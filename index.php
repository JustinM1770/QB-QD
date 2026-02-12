<?php
// ==========================================
// 1. CONFIGURACIÓN INICIAL Y DEPENDENCIAS
// ==========================================

// Manejador de errores centralizado
require_once __DIR__ . '/config/error_handler.php';

// Iniciar sesión
session_start();

// Validar tipo de usuario (Redirecciones de seguridad)
if (isset($_SESSION['tipo_usuario'])) {
    if ($_SESSION['tipo_usuario'] === 'repartidor') {
        header("Location: admin/repartidor_dashboard.php");
        exit();
    } elseif ($_SESSION['tipo_usuario'] === 'negocio') {
        header("Location: admin/negocio_configuracion.php");
        exit();
    }
    // Si es cliente, continúa normalmente
}

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'includes/business_helpers.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Categoria.php';
require_once 'models/Membership.php';
require_once 'models/Pedido.php';
require_once 'models/PromotionalBanner.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// ==========================================
// 2. DATOS GENERALES (CATEGORÍAS Y BANNERS)
// ==========================================

// Obtener categorías populares
$categoria = new Categoria($db);
$categorias_populares = $categoria->obtenerPopulares();

// Obtener banners promocionales activos
$promotional_banner = new PromotionalBanner($db);
$banners_activos = $promotional_banner->obtenerActivos();

foreach ($banners_activos as $banner) {
    mostrarBannerModal($banner);
}

// Script para abrir el primer banner automáticamente
if (count($banners_activos) > 0) {
    echo '
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var firstBannerModal = document.getElementById("bannerModal" + ' . $banners_activos[0]['id_banner'] . ');
            if (firstBannerModal) {
                var modal = new bootstrap.Modal(firstBannerModal);
                modal.show();
            }
        });
    </script>
    ';
}

// ==========================================
// 3. LÓGICA PRINCIPAL DE NEGOCIOS (FILTRADO INTELIGENTE)
// ==========================================

// Capturar variables de la URL
$termino_busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$municipio_actual = isset($_GET['municipio']) ? trim($_GET['municipio']) : null;

$negocio = new Negocio($db);
$negocios_destacados = [];

// DECIDIR QUÉ MOSTRAR (EN ORDEN DE PRIORIDAD)
if (!empty($termino_busqueda)) {
    // PRIORIDAD 1: El usuario está buscando algo específico
    $negocios_destacados = $negocio->buscar($termino_busqueda);

} elseif (!empty($municipio_actual)) {
    // PRIORIDAD 2: Filtrar por Municipio (Smart Location)
    // Obtenemos TODOS los negocios activos para filtrar por municipio (límite alto)
    $todos = $negocio->obtenerDestacados(100); 
    
    // Función helper para normalizar texto (quitar acentos y espacios extra)
    $normalizar = function($texto) {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $texto
        );
        return preg_replace('/\s+/', ' ', $texto);
    };
    
    $municipio_normalizado = $normalizar($municipio_actual);
    
    foreach ($todos as $n) {
        // Validamos que existan las columnas en tu BD para evitar errores
        $muni_bd = isset($n['municipio']) ? $normalizar($n['municipio']) : '';
        $ciud_bd = isset($n['ciudad']) ? $normalizar($n['ciudad']) : '';
        
        // Comparamos normalizado sin importar acentos ni mayúsculas
        if (
            (!empty($muni_bd) && (strpos($muni_bd, $municipio_normalizado) !== false || strpos($municipio_normalizado, $muni_bd) !== false)) ||
            (!empty($ciud_bd) && (strpos($ciud_bd, $municipio_normalizado) !== false || strpos($municipio_normalizado, $ciud_bd) !== false))
        ) {
            $negocios_destacados[] = $n;
        }
    }
    
    // Si no hay resultados en ese municipio, puedes decidir si mostrar mensaje vacío o destacados generales
    // Por ahora dejamos el array como quedó (vacío o con resultados)

} elseif ($lat !== null && $lng !== null) {
    // PRIORIDAD 3: Ubicación GPS (Cercanía genérica si no hay municipio exacto)
    if (method_exists($negocio, 'obtenerCercanos')) {
        $negocios_destacados = $negocio->obtenerCercanos($lat, $lng);
    }
    
    // Fallback: Si no hay cercanos o el método no existe, mostrar destacados generales
    if (empty($negocios_destacados)) {
        $negocios_destacados = $negocio->obtenerDestacados();
    }

} else {
    // PRIORIDAD 4: Default (Mostrar destacados generales)
    $negocios_destacados = $negocio->obtenerDestacados();
}

// ==========================================
// 3.5 NEGOCIOS RECOMENDADOS (Premium y Verificados)
// ==========================================
$negocios_recomendados = [];
try {
    // Obtener negocios premium/verificados con prioridad
    $stmt = $db->prepare("
        SELECT n.*,
               COALESCE(n.rating_promedio, 0) as rating,
               COALESCE(n.total_resenas, 0) as total_resenas,
               CASE
                   WHEN n.es_premium = 1 AND n.verificado = 1 THEN 3
                   WHEN n.es_premium = 1 THEN 2
                   WHEN n.verificado = 1 THEN 1
                   ELSE 0
               END as prioridad
        FROM negocios n
        WHERE n.activo = 1
          AND (n.es_premium = 1 OR n.verificado = 1 OR n.destacado = 1)
        ORDER BY prioridad DESC, n.rating_promedio DESC, n.total_resenas DESC
        LIMIT 6
    ");
    $stmt->execute();
    $negocios_recomendados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener negocios recomendados: " . $e->getMessage());
}

// ==========================================
// 4. DATOS DE USUARIO, MEMBRESÍA Y DIRECCIONES
// ==========================================

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$mis_direcciones = []; // Array para direcciones guardadas (necesario para JS)

if ($usuario_logueado) {
    // Información del Usuario
    $usuario = new Usuario($db);
    $usuario->id_usuario = $_SESSION["id_usuario"];
    $usuario->obtenerPorId();

    // Obtener Direcciones Guardadas (IMPORTANTE PARA SMART LOCATION JS)
    // Utiliza función helper que maneja ambas tablas: direcciones y direcciones_usuario
    $mis_direcciones = getUserAddresses($db, $_SESSION['id_usuario']);

    // Verificar pedidos pendientes
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
    
    // Buscar pedidos en proceso (no entregados, cancelados o abandonados)
    // Estados: 1=pendiente, 2=confirmado, 3=en_preparacion, 4=listo_para_recoger, 5=en_camino
    foreach ($pedidosPendientes as $p) {
        if (isset($p['id_estado']) && in_array($p['id_estado'], [1, 2, 3, 4, 5])) {
            $pedidoPendiente = $p;
            break; // Mostrar el primero en proceso
        }
    }

    // Verificar Membresía
    $membership = new Membership($db);
    $membership->id_usuario = $_SESSION["id_usuario"];
    $esMiembroActivo = $membership->isActive();
} else {
    $esMiembroActivo = false;
    $pedidoPendiente = null; // Definir para usuarios no logueados
    $mis_direcciones = []; // Array vacío para usuarios no logueados
}

// ==========================================
// 5. FUNCIONES HELPER
// ==========================================

function mostrarBannerModal($banner) {
    echo '
    <div class="modal fade banner-modal" id="bannerModal' . $banner['id_banner'] . '" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content banner-modal-content">
                <button type="button" class="btn-close banner-close" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="banner-modal-body">
                    <div class="banner-background" style="background-image: url(\'' . ($banner['imagen_url'] ? $banner['imagen_url'] : 'assets/img/banners/default-banner.jpg') . '\');">
                        <div class="banner-overlay">
                            <div class="banner-content">
                                <div class="banner-logo">
                                    <i class="fas fa-crown"></i>
                                    <span>QuickBite Pro</span>
                                </div>
                                
                                <h2 class="banner-title">' . htmlspecialchars($banner['titulo']) . '</h2>';
    
    if ($banner['descuento_porcentaje'] > 0) {
        echo '
                                <div class="discount-highlight">
                                    <span class="discount-percent">' . $banner['descuento_porcentaje'] . '%</span>
                                    <span class="discount-text">OFF</span>
                                </div>';
    }
    
    echo '
                                <p class="banner-description">' . htmlspecialchars($banner['descripcion']) . '</p>
                                
                                <div class="banner-pricing">
                                    <span class="current-price">¡Oferta Especial!</span>
                                </div>
                                
                                <div class="banner-actions">
                                    <a href="' . ($banner['enlace_destino'] ? $banner['enlace_destino'] : '#') . '" class="btn-activar">
                                        Activar Oferta
                                    </a>
                                </div>
                                
                                <div class="banner-terms">
                                    <small>Aplican términos y condiciones. Oferta válida por tiempo limitado.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>QuickBite - Delivery Rápido y Delicioso</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Pide comida desde tu app favorita de delivery. Rápido, delicioso y confiable.">
   <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="QuickBite">
    <meta name="msapplication-TileImage" content="/assets/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#0165FF">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-96x96.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/assets/icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="/assets/icons/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="/assets/icons/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/assets/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="/assets/icons/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/assets/icons/icon-512x512.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    

    
    <!-- Theme Handler - Cargar ANTES de que renderice el body para evitar flash -->
    <script src="assets/js/theme-handler.js?v=2.1"></script>
    
    <!-- Fonts: Inter and DM Sans -->
    <link rel="stylesheet" href="assets/css/transitions.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/soft-ui.css?v=2.0">
<style>
/* ============================================
   VARIABLES MINIMALISTAS
   ============================================ */
:root {
    --primary: #0165FF;
    --primary-dark: #0052cc;
    --primary-light: rgba(1, 101, 255, 0.05);
    --secondary: #f8f9fa;
    --accent: #ffffff;
    --dark: #212529;
    --light: #FFFFFF;
    --success: #10b981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --info: #0165FF;
    --white: #ffffff;
    --gray-50: #fafafa;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --green: #10b981;
    --green-dark: #059669;
    --gray-900: #212529;
    --gradient: linear-gradient(135deg, #0165FF 0%, #0052cc 100%);
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --border-radius-xl: 16px;
    --border-radius-full: 24px;
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
    --shadow-md: 0 2px 6px 0 rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 4px 12px 0 rgba(0, 0, 0, 0.08);
    --shadow-xl: 0 6px 20px 0 rgba(0, 0, 0, 0.1);
    --transition: all 0.2s ease;
    --body-bg: #f8f9fa;
    
    --border-radius-card: 16px;
    --border-radius-button: 12px;

    --shadow-soft: 0 4px 20px -4px rgba(0,0,0,0.06);
    --shadow-hover: 0 8px 30px -4px rgba(0,0,0,0.1);
}
/* =======================================================
   MODO OSCURO AUTOMÁTICO (AMOLED #000000 vs #FFFFFF)
   ======================================================= */

/* 1. Ajustes base para Modo Claro (Forzando el blanco puro que pediste) */
:root {
    --body-bg: #ffffff; /* Antes tenías #f8f9fa, ahora es blanco puro */
    --card-bg: #ffffff;
    --nav-bg: rgba(255, 255, 255, 0.95);
    --text-main: #212529;
}

/* 2. DETECCIÓN DE MODO OSCURO */
@media (prefers-color-scheme: dark) {
    :root {
        /* Colores Base */
        --primary: #0165FF; /* El azul se mantiene igual, brilla bien en negro */
        --body-bg: #000000; /* NEGRO PURO */
        --white: #121212;   /* Las tarjetas serán casi negras para que se note la separación */
        --gray-100: #1a1a1a; /* Fondos secundarios */
        --gray-200: #333333; /* Bordes */
        
        /* Inversión de Textos */
        --gray-900: #ffffff; /* Títulos ahora son blancos */
        --gray-800: #e0e0e0; /* Texto normal gris claro */
        --gray-700: #cccccc;
        --gray-600: #aaaaaa;
        
        /* Sombras (En modo oscuro las sombras negras no se ven, usamos brillos suaves) */
        --shadow-sm: 0 1px 3px 0 rgba(255, 255, 255, 0.05);
        --shadow-hover: 0 8px 30px -4px rgba(255, 255, 255, 0.1);
        
        /* Variable personalizada para la navbar */
        --nav-bg: rgba(0, 0, 0, 0.90); 
    }

    /* === CORRECCIONES ESPECÍFICAS PARA TU DISEÑO === */

    body {
        background-color: var(--body-bg) !important;
        color: var(--gray-800);
        min-height: 100vh;
    min-height: -webkit-fill-available;
    }
    html {
    height: -webkit-fill-available;
}

/* Para iOS PWA */

    /* Tarjetas y Elementos Flotantes */
    .category-item, 
    .featured-card, 
    .search-bar,
    .addresses-container,
    .alert-content {
        background: #111111 !important; /* Un poco más claro que #000000 para que se vea */
        border: 1px solid #333; /* Borde sutil para separar del fondo negro */
        color: #fff;
    }

    /* Barra de Navegación Inferior */
    .bottom-nav {
        background: var(--nav-bg) !important;
        border-top: 1px solid #333;
    }
    
    /* Inputs de búsqueda */
    .search-bar input {
        color: #fff !important;
    }
    .search-bar input::placeholder {
        color: #888;
    }

    /* Top Header */
    .top-header {
        background: #000000 !important;
        border-bottom: 1px solid #333;
    }
    .location-btn {
        color: #fff !important;
    }
    .location-btn i:last-child {
        color: #888;
    }

    /* === LA MAGIA: INVERTIR LOS ICONOS PNG === */
    /* Como usas imágenes PNG negras, en modo oscuro no se verían. 
       Esto las invierte a blanco automáticamente */
    .nav-icon, 
    .category-icon img,
    .profile-btn .nav-icon,
    .logout-btn .nav-icon {
        filter: invert(1) brightness(2); /* Vuelve los iconos negros -> blancos */
    }

    /* Excepción: El icono activo azul NO lo invertimos o se verá naranja */
    .nav-item.active .nav-icon {
        filter: none !important; 
    }
    
    /* El botón central (carrito) ya tiene fondo azul, solo hacemos el icono blanco */
    .central-btn .nav-icon {
        filter: brightness(0) invert(1) !important;
    }

    /* Botones de perfil circulares */
    .profile-btn, .logout-btn {
        background: #222 !important;
    }
}

/* ============================================
   RESET Y BASE
   ============================================ */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--white);
    color: var(--white);
    line-height: 1.6;
    font-weight: 400;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

h1, h2, h3, h4, h5, h6 {
    font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;
    font-weight: 700;
    color: var(--gray-900);
    line-height: 1.2;
    letter-spacing: -0.02em;
}

/* ============================================
   CONTAINER Y HEADER
   ============================================ */
.container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 1.5rem;
    padding-top: 90px;
    padding-bottom: 200px;
}

/* Top Header */
.top-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: var(--white);
    border-bottom: 0px solid var(--gray-200);
    z-index: 1000;
    padding: 1rem 0;
}

.header-content {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.location-info {
    flex: 1;
}

.location-btn {
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray-900);
    font-weight: 600;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.location-btn:hover {
    background: var(--gray-100);
}

.location-btn i:first-child {
    color: var(--primary);
}

.location-btn i:last-child {
    font-size: 0.75rem;
    color: var(--gray-400);
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.profile-btn, .logout-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gray-100);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-700);
    text-decoration: none;
    transition: var(--transition);
}

.profile-btn:hover, .logout-btn:hover {
    background: var(--gray-200);
    color: var(--gray-900);
}

.profile-btn .nav-icon, .logout-btn .nav-icon {
    width: 20px;
    height: 20px;
    object-fit: contain;
    transition: var(--transition);
    filter: brightness(0) saturate(100%) invert(44%) sepia(7%) saturate(445%) hue-rotate(314deg) brightness(94%) contrast(89%);
}

.profile-btn:hover .nav-icon, .logout-btn:hover .nav-icon {
    filter: brightness(0) saturate(100%) invert(7%) sepia(9%) saturate(2%) hue-rotate(314deg) brightness(98%) contrast(95%);
}

/* Search Section */
.search-section {
    margin: 1.5rem 0;
}

/* ============================================
   ALERTAS DE MEMBRESÍA
   ============================================ */
.membership-alert {
    background: var(--primary);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius-lg);
    margin: 1rem 0;
    border: 1px solid var(--primary-dark);
}

.membership-alert.danger {
    background: var(--danger);
    border-color: #DC2626;
}



/* ============================================
   BÚSQUEDA MINIMALISTA COMPACTA
   ============================================ */
.search-form {
    margin-bottom: 1.5rem;
}

.search-bar {
    position: relative;
    background: #ffffff;
    border-radius: 30px; /* Cápsula total */
    border: none;
    /* Sombra flotante minimalista */
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    padding: 4px;
}

.search-bar:focus-within {
    border-color: var(--primary);
    background: var(--white);
    box-shadow: 0 4px 16px rgba(1, 101, 255, 0.15);
}

.search-bar button {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--primary);
    font-size: 1.1rem;
    z-index: 3;
    padding: 8px;
    cursor: pointer;
    transition: var(--transition);
    border-radius: 50%;
}

.search-bar button:hover {
    color: var(--primary-dark);
}

.search-bar input {
    width: 100%;
    padding: 10px 50px 10px 16px;
    border: none;
    outline: none;
    font-size: 0.95rem;
    background: transparent;
    color: var(--gray-900);
    font-weight: 600;
    letter-spacing: 0.01em;
    color: #333;
}

.search-bar input::placeholder {
    color: var(--gray-500);
    font-weight: 400;
}

.search-clear {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--gray-400);
    font-size: 0.875rem;
    padding: 6px;
    z-index: 3;
    cursor: pointer;
    transition: var(--transition);
}

.search-clear:hover {
    color: var(--primary);
}

/* ============================================
   BANNER PRO COMPACTO
   ============================================ */
.pro-banner-compact {
    margin: 1.5rem 0 1.5rem 0;
}

.pro-banner-card {
    background: linear-gradient(135deg, rgba(1, 101, 255, 0.08) 0%, rgba(1, 101, 255, 0.04) 100%);
    border: 1px solid rgba(1, 101, 255, 0.12);
    border-radius: var(--border-radius-card);
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: var(--transition);
    backdrop-filter: blur(10px);
}

.pro-banner-card:hover {
    background: linear-gradient(135deg, rgba(1, 101, 255, 0.12) 0%, rgba(1, 101, 255, 0.06) 100%);
    transform: translateY(-2px);
    box-shadow: var(--shadow-soft);
}

.pro-banner-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pro-icon {
    width: 36px;
    height: 36px;
    background: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
}

.pro-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pro-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
}

.pro-subtitle {
    font-size: 0.75rem;
    color: var(--gray-600);
    font-weight: 500;
}

.pro-cta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
}

.pro-price {
    font-size: 0.875rem;
    font-weight: 600;
}

.pro-cta i {
    font-size: 0.75rem;
    opacity: 0.7;
    transition: var(--transition);
}

.pro-banner-card:hover .pro-cta i {
    opacity: 1;
    transform: translateX(2px);
}

/* ============================================
   SECCIONES
   ============================================ */
.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 3rem 0 2rem 0;
}

.section-title h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}

.section-title a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 400;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: var(--transition);
}

.section-title a:hover {
    color: var(--primary-dark);
}

/* ============================================
   CATEGORÍAS ESTILO RAPPI GRID
   ============================================ */
.categories-grid {
    margin: 2.5rem 0 3rem 0;
}

.category-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.category-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: var(--gray-800);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 2rem 1.25rem;
    
    /* Tarjeta flotante minimalista */
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    border: none;
    height: 100%;
    min-height: 140px;
}

.category-item:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-hover);
    color: var(--gray-700);
}

.category-icon {
    width: 85px;
    height: 85px;
    border-radius: 16px;
    font-weight: 600;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
    transition: var(--transition);
    perspective: 1000px;
}

.category-icon img {
    width: 70px;
    height: 70px;
    object-fit: contain;
    transition: var(--transition);
    filter: drop-shadow(0 3px 8px rgba(0, 0, 0, 0.08));
    transform-style: preserve-3d;
    border-radius: 8px;
}

.category-item:hover .category-icon img {
    transform: scale(1.2) translateZ(15px) rotateY(5deg);
    filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.25));
}

.category-icon.turbo {
    background: linear-gradient(135deg, #00D4AA 0%, #00B894 100%);
    color: white;
    border-radius: 16px;
    padding: 8px;
}

.category-icon.turbo img {
    width: 75px;
    height: 75px;
    filter: brightness(0) invert(1) drop-shadow(0 6px 12px rgba(0, 0, 0, 0.25));
}

.category-item span {
    font-size: 0.875rem;
    font-weight: 600;
    text-align: center;
}

/* Responsive design para categorías */
@media (max-width: 768px) {
    .category-row {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .category-item {
        padding: 1.5rem 1rem;
        min-height: 120px;
    }
    
    .category-icon {
        width: 70px;
        height: 70px;
    }
    
    .category-icon img {
        width: 60px;
        height: 60px;
    }
}

@media (max-width: 480px) {
    .category-row {
        gap: 0.25rem;
    }

    .category-item {
        padding: 0.5rem 0.25rem;
    }

    .category-icon {
        width: 58px;
        height: 58px;
        margin-bottom: 0.5rem;
    }

    .category-icon img {
        width: 48px;
        height: 48px;
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
    }

    .category-item span {
        font-size: 0.7rem;
    }
}
/* Membership Banner */
.membership-banner {
    margin: 2rem 0;
}

.membership-card {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 1.25rem 1.5rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    box-shadow: 0 4px 24px rgba(1, 101, 255, 0.2);
}

.membership-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(1, 101, 255, 0.3);
}

.membership-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.membership-icon {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.membership-icon i {
    font-size: 1.75rem;
    color: white;
}

.membership-text h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin: 0 0 0.15rem 0;
    color: white;
}

.membership-text p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.85rem;
}

.membership-action {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.membership-price {
    font-size: 1rem;
    font-weight: 700;
    color: white;
}

.membership-action i {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.8);
}

/* ============================================
   SECCIÓN DESTACADA ESTILO RAPPI
   ============================================ */

   /* Badge de Estado */
.status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 2;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.status-open {
    background: rgba(16, 185, 129, 0.9); /* Verde */
    color: white;
}

.status-closed {
    background: rgba(239, 68, 68, 0.9); /* Rojo */
    color: white;
}

/* En modo oscuro, bajamos un poco la intensidad para que no lastime la vista */
@media (prefers-color-scheme: dark) {
    .status-open { background: rgba(16, 185, 129, 0.8); }
    .status-closed { background: rgba(239, 68, 68, 0.8); }
}

.featured-section {
    margin: 2rem 0;
}


.featured-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 1.5rem;
    letter-spacing: -0.01em;
}

.restaurants-grid {
    margin-top: 1rem;
}

.featured-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.featured-card {
    position: relative;
    height: 200px;
    border-radius: 20px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}

.featured-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
}



.card-image {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
}

.mexican-bg {
    background: linear-gradient(135deg, rgba(0,0,0,0.4), rgba(0,0,0,0.6)), 
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200"><rect fill="%23FF6B35" width="400" height="200"/><circle fill="%23FF4520" cx="100" cy="50" r="30"/><circle fill="%23FF4520" cx="300" cy="150" r="40"/></svg>');
}

.tortas-bg {
    background: linear-gradient(135deg, rgba(0,0,0,0.4), rgba(0,0,0,0.6)), 
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200"><rect fill="%23F39C12" width="400" height="200"/><circle fill="%23E67E22" cx="150" cy="100" r="50"/><circle fill="%23E67E22" cx="350" cy="50" r="30"/></svg>');
}

.card-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    padding: 2rem 1.5rem 1.5rem;
    color: white;
}

.card-overlay h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.75rem 0;
    color: white;
}

.card-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
}

.card-info span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.rating {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: var(--border-radius);
    font-weight: 600;
    backdrop-filter: blur(10px);
}

/* ============================================
   BOTTOM NAVIGATION MINIMALISTA
   ============================================ */
/* ============================================
   BOTTOM NAVIGATION ESTILO RAPPI / iOS
   ============================================ */
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
        bottom: 2px;
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

/* ============================================
   BARRA DE DIRECCIONES GUARDADAS
   ============================================ */
.saved-addresses-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    display: none;
    animation: fadeIn 0.3s ease;
}

.addresses-container {
    position: absolute;
    top: 70px;
    left: 0;
    right: 0;
    background: var(--white);
    border-radius: 0 0 var(--border-radius-xl) var(--border-radius-xl);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    max-height: 70vh;
    overflow-y: auto;
}

.addresses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.addresses-header h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.addresses-header i {
    color: var(--primary);
}

.close-addresses {
    background: var(--gray-100);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    cursor: pointer;
    transition: var(--transition);
}

.close-addresses:hover {
    background: var(--gray-200);
    color: var(--gray-800);
}

.addresses-list {
    padding: 0;
}

.address-item {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    cursor: pointer;
    transition: var(--transition);
    border-bottom: 1px solid var(--gray-100);
}

.address-item:hover {
    background: var(--gray-50);
}

.address-item:last-child {
    border-bottom: none;
}

.address-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.address-icon i {
    color: var(--primary);
    font-size: 1rem;
}

.address-info {
    flex: 1;
}

.address-label {
    display: block;
    font-weight: 600;
    color: var(--gray-900);
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.address-detail {
    color: var(--gray-500);
    font-size: 0.85rem;
    line-height: 1.3;
}

.address-item > i:last-child {
    color: var(--gray-400);
    margin-left: 1rem;
}

.address-item.current-location .address-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.address-item.current-location .address-icon i {
    color: white;
}

.address-item.add-new .address-icon {
    background: var(--gray-100);
    border: 2px dashed var(--gray-300);
}

.address-item.add-new .address-icon i {
    color: var(--gray-400);
}

.address-item.add-new:hover .address-icon {
    background: var(--primary-light);
    border-color: var(--primary);
}

.address-item.add-new:hover .address-icon i {
    color: var(--primary);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* ============================================
   TOAST Y ALERTAS
   ============================================ */
.location-toast {
    position: fixed;
    top: 2rem;
    left: 50%;
    transform: translateX(-50%);
    background: var(--primary);
    color: var(--white);
    padding: 0.875rem 1.5rem;
    border-radius: var(--border-radius);
    z-index: 1001;
    font-size: 0.875rem;
    font-weight: 400;
    display: none;
}

/* Alerta de Cobertura */
.cobertura-alert {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.cobertura-alert.show {
    opacity: 1;
    visibility: visible;
}

.alert-content {
    background: var(--white);
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    max-width: 400px;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
}

.alert-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.alert-icon i {
    font-size: 2rem;
    color: white;
}

.alert-text h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 1rem;
}

.alert-text p {
    color: var(--gray-600);
    line-height: 1.6;
    margin-bottom: 0.75rem;
}

.alert-text p:last-child {
    margin-bottom: 0;
}

.alert-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: transparent;
    border: none;
    color: var(--gray-400);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: var(--transition);
}

.alert-close:hover {
    background: var(--gray-100);
    color: var(--gray-600);
}

/* ============================================
   NO RESULTS
   ============================================ */
.no-results {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray-400);
}

.no-results i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.no-results h3 {
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
    color: var(--gray-700);
    font-weight: 700;
}

.no-results p {
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
    color: var(--gray-500);
}

.btn-reset {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary);
    color: var(--white);
    padding: 0.875rem 1.5rem;
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    font-size: 0.95rem;
}

.btn-reset:hover {
    background: var(--primary-dark);
    color: var(--white);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .container {
        padding: 0 1rem;
        padding-top: 70px;
        padding-bottom: 100px;
    }

    .header-content {
        padding: 0 1rem;
    }

    .location-btn span {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .search-title {
        font-size: 1.25rem;
    }

    /* Hero Slider - Tablet */
.hero-slider {
    height: 180px;
}

.promo-card {
    padding: 1.5rem 2rem;
}

.promo-wrapper {
    gap: 2rem;
}

.promo-badge {
    font-size: 0.6rem;
    padding: 0.35rem 0.85rem;
}

.promo-title {
    font-size: 1rem;
}

.highlight {
    font-size: 1.75rem;
}

.promo-description {
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.promo-btn {
    padding: 0.6rem 1.3rem;
    font-size: 0.8rem;
}

.promo-icon {
    width: 100px;
    height: 100px;
}

.promo-icon i {
    font-size: 2.5rem;
}

    .category-row {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
    }

    .category-item {
        padding: 0.75rem 0.5rem;
    }

    .category-icon {
        width: 70px;
        height: 70px;
    }

    .category-item span {
        font-size: 0.75rem;
    }

    .membership-card {
        padding: 1rem 1.25rem;
    }

    .membership-text h3 {
        font-size: 1rem;
    }

    .membership-text p {
        font-size: 0.8rem;
    }

    .membership-price {
        font-size: 0.95rem;
    }

    .featured-cards {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .featured-card {
        height: 180px;
    }

    .featured-title {
        font-size: 1.5rem;
    }

    .membership-card {
        padding: 1rem 1.25rem;
    }

    .membership-text h3 {
        font-size: 1rem;
    }

    .membership-text p {
        font-size: 0.8rem;
    }

    .membership-price {
        font-size: 0.95rem;
    }
}

@media (max-width: 480px) {
    .header-actions {
        gap: 0.5rem;
    }

    .profile-btn, .logout-btn {
        width: 36px;
        height: 36px;
    }

   .hero-slider {
                height: 180px;
                border-radius: var(--border-radius-lg);
            }

            .promo-card {
                padding: 1.25rem;
                flex-direction: column;
                text-align: center;
                justify-content: center;
            }

            .promo-badge {
                top: 1rem;
                left: 1rem;
            }

            .promo-content {
                max-width: 100%;
                margin-bottom: 0rem;
            }

            .promo-content h2 {
                font-size: 1.35rem;
            }

            .highlight {
                font-size: 1.75rem;
            }

            .promo-content p {
                font-size: 0.8rem;
                margin-bottom: 0.3rem;
            }

            .promo-btn {
                padding: 0.6rem 1.1rem;
                font-size: 0.8rem;
            }

            .promo-visual {
                width: 90px;
                height: 90px;
            }

            .promo-icon-circle i {
                font-size: 2.25rem;
            }

            .slider-controls {
                bottom: 1rem;
            }

            .slider-dot {
                width: 20px;
                height: 3px;
            }

            .slider-dot.active {
                width: 28px;
            }

    .category-row {
        gap: 0.25rem;
    }

    .category-item {
        padding: 0.5rem 0.25rem;
    }

    .category-icon {
        width: 58px;
        height: 58px;
        margin-bottom: 0.5rem;
    }

    .category-icon img {
        width: 48px;
        height: 48px;
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
    }

    .category-item span {
        font-size: 0.7rem;
    }

    .membership-card {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
    }

    .membership-info {
        gap: 0.75rem;
    }

    .membership-icon {
        width: 60px;
        height: 60px;
    }

    .membership-icon i {
        font-size: 1.5rem;
    }

    .membership-action {
        align-self: flex-end;
    }
}

/* ============================================
   PWA INSTALL HERO BANNER
   ============================================ */
.pwa-install-hero {
    background: linear-gradient(135deg, #0165FF 0%, #0052cc 100%);
    padding: 1rem 1.25rem;
    margin: 0;
    position: relative;
    overflow: hidden;
}

.pwa-install-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    pointer-events: none;
}

.pwa-install-hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 150px;
    height: 150px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.pwa-hero-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    z-index: 1;
    max-width: 600px;
    margin: 0 auto;
}

.pwa-hero-icon {
    flex-shrink: 0;
}

.pwa-app-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    background: #fff;
}

.pwa-hero-text {
    flex: 1;
    min-width: 0;
}

.pwa-hero-title {
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 0.15rem 0;
    line-height: 1.2;
}

.pwa-hero-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.8rem;
    margin: 0;
    line-height: 1.3;
}

.pwa-hero-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.pwa-install-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: #fff;
    color: #0165FF;
    border: none;
    padding: 0.6rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.pwa-install-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.pwa-install-btn:active {
    transform: scale(0.98);
}

.pwa-install-btn i {
    font-size: 0.9rem;
}

.pwa-dismiss-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s ease;
}

.pwa-dismiss-btn:hover {
    background: rgba(255,255,255,0.3);
}

/* PWA Hero en modo oscuro */
[data-theme="dark"] .pwa-install-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

[data-theme="dark"] .pwa-app-icon {
    background: #1a1a2e;
    border: 1px solid rgba(255,255,255,0.1);
}

[data-theme="dark"] .pwa-install-btn {
    background: #0165FF;
    color: #fff;
}

/* iOS Safari specific instructions */
.pwa-ios-instructions {
    display: none;
    background: rgba(0,0,0,0.85);
    color: #fff;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem;
    z-index: 9999;
    border-radius: 20px 20px 0 0;
    text-align: center;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}

.pwa-ios-instructions.show {
    display: block;
}

.pwa-ios-instructions h4 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.pwa-ios-steps {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.pwa-ios-step {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-align: left;
    font-size: 0.9rem;
}

.pwa-ios-step i {
    font-size: 1.2rem;
    width: 30px;
    text-align: center;
    color: #0165FF;
}

.pwa-ios-close {
    background: #0165FF;
    color: #fff;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 480px) {
    .pwa-install-hero {
        padding: 0.875rem 1rem;
    }

    .pwa-hero-content {
        gap: 0.75rem;
    }

    .pwa-app-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
    }

    .pwa-hero-title {
        font-size: 0.9rem;
    }

    .pwa-hero-subtitle {
        font-size: 0.75rem;
    }

    .pwa-install-btn {
        padding: 0.5rem 0.875rem;
        font-size: 0.8rem;
    }

    .pwa-dismiss-btn {
        width: 28px;
        height: 28px;
    }
}
</style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <div class="qb-page-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-content">
                <div class="location-info">
                    <button class="location-btn" onclick="toggleSavedAddresses()">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="location-text">Detectando ubicación...</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="header-actions">
                    <?php if ($usuario_logueado): ?>
                        <a href="perfil.php" class="profile-btn">
                            <img src="assets/icons/user.png" alt="Perfil" class="nav-icon">
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="profile-btn">
                            <img src="assets/icons/user.png" alt="Perfil" class="nav-icon">
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo $usuario_logueado ? 'logout.php' : 'login.php'; ?>" class="logout-btn">
                        <img src="assets/icons/logout.png" alt="Cerrar Sesión" class="nav-icon">
                    </a>
                </div>
            </div>
        </header>

        <!-- Barra de Direcciones Guardadas -->
        <div id="saved-addresses-bar" class="saved-addresses-bar">
            <div class="addresses-container">
                <div class="addresses-header">
                    <h3><i class="fas fa-map-marker-alt"></i> Direcciones guardadas</h3>
                    <button class="close-addresses" onclick="closeSavedAddresses()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="addresses-list">
                    <div class="address-item current-location" onclick="getCurrentLocation()">
                        <div class="address-icon">
                            <i class="fas fa-crosshairs"></i>
                        </div>
                        <div class="address-info">
                            <span class="address-label">Ubicación actual</span>
                            <small class="address-detail">Usar mi ubicación actual</small>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    
                    <?php if ($usuario_logueado && !empty($mis_direcciones)): ?>
                        <?php foreach ($mis_direcciones as $dir): ?>
                            <?php 
                                $titulo = htmlspecialchars($dir['titulo'] ?? 'Dirección');
                                $direccion_completa = htmlspecialchars($dir['direccion'] ?? '');
                                $latitud = $dir['latitud'] ?? '';
                                $longitud = $dir['longitud'] ?? '';
                                $ciudad = htmlspecialchars($dir['ciudad'] ?? '');
                                
                                // Determinar icono según el título
                                $icono = 'fa-map-marker-alt';
                                $titulo_lower = strtolower($titulo);
                                if (strpos($titulo_lower, 'casa') !== false) {
                                    $icono = 'fa-home';
                                } elseif (strpos($titulo_lower, 'trabajo') !== false || strpos($titulo_lower, 'oficina') !== false) {
                                    $icono = 'fa-briefcase';
                                } elseif (strpos($titulo_lower, 'escuela') !== false || strpos($titulo_lower, 'universidad') !== false) {
                                    $icono = 'fa-graduation-cap';
                                }
                            ?>
                            <div class="address-item" onclick="selectAddressWithCoords('<?php echo $titulo; ?>', '<?php echo $direccion_completa; ?>', <?php echo $latitud ?: 'null'; ?>, <?php echo $longitud ?: 'null'; ?>, '<?php echo $ciudad; ?>')">
                                <div class="address-icon">
                                    <i class="fas <?php echo $icono; ?>"></i>
                                </div>
                                <div class="address-info">
                                    <span class="address-label"><?php echo $titulo; ?></span>
                                    <small class="address-detail"><?php echo $direccion_completa; ?></small>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($usuario_logueado): ?>
                        <div class="address-item" style="opacity: 0.6;">
                            <div class="address-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="address-info">
                                <span class="address-label">Sin direcciones guardadas</span>
                                <small class="address-detail">Agrega tu primera dirección</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="address-item" onclick="window.location.href='login.php?redirect=perfil.php'">
                            <div class="address-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="address-info">
                                <span class="address-label">Inicia sesión</span>
                                <small class="address-detail">Para ver tus direcciones guardadas</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($usuario_logueado): ?>
                    <div class="address-item add-new" onclick="addNewAddress()">
                        <div class="address-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="address-info">
                            <span class="address-label">Agregar nueva dirección</span>
                            <small class="address-detail">Guardar una nueva ubicación</small>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PWA Install Hero Banner -->
        <div id="pwa-install-hero" class="pwa-install-hero" style="display: none;">
            <div class="pwa-hero-content">
                <div class="pwa-hero-icon">
                    <img src="/assets/icons/icon-96x96.png" alt="QuickBite App" class="pwa-app-icon">
                </div>
                <div class="pwa-hero-text">
                    <h3 class="pwa-hero-title">Instala QuickBite</h3>
                    <p class="pwa-hero-subtitle">Acceso rápido desde tu pantalla de inicio</p>
                </div>
                <div class="pwa-hero-actions">
                    <button id="pwa-install-btn" class="pwa-install-btn">
                        <i class="fas fa-download"></i>
                        <span>Instalar</span>
                    </button>
                    <button id="pwa-dismiss-btn" class="pwa-dismiss-btn" aria-label="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

    <div class="container">
        <!-- Search Section -->
        <section class="search-section">
            <h1 class="search-title">Qué se te antoja hoy?</h1>
            <form action="index.php" method="GET" class="search-form">
                <div class="search-bar <?php echo !empty($termino_busqueda) ? 'search-active' : ''; ?>">
                    <button type="submit" aria-label="Buscar">
                         <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M21 21L16.5 16.5M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" stroke="#0165FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
                    </button>
                    <input type="text" name="buscar" placeholder="Buscar restaurantes, comida..." value="<?php echo htmlspecialchars($termino_busqueda); ?>" autocomplete="off">
                    <?php if (!empty($termino_busqueda)): ?>
                        <a href="index.php" class="search-clear" aria-label="Limpiar búsqueda" title="Limpiar búsqueda">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Campos ocultos para coordenadas -->
                <input type="hidden" name="lat" id="lat" value="<?php echo $lat; ?>">
                <input type="hidden" name="lng" id="lng" value="<?php echo $lng; ?>">
            </form>
        </section>

        <!-- <?php if (!$esMiembroActivo): ?>
        <!-- Banner Pro Compacto (solo para no miembros) 
        <section class="pro-banner-compact">
            <div class="pro-banner-card" onclick="abrirMembresia()">
                <div class="pro-banner-content">
                    <div class="pro-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="pro-text">
                        <span class="pro-title">QuickBite Pro</span>
                        <span class="pro-subtitle">Envíos gratis ilimitados</span>
                    </div>
                </div>
                <div class="pro-cta">
                    <span class="pro-price">$59/mes</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </section>
        <?php endif; ?> -->

        <!-- Toast para notificaciones de ubicación -->
        <div id="location-toast" class="location-toast"></div>

        <!-- Alert for pending order -->
        <?php if ($pedidoPendiente): ?>
            <div class="alert alert-warning d-flex align-items-center justify-content-center gap-2 text-center" role="alert" style="margin: 1rem 0; font-weight: 600; font-size: 1.1rem; border-radius: 12px;">
                <span>
                    Tienes un pedido en curso. Por favor, 
                    <a href="confirmacion_pedido.php?id=<?php echo htmlspecialchars($pedidoPendiente['id_pedido']); ?>" style="text-decoration: underline; font-weight: 700;">
                        haz clic aquí para ver el estado de tu pedido
                    </a>.
                </span>
            </div>
        <?php endif; ?>

        
<?php if ($esMiembroActivo) {
    // Verificar si está próxima a vencer (solo en los últimos 7 días)
    $stmt = $db->prepare("SELECT DATEDIFF(fecha_fin, NOW()) as dias_restantes FROM membresias WHERE id_usuario = ? AND estado = 'activo' LIMIT 1");
    $stmt->bindParam(1, $_SESSION["id_usuario"]);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $diasParaVencer = (int)$row['dias_restantes'];
        
        // Solo mostrar alerta si quedan entre 0 y 7 días (no mostrar si es negativo o más de 7 días)
        if ($diasParaVencer >= 0 && $diasParaVencer <= 7) {
           echo '<div class="membership-alert" style="position: relative; margin: 1rem 0;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 style="color: white; margin-bottom: 0.5rem; font-size: 1.1rem;">
                            <i class="fas fa-clock" style="margin-right: 0.5rem;"></i>
                            Tu membresía expira en ' . $diasParaVencer . ' día' . ($diasParaVencer != 1 ? 's' : '') . '
                        </h3>
                        <p style="margin: 0; color: white; opacity: 0.9;">
                            Renueva ahora para seguir disfrutando de todos los beneficios premium
                        </p>
                    </div>
                    <a href="membership_subscribe.php?renovar=1" style="background: white; color: #F59E0B; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; white-space: nowrap;">
                        Renovar
                    </a>
                </div>
              </div>';
        }
    }
} 
?> 
       <!-- Categories Grid -->

        <section class="categories-grid">

            <div class="category-row">

                <a href="categoria.php?id=4" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/cafe.png" alt="Cafetería" onerror="this.src='assets/icons/cafe.png'">

                    </div>

                    <span>Cafetería</span>

                </a>

                <a href="categoria.php?id=12" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/drink.png" alt="Restaurant" onerror="this.src='assets/icons/default.png'">

                    </div>

                    <span>Bebidas</span>

                </a>

                <a href="categoria.php?id=6" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/bread-cut.png" alt="Panadería" onerror="this.src='assets/icons/bread.png'">

                    </div>

                    <span>Panadería</span>

                </a>

                <a href="categoria.php?id=15" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/burger-3d.webp" alt="Heladería" onerror="this.src='assets/icons/ice-cream.png'">

                    </div>

                    <span>Hamburgesas</span>

                </a>

            </div>

            <div class="category-row">

                <a href="categoria.php?id=8" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/taco.png" alt="Taquería" onerror="this.src='assets/icons/taco.png'">

                    </div>

                    <span>Taquería</span>

                </a>

                <a href="categoria.php?id=9" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/watermelon.png" alt="Frutería" onerror="this.src='assets/icons/fruits.png'">

                    </div>

                    <span>Frutería</span>

                </a>

                <a href="categoria.php?id=10" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/pizza.png" alt="Pizzería" onerror="this.src='assets/icons/default.png'">

                    </div>

                    <span>Pizzería</span>

                </a>

                <a href="categoria.php?id=20" class="category-item">

                    <div class="category-icon">

                        <img src="assets/icons/rose.png" alt="Mariscos" onerror="this.src='assets/icons/rose.png'">

                    </div>

                    <span>Floreria</span>

                </a>

            </div>

        </section>


        

      <!-- Seccion de Negocios Recomendados -->
      <?php if (!empty($negocios_recomendados) && empty($termino_busqueda)): ?>
      <section class="recommended-section" style="padding: 1rem 0 0.5rem 0;">
            <h2 class="featured-title" style="margin-bottom: 1rem;">Recomendados</h2>

            <div class="recommended-scroll" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 1rem; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
                <?php foreach ($negocios_recomendados as $rec): ?>
                    <?php $esta_abierto_rec = isBusinessOpen($db, $rec['id_negocio']); ?>
                    <a href="negocio.php?id=<?php echo $rec['id_negocio']; ?>" class="featured-card recommended-card-item" style="flex: 0 0 280px; scroll-snap-align: start; text-decoration: none; color: inherit; border: 3px solid #D4AF37; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);">
                        <div class="card-image" style="background-image: url('<?php echo !empty($rec['imagen_portada']) ? $rec['imagen_portada'] : 'assets/img/restaurants/default.jpg'; ?>'); position: relative;">

                            <!-- Badge de verificado azul -->
                            <div style="position: absolute; top: 10px; left: 10px; z-index: 3;">
                                <img src="assets/icons/verificado.png" alt="Verificado" style="width: 28px; height: 28px;">
                            </div>

                            <!-- Badge Abierto/Cerrado -->
                            <?php if ($esta_abierto_rec): ?>
                                <span style="position: absolute; top: 10px; right: 10px; background: rgba(16, 185, 129, 0.95); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; z-index: 2;">Abierto</span>
                            <?php else: ?>
                                <span style="position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.95); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; z-index: 2;">Cerrado</span>
                                <div style="position:absolute; inset:0; background:rgba(0,0,0,0.35); z-index:1;"></div>
                            <?php endif; ?>

                            <div class="card-overlay">
                                <h3><?php echo htmlspecialchars($rec['nombre']); ?></h3>
                                <div class="card-info">
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo $rec['tiempo_preparacion_promedio'] ?? 25; ?> min
                                    </span>
                                    <span class="rating">
                                        <i class="fas fa-star" style="font-size: 0.8rem; margin-right: 2px;"></i>
                                        <?php echo number_format($rec['rating'] ?? 0, 1); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <style>
                .recommended-scroll::-webkit-scrollbar { display: none; }
                .recommended-card-item { transition: transform 0.2s, box-shadow 0.2s; }
                .recommended-card-item:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
                }
            </style>
      </section>
      <?php endif; ?>

      <section class="featured-section">
            <h2 class="featured-title">Restaurantes populares</h2>

            <div class="restaurants-grid">

                <?php if (!empty($termino_busqueda) && empty($negocios_destacados)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No se encontraron resultados</h3>
                        <p>No hemos encontrado resultados para "<?php echo htmlspecialchars($termino_busqueda); ?>".</p>
                        <a href="index.php" class="btn-reset">Ver todos los restaurantes</a>
                    </div>

                <?php elseif (empty($negocios_destacados)): ?>
                    <div class="no-results" style="padding: 4rem 1rem;">
                        <div style="background: var(--gray-100); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                            <i class="fas fa-store-slash" style="font-size: 2rem; color: var(--gray-400);"></i>
                        </div>
                        <h3 style="color: var(--gray-800);">No hay negocios disponibles</h3>
                        <p style="color: var(--gray-500); max-width: 300px; margin: 0 auto;">
                            Parece que aún no tenemos restaurantes registrados en esta zona. ¡Estamos trabajando para llegar pronto!
                        </p>
                    </div>

                <?php else: ?>
                    <div class="featured-cards">
                        <?php foreach ($negocios_destacados as $neg): ?>
                            <?php 
                                // Lógica de Abierto/Cerrado usando función estandarizada
                                // Consulta la tabla negocio_horarios para determinar si está abierto
                                $esta_abierto = isBusinessOpen($db, $neg['id_negocio']);
                            ?>

                            <a href="negocio.php?id=<?php echo $neg['id_negocio']; ?>" class="featured-card" style="text-decoration: none; color: inherit;">
                                <div class="card-image" style="background-image: url('<?php echo !empty($neg['imagen_portada']) ? $neg['imagen_portada'] : 'assets/img/restaurants/default.jpg'; ?>');">

                                    <!-- Badges Premium/Verificado -->
                                    <?php if (!empty($neg['es_premium']) || !empty($neg['verificado'])): ?>
                                    <div style="position: absolute; top: 10px; left: 10px; display: flex; gap: 4px; z-index: 3;">
                                        <?php if (!empty($neg['es_premium'])): ?>
                                        <span style="background: linear-gradient(135deg, #FFD700, #FFA500); color: #000; padding: 3px 6px; border-radius: 10px; font-size: 0.65rem; font-weight: 700; display: flex; align-items: center; gap: 2px;">
                                            <i class="fas fa-crown"></i>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($neg['verificado'])): ?>
                                        <span style="background: #3B82F6; color: white; padding: 3px 6px; border-radius: 10px; font-size: 0.65rem; font-weight: 700;">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($esta_abierto): ?>
                                        <span class="status-badge status-open" style="position: absolute; top: 10px; right: 10px; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; z-index: 2; backdrop-filter: blur(4px); background: rgba(16, 185, 129, 0.9); color: white;">
                                            Abierto
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-closed" style="position: absolute; top: 10px; right: 10px; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; z-index: 2; backdrop-filter: blur(4px); background: rgba(239, 68, 68, 0.9); color: white;">
                                            Cerrado
                                        </span>
                                        <div style="position:absolute; inset:0; background:rgba(0,0,0,0.4); z-index:1;"></div>
                                    <?php endif; ?>

                                    <div class="card-overlay">
                                        <h3><?php echo htmlspecialchars($neg['nombre']); ?></h3>
                                        <div class="card-info">
                                            <span>
                                                <i class="fas fa-clock"></i> 
                                                <?php echo htmlspecialchars($neg['tiempo_preparacion_promedio']); ?> min
                                            </span>
                                            <span class="rating">
                                                <i class="fas fa-star" style="font-size: 0.8rem; margin-right: 2px;"></i>
                                                <?php echo number_format($neg['rating'], 1); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    

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
</div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        // ==========================================
        // DECLARACIONES (Primero)
        // ==========================================
        
        // Sistema unificado de validación
        const TEXTOS_INVALIDOS = [
            'ubicación no disponible',
            'ubicacion no disponible', 
            'tocar para ubicar',
            'toca aquí para ubicar',
            'toca aqui para ubicar',
            'detectando ubicación',
            'detectando',
            'obteniendo dirección',
            'error',
            'undefined',
            'null',
            'tu ubicación actual',
            'ubicación aproximada'
        ];

        const STORAGE_KEYS = {
            DIRECCION: 'quickbite_direccion',
            UBICACION: 'quickbite_ubicacion',
            CACHE: 'quickbite_location_cache'
        };

        function esTextoInvalido(texto) {
            if (!texto || texto.trim() === '') return true;
            const textoLower = texto.toLowerCase();
            return TEXTOS_INVALIDOS.some(invalido => textoLower.includes(invalido));
        }

        function guardarUbicacion(lat, lng, direccion, municipio = '') {
            if (esTextoInvalido(direccion)) return false;
            
            const data = {
                lat,
                lng,
                direccion,
                municipio,
                timestamp: Date.now()
            };
            
            localStorage.setItem(STORAGE_KEYS.DIRECCION, direccion);
            localStorage.setItem(STORAGE_KEYS.UBICACION, JSON.stringify(data));
            return true;
        }

        function obtenerUbicacionGuardada() {
            try {
                const data = localStorage.getItem(STORAGE_KEYS.UBICACION);
                if (!data) return null;
                
                const ubicacion = JSON.parse(data);
                
                // Validar vigencia (30 min) y texto válido
                if ((Date.now() - ubicacion.timestamp) > 1800000) return null;
                if (esTextoInvalido(ubicacion.direccion)) return null;
                
                return ubicacion;
            } catch {
                return null;
            }
        }

        // ==========================================
        // LIMPIEZA DE CACHE (Después de declaraciones)
        // ==========================================
        (function() {
            try {
                const direccion = localStorage.getItem(STORAGE_KEYS.DIRECCION);
                if (direccion && esTextoInvalido(direccion)) {
                    localStorage.removeItem(STORAGE_KEYS.DIRECCION);
                    console.log('🗑️ Dirección inválida limpiada');
                }
                
                const ubicacionGuardada = obtenerUbicacionGuardada();
                if (!ubicacionGuardada) {
                    localStorage.removeItem(STORAGE_KEYS.DIRECCION);
                    localStorage.removeItem(STORAGE_KEYS.UBICACION);
                    localStorage.removeItem(STORAGE_KEYS.CACHE);
                    console.log('🗑️ Cache expirado limpiado');
                }
            } catch (e) {
                console.log('🗑️ Error limpiando cache:', e);
            }
        })();

        // ==========================================
        // RESTO DEL CÓDIGO
        // ==========================================
        
        // Variable que indica si hay negocios disponibles en la zona (desde PHP)
        const hayNegociosEnZona = <?php echo !empty($negocios_destacados) ? 'true' : 'false'; ?>;
        const cantidadNegocios = <?php echo count($negocios_destacados); ?>;
        
        // Función para mostrar mensajes en el toast
        function mostrarToast(mensaje, duracion = 3000) {
            const toast = document.getElementById('location-toast');
            toast.textContent = mensaje;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, duracion);
        }
        
        // Municipios disponibles para el servicio
        const municipiosDisponibles = [
            'Teocaltiche',
            'Villa Hidalgo', 
            'La Barca',
            'Ojuelos',
        ];

        // Función para limpiar acentos y caracteres especiales
        function limpiarTexto(texto) {
            if (!texto) return '';
            return texto
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Quitar acentos
                .replace(/\s+/g, ' ') // Normalizar espacios
                .trim()
                .toLowerCase();
        }

        // Función mejorada para verificar cobertura con datos de Google Maps
        function verificarCobertura(ubicacionInfo) {
            console.log('Verificando cobertura para:', ubicacionInfo);
            
            // Si hay negocios en la zona (verificado por el servidor), hay cobertura
            if (hayNegociosEnZona) {
                console.log('✅ Hay negocios en la zona (servidor confirmó):', cantidadNegocios);
                return true;
            }
            
            // Si es una cadena, convertir a objeto
            if (typeof ubicacionInfo === 'string') {
                ubicacionInfo = { direccionCompleta: ubicacionInfo };
            }
            
            const campos = [
                ubicacionInfo.direccionCompleta,
                ubicacionInfo.ciudad,
                ubicacionInfo.municipio,
                ubicacionInfo.googleFormatted
            ].filter(campo => campo && campo.trim() !== '');
            
            console.log('Campos a verificar:', campos);
            
            // Buscar en todos los campos disponibles (con limpieza de acentos)
            for (let municipio of municipiosDisponibles) {
                const municipioLimpio = limpiarTexto(municipio);
                console.log('Verificando municipio:', municipio, '->', municipioLimpio);
                
                for (let campo of campos) {
                    const campoLimpio = limpiarTexto(campo);
                    if (campoLimpio.includes(municipioLimpio)) {
                        console.log(`¡Cobertura encontrada! ${municipio} en: ${campo}`);
                        return true;
                    }
                }
            }
            
            console.log('No se encontró cobertura en ningún campo');
            return false;
        }

        // Función para obtener dirección usando Google Maps API
        async function obtenerDireccionPorCoordenadas(lat, lng) {
            try {
                const googleApiKey = '<?php echo getenv("GOOGLE_MAPS_API_KEY") ?: ""; ?>';
                const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${googleApiKey}&language=es&region=mx`;
                console.log('Haciendo petición a Google Maps API:', url);
                
                const response = await fetch(url);
                const data = await response.json();
                
                console.log('Respuesta completa de Google Maps:', data);
                
                if (data.status === 'OK' && data.results && data.results.length > 0) {
                    const result = data.results[0];
                    
                    // Extraer componentes de la dirección de Google Maps
                    const components = {};
                    result.address_components.forEach(component => {
                        const types = component.types;
                        if (types.includes('locality')) {
                            components.city = component.long_name;
                        } else if (types.includes('administrative_area_level_2')) {
                            components.municipality = component.long_name;
                        } else if (types.includes('administrative_area_level_1')) {
                            components.state = component.long_name;
                        } else if (types.includes('sublocality') || types.includes('sublocality_level_1')) {
                            components.neighborhood = component.long_name;
                        } else if (types.includes('sublocality_level_2')) {
                            components.sublocality = component.long_name;
                        } else if (types.includes('route')) {
                            components.route = component.long_name;
                        } else if (types.includes('street_number')) {
                            components.street_number = component.long_name;
                        }
                    });
                    
                    console.log('Componentes extraídos de Google Maps:', components);
                    
                    // Construir dirección legible y limpia
                    const ciudad = components.city || components.municipality || '';
                    const estado = components.state || '';
                    const municipio = components.municipality || components.city || '';
                    const barrio = components.neighborhood || '';
                    
                    // Crear dirección más legible evitando "unnamed road"
                    let direccionLegible = '';
                    
                    if (barrio && ciudad && barrio !== ciudad) {
                        direccionLegible = `${barrio}, ${ciudad}`;
                    } else if (ciudad) {
                        direccionLegible = ciudad;
                    } else if (municipio) {
                        direccionLegible = municipio;
                    }
                    
                    // Agregar estado si no está incluido ya
                    if (estado && !direccionLegible.toLowerCase().includes(estado.toLowerCase())) {
                        direccionLegible += direccionLegible ? `, ${estado}` : estado;
                    }
                    
                    // Si no tenemos una dirección clara, usar la formateada pero limpiarla
                    if (!direccionLegible) {
                        direccionLegible = result.formatted_address;
                        // Limpiar "unnamed road" y similares
                        direccionLegible = direccionLegible.replace(/unnamed\s+road,?\s*/gi, '');
                        direccionLegible = direccionLegible.replace(/^\s*,\s*/, ''); // Quitar comas al inicio
                    }
                    
                    const ubicacionInfo = {
                        direccionCompleta: direccionLegible,
                        ciudad: ciudad,
                        estado: estado,  
                        municipio: municipio,
                        coordenadas: { lat, lng },
                        componentesCompletos: components,
                        googleFormatted: result.formatted_address
                    };
                    
                    console.log('Información final procesada:', ubicacionInfo);
                    return ubicacionInfo;
                }
                
                console.error('❌ Error en Google Maps API:', data.status, data.error_message || 'Sin mensaje');
                
                // Fallback robusto: intentar extraer datos de formatted_address si existe
                if (data.results && data.results.length > 0 && data.results[0].formatted_address) {
                    const partes = data.results[0].formatted_address.split(',').map(p => p.trim());
                    const calle = partes[0] || '';
                    const ciudad = partes[1] || '';
                    const estado = partes[2] || '';
                    
                    return {
                        direccionCompleta: `${calle}, ${ciudad}`.replace(/^,\s*|,\s*$/g, ''),
                        ciudad: ciudad,
                        estado: estado,
                        municipio: ciudad,
                        coordenadas: { lat, lng },
                        componentesCompletos: { route: calle, city: ciudad, state: estado },
                        googleFormatted: data.results[0].formatted_address
                    };
                }
                
                // Retornar objeto básico con datos mínimos para que no falle
                return {
                    direccionCompleta: 'Tu ubicación actual',
                    ciudad: '',
                    estado: '',
                    municipio: '',
                    coordenadas: { lat, lng },
                    componentesCompletos: {},
                    googleFormatted: 'Tu ubicación actual'
                };
                
            } catch (error) {
                console.error('❌ Error obteniendo dirección con Google Maps:', error);
                // Retornar objeto básico en lugar de null
                return {
                    direccionCompleta: 'Tu ubicación actual',
                    ciudad: '',
                    estado: '',
                    municipio: '',
                    coordenadas: { lat, lng },
                    componentesCompletos: {},
                    googleFormatted: 'Tu ubicación actual'
                };
            }
        }

        // Función principal para obtener ubicación
        function obtenerUbicacion() {
            const locationText = document.getElementById('location-text');
            const locationBtn = document.querySelector('.location-btn');
            
            // Deshabilitar botón y mostrar cargando
            locationBtn.disabled = true;
            locationText.textContent = 'Detectando ubicación...';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    async function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        try {
                            // Obtener dirección real
                            const ubicacionInfo = await obtenerDireccionPorCoordenadas(lat, lng);
                            
                            if (ubicacionInfo) {
                                console.log('✅ Información de ubicación obtenida:', ubicacionInfo);
                                
                                // Usar formatted_address de Google Maps para máxima precisión
                                const direccionCompleta = ubicacionInfo.googleFormatted || 
                                                        ubicacionInfo.direccionCompleta || 
                                                        `${ubicacionInfo.ciudad}, ${ubicacionInfo.estado}` || 
                                                        'Ubicación detectada';
                                
                                const direccionCorta = ubicacionInfo.ciudad || 
                                                    ubicacionInfo.municipio || 
                                                    'Ubicación actual';
                                
                                // Guardar en localStorage usando función unificada
                                guardarUbicacion(lat, lng, direccionCompleta, ubicacionInfo.municipio || '');
                                
                                // Verificar cobertura usando la función mejorada
                                const tieneCobertura = verificarCobertura(ubicacionInfo);
                                
                                // Mostrar dirección completa exacta de Google Maps
                                locationText.textContent = direccionCompleta;
                                console.log('📍 Dirección mostrada:', direccionCompleta);
                                
                                // Redirigir con parámetros de ubicación
                                const municipioParam = encodeURIComponent(ubicacionInfo.municipio || ubicacionInfo.ciudad || '');
                                window.location.href = `index.php?lat=${lat}&lng=${lng}&municipio=${municipioParam}`;
                                return; // Salir porque vamos a redirigir
                                
                            } else {
                                throw new Error('No se pudo obtener la dirección');
                            }
                        } catch (error) {
                            console.error('Error procesando ubicación:', error);
                            // NO mostrar "Ubicación no disponible", usar coordenadas si las tenemos
                            locationText.textContent = `Ubicación: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                            // Aún así redirigir para buscar negocios cercanos
                            window.location.href = `index.php?lat=${lat}&lng=${lng}`;
                            return;
                        }
                        
                        locationBtn.disabled = false;
                    },
                    function(error) {
                        locationBtn.disabled = false;
                        
                        // Intentar usar dirección guardada del usuario
                        const direccionUsuario = obtenerDireccionPrincipalUsuario();
                        if (direccionUsuario && direccionUsuario.direccion && !esTextoInvalido(direccionUsuario.direccion)) {
                            locationText.textContent = direccionUsuario.direccion;
                            document.getElementById('lat').value = direccionUsuario.lat || '';
                            document.getElementById('lng').value = direccionUsuario.lng || '';
                        } else {
                            locationText.textContent = 'Toca aquí para ubicar';
                        }
                        
                        // Mensajes más amigables
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                mostrarToast("💡 Activa los permisos de ubicación en tu navegador", 4000);
                                break;
                            case error.POSITION_UNAVAILABLE:
                                mostrarToast("No pudimos obtener tu ubicación. Intenta de nuevo", 3000);
                                break;
                            case error.TIMEOUT:
                                mostrarToast("La ubicación está tardando mucho. Reintenta", 3000);
                                break;
                            default:
                                mostrarToast("Error obteniendo ubicación", 2500);
                                break;
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 300000 // 5 minutos cache
                    }
                );
            } else {
                locationBtn.disabled = false;
                locationText.textContent = 'Geolocalización no soportada';
                mostrarToast("Tu navegador no soporta geolocalización");
            }
        }

        // Función para mostrar alerta de cobertura
        function mostrarAlertaCobertura(ubicacion) {
            const alertaHtml = `
                <div id="cobertura-alert" class="cobertura-alert">
                    <div class="alert-content">
                        <div class="alert-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="alert-text">
                            <h3>¡Próximamente en ${ubicacion}!</h3>
                            <p>Aún no tenemos cobertura en tu área, pero estamos expandiéndonos rápidamente.</p>
                            <p><strong>Disponible en:</strong> Teocaltiche, Villa Hidalgo, La Barca, Ojuelos, Jalisco</p>
                        </div>
                        <button class="alert-close" onclick="cerrarAlertaCobertura()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Insertar alerta en el DOM
            document.body.insertAdjacentHTML('beforeend', alertaHtml);
            
            // Mostrar con animación
            setTimeout(() => {
                document.getElementById('cobertura-alert').classList.add('show');
            }, 100);
        }

        // Función para cerrar alerta de cobertura
        function cerrarAlertaCobertura() {
            const alert = document.getElementById('cobertura-alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }

        // Función para abrir programa de referidos
        function abrirReferidos() {
            <?php if ($usuario_logueado): ?>
                window.location.href = 'perfil.php?tab=referidos';
            <?php else: ?>
                window.location.href = 'login.php?redirect=referidos';
            <?php endif; ?>
        }

        // Función para abrir membresía
        function abrirMembresia() {
            <?php if ($usuario_logueado): ?>
                window.location.href = 'membership_subscribe.php';
            <?php else: ?>
                window.location.href = 'login.php?redirect=membership';
            <?php endif; ?>
        }

        // Direcciones guardadas del usuario (desde BD)
        const direccionesUsuario = <?php echo json_encode($mis_direcciones ?? []); ?>;
        const usuarioLogueado = <?php echo $usuario_logueado ? 'true' : 'false'; ?>;
        
        // Función para obtener dirección principal del usuario
        function obtenerDireccionPrincipalUsuario() {
            if (!usuarioLogueado || !direccionesUsuario || direccionesUsuario.length === 0) {
                return null;
            }
            
            // Buscar dirección predeterminada/principal
            let direccionPrincipal = direccionesUsuario.find(d => 
                d.es_predeterminada == 1 || d.es_principal == 1
            );
            
            // Si no hay predeterminada, usar la primera
            if (!direccionPrincipal) {
                direccionPrincipal = direccionesUsuario[0];
            }
            
            if (direccionPrincipal) {
                // Construir dirección legible con calle y número
                let direccionCompleta = '';
                
                if (direccionPrincipal.calle) {
                    direccionCompleta = direccionPrincipal.calle;
                    if (direccionPrincipal.numero) {
                        direccionCompleta += ' #' + direccionPrincipal.numero;
                    }
                    if (direccionPrincipal.colonia) {
                        direccionCompleta += ', ' + direccionPrincipal.colonia;
                    }
                    if (direccionPrincipal.ciudad) {
                        direccionCompleta += ', ' + direccionPrincipal.ciudad;
                    }
                } else if (direccionPrincipal.direccion) {
                    // Usar campo direccion de ubicaciones_usuarios
                    direccionCompleta = direccionPrincipal.direccion;
                }
                
                return {
                    direccion: direccionCompleta,
                    lat: parseFloat(direccionPrincipal.latitud || direccionPrincipal.lat || 0),
                    lng: parseFloat(direccionPrincipal.longitud || direccionPrincipal.lng || 0),
                    municipio: direccionPrincipal.ciudad || ''
                };
            }
            
            return null;
        }

        // Detección automática de ubicación al cargar la página
        document.addEventListener('DOMContentLoaded', async function() {
            const locationText = document.getElementById('location-text');
            const urlParams = new URLSearchParams(window.location.search);
            
            let direccionEstablecida = false;
            
            // PASO 1: Hay coordenadas en URL (servidor ya filtró negocios)
            if (urlParams.has('lat') && urlParams.has('lng')) {
                const lat = parseFloat(urlParams.get('lat'));
                const lng = parseFloat(urlParams.get('lng'));
                
                document.getElementById('lat').value = lat;
                document.getElementById('lng').value = lng;
                
                let direccionFinal = '';
                
                // Prioridad 1: Usuario logueado con dirección
                if (usuarioLogueado && direccionesUsuario?.length > 0) {
                    const dir = direccionesUsuario.find(d => d.es_predeterminada == 1) || direccionesUsuario[0];
                    if (dir?.direccion) {
                        direccionFinal = dir.direccion;
                    } else if (dir?.calle && dir?.numero) {
                        direccionFinal = `${dir.calle} #${dir.numero}, ${dir.ciudad || ''}`.trim();
                    }
                }
                
                // Prioridad 2: localStorage válido
                if (!direccionFinal) {
                    const localDir = localStorage.getItem(STORAGE_KEYS.DIRECCION);
                    if (localDir && !esTextoInvalido(localDir)) {
                        direccionFinal = localDir;
                    }
                }
                
                // Prioridad 3: Google Maps API
                if (!direccionFinal) {
                    locationText.textContent = 'Obteniendo dirección...';
                    try {
                        await obtenerDireccionSinRecargar(lat, lng);
                        direccionEstablecida = true;
                    } catch (e) {
                        direccionFinal = `Ubicación GPS`;
                    }
                }
                
                if (direccionFinal && !direccionEstablecida) {
                    locationText.textContent = direccionFinal;
                    guardarUbicacion(lat, lng, direccionFinal, urlParams.get('municipio') || '');
                    direccionEstablecida = true;
                }
                
                if (hayNegociosEnZona && direccionEstablecida) {
                    mostrarToast(`${cantidadNegocios} negocio(s) disponibles`, 2500);
                }
                
            } else {
                // PASO 2: Sin coordenadas - buscar ubicación guardada
                
                // Opción A: Usuario con dirección en BD
                if (usuarioLogueado && direccionesUsuario?.length > 0) {
                    const dir = direccionesUsuario.find(d => d.es_predeterminada == 1) || direccionesUsuario[0];
                    if (dir?.latitud && dir?.longitud) {
                        const textoDir = dir.direccion || `${dir.calle} #${dir.numero}, ${dir.ciudad}`;
                        locationText.textContent = textoDir;
                        
                        setTimeout(() => {
                            window.location.href = `index.php?lat=${dir.latitud}&lng=${dir.longitud}&municipio=${encodeURIComponent(dir.ciudad || '')}`;
                        }, 300);
                        return;
                    }
                }
                
                // Opción B: localStorage reciente
                const ubicacionGuardada = obtenerUbicacionGuardada();
                if (ubicacionGuardada) {
                    locationText.textContent = ubicacionGuardada.direccion;
                    setTimeout(() => {
                        window.location.href = `index.php?lat=${ubicacionGuardada.lat}&lng=${ubicacionGuardada.lng}&municipio=${encodeURIComponent(ubicacionGuardada.municipio || '')}`;
                    }, 300);
                    return;
                }
                
                // Opción C: Mostrar mensaje por defecto
                locationText.textContent = 'Toca aquí para ubicar';
            }
        });

        // Sistema de cache para ubicación
        const LOCATION_CACHE_KEY = 'quickbite_location_cache';
        const CACHE_DURATION = 5 * 60 * 1000; // 5 minutos

        // Función para obtener ubicación automáticamente sin interacción del usuario
        function obtenerUbicacionAutomatica() {
            // Verificar cache primero
            const cachedLocation = getCachedLocation();
            if (cachedLocation) {
                console.log('Usando ubicación en cache');
                document.getElementById('location-text').textContent = cachedLocation.direccion;
                document.getElementById('lat').value = cachedLocation.lat;
                document.getElementById('lng').value = cachedLocation.lng;
                return;
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    async function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        await obtenerDireccionSinRecargar(lat, lng);
                    },
                    function(error) {
                        // No mostrar errores molestos
                        console.log('Ubicación no disponible:', error.message);
                        
                        // Si no se puede obtener ubicación, intentar usar dirección guardada del usuario
                        const direccionUsuario = obtenerDireccionPrincipalUsuario();
                        if (direccionUsuario && direccionUsuario.direccion) {
                            document.getElementById('location-text').textContent = direccionUsuario.direccion;
                            if (direccionUsuario.lat && direccionUsuario.lng) {
                                document.getElementById('lat').value = direccionUsuario.lat;
                                document.getElementById('lng').value = direccionUsuario.lng;
                            }
                        } else {
                            document.getElementById('location-text').textContent = 'Tocar para ubicar';
                        }
                    },
                    {
                        enableHighAccuracy: false, // Más rápido para detección automática
                        timeout: 10000,
                        maximumAge: 600000 // 10 minutos de cache
                    }
                );
            } else {
                // Navegador no soporta geolocalización
                const direccionUsuario = obtenerDireccionPrincipalUsuario();
                if (direccionUsuario && direccionUsuario.direccion) {
                    document.getElementById('location-text').textContent = direccionUsuario.direccion;
                } else {
                    document.getElementById('location-text').textContent = 'Tocar para ubicar';
                }
            }
        }

        // Funciones de cache
        function getCachedLocation() {
            try {
                const cached = localStorage.getItem(LOCATION_CACHE_KEY);
                if (!cached) return null;
                
                const data = JSON.parse(cached);
                const now = new Date().getTime();
                
                if (now - data.timestamp > CACHE_DURATION) {
                    localStorage.removeItem(LOCATION_CACHE_KEY);
                    return null;
                }
                
                return data;
            } catch (e) {
                return null;
            }
        }

        function setCachedLocation(lat, lng, direccion) {
            try {
                const data = {
                    lat: lat,
                    lng: lng,
                    direccion: direccion,
                    timestamp: new Date().getTime()
                };
                localStorage.setItem(LOCATION_CACHE_KEY, JSON.stringify(data));
            } catch (e) {
                console.log('No se pudo guardar en cache:', e);
            }
        }

        // Función para obtener dirección sin recargar la página
        async function obtenerDireccionSinRecargar(lat, lng) {
            try {
                const ubicacionInfo = await obtenerDireccionPorCoordenadas(lat, lng);
                
                if (ubicacionInfo) {
                    console.log('Información de ubicación obtenida:', ubicacionInfo);
                    
                    // Construir dirección mostrando calle y municipio
                    let direccionCompleta = '';
                    
                    if (ubicacionInfo.componentesCompletos) {
                        const c = ubicacionInfo.componentesCompletos;
                        
                        // Obtener calle completa (número + nombre de calle)
                        let calle = '';
                        if (c.street_number && c.route) {
                            calle = `${c.route} ${c.street_number}`;
                        } else if (c.route) {
                            calle = c.route;
                        }
                        
                        // Obtener municipio
                        const municipio = c.municipality || c.city || '';
                        
                        // Construir dirección: Calle, Municipio
                        if (calle && municipio) {
                            direccionCompleta = `${calle}, ${municipio}`;
                        } else if (municipio) {
                            // Si no hay calle, usar barrio o colonia + municipio
                            const barrio = c.neighborhood || c.sublocality || '';
                            if (barrio) {
                                direccionCompleta = `${barrio}, ${municipio}`;
                            } else {
                                direccionCompleta = municipio;
                            }
                        }
                    }
                    
                    // Fallback si no se encuentra una dirección decente
                    if (!direccionCompleta || direccionCompleta.toLowerCase().includes('unnamed')) {
                        // Construir dirección con calle y número si están disponibles
                        const c = ubicacionInfo.componentesCompletos || {};
                        if (c.route) {
                            direccionCompleta = c.route;
                            if (c.street_number) {
                                direccionCompleta += ' #' + c.street_number;
                            }
                            if (c.city || c.municipality) {
                                direccionCompleta += ', ' + (c.city || c.municipality);
                            }
                        } else if (c.neighborhood && (c.city || c.municipality)) {
                            direccionCompleta = c.neighborhood + ', ' + (c.city || c.municipality);
                        } else if (c.city || c.municipality) {
                            // Si hay ciudad/municipio, usar eso
                            direccionCompleta = c.city || c.municipality;
                        } else if (ubicacionInfo.googleFormatted) {
                            // Usar el formato de Google como fallback
                            direccionCompleta = ubicacionInfo.googleFormatted;
                        } else {
                            // Último recurso: usar dirección del usuario si existe
                            const direccionUsuario = obtenerDireccionPrincipalUsuario();
                            if (direccionUsuario && direccionUsuario.direccion) {
                                direccionCompleta = direccionUsuario.direccion;
                            } else {
                                // Si nada funciona, mostrar coordenadas parciales
                                direccionCompleta = `Ubicación: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                            }
                        }
                    }
                    
                    // Validar que la dirección final sea válida
                    if (esTextoInvalido(direccionCompleta) || direccionCompleta.toLowerCase().includes('unnamed road')) {
                        console.warn('⚠️ Dirección inválida detectada:', direccionCompleta);
                        
                        const direccionUsuario = obtenerDireccionPrincipalUsuario();
                        if (direccionUsuario?.direccion && !esTextoInvalido(direccionUsuario.direccion)) {
                            direccionCompleta = direccionUsuario.direccion;
                        } else {
                            direccionCompleta = 'Ubicación actual';
                        }
                    }
                    
                    document.getElementById('location-text').textContent = direccionCompleta;
                    
                    // Guardar en cache (sistema antiguo)
                    setCachedLocation(lat, lng, direccionCompleta);
                    
                    // Guardar en localStorage (sistema unificado)
                    guardarUbicacion(lat, lng, direccionCompleta, ubicacionInfo.municipio || ubicacionInfo.ciudad || '');
                    
                    // Actualizar campos ocultos para futuras búsquedas
                    document.getElementById('lat').value = lat;
                    document.getElementById('lng').value = lng;
                    
                    // NO mostrar alerta de cobertura aquí - el servidor ya verificó si hay negocios
                    // La variable hayNegociosEnZona se calcula en el servidor con las coordenadas actuales
                    if (hayNegociosEnZona) {
                        mostrarToast("Ubicación detectada correctamente", 2000);
                    }
                    // Si no hay negocios, simplemente no mostrar nada molesto
                } else {
                    // Si no hay info de ubicación, usar coordenadas
                    const direccionFallback = `Ubicación: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                    document.getElementById('location-text').textContent = direccionFallback;
                    guardarUbicacion(lat, lng, direccionFallback, '');
                }
            } catch (error) {
                console.error('Error procesando ubicación:', error);
                // En caso de error, mostrar coordenadas
                const direccionError = `Ubicación: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                document.getElementById('location-text').textContent = direccionError;
                guardarUbicacion(lat, lng, direccionError, '');
            }
        }
    </script>
  

  <!-- ✅ MEMBERSHIP MODAL - SOLO PARA NO MIEMBROS -->



    <script src="assets/js/transitions.js"></script>
<script src="assets/js/hero-slider.js"></script>

<!-- PWA Service Worker Registration (Versión Simple) -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            // Desregistrar service worker anterior si existe
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (let registration of registrations) {
                await registration.unregister();
                console.log('Service Worker anterior desregistrado');
            }
            
            // Registrar nuevo service worker simple
            const registration = await navigator.serviceWorker.register('/sw-simple.js');
            console.log('✅ Service Worker simple registrado:', registration);
            
        } catch (error) {
            console.error('❌ Error con Service Worker:', error);
        }
    });
}

// FUNCIONES PARA DIRECCIONES GUARDADAS
function toggleSavedAddresses() {
    const addressesBar = document.getElementById('saved-addresses-bar');
    if (addressesBar.style.display === 'block') {
        closeSavedAddresses();
    } else {
        addressesBar.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeSavedAddresses() {
    const addressesBar = document.getElementById('saved-addresses-bar');
    addressesBar.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function getCurrentLocation() {
    closeSavedAddresses();
    obtenerUbicacion();
}

function selectAddress(label, address) {
    document.getElementById('location-text').textContent = address;
    closeSavedAddresses();
    
    // Aquí puedes agregar lógica para geocodificar la dirección si es necesario
    console.log('Dirección seleccionada:', label, address);
}

// ✅ NUEVA FUNCIÓN: Seleccionar dirección con coordenadas guardadas
function selectAddressWithCoords(label, address, lat, lng, municipio = '') {
    document.getElementById('location-text').textContent = address;
    closeSavedAddresses();
    
    // Si tenemos coordenadas, actualizar los campos ocultos y recargar
    if (lat !== null && lng !== null) {
        document.getElementById('lat').value = lat;
        document.getElementById('lng').value = lng;
        
        // Actualizar URL con las coordenadas y municipio
        const url = new URL(window.location.href);
        url.searchParams.set('lat', lat);
        url.searchParams.set('lng', lng);
        if (municipio) {
            url.searchParams.set('municipio', municipio);
        }
        
        // Guardar en localStorage usando función unificada
        guardarUbicacion(lat, lng, address, municipio);
        
        // Mostrar toast de confirmación
        mostrarToast(`Dirección "${label}" seleccionada`, 2000);
        
        // Recargar página con nuevas coordenadas
        setTimeout(() => {
            window.location.href = url.toString();
        }, 500);
    } else {
        // Sin coordenadas, solo mostrar el texto
        mostrarToast(`Dirección "${label}" seleccionada (sin coordenadas GPS)`, 2000);
        console.log('Dirección seleccionada (sin coords):', label, address);
    }
}

function addNewAddress() {
    closeSavedAddresses();
    // Redirigir al perfil para agregar nueva dirección
    window.location.href = 'perfil.php#direcciones';
}

// Cerrar la barra al hacer clic fuera
document.addEventListener('click', function(event) {
    const addressesBar = document.getElementById('saved-addresses-bar');
    const locationBtn = document.querySelector('.location-btn');
    
    if (addressesBar.style.display === 'block' && 
        !addressesBar.contains(event.target) && 
        !locationBtn.contains(event.target)) {
        closeSavedAddresses();
    }
});
</script>
<?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
<?php
// Modal de reseñas - Solo para usuarios autenticados (clientes)
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true &&
    (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] === 'cliente')) {
    include 'includes/modal_resena.php';
}
?>
</body>
</html>