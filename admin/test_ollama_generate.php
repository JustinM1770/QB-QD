<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $payload = [
        'model' => 'moondream',
        'prompt' => 'Say only: Hello, I am working!',
        'stream' => false,
        'options' => [
            'temperature' => 0,
            'num_predict' => 20
        ]
    ];
    
    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        throw new Exception("cURL error: " . $error);
    }
    if ($httpCode !== 200) {
        throw new Exception("HTTP error: " . $httpCode);
    $result = json_decode($response, true);
    if (!isset($result['response'])) {
        throw new Exception("Respuesta inválida: " . print_r($result, true));
    echo json_encode([
        'success' => true,
        'response' => $result['response'],
        'model' => $result['model'] ?? 'moondream',
        'total_duration_ms' => isset($result['total_duration']) ? 
            round($result['total_duration'] / 1000000) : null
}
    ]);
} catch (Exception $e) {
        'success' => false,
        'error' => $e->getMessage()
}
