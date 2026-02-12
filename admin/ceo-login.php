<?php
// Configurar reporte de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor ingresa email y contraseña.';
    } else {
        try {
            // Buscar usuario CEO por email
            $query = "SELECT * FROM usuarios WHERE email = :email AND tipo_usuario = 'ceo' AND activo = 1 LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_data) {
                // Verificar contraseña
                if (password_verify($password, $user_data['password'])) {
                    // Login exitoso
                    $_SESSION['loggedin'] = true;
                    $_SESSION['id_usuario'] = $user_data['id_usuario'];
                    $_SESSION['tipo_usuario'] = 'ceo';
                    $_SESSION['nombre'] = $user_data['nombre'];
                    $_SESSION['email'] = $user_data['email'];
                    
                    // Redirigir al panel CEO
                    header('Location: ceo-panel.php');
                    exit;
                } else {
                    $error = 'Contraseña incorrecta.';
                }
            } else {
                $error = 'Usuario no encontrado o no autorizado.';
            }
        } catch (Exception $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login CEO - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo-section i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
            font-size: 1.8rem;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .error-message {
            background: #ffe6e6;
            color: #d63384;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
            border-left: 4px solid #d63384;
        }
        .success-message {
            background: #e6f3e6;
            color: #198754;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
            border-left: 4px solid #198754;
        }
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            z-index: 10;
        }
        .input-group .form-control {
            padding-left: 45px;
        }
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .back-link a:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <i class="fas fa-user-shield"></i>
            <h1>Panel de Administración</h1>
            <p class="subtitle">Acceso exclusivo para administradores</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    placeholder="correo@ejemplo.com"
                    required 
                    autofocus 
                />
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <label for="password" class="form-label">Contraseña</label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    placeholder="Ingresa tu contraseña"
                    required 
                />
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-sign-in-alt me-2"></i>
                Ingresar al Panel
            </button>
        </form>

        <div class="back-link">
            <a href="../index.php">
                <i class="fas fa-arrow-left me-2"></i>
                Volver al sitio web
            </a>
        </div>
    </div>

</body>
</html>