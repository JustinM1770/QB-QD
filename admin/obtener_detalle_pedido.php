<?php
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

require_once '../config/database.php';

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
    
    // Obtener información completa del pedido - usando la misma estructura que aceptar_pedido.php
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
              WHERE p.id_pedido = ? AND p.id_repartidor = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_pedido);
    $stmt->bindParam(2, $id_repartidor);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no autorizado']);
        exit;
    }
    
    $pedido_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener items del pedido desde detalle_pedidos
    $query_items = "SELECT dp.cantidad, dp.precio_unitario as precio, p.nombre, p.descripcion
                    FROM detalle_pedidos dp
                    LEFT JOIN productos p ON dp.id_producto = p.id_producto
                    WHERE dp.id_pedido = ?";
    
    $stmt_items = $db->prepare($query_items);
    $stmt_items->bindParam(1, $id_pedido);
    $stmt_items->execute();
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay items, crear uno genérico
    if (empty($items)) {
        $items = [
            [
                'cantidad' => 1,
                'precio' => $pedido_data['monto_total'] ?? 0,
                'nombre' => 'Pedido completo',
                'descripcion' => 'Detalles del pedido no disponibles'
            ]
        ];
    }
    
    // Mapear estado de ID a texto para JavaScript
    function mapearEstado($id_estado) {
        switch ($id_estado) {
            case 1: return 'asignado';
            case 2: return 'confirmado';
            case 3: return 'preparando';
            case 4: return 'recogido';      // listo_para_entrega se considera "recogido" para el repartidor
            case 5: return 'en_camino_cliente';  // en_camino
            case 6: return 'entregado';
            default: return 'asignado';
        }
    }
    
    // Estructurar respuesta usando el mismo formato que aceptar_pedido.php
    $pedido = [
        'id_pedido' => $pedido_data['id_pedido'],
        'estado' => mapearEstado($pedido_data['id_estado']),
        'id_estado' => $pedido_data['id_estado'],
        'total' => floatval($pedido_data['monto_total'] ?? 0),
        'comision_repartidor' => floatval($pedido_data['comision_repartidor'] ?? 35.00),
        'propina_estimada' => floatval($pedido_data['propina_estimada'] ?? 0.00),
        'metodo_pago' => $pedido_data['metodo_pago'] ?? 'efectivo',
        'notas' => $pedido_data['instrucciones_especiales'] ?? '',
        'negocio' => [
            'nombre' => $pedido_data['nombre_negocio'] ?? 'Restaurante',
            'direccion' => $pedido_data['direccion_negocio'] ?? 'Dirección del restaurante',
            'telefono' => $pedido_data['telefono_negocio'] ?? '',
            'lat' => floatval($pedido_data['lat_negocio']),
            'lng' => floatval($pedido_data['lng_negocio'])
        ],
        'cliente' => [
            'nombre' => trim(($pedido_data['nombre_cliente'] ?? '') . ' ' . ($pedido_data['apellido_cliente'] ?? '')),
            'direccion' => $pedido_data['direccion_cliente'] ?? 'Dirección de entrega',
            'telefono' => $pedido_data['telefono_cliente'] ?? '',
            'lat' => floatval($pedido_data['lat_cliente']),
            'lng' => floatval($pedido_data['lng_cliente'])
        ],
        'items' => $items
    ];
    
    echo json_encode(['success' => true, 'pedido' => $pedido]);
    
} catch (PDOException $e) {
    error_log("Error al obtener detalle del pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error general: ' . $e->getMessage()]);
}
?>