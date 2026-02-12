<?php
/**
 * API para obtener opciones de personalización de productos
 * 
 * Endpoint: /api/producto_opciones.php?id_producto=XX
 * 
 * Respuesta:
 * {
 *   "success": true,
 *   "producto": { ... },
 *   "permite_personalizacion_unidad": true,
 *   "elegibles": [...],
 *   "grupos_opciones": [...]
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PersonalizacionUnidad.php';

try {
    $id_producto = filter_input(INPUT_GET, 'id_producto', FILTER_VALIDATE_INT);
    
    if (!$id_producto || $id_producto <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener información del producto
    $query = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.imagen,
                     p.tiene_elegibles, p.tiene_opciones_dinamicas, p.permite_personalizacion_unidad,
                     p.permite_mensaje_tarjeta, p.permite_texto_producto, p.limite_texto_producto,
                     n.nombre as negocio_nombre
              FROM productos p
              LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
              WHERE p.id_producto = :id_producto AND p.disponible = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
    $stmt->execute();
    
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        throw new Exception('Producto no encontrado o no disponible');
    }
    
    $personalizacion = new PersonalizacionUnidad($db);
    
    // Obtener elegibles si el producto los tiene
    $elegibles = [];
    if ($producto['tiene_elegibles']) {
        $elegibles = $personalizacion->obtenerElegibles($id_producto);
    }
    
    // Obtener grupos de opciones si el producto las tiene
    $grupos_opciones = [];
    if ($producto['tiene_opciones_dinamicas']) {
        $grupos_opciones = $personalizacion->obtenerOpciones($id_producto);
    }
    
    // Respuesta
    echo json_encode([
        'success' => true,
        'producto' => [
            'id_producto' => (int)$producto['id_producto'],
            'nombre' => $producto['nombre'],
            'descripcion' => $producto['descripcion'],
            'precio' => (float)$producto['precio'],
            'imagen' => $producto['imagen'],
            'negocio' => $producto['negocio_nombre'],
            'permite_mensaje_tarjeta' => (bool)($producto['permite_mensaje_tarjeta'] ?? false),
            'permite_texto_producto' => (bool)($producto['permite_texto_producto'] ?? false),
            'limite_texto_producto' => (int)($producto['limite_texto_producto'] ?? 50)
        ],
        'permite_personalizacion_unidad' => (bool)$producto['permite_personalizacion_unidad'],
        'tiene_elegibles' => (bool)$producto['tiene_elegibles'],
        'tiene_opciones_dinamicas' => (bool)$producto['tiene_opciones_dinamicas'],
        'elegibles' => $elegibles,
        'grupos_opciones' => $grupos_opciones
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
