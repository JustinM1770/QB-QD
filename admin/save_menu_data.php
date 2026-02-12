<?php
/**
 * Endpoint para guardar datos del menú en la base de datos
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/gemini_menu_parser.php';

try {
    // Leer JSON del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['negocio_id']) || !isset($data['menu_data'])) {
        throw new Exception('Datos inválidos');
    }
    
    $negocioId = (int)$data['negocio_id'];
    $menuData = $data['menu_data'];
    
    // Validar negocio
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id_negocio, nombre FROM negocios WHERE id_negocio = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$negocioId]);
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$negocio) {
        throw new Exception('Negocio no encontrado');
    }
    
    // Insertar datos
    $parser = new GeminiMenuParser();
    $stats = $parser->insertIntoDatabase($menuData, $negocioId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Menú guardado exitosamente',
        'stats' => $stats,
        'negocio' => $negocio['nombre']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
