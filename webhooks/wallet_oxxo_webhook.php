<?php
/**
 * QuickBite - Webhook para Depósitos OXXO
 * Procesa notificaciones de MercadoPago cuando se completa un pago OXXO
 * 
 * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/../config/database_mysqli.php';
require_once __DIR__ . '/../models/WalletDepositService.php';
// Log de la petición
$logFile = __DIR__ . '/../logs/webhook_oxxo_' . date('Y-m-d') . '.log';
$rawInput = file_get_contents('php://input');
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n\n[{$timestamp}] ===== WEBHOOK RECIBIDO =====\n", FILE_APPEND);
file_put_contents($logFile, "Headers: " . json_encode(getallheaders()) . "\n", FILE_APPEND);
file_put_contents($logFile, "Body: {$rawInput}\n", FILE_APPEND);
try {
    // Parsear datos del webhook
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        throw new Exception('Body inválido');
    }
    file_put_contents($logFile, "Data parsed: " . json_encode($data) . "\n", FILE_APPEND);
    // Verificar que sea una notificación de pago
    if (!isset($data['type']) || $data['type'] !== 'payment') {
        file_put_contents($logFile, "Tipo de notificación ignorado: {$data['type']}\n", FILE_APPEND);
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit;
    // Obtener ID del pago
    $paymentId = null;
    if (isset($data['data']['id'])) {
        $paymentId = $data['data']['id'];
    } elseif (isset($data['id'])) {
        $paymentId = $data['id'];
    if (!$paymentId) {
        throw new Exception('No se encontró payment ID');
    file_put_contents($logFile, "Processing payment ID: {$paymentId}\n", FILE_APPEND);
    // Conectar a la base de datos
    $database = new DatabaseMysqli();
    $conn = $database->getConnection();
    // Obtener configuración de MercadoPago
    $mpConfig = require __DIR__ . '/../config/mercadopago.php';
    $accessToken = $mpConfig['access_token'];
    // Inicializar servicio
    $depositService = new WalletDepositService($conn, $accessToken);
    // Procesar el pago
    $result = $depositService->processWebhookPayment($paymentId);
    file_put_contents($logFile, "Resultado: " . json_encode($result) . "\n", FILE_APPEND);
    if ($result['success']) {
        // Aquí puedes enviar notificación al usuario
        // Email, push notification, etc.
        
        echo json_encode([
            'status' => 'processed',
            'message' => 'Depósito acreditado exitosamente'
}
        ]);
    } else {
        http_response_code(200); // Siempre 200 para que MP no reintente
            'status' => 'error',
            'message' => $result['message']
} catch (Exception $e) {
    file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(200); // Siempre 200 para evitar reintentos infinitos
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
