<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

session_start();
$_SESSION['carrito'] = ['items' => [], 'negocio_id' => 0, 'negocio_nombre' => '', 'subtotal' => 0, 'total' => 0];

echo json_encode([
    'success' => true,
    'message' => 'Carrito vaciado',
    'carrito' => $_SESSION['carrito']
]);
