<?php
/**
 * API para procesar distribución de pagos de pedidos
 *
 * Endpoint: POST /api/procesar_pago_pedido.php
 * Body: { "id_pedido": 123 }
 *
 * Se llama automáticamente cuando un pedido se marca como entregado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../services/PagosPedidoService.php';

try {
    // Solo aceptar POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener datos
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    $idPedido = isset($input['id_pedido']) ? intval($input['id_pedido']) : 0;

    if ($idPedido <= 0) {
        throw new Exception('ID de pedido inválido');
    }

    // Procesar pagos
    $servicio = new PagosPedidoService();
    $resultado = $servicio->procesarPagosPedido($idPedido);

    echo json_encode($resultado);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
