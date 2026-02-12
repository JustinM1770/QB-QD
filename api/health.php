<?php
/**
 * QuickBite - Health Check Endpoint
 *
 * Verifica el estado de todos los servicios críticos del sistema.
 * Usar para: monitoreo, load balancers, alertas.
 *
 * Respuestas:
 * - HTTP 200: Sistema saludable
 * - HTTP 503: Uno o más servicios fallando
 */

// No mostrar errores al cliente
error_reporting(0);
ini_set('display_errors', 0);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Cargar configuración
$projectRoot = dirname(__DIR__);

// Intentar cargar env.php si existe
if (file_exists($projectRoot . '/config/env.php')) {
    require_once $projectRoot . '/config/env.php';
}

// Función helper para env si no existe
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}

// Leer versión
$version = '1.0.0';
if (file_exists($projectRoot . '/VERSION')) {
    $version = trim(file_get_contents($projectRoot . '/VERSION'));
}

/**
 * Verificar conexión a base de datos MySQL
 */
function checkDatabase(): array {
    try {
        $host = env('DB_HOST', 'localhost');
        $name = env('DB_NAME', 'app_delivery');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Verificar que podemos hacer una query simple
        $stmt = $pdo->query("SELECT 1");
        $stmt->fetch();

        // Obtener información adicional
        $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        return [
            'status' => 'healthy',
            'message' => 'Conexión exitosa',
            'details' => [
                'host' => $host,
                'database' => $name,
                'server_version' => $serverVersion
            ]
        ];
    } catch (PDOException $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Error de conexión: ' . $e->getMessage(),
            'details' => null
        ];
    }
}

/**
 * Verificar configuración de Stripe
 */
function checkStripe(): array {
    $secretKey = env('STRIPE_SECRET_KEY', '');
    $publicKey = env('STRIPE_PUBLIC_KEY', '');

    if (empty($secretKey) || empty($publicKey)) {
        return [
            'status' => 'unhealthy',
            'message' => 'Claves de Stripe no configuradas',
            'details' => null
        ];
    }

    // Verificar formato de las claves
    $isLive = strpos($secretKey, 'sk_live_') === 0;
    $isTest = strpos($secretKey, 'sk_test_') === 0;

    if (!$isLive && !$isTest) {
        return [
            'status' => 'unhealthy',
            'message' => 'Formato de clave Stripe inválido',
            'details' => null
        ];
    }

    return [
        'status' => 'healthy',
        'message' => 'Configurado correctamente',
        'details' => [
            'mode' => $isLive ? 'production' : 'test',
            'public_key_prefix' => substr($publicKey, 0, 12) . '...'
        ]
    ];
}

/**
 * Verificar configuración de MercadoPago
 */
function checkMercadoPago(): array {
    $accessToken = env('MP_ACCESS_TOKEN', '');
    $publicKey = env('MP_PUBLIC_KEY', '');

    if (empty($accessToken) || empty($publicKey)) {
        return [
            'status' => 'unhealthy',
            'message' => 'Credenciales de MercadoPago no configuradas',
            'details' => null
        ];
    }

    // Verificar formato básico
    if (strpos($accessToken, 'APP_USR-') !== 0) {
        return [
            'status' => 'warning',
            'message' => 'Formato de token podría ser inválido',
            'details' => null
        ];
    }

    return [
        'status' => 'healthy',
        'message' => 'Configurado correctamente',
        'details' => [
            'public_key_prefix' => substr($publicKey, 0, 15) . '...'
        ]
    ];
}

/**
 * Verificar WhatsApp Bot (puerto 3030)
 */
function checkWhatsAppBot(): array {
    $port = env('WHATSAPP_BOT_PORT', 3030);
    $host = '127.0.0.1';

    $socket = @fsockopen($host, $port, $errno, $errstr, 2);

    if ($socket) {
        fclose($socket);
        return [
            'status' => 'healthy',
            'message' => 'Bot corriendo en puerto ' . $port,
            'details' => [
                'port' => $port,
                'host' => $host
            ]
        ];
    }

    return [
        'status' => 'unhealthy',
        'message' => "Bot no responde en puerto $port: $errstr",
        'details' => [
            'port' => $port,
            'error_code' => $errno
        ]
    ];
}

/**
 * Verificar permisos de escritura en directorios críticos
 */
function checkFileSystem(): array {
    global $projectRoot;

    $directories = [
        'logs' => $projectRoot . '/logs',
        'uploads' => $projectRoot . '/uploads'
    ];

    $issues = [];
    $details = [];

    foreach ($directories as $name => $path) {
        if (!is_dir($path)) {
            $issues[] = "$name: directorio no existe";
            $details[$name] = 'missing';
        } elseif (!is_writable($path)) {
            $issues[] = "$name: sin permisos de escritura";
            $details[$name] = 'not_writable';
        } else {
            $details[$name] = 'ok';
        }
    }

    if (!empty($issues)) {
        return [
            'status' => 'unhealthy',
            'message' => implode(', ', $issues),
            'details' => $details
        ];
    }

    return [
        'status' => 'healthy',
        'message' => 'Todos los directorios accesibles',
        'details' => $details
    ];
}

/**
 * Verificar configuración SMTP
 */
function checkSMTP(): array {
    $host = env('SMTP_HOST', '');
    $user = env('SMTP_USER', '');

    if (empty($host) || empty($user)) {
        return [
            'status' => 'warning',
            'message' => 'SMTP no configurado completamente',
            'details' => null
        ];
    }

    return [
        'status' => 'healthy',
        'message' => 'Configurado',
        'details' => [
            'host' => $host,
            'user' => substr($user, 0, 5) . '...'
        ]
    ];
}

/**
 * Verificar uso de memoria y recursos
 */
function checkResources(): array {
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    $memoryLimitBytes = convertToBytes($memoryLimit);

    $usagePercent = ($memoryLimitBytes > 0)
        ? round(($memoryUsage / $memoryLimitBytes) * 100, 2)
        : 0;

    $status = 'healthy';
    if ($usagePercent > 80) {
        $status = 'warning';
    } elseif ($usagePercent > 95) {
        $status = 'unhealthy';
    }

    return [
        'status' => $status,
        'message' => "Memoria: $usagePercent% usado",
        'details' => [
            'memory_usage' => formatBytes($memoryUsage),
            'memory_limit' => $memoryLimit,
            'usage_percent' => $usagePercent,
            'php_version' => PHP_VERSION
        ]
    ];
}

/**
 * Convertir string de memoria a bytes
 */
function convertToBytes(string $value): int {
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $bytes = (int) $value;

    switch ($unit) {
        case 'g': $bytes *= 1024;
        case 'm': $bytes *= 1024;
        case 'k': $bytes *= 1024;
    }

    return $bytes;
}

/**
 * Formatear bytes a formato legible
 */
function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// ========================================
// EJECUTAR TODAS LAS VERIFICACIONES
// ========================================

$startTime = microtime(true);

$checks = [
    'database' => checkDatabase(),
    'stripe' => checkStripe(),
    'mercadopago' => checkMercadoPago(),
    'whatsapp_bot' => checkWhatsAppBot(),
    'filesystem' => checkFileSystem(),
    'smtp' => checkSMTP(),
    'resources' => checkResources()
];

$endTime = microtime(true);
$responseTime = round(($endTime - $startTime) * 1000, 2);

// Determinar estado general
$overallStatus = 'healthy';
$unhealthyCount = 0;
$warningCount = 0;

foreach ($checks as $check) {
    if ($check['status'] === 'unhealthy') {
        $unhealthyCount++;
    } elseif ($check['status'] === 'warning') {
        $warningCount++;
    }
}

// Base de datos es crítica - si falla, todo el sistema está unhealthy
if ($checks['database']['status'] === 'unhealthy') {
    $overallStatus = 'unhealthy';
} elseif ($unhealthyCount > 0) {
    $overallStatus = 'degraded';
} elseif ($warningCount > 0) {
    $overallStatus = 'warning';
}

// Construir respuesta
$response = [
    'status' => $overallStatus,
    'timestamp' => date('c'),
    'version' => $version,
    'environment' => env('ENVIRONMENT', 'production'),
    'response_time_ms' => $responseTime,
    'checks' => $checks,
    'summary' => [
        'total' => count($checks),
        'healthy' => count(array_filter($checks, fn($c) => $c['status'] === 'healthy')),
        'warning' => $warningCount,
        'unhealthy' => $unhealthyCount
    ]
];

// Establecer código HTTP
$httpCode = match($overallStatus) {
    'healthy' => 200,
    'warning' => 200,
    'degraded' => 503,
    'unhealthy' => 503,
    default => 500
};

http_response_code($httpCode);

// Enviar respuesta
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
