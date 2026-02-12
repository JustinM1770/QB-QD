<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$carrito = $_SESSION['carrito'] ?? ['items' => [], 'negocio_id' => 0, 'negocio_nombre' => '', 'subtotal' => 0, 'total' => 0];

echo json_encode([
    'success' => true,
    'carrito' => [
        'items' => $carrito['items'] ?? [],
        'negocio_id' => (int)($carrito['negocio_id'] ?? 0),
        'negocio_nombre' => $carrito['negocio_nombre'] ?? '',
        'subtotal' => (float)($carrito['subtotal'] ?? 0),
        'costo_envio' => 0,
        'descuento' => 0,
        'total' => (float)($carrito['total'] ?? 0)
    ]
]);
