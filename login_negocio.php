<?php
// Configuración de errores para producción
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();

// Protección CSRF
require_once __DIR__ . '/config/csrf.php';

// Si el usuario ya está logueado y es negocio, redirigir según tenga o no negocio registrado
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'negocio') {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Verificar si tiene negocio registrado
    $query_negocio = "SELECT * FROM negocios WHERE id_propietario = ?";
    $stmt_negocio = $db->prepare($query_negocio);
    $stmt_negocio->bindParam(1, $_SESSION['id_usuario']);
    $stmt_negocio->execute();

    if ($stmt_negocio->rowCount() > 0) {
        // Si ya tiene negocio, ir al dashboard
        header("Location: admin/negocio_configuracion.php");
    } else {
        // Si no tiene negocio, ir a registrar negocio
        header("Location: registro_negocio.php");
    }
    exit();
}

// Incluir configuración de BD y modelos necesarios
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
    } else {

    // Validar email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor ingresa tu email.";
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
        try {
            // Conectar a BD
            $database = new Database();
            $db = $database->getConnection();
            
            // Configurar objeto usuario
            $usuario = new Usuario($db);
            $usuario->email = $email;
            $usuario->password = $password;
            
            // Intentar login con el método login de tu clase Usuario
            if ($usuario->login()) {
                // Verificar si el usuario es tipo negocio
                $query = "SELECT tipo_usuario, id_usuario, nombre FROM usuarios WHERE id_usuario = ?";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $usuario->id_usuario);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $tipo_usuario = $row['tipo_usuario'];
                    
                    if ($tipo_usuario === 'negocio') {
                        // Regenerar ID de sesión para prevenir session fixation
                        session_regenerate_id(true);

                        // Iniciar sesión básica
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id_usuario"] = $usuario->id_usuario;
                        $_SESSION["tipo_usuario"] = "negocio";
                        $_SESSION["nombre_usuario"] = $row['nombre'];
                        
                        // Verificar si tiene negocio registrado
                        $query_negocio = "SELECT id_negocio, nombre FROM negocios WHERE id_propietario = ? AND activo = 1";
                        $stmt_negocio = $db->prepare($query_negocio);
                        $stmt_negocio->bindParam(1, $usuario->id_usuario);
                        $stmt_negocio->execute();
                        
                        if ($stmt_negocio->rowCount() > 0) {
                            // Si tiene negocio registrado, establecer datos del negocio en sesión
                            $negocio_data = $stmt_negocio->fetch(PDO::FETCH_ASSOC);
                            $_SESSION["id_negocio"] = $negocio_data['id_negocio'];
                            $_SESSION["nombre_negocio"] = $negocio_data['nombre'];
                            
                            // Redirigir al dashboard de negocio
                            header("Location: admin/negocio_configuracion.php");
                            exit();
                        } else {
                            // Si no tiene negocio registrado, redirigir al registro de negocio
                            $_SESSION['mensaje_exito'] = "¡Bienvenido de nuevo! Completa el registro de tu negocio para empezar a vender.";
                            header("Location: registro_negocio.php");
                            exit();
                        }
                    } else {
                        $login_err = "Esta cuenta no está registrada como negocio. Por favor, usa el login principal para clientes.";
                    }
                } else {
                    $login_err = "Error al verificar el tipo de usuario. Por favor, intenta de nuevo.";
                }
            } else {
                $login_err = "Email o contraseña incorrectos.";
            }
        } catch (Exception $e) {
            error_log("Error en login_negocio: " . $e->getMessage());
            $login_err = "Error en el sistema. Por favor, intenta más tarde.";
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
    <title>Login para Negocios - QuickBite</title>
    <!-- Fonts: Inter and DM Sans -->
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
            max-width: 420px;
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
            margin-bottom: 15px;
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

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
            margin-top: 15px;
            display: inline-block;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: rgba(1, 101, 255, 0.1);
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

        .icon-header {
            width: 80px;
            height: 80px;
            background-color: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .icon-header i {
            font-size: 35px;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="qb-logo">
                    <span class="quick">Quick</span><span class="bite">Bite</span>
                </div>
        <div class="auth-card">
            <div class="auth-header">
                <div class="icon-header">
                    <i class="fas fa-store"></i>
                </div>
                <h1 class="auth-title">Portal de Negocios</h1>
                <p class="auth-subtitle">Accede a tu cuenta de negocio para gestionar tus productos y pedidos</p>
            </div>
            
            <?php if (!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
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

            <div class="divider" style="display: flex; align-items: center; gap: 15px; margin: 20px 0; color: #9ca3af; font-size: 0.85rem;">
                <span style="flex: 1; height: 1px; background: #e5e7eb;"></span>
                <span>o continúa con</span>
                <span style="flex: 1; height: 1px; background: #e5e7eb;"></span>
            </div>

            <a href="auth/google_login.php?type=negocio" class="btn" style="width: 100%; background: white; color: #374151; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                <img src="https://www.google.com/favicon.ico" alt="Google" style="width: 20px; height: 20px;">
                Continuar con Google
            </a>

            <div class="text-center mt-4">
                <a href="login.php" class="btn-outline">
                    <i class="fas fa-arrow-left me-2"></i> Volver al inicio de sesión
                </a>
            </div>

            <div class="auth-footer">
                ¿No tienes un negocio registrado? <a href="registro_negocio_express.php">Regístrate aquí</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>