<?php
/**
 * API: Actualizar estado online/offline del repartidor
 * POST /api/repartidores/estado.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->repartidor_id) || !isset($data->online)) {
        throw new Exception('Faltan datos requeridos');
    }

    $repartidor_id = $data->repartidor_id;
    $disponible = $data->online ? 1 : 0;

    // Actualizar estado en tabla de repartidores (columna: disponible)
    $query = "UPDATE repartidores
              SET disponible = ?,
                  ultima_actualizacion_ubicacion = NOW()
              WHERE id_repartidor = ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$disponible, $repartidor_id]);

    echo json_encode([
        'success' => true,
        'message' => $disponible ? 'Ahora estás disponible' : 'Ahora estás no disponible',
        'online' => (bool)$disponible
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
