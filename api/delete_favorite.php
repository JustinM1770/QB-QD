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
$id_favorito = isset($_POST['id_favorito']) ? (int)$_POST['id_favorito'] : 0;

if ($id_favorito <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de favorito inválido']);
    exit;
}

// Verificar que el favorito pertenece al usuario
$query_check = "SELECT id_favorito FROM favoritos WHERE id_favorito = :id_favorito AND id_usuario = :id_usuario LIMIT 1";
$stmt_check = $db->prepare($query_check);
$stmt_check->bindParam(':id_favorito', $id_favorito);
$stmt_check->bindParam(':id_usuario', $id_usuario);
$stmt_check->execute();

if ($stmt_check->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Favorito no encontrado o no pertenece al usuario']);
    exit;
}

// Eliminar favorito
$query_delete = "DELETE FROM favoritos WHERE id_favorito = :id_favorito";
$stmt_delete = $db->prepare($query_delete);
$stmt_delete->bindParam(':id_favorito', $id_favorito);

if ($stmt_delete->execute()) {
    echo json_encode(['success' => true, 'message' => 'Favorito eliminado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar favorito']);
}
?>
