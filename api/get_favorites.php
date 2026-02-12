<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id_usuario = $_SESSION['id_usuario'];

$query = "SELECT f.id_favorito, n.id_negocio, n.nombre, n.descripcion, n.logo, n.imagen_portada
          FROM favoritos f
          JOIN negocios n ON f.id_negocio = n.id_negocio
          WHERE f.id_usuario = :id_usuario
          ORDER BY f.fecha_creacion DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->execute();

$favoritos = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $favoritos[] = [
        'id_favorito' => $row['id_favorito'],
        'id' => $row['id_negocio'],
        'nombre' => $row['nombre'],
        'descripcion' => $row['descripcion'],
        'logo' => $row['logo'],
        'imagen_portada' => $row['imagen_portada']
    ];
}

echo json_encode(['success' => true, 'favoritos' => $favoritos]);
?>
