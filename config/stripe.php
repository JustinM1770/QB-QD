<?php
/**
 * Configuración de Stripe para QuickBite
 * 
 * SEGURIDAD: Las credenciales se cargan desde variables de entorno (.env)
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

// Asegurarse de que el script no se pueda acceder directamente
if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY', ''));
}

if (!defined('STRIPE_PUBLIC_KEY')) {
    define('STRIPE_PUBLIC_KEY', env('STRIPE_PUBLIC_KEY', ''));
}

// Configuración de Stripe para México
define('STRIPE_CURRENCY', 'MXN');
define('STRIPE_COUNTRY', 'MX');
define('STRIPE_LOCALE', 'es');

// Configuración de webhooks
if (!defined('STRIPE_WEBHOOK_SECRET')) {
    define('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET', ''));
}

// URLs de redirección
define('STRIPE_SUCCESS_URL', env('APP_URL', 'https://quickbite.com.mx') . '/payment/success');
define('STRIPE_CANCEL_URL', env('APP_URL', 'https://quickbite.com.mx') . '/payment/cancel');

// Comisión de la plataforma (10%)
define('PLATFORM_FEE_PERCENTAGE', 10);

// Cargar la biblioteca de Stripe si existe
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Configurar Stripe solo si la clave está definida
    if (!empty(STRIPE_SECRET_KEY)) {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        \Stripe\Stripe::setApiVersion('2023-10-16');
    }
}
