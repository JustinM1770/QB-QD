<?php
/**
 * Ollama Menu Parser - Extractor de menús usando IA local (100% GRATIS)
 * 
 * Usa Ollama con el modelo LLaVA para analizar imágenes de menús y extraer
 * información estructurada sin necesidad de API keys ni costos.
 * Requisitos:
 * - Ollama instalado: curl -fsSL https://ollama.com/install.sh | sh
 * - Modelo LLaVA: ollama pull llava
 * Modelos disponibles con visión:
 * - llava (7B) - Recomendado, rápido
 * - llava:13b - Más preciso pero más lento
 * - bakllava - Alternativa
 * Uso:
 * $parser = new OllamaMenuParser();
 * $result = $parser->parseMenuFromImage('/path/to/menu.jpg');
 * $parser->insertIntoDatabase($result);
 */

class OllamaMenuParser {
    
    private $ollamaUrl;
    private $model;
    private $dbHost;
    private $dbName;
    private $dbUser;
    private $dbPass;
    private $pdo;
    public function __construct(
        $ollamaUrl = 'http://localhost:11434',
        $model = 'moondream',  // Modelo de 4.1GB - requiere ~5GB RAM disponible
        $dbHost = null,
        $dbName = null,
        $dbUser = null,
        $dbPass = null
    ) {
        // Cargar variables de entorno si no están cargadas
        if (!function_exists('env')) {
            require_once __DIR__ . '/../config/env.php';
        }

        $this->ollamaUrl = rtrim($ollamaUrl, '/');
        $this->model = $model;
        $this->dbHost = $dbHost ?: env('DB_HOST', 'localhost');
        $this->dbName = $dbName ?: env('DB_NAME');
        $this->dbUser = $dbUser ?: env('DB_USER');
        $this->dbPass = $dbPass ?: env('DB_PASS');
        
        // Conectar a base de datos
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->dbHost};dbname={$this->dbName};charset=utf8mb4",
                $this->dbUser,
                $this->dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a BD: " . $e->getMessage());
        }
    }
    /**
     * Analiza una imagen de menú usando Ollama LLaVA
     * 
     * @param string $imagePath Ruta local a la imagen
     * @return array Datos estructurados del menú
     */
    public function parseMenuFromImage($imagePath) {
        // Verificar que Ollama esté corriendo
        if (!$this->isOllamaRunning()) {
            throw new Exception("Ollama no está corriendo. Ejecuta: ollama serve");
        // Verificar que el modelo esté disponible
        if (!$this->isModelAvailable()) {
            throw new Exception("Modelo '{$this->model}' no disponible. Ejecuta: ollama pull {$this->model}");
        // Convertir imagen a base64
        $imageBase64 = $this->encodeImageToBase64($imagePath);
        // Crear prompt
        $prompt = $this->buildPrompt();
        // Hacer request a Ollama
        $response = $this->makeOllamaRequest($prompt, $imageBase64);
        // Extraer y validar JSON
        $menuData = $this->extractAndValidateJson($response);
        // Estructurar para base de datos
        $structured = $this->structureForDatabase($menuData);
        return $structured;
     * Verifica si Ollama está corriendo
    private function isOllamaRunning() {
        $ch = curl_init($this->ollamaUrl . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            error_log("Ollama connection error: {$error}");
            return false;
        return $httpCode === 200;
     * Verifica si el modelo está disponible
    private function isModelAvailable() {
        if ($error || !$response) {
            error_log("Error checking models: {$error}");
        $data = json_decode($response, true);
        if (!isset($data['models'])) {
        foreach ($data['models'] as $model) {
            if (strpos($model['name'], $this->model) !== false) {
                return true;
            }
        return false;
     * Convierte imagen a base64 con compresión para reducir memoria
    private function encodeImageToBase64($imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception("Imagen no encontrada: {$imagePath}");
        // Obtener info de la imagen
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            throw new Exception("No es una imagen válida");
        // Cargar imagen según tipo
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($imagePath);
            default:
                throw new Exception("Formato de imagen no soportado");
        if ($image === false) {
            throw new Exception("No se pudo procesar la imagen");
        // Redimensionar si es muy grande (max 1024x1024 para velocidad)
        $width = imagesx($image);
        $height = imagesy($image);
        $maxDim = 1024;
        if ($width > $maxDim || $height > $maxDim) {
            $ratio = min($maxDim / $width, $maxDim / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        // Convertir a JPEG con compresión alta
        ob_start();
        imagejpeg($image, null, 70); // 70% calidad (más rápido)
        $imageData = ob_get_clean();
        imagedestroy($image);
        if ($imageData === false) {
            throw new Exception("No se pudo comprimir la imagen");
        return base64_encode($imageData);
     * Construye el prompt para Ollama (ultra simplificado)
    private function buildPrompt() {
        return <<<PROMPT
Extrae productos de este menú en JSON:
}
{
  "categorias": [{"nombre": "..."}],
  "productos": [{"nombre": "...", "categoria": "...", "precio": 0, "descripcion": "", "disponible": true}]
}
Solo JSON válido:
PROMPT;
     * Hace request a Ollama API
    private function makeOllamaRequest($prompt, $imageBase64) {
        $url = $this->ollamaUrl . '/api/generate';
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'images' => [$imageBase64],
            'stream' => false,
            'options' => [
                'temperature' => 0.1,
                'num_predict' => 1024,      // Reducido para evitar OOM
                'num_ctx' => 2048,          // Contexto reducido
                'num_batch' => 128,         // Batch size conservador
                'num_gpu' => 0,             // CPU only (más estable)
                'num_thread' => 4,          // Limitar threads
                'repeat_penalty' => 1.1,
                'top_k' => 40,
                'top_p' => 0.9
            ]
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Expect:',
            'Connection: close'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos (LLaVA puede tardar)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120);
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60);
        $curlInfo = curl_getinfo($ch);
        // Log detallado para debugging
            error_log("cURL Error: {$error}");
            error_log("cURL Info: " . print_r($curlInfo, true));
            throw new Exception("Error en request a Ollama: {$error}");
        if ($httpCode !== 200) {
            // Intentar decodificar el error
            $errorData = json_decode($response, true);
            if (isset($errorData['error'])) {
                $errorMsg = $errorData['error'];
                
                // Error de memoria
                if (strpos($errorMsg, 'system memory') !== false) {
                    throw new Exception(
                        "Memoria insuficiente para el modelo. " .
                        "Usa un modelo más ligero o libera RAM.\n" .
                        "Modelos recomendados:\n" .
                        "  - ollama pull llava:7b-v1.6-q4_0 (2.5GB)\n" .
}
                        "  - ollama pull llava:7b-v1.6-q3_K_S (1.9GB)\n" .
                        "Error: {$errorMsg}"
                    );
                }
                throw new Exception("Error de Ollama: {$errorMsg}");
            throw new Exception("Ollama devolvió código HTTP {$httpCode}");
        $result = json_decode($response, true);
        if (!isset($result['response'])) {
            throw new Exception("Respuesta inválida de Ollama: " . print_r($result, true));
        return $result['response'];
     * Extrae y valida JSON de la respuesta
    private function extractAndValidateJson($response) {
        // Limpiar respuesta
        $response = trim($response);
        // Buscar JSON en la respuesta
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        if ($jsonStart === false || $jsonEnd === false) {
            throw new Exception("No se encontró JSON en la respuesta");
        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        // Decodificar JSON
        $data = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido: " . json_last_error_msg() . "\nRespuesta: " . $response);
        // Validar estructura
        if (!isset($data['categorias']) || !isset($data['productos'])) {
            throw new Exception("JSON no tiene la estructura esperada");
        return $data;
     * Estructura los datos para la base de datos
    private function structureForDatabase($menuData) {
        $structured = [
            'categorias' => $menuData['categorias'] ?? [],
            'productos' => $menuData['productos'] ?? [],
            'grupos_opciones' => $menuData['grupos_opciones'] ?? [],
            'estadisticas' => [
                'total_categorias' => count($menuData['categorias'] ?? []),
                'total_productos' => count($menuData['productos'] ?? []),
                'total_grupos_opciones' => count($menuData['grupos_opciones'] ?? []),
                'precio_promedio' => $this->calculateAveragePrice($menuData['productos'] ?? [])
        // Calcular totales de opciones
        $totalOpciones = 0;
        foreach ($structured['grupos_opciones'] as $grupo) {
            $totalOpciones += count($grupo['opciones'] ?? []);
        $structured['estadisticas']['total_opciones'] = $totalOpciones;
     * Calcula precio promedio
    private function calculateAveragePrice($productos) {
        if (empty($productos)) {
            return 0;
        $total = 0;
        $count = 0;
        foreach ($productos as $prod) {
            if (isset($prod['precio']) && is_numeric($prod['precio'])) {
                $total += floatval($prod['precio']);
                $count++;
        return $count > 0 ? round($total / $count, 2) : 0;
     * Inserta los datos en la base de datos
    public function insertIntoDatabase($menuData) {
            $this->pdo->beginTransaction();
            $stats = [
                'categorias_creadas' => 0,
                'productos_creados' => 0,
                'grupos_creados' => 0,
                'opciones_creadas' => 0
            ];
            // 1. Insertar categorías
            $categoryIds = [];
            foreach ($menuData['categorias'] as $cat) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO categorias (nombre, descripcion, activo) 
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE id_categoria = LAST_INSERT_ID(id_categoria)
                ");
                $stmt->execute([
                    $cat['nombre'],
                    $cat['descripcion'] ?? ''
                ]);
                $categoryIds[$cat['nombre']] = $this->pdo->lastInsertId();
                $stats['categorias_creadas']++;
            // 2. Insertar productos
            $productIds = [];
            foreach ($menuData['productos'] as $prod) {
                $idCategoria = $categoryIds[$prod['categoria']] ?? null;
                if (!$idCategoria) {
                    continue; // Skip si no hay categoría
                $tieneOpciones = false;
                foreach ($menuData['grupos_opciones'] as $grupo) {
                    if ($grupo['producto'] === $prod['nombre']) {
                        $tieneOpciones = true;
}
                        break;
                    }
                    INSERT INTO productos (
                        id_categoria, nombre, descripcion, precio, 
                        disponible, calorias, tiene_opciones_dinamicas
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    $idCategoria,
                    $prod['nombre'],
                    $prod['descripcion'] ?? '',
                    $prod['precio'],
                    $prod['disponible'] ?? 1,
                    $prod['calorias'] ?? null,
                    $tieneOpciones ? 1 : 0
                $productIds[$prod['nombre']] = $this->pdo->lastInsertId();
                $stats['productos_creados']++;
            // 3. Insertar grupos de opciones
            foreach ($menuData['grupos_opciones'] as $grupo) {
                $idProducto = $productIds[$grupo['producto']] ?? null;
                if (!$idProducto) {
                    continue;
                    INSERT INTO grupos_opciones (
                        id_producto, nombre, descripcion, tipo_seleccion,
                        obligatorio, min_selecciones, max_selecciones, activo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    $idProducto,
                    $grupo['nombre'],
                    $grupo['descripcion'] ?? '',
                    $grupo['tipo_seleccion'],
                    $grupo['obligatorio'] ? 1 : 0,
                    $grupo['min_selecciones'] ?? 0,
                    $grupo['max_selecciones'] ?? 1
                $idGrupo = $this->pdo->lastInsertId();
                $stats['grupos_creados']++;
                // 4. Insertar opciones
                foreach ($grupo['opciones'] as $opcion) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO opciones (
                            id_grupo_opcion, nombre, precio_adicional, 
                            por_defecto, disponible
                        ) VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $idGrupo,
                        $opcion['nombre'],
                        $opcion['precio_adicional'] ?? 0,
                        $opcion['por_defecto'] ?? 0
                    ]);
                    $stats['opciones_creadas']++;
            $this->pdo->commit();
}
            return $stats;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Error al insertar en BD: " . $e->getMessage());
     * Obtiene información del sistema Ollama
    public function getSystemInfo() {
            // Check si Ollama está corriendo
            $running = $this->isOllamaRunning();
            // Obtener modelos instalados
            $ch = curl_init($this->ollamaUrl . '/api/tags');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
            $models = [];
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['models'])) {
                    foreach ($data['models'] as $model) {
                        $models[] = [
                            'name' => $model['name'],
                            'size' => $model['size'] ?? 'N/A',
                            'modified' => $model['modified_at'] ?? 'N/A'
                        ];
            return [
                'ollama_running' => $running,
                'ollama_url' => $this->ollamaUrl,
                'current_model' => $this->model,
                'model_available' => $this->isModelAvailable(),
                'installed_models' => $models
                'ollama_running' => false,
                'error' => $e->getMessage()
// ============================================================================
// EJEMPLO DE USO CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "=== Ollama Menu Parser - Test ===\n\n";
    if ($argc < 2) {
        echo "Uso: php ollama_menu_parser.php <ruta_imagen>\n";
        echo "Ejemplo: php ollama_menu_parser.php /path/to/menu.jpg\n\n";
        echo "Asegúrate de tener Ollama corriendo:\n";
        echo "  ollama serve\n";
        echo "  ollama pull llava\n";
        exit(1);
    $imagePath = $argv[1];
    try {
        $parser = new OllamaMenuParser();
        // Verificar sistema
        echo "Verificando Ollama...\n";
        $info = $parser->getSystemInfo();
        if (!$info['ollama_running']) {
            echo "ERROR: Ollama no está corriendo.\n";
            echo "Ejecuta: ollama serve\n";
            exit(1);
        if (!$info['model_available']) {
            echo "ERROR: Modelo 'llava' no disponible.\n";
            echo "Ejecuta: ollama pull llava\n";
        echo "✓ Ollama corriendo\n";
}
        echo "✓ Modelo disponible: llava\n\n";
        echo "Analizando imagen: {$imagePath}\n";
        echo "Esto puede tomar 1-3 minutos...\n\n";
        $result = $parser->parseMenuFromImage($imagePath);
        echo "=== RESULTADOS ===\n";
        echo "Categorías: " . $result['estadisticas']['total_categorias'] . "\n";
        echo "Productos: " . $result['estadisticas']['total_productos'] . "\n";
        echo "Grupos opciones: " . $result['estadisticas']['total_grupos_opciones'] . "\n";
        echo "Opciones: " . $result['estadisticas']['total_opciones'] . "\n";
        echo "Precio promedio: $" . $result['estadisticas']['precio_promedio'] . "\n\n";
        echo "¿Deseas importar a la base de datos? (s/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) === 's') {
            echo "Importando...\n";
            $stats = $parser->insertIntoDatabase($result);
            echo "\n✓ IMPORTADO EXITOSAMENTE:\n";
            echo "  - Categorías: {$stats['categorias_creadas']}\n";
            echo "  - Productos: {$stats['productos_creados']}\n";
            echo "  - Grupos: {$stats['grupos_creados']}\n";
            echo "  - Opciones: {$stats['opciones_creadas']}\n";
        } else {
            echo "Cancelado.\n";
        // Guardar JSON
        $jsonFile = 'menu_parsed_' . time() . '.json';
        file_put_contents($jsonFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\nJSON guardado en: {$jsonFile}\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
