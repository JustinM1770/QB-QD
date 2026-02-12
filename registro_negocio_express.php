<?php
/**
 * QuickBite - Registro Express para Negocios
 * Formulario simplificado: usuario + negocio en un solo paso
 */
session_start();

require_once 'config/database.php';
require_once 'config/env.php';
require_once 'config/csrf.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';

// PHPMailer
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Si ya está logueado como negocio
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SESSION["tipo_usuario"] === "negocio") {
        header("Location: admin/negocio_configuracion.php");
        exit;
    }
}

$database = new Database();
$db = $database->getConnection();

// Variables
$errors = [];
$success = false;
$step = isset($_GET['step']) ? $_GET['step'] : 'register';

// Configuración de email
$emailConfig = [
    'host' => env('SMTP_HOST', 'smtp.hostinger.com'),
    'port' => (int) env('SMTP_PORT', 587),
    'username' => env('SMTP_USER', 'contacto@quickbite.com.mx'),
    'password' => env('SMTP_PASS', ''),
    'from_email' => env('SMTP_FROM_EMAIL', 'contacto@quickbite.com.mx'),
    'from_name' => env('SMTP_FROM_NAME', 'QuickBite')
];

// Procesar formulario de registro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Error de seguridad. Recarga la página.";
    } else {

        $action = $_POST['action'];

        // PASO 1: Registro Express
        if ($action === 'register_express') {
            $nombre_propietario = trim($_POST['nombre_propietario'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $password = $_POST['password'] ?? '';
            $nombre_negocio = trim($_POST['nombre_negocio'] ?? '');
            $categoria_principal = (int)($_POST['categoria_principal'] ?? 0);

            // Validaciones mínimas
            if (empty($nombre_propietario)) $errors[] = "Tu nombre es requerido";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email válido requerido";
            if (empty($telefono) || strlen($telefono) < 10) $errors[] = "Teléfono válido requerido";
            if (empty($password) || strlen($password) < 6) $errors[] = "Contraseña mínimo 6 caracteres";
            if (empty($nombre_negocio)) $errors[] = "Nombre del negocio requerido";
            if ($categoria_principal <= 0) $errors[] = "Selecciona una categoría";

            // Verificar email único
            if (empty($errors)) {
                $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Este email ya está registrado";
                }
            }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    // 1. Crear usuario
                    $codigo_verificacion = sprintf("%06d", mt_rand(0, 999999));
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $db->prepare("
                        INSERT INTO usuarios (nombre, email, telefono, password, tipo_usuario, verificado, codigo_verificacion, fecha_registro)
                        VALUES (?, ?, ?, ?, 'negocio', 0, ?, NOW())
                    ");
                    $stmt->execute([$nombre_propietario, $email, $telefono, $password_hash, $codigo_verificacion]);
                    $id_usuario = $db->lastInsertId();

                    // 2. Crear negocio con datos mínimos
                    $stmt = $db->prepare("
                        INSERT INTO negocios (
                            id_propietario, nombre, telefono, email,
                            activo, estado_operativo, registro_completado,
                            tiempo_preparacion_promedio, pedido_minimo, costo_envio, radio_entrega,
                            fecha_creacion
                        ) VALUES (?, ?, ?, ?, 1, 'pendiente', 0, 30, 0, 25, 5, NOW())
                    ");
                    $stmt->execute([$id_usuario, $nombre_negocio, $telefono, $email]);
                    $id_negocio = $db->lastInsertId();

                    // 3. Asignar categoría principal
                    $stmt = $db->prepare("INSERT INTO negocio_categorias (id_negocio, id_categoria) VALUES (?, ?)");
                    $stmt->execute([$id_negocio, $categoria_principal]);

                    // 4. Crear horarios por defecto (Lun-Sáb 9:00-21:00)
                    for ($dia = 0; $dia <= 6; $dia++) {
                        $activo = ($dia >= 1 && $dia <= 6) ? 1 : 0; // Cerrado domingos
                        $stmt = $db->prepare("
                            INSERT INTO horarios_negocio (id_negocio, dia_semana, hora_apertura, hora_cierre, activo)
                            VALUES (?, ?, '09:00:00', '21:00:00', ?)
                        ");
                        $stmt->execute([$id_negocio, $dia, $activo]);
                    }

                    $db->commit();

                    // Guardar en sesión para verificación
                    $_SESSION['pending_verification'] = [
                        'id_usuario' => $id_usuario,
                        'id_negocio' => $id_negocio,
                        'email' => $email,
                        'nombre' => $nombre_propietario
                    ];

                    // Enviar email de verificación
                    enviarEmailVerificacion($email, $codigo_verificacion, $nombre_propietario, $emailConfig);

                    header("Location: registro_negocio_express.php?step=verify");
                    exit;

                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error registro express: " . $e->getMessage());
                    $errors[] = "Error al crear la cuenta. Intenta de nuevo.";
                }
            }
        }

        // PASO 2: Verificar código
        if ($action === 'verify_code') {
            $codigo = trim($_POST['codigo'] ?? '');

            if (empty($_SESSION['pending_verification'])) {
                $errors[] = "Sesión expirada. Inicia el registro de nuevo.";
            } else {
                $pending = $_SESSION['pending_verification'];

                $stmt = $db->prepare("
                    SELECT id_usuario, codigo_verificacion
                    FROM usuarios
                    WHERE id_usuario = ? AND verificado = 0
                ");
                $stmt->execute([$pending['id_usuario']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && $user['codigo_verificacion'] === $codigo) {
                    // Verificar usuario
                    $stmt = $db->prepare("UPDATE usuarios SET verificado = 1, codigo_verificacion = NULL WHERE id_usuario = ?");
                    $stmt->execute([$pending['id_usuario']]);

                    // Iniciar sesión
                    $_SESSION['loggedin'] = true;
                    $_SESSION['id_usuario'] = $pending['id_usuario'];
                    $_SESSION['nombre'] = $pending['nombre'];
                    $_SESSION['tipo_usuario'] = 'negocio';
                    $_SESSION['registro_express'] = true;

                    unset($_SESSION['pending_verification']);

                    // Redirigir a completar perfil
                    header("Location: registro_negocio_express.php?step=complete");
                    exit;
                } else {
                    $errors[] = "Código incorrecto. Verifica e intenta de nuevo.";
                }
            }
        }

        // PASO 3: Completar perfil (ubicación básica)
        if ($action === 'complete_profile') {
            if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'negocio') {
                header("Location: registro_negocio_express.php");
                exit;
            }

            $calle = trim($_POST['calle'] ?? '');
            $numero = trim($_POST['numero'] ?? '');
            $colonia = trim($_POST['colonia'] ?? '');
            $ciudad = trim($_POST['ciudad'] ?? '');
            $latitud = floatval($_POST['latitud'] ?? 0);
            $longitud = floatval($_POST['longitud'] ?? 0);

            if (empty($calle) || empty($colonia) || empty($ciudad)) {
                $errors[] = "La dirección es requerida para recibir pedidos";
            }

            if (empty($errors)) {
                // Obtener negocio del usuario
                $stmt = $db->prepare("SELECT id_negocio FROM negocios WHERE id_propietario = ?");
                $stmt->execute([$_SESSION['id_usuario']]);
                $negocio = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($negocio) {
                    $stmt = $db->prepare("
                        UPDATE negocios SET
                            calle = ?, numero = ?, colonia = ?, ciudad = ?,
                            estado = 'Jalisco', latitud = ?, longitud = ?,
                            registro_completado = 1, estado_operativo = 'activo'
                        WHERE id_negocio = ?
                    ");
                    $stmt->execute([$calle, $numero, $colonia, $ciudad, $latitud, $longitud, $negocio['id_negocio']]);

                    $_SESSION['mensaje_bienvenida'] = true;
                    header("Location: admin/negocio_configuracion.php");
                    exit;
                }
            }
        }
    }
}

// Obtener categorías para el formulario
$stmt = $db->query("SELECT id_categoria, nombre, icono FROM categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para enviar email
function enviarEmailVerificacion($email, $codigo, $nombre, $config) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['port'];
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verifica tu cuenta - QuickBite';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px;'>
                <h2 style='color: #0165FF; text-align: center;'>QuickBite</h2>
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Tu código de verificación es:</p>
                <div style='background: #f5f5f5; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #0165FF;'>$codigo</span>
                </div>
                <p style='color: #666; font-size: 14px;'>Este código expira en 15 minutos.</p>
            </div>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error email: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registra tu Negocio - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 480px;
        }

        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #0165FF 0%, #0052cc 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 1.75rem;
            margin-bottom: 8px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 30px;
        }

        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .progress-step {
            width: 40px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .progress-step.active {
            background: #0165FF;
        }

        .progress-step.completed {
            background: #10b981;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #0165FF;
            box-shadow: 0 0 0 4px rgba(1, 101, 255, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .categoria-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .categoria-option {
            padding: 15px 10px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .categoria-option:hover {
            border-color: #0165FF;
            background: #f0f7ff;
        }

        .categoria-option.selected {
            border-color: #0165FF;
            background: #0165FF;
            color: white;
        }

        .categoria-option i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 8px;
        }

        .categoria-option input {
            display: none;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0165FF 0%, #0052cc 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(1, 101, 255, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-google {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-google:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .btn-google img {
            width: 20px;
            height: 20px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            color: #9ca3af;
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .code-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 25px 0;
        }

        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.2s;
        }

        .code-input:focus {
            outline: none;
            border-color: #0165FF;
            box-shadow: 0 0 0 4px rgba(1, 101, 255, 0.1);
        }

        .resend-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .resend-link a {
            color: #0165FF;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .login-link a {
            color: #0165FF;
            text-decoration: none;
            font-weight: 600;
        }

        #map {
            width: 100%;
            height: 200px;
            border-radius: 12px;
            margin-top: 15px;
        }

        .location-hint {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #0369a1;
        }

        .location-hint i {
            margin-right: 8px;
        }

        .feature-list {
            margin: 20px 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            color: #374151;
            font-size: 0.9rem;
        }

        .feature-item i {
            color: #10b981;
            font-size: 1.1rem;
        }

        @media (max-width: 480px) {
            .categoria-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .code-input {
                width: 45px;
                height: 55px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">

            <?php if ($step === 'register'): ?>
            <!-- PASO 1: Registro Express -->
            <div class="card-header">
                <h1>Registra tu Negocio</h1>
                <p>En menos de 2 minutos estarás listo</p>
            </div>
            <div class="card-body">
                <div class="progress-steps">
                    <div class="progress-step active"></div>
                    <div class="progress-step"></div>
                    <div class="progress-step"></div>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo implode('<br>', $errors); ?>
                </div>
                <?php endif; ?>

                <!-- Login con Google -->
                <a href="auth/google_login.php?type=negocio" class="btn btn-google">
                    <img src="https://www.google.com/favicon.ico" alt="Google">
                    Continuar con Google
                </a>

                <div class="divider">o regístrate con email</div>

                <form method="POST" id="registerForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="register_express">

                    <div class="form-group">
                        <label class="form-label">Tu nombre</label>
                        <input type="text" name="nombre_propietario" class="form-control" placeholder="Ej: Juan Pérez" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="tu@email.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" placeholder="10 dígitos" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nombre de tu negocio</label>
                        <input type="text" name="nombre_negocio" class="form-control" placeholder="Ej: Tacos El Patrón" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">¿Qué tipo de comida vendes?</label>
                        <div class="categoria-grid">
                            <?php
                            $iconos = [
                                'Tacos' => 'utensils',
                                'Pizza' => 'pizza-slice',
                                'Hamburguesas' => 'hamburger',
                                'Sushi' => 'fish',
                                'Pollos' => 'drumstick-bite',
                                'Mariscos' => 'shrimp',
                                'Postres' => 'ice-cream',
                                'Café' => 'coffee',
                                'Comida Mexicana' => 'pepper-hot'
                            ];
                            foreach ($categorias as $cat):
                                $icono = $iconos[$cat['nombre']] ?? 'store';
                            ?>
                            <label class="categoria-option" onclick="selectCategoria(this)">
                                <input type="radio" name="categoria_principal" value="<?php echo $cat['id_categoria']; ?>">
                                <i class="fas fa-<?php echo $icono; ?>"></i>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                        <i class="fas fa-rocket"></i> Crear mi cuenta
                    </button>
                </form>

                <div class="login-link">
                    ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
                </div>
            </div>

            <?php elseif ($step === 'verify'): ?>
            <!-- PASO 2: Verificar código -->
            <div class="card-header">
                <h1>Verifica tu Email</h1>
                <p>Enviamos un código a tu correo</p>
            </div>
            <div class="card-body">
                <div class="progress-steps">
                    <div class="progress-step completed"></div>
                    <div class="progress-step active"></div>
                    <div class="progress-step"></div>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo implode('<br>', $errors); ?>
                </div>
                <?php endif; ?>

                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-envelope" style="font-size: 3rem; color: #0165FF;"></i>
                    <p style="margin-top: 15px; color: #6b7280;">
                        Ingresa el código de 6 dígitos que enviamos a<br>
                        <strong><?php echo htmlspecialchars($_SESSION['pending_verification']['email'] ?? ''); ?></strong>
                    </p>
                </div>

                <form method="POST" id="verifyForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="verify_code">
                    <input type="hidden" name="codigo" id="codigoCompleto">

                    <div class="code-input-container">
                        <input type="text" class="code-input" maxlength="1" data-index="0" autofocus>
                        <input type="text" class="code-input" maxlength="1" data-index="1">
                        <input type="text" class="code-input" maxlength="1" data-index="2">
                        <input type="text" class="code-input" maxlength="1" data-index="3">
                        <input type="text" class="code-input" maxlength="1" data-index="4">
                        <input type="text" class="code-input" maxlength="1" data-index="5">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Verificar código
                    </button>
                </form>

                <div class="resend-link">
                    ¿No recibiste el código? <a href="registro_negocio_express.php">Reenviar</a>
                </div>
            </div>

            <?php elseif ($step === 'complete'): ?>
            <!-- PASO 3: Completar ubicación -->
            <div class="card-header">
                <h1>¿Dónde está tu negocio?</h1>
                <p>Último paso para empezar a vender</p>
            </div>
            <div class="card-body">
                <div class="progress-steps">
                    <div class="progress-step completed"></div>
                    <div class="progress-step completed"></div>
                    <div class="progress-step active"></div>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo implode('<br>', $errors); ?>
                </div>
                <?php endif; ?>

                <div class="location-hint">
                    <i class="fas fa-info-circle"></i>
                    Los clientes cercanos podrán encontrar tu negocio y hacer pedidos
                </div>

                <form method="POST" id="locationForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="complete_profile">
                    <input type="hidden" name="latitud" id="latitud">
                    <input type="hidden" name="longitud" id="longitud">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Calle</label>
                            <input type="text" name="calle" id="calle" class="form-control" placeholder="Ej: Av. México" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número</label>
                            <input type="text" name="numero" id="numero" class="form-control" placeholder="Ej: 123">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Colonia</label>
                            <input type="text" name="colonia" id="colonia" class="form-control" placeholder="Ej: Centro" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" id="ciudad" class="form-control" placeholder="Ej: Guadalajara" required>
                        </div>
                    </div>

                    <div id="map"></div>
                    <small style="color: #6b7280; display: block; margin: 10px 0;">
                        <i class="fas fa-hand-pointer"></i> Puedes mover el marcador para ajustar la ubicación exacta
                    </small>

                    <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-check-circle"></i> Completar registro
                    </button>
                </form>

                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Podrás agregar tu menú después</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Configura horarios y precios cuando quieras</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Sube logo e imágenes más tarde</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js"></script>
    <script>
        // Selección de categoría
        function selectCategoria(element) {
            document.querySelectorAll('.categoria-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            element.querySelector('input').checked = true;
        }

        // Manejo de inputs de código
        document.querySelectorAll('.code-input').forEach((input, index, inputs) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                updateCodigoCompleto();
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').split('').slice(0, 6);
                digits.forEach((digit, i) => {
                    if (inputs[i]) inputs[i].value = digit;
                });
                updateCodigoCompleto();
            });
        });

        function updateCodigoCompleto() {
            const inputs = document.querySelectorAll('.code-input');
            let codigo = '';
            inputs.forEach(input => codigo += input.value);
            document.getElementById('codigoCompleto').value = codigo;
        }

        // Mapa para ubicación
        <?php if ($step === 'complete'): ?>
        mapboxgl.accessToken = '<?php echo getenv("MAPBOX_TOKEN") ?: ""; ?>';

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [-103.3496, 20.6597], // Guadalajara
            zoom: 13
        });

        const marker = new mapboxgl.Marker({ draggable: true })
            .setLngLat([-103.3496, 20.6597])
            .addTo(map);

        // Actualizar coords al mover marcador
        marker.on('dragend', () => {
            const lngLat = marker.getLngLat();
            document.getElementById('latitud').value = lngLat.lat.toFixed(6);
            document.getElementById('longitud').value = lngLat.lng.toFixed(6);
        });

        // Click en mapa
        map.on('click', (e) => {
            marker.setLngLat([e.lngLat.lng, e.lngLat.lat]);
            document.getElementById('latitud').value = e.lngLat.lat.toFixed(6);
            document.getElementById('longitud').value = e.lngLat.lng.toFixed(6);
        });

        // Geocodificar dirección
        let geocodeTimeout;
        ['calle', 'numero', 'colonia', 'ciudad'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => {
                clearTimeout(geocodeTimeout);
                geocodeTimeout = setTimeout(geocodeDireccion, 1000);
            });
        });

        async function geocodeDireccion() {
            const calle = document.getElementById('calle').value;
            const numero = document.getElementById('numero').value;
            const colonia = document.getElementById('colonia').value;
            const ciudad = document.getElementById('ciudad').value;

            if (!calle || !ciudad) return;

            const direccion = `${calle} ${numero}, ${colonia}, ${ciudad}, Jalisco, México`;

            try {
                const response = await fetch(
                    `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(direccion)}.json?access_token=${mapboxgl.accessToken}&country=mx&limit=1`
                );
                const data = await response.json();

                if (data.features?.length > 0) {
                    const [lng, lat] = data.features[0].center;
                    map.setCenter([lng, lat]);
                    marker.setLngLat([lng, lat]);
                    document.getElementById('latitud').value = lat.toFixed(6);
                    document.getElementById('longitud').value = lng.toFixed(6);
                }
            } catch (e) {
                console.error('Geocode error:', e);
            }
        }

        // Intentar geolocalización
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((pos) => {
                const { latitude, longitude } = pos.coords;
                map.setCenter([longitude, latitude]);
                marker.setLngLat([longitude, latitude]);
                document.getElementById('latitud').value = latitude.toFixed(6);
                document.getElementById('longitud').value = longitude.toFixed(6);
            });
        }

        // Set coords iniciales
        document.getElementById('latitud').value = 20.6597;
        document.getElementById('longitud').value = -103.3496;
        <?php endif; ?>

        // Submit loading
        document.getElementById('registerForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando cuenta...';
        });
    </script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>
