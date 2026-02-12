<?php
/**
 * Diagnóstico de conexión a Ollama
 * Verifica si Ollama está corriendo y responde correctamente
 */

error_reporting(0);
ini_set('display_errors', 0);
$ollamaUrl = 'http://localhost:11434';
echo "<h1>Diagnóstico de Ollama</h1>\n";
echo "<pre>\n";
// Test 1: Ping básico
echo "=== Test 1: Ping básico ===\n";
$ch = curl_init($ollamaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_VERBOSE, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);
if ($error) {
    echo "❌ Error: {$error}\n";
    echo "Info: " . print_r($info, true) . "\n";
} else {
    echo "✅ Conexión exitosa\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: {$response}\n";
}
echo "\n";
// Test 2: Verificar API tags
echo "=== Test 2: API Tags ===\n";
$ch = curl_init($ollamaUrl . '/api/tags');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    echo "✅ HTTP Code: {$httpCode}\n";
    if ($response) {
        $data = json_decode($response, true);
        echo "Modelos disponibles:\n";
        if (isset($data['models']) && count($data['models']) > 0) {
            foreach ($data['models'] as $model) {
}
                echo "  - " . $model['name'] . "\n";
            }
        } else {
            echo "  ⚠️ No hay modelos instalados\n";
            echo "  Instala LLaVA con: ollama pull llava\n";
        }
    } else {
        echo "❌ Respuesta vacía\n";
    }
// Test 3: Test simple de generación
echo "=== Test 3: Generación simple (sin imagen) ===\n";
$payload = [
    'model' => 'moondream',
    'prompt' => 'Responde solo: OK',
    'stream' => false,
    'options' => [
        'temperature' => 0,
        'num_predict' => 10
    ]
];
$ch = curl_init($ollamaUrl . '/api/generate');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Expect:',
    'Connection: close'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
echo "Enviando request... (puede tardar)\n";
    echo "\n";
    echo "POSIBLES SOLUCIONES:\n";
    echo "1. Verifica que Ollama esté corriendo:\n";
    echo "   sudo systemctl status ollama\n";
    echo "   (o) ps aux | grep ollama\n\n";
    echo "2. Si no está corriendo, inícialo:\n";
    echo "   ollama serve\n\n";
    echo "3. Si está corriendo pero da error, reinícialo:\n";
    echo "   sudo systemctl restart ollama\n\n";
    echo "4. Verifica que el modelo esté descargado:\n";
    echo "   ollama list\n";
    echo "   ollama pull llava\n\n";
    echo "5. Aumenta los límites de memoria si es necesario:\n";
    echo "   export OLLAMA_MAX_LOADED_MODELS=1\n";
    echo "   export OLLAMA_NUM_PARALLEL=1\n";
        $result = json_decode($response, true);
        if (isset($result['response'])) {
            echo "Respuesta: " . $result['response'] . "\n";
            echo "✅ Ollama está funcionando correctamente!\n";
            echo "Respuesta completa: " . print_r($result, true) . "\n";
// Test 4: Verificar cURL y extensiones PHP
echo "=== Test 4: Configuración PHP ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "cURL Version: " . curl_version()['version'] . "\n";
echo "cURL SSL Version: " . curl_version()['ssl_version'] . "\n";
if (function_exists('curl_init')) {
    echo "✅ cURL está habilitado\n";
    echo "❌ cURL NO está habilitado\n";
// Test 5: Conectividad de red
echo "=== Test 5: Conectividad de red ===\n";
$socket = @fsockopen('localhost', 11434, $errno, $errstr, 5);
if ($socket) {
    echo "✅ Puerto 11434 está abierto\n";
    fclose($socket);
}
    echo "❌ No se puede conectar al puerto 11434\n";
    echo "Error: {$errstr} ({$errno})\n";
echo "</pre>\n";
?>
