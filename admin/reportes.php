<?php
// Configurar reporte de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Pedido.php';
require_once __DIR__ . '/../models/Repartidor.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado y es un negocio
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/reportes.php");
    exit;
}

// Obtener información del usuario y su negocio
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
$negocio->id_negocio = $negocio_info['id_negocio'];

// Instanciar modelos
$pedido = new Pedido($db);
$repartidor = new Repartidor($db);

// Obtener estadísticas generales de pedidos para el negocio
try {
    $estadisticas_pedidos = $pedido->obtenerEstadisticasNegocio($negocio->id_negocio);
    $estadisticas_pedidos = array_merge([
        'total' => 0,
        'pendientes' => 0,
        'confirmados' => 0,
        'preparando' => 0,
        'listos' => 0,
        'en_camino' => 0,
        'entregados' => 0,
        'cancelados' => 0,
        'ingresos_totales' => 0,
        'ticket_promedio' => 0,
    ], $estadisticas_pedidos);
} catch (Exception $e) {
    $estadisticas_pedidos = [
        'total' => 0,
        'pendientes' => 0,
        'confirmados' => 0,
        'preparando' => 0,
        'listos' => 0,
        'en_camino' => 0,
        'entregados' => 0,
        'cancelados' => 0,
        'ingresos_totales' => 0,
        'ticket_promedio' => 0,
    ];
}

// Obtener compras totales de usuarios (número de pedidos por usuario)
try {
    $query = "SELECT u.id_usuario, u.nombre, u.apellido, COUNT(p.id_pedido) AS total_compras, SUM(p.monto_total) AS total_gastado
              FROM pedidos p
              JOIN usuarios u ON p.id_usuario = u.id_usuario
              WHERE p.id_negocio = :id_negocio
              GROUP BY u.id_usuario
              ORDER BY total_compras DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_negocio', $negocio->id_negocio);
    $stmt->execute();
    $usuarios_compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usuarios_compras = [];
}

// Obtener entregas realizadas por repartidores (número de entregas y tiempo promedio)
try {
    $query = "SELECT r.id_repartidor, r.nombre, r.apellido, COUNT(p.id_pedido) AS total_entregas,
                     AVG(TIMESTAMPDIFF(MINUTE, p.fecha_asignacion, p.fecha_entrega)) AS tiempo_promedio_entrega
              FROM pedidos p
              JOIN repartidores r ON p.id_repartidor = r.id_repartidor
              WHERE p.id_negocio = :id_negocio AND p.estado = 'entregado'
              GROUP BY r.id_repartidor
              ORDER BY total_entregas DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_negocio', $negocio->id_negocio);
    $stmt->execute();
    $repartidores_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $repartidores_entregas = [];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel de Reportes - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #E3F2FD;
            --primary-dark: #0153CC;
            --secondary: #F8F8F8;
            --accent: #FF9500;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --medium-gray: #888;
            --light-gray: #E8E8E8;
            --danger: #FF4D4D;
            --success: #4CAF50;
            --sidebar-width: 280px;
            --border-radius: 12px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            margin: 0;
            padding: 0;
            color: var(--dark);
            display: flex;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
        }
        .sidebar-brand {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .sidebar-brand i {
            margin-right: 15px;
            font-size: 1.8rem;
        }
        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
            overflow-y: auto;
        }
        .menu-section {
            padding: 0 20px;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--medium-gray);
        }
        .menu-item {
            padding: 14px 25px;
            display: flex;
            align-items: center;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            margin: 2px 0;
        }
        .menu-item i {
            margin-right: 15px;
            font-size: 1.1rem;
            color: var(--medium-gray);
            transition: all 0.3s ease;
            width: 20px;
            text-align: center;
        }
        .menu-item:hover {
            background-color: var(--primary-light);
            color: var(--primary);
        }
        .menu-item.active {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            position: relative;
            border-radius: 0 30px 30px 0;
            margin-left: 0;
        }
        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary);
        }
        .menu-item.active i {
            color: var(--primary);
        }
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
            background-color: white;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
            font-weight: 600;
            font-size: 1.2rem;
        }
        .user-details {
            flex-grow: 1;
        }
        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin: 0;
        }
        .user-role {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin: 0;
        }
        .logout-btn {
            color: var(--medium-gray);
            margin-left: 15px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            color: var(--danger);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px;
            position: relative;
            min-height: 100vh;
            background: var(--light);
        }
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--dark);
        }
        .page-description {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 600px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(1, 101, 255, 0.1);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.15;
        }
        .stat-icon.sales {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-icon.orders {
            background: linear-gradient(135deg, var(--accent), #f57c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-icon.deliveries {
            background: linear-gradient(135deg, var(--success), #2e7d32);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            font-size: 0.9rem;
            color: var(--medium-gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .stat-value.accent {
            background: linear-gradient(135deg, var(--accent), #f57c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            color: var(--medium-gray);
        }
        .stat-change-positive {
            color: var(--success);
        }
        .stat-change-negative {
            color: var(--danger);
        }
        .stat-change i {
            margin-right: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            text-align: left;
        }
        th {
            background: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 600;
        }
        tbody tr:hover {
            background: var(--primary-light);
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-brand">
                <i class="fas fa-utensils"></i>
                QuickBite
            </a>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
            <a href="pedidos.php" class="menu-item">
                <i class="fas fa-shopping-bag"></i>
                Pedidos
            </a>
            <div class="menu-section">NEGOCIO</div>
            <a href="negocio_configuracion.php" class="menu-item">
                <i class="fas fa-store"></i>
                Mi Negocio
            </a>
            <a href="reportes.php" class="menu-item active">
                <i class="fas fa-chart-bar"></i>
                Reportes
            </a>
            <div class="menu-section">CONFIGURACIÓN</div>
            <a href="configuracion.php" class="menu-item">
                <i class="fas fa-cog"></i>
                Configuración
            </a>
            <a href="perfil.php" class="menu-item">
                <i class="fas fa-user"></i>
                Mi Perfil
            </a>
            <a href="soporte.php" class="menu-item">
                <i class="fas fa-headset"></i>
                Soporte
            </a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($usuario->nombre, 0, 1); ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo $usuario->nombre . ' ' . $usuario->apellido; ?></p>
                    <p class="user-role">Propietario</p>
                </div>
                <a href="../logout.php" class="logout-btn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Panel de Reportes</h1>
                <p class="page-description">Visualiza reportes de ventas, compras y entregas de tu negocio</p>
            </div>
        </div>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon sales">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-label">Ingresos Totales</div>
                <div class="stat-value">$<?php echo number_format($estadisticas_pedidos['ingresos_totales'], 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orders">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-label">Total Pedidos</div>
                <div class="stat-value"><?php echo $estadisticas_pedidos['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon deliveries">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-label">Pedidos Entregados</div>
                <div class="stat-value"><?php echo $estadisticas_pedidos['entregados']; ?></div>
            </div>
        </div>
        <div>
            <h2 class="section-title">Top 10 Usuarios por Compras</h2>
            <?php if (empty($usuarios_compras)): ?>
                <p>No hay datos disponibles.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Total Compras</th>
                            <th>Total Gastado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios_compras as $usuario_compra): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario_compra['nombre'] . ' ' . $usuario_compra['apellido']); ?></td>
                            <td><?php echo $usuario_compra['total_compras']; ?></td>
                            <td>$<?php echo number_format($usuario_compra['total_gastado'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div>
            <h2 class="section-title">Top 10 Repartidores por Entregas</h2>
            <?php if (empty($repartidores_entregas)): ?>
                <p>No hay datos disponibles.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Repartidor</th>
                            <th>Total Entregas</th>
                            <th>Tiempo Promedio de Entrega (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repartidores_entregas as $repartidor_entrega): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($repartidor_entrega['nombre'] . ' ' . $repartidor_entrega['apellido']); ?></td>
                            <td><?php echo $repartidor_entrega['total_entregas']; ?></td>
                            <td><?php echo $repartidor_entrega['tiempo_promedio_entrega'] !== null ? number_format($repartidor_entrega['tiempo_promedio_entrega'], 2) : 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
