<?php
session_start();

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';

// Errores desactivados en producción - usar logs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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
            header("Location: registro-usuario-negocio.php");
        } else {
            // Si no tiene negocio, ir a registrar negocio
            header("Location: registro-negocio.php");
        }
        exit;
    } 
}

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Variables para mensajes y valores previos
$nombre = $email = $telefono = $password = $confirm_password = "";
$nombre_err = $email_err = $telefono_err = $password_err = $confirm_password_err = $terminos_err = "";
$register_success = false;

// Procesar datos del formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {

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

    // Validar contraseña (mínimo 8 caracteres, mayúscula, minúscula, número)
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor ingresa una contraseña.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/', trim($_POST["password"]))) {
        $password_err = "La contraseña debe contener al menos una mayúscula, una minúscula y un número.";
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

    // Validate terms and conditions
    if (!isset($_POST["terminos"])) {
        $terminos_err = "Debes aceptar los términos y condiciones.";
    }

    // Verificar errores antes de insertar en la base de datos
    if (empty($nombre_err) && empty($email_err) && empty($telefono_err) && empty($password_err) && empty($confirm_password_err) && empty($terminos_err)) {

        // Crear objeto Usuario
        $usuario = new Usuario($db);

        // Establecer propiedades
        $usuario->nombre = $nombre;
        $usuario->email = $email;
        $usuario->telefono = $telefono;
        $usuario->password = $password;
        $usuario->tipo_usuario = "negocio";

        // Registrar usuario
        if ($usuario->registrar()) {
            $register_success = true;

            // Iniciar sesión automáticamente después de registrar
            $_SESSION["loggedin"] = true;
            $_SESSION["id_usuario"] = $usuario->id_usuario;
            $_SESSION["tipo_usuario"] = "negocio";
            $_SESSION["nombre_usuario"] = $usuario->nombre;

            // Mostrar mensaje de éxito y luego redirigir
            $_SESSION['mensaje_exito'] = "¡Registro exitoso! Ahora completa la información de tu negocio.";
            $_SESSION['mensaje_validacion'] = "¡Cuenta creada! Tus datos están en validación por nuestro equipo.";
            
            // Redirigir después de 2 segundos usando JavaScript
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'registro_negocio.php';
                }, 2000);
            </script>";
        } else {
            echo "<div class='alert alert-danger'>Lo sentimos, ocurrió un error. Inténtalo de nuevo más tarde.</div>";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        :root {
            --primary: #0165FF;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --gradient: linear-gradient(135deg, #0165FF 0%, #0165FF 100%);
            --success: #22c55e;
            --warning: #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #fff8f3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        .main-container {
            display: flex;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            min-height: 550px;
        }

        .benefits-panel {
            background: var(--gradient);
            color: white;
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .benefits-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .benefits-title {
            font-size: 1.5rem;
            margin-bottom: 25px;
            position: relative;
            text-align: center;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
            opacity: 0;
            animation: slideIn 1s ease-out forwards;
        }

        .benefit-item:nth-child(2) { animation-delay: 0.2s; }
        .benefit-item:nth-child(3) { animation-delay: 0.4s; }
        .benefit-item:nth-child(4) { animation-delay: 0.6s; }
        .benefit-item:nth-child(5) { animation-delay: 0.8s; }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .benefit-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 1rem;
        }

        .benefit-text h4 {
            margin: 0 0 3px 0;
            font-size: 1rem;
        }

        .benefit-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .auth-panel {
            flex: 1.2;
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .auth-header img {
            height: 45px;
            margin-bottom: 12px;
        }

        .auth-title {
            font-size: 1.5rem;
            color: var(--accent);
            margin-bottom: 5px;
        }

        .auth-subtitle {
            color: #666;
            font-size: 0.85rem;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 6px;
            color: var(--accent);
        }

        .form-control {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.15);
            transform: translateY(-1px);
        }

        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }

        .strength-weak { color: #ef4444; }
        .strength-medium { color: var(--warning); }
        .strength-strong { color: var(--success); }

        .password-requirements {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #ddd;
        }

        .password-requirements small {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }

        .requirement i {
            margin-right: 8px;
            width: 12px;
        }

        .requirement.valid i {
            color: var(--success) !important;
        }

        .requirement.valid i:before {
            content: "\f00c"; /* check icon */
        }

        .password-requirements {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #ddd;
        }

        .password-requirements small {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }

        .requirement i {
            margin-right: 8px;
            width: 12px;
        }

        .requirement.valid i {
            color: var(--success) !important;
        }

        .requirement.valid i:before {
            content: "\f00c"; /* check icon */
        }

        .btn-primary {
            background: var(--gradient);
            border: none;
            border-radius: 8px;
            padding: 10px 0;
            font-weight: 600;
            width: 100%;
            margin-top: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(1, 101, 255, 0.3);
        }

        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            margin: 15px 0;
        }

        .terms-checkbox input[type="checkbox"] {
            margin-right: 8px;
            margin-top: 2px;
        }

        .terms-checkbox label {
            font-size: 0.8rem;
            line-height: 1.4;
            color: #666;
        }

        .terms-checkbox a {
            color: var(--primary);
            text-decoration: underline;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #666;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .auth-footer a:hover {
            color: #0052cc;
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
            border: none;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .success-message {
            text-align: center;
            padding: 20px;
            background-color: rgba(16, 185, 129, 0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .success-message i {
            font-size: 3rem;
            color: #10B981;
            margin-bottom: 15px;
        }

        .success-message h3 {
            color: #10B981;
            margin-bottom: 10px;
        }

        /* Modal para beneficios en móvil */
        .mobile-benefits-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .mobile-benefits-content {
            background: var(--gradient);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px;
            max-width: 350px;
            text-align: center;
            position: relative;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mobile-benefits-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.7;
        }

        .mobile-benefits-close:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .main-container {
                flex-direction: column;
                margin: 0;
                max-width: 100%;
                min-height: auto;
            }
            
            .benefits-panel {
                display: none; /* Ocultar en móvil, mostrar en modal */
            }
            
            .auth-panel {
                padding: 20px;
                order: 1;
            }
            
            .auth-title {
                font-size: 1.3rem;
            }
            
            .form-control {
                padding: 12px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .btn-primary {
                padding: 12px 0;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                border-radius: 15px;
            }
            
            .auth-panel {
                padding: 15px;
            }
            
            .auth-header img {
                height: 40px;
            }
            
            .auth-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Modal de beneficios para móvil -->
    <div id="mobileBenefitsModal" class="mobile-benefits-modal">
        <div class="mobile-benefits-content">
            <button class="mobile-benefits-close" onclick="closeMobileBenefits()">&times;</button>
            <h3 class="mb-3">Beneficios para negocios</h3>
            
            <div class="benefit-item mb-3">
                <div class="benefit-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="benefit-text">
                    <h4>Más clientes</h4>
                    <p>Alcanza miles de usuarios hambrientos</p>
                </div>
            </div>
            
            <div class="benefit-item mb-3">
                <div class="benefit-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="benefit-text">
                    <h4>Aumenta ventas</h4>
                    <p>Incrementa tus ingresos hasta 40%</p>
                </div>
            </div>
            
            <div class="benefit-item mb-3">
                <div class="benefit-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="benefit-text">
                    <h4>Gestión fácil</h4>
                    <p>Panel de control intuitivo</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="benefit-text">
                    <h4>24/7 disponible</h4>
                    <p>Ventas las 24 horas del día</p>
                </div>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Panel de beneficios (desktop) -->
        <div class="benefits-panel">
            <h2 class="benefits-title">Beneficios para negocios</h2>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="benefit-text">
                    <h4>Más clientes</h4>
                    <p>Alcanza miles de usuarios hambrientos</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="benefit-text">
                    <h4>Aumenta ventas</h4>
                    <p>Incrementa tus ingresos hasta 40%</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="benefit-text">
                    <h4>Gestión fácil</h4>
                    <p>Panel de control intuitivo</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="benefit-text">
                    <h4>24/7 disponible</h4>
                    <p>Ventas las 24 horas del día</p>
                </div>
            </div>
        </div>

        <!-- Panel de registro -->
        <div class="auth-panel">
            <?php if ($register_success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h3>¡Registro exitoso!</h3>
                    <p>Tu cuenta ha sido creada correctamente. Serás redirigido para registrar tu negocio...</p>
                </div>
            <?php else: ?>
                <div class="auth-header">
                    <img src="assets/img/logo.png" alt="QuickBite Logo">
                    <h1 class="auth-title">Crear cuenta</h1>
                    <p class="auth-subtitle">Registra tu negocio y empieza a vender</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                    <div class="form-group">
                        <label for="nombre" class="form-label">Nombre completo</label>
                        <input type="text" name="nombre" id="nombre" class="form-control <?php echo (!empty($nombre_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $nombre; ?>" placeholder="Tu nombre completo" required />
                        <div class="invalid-feedback"><?php echo $nombre_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="tu@email.com" required />
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" class="form-control <?php echo (!empty($telefono_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $telefono; ?>" placeholder="10 dígitos" maxlength="10" required />
                        <div class="invalid-feedback"><?php echo $telefono_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Mínimo 8 caracteres" required />
                        <div id="password-strength" class="password-strength"></div>
                        <div id="password-requirements" class="password-requirements">
                            <small class="text-muted">La contraseña debe contener:</small>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-times text-danger"></i>
                                <span>Al menos 8 caracteres</span>
                            </div>
                            <div class="requirement" id="req-lowercase">
                                <i class="fas fa-times text-danger"></i>
                                <span>Una letra minúscula (a-z)</span>
                            </div>
                            <div class="requirement" id="req-uppercase">
                                <i class="fas fa-times text-danger"></i>
                                <span>Una letra mayúscula (A-Z)</span>
                            </div>
                            <div class="requirement" id="req-number">
                                <i class="fas fa-times text-danger"></i>
                                <span>Un número (0-9)</span>
                            </div>
                        </div>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Repite tu contraseña" required />
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                    
                    <div class="terms-checkbox">
                        <input type="checkbox" name="terminos" id="terminos" class="<?php echo (!empty($terminos_err)) ? 'is-invalid' : ''; ?>">
                        <label for="terminos">
                            Acepto los <a href="#" data-bs-toggle="modal" data-bs-target="#terminosModal">términos y condiciones</a> y la <a href="#" data-bs-toggle="modal" data-bs-target="#privacidadModal">política de privacidad</a>
                        </label>
                    </div>
                    <?php if (!empty($terminos_err)): ?>
                        <div class="invalid-feedback d-block"><?php echo $terminos_err; ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-store me-2"></i>Crear cuenta
                    </button>
                </form>
                
                
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Términos y Condiciones -->
    <div class="modal fade" id="terminosModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Términos y Condiciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Aceptación de términos</h6>
                    <p>Al registrar tu negocio en QuickBite, aceptas cumplir con estos términos y condiciones.</p>
                    
                    <h6>2. Responsabilidades del negocio</h6>
                    <p>Te comprometes a mantener la calidad de los alimentos y cumplir con los tiempos de preparación establecidos.</p>
                    
                    <h6>3. Comisiones y pagos</h6>
                    <p>QuickBite cobrará una comisión por cada pedido procesado. Los pagos se realizarán semanalmente.</p>
                    
                    <h6>4. Calidad del servicio</h6>
                    <p>Debes mantener estándares altos de calidad e higiene en la preparación de alimentos.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Política de Privacidad -->
    <div class="modal fade" id="privacidadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Política de Privacidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Recopilación de datos</h6>
                    <p>Recopilamos información necesaria para la gestión de tu negocio en nuestra plataforma.</p>
                    
                    <h6>Uso de la información</h6>
                    <p>Utilizamos tus datos para procesar pedidos, gestionar pagos y mejorar nuestros servicios.</p>
                    
                    <h6>Protección de datos</h6>
                    <p>Implementamos medidas de seguridad para proteger la información de tu negocio.</p>
                    
                    <h6>Compartir información</h6>
                    <p>No compartimos información confidencial de tu negocio con terceros sin autorización.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Mostrar modal de beneficios solo en móvil
            if (window.innerWidth <= 768) {
                setTimeout(function() {
                    $('#mobileBenefitsModal').fadeIn();
                }, 1000);
                
                // Auto-cerrar después de 5 segundos
                setTimeout(function() {
                    closeMobileBenefits();
                }, 6000);
            }
            
            // Validación de fortaleza de contraseña
            $('#password').on('input', function() {
                const password = $(this).val();
                const strengthDiv = $('#password-strength');
                
                // Validar requisitos individuales
                const hasLength = password.length >= 8;
                const hasLowercase = /[a-z]/.test(password);
                const hasUppercase = /[A-Z]/.test(password);
                const hasNumber = /\d/.test(password);
                
                // Actualizar indicadores visuales
                updateRequirement('req-length', hasLength);
                updateRequirement('req-lowercase', hasLowercase);
                updateRequirement('req-uppercase', hasUppercase);
                updateRequirement('req-number', hasNumber);
                
                if (password.length === 0) {
                    strengthDiv.text('');
                    return;
                }
                
                // Calcular fortaleza general
                let strength = 0;
                let message = '';
                
                if (hasLength) strength++;
                if (hasLowercase) strength++;
                if (hasUppercase) strength++;
                if (hasNumber) strength++;
                if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
                
                switch (strength) {
                    case 0:
                    case 1:
                    case 2:
                        message = 'Contraseña débil';
                        strengthDiv.attr('class', 'password-strength strength-weak');
                        break;
                    case 3:
                        message = 'Contraseña media';
                        strengthDiv.attr('class', 'password-strength strength-medium');
                        break;
                    case 4:
                    case 5:
                        message = 'Contraseña fuerte';
                        strengthDiv.attr('class', 'password-strength strength-strong');
                        break;
                }
                
                strengthDiv.text(message);
            });
            
            // Función para actualizar requisitos
            function updateRequirement(id, isValid) {
                const element = $('#' + id);
                if (isValid) {
                    element.addClass('valid');
                } else {
                    element.removeClass('valid');
                }
            }
            
            // Formatear teléfono
            $('#telefono').on('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        });
        
        // Función para cerrar modal de beneficios móvil
        function closeMobileBenefits() {
            $('#mobileBenefitsModal').fadeOut();
        }
        
        // Cerrar modal al hacer click fuera del contenido
        $('#mobileBenefitsModal').click(function(e) {
            if (e.target === this) {
                closeMobileBenefits();
            }
        });
    </script>
</body>
</html>