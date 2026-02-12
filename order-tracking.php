<?php
session_start();
require_once 'config/database.php';
require_once 'models/Pedido.php';
require_once 'models/Negocio.php';
require_once 'models/Direccion.php';
require_once 'models/Usuario.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: index.php");
    exit;
}

$id_pedido = intval($_GET['id']);

$database = new Database();
$db = $database->getConnection();

$pedido = new Pedido($db);
$pedido->id_pedido = $id_pedido;
$datos_pedido = $pedido->obtenerPorId();

if (!$datos_pedido || $datos_pedido['id_usuario'] != $_SESSION['id_usuario']) {
    header("location: index.php");
    exit;
}

$negocio = new Negocio($db);
$negocio->id_negocio = $datos_pedido['id_negocio'];
$negocio->obtenerPorId();

$direccion = new Direccion($db);
$direccion->id_direccion = $datos_pedido['id_direccion'];
$direccion->obtenerPorId();

// Detectar si es pickup
$es_pickup = (isset($datos_pedido['tipo_pedido']) && $datos_pedido['tipo_pedido'] === 'pickup');

// Normalizar estado para pickup
$estado_actual = $datos_pedido['id_estado'] ?? 1;
if ($es_pickup && $estado_actual > 4) {
    $estado_actual = 4; // En pickup, estados 5 y 6 se convierten en 4
}

// Obtener items del pedido
$items_pedido = $pedido->obtenerItems();

// Obtener datos del repartidor si aplica (delivery)
$repartidor_info = null;
$repartidor_resenas = [];
if (!$es_pickup && !empty($datos_pedido['id_repartidor'])) {
    $stmt = $db->prepare("
        SELECT r.*, u.nombre, u.apellido, u.telefono,
               COALESCE(r.total_resenas, 0) as total_resenas,
               COALESCE(r.rating_promedio_resenas, r.calificacion_promedio, 5.0) as rating,
               n.nombre as nivel_nombre, n.emoji as nivel_emoji
        FROM repartidores r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        LEFT JOIN niveles_repartidor n ON r.id_nivel = n.id_nivel
        WHERE r.id_repartidor = ?
    ");
    $stmt->execute([$datos_pedido['id_repartidor']]);
    $repartidor_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener ultimas resenas del repartidor
    if ($repartidor_info) {
        $stmt = $db->prepare("
            SELECT v.calificacion_repartidor, v.comentario_repartidor, v.tiempo_entrega_percibido,
                   DATE_FORMAT(v.fecha_creacion, '%d/%m/%Y') as fecha
            FROM valoraciones v
            WHERE v.id_repartidor = ? AND v.calificacion_repartidor IS NOT NULL AND v.visible = 1
            ORDER BY v.fecha_creacion DESC
            LIMIT 3
        ");
        $stmt->execute([$datos_pedido['id_repartidor']]);
        $repartidor_resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Verificar si hay foto de entrega
$foto_entrega = null;
if ($estado_actual == 6) {
    $stmt = $db->prepare("SELECT foto_url, fecha_captura FROM fotos_entrega WHERE id_pedido = ? ORDER BY fecha_captura DESC LIMIT 1");
    $stmt->execute([$id_pedido]);
    $foto_entrega = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Verificar si ya dejo resena
$ya_tiene_resena = false;
$stmt = $db->prepare("SELECT id_valoracion FROM valoraciones WHERE id_pedido = ?");
$stmt->execute([$id_pedido]);
$ya_tiene_resena = $stmt->fetch() !== false;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Pedido - QuickBite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .status-card { background: white; border-radius: 15px; padding: 2rem; margin: 2rem 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .status-icon { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #0165FF, #4285F4); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .timeline { display: flex; justify-content: space-between; margin: 2rem 0; }
        .step { text-align: center; flex: 1; }
        .step-icon { width: 50px; height: 50px; border-radius: 50%; background: #e9ecef; color: #6c757d; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; }
        .step-icon.active { background: #0165FF; color: white; }
        .step-icon.completed { background: #28a745; color: white; }
        .pickup-badge { background: linear-gradient(135deg, #ffc107, #ffb300); color: #000; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 500; }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <div class="container">
        <div class="status-card">
            <div class="d-flex align-items-center mb-4">
                <div class="status-icon me-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h2 class="mb-1">Estado del Pedido #<?php echo str_pad($id_pedido, 6, '0', STR_PAD_LEFT); ?></h2>
                    <p class="text-muted mb-0">Tu pedido está siendo procesado</p>
                </div>
                <?php if ($es_pickup): ?>
                <div class="ms-auto">
                    <span class="pickup-badge">
                        <i class="fas fa-walking me-2"></i>Recoger en tienda
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="timeline">
                <div class="step">
                    <div class="step-icon <?php echo $estado_actual >= 1 ? 'completed' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <small>Recibido</small>
                </div>
                <div class="step">
                    <div class="step-icon <?php echo $estado_actual >= 2 ? 'completed' : ($estado_actual == 2 ? 'active' : ''); ?>">
                        <i class="fas fa-thumbs-up"></i>
                    </div>
                    <small>Confirmado</small>
                </div>
                <div class="step">
                    <div class="step-icon <?php echo $estado_actual >= 3 ? 'completed' : ($estado_actual == 3 ? 'active' : ''); ?>">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <small>Preparando</small>
                </div>
                <div class="step">
                    <div class="step-icon <?php echo $estado_actual >= 4 ? 'completed' : ($estado_actual == 4 ? 'active' : ''); ?>">
                        <i class="fas fa-<?php echo $es_pickup ? 'store' : 'box-open'; ?>"></i>
                    </div>
                    <small><?php echo $es_pickup ? 'Listo para recoger' : 'Listo'; ?></small>
                </div>
                <?php if (!$es_pickup): ?>
                <div class="step">
                    <div class="step-icon <?php echo $estado_actual >= 5 ? 'completed' : ($estado_actual == 5 ? 'active' : ''); ?>">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <small>En camino</small>
                </div>
                <div class="step">
                    <div class="step-icon <?php echo $estado_actual == 6 ? 'completed' : ''; ?>">
                        <i class="fas fa-home"></i>
                    </div>
                    <small>Entregado</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información del pedido -->
        <div class="row">
            <div class="col-md-6">
                <div class="status-card">
                    <h5><i class="fas fa-store me-2"></i><?php echo $es_pickup ? 'Restaurante' : 'Información'; ?></h5>
                    <p><strong><?php echo $negocio->nombre ?? 'Restaurante'; ?></strong></p>
                    <?php if ($es_pickup): ?>
                        <p><?php echo ($negocio->calle ?? '') . ' ' . ($negocio->numero ?? ''); ?></p>
                        <p>Tel: <?php echo $negocio->telefono ?? ''; ?></p>
                    <?php else: ?>
                        <p><strong>Dirección de entrega:</strong></p>
                        <p><?php echo ($direccion->calle ?? '') . ' ' . ($direccion->numero ?? ''); ?></p>
                        <p><?php echo $direccion->colonia ?? ''; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="status-card">
                    <h5><i class="fas fa-receipt me-2"></i>Resumen del pedido</h5>
                    <?php if ($items_pedido): ?>
                        <?php foreach ($items_pedido as $item): ?>
                        <div class="d-flex justify-content-between">
                            <span><?php echo $item['cantidad']; ?>x <?php echo $item['nombre']; ?></span>
                            <span>$<?php echo number_format($item['precio_total'], 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total</span>
                        <span>$<?php echo number_format($datos_pedido['monto_total'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Repartidor (solo delivery) -->
        <?php if ($repartidor_info && !$es_pickup): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="status-card">
                    <h5><i class="fas fa-motorcycle me-2"></i>Tu repartidor</h5>
                    <div class="d-flex align-items-center">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #00D1B2, #00A78E); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold; margin-right: 1rem;">
                            <?php echo strtoupper(substr($repartidor_info['nombre'] ?? 'R', 0, 1)); ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">
                                <?php echo htmlspecialchars(($repartidor_info['nombre'] ?? '') . ' ' . ($repartidor_info['apellido'] ?? '')); ?>
                                <?php if (!empty($repartidor_info['nivel_emoji'])): ?>
                                    <span title="<?php echo htmlspecialchars($repartidor_info['nivel_nombre'] ?? ''); ?>"><?php echo $repartidor_info['nivel_emoji']; ?></span>
                                <?php endif; ?>
                            </h6>
                            <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.9rem; color: #6c757d;">
                                <span>
                                    <i class="fas fa-star" style="color: #FFD700;"></i>
                                    <?php echo number_format($repartidor_info['rating'] ?? 5, 1); ?>
                                    <small>(<?php echo $repartidor_info['total_resenas'] ?? 0; ?> reseñas)</small>
                                </span>
                                <span>
                                    <i class="fas fa-truck"></i>
                                    <?php echo $repartidor_info['total_entregas'] ?? 0; ?> entregas
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($repartidor_info['telefono']) && $estado_actual >= 5): ?>
                        <a href="tel:<?php echo $repartidor_info['telefono']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-phone"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Reseñas del repartidor -->
                    <?php if (!empty($repartidor_resenas)): ?>
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted fw-bold">Reseñas recientes:</small>
                        <?php foreach ($repartidor_resenas as $resena): ?>
                        <div class="mt-2 p-2" style="background: #f8f9fa; border-radius: 8px; font-size: 0.85rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?php echo $i <= $resena['calificacion_repartidor'] ? '#FFD700' : '#e9ecef'; ?>; font-size: 0.75rem;"></i>
                                    <?php endfor; ?>
                                    <?php if ($resena['tiempo_entrega_percibido']): ?>
                                        <span class="badge bg-<?php echo $resena['tiempo_entrega_percibido'] == 'muy_rapido' || $resena['tiempo_entrega_percibido'] == 'rapido' ? 'success' : ($resena['tiempo_entrega_percibido'] == 'normal' ? 'secondary' : 'warning'); ?>" style="font-size: 0.65rem;">
                                            <?php echo ucfirst(str_replace('_', ' ', $resena['tiempo_entrega_percibido'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo $resena['fecha']; ?></small>
                            </div>
                            <?php if (!empty($resena['comentario_repartidor'])): ?>
                                <p class="mb-0 mt-1 text-muted">"<?php echo htmlspecialchars($resena['comentario_repartidor']); ?>"</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Foto de Entrega (si ya fue entregado) -->
        <?php if ($foto_entrega): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="status-card">
                    <h5><i class="fas fa-camera me-2"></i>Foto de entrega</h5>
                    <div class="text-center">
                        <img src="<?php echo htmlspecialchars($foto_entrega['foto_url']); ?>" alt="Foto de entrega"
                             style="max-width: 100%; max-height: 300px; border-radius: 12px; cursor: pointer;"
                             onclick="window.open(this.src, '_blank')">
                        <p class="text-muted small mt-2">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($foto_entrega['fecha_captura'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Boton para dejar reseña (solo si entregado y no ha dejado reseña) -->
        <?php if ($estado_actual == 6 && !$ya_tiene_resena): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="status-card text-center" style="background: linear-gradient(135deg, #fff9e6, #fff3cd);">
                    <i class="fas fa-star" style="font-size: 2rem; color: #FFD700;"></i>
                    <h5 class="mt-2">¿Qué te pareció tu pedido?</h5>
                    <p class="text-muted">Tu opinión nos ayuda a mejorar</p>
                    <button class="btn btn-warning" onclick="abrirModalResena({
                        id_pedido: <?php echo $id_pedido; ?>,
                        nombre_negocio: '<?php echo addslashes($negocio->nombre ?? ''); ?>',
                        id_repartidor: <?php echo $datos_pedido['id_repartidor'] ?? 'null'; ?>,
                        nombre_repartidor: '<?php echo addslashes(($repartidor_info['nombre'] ?? '') . ' ' . ($repartidor_info['apellido'] ?? '')); ?>',
                        tipo_pedido: '<?php echo $datos_pedido['tipo_pedido'] ?? 'delivery'; ?>'
                    })">
                        <i class="fas fa-edit me-2"></i>Dejar reseña
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4 mb-4">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Volver al inicio
            </a>
        </div>
    </div>

    <?php include 'includes/modal_resena.php'; ?>

    <script src="/js/push-notifications.js"></script>
    <script src="/js/order-tracking.js"></script>
    <script>
        // Configuración global
        const config = {
            orderId: <?php echo $id_pedido; ?>,
            userId: <?php echo $_SESSION['id_usuario']; ?>,
            currentStatus: <?php echo $estado_actual; ?>,
            isPickup: <?php echo $es_pickup ? 'true' : 'false'; ?>
        };

        // Función para mostrar notificaciones
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 1000; min-width: 300px;';
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 5000);
        }

        // Función para actualizar estado
        function updateOrderStatus(newStatus, statusText) {
            if (newStatus !== config.currentStatus && window.sendStatusNotification) {
                window.sendStatusNotification(newStatus, '<?php echo addslashes($negocio->nombre ?? "el restaurante"); ?>');
            }
            
            // Actualizar timeline
            const steps = document.querySelectorAll('.step-icon');
            const maxSteps = config.isPickup ? 4 : 6;
            
            steps.forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('active', 'completed');
                
                if (stepNum < newStatus) {
                    step.classList.add('completed');
                } else if (stepNum === newStatus) {
                    step.classList.add('active');
                }
            });
            
            config.currentStatus = newStatus;
        }

        // Inicializar WebSocket
        document.addEventListener('DOMContentLoaded', function() {
            if (window.OrderTrackingClient) {
                const trackingClient = new OrderTrackingClient(config.userId, config.orderId);
                
                trackingClient.on('Connect', () => {
                    console.log('Conectado al sistema de seguimiento');
                });
                
                trackingClient.on('OrderStatusUpdate', (data) => {
                    updateOrderStatus(data.status, data.statusText);
                });
                
                trackingClient.connect();
                window.trackingClient = trackingClient;
            }
        });

        // Banner de notificaciones
        setTimeout(() => {
            if (Notification.permission === 'default' && window.pushManager) {
                const banner = document.createElement('div');
                banner.className = 'alert alert-info position-fixed';
                banner.style.cssText = 'top: 20px; right: 20px; z-index: 1000; max-width: 350px;';
                banner.innerHTML = `
                    <h6><i class="fas fa-bell me-2"></i>Recibe notificaciones</h6>
                    <p class="mb-2 small">Te avisaremos cuando cambie el estado de tu pedido</p>
                    <button class="btn btn-sm btn-primary me-2" onclick="enableNotifications()">Activar</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="this.parentElement.remove()">Después</button>
                `;
                document.body.appendChild(banner);
            }
        }, 3000);

        window.enableNotifications = async function() {
            if (window.pushManager) {
                const success = await window.pushManager.requestPermission();
                if (success) {
                    showNotification('Notificaciones activadas correctamente');
                    document.querySelector('[onclick="enableNotifications()"]').parentElement.remove();
                }
            }
        };
    </script>
</body>
</html>