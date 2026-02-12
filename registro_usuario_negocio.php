<?php
session_start();

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'config/env.php';
require_once 'config/csrf.php';
require_once 'models/Usuario.php';

// PHPMailer para envío de emails
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Activar reporte de errores para depuración
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Si el usuario ya está logueado, redirigir según su tipo
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $tipo_usuario = isset($_SESSION["tipo_usuario"]) ? $_SESSION["tipo_usuario"] : null;
    
    if ($tipo_usuario === "negocio") {
        // Verificar si ya tiene un negocio registrado
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM negocios WHERE id_propietario = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $_SESSION["id_usuario"]);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Si ya tiene negocio, ir al dashboard
            header("Location: admin/negocio_configuracion.php");
        } else {
            // Si no tiene negocio, ir a registrar negocio
            header("Location: registro_negocio.php");
        }
        exit;
    } elseif ($tipo_usuario === "cliente") {
        header("Location: cliente_dashboard.php");
        exit;
    }
}

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Configuración de email - Cargada desde variables de entorno
$emailConfig = [
    'host' => env('SMTP_HOST', 'smtp.hostinger.com'),
    'port' => (int) env('SMTP_PORT', 587),
    'username' => env('SMTP_USER', 'contacto@quickbite.com.mx'),
    'password' => env('SMTP_PASS', ''),  // ✅ SEGURO: Cargado desde .env
    'from_email' => env('SMTP_FROM_EMAIL', 'contacto@quickbite.com.mx'),
    'from_name' => env('SMTP_FROM_NAME', 'QuickBite')
];

// Variables para mensajes y valores previos
$nombre = $email = $telefono = $password = $confirm_password = "";
$nombre_err = $email_err = $telefono_err = $password_err = $confirm_password_err = "";
$register_success = false;
$verification_sent = false;

// Función para enviar email de verificación
function enviarEmailVerificacionNegocio($email, $codigo, $nombre) {
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
        $mail->Subject = 'Verificación de cuenta - QuickBite Negocio';
        $mail->Body = crearTemplateNegocio($codigo, $nombre);

        $mail->AltBody = "Hola $nombre,\n\nTu código de verificación es: $codigo\n\nEste código expira en 15 minutos.\n\nSaludos,\nEquipo QuickBite";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Error enviando email de verificación: " . $mail->ErrorInfo);
        return false;
    }
}

function crearTemplateNegocio($codigo, $nombre) {
    return '
    <!DOCTYPE html>
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
            .header-subtitle { opacity: 0.9; font-size: 16px; }
            .content { padding: 40px 30px; }
            .greeting { font-size: 20px; color: #0f172a; margin-bottom: 20px; font-weight: 600; }
            .message { color: #475569; font-size: 16px; margin-bottom: 30px; line-height: 1.6; }
            .code-container {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 30px;
                text-align: center;
                margin: 30px 0;
            }
            .code-label { color: #64748b; font-size: 14px; margin-bottom: 10px; font-weight: 500; }
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
            .business-note {
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
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">QuickBite</div>
                <div class="header-subtitle">Bienvenido partner comercial</div>
            </div>
            
            <div class="content">
                <div class="greeting">¡Bienvenido ' . htmlspecialchars($nombre) . '!</div>
                
                <div class="message">
                    Gracias por elegir QuickBite como tu plataforma de ventas. Para completar tu registro y empezar a vender, 
                    necesitamos verificar tu dirección de email con el siguiente código:
                </div>
                
                <div class="code-container">
                    <div class="code-label">Código de Verificación</div>
                    <div class="code">' . $codigo . '</div>
                </div>
                
                <div class="expiry">
                    ⏰ Este código expira en 15 minutos por seguridad.
                </div>
                
                <div class="business-note">
                    🏪 <strong>¡Ya casi puedes empezar a vender!</strong> Una vez verificada tu cuenta, podrás:
                    <ul style="margin-top: 8px; padding-left: 20px;">
                        <li>Registrar tu negocio y menú</li>
                        <li>Recibir pedidos en línea</li>
                        <li>Gestionar tus ventas</li>
                        <li>Acceder a estadísticas detalladas</li>
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

    // Validar email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor ingresa tu email.";
    } else {
        $email = trim($_POST["email"]);

        // Verificar que sea un email válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Formato de email inválido.";
        } else {
            // Verificar si el email ya existe
            $usuario_check = new Usuario($db);
            $usuario_check->email = $email;
            if ($usuario_check->emailExiste()) {
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

    // Verificar errores antes de insertar en la base de datos
    if (empty($nombre_err) && empty($email_err) && empty($telefono_err) && empty($password_err) && empty($confirm_password_err)) {

        // Generar código de verificación
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Crear objeto Usuario
        $usuario = new Usuario($db);

        // Establecer propiedades
        $usuario->nombre = $nombre;
        $usuario->email = $email;
        $usuario->telefono = $telefono;
        $usuario->password = $password;
        $usuario->tipo_usuario = "negocio";
        $usuario->verification_code = $verification_code;
        $usuario->is_verified = 0;
        $usuario->activo = 1;

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // Registrar usuario
            if ($usuario->registrar()) {
                
                // Guardar código de verificación en tabla separada
                $query = "INSERT INTO email_verifications (email, codigo, expira) 
                         VALUES (?, ?, ?) 
                         ON DUPLICATE KEY UPDATE 
                         codigo = VALUES(codigo), 
                         expira = VALUES(expira), 
                         usado = 0";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $email);
                $stmt->bindParam(2, $verification_code);
                $stmt->bindParam(3, $expira);
                
                if ($stmt->execute()) {
                    // Enviar email de verificación
                    if (enviarEmailVerificacionNegocio($email, $verification_code, $nombre)) {
                        $db->commit();
                        $register_success = true;
                        $verification_sent = true;
                        
                        // Guardar email en sesión para la página de verificación
                        $_SESSION['email_verification'] = $email;
                        $_SESSION['user_type'] = 'negocio';
                        
                        // Limpiar datos del formulario
                        $nombre = $email = $telefono = $password = $confirm_password = "";
                        
                    } else {
                        $db->rollback();
                        $email_err = "Error al enviar el email de verificación. Inténtalo de nuevo.";
                    }
                } else {
                    $db->rollback();
                    $email_err = "Error al guardar el código de verificación.";
                }
            } else {
                $db->rollback();
                $email_err = "Error al registrar el usuario.";
            }
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en registro negocio: " . $e->getMessage());
            $email_err = "Ocurrió un error. Inténtalo de nuevo más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro de Usuario Negocio - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png" />
    <meta name="theme-color" content="#0165FF">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@700&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
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
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary);
            transform: translateY(-1px);
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

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
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
    </style>
</head>
<body>
    <div class="auth-container">
         <div class="qb-logo">
                    <span class="quick">Quick</span><span class="bite">Bite</span>
                </div>
        <div class="auth-card">
            <?php if ($register_success && $verification_sent): ?>
                <!-- Mensaje de éxito con verificación -->
                <div class="success-message">
                    <i class="fas fa-envelope-circle-check"></i>
                    <h3>¡Registro exitoso!</h3>
                    <p>Tu cuenta de negocio ha sido creada correctamente. Hemos enviado un código de verificación de 6 dígitos a:</p>
                    <p><strong><?php echo htmlspecialchars($email); ?></strong></p>
                    <p>Por favor revisa tu bandeja de entrada y tu carpeta de spam.</p>
                    
                    <a href="verify_email.php" class="btn btn-success">
                        <i class="fas fa-shield-check me-2"></i>
                        Verificar mi cuenta
                    </a>
                    
                    <div class="verification-info">
                        <i class="fas fa-info-circle"></i>
                        <p>El código de verificación expira en 15 minutos. Después de verificar tu email, podrás registrar tu negocio.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-header">
                    <div class="icon-header">
                        <i class="fas fa-store"></i>
                    </div>
                    <h1 class="auth-title">Registra tu Negocio</h1>
                    <p class="auth-subtitle">Crea tu cuenta para empezar a vender en QuickBite</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="nombre" class="form-label">Nombre completo</label>
                        <input type="text" name="nombre" id="nombre" class="form-control <?php echo (!empty($nombre_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $nombre; ?>" required />
                        <div class="invalid-feedback"><?php echo $nombre_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" required />
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control <?php echo (!empty($telefono_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $telefono; ?>" placeholder="10 dígitos" required />
                        <div class="invalid-feedback"><?php echo $telefono_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required />
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" required />
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-store me-2"></i>
                        Crear cuenta de negocio
                    </button>
                </form>
                
                <div class="login-link">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Máscara para teléfono
        $("#telefono").on("input", function() {
            $(this).val($(this).val().replace(/[^0-9]/g, '').substring(0, 10));
        });

        // Validación de contraseñas en tiempo real
        $("#password, #confirm_password").on("keyup", function() {
            var password = $("#password").val();
            var confirmPassword = $("#confirm_password").val();
            
            if (password.length > 0 && confirmPassword.length > 0) {
                if (password !== confirmPassword) {
                    $("#confirm_password")[0].setCustomValidity("Las contraseñas no coinciden");
                    $("#confirm_password").addClass('is-invalid');
                } else {
                    $("#confirm_password")[0].setCustomValidity("");
                    $("#confirm_password").removeClass('is-invalid').addClass('is-valid');
                }
            }
        });
    </script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>