<?php
/**
 * Proxy para el bot de WhatsApp
 * Evita problemas de CORS al hacer peticiones desde el navegador
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Leer el body JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

// Determinar el endpoint
$endpoint = $_GET['endpoint'] ?? 'send-order';

// URL del bot local
$botUrl = "http://localhost:3030/{$endpoint}";

// Hacer la petición al bot
$ch = curl_init($botUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Manejar errores de conexión
if ($error) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión con el bot de WhatsApp',
        'details' => $error
    ]);
    exit;
}

// Retornar la respuesta del bot
http_response_code($httpCode);
echo $response;
?>
