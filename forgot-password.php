<?php
// forgot-password.php
session_start();

// Mostrar errores para debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once 'config/database.php';
require_once 'models/Usuario.php';

// PHPMailer - Instalación manual
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();
$usuario = new Usuario($db);

$step = $_GET['step'] ?? 1;
$message = '';
$error = '';

// Configuración de email - desde variables de entorno
require_once __DIR__ . '/config/env.php';

$emailConfig = [
    'host' => getenv('SMTP_HOST') ?: 'smtp.hostinger.com',
    'port' => (int)(getenv('SMTP_PORT') ?: 587),
    'username' => getenv('SMTP_USER') ?: getenv('SMTP_FROM_EMAIL'),
    'password' => getenv('SMTP_PASS'),
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'contacto@quickbite.com.mx',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'QuickBite'
];

// Función optimizada para Hostinger
function enviarCodigoRecuperacion($email, $codigo, $nombre) {
    global $emailConfig;
    
    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP para Hostinger
        $mail->isSMTP();
        $mail->Host       = $emailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['username'];
        $mail->Password   = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS para puerto 587
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
        $mail->Subject = 'Código de recuperación - QuickBite';
        $mail->Body = crearTemplateEmail($codigo, $nombre);

        // Versión de texto plano como respaldo
        $mail->AltBody = "Hola $nombre,\n\nTu código de recuperación es: $codigo\n\nEste código expira en 15 minutos.\n\nSaludos,\nEquipo QuickBite";

        // Enviar email
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log del error para debugging
        error_log("Error enviando email desde Hostinger: " . $mail->ErrorInfo);
        return false;
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_reset'])) {
        // Paso 1: Solicitar reset
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Por favor ingresa un email válido';
        } else {
            // Verificar si el email existe
            $query = "SELECT id_usuario, nombre, apellido FROM usuarios WHERE email = ? AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generar código de 6 dígitos
                $codigo = sprintf("%06d", mt_rand(0, 999999));
                $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Guardar código en base de datos
                $query = "INSERT INTO password_resets (email, codigo, expira, created_at) 
                         VALUES (?, ?, ?, NOW()) 
                         ON DUPLICATE KEY UPDATE 
                         codigo = VALUES(codigo), 
                         expira = VALUES(expira), 
                         created_at = NOW()";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $email);
                $stmt->bindParam(2, $codigo);
                $stmt->bindParam(3, $expira);
                
                if ($stmt->execute()) {
                    // Enviar email con PHPMailer
                    if (enviarCodigoRecuperacion($email, $codigo, $userData['nombre'])) {
                        $_SESSION['reset_email'] = $email;
                        header('Location: forgot-password.php?step=2');
                        exit;
                    } else {
                        $error = 'Error al enviar el email. Verifica tu configuración de correo.';
                    }
                } else {
                    $error = 'Error en el sistema. Intenta más tarde.';
                }
            } else {
                $error = 'No encontramos una cuenta con ese email';
            }
        }
    } 
    elseif (isset($_POST['resend_code'])) {
        // Reenviar código
        $email = $_SESSION['reset_email'] ?? '';
        if (empty($email)) {
            $error = 'No hay email para reenviar el código';
        } else {
            // Verificar si el email existe
            $query = "SELECT id_usuario, nombre FROM usuarios WHERE email = ? AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generar nuevo código
                $codigo = sprintf("%06d", mt_rand(0, 999999));
                $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Actualizar código en base de datos
                $query = "INSERT INTO password_resets (email, codigo, expira, created_at) 
                         VALUES (?, ?, ?, NOW()) 
                         ON DUPLICATE KEY UPDATE 
                         codigo = VALUES(codigo), 
                         expira = VALUES(expira), 
                         created_at = NOW(), usado = 0";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $email);
                $stmt->bindParam(2, $codigo);
                $stmt->bindParam(3, $expira);
                
                if ($stmt->execute()) {
                    // Enviar email
                    if (enviarCodigoRecuperacion($email, $codigo, $userData['nombre'])) {
                        $message = 'Código reenviado correctamente';
                    } else {
                        $error = 'Error al enviar el email. Intenta de nuevo.';
                    }
                } else {
                    $error = 'Error en el sistema. Intenta más tarde.';
                }
            } else {
                $error = 'No encontramos una cuenta con ese email';
            }
        }
    }
    elseif (isset($_POST['verify_code'])) {
        // Paso 2: Verificar código
        $codigo = $_POST['codigo'];
        $email = $_SESSION['reset_email'] ?? '';
        
        if (empty($codigo) || empty($email)) {
            $error = 'Código o email faltante';
        } else {
            // Verificar código
            $query = "SELECT * FROM password_resets 
                     WHERE email = ? AND codigo = ? AND expira > NOW() AND usado = 0";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $email);
            $stmt->bindParam(2, $codigo);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['reset_verified'] = true;
                header('Location: forgot-password.php?step=3');
                exit;
            } else {
                $error = 'Código incorrecto o expirado';
            }
        }
    }
    elseif (isset($_POST['reset_password'])) {
        // Paso 3: Cambiar contraseña
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $email = $_SESSION['reset_email'] ?? '';
        
        if (empty($password) || empty($confirm_password) || empty($email)) {
            $error = 'Todos los campos son requeridos';
        } elseif ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            // Actualizar contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $db->beginTransaction();
            try {
                // Actualizar contraseña
                $query = "UPDATE usuarios SET password = ? WHERE email = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $hashedPassword);
                $stmt->bindParam(2, $email);
                $stmt->execute();
                
                // Marcar código como usado
                $query = "UPDATE password_resets SET usado = 1 WHERE email = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $email);
                $stmt->execute();
                
                $db->commit();
                
                // Limpiar sesión
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_verified']);
                
                $message = 'Contraseña actualizada correctamente';
                $step = 4;
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Error al actualizar la contraseña';
            }
        }
    }
}

function crearTemplateEmail($codigo, $nombre) {
    return '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Código de Recuperación - QuickBite</title>
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
            .security-note {
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
                <div class="greeting">¡Hola ' . htmlspecialchars($nombre) . '!</div>
                
                <div class="message">
                    Recibimos una solicitud para restablecer la contraseña de tu cuenta de QuickBite. 
                    Usa el siguiente código de verificación para continuar:
                </div>
                
                <div class="code-container">
                    <div class="code-label">Código de Verificación</div>
                    <div class="code">' . $codigo . '</div>
                </div>
                
                <div class="expiry">
                    ⏰ Este código expira en 15 minutos por seguridad.
                </div>
                
                <div class="security-note">
                    🔒 <strong>Nota de Seguridad:</strong> Si no solicitaste este cambio, puedes ignorar este email. 
                    Tu cuenta permanecerá segura y no se realizarán cambios.
                </div>
            </div>
            
            <div class="footer">
                Este email fue enviado por QuickBite<br>
                Si tienes problemas, contáctanos en contacto@quickbite.com.mx<br><br>
                © ' . date('Y') . ' QuickBite. Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>';
}

$page_title = "Recuperar Contraseña - QuickBite";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title><?php echo $page_title; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="icon" type="image/x-icon" href="/assets/img/logo.png">
    
    <style>
        /* Forzar modo claro */
        :root {
            color-scheme: light only;
        }
        
        :root {
            --primary: #0165FF;
            --primary-light: rgba(1, 101, 255, 0.08);
            --primary-medium: rgba(1, 101, 255, 0.12);
            --primary-dark: #0052cc;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #0EA5E9;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-soft: 0 0 40px rgba(1, 101, 255, 0.08);
            --shadow-card: 0 8px 32px rgba(15, 23, 42, 0.06);
            --shadow-input: 0 2px 8px rgba(15, 23, 42, 0.04);
            --shadow-button: 0 4px 16px rgba(1, 101, 255, 0.24);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, #fafbfc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--gray-700);
            font-weight: 400;
            line-height: 1.6;
        }

        .auth-container {
            background: var(--white);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-card);
            width: 100%;
            max-width: 420px;
            padding: 48px 32px;
            position: relative;
            border: 1px solid var(--gray-100);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .auth-logo {
            font-size: 40px;
            margin-bottom: 20px;
            color: var(--primary);
            font-weight: 500;
        }

        .auth-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 8px;
            font-family: 'DM Sans', sans-serif;
        }

        .auth-subtitle {
            color: var(--gray-500);
            font-size: 15px;
            line-height: 1.6;
            font-weight: 400;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
            gap: 8px;
        }

        .step {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gray-200);
            transition: var(--transition);
        }

        .step.active {
            background: var(--primary);
            transform: scale(1.25);
        }

        .step.completed {
            background: var(--success);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 16px 16px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 15px;
            transition: var(--transition);
            background: var(--white);
            font-family: inherit;
            color: var(--gray-900);
            font-weight: 400;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-input::placeholder {
            color: var(--gray-400);
            font-weight: 400;
        }

        .code-input {
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 6px;
            font-family: 'DM Sans', sans-serif;
        }

        .auth-button {
            width: 100%;
            padding: 14px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            box-shadow: var(--shadow-button);
            margin-bottom: 20px;
        }

        .auth-button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(1, 101, 255, 0.3);
        }

        .auth-button:active {
            transform: translateY(0);
        }

        .auth-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            background: var(--gray-300);
            box-shadow: none;
        }

        .auth-button.secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            box-shadow: var(--shadow-input);
        }

        .auth-button.secondary:hover {
            background: var(--gray-200);
            box-shadow: var(--shadow-input);
        }

        .auth-link {
            display: block;
            text-align: center;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
        }

        .auth-link:hover {
            color: var(--primary-dark);
        }

        .alert {
            padding: 14px 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 400;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.06);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.06);
            border-color: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
            color: var(--gray-500);
        }

        .strength-bar {
            width: 100%;
            height: 3px;
            background: var(--gray-100);
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            background: var(--danger);
            transition: var(--transition);
            width: 0%;
            border-radius: 2px;
        }

        .strength-fill.weak { background: var(--danger); width: 25%; }
        .strength-fill.fair { background: var(--warning); width: 50%; }
        .strength-fill.good { background: var(--info); width: 75%; }
        .strength-fill.strong { background: var(--success); width: 100%; }

        .resend-code {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--gray-500);
        }

        .resend-link {
            color: var(--primary);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .resend-link:hover {
            color: var(--primary-dark);
        }

        .success-icon {
            font-size: 48px;
            color: var(--success);
            text-align: center;
            margin-bottom: 24px;
            animation: fadeInScale 0.5s ease;
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }
            
            .auth-container {
                padding: 32px 24px;
            }
            
            .auth-title {
                font-size: 22px;
            }
            
            .code-input {
                font-size: 18px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo" >
                <img src="/assets/img/logo.png"  alt="Logo" style="width: 100px; height: 100px;">
                        </div>
            
            <?php if ($step == 1): ?>
                <h1 class="auth-title">Recuperar Contraseña</h1>
                <p class="auth-subtitle">Ingresa tu email para recibir un código de verificación</p>
            <?php elseif ($step == 2): ?>
                <h1 class="auth-title">Verifica tu Email</h1>
                <p class="auth-subtitle">Ingresa el código de 6 dígitos que enviamos a<br><strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong></p>
            <?php elseif ($step == 3): ?>
                <h1 class="auth-title">Nueva Contraseña</h1>
                <p class="auth-subtitle">Crea una nueva contraseña segura para tu cuenta</p>
            <?php else: ?>
                <h1 class="auth-title">¡Listo!</h1>
                <p class="auth-subtitle">Tu contraseña ha sido actualizada correctamente</p>
            <?php endif; ?>
        </div>

        <!-- Indicador de pasos -->
        <?php if ($step <= 3): ?>
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'completed' : ''; ?>"></div>
            <div class="step <?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : ''; ?>"></div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>"></div>
        </div>
        <?php endif; ?>

        <!-- Mensajes de error/éxito -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Paso 1: Solicitar email -->
        <?php if ($step == 1): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="tu@email.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>
                
                <button type="submit" name="request_reset" class="auth-button">
                    <i class="fas fa-paper-plane"></i> Enviar Código
                </button>
                
                <a href="login.php" class="auth-link">
                    <i class="fas fa-arrow-left"></i> Volver al Login
                </a>
            </form>

        <!-- Paso 2: Verificar código -->
        <?php elseif ($step == 2): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="codigo" class="form-label">Código de Verificación</label>
                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        class="form-input code-input"
                        placeholder="000000"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                        autocomplete="one-time-code"
                    >
                </div>

                <button type="submit" name="verify_code" class="auth-button">
                    <i class="fas fa-check"></i> Verificar Código
                </button>
            </form>

            <div class="resend-code">
                ¿No recibiste el código?
                <form method="POST" style="display: inline;">
                    <button type="submit" name="resend_code" class="resend-link" style="border: none; background: none; font-size: inherit;">
                        Reenviar código
                    </button>
                </form>
            </div>

            <a href="forgot-password.php?step=1" class="auth-link" style="margin-top: 20px; display: block;">
                <i class="fas fa-arrow-left"></i> Usar otro email
            </a>

        <!-- Paso 3: Nueva contraseña -->
        <?php elseif ($step == 3): ?>
            <form method="POST" action="" id="passwordForm">
                <div class="form-group">
                    <label for="password" class="form-label">Nueva Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Mínimo 6 caracteres"
                        minlength="6"
                        required
                    >
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <span id="strengthText">La contraseña debe tener al menos 6 caracteres</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-input" 
                        placeholder="Repite tu contraseña"
                        required
                    >
                </div>
                
                <button type="submit" name="reset_password" class="auth-button" id="submitBtn">
                    <i class="fas fa-lock"></i> Actualizar Contraseña
                </button>
                
                <a href="login.php" class="auth-link">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
            </form>

        <!-- Paso 4: Éxito -->
        <?php else: ?>
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <div style="text-align: center; margin-bottom: 30px;">
                <p style="color: var(--gray-600); margin-bottom: 20px;">
                    Tu contraseña ha sido actualizada exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.
                </p>
            </div>
            
            <a href="login.php" class="auth-button" style="text-decoration: none; display: block; text-align: center;">
                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </a>
        <?php endif; ?>
    </div>

    <script>
        // Script para el indicador de fortaleza de contraseña
        <?php if ($step == 3): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const submitBtn = document.getElementById('submitBtn');
            
            function checkPasswordStrength(password) {
                let strength = 0;
                let feedback = [];
                
                if (password.length >= 6) strength += 1;
                else feedback.push('al menos 6 caracteres');
                
                if (password.length >= 8) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                return { strength, feedback };
            }
            
            function updateStrengthIndicator(password) {
                if (password.length === 0) {
                    strengthIndicator.style.display = 'none';
                    return;
                }
                
                strengthIndicator.style.display = 'block';
                const { strength } = checkPasswordStrength(password);
                
                strengthFill.className = 'strength-fill';
                
                if (strength <= 2) {
                    strengthFill.classList.add('weak');
                    strengthText.textContent = 'Contraseña débil';
                } else if (strength <= 3) {
                    strengthFill.classList.add('fair');
                    strengthText.textContent = 'Contraseña regular';
                } else if (strength <= 4) {
                    strengthFill.classList.add('good');
                    strengthText.textContent = 'Contraseña buena';
                } else {
                    strengthFill.classList.add('strong');
                    strengthText.textContent = 'Contraseña fuerte';
                }
            }
            
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                const isValid = password.length >= 6 && password === confirmPassword;
                submitBtn.disabled = !isValid;
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.style.borderColor = 'var(--danger)';
                } else {
                    confirmPasswordInput.style.borderColor = 'var(--gray-200)';
                }
            }
            
            passwordInput.addEventListener('input', function() {
                updateStrengthIndicator(this.value);
                validatePasswords();
            });
            
            confirmPasswordInput.addEventListener('input', validatePasswords);
            
            // Auto-focus en el campo de código si estamos en el paso 2
            const codigoInput = document.getElementById('codigo');
            if (codigoInput) {
                codigoInput.focus();
                
                // Permitir solo números en el campo de código
                codigoInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
        <?php endif; ?>
        
        // Auto-focus en el campo de email si estamos en el paso 1
        <?php if ($step == 1): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
        <?php endif; ?>
        
        // Auto-focus en el campo de código si estamos en el paso 2
        <?php if ($step == 2): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const codigoInput = document.getElementById('codigo');
            if (codigoInput) {
                codigoInput.focus();
                
                // Permitir solo números
                codigoInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Auto-submit cuando se completen 6 dígitos
                    if (this.value.length === 6) {
                        // Pequeña pausa para mejor UX
                        setTimeout(() => {
                            this.form.submit();
                        }, 500);
                    }
                });
                
                // Pegar código del portapapeles
                codigoInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        const value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                        this.value = value;
                        if (value.length === 6) {
                            setTimeout(() => {
                                this.form.submit();
                            }, 500);
                        }
                    }, 10);
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>