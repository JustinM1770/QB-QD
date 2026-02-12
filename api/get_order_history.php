<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

try {
    // Incluir archivos necesarios
    require_once '../config/database.php';

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $id_usuario = $_SESSION['id_usuario'];
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

    // Query mejorada con LEFT JOIN para manejar casos donde el negocio no existe
    $query = "SELECT p.id_pedido, p.fecha_creacion, COALESCE(n.nombre, 'Negocio no disponible') as negocio_nombre, 
                     p.total, p.estado, p.id_negocio, n.imagen as negocio_imagen
              FROM pedidos p
              LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
              WHERE p.id_usuario = :id_usuario
              ORDER BY p.fecha_creacion DESC
              LIMIT :limit";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $historial = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Formatear fecha
        $fechaFormateada = date('d/m/Y H:i', strtotime($row['fecha_creacion']));
        
        // Traducir estado
        $estadoTexto = traducirEstado($row['estado']);
        
        $historial[] = [
            'id' => $row['id_pedido'],
            'fecha' => $row['fecha_creacion'],
            'fecha_formateada' => $fechaFormateada,
            'negocio' => $row['negocio_nombre'],
            'total' => floatval($row['total']),
            'total_formateado' => '$' . number_format($row['total'], 2),
            'estado' => $row['estado'],
            'estado_texto' => $estadoTexto,
            'id_negocio' => $row['id_negocio'],
            'negocio_imagen' => $row['negocio_imagen']
        ];
    }

    echo json_encode([
        'success' => true, 
        'historial' => $historial,
        'total' => count($historial)
    ]);

} catch (Exception $e) {
    error_log("Error en get_order_history.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}

function traducirEstado($estado) {
    $estados = [
        'pendiente' => 'Pendiente',
        'confirmado' => 'Confirmado',
        'preparando' => 'Preparando',
        'listo' => 'Listo',
        'en_camino' => 'En camino',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado'
    ];
    
    return $estados[$estado] ?? ucfirst($estado);
}
?>
