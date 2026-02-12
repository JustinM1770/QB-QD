<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(0);

require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $id_repartidor = $_SESSION['id_usuario'];
    
    // Obtener estadísticas del repartidor
    $query = "SELECT 
                COUNT(CASE WHEN id_estado = 5 THEN 1 END) as total_entregas,
                COALESCE(SUM(comision_repartidor), 0) as ganancia_total,
                COALESCE(AVG(calificacion_entrega), 0) as calificacion_promedio
              FROM pedidos 
              WHERE id_repartidor = ?";
              
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_repartidor);
    $stmt->execute();
    
    $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Formatear valores
    $estadisticas['ganancia_total'] = number_format($estadisticas['ganancia_total'], 2);
    $estadisticas['calificacion_promedio'] = number_format($estadisticas['calificacion_promedio'], 1);
    
    echo json_encode([
        'success' => true,
        'estadisticas' => $estadisticas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}
