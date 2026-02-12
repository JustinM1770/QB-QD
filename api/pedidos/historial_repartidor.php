<?php
/**
 * API: Obtener historial de pedidos entregados por un repartidor
 * GET /api/pedidos/historial_repartidor.php?repartidor_id=X&limit=20
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!isset($_GET['repartidor_id'])) {
        throw new Exception('Falta repartidor_id');
    }

    $repartidor_id = $_GET['repartidor_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    $query = "SELECT
                p.id_pedido as id,
                p.id_usuario as usuario_id,
                p.id_negocio as negocio_id,
                p.monto_total as total,
                p.costo_envio,
                p.fecha_creacion as fecha_pedido,
                p.fecha_entrega,
                e.nombre as estado,
                d.calle,
                d.numero as numero_calle,
                d.colonia,
                d.ciudad,
                CONCAT(d.calle, ' ', d.numero, ', ', d.colonia, ', ', d.ciudad) as direccion_entrega,
                n.nombre as negocio_nombre,
                CONCAT(u.nombre, ' ', u.apellido) as cliente_nombre
              FROM pedidos p
              INNER JOIN negocios n ON p.id_negocio = n.id_negocio
              INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
              INNER JOIN estados_pedido e ON p.id_estado = e.id_estado
              INNER JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
              WHERE p.id_repartidor = ?
              AND p.id_estado = 6
              AND p.tipo_pedido = 'delivery'
              ORDER BY p.fecha_entrega DESC
              LIMIT ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$repartidor_id, $limit]);

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas
    $statsQuery = "SELECT
                    COUNT(*) as total_entregas,
                    SUM(monto_total) as ganancias_total,
                    AVG(monto_total) as promedio_pedido
                   FROM pedidos
                   WHERE id_repartidor = ?
                   AND id_estado = 6
                   AND tipo_pedido = 'delivery'";

    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$repartidor_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'total' => count($pedidos),
        'estadisticas' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
