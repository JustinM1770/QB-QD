<?php
/**
 * Orez Floristería - Lógica especializada para productos de flores
 * Incluye: selector de diseños, variantes de tamaño, complementos, formato WhatsApp
 */

// ID del negocio Orez Floristería
define('OREZ_NEGOCIO_ID', 9);

/**
 * Obtener diseños de rosas disponibles
 */
function getDiseniosRosas() {
    $disenos = [];
    $path = 'assets/img/productos/OREZ/ROSAS/';
    for ($i = 1; $i <= 12; $i++) {
        $disenos[] = [
            'id' => $i,
            'nombre' => 'Diseño ' . $i,
            'imagen' => $path . 'rosas' . $i . '.jpeg'
        ];
    }
    return $disenos;
}

/**
 * Obtener variantes de tamaño para ramos de rosas
 */
function getVariantesRosas() {
    return [
        ['cantidad' => 12, 'precio' => 449, 'label' => '12 Rosas'],
        ['cantidad' => 25, 'precio' => 875, 'label' => '25 Rosas'],
        ['cantidad' => 50, 'precio' => 1719, 'label' => '50 Rosas'],
        ['cantidad' => 75, 'precio' => 2399, 'label' => '75 Rosas'],
        ['cantidad' => 100, 'precio' => 3199, 'label' => '100 Rosas'],
        ['cantidad' => 150, 'precio' => 4599, 'label' => '150 Rosas'],
        ['cantidad' => 200, 'precio' => 5999, 'label' => '200 Rosas']
    ];
}

/**
 * Obtener complementos/extras para flores
 */
function getComplementosFlores($pdo, $id_negocio = OREZ_NEGOCIO_ID) {
    // Buscar productos de la categoría "Extras para Ramos"
    $stmt = $pdo->prepare("
        SELECT p.id_producto, p.nombre, p.precio, p.imagen, p.descripcion
        FROM productos p
        JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
        WHERE p.id_negocio = ?
        AND cp.nombre LIKE '%Extras%'
        AND p.disponible = 1
        ORDER BY p.precio ASC
    ");
    $stmt->execute([$id_negocio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Identificar productos que son variantes (Mitad/Doble)
 * y devolver el producto principal con sus variantes.
 * Las variantes se ocultan del catálogo y se muestran en el modal del producto principal.
 */
function consolidarProductosMitad($productos) {
    $consolidados = [];
    $variantes_procesadas = [];

    foreach ($productos as $producto) {
        $nombre = $producto['nombre'];

        // Detectar si es una variante (- Mitad, - Doble, Mitad, Doble al final)
        $es_mitad = preg_match('/\s*-?\s*Mitad\s*$/i', $nombre);
        $es_doble = preg_match('/\s*-?\s*Doble\s*$/i', $nombre);

        if ($es_mitad || $es_doble) {
            // Extraer nombre base (quitar sufijo Mitad/Doble)
            $nombre_base = preg_replace('/\s*-?\s*(Mitad|Doble)\s*(Ramo)?\s*$/i', '', $nombre);
            $nombre_base = trim($nombre_base);

            if (!isset($variantes_procesadas[$nombre_base])) {
                $variantes_procesadas[$nombre_base] = [
                    'variantes' => []
                ];
            }

            $variantes_procesadas[$nombre_base]['variantes'][] = [
                'tipo' => $es_mitad ? 'mitad' : 'doble',
                'id_producto' => $producto['id_producto'],
                'nombre' => $producto['nombre'],
                'precio' => floatval($producto['precio']),
                'imagen' => $producto['imagen']
            ];
        } else {
            // Es un producto principal
            $nombre_base = $nombre;

            if (!isset($variantes_procesadas[$nombre_base])) {
                $variantes_procesadas[$nombre_base] = [
                    'principal' => $producto,
                    'variantes' => []
                ];
            } else {
                $variantes_procesadas[$nombre_base]['principal'] = $producto;
            }
        }
    }

    // Consolidar: mostrar solo productos principales con sus variantes
    foreach ($variantes_procesadas as $nombre_base => $data) {
        if (isset($data['principal'])) {
            $producto = $data['principal'];
            $producto['tiene_variantes_tamano'] = !empty($data['variantes']);
            $producto['variantes_tamano'] = $data['variantes'];
            // Agregar opción "Completo" que es el producto principal
            if (!empty($data['variantes'])) {
                array_unshift($producto['variantes_tamano'], [
                    'tipo' => 'completo',
                    'id_producto' => $producto['id_producto'],
                    'nombre' => $producto['nombre'],
                    'precio' => floatval($producto['precio']),
                    'imagen' => $producto['imagen']
                ]);
            }
            $consolidados[] = $producto;
        } elseif (!empty($data['variantes'])) {
            // Si solo hay variantes sin principal, usar la de mayor precio como principal
            usort($data['variantes'], function($a, $b) {
                return $b['precio'] - $a['precio'];
            });
            $principal = $data['variantes'][0];
            $producto = [
                'id_producto' => $principal['id_producto'],
                'nombre' => $nombre_base,
                'precio' => $principal['precio'],
                'imagen' => $principal['imagen'],
                'tiene_variantes_tamano' => count($data['variantes']) > 1,
                'variantes_tamano' => $data['variantes']
            ];
            $consolidados[] = $producto;
        }
    }

    return $consolidados;
}

/**
 * Filtrar productos de categorías que no deben mostrarse en el catálogo principal
 * (Ej: "Extras para Ramos" solo se muestran como complementos en el modal)
 */
function filtrarCategoriasOcultas($productos, $categorias_ocultas = ['Extras para Ramos']) {
    return array_filter($productos, function($p) use ($categorias_ocultas) {
        $categoria = $p['categoria'] ?? $p['categoria_nombre'] ?? '';
        foreach ($categorias_ocultas as $cat_oculta) {
            if (stripos($categoria, $cat_oculta) !== false) {
                return false;
            }
        }
        return true;
    });
}

/**
 * Obtener datos completos de variantes para JavaScript
 */
function getVariantesProductoOrez($pdo, $id_negocio = OREZ_NEGOCIO_ID) {
    // Obtener todos los productos con sus categorías
    $stmt = $pdo->prepare("
        SELECT p.id_producto, p.nombre, p.precio, p.imagen, p.descripcion,
               cp.nombre as categoria
        FROM productos p
        JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
        WHERE p.id_negocio = ?
        AND p.disponible = 1
        ORDER BY cp.nombre, p.nombre
    ");
    $stmt->execute([$id_negocio]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear mapa de variantes por nombre base
    $variantes_map = [];

    foreach ($productos as $producto) {
        $nombre = $producto['nombre'];
        $es_mitad = preg_match('/\s*-?\s*Mitad\s*$/i', $nombre);
        $es_doble = preg_match('/\s*-?\s*Doble\s*$/i', $nombre);

        if ($es_mitad || $es_doble) {
            $nombre_base = preg_replace('/\s*-?\s*(Mitad|Doble)\s*(Ramo)?\s*$/i', '', $nombre);
            $nombre_base = trim($nombre_base);

            if (!isset($variantes_map[$nombre_base])) {
                $variantes_map[$nombre_base] = [];
            }

            $variantes_map[$nombre_base][] = [
                'tipo' => $es_mitad ? 'mitad' : 'doble',
                'id' => intval($producto['id_producto']),
                'nombre' => $producto['nombre'],
                'precio' => floatval($producto['precio'])
            ];
        }
    }

    return $variantes_map;
}

/**
 * Formatear mensaje de WhatsApp para pedido de flores
 */
function formatearMensajeWhatsAppFlores($pedido) {
    $mensaje = "🌹 *NUEVO PEDIDO - OREZ FLORISTERÍA*\n\n";
    $mensaje .= "📦 *PRODUCTO:* " . ($pedido['producto'] ?? 'N/A') . "\n";

    if (!empty($pedido['diseno'])) {
        $mensaje .= "🎨 *DISEÑO:* " . $pedido['diseno'] . "\n";
    }

    if (!empty($pedido['tamano'])) {
        $mensaje .= "📏 *TAMAÑO:* " . $pedido['tamano'] . "\n";
    }

    if (!empty($pedido['extras']) && is_array($pedido['extras'])) {
        $mensaje .= "✨ *EXTRAS:* " . implode(', ', $pedido['extras']) . "\n";
    }

    if (!empty($pedido['mensaje_tarjeta'])) {
        $mensaje .= "💌 *MENSAJE TARJETA:* " . $pedido['mensaje_tarjeta'] . "\n";
    }

    $mensaje .= "\n💰 *TOTAL:* $" . number_format($pedido['total'] ?? 0, 2) . "\n";

    if (!empty($pedido['cliente'])) {
        $mensaje .= "\n👤 *CLIENTE:* " . $pedido['cliente'] . "\n";
    }

    if (!empty($pedido['telefono'])) {
        $mensaje .= "📱 *TELÉFONO:* " . $pedido['telefono'] . "\n";
    }

    if (!empty($pedido['direccion'])) {
        $mensaje .= "📍 *DIRECCIÓN:* " . $pedido['direccion'] . "\n";
    }

    return $mensaje;
}

/**
 * Calcular costo de envío por zona para Orez Floristería
 * Zonas:
 *   - Teocaltiche, Jalisco: $70
 *   - Rancherías (Mechoacanejo, etc.): $180
 *   - Villa Hidalgo, Jalisco: $350
 */
function calcularEnvioOrez($ciudad, $colonia = '') {
    $ciudad = mb_strtolower(trim($ciudad), 'UTF-8');
    $colonia = mb_strtolower(trim($colonia), 'UTF-8');

    // Rancherías / localidades rurales de Teocaltiche
    $rancherias = [
        'mechoacanejo', 'belén del refugio', 'belen del refugio',
        'el salitre', 'santa maría del valle', 'santa maria del valle',
        'cerro gordo', 'el puesto', 'la labor', 'el carmen',
        'el naranjito', 'agua gorda', 'los dolores',
        'el refugio', 'piedra gorda', 'la cueva',
    ];

    // Verificar rancherías primero (pueden estar en ciudad o colonia)
    foreach ($rancherias as $rancheria) {
        if (strpos($ciudad, $rancheria) !== false || strpos($colonia, $rancheria) !== false) {
            return 180.00;
        }
    }

    // Villa Hidalgo
    if (strpos($ciudad, 'villa hidalgo') !== false) {
        return 350.00;
    }

    // Teocaltiche (zona urbana)
    if (strpos($ciudad, 'teocaltiche') !== false) {
        return 70.00;
    }

    // Ubicación no reconocida: usar tarifa más alta por seguridad
    return 350.00;
}

/**
 * Verificar si un producto es "Ramo de Rosas" dinámico
 */
function esRamoDinamico($producto) {
    $nombre = strtolower($producto['nombre'] ?? '');
    return (
        strpos($nombre, 'ramo de') !== false &&
        strpos($nombre, 'rosas') !== false &&
        !strpos($nombre, 'tulipanes') &&
        !strpos($nombre, 'gerberas')
    );
}
