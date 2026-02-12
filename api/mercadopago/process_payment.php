<?php
/**
 * API para procesar pagos con MercadoPago
 * Endpoint: /api/mercadopago/process_payment.php
 * 
 * Procesa pagos con tarjeta usando la API de MercadoPago
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../config/env.php';

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);

error_log('MercadoPago process_payment.php - Received input: ' . json_encode($input));

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Credenciales de MercadoPago
$access_token = env('MP_ACCESS_TOKEN');

if (empty($access_token)) {
    http_response_code(500);
    echo json_encode(['error' => 'Credenciales de MercadoPago no configuradas']);
    exit;
}

try {
    // El token debe venir del frontend (tokenización con SDK de JavaScript)
    if (empty($input['token'])) {
        throw new Exception('Se requiere token de tarjeta');
    }

    $token = $input['token'];
    error_log('Token recibido del frontend: ' . $token);

    // Crear el pago (MercadoPago detecta el método de pago automáticamente del token)
    $payment_data = [
        'transaction_amount' => floatval($input['transaction_amount']),
        'token' => $token,
        'description' => $input['description'] ?? 'Compra en QuickBite',
        'installments' => intval($input['installments'] ?? 1),
        'payer' => [
            'email' => $input['cardholder_email'] ?? $input['payer']['email'] ?? 'cliente@quickbite.com.mx'
        ]
    ];
    
    error_log('Payment Data: ' . json_encode($payment_data));
    
    // Hacer request a MercadoPago
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payment_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'X-Idempotency-Key: qb_' . uniqid() . '_' . time()
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('Error de conexión: ' . $curl_error);
    }
    
    $result = json_decode($response, true);
    
    error_log('MercadoPago Payment Response [' . $http_code . ']: ' . $response);
    
    // Verificar respuesta
    if ($http_code >= 200 && $http_code < 300) {
        $status = $result['status'] ?? 'unknown';
        $status_detail = $result['status_detail'] ?? '';
        
        if ($status === 'approved') {
            echo json_encode([
                'success' => true,
                'payment_id' => $result['id'],
                'status' => $status,
                'status_detail' => $status_detail,
                'message' => '¡Pago aprobado exitosamente!'
            ]);
        } elseif ($status === 'pending' || $status === 'in_process') {
            echo json_encode([
                'success' => true,
                'payment_id' => $result['id'],
                'status' => $status,
                'status_detail' => $status_detail,
                'message' => 'Pago pendiente de confirmación'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'payment_id' => $result['id'] ?? null,
                'status' => $status,
                'status_detail' => $status_detail,
                'error' => getErrorMessage($status_detail)
            ]);
        }
    } else {
        $error_message = $result['message'] ?? 'Error desconocido de MercadoPago';
        if (!empty($result['cause'])) {
            foreach ($result['cause'] as $cause) {
                $error_message .= ' - ' . ($cause['description'] ?? $cause['code'] ?? '');
            }
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $error_message
        ]);
    }
    
} catch (Exception $e) {
    error_log('MercadoPago Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtener mensaje de error amigable
 */
function getErrorMessage($status_detail) {
    $messages = [
        'cc_rejected_bad_filled_card_number' => 'El número de tarjeta es incorrecto',
        'cc_rejected_bad_filled_date' => 'La fecha de vencimiento es incorrecta',
        'cc_rejected_bad_filled_other' => 'Algún dato de la tarjeta es incorrecto',
        'cc_rejected_bad_filled_security_code' => 'El código de seguridad es incorrecto',
        'cc_rejected_blacklist' => 'No pudimos procesar tu pago con esta tarjeta',
        'cc_rejected_call_for_authorize' => 'Debes autorizar el pago llamando a tu banco',
        'cc_rejected_card_disabled' => 'Tu tarjeta está deshabilitada. Llama a tu banco',
        'cc_rejected_card_error' => 'No pudimos procesar tu pago. Intenta con otra tarjeta',
        'cc_rejected_duplicated_payment' => 'Ya realizaste un pago similar. Espera unos minutos',
        'cc_rejected_high_risk' => 'Tu pago fue rechazado por seguridad',
        'cc_rejected_insufficient_amount' => 'Tu tarjeta no tiene fondos suficientes',
        'cc_rejected_invalid_installments' => 'Tu tarjeta no permite esta cantidad de cuotas',
        'cc_rejected_max_attempts' => 'Alcanzaste el límite de intentos. Intenta más tarde',
        'cc_rejected_other_reason' => 'Tu pago fue rechazado. Intenta con otra tarjeta',
        'pending_contingency' => 'Estamos procesando tu pago',
        'pending_review_manual' => 'Estamos revisando tu pago'
    ];
    
    return $messages[$status_detail] ?? 'Error al procesar el pago. Intenta con otra tarjeta.';
}
