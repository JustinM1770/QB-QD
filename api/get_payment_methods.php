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

$query = "SELECT id_metodo_pago, tipo_pago, proveedor, numero_cuenta, fecha_vencimiento, es_predeterminado
          FROM metodos_pago
          WHERE id_usuario = :id_usuario
          ORDER BY es_predeterminado DESC, id_metodo_pago DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->execute();

$metodos_pago = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $metodos_pago[] = [
        'id' => $row['id_metodo_pago'],
        'tipo' => $row['tipo_pago'],
        'proveedor' => $row['proveedor'],
        'numero_cuenta' => $row['numero_cuenta'],
        'fecha_vencimiento' => $row['fecha_vencimiento'],
        'predeterminado' => (bool)$row['es_predeterminado']
    ];
}

echo json_encode(['success' => true, 'metodos_pago' => $metodos_pago]);
?>
