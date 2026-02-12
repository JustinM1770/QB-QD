<?php
session_start();

if (isset($_SESSION['tipo_usuario'])) {
    if ($_SESSION['tipo_usuario'] === 'repartidor') {
        header("Location: admin/repartidor_dashboard.php");
        exit();
    } elseif ($_SESSION['tipo_usuario'] === 'negocio') {
        header("Location: admin/negocio_configuracion.php");
        exit();
    }
}

require_once 'config/database.php';
require_once 'models/Categoria.php';

$database = new Database();
$db = $database->getConnection();

$categoria = new Categoria($db);
$categorias = $categoria->obtenerTodas();

$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Categorías - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png" />
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/soft-ui.css?v=2.0">
    <script src="assets/js/theme-handler.js?v=2.1"></script>
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: rgba(1, 101, 255, 0.08);
            --primary-dark: #0153CC;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #212529;
            --light: #FFFFFF;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --gray-800: #343a40;
            --gray-900: #212529;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            padding-bottom: 100px;
        }
        
        /* Header estilo QuickBite */
        .page-header {
            background: var(--light);
            padding: 16px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 16px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--gray-100);
            border-radius: 12px;
            color: var(--gray-800);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .back-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .page-title {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
            color: var(--gray-900);
        }
        
        /* Container principal */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px;
        }
        
        /* Descripción de la página */
        .page-description {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .page-description p {
            color: var(--gray-600);
            font-size: 1rem;
            margin: 0;
        }
        
        /* Grid de categorías */
        .categoria-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
        }
        
        @media (min-width: 768px) {
            .categoria-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 20px;
            }
        }
        
        /* Cards de categoría */
        .categoria-card {
            background: var(--light);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .categoria-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--primary);
        }
        
        .categoria-card:active {
            transform: translateY(-2px);
        }
        
        .categoria-icono {
            width: 100px;
            height: 100px;
            background-size: cover;
            background-position: center;
            border-radius: 16px;
            margin-bottom: 12px;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        @media (min-width: 768px) {
            .categoria-icono {
                width: 120px;
                height: 120px;
            }
        }
        
        .categoria-card:hover .categoria-icono {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        
        .categoria-nombre {
            font-weight: 600;
            font-size: 0.95rem;
            text-align: center;
            color: var(--gray-900);
            line-height: 1.3;
        }
        
        .categoria-descripcion {
            font-size: 0.8rem;
            color: var(--gray-600);
            text-align: center;
            margin-top: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .empty-state-icon i {
            font-size: 2rem;
            color: var(--gray-600);
        }
        
        .empty-state h3 {
            color: var(--gray-800);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--gray-600);
        }
        
        /* Bottom Navigation (consistente con index.php) */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--light);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0 calc(8px + env(safe-area-inset-bottom));
            box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
            z-index: 1000;
            border-top: 1px solid var(--gray-200);
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 0.7rem;
            padding: 4px 12px;
            transition: var(--transition);
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            margin-bottom: 4px;
        }
        
        .central-btn {
            width: 56px;
            height: 56px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: -20px;
            box-shadow: 0 4px 15px rgba(1, 101, 255, 0.4);
        }
        
        .central-btn .nav-icon {
            filter: brightness(0) invert(1);
        }
        
        /* Modo oscuro */
        @media (prefers-color-scheme: dark) {
            :root {
                --light: #000000;
                --dark: #ffffff;
                --gray-100: #1a1a1a;
                --gray-200: #333333;
                --gray-600: #aaaaaa;
                --gray-800: #e0e0e0;
                --gray-900: #ffffff;
            }
            
            body {
                background-color: #000000;
            }
            
            .page-header {
                background: #000000;
                border-color: #333;
            }
            
            .categoria-card {
                background: #111111;
                border-color: #333;
            }
            
            .bottom-nav {
                background: rgba(0, 0, 0, 0.95);
                border-color: #333;
            }
            
            .nav-icon {
                filter: invert(1) brightness(2);
            }
            
            .nav-item.active .nav-icon {
                filter: none;
            }
        }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- Header -->
    <header class="page-header">
        <div class="header-content">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Categorías</h1>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="main-container">
        <div class="page-description">
            <p>Explora todas las categorías disponibles</p>
        </div>

        <?php if (empty($categorias)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3>Sin categorías</h3>
                <p>No hay categorías disponibles en este momento.</p>
            </div>
        <?php else: ?>
            <section class="categoria-grid">
                <?php foreach ($categorias as $cat): ?>
                    <a href="categoria.php?id=<?php echo $cat['id_categoria']; ?>" class="categoria-card">
                        <div class="categoria-icono" style="background-image: url('<?php echo $cat['icono'] ? htmlspecialchars($cat['icono']) : 'assets/img/categories/default.jpg'; ?>');"></div>
                        <div class="categoria-nombre"><?php echo htmlspecialchars($cat['nombre']); ?></div>
                        <?php if (!empty($cat['descripcion'])): ?>
                            <div class="categoria-descripcion"><?php echo htmlspecialchars($cat['descripcion']); ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <img src="assets/icons/home.png" alt="Inicio" class="nav-icon">
            <span>Inicio</span>
        </a>
        <a href="buscar.php" class="nav-item active">
            <img src="assets/icons/search.png" alt="Buscar" class="nav-icon">
            <span>Buscar</span>
        </a>
        <a href="<?php echo $usuario_logueado ? 'carrito.php' : 'login.php'; ?>" class="central-btn">
            <img src="assets/icons/cart.png" alt="Carrito" class="nav-icon">
        </a>
        <a href="<?php echo $usuario_logueado ? 'favoritos.php' : 'login.php'; ?>" class="nav-item">
            <img src="assets/icons/fav.png" alt="Favoritos" class="nav-icon">
            <span>Favoritos</span>
        </a>
        <a href="<?php echo $usuario_logueado ? 'perfil.php' : 'login.php'; ?>" class="nav-item">
            <img src="assets/icons/user.png" alt="Perfil" class="nav-icon">
            <span>Perfil</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
