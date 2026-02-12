<?php

session_start();

// ==========================================
// CONFIGURACIÓN Y CONSTANTES
// ==========================================

// Token de Mapbox desde variables de entorno (REQUERIDO)
require_once __DIR__ . '/config/env.php';
$mapbox_token = getenv('MAPBOX_ACCESS_TOKEN');
if (empty($mapbox_token)) {
    error_log('ERROR: MAPBOX_ACCESS_TOKEN no está configurado en .env');
    $mapbox_token = ''; // No usar token por defecto
}
define('MAPBOX_TOKEN', $mapbox_token);

// Modo debug condicional
define('DEBUG_MODE', false); // Cambiar a true solo en desarrollo

function debugLog($message, $data = null) {
    if (DEBUG_MODE) {
        $output = $message;
        if ($data !== null) {
            $output .= ': ' . print_r($data, true);
        }
        error_log($output);
    }
}

// Función robusta para detectar pedidos pickup
function esPickup($datos_pedido) {
    // Prioridad 1: Campo tipo_pedido
    if (isset($datos_pedido['tipo_pedido']) && !empty($datos_pedido['tipo_pedido'])) {
        return strtolower(trim($datos_pedido['tipo_pedido'])) === 'pickup';
    }
    
    // Prioridad 2: Campo tipo_entrega
    if (isset($datos_pedido['tipo_entrega']) && !empty($datos_pedido['tipo_entrega'])) {
        return strtolower(trim($datos_pedido['tipo_entrega'])) === 'pickup';
    }
    
    // Fallback: Buscar en instrucciones (case-insensitive)
    if (isset($datos_pedido['instrucciones_especiales']) && !empty($datos_pedido['instrucciones_especiales'])) {
        $instrucciones = strtolower($datos_pedido['instrucciones_especiales']);
        return preg_match('/\bpick\s*up\b|\brecoger\b/i', $instrucciones) === 1;
    }
    
    return false;
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

ini_set('display_startup_errors', 0);

error_reporting(0);

debugLog("SESSION loggedin", isset($_SESSION["loggedin"]) ? $_SESSION["loggedin"] : "NOT SET");
debugLog("SESSION id_usuario", isset($_SESSION["id_usuario"]) ? $_SESSION["id_usuario"] : "NOT SET");



require_once 'config/database.php';

require_once 'models/Pedido.php';

require_once 'models/Negocio.php';

require_once 'models/Direccion.php';

require_once 'models/Usuario.php';

require_once 'models/Repartidor.php'; // Agregamos modelo de repartidor



// Verificar si el usuario está logueado

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {

  header("location: login.php");

  exit;

}



// Verificar si se proporcionó un ID de pedido

if (!isset($_GET['id']) || empty($_GET['id'])) {

  header("location: index.php");

  exit;

}



$id_pedido = intval($_GET['id']);



// Conectar a BD

$database = new Database();

$db = $database->getConnection();



// Obtener información del pedido

$pedido = new Pedido($db);

$pedido->id_pedido = $id_pedido;

$datos_pedido = $pedido->obtenerPorId();

// Validar que el pedido tenga los campos mínimos requeridos
$campos_requeridos = ['id_usuario', 'id_negocio', 'id_direccion', 'id_estado'];
$campos_faltantes = [];

if ($datos_pedido) {
    foreach ($campos_requeridos as $campo) {
        if (!isset($datos_pedido[$campo]) || empty($datos_pedido[$campo])) {
            $campos_faltantes[] = $campo;
        }
    }
    
    if (!empty($campos_faltantes)) {
        error_log("Pedido $id_pedido incompleto. Campos faltantes: " . implode(', ', $campos_faltantes));
        header("location: index.php?error=invalid_order");
        exit;
    }
}



// Verificar si el pedido existe y pertenece al usuario

if (!$datos_pedido || $datos_pedido['id_usuario'] != $_SESSION['id_usuario']) {

  header("location: index.php");

  exit;

}



// Obtener información del negocio

$negocio = new Negocio($db);

$negocio->id_negocio = $datos_pedido['id_negocio'];

$negocio->obtenerPorId();



$direccion = new Direccion($db);

$direccion->id_direccion = $datos_pedido['id_direccion'];

$direccion->obtenerPorId();





function obtenerCoordenadasMapbox($direccion, $db) {

  if (!$direccion || !isset($direccion->id_direccion)) {

    return null;

  }

   

  // 1. Verificar si ya tenemos coordenadas en BD

  if (!empty($direccion->latitud) && !empty($direccion->longitud)) {

    return [

      'lat' => (float)$direccion->latitud,

      'lng' => (float)$direccion->longitud,

      'source' => 'database'

    ];

  }

   

  // 2. Construir dirección completa para Mapbox

  $direccion_completa = trim(sprintf(

    "%s %s, %s, %s, %s, %s",

    $direccion->calle ?? '',

    $direccion->numero ?? '',

    $direccion->colonia ?? '',

    $direccion->ciudad ?? '',

    $direccion->estado ?? '',

    $direccion->codigo_postal ?? ''

  ));

   

  // Limpiar y formatear

  $direccion_completa = preg_replace('/\s+/', ' ', $direccion_completa);

  $direccion_completa = str_replace(', ,', ',', $direccion_completa);

  $direccion_completa = rtrim($direccion_completa, ', ');

   

  // 3. Geocodificar con Mapbox API

  $mapbox_token = MAPBOX_TOKEN;

   

  $coordenadas = geocodificarConMapboxAPI($direccion_completa, $mapbox_token);

   

  if ($coordenadas) {

    // Guardar en BD para futuras consultas

    guardarCoordenadasMapbox($direccion->id_direccion, $coordenadas, $db);

    return array_merge($coordenadas, ['source' => 'mapbox_api']);

  }

   

  // 4. Fallback: Coordenadas por ciudad

  return obtenerCoordenadasCiudadLocal($direccion->ciudad, $direccion->estado);

}



// Geocodificación con Mapbox Geocoding API - Con retry logic
function geocodificarConMapboxAPI($direccion_completa, $mapbox_token, $max_retries = 3) {
  $retry_count = 0;
  
  while ($retry_count < $max_retries) {
    try {
      // URL de la API de Geocodificación de Mapbox
      $url = sprintf(
        'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json?access_token=%s&country=MX&types=address&limit=1',
        urlencode($direccion_completa),
        $mapbox_token
      );
       
      $context = stream_context_create([
        'http' => [
          'timeout' => 10,
          'user_agent' => 'QuickBite-Mapbox/1.0',
          'ignore_errors' => true
        ]
      ]);
       
      $response = @file_get_contents($url, false, $context);
       
      if ($response === false) {
        $retry_count++;
        if ($retry_count < $max_retries) {
          debugLog("Mapbox API - Reintento " . ($retry_count) . " de " . $max_retries);
          sleep(1); // Esperar antes de reintentar
          continue;
        }
        error_log("Error: No se pudo conectar a Mapbox API después de $max_retries intentos");
        return null;
      }
       
      $data = json_decode($response, true);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decodificando JSON de Mapbox: " . json_last_error_msg());
        return null;
      }
       
      if (isset($data['features']) && !empty($data['features'])) {
        $feature = $data['features'][0];
        $coordinates = $feature['geometry']['coordinates'];
         
        // Mapbox devuelve [lng, lat], necesitamos [lat, lng]
        return [
          'lat' => (float)$coordinates[1],
          'lng' => (float)$coordinates[0],
          'accuracy' => $feature['properties']['accuracy'] ?? 'unknown',
          'place_name' => $feature['place_name'] ?? $direccion_completa
        ];
      }
       
      error_log("Mapbox API: No se encontraron resultados para: " . $direccion_completa);
      return null;
       
    } catch (Exception $e) {
      error_log("Error geocodificación Mapbox (intento " . ($retry_count + 1) . "): " . $e->getMessage());
      $retry_count++;
      if ($retry_count < $max_retries) {
        sleep(1);
      }
    }
  }
  
  return null;
}



// Geocodificación alternativa con coordenadas aproximadas por ciudad

function geocodificarConMapboxCiudad($ciudad, $estado, $mapbox_token) {

  try {

    $busqueda_ciudad = "$ciudad, $estado, México";

     

    $url = sprintf(

      'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json?access_token=%s&country=MX&types=place&limit=1',

      urlencode($busqueda_ciudad),

      $mapbox_token

    );

     

    $response = file_get_contents($url);

     

    if ($response) {

      $data = json_decode($response, true);

       

      if (isset($data['features']) && !empty($data['features'])) {

        $coordinates = $data['features'][0]['geometry']['coordinates'];

        return [

          'lat' => (float)$coordinates[1],

          'lng' => (float)$coordinates[0]

        ];

      }

    }

     

    return null;

     

  } catch (Exception $e) {

    error_log("Error geocodificación ciudad Mapbox: " . $e->getMessage());

    return null;

  }

}



// Guardar coordenadas en BD

function guardarCoordenadasMapbox($id_direccion, $coordenadas, $db) {

  try {

    $stmt = $db->prepare("UPDATE direcciones_usuario SET latitud = ?, longitud = ?, fecha_geocodificacion = NOW() WHERE id_direccion = ?");

    $stmt->execute([

      $coordenadas['lat'], 

      $coordenadas['lng'], 

      $id_direccion

    ]);

     

    error_log("✅ Coordenadas Mapbox guardadas para dirección ID: $id_direccion");

     

  } catch (Exception $e) {

    error_log("❌ Error guardando coordenadas Mapbox: " . $e->getMessage());

  }

}



// Coordenadas locales como fallback

function obtenerCoordenadasCiudadLocal($ciudad, $estado) {

  $coordenadas_exactas = [

    'teocaltiche' => ['lat' => 21.4167, 'lng' => -102.5667],

    'ojuelos' => ['lat' => 21.8667, 'lng' => -101.6000],

    'ojuelos de jalisco' => ['lat' => 21.8667, 'lng' => -101.6000]

  ];

   

  $ciudad_lower = strtolower(trim($ciudad ?? ''));

   

  if (isset($coordenadas_exactas[$ciudad_lower])) {

    return array_merge($coordenadas_exactas[$ciudad_lower], ['source' => 'local_database']);

  }

   

  return [

    'lat' => 21.4167, 

    'lng' => -102.5667, 

    'source' => 'default_teocaltiche'

  ];

}



// ==================== IMPLEMENTACIÓN PRINCIPAL ====================



// Obtener coordenadas exactas de la dirección del pedido usando Mapbox

$coordenadas_entrega = obtenerCoordenadasMapbox($direccion, $db);



if ($coordenadas_entrega) {

  $deliveryLat = $coordenadas_entrega['lat'];

  $deliveryLng = $coordenadas_entrega['lng'];

  $coord_source = $coordenadas_entrega['source'];

  $coord_accuracy = $coordenadas_entrega['accuracy'] ?? 'unknown';

} else {

  // Fallback 

  $deliveryLat = 21.4167;

  $deliveryLng = -102.5667;

  $coord_source = 'fallback';

  $coord_accuracy = 'low';

}



// Coordenadas del restaurante/negocio

$restaurantLat = 21.4167; // Fallback Teocaltiche

$restaurantLng = -102.5667;



if (!empty($negocio->latitud) && !empty($negocio->longitud)) {

  $restaurantLat = (float)$negocio->latitud;

  $restaurantLng = (float)$negocio->longitud;

} else {

  // Geocodificar dirección del restaurante si está disponible

  if (isset($negocio->calle, $negocio->ciudad)) {

    $direccion_restaurante = sprintf(

      "%s %s, %s, %s",

      $negocio->calle ?? '',

      $negocio->numero ?? '',

      $negocio->ciudad ?? '',

      $negocio->estado ?? ''

    );

     

    $mapbox_token = MAPBOX_TOKEN;

    $coords_restaurante = geocodificarConMapboxAPI($direccion_restaurante, $mapbox_token);

     

    if ($coords_restaurante) {

      $restaurantLat = $coords_restaurante['lat'];

      $restaurantLng = $coords_restaurante['lng'];

    }

  }

}



// Usar función robusta para detectar pickup
$es_pickup = esPickup($datos_pedido);

// Debug condicional
debugLog("tipo_entrega", $datos_pedido['tipo_entrega'] ?? 'NO_SET');
debugLog("notas", $datos_pedido['notas'] ?? 'NO_SET');
debugLog("es_pickup resultado", $es_pickup ? 'TRUE' : 'FALSE');

// Obtener información del repartidor si está asignado y no es pickup

$repartidor = null;

$repartidor_info = '';

if (!$es_pickup && isset($datos_pedido['id_repartidor']) && !empty($datos_pedido['id_repartidor'])) {

  $repartidor = new Repartidor($db);

  $repartidor->id_repartidor = $datos_pedido['id_repartidor'];

  $repartidor_data = $repartidor->obtenerPorId();

   

  if ($repartidor_data) {

    $repartidor_info = $repartidor_data['nombre'] . ' ' . $repartidor_data['apellido'] . ' - Tel: ' . $repartidor_data['telefono'];

  } else {

    // Si no se encuentra el repartidor, limpiar la referencia

    $repartidor_info = 'Repartidor no disponible';

    error_log("Repartidor con ID {$datos_pedido['id_repartidor']} no encontrado");

  }

}



// Normalizar estados para PICKUP

function normalizarEstadoPickup($estado) {

  switch ($estado) {

    case 1: return 1; // Pedido recibido

    case 2: return 2; // Pedido confirmado

    case 3: return 3; // Preparando

    case 4: case 5: return 4; // Listo para pickup

    case 6: return 4; // Completado (en pickup es lo mismo que listo)

    default: return $estado;

  }

}



// Obtener estado actual del pedido con mensajes mejorados

$estado_actual = $datos_pedido['id_estado'] ?? 1;

if (isset($es_pickup) && $es_pickup) {

  $estado_actual = normalizarEstadoPickup($estado_actual);

}

$estado_config = obtenerConfigEstado($estado_actual, $negocio->nombre ?? 'el restaurante', $es_pickup);

// Obtener datos SPEI si es pago pendiente
$spei_data = null;
if (($estado_actual == 7 || isset($_GET['spei'])) && in_array($datos_pedido['metodo_pago'] ?? '', ['spei', 'efectivo'])) {
    try {
        $stmt_spei = $db->prepare("SELECT * FROM spei_payments WHERE pedido_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt_spei->execute([$id_pedido]);
        $spei_data = $stmt_spei->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo datos SPEI: " . $e->getMessage());
    }
}

function obtenerConfigEstado($estado, $nombre_negocio, $es_pickup) {

  $config = [];

   

  if ($es_pickup) {

    switch ($estado) {

      case 1:

        $config = [

          'titulo' => '¡Pedido recibido!',

          'mensaje' => 'Tu pedido ha sido enviado a ' . $nombre_negocio . ' y pronto será procesado',

          'tiempo' => '15-20 minutos',

          'icono' => 'fas fa-check-circle',

          'color' => 'success'

        ];

        break;

      case 2:

        $config = [

          'titulo' => '¡Pedido confirmado!',

          'mensaje' => $nombre_negocio . ' ha confirmado tu orden y comenzará a prepararla',

          'tiempo' => '10-15 minutos',

          'icono' => 'fas fa-thumbs-up',

          'color' => 'info'

        ];

        break;

      case 3:

        $config = [

          'titulo' => 'Preparando tu pedido',

          'mensaje' => 'Tu orden está siendo preparada con mucho cuidado',

          'tiempo' => '5-10 minutos',

          'icono' => 'fas fa-fire',

          'color' => 'warning'

        ];

        break;

      case 4:

        $config = [

          'titulo' => '¡Listo para recoger!',

          'mensaje' => 'Tu pedido está listo. Ya puedes pasar a recogerlo en ' . $nombre_negocio,

          'tiempo' => 'Listo',

          'icono' => 'fas fa-store',

          'color' => 'primary'

        ];

        break;

      case 5:

        $config = [

          'titulo' => '¡Pedido retirado!',

          'mensaje' => 'Has retirado tu pedido exitosamente. ¡Disfrútalo!',

          'tiempo' => 'Completado',

          'icono' => 'fas fa-hand-holding',

          'color' => 'success'

        ];

        break;

      case 6:

        $config = [

          'titulo' => '¡Pedido completado!',

          'mensaje' => 'Gracias por tu preferencia. ¡Esperamos verte pronto!',

          'tiempo' => 'Finalizado',

          'icono' => 'fas fa-star',

          'color' => 'success'

        ];

        break;

      case 7:

        $config = [

          'titulo' => 'Pedido cancelado',

          'mensaje' => 'Tu pedido ha sido cancelado. El reembolso será procesado en breve',

          'tiempo' => 'Cancelado',

          'icono' => 'fas fa-times-circle',

          'color' => 'danger'

        ];

        break;

      case 8:

        $config = [

          'titulo' => 'Pedido rechazado',

          'mensaje' => $nombre_negocio . ' no pudo procesar tu pedido en este momento',

          'tiempo' => 'Rechazado',

          'icono' => 'fas fa-ban',

          'color' => 'danger'

        ];

        break;

      case 9:

        $config = [

          'titulo' => 'Reembolso procesado',

          'mensaje' => 'El reembolso ha sido procesado exitosamente',

          'tiempo' => 'Reembolsado',

          'icono' => 'fas fa-undo',

          'color' => 'info'

        ];

        break;

      case 10:

        $config = [

          'titulo' => 'Pedido expirado',

          'mensaje' => 'El tiempo límite para recoger tu pedido ha expirado',

          'tiempo' => 'Expirado',

          'icono' => 'fas fa-clock',

          'color' => 'warning'

        ];

        break;

      case 7:

        $config = [

          'titulo' => 'Pendiente de Pago',

          'mensaje' => 'Realiza la transferencia SPEI para confirmar tu pedido',

          'tiempo' => 'Esperando pago',

          'icono' => 'fas fa-university',

          'color' => 'warning'

        ];

        break;

      default:

        $config = [

          'titulo' => 'Estado desconocido',

          'mensaje' => 'Estamos verificando el estado de tu pedido',

          'tiempo' => 'Verificando...',

          'icono' => 'fas fa-question-circle',

          'color' => 'secondary'

        ];

    }

  } else {

    // Estados para pedidos normales (no pickup)

    switch ($estado) {

      case 1:

        $config = [

          'titulo' => '¡Pedido creado correctamente!',

          'mensaje' => 'Tu pedido ha sido enviado a ' . $nombre_negocio . ' y pronto será procesado',

          'tiempo' => '25-35 minutos',

          'icono' => 'fas fa-check-circle',

          'color' => 'success'

        ];

        break;

      case 2:

        $config = [

          'titulo' => '¡' . $nombre_negocio . ' aceptó tu pedido!',

          'mensaje' => 'El restaurante ha confirmado tu orden y comenzará a prepararla',

          'tiempo' => '20-30 minutos',

          'icono' => 'fas fa-thumbs-up',

          'color' => 'info'

        ];

        break;

      case 3:

        $config = [

          'titulo' => 'Tu pedido se está preparando',

          'mensaje' => 'Los chefs están cocinando tu orden con mucho cuidado',

          'tiempo' => '15-25 minutos',

          'icono' => 'fas fa-fire',

          'color' => 'warning'

        ];

        break;

      case 4:

        $config = [

          'titulo' => '¡Tu pedido está listo!',

          'mensaje' => 'El restaurante terminó de preparar tu orden y está esperando al repartidor',

          'tiempo' => '10-15 minutos',

          'icono' => 'fas fa-box-open',

          'color' => 'primary'

        ];

        break;

      case 5:

        $config = [

          'titulo' => 'El repartidor se dirige a tu domicilio',

          'mensaje' => 'Tu pedido va en camino. ¡Ya casi llega!',

          'tiempo' => '5-10 minutos',

          'icono' => 'fas fa-shipping-fast',

          'color' => 'warning'

        ];

        break;

      case 6:

        $config = [

          'titulo' => '¡Pedido entregado exitosamente!',

          'mensaje' => 'Esperamos que disfrutes tu comida. ¡Gracias por tu preferencia!',

          'tiempo' => 'Completado',

          'icono' => 'fas fa-star',

          'color' => 'success'

        ];

        break;

      case 7:

        $config = [

          'titulo' => 'Pedido cancelado',

          'mensaje' => 'Tu pedido ha sido cancelado. El reembolso será procesado automáticamente',

          'tiempo' => 'Cancelado',

          'icono' => 'fas fa-times-circle',

          'color' => 'danger'

        ];

        break;

      case 8:

        $config = [

          'titulo' => 'Pedido rechazado por el restaurante',

          'mensaje' => $nombre_negocio . ' no pudo aceptar tu pedido en este momento',

          'tiempo' => 'Rechazado',

          'icono' => 'fas fa-ban',

          'color' => 'danger'

        ];

        break;

      case 9:

        $config = [

          'titulo' => 'Buscando repartidor',

          'mensaje' => 'Estamos asignando un repartidor para tu pedido',

          'tiempo' => '10-15 minutos',

          'icono' => 'fas fa-search',

          'color' => 'info'

        ];

        break;

      case 10:

        $config = [

          'titulo' => 'Repartidor asignado',

          'mensaje' => 'Un repartidor ha sido asignado y se dirige al restaurante',

          'tiempo' => '8-12 minutos',

          'icono' => 'fas fa-user-check',

          'color' => 'info'

        ];

        break;

      case 11:

        $config = [

          'titulo' => 'Repartidor en el restaurante',

          'mensaje' => 'El repartidor está recogiendo tu pedido en ' . $nombre_negocio,

          'tiempo' => '5-8 minutos',

          'icono' => 'fas fa-handshake',

          'color' => 'warning'

        ];

        break;

      case 12:

        $config = [

          'titulo' => 'Pedido recogido por el repartidor',

          'mensaje' => 'Tu pedido fue recogido y está en camino hacia tu ubicación',

          'tiempo' => '3-7 minutos',

          'icono' => 'fas fa-motorcycle',

          'color' => 'warning'

        ];

        break;

      case 13:

        $config = [

          'titulo' => 'Repartidor cerca de tu ubicación',

          'mensaje' => 'El repartidor está muy cerca. ¡Prepárate para recibir tu pedido!',

          'tiempo' => '1-3 minutos',

          'icono' => 'fas fa-map-marker-alt',

          'color' => 'info'

        ];

        break;

      case 14:

        $config = [

          'titulo' => 'Problema con la entrega',

          'mensaje' => 'Ha ocurrido un problema con la entrega. Te contactaremos pronto',

          'tiempo' => 'En revisión',

          'icono' => 'fas fa-exclamation-triangle',

          'color' => 'warning'

        ];

        break;

      case 15:

        $config = [

          'titulo' => 'Reembolso procesado',

          'mensaje' => 'El reembolso de tu pedido ha sido procesado exitosamente',

          'tiempo' => 'Reembolsado',

          'icono' => 'fas fa-undo',

          'color' => 'info'

        ];

        break;

      case 16:

        $config = [

          'titulo' => 'Pedido reagendado',

          'mensaje' => 'Tu pedido ha sido reagendado para una nueva fecha y hora',

          'tiempo' => 'Reagendado',

          'icono' => 'fas fa-calendar-alt',

          'color' => 'info'

        ];

        break;

      case 17:

        $config = [

          'titulo' => 'Esperando confirmación',

          'mensaje' => 'Estamos esperando la confirmación del restaurante',

          'tiempo' => '5-10 minutos',

          'icono' => 'fas fa-hourglass-half',

          'color' => 'secondary'

        ];

        break;

      case 18:

        $config = [

          'titulo' => 'Pedido en pausa',

          'mensaje' => 'Tu pedido está temporalmente en pausa por solicitud',

          'tiempo' => 'En pausa',

          'icono' => 'fas fa-pause-circle',

          'color' => 'secondary'

        ];

        break;

      case 19:

        $config = [

          'titulo' => 'Repartidor no disponible',

          'mensaje' => 'No hay repartidores disponibles en este momento. Estamos solucionando esto',

          'tiempo' => 'Buscando alternativas',

          'icono' => 'fas fa-user-times',

          'color' => 'warning'

        ];

        break;

      case 20:

        $config = [

          'titulo' => 'Entrega fallida',

          'mensaje' => 'No se pudo realizar la entrega. Te contactaremos para coordinar',

          'tiempo' => 'Fallida',

          'icono' => 'fas fa-times',

          'color' => 'danger'

        ];

        break;

      case 7:

        $config = [

          'titulo' => 'Pendiente de Pago',

          'mensaje' => 'Realiza la transferencia SPEI para confirmar tu pedido',

          'tiempo' => 'Esperando pago',

          'icono' => 'fas fa-university',

          'color' => 'warning'

        ];

        break;

      default:

        $config = [

          'titulo' => 'Estado desconocido',

          'mensaje' => 'Estamos verificando el estado de tu pedido',

          'tiempo' => 'Verificando...',

          'icono' => 'fas fa-question-circle',

          'color' => 'secondary'

        ];

    }

  }

   

  return $config;

}

// Función para obtener los pasos del timeline según el tipo de pedido
function obtenerPasosTimeline($es_pickup) {
  if ($es_pickup) {
    return [
      1 => [
        'titulo' => 'Pedido recibido',
        'subtitulo' => 'Tu pedido ha sido enviado al restaurante',
        'icono' => 'fas fa-receipt',
        'icono_completado' => 'fas fa-check'
      ],
      2 => [
        'titulo' => 'Preparando tu pedido', 
        'subtitulo' => 'El restaurante está cocinando tu orden',
        'icono' => 'fas fa-fire',
        'icono_completado' => 'fas fa-check',
        'estados_activos' => [2, 3] // Estados 2 y 3 activan este paso
      ],
      3 => [
        'titulo' => 'Listo para recoger',
        'subtitulo' => 'Tu pedido está listo en el restaurante', 
        'icono' => 'fas fa-store',
        'icono_completado' => 'fas fa-check'
      ],
      4 => [
        'titulo' => 'Pedido retirado',
        'subtitulo' => '¡Disfruta tu comida!',
        'icono' => 'fas fa-hand-holding',
        'icono_completado' => 'fas fa-star'
      ]
    ];
  } else {
    return [
      1 => [
        'titulo' => 'Pedido creado',
        'subtitulo' => 'Tu pedido ha sido enviado al restaurante',
        'icono' => 'fas fa-receipt', 
        'icono_completado' => 'fas fa-check'
      ],
      2 => [
        'titulo' => 'Confirmado por restaurante',
        'subtitulo' => 'El restaurante aceptó tu pedido',
        'icono' => 'fas fa-thumbs-up',
        'icono_completado' => 'fas fa-check'
      ],
      3 => [
        'titulo' => 'Preparando',
        'subtitulo' => 'Los chefs están cocinando tu orden',
        'icono' => 'fas fa-fire',
        'icono_completado' => 'fas fa-check'
      ],
      4 => [
        'titulo' => 'Listo para entrega',
        'subtitulo' => 'Asignando repartidor',
        'icono' => 'fas fa-box-open',
        'icono_completado' => 'fas fa-check'
      ],
      5 => [
        'titulo' => 'En camino',
        'subtitulo' => 'El repartidor se dirige a tu ubicación',
        'icono' => 'fas fa-shipping-fast',
        'icono_completado' => 'fas fa-check'
      ],
      6 => [
        'titulo' => 'Entregado',
        'subtitulo' => '¡Disfruta tu comida!',
        'icono' => 'fas fa-home',
        'icono_completado' => 'fas fa-star'
      ]
    ];
  }
}

// Función para determinar el estado de un paso del timeline
function obtenerEstadoPaso($paso, $estado_actual, $pasos_timeline) {
  // Estado 7 = pendiente de pago (transferencia): ningún paso completado, paso 1 activo
  if ($estado_actual == 7) {
    if ($paso == 1) return 'active';
    return 'pending';
  }

  $paso_info = $pasos_timeline[$paso];

  // Para pickup, el paso 2 se activa con estados 2 o 3
  if (isset($paso_info['estados_activos'])) {
    $es_activo = in_array($estado_actual, $paso_info['estados_activos']);
    $es_completado = $estado_actual > max($paso_info['estados_activos']);
  } else {
    $es_activo = ($estado_actual == $paso);
    $es_completado = ($estado_actual > $paso);
  }

  if ($es_completado) {
    return 'completed';
  } elseif ($es_activo) {
    return 'active';
  } else {
    return '';
  }
}

// Función para obtener el tiempo estimado de un paso
function obtenerTiempoPaso($paso, $estado_actual, $fecha_pedido, $es_pickup) {
  $tiempos_estimados = [
    1 => 0,   // Inmediato
    2 => $es_pickup ? 5 : 2,   // 5 min pickup, 2 min delivery
    3 => $es_pickup ? 15 : 8,  // 15 min pickup, 8 min delivery
    4 => $es_pickup ? 20 : 15, // 20 min pickup, 15 min delivery
    5 => 25,  // Solo delivery
    6 => 35   // Solo delivery
  ];
  
  $minutos = $tiempos_estimados[$paso] ?? 0;
  
  if ($paso == 1) {
    // Para el primer paso, siempre mostrar la fecha real del pedido
    return formatearFechaEspanol($fecha_pedido ?? 'now');
  } elseif ($estado_actual >= $paso) {
    // Ya completado - mostrar tiempo estimado basado en fecha del pedido
    return formatearFechaEspanol($fecha_pedido ?? 'now', $minutos);
  } else {
    // Pendiente - mostrar estimación
    if ($estado_actual == ($paso - 1) || ($es_pickup && $paso == 2 && in_array($estado_actual, [2, 3]))) {
      return "Estimado: " . formatearFechaEspanol('now', $minutos);
    } else {
      return "Pendiente de confirmación";
    }
  }
}



// Calcular tiempo estimado de entrega

function calcularTiempoEntrega($estado) {

  switch ($estado) {

    case 1: return "25-35 minutos";

    case 2: return "20-30 minutos";

    case 3: return "15-25 minutos";

    case 4: return "10-15 minutos";

    case 5: return "5-10 minutos";

    case 6: return "Entregado";

    default: return "Tiempo estimado no disponible";

  }

}



$tiempo_entrega = calcularTiempoEntrega($estado_actual);



// Inicializar datos del timeline
$pasos_timeline = obtenerPasosTimeline($es_pickup);

// Obtener los ítems del pedido

$items_pedido = $pedido->obtenerItems();



// Función para formatear fechas en español

function formatearFechaEspanol($fecha_string, $agregar_minutos = 0) {

  try {

    $fecha = new DateTime($fecha_string);

    if ($agregar_minutos > 0) {

      $fecha->add(new DateInterval('PT' . $agregar_minutos . 'M'));

    }

    

    $meses = [

      1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',

      5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',

      9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'

    ];

    

    $dia = $fecha->format('j');

    $mes = $meses[(int)$fecha->format('n')];

    $año = $fecha->format('Y');

    $hora = $fecha->format('H:i');

    

    return "{$dia} de {$mes}, {$año} | {$hora}";

  } catch (Exception $e) {

    return date('j \d\e M, Y | H:i');

  }

}







?>



<!DOCTYPE html>

<html lang="es">

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Seguimiento de Pedido - QuickBite</title>

  <!-- Global Theme CSS y JS (Modo Oscuro Persistente) -->
  <link rel="stylesheet" href="assets/css/global-theme.css?v=2.1">
  <script src="assets/js/theme-handler.js?v=2.1"></script>

  <link rel="icon" type="image/x-icon" href="assets/img/logo.png">

  <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- Scripts de pago -->

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">



  <!-- Mapbox CSS -->

  <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">

   

<style>

:root {

  --primary: #0165FF;

  --primary-light: #4285FF;

  --primary-dark: #0056E0;

  --secondary: #f5f5f5;

  --accent: #1a1a1a;

  --dark: #000000;

  --light: #ffffff;

  --gray-50: #fafafa;

  --gray-100: #f5f5f5;

  --gray-200: #e5e5e5;

  --gray-300: #d4d4d4;

  --gray-400: #a3a3a3;

  --gray-500: #737373;

  --gray-600: #525252;

  --gray-700: #404040;

  --gray-800: #262626;

  --gray-900: #171717;

  --gradient: linear-gradient(135deg, #000000 0%, #333333 100%);

  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);

  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);

  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);

  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

  --border-radius: 20px;

  --border-radius-lg: 24px;

  --border-radius-xl: 28px;

  --border-radius-full: 50px;

  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

  --success: #22c55e;

  --warning: #f59e0b;

  --danger: #ef4444;

  --info: #3b82f6;

}



* {

  box-sizing: border-box;

  margin: 0;

  padding: 0;

}



body {

  font-family: 'Nunito', sans-serif;

  background: var(--gray-100);

  color: var(--gray-900);

  line-height: 1.6;

  font-size: 16px;

  -webkit-font-smoothing: antialiased;

  -moz-osx-font-smoothing: grayscale;

  font-weight: 400;

  min-height: 100vh;

  padding: 0;

  margin: 0;

}



.main-container {


  margin: 0 auto;

  background: var(--light);

  min-height: 100vh;

  position: relative;

  padding: 20px;

}



.page-header {

  display: flex;

  align-items: center;

  gap: 16px;

  padding: 20px 0 24px 0;

  border-bottom: 1px solid var(--gray-200);

  margin-bottom: 32px;

  position: relative;

  background: var(--light);

  z-index: 1;

}



.back-button {

  width: 48px;

  height: 48px;

  border-radius: 12px;

  background: var(--gray-100);

  border: none;

  display: flex;

  align-items: center;

  justify-content: center;

  color: var(--dark);

  font-size: 18px;

  cursor: pointer;

  transition: var(--transition);

}



.back-button:hover {

  background: var(--gray-200);

  transform: translateY(-2px);

}



.page-title {

  font-size: 28px;

  font-weight: 700;

  color: var(--dark);

  margin: 0;

}



.header {

  display: flex;

  align-items: center;

  padding: 16px 20px;

  background: var(--light);

  border-bottom: 0.5px solid var(--gray-200);

}



.back-button {

  width: 32px;

  height: 32px;

  border-radius: 50%;

  background: var(--gray-100);

  border: none;

  display: flex;

  align-items: center;

  justify-content: center;

  margin-right: 16px;

  color: var(--dark);

  font-size: 16px;

  cursor: pointer;

}



.header-title {

  font-size: 17px;

  font-weight: 600;

  color: var(--dark);

  margin: 0;

}



.map-section {

  background: var(--light);

  border-radius: var(--border-radius);

  padding: 24px;

  margin-bottom: 32px;

  box-shadow: var(--shadow-md);

  border: 1px solid var(--gray-200);

}



.map-container {

  height: 400px;

  background: var(--gray-200);

  position: relative;

  overflow: hidden;

  border-radius: var(--border-radius);

  box-shadow: var(--shadow-sm);

}



#map {

  height: 100%;

  width: 100%;

  border-radius: var(--border-radius);

}



.content-grid {

  display: grid;

  grid-template-columns: 1fr 1fr;

  gap: 32px;

  margin-bottom: 32px;

}



.info-card {

  background: var(--light);

  border-radius: var(--border-radius);

  padding: 24px;

  box-shadow: var(--shadow-md);

  border: 1px solid var(--gray-200);

  height: fit-content;

}



.product-info {

  padding: 32px 0;

}



.product-header {

  display: flex;

  align-items: flex-start;

  gap: 20px;

  margin-bottom: 40px;

  padding: 24px;

  background: var(--light);

  border-radius: var(--border-radius);

  box-shadow: var(--shadow-md);

  border: 1px solid var(--gray-200);

}



.product-icon {

  width: 56px;

  height: 56px;

  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);

  border-radius: 16px;

  display: flex;

  align-items: center;

  justify-content: center;

  color: var(--light);

  font-size: 24px;

  flex-shrink: 0;

  box-shadow: var(--shadow-md);

}



.product-details {

  flex: 1;

}



.product-name {

  font-size: 20px;

  font-weight: 700;

  color: var(--dark);

  margin-bottom: 8px;

}



.product-category {

  font-size: 16px;

  color: var(--gray-500);

  margin-bottom: 12px;

}



.product-rating {

  display: flex;

  align-items: center;

  gap: 12px;

}



.rating-stars {

  color: #fbbf24;

  font-size: 16px;

}



.rating-number {

  font-size: 16px;

  color: var(--gray-600);

  font-weight: 600;

}



.timeline-container {

  background: var(--light);

  border-radius: var(--border-radius);

  padding: 32px;

  box-shadow: var(--shadow-md);

  border: 1px solid var(--gray-200);

}



.timeline-title {

  font-size: 22px;

  font-weight: 700;

  color: var(--dark);

  margin-bottom: 24px;

  display: flex;

  align-items: center;

  gap: 12px;

}



.timeline-title i {

  color: var(--primary);

}



.timeline-step {

  display: flex;

  align-items: flex-start;

  position: relative;

  padding-bottom: 32px;

}



.timeline-step:last-child {

  padding-bottom: 0;

}



.timeline-step:not(:last-child)::after {

  content: '';

  position: absolute;

  left: 25px;

  top: 52px;

  bottom: 0;

  width: 2px;

  background: var(--gray-200);

}



.timeline-step.completed:not(:last-child)::after {

  background: var(--dark);

}



.step-indicator {

  width: 52px;

  height: 52px;

  border-radius: 50%;

  background: var(--gray-300);

  border: 3px solid var(--gray-200);

  display: flex;

  align-items: center;

  justify-content: center;

  margin-right: 20px;

  flex-shrink: 0;

  position: relative;

  z-index: 2;

  font-size: 18px;

  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);

  transform: scale(1);

}



.step-indicator.completed {

  background: var(--primary);

  border-color: var(--primary);

  color: var(--light);

  box-shadow: 0 0 0 8px rgba(1, 101, 255, 0.1);

  animation: stepCompleted 0.6s ease-out;

}



.step-indicator.active {

  background: var(--light);

  border-color: var(--dark);

  color: var(--dark);

  box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);

  animation: stepActive 0.4s ease-out;

}



/* Animaciones para cambios de estado */

@keyframes stepCompleted {

  0% {

    transform: scale(1);

    box-shadow: 0 0 0 0 rgba(1, 101, 255, 0.4);

  }

  50% {

    transform: scale(1.15);

    box-shadow: 0 0 0 12px rgba(1, 101, 255, 0.2);

  }

  100% {

    transform: scale(1);

    box-shadow: 0 0 0 8px rgba(1, 101, 255, 0.1);

  }

}



@keyframes stepActive {

  0% {

    transform: scale(1);

    box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.2);

  }

  50% {

    transform: scale(1.1);

    box-shadow: 0 0 0 8px rgba(0, 0, 0, 0.15);

  }

  100% {

    transform: scale(1);

    box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.1);

  }

}



/* Transición suave para iconos */

.step-indicator i {

  transition: all 0.3s ease;

}



.step-content {

  flex: 1;

  padding-top: 4px;

}



.step-title {

  font-size: 18px;

  font-weight: 600;

  color: var(--dark);

  margin-bottom: 6px;

}



.step-subtitle {

  font-size: 16px;

  color: var(--gray-500);

  margin-bottom: 4px;

}



.step-time {

  font-size: 16px;

  color: var(--gray-500);

}



.step-time.completed {

  color: var(--dark);

}



.action-section {

  text-align: center;

  margin-top: 15px;

  display: flex;

  flex-direction: row;

  gap: 16px;

  align-items: center;

  justify-content: center;

}



.cancel-button, .home-button {

  border-radius: 12px;

  font-size: 16px;

  font-weight: 600;

  cursor: pointer;

  transition: var(--transition);

  padding: 16px 32px;

  min-width: 200px;

  border: 2px solid;

  display: flex;

  align-items: center;

  justify-content: center;

  gap: 8px;

  text-decoration: none;

}



.cancel-button {

  background: var(--gray-100);

  border-color: var(--gray-300);

  color: var(--dark);

}



.cancel-button:hover {

  background: var(--gray-200);

  border-color: var(--gray-400);

  transform: translateY(-2px);

  box-shadow: var(--shadow-md);

}



.home-button {

  background: var(--primary);

  border-color: var(--primary);

  color: var(--light);

}



.home-button:hover {

  background: var(--primary-light);

  border-color: var(--primary-light);

  transform: translateY(-2px);

  box-shadow: var(--shadow-md);

  color: var(--light);

  text-decoration: none;

}

  transform: translateY(-2px);

  box-shadow: var(--shadow-md);

}



/* Responsive Design */

@media (max-width: 1024px) {

  .content-grid {

    grid-template-columns: 1fr;

    gap: 24px;

  }

  

  .main-container {

    padding: 16px;

  }

  

  .page-header {
    text-align: center;

    padding: 18px 0 22px 0;

    margin-bottom: 28px;

  }

  

  .page-title {

    font-size: 26px;

  }

  

  .map-overlay {

    top: 14px;

    right: 14px;

    max-width: 200px;

    padding: 12px 16px;

  }

}



@media (max-width: 768px) {

  .main-container {

    padding: 12px;

  }

  

  .page-header {

    padding: 10px 0 20px 0;

    margin-bottom: 24px;

  }

  

  .page-title {

    font-size: 24px;

  }

  

  .map-overlay {

    top: 12px;

    right: 12px;

    max-width: 180px;

    font-size: 13px;

    padding: 10px 14px;

  }

  

  .map-section {

    padding: 20px;

    margin-bottom: 24px;

  }

  

  .map-container {

    height: 300px;

  }

  

  .product-header {

    padding: 20px;

    margin-bottom: 32px;

  }

  

  .product-icon {

    width: 48px;

    height: 48px;

    font-size: 20px;

  }

  

  .product-name {

    font-size: 18px;

  }

  

  .timeline-container {

    padding: 24px;

  }

  

  .timeline-title {

    font-size: 20px;

  }

  

  .step-indicator {

    width: 44px;

    height: 44px;

    font-size: 16px;

  }

  

  .step-title {

    font-size: 16px;

  }

  

  .info-card {

    padding: 20px;

  }

}



@media (max-width: 480px) {

  .main-container {

    padding: 8px;

  }

  

  .page-header {

    flex-direction: row;

    align-items: center;

    gap: 12px;

    padding: 8px 0 16px 0;

    margin-bottom: 20px;

  }

  

  .back-button {

    width: 40px;

    height: 40px;

  }

  

  .page-title {

    font-size: 20px;

  }

  

  .map-overlay {

    top: 8px;

    right: 8px;

    left: 8px;

    max-width: none;

    width: auto;

    font-size: 12px;

    padding: 8px 12px;

    text-align: center;

    position: absolute;

    background: rgba(255, 255, 255, 0.98);

  }

  

  .map-container {

    height: 250px;

  }

  

  .product-header {

    flex-direction: column;

    text-align:;

    gap: 16px;

  }

  

  .timeline-step {

    padding-bottom: 28px;

  }

  

  .timeline-step:not(:last-child)::after {

    left: 21px;

  }

  

  .step-indicator {

    width: 40px;

    height: 40px;

    font-size: 14px;

    margin-right: 16px;

  }

  

  .action-section {

    flex-direction: column;

    justify-content: center;

  }

  

  .cancel-button, .home-button {

    width: 100%;

    min-width: auto;

    padding: 14px 24px;

    font-size: 15px;

  }

}



/* Hide original desktop elements */

.container, .order-status-card, .status-header, .order-info-banner, .status-timeline, .row, .info-section {

  display: none !important;

}



/* Map overlay adjustments */

.map-overlay {

  position: absolute;

  top: 16px;

  right: 16px;

  background: rgba(255, 255, 255, 0.98);

  backdrop-filter: blur(12px);

  padding: 14px 18px;

  border-radius: 16px;

  box-shadow: var(--shadow-lg);

  z-index: 10;

  max-width: 220px;

  font-size: 14px;

  border: 1px solid rgba(0, 0, 0, 0.1);

  font-weight: 500;

  line-height: 1.4;

}



/* Popups */

.mapboxgl-popup-content {

  border-radius: 16px !important;

  padding: 16px !important;

  box-shadow: var(--shadow-xl) !important;

  border: 0.5px solid var(--gray-200) !important;

  min-width: 220px !important;

  font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif !important;

}



.mapboxgl-popup-tip {

  border-top-color: var(--light) !important;

}



.popup-title {

  font-weight: 600;

  margin-bottom: 8px;

  color: var(--dark);

  font-size: 16px;

}



.popup-info {

  color: var(--gray-600);

  margin-bottom: 4px;

  display: flex;

  align-items: center;

  gap: 8px;

  font-size: 14px;

}



.popup-info i {

  color: var(--dark);

  width: 16px;

  text-align: center;

}



/* Custom markers */

.custom-marker {

  width: 32px;

  height: 32px;

  border-radius: 50%;

  display: flex;

  align-items: center;

  justify-content: center;

  color: var(--light);

  font-size: 14px;

  box-shadow: var(--shadow-lg);

  cursor: pointer;

  transition: var(--transition);

  border: 2px solid var(--light);

}



.custom-marker:hover {

  transform: scale(1.1);

}



.restaurant-marker {

  background: var(--dark);

}



.delivery-marker {

  background: var(--success);

}



.courier-marker {

  background: var(--warning);

  animation: courierPulse 2s infinite;

}



@keyframes courierPulse {
  0%, 100% { 
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
  }
  50% { 
    transform: scale(1.1);
    box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
  }
}

/* Animación para indicador "En vivo" */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

/* Estilos para marcador de usuario */
.user-marker {
  background: var(--primary);
  border-color: var(--primary-light);
}

/* Estilos para información de ruta */
.route-info {
  margin: 16px 0;
  padding: 16px;
  background: var(--light);
  border-radius: 12px;
  border: 1px solid var(--gray-200);
  box-shadow: var(--shadow-sm);
}

.route-info h4 {
  margin: 0 0 12px 0;
  color: var(--dark);
  font-size: 16px;
}

.route-info div {
  display: flex;
  gap: 24px;
  font-size: 14px;
  color: var(--gray-600);
}

/* =======================================================
   MODO OSCURO - CONFIRMACION_PEDIDO.PHP
   ======================================================= */
@media (prefers-color-scheme: dark) {
    :root {
        --body-bg: #000000;
        --white: #111111;
        --light: #111111;
        --gray-100: #1a1a1a;
        --gray-200: #333333;
        --gray-900: #ffffff;
        --gray-800: #e0e0e0;
        --gray-700: #cccccc;
        --gray-600: #aaaaaa;
        --dark: #ffffff;
    }

    body {
        background-color: #000000 !important;
        color: #e0e0e0;
    }

    .confirmation-header, .page-header {
        background: #000000 !important;
        border-bottom: 1px solid #333;
    }

    .page-title, .confirmation-title {
        color: #fff !important;
    }

    .order-card, .confirmation-card, .tracking-card {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .card-title, .section-title {
        color: #fff !important;
    }

    .order-item {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }

    .item-name {
        color: #fff !important;
    }

    .item-details, .item-quantity {
        color: #aaa !important;
    }

    .order-summary {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .summary-row {
        color: #e0e0e0 !important;
        border-color: #333 !important;
    }

    .summary-total {
        color: #fff !important;
    }

    .tracking-step, .step-item {
        background: #1a1a1a !important;
    }

    .step-title {
        color: #fff !important;
    }

    .step-description, .step-time {
        color: #888 !important;
    }

    .delivery-info, .address-info {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .info-label {
        color: #888 !important;
    }

    .info-value {
        color: #fff !important;
    }

    .route-info {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .route-info h4 {
        color: #fff !important;
    }

    .route-info div {
        color: #aaa !important;
    }

    /* Mapbox en modo oscuro */
    .mapboxgl-popup-content {
        background: #111111 !important;
        color: #ffffff !important;
    }

    .mapboxgl-popup-tip {
        border-top-color: #111111 !important;
    }
}

/* Soporte para data-theme="dark" */
[data-theme="dark"] body,
html.dark-mode body {
    background-color: #000000 !important;
    color: #e0e0e0;
}

[data-theme="dark"] .confirmation-header,
[data-theme="dark"] .page-header,
html.dark-mode .confirmation-header,
html.dark-mode .page-header {
    background: #000000 !important;
    border-bottom: 1px solid #333;
}

[data-theme="dark"] .page-title,
[data-theme="dark"] .confirmation-title,
html.dark-mode .page-title,
html.dark-mode .confirmation-title {
    color: #fff !important;
}

[data-theme="dark"] .order-card,
[data-theme="dark"] .confirmation-card,
[data-theme="dark"] .tracking-card,
html.dark-mode .order-card,
html.dark-mode .confirmation-card,
html.dark-mode .tracking-card {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .card-title,
[data-theme="dark"] .section-title,
html.dark-mode .card-title,
html.dark-mode .section-title {
    color: #fff !important;
}

[data-theme="dark"] .order-item,
html.dark-mode .order-item {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .item-name,
html.dark-mode .item-name {
    color: #fff !important;
}

[data-theme="dark"] .item-details,
[data-theme="dark"] .item-quantity,
html.dark-mode .item-details,
html.dark-mode .item-quantity {
    color: #aaa !important;
}

[data-theme="dark"] .order-summary,
html.dark-mode .order-summary {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .summary-row,
html.dark-mode .summary-row {
    color: #e0e0e0 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .delivery-info,
[data-theme="dark"] .address-info,
html.dark-mode .delivery-info,
html.dark-mode .address-info {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .info-label,
html.dark-mode .info-label {
    color: #888 !important;
}

[data-theme="dark"] .info-value,
html.dark-mode .info-value {
    color: #fff !important;
}

[data-theme="dark"] .route-info,
html.dark-mode .route-info {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .route-info h4,
html.dark-mode .route-info h4 {
    color: #fff !important;
}

[data-theme="dark"] .mapboxgl-popup-content,
html.dark-mode .mapboxgl-popup-content {
    background: #111111 !important;
    color: #ffffff !important;
}

</style>

</head>

<body>
<?php include_once 'includes/valentine.php'; ?>

  <div class="main-container">

    <!-- Header -->

    <div class="page-header">

      <button class="back-button" onclick="window.history.back()">

        <i class="fas fa-chevron-left"></i>

      </button>

      <h1 class="page-title">Seguimiento de Pedido</h1>

    </div>



    <!-- Map -->

    <div class="map-container">

      <div id="map"></div>

      <div class="map-overlay" id="map-info">

        <?php if ($es_pickup): ?>

          <div><i class="fas fa-store me-2"></i><strong>Restaurante:</strong> <?php echo htmlspecialchars($negocio->nombre ?? 'Negocio', ENT_QUOTES, 'UTF-8'); ?></div>

          <div><i class="fas fa-map-marker-alt me-2"></i><strong>Ubicación del negocio</strong></div>

        <?php else: ?>

          <div><i class="fas fa-route me-2"></i><strong>Distancia:</strong> <span id="route-distance">Calculando...</span></div>

          <div><i class="fas fa-clock me-2"></i><strong>Tiempo:</strong> <span id="route-time">Calculando...</span></div>

          <?php if ($estado_actual == 5 && $repartidor_info): ?>

          <div class="mt-2 pt-2 border-top">

            <small><i class="fas fa-motorcycle me-2"></i><?php echo explode(' - ', $repartidor_info)[0]; ?></small>

          </div>

          <?php endif; ?>

        <?php endif; ?>

      </div>

    </div>



    <!-- Product Info -->

    <div class="product-info">

      <div class="product-header">

        <div class="product-icon">

          <i class="fas fa-utensils"></i>

        </div>

        <div class="product-details">

          <div class="product-name"><?php echo htmlspecialchars($negocio->nombre ?? 'Pedido QuickBite'); ?></div>

          <div class="product-category">Restaurante • <span class="rating-stars">★</span> <span class="rating-number"><?php echo htmlspecialchars($negocio->rating ?? '4.5'); ?></span></div>

        </div>

      </div>



      <!-- SPEI Payment Info -->
      <?php if ($spei_data && $estado_actual == 7): ?>
      <div class="spei-payment-info" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 2px solid #ffc107; border-radius: 15px; padding: 20px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
          <i class="fas fa-university" style="font-size: 28px; color: #856404; margin-right: 12px;"></i>
          <div>
            <h4 style="margin: 0; color: #856404; font-weight: 700;">Pago Pendiente - Transferencia SPEI</h4>
            <small style="color: #856404;">Realiza la transferencia para confirmar tu pedido</small>
          </div>
        </div>

        <div style="background: white; border-radius: 10px; padding: 15px; margin-bottom: 15px;">
          <?php if (!empty($spei_data['clabe'])): ?>
          <div style="margin-bottom: 12px;">
            <label style="font-size: 12px; color: #666; display: block;">CLABE Interbancaria</label>
            <div style="font-size: 18px; font-weight: 700; font-family: monospace; color: #333;">
              <?php echo htmlspecialchars($spei_data['clabe']); ?>
              <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($spei_data['clabe']); ?>'); alert('CLABE copiada');" style="background: #007bff; color: white; border: none; border-radius: 5px; padding: 5px 10px; font-size: 12px; margin-left: 10px; cursor: pointer;">
                <i class="fas fa-copy"></i> Copiar
              </button>
            </div>
          </div>
          <?php endif; ?>

          <div style="margin-bottom: 12px;">
            <label style="font-size: 12px; color: #666; display: block;">Monto Exacto</label>
            <div style="font-size: 22px; font-weight: 700; color: #28a745;">
              $<?php echo number_format($spei_data['amount'], 2); ?> MXN
            </div>
          </div>

          <div style="margin-bottom: 12px;">
            <label style="font-size: 12px; color: #666; display: block;">Referencia / Concepto</label>
            <div style="font-size: 14px; font-weight: 600; color: #333;">
              <?php echo htmlspecialchars($spei_data['external_reference']); ?>
            </div>
          </div>

          <?php if (!empty($spei_data['expires_at'])): ?>
          <div>
            <label style="font-size: 12px; color: #666; display: block;">Fecha límite de pago</label>
            <div style="font-size: 14px; color: #dc3545; font-weight: 600;">
              <?php echo date('d/m/Y H:i', strtotime($spei_data['expires_at'])); ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($spei_data['ticket_url'])): ?>
        <a href="<?php echo htmlspecialchars($spei_data['ticket_url']); ?>" target="_blank" style="display: block; background: #007bff; color: white; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600;">
          <i class="fas fa-external-link-alt"></i> Ver comprobante completo
        </a>
        <?php endif; ?>

        <div style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 8px; font-size: 13px; color: #666;">
          <strong>Instrucciones:</strong><br>
          1. Ingresa a tu banca en línea o app bancaria<br>
          2. Selecciona transferencia SPEI<br>
          3. Ingresa la CLABE y el monto exacto<br>
          4. Tu pago se acreditará en minutos
        </div>
      </div>
      <?php endif; ?>

      <!-- Timeline -->
      <div class="timeline-container">
        <div class="timeline-title">
          <i class="fas fa-clock"></i>
          Seguimiento del pedido
        </div>
        
        <?php foreach ($pasos_timeline as $numero_paso => $paso_info): ?>
          <?php 
            $estado_paso = obtenerEstadoPaso($numero_paso, $estado_actual, $pasos_timeline);
            $es_completado = ($estado_paso === 'completed');
            $es_activo = ($estado_paso === 'active');
            $icono = $es_completado ? $paso_info['icono_completado'] : $paso_info['icono'];
            $tiempo = obtenerTiempoPaso($numero_paso, $estado_actual, $datos_pedido['fecha_pedido'] ?? 'now', $es_pickup);
          ?>
          <div class="timeline-step <?php echo $estado_paso; ?>">
            <div class="step-indicator <?php echo $estado_paso; ?>">
              <i class="<?php echo $icono; ?>"></i>
            </div>
            <div class="step-content">
              <div class="step-title"><?php echo $paso_info['titulo']; ?></div>
              <div class="step-subtitle"><?php echo $paso_info['subtitulo']; ?></div>
              <div class="step-time <?php echo $es_completado ? 'completed' : ''; ?>">
                <?php echo $tiempo; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>



    <!-- Action Section -->

    <div class="action-section">

      <button class="cancel-button" onclick="confirmCancel()">Cancelar pedido</button>

      <button class="home-button" onclick="window.location.href='index.php'">

        <i class="fas fa-home"></i>

        Volver a la página principal

      </button>

    </div>

  </div>

  <!-- Mapbox JavaScript -->

  <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>

  

  <script>

    // Configuration

    const config = {

      userId: <?php echo $_SESSION['id_usuario'] ?? 'null'; ?>,

      orderId: <?php echo $id_pedido ?? 'null'; ?>,

      currentStatus: <?php echo $estado_actual ?? 1; ?>,

      isPickup: <?php echo $es_pickup ? 'true' : 'false'; ?>,

      deliveryLat: <?php echo $deliveryLat ?? 21.4167; ?>,

      deliveryLng: <?php echo $deliveryLng ?? -102.5667; ?>,

      restaurantLat: <?php echo $restaurantLat ?? 21.4167; ?>,

      restaurantLng: <?php echo $restaurantLng ?? -102.5667; ?>,

      mapboxToken: '<?php echo MAPBOX_TOKEN; ?>'

    };

    // Global variables for route animation
    let map = null;
    let routeAnimationId = null;

    // Route animation function
    function iniciarAnimacionRuta(route, routeId) {
      if (!route || !route.geometry || !route.geometry.coordinates) {
        console.error('Route data is invalid for animation');
        return;
      }
      
      const coordinates = route.geometry.coordinates;
      const totalFrames = 600; // 10 segundos a 60 FPS
      let currentFrame = 0;
      
      // Cancelar animación anterior si existe
      if (routeAnimationId) {
        cancelAnimationFrame(routeAnimationId);
      }
      
      function animate() {
        currentFrame++;
        const progress = currentFrame / totalFrames;
        
        if (progress <= 1) {
          // Calcular cuántas coordenadas mostrar basado en el progreso
          const coordsToShow = Math.floor(coordinates.length * progress);
          const progressCoords = coordinates.slice(0, Math.max(1, coordsToShow));
          
          // Actualizar la línea de progreso
          if (map.getSource(routeId + '-progress')) {
            map.getSource(routeId + '-progress').setData({
              type: 'Feature',
              properties: {},
              geometry: {
                type: 'LineString',
                coordinates: progressCoords
              }
            });
          }
          
          routeAnimationId = requestAnimationFrame(animate);
        } else {
          routeAnimationId = null;
        }
      }
      
      animate();
    }



    // Initialize map

    function initializeMap() {

      mapboxgl.accessToken = config.mapboxToken;

      

      map = new mapboxgl.Map({

        container: 'map',

        style: 'mapbox://styles/mapbox/light-v11',

        center: [config.deliveryLng, config.deliveryLat],

        zoom: 14,

        pitch: 0,

        bearing: 0

      });



      map.on('load', function() {

        // Add restaurant marker

        const restaurantMarker = document.createElement('div');

        restaurantMarker.className = 'custom-marker restaurant-marker';

        restaurantMarker.innerHTML = '<i class="fas fa-store"></i>';

        

        new mapboxgl.Marker(restaurantMarker)

          .setLngLat([config.restaurantLng, config.restaurantLat])

          .setPopup(new mapboxgl.Popup({ offset: 25 })

            .setHTML(`

              <div class="popup-title"><?php echo htmlspecialchars($negocio->nombre ?? 'Restaurante'); ?></div>

              <div class="popup-info">

                <i class="fas fa-map-marker-alt"></i>

                Ubicación del restaurante

              </div>

            `))

          .addTo(map);



        <?php if (!$es_pickup): ?>

        // Add delivery marker for regular orders

        const deliveryMarker = document.createElement('div');

        deliveryMarker.className = 'custom-marker delivery-marker';

        deliveryMarker.innerHTML = '<i class="fas fa-home"></i>';

        

        new mapboxgl.Marker(deliveryMarker)

          .setLngLat([config.deliveryLng, config.deliveryLat])

          .setPopup(new mapboxgl.Popup({ offset: 25 })

            .setHTML(`

              <div class="popup-title">Dirección de entrega</div>

              <div class="popup-info">

                <i class="fas fa-home"></i>

                <?php echo htmlspecialchars($direccion->nombre_direccion ?? 'Tu domicilio'); ?>

              </div>

            `))

          .addTo(map);



        // Fit bounds to show both markers

        const bounds = new mapboxgl.LngLatBounds();

        bounds.extend([config.restaurantLng, config.restaurantLat]);

        bounds.extend([config.deliveryLng, config.deliveryLat]);

        map.fitBounds(bounds, { padding: 50 });
        
        // Initialize delivery route tracking
        setTimeout(initializeRouteTracking, 1500);

        <?php else: ?>

        // For pickup orders, center on restaurant

        map.setCenter([config.restaurantLng, config.restaurantLat]);

        <?php endif; ?>

      });

    }


    // Initialize route tracking for delivery orders
    function initializeRouteTracking() {
      const estadoActual = <?php echo $estado_actual; ?>;
      
      // If order is ready (estado 4+), show route from restaurant to delivery
      if (estadoActual >= 4) {
        setTimeout(() => {
          obtenerRuta(
            [config.restaurantLng, config.restaurantLat], 
            [config.deliveryLng, config.deliveryLat],
            'delivery-route'
          );
        }, 1000);
      }
      
      // If courier is on the way (estado 5+), start live tracking
      if (estadoActual >= 5) {
        setTimeout(() => {
          iniciarSeguimientoRepartidor();
        }, 2000);
      }
    }
    
    // Initialize pickup tracking - route from user to restaurant  
    function initializePickupTracking() {
      console.log('Starting pickup tracking...');
      
      // Get user's current location
      if (navigator.geolocation) {
        console.log('Geolocation available, requesting position...');
        
        navigator.geolocation.getCurrentPosition(function(position) {
          const userLat = position.coords.latitude;
          const userLng = position.coords.longitude;
          
          console.log('User location obtained:', { userLat, userLng });
          
          // Add user location marker
          const userMarker = document.createElement('div');
          userMarker.className = 'custom-marker user-marker';
          userMarker.innerHTML = '<i class="fas fa-user"></i>';
          
          new mapboxgl.Marker(userMarker)
            .setLngLat([userLng, userLat])
            .setPopup(new mapboxgl.Popup({ offset: 25 })
              .setHTML(`
                <div class="popup-title">Tu ubicación</div>
                <div class="popup-info">
                  <i class="fas fa-map-marker-alt"></i>
                  Ubicación actual
                </div>
              `))
            .addTo(map);
          
          console.log('User marker added to map');
          
          // Show route from user to restaurant
          setTimeout(() => {
            console.log('Requesting route from user to restaurant...');
            obtenerRuta([userLng, userLat], [config.restaurantLng, config.restaurantLat], 'pickup-route');
          }, 500);
          
          // Fit bounds to show both locations
          const bounds = new mapboxgl.LngLatBounds();
          bounds.extend([userLng, userLat]);
          bounds.extend([config.restaurantLng, config.restaurantLat]);
          map.fitBounds(bounds, { padding: 80 });
          
        }, function(error) {
          let errorMsg = 'Error obteniendo ubicación: ';
          
          switch(error.code) {
            case error.PERMISSION_DENIED:
              errorMsg += 'Por favor permite el acceso a tu ubicación en la configuración del navegador.';
              break;
            case error.POSITION_UNAVAILABLE:
              errorMsg += 'Ubicación no disponible. Verifica tu conexión GPS/WiFi.';
              break;
            case error.TIMEOUT:
              errorMsg += 'Tiempo de espera agotado. Intentando nuevamente...';
              // Reintentar después de 3 segundos
              setTimeout(() => {
                console.log('Reintentando obtener ubicación...');
                initializePickupTracking();
              }, 3000);
              return;
            default:
              errorMsg += 'Error desconocido.';
          }
          
          console.error(errorMsg, error);
          
          // Mostrar mensaje en el overlay del mapa en lugar de alert
          const mapInfo = document.getElementById('map-info');
          if (mapInfo) {
            mapInfo.innerHTML = `
              <div style="color: #ef4444;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error de ubicación</strong><br>
                <small>${errorMsg}</small>
              </div>
            `;
          }
          
          // Fallback: centrar en restaurante
          map.setCenter([config.restaurantLng, config.restaurantLat]);
        }, {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 60000
        });
      } else {
        console.error('Geolocation not supported');
        alert('Tu navegador no soporta geolocalización.');
      }
    }
    
    // Get route using Mapbox Directions API
    function obtenerRuta(origen, destino, routeId) {
      console.log('Obteniendo ruta:', { origen, destino, routeId });
      
      const url = `https://api.mapbox.com/directions/v5/mapbox/driving/${origen[0]},${origen[1]};${destino[0]},${destino[1]}?geometries=geojson&access_token=${config.mapboxToken}`;
      
      console.log('URL de ruta:', url);
      
      fetch(url)
        .then(response => {
          console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          console.log('Route data received:', data);
          
          if (data.routes && data.routes.length > 0) {
            const route = data.routes[0];
            console.log('Adding route to map:', routeId);
            
            // Limpiar rutas anteriores
            if (map.getSource(routeId)) {
              map.removeLayer(routeId);
              map.removeSource(routeId);
            }
            if (map.getSource(routeId + '-progress')) {
              map.removeLayer(routeId + '-progress');
              map.removeSource(routeId + '-progress');
            }
            
            // Agregar ruta base (línea completa en negro con opacidad baja)
            map.addSource(routeId, {
              type: 'geojson',
              data: {
                type: 'Feature',
                properties: {},
                geometry: route.geometry
              }
            });
            
            map.addLayer({
              id: routeId,
              type: 'line',
              source: routeId,
              layout: {
                'line-join': 'round',
                'line-cap': 'round'
              },
              paint: {
                'line-color': '#000000',
                'line-width': 4,
                'line-opacity': 0.3
              }
            });
            
            // Crear línea de progreso (inicialmente vacía)
            map.addSource(routeId + '-progress', {
              type: 'geojson',
              data: {
                type: 'Feature',
                properties: {},
                geometry: {
                  type: 'LineString',
                  coordinates: []
                }
              }
            });
            
            map.addLayer({
              id: routeId + '-progress',
              type: 'line',
              source: routeId + '-progress',
              layout: {
                'line-join': 'round',
                'line-cap': 'round'
              },
              paint: {
                'line-color': '#000000',
                'line-width': 6,
                'line-opacity': 1
              }
            });
            
            // Iniciar animación progresiva
            setTimeout(() => {
              iniciarAnimacionRuta(route, routeId);
            }, 500);
            
            // Show route info
            mostrarInfoRuta(route, routeId === 'pickup-route');
          } else {
            console.error('No routes found in response');
          }
        })
        .catch(error => {
          console.error('Error getting route:', error);
        });
    }
    
    // Show route information
    function mostrarInfoRuta(route, esPickup) {
      // Validar datos de ruta antes de usarlos
      if (!route || !route.duration || !route.distance) {
        console.error('Datos de ruta incompletos');
        return;
      }
      
      const duration = route.duration ? Math.round(route.duration / 60) : 0; // Convert to minutes
      const distance = route.distance ? (route.distance / 1000).toFixed(1) : '0.0'; // Convert to km
      
      const infoHTML = `
        <div class="route-info" style="
          margin: 16px 0;
          padding: 16px;
          background: var(--light);
          border-radius: 12px;
          border: 1px solid var(--gray-200);
          box-shadow: var(--shadow-sm);
        ">
          <h4 style="margin: 0 0 12px 0; color: var(--dark); font-size: 16px;">
            <i class="fas fa-route" style="color: ${esPickup ? '#0165FF' : '#22c55e'}; margin-right: 8px;"></i> 
            ${esPickup ? 'Ruta al restaurante' : 'Ruta de entrega'}
          </h4>
          <div style="display: flex; gap: 24px; font-size: 14px; color: var(--gray-600);">
            <span><i class="fas fa-clock" style="margin-right: 4px;"></i> ${duration} min</span>
            <span><i class="fas fa-road" style="margin-right: 4px;"></i> ${distance} km</span>
          </div>
        </div>
      `;
      
      // Add route info after map container
      const mapContainer = document.querySelector('.map-container');
      if (mapContainer && !document.querySelector('.route-info')) {
        mapContainer.insertAdjacentHTML('afterend', infoHTML);
      }
    }
    
    // ==========================================
    // TRACKING EN TIEMPO REAL DEL REPARTIDOR
    // ==========================================
    
    let courierMarker = null;
    let trackingInterval = null;
    let lastKnownPosition = null;
    const TRACKING_INTERVAL = 5000; // Actualizar cada 5 segundos
    const ORDER_ID = <?php echo $id_pedido; ?>;
    
    // Start courier live tracking with real-time data
    function iniciarSeguimientoRepartidor() {
      console.log('🚀 Iniciando tracking en tiempo real del repartidor...');
      
      // Create courier marker
      const courierElement = document.createElement('div');
      courierElement.className = 'custom-marker courier-marker';
      courierElement.innerHTML = '<i class="fas fa-motorcycle"></i>';
      
      courierMarker = new mapboxgl.Marker(courierElement)
        .setPopup(new mapboxgl.Popup({ offset: 25 })
          .setHTML(`
            <div class="popup-title">Repartidor</div>
            <div class="popup-info">
              <i class="fas fa-spinner fa-spin"></i>
              Conectando con tracking GPS...
            </div>
          `))
        .addTo(map);
      
      // Iniciar en el restaurante mientras se obtiene la ubicación real
      courierMarker.setLngLat([config.restaurantLng, config.restaurantLat]);
      
      // Primera consulta de ubicación
      obtenerUbicacionRepartidor();
      
      // Configurar polling para actualizaciones periódicas
      trackingInterval = setInterval(obtenerUbicacionRepartidor, TRACKING_INTERVAL);
      
      // Limpiar interval cuando se cierre la página
      window.addEventListener('beforeunload', () => {
        if (trackingInterval) clearInterval(trackingInterval);
      });
    }
    
    // Obtener ubicación real del repartidor desde el servidor
    async function obtenerUbicacionRepartidor() {
      try {
        const response = await fetch(`/admin/obtener_ubicacion_repartidor.php?order_id=${ORDER_ID}`, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('📍 Ubicación del repartidor:', data);
        
        if (data.success) {
          if (data.tracking_active && data.location) {
            // Actualizar marcador con ubicación real
            actualizarPosicionRepartidor(data);
          } else if (data.courier_assigned && !data.tracking_active) {
            // Repartidor asignado pero sin GPS activo
            mostrarMensajeEsperandoGPS(data);
          } else if (!data.courier_assigned) {
            // Sin repartidor asignado
            mostrarMensajeSinRepartidor();
          }
        } else {
          console.warn('⚠️ Error obteniendo ubicación:', data.message);
          // Fallback: usar simulación si falla el tracking real
          if (!lastKnownPosition) {
            usarSimulacionFallback();
          }
        }
      } catch (error) {
        console.error('❌ Error en tracking:', error);
        // Fallback: usar simulación si falla la conexión
        if (!lastKnownPosition) {
          usarSimulacionFallback();
        }
      }
    }
    
    // Actualizar posición del repartidor en el mapa
    function actualizarPosicionRepartidor(data) {
      const { location, courier_info, eta, tracking_details } = data;
      const newLng = location.longitude;
      const newLat = location.latitude;
      
      // Animar movimiento suave si hay posición anterior
      if (lastKnownPosition && courierMarker) {
        animarMovimiento(lastKnownPosition, { lng: newLng, lat: newLat });
      } else if (courierMarker) {
        courierMarker.setLngLat([newLng, newLat]);
      }
      
      // Guardar posición actual
      lastKnownPosition = { lng: newLng, lat: newLat };
      
      // Actualizar popup con información real
      const etaText = eta?.text || 'Calculando...';
      const speedText = tracking_details?.speed ? `${Math.round(tracking_details.speed * 3.6)} km/h` : '';
      const freshnessText = location.minutes_ago < 1 ? 'En vivo' : `Hace ${Math.round(location.minutes_ago)} min`;
      
      courierMarker.getPopup().setHTML(`
        <div class="popup-title">
          <i class="fas fa-circle" style="color: #22c55e; font-size: 8px; animation: pulse 1s infinite;"></i>
          Repartidor en camino
        </div>
        <div class="popup-info">
          <strong>${courier_info?.name || 'Repartidor'}</strong><br>
          <i class="fas fa-clock"></i> ETA: ${etaText}<br>
          ${speedText ? `<i class="fas fa-tachometer-alt"></i> ${speedText}<br>` : ''}
          <small style="color: #888;">${freshnessText}</small>
        </div>
      `);
      
      // Actualizar info en overlay del mapa
      const mapInfo = document.getElementById('map-info');
      if (mapInfo) {
        document.getElementById('route-time').textContent = etaText;
      }
      
      // Centrar mapa para mostrar repartidor y destino
      const bounds = new mapboxgl.LngLatBounds();
      bounds.extend([newLng, newLat]);
      bounds.extend([config.deliveryLng, config.deliveryLat]);
      map.fitBounds(bounds, { padding: 80, maxZoom: 16 });
    }
    
    // Animar movimiento suave del marcador
    function animarMovimiento(from, to, duration = 1000) {
      const startTime = performance.now();
      
      function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing suave
        const easeProgress = 1 - Math.pow(1 - progress, 3);
        
        const currentLng = from.lng + (to.lng - from.lng) * easeProgress;
        const currentLat = from.lat + (to.lat - from.lat) * easeProgress;
        
        courierMarker.setLngLat([currentLng, currentLat]);
        
        if (progress < 1) {
          requestAnimationFrame(animate);
        }
      }
      
      requestAnimationFrame(animate);
    }
    
    // Mostrar mensaje cuando GPS no está activo
    function mostrarMensajeEsperandoGPS(data) {
      if (courierMarker) {
        courierMarker.setLngLat([config.restaurantLng, config.restaurantLat]);
        courierMarker.getPopup().setHTML(`
          <div class="popup-title">Repartidor asignado</div>
          <div class="popup-info">
            <strong>${data.courier_info?.name || 'Repartidor'}</strong><br>
            <i class="fas fa-satellite-dish" style="color: #f59e0b;"></i>
            Esperando señal GPS...<br>
            <small>El seguimiento iniciará pronto</small>
          </div>
        `);
      }
    }
    
    // Mostrar mensaje sin repartidor
    function mostrarMensajeSinRepartidor() {
      if (courierMarker) {
        courierMarker.getPopup().setHTML(`
          <div class="popup-title">Buscando repartidor</div>
          <div class="popup-info">
            <i class="fas fa-search" style="color: #0165FF;"></i>
            Asignando un repartidor a tu pedido...
          </div>
        `);
      }
    }
    
    // Usar simulación como fallback si tracking real falla
    function usarSimulacionFallback() {
      console.log('⚠️ Usando simulación como fallback');
      
      // Detener el polling real
      if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
      }
      
      // Obtener ruta y simular
      const url = `https://api.mapbox.com/directions/v5/mapbox/driving/${config.restaurantLng},${config.restaurantLat};${config.deliveryLng},${config.deliveryLat}?geometries=geojson&access_token=${config.mapboxToken}`;
      
      fetch(url)
        .then(response => response.json())
        .then(data => {
          if (data.routes && data.routes.length > 0) {
            simularMovimientoRepartidor(courierMarker, data.routes[0]);
          } else {
            simularMovimientoRepartidorLineal(courierMarker);
          }
        })
        .catch(error => {
          console.error('Error obteniendo ruta:', error);
          simularMovimientoRepartidorLineal(courierMarker);
        });
    }
    
    // Simulate courier movement following the actual route (fallback)
    function simularMovimientoRepartidor(marker, route) {
      if (!route || !route.geometry || !route.geometry.coordinates) {
        console.error('No route data for courier simulation');
        simularMovimientoRepartidorLineal(marker);
        return;
      }
      
      const coordinates = route.geometry.coordinates;
      let currentIndex = 0;
      const totalPoints = coordinates.length;
      const interval = 3000; // 3 segundos por punto
      
      // Calcular cuántos puntos saltar para completar en ~2 minutos
      const skipPoints = Math.max(1, Math.floor(totalPoints / 40));
      
      const updatePosition = () => {
        if (currentIndex < totalPoints) {
          const [lng, lat] = coordinates[currentIndex];
          
          // Animar el movimiento suavemente
          marker.setLngLat([lng, lat]);
          
          // Actualizar progreso
          const percentage = Math.min(100, Math.round((currentIndex / totalPoints) * 100));
          
          marker.getPopup().setHTML(`
            <div class="popup-title">Repartidor en camino</div>
            <div class="popup-info">
              <i class="fas fa-motorcycle"></i>
              Progreso: ${percentage}%<br>
              <small style="color: #888;">Simulación</small>
            </div>
          `);
          
          currentIndex += skipPoints;
          setTimeout(updatePosition, interval);
        } else {
          // Llegó al destino
          marker.setLngLat([config.deliveryLng, config.deliveryLat]);
          marker.getPopup().setHTML(`
            <div class="popup-title">¡Repartidor ha llegado!</div>
            <div class="popup-info">
              <i class="fas fa-check-circle" style="color: #22c55e;"></i>
              El repartidor está en tu ubicación
            </div>
          `);
        }
      };
      
      // Iniciar en el primer punto de la ruta
      marker.setLngLat(coordinates[0]);
      setTimeout(updatePosition, 2000);
    }
    
    // Fallback: Simulate courier movement linearly (if route fails)
    function simularMovimientoRepartidorLineal(marker) {
      const startLng = config.restaurantLng;
      const startLat = config.restaurantLat;
      const endLng = config.deliveryLng;
      const endLat = config.deliveryLat;
      
      let progress = 0;
      const interval = 3000; // Update every 3 seconds
      
      const updatePosition = () => {
        if (progress <= 1) {
          // Linear interpolation between start and end points
          const currentLng = startLng + (endLng - startLng) * progress;
          const currentLat = startLat + (endLat - startLat) * progress;
          
          marker.setLngLat([currentLng, currentLat]);
          progress += 0.02; // 2% progress each update
          
          // Update courier popup with progress
          const percentage = Math.round(progress * 100);
          marker.getPopup().setHTML(`
            <div class="popup-title">Repartidor en camino</div>
            <div class="popup-info">
              <i class="fas fa-motorcycle"></i>
              Progreso: ${percentage > 100 ? 100 : percentage}%<br>
              <small style="color: #888;">Simulación</small>
            </div>
          `);
          
          setTimeout(updatePosition, interval);
        } else {
          // Arrived at destination
          marker.getPopup().setHTML(`
            <div class="popup-title">¡Repartidor ha llegado!</div>
            <div class="popup-info">
              <i class="fas fa-check-circle" style="color: #22c55e;"></i>
              El repartidor está en tu ubicación
            </div>
          `);
        }
      };
      
      // Start at restaurant
      marker.setLngLat([startLng, startLat]);
      setTimeout(updatePosition, 2000); // Start moving after 2 seconds
    }

    // Cancel order function

    function confirmCancel() {

      if (confirm('¿Estás seguro de que quieres cancelar este pedido?')) {

        // Here you would implement the cancel order logic

        window.location.href = 'index.php';

      }

    }



    // Initialize when page loads

    document.addEventListener('DOMContentLoaded', function() {

      initializeMap();
      
      // Initialize route tracking after map is loaded
      <?php if ($es_pickup): ?>
      setTimeout(() => {
        console.log('Initializing pickup tracking...');
        initializePickupTracking();
      }, 2000);
      <?php else: ?>
      setTimeout(() => {
        console.log('Initializing delivery tracking...');
        initializeRouteTracking();
      }, 2000);
      <?php endif; ?>

    });

  </script>

</body>
</html>
