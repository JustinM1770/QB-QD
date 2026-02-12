<?php
/**
 * QuickBite - Panel de Membresía Premium para Negocios
 * Permite a los negocios ver y contratar el plan Premium
 */

session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar autenticación de negocio
if (!isset($_SESSION['id_negocio'])) {
    header('Location: ../login_negocio.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$idNegocio = $_SESSION['id_negocio'];

// Obtener información del negocio
$stmt = $db->prepare("
    SELECT n.*, pm.nombre as plan_nombre, pm.precio_mensual, pm.caracteristicas
    FROM negocios n
    LEFT JOIN planes_membresia_negocio pm ON n.id_plan_membresia = pm.id_plan
    WHERE n.id_negocio = ?
");
$stmt->execute([$idNegocio]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener planes disponibles
$stmt = $db->query("SELECT * FROM planes_membresia_negocio WHERE activo = 1 ORDER BY orden");
$planes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial de membresías
$stmt = $db->prepare("
    SELECT * FROM membresias_negocios
    WHERE id_negocio = ?
    ORDER BY fecha_creacion DESC
    LIMIT 10
");
$stmt->execute([$idNegocio]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas del mes
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_pedidos,
        COALESCE(SUM(total_productos), 0) as ventas_totales,
        COALESCE(SUM(comision_plataforma), 0) as comisiones_pagadas
    FROM pedidos
    WHERE id_negocio = ?
    AND id_estado = 6
    AND MONTH(fecha_creacion) = MONTH(CURDATE())
    AND YEAR(fecha_creacion) = YEAR(CURDATE())
");
$stmt->execute([$idNegocio]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular ahorro potencial con Premium
$ventasMensuales = $estadisticas['ventas_totales'];
$comisionActual = $negocio['comision_porcentaje'] ?? 10;
$ahorroMensual = $ventasMensuales * (($comisionActual - 8) / 100);
$convienePremium = $ahorroMensual > 199;

// Procesar solicitud de upgrade
$mensaje = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upgrade_premium') {
        // Aquí se integraría con MercadoPago para el pago
        // Por ahora, simulamos la activación
        try {
            $db->beginTransaction();

            // Crear membresía
            $stmt = $db->prepare("
                INSERT INTO membresias_negocios
                (id_negocio, plan, precio_pagado, comision_porcentaje, fecha_inicio, fecha_fin, estado, metodo_pago)
                VALUES (?, 'premium', 199.00, 8.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'activa', 'pendiente')
            ");
            $stmt->execute([$idNegocio]);
            $idMembresia = $db->lastInsertId();

            // Actualizar negocio
            $stmt = $db->prepare("
                UPDATE negocios SET
                    es_premium = 1,
                    comision_porcentaje = 8.00,
                    id_plan_membresia = 2,
                    fecha_inicio_premium = CURDATE(),
                    fecha_fin_premium = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                WHERE id_negocio = ?
            ");
            $stmt->execute([$idNegocio]);

            $db->commit();

            $mensaje = ['tipo' => 'exito', 'texto' => 'Membresía Premium activada. Se redirigirá al pago.'];

            // Actualizar datos del negocio
            $negocio['es_premium'] = 1;
            $negocio['comision_porcentaje'] = 8;

        } catch (Exception $e) {
            $db->rollBack();
            $mensaje = ['tipo' => 'error', 'texto' => 'Error al procesar: ' . $e->getMessage()];
        }
    }
}

$caracteristicasBasico = [
    'Aparecer en la app' => true,
    'Recibir pedidos' => true,
    'Panel de administración' => true,
    'Reportes básicos' => true,
    'Bot de WhatsApp' => false,
    'IA para menú' => false,
    'Badge Premium' => false,
    'Prioridad en búsquedas' => false,
    'Reportes avanzados' => false,
    'Soporte prioritario' => false
];

$caracteristicasPremium = [
    'Aparecer en la app' => true,
    'Recibir pedidos' => true,
    'Panel de administración' => true,
    'Reportes básicos' => true,
    'Bot de WhatsApp' => true,
    'IA para menú' => true,
    'Badge Premium' => true,
    'Prioridad en búsquedas' => true,
    'Reportes avanzados' => true,
    'Soporte prioritario' => true
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresía Premium - QuickBite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563EB;
            --gold: #FFD700;
            --success: #10B981;
        }

        body {
            background: #f8fafc;
        }

        .navbar-custom {
            background: linear-gradient(135deg, #1e3a5f 0%, var(--primary) 100%);
        }

        .plan-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .plan-card.premium {
            border: 3px solid var(--gold);
        }

        .plan-header {
            padding: 30px;
            text-align: center;
        }

        .plan-header.basico {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }

        .plan-header.premium {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: #1a1a1a;
        }

        .plan-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 48px;
            font-weight: 800;
        }

        .plan-price small {
            font-size: 16px;
            font-weight: 400;
        }

        .plan-comision {
            font-size: 20px;
            margin-top: 10px;
        }

        .plan-body {
            padding: 30px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list .fa-check {
            color: var(--success);
        }

        .feature-list .fa-times {
            color: #cbd5e1;
        }

        .btn-upgrade {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: #1a1a1a;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 18px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-upgrade:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 20px rgba(255, 215, 0, 0.5);
            color: #1a1a1a;
        }

        .btn-current {
            background: #e2e8f0;
            color: #64748b;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            cursor: default;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .stats-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }

        .ahorro-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid var(--success);
            border-radius: 16px;
            padding: 25px;
        }

        .ahorro-card.no-conviene {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-color: #f59e0b;
        }

        .premium-badge {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: #1a1a1a;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .current-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .historial-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand" href="negocio_configuracion.php">
                <i class="fas fa-arrow-left me-2"></i> Volver al Panel
            </a>
            <span class="text-white">
                <?php echo htmlspecialchars($negocio['nombre']); ?>
                <?php if ($negocio['es_premium']): ?>
                    <span class="premium-badge ms-2"><i class="fas fa-crown me-1"></i>Premium</span>
                <?php endif; ?>
            </span>
        </div>
    </nav>

    <div class="container pb-5">
        <?php if ($mensaje): ?>
            <div class="alert <?php echo $mensaje['tipo'] === 'exito' ? 'alert-success' : 'alert-danger'; ?> mb-4">
                <i class="fas <?php echo $mensaje['tipo'] === 'exito' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensaje['texto']); ?>
            </div>
        <?php endif; ?>

        <h2 class="mb-4"><i class="fas fa-crown text-warning me-2"></i>Membresía Premium</h2>

        <!-- Estadísticas del mes -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($estadisticas['total_pedidos']); ?></div>
                    <div class="text-muted">Pedidos este mes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?php echo number_format($estadisticas['ventas_totales'], 0); ?></div>
                    <div class="text-muted">Ventas este mes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $negocio['comision_porcentaje']; ?>%</div>
                    <div class="text-muted">Tu comisión actual</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?php echo number_format($estadisticas['comisiones_pagadas'], 0); ?></div>
                    <div class="text-muted">Comisiones pagadas</div>
                </div>
            </div>
        </div>

        <!-- Calculadora de ahorro -->
        <?php if (!$negocio['es_premium']): ?>
            <div class="ahorro-card <?php echo !$convienePremium ? 'no-conviene' : ''; ?> mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <?php if ($convienePremium): ?>
                            <h4><i class="fas fa-piggy-bank text-success me-2"></i>¡Te conviene ser Premium!</h4>
                            <p class="mb-0">
                                Con tus ventas de <strong>$<?php echo number_format($ventasMensuales, 0); ?></strong> este mes,
                                ahorrarías <strong>$<?php echo number_format($ahorroMensual - 199, 0); ?></strong> mensuales
                                con el plan Premium.
                            </p>
                        <?php else: ?>
                            <h4><i class="fas fa-info-circle text-warning me-2"></i>Premium aún no te conviene</h4>
                            <p class="mb-0">
                                Con ventas de $<?php echo number_format($ventasMensuales, 0); ?>/mes, el ahorro sería de $<?php echo number_format($ahorroMensual, 0); ?>.
                                Necesitas vender más de <strong>$10,000/mes</strong> para que Premium te beneficie.
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="fs-3 fw-bold <?php echo $convienePremium ? 'text-success' : 'text-warning'; ?>">
                            <?php echo $convienePremium ? '+' : ''; ?>$<?php echo number_format($ahorroMensual - 199, 0); ?>/mes
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Planes -->
        <div class="row g-4 mb-5">
            <!-- Plan Básico -->
            <div class="col-md-6">
                <div class="plan-card position-relative">
                    <?php if (!$negocio['es_premium']): ?>
                        <div class="current-badge">Tu plan actual</div>
                    <?php endif; ?>
                    <div class="plan-header basico">
                        <div class="plan-name">Plan Básico</div>
                        <div class="plan-price">$0 <small>/mes</small></div>
                        <div class="plan-comision">Comisión: 10%</div>
                    </div>
                    <div class="plan-body">
                        <ul class="feature-list">
                            <?php foreach ($caracteristicasBasico as $feature => $activo): ?>
                                <li>
                                    <i class="fas <?php echo $activo ? 'fa-check' : 'fa-times'; ?>"></i>
                                    <?php echo $feature; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!$negocio['es_premium']): ?>
                            <button class="btn-current mt-3" disabled>
                                <i class="fas fa-check me-2"></i>Plan actual
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Plan Premium -->
            <div class="col-md-6">
                <div class="plan-card premium position-relative">
                    <?php if ($negocio['es_premium']): ?>
                        <div class="current-badge">Tu plan actual</div>
                    <?php endif; ?>
                    <div class="plan-header premium">
                        <div class="plan-name"><i class="fas fa-crown me-2"></i>Plan Premium</div>
                        <div class="plan-price">$199 <small>/mes</small></div>
                        <div class="plan-comision">Comisión: 8%</div>
                    </div>
                    <div class="plan-body">
                        <ul class="feature-list">
                            <?php foreach ($caracteristicasPremium as $feature => $activo): ?>
                                <li>
                                    <i class="fas <?php echo $activo ? 'fa-check' : 'fa-times'; ?>"></i>
                                    <strong><?php echo $feature; ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!$negocio['es_premium']): ?>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="upgrade_premium">
                                <button type="submit" class="btn-upgrade">
                                    <i class="fas fa-crown me-2"></i>Activar Premium
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center mt-3">
                                <span class="text-success fw-bold">
                                    <i class="fas fa-check-circle me-1"></i>Premium activo
                                </span>
                                <br>
                                <small class="text-muted">
                                    Vence: <?php echo date('d/m/Y', strtotime($negocio['fecha_fin_premium'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de membresías -->
        <?php if (!empty($historial)): ?>
            <h4 class="mb-3"><i class="fas fa-history me-2"></i>Historial de membresías</h4>
            <div class="card">
                <div class="card-body">
                    <?php foreach ($historial as $h): ?>
                        <div class="historial-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo ucfirst($h['plan']); ?></strong>
                                    <span class="badge bg-<?php echo $h['estado'] === 'activa' ? 'success' : 'secondary'; ?> ms-2">
                                        <?php echo ucfirst($h['estado']); ?>
                                    </span>
                                </div>
                                <div class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($h['fecha_inicio'])); ?> -
                                    <?php echo date('d/m/Y', strtotime($h['fecha_fin'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
