<?php
/**
 * Parser de Menús con Gemini Flash (GRATIS y RÁPIDO)
 * 
 * Ventajas vs Ollama:
 * - ✅ 5-10 segundos (vs 60+ segundos)
 * - ✅ Completamente gratis
 * - ✅ No consume recursos del servidor
 * - ✅ Mucho más preciso
 * Obtén tu API key gratis en: https://makersuite.google.com/app/apikey
 */

class GeminiMenuParser {
    
    private $apiKey;
    private $pdo;
    
    public function __construct(
        $apiKey = null,
        $dbHost = null,
        $dbName = null,
        $dbUser = null,
        $dbPass = null
    ) {
        // Cargar variables de entorno si no están cargadas
        if (!function_exists('env')) {
            require_once __DIR__ . '/../config/env.php';
        }

        // Usar API key del parámetro o variable de entorno
        $this->apiKey = $apiKey ?: env('AI_API_KEY');

        // Credenciales de BD desde .env
        $dbHost = $dbHost ?: env('DB_HOST', 'localhost');
        $dbName = $dbName ?: env('DB_NAME');
        $dbUser = $dbUser ?: env('DB_USER');
        $dbPass = $dbPass ?: env('DB_PASS');
        
        if (!$this->apiKey) {
            throw new Exception('GEMINI_API_KEY no configurada. Define AI_API_KEY en .env o pasa el API key al constructor.');
        }
        
        try {
            $this->pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception("Error de conexión a BD: " . $e->getMessage());
        }
    }
    
    /**
     * Analiza una imagen de menú usando Gemini
     */
    public function parseMenuFromImage($imagePath) {
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath);
        
        $prompt = "Analiza esta imagen de menú de restaurante y extrae TODOS los productos en formato JSON.\n\n" .
                  "Formato requerido:\n" .
                  "{\n" .
                  "  \"categorias\": [{\"nombre\": \"Nombre categoría\", \"descripcion\": \"\"}],\n" .
                  "  \"productos\": [\n" .
                  "    {\n" .
                  "      \"nombre\": \"Nombre producto\",\n" .
                  "      \"categoria\": \"Categoría\",\n" .
                  "      \"precio\": 99.99,\n" .
                  "      \"descripcion\": \"Descripción\",\n" .
                  "      \"calorias\": 350,\n" .
                  "      \"disponible\": true\n" .
                  "    }\n" .
                  "  ]\n" .
                  "}\n\n" .
                  "IMPORTANTE:\n" .
                  "- Responde SOLO con JSON válido, sin markdown ni texto extra\n" .
                  "- Extrae TODOS los productos visibles\n" .
                  "- Precios como números decimales\n" .
                  "- Calcula calorías aproximadas basándote en el tipo de comida, tamaño de porción e ingredientes visibles\n" .
                  "- Si no puedes estimar las calorías con confianza, usa null\n" .
                  "- No inventes información";
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 8192
            ]
        ];
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$this->apiKey}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Error en request a Gemini: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini devolvió código HTTP {$httpCode}: {$response}");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("Respuesta completa de Gemini: " . print_r($result, true));
            
            if (isset($result['error'])) {
                throw new Exception("Error de Gemini: " . $result['error']['message']);
            }
            
            if (isset($result['candidates'][0]['finishReason'])) {
                throw new Exception("Contenido bloqueado por seguridad: " . $result['candidates'][0]['finishReason']);
            }
            
            throw new Exception("Respuesta inválida de Gemini. Revisa los logs del servidor.");
        }
        
        $textResponse = $result['candidates'][0]['content']['parts'][0]['text'];
        
        $textResponse = preg_replace('/```json\s*/', '', $textResponse);
        $textResponse = preg_replace('/```\s*$/', '', $textResponse);
        $textResponse = preg_replace('/```/', '', $textResponse);
        $textResponse = trim($textResponse);
        
        if (preg_match('/\{[\s\S]*\}/', $textResponse, $matches)) {
            $textResponse = $matches[0];
        }
        
        error_log("JSON extraído de Gemini: " . substr($textResponse, 0, 500));
        
        $menuData = json_decode($textResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido: " . json_last_error_msg() . ". Primeros 200 chars: " . substr($textResponse, 0, 200));
        }
        
        return $this->structureForDatabase($menuData);
    }
    
    /**
     * Genera imagen profesional del platillo usando Pollinations AI (GRATIS)
     */
    private function generateProductImage($nombreProducto, $descripcion = '') {
        try {
            $context = !empty($descripcion) ? ", $descripcion" : '';
            $prompt = "Professional photorealistic food photography of $nombreProducto$context, studio lighting, shallow depth of field, high-end restaurant presentation, 8k quality, commercial food advertising style, appetizing colors, clean white plate, elegant garnish, soft natural lighting from above";
            
            $imageUrl = 'https://image.pollinations.ai/prompt/' . urlencode($prompt) . '?width=800&height=600&nologo=true&enhance=true';
            
            error_log("🎨 Generando imagen para: $nombreProducto");
            error_log("📸 URL Pollinations: $imageUrl");
            
            $localPath = $this->downloadImageToServer($imageUrl, $nombreProducto);
            
            if ($localPath) {
                error_log("✅ Imagen guardada en: $localPath");
                return $localPath;
            }
        } catch (Exception $e) {
            error_log("❌ Error generando imagen para '$nombreProducto': " . $e->getMessage());
        }
        
        return null;
    }
    
    // Control de rate limit
    private $rateLimitHit = false;
    private $imagesGenerated = 0;
    private $maxImagesBeforePause = 5;
    
    /**
     * Descarga imagen al servidor con manejo de rate limit
     */
    private function downloadImageToServer($imageUrl, $productName) {
        // Si ya alcanzamos rate limit, no intentar más
        if ($this->rateLimitHit) {
            error_log("⏸️ Saltando imagen por rate limit previo: $productName");
            return null;
        }
        
        try {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/public/images/platillos/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $safeName = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($productName));
            $filename = $safeName . '_' . time() . '.jpg';
            $localPath = $uploadDir . $filename;
            
            // Pausa entre imágenes para evitar rate limit
            $this->imagesGenerated++;
            if ($this->imagesGenerated > 1) {
                $pauseTime = ($this->imagesGenerated % $this->maxImagesBeforePause === 0) ? 3 : 1;
                sleep($pauseTime); // Pausa de 1-3 segundos
            }
            
            $ch = curl_init($imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'QuickBite/1.0');
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            // Detectar rate limit (429) o errores del servidor
            if ($httpCode === 429) {
                error_log("🚫 RATE LIMIT alcanzado en Pollinations.ai");
                $this->rateLimitHit = true;
                return null;
            }
            
            // Verificar que sea una imagen válida
            if ($httpCode === 200 && $imageData && strpos($contentType, 'image') !== false) {
                // Verificar tamaño mínimo (evitar respuestas de error en forma de imagen)
                if (strlen($imageData) > 5000) {
                    file_put_contents($localPath, $imageData);
                    return '/public/images/platillos/' . $filename;
                } else {
                    error_log("⚠️ Imagen muy pequeña, posible error: " . strlen($imageData) . " bytes");
                }
            }
            
            error_log("⚠️ Error descargando imagen (HTTP $httpCode, Content-Type: $contentType)");
            return null;
            
        } catch (Exception $e) {
            error_log("❌ Error en downloadImageToServer: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Genera placeholder según categoría
     */
    private function getCategoryPlaceholder($categoria) {
        $placeholders = [
            'hamburguesas' => '/public/images/placeholders/burger.jpg',
            'pizzas' => '/public/images/placeholders/pizza.jpg',
            'bebidas' => '/public/images/placeholders/drink.jpg',
            'postres' => '/public/images/placeholders/dessert.jpg',
            'ensaladas' => '/public/images/placeholders/salad.jpg',
            'tacos' => '/public/images/placeholders/tacos.jpg',
            'sopas' => '/public/images/placeholders/soup.jpg',
            'default' => '/public/images/platillos/placeholder.jpg'
        ];
        
        $catLower = strtolower($categoria ?? 'default');
        foreach ($placeholders as $key => $path) {
            if (strpos($catLower, $key) !== false) {
                return $path;
            }
        }
        return $placeholders['default'];
    }
    
    /**
     * Estructura datos para base de datos y genera imágenes (optimizado)
     */
    private function structureForDatabase($data) {
        if (!empty($data['productos'])) {
            error_log("\n🎨 ===== GENERANDO IMÁGENES CON IA =====");
            
            $count = 0;
            $generated = 0;
            $maxImages = 20; // Límite más conservador para evitar rate limit
            
            foreach ($data['productos'] as &$producto) {
                $count++;
                
                // Si ya tenemos rate limit o llegamos al máximo, usar placeholders
                if ($this->rateLimitHit || $generated >= $maxImages) {
                    if (empty($producto['imagen'])) {
                        $producto['imagen'] = $this->getCategoryPlaceholder($producto['categoria'] ?? '');
                        error_log("📷 Placeholder para '{$producto['nombre']}' (rate limit)");
                    }
                    continue;
                }
                
                if (empty($producto['imagen'])) {
                    $imagenLocal = $this->generateProductImage(
                        $producto['nombre'],
                        $producto['descripcion'] ?? ''
                    );
                    
                    if ($imagenLocal) {
                        $producto['imagen'] = $imagenLocal;
                        $generated++;
                        error_log("✅ '{$producto['nombre']}' -> $imagenLocal");
                    } else {
                        // Usar placeholder según categoría
                        $producto['imagen'] = $this->getCategoryPlaceholder($producto['categoria'] ?? '');
                        error_log("⚠️ Usando placeholder para '{$producto['nombre']}'");
                    }
                }
            }
            unset($producto);
            
            $skipped = $count - $generated;
            error_log("===== FIN GENERACIÓN: $generated generadas, $skipped con placeholder =====\n");
        }
        
        return [
            'categorias' => $data['categorias'] ?? [],
            'productos' => $data['productos'] ?? [],
            'grupos_opciones' => $data['grupos_opciones'] ?? [],
            'stats' => [
                'imagenes_generadas' => $generated ?? 0,
                'rate_limit_hit' => $this->rateLimitHit
            ]
        ];
    }
    
    /**
     * Inserta en base de datos
     */
    public function insertIntoDatabase($data, $negocioId) {
        $stats = [
            'categorias' => 0,
            'productos' => 0,
            'grupos_opciones' => 0
        ];
        
        try {
            $this->pdo->beginTransaction();
            
            $stmtCat = $this->pdo->prepare(
                "INSERT INTO categorias_producto (id_negocio, nombre, descripcion) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)"
            );
            
            foreach ($data['categorias'] as $cat) {
                $stmtCat->execute([
                    $negocioId,
                    $cat['nombre'],
                    $cat['descripcion'] ?? ''
                ]);
                $stats['categorias']++;
            }
            
            $stmtProd = $this->pdo->prepare(
                "INSERT INTO productos (id_negocio, id_categoria, nombre, descripcion, precio, imagen, calorias, disponible) 
                 SELECT ?, c.id_categoria, ?, ?, ?, ?, ?, ?
                 FROM categorias_producto c 
                 WHERE c.id_negocio = ? AND c.nombre = ?
                 ON DUPLICATE KEY UPDATE 
                     descripcion = VALUES(descripcion),
                     precio = VALUES(precio),
                     imagen = VALUES(imagen),
                     calorias = VALUES(calorias),
                     disponible = VALUES(disponible)"
            );
            
            foreach ($data['productos'] as $prod) {
                $stmtProd->execute([
                    $negocioId,
                    $prod['nombre'],
                    $prod['descripcion'] ?? '',
                    $prod['precio'],
                    $prod['imagen'] ?? null,
                    $prod['calorias'] ?? null,
                    $prod['disponible'] ?? true ? 1 : 0,
                    $negocioId,
                    $prod['categoria']
                ]);
                $stats['productos']++;
            }
            
            $this->pdo->commit();
            return $stats;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}