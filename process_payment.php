<?php
/**
 * Procesador de pagos MercadoPago - QuickBite
 *
 * Este archivo maneja el procesamiento de pagos con tarjeta mediante MercadoPago SDK
 */

// Configuración de errores - solo logging, no display
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/mp_errors.log');

// Iniciar sesión
session_start();

// Headers para API JSON con soporte CORS (Cloudflare)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Manejar preflight OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Función de logging mejorada para debugging
 */
function logMP($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message";
    if ($data !== null) {
        $log .= " | " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n" . str_repeat('-', 80) . "\n";
    error_log($log, 3, __DIR__ . '/logs/mp_errors.log');
    return $log;
}

// Log inicio del proceso
logMP("🔵 INICIO DE PROCESO DE PAGO", [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? 'no_cloudflare',
    'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
]);

try {
    // 1. Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Método no permitido: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'),
            'allowed_methods' => ['POST']
        ]);
        exit;
    }

    logMP("✅ Método POST verificado");

    // 2. Cargar dependencias
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'MercadoPago SDK no instalado',
            'message' => 'Por favor contacta al administrador del sistema'
        ]);
        exit;
    }
    require_once __DIR__ . '/vendor/autoload.php';
    logMP("✅ Autoload cargado");

    // 3. Cargar configuración
    if (!file_exists(__DIR__ . '/config/mercadopago.php')) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Configuración de MercadoPago no encontrada',
            'message' => 'Por favor contacta al administrador del sistema'
        ]);
        exit;
    }
    $mp_config = require __DIR__ . '/config/mercadopago.php';
    logMP("✅ Config cargada", [
        'public_key_prefix' => substr($mp_config['public_key'] ?? '', 0, 15),
        'access_token_prefix' => substr($mp_config['access_token'] ?? '', 0, 15)
    ]);

    // 4. Configurar SDK MercadoPago
    MercadoPago\MercadoPagoConfig::setAccessToken($mp_config['access_token']);
    MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(MercadoPago\MercadoPagoConfig::LOCAL);
    logMP("✅ MercadoPagoConfig configurado");

    // 5. Detectar método de fallback o JSON normal
    $is_fallback = isset($_POST['fallback_method']) && $_POST['fallback_method'] === '1';
    $data = [];

    if ($is_fallback) {
        logMP("🔄 Procesando mediante método de fallback");

        // En modo fallback, los datos vienen como POST normales
        foreach ($_POST as $key => $value) {
            if ($key !== 'fallback_method') {
                // Intentar decodificar JSON si es necesario
                if (is_string($value) && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
                    $decoded = json_decode($value, true);
                    $data[$key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
                } else {
                    $data[$key] = $value;
                }
            }
        }
        logMP("📥 Datos fallback procesados", ['keys' => array_keys($data)]);
    } else {
        // Leer input JSON normal
        $input = file_get_contents('php://input');
        logMP("📥 Input recibido", ['length' => strlen($input), 'preview' => substr($input, 0, 200)]);

        if (empty($input)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se recibieron datos en el POST',
                'message' => 'Por favor envía los datos del pago'
            ]);
            exit;
        }

        // Parsear JSON
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Error parseando JSON: ' . json_last_error_msg(),
                'message' => 'Formato de datos inválido'
            ]);
            exit;
        }
    }

    logMP("✅ JSON parseado", [
        'keys' => array_keys($data),
        'amount' => $data['transaction_amount'] ?? 'N/A',
        'payment_method' => $data['payment_method_id'] ?? 'N/A',
        'has_token' => isset($data['token']) ? 'Sí (' . strlen($data['token']) . ' chars)' : 'No',
        'installments' => $data['installments'] ?? 'N/A'
    ]);

    // 6. Validar datos requeridos
    $required = ['token', 'transaction_amount', 'installments', 'payment_method_id'];
    $missing_fields = [];
    $invalid_fields = [];

    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $missing_fields[] = $field;
        } else {
            // Validaciones específicas por campo
            switch ($field) {
                case 'token':
                    if (strlen(trim($data[$field])) < 10) {
                        $invalid_fields[] = $field . ' (demasiado corto: ' . strlen(trim($data[$field])) . ' chars)';
                    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', trim($data[$field]))) {
                        $invalid_fields[] = $field . ' (formato inválido)';
                    }
                    break;
                case 'transaction_amount':
                    if (!is_numeric($data[$field]) || (float)$data[$field] <= 0) {
                        $invalid_fields[] = $field . ' (debe ser mayor a 0)';
                    }
                    break;
                case 'installments':
                    if (!is_numeric($data[$field]) || (int)$data[$field] < 1) {
                        $invalid_fields[] = $field . ' (debe ser al menos 1)';
                    }
                    break;
                case 'payment_method_id':
                    if (strlen(trim($data[$field])) < 2) {
                        $invalid_fields[] = $field . ' (ID inválido)';
                    }
                    break;
            }
        }
    }

    if (!empty($missing_fields) || !empty($invalid_fields)) {
        $error_details = [];
        if (!empty($missing_fields)) {
            $error_details[] = 'Campos faltantes: ' . implode(', ', $missing_fields);
        }
        if (!empty($invalid_fields)) {
            $error_details[] = 'Campos inválidos: ' . implode(', ', $invalid_fields);
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Datos del formulario incompletos o inválidos',
            'details' => $error_details,
            'message' => 'Por favor verifica los datos de tu tarjeta e intenta nuevamente'
        ]);
        exit;
    }

    logMP("✅ Validación de campos completada");

    // 7. Crear cliente de pago (Nueva SDK)
    $paymentClient = new MercadoPago\Client\Payment\PaymentClient();

    // 8. Preparar datos del pago según recomendaciones de MercadoPago
    $payment_data = [
        'transaction_amount' => (float)$data['transaction_amount'],
        'token' => $data['token'],
        'description' => 'Pedido de comida - QuickBite México - Productos alimenticios',
        'installments' => (int)$data['installments'],
        'payment_method_id' => $data['payment_method_id'],
        'capture' => true,
        'external_reference' => 'QB_' . time() . '_' . ($_SESSION['id_usuario'] ?? 0),
        'notification_url' => 'https://quickbite.com.mx/webhook/mercadopago_webhook.php',
        'additional_info' => [
            'items' => isset($data['items']) && is_array($data['items']) ? array_map(function($item) {
                return [
                    'id' => $item['id'] ?? 'item_' . uniqid(),
                    'title' => $item['title'] ?? 'Producto QuickBite',
                    'description' => $item['description'] ?? 'Producto de QuickBite',
                    'category_id' => $item['category_id'] ?? 'food',
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'currency_id' => 'MXN'
                ];
            }, $data['items']) : [],
            'payer' => [
                'first_name' => $data['payer_name'] ?? 'Cliente',
                'last_name' => 'QuickBite',
                'phone' => [
                    'area_code' => '52',
                    'number' => '5555555555'
                ],
                'address' => [
                    'zip_code' => '01000',
                    'street_name' => 'Centro',
                    'street_number' => '1'
                ]
            ],
            'shipments' => [
                'receiver_address' => [
                    'state_name' => 'CDMX',
                    'city_name' => 'Ciudad de México',
                    'street_name' => 'Delivery QuickBite'
                ]
            ]
        ]
    ];

    // Issuer ID (opcional para algunos métodos)
    if (!empty($data['issuer_id']) && $data['issuer_id'] !== 'null' && $data['issuer_id'] !== '0') {
        $payment_data['issuer_id'] = (int)$data['issuer_id'];
    }

    // Configurar payer
    if (isset($data['payer'])) {
        $payment_data['payer'] = [
            'email' => $data['payer']['email'] ?? 'usuario@quickbite.com.mx'
        ];
        if (isset($data['payer']['identification']) && is_array($data['payer']['identification'])) {
            $identification = $data['payer']['identification'];
            if (!empty($identification['type']) && !empty($identification['number'])) {
                $payment_data['payer']['identification'] = [
                    'type' => $identification['type'],
                    'number' => $identification['number']
                ];
            }
        }
    } else {
        $payment_data['payer'] = [
            'email' => 'usuario@quickbite.com.mx'
        ];
    }

    // Metadata
    $payment_data['metadata'] = [
        'user_id' => $_SESSION['id_usuario'] ?? 0,
        'platform' => 'QuickBite'
    ];

    logMP("✅ Datos del pago preparados", [
        'amount' => $payment_data['transaction_amount'],
        'method' => $payment_data['payment_method_id'],
        'installments' => $payment_data['installments'],
        'payer_email' => $payment_data['payer']['email'] ?? 'N/A',
        'has_issuer' => isset($payment_data['issuer_id']) ? 'Sí' : 'No',
        'has_identification' => isset($payment_data['payer']['identification']) ? 'Sí' : 'No'
    ]);

    // 9. PROCESAR PAGO
    logMP("💳 ENVIANDO PAGO A MERCADOPAGO...");
    $result = $paymentClient->create($payment_data);

    logMP("📥 RESPUESTA DE MERCADOPAGO", [
        'id' => $result->id ?? 'N/A',
        'status' => $result->status ?? 'N/A',
        'status_detail' => $result->status_detail ?? 'N/A',
        'payment_method_id' => $result->payment_method_id ?? 'N/A'
    ]);

    // 10. Preparar respuesta
    $response = [
        'success' => false,
        'id' => $result->id ?? null,
        'status' => $result->status ?? 'unknown',
        'status_detail' => $result->status_detail ?? '',
        'detail' => $result->status_detail ?? '',
        'payment_method' => $result->payment_method_id ?? null
    ];

    // 11. Evaluar estado con manejo mejorado
    if ($result->status === 'approved') {
        $response['success'] = true;
        $response['message'] = 'Pago aprobado exitosamente';
        logMP("✅✅✅ PAGO APROBADO", ['id' => $result->id]);

    } elseif ($result->status === 'pending') {
        $response['success'] = true; // Consideramos los pendientes como exitosos
        switch ($result->status_detail) {
            case 'pending_review_manual':
                $response['message'] = 'Pago en revisión. Tu pago está siendo verificado por MercadoPago y será procesado en las próximas horas.';
                $response['user_message'] = 'Tu pedido ha sido recibido y el pago está en proceso de verificación. Te notificaremos cuando sea aprobado.';
                logMP("📋 PAGO EN REVISIÓN MANUAL", ['id' => $result->id]);
                break;
            case 'pending_waiting_payment':
                $response['message'] = 'Esperando el pago del usuario';
                $response['user_message'] = 'Completa tu pago para confirmar el pedido.';
                logMP("⏳ ESPERANDO PAGO", ['id' => $result->id]);
                break;
            case 'pending_contingency':
                $response['message'] = 'Pago en proceso por contingencia bancaria';
                $response['user_message'] = 'Tu pago está siendo procesado. Te confirmaremos el estado en breve.';
                logMP("🏦 CONTINGENCIA BANCARIA", ['id' => $result->id]);
                break;
            default:
                $response['message'] = 'Pago pendiente de confirmación';
                $response['user_message'] = 'Tu pago está siendo procesado. Te confirmaremos el estado pronto.';
                logMP("⏳ PAGO PENDIENTE", ['id' => $result->id, 'detail' => $result->status_detail]);
                break;
        }

    } elseif ($result->status === 'rejected') {
        // Manejar diferentes tipos de rechazo
        switch ($result->status_detail) {
            case 'cc_rejected_insufficient_amount':
                $response['message'] = 'Fondos insuficientes en la tarjeta';
                $response['user_message'] = 'Tu tarjeta no tiene fondos suficientes. Intenta con otra tarjeta o verifica tu saldo.';
                break;
            case 'cc_rejected_bad_filled_card_number':
                $response['message'] = 'Número de tarjeta incorrecto';
                $response['user_message'] = 'Verifica que el número de tarjeta esté correcto e intenta nuevamente.';
                break;
            case 'cc_rejected_bad_filled_date':
                $response['message'] = 'Fecha de vencimiento incorrecta';
                $response['user_message'] = 'Verifica la fecha de vencimiento de tu tarjeta e intenta nuevamente.';
                break;
            case 'cc_rejected_bad_filled_security_code':
                $response['message'] = 'Código de seguridad incorrecto';
                $response['user_message'] = 'Verifica el código CVV de tu tarjeta e intenta nuevamente.';
                break;
            case 'cc_rejected_blacklist':
                $response['message'] = 'Tarjeta en lista negra';
                $response['user_message'] = 'Esta tarjeta no puede ser utilizada. Intenta con otra tarjeta.';
                break;
            case 'cc_rejected_high_risk':
                $response['message'] = 'Pago rechazado por seguridad';
                $response['user_message'] = 'El pago fue rechazado por políticas de seguridad. Intenta con otra tarjeta.';
                break;
            default:
                $response['message'] = 'Pago rechazado: ' . ($result->status_detail ?? 'motivo desconocido');
                $response['user_message'] = 'El pago no pudo ser procesado. Intenta con otra tarjeta o método de pago.';
                break;
        }
        logMP("❌ PAGO RECHAZADO", [
            'id' => $result->id,
            'detail' => $result->status_detail,
            'cause' => $result->status_detail
        ]);

    } else {
        $response['message'] = 'Estado desconocido: ' . $result->status;
        $response['user_message'] = 'Ocurrió un error inesperado. Por favor intenta nuevamente.';
        logMP("⚠️ ESTADO DESCONOCIDO", ['status' => $result->status]);
    }

    // 12. Enviar respuesta
    logMP("📤 ENVIANDO RESPUESTA AL FRONTEND", $response);

    // Manejo especial para fallback
    if ($is_fallback && isset($response['success']) && $response['success']) {
        logMP("🔄 Redirigiendo en modo fallback", ['payment_id' => $response['id']]);
        $_SESSION['payment_result'] = $response;
        $_SESSION['payment_fallback'] = true;
        $_POST['payment_id'] = $response['id'];
        $_POST['payment_method'] = 'mercadopago';
        header('Location: checkout.php?payment_processed=1');
        exit;
    }

    // Respuesta JSON normal
    echo json_encode($response);

} catch (MercadoPago\Exceptions\MPApiException $e) {
    $error = [
        'success' => false,
        'error' => 'MercadoPago API Error: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'type' => 'MPApiException',
        'details' => [
            'status_code' => $e->getCode(),
            'message' => $e->getMessage()
        ]
    ];
    logMP("❌❌❌ EXCEPCIÓN MERCADOPAGO API", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode($error);

} catch (Exception $e) {
    $error = [
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage(),
        'type' => 'Exception',
        'details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ];
    logMP("❌❌❌ EXCEPCIÓN GENERAL", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode($error);
}

logMP("🔵 FIN DEL PROCESO");
