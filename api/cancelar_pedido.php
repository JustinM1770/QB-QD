<?php
/**
 * API: Cancelar Pedido
 * Permite al usuario cancelar su pedido según el estado y método de pago
 */

session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener datos del request
    $data = json_decode(file_get_contents('php://input'), true);
    $id_pedido = isset($data['id_pedido']) ? intval($data['id_pedido']) : 0;
    $motivo = isset($data['motivo']) ? trim($data['motivo']) : 'Cancelado por el usuario';
    
    if ($id_pedido <= 0) {
        throw new Exception('ID de pedido inválido');
    }
    
    // ==========================================
    // 1. OBTENER INFORMACIÓN DEL PEDIDO
    // ==========================================
    $query = "
        SELECT 
            p.*,
            e.nombre as estado_nombre,
            u.nombre as usuario_nombre,
            u.email as usuario_email
        FROM pedidos p
        INNER JOIN estados_pedido e ON p.id_estado = e.id_estado
        INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE p.id_pedido = :id_pedido 
        AND p.id_usuario = :id_usuario
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id_pedido', $id_pedido, PDO::PARAM_INT);
    $stmt->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
    $stmt->execute();
    
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        throw new Exception('Pedido no encontrado o no pertenece al usuario');
    }
    
    // ==========================================
    // 2. VALIDAR SI PUEDE CANCELAR SEGÚN ESTADO
    // ==========================================
    
    // Estados que permiten cancelación:
    // 1 = pendiente (OK)
    // 2 = confirmado (OK - negocio aún no empezó)
    // 3 = en_preparacion (NO - ya está preparando)
    // 4 = listo_para_recoger (NO - ya está listo)
    // 5 = en_camino (NO - ya salió)
    // 6 = entregado (NO - ya entregado)
    // 7 = cancelado (NO - ya cancelado)
    
    $estados_cancelables = [1, 2]; // Solo pendiente y confirmado
    
    if (!in_array($pedido['id_estado'], $estados_cancelables)) {
        $mensaje_error = '';
        
        switch($pedido['id_estado']) {
            case 3:
            case 4:
                $mensaje_error = 'No puedes cancelar porque el negocio ya está preparando tu pedido';
                break;
            case 5:
                $mensaje_error = 'No puedes cancelar porque el pedido ya está en camino';
                break;
            case 6:
                $mensaje_error = 'El pedido ya fue entregado';
                break;
            case 7:
                $mensaje_error = 'El pedido ya está cancelado';
                break;
            default:
                $mensaje_error = 'No puedes cancelar este pedido en su estado actual';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $mensaje_error,
            'estado_actual' => $pedido['estado_nombre'],
            'puede_cancelar' => false
        ]);
        exit;
    }
    
    // ==========================================
    // 3. VALIDAR MÉTODO DE PAGO PARA REEMBOLSO
    // ==========================================
    
    $metodo_pago = strtolower($pedido['metodo_pago'] ?? '');
    $es_efectivo = (strpos($metodo_pago, 'efectivo') !== false || $metodo_pago == 'efectivo');
    $requiere_reembolso = !$es_efectivo && !empty($pedido['payment_id']);
    
    // ==========================================
    // 4. PROCESAR CANCELACIÓN
    // ==========================================
    
    $db->beginTransaction();
    
    try {
        // Actualizar estado del pedido a cancelado
        $update = "
            UPDATE pedidos 
            SET 
                id_estado = 7,  -- cancelado
                motivo_cancelacion = :motivo,
                fecha_actualizacion = NOW()
            WHERE id_pedido = :id_pedido
        ";
        
        $stmt_update = $db->prepare($update);
        $stmt_update->bindValue(':motivo', $motivo, PDO::PARAM_STR);
        $stmt_update->bindValue(':id_pedido', $id_pedido, PDO::PARAM_INT);
        $stmt_update->execute();
        
        $reembolso_procesado = false;
        $mensaje_reembolso = '';
        
        // ==========================================
        // 5. PROCESAR REEMBOLSO SI APLICA
        // ==========================================
        
        if ($requiere_reembolso) {
            
            if (strpos($metodo_pago, 'mercadopago') !== false || strpos($metodo_pago, 'tarjeta') !== false) {
                
                // Intentar reembolso automático con MercadoPago
                try {
                    require_once '../vendor/autoload.php';
                    
                    $mp_access_token = getenv('MERCADOPAGO_ACCESS_TOKEN');
                    if (!empty($mp_access_token)) {
                        
                        MercadoPago\SDK::setAccessToken($mp_access_token);
                        
                        $refund = new MercadoPago\Refund();
                        $refund->payment_id = $pedido['payment_id'];
                        $refund->save();
                        
                        if ($refund->status == 'approved') {
                            $reembolso_procesado = true;
                            $mensaje_reembolso = 'Reembolso procesado exitosamente';
                            
                            // Registrar en tabla de reembolsos
                            $insert_reembolso = "
                                INSERT INTO reembolsos 
                                (id_pedido, id_usuario, monto, motivo, estado, fecha_solicitud, payment_id_original, refund_id, metodo_reembolso, procesado_automaticamente)
                                VALUES 
                                (:id_pedido, :id_usuario, :monto, :motivo, 'aprobado', NOW(), :payment_id, :refund_id, 'mercadopago', 0)
                            ";
                            
                            $stmt_reembolso = $db->prepare($insert_reembolso);
                            $stmt_reembolso->execute([
                                ':id_pedido' => $id_pedido,
                                ':id_usuario' => $_SESSION['id_usuario'],
                                ':monto' => $pedido['monto_total'],
                                ':motivo' => 'Cancelación por usuario: ' . $motivo,
                                ':payment_id' => $pedido['payment_id'],
                                ':refund_id' => $refund->id
                            ]);
                            
                        } else {
                            $mensaje_reembolso = 'Reembolso en proceso. Recibirás tu dinero en 3-5 días hábiles';
                            
                            // Registrar como pendiente
                            $insert_reembolso = "
                                INSERT INTO reembolsos 
                                (id_pedido, id_usuario, monto, motivo, estado, fecha_solicitud, payment_id_original, metodo_reembolso, procesado_automaticamente)
                                VALUES 
                                (:id_pedido, :id_usuario, :monto, :motivo, 'procesando', NOW(), :payment_id, 'mercadopago', 0)
                            ";
                            
                            $stmt_reembolso = $db->prepare($insert_reembolso);
                            $stmt_reembolso->execute([
                                ':id_pedido' => $id_pedido,
                                ':id_usuario' => $_SESSION['id_usuario'],
                                ':monto' => $pedido['monto_total'],
                                ':motivo' => 'Cancelación por usuario: ' . $motivo,
                                ':payment_id' => $pedido['payment_id']
                            ]);
                        }
                        
                    } else {
                        throw new Exception('Configuración de pagos no disponible');
                    }
                    
                } catch (Exception $e) {
                    error_log("Error procesando reembolso para pedido #{$id_pedido}: " . $e->getMessage());
                    $mensaje_reembolso = 'Tu reembolso será procesado manualmente. Contacta a soporte si no lo recibes en 5 días hábiles.';
                    
                    // Registrar como pendiente para revisión manual
                    $insert_reembolso = "
                        INSERT INTO reembolsos 
                        (id_pedido, id_usuario, monto, motivo, estado, fecha_solicitud, payment_id_original, notas_admin, procesado_automaticamente)
                        VALUES 
                        (:id_pedido, :id_usuario, :monto, :motivo, 'pendiente', NOW(), :payment_id, :notas, 0)
                    ";
                    
                    $stmt_reembolso = $db->prepare($insert_reembolso);
                    $stmt_reembolso->execute([
                        ':id_pedido' => $id_pedido,
                        ':id_usuario' => $_SESSION['id_usuario'],
                        ':monto' => $pedido['monto_total'],
                        ':motivo' => 'Cancelación por usuario: ' . $motivo,
                        ':payment_id' => $pedido['payment_id'],
                        ':notas' => 'Error automático: ' . $e->getMessage()
                    ]);
                }
            }
        } else if ($es_efectivo) {
            $mensaje_reembolso = 'Como elegiste pago en efectivo, no se realizó ningún cargo.';
        }
        
        // ==========================================
        // 6. NOTIFICAR AL NEGOCIO
        // ==========================================
        
        try {
            // Notificar al negocio sobre la cancelación
            if (file_exists('../api/notificaciones.php')) {
                require_once '../api/notificaciones.php';
                
                enviarNotificacion(
                    $pedido['id_negocio'],
                    'Pedido cancelado',
                    "El pedido #{$id_pedido} fue cancelado por el cliente",
                    'pedido',
                    $id_pedido,
                    'negocio'
                );
            }
        } catch (Exception $e) {
            error_log("Error enviando notificación al negocio: " . $e->getMessage());
        }
        
        $db->commit();
        
        // ==========================================
        // 7. RESPUESTA EXITOSA
        // ==========================================
        
        $respuesta = [
            'success' => true,
            'message' => 'Pedido cancelado exitosamente',
            'reembolso' => [
                'aplica' => $requiere_reembolso,
                'procesado' => $reembolso_procesado,
                'mensaje' => $mensaje_reembolso,
                'es_efectivo' => $es_efectivo
            ]
        ];
        
        echo json_encode($respuesta);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log("Error en cancelar_pedido.php: " . $e->getMessage());
}
