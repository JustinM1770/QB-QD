<?php
/**
 * Endpoint para recibir webhooks de WhatsApp Cloud API
 * Este archivo debe ser configurado como Webhook URL en Meta for Developers:
 * https://tudominio.com/whatsapp_webhook.php
 * 
 * Configuración en Meta:
 * 1. Ir a https://developers.facebook.com/apps/
 * 2. Seleccionar tu app > WhatsApp > Configuration
 * 3. Webhook URL: https://quickbite.com.mx/whatsapp_webhook.php
 * 4. Verify Token: debe coincidir con WHATSAPP_VERIFY_TOKEN en config
 * 5. Suscribirse a eventos: messages, message_status
 */

require_once __DIR__ . '/api/WhatsAppService.php';

// Instanciar el servicio de WhatsApp
$whatsappService = new WhatsAppService();

// Método GET: Verificación del webhook (Meta lo llama al configurar)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    // Verificar token usando el método correcto
    $verifiedChallenge = $whatsappService->verifyWebhook($mode, $token, $challenge);
    
    if ($verifiedChallenge !== false) {
        // Responder con el challenge para completar la verificación
        http_response_code(200);
        echo $verifiedChallenge;
        exit;
    } else {
        // Token inválido
        http_response_code(403);
        echo json_encode(['error' => 'Verification failed']);
        exit;
    }
}

// Método POST: Procesar webhooks entrantes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener el payload JSON
    $payload = file_get_contents('php://input');
    
    // Procesar el webhook
    try {
        $result = $whatsappService->handleWebhook($payload);
        
        // Responder siempre con 200 para que Meta no reintente
        http_response_code(200);
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        // Aunque falle internamente, respondemos 200 a Meta para evitar bucles de reintentos infinitos
        error_log("Error procesando webhook: " . $e->getMessage());
        http_response_code(200);
        echo json_encode(['status' => 'error', 'message' => 'Internal processing error']);
        exit;
    }
}

// Método no soportado (Si no es GET ni POST)
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;
?>