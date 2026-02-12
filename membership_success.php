<?php
// membership_success.php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] === false) {
    header("Location: login.php");
    exit();
}

// Incluir configuración y modelos
require_once 'config/database.php';
require_once 'config/env.php';
require_once 'models/Membership.php';
require_once 'models/Usuario.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';
$session_id = $_GET['session_id'] ?? null;
$payment_intent = $_GET['payment_intent'] ?? null;
$plan = $_GET['plan'] ?? 'monthly';

// Verificar si viene de MercadoPago
$mp_payment_id = $_GET['payment_id'] ?? null;
$mp_status = $_GET['status'] ?? null;
$mp_external_reference = $_GET['external_reference'] ?? null;

if ($session_id) {
    // Procesar éxito de Stripe
    if (file_exists('vendor/autoload.php')) {
        try {
            require_once 'vendor/autoload.php';
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
            
            // Obtener la sesión de Stripe
            $session = \Stripe\Checkout\Session::retrieve($session_id);
            
            if ($session->payment_status == 'paid') {
                // El pago fue exitoso, activar la membresía
                $membership = new Membership($db);
                $user_id = $_SESSION['id_usuario'];
                $plan = $session->metadata['plan'] ?? 'monthly';
                
                if (method_exists($membership, 'subscribe') && $membership->subscribe($user_id, $plan)) {
                    $success_message = "¡Felicidades! Tu suscripción a QuickBite Premium ha sido activada exitosamente con Stripe.";
                } else {
                    $success_message = "El pago con Stripe fue procesado exitosamente. Tu membresía está siendo activada.";
                }
            } else {
                $error_message = "El pago con Stripe no pudo ser completado. Por favor intenta nuevamente.";
            }
        } catch (Exception $e) {
            $error_message = "Error al verificar el pago con Stripe: " . $e->getMessage();
            error_log("Error verificando sesión de Stripe: " . $e->getMessage());
        }
    } else {
        // Modo desarrollo sin Stripe
        $membership = new Membership($db);
        $user_id = $_SESSION['id_usuario'];
        $plan = 'monthly';
        
        if (method_exists($membership, 'subscribe') && $membership->subscribe($user_id, $plan)) {
            $success_message = "¡Membresía activada exitosamente! (Modo desarrollo)";
        } else {
            $success_message = "¡Suscripción simulada exitosa! (Modo desarrollo)";
        }
    }
} elseif ($mp_payment_id && $mp_status === 'approved') {
    // Procesar éxito de MercadoPago
    if (file_exists('vendor/autoload.php') && file_exists('config/mercadopago.php')) {
        try {
            require_once 'vendor/autoload.php';
            $mp_config = require_once 'config/mercadopago.php';
            
            // Configurar MercadoPago
            MercadoPago\MercadoPagoConfig::setAccessToken($mp_config['access_token']);
            
            // Verificar el pago
            $paymentClient = new MercadoPago\Client\Payment\PaymentClient();
            $payment = $paymentClient->get($mp_payment_id);
            
            if ($payment->status === 'approved') {
                // El pago fue exitoso, activar la membresía
                $membership = new Membership($db);
                $user_id = $_SESSION['id_usuario'];
                
                // Extraer el plan del external_reference
                if ($mp_external_reference && strpos($mp_external_reference, 'membership_') === 0) {
                    $parts = explode('_', $mp_external_reference);
                    $plan = $parts[2] ?? 'monthly';
                }
                
                if (method_exists($membership, 'subscribe') && $membership->subscribe($user_id, $plan)) {
                    $success_message = "¡Felicidades! Tu suscripción a QuickBite Premium ha sido activada exitosamente con MercadoPago.";
                } else {
                    $success_message = "El pago con MercadoPago fue procesado exitosamente. Tu membresía está siendo activada.";
                }
                
                error_log("Membresía activada exitosamente - Usuario: {$user_id}, Plan: {$plan}, Payment ID: {$mp_payment_id}");
            } else {
                $error_message = "El pago con MercadoPago no fue aprobado. Estado: " . $payment->status;
            }
        } catch (Exception $e) {
            $error_message = "Error al verificar el pago con MercadoPago: " . $e->getMessage();
            error_log("Error verificando pago MercadoPago: " . $e->getMessage());
        }
    } else {
        $error_message = "MercadoPago no está configurado correctamente.";
    }
} elseif ($_GET['membership'] === 'true') {
    // Redirigido desde MercadoPago pero sin confirmar pago aún
    $success_message = "Hemos recibido tu pago. Estamos verificando la transacción y activaremos tu membresía en unos minutos.";
} else {
    $error_message = "No se encontró información del pago. Por favor contacta a soporte si realizaste un pago.";
}

// Obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION['id_usuario'];
$usuario->obtenerPorId();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $success_message ? 'Suscripción Exitosa' : 'Error en Suscripción'; ?> - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #4285F4;
            --primary-dark: #0052CC;
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
            --light: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-600: #475569;
            --gray-900: #0F172A;
            --gradient: linear-gradient(135deg, #0165FF 0%, #4285F4 100%);
            --success-gradient: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            --error-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-900);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .result-card {
            background: var(--light);
            border-radius: var(--border-radius-xl);
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: var(--shadow-xl);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .result-card.success {
            border-color: var(--success);
        }

        .result-card.error {
            border-color: var(--error);
        }

        .result-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient);
        }

        .result-card.success::before {
            background: var(--success-gradient);
        }

        .result-card.error::before {
            background: var(--error-gradient);
        }

        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
            font-size: 2.5rem;
            color: var(--light);
            animation: bounce 1s ease-in-out;
        }

        .result-icon.success {
            background: var(--success-gradient);
        }

        .result-icon.error {
            background: var(--error-gradient);
        }

        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0, -15px, 0);
            }
            70% {
                transform: translate3d(0, -7px, 0);
            }
            90% {
                transform: translate3d(0, -2px, 0);
            }
        }

        .result-title {
            font-family: 'DM Sans', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }

        .result-message {
            font-size: 1.125rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .benefits-preview {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .benefits-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gray-900);
            text-align: center;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .benefit-item:last-child {
            margin-bottom: 0;
        }

        .benefit-item i {
            color: var(--success);
            margin-right: 0.75rem;
            width: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn-primary {
            background: var(--gradient);
            color: var(--light);
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            color: var(--light);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 50px;
            padding: 1rem 2rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-size: 1rem;
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--light);
            transform: translateY(-2px);
        }

        .contact-info {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            border-left: 4px solid var(--primary);
        }

        .contact-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .contact-info p {
            font-size: 0.95rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .contact-info a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 576px) {
            body {
                padding: 1rem 0.5rem;
            }

            .result-card {
                padding: 2rem 1.5rem;
            }

            .result-title {
                font-size: 1.5rem;
            }

            .result-message {
                font-size: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
                max-width: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success_message): ?>
            <!-- Éxito -->
            <div class="result-card success">
                <div class="result-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="result-title">¡Suscripción Exitosa!</h1>
                <p class="result-message"><?php echo htmlspecialchars($success_message); ?></p>
                
                <div class="benefits-preview">
                    <h3 class="benefits-title">Ahora puedes disfrutar de:</h3>
                    <div class="benefit-item">
                        <i class="fas fa-percentage"></i>
                        <span>10% de descuento en todas tus compras</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Envío gratis en pedidos mayores a $300</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-star"></i>
                        <span>Acceso prioritario a nuevos restaurantes</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-clock"></i>
                        <span>Entrega más rápida en horarios pico</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-gift"></i>
                        <span>Ofertas exclusivas para miembros</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-headset"></i>
                        <span>Soporte al cliente prioritario</span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="index.php" class="btn-primary">
                        <i class="fas fa-home"></i>
                        Ir al inicio
                    </a>
                    <a href="perfil.php" class="btn-secondary">
                        <i class="fas fa-user"></i>
                        Ver mi perfil
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Error -->
            <div class="result-card error">
                <div class="result-icon error">
                    <i class="fas fa-times"></i>
                </div>
                <h1 class="result-title">Error en la Suscripción</h1>
                <p class="result-message"><?php echo htmlspecialchars($error_message); ?></p>
                
                <div class="action-buttons">
                    <a href="membership_subscribe.php" class="btn-primary">
                        <i class="fas fa-redo"></i>
                        Intentar nuevamente
                    </a>
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-home"></i>
                        Ir al inicio
                    </a>
                </div>
                
                <div class="contact-info">
                    <h4><i class="fas fa-headset"></i> ¿Necesitas ayuda?</h4>
                    <p>Si el problema persiste, no dudes en contactarnos:</p>
                    <p><strong>Email:</strong> <a href="mailto:soporte@quickbite.com">soporte@quickbite.com</a></p>
                    <p><strong>WhatsApp:</strong> <a href="https://wa.me/1234567890">+52 123 456 7890</a></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>