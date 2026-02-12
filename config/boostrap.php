<?php
/**
 * config/bootstrap.php
 * Inicializa la aplicación y carga configuraciones
 * INCLUIR ESTE ARCHIVO ANTES DE database.php EN TODOS LOS SCRIPTS
 */

// Cargar variables de entorno primero
require_once __DIR__ . '/env.php';

// Detectar si estamos en desarrollo o producción
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$environment = env('ENVIRONMENT', $is_local ? 'development' : 'production');
define('IS_DEVELOPMENT', $environment === 'development');
define('IS_PRODUCTION', $environment === 'production');

// ============================================
// SUPRESIÓN DE ERRORES EN PRODUCCIÓN (OWASP)
// ============================================
if (IS_PRODUCTION) {
    // En producción: NO mostrar errores al usuario (previene fuga de información)
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
} else {
    // En desarrollo: mostrar errores para debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}

// ============================================
// CONFIGURACIÓN DE SESIONES SEGURAS (OWASP)
// ============================================
// Solo configurar si la sesión no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Detectar si estamos en HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // Configurar parámetros de cookie de sesión seguros
    session_set_cookie_params([
        'lifetime' => 0,                    // Cookie de sesión (expira al cerrar navegador)
        'path' => '/',                      // Disponible en todo el sitio
        'domain' => '',                     // Dominio actual
        'secure' => $isHttps,               // Solo HTTPS en producción
        'httponly' => true,                 // No accesible desde JavaScript (previene XSS)
        'samesite' => 'Strict'              // Previene CSRF
    ]);
    
    // Configuraciones adicionales de seguridad de sesión
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.use_strict_mode', 1);           // Rechaza IDs de sesión no inicializados
    ini_set('session.cookie_samesite', 'Strict');
    
    // Regenerar ID de sesión periódicamente (previene session fixation)
    ini_set('session.gc_maxlifetime', 3600);         // 1 hora máximo
}

// Cargar autoloader de Composer si existe
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Cargar variables de entorno
    if (class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        } catch (Exception $e) {
            error_log("Error cargando .env: " . $e->getMessage());
        }
    }
}

// Función helper para obtener variables de entorno
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convertir strings booleanos
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        return $value;
    }
}

// Configuración de timezone
date_default_timezone_set('America/Mexico_City');

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', !IS_DEVELOPMENT); // Solo HTTPS en producción

// Definir constantes de la aplicación
define('APP_URL', env('APP_URL', 'http://localhost:3000'));
define('PROJECT_ROOT', env('PROJECT_ROOT', __DIR__ . '/..'));

// Stripe Configuration
define('STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY', ''));
define('STRIPE_PUBLIC_KEY', env('STRIPE_PUBLIC_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET', ''));

// Validar configuración de Stripe si se usa wallet
if (isset($_GET['wallet']) || isset($_POST['wallet']) || strpos($_SERVER['REQUEST_URI'] ?? '', 'wallet') !== false) {
    if (empty(STRIPE_SECRET_KEY)) {
        error_log("ADVERTENCIA: STRIPE_SECRET_KEY no configurado");
        if (IS_DEVELOPMENT) {
            die("Error: Stripe no está configurado. Por favor configura las claves en .env");
        }
    }
}

// Función para log seguro
if (!function_exists('log_info')) {
    function log_info($message, $context = []) {
        $log_message = date('Y-m-d H:i:s') . " [INFO] " . $message;
        if (!empty($context)) {
            $log_message .= " " . json_encode($context);
        }
        error_log($log_message);
    }
}

if (!function_exists('log_error')) {
    function log_error($message, $context = []) {
        $log_message = date('Y-m-d H:i:s') . " [ERROR] " . $message;
        if (!empty($context)) {
            $log_message .= " " . json_encode($context);
        }
        error_log($log_message);
    }
}

// Headers de seguridad
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    
    if (!IS_DEVELOPMENT) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

return true;
?>