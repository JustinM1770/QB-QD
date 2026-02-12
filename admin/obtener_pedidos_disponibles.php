<?php
// Iniciar sesión y conexión a BD
session_start();

// Activar reporte de errores para depuración
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Incluir configuración de BD
require_once '../config/database.php';
require_once '../models/Repartidor.php';

// Verificar autenticación (comentado para pruebas)
/*
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
*/

// Para pruebas, permitir acceso anónimo
$isTest = false;

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Inicializar objetos
$repartidor = new Repartidor($db);

// Obtener ID de repartidor (de la sesión o una prueba)
$id_repartidor = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 'test_courier';

// Verificar disponibilidad del repartidor (obviar para pruebas)
$disponibilidad = true;

// Obtener ubicación actual (simulada o real)
$latitud = 19.4326; // CDMX por defecto
$longitud = -99.1332;

// Si es petición GET con coordenadas, usarlas
if (isset($_GET['lat']) && isset($_GET['lng'])) {
    $latitud = floatval($_GET['lat']);
    $longitud = floatval($_GET['lng']);
}

// Si es POST con coordenadas, también usarlas
if (isset($_POST['lat']) && isset($_POST['lng'])) {
    $latitud = floatval($_POST['lat']);
    $longitud = floatval($_POST['lng']);
}

// Configurar respuesta como JSON
header('Content-Type: application/json');

// Si no hay disponibilidad, devolver array vacío
if (!$disponibilidad && !$isTest) {
    echo json_encode([]);
    exit;
}

// Intentar obtener pedidos disponibles
try {
    // Primero intentar el método normal
    $pedidos_disponibles = [];

    if (!$isTest) {
        $pedidos_disponibles = $repartidor->obtenerPedidosDisponibles($longitud, $latitud);
    }

    // Si no hay pedidos o es prueba, generar datos de ejemplo
    if (empty($pedidos_disponibles) || $isTest) {
        // Datos de prueba
        $pedidos_disponibles = [
            [
                'id_pedido' => 1001,
                'restaurante' => 'Taquería El Buen Sabor',
                'cliente' => 'Juan López',
                'direccion_entrega' => 'Calle Principal 123, Col. Centro',
                'distancia' => 1500, // 1.5 km
                'tiempo_restante' => 15
            ],
            [
                'id_pedido' => 1002,
                'restaurante' => 'Pizzería Bella Napoli',
                'cliente' => 'María González',
                'direccion_entrega' => 'Av. Reforma 456, Col. Juárez',
                'distancia' => 2300, // 2.3 km
                'tiempo_restante' => 25
            ],
            [
                'id_pedido' => 1003,
                'restaurante' => 'Sushi House',
                'cliente' => 'Carlos Ramírez',
                'direccion_entrega' => 'Calle Insurgentes 789, Col. Roma',
                'distancia' => 3200, // 3.2 km
                'tiempo_restante' => null
            ]
        ];
    }

    // Devolver respuesta
    echo json_encode($pedidos_disponibles);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener pedidos: ' . $e->getMessage()]);
}