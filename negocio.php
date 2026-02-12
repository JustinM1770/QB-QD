<?php
// Iniciar sesión
session_start();

// Habilitar reporte de errores para depuración
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Verificar que se recibió el ID del negocio
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id_negocio = (int)$_GET['id'];

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'includes/business_helpers.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Producto.php';
require_once 'models/ElegibleProducto.php';

// Incluir lógica especializada de Orez Floristería
require_once 'includes/orez_floreria.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Obtener información del negocio
$negocio = new Negocio($db);
$negocio->id_negocio = $id_negocio;

// Si no se encuentra el negocio, redireccionar
if (!$negocio->obtenerPorId()) {
    header("Location: index.php");
    exit;
}

// Obtener y agrupar productos por categoría
$producto = new Producto($db);
$productos = $producto->obtenerPorNegocio($id_negocio);

// Agrupar productos por categoría
$productos_por_categoria = [];
$categorias = [];

foreach ($productos as $prod) {
    $id_cat = $prod['id_categoria'] ?? 0;
    $nombre_cat = $prod['nombre_categoria'] ?? 'Sin categoría';
    
    if (!isset($categorias[$id_cat])) {
        $categorias[$id_cat] = [
            'id_categoria' => $id_cat,
            'nombre' => $nombre_cat,
            'productos' => []
        ];
    }
    
    $categorias[$id_cat]['productos'][] = $prod;
}

// Obtener elegibles y grupos de opciones para productos
foreach ($categorias as &$categoria) {
    foreach ($categoria['productos'] as &$prod) {
        // Obtener elegibles tradicionales si los tiene
        if ($prod['tiene_elegibles']) {
            $elegible = new ElegibleProducto($db);
            $prod['elegibles'] = $elegible->obtenerPorProducto($prod['id_producto']);
        }

        // Obtener grupos de opciones dinámicos (colores, tamaños, etc.)
        $producto_temp = new Producto($db);
        $prod['grupos_opciones'] = $producto_temp->obtenerOpcionesPorProducto($prod['id_producto']);
        $prod['tiene_grupos_opciones'] = !empty($prod['grupos_opciones']);
    }
}
unset($categoria, $prod);

// Convertir a array indexado numéricamente
$productos_por_categoria = array_values($categorias);

// Obtener productos destacados/recomendados (los más vendidos o marcados como destacados)
$productos_destacados = [];
foreach ($productos as $prod) {
    // Considerar destacados o los primeros 6 productos con imagen
    if (!empty($prod['destacado']) || (!empty($prod['imagen']) && count($productos_destacados) < 6)) {
        $productos_destacados[] = $prod;
    }
}
// Si no hay suficientes destacados, tomar los primeros 6 con imagen
if (count($productos_destacados) < 4) {
    $productos_destacados = array_filter($productos, function($p) {
        return !empty($p['imagen']);
    });
    $productos_destacados = array_slice($productos_destacados, 0, 6);
}

// Detectar si es Orez Floristería y cargar complementos
$es_orez_floreria = ($id_negocio == OREZ_NEGOCIO_ID);
$complementos_orez = [];
$variantes_orez = [];
if ($es_orez_floreria) {
    $complementos_orez = getComplementosFlores($db, $id_negocio);
    $variantes_orez = getVariantesProductoOrez($db, $id_negocio);

    // 1. OBTENER LAS VARIANTES
    $variantes_raw = getVariantesProductoOrez($db, $id_negocio);
    $variantes_orez = [];
    // Aplicar recargo del 5% a variantes
    foreach ($variantes_raw as $v) {
        $v['precio_final'] = $v['precio'] * 1.05; 
        $variantes_orez[] = $v;
    }

    // Filtrar productos de Orez: ocultar "- Mitad", "- Doble" y "Extras para Ramos"
    foreach ($productos_por_categoria as $key => &$cat) {
        // Ocultar categoría "Extras para Ramos" completamente
        if (stripos($cat['nombre'], 'Extras') !== false) {
            unset($productos_por_categoria[$key]);
            continue;
        }

        // Filtrar productos con sufijos de variante (se mostrarán en el modal del producto principal)
        $cat['productos'] = array_filter($cat['productos'], function($p) {
            $nombre = $p['nombre'] ?? '';
            // Ocultar si termina en "- Mitad", "- Doble", "Mitad" o "Doble"
            return !preg_match('/\s*-?\s*(Mitad|Doble)\s*(Ramo)?\s*$/i', $nombre);
        });

        // Re-indexar el array
        $cat['productos'] = array_values($cat['productos']);

        // Si la categoría quedó vacía, eliminarla
        if (empty($cat['productos'])) {
            unset($productos_por_categoria[$key]);
        }
    }
    unset($cat);

    // Re-indexar array de categorías
    $productos_por_categoria = array_values($productos_por_categoria);
}

// Verificar si el negocio está abierto actualmente (usando función estandarizada)
$esta_abierto = isBusinessOpen($db, $id_negocio);

// Obtener horarios formateados
$horarios_formateados = $negocio->obtenerHorariosFormateados();

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Verificar si el negocio está en favoritos del usuario
$negocio_favorito = false;
if ($usuario_logueado && isset($_SESSION['id_usuario'])) {
    $id_usuario = $_SESSION['id_usuario'];
    $query_fav = "SELECT 1 FROM favoritos WHERE id_usuario = :id_usuario AND id_negocio = :id_negocio LIMIT 1";
    $stmt_fav = $db->prepare($query_fav);
    $stmt_fav->bindParam(':id_usuario', $id_usuario);
    $stmt_fav->bindParam(':id_negocio', $id_negocio);
    $stmt_fav->execute();
    $negocio_favorito = $stmt_fav->rowCount() > 0;
}

// Obtener resenas del negocio
$resenas_negocio = [];
$stats_resenas = null;
try {
    // Estadisticas de resenas
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            AVG(calificacion_negocio) as promedio,
            SUM(CASE WHEN calificacion_negocio = 5 THEN 1 ELSE 0 END) as cinco,
            SUM(CASE WHEN calificacion_negocio = 4 THEN 1 ELSE 0 END) as cuatro,
            SUM(CASE WHEN calificacion_negocio = 3 THEN 1 ELSE 0 END) as tres,
            SUM(CASE WHEN calificacion_negocio = 2 THEN 1 ELSE 0 END) as dos,
            SUM(CASE WHEN calificacion_negocio = 1 THEN 1 ELSE 0 END) as una
        FROM valoraciones
        WHERE id_negocio = ? AND visible = 1
    ");
    $stmt->execute([$id_negocio]);
    $stats_resenas = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener resenas recientes
    $stmt = $db->prepare("
        SELECT v.*, u.nombre as nombre_usuario,
               DATE_FORMAT(v.fecha_creacion, '%d/%m/%Y') as fecha_formateada
        FROM valoraciones v
        JOIN usuarios u ON v.id_usuario = u.id_usuario
        WHERE v.id_negocio = ? AND v.visible = 1
        ORDER BY v.fecha_creacion DESC
        LIMIT 10
    ");
    $stmt->execute([$id_negocio]);
    $resenas_negocio = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener resenas: " . $e->getMessage());
}

// Formatear días de la semana
$dias_semana = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];

// Verificar si hay una ubicación de usuario para calcular distancia
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

// Calcular si está en radio de entrega
$dentro_radio_entrega = true; // Forzar a que siempre esté dentro del radio de entrega

$producto_agregado = false;
$mensaje_carrito = "";

// Procesar agregar al carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_carrito'])) {
    try {
        // Verificar que el usuario está logueado
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            header("Location: login.php?redirect=negocio.php?id=" . $id_negocio);
            exit;
        }

        // Verificar datos básicos
        if (!isset($_POST['id_producto']) || empty($_POST['id_producto'])) {
            throw new Exception("ID de producto no especificado");
        }

        $id_producto = (int)$_POST['id_producto'];
        $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;
        
        if ($cantidad < 1 || $cantidad > 10) {
            throw new Exception("Cantidad debe ser entre 1 y 10");
        }
        
        // Obtener información del producto
        $producto_temp = new Producto($db);
        $producto_encontrado = $producto_temp->obtenerPorId($id_producto);
        
        if (!$producto_encontrado) {
            throw new Exception("Producto no encontrado");
        }
        
        // ✅ VALIDAR QUE EL NEGOCIO ESTÉ ABIERTO ANTES DE AGREGAR AL CARRITO
        if (!$esta_abierto) {
            // Obtener horarios del día actual para mostrar en el mensaje
            $dia_actual = (int)date('w');
            $stmt_horario = $db->prepare("SELECT hora_apertura, hora_cierre FROM negocio_horarios WHERE id_negocio = ? AND dia_semana = ? AND activo = 1");
            $stmt_horario->execute([$id_negocio, $dia_actual]);
            $horario_hoy = $stmt_horario->fetch(PDO::FETCH_ASSOC);
            
            $mensaje_horario = "";
            if ($horario_hoy) {
                $apertura = substr($horario_hoy['hora_apertura'], 0, 5);
                $cierre = substr($horario_hoy['hora_cierre'], 0, 5);
                $mensaje_horario = " Horario de hoy: $apertura - $cierre hrs.";
            } else {
                $mensaje_horario = " El negocio no abre hoy.";
            }
            
            throw new Exception("El negocio está cerrado en este momento. No puedes agregar productos.$mensaje_horario");
        }
        
        // Verificar si ya hay productos de otro restaurante en el carrito
        if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito']['items']) && 
            isset($_SESSION['carrito']['negocio_id']) && $_SESSION['carrito']['negocio_id'] != $id_negocio) {
            // Si se indicó que se debe limpiar el carrito, hacerlo
            if (isset($_POST['limpiar_carrito']) && $_POST['limpiar_carrito'] == '1') {
                $_SESSION['carrito'] = [];
            } else {
                $mensaje_carrito = "Ya tienes productos de otro restaurante en tu carrito. ¿Deseas vaciar el carrito y agregar este producto?";
                throw new Exception($mensaje_carrito);
            }
        }
        
        // Inicializar carrito si no existe
        if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [
                'items' => [],
                'negocio_id' => $id_negocio,
                'negocio_nombre' => $negocio->nombre,
                'subtotal' => 0,
                'total' => 0
            ];
        }
        
        // Verificar si el producto ya está en el carrito
        $producto_existente = false;
        foreach ($_SESSION['carrito']['items'] as $key => $item) {
            if ($item['id_producto'] == $id_producto) {
                // Incrementar cantidad
                $_SESSION['carrito']['items'][$key]['cantidad'] += $cantidad;
                $producto_existente = true;
                break;
            }
        }
        
        if (!$producto_existente) {
            // Agregar item al carrito con índice numérico (consistente con carrito.php)
            $_SESSION['carrito']['items'][] = [
                'id_producto' => $id_producto,
                'nombre' => htmlspecialchars($producto_encontrado['nombre']),
                'descripcion' => isset($producto_encontrado['descripcion']) ? htmlspecialchars($producto_encontrado['descripcion']) : '',
                'precio' => (float)$producto_encontrado['precio'],
                'cantidad' => $cantidad,
                'imagen' => isset($producto_encontrado['imagen']) ? $producto_encontrado['imagen'] : ''
            ];
        }
        
        // Calcular subtotal y total
        $_SESSION['carrito']['subtotal'] = 0;
        foreach ($_SESSION['carrito']['items'] as $item) {
            $_SESSION['carrito']['subtotal'] += $item['precio'] * $item['cantidad'];
        }
        $_SESSION['carrito']['total'] = $_SESSION['carrito']['subtotal'];
        
        $producto_agregado = true;
        $mensaje_carrito = "Producto agregado al carrito correctamente.";
        
    } catch (Exception $e) {
        error_log('Error en agregar_carrito: ' . $e->getMessage());
        
        if (strpos($e->getMessage(), "otro restaurante") !== false) {
            $producto_agregado = false;
            $mensaje_carrito = $e->getMessage();
        } else {
            $producto_agregado = false;
            $mensaje_carrito = "Error al agregar al carrito: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($negocio->nombre); ?> - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    
    <!-- Global Theme CSS y JS (Modo Oscuro Persistente) -->
    <link rel="stylesheet" href="assets/css/global-theme.css?v=2.1">
    <link rel="stylesheet" href="assets/css/store-premium.css?v=1.0">
    <?php if ($es_orez_floreria): ?>
    <link rel="stylesheet" href="assets/css/orez-floreria.css?v=1.0">
    <?php endif; ?>
    <script src="assets/js/theme-handler.js?v=2.1"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>

</head>
<style>
        :root {
            --primary: #0165FF;
            --primary-light: #4285F4;
            --primary-dark: #0052CC;
            --secondary: #F8FAFC;
            --accent: #1E293B;
            --dark: #0F172A;
            --light: #FFFFFF;
            --gradient: linear-gradient(135deg, #0165FF 0%, #4285F4 100%);
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--light);
            min-height: 100vh;
            color: var(--dark);
            padding-bottom: 100px;
            overflow-x: hidden;
            max-width: 100vw;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            overflow-x: hidden;
        }

        /* Restaurant Header */
        .restaurant-header {
            position: relative;
            height: 370px;
            margin-bottom: 70px;
            border-radius: 0 0 25px 25px;
            overflow: hidden;
            background: var(--gradient);
            width: 100%;
        }

        .restaurant-cover {
            height: 100%;
            width: 100%;
            background: var(--gradient);
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .restaurant-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Restaurant Info - Versión Final */
        .restaurant-info {
            position: absolute;
            bottom: -35px;
            left: 1rem;
            right: 1rem;
            background-color: var(--light);
            border-radius: 25px;
            padding: 60px 20px 20px 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            border: 3px solid rgba(1, 101, 255, 0.1);
            max-width: calc(100vw - 2rem);
            margin: 0 auto;
        }

        .restaurant-logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: var(--gradient);
            background-size: cover;
            background-position: center;
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: 4px solid var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--light);
            font-size: 1.8rem;
            overflow: hidden;
            z-index: 5;
        }

        .restaurant-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
        }

        .restaurant-name {
            margin-top: 25px;
            font-size: 2.2rem;
            margin-bottom: 12px;
            color: var(--dark);
            font-weight: 800;
            text-align: center;
            line-height: 1.1;
            word-wrap: break-word;
            hyphens: auto;
        }

        .status-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 18px;
        }

        .status-tag {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-open {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .status-closed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .restaurant-tags {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 18px;
            gap: 8px;
        }

        .tag {
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.1), rgba(68, 133, 244, 0.1));
            color: var(--primary);
            font-size: 0.85rem;
            padding: 6px 14px;
            border-radius: 25px;
            border: 1px solid rgba(1, 101, 255, 0.2);
            font-weight: 500;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .restaurant-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 0;
            font-size: 0.95rem;
            color: var(--accent);
            gap: 8px 18px;
        }

        .restaurant-meta span {
            display: flex;
            align-items: center;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .restaurant-meta i {
            margin-right: 6px;
            color: var(--primary);
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            text-decoration: none;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            z-index: 10;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .back-button:hover {
            background: var(--light);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .action-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 12px;
            z-index: 10;
        }

        .action-button {
            background: rgba(255, 255, 255, 0.95);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            text-decoration: none;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            backdrop-filter: blur(10px);
        }

        .action-button:hover {
            background: var(--light);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .action-button.favorite {
            color: var(--danger);
        }

        .action-button.favorite.active {
            background: var(--danger);
            color: var(--light);
        }
        /* Estilos para opciones y calorías */
    .product-options {
    background: linear-gradient(135deg, rgba(1, 101, 255, 0.1), rgba(1, 101, 255, 0.05));
    border-radius: 8px;
    padding: 8px 12px;
    border-left: 3px solid var(--primary);
}

.options-list {
    margin-top: 4px;
}

.options-text {
    color: var(--accent);
    font-size: 0.8rem;
    line-height: 1.4;
}

.product-calories {
    display: flex;
    justify-content: flex-start;
    align-items: center;
}

.calories-badge {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, rgba(255, 149, 0, 0.15), rgba(255, 149, 0, 0.1));
    color: #ff9500;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(255, 149, 0, 0.2);
}

.calories-badge i {
    margin-right: 4px;
    font-size: 0.75rem;
}

/* Selector de elegibles */
.elegibles-selector {
    margin-bottom: 15px;
}

.elegible-select {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid rgba(1, 101, 255, 0.2);
    border-radius: 10px;
    background: var(--light);
    color: var(--dark);
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s ease;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%23666' d='m2 0-2 2h4zm0 5 2-2h-4z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px 10px;
    padding-right: 35px;
}

.elegible-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
}

.elegible-select option {
    padding: 8px 12px;
    font-weight: 500;
}

/* Botón mejorado con precio dinámico */
.add-to-cart-btn {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.btn-text {
    flex: 1;
    text-align: left;
}

.btn-price {
    font-weight: 700;
    font-size: 1rem;
    padding: 2px 8px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

/* Mejoras responsive */
@media (max-width: 576px) {
    .product-options {
        padding: 6px 10px;
    }
    
    .options-text {
        font-size: 0.75rem;
    }
    
    .calories-badge {
        font-size: 0.75rem;
        padding: 3px 8px;
    }
    
    .elegible-select {
        font-size: 0.85rem;
        padding: 8px 10px;
        padding-right: 30px;
    }
    
    .btn-price {
        font-size: 0.9rem;
        padding: 1px 6px;
    }
}

/* Animaciones */
.product-options,
.product-calories {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

        /* Tabs */
        .tabs-container {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--light);
            border-bottom: 2px solid rgba(1, 101, 255, 0.1);
            margin: 0 -1rem 25px -1rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        }

        .nav-tabs {
            display: flex;
            overflow-x: auto;
            border: none;
            padding: 0 1rem;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .nav-tabs::-webkit-scrollbar {
            display: none;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--accent);
            font-weight: 600;
            padding: 18px 0;
            margin-right: 30px;
            position: relative;
            white-space: nowrap;
            background: transparent;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
        }

        .nav-tabs .nav-link.active::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
            border-radius: 4px 4px 0 0;
        }

        /* Menu Items */
        .menu-category {
            margin-bottom: 35px;
        }

        .category-title {
            font-size: 1.4rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(1, 101, 255, 0.1);
            color: var(--dark);
            font-weight: 700;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .col-md-6 {
            width: 100%;
            padding: 10px;
        }

        @media (min-width: 768px) {
            .col-md-6 {
                width: 50%;
            }
        }

        .menu-item {
            background: var(--light);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            height: 100%;
        }

        .menu-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
            border-color: rgba(1, 101, 255, 0.2);
        }

        .menu-item-inner {
            display: flex;
            padding: 20px;
            height: 100%;
            flex-direction: column;
        }

        .menu-item-img {
            width: 100%;
            height: 120px;
            border-radius: 15px;
            background: var(--gradient);
            background-size: cover;
            background-position: center;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--light);
            font-size: 2rem;
            overflow: hidden;
            position: relative;
        }

        .menu-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px;
        }

        .menu-item-img i {
            position: absolute;
            z-index: 1;
        }

        .menu-item-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .menu-item-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            font-size: 1.15rem;
        }

        .menu-item-desc {
            font-size: 0.9rem;
            color: var(--accent);
            margin-bottom: 12px;
            opacity: 0.8;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .menu-item-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            gap: 10px;
        }

        .quantity-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 2px solid var(--primary);
            background: var(--light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1.1rem;
        }

        .quantity-btn:hover {
            background: var(--primary);
            color: var(--light);
            transform: scale(1.1);
        }

        .quantity-input {
            width: 50px;
            height: 35px;
            border: 2px solid rgba(1, 101, 255, 0.2);
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            background: var(--light);
            font-size: 1rem;
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .add-to-cart-btn {
            background: var(--gradient);
            color: var(--light);
            border: none;
            border-radius: 25px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .add-to-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(1, 101, 255, 0.3);
        }

        /* Cart Preview */
        .cart-preview {
            position: fixed;
            bottom: 85px;
            left: 1rem;
            right: 1rem;
            background: var(--dark);
            border-radius: 20px;
            padding: 18px 22px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--light);
        }

        .cart-preview-info {
            display: flex;
            align-items: center;
        }

        .cart-preview-count {
            background: var(--primary);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 14px;
        }

        .cart-preview-total {
            font-weight: 600;
            font-size: 1.15rem;
        }

        .view-cart-btn {
            background: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 15px;
            padding: 12px 18px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .view-cart-btn:hover {
            background: var(--primary-dark);
            color: var(--light);
            transform: translateY(-2px);
        }

        /* Cart Toast */
        .cart-toast {
            position: fixed;
            bottom: 200px;
            left: 1rem;
            right: 1rem;
            background: var(--light);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1050;
            display: none;
            border-left: 6px solid var(--success);
        }

        .cart-toast.error {
            border-left-color: var(--danger);
        }

        .cart-toast-message {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .cart-toast-message i {
            margin-right: 12px;
            font-size: 1.3rem;
        }

        .cart-toast.success i {
            color: var(--success);
        }

        .cart-toast.error i {
            color: var(--danger);
        }

        .cart-toast-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .cart-toast-btn {
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        /* Info Blocks */
        .info-block {
            background: var(--light);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
            border: 2px solid rgba(1, 101, 255, 0.1);
        }

        .info-block h3 {
            color: var(--dark);
            margin-bottom: 18px;
            font-size: 1.3rem;
        }
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        /* Fondo semitransparente con efecto vidrio */
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        
        /* Sombra suave hacia arriba, sin borde duro */
        border-top: none;
        box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
        
        padding: 0.5rem 1.5rem;
        /* Importante para iPhones con FaceID (evita que tape la barra negra inferior) */
        padding-bottom: max(10px, env(safe-area-inset-bottom));
        
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        border-radius: 20px 20px 0 0; /* Opcional: puntas redondeadas arriba */
    }

    /* Items normales */
    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #0165f0; /* Gris suave inactivo */
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        flex: 1;
        gap: 4px;
        position: relative;
    }

.nav-item .nav-icon {
    width: 24px;
    height: 24px;
    object-fit: contain;
    transition: all 0.3s ease;
    
    /* ESTO ES LO QUE TE FALTA: */
    /* Convertimos a escala de grises y bajamos la opacidad al 40% */
    /* Esto hace que un icono negro se vea gris claro */
    filter: grayscale(100%) opacity(0.);
}

    .nav-item span {
        font-size: 0.65rem;
        font-weight: 500;
        transition: var(--transition);
    }

    /* Estado Activo (La magia de la animación) */
    .nav-item.active {
        color: var(--primary);
    }

.nav-item.active .nav-icon {
    transform: translateY(-2px);
    
    /* El "Filtro Mágico" que convierte el icono a azul #0165f0 */
    filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%);
    
    /* Importante: Restauramos la opacidad al 100% para que se vea vivo */
    opacity: 1;
}
    .nav-item.active span {
        font-weight: 700;
        color: var(--primary);
    }

    /* Efecto de punto debajo del activo (Estilo minimalista) */
    .nav-item.active::after {
        content: '';
        position: absolute;
        bottom: -5px; /* Ajustar según padding */
        width: 4px;
        height: 4px;
        background: var(--primary);
        border-radius: 50%;
    }

    /* BOTÓN CENTRAL FLOTANTE (EL CARRITO) */
    .central-btn {
        position: relative;
        /* Esto hace que suba */
        transform: translateY(-25px); 
        
        background: var(--primary); /* O un gradiente: var(--gradient) */
        width: 64px; /* Más grande */
        height: 64px;
        border-radius: 50%;
        
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Efecto rebote */
        
        /* El truco del "Recorte": Un borde grueso del mismo color que el fondo de la página */
        border: 5px solid var(--body-bg, #ffffff); /* Asegúrate que coincida con tu fondo */
        
        /* Sombra resplandeciente (Glow) */
        box-shadow: 0 10px 25px rgba(1, 101, 255, 0.4);
        z-index: 10;
    }

    .central-btn:active {
        transform: translateY(-22px) scale(0.95); /* Efecto al presionar */
        box-shadow: 0 5px 15px rgba(1, 101, 255, 0.3);
    }

    .central-btn .nav-icon {
        width: 28px;
        height: 28px;
        filter: brightness(0) invert(1); /* Blanco puro */
    }

    /* Hover effects (Solo PC) */
    @media (min-width: 768px) {
        .nav-item:hover {
            color: var(--primary);
        }
        .nav-item:hover .nav-icon {
            filter: grayscale(0%) opacity(0.8);
        }
        .central-btn:hover {
            transform: translateY(-30px) scale(1.05);
        }
    }
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 5rem;
            color: var(--primary);
            margin-bottom: 25px;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 12px;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--accent);
            opacity: 0.7;
            font-size: 1.1rem;
        }

        /* Tab Content */
        .tab-content {
            padding: 0;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* Bootstrap overrides */
        .btn {
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--gradient);
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-outline-secondary:hover {
            background: var(--accent);
            border-color: var(--accent);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        /* Focus states */
        button:focus,
        input:focus,
        select:focus,
        textarea:focus,
        a:focus {
            outline: 3px solid rgba(1, 101, 255, 0.3);
            outline-offset: 2px;
        }

        /* Smooth animations */
        * {
            transition: all 0.3s ease;
        }

        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Responsive Design */

        /* Tablets y pantallas medianas */
        @media (max-width: 768px) {
            .restaurant-header {
                height: 400px;
                margin-bottom: 10px;
            }

            .restaurant-info {
                bottom: -65px;
                padding: 55px 18px 18px 18px;
                border-radius: 20px;
                left: 0.75rem;
                right: 0.75rem;
                max-width: calc(100vw - 1.5rem);
            }

            .restaurant-logo {
                width: 70px;
                height: 70px;
                top: -35px;
                border-radius: 18px;
                font-size: 1.5rem;
            }

            .restaurant-name {
                font-size: 1.9rem;
                margin-top: 20px;
                margin-bottom: 10px;
            }

            .status-container {
                margin-bottom: 15px;
            }

            .status-tag {
                font-size: 0.85rem;
                padding: 7px 14px;
            }

            .restaurant-tags {
                margin-bottom: 15px;
                gap: 6px;
            }

            .tag {
                font-size: 0.8rem;
                padding: 5px 12px;
            }

            .restaurant-meta {
                font-size: 0.85rem;
                gap: 6px 15px;
            }

            .restaurant-meta span {
                font-size: 0.85rem;
            }

            .restaurant-meta i {
                font-size: 0.8rem;
            }

            .menu-item-inner {
                padding: 15px;
            }

            .menu-item-img {
                height: 100px;
            }

            .back-button,
            .action-button {
                width: 40px;
                height: 40px;
                top: 15px;
            }

            .back-button {
                left: 15px;
            }

            .action-buttons {
                right: 15px;
                gap: 10px;
            }

            .central-btn {
                width: 55px;
                height: 55px;
                top: -15px;
            }

            .col-md-6 {
                width: 100%;
            }
        }

        /* Móviles grandes */
        @media (max-width: 480px) {
            .container {
                padding: 0 0.5rem;
            }

            .restaurant-info {
                bottom: -25px;
                padding: 13px 16px 16px 16px;
                border-radius: 18px;
                left: 0.5rem;
                right: 0.5rem;
                max-width: calc(100vw - 1rem);
            }

            .restaurant-logo {
                width: 60px;
                height: 60px;
                top: -30px;
                border-radius: 15px;
                font-size: 1.3rem;
                border: 3px solid var(--light);
            }

            .restaurant-name {
                font-size: 1.6rem;
                margin-top: 18px;
                margin-bottom: 8px;
                line-height: 1.2;
            }

            .status-container {
                margin-bottom: 15px;
            }

            .status-tag {
                font-size: 0.8rem;
                padding: 6px 12px;
            }

            .restaurant-tags {
                margin-bottom: 12px;
                gap: 5px;
            }

            .tag {
                font-size: 0.75rem;
                padding: 4px 10px;
                border-radius: 20px;
            }

            .restaurant-meta {
                font-size: 0.8rem;
                gap: 4px 12px;
            }

            .restaurant-meta span {
                font-size: 0.8rem;
            }

            .restaurant-meta i {
                font-size: 0.75rem;
                margin-right: 5px;
            }

            .menu-item-inner {
                padding: 12px;
            }

            .menu-item-img {
                height: 90px;
            }

            .cart-preview {
                left: 0.5rem;
                right: 0.5rem;
                padding: 15px 18px;
            }

            .cart-toast {
                left: 0.5rem;
                right: 0.5rem;
            }
        }

        /* Móviles pequeños */
        @media (max-width: 360px) {
            .restaurant-info {
                bottom: -55px;
                padding: 45px 12px 14px 12px;
                border-radius: 16px;
                left: 0.375rem;
                right: 0.375rem;
                max-width: calc(100vw - 0.75rem);
            }

            .restaurant-logo {
                width: 55px;
                height: 55px;
                top: -27px;
                border-radius: 14px;
                font-size: 1.2rem;
            }

            .restaurant-name {
                font-size: 1.4rem;
                margin-top: 16px;
                margin-bottom: 6px;
            }

            .status-container {
                margin-bottom: 10px;
            }

            .status-tag {
                font-size: 0.75rem;
                padding: 5px 10px;
            }

            .restaurant-tags {
                margin-bottom: 10px;
                gap: 4px;
            }

            .tag {
                font-size: 0.7rem;
                padding: 3px 8px;
                border-radius: 18px;
            }

            .restaurant-meta {
                font-size: 0.75rem;
                gap: 3px 10px;
                flex-direction: column;
                align-items: center;
            }

            .restaurant-meta span {
                font-size: 0.75rem;
                margin-bottom: 2px;
            }
        }

        /* Pantallas ultra pequeñas */
        @media (max-width: 320px) {
            .restaurant-info {
                bottom: -50px;
                padding: 40px 10px 12px 10px;
                left: 0.25rem;
                right: 0.25rem;
                max-width: calc(100vw - 0.5rem);
            }

            .restaurant-logo {
                width: 50px;
                height: 50px;
                top: -25px;
                font-size: 1.1rem;
            }

            .restaurant-name {
                font-size: 1.3rem;
                margin-top: 14px;
                margin-bottom: 5px;
            }

            .status-tag {
                font-size: 0.7rem;
                padding: 4px 8px;
            }

            .tag {
                font-size: 0.65rem;
                padding: 2px 6px;
            }

            .restaurant-meta span {
                font-size: 0.7rem;
            }
        }

        /* Orientación landscape en móviles */
        @media (max-width: 768px) and (orientation: landscape) {
            .restaurant-info {
                bottom: -50px;
                padding: 45px 16px 16px 16px;
            }

            .restaurant-logo {
                width: 55px;
                height: 55px;
                top: -27px;
            }

            .restaurant-name {
                font-size: 1.5rem;
                margin-top: 16px;
            }

            .restaurant-meta {
                flex-direction: row;
                justify-content: space-around;
                gap: 4px 8px;
            }
        }

        /* Ajustes para mejorar la legibilidad */
        @media (prefers-reduced-motion: reduce) {
            .restaurant-info,
            .restaurant-logo,
            .tag,
            .status-tag,
            * {
                transition: none;
            }
        }

        /* Mejoras para accesibilidad */
        @media (prefers-contrast: high) {
            .restaurant-info {
                border: 3px solid var(--primary);
            }
            
            .tag {
                border: 2px solid var(--primary);
            }
            
            .status-tag {
                border: 2px solid currentColor;
            }
        }

        /* Optimización para pantallas de alta densidad */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 2dppx) {
            .restaurant-logo {
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }
            
            .restaurant-info {
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            }
        }

        /* Mapbox overrides */
        .mapboxgl-popup-content {
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .mapboxgl-popup-anchor-bottom .mapboxgl-popup-tip {
            border-top-color: white;
        }

        .mapboxgl-ctrl-group {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 768px) {
    .menu-item-inner {
        padding: 15px;
    }

    .menu-item-img {
        height: 140px; /* Aumentado de 100px a 140px para mejor visibilidad */
    }
}

@media (max-width: 480px) {
    .menu-item-inner {
        padding: 12px;
    }

    .menu-item-img {
        height: 120px; /* Aumentado de 90px a 120px */
    }
}

@media (max-width: 360px) {
    .menu-item-img {
        height: 110px; /* Aumentado de 90px a 110px */
    }
}

/* Agregar al final del <style> */
.bottom-nav {
    transition: transform 0.3s ease;
}

.bottom-nav.hidden {
    transform: translateY(100%);
}

/* ============================================
   MODO OSCURO - NEGOCIO.PHP
   ============================================ */

/* Variables de modo oscuro */
[data-theme="dark"],
html.dark-mode {
    --light: #000000;
    --dark: #ffffff;
    --secondary: #111111;
    --accent: #e2e8f0;
}

/* Fondo y texto general */
[data-theme="dark"] body,
html.dark-mode body {
    background-color: #000000 !important;
    color: #ffffff;
}

/* Tarjeta de información del restaurante */
[data-theme="dark"] .restaurant-info,
html.dark-mode .restaurant-info {
    background: #111111 !important;
    border: 1px solid #333 !important;
    color: #ffffff;
}

[data-theme="dark"] .restaurant-info h1,
[data-theme="dark"] .restaurant-info h2,
[data-theme="dark"] .restaurant-info h3,
[data-theme="dark"] .restaurant-info p,
html.dark-mode .restaurant-info h1,
html.dark-mode .restaurant-info h2,
html.dark-mode .restaurant-info h3,
html.dark-mode .restaurant-info p {
    color: #ffffff !important;
}

/* Tabs de navegación */
[data-theme="dark"] .tabs-container,
html.dark-mode .tabs-container {
    background: #111111 !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .tab-btn,
html.dark-mode .tab-btn {
    color: #aaaaaa !important;
}

[data-theme="dark"] .tab-btn.active,
html.dark-mode .tab-btn.active {
    color: var(--primary) !important;
}

/* Tarjetas de productos del menú */
[data-theme="dark"] .menu-item,
[data-theme="dark"] .menu-item-inner,
html.dark-mode .menu-item,
html.dark-mode .menu-item-inner {
    background: #111111 !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .menu-item h3,
[data-theme="dark"] .menu-item h4,
[data-theme="dark"] .menu-item-name,
[data-theme="dark"] .menu-item-price,
html.dark-mode .menu-item h3,
html.dark-mode .menu-item h4,
html.dark-mode .menu-item-name,
html.dark-mode .menu-item-price {
    color: #ffffff !important;
}

[data-theme="dark"] .menu-item-description,
html.dark-mode .menu-item-description {
    color: #aaaaaa !important;
}

/* Secciones de categorías */
[data-theme="dark"] .category-title,
[data-theme="dark"] .menu-category h2,
html.dark-mode .category-title,
html.dark-mode .menu-category h2 {
    color: #ffffff !important;
}

/* Tags y badges */
[data-theme="dark"] .tag,
html.dark-mode .tag {
    background: rgba(1, 101, 255, 0.2) !important;
    color: #ffffff !important;
    border: 1px solid #333 !important;
}

/* Controles de cantidad */
[data-theme="dark"] .quantity-btn,
html.dark-mode .quantity-btn {
    background: #222222 !important;
    border: 1px solid #444 !important;
    color: #ffffff !important;
}

[data-theme="dark"] .quantity-display,
html.dark-mode .quantity-display {
    background: #333333 !important;
    color: #ffffff !important;
}

/* Modal de producto */
[data-theme="dark"] .modal-content,
[data-theme="dark"] .product-modal-content,
html.dark-mode .modal-content,
html.dark-mode .product-modal-content {
    background: #111111 !important;
    border: 1px solid #333 !important;
    color: #ffffff !important;
}

[data-theme="dark"] .modal-header,
[data-theme="dark"] .modal-footer,
html.dark-mode .modal-header,
html.dark-mode .modal-footer {
    border-color: #333 !important;
}

[data-theme="dark"] .modal-title,
html.dark-mode .modal-title {
    color: #ffffff !important;
}

/* Botón cerrar modal - siempre blanco */
.btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

[data-theme="dark"] .btn-close,
html.dark-mode .btn-close {
    filter: invert(1);
}   

/* Información del negocio (tab información) */
[data-theme="dark"] .info-card,
[data-theme="dark"] .info-section,
html.dark-mode .info-card,
html.dark-mode .info-section {
    background: #111111 !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .info-card h3,
[data-theme="dark"] .info-card h4,
[data-theme="dark"] .info-card p,
[data-theme="dark"] .info-card span,
html.dark-mode .info-card h3,
html.dark-mode .info-card h4,
html.dark-mode .info-card p,
html.dark-mode .info-card span {
    color: #ffffff !important;
}

/* Reseñas */
[data-theme="dark"] .review-card,
html.dark-mode .review-card {
    background: #111111 !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .review-card p,
[data-theme="dark"] .review-author,
html.dark-mode .review-card p,
html.dark-mode .review-author {
    color: #ffffff !important;
}

/* Navegación inferior */
[data-theme="dark"] .bottom-nav,
html.dark-mode .bottom-nav {
    background: rgba(0, 0, 0, 0.95) !important;
    border-top: 1px solid #333 !important;
}

[data-theme="dark"] .nav-item,
html.dark-mode .nav-item {
    color: #888888 !important;
}

[data-theme="dark"] .nav-item.active,
html.dark-mode .nav-item.active {
    color: var(--primary) !important;
}

/* Icono hamburguesa placeholder */
[data-theme="dark"] .menu-item-img.placeholder-img,
html.dark-mode .menu-item-img.placeholder-img {
    background: #1a1a1a !important;
}

/* Scroll to top y otros botones flotantes */
[data-theme="dark"] .back-btn,
[data-theme="dark"] .share-btn,
[data-theme="dark"] .favorite-btn,
html.dark-mode .back-btn,
html.dark-mode .share-btn,
html.dark-mode .favorite-btn {
    background: #222222 !important;
    border: 1px solid #444 !important;
    color: #ffffff !important;
}

/* Mapbox en modo oscuro */
[data-theme="dark"] .mapboxgl-popup-content,
html.dark-mode .mapboxgl-popup-content {
    background: #111111 !important;
    color: #ffffff !important;
}

/* Iconos del navbar - BLANCOS en modo oscuro */
[data-theme="dark"] .nav-icon,
html.dark-mode .nav-icon {
    filter: invert(1) brightness(2) !important;
}

[data-theme="dark"] .nav-item.active .nav-icon,
html.dark-mode .nav-item.active .nav-icon {
    filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%) !important;
}

[data-theme="dark"] .central-btn,
html.dark-mode .central-btn {
    border-color: #000 !important;
}

[data-theme="dark"] .central-btn .nav-icon,
html.dark-mode .central-btn .nav-icon {
    filter: brightness(0) invert(1) !important;
}

[data-theme="dark"] .nav-item span,
html.dark-mode .nav-item span {
    color: #fff !important;
}

[data-theme="dark"] .nav-item.active span,
html.dark-mode .nav-item.active span {
    color: var(--primary) !important;
}

/* Nav Tabs (Menú, Información, Reseñas) en modo oscuro */
[data-theme="dark"] .nav-tabs .nav-link,
html.dark-mode .nav-tabs .nav-link {
    color: #ffffff !important;
}

[data-theme="dark"] .nav-tabs .nav-link:hover,
html.dark-mode .nav-tabs .nav-link:hover {
    color: var(--primary) !important;
}

[data-theme="dark"] .nav-tabs .nav-link.active,
html.dark-mode .nav-tabs .nav-link.active {
    color: var(--primary) !important;
}

[data-theme="dark"] .tabs-container,
html.dark-mode .tabs-container {
    background: #111111 !important;
    border-bottom: 1px solid #333 !important;
}

/* Cart Toast */
[data-theme="dark"] .cart-toast,
html.dark-mode .cart-toast {
    background: #111111 !important;
    border: 1px solid #333 !important;
    color: #ffffff !important;
}

[data-theme="dark"] .cart-toast-message,
html.dark-mode .cart-toast-message {
    color: #ffffff !important;
}

/* Info Block */
[data-theme="dark"] .info-block,
html.dark-mode .info-block {
    background: #111111 !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .info-block h3,
[data-theme="dark"] .info-block p,
html.dark-mode .info-block h3,
html.dark-mode .info-block p {
    color: #ffffff !important;
}

/* Empty State */
[data-theme="dark"] .empty-state,
html.dark-mode .empty-state {
    background: transparent !important;
}

[data-theme="dark"] .empty-state h3,
html.dark-mode .empty-state h3 {
    color: #ffffff !important;
}

[data-theme="dark"] .empty-state p,
html.dark-mode .empty-state p {
    color: #aaaaaa !important;
}

/* Opciones de producto */
[data-theme="dark"] .product-options,
html.dark-mode .product-options {
    background: linear-gradient(135deg, rgba(1, 101, 255, 0.2), rgba(1, 101, 255, 0.1)) !important;
    border-left: 3px solid var(--primary) !important;
}

[data-theme="dark"] .options-text,
html.dark-mode .options-text {
    color: #e2e8f0 !important;
}

/* Badge de calorías */
[data-theme="dark"] .calories-badge,
html.dark-mode .calories-badge {
    background: linear-gradient(135deg, rgba(255, 149, 0, 0.25), rgba(255, 149, 0, 0.15)) !important;
    color: #ffb347 !important;
    border: 1px solid rgba(255, 149, 0, 0.3) !important;
}

/* Selector de elegibles */
[data-theme="dark"] .elegible-select,
html.dark-mode .elegible-select {
    background: #1a1a1a !important;
    color: #ffffff !important;
    border: 2px solid #333 !important;
}

[data-theme="dark"] .elegible-select:focus,
html.dark-mode .elegible-select:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.2) !important;
}

[data-theme="dark"] .elegible-select option,
html.dark-mode .elegible-select option {
    background: #1a1a1a !important;
    color: #ffffff !important;
}

/* Input de cantidad */
[data-theme="dark"] .quantity-input,
html.dark-mode .quantity-input {
    background: #1a1a1a !important;
    color: #ffffff !important;
    border: 2px solid #333 !important;
}

[data-theme="dark"] .quantity-input:focus,
html.dark-mode .quantity-input:focus {
    border-color: var(--primary) !important;
}

/* Botones de navegación superior */
[data-theme="dark"] .back-button,
html.dark-mode .back-button {
    background: rgba(30, 30, 30, 0.95) !important;
    color: #ffffff !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .back-button:hover,
html.dark-mode .back-button:hover {
    background: #222222 !important;
    color: var(--primary) !important;
}

[data-theme="dark"] .action-button,
html.dark-mode .action-button {
    background: rgba(30, 30, 30, 0.95) !important;
    color: #ffffff !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .action-button:hover,
html.dark-mode .action-button:hover {
    background: #222222 !important;
}

[data-theme="dark"] .action-button.favorite.active,
html.dark-mode .action-button.favorite.active {
    background: var(--danger) !important;
    color: #ffffff !important;
}

/* Títulos y descripciones de productos */
[data-theme="dark"] .menu-item-title,
html.dark-mode .menu-item-title {
    color: #ffffff !important;
}

[data-theme="dark"] .menu-item-desc,
html.dark-mode .menu-item-desc {
    color: #aaaaaa !important;
}

/* Precio del botón agregar */
[data-theme="dark"] .btn-price,
html.dark-mode .btn-price {
    background: rgba(255, 255, 255, 0.15) !important;
}

/* Restaurant name y meta */
[data-theme="dark"] .restaurant-name,
html.dark-mode .restaurant-name {
    color: #ffffff !important;
}

[data-theme="dark"] .restaurant-meta,
[data-theme="dark"] .restaurant-meta span,
html.dark-mode .restaurant-meta,
html.dark-mode .restaurant-meta span {
    color: #e2e8f0 !important;
}

/* Logo del restaurante */
[data-theme="dark"] .restaurant-logo,
html.dark-mode .restaurant-logo {
    border-color: #222222 !important;
}

/* Cart preview */
[data-theme="dark"] .cart-preview,
html.dark-mode .cart-preview {
    background: #111111 !important;
    border: 1px solid #333 !important;
}

/* Reviews summary */
[data-theme="dark"] .reviews-summary,
html.dark-mode .reviews-summary {
    background: #1a1a1a !important;
    border: 1px solid #333 !important;
}

[data-theme="dark"] .reviews-summary small,
[data-theme="dark"] .reviews-summary .text-muted,
html.dark-mode .reviews-summary small,
html.dark-mode .reviews-summary .text-muted {
    color: #aaaaaa !important;
}

/* Review items */
[data-theme="dark"] .review-item,
html.dark-mode .review-item {
    border-bottom-color: #333 !important;
}

[data-theme="dark"] .review-item p,
[data-theme="dark"] .review-item strong,
html.dark-mode .review-item p,
html.dark-mode .review-item strong {
    color: #ffffff !important;
}

[data-theme="dark"] .review-item .text-muted,
html.dark-mode .review-item .text-muted {
    color: #aaaaaa !important;
}

/* Respuesta del negocio */
[data-theme="dark"] .respuesta-negocio,
html.dark-mode .respuesta-negocio {
    background: rgba(1, 101, 255, 0.15) !important;
    border-left: 3px solid var(--primary) !important;
}

[data-theme="dark"] .respuesta-negocio p,
html.dark-mode .respuesta-negocio p {
    color: #e2e8f0 !important;
}

/* Info item text-muted */
[data-theme="dark"] .info-item .text-muted,
[data-theme="dark"] .info-block .text-muted,
html.dark-mode .info-item .text-muted,
html.dark-mode .info-block .text-muted {
    color: #888888 !important;
}

/* Form labels */
[data-theme="dark"] .form-label,
[data-theme="dark"] .elegibles-selector label,
html.dark-mode .form-label,
html.dark-mode .elegibles-selector label {
    color: #ffffff !important;
}

/* Progress bar background in reviews */
[data-theme="dark"] .reviews-summary [style*="background: #e9ecef"],
html.dark-mode .reviews-summary [style*="background: #e9ecef"] {
    background: #333333 !important;
}

/* Stars inactive color */
[data-theme="dark"] .fa-star[style*="color: #e9ecef"],
html.dark-mode .fa-star[style*="color: #e9ecef"] {
    color: #444444 !important;
}

@media (prefers-color-scheme: dark) {
    .nav-icon {
        filter: invert(1) brightness(2) !important;
    }

    .nav-item.active .nav-icon {
        filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%) !important;
    }

    .central-btn {
        border-color: #000 !important;
    }

    .central-btn .nav-icon {
        filter: brightness(0) invert(1) !important;
    }

    .nav-item span {
        color: #fff !important;
    }

    .nav-item.active span {
        color: var(--primary) !important;
    }

    /* Nav Tabs en auto dark mode */
    .nav-tabs .nav-link {
        color: #ffffff !important;
    }

    .nav-tabs .nav-link:hover,
    .nav-tabs .nav-link.active {
        color: var(--primary) !important;
    }

    .tabs-container {
        background: #111111 !important;
        border-bottom: 1px solid #333 !important;
    }
}
</style>
<body>
<?php include_once 'includes/valentine.php'; ?>
<script>
// Ocultar/mostrar bottom nav al hacer scroll
document.addEventListener('DOMContentLoaded', function() {
    let lastScrollTop = 0;
    const bottomNav = document.querySelector('.bottom-nav');

    if (bottomNav) {
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > lastScrollTop && scrollTop > 100) {
                bottomNav.classList.add('hidden');
            } else {
                bottomNav.classList.remove('hidden');
            }

            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        });
    }
});
</script>
    <div class="container">
        <!-- Restaurant Header -->
        <div class="restaurant-header">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="action-buttons">
                <a href="#" class="action-button share-btn" title="Compartir">
                    <i class="fas fa-share-alt"></i>
                </a>
            <a href="#" class="action-button favorite-btn" title="Favorito">
                <?php if ($negocio_favorito): ?>
                    <i class="fas fa-heart"></i>
                <?php else: ?>
                    <i class="far fa-heart"></i>
                <?php endif; ?>
            </a>
            </div>
            <div class="restaurant-cover" style="background-image: url('<?php echo !empty($negocio->imagen_portada) ? htmlspecialchars($negocio->imagen_portada) : 'assets/img/restaurants/portada_67f47941b4e37_logogaudi.jpg'; ?>');">
            </div>
            <div class="restaurant-info">
    <div class="restaurant-logo" style="background-image: url('<?php echo !empty($negocio->logo) ? htmlspecialchars($negocio->logo) : 'assets/img/restaurants/logo_67f47941b4b5a_gaudi.jpg'; ?>');">
        <?php if (empty($negocio->logo)): ?>
            <i class="fas fa-utensils"></i>
        <?php endif; ?>
    </div>
    
    <h1 class="restaurant-name">
        <?php echo htmlspecialchars($negocio->nombre); ?>
    </h1>
    <div class="restaurant-tags">
        <?php
        // Mapeo de categorías a etiquetas legibles
        $categoria_tags = [
            'restaurante' => ['Restaurante', 'Comida'],
            'comida_rapida' => ['Comida rápida', 'Fast Food'],
            'cafeteria' => ['Cafetería', 'Bebidas'],
            'panaderia' => ['Panadería', 'Repostería'],
            'floreria' => ['Floristería', 'Ramos y Arreglos'],
            'farmacia' => ['Farmacia', 'Salud'],
            'abarrotes' => ['Abarrotes', 'Tienda'],
            'otro' => ['Tienda', 'Productos']
        ];
        $cat = $negocio->categoria_negocio ?? 'restaurante';
        $tags = $categoria_tags[$cat] ?? $categoria_tags['otro'];
        ?>
        <span class="tag"><?php echo htmlspecialchars($tags[0]); ?></span>
        <span class="tag"><?php echo htmlspecialchars($tags[1]); ?></span>
        <?php if ($negocio->membresia_premium || $negocio->es_premium): ?>
            <span class="tag tag-premium"><i class="fas fa-crown"></i> Premium</span>
        <?php endif; ?>
    </div>

    <div class="status-container">
        <?php if ($esta_abierto): ?>
            <span class="status-tag-dynamic open">
                <span class="status-pulse active"></span>
                <span>Abierto ahora</span>
            </span>
        <?php else: ?>
            <?php
            // Calcular proxima apertura
            $proxima_apertura = '';
            $dia_actual = (int)date('w');
            $hora_actual = date('H:i:s');

            // Buscar el proximo horario de apertura
            for ($i = 0; $i < 7; $i++) {
                $dia_buscar = ($dia_actual + $i) % 7;
                foreach ($horarios_formateados as $horario) {
                    $dias_map = ['Domingo' => 0, 'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];
                    if (isset($dias_map[$horario['dia']]) && $dias_map[$horario['dia']] == $dia_buscar && $horario['activo']) {
                        $hora_apertura = $horario['hora_apertura'];
                        if ($i == 0 && $hora_actual < $hora_apertura . ':00') {
                            // Abre hoy mas tarde
                            $proxima_apertura = 'Abre hoy a las ' . $hora_apertura;
                            break 2;
                        } elseif ($i == 1) {
                            $proxima_apertura = 'Abre mañana a las ' . $hora_apertura;
                            break 2;
                        } elseif ($i > 0) {
                            $proxima_apertura = 'Abre el ' . $horario['dia'] . ' a las ' . $hora_apertura;
                            break 2;
                        }
                    }
                }
            }
            if (empty($proxima_apertura)) {
                $proxima_apertura = 'Cerrado temporalmente';
            }
            ?>
            <span class="status-tag-dynamic closed">
                <span class="status-pulse inactive"></span>
                <span><?php echo $proxima_apertura; ?></span>
            </span>
        <?php endif; ?>
    </div>
    
</div>
        </div>

        <!-- Cart Toast -->
        <?php if (!empty($mensaje_carrito)): ?>
        <div id="cart-toast" class="cart-toast <?php echo $producto_agregado ? 'success' : 'error'; ?>" style="display: block;">
            <div class="cart-toast-message">
                <i class="fas <?php echo $producto_agregado ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <div><?php echo htmlspecialchars($mensaje_carrito); ?></div>
            </div>
            <?php if (!$producto_agregado && strpos($mensaje_carrito, "otro restaurante") !== false): ?>
                <div class="cart-toast-actions">
                    <button onclick="clearCartAndAddProduct()" class="btn btn-sm btn-primary cart-toast-btn">Vaciar carrito</button>
                    <button onclick="hideCartToast()" class="btn btn-sm btn-outline-secondary cart-toast-btn">Cancelar</button>
                </div>
            <?php else: ?>
                <div class="cart-toast-actions">
                    <button onclick="hideCartToast()" class="btn btn-sm btn-outline-secondary cart-toast-btn">Cerrar</button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Cart Preview -->
        <?php if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito']['items']) && $_SESSION['carrito']['negocio_id'] == $negocio->id_negocio): ?>
        <div class="cart-preview">
            <div class="cart-preview-info">
                <div class="cart-preview-count">
                    <?php 
                    $total_items = 0;
                    foreach ($_SESSION['carrito']['items'] as $item) {
                        $total_items += $item['cantidad'];
                    }
                    echo $total_items;
                    ?>
                </div>
                <div class="cart-preview-total">
                    $<?php
                    $subtotal = 0;
                    foreach ($_SESSION['carrito']['items'] as $item) {
                        $subtotal += $item['precio'] * $item['cantidad'];
                    }
                    echo number_format($subtotal, 2);
                    ?>
                </div>
            </div>
            <a href="carrito.php" class="view-cart-btn">Ver carrito</a>
        </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <ul class="nav nav-tabs" id="restaurantTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="menu-tab" data-bs-toggle="tab" data-bs-target="#menu" type="button" role="tab" aria-controls="menu" aria-selected="true">Menú</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="false">Información</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">Reseñas</button>
                </li>
            </ul>
        </div>

        <!-- Categories Navigation (debajo del header) -->
        <?php if (!empty($productos_por_categoria)): ?>
        <nav class="categories-sticky-nav" id="categoriesNav">
            <div class="categories-scroll-container">
                <?php foreach ($productos_por_categoria as $index => $cat): ?>
                    <a href="#category-<?php echo $cat['id_categoria']; ?>"
                       class="category-chip <?php echo $index === 0 ? 'active' : ''; ?>"
                       data-category="<?php echo $cat['id_categoria']; ?>">
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                        <span class="count"><?php echo count($cat['productos']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php endif; ?>

        <!-- Tab Content -->
        <div class="tab-content" id="restaurantTabsContent">
            <!-- Menu Tab -->
            <div class="tab-pane fade show active" id="menu" role="tabpanel" aria-labelledby="menu-tab">
                <?php if (empty($productos_por_categoria)): ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>No hay productos disponibles</h3>
                        <p>Este restaurante aún no ha agregado productos a su menú.</p>
                    </div>
                <?php else: ?>
                    <!-- Seccion Favoritos del Publico / Recomendados -->
                    <?php if (!empty($productos_destacados)): ?>
                    <div class="featured-section">
                        <div class="featured-section-header">
                            <h3 class="featured-section-title">
                                <i class="fas fa-fire fire-icon"></i> Favoritos del público
                            </h3>
                        </div>
                        <div class="featured-scroll-container">
                            <?php foreach ($productos_destacados as $prod_dest): ?>
                            <div class="featured-product-card"
                                 onclick="openProductModal(productosData[<?php echo $prod_dest['id_producto']; ?>])"
                                 data-product-id="<?php echo $prod_dest['id_producto']; ?>">
                                <div class="featured-product-image">
                                    <?php if (!empty($prod_dest['destacado'])): ?>
                                    <span class="featured-product-badge">
                                        <i class="fas fa-star"></i> Popular
                                    </span>
                                    <?php endif; ?>
                                    <img src="<?php echo htmlspecialchars($prod_dest['imagen'] ?? 'assets/img/placeholder-product.png'); ?>"
                                         alt="<?php echo htmlspecialchars($prod_dest['nombre']); ?>">
                                </div>
                                <div class="featured-product-content">
                                    <div class="featured-product-name"><?php echo htmlspecialchars($prod_dest['nombre']); ?></div>
                                    <div class="featured-product-price">$<?php echo number_format($prod_dest['precio'], 2); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($productos_por_categoria as $categoria): ?>
                        <div class="menu-category" id="category-<?php echo $categoria['id_categoria']; ?>">
                            <h2 class="category-title"><?php echo htmlspecialchars($categoria['nombre']); ?></h2>
                            <div class="products-grid">
                               <?php foreach ($categoria['productos'] as $producto):
                                    // Calcular descuento si existe precio_original
                                    $tiene_descuento = !empty($producto['precio_original']) && $producto['precio_original'] > $producto['precio'];
                                    $porcentaje_descuento = 0;
                                    if ($tiene_descuento) {
                                        $porcentaje_descuento = round((($producto['precio_original'] - $producto['precio']) / $producto['precio_original']) * 100);
                                    }
                               ?>
    <div class="product-grid-item">
        <div class="menu-item product-card-premium" data-product-id="<?php echo $producto['id_producto']; ?>">
            <div class="menu-item-inner">
                <div class="menu-item-img product-image-container">
                    <?php if ($tiene_descuento): ?>
                        <span class="discount-badge">-<?php echo $porcentaje_descuento; ?>%</span>
                    <?php endif; ?>

                    <?php if (!empty($producto['imagen'])): ?>
                        <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                    <?php else: ?>
                        <?php
                        // Icono fallback según categoría del negocio
                        $iconos_categoria = [
                            'restaurante' => 'fa-utensils',
                            'comida_rapida' => 'fa-hamburger',
                            'cafeteria' => 'fa-coffee',
                            'panaderia' => 'fa-bread-slice',
                            'floreria' => 'fa-seedling',
                            'farmacia' => 'fa-pills',
                            'abarrotes' => 'fa-shopping-basket',
                            'otro' => 'fa-box'
                        ];
                        $icono = $iconos_categoria[$negocio->categoria_negocio ?? 'restaurante'] ?? 'fa-box';
                        ?>
                        <i class="fas <?php echo $icono; ?>"></i>
                    <?php endif; ?>

                    <!-- Quick Add Button -->
                    <button type="button" class="quick-add-btn"
                            onclick="quickAddToCart(<?php echo $producto['id_producto']; ?>, this)"
                            data-product-id="<?php echo $producto['id_producto']; ?>"
                            title="Agregar al carrito">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="menu-item-content product-content">
                    <h3 class="menu-item-title product-name"><?php echo htmlspecialchars($producto['nombre']); ?></h3>

                    <!-- Mostrar elegibles si existen -->
                    <?php if ($producto['tiene_elegibles'] && !empty($producto['elegibles'])): ?>
                        <div class="product-options mb-2">
                            <small class="text-muted d-block mb-1">
                                <i class="fas fa-list-ul"></i> Opciones disponibles:
                            </small>
                            <div class="options-list">
                                <?php
                                $elegibles_disponibles = array_filter($producto['elegibles'], function($e) { return $e['disponible']; });
                                $elegibles_nombres = array_map(function($e) {
                                    return $e['nombre'] . ($e['precio_adicional'] > 0 ? ' (+$' . number_format($e['precio_adicional'], 2) . ')' : '');
                                }, array_slice($elegibles_disponibles, 0, 3));
                                ?>
                                <small class="options-text">
                                    <?php echo implode(', ', $elegibles_nombres); ?>
                                    <?php if (count($elegibles_disponibles) > 3): ?>
                                        <span class="text-primary">y <?php echo count($elegibles_disponibles) - 3; ?> más...</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="menu-item-desc product-description"><?php echo htmlspecialchars($producto['descripcion'] ?? 'Deliciosa opción disponible en nuestro menú'); ?></p>

                    <!-- Mostrar calorías si están disponibles -->
                    <?php if (!empty($producto['calorias'])): ?>
                        <div class="product-calories mb-2">
                            <small class="calories-badge">
                                <i class="fas fa-fire"></i> <?php echo $producto['calorias']; ?> cal
                            </small>
                        </div>
                    <?php endif; ?>

                    <!-- Precios con descuento -->
                    <div class="product-pricing">
                        <span class="price-current">$<?php echo number_format($producto['precio'], 2); ?></span>
                        <?php if ($tiene_descuento): ?>
                            <span class="price-original">$<?php echo number_format($producto['precio_original'], 2); ?></span>
                            <span class="price-discount-percent">-<?php echo $porcentaje_descuento; ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="negocio.php?id=<?php echo $id_negocio; ?>" class="add-to-cart-form">
                        <input type="hidden" name="agregar_carrito" value="1">
                        <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                        
                        <!-- Selector de elegibles si existen -->
                        <?php if ($producto['tiene_elegibles'] && !empty($elegibles_disponibles)): ?>
                            <div class="elegibles-selector mb-3">
                                <label class="form-label" style="font-size: 0.9rem; font-weight: 600; color: var(--dark);">
                                    Elige tu opción:
                                </label>
                                <select name="elegible_id" class="form-select elegible-select" required>
                                    <option value="">Selecciona una opción</option>
                                    <?php foreach ($elegibles_disponibles as $elegible): ?>
                                        <option value="<?php echo $elegible['id_elegible']; ?>" 
                                                data-precio="<?php echo $elegible['precio_adicional']; ?>">
                                            <?php echo htmlspecialchars($elegible['nombre']); ?>
                                            <?php if ($elegible['precio_adicional'] > 0): ?>
                                                (+$<?php echo number_format($elegible['precio_adicional'], 2); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="quantity-control">
                            <button type="button" class="quantity-btn" onclick="this.nextElementSibling.stepDown(); if(this.nextElementSibling.value < 1) this.nextElementSibling.value = 1; updatePrice(this.closest('.menu-item'));">-</button>
                            <input type="number" name="cantidad" class="quantity-input" value="1" min="1" max="10" onchange="updatePrice(this.closest('.menu-item'));">
                            <button type="button" class="quantity-btn" onclick="this.previousElementSibling.stepUp(); if(this.previousElementSibling.value > 10) this.previousElementSibling.value = 10; updatePrice(this.closest('.menu-item'));">+</button>
                        </div>
                        
                        <button type="submit" class="add-to-cart-btn">
                            <i class="fas fa-cart-plus me-1"></i> 
                            <span class="btn-text">Agregar al carrito</span>
                            <span class="btn-price" style="margin-left: auto;">$<?php echo number_format($producto['precio'], 2); ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>    
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Info Tab -->
            <div class="tab-pane fade" id="info" role="tabpanel" aria-labelledby="info-tab">
                <!-- Delivery Info Banner -->
                <div class="delivery-info-banner">
                    <div class="delivery-info-card">
                        <div class="delivery-info-icon time">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="delivery-info-text">
                            <div class="delivery-info-label">Tiempo de entrega</div>
                            <div class="delivery-info-value">
                                <?php echo !empty($negocio->tiempo_preparacion_promedio) ? $negocio->tiempo_preparacion_promedio . ' min' : '25-40 min'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="delivery-info-card">
                        <div class="delivery-info-icon cost">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <div class="delivery-info-text">
                            <div class="delivery-info-label">Costo de envío</div>
                            <div class="delivery-info-value <?php echo (isset($negocio->costo_envio) && $negocio->costo_envio == 0) ? 'free' : ''; ?>">
                                <?php
                                if (isset($negocio->costo_envio) && $negocio->costo_envio > 0) {
                                    echo '$' . number_format($negocio->costo_envio, 2);
                                } else {
                                    echo '<i class="fas fa-gift me-1"></i> Gratis';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-block">
                    <h3>Sobre <?php echo htmlspecialchars($negocio->nombre); ?></h3>
                    <p><?php echo htmlspecialchars($negocio->descripcion ?? 'Restaurante con deliciosa comida y excelente servicio.'); ?></p>
                </div>

                <div class="info-block">
                    <h3>Horarios</h3>
                    <div class="info-item">
                        <?php foreach ($horarios_formateados as $horario): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><strong><?php echo htmlspecialchars($horario['dia']); ?></strong></span>
                                <span>
                                    <?php if ($horario['activo']): ?>
                                        <?php echo htmlspecialchars($horario['hora_apertura']); ?> - <?php echo htmlspecialchars($horario['hora_cierre']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Cerrado</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="info-block">
                    <h3>Dirección</h3>
                    <div class="info-item">
                        <p><?php echo htmlspecialchars(($negocio->calle ?? '') . ' ' . ($negocio->numero ?? '') . ', ' . ($negocio->colonia ?? '') . ', ' . ($negocio->ciudad ?? '')); ?></p>
                        <div id="map-container" style="height: 250px; border-radius: 15px; margin-top: 15px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                            <div id="map" style="width: 100%; height: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="info-block">
                    <h3>Contacto</h3>
                    <div class="info-item">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Teléfono:</strong></span>
                            <span><?php echo htmlspecialchars($negocio->telefono ?? 'No disponible'); ?></span>
                        </div>
                        <?php if (!empty($negocio->email)): ?>
                        <div class="d-flex justify-content-between">
                            <span><strong>Email:</strong></span>
                            <span><?php echo htmlspecialchars($negocio->email); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Reviews Tab -->
            <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                <?php if (!empty($resenas_negocio) && $stats_resenas['total'] > 0): ?>
                <!-- Resumen de calificaciones -->
                <div class="reviews-summary" style="background: #f8f9fa; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="text-center">
                            <div style="font-size: 3rem; font-weight: 700; color: var(--primary);">
                                <?php echo number_format($stats_resenas['promedio'] ?? 0, 1); ?>
                            </div>
                            <div class="stars-display mb-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?php echo $i <= round($stats_resenas['promedio']) ? '#FFD700' : '#e9ecef'; ?>;"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted"><?php echo $stats_resenas['total']; ?> reseñas</small>
                        </div>
                        <div class="flex-grow-1" style="min-width: 200px;">
                            <?php
                            $barras = [
                                ['label' => '5', 'count' => $stats_resenas['cinco'] ?? 0],
                                ['label' => '4', 'count' => $stats_resenas['cuatro'] ?? 0],
                                ['label' => '3', 'count' => $stats_resenas['tres'] ?? 0],
                                ['label' => '2', 'count' => $stats_resenas['dos'] ?? 0],
                                ['label' => '1', 'count' => $stats_resenas['una'] ?? 0],
                            ];
                            $max_count = max(array_column($barras, 'count'));
                            foreach ($barras as $barra):
                                $porcentaje = $max_count > 0 ? ($barra['count'] / $stats_resenas['total']) * 100 : 0;
                            ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <small style="width: 15px;"><?php echo $barra['label']; ?></small>
                                <div class="flex-grow-1" style="background: #e9ecef; height: 8px; border-radius: 4px;">
                                    <div style="background: #FFD700; height: 100%; border-radius: 4px; width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                                <small class="text-muted" style="width: 30px;"><?php echo $barra['count']; ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Lista de resenas -->
                <div class="reviews-list">
                    <?php foreach ($resenas_negocio as $resena): ?>
                    <div class="review-item" style="border-bottom: 1px solid #eee; padding: 1rem 0;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($resena['nombre_usuario'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($resena['nombre_usuario']); ?></strong>
                                    <div class="stars-small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="font-size: 0.75rem; color: <?php echo $i <= $resena['calificacion_negocio'] ? '#FFD700' : '#e9ecef'; ?>;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted"><?php echo $resena['fecha_formateada']; ?></small>
                        </div>
                        <?php if (!empty($resena['comentario'])): ?>
                            <p class="mb-2" style="color: #444;"><?php echo nl2br(htmlspecialchars($resena['comentario'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($resena['estado_pedido']) && $resena['estado_pedido'] !== 'perfecto'): ?>
                            <span class="badge bg-<?php echo $resena['estado_pedido'] == 'bien' ? 'info' : ($resena['estado_pedido'] == 'con_problemas' ? 'warning' : 'danger'); ?>">
                                Pedido: <?php echo ucfirst(str_replace('_', ' ', $resena['estado_pedido'])); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($resena['respuesta_negocio'])): ?>
                            <div class="respuesta-negocio mt-2 p-2" style="background: #f0f9ff; border-radius: 8px; border-left: 3px solid var(--primary);">
                                <small class="text-primary fw-bold"><i class="fas fa-store me-1"></i>Respuesta del negocio:</small>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($resena['respuesta_negocio'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>Aún no hay reseñas</h3>
                    <p>¡Sé el primero en dejar una reseña después de hacer un pedido!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item active qb-transition">
        <img src="assets/icons/home.png" alt="Inicio" class="nav-icon">
        <span>Inicio</span>
    </a>
    <a href="buscar.php" class="nav-item qb-transition">
        <img src="assets/icons/search.png" alt="Buscar" class="nav-icon">
        <span>Buscar</span>
    </a>
    <a href="<?php echo $usuario_logueado ? 'carrito.php' : 'login.php'; ?>" class="central-btn qb-transition">
        <img src="assets/icons/cart.png" alt="Carrito" class="nav-icon">
    </a>
    <a href="<?php echo $usuario_logueado ? 'favoritos.php' : 'login.php'; ?>" class="nav-item qb-transition">
        <img src="assets/icons/fav.png" alt="Favoritos" class="nav-icon">
        <span>Favoritos</span>
    </a>
    <a href="<?php echo $usuario_logueado ? 'perfil.php' : 'login.php'; ?>" class="nav-item qb-transition">
        <img src="assets/icons/user.png" alt="Perfil" class="nav-icon">
        <span>Perfil</span>
    </a>
</nav>  

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuración de Mapbox
        mapboxgl.accessToken = '<?php echo getenv("MAPBOX_TOKEN") ?: ""; ?>';
        
        // Función para inicializar el mapa
        function initializeMap() {
            // Coordenadas del negocio (puedes cambiarlas por las reales)
            const negocioLat = <?php echo !empty($negocio->latitud) ? $negocio->latitud : '20.676'; ?>;
            const negocioLng = <?php echo !empty($negocio->longitud) ? $negocio->longitud : '-103.347'; ?>;
            
            try {
                window.map = new mapboxgl.Map({
                    container: 'map',
                    style: 'mapbox://styles/mapbox/streets-v11',
                    center: [negocioLng, negocioLat],
                    zoom: 15,
                    attributionControl: false
                });

                // Forzar resize para evitar problemas de renderizado
                window.map.resize();

                // Agregar controles de zoom
                window.map.addControl(new mapboxgl.NavigationControl(), 'top-right');

                // Escuchar errores de carga de tiles
                window.map.on('error', function(e) {
                    console.error('Mapbox error:', e.error);
                });

                // Marcador del restaurante
                const restaurantMarker = new mapboxgl.Marker({
                    color: '#0165FF',
                    scale: 1.2
                })
                .setLngLat([negocioLng, negocioLat])
                .setPopup(new mapboxgl.Popup({ offset: 25 })
                    .setHTML(`
                        <div style="padding: 10px; text-align: center;">
                            <h6 style="margin: 0 0 5px 0; color: #0165FF; font-weight: 600;">
                                <?php echo htmlspecialchars($negocio->nombre); ?>
                            </h6>
                            <p style="margin: 0; font-size: 12px; color: #666;">
                                <?php echo htmlspecialchars(($negocio->calle ?? '') . ' ' . ($negocio->numero ?? '')); ?>
                            </p>
                        </div>
                    `))
                .addTo(window.map);

                // Obtener ubicación del usuario para mostrar distancia
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const userLat = position.coords.latitude;
                        const userLng = position.coords.longitude;
                        
                        // Marcador del usuario
                        const userMarker = new mapboxgl.Marker({
                            color: '#10B981',
                            scale: 0.8
                        })
                        .setLngLat([userLng, userLat])
                        .setPopup(new mapboxgl.Popup({ offset: 15 })
                            .setHTML('<div style="padding: 5px; text-align: center; font-size: 12px;">Tu ubicación</div>'))
                        .addTo(window.map);

                        // Calcular y mostrar ruta
                        getRoute([userLng, userLat], [negocioLng, negocioLat], map);
                        
                        // Ajustar vista para mostrar ambos puntos
                        const bounds = new mapboxgl.LngLatBounds();
                        bounds.extend([userLng, userLat]);
                        bounds.extend([negocioLng, negocioLat]);
                        window.map.fitBounds(bounds, { padding: 50 });
                    }, function(error) {
                        console.log('Error obteniendo ubicación:', error);
                        // Solo mostrar el marcador del restaurante
                        restaurantMarker.getPopup().addTo(window.map);
                    });
                } else {
                    // Geolocalización no disponible, solo mostrar restaurante
                    restaurantMarker.getPopup().addTo(window.map);
                }

                // Evento cuando el mapa está cargado
                window.map.on('load', function() {
                    console.log('Mapa cargado correctamente');
                });

            } catch (error) {
                console.error('Error inicializando mapa:', error);
                // Fallback: mostrar mensaje en caso de error
                document.getElementById('map').innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: var(--light);">
                        <div class="text-center">
                            <i class="fas fa-map-marker-alt mb-2" style="font-size: 2rem;"></i>
                            <div>Ubicación del restaurante</div>
                            <small style="opacity: 0.8;">Mapa no disponible</small>
                        </div>
                    </div>
                `;
            }
        }

        // Función para obtener y mostrar la ruta
        async function getRoute(start, end, map) {
            try {
                const query = await fetch(
                    `https://api.mapbox.com/directions/v5/mapbox/driving/${start[0]},${start[1]};${end[0]},${end[1]}?steps=true&geometries=geojson&access_token=${mapboxgl.accessToken}`,
                    { method: 'GET' }
                );
                
                const json = await query.json();
                
                if (json.routes && json.routes.length > 0) {
                    const data = json.routes[0];
                    const route = data.geometry.coordinates;
                    
                    // Crear GeoJSON para la ruta
                    const geojson = {
                        type: 'Feature',
                        properties: {},
                        geometry: {
                            type: 'LineString',
                            coordinates: route
                        }
                    };
                    
                    // Agregar la ruta al mapa
                    if (map.getSource('route')) {
                        map.getSource('route').setData(geojson);
                    } else {
                        map.addLayer({
                            id: 'route',
                            type: 'line',
                            source: {
                                type: 'geojson',
                                data: geojson
                            },
                            layout: {
                                'line-join': 'round',
                                'line-cap': 'round'
                            },
                            paint: {
                                'line-color': '#0165FF',
                                'line-width': 4,
                                'line-opacity': 0.8
                            }
                        });
                    }
                    
                    // Mostrar información de distancia y tiempo
                    const distance = (data.distance / 1000).toFixed(1);
                    const duration = Math.round(data.duration / 60);
                    
                    // Actualizar la meta información del restaurante
                    const metaElement = document.querySelector('.restaurant-meta');
                    if (metaElement) {
                        const distanceSpan = metaElement.querySelector('.distance-info');
                        if (distanceSpan) {
                            distanceSpan.innerHTML = `<i class="fas fa-route"></i> ${distance} km • ${duration} min`;
                        } else {
                            metaElement.innerHTML += `<span class="distance-info"><i class="fas fa-route"></i> ${distance} km • ${duration} min</span>`;
                        }
                    }
                }
            } catch (error) {
                console.error('Error obteniendo ruta:', error);
            }
        }

        // Inicializar mapa cuando la pestaña de información esté activa
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar si estamos en la pestaña de información
            const infoTab = document.getElementById('info-tab');
            const infoPane = document.getElementById('info');
            
            if (infoTab && infoPane) {
                // Inicializar cuando se haga clic en la pestaña de información
                infoTab.addEventListener('click', function() {
                    setTimeout(function() {
                        if (infoPane.classList.contains('active') && !window.mapInitialized) {
                            initializeMap();
                            window.mapInitialized = true;
                            // Forzar resize después de inicializar el mapa
                            if (window.map) {
                                window.map.resize();
                            }
                        }
                    }, 300);
                });
                
                // Si la pestaña de información está activa por defecto
                if (infoPane.classList.contains('active')) {
                    setTimeout(() => {
                        initializeMap();
                        window.mapInitialized = true;
                        if (window.map) {
                            window.map.resize();
                        }
                    }, 500);
                }
            }
        });
        
        // Función para ocultar notificación de carrito
        function hideCartToast() {
            const toast = document.getElementById('cart-toast');
            if (toast) {
                toast.style.display = 'none';
            }
        }

        // Función para limpiar carrito y agregar producto
        function clearCartAndAddProduct() {
            // Crear un formulario temporal para enviar la limpieza
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            const input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'limpiar_carrito';
            input1.value = '1';
            
            const input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'agregar_carrito';
            input2.value = '1';
            
            form.appendChild(input1);
            form.appendChild(input2);
            document.body.appendChild(form);
            form.submit();
        }

        // Función para compartir restaurante
        function shareRestaurant() {
            const shareData = {
                title: '<?php echo addslashes($negocio->nombre); ?>',
                text: 'Mira este restaurante en QuickBite',
                url: window.location.href
            };
            
            if (navigator.share) {
                navigator.share(shareData).catch(console.error);
            } else {
                // Fallback: copiar URL
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('URL copiada al portapapeles');
                }, () => {
                    alert('No se pudo copiar la URL');
                });
            }
        }

        // Función para toggle favorito
        function toggleFavorite() {
            <?php if (!$usuario_logueado): ?>
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            <?php else: ?>
                // Lógica para agregar/quitar favorito
                const favoriteBtn = document.querySelector('.favorite-btn');
                if (!favoriteBtn) return;

                fetch('api/toggle_favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id_negocio=' + encodeURIComponent(<?php echo $id_negocio; ?>)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.favorito) {
                            favoriteBtn.classList.add('favorito-activo');
                            favoriteBtn.innerHTML = '<i class="fas fa-heart"></i>';
                        } else {
                            favoriteBtn.classList.remove('favorito-activo');
                            favoriteBtn.innerHTML = '<i class="far fa-heart"></i>';
                        }
                        // Mostrar alerta estilizada en lugar de alert()
                        const existingToast = document.getElementById('favorite-toast');
                        if (existingToast) {
                            existingToast.remove();
                        }
                        const toast = document.createElement('div');
                        toast.id = 'favorite-toast';
                        toast.className = 'cart-toast success';
                        toast.style.position = 'fixed';
                        toast.style.bottom = '100px';
                        toast.style.left = '50%';
                        toast.style.transform = 'translateX(-50%)';
                        toast.style.zIndex = '1100';
                        toast.style.display = 'flex';
                        toast.style.alignItems = 'center';
                        toast.style.padding = '15px 20px';
                        toast.style.borderRadius = '15px';
                        toast.style.backgroundColor = 'var(--success)';
                        toast.style.color = 'white';
                        toast.style.boxShadow = '0 8px 30px rgba(0, 0, 0, 0.2)';
                        toast.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i> ${data.message}
                        `;
                        document.body.appendChild(toast);
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                });
            <?php endif; ?>
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar botones de acción
            const shareBtn = document.querySelector('.share-btn');
            const favoriteBtn = document.querySelector('.favorite-btn');
            
            if (shareBtn) {
                shareBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    shareRestaurant();
                });
            }
            
            if (favoriteBtn) {
                favoriteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleFavorite();
                });
            }

            // Auto-ocultar toast después de 5 segundos si no es de error de carrito
            const toast = document.getElementById('cart-toast');
            if (toast && !toast.textContent.includes('otro restaurante')) {
                setTimeout(hideCartToast, 5000);
            }

            // Validar formularios de cantidad
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const quantityInput = this.querySelector('input[name="cantidad"]');
                    const quantity = parseInt(quantityInput.value);
                    
                    if (quantity < 1 || quantity > 10) {
                        e.preventDefault();
                        alert('La cantidad debe ser entre 1 y 10');
                        return false;
                    }
                });
            });
        });
    </script>
    <script>function updatePrice(menuItem) {
    const basePrice = parseFloat(menuItem.querySelector('.menu-item-price').textContent.replace('$', '').replace(',', ''));
    const quantity = parseInt(menuItem.querySelector('.quantity-input').value) || 1;
    const elegibleSelect = menuItem.querySelector('.elegible-select');
    const btnPrice = menuItem.querySelector('.btn-price');
    
    let totalPrice = basePrice;
    
    // Agregar precio adicional del elegible si está seleccionado
    if (elegibleSelect) {
        const selectedOption = elegibleSelect.options[elegibleSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.precio) {
            totalPrice += parseFloat(selectedOption.dataset.precio);
        }
    }
    
    // Multiplicar por cantidad
    totalPrice *= quantity;
    
    // Actualizar el botón
    if (btnPrice) {
        btnPrice.textContent = '$' + totalPrice.toFixed(2);
    }
}

// Función para mostrar alertas personalizadas
function showCustomAlert(message, type = 'info') {
    // Remover alerta existente si la hay
    const existingAlert = document.getElementById('custom-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Crear nueva alerta
    const alert = document.createElement('div');
    alert.id = 'custom-alert';
    alert.className = `cart-toast ${type}`;
    alert.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1200;
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-radius: 15px;
        background: white;
        color: var(--dark);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        border-left: 6px solid var(--${type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'primary'});
        max-width: 90vw;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    
    const iconClass = type === 'warning' ? 'fa-exclamation-triangle' : 
                     type === 'error' ? 'fa-times-circle' : 
                     'fa-info-circle';
    
    alert.innerHTML = `
        <i class="fas ${iconClass} me-2" style="color: var(--${type === 'warning' ? 'warning' : type === 'error' ? 'danger' : 'primary'});"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="margin-left: 15px; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--accent);">×</button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-remover después de 4 segundos
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 4000);
}

// Event listeners mejorados
document.addEventListener('DOMContentLoaded', function() {
    // Agregar event listeners para todos los selectores de elegibles
    document.querySelectorAll('.elegible-select').forEach(select => {
        select.addEventListener('change', function() {
            updatePrice(this.closest('.menu-item'));
        });
    });
    
    // Agregar event listeners para inputs de cantidad
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            updatePrice(this.closest('.menu-item'));
        });
    });
    
    // Validar formularios antes del envío (actualizado)
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const elegibleSelect = this.querySelector('.elegible-select');
            
            // Si hay selector de elegibles, verificar que esté seleccionado
            if (elegibleSelect && elegibleSelect.hasAttribute('required')) {
                if (!elegibleSelect.value) {
                    e.preventDefault();
                    
                    // Mostrar alerta estilizada
                    showCustomAlert('Por favor, selecciona una opción antes de agregar al carrito.', 'warning');
                    
                    // Resaltar el campo
                    elegibleSelect.style.borderColor = 'var(--danger)';
                    elegibleSelect.focus();
                    
                    setTimeout(() => {
                        elegibleSelect.style.borderColor = 'rgba(1, 101, 255, 0.2)';
                    }, 3000);
                    
                    return false;
                }
            }
            
            const quantityInput = this.querySelector('input[name="cantidad"]');
            const quantity = parseInt(quantityInput.value);
            
            if (quantity < 1 || quantity > 10) {
                e.preventDefault();
                showCustomAlert('La cantidad debe ser entre 1 y 10', 'warning');
                return false;
            }
        });
    });
});</script>

<!-- Modal de Producto Mejorado -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-0">
                    <!-- Imagen del producto -->
                    <div class="col-12 col-md-5">
                        <div class="product-modal-image">
                            <img id="modalProductImage" src="" alt="">
                        </div>
                    </div>
                    <!-- Info del producto -->
                    <div class="col-12 col-md-7 modal-product-info">
                        <h4 id="modalProductName"></h4>
                        <p id="modalProductDesc"></p>
                        <div id="modalProductPrice"></div>

                        <form id="modalAddToCartForm" method="POST" action="negocio.php?id=<?php echo $id_negocio; ?>">
                            <input type="hidden" name="agregar_carrito" value="1">
                            <input type="hidden" id="modalProductId" name="id_producto" value="">

                            <!-- Contenedor de grupos de opciones -->
                            <div id="modalOptionsContainer"></div>

                            <!-- Control de cantidad -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cantidad</label>
                                <div class="quantity-control">
                                    <button type="button" class="btn btn-outline-secondary" onclick="updateModalQuantity(-1)">-</button>
                                    <input type="number" name="cantidad" id="modalQuantity" class="form-control" value="1" min="1" max="10" onchange="updateModalTotal()">
                                    <button type="button" class="btn btn-outline-secondary" onclick="updateModalQuantity(1)">+</button>
                                </div>
                            </div>

                            <!-- Botón de agregar -->
                            <button type="submit" class="btn btn-primary w-100 btn-add-cart">
                                <i class="fas fa-cart-plus me-2"></i>
                                Agregar al carrito - <span id="modalTotalPrice">$0.00</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================
   MODAL DE PRODUCTO - ESTILOS RESPONSIVOS
   ============================================ */

/* Prevenir overflow cuando modal está abierto */
body.modal-open {
    overflow-x: hidden !important;
}

.modal {
    overflow-x: hidden !important;
}

/* Modal base */
#productModal .modal-dialog {
    margin: 0.5rem;
    max-width: calc(100% - 1rem);
    width: 100%;
}

#productModal .modal-content {
    border-radius: 20px;
    overflow: hidden;
    background: #ffffff;
    border: none;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    max-width: 100%;
}

#productModal .modal-header {
    background: transparent;
    border: none;
    padding: 1rem 1rem 0;
    position: absolute;
    right: 0;
    top: 0;
    z-index: 10;
}

#productModal .modal-body {
    padding: 0;
    overflow-x: hidden;
}

#productModal .modal-body > .row {
    margin: 0;
    width: 100%;
    overflow-x: hidden;
}

/* Imagen del producto en modal */
#productModal .product-modal-image {
    position: relative;
    border-radius: 0;
    overflow: hidden;
    background: #f8f9fa;
}

#productModal .product-modal-image img {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

/* Contenido del modal */
#productModal .modal-product-info {
    padding: 1.25rem;
    overflow-x: hidden;
    max-width: 100%;
}

#productModal #modalOptionsContainer {
    overflow-x: hidden;
    max-width: 100%;
}

#productModal #modalOptionsContainer .row {
    margin-left: -0.25rem;
    margin-right: -0.25rem;
}

#productModal #modalOptionsContainer .row > [class*="col-"] {
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}

#productModal #modalProductName {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #212529;
}

#productModal #modalProductDesc {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

#productModal #modalProductPrice {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary, #0d6efd);
    margin-bottom: 1rem;
}

/* Opciones de producto */
.product-option-card {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
    text-align: center;
    word-wrap: break-word;
    overflow: hidden;
}

.product-option-card:hover {
    border-color: var(--primary, #0d6efd);
    transform: translateY(-2px);
}

.product-option-card.selected {
    border-color: var(--primary, #0d6efd);
    background: rgba(13, 110, 253, 0.05);
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
}

.product-option-card img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.option-group-title {
    font-weight: 600;
    color: #212529;
    margin-bottom: 10px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.option-group-title .badge {
    font-size: 0.65rem;
    font-weight: 500;
}

/* Control de cantidad */
#productModal .quantity-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

#productModal .quantity-control .btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 600;
}

#productModal #modalQuantity {
    width: 60px;
    text-align: center;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 10px;
}

/* Botón agregar al carrito */
#productModal .btn-add-cart {
    font-size: 1rem;
    font-weight: 600;
    padding: 0.875rem 1.5rem;
    border-radius: 50px;
}

/* Items clickeables del menú */
.menu-item-clickable {
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.menu-item-clickable:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* ============================================
   RESPONSIVE - MÓVIL (default)
   ============================================ */
@media (max-width: 767.98px) {
    #productModal .modal-dialog {
        margin: 0;
        max-width: 100%;
        height: 100%;
    }

    #productModal .modal-content {
        border-radius: 0;
        min-height: 100%;
    }

    #productModal .modal-body > .row {
        flex-direction: column;
    }

    #productModal .product-modal-image img {
        height: 220px;
    }
}

/* ============================================
   RESPONSIVE - TABLET (768px+)
   ============================================ */
@media (min-width: 768px) {
    #productModal .modal-dialog {
        margin: 1.75rem auto;
        max-width: 750px;
    }

    #productModal .modal-content {
        border-radius: 20px;
    }

    #productModal .modal-body > .row {
        display: flex;
        flex-wrap: nowrap;
    }

    #productModal .modal-body > .row > .col-md-5 {
        flex: 0 0 40%;
        max-width: 40%;
    }

    #productModal .modal-body > .row > .col-md-7 {
        flex: 0 0 60%;
        max-width: 60%;
        padding: 1.5rem;
    }

    #productModal .product-modal-image {
        border-radius: 15px;
        margin: 1rem;
        height: calc(100% - 2rem);
    }

    #productModal .product-modal-image img {
        height: 100%;
        min-height: 350px;
    }

    #productModal #modalProductName {
        font-size: 1.5rem;
    }

    .product-option-card img {
        width: 60px;
        height: 60px;
    }
}

/* ============================================
   RESPONSIVE - DESKTOP (992px+)
   ============================================ */
@media (min-width: 992px) {
    #productModal .modal-dialog {
        max-width: 850px;
    }
}

/* ============================================
   MODO OSCURO - MODAL DE PRODUCTO
   ============================================ */
[data-theme="dark"] #productModal .modal-content,
html.dark-mode #productModal .modal-content {
    background: #111111 !important;
    border: 1px solid #333333 !important;
}

[data-theme="dark"] #productModal .modal-header,
html.dark-mode #productModal .modal-header {
    background: transparent !important;
}

[data-theme="dark"] #productModal .btn-close,
html.dark-mode #productModal .btn-close {
    filter: invert(1);
}

[data-theme="dark"] #productModal .product-modal-image,
html.dark-mode #productModal .product-modal-image {
    background: #1a1a1a !important;
}

[data-theme="dark"] #productModal #modalProductName,
html.dark-mode #productModal #modalProductName {
    color: #ffffff !important;
}

[data-theme="dark"] #productModal #modalProductDesc,
html.dark-mode #productModal #modalProductDesc {
    color: #9ca3af !important;
}

[data-theme="dark"] #productModal #modalProductPrice,
html.dark-mode #productModal #modalProductPrice {
    color: #60a5fa !important;
}

[data-theme="dark"] .product-option-card,
html.dark-mode .product-option-card {
    background: #1a1a1a !important;
    border-color: #333333 !important;
    color: #ffffff !important;
}

[data-theme="dark"] .product-option-card:hover,
html.dark-mode .product-option-card:hover {
    border-color: #60a5fa !important;
}

[data-theme="dark"] .product-option-card.selected,
html.dark-mode .product-option-card.selected {
    border-color: #60a5fa !important;
    background: rgba(96, 165, 250, 0.1) !important;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2) !important;
}

[data-theme="dark"] .product-option-card .small,
html.dark-mode .product-option-card .small {
    color: #d1d5db !important;
}

[data-theme="dark"] .product-option-card .text-primary,
html.dark-mode .product-option-card .text-primary {
    color: #60a5fa !important;
}

[data-theme="dark"] .product-option-card .text-success,
html.dark-mode .product-option-card .text-success {
    color: #34d399 !important;
}

[data-theme="dark"] .option-group-title,
html.dark-mode .option-group-title {
    color: #ffffff !important;
}

[data-theme="dark"] #productModal .form-label,
html.dark-mode #productModal .form-label {
    color: #ffffff !important;
}

[data-theme="dark"] #productModal .form-control,
html.dark-mode #productModal .form-control {
    background: #1a1a1a !important;
    border-color: #333333 !important;
    color: #ffffff !important;
}

[data-theme="dark"] #productModal .btn-outline-secondary,
html.dark-mode #productModal .btn-outline-secondary {
    border-color: #444444 !important;
    color: #ffffff !important;
}

[data-theme="dark"] #productModal .btn-outline-secondary:hover,
html.dark-mode #productModal .btn-outline-secondary:hover {
    background: #333333 !important;
    border-color: #555555 !important;
}

[data-theme="dark"] #productModal .text-muted,
html.dark-mode #productModal .text-muted {
    color: #9ca3af !important;
}

[data-theme="dark"] .menu-item-clickable:hover,
html.dark-mode .menu-item-clickable:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
}
</style>

<script>
// Variables globales del modal
let currentProduct = null;
let currentBasePrice = 0;

// Función para abrir el modal de producto
function openProductModal(productData) {
    currentProduct = productData;
    currentBasePrice = parseFloat(productData.precio);

    // Establecer datos básicos
    document.getElementById('modalProductImage').src = productData.imagen || 'assets/img/placeholder-product.png';
    document.getElementById('modalProductName').textContent = productData.nombre;
    document.getElementById('modalProductDesc').textContent = productData.descripcion || 'Deliciosa opción de nuestro menú';
    document.getElementById('modalProductPrice').textContent = '$' + parseFloat(productData.precio).toFixed(2);
    document.getElementById('modalProductId').value = productData.id_producto;
    document.getElementById('modalQuantity').value = 1;

    // Generar opciones dinámicas
    const optionsContainer = document.getElementById('modalOptionsContainer');
    optionsContainer.innerHTML = '';

    // Mostrar ELEGIBLES (extras para ramos, etc.)
    if (productData.elegibles && productData.elegibles.length > 0) {
        const elegiblesDisponibles = productData.elegibles.filter(e => e.disponible);
        if (elegiblesDisponibles.length > 0) {
            let elegiblesHtml = `
                <div class="elegibles-modal-section">
                    <div class="elegibles-modal-title">
                        <i class="fas fa-plus-circle"></i> Extras disponibles
                    </div>
                    <div id="elegiblesContainer">
            `;
            elegiblesDisponibles.forEach((elegible, index) => {
                const precioExtra = parseFloat(elegible.precio_adicional) || 0;
                elegiblesHtml += `
                    <label class="elegible-option" onclick="toggleElegible(this)">
                        <input type="radio" name="elegible_id" value="${elegible.id_elegible}"
                               data-precio="${precioExtra}" ${index === 0 ? 'checked' : ''}>
                        <div class="elegible-option-info">
                            <div class="elegible-option-name">${elegible.nombre}</div>
                            ${elegible.descripcion ? `<div class="elegible-option-desc">${elegible.descripcion}</div>` : ''}
                        </div>
                        <div class="elegible-option-price ${precioExtra === 0 ? 'free' : ''}">
                            ${precioExtra > 0 ? '+$' + precioExtra.toFixed(2) : 'Incluido'}
                        </div>
                    </label>
                `;
            });
            elegiblesHtml += '</div></div>';
            optionsContainer.innerHTML += elegiblesHtml;

            // Marcar el primero como seleccionado visualmente
            setTimeout(() => {
                const firstElegible = document.querySelector('.elegible-option');
                if (firstElegible) firstElegible.classList.add('selected');
            }, 10);
        }
    }

    // Mostrar GRUPOS DE OPCIONES (colores, tamaños, etc.)
    if (productData.grupos_opciones && productData.grupos_opciones.length > 0) {
        productData.grupos_opciones.forEach((grupo, grupoIndex) => {
            const grupoHtml = `
                <div class="mb-3">
                    <label class="option-group-title">
                        ${grupo.nombre}
                        ${grupo.es_obligatorio ? '<span class="badge bg-danger ms-2">Requerido</span>' : ''}
                    </label>
                    <div class="row g-2" id="optionGroup_${grupo.id_grupo_opcion}">
                        ${grupo.opciones.map((opcion, index) => `
                            <div class="col-6 col-md-4">
                                <div class="product-option-card ${opcion.por_defecto ? 'selected' : ''}"
                                     onclick="selectOption(${grupo.id_grupo_opcion}, ${opcion.id_opcion}, this, '${opcion.imagen || ''}', ${opcion.precio_adicional})"
                                     data-opcion-id="${opcion.id_opcion}"
                                     data-precio="${opcion.precio_adicional}">
                                    ${opcion.imagen ? `<img src="${opcion.imagen}" alt="${opcion.nombre}" class="mb-2">` : ''}
                                    <div class="small fw-semibold">${opcion.nombre}</div>
                                    ${opcion.precio_adicional > 0 ? `<div class="small text-primary">+$${parseFloat(opcion.precio_adicional).toFixed(2)}</div>` : '<div class="small text-success">Incluido</div>'}
                                    <input type="radio" name="opcion_grupo_${grupo.id_grupo_opcion}" value="${opcion.id_opcion}" ${opcion.por_defecto ? 'checked' : ''} class="d-none" ${grupo.es_obligatorio ? 'required' : ''}>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            optionsContainer.innerHTML += grupoHtml;
        });
    }

    // Calcular precio inicial
    updateModalTotal();

    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

// Función para toggle de elegibles
function toggleElegible(element) {
    document.querySelectorAll('.elegible-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    element.classList.add('selected');
    updateModalTotal();
}

// Función para seleccionar una opción
function selectOption(grupoId, opcionId, element, imagen, precioAdicional) {
    // Deseleccionar todas las opciones del grupo
    const grupo = document.getElementById('optionGroup_' + grupoId);
    grupo.querySelectorAll('.product-option-card').forEach(card => {
        card.classList.remove('selected');
        card.querySelector('input[type="radio"]').checked = false;
    });

    // Seleccionar la opción clickeada
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;

    // Cambiar imagen si la opción tiene una
    if (imagen) {
        document.getElementById('modalProductImage').src = imagen;
    }

    // Actualizar precio total
    updateModalTotal();
}

// Función para actualizar cantidad
function updateModalQuantity(change) {
    const input = document.getElementById('modalQuantity');
    let value = parseInt(input.value) + change;
    if (value < 1) value = 1;
    if (value > 10) value = 10;
    input.value = value;
    updateModalTotal();
}

// Función para calcular y mostrar precio total
function updateModalTotal() {
    let total = currentBasePrice;

    // Sumar precios adicionales de opciones seleccionadas (grupos de opciones)
    document.querySelectorAll('#modalOptionsContainer .product-option-card.selected').forEach(card => {
        total += parseFloat(card.dataset.precio) || 0;
    });

    // Sumar precio de elegible seleccionado
    const elegibleSeleccionado = document.querySelector('.elegible-option.selected input[type="radio"]:checked');
    if (elegibleSeleccionado) {
        total += parseFloat(elegibleSeleccionado.dataset.precio) || 0;
    }

    // Multiplicar por cantidad
    const quantity = parseInt(document.getElementById('modalQuantity').value) || 1;
    total *= quantity;

    document.getElementById('modalTotalPrice').textContent = '$' + total.toFixed(2);
}

// Inicializar productos clickeables
<?php
$productos_json = [];
foreach ($productos_por_categoria as $cat) {
    foreach ($cat['productos'] as $prod) {
        $productos_json[$prod['id_producto']] = [
            'id_producto' => $prod['id_producto'],
            'nombre' => $prod['nombre'],
            'descripcion' => $prod['descripcion'] ?? '',
            'precio' => $prod['precio'],
            'imagen' => $prod['imagen'] ?? '',
            'grupos_opciones' => $prod['grupos_opciones'] ?? [],
            'elegibles' => $prod['elegibles'] ?? [],
            'tiene_elegibles' => $prod['tiene_elegibles'] ?? false
        ];
    }
}
?>
const productosData = <?php echo json_encode($productos_json); ?>;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.menu-item, .product-card-premium').forEach(item => {
        const productId = item.querySelector('input[name="id_producto"]')?.value || item.dataset.productId;
        if (productId && productosData[productId]) {
            // Hacer TODOS los productos clickeables para abrir el modal
            item.classList.add('menu-item-clickable');

            // Hacer clickeable toda el área inner (contenido completo)
            const innerArea = item.querySelector('.menu-item-inner');
            const clickableArea = item.querySelector('.menu-item-img, .product-image-container');

            // Función para abrir modal
            const openModal = function(e) {
                // No abrir modal si se hizo clic en el botón quick-add o en inputs
                if (e.target.closest('.quick-add-btn, .quantity-selector, button, input')) return;
                e.preventDefault();
                e.stopPropagation();
                openProductModal(productosData[productId]);
            };

            // Hacer clickeable el área inner completa
            if (innerArea) {
                innerArea.style.cursor = 'pointer';
                innerArea.addEventListener('click', openModal);
            }

            // Agregar badge de "Ver detalles" en la imagen
            if (clickableArea && !clickableArea.querySelector('.view-details-badge')) {
                const badge = document.createElement('div');
                badge.className = 'view-details-badge';
                const hasOptions = (productosData[productId].grupos_opciones && productosData[productId].grupos_opciones.length > 0) ||
                                   (productosData[productId].elegibles && productosData[productId].elegibles.length > 0);
                badge.innerHTML = hasOptions
                    ? '<i class="fas fa-palette"></i> Ver opciones'
                    : '<i class="fas fa-eye"></i> Ver detalles';
                clickableArea.style.position = 'relative';
                clickableArea.appendChild(badge);
            }
        }
    });
});
</script>

<!-- Premium Store UI/UX Scripts -->
<script>
// ============================================
// CATEGORIES STICKY NAVIGATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const categoriesNav = document.getElementById('categoriesNav');
    const categoryChips = document.querySelectorAll('.category-chip');
    const menuCategories = document.querySelectorAll('.menu-category');

    // Scroll suave al hacer clic en categoría
    categoryChips.forEach(chip => {
        chip.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                const navHeight = categoriesNav ? categoriesNav.offsetHeight : 0;
                const tabsHeight = document.querySelector('.tabs-container')?.offsetHeight || 0;
                const offset = navHeight + tabsHeight + 20;

                window.scrollTo({
                    top: targetElement.offsetTop - offset,
                    behavior: 'smooth'
                });

                // Actualizar chip activo
                categoryChips.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });

    // Detectar categoría activa al hacer scroll
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const navHeight = categoriesNav ? categoriesNav.offsetHeight : 0;
            const scrollPosition = window.scrollY + navHeight + 150;

            menuCategories.forEach((category, index) => {
                const categoryTop = category.offsetTop;
                const categoryBottom = categoryTop + category.offsetHeight;

                if (scrollPosition >= categoryTop && scrollPosition < categoryBottom) {
                    categoryChips.forEach(c => c.classList.remove('active'));
                    if (categoryChips[index]) {
                        categoryChips[index].classList.add('active');

                        // Scroll horizontal para mostrar el chip activo
                        const container = document.querySelector('.categories-scroll-container');
                        if (container) {
                            const chipLeft = categoryChips[index].offsetLeft;
                            const containerWidth = container.offsetWidth;
                            const chipWidth = categoryChips[index].offsetWidth;
                            container.scrollTo({
                                left: chipLeft - (containerWidth / 2) + (chipWidth / 2),
                                behavior: 'smooth'
                            });
                        }
                    }
                }
            });

            // Efecto sombra al hacer scroll
            if (categoriesNav) {
                if (window.scrollY > 100) {
                    categoriesNav.classList.add('scrolled');
                } else {
                    categoriesNav.classList.remove('scrolled');
                }
            }
        }, 50);
    });
});

// ============================================
// QUICK ADD TO CART (AJAX)
// ============================================
function quickAddToCart(productId, button) {
    <?php if (!$usuario_logueado): ?>
    // Redirigir a login si no está logueado
    window.location.href = 'login.php?redirect=negocio.php?id=<?php echo $id_negocio; ?>';
    return;
    <?php endif; ?>

    <?php if (!$esta_abierto): ?>
    // Mostrar mensaje si el negocio está cerrado
    showToast('El negocio está cerrado en este momento', 'error');
    return;
    <?php endif; ?>

    // Deshabilitar botón temporalmente
    button.disabled = true;
    const originalIcon = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    // Enviar solicitud AJAX
    const formData = new FormData();
    formData.append('agregar_carrito', '1');
    formData.append('id_producto', productId);
    formData.append('cantidad', '1');

    fetch('negocio.php?id=<?php echo $id_negocio; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Éxito
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('added');

        // Actualizar contador del carrito si existe
        updateCartPreview();

        // Mostrar toast de éxito
        showToast('Producto agregado al carrito', 'success');

        // Restaurar botón después de 1.5s
        setTimeout(() => {
            button.innerHTML = originalIcon;
            button.classList.remove('added');
            button.disabled = false;
        }, 1500);
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalIcon;
        button.disabled = false;
        showToast('Error al agregar al carrito', 'error');
    });
}

// Actualizar preview del carrito
function updateCartPreview() {
    // Recargar la página para mostrar el carrito actualizado
    // En una implementación más avanzada, esto sería una llamada AJAX
    setTimeout(() => {
        location.reload();
    }, 500);
}

// Mostrar toast notification
function showToast(message, type = 'success') {
    // Remover toast existente
    const existingToast = document.querySelector('.quick-toast');
    if (existingToast) {
        existingToast.remove();
    }

    // Crear nuevo toast
    const toast = document.createElement('div');
    toast.className = 'quick-toast';
    toast.style.cssText = `
        position: fixed;
        bottom: 120px;
        left: 50%;
        transform: translateX(-50%);
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        z-index: 9999;
        animation: toastSlideUp 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    `;

    if (type === 'success') {
        toast.style.background = 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
    } else {
        toast.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
        toast.style.color = 'white';
        toast.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
    }

    document.body.appendChild(toast);

    // Auto-remover después de 3s
    setTimeout(() => {
        toast.style.animation = 'toastSlideDown 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Agregar estilos de animación
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    @keyframes toastSlideUp {
        from { opacity: 0; transform: translateX(-50%) translateY(20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    @keyframes toastSlideDown {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(20px); }
    }
`;
document.head.appendChild(toastStyles);
</script>

<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw-simple.js').catch(error => {
        console.error('Error registrando Service Worker:', error);
    });
}
</script>

<?php if ($es_orez_floreria): ?>
<!-- Orez Floristería - Scripts especializados -->
<script src="assets/js/orez-floreria.js?v=1.4"></script>
<script>
// Datos de complementos y variantes de Orez
const complementosOrez = <?php echo json_encode($complementos_orez); ?>;
const variantesOrezPorNombre = <?php echo json_encode($variantes_orez); ?>;
const variantesOrezPorId = <?php echo file_get_contents('assets/js/orez-variantes-map.json'); ?>;

// Función para encontrar variantes de un producto por ID o nombre
function getVariantesProducto(productId, nombreProducto) {
    // Primero buscar por ID (más preciso)
    const productIdStr = String(productId);
    if (variantesOrezPorId && variantesOrezPorId[productIdStr]) {
        return variantesOrezPorId[productIdStr];
    }
    // Fallback: buscar por nombre (si existe)
    if (nombreProducto && variantesOrezPorNombre) {
        for (const [nombreBase, variantes] of Object.entries(variantesOrezPorNombre)) {
            if (nombreProducto === nombreBase || nombreProducto.startsWith(nombreBase)) {
                return variantes;
            }
        }
    }
    return null;
}

// Sobrescribir openProductModal para productos de Orez
const originalOpenProductModal = openProductModal;
openProductModal = function(productData) {
    const nombre = (productData.nombre || '');
    const nombreLower = nombre.toLowerCase();

    // Detectar si es un ramo de rosas dinámico (12, 25, 50, 75, 100, 150, 200 rosas)
    // También detectar "Ramos de Rosas" en general
    const esRamoDinamico = (
        nombreLower.includes('ramo de') &&
        nombreLower.includes('rosas') &&
        !nombreLower.includes('tulipanes') &&
        !nombreLower.includes('gerberas') &&
        !nombreLower.includes('dólar') &&
        !nombreLower.includes('dolar')
    ) || nombreLower === 'ramos de rosas'; // Categoría especial

    console.log('[Orez] Producto:', nombre, '| esRamoDinamico:', esRamoDinamico);

    // Buscar si este producto tiene variantes (Mitad/Doble)
    const productId = productData.id_producto || productData.id;
    const variantesProducto = getVariantesProducto(productId, nombre);
    const tieneVariantes = variantesProducto && variantesProducto.length > 0;

    // Agregar variantes al productData si las tiene
    if (tieneVariantes) {
        productData.variantes_tamano = variantesProducto;
        productData.tiene_variantes_tamano = true;
    }

    // Usar modal especializado de Orez para:
    // 1. Ramos de rosas dinámicos (selector de cantidad y diseño)
    // 2. Cualquier producto de Orez (para mostrar complementos)
    if (esRamoDinamico || productData.tiene_opciones_dinamicas) {
        openOrezProductModal(productData, complementosOrez, true); // true = es ramo dinámico
    } else {
        // Usar modal de Orez para todos los productos (para mostrar variantes y complementos)
        openOrezProductModal(productData, complementosOrez, false);
    }
};

console.log('Orez Floristeria - Modal especializado activado');
</script>
<?php endif; ?>
 <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>