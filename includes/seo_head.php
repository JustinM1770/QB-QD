<?php
/**
 * Componente SEO Head para QuickBite
 *
 * Incluye meta tags, Open Graph, Twitter Cards y Schema.org
 *
 * USO:
 * $seo = [
 *     'title' => 'Titulo de la pagina',
 *     'description' => 'Descripcion de la pagina',
 *     'image' => '/assets/img/og-image.jpg',
 *     'url' => 'https://quickbite.com.mx/pagina',
 *     'type' => 'website' // o 'article', 'product', etc.
 * ];
 * include 'includes/seo_head.php';
 */

// Valores por defecto
$default_seo = [
    'title' => 'QuickBite - Pide comida a domicilio',
    'description' => 'QuickBite es la plataforma de delivery de comida mas rapida de Mexico. Pide tus platillos favoritos de los mejores restaurantes cerca de ti.',
    'keywords' => 'delivery, comida a domicilio, restaurantes, pedir comida, QuickBite, Mexico, entrega rapida',
    'image' => '/assets/img/og-default.jpg',
    'url' => '',
    'type' => 'website',
    'site_name' => 'QuickBite',
    'locale' => 'es_MX',
    'twitter_handle' => '@quickbite_mx'
];

// Mezclar con valores proporcionados
$seo = array_merge($default_seo, $seo ?? []);

// Construir URL completa
$base_url = env('APP_URL', 'https://quickbite.com.mx');
$current_url = $seo['url'] ?: $base_url . ($_SERVER['REQUEST_URI'] ?? '/');
$image_url = strpos($seo['image'], 'http') === 0 ? $seo['image'] : $base_url . $seo['image'];

// Sanitizar valores
$title = htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars(mb_substr($seo['description'], 0, 160), ENT_QUOTES, 'UTF-8');
$keywords = htmlspecialchars($seo['keywords'], ENT_QUOTES, 'UTF-8');
?>

<!-- SEO Meta Tags -->
<meta name="description" content="<?php echo $description; ?>">
<meta name="keywords" content="<?php echo $keywords; ?>">
<meta name="author" content="QuickBite">
<meta name="robots" content="index, follow">
<meta name="googlebot" content="index, follow">
<link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?php echo $seo['type']; ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
<meta property="og:title" content="<?php echo $title; ?>">
<meta property="og:description" content="<?php echo $description; ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($image_url); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="<?php echo $seo['site_name']; ?>">
<meta property="og:locale" content="<?php echo $seo['locale']; ?>">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="<?php echo $seo['twitter_handle']; ?>">
<meta name="twitter:title" content="<?php echo $title; ?>">
<meta name="twitter:description" content="<?php echo $description; ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($image_url); ?>">

<!-- PWA / Mobile -->
<meta name="theme-color" content="#FF6B35">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="QuickBite">
<link rel="apple-touch-icon" href="<?php echo $base_url; ?>/assets/icons/icon-192x192.png">

<!-- Favicon -->
<link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/img/logo.png">

<!-- Schema.org JSON-LD -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "QuickBite",
    "url": "<?php echo $base_url; ?>",
    "logo": "<?php echo $base_url; ?>/assets/img/logo.png",
    "description": "<?php echo $description; ?>",
    "address": {
        "@type": "PostalAddress",
        "addressCountry": "MX"
    },
    "contactPoint": {
        "@type": "ContactPoint",
        "contactType": "customer service",
        "availableLanguage": "Spanish"
    },
    "sameAs": [
        "https://facebook.com/quickbite.mx",
        "https://instagram.com/quickbite_mx",
        "https://twitter.com/quickbite_mx"
    ]
}
</script>

<?php if ($seo['type'] === 'website'): ?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "QuickBite",
    "url": "<?php echo $base_url; ?>",
    "potentialAction": {
        "@type": "SearchAction",
        "target": "<?php echo $base_url; ?>/buscar.php?q={search_term_string}",
        "query-input": "required name=search_term_string"
    }
}
</script>
<?php endif; ?>

<?php if (isset($negocio_data) && is_array($negocio_data)): ?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Restaurant",
    "name": "<?php echo htmlspecialchars($negocio_data['nombre'] ?? ''); ?>",
    "url": "<?php echo htmlspecialchars($current_url); ?>",
    "image": "<?php echo htmlspecialchars($negocio_data['imagen'] ?? ''); ?>",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?php echo htmlspecialchars($negocio_data['direccion'] ?? ''); ?>",
        "addressLocality": "<?php echo htmlspecialchars($negocio_data['ciudad'] ?? ''); ?>",
        "addressCountry": "MX"
    },
    "servesCuisine": "<?php echo htmlspecialchars($negocio_data['categoria'] ?? 'Comida'); ?>",
    "priceRange": "$$",
    "acceptsReservations": false,
    "hasDeliveryMethod": {
        "@type": "DeliveryMethod",
        "name": "Delivery"
    }
    <?php if (!empty($negocio_data['rating'])): ?>
    ,"aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "<?php echo $negocio_data['rating']; ?>",
        "reviewCount": "<?php echo $negocio_data['reviews_count'] ?? 0; ?>"
    }
    <?php endif; ?>
}
</script>
<?php endif; ?>
