<?php
/**
 * QuickBite - Login con Google OAuth 2.0
 * Maneja autenticación de usuarios y negocios con Google
 */
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';

// Configuración de Google OAuth
$google_client_id = env('GOOGLE_CLIENT_ID', '');
$google_client_secret = env('GOOGLE_CLIENT_SECRET', '');
$redirect_uri = env('APP_URL', 'https://quickbite.com.mx') . '/auth/google_callback.php';

// Tipo de usuario (cliente o negocio)
$type = isset($_GET['type']) ? $_GET['type'] : 'cliente';
$_SESSION['google_auth_type'] = $type;

// Si no hay credenciales configuradas, mostrar mensaje
if (empty($google_client_id) || empty($google_client_secret)) {
    $_SESSION['auth_error'] = 'Login con Google no está configurado. Por favor usa email y contraseña.';

    if ($type === 'negocio') {
        header('Location: ../registro_negocio_express.php');
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// Generar state para seguridad CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Construir URL de autenticación de Google
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $google_client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account'
]);

// Redirigir a Google
header('Location: ' . $auth_url);
exit;
