<?php
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

require_once '../config/database.php';
require_once '../api/WhatsAppLocalClient.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_POST['id_pedido']) || empty($_POST['id_pedido'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
    exit;
}

$id_pedido = intval($_POST['id_pedido']);
$id_repartidor = $_SESSION['id_usuario'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // PRIMERO: VERIFICAR QUE EL REPARTIDOR EXISTE
    $query_repartidor = "SELECT id_repartidor FROM repartidores WHERE id_repartidor = ?";
    $stmt_repartidor = $db->prepare($query_repartidor);
    $stmt_repartidor->bindParam(1, $id_repartidor);
    $stmt_repartidor->execute();
    
    if ($stmt_repartidor->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => "Repartidor no encontrado en la tabla repartidores. ID: $id_repartidor"]);
        exit;
    }
    
    // VERIFICAR QUE EL PEDIDO EXISTE, ESTÁ EN ESTADO 4, Y NO TIENE REPARTIDOR
    $query = "SELECT p.*, 
                     n.nombre as nombre_negocio, 
                     CONCAT(COALESCE(n.calle, ''), ' ', COALESCE(n.numero, ''), ', ', COALESCE(n.colonia, '')) as direccion_negocio, 
                     n.telefono as telefono_negocio, 
                     COALESCE(n.latitud, 19.4326) as lat_negocio, 
                     COALESCE(n.longitud, -99.1332) as lng_negocio,
                     u.nombre as nombre_cliente, 
                     u.apellido as apellido_cliente, 
                     u.telefono as telefono_cliente,
                     CONCAT(COALESCE(d.calle, ''), ' ', COALESCE(d.numero, ''), ', ', COALESCE(d.colonia, ''), ', ', COALESCE(d.ciudad, '')) as direccion_cliente,
                     COALESCE(d.latitud, 19.4226) as lat_cliente, 
                     COALESCE(d.longitud, -99.1432) as lng_cliente
              FROM pedidos p 
              LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
              LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
              LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
              WHERE p.id_pedido = ? AND p.id_estado = 4 AND p.id_repartidor IS NULL";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_pedido);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no disponible o ya asignado']);
        exit;
    }
    
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ASIGNAR EL PEDIDO AL REPARTIDOR
    $db->beginTransaction();
    
    try {
        // Asignar estado 5 (en_camino) cuando el repartidor acepta el pedido
        $query_update = "UPDATE pedidos SET 
                         id_repartidor = ?, 
                         id_estado = 5,  -- Estado 5: En camino (repartidor va a recoger)
                         fecha_actualizacion = NOW() 
                         WHERE id_pedido = ? AND id_repartidor IS NULL AND id_estado = 4";
        
        $stmt_update = $db->prepare($query_update);
        $stmt_update->bindParam(1, $id_repartidor);
        $stmt_update->bindParam(2, $id_pedido);
        
        if (!$stmt_update->execute() || $stmt_update->rowCount() === 0) {
            throw new Exception('El pedido ya fue asignado a otro repartidor');
        }
        
        $db->commit();
        
        // Enviar notificación por WhatsApp al cliente
        $whatsappSent = false;
        try {
            if (!empty($pedido['telefono_cliente'])) {
                $whatsapp = new WhatsAppLocalClient();
                $result = $whatsapp->sendOrderNotification(
                    $pedido['telefono_cliente'],
                    $id_pedido,
                    'en_camino', // Estado cuando el repartidor acepta
                    $pedido['monto_total'] ?? 0,
                    trim(($pedido['nombre_cliente'] ?? '') . ' ' . ($pedido['apellido_cliente'] ?? ''))
                );
                
                $whatsappSent = $result['success'] ?? false;
                error_log("WhatsApp notificación aceptar pedido: " . ($whatsappSent ? 'enviado' : 'error'));
            }
        } catch (Exception $e) {
            error_log("Error WhatsApp en aceptar pedido: " . $e->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => 'Pedido aceptado correctamente',
            'whatsapp_sent' => $whatsappSent,
            'id_pedido' => $id_pedido,
            'nombre_negocio' => $pedido['nombre_negocio'] ?? 'Restaurante',
            'direccion_negocio' => $pedido['direccion_negocio'] ?? 'Dirección del restaurante',
            'telefono_negocio' => $pedido['telefono_negocio'] ?? '',
            'lat_negocio' => floatval($pedido['lat_negocio']),
            'lng_negocio' => floatval($pedido['lng_negocio']),
            'nombre_cliente' => trim(($pedido['nombre_cliente'] ?? '') . ' ' . ($pedido['apellido_cliente'] ?? '')),
            'direccion_cliente' => $pedido['direccion_cliente'] ?? 'Dirección de entrega',
            'telefono_cliente' => $pedido['telefono_cliente'] ?? '',
            'lat_cliente' => floatval($pedido['lat_cliente']),
            'lng_cliente' => floatval($pedido['lng_cliente']),
            'total' => number_format(floatval($pedido['monto_total'] ?? 0), 2),
            'metodo_pago' => 'efectivo',
            'notas' => $pedido['instrucciones_especiales'] ?? '',
            'items' => []
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>