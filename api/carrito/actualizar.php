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

$input = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($input['id'] ?? 0);
$cantidad = (int)($input['cantidad'] ?? 0);

if (!isset($_SESSION['carrito']['items'])) {
    echo json_encode(['success' => false, 'message' => 'Carrito vacío']);
    exit;
}

$carrito = &$_SESSION['carrito'];

foreach ($carrito['items'] as $key => &$item) {
    if ($item['id'] == $itemId) {
        if ($cantidad <= 0) {
            array_splice($carrito['items'], $key, 1);
        } else {
            $item['cantidad'] = $cantidad;
            $item['subtotal'] = $item['precio'] * $cantidad;
        }
        break;
    }
}

$subtotal = array_sum(array_column($carrito['items'], 'subtotal'));
$carrito['subtotal'] = $subtotal;
$carrito['total'] = $subtotal;

echo json_encode([
    'success' => true,
    'carrito' => [
        'items' => array_values($carrito['items']),
        'negocio_id' => (int)$carrito['negocio_id'],
        'negocio_nombre' => $carrito['negocio_nombre'],
        'subtotal' => $carrito['subtotal'],
        'costo_envio' => 0,
        'descuento' => 0,
        'total' => $carrito['total']
    ]
]);
