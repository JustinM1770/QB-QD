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
require_once 'models/Negocio.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "ID de categoría no especificado.";
    exit();
}

$id_categoria = (int) $_GET['id'];

$categoria = new Categoria($db);
$categoria->id_categoria = $id_categoria;

if (!$categoria->obtenerPorId()) {
    echo "Categoría no encontrada.";
    exit();
}

$negocio = new Negocio($db);
$negocios = $negocio->obtenerPorCategoria($id_categoria);

$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($categoria->nombre); ?> - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #E3F2FD;
            --primary-dark: #0153CC;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FAFAFA;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
            font-size: 16px;
            margin: 0;
            padding: 0;
        }
        header {
            border-bottom: 1px solid var(--primary-light);
            background: var(--light);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        nav {
            padding: 1rem 0;
        }
        .back-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--primary-dark);
        }
        .hero {
            padding: 3rem 0;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }
        .hero p {
            font-size: 1.1rem;
            color: var(--dark);
            max-width: 600px;
            margin: 0 auto;
        }
        main {
            padding: 3rem 0 5rem;
        }
        .empty-state {
            text-align: center;
            padding: 5rem 1rem;
            color: var(--primary-dark);
        }
        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .card {
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border-radius: 0.75rem;
            border: 1px solid var(--primary-light);
            overflow: hidden;
            background: var(--light);
        }
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 0.25rem 0.75rem rgba(1, 101, 255, 0.15);
            transform: translateY(-0.125rem);
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
            width: 100%;
            background-color: var(--secondary);
        }
        .card-body {
            padding: 1.25rem 1.5rem;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        .card-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--primary-dark);
        }
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .tag {
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .rating {
            background: var(--primary);
            color: var(--light);
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        @media (max-width: 768px) {
            .hero {
                padding: 1.5rem 0;
            }
            .hero h1 {
                font-size: 2rem;
            }
            .hero p {
                font-size: 1rem;
            }
            main {
                padding: 1.5rem 0 2.5rem;
            }
        }
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 1.75rem;
            }
            .card-img-top {
                height: 160px;
            }
            .tag {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <header>
        <div class="container">
            <nav>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver al inicio
                </a>
            </nav>
        </div>
    </header>

    <div class="hero">
        <div class="container">
            <h1><?php echo htmlspecialchars($categoria->nombre); ?></h1>
            <?php if (!empty($categoria->descripcion)): ?>
                <p><?php echo htmlspecialchars($categoria->descripcion); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <main>
        <div class="container">
            <?php if (empty($negocios)): ?>
                <div class="empty-state">
                    <h2>No hay negocios disponibles</h2>
                    <p>Actualmente no hay negocios en esta categoría.</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 fade-in">
                    <?php foreach ($negocios as $neg): ?>
                        <a href="negocio.php?id=<?php echo $neg['id_negocio']; ?>" class="card text-decoration-none">
                            <img src="<?php echo $neg['imagen_portada'] ? $neg['imagen_portada'] : 'assets/img/restaurants/default.jpg'; ?>" alt="<?php echo htmlspecialchars($neg['nombre']); ?>" class="card-img-top" />
                            <div class="card-body">
                                <h3 class="card-title"><?php echo htmlspecialchars($neg['nombre']); ?></h3>
                                <div class="card-meta">
                                    <span><?php echo htmlspecialchars($neg['tiempo_preparacion_promedio']); ?> min</span>
                                    <span>Envío $<?php echo number_format($neg['costo_envio'], 2); ?></span>
                                </div>
                                <div class="tags">
                                    <?php foreach ($neg['categorias'] as $catNombre): ?>
                                        <span class="tag"><?php echo htmlspecialchars($catNombre); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="rating">
                                    ★ <?php echo number_format($neg['rating'], 1); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>
