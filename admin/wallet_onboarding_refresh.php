<?php
/**
 * admin/wallet_onboarding_refresh.php
 * Maneja el callback cuando el usuario cancela o necesita refrescar el onboarding
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/WalletStripe.php';

// Verificar sesión
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'negocio') {
    header("Location: ../login.php");
    exit;
}

// Obtener account_id de Stripe
$account_id = $_GET['account_id'] ?? '';

if ($account_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $wallet_service = new WalletStripe($db, STRIPE_SECRET_KEY);
        
        // Generar nueva URL de onboarding
        $nueva_url = $wallet_service->generarOnboardingUrl($account_id);
        
        $_SESSION['mensaje'] = "⚠️ Necesitas completar tu perfil bancario para poder retirar dinero.";
        $_SESSION['onboarding_url'] = $nueva_url;
        
        error_log("Onboarding refresh para cuenta: $account_id");
    } catch (Exception $e) {
        error_log("Error generando nueva URL de onboarding: " . $e->getMessage());
        $_SESSION['error'] = "Error al generar enlace de configuración.";
    }
} else {
    $_SESSION['mensaje'] = "⚠️ Debes completar tu perfil bancario para poder usar la cartera.";
}

header("Location: wallet_negocio.php");
exit;
?>
