<?php
/**
 * admin/wallet_onboarding_complete.php
 * Maneja el callback cuando el usuario completa el onboarding de Stripe
 */

session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
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
        
        // Actualizar onboarding como completado
        $query = "UPDATE wallets 
                 SET onboarding_completado = TRUE 
                 WHERE stripe_account_id = :account_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':account_id', $account_id);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "✅ ¡Perfil bancario completado exitosamente! Ya puedes recibir y retirar pagos.";
            error_log("Onboarding completado para cuenta Stripe: $account_id");
        } else {
            $_SESSION['error'] = "Error actualizando el estado de onboarding.";
        }
    } catch (Exception $e) {
        error_log("Error completando onboarding: " . $e->getMessage());
        $_SESSION['error'] = "Ocurrió un error al completar la configuración. Por favor contacta a soporte.";
    }
} else {
    $_SESSION['error'] = "No se recibió información de la cuenta de Stripe.";
}

header("Location: wallet_negocio.php");
exit;
?>
