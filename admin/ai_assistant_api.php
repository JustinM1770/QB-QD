<?php
/**
 * API del Asistente IA con Gemini
 * Análisis de ventas, recomendaciones y gestión de menú
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/gemini_menu_parser.php';

// API Key de Gemini
$GEMINI_API_KEY = getenv('GEMINI_API_KEY') ?: '';

// Obtener datos del request
$requestMethod = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($requestMethod === 'POST') {
    $action = $input['action'] ?? '';
    $negocioId = $input['negocio_id'] ?? null;
    
    if (!$negocioId) {
        echo json_encode(['success' => false, 'error' => 'negocio_id requerido']);
        exit;
    }
    
    switch ($action) {
        case 'analyze_sales':
            analyzeSales($negocioId);
            break;
            
        case 'get_recommendations':
            getRecommendations($negocioId);
            break;
            
        case 'chat':
            processChat($negocioId, $input['message'] ?? '');
            break;
            
        case 'get_insights':
            getBusinessInsights($negocioId);
            break;
            
        case 'optimize_menu':
            optimizeMenu($negocioId);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

/**
 * Analiza las ventas del negocio
 */
function analyzeSales($negocioId) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener datos de ventas de los últimos 30 días
    $query = "
        SELECT 
            p.nombre as producto,
            p.precio,
            p.categoria_id,
            c.nombre as categoria,
            COUNT(dp.id) as ventas,
            SUM(dp.cantidad) as cantidad_total,
            SUM(dp.subtotal) as ingresos_totales,
            AVG(dp.subtotal) as ticket_promedio
        FROM productos p
        LEFT JOIN detalle_pedidos dp ON p.id = dp.producto_id
        LEFT JOIN pedidos pe ON dp.pedido_id = pe.id
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.negocio_id = :negocio_id
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND pe.estado_actual NOT IN ('cancelado', 'rechazado')
        GROUP BY p.id
        ORDER BY cantidad_total DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':negocio_id', $negocioId);
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas generales
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT pe.id) as total_pedidos,
            COUNT(DISTINCT pe.usuario_id) as clientes_unicos,
            SUM(pe.total) as ingresos_totales,
            AVG(pe.total) as ticket_promedio,
            MAX(pe.total) as ticket_maximo,
            MIN(pe.total) as ticket_minimo
        FROM pedidos pe
        WHERE pe.negocio_id = :negocio_id
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND pe.estado_actual NOT IN ('cancelado', 'rechazado')
    ";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':negocio_id', $negocioId);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Análisis por categoría
    $categoryQuery = "
        SELECT 
            c.nombre as categoria,
            COUNT(dp.id) as ventas,
            SUM(dp.subtotal) as ingresos
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id
        LEFT JOIN detalle_pedidos dp ON p.id = dp.producto_id
        LEFT JOIN pedidos pe ON dp.pedido_id = pe.id
        WHERE c.negocio_id = :negocio_id
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND pe.estado_actual NOT IN ('cancelado', 'rechazado')
        GROUP BY c.id
        ORDER BY ingresos DESC
    ";
    
    $categoryStmt = $db->prepare($categoryQuery);
    $categoryStmt->bindParam(':negocio_id', $negocioId);
    $categoryStmt->execute();
    $categorias = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'productos' => $productos,
            'estadisticas' => $stats,
            'categorias' => $categorias,
            'top_3' => array_slice($productos, 0, 3),
            'periodo' => 'Últimos 30 días'
        ]
    ]);
}

/**
 * Genera recomendaciones usando IA
 */
function getRecommendations($negocioId) {
    global $GEMINI_API_KEY;
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener datos del negocio
    $query = "SELECT nombre, tipo_cocina, descripcion FROM negocios WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $negocioId);
    $stmt->execute();
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener productos con bajo rendimiento
    $productsQuery = "
        SELECT 
            p.nombre,
            p.precio,
            p.descripcion,
            c.nombre as categoria,
            COALESCE(COUNT(dp.id), 0) as ventas
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN detalle_pedidos dp ON p.id = dp.producto_id
        LEFT JOIN pedidos pe ON dp.pedido_id = pe.id 
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE p.negocio_id = :negocio_id
        GROUP BY p.id
        ORDER BY ventas ASC
        LIMIT 10
    ";
    
    $productsStmt = $db->prepare($productsQuery);
    $productsStmt->bindParam(':negocio_id', $negocioId);
    $productsStmt->execute();
    $productosPocoVendidos = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar contexto para Gemini
    $context = [
        'negocio' => $negocio,
        'productos_bajo_rendimiento' => $productosPocoVendidos,
        'fecha_analisis' => date('Y-m-d')
    ];
    
    $prompt = "Eres un consultor experto en restaurantes. Analiza estos datos y proporciona 5 recomendaciones específicas para aumentar las ventas:\n\n" .
              "Negocio: {$negocio['nombre']}\n" .
              "Tipo: {$negocio['tipo_cocina']}\n\n" .
              "Productos con pocas ventas (últimos 30 días):\n";
    
    foreach ($productosPocoVendidos as $prod) {
        $prompt .= "- {$prod['nombre']} ({$prod['categoria']}): {$prod['ventas']} ventas - \${$prod['precio']}\n";
    }
    
    $prompt .= "\n\nProporciona recomendaciones ESPECÍFICAS y ACCIONABLES en formato JSON:\n" .
               "{\n" .
               '  "recomendaciones": [' . "\n" .
               '    {' . "\n" .
               '      "titulo": "Título de la recomendación",' . "\n" .
               '      "descripcion": "Descripción detallada",' . "\n" .
               '      "impacto": "alto|medio|bajo",' . "\n" .
               '      "categoria": "menu|marketing|precios|operaciones"' . "\n" .
               '    }' . "\n" .
               '  ]' . "\n" .
               '}';
    
    $recommendations = callGeminiAPI($prompt, $GEMINI_API_KEY);
    
    echo json_encode([
        'success' => true,
        'data' => $recommendations,
        'context' => $context
    ]);
}

/**
 * Procesa mensajes de chat con contexto
 */
function processChat($negocioId, $message) {
    global $GEMINI_API_KEY;
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener contexto del negocio
    $negocioQuery = "SELECT * FROM negocios WHERE id = :id";
    $negocioStmt = $db->prepare($negocioQuery);
    $negocioStmt->bindParam(':id', $negocioId);
    $negocioStmt->execute();
    $negocio = $negocioStmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas de productos
    $statsQuery = "
        SELECT 
            p.nombre,
            p.precio,
            p.descripcion,
            c.nombre as categoria,
            COUNT(dp.id) as ventas,
            SUM(dp.subtotal) as ingresos
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN detalle_pedidos dp ON p.id = dp.producto_id
        LEFT JOIN pedidos pe ON dp.pedido_id = pe.id
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE p.negocio_id = :negocio_id
        GROUP BY p.id
        ORDER BY ventas DESC
    ";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':negocio_id', $negocioId);
    $statsStmt->execute();
    $productos = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Construir prompt con contexto
    $contextPrompt = "Eres un asistente IA experto en negocios de comida. Tienes acceso a los siguientes datos:\n\n";
    $contextPrompt .= "NEGOCIO:\n";
    $contextPrompt .= "- Nombre: {$negocio['nombre']}\n";
    $contextPrompt .= "- Tipo: {$negocio['tipo_cocina']}\n\n";
    
    if (!empty($productos)) {
        $contextPrompt .= "PRODUCTOS (últimos 30 días):\n";
        $top5 = array_slice($productos, 0, 5);
        foreach ($top5 as $p) {
            $contextPrompt .= "- {$p['nombre']}: {$p['ventas']} ventas, \${$p['ingresos']} ingresos\n";
        }
        $contextPrompt .= "\n";
    }
    
    $contextPrompt .= "PREGUNTA DEL USUARIO: {$message}\n\n";
    $contextPrompt .= "Responde de manera conversacional, útil y con datos específicos cuando sea posible. Si mencionas cifras, usa los datos reales proporcionados.";
    
    $response = callGeminiAPI($contextPrompt, $GEMINI_API_KEY, false);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'response' => $response,
            'context_used' => !empty($productos)
        ]
    ]);
}

/**
 * Obtiene insights del negocio
 */
function getBusinessInsights($negocioId) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Horarios de mayor demanda
    $horariosQuery = "
        SELECT 
            HOUR(fecha_pedido) as hora,
            COUNT(*) as pedidos,
            SUM(total) as ingresos
        FROM pedidos
        WHERE negocio_id = :negocio_id
            AND fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND estado_actual NOT IN ('cancelado', 'rechazado')
        GROUP BY HOUR(fecha_pedido)
        ORDER BY pedidos DESC
        LIMIT 5
    ";
    
    $horariosStmt = $db->prepare($horariosQuery);
    $horariosStmt->bindParam(':negocio_id', $negocioId);
    $horariosStmt->execute();
    $horarios = $horariosStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Días de mayor demanda
    $diasQuery = "
        SELECT 
            DAYNAME(fecha_pedido) as dia,
            COUNT(*) as pedidos,
            SUM(total) as ingresos
        FROM pedidos
        WHERE negocio_id = :negocio_id
            AND fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND estado_actual NOT IN ('cancelado', 'rechazado')
        GROUP BY DAYOFWEEK(fecha_pedido)
        ORDER BY pedidos DESC
    ";
    
    $diasStmt = $db->prepare($diasQuery);
    $diasStmt->bindParam(':negocio_id', $negocioId);
    $diasStmt->execute();
    $dias = $diasStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Productos que se compran juntos
    $combosQuery = "
        SELECT 
            p1.nombre as producto1,
            p2.nombre as producto2,
            COUNT(*) as veces
        FROM detalle_pedidos dp1
        JOIN detalle_pedidos dp2 ON dp1.pedido_id = dp2.pedido_id AND dp1.id < dp2.id
        JOIN productos p1 ON dp1.producto_id = p1.id
        JOIN productos p2 ON dp2.producto_id = p2.id
        JOIN pedidos pe ON dp1.pedido_id = pe.id
        WHERE p1.negocio_id = :negocio_id
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p1.id, p2.id
        ORDER BY veces DESC
        LIMIT 10
    ";
    
    $combosStmt = $db->prepare($combosQuery);
    $combosStmt->bindParam(':negocio_id', $negocioId);
    $combosStmt->execute();
    $combos = $combosStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tasa de retención de clientes
    $retencionQuery = "
        SELECT 
            COUNT(DISTINCT CASE WHEN pedidos >= 2 THEN usuario_id END) as clientes_recurrentes,
            COUNT(DISTINCT usuario_id) as clientes_totales,
            ROUND((COUNT(DISTINCT CASE WHEN pedidos >= 2 THEN usuario_id END) / COUNT(DISTINCT usuario_id)) * 100, 2) as tasa_retencion
        FROM (
            SELECT usuario_id, COUNT(*) as pedidos
            FROM pedidos
            WHERE negocio_id = :negocio_id
                AND fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND estado_actual NOT IN ('cancelado', 'rechazado')
            GROUP BY usuario_id
        ) as subquery
    ";
    
    $retencionStmt = $db->prepare($retencionQuery);
    $retencionStmt->bindParam(':negocio_id', $negocioId);
    $retencionStmt->execute();
    $retencion = $retencionStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'horarios_pico' => $horarios,
            'dias_populares' => $dias,
            'combos_frecuentes' => $combos,
            'retencion_clientes' => $retencion
        ]
    ]);
}

/**
 * Optimiza el menú usando IA
 */
function optimizeMenu($negocioId) {
    global $GEMINI_API_KEY;
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener todos los productos con sus métricas
    $query = "
        SELECT 
            p.id,
            p.nombre,
            p.precio,
            p.descripcion,
            c.nombre as categoria,
            COUNT(dp.id) as ventas,
            SUM(dp.subtotal) as ingresos,
            p.costo_preparacion
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN detalle_pedidos dp ON p.id = dp.producto_id
        LEFT JOIN pedidos pe ON dp.pedido_id = pe.id
            AND pe.fecha_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE p.negocio_id = :negocio_id
        GROUP BY p.id
        ORDER BY ventas DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':negocio_id', $negocioId);
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $prompt = "Analiza este menú y proporciona recomendaciones de optimización:\n\n";
    
    foreach ($productos as $p) {
        $margen = $p['costo_preparacion'] ? (($p['precio'] - $p['costo_preparacion']) / $p['precio'] * 100) : 0;
        $prompt .= "- {$p['nombre']} ({$p['categoria']}): \${$p['precio']}, {$p['ventas']} ventas, Margen: {$margen}%\n";
    }
    
    $prompt .= "\n\nProporciona en formato JSON:\n" .
               "{\n" .
               '  "eliminar": ["producto1", "producto2"],' . "\n" .
               '  "destacar": ["producto3", "producto4"],' . "\n" .
               '  "ajustar_precio": [{"producto": "nombre", "precio_sugerido": 100, "razon": "explicación"}],' . "\n" .
               '  "nuevos_productos": ["sugerencia1", "sugerencia2"]' . "\n" .
               '}';
    
    $optimization = callGeminiAPI($prompt, $GEMINI_API_KEY);
    
    echo json_encode([
        'success' => true,
        'data' => $optimization,
        'productos_analizados' => count($productos)
    ]);
}

/**
 * Llama a la API de Gemini
 */
function callGeminiAPI($prompt, $apiKey, $expectJSON = true) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$apiKey}";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048,
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => "Error HTTP {$httpCode}"];
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return ['error' => 'Respuesta inválida de Gemini'];
    }
    
    $textResponse = $result['candidates'][0]['content']['parts'][0]['text'];
    
    if ($expectJSON) {
        // Extraer JSON del texto
        if (preg_match('/\{[\s\S]*\}/', $textResponse, $matches)) {
            return json_decode($matches[0], true);
        }
        return ['error' => 'No se pudo extraer JSON de la respuesta'];
    }
    
    return $textResponse;
}
