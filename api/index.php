<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once 'OrderController.php';

header("Content-Type: application/json");

// Función para validar sesión
function requireAuth() {
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(["message" => "No autorizado. Debe iniciar sesión."]);
        exit;
    }
    return $_SESSION['id_usuario'];
}

// Función para validar JSON
function getJsonInput() {
    $input = file_get_contents("php://input");
    if (empty($input)) {
        return null;
    }
    $data = json_decode($input);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["message" => "JSON inválido: " . json_last_error_msg()]);
        exit;
    }
    return $data;
}

$database = new Database();
$db = $database->getConnection();

$orderController = new OrderController($db);

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Simple routing based on URI and method
if (preg_match('/\/api\/orders\/(\d+)\/status$/', $uri, $matches) && $method === 'PUT') {
    $userId = requireAuth();
    $orderId = (int)$matches[1];
    $data = getJsonInput();

    // Verificar que el usuario tiene permiso para modificar este pedido
    if (!$orderController->userCanModifyOrder($userId, $orderId)) {
        http_response_code(403);
        echo json_encode(["message" => "No tiene permiso para modificar este pedido"]);
        exit;
    }

    if (isset($data->status)) {
        $orderController->updateOrderStatusByBusiness($orderId, $data->status);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Estado no proporcionado"]);
    }
} elseif (preg_match('/\/api\/orders\/(\d+)\/assign-repartidor$/', $uri, $matches) && $method === 'POST') {
    $userId = requireAuth();
    $orderId = (int)$matches[1];
    $data = getJsonInput();

    // Verificar que el usuario tiene permiso para asignar repartidor
    if (!$orderController->userCanModifyOrder($userId, $orderId)) {
        http_response_code(403);
        echo json_encode(["message" => "No tiene permiso para modificar este pedido"]);
        exit;
    }

    if (isset($data->repartidor_id)) {
        $orderController->assignRepartidorAndStartDelivery($orderId, (int)$data->repartidor_id);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "ID de repartidor no proporcionado"]);
    }
} elseif ($uri === '/api/orders' && $method === 'POST') {
    requireAuth();
    $orderController->create();
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint no encontrado"]);
}
