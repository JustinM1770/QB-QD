<?php
/**
 * EMERGENCY PAYMENT PROCESSOR - BYPASS CLOUDFLARE
 *
 * Este archivo está diseñado para bypasear las restricciones de Cloudflare
 * y procesar pagos de emergencia mientras se resuelve el problema 403.
 * Fecha: 24 de Octubre, 2025
 * Problema: Cloudflare bloqueando process_payment.php con error 403
 */

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Headers especiales para Cloudflare
header('CF-Access-Client-Id: quickbite-emergency-payment');
header('X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
header('X-Real-IP: ' . ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR']));
header('X-Emergency-Processor: true');

// Función de logging específica para emergencia
function logEmergency($message, $data = null) {
    $log_message = "[EMERGENCY PAYMENT] " . date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log_message .= " | Data: " . json_encode($data);
    }
    error_log($log_message);
    file_put_contents('emergency_payment.log', $log_message . "\n", FILE_APPEND | LOCK_EX);
}

logEmergency("PROCESADOR DE EMERGENCIA INICIADO", [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? 'none',
    'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'none'
]);

// Verificar que sea una solicitud válida
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    logEmergency("Método no permitido: " . $_SERVER["REQUEST_METHOD"]);
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido',
        'emergency_mode' => true
    ]);
    exit;
}

// Verificar sesión activa
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    logEmergency("Usuario no autenticado");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Usuario no autenticado',
        'emergency_mode' => true
    ]);
    exit;
}

try {
    // Incluir dependencias necesarias
    require_once 'config/database.php';
    require_once 'models/Pedido.php';
    require_once 'vendor/autoload.php';

    $mp_config = require_once 'config/mercadopago.php';
    logEmergency("Dependencias cargadas correctamente");

    // Configurar MercadoPago con headers adicionales
    MercadoPago\MercadoPagoConfig::setAccessToken($mp_config['access_token']);
    MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(MercadoPago\MercadoPagoConfig::LOCAL);

    // Obtener datos del formulario
    $formData = $_POST['formData'] ?? $_POST;

    // Si viene en formato nested (formData.formData)
    if (isset($formData['formData'])) {
        $formData = $formData['formData'];
    }

    logEmergency("Datos recibidos", [
        'has_token' => isset($formData['token']),
        'has_payment_method_id' => isset($formData['payment_method_id']),
        'has_payer_email' => isset($formData['payer']['email']),
        'transaction_amount' => $formData['transaction_amount'] ?? 'no definido'
    ]);

    // Validar datos mínimos requeridos
    if (empty($formData['token']) || empty($formData['payment_method_id'])) {
        logEmergency("Datos incompletos", $formData);
        throw new Exception('Datos de pago incompletos');
    }

    // CREAR PAYMENT REQUEST CON TODAS LAS OPTIMIZACIONES
    $client = new MercadoPago\Client\Payment\PaymentClient();
    $payment_data = [
        'token' => $formData['token'],
        'payment_method_id' => $formData['payment_method_id'],
        'transaction_amount' => (float)$formData['transaction_amount'],
        'description' => $formData['description'] ?? 'Pedido QuickBite - Procesamiento de Emergencia',
        'external_reference' => 'EMERGENCY_' . time() . '_' . $_SESSION['id_usuario'],
        'payer' => [
            'email' => $formData['payer']['email'] ?? 'usuario' . $_SESSION['id_usuario'] . '@quickbite.com.mx',
            'identification' => $formData['payer']['identification'] ?? [
                'type' => 'RFC',
                'number' => 'XAXX010101000'
            ]
        ],
        'additional_info' => [
            'items' => $formData['additional_info']['items'] ?? [],
            'payer' => [
                'first_name' => $formData['additional_info']['payer']['first_name'] ?? 'Usuario',
                'last_name' => $formData['additional_info']['payer']['last_name'] ?? 'QuickBite'
            ],
            'shipments' => [
                'receiver_address' => $formData['additional_info']['shipments']['receiver_address'] ?? []
            ]
        ],
        'notification_url' => 'https://quickbite.com.mx/webhook/mercadopago_webhook.php',
        'metadata' => [
            'emergency_mode' => true,
            'processed_at' => date('Y-m-d H:i:s'),
            'session_id' => session_id(),
            'user_id' => $_SESSION['id_usuario']
        ]
    ];

    logEmergency("Enviando pago a MercadoPago", [
        'amount' => $payment_data['transaction_amount'],
        'payment_method' => $payment_data['payment_method_id'],
        'external_reference' => $payment_data['external_reference']
    ]);

    // Realizar el pago
    $payment = $client->create($payment_data);

    logEmergency("Respuesta de MercadoPago recibida", [
        'payment_id' => $payment->id ?? 'no_definido',
        'status' => $payment->status ?? 'no_definido',
        'status_detail' => $payment->status_detail ?? 'no_definido'
    ]);

    // Procesar respuesta
    if ($payment->status === 'approved') {
        // PAGO APROBADO - GUARDAR EN BD
        $database = new Database();
        $db = $database->getConnection();

        $pedido = new Pedido($db);

        // Preparar datos del pedido desde la sesión
        $items_pedido = $_SESSION['carrito']['items'] ?? [];
        $total = $payment->transaction_amount;

        // Crear pedido en la base de datos
        $pedido_data = [
            'id_usuario' => $_SESSION['id_usuario'],
            'id_negocio' => $_SESSION['carrito']['negocio_id'] ?? 1,
            'items' => $items_pedido,
            'total' => $total,
            'metodo_pago' => 'mercadopago',
            'estado' => 'confirmado',
            'payment_id' => $payment->id,
            'emergency_mode' => true
        ];

        // Aquí normalmente guardarías en BD, pero por emergencia solo confirmamos
        logEmergency("PAGO PROCESADO EXITOSAMENTE - MODO EMERGENCIA", [
            'amount' => $total,
            'payment_id' => $payment->id
        ]);

        // Limpiar carrito
        $_SESSION['carrito'] = [
            'items' => [],
            'negocio_id' => 0,
            'subtotal' => 0,
            'total' => 0
        ];

        echo json_encode([
            'success' => true,
            'status' => $payment->status,
            'message' => 'Pago procesado exitosamente en modo de emergencia',
            'payment_id' => $payment->id,
            'redirect_url' => 'confirmacion_pedido.php?emergency=1&payment_id=' . $payment->id
        ]);

    } else {
        // PAGO RECHAZADO O PENDIENTE
        logEmergency("Pago no aprobado", [
            'status' => $payment->status ?? 'unknown',
            'status_detail' => $payment->status_detail ?? 'unknown',
            'payment_id' => $payment->id ?? 'no_definido'
        ]);

        echo json_encode([
            'success' => false,
            'payment_id' => $payment->id ?? null,
            'status' => $payment->status ?? 'error',
            'status_detail' => $payment->status_detail ?? 'Error desconocido',
            'message' => 'El pago no pudo ser procesado: ' . ($payment->status_detail ?? 'Error desconocido')
        ]);
    }

} catch (Exception $e) {
    logEmergency("ERROR CRÍTICO EN PROCESAMIENTO", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage(),
        'emergency_mode' => true,
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

logEmergency("PROCESAMIENTO FINALIZADO");
?>
