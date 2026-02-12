<?php
/**
 * Sistema de Logs de Auditoria para QuickBite
 *
 * Registra acciones criticas de usuarios para seguridad y compliance.
 * Almacena en base de datos y archivos de log.
 *
 * USO:
 * require_once 'config/audit_log.php';
 *
 * audit_log('login', 'Usuario inicio sesion exitosamente');
 * audit_log('payment', 'Pago procesado', ['monto' => 150.00, 'metodo' => 'stripe']);
 */

// Cargar dependencias
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

// Directorio de logs de auditoria
define('AUDIT_LOG_DIR', __DIR__ . '/../logs/audit');

// Crear directorio si no existe
if (!file_exists(AUDIT_LOG_DIR)) {
    @mkdir(AUDIT_LOG_DIR, 0755, true);
}

// Tipos de eventos de auditoria
define('AUDIT_TYPES', [
    'login' => 'Inicio de sesion',
    'logout' => 'Cierre de sesion',
    'login_failed' => 'Intento de login fallido',
    'register' => 'Registro de usuario',
    'password_change' => 'Cambio de contrasena',
    'password_reset' => 'Restablecimiento de contrasena',
    'profile_update' => 'Actualizacion de perfil',
    'order_create' => 'Creacion de pedido',
    'order_update' => 'Actualizacion de pedido',
    'order_cancel' => 'Cancelacion de pedido',
    'payment' => 'Procesamiento de pago',
    'payment_failed' => 'Pago fallido',
    'refund' => 'Reembolso procesado',
    'admin_action' => 'Accion administrativa',
    'data_export' => 'Exportacion de datos',
    'data_delete' => 'Eliminacion de datos',
    'permission_change' => 'Cambio de permisos',
    'config_change' => 'Cambio de configuracion',
    'security_alert' => 'Alerta de seguridad'
]);

/**
 * Obtener IP del cliente
 */
function audit_get_ip() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return filter_var($ip, FILTER_VALIDATE_IP) ?: 'unknown';
        }
    }

    return 'unknown';
}

/**
 * Obtener informacion del User Agent
 */
function audit_get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

/**
 * Obtener ID de usuario actual
 */
function audit_get_user_id() {
    if (session_status() === PHP_SESSION_NONE) {
        return null;
    }

    return $_SESSION['id_usuario'] ?? $_SESSION['id_negocio'] ?? $_SESSION['id_repartidor'] ?? null;
}

/**
 * Obtener tipo de usuario actual
 */
function audit_get_user_type() {
    if (session_status() === PHP_SESSION_NONE) {
        return 'guest';
    }

    return $_SESSION['tipo_usuario'] ?? 'guest';
}

/**
 * Registrar evento de auditoria
 *
 * @param string $event_type Tipo de evento (ver AUDIT_TYPES)
 * @param string $description Descripcion del evento
 * @param array $context Datos adicionales (sin info sensible)
 * @param int|null $user_id ID del usuario afectado (si diferente al actual)
 * @return bool Exito del registro
 */
function audit_log($event_type, $description, $context = [], $user_id = null) {
    $timestamp = date('Y-m-d H:i:s');
    $date = date('Y-m-d');

    $entry = [
        'timestamp' => $timestamp,
        'event_type' => $event_type,
        'event_label' => AUDIT_TYPES[$event_type] ?? $event_type,
        'description' => $description,
        'user_id' => $user_id ?? audit_get_user_id(),
        'user_type' => audit_get_user_type(),
        'ip_address' => audit_get_ip(),
        'user_agent' => audit_get_user_agent(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'context' => sanitize_audit_context($context)
    ];

    // Guardar en archivo
    $log_file = AUDIT_LOG_DIR . "/audit_$date.log";
    $log_line = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
    $file_result = @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);

    // Intentar guardar en base de datos
    $db_result = audit_log_to_database($entry);

    return $file_result !== false || $db_result;
}

/**
 * Sanitizar contexto de auditoria (remover datos sensibles)
 */
function sanitize_audit_context($context) {
    if (!is_array($context)) {
        return $context;
    }

    $sensitive_keys = [
        'password', 'pass', 'pwd', 'secret', 'token', 'api_key', 'apikey',
        'access_token', 'refresh_token', 'credit_card', 'card_number',
        'cvv', 'cvc', 'pin', 'ssn', 'private_key', 'tarjeta', 'numero_tarjeta'
    ];

    $sanitized = [];
    foreach ($context as $key => $value) {
        $key_lower = strtolower($key);

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
            $sanitized[$key] = sanitize_audit_context($value);
        } else {
            $sanitized[$key] = $value;
        }
    }

    return $sanitized;
}

/**
 * Guardar log en base de datos
 */
function audit_log_to_database($entry) {
    try {
        $pdo = new PDO(
            "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
            env('DB_USER'),
            env('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                event_label VARCHAR(100),
                description TEXT,
                user_id INT UNSIGNED,
                user_type VARCHAR(20),
                ip_address VARCHAR(45),
                user_agent TEXT,
                request_uri VARCHAR(500),
                request_method VARCHAR(10),
                context JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_event_type (event_type),
                INDEX idx_user_id (user_id),
                INDEX idx_ip_address (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
            (timestamp, event_type, event_label, description, user_id, user_type,
             ip_address, user_agent, request_uri, request_method, context)
            VALUES
            (:timestamp, :event_type, :event_label, :description, :user_id, :user_type,
             :ip_address, :user_agent, :request_uri, :request_method, :context)
        ");

        $stmt->execute([
            ':timestamp' => $entry['timestamp'],
            ':event_type' => $entry['event_type'],
            ':event_label' => $entry['event_label'],
            ':description' => $entry['description'],
            ':user_id' => $entry['user_id'],
            ':user_type' => $entry['user_type'],
            ':ip_address' => $entry['ip_address'],
            ':user_agent' => mb_substr($entry['user_agent'], 0, 500),
            ':request_uri' => mb_substr($entry['request_uri'], 0, 500),
            ':request_method' => $entry['request_method'],
            ':context' => json_encode($entry['context'])
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Error guardando audit log en BD: " . $e->getMessage());
        return false;
    }
}

/**
 * Buscar logs de auditoria
 *
 * @param array $filters Filtros de busqueda
 * @param int $limit Limite de resultados
 * @param int $offset Offset para paginacion
 * @return array Logs encontrados
 */
function audit_search($filters = [], $limit = 100, $offset = 0) {
    try {
        $pdo = new PDO(
            "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
            env('DB_USER'),
            env('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $where = [];
        $params = [];

        if (!empty($filters['event_type'])) {
            $where[] = "event_type = :event_type";
            $params[':event_type'] = $filters['event_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $where[] = "ip_address = :ip_address";
            $params[':ip_address'] = $filters['ip_address'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "timestamp >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "timestamp <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT * FROM audit_logs $whereClause ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Error buscando audit logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Limpiar logs antiguos
 *
 * @param int $days_to_keep Dias a mantener (default: 90)
 * @return int Registros eliminados
 */
function audit_cleanup($days_to_keep = 90) {
    try {
        $pdo = new PDO(
            "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
            env('DB_USER'),
            env('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_to_keep days"));

        $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE timestamp < :cutoff");
        $stmt->execute([':cutoff' => $cutoff_date]);

        $deleted = $stmt->rowCount();

        // Limpiar archivos de log antiguos
        $files = glob(AUDIT_LOG_DIR . '/audit_*.log');
        $cutoff_timestamp = strtotime("-$days_to_keep days");

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_timestamp) {
                @unlink($file);
            }
        }

        return $deleted;

    } catch (Exception $e) {
        error_log("Error limpiando audit logs: " . $e->getMessage());
        return 0;
    }
}

// Funciones helper para eventos comunes

function audit_login_success($user_id = null) {
    audit_log('login', 'Inicio de sesion exitoso', [], $user_id);
}

function audit_login_failed($identifier, $reason = 'Credenciales invalidas') {
    audit_log('login_failed', $reason, ['identifier' => $identifier]);
}

function audit_logout() {
    audit_log('logout', 'Sesion cerrada por el usuario');
}

function audit_register($user_id, $user_type = 'cliente') {
    audit_log('register', "Nuevo registro de $user_type", ['user_type' => $user_type], $user_id);
}

function audit_password_change($user_id = null) {
    audit_log('password_change', 'Contrasena actualizada', [], $user_id);
}

function audit_order_create($order_id, $total) {
    audit_log('order_create', "Pedido #$order_id creado", ['order_id' => $order_id, 'total' => $total]);
}

function audit_payment($order_id, $amount, $method, $status = 'success') {
    audit_log(
        $status === 'success' ? 'payment' : 'payment_failed',
        "Pago de pedido #$order_id",
        ['order_id' => $order_id, 'amount' => $amount, 'method' => $method, 'status' => $status]
    );
}

function audit_admin_action($action, $details = []) {
    audit_log('admin_action', $action, $details);
}

function audit_security_alert($description, $context = []) {
    audit_log('security_alert', $description, $context);
}
