<?php
/**
 * QuickBite - Página de Favoritos
 * Muestra los negocios favoritos del usuario
 */

// Manejador de errores centralizado
require_once __DIR__ . '/config/error_handler.php';

session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: login.php?redirect=favoritos.php");
    exit;
}

// Validar tipo de usuario
if (isset($_SESSION['tipo_usuario'])) {
    if ($_SESSION['tipo_usuario'] === 'repartidor') {
        header("Location: admin/repartidor_dashboard.php");
        exit();
    } elseif ($_SESSION['tipo_usuario'] === 'negocio') {
        header("Location: admin/negocio_configuracion.php");
        exit();
    }
}

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Membership.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

$usuario_logueado = true;
$id_usuario = $_SESSION['id_usuario'];

// Obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $id_usuario;
$usuario->obtenerPorId();

// Verificar membresía
$membership = new Membership($db);
$membership->id_usuario = $id_usuario;
$esMiembroActivo = $membership->isActive();

// Obtener favoritos del usuario
$query = "SELECT f.id_favorito, f.fecha_creacion as fecha_agregado,
                 n.id_negocio, n.nombre, n.descripcion, n.logo, n.imagen_portada,
                 n.direccion, n.calificacion_promedio, n.tiempo_entrega_estimado,
                 n.precio_minimo_pedido, n.activo,
                 c.nombre as categoria_nombre
          FROM favoritos f
          JOIN negocios n ON f.id_negocio = n.id_negocio
          LEFT JOIN categorias c ON n.id_categoria = c.id_categoria
          WHERE f.id_usuario = :id_usuario
          ORDER BY f.fecha_creacion DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->execute();
$favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_favoritos = count($favoritos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Mis Favoritos - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=League+Spartan:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #4285F4;
            --primary-dark: #0052CC;
            --secondary: #F8FAFC;
            --accent: #1E293B;
            --dark: #0F172A;
            --light: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --gradient: linear-gradient(135deg, #0165FF 0%, #4285F4 100%);
            --danger: #EF4444;
            --success: #22C55E;
            --warning: #F59E0B;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
            --border-radius: 16px;
            --border-radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
            padding-bottom: 100px;
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        /* Header */
        .header {
            background: var(--light);
            border-bottom: 2px solid var(--gray-100);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            font-family: 'League Spartan', sans-serif;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .logo .bite {
            color: #FFD700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
        }

        .back-btn {
            color: var(--gray-600);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        }

        .back-btn:hover {
            background-color: var(--gray-100);
            color: var(--primary);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        /* Page Title */
        .page-header {
            text-align: center;
            padding: 2rem 0;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-500);
            font-size: 1rem;
        }

        .favorites-count {
            background: var(--gradient);
            color: var(--light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Grid de favoritos */
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        /* Card de negocio favorito */
        .favorite-card {
            background: var(--light);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--gray-100);
            transition: var(--transition);
            position: relative;
        }

        .favorite-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .favorite-card.inactive {
            opacity: 0.6;
        }

        .favorite-card.inactive::after {
            content: 'Cerrado temporalmente';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            z-index: 5;
        }

        .card-image {
            height: 160px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .card-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.5));
        }

        .remove-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 40px;
            height: 40px;
            background: var(--light);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            z-index: 10;
        }

        .remove-btn i {
            color: var(--danger);
            font-size: 1.1rem;
        }

        .remove-btn:hover {
            background: var(--danger);
            transform: scale(1.1);
        }

        .remove-btn:hover i {
            color: var(--light);
        }

        .business-logo {
            position: absolute;
            bottom: -30px;
            left: 20px;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            border: 3px solid var(--light);
            background: var(--light);
            object-fit: cover;
            box-shadow: var(--shadow-md);
        }

        .card-content {
            padding: 2.5rem 1.25rem 1.25rem;
        }

        .business-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .business-category {
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .business-info {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .info-item i {
            color: var(--primary);
        }

        .info-item.rating i {
            color: var(--warning);
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-100);
        }

        .order-btn {
            background: var(--gradient);
            color: var(--light);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--light);
        }

        .date-added {
            font-size: 0.75rem;
            color: var(--gray-400);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--light);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            margin-top: 2rem;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray-500);
            margin-bottom: 1.5rem;
        }

        .explore-btn {
            background: var(--gradient);
            color: var(--light);
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .explore-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--light);
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--light);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0.75rem 0;
            padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            border-top: 1px solid var(--gray-100);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray-400);
            font-size: 0.75rem;
            transition: var(--transition);
            padding: 0.5rem;
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-item:hover {
            color: var(--primary);
        }

        .nav-icon {
            width: 24px;
            height: 24px;
            margin-bottom: 4px;
            opacity: 0.6;
        }

        .nav-item.active .nav-icon {
            opacity: 1;
        }

        .central-btn {
            background: var(--gradient);
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: -28px;
            box-shadow: var(--shadow-lg);
            text-decoration: none;
        }

        .central-btn .nav-icon {
            filter: brightness(0) invert(1);
            opacity: 1;
        }

        /* Toast notification */
        .toast-notification {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: var(--light);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
            display: none;
            z-index: 2000;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .favorites-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <span class="quick">Quick</span><span class="bite">Bite</span>
            </a>
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
        </div>
    </header>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-heart" style="color: var(--danger);"></i>
                Mis Favoritos
                <span class="favorites-count"><?php echo $total_favoritos; ?></span>
            </h1>
            <p>Tus restaurantes favoritos en un solo lugar</p>
        </div>

        <?php if ($total_favoritos > 0): ?>
            <!-- Grid de favoritos -->
            <div class="favorites-grid">
                <?php foreach ($favoritos as $fav): ?>
                    <div class="favorite-card <?php echo !$fav['activo'] ? 'inactive' : ''; ?>" data-id="<?php echo $fav['id_favorito']; ?>">
                        <div class="card-image" style="background-image: url('<?php echo $fav['imagen_portada'] ?: 'assets/img/default-restaurant.jpg'; ?>');">
                            <button class="remove-btn" onclick="removeFavorite(<?php echo $fav['id_negocio']; ?>, this)" title="Eliminar de favoritos">
                                <i class="fas fa-heart-broken"></i>
                            </button>
                            <img src="<?php echo $fav['logo'] ?: 'assets/img/default-logo.png'; ?>"
                                 alt="<?php echo htmlspecialchars($fav['nombre']); ?>"
                                 class="business-logo"
                                 onerror="this.src='assets/img/default-logo.png'">
                        </div>
                        <div class="card-content">
                            <h3 class="business-name"><?php echo htmlspecialchars($fav['nombre']); ?></h3>
                            <div class="business-category">
                                <i class="fas fa-utensils"></i>
                                <?php echo htmlspecialchars($fav['categoria_nombre'] ?: 'Restaurante'); ?>
                            </div>
                            <div class="business-info">
                                <?php if ($fav['calificacion_promedio']): ?>
                                    <span class="info-item rating">
                                        <i class="fas fa-star"></i>
                                        <?php echo number_format($fav['calificacion_promedio'], 1); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($fav['tiempo_entrega_estimado']): ?>
                                    <span class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $fav['tiempo_entrega_estimado']; ?> min
                                    </span>
                                <?php endif; ?>
                                <?php if ($fav['precio_minimo_pedido']): ?>
                                    <span class="info-item">
                                        <i class="fas fa-tag"></i>
                                        Min. $<?php echo number_format($fav['precio_minimo_pedido'], 0); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <span class="date-added">
                                    <i class="fas fa-calendar"></i>
                                    Agregado: <?php echo date('d/m/Y', strtotime($fav['fecha_agregado'])); ?>
                                </span>
                                <a href="negocio.php?id=<?php echo $fav['id_negocio']; ?>" class="order-btn">
                                    <i class="fas fa-shopping-bag"></i>
                                    Ordenar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="far fa-heart"></i>
                </div>
                <h3>No tienes favoritos aun</h3>
                <p>Explora restaurantes y agrega tus favoritos tocando el icono de corazon</p>
                <a href="index.php" class="explore-btn">
                    <i class="fas fa-search"></i>
                    Explorar Restaurantes
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <img src="assets/icons/home.png" alt="Inicio" class="nav-icon">
            <span>Inicio</span>
        </a>
        <a href="buscar.php" class="nav-item">
            <img src="assets/icons/search.png" alt="Buscar" class="nav-icon">
            <span>Buscar</span>
        </a>
        <a href="carrito.php" class="central-btn">
            <img src="assets/icons/cart.png" alt="Carrito" class="nav-icon">
        </a>
        <a href="favoritos.php" class="nav-item active">
            <img src="assets/icons/fav.png" alt="Favoritos" class="nav-icon">
            <span>Favoritos</span>
        </a>
        <a href="perfil.php" class="nav-item">
            <img src="assets/icons/user.png" alt="Perfil" class="nav-icon">
            <span>Perfil</span>
        </a>
    </nav>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, duration = 3000) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, duration);
        }

        function removeFavorite(idNegocio, button) {
            if (!confirm('¿Eliminar este restaurante de tus favoritos?')) {
                return;
            }

            const card = button.closest('.favorite-card');

            fetch('api/toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_negocio: idNegocio })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animar y eliminar la tarjeta
                    card.style.transition = 'all 0.3s ease';
                    card.style.transform = 'scale(0.8)';
                    card.style.opacity = '0';

                    setTimeout(() => {
                        card.remove();

                        // Actualizar contador
                        const countBadge = document.querySelector('.favorites-count');
                        let count = parseInt(countBadge.textContent) - 1;
                        countBadge.textContent = count;

                        // Si no quedan favoritos, mostrar empty state
                        if (count === 0) {
                            location.reload();
                        }
                    }, 300);

                    showToast('Eliminado de favoritos');
                } else {
                    showToast('Error al eliminar: ' + (data.message || 'Intenta de nuevo'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexion');
            });
        }
    </script>
</body>
</html>
