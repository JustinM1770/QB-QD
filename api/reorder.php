<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['pedido_id'])) {
        throw new Exception('ID de pedido no proporcionado');
    }

    $pedido_id = intval($input['pedido_id']);
    $user_id = $_SESSION['id_usuario'];

    // Incluir archivos necesarios
    require_once '../config/database.php';
    require_once '../models/Pedido.php';
    require_once '../models/DetallePedido.php';
    require_once '../models/Carrito.php';

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Verificar que el pedido pertenece al usuario
    $query = "SELECT id_usuario FROM pedidos WHERE id_pedido = :pedido_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':pedido_id', $pedido_id);
    $stmt->execute();
    
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido || $pedido['id_usuario'] != $user_id) {
        throw new Exception('Pedido no encontrado o no autorizado');
    }

    // Obtener los productos del pedido original
    $query = "SELECT id_producto, cantidad, precio_unitario, notas 
              FROM detalle_pedidos 
              WHERE id_pedido = :pedido_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':pedido_id', $pedido_id);
    $stmt->execute();
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($productos)) {
        throw new Exception('No se encontraron productos en el pedido');
    }

    // Instanciar modelo de carrito
    $carrito = new Carrito($db);
    $productosAgregados = 0;

    // Agregar cada producto al carrito
    foreach ($productos as $producto) {
        try {
            $carrito->id_usuario = $user_id;
            $carrito->id_producto = $producto['id_producto'];
            $carrito->cantidad = $producto['cantidad'];
            $carrito->notas = $producto['notas'] ?? '';
            
            if ($carrito->agregar()) {
                $productosAgregados++;
            }
        } catch (Exception $e) {
            error_log("Error al agregar producto {$producto['id_producto']} al carrito: " . $e->getMessage());
            // Continuar con los demás productos
        }
    }

    if ($productosAgregados > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Se agregaron {$productosAgregados} productos al carrito",
            'productos_agregados' => $productosAgregados,
            'total_productos' => count($productos)
        ]);
    } else {
        throw new Exception('No se pudo agregar ningún producto al carrito');
    }

} catch (Exception $e) {
    error_log("Error en reorder.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>