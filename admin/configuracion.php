<?php
/**
 * Configuración - Ajustes generales del negocio
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
    header("Location: ../login.php?redirect=admin/configuracion.php");
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

// Obtener configuración actual
try {
    $stmt = $db->prepare("SELECT acepta_programados, tiempo_minimo_programacion, acepta_efectivo, acepta_tarjeta, notificaciones_email, notificaciones_whatsapp FROM negocios WHERE id_negocio = ?");
    $stmt->execute([$id_negocio]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    // Valores por defecto si no existen
    $config = array_merge([
        'acepta_programados' => 1,
        'tiempo_minimo_programacion' => 60,
        'acepta_efectivo' => 1,
        'acepta_tarjeta' => 1,
        'notificaciones_email' => 1,
        'notificaciones_whatsapp' => 1
    ], $config ?: []);
} catch (Exception $e) {
    $config = [
        'acepta_programados' => 1,
        'tiempo_minimo_programacion' => 60,
        'acepta_efectivo' => 1,
        'acepta_tarjeta' => 1,
        'notificaciones_email' => 1,
        'notificaciones_whatsapp' => 1
    ];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'guardar_config') {
            $acepta_programados = isset($_POST['acepta_programados']) ? 1 : 0;
            $tiempo_minimo_programacion = intval($_POST['tiempo_minimo_programacion'] ?? 60);
            $acepta_efectivo = isset($_POST['acepta_efectivo']) ? 1 : 0;
            $acepta_tarjeta = isset($_POST['acepta_tarjeta']) ? 1 : 0;
            $notificaciones_email = isset($_POST['notificaciones_email']) ? 1 : 0;
            $notificaciones_whatsapp = isset($_POST['notificaciones_whatsapp']) ? 1 : 0;

            try {
                $stmt = $db->prepare("UPDATE negocios SET acepta_programados = ?, tiempo_minimo_programacion = ?, acepta_efectivo = ?, acepta_tarjeta = ?, notificaciones_email = ?, notificaciones_whatsapp = ? WHERE id_negocio = ?");
                $stmt->execute([$acepta_programados, $tiempo_minimo_programacion, $acepta_efectivo, $acepta_tarjeta, $notificaciones_email, $notificaciones_whatsapp, $id_negocio]);

                $mensaje = 'Configuración guardada exitosamente';

                // Actualizar config local
                $config['acepta_programados'] = $acepta_programados;
                $config['tiempo_minimo_programacion'] = $tiempo_minimo_programacion;
                $config['acepta_efectivo'] = $acepta_efectivo;
                $config['acepta_tarjeta'] = $acepta_tarjeta;
                $config['notificaciones_email'] = $notificaciones_email;
                $config['notificaciones_whatsapp'] = $notificaciones_whatsapp;
            } catch (Exception $e) {
                $error = 'Error al guardar la configuración';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - <?php echo htmlspecialchars($negocio_info['nombre']); ?></title>
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
        .config-card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 20px; }
        .config-card h5 { color: var(--secondary-color); margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .form-switch .form-check-input { width: 50px; height: 26px; }
        .form-switch .form-check-input:checked { background-color: var(--primary-color); border-color: var(--primary-color); }
        .config-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .config-item:last-child { border-bottom: none; }
        .config-item-info h6 { margin-bottom: 3px; }
        .config-item-info p { margin: 0; font-size: 0.85rem; color: #6c757d; }
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
            <a href="promociones.php" class="menu-item"><i class="fas fa-percent"></i> Promociones</a>
            <div class="menu-section">NEGOCIO</div>
            <a href="negocio_configuracion.php" class="menu-item"><i class="fas fa-store"></i> Mi Negocio</a>
            <a href="wallet_negocio.php" class="menu-item"><i class="fas fa-wallet"></i> Monedero</a>
            <a href="reportes.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
            <div class="menu-section">CONFIGURACION</div>
            <a href="configuracion.php" class="menu-item active"><i class="fas fa-cog"></i> Configuracion</a>
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
        <div class="mb-4">
            <h2 class="mb-1"><i class="fas fa-cog text-primary me-2"></i>Configuracion</h2>
            <p class="text-muted mb-0">Ajusta las preferencias de tu negocio</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $mensaje; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="guardar_config">

            <!-- Pedidos -->
            <div class="config-card">
                <h5><i class="fas fa-shopping-bag me-2"></i>Pedidos</h5>

                <div class="config-item">
                    <div class="config-item-info">
                        <h6>Pedidos programados</h6>
                        <p>Permite a los clientes programar pedidos para una fecha y hora especifica</p>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="acepta_programados" id="acepta_programados" <?php echo $config['acepta_programados'] ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-item-info">
                        <h6>Tiempo minimo de anticipacion</h6>
                        <p>Minutos minimos que el cliente debe programar con anticipacion</p>
                    </div>
                    <div style="width: 120px;">
                        <div class="input-group">
                            <input type="number" class="form-control" name="tiempo_minimo_programacion" value="<?php echo $config['tiempo_minimo_programacion']; ?>" min="15" max="1440">
                            <span class="input-group-text">min</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Métodos de pago -->
            <div class="config-card">
                <h5><i class="fas fa-credit-card me-2"></i>Metodos de Pago</h5>

                <div class="config-item">
                    <div class="config-item-info">
                        <h6>Pago en efectivo</h6>
                        <p>Permite a los clientes pagar en efectivo al recibir su pedido</p>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="acepta_efectivo" <?php echo $config['acepta_efectivo'] ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-item-info">
                        <h6>Pago con tarjeta</h6>
                        <p>Permite a los clientes pagar con tarjeta de credito/debito</p>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="acepta_tarjeta" <?php echo $config['acepta_tarjeta'] ? 'checked' : ''; ?>>
                    </div>
                </div>
            </div>

            <!-- Notificaciones -->
            <div class="config-card">
                <h5><i class="fas fa-bell me-2"></i>Notificaciones</h5>

                <div class="config-item">
                    <div class="config-item-info">
                        <h6>Notificaciones por email</h6>
                        <p>Recibe notificaciones de nuevos pedidos por correo electronico</p>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="notificaciones_email" <?php echo $config['notificaciones_email'] ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-item-info">
                        <h6>Notificaciones por WhatsApp</h6>
                        <p>Recibe notificaciones de nuevos pedidos por WhatsApp</p>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="notificaciones_whatsapp" <?php echo $config['notificaciones_whatsapp'] ? 'checked' : ''; ?>>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="config-card">
                <h5><i class="fas fa-link me-2"></i>Accesos Rapidos</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="negocio_configuracion.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-store me-2"></i>Editar datos del negocio
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="negocio_configuracion.php#horarios" class="btn btn-outline-primary w-100">
                            <i class="fas fa-clock me-2"></i>Editar horarios
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="analytics_negocio.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-chart-line me-2"></i>Ver analytics
                        </a>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Guardar Configuracion
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
