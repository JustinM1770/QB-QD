<?php
/**
 * Obtiene menú desde base de datos
 */

// Cargar configuración centralizada
require_once __DIR__ . '/../config/error_handler.php';
require_once __DIR__ . '/../config/env.php';

header('Content-Type: application/json');

if (!isset($_GET['negocio_id'])) {
    echo json_encode(['success' => false, 'error' => 'Falta negocio_id']);
    exit;
}

$negocioId = (int)$_GET['negocio_id'];

try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener categorías
    $stmtCat = $pdo->prepare("SELECT * FROM categorias_producto WHERE id_negocio = ? ORDER BY nombre");
    $stmtCat->execute([$negocioId]);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener productos
    $stmtProd = $pdo->prepare("
        SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria 
        WHERE p.id_negocio = ? 
        ORDER BY c.nombre, p.nombre
    ");
    $stmtProd->execute([$negocioId]);
    $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear productos
    $productosFormateados = array_map(function($prod) {
        return [
            'id' => $prod['id_producto'],
            'nombre' => $prod['nombre'],
            'categoria' => $prod['categoria_nombre'] ?? 'Sin categoría',
            'precio' => (float)$prod['precio'],
            'descripcion' => $prod['descripcion'] ?? '',
            'calorias' => $prod['calorias'] ? (int)$prod['calorias'] : null,
            'disponible' => (bool)$prod['disponible']
        ];
    }, $productos);
    
    // Formatear categorías
    $categoriasFormateadas = array_map(function($cat) {
        return [
            'id' => $cat['id_categoria'],
            'nombre' => $cat['nombre'],
            'descripcion' => $cat['descripcion'] ?? ''
        ];
    }, $categorias);
    
    echo json_encode([
        'success' => true,
        'menu' => [
            'categorias' => $categoriasFormateadas,
            'productos' => $productosFormateados
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;