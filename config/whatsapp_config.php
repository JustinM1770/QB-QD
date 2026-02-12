<?php
/**
 * Configuración de WhatsApp Cloud API
 * 
 * SEGURIDAD: Las credenciales se cargan desde variables de entorno (.env)
 * 
 * Para obtener las credenciales:
 * 1. Crear una app en https://developers.facebook.com/
 * 2. Agregar el producto "WhatsApp"
 * 3. Obtener el Phone Number ID y el Access Token
 * 4. Configurar el Webhook URL y el Verify Token
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';

define('WHATSAPP_API_VERSION', 'v21.0');
define('WHATSAPP_PHONE_NUMBER_ID', env('WHATSAPP_PHONE_NUMBER_ID', ''));
define('WHATSAPP_ACCESS_TOKEN', env('WHATSAPP_ACCESS_TOKEN', ''));
define('WHATSAPP_VERIFY_TOKEN', env('WHATSAPP_VERIFY_TOKEN', 'quickbite_webhook_secret'));
define('WHATSAPP_APP_SECRET', env('WHATSAPP_APP_SECRET', ''));

// URL base de la API
define('WHATSAPP_API_URL', 'https://graph.facebook.com/' . WHATSAPP_API_VERSION . '/' . WHATSAPP_PHONE_NUMBER_ID . '/messages');

// Configuración de logging
define('WHATSAPP_LOG_FILE', __DIR__ . '/../logs/whatsapp.log');

// Función helper para logging (sin datos sensibles)
function logWhatsApp($message, $data = null) {
    $logDir = dirname(WHATSAPP_LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        // Sanitizar datos sensibles antes de loguear
        $safeData = $data;
        if (is_array($safeData)) {
            unset($safeData['access_token'], $safeData['password'], $safeData['secret']);
        }
        $logMessage .= "\n" . json_encode($safeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    file_put_contents(WHATSAPP_LOG_FILE, $logMessage, FILE_APPEND);
}

return [
    'phone_number_id' => WHATSAPP_PHONE_NUMBER_ID,
    'access_token' => WHATSAPP_ACCESS_TOKEN,
    'verify_token' => WHATSAPP_VERIFY_TOKEN,
    'app_secret' => WHATSAPP_APP_SECRET,
    'api_url' => WHATSAPP_API_URL,
    'api_version' => WHATSAPP_API_VERSION,
];
