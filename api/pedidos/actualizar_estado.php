<?php
/**
 * API: Actualizar estado de un pedido
 * PUT /api/pedidos/actualizar_estado.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST');

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener datos del request
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->pedido_id) || !isset($data->estado)) {
        throw new Exception('Faltan datos requeridos');
    }

    $pedido_id = $data->pedido_id;
    $nuevo_estado = $data->estado;
    $observaciones = $data->observaciones ?? null;

    // Mapeo de nombres de estado a IDs
    $estado_map = [
        'pendiente' => 1,
        'confirmado' => 2,
        'en_preparacion' => 3,
        'preparando' => 3, // alias
        'listo_para_recoger' => 4,
        'listo' => 4, // alias
        'en_camino' => 5,
        'entregado' => 6,
        'cancelado' => 7,
        'abandonado' => 8,
        'reasignado' => 9,
        'sin_repartidor' => 10
    ];

    if (!isset($estado_map[$nuevo_estado])) {
        throw new Exception('Estado no válido');
    }

    $id_estado = $estado_map[$nuevo_estado];

    // Actualizar estado del pedido
    $query = "UPDATE pedidos SET id_estado = ?";

    // Si se marca como entregado, guardar fecha de entrega
    if ($nuevo_estado === 'entregado') {
        $query .= ", fecha_entrega = NOW()";
    }

    $query .= " WHERE id_pedido = ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$id_estado, $pedido_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo actualizar el pedido');
    }

    // Registrar en historial de estados
    $historialQuery = "INSERT INTO historial_estados_pedido (id_pedido, id_estado, notas)
                       VALUES (?, ?, ?)";
    $historialStmt = $db->prepare($historialQuery);
    $historialStmt->execute([$pedido_id, $id_estado, $observaciones]);

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'pedido_id' => $pedido_id,
        'nuevo_estado' => $nuevo_estado
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
