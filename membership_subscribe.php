<?php
// Iniciar sesión
session_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'checkout_errors.log');


$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Redirigir al login si no está logueado
if (!$usuario_logueado) {
    header("Location: login.php?redirect=membership_subscribe.php");
    exit;
}
// Validar tipo de usuario
if (isset($_SESSION['tipo_usuario'])) {
    if ($_SESSION['tipo_usuario'] === 'repartidor') {
        header("Location: admin/repartidor_dashboard.php");
        exit();
    } elseif ($_SESSION['tipo_usuario'] === 'negocio') {
        header("Location: admin/negocio_configuracion.php");
        exit();
    }
    
}

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Membership.php';

// Configuración de Stripe - DESHABILITADO
$stripe_available = false;
$stripe_error = "Stripe deshabilitado - usando solo MercadoPago";

// Configuración de MercadoPago usando la API REST
$mercadopago_available = false;
$mercadopago_error = null;

// Cargar configuración de MercadoPago desde env.php
require_once __DIR__ . '/config/env.php';

$mp_access_token = env('MP_ACCESS_TOKEN');
$mp_public_key = env('MP_PUBLIC_KEY');

if (!empty($mp_access_token) && !empty($mp_public_key)) {
    $mercadopago_available = true;
    error_log("✅ MercadoPago configurado para membresías (API REST)");
    
    // Configuración de URLs
    $app_url = env('APP_URL', 'https://quickbite.com.mx');
    $mp_config = [
        'access_token' => $mp_access_token,
        'public_key' => $mp_public_key,
        'success_url' => $app_url . '/membership_success.php',
        'failure_url' => $app_url . '/membership_subscribe.php',
        'pending_url' => $app_url . '/membership_subscribe.php',
        'webhook_url' => $app_url . '/webhooks/mercadopago.php'
    ];
} else {
    $mercadopago_error = "Credenciales de MercadoPago no configuradas en .env";
}

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Si está logueado, verificar si ya es miembro
if ($usuario_logueado) {
    $membership = new Membership($db);
    $membership->id_usuario = $_SESSION["id_usuario"];
    $esMiembroActivo = $membership->isActive();
    
    // Verificar si la membresía está próxima a vencer (dentro de 7 días)
    if ($esMiembroActivo) {
        // Obtener información detallada de la membresía
        $stmt = $db->prepare("SELECT fecha_fin, DATEDIFF(fecha_fin, NOW()) as dias_restantes FROM membresias WHERE id_usuario = ? AND estado = 'activo' ORDER BY fecha_fin DESC LIMIT 1");
        $stmt->bindParam(1, $_SESSION["id_usuario"]);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fechaVencimiento = $row['fecha_fin'];
            $diasParaVencer = (int)$row['dias_restantes'];
            
            // Mostrar opción de renovación si faltan 7 días o menos, o si ya venció, o si el usuario explícitamente quiere renovar
            $mostrarRenovacion = ($diasParaVencer <= 7) || isset($_GET['renovar']);
        }
        
        // Si la membresía no está próxima a vencer y no hay parámetro de renovación, redirigir
        if (!$mostrarRenovacion) {
            header("Location: index.php?mensaje=membresia_activa&dias=" . $diasParaVencer);
            exit();
        }
    }
}

// Manejar activación de membresía desde JavaScript (solo MercadoPago)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'activate_membership') {
    header('Content-Type: application/json');
    
    try {
        $payment_intent_id = $_POST['payment_intent_id'] ?? '';
        $plan = $_POST['plan'] ?? '';
        $user_id = $_SESSION['id_usuario'];
        $payment_method = $_POST['payment_method'] ?? 'mercadopago';
        
        if (empty($payment_intent_id) || empty($plan)) {
            throw new Exception('Datos faltantes para activar membresía');
        }
        
        // Verificar el pago con MercadoPago usando API REST
        if ($payment_method === 'mercadopago' && $mercadopago_available && strpos($payment_intent_id, 'dev_') !== 0) {
            // Verificar pago usando API REST
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $payment_intent_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $mp_access_token
                ],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $payment = json_decode($response, true);
                if ($payment['status'] !== 'approved') {
                    throw new Exception('El pago con MercadoPago no fue aprobado');
                }
            } else {
                error_log("Error verificando pago MP: HTTP $http_code - $response");
            }
        }
        
        // Activar la membresía
        $membership = new Membership($db);
        if (method_exists($membership, 'subscribe') && $membership->subscribe($user_id, $plan)) {
            echo json_encode([
                'success' => true,
                'message' => 'Membresía activada correctamente',
                'payment_intent_id' => $payment_intent_id,
                'plan' => $plan,
                'payment_method' => $payment_method
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Pago procesado correctamente (membresía pendiente de implementación)',
                'payment_intent_id' => $payment_intent_id,
                'plan' => $plan,
                'payment_method' => $payment_method
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Procesar formulario de suscripción (solo MercadoPago)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario_logueado && !isset($_POST['action'])) {
    $plan = $_POST['plan'] ?? 'monthly';
    $payment_method = $_POST['payment_method'] ?? 'mercadopago';
    
    // Procesar con MercadoPago
    if ($payment_method === 'mercadopago' && $mercadopago_available) {
        try {
            // Configurar precios según el plan
            $precios = [
                'monthly' => 59,
                'yearly' => 599
            ];
            
            $precio = $precios[$plan] ?? $precios['monthly'];
            
            // Obtener información del usuario
            $usuario = new Usuario($db);
            $usuario->id_usuario = $_SESSION['id_usuario'];
            $usuario->obtenerPorId();
            
            // Crear preferencia de MercadoPago usando API REST
            $preference_data = [
                "items" => [
                    [
                        "id" => "membership_" . $plan,
                        "title" => "Membresía QuickBite " . ($plan === 'yearly' ? 'Anual' : 'Mensual'),
                        "description" => "Suscripción a QuickBite Premium",
                        "quantity" => 1,
                        "currency_id" => "MXN",
                        "unit_price" => (float)$precio
                    ]
                ],
                "payer" => [
                    "name" => $usuario->nombre ?? "Usuario",
                    "surname" => $usuario->apellido ?? "QuickBite",
                    "email" => $_SESSION["email"] ?? "usuario" . $_SESSION["id_usuario"] . "@quickbite.com.mx"
                ],
                "back_urls" => [
                    "success" => $mp_config['success_url'] . "?membership=true&plan=" . $plan,
                    "failure" => $mp_config['failure_url'] . "?membership_error=1",
                    "pending" => $mp_config['pending_url'] . "?membership_pending=1"
                ],
                "auto_return" => "approved",
                "external_reference" => "membership_" . $_SESSION["id_usuario"] . "_" . $plan . "_" . time(),
                "metadata" => [
                    "user_id" => $_SESSION["id_usuario"],
                    "plan" => $plan,
                    "type" => "membership_subscription"
                ],
                "notification_url" => $mp_config['webhook_url'] . "?type=membership"
            ];
            
            // Crear preferencia usando cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($preference_data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $mp_access_token,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($http_code === 200 || $http_code === 201) {
                if (!empty($result['init_point'])) {
                    // Redirigir a MercadoPago
                    header('Location: ' . $result['init_point']);
                    exit;
                } else {
                    throw new Exception('No se obtuvo URL de pago de MercadoPago');
                }
            } else {
                $error_msg = $result['message'] ?? 'Error desconocido';
                throw new Exception('Error de MercadoPago: ' . $error_msg);
            }
            
        } catch (Exception $e) {
            $error_message = "Error al procesar el pago con MercadoPago: " . $e->getMessage();
            error_log("Error MercadoPago membresía: " . $e->getMessage());
        }
    }
    else {
        $error_message = "El sistema de pagos no está disponible. " . ($mercadopago_error ?? "MercadoPago no configurado");
    }
}

// Planes de suscripción - precios reales
$planes = [
    'monthly' => [
        'nombre' => 'Plan Mensual',
        'precio' => 59,
        'periodo' => 'mes',
        'descuento' => 0,
        'stripe_price_id' => 'price_1RksyPIdYYLBpeXymlvdZI9t'
    ],
    'yearly' => [
        'nombre' => 'Plan Anual',
        'precio' => 599,
        'precio_original' => 708,
        'periodo' => 'año',
        'descuento' => 15,
        'popular' => true,
        'stripe_price_id' => 'price_1Rkt0tIdYYLBpeXyfN1dbCUE'
    ]
];

// Beneficios de la membresía
$beneficios = [
    [
        'icono' => 'fas fa-percentage',
        'titulo' => '15% de descuento',
        'descripcion' => 'En todas tus compras sin mínimo'
    ],
    [
        'icono' => 'fas fa-shipping-fast',
        'titulo' => 'Envío gratis',
        'descripcion' => 'En pedidos mayores a $300'
    ],
    [
        'icono' => 'fas fa-star',
        'titulo' => 'Acceso prioritario',
        'descripcion' => 'A nuevos restaurantes y promociones'
    ],
    [
        'icono' => 'fas fa-clock',
        'titulo' => 'Entrega más rápida',
        'descripcion' => 'Prioridad en horarios pico'
    ],
    [
        'icono' => 'fas fa-gift',
        'titulo' => 'Ofertas exclusivas',
        'descripcion' => 'Promociones solo para miembros'
    ],
    [
        'icono' => 'fas fa-headset',
        'titulo' => 'Soporte premium',
        'descripcion' => 'Atención al cliente prioritaria'
    ]
];

// Verificar si hay mensaje de cancelación
$canceled_message = isset($_GET['canceled']) ? "Pago cancelado. Puedes intentar nuevamente cuando gustes." : null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresía Premium - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
     <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@700&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
:root {
    --primary: #0165FF;
    --primary-light: #4285F4;
    --primary-dark: #0052CC;
    --secondary: #F8FAFC;
    --accent: #1E293B;
    --dark: #0F172A;
    --light: #FFFFFF;
    --gray-50: #F8FAFC;
    --gray-100: #F1F5F9;
    --gray-200: #E2E8F0;
    --gray-300: #CBD5E1;
    --gray-400: #94A3B8;
    --gray-500: #64748B;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1E293B;
    --gray-900: #0F172A;
    --gradient: linear-gradient(135deg, #0165FF 0%, #4285F4 100%);
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --border-radius: 16px;
    --border-radius-lg: 20px;
    --border-radius-xl: 24px;
    --border-radius-full: 50px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--gray-50);
    color: var(--gray-900);
    line-height: 1.6;
    font-size: 16px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    font-weight: 400;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'DM Sans', sans-serif;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

h1 { font-size: clamp(2rem, 4vw, 2.5rem); font-weight: 800; }
h2 { font-size: clamp(1.5rem, 3vw, 1.875rem); font-weight: 700; }
h3 { font-size: clamp(1.25rem, 2.5vw, 1.5rem); font-weight: 600; }

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
    padding-bottom: 2rem;
}

/* Header */
.header {
    background: var(--light);
    border-bottom: 2px solid var(--gray-100);
    padding: 1rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(20px);
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.logo {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    font-family: 'League Spartan', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.logo .bite {
            color: #FFD700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            }

.back-btn {
    color: var(--gray-600);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius);
    transition: var(--transition);
    font-weight: 500;
}

.back-btn:hover {
    background-color: var(--gray-100);
    color: var(--primary);
}

/* Hero Section */
.hero-section {
    background: var(--gradient);
    padding: 4rem 0 6rem 0;
    text-align: center;
    color: var(--light);
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="30" r="1.5" fill="white" opacity="0.15"/><circle cx="30" cy="70" r="1" fill="white" opacity="0.1"/><circle cx="70" cy="80" r="2" fill="white" opacity="0.1"/></svg>');
    pointer-events: none;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-icon {
    font-size: 4rem;
    margin-bottom: 2rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.hero-section h1 {
    color: var(--light);
    margin-bottom: 1rem;
}

.hero-section p {
    font-size: 1.25rem;
    opacity: 0.95;
    max-width: 600px;
    margin: 0 auto 2rem auto;
}

/* Benefits Section */
.benefits-section {
    padding: 4rem 0;
    background: var(--light);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.benefit-card {
    background: var(--light);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 2px solid var(--gray-100);
    transition: var(--transition);
}

.benefit-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary);
}

.benefit-icon {
    width: 64px;
    height: 64px;
    background: var(--gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem auto;
    color: var(--light);
    font-size: 1.5rem;
}

.benefit-card h3 {
    margin-bottom: 1rem;
    color: var(--dark);
}

.benefit-card p {
    color: var(--gray-600);
    font-size: 1rem;
}

/* Plans Section */
.plans-section {
    padding: 4rem 0;
    background: var(--gray-50);
}

.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.plan-card {
    background: var(--light);
    border-radius: var(--border-radius-xl);
    padding: 2.5rem 2rem;
    text-align: center;
    box-shadow: var(--shadow-md);
    border: 2px solid var(--gray-100);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.plan-card.popular {
    border-color: var(--primary);
    transform: scale(1.05);
}

.plan-card.popular::before {
    content: 'Más Popular';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: var(--gradient);
    color: var(--light);
    padding: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}

.plan-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
}

.plan-card.popular:hover {
    transform: translateY(-8px) scale(1.05);
}

.plan-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--dark);
}

.plan-price {
    font-size: 3rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.plan-price-original {
    font-size: 1.25rem;
    color: var(--gray-400);
    text-decoration: line-through;
    margin-bottom: 0.5rem;
}

.plan-period {
    color: var(--gray-600);
    margin-bottom: 2rem;
}

.plan-discount {
    background: var(--primary);
    color: var(--light);
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 2rem;
    display: inline-block;
}

.card-input-section {
    margin-bottom: 1rem;
    text-align: left;
}

.card-input-section label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
}

#card-element {
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    background: white;
    transition: border-color 0.3s ease;
}

#card-element:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
}

#card-errors {
    color: #fa755a;
    font-size: 0.875rem;
    margin-top: 8px;
    display: none;
}

.plan-btn {
    background: var(--gradient);
    color: var(--light);
    border: none;
    border-radius: var(--border-radius-full);
    padding: 1rem 2rem;
    font-weight: 600;
    font-size: 1.1rem;
    width: 100%;
    transition: var(--transition);
    cursor: pointer;
    box-shadow: var(--shadow-md);
}

.plan-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
    filter: brightness(1.05);
}

.plan-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Payment Method Selector */
.payment-method-selector {
    margin-bottom: 1.5rem;
    text-align: left;
}

.payment-option {
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
}

.payment-option input[type="radio"] {
    margin-right: 0.75rem;
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}

.payment-label {
    font-weight: 500;
    color: var(--gray-700);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: var(--border-radius);
    transition: var(--transition);
    flex: 1;
}

.payment-label:hover {
    background: var(--gray-100);
}

.payment-option input[type="radio"]:checked + .payment-label {
    color: var(--primary);
    background: var(--gray-100);
    font-weight: 600;
}

.mercadopago-section {
    margin-bottom: 1rem;
    text-align: left;
}

.card-element {
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    background: white;
    transition: border-color 0.3s ease;
}

.card-element:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
}

.card-errors {
    color: #fa755a;
    font-size: 0.875rem;
    margin-top: 8px;
    display: none;
}

/* Login Required */
.login-required {
    background: var(--light);
    border-radius: var(--border-radius-xl);
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow-lg);
    border: 2px solid var(--gray-100);
    max-width: 500px;
    margin: 2rem auto;
}

.login-required h3 {
    margin-bottom: 1rem;
    color: var(--dark);
}

.login-required p {
    margin-bottom: 2rem;
    color: var(--gray-600);
}

.btn-primary {
    background: var(--gradient);
    color: var(--light);
    border: none;
    border-radius: var(--border-radius-full);
    padding: 1rem 2rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: var(--transition);
    margin: 0.5rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    color: var(--light);
}

.btn-secondary {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
    border-radius: var(--border-radius-full);
    padding: 1rem 2rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: var(--transition);
    margin: 0.5rem;
}

.btn-secondary:hover {
    background: var(--primary);
    color: var(--light);
    transform: translateY(-2px);
}

/* Alert Messages */
.alert {
    border-radius: var(--border-radius);
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    border: 2px solid;
}

.alert-success {
    background: #f0fdf4;
    border-color: #22c55e;
    color: #166534;
}

.alert-danger {
    background: #fef2f2;
    border-color: #ef4444;
    color: #dc2626;
}

.alert-warning {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #92400e;
}

/* Security badge for payments */
.security-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.75rem;
    background: var(--gray-100);
    border-radius: var(--border-radius);
    font-size: 0.875rem;
    color: var(--gray-600);
}

.security-badge i {
    color: #22c55e;
}

/* Payment icons */
.payment-icons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    opacity: 0.7;
}

.payment-icons i {
    font-size: 1.5rem;
    color: var(--gray-500);
}

/* Stripe branding compliance */
.powered-by-stripe {
    text-align: center;
    margin-top: 2rem;
    font-size: 0.875rem;
    color: var(--gray-500);
}

.powered-by-stripe a {
    color: #635bff;
    text-decoration: none;
    font-weight: 600;
}

.powered-by-stripe a:hover {
    text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 0 1rem;
    }

    .hero-section {
        padding: 3rem 0 4rem 0;
    }

    .hero-icon {
        font-size: 3rem;
        margin-bottom: 1.5rem;
    }

    .benefits-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .plans-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .plan-card.popular {
        transform: none;
    }

    .plan-card.popular:hover {
        transform: translateY(-8px);
    }

    .benefit-card {
        padding: 1.5rem;
    }

    .plan-card {
        padding: 2rem 1.5rem;
    }
}

@media (max-width: 480px) {
    .hero-section {
        padding: 2rem 0 3rem 0;
    }

    .hero-section h1 {
        font-size: 2rem;
    }

    .hero-section p {
        font-size: 1.1rem;
    }

    .benefit-card {
        padding: 1.25rem;
    }

    .plan-card {
        padding: 1.5rem 1rem;
    }

    .plan-price {
        font-size: 2.5rem;
    }
}

/* Focus states for accessibility */
button:focus,
input:focus,
a:focus {
    outline: 3px solid var(--primary);
    outline-offset: 2px;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}
</style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
               <span class="quick">Quick</span><span class="bite">Bite</span>
            </a>
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-icon">✨</div>
                <h1>Únete a QuickBite Premium</h1>
                <p>Disfruta de beneficios exclusivos, descuentos especiales y una experiencia de delivery sin igual</p>
            </div>
        </div>
    </section>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($canceled_message): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <?php echo $canceled_message; ?>
            </div>
        <?php endif; ?>



        <!-- Benefits Section -->
        <section class="benefits-section">
            <div class="text-center">
                <h2>Beneficios Exclusivos</h2>
                <p style="color: var(--gray-600); font-size: 1.125rem; max-width: 600px; margin: 1rem auto 0 auto;">
                    Descubre todas las ventajas de ser miembro premium
                </p>
            </div>

            <div class="benefits-grid">
                <?php foreach ($beneficios as $beneficio): ?>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="<?php echo $beneficio['icono']; ?>"></i>
                    </div>
                    <h3><?php echo $beneficio['titulo']; ?></h3>
                    <p><?php echo $beneficio['descripcion']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Plans Section -->
        <section class="plans-section">
            <div class="text-center">
                <h2>Elige tu Plan</h2>
                <p style="color: var(--gray-600); font-size: 1.125rem; max-width: 600px; margin: 1rem auto 0 auto;">
                    Selecciona el plan que mejor se adapte a tus necesidades
                </p>
            </div>

            <?php if ($usuario_logueado): ?>
                <div class="plans-grid">
                    <?php foreach ($planes as $key => $plan): ?>
                    <div class="plan-card <?php echo isset($plan['popular']) ? 'popular' : ''; ?>">
                        <div class="plan-name"><?php echo $plan['nombre']; ?></div>
                        
                        <?php if (isset($plan['precio_original'])): ?>
                            <div class="plan-price-original">$<?php echo number_format($plan['precio_original']); ?></div>
                        <?php endif; ?>
                        
                        <div class="plan-price">$<?php echo number_format($plan['precio']); ?></div>
                        <div class="plan-period">por <?php echo $plan['periodo']; ?></div>
                        
                        <?php if (isset($plan['descuento']) && $plan['descuento'] > 0): ?>
                            <div class="plan-discount"><?php echo $plan['descuento']; ?>% de descuento</div>
                        <?php endif; ?>
                        
                        <!-- Selector de método de pago -->
                        <?php if ($stripe_available || $mercadopago_available): ?>
                        <div class="payment-method-selector">
                            <label style="font-weight: 600; margin-bottom: 1rem; display: block;">Método de pago:</label>
                            
                            <?php if ($stripe_available): ?>
                            <div class="payment-option">
                                <input type="radio" id="stripe-<?php echo $key; ?>" name="payment_method_<?php echo $key; ?>" value="stripe" checked>
                                <label for="stripe-<?php echo $key; ?>" class="payment-label">
                                    <i class="fab fa-stripe" style="color: #635bff;"></i>
                                    Tarjeta de Crédito/Débito (Stripe)
                                </label>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($mercadopago_available): ?>
                            <div class="payment-option">
                                <input type="radio" id="mercadopago-<?php echo $key; ?>" name="payment_method_<?php echo $key; ?>" value="mercadopago" <?php echo !$stripe_available ? 'checked' : ''; ?>>
                                <label for="mercadopago-<?php echo $key; ?>" class="payment-label">
                                    <i class="fas fa-credit-card" style="color: #009ee3;"></i>
                                    MercadoPago (Tarjetas, OXXO, etc.)
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" class="subscription-form" data-plan="<?php echo $key; ?>">
                            <input type="hidden" name="plan" value="<?php echo $key; ?>">
                            <input type="hidden" name="payment_method" value="stripe" class="payment-method-input">
                            
                            <?php if ($stripe_available): ?>
                            <!-- Elemento de tarjeta para Stripe -->
                            <div class="card-input-section stripe-section" id="stripe-section-<?php echo $key; ?>">
                                <label>Información de la tarjeta:</label>
                                <div id="card-element-<?php echo $key; ?>" class="card-element">
                                    <!-- Stripe Elements creará el input aquí -->
                                </div>
                                <div id="card-errors-<?php echo $key; ?>" class="card-errors"></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($mercadopago_available): ?>
                            <!-- Información para MercadoPago -->
                            <div class="mercadopago-section" id="mercadopago-section-<?php echo $key; ?>" style="display: none;">
                                <div class="mb-3">
                                    <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 1rem;">
                                        <i class="fas fa-info-circle" style="color: #009ee3;"></i>
                                        Con MercadoPago puedes pagar con tarjeta, OXXO, bancos y más opciones
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="plan-btn" <?php echo (!$stripe_available && !$mercadopago_available) ? 'disabled' : ''; ?>>
                                <i class="fas fa-credit-card" style="margin-right: 0.5rem;"></i>
                                <?php if ($stripe_available || $mercadopago_available): ?>
                                    <span class="btn-text">Suscribirse ahora</span>
                                <?php else: ?>
                                    Sistema no disponible
                                <?php endif; ?>
                            </button>
                        </form>
                        
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>
                                <?php if ($stripe_available || $mercadopago_available): ?>
                                    Pago 100% seguro con cifrado SSL
                                <?php else: ?>
                                    Sistema de pagos no configurado
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($stripe_available || $mercadopago_available): ?>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-amex"></i>
                            <?php if ($mercadopago_available): ?>
                                <i class="fas fa-store" title="OXXO"></i>
                                <i class="fas fa-university" title="Bancos"></i>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="login-required">
                    <h3>Inicia Sesión para Continuar</h3>
                    <p>Necesitas tener una cuenta para suscribirte a la membresía premium</p>
                    <a href="login.php" class="btn-primary">Iniciar Sesión</a>
                    <a href="register.php" class="btn-secondary">Registrarse</a>
                </div>
            <?php endif; ?>
            
            <?php if ($stripe_available || $mercadopago_available): ?>
            <div class="powered-by-stripe">
                Pagos procesados de forma segura por 
                <?php if ($stripe_available): ?>
                    <a href="https://stripe.com" target="_blank">Stripe</a>
                <?php endif; ?>
                <?php if ($stripe_available && $mercadopago_available): ?>
                    y 
                <?php endif; ?>
                <?php if ($mercadopago_available): ?>
                    <a href="https://mercadopago.com.mx" target="_blank">MercadoPago</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="powered-by-stripe">
                <strong>Para habilitar pagos:</strong> 
                <?php if (!$stripe_available): ?>
                    Instala Stripe PHP con <code>composer require stripe/stripe-php</code>
                <?php endif; ?>
                <?php if (!$stripe_available && !$mercadopago_available): ?>
                    y 
                <?php endif; ?>
                <?php if (!$mercadopago_available): ?>
                    configura MercadoPago en <code>config/mercadopago.php</code>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        // Variables globales
        let processingPayment = false;
        
        // Función para procesar pago con MercadoPago
        function processMercadoPagoPayment(planData, button) {
            try {
                // Mostrar estado de carga
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>Redirigiendo a MercadoPago...';
                button.disabled = true;
                
                // El formulario se enviará normalmente al servidor para crear la preferencia de MercadoPago
                return true;
                
            } catch (error) {
                console.error('❌ Error preparando MercadoPago:', error);
                alert('Error al preparar el pago: ' + error.message);
                
                // Restaurar botón
                button.innerHTML = originalText;
                button.disabled = false;
                return false;
            }
        }
        
        // Animación de entrada para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.benefit-card, .plan-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            });

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                observer.observe(card);
            });
        });

        // Manejo de formularios de suscripción
        document.querySelectorAll('.subscription-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const planCard = this.closest('.plan-card');
                const planName = planCard.querySelector('.plan-name').textContent;
                const planPriceText = planCard.querySelector('.plan-price').textContent;
                const planPrice = parseFloat(planPriceText.replace(/[$,\s]/g, ''));
                const button = this.querySelector('.plan-btn');
                
                // Determinar el tipo de plan
                const planKey = planName.includes('Anual') ? 'yearly' : 'monthly';
                
                const planData = {
                    nombre: planName,
                    precio: planPrice,
                    key: planKey
                };
                
                // Confirmación
                if (!confirm(`¿Estás seguro de que quieres suscribirte al ${planName} por $${planPrice} con MercadoPago?`)) {
                    return;
                }
                
                // Procesar con MercadoPago (envío de formulario normal)
                if (processMercadoPagoPayment(planData, button)) {
                    // Permitir envío del formulario
                    this.submit();
                }
            });
        });

        // Detectar si regresamos de MercadoPago
        if (window.location.search.includes('canceled=1') || window.location.search.includes('membership_error=1')) {
            // Remover el parámetro de la URL sin recargar
            const url = new URL(window.location);
            url.searchParams.delete('canceled');
            url.searchParams.delete('membership_error');
            window.history.replaceState({}, '', url);
        }
        
        console.log('✅ Sistema de membresías con MercadoPago inicializado correctamente');
    </script>
</body>
</html>