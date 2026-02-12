<?php
/**
 * Categorías de Productos - Panel de Negocio
 * Gestiona las categorías del menú del negocio (Hamburguesas, Bebidas, etc.)
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/CategoriaProducto.php';

$database = new Database();
$db = $database->getConnection();

// Verificar autenticación
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/categorias.php");
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

$mensaje = '';
$error = '';

$categoriaProducto = new CategoriaProducto($db);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';

        // Crear categoría
        if ($action === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $orden = intval($_POST['orden'] ?? 0);

            if (empty($nombre)) {
                $error = 'El nombre de la categoría es obligatorio';
            } else {
                $categoriaProducto->id_negocio = $id_negocio;
                $categoriaProducto->nombre = $nombre;
                $categoriaProducto->descripcion = $descripcion;
                $categoriaProducto->orden_visualizacion = $orden;

                if ($categoriaProducto->crear()) {
                    $mensaje = 'Categoría creada exitosamente';
                } else {
                    $error = 'Error al crear la categoría';
                }
            }
        }

        // Editar categoría
        if ($action === 'editar') {
            $id_categoria = intval($_POST['id_categoria'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $orden = intval($_POST['orden'] ?? 0);

            if (empty($nombre)) {
                $error = 'El nombre de la categoría es obligatorio';
            } else {
                $categoriaProducto->id_categoria = $id_categoria;
                $categoriaProducto->id_negocio = $id_negocio;
                $categoriaProducto->nombre = $nombre;
                $categoriaProducto->descripcion = $descripcion;
                $categoriaProducto->orden_visualizacion = $orden;

                if ($categoriaProducto->actualizar()) {
                    $mensaje = 'Categoría actualizada exitosamente';
                } else {
                    $error = 'Error al actualizar la categoría';
                }
            }
        }

        // Eliminar categoría
        if ($action === 'eliminar') {
            $id_categoria = intval($_POST['id_categoria'] ?? 0);

            $categoriaProducto->id_categoria = $id_categoria;
            $categoriaProducto->id_negocio = $id_negocio;

            if ($categoriaProducto->eliminar()) {
                $mensaje = 'Categoría eliminada exitosamente. Los productos de esta categoría quedaron sin categoría asignada.';
            } else {
                $error = 'Error al eliminar la categoría';
            }
        }
    }
}

// Obtener categorías del negocio
$categorias = $categoriaProducto->obtenerPorNegocio($id_negocio);

// Contar productos por categoría
$productos_por_categoria = [];
try {
    $stmt = $db->prepare("SELECT id_categoria, COUNT(*) as total FROM productos WHERE id_negocio = ? GROUP BY id_categoria");
    $stmt->execute([$id_negocio]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productos_por_categoria[$row['id_categoria']] = $row['total'];
    }
} catch (Exception $e) {}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías de Productos - <?php echo htmlspecialchars($negocio_info['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #FF6B35; --secondary-color: #2E294E; --sidebar-width: 260px; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: var(--sidebar-width); background: var(--secondary-color);
            padding: 20px 0; z-index: 1000; overflow-y: auto;
        }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand { color: var(--primary-color); font-size: 1.5rem; font-weight: 700; text-decoration: none; }
        .sidebar-menu { padding: 20px 0; }
        .menu-section { color: rgba(255,255,255,0.5); font-size: 0.75rem; padding: 10px 20px; text-transform: uppercase; }
        .menu-item { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; }
        .menu-item i { width: 20px; margin-right: 10px; }
        .menu-item:hover, .menu-item.active { background: rgba(255,107,53,0.2); color: var(--primary-color); }
        .main-content { margin-left: var(--sidebar-width); padding: 30px; }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .user-details { flex: 1; }
        .user-name { color: white; font-size: 0.9rem; margin: 0; }
        .user-role { color: rgba(255,255,255,0.5); font-size: 0.75rem; margin: 0; }
        .logout-btn { color: rgba(255,255,255,0.5); }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid #eee; font-weight: 600; }
        .btn-primary { background: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background: #e55a2b; border-color: #e55a2b; }
        .categoria-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }
        .categoria-card:hover { transform: translateY(-2px); }
        .categoria-info h5 { margin-bottom: 5px; color: var(--secondary-color); }
        .categoria-info p { margin: 0; color: #6c757d; font-size: 0.9rem; }
        .categoria-badge { background: #e9ecef; color: #495057; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; }
        .categoria-actions { display: flex; gap: 8px; }
        .orden-badge { background: var(--primary-color); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.85rem; margin-right: 15px; }
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
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="pedidos.php" class="menu-item"><i class="fas fa-shopping-bag"></i> Pedidos</a>
            <div class="menu-section">MENU Y OFERTAS</div>
            <a href="menu.php" class="menu-item"><i class="fas fa-clipboard-list"></i> Menu</a>
            <a href="categorias.php" class="menu-item active"><i class="fas fa-tags"></i> Categorias</a>
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
                <h2 class="mb-1"><i class="fas fa-tags text-primary me-2"></i>Categorías de Productos</h2>
                <p class="text-muted mb-0">Organiza tu menú en categorías (Hamburguesas, Bebidas, Postres...)</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                <i class="fas fa-plus me-2"></i>Nueva Categoría
            </button>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (empty($categorias)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-folder-open text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">No tienes categorías</h4>
                    <p class="text-muted">Crea categorías para organizar los productos de tu menú</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                        <i class="fas fa-plus me-2"></i>Crear Primera Categoría
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($categorias as $cat): ?>
                <div class="categoria-card">
                    <div class="d-flex align-items-center">
                        <div class="orden-badge"><?php echo $cat['orden_visualizacion'] ?? 0; ?></div>
                        <div class="categoria-info">
                            <h5><?php echo htmlspecialchars($cat['nombre']); ?></h5>
                            <p><?php echo !empty($cat['descripcion']) ? htmlspecialchars($cat['descripcion']) : 'Sin descripción'; ?></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="categoria-badge">
                            <i class="fas fa-box me-1"></i>
                            <?php echo $productos_por_categoria[$cat['id_categoria']] ?? 0; ?> productos
                        </span>
                        <div class="categoria-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta categoría? Los productos quedarán sin categoría.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="eliminar">
                                <input type="hidden" name="id_categoria" value="<?php echo $cat['id_categoria']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="alert alert-info mt-4">
            <i class="fas fa-lightbulb me-2"></i>
            <strong>Tip:</strong> El número indica el orden de visualización. Las categorías con número menor aparecen primero en tu menú.
        </div>
    </div>

    <!-- Modal Crear/Editar Categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formCategoria">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="formAction" value="crear">
                        <input type="hidden" name="id_categoria" id="formIdCategoria" value="">

                        <div class="mb-3">
                            <label class="form-label">Nombre de la categoría *</label>
                            <input type="text" class="form-control" name="nombre" id="formNombre" required placeholder="Ej: Hamburguesas">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="formDescripcion" rows="2" placeholder="Descripción opcional"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Orden de visualización</label>
                            <input type="number" class="form-control" name="orden" id="formOrden" value="0" min="0">
                            <small class="text-muted">Número menor = aparece primero</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarCategoria(cat) {
            document.getElementById('modalTitle').textContent = 'Editar Categoría';
            document.getElementById('formAction').value = 'editar';
            document.getElementById('formIdCategoria').value = cat.id_categoria;
            document.getElementById('formNombre').value = cat.nombre;
            document.getElementById('formDescripcion').value = cat.descripcion || '';
            document.getElementById('formOrden').value = cat.orden_visualizacion || 0;

            new bootstrap.Modal(document.getElementById('modalCategoria')).show();
        }

        // Reset modal on close
        document.getElementById('modalCategoria').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalTitle').textContent = 'Nueva Categoría';
            document.getElementById('formAction').value = 'crear';
            document.getElementById('formIdCategoria').value = '';
            document.getElementById('formNombre').value = '';
            document.getElementById('formDescripcion').value = '';
            document.getElementById('formOrden').value = '0';
        });
    </script>
</body>
</html>
