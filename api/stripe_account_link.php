<?php
require_once '../config/database.php';
require_once '../config/payment.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) ||
    $_SESSION["loggedin"] !== true ||
    !isset($_SESSION["tipo_usuario"]) ||
    $_SESSION["tipo_usuario"] !== "negocio" ||
    !isset($_SESSION["id_negocio"])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del wallet
    $stmt = $conn->prepare("SELECT * FROM wallets WHERE id_usuario = :business_id AND tipo_usuario = 'business'");
    $stmt->bindParam(':business_id', $_SESSION["id_negocio"], PDO::PARAM_INT);
    $stmt->execute();
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new Exception('Wallet no encontrado');
    }

    // Si ya tiene una cuenta Stripe conectada, actualizarla
    if (!empty($wallet['stripe_account_id'])) {
        $account_link = \Stripe\AccountLink::create([
            'account' => $wallet['stripe_account_id'],
            'refresh_url' => 'https://quickbite.com.mx/admin/wallet_negocio.php?refresh=true',
            'return_url' => 'https://quickbite.com.mx/admin/wallet_negocio.php?setup=complete',
            'type' => 'account_onboarding',
        ]);
    } else {
        // Crear una nueva cuenta conectada
        $account = \Stripe\Account::create([
            'type' => 'express',
            'country' => 'MX',
            'email' => $wallet['email_contacto'] ?? '',
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'business_type' => 'individual',
            'business_profile' => [
                'mcc' => '5812', // Restaurants
                'url' => 'https://quickbite.com.mx',
            ],
            'metadata' => [
                'business_id' => $_SESSION["id_negocio"],
                'wallet_id' => $wallet['id_wallet']
            ]
        ]);

        // Actualizar el wallet con el ID de la cuenta Stripe
        $stmt = $conn->prepare("UPDATE wallets SET stripe_account_id = :stripe_id WHERE id_wallet = :wallet_id");
        $stmt->bindParam(':stripe_id', $account->id);
        $stmt->bindParam(':wallet_id', $wallet['id_wallet']);
        $stmt->execute();

        // Crear el link de onboarding
        $account_link = \Stripe\AccountLink::create([
            'account' => $account->id,
            'refresh_url' => 'https://quickbite.com.mx/admin/wallet_negocio.php?refresh=true',
            'return_url' => 'https://quickbite.com.mx/admin/wallet_negocio.php?setup=complete',
            'type' => 'account_onboarding',
        ]);
    }

    echo json_encode(['url' => $account_link->url]);

} catch (Exception $e) {
    error_log("Error en stripe_account_link.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al generar el link de configuración']);
}
?>
