<?php
// ===== admin/promotional_banners.php =====
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once '../config/database.php';
require_once '../config/csrf.php';
require_once '../models/PromotionalBanner.php';
require_once '../models/Negocio.php';

// Verificar autenticación de admin
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'ceo') {
    header("Location: ceo-login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

/**
 * Procesa y optimiza imágenes de banners
 */
function procesarImagenBanner($archivo, $ancho_max = 1200, $alto_max = 600) {
    $upload_dir = '../assets/img/banners/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $extensiones_validas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($file_extension, $extensiones_validas)) {
        return ['error' => 'Formato de imagen no válido'];
    }

    // Obtener información de la imagen
    $imagen_info = getimagesize($archivo['tmp_name']);
    if (!$imagen_info) {
        return ['error' => 'No se pudo leer la imagen'];
    }

    $ancho_original = $imagen_info[0];
    $alto_original = $imagen_info[1];
    $tipo_imagen = $imagen_info[2];

    // Cargar imagen según tipo
    switch ($tipo_imagen) {
        case IMAGETYPE_JPEG:
            $imagen_original = imagecreatefromjpeg($archivo['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $imagen_original = imagecreatefrompng($archivo['tmp_name']);
            break;
        case IMAGETYPE_GIF:
            $imagen_original = imagecreatefromgif($archivo['tmp_name']);
            break;
        case IMAGETYPE_WEBP:
            $imagen_original = imagecreatefromwebp($archivo['tmp_name']);
            break;
        default:
            return ['error' => 'Tipo de imagen no soportado'];
    }

    // Calcular nuevas dimensiones manteniendo aspecto
    $ratio = min($ancho_max / $ancho_original, $alto_max / $alto_original);

    // Solo redimensionar si es más grande que el máximo
    if ($ratio < 1) {
        $nuevo_ancho = round($ancho_original * $ratio);
        $nuevo_alto = round($alto_original * $ratio);
    } else {
        $nuevo_ancho = $ancho_original;
        $nuevo_alto = $alto_original;
    }

    // Crear imagen redimensionada
    $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);

    // Preservar transparencia para PNG
    if ($tipo_imagen == IMAGETYPE_PNG) {
        imagealphablending($imagen_nueva, false);
        imagesavealpha($imagen_nueva, true);
        $transparente = imagecolorallocatealpha($imagen_nueva, 255, 255, 255, 127);
        imagefilledrectangle($imagen_nueva, 0, 0, $nuevo_ancho, $nuevo_alto, $transparente);
    }

    // Redimensionar
    imagecopyresampled(
        $imagen_nueva, $imagen_original,
        0, 0, 0, 0,
        $nuevo_ancho, $nuevo_alto,
        $ancho_original, $alto_original
    );

    // Generar nombre único
    $new_filename = 'banner_' . uniqid() . '_' . time() . '.webp';
    $upload_path = $upload_dir . $new_filename;

    // Guardar como WebP (mejor compresión)
    if (imagewebp($imagen_nueva, $upload_path, 85)) {
        imagedestroy($imagen_original);
        imagedestroy($imagen_nueva);

        return [
            'success' => true,
            'path' => 'assets/img/banners/' . $new_filename,
            'width' => $nuevo_ancho,
            'height' => $nuevo_alto
        ];
    }

    imagedestroy($imagen_original);
    imagedestroy($imagen_nueva);
    return ['error' => 'Error al guardar la imagen'];
}

/**
 * Envía notificaciones push masivas a usuarios
 */
function enviarNotificacionMasiva($db, $titulo, $mensaje, $enlace = null, $segmento = 'todos') {
    $resultados = ['enviados' => 0, 'fallidos' => 0, 'total' => 0];

    // Obtener suscripciones según segmento
    $query = "SELECT ps.*, u.nombre, u.email FROM push_subscriptions ps
              LEFT JOIN usuarios u ON ps.user_id = u.id_usuario WHERE 1=1";

    if ($segmento === 'activos') {
        $query .= " AND u.activo = 1";
    } elseif ($segmento === 'premium') {
        $query .= " AND EXISTS (SELECT 1 FROM membresias m WHERE m.id_usuario = u.id_usuario AND m.estado = 'activo')";
    }

    $stmt = $db->prepare($query);
    $stmt->execute();
    $suscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultados['total'] = count($suscripciones);

    if (empty($suscripciones)) {
        return $resultados;
    }

    // Configuración VAPID
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:contacto@quickbite.com.mx',
            'publicKey' => 'BOeqLR7r_-fyCTb5S8G3I-AmcRz58mueyf9ncPQ2Pm12dO_7bu1-2YBnU3iLrRS7fhw1N1bin7lNAmQSxpDx6Iw',
            'privateKey' => 'UQfNfE3QmISy-gyPUrgYGZcpb3-iaqbBe2AShA01KeY'
        ]
    ];

    try {
        require_once '../vendor/autoload.php';
        $webPush = new \Minishlink\WebPush\WebPush($auth);

        foreach ($suscripciones as $sub) {
            try {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['p256dh'],
                    'authToken' => $sub['auth']
                ]);

                $payload = json_encode([
                    'title' => $titulo,
                    'body' => $mensaje,
                    'icon' => '/assets/img/logo.png',
                    'badge' => '/assets/img/badge.png',
                    'data' => [
                        'url' => $enlace ?: '/',
                        'timestamp' => time()
                    ]
                ]);

                $result = $webPush->sendOneNotification($subscription, $payload);

                if ($result->isSuccess()) {
                    $resultados['enviados']++;
                } else {
                    $resultados['fallidos']++;
                    // Eliminar suscripciones inválidas
                    if (in_array($result->getStatusCode(), [410, 404])) {
                        $stmtDel = $db->prepare("DELETE FROM push_subscriptions WHERE id = ?");
                        $stmtDel->execute([$sub['id']]);
                    }
                }
            } catch (Exception $e) {
                $resultados['fallidos']++;
                error_log("Error enviando notificación: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Error inicializando WebPush: " . $e->getMessage());
    }

    // Registrar en historial
    $stmtLog = $db->prepare("INSERT INTO notificaciones_log (titulo, mensaje, segmento, enviados, fallidos, fecha) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmtLog->execute([$titulo, $mensaje, $segmento, $resultados['enviados'], $resultados['fallidos']]);

    return $resultados;
}

$banner = new PromotionalBanner($db);
$negocio = new Negocio($db);

// Procesamiento de formularios
$mensaje = '';
$tipo_mensaje = '';
$notif_resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'crear':
                $banner->titulo = htmlspecialchars($_POST['titulo']);
                $banner->descripcion = htmlspecialchars($_POST['descripcion']);
                $banner->enlace_destino = filter_var($_POST['enlace_destino'], FILTER_SANITIZE_URL);
                $banner->tipo_banner = $_POST['tipo_banner'];
                $banner->descuento_porcentaje = intval($_POST['descuento_porcentaje']);
                $banner->fecha_inicio = $_POST['fecha_inicio'] ?: date('Y-m-d H:i:s');
                $banner->fecha_fin = $_POST['fecha_fin'] ?: null;
                $banner->activo = isset($_POST['activo']) ? 1 : 0;
                $banner->posicion = intval($_POST['posicion']);
                $banner->negocio_id = !empty($_POST['negocio_id']) ? intval($_POST['negocio_id']) : null;

                // Manejo de imagen con procesamiento optimizado
                if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $resultado_imagen = procesarImagenBanner($_FILES['imagen']);
                    if (isset($resultado_imagen['success'])) {
                        $banner->imagen_url = $resultado_imagen['path'];
                    } else {
                        $mensaje = "Error en imagen: " . $resultado_imagen['error'];
                        $tipo_mensaje = "warning";
                    }
                } elseif (!empty($_POST['imagen_url'])) {
                    $banner->imagen_url = filter_var($_POST['imagen_url'], FILTER_SANITIZE_URL);
                }

                if ($banner->crear()) {
                    $mensaje = "Banner creado exitosamente";
                    $tipo_mensaje = "success";

                    // Enviar notificación si se solicitó
                    if (isset($_POST['enviar_notificacion']) && $_POST['enviar_notificacion'] == '1') {
                        $segmento = $_POST['segmento_notificacion'] ?? 'todos';
                        $notif_resultado = enviarNotificacionMasiva(
                            $db,
                            $banner->titulo,
                            $banner->descripcion ?: "¡Nueva promoción disponible!",
                            $banner->enlace_destino,
                            $segmento
                        );
                        $mensaje .= " | Notificaciones: {$notif_resultado['enviados']} enviadas, {$notif_resultado['fallidos']} fallidas";
                    }
                } else {
                    $mensaje = "Error al crear el banner";
                    $tipo_mensaje = "danger";
                }
                break;

            case 'editar':
                $banner->id_banner = intval($_POST['id_banner']);
                $banner->titulo = htmlspecialchars($_POST['titulo']);
                $banner->descripcion = htmlspecialchars($_POST['descripcion']);
                $banner->enlace_destino = filter_var($_POST['enlace_destino'], FILTER_SANITIZE_URL);
                $banner->tipo_banner = $_POST['tipo_banner'];
                $banner->descuento_porcentaje = intval($_POST['descuento_porcentaje']);
                $banner->fecha_inicio = $_POST['fecha_inicio'];
                $banner->fecha_fin = $_POST['fecha_fin'] ?: null;
                $banner->activo = isset($_POST['activo']) ? 1 : 0;
                $banner->posicion = intval($_POST['posicion']);
                $banner->negocio_id = !empty($_POST['negocio_id']) ? intval($_POST['negocio_id']) : null;

                // Manejo de imagen con procesamiento optimizado
                if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $resultado_imagen = procesarImagenBanner($_FILES['imagen']);
                    if (isset($resultado_imagen['success'])) {
                        $banner->imagen_url = $resultado_imagen['path'];
                    }
                } elseif (!empty($_POST['imagen_url'])) {
                    $banner->imagen_url = filter_var($_POST['imagen_url'], FILTER_SANITIZE_URL);
                } else {
                    // Mantener imagen actual
                    $banner->obtenerPorId();
                }

                if ($banner->actualizar()) {
                    $mensaje = "Banner actualizado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar el banner";
                    $tipo_mensaje = "danger";
                }
                break;

            case 'enviar_notificacion':
                $titulo_notif = htmlspecialchars($_POST['titulo_notificacion']);
                $mensaje_notif = htmlspecialchars($_POST['mensaje_notificacion']);
                $enlace_notif = filter_var($_POST['enlace_notificacion'] ?? '', FILTER_SANITIZE_URL);
                $segmento = $_POST['segmento'] ?? 'todos';

                $notif_resultado = enviarNotificacionMasiva($db, $titulo_notif, $mensaje_notif, $enlace_notif, $segmento);

                $mensaje = "Notificación enviada: {$notif_resultado['enviados']} de {$notif_resultado['total']} usuarios";
                $tipo_mensaje = $notif_resultado['enviados'] > 0 ? "success" : "warning";
                break;
                
            case 'eliminar':
                $banner->id_banner = intval($_POST['id_banner']);
                if ($banner->eliminar()) {
                    $mensaje = "Banner eliminado exitosamente";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al eliminar el banner";
                    $tipo_mensaje = "danger";
                }
                break;
                
            case 'toggle_activo':
                $banner->id_banner = intval($_POST['id_banner']);
                $banner->obtenerPorId();
                $banner->activo = $banner->activo ? 0 : 1;
                if ($banner->actualizar()) {
                    echo json_encode(['success' => true]);
                    exit();
                } else {
                    echo json_encode(['success' => false]);
                    exit();
                }
                break;
        }
    }
}

// Obtener datos para mostrar
$banners = $banner->obtenerTodos();
$negocios = $negocio->obtenerTodos();

// Estadísticas
$stats_query = "SELECT 
    COUNT(*) as total_banners,
    COUNT(CASE WHEN activo = 1 THEN 1 END) as banners_activos,
    COUNT(CASE WHEN tipo_banner = 'descuento' THEN 1 END) as banners_descuento,
    COUNT(CASE WHEN fecha_fin < NOW() THEN 1 END) as banners_expirados
    FROM promotional_banners";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$estadisticas = $stats_stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - Banners Promocionales</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 2px 0;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .stat-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .banner-preview {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: white;
            margin-bottom: 1rem;
            position: relative;
            min-height: 200px;
        }

        .banner-preview.has-image {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .banner-preview .banner-overlay {
            background: linear-gradient(135deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.3) 100%);
            padding: 1.5rem;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .banner-preview.descuento .banner-overlay {
            background: linear-gradient(135deg, rgba(240,147,251,0.85) 0%, rgba(245,87,108,0.85) 100%);
        }

        .banner-preview.nuevo_negocio .banner-overlay {
            background: linear-gradient(135deg, rgba(79,172,254,0.85) 0%, rgba(0,242,254,0.85) 100%);
        }

        .banner-preview.evento .banner-overlay {
            background: linear-gradient(135deg, rgba(67,233,123,0.85) 0%, rgba(56,249,215,0.85) 100%);
        }

        .banner-preview.promocion .banner-overlay {
            background: linear-gradient(135deg, rgba(102,126,234,0.85) 0%, rgba(118,75,162,0.85) 100%);
        }

        .banner-preview:not(.has-image).descuento {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .banner-preview:not(.has-image).nuevo_negocio {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .banner-preview:not(.has-image).evento {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .banner-preview:not(.has-image).promocion {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .banner-image-full {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .banner-image-thumb {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .banner-title {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .banner-description {
            font-size: 0.9rem;
            opacity: 0.95;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .banner-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .banner-discount-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ff4757;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(255,71,87,0.4);
        }

        /* Vista previa mejorada en modal */
        #banner-preview-modal {
            border-radius: 16px;
            overflow: hidden;
            min-height: 200px;
            background-size: cover;
            background-position: center;
        }

        #banner-preview-modal .preview-content {
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.2) 100%);
            padding: 20px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            color: white;
        }

        /* Tabla de imágenes mejorada */
        .table .banner-img-cell {
            width: 120px;
        }

        .table .banner-img-cell img {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Notificaciones card */
        .notification-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }

        .notification-stat {
            text-align: center;
            padding: 15px;
        }

        .notification-stat h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .notification-stat small {
            opacity: 0.8;
        }
        
        .btn-sm {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center mb-4">
                    <h4><i class="fas fa-bullhorn me-2"></i>QuickBite</h4>
                    <small>Panel de Banners</small>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="ceo-panel.php">
                        <i class="fas fa-chart-pie me-2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="promotional_banners.php">
                        <i class="fas fa-images me-2"></i>Banners
                    </a>
                    <a class="nav-link" href="gestionar_aliados.php">
                        <i class="fas fa-handshake me-2"></i>Aliados
                    </a>
                    <hr class="my-3">
                    <a class="nav-link" href="../index.php">
                        <i class="fas fa-home me-2"></i>Volver al sitio
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-bullhorn me-2"></i>Banners Promocionales</h2>
                        <p class="text-muted">Gestiona las campañas publicitarias de tu plataforma</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bannerModal">
                        <i class="fas fa-plus me-2"></i>Nuevo Banner
                    </button>
                </div>

                <!-- Mensajes -->
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Section -->
                <div id="dashboard">
                    <!-- Estadísticas Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $estadisticas['total_banners']; ?></h3>
                                            <p class="mb-0">Total Banners</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-images fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $estadisticas['banners_activos']; ?></h3>
                                            <p class="mb-0">Banners Activos</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-eye fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $estadisticas['banners_descuento']; ?></h3>
                                            <p class="mb-0">Descuentos</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-percent fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $estadisticas['banners_expirados']; ?></h3>
                                            <p class="mb-0">Expirados</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="chart-container">
                                <h5>Rendimiento de Banners (Últimos 30 días)</h5>
                                <canvas id="performanceChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="chart-container">
                                <h5>Distribución por Tipo</h5>
                                <canvas id="typeChart" width="200" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección de Notificaciones Push -->
                <div class="notification-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5><i class="fas fa-bell me-2"></i>Centro de Notificaciones Push</h5>
                            <p class="mb-0 opacity-75">Envía notificaciones masivas a todos los usuarios de la app</p>
                        </div>
                        <div class="col-md-3">
                            <?php
                            $stmt_subs = $db->query("SELECT COUNT(*) as total FROM push_subscriptions");
                            $total_suscriptores = $stmt_subs->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                            ?>
                            <div class="notification-stat">
                                <h3><?php echo number_format($total_suscriptores); ?></h3>
                                <small>Usuarios suscritos</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#notificationModal">
                                <i class="fas fa-paper-plane me-2"></i>Enviar Notificación
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Banners Section -->
                <div id="banners">
                    <h4 class="mb-3">Banners Activos - Vista Previa</h4>
                    <div class="row">
                        <?php foreach ($banners as $b): ?>
                            <?php if ($b['activo']): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="banner-preview <?php echo $b['tipo_banner']; ?> <?php echo $b['imagen_url'] ? 'has-image' : ''; ?>"
                                         <?php if ($b['imagen_url']): ?>style="background-image: url('../<?php echo htmlspecialchars($b['imagen_url']); ?>');"<?php endif; ?>>

                                        <?php if ($b['tipo_banner'] === 'descuento' && $b['descuento_porcentaje'] > 0): ?>
                                            <div class="banner-discount-badge">
                                                <?php echo $b['descuento_porcentaje']; ?>% OFF
                                            </div>
                                        <?php endif; ?>

                                        <div class="banner-actions">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-light btn-sm" onclick="editBanner(<?php echo $b['id_banner']; ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm" onclick="sendBannerNotification(<?php echo $b['id_banner']; ?>)" title="Enviar notificación">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                                <button class="btn btn-warning btn-sm" onclick="toggleBanner(<?php echo $b['id_banner']; ?>)" title="Desactivar">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteBanner(<?php echo $b['id_banner']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="banner-overlay">
                                            <div class="banner-title">
                                                <?php echo htmlspecialchars($b['titulo']); ?>
                                            </div>

                                            <?php if ($b['descripcion']): ?>
                                                <div class="banner-description">
                                                    <?php echo htmlspecialchars($b['descripcion']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="opacity-75">
                                                    <i class="fas fa-store me-1"></i>
                                                    <?php echo $b['negocio_nombre'] ?: 'General'; ?>
                                                </small>
                                                <small class="opacity-75">
                                                    <i class="fas fa-sort-numeric-up me-1"></i>Pos: <?php echo $b['posicion']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tabla de todos los banners -->
                    <h4 class="mb-3 mt-5">Todos los Banners</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Imagen</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Negocio</th>
                                    <th>Estado</th>
                                    <th>Posición</th>
                                    <th>Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($banners as $b): ?>
                                    <tr>
                                        <td class="banner-img-cell">
                                            <?php if ($b['imagen_url']): ?>
                                                <img src="../<?php echo htmlspecialchars($b['imagen_url']); ?>" alt="Banner" loading="lazy">
                                            <?php else: ?>
                                                <div class="bg-secondary d-flex align-items-center justify-content-center text-white" style="width:100px;height:60px;border-radius:8px;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['titulo']); ?></strong>
                                            <?php if ($b['descuento_porcentaje'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $b['descuento_porcentaje']; ?>% OFF</span>
                                            <?php endif; ?>
                                            <?php if ($b['descripcion']): ?>
                                                <br><small class="text-muted"><?php echo mb_substr(htmlspecialchars($b['descripcion']), 0, 50); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $b['tipo_banner'] === 'descuento' ? 'danger' : 
                                                    ($b['tipo_banner'] === 'nuevo_negocio' ? 'info' : 
                                                    ($b['tipo_banner'] === 'evento' ? 'success' : 'primary'));
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $b['tipo_banner'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $b['negocio_nombre'] ?: '<span class="text-muted">General</span>'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $b['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $b['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                            <?php if ($b['fecha_fin'] && strtotime($b['fecha_fin']) < time()): ?>
                                                <span class="badge bg-warning">Expirado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $b['posicion']; ?></td>
                                        <td>
                                            <small>
                                                <strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($b['fecha_inicio'])); ?><br>
                                                <?php if ($b['fecha_fin']): ?>
                                                    <strong>Fin:</strong> <?php echo date('d/m/Y', strtotime($b['fecha_fin'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin fin</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editBanner(<?php echo $b['id_banner']; ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="sendBannerNotification(<?php echo $b['id_banner']; ?>)" title="Notificar">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                                <button class="btn btn-outline-<?php echo $b['activo'] ? 'warning' : 'success'; ?>"
                                                        onclick="toggleBanner(<?php echo $b['id_banner']; ?>)"
                                                        title="<?php echo $b['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo $b['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteBanner(<?php echo $b['id_banner']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Enviar Notificación -->
    <div class="modal fade" id="notificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-bell me-2"></i>Enviar Notificación Push
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="enviar_notificacion">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Título de la Notificación *</label>
                            <input type="text" class="form-control" name="titulo_notificacion" required maxlength="50" placeholder="Ej: ¡Nuevas ofertas!">
                            <small class="text-muted">Máximo 50 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mensaje *</label>
                            <textarea class="form-control" name="mensaje_notificacion" rows="3" required maxlength="200" placeholder="Ej: Descubre las promociones de hoy..."></textarea>
                            <small class="text-muted">Máximo 200 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Enlace de destino (opcional)</label>
                            <input type="url" class="form-control" name="enlace_notificacion" placeholder="https://...">
                            <small class="text-muted">A dónde llevar al usuario al hacer clic</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Segmento de usuarios</label>
                            <select class="form-select" name="segmento">
                                <option value="todos">Todos los usuarios (<?php echo number_format($total_suscriptores); ?>)</option>
                                <option value="activos">Solo usuarios activos</option>
                                <option value="premium">Solo miembros premium</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Las notificaciones se enviarán inmediatamente a todos los usuarios que tengan activadas las notificaciones push en sus dispositivos.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Notificación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Crear/Editar Banner -->
    <div class="modal fade" id="bannerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bullhorn me-2"></i>
                        <span id="modal-title">Crear Banner Promocional</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="bannerForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="form-action" value="crear">
                    <input type="hidden" name="id_banner" id="form-id">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Título *</label>
                                    <input type="text" class="form-control" name="titulo" id="titulo" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="descripcion" id="descripcion" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Banner</label>
                                            <select class="form-select" name="tipo_banner" id="tipo_banner">
                                                <option value="promocion">Promoción General</option>
                                                <option value="descuento">Descuento</option>
                                                <option value="nuevo_negocio">Nuevo Negocio</option>
                                                <option value="evento">Evento</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">% Descuento</label>
                                            <input type="number" class="form-control" name="descuento_porcentaje" id="descuento_porcentaje" min="0" max="100" value="0">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Posición</label>
                                            <input type="number" class="form-control" name="posicion" id="posicion" min="0" value="0">
                                            <small class="text-muted">0 = mayor prioridad</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Estado</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="activo" id="activo" checked>
                                                <label class="form-check-label" for="activo">
                                                    Banner activo
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Imagen del Banner</label>
                                    <input type="file" class="form-control" name="imagen" id="imagen" accept="image/*">
                                    <small class="text-muted">O pega una URL de imagen abajo</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">URL de Imagen</label>
                                    <input type="url" class="form-control" name="imagen_url" id="imagen_url" placeholder="https://...">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Enlace Destino</label>
                                    <input type="url" class="form-control" name="enlace_destino" id="enlace_destino" placeholder="https://...">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Negocio (opcional)</label>
                                    <select class="form-select" name="negocio_id" id="negocio_id">
                                        <option value="">Seleccionar negocio...</option>
                                        <?php foreach ($negocios as $neg): ?>
                                            <option value="<?php echo $neg['id_negocio']; ?>">
                                                <?php echo htmlspecialchars($neg['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha Inicio</label>
                                            <input type="datetime-local" class="form-control" name="fecha_inicio" id="fecha_inicio">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha Fin</label>
                                            <input type="datetime-local" class="form-control" name="fecha_fin" id="fecha_fin">
                                            <small class="text-muted">Opcional</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Opción de Notificación Push -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="enviar_notificacion" id="enviar_notificacion" value="1">
                                            <label class="form-check-label" for="enviar_notificacion">
                                                <i class="fas fa-bell text-primary me-2"></i>
                                                <strong>Enviar notificación push al crear</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            Se enviará una notificación a todos los usuarios con el título y descripción del banner
                                        </small>

                                        <div id="notif-options" style="display:none; margin-top: 15px;">
                                            <label class="form-label">Segmento de usuarios:</label>
                                            <select class="form-select form-select-sm" name="segmento_notificacion">
                                                <option value="todos">Todos los usuarios</option>
                                                <option value="activos">Solo usuarios activos</option>
                                                <option value="premium">Solo miembros premium</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vista Previa -->
                        <div class="mb-3">
                            <label class="form-label">Vista Previa:</label>
                            <div id="banner-preview" class="banner-preview promocion">
                                <div class="banner-image d-flex align-items-center justify-content-center">
                                    <i class="fas fa-image"></i>
                                </div>
                                <div class="banner-title">Título del Banner</div>
                                <div class="banner-description">Descripción del banner promocional</div>
                                <button type="button" class="btn btn-light btn-sm">Ver Oferta →</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Banner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script>
        // Variables globales
        const bannersData = <?php echo json_encode($banners); ?>;
        
        // Actualizar vista previa en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('bannerForm');
            const preview = document.getElementById('banner-preview');
            
            // Inicializar fecha actual
            const now = new Date();
            const formattedNow = now.toISOString().slice(0, 16);
            document.getElementById('fecha_inicio').value = formattedNow;
            
            // Actualizar preview cuando cambien los inputs
            form.addEventListener('input', updatePreview);
            form.addEventListener('change', updatePreview);
            
            function updatePreview() {
                const titulo = form.titulo.value || 'Título del Banner';
                const descripcion = form.descripcion.value || 'Descripción del banner promocional';
                const tipo = form.tipo_banner.value;
                const descuento = form.descuento_porcentaje.value;
                const imagenUrl = form.imagen_url.value;
                
                // Actualizar contenido
                preview.querySelector('.banner-title').textContent = titulo;
                preview.querySelector('.banner-description').textContent = descripcion;
                
                // Actualizar clase de tipo
                preview.className = `banner-preview ${tipo}`;
                
                // Mostrar descuento si aplica
                if (tipo === 'descuento' && descuento > 0) {
                    preview.querySelector('.banner-title').textContent = `${descuento}% OFF - ${titulo}`;
                }
                
                // Actualizar imagen
                const bannerImage = preview.querySelector('.banner-image');
                if (imagenUrl) {
                    bannerImage.innerHTML = `<img src="${imagenUrl}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                } else {
                    bannerImage.innerHTML = '<i class="fas fa-image"></i>';
                }
            }
            
            // Inicializar gráficos
            initCharts();
            
            // Manejar subida de archivos
            document.getElementById('imagen').addEventListener('change', function(e) {
                if (e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const bannerImage = preview.querySelector('.banner-image');
                        bannerImage.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
        });
        
        // Funciones para manejar banners
        function editBanner(id) {
            const banner = bannersData.find(b => b.id_banner == id);
            if (!banner) return;
            
            // Llenar formulario
            document.getElementById('form-action').value = 'editar';
            document.getElementById('form-id').value = banner.id_banner;
            document.getElementById('modal-title').textContent = 'Editar Banner';
            
            document.getElementById('titulo').value = banner.titulo;
            document.getElementById('descripcion').value = banner.descripcion || '';
            document.getElementById('enlace_destino').value = banner.enlace_destino || '';
            document.getElementById('tipo_banner').value = banner.tipo_banner;
            document.getElementById('descuento_porcentaje').value = banner.descuento_porcentaje || 0;
            document.getElementById('posicion').value = banner.posicion || 0;
            document.getElementById('activo').checked = banner.activo == 1;
            document.getElementById('imagen_url').value = banner.imagen_url || '';
            document.getElementById('negocio_id').value = banner.negocio_id || '';
            
            if (banner.fecha_inicio) {
                const fechaInicio = new Date(banner.fecha_inicio);
                document.getElementById('fecha_inicio').value = fechaInicio.toISOString().slice(0, 16);
            }
            
            if (banner.fecha_fin) {
                const fechaFin = new Date(banner.fecha_fin);
                document.getElementById('fecha_fin').value = fechaFin.toISOString().slice(0, 16);
            }
            
            // Mostrar modal
            new bootstrap.Modal(document.getElementById('bannerModal')).show();
        }
        
        function toggleBanner(id) {
            if (confirm('¿Estás seguro de cambiar el estado de este banner?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_activo&id_banner=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al cambiar el estado del banner');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión');
                });
            }
        }
        
        function deleteBanner(id) {
            if (confirm('¿Estás seguro de eliminar este banner? Esta acción no se puede deshacer.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id_banner" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetForm() {
            document.getElementById('form-action').value = 'crear';
            document.getElementById('form-id').value = '';
            document.getElementById('modal-title').textContent = 'Crear Banner Promocional';
            document.getElementById('bannerForm').reset();
            
            // Resetear fecha actual
            const now = new Date();
            const formattedNow = now.toISOString().slice(0, 16);
            document.getElementById('fecha_inicio').value = formattedNow;
        }
        
        // Inicializar gráficos
        function initCharts() {
            // Gráfico de rendimiento
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Clics',
                        data: [12, 19, 3, 5, 2, 3, 10],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1
                    }, {
                        label: 'Conversiones',
                        data: [2, 3, 1, 1, 0, 1, 2],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Gráfico de distribución por tipo
            const typeCtx = document.getElementById('typeChart').getContext('2d');
            new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Descuentos', 'Promociones', 'Nuevos Negocios', 'Eventos'],
                    datasets: [{
                        data: [
                            <?php echo $estadisticas['banners_descuento']; ?>,
                            <?php echo $estadisticas['total_banners'] - $estadisticas['banners_descuento']; ?>,
                            2,
                            1
                        ],
                        backgroundColor: [
                            '#f093fb',
                            '#667eea',
                            '#4facfe',
                            '#43e97b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        }
        
        // Toggle opciones de notificación
        document.getElementById('enviar_notificacion').addEventListener('change', function() {
            document.getElementById('notif-options').style.display = this.checked ? 'block' : 'none';
        });

        // Función para enviar notificación de un banner existente
        function sendBannerNotification(id) {
            const banner = bannersData.find(b => b.id_banner == id);
            if (!banner) return;

            // Rellenar el modal de notificación con los datos del banner
            document.querySelector('#notificationModal input[name="titulo_notificacion"]').value = banner.titulo;
            document.querySelector('#notificationModal textarea[name="mensaje_notificacion"]').value = banner.descripcion || '¡Nueva promoción disponible!';
            document.querySelector('#notificationModal input[name="enlace_notificacion"]').value = banner.enlace_destino || '';

            // Abrir modal
            new bootstrap.Modal(document.getElementById('notificationModal')).show();
        }

        // Event listeners
        document.getElementById('bannerModal').addEventListener('hidden.bs.modal', resetForm);

        // Navegación del sidebar
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    const target = this.getAttribute('href').substring(1);
                    if (target === 'dashboard') {
                        document.getElementById('dashboard').style.display = 'block';
                        document.getElementById('banners').style.display = 'none';
                    } else if (target === 'banners') {
                        document.getElementById('dashboard').style.display = 'none';
                        document.getElementById('banners').style.display = 'block';
                    }
                }
            });
        });
    </script>
</body>
</html>

