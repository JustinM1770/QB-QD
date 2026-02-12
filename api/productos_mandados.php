<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Producto.php';

// Obtener latitud y longitud del usuario desde GET, o valores por defecto
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 21.8823; // Ejemplo: Aguascalientes centro
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : -102.2826;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$premium = isset($_GET['premium']) ? boolval($_GET['premium']) : false;

$database = new Database();
$db = $database->getConnection();

$negocioModel = new Negocio($db);
$productoModel = new Producto($db);

// Obtener negocios cercanos
$negocios = $negocioModel->obtenerCercanos($lat, $lng, 10);

$result = [];

foreach ($negocios as $negocio) {
    // Si se filtra por premium y el negocio no es premium, saltar
    if ($premium && empty($negocio['premium'])) {
        continue;
    }

    if ($q !== '') {
        // Buscar productos que coincidan con el término en el negocio
        $productos = $productoModel->buscar($negocio['id_negocio'], $q);
    } else {
        // Obtener todos los productos del negocio
        $productos = $productoModel->obtenerPorNegocio($negocio['id_negocio']);
    }

    // Filtrar productos disponibles
    $productos_mapeados = [];
    foreach ($productos as $producto) {
        if ($producto['disponible']) {
            $productos_mapeados[] = [
                'id' => $producto['id_producto'],
                'name' => $producto['nombre'],
                'price' => floatval($producto['precio']),
                'icon' => $producto['imagen'] ? "<img src='/uploads/productos/{$producto['imagen']}' alt='{$producto['nombre']}' style='width:24px;height:24px;' />" : '📦',
                'category' => $producto['nombre_categoria']
            ];
        }
    }

    // Solo incluir negocios con productos disponibles
    if (count($productos_mapeados) > 0) {
        $result[] = [
            'id_negocio' => $negocio['id_negocio'],
            'nombre' => $negocio['nombre'],
            'logo' => $negocio['logo'],
            'tiempo_preparacion_promedio' => $negocio['tiempo_preparacion_promedio'],
            'costo_envio' => $negocio['costo_envio'],
            'rating' => floatval($negocio['rating']),
            'productos' => $productos_mapeados,
            // Campos para negocio principal y secundario (placeholder)
            'negocio_principal' => true,
            'negocio_secundario' => false
        ];
    }
}

echo json_encode($result);
?>
