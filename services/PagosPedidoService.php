<?php
/**
 * PagosPedidoService - Servicio de distribución de pagos
 *
 * Maneja la distribución automática de pagos cuando un pedido se marca como entregado:
 * - Acredita al negocio (subtotal - comisión) - SOLO si pago es digital
 * - Acredita al repartidor (envío + propina) - SOLO si pago es digital
 * - Si pago es EFECTIVO: registra ganancia pero NO es retirable (ya lo tienen en mano)
 * - Si pago es EFECTIVO: el negocio genera deuda de comisión a QuickBite
 *
 * @version 1.1.0
 * @date 2026-01-07
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/quickbite_fees.php';

class PagosPedidoService {
    private $pdo;

    // Estados de pedido
    const ESTADO_ENTREGADO = 6;

    // Métodos de pago
    const METODOS_EFECTIVO = ['efectivo', 'cash', 'contra_entrega'];
    const METODOS_DIGITALES = ['tarjeta', 'card', 'stripe', 'mercadopago', 'mp', 'paypal', 'transferencia'];

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            $database = new Database();
            $this->pdo = $database->getConnection();
        }
    }

    /**
     * Verificar si el método de pago es efectivo
     */
    private function esMetodoPagoEfectivo(?string $metodoPago): bool {
        if (empty($metodoPago)) return false;
        $metodo = strtolower(trim($metodoPago));
        return in_array($metodo, self::METODOS_EFECTIVO);
    }

    /**
     * Procesar distribución de pagos cuando un pedido se marca como entregado
     *
     * @param int $idPedido ID del pedido
     * @return array Resultado de la operación
     */
    public function procesarPagosPedido(int $idPedido): array {
        try {
            // Obtener datos del pedido
            $pedido = $this->obtenerPedido($idPedido);

            if (!$pedido) {
                return ['success' => false, 'error' => 'Pedido no encontrado'];
            }

            // Verificar que el pedido está entregado
            if ($pedido['id_estado'] != self::ESTADO_ENTREGADO) {
                return ['success' => false, 'error' => 'El pedido no está en estado entregado'];
            }

            // Verificar que no se haya procesado ya
            if ($this->pedidoYaProcesado($idPedido)) {
                return ['success' => false, 'error' => 'Este pedido ya fue procesado para pagos'];
            }

            // Determinar si es pago en efectivo
            $esEfectivo = $this->esMetodoPagoEfectivo($pedido['metodo_pago'] ?? '');

            // Asegurar que la tabla de deudas existe ANTES de la transacción
            // (DDL statements causan implicit commits en MySQL)
            if ($esEfectivo) {
                $this->asegurarTablaDeudas();
            }

            // Iniciar transacción
            $this->pdo->beginTransaction();

            // Calcular distribución
            $distribucion = $this->calcularDistribucion($pedido);
            $distribucion['metodo_pago'] = $pedido['metodo_pago'];
            $distribucion['es_efectivo'] = $esEfectivo;

            // Actualizar pedido con montos calculados
            $this->actualizarMontosEnPedido($idPedido, $distribucion);

            if ($esEfectivo) {
                // PAGO EN EFECTIVO:
                // - El dinero ya lo tienen físicamente
                // - Registrar como ganancia (historial) pero NO retirable
                // - El negocio debe la comisión a QuickBite

                $resultadoNegocio = $this->registrarGananciaEfectivo(
                    $pedido['id_negocio'],
                    $idPedido,
                    $distribucion['pago_negocio'],
                    $distribucion['comision_plataforma'],
                    'business'
                );

                // Registrar deuda de comisión del negocio a QuickBite
                $this->registrarDeudaComision(
                    $pedido['id_negocio'],
                    $idPedido,
                    $distribucion['comision_plataforma']
                );

                $resultadoRepartidor = null;
                if ($pedido['id_repartidor']) {
                    $resultadoRepartidor = $this->registrarGananciaEfectivo(
                        $pedido['id_repartidor'],
                        $idPedido,
                        $distribucion['pago_repartidor'],
                        0,
                        'courier'
                    );
                }

                $distribucion['nota'] = 'Pago en efectivo - ganancia registrada pero NO retirable (ya cobrado en mano)';

            } else {
                // PAGO DIGITAL:
                // - Acreditar saldo retirable a negocio y repartidor

                $resultadoNegocio = $this->acreditarNegocio(
                    $pedido['id_negocio'],
                    $idPedido,
                    $distribucion['pago_negocio'],
                    $distribucion['comision_plataforma']
                );

                $resultadoRepartidor = null;
                if ($pedido['id_repartidor']) {
                    $resultadoRepartidor = $this->acreditarRepartidor(
                        $pedido['id_repartidor'],
                        $idPedido,
                        $distribucion['pago_repartidor'],
                        $distribucion['propina']
                    );
                }

                $distribucion['nota'] = 'Pago digital - saldo acreditado y disponible para retiro';
            }

            // Registrar que el pedido fue procesado
            $this->marcarPedidoProcesado($idPedido);

            $this->pdo->commit();

            return [
                'success' => true,
                'pedido_id' => $idPedido,
                'es_efectivo' => $esEfectivo,
                'distribucion' => $distribucion,
                'negocio' => $resultadoNegocio,
                'repartidor' => $resultadoRepartidor
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error procesando pagos pedido #$idPedido: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener datos del pedido
     */
    private function obtenerPedido(int $idPedido): ?array {
        $stmt = $this->pdo->prepare("
            SELECT p.*, n.es_premium, n.comision_porcentaje as comision_negocio,
                   r.id_usuario as id_usuario_repartidor
            FROM pedidos p
            LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
            LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
            WHERE p.id_pedido = ?
        ");
        $stmt->execute([$idPedido]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Verificar si el pedido ya fue procesado para pagos
     */
    private function pedidoYaProcesado(int $idPedido): bool {
        // Verificar si hay transacciones de este pedido
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM wallet_transacciones
            WHERE id_pedido = ? AND tipo IN ('ingreso', 'ingreso_pedido', 'ingreso_entrega')
        ");
        $stmt->execute([$idPedido]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Calcular distribución del pedido
     */
    private function calcularDistribucion(array $pedido): array {
        $subtotalProductos = floatval($pedido['total_productos'] ?? 0);
        $costoEnvio = floatval($pedido['costo_envio'] ?? 25);
        $propina = floatval($pedido['propina'] ?? 0);
        $cargoServicio = floatval($pedido['cargo_servicio'] ?? 5);
        $esPremium = (bool)($pedido['es_premium'] ?? false);

        // Usar configuración de comisiones
        $porcentajeComision = $esPremium ? QUICKBITE_COMISION_PREMIUM : QUICKBITE_COMISION_BASICA;

        // Calcular comisión
        $comisionPlataforma = round($subtotalProductos * ($porcentajeComision / 100), 2);

        // Pago al negocio (productos menos comisión)
        $pagoNegocio = $subtotalProductos - $comisionPlataforma;

        // Pago al repartidor (envío + propina) - mínimo garantizado $25
        $pagoRepartidorEnvio = max($costoEnvio, QUICKBITE_ENVIO_MINIMO);
        $pagoRepartidor = $pagoRepartidorEnvio + $propina;

        // Ganancia QuickBite
        $gananciaQuickBite = $comisionPlataforma + $cargoServicio;

        // Si hubo subsidio de envío (cliente pagó menos de lo que recibe repartidor)
        $subsidioEnvio = max(0, $pagoRepartidorEnvio - $costoEnvio);
        if ($subsidioEnvio > 0) {
            $gananciaQuickBite -= $subsidioEnvio;
        }

        return [
            'subtotal_productos' => $subtotalProductos,
            'costo_envio' => $costoEnvio,
            'propina' => $propina,
            'cargo_servicio' => $cargoServicio,
            'comision_porcentaje' => $porcentajeComision,
            'comision_plataforma' => $comisionPlataforma,
            'pago_negocio' => round($pagoNegocio, 2),
            'pago_repartidor' => round($pagoRepartidor, 2),
            'subsidio_envio' => round($subsidioEnvio, 2),
            'ganancia_quickbite' => round($gananciaQuickBite, 2)
        ];
    }

    /**
     * Actualizar montos calculados en el pedido
     */
    private function actualizarMontosEnPedido(int $idPedido, array $distribucion): void {
        $stmt = $this->pdo->prepare("
            UPDATE pedidos SET
                comision_plataforma = ?,
                comision_porcentaje = ?,
                pago_negocio = ?,
                pago_repartidor = ?,
                subsidio_envio = ?
            WHERE id_pedido = ?
        ");
        $stmt->execute([
            $distribucion['comision_plataforma'],
            $distribucion['comision_porcentaje'],
            $distribucion['pago_negocio'],
            $distribucion['pago_repartidor'],
            $distribucion['subsidio_envio'],
            $idPedido
        ]);
    }

    /**
     * Acreditar pago al negocio
     */
    private function acreditarNegocio(int $idNegocio, int $idPedido, float $monto, float $comision): array {
        // Obtener o crear wallet del negocio
        $wallet = $this->obtenerOCrearWallet($idNegocio, 'business');

        if (!$wallet) {
            throw new Exception("No se pudo obtener/crear wallet del negocio");
        }

        $saldoAnterior = floatval($wallet['saldo_disponible']);
        $saldoNuevo = $saldoAnterior + $monto;

        // Actualizar saldo
        $stmt = $this->pdo->prepare("
            UPDATE wallets SET
                saldo_disponible = saldo_disponible + ?,
                fecha_actualizacion = NOW()
            WHERE id_wallet = ?
        ");
        $stmt->execute([$monto, $wallet['id_wallet']]);

        // Registrar transacción
        $stmt = $this->pdo->prepare("
            INSERT INTO wallet_transacciones
            (id_wallet, tipo, monto, comision, descripcion, estado, id_pedido, fecha)
            VALUES (?, 'ingreso', ?, ?, ?, 'completado', ?, NOW())
        ");
        $stmt->execute([
            $wallet['id_wallet'],
            $monto,
            $comision,
            "Venta pedido #$idPedido (comisión {$comision})",
            $idPedido
        ]);

        return [
            'wallet_id' => $wallet['id_wallet'],
            'monto_acreditado' => $monto,
            'comision_descontada' => $comision,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo
        ];
    }

    /**
     * Acreditar pago al repartidor
     */
    private function acreditarRepartidor(int $idRepartidor, int $idPedido, float $monto, float $propina): array {
        // Obtener id_usuario del repartidor
        $stmt = $this->pdo->prepare("SELECT id_usuario FROM repartidores WHERE id_repartidor = ?");
        $stmt->execute([$idRepartidor]);
        $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$repartidor) {
            throw new Exception("Repartidor no encontrado");
        }

        // Obtener o crear wallet del repartidor
        $wallet = $this->obtenerOCrearWallet($repartidor['id_usuario'], 'courier');

        if (!$wallet) {
            throw new Exception("No se pudo obtener/crear wallet del repartidor");
        }

        $saldoAnterior = floatval($wallet['saldo_disponible']);
        $saldoNuevo = $saldoAnterior + $monto;

        // Actualizar saldo en wallet
        $stmt = $this->pdo->prepare("
            UPDATE wallets SET
                saldo_disponible = saldo_disponible + ?,
                fecha_actualizacion = NOW()
            WHERE id_wallet = ?
        ");
        $stmt->execute([$monto, $wallet['id_wallet']]);

        // Registrar transacción
        $propinaTexto = $propina > 0 ? " + propina \${$propina}" : "";
        $stmt = $this->pdo->prepare("
            INSERT INTO wallet_transacciones
            (id_wallet, tipo, monto, descripcion, estado, id_pedido, fecha)
            VALUES (?, 'ingreso', ?, ?, 'completado', ?, NOW())
        ");
        $stmt->execute([
            $wallet['id_wallet'],
            $monto,
            "Entrega pedido #$idPedido{$propinaTexto}",
            $idPedido
        ]);

        // Registrar en ganancias_repartidor
        $stmt = $this->pdo->prepare("
            INSERT INTO ganancias_repartidor (id_repartidor, id_pedido, ganancia, fecha_ganancia)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$idRepartidor, $idPedido, $monto]);

        // Actualizar total_ganancias del repartidor
        $stmt = $this->pdo->prepare("
            UPDATE repartidores SET
                total_ganancias = COALESCE(total_ganancias, 0) + ?,
                total_entregas = COALESCE(total_entregas, 0) + 1
            WHERE id_repartidor = ?
        ");
        $stmt->execute([$monto, $idRepartidor]);

        return [
            'wallet_id' => $wallet['id_wallet'],
            'monto_acreditado' => $monto,
            'propina_incluida' => $propina,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo
        ];
    }

    /**
     * Obtener o crear wallet para usuario
     */
    private function obtenerOCrearWallet(int $idUsuario, string $tipoUsuario): ?array {
        // Buscar wallet existente
        $stmt = $this->pdo->prepare("
            SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = ?
        ");
        $stmt->execute([$idUsuario, $tipoUsuario]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($wallet) {
            return $wallet;
        }

        // Crear nuevo wallet
        $cuentaExterna = 'LOCAL_' . strtoupper(substr($tipoUsuario, 0, 3)) . '_' . $idUsuario . '_' . time();

        $stmt = $this->pdo->prepare("
            INSERT INTO wallets (id_usuario, tipo_usuario, cuenta_externa_id, saldo_disponible, saldo_pendiente, estado, fecha_creacion)
            VALUES (?, ?, ?, 0.00, 0.00, 'activo', NOW())
        ");
        $stmt->execute([$idUsuario, $tipoUsuario, $cuentaExterna]);

        $idWallet = $this->pdo->lastInsertId();

        return [
            'id_wallet' => $idWallet,
            'id_usuario' => $idUsuario,
            'tipo_usuario' => $tipoUsuario,
            'saldo_disponible' => 0,
            'saldo_pendiente' => 0,
            'estado' => 'activo'
        ];
    }

    /**
     * Marcar pedido como procesado para pagos
     */
    private function marcarPedidoProcesado(int $idPedido): void {
        // Se marca implícitamente por las transacciones creadas
        // Opcionalmente podríamos agregar un campo pago_procesado en pedidos
    }

    /**
     * Procesar retiro de fondos
     */
    public function solicitarRetiro(int $idWallet, float $monto, string $clabe): array {
        try {
            // Verificar wallet
            $stmt = $this->pdo->prepare("SELECT * FROM wallets WHERE id_wallet = ?");
            $stmt->execute([$idWallet]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                return ['success' => false, 'error' => 'Wallet no encontrada'];
            }

            $saldoDisponible = floatval($wallet['saldo_disponible']);

            // Validar monto mínimo
            if ($monto < 100) {
                return ['success' => false, 'error' => 'El monto mínimo de retiro es $100'];
            }

            // Validar saldo suficiente
            if ($monto > $saldoDisponible) {
                return ['success' => false, 'error' => 'Saldo insuficiente'];
            }

            // Validar CLABE
            if (!preg_match('/^\d{18}$/', $clabe)) {
                return ['success' => false, 'error' => 'CLABE inválida (debe tener 18 dígitos)'];
            }

            $this->pdo->beginTransaction();

            // Crear solicitud de retiro
            $stmt = $this->pdo->prepare("
                INSERT INTO wallet_retiros (id_wallet, monto, clabe, estado, fecha_solicitud)
                VALUES (?, ?, ?, 'procesando', NOW())
            ");
            $stmt->execute([$idWallet, $monto, $clabe]);
            $idRetiro = $this->pdo->lastInsertId();

            // Mover de disponible a pendiente
            $stmt = $this->pdo->prepare("
                UPDATE wallets SET
                    saldo_disponible = saldo_disponible - ?,
                    saldo_pendiente = saldo_pendiente + ?,
                    fecha_actualizacion = NOW()
                WHERE id_wallet = ?
            ");
            $stmt->execute([$monto, $monto, $idWallet]);

            // Registrar transacción
            $stmt = $this->pdo->prepare("
                INSERT INTO wallet_transacciones (id_wallet, tipo, monto, descripcion, estado, fecha)
                VALUES (?, 'retiro', ?, 'Solicitud de retiro a CLABE', 'pendiente', NOW())
            ");
            $stmt->execute([$idWallet, -$monto]);

            $this->pdo->commit();

            return [
                'success' => true,
                'retiro_id' => $idRetiro,
                'monto' => $monto,
                'mensaje' => 'Solicitud de retiro creada. Se procesará en 1-3 días hábiles.'
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener historial de transacciones de un wallet
     */
    public function obtenerTransacciones(int $idWallet, int $limite = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT wt.*, p.id_negocio, p.total_productos
            FROM wallet_transacciones wt
            LEFT JOIN pedidos p ON wt.id_pedido = p.id_pedido
            WHERE wt.id_wallet = ?
            ORDER BY wt.fecha DESC
            LIMIT ?
        ");
        $stmt->execute([$idWallet, $limite]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener resumen de wallet
     */
    public function obtenerResumenWallet(int $idWallet): array {
        $stmt = $this->pdo->prepare("SELECT * FROM wallets WHERE id_wallet = ?");
        $stmt->execute([$idWallet]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            return [];
        }

        // Estadísticas del mes
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(CASE WHEN tipo = 'ingreso' AND estado = 'completado' THEN 1 END) as total_ingresos,
                SUM(CASE WHEN tipo = 'ingreso' AND estado = 'completado' THEN monto ELSE 0 END) as monto_ingresos,
                SUM(CASE WHEN tipo = 'ingreso' AND estado = 'completado' THEN COALESCE(comision, 0) ELSE 0 END) as total_comisiones,
                COUNT(CASE WHEN tipo = 'retiro' THEN 1 END) as total_retiros,
                SUM(CASE WHEN tipo = 'retiro' AND estado = 'completado' THEN ABS(monto) ELSE 0 END) as monto_retirado
            FROM wallet_transacciones
            WHERE id_wallet = ?
            AND MONTH(fecha) = MONTH(CURRENT_DATE())
            AND YEAR(fecha) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$idWallet]);
        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'wallet' => $wallet,
            'estadisticas_mes' => $estadisticas
        ];
    }

    /**
     * Registrar ganancia en efectivo (NO retirable - ya la tienen en mano)
     * Solo se registra para historial y estadísticas
     *
     * @param int $idUsuario ID del usuario (negocio o repartidor)
     * @param int $idPedido ID del pedido
     * @param float $monto Monto ganado
     * @param float $comision Comisión aplicada (solo para negocios)
     * @param string $tipoUsuario 'business' o 'courier'
     * @return array Resultado
     */
    private function registrarGananciaEfectivo(int $idUsuario, int $idPedido, float $monto, float $comision, string $tipoUsuario): array {
        // Obtener o crear wallet
        $wallet = $this->obtenerOCrearWallet($idUsuario, $tipoUsuario);

        if (!$wallet) {
            throw new Exception("No se pudo obtener/crear wallet para $tipoUsuario");
        }

        // Registrar transacción como "ganancia_efectivo" (NO suma a saldo_disponible)
        $descripcion = $tipoUsuario === 'business'
            ? "Venta efectivo pedido #$idPedido (comisión \${$comision} adeudada)"
            : "Entrega efectivo pedido #$idPedido";

        $stmt = $this->pdo->prepare("
            INSERT INTO wallet_transacciones
            (id_wallet, tipo, monto, comision, descripcion, estado, id_pedido, fecha, es_efectivo)
            VALUES (?, 'ganancia_efectivo', ?, ?, ?, 'completado', ?, NOW(), 1)
        ");

        // Verificar si existe la columna es_efectivo
        try {
            $stmt->execute([
                $wallet['id_wallet'],
                $monto,
                $comision,
                $descripcion,
                $idPedido
            ]);
        } catch (PDOException $e) {
            // Si falla por columna es_efectivo, intentar sin ella
            if (strpos($e->getMessage(), 'es_efectivo') !== false) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO wallet_transacciones
                    (id_wallet, tipo, monto, comision, descripcion, estado, id_pedido, fecha)
                    VALUES (?, 'ganancia_efectivo', ?, ?, ?, 'completado', ?, NOW())
                ");
                $stmt->execute([
                    $wallet['id_wallet'],
                    $monto,
                    $comision,
                    $descripcion,
                    $idPedido
                ]);
            } else {
                throw $e;
            }
        }

        // Si es repartidor, también registrar en ganancias_repartidor
        if ($tipoUsuario === 'courier') {
            // Obtener id_repartidor desde id_usuario
            $stmt = $this->pdo->prepare("SELECT id_repartidor FROM repartidores WHERE id_usuario = ?");
            $stmt->execute([$idUsuario]);
            $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($repartidor) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO ganancias_repartidor (id_repartidor, id_pedido, ganancia, fecha_ganancia, es_efectivo)
                    VALUES (?, ?, ?, NOW(), 1)
                ");

                try {
                    $stmt->execute([$repartidor['id_repartidor'], $idPedido, $monto]);
                } catch (PDOException $e) {
                    // Si falla por columna es_efectivo, intentar sin ella
                    if (strpos($e->getMessage(), 'es_efectivo') !== false) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO ganancias_repartidor (id_repartidor, id_pedido, ganancia, fecha_ganancia)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmt->execute([$repartidor['id_repartidor'], $idPedido, $monto]);
                    } else {
                        throw $e;
                    }
                }

                // Actualizar estadísticas del repartidor
                $stmt = $this->pdo->prepare("
                    UPDATE repartidores SET
                        total_ganancias = COALESCE(total_ganancias, 0) + ?,
                        total_entregas = COALESCE(total_entregas, 0) + 1
                    WHERE id_repartidor = ?
                ");
                $stmt->execute([$monto, $repartidor['id_repartidor']]);
            }
        }

        return [
            'wallet_id' => $wallet['id_wallet'],
            'monto_registrado' => $monto,
            'comision' => $comision,
            'es_efectivo' => true,
            'retirable' => false,
            'nota' => 'Ganancia en efectivo - ya cobrada en mano, no disponible para retiro'
        ];
    }

    /**
     * Registrar deuda de comisión del negocio hacia QuickBite
     * Cuando un pedido se paga en efectivo, el negocio debe la comisión a QuickBite
     *
     * @param int $idNegocio ID del negocio
     * @param int $idPedido ID del pedido
     * @param float $comision Monto de la comisión adeudada
     */
    private function registrarDeudaComision(int $idNegocio, int $idPedido, float $comision): void {
        if ($comision <= 0) {
            return;
        }

        // Tabla ya asegurada antes de la transacción principal
        // Registrar la deuda
        $stmt = $this->pdo->prepare("
            INSERT INTO deudas_comisiones_negocios
            (id_negocio, id_pedido, monto_comision, fecha_generacion, estado)
            VALUES (?, ?, ?, NOW(), 'pendiente')
        ");
        $stmt->execute([$idNegocio, $idPedido, $comision]);

        // Actualizar saldo deudor del negocio
        $stmt = $this->pdo->prepare("
            UPDATE negocios SET
                saldo_deudor = COALESCE(saldo_deudor, 0) + ?
            WHERE id_negocio = ?
        ");

        try {
            $stmt->execute([$comision, $idNegocio]);
        } catch (PDOException $e) {
            // Si la columna no existe, crearla
            if (strpos($e->getMessage(), 'saldo_deudor') !== false) {
                $this->pdo->exec("ALTER TABLE negocios ADD COLUMN saldo_deudor DECIMAL(10,2) DEFAULT 0.00");
                $stmt->execute([$comision, $idNegocio]);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Asegurar que existe la tabla de deudas de comisiones para negocios
     */
    private function asegurarTablaDeudas(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS deudas_comisiones_negocios (
                id_deuda INT AUTO_INCREMENT PRIMARY KEY,
                id_negocio INT NOT NULL,
                id_pedido INT NOT NULL,
                monto_comision DECIMAL(10,2) NOT NULL,
                fecha_generacion DATETIME NOT NULL,
                fecha_pago DATETIME NULL,
                estado ENUM('pendiente', 'pagada', 'cancelada') DEFAULT 'pendiente',
                metodo_pago VARCHAR(50) NULL,
                referencia_pago VARCHAR(100) NULL,
                INDEX idx_negocio (id_negocio),
                INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Obtener deudas pendientes de un negocio
     */
    public function obtenerDeudasNegocio(int $idNegocio): array {
        $this->asegurarTablaDeudas();

        $stmt = $this->pdo->prepare("
            SELECT dc.*, p.monto_total, p.fecha_creacion as fecha_pedido
            FROM deudas_comisiones_negocios dc
            LEFT JOIN pedidos p ON dc.id_pedido = p.id_pedido
            WHERE dc.id_negocio = ? AND dc.estado = 'pendiente'
            ORDER BY dc.fecha_generacion DESC
        ");
        $stmt->execute([$idNegocio]);

        $deudas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular total adeudado
        $totalDeuda = 0;
        foreach ($deudas as $deuda) {
            $totalDeuda += floatval($deuda['monto_comision']);
        }

        return [
            'deudas' => $deudas,
            'total_adeudado' => round($totalDeuda, 2),
            'cantidad_pedidos' => count($deudas)
        ];
    }

    /**
     * Pagar deudas de comisiones
     */
    public function pagarDeudas(int $idNegocio, array $idsDeudas, string $metodoPago, string $referencia = ''): array {
        try {
            $this->pdo->beginTransaction();

            $montoTotal = 0;

            foreach ($idsDeudas as $idDeuda) {
                $stmt = $this->pdo->prepare("
                    SELECT * FROM deudas_comisiones_negocios
                    WHERE id_deuda = ? AND id_negocio = ? AND estado = 'pendiente'
                ");
                $stmt->execute([$idDeuda, $idNegocio]);
                $deuda = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($deuda) {
                    $montoTotal += floatval($deuda['monto_comision']);

                    // Marcar como pagada
                    $stmt = $this->pdo->prepare("
                        UPDATE deudas_comisiones_negocios SET
                            estado = 'pagada',
                            fecha_pago = NOW(),
                            metodo_pago = ?,
                            referencia_pago = ?
                        WHERE id_deuda = ?
                    ");
                    $stmt->execute([$metodoPago, $referencia, $idDeuda]);
                }
            }

            // Actualizar saldo deudor del negocio
            $stmt = $this->pdo->prepare("
                UPDATE negocios SET
                    saldo_deudor = GREATEST(0, COALESCE(saldo_deudor, 0) - ?)
                WHERE id_negocio = ?
            ");
            $stmt->execute([$montoTotal, $idNegocio]);

            $this->pdo->commit();

            return [
                'success' => true,
                'monto_pagado' => $montoTotal,
                'deudas_liquidadas' => count($idsDeudas)
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener resumen de ganancias (efectivo vs digital)
     */
    public function obtenerResumenGanancias(int $idWallet): array {
        // Ganancias digitales (retirables)
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(monto), 0) as total_digital,
                COUNT(*) as cantidad_digital
            FROM wallet_transacciones
            WHERE id_wallet = ? AND tipo = 'ingreso' AND estado = 'completado'
        ");
        $stmt->execute([$idWallet]);
        $digital = $stmt->fetch(PDO::FETCH_ASSOC);

        // Ganancias en efectivo (no retirables)
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(monto), 0) as total_efectivo,
                COUNT(*) as cantidad_efectivo
            FROM wallet_transacciones
            WHERE id_wallet = ? AND tipo = 'ganancia_efectivo' AND estado = 'completado'
        ");
        $stmt->execute([$idWallet]);
        $efectivo = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'ganancias_digitales' => [
                'total' => round(floatval($digital['total_digital']), 2),
                'cantidad_pedidos' => intval($digital['cantidad_digital']),
                'retirable' => true
            ],
            'ganancias_efectivo' => [
                'total' => round(floatval($efectivo['total_efectivo']), 2),
                'cantidad_pedidos' => intval($efectivo['cantidad_efectivo']),
                'retirable' => false
            ],
            'total_general' => round(floatval($digital['total_digital']) + floatval($efectivo['total_efectivo']), 2)
        ];
    }
}
