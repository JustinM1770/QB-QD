<?php
/**
 * WEB ENDPOINT - Parser de menús con OpenAI
 * Permite subir imágenes y parsear menús desde el navegador
 */

session_start();
require_once '../config/database.php';
require_once 'openai_menu_parser.php';
header('Content-Type: application/json; charset=utf-8');
// Verificar autenticación
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'negocio') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';
try {
    // Obtener API Key (desde config o variable de entorno)
    $apiKey = getenv('OPENAI_API_KEY') ?: null;
    
    if (empty($apiKey)) {
        throw new Exception('API Key de OpenAI no configurada. Contacta al administrador.');
    }
    $parser = new OpenAIMenuParser($apiKey);
    switch ($action) {
        
        case 'parse_image':
            // Verificar que se subió una imagen
            if (!isset($_FILES['menu_image']) || $_FILES['menu_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibió ninguna imagen válida');
            }
            
            $file = $_FILES['menu_image'];
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG o WebP.');
            }
            
            // Validar tamaño (máx 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Archivo muy grande. Máximo 10MB.');
            }
            
            // Parsear menú
            $result = $parser->parseMenuFromImage($file['tmp_name']);
            
            if (!$result['success']) {
                throw new Exception($result['error']);
            }
            
            // Devolver resultado
            echo json_encode([
                'success' => true,
                'message' => 'Menú parseado exitosamente',
                'data' => $result['data'],
                'estadisticas' => $result['data']['estadisticas'],
                'tokens_usados' => $result['tokens_used']
            ]);
            break;
            
        case 'parse_url':
            $imageUrl = $_POST['image_url'] ?? '';
            
            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('URL de imagen inválida');
            }
            
            // Parsear menú desde URL
            $result = $parser->parseMenuFromImage($imageUrl);
            
            if (!$result['success']) {
                throw new Exception($result['error']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Menú parseado exitosamente',
                'data' => $result['data'],
                'estadisticas' => $result['data']['estadisticas'],
                'tokens_usados' => $result['tokens_used']
            ]);
            break;
            
        case 'import_to_db':
            $menuJson = $_POST['menu_data'] ?? '';
            
            if (empty($menuJson)) {
                throw new Exception('Datos del menú no recibidos');
            }
            
            $menuData = json_decode($menuJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON inválido: ' . json_last_error_msg());
            }
            
            // Conectar a BD
            $database = new Database();
            $db = $database->getConnection();
            $idNegocio = $_SESSION['id_negocio'];
            
            // Importar a base de datos
            $result = $parser->insertIntoDatabase($db, $idNegocio, $menuData);
            
            echo json_encode([
                'success' => true,
                'message' => 'Menú importado exitosamente a la base de datos',
                'result' => $result
            ]);
            break;
            
        case 'parse_and_import':
            // Parsear e importar en un solo paso
            if (!isset($_FILES['menu_image']) || $_FILES['menu_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibió ninguna imagen válida');
            }
            
            $file = $_FILES['menu_image'];
            
            // Validaciones
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido');
            }
            
            // Parsear
            $parseResult = $parser->parseMenuFromImage($file['tmp_name']);
            if (!$parseResult['success']) {
                throw new Exception($parseResult['error']);
            }
            
            // Conectar a BD
            $database = new Database();
            $db = $database->getConnection();
            $idNegocio = $_SESSION['id_negocio'];
            
            // Importar
            $importResult = $parser->insertIntoDatabase($db, $idNegocio, $parseResult['data']);
            if (!$importResult['success']) {
                throw new Exception($importResult['error']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Menú parseado e importado exitosamente',
                'parse_result' => [
                    'estadisticas' => $parseResult['data']['estadisticas'],
                    'tokens_usados' => $parseResult['tokens_used']
                ],
                'import_result' => $importResult
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
