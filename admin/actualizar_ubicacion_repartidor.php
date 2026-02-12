<?php
// ARCHIVO PARA RECIBIR UBICACIONES GPS DEL REPARTIDOR
session_start();

// Headers mejorados para compatibilidad con Cloudflare WAF
header('X-Powered-By: QuickBite/1.0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Accept-Ranges: bytes');

// Configurar headers JSON solo si es necesario
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Suprimir errores para evitar interferencia con Cloudflare
ini_set('display_errors', 0);
error_reporting(0);

require_once '../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Validar datos requeridos
$required_fields = ['latitude', 'longitude', 'order_id', 'courier_id'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Campo requerido faltante: $field"]);
        exit;
    }
}

$latitude = floatval($data['latitude']);
$longitude = floatval($data['longitude']);
$order_id = intval($data['order_id']);
$courier_id = intval($data['courier_id']);
$accuracy = isset($data['accuracy']) ? floatval($data['accuracy']) : null;
$speed = isset($data['speed']) ? floatval($data['speed']) : null;
$heading = isset($data['heading']) ? floatval($data['heading']) : null;
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');

// Validar rango de coordenadas
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(['success' => false, 'message' => 'Coordenadas fuera de rango válido']);
    exit;
}

// Verificar que el repartidor coincida con la sesión
if ($courier_id !== $_SESSION['id_usuario']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ID de repartidor no coincide']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar que el pedido pertenece al repartidor
    $query_verify = "SELECT id_pedido, id_estado FROM pedidos WHERE id_pedido = ? AND id_repartidor = ?";
    $stmt_verify = $db->prepare($query_verify);
    $stmt_verify->bindParam(1, $order_id);
    $stmt_verify->bindParam(2, $courier_id);
    $stmt_verify->execute();
    
    if ($stmt_verify->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no autorizado']);
        exit;
    }
    
    $pedido_info = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    $estado_pedido = $pedido_info['id_estado'];
    
    // Solo permitir tracking para pedidos en estados activos (1, 4, 5)
    if (!in_array($estado_pedido, [1, 4, 5])) {
        echo json_encode(['success' => false, 'message' => 'Pedido no está en estado activo para tracking']);
        exit;
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Actualizar ubicación del repartidor en la tabla repartidores
        $query_update_courier = "UPDATE repartidores SET 
                                latitud_actual = ?, 
                                longitud_actual = ?, 
                                ultima_actualizacion_ubicacion = NOW() 
                                WHERE id_repartidor = ?";
        $stmt_update_courier = $db->prepare($query_update_courier);
        $stmt_update_courier->bindParam(1, $latitude);
        $stmt_update_courier->bindParam(2, $longitude);
        $stmt_update_courier->bindParam(3, $courier_id);
        $stmt_update_courier->execute();
        
        // Insertar en tabla de tracking (crear si no existe)
        try {
            $query_tracking = "INSERT INTO courier_tracking 
                              (id_pedido, id_repartidor, latitud, longitud, accuracy, speed, heading, timestamp_gps, fecha_creacion) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_tracking = $db->prepare($query_tracking);
            $stmt_tracking->bindParam(1, $order_id);
            $stmt_tracking->bindParam(2, $courier_id);
            $stmt_tracking->bindParam(3, $latitude);
            $stmt_tracking->bindParam(4, $longitude);
            $stmt_tracking->bindParam(5, $accuracy);
            $stmt_tracking->bindParam(6, $speed);
            $stmt_tracking->bindParam(7, $heading);
            $stmt_tracking->bindParam(8, $timestamp);
            $stmt_tracking->execute();
        } catch (PDOException $e) {
            // Si la tabla no existe, crear la tabla de tracking
            if ($e->getCode() == '42S02') { // Table doesn't exist
                $create_table = "CREATE TABLE IF NOT EXISTS courier_tracking (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_pedido INT NOT NULL,
                    id_repartidor INT NOT NULL,
                    latitud DECIMAL(10, 8) NOT NULL,
                    longitud DECIMAL(11, 8) NOT NULL,
                    accuracy DECIMAL(8, 2) NULL,
                    speed DECIMAL(8, 2) NULL,
                    heading DECIMAL(6, 2) NULL,
                    timestamp_gps TIMESTAMP NULL,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pedido_repartidor (id_pedido, id_repartidor),
                    INDEX idx_fecha (fecha_creacion)
                )";
                $db->exec($create_table);
                
                // Reintentar inserción
                $stmt_tracking->execute();
            } else {
                throw $e;
            }
        }
        
        // Notificar a clientes via WebSocket (si está disponible)
        notificarClientes($order_id, $latitude, $longitude, $accuracy, $speed);
        
        // Confirmar transacción
        $db->commit();
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Ubicación actualizada correctamente',
            'order_id' => $order_id,
            'courier_id' => $courier_id,
            'coordinates' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'accuracy' => $accuracy,
            'speed' => $speed
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error al actualizar ubicación del repartidor: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error en la base de datos',
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    error_log("Error general al actualizar ubicación: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

// Función para notificar a clientes via WebSocket
function notificarClientes($order_id, $latitude, $longitude, $accuracy = null, $speed = null) {
    try {
        // Si tienes un servidor WebSocket configurado, enviar notificación
        $websocket_url = 'ws://localhost:8080'; // Ajustar según tu configuración
        
        $notification_data = [
            'event' => 'courier_location_update',
            'data' => [
                'order_id' => $order_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'speed' => $speed,
                'timestamp' => date('c') // ISO 8601 format
            ]
        ];
        
        // Intentar enviar vía WebSocket (implementación básica)
        // En un entorno de producción, usar una librería más robusta como ReactPHP/Socket.io
        
        // Por ahora, registrar en log para debugging
        error_log("Notificación WebSocket: " . json_encode($notification_data));
        
        // También guardar en una tabla de notificaciones pendientes para que el cliente las pueda consultar
        try {
            global $db;
            $query_notification = "INSERT INTO pending_notifications (order_id, event_type, data, created_at) 
                                  VALUES (?, 'courier_location_update', ?, NOW())";
            $stmt_notification = $db->prepare($query_notification);
            $stmt_notification->bindParam(1, $order_id);
            $stmt_notification->bindParam(2, json_encode($notification_data['data']));
            $stmt_notification->execute();
        } catch (PDOException $e) {
            // Si la tabla no existe, crearla
            if ($e->getCode() == '42S02') {
                $create_notifications = "CREATE TABLE IF NOT EXISTS pending_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    event_type VARCHAR(50) NOT NULL,
                    data JSON NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    processed BOOLEAN DEFAULT FALSE,
                    INDEX idx_order_processed (order_id, processed),
                    INDEX idx_created (created_at)
                )";
                $db->exec($create_notifications);
                
                // Reintentar inserción
                $stmt_notification->execute();
            }
        }
        
    } catch (Exception $e) {
        error_log("Error enviando notificación WebSocket: " . $e->getMessage());
        // No fallar la operación principal por errores de notificación
    }
}
?>