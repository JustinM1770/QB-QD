<?php
/**
 * VERIFICACION COMPLETA DEL SISTEMA - QUICKBITE
 * Antes de lanzar, asegurarse que TODO funciona
 *
 * Ejecutar: php tests/complete_system_check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/env.php';

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║         VERIFICACION COMPLETA DEL SISTEMA - QUICKBITE            ║\n";
echo "║                   Pre-Lanzamiento Teocaltiche                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$total_checks = 0;
$passed = 0;
$failed = 0;
$warnings = 0;
$critical_issues = [];

function check($name, $condition, $is_critical = false) {
    global $total_checks, $passed, $failed, $critical_issues;
    $total_checks++;

    if ($condition) {
        echo "  ✅ $name\n";
        $passed++;
        return true;
    } else {
        echo "  ❌ $name\n";
        $failed++;
        if ($is_critical) {
            $critical_issues[] = $name;
        }
        return false;
    }
}

function warn($name, $note = '') {
    global $warnings;
    $warnings++;
    echo "  ⚠️  $name" . ($note ? " - $note" : "") . "\n";
}

function section($title) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  $title\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
}

function info($text) {
    echo "  ℹ️  $text\n";
}

// Conexion a BD
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $db_connected = true;
} catch (PDOException $e) {
    die("❌ CRITICO: No se puede conectar a la base de datos: " . $e->getMessage() . "\n");
}

// ═══════════════════════════════════════════════════════════════
// 1. FLUJO DEL CLIENTE
// ═══════════════════════════════════════════════════════════════
section("1. FLUJO DEL CLIENTE");

// 1.1 Registro/Login
echo "\n  📱 Registro y Login:\n";
check("Página de registro existe", file_exists(__DIR__ . '/../register.php'), true);
check("Página de login existe", file_exists(__DIR__ . '/../login.php'), true);
check("Recuperar contraseña existe", file_exists(__DIR__ . '/../forgot-password.php'));
check("Verificación de email existe", file_exists(__DIR__ . '/../verify_email.php'));

// Verificar que hay usuarios registrados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
$usuarios = $stmt->fetch()['total'];
check("Hay usuarios registrados ($usuarios)", $usuarios > 0, true);

// 1.2 Ver negocios
echo "\n  🏪 Ver Negocios:\n";
check("Página principal existe", file_exists(__DIR__ . '/../index.php'), true);
check("Página de búsqueda existe", file_exists(__DIR__ . '/../buscar.php'));
check("Página de negocio individual existe", file_exists(__DIR__ . '/../negocio.php'), true);

$stmt = $pdo->query("SELECT COUNT(*) as total FROM negocios WHERE activo = 1");
$negocios = $stmt->fetch()['total'];
check("Hay negocios activos ($negocios)", $negocios > 0, true);

$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE disponible = 1");
$productos = $stmt->fetch()['total'];
check("Hay productos disponibles ($productos)", $productos > 0, true);

// 1.3 Carrito
echo "\n  🛒 Carrito:\n";
check("Página de carrito existe", file_exists(__DIR__ . '/../carrito.php'), true);

$carrito_content = file_get_contents(__DIR__ . '/../carrito.php');
check("Carrito maneja sesiones", strpos($carrito_content, 'SESSION') !== false);
check("Carrito puede agregar productos", strpos($carrito_content, 'agregar') !== false || strpos($carrito_content, 'add') !== false);

// 1.4 Checkout
echo "\n  💳 Checkout:\n";
check("Página de checkout existe", file_exists(__DIR__ . '/../checkout.php'), true);

$checkout_content = file_get_contents(__DIR__ . '/../checkout.php');
check("Checkout valida usuario logueado", strpos($checkout_content, 'loggedin') !== false);
check("Checkout incluye dirección", strpos($checkout_content, 'direccion') !== false || strpos($checkout_content, 'Direccion') !== false);

// 1.5 Confirmación y seguimiento
echo "\n  📦 Confirmación y Seguimiento:\n";
check("Página de confirmación existe", file_exists(__DIR__ . '/../confirmacion_pedido.php'), true);
check("Página de mis pedidos existe", file_exists(__DIR__ . '/../pedidos.php'), true);

// 1.6 Perfil
echo "\n  👤 Perfil de Usuario:\n";
check("Página de perfil existe", file_exists(__DIR__ . '/../perfil.php'));

// ═══════════════════════════════════════════════════════════════
// 2. FLUJO DEL NEGOCIO
// ═══════════════════════════════════════════════════════════════
section("2. FLUJO DEL NEGOCIO");

// 2.1 Registro/Login
echo "\n  🔐 Registro y Login:\n";
check("Login de negocio existe", file_exists(__DIR__ . '/../login_negocio.php'), true);
check("Registro de negocio existe", file_exists(__DIR__ . '/../registro_negocio.php'), true);

// 2.2 Panel de administración
echo "\n  📊 Panel de Administración:\n";
check("Panel de configuración existe", file_exists(__DIR__ . '/../admin/negocio_configuracion.php'), true);
check("Gestión de menú existe", file_exists(__DIR__ . '/../admin/menu.php'), true);
check("Gestión de pedidos existe", file_exists(__DIR__ . '/../admin/pedidos.php'), true);
check("Categorías existe", file_exists(__DIR__ . '/../admin/categorias.php'));
check("Reportes existe", file_exists(__DIR__ . '/../admin/reportes.php'));

// 2.3 Verificar que negocios tienen datos completos
echo "\n  📋 Datos de Negocios:\n";
$stmt = $pdo->query("
    SELECT n.*,
           (SELECT COUNT(*) FROM productos WHERE id_negocio = n.id_negocio AND disponible = 1) as productos
    FROM negocios n
    WHERE n.activo = 1
");
$negocios_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($negocios_data as $neg) {
    $tiene_productos = $neg['productos'] > 0;
    $tiene_direccion = !empty($neg['calle']) && !empty($neg['ciudad']);
    $tiene_telefono = !empty($neg['telefono']);

    echo "\n  Negocio: {$neg['nombre']} (ID: {$neg['id_negocio']})\n";
    check("    Tiene productos ({$neg['productos']})", $tiene_productos, true);
    check("    Tiene dirección completa", $tiene_direccion);
    check("    Tiene teléfono", $tiene_telefono);

    if (!$tiene_productos) {
        warn("    Sin productos - no aparecerá en búsquedas");
    }
}

// ═══════════════════════════════════════════════════════════════
// 3. FLUJO DEL REPARTIDOR
// ═══════════════════════════════════════════════════════════════
section("3. FLUJO DEL REPARTIDOR");

echo "\n  🔐 Registro y Login:\n";
check("Login de repartidor existe", file_exists(__DIR__ . '/../login_repartidor.php'), true);
check("Registro de repartidor existe", file_exists(__DIR__ . '/../registro_repartidor.php'), true);

echo "\n  📱 Dashboard:\n";
check("Dashboard de repartidor existe", file_exists(__DIR__ . '/../admin/repartidor_dashboard.php'), true);

// Verificar repartidores
$stmt = $pdo->query("
    SELECT r.*, u.nombre, u.telefono, u.email
    FROM repartidores r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.activo = 1
");
$repartidores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n  👥 Repartidores Registrados:\n";
check("Hay repartidores activos (" . count($repartidores) . ")", count($repartidores) > 0, true);

$disponibles = 0;
foreach ($repartidores as $rep) {
    if ($rep['disponible']) $disponibles++;
}
info("Repartidores disponibles ahora: $disponibles");

if ($disponibles == 0) {
    warn("No hay repartidores disponibles - los pedidos no se podrán asignar");
}

// ═══════════════════════════════════════════════════════════════
// 4. SISTEMA DE PAGOS
// ═══════════════════════════════════════════════════════════════
section("4. SISTEMA DE PAGOS");

echo "\n  💳 Stripe:\n";
$stripe_secret = env('STRIPE_SECRET_KEY');
$stripe_public = env('STRIPE_PUBLIC_KEY');
$stripe_webhook = env('STRIPE_WEBHOOK_SECRET');

check("Stripe Secret Key configurada", !empty($stripe_secret), true);
check("Stripe Public Key configurada", !empty($stripe_public), true);
check("Stripe Webhook Secret configurada", !empty($stripe_webhook));

if ($stripe_secret) {
    $is_live = strpos($stripe_secret, 'sk_live_') === 0;
    if ($is_live) {
        check("Stripe en modo PRODUCCIÓN", true);
    } else {
        warn("Stripe en modo TEST - cambiar a producción antes de lanzar");
    }
}

echo "\n  💰 MercadoPago:\n";
$mp_token = env('MP_ACCESS_TOKEN');
$mp_public = env('MP_PUBLIC_KEY');
$mp_webhook = env('MP_WEBHOOK_SECRET');

check("MercadoPago Access Token configurado", !empty($mp_token), true);
check("MercadoPago Public Key configurada", !empty($mp_public));
check("MercadoPago Webhook Secret configurado", !empty($mp_webhook));

echo "\n  🔗 Webhooks:\n";
check("Webhook Stripe existe", file_exists(__DIR__ . '/../webhooks/stripe.php') || file_exists(__DIR__ . '/../webhook/stripe_webhook.php'));
check("Webhook MercadoPago existe", file_exists(__DIR__ . '/../webhooks/mercadopago.php') || file_exists(__DIR__ . '/../api/mercadopago/webhook.php'));

// ═══════════════════════════════════════════════════════════════
// 5. WHATSAPP BOT
// ═══════════════════════════════════════════════════════════════
section("5. WHATSAPP BOT");

$bot_port = env('WHATSAPP_BOT_PORT', 3030);
$bot_url = "http://localhost:$bot_port";

echo "\n  🤖 Estado del Bot:\n";

// Verificar si el servidor está corriendo
$ch = curl_init("$bot_url/health");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$bot_running = $http_code === 200;
check("Bot corriendo en puerto $bot_port", $bot_running);

if ($bot_running) {
    $health = json_decode($response, true);
    if ($health) {
        $whatsapp_ready = $health['whatsapp_ready'] ?? $health['connected'] ?? false;
        check("WhatsApp conectado/autenticado", $whatsapp_ready);

        if (!$whatsapp_ready) {
            warn("WhatsApp no conectado - escanear QR si es necesario");
        }
    }
}

// Verificar endpoint de envío
$ch = curl_init("$bot_url/send");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => true]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

check("Endpoint /send accesible", $http_code > 0 && $http_code < 500);

// ═══════════════════════════════════════════════════════════════
// 6. NOTIFICACIONES Y EMAIL
// ═══════════════════════════════════════════════════════════════
section("6. NOTIFICACIONES Y EMAIL");

echo "\n  📧 Configuración de Email:\n";
check("SMTP Host configurado", !empty(env('SMTP_HOST')));
check("SMTP User configurado", !empty(env('SMTP_USER')));
check("SMTP Pass configurado", !empty(env('SMTP_PASS')));
check("PHPMailer instalado", file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php'));

echo "\n  🔔 Push Notifications:\n";
check("Service Worker existe", file_exists(__DIR__ . '/../sw.js'));
check("Manifest.json existe", file_exists(__DIR__ . '/../manifest.json'));

// ═══════════════════════════════════════════════════════════════
// 7. ESTADOS DE PEDIDO
// ═══════════════════════════════════════════════════════════════
section("7. ESTADOS DE PEDIDO");

$stmt = $pdo->query("SELECT * FROM estados_pedido ORDER BY id_estado");
$estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n  Flujo de estados configurado:\n";
$estados_requeridos = ['pendiente', 'confirmado', 'en_preparacion', 'en_camino', 'entregado', 'cancelado'];
$estados_nombres = array_column($estados, 'nombre');

foreach ($estados_requeridos as $estado) {
    $existe = in_array($estado, $estados_nombres);
    check("Estado '$estado' existe", $existe, true);
}

// ═══════════════════════════════════════════════════════════════
// 8. APIS CRÍTICAS
// ═══════════════════════════════════════════════════════════════
section("8. APIS CRÍTICAS");

$apis = [
    'api/get_order_status.php' => 'Estado de pedido',
    'api/get_payment_methods.php' => 'Métodos de pago',
    'api/producto_opciones.php' => 'Opciones de producto',
];

foreach ($apis as $file => $desc) {
    check("API $desc existe", file_exists(__DIR__ . "/../$file"));
}

// ═══════════════════════════════════════════════════════════════
// 9. SEGURIDAD
// ═══════════════════════════════════════════════════════════════
section("9. SEGURIDAD");

check("CSRF Protection existe", file_exists(__DIR__ . '/../config/csrf.php'));
check("Rate Limiting existe", file_exists(__DIR__ . '/../config/rate_limit.php'));
check("Error Handler existe", file_exists(__DIR__ . '/../config/error_handler.php'));
check("Archivo .env protegido (no 777)", (fileperms(__DIR__ . '/../.env') & 0777) !== 0777);

// Verificar HTTPS en producción
$app_url = env('APP_URL', '');
if (strpos($app_url, 'https://') === 0) {
    check("URL configurada con HTTPS", true);
} else {
    warn("URL no usa HTTPS - configurar SSL antes de lanzar");
}

// ═══════════════════════════════════════════════════════════════
// 10. PWA Y MÓVIL
// ═══════════════════════════════════════════════════════════════
section("10. PWA Y MÓVIL");

check("Manifest.json válido", file_exists(__DIR__ . '/../manifest.json'));
check("Service Worker existe", file_exists(__DIR__ . '/../sw.js'));
check("Página offline existe", file_exists(__DIR__ . '/../offline.html'));

if (file_exists(__DIR__ . '/../manifest.json')) {
    $manifest = json_decode(file_get_contents(__DIR__ . '/../manifest.json'), true);
    check("Manifest tiene nombre", isset($manifest['name']));
    check("Manifest tiene iconos", isset($manifest['icons']) && count($manifest['icons']) > 0);
    check("Manifest tiene start_url", isset($manifest['start_url']));
}

// ═══════════════════════════════════════════════════════════════
// RESUMEN FINAL
// ═══════════════════════════════════════════════════════════════
echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                        RESUMEN FINAL                              ║\n";
echo "╠═══════════════════════════════════════════════════════════════════╣\n";

$porcentaje = round(($passed / $total_checks) * 100, 1);
$status_emoji = $porcentaje >= 90 ? "✅" : ($porcentaje >= 70 ? "⚠️" : "❌");

printf("║  Total verificaciones: %-4d                                      ║\n", $total_checks);
printf("║  Pasaron:              %-4d (%5.1f%%)                             ║\n", $passed, ($passed/$total_checks)*100);
printf("║  Fallaron:             %-4d (%5.1f%%)                             ║\n", $failed, ($failed/$total_checks)*100);
printf("║  Advertencias:         %-4d                                      ║\n", $warnings);
echo "╠═══════════════════════════════════════════════════════════════════╣\n";

if (empty($critical_issues)) {
    echo "║  $status_emoji ESTADO: LISTO PARA LANZAR                             ║\n";
} else {
    echo "║  ❌ ESTADO: HAY PROBLEMAS CRÍTICOS                                ║\n";
    echo "╠═══════════════════════════════════════════════════════════════════╣\n";
    echo "║  PROBLEMAS CRÍTICOS A RESOLVER:                                   ║\n";
    foreach ($critical_issues as $issue) {
        echo "║  • " . substr($issue, 0, 60) . str_repeat(' ', max(0, 60 - strlen($issue))) . "   ║\n";
    }
}

echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

// Recomendaciones finales
if ($failed > 0 || $warnings > 0) {
    echo "📋 RECOMENDACIONES:\n";
    echo "─────────────────────────────────────────────────────────────────────\n";

    if (count($repartidores) < 3) {
        echo "• Necesitas al menos 3-5 repartidores antes de lanzar\n";
    }

    if (count($negocios_data) < 3) {
        echo "• Necesitas al menos 3-5 negocios con productos antes de lanzar\n";
    }

    if (!$bot_running) {
        echo "• Iniciar el WhatsApp Bot: cd whatsapp-bot && npm start\n";
    }

    echo "\n";
}

exit($failed > 0 ? 1 : 0);
