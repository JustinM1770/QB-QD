<?php
/**
 * repartidor/wallet.php
 * Interfaz de wallet para repartidores/couriers
 */
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);
session_start();

require_once '../../config/database.php';
require_once '../../models/WalletMercadoPago.php';
require_once '../../models/Usuario.php';
// Cargar configuración de MercadoPago
$mp_config = require_once '../../config/mercadopago.php';
// Verificar sesión
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../login.php");
    exit;
}
if ($_SESSION['tipo_usuario'] !== 'repartidor') {
    header("Location: ../../index.php");
    exit;
}

// Conectar BD
$database = new Database();
$db = $database->getConnection();
// Verificar que la configuración de MercadoPago esté disponible
if (!isset($mp_config['access_token']) || empty($mp_config['access_token'])) {
    die('Error: La configuración de MercadoPago no está disponible. Por favor contacta al administrador.');
}

// Inicializar
try {
    $wallet = new WalletMercadoPago($db, $mp_config['access_token'], $mp_config['public_key']);
    $id_usuario = $_SESSION['id_usuario'];
    // Obtener wallet
    $wallet_info = $wallet->obtenerWallet($id_usuario, WalletMercadoPago::TIPO_REPARTIDOR);
} catch (Exception $e) {
    die('Error al inicializar la wallet: ' . $e->getMessage());
}

// Inicializar variables
$resumen = [
    'saldo_disponible' => 0,
    'saldo_pendiente' => 0
];
$transacciones = [];
// Si no existe wallet, crearla
if (!$wallet_info) {
    $usuario = new Usuario($db);
    $usuario->id_usuario = $id_usuario;
    $usuario->obtenerPorId();
    
    try {
        $resultado = $wallet->crearWallet(
            $id_usuario,
            WalletMercadoPago::TIPO_REPARTIDOR,
            $usuario->nombre . ' ' . $usuario->apellido,
            $usuario->email
        );
        
        // Con MercadoPago no necesitamos onboarding URL
        $wallet_info = $wallet->obtenerWallet($id_usuario, WalletMercadoPago::TIPO_REPARTIDOR);
        $mensaje_success = "Wallet creada exitosamente con MercadoPago";
    } catch (Exception $e) {
        $error_wallet = "Error creando wallet: " . $e->getMessage();
    }
}

// Obtener resumen solo si existe wallet
if ($wallet_info) {
    $resumen = $wallet->obtenerResumen($wallet_info['id_wallet']) ?: [
        'saldo_disponible' => 0,
        'saldo_pendiente' => 0
    ];
    $transacciones = $wallet->obtenerTransacciones($wallet_info['id_wallet'], 20) ?: [];
}

// Procesar solicitud de retiro
$mensaje_retiro = '';
$error_retiro = '';
// Obtener datos bancarios del repartidor
$clabe_guardada = '';
$banco_guardado = '';
$titular_guardado = '';
$query = "SELECT clabe, banco, titular_cuenta FROM usuarios WHERE id_usuario = ?";
$stmt = $db->prepare($query);
$stmt->execute([$id_usuario]);
$datos_bancarios = $stmt->fetch(PDO::FETCH_ASSOC);
if ($datos_bancarios) {
    $clabe_guardada = $datos_bancarios['clabe'] ?? '';
    $banco_guardado = $datos_bancarios['banco'] ?? '';
    $titular_guardado = $datos_bancarios['titular_cuenta'] ?? '';
}

// Procesar actualización de datos bancarios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_clabe') {
    $clabe = trim($_POST['clabe']);
    $banco = trim($_POST['banco']);
    $titular = trim($_POST['titular']);
    
    // Validar CLABE (18 dígitos)
    if (!preg_match('/^\d{18}$/', $clabe)) {
        $error_retiro = "La CLABE debe tener exactamente 18 dígitos numéricos";
    } else {
        try {
            $query = "UPDATE usuarios SET clabe = ?, banco = ?, titular_cuenta = ? WHERE id_usuario = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$clabe, $banco, $titular, $id_usuario]);
            
            $clabe_guardada = $clabe;
            $banco_guardado = $banco;
            $titular_guardado = $titular;
            $mensaje_retiro = "Datos bancarios guardados exitosamente";
        } catch (Exception $e) {
            $error_retiro = "Error al guardar datos bancarios: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'solicitar_retiro') {
    $monto = (float)$_POST['monto'];
    // Validar que tenga CLABE registrada
    if (empty($clabe_guardada)) {
        $error_retiro = "Primero debes registrar tu CLABE interbancaria";
    } else {
        try {
            $resultado = $wallet->solicitarRetiro($wallet_info['id_wallet'], $monto);
            if ($resultado['exito']) {
                $mensaje_retiro = "¡Retiro procesado correctamente! El dinero llegará a tu cuenta en 1-2 días hábiles.";
                // Recargar datos
                $resumen = $wallet->obtenerResumen($wallet_info['id_wallet']);
                $transacciones = $wallet->obtenerTransacciones($wallet_info['id_wallet'], 20);
            } else {
                $error_retiro = $resultado['error'];
            }
        } catch (Exception $e) {
            $error_retiro = $e->getMessage();
        }
    }
}
// Con MercadoPago no necesitamos verificar onboarding
$onboarding_completo = true; // Siempre completo con MercadoPago
// Obtener estadísticas de entregas del mes
$stats_mes = [
    'total_entregas' => 0,
    'total_ganancias' => 0
];

try {
    if ($wallet_info) {
        $mes_actual = date('Y-m-01');
    $query_entregas = "SELECT COUNT(*) as total_entregas, COALESCE(SUM(monto), 0) as total_ganancias
                      FROM wallet_transacciones 
                      WHERE id_wallet = :id_wallet 
                      AND tipo = 'ingreso_entrega'
                      AND fecha >= :mes";
    $stmt = $db->prepare($query_entregas);
    $stmt->bindParam(':id_wallet', $wallet_info['id_wallet']);
    $stmt->bindParam(':mes', $mes_actual);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats_mes = $result;
        }
    }
} catch (Exception $e) {
    error_log("Error obteniendo stats: " . $e->getMessage());
}

// Obtener historial de retiros
$historial_retiros = [];
try {
    if ($wallet_info) {
        $query_retiros = "SELECT * FROM wallet_transacciones 
                         WHERE id_wallet = :id_wallet 
                         AND tipo IN ('retiro', 'retiro_procesado', 'retiro_rechazado')
                         ORDER BY fecha DESC 
                         LIMIT 50";
        $stmt = $db->prepare($query_retiros);
        $stmt->bindParam(':id_wallet', $wallet_info['id_wallet']);
        $stmt->execute();
        $historial_retiros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silenciosamente fallar si no existe la tabla
    error_log("Error obteniendo historial: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cartera - QuickBite Repartidor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/repartidor-dashboard.css">
    <style>
        :root {
            --primary: #FF6B00;
            --primary-light: rgba(255, 107, 0, 0.1);
            --dark: #2D3142;
            --gray-200: #E5E7EB;
            --gray-500: #6B7280;
            --secondary: #F9FAFB;
            --success: #10B981;
            --danger: #EF4444;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-light) 0%, #ffffff 100%);
        }
        
        .navbar {
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-size: 1.25rem;
            transition: all 0.2s ease;
        }
        
        .navbar-brand:hover {
            transform: translateX(-3px);
        }
        .wallet-header {
            background: var(--secondary);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .wallet-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .wallet-subtitle {
            color: var(--gray-500);
            font-size: 0.95rem;
        }
        .stat-box {
            background: white;
            padding: 1.75rem;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .stat-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .stat-label {
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-500);
        }
        
        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
        }
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        .transaction-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            background: var(--primary-light);
            color: var(--primary);
        }
        .transaction-details h6 {
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .transaction-date {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin: 0.25rem 0 0 0;
        }
        
        .transaction-amount {
            font-size: 1.0625rem;
            font-weight: 600;
            color: var(--success);
        }
        
        .transaction-amount.negative {
            color: var(--danger);
        }
        .alert {
            border-radius: 12px;
            padding: 1rem 1.25rem;
            border: none;
        }
        
        .alert i {
            margin-right: 0.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            padding: 0.75rem 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
        }
        
        .form-control.is-valid {
            border-color: var(--success);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger);
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: #E65F00;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.25);
        }
        
        .btn-primary:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
        }
        .accordion-button {
            border-radius: 12px !important;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--primary-light);
        }
        
        .accordion-item {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }
        
        .accordion-body {
            padding: 1.25rem;
            line-height: 1.6;
        }
        
        .clabe-input {
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            font-size: 1rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--gray-500);
            padding: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .table tbody tr {
            transition: background 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(255, 107, 0, 0.03);
        }
        
        .table tbody td {
            vertical-align: middle;
            padding: 1rem;
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5rem;
            }
            
            .section-card {
                padding: 1.25rem;
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
    <script>
        // Validación de CLABE en tiempo real
        function validateCLABE(clabe) {
            if (!/^\d{18}$/.test(clabe)) {
                return false;
            }
            
            const weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
            let sum = 0;
            
            for (let i = 0; i < 17; i++) {
                sum += parseInt(clabe[i]) * weights[i];
            }
            
            const checkDigit = (10 - (sum % 10)) % 10;
            return parseInt(clabe[17]) === checkDigit;
        }
        document.addEventListener('DOMContentLoaded', function() {
            const clabeInput = document.getElementById('clabe');
            const submitBtn = document.getElementById('submitBtn');
            if (clabeInput) {
                clabeInput.addEventListener('input', function(e) {
                    const clabe = e.target.value.replace(/\D/g, ''); // Solo números
                    e.target.value = clabe;
                    
                    if (clabe.length === 18) {
                        if (validateCLABE(clabe)) {
                            e.target.classList.remove('is-invalid');
                            e.target.classList.add('is-valid');
                            if (submitBtn) submitBtn.disabled = false;
                        } else {
                            e.target.classList.remove('is-valid');
                            e.target.classList.add('is-invalid');
                            if (submitBtn) submitBtn.disabled = true;
                        }
                    } else {
                        e.target.classList.remove('is-valid', 'is-invalid');
                        if (submitBtn) submitBtn.disabled = clabe.length > 0;
                    }
                });
            }
        });
    </script>
</head>
<body>
    <!-- Header con navegación -->
    <nav class="navbar navbar-expand-lg" style="background: var(--primary); box-shadow: 0 2px 12px rgba(0,0,0,0.1);">
        <div class="container">
            <a href="../repartidor_dashboard.php" class="navbar-brand text-white d-flex align-items-center">
                <i class="fas fa-arrow-left me-2"></i>
                <span class="fw-bold">QuickBite</span>
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Repartidor'); ?>
                </span>
                <a href="../../logout.php" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </nav>
    <div class="wallet-header">
        <div class="container">
            <h1 class="wallet-title"><i class="fas fa-wallet"></i> Mi Cartera</h1>
            <p class="wallet-subtitle">Gestiona tus ganancias por entregas</p>
        </div>
    </div>
    <div class="container pb-5">
        <!-- Mensajes -->
        <?php if (isset($mensaje_success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje_success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($mensaje_retiro): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje_retiro; ?>
        </div>
        <?php endif; ?>
        <?php if ($error_retiro): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_retiro; ?>
        </div>
        <?php endif; ?>
        <!-- Estadísticas Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-label">Disponible</div>
                    <div class="stat-value">$<?php echo number_format($resumen['saldo_disponible'], 2); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-label">Pendiente</div>
                    <div class="stat-value">$<?php echo number_format($resumen['saldo_pendiente'], 2); ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    <div class="stat-label">Entregas Mes</div>
                    <div class="stat-value"><?php echo $stats_mes['total_entregas'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-label">Ganancia Mes</div>
                    <div class="stat-value">$<?php echo number_format($stats_mes['total_ganancias'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        <!-- Datos Bancarios -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-university"></i> Datos Bancarios
            </h2>
            <?php if (empty($clabe_guardada)): ?>
            <div class="alert alert-warning mb-4">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Configura tu cuenta bancaria</strong> para poder realizar retiros.
            </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_clabe">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">CLABE Interbancaria * <small class="text-muted">(18 dígitos)</small></label>
                        <input type="text" class="form-control clabe-input" name="clabe" id="clabe"
                               value="<?php echo htmlspecialchars($clabe_guardada); ?>"
                               placeholder="012345678901234567" maxlength="18" 
                               pattern="\d{18}" required>
                        <small class="text-muted">
                            <span id="clabe-status"></span>
                        </small>
                    </div>
                        <label class="form-label">Banco *</label>
                        <select class="form-control" name="banco" required>
                            <option value="">Selecciona tu banco</option>
                            <option value="BBVA" <?php echo $banco_guardado === 'BBVA' ? 'selected' : ''; ?>>BBVA</option>
                            <option value="Santander" <?php echo $banco_guardado === 'Santander' ? 'selected' : ''; ?>>Santander</option>
                            <option value="Banorte" <?php echo $banco_guardado === 'Banorte' ? 'selected' : ''; ?>>Banorte</option>
                            <option value="HSBC" <?php echo $banco_guardado === 'HSBC' ? 'selected' : ''; ?>>HSBC</option>
                            <option value="Scotiabank" <?php echo $banco_guardado === 'Scotiabank' ? 'selected' : ''; ?>>Scotiabank</option>
                            <option value="Citibanamex" <?php echo $banco_guardado === 'Citibanamex' ? 'selected' : ''; ?>>Citibanamex</option>
                            <option value="Inbursa" <?php echo $banco_guardado === 'Inbursa' ? 'selected' : ''; ?>>Inbursa</option>
                            <option value="Azteca" <?php echo $banco_guardado === 'Azteca' ? 'selected' : ''; ?>>Banco Azteca</option>
                            <option value="BanRegio" <?php echo $banco_guardado === 'BanRegio' ? 'selected' : ''; ?>>BanRegio</option>
                            <option value="Otro" <?php echo !in_array($banco_guardado, ['BBVA','Santander','Banorte','HSBC','Scotiabank','Citibanamex','Inbursa','Azteca','BanRegio','']) && $banco_guardado ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Titular de la Cuenta *</label>
                        <input type="text" class="form-control" name="titular" 
                               value="<?php echo htmlspecialchars($titular_guardado); ?>"
                               placeholder="NOMBRE COMPLETO COMO APARECE EN EL BANCO" 
                               style="text-transform: uppercase;" required>
                        <small class="text-muted">Debe coincidir exactamente con tu identificación oficial</small>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <i class="fas fa-save"></i> Guardar Datos Bancarios
                        </button>
                    </div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Seguridad:</strong> Tus datos bancarios se almacenan de forma segura y encriptada. Solo se usan para procesar tus retiros.
            </form>
        <!-- Solicitar Retiro -->
        <?php if ($resumen['saldo_disponible'] >= 100): ?>
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-arrow-down"></i> Solicitar Retiro
            </h2>
            <form method="POST">
                <input type="hidden" name="accion" value="solicitar_retiro">
                <div class="row align-items-end">
                    <div class="col-md-8 mb-3 mb-md-0">
                        <label class="form-label">Monto a Retirar</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="monto" 
                                   min="100" step="0.01" 
                                   max="<?php echo $resumen['saldo_disponible']; ?>"
                                   placeholder="Ingresa el monto" required>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Mínimo: $100 • Disponible: $<?php echo number_format($resumen['saldo_disponible'], 2); ?>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Retirar Dinero
                <div class="alert alert-info mt-4 mb-0">
                    <i class="fas fa-info-circle"></i>
                    <strong>Información:</strong> Los retiros se transfieren automáticamente en 1-2 días hábiles.
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="section-card">
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle"></i> 
                Necesitas acumular mínimo $100 para realizar un retiro. 
                <strong>Saldo actual: $<?php echo number_format($resumen['saldo_disponible'], 2); ?></strong>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Historial de Retiros -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-file-invoice-dollar"></i> Historial de Retiros
            </h2>
            <?php if (empty($historial_retiros)): ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> Aún no has realizado ningún retiro.
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Cuenta</th>
                            <th>Estado</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial_retiros as $retiro): 
                            $estado_class = 'warning';
                            $estado_icono = 'clock';
                            $estado_texto = 'Pendiente';
                            
                            if ($retiro['tipo'] === 'retiro_procesado') {
                                $estado_class = 'success';
                                $estado_icono = 'check-circle';
                                $estado_texto = 'Completado';
                            } elseif ($retiro['tipo'] === 'retiro_rechazado') {
                                $estado_class = 'danger';
                                $estado_icono = 'times-circle';
                                $estado_texto = 'Rechazado';
                            }
                        ?>
                            <td><?php echo date('d/m/Y H:i', strtotime($retiro['fecha'])); ?></td>
                            <td class="fw-bold text-danger">
                                -$<?php echo number_format(abs($retiro['monto']), 2); ?>
                            </td>
                            <td>
                                <?php if (!empty($clabe_guardada)): ?>
                                    <small class="text-muted">
                                        <?php echo $banco_guardado; ?><br>
                                        ****<?php echo substr($clabe_guardada, -4); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">No especificada</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $estado_class; ?>">
                                    <i class="fas fa-<?php echo $estado_icono; ?>"></i>
                                    <?php echo $estado_texto; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($retiro['notas'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($retiro['notas']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Historial de Transacciones -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-history"></i> Historial de Transacciones
            </h2>
            <?php if (empty($transacciones)): ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> Aún no tienes transacciones registradas.
            </div>
            <?php else: ?>
            <div>
                <?php foreach ($transacciones as $trans): ?>
                <div class="transaction-item">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="transaction-icon">
                            <i class="fas <?php echo $trans['tipo'] === 'ingreso_entrega' ? 'fa-truck' : 'fa-arrow-down'; ?>"></i>
                        <div class="transaction-details">
                            <h6><?php 
                                echo $trans['tipo'] === 'ingreso_entrega' 
                                    ? 'Pago por Entrega' 
                                    : ucfirst(str_replace('_', ' ', $trans['tipo'])); 
                            ?></h6>
                            <p class="transaction-date"><?php echo date('d/m/Y H:i', strtotime($trans['fecha'])); ?></p>
                        </div>
                    </div>
                    <div class="transaction-amount <?php echo $trans['monto'] < 0 ? 'negative' : ''; ?>">
                        <?php echo ($trans['monto'] >= 0 ? '+' : '') . '$' . number_format(abs($trans['monto']), 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Preguntas Frecuentes -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-question-circle"></i> Preguntas Frecuentes
            </h2>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            ¿Cuándo recibo mis ganancias?
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Recibes el pago inmediatamente después de completar cada entrega. 
                            El dinero aparece en tu cartera digital y puedes retirarlo cuando acumules mínimo $100.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            ¿Cuánto tarda el retiro?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Los retiros se transfieren automáticamente a tu cuenta bancaria en 1-2 días hábiles.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            ¿Hay comisión en los retiros?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            QuickBite no cobra comisión por retiros. Transferimos el 100% de tus ganancias.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
