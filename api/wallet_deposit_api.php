<?php
/**
 * QuickBite - API de Depósitos a Wallet
 * Endpoints para gestionar depósitos con OXXO y otros métodos
 *
 * @version 1.0.0
 * @date 2025-11-20
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database_mysqli.php';
require_once __DIR__ . '/../models/WalletDepositService.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function validateSession() {
    session_start();
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado'
        ]);
        exit;
    }
    return $_SESSION['usuario_id'];
}

try {
    $database = new DatabaseMysqli();
    $conn = $database->getConnection();

    // Obtener configuración de MercadoPago
    $mpConfig = require __DIR__ . '/../config/mercadopago.php';
    $accessToken = $mpConfig['access_token'];

    // Inicializar servicio
    $depositService = new WalletDepositService($conn, $accessToken);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($action) {

        // ==================== CREAR DEPÓSITO OXXO ====================
        case 'create_oxxo':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();
            $data = json_decode(file_get_contents('php://input'), true);
            $amount = $data['amount'] ?? null;
            $email = $data['email'] ?? ($_SESSION['email'] ?? null);
            $name = $data['name'] ?? ($_SESSION['nombre'] ?? null);

            if (!$amount || !$email) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Faltan parámetros: amount y email son requeridos'
                ]);
                exit;
            }

            $result = $depositService->createOXXODeposit($userId, $amount, $email, $name);
            echo json_encode($result);
            break;

        // ==================== CONSULTAR ESTADO DE DEPÓSITO ====================
        case 'status':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();
            $depositId = $_GET['deposit_id'] ?? null;

            if (!$depositId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Parámetro deposit_id requerido'
                ]);
                exit;
            }

            $result = $depositService->getDepositStatus($depositId, $userId);
            echo json_encode($result);
            break;

        // ==================== HISTORIAL DE DEPÓSITOS ====================
        case 'history':
            $userId = validateSession();
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $result = $depositService->getDepositHistory($userId, $limit);
            echo json_encode($result);
            break;

        // ==================== CANCELAR DEPÓSITO ====================
        case 'cancel':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();
            $data = json_decode(file_get_contents('php://input'), true);
            $depositId = $data['deposit_id'] ?? null;

            if (!$depositId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Parámetro deposit_id requerido'
                ]);
                exit;
            }

            $result = $depositService->cancelDeposit($depositId, $userId);
            echo json_encode($result);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida',
                'available_actions' => [
                    'create_oxxo' => 'POST - Crear depósito OXXO',
                    'status' => 'GET - Consultar estado de depósito',
                    'history' => 'GET - Historial de depósitos',
                    'cancel' => 'POST - Cancelar depósito pendiente'
                ]
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>
