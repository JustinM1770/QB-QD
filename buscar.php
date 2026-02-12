<?php
// Iniciar sesión
session_start();

if (isset($_SESSION['tipo_usuario'])) {
    if ($_SESSION['tipo_usuario'] === 'repartidor') {
        header("Location: admin/repartidor_dashboard.php");
        exit(); // IMPORTANTE: Detiene la ejecución
    } elseif ($_SESSION['tipo_usuario'] === 'negocio') {
        header("Location: admin/negocio_configuracion.php");
        exit(); // IMPORTANTE
    }
}

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Categoria.php';
require_once 'models/Membership.php';
require_once 'models/Pedido.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Si está logueado, obtener información adicional del usuario y membresía
if ($usuario_logueado) {
    $usuario = new Usuario($db);
    $usuario->id_usuario = $_SESSION["id_usuario"];
    $usuario->obtenerPorId();

    // Verificar si el usuario tiene un pedido pendiente
    $pedido = new Pedido($db);
    $pedidoPendiente = null;
    $pedidosPendientes = [];
    try {
        if (!empty($usuario->id_usuario)) {
            $pedidosPendientes = $pedido->obtenerPorUsuario($usuario->id_usuario);
        }
    } catch (Exception $e) {
        error_log("Error obteniendo pedidos pendientes: " . $e->getMessage());
    }
    foreach ($pedidosPendientes as $p) {
        if (isset($p['id_estado']) && $p['id_estado'] == 1) { // Estado pendiente
            $pedidoPendiente = $p;
            break;
        }
    }

    $membership = new Membership($db);
    $membership->id_usuario = $_SESSION["id_usuario"];
    $esMiembroActivo = $membership->isActive();
} else {
    $esMiembroActivo = false;
    $pedidoPendiente = null;
}

// Obtener todas las categorías
$categoria = new Categoria($db);
$todasCategorias = $categoria->obtenerTodas();

// Buscar negocios si hay un término de búsqueda
$termino_busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$categoria_filtro = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$negocios_resultado = [];

// Instanciar modelo de negocio
$negocio = new Negocio($db);

if (!empty($termino_busqueda) || $categoria_filtro) {
    if ($categoria_filtro) {
        $negocios_resultado = $negocio->obtenerPorCategoria($categoria_filtro);
    } else {
        $negocios_resultado = $negocio->buscar($termino_busqueda);
    }
}

// Obtener negocios populares como sugerencias
$negocios_populares = $negocio->obtenerDestacados(6);

// Búsquedas sugeridas
$busquedas_sugeridas = [
    'Pizza', 'Hamburguesas', 'Sushi', 'Tacos', 'Café', 'Postres',
    'Comida china', 'Comida italiana', 'Comida mexicana', 'Bebidas'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar - QuickBite</title>
    
    <!-- Global Theme CSS y JS (Modo Oscuro Persistente) -->
    <link rel="stylesheet" href="assets/css/global-theme.css?v=2.1">
    <script src="assets/js/theme-handler.js?v=2.1"></script>
    
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/transitions.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
   <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
/* Variables CSS */
:root {
    --primary: #0165FF;
    --primary-dark: #0052cc;
    --primary-light: rgba(1, 101, 255, 0.08);
    --secondary: #F8F8F8;
    --accent: #2C2C2C;
    --dark: #2F2F2F;
    --light: #FAFAFA;
    --success: #10b981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --info: #0EA5E9;
    --white: #ffffff;
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-400: #94a3b8;
    --gray-500: #64748b;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    --gradient: linear-gradient(135deg, #0165FF 0%, #0165FF 100%);
    --border-radius: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-full: 50px;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 12px -1px rgba(0, 0, 0, 0.08);
    --shadow-lg: 0 8px 25px -8px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-fast: all 0.15s ease-out;
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    --spacing-2xl: 48px;
}

/* Reset y base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--light);
    color: var(--gray-700);
    line-height: 1.6;
    font-weight: 400;
    overflow-x: hidden;
    
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'Nunito', sans-serif;
    font-weight: 600;
    color: var(--gray-900);
    line-height: 1.25;
    margin: 0;
}

/* Container principal */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--spacing-md);
    padding-bottom: 120px;
}

/* Header de búsqueda */
.search-header {
    position: sticky;
    top: 0;
    background: var(--light);
    padding: var(--spacing-md) 0;
    z-index: 100;
    border-bottom: 1px solid var(--gray-100);
    backdrop-filter: blur(20px);
    animation: slideDown 0.6s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.search-form {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-md);
}

.search-bar {
    flex: 1;
    max-width: 480px;
    position: relative;
    background: var(--white);
    border-radius: var(--border-radius-full);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-100);
    overflow: hidden;
    backdrop-filter: blur(20px);
    transition: var(--transition);
    transform: translateY(0);
    height: 44px;
}

.search-bar:focus-within {
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.search-bar button {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-400);
    font-size: 1rem;
    transition: var(--transition);
    z-index: 2;
    padding: 6px;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-bar:focus-within button {
    color: var(--primary);
    background: var(--primary-light);
}

.search-bar input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    border: none;
    outline: none;
    font-size: 0.95rem;
    background: transparent;
    color: var(--gray-700);
    font-weight: 400;
    height: 44px;
    line-height: 1.2;
}

.search-bar input::placeholder {
    color: var(--gray-400);
    font-weight: 400;
}

.btn-back {
    background: var(--white);
    border: 1px solid var(--gray-100);
    border-radius: 50%;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    text-decoration: none;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
}

.btn-back:hover {
    background: var(--gray-50);
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Filtros de categoría */
.category-filters {
    display: flex;
    gap: var(--spacing-sm);
    overflow-x: auto;
    padding: var(--spacing-lg) 0;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

.category-filters::-webkit-scrollbar {
    display: none;
}

.category-filter {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-full);
    padding: var(--spacing-sm) var(--spacing-md);
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--transition);
    white-space: nowrap;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    box-shadow: var(--shadow-sm);
}

.category-filter:hover,
.category-filter.active {
    background: var(--primary);
    color: var(--white);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Búsquedas sugeridas */
.suggested-searches {
    margin: var(--spacing-xl) 0;
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: var(--spacing-lg);
    letter-spacing: -0.01em;
}

.suggested-items {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.suggested-item {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-full);
    padding: var(--spacing-sm) var(--spacing-md);
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}

.suggested-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 0;
    height: 100%;
    background: var(--primary-light);
    transition: var(--transition);
    z-index: 0;
}

.suggested-item:hover::before {
    width: 100%;
}

.suggested-item:hover {
    color: var(--primary);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.suggested-item > * {
    position: relative;
    z-index: 1;
}

/* Resultados de búsqueda */
.search-results {
    margin: var(--spacing-xl) 0;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.results-count {
    color: var(--gray-500);
    font-size: 0.9rem;
}

/* Grid de restaurantes */
.restaurant-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-lg);
    justify-content: center;
}

.restaurant-card {
    background: var(--white);
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-100);
    position: relative;
    animation: fadeInUp 0.6s ease-out both;
}

.restaurant-card:nth-child(1) { animation-delay: 0.1s; }
.restaurant-card:nth-child(2) { animation-delay: 0.2s; }
.restaurant-card:nth-child(3) { animation-delay: 0.3s; }
.restaurant-card:nth-child(4) { animation-delay: 0.4s; }

.restaurant-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary-light), transparent);
    opacity: 0;
    transition: var(--transition);
    z-index: 0;
    border-radius: var(--border-radius-xl);
}

.restaurant-card:hover::before {
    opacity: 1;
}

.restaurant-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--shadow-xl);
    color: inherit;
    border-color: var(--primary);
}

.restaurant-card > * {
    position: relative;
    z-index: 1;
}

.card-img {
    height: 200px;
    background-size: cover;
    background-position: center;
    background-color: var(--gray-100);
    position: relative;
    overflow: hidden;
}

.card-img::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(0, 0, 0, 0) 0%, 
        rgba(0, 0, 0, 0.1) 100%);
    transition: var(--transition);
}

.restaurant-card:hover .card-img::before {
    background: linear-gradient(135deg, 
        rgba(1, 101, 255, 0.1) 0%, 
        rgba(1, 101, 255, 0.2) 100%);
}

.card-content {
    padding: var(--spacing-lg);
}

.restaurant-name {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
    gap: var(--spacing-md);
}

.restaurant-name span:first-child {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--gray-900);
    flex: 1;
    line-height: 1.3;
}

.rating {
    background: var(--primary);
    color: var(--white);
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--border-radius-full);
    font-size: 0.85rem;
    font-weight: 600;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 2px;
}

.restaurant-info {
    color: var(--gray-500);
    font-size: 0.9rem;
    margin-bottom: var(--spacing-md);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    line-height: 1.4;
}

.restaurant-info i {
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}

.divider {
    color: var(--gray-300);
    margin: 0 var(--spacing-xs);
}

.restaurant-categories {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
    justify-content: flex-start;
}

.category-badge {
    background: var(--gray-100);
    color: var(--gray-600);
    padding: var(--spacing-xs) var(--spacing-md);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition-fast);
}

.restaurant-card:hover .category-badge {
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* Sin resultados */
.no-results {
    text-align: center;
    padding: var(--spacing-2xl) var(--spacing-xl);
    color: var(--gray-500);
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.no-results i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: var(--spacing-lg);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.05); opacity: 1; }
}

.no-results h3 {
    font-size: 1.5rem;
    margin-bottom: var(--spacing-md);
    color: var(--gray-700);
    font-weight: 600;
}

.no-results p {
    margin-bottom: var(--spacing-xl);
    font-size: 1.1rem;
    line-height: 1.6;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.bottom-nav {
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    height: 70px;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 0 10px;
    padding-bottom: env(safe-area-inset-bottom);
    z-index: 9999 !important; /* Prioridad máxima */
    box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
    border-radius: 25px 25px 0 0;
    margin: 0 !important;
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
    filter: grayscale(100%) brightness(0.6) opacity(0.7);
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
    transform: translateY(-28px); /* Ajustado para que resalte más */
    background: var(--primary);
    width: 65px;
    height: 65px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 6px solid var(--light); /* Debe coincidir con el fondo del body */
    box-shadow: 0 10px 20px rgba(1, 101, 255, 0.3);
    z-index: 10000; /* Un poco más que el nav */
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
/* Responsive design */
@media (max-width: 768px) {
    .container {
        padding: 0 var(--spacing-md);
        padding-bottom: 120px;
    }

    .search-form {
        flex-direction: row;
        gap: var(--spacing-md);
    }

    .search-bar {
        flex: 1;
    }

    .category-filters {
        padding: var(--spacing-md) 0;
    }

    .restaurant-cards {
        grid-template-columns: 1fr;
        gap: var(--spacing-lg);
    }

    .restaurant-card .card-img {
        height: 180px;
    }

    .restaurant-card .card-content {
        padding: var(--spacing-lg);
    }

    .bottom-nav {
        padding: var(--spacing-md) 0;
    }

    .nav-item {
        font-size: 0.75rem;
        padding: var(--spacing-sm);
        min-width: 50px;
    }

    .nav-item i {
        font-size: 1.25rem;
        margin-bottom: var(--spacing-xs);
    }

    .central-btn {
        width: 56px;
        height: 56px;
        top: -8px;
    }
}

@media (max-width: 480px) {
    .suggested-items {
        gap: var(--spacing-xs);
    }

    .category-filters {
        gap: var(--spacing-xs);
    }

    .restaurant-card .card-img {
        height: 160px;
    }

    .restaurant-card .card-content {
        padding: var(--spacing-md);
    }
}

/* Focus states para accesibilidad */
button:focus,
input:focus,
a:focus {
    outline: 3px solid var(--primary);
    outline-offset: 2px;
    border-radius: var(--border-radius);
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Micro-interactions adicionales */
.hover-lift {
    transition: var(--transition);
}

.hover-lift:hover {
    transform: translateY(-2px);
}

.fade-in {
    animation: fadeIn 0.6s ease-out;
}



img {
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
}

/* Reducir movimiento para usuarios con preferencia de accesibilidad */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* =======================================================
   MODO OSCURO - BUSCAR.PHP
   ======================================================= */
@media (prefers-color-scheme: dark) {
    :root {
        --body-bg: #000000;
        --white: #111111;
        --light: #000000;
        --gray-50: #111111;
        --gray-100: #1a1a1a;
        --gray-200: #333333;
        --gray-900: #ffffff;
        --gray-800: #e0e0e0;
        --gray-700: #cccccc;
        --gray-600: #aaaaaa;
        --gray-500: #888888;
        --gray-400: #666666;
        --nav-bg: rgba(0, 0, 0, 0.95);
    }

    body {
        background-color: #000000 !important;
        color: #e0e0e0;
    }

    /* Header de búsqueda */
    .search-header {
        background: #000000 !important;
        border-bottom: 1px solid #333;
    }

    /* Barra de búsqueda */
    .search-bar {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .search-bar input {
        color: #fff !important;
    }

    .search-bar input::placeholder {
        color: #888;
    }

    .search-bar button {
        color: #888;
    }

    .search-bar:focus-within button {
        color: var(--primary);
    }

    /* Botón volver */
    .btn-back {
        background: #111111 !important;
        border-color: #333 !important;
        color: #fff !important;
    }

    .btn-back:hover {
        background: #222 !important;
        color: var(--primary) !important;
    }

    /* Filtros de categoría */
    .category-filter {
        background: #111111 !important;
        border-color: #333 !important;
        color: #e0e0e0 !important;
    }

    .category-filter:hover,
    .category-filter.active {
        background: var(--primary) !important;
        color: #fff !important;
        border-color: var(--primary) !important;
    }

    /* Títulos de sección */
    .section-title {
        color: #fff !important;
    }

    /* Items sugeridos */
    .suggested-item {
        background: #111111 !important;
        border-color: #333 !important;
        color: #e0e0e0 !important;
    }

    .suggested-item:hover {
        color: var(--primary) !important;
        border-color: var(--primary) !important;
    }

    .suggested-item::before {
        background: rgba(1, 101, 255, 0.2) !important;
    }

    /* Tarjetas de restaurante */
    .restaurant-card {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .restaurant-card:hover {
        border-color: var(--primary) !important;
    }

    .restaurant-card::before {
        background: linear-gradient(135deg, rgba(1, 101, 255, 0.1), transparent) !important;
    }

    .card-content {
        background: #111111 !important;
    }

    .restaurant-name span:first-child {
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
        color: #0165FF !important;
    }

    /* Sin resultados */
    .no-results {
        color: #aaa !important;
    }

    .no-results h3 {
        color: #fff !important;
    }

    .no-results i {
        color: #444 !important;
    }

    /* Contador de resultados */
    .results-count {
        color: #888 !important;
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

    /* Icono activo (azul) no invertir */
    .nav-item.active .nav-icon {
        filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%) !important;
    }

    /* Botón central */
    .central-btn {
        border-color: #000 !important;
    }

    .central-btn .nav-icon {
        filter: brightness(0) invert(1) !important;
    }

    /* Textos del nav */
    .nav-item span {
        color: #fff !important;
    }

    .nav-item.active span {
        color: var(--primary) !important;
    }
}

/* Soporte para data-theme="dark" y clase .dark-mode */
[data-theme="dark"] body,
html.dark-mode body,
body.dark-mode {
    background-color: #000000 !important;
    color: #e0e0e0;
}

[data-theme="dark"] .search-header,
html.dark-mode .search-header {
    background: #000000 !important;
    border-bottom: 1px solid #333;
}

[data-theme="dark"] .search-bar,
html.dark-mode .search-bar {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .search-bar input,
html.dark-mode .search-bar input {
    color: #fff !important;
}

[data-theme="dark"] .btn-back,
html.dark-mode .btn-back {
    background: #111111 !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .category-filter,
html.dark-mode .category-filter {
    background: #111111 !important;
    border-color: #333 !important;
    color: #e0e0e0 !important;
}

[data-theme="dark"] .category-filter:hover,
[data-theme="dark"] .category-filter.active,
html.dark-mode .category-filter:hover,
html.dark-mode .category-filter.active {
    background: var(--primary) !important;
    color: #fff !important;
    border-color: var(--primary) !important;
}

[data-theme="dark"] .section-title,
html.dark-mode .section-title {
    color: #fff !important;
}

[data-theme="dark"] .suggested-item,
html.dark-mode .suggested-item {
    background: #111111 !important;
    border-color: #333 !important;
    color: #e0e0e0 !important;
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

[data-theme="dark"] .restaurant-name span:first-child,
html.dark-mode .restaurant-name span:first-child {
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

[data-theme="dark"] .no-results,
html.dark-mode .no-results {
    color: #aaa !important;
}

[data-theme="dark"] .no-results h3,
html.dark-mode .no-results h3 {
    color: #fff !important;
}
</style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <div class="container">
        <!-- Header de búsqueda -->
        <header class="search-header">
            <form action="buscar.php" method="GET" class="search-form">
                <a href="index.php" class="btn-back" aria-label="Volver al inicio">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="search-bar">
                    <button type="submit" aria-label="Buscar">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" name="buscar" placeholder="Buscar restaurantes..." 
       value="<?php echo htmlspecialchars($termino_busqueda); ?>">
                </div>
            </form>
        </header>

        <!-- Filtros de categoría -->
        <?php if (!empty($todasCategorias)): ?>
        <section class="category-filters">
            <a href="buscar.php" class="category-filter <?php echo !$categoria_filtro ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i>
                Todas
            </a>
            <?php foreach ($todasCategorias as $cat): ?>
                <a href="buscar.php?categoria=<?php echo $cat['id_categoria']; ?>" 
                   class="category-filter <?php echo $categoria_filtro == $cat['id_categoria'] ? 'active' : ''; ?>">
                    <i class="<?php echo $cat['icono'] ? $cat['icono'] : 'fas fa-utensils'; ?>"></i>
                    <?php echo $cat['nombre']; ?>
                </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if (empty($termino_busqueda) && !$categoria_filtro): ?>
            <!-- Búsquedas sugeridas -->
            <section class="suggested-searches">
                <h2 class="section-title">Búsquedas populares</h2>
                <div class="suggested-items">
                    <?php foreach ($busquedas_sugeridas as $index => $sugerencia): ?>
                        <a href="buscar.php?buscar=<?php echo urlencode($sugerencia); ?>" 
                           class="suggested-item" 
                           style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                            <i class="fas fa-search"></i>
                            <?php echo $sugerencia; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Restaurantes populares -->
            <section class="search-results">
                <div class="results-header">
                    <h2 class="section-title">Restaurantes populares</h2>
                </div>
                
                <div class="restaurant-cards">
                    <?php if (!empty($negocios_populares)): ?>
                        <?php foreach ($negocios_populares as $neg): ?>
                            <a href="negocio.php?id=<?php echo $neg['id_negocio']; ?>" class="restaurant-card hover-lift">
                                <div class="card-img" style="background-image: url('<?php echo $neg['imagen_portada'] ? $neg['imagen_portada'] : 'assets/img/restaurants/default.jpg'; ?>');"></div>
                                <div class="card-content">
                                    <div class="restaurant-name">
                                        <span><?php echo htmlspecialchars($neg['nombre']); ?></span>
                                        <span class="rating"><?php echo number_format($neg['rating'], 1); ?></span>
                                    </div>
                                    <div class="restaurant-info">
                                        <i class="fas fa-clock"></i> <?php echo $neg['tiempo_preparacion_promedio']; ?> min
                                        <span class="divider">|</span>
                                        <i class="fas fa-motorcycle"></i> Envío $<?php echo number_format($neg['costo_envio'], 2); ?>
                                    </div>
                                    <div class="restaurant-categories">
        <?php if (!empty($neg['categorias'])): ?>
        <?php foreach ($neg['categorias'] as $cat_nombre): ?>
            <span class="category-badge"><?php echo htmlspecialchars((string)$cat_nombre); ?></span>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Restaurantes de ejemplo si no hay datos -->
                        <a href="negocio.php?id=1" class="restaurant-card hover-lift">
                            <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400');"></div>
                            <div class="card-content">
                                <div class="restaurant-name">
                                    <span>Pizza Palace</span>
                                    <span class="rating">4.8</span>
                                </div>
                                <div class="restaurant-info">
                                    <i class="fas fa-clock"></i> 25-35 min
                                    <span class="divider">|</span>
                                    <i class="fas fa-motorcycle"></i> Envío $15.50
                                </div>
                                <div class="restaurant-categories">
                                    <span class="category-badge">Pizza</span>
                                    <span class="category-badge">Italiana</span>
                                </div>
                            </div>
                        </a>
                        <a href="negocio.php?id=2" class="restaurant-card hover-lift">
                            <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400');"></div>
                            <div class="card-content">
                                <div class="restaurant-name">
                                    <span>Burger House</span>
                                    <span class="rating">4.5</span>
                                </div>
                                <div class="restaurant-info">
                                    <i class="fas fa-clock"></i> 15-25 min
                                    <span class="divider">|</span>
                                    <i class="fas fa-motorcycle"></i> Envío $12.99
                                </div>
                                <div class="restaurant-categories">
                                    <span class="category-badge">Hamburguesas</span>
                                    <span class="category-badge">Comida rápida</span>
                                </div>
                            </div>
                        </a>
                        <a href="negocio.php?id=3" class="restaurant-card hover-lift">
                            <div class="card-img" style="background-image: url('https://images.unsplash.com/photo-1579952363873-27d3bfad9c0d?w=400');"></div>
                            <div class="card-content">
                                <div class="restaurant-name">
                                    <span>Sushi Garden</span>
                                    <span class="rating">4.7</span>
                                </div>
                                <div class="restaurant-info">
                                    <i class="fas fa-clock"></i> 30-40 min
                                    <span class="divider">|</span>
                                    <i class="fas fa-motorcycle"></i> Envío $18.00
                                </div>
                                <div class="restaurant-categories">
                                    <span class="category-badge">Sushi</span>
                                    <span class="category-badge">Japonesa</span>
                                </div>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

        <?php else: ?>
            <!-- Resultados de búsqueda -->
            <section class="search-results">
                <div class="results-header">
                    <h2 class="section-title">
                <?php 
                if ($categoria_filtro) {
                    $nombre_cat = "Categoría";
                    foreach ($todasCategorias as $cat) {
                        if ($cat['id_categoria'] == $categoria_filtro) {
                            $nombre_cat = $cat['nombre'];
                            break;
                        }
                    }
                    echo "Restaurantes de " . htmlspecialchars($nombre_cat);
                } else {
                    echo "Resultados para \"" . htmlspecialchars($termino_busqueda) . "\"";
                }
                ?>
            </h2>
                    <?php if (!empty($negocios_resultado)): ?>
                        <div class="results-count">
                            <?php echo count($negocios_resultado); ?> restaurante<?php echo count($negocios_resultado) != 1 ? 's' : ''; ?> encontrado<?php echo count($negocios_resultado) != 1 ? 's' : ''; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($negocios_resultado)): ?>
                    <div class="restaurant-cards">
                        <?php foreach ($negocios_resultado as $neg): ?>
                            <a href="negocio.php?id=<?php echo $neg['id_negocio']; ?>" class="restaurant-card hover-lift">
                                <div class="card-img" style="background-image: url('<?php echo $neg['imagen_portada'] ? $neg['imagen_portada'] : 'assets/img/restaurants/default.jpg'; ?>');"></div>
                                <div class="card-content">
                                    <div class="restaurant-name">
                                        <span><?php echo htmlspecialchars($neg['nombre']); ?></span>
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
                                                <span class="category-badge"><?php echo htmlspecialchars($cat); ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- No hay resultados -->
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No se encontraron resultados</h3>
                        <p>
                            <?php if ($categoria_filtro): ?>
                                No encontramos restaurantes en esta categoría. Prueba con otra categoría o explora todas las opciones.
                            <?php else: ?>
                                No hemos encontrado resultados para "<?php echo htmlspecialchars($termino_busqueda); ?>". Intenta con otra búsqueda o explora nuestras categorías.
                            <?php endif; ?>
                        </p>
                        <a href="buscar.php" class="suggested-item hover-lift" style="margin-top: 1rem;">
                            <i class="fas fa-arrow-left"></i>
                            Explorar todas las opciones
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Alert for pending order -->
        <?php if ($pedidoPendiente): ?>
            <div class="alert alert-warning d-flex align-items-center justify-content-center gap-2 text-center fade-in" 
                 role="alert" 
                 style="margin: 2rem 0; padding: 1rem 2rem; font-weight: 600; font-size: 1rem; border-radius: 12px; background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;">
                <i class="fas fa-clock" style="color: #f39c12;"></i>
                <span>
                    Tienes un pedido en curso. 
                    <a href="confirmacion_pedido.php?id=<?php echo htmlspecialchars($pedidoPendiente['id_pedido']); ?>" 
                       style="text-decoration: underline; font-weight: 700; color: #856404;">
                        Ver estado del pedido
                    </a>
                </span>
            </div>
        <?php endif; ?>
    </div>
    </div>


    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
    <a href="index.php" class="nav-item qb-transition">
        <img src="assets/icons/home.png" alt="Inicio" class="nav-icon">
        <span>Inicio</span>
    </a>
    <a href="buscar.php" class="nav-item active qb-transition">
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
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus en el campo de búsqueda si está vacío
            const searchInput = document.querySelector('input[name="buscar"]');
            if (searchInput && !searchInput.value.trim()) {
                searchInput.focus();
            }

            // Agregar efectos de entrada escalonados a las tarjetas
            const restaurantCards = document.querySelectorAll('.restaurant-card');
            const suggestedItems = document.querySelectorAll('.suggested-item');
            
            // Intersection Observer para animaciones al hacer scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observar elementos para animaciones
            [...restaurantCards, ...suggestedItems].forEach(card => {
                card.style.animationPlayState = 'paused';
                observer.observe(card);
            });
            
            // Efecto de ripple en elementos interactivos
            const interactiveElements = document.querySelectorAll('.suggested-item, .category-filter, .restaurant-card');
            interactiveElements.forEach(element => {
                element.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(1, 101, 255, 0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                        z-index: 1;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        if (ripple.parentNode) {
                            ripple.parentNode.removeChild(ripple);
                        }
                    }, 600);
                });
            });
            
            // Agregar estilo para la animación ripple
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    from {
                        transform: scale(0);
                        opacity: 1;
                    }
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Smooth scroll horizontal para filtros de categoría
            const categoryFilters = document.querySelector('.category-filters');
            if (categoryFilters) {
                let isDown = false;
                let startX;
                let scrollLeft;

                categoryFilters.addEventListener('mousedown', (e) => {
                    isDown = true;
                    startX = e.pageX - categoryFilters.offsetLeft;
                    scrollLeft = categoryFilters.scrollLeft;
                });

                categoryFilters.addEventListener('mouseleave', () => {
                    isDown = false;
                });

                categoryFilters.addEventListener('mouseup', () => {
                    isDown = false;
                });

                categoryFilters.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - categoryFilters.offsetLeft;
                    const walk = (x - startX) * 2;
                    categoryFilters.scrollLeft = scrollLeft - walk;
                });
            }
            
            // Lazy loading para imágenes
            const images = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(img => imageObserver.observe(img));
            
            // Búsqueda en tiempo real (opcional)
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        // Aquí podrías implementar búsqueda AJAX en tiempo real
                        console.log('Búsqueda en tiempo real:', query);
                    }, 500);
                }
            });
        });
        
        // Optimización de performance
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                window.dispatchEvent(new Event('resize-end'));
            }, 250);
        });
    </script>
    <script src="assets/js/transitions.js"></script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>