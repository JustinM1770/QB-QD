<?php
/**
 * QuickBite - Panel de Gamificación para Repartidores
 * Muestra niveles, progreso, recompensas y estado de deuda
 */

session_start();
ini_set('display_errors', 0);
error_reporting(0);

// Verificar sesión
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'repartidor') {
    header("Location: ../../login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../models/Usuario.php';

$database = new Database();
$db = $database->getConnection();

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del repartidor
$stmt = $db->prepare("
    SELECT r.*, u.nombre, u.apellido, u.email, u.telefono
    FROM repartidores r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.id_usuario = ?
");
$stmt->execute([$id_usuario]);
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repartidor) {
    header("Location: ../repartidor_dashboard.php");
    exit;
}

$id_repartidor = $repartidor['id_repartidor'];

// Obtener todos los niveles
$stmt = $db->query("SELECT * FROM niveles_repartidor WHERE activo = 1 ORDER BY orden ASC");
$niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nivel actual del repartidor
$nivel_actual = null;
$proximo_nivel = null;
foreach ($niveles as $i => $n) {
    if ($n['id_nivel'] == ($repartidor['id_nivel'] ?? 1)) {
        $nivel_actual = $n;
        if (isset($niveles[$i + 1])) {
            $proximo_nivel = $niveles[$i + 1];
        }
    }
}

// Estadísticas
$total_entregas = $repartidor['total_entregas'] ?? 0;
$calificacion = $repartidor['calificacion_promedio'] ?? 5.0;
$saldo_deuda = $repartidor['saldo_deuda'] ?? 0;
$bloqueado = $repartidor['bloqueado_por_deuda'] ?? 0;

// Calcular progreso hacia siguiente nivel
$progreso = 0;
$entregas_faltantes = 0;
if ($proximo_nivel) {
    $entregas_faltantes = $proximo_nivel['entregas_requeridas'] - $total_entregas;
    $rango = $proximo_nivel['entregas_requeridas'] - ($nivel_actual['entregas_requeridas'] ?? 0);
    $avance = $total_entregas - ($nivel_actual['entregas_requeridas'] ?? 0);
    $progreso = min(100, max(0, ($avance / $rango) * 100));
}

// Obtener historial de niveles
$stmt = $db->prepare("
    SELECT h.*, n1.nombre as nivel_anterior_nombre, n2.nombre as nivel_nuevo_nombre,
           n2.emoji as nivel_nuevo_emoji, n2.recompensa
    FROM historial_niveles_repartidor h
    LEFT JOIN niveles_repartidor n1 ON h.id_nivel_anterior = n1.id_nivel
    LEFT JOIN niveles_repartidor n2 ON h.id_nivel_nuevo = n2.id_nivel
    WHERE h.id_repartidor = ?
    ORDER BY h.fecha_cambio DESC
    LIMIT 5
");
$stmt->execute([$id_repartidor]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener recompensas pendientes
$stmt = $db->prepare("
    SELECT r.*, n.nombre as nivel_nombre, n.emoji
    FROM recompensas_repartidor r
    JOIN niveles_repartidor n ON r.id_nivel = n.id_nivel
    WHERE r.id_repartidor = ?
    ORDER BY r.fecha_solicitud DESC
");
$stmt->execute([$id_repartidor]);
$recompensas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener deudas pendientes
$stmt = $db->prepare("
    SELECT d.*, p.id_pedido
    FROM deudas_comisiones d
    JOIN pedidos p ON d.id_pedido = p.id_pedido
    WHERE d.id_repartidor = ? AND d.estado IN ('pendiente', 'parcial')
    ORDER BY d.fecha_creacion DESC
    LIMIT 10
");
$stmt->execute([$id_repartidor]);
$deudas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar solicitud de recompensa
$mensaje = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reclamar_recompensa'])) {
    $direccion = trim($_POST['direccion_envio'] ?? '');
    if (!empty($direccion)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO recompensas_repartidor (id_repartidor, id_nivel, recompensa, direccion_envio)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id_repartidor, $nivel_actual['id_nivel'], $nivel_actual['recompensa'], $direccion]);

            // Marcar como reclamada
            $db->prepare("UPDATE repartidores SET recompensa_reclamada = 1 WHERE id_repartidor = ?")->execute([$id_repartidor]);
            $repartidor['recompensa_reclamada'] = 1;

            $mensaje = ['tipo' => 'success', 'texto' => '¡Recompensa solicitada! Te contactaremos pronto para el envío.'];
        } catch (Exception $e) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Error al solicitar recompensa: ' . $e->getMessage()];
        }
    }
}

// === SISTEMA DE REFERIDOS ===
require_once '../../api/ReferralRepartidor.php';

$referralRepartidor = new ReferralRepartidor($db);

// Obtener datos de referidos
$datosReferidos = [
    'total_referidos' => 0,
    'referidos_activos' => 0,
    'total_bonificaciones' => 0,
    'codigo_referido' => '',
    'enlace_referido' => '',
    'historial_referidos' => [],
    'historial_bonificaciones' => [],
    'config_bonificaciones' => []
];

try {
    $datosReferidos = $referralRepartidor->obtenerEstadisticasReferidos($id_repartidor);
    $datosReferidos['enlace_referido'] = $referralRepartidor->generarEnlaceReferido($id_repartidor);
    $datosReferidos['historial_referidos'] = $referralRepartidor->obtenerHistorialReferidos($id_repartidor);
    $datosReferidos['historial_bonificaciones'] = $referralRepartidor->obtenerHistorialBonificaciones($id_repartidor);
    $datosReferidos['config_bonificaciones'] = $referralRepartidor->obtenerConfiguracionBonificaciones();
} catch (Exception $e) {
    error_log("Error al cargar datos de referidos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Nivel - QuickBite Repartidor</title>
    <link rel="icon" type="image/x-icon" href="../../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #00D1B2;
            --primary-dark: #00A78E;
            --gold: #FFD700;
            --silver: #C0C0C0;
            --bronze: #CD7F32;
            --diamond: #00D4FF;
            --danger: #EF4444;
            --success: #22C55E;
            --warning: #F59E0B;
            --dark: #1A1A1A;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-500: #64748B;
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
            padding-bottom: 80px;
        }

        h1, h2, h3 { font-family: 'DM Sans', sans-serif; }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .container { max-width: 800px; margin: 0 auto; padding: 1.5rem; }

        /* Nivel Card */
        .nivel-card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            margin-top: -30px;
            position: relative;
            z-index: 10;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .nivel-badge {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .nivel-nombre {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .nivel-nombre.bronce { color: var(--bronze); }
        .nivel-nombre.plata { color: var(--silver); }
        .nivel-nombre.oro { color: var(--gold); }
        .nivel-nombre.diamante { color: var(--diamond); }

        .entregas-count {
            font-size: 1.25rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
        }

        .calificacion {
            background: var(--gray-100);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .calificacion i { color: var(--warning); }

        /* Progreso */
        .progreso-section {
            background: var(--gray-100);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .progreso-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .progreso-bar {
            height: 12px;
            background: white;
            border-radius: 6px;
            overflow: hidden;
        }

        .progreso-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--gold) 100%);
            border-radius: 6px;
            transition: width 0.5s ease;
        }

        .entregas-faltantes {
            text-align: center;
            margin-top: 1rem;
            color: var(--gray-500);
        }

        /* Recompensa Card */
        .recompensa-card {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: var(--dark);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .recompensa-card h3 {
            margin-bottom: 0.5rem;
        }

        .btn-reclamar {
            background: white;
            color: var(--dark);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            margin-top: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-reclamar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Niveles Grid */
        .niveles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .nivel-item {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-lg);
            border: 2px solid var(--gray-100);
            opacity: 0.5;
        }

        .nivel-item.alcanzado {
            opacity: 1;
            border-color: var(--success);
        }

        .nivel-item.actual {
            opacity: 1;
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .nivel-item .emoji { font-size: 2.5rem; }
        .nivel-item .nombre { font-weight: 600; margin-top: 0.5rem; }
        .nivel-item .entregas { font-size: 0.8rem; color: var(--gray-500); }

        /* Deuda Section */
        .deuda-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .deuda-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .deuda-monto {
            font-size: 2rem;
            font-weight: 700;
        }

        .deuda-monto.negativo { color: var(--danger); }
        .deuda-monto.positivo { color: var(--success); }

        .deuda-warning {
            background: #FEF2F2;
            border: 2px solid var(--danger);
            color: var(--danger);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .deuda-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        /* Historial */
        .historial-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .historial-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .historial-item:last-child { border-bottom: none; }

        .historial-emoji { font-size: 2rem; }

        /* Referidos Section */
        .referidos-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .referidos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .referidos-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .stat-card.highlight {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .stat-card.highlight .stat-value,
        .stat-card.highlight .stat-label {
            color: white;
        }

        .codigo-referido-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .codigo-referido-box h4 {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .codigo-display {
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 1rem 0;
        }

        .enlace-referido {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.8rem;
            word-break: break-all;
            margin-bottom: 1rem;
        }

        .btn-copiar {
            background: white;
            color: #667eea;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-copiar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-compartir {
            background: #25D366;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 0.5rem;
        }

        .bonificaciones-info {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .bonificacion-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .bonificacion-item:last-child {
            border-bottom: none;
        }

        .bonificacion-monto {
            font-weight: 700;
            color: var(--success);
        }

        .referido-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .referido-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }

        .referido-info {
            flex: 1;
        }

        .referido-nombre {
            font-weight: 600;
        }

        .referido-stats {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        .referido-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .referido-badge.activo {
            background: #DCFCE7;
            color: var(--success);
        }

        .referido-badge.pendiente {
            background: #FEF3C7;
            color: var(--warning);
        }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 0.75rem 0;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray-500);
            font-size: 0.75rem;
            padding: 0.5rem;
        }

        .nav-item.active { color: var(--primary); }
        .nav-item i { font-size: 1.25rem; margin-bottom: 0.25rem; }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .alert-success { background: #F0FDF4; color: var(--success); border: 1px solid var(--success); }
        .alert-danger { background: #FEF2F2; color: var(--danger); border: 1px solid var(--danger); }

        /* Modal */
        .modal-content { border-radius: 16px; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-trophy"></i> Mi Nivel</h1>
        <p>Completa entregas y gana recompensas</p>
    </div>

    <div class="container">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $mensaje['tipo'] === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas <?php echo $mensaje['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $mensaje['texto']; ?>
            </div>
        <?php endif; ?>

        <!-- Nivel Actual -->
        <div class="nivel-card">
            <div class="nivel-badge"><?php echo $nivel_actual['emoji'] ?? '🆕'; ?></div>
            <div class="nivel-nombre <?php echo strtolower($nivel_actual['nombre'] ?? 'nuevo'); ?>">
                Nivel <?php echo $nivel_actual['nombre'] ?? 'Nuevo'; ?>
            </div>
            <div class="entregas-count">
                <i class="fas fa-truck"></i> <?php echo $total_entregas; ?> entregas completadas
            </div>
            <div class="calificacion">
                <i class="fas fa-star"></i>
                <span><?php echo number_format($calificacion, 1); ?></span>
            </div>

            <?php if ($proximo_nivel): ?>
            <div class="progreso-section">
                <div class="progreso-header">
                    <span><?php echo $nivel_actual['emoji'] ?? '🆕'; ?> <?php echo $nivel_actual['nombre'] ?? 'Nuevo'; ?></span>
                    <span><?php echo $proximo_nivel['emoji']; ?> <?php echo $proximo_nivel['nombre']; ?></span>
                </div>
                <div class="progreso-bar">
                    <div class="progreso-fill" style="width: <?php echo $progreso; ?>%"></div>
                </div>
                <div class="entregas-faltantes">
                    <?php if ($entregas_faltantes > 0): ?>
                        <strong><?php echo $entregas_faltantes; ?></strong> entregas más para <?php echo $proximo_nivel['nombre']; ?>
                    <?php else: ?>
                        ¡Muy cerca de subir de nivel!
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recompensa disponible -->
            <?php if ($nivel_actual && $nivel_actual['orden'] >= 1 && !$repartidor['recompensa_reclamada']): ?>
            <div class="recompensa-card">
                <h3><i class="fas fa-gift"></i> ¡Recompensa Disponible!</h3>
                <p><?php echo $nivel_actual['recompensa']; ?></p>
                <button class="btn-reclamar" data-bs-toggle="modal" data-bs-target="#modalRecompensa">
                    <i class="fas fa-box"></i> Reclamar mi recompensa
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Todos los niveles -->
        <h3 class="mt-4 mb-3"><i class="fas fa-layer-group"></i> Niveles</h3>
        <div class="niveles-grid">
            <?php foreach ($niveles as $n): ?>
                <?php
                    $esAlcanzado = $total_entregas >= $n['entregas_requeridas'];
                    $esActual = $n['id_nivel'] == ($repartidor['id_nivel'] ?? 1);
                ?>
                <div class="nivel-item <?php echo $esAlcanzado ? 'alcanzado' : ''; ?> <?php echo $esActual ? 'actual' : ''; ?>">
                    <div class="emoji"><?php echo $n['emoji']; ?></div>
                    <div class="nombre"><?php echo $n['nombre']; ?></div>
                    <div class="entregas"><?php echo $n['entregas_requeridas']; ?> entregas</div>
                    <div style="font-size: 0.7rem; color: var(--gray-500); margin-top: 0.25rem;">
                        <?php echo $n['recompensa']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Estado de Deuda -->
        <div class="deuda-section">
            <div class="deuda-header">
                <h3><i class="fas fa-wallet"></i> Estado de Cuenta</h3>
                <div class="deuda-monto <?php echo $saldo_deuda < 0 ? 'negativo' : 'positivo'; ?>">
                    $<?php echo number_format(abs($saldo_deuda), 2); ?>
                    <?php echo $saldo_deuda < 0 ? '<small style="font-size: 0.8rem;">deuda</small>' : ''; ?>
                </div>
            </div>

            <?php if ($bloqueado): ?>
            <div class="deuda-warning">
                <i class="fas fa-ban"></i>
                <strong>Cuenta Bloqueada</strong>
                <p class="mb-0">Tu cuenta está bloqueada por deuda superior a $200. Liquida tu deuda para continuar recibiendo pedidos.</p>
            </div>
            <?php elseif ($saldo_deuda < 0): ?>
            <div class="alert" style="background: #FFFBEB; color: #92400E; border: 1px solid #F59E0B;">
                <i class="fas fa-exclamation-triangle"></i>
                Tienes una deuda de comisiones. Si llega a $200, tu cuenta será bloqueada temporalmente.
            </div>
            <?php endif; ?>

            <?php if (!empty($deudas)): ?>
            <h5 class="mt-3">Comisiones Pendientes</h5>
            <?php foreach ($deudas as $d): ?>
            <div class="deuda-item">
                <div>
                    <strong>Pedido #<?php echo $d['id_pedido']; ?></strong>
                    <div style="font-size: 0.8rem; color: var(--gray-500);">
                        <?php echo date('d/m/Y', strtotime($d['fecha_creacion'])); ?>
                    </div>
                </div>
                <div style="color: var(--danger); font-weight: 600;">
                    -$<?php echo number_format($d['monto_comision'], 2); ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-center text-muted mt-3">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                No tienes deudas pendientes
            </p>
            <?php endif; ?>
        </div>

        <!-- Historial de Niveles -->
        <?php if (!empty($historial)): ?>
        <div class="historial-section">
            <h3><i class="fas fa-history"></i> Historial de Niveles</h3>
            <?php foreach ($historial as $h): ?>
            <div class="historial-item">
                <div class="historial-emoji"><?php echo $h['nivel_nuevo_emoji']; ?></div>
                <div style="flex: 1;">
                    <strong>Subiste a <?php echo $h['nivel_nuevo_nombre']; ?></strong>
                    <div style="font-size: 0.8rem; color: var(--gray-500);">
                        <?php echo date('d/m/Y', strtotime($h['fecha_cambio'])); ?> - <?php echo $h['entregas_al_subir']; ?> entregas
                    </div>
                    <?php if ($h['recompensa']): ?>
                    <div style="font-size: 0.8rem; color: var(--primary);">
                        <i class="fas fa-gift"></i> <?php echo $h['recompensa']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Programa de Referidos -->
        <div class="referidos-section">
            <div class="referidos-header">
                <h3><i class="fas fa-users"></i> Programa de Referidos</h3>
            </div>

            <!-- Estadisticas de Referidos -->
            <div class="referidos-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $datosReferidos['total_referidos'] ?? 0; ?></div>
                    <div class="stat-label">Total Referidos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $datosReferidos['referidos_activos'] ?? 0; ?></div>
                    <div class="stat-label">Activos (+10 entregas)</div>
                </div>
                <div class="stat-card highlight">
                    <div class="stat-value">$<?php echo number_format($datosReferidos['total_bonificaciones'] ?? 0, 0); ?></div>
                    <div class="stat-label">Bonificaciones Ganadas</div>
                </div>
            </div>

            <!-- Codigo de Referido -->
            <div class="codigo-referido-box">
                <h4><i class="fas fa-share-alt"></i> Tu Codigo de Referido</h4>
                <div class="codigo-display" id="codigoReferido"><?php echo htmlspecialchars($datosReferidos['codigo_referido'] ?? 'N/A'); ?></div>
                <div class="enlace-referido" id="enlaceReferido"><?php echo htmlspecialchars($datosReferidos['enlace_referido'] ?? ''); ?></div>
                <button class="btn-copiar" onclick="copiarEnlace()">
                    <i class="fas fa-copy"></i> Copiar Enlace
                </button>
                <button class="btn-compartir" onclick="compartirWhatsApp()">
                    <i class="fab fa-whatsapp"></i> Compartir
                </button>
            </div>

            <!-- Bonificaciones Disponibles -->
            <div class="bonificaciones-info">
                <h5><i class="fas fa-gift"></i> Bonificaciones que Puedes Ganar</h5>
                <?php if (!empty($datosReferidos['config_bonificaciones'])): ?>
                    <?php foreach ($datosReferidos['config_bonificaciones'] as $config): ?>
                    <div class="bonificacion-item">
                        <div>
                            <strong><?php echo htmlspecialchars($config['descripcion']); ?></strong>
                        </div>
                        <div class="bonificacion-monto">+$<?php echo number_format($config['monto'], 0); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bonificacion-item">
                        <div><strong>Por cada repartidor referido que complete 10 entregas</strong></div>
                        <div class="bonificacion-monto">+$50</div>
                    </div>
                    <div class="bonificacion-item">
                        <div><strong>Cuando tu referido complete 50 entregas</strong></div>
                        <div class="bonificacion-monto">+$25</div>
                    </div>
                    <div class="bonificacion-item">
                        <div><strong>Cuando tu referido alcance nivel Oro</strong></div>
                        <div class="bonificacion-monto">+$100</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lista de Referidos -->
            <?php if (!empty($datosReferidos['historial_referidos'])): ?>
            <div class="mt-4">
                <h5><i class="fas fa-list"></i> Tus Referidos</h5>
                <?php foreach ($datosReferidos['historial_referidos'] as $ref): ?>
                <div class="referido-card">
                    <div class="referido-avatar">
                        <?php echo strtoupper(substr($ref['nombre'] ?? 'R', 0, 1)); ?>
                    </div>
                    <div class="referido-info">
                        <div class="referido-nombre">
                            <?php echo htmlspecialchars(($ref['nombre'] ?? '') . ' ' . ($ref['apellido'] ?? '')); ?>
                            <?php if (!empty($ref['nivel_emoji'])): ?>
                                <span><?php echo $ref['nivel_emoji']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="referido-stats">
                            <i class="fas fa-truck"></i> <?php echo $ref['total_entregas'] ?? 0; ?> entregas
                            &bull;
                            <i class="fas fa-star"></i> <?php echo number_format($ref['calificacion_promedio'] ?? 5, 1); ?>
                        </div>
                    </div>
                    <?php if (($ref['total_entregas'] ?? 0) >= 10): ?>
                        <span class="referido-badge activo">
                            <i class="fas fa-check"></i> Activo
                        </span>
                    <?php else: ?>
                        <span class="referido-badge pendiente">
                            <?php echo 10 - ($ref['total_entregas'] ?? 0); ?> entregas restantes
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted mt-4 p-4" style="background: var(--gray-100); border-radius: 12px;">
                <i class="fas fa-user-plus" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 1rem;"></i>
                <p>Aun no tienes referidos</p>
                <p class="small">Comparte tu codigo y gana bonificaciones cuando tus referidos completen entregas</p>
            </div>
            <?php endif; ?>

            <!-- Historial de Bonificaciones -->
            <?php if (!empty($datosReferidos['historial_bonificaciones'])): ?>
            <div class="mt-4">
                <h5><i class="fas fa-history"></i> Historial de Bonificaciones</h5>
                <?php foreach (array_slice($datosReferidos['historial_bonificaciones'], 0, 5) as $bono): ?>
                <div class="deuda-item">
                    <div>
                        <strong><?php echo htmlspecialchars($bono['descripcion'] ?? 'Bonificacion'); ?></strong>
                        <div style="font-size: 0.8rem; color: var(--gray-500);">
                            <?php echo date('d/m/Y', strtotime($bono['fecha_solicitud'])); ?>
                            <?php if (!empty($bono['referido_nombre'])): ?>
                                - <?php echo htmlspecialchars($bono['referido_nombre']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="color: var(--success); font-weight: 600;">
                        +$<?php echo number_format($bono['monto_bonificacion'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Reclamar Recompensa -->
    <div class="modal fade" id="modalRecompensa" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%); color: var(--dark);">
                    <h5 class="modal-title"><i class="fas fa-gift"></i> Reclamar Recompensa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div style="font-size: 4rem;"><?php echo $nivel_actual['emoji'] ?? '🎁'; ?></div>
                            <h4><?php echo $nivel_actual['recompensa'] ?? 'Recompensa'; ?></h4>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dirección de envío completa:</label>
                            <textarea name="direccion_envio" class="form-control" rows="3" required
                                      placeholder="Calle, número, colonia, ciudad, CP..."></textarea>
                        </div>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle"></i>
                            Tu recompensa será enviada en los próximos 5-7 días hábiles.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="reclamar_recompensa" class="btn" style="background: var(--gold); color: var(--dark);">
                            <i class="fas fa-paper-plane"></i> Solicitar Envío
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../repartidor_dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Inicio</span>
        </a>
        <a href="wallet.php" class="nav-item">
            <i class="fas fa-wallet"></i>
            <span>Cartera</span>
        </a>
        <a href="gamificacion.php" class="nav-item active">
            <i class="fas fa-trophy"></i>
            <span>Nivel</span>
        </a>
        <a href="../repartidor_dashboard.php#perfil" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funciones para el programa de referidos
        function copiarEnlace() {
            const enlace = document.getElementById('enlaceReferido').textContent;
            navigator.clipboard.writeText(enlace).then(() => {
                mostrarNotificacion('Enlace copiado al portapapeles', 'success');
            }).catch(err => {
                // Fallback para navegadores sin soporte de clipboard API
                const textArea = document.createElement('textarea');
                textArea.value = enlace;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                mostrarNotificacion('Enlace copiado al portapapeles', 'success');
            });
        }

        function compartirWhatsApp() {
            const enlace = document.getElementById('enlaceReferido').textContent;
            const mensaje = encodeURIComponent('Unete a QuickBite como repartidor y gana dinero haciendo entregas. Usa mi codigo de referido: ' + enlace);
            window.open('https://wa.me/?text=' + mensaje, '_blank');
        }

        function mostrarNotificacion(mensaje, tipo) {
            // Crear notificacion toast
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-5 p-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-body" style="background: ${tipo === 'success' ? '#22C55E' : '#EF4444'}; color: white; border-radius: 12px; padding: 1rem;">
                        <i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                        ${mensaje}
                    </div>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>
