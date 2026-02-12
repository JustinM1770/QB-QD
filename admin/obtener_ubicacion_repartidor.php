<?php
// ENDPOINT PARA QUE LOS CLIENTES OBTENGAN LA UBICACIÓN DEL REPARTIDOR
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

require_once 'config/database.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que se proporcione el ID del pedido
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
    exit;
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['id_usuario'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar que el pedido pertenezca al usuario logueado
    $query_verify = "SELECT id_pedido, id_repartidor, id_estado FROM pedidos 
                     WHERE id_pedido = ? AND id_usuario = ?";
    $stmt_verify = $db->prepare($query_verify);
    $stmt_verify->bindParam(1, $order_id);
    $stmt_verify->bindParam(2, $user_id);
    $stmt_verify->execute();
    
    if ($stmt_verify->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no autorizado']);
        exit;
    }
    
    $pedido_info = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    $courier_id = $pedido_info['id_repartidor'];
    $estado_pedido = $pedido_info['id_estado'];
    
    // Si no hay repartidor asignado
    if (!$courier_id) {
        echo json_encode([
            'success' => true,
            'courier_assigned' => false,
            'message' => 'Aún no se ha asignado un repartidor a tu pedido'
        ]);
        exit;
    }
    
    // Si el pedido no está en estado activo de entrega
    if (!in_array($estado_pedido, [1, 4, 5])) {
        echo json_encode([
            'success' => true,
            'courier_assigned' => true,
            'tracking_active' => false,
            'message' => 'El seguimiento en tiempo real no está disponible para este estado del pedido'
        ]);
        exit;
    }
    
    // Obtener la ubicación más reciente del repartidor
    $query_location = "SELECT r.latitud_actual, r.longitud_actual, r.ultima_actualizacion_ubicacion,
                              u.nombre, u.apellido, u.telefono
                       FROM repartidores r
                       LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
                       WHERE r.id_repartidor = ?";
    $stmt_location = $db->prepare($query_location);
    $stmt_location->bindParam(1, $courier_id);
    $stmt_location->execute();
    
    if ($stmt_location->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Información del repartidor no disponible'
        ]);
        exit;
    }
    
    $courier_data = $stmt_location->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si hay ubicación disponible
    if (!$courier_data['latitud_actual'] || !$courier_data['longitud_actual']) {
        echo json_encode([
            'success' => true,
            'courier_assigned' => true,
            'tracking_active' => false,
            'courier_info' => [
                'name' => trim(($courier_data['nombre'] ?? '') . ' ' . ($courier_data['apellido'] ?? '')),
                'phone' => $courier_data['telefono'] ?? ''
            ],
            'message' => 'El repartidor aún no ha iniciado el seguimiento GPS'
        ]);
        exit;
    }
    
    // Verificar qué tan reciente es la ubicación (no más de 5 minutos)
    $last_update = strtotime($courier_data['ultima_actualizacion_ubicacion']);
    $now = time();
    $minutes_since_update = ($now - $last_update) / 60;
    
    $location_fresh = $minutes_since_update <= 5;
    
    // Obtener información adicional del tracking si está disponible
    $tracking_details = null;
    try {
        $query_tracking = "SELECT accuracy, speed, heading, timestamp_gps 
                          FROM courier_tracking 
                          WHERE id_pedido = ? AND id_repartidor = ? 
                          ORDER BY fecha_creacion DESC 
                          LIMIT 1";
        $stmt_tracking = $db->prepare($query_tracking);
        $stmt_tracking->bindParam(1, $order_id);
        $stmt_tracking->bindParam(2, $courier_id);
        $stmt_tracking->execute();
        
        if ($stmt_tracking->rowCount() > 0) {
            $tracking_details = $stmt_tracking->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Tabla de tracking no existe, continuar sin detalles adicionales
        error_log("Tabla courier_tracking no disponible: " . $e->getMessage());
    }
    
    // Calcular ETA estimado basado en la distancia
    $eta_minutes = calcularETA($courier_data['latitud_actual'], $courier_data['longitud_actual'], $order_id, $db);
    
    // Respuesta exitosa con ubicación
    $response = [
        'success' => true,
        'courier_assigned' => true,
        'tracking_active' => true,
        'location_fresh' => $location_fresh,
        'courier_info' => [
            'id' => $courier_id,
            'name' => trim(($courier_data['nombre'] ?? '') . ' ' . ($courier_data['apellido'] ?? '')),
            'phone' => $courier_data['telefono'] ?? ''
        ],
        'location' => [
            'latitude' => floatval($courier_data['latitud_actual']),
            'longitude' => floatval($courier_data['longitud_actual']),
            'last_updated' => $courier_data['ultima_actualizacion_ubicacion'],
            'minutes_ago' => round($minutes_since_update, 1)
        ],
        'eta' => [
            'minutes' => $eta_minutes,
            'text' => $eta_minutes > 0 ? $eta_minutes . ' minutos' : 'Calculando...'
        ],
        'order_status' => $estado_pedido
    ];
    
    // Agregar detalles de tracking si están disponibles
    if ($tracking_details) {
        $response['tracking_details'] = [
            'accuracy' => $tracking_details['accuracy'] ? floatval($tracking_details['accuracy']) : null,
            'speed' => $tracking_details['speed'] ? floatval($tracking_details['speed']) : null,
            'heading' => $tracking_details['heading'] ? floatval($tracking_details['heading']) : null,
            'gps_timestamp' => $tracking_details['timestamp_gps']
        ];
    }
    
    // Obtener notificaciones pendientes para este pedido
    $notifications = obtenerNotificacionesPendientes($order_id, $db);
    if (!empty($notifications)) {
        $response['notifications'] = $notifications;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Error al obtener ubicación del repartidor: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos'
    ]);
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor'
    ]);
}

// Función para calcular ETA estimado
function calcularETA($courier_lat, $courier_lng, $order_id, $db) {
    try {
        // Obtener dirección de entrega del pedido
        $query = "SELECT d.latitud, d.longitud 
                  FROM pedidos p 
                  LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion 
                  WHERE p.id_pedido = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $order_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return 0;
        }
        
        $delivery_location = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$delivery_location['latitud'] || !$delivery_location['longitud']) {
            return 0;
        }
        
        // Calcular distancia en línea recta
        $distance_km = calcularDistanciaKm(
            $courier_lat, $courier_lng,
            floatval($delivery_location['latitud']), floatval($delivery_location['longitud'])
        );
        
        // Estimar tiempo basado en velocidad promedio de 25 km/h en ciudad
        $average_speed_kmh = 25;
        $eta_minutes = ($distance_km / $average_speed_kmh) * 60;
        
        // Agregar 2-5 minutos de buffer
        $eta_minutes += rand(2, 5);
        
        return max(1, round($eta_minutes)); // Mínimo 1 minuto
        
    } catch (Exception $e) {
        error_log("Error calculando ETA: " . $e->getMessage());
        return 0;
    }
}

// Función para calcular distancia en kilómetros
function calcularDistanciaKm($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Radio de la Tierra en km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

// Función para obtener notificaciones pendientes
function obtenerNotificacionesPendientes($order_id, $db) {
    try {
        $query = "SELECT id, event_type, data, created_at 
                  FROM pending_notifications 
                  WHERE order_id = ? AND processed = FALSE 
                  ORDER BY created_at ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $order_id);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Marcar notificaciones como procesadas
        if (!empty($notifications)) {
            $ids = array_column($notifications, 'id');
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query_update = "UPDATE pending_notifications SET processed = TRUE WHERE id IN ($placeholders)";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->execute($ids);
        }
        
        return $notifications;
        
    } catch (PDOException $e) {
        // Tabla no existe, devolver array vacío
        if ($e->getCode() == '42S02') {
            return [];
        }
        throw $e;
    }
}
?>