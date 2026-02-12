<?php
/**
 * API para guardar/obtener personalización por unidad en el carrito
 * 
 * POST /api/personalizacion_carrito.php
 * Body: {
 *   "action": "guardar",
 *   "id_detalle_pedido": 123,
 *   "unidades": [
 *     {"numero_unidad": 1, "id_elegible": 21, "opciones": [1, 4], "notas": ""},
 *     {"numero_unidad": 2, "id_elegible": 22, "opciones": [2], "notas": "bien dorado"}
 *   ]
 * }
 * 
 * GET /api/personalizacion_carrito.php?id_detalle_pedido=123
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PersonalizacionUnidad.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $personalizacion = new PersonalizacionUnidad($db);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obtener personalización existente
        $id_detalle_pedido = filter_input(INPUT_GET, 'id_detalle_pedido', FILTER_VALIDATE_INT);
        
        if (!$id_detalle_pedido) {
            throw new Exception('ID de detalle inválido');
        }
        
        $unidades = $personalizacion->obtenerPersonalizacion($id_detalle_pedido);
        
        echo json_encode([
            'success' => true,
            'unidades' => $unidades
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Guardar personalización
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Datos JSON inválidos');
        }
        
        $action = $input['action'] ?? 'guardar';
        
        switch ($action) {
            case 'guardar':
                $id_detalle_pedido = filter_var($input['id_detalle_pedido'] ?? 0, FILTER_VALIDATE_INT);
                $unidades = $input['unidades'] ?? [];
                
                if (!$id_detalle_pedido || $id_detalle_pedido <= 0) {
                    throw new Exception('ID de detalle inválido');
                }
                
                if (empty($unidades)) {
                    throw new Exception('No hay unidades para guardar');
                }
                
                $resultado = $personalizacion->guardarPersonalizacion($id_detalle_pedido, $unidades);
                
                if ($resultado['success']) {
                    // Actualizar precio en detalles_pedido
                    $query = "UPDATE detalles_pedido 
                              SET subtotal = precio_unitario + :adicional
                              WHERE id_detalle_pedido = :id_detalle_pedido";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':adicional', $resultado['precio_adicional_total']);
                    $stmt->bindParam(':id_detalle_pedido', $id_detalle_pedido, PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'calcular_precio':
                $id_producto = filter_var($input['id_producto'] ?? 0, FILTER_VALIDATE_INT);
                $cantidad = filter_var($input['cantidad'] ?? 1, FILTER_VALIDATE_INT);
                $unidades = $input['unidades'] ?? [];
                
                if (!$id_producto) {
                    throw new Exception('ID de producto inválido');
                }
                
                $precio_total = $personalizacion->calcularPrecioTotal($id_producto, $cantidad, $unidades);
                
                echo json_encode([
                    'success' => true,
                    'precio_total' => $precio_total
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'eliminar':
                $id_detalle_pedido = filter_var($input['id_detalle_pedido'] ?? 0, FILTER_VALIDATE_INT);
                
                if (!$id_detalle_pedido) {
                    throw new Exception('ID de detalle inválido');
                }
                
                $resultado = $personalizacion->eliminarPersonalizacion($id_detalle_pedido);
                
                echo json_encode([
                    'success' => $resultado
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('Acción no reconocida');
        }
    } else {
        throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
