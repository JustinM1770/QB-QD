<?php
/**
 * API: Aceptar un pedido
 * POST /api/pedidos/aceptar.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener datos del request
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->pedido_id) || !isset($data->repartidor_id)) {
        throw new Exception('Faltan datos requeridos');
    }

    $pedido_id = $data->pedido_id;
    $repartidor_id = $data->repartidor_id;

    // Verificar que el pedido existe y está disponible
    $checkQuery = "SELECT id_pedido, id_estado, id_repartidor
                   FROM pedidos
                   WHERE id_pedido = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$pedido_id]);
    $pedido = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }

    if ($pedido['id_repartidor'] && $pedido['id_repartidor'] != 0) {
        throw new Exception('Este pedido ya fue aceptado por otro repartidor');
    }

    // Asignar repartidor al pedido (id_estado = 5 es 'en_camino')
    $query = "UPDATE pedidos
              SET id_repartidor = ?,
                  id_estado = 5,
                  fecha_asignacion_repartidor = NOW(),
                  fecha_aceptacion_repartidor = NOW()
              WHERE id_pedido = ?
              AND (id_repartidor IS NULL OR id_repartidor = 0)";

    $stmt = $db->prepare($query);
    $result = $stmt->execute([$repartidor_id, $pedido_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo aceptar el pedido. Puede que otro repartidor lo haya tomado.');
    }

    // Registrar en historial de estados
    $historialQuery = "INSERT INTO historial_estados_pedido (id_pedido, id_estado, notas)
                       VALUES (?, 5, 'Pedido aceptado por repartidor')";
    $historialStmt = $db->prepare($historialQuery);
    $historialStmt->execute([$pedido_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Pedido aceptado exitosamente',
        'pedido_id' => $pedido_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
