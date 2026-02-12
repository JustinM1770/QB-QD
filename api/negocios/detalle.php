<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de negocio requerido']);
    exit;
}

try {
    // Negocio
    $stmt = $pdo->prepare("SELECT n.id, n.nombre, n.descripcion, n.direccion, n.telefono, n.logo, n.portada,
                                   c.nombre as categoria, n.id_categoria, n.calificacion,
                                   COALESCE(n.total_resenas, 0) as total_resenas,
                                   COALESCE(n.tiempo_entrega, '30-45 min') as tiempo_entrega,
                                   COALESCE(n.costo_envio, 0) as costo_envio,
                                   COALESCE(n.pedido_minimo, 0) as pedido_minimo,
                                   n.abierto
                            FROM negocios n
                            LEFT JOIN categorias c ON n.id_categoria = c.id
                            WHERE n.id = ?");
    $stmt->execute([$id]);
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$negocio) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Negocio no encontrado']);
        exit;
    }

    $negocio['id'] = (int)$negocio['id'];
    $negocio['calificacion'] = (float)$negocio['calificacion'];
    $negocio['total_resenas'] = (int)$negocio['total_resenas'];
    $negocio['costo_envio'] = (float)$negocio['costo_envio'];
    $negocio['pedido_minimo'] = (float)$negocio['pedido_minimo'];
    $negocio['abierto'] = (bool)$negocio['abierto'];

    // Productos agrupados por categoría de menú
    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen,
                                   p.id_negocio, COALESCE(cm.nombre, 'General') as categoria, p.disponible
                            FROM productos p
                            LEFT JOIN categorias_menu cm ON p.id_categoria_menu = cm.id
                            WHERE p.id_negocio = ? AND p.activo = 1
                            ORDER BY cm.orden ASC, p.nombre ASC");
    $stmt->execute([$id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productos as &$p) {
        $p['id'] = (int)$p['id'];
        $p['id_negocio'] = (int)$p['id_negocio'];
        $p['precio'] = (float)$p['precio'];
        $p['disponible'] = (bool)$p['disponible'];
    }

    // Agrupar por categoría
    $categorias = [];
    foreach ($productos as $p) {
        $cat = $p['categoria'];
        if (!isset($categorias[$cat])) {
            $categorias[$cat] = ['id' => count($categorias) + 1, 'nombre' => $cat, 'productos' => []];
        }
        $categorias[$cat]['productos'][] = $p;
    }

    echo json_encode([
        'success' => true,
        'negocio' => $negocio,
        'productos' => $productos,
        'categorias' => array_values($categorias)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
