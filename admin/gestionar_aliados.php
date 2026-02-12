<?php
/**
 * QuickBite - Panel CEO: Gestión de Negocios Aliados
 * CRUD completo para administrar alianzas comerciales
 */

session_start();

// Debug temporal - eliminar después
// error_log("Session en gestionar_aliados: " . print_r($_SESSION, true));

// Verificar autenticación y permisos CEO
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'ceo') {
    // Si no está logueado, redirigir al login CEO
    header("Location: ceo-login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = null;
$error = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'crear_aliado':
                $stmt = $db->prepare("
                    INSERT INTO negocios_aliados
                    (nombre, id_categoria, descripcion, direccion, telefono, email, logo_url, descuento_porcentaje,
                     condiciones, solo_primera_vez, limite_usos_mes, fecha_inicio_alianza, fecha_fin_alianza, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['id_categoria'],
                    $_POST['descripcion'],
                    $_POST['direccion'],
                    $_POST['telefono'],
                    $_POST['email'],
                    $_POST['logo_url'] ?: null,
                    $_POST['descuento_porcentaje'],
                    $_POST['condiciones'],
                    isset($_POST['solo_primera_vez']) ? 1 : 0,
                    $_POST['limite_usos_mes'] ?: null,
                    $_POST['fecha_inicio_alianza'],
                    $_POST['fecha_fin_alianza'] ?: null,
                    $_POST['estado']
                ]);
                $mensaje = "Aliado creado exitosamente";
                break;

            case 'editar_aliado':
                $stmt = $db->prepare("
                    UPDATE negocios_aliados SET
                        nombre = ?, id_categoria = ?, descripcion = ?, direccion = ?,
                        telefono = ?, email = ?, logo_url = ?, descuento_porcentaje = ?,
                        condiciones = ?, solo_primera_vez = ?, limite_usos_mes = ?,
                        fecha_inicio_alianza = ?, fecha_fin_alianza = ?, estado = ?
                    WHERE id_aliado = ?
                ");
                $stmt->execute([
                    $_POST['nombre'],
                    $_POST['id_categoria'],
                    $_POST['descripcion'],
                    $_POST['direccion'],
                    $_POST['telefono'],
                    $_POST['email'],
                    $_POST['logo_url'] ?: null,
                    $_POST['descuento_porcentaje'],
                    $_POST['condiciones'],
                    isset($_POST['solo_primera_vez']) ? 1 : 0,
                    $_POST['limite_usos_mes'] ?: null,
                    $_POST['fecha_inicio_alianza'],
                    $_POST['fecha_fin_alianza'] ?: null,
                    $_POST['estado'],
                    $_POST['id_aliado']
                ]);
                $mensaje = "Aliado actualizado exitosamente";
                break;

            case 'eliminar_aliado':
                $stmt = $db->prepare("UPDATE negocios_aliados SET estado = 'inactivo' WHERE id_aliado = ?");
                $stmt->execute([$_POST['id_aliado']]);
                $mensaje = "Aliado desactivado exitosamente";
                break;

            case 'crear_categoria':
                $stmt = $db->prepare("INSERT INTO categorias_aliados (nombre, icono, descripcion, orden) VALUES (?, ?, ?, ?)");
                $maxOrden = $db->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM categorias_aliados")->fetchColumn();
                $stmt->execute([$_POST['cat_nombre'], $_POST['cat_icono'], $_POST['cat_descripcion'], $maxOrden]);
                $mensaje = "Categoría creada exitosamente";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener estadísticas
$stats = [];
$stats['total_aliados'] = $db->query("SELECT COUNT(*) FROM negocios_aliados WHERE estado = 'activo'")->fetchColumn();
$stats['total_categorias'] = $db->query("SELECT COUNT(*) FROM categorias_aliados WHERE activo = 1")->fetchColumn();
$stats['codigos_generados'] = $db->query("SELECT COUNT(*) FROM codigos_descuento_aliados")->fetchColumn();
$stats['codigos_usados'] = $db->query("SELECT COUNT(*) FROM codigos_descuento_aliados WHERE usado = 1")->fetchColumn();
$stats['ahorro_total'] = $db->query("SELECT COALESCE(SUM(descuento_aplicado), 0) FROM uso_beneficios_aliados WHERE estado = 'verificado'")->fetchColumn();

// Obtener aliados con estadísticas
$aliados = $db->query("
    SELECT na.*, ca.nombre as categoria_nombre, ca.icono as categoria_icono,
           (SELECT COUNT(*) FROM codigos_descuento_aliados WHERE id_aliado = na.id_aliado) as codigos_generados,
           (SELECT COUNT(*) FROM uso_beneficios_aliados WHERE id_aliado = na.id_aliado AND estado = 'verificado') as veces_canjeado
    FROM negocios_aliados na
    JOIN categorias_aliados ca ON na.id_categoria = ca.id_categoria
    ORDER BY na.estado DESC, na.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias_aliados ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

// Obtener aliado para editar si se solicita
$aliadoEditar = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM negocios_aliados WHERE id_aliado = ?");
    $stmt->execute([$_GET['editar']]);
    $aliadoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Aliados - QuickBite Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --qb-primary: #2563EB;
            --qb-secondary: #1e3a5f;
            --qb-gold: #F59E0B;
            --qb-success: #10B981;
        }
        body { background: #f1f5f9; }
        .sidebar {
            background: linear-gradient(180deg, var(--qb-secondary) 0%, #0f172a 100%);
            min-height: 100vh;
            position: fixed;
            width: 250px;
            padding-top: 1rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            margin: 0.25rem 1rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .aliado-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .aliado-card:hover { transform: translateY(-2px); }
        .aliado-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .badge-estado {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-activo { background: #dcfce7; color: #166534; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="text-center mb-4">
            <h4 class="text-white mb-0">Quick<span style="color: var(--qb-gold)">Bite</span></h4>
            <small class="text-white-50">Panel CEO</small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="ceo-panel.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="promotional_banners.php"><i class="bi bi-images me-2"></i> Banners</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="gestionar_aliados.php"><i class="bi bi-shop me-2"></i> Aliados</a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="../index.php"><i class="bi bi-arrow-left me-2"></i> Volver al sitio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Gestión de Aliados</h2>
                <p class="text-muted mb-0">Administra las alianzas comerciales de QuickBite Club</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                    <i class="bi bi-folder-plus me-2"></i>Nueva Categoría
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAliado">
                    <i class="bi bi-plus-lg me-2"></i>Nuevo Aliado
                </button>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: #dbeafe; color: var(--qb-primary);">
                            <i class="bi bi-shop"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0"><?php echo $stats['total_aliados']; ?></div>
                            <small class="text-muted">Aliados Activos</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: #fef3c7; color: var(--qb-gold);">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0"><?php echo $stats['codigos_generados']; ?></div>
                            <small class="text-muted">Códigos Generados</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: #dcfce7; color: var(--qb-success);">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0"><?php echo $stats['codigos_usados']; ?></div>
                            <small class="text-muted">Códigos Canjeados</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon" style="background: #f3e8ff; color: #7c3aed;">
                            <i class="bi bi-piggy-bank"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0">$<?php echo number_format($stats['ahorro_total'], 2); ?></div>
                            <small class="text-muted">Ahorro Generado</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categorías -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-folder me-2"></i>Categorías de Aliados</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($categorias as $cat): ?>
                        <span class="badge bg-light text-dark p-2 d-flex align-items-center gap-2">
                            <span><?php echo $cat['icono']; ?></span>
                            <span><?php echo htmlspecialchars($cat['nombre']); ?></span>
                            <span class="badge bg-primary rounded-pill">
                                <?php
                                $count = 0;
                                foreach ($aliados as $a) {
                                    if ($a['id_categoria'] == $cat['id_categoria'] && $a['estado'] == 'activo') $count++;
                                }
                                echo $count;
                                ?>
                            </span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Lista de Aliados -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Todos los Aliados</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="filtroCategoria" style="width: auto;">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" id="filtroEstado" style="width: auto;">
                        <option value="">Todos los estados</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                        <option value="pendiente">Pendientes</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="listaAliados">
                    <?php foreach ($aliados as $aliado): ?>
                        <div class="col-lg-6 aliado-item"
                             data-categoria="<?php echo $aliado['id_categoria']; ?>"
                             data-estado="<?php echo $aliado['estado']; ?>">
                            <div class="aliado-card">
                                <div class="d-flex gap-3">
                                    <div class="aliado-logo">
                                        <?php if ($aliado['logo_url']): ?>
                                            <img src="<?php echo htmlspecialchars($aliado['logo_url']); ?>"
                                                 alt="<?php echo htmlspecialchars($aliado['nombre']); ?>"
                                                 class="w-100 h-100" style="object-fit: cover; border-radius: 12px;">
                                        <?php else: ?>
                                            <?php echo $aliado['categoria_icono']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($aliado['nombre']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo $aliado['categoria_icono']; ?> <?php echo htmlspecialchars($aliado['categoria_nombre']); ?>
                                                </small>
                                            </div>
                                            <span class="badge-estado badge-<?php echo $aliado['estado']; ?>">
                                                <?php echo ucfirst($aliado['estado']); ?>
                                            </span>
                                        </div>
                                        <div class="d-flex gap-3 mt-2 text-muted small">
                                            <span><i class="bi bi-percent"></i> <?php echo $aliado['descuento_porcentaje']; ?>% desc.</span>
                                            <span><i class="bi bi-ticket"></i> <?php echo $aliado['codigos_generados']; ?> códigos</span>
                                            <span><i class="bi bi-check2"></i> <?php echo $aliado['veces_canjeado']; ?> canjeados</span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-1">
                                        <a href="?editar=<?php echo $aliado['id_aliado']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($aliado['estado'] == 'activo'): ?>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Desactivar este aliado?');">
                                                <input type="hidden" name="action" value="eliminar_aliado">
                                                <input type="hidden" name="id_aliado" value="<?php echo $aliado['id_aliado']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($aliados)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shop display-1 text-muted"></i>
                        <p class="text-muted mt-3">No hay aliados registrados</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAliado">
                            <i class="bi bi-plus-lg me-2"></i>Agregar Primer Aliado
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Aliado -->
    <div class="modal fade" id="modalAliado" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $aliadoEditar ? 'editar_aliado' : 'crear_aliado'; ?>">
                    <?php if ($aliadoEditar): ?>
                        <input type="hidden" name="id_aliado" value="<?php echo $aliadoEditar['id_aliado']; ?>">
                    <?php endif; ?>

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-shop me-2"></i>
                            <?php echo $aliadoEditar ? 'Editar Aliado' : 'Nuevo Aliado'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nombre del Negocio *</label>
                                <input type="text" name="nombre" class="form-control" required
                                       value="<?php echo htmlspecialchars($aliadoEditar['nombre'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Categoría *</label>
                                <select name="id_categoria" class="form-select" required>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id_categoria']; ?>"
                                            <?php echo ($aliadoEditar['id_categoria'] ?? '') == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                            <?php echo $cat['icono'] . ' ' . htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($aliadoEditar['descripcion'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Dirección</label>
                                <input type="text" name="direccion" class="form-control"
                                       value="<?php echo htmlspecialchars($aliadoEditar['direccion'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control"
                                       value="<?php echo htmlspecialchars($aliadoEditar['telefono'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($aliadoEditar['email'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">URL del Logo</label>
                                <input type="url" name="logo_url" class="form-control"
                                       value="<?php echo htmlspecialchars($aliadoEditar['logo_url'] ?? ''); ?>"
                                       placeholder="https://...">
                            </div>

                            <div class="col-12"><hr><h6>Condiciones del Descuento</h6></div>

                            <div class="col-md-3">
                                <label class="form-label">% Descuento *</label>
                                <div class="input-group">
                                    <input type="number" name="descuento_porcentaje" class="form-control" required
                                           min="1" max="100"
                                           value="<?php echo htmlspecialchars($aliadoEditar['descuento_porcentaje'] ?? '15'); ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Límite Usos/Mes</label>
                                <input type="number" name="limite_usos_mes" class="form-control" min="0"
                                       value="<?php echo htmlspecialchars($aliadoEditar['limite_usos_mes'] ?? ''); ?>"
                                       placeholder="Sin límite">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha Inicio *</label>
                                <input type="date" name="fecha_inicio_alianza" class="form-control" required
                                       value="<?php echo $aliadoEditar['fecha_inicio_alianza'] ?? date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha Fin</label>
                                <input type="date" name="fecha_fin_alianza" class="form-control"
                                       value="<?php echo $aliadoEditar['fecha_fin_alianza'] ?? ''; ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Condiciones/Restricciones</label>
                                <input type="text" name="condiciones" class="form-control"
                                       value="<?php echo htmlspecialchars($aliadoEditar['condiciones'] ?? ''); ?>"
                                       placeholder="Ej: Válido en compras mayores a $50">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="solo_primera_vez" class="form-check-input" id="soloPrimeraVez"
                                        <?php echo ($aliadoEditar['solo_primera_vez'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="soloPrimeraVez">
                                        Solo válido la primera vez
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="activo" <?php echo ($aliadoEditar['estado'] ?? '') == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="pendiente" <?php echo ($aliadoEditar['estado'] ?? '') == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="inactivo" <?php echo ($aliadoEditar['estado'] ?? '') == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i><?php echo $aliadoEditar ? 'Guardar Cambios' : 'Crear Aliado'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="crear_categoria">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i>Nueva Categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="cat_nombre" class="form-control" required
                                   placeholder="Ej: Restaurantes">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icono (Emoji) *</label>
                            <input type="text" name="cat_icono" class="form-control" required
                                   placeholder="Ej: 🍽️" maxlength="10">
                            <small class="text-muted">Usa un emoji que represente la categoría</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="cat_descripcion" class="form-control"
                                   placeholder="Descripción breve de la categoría">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Crear Categoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtros
        document.getElementById('filtroCategoria').addEventListener('change', filtrar);
        document.getElementById('filtroEstado').addEventListener('change', filtrar);

        function filtrar() {
            const categoria = document.getElementById('filtroCategoria').value;
            const estado = document.getElementById('filtroEstado').value;

            document.querySelectorAll('.aliado-item').forEach(item => {
                const matchCat = !categoria || item.dataset.categoria === categoria;
                const matchEstado = !estado || item.dataset.estado === estado;
                item.style.display = (matchCat && matchEstado) ? '' : 'none';
            });
        }

        <?php if ($aliadoEditar): ?>
        // Abrir modal de edición automáticamente
        new bootstrap.Modal(document.getElementById('modalAliado')).show();
        <?php endif; ?>
    </script>
</body>
</html>
