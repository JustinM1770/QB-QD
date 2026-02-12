<?php
// Errores desactivados en producción - usar logs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Iniciar sesión
session_start();

require_once 'config/database.php';
require_once 'models/Repartidor.php';

$database = new Database();
$db = $database->getConnection();

$email = $password = $telefono = $licencia = "";
$email_err = $password_err = $telefono_err = $licencia_err = $terminos_err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Por favor ingresa tu email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Por favor ingresa un email válido.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Validate password (mínimo 8 caracteres, mayúscula, minúscula, número)
    if (empty(trim($_POST["password"]))) {
        $password_err = "Por favor ingresa tu contraseña.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/', trim($_POST["password"]))) {
        $password_err = "La contraseña debe contener al menos una mayúscula, una minúscula y un número.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate phone
    if (empty(trim($_POST["telefono"]))) {
        $telefono_err = "Por favor ingresa tu teléfono.";
    } elseif (!preg_match('/^[0-9]{10}$/', trim($_POST["telefono"]))) {
        $telefono_err = "El teléfono debe tener 10 dígitos.";
    } else {
        $telefono = trim($_POST["telefono"]);
    }
    
    // Only require license for motorized vehicles
    $tipo_vehiculo = $_POST["tipo_vehiculo"];
    if (($tipo_vehiculo === 'motocicleta' || $tipo_vehiculo === 'coche' || $tipo_vehiculo === 'camioneta') && empty(trim($_POST["licencia"]))) {
        $licencia_err = "Por favor ingresa tu número de licencia.";
    } else {
        $licencia = trim($_POST["licencia"] ?? '');
    }
    
    // Validate terms and conditions
    if (!isset($_POST["terminos"])) {
        $terminos_err = "Debes aceptar los términos y condiciones.";
    }
    
    if (empty($email_err) && empty($password_err) && empty($telefono_err) && empty($licencia_err) && empty($terminos_err)) {
        $repartidor = new Repartidor($db);
        try {
             if ($repartidor->registrar($email, $password, $telefono, $tipo_vehiculo, $licencia)) {
                $_SESSION['registro_exitoso'] = true;
                $_SESSION['mensaje_validacion'] = "¡Registro completado! Tus datos están en validación por los desarrolladores. Te contactaremos pronto.";
                header("Location: index.php");
                exit;
            }
        } catch(PDOException $e) {
            $error_general = "Error al registrar: " . $e->getMessage();
            error_log("Error detallado en registro_repartidor: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Repartidores - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            background: linear-gradient(135deg,#ffffff 0%, #fff8f3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
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
            
            .benefits-title {
                font-size: 1.3rem;
                margin-bottom: 20px;
            }
            
            .benefit-item {
                margin-bottom: 12px;
            }
            
            .benefit-icon {
                width: 35px;
                height: 35px;
                margin-right: 10px;
            }
            
            .benefit-text h4 {
                font-size: 0.9rem;
            }
            
            .benefit-text p {
                font-size: 0.8rem;
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
            
            .benefits-panel,
            .auth-panel {
                padding: 15px;
            }
            
            .auth-header img {
                height: 40px;
            }
            
            .benefits-title {
                font-size: 1.2rem;
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
            <h3 class="mb-3">Beneficios para repartidores</h3>
            
            <div class="benefit-item mb-3">
                <div class="benefit-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="benefit-text">
                    <h4>Incrementa tus ventas</h4>
                    <p>Accede a miles de clientes y aumenta tus ingresos diarios</p>
                </div>
            </div>
            
            <div class="benefit-item mb-3">
                <div class="benefit-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="benefit-text">
                    <h4>Horarios flexibles</h4>
                    <p>Trabaja cuando quieras, controla tu tiempo</p>
                </div>
            </div>
            
            <div class="benefit-item mb-3">
                <div class="benefit-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="benefit-text">
                    <h4>Pagos semanales</h4>
                    <p>Recibe tus ganancias de forma puntual cada semana</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="benefit-text">
                    <h4>Seguro incluido</h4>
                    <p>Protección completa durante tus entregas</p>
                </div>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Panel de beneficios -->
        <div class="benefits-panel">
            <h2 class="benefits-title">Beneficios para repartidores</h2>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="benefit-text">
                    <h4>Incrementa tus ventas</h4>
                    <p>Accede a miles de clientes y aumenta tus ingresos diarios</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="benefit-text">
                    <h4>Horarios flexibles</h4>
                    <p>Trabaja cuando quieras, controla tu tiempo</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="benefit-text">
                    <h4>Pagos semanales</h4>
                    <p>Recibe tus ganancias de forma puntual cada semana</p>
                </div>
            </div>
            
            <div class="benefit-item">
                <div class="benefit-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="benefit-text">
                    <h4>Seguro incluido</h4>
                    <p>Protección completa durante tus entregas</p>
                </div>
            </div>
        </div>

        <!-- Panel de registro -->
        <div class="auth-panel">
            <div class="auth-header">
                <img src="../assets/img/logo.png" alt="QuickBite Logo">
                <h1 class="auth-title">Crear cuenta</h1>
                <p class="auth-subtitle">Completa el formulario para comenzar</p>
                <?php if (!empty($error_general)): ?>
                    <div class="alert alert-danger"><?php echo $error_general; ?></div>
                <?php endif; ?>
            </div>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="tu@email.com">
                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Mínimo 8 caracteres">
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
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="tel" name="telefono" id="telefono" class="form-control <?php echo (!empty($telefono_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $telefono; ?>" placeholder="10 dígitos" maxlength="10">
                    <div class="invalid-feedback"><?php echo $telefono_err; ?></div>
                </div>
                
                <div class="form-group">
                    <label for="tipo_vehiculo" class="form-label">Tipo de vehículo</label>
                    <select name="tipo_vehiculo" id="tipo_vehiculo" class="form-control">
                        <option value="bicicleta">🚲 Bicicleta</option>
                        <option value="motocicleta">🏍️ Motocicleta</option>
                        <option value="coche">🚗 Coche</option>
                        <option value="camioneta">🚚 Camioneta</option>
                    </select>
                </div>
                
                <div class="form-group" id="licencia-group" style="display:none;">
                    <label for="licencia" class="form-label">Número de licencia</label>
                    <input type="text" name="licencia" id="licencia" class="form-control <?php echo (!empty($licencia_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $licencia; ?>" placeholder="Número de licencia de conducir">
                    <div class="invalid-feedback"><?php echo $licencia_err; ?></div>
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
                    <i class="fas fa-user-plus me-2"></i>Crear cuenta
                </button>
            </form>
            
            
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
                    <p>Al registrarte como repartidor en QuickBite, aceptas cumplir con estos términos y condiciones.</p>
                    
                    <h6>2. Responsabilidades del repartidor</h6>
                    <p>Te comprometes a entregar los pedidos de manera puntual y en perfecto estado, manteniendo la calidad del servicio.</p>
                    
                    <h6>3. Pagos y comisiones</h6>
                    <p>Los pagos se realizarán semanalmente. QuickBite se reserva el derecho de retener comisiones por el uso de la plataforma.</p>
                    
                    <h6>4. Uso de vehículo</h6>
                    <p>Debes contar con un vehículo en buen estado y, en caso de vehículos motorizados (motocicleta, coche, camioneta), licencia vigente.</p>
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
                    <p>Recopilamos información personal necesaria para el funcionamiento del servicio de entrega.</p>
                    
                    <h6>Uso de la información</h6>
                    <p>Utilizamos tus datos para gestionar entregas, pagos y mejorar nuestros servicios.</p>
                    
                    <h6>Protección de datos</h6>
                    <p>Implementamos medidas de seguridad para proteger tu información personal.</p>
                    
                    <h6>Compartir información</h6>
                    <p>No compartimos tu información personal con terceros sin tu consentimiento.</p>
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
            
            // Mostrar/ocultar campo de licencia
            $('#tipo_vehiculo').change(function() {
                if ($(this).val() === 'motocicleta' || $(this).val() === 'coche' || $(this).val() === 'camioneta') {
                    $('#licencia-group').slideDown();
                } else {
                    $('#licencia-group').slideUp();
                    $('#licencia').val('');
                }
            });
            
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