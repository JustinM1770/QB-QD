<?php
/**
 * GestionPedidos - Sistema de Reasignación y Multi-Pedido
 * QuickBite - Sistema Ganar-Ganar
 * 
 * Beneficios:
 * - Repartidores: Más pedidos, más ganancias, bonificaciones
 * - Clientes: Entregas más rápidas y confiables
 * - Negocios: Menos pedidos perdidos, mejor servicio
 * - Plataforma: Mayor eficiencia, menos problemas
 */

require_once __DIR__ . '/../config/database.php';

class GestionPedidos {
    private $pdo;
    
    // Constantes de estado
    const ESTADO_PENDIENTE = 1;
    const ESTADO_CONFIRMADO = 2;
    const ESTADO_EN_PREPARACION = 3;
    const ESTADO_LISTO_RECOGER = 4;
    const ESTADO_EN_CAMINO = 5;
    const ESTADO_ENTREGADO = 6;
    const ESTADO_CANCELADO = 7;
    const ESTADO_ABANDONADO = 8;
    const ESTADO_REASIGNADO = 9;
    const ESTADO_SIN_REPARTIDOR = 10;
    
    // Motivos de reasignación
    const MOTIVO_TIMEOUT_ACEPTACION = 'timeout_aceptacion';
    const MOTIVO_TIMEOUT_RECOGIDA = 'timeout_recogida';
    const MOTIVO_ABANDONO_VOLUNTARIO = 'abandono_voluntario';
    const MOTIVO_PROBLEMA_VEHICULO = 'problema_vehiculo';
    const MOTIVO_EMERGENCIA = 'emergencia';
    const MOTIVO_ADMIN = 'reasignacion_admin';
    const MOTIVO_OPTIMIZACION = 'optimizacion_ruta';
    
    public function __construct() {
        $database = new Database();
        $this->pdo = $database->getConnection();
    }
    
    // =========================================
    // SECCIÓN 1: ASIGNACIÓN DE PEDIDOS
    // =========================================
    
    /**
     * Asignar pedido a un repartidor
     */
    public function asignarPedido(int $idPedido, int $idRepartidor): array {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar que el pedido está disponible
            $stmt = $this->pdo->prepare("
                SELECT id_pedido, id_estado, id_repartidor, id_negocio 
                FROM pedidos WHERE id_pedido = ? FOR UPDATE
            ");
            $stmt->execute([$idPedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                throw new Exception("Pedido no encontrado");
            }
            
            if ($pedido['id_repartidor'] && $pedido['id_estado'] != self::ESTADO_ABANDONADO) {
                throw new Exception("Pedido ya tiene repartidor asignado");
            }
            
            // Verificar que el repartidor está disponible
            $stmt = $this->pdo->prepare("
                SELECT id_repartidor, disponible, activo,
                       (SELECT COUNT(*) FROM pedidos WHERE id_repartidor = ? AND id_estado IN (4,5)) as pedidos_activos
                FROM repartidores WHERE id_repartidor = ?
            ");
            $stmt->execute([$idRepartidor, $idRepartidor]);
            $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$repartidor || !$repartidor['activo']) {
                throw new Exception("Repartidor no válido");
            }
            
            // Obtener configuración de timeout
            $config = $this->obtenerConfigTimeout($pedido['id_negocio']);
            
            // Actualizar pedido
            $stmt = $this->pdo->prepare("
                UPDATE pedidos SET 
                    id_repartidor = ?,
                    id_repartidor_anterior = id_repartidor,
                    fecha_asignacion_repartidor = NOW(),
                    fecha_aceptacion_repartidor = NULL,
                    timeout_aceptacion_minutos = ?,
                    timeout_recogida_minutos = ?,
                    intentos_asignacion = intentos_asignacion + 1,
                    id_estado = CASE WHEN id_estado = ? THEN ? ELSE id_estado END
                WHERE id_pedido = ?
            ");
            $stmt->execute([
                $idRepartidor,
                $config['timeout_aceptacion_minutos'],
                $config['timeout_recogida_minutos'],
                self::ESTADO_ABANDONADO,
                self::ESTADO_LISTO_RECOGER,
                $idPedido
            ]);
            
            // Registrar en historial
            $this->registrarHistorial($idPedido, self::ESTADO_LISTO_RECOGER, "Asignado a repartidor #{$idRepartidor}");
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Pedido asignado correctamente',
                'timeout_minutos' => $config['timeout_aceptacion_minutos']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Repartidor acepta el pedido (confirma que va en camino al negocio)
     */
    public function aceptarPedido(int $idPedido, int $idRepartidor): array {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                SELECT id_pedido, id_repartidor, id_estado, fecha_asignacion_repartidor
                FROM pedidos WHERE id_pedido = ? AND id_repartidor = ? FOR UPDATE
            ");
            $stmt->execute([$idPedido, $idRepartidor]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                throw new Exception("Pedido no encontrado o no asignado a este repartidor");
            }
            
            if ($pedido['id_estado'] != self::ESTADO_LISTO_RECOGER) {
                throw new Exception("El pedido no está en estado listo para recoger");
            }
            
            // Calcular tiempo de respuesta
            $tiempoRespuesta = $pedido['fecha_asignacion_repartidor'] 
                ? (time() - strtotime($pedido['fecha_asignacion_repartidor'])) / 60 
                : 0;
            
            // Actualizar pedido
            $stmt = $this->pdo->prepare("
                UPDATE pedidos SET 
                    fecha_aceptacion_repartidor = NOW(),
                    id_estado = ?
                WHERE id_pedido = ?
            ");
            $stmt->execute([self::ESTADO_EN_CAMINO, $idPedido]);
            
            // Actualizar métricas del repartidor
            $this->actualizarMetricaAceptacion($idRepartidor, $tiempoRespuesta);
            
            // Registrar historial
            $this->registrarHistorial($idPedido, self::ESTADO_EN_CAMINO, "Repartidor en camino al negocio");
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Pedido aceptado, ¡ve por él!',
                'tiempo_respuesta_min' => round($tiempoRespuesta, 1)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Repartidor confirma recogida del pedido
     */
    public function confirmarRecogida(int $idPedido, int $idRepartidor): array {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE pedidos SET 
                    fecha_recogida = NOW()
                WHERE id_pedido = ? AND id_repartidor = ? AND id_estado = ?
            ");
            $stmt->execute([$idPedido, $idRepartidor, self::ESTADO_EN_CAMINO]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'No se pudo confirmar la recogida'];
            }
            
            $this->registrarHistorial($idPedido, self::ESTADO_EN_CAMINO, "Pedido recogido del negocio");
            
            return ['success' => true, 'message' => 'Recogida confirmada'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Marcar pedido como entregado
     */
    public function confirmarEntrega(int $idPedido, int $idRepartidor): array {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                SELECT p.*, TIMESTAMPDIFF(MINUTE, p.fecha_creacion, NOW()) as tiempo_total
                FROM pedidos p
                WHERE p.id_pedido = ? AND p.id_repartidor = ? AND p.id_estado = ?
                FOR UPDATE
            ");
            $stmt->execute([$idPedido, $idRepartidor, self::ESTADO_EN_CAMINO]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                throw new Exception("Pedido no válido para entrega");
            }
            
            // Actualizar pedido
            $stmt = $this->pdo->prepare("
                UPDATE pedidos SET 
                    id_estado = ?,
                    fecha_entrega = NOW(),
                    tiempo_entrega_real = NOW()
                WHERE id_pedido = ?
            ");
            $stmt->execute([self::ESTADO_ENTREGADO, $idPedido]);
            
            // Actualizar métricas
            $this->actualizarMetricaEntrega($idRepartidor, $pedido['tiempo_total']);
            
            // Verificar logros
            $this->verificarLogros($idRepartidor);
            
            // Registrar historial
            $this->registrarHistorial($idPedido, self::ESTADO_ENTREGADO, "Pedido entregado correctamente");
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => '¡Entrega completada!',
                'tiempo_total_min' => $pedido['tiempo_total']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =========================================
    // SECCIÓN 2: ABANDONO Y REASIGNACIÓN
    // =========================================
    
    /**
     * Repartidor abandona pedido voluntariamente
     */
    public function abandonarPedido(int $idPedido, int $idRepartidor, string $motivo = 'abandono_voluntario', string $notas = ''): array {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                SELECT id_pedido, id_repartidor, id_estado, fecha_recogida
                FROM pedidos WHERE id_pedido = ? AND id_repartidor = ? FOR UPDATE
            ");
            $stmt->execute([$idPedido, $idRepartidor]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                throw new Exception("Pedido no encontrado");
            }
            
            // No se puede abandonar si ya recogió el pedido
            if ($pedido['fecha_recogida']) {
                throw new Exception("No puedes abandonar un pedido que ya recogiste. Debes entregarlo.");
            }
            
            // Validar motivo
            $motivosValidos = [
                self::MOTIVO_ABANDONO_VOLUNTARIO,
                self::MOTIVO_PROBLEMA_VEHICULO,
                self::MOTIVO_EMERGENCIA
            ];
            if (!in_array($motivo, $motivosValidos)) {
                $motivo = self::MOTIVO_ABANDONO_VOLUNTARIO;
            }
            
            // Marcar como abandonado
            $stmt = $this->pdo->prepare("
                UPDATE pedidos SET 
                    id_estado = ?,
                    id_repartidor_anterior = id_repartidor,
                    id_repartidor = NULL,
                    motivo_cancelacion = ?,
                    prioridad = prioridad + 10
                WHERE id_pedido = ?
            ");
            $stmt->execute([self::ESTADO_ABANDONADO, $notas ?: $motivo, $idPedido]);
            
            // Registrar reasignación
            $this->registrarReasignacion($idPedido, $idRepartidor, null, $motivo, $notas, 'repartidor');
            
            // Penalizar métricas
            $this->penalizarAbandono($idRepartidor, $motivo);
            
            // Registrar historial
            $this->registrarHistorial($idPedido, self::ESTADO_ABANDONADO, "Abandonado por repartidor: {$motivo}");
            
            $this->pdo->commit();
            
            // Intentar reasignar automáticamente
            $this->intentarReasignacionAutomatica($idPedido);
            
            return [
                'success' => true,
                'message' => 'Pedido liberado. Se buscará otro repartidor.',
                'penalizacion' => $motivo === self::MOTIVO_EMERGENCIA ? 'ninguna' : 'aplicada'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Reasignar pedido manualmente (admin/negocio)
     */
    public function reasignarPedido(int $idPedido, int $nuevoRepartidor, int $iniciadorId, string $iniciadorTipo = 'admin'): array {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("SELECT id_repartidor FROM pedidos WHERE id_pedido = ? FOR UPDATE");
            $stmt->execute([$idPedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $repartidorAnterior = $pedido['id_repartidor'];
            
            // Asignar nuevo repartidor
            $resultado = $this->asignarPedido($idPedido, $nuevoRepartidor);
            
            if ($resultado['success']) {
                // Registrar reasignación
                $this->registrarReasignacion(
                    $idPedido, 
                    $repartidorAnterior, 
                    $nuevoRepartidor, 
                    self::MOTIVO_ADMIN, 
                    "Reasignado por {$iniciadorTipo}", 
                    $iniciadorTipo,
                    $iniciadorId
                );
                
                $this->registrarHistorial($idPedido, self::ESTADO_REASIGNADO, 
                    "Reasignado de #{$repartidorAnterior} a #{$nuevoRepartidor}");
            }
            
            $this->pdo->commit();
            return $resultado;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verificar y procesar timeouts de pedidos
     * (Llamado por cron job cada 5 minutos)
     */
    public function procesarTimeouts(): array {
        $resultados = [
            'procesados' => 0,
            'reasignados' => 0,
            'sin_repartidor' => 0,
            'errores' => []
        ];
        
        try {
            // Obtener pedidos con timeout - query directa en lugar de vista
            $stmt = $this->pdo->query("
                SELECT 
                    p.id_pedido,
                    p.id_repartidor,
                    p.id_negocio,
                    COALESCE(p.intentos_asignacion, 0) as intentos_asignacion,
                    CASE 
                        WHEN p.id_estado = 4 AND p.fecha_aceptacion_repartidor IS NULL 
                             AND TIMESTAMPDIFF(MINUTE, p.fecha_asignacion_repartidor, NOW()) > COALESCE(p.timeout_aceptacion_minutos, 10)
                        THEN 'TIMEOUT_ACEPTACION'
                        WHEN p.id_estado = 5 AND p.fecha_recogida IS NULL 
                             AND TIMESTAMPDIFF(MINUTE, p.fecha_aceptacion_repartidor, NOW()) > COALESCE(p.timeout_recogida_minutos, 20)
                        THEN 'TIMEOUT_RECOGIDA'
                        ELSE 'OK'
                    END as estado_timeout
                FROM pedidos p
                WHERE p.id_estado IN (4, 5)
                  AND p.id_repartidor IS NOT NULL
                HAVING estado_timeout != 'OK'
            ");
            
            $pedidosTimeout = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($pedidosTimeout as $pedido) {
                $resultados['procesados']++;
                
                $motivo = $pedido['estado_timeout'] === 'TIMEOUT_ACEPTACION' 
                    ? self::MOTIVO_TIMEOUT_ACEPTACION 
                    : self::MOTIVO_TIMEOUT_RECOGIDA;
                
                // Liberar pedido del repartidor actual
                $this->liberarPedidoPorTimeout($pedido['id_pedido'], $pedido['id_repartidor'], $motivo);
                
                // Intentar reasignar
                $reasignado = $this->intentarReasignacionAutomatica($pedido['id_pedido']);
                
                if ($reasignado) {
                    $resultados['reasignados']++;
                } else {
                    $resultados['sin_repartidor']++;
                }
            }
            
        } catch (Exception $e) {
            $resultados['errores'][] = $e->getMessage();
        }
        
        return $resultados;
    }
    
    /**
     * Liberar pedido por timeout
     */
    private function liberarPedidoPorTimeout(int $idPedido, int $idRepartidor, string $motivo): void {
        $stmt = $this->pdo->prepare("
            UPDATE pedidos SET 
                id_estado = ?,
                id_repartidor_anterior = id_repartidor,
                id_repartidor = NULL,
                prioridad = prioridad + 15,
                motivo_cancelacion = ?
            WHERE id_pedido = ?
        ");
        $stmt->execute([self::ESTADO_ABANDONADO, $motivo, $idPedido]);
        
        // Registrar
        $this->registrarReasignacion($idPedido, $idRepartidor, null, $motivo, 'Timeout automático', 'sistema');
        $this->registrarHistorial($idPedido, self::ESTADO_ABANDONADO, "Timeout: {$motivo}");
        
        // Penalizar (más suave para timeout que para abandono)
        $this->penalizarTimeout($idRepartidor);
    }
    
    /**
     * Intentar reasignación automática a mejor repartidor disponible
     */
    private function intentarReasignacionAutomatica(int $idPedido): bool {
        // Obtener info del pedido
        $stmt = $this->pdo->prepare("
            SELECT p.*, n.latitud as lat_negocio, n.longitud as lng_negocio,
                   COALESCE(p.prioridad, 0) as prioridad,
                   COALESCE(p.intentos_asignacion, 0) as intentos_asignacion
            FROM pedidos p
            JOIN negocios n ON p.id_negocio = n.id_negocio
            WHERE p.id_pedido = ?
        ");
        $stmt->execute([$idPedido]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) return false;
        
        // Obtener configuración
        $config = $this->obtenerConfigTimeout($pedido['id_negocio']);
        $maxIntentos = $config['max_intentos_asignacion'];
        
        if ($pedido['intentos_asignacion'] >= $maxIntentos) {
            // Marcar como sin repartidor
            $stmt = $this->pdo->prepare("UPDATE pedidos SET id_estado = ? WHERE id_pedido = ?");
            $stmt->execute([self::ESTADO_SIN_REPARTIDOR, $idPedido]);
            $this->registrarHistorial($idPedido, self::ESTADO_SIN_REPARTIDOR, 
                "Máximo de intentos alcanzado ({$maxIntentos})");
            
            // TODO: Notificar al negocio
            return false;
        }
        
        // Buscar mejor repartidor
        $repartidor = $this->encontrarMejorRepartidor(
            $pedido['lat_negocio'], 
            $pedido['lng_negocio'],
            $config['radio_busqueda_km'] + ($pedido['intentos_asignacion'] * $config['incremento_radio_km']),
            $pedido['id_repartidor_anterior'] ?? null // Excluir repartidor anterior
        );
        
        if ($repartidor) {
            $resultado = $this->asignarPedido($idPedido, $repartidor['id_repartidor']);
            
            if ($resultado['success']) {
                // Agregar bonificación por rescate si aplica
                if ($pedido['intentos_asignacion'] > 0) {
                    $this->crearBonificacionRescate($repartidor['id_repartidor'], $idPedido, $config['bonificacion_reintento']);
                }
                
                // TODO: Notificar al nuevo repartidor
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Encontrar el mejor repartidor disponible cerca de una ubicación
     */
    public function encontrarMejorRepartidor(float $latitud, float $longitud, float $radioKm = 5, ?int $excluirRepartidor = null): ?array {
        $sql = "
            SELECT 
                r.*,
                (6371 * acos(cos(radians(?)) * cos(radians(latitud)) * cos(radians(longitud) - radians(?)) + sin(radians(?)) * sin(radians(latitud)))) AS distancia_km
            FROM v_repartidores_disponibles r
            WHERE r.pedidos_activos < 4
        ";
        
        $params = [$latitud, $longitud, $latitud];
        
        if ($excluirRepartidor) {
            $sql .= " AND r.id_repartidor != ?";
            $params[] = $excluirRepartidor;
        }
        
        $sql .= "
            HAVING distancia_km <= ?
            ORDER BY 
                r.score DESC,
                distancia_km ASC,
                r.pedidos_activos ASC
            LIMIT 1
        ";
        $params[] = $radioKm;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    // =========================================
    // SECCIÓN 3: MULTI-PEDIDO / BATCH
    // =========================================
    
    /**
     * Obtener pedidos cercanos para batch
     */
    public function obtenerPedidosCercanos(int $idRepartidor, float $latitud, float $longitud, float $radioKm = 3): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id_pedido,
                p.id_negocio,
                n.nombre as nombre_negocio,
                n.latitud as lat_negocio,
                n.longitud as lng_negocio,
                CONCAT(d.calle, ' ', d.numero, ', ', d.colonia) as direccion_entrega,
                d.latitud as latitud_entrega,
                d.longitud as longitud_entrega,
                p.total,
                p.fecha_creacion,
                ep.nombre as estado,
                (6371 * acos(cos(radians(?)) * cos(radians(n.latitud)) * cos(radians(n.longitud) - radians(?)) + sin(radians(?)) * sin(radians(n.latitud)))) AS distancia_negocio_km
            FROM pedidos p
            JOIN negocios n ON p.id_negocio = n.id_negocio
            JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
            JOIN estados_pedido ep ON p.id_estado = ep.id_estado
            WHERE p.id_estado IN (?, ?)
              AND (p.id_repartidor IS NULL OR p.id_repartidor = ?)
            HAVING distancia_negocio_km <= ?
            ORDER BY distancia_negocio_km ASC
            LIMIT 10
        ");
        
        $stmt->execute([
            $latitud, $longitud, $latitud,
            self::ESTADO_LISTO_RECOGER,
            self::ESTADO_ABANDONADO,
            $idRepartidor,
            $radioKm
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear ruta batch con múltiples pedidos
     */
    public function crearRutaBatch(int $idRepartidor, array $idsPedidos): array {
        if (count($idsPedidos) < 2) {
            return ['success' => false, 'error' => 'Se necesitan al menos 2 pedidos para una ruta batch'];
        }
        
        if (count($idsPedidos) > 4) {
            return ['success' => false, 'error' => 'Máximo 4 pedidos por ruta'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Verificar que todos los pedidos están disponibles
            $placeholders = implode(',', array_fill(0, count($idsPedidos), '?'));
            $stmt = $this->pdo->prepare("
                SELECT id_pedido, id_estado, id_repartidor, id_negocio,
                       latitud_entrega, longitud_entrega
                FROM pedidos 
                WHERE id_pedido IN ({$placeholders})
                FOR UPDATE
            ");
            $stmt->execute($idsPedidos);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($pedidos) !== count($idsPedidos)) {
                throw new Exception("Algunos pedidos no existen");
            }
            
            foreach ($pedidos as $p) {
                if ($p['id_repartidor'] && $p['id_repartidor'] != $idRepartidor) {
                    throw new Exception("Pedido #{$p['id_pedido']} ya tiene otro repartidor");
                }
                if (!in_array($p['id_estado'], [self::ESTADO_LISTO_RECOGER, self::ESTADO_ABANDONADO])) {
                    throw new Exception("Pedido #{$p['id_pedido']} no está disponible");
                }
            }
            
            // Obtener ubicación del repartidor
            $stmt = $this->pdo->prepare("SELECT latitud_actual, longitud_actual FROM repartidores WHERE id_repartidor = ?");
            $stmt->execute([$idRepartidor]);
            $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Crear la ruta
            $stmt = $this->pdo->prepare("
                INSERT INTO rutas_entrega (
                    id_repartidor, estado, tipo_ruta, total_pedidos,
                    latitud_inicio, longitud_inicio, max_pedidos
                ) VALUES (?, 'activa', 'batch', ?, ?, ?, 4)
            ");
            $stmt->execute([
                $idRepartidor,
                count($idsPedidos),
                $repartidor['latitud_actual'],
                $repartidor['longitud_actual']
            ]);
            $idRuta = $this->pdo->lastInsertId();
            
            // Optimizar orden de paradas (simple: ordenar por distancia)
            $paradasOrdenadas = $this->optimizarRuta($pedidos, $repartidor['latitud_actual'], $repartidor['longitud_actual']);
            
            $orden = 1;
            $distanciaTotal = 0;
            
            foreach ($paradasOrdenadas as $parada) {
                // Agregar parada de recolección
                $stmt = $this->pdo->prepare("
                    INSERT INTO pedidos_ruta (
                        id_ruta, id_pedido, orden_entrega, tipo_parada,
                        latitud, longitud, direccion
                    ) VALUES (?, ?, ?, 'recoleccion', ?, ?, ?)
                ");
                $stmt->execute([
                    $idRuta,
                    $parada['id_pedido'],
                    $orden++,
                    $parada['lat_negocio'],
                    $parada['lng_negocio'],
                    $parada['direccion_negocio'] ?? 'Negocio'
                ]);
                
                // Agregar parada de entrega
                $stmt = $this->pdo->prepare("
                    INSERT INTO pedidos_ruta (
                        id_ruta, id_pedido, orden_entrega, tipo_parada,
                        latitud, longitud, direccion
                    ) VALUES (?, ?, ?, 'entrega', ?, ?, ?)
                ");
                $stmt->execute([
                    $idRuta,
                    $parada['id_pedido'],
                    $orden++,
                    $parada['latitud_entrega'],
                    $parada['longitud_entrega'],
                    $parada['direccion_entrega'] ?? 'Cliente'
                ]);
                
                // Asignar pedido al repartidor
                $this->asignarPedido($parada['id_pedido'], $idRepartidor);
                
                // Marcar como entrega múltiple
                $stmt = $this->pdo->prepare("UPDATE pedidos SET id_ruta = ?, es_entrega_multiple = 1 WHERE id_pedido = ?");
                $stmt->execute([$idRuta, $parada['id_pedido']]);
            }
            
            // Calcular bonificación batch
            $bonificacion = $this->calcularBonificacionBatch(count($idsPedidos));
            
            $stmt = $this->pdo->prepare("UPDATE rutas_entrega SET bonificacion_batch = ? WHERE id_ruta = ?");
            $stmt->execute([$bonificacion, $idRuta]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'id_ruta' => $idRuta,
                'total_pedidos' => count($idsPedidos),
                'bonificacion' => $bonificacion,
                'message' => "Ruta batch creada con {$orden} paradas"
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener ruta activa del repartidor
     */
    public function obtenerRutaActiva(int $idRepartidor): ?array {
        $stmt = $this->pdo->prepare("
            SELECT r.*, 
                   JSON_ARRAYAGG(
                       JSON_OBJECT(
                           'id_pedido', pr.id_pedido,
                           'orden', pr.orden_entrega,
                           'tipo', pr.tipo_parada,
                           'estado', pr.estado_parada,
                           'lat', pr.latitud,
                           'lng', pr.longitud,
                           'direccion', pr.direccion
                       )
                   ) as paradas
            FROM rutas_entrega r
            LEFT JOIN pedidos_ruta pr ON r.id_ruta = pr.id_ruta
            WHERE r.id_repartidor = ? AND r.estado IN ('activa', 'en_progreso')
            GROUP BY r.id_ruta
            ORDER BY r.fecha_creacion DESC
            LIMIT 1
        ");
        $stmt->execute([$idRepartidor]);
        $ruta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ruta && $ruta['paradas']) {
            $ruta['paradas'] = json_decode($ruta['paradas'], true);
            // Ordenar paradas
            usort($ruta['paradas'], fn($a, $b) => $a['orden'] - $b['orden']);
        }
        
        return $ruta ?: null;
    }
    
    /**
     * Agregar pedido a ruta activa
     */
    public function agregarPedidoARuta(int $idRepartidor, int $idPedido): array {
        $rutaActiva = $this->obtenerRutaActiva($idRepartidor);
        
        if (!$rutaActiva) {
            // Crear nueva ruta single
            return $this->asignarPedido($idPedido, $idRepartidor);
        }
        
        if ($rutaActiva['total_pedidos'] >= $rutaActiva['max_pedidos']) {
            return ['success' => false, 'error' => 'Ruta llena, completa entregas primero'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Verificar pedido
            $stmt = $this->pdo->prepare("SELECT * FROM pedidos WHERE id_pedido = ? FOR UPDATE");
            $stmt->execute([$idPedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pedido || ($pedido['id_repartidor'] && $pedido['id_repartidor'] != $idRepartidor)) {
                throw new Exception("Pedido no disponible");
            }
            
            // Obtener último orden
            $stmt = $this->pdo->prepare("SELECT MAX(orden_entrega) FROM pedidos_ruta WHERE id_ruta = ?");
            $stmt->execute([$rutaActiva['id_ruta']]);
            $ultimoOrden = $stmt->fetchColumn() ?: 0;
            
            // Agregar paradas
            // Recolección
            $stmt = $this->pdo->prepare("
                INSERT INTO pedidos_ruta (id_ruta, id_pedido, orden_entrega, tipo_parada, latitud, longitud, direccion)
                SELECT ?, ?, ?, 'recoleccion', n.latitud, n.longitud, n.direccion
                FROM pedidos p JOIN negocios n ON p.id_negocio = n.id_negocio WHERE p.id_pedido = ?
            ");
            $stmt->execute([$rutaActiva['id_ruta'], $idPedido, $ultimoOrden + 1, $idPedido]);
            
            // Entrega
            $stmt = $this->pdo->prepare("
                INSERT INTO pedidos_ruta (id_ruta, id_pedido, orden_entrega, tipo_parada, latitud, longitud, direccion)
                VALUES (?, ?, ?, 'entrega', ?, ?, ?)
            ");
            $stmt->execute([
                $rutaActiva['id_ruta'],
                $idPedido,
                $ultimoOrden + 2,
                $pedido['latitud_entrega'],
                $pedido['longitud_entrega'],
                $pedido['direccion_entrega']
            ]);
            
            // Actualizar pedido
            $stmt = $this->pdo->prepare("
                UPDATE pedidos SET id_ruta = ?, id_repartidor = ?, es_entrega_multiple = 1,
                       fecha_asignacion_repartidor = NOW()
                WHERE id_pedido = ?
            ");
            $stmt->execute([$rutaActiva['id_ruta'], $idRepartidor, $idPedido]);
            
            // Actualizar ruta
            $stmt = $this->pdo->prepare("UPDATE rutas_entrega SET total_pedidos = total_pedidos + 1 WHERE id_ruta = ?");
            $stmt->execute([$rutaActiva['id_ruta']]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Pedido agregado a tu ruta',
                'total_pedidos' => $rutaActiva['total_pedidos'] + 1
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar sugerencias de batch para un repartidor
     */
    public function generarSugerenciasBatch(int $idRepartidor): array {
        $stmt = $this->pdo->prepare("SELECT latitud_actual, longitud_actual FROM repartidores WHERE id_repartidor = ?");
        $stmt->execute([$idRepartidor]);
        $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$repartidor['latitud_actual']) {
            return [];
        }
        
        $pedidosCercanos = $this->obtenerPedidosCercanos(
            $idRepartidor,
            $repartidor['latitud_actual'],
            $repartidor['longitud_actual'],
            5 // 5km
        );
        
        if (count($pedidosCercanos) < 2) {
            return [];
        }
        
        // Agrupar por negocio cercano (mismo negocio = mejor batch)
        $gruposPorNegocio = [];
        foreach ($pedidosCercanos as $p) {
            $gruposPorNegocio[$p['id_negocio']][] = $p;
        }
        
        $sugerencias = [];
        
        // Crear sugerencia para negocios con múltiples pedidos
        foreach ($gruposPorNegocio as $idNegocio => $pedidos) {
            if (count($pedidos) >= 2) {
                $ids = array_column(array_slice($pedidos, 0, 4), 'id_pedido');
                $ganancia = array_sum(array_column($pedidos, 'total')) * 0.15; // 15% de propina estimada
                $bonificacion = $this->calcularBonificacionBatch(count($ids));
                
                $sugerencias[] = [
                    'tipo' => 'mismo_negocio',
                    'pedidos' => $ids,
                    'negocio' => $pedidos[0]['nombre_negocio'],
                    'ganancia_estimada' => $ganancia + $bonificacion,
                    'bonificacion' => $bonificacion,
                    'mensaje' => count($ids) . " pedidos en " . $pedidos[0]['nombre_negocio']
                ];
            }
        }
        
        return $sugerencias;
    }
    
    // =========================================
    // SECCIÓN 4: HELPERS Y MÉTRICAS
    // =========================================
    
    private function obtenerConfigTimeout(?int $idNegocio = null): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM configuracion_timeout 
            WHERE (tipo = 'global') OR (tipo = 'negocio' AND id_referencia = ?)
            ORDER BY tipo DESC LIMIT 1
        ");
        $stmt->execute([$idNegocio]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $config ?: [
            'timeout_aceptacion_minutos' => 10,
            'timeout_recogida_minutos' => 20,
            'max_intentos_asignacion' => 3,
            'radio_busqueda_km' => 5,
            'incremento_radio_km' => 2,
            'bonificacion_reintento' => 5
        ];
    }
    
    private function registrarHistorial(int $idPedido, int $idEstado, string $notas = ''): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO historial_estados_pedido (id_pedido, id_estado, notas) VALUES (?, ?, ?)
        ");
        $stmt->execute([$idPedido, $idEstado, $notas]);
    }
    
    private function registrarReasignacion(int $idPedido, ?int $repartidorAnterior, ?int $repartidorNuevo, 
                                           string $motivo, string $notas, string $iniciador, ?int $iniciadorId = null): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO reasignaciones_pedido 
            (id_pedido, id_repartidor_anterior, id_repartidor_nuevo, motivo, notas, iniciado_por, id_usuario_iniciador)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$idPedido, $repartidorAnterior, $repartidorNuevo, $motivo, $notas, $iniciador, $iniciadorId]);
    }
    
    private function actualizarMetricaAceptacion(int $idRepartidor, float $tiempoMinutos): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO metricas_repartidor (id_repartidor, promedio_tiempo_aceptacion)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
                promedio_tiempo_aceptacion = (promedio_tiempo_aceptacion + ?) / 2
        ");
        $stmt->execute([$idRepartidor, $tiempoMinutos, $tiempoMinutos]);
    }
    
    private function actualizarMetricaEntrega(int $idRepartidor, float $tiempoMinutos): void {
        $stmt = $this->pdo->prepare("
            UPDATE metricas_repartidor SET 
                total_pedidos_completados = total_pedidos_completados + 1,
                promedio_tiempo_entrega = (promedio_tiempo_entrega * total_pedidos_completados + ?) / (total_pedidos_completados + 1),
                tasa_cumplimiento = (total_pedidos_completados + 1) * 100.0 / 
                    NULLIF(total_pedidos_completados + total_pedidos_abandonados + 1, 0),
                score_confiabilidad = LEAST(100, score_confiabilidad + 1)
            WHERE id_repartidor = ?
        ");
        $stmt->execute([$tiempoMinutos, $idRepartidor]);
    }
    
    private function penalizarAbandono(int $idRepartidor, string $motivo): void {
        // No penalizar emergencias
        if ($motivo === self::MOTIVO_EMERGENCIA) return;
        
        $penalizacion = $motivo === self::MOTIVO_PROBLEMA_VEHICULO ? 2 : 5;
        
        $stmt = $this->pdo->prepare("
            UPDATE metricas_repartidor SET 
                total_pedidos_abandonados = total_pedidos_abandonados + 1,
                score_confiabilidad = GREATEST(0, score_confiabilidad - ?),
                tasa_cumplimiento = total_pedidos_completados * 100.0 / 
                    NULLIF(total_pedidos_completados + total_pedidos_abandonados + 1, 0)
            WHERE id_repartidor = ?
        ");
        $stmt->execute([$penalizacion, $idRepartidor]);
    }
    
    private function penalizarTimeout(int $idRepartidor): void {
        $stmt = $this->pdo->prepare("
            UPDATE metricas_repartidor SET 
                total_pedidos_timeout = total_pedidos_timeout + 1,
                score_confiabilidad = GREATEST(0, score_confiabilidad - 3)
            WHERE id_repartidor = ?
        ");
        $stmt->execute([$idRepartidor]);
    }
    
    private function calcularBonificacionBatch(int $numPedidos): float {
        // Bonificación escalonada
        $bonificaciones = [
            2 => 10.00,
            3 => 20.00,
            4 => 35.00
        ];
        return $bonificaciones[$numPedidos] ?? 0;
    }
    
    private function crearBonificacionRescate(int $idRepartidor, int $idPedido, float $monto): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO bonificaciones_repartidor 
            (id_repartidor, id_pedido, tipo, monto, descripcion)
            VALUES (?, ?, 'rescate_pedido', ?, 'Bonificación por rescatar pedido')
        ");
        $stmt->execute([$idRepartidor, $idPedido, $monto]);
    }
    
    private function optimizarRuta(array $pedidos, float $latInicio, float $lngInicio): array {
        // Algoritmo simple: nearest neighbor
        $ordenados = [];
        $pendientes = $pedidos;
        $latActual = $latInicio;
        $lngActual = $lngInicio;
        
        while (!empty($pendientes)) {
            $menorDistancia = PHP_FLOAT_MAX;
            $indiceMasCercano = 0;
            
            foreach ($pendientes as $i => $p) {
                $distancia = $this->calcularDistancia($latActual, $lngActual, 
                    $p['lat_negocio'] ?? $p['latitud_entrega'], 
                    $p['lng_negocio'] ?? $p['longitud_entrega']);
                
                if ($distancia < $menorDistancia) {
                    $menorDistancia = $distancia;
                    $indiceMasCercano = $i;
                }
            }
            
            $ordenados[] = $pendientes[$indiceMasCercano];
            $latActual = $pendientes[$indiceMasCercano]['latitud_entrega'];
            $lngActual = $pendientes[$indiceMasCercano]['longitud_entrega'];
            unset($pendientes[$indiceMasCercano]);
            $pendientes = array_values($pendientes);
        }
        
        return $ordenados;
    }
    
    private function calcularDistancia(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
    
    /**
     * Verificar y otorgar logros al repartidor
     */
    private function verificarLogros(int $idRepartidor): void {
        // Obtener métricas actuales
        $stmt = $this->pdo->prepare("SELECT * FROM metricas_repartidor WHERE id_repartidor = ?");
        $stmt->execute([$idRepartidor]);
        $metricas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$metricas) return;
        
        // Obtener logros no desbloqueados
        $stmt = $this->pdo->prepare("
            SELECT l.* FROM logros_repartidor l
            WHERE l.activo = 1 AND l.id_logro NOT IN (
                SELECT id_logro FROM repartidor_logros WHERE id_repartidor = ?
            )
        ");
        $stmt->execute([$idRepartidor]);
        $logrosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($logrosDisponibles as $logro) {
            $cumple = false;
            
            switch ($logro['requisito_tipo']) {
                case 'pedidos_completados':
                    $cumple = $metricas['total_pedidos_completados'] >= $logro['requisito_valor'];
                    break;
                // Agregar más casos según necesidad
            }
            
            if ($cumple) {
                // Otorgar logro
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO repartidor_logros (id_repartidor, id_logro, bonificacion_otorgada)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$idRepartidor, $logro['id_logro'], $logro['bonificacion']]);
                
                // Crear bonificación
                if ($logro['bonificacion'] > 0) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO bonificaciones_repartidor 
                        (id_repartidor, tipo, monto, descripcion)
                        VALUES (?, 'racha_completados', ?, ?)
                    ");
                    $stmt->execute([$idRepartidor, $logro['bonificacion'], "Logro: {$logro['nombre']}"]);
                }
            }
        }
    }
    
    /**
     * Obtener estadísticas del repartidor
     */
    public function obtenerEstadisticasRepartidor(int $idRepartidor): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                m.*,
                (SELECT COUNT(*) FROM bonificaciones_repartidor WHERE id_repartidor = ? AND estado = 'pendiente') as bonificaciones_pendientes,
                (SELECT SUM(monto) FROM bonificaciones_repartidor WHERE id_repartidor = ? AND estado = 'pendiente') as monto_bonificaciones,
                (SELECT COUNT(*) FROM repartidor_logros WHERE id_repartidor = ?) as logros_desbloqueados
            FROM metricas_repartidor m
            WHERE m.id_repartidor = ?
        ");
        $stmt->execute([$idRepartidor, $idRepartidor, $idRepartidor, $idRepartidor]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * Obtener logros del repartidor
     */
    public function obtenerLogrosRepartidor(int $idRepartidor): array {
        $stmt = $this->pdo->prepare("
            SELECT l.*, rl.fecha_desbloqueo,
                   CASE WHEN rl.id_repartidor IS NOT NULL THEN 1 ELSE 0 END as desbloqueado
            FROM logros_repartidor l
            LEFT JOIN repartidor_logros rl ON l.id_logro = rl.id_logro AND rl.id_repartidor = ?
            WHERE l.activo = 1
            ORDER BY l.requisito_valor ASC
        ");
        $stmt->execute([$idRepartidor]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
