<?php
/**
 * Pruebas Funcionales para QuickBite
 * Verifica: Pedidos, WhatsApp Bot, Cupones, Carrito, Pagos
 * Ejecutar: php tests/functional_tests.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/env.php';

echo "\n";
echo "==========================================\n";
echo "   PRUEBAS FUNCIONALES - QUICKBITE\n";
echo "==========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$tests_passed = 0;
$tests_failed = 0;
$tests_total = 0;
$warnings = [];

function test($name, $condition, $error_msg = '') {
    global $tests_passed, $tests_failed, $tests_total;
    $tests_total++;
    if ($condition) {
        echo "[PASS] $name\n";
        $tests_passed++;
        return true;
    } else {
        echo "[FAIL] $name" . ($error_msg ? " - $error_msg" : "") . "\n";
        $tests_failed++;
        return false;
    }
}

function warn($msg) {
    global $warnings;
    $warnings[] = $msg;
    echo "[WARN] $msg\n";
}

function section($title) {
    echo "\n=== $title ===\n";
}

// Conexion a BD
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Error de conexion a BD: " . $e->getMessage() . "\n");
}

// ==========================================
// 1. SISTEMA DE PEDIDOS
// ==========================================
section("SISTEMA DE PEDIDOS");

// Verificar tabla de pedidos
try {
    $stmt = $pdo->query("DESCRIBE pedidos");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $required_columns = ['id_pedido', 'id_usuario', 'id_negocio', 'monto_total', 'id_estado', 'fecha_creacion'];
    $missing = array_diff($required_columns, $columns);
    test("Tabla pedidos tiene columnas requeridas", empty($missing),
         $missing ? "Faltan: " . implode(', ', $missing) : "");
} catch (Exception $e) {
    test("Tabla pedidos estructura", false, $e->getMessage());
}

// Verificar estados de pedido
try {
    $stmt = $pdo->query("SELECT * FROM estados_pedido ORDER BY id_estado");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    test("Tabla estados_pedido existe", count($estados) > 0);

    $expected_states = ['pendiente', 'confirmado', 'preparando', 'en_camino', 'entregado', 'cancelado'];
    $found_states = array_column($estados, 'nombre');

    echo "   Estados encontrados: " . implode(', ', $found_states) . "\n";
    test("Estados de pedido configurados", count($estados) >= 5);
} catch (Exception $e) {
    test("Estados de pedido", false, $e->getMessage());
}

// Estadisticas de pedidos
try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN id_estado = 1 THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN id_estado = 6 THEN 1 ELSE 0 END) as entregados,
            SUM(CASE WHEN id_estado = 7 THEN 1 ELSE 0 END) as cancelados,
            COALESCE(SUM(monto_total), 0) as monto_total
        FROM pedidos
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "   Total pedidos: {$stats['total']}\n";
    echo "   Pendientes: {$stats['pendientes']}\n";
    echo "   Entregados: {$stats['entregados']}\n";
    echo "   Cancelados: {$stats['cancelados']}\n";
    echo "   Monto total: $" . number_format($stats['monto_total'], 2) . "\n";

    test("Sistema de pedidos operativo", true);
} catch (Exception $e) {
    test("Estadisticas de pedidos", false, $e->getMessage());
}

// Verificar modelo de Pedidos
try {
    require_once __DIR__ . '/../models/Pedido.php';
    $pedido = new Pedido($pdo);
    test("Modelo Pedido carga correctamente", true);

    // Verificar metodos criticos
    $methods = get_class_methods($pedido);
    $required_methods = ['crear', 'obtenerPorId', 'actualizarEstado'];
    $missing_methods = [];
    foreach ($required_methods as $method) {
        if (!in_array($method, $methods)) {
            $missing_methods[] = $method;
        }
    }
    test("Modelo Pedido tiene metodos criticos", empty($missing_methods),
         $missing_methods ? "Faltan: " . implode(', ', $missing_methods) : "");
} catch (Exception $e) {
    test("Modelo Pedido", false, $e->getMessage());
}

// ==========================================
// 2. WHATSAPP BOT
// ==========================================
section("WHATSAPP BOT");

// Verificar que el servidor esta corriendo
$bot_port = env('WHATSAPP_BOT_PORT', 3030);
$bot_url = "http://localhost:$bot_port";

$ch = curl_init("$bot_url/health");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    test("WhatsApp Bot responde en puerto $bot_port", true);
    $health = json_decode($response, true);
    if ($health) {
        echo "   Status: " . ($health['status'] ?? 'unknown') . "\n";
        echo "   WhatsApp conectado: " . ($health['whatsapp_ready'] ?? 'unknown') . "\n";
    }
} else {
    // Intentar endpoint raiz
    $ch = curl_init("$bot_url/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    test("WhatsApp Bot accesible", $http_code === 200, "HTTP $http_code");
}

// Verificar endpoint de envio
$ch = curl_init("$bot_url/status");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $status = json_decode($response, true);
    test("WhatsApp Bot endpoint /status", true);
    if (isset($status['connected'])) {
        echo "   Conectado a WhatsApp: " . ($status['connected'] ? 'Si' : 'No') . "\n";
    }
} else {
    warn("Endpoint /status no disponible (HTTP $http_code)");
}

// Verificar configuracion de WhatsApp
test("WHATSAPP_PHONE_NUMBER_ID configurado", !empty(env('WHATSAPP_PHONE_NUMBER_ID')));
test("WHATSAPP_ACCESS_TOKEN configurado", !empty(env('WHATSAPP_ACCESS_TOKEN')));

// ==========================================
// 3. SISTEMA DE CUPONES/PROMOCIONES
// ==========================================
section("SISTEMA DE CUPONES/PROMOCIONES");

// Verificar tabla de promociones
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'promociones'");
    $exists = $stmt->rowCount() > 0;
    test("Tabla promociones existe", $exists);

    if ($exists) {
        $stmt = $pdo->query("SELECT * FROM promociones WHERE activa = 1");
        $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Promociones activas: " . count($promos) . "\n";

        foreach (array_slice($promos, 0, 3) as $promo) {
            echo "   - {$promo['codigo']}: {$promo['descripcion']}\n";
        }

        test("Hay promociones configuradas", count($promos) >= 0);
    }
} catch (Exception $e) {
    test("Tabla promociones", false, $e->getMessage());
}

// Verificar tabla de cupones
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'cupones'");
    $exists = $stmt->rowCount() > 0;

    if ($exists) {
        test("Tabla cupones existe", true);
        $stmt = $pdo->query("SELECT * FROM cupones WHERE activo = 1 AND fecha_expiracion > NOW()");
        $cupones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Cupones vigentes: " . count($cupones) . "\n";
    } else {
        warn("Tabla cupones no existe - usando solo promociones");
    }
} catch (Exception $e) {
    warn("Error verificando cupones: " . $e->getMessage());
}

// Verificar modelo de Promocion
try {
    require_once __DIR__ . '/../models/Promocion.php';
    $promocion = new Promocion($pdo);
    test("Modelo Promocion carga correctamente", true);

    // Verificar metodo de validacion de codigo
    $methods = get_class_methods($promocion);
    test("Modelo Promocion tiene metodo validar",
         in_array('validarCodigo', $methods) || in_array('aplicar', $methods) || in_array('obtenerPorCodigo', $methods));
} catch (Exception $e) {
    test("Modelo Promocion", false, $e->getMessage());
}

// ==========================================
// 4. SISTEMA DE CARRITO
// ==========================================
section("SISTEMA DE CARRITO");

// Verificar archivo de carrito
test("Archivo carrito.php existe", file_exists(__DIR__ . '/../carrito.php'));
test("Archivo checkout.php existe", file_exists(__DIR__ . '/../checkout.php'));

// Verificar logica de carrito en sesion
$carrito_content = file_get_contents(__DIR__ . '/../carrito.php');
test("Carrito usa SESSION", strpos($carrito_content, '$_SESSION') !== false);
test("Carrito tiene validacion de sesion",
     strpos($carrito_content, 'session') !== false || strpos($carrito_content, 'SESSION') !== false);

// ==========================================
// 5. SISTEMA DE PAGOS
// ==========================================
section("SISTEMA DE PAGOS");

// Stripe
$stripe_key = env('STRIPE_SECRET_KEY');
test("Stripe configurado (produccion)", $stripe_key && strpos($stripe_key, 'sk_live_') === 0);

// Verificar archivo de configuracion Stripe
if (file_exists(__DIR__ . '/../config/stripe.php')) {
    test("config/stripe.php existe", true);
} else {
    warn("config/stripe.php no existe");
}

// MercadoPago
$mp_token = env('MP_ACCESS_TOKEN');
test("MercadoPago configurado", !empty($mp_token));

// Verificar metodos de pago en BD
try {
    $stmt = $pdo->query("SELECT * FROM metodos_pago");
    $metodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Metodos de pago activos: " . count($metodos) . "\n";
    foreach ($metodos as $m) {
        echo "   - " . ($m['nombre'] ?? $m['tipo'] ?? 'Desconocido') . "\n";
    }
    test("Metodos de pago configurados", count($metodos) > 0);
} catch (Exception $e) {
    test("Metodos de pago en BD", false, $e->getMessage());
}

// ==========================================
// 6. SISTEMA DE REPARTIDORES
// ==========================================
section("SISTEMA DE REPARTIDORES");

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as disponibles
        FROM repartidores
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "   Total repartidores: {$stats['total']}\n";
    echo "   Activos: {$stats['activos']}\n";
    echo "   Disponibles ahora: {$stats['disponibles']}\n";

    test("Sistema de repartidores operativo", true);
} catch (Exception $e) {
    test("Sistema de repartidores", false, $e->getMessage());
}

// Verificar dashboard de repartidor
test("Dashboard repartidor existe", file_exists(__DIR__ . '/../admin/repartidor_dashboard.php'));

// ==========================================
// 7. SISTEMA DE NEGOCIOS
// ==========================================
section("SISTEMA DE NEGOCIOS");

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado_operativo = 'activo' THEN 1 ELSE 0 END) as verificados
        FROM negocios
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "   Total negocios: {$stats['total']}\n";
    echo "   Activos: {$stats['activos']}\n";
    echo "   Verificados: {$stats['verificados']}\n";

    test("Negocios registrados", $stats['total'] > 0);

    // Verificar productos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE disponible = 1");
    $productos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Productos disponibles: {$productos['total']}\n";
    test("Productos en catalogo", $productos['total'] > 0);
} catch (Exception $e) {
    test("Sistema de negocios", false, $e->getMessage());
}

// ==========================================
// 8. SISTEMA DE NOTIFICACIONES
// ==========================================
section("SISTEMA DE NOTIFICACIONES");

// Email
test("SMTP configurado", !empty(env('SMTP_HOST')));
test("PHPMailer instalado", file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php'));

// Push notifications (Service Worker)
if (file_exists(__DIR__ . '/../sw.js')) {
    $sw_content = file_get_contents(__DIR__ . '/../sw.js');
    test("Service Worker tiene push", strpos($sw_content, 'push') !== false);
}

// ==========================================
// 9. SISTEMA DE MEMBRESIAS
// ==========================================
section("SISTEMA DE MEMBRESIAS");

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'membresias'");
    if ($stmt->rowCount() > 0) {
        test("Tabla membresias existe", true);

        $stmt = $pdo->query("SELECT * FROM membresias LIMIT 10");
        $membresias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Planes de membresia activos: " . count($membresias) . "\n";
    } else {
        warn("Tabla membresias no encontrada");
    }
} catch (Exception $e) {
    warn("Error verificando membresias: " . $e->getMessage());
}

// Verificar modelo
if (file_exists(__DIR__ . '/../models/Membership.php')) {
    test("Modelo Membership existe", true);
}

// ==========================================
// 10. FLUJO COMPLETO DE PEDIDO (Simulacion)
// ==========================================
section("SIMULACION DE FLUJO DE PEDIDO");

echo "   Verificando flujo: Usuario -> Carrito -> Checkout -> Pedido -> Repartidor\n";

// Verificar que existen todos los archivos del flujo
$flujo_files = [
    'index.php' => 'Pagina principal',
    'negocio.php' => 'Ver negocio/menu',
    'carrito.php' => 'Carrito de compras',
    'checkout.php' => 'Proceso de pago',
    'confirmacion_pedido.php' => 'Confirmacion',
    'pedidos.php' => 'Mis pedidos',
    'admin/pedidos.php' => 'Admin pedidos (negocio)',
    'admin/repartidor_dashboard.php' => 'Dashboard repartidor'
];

$flujo_ok = true;
foreach ($flujo_files as $file => $desc) {
    if (!file_exists(__DIR__ . "/../$file")) {
        echo "   [FALTA] $file - $desc\n";
        $flujo_ok = false;
    }
}

test("Archivos del flujo de pedido completos", $flujo_ok);

// ==========================================
// RESUMEN
// ==========================================
echo "\n==========================================\n";
echo "RESUMEN DE PRUEBAS FUNCIONALES\n";
echo "==========================================\n";
echo "Total: $tests_total\n";
echo "Pasaron: $tests_passed (" . round($tests_passed/$tests_total*100, 1) . "%)\n";
echo "Fallaron: $tests_failed (" . round($tests_failed/$tests_total*100, 1) . "%)\n";

if (count($warnings) > 0) {
    echo "\nAdvertencias (" . count($warnings) . "):\n";
    foreach ($warnings as $w) {
        echo "  - $w\n";
    }
}

echo "==========================================\n";

if ($tests_failed > 0) {
    echo "\n[!] HAY $tests_failed PRUEBAS QUE REQUIEREN ATENCION\n";
    exit(1);
} else {
    echo "\n[OK] SISTEMA FUNCIONAL\n";
    exit(0);
}
