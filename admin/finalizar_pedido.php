<?php
/**
 * Finalizar Pedido - Repartidor
 *
 * Marca un pedido como entregado y procesa la distribución de pagos:
 * - Actualiza el estado del pedido
 * - Acredita al negocio en su wallet
 * - Acredita al repartidor en su wallet
 */
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../services/PagosPedidoService.php';

// Verificar autenticación
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_POST['id_pedido']) || empty($_POST['id_pedido'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
    exit;
}

$id_pedido = intval($_POST['id_pedido']);
$id_usuario_repartidor = $_SESSION['id_usuario'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verificar que el pedido pertenece al repartidor y está en estado correcto
    $query_verificar = "SELECT p.*, n.nombre as nombre_negocio, u.nombre as nombre_cliente,
                               r.id_repartidor
                       FROM pedidos p
                       LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                       LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                       LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
                       WHERE p.id_pedido = ?
                       AND r.id_usuario = ?
                       AND (p.id_estado IN (4, 5) OR p.estado IN ('asignado', 'recogido', 'en_camino'))";

    $stmt_verificar = $db->prepare($query_verificar);
    $stmt_verificar->execute([$id_pedido, $id_usuario_repartidor]);

    if ($stmt_verificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no se puede finalizar']);
        exit;
    }

    $pedido = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    // Calcular tiempo de entrega
    $minutos_entrega = 0;
    if (!empty($pedido['fecha_asignacion_repartidor'])) {
        $fecha_asignacion = new DateTime($pedido['fecha_asignacion_repartidor']);
        $fecha_entrega = new DateTime();
        $tiempo_entrega = $fecha_entrega->diff($fecha_asignacion);
        $minutos_entrega = ($tiempo_entrega->h * 60) + $tiempo_entrega->i;
    }

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Actualizar estado del pedido a entregado (estado 6)
        $query_finalizar = "UPDATE pedidos SET
                           id_estado = 6,
                           estado = 'entregado',
                           fecha_entrega = NOW(),
                           tiempo_entrega = ?,
                           fecha_actualizacion = NOW()
                           WHERE id_pedido = ?";

        $stmt_finalizar = $db->prepare($query_finalizar);
        $stmt_finalizar->execute([$minutos_entrega, $id_pedido]);

        // Registrar en historial
        $query_historial = "INSERT INTO historial_estados_pedido (id_pedido, id_estado, notas, fecha_cambio)
                           VALUES (?, 6, 'Pedido entregado exitosamente', NOW())";
        $stmt_historial = $db->prepare($query_historial);
        $stmt_historial->execute([$id_pedido]);

        $db->commit();

        // Procesar distribución de pagos (wallets)
        $servicioPagos = new PagosPedidoService($db);
        $resultadoPagos = $servicioPagos->procesarPagosPedido($id_pedido);

        // Preparar respuesta
        $ganancia_repartidor = 0;
        if ($resultadoPagos['success'] && isset($resultadoPagos['distribucion'])) {
            $ganancia_repartidor = $resultadoPagos['distribucion']['pago_repartidor'];
        }

        $response = [
            'success' => true,
            'message' => 'Entrega finalizada exitosamente',
            'pedido_id' => $id_pedido,
            'cliente' => $pedido['nombre_cliente'],
            'restaurante' => $pedido['nombre_negocio'],
            'tiempo_entrega' => $minutos_entrega,
            'ganancia' => number_format($ganancia_repartidor, 2),
            'fecha_entrega' => date('Y-m-d H:i:s'),
            'pago_procesado' => $resultadoPagos['success'],
            'distribucion' => $resultadoPagos['distribucion'] ?? null
        ];

        echo json_encode($response);

    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} catch (PDOException $e) {
    error_log("Error BD finalizar_pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
} catch (Exception $e) {
    error_log("Error finalizar_pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>