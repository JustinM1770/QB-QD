<?php
/**
 * QuickBite - Google OAuth Callback
 * Procesa la respuesta de Google y crea/autentica al usuario
 */
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';

$database = new Database();
$db = $database->getConnection();

// Configuración
$google_client_id = env('GOOGLE_CLIENT_ID', '');
$google_client_secret = env('GOOGLE_CLIENT_SECRET', '');
$redirect_uri = env('APP_URL', 'https://quickbite.com.mx') . '/auth/google_callback.php';

// Verificar errores de Google
if (isset($_GET['error'])) {
    $_SESSION['auth_error'] = 'Error de autenticación: ' . htmlspecialchars($_GET['error']);
    header('Location: ../login.php');
    exit;
}

// Verificar código y state
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    $_SESSION['auth_error'] = 'Respuesta inválida de Google';
    header('Location: ../login.php');
    exit;
}

// Verificar state (CSRF protection)
if ($_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    $_SESSION['auth_error'] = 'Error de seguridad. Intenta de nuevo.';
    header('Location: ../login.php');
    exit;
}

unset($_SESSION['google_oauth_state']);

$code = $_GET['code'];
$auth_type = $_SESSION['google_auth_type'] ?? 'cliente';
unset($_SESSION['google_auth_type']);

try {
    // Intercambiar código por token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code' => $code,
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $token_response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($token_response, true);

    if (!isset($token_data['access_token'])) {
        throw new Exception('No se pudo obtener el token de acceso');
    }

    // Obtener información del usuario
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($user_info_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $user_response = curl_exec($ch);
    curl_close($ch);

    $google_user = json_decode($user_response, true);

    if (!isset($google_user['email'])) {
        throw new Exception('No se pudo obtener el email de Google');
    }

    $email = $google_user['email'];
    $nombre = $google_user['name'] ?? $google_user['given_name'] ?? 'Usuario';
    $google_id = $google_user['id'];
    $foto = $google_user['picture'] ?? null;

    // Buscar usuario existente
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? OR google_id = ?");
    $stmt->execute([$email, $google_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // Usuario existe - actualizar google_id si es necesario y hacer login
        if (empty($usuario['google_id'])) {
            $stmt = $db->prepare("UPDATE usuarios SET google_id = ?, foto_perfil = COALESCE(foto_perfil, ?) WHERE id_usuario = ?");
            $stmt->execute([$google_id, $foto, $usuario['id_usuario']]);
        }

        // Verificar si el tipo coincide o ajustar
        $tipo_usuario = $usuario['tipo_usuario'];

        // Si intenta entrar como negocio pero es cliente, verificar si tiene negocio
        if ($auth_type === 'negocio' && $tipo_usuario === 'cliente') {
            $stmt = $db->prepare("SELECT id_negocio FROM negocios WHERE id_propietario = ?");
            $stmt->execute([$usuario['id_usuario']]);
            if ($stmt->rowCount() > 0) {
                $tipo_usuario = 'negocio';
                $stmt = $db->prepare("UPDATE usuarios SET tipo_usuario = 'negocio' WHERE id_usuario = ?");
                $stmt->execute([$usuario['id_usuario']]);
            }
        }

        // Iniciar sesión
        $_SESSION['loggedin'] = true;
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['tipo_usuario'] = $tipo_usuario;
        $_SESSION['foto_perfil'] = $foto ?? $usuario['foto_perfil'];

        // Redirigir según tipo
        if ($tipo_usuario === 'negocio') {
            // Verificar si tiene negocio
            $stmt = $db->prepare("SELECT id_negocio, registro_completado FROM negocios WHERE id_propietario = ?");
            $stmt->execute([$usuario['id_usuario']]);
            $negocio = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($negocio) {
                if ($negocio['registro_completado']) {
                    header('Location: ../admin/negocio_configuracion.php');
                } else {
                    header('Location: ../registro_negocio_express.php?step=complete');
                }
            } else {
                // Tiene cuenta pero no negocio, crear negocio básico
                header('Location: ../registro_negocio_express.php?step=complete&from_google=1');
            }
        } elseif ($tipo_usuario === 'repartidor') {
            header('Location: ../admin/repartidor_dashboard.php');
        } else {
            header('Location: ../index.php');
        }
        exit;

    } else {
        // Usuario nuevo - crear cuenta
        $db->beginTransaction();

        try {
            // Crear usuario
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, email, google_id, foto_perfil, tipo_usuario, verificado, fecha_registro)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$nombre, $email, $google_id, $foto, $auth_type]);
            $id_usuario = $db->lastInsertId();

            // Si es negocio, crear negocio básico
            if ($auth_type === 'negocio') {
                $stmt = $db->prepare("
                    INSERT INTO negocios (
                        id_propietario, nombre, email, activo, estado_operativo, registro_completado,
                        tiempo_preparacion_promedio, pedido_minimo, costo_envio, radio_entrega, fecha_creacion
                    ) VALUES (?, ?, ?, 1, 'pendiente', 0, 30, 0, 25, 5, NOW())
                ");
                $stmt->execute([$id_usuario, 'Mi Negocio', $email]);
                $id_negocio = $db->lastInsertId();

                // Horarios por defecto
                for ($dia = 0; $dia <= 6; $dia++) {
                    $activo = ($dia >= 1 && $dia <= 6) ? 1 : 0;
                    $stmt = $db->prepare("
                        INSERT INTO horarios_negocio (id_negocio, dia_semana, hora_apertura, hora_cierre, activo)
                        VALUES (?, ?, '09:00:00', '21:00:00', ?)
                    ");
                    $stmt->execute([$id_negocio, $dia, $activo]);
                }
            }

            // Si es repartidor, crear registro básico
            if ($auth_type === 'repartidor') {
                $stmt = $db->prepare("
                    INSERT INTO repartidores (
                        id_usuario, estado, disponible, calificacion_promedio,
                        total_entregas, fecha_registro
                    ) VALUES (?, 'pendiente', 0, 5.0, 0, NOW())
                ");
                $stmt->execute([$id_usuario]);
            }

            $db->commit();

            // Iniciar sesión
            $_SESSION['loggedin'] = true;
            $_SESSION['id_usuario'] = $id_usuario;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['email'] = $email;
            $_SESSION['tipo_usuario'] = $auth_type;
            $_SESSION['foto_perfil'] = $foto;
            $_SESSION['nuevo_usuario_google'] = true;

            // Redirigir según tipo
            if ($auth_type === 'negocio') {
                header('Location: ../registro_negocio_express.php?step=complete&from_google=1');
            } elseif ($auth_type === 'repartidor') {
                // Redirigir a completar registro de repartidor
                $_SESSION['registro_google_repartidor'] = true;
                header('Location: ../registro_repartidor.php?from_google=1');
            } else {
                header('Location: ../index.php?welcome=1');
            }
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    error_log("Error Google OAuth: " . $e->getMessage());
    $_SESSION['auth_error'] = 'Error al procesar la autenticación. Intenta de nuevo.';
    header('Location: ../login.php');
    exit;
}
