<?php
/**
 * API para pagos SPEI (Transferencia Bancaria) con MercadoPago
 * Endpoint: /api/mercadopago/spei_payment.php
 *
 * Métodos:
 *   POST - Crear un nuevo pago SPEI
 *   GET  - Consultar estado de un pago SPEI
 *
 * @version 1.0.0
 * @date 2026-01-24
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/SPEIPaymentService.php';

// Iniciar sesión para validar usuario
session_start();

/**
 * Responde con JSON y termina
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Obtiene la conexión mysqli
 */
function getMysqliConnection() {
    $host = env('DB_HOST', 'localhost');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $dbname = env('DB_NAME', 'quickbite');
    $port = env('DB_PORT', 3306);

    $conn = new mysqli($host, $user, $pass, $dbname, $port);

    if ($conn->connect_error) {
        throw new Exception('Error de conexión a base de datos: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

try {
    // Verificar usuario autenticado
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
        jsonResponse(['error' => 'No autorizado. Debe iniciar sesión.'], 401);
    }

    $userId = $_SESSION['user_id'] ?? $_SESSION['usuario_id'];

    // Obtener conexión a BD
    $conn = getMysqliConnection();

    // Inicializar servicio SPEI
    $speiService = new SPEIPaymentService($conn);

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'POST':
            // =====================
            // CREAR PAGO SPEI
            // =====================
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                // Intentar con form data
                $input = $_POST;
            }

            if (empty($input)) {
                jsonResponse(['error' => 'Datos de pago no proporcionados'], 400);
            }

            // Validar pedido_id
            $pedidoId = isset($input['pedido_id']) ? (int)$input['pedido_id'] : null;

            if (!$pedidoId) {
                jsonResponse(['error' => 'Se requiere pedido_id'], 400);
            }

            // Verificar que el pedido pertenezca al usuario
            $stmtPedido = $conn->prepare(
                "SELECT id_pedido, monto_total, id_usuario, id_estado FROM pedidos WHERE id_pedido = ?"
            );
            $stmtPedido->bind_param('i', $pedidoId);
            $stmtPedido->execute();
            $resultPedido = $stmtPedido->get_result();

            if ($resultPedido->num_rows === 0) {
                $stmtPedido->close();
                jsonResponse(['error' => 'Pedido no encontrado'], 404);
            }

            $pedido = $resultPedido->fetch_assoc();
            $stmtPedido->close();

            // Verificar propiedad del pedido
            if ((int)$pedido['id_usuario'] !== (int)$userId) {
                jsonResponse(['error' => 'No tiene permiso para pagar este pedido'], 403);
            }

            // Verificar estado del pedido (debe estar pendiente de pago)
            if ((int)$pedido['id_estado'] !== 1) {
                jsonResponse(['error' => 'Este pedido ya no puede ser pagado'], 400);
            }

            // Obtener email del usuario
            $stmtUser = $conn->prepare("SELECT email, nombre, apellido FROM usuarios WHERE id = ?");
            $stmtUser->bind_param('i', $userId);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $usuario = $resultUser->fetch_assoc();
            $stmtUser->close();

            $email = $input['email'] ?? $usuario['email'] ?? null;

            if (!$email) {
                jsonResponse(['error' => 'Se requiere un email válido'], 400);
            }

            // Crear pago SPEI
            $result = $speiService->createSPEIPayment([
                'pedido_id' => $pedidoId,
                'amount' => $input['amount'] ?? $pedido['monto_total'],
                'email' => $email,
                'description' => $input['description'] ?? "Pedido QuickBite #{$pedidoId}",
                'first_name' => $input['first_name'] ?? $usuario['nombre'] ?? 'Cliente',
                'last_name' => $input['last_name'] ?? $usuario['apellido'] ?? 'QuickBite'
            ]);

            $statusCode = $result['success'] ? 201 : 400;
            jsonResponse($result, $statusCode);
            break;

        case 'GET':
            // =====================
            // CONSULTAR ESTADO SPEI
            // =====================
            $pedidoId = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : null;
            $paymentId = $_GET['payment_id'] ?? null;

            if (!$pedidoId && !$paymentId) {
                jsonResponse(['error' => 'Se requiere pedido_id o payment_id'], 400);
            }

            if ($pedidoId) {
                // Verificar que el pedido pertenezca al usuario
                $stmtVerify = $conn->prepare("SELECT id_usuario FROM pedidos WHERE id_pedido = ?");
                $stmtVerify->bind_param('i', $pedidoId);
                $stmtVerify->execute();
                $resultVerify = $stmtVerify->get_result();

                if ($resultVerify->num_rows === 0) {
                    $stmtVerify->close();
                    jsonResponse(['error' => 'Pedido no encontrado'], 404);
                }

                $pedidoData = $resultVerify->fetch_assoc();
                $stmtVerify->close();

                if ((int)$pedidoData['id_usuario'] !== (int)$userId) {
                    jsonResponse(['error' => 'No tiene permiso para ver este pago'], 403);
                }

                $result = $speiService->getPaymentStatus($pedidoId);
            } else {
                // Buscar por payment_id
                $stmtFind = $conn->prepare(
                    "SELECT sp.pedido_id, p.id_usuario
                     FROM spei_payments sp
                     JOIN pedidos p ON sp.pedido_id = p.id_pedido
                     WHERE sp.mercadopago_payment_id = ?"
                );
                $stmtFind->bind_param('s', $paymentId);
                $stmtFind->execute();
                $resultFind = $stmtFind->get_result();

                if ($resultFind->num_rows === 0) {
                    $stmtFind->close();
                    jsonResponse(['error' => 'Pago no encontrado'], 404);
                }

                $paymentData = $resultFind->fetch_assoc();
                $stmtFind->close();

                if ((int)$paymentData['id_usuario'] !== (int)$userId) {
                    jsonResponse(['error' => 'No tiene permiso para ver este pago'], 403);
                }

                $result = $speiService->getPaymentStatus($paymentData['pedido_id']);
            }

            $statusCode = $result['success'] ? 200 : 404;
            jsonResponse($result, $statusCode);
            break;

        default:
            jsonResponse(['error' => 'Método no permitido'], 405);
    }

} catch (Exception $e) {
    error_log('SPEI Payment API Error: ' . $e->getMessage());
    jsonResponse([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ], 500);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
