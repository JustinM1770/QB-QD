<?php
/**
 * Dashboard - Panel principal del negocio
 * Muestra resumen de estadísticas y accesos rápidos
 */

// Configurar reporte de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Pedido.php';

$database = new Database();
$db = $database->getConnection();

// Verificar autenticación
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/dashboard.php");
    exit;
}

$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

$negocio = new Negocio($db);
$negocios = $negocio->obtenerPorIdPropietario($usuario->id_usuario);

if (empty($negocios)) {
    header("Location: negocio_configuracion.php?mensaje=Debes registrar tu negocio primero");
    exit;
}

$negocio_info = $negocios[0];
$id_negocio = $negocio_info['id_negocio'];

// Obtener estadísticas rápidas
$hoy = date('Y-m-d');
$inicio_mes = date('Y-m-01');

// Pedidos de hoy
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(monto_total), 0) as ventas FROM pedidos WHERE id_negocio = ? AND DATE(fecha_creacion) = ? AND id_estado NOT IN (5, 7)");
    $stmt->execute([$id_negocio, $hoy]);
    $hoy_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $hoy_stats = ['total' => 0, 'ventas' => 0];
}

// Pedidos pendientes
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE id_negocio = ? AND id_estado IN (1, 2, 3)");
    $stmt->execute([$id_negocio]);
    $pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $pendientes = 0;
}

// Ventas del mes
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto_total), 0) as ventas FROM pedidos WHERE id_negocio = ? AND DATE(fecha_creacion) >= ? AND id_estado NOT IN (5, 7)");
    $stmt->execute([$id_negocio, $inicio_mes]);
    $ventas_mes = $stmt->fetch(PDO::FETCH_ASSOC)['ventas'];
} catch (Exception $e) {
    $ventas_mes = 0;
}

// Productos activos
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE id_negocio = ? AND disponible = 1");
    $stmt->execute([$id_negocio]);
    $productos_activos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (Exception $e) {
    $productos_activos = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($negocio_info['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #2E294E;
            --sidebar-width: 260px;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: var(--sidebar-width); background: var(--secondary-color);
            padding: 20px 0; z-index: 1000; overflow-y: auto;
        }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand { color: var(--primary-color); font-size: 1.5rem; font-weight: 700; text-decoration: none; }
        .sidebar-brand:hover { color: var(--primary-color); }
        .sidebar-menu { padding: 20px 0; }
        .menu-section { color: rgba(255,255,255,0.5); font-size: 0.75rem; padding: 10px 20px; text-transform: uppercase; letter-spacing: 1px; }
        .menu-item { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .menu-item i { width: 20px; margin-right: 10px; }
        .menu-item:hover, .menu-item.active { background: rgba(255,107,53,0.2); color: var(--primary-color); }
        .main-content { margin-left: var(--sidebar-width); padding: 30px; }
        .stat-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08); height: 100%;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .icon {
            width: 60px; height: 60px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: var(--secondary-color); }
        .stat-card .label { color: #6c757d; font-size: 0.9rem; }
        .quick-action {
            background: white; border-radius: 12px; padding: 20px;
            text-align: center; text-decoration: none; color: var(--secondary-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;
            display: block;
        }
        .quick-action:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); color: var(--primary-color); }
        .quick-action i { font-size: 2rem; margin-bottom: 10px; color: var(--primary-color); }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .user-details { flex: 1; }
        .user-name { color: white; font-size: 0.9rem; margin: 0; }
        .user-role { color: rgba(255,255,255,0.5); font-size: 0.75rem; margin: 0; }
        .logout-btn { color: rgba(255,255,255,0.5); font-size: 1.2rem; }
        .logout-btn:hover { color: #dc3545; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-brand"><i class="fas fa-utensils"></i> QuickBite</a>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="pedidos.php" class="menu-item"><i class="fas fa-shopping-bag"></i> Pedidos</a>
            <div class="menu-section">MENU Y OFERTAS</div>
            <a href="menu.php" class="menu-item"><i class="fas fa-clipboard-list"></i> Menu</a>
            <a href="categorias.php" class="menu-item"><i class="fas fa-tags"></i> Categorias</a>
            <a href="promociones.php" class="menu-item"><i class="fas fa-percent"></i> Promociones</a>
            <div class="menu-section">NEGOCIO</div>
            <a href="negocio_configuracion.php" class="menu-item"><i class="fas fa-store"></i> Mi Negocio</a>
            <a href="wallet_negocio.php" class="menu-item"><i class="fas fa-wallet"></i> Monedero</a>
            <a href="reportes.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
            <div class="menu-section">CONFIGURACION</div>
            <a href="configuracion.php" class="menu-item"><i class="fas fa-cog"></i> Configuracion</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($usuario->nombre, 0, 1); ?></div>
                <div class="user-details">
                    <p class="user-name"><?php echo htmlspecialchars($usuario->nombre); ?></p>
                    <p class="user-role">Propietario</p>
                </div>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Bienvenido, <?php echo htmlspecialchars($usuario->nombre); ?></h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($negocio_info['nombre']); ?> - <?php echo date('d M Y'); ?></p>
            </div>
        </div>

        <!-- Estadísticas principales -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="value">$<?php echo number_format($hoy_stats['ventas'], 0); ?></div>
                            <div class="label">Ventas de hoy</div>
                        </div>
                        <div class="icon bg-success bg-opacity-10 text-success"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="value"><?php echo $hoy_stats['total']; ?></div>
                            <div class="label">Pedidos hoy</div>
                        </div>
                        <div class="icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-shopping-bag"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="value"><?php echo $pendientes; ?></div>
                            <div class="label">Pedidos pendientes</div>
                        </div>
                        <div class="icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="value">$<?php echo number_format($ventas_mes, 0); ?></div>
                            <div class="label">Ventas del mes</div>
                        </div>
                        <div class="icon bg-info bg-opacity-10 text-info"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <h5 class="mb-3"><i class="fas fa-bolt text-warning me-2"></i>Acciones rapidas</h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <a href="pedidos.php" class="quick-action">
                    <i class="fas fa-shopping-bag d-block"></i>
                    <span>Ver Pedidos</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="menu.php" class="quick-action">
                    <i class="fas fa-plus-circle d-block"></i>
                    <span>Agregar Producto</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="promociones.php" class="quick-action">
                    <i class="fas fa-ticket-alt d-block"></i>
                    <span>Crear Cupon</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="analytics_negocio.php" class="quick-action">
                    <i class="fas fa-chart-pie d-block"></i>
                    <span>Ver Analytics</span>
                </a>
            </div>
        </div>

        <!-- Info adicional -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <h5 class="mb-3"><i class="fas fa-utensils text-primary me-2"></i>Tu Menu</h5>
                    <p class="mb-2"><strong><?php echo $productos_activos; ?></strong> productos activos</p>
                    <a href="menu.php" class="btn btn-outline-primary btn-sm">Gestionar menu</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <h5 class="mb-3"><i class="fas fa-store text-success me-2"></i>Tu Negocio</h5>
                    <p class="mb-2"><strong><?php echo htmlspecialchars($negocio_info['nombre']); ?></strong></p>
                    <a href="negocio_configuracion.php" class="btn btn-outline-success btn-sm">Editar negocio</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
