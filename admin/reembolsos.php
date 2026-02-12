<?php
/**
 * Panel de Administración de Reembolsos
 * Permite ver, aprobar y gestionar reembolsos de pedidos abandonados
 */

session_start();

// Verificar acceso de administrador
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("location: ../admin/login.php");
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['aprobar_reembolso'])) {
        $id_reembolso = intval($_POST['id_reembolso']);
        
        try {
            $db->beginTransaction();
            
            $update = "UPDATE reembolsos SET estado = 'aprobado', fecha_aprobacion = NOW() WHERE id_reembolso = ?";
            $stmt = $db->prepare($update);
            $stmt->execute([$id_reembolso]);
            
            $db->commit();
            $mensaje = "Reembolso aprobado exitosamente";
            $tipo_mensaje = "success";
            
        } catch (Exception $e) {
            $db->rollBack();
            $mensaje = "Error al aprobar reembolso: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
    
    if (isset($_POST['rechazar_reembolso'])) {
        $id_reembolso = intval($_POST['id_reembolso']);
        $notas = $_POST['notas_rechazo'] ?? '';
        
        try {
            $db->beginTransaction();
            
            $update = "UPDATE reembolsos SET estado = 'rechazado', notas_admin = ? WHERE id_reembolso = ?";
            $stmt = $db->prepare($update);
            $stmt->execute([$notas, $id_reembolso]);
            
            $db->commit();
            $mensaje = "Reembolso rechazado";
            $tipo_mensaje = "warning";
            
        } catch (Exception $e) {
            $db->rollBack();
            $mensaje = "Error al rechazar reembolso: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener estadísticas
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados,
        SUM(monto) as monto_total,
        SUM(CASE WHEN estado = 'pendiente' THEN monto ELSE 0 END) as monto_pendiente
    FROM reembolsos
    WHERE fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Obtener reembolsos recientes
$filtro = $_GET['filtro'] ?? 'todos';
$where_clause = "WHERE 1=1";

if ($filtro == 'pendientes') {
    $where_clause .= " AND r.estado = 'pendiente'";
} elseif ($filtro == 'aprobados') {
    $where_clause .= " AND r.estado = 'aprobado'";
}

$reembolsos_query = "
    SELECT 
        r.*,
        p.id_pedido,
        u.nombre as usuario_nombre,
        u.email as usuario_email,
        u.telefono as usuario_telefono,
        n.nombre as negocio_nombre,
        rep.nombre as repartidor_nombre
    FROM reembolsos r
    INNER JOIN pedidos p ON r.id_pedido = p.id_pedido
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
    LEFT JOIN repartidores rep ON p.id_repartidor_anterior = rep.id_repartidor
    {$where_clause}
    ORDER BY r.fecha_solicitud DESC
    LIMIT 100
";

$reembolsos = $db->query($reembolsos_query)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reembolsos - QuickBite Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .badge-pendiente { background: var(--warning); }
        .badge-aprobado { background: var(--success); }
        .badge-rechazado { background: var(--danger); }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        .btn-action {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../admin/dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Panel Admin
            </a>
            <span class="navbar-text">Gestión de Reembolsos</span>
        </div>
    </nav>

    <div class="container-fluid py-4">
        
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
                    <div class="stat-label">Total (30 días)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value text-warning"><?= $stats['pendientes'] ?? 0 ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value text-success"><?= $stats['aprobados'] ?? 0 ?></div>
                    <div class="stat-label">Aprobados</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value">$<?= number_format($stats['monto_total'] ?? 0, 2) ?></div>
                    <div class="stat-label">Monto Total</div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="?filtro=todos" class="btn btn-<?= $filtro == 'todos' ? 'primary' : 'outline-primary' ?>">
                        <i class="fas fa-list me-1"></i> Todos
                    </a>
                    <a href="?filtro=pendientes" class="btn btn-<?= $filtro == 'pendientes' ? 'warning' : 'outline-warning' ?>">
                        <i class="fas fa-clock me-1"></i> Pendientes (<?= $stats['pendientes'] ?? 0 ?>)
                    </a>
                    <a href="?filtro=aprobados" class="btn btn-<?= $filtro == 'aprobados' ? 'success' : 'outline-success' ?>">
                        <i class="fas fa-check me-1"></i> Aprobados
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Reembolsos -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Reembolsos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Pedido</th>
                                <th>Usuario</th>
                                <th>Negocio</th>
                                <th>Repartidor</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reembolsos as $reembolso): ?>
                            <tr>
                                <td><strong>#<?= $reembolso['id_reembolso'] ?></strong></td>
                                <td>
                                    <small><?= date('d/m/Y H:i', strtotime($reembolso['fecha_solicitud'])) ?></small>
                                    <?php if ($reembolso['procesado_automaticamente']): ?>
                                    <br><span class="badge bg-info">Auto</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../admin/pedido_detalle.php?id=<?= $reembolso['id_pedido'] ?>" target="_blank">
                                        #<?= $reembolso['id_pedido'] ?>
                                    </a>
                                </td>
                                <td>
                                    <?= htmlspecialchars($reembolso['usuario_nombre']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($reembolso['usuario_email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($reembolso['negocio_nombre']) ?></td>
                                <td><?= htmlspecialchars($reembolso['repartidor_nombre'] ?? 'N/A') ?></td>
                                <td><strong>$<?= number_format($reembolso['monto'], 2) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $reembolso['estado'] ?>">
                                        <?= ucfirst($reembolso['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reembolso['estado'] == 'pendiente'): ?>
                                    <button class="btn btn-success btn-action" 
                                            onclick="aprobarReembolso(<?= $reembolso['id_reembolso'] ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-danger btn-action" 
                                            onclick="rechazarReembolso(<?= $reembolso['id_reembolso'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-primary btn-action" 
                                            onclick="verDetalles(<?= $reembolso['id_reembolso'] ?>)"
                                            data-bs-toggle="modal" data-bs-target="#detalleModal"
                                            data-reembolso='<?= json_encode($reembolso) ?>'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($reembolsos)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No hay reembolsos para mostrar</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalles -->
    <div class="modal fade" id="detalleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle del Reembolso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Contenido dinámico -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formularios ocultos para acciones -->
    <form id="formAprobar" method="POST" style="display: none;">
        <input type="hidden" name="id_reembolso" id="aprobar_id">
        <input type="hidden" name="aprobar_reembolso" value="1">
    </form>
    
    <form id="formRechazar" method="POST" style="display: none;">
        <input type="hidden" name="id_reembolso" id="rechazar_id">
        <input type="text" name="notas_rechazo" id="rechazar_notas">
        <input type="hidden" name="rechazar_reembolso" value="1">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function aprobarReembolso(id) {
            if (confirm('¿Confirmar aprobación del reembolso #' + id + '?')) {
                document.getElementById('aprobar_id').value = id;
                document.getElementById('formAprobar').submit();
            }
        }
        
        function rechazarReembolso(id) {
            const motivo = prompt('Motivo del rechazo:');
            if (motivo) {
                document.getElementById('rechazar_id').value = id;
                document.getElementById('rechazar_notas').value = motivo;
                document.getElementById('formRechazar').submit();
            }
        }
        
        function verDetalles(id) {
            const btn = event.target.closest('button');
            const data = JSON.parse(btn.getAttribute('data-reembolso'));
            
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID Reembolso:</strong> #${data.id_reembolso}</p>
                        <p><strong>Pedido:</strong> #${data.id_pedido}</p>
                        <p><strong>Monto:</strong> $${parseFloat(data.monto).toFixed(2)}</p>
                        <p><strong>Estado:</strong> <span class="badge badge-${data.estado}">${data.estado}</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Usuario:</strong> ${data.usuario_nombre}</p>
                        <p><strong>Email:</strong> ${data.usuario_email}</p>
                        <p><strong>Negocio:</strong> ${data.negocio_nombre}</p>
                        <p><strong>Repartidor:</strong> ${data.repartidor_nombre || 'N/A'}</p>
                    </div>
                </div>
                <hr>
                <p><strong>Motivo:</strong></p>
                <p class="bg-light p-3 rounded">${data.motivo}</p>
                ${data.payment_id_original ? '<p><strong>Payment ID:</strong> ' + data.payment_id_original + '</p>' : ''}
                ${data.refund_id ? '<p><strong>Refund ID:</strong> ' + data.refund_id + '</p>' : ''}
                ${data.notas_admin ? '<p><strong>Notas Admin:</strong> ' + data.notas_admin + '</p>' : ''}
            `;
            
            document.getElementById('modalContent').innerHTML = content;
        }
    </script>
</body>
</html>
