<?php
session_start();
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $mp_config = require_once 'config/mercadopago.php';
    MercadoPago\SDK::setAccessToken($mp_config['access_token']);

    $payment = new MercadoPago\Payment();
    $payment->transaction_amount = (float)$input['transaction_amount'];
    $payment->description = $input['description'] ?? 'Pedido QuickBite';
    $payment->payment_method_id = $input['paymentMethodId'];
    $payment->installments = (int)($input['installments'] ?? 1);
    $payment->token = $input['token'];

    if (isset($input['issuerId'])) {
        $payment->issuer_id = (int)$input['issuerId'];
    }

    $payer = new MercadoPago\Payer();
    $payer->email = $input['payer']['email'] ?? $_SESSION['email'];

    if (isset($input['payer']['identification'])) {
        $identification = new MercadoPago\Identification();
        $identification->type = $input['payer']['identification']['type'];
        $identification->number = $input['payer']['identification']['number'];
        $payer->identification = $identification;
    }

    $payment->payer = $payer;
    $payment->save();

    error_log("✅ [MERCADOPAGO] Payment ID: " . $payment->id . " | Status: " . $payment->status);

    echo json_encode([
        'id' => $payment->id,
        'status' => $payment->status,
        'status_detail' => $payment->status_detail,
        'transaction_amount' => $payment->transaction_amount
    ]);

} catch (Exception $e) {
    error_log("❌ [PAYMENT ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?>
