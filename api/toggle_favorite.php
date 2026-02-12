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
$id_negocio = isset($_POST['id_negocio']) ? (int)$_POST['id_negocio'] : 0;

if ($id_negocio <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de negocio inválido']);
    exit;
}

// Verificar si ya es favorito
$query_check = "SELECT id_favorito FROM favoritos WHERE id_usuario = :id_usuario AND id_negocio = :id_negocio LIMIT 1";
$stmt_check = $db->prepare($query_check);
$stmt_check->bindParam(':id_usuario', $id_usuario);
$stmt_check->bindParam(':id_negocio', $id_negocio);
$stmt_check->execute();

if ($stmt_check->rowCount() > 0) {
    // Ya es favorito, eliminar
    $row = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $id_favorito = $row['id_favorito'];

    $query_delete = "DELETE FROM favoritos WHERE id_favorito = :id_favorito";
    $stmt_delete = $db->prepare($query_delete);
    $stmt_delete->bindParam(':id_favorito', $id_favorito);

    if ($stmt_delete->execute()) {
        echo json_encode(['success' => true, 'favorito' => false, 'message' => 'Eliminado de favoritos']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar favorito']);
    }
} else {
    // No es favorito, agregar
    $query_insert = "INSERT INTO favoritos (id_usuario, id_negocio, fecha_creacion) VALUES (:id_usuario, :id_negocio, NOW())";
    $stmt_insert = $db->prepare($query_insert);
    $stmt_insert->bindParam(':id_usuario', $id_usuario);
    $stmt_insert->bindParam(':id_negocio', $id_negocio);

    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'favorito' => true, 'message' => 'Agregado a favoritos']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar favorito']);
    }
}
?>
