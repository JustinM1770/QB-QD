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

$query = "SELECT id_direccion, nombre_direccion, calle, numero, colonia, ciudad, estado, codigo_postal, es_predeterminada
          FROM direcciones_usuario
          WHERE id_usuario = :id_usuario
          ORDER BY es_predeterminada DESC, id_direccion DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->execute();

$direcciones = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $direcciones[] = [
        'id' => $row['id_direccion'],
        'nombre' => $row['nombre_direccion'],
        'calle' => $row['calle'],
        'numero' => $row['numero'],
        'colonia' => $row['colonia'],
        'ciudad' => $row['ciudad'],
        'estado' => $row['estado'],
        'codigo_postal' => $row['codigo_postal'],
        'predeterminada' => (bool)$row['es_predeterminada']
    ];
}

echo json_encode(['success' => true, 'direcciones' => $direcciones]);
?>
