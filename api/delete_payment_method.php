<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id_usuario = $_SESSION['id_usuario'];
$id_metodo_pago = isset($_POST['id_metodo_pago']) ? (int)$_POST['id_metodo_pago'] : 0;

if ($id_metodo_pago <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de método de pago inválido']);
    exit;
}

// Verificar que el método de pago pertenece al usuario
$query_check = "SELECT id_metodo_pago FROM metodos_pago WHERE id_metodo_pago = :id_metodo_pago AND id_usuario = :id_usuario LIMIT 1";
$stmt_check = $db->prepare($query_check);
$stmt_check->bindParam(':id_metodo_pago', $id_metodo_pago);
$stmt_check->bindParam(':id_usuario', $id_usuario);
$stmt_check->execute();

if ($stmt_check->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Método de pago no encontrado o no pertenece al usuario']);
    exit;
}

// Eliminar método de pago
$query_delete = "DELETE FROM metodos_pago WHERE id_metodo_pago = :id_metodo_pago";
$stmt_delete = $db->prepare($query_delete);
$stmt_delete->bindParam(':id_metodo_pago', $id_metodo_pago);

if ($stmt_delete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Método de pago eliminado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar método de pago']);
}
?>
