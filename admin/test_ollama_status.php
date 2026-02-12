<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Test 1: Verificar que Ollama esté corriendo
    $ch = curl_init('http://localhost:11434/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error || $httpCode !== 200) {
        throw new Exception("Ollama no está respondiendo: " . ($error ?: "HTTP $httpCode"));
    }
    $data = json_decode($response, true);
    if (!isset($data['models']) || empty($data['models'])) {
        throw new Exception("No hay modelos instalados");
    // Buscar moondream
    $modelFound = false;
    $modelName = '';
    foreach ($data['models'] as $model) {
        if (strpos($model['name'], 'moondream') !== false) {
            $modelFound = true;
            $modelName = $model['name'];
            break;
        }
    if (!$modelFound) {
        throw new Exception("Modelo moondream no encontrado. Modelos disponibles: " . 
            implode(', ', array_column($data['models'], 'name')));
    echo json_encode([
        'success' => true,
        'ollama_running' => true,
        'model_available' => true,
        'model' => $modelName,
        'total_models' => count($data['models'])
}
    ]);
} catch (Exception $e) {
        'success' => false,
        'ollama_running' => false,
        'model_available' => false,
        'error' => $e->getMessage()
}
