<?php
/**
 * QuickBite - Servicio de Cálculo de Comisiones
 *
 * Centraliza toda la lógica de cálculo de comisiones, envíos y distribución de pagos.
 *
 * MODELO DE NEGOCIO JUSTO:
 * - Cliente: Precios justos, envío transparente, propina opcional
 * - Negocio: Comisión baja (8-12%), sin rentas obligatorias
 * - Repartidor: Mínimo $25 garantizado, 100% propinas
 * - QuickBite: Volumen > margen
 */

require_once __DIR__ . '/../config/quickbite_fees.php';

class ComisionService {
    private $conn;

    public function __construct($db = null) {
        $this->conn = $db;
    }

    /**
     * Calcular la distribución completa de un pedido
     *
     * @param float $subtotalProductos Subtotal de productos
     * @param float $distanciaKm Distancia en kilómetros
     * @param float $propina Propina del cliente
     * @param int $idUsuario ID del usuario (para verificar membresía)
     * @param int $idNegocio ID del negocio (para verificar si es premium)
     * @return array Distribución completa del pedido
     */
    public function calcularDistribucion($subtotalProductos, $distanciaKm, $propina = 0, $idUsuario = null, $idNegocio = null) {
        // Verificar estados de membresía
        $clienteEsMiembro = $idUsuario ? $this->verificarMembresiaClub($idUsuario) : false;
        $negocioEsPremium = $idNegocio ? $this->verificarNegocioPremium($idNegocio) : false;

        return calcularDistribucionPedido($subtotalProductos, $distanciaKm, $propina, $clienteEsMiembro, $negocioEsPremium);
    }

    /**
     * Verificar si un usuario es miembro (QuickBite Club / Premium)
     * Compatible con el sistema existente que usa es_miembro
     *
     * @param int $idUsuario ID del usuario
     * @return bool
     */
    public function verificarMembresiaClub($idUsuario) {
        if (!$this->conn) {
            return false;
        }

        try {
            // Compatible con sistema existente: usa es_miembro y fecha_fin_membresia
            $query = "SELECT es_miembro, fecha_fin_membresia
                      FROM usuarios
                      WHERE id_usuario = ? AND es_miembro = 1 AND (fecha_fin_membresia IS NULL OR fecha_fin_membresia >= CURDATE())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$idUsuario]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && $result['es_miembro'] == 1;
        } catch (Exception $e) {
            error_log("Error verificando membresía: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si un negocio tiene membresía premium activa
     *
     * @param int $idNegocio ID del negocio
     * @return bool
     */
    public function verificarNegocioPremium($idNegocio) {
        if (!$this->conn) {
            return false;
        }

        try {
            $query = "SELECT es_premium, fecha_fin_premium
                      FROM negocios
                      WHERE id_negocio = ? AND es_premium = 1 AND (fecha_fin_premium IS NULL OR fecha_fin_premium >= CURDATE())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$idNegocio]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && $result['es_premium'] == 1;
        } catch (Exception $e) {
            error_log("Error verificando negocio premium: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener el porcentaje de comisión para un negocio
     *
     * @param int $idNegocio ID del negocio
     * @return float Porcentaje de comisión
     */
    public function obtenerComisionNegocio($idNegocio) {
        if ($this->verificarNegocioPremium($idNegocio)) {
            return QUICKBITE_COMISION_PREMIUM;
        }
        return QUICKBITE_COMISION_BASICA;
    }

    /**
     * Calcular el costo de envío para un pedido - NUEVO MODELO
     *
     * @param float $distanciaKm Distancia en kilómetros
     * @param int $idUsuario ID del usuario (opcional, para verificar membresía)
     * @param float $subtotalPedido Subtotal del pedido (para descuentos de membresía)
     * @return float Costo de envío
     */
    public function calcularEnvio($distanciaKm, $idUsuario = null, $subtotalPedido = 0) {
        $esMiembro = $idUsuario ? $this->verificarMembresiaClub($idUsuario) : false;
        return calcularCostoEnvioQuickBite($distanciaKm, $esMiembro, $subtotalPedido);
    }

    /**
     * Calcular el cargo de servicio para un pedido
     *
     * @param int $idUsuario ID del usuario (opcional, para verificar membresía)
     * @return float Cargo de servicio
     */
    public function calcularCargoServicio($idUsuario = null) {
        $esMiembro = $idUsuario ? $this->verificarMembresiaClub($idUsuario) : false;
        return calcularCargoServicio($esMiembro);
    }

    /**
     * Calcular el pago al repartidor
     *
     * @param float $distanciaKm Distancia en kilómetros
     * @param float $propina Propina del cliente
     * @return float Pago total al repartidor
     */
    public function calcularPagoRepartidor($distanciaKm, $propina = 0) {
        return calcularPagoRepartidor($distanciaKm, $propina);
    }

    /**
     * Calcular la comisión de QuickBite sobre los productos
     *
     * @param float $subtotalProductos Subtotal de productos
     * @param int $idNegocio ID del negocio
     * @return array [porcentaje, monto_comision, monto_negocio]
     */
    public function calcularComision($subtotalProductos, $idNegocio = null) {
        $esPremium = $idNegocio ? $this->verificarNegocioPremium($idNegocio) : false;
        return calcularComisionNegocio($subtotalProductos, $esPremium);
    }

    /**
     * Calcular el total del pedido con todos los cargos - NUEVO MODELO
     *
     * @param float $subtotalProductos Subtotal de productos
     * @param float $distanciaKm Distancia en kilómetros
     * @param float $propina Propina del cliente
     * @param int $idUsuario ID del usuario
     * @return array Desglose completo del pedido
     */
    public function calcularTotalPedido($subtotalProductos, $distanciaKm, $propina = 0, $idUsuario = null) {
        $esMiembro = $idUsuario ? $this->verificarMembresiaClub($idUsuario) : false;

        // Pasar subtotal para calcular descuentos de membresía
        $costoEnvio = $this->calcularEnvio($distanciaKm, $idUsuario, $subtotalProductos);
        $cargoServicio = $this->calcularCargoServicio($idUsuario);
        $total = $subtotalProductos + $costoEnvio + $cargoServicio + $propina;

        // Determinar tipo de descuento en envío
        $tipoDescuentoEnvio = 'ninguno';
        if ($esMiembro) {
            if ($subtotalProductos >= QUICKBITE_ENVIO_GRATIS_MONTO) {
                $tipoDescuentoEnvio = 'gratis';
            } elseif ($subtotalProductos >= QUICKBITE_ENVIO_MITAD_MONTO) {
                $tipoDescuentoEnvio = '50%';
            }
        }

        return [
            'subtotal_productos' => round($subtotalProductos, 2),
            'costo_envio' => round($costoEnvio, 2),
            'cargo_servicio' => round($cargoServicio, 2),
            'propina' => round($propina, 2),
            'total' => round($total, 2),
            'es_miembro_club' => $esMiembro,
            'descuento_envio' => $tipoDescuentoEnvio,
            'sin_cargo_servicio' => true // Ahora es gratis para todos
        ];
    }

    /**
     * Registrar el ahorro de un miembro Club
     *
     * @param int $idUsuario ID del usuario
     * @param int $idPedido ID del pedido
     * @param string $tipoAhorro Tipo de ahorro (envio, cargo_servicio, etc.)
     * @param float $montoAhorrado Monto ahorrado
     * @param string $descripcion Descripción del ahorro
     * @return bool
     */
    public function registrarAhorroMiembro($idUsuario, $idPedido, $tipoAhorro, $montoAhorrado, $descripcion = '') {
        if (!$this->conn || !$this->verificarMembresiaClub($idUsuario)) {
            return false;
        }

        try {
            $query = "INSERT INTO quickbite_club_ahorros (id_usuario, id_pedido, tipo_ahorro, monto_ahorrado, descripcion)
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$idUsuario, $idPedido, $tipoAhorro, $montoAhorrado, $descripcion]);
        } catch (Exception $e) {
            error_log("Error registrando ahorro: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener resumen de comisiones para un periodo
     *
     * @param int $idNegocio ID del negocio
     * @param string $fechaInicio Fecha de inicio (Y-m-d)
     * @param string $fechaFin Fecha de fin (Y-m-d)
     * @return array Resumen de comisiones
     */
    public function obtenerResumenComisiones($idNegocio, $fechaInicio, $fechaFin) {
        if (!$this->conn) {
            return null;
        }

        try {
            $query = "SELECT
                        COUNT(*) as total_pedidos,
                        SUM(total_productos) as ventas_totales,
                        SUM(total_productos * (CASE WHEN n.es_premium = 1 THEN ? ELSE ? END / 100)) as comisiones_pagadas,
                        AVG(total_productos * (CASE WHEN n.es_premium = 1 THEN ? ELSE ? END / 100)) as comision_promedio
                      FROM pedidos p
                      JOIN negocios n ON p.id_negocio = n.id_negocio
                      WHERE p.id_negocio = ?
                        AND p.id_estado = 6
                        AND DATE(p.fecha_creacion) BETWEEN ? AND ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                QUICKBITE_COMISION_PREMIUM,
                QUICKBITE_COMISION_BASICA,
                QUICKBITE_COMISION_PREMIUM,
                QUICKBITE_COMISION_BASICA,
                $idNegocio,
                $fechaInicio,
                $fechaFin
            ]);

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                $esPremium = $this->verificarNegocioPremium($idNegocio);
                $resultado['es_premium'] = $esPremium;
                $resultado['comision_actual'] = $esPremium ? QUICKBITE_COMISION_PREMIUM : QUICKBITE_COMISION_BASICA;
                $resultado['ganancia_neta'] = $resultado['ventas_totales'] - $resultado['comisiones_pagadas'];
            }

            return $resultado;
        } catch (Exception $e) {
            error_log("Error obteniendo resumen comisiones: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verificar si conviene membresía premium para un negocio
     *
     * @param int $idNegocio ID del negocio
     * @param int $meses Número de meses a analizar
     * @return array Análisis de conveniencia
     */
    public function analizarConvenienciaPremium($idNegocio, $meses = 3) {
        if (!$this->conn) {
            return null;
        }

        try {
            // Obtener ventas promedio mensual
            $query = "SELECT AVG(ventas_mes) as promedio_mensual FROM (
                        SELECT SUM(total_productos) as ventas_mes
                        FROM pedidos
                        WHERE id_negocio = ? AND id_estado = 6
                        GROUP BY YEAR(fecha_creacion), MONTH(fecha_creacion)
                        ORDER BY YEAR(fecha_creacion) DESC, MONTH(fecha_creacion) DESC
                        LIMIT ?
                      ) as ventas";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$idNegocio, $meses]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            $ventasPromedio = $resultado['promedio_mensual'] ?? 0;

            return verificarConvenienciaPremium($ventasPromedio);
        } catch (Exception $e) {
            error_log("Error analizando conveniencia premium: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener estadísticas de distribución de ingresos
     *
     * @param string $fechaInicio Fecha de inicio (Y-m-d)
     * @param string $fechaFin Fecha de fin (Y-m-d)
     * @return array Estadísticas de distribución
     */
    public function obtenerEstadisticasDistribucion($fechaInicio, $fechaFin) {
        if (!$this->conn) {
            return null;
        }

        try {
            $query = "SELECT
                        COUNT(*) as total_pedidos,
                        SUM(total_productos) as total_productos,
                        SUM(costo_envio) as total_envios,
                        SUM(cargo_servicio) as total_cargos_servicio,
                        SUM(propina) as total_propinas,
                        SUM(monto_total) as total_cobrado,
                        AVG(monto_total) as ticket_promedio
                      FROM pedidos
                      WHERE id_estado = 6
                        AND DATE(fecha_creacion) BETWEEN ? AND ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$fechaInicio, $fechaFin]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verificar si la distancia está dentro del rango de entrega
     *
     * @param float $distanciaKm Distancia en kilómetros
     * @return bool
     */
    public function dentroDeRangoEntrega($distanciaKm) {
        return $distanciaKm <= QUICKBITE_DISTANCIA_MAXIMA;
    }

    /**
     * Obtener información de beneficios del miembro Club
     *
     * @param int $idUsuario ID del usuario
     * @return array|null Información de beneficios
     */
    public function obtenerBeneficiosMiembro($idUsuario) {
        if (!$this->conn || !$this->verificarMembresiaClub($idUsuario)) {
            return null;
        }

        try {
            $query = "SELECT
                        u.fecha_inicio_club,
                        u.fecha_fin_club,
                        u.envios_gratis_usados,
                        u.ahorro_total_club,
                        DATEDIFF(u.fecha_fin_club, CURDATE()) as dias_restantes
                      FROM usuarios u
                      WHERE u.id_usuario = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$idUsuario]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $usuario['beneficios'] = QUICKBITE_CLUB_BENEFICIOS;
                $usuario['descuentos_aliados'] = QUICKBITE_CLUB_DESCUENTOS_ALIADOS;
            }

            return $usuario;
        } catch (Exception $e) {
            error_log("Error obteniendo beneficios miembro: " . $e->getMessage());
            return null;
        }
    }
}
