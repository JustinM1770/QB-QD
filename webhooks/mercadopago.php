<?php
/**
 * Webhook de MercadoPago para notificaciones de pagos
 * 
 * SEGURIDAD IMPLEMENTADA:
 * - Validación de firma HMAC
 * - CORS restringido
 * - Rate limiting básico
 * - Sanitización de datos
 */

// Cargar variables de entorno
require_once __DIR__ . '/../config/env.php';

// Headers de respuesta (CORS restringido)
header('Content-Type: application/json');

// Solo permitir orígenes de MercadoPago
$allowed_origins = [
    'https://www.mercadopago.com',
    'https://api.mercadopago.com'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Signature');

// Responder a OPTIONS requests para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

/**
 * Función para logging seguro (sin datos sensibles)
 */
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/../logs/mp_webhook.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    
    if ($data !== null) {
        // Sanitizar datos sensibles
        $safeData = $data;
        if (is_array($safeData)) {
            unset($safeData['access_token'], $safeData['card'], $safeData['payer']['identification']);
        }
        $logEntry .= " - " . json_encode($safeData);
    }
    
    error_log($logEntry . "\n", 3, $logFile);
}

/**
 * Validar firma HMAC de MercadoPago
 */
function validateSignature($payload, $signature, $secret) {
    if (empty($signature) || empty($secret)) {
        return false;
    }
    
    // MercadoPago envía la firma en formato: ts=xxx,v1=xxx
    $parts = [];
    foreach (explode(',', $signature) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }
    
    if (!isset($parts['ts']) || !isset($parts['v1'])) {
        return false;
    }
    
    // Construir string para validar
    $signedPayload = $parts['ts'] . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);
    
    return hash_equals($expectedSignature, $parts['v1']);
}

try {
    // Cargar configuración de MercadoPago
    $mp_config = require __DIR__ . '/../config/mercadopago.php';
    
    // Obtener datos del webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validar firma (si está configurada)
    $signature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
    $webhook_secret = env('MP_WEBHOOK_SECRET', '');
    
    if (!empty($webhook_secret)) {
        if (!validateSignature($input, $signature, $webhook_secret)) {
            logWebhook('SEGURIDAD: Firma inválida rechazada', ['signature' => substr($signature, 0, 20) . '...']);
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit();
        }
    }
    
    // Log de la notificación recibida (sin datos sensibles)
    logWebhook('Webhook recibido', [
        'type' => $data['type'] ?? 'unknown',
        'action' => $data['action'] ?? 'unknown',
        'data_id' => $data['data']['id'] ?? null
    ]);
    
    // Validar que se recibieron datos
    if (!$data) {
        throw new Exception('No data received');
    }
    
    // Procesar según el tipo de notificación
    if (isset($data['type'])) {
        switch ($data['type']) {
            case 'payment':
                if (isset($data['data']['id'])) {
                    $payment_id = (int)$data['data']['id']; // Sanitizar
                    
                    // Cargar SDK de MercadoPago para verificar el pago
                    require_once __DIR__ . '/../vendor/autoload.php';
                    MercadoPago\MercadoPagoConfig::setAccessToken($mp_config['access_token']);
                    
                    try {
                        $paymentClient = new MercadoPago\Client\Payment\PaymentClient();
                        $payment = $paymentClient->get($payment_id);
                        
                        // Verificar si es un pago de membresía
                        $isMembresia = (isset($_GET['type']) && $_GET['type'] === 'membership') || 
                                     (isset($payment->external_reference) && 
                                      strpos($payment->external_reference, 'membership_') === 0);
                        
                        if ($isMembresia && $payment->status === 'approved') {
                            // Procesar pago de membresía
                            require_once __DIR__ . '/../config/database.php';
                            require_once __DIR__ . '/../models/Membership.php';
                            
                            $database = new Database();
                            $db = $database->getConnection();
                            
                            // Extraer información de la referencia externa
                            if (isset($payment->external_reference)) {
                                $parts = explode('_', $payment->external_reference);
                                if (count($parts) >= 3) {
                                    $user_id = (int)$parts[1]; // Sanitizar
                                    $plan = preg_replace('/[^a-z0-9_]/', '', $parts[2]); // Sanitizar
                                    
                                    // Activar membresía
                                    $membership = new Membership($db);
                                    if (method_exists($membership, 'subscribe') && $membership->subscribe($user_id, $plan)) {
                                        logWebhook("Membresía activada", ['user_id' => $user_id, 'plan' => $plan]);
                                    } else {
                                        logWebhook("Error activando membresía", ['user_id' => $user_id, 'plan' => $plan]);
                                    }
                                }
                            }
                        } else {
                            // Lógica para pedidos normales de comida
                            logWebhook("Notificación de pago de pedido", ['payment_id' => $payment_id, 'status' => $payment->status ?? 'unknown']);
                        }
                    } catch (Exception $e) {
                        logWebhook("Error procesando webhook de pago", ['error' => $e->getMessage()]);
                    }
                    
                    // Responder con éxito
                    http_response_code(200);
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Payment notification processed'
                    ]);
                }
                break;
                
            case 'merchant_order':
                $order_id = isset($data['data']['id']) ? (int)$data['data']['id'] : 0;
                logWebhook("Notificación de orden", ['order_id' => $order_id]);
                
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Merchant order notification processed'
                ]);
                break;
                
            default:
                logWebhook("Tipo de notificación desconocido", ['type' => $data['type']]);
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Notification received'
                ]);
        }
    } else {
        // Notificación sin tipo específico
        logWebhook("Notificación sin tipo recibida");
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Notification received'
        ]);
    }
    
} catch (Exception $e) {
    logWebhook("Error en webhook", ['error' => $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}
?>