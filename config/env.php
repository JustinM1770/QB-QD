<?php
/**
 * Carga de Variables de Entorno para QuickBite
 * 
 * Este archivo DEBE ser incluido al inicio de cualquier script que necesite
 * acceso a credenciales o configuración sensible.
 * 
 * USO:
 * require_once __DIR__ . '/config/env.php';
 * $db_pass = getenv('DB_PASS');
 */

// Evitar múltiples cargas
if (defined('ENV_LOADED')) {
    return;
}
define('ENV_LOADED', true);

/**
 * Cargar archivo .env manualmente (sin dependencias externas)
 */
function loadEnvFile($path) {
    if (!file_exists($path)) {
        error_log("CRÍTICO: Archivo .env no encontrado en: $path");
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsear línea KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            $value = trim($value, '"\'');
            
            // Establecer en entorno si no existe ya
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    
    return true;
}

// Ruta al archivo .env (un nivel arriba del directorio config)
$envPath = dirname(__DIR__) . '/.env';
loadEnvFile($envPath);

/**
 * Helper para obtener variable de entorno con valor por defecto
 * 
 * @param string $key Nombre de la variable
 * @param mixed $default Valor por defecto si no existe
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    
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
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

/**
 * Verificar que las variables críticas están definidas
 */
function validateRequiredEnvVars() {
    $required = [
        'DB_HOST',
        'DB_NAME', 
        'DB_USER',
        'DB_PASS'
    ];
    
    $missing = [];
    foreach ($required as $var) {
        if (!getenv($var)) {
            $missing[] = $var;
        }
    }
    
    if (!empty($missing)) {
        error_log("CRÍTICO: Variables de entorno faltantes: " . implode(', ', $missing));
        return false;
    }
    
    return true;
}

// Validar variables críticas al cargar
validateRequiredEnvVars();

// Definir constantes de entorno para compatibilidad
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', env('ENVIRONMENT', 'production'));
}

if (!defined('APP_URL')) {
    define('APP_URL', env('APP_URL', 'https://quickbite.com.mx'));
}
