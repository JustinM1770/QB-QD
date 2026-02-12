<?php
// Iniciar sesión
session_start();

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Categoria.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Obtener todas las categorías para el filtro
$categoria_obj = new Categoria($db);
$todas_categorias = $categoria_obj->obtenerTodas();

// Variables para filtros
$categoria_id = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$ordenar_por = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'rating';
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

// Instanciar modelo de negocio
$negocio = new Negocio($db);

// Obtener negocios según filtros
$negocios = [];

if (!empty($busqueda)) {
    // Buscar por término
    $negocios = $negocio->buscar($busqueda);
} elseif ($lat !== null && $lng !== null) {
    // Buscar por ubicación
    $negocios = $negocio->obtenerCercanos($lat, $lng);
} elseif ($categoria_id > 0) {
    // Filtrar por categoría
    $negocios = $negocio->obtenerPorCategoria($categoria_id);
} else {
    // Mostrar todos
    $negocios = $negocio->obtenerTodos();
}

// Ordenar resultados (ya deberían venir ordenados por rating de la BD, pero podemos agregar más opciones)
if ($ordenar_por === 'tiempo') {
    usort($negocios, function($a, $b) {
        return $a['tiempo_preparacion_promedio'] - $b['tiempo_preparacion_promedio'];
    });
} elseif ($ordenar_por === 'envio') {
    usort($negocios, function($a, $b) {
        return $a['costo_envio'] - $b['costo_envio'];
    });
}

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurantes - QuickBite</title>
    <!-- Fonts: Inter and DM Sans -->
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1E88E5;         /* Azul principal */
            --primary-light: #64B5F6;   /* Azul claro */
            --primary-dark: #1565C0;    /* Azul oscuro */
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --gradient: linear-gradient(135deg, #1E88E5 0%, #64B5F6 100%);
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
        }

        .container {
            padding-bottom: 80px;
        }

        .page-header {
            display: flex;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .page-title {
            font-size: 1.5rem;
            margin: 0;
            flex: 1;
        }

        .back-button {
            background-color: var(--secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            text-decoration: none;
            margin-right: 15px;
        }

        .filter-bar {
            background-color: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-scroll {
            display: flex;
            overflow-x: auto;
            padding: 5px 0;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        .filter-scroll::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .filter-btn {
            flex: 0 0 auto;
            margin-right: 10px;
            padding: 8px 15px;
            background-color: var(--secondary);
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .filter-btn.active {
            background-color: var(--primary);
            color: white;
        }

        .sort-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
        }

        .sort-bar label {
            font-size: 0.9rem;
            font-weight: 500;
            margin-right: 10px;
        }

        .sort-bar select {
            padding: 8px 15px;
            border: 1px solid #eee;
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--dark);
            background-color: white;
        }

        .restaurant-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .restaurant-card {
            background-color: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--dark);
        }

        .restaurant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .restaurant-card .card-img {
            height: 140px;
            background-color: #f1f1f1;
            background-size: cover;
            background-position: center;
        }

        .restaurant-card .card-content {
            padding: 15px;
        }

        .restaurant-card .restaurant-name {
            font-weight: 600;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .restaurant-card .restaurant-info {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: #666;
            margin: 8px 0;
        }

        .restaurant-card .restaurant-info i {
            margin-right: 5px;
            color: var(--primary);
        }

        .restaurant-card .restaurant-info .divider {
            margin: 0 8px;
            color: #ddd;
        }

        .restaurant-card .restaurant-categories {
            margin-top: 10px;
        }

        .restaurant-card .category-badge {
            display: inline-block;
            background-color: var(--secondary);
            color: var(--accent);
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 50px;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .restaurant-card .rating {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 50px;
            font-size: 0.8rem;
        }

        .search-form {
            margin-bottom: 20px;
        }

        .search-bar {
            background-color: white;
            border-radius: 50px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .search-bar input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            margin-left: 10px;
            font-family: 'Inter', sans-serif;
        }

        .search-bar i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .search-bar button {
            background: none;
            border: none;
            cursor: pointer;
        }

        .hero-btn {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .hero-btn button {
            background-color: white;
            color: var(--dark);
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .hero-btn button:hover {
            background-color: var(--accent);
            color: white;
        }

        .location-spinner {
            display: none;
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .no-results {
            text-align: center;
            padding: 40px 0;
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .no-results i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }

        .no-results h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .no-results p {
            color: #666;
            max-width: 80%;
            margin: 0 auto 20px;
        }

        .location-toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            z-index: 1001;
            font-size: 0.9rem;
            display: none;
        }

        .btn-reset {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-reset:hover {
            background: var(--primary-dark);
            color: white;
        }

        =========================================== */
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
    filter: grayscale(100%) opacity(0.4);
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

/* =======================================================
   MODO OSCURO - RESTAURANTS.PHP
   ======================================================= */
@media (prefers-color-scheme: dark) {
    :root {
        --body-bg: #000000;
        --white: #111111;
        --light: #000000;
        --gray-100: #1a1a1a;
        --gray-200: #333333;
        --gray-900: #ffffff;
        --gray-800: #e0e0e0;
        --gray-700: #cccccc;
        --gray-600: #aaaaaa;
    }

    body {
        background-color: #000000 !important;
        color: #e0e0e0;
    }

    .page-header {
        background: #000000 !important;
        border-bottom: 1px solid #333;
    }

    .page-title {
        color: #fff !important;
    }

    .back-button {
        color: #fff !important;
    }

    .restaurant-card {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .restaurant-card:hover {
        border-color: var(--primary) !important;
    }

    .card-content {
        background: #111111 !important;
    }

    .restaurant-name {
        color: #fff !important;
    }

    .restaurant-info {
        color: #aaa !important;
    }

    .category-badge {
        background: #1a1a1a !important;
        color: #ccc !important;
        border: 1px solid #333;
    }

    .restaurant-card:hover .category-badge {
        background: rgba(1, 101, 255, 0.2) !important;
        color: var(--primary) !important;
    }

    .section-title {
        color: #fff !important;
    }

    /* Bottom Nav */
    .bottom-nav {
        background: rgba(0, 0, 0, 0.95) !important;
        border-top: 1px solid #333;
    }

    /* Iconos del navbar - BLANCOS */
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
}

/* Soporte para data-theme="dark" y clase .dark-mode */
[data-theme="dark"] body,
html.dark-mode body {
    background-color: #000000 !important;
    color: #e0e0e0;
}

[data-theme="dark"] .page-header,
html.dark-mode .page-header {
    background: #000000 !important;
    border-bottom: 1px solid #333;
}

[data-theme="dark"] .page-title,
html.dark-mode .page-title {
    color: #fff !important;
}

[data-theme="dark"] .back-button,
html.dark-mode .back-button {
    color: #fff !important;
}

[data-theme="dark"] .restaurant-card,
html.dark-mode .restaurant-card {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .card-content,
html.dark-mode .card-content {
    background: #111111 !important;
}

[data-theme="dark"] .restaurant-name,
html.dark-mode .restaurant-name {
    color: #fff !important;
}

[data-theme="dark"] .restaurant-info,
html.dark-mode .restaurant-info {
    color: #aaa !important;
}

[data-theme="dark"] .category-badge,
html.dark-mode .category-badge {
    background: #1a1a1a !important;
    color: #ccc !important;
    border: 1px solid #333;
}

[data-theme="dark"] .section-title,
html.dark-mode .section-title {
    color: #fff !important;
}

[data-theme="dark"] .bottom-nav,
html.dark-mode .bottom-nav {
    background: rgba(0, 0, 0, 0.95) !important;
    border-top: 1px solid #333;
}

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
    
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <div class="container">
        <div class="page-header">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Restaurantes</h1>
        </div>

        <!-- Toast para notificaciones de ubicación -->
        <div id="location-toast" class="location-toast"></div>

        <!-- Search Form -->
        <form action="restaurants.php" method="GET" class="search-form">
            <div class="search-bar">
                <button type="submit" aria-label="Buscar">
                    <i class="fas fa-search"></i>
                </button>
                <input type="text" name="buscar" placeholder="Buscar restaurantes, cocinas, platos..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <?php if (!empty($busqueda)): ?>
                    <a href="restaurants.php" class="ms-2 text-decoration-none" aria-label="Limpiar búsqueda">
                        <i class="fas fa-times-circle"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Campos ocultos para coordenadas -->
            <input type="hidden" name="lat" id="lat" value="<?php echo $lat; ?>">
            <input type="hidden" name="lng" id="lng" value="<?php echo $lng; ?>">
            
            <!-- Preserve other filter parameters -->
            <?php if ($categoria_id > 0): ?>
                <input type="hidden" name="categoria" value="<?php echo $categoria_id; ?>">
            <?php endif; ?>
            <?php if ($ordenar_por): ?>
                <input type="hidden" name="ordenar" value="<?php echo $ordenar_por; ?>">
            <?php endif; ?>
        </form>

        <!-- Ubicación Button -->
        <div class="hero-btn">
            <button id="ubicacion-btn" onclick="obtenerUbicacion()">
                <div id="location-spinner" class="location-spinner"></div>
                <i class="fas fa-map-marker-alt me-2"></i>
                Restaurantes cerca de mí
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-scroll">
                <a href="restaurants.php?<?php echo http_build_query(array_merge($_GET, ['categoria' => 0])); ?>" class="filter-btn <?php echo $categoria_id == 0 ? 'active' : ''; ?>">
                    Todos
                </a>
                <?php foreach ($todas_categorias as $cat): ?>
                    <a href="restaurants.php?<?php echo http_build_query(array_merge($_GET, ['categoria' => $cat['id_categoria']])); ?>" class="filter-btn <?php echo $categoria_id == $cat['id_categoria'] ? 'active' : ''; ?>">
                        <?php echo $cat['nombre']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sort Bar -->
        <div class="sort-bar">
            <div>
                <?php if (count($negocios) > 0): ?>
                    <span><?php echo count($negocios); ?> restaurantes encontrados</span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center">
                <label for="ordenar">Ordenar por:</label>
                <select id="ordenar" name="ordenar" onchange="cambiarOrden(this.value)">
                    <option value="rating" <?php echo $ordenar_por === 'rating' ? 'selected' : ''; ?>>Valoración</option>
                    <option value="tiempo" <?php echo $ordenar_por === 'tiempo' ? 'selected' : ''; ?>>Tiempo de entrega</option>
                    <option value="envio" <?php echo $ordenar_por === 'envio' ? 'selected' : ''; ?>>Costo de envío</option>
                </select>
            </div>
        </div>

        <!-- Results -->
        <?php if (empty($negocios)): ?>
            <div class="no-results">
                <i class="fas fa-store-slash"></i>
                <h3>No se encontraron restaurantes</h3>
                <?php if (!empty($busqueda)): ?>
                    <p>No hemos encontrado resultados para "<?php echo htmlspecialchars($busqueda); ?>". Intenta con otra búsqueda o explora nuestras categorías.</p>
                <?php elseif ($categoria_id > 0): ?>
                    <p>No hay restaurantes disponibles en esta categoría en este momento.</p>
                <?php elseif ($lat !== null && $lng !== null): ?>
                    <p>No hemos encontrado restaurantes cerca de tu ubicación actual.</p>
                <?php else: ?>
                    <p>No hay restaurantes disponibles en este momento.</p>
                <?php endif; ?>
                <a href="restaurants.php" class="btn-reset">Ver todos los restaurantes</a>
            </div>
        <?php else: ?>
            <div class="restaurant-cards">
                <?php foreach ($negocios as $neg): ?>
                    <a href="negocio.php?id=<?php echo $neg['id_negocio']; ?>" class="restaurant-card">
                        <div class="card-img" style="background-image: url('<?php echo $neg['imagen_portada'] ? $neg['imagen_portada'] : 'assets/img/restaurants/default.jpg'; ?>');"></div>
                        <div class="card-content">
                            <div class="restaurant-name">
                                <span><?php echo $neg['nombre']; ?></span>
                                <span class="rating"><?php echo number_format($neg['rating'], 1); ?></span>
                            </div>
                            <div class="restaurant-info">
                                <i class="fas fa-clock"></i> <?php echo $neg['tiempo_preparacion_promedio']; ?> min
                                <span class="divider">|</span>
                                <i class="fas fa-motorcycle"></i> Envío $<?php echo number_format($neg['costo_envio'], 2); ?>
                            </div>
                            <?php if (isset($neg['distancia'])): ?>
                                <div class="restaurant-info">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo $neg['distancia']; ?> km de distancia
                                </div>
                            <?php endif; ?>
                            <div class="restaurant-categories">
                                <?php if (!empty($neg['categorias'])): ?>
                                    <?php foreach ($neg['categorias'] as $cat): ?>
                                        <span class="category-badge"><?php echo $cat; ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para cambiar orden de restaurantes
        function cambiarOrden(valor) {
            // Obtener parámetros actuales de la URL
            const urlParams = new URLSearchParams(window.location.search);
            
            // Actualizar parámetro de ordenamiento
            urlParams.set('ordenar', valor);
            
            // Redirigir con nueva URL
            window.location.href = 'restaurants.php?' + urlParams.toString();
        }
        
        // Función para mostrar mensajes en el toast
        function mostrarToast(mensaje, duracion = 3000) {
            const toast = document.getElementById('location-toast');
            toast.textContent = mensaje;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, duracion);
        }
        
        // Función para obtener ubicación del usuario
        function obtenerUbicacion() {
            const locationSpinner = document.getElementById('location-spinner');
            const locationBtn = document.getElementById('ubicacion-btn');
            
            // Deshabilitar botón y mostrar spinner
            locationBtn.disabled = true;
            locationSpinner.style.display = 'inline-block';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        // Actualizar campos ocultos
                        document.getElementById('lat').value = lat;
                        document.getElementById('lng').value = lng;
                        
                        // Enviar formulario para actualizar resultados
                        document.querySelector('.search-form').submit();
                    },
                    function(error) {
                        // Restaurar botón
                        locationBtn.disabled = false;
                        locationSpinner.style.display = 'none';
                        
                        // Mostrar mensaje de error
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                mostrarToast("Necesitamos permiso para acceder a tu ubicación");
                                break;
                            case error.POSITION_UNAVAILABLE:
                                mostrarToast("La información de ubicación no está disponible");
                                break;
                            case error.TIMEOUT:
                                mostrarToast("Tiempo de espera agotado para obtener ubicación");
                                break;
                            case error.UNKNOWN_ERROR:
                                mostrarToast("Error desconocido al obtener ubicación");
                                break;
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                locationBtn.disabled = false;
                locationSpinner.style.display = 'none';
                mostrarToast("Tu navegador no soporta geolocalización");
            }
        }
        
        // Si ya hay coordenadas en la URL, mostrar mensaje
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('lat') && urlParams.has('lng')) {
                mostrarToast("Mostrando restaurantes cercanos a tu ubicación", 5000);
            }
        });
    </script>
</body>
</html>