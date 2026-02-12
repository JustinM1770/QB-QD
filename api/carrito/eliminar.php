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

if (!isset($_SESSION['carrito']['items'])) {
    echo json_encode(['success' => false, 'message' => 'Carrito vacío']);
    exit;
}

$carrito = &$_SESSION['carrito'];
$carrito['items'] = array_values(array_filter($carrito['items'], fn($item) => $item['id'] != $itemId));

$subtotal = array_sum(array_column($carrito['items'], 'subtotal'));
$carrito['subtotal'] = $subtotal;
$carrito['total'] = $subtotal;

if (empty($carrito['items'])) {
    $carrito['negocio_id'] = 0;
    $carrito['negocio_nombre'] = '';
}

echo json_encode([
    'success' => true,
    'carrito' => [
        'items' => $carrito['items'],
        'negocio_id' => (int)$carrito['negocio_id'],
        'negocio_nombre' => $carrito['negocio_nombre'],
        'subtotal' => $carrito['subtotal'],
        'costo_envio' => 0,
        'descuento' => 0,
        'total' => $carrito['total']
    ]
]);
