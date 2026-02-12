<?php
/**
 * Guarda menú en base de datos
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once 'gemini_menu_parser.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['negocio_id']) || !isset($data['menu'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    $parser = new GeminiMenuParser();
    $stats = $parser->insertIntoDatabase($data['menu'], $data['negocio_id']);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;

