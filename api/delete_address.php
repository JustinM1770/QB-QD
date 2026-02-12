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
$id_direccion = isset($_POST['id_direccion']) ? (int)$_POST['id_direccion'] : 0;

if ($id_direccion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de dirección inválido']);
    exit;
}

// Verificar que la dirección pertenece al usuario
$query_check = "SELECT id_direccion FROM direcciones_usuario WHERE id_direccion = :id_direccion AND id_usuario = :id_usuario LIMIT 1";
$stmt_check = $db->prepare($query_check);
$stmt_check->bindParam(':id_direccion', $id_direccion);
$stmt_check->bindParam(':id_usuario', $id_usuario);
$stmt_check->execute();

if ($stmt_check->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario']);
    exit;
}

// Eliminar dirección
$query_delete = "DELETE FROM direcciones_usuario WHERE id_direccion = :id_direccion";
$stmt_delete = $db->prepare($query_delete);
$stmt_delete->bindParam(':id_direccion', $id_direccion);

if ($stmt_delete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Dirección eliminada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar dirección']);
}
?>
