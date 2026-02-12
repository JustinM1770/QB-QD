<?php
/**
 * Analytics Dashboard para Negocios
 * Muestra estadísticas de ventas, productos más vendidos, pedidos por estado y horarios pico
 */

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

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado y es un negocio
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/analytics_negocio.php");
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
$id_negocio = $negocio_info['id_negocio'];

// =====================================================
// OBTENER ESTADÍSTICAS
// =====================================================

// Fechas para filtros
$hoy = date('Y-m-d');
$inicio_semana = date('Y-m-d', strtotime('monday this week'));
$inicio_mes = date('Y-m-01');
$hace_30_dias = date('Y-m-d', strtotime('-30 days'));

// 1. VENTAS DEL DÍA, SEMANA Y MES
try {
    // Ventas del día
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_pedidos, COALESCE(SUM(monto_total), 0) as total_ventas
        FROM pedidos
        WHERE id_negocio = ? AND DATE(fecha_creacion) = ? AND id_estado NOT IN (5, 7)
    ");
    $stmt->execute([$id_negocio, $hoy]);
    $ventas_hoy = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ventas de la semana
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_pedidos, COALESCE(SUM(monto_total), 0) as total_ventas
        FROM pedidos
        WHERE id_negocio = ? AND DATE(fecha_creacion) >= ? AND id_estado NOT IN (5, 7)
    ");
    $stmt->execute([$id_negocio, $inicio_semana]);
    $ventas_semana = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ventas del mes
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_pedidos, COALESCE(SUM(monto_total), 0) as total_ventas
        FROM pedidos
        WHERE id_negocio = ? AND DATE(fecha_creacion) >= ? AND id_estado NOT IN (5, 7)
    ");
    $stmt->execute([$id_negocio, $inicio_mes]);
    $ventas_mes = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $ventas_hoy = ['total_pedidos' => 0, 'total_ventas' => 0];
    $ventas_semana = ['total_pedidos' => 0, 'total_ventas' => 0];
    $ventas_mes = ['total_pedidos' => 0, 'total_ventas' => 0];
}

// 2. PRODUCTOS MÁS VENDIDOS (últimos 30 días)
try {
    $stmt = $db->prepare("
        SELECT p.nombre, p.id_producto, SUM(dp.cantidad) as total_vendido,
               SUM(dp.subtotal) as ingresos_producto
        FROM detalles_pedido dp
        INNER JOIN productos p ON dp.id_producto = p.id_producto
        INNER JOIN pedidos ped ON dp.id_pedido = ped.id_pedido
        WHERE ped.id_negocio = ?
          AND ped.fecha_creacion >= ?
          AND ped.id_estado NOT IN (5, 7)
        GROUP BY p.id_producto, p.nombre
        ORDER BY total_vendido DESC
        LIMIT 10
    ");
    $stmt->execute([$id_negocio, $hace_30_dias]);
    $productos_mas_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productos_mas_vendidos = [];
}

// 3. PEDIDOS POR ESTADO
try {
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN id_estado = 1 THEN 1 ELSE 0 END), 0) as pendientes,
            COALESCE(SUM(CASE WHEN id_estado = 2 THEN 1 ELSE 0 END), 0) as confirmados,
            COALESCE(SUM(CASE WHEN id_estado = 3 THEN 1 ELSE 0 END), 0) as preparando,
            COALESCE(SUM(CASE WHEN id_estado = 4 THEN 1 ELSE 0 END), 0) as listos,
            COALESCE(SUM(CASE WHEN id_estado = 6 THEN 1 ELSE 0 END), 0) as en_camino,
            COALESCE(SUM(CASE WHEN id_estado = 8 THEN 1 ELSE 0 END), 0) as entregados,
            COALESCE(SUM(CASE WHEN id_estado IN (5, 7) THEN 1 ELSE 0 END), 0) as cancelados,
            COUNT(*) as total
        FROM pedidos
        WHERE id_negocio = ? AND fecha_creacion >= ?
    ");
    $stmt->execute([$id_negocio, $inicio_mes]);
    $pedidos_por_estado = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pedidos_por_estado = [
        'pendientes' => 0, 'confirmados' => 0, 'preparando' => 0,
        'listos' => 0, 'en_camino' => 0, 'entregados' => 0, 'cancelados' => 0, 'total' => 0
    ];
}

// 4. HORARIOS PICO (últimos 30 días)
try {
    $stmt = $db->prepare("
        SELECT HOUR(fecha_creacion) as hora, COUNT(*) as total_pedidos
        FROM pedidos
        WHERE id_negocio = ? AND fecha_creacion >= ? AND id_estado NOT IN (5, 7)
        GROUP BY HOUR(fecha_creacion)
        ORDER BY hora
    ");
    $stmt->execute([$id_negocio, $hace_30_dias]);
    $horarios_pico_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear array de 24 horas
    $horarios_pico = array_fill(0, 24, 0);
    foreach ($horarios_pico_raw as $h) {
        $horarios_pico[(int)$h['hora']] = (int)$h['total_pedidos'];
    }
} catch (Exception $e) {
    $horarios_pico = array_fill(0, 24, 0);
}

// 5. VENTAS DIARIAS (últimos 7 días)
try {
    $stmt = $db->prepare("
        SELECT DATE(fecha_creacion) as fecha, COUNT(*) as pedidos, COALESCE(SUM(monto_total), 0) as ventas
        FROM pedidos
        WHERE id_negocio = ? AND fecha_creacion >= ? AND id_estado NOT IN (5, 7)
        GROUP BY DATE(fecha_creacion)
        ORDER BY fecha
    ");
    $stmt->execute([$id_negocio, date('Y-m-d', strtotime('-7 days'))]);
    $ventas_diarias_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear array de 7 días
    $ventas_diarias = [];
    $labels_dias = [];
    for ($i = 6; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-$i days"));
        $labels_dias[] = date('D d', strtotime($fecha));
        $ventas_diarias[$fecha] = ['pedidos' => 0, 'ventas' => 0];
    }
    foreach ($ventas_diarias_raw as $v) {
        if (isset($ventas_diarias[$v['fecha']])) {
            $ventas_diarias[$v['fecha']] = ['pedidos' => (int)$v['pedidos'], 'ventas' => (float)$v['ventas']];
        }
    }
} catch (Exception $e) {
    $ventas_diarias = [];
    $labels_dias = [];
}

// 6. TICKET PROMEDIO
$ticket_promedio = $ventas_mes['total_pedidos'] > 0
    ? $ventas_mes['total_ventas'] / $ventas_mes['total_pedidos']
    : 0;

// Preparar datos para Chart.js
$datos_ventas_diarias = array_values(array_map(function($v) { return $v['ventas']; }, $ventas_diarias));
$datos_pedidos_diarios = array_values(array_map(function($v) { return $v['pedidos']; }, $ventas_diarias));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo htmlspecialchars($negocio_info['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #FF6B35;
            --secondary-color: #2E294E;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .stat-card .trend {
            font-size: 0.85rem;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .chart-card h5 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 15px;
        }

        .product-rank.top-3 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 500;
            color: var(--secondary-color);
        }

        .product-stats {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .back-btn {
            color: var(--secondary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            color: var(--primary-color);
        }

        .period-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-bolt"></i> QuickBite
            </a>
            <div class="d-flex align-items-center">
                <span class="me-3"><?php echo htmlspecialchars($negocio_info['nombre']); ?></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <a href="pedidos.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Volver a Pedidos
        </a>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Analytics del Negocio</h2>
                <p class="text-muted mb-0">Estadísticas y métricas de rendimiento</p>
            </div>
            <span class="badge bg-light text-dark">
                <i class="fas fa-calendar me-1"></i>
                <?php echo date('d M Y'); ?>
            </span>
        </div>

        <!-- Tarjetas de estadísticas principales -->
        <div class="row g-3 mb-4">
            <!-- Ventas Hoy -->
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <span class="period-badge bg-primary bg-opacity-10 text-primary">Hoy</span>
                    </div>
                    <div class="value">$<?php echo number_format($ventas_hoy['total_ventas'], 0); ?></div>
                    <div class="label"><?php echo $ventas_hoy['total_pedidos']; ?> pedidos</div>
                </div>
            </div>

            <!-- Ventas Semana -->
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <span class="period-badge bg-success bg-opacity-10 text-success">Semana</span>
                    </div>
                    <div class="value">$<?php echo number_format($ventas_semana['total_ventas'], 0); ?></div>
                    <div class="label"><?php echo $ventas_semana['total_pedidos']; ?> pedidos</div>
                </div>
            </div>

            <!-- Ventas Mes -->
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <span class="period-badge bg-warning bg-opacity-10 text-warning">Mes</span>
                    </div>
                    <div class="value">$<?php echo number_format($ventas_mes['total_ventas'], 0); ?></div>
                    <div class="label"><?php echo $ventas_mes['total_pedidos']; ?> pedidos</div>
                </div>
            </div>

            <!-- Ticket Promedio -->
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <span class="period-badge bg-info bg-opacity-10 text-info">Promedio</span>
                    </div>
                    <div class="value">$<?php echo number_format($ticket_promedio, 0); ?></div>
                    <div class="label">Ticket promedio</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Gráfica de Ventas Diarias -->
            <div class="col-lg-8">
                <div class="chart-card">
                    <h5><i class="fas fa-chart-bar me-2"></i>Ventas de los últimos 7 días</h5>
                    <canvas id="ventasDiariasChart" height="100"></canvas>
                </div>

                <!-- Gráfica de Horarios Pico -->
                <div class="chart-card">
                    <h5><i class="fas fa-clock me-2"></i>Horarios con más pedidos</h5>
                    <canvas id="horariosPicoChart" height="80"></canvas>
                </div>
            </div>

            <!-- Panel lateral -->
            <div class="col-lg-4">
                <!-- Pedidos por Estado -->
                <div class="chart-card">
                    <h5><i class="fas fa-tasks me-2"></i>Pedidos por Estado (Este mes)</h5>
                    <canvas id="pedidosEstadoChart" height="200"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="fas fa-circle text-warning"></i> Pendientes</span>
                            <strong><?php echo $pedidos_por_estado['pendientes']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="fas fa-circle text-info"></i> En proceso</span>
                            <strong><?php echo $pedidos_por_estado['confirmados'] + $pedidos_por_estado['preparando'] + $pedidos_por_estado['listos']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="fas fa-circle text-primary"></i> En camino</span>
                            <strong><?php echo $pedidos_por_estado['en_camino']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><i class="fas fa-circle text-success"></i> Entregados</span>
                            <strong><?php echo $pedidos_por_estado['entregados']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span><i class="fas fa-circle text-danger"></i> Cancelados</span>
                            <strong><?php echo $pedidos_por_estado['cancelados']; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Productos Más Vendidos -->
                <div class="chart-card">
                    <h5><i class="fas fa-star me-2"></i>Productos Más Vendidos</h5>
                    <?php if (!empty($productos_mas_vendidos)): ?>
                        <?php foreach ($productos_mas_vendidos as $index => $producto): ?>
                            <div class="product-item">
                                <div class="product-rank <?php echo $index < 3 ? 'top-3' : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                                    <div class="product-stats">
                                        <i class="fas fa-shopping-cart"></i> <?php echo $producto['total_vendido']; ?> vendidos
                                        &bull;
                                        <i class="fas fa-dollar-sign"></i> $<?php echo number_format($producto['ingresos_producto'], 0); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-chart-pie fa-2x mb-2 d-block opacity-50"></i>
                            No hay datos de ventas todavía
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos para gráficas
        const labelsDias = <?php echo json_encode($labels_dias); ?>;
        const datosVentas = <?php echo json_encode($datos_ventas_diarias); ?>;
        const datosPedidos = <?php echo json_encode($datos_pedidos_diarios); ?>;
        const horariosPico = <?php echo json_encode(array_values($horarios_pico)); ?>;

        // Colores
        const primaryColor = '#FF6B35';
        const successColor = '#28a745';
        const warningColor = '#ffc107';
        const dangerColor = '#dc3545';
        const infoColor = '#17a2b8';

        // Gráfica de Ventas Diarias
        new Chart(document.getElementById('ventasDiariasChart'), {
            type: 'bar',
            data: {
                labels: labelsDias,
                datasets: [{
                    label: 'Ventas ($)',
                    data: datosVentas,
                    backgroundColor: primaryColor + '80',
                    borderColor: primaryColor,
                    borderWidth: 2,
                    borderRadius: 8,
                    yAxisID: 'y'
                }, {
                    label: 'Pedidos',
                    data: datosPedidos,
                    type: 'line',
                    borderColor: infoColor,
                    backgroundColor: infoColor + '20',
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: infoColor,
                    tension: 0.3,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Ventas ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pedidos'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Gráfica de Horarios Pico
        const horasLabels = Array.from({length: 24}, (_, i) => {
            const hour = i % 12 || 12;
            const ampm = i < 12 ? 'AM' : 'PM';
            return `${hour}${ampm}`;
        });

        new Chart(document.getElementById('horariosPicoChart'), {
            type: 'bar',
            data: {
                labels: horasLabels,
                datasets: [{
                    label: 'Pedidos',
                    data: horariosPico,
                    backgroundColor: horariosPico.map((v, i) => {
                        const max = Math.max(...horariosPico);
                        const intensity = max > 0 ? v / max : 0;
                        return `rgba(255, 107, 53, ${0.3 + intensity * 0.7})`;
                    }),
                    borderColor: primaryColor,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return 'Hora: ' + context[0].label;
                            },
                            label: function(context) {
                                return context.raw + ' pedidos';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de pedidos'
                        }
                    }
                }
            }
        });

        // Gráfica de Pedidos por Estado (Dona)
        new Chart(document.getElementById('pedidosEstadoChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pendientes', 'En proceso', 'En camino', 'Entregados', 'Cancelados'],
                datasets: [{
                    data: [
                        <?php echo $pedidos_por_estado['pendientes']; ?>,
                        <?php echo $pedidos_por_estado['confirmados'] + $pedidos_por_estado['preparando'] + $pedidos_por_estado['listos']; ?>,
                        <?php echo $pedidos_por_estado['en_camino']; ?>,
                        <?php echo $pedidos_por_estado['entregados']; ?>,
                        <?php echo $pedidos_por_estado['cancelados']; ?>
                    ],
                    backgroundColor: [
                        warningColor,
                        infoColor,
                        primaryColor,
                        successColor,
                        dangerColor
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>
