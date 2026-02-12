<?php
/**
 * OPENAI MENU PARSER - Extractor de menús desde imágenes
 * Utiliza GPT-4o Vision para analizar imágenes de menús y convertirlas a JSON estructurado
 * 
 * Requisitos:
 * - API Key de OpenAI con acceso a GPT-4o
 * - PHP 7.4+
 * - Extension cURL habilitada
 */

class OpenAIMenuParser {
    
    private $apiKey;
    private $model = 'gpt-4o'; // Modelo con capacidad de visión
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';
    private $maxTokens = 4000;
    private $temperature = 0.2; // Baja temperatura para respuestas más consistentes
    /**
     * Constructor
     * @param string $apiKey - API Key de OpenAI
     */
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?? getenv('OPENAI_API_KEY');
        
        if (empty($this->apiKey)) {
            throw new Exception('API Key de OpenAI no configurada. Define OPENAI_API_KEY en .env o pásala al constructor.');
        }
    }
     * Parsea un menú desde una imagen
     * @param string $imagePath - Ruta local a la imagen o URL
     * @return array - Menú estructurado listo para BD
    public function parseMenuFromImage($imagePath) {
        try {
            // Validar que el archivo existe (si es ruta local)
            if (!filter_var($imagePath, FILTER_VALIDATE_URL) && !file_exists($imagePath)) {
                throw new Exception("Imagen no encontrada: $imagePath");
            }
            
            // Codificar imagen en base64
            $imageBase64 = $this->encodeImageToBase64($imagePath);
            // Determinar tipo MIME
            $mimeType = $this->getMimeType($imagePath);
            // Construir el prompt
            $prompt = $this->buildPrompt();
            // Preparar el payload para la API
            $payload = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un asistente experto en analizar menús de restaurantes. Tu tarea es extraer toda la información del menú de forma estructurada y precisa. Siempre respondes en formato JSON válido.'
                    ],
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ],
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:$mimeType;base64,$imageBase64",
                                    'detail' => 'high' // Alta resolución para mejor detección
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'response_format' => ['type' => 'json_object'] // Forzar respuesta JSON
            ];
            // Realizar petición a OpenAI
            $response = $this->makeApiRequest($payload);
            // Extraer y validar JSON
            $menuData = $this->extractAndValidateJson($response);
            // Estructurar para base de datos
            $structuredMenu = $this->structureForDatabase($menuData);
            return [
                'success' => true,
                'data' => $structuredMenu,
                'raw_response' => $menuData,
                'tokens_used' => $response['usage'] ?? null
        } catch (Exception $e) {
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
     * Codifica imagen a base64
     * @param string $imagePath
     * @return string
    private function encodeImageToBase64($imagePath) {
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            // Si es URL, descargar primero
            $imageData = @file_get_contents($imagePath);
            if ($imageData === false) {
                throw new Exception("No se pudo descargar la imagen desde URL: $imagePath");
        } else {
            // Si es archivo local
                throw new Exception("No se pudo leer el archivo: $imagePath");
        return base64_encode($imageData);
     * Obtiene el tipo MIME de la imagen
    private function getMimeType($imagePath) {
            // Para URLs, intentar detectar desde headers
            $headers = @get_headers($imagePath, 1);
            if (isset($headers['Content-Type'])) {
                return is_array($headers['Content-Type']) 
                    ? $headers['Content-Type'][0] 
                    : $headers['Content-Type'];
            return 'image/jpeg'; // Fallback
        // Para archivos locales
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $imagePath);
        finfo_close($finfo);
        return $mimeType ?: 'image/jpeg';
     * Construye el prompt para GPT-4o
    private function buildPrompt() {
        return <<<PROMPT
Analiza esta imagen de menú de restaurante y extrae TODA la información en formato JSON.
INSTRUCCIONES IMPORTANTES:
1. Detecta TODAS las categorías (Entradas, Platos Fuertes, Bebidas, Postres, etc.)
2. Para cada platillo extrae: nombre, descripción, precio, ingredientes si están visibles
3. Si hay opciones/variantes (tamaños, proteínas, etc.), inclúyelas en "opciones_dinamicas"
4. Mantén los precios EXACTOS como aparecen
5. Si no ves descripción, deja el campo vacío
6. Si hay calorías, guárdalas
FORMATO JSON REQUERIDO:
{
  "nombre_restaurante": "Nombre si está visible, sino null",
  "tipo_cocina": "Tipo de comida (italiana, mexicana, etc.) si es identificable",
  "moneda": "MXN o USD según símbolo",
  "categorias": [
    {
      "nombre": "Nombre de categoría",
      "descripcion": "Descripción de la categoría si existe",
      "productos": [
        {
          "nombre": "Nombre del platillo",
          "descripcion": "Descripción completa del platillo",
          "precio": 150.00,
          "calorias": 450,
          "ingredientes": ["ingrediente1", "ingrediente2"],
          "tiene_opciones": true,
          "opciones_dinamicas": [
            {
              "grupo_nombre": "Elige tu proteína",
              "tipo_seleccion": "unica",
              "obligatorio": true,
              "opciones": [
                {
                  "nombre": "Pollo",
                  "precio_adicional": 0.00
                },
                  "nombre": "Carne",
                  "precio_adicional": 20.00
                }
              ]
          ]
      ]
  ]
}
REGLAS:
- Todos los precios deben ser números (float)
- Si no hay opciones dinámicas, opciones_dinamicas debe ser array vacío []
- Mantén nombres exactos como aparecen en el menú
- Si algo no es visible/legible, usa null
- SOLO responde con JSON válido, sin texto adicional
Analiza la imagen y devuelve el JSON:
PROMPT;
     * Realiza petición HTTP a la API de OpenAI
     * @param array $payload
     * @return array
    private function makeApiRequest($payload) {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 120, // 2 minutos timeout
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new Exception("Error cURL: $error");
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Error desconocido';
            throw new Exception("Error API OpenAI ($httpCode): $errorMsg");
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decodificando respuesta JSON: " . json_last_error_msg());
        return $data;
     * Extrae y valida el JSON de la respuesta
     * @param array $response
    private function extractAndValidateJson($response) {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Respuesta de OpenAI sin contenido");
        $content = $response['choices'][0]['message']['content'];
        // Intentar decodificar JSON
        $menuData = json_decode($content, true);
            // Intentar limpiar el JSON (por si viene con markdown)
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $content = trim($content);
            $menuData = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON inválido en respuesta: " . json_last_error_msg() . "\nContenido: " . substr($content, 0, 500));
        // Validar estructura básica
        if (!isset($menuData['categorias']) || !is_array($menuData['categorias'])) {
            throw new Exception("JSON no contiene estructura de categorías válida");
        return $menuData;
     * Estructura el menú para inserción en base de datos
     * @param array $menuData
    private function structureForDatabase($menuData) {
        $structured = [
            'metadata' => [
                'nombre_restaurante' => $menuData['nombre_restaurante'] ?? null,
                'tipo_cocina' => $menuData['tipo_cocina'] ?? null,
                'moneda' => $menuData['moneda'] ?? 'MXN'
            'categorias' => [],
            'productos' => [],
            'grupos_opciones' => [],
            'opciones' => [],
            'estadisticas' => [
                'total_categorias' => 0,
                'total_productos' => 0,
                'total_grupos_opciones' => 0,
                'total_opciones' => 0,
                'precio_minimo' => null,
                'precio_maximo' => null,
                'precio_promedio' => null
            ]
        ];
        $precios = [];
        foreach ($menuData['categorias'] as $catIndex => $categoria) {
            // Agregar categoría
            $categoriaData = [
                'orden' => $catIndex,
                'nombre' => $categoria['nombre'],
                'descripcion' => $categoria['descripcion'] ?? ''
            $structured['categorias'][] = $categoriaData;
            $structured['estadisticas']['total_categorias']++;
            // Procesar productos de la categoría
            if (isset($categoria['productos']) && is_array($categoria['productos'])) {
                foreach ($categoria['productos'] as $prodIndex => $producto) {
                    $productoData = [
                        'categoria' => $categoria['nombre'],
                        'orden' => $prodIndex,
                        'nombre' => $producto['nombre'],
                        'descripcion' => $producto['descripcion'] ?? '',
                        'precio' => (float)($producto['precio'] ?? 0),
                        'calorias' => $producto['calorias'] ?? null,
                        'ingredientes' => $producto['ingredientes'] ?? [],
                        'tiene_opciones_dinamicas' => !empty($producto['opciones_dinamicas']),
                        'disponible' => true,
                        'destacado' => false
                    ];
                    
                    $structured['productos'][] = $productoData;
                    $structured['estadisticas']['total_productos']++;
                    if ($productoData['precio'] > 0) {
}
                        $precios[] = $productoData['precio'];
                    }
                    // Procesar opciones dinámicas
                    if (isset($producto['opciones_dinamicas']) && is_array($producto['opciones_dinamicas'])) {
                        foreach ($producto['opciones_dinamicas'] as $grupoIndex => $grupo) {
                            $grupoData = [
                                'producto' => $producto['nombre'],
                                'nombre' => $grupo['grupo_nombre'],
                                'tipo_seleccion' => $grupo['tipo_seleccion'] ?? 'unica',
                                'obligatorio' => $grupo['obligatorio'] ?? true,
                                'min_selecciones' => $grupo['min_selecciones'] ?? 0,
                                'max_selecciones' => $grupo['max_selecciones'] ?? 1,
                                'orden' => $grupoIndex,
                                'opciones' => []
                            ];
                            
                            if (isset($grupo['opciones']) && is_array($grupo['opciones'])) {
                                foreach ($grupo['opciones'] as $opIndex => $opcion) {
                                    $opcionData = [
                                        'nombre' => $opcion['nombre'],
];                                        'precio_adicional' => (float)($opcion['precio_adicional'] ?? 0),
                                        'por_defecto' => $opcion['por_defecto'] ?? false,
                                        'orden' => $opIndex
                                    ];
                                    
                                    $grupoData['opciones'][] = $opcionData;
                                    $structured['estadisticas']['total_opciones']++;
                                }
                            }
                            $structured['grupos_opciones'][] = $grupoData;
                            $structured['estadisticas']['total_grupos_opciones']++;
                        }
        // Calcular estadísticas de precios
        if (!empty($precios)) {
            $structured['estadisticas']['precio_minimo'] = min($precios);
            $structured['estadisticas']['precio_maximo'] = max($precios);
            $structured['estadisticas']['precio_promedio'] = round(array_sum($precios) / count($precios), 2);
        return $structured;
     * Inserta el menú parseado en la base de datos
     * @param PDO $db - Conexión PDO a la base de datos
     * @param int $idNegocio - ID del negocio
     * @param array $menuData - Datos del menú estructurados
     * @return array - Resultado de la inserción
    public function insertIntoDatabase($db, $idNegocio, $menuData) {
            $db->beginTransaction();
            $result = [
                'categorias_creadas' => 0,
                'productos_creados' => 0,
                'grupos_creados' => 0,
                'opciones_creadas' => 0,
                'errores' => []
            // Mapeo de categorías: nombre => id
            $categoriasMap = [];
            // 1. Insertar categorías
            foreach ($menuData['categorias'] as $cat) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO categorias_producto (id_negocio, nombre, descripcion, orden)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $idNegocio,
                        $cat['nombre'],
                        $cat['descripcion'],
                        $cat['orden']
                    ]);
                    $categoriasMap[$cat['nombre']] = $db->lastInsertId();
                    $result['categorias_creadas']++;
                } catch (Exception $e) {
                    $result['errores'][] = "Error creando categoría '{$cat['nombre']}': " . $e->getMessage();
            // Mapeo de productos: nombre => id
            $productosMap = [];
            // 2. Insertar productos
            foreach ($menuData['productos'] as $prod) {
                    $idCategoria = $categoriasMap[$prod['categoria']] ?? null;
                        INSERT INTO productos 
                        (id_negocio, id_categoria, nombre, descripcion, precio, calorias, 
                         disponible, destacado, tiene_opciones_dinamicas, orden_visualizacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        $idCategoria,
                        $prod['nombre'],
                        $prod['descripcion'],
                        $prod['precio'],
                        $prod['calorias'],
                        $prod['disponible'] ? 1 : 0,
                        $prod['destacado'] ? 1 : 0,
                        $prod['tiene_opciones_dinamicas'] ? 1 : 0,
                        $prod['orden']
                    $productosMap[$prod['nombre']] = $db->lastInsertId();
                    $result['productos_creados']++;
                    $result['errores'][] = "Error creando producto '{$prod['nombre']}': " . $e->getMessage();
            // 3. Insertar grupos de opciones y sus opciones
            foreach ($menuData['grupos_opciones'] as $grupo) {
                    $idProducto = $productosMap[$grupo['producto']] ?? null;
                    if (!$idProducto) {
                        throw new Exception("Producto no encontrado: {$grupo['producto']}");
                        INSERT INTO grupos_opciones 
                        (id_producto, nombre, tipo_seleccion, obligatorio, 
                         min_selecciones, max_selecciones, orden_visualizacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        $idProducto,
                        $grupo['nombre'],
                        $grupo['tipo_seleccion'],
                        $grupo['obligatorio'] ? 1 : 0,
                        $grupo['min_selecciones'],
                        $grupo['max_selecciones'],
                        $grupo['orden']
                    $idGrupo = $db->lastInsertId();
                    $result['grupos_creados']++;
                    // Insertar opciones del grupo
                    foreach ($grupo['opciones'] as $opcion) {
                        $stmt = $db->prepare("
                            INSERT INTO opciones 
                            (id_grupo_opcion, nombre, precio_adicional, por_defecto, orden_visualizacion)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $idGrupo,
                            $opcion['nombre'],
                            $opcion['precio_adicional'],
                            $opcion['por_defecto'] ? 1 : 0,
                            $opcion['orden']
                        ]);
                        $result['opciones_creadas']++;
                    $result['errores'][] = "Error creando grupo '{$grupo['nombre']}': " . $e->getMessage();
            $db->commit();
            $result['success'] = true;
            $result['mensaje'] = "Menú importado exitosamente";
            return $result;
            $db->rollBack();
// ============================================
// EJEMPLO DE USO
if (php_sapi_name() === 'cli') {
    echo "=== OPENAI MENU PARSER - DEMO ===\n\n";
    // Configuración
    $apiKey = 'tu-api-key-aqui'; // O usar getenv('OPENAI_API_KEY')
    if ($apiKey === 'tu-api-key-aqui') {
        echo "⚠️  CONFIGURA TU API KEY DE OPENAI\n";
        echo "Edita el archivo y reemplaza 'tu-api-key-aqui' con tu API key real\n";
        echo "O configura la variable de entorno: export OPENAI_API_KEY='sk-...'\n\n";
        exit(1);
    // Ejemplo de uso
    try {
        $parser = new OpenAIMenuParser($apiKey);
        // Ruta de la imagen (puede ser local o URL)
        $imagePath = $argv[1] ?? 'menu_ejemplo.jpg';
        echo "Analizando imagen: $imagePath\n";
        echo "Esto puede tomar 30-60 segundos...\n\n";
        // Parsear menú
        $result = $parser->parseMenuFromImage($imagePath);
        if ($result['success']) {
            echo "✅ Menú parseado exitosamente!\n\n";
            echo "ESTADÍSTICAS:\n";
}
            $stats = $result['data']['estadisticas'];
            echo "- Categorías: {$stats['total_categorias']}\n";
            echo "- Productos: {$stats['total_productos']}\n";
            echo "- Grupos de opciones: {$stats['total_grupos_opciones']}\n";
            echo "- Opciones totales: {$stats['total_opciones']}\n";
            if ($stats['precio_minimo']) {
                echo "- Precio mínimo: \${$stats['precio_minimo']}\n";
                echo "- Precio máximo: \${$stats['precio_maximo']}\n";
                echo "- Precio promedio: \${$stats['precio_promedio']}\n";
            echo "\nTOKENS USADOS:\n";
            if ($result['tokens_used']) {
                echo "- Prompt: {$result['tokens_used']['prompt_tokens']}\n";
                echo "- Completion: {$result['tokens_used']['completion_tokens']}\n";
                echo "- Total: {$result['tokens_used']['total_tokens']}\n";
            echo "\n--- JSON GENERADO ---\n";
            echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "\n";
            // Guardar resultado en archivo
            $outputFile = 'menu_parseado_' . date('Y-m-d_His') . '.json';
            file_put_contents($outputFile, json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "\n💾 Resultado guardado en: $outputFile\n";
            echo "❌ Error parseando menú:\n";
            echo $result['error'] . "\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
?>
