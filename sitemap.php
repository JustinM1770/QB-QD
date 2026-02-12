<?php
/**
 * Generador dinamico de Sitemap XML para QuickBite
 *
 * Genera un sitemap con todas las paginas publicas y negocios activos.
 * Acceder via: /sitemap.xml (requiere rewrite) o /sitemap.php
 */

header('Content-Type: application/xml; charset=UTF-8');

// Cargar configuracion
require_once __DIR__ . '/config/env.php';

$base_url = env('APP_URL', 'https://quickbite.com.mx');
$today = date('Y-m-d');

// Conectar a BD para obtener negocios
$negocios = [];
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->query("
        SELECT id_negocio, nombre, updated_at
        FROM negocios
        WHERE activo = 1 AND verificado = 1
        ORDER BY nombre
        LIMIT 1000
    ");
    $negocios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error generando sitemap: " . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <!-- Pagina Principal -->
    <url>
        <loc><?php echo $base_url; ?>/</loc>
        <lastmod><?php echo $today; ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Listado de Restaurantes -->
    <url>
        <loc><?php echo $base_url; ?>/restaurants.php</loc>
        <lastmod><?php echo $today; ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Busqueda -->
    <url>
        <loc><?php echo $base_url; ?>/buscar.php</loc>
        <lastmod><?php echo $today; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Paginas Estaticas -->
    <url>
        <loc><?php echo $base_url; ?>/proximamente.php</loc>
        <lastmod><?php echo $today; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Negocios/Restaurantes -->
<?php foreach ($negocios as $negocio):
    $lastmod = !empty($negocio['updated_at']) ? date('Y-m-d', strtotime($negocio['updated_at'])) : $today;
?>
    <url>
        <loc><?php echo $base_url; ?>/negocio.php?id=<?php echo $negocio['id_negocio']; ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>

</urlset>
