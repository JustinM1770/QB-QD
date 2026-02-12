<?php
/**
 * PHPUnit Bootstrap File
 *
 * Este archivo se carga antes de ejecutar los tests.
 * Configura el autoloader y las variables de entorno de prueba.
 */

// Definir constante para indicar modo de testing
define('PHPUNIT_RUNNING', true);
define('PROJECT_ROOT', dirname(__DIR__));

// Cargar autoloader de Composer
require_once PROJECT_ROOT . '/vendor/autoload.php';

// Cargar variables de entorno (si existe .env.testing, usar ese, sino .env)
$envFile = file_exists(PROJECT_ROOT . '/.env.testing')
    ? '.env.testing'
    : '.env';

if (file_exists(PROJECT_ROOT . '/' . $envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT, $envFile);
    $dotenv->safeLoad();
}

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Configurar reporte de errores para testing
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Función helper para obtener variables de entorno (si no existe)
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }

        // Convertir strings a tipos apropiados
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
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }
}

/**
 * Helper para obtener conexión de base de datos de prueba
 */
function getTestDatabase(): ?PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $host = env('DB_HOST', 'localhost');
            $name = env('DB_NAME', 'app_delivery_test');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');

            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            // En tests unitarios podemos no tener BD disponible
            return null;
        }
    }

    return $pdo;
}

/**
 * Helper para crear mocks de PDO
 */
function createMockPDO(): PDO {
    return new class extends PDO {
        public function __construct() {
            // No llamar al constructor padre para evitar conexión real
        }
    };
}
