<?php
// mandados_modificado.php - Sistema de mandados: Primero seleccionar tienda, luego agregar productos específicos
session_start();

// Verificar si el carrito existe, sino inicializarlo
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [
        'items' => [],
        'tienda_seleccionada' => null,
        'info_tienda' => null,
        'subtotal' => 0,
        'total' => 0
    ];
}

// Habilitar visualización de errores para depuración
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

if (!$usuario_logueado) {
    header("Location: login.php?redirect=mandados.php");
    exit;
}

// Obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

// CONFIGURACIÓN DE APIs
$GOOGLE_API_KEY = getenv('GOOGLE_MAPS_API_KEY') ?: '';

// FUNCIÓN: Buscar tiendas reales con Google Places API
function buscarTiendasGooglePlaces($lat, $lng, $radio = 10000) {
    global $GOOGLE_API_KEY;
    
    $tipos = [
        'supermarket',
        'grocery_or_supermarket', 
        'convenience_store',
        'pharmacy',
        'store'
    ];
    
    $tiendas = [];
    
    foreach ($tipos as $tipo) {
        $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json";
        $params = [
            'location' => $lat . ',' . $lng,
            'radius' => $radio,
            'type' => $tipo,
            'key' => $GOOGLE_API_KEY,
            'language' => 'es'
        ];
        
        $url_completa = $url . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_completa);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: QuickBite-Mandados/1.0 (contacto@quickbite.mx)'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $http_code == 200) {
            $data = json_decode($response, true);
            if ($data['status'] === 'OK' && isset($data['results'])) {
                foreach ($data['results'] as $lugar) {
                    $lat_tienda = $lugar['geometry']['location']['lat'];
                    $lng_tienda = $lugar['geometry']['location']['lng'];
                    
                    $distancia = calcularDistancia($lat, $lng, $lat_tienda, $lng_tienda);
                    
                    $tiendas[] = [
                        'place_id' => $lugar['place_id'],
                        'nombre' => $lugar['name'],
                        'direccion' => $lugar['vicinity'] ?? 'Sin dirección',
                        'rating' => $lugar['rating'] ?? 0,
                        'distancia' => $distancia,
                        'tipos' => $lugar['types'] ?? [],
                        'abierto_ahora' => isset($lugar['opening_hours']['open_now']) ? 
                                          ($lugar['opening_hours']['open_now'] ? 'Abierto' : 'Cerrado') : 
                                          'No disponible',
                        'latitud' => $lat_tienda,
                        'longitud' => $lng_tienda,
                        'tipo_negocio' => determinarTipoNegocio($lugar['types'] ?? []),
                        'es_cadena_conocida' => esCadenaConocida($lugar['name']),
                        'precio_nivel' => $lugar['price_level'] ?? 2
                    ];
                }
            }
        }
        
        usleep(200000); // 0.2 segundos de delay
    }
    
    // Eliminar duplicados y ordenar
    $tiendas_unicas = eliminarDuplicados($tiendas);
    
    usort($tiendas_unicas, function($a, $b) {
        if ($a['es_cadena_conocida'] != $b['es_cadena_conocida']) {
            return $b['es_cadena_conocida'] - $a['es_cadena_conocida'];
        }
        return $a['distancia'] <=> $b['distancia'];
    });
    
    return array_slice($tiendas_unicas, 0, 20);
}

// Categorías de productos más comunes en México
function obtenerCategoriasSugeridas() {
    return [
        'Bebidas' => [
            'Coca-Cola sin azúcar 600ml',
            'Pepsi 2L',
            'Agua Bonafont 1.5L',
            'Cerveza Corona 355ml (6 pack)',
            'Jugo Del Valle Naranja 1L',
            'Leche Lala entera 1L',
            'Café Nescafé clásico 200g'
        ],
        'Alimentos básicos' => [
            'Pan Bimbo blanco grande',
            'Arroz Verde Valle 1kg',
            'Frijoles La Costeña 580g',
            'Aceite Capullo 1L',
            'Sal La Fina 1kg',
            'Azúcar estándar 1kg',
            'Huevos blancos 12 piezas'
        ],
        'Carnes y lácteos' => [
            'Pollo entero fresco 1kg',
            'Carne molida res 500g',
            'Queso Oaxaca Lala 400g',
            'Jamón FUD rebanado 200g',
            'Yogurt Danone natural 1L',
            'Mantequilla Lala 90g'
        ],
        'Limpieza' => [
            'Detergente Ariel en polvo 1kg',
            'Suavitel aroma original 1L',
            'Jabón Zote rosa 200g',
            'Papel higiénico Pétalo 4 rollos',
            'Cloro Cloralex 1L'
        ],
        'Cuidado personal' => [
            'Champú Head & Shoulders 400ml',
            'Pasta dental Colgate 75ml',
            'Jabón Dove belleza 90g',
            'Desodorante Rexona 150ml'
        ]
    ];
}

// Función para estimar precio basado en el producto
function estimarPrecioProducto($descripcion_producto) {
    $descripcion_lower = strtolower($descripcion_producto);
    
    // Patrones de precios aproximados en pesos mexicanos
    $patrones_precio = [
        // Bebidas
        '/coca.*(355|600)ml/i' => [15, 22],
        '/coca.*2.*l/i' => [30, 40],
        '/pepsi.*(355|600)ml/i' => [14, 20],
        '/cerveza.*355ml/i' => [18, 25],
        '/cerveza.*6.*pack/i' => [90, 140],
        '/agua.*1\.?5l/i' => [15, 25],
        '/leche.*1l/i' => [22, 28],
        '/cafe.*200g/i' => [45, 65],
        
        // Alimentos básicos
        '/pan.*bimbo/i' => [25, 35],
        '/arroz.*1kg/i' => [18, 28],
        '/frijoles.*580g/i' => [20, 30],
        '/aceite.*1l/i' => [35, 50],
        '/sal.*1kg/i' => [8, 15],
        '/azucar.*1kg/i' => [20, 30],
        '/huevos.*12/i' => [30, 45],
        
        // Carnes y lácteos
        '/pollo.*1kg/i' => [60, 85],
        '/carne.*molida.*500g/i' => [50, 75],
        '/queso.*400g/i' => [45, 70],
        '/jamon.*200g/i' => [35, 55],
        '/yogurt.*1l/i' => [25, 40],
        '/mantequilla.*90g/i' => [18, 28],
        
        // Limpieza
        '/detergente.*1kg/i' => [35, 55],
        '/suavitel.*1l/i' => [25, 40],
        '/jabon.*zote/i' => [12, 20],
        '/papel.*higienico.*4/i' => [35, 55],
        '/cloro.*1l/i' => [15, 25],
        
        // Cuidado personal
        '/champu.*400ml/i' => [45, 70],
        '/pasta.*dental.*75ml/i' => [20, 35],
        '/jabon.*dove.*90g/i' => [15, 25],
        '/desodorante.*150ml/i' => [35, 55]
    ];
    
    foreach ($patrones_precio as $patron => $rango) {
        if (preg_match($patron, $descripcion_lower)) {
            $precio = rand($rango[0] * 100, $rango[1] * 100) / 100;
            return round($precio * 2) / 2; // Redondear a 0.50
        }
    }
    
    // Precio por defecto si no coincide con ningún patrón
    return rand(1000, 8000) / 100; // Entre $10 y $80
}

// Funciones auxiliares (iguales que antes)
function calcularDistancia($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371;
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    
    $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return round($earth_radius * $c, 2);
}

function eliminarDuplicados($tiendas) {
    $unicos = [];
    $nombres_vistos = [];
    
    foreach ($tiendas as $tienda) {
        $nombre_normalizado = strtolower(trim($tienda['nombre']));
        
        $es_duplicado = false;
        foreach ($nombres_vistos as $nombre_previo => $info_previa) {
            $similitud = similar_text($nombre_normalizado, $nombre_previo);
            $distancia_entre_lugares = calcularDistancia(
                $tienda['latitud'], $tienda['longitud'],
                $info_previa['lat'], $info_previa['lng']
            );
            
            if ($similitud > 15 && $distancia_entre_lugares < 0.2) {
                $es_duplicado = true;
                break;
            }
        }
        
        if (!$es_duplicado) {
            $unicos[] = $tienda;
            $nombres_vistos[$nombre_normalizado] = [
                'lat' => $tienda['latitud'],
                'lng' => $tienda['longitud']
            ];
        }
    }
    
    return $unicos;
}

function determinarTipoNegocio($tipos_google) {
    $mapeo = [
        'supermarket' => 'Supermercado',
        'grocery_or_supermarket' => 'Supermercado',
        'convenience_store' => 'Tienda de conveniencia',
        'pharmacy' => 'Farmacia',
        'gas_station' => 'Gasolinera',
        'store' => 'Tienda',
        'shopping_mall' => 'Centro comercial',
        'department_store' => 'Tienda departamental'
    ];
    
    foreach ($tipos_google as $tipo) {
        if (isset($mapeo[$tipo])) {
            return $mapeo[$tipo];
        }
    }
    
    return 'Tienda';
}

function esCadenaConocida($nombre) {
    $cadenas_mexicanas = [
        'walmart', 'bodega aurrera', 'superama', 'sam\'s club',
        'soriana', 'mega soriana', 'soriana hiper',
        'chedraui', 'super chedraui', 'selecto chedraui',
        'comercial mexicana', 'mega comercial mexicana',
        'heb', 'mi tienda del ahorro',
        'la comer', 'fresko', 'sumesa',
        'casa ley', 'ley',
        'oxxo', 'seven eleven', '7-eleven',
        'farmacias guadalajara', 'farmacia san pablo', 'farmacias del ahorro'
    ];
    
    $nombre_lower = strtolower($nombre);
    foreach ($cadenas_mexicanas as $cadena) {
        if (strpos($nombre_lower, $cadena) !== false) {
            return true;
        }
    }
    
    return false;
}

// MANEJAR PETICIONES AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'buscar_tiendas':
            $lat = floatval($input['lat'] ?? 21.8818);
            $lng = floatval($input['lng'] ?? -102.2916);
            $radio = intval($input['radio'] ?? 10) * 1000;
            
            try {
                $tiendas = buscarTiendasGooglePlaces($lat, $lng, $radio);
                echo json_encode(['success' => true, 'tiendas' => $tiendas]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'seleccionar_tienda':
            $place_id = $input['place_id'] ?? '';
            $info_tienda = $input['info_tienda'] ?? [];
            
            try {
                $_SESSION['carrito']['tienda_seleccionada'] = $place_id;
                $_SESSION['carrito']['info_tienda'] = $info_tienda;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Tienda seleccionada correctamente',
                    'tienda' => $info_tienda['nombre']
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'agregar_producto_manual':
            $descripcion = trim($input['descripcion'] ?? '');
            $cantidad = intval($input['cantidad'] ?? 1);
            $precio_estimado = floatval($input['precio_estimado'] ?? 0);
            $notas = trim($input['notas'] ?? '');
            
            try {
                if (empty($descripcion)) {
                    throw new Exception('La descripción del producto es obligatoria');
                }
                
                if (!$_SESSION['carrito']['tienda_seleccionada']) {
                    throw new Exception('Debes seleccionar una tienda primero');
                }
                
                if ($cantidad < 1) {
                    throw new Exception('La cantidad debe ser mayor a 0');
                }
                
                // Generar ID único para el producto
                $producto_id = 'manual_' . time() . '_' . rand(1000, 9999);
                
                // Si no hay precio estimado, calcularlo automáticamente
                if ($precio_estimado <= 0) {
                    $precio_estimado = estimarPrecioProducto($descripcion);
                }
                
                $subtotal = $precio_estimado * $cantidad;
                
                // Agregar producto al carrito
                $_SESSION['carrito']['items'][] = [
                    'id' => $producto_id,
                    'descripcion' => $descripcion,
                    'cantidad' => $cantidad,
                    'precio_estimado' => $precio_estimado,
                    'subtotal' => $subtotal,
                    'notas' => $notas,
                    'tipo' => 'manual',
                    'fecha_agregado' => date('Y-m-d H:i:s')
                ];
                
                // Recalcular totales
                $_SESSION['carrito']['subtotal'] = 0;
                foreach ($_SESSION['carrito']['items'] as $item) {
                    $_SESSION['carrito']['subtotal'] += $item['subtotal'];
                }
                $_SESSION['carrito']['total'] = $_SESSION['carrito']['subtotal'];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Producto agregado al carrito',
                    'carrito_count' => count($_SESSION['carrito']['items']),
                    'carrito_total' => $_SESSION['carrito']['total']
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'obtener_sugerencias':
            $categorias = obtenerCategoriasSugeridas();
            echo json_encode(['success' => true, 'categorias' => $categorias]);
            break;
            
        case 'obtener_carrito_info':
            echo json_encode([
                'success' => true,
                'carrito_count' => count($_SESSION['carrito']['items']),
                'carrito_total' => $_SESSION['carrito']['total'],
                'tienda_seleccionada' => $_SESSION['carrito']['info_tienda']['nombre'] ?? null
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    exit;
}

$cantidad_carrito = count($_SESSION['carrito']['items']);
$tienda_actual = $_SESSION['carrito']['info_tienda'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mandados Personalizados - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0066cc;
            --primary-dark: #004499;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
            --accent: #8b5cf6;
            --gradient: linear-gradient(135deg, #0066cc 0%, #004499 50%, #8b5cf6 100%);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--dark);
            line-height: 1.6;
            padding-bottom: 80px;
        }

        .header {
            background: var(--gradient);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-lg);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
        }

        .logo {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tienda-actual {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .cart-icon {
            position: relative;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            text-decoration: none;
            padding: 0.75rem;
            border-radius: 12px;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .cart-icon:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid white;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .hero-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
        }

        .hero-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .hero-title h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .step.active {
            background: var(--primary);
            color: white;
        }

        .step.completed {
            background: var(--success);
            color: white;
        }

        .step.pending {
            background: #e2e8f0;
            color: var(--secondary);
        }

        .step-separator {
            width: 30px;
            height: 2px;
            background: #e2e8f0;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .content-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            height: fit-content;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tiendas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .tienda-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .tienda-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .tienda-card.selected {
            border-color: var(--success);
            background: #f0fdf4;
        }

        .tienda-card.chain {
            border-color: var(--primary);
            background: #f8faff;
        }

        .tienda-nombre {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .tienda-tipo {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .tienda-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .tienda-direccion {
            color: var(--secondary);
            font-size: 0.8rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .select-tienda-btn {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .select-tienda-btn:hover {
            background: var(--primary-dark);
        }

        .select-tienda-btn:disabled {
            background: var(--success);
            cursor: not-allowed;
        }

        .chain-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .producto-form {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .precio-estimado {
            background: #e0f2fe;
            border: 1px solid #0ea5e9;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 0.5rem;
            color: #0c4a6e;
            font-weight: 500;
        }

        .cantidad-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cantidad-btn {
            width: 40px;
            height: 40px;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .cantidad-btn:hover {
            background: var(--primary);
            color: white;
        }

        .cantidad-input {
            width: 80px;
            text-align: center;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .agregar-btn {
            width: 100%;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .agregar-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .agregar-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .sugerencias-section {
            margin-top: 2rem;
        }

        .categorias-grid {
            display: grid;
            gap: 1rem;
        }

        .categoria-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .categoria-header {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .categoria-content {
            padding: 1rem;
            display: none;
        }

        .categoria-content.show {
            display: block;
        }

        .producto-sugerido {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .producto-sugerido:hover {
            background: #e0f2fe;
            border-color: var(--primary);
        }

        .producto-sugerido-nombre {
            font-weight: 500;
            color: var(--dark);
        }

        .usar-sugerencia-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .sidebar {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            height: fit-content;
            box-shadow: var(--shadow);
            position: sticky;
            top: 120px;
        }

        .carrito-resumen {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .carrito-item {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }

        .carrito-item-nombre {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .carrito-item-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .carrito-total {
            background: var(--primary);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .info-banner {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .info-banner h3 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .notification {
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1001;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            max-width: 350px;
        }

        .notification.success { background: var(--success); color: white; }
        .notification.error { background: var(--danger); color: white; }
        .notification.warning { background: var(--warning); color: white; }
        .notification.info { background: var(--primary); color: white; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-top: 1px solid #e2e8f0;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--secondary);
            text-decoration: none;
            padding: 8px 12px;
            transition: all 0.3s;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 12px;
            min-width: 60px;
        }

        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }

        .nav-item.active {
            color: var(--primary);
            background: rgba(0, 102, 204, 0.1);
        }

        .central-btn {
            background: var(--gradient);
            color: white;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            border: 3px solid white;
            position: relative;
            top: -16px;
            margin: 0 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .central-btn:hover {
            color: white;
            transform: translateY(-2px);
        }

        .ejemplos-banner {
            background: linear-gradient(45deg, #8b5cf6, #a855f7);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
        }

        .ejemplos-banner h4 {
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .ejemplos-lista {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .ejemplo-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .tiendas-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title h1 {
                font-size: 2rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .step-indicator {
                flex-direction: column;
                gap: 0.5rem;
            }

            .step-separator {
                display: none;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-clipboard-list"></i>
                QuickBite Mandados
            </div>
            
            <?php if ($tienda_actual): ?>
            <div class="tienda-actual">
                <i class="fas fa-store"></i> <?php echo htmlspecialchars($tienda_actual['nombre']); ?>
            </div>
            <?php endif; ?>
            
            <a href="carrito.php" class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cart-count"><?php echo $cantidad_carrito; ?></span>
            </a>
        </div>
    </header>

    <div class="container">
        <div class="hero-section fade-in">
            <div class="hero-title">
                <h1>🛒 Mandados Personalizados</h1>
                <p>Selecciona una tienda real y agrega productos específicos para tu mandado</p>
            </div>

            <div class="info-banner">
                <h3><i class="fas fa-lightbulb"></i> ¿Cómo funciona?</h3>
                <p><strong>1.</strong> Encuentra tiendas reales cerca de ti con Google Places &nbsp;|&nbsp; <strong>2.</strong> Selecciona una tienda &nbsp;|&nbsp; <strong>3.</strong> Agrega productos específicos manualmente</p>
            </div>

            <div class="step-indicator">
                <div class="step" id="step-1">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Seleccionar Tienda</span>
                </div>
                <div class="step-separator"></div>
                <div class="step pending" id="step-2">
                    <i class="fas fa-plus"></i>
                    <span>Agregar Productos</span>
                </div>
                <div class="step-separator"></div>
                <div class="step pending" id="step-3">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Revisar Carrito</span>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="content-section">
                <!-- Sección 1: Seleccionar Tienda -->
                <div id="seccion-tiendas">
                    <h2 class="section-title">
                        <i class="fas fa-store"></i>
                        Tiendas Cercanas (Google Places API)
                    </h2>
                    
                    <div id="tiendas-container">
                        <div class="loading">
                            <i class="fas fa-spinner"></i>
                            <p>Obteniendo ubicación y buscando tiendas...</p>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Agregar Productos (oculta inicialmente) -->
                <div id="seccion-productos" style="display: none;">
                    <h2 class="section-title">
                        <i class="fas fa-plus-circle"></i>
                        Agregar Productos Específicos
                    </h2>

                    <div class="ejemplos-banner">
                        <h4><i class="fas fa-star"></i> Sé específico con tus productos</h4>
                        <div class="ejemplos-lista">
                            <div class="ejemplo-item">• Coca-Cola sin azúcar 600ml</div>
                            <div class="ejemplo-item">• Pan Bimbo blanco grande</div>
                            <div class="ejemplo-item">• Leche Lala entera 1L</div>
                            <div class="ejemplo-item">• Huevos blancos 12 piezas</div>
                            <div class="ejemplo-item">• Cerveza Corona 355ml (6 pack)</div>
                            <div class="ejemplo-item">• Detergente Ariel 1kg</div>
                        </div>
                    </div>

                    <div class="producto-form">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-tag"></i> Descripción específica del producto *
                            </label>
                            <input 
                                type="text" 
                                id="producto-descripcion" 
                                class="form-input"
                                placeholder="Ej: Coca-Cola sin azúcar 600ml, Pan Bimbo blanco grande, etc."
                                autocomplete="off">
                            <div class="precio-estimado" id="precio-estimado" style="display: none;">
                                <i class="fas fa-calculator"></i> Precio estimado: $<span id="precio-valor">0.00</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-sort-numeric-up"></i> Cantidad
                            </label>
                            <div class="cantidad-controls">
                                <button class="cantidad-btn" onclick="cambiarCantidad(-1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="producto-cantidad" class="cantidad-input" value="1" min="1" max="50">
                                <button class="cantidad-btn" onclick="cambiarCantidad(1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-sticky-note"></i> Notas adicionales (opcional)
                            </label>
                            <textarea 
                                id="producto-notas" 
                                class="form-input form-textarea"
                                placeholder="Ej: Si no hay, buscar marca similar, preferir producto orgánico, etc."></textarea>
                        </div>

                        <button class="agregar-btn" onclick="agregarProducto()">
                            <i class="fas fa-cart-plus"></i>
                            Agregar al Carrito
                        </button>
                    </div>

                    <div class="sugerencias-section">
                        <h3 class="section-title">
                            <i class="fas fa-lightbulb"></i>
                            Productos Sugeridos por Categoría
                        </h3>
                        <div id="categorias-container"></div>
                    </div>
                </div>
            </div>

            <div class="sidebar">
                <h3 class="section-title">
                    <i class="fas fa-shopping-cart"></i>
                    Resumen del Pedido
                </h3>

                <div id="carrito-info">
                    <div class="carrito-resumen">
                        <div style="text-align: center; color: var(--secondary); padding: 2rem;">
                            <i class="fas fa-shopping-cart" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <p>Tu carrito está vacío</p>
                            <p style="font-size: 0.9rem;">Selecciona una tienda y agrega productos</p>
                        </div>
                    </div>
                </div>

                <div id="info-tienda-seleccionada" style="display: none;">
                    <h4 style="margin-bottom: 1rem; color: var(--primary);">
                        <i class="fas fa-store"></i> Tienda Seleccionada
                    </h4>
                    <div id="tienda-info-card"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navegación inferior -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Inicio</span>
        </a>
        <a href="buscar.php" class="nav-item">
            <i class="fas fa-search"></i>
            <span>Buscar</span>
        </a>
        <a href="carrito.php" class="central-btn">
            <i class="fas fa-shopping-cart"></i>
        </a>
        <a href="favoritos.php" class="nav-item">
            <i class="fas fa-heart"></i>
            <span>Favoritos</span>
        </a>
        <a href="perfil.php" class="nav-item active">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
    </nav>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let ubicacionUsuario = null;
        let tiendasDisponibles = [];
        let tiendaSeleccionada = null;
        let categoriasProductos = {};

        // Inicializar la aplicación
        document.addEventListener('DOMContentLoaded', function() {
            inicializarEventos();
            obtenerUbicacion();
            cargarSugerenciasProductos();
            actualizarEstadoCarrito();
        });

        function inicializarEventos() {
            // Evento para descripción del producto (calcular precio estimado)
            document.getElementById('producto-descripcion').addEventListener('input', function() {
                const descripcion = this.value.trim();
                if (descripcion.length > 3) {
                    calcularPrecioEstimado(descripcion);
                } else {
                    document.getElementById('precio-estimado').style.display = 'none';
                }
            });

            // Enter para agregar producto
            document.getElementById('producto-descripcion').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    agregarProducto();
                }
            });
        }

        // Obtener ubicación del usuario
        function obtenerUbicacion() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        ubicacionUsuario = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        buscarTiendas();
                    },
                    function(error) {
                        console.error('Error de ubicación:', error);
                        // Usar ubicación por defecto (Aguascalientes)
                        ubicacionUsuario = { lat: 21.8818, lng: -102.2916 };
                        buscarTiendas();
                        mostrarNotificacion('Usando ubicación predeterminada (Aguascalientes)', 'warning');
                    }
                );
            } else {
                ubicacionUsuario = { lat: 21.8818, lng: -102.2916 };
                buscarTiendas();
                mostrarNotificacion('Geolocalización no disponible, usando ubicación predeterminada', 'warning');
            }
        }

        // Buscar tiendas con Google Places API
        function buscarTiendas() {
            if (!ubicacionUsuario) return;

            const container = document.getElementById('tiendas-container');
            container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><p>Buscando tiendas con Google Places API...</p></div>';

            $.ajax({
                url: 'mandados.php',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: JSON.stringify({
                    action: 'buscar_tiendas',
                    lat: ubicacionUsuario.lat,
                    lng: ubicacionUsuario.lng,
                    radio: 10
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.tiendas && data.tiendas.length > 0) {
                        tiendasDisponibles = data.tiendas;
                        mostrarTiendas(data.tiendas);
                    } else {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 3rem; color: var(--secondary);">
                                <i class="fas fa-store-slash" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                                <h4>No se encontraron tiendas</h4>
                                <p>Intenta ampliar el radio de búsqueda o verifica tu ubicación</p>
                            </div>
                        `;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error Google Places:', error);
                    container.innerHTML = `
                        <div style="text-align: center; padding: 3rem; color: var(--danger);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <h4>Error en Google Places API</h4>
                            <p>Verifica la configuración de la API</p>
                        </div>
                    `;
                }
            });
        }

        // Mostrar tiendas disponibles
        function mostrarTiendas(tiendas) {
            const container = document.getElementById('tiendas-container');
            
            const tiendasHTML = tiendas.map(tienda => `
                <div class="tienda-card ${tienda.es_cadena_conocida ? 'chain' : ''}" data-place-id="${tienda.place_id}">
                    ${tienda.es_cadena_conocida ? '<span class="chain-badge">Cadena</span>' : ''}
                    
                    <div class="tienda-nombre">${tienda.nombre}</div>
                    <div class="tienda-tipo">${tienda.tipo_negocio}</div>
                    <div class="tienda-direccion">${tienda.direccion}</div>
                    
                    <div class="tienda-meta">
                        <span><i class="fas fa-map-marker-alt"></i> ${tienda.distancia} km</span>
                        <span>
                            ${tienda.rating > 0 ? 
                                `<i class="fas fa-star" style="color: #fbbf24;"></i> ${tienda.rating.toFixed(1)}` : 
                                'Sin rating'
                            }
                        </span>
                    </div>
                    
                    <div style="font-size: 0.85rem; margin-bottom: 1rem; color: ${
                        tienda.abierto_ahora === 'Abierto' ? 'var(--success)' : 
                        tienda.abierto_ahora === 'Cerrado' ? 'var(--danger)' : 
                        'var(--secondary)'
                    };">
                        <i class="fas fa-clock"></i> ${tienda.abierto_ahora}
                    </div>
                    
                    <button class="select-tienda-btn" onclick="seleccionarTienda('${tienda.place_id}')">
                        <i class="fas fa-check"></i> Seleccionar Tienda
                    </button>
                </div>
            `).join('');

            container.innerHTML = `
                <div style="margin-bottom: 1.5rem; color: var(--primary); font-weight: 600;">
                    <i class="fas fa-google"></i> ${tiendas.length} tiendas encontradas via Google Places API
                </div>
                <div class="tiendas-grid">${tiendasHTML}</div>
            `;
        }

        // Seleccionar tienda
        function seleccionarTienda(placeId) {
            const tienda = tiendasDisponibles.find(t => t.place_id === placeId);
            if (!tienda) return;

            // Actualizar UI
            document.querySelectorAll('.tienda-card').forEach(card => {
                card.classList.remove('selected');
                const btn = card.querySelector('.select-tienda-btn');
                btn.innerHTML = '<i class="fas fa-check"></i> Seleccionar Tienda';
                btn.disabled = false;
            });

            const cardSeleccionada = document.querySelector(`[data-place-id="${placeId}"]`);
            cardSeleccionada.classList.add('selected');
            const btnSeleccionado = cardSeleccionada.querySelector('.select-tienda-btn');
            btnSeleccionado.innerHTML = '<i class="fas fa-check-circle"></i> Tienda Seleccionada';
            btnSeleccionado.disabled = true;

            // Guardar en sesión
            $.ajax({
                url: 'mandados.php',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: JSON.stringify({
                    action: 'seleccionar_tienda',
                    place_id: placeId,
                    info_tienda: tienda
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        tiendaSeleccionada = tienda;
                        mostrarNotificacion(`${tienda.nombre} seleccionada correctamente`, 'success');
                        
                        // Activar paso 2
                        document.getElementById('step-1').classList.add('completed');
                        document.getElementById('step-1').classList.remove('active');
                        document.getElementById('step-2').classList.add('active');
                        document.getElementById('step-2').classList.remove('pending');
                        
                        // Mostrar sección de productos
                        document.getElementById('seccion-productos').style.display = 'block';
                        document.getElementById('seccion-productos').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                        
                        // Actualizar sidebar
                        actualizarInfoTienda(tienda);
                        
                        // Enfocar input de producto
                        setTimeout(() => {
                            document.getElementById('producto-descripcion').focus();
                        }, 500);
                    }
                },
                error: function() {
                    mostrarNotificacion('Error al seleccionar tienda', 'error');
                }
            });
        }

        // Actualizar información de tienda en sidebar
        function actualizarInfoTienda(tienda) {
            const container = document.getElementById('tienda-info-card');
            const section = document.getElementById('info-tienda-seleccionada');
            
            container.innerHTML = `
                <div style="background: #f8fafc; border-radius: 12px; padding: 1rem; border: 2px solid var(--success);">
                    <div style="font-weight: 600; color: var(--dark); margin-bottom: 0.5rem;">
                        ${tienda.nombre}
                    </div>
                    <div style="font-size: 0.9rem; color: var(--secondary); margin-bottom: 0.5rem;">
                        ${tienda.tipo_negocio}
                    </div>
                    <div style="font-size: 0.85rem; color: var(--secondary);">
                        <i class="fas fa-map-marker-alt"></i> ${tienda.distancia} km
                        ${tienda.rating > 0 ? ` • <i class="fas fa-star" style="color: #fbbf24;"></i> ${tienda.rating.toFixed(1)}` : ''}
                    </div>
                </div>
            `;
            
            section.style.display = 'block';
        }

        // Calcular precio estimado del producto
        function calcularPrecioEstimado(descripcion) {
            // Esta función simula el cálculo que se hace en el servidor
            const desc = descripcion.toLowerCase();
            let precio = 25.00; // Precio base
            
            // Ajustar precio según patrones
            if (desc.includes('coca') || desc.includes('pepsi')) {
                if (desc.includes('600ml') || desc.includes('355ml')) precio = 18.50;
                else if (desc.includes('2l') || desc.includes('2.5l')) precio = 35.00;
            } else if (desc.includes('cerveza')) {
                if (desc.includes('6 pack') || desc.includes('6pack')) precio = 120.00;
                else precio = 19.50;
            } else if (desc.includes('leche') && desc.includes('1l')) {
                precio = 25.00;
            } else if (desc.includes('pan') && desc.includes('bimbo')) {
                precio = 28.50;
            } else if (desc.includes('huevos')) {
                precio = 38.00;
            } else if (desc.includes('detergente')) {
                precio = 45.00;
            } else if (desc.includes('arroz') && desc.includes('1kg')) {
                precio = 22.00;
            } else if (desc.includes('aceite') && desc.includes('1l')) {
                precio = 42.00;
            }
            
            // Mostrar precio estimado
            document.getElementById('precio-valor').textContent = precio.toFixed(2);
            document.getElementById('precio-estimado').style.display = 'block';
            
            return precio;
        }

        // Cambiar cantidad
        function cambiarCantidad(cambio) {
            const input = document.getElementById('producto-cantidad');
            let nuevaCantidad = parseInt(input.value) + cambio;
            
            if (nuevaCantidad < 1) nuevaCantidad = 1;
            if (nuevaCantidad > 50) nuevaCantidad = 50;
            
            input.value = nuevaCantidad;
        }

        // Agregar producto al carrito
        function agregarProducto() {
            const descripcion = document.getElementById('producto-descripcion').value.trim();
            const cantidad = parseInt(document.getElementById('producto-cantidad').value);
            const notas = document.getElementById('producto-notas').value.trim();
            const precioEstimado = parseFloat(document.getElementById('precio-valor').textContent || '0');

            if (!descripcion) {
                mostrarNotificacion('Por favor, describe el producto específico', 'warning');
                document.getElementById('producto-descripcion').focus();
                return;
            }

            if (!tiendaSeleccionada) {
                mostrarNotificacion('Primero debes seleccionar una tienda', 'warning');
                return;
            }

            if (cantidad < 1 || cantidad > 50) {
                mostrarNotificacion('La cantidad debe estar entre 1 y 50', 'warning');
                return;
            }

            // Mostrar loading
            const btn = document.querySelector('.agregar-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            btn.disabled = true;

            $.ajax({
                url: 'mandados.php',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: JSON.stringify({
                    action: 'agregar_producto_manual',
                    descripcion: descripcion,
                    cantidad: cantidad,
                    precio_estimado: precioEstimado,
                    notas: notas
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        mostrarNotificacion(`${descripcion} agregado al carrito (${cantidad} ${cantidad === 1 ? 'unidad' : 'unidades'})`, 'success');
                        
                        // Limpiar formulario
                        document.getElementById('producto-descripcion').value = '';
                        document.getElementById('producto-cantidad').value = '1';
                        document.getElementById('producto-notas').value = '';
                        document.getElementById('precio-estimado').style.display = 'none';
                        
                        // Actualizar contador del carrito
                        document.getElementById('cart-count').textContent = data.carrito_count;
                        
                        // Actualizar estado del carrito
                        actualizarEstadoCarrito();
                        
                        // Activar paso 3 si es el primer producto
                        if (data.carrito_count === 1) {
                            document.getElementById('step-2').classList.add('completed');
                            document.getElementById('step-3').classList.add('active');
                            document.getElementById('step-3').classList.remove('pending');
                        }
                        
                        // Enfocar de nuevo el input para agregar más productos
                        document.getElementById('producto-descripcion').focus();
                    } else {
                        mostrarNotificacion(data.error || 'Error al agregar producto', 'error');
                    }
                },
                error: function() {
                    mostrarNotificacion('Error del servidor', 'error');
                },
                complete: function() {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        }

        // Cargar sugerencias de productos
        function cargarSugerenciasProductos() {
            $.ajax({
                url: 'mandados.php',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: JSON.stringify({ action: 'obtener_sugerencias' }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.categorias) {
                        categoriasProductos = data.categorias;
                        mostrarSugerencias(data.categorias);
                    }
                },
                error: function() {
                    console.log('No se pudieron cargar las sugerencias');
                }
            });
        }

        // Mostrar sugerencias de productos
        function mostrarSugerencias(categorias) {
            const container = document.getElementById('categorias-container');
            
            const categoriasHTML = Object.keys(categorias).map(categoria => `
                <div class="categoria-item">
                    <div class="categoria-header" onclick="toggleCategoria('${categoria}')">
                        <span><i class="fas fa-chevron-right" id="icon-${categoria}"></i> ${categoria}</span>
                    </div>
                    <div class="categoria-content" id="content-${categoria}">
                        ${categorias[categoria].map(producto => `
                            <div class="producto-sugerido">
                                <span class="producto-sugerido-nombre">${producto}</span>
                                <button class="usar-sugerencia-btn" onclick="usarSugerencia('${producto}')">
                                    Usar
                                </button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');

            container.innerHTML = categoriasHTML;
        }

        // Toggle categoría de sugerencias
        function toggleCategoria(categoria) {
            const content = document.getElementById(`content-${categoria}`);
            const icon = document.getElementById(`icon-${categoria}`);
            
            if (content.classList.contains('show')) {
                content.classList.remove('show');
                icon.className = 'fas fa-chevron-right';
            } else {
                content.classList.add('show');
                icon.className = 'fas fa-chevron-down';
            }
        }

        // Usar sugerencia de producto
        function usarSugerencia(producto) {
            document.getElementById('producto-descripcion').value = producto;
            calcularPrecioEstimado(producto);
            document.getElementById('producto-descripcion').focus();
            
            // Scroll hasta el formulario
            document.querySelector('.producto-form').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }

        // Actualizar estado del carrito en sidebar
        function actualizarEstadoCarrito() {
            $.ajax({
                url: 'mandados.php',
                type: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: JSON.stringify({ action: 'obtener_carrito_info' }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        const container = document.getElementById('carrito-info');
                        
                        if (data.carrito_count > 0) {
                            container.innerHTML = `
                                <div class="carrito-resumen">
                                    <div style="text-align: center; margin-bottom: 1rem;">
                                        <i class="fas fa-shopping-cart" style="color: var(--success); font-size: 2rem;"></i>
                                        <div style="font-weight: 600; margin-top: 0.5rem;">
                                            ${data.carrito_count} producto${data.carrito_count !== 1 ? 's' : ''} en el carrito
                                        </div>
                                    </div>
                                    
                                    <div class="carrito-total">
                                        Total estimado: ${data.carrito_total.toFixed(2)}
                                    </div>
                                    
                                    <div style="margin-top: 1rem; text-align: center;">
                                        <a href="carrito.php" style="background: var(--success); color: white; padding: 0.75rem 1.5rem; border-radius: 10px; text-decoration: none; display: inline-block; font-weight: 500;">
                                            <i class="fas fa-eye"></i> Ver Carrito Completo
                                        </a>
                                    </div>
                                    
                                    <div style="margin-top: 1rem; padding: 1rem; background: #f0fdf4; border-radius: 10px; border: 1px solid var(--success); font-size: 0.9rem; color: #166534;">
                                        <i class="fas fa-info-circle"></i> Los precios son estimados. El precio final puede variar según disponibilidad y tienda.
                                    </div>
                                </div>
                            `;
                        } else {
                            container.innerHTML = `
                                <div class="carrito-resumen">
                                    <div style="text-align: center; color: var(--secondary); padding: 2rem;">
                                        <i class="fas fa-shopping-cart" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                                        <p>Tu carrito está vacío</p>
                                        <p style="font-size: 0.9rem;">Selecciona una tienda y agrega productos</p>
                                    </div>
                                </div>
                            `;
                        }
                    }
                }
            });
        }

        // Mostrar notificación
        function mostrarNotificacion(mensaje, tipo = 'success') {
            // Remover notificación anterior
            const existente = document.querySelector('.notification');
            if (existente) existente.remove();

            const iconos = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-times-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle'
            };

            const notification = document.createElement('div');
            notification.className = `notification ${tipo}`;
            notification.innerHTML = `<i class="${iconos[tipo]}"></i> ${mensaje}`;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 5000);
        }

        // Funciones de utilidad
        function irAlCarrito() {
            window.location.href = 'carrito.php';
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + K para enfocar descripción de producto
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                if (tiendaSeleccionada) {
                    document.getElementById('producto-descripcion').focus();
                } else {
                    mostrarNotificacion('Primero selecciona una tienda', 'info');
                }
            }
            
            // Ctrl + Enter para agregar producto rápido
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                if (tiendaSeleccionada) {
                    agregarProducto();
                }
            }
        });

        // Actualizar carrito cada 30 segundos
        setInterval(actualizarEstadoCarrito, 30000);

        // Mostrar tips iniciales
        setTimeout(() => {
            if (!tiendaSeleccionada) {
                mostrarNotificacion('💡 Tip: Selecciona una tienda para comenzar a agregar productos', 'info');
            }
        }, 3000);

        // Debug function
        function debugSistema() {
            console.log('🔍 Estado del Sistema:');
            console.log('📍 Ubicación:', ubicacionUsuario);
            console.log('🏪 Tiendas disponibles:', tiendasDisponibles.length);
            console.log('🏪 Tienda seleccionada:', tiendaSeleccionada);
            console.log('📦 Categorías cargadas:', Object.keys(categoriasProductos).length);
        }

        window.debugMandados = debugSistema;
    </script>
</body>
</html>