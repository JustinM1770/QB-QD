<?php
/**
 * Catálogo Público - Orez Floristería
 * Vista de productos sin necesidad de iniciar sesión
 * Para comprar se requiere login
 */
session_start();

require_once 'config/database.php';
require_once 'includes/orez_floreria.php';

$database = new Database();
$db = $database->getConnection();

// ID del negocio Orez
$id_negocio = OREZ_NEGOCIO_ID;

// Obtener info del negocio
$stmt = $db->prepare("SELECT * FROM negocios WHERE id_negocio = ?");
$stmt->execute([$id_negocio]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener productos agrupados por categoría
$stmt = $db->prepare("
    SELECT p.*, cp.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
    WHERE p.id_negocio = ? AND p.disponible = 1
    ORDER BY cp.nombre, p.precio ASC
");
$stmt->execute([$id_negocio]);
$productos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrar categorías ocultas y consolidar variantes
$productos_filtrados = filtrarCategoriasOcultas($productos_raw);
$productos_consolidados = consolidarProductosMitad($productos_filtrados);

// Agrupar por categoría
$categorias = [];
foreach ($productos_raw as $prod) {
    $cat = $prod['categoria_nombre'] ?? 'Otros';
    if (stripos($cat, 'Extras') !== false) continue; // Ocultar extras

    if (!isset($categorias[$cat])) {
        $categorias[$cat] = [];
    }

    // No agregar variantes sueltas
    if (!preg_match('/\s*-?\s*(Mitad|Doble)\s*(Ramo)?\s*$/i', $prod['nombre'])) {
        $categorias[$cat][] = $prod;
    }
}

// Usuario logueado?
$usuario_logueado = isset($_SESSION['id_usuario']);
$usuario_nombre = $_SESSION['nombre'] ?? '';

// Cargar mapa de variantes
$variantes_json = file_get_contents('assets/js/orez-variantes-map.json');
$variantes_map = json_decode($variantes_json, true) ?: [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - Orez Floristería</title>
    <meta name="description" content="Catálogo de flores de Orez Floristería. Ramos de rosas, tulipanes, gerberas y más. Envío a domicilio.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --orez-red: #8B0000;
            --orez-red-light: #A52A2A;
            --orez-cream: #FFF8F0;
            --orez-gold: #D4AF37;
            --orez-pink: #E91E63;
            --orez-pink-light: #FCE4EC;
            --shadow-soft: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-hover: 0 8px 30px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--orez-cream);
            color: #333;
            min-height: 100vh;
        }

        /* Header */
        .catalog-header {
            background: linear-gradient(135deg, var(--orez-red) 0%, var(--orez-red-light) 100%);
            color: white;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(139,0,0,0.3);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-section img {
            height: 50px;
            border-radius: 8px;
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .logo-subtitle {
            font-size: 0.7rem;
            letter-spacing: 3px;
            opacity: 0.9;
            text-transform: uppercase;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-login, .btn-cart {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-login {
            background: white;
            color: var(--orez-red);
            border: none;
        }

        .btn-login:hover {
            background: var(--orez-gold);
            color: white;
        }

        .btn-cart {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 2px solid rgba(255,255,255,0.5);
        }

        .btn-cart:hover {
            background: white;
            color: var(--orez-red);
        }

        .cart-count {
            background: var(--orez-gold);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 4px;
        }

        .user-greeting {
            color: rgba(255,255,255,0.9);
            font-size: 0.85rem;
        }

        .user-greeting strong {
            color: var(--orez-gold);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--orez-red) 0%, #5C0000 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.05)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.03)"/><circle cx="40" cy="70" r="2" fill="rgba(255,255,255,0.04)"/></svg>');
            opacity: 0.5;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 25px;
        }

        .hero-badge {
            display: inline-block;
            background: var(--orez-gold);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* WhatsApp Banner */
        .whatsapp-banner {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 15px 20px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .whatsapp-banner a {
            background: white;
            color: #128C7E;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .whatsapp-banner a:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Categories Nav */
        .categories-nav {
            background: white;
            padding: 15px 20px;
            position: sticky;
            top: 74px;
            z-index: 90;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .categories-nav::-webkit-scrollbar {
            height: 0;
        }

        .categories-list {
            display: flex;
            gap: 10px;
            max-width: 1400px;
            margin: 0 auto;
            justify-content: center;
            flex-wrap: wrap;
        }

        .category-pill {
            padding: 10px 20px;
            background: var(--orez-pink-light);
            color: var(--orez-red);
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            border: 2px solid transparent;
        }

        .category-pill:hover, .category-pill.active {
            background: var(--orez-red);
            color: white;
            transform: translateY(-2px);
        }

        /* Main Content */
        .catalog-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Category Section */
        .category-section {
            margin-bottom: 50px;
            scroll-margin-top: 150px;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--orez-pink-light);
        }

        .category-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--orez-red) 0%, var(--orez-pink) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--orez-red);
        }

        .category-count {
            background: var(--orez-pink-light);
            color: var(--orez-red);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        /* Product Card */
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }

        .product-image {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.08);
        }

        .product-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--orez-gold);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .product-variants-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--orez-pink);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .product-info {
            padding: 20px;
        }

        .product-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .product-description {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--orez-red);
        }

        .product-price-from {
            font-size: 0.75rem;
            color: #999;
            font-weight: 400;
        }

        .btn-add-cart {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--orez-red) 0%, var(--orez-pink) 100%);
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-add-cart:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 5px 20px rgba(233,30,99,0.4);
        }

        /* Variants Modal */
        .product-variants {
            padding: 0 20px 20px;
            border-top: 1px solid #f0f0f0;
            margin-top: -5px;
        }

        .variants-title {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 10px;
            padding-top: 15px;
        }

        .variants-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .variant-chip {
            padding: 6px 12px;
            background: var(--orez-pink-light);
            border: 2px solid transparent;
            border-radius: 20px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .variant-chip:hover, .variant-chip.selected {
            border-color: var(--orez-pink);
            background: white;
        }

        .variant-chip .chip-label {
            font-weight: 600;
            color: var(--orez-red);
        }

        .variant-chip .chip-price {
            color: #666;
            margin-left: 5px;
        }

        /* Login Prompt Modal */
        .login-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-modal.active {
            display: flex;
        }

        .login-modal-content {
            background: white;
            border-radius: 25px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            position: relative;
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .login-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
            transition: color 0.2s;
        }

        .login-modal-close:hover {
            color: var(--orez-red);
        }

        .login-modal-icon {
            width: 80px;
            height: 80px;
            background: var(--orez-pink-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: var(--orez-pink);
        }

        .login-modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--orez-red);
            margin-bottom: 10px;
        }

        .login-modal-text {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .login-modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-modal-login {
            padding: 14px 30px;
            background: linear-gradient(135deg, var(--orez-red) 0%, var(--orez-pink) 100%);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }

        .btn-modal-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233,30,99,0.3);
        }

        .btn-modal-register {
            padding: 14px 30px;
            background: white;
            color: var(--orez-red);
            border: 2px solid var(--orez-red);
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }

        .btn-modal-register:hover {
            background: var(--orez-pink-light);
        }

        /* Footer */
        .catalog-footer {
            background: linear-gradient(135deg, var(--orez-red) 0%, #5C0000 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .footer-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .footer-contact {
            margin: 20px 0;
        }

        .footer-contact a {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 15px;
            opacity: 0.9;
            transition: opacity 0.2s;
        }

        .footer-contact a:hover {
            opacity: 1;
        }

        .footer-copy {
            font-size: 0.85rem;
            opacity: 0.7;
            margin-top: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 10px;
                padding: 10px 15px;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-section {
                padding: 40px 15px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .product-image {
                height: 180px;
            }

            .product-info {
                padding: 12px;
            }

            .product-name {
                font-size: 0.95rem;
            }

            .product-price {
                font-size: 1.1rem;
            }

            .btn-add-cart {
                width: 38px;
                height: 38px;
                font-size: 0.9rem;
            }

            .categories-nav {
                top: 0;
            }

            .category-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-image {
                height: 220px;
            }
        }

        /* Image placeholder */
        .img-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--orez-pink-light) 0%, #fff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--orez-pink);
            font-size: 3rem;
        }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- Header -->
    <header class="catalog-header">
        <div class="header-top">
            <div class="logo-section">
                <?php if (!empty($negocio['logo'])): ?>
                    <img src="<?= htmlspecialchars($negocio['logo']) ?>" alt="Orez Floristería">
                <?php endif; ?>
                <div>
                    <div class="logo-text">OREZ</div>
                    <div class="logo-subtitle">Floristería</div>
                </div>
            </div>

            <div class="header-actions">
                <?php if ($usuario_logueado): ?>
                    <span class="user-greeting">Hola, <strong><?= htmlspecialchars($usuario_nombre) ?></strong></span>
                    <a href="carrito.php" class="btn-cart">
                        <i class="fas fa-shopping-bag"></i>
                        Mi Carrito
                        <span class="cart-count" id="cartCount">0</span>
                    </a>
                <?php else: ?>
                    <a href="login.php?redirect=catalogo_orez.php" class="btn-login">
                        <i class="fas fa-user"></i>
                        Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Catálogo de Flores</h1>
            <p class="hero-subtitle">Ramos artesanales con las flores más frescas. Envío a domicilio.</p>
            <span class="hero-badge"><i class="fas fa-heart"></i> Edición San Valentín</span>
        </div>
    </section>

    <!-- WhatsApp Banner -->
    <div class="whatsapp-banner">
        <span><i class="fab fa-whatsapp fa-lg"></i> ¿Tienes dudas o quieres un arreglo personalizado?</span>
        <a href="https://wa.me/523461035947" target="_blank">
            <i class="fab fa-whatsapp"></i> Contáctanos: +52 346 103 5947
        </a>
    </div>

    <!-- Categories Navigation -->
    <nav class="categories-nav">
        <div class="categories-list">
            <?php foreach (array_keys($categorias) as $cat): ?>
                <span class="category-pill" onclick="scrollToCategory('<?= htmlspecialchars(slugify($cat)) ?>')">
                    <?= htmlspecialchars($cat) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Main Catalog -->
    <main class="catalog-main">
        <?php foreach ($categorias as $cat_nombre => $cat_productos): ?>
            <?php if (empty($cat_productos)) continue; ?>

            <section class="category-section" id="<?= htmlspecialchars(slugify($cat_nombre)) ?>">
                <div class="category-header">
                    <div class="category-icon">
                        <?php
                        $icon = 'fa-seedling';
                        if (stripos($cat_nombre, 'rosa') !== false) $icon = 'fa-heart';
                        elseif (stripos($cat_nombre, 'tulip') !== false) $icon = 'fa-leaf';
                        elseif (stripos($cat_nombre, 'gerb') !== false) $icon = 'fa-sun';
                        elseif (stripos($cat_nombre, 'premium') !== false) $icon = 'fa-crown';
                        elseif (stripos($cat_nombre, 'arreglo') !== false) $icon = 'fa-gift';
                        ?>
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <h2 class="category-title"><?= htmlspecialchars($cat_nombre) ?></h2>
                    <span class="category-count"><?= count($cat_productos) ?> productos</span>
                </div>

                <div class="products-grid">
                    <?php foreach ($cat_productos as $producto): ?>
                        <?php
                        $id_prod = $producto['id_producto'];
                        $tiene_variantes = isset($variantes_map[$id_prod]);
                        $variantes = $tiene_variantes ? $variantes_map[$id_prod] : [];
                        $precio_min = floatval($producto['precio']);

                        // Calcular precio mínimo si hay variantes
                        if ($tiene_variantes) {
                            foreach ($variantes as $v) {
                                if ($v['precio'] < $precio_min) {
                                    $precio_min = $v['precio'];
                                }
                            }
                        }

                        $imagen = $producto['imagen'] ?? '';
                        if (empty($imagen) || !file_exists($imagen)) {
                            $imagen = '';
                        }
                        ?>
                        <article class="product-card" data-id="<?= $id_prod ?>">
                            <div class="product-image">
                                <?php if (!empty($imagen)): ?>
                                    <img src="<?= htmlspecialchars($imagen) ?>"
                                         alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="img-placeholder">
                                        <i class="fas fa-seedling"></i>
                                    </div>
                                <?php endif; ?>

                                <?php if ($tiene_variantes): ?>
                                    <span class="product-variants-badge">
                                        <i class="fas fa-layer-group"></i> Variantes
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($producto['nombre']) ?></h3>

                                <?php if (!empty($producto['descripcion'])): ?>
                                    <p class="product-description"><?= htmlspecialchars($producto['descripcion']) ?></p>
                                <?php endif; ?>

                                <div class="product-price-row">
                                    <div class="product-price">
                                        <?php if ($tiene_variantes && $precio_min < floatval($producto['precio'])): ?>
                                            <span class="product-price-from">Desde</span><br>
                                        <?php endif; ?>
                                        $<?= number_format($tiene_variantes ? $precio_min : $producto['precio'], 0) ?>
                                    </div>

                                    <button class="btn-add-cart"
                                            onclick="handleAddToCart(<?= $id_prod ?>, '<?= htmlspecialchars(addslashes($producto['nombre'])) ?>', <?= $producto['precio'] ?>, <?= $tiene_variantes ? 'true' : 'false' ?>)"
                                            title="Agregar al carrito">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if ($tiene_variantes && count($variantes) > 0): ?>
                                <div class="product-variants">
                                    <div class="variants-title">Tamaños disponibles:</div>
                                    <div class="variants-chips">
                                        <span class="variant-chip selected" data-price="<?= $producto['precio'] ?>">
                                            <span class="chip-label">Completo</span>
                                            <span class="chip-price">$<?= number_format($producto['precio'], 0) ?></span>
                                        </span>
                                        <?php foreach ($variantes as $v): ?>
                                            <span class="variant-chip" data-price="<?= $v['precio'] ?>" data-id="<?= $v['id'] ?>">
                                                <span class="chip-label"><?= ucfirst($v['tipo']) ?></span>
                                                <span class="chip-price">$<?= number_format($v['precio'], 0) ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <!-- Login Modal -->
    <div class="login-modal" id="loginModal">
        <div class="login-modal-content">
            <span class="login-modal-close" onclick="closeLoginModal()">&times;</span>
            <div class="login-modal-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h3 class="login-modal-title">Inicia sesión para comprar</h3>
            <p class="login-modal-text">
                Para agregar productos a tu carrito y realizar pedidos, necesitas una cuenta.
            </p>
            <div class="login-modal-buttons">
                <a href="login.php?redirect=catalogo_orez.php" class="btn-modal-login">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </a>
                <a href="register.php?redirect=catalogo_orez.php" class="btn-modal-register">
                    <i class="fas fa-user-plus"></i> Crear Cuenta
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="catalog-footer">
        <div class="footer-logo">OREZ Floristería</div>
        <p>Arreglos florales con amor y dedicación</p>
        <div class="footer-contact">
            <a href="https://wa.me/523461035947" target="_blank">
                <i class="fab fa-whatsapp"></i> +52 346 103 5947
            </a>
            <a href="mailto:contacto@orezfloreria.com">
                <i class="fas fa-envelope"></i> Contacto
            </a>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> Orez Floristería. Todos los derechos reservados.</p>
    </footer>

    <script>
        const isLoggedIn = <?= $usuario_logueado ? 'true' : 'false' ?>;

        // Scroll to category
        function scrollToCategory(slug) {
            const section = document.getElementById(slug);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth' });

                // Update active pill
                document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
                event.target.classList.add('active');
            }
        }

        // Handle add to cart
        function handleAddToCart(productId, productName, price, hasVariants) {
            if (!isLoggedIn) {
                showLoginModal();
                return;
            }

            // Si está logueado, redirigir al negocio con el producto
            window.location.href = `negocio.php?id=<?= OREZ_NEGOCIO_ID ?>&producto=${productId}`;
        }

        // Login modal
        function showLoginModal() {
            document.getElementById('loginModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal on backdrop click
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLoginModal();
            }
        });

        // Variant chip selection
        document.querySelectorAll('.variant-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                const parent = this.closest('.product-variants');
                parent.querySelectorAll('.variant-chip').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');

                // Update price display
                const card = this.closest('.product-card');
                const priceEl = card.querySelector('.product-price');
                const price = this.dataset.price;
                priceEl.innerHTML = `$${parseInt(price).toLocaleString()}`;
            });
        });

        // Slugify function
        function slugify(text) {
            return text.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '');
        }
    </script>
</body>
</html>

<?php
// Helper function to create URL-safe slugs
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'n-a';
}
?>
