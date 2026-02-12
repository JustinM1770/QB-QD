<?php
// get_order_status.php - API para consultar estado actual del pedido
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Activar reporte de errores para depuración
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Incluir configuración de BD
require_once '../config/database.php';
require_once '../models/Pedido.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si se proporcionó un ID de pedido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
    exit;
}

$id_pedido = intval($_GET['id']);
$id_usuario = $_SESSION['id_usuario'];

try {
    // Conectar a BD
    $database = new Database();
    $db = $database->getConnection();
    
    // Consultar estado actual del pedido
    $query = "SELECT id_estado, fecha_actualizacion, tipo_entrega 
              FROM pedidos 
              WHERE id_pedido = ? AND id_usuario = ?";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_pedido);
    $stmt->bindParam(2, $id_usuario);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'status' => intval($resultado['id_estado']),
            'last_updated' => $resultado['fecha_actualizacion'],
            'delivery_type' => $resultado['tipo_entrega'] ?? 'delivery',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Pedido no encontrado o no autorizado'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error en get_order_status: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos'
    ]);
} catch (Exception $e) {
    error_log("Error general en get_order_status: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor'
    ]);
}
?>