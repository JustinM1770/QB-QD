<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

session_start();
if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$idProducto = (int)($input['id_producto'] ?? 0);
$cantidad = max(1, (int)($input['cantidad'] ?? 1));
$notas = trim($input['notas'] ?? '');

if (!$idProducto) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Producto requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.precio, p.imagen, p.id_negocio, n.nombre as negocio_nombre
                           FROM productos p
                           JOIN negocios n ON p.id_negocio = n.id
                           WHERE p.id = ? AND p.activo = 1");
    $stmt->execute([$idProducto]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }

    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = ['items' => [], 'negocio_id' => 0, 'negocio_nombre' => ''];
    }

    $carrito = &$_SESSION['carrito'];

    // Si el carrito tiene items de otro negocio, vaciar
    if ($carrito['negocio_id'] && $carrito['negocio_id'] != $producto['id_negocio']) {
        $carrito = ['items' => [], 'negocio_id' => 0, 'negocio_nombre' => ''];
    }

    $carrito['negocio_id'] = (int)$producto['id_negocio'];
    $carrito['negocio_nombre'] = $producto['negocio_nombre'];

    // Buscar si ya existe el producto
    $found = false;
    foreach ($carrito['items'] as &$item) {
        if ($item['id_producto'] == $idProducto) {
            $item['cantidad'] += $cantidad;
            $item['subtotal'] = $item['cantidad'] * $item['precio'];
            $found = true;
            break;
        }
    }

    if (!$found) {
        $carrito['items'][] = [
            'id' => count($carrito['items']) + 1,
            'id_producto' => (int)$producto['id'],
            'nombre' => $producto['nombre'],
            'precio' => (float)$producto['precio'],
            'cantidad' => $cantidad,
            'imagen' => $producto['imagen'],
            'notas' => $notas,
            'subtotal' => (float)$producto['precio'] * $cantidad
        ];
    }

    // Recalcular totales
    $subtotal = array_sum(array_column($carrito['items'], 'subtotal'));
    $carrito['subtotal'] = $subtotal;
    $carrito['total'] = $subtotal;

    echo json_encode([
        'success' => true,
        'message' => 'Producto agregado al carrito',
        'carrito' => [
            'items' => $carrito['items'],
            'negocio_id' => $carrito['negocio_id'],
            'negocio_nombre' => $carrito['negocio_nombre'],
            'subtotal' => $carrito['subtotal'],
            'costo_envio' => 0,
            'descuento' => 0,
            'total' => $carrito['total']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
