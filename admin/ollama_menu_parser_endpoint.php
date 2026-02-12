<?php
/**
 * Endpoint para el parser de menús con Ollama
 * Recibe imágenes y devuelve menú estructurado en JSON
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300); // 5 minutos para procesamiento de IA
ini_set('max_execution_time', 300);
require_once 'ollama_menu_parser.php';
// Check status
if (isset($_GET['check'])) {
    try {
        $parser = new OllamaMenuParser(
            'http://localhost:11434',
            'moondream'
        );
        
        // Verificar que Ollama esté corriendo usando reflexión
        $reflection = new ReflectionClass($parser);
        $method = $reflection->getMethod('isOllamaRunning');
        $method->setAccessible(true);
        $running = $method->invoke($parser);
        $method2 = $reflection->getMethod('isModelAvailable');
        $method2->setAccessible(true);
        $modelAvailable = $method2->invoke($parser);
        echo json_encode([
            'ollama_running' => $running && $modelAvailable,
            'model' => 'moondream',
            'status' => 'ready'
        ]);
    } catch (Exception $e) {
            'ollama_running' => false,
            'error' => $e->getMessage()
    }
    exit;
}
// Procesar imagen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    
        $file = $_FILES['image'];
        // Validar archivo
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir archivo');
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Formato de imagen no soportado. Use JPG, PNG o WEBP');
        // Mover archivo temporal
        $tempPath = sys_get_temp_dir() . '/' . uniqid('menu_') . '.jpg';
        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            throw new Exception('No se pudo procesar la imagen');
        // Crear parser y procesar
        $result = $parser->parseMenuFromImage($tempPath);
        // Limpiar archivo temporal
        @unlink($tempPath);
        // Responder
            'success' => true,
            'data' => $result
        // Limpiar archivo temporal si existe
        if (isset($tempPath) && file_exists($tempPath)) {
            @unlink($tempPath);
            'success' => false,
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Método no soportado. Use POST con una imagen'
    ]);
