<?php
/**
 * Promociones - Gestión de cupones, ofertas e historias de Instagram
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

$database = new Database();
$db = $database->getConnection();

// Verificar autenticación
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/promociones.php");
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

// Procesar formulario de cupón
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'crear_cupon') {
            $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
            $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
            $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
            $minimo_compra = floatval($_POST['minimo_compra'] ?? 0);
            $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
            $fecha_fin = $_POST['fecha_fin'] ?? '';
            $uso_maximo = intval($_POST['uso_maximo'] ?? 0);
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($codigo) || $valor_descuento <= 0) {
                $error = 'El código y el valor del descuento son obligatorios';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO cupones (id_negocio, codigo, tipo_descuento, valor_descuento, minimo_compra, fecha_inicio, fecha_fin, uso_maximo, usos_actuales, descripcion, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 1)");
                    $stmt->execute([$id_negocio, $codigo, $tipo_descuento, $valor_descuento, $minimo_compra, $fecha_inicio, $fecha_fin ?: null, $uso_maximo, $descripcion]);
                    $mensaje = 'Cupón creado exitosamente';
                } catch (Exception $e) {
                    $error = 'Error al crear el cupón: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'toggle_cupon') {
            $id_cupon = intval($_POST['id_cupon'] ?? 0);
            try {
                $stmt = $db->prepare("UPDATE cupones SET activo = NOT activo WHERE id_cupon = ? AND id_negocio = ?");
                $stmt->execute([$id_cupon, $id_negocio]);
                $mensaje = 'Estado del cupón actualizado';
            } catch (Exception $e) {
                $error = 'Error al actualizar el cupón';
            }
        } elseif ($action === 'eliminar_cupon') {
            $id_cupon = intval($_POST['id_cupon'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM cupones WHERE id_cupon = ? AND id_negocio = ?");
                $stmt->execute([$id_cupon, $id_negocio]);
                $mensaje = 'Cupón eliminado';
            } catch (Exception $e) {
                $error = 'Error al eliminar el cupón';
            }
        }
    }
}

// Obtener cupones del negocio
try {
    $stmt = $db->prepare("SELECT * FROM cupones WHERE id_negocio = ? ORDER BY fecha_creacion DESC");
    $stmt->execute([$id_negocio]);
    $cupones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cupones = [];
}

// Obtener productos para historias
try {
    $stmt = $db->prepare("SELECT id_producto, nombre, precio, imagen FROM productos WHERE id_negocio = ? AND disponible = 1 ORDER BY nombre LIMIT 20");
    $stmt->execute([$id_negocio]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productos = [];
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promociones - <?php echo htmlspecialchars($negocio_info['nombre']); ?></title>
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
        .card-cupon { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .cupon-codigo { font-family: monospace; font-size: 1.2rem; font-weight: 700; color: var(--primary-color); background: #fff5f0; padding: 8px 15px; border-radius: 8px; display: inline-block; }
        .badge-activo { background: #28a745; }
        .badge-inactivo { background: #6c757d; }

        /* Tabs */
        .nav-tabs-custom { border-bottom: 2px solid #eee; }
        .nav-tabs-custom .nav-link { border: none; color: #6c757d; padding: 15px 25px; font-weight: 500; }
        .nav-tabs-custom .nav-link.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); background: transparent; }
        .nav-tabs-custom .nav-link:hover { color: var(--primary-color); }

        /* Story Preview */
        .story-preview-container {
            width: 270px;
            height: 480px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            position: relative;
            margin: 0 auto;
        }
        .story-preview {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        .story-template-1 { background: linear-gradient(135deg, #FF6B35, #FF8E53); }
        .story-template-2 { background: linear-gradient(135deg, #667eea, #764ba2); }
        .story-template-3 { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .story-template-4 { background: linear-gradient(135deg, #ee0979, #ff6a00); }
        .story-template-5 { background: linear-gradient(135deg, #2E294E, #4a4270); }

        .story-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .story-logo img { width: 60px; height: 60px; object-fit: contain; border-radius: 50%; }
        .story-title { color: white; font-size: 1.5rem; font-weight: 700; margin-bottom: 10px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .story-discount {
            font-size: 3rem;
            font-weight: 900;
            color: white;
            text-shadow: 0 4px 20px rgba(0,0,0,0.4);
            line-height: 1;
        }
        .story-code {
            background: white;
            color: var(--primary-color);
            padding: 10px 25px;
            border-radius: 30px;
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .story-cta {
            color: white;
            font-size: 0.9rem;
            margin-top: 15px;
            opacity: 0.9;
        }
        .story-swipe {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            opacity: 0.8;
        }
        .story-swipe i { font-size: 1.5rem; animation: bounce 1s infinite; }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .template-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .template-option {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s;
        }
        .template-option:hover { transform: scale(1.1); }
        .template-option.selected { border-color: var(--secondary-color); }

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
            <a href="categorias.php" class="menu-item"><i class="fas fa-tags"></i> Categorias</a>
            <a href="promociones.php" class="menu-item active"><i class="fas fa-percent"></i> Promociones</a>
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
                <h2 class="mb-1"><i class="fas fa-percent text-primary me-2"></i>Promociones</h2>
                <p class="text-muted mb-0">Cupones de descuento e historias de Instagram</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs nav-tabs-custom mb-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#cupones">
                    <i class="fas fa-ticket-alt me-2"></i>Cupones
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#historias">
                    <i class="fab fa-instagram me-2"></i>Historias Instagram
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab Cupones -->
            <div class="tab-pane fade show active" id="cupones">
                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCupon">
                        <i class="fas fa-plus me-2"></i>Nuevo Cupon
                    </button>
                </div>

                <div class="row g-4">
                    <?php if (empty($cupones)): ?>
                        <div class="col-12">
                            <div class="card card-cupon p-5 text-center">
                                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                <h5>No tienes cupones creados</h5>
                                <p class="text-muted">Crea tu primer cupon para atraer mas clientes</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cupones as $cupon): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card card-cupon h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <span class="cupon-codigo"><?php echo htmlspecialchars($cupon['codigo']); ?></span>
                                            <span class="badge <?php echo $cupon['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                <?php echo $cupon['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </div>
                                        <h5 class="mb-2">
                                            <?php if ($cupon['tipo_descuento'] === 'porcentaje'): ?>
                                                <?php echo $cupon['valor_descuento']; ?>% de descuento
                                            <?php else: ?>
                                                $<?php echo number_format($cupon['valor_descuento'], 2); ?> de descuento
                                            <?php endif; ?>
                                        </h5>
                                        <?php if ($cupon['descripcion']): ?>
                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($cupon['descripcion']); ?></p>
                                        <?php endif; ?>
                                        <div class="small text-muted mb-3">
                                            <?php if ($cupon['minimo_compra'] > 0): ?>
                                                <div><i class="fas fa-shopping-cart me-1"></i>Minimo: $<?php echo number_format($cupon['minimo_compra'], 2); ?></div>
                                            <?php endif; ?>
                                            <?php if ($cupon['uso_maximo'] > 0): ?>
                                                <div><i class="fas fa-users me-1"></i>Usos: <?php echo $cupon['usos_actuales']; ?>/<?php echo $cupon['uso_maximo']; ?></div>
                                            <?php endif; ?>
                                            <?php if ($cupon['fecha_fin']): ?>
                                                <div><i class="fas fa-calendar me-1"></i>Vence: <?php echo date('d/m/Y', strtotime($cupon['fecha_fin'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="crearHistoriaCupon('<?php echo htmlspecialchars($cupon['codigo']); ?>', '<?php echo $cupon['tipo_descuento'] === 'porcentaje' ? $cupon['valor_descuento'] . '%' : '$' . number_format($cupon['valor_descuento'], 0); ?>')">
                                                <i class="fab fa-instagram"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="toggle_cupon">
                                                <input type="hidden" name="id_cupon" value="<?php echo $cupon['id_cupon']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar este cupon?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="eliminar_cupon">
                                                <input type="hidden" name="id_cupon" value="<?php echo $cupon['id_cupon']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Historias Instagram -->
            <div class="tab-pane fade" id="historias">
                <div class="row">
                    <div class="col-lg-7">
                        <div class="card card-cupon p-4">
                            <h5 class="mb-4"><i class="fab fa-instagram text-danger me-2"></i>Crear Historia para Instagram</h5>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Tipo de historia</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="tipoHistoria" id="tipoCupon" value="cupon" checked>
                                    <label class="btn btn-outline-primary" for="tipoCupon"><i class="fas fa-ticket-alt me-2"></i>Cupon</label>

                                    <input type="radio" class="btn-check" name="tipoHistoria" id="tipoPromo" value="promo">
                                    <label class="btn btn-outline-primary" for="tipoPromo"><i class="fas fa-fire me-2"></i>Promocion</label>

                                    <input type="radio" class="btn-check" name="tipoHistoria" id="tipoProducto" value="producto">
                                    <label class="btn btn-outline-primary" for="tipoProducto"><i class="fas fa-utensils me-2"></i>Producto</label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Plantilla de color</label>
                                <div class="template-selector">
                                    <div class="template-option story-template-1 selected" data-template="1"></div>
                                    <div class="template-option story-template-2" data-template="2"></div>
                                    <div class="template-option story-template-3" data-template="3"></div>
                                    <div class="template-option story-template-4" data-template="4"></div>
                                    <div class="template-option story-template-5" data-template="5"></div>
                                </div>
                            </div>

                            <!-- Campos para Cupón -->
                            <div id="camposCupon">
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar cupon existente</label>
                                    <select class="form-select" id="selectCupon" onchange="cargarCupon()">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($cupones as $cupon): ?>
                                            <option value="<?php echo htmlspecialchars($cupon['codigo']); ?>"
                                                    data-descuento="<?php echo $cupon['tipo_descuento'] === 'porcentaje' ? $cupon['valor_descuento'] . '%' : '$' . number_format($cupon['valor_descuento'], 0); ?>">
                                                <?php echo htmlspecialchars($cupon['codigo']); ?> -
                                                <?php echo $cupon['tipo_descuento'] === 'porcentaje' ? $cupon['valor_descuento'] . '%' : '$' . number_format($cupon['valor_descuento'], 0); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Campos para Promoción -->
                            <div id="camposPromo" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Titulo de la promocion</label>
                                    <input type="text" class="form-control" id="promoTitulo" placeholder="Ej: Oferta Especial" oninput="actualizarPreview()">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Descuento o precio</label>
                                    <input type="text" class="form-control" id="promoDescuento" placeholder="Ej: 2x1, 50%, $99" oninput="actualizarPreview()">
                                </div>
                            </div>

                            <!-- Campos para Producto -->
                            <div id="camposProducto" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar producto</label>
                                    <select class="form-select" id="selectProducto" onchange="cargarProducto()">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($productos as $prod): ?>
                                            <option value="<?php echo $prod['id_producto']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                                    data-precio="$<?php echo number_format($prod['precio'], 0); ?>">
                                                <?php echo htmlspecialchars($prod['nombre']); ?> - $<?php echo number_format($prod['precio'], 0); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Campos comunes -->
                            <div class="mb-3">
                                <label class="form-label">Texto principal</label>
                                <input type="text" class="form-control" id="storyTitulo" value="<?php echo htmlspecialchars($negocio_info['nombre']); ?>" oninput="actualizarPreview()">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Descuento/Precio destacado</label>
                                <input type="text" class="form-control" id="storyDescuento" placeholder="Ej: 20% OFF" oninput="actualizarPreview()">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Codigo (opcional)</label>
                                <input type="text" class="form-control" id="storyCodigo" placeholder="Ej: PROMO20" style="text-transform: uppercase;" oninput="actualizarPreview()">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Llamado a la accion</label>
                                <input type="text" class="form-control" id="storyCTA" value="Pide ahora en QuickBite" oninput="actualizarPreview()">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary btn-lg" onclick="descargarHistoria()">
                                    <i class="fas fa-download me-2"></i>Descargar Historia
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="copiarParaInstagram()">
                                    <i class="fas fa-copy me-2"></i>Copiar texto para Instagram
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card card-cupon p-4">
                            <h5 class="mb-4 text-center"><i class="fas fa-eye me-2"></i>Vista previa</h5>

                            <div class="story-preview-container">
                                <div class="story-preview story-template-1" id="storyPreview">
                                    <div class="story-logo">
                                        <?php if (!empty($negocio_info['logo'])): ?>
                                            <img src="../<?php echo htmlspecialchars($negocio_info['logo']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <i class="fas fa-utensils fa-2x" style="color: var(--primary-color);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="story-title" id="previewTitulo"><?php echo htmlspecialchars($negocio_info['nombre']); ?></div>
                                    <div class="story-discount" id="previewDescuento">20% OFF</div>
                                    <div class="story-code" id="previewCodigo" style="display: none;">CODIGO</div>
                                    <div class="story-cta" id="previewCTA">Pide ahora en QuickBite</div>
                                    <div class="story-swipe">
                                        <i class="fas fa-chevron-up"></i>
                                        <span>Desliza para pedir</span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Dimension: 1080 x 1920px (formato historia)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear Cupón -->
    <div class="modal fade" id="modalCupon" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="crear_cupon">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-ticket-alt me-2"></i>Crear Cupon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Codigo del cupon *</label>
                            <input type="text" name="codigo" class="form-control" placeholder="Ej: DESCUENTO20" required style="text-transform: uppercase;">
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Tipo de descuento</label>
                                <select name="tipo_descuento" class="form-select">
                                    <option value="porcentaje">Porcentaje (%)</option>
                                    <option value="fijo">Monto fijo ($)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Valor *</label>
                                <input type="number" name="valor_descuento" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Compra minima ($)</label>
                            <input type="number" name="minimo_compra" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Fecha inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Fecha fin</label>
                                <input type="date" name="fecha_fin" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Limite de usos (0 = ilimitado)</label>
                            <input type="number" name="uso_maximo" class="form-control" min="0" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripcion</label>
                            <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripcion opcional del cupon"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Crear Cupon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Canvas oculto para generar imagen -->
    <canvas id="storyCanvas" width="1080" height="1920" style="display: none;"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTemplate = 1;
        const negocioNombre = "<?php echo addslashes($negocio_info['nombre']); ?>";
        const negocioLogo = "<?php echo !empty($negocio_info['logo']) ? '../' . addslashes($negocio_info['logo']) : ''; ?>";

        // Cambiar tipo de historia
        document.querySelectorAll('input[name="tipoHistoria"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('camposCupon').style.display = this.value === 'cupon' ? 'block' : 'none';
                document.getElementById('camposPromo').style.display = this.value === 'promo' ? 'block' : 'none';
                document.getElementById('camposProducto').style.display = this.value === 'producto' ? 'block' : 'none';
            });
        });

        // Cambiar plantilla
        document.querySelectorAll('.template-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.template-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                currentTemplate = this.dataset.template;

                const preview = document.getElementById('storyPreview');
                preview.className = 'story-preview story-template-' + currentTemplate;
            });
        });

        function cargarCupon() {
            const select = document.getElementById('selectCupon');
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('storyCodigo').value = option.value;
                document.getElementById('storyDescuento').value = option.dataset.descuento + ' OFF';
                actualizarPreview();
            }
        }

        function cargarProducto() {
            const select = document.getElementById('selectProducto');
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('storyTitulo').value = option.dataset.nombre;
                document.getElementById('storyDescuento').value = option.dataset.precio;
                document.getElementById('storyCodigo').value = '';
                actualizarPreview();
            }
        }

        function crearHistoriaCupon(codigo, descuento) {
            // Cambiar a tab de historias
            const tab = new bootstrap.Tab(document.querySelector('a[href="#historias"]'));
            tab.show();

            // Llenar datos
            document.getElementById('storyCodigo').value = codigo;
            document.getElementById('storyDescuento').value = descuento + ' OFF';
            actualizarPreview();
        }

        function actualizarPreview() {
            const titulo = document.getElementById('storyTitulo').value || negocioNombre;
            const descuento = document.getElementById('storyDescuento').value || '20% OFF';
            const codigo = document.getElementById('storyCodigo').value;
            const cta = document.getElementById('storyCTA').value || 'Pide ahora';

            document.getElementById('previewTitulo').textContent = titulo;
            document.getElementById('previewDescuento').textContent = descuento;
            document.getElementById('previewCTA').textContent = cta;

            const codigoEl = document.getElementById('previewCodigo');
            if (codigo) {
                codigoEl.textContent = codigo;
                codigoEl.style.display = 'block';
            } else {
                codigoEl.style.display = 'none';
            }
        }

        function descargarHistoria() {
            const canvas = document.getElementById('storyCanvas');
            const ctx = canvas.getContext('2d');

            // Gradientes según plantilla
            const gradientes = {
                1: ['#FF6B35', '#FF8E53'],
                2: ['#667eea', '#764ba2'],
                3: ['#11998e', '#38ef7d'],
                4: ['#ee0979', '#ff6a00'],
                5: ['#2E294E', '#4a4270']
            };

            const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            grad.addColorStop(0, gradientes[currentTemplate][0]);
            grad.addColorStop(1, gradientes[currentTemplate][1]);
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Logo (círculo blanco)
            ctx.beginPath();
            ctx.arc(540, 400, 120, 0, Math.PI * 2);
            ctx.fillStyle = 'white';
            ctx.fill();
            ctx.closePath();

            // Icono en el logo
            ctx.font = '80px FontAwesome';
            ctx.fillStyle = '#FF6B35';
            ctx.textAlign = 'center';
            ctx.fillText('\uf2e7', 540, 430);

            // Título
            ctx.font = 'bold 70px Segoe UI';
            ctx.fillStyle = 'white';
            ctx.textAlign = 'center';
            ctx.shadowColor = 'rgba(0,0,0,0.3)';
            ctx.shadowBlur = 20;
            ctx.fillText(document.getElementById('storyTitulo').value || negocioNombre, 540, 650);

            // Descuento grande
            ctx.font = 'bold 180px Segoe UI';
            ctx.shadowBlur = 40;
            ctx.fillText(document.getElementById('storyDescuento').value || '20% OFF', 540, 950);

            // Código
            const codigo = document.getElementById('storyCodigo').value;
            if (codigo) {
                ctx.shadowBlur = 0;
                // Fondo del código
                ctx.fillStyle = 'white';
                roundRect(ctx, 340, 1050, 400, 80, 40);
                ctx.fill();
                // Texto del código
                ctx.font = 'bold 45px monospace';
                ctx.fillStyle = '#FF6B35';
                ctx.fillText(codigo.toUpperCase(), 540, 1105);
            }

            // CTA
            ctx.font = '40px Segoe UI';
            ctx.fillStyle = 'rgba(255,255,255,0.9)';
            ctx.shadowBlur = 0;
            ctx.fillText(document.getElementById('storyCTA').value || 'Pide ahora en QuickBite', 540, 1250);

            // Swipe up
            ctx.font = '35px Segoe UI';
            ctx.fillStyle = 'rgba(255,255,255,0.8)';
            ctx.fillText('↑', 540, 1750);
            ctx.font = '30px Segoe UI';
            ctx.fillText('Desliza para pedir', 540, 1800);

            // Descargar
            const link = document.createElement('a');
            link.download = 'historia_instagram_' + Date.now() + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        function roundRect(ctx, x, y, width, height, radius) {
            ctx.beginPath();
            ctx.moveTo(x + radius, y);
            ctx.lineTo(x + width - radius, y);
            ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
            ctx.lineTo(x + width, y + height - radius);
            ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            ctx.lineTo(x + radius, y + height);
            ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
            ctx.lineTo(x, y + radius);
            ctx.quadraticCurveTo(x, y, x + radius, y);
            ctx.closePath();
        }

        function copiarParaInstagram() {
            const titulo = document.getElementById('storyTitulo').value || negocioNombre;
            const descuento = document.getElementById('storyDescuento').value || '';
            const codigo = document.getElementById('storyCodigo').value;
            const cta = document.getElementById('storyCTA').value || 'Pide ahora en QuickBite';

            let texto = `🔥 ${titulo}\n\n`;
            if (descuento) texto += `💥 ${descuento}\n\n`;
            if (codigo) texto += `🎟️ Usa el codigo: ${codigo.toUpperCase()}\n\n`;
            texto += `📱 ${cta}\n\n`;
            texto += `#QuickBite #Delivery #Promocion #Descuento`;

            navigator.clipboard.writeText(texto).then(() => {
                alert('Texto copiado al portapapeles!');
            });
        }

        // Inicializar preview
        actualizarPreview();
    </script>
</body>
</html>
