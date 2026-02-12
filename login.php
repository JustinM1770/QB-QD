<?php
// Manejador de errores centralizado
require_once __DIR__ . '/config/error_handler.php';

// Protección CSRF y Rate Limiting (csrf.php ya inicia la sesión)
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/rate_limit.php';

// Si el usuario ya está logueado, redirigir a la página principal
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Incluir configuración de BD y modelo de usuario
require_once 'config/database.php';
require_once 'models/Usuario.php';

// Variables para mensajes y valores previos
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Procesar datos del formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $login_err = "Error de seguridad. Por favor, recarga la página e intenta de nuevo.";
    }
    // Verificar rate limiting (5 intentos por minuto)
    elseif (!rate_limit('login')) {
        $remaining = get_rate_limit_remaining('login', 60);
        $login_err = "Demasiados intentos. Por favor espera {$remaining} segundos.";
    }
    else {
        // Validar email
        if (empty(trim($_POST["email"]))) {
            $email_err = "Por favor ingresa tu email.";
        } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
            $email_err = "Formato de email inválido.";
        } else {
            $email = trim($_POST["email"]);
        }
        
        // Validar contraseña
        if (empty(trim($_POST["password"]))) {
            $password_err = "Por favor ingresa tu contraseña.";
        } else {
            $password = trim($_POST["password"]);
        }
        
        // Validar credenciales
        if (empty($email_err) && empty($password_err)) {
            // Conectar a BD
            $database = new Database();
            $db = $database->getConnection();
            
            // Configurar objeto usuario
            $usuario = new Usuario($db);
            $usuario->email = $email;
            $usuario->password = $password;
            
            // Intentar login
            $resultado = $usuario->login();
            
            if ($resultado['success']) {
                // Verificar que sea cliente
                if ($usuario->tipo_usuario !== 'cliente') {
                    $login_err = "Este formulario es solo para clientes. Por favor usa el formulario correcto.";
                } else {
                    // Login exitoso - resetear rate limit
                    reset_rate_limit('login');
                    
                    // Regenerar ID de sesión para prevenir session fixation
                    session_regenerate_id(true);
                    
                    // Iniciar sesión y guardar datos
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id_usuario"] = $usuario->id_usuario;
                    $_SESSION["nombre"] = $usuario->nombre;
                    $_SESSION["apellido"] = $usuario->apellido;
                    $_SESSION["tipo_usuario"] = 'cliente';
                    $_SESSION["last_activity"] = time();
                    
                    // Regenerar token CSRF
                    regenerate_csrf_token();
                
                    // Redirigir al usuario a la página principal
                    header("location: index.php");
                    exit();
                }
            } else {
                // Manejar diferentes tipos de errores
                if ($resultado['error'] === 'email_not_verified') {
                    $login_err = $resultado['message'];
                } else {
                    $login_err = "Email o contraseña incorrectos.";
                }
            }
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
    <title>Iniciar Sesión - QuickBite</title>
    

    
    <!-- Fonts: Inter and DM Sans -->
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
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
            --warning: #FFD700;
            --gradient: linear-gradient(135deg, #0165FF 0%, #0165FF 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Logo QuickBite con tamaños responsivos */
        .qb-logo {
            font-family: 'League Spartan', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 700;
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
            letter-spacing: -0.01em;
            position: relative;
            transition: all 0.5s ease;
            text-align: center;
        }

        .qb-logo .quick {
            color: var(--light);
            animation: fadeInLeft 1s ease 0.2s both;
        }

        .qb-logo .bite {
            color: #FFD700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            animation: fadeInRight 1s ease 0.4s both;
        }

        /* Animaciones del preloader */
        @keyframes fadeInLeft {
            0% {
                opacity: 0;
                transform: translateX(-50px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInRight {
            0% {
                opacity: 0;
                transform: translateX(50px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes logoSpinner {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes pulseBite {
            0%, 100% { 
                color: #FFD700;
                text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
                transform: scale(1);
            }
            25% { 
                color: #FFA500;
                text-shadow: 0 0 35px rgba(255, 165, 0, 0.8);
                transform: scale(1.1);
            }
            50% { 
                color: #FF8C00;
                text-shadow: 0 0 40px rgba(255, 140, 0, 1);
                transform: scale(1.15);
            }
            75% { 
                color: #FFA500;
                text-shadow: 0 0 35px rgba(255, 165, 0, 0.8);
                transform: scale(1.1);
            }
        }

        @keyframes pulseQuick {
            0%, 100% { 
                opacity: 1;
                transform: scale(1);
            }
            50% { 
                opacity: 0.8;
                transform: scale(1.02);
            }
        }

        /* Estado de carga */
        .qb-logo.loading {
            animation: logoSpinner 3s ease-in-out infinite;
        }

        .qb-logo.loading .quick {
            animation: fadeInLeft 1s ease 0.2s both, pulseQuick 2s ease-in-out infinite;
        }

        .qb-logo.loading .bite {
            animation: fadeInRight 1s ease 0.4s both, pulseBite 1.8s ease-in-out infinite;
        }

        /* Spinner de carga */
        .loading-spinner {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 215, 0, 0.3);
            border-top: 2px solid #FFD700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .qb-logo.loading .loading-spinner {
            opacity: 1;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        /* Contenedor responsivo con mejor padding */
        .auth-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 15px;
            width: 100%;
        }

        /* Card responsivo con ancho fluido */
        .auth-card {
            background-color: white;
            border-radius: clamp(15px, 3vw, 20px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            padding: clamp(20px, 5vw, 30px);
        }

        .auth-header {
            text-align: center;
            margin-bottom: clamp(20px, 4vw, 30px);
        }

        .auth-header img {
            height: clamp(50px, 10vw, 70px);
            margin-bottom: clamp(15px, 3vw, 20px);
        }

        /* Título responsivo */
        .auth-title {
            font-size: clamp(1.3rem, 4vw, 1.8rem);
            color: #000000;
            margin-bottom: 5px;
            font-weight: 700;
            line-height: 1.3;
        }

        .auth-subtitle {
            color: #666;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .form-group {
            margin-bottom: clamp(15px, 3vw, 20px);
        }

        .form-label {
            font-weight: 500;
            font-size: clamp(0.85rem, 2vw, 0.9rem);
            margin-bottom: 8px;
            color: var(--accent);
            display: block;
        }

        /* Inputs responsivos con mejor touch target */
        .form-control {
            padding: clamp(10px, 2vw, 12px) clamp(12px, 3vw, 15px);
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            font-size: clamp(0.9rem, 2vw, 0.95rem);
            width: 100%;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.15);
            outline: none;
        }

        /* Botón responsivo con mejor touch target */
        .btn-primary {
            background: #FFD700;
            border: none;
            border-radius: 10px;
            padding: clamp(10px, 2.5vw, 12px) 0;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            color: var(--dark);
            font-size: clamp(0.9rem, 2vw, 1rem);
            transition: all 0.2s ease;
            cursor: pointer;
            min-height: 44px; /* Mejor touch target para móviles */
        }

        .btn-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .auth-footer {
            text-align: center;
            margin-top: clamp(20px, 4vw, 25px);
            font-size: clamp(0.85rem, 2vw, 0.9rem);
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .invalid-feedback {
            color: #e74c3c;
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            margin-top: 5px;
            display: block;
        }

        .alert {
            border-radius: 10px;
            padding: clamp(10px, 2vw, 12px) clamp(12px, 3vw, 15px);
            margin-bottom: clamp(15px, 3vw, 20px);
            font-size: clamp(0.85rem, 2vw, 0.9rem);
        }

        /* Selector de tipo de usuario responsivo */
        .user-type-selector {
            display: flex;
            justify-content: center;
            gap: clamp(20px, 5vw, 30px);
            margin-bottom: clamp(15px, 3vw, 20px);
            flex-wrap: wrap;
        }

        .user-type-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--accent);
            transition: all 0.3s ease;
            min-width: 70px;
        }

        .user-type-btn:hover {
            transform: translateY(-3px);
            color: var(--primary);
        }

        /* Botones circulares responsivos */
        .user-type-btn .btn-circle {
            width: clamp(60px, 12vw, 70px);
            height: clamp(60px, 12vw, 70px);
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .user-type-btn:hover .btn-circle {
            border-color: var(--primary);
            box-shadow: 0 6px 15px rgba(1, 101, 255, 0.2);
        }

        .user-type-btn i {
            font-size: clamp(24px, 5vw, 28px);
        }

        .user-type-label {
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            font-weight: 500;
            text-align: center;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: clamp(15px, 3vw, 20px) 0;
            color: #888;
            font-size: clamp(0.8rem, 2vw, 0.85rem);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider::before {
            margin-right: .5em;
        }

        .divider::after {
            margin-left: .5em;
        }

        /* Estilos responsivos para checkboxes y enlaces */
        .d-flex.justify-content-between {
            font-size: clamp(0.8rem, 2vw, 0.85rem);
            flex-wrap: wrap;
            gap: 10px;
        }

        .form-check-label {
            font-size: clamp(0.8rem, 2vw, 0.85rem);
        }

        /* Media queries para ajustes específicos en dispositivos pequeños */
        @media (max-width: 480px) {
            .auth-container {
                padding: 10px;
                justify-content: flex-start;
                padding-top: 20px;
            }

            .qb-logo {
                margin-bottom: 1rem;
                font-size: 2rem;
            }

            .auth-card {
                padding: 20px;
                border-radius: 15px;
            }

            .user-type-selector {
                gap: 15px;
            }

            .user-type-btn .btn-circle {
                width: 60px;
                height: 60px;
            }

            .user-type-btn i {
                font-size: 24px;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-check {
                margin-bottom: 5px;
            }
        }

        /* Media query para tablets */
        @media (min-width: 481px) and (max-width: 768px) {
            .auth-card {
                max-width: 450px;
            }
        }

        /* Media query para desktop */
        @media (min-width: 769px) {
            .auth-container {
                padding: 30px;
            }
        }

        /* Mejoras de accesibilidad táctil */
        @media (hover: none) and (pointer: coarse) {
            .btn-primary,
            .form-control,
            .user-type-btn .btn-circle {
                min-height: 44px;
            }
            
            .form-control {
                font-size: 16px; /* Previene zoom en iOS */
            }
        }

/* =======================================================
   MODO OSCURO - LOGIN.PHP
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

    .user-type-btn .btn-circle {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }

    .user-type-btn span {
        color: #e0e0e0 !important;
    }
}


    </style>
</head>
<body>
    
    <div class="auth-container">
        <div class="qb-logo" id="qb-logo">
            <span class="quick">Quick</span><span class="bite">Bite</span>
        </div>
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">Inicia sesión para continuar</h1>
            </div>

            <!-- Selector de tipo de usuario -->
            <div class="user-type-selector">
                <a href="login_repartidor.php" class="user-type-btn">
                    <div class="btn-circle">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <span class="user-type-label">Repartidor</span>
                </a>
                <a href="login_negocio.php" class="user-type-btn">
                    <div class="btn-circle">
                        <i class="fas fa-store"></i>
                    </div>
                    <span class="user-type-label">Negocio</span>
                </a>
            </div>

            <div class="divider">o inicia como cliente</div>
            
            <?php if (!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
            <?php endif; ?>
            
           <?php if (isset($resultado) && isset($resultado['error']) && $resultado['error'] === 'email_not_verified'): ?>
                <div class="alert alert-warning">
                    <strong>¡Cuenta no verificada!</strong><br>
                    <?php echo $resultado['message']; ?>
                    <div class="mt-3">
                        <a href="verify_email.php?email=<?php echo urlencode($email); ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-envelope"></i> Verificar mi cuenta
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">
                            Recordarme
                        </label>
                    </div>
                    <a href="forgot-password.php" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn btn-primary">Iniciar sesión</button>
            </form>

            <div style="display: flex; align-items: center; gap: 15px; margin: 20px 0; color: #9ca3af; font-size: 0.85rem;">
                <span style="flex: 1; height: 1px; background: #e5e7eb;"></span>
                <span>o continúa con</span>
                <span style="flex: 1; height: 1px; background: #e5e7eb;"></span>
            </div>

            <a href="auth/google_login.php?type=cliente" class="btn" style="width: 100%; background: white; color: #374151; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.2s;">
                <img src="https://www.google.com/favicon.ico" alt="Google" style="width: 20px; height: 20px;">
                Continuar con Google
            </a>

            <div class="auth-footer">
                ¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            const $logo = $('#qb-logo');
            const $form = $('form');
            const $submitBtn = $('.btn-primary');
            
            // Activar preloader al enviar el formulario
            $form.on('submit', function(e) {
                // Activar estado de carga
                $logo.addClass('loading');
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Iniciando sesión...');
                
                // Simular delay mínimo para mostrar la animación
                setTimeout(() => {
                    // El formulario se enviará normalmente después del delay
                }, 800);
            });
            
            // Desactivar preloader si hay errores (página se recarga)
            if ($('.alert-danger, .alert-warning').length > 0) {
                $logo.removeClass('loading');
                $submitBtn.prop('disabled', false).html('Iniciar sesión');
            }
            
            // Efecto hover en el botón
            $submitBtn.hover(
                function() {
                    if (!$(this).prop('disabled')) {
                        $logo.addClass('loading');
                    }
                },
                function() {
                    if (!$(this).prop('disabled')) {
                        setTimeout(() => {
                            $logo.removeClass('loading');
                        }, 500);
                    }
                }
            );
        });
    </script>
    <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>
