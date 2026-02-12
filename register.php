<?php
// Manejador de errores centralizado
require_once __DIR__ . '/config/error_handler.php';

// Iniciar sesión
session_start();

// Incluir sistema de protección CSRF
require_once __DIR__ . '/config/csrf.php';

// Si el usuario ya está logueado, redirigir a la página principal
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';

// Incluir Referral si existe
$referral_disponible = false;
if (file_exists('api/Referral.php')) {
    require_once 'api/Referral.php';
    $referral_disponible = true;
}

// PHPMailer - Instalación manual
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

// Cargar variables de entorno
require_once 'config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Configuración de email - Cargada desde variables de entorno (SEGURO)
$emailConfig = [
    'host' => env('SMTP_HOST', 'smtp.hostinger.com'),
    'port' => (int) env('SMTP_PORT', 587),
    'username' => env('SMTP_USER', 'contacto@quickbite.com.mx'),
    'password' => env('SMTP_PASS', ''),  // ✅ SEGURO: Cargado desde .env
    'from_email' => env('SMTP_FROM_EMAIL', 'contacto@quickbite.com.mx'),
    'from_name' => env('SMTP_FROM_NAME', 'QuickBite')
];

// Variables para mensajes y valores previos
$nombre = $apellido = $email = $telefono = $password = $confirm_password = $codigo_referido = "";
$nombre_err = $apellido_err = $email_err = $telefono_err = $password_err = $confirm_password_err = $codigo_referido_err = "";
$register_success = false;
$verification_sent = false;

// Verificar si hay código de referido en la URL
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $codigo_referido = trim($_GET['ref']);
}

// Función para enviar email de verificación
function enviarEmailVerificacion($email, $codigo, $nombre) {
    global $emailConfig;
    
    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP para Hostinger
        $mail->isSMTP();
        $mail->Host       = $emailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['username'];
        $mail->Password   = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $emailConfig['port'];
        
        // Configuración SSL optimizada para Hostinger
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Configuración del email
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($email, $nombre);
        $mail->addReplyTo($emailConfig['from_email'], $emailConfig['from_name']);

        // Contenido del email
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verificación de cuenta - QuickBite';
        $mail->Body = crearTemplateVerificacion($codigo, $nombre);

        // Versión de texto plano como respaldo
        $mail->AltBody = "Hola $nombre,\n\nTu código de verificación es: $codigo\n\nEste código expira en 15 minutos.\n\nSaludos,\nEquipo QuickBite";

        // Enviar email
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Error enviando email de verificación: " . $mail->ErrorInfo);
        return false;
    }
}

// Template del email de verificación (mismo que antes)
function crearTemplateVerificacion($codigo, $nombre) {
    return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verificación de Cuenta - QuickBite</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: Inter, -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                padding: 20px;
                line-height: 1.6;
                color: #334155;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 8px 32px rgba(15, 23, 42, 0.06);
                border: 1px solid #e2e8f0;
            }
            .header {
                background: linear-gradient(135deg, #0165FF 0%, #0052cc 100%);
                padding: 40px 30px;
                text-align: center;
                color: white;
            }
            .logo {
                font-size: 32px;
                font-weight: 600;
                margin-bottom: 8px;
            }
            .header-subtitle {
                opacity: 0.9;
                font-size: 16px;
            }
            .content {
                padding: 40px 30px;
            }
            .greeting {
                font-size: 20px;
                color: #0f172a;
                margin-bottom: 20px;
                font-weight: 600;
            }
            .message {
                color: #475569;
                font-size: 16px;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .code-container {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 30px;
                text-align: center;
                margin: 30px 0;
            }
            .code-label {
                color: #64748b;
                font-size: 14px;
                margin-bottom: 10px;
                font-weight: 500;
            }
            .code {
                font-size: 32px;
                font-weight: 700;
                color: #0165FF;
                letter-spacing: 6px;
                font-family: "DM Sans", monospace;
            }
            .expiry {
                background: rgba(239, 68, 68, 0.06);
                color: #dc2626;
                padding: 16px;
                border-radius: 8px;
                font-size: 14px;
                margin: 20px 0;
                border: 1px solid rgba(239, 68, 68, 0.2);
                font-weight: 500;
            }
            .welcome-note {
                background: rgba(16, 185, 129, 0.06);
                border: 1px solid rgba(16, 185, 129, 0.2);
                color: #059669;
                padding: 16px;
                border-radius: 8px;
                font-size: 14px;
                margin: 20px 0;
                line-height: 1.5;
            }
            .footer {
                background: #f8fafc;
                padding: 30px;
                text-align: center;
                border-top: 1px solid #e2e8f0;
                color: #64748b;
                font-size: 14px;
            }
            @media (max-width: 600px) {
                .container { margin: 10px; border-radius: 12px; }
                .header, .content { padding: 24px 20px; }
                .code { font-size: 24px; letter-spacing: 4px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">QuickBite</div>
                <div class="header-subtitle">Tu comida favorita, entregada rápido</div>
            </div>
            
            <div class="content">
                <div class="greeting">¡Bienvenido ' . htmlspecialchars($nombre) . '!</div>
                
                <div class="message">
                    Gracias por registrarte en QuickBite. Para completar tu registro y activar tu cuenta, 
                    necesitamos verificar tu dirección de email con el siguiente código:
                </div>
                
                <div class="code-container">
                    <div class="code-label">Código de Verificación</div>
                    <div class="code">' . $codigo . '</div>
                </div>
                
                <div class="expiry">
                    ⏰ Este código expira en 15 minutos por seguridad.
                </div>
                
                <div class="welcome-note">
                    🎉 <strong>¡Ya casi terminamos!</strong> Una vez verificada tu cuenta, podrás:
                    <ul style="margin-top: 8px; padding-left: 20px;">
                        <li>Explorar nuestro menú completo</li>
                        <li>Realizar pedidos en línea</li>
                        <li>Rastrear tus entregas en tiempo real</li>
                        <li>Guardar tus platillos favoritos</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer">
                Este email fue enviado por QuickBite<br>
                Si no solicitaste esta cuenta, puedes ignorar este email<br><br>
                © ' . date('Y') . ' QuickBite. Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>';
}

// Procesar datos del formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validar token CSRF antes de procesar
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Error de seguridad: Token CSRF inválido. Por favor recarga la página e intenta de nuevo.');
    }
    
    // Validar nombre
    if (empty(trim($_POST["nombre"]))) {
        $nombre_err = "Por favor ingresa tu nombre.";
    } elseif (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", trim($_POST["nombre"]))) {
        $nombre_err = "El nombre solo puede contener letras y espacios.";
    } else {
        $nombre = trim($_POST["nombre"]);
    }
    
    // Validar apellido
    if (empty(trim($_POST["apellido"]))) {
        $apellido_err = "Por favor ingresa tu apellido.";
    } elseif (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", trim($_POST["apellido"]))) {
        $apellido_err = "El apellido solo puede contener letras y espacios.";
    } else {
        $apellido = trim($_POST["apellido"]);
    }
    
    // Validar email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor ingresa tu email.";
    } else {
        $email = trim($_POST["email"]);
        
        // Verificar que sea un email válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Formato de email inválido.";
        } else {
            // Conectar a BD
            $database = new Database();
            $db = $database->getConnection();
            
            // Crear objeto Usuario
            $usuario = new Usuario($db);
            $usuario->email = $email;
            
            // Verificar si el email ya existe
            if ($usuario->emailExiste()) {
                $email_err = "Este email ya está registrado.";
            }
        }
    }
    
    // Validar teléfono
    if (empty(trim($_POST["telefono"]))) {
        $telefono_err = "Por favor ingresa tu teléfono.";
    } elseif (!preg_match("/^[0-9]{10}$/", trim($_POST["telefono"]))) {
        $telefono_err = "El teléfono debe tener 10 dígitos.";
    } else {
        $telefono = trim($_POST["telefono"]);
    }
    
    // Validar contraseña
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor ingresa una contraseña.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validar confirmación de contraseña
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Por favor confirma la contraseña.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Las contraseñas no coinciden.";
        }
    }
    
    // Validar código de referido (opcional)
    $id_usuario_referente = null;
    if (!empty(trim($_POST["codigo_referido"]))) {
        $codigo_referido = trim($_POST["codigo_referido"]);
        
        if ($referral_disponible) {
            $referral = new Referral($db);
            $id_usuario_referente = $referral->verificarCodigoReferido($codigo_referido);
            
            if (!$id_usuario_referente) {
                $codigo_referido_err = "Código de referido inválido.";
            }
        } else {
            $codigo_referido_err = "Sistema de referidos no disponible.";
        }
    } else {
        $codigo_referido = trim($_POST["codigo_referido"]);
    }
    
    // Verificar errores antes de insertar en la base de datos
    if (empty($nombre_err) && empty($apellido_err) && empty($email_err) && empty($telefono_err) && empty($password_err) && empty($confirm_password_err) && empty($codigo_referido_err)) {
        
        // Conectar a BD si aún no está conectado
        if (!isset($db)) {
            $database = new Database();
            $db = $database->getConnection();
            $usuario = new Usuario($db);
        }
        
        // Establecer propiedades del usuario
        $usuario->nombre = $nombre;
        $usuario->apellido = $apellido;
        $usuario->email = $email;
        $usuario->telefono = $telefono;
        $usuario->password = $password;
        $usuario->tipo_usuario = 'cliente';
        // Generar código de verificación de 6 dígitos
        $codigo_verificacion = sprintf("%06d", mt_rand(1, 999999));
        $usuario->verification_code = $codigo_verificacion;
        $usuario->is_verified = 0; // Requiere verificación de email
        $usuario->activo = 1;

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // Registrar usuario
            if ($usuario->registrar()) {
                $nuevo_usuario_id = $db->lastInsertId();

                // Registrar referido si aplica
                if ($id_usuario_referente && $referral_disponible) {
                    $referral = new Referral($db);
                    $referral->id_usuario_referente = $id_usuario_referente;
                    $referral->id_usuario_referido = $nuevo_usuario_id;
                    $referral->addReferral();
                }

                // Enviar email de verificación
                $email_enviado = enviarEmailVerificacion($email, $codigo_verificacion, $nombre);

                if (!$email_enviado) {
                    error_log("No se pudo enviar email de verificación a: $email");
                }

                $db->commit();
                $register_success = true;
                $verification_sent = $email_enviado;

                // Guardar email en sesión para la página de verificación
                $_SESSION['email_verification'] = $email;

                // Limpiar datos del formulario
                $nombre = $apellido = $email = $telefono = $password = $confirm_password = $codigo_referido = "";

            } else {
                $db->rollback();
                $email_err = "Error al registrar el usuario.";
            }
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en registro: " . $e->getMessage());
            $email_err = "Ocurrió un error. Inténtalo de nuevo más tarde.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>Registro - QuickBite</title>
    
    <!-- Fonts: Inter and DM Sans -->
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <meta name="theme-color" content="#0165FF">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@700&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        /* Forzar modo claro - sin modo oscuro */
        :root {
            color-scheme: light only;
        }
        
        :root {
            --primary: #0165FF;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --success: #10b981;
            --gradient: linear-gradient(135deg, #0165FF 0%, #0165FF 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
                 /* Logo QuickBite con League Spartan */
            .qb-logo {
            font-family: 'League Spartan', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: -0.01em;
            }

            .qb-logo .quick {
            color: var(--light);
            }

            .qb-logo .bite {
            color: #FFD700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        .auth-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .auth-card {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 30px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .auth-header img {
            height: 70px;
            margin-bottom: 20px;
        }

        .auth-title {
            font-size: 1.8rem;
            color: var(--accent);
            margin-bottom: 5px;
        }

        .auth-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: var(--accent);
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.15);
        }

        .btn-primary {
            background: var(--gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 0;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: var(--primary);
            transform: translateY(-1px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .invalid-feedback {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }

        .success-message {
            text-align: center;
            padding: 30px;
            background-color: rgba(16, 185, 129, 0.1);
            border-radius: 15px;
            margin-bottom: 20px;
            border: 2px solid rgba(16, 185, 129, 0.2);
        }

        .success-message i {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 20px;
            animation: bounceIn 0.6s ease;
        }

        .success-message h3 {
            color: var(--success);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .success-message p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .btn-success {
            background-color: var(--success);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            color: white;
        }

        .verification-info {
            background-color: rgba(1, 101, 255, 0.1);
            border: 1px solid rgba(1, 101, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .verification-info i {
            color: var(--primary);
            margin-right: 8px;
        }

        .verification-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        
        .password-requirements .fa-circle {
            font-size: 8px;
            color: #ccc;
        }
        
        .password-requirements .fa-check-circle {
            font-size: 12px;
        }
        
        .password-strength {
            margin-top: 8px;
        }
        
        .password-strength .progress {
            background-color: #e9ecef;
            border-radius: 4px;
        }
        
        #togglePassword, #toggleConfirmPassword {
            background: none;
            border: none;
            padding: 0 12px;
        }
        
        #togglePassword:hover, #toggleConfirmPassword:hover {
            color: var(--primary) !important;
        }

        /* Estilo especial para campo de referido */
        .referral-field {
            position: relative;
        }

        .referral-field .form-control {
            padding-left: 45px;
        }

        .referral-field .referral-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.1rem;
        }

        .referral-info {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #059669;
        }

        .referral-info i {
            margin-right: 6px;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .form-control.is-invalid {
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.15);
        }

        .form-control.is-valid {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

/* =======================================================
   MODO OSCURO - REGISTER.PHP
   ======================================================= */
@media (prefers-color-scheme: dark) {
    body {
        background-color: #000000 !important;
    }

    .auth-container {
        background: #000000;
    }

    .auth-card {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .auth-header h1, .auth-title {
        color: #fff !important;
    }

    .auth-header p {
        color: #888 !important;
    }

    .form-label {
        color: #e0e0e0 !important;
    }

    .form-control {
        background: #1a1a1a !important;
        border-color: #333 !important;
        color: #fff !important;
    }

    .form-control::placeholder {
        color: #666 !important;
    }

    .form-control:focus {
        border-color: var(--primary) !important;
        background: #1a1a1a !important;
    }

    .form-check-label {
        color: #aaa !important;
    }

    .auth-footer, .auth-link {
        color: #888 !important;
    }

    .auth-footer a, .auth-link a {
        color: var(--primary) !important;
    }

    .qb-logo .quick {
        color: #fff !important;
    }

    .alert {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }

    .password-requirements {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }

    .password-requirements li {
        color: #aaa !important;
    }
}

/* Soporte para data-theme="dark" */
[data-theme="dark"] body,
html.dark-mode body {
    background-color: #000000 !important;
}

[data-theme="dark"] .auth-container,
html.dark-mode .auth-container {
    background: #000000;
}

[data-theme="dark"] .auth-card,
html.dark-mode .auth-card {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .auth-header h1,
[data-theme="dark"] .auth-title,
html.dark-mode .auth-header h1,
html.dark-mode .auth-title {
    color: #fff !important;
}

[data-theme="dark"] .auth-header p,
html.dark-mode .auth-header p {
    color: #888 !important;
}

[data-theme="dark"] .form-label,
html.dark-mode .form-label {
    color: #e0e0e0 !important;
}

[data-theme="dark"] .form-control,
html.dark-mode .form-control {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .form-check-label,
html.dark-mode .form-check-label {
    color: #aaa !important;
}

[data-theme="dark"] .auth-footer,
[data-theme="dark"] .auth-link,
html.dark-mode .auth-footer,
html.dark-mode .auth-link {
    color: #888 !important;
}

[data-theme="dark"] .qb-logo .quick,
html.dark-mode .qb-logo .quick {
    color: #fff !important;
}

[data-theme="dark"] .password-requirements,
html.dark-mode .password-requirements {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .password-requirements li,
html.dark-mode .password-requirements li {
    color: #aaa !important;
}
    </style>
</head>
<body>
    
    <div class="auth-container">
        <div class="qb-logo">
                    <span class="quick">Quick</span><span class="bite">Bite</span>
                </div>
        <div class="auth-card">
            <?php if ($register_success): ?>
                <!-- Mensaje de éxito con verificación -->
                <div class="success-message">
                    <i class="fas fa-envelope-circle-check"></i>
                    <h3>¡Registro exitoso!</h3>
                    <p>Tu cuenta ha sido creada correctamente. Hemos enviado un código de verificación de 6 dígitos a:</p>
                    <p><strong><?php echo htmlspecialchars($_SESSION['email_verification'] ?? ''); ?></strong></p>
                    <p>Por favor revisa tu bandeja de entrada y tu carpeta de spam.</p>

                    <a href="verify_email.php" class="btn btn-success">
                        <i class="fas fa-shield-check me-2"></i>
                        Verificar mi cuenta
                    </a>

                    <div class="verification-info">
                        <i class="fas fa-info-circle"></i>
                        <p>El código de verificación expira en 15 minutos. Si no lo recibes, podrás solicitar uno nuevo desde la página de verificación.</p>
                    </div>
                    <?php if (!$verification_sent): ?>
                    <div class="alert alert-warning mt-3" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #d97706; border-radius: 10px; padding: 12px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Hubo un problema al enviar el correo. Puedes solicitar un nuevo código desde la página de verificación.
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                
                <div class="auth-header">
                    
                    <h1 class="auth-title">Crear cuenta</h1>
                    <p class="auth-subtitle">Regístrate para comenzar a pedir</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate id="registerForm">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" name="nombre" id="nombre" class="form-control <?php echo (!empty($nombre_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $nombre; ?>" required>
                                <div class="invalid-feedback"><?php echo $nombre_err; ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" name="apellido" id="apellido" class="form-control <?php echo (!empty($apellido_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $apellido; ?>" required>
                                <div class="invalid-feedback"><?php echo $apellido_err; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" required>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control <?php echo (!empty($telefono_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $telefono; ?>" placeholder="10 dígitos" required>
                        <div class="invalid-feedback"><?php echo $telefono_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="position-relative">
                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" minlength="6" required>
                            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="togglePassword" style="z-index:10;">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        <div class="password-requirements mt-2" id="passwordReqs">
                            <small class="d-block"><i class="fas fa-circle me-1" id="reqLength"></i> Mínimo 6 caracteres</small>
                        </div>
                        <div class="password-strength mt-1" id="passwordStrength" style="display:none;">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="strengthBar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted" id="strengthText"></small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <div class="position-relative">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" minlength="6" required>
                            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" id="toggleConfirmPassword" style="z-index:10;">
                                <i class="fas fa-eye" id="eyeIconConfirm"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                        <div id="passwordMatchIndicator" style="display:none;" class="mt-1">
                            <small><i class="fas fa-check-circle text-success me-1"></i> Las contraseñas coinciden</small>
                        </div>
                    </div>
                    
                    <!-- Campo de código de referido -->
                    <div class="form-group">
                        <label for="codigo_referido" class="form-label">Código de referido <span class="text-muted">(opcional)</span></label>
                        <div class="referral-field">
                            <i class="fas fa-users referral-icon"></i>
                            <input type="text" name="codigo_referido" id="codigo_referido" class="form-control <?php echo (!empty($codigo_referido_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $codigo_referido; ?>" placeholder="Ingresa el código de tu amigo">
                            <div class="invalid-feedback"><?php echo $codigo_referido_err; ?></div>
                        </div>
                        <?php if (empty($codigo_referido_err) && !empty($codigo_referido)): ?>
                            <div class="referral-info">
                                <i class="fas fa-gift"></i>
                                Te registraste con un código de referido. Ambos obtendrán beneficios cuando realices compras.
                            </div>
                        <?php else: ?>
                            <div class="referral-info">
                                <i class="fas fa-info-circle"></i>
                                Si tienes un código de referido de un amigo, ingrésalo para obtener beneficios especiales.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                Acepto los <a href="terms.html" target="_blank">Términos y Condiciones</a> y la <a href="privacy.html" target="_blank">Política de Privacidad</a>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-3" id="submitBtn">
                        <span class="loading-spinner" id="loadingSpinner"></span>
                        <i class="fas fa-user-plus me-2" id="submitIcon"></i>
                        <span id="submitText">Crear cuenta</span>
                    </button>
                </form>

                <div style="display: flex; align-items: center; gap: 15px; margin: 20px 0; color: #9ca3af; font-size: 0.85rem;">
                    <span style="flex: 1; height: 1px; background: #e5e7eb;"></span>
                    <span>o regístrate con</span>
                    <span style="flex: 1; height: 1px; background: #e5e7eb;"></span>
                </div>

                <a href="auth/google_login.php?type=cliente" class="btn" style="width: 100%; background: white; color: #374151; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 16px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                    <img src="https://www.google.com/favicon.ico" alt="Google" style="width: 20px; height: 20px;">
                    Continuar con Google
                </a>

                <div class="auth-footer">
                    ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del lado del cliente y mejoras de UX
        (function() {
            'use strict';
            
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const submitIcon = document.getElementById('submitIcon');
            const submitText = document.getElementById('submitText');
            
            // Máscara para teléfono
            $("#telefono").on("input", function() {
                let value = $(this).val().replace(/[^0-9]/g, '').substring(0, 10);
                $(this).val(value);
                
                // Validación visual en tiempo real
                if (value.length === 10) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (value.length > 0) {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                } else {
                    $(this).removeClass('is-valid is-invalid');
                }
            });
            
            // Validación de email en tiempo real
            $("#email").on("blur", function() {
                const email = $(this).val();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && emailRegex.test(email)) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (email) {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                } else {
                    $(this).removeClass('is-valid is-invalid');
                }
            });
            
            // Validación de contraseñas en tiempo real
            function validatePasswords() {
                const password = $("#password").val();
                const confirmPassword = $("#confirm_password").val();
                
                // Actualizar indicador de requisitos
                const reqLength = document.getElementById('reqLength');
                if (password.length >= 6) {
                    reqLength.className = 'fas fa-check-circle text-success me-1';
                    $("#password").removeClass('is-invalid').addClass('is-valid');
                } else if (password.length > 0) {
                    reqLength.className = 'fas fa-circle text-warning me-1';
                    $("#password").removeClass('is-valid');
                } else {
                    reqLength.className = 'fas fa-circle me-1';
                    $("#password").removeClass('is-valid is-invalid');
                }
                
                // Mostrar barra de fortaleza
                updatePasswordStrength(password);
                
                // Validar confirmación de contraseña
                const matchIndicator = document.getElementById('passwordMatchIndicator');
                if (confirmPassword && password === confirmPassword && password.length >= 6) {
                    $("#confirm_password").removeClass('is-invalid').addClass('is-valid');
                    matchIndicator.style.display = 'block';
                } else if (confirmPassword) {
                    $("#confirm_password").removeClass('is-valid').addClass('is-invalid');
                    matchIndicator.style.display = 'none';
                } else {
                    $("#confirm_password").removeClass('is-valid is-invalid');
                    matchIndicator.style.display = 'none';
                }
                
                // Habilitar/deshabilitar botón - SIMPLIFICADO para mejor UX
                checkFormValidity();
            }
            
            // Función para verificar fortaleza de contraseña
            function updatePasswordStrength(password) {
                const strengthDiv = document.getElementById('passwordStrength');
                const strengthBar = document.getElementById('strengthBar');
                const strengthText = document.getElementById('strengthText');
                
                if (password.length === 0) {
                    strengthDiv.style.display = 'none';
                    return;
                }
                
                strengthDiv.style.display = 'block';
                let strength = 0;
                
                if (password.length >= 6) strength += 25;
                if (password.length >= 8) strength += 25;
                if (/[A-Z]/.test(password)) strength += 25;
                if (/[0-9]/.test(password)) strength += 15;
                if (/[^A-Za-z0-9]/.test(password)) strength += 10;
                
                strength = Math.min(strength, 100);
                strengthBar.style.width = strength + '%';
                
                if (strength < 40) {
                    strengthBar.className = 'progress-bar bg-danger';
                    strengthText.textContent = 'Débil';
                } else if (strength < 70) {
                    strengthBar.className = 'progress-bar bg-warning';
                    strengthText.textContent = 'Media';
                } else {
                    strengthBar.className = 'progress-bar bg-success';
                    strengthText.textContent = 'Fuerte';
                }
            }
            
            // Verificar validez del formulario de forma más flexible
            function checkFormValidity() {
                const nombre = $("#nombre").val().trim();
                const apellido = $("#apellido").val().trim();
                const email = $("#email").val().trim();
                const telefono = $("#telefono").val().trim();
                const password = $("#password").val();
                const confirmPassword = $("#confirm_password").val();
                const termsChecked = $("#terms").is(':checked');
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                const isValid = nombre.length > 0 &&
                              apellido.length > 0 &&
                              emailRegex.test(email) &&
                              telefono.length === 10 &&
                              password.length >= 6 &&
                              password === confirmPassword &&
                              termsChecked;
                
                submitBtn.disabled = !isValid;
                
                // Cambiar estilo del botón según validez
                if (isValid) {
                    submitBtn.classList.remove('btn-secondary');
                    submitBtn.classList.add('btn-primary');
                }
            }
            
            $("#password, #confirm_password").on("keyup input", validatePasswords);
            $("#terms").on("change", validatePasswords);
            
            // Validación de nombres (solo letras y espacios)
            $("#nombre, #apellido").on("input", function() {
                const nameRegex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
                const value = $(this).val();
                
                if (value && nameRegex.test(value)) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (value) {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                } else {
                    $(this).removeClass('is-valid is-invalid');
                }
            });
            
            // Validación de código de referido (opcional)
            $("#codigo_referido").on("input", function() {
                const codigo = $(this).val().trim();
                if (codigo === '') {
                    $(this).removeClass('is-valid is-invalid');
                } else if (codigo.length >= 6) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }
            });
            
            // Animación del botón de envío
            if (form) {
                form.addEventListener('submit', function(event) {
                    // Mostrar loading
                    submitBtn.disabled = true;
                    loadingSpinner.style.display = 'inline-block';
                    submitIcon.style.display = 'none';
                    submitText.textContent = 'Creando cuenta...';
                    
                    // Validación final del lado del cliente
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        // Restaurar botón
                        submitBtn.disabled = false;
                        loadingSpinner.style.display = 'none';
                        submitIcon.style.display = 'inline';
                        submitText.textContent = 'Crear cuenta';
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            }
            
            // Auto-focus en primer campo
            $("#nombre").focus();
            
            // Prevenir envío doble del formulario
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
            });
            
            // Si hay código de referido en la URL, hacer scroll al campo
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('ref')) {
                setTimeout(() => {
                    document.getElementById('codigo_referido').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    document.getElementById('codigo_referido').focus();
                }, 500);
            }
            
        })();
        
        // Mejorar experiencia de usuario con efectos visuales
        document.addEventListener('DOMContentLoaded', function() {
            // Animación suave de los campos al hacer focus
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Tooltip para requisitos de contraseña
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.addEventListener('focus', function() {
                    const requirements = document.getElementById('passwordReqs');
                    if (requirements) {
                        requirements.style.opacity = '1';
                        requirements.style.transform = 'translateY(0)';
                    }
                });
            }
            
            // Toggle para mostrar/ocultar contraseña
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeIconConfirm = document.getElementById('eyeIconConfirm');
            
            if (togglePassword) {
                togglePassword.addEventListener('click', function(e) {
                    e.preventDefault();
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    eyeIcon.classList.toggle('fa-eye');
                    eyeIcon.classList.toggle('fa-eye-slash');
                });
            }
            
            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function(e) {
                    e.preventDefault();
                    const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordField.setAttribute('type', type);
                    eyeIconConfirm.classList.toggle('fa-eye');
                    eyeIconConfirm.classList.toggle('fa-eye-slash');
                });
            }
            
            // Llamar checkFormValidity cuando cambien otros campos
            $('#nombre, #apellido, #email, #telefono').on('input blur', function() {
                setTimeout(checkFormValidity, 100);
            });
        });
        
        // Función global para verificar formulario
        function checkFormValidity() {
            const nombre = $("#nombre").val().trim();
            const apellido = $("#apellido").val().trim();
            const email = $("#email").val().trim();
            const telefono = $("#telefono").val().trim();
            const password = $("#password").val();
            const confirmPassword = $("#confirm_password").val();
            const termsChecked = $("#terms").is(':checked');
            const submitBtn = document.getElementById('submitBtn');
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            const isValid = nombre.length > 0 &&
                          apellido.length > 0 &&
                          emailRegex.test(email) &&
                          telefono.length === 10 &&
                          password.length >= 6 &&
                          password === confirmPassword &&
                          termsChecked;
            
            if (submitBtn) {
                submitBtn.disabled = !isValid;
            }
        }
    </script>
</body>
</html>