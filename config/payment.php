<?php
// Cargar variables de entorno si no están ya cargadas
if (!function_exists('load_env')) {
    function load_env() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if (!empty($key)) {
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                    }
                }
            }
        }
    }
    load_env();
}

// Configuración de pagos
define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? null);
define('STRIPE_PUBLIC_KEY', $_ENV['STRIPE_PUBLIC_KEY'] ?? null);
define('STRIPE_WEBHOOK_SECRET', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null);
// Configuración de Stripe Connect
define('STRIPE_CLIENT_ID', $_ENV['STRIPE_CLIENT_ID'] ?? null);
define('STRIPE_CURRENCY', $_ENV['STRIPE_CURRENCY'] ?? 'MXN');
// Configuración de retiros
define('STRIPE_MIN_PAYOUT_AMOUNT', $_ENV['STRIPE_MIN_PAYOUT_AMOUNT'] ?? 100);
define('STRIPE_PAYOUT_DELAY_DAYS', $_ENV['STRIPE_PAYOUT_DELAY_DAYS'] ?? 7);
// URLs de redirección
define('STRIPE_CONNECT_SUCCESS_URL', $_ENV['STRIPE_CONNECT_SUCCESS_URL'] ?? '');
define('STRIPE_CONNECT_REFRESH_URL', $_ENV['STRIPE_CONNECT_REFRESH_URL'] ?? '');
// Comisiones
define('STRIPE_PLATFORM_FEE_PERCENTAGE', $_ENV['STRIPE_PLATFORM_FEE_PERCENTAGE'] ?? 15);
define('STRIPE_CONNECT_FEE_PERCENTAGE', $_ENV['STRIPE_CONNECT_FEE_PERCENTAGE'] ?? 2.9);
define('STRIPE_CONNECT_FIXED_FEE', $_ENV['STRIPE_CONNECT_FIXED_FEE'] ?? 0.30);
