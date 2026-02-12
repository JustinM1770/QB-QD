<?php
/**
 * Sistema de Rate Limiting para QuickBite
 * 
 * Protege endpoints críticos contra abuso y ataques de fuerza bruta.
 * Almacena contadores en archivos temporales (compatible sin Redis).
 * 
 * USO:
 * require_once 'config/rate_limit.php';
 * 
 * // En endpoint de login
 * if (!check_rate_limit('login', 5, 60)) { // 5 intentos por minuto
 *     die('Demasiados intentos. Espera un momento.');
 * }
 */

// Directorio para almacenar datos de rate limiting
define('RATE_LIMIT_DIR', __DIR__ . '/../logs/rate_limits');

// Crear directorio si no existe
if (!file_exists(RATE_LIMIT_DIR)) {
    @mkdir(RATE_LIMIT_DIR, 0755, true);
}

/**
 * Obtener IP del cliente (considerando proxies/Cloudflare)
 */
function getRateLimitIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    
    return 'unknown';
}

/**
 * Generar clave única para rate limiting
 * @param string $action Tipo de acción (login, register, etc.)
 * @return string Clave única
 */
function getRateLimitKey($action) {
    $ip = getRateLimitIP();
    // Sanitizar para nombre de archivo seguro
    $safe_ip = preg_replace('/[^a-zA-Z0-9_.]/', '_', $ip);
    $safe_action = preg_replace('/[^a-zA-Z0-9_]/', '_', $action);
    return $safe_action . '_' . $safe_ip;
}

/**
 * Obtener datos de rate limiting
 * @param string $key Clave única
 * @return array Datos del contador
 */
function getRateLimitData($key) {
    $file = RATE_LIMIT_DIR . '/' . $key . '.json';
    
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content) {
            return json_decode($content, true);
        }
    }
    
    return ['count' => 0, 'first_attempt' => time()];
}

/**
 * Guardar datos de rate limiting
 * @param string $key Clave única
 * @param array $data Datos a guardar
 */
function saveRateLimitData($key, $data) {
    $file = RATE_LIMIT_DIR . '/' . $key . '.json';
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Verificar y aplicar rate limiting
 * 
 * @param string $action Nombre de la acción (login, register, payment, etc.)
 * @param int $max_attempts Máximo de intentos permitidos
 * @param int $window_seconds Ventana de tiempo en segundos
 * @return bool True si se permite la acción, False si se bloquea
 */
function check_rate_limit($action, $max_attempts = 5, $window_seconds = 60) {
    $key = getRateLimitKey($action);
    $data = getRateLimitData($key);
    
    $now = time();
    
    // Si la ventana de tiempo expiró, reiniciar contador
    if (($now - $data['first_attempt']) > $window_seconds) {
        $data = ['count' => 0, 'first_attempt' => $now];
    }
    
    // Incrementar contador
    $data['count']++;
    $data['last_attempt'] = $now;
    
    // Guardar datos
    saveRateLimitData($key, $data);
    
    // Verificar si excede el límite
    if ($data['count'] > $max_attempts) {
        // Log del intento bloqueado
        error_log("Rate limit excedido: action=$action, ip=" . getRateLimitIP() . ", count={$data['count']}");
        return false;
    }
    
    return true;
}

/**
 * Obtener tiempo restante de bloqueo
 * @param string $action Nombre de la acción
 * @param int $window_seconds Ventana de tiempo
 * @return int Segundos restantes de bloqueo
 */
function get_rate_limit_remaining($action, $window_seconds = 60) {
    $key = getRateLimitKey($action);
    $data = getRateLimitData($key);
    
    $elapsed = time() - $data['first_attempt'];
    $remaining = $window_seconds - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Resetear rate limit para una acción (ej: después de login exitoso)
 * @param string $action Nombre de la acción
 */
function reset_rate_limit($action) {
    $key = getRateLimitKey($action);
    $file = RATE_LIMIT_DIR . '/' . $key . '.json';
    
    if (file_exists($file)) {
        @unlink($file);
    }
}

/**
 * Limpiar archivos de rate limiting antiguos (ejecutar periódicamente)
 * @param int $max_age_seconds Edad máxima en segundos (default: 1 hora)
 */
function cleanup_rate_limits($max_age_seconds = 3600) {
    $files = glob(RATE_LIMIT_DIR . '/*.json');
    $now = time();
    
    foreach ($files as $file) {
        if (($now - filemtime($file)) > $max_age_seconds) {
            @unlink($file);
        }
    }
}

// Configuraciones predefinidas por tipo de acción
define('RATE_LIMITS', [
    'login' => ['max' => 5, 'window' => 60],           // 5 intentos por minuto
    'register' => ['max' => 3, 'window' => 300],       // 3 registros cada 5 min
    'password_reset' => ['max' => 3, 'window' => 300], // 3 resets cada 5 min
    'payment' => ['max' => 10, 'window' => 60],        // 10 pagos por minuto
    'api_general' => ['max' => 60, 'window' => 60],    // 60 req/min general
    'whatsapp' => ['max' => 30, 'window' => 60]        // 30 mensajes/min
]);

/**
 * Helper para verificar con configuración predefinida
 * @param string $action Nombre de la acción
 * @return bool
 */
function rate_limit($action) {
    $config = RATE_LIMITS[$action] ?? ['max' => 60, 'window' => 60];
    return check_rate_limit($action, $config['max'], $config['window']);
}
