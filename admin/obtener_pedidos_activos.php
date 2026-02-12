<?php

session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $id_repartidor = $_SESSION['id_usuario'];
    
    // Obtener pedidos activos del repartidor
    $query = "SELECT 
                p.id_pedido,
                p.tipo_pedido,
                p.total_productos,
                p.costo_envio,
                p.cargo_servicio,
                p.impuestos,
                p.propina,
                p.monto_total,
                p.instrucciones_especiales,
                p.tiempo_entrega_estimado,
                p.fecha_creacion,
                p.metodo_pago,
                p.monto_efectivo,
                p.ganancia,
                n.nombre as nombre_negocio,
                CONCAT(n.calle, ' ', n.numero, ', ', n.colonia) as direccion_negocio,
                n.telefono as telefono_negocio,
                n.latitud as lat_negocio,
                n.longitud as lng_negocio,
                u.nombre as nombre_cliente,
                u.apellido as apellido_cliente,
                u.telefono as telefono_cliente,
                CONCAT(d.calle, ' ', d.numero, ', ', d.colonia, ', ', d.ciudad) as direccion_cliente,
                d.latitud as lat_cliente,
                d.longitud as lng_cliente
            FROM pedidos p
            JOIN negocios n ON p.id_negocio = n.id_negocio
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
            WHERE p.id_repartidor = ? 
            AND p.id_estado IN (1, 2, 3, 4)
            AND p.tipo_pedido = 'delivery'
            ORDER BY p.fecha_creacion DESC";
            
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_repartidor);
    $stmt->execute();
    
    $pedidos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear valores monetarios y calcular tiempos
    foreach ($pedidos_activos as &$pedido) {
        $pedido['total_productos'] = number_format((float)$pedido['total_productos'], 2, '.', '');
        $pedido['costo_envio'] = number_format((float)$pedido['costo_envio'], 2, '.', '');
        $pedido['cargo_servicio'] = number_format((float)$pedido['cargo_servicio'], 2, '.', '');
        $pedido['impuestos'] = number_format((float)$pedido['impuestos'], 2, '.', '');
        $pedido['propina'] = number_format((float)$pedido['propina'], 2, '.', '');
        $pedido['monto_total'] = number_format((float)$pedido['monto_total'], 2, '.', '');
        $pedido['ganancia'] = number_format((float)$pedido['ganancia'], 2, '.', '');
        $pedido['tiempo_estimado'] = strtotime($pedido['tiempo_entrega_estimado']) - time();
    }
    
    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos_activos
    ]);
    
} catch (Exception $e) {
    error_log("Error en obtener_pedidos_activos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener pedidos activos: ' . $e->getMessage()
    ]);
}