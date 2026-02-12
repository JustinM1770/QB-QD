<?php
/**
 * Configuración de MercadoPago
 * 
 * SEGURIDAD: Las credenciales se cargan desde variables de entorno (.env)
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

return [
    'public_key' => env('MP_PUBLIC_KEY', ''),
    'access_token' => env('MP_ACCESS_TOKEN', ''),
    'test_mode' => env('ENVIRONMENT') !== 'production',
    'id' => env('MP_APP_ID', ''),
    'country_id' => 'MX',
    
    // Configuración de comisiones
    'fee_percentage' => 0.0399,
    'fixed_fee' => 4.00,
    
    // URLs de producción (ajustadas al dominio actual)
    'success_url' => env('APP_URL', 'https://quickbite.com.mx') . '/confirmacion_pedido.php',
    'failure_url' => env('APP_URL', 'https://quickbite.com.mx') . '/checkout.php',
    'pending_url' => env('APP_URL', 'https://quickbite.com.mx') . '/checkout.php',
    'webhook_url' => env('APP_URL', 'https://quickbite.com.mx') . '/webhooks/mercadopago.php',
    
    // Configuración adicional para debugging
    'debug' => env('ENVIRONMENT') !== 'production',
    'locale' => 'es-MX'
];
