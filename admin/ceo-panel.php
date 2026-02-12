<?php
/**
 * QuickBite - Panel CEO Completo
 * Gestión de membresías, repartidores, comisiones y más
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../models/Repartidor.php';
require_once __DIR__ . '/../models/Membership.php';
require_once __DIR__ . '/../models/Categoria.php';

// Verificar autenticación CEO
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_ceo = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "ceo";

if (!$usuario_logueado || !$es_ceo) {
    header("Location: ceo-login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Tab activa
$tab = $_GET['tab'] ?? 'dashboard';

// Procesar acciones POST
$mensaje = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Aprobar/Rechazar membresía de negocio
    if ($accion === 'actualizar_membresia_negocio') {
        $id_negocio = (int)$_POST['id_negocio'];
        $nuevo_estado = $_POST['estado'];
        try {
            if ($nuevo_estado === 'aprobar') {
                $db->prepare("UPDATE negocios SET es_premium = 1, comision_porcentaje = 5.00 WHERE id_negocio = ?")->execute([$id_negocio]);
            } else {
                $db->prepare("UPDATE negocios SET es_premium = 0, comision_porcentaje = 10.00 WHERE id_negocio = ?")->execute([$id_negocio]);
            }
            $mensaje = ['tipo' => 'success', 'texto' => 'Membresía actualizada correctamente'];
        } catch (Exception $e) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
        }
    }

    // Marcar recompensa como enviada
    if ($accion === 'enviar_recompensa') {
        $id_recompensa = (int)$_POST['id_recompensa'];
        $tracking = $_POST['tracking'] ?? '';
        try {
            $db->prepare("UPDATE recompensas_repartidor SET estado = 'enviada', fecha_envio = CURDATE(), tracking_number = ? WHERE id_recompensa = ?")
               ->execute([$tracking, $id_recompensa]);
            $mensaje = ['tipo' => 'success', 'texto' => 'Recompensa marcada como enviada'];
        } catch (Exception $e) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
        }
    }

    // Registrar pago de deuda
    if ($accion === 'pagar_deuda') {
        $id_repartidor = (int)$_POST['id_repartidor'];
        $monto = (float)$_POST['monto'];
        try {
            $db->prepare("UPDATE repartidores SET saldo_deuda = saldo_deuda + ? WHERE id_repartidor = ?")->execute([$monto, $id_repartidor]);
            $db->prepare("UPDATE deudas_comisiones SET estado = 'pagada', fecha_pago = CURDATE() WHERE id_repartidor = ? AND estado = 'pendiente' LIMIT 1")->execute([$id_repartidor]);
            $mensaje = ['tipo' => 'success', 'texto' => 'Pago registrado correctamente'];
        } catch (Exception $e) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
        }
    }

    // Crear categoría base
    if ($accion === 'crear_categoria') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $icono = '';

        if (!empty($nombre)) {
            // Procesar imagen si se ha cargado
            if (isset($_FILES['icono']) && $_FILES['icono']['error'] === UPLOAD_ERR_OK) {
                $directorio_destino = __DIR__ . "/../assets/img/categories/";
                if (!file_exists($directorio_destino)) {
                    mkdir($directorio_destino, 0777, true);
                }
                $nombre_archivo = uniqid('categoria_') . '_' . basename($_FILES['icono']['name']);
                $ruta_completa = $directorio_destino . $nombre_archivo;
                $tipo_archivo = strtolower(pathinfo($ruta_completa, PATHINFO_EXTENSION));
                if (in_array($tipo_archivo, ['jpg', 'jpeg', 'png', 'webp'])) {
                    if (move_uploaded_file($_FILES['icono']['tmp_name'], $ruta_completa)) {
                        $icono = "/assets/img/categories/" . $nombre_archivo;
                    }
                }
            }

            try {
                $categoria = new Categoria($db);
                $categoria->nombre = $nombre;
                $categoria->descripcion = $descripcion;
                $categoria->icono = $icono;
                if ($categoria->crear()) {
                    $mensaje = ['tipo' => 'success', 'texto' => 'Categoría base creada correctamente'];
                } else {
                    $mensaje = ['tipo' => 'error', 'texto' => 'Error al crear la categoría'];
                }
            } catch (Exception $e) {
                $mensaje = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
            }
        } else {
            $mensaje = ['tipo' => 'error', 'texto' => 'El nombre es obligatorio'];
        }
    }

    // Eliminar categoría base
    if ($accion === 'eliminar_categoria') {
        $id_categoria = (int)$_POST['id_categoria'];
        try {
            // Verificar si hay negocios usando esta categoría
            $stmt = $db->prepare("SELECT COUNT(*) FROM relacion_negocio_categoria WHERE id_categoria = ?");
            $stmt->execute([$id_categoria]);
            $en_uso = $stmt->fetchColumn();

            if ($en_uso > 0) {
                $mensaje = ['tipo' => 'error', 'texto' => 'No se puede eliminar: hay ' . $en_uso . ' negocios usando esta categoría'];
            } else {
                $categoria = new Categoria($db);
                $categoria->id_categoria = $id_categoria;
                if ($categoria->eliminar()) {
                    $mensaje = ['tipo' => 'success', 'texto' => 'Categoría eliminada correctamente'];
                } else {
                    $mensaje = ['tipo' => 'error', 'texto' => 'Error al eliminar la categoría'];
                }
            }
        } catch (Exception $e) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
        }
    }
}

// ========== OBTENER DATOS ==========

// Estadísticas generales
$stats = [];
try {
    // Ingresos totales
    $stats['ingresos'] = $db->query("SELECT COALESCE(SUM(monto_total), 0) FROM pedidos")->fetchColumn();

    // Total pedidos
    $stats['pedidos'] = $db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();

    // Pedidos entregados
    $stats['entregados'] = $db->query("SELECT COUNT(*) FROM pedidos WHERE id_estado = 6")->fetchColumn();

    // Negocios activos
    $stats['negocios'] = $db->query("SELECT COUNT(*) FROM negocios WHERE activo = 1")->fetchColumn();

    // Negocios PRO
    $stats['negocios_pro'] = $db->query("SELECT COUNT(*) FROM negocios WHERE es_premium = 1 AND activo = 1")->fetchColumn();

    // Repartidores activos
    $stats['repartidores'] = $db->query("SELECT COUNT(*) FROM repartidores WHERE activo = 1")->fetchColumn();

    // Membresías clientes activas
    $stats['membresias_clientes'] = $db->query("SELECT COUNT(*) FROM membresias WHERE estado = 'activo' AND fecha_fin >= CURDATE()")->fetchColumn();

    // Comisiones del mes
    $stats['comisiones_mes'] = $db->query("SELECT COALESCE(SUM(comision_plataforma), 0) FROM pedidos WHERE MONTH(fecha_creacion) = MONTH(CURDATE()) AND id_estado = 6")->fetchColumn();

    // Deudas pendientes repartidores
    $stats['deudas_pendientes'] = $db->query("SELECT COALESCE(SUM(ABS(saldo_deuda)), 0) FROM repartidores WHERE saldo_deuda < 0")->fetchColumn();

} catch (Exception $e) {
    error_log("Error stats CEO: " . $e->getMessage());
}

// Top negocios
$top_negocios = $db->query("
    SELECT n.id_negocio, n.nombre, n.es_premium, n.comision_porcentaje,
           COUNT(p.id_pedido) as total_pedidos,
           COALESCE(SUM(p.monto_total), 0) as total_ventas
    FROM negocios n
    LEFT JOIN pedidos p ON n.id_negocio = p.id_negocio AND p.id_estado = 6
    WHERE n.activo = 1
    GROUP BY n.id_negocio
    ORDER BY total_ventas DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Membresías de negocios
$membresias_negocios = $db->query("
    SELECT n.id_negocio, n.nombre, n.logo, n.es_premium, n.comision_porcentaje,
           n.fecha_inicio_premium, n.fecha_fin_premium,
           COALESCE(SUM(p.monto_total), 0) as ventas_mes
    FROM negocios n
    LEFT JOIN pedidos p ON n.id_negocio = p.id_negocio AND p.id_estado = 6 AND MONTH(p.fecha_creacion) = MONTH(CURDATE())
    WHERE n.activo = 1
    GROUP BY n.id_negocio
    ORDER BY n.es_premium DESC, ventas_mes DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Repartidores con gamificación
$repartidores_gami = [];
try {
    $repartidores_gami = $db->query("
        SELECT r.id_repartidor, r.total_entregas, r.calificacion_promedio, r.saldo_deuda, r.bloqueado_por_deuda,
               r.id_nivel, r.recompensa_reclamada,
               u.nombre, u.apellido, u.telefono,
               n.nombre as nivel_nombre, n.emoji as nivel_emoji, n.recompensa as nivel_recompensa
        FROM repartidores r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        LEFT JOIN niveles_repartidor n ON r.id_nivel = n.id_nivel
        WHERE r.activo = 1
        ORDER BY r.total_entregas DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error repartidores gami: " . $e->getMessage());
}

// Recompensas pendientes de envío
$recompensas_pendientes = [];
try {
    $recompensas_pendientes = $db->query("
        SELECT rr.*, u.nombre, u.apellido, u.telefono, n.emoji, n.nombre as nivel_nombre
        FROM recompensas_repartidor rr
        JOIN repartidores r ON rr.id_repartidor = r.id_repartidor
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        JOIN niveles_repartidor n ON rr.id_nivel = n.id_nivel
        WHERE rr.estado = 'pendiente'
        ORDER BY rr.fecha_solicitud ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error recompensas: " . $e->getMessage());
}

// Repartidores con deuda
$repartidores_deuda = [];
try {
    $repartidores_deuda = $db->query("
        SELECT r.id_repartidor, r.saldo_deuda, r.bloqueado_por_deuda,
               u.nombre, u.apellido, u.telefono
        FROM repartidores r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.saldo_deuda < 0
        ORDER BY r.saldo_deuda ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error deudas: " . $e->getMessage());
}

// Membresías de clientes activas
$membresias_clientes = $db->query("
    SELECT m.*, u.nombre, u.apellido, u.email
    FROM membresias m
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    WHERE m.estado = 'activo' AND m.fecha_fin >= CURDATE()
    ORDER BY m.fecha_fin ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Comisiones por día (últimos 30 días)
$comisiones_diarias = $db->query("
    SELECT DATE(fecha_creacion) as fecha,
           COUNT(*) as pedidos,
           COALESCE(SUM(monto_total), 0) as ventas,
           COALESCE(SUM(comision_plataforma), 0) as comision
    FROM pedidos
    WHERE fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND id_estado = 6
    GROUP BY DATE(fecha_creacion)
    ORDER BY fecha DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Categorías base (para negocios)
$categorias_base = [];
try {
    $categorias_base = $db->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM relacion_negocio_categoria rnc WHERE rnc.id_categoria = c.id_categoria) as negocios_usando
        FROM categorias_negocio c
        ORDER BY c.nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error categorías: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel CEO - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-dark: #0052CC;
            --gold: #FFD700;
            --success: #22C55E;
            --danger: #EF4444;
            --warning: #F59E0B;
            --dark: #1E293B;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-500: #64748B;
            --sidebar-width: 250px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
        }

        h1, h2, h3 { font-family: 'DM Sans', sans-serif; }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark) 0%, #0F172A 100%);
            color: white;
            padding: 1.5rem;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-logo {
            text-align: center;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1.5rem;
        }

        .sidebar-logo h4 {
            color: var(--primary);
            margin: 0;
        }

        .sidebar-logo small {
            color: var(--gold);
        }

        .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-link.active {
            background: var(--primary);
        }

        .nav-link i { width: 20px; text-align: center; }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 1.75rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-card .label {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .stat-card.gold .value { color: var(--gold); }
        .stat-card.success .value { color: var(--success); }
        .stat-card.danger .value { color: var(--danger); }

        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            border: none;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--gray-100);
            padding: 1rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body { padding: 1.5rem; }

        /* Tables */
        .table {
            margin: 0;
        }

        .table th {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--gray-500);
            border: none;
        }

        .table td {
            vertical-align: middle;
            border-color: var(--gray-100);
        }

        /* Badges */
        .badge-pro {
            background: var(--gold);
            color: var(--dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .badge-aliado {
            background: var(--gray-100);
            color: var(--gray-500);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        .badge-bloqueado {
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        /* Buttons */
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-success {
            background: var(--success);
            border-color: var(--success);
        }

        .btn-warning {
            background: var(--warning);
            border-color: var(--warning);
            color: var(--dark);
        }

        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-success { background: #F0FDF4; color: var(--success); }
        .alert-danger { background: #FEF2F2; color: var(--danger); }

        /* Nivel emoji */
        .nivel-badge {
            font-size: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <h4><i class="fas fa-bolt"></i> QuickBite</h4>
            <small>Panel CEO</small>
        </div>
        <nav>
            <a href="?tab=dashboard" class="nav-link <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="?tab=negocios" class="nav-link <?php echo $tab === 'negocios' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i> Negocios PRO
            </a>
            <a href="?tab=repartidores" class="nav-link <?php echo $tab === 'repartidores' ? 'active' : ''; ?>">
                <i class="fas fa-motorcycle"></i> Repartidores
            </a>
            <a href="?tab=recompensas" class="nav-link <?php echo $tab === 'recompensas' ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i> Recompensas
                <?php if (count($recompensas_pendientes) > 0): ?>
                    <span class="badge bg-danger"><?php echo count($recompensas_pendientes); ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=deudas" class="nav-link <?php echo $tab === 'deudas' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> Deudas
                <?php if (count($repartidores_deuda) > 0): ?>
                    <span class="badge bg-warning text-dark"><?php echo count($repartidores_deuda); ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=comisiones" class="nav-link <?php echo $tab === 'comisiones' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i> Comisiones
            </a>
            <a href="?tab=membresias" class="nav-link <?php echo $tab === 'membresias' ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i> Membresías Club
            </a>
            <a href="?tab=cupones" class="nav-link <?php echo $tab === 'cupones' ? 'active' : ''; ?>">
                <i class="fas fa-ticket-alt"></i> Cupones
            </a>
            <a href="?tab=categorias" class="nav-link <?php echo $tab === 'categorias' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Categorías Base
            </a>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="promotional_banners.php" class="nav-link">
                <i class="fas fa-images"></i> Banners
            </a>
            <a href="gestionar_aliados.php" class="nav-link">
                <i class="fas fa-handshake"></i> Aliados
            </a>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="../index.php" class="nav-link">
                <i class="fas fa-home"></i> Ir al Sitio
            </a>
            <a href="../logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $mensaje['tipo'] === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas <?php echo $mensaje['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $mensaje['texto']; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
        <!-- ========== DASHBOARD ========== -->
        <div class="page-header">
            <h1><i class="fas fa-chart-pie"></i> Dashboard General</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="value">$<?php echo number_format($stats['ingresos'], 0); ?></div>
                <div class="label">Ingresos Totales</div>
            </div>
            <div class="stat-card success">
                <div class="value"><?php echo number_format($stats['entregados']); ?></div>
                <div class="label">Pedidos Entregados</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $stats['negocios']; ?></div>
                <div class="label">Negocios Activos</div>
            </div>
            <div class="stat-card gold">
                <div class="value"><?php echo $stats['negocios_pro']; ?></div>
                <div class="label">Negocios PRO</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $stats['repartidores']; ?></div>
                <div class="label">Repartidores</div>
            </div>
            <div class="stat-card success">
                <div class="value">$<?php echo number_format($stats['comisiones_mes'], 0); ?></div>
                <div class="label">Comisiones del Mes</div>
            </div>
            <div class="stat-card danger">
                <div class="value">$<?php echo number_format($stats['deudas_pendientes'], 0); ?></div>
                <div class="label">Deudas Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $stats['membresias_clientes']; ?></div>
                <div class="label">Membresías Club</div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-trophy text-warning"></i> Top 10 Negocios</div>
                    <div class="card-body p-0">
                        <table class="table">
                            <thead>
                                <tr><th>Negocio</th><th>Pedidos</th><th>Ventas</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_negocios as $n): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($n['nombre']); ?>
                                        <?php if ($n['es_premium']): ?><span class="badge-pro">PRO</span><?php endif; ?>
                                    </td>
                                    <td><?php echo $n['total_pedidos']; ?></td>
                                    <td>$<?php echo number_format($n['total_ventas'], 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-motorcycle text-primary"></i> Top Repartidores</div>
                    <div class="card-body p-0">
                        <table class="table">
                            <thead>
                                <tr><th>Repartidor</th><th>Nivel</th><th>Entregas</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($repartidores_gami, 0, 10) as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['nombre'] . ' ' . $r['apellido']); ?></td>
                                    <td><span class="nivel-badge"><?php echo $r['nivel_emoji'] ?? '🆕'; ?></span> <?php echo $r['nivel_nombre'] ?? 'Nuevo'; ?></td>
                                    <td><?php echo $r['total_entregas']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'negocios'): ?>
        <!-- ========== NEGOCIOS PRO ========== -->
        <div class="page-header">
            <h1><i class="fas fa-store"></i> Gestión de Negocios</h1>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-crown text-warning"></i> Membresías de Negocios</div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Negocio</th>
                            <th>Plan</th>
                            <th>Comisión</th>
                            <th>Ventas Mes</th>
                            <th>Vencimiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membresias_negocios as $n): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($n['nombre']); ?></strong>
                            </td>
                            <td>
                                <?php if ($n['es_premium']): ?>
                                    <span class="badge-pro"><i class="fas fa-crown"></i> PRO</span>
                                <?php else: ?>
                                    <span class="badge-aliado">Aliado</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $n['comision_porcentaje'] ?? 10; ?>%</td>
                            <td>$<?php echo number_format($n['ventas_mes'], 0); ?></td>
                            <td>
                                <?php echo $n['fecha_fin_premium'] ? date('d/m/Y', strtotime($n['fecha_fin_premium'])) : '-'; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="actualizar_membresia_negocio">
                                    <input type="hidden" name="id_negocio" value="<?php echo $n['id_negocio']; ?>">
                                    <?php if ($n['es_premium']): ?>
                                        <button type="submit" name="estado" value="quitar" class="btn btn-sm btn-outline-danger">
                                            Quitar PRO
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="estado" value="aprobar" class="btn btn-sm btn-warning">
                                            <i class="fas fa-crown"></i> Hacer PRO
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'repartidores'): ?>
        <!-- ========== REPARTIDORES ========== -->
        <div class="page-header">
            <h1><i class="fas fa-motorcycle"></i> Gamificación de Repartidores</h1>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-trophy"></i> Niveles y Progreso</div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Repartidor</th>
                            <th>Nivel</th>
                            <th>Entregas</th>
                            <th>Calificación</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repartidores_gami as $r): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($r['nombre'] . ' ' . $r['apellido']); ?></strong>
                                <br><small class="text-muted"><?php echo $r['telefono']; ?></small>
                            </td>
                            <td>
                                <span class="nivel-badge"><?php echo $r['nivel_emoji'] ?? '🆕'; ?></span>
                                <?php echo $r['nivel_nombre'] ?? 'Nuevo'; ?>
                            </td>
                            <td><strong><?php echo $r['total_entregas']; ?></strong></td>
                            <td>
                                <i class="fas fa-star text-warning"></i>
                                <?php echo number_format($r['calificacion_promedio'] ?? 5, 1); ?>
                            </td>
                            <td class="<?php echo $r['saldo_deuda'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                $<?php echo number_format($r['saldo_deuda'] ?? 0, 2); ?>
                            </td>
                            <td>
                                <?php if ($r['bloqueado_por_deuda']): ?>
                                    <span class="badge-bloqueado"><i class="fas fa-ban"></i> Bloqueado</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'recompensas'): ?>
        <!-- ========== RECOMPENSAS ========== -->
        <div class="page-header">
            <h1><i class="fas fa-gift"></i> Envío de Recompensas (Kit Marketing)</h1>
        </div>

        <?php if (empty($recompensas_pendientes)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">No hay recompensas pendientes</h4>
                    <p class="text-muted">Todas las recompensas han sido enviadas</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-box text-warning"></i> Pendientes de Envío (<?php echo count($recompensas_pendientes); ?>)</div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Repartidor</th>
                                <th>Nivel</th>
                                <th>Recompensa</th>
                                <th>Dirección</th>
                                <th>Fecha Solicitud</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recompensas_pendientes as $rw): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($rw['nombre'] . ' ' . $rw['apellido']); ?></strong>
                                    <br><small><?php echo $rw['telefono']; ?></small>
                                </td>
                                <td><span class="nivel-badge"><?php echo $rw['emoji']; ?></span> <?php echo $rw['nivel_nombre']; ?></td>
                                <td><strong><?php echo $rw['recompensa']; ?></strong></td>
                                <td style="max-width: 200px;"><?php echo htmlspecialchars($rw['direccion_envio']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($rw['fecha_solicitud'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalEnvio<?php echo $rw['id_recompensa']; ?>">
                                        <i class="fas fa-truck"></i> Marcar Enviado
                                    </button>

                                    <!-- Modal -->
                                    <div class="modal fade" id="modalEnvio<?php echo $rw['id_recompensa']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5>Confirmar Envío</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="accion" value="enviar_recompensa">
                                                        <input type="hidden" name="id_recompensa" value="<?php echo $rw['id_recompensa']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Número de Guía (opcional)</label>
                                                            <input type="text" name="tracking" class="form-control" placeholder="Ej: 1234567890">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check"></i> Confirmar Envío
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php elseif ($tab === 'deudas'): ?>
        <!-- ========== DEUDAS ========== -->
        <div class="page-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Deudas de Repartidores</h1>
        </div>

        <?php if (empty($repartidores_deuda)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Sin deudas pendientes</h4>
                    <p class="text-muted">Todos los repartidores están al corriente</p>
                </div>
            </div>
        <?php else: ?>
            <div class="alert" style="background: #FEF3C7; color: #92400E;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Deuda total pendiente: $<?php echo number_format($stats['deudas_pendientes'], 2); ?></strong>
                - Los repartidores con deuda mayor a $200 están bloqueados automáticamente.
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-users text-danger"></i> Repartidores con Deuda</div>
                <div class="card-body p-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Repartidor</th>
                                <th>Teléfono</th>
                                <th>Deuda</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repartidores_deuda as $rd): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rd['nombre'] . ' ' . $rd['apellido']); ?></strong></td>
                                <td><?php echo $rd['telefono']; ?></td>
                                <td class="text-danger"><strong>-$<?php echo number_format(abs($rd['saldo_deuda']), 2); ?></strong></td>
                                <td>
                                    <?php if ($rd['bloqueado_por_deuda']): ?>
                                        <span class="badge-bloqueado"><i class="fas fa-ban"></i> BLOQUEADO</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Con deuda</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalPago<?php echo $rd['id_repartidor']; ?>">
                                        <i class="fas fa-dollar-sign"></i> Registrar Pago
                                    </button>

                                    <!-- Modal Pago -->
                                    <div class="modal fade" id="modalPago<?php echo $rd['id_repartidor']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5>Registrar Pago de Deuda</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="accion" value="pagar_deuda">
                                                        <input type="hidden" name="id_repartidor" value="<?php echo $rd['id_repartidor']; ?>">
                                                        <p>Deuda actual: <strong class="text-danger">$<?php echo number_format(abs($rd['saldo_deuda']), 2); ?></strong></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Monto Pagado</label>
                                                            <input type="number" name="monto" class="form-control" step="0.01"
                                                                   max="<?php echo abs($rd['saldo_deuda']); ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check"></i> Registrar Pago
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php elseif ($tab === 'comisiones'): ?>
        <!-- ========== COMISIONES ========== -->
        <div class="page-header">
            <h1><i class="fas fa-coins"></i> Estadísticas de Comisiones</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card success">
                <div class="value">$<?php echo number_format($stats['comisiones_mes'], 0); ?></div>
                <div class="label">Comisiones Este Mes</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $stats['entregados']; ?></div>
                <div class="label">Pedidos Completados</div>
            </div>
            <div class="stat-card gold">
                <div class="value"><?php echo $stats['negocios_pro']; ?></div>
                <div class="label">Negocios PRO (5%)</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $stats['negocios'] - $stats['negocios_pro']; ?></div>
                <div class="label">Negocios Aliados (10%)</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-calendar"></i> Comisiones Últimos 30 Días</div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Pedidos</th>
                            <th>Ventas</th>
                            <th>Comisión QuickBite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comisiones_diarias as $cd): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($cd['fecha'])); ?></td>
                            <td><?php echo $cd['pedidos']; ?></td>
                            <td>$<?php echo number_format($cd['ventas'], 0); ?></td>
                            <td class="text-success"><strong>$<?php echo number_format($cd['comision'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'membresias'): ?>
        <!-- ========== MEMBRESÍAS CLUB ========== -->
        <div class="page-header">
            <h1><i class="fas fa-id-card"></i> Membresías QuickBite Club</h1>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-users"></i> Miembros Activos (<?php echo count($membresias_clientes); ?>)</div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Inicio</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membresias_clientes as $mc): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($mc['nombre'] . ' ' . $mc['apellido']); ?></strong></td>
                            <td><?php echo htmlspecialchars($mc['email']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($mc['fecha_inicio'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($mc['fecha_fin'])); ?></td>
                            <td><span class="badge bg-success">Activo</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'cupones'): ?>
        <!-- ========== CUPONES ========== -->
        <?php
        // Procesar acciones de cupones
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cupon'])) {
            $accion_cupon = $_POST['accion_cupon'];

            if ($accion_cupon === 'crear') {
                $stmt = $db->prepare("INSERT INTO cupones (codigo, descripcion, tipo_descuento, valor_descuento, minimo_compra, maximo_descuento, usos_maximos, usos_por_usuario, fecha_expiracion, solo_primera_compra, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    strtoupper(trim($_POST['codigo'])),
                    trim($_POST['descripcion']),
                    $_POST['tipo_descuento'],
                    (float)$_POST['valor_descuento'],
                    (float)$_POST['minimo_compra'],
                    $_POST['maximo_descuento'] ? (float)$_POST['maximo_descuento'] : null,
                    $_POST['usos_maximos'] ? (int)$_POST['usos_maximos'] : null,
                    (int)$_POST['usos_por_usuario'],
                    $_POST['fecha_expiracion'] ?: null,
                    isset($_POST['solo_primera_compra']) ? 1 : 0
                ]);
                $mensaje = ['tipo' => 'success', 'texto' => 'Cupón creado correctamente'];
            }

            if ($accion_cupon === 'toggle') {
                $id_cupon = (int)$_POST['id_cupon'];
                $db->prepare("UPDATE cupones SET activo = NOT activo WHERE id_cupon = ?")->execute([$id_cupon]);
                $mensaje = ['tipo' => 'success', 'texto' => 'Estado del cupón actualizado'];
            }

            if ($accion_cupon === 'eliminar') {
                $id_cupon = (int)$_POST['id_cupon'];
                $db->prepare("DELETE FROM cupones WHERE id_cupon = ?")->execute([$id_cupon]);
                $mensaje = ['tipo' => 'success', 'texto' => 'Cupón eliminado'];
            }
        }

        // Obtener cupones
        $cupones = $db->query("SELECT c.*, (SELECT COUNT(*) FROM cupones_usuarios cu WHERE cu.id_cupon = c.id_cupon) as usos_reales FROM cupones c ORDER BY c.fecha_creacion DESC")->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="page-header">
            <h1><i class="fas fa-ticket-alt"></i> Gestión de Cupones</h1>
        </div>

        <!-- Crear nuevo cupón -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-plus-circle"></i> Crear Nuevo Cupón</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="accion_cupon" value="crear">
                    <div class="col-md-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" class="form-control" required placeholder="Ej: PROMO20" style="text-transform: uppercase;">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control" required placeholder="Descripción del cupón">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="tipo_descuento" class="form-select">
                            <option value="porcentaje">Porcentaje %</option>
                            <option value="monto_fijo">Monto Fijo $</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor</label>
                        <input type="number" name="valor_descuento" class="form-control" step="0.01" required placeholder="10">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Mínimo compra</label>
                        <input type="number" name="minimo_compra" class="form-control" step="0.01" value="0" placeholder="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Máx. descuento</label>
                        <input type="number" name="maximo_descuento" class="form-control" step="0.01" placeholder="Opcional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Usos máximos</label>
                        <input type="number" name="usos_maximos" class="form-control" placeholder="Ilimitado">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Usos/usuario</label>
                        <input type="number" name="usos_por_usuario" class="form-control" value="1" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Expiración</label>
                        <input type="date" name="fecha_expiracion" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="solo_primera_compra" class="form-check-input" id="primera_compra">
                            <label class="form-check-label" for="primera_compra">Solo 1ra compra</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Crear Cupón</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de cupones -->
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> Cupones Existentes (<?php echo count($cupones); ?>)</div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Descuento</th>
                            <th>Mín. compra</th>
                            <th>Usos</th>
                            <th>Expiración</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cupones as $cupon): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cupon['codigo']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cupon['descripcion']); ?></td>
                            <td>
                                <?php if ($cupon['tipo_descuento'] === 'porcentaje'): ?>
                                    <?php echo $cupon['valor_descuento']; ?>%
                                <?php else: ?>
                                    $<?php echo number_format($cupon['valor_descuento'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($cupon['minimo_compra'], 2); ?></td>
                            <td>
                                <?php echo $cupon['usos_reales']; ?>
                                <?php if ($cupon['usos_maximos']): ?>
                                    / <?php echo $cupon['usos_maximos']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cupon['fecha_expiracion']): ?>
                                    <?php echo date('d/m/Y', strtotime($cupon['fecha_expiracion'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Sin límite</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cupon['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion_cupon" value="toggle">
                                    <input type="hidden" name="id_cupon" value="<?php echo $cupon['id_cupon']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $cupon['activo'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <i class="fas <?php echo $cupon['activo'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este cupón?')">
                                    <input type="hidden" name="accion_cupon" value="eliminar">
                                    <input type="hidden" name="id_cupon" value="<?php echo $cupon['id_cupon']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'categorias'): ?>
        <!-- ========== CATEGORIAS BASE ========== -->
        <div class="page-header">
            <h1><i class="fas fa-tags"></i> Categorías Base de Negocios</h1>
            <p class="text-muted mb-0">Estas categorías clasifican los tipos de negocios (Pizzería, Restaurante, etc.). Los negocios NO pueden modificarlas.</p>
        </div>

        <!-- Formulario crear categoría -->
        <div class="card">
            <div class="card-header"><i class="fas fa-plus text-success"></i> Nueva Categoría Base</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="accion" value="crear_categoria">
                    <div class="col-md-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Pizzería" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Descripción opcional">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Icono</label>
                        <input type="file" name="icono" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-save"></i> Crear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de categorías -->
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> Categorías Existentes (<?php echo count($categorias_base); ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($categorias_base)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-3 text-muted">No hay categorías base. Crea la primera.</p>
                    </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th width="60">Icono</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Negocios</th>
                            <th width="100">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias_base as $cat): ?>
                        <tr>
                            <td>
                                <?php if (!empty($cat['icono'])): ?>
                                    <img src="..<?php echo htmlspecialchars($cat['icono']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; background: var(--gray-100); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($cat['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cat['descripcion'] ?? ''); ?></td>
                            <td>
                                <?php if ($cat['negocios_usando'] > 0): ?>
                                    <span class="badge bg-primary"><?php echo $cat['negocios_usando']; ?> negocios</span>
                                <?php else: ?>
                                    <span class="text-muted">Sin uso</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta categoría?')">
                                    <input type="hidden" name="accion" value="eliminar_categoria">
                                    <input type="hidden" name="id_categoria" value="<?php echo $cat['id_categoria']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" <?php echo $cat['negocios_usando'] > 0 ? 'disabled title="En uso"' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i>
            <strong>Nota:</strong> Estas categorías son las que los negocios seleccionan al registrarse (Pizzería, Comida Mexicana, etc.).
            No se pueden eliminar si hay negocios usándolas. Los negocios gestionan sus propias categorías de <strong>productos</strong> en su panel.
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
