<?php
/**
 * Wallet Repartidor - Panel de billetera para repartidores
 * Diseño minimalista y responsivo
 */
ini_set('display_errors', 0);
error_reporting(0);
session_start();

require_once '../../config/database.php';
require_once '../../models/WalletMercadoPago.php';
require_once '../../models/Usuario.php';

// Verificar sesión
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../login.php");
    exit;
}

if ($_SESSION['tipo_usuario'] !== 'repartidor') {
    header("Location: ../../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$mp_config = require '../../config/mercadopago.php';
$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del repartidor
$stmt = $db->prepare("SELECT r.*, u.nombre, u.apellido FROM repartidores r JOIN usuarios u ON r.id_usuario = u.id_usuario WHERE r.id_usuario = ?");
$stmt->execute([$id_usuario]);
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

$clabe_guardada = $repartidor['cuenta_clabe'] ?? '';
$banco_guardado = $repartidor['banco'] ?? '';
$titular_guardado = $repartidor['titular_cuenta'] ?? '';
$nombre_completo = trim(($repartidor['nombre'] ?? '') . ' ' . ($repartidor['apellido'] ?? ''));

// Inicializar wallet
$wallet = new WalletMercadoPago($db, $mp_config['access_token'], $mp_config['public_key']);
$wallet_info = $wallet->obtenerWallet($id_usuario, 'courier');

if (!$wallet_info) {
    $wallet->crearWallet($id_usuario, 'courier', $nombre_completo, $_SESSION['email'] ?? '');
    $wallet_info = $wallet->obtenerWallet($id_usuario, 'courier');
}

$resumen = $wallet_info ? $wallet->obtenerResumen($wallet_info['id_wallet']) : ['saldo_disponible' => 0, 'saldo_pendiente' => 0];
$transacciones = $wallet_info ? $wallet->obtenerTransacciones($wallet_info['id_wallet'], 20) : [];

// Ganancias efectivo vs digital
$ganancias_digitales = 0;
$ganancias_efectivo = 0;
$entregas_mes = 0;
if ($wallet_info) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM wallet_transacciones WHERE id_wallet = ? AND tipo IN ('ingreso_entrega', 'ingreso') AND estado = 'completado'");
    $stmt->execute([$wallet_info['id_wallet']]);
    $ganancias_digitales = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);

    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM wallet_transacciones WHERE id_wallet = ? AND tipo = 'ganancia_efectivo' AND estado = 'completado'");
    $stmt->execute([$wallet_info['id_wallet']]);
    $ganancias_efectivo = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);

    // Stats del mes
    $mes_actual = date('Y-m-01');
    $stmt = $db->prepare("SELECT COUNT(*) as entregas, COALESCE(SUM(monto), 0) as ingresos FROM wallet_transacciones WHERE id_wallet = ? AND tipo IN ('ingreso_entrega', 'ingreso', 'ganancia_efectivo') AND fecha >= ?");
    $stmt->execute([$wallet_info['id_wallet'], $mes_actual]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $entregas_mes = $stats['entregas'] ?? 0;
    $ingresos_mes = $stats['ingresos'] ?? 0;
}

// Procesar formularios
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_clabe') {
        $clabe = preg_replace('/\D/', '', $_POST['clabe'] ?? '');
        $banco = trim($_POST['banco'] ?? '');
        $titular = trim($_POST['titular'] ?? '');

        if (strlen($clabe) !== 18) {
            $error = "La CLABE debe tener 18 dígitos";
        } else {
            $stmt = $db->prepare("UPDATE repartidores SET cuenta_clabe = ?, banco = ?, titular_cuenta = ? WHERE id_usuario = ?");
            $stmt->execute([$clabe, $banco, $titular, $id_usuario]);
            $clabe_guardada = $clabe;
            $banco_guardado = $banco;
            $titular_guardado = $titular;
            $mensaje = "Datos bancarios actualizados correctamente";
        }
    } elseif ($_POST['accion'] === 'solicitar_retiro' && !empty($clabe_guardada)) {
        $monto = floatval($_POST['monto'] ?? 0);
        if ($monto >= 100 && $monto <= $resumen['saldo_disponible']) {
            try {
                $resultado = $wallet->solicitarRetiro($wallet_info['id_wallet'], $monto);
                if ($resultado['exito']) {
                    $mensaje = "Retiro procesado. Llegará a tu cuenta en 1-2 días hábiles.";
                    $resumen = $wallet->obtenerResumen($wallet_info['id_wallet']);
                    $transacciones = $wallet->obtenerTransacciones($wallet_info['id_wallet'], 20);
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = "Monto inválido. Mínimo $100, máximo tu saldo disponible.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mi Cartera - QuickBite Repartidor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/global-theme.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--body-bg, #f5f5f7);
            color: var(--text-main, #1d1d1f);
            min-height: 100vh;
            padding-bottom: 80px;
            -webkit-font-smoothing: antialiased;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, #00C853 0%, #00A843 100%);
            padding: 1rem 1rem 3rem;
            color: white;
        }
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .back-link {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .back-link:hover { color: white; }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }
        .page-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        /* Container */
        .main-container {
            max-width: 540px;
            margin: 0 auto;
            padding: 0 1rem 2rem;
        }

        /* Balance Card */
        .balance-card {
            background: var(--card-bg, white);
            border-radius: 20px;
            padding: 1.75rem;
            margin-top: -2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
            border: 1px solid var(--border-color, rgba(0,0,0,0.05));
        }
        .balance-label {
            font-size: 0.8rem;
            color: var(--muted-text, #86868b);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        .balance-amount {
            font-size: 2.75rem;
            font-weight: 700;
            color: var(--text-main, #1d1d1f);
            margin: 0.25rem 0;
            letter-spacing: -1px;
        }
        .balance-pending {
            font-size: 0.85rem;
            color: var(--muted-text, #86868b);
        }
        .balance-pending i { margin-right: 0.25rem; }

        /* Stats Grid */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin: 1.25rem 0;
        }
        .stat-card {
            background: var(--card-bg, white);
            border-radius: 14px;
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border-color, rgba(0,0,0,0.05));
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main, #1d1d1f);
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--muted-text, #86868b);
            margin-top: 0.25rem;
        }

        /* Section */
        .section {
            background: var(--card-bg, white);
            border-radius: 14px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color, rgba(0,0,0,0.05));
            overflow: hidden;
        }
        .section-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-main, #1d1d1f);
        }
        .section-header i { color: #00C853; font-size: 0.85rem; }
        .section-body { padding: 1rem 1.25rem; }

        /* Earnings List */
        .earning-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 0;
            border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
        }
        .earning-item:last-child { border-bottom: none; }
        .earning-left {
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }
        .earning-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .earning-icon.digital { background: #E3F2FD; color: #1565C0; }
        .earning-icon.cash { background: #E8F5E9; color: #2E7D32; }
        .earning-title { font-weight: 500; font-size: 0.9rem; }
        .earning-subtitle { font-size: 0.75rem; color: var(--muted-text, #86868b); }
        .earning-amount { font-weight: 600; font-size: 0.95rem; }
        .earning-badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 100px;
            font-weight: 500;
        }

        /* Bank Account */
        .bank-status {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .bank-status.warning {
            background: linear-gradient(135deg, #FFF8E1 0%, #FFECB3 100%);
        }
        .bank-status.success {
            background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        }
        .bank-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .bank-details { flex: 1; }
        .bank-name { font-weight: 600; font-size: 0.95rem; }
        .bank-clabe { font-size: 0.8rem; color: var(--muted-text, #666); font-family: monospace; }

        /* Form */
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.35rem;
            color: var(--text-main, #1d1d1f);
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            border: 1.5px solid var(--border-color, #e5e5e5);
            background: var(--input-bg, white);
            color: var(--text-main, #1d1d1f);
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #00C853;
            box-shadow: 0 0 0 4px rgba(0,200,83,0.1);
            outline: none;
        }
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 1.5px solid var(--border-color, #e5e5e5);
            border-right: none;
            background: var(--card-bg, #f5f5f7);
        }
        .input-group .form-control { border-radius: 0 10px 10px 0; }

        /* Buttons */
        .btn-primary {
            background: #00C853;
            border: none;
            border-radius: 10px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: #00A843; transform: translateY(-1px); }
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }

        /* Transaction List */
        .tx-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 0;
            border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.05));
        }
        .tx-item:last-child { border-bottom: none; }
        .tx-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.875rem;
            flex-shrink: 0;
        }
        .tx-icon.income { background: #E8F5E9; color: #2E7D32; }
        .tx-icon.withdraw { background: #FFF3E0; color: #EF6C00; }
        .tx-icon.cash { background: #F3E5F5; color: #7B1FA2; }
        .tx-info { flex: 1; min-width: 0; }
        .tx-title {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-main, #1d1d1f);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tx-date { font-size: 0.75rem; color: var(--muted-text, #86868b); }
        .tx-amount {
            font-weight: 600;
            font-size: 0.95rem;
            text-align: right;
        }
        .tx-amount.positive { color: #2E7D32; }
        .tx-amount.negative { color: #C62828; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--muted-text, #86868b);
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.4;
            margin-bottom: 0.75rem;
        }

        /* Alert */
        .alert {
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
        }

        /* Modal */
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color, #eee);
        }
        .modal-body { padding: 1.5rem; }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color, #eee);
        }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--nav-bg, white);
            border-top: 1px solid var(--border-color, #eee);
            display: flex;
            justify-content: space-around;
            padding: 0.5rem 0 calc(0.5rem + env(safe-area-inset-bottom));
            z-index: 1000;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--muted-text, #86868b);
            font-size: 0.7rem;
            padding: 0.5rem;
            min-width: 60px;
        }
        .nav-item i { font-size: 1.25rem; margin-bottom: 0.25rem; }
        .nav-item.active { color: #00C853; }

        /* Responsive */
        @media (max-width: 576px) {
            .page-header { padding: 1rem 1rem 2.5rem; }
            .page-title { font-size: 1.5rem; }
            .balance-card { margin-top: -2rem; padding: 1.25rem; border-radius: 16px; }
            .balance-amount { font-size: 2.25rem; }
            .main-container { padding: 0 0.75rem 2rem; }
            .section { border-radius: 12px; }
            .stat-card { padding: 0.875rem; }
            .stat-value { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="main-container" style="padding-bottom:0;">
            <div class="header-top">
                <a href="../repartidor_dashboard.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Dashboard
                </a>
            </div>
            <h1 class="page-title"><i class="fas fa-wallet me-2"></i>Mi Cartera</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($nombre_completo); ?></p>
        </div>
    </div>

    <div class="main-container">
        <?php if ($mensaje): ?>
        <div class="alert alert-success mt-3">
            <i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger mt-3">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Balance Card -->
        <div class="balance-card">
            <div class="balance-label">Disponible para retiro</div>
            <div class="balance-amount">$<?php echo number_format($resumen['saldo_disponible'], 2); ?></div>
            <?php if ($resumen['saldo_pendiente'] > 0): ?>
            <div class="balance-pending">
                <i class="fas fa-clock"></i> $<?php echo number_format($resumen['saldo_pendiente'], 2); ?> en proceso
            </div>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $entregas_mes; ?></div>
                <div class="stat-label">Entregas del mes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($ingresos_mes ?? 0, 0); ?></div>
                <div class="stat-label">Ganancias del mes</div>
            </div>
        </div>

        <!-- Desglose -->
        <?php if ($ganancias_digitales > 0 || $ganancias_efectivo > 0): ?>
        <div class="section">
            <div class="section-header"><i class="fas fa-chart-pie"></i> Desglose</div>
            <div class="section-body" style="padding-top:0;">
                <div class="earning-item">
                    <div class="earning-left">
                        <div class="earning-icon digital"><i class="fas fa-credit-card"></i></div>
                        <div>
                            <div class="earning-title">Pagos digitales</div>
                            <div class="earning-subtitle">Transferidos a wallet</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="earning-amount">$<?php echo number_format($ganancias_digitales, 2); ?></div>
                        <span class="earning-badge bg-success text-white">Retirable</span>
                    </div>
                </div>
                <div class="earning-item">
                    <div class="earning-left">
                        <div class="earning-icon cash"><i class="fas fa-money-bill-wave"></i></div>
                        <div>
                            <div class="earning-title">Cobrado en efectivo</div>
                            <div class="earning-subtitle">Del cliente directamente</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="earning-amount">$<?php echo number_format($ganancias_efectivo, 2); ?></div>
                        <span class="earning-badge bg-secondary text-white">Ya en mano</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cuenta Bancaria -->
        <div class="section">
            <div class="section-header"><i class="fas fa-university"></i> Cuenta para retiros</div>
            <div class="section-body">
                <?php if (empty($clabe_guardada)): ?>
                <div class="bank-status warning mb-3">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    <strong>Registra tu CLABE</strong> para retirar tu dinero
                </div>
                <?php else: ?>
                <div class="bank-status success mb-3">
                    <div class="bank-info">
                        <div class="bank-details">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span class="bank-name"><?php echo htmlspecialchars($banco_guardado); ?></span>
                            <div class="bank-clabe mt-1">
                                <?php echo substr($clabe_guardada, 0, 4) . ' •••• •••• ' . substr($clabe_guardada, -4); ?>
                                <br><small><?php echo htmlspecialchars($titular_guardado); ?></small>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#formClabe">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="collapse <?php echo empty($clabe_guardada) ? 'show' : ''; ?>" id="formClabe">
                    <form method="POST" id="formClabeRepartidor">
                        <input type="hidden" name="accion" value="guardar_clabe">
                        <div class="mb-3">
                            <label class="form-label">CLABE Interbancaria</label>
                            <input type="text" class="form-control" name="clabe" id="inputClabe"
                                   maxlength="18" inputmode="numeric"
                                   value="<?php echo htmlspecialchars($clabe_guardada); ?>"
                                   placeholder="18 dígitos" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Banco</label>
                                <select class="form-select" name="banco" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach (['BBVA', 'Santander', 'Banorte', 'Citibanamex', 'HSBC', 'Scotiabank', 'Banco Azteca', 'BanCoppel', 'Nu México', 'Mercado Pago', 'Spin by Oxxo', 'Otro'] as $b): ?>
                                    <option value="<?php echo $b; ?>" <?php echo $banco_guardado === $b ? 'selected' : ''; ?>><?php echo $b; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Titular</label>
                                <input type="text" class="form-control" name="titular"
                                       value="<?php echo htmlspecialchars($titular_guardado); ?>" required>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary w-100" onclick="confirmarClabe()">
                            <i class="fas fa-save me-2"></i>Guardar cuenta
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Retiro -->
        <?php if (!empty($clabe_guardada) && $resumen['saldo_disponible'] >= 100): ?>
        <div class="section">
            <div class="section-header"><i class="fas fa-paper-plane"></i> Solicitar retiro</div>
            <div class="section-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="solicitar_retiro">
                    <div class="mb-3">
                        <label class="form-label">Monto a retirar</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="monto"
                                   min="100" max="<?php echo $resumen['saldo_disponible']; ?>"
                                   step="0.01" required>
                        </div>
                        <small class="text-muted">Mín. $100 · Disponible: $<?php echo number_format($resumen['saldo_disponible'], 2); ?></small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-arrow-right me-2"></i>Retirar
                    </button>
                </form>
            </div>
        </div>
        <?php elseif (!empty($clabe_guardada)): ?>
        <div class="section">
            <div class="section-header"><i class="fas fa-paper-plane"></i> Retiros</div>
            <div class="section-body">
                <div class="empty-state" style="padding:1.5rem;">
                    <i class="fas fa-piggy-bank"></i>
                    <p class="mb-0">Necesitas mínimo $100</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historial -->
        <div class="section">
            <div class="section-header"><i class="fas fa-history"></i> Movimientos</div>
            <div class="section-body" style="padding-top:0;">
                <?php if (empty($transacciones)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>Sin movimientos aún</p>
                </div>
                <?php else: ?>
                <?php foreach ($transacciones as $t):
                    $esEfectivo = $t['tipo'] === 'ganancia_efectivo';
                    $esRetiro = $t['tipo'] === 'retiro';
                ?>
                <div class="tx-item">
                    <div class="tx-icon <?php echo $esRetiro ? 'withdraw' : ($esEfectivo ? 'cash' : 'income'); ?>">
                        <i class="fas <?php echo $esRetiro ? 'fa-arrow-down' : ($esEfectivo ? 'fa-money-bill' : 'fa-motorcycle'); ?>"></i>
                    </div>
                    <div class="tx-info">
                        <div class="tx-title">
                            <?php echo $esEfectivo ? 'Entrega efectivo' : ($esRetiro ? 'Retiro' : 'Entrega'); ?>
                            <?php if ($esEfectivo): ?>
                            <span class="badge bg-secondary" style="font-size:0.6rem;">EN MANO</span>
                            <?php endif; ?>
                        </div>
                        <div class="tx-date"><?php echo date('d M Y, H:i', strtotime($t['fecha'])); ?></div>
                    </div>
                    <div class="tx-amount <?php echo $t['monto'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($t['monto'] >= 0 ? '+' : '') . '$' . number_format(abs($t['monto']), 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bottom Nav -->
    <div class="bottom-nav">
        <a href="../repartidor_dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Inicio</span>
        </a>
        <a href="../repartidor_dashboard.php#pedidos" class="nav-item">
            <i class="fas fa-list"></i>
            <span>Pedidos</span>
        </a>
        <a href="wallet.php" class="nav-item active">
            <i class="fas fa-wallet"></i>
            <span>Cartera</span>
        </a>
        <a href="../repartidor_dashboard.php#perfil" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalClabe" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirmar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Verifica que los datos sean correctos:</p>
                    <div class="p-3 rounded bg-light">
                        <p class="mb-1"><strong>CLABE:</strong> <span id="confirmClabe" style="font-family:monospace;"></span></p>
                        <p class="mb-1"><strong>Banco:</strong> <span id="confirmBanco"></span></p>
                        <p class="mb-0"><strong>Titular:</strong> <span id="confirmTitular"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="enviarForm()">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('inputClabe')?.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 18);
        });

        function confirmarClabe() {
            const clabe = document.querySelector('[name="clabe"]').value.replace(/\D/g, '');
            const banco = document.querySelector('[name="banco"]');
            const titular = document.querySelector('[name="titular"]').value;
            if (clabe.length !== 18) { alert('CLABE debe tener 18 dígitos'); return; }
            document.getElementById('confirmClabe').textContent = clabe;
            document.getElementById('confirmBanco').textContent = banco.options[banco.selectedIndex].text;
            document.getElementById('confirmTitular').textContent = titular;
            new bootstrap.Modal(document.getElementById('modalClabe')).show();
        }

        function enviarForm() {
            bootstrap.Modal.getInstance(document.getElementById('modalClabe')).hide();
            document.getElementById('formClabeRepartidor').submit();
        }
    </script>
</body>
</html>
