<?php
/**
 * API: Obtener pedidos disponibles para repartidores
 * GET /api/pedidos/disponibles.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener pedidos que están listos para ser recogidos y no tienen repartidor asignado
    $query = "SELECT
                p.id_pedido as id,
                p.id_usuario as usuario_id,
                p.id_negocio as negocio_id,
                p.monto_total as total,
                p.costo_envio,
                p.instrucciones_especiales as instrucciones_entrega,
                p.metodo_pago,
                p.fecha_creacion as fecha_pedido,
                e.nombre as estado,
                d.calle,
                d.numero as numero_calle,
                d.colonia,
                d.ciudad,
                d.estado as estado_direccion,
                d.latitud as latitud_entrega,
                d.longitud as longitud_entrega,
                d.referencias,
                CONCAT(d.calle, ' ', d.numero, ', ', d.colonia, ', ', d.ciudad) as direccion_entrega,
                n.nombre as negocio_nombre,
                CONCAT(n.calle, ' ', n.numero, ', ', n.colonia, ', ', n.ciudad) as negocio_direccion,
                n.latitud as negocio_latitud,
                n.longitud as negocio_longitud,
                n.telefono as negocio_telefono,
                CONCAT(u.nombre, ' ', u.apellido) as cliente_nombre,
                u.telefono as cliente_telefono
              FROM pedidos p
              INNER JOIN negocios n ON p.id_negocio = n.id_negocio
              INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
              INNER JOIN estados_pedido e ON p.id_estado = e.id_estado
              INNER JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
              WHERE p.id_estado IN (3, 4)
              AND (p.id_repartidor IS NULL OR p.id_repartidor = 0)
              AND p.tipo_pedido = 'delivery'
              ORDER BY p.fecha_creacion ASC
              LIMIT 50";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $pedidos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Obtener detalles del pedido
        $detallesQuery = "SELECT
                            dp.id_detalle_pedido as id,
                            dp.id_producto as producto_id,
                            dp.cantidad,
                            dp.precio_unitario,
                            dp.precio_total as subtotal,
                            pr.nombre as producto_nombre
                          FROM detalles_pedido dp
                          INNER JOIN productos pr ON dp.id_producto = pr.id_producto
                          WHERE dp.id_pedido = ?";

        $detallesStmt = $db->prepare($detallesQuery);
        $detallesStmt->execute([$row['id']]);
        $detalles = $detallesStmt->fetchAll(PDO::FETCH_ASSOC);

        $row['detalles'] = $detalles;
        $pedidos[] = $row;
    }

    echo json_encode([
        'success' => true,
        'pedidos' => $pedidos,
        'total' => count($pedidos)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener pedidos: ' . $e->getMessage()
    ]);
}
?>
