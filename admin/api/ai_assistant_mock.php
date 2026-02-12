<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Pedido.php';
require_once __DIR__ . '/../../models/Producto.php';
require_once __DIR__ . '/../../api/ChatService.php';
require_once __DIR__ . '/../../includes/business_helpers.php'; // Helpers de negocio

$response = ['success' => false, 'error' => 'Acción no reconocida'];

/**
 * Verifica si el negocio existe y está activo
 */
function verificarNegocioActivo($db, $negocioId) {
    $stmt = $db->prepare("SELECT id_negocio, nombre, activo FROM negocios WHERE id_negocio = ?");
    $stmt->execute([$negocioId]);
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$negocio) {
        return ['valid' => false, 'error' => 'Negocio no encontrado'];
    }
    
    if (!$negocio['activo']) {
        return ['valid' => false, 'error' => 'El negocio está desactivado'];
    }
    
    return ['valid' => true, 'negocio' => $negocio];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detectar si es una imagen subida (FormData) o JSON
    if (isset($_FILES['image'])) {
        // Redirigir a menu_parser_endpoint.php para análisis de imagen
        // Este endpoint no debería recibir imágenes directamente
        echo json_encode([
            'success' => false, 
            'error' => 'Para análisis de imágenes use menu_parser_endpoint.php',
            'redirect' => '../menu_parser_endpoint.php'
        ]);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $negocioId = $input['negocio_id'] ?? null;

    if (empty($negocioId) && !in_array($action, ['chat', 'parse_menu'])) {
        echo json_encode(['success' => false, 'error' => 'ID de negocio no proporcionado.']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();
    $chatService = new ChatService();

    switch ($action) {
        case 'analyze_sales':
            try {
                $pedido = new Pedido($db);
                $estadisticas = $pedido->obtenerEstadisticasNegocio($negocioId);

                $query_top_productos = "SELECT p.nombre as producto, SUM(dp.cantidad) as cantidad_total, SUM(dp.subtotal) as ingresos_totales, cp.nombre as categoria
                                        FROM detalles_pedido dp
                                        JOIN productos p ON dp.id_producto = p.id_producto
                                        JOIN pedidos ped ON dp.id_pedido = ped.id_pedido
                                        LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                                        WHERE ped.id_negocio = :id_negocio AND ped.id_estado = 6
                                        GROUP BY p.id_producto, p.nombre, cp.nombre
                                        ORDER BY cantidad_total DESC
                                        LIMIT 3";
                
                $stmt_top = $db->prepare($query_top_productos);
                $stmt_top->bindParam(':id_negocio', $negocioId, PDO::PARAM_INT);
                $stmt_top->execute();
                $top_3_productos = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

                $response = [
                    'success' => true,
                    'data' => [
                        'estadisticas' => $estadisticas,
                        'top_3' => $top_3_productos
                    ]
                ];

            } catch (Exception $e) {
                $response = ['success' => false, 'error' => 'Error al analizar ventas: ' . $e->getMessage()];
            }
            break;

        case 'get_recommendations':
            // Obtener datos de ventas para generar recomendaciones
            $pedido = new Pedido($db);
            $estadisticas = $pedido->obtenerEstadisticasNegocio($negocioId);
            // ... (obtener más datos si es necesario) ...

            // Generar recomendaciones usando el ChatService
            $recomendaciones = $chatService->generateRecommendations(['estadisticas' => $estadisticas, 'top_3' => []]); // Simulado por ahora

            $response = [
                'success' => true,
                'data' => [
                    'recomendaciones' => $recomendaciones
                ]
            ];
            break;

        case 'get_insights':
            // Lógica para obtener insights (a implementar con datos reales)
            $response = [
                'success' => true,
                'data' => [
                    'horarios_pico' => [['hora' => '20', 'pedidos' => 50, 'ingresos' => 1000]],
                    'dias_populares' => [['dia' => 'Sábado', 'pedidos' => 100]],
                ]
            ];
            break;
            
        case 'chat':
            $message = $input['message'] ?? '';
            $conversationHistory = $input['history'] ?? [];
            
            // Enviar mensaje al ChatService
            $aiResponse = $chatService->sendMessage($message, $conversationHistory);

            $response = [
                'success' => true,
                'data' => [
                    'response' => $aiResponse
                ]
            ];
            break;
        
        case 'parse_menu':
            // Redirigir a menu_parser_endpoint.php para análisis de imagen
            $response = [
                'success' => false, 
                'error' => 'Use menu_parser_endpoint.php para análisis de imágenes de menú',
                'redirect' => '../menu_parser_endpoint.php'
            ];
            break;

        case 'save_menu':
            // Guardar productos del menú parseado en la base de datos
            $productos = $input['productos'] ?? [];
            
            if (empty($productos)) {
                $response = ['success' => false, 'error' => 'No hay productos para guardar'];
                break;
            }
            
            // Verificar que el negocio exista y esté activo
            $negocioCheck = verificarNegocioActivo($db, $negocioId);
            if (!$negocioCheck['valid']) {
                $response = ['success' => false, 'error' => $negocioCheck['error']];
                break;
            }
            
            // Verificar que el negocio esté abierto (opcional - solo advertencia)
            $negocioAbierto = isBusinessOpen($db, $negocioId);
            $advertencia = '';
            if (!$negocioAbierto) {
                $advertencia = 'Nota: El negocio está actualmente cerrado. Los productos se guardarán pero no serán visibles para clientes hasta que abra.';
            }
            
            try {
                $insertados = 0;
                $actualizados = 0;
                $errores = 0;
                
                // Obtener o crear categorías
                foreach ($productos as $prod) {
                    $categoria = $prod['categoria'] ?? 'Sin categoría';
                    
                    // Buscar o crear categoría
                    $stmt_cat = $db->prepare("SELECT id_categoria FROM categorias_producto WHERE nombre = :nombre AND id_negocio = :negocio LIMIT 1");
                    $stmt_cat->execute([':nombre' => $categoria, ':negocio' => $negocioId]);
                    $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
                    
                    if ($cat_row) {
                        $id_categoria = $cat_row['id_categoria'];
                    } else {
                        // Crear categoría
                        $stmt_new_cat = $db->prepare("INSERT INTO categorias_producto (nombre, id_negocio, activo) VALUES (:nombre, :negocio, 1)");
                        $stmt_new_cat->execute([':nombre' => $categoria, ':negocio' => $negocioId]);
                        $id_categoria = $db->lastInsertId();
                    }
                    
                    // Verificar si el producto ya existe
                    $stmt_check = $db->prepare("SELECT id_producto FROM productos WHERE nombre = :nombre AND id_negocio = :negocio LIMIT 1");
                    $stmt_check->execute([':nombre' => $prod['nombre'], ':negocio' => $negocioId]);
                    
                    if ($stmt_check->fetch()) {
                        // Producto ya existe, actualizar
                        $stmt_update = $db->prepare("
                            UPDATE productos SET 
                                descripcion = :desc,
                                precio = :precio,
                                id_categoria = :cat,
                                imagen = :imagen
                            WHERE nombre = :nombre AND id_negocio = :negocio
                        ");
                        $stmt_update->execute([
                            ':desc' => $prod['descripcion'] ?? '',
                            ':precio' => $prod['precio'] ?? 0,
                            ':cat' => $id_categoria,
                            ':imagen' => $prod['imagen'] ?? null,
                            ':nombre' => $prod['nombre'],
                            ':negocio' => $negocioId
                        ]);
                    } else {
                        // Insertar nuevo producto
                        $stmt_insert = $db->prepare("
                            INSERT INTO productos (nombre, descripcion, precio, id_categoria, id_negocio, imagen, activo, created_at) 
                            VALUES (:nombre, :desc, :precio, :cat, :negocio, :imagen, 1, NOW())
                        ");
                        $result = $stmt_insert->execute([
                            ':nombre' => $prod['nombre'],
                            ':desc' => $prod['descripcion'] ?? '',
                            ':precio' => $prod['precio'] ?? 0,
                            ':cat' => $id_categoria,
                            ':negocio' => $negocioId,
                            ':imagen' => $prod['imagen'] ?? null
                        ]);
                        
                        if ($result) {
                            $insertados++;
                        } else {
                            $errores++;
                        }
                    }
                }
                
                $response = [
                    'success' => true,
                    'data' => [
                        'insertados' => $insertados,
                        'actualizados' => count($productos) - $insertados - $errores,
                        'errores' => $errores,
                        'total' => count($productos),
                        'negocio_abierto' => $negocioAbierto,
                        'advertencia' => $advertencia ?: null
                    ]
                ];
                
            } catch (Exception $e) {
                $response = ['success' => false, 'error' => 'Error al guardar: ' . $e->getMessage()];
            }
            break;

        case 'optimize_menu':
            try {
                // Obtener productos con pocas ventas
                $stmt_low = $db->prepare("
                    SELECT p.nombre, COALESCE(SUM(dp.cantidad), 0) as ventas
                    FROM productos p
                    LEFT JOIN detalles_pedido dp ON p.id_producto = dp.id_producto
                    LEFT JOIN pedidos ped ON dp.id_pedido = ped.id_pedido AND ped.id_estado = 6
                    WHERE p.id_negocio = :negocio AND p.activo = 1
                    GROUP BY p.id_producto, p.nombre
                    ORDER BY ventas ASC
                    LIMIT 5
                ");
                $stmt_low->execute([':negocio' => $negocioId]);
                $low_sellers = $stmt_low->fetchAll(PDO::FETCH_COLUMN);
                
                // Obtener productos más vendidos
                $stmt_high = $db->prepare("
                    SELECT p.nombre
                    FROM productos p
                    JOIN detalles_pedido dp ON p.id_producto = dp.id_producto
                    JOIN pedidos ped ON dp.id_pedido = ped.id_pedido
                    WHERE p.id_negocio = :negocio AND ped.id_estado = 6 AND p.activo = 1
                    GROUP BY p.id_producto, p.nombre
                    ORDER BY SUM(dp.cantidad) DESC
                    LIMIT 5
                ");
                $stmt_high->execute([':negocio' => $negocioId]);
                $best_sellers = $stmt_high->fetchAll(PDO::FETCH_COLUMN);
                
                $response = [
                    'success' => true,
                    'data' => [
                        'eliminar' => array_filter($low_sellers),
                        'destacar' => $best_sellers,
                        'ajustar_precio' => [],
                        'nuevos_productos' => [
                            'Combo del día',
                            'Menú ejecutivo',
                            'Postre especial'
                        ]
                    ]
                ];
            } catch (Exception $e) {
                $response = ['success' => false, 'error' => 'Error al optimizar: ' . $e->getMessage()];
            }
            break;

        default:
            $response = ['success' => false, 'error' => 'Acción no definida.'];
            break;
    }
}

echo json_encode($response);
?>