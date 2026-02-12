<?php
/**
 * QuickBite - Perfil y Beneficios del Usuario
 * Muestra ahorros, historial de descuentos y acceso a beneficios del Club
 */

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['id_usuario'];

// Obtener datos del usuario con información de membresía
$stmt = $db->prepare("
    SELECT nombre, email, puntos_acumulados, es_miembro, es_miembro_club,
           fecha_fin_membresia, ahorro_total_membresia, fecha_registro
    FROM usuarios WHERE id_usuario = ?
");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar si es miembro activo
$esMiembroActivo = ($userData['es_miembro'] == 1 || $userData['es_miembro_club'] == 1) &&
                   ($userData['fecha_fin_membresia'] === null || $userData['fecha_fin_membresia'] >= date('Y-m-d'));

// Obtener historial de uso de códigos de aliados
$stmt = $db->prepare("
    SELECT ub.*, na.nombre as aliado_nombre, na.descuento_porcentaje
    FROM uso_beneficios_aliados ub
    JOIN negocios_aliados na ON ub.id_aliado = na.id_aliado
    WHERE ub.id_usuario = ?
    ORDER BY ub.fecha_uso DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$historialAliados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener códigos activos
$stmt = $db->prepare("
    SELECT cd.*, na.nombre as aliado_nombre, na.descuento_porcentaje
    FROM codigos_descuento_aliados cd
    JOIN negocios_aliados na ON cd.id_aliado = na.id_aliado
    WHERE cd.id_usuario = ? AND cd.usado = 0 AND cd.fecha_expiracion >= CURDATE()
    ORDER BY cd.fecha_expiracion ASC
");
$stmt->execute([$userId]);
$codigosActivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas de ahorro
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_usos,
        COALESCE(SUM(descuento_aplicado), 0) as total_descuentos
    FROM uso_beneficios_aliados
    WHERE id_usuario = ? AND estado = 'verificado'
");
$stmt->execute([$userId]);
$statsAliados = $stmt->fetch(PDO::FETCH_ASSOC);

// Ahorro por cargo de servicio (si es miembro, $5 por pedido)
$stmt = $db->prepare("
    SELECT COUNT(*) as pedidos_miembro
    FROM pedidos
    WHERE id_usuario = ? AND estado IN ('entregado', 'completado')
    AND fecha_pedido >= (SELECT COALESCE(fecha_inicio_membresia, fecha_registro) FROM usuarios WHERE id_usuario = ?)
");
$stmt->execute([$userId, $userId]);
$pedidosMiembro = $stmt->fetch(PDO::FETCH_ASSOC);
$ahorroCargoServicio = ($esMiembroActivo) ? ($pedidosMiembro['pedidos_miembro'] ?? 0) * 5 : 0;

// Total ahorrado
$ahorroTotal = floatval($userData['ahorro_total_membresia'] ?? 0) + floatval($statsAliados['total_descuentos']);

// Contar aliados disponibles
$stmt = $db->query("SELECT COUNT(*) FROM negocios_aliados WHERE estado = 'activo'");
$totalAliados = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil y Beneficios - QuickBite</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --qb-primary: #2563EB;
            --qb-secondary: #1e3a5f;
            --qb-gold: #F59E0B;
            --qb-success: #10B981;
        }
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        .navbar-brand img { height: 40px; }
        .hero-section {
            background: linear-gradient(135deg, var(--qb-secondary) 0%, var(--qb-primary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        .stat-icon.ahorro { background: linear-gradient(135deg, #10B981, #059669); color: white; }
        .stat-icon.puntos { background: linear-gradient(135deg, var(--qb-gold), #D97706); color: white; }
        .stat-icon.aliados { background: linear-gradient(135deg, var(--qb-primary), var(--qb-secondary)); color: white; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--qb-secondary); }
        .stat-label { color: #64748b; font-size: 0.9rem; }

        .benefit-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .benefit-card h5 {
            color: var(--qb-secondary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .membership-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
        }
        .membership-badge.active {
            background: linear-gradient(135deg, var(--qb-gold), #D97706);
            color: white;
        }
        .membership-badge.inactive {
            background: #e2e8f0;
            color: #64748b;
        }
        .codigo-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px dashed var(--qb-primary);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .codigo-valor {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--qb-primary);
            letter-spacing: 2px;
        }
        .historial-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .historial-item:last-child { border-bottom: none; }
        .descuento-badge {
            background: var(--qb-success);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .cta-aliados {
            background: linear-gradient(135deg, var(--qb-primary) 0%, var(--qb-secondary) 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
        }
        .cta-aliados h4 { margin-bottom: 1rem; }
        .btn-gold {
            background: linear-gradient(135deg, var(--qb-gold), #D97706);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-gold:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4);
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.png" alt="QuickBite" onerror="this.outerHTML='<span class=\'fw-bold\' style=\'color:#2563EB\'>Quick</span><span class=\'fw-bold\' style=\'color:#F59E0B\'>Bite</span>'">
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="aliados.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-gift"></i> Aliados
                </a>
                <a href="index.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-house"></i> Inicio
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1">Hola, <?php echo htmlspecialchars($userData['nombre']); ?></h2>
                    <p class="mb-2 opacity-75">Miembro desde <?php echo date('d/m/Y', strtotime($userData['fecha_registro'])); ?></p>
                    <?php if ($esMiembroActivo): ?>
                        <span class="membership-badge active">
                            <i class="bi bi-star-fill"></i> QuickBite Club Activo
                            <?php if ($userData['fecha_fin_membresia']): ?>
                                <small>(hasta <?php echo date('d/m/Y', strtotime($userData['fecha_fin_membresia'])); ?>)</small>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="membership-badge inactive">
                            <i class="bi bi-star"></i> Sin membresía activa
                        </span>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (!$esMiembroActivo): ?>
                        <a href="membership_subscribe.php" class="btn btn-gold">
                            <i class="bi bi-crown"></i> Únete al Club - $49/mes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon ahorro">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($ahorroTotal, 2); ?></div>
                    <div class="stat-label">Total Ahorrado</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon puntos">
                        <i class="bi bi-coin"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($userData['puntos_acumulados'] ?? 0); ?></div>
                    <div class="stat-label">Puntos Acumulados</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon aliados">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="stat-value"><?php echo $totalAliados; ?></div>
                    <div class="stat-label">Aliados Disponibles</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Códigos Activos -->
            <div class="col-lg-6">
                <div class="benefit-card">
                    <h5><i class="bi bi-ticket-perforated text-primary"></i> Mis Códigos Activos</h5>
                    <?php if (empty($codigosActivos)): ?>
                        <div class="empty-state">
                            <i class="bi bi-ticket"></i>
                            <p>No tienes códigos activos</p>
                            <?php if ($esMiembroActivo): ?>
                                <a href="aliados.php" class="btn btn-outline-primary btn-sm">Generar código</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($codigosActivos as $codigo): ?>
                            <div class="codigo-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted"><?php echo htmlspecialchars($codigo['aliado_nombre']); ?></small>
                                        <div class="codigo-valor"><?php echo htmlspecialchars($codigo['codigo']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="descuento-badge"><?php echo $codigo['descuento_porcentaje']; ?>% OFF</span>
                                        <div class="small text-muted mt-1">
                                            Expira: <?php echo date('d/m/Y', strtotime($codigo['fecha_expiracion'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Desglose de Ahorros -->
                <?php if ($esMiembroActivo): ?>
                <div class="benefit-card">
                    <h5><i class="bi bi-graph-up-arrow text-success"></i> Desglose de Ahorros</h5>
                    <div class="historial-item">
                        <span><i class="bi bi-truck text-primary me-2"></i> Cargos de servicio ($5 x pedido)</span>
                        <strong class="text-success">$<?php echo number_format($ahorroCargoServicio, 2); ?></strong>
                    </div>
                    <div class="historial-item">
                        <span><i class="bi bi-shop text-primary me-2"></i> Descuentos en aliados</span>
                        <strong class="text-success">$<?php echo number_format($statsAliados['total_descuentos'], 2); ?></strong>
                    </div>
                    <div class="historial-item border-top pt-2 mt-2">
                        <strong>Total Ahorrado</strong>
                        <strong class="text-success fs-5">$<?php echo number_format($ahorroTotal, 2); ?></strong>
                    </div>
                    <?php
                    $costoMembresia = 49; // $49/mes
                    $mesesMiembro = 1; // Simplificado
                    $roi = $ahorroTotal - ($costoMembresia * $mesesMiembro);
                    ?>
                    <?php if ($roi > 0): ?>
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>¡Excelente!</strong> Tu membresía ya se pagó sola. Ganancia neta: <strong>$<?php echo number_format($roi, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Historial de Uso -->
            <div class="col-lg-6">
                <div class="benefit-card">
                    <h5><i class="bi bi-clock-history text-primary"></i> Historial de Descuentos</h5>
                    <?php if (empty($historialAliados)): ?>
                        <div class="empty-state">
                            <i class="bi bi-receipt"></i>
                            <p>Aún no has usado descuentos en aliados</p>
                            <?php if ($esMiembroActivo): ?>
                                <a href="aliados.php" class="btn btn-outline-primary btn-sm">Ver aliados</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($historialAliados as $uso): ?>
                            <div class="historial-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($uso['aliado_nombre']); ?></strong>
                                    <div class="small text-muted">
                                        <?php echo date('d/m/Y', strtotime($uso['fecha_uso'])); ?>
                                        - Código: <?php echo htmlspecialchars($uso['codigo_usado']); ?>
                                    </div>
                                </div>
                                <span class="descuento-badge">-$<?php echo number_format($uso['descuento_aplicado'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- CTA Aliados -->
                <?php if ($esMiembroActivo): ?>
                <div class="cta-aliados">
                    <h4><i class="bi bi-gift me-2"></i>¡Aprovecha tus beneficios!</h4>
                    <p class="mb-3">Tienes acceso a <?php echo $totalAliados; ?> negocios aliados con descuentos exclusivos</p>
                    <a href="aliados.php" class="btn btn-gold">
                        <i class="bi bi-arrow-right-circle me-2"></i>Ver Aliados
                    </a>
                </div>
                <?php else: ?>
                <div class="cta-aliados">
                    <h4><i class="bi bi-crown me-2"></i>Únete al QuickBite Club</h4>
                    <p class="mb-2">Por solo <strong>$49/mes</strong> obtén:</p>
                    <ul class="list-unstyled text-start mb-3" style="max-width: 300px; margin: 0 auto;">
                        <li><i class="bi bi-check-circle-fill text-warning me-2"></i> $0 cargo de servicio</li>
                        <li><i class="bi bi-check-circle-fill text-warning me-2"></i> Descuentos en <?php echo $totalAliados; ?>+ aliados</li>
                        <li><i class="bi bi-check-circle-fill text-warning me-2"></i> Promociones exclusivas</li>
                        <li><i class="bi bi-check-circle-fill text-warning me-2"></i> Acceso a ranking mensual</li>
                    </ul>
                    <a href="membership_subscribe.php" class="btn btn-gold">
                        <i class="bi bi-star-fill me-2"></i>Suscribirme Ahora
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
