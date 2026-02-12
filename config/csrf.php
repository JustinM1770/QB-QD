<?php
/**
 * Sistema de protección CSRF para QuickBite
 * 
 * USO:
 * 1. En formularios HTML:
 *    <?php echo csrf_field(); ?>
 * 
 * 2. En el procesamiento del formulario:
 *    if (!verify_csrf_token($_POST['csrf_token'])) {
 *        die('Token CSRF inválido');
 *    }
 * 
 * 3. Para AJAX:
 *    const token = document.querySelector('meta[name="csrf-token"]').content;
 *    fetch(url, { headers: { 'X-CSRF-TOKEN': token } });
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generar token CSRF único
 * @return string Token generado
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    // Regenerar token si tiene más de 1 hora
    if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Obtener token CSRF actual
 * @return string Token actual
 */
function get_csrf_token() {
    return generate_csrf_token();
}

/**
 * Verificar token CSRF
 * @param string $token Token a verificar
 * @return bool True si es válido
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar campo hidden de formulario con CSRF
 * @return string HTML del campo hidden
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Generar meta tag para AJAX
 * @return string HTML del meta tag
 */
function csrf_meta() {
    $token = generate_csrf_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}

/**
 * Middleware para verificar CSRF en peticiones POST
 * @return bool|void True si pasa, termina script si falla
 */
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = null;
        
        // Buscar token en POST o en headers (para AJAX)
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            
            // Responder según el tipo de petición
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
            } else {
                echo '<!DOCTYPE html><html><head><title>Error de Seguridad</title></head><body>';
                echo '<h1>Error de Seguridad</h1>';
                echo '<p>La solicitud no pudo ser verificada. Por favor, recarga la página e intenta de nuevo.</p>';
                echo '<a href="javascript:history.back()">Volver</a>';
                echo '</body></html>';
            }
            exit;
        }
    }
    
    return true;
}

/**
 * Regenerar token CSRF (después de operaciones sensibles)
 */
function regenerate_csrf_token() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}
