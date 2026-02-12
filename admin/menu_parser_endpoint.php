<?php
/**
 * Endpoint que usa Gemini (RÁPIDO) o Ollama (LENTO) según configuración
 */

// Aumentar timeouts para generación de imágenes
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', 300);
ignore_user_abort(true); // Continuar aunque el usuario cierre el navegador

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
// CONFIGURACIÓN: Cambia aquí para usar Gemini o Ollama
$USE_GEMINI = true; // true = Gemini (rápido), false = Ollama (lento)
$GEMINI_API_KEY = getenv('GEMINI_API_KEY') ?: ''; // Configurar en .env
// Check status
if (isset($_GET['check'])) {
    if ($USE_GEMINI) {
        echo json_encode([
            'ollama_running' => true,
            'model_available' => true,
            'model' => 'gemini-2.0-flash',
            'status' => 'ready',
            'speed' => 'fast',
            'provider' => 'Google Gemini (Gratis)'
        ]);
    } else {
        require_once 'ollama_menu_parser.php';
        try {
            $parser = new OllamaMenuParser('http://localhost:11434', 'moondream');
            $reflection = new ReflectionClass($parser);
            $method = $reflection->getMethod('isOllamaRunning');
            $method->setAccessible(true);
            $running = $method->invoke($parser);
            
            echo json_encode([
                'ollama_running' => $running,
                'model_available' => $running,
                'model' => 'moondream',
                'status' => $running ? 'ready' : 'offline',
                'speed' => 'slow',
                'provider' => 'Ollama Local'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'ollama_running' => false,
                'model_available' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    exit;
}
// Procesar imagen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    
    try {
        $file = $_FILES['image'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir archivo');
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Formato no soportado. Use JPG, PNG o WEBP');
        }
        // Mover archivo temporal
        $tempPath = sys_get_temp_dir() . '/' . uniqid('menu_') . '.jpg';
        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            throw new Exception('No se pudo procesar la imagen');
        }
        
        $startTime = microtime(true);
        // Usar Gemini o Ollama según configuración
        if ($USE_GEMINI) {
            require_once 'gemini_menu_parser.php';
            $parser = new GeminiMenuParser($GEMINI_API_KEY);
        } else {
            require_once 'ollama_menu_parser.php';
            $parser = new OllamaMenuParser();
        }
        $result = $parser->parseMenuFromImage($tempPath);
        $duration = round(microtime(true) - $startTime, 2);
        // Limpiar
        @unlink($tempPath);
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'duration' => $duration,
            'provider' => $USE_GEMINI ? 'Gemini' : 'Ollama'
        ]);
    } catch (Exception $e) {
        if (isset($tempPath) && file_exists($tempPath)) {
            @unlink($tempPath);
        }
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Método no soportado'
    ]);
}