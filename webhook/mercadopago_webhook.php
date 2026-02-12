<?php
/**
 * Webhook Handler para MercadoPago - Versión Segura
 * Recibe notificaciones de cambios de estado de pagos
 * Implementa validación HMAC según documentación oficial de MercadoPago
 */

// Cargar configuración y variables de entorno
require_once __DIR__ . '/../config/env.php';

// Headers de seguridad y respuesta
// CORS restringido a MercadoPago
$allowed_origins = ['https://api.mercadopago.com', 'https://www.mercadopago.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://api.mercadopago.com');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-signature, x-request-id');
header('Content-Type: application/json');

// Manejo de preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log de debug para webhooks
function logWebhook($message, $data = null) {
    $log_message = "[WEBHOOK MP] " . date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log_message .= " | Data: " . json_encode($data);
    }
    $log_file = __DIR__ . '/../logs/mercadopago_webhook.log';
    
    // Crear directorio de logs si no existe
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    error_log($log_message . PHP_EOL, 3, $log_file);
    
    // También log al archivo de errores estándar
    error_log($log_message);
}

/**
 * Valida la firma HMAC del webhook de MercadoPago
 * Previene notificaciones falsas según OWASP y documentación oficial de MP
 * 
 * @param string $xSignature Header x-signature
 * @param string $xRequestId Header x-request-id  
 * @param string $dataId ID del recurso en la notificación
 * @return bool True si la firma es válida
 */
function validateMercadoPagoSignature($xSignature, $xRequestId, $dataId) {
    // Obtener el secret key desde variables de entorno
    $secretKey = env('MP_WEBHOOK_SECRET', '');
    
    if (empty($secretKey)) {
        logWebhook("⚠️ ADVERTENCIA: MP_WEBHOOK_SECRET no configurado - Validación HMAC deshabilitada");
        // En producción, esto debería retornar false
        if (env('ENVIRONMENT') === 'production') {
            return false;
        }
        return true; // Solo para desarrollo
    }
    
    if (empty($xSignature) || empty($xRequestId)) {
        logWebhook("❌ Headers de seguridad faltantes", [
            'x-signature' => !empty($xSignature),
            'x-request-id' => !empty($xRequestId)
        ]);
        return false;
    }
    
    // Parsear el header x-signature (formato: ts=xxx,v1=xxx)
    $parts = explode(',', $xSignature);
    $ts = null;
    $hash = null;
    
    foreach ($parts as $part) {
        $keyValue = explode('=', trim($part), 2);
        if (count($keyValue) == 2) {
            if ($keyValue[0] === 'ts') {
                $ts = $keyValue[1];
            } elseif ($keyValue[0] === 'v1') {
                $hash = $keyValue[1];
            }
        }
    }
    
    if (empty($ts) || empty($hash)) {
        logWebhook("❌ Formato de x-signature inválido", ['x-signature' => $xSignature]);
        return false;
    }
    
    // Verificar que el timestamp no sea muy antiguo (previene replay attacks)
    $currentTime = time();
    $signatureTime = (int)$ts;
    $tolerance = 300; // 5 minutos de tolerancia
    
    if (abs($currentTime - $signatureTime) > $tolerance) {
        logWebhook("❌ Timestamp de firma expirado", [
            'signature_time' => $signatureTime,
            'current_time' => $currentTime,
            'diff' => abs($currentTime - $signatureTime)
        ]);
        return false;
    }
    
    // Construir el manifest string según documentación de MercadoPago
    // Formato: id:[data.id];request-id:[x-request-id];ts:[ts];
    $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
    
    // Calcular HMAC-SHA256
    $calculatedHash = hash_hmac('sha256', $manifest, $secretKey);
    
    // Comparación segura de tiempo constante
    if (!hash_equals($calculatedHash, $hash)) {
        logWebhook("❌ Firma HMAC inválida", [
            'expected' => substr($calculatedHash, 0, 16) . '...',
            'received' => substr($hash, 0, 16) . '...'
        ]);
        return false;
    }
    
    logWebhook("✅ Firma HMAC válida");
    return true;
}

try {
    logWebhook("🔔 Webhook recibido", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Obtener el cuerpo de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    logWebhook("📥 Datos recibidos", $data);
    
    // ============================================
    // VALIDACIÓN DE FIRMA HMAC (SEGURIDAD CRÍTICA)
    // ============================================
    $xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $dataId = $data['data']['id'] ?? $data['id'] ?? '';
    
    if (!validateMercadoPagoSignature($xSignature, $xRequestId, $dataId)) {
        logWebhook("🚫 ALERTA DE SEGURIDAD: Webhook rechazado por firma inválida", [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Invalid signature']);
        exit;
    }
    
    // Verificar que se recibieron datos válidos
    if (!$data) {
        logWebhook("❌ No se recibieron datos válidos");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }
    
    // Extraer información del webhook
    $type = $data['type'] ?? null;
    $action = $data['action'] ?? null;
    $payment_id = null;
    
    // Obtener ID del pago según el tipo de notificación
    if ($type === 'payment') {
        $payment_id = $data['data']['id'] ?? null;
    } elseif (isset($data['id'])) {
        $payment_id = $data['id'];
    }
    
    logWebhook("📊 Procesando notificación", [
        'type' => $type,
        'action' => $action, 
        'payment_id' => $payment_id
    ]);
    
    // Validar que tenemos un ID de pago
    if (!$payment_id) {
        logWebhook("❌ No se encontró ID de pago en la notificación");
        http_response_code(400);
        echo json_encode(['error' => 'No payment ID found']);
        exit;
    }
    
    // Cargar configuración de MercadoPago
    require_once '../config/mercadopago.php';
    require_once '../vendor/autoload.php';
    
    // Configurar SDK
    MercadoPago\MercadoPagoConfig::setAccessToken($mp_config['access_token']);
    
    // Obtener información del pago desde MercadoPago
    $paymentClient = new MercadoPago\Client\Payment\PaymentClient();
    $payment = $paymentClient->get($payment_id);
    
    logWebhook("💳 Pago obtenido de MercadoPago", [
        'id' => $payment->id,
        'status' => $payment->status,
        'status_detail' => $payment->status_detail,
        'external_reference' => $payment->external_reference ?? null
    ]);
    
    // Conectar a base de datos para actualizar estado
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Buscar el pedido por external_reference o por payment_id
    $external_ref = $payment->external_reference ?? null;
    $pedido_id = null;
    
    if ($external_ref && strpos($external_ref, 'QB_') === 0) {
        // Buscar por external_reference en metadata o en tabla de pagos
        $stmt = $db->prepare("
            SELECT p.id_pedido, p.id_estado 
            FROM pedidos p 
            WHERE p.referencia_externa = ? OR p.payment_id = ?
            LIMIT 1
        ");
        $stmt->execute([$external_ref, $payment_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedido) {
            $pedido_id = $pedido['id_pedido'];
        }
    }
    
    // Si no se encontró por referencia, buscar por payment_id
    if (!$pedido_id) {
        $stmt = $db->prepare("
            SELECT id_pedido, id_estado 
            FROM pedidos 
            WHERE payment_id = ?
            LIMIT 1
        ");
        $stmt->execute([$payment_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedido) {
            $pedido_id = $pedido['id_pedido'];
        }
    }
    
    if (!$pedido_id) {
        logWebhook("⚠️ No se encontró pedido asociado al pago", [
            'payment_id' => $payment_id,
            'external_reference' => $external_ref
        ]);
        
        // Aún así respondemos OK para que MercadoPago no siga enviando
        echo json_encode(['status' => 'ok', 'message' => 'Payment not found but acknowledged']);
        exit;
    }
    
    // Determinar nuevo estado basado en el status del pago
    $nuevo_estado = null;
    
    switch ($payment->status) {
        case 'approved':
            $nuevo_estado = 2; // Confirmado/Pagado
            logWebhook("✅ Pago aprobado - actualizando a estado confirmado");
            break;
            
        case 'pending':
            $nuevo_estado = 1; // Pendiente
            logWebhook("⏳ Pago pendiente - manteniendo estado pendiente");
            break;
            
        case 'rejected':
        case 'cancelled':
            $nuevo_estado = 7; // Cancelado
            logWebhook("❌ Pago rechazado/cancelado - actualizando a cancelado");
            break;
            
        default:
            logWebhook("⚠️ Estado de pago desconocido: " . $payment->status);
            break;
    }
    
    // Actualizar estado del pedido si es necesario
    if ($nuevo_estado && $pedido['id_estado'] != $nuevo_estado) {
        $stmt = $db->prepare("
            UPDATE pedidos 
            SET id_estado = ?, 
                payment_status = ?,
                payment_status_detail = ?,
                updated_at = NOW()
            WHERE id_pedido = ?
        ");
        
        $updated = $stmt->execute([
            $nuevo_estado,
            $payment->status,
            $payment->status_detail,
            $pedido_id
        ]);
        
        if ($updated) {
            logWebhook("✅ Estado del pedido actualizado", [
                'pedido_id' => $pedido_id,
                'estado_anterior' => $pedido['id_estado'],
                'estado_nuevo' => $nuevo_estado,
                'payment_status' => $payment->status
            ]);
        } else {
            logWebhook("❌ Error actualizando estado del pedido", [
                'pedido_id' => $pedido_id,
                'nuevo_estado' => $nuevo_estado
            ]);
        }
    }
    
    // Respuesta exitosa
    logWebhook("🎉 Webhook procesado exitosamente", [
        'pedido_id' => $pedido_id,
        'payment_status' => $payment->status
    ]);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'payment_id' => $payment_id,
        'pedido_id' => $pedido_id,
        'new_status' => $payment->status
    ]);
    
} catch (Exception $e) {
    logWebhook("❌ Error procesando webhook: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
?>