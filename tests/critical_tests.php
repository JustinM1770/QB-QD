<?php
/**
 * Pruebas Criticas para QuickBite
 * Ejecutar: php tests/critical_tests.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n";
echo "==========================================\n";
echo "   PRUEBAS CRITICAS - QUICKBITE\n";
echo "==========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$tests_passed = 0;
$tests_failed = 0;
$tests_total = 0;

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

function section($title) {
    echo "\n--- $title ---\n";
}

// ==========================================
// 1. PRUEBAS DE ARCHIVOS CRITICOS
// ==========================================
section("ARCHIVOS CRITICOS");

test("Archivo .env existe", file_exists(__DIR__ . '/../.env'));
test("config/database.php existe", file_exists(__DIR__ . '/../config/database.php'));
test("config/env.php existe", file_exists(__DIR__ . '/../config/env.php'));
test("config/error_handler.php existe", file_exists(__DIR__ . '/../config/error_handler.php'));
test("index.php existe", file_exists(__DIR__ . '/../index.php'));

// ==========================================
// 2. PRUEBAS DE VARIABLES DE ENTORNO
// ==========================================
section("VARIABLES DE ENTORNO");

require_once __DIR__ . '/../config/env.php';

test("DB_HOST definido", !empty(env('DB_HOST')));
test("DB_NAME definido", !empty(env('DB_NAME')));
test("DB_USER definido", !empty(env('DB_USER')));
test("DB_PASS definido", !empty(env('DB_PASS')));
test("STRIPE_SECRET_KEY definido", !empty(env('STRIPE_SECRET_KEY')));
test("MP_ACCESS_TOKEN definido", !empty(env('MP_ACCESS_TOKEN')));
test("ENVIRONMENT es production", env('ENVIRONMENT') === 'production');

// ==========================================
// 3. PRUEBAS DE CONEXION A BASE DE DATOS
// ==========================================
section("CONEXION A BASE DE DATOS");

$db_connected = false;
$pdo = null;

try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $db_connected = true;
    test("Conexion a MySQL exitosa", true);
} catch (PDOException $e) {
    test("Conexion a MySQL exitosa", false, $e->getMessage());
}

if ($db_connected && $pdo) {
    // Verificar tablas criticas
    $critical_tables = [
        'usuarios', 'negocios', 'productos', 'pedidos',
        'repartidores', 'categorias_producto', 'metodos_pago'
    ];

    foreach ($critical_tables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            test("Tabla '$table' existe", true);
        } catch (Exception $e) {
            test("Tabla '$table' existe", false, $e->getMessage());
        }
    }

    // Contar registros importantes
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        test("Usuarios registrados: $count", $count >= 0);
    } catch (Exception $e) {
        test("Conteo de usuarios", false, $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM negocios WHERE activo = 1");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        test("Negocios activos: $count", $count >= 0);
    } catch (Exception $e) {
        test("Conteo de negocios", false, $e->getMessage());
    }
}

// ==========================================
// 4. PRUEBAS DE SEGURIDAD
// ==========================================
section("SEGURIDAD");

// Verificar que display_errors esta desactivado en produccion
$bootstrap_content = file_get_contents(__DIR__ . '/../config/boostrap.php');
test("display_errors=0 en produccion (bootstrap)",
    strpos($bootstrap_content, "ini_set('display_errors', 0)") !== false);

// Verificar que .env no es accesible via web
test("Archivo .env no tiene permisos 777",
    (fileperms(__DIR__ . '/../.env') & 0777) !== 0777);

// Verificar que existen archivos de seguridad
test("config/csrf.php existe", file_exists(__DIR__ . '/../config/csrf.php'));
test("config/rate_limit.php existe", file_exists(__DIR__ . '/../config/rate_limit.php'));
test("config/validation.php existe", file_exists(__DIR__ . '/../config/validation.php'));

// Verificar que no hay credenciales hardcodeadas en archivos criticos
$index_content = file_get_contents(__DIR__ . '/../index.php');
test("No hay passwords en index.php",
    strpos($index_content, 'Aa13684780') === false);

// ==========================================
// 5. PRUEBAS DE MODELOS
// ==========================================
section("MODELOS");

$models = ['Usuario', 'Negocio', 'Producto', 'Pedido', 'Repartidor'];

foreach ($models as $model) {
    $model_file = __DIR__ . "/../models/$model.php";
    if (file_exists($model_file)) {
        test("Modelo $model existe", true);

        // Verificar que usa prepared statements
        $content = file_get_contents($model_file);
        $uses_prepared = strpos($content, 'prepare(') !== false ||
                        strpos($content, 'bindParam') !== false ||
                        strpos($content, 'bindValue') !== false;
        test("Modelo $model usa prepared statements", $uses_prepared);
    } else {
        test("Modelo $model existe", false);
    }
}

// ==========================================
// 6. PRUEBAS DE APIs
// ==========================================
section("ENDPOINTS API");

$api_endpoints = [
    'api/get_order_status.php',
    'api/get_payment_methods.php',
    'api/wallet_api.php'
];

foreach ($api_endpoints as $endpoint) {
    test("API $endpoint existe", file_exists(__DIR__ . "/../$endpoint"));
}

// ==========================================
// 7. PRUEBAS DE INTEGRACIONES
// ==========================================
section("INTEGRACIONES DE PAGO");

// Stripe
$stripe_key = env('STRIPE_SECRET_KEY');
test("Stripe key es de produccion (sk_live)",
    $stripe_key && strpos($stripe_key, 'sk_live_') === 0);

// MercadoPago
$mp_token = env('MP_ACCESS_TOKEN');
test("MercadoPago token definido", !empty($mp_token));

// ==========================================
// 8. PRUEBAS DE PWA
// ==========================================
section("PWA");

test("manifest.json existe", file_exists(__DIR__ . '/../manifest.json'));
test("sw.js (Service Worker) existe", file_exists(__DIR__ . '/../sw.js'));
test("offline.html existe", file_exists(__DIR__ . '/../offline.html'));

if (file_exists(__DIR__ . '/../manifest.json')) {
    $manifest = json_decode(file_get_contents(__DIR__ . '/../manifest.json'), true);
    test("manifest.json es JSON valido", $manifest !== null);
    test("manifest tiene name", isset($manifest['name']));
    test("manifest tiene icons", isset($manifest['icons']) && count($manifest['icons']) > 0);
}

// ==========================================
// 9. PRUEBAS DE PERMISOS
// ==========================================
section("PERMISOS DE DIRECTORIOS");

$writable_dirs = ['logs', 'uploads', 'logs/rate_limits'];

foreach ($writable_dirs as $dir) {
    $path = __DIR__ . "/../$dir";
    if (file_exists($path)) {
        test("Directorio $dir es escribible", is_writable($path));
    } else {
        // Intentar crear
        if (@mkdir($path, 0755, true)) {
            test("Directorio $dir creado", true);
        } else {
            test("Directorio $dir existe/es escribible", false);
        }
    }
}

// ==========================================
// 10. PRUEBAS DE WHATSAPP BOT
// ==========================================
section("WHATSAPP BOT");

test("whatsapp-bot/server.js existe", file_exists(__DIR__ . '/../whatsapp-bot/server.js'));
test("whatsapp-bot/package.json existe", file_exists(__DIR__ . '/../whatsapp-bot/package.json'));

// Verificar si el bot esta corriendo
$bot_port = env('WHATSAPP_BOT_PORT', 3030);
$bot_running = @fsockopen('localhost', $bot_port, $errno, $errstr, 1);
if ($bot_running) {
    fclose($bot_running);
    test("WhatsApp Bot corriendo en puerto $bot_port", true);
} else {
    test("WhatsApp Bot corriendo en puerto $bot_port", false, "No accesible");
}

// ==========================================
// 11. PRUEBAS DE RENDIMIENTO BASICO
// ==========================================
section("RENDIMIENTO");

if ($db_connected && $pdo) {
    // Test de query simple
    $start = microtime(true);
    for ($i = 0; $i < 10; $i++) {
        $pdo->query("SELECT 1");
    }
    $time = (microtime(true) - $start) * 1000;
    test("10 queries simples < 100ms", $time < 100, round($time, 2) . "ms");

    // Test de query con JOIN
    $start = microtime(true);
    try {
        $pdo->query("
            SELECT p.*, n.nombre as negocio_nombre
            FROM productos p
            JOIN negocios n ON p.id_negocio = n.id_negocio
            LIMIT 10
        ");
        $time = (microtime(true) - $start) * 1000;
        test("Query con JOIN < 500ms", $time < 500, round($time, 2) . "ms");
    } catch (Exception $e) {
        test("Query con JOIN", false, $e->getMessage());
    }
}

// ==========================================
// RESUMEN
// ==========================================
echo "\n==========================================\n";
echo "RESUMEN DE PRUEBAS\n";
echo "==========================================\n";
echo "Total: $tests_total\n";
echo "Pasaron: $tests_passed (" . round($tests_passed/$tests_total*100, 1) . "%)\n";
echo "Fallaron: $tests_failed (" . round($tests_failed/$tests_total*100, 1) . "%)\n";
echo "==========================================\n";

if ($tests_failed > 0) {
    echo "\n[!] HAY $tests_failed PRUEBAS FALLIDAS QUE REQUIEREN ATENCION\n";
    exit(1);
} else {
    echo "\n[OK] TODAS LAS PRUEBAS PASARON\n";
    exit(0);
}
