<?php
/**
 * API REST para Ollama Menu Parser
 * Endpoint web para analizar menús con Ollama (100% GRATIS, sin API keys)
 */

header('Content-Type: application/json');
// Verificar autenticación (opcional)
session_start();
// if (!isset($_SESSION['id_usuario'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'error' => 'No autenticado']);
//     exit;
// }
require_once 'ollama_menu_parser.php';
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    $parser = new OllamaMenuParser();
    switch ($action) {
        
        // ================================================================
        // 1. Verificar estado de Ollama
        case 'check_status':
            $info = $parser->getSystemInfo();
            echo json_encode([
                'success' => true,
                'data' => $info
            ]);
            break;
        // 2. Analizar imagen subida
        case 'parse_image':
            if (!isset($_FILES['menu_image'])) {
                throw new Exception('No se recibió ninguna imagen');
            }
            
            $file = $_FILES['menu_image'];
            // Validar tipo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG o WebP');
            // Validar tamaño (10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Archivo muy grande. Máximo 10MB');
            // Guardar temporalmente
            $tmpPath = sys_get_temp_dir() . '/' . uniqid('menu_') . '_' . basename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                throw new Exception('Error al guardar archivo temporal');
            // Analizar
            $result = $parser->parseMenuFromImage($tmpPath);
            // Limpiar
            unlink($tmpPath);
                'data' => $result,
                'estadisticas' => $result['estadisticas']
        // 3. Importar datos parseados a la BD
}
        case 'import_to_db':
            $menuData = json_decode($_POST['menu_data'] ?? '{}', true);
            if (empty($menuData)) {
                throw new Exception('No se recibieron datos del menú');
            $stats = $parser->insertIntoDatabase($menuData);
                'result' => $stats
        // 4. Analizar e importar en un solo paso
        case 'parse_and_import':
            // Validar
                throw new Exception('Tipo de archivo no permitido');
            move_uploaded_file($file['tmp_name'], $tmpPath);
            $menuData = $parser->parseMenuFromImage($tmpPath);
            // Importar
            $importStats = $parser->insertIntoDatabase($menuData);
                'parsed_data' => $menuData,
                'import_stats' => $importStats
        // 5. Listar modelos disponibles
        case 'list_models':
                'models' => $info['installed_models'] ?? []
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
