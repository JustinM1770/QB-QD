<?php
session_start();

require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Repartidor.php';
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();
$usuario = new Usuario($db);
$repartidor = new Repartidor($db);

$message = '';
$error = '';
$verification_success = false;

$email = $_SESSION['email_verification'] ?? $_SESSION['email_verification_repartidor'] ?? $_GET['email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'cliente';
$is_repartidor = isset($_SESSION['email_verification_repartidor']) || $user_type === 'repartidor';

if (empty($email)) {
    header('Location: ' . ($is_repartidor ? 'registro_repartidor.php' : 'register.php'));
    exit;
}


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


function getLoginPage($tipo_usuario) {
    switch ($tipo_usuario) {
        case 'repartidor':
            return 'login_repartidor.php';
        case 'negocio':
            return 'login_negocio.php';
        case 'cliente':
        default:
            return 'login.php';
    }
}


function reenviarCodigoVerificacion($email, $codigo, $nombre) {
    global $emailConfig;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $emailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['username'];
        $mail->Password   = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $emailConfig['port'];

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($email, $nombre);
        $mail->addReplyTo($emailConfig['from_email'], $emailConfig['from_name']);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Nuevo código de verificación - QuickBite';
        $mail->Body = crearTemplateReenvio($codigo, $nombre);
        $mail->AltBody = "Hola $nombre,\n\nTu nuevo código de verificación es: $codigo\n\nEste código expira en 15 minutos.\n\nSaludos,\nEquipo QuickBite";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Error reenviando código: " . $mail->ErrorInfo);
        return false;
    }
}

function crearTemplateReenvio($codigo, $nombre) {
    return '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
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
            }
            .header {
                background: linear-gradient(135deg, #0165FF 0%, #0052cc 100%);
                padding: 40px 30px;
                text-align: center;
                color: white;
            }
            .logo { font-size: 32px; font-weight: 600; margin-bottom: 8px; }
            .content { padding: 40px 30px; }
            .greeting { font-size: 20px; color: #0f172a; margin-bottom: 20px; font-weight: 600; }
            .message { color: #475569; font-size: 16px; margin-bottom: 30px; }
            .code-container {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 30px;
                text-align: center;
                margin: 30px 0;
            }
            .code-label { color: #64748b; font-size: 14px; margin-bottom: 10px; }
            .code {
                font-size: 32px;
                font-weight: 700;
                color: #0165FF;
                letter-spacing: 6px;
            }
            .footer {
                background: #f8fafc;
                padding: 30px;
                text-align: center;
                color: #64748b;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">QuickBite</div>
                <div>Nuevo código de verificación</div>
            </div>
            <div class="content">
                <div class="greeting">¡Hola ' . htmlspecialchars($nombre) . '!</div>
                <div class="message">
                    Has solicitado un nuevo código de verificación. Aquí tienes tu nuevo código:
                </div>
                <div class="code-container">
                    <div class="code-label">Nuevo Código de Verificación</div>
                    <div class="code">' . $codigo . '</div>
                </div>
                <p style="color: #dc2626; font-weight: 500;">⏰ Este código expira en 15 minutos.</p>
            </div>
            <div class="footer">
                © ' . date('Y') . ' QuickBite. Todos los derechos reservados.
            </div>
        </div>
    </body>
    </html>';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['codigo']) && !isset($_POST['resend_code'])) {
        $codigo = trim($_POST['codigo']);

        if (empty($codigo)) {
            $error = 'Por favor ingresa el código de verificación';
        } elseif (strlen($codigo) !== 6 || !is_numeric($codigo)) {
            $error = 'El código debe tener 6 dígitos';
        } else {
            try {
                
                $query = "SELECT id_usuario, nombre, tipo_usuario, verification_code 
                         FROM usuarios 
                         WHERE email = :email AND verification_code = :codigo AND activo = 1";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':codigo', $codigo);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    
                    $update_query = "UPDATE usuarios 
                                   SET is_verified = 1, verification_code = NULL, fecha_actualizacion = NOW()
                                   WHERE email = :email";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':email', $email);
                    
                    if ($update_stmt->execute()) {
                        
                        if ($user_data['tipo_usuario'] === 'repartidor') {
                            $update_repartidor_query = "UPDATE repartidores 
                                                       SET is_verified = 1, verification_code = NULL 
                                                       WHERE id_usuario = :id_usuario";
                            $update_repartidor_stmt = $db->prepare($update_repartidor_query);
                            $update_repartidor_stmt->bindParam(':id_usuario', $user_data['id_usuario']);
                            $update_repartidor_stmt->execute();
                        }
                        
                        $verification_success = true;
                        
                        
                        $_SESSION['verified_user_type'] = $user_data['tipo_usuario'];
                        
                        // Limpiar sesiones de verificación
                        unset($_SESSION['email_verification']);
                        unset($_SESSION['email_verification_repartidor']);
                        unset($_SESSION['user_type']);
                        
                        
                        $_SESSION['nombre'] = $user_data['nombre'];
                        $_SESSION['email'] = $email;
                        $_SESSION['tipo_usuario'] = $user_data['tipo_usuario'];
                        
                        $message = '¡Cuenta verificada exitosamente!';
                    } else {
                        $error = 'Error al verificar la cuenta. Intenta nuevamente.';
                    }
                } else {
                    $error = 'Código incorrecto. Intenta nuevamente.';
                }
            } catch (Exception $e) {
                $error = 'Error interno. Intenta nuevamente.';
            }
        }
    }

    if (isset($_POST['resend_code'])) {
        $now = time();
        $last_sent_time = $_SESSION['last_code_sent'] ?? 0;

        if ($now - $last_sent_time < 60) {
            $error = 'Debes esperar ' . (60 - ($now - $last_sent_time)) . ' segundos para reenviar el código.';
        } else {
            try {
                
                $new_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                
                $name_query = "SELECT nombre FROM usuarios WHERE email = :email";
                $name_stmt = $db->prepare($name_query);
                $name_stmt->bindParam(':email', $email);
                $name_stmt->execute();
                $user_data = $name_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user_data) {
                    
                    $update_code_query = "UPDATE usuarios 
                                         SET verification_code = :codigo, fecha_actualizacion = NOW() 
                                         WHERE email = :email";
                    $update_code_stmt = $db->prepare($update_code_query);
                    $update_code_stmt->bindParam(':codigo', $new_code);
                    $update_code_stmt->bindParam(':email', $email);
                    
                    if ($update_code_stmt->execute()) {
                        if (reenviarCodigoVerificacion($email, $new_code, $user_data['nombre'])) {
                            $_SESSION['last_code_sent'] = $now;
                            $message = 'Se ha enviado un nuevo código de verificación a tu correo.';
                        } else {
                            $error = 'Hubo un error al enviar el correo. Intenta nuevamente.';
                        }
                    } else {
                        $error = 'No se pudo generar un nuevo código. Intenta nuevamente.';
                    }
                } else {
                    $error = 'No se encontró la cuenta. Verifica tu email.';
                }
            } catch (Exception $e) {
                $error = 'Error interno. Intenta nuevamente.';
                error_log("Error reenvío código: " . $e->getMessage());
            }
        }
    }
}

// Determinar página de login basada en el tipo de usuario verificado
$login_page = 'login.php'; // Por defecto
if ($verification_success && isset($_SESSION['verified_user_type'])) {
    $login_page = getLoginPage($_SESSION['verified_user_type']);
}

$page_title = "Verificar Email - QuickBite";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: rgba(1, 101, 255, 0.08);
            --success: #10B981;
            --danger: #EF4444;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
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
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.06);
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
            height: 80px;
            margin-bottom: 20px;
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

        .email-display {
            background: var(--primary-light);
            border: 1px solid rgba(1, 101, 255, 0.2);
            border-radius: 12px;
            padding: 12px;
            margin: 16px 0;
            text-align: center;
            font-weight: 500;
            color: var(--primary);
            font-size: 14px;
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
            border-radius: 12px;
            font-size: 18px;
            transition: all 0.2s ease;
            background: var(--white);
            font-family: 'DM Sans', monospace;
            color: var(--gray-900);
            font-weight: 600;
            text-align: center;
            letter-spacing: 4px;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-input::placeholder {
            color: var(--gray-400);
            font-weight: 400;
            letter-spacing: 2px;
        }

        .auth-button {
            width: 100%;
            padding: 14px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            box-shadow: 0 4px 16px rgba(1, 101, 255, 0.24);
            margin-bottom: 20px;
        }

        .auth-button:hover {
            background: #0052cc;
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
            background: var(--gray-400);
            box-shadow: none;
        }

        .auth-button.secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .auth-button.secondary:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
        }

        .auth-link {
            display: block;
            text-align: center;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .auth-link:hover {
            color: #0052cc;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
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

        .resend-section {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-100);
        }

        .resend-text {
            font-size: 14px;
            color: var(--gray-500);
            margin-bottom: 12px;
        }

        .resend-button {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: underline;
        }

        .resend-button:hover {
            color: #0052cc;
        }

        .success-icon {
            font-size: 48px;
            color: var(--success);
            text-align: center;
            margin-bottom: 24px;
            animation: fadeInScale 0.5s ease;
        }

        .success-content {
            text-align: center;
        }

        .success-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--success);
            margin-bottom: 12px;
            font-family: 'DM Sans', sans-serif;
        }

        .success-message {
            color: var(--gray-600);
            margin-bottom: 24px;
            line-height: 1.6;
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

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .loading {
            animation: pulse 1.5s infinite;
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
            
            .form-input {
                font-size: 16px;
                letter-spacing: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <?php if ($verification_success): ?>
            <!-- Pantalla de éxito -->
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h1 class="success-title">¡Cuenta verificada!</h1>
                <p class="success-message">
                    Tu cuenta ha sido verificada exitosamente. Ya puedes iniciar sesión.
                </p>
                
                <a href="<?php echo $login_page; ?>" class="auth-button">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Iniciar sesión
                </a>
            </div>
            
        <?php else: ?>
            <!-- Formulario de verificación -->
            <div class="auth-header">
                <img src="assets/img/logo.png" alt="QuickBite Logo" class="auth-logo">
                <h1 class="auth-title">Verificar tu email</h1>
                <p class="auth-subtitle">
                    Hemos enviado un código de verificación de 6 dígitos a:
                </p>
                <div class="email-display">
                    <i class="fas fa-envelope me-2"></i>
                    <?php echo htmlspecialchars($email); ?>
                </div>
            </div>

            <!-- Mensajes de error/éxito -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message) && !$verification_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Formulario de verificación -->
            <form method="POST" action="" id="verifyForm">
                <div class="form-group">
                    <label for="codigo" class="form-label">Código de verificación</label>
                    <input 
                        type="number" 
                        id="codigo" 
                        name="codigo" 
                        class="form-input" 
                        placeholder="000000"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                        autocomplete="one-time-code"
                        autofocus
                        value="<?php echo isset($_POST['codigo']) ? htmlspecialchars($_POST['codigo']) : ''; ?>"
                    >
                </div>
                
                <button type="submit" name="verify_code" value="1" class="auth-button" id="verifyBtn">
                    <i class="fas fa-shield-check me-2"></i>
                    Verificar código
                </button>
            </form>
            
            <!-- Sección de reenvío -->
            <div class="resend-section">
                <p class="resend-text">¿No recibiste el código?</p>
                
                <form method="POST" style="display: inline;">
                    <button type="submit" name="resend_code" class="resend-button" id="resendBtn">
                        <i class="fas fa-paper-plane me-1"></i>
                        Reenviar código
                    </button>
                </form>
                
                <div class="countdown" id="resendCountdown" style="display: none;">
                    Podrás reenviar en <span id="resendTimer">60</span> segundos
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="<?php echo $is_repartidor ? 'registro_repartidor.php' : 'register.php'; ?>" class="auth-link">
                    <i class="fas fa-arrow-left me-1"></i>
                    Usar otro email
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const codigoInput = document.getElementById('codigo');
            const verifyForm = document.getElementById('verifyForm');
            const verifyBtn = document.getElementById('verifyBtn');
            const resendBtn = document.getElementById('resendBtn');
            const resendCountdown = document.getElementById('resendCountdown');
            const resendTimer = document.getElementById('resendTimer');
            
            // Auto-format del código (solo números)
            if (codigoInput) {
                codigoInput.addEventListener('input', function() {
                    // Permitir solo números
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
                
                // Pegar código del portapapeles
                codigoInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        const value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                        this.value = value;
                    }, 10);
                });
            }
            
            // Manejo del botón de reenvío
            if (resendBtn) {
                resendBtn.addEventListener('click', function(e) {
                    // Deshabilitar botón y mostrar countdown
                    this.disabled = true;
                    this.style.opacity = '0.5';
                    this.style.cursor = 'not-allowed';
                    
                    if (resendCountdown) {
                        resendCountdown.style.display = 'block';
                        
                        let seconds = 60;
                        const countdown = setInterval(() => {
                            seconds--;
                            if (resendTimer) {
                                resendTimer.textContent = seconds;
                            }
                            
                            if (seconds <= 0) {
                                clearInterval(countdown);
                                resendCountdown.style.display = 'none';
                                this.disabled = false;
                                this.style.opacity = '1';
                                this.style.cursor = 'pointer';
                            }
                        }, 1000);
                    }
                });
            }
            
            // Animación del botón de verificar
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    // Prevenir envío múltiple
                    if (verifyBtn.disabled) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Validar código antes de enviar
                    const codigo = codigoInput.value.trim();
                    if (!codigo || codigo.length !== 6 || !/^[0-9]{6}$/.test(codigo)) {
                        e.preventDefault();
                        alert('Por favor ingresa un código de 6 dígitos');
                        return false;
                    }
                    
                    // Deshabilitar botón y mostrar estado de carga
                    verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verificando...';
                    verifyBtn.classList.add('loading');
                    
                    // Permitir el envío del formulario
                    return true;
                });
            }
            
            // Restaurar botón si hay error (página recargada con error)
            <?php if (!empty($error)): ?>
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-shield-check me-2"></i>Verificar código';
                verifyBtn.classList.remove('loading');
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>