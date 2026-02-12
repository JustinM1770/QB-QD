<?php
/**
 * Endpoint para confirmar pago SPEI desde el bot de WhatsApp
 * Cuando el negocio responde "recibido" el bot llama este endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Token de seguridad para validar que viene del bot
$bot_token = getenv('WHATSAPP_BOT_SECRET') ?: 'quickbite_bot_internal_2024';
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($auth_header !== 'Bearer ' . $bot_token) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = isset($input['pedido_id']) ? (int)$input['pedido_id'] : 0;

if ($pedido_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'pedido_id requerido']);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';

    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el pedido existe, está en estado 7 (pendiente pago) y es SPEI
    $stmt = $db->prepare("
        SELECT p.id_pedido, p.id_estado, p.metodo_pago, p.monto_total, p.id_usuario, p.id_negocio,
               u.nombre AS cliente_nombre, u.telefono AS cliente_telefono,
               n.nombre AS negocio_nombre
        FROM pedidos p
        JOIN usuarios u ON u.id_usuario = p.id_usuario
        JOIN negocios n ON n.id_negocio = p.id_negocio
        WHERE p.id_pedido = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    if ($pedido['id_estado'] != 7) {
        echo json_encode(['success' => false, 'error' => 'Pedido no está pendiente de pago', 'estado_actual' => $pedido['id_estado']]);
        exit;
    }

    if ($pedido['metodo_pago'] !== 'spei' && $pedido['metodo_pago'] !== 'efectivo') {
        echo json_encode(['success' => false, 'error' => 'Pedido no es SPEI/efectivo']);
        exit;
    }

    // Cambiar estado a 2 (Confirmado)
    $stmt_update = $db->prepare("
        UPDATE pedidos
        SET id_estado = 2,
            payment_status = 'approved',
            fecha_actualizacion = NOW()
        WHERE id_pedido = ? AND id_estado = 7
    ");
    $stmt_update->execute([$pedido_id]);

    if ($stmt_update->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el pedido']);
        exit;
    }

    // Notificar al cliente por WhatsApp
    $cliente_telefono = $pedido['cliente_telefono'] ?? '';
    $notificacion_enviada = false;

    if (!empty($cliente_telefono)) {
        require_once __DIR__ . '/WhatsAppLocalClient.php';
        $whatsapp = new WhatsAppLocalClient();

        $mensaje_cliente = "✅ *¡Pago confirmado!*\n\n"
            . "📋 *Pedido #" . $pedido_id . "*\n"
            . "🏪 *" . $pedido['negocio_nombre'] . "* confirmó la recepción de tu transferencia.\n"
            . "💰 *Monto:* $" . number_format($pedido['monto_total'], 2) . "\n\n"
            . "Tu pedido está siendo procesado. Te notificaremos cuando esté en preparación.";

        $resultado = $whatsapp->sendMessage($cliente_telefono, $mensaje_cliente);
        $notificacion_enviada = $resultado['success'] ?? false;
    }

    echo json_encode([
        'success' => true,
        'pedido_id' => $pedido_id,
        'nuevo_estado' => 2,
        'cliente_notificado' => $notificacion_enviada
    ]);

} catch (Exception $e) {
    error_log("Error en confirmar_pago_spei: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
