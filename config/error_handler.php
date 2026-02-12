<?php
/**
 * Configuración de Errores para Producción/Desarrollo
 * QuickBite - Sistema de Delivery
 * 
 * Este archivo centraliza la configuración de errores
 * para evitar mostrar información sensible en producción
 */

// Cargar variables de entorno si no están cargadas
if (!defined('ENV_LOADED')) {
    require_once __DIR__ . '/env.php';
}

// Detectar si estamos en producción
$is_production = (env('ENVIRONMENT', 'production') === 'production') ||
                 ($_SERVER['SERVER_NAME'] !== 'localhost' && 
                  $_SERVER['SERVER_NAME'] !== '127.0.0.1');

// Permitir debug temporal con parámetro (solo en desarrollo)
if (isset($_GET['debug']) && !$is_production) {
    $is_production = false;
}

if ($is_production) {
    // PRODUCCIÓN: Ocultar errores al usuario
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    
    // Registrar errores en archivo de log
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    // DESARROLLO: Mostrar todos los errores
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Crear directorio de logs si no existe
$log_dir = __DIR__ . '/../logs';
if (!file_exists($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

// Constantes de niveles de log
define('LOG_LEVEL_DEBUG', 'DEBUG');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_WARN', 'WARN');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_CRITICAL', 'CRITICAL');

/**
 * Función helper para loguear errores de forma estructurada
 * 
 * @param string $message Mensaje del error
 * @param array $context Contexto adicional (sin datos sensibles)
 * @param string $level Nivel de log
 */
function logError($message, $context = [], $level = LOG_LEVEL_ERROR) {
    $log_file = __DIR__ . '/../logs/app_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Sanitizar contexto - remover datos sensibles
    $safe_context = sanitizeLogContext($context);
    
    $log_entry = [
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'context' => $safe_context,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
        'ip' => getClientIP()
    ];
    
    $log_message = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . "\n";
    error_log($log_message, 3, $log_file);
}

/**
 * Función helper para loguear debug (solo en desarrollo)
 */
function logDebug($message, $context = []) {
    global $is_production;
    if (!$is_production) {
        logError($message, $context, LOG_LEVEL_DEBUG);
    }
}

/**
 * Log de información general
 */
function logInfo($message, $context = []) {
    logError($message, $context, LOG_LEVEL_INFO);
}

/**
 * Log de advertencias
 */
function logWarn($message, $context = []) {
    logError($message, $context, LOG_LEVEL_WARN);
}

/**
 * Log de errores críticos
 */
function logCritical($message, $context = []) {
    logError($message, $context, LOG_LEVEL_CRITICAL);
}

/**
 * Sanitizar contexto de log para remover datos sensibles
 */
function sanitizeLogContext($context) {
    if (!is_array($context)) {
        return $context;
    }
    
    $sensitive_keys = [
        'password', 'pass', 'pwd', 'secret', 'token', 'api_key', 'apikey',
        'access_token', 'refresh_token', 'credit_card', 'card_number',
        'cvv', 'cvc', 'pin', 'ssn', 'private_key'
    ];
    
    $sanitized = [];
    foreach ($context as $key => $value) {
        $key_lower = strtolower($key);
        
        // Verificar si la clave contiene información sensible
        $is_sensitive = false;
        foreach ($sensitive_keys as $sensitive) {
            if (strpos($key_lower, $sensitive) !== false) {
                $is_sensitive = true;
                break;
            }
        }
        
        if ($is_sensitive) {
            $sanitized[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            $sanitized[$key] = sanitizeLogContext($value);
        } else {
            $sanitized[$key] = $value;
        }
    }
    
    return $sanitized;
}

/**
 * Obtener IP del cliente (considerando proxies/Cloudflare)
 */
function getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Si hay múltiples IPs, tomar la primera
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    
    return 'unknown';
}

/**
 * Manejador de excepciones no capturadas
 */
set_exception_handler(function($exception) {
    logCritical('Uncaught Exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    // En producción, mostrar página de error genérica
    global $is_production;
    if ($is_production) {
        http_response_code(500);
        include __DIR__ . '/../50x.php';
        exit;
    }
    
    throw $exception; // Re-lanzar en desarrollo
});

/**
 * Manejador de errores fatales
 */
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        logCritical('Fatal Error', [
            'type' => $error['type'],
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});
