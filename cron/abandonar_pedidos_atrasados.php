<?php
/**
 * CRON: Abandonar pedidos atrasados y procesar reembolsos
 * 
 * Este script debe ejecutarse cada 5 minutos para verificar:
 * 1. Pedidos en camino que no se han entregado en el tiempo límite
 * 2. Pedidos listos para recoger que el repartidor no ha recogido
 * 3. Procesar reembolsos automáticos
 * 
 * Configuración crontab (cada 5 minutos):
 * Ejecutar: crontab -u www-data -e
 * Agregar: php /var/www/html/cron/abandonar_pedidos_atrasados.php
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_abandono.log');

// Timestamp para log
echo "\n=== CRON Abandono de Pedidos - " . date('Y-m-d H:i:s') . " ===\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';

// Tiempos límite en minutos
define('TIMEOUT_ENTREGA', 60);      // 60 minutos para entregar después de recoger
define('TIMEOUT_RECOGIDA', 30);     // 30 minutos para recoger pedido listo
define('TIMEOUT_EN_CAMINO', 45);    // 45 minutos máximo en camino

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    echo "Conexión establecida correctamente\n";
    
    // ==============================================
    // 1. BUSCAR PEDIDOS EN CAMINO ATRASADOS
    // ==============================================
    $query_en_camino = "
        SELECT 
            p.id_pedido,
            p.id_usuario,
            p.id_negocio,
            p.id_repartidor,
            p.monto_total,
            p.payment_id,
            p.metodo_pago,
            p.fecha_recogida,
            p.fecha_aceptacion_repartidor,
            u.nombre as usuario_nombre,
            u.email as usuario_email,
            u.telefono as usuario_telefono,
            r.nombre as repartidor_nombre,
            n.nombre as negocio_nombre,
            TIMESTAMPDIFF(MINUTE, p.fecha_recogida, NOW()) as minutos_desde_recogida,
            TIMESTAMPDIFF(MINUTE, p.fecha_aceptacion_repartidor, NOW()) as minutos_desde_aceptacion
        FROM pedidos p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
        LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
        WHERE p.id_estado = 5 -- en_camino
        AND (
            (p.fecha_recogida IS NOT NULL AND TIMESTAMPDIFF(MINUTE, p.fecha_recogida, NOW()) > :timeout_entrega)
            OR
            (p.fecha_recogida IS NULL AND p.fecha_aceptacion_repartidor IS NOT NULL 
             AND TIMESTAMPDIFF(MINUTE, p.fecha_aceptacion_repartidor, NOW()) > :timeout_en_camino)
        )
    ";
    
    $stmt = $db->prepare($query_en_camino);
    $stmt->bindValue(':timeout_entrega', TIMEOUT_ENTREGA, PDO::PARAM_INT);
    $stmt->bindValue(':timeout_en_camino', TIMEOUT_EN_CAMINO, PDO::PARAM_INT);
    $stmt->execute();
    
    $pedidos_atrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Pedidos en camino atrasados encontrados: " . count($pedidos_atrasados) . "\n";
    
    // ==============================================
    // 2. BUSCAR PEDIDOS LISTOS NO RECOGIDOS
    // ==============================================
    $query_sin_recoger = "
        SELECT 
            p.id_pedido,
            p.id_usuario,
            p.id_negocio,
            p.id_repartidor,
            p.monto_total,
            p.payment_id,
            p.metodo_pago,
            p.fecha_asignacion_repartidor,
            u.nombre as usuario_nombre,
            u.email as usuario_email,
            u.telefono as usuario_telefono,
            r.nombre as repartidor_nombre,
            n.nombre as negocio_nombre,
            TIMESTAMPDIFF(MINUTE, p.fecha_asignacion_repartidor, NOW()) as minutos_desde_asignacion
        FROM pedidos p
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
        LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
        WHERE p.id_estado = 4 -- listo_para_recoger
        AND p.id_repartidor IS NOT NULL
        AND p.fecha_asignacion_repartidor IS NOT NULL
        AND p.fecha_recogida IS NULL
        AND TIMESTAMPDIFF(MINUTE, p.fecha_asignacion_repartidor, NOW()) > :timeout_recogida
    ";
    
    $stmt = $db->prepare($query_sin_recoger);
    $stmt->bindValue(':timeout_recogida', TIMEOUT_RECOGIDA, PDO::PARAM_INT);
    $stmt->execute();
    
    $pedidos_sin_recoger = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Pedidos listos no recogidos encontrados: " . count($pedidos_sin_recoger) . "\n";
    
    // Combinar ambos arrays
    $todos_pedidos_abandonar = array_merge($pedidos_atrasados, $pedidos_sin_recoger);
    
    if (empty($todos_pedidos_abandonar)) {
        echo "No hay pedidos para abandonar en este momento\n";
        exit(0);
    }
    
    // ==============================================
    // 3. PROCESAR CADA PEDIDO
    // ==============================================
    $abandonados_exitosos = 0;
    $reembolsos_procesados = 0;
    $errores = 0;
    
    foreach ($todos_pedidos_abandonar as $pedido) {
        try {
            $db->beginTransaction();
            
            echo "\n--- Procesando pedido #{$pedido['id_pedido']} ---\n";
            echo "Usuario: {$pedido['usuario_nombre']} ({$pedido['usuario_email']})\n";
            echo "Repartidor: {$pedido['repartidor_nombre']}\n";
            echo "Monto: $" . number_format($pedido['monto_total'], 2) . "\n";
            
            // Guardar ID del repartidor anterior
            $update_pedido = "
                UPDATE pedidos 
                SET 
                    id_estado = 8,  -- abandonado
                    id_repartidor_anterior = :id_repartidor,
                    id_repartidor = NULL,
                    fecha_actualizacion = NOW(),
                    motivo_cancelacion = :motivo
                WHERE id_pedido = :id_pedido
            ";
            
            $motivo = "Pedido abandonado automáticamente - ";
            if (isset($pedido['minutos_desde_recogida'])) {
                $motivo .= "No entregado después de {$pedido['minutos_desde_recogida']} minutos de recogida";
            } else if (isset($pedido['minutos_desde_asignacion'])) {
                $motivo .= "No recogido después de {$pedido['minutos_desde_asignacion']} minutos de asignación";
            } else {
                $motivo .= "Tiempo excedido sin actualización";
            }
            
            $stmt_update = $db->prepare($update_pedido);
            $stmt_update->bindValue(':id_pedido', $pedido['id_pedido'], PDO::PARAM_INT);
            $stmt_update->bindValue(':id_repartidor', $pedido['id_repartidor'], PDO::PARAM_INT);
            $stmt_update->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $stmt_update->execute();
            
            echo "✓ Pedido marcado como abandonado\n";
            
            // ==============================================
            // 4. PROCESAR REEMBOLSO
            // ==============================================
            $reembolso_exitoso = false;
            $mensaje_reembolso = '';
            
            if (!empty($pedido['payment_id']) && !empty($pedido['metodo_pago'])) {
                
                if (strpos($pedido['metodo_pago'], 'mercadopago') !== false) {
                    // Reembolso MercadoPago
                    echo "Procesando reembolso MercadoPago...\n";
                    
                    require_once __DIR__ . '/../vendor/autoload.php';
                    
                    $mp_access_token = getenv('MERCADOPAGO_ACCESS_TOKEN');
                    if (empty($mp_access_token)) {
                        throw new Exception('MercadoPago access token no configurado');
                    }
                    
                    MercadoPago\SDK::setAccessToken($mp_access_token);
                    
                    try {
                        $refund = new MercadoPago\Refund();
                        $refund->payment_id = $pedido['payment_id'];
                        $refund->save();
                        
                        if ($refund->status == 'approved') {
                            $reembolso_exitoso = true;
                            $mensaje_reembolso = "Reembolso MercadoPago aprobado - ID: {$refund->id}";
                            echo "✓ {$mensaje_reembolso}\n";
                        } else {
                            $mensaje_reembolso = "Reembolso MercadoPago pendiente - Status: {$refund->status}";
                            echo "⚠ {$mensaje_reembolso}\n";
                        }
                        
                    } catch (Exception $e) {
                        $mensaje_reembolso = "Error en reembolso MP: " . $e->getMessage();
                        echo "✗ {$mensaje_reembolso}\n";
                        error_log("Error reembolso MP pedido #{$pedido['id_pedido']}: " . $e->getMessage());
                    }
                    
                } else if ($pedido['metodo_pago'] == 'efectivo' || strpos($pedido['metodo_pago'], 'efectivo') !== false) {
                    // Para efectivo NO hay reembolso porque es pago contra entrega
                    $reembolso_exitoso = false; // Cambiado a false
                    $mensaje_reembolso = "Pedido en efectivo (contra entrega) - No se procesó pago previo, no requiere reembolso";
                    echo "ℹ {$mensaje_reembolso}\n";
                    
                    // No registrar en tabla de reembolsos para efectivo
                    continue; // Saltar registro de reembolso
                    
                } else {
                    $mensaje_reembolso = "Método de pago: {$pedido['metodo_pago']} - Requiere reembolso manual";
                    echo "⚠ {$mensaje_reembolso}\n";
                }
                
                // Registrar en tabla de reembolsos (si existe)
                try {
                    $insert_reembolso = "
                        INSERT INTO reembolsos 
                        (id_pedido, id_usuario, monto, motivo, estado, fecha_solicitud, payment_id_original)
                        VALUES 
                        (:id_pedido, :id_usuario, :monto, :motivo, :estado, NOW(), :payment_id)
                    ";
                    
                    $stmt_reembolso = $db->prepare($insert_reembolso);
                    $stmt_reembolso->bindValue(':id_pedido', $pedido['id_pedido'], PDO::PARAM_INT);
                    $stmt_reembolso->bindValue(':id_usuario', $pedido['id_usuario'], PDO::PARAM_INT);
                    $stmt_reembolso->bindValue(':monto', $pedido['monto_total'], PDO::PARAM_STR);
                    $stmt_reembolso->bindValue(':motivo', $motivo, PDO::PARAM_STR);
                    $stmt_reembolso->bindValue(':estado', $reembolso_exitoso ? 'aprobado' : 'pendiente', PDO::PARAM_STR);
                    $stmt_reembolso->bindValue(':payment_id', $pedido['payment_id'], PDO::PARAM_STR);
                    $stmt_reembolso->execute();
                    
                    echo "✓ Reembolso registrado en base de datos\n";
                    
                } catch (PDOException $e) {
                    // Tabla reembolsos puede no existir, no es crítico
                    if ($e->getCode() != '42S02') { // 42S02 = Table doesn't exist
                        echo "⚠ No se pudo registrar reembolso en BD: " . $e->getMessage() . "\n";
                    }
                }
                
                if ($reembolso_exitoso && $pedido['metodo_pago'] != 'efectivo') {
                    $reembolsos_procesados++;
                }
            }
            
            // ==============================================
            // 5. NOTIFICAR AL USUARIO
            // ==============================================
            try {
                if (file_exists(__DIR__ . '/../api/notificaciones.php')) {
                    require_once __DIR__ . '/../api/notificaciones.php';
                    
                    $metodo_es_efectivo = ($pedido['metodo_pago'] == 'efectivo' || strpos($pedido['metodo_pago'], 'efectivo') !== false);
                    
                    if ($metodo_es_efectivo) {
                        $mensaje_usuario = "Tu pedido #{$pedido['id_pedido']} no pudo ser entregado. Como elegiste pago en efectivo, no se realizó ningún cargo.";
                    } else {
                        $mensaje_usuario = "Tu pedido #{$pedido['id_pedido']} no pudo ser entregado. " . 
                                          ($reembolso_exitoso ? "Tu reembolso está siendo procesado y será acreditado en 3-5 días hábiles." : "Contacta con soporte para procesar tu reembolso.");
                    }
                    
                    enviarNotificacion(
                        $pedido['id_usuario'],
                        'Pedido no entregado',
                        $mensaje_usuario,
                        'pedido',
                        $pedido['id_pedido']
                    );
                    
                    echo "✓ Notificación enviada al usuario\n";
                }
            } catch (Exception $e) {
                echo "⚠ Error enviando notificación: " . $e->getMessage() . "\n";
            }
            
            // ==============================================
            // 6. PENALIZAR AL REPARTIDOR (opcional)
            // ==============================================
            try {
                $penalizacion = "
                    UPDATE repartidores 
                    SET 
                        pedidos_abandonados = COALESCE(pedidos_abandonados, 0) + 1,
                        calificacion = GREATEST(calificacion - 0.5, 1.0)
                    WHERE id_repartidor = :id_repartidor
                ";
                
                $stmt_pen = $db->prepare($penalizacion);
                $stmt_pen->bindValue(':id_repartidor', $pedido['id_repartidor'], PDO::PARAM_INT);
                $stmt_pen->execute();
                
                echo "✓ Repartidor penalizado\n";
                
            } catch (PDOException $e) {
                echo "⚠ No se pudo penalizar repartidor: " . $e->getMessage() . "\n";
            }
            
            $db->commit();
            $abandonados_exitosos++;
            echo "✓ Pedido #{$pedido['id_pedido']} procesado exitosamente\n";
            
        } catch (Exception $e) {
            $db->rollBack();
            $errores++;
            echo "✗ Error procesando pedido #{$pedido['id_pedido']}: " . $e->getMessage() . "\n";
            error_log("Error abandonando pedido #{$pedido['id_pedido']}: " . $e->getMessage());
        }
    }
    
    // ==============================================
    // 7. RESUMEN FINAL
    // ==============================================
    echo "\n=== RESUMEN ===\n";
    echo "Pedidos encontrados: " . count($todos_pedidos_abandonar) . "\n";
    echo "Abandonados exitosamente: {$abandonados_exitosos}\n";
    echo "Reembolsos procesados: {$reembolsos_procesados}\n";
    echo "Errores: {$errores}\n";
    echo "=== FIN CRON - " . date('Y-m-d H:i:s') . " ===\n\n";
    
} catch (Exception $e) {
    echo "ERROR CRÍTICO: " . $e->getMessage() . "\n";
    error_log("Error crítico en cron abandono: " . $e->getMessage());
    exit(1);
}

exit(0);
