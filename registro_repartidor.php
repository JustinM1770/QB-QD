<?php
// Configuración de errores para producción
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Iniciar sesión
session_start();

// Protección CSRF
require_once __DIR__ . '/config/csrf.php';

require_once 'config/database.php';
require_once 'config/env.php';
require_once 'models/Repartidor.php';

// PHPMailer para envío de emails
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$database = new Database();
$db = $database->getConnection();

// Configuración de email - Cargada desde variables de entorno (SEGURO)
$emailConfig = [
    'host' => env('SMTP_HOST', 'smtp.hostinger.com'),
    'port' => (int) env('SMTP_PORT', 587),
    'username' => env('SMTP_USER', 'contacto@quickbite.com.mx'),
    'password' => env('SMTP_PASS', ''),  // ✅ SEGURO: Cargado desde .env
    'from_email' => env('SMTP_FROM_EMAIL', 'contacto@quickbite.com.mx'),
    'from_name' => env('SMTP_FROM_NAME', 'QuickBite')
];

$nombre = $apellido = $email = $password = $telefono = $licencia = "";
$nombre_err = $apellido_err = $email_err = $password_err = $telefono_err = $licencia_err = "";
$register_success = false;
$verification_sent = false;

// Función para enviar email de verificación
function enviarEmailVerificacionRepartidor($email, $codigo, $nombre = "Repartidor") {
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
        $mail->Subject = 'Verificación de cuenta - QuickBite Repartidor';
        $mail->Body = crearTemplateRepartidor($codigo, $nombre);

        $mail->AltBody = "Hola $nombre,\n\nTu código de verificación es: $codigo\n\nEste código expira en 15 minutos.\n\nSaludos,\nEquipo QuickBite";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Error enviando email de verificación: " . $mail->ErrorInfo);
        return false;
    }
}

function crearTemplateRepartidor($codigo, $nombre) {
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
            .repartidor-note {
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
                <div class="header-subtitle">Bienvenido al equipo de repartidores</div>
            </div>
            
            <div class="content">
                <div class="greeting">¡Bienvenido ' . htmlspecialchars($nombre) . '!</div>
                
                <div class="message">
                    Gracias por unirte a nuestro equipo de repartidores. Para completar tu registro y activar tu cuenta, 
                    necesitamos verificar tu dirección de email con el siguiente código:
                </div>
                
                <div class="code-container">
                    <div class="code-label">Código de Verificación</div>
                    <div class="code">' . $codigo . '</div>
                </div>
                
                <div class="expiry">
                    ⏰ Este código expira en 15 minutos por seguridad.
                </div>
                
                <div class="repartidor-note">
                    🚴‍♂️ <strong>¡Ya casi formas parte del equipo!</strong> Una vez verificada tu cuenta, podrás:
                    <ul style="margin-top: 8px; padding-left: 20px;">
                        <li>Acceder a tu panel de repartidor</li>
                        <li>Recibir pedidos en tu zona</li>
                        <li>Gestionar tus entregas</li>
                        <li>Ganar dinero entregando comida</li>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $email_err = "Error de seguridad. Por favor, recarga la página e intenta de nuevo.";
    } else {

    // Crear instancia del modelo repartidor
    $repartidor = new Repartidor($db);

    // Validate name
    if (empty(trim($_POST["nombre"]))) {
        $nombre_err = "Por favor ingresa tu nombre.";
    } elseif (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", trim($_POST["nombre"]))) {
        $nombre_err = "El nombre solo puede contener letras y espacios.";
    } else {
        $nombre = trim($_POST["nombre"]);
    }
    
    // Validate last name
    if (empty(trim($_POST["apellido"]))) {
        $apellido_err = "Por favor ingresa tu apellido.";
    } elseif (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", trim($_POST["apellido"]))) {
        $apellido_err = "El apellido solo puede contener letras y espacios.";
    } else {
        $apellido = trim($_POST["apellido"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor ingresa tu email.";
    } else {
        $email = trim($_POST["email"]);
        
        // Verificar que sea un email válido
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Formato de email inválido.";
        } else {
            // Verificar si el email ya existe en la tabla usuarios
            $query_check = "SELECT id_usuario FROM usuarios WHERE email = ?";
            $stmt_check = $db->prepare($query_check);
            $stmt_check->bindParam(1, $email);
            $stmt_check->execute();
            
            if ($stmt_check->rowCount() > 0) {
                $email_err = "Este email ya está registrado.";
            }
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor ingresa tu contraseña.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate phone
    if (empty(trim($_POST["telefono"]))) {
        $telefono_err = "Por favor ingresa tu teléfono.";
    } elseif (!preg_match("/^[0-9]{10}$/", trim($_POST["telefono"]))) {
        $telefono_err = "El teléfono debe tener 10 dígitos.";
    } else {
        $telefono = trim($_POST["telefono"]);
    }
    
    // Only require license for cars/trucks
    $tipo_vehiculo = $_POST["tipo_vehiculo"];
    if (($tipo_vehiculo === 'coche' || $tipo_vehiculo === 'camioneta') && empty(trim($_POST["licencia"]))) {
        $licencia_err = "Por favor ingresa tu número de licencia.";
    } else {
        $licencia = trim($_POST["licencia"] ?? '');
    }
    
    if (empty($nombre_err) && empty($apellido_err) && empty($email_err) && empty($password_err) && empty($telefono_err) && empty($licencia_err)) {
        
        // Generar código de verificación
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $expira = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Variable para controlar el estado de la transacción
        $transaction_started = false;
        
        try {
            // Intentar iniciar transacción de manera segura
            try {
                $db->beginTransaction();
                $transaction_started = true;
            } catch (PDOException $e) {
                // Si no se puede iniciar transacción, continuar sin ella
                error_log("No se pudo iniciar transacción: " . $e->getMessage());
                $transaction_started = false;
            }
            
            // Primero registrar en la tabla usuarios
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query_usuario = "INSERT INTO usuarios (nombre, apellido, email, password, telefono, tipo_usuario, verification_code, is_verified, activo) 
                             VALUES (?, ?, ?, ?, ?, 'repartidor', ?, 0, 1)";
            
            $stmt_usuario = $db->prepare($query_usuario);
            $stmt_usuario->bindParam(1, $nombre);
            $stmt_usuario->bindParam(2, $apellido);
            $stmt_usuario->bindParam(3, $email);
            $stmt_usuario->bindParam(4, $hashed_password);
            $stmt_usuario->bindParam(5, $telefono);
            $stmt_usuario->bindParam(6, $verification_code);
            
            if ($stmt_usuario->execute()) {
                $user_id = $db->lastInsertId();
                
                // Ahora registrar en la tabla repartidores con los datos específicos
                $query_repartidor = "INSERT INTO repartidores (id_usuario, tipo_vehiculo, numero_licencia, verification_code, is_verified, activo, disponible) 
                                    VALUES (?, ?, ?, ?, 0, 1, 0)";
                
                $stmt_repartidor = $db->prepare($query_repartidor);
                $stmt_repartidor->bindParam(1, $user_id);
                $stmt_repartidor->bindParam(2, $tipo_vehiculo);
                $stmt_repartidor->bindParam(3, $licencia);
                $stmt_repartidor->bindParam(4, $verification_code);
                
                if ($stmt_repartidor->execute()) {
                    
                    // Guardar código de verificación en tabla separada
                    $query_verification = "INSERT INTO email_verifications_repartidores (email, codigo, expira, usado) 
                                         VALUES (?, ?, ?, 0) 
                                         ON DUPLICATE KEY UPDATE 
                                         codigo = VALUES(codigo), 
                                         expira = VALUES(expira), 
                                         usado = 0";
                    
                    $stmt_verification = $db->prepare($query_verification);
                    $stmt_verification->bindParam(1, $email);
                    $stmt_verification->bindParam(2, $verification_code);
                    $stmt_verification->bindParam(3, $expira);
                    
                    if ($stmt_verification->execute()) {
                        // Enviar email de verificación con el nombre real
                        if (enviarEmailVerificacionRepartidor($email, $verification_code, $nombre)) {
                            // Confirmar transacción solo si se inició y está activa
                            if ($transaction_started && $db->inTransaction()) {
                                $db->commit();
                            }
                            
                            $register_success = true;
                            $verification_sent = true;
                            
                            // Guardar email en sesión para la página de verificación
                            $_SESSION['email_verification_repartidor'] = $email;
                            $_SESSION['user_type'] = 'repartidor';
                            
                            // Limpiar datos del formulario
                            $nombre = $apellido = $email = $password = $telefono = $licencia = "";
                            
                        } else {
                            // Revertir transacción solo si se inició y está activa
                            if ($transaction_started && $db->inTransaction()) {
                                $db->rollback();
                            }
                            $email_err = "Error al enviar el email de verificación. Inténtalo de nuevo.";
                        }
                    } else {
                        // Revertir transacción solo si se inició y está activa
                        if ($transaction_started && $db->inTransaction()) {
                            $db->rollback();
                        }
                        $email_err = "Error al guardar el código de verificación.";
                    }
                } else {
                    // Revertir transacción solo si se inició y está activa
                    if ($transaction_started && $db->inTransaction()) {
                        $db->rollback();
                    }
                    $email_err = "Error al registrar los datos del repartidor.";
                }
            } else {
                // Revertir transacción solo si se inició y está activa
                if ($transaction_started && $db->inTransaction()) {
                    $db->rollback();
                }
                $email_err = "Error al crear el usuario. Verifica que el email no esté ya registrado.";
            }
        } catch (Exception $e) {
            // Revertir transacción solo si se inició y está activa
            if ($transaction_started && $db->inTransaction()) {
                try {
                    $db->rollback();
                } catch (PDOException $rollbackException) {
                    error_log("Error al hacer rollback: " . $rollbackException->getMessage());
                }
            }
            error_log("Error en registro repartidor: " . $e->getMessage());
            $email_err = "Ocurrió un error. Inténtalo de nuevo más tarde.";
        }
    }
    } // Cierre del else CSRF
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Repartidores - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <meta name="theme-color" content="#0165FF">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            .icon{
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 10px;

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
            margin-bottom: 30px;
        }

        .auth-header img {
            height: 60px;
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
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 8px;
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
                    <p>Tu cuenta de repartidor ha sido creada correctamente. Hemos enviado un código de verificación de 6 dígitos a:</p>
                    <p><strong><?php echo htmlspecialchars($_SESSION['email_verification_repartidor'] ?? ''); ?></strong></p>
                    <p>Por favor revisa tu bandeja de entrada y tu carpeta de spam.</p>
                    
                    <a href="verify_email.php" class="btn btn-success">
                        <i class="fas fa-shield-check me-2"></i>
                        Verificar mi cuenta
                    </a>
                    
                    <div class="verification-info">
                        <i class="fas fa-info-circle"></i>
                        <p>El código de verificación expira en 15 minutos. Si no lo recibes, podrás solicitar uno nuevo desde la página de verificación.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-header">
                    <div class="icon">
                        <i class="fas fa-motorcycle" > </i>
                    </div>
                    <h1 class="auth-title">Registro para repartidores</h1>
                    <p class="auth-subtitle">Completa tus datos para unirte a nuestra plataforma</p>
                    <?php if (!empty($error_general)): ?>
                        <div class="alert alert-danger"><?php echo $error_general; ?></div>
                    <?php endif; ?>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control <?php echo (!empty($telefono_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $telefono; ?>" placeholder="10 dígitos" required>
                        <div class="invalid-feedback"><?php echo $telefono_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo_vehiculo" class="form-label">Tipo de vehículo</label>
                        <select name="tipo_vehiculo" id="tipo_vehiculo" class="form-control" required>
                            <option value="bicicleta" <?php echo (isset($_POST['tipo_vehiculo']) && $_POST['tipo_vehiculo'] === 'bicicleta') ? 'selected' : ''; ?>>Bicicleta</option>
                            <option value="motocicleta" <?php echo (isset($_POST['tipo_vehiculo']) && $_POST['tipo_vehiculo'] === 'motocicleta') ? 'selected' : ''; ?>>Motocicleta</option>
                            <option value="coche" <?php echo (isset($_POST['tipo_vehiculo']) && $_POST['tipo_vehiculo'] === 'coche') ? 'selected' : ''; ?>>Coche</option>
                            <option value="camioneta" <?php echo (isset($_POST['tipo_vehiculo']) && $_POST['tipo_vehiculo'] === 'camioneta') ? 'selected' : ''; ?>>Camioneta</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="licencia-group" style="display:none;">
                        <label for="licencia" class="form-label">Número de licencia</label>
                        <input type="text" name="licencia" id="licencia" class="form-control <?php echo (!empty($licencia_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $licencia; ?>">
                        <div class="invalid-feedback"><?php echo $licencia_err; ?></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-motorcycle me-2"></i>
                        Registrarse como repartidor
                    </button>
                </form>
                
                <div class="auth-footer">
                    ¿Ya tienes una cuenta? <a href="login_repartidor.php">Inicia sesión aquí</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mostrar/ocultar campo de licencia según tipo de vehículo
            function toggleLicencia() {
                const tipoVehiculo = $('#tipo_vehiculo').val();
                if (tipoVehiculo === 'coche' || tipoVehiculo === 'camioneta') {
                    $('#licencia-group').show();
                    $('#licencia').attr('required', true);
                } else {
                    $('#licencia-group').hide();
                    $('#licencia').attr('required', false);
                }
            }
            
            // Ejecutar al cargar la página
            toggleLicencia();
            
            // Ejecutar al cambiar el tipo de vehículo
            $('#tipo_vehiculo').change(toggleLicencia);
            
            // Validación de nombres (solo letras y espacios)
            $("#nombre, #apellido").on("input", function() {
                const nameRegex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
                const value = $(this).val();
                
                if (value && nameRegex.test(value) && value.length >= 2) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (value) {
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
            
            // Validación de contraseña en tiempo real
            $("#password").on("input", function() {
                const password = $(this).val();
                
                if (password.length >= 6) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else if (password.length > 0) {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                } else {
                    $(this).removeClass('is-valid is-invalid');
                }
            });
            
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
            
            // Auto-focus en primer campo
            $("#nombre").focus();
        });
    </script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html> 