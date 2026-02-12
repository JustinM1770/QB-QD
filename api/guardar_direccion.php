<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../models/Direccion.php';

// Obtener conexión a BD
$database = new Database();
$db = $database->getConnection();

// Crear objeto Dirección
$direccion = new Direccion($db);

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener datos del POST
$data = $_POST;

// Asignar propiedades
$direccion->id_usuario = $_SESSION['id_usuario'];
$direccion->nombre_direccion = $data['nombre_direccion'];
$direccion->calle = $data['calle'];
$direccion->numero = $data['numero'];
$direccion->colonia = $data['colonia'];
$direccion->ciudad = $data['ciudad'];
$direccion->estado = $data['estado'];
$direccion->codigo_postal = $data['codigo_postal'];
$direccion->latitud = isset($data['latitud']) ? $data['latitud'] : null;
$direccion->longitud = isset($data['longitud']) ? $data['longitud'] : null;
$direccion->es_predeterminada = isset($data['es_predeterminada']) ? 1 : 0;

// Crear la dirección
if ($direccion->crear()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar la dirección']);
}
?>
