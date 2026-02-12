<?php
// ===========================
// MANEJO DE SOLICITUDES AJAX PRIMERO (ANTES DE CUALQUIER SALIDA)
// ===========================

session_start();

// Configuración específica para Cloudflare y AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_detalles') {
    // Configurar headers específicos para Cloudflare antes de cualquier salida
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Headers específicos para Cloudflare
    header('CF-Cache-Status: BYPASS');
    header('X-Robots-Tag: noindex, nofollow');
    
    // CORS headers para evitar bloqueos
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        $allowed_domains = [
            $_SERVER['HTTP_HOST'],
            'localhost',
            '127.0.0.1'
        ];
        
        foreach ($allowed_domains as $domain) {
            if (strpos($origin, $domain) !== false) {
                header("Access-Control-Allow-Origin: $origin");
                break;
            }
        }
    }
    
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    
    // Limpiar cualquier buffer de salida previo
    if (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // Verificar parámetros requeridos
        if (!isset($_GET['id_pedido']) || !isset($_GET['csrf_token'])) {
            throw new Exception('Parámetros requeridos faltantes');
        }
        
        // Generar token CSRF si no existe
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Verificar token CSRF
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
            throw new Exception('Token CSRF inválido');
        }
        
        // Verificar autenticación
        if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["tipo_usuario"]) || $_SESSION["tipo_usuario"] !== "negocio") {
            throw new Exception("No autorizado");
        }
        
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../models/Usuario.php';
        require_once __DIR__ . '/../models/Negocio.php';
        
        $database = new Database();
        $db = $database->getConnection();
        $id_pedido = (int)$_GET['id_pedido'];
        
        // Obtener información del negocio
        $usuario = new Usuario($db);
        $usuario->id_usuario = $_SESSION["id_usuario"];
        $usuario->obtenerPorId();
        
        $negocio = new Negocio($db);
        $negocios = $negocio->obtenerPorIdPropietario($usuario->id_usuario);
        
        if (empty($negocios)) {
            throw new Exception('Negocio no encontrado');
        }
        
        $negocio_info = $negocios[0];
        $id_negocio = $negocio_info['id_negocio'];
        
        // Consulta principal del pedido
        $query = "SELECT p.*, 
                u.nombre as nombre_cliente,
                u.apellido as apellido_cliente,
                u.email as email_cliente,
                u.telefono as telefono_cliente,
                d.calle,
                d.numero,
                d.colonia,
                d.ciudad,
                d.estado,
                d.codigo_postal,
                d.referencias,
                mp.tipo_pago,
                mp.proveedor,
                mp.numero_cuenta
                FROM pedidos p
                LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
                LEFT JOIN metodos_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
                WHERE p.id_pedido = :id_pedido AND p.id_negocio = :id_negocio";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
        $stmt->bindParam(':id_negocio', $id_negocio, PDO::PARAM_INT);
        $stmt->execute();
        
        $pedido_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido_info) {
            throw new Exception('Pedido no encontrado o no pertenece a tu negocio');
        }
        
        // Obtener items del pedido
        $query_items = "SELECT 
                            dp.*,
                            p.nombre,
                            p.descripcion
                        FROM detalles_pedido dp
                        LEFT JOIN productos p ON dp.id_producto = p.id_producto
                        WHERE dp.id_pedido = :id_pedido
                        ORDER BY dp.id_detalle_pedido";
        
        $stmt_items = $db->prepare($query_items);
        $stmt_items->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
        $stmt_items->execute();
        
        // Cargar modelo de personalización
        require_once __DIR__ . '/../models/PersonalizacionUnidad.php';
        $personalizacionModel = new PersonalizacionUnidad($db);

        $items = [];
        while ($item = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
            $id_detalle = (int)$item['id_detalle_pedido'];

            // Obtener personalización por unidad (incluye mensajes)
            $personalizacion = $personalizacionModel->obtenerPersonalizacion($id_detalle);

            $items[] = [
                'id_detalle_pedido' => $id_detalle,
                'id_producto' => (int)($item['id_producto'] ?? 0),
                'nombre' => $item['nombre'] ?? 'Producto',
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (int)$item['cantidad'],
                'precio_unitario' => (float)$item['precio_unitario'],
                'precio_total' => (float)$item['precio_total'],
                'instrucciones_especiales' => $item['instrucciones_especiales'] ?? '',
                'opciones' => [],
                'personalizacion' => $personalizacion
            ];
        }
        
        // Si no hay items, crear uno genérico
        if (empty($items)) {
            $items[] = [
                'id_detalle_pedido' => 0,
                'id_producto' => 0,
                'nombre' => 'Pedido',
                'descripcion' => 'Detalles no disponibles',
                'cantidad' => 1,
                'precio_unitario' => (float)($pedido_info['monto_total'] ?? 0),
                'precio_total' => (float)($pedido_info['monto_total'] ?? 0),
                'instrucciones_especiales' => '',
                'opciones' => []
            ];
        }
        
        // Mapear estado
        $estado_map = [
            1 => 'pendiente',
            2 => 'confirmado',
            3 => 'preparando',
            4 => 'listo_para_entrega',
            5 => 'en_camino',
            6 => 'entregado',
            7 => 'cancelado'
        ];
        
        $id_estado = (int)($pedido_info['id_estado'] ?? 1);
        $estado_actual = $estado_map[$id_estado] ?? 'pendiente';
        
        // Preparar respuesta
        $response = [
            'success' => true,
            'data' => [
                'pedido' => [
                    'id_pedido' => (int)$pedido_info['id_pedido'],
                    'fecha_creacion' => $pedido_info['fecha_creacion'],
                    'estado' => $estado_actual,
                    'id_estado' => $id_estado,
                    'subtotal' => (float)($pedido_info['total_productos'] ?? 0),
                    'costo_envio' => (float)($pedido_info['costo_envio'] ?? 0),
                    'cargo_servicio' => (float)($pedido_info['cargo_servicio'] ?? 0),
                    'impuestos' => (float)($pedido_info['impuestos'] ?? 0),
                    'propina' => (float)($pedido_info['propina'] ?? 0),
                    'descuento' => 0,
                    'total' => (float)($pedido_info['monto_total'] ?? 0),
                    'instrucciones_especiales' => $pedido_info['instrucciones_especiales'] ?? ''
                ],
                'items' => $items,
                'cliente' => [
                    'nombre' => $pedido_info['nombre_cliente'] ?? 'Cliente',
                    'apellido' => $pedido_info['apellido_cliente'] ?? '',
                    'email' => $pedido_info['email_cliente'] ?? 'No disponible',
                    'telefono' => $pedido_info['telefono_cliente'] ?? 'No disponible'
                ],
                'direccion' => [
                    'calle' => $pedido_info['calle'] ?? '',
                    'numero' => $pedido_info['numero'] ?? '',
                    'colonia' => $pedido_info['colonia'] ?? '',
                    'ciudad' => $pedido_info['ciudad'] ?? '',
                    'estado' => $pedido_info['estado'] ?? '',
                    'codigo_postal' => $pedido_info['codigo_postal'] ?? '',
                    'referencias' => $pedido_info['referencias'] ?? ''
                ],
                'metodo_pago' => [
                    'tipo' => $pedido_info['tipo_pago'] ?? 'Efectivo',
                    'proveedor' => $pedido_info['proveedor'] ?? '',
                    'numero_cuenta' => $pedido_info['numero_cuenta'] ?? ''
                ]
            ]
        ];
        
        // Enviar respuesta
        ob_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        // Limpiar buffer y enviar error
        ob_clean();
        error_log("Error AJAX pedidos: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // Asegurar que no se ejecute más código
    exit;
}

// ===========================
// CONFIGURACIÓN GENERAL
// ===========================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Configuración específica para Cloudflare
if (isset($_SERVER['HTTP_CF_RAY'])) {
    // Estamos detrás de Cloudflare
    header('CF-Cache-Status: BYPASS');
    header('CF-Edge-Cache: no-cache');
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verificar_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===========================
// INCLUDES Y CONFIGURACIÓN DE BD
// ===========================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Pedido.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// ===========================
// VERIFICACIÓN DE AUTENTICACIÓN
// ===========================

// Verificar si el usuario está logueado y es un negocio
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/pedidos.php");
    exit;
}

// Si está logueado, obtener información del usuario y su negocio
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

$negocio = new Negocio($db);
$negocios = $negocio->obtenerPorIdPropietario($usuario->id_usuario);

// Verificar si el usuario tiene un negocio registrado
if (empty($negocios)) {
    header("Location: negocio_configuracion.php?mensaje=Debes registrar tu negocio primero");
    exit;
}

$negocio_info = $negocios[0];
$negocio->id_negocio = $negocio_info['id_negocio'];

// Crear instancia del modelo Pedido
$pedido = new Pedido($db);

// ===========================
// PROCESAMIENTO DE ACCIONES
// ===========================

$mensaje_exito = '';
$mensaje_error = '';

// Procesar acciones GET (para compatibilidad con enlaces existentes)
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['action']) && isset($_GET['id'])) {
    $id_pedido = (int)$_GET['id'];
    $accion = $_GET['action'];
    
    // Verificar que el pedido pertenezca al negocio actual
    $pedido->id_pedido = $id_pedido;
    $pedido_info = $pedido->obtenerPorId();
    
    if ($pedido_info && $pedido_info['id_negocio'] == $negocio->id_negocio) {
        switch ($accion) {
            case 'aceptar':
                if ($pedido->cambiarEstado($id_pedido, 2)) { // Estado 2 = confirmado
                    $mensaje_exito = "Pedido #" . $id_pedido . " aceptado correctamente.";
                } else {
                    $mensaje_error = "Error al aceptar el pedido.";
                }
                break;
                
            case 'rechazar':
                if ($pedido->cambiarEstado($id_pedido, 7)) { // Estado 7 = cancelado
                    $mensaje_exito = "Pedido #" . $id_pedido . " rechazado correctamente.";
                } else {
                    $mensaje_error = "Error al rechazar el pedido.";
                }
                break;
        }
    } else {
        $mensaje_error = "No tienes permiso para realizar esta acción o el pedido no existe.";
    }
    
    // Redireccionar para evitar resubmisión
    header("Location: pedidos.php?filtro=" . ($_GET['filtro'] ?? 'todos') . 
           ($mensaje_exito ? "&success=" . urlencode($mensaje_exito) : "") .
           ($mensaje_error ? "&error=" . urlencode($mensaje_error) : ""));
    exit;
}

// Procesar acciones POST (para formularios con CSRF)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && isset($_POST['id_pedido'])) {
    // Log de debugging
    error_log("=== PROCESANDO ACCIÓN POST ===");
    error_log("POST data: " . print_r($_POST, true));
    
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje_error = 'Error: Token CSRF inválido';
        error_log("ERROR: Token CSRF inválido");
    } else {
        $id_pedido = (int)$_POST['id_pedido'];
        $accion = $_POST['action'];

        error_log("Procesando: ID=$id_pedido, Acción=$accion");

        // Crear nueva instancia del pedido para esta operación
        $pedido_operacion = new Pedido($db);
        $pedido_operacion->id_pedido = $id_pedido;
        
        // Verificar que el pedido pertenezca al negocio actual
        $pedido_info = $pedido_operacion->obtenerPorId();
        
        error_log("Pedido info: " . print_r($pedido_info, true));
        error_log("ID Negocio actual: " . $negocio->id_negocio);

        if ($pedido_info && $pedido_info['id_negocio'] == $negocio->id_negocio) {
            switch ($accion) {
                case 'aceptar':
                    error_log("Intentando aceptar pedido $id_pedido");
                    $resultado = $pedido_operacion->cambiarEstado($id_pedido, 2);
                    error_log("Resultado cambiarEstado: " . ($resultado ? 'ÉXITO' : 'FALLÓ'));
                    
                    if ($resultado) {
                        $mensaje_exito = "Pedido #" . $id_pedido . " aceptado correctamente.";
                        error_log("ÉXITO: Pedido $id_pedido aceptado");
                    } else {
                        $mensaje_error = "Error al aceptar el pedido.";
                        error_log("ERROR: No se pudo aceptar pedido $id_pedido");
                    }
                    break;
                    
                case 'rechazar':
                    error_log("Intentando rechazar pedido $id_pedido");
                    $resultado = $pedido_operacion->cambiarEstado($id_pedido, 7);
                    error_log("Resultado cambiarEstado: " . ($resultado ? 'ÉXITO' : 'FALLÓ'));
                    
                    if ($resultado) {
                        $mensaje_exito = "Pedido #" . $id_pedido . " rechazado correctamente.";
                        error_log("ÉXITO: Pedido $id_pedido rechazado");
                    } else {
                        $mensaje_error = "Error al rechazar el pedido.";
                        error_log("ERROR: No se pudo rechazar pedido $id_pedido");
                    }
                    break;
                    
                case 'preparando':
                    error_log("Intentando marcar como preparando pedido $id_pedido");
                    $resultado = $pedido_operacion->cambiarEstado($id_pedido, 3);
                    
                    if ($resultado) {
                        $mensaje_exito = "Pedido #" . $id_pedido . " marcado como en preparación.";
                        error_log("ÉXITO: Pedido $id_pedido en preparación");
                    } else {
                        $mensaje_error = "Error al actualizar el estado del pedido.";
                        error_log("ERROR: No se pudo marcar como preparando pedido $id_pedido");
                    }
                    break;
                    
                case 'listo':
                    error_log("Intentando marcar como listo pedido $id_pedido");
                    $resultado = $pedido_operacion->cambiarEstado($id_pedido, 4);
                    
                    if ($resultado) {
                        $mensaje_exito = "Pedido #" . $id_pedido . " marcado como listo para entrega.";
                        error_log("ÉXITO: Pedido $id_pedido listo");
                    } else {
                        $mensaje_error = "Error al actualizar el estado del pedido.";
                        error_log("ERROR: No se pudo marcar como listo pedido $id_pedido");
                    }
                    break;
                    
                default:
                    $mensaje_error = "Acción no válida.";
                    error_log("ERROR: Acción no válida: $accion");
            }
        } else {
            $mensaje_error = "No tienes permiso para realizar esta acción o el pedido no existe.";
            error_log("ERROR: Pedido no encontrado o no pertenece al negocio");
            error_log("Pedido info: " . print_r($pedido_info, true));
            error_log("Negocio esperado: " . $negocio->id_negocio);
        }
    }
    
    error_log("=== FIN PROCESAMIENTO POST ===");
    error_log("Mensaje éxito: " . $mensaje_exito);
    error_log("Mensaje error: " . $mensaje_error);
}

// Verificar mensajes de la URL (después de redirección)
if (isset($_GET['success'])) {
    $mensaje_exito = $_GET['success'];
}
if (isset($_GET['error'])) {
    $mensaje_error = $_GET['error'];
}

// ===========================
// OBTENER DATOS
// ===========================

// Determinar filtro de pedidos
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';

// Obtener pedidos según el filtro
$pedidos = [];
try {
    switch ($filtro) {
        case 'pendientes':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 1);
            break;
        case 'confirmados':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 2);
            break;
        case 'preparando':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 3);
            break;
        case 'listos':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 4);
            break;
        case 'en_camino':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 5);
            break;
        case 'entregados':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 6);
            break;
        case 'cancelados':
            $pedidos = $pedido->obtenerPorNegocioYEstado($negocio->id_negocio, 7);
            break;
        case 'hoy':
            $pedidos = $pedido->obtenerPedidosHoy($negocio->id_negocio);
            break;
        default: // todos
            $pedidos = $pedido->obtenerPorNegocio($negocio->id_negocio);
            break;
    }
} catch (Exception $e) {
    $mensaje_error = "Error al obtener los pedidos: " . $e->getMessage();
    $pedidos = [];
}

// Obtener estadísticas de pedidos para el negocio
try {
    $estadisticas = $pedido->obtenerEstadisticasNegocio($negocio->id_negocio);
    
    // Asegurar que todos los índices necesarios estén definidos
    $defaults = [
        'pendientes' => 0,
        'confirmados' => 0,
        'preparando' => 0,
        'listos' => 0,
        'en_camino' => 0,
        'entregados' => 0,
        'cancelados' => 0,
        'en_proceso' => 0,
        'total' => 0,
        'hoy' => 0,
        'entregados_hoy' => 0,
        'ingresos_hoy' => 0,
        'ticket_promedio' => 0,
        'porcentaje_completados' => 0
    ];
    
    $estadisticas = array_merge($defaults, $estadisticas);
} catch (Exception $e) {
    $estadisticas = [
        'pendientes' => 0,
        'confirmados' => 0,
        'preparando' => 0,
        'listos' => 0,
        'en_camino' => 0,
        'entregados' => 0,
        'cancelados' => 0,
        'en_proceso' => 0,
        'total' => 0,
        'hoy' => 0,
        'entregados_hoy' => 0,
        'ingresos_hoy' => 0,
        'ticket_promedio' => 0,
        'porcentaje_completados' => 0
    ];
    $mensaje_error = "Error al obtener estadísticas: " . $e->getMessage();
}

// Verificar si hay pedidos nuevos para notificación sonora
$pedidos_nuevos = isset($_SESSION['ultimo_check_pedidos']) ? 
    $pedido->obtenerPedidosNuevos($negocio->id_negocio, $_SESSION['ultimo_check_pedidos']) : 
    [];

// Actualizar último check de pedidos
$_SESSION['ultimo_check_pedidos'] = date('Y-m-d H:i:s');

// ===========================
// FUNCIONES AUXILIARES
// ===========================

// Función auxiliar para obtener clase CSS del estado
function getStatusClass($id_estado) {
    switch ((int)$id_estado) {
        case 1: return 'status-pending';
        case 2: return 'status-confirmed';
        case 3: return 'status-preparing';
        case 4: return 'status-ready';
        case 5: return 'status-delivering';
        case 6: return 'status-delivered';
        case 7: return 'status-cancelled';
        default: return 'status-pending';
    }
}

// Función auxiliar para obtener texto del estado
function getStatusText($id_estado) {
    switch ((int)$id_estado) {
        case 1: return 'Pendiente';
        case 2: return 'Confirmado';
        case 3: return 'Preparando';
        case 4: return 'Listo para entrega';
        case 5: return 'En camino';
        case 6: return 'Entregado';
        case 7: return 'Cancelado';
        default: return 'Desconocido';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - QuickBite</title>
    <!-- Fonts: Inter and DM Sans -->
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui@5/material-ui.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
 :root {
            --primary: #0165FF;         
            --primary-light: #E3F2FD;   
            --primary-dark: #0153CC;    
            --secondary: #F8F8F8;
            --accent: #FF9500;          
            --accent-light: #FFE1AE;    
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --medium-gray: #888;
            --light-gray: #E8E8E8;
            --danger: #FF4D4D;
            --success: #4CAF50;
            --warning: #FFC107;
            --sidebar-width: 280px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #f0f4f8 100%);
            min-height: 100vh;
            display: flex;
            color: var(--dark);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            line-height: 1.3;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
            height: 100vh;
            position: fixed;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(1, 101, 255, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
        }

        .sidebar-brand {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .sidebar-brand:hover {
            transform: scale(1.02);
        }

        .sidebar-brand i {
            margin-right: 15px;
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-light) transparent;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 2px;
        }

        .menu-section {
            padding: 0 20px;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--medium-gray);
        }

        .menu-item {
            padding: 14px 25px;
            display: flex;
            align-items: center;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            margin: 2px 10px;
            border-radius: 12px;
        }

        .menu-item i {
            margin-right: 15px;
            font-size: 1.1rem;
            color: var(--medium-gray);
            transition: var(--transition);
            width: 20px;
            text-align: center;
        }

        .menu-item:hover {
            background: linear-gradient(135deg, var(--primary-light), rgba(1, 101, 255, 0.1));
            color: var(--primary);
            transform: translateX(5px);
            text-decoration: none;
        }

        .menu-item:hover i {
            color: var(--primary);
            transform: scale(1.1);
        }

        .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 600;
            box-shadow: var(--shadow-md);
        }

        .menu-item.active i {
            color: white;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            box-shadow: var(--shadow-sm);
        }

        .user-details {
            flex-grow: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin: 0;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin: 0;
        }

        .logout-btn {
            color: var(--medium-gray);
            margin-left: 15px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 8px;
            border-radius: 8px;
            text-decoration: none;
        }

        .logout-btn:hover {
            color: var(--danger);
            background: rgba(255, 77, 77, 0.1);
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px;
            position: relative;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--dark);
            background: linear-gradient(135deg, var(--dark), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-description {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 600px;
        }

        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, white 0%, #fafbff 100%);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(1, 101, 255, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.15;
        }

        .stat-icon.new-orders {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-icon.processing {
            background: linear-gradient(135deg, var(--warning), #ff8f00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-icon.completed {
            background: linear-gradient(135deg, var(--success), #2e7d32);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-icon.revenue {
            background: linear-gradient(135deg, var(--accent), #f57c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium-gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-value.accent {
            background: linear-gradient(135deg, var(--accent), #f57c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            color: var(--medium-gray);
        }

        .stat-change-positive {
            color: var(--success);
        }

        .stat-change-negative {
            color: var(--danger);
        }

        .stat-change i {
            margin-right: 5px;
        }

        /* Filter tabs */
        .custom-tabs {
            display: flex;
            background: white;
            border-radius: var(--border-radius);
            padding: 8px;
            margin-bottom: 30px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            box-shadow: var(--shadow-sm);
            scrollbar-width: none;
        }

        .custom-tabs::-webkit-scrollbar {
            display: none;
        }

        .tab-item {
            padding: 12px 20px;
            font-weight: 500;
            color: var(--medium-gray);
            text-decoration: none;
            position: relative;
            white-space: nowrap;
            border-radius: 8px;
            transition: var(--transition);
            margin: 0 2px;
            min-width: max-content;
        }

        .tab-item:hover {
            color: var(--primary);
            background: var(--primary-light);
            text-decoration: none;
        }

        .tab-item.active {
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .tab-item .badge {
            margin-left: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25em 0.6em;
            border-radius: 20px;
            background-color: rgba(255, 255, 255, 0.2);
            color: inherit;
        }

        .tab-item:not(.active) .badge {
            background-color: var(--light-gray);
            color: var(--medium-gray);
        }

        /* Orders Table */
        .content-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid rgba(1, 101, 255, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-light) transparent;
        }

        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: var(--secondary);
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 3px;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 800px;
        }

        .custom-table th,
        .custom-table td {
            padding: 18px 20px;
            vertical-align: middle;
        }

        .custom-table th {
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
            font-weight: 600;
            color: var(--dark);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--light-gray);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .custom-table td {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .custom-table tbody tr:last-child td {
            border-bottom: none;
        }

        .custom-table tbody tr {
            transition: var(--transition);
        }

        .custom-table tbody tr:hover {
            background: linear-gradient(135deg, var(--primary-light), rgba(1, 101, 255, 0.05));
            transform: scale(1.001);
        }

        /* Status Badge */
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            min-width: 130px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        .status-pending {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.2), rgba(255, 193, 7, 0.1));
            color: #d39e00;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-confirmed {
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.2), rgba(1, 101, 255, 0.1));
            color: var(--primary);
            border: 1px solid rgba(1, 101, 255, 0.3);
        }

        .status-preparing {
            background: linear-gradient(135deg, rgba(255, 149, 0, 0.2), rgba(255, 149, 0, 0.1));
            color: var(--accent);
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .status-ready {
            background: linear-gradient(135deg, rgba(13, 202, 240, 0.2), rgba(13, 202, 240, 0.1));
            color: #0dcaf0;
            border: 1px solid rgba(13, 202, 240, 0.3);
        }

        .status-delivering {
            background: linear-gradient(135deg, rgba(111, 66, 193, 0.2), rgba(111, 66, 193, 0.1));
            color: #6f42c1;
            border: 1px solid rgba(111, 66, 193, 0.3);
        }

        .status-delivered {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.2), rgba(76, 175, 80, 0.1));
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-cancelled {
            background: linear-gradient(135deg, rgba(255, 77, 77, 0.2), rgba(255, 77, 77, 0.1));
            color: var(--danger);
            border: 1px solid rgba(255, 77, 77, 0.3);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            min-width: 90px;
            position: relative;
            overflow: hidden;
        }

        .btn-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-action:hover::before {
            left: 100%;
        }

        .btn-action i {
            margin-right: 6px;
            font-size: 0.95rem;
        }

        .btn-accept {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.15), rgba(76, 175, 80, 0.1));
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .btn-accept:hover {
            background: linear-gradient(135deg, var(--success), #2e7d32);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-reject {
            background: linear-gradient(135deg, rgba(255, 77, 77, 0.15), rgba(255, 77, 77, 0.1));
            color: var(--danger);
            border: 1px solid rgba(255, 77, 77, 0.3);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, var(--danger), #d32f2f);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-view {
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.15), rgba(1, 101, 255, 0.1));
            color: var(--primary);
            border: 1px solid rgba(1, 101, 255, 0.3);
        }

        .btn-view:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-preparing {
            background: linear-gradient(135deg, rgba(255, 149, 0, 0.15), rgba(255, 149, 0, 0.1));
            color: var(--accent);
            border: 1px solid rgba(255, 149, 0, 0.3);
        }

        .btn-preparing:hover {
            background: linear-gradient(135deg, var(--accent), #f57c00);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-ready {
            background: linear-gradient(135deg, rgba(13, 202, 240, 0.15), rgba(13, 202, 240, 0.1));
            color: #0dcaf0;
            border: 1px solid rgba(13, 202, 240, 0.3);
        }

        .btn-ready:hover {
            background: linear-gradient(135deg, #0dcaf0, #0891b2);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Customer Info */
        .customer-info {
            display: flex;
            align-items: center;
        }

        .customer-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), rgba(1, 101, 255, 0.2));
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: var(--shadow-sm);
            border: 2px solid rgba(1, 101, 255, 0.1);
        }

        .customer-name {
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .customer-phone {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin: 0;
        }

        /* Alert messages */
        .alert {
            padding: 20px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow-sm);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            margin-right: 15px;
            font-size: 1.3rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.15), rgba(76, 175, 80, 0.1));
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(255, 77, 77, 0.15), rgba(255, 77, 77, 0.1));
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(1, 101, 255, 0.1);
        }

        .empty-icon {
            font-size: 5rem;
            background: linear-gradient(135deg, var(--light-gray), #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }

        .empty-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .empty-description {
            color: var(--medium-gray);
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }

        /* Modal */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-bottom: none;
            padding: 25px 30px;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            padding: 25px 30px;
            border-top: 1px solid var(--light-gray);
            background: var(--secondary);
        }

        .order-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px solid var(--light-gray);
        }

        .order-id {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
        }

        .order-date {
            color: var(--medium-gray);
            font-size: 0.95rem;
        }

        .order-items {
            margin-bottom: 25px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }

        .order-item:hover {
            background: var(--secondary);
            margin: 0 -15px;
            padding: 20px 15px;
            border-radius: 8px;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .item-options {
            font-size: 0.9rem;
            color: var(--medium-gray);
            line-height: 1.4;
        }

        .item-quantity {
            font-weight: 700;
            margin-right: 25px;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .item-price {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
        }

        /* Estilos para mensajes personalizados */
        .mensajes-personalizados {
            margin-top: 12px;
            padding: 10px 12px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 8px;
            border-left: 3px solid #f59e0b;
        }

        .mensaje-producto,
        .mensaje-tarjeta {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 6px 0;
        }

        .mensaje-producto:not(:last-child),
        .mensaje-tarjeta:not(:last-child) {
            border-bottom: 1px dashed rgba(0,0,0,0.1);
        }

        .mensaje-icon {
            font-size: 1rem;
            flex-shrink: 0;
        }

        .mensaje-label {
            font-weight: 600;
            font-size: 0.8rem;
            color: #92400e;
            flex-shrink: 0;
        }

        .mensaje-texto {
            font-size: 0.9rem;
            color: #78350f;
            font-style: italic;
            word-break: break-word;
        }

        .order-summary {
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(1, 101, 255, 0.1);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .summary-label {
            color: var(--medium-gray);
            font-weight: 500;
        }

        .summary-value {
            font-weight: 600;
            color: var(--dark);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--primary);
        }

        .total-label {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--dark);
        }

        .total-value {
            font-weight: 700;
            font-size: 1.3rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .customer-details, .delivery-details {
            margin-top: 35px;
        }

        .details-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        .details-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            margin-right: 12px;
            border-radius: 2px;
        }

        .detail-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            transition: var(--transition);
        }

        .detail-row:hover {
            background: var(--secondary);
        }

        .detail-icon {
            color: var(--primary);
            margin-right: 15px;
            font-size: 1.2rem;
            width: 25px;
            margin-top: 2px;
        }

        .detail-content {
            flex-grow: 1;
        }

        .detail-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .detail-value {
            color: var(--medium-gray);
            line-height: 1.5;
        }

        /* Toggle Sidebar Button */
        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 25px;
            left: 25px;
            z-index: 1010;
            background: linear-gradient(135deg, white, #f8faff);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            font-size: 1.3rem;
            color: var(--primary);
            border: 2px solid rgba(1, 101, 255, 0.1);
            transition: var(--transition);
        }

        .toggle-sidebar:hover {
            transform: scale(1.1);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
            }
            
            .toggle-sidebar {
                display: flex;
            }
            
            .page-header {
                margin-top: 60px;
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- [SIDEBAR HTML - mantener igual] -->
     <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-brand">
                <i class="fas fa-utensils"></i>
                QuickBite
            </a>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
            <a href="pedidos.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                Pedidos
            </a>

            <div class="menu-section">MENÚ Y OFERTAS</div>
            <a href="menu.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                Menú
            </a>
            <a href="categorias.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                Categorías
            </a>
            <a href="promociones.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'promociones.php' ? 'active' : ''; ?>">
                <i class="fas fa-percent"></i>
                Promociones
            </a>

            <div class="menu-section">NEGOCIO</div>
            <a href="negocio_configuracion.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'negocio_configuracion.php' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i>
                Mi Negocio
            </a>
            <a href="wallet_negocio.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'wallet_negocio.php' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                Monedero
            </a>
            <a href="reportes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Reportes
            </a>

            <div class="menu-section">CONFIGURACIÓN</div>
            <a href="configuracion.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                Configuración
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($usuario->nombre, 0, 1); ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo $usuario->nombre . ' ' . $usuario->apellido; ?></p>
                    <p class="user-role">Propietario</p>
                </div>
                <a href="../logout.php" class="logout-btn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar d-lg-none" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestión de Pedidos</h1>
                <p class="page-description">Administra todos los pedidos de tu negocio</p>
            </div>
        </div>
        
        <?php if (!empty($mensaje_exito)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($mensaje_exito); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje_error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($mensaje_error); ?>
        </div>
        <?php endif; ?>
 <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon new-orders">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-label">Pedidos Nuevos</div>
                <div class="stat-value"><?php echo isset($estadisticas['pendientes']) ? $estadisticas['pendientes'] : 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-clock"></i> Esperando confirmación
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon processing">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-label">En Proceso</div>
                <div class="stat-value"><?php echo isset($estadisticas['en_proceso']) ? $estadisticas['en_proceso'] : 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-fire"></i> Preparando o listos
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Completados Hoy</div>
                <div class="stat-value"><?php echo isset($estadisticas['entregados_hoy']) ? $estadisticas['entregados_hoy'] : 0; ?></div>
                <div class="stat-change stat-change-positive">
                    <i class="fas fa-arrow-up"></i> <?php echo isset($estadisticas['porcentaje_completados']) ? $estadisticas['porcentaje_completados'] : 0; ?>% de finalización
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-label">Ingresos Hoy</div>
                <div class="stat-value accent">$<?php echo isset($estadisticas['ingresos_hoy']) ? number_format($estadisticas['ingresos_hoy'], 2) : '0.00'; ?></div>
                <div class="stat-change">
                    <i class="fas fa-calculator"></i> $<?php echo isset($estadisticas['ticket_promedio']) ? number_format($estadisticas['ticket_promedio'], 2) : '0.00'; ?> ticket promedio
                </div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="custom-tabs">
            <a href="pedidos.php?filtro=todos" class="tab-item <?php echo $filtro == 'todos' ? 'active' : ''; ?>">
                Todos <span class="badge"><?php echo isset($estadisticas['total']) ? $estadisticas['total'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=pendientes" class="tab-item <?php echo $filtro == 'pendientes' ? 'active' : ''; ?>">
                Pendientes <span class="badge"><?php echo isset($estadisticas['pendientes']) ? $estadisticas['pendientes'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=confirmados" class="tab-item <?php echo $filtro == 'confirmados' ? 'active' : ''; ?>">
                Confirmados <span class="badge"><?php echo isset($estadisticas['confirmados']) ? $estadisticas['confirmados'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=preparando" class="tab-item <?php echo $filtro == 'preparando' ? 'active' : ''; ?>">
                Preparando <span class="badge"><?php echo isset($estadisticas['preparando']) ? $estadisticas['preparando'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=listos" class="tab-item <?php echo $filtro == 'listos' ? 'active' : ''; ?>">
                Listos <span class="badge"><?php echo isset($estadisticas['listos']) ? $estadisticas['listos'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=en_camino" class="tab-item <?php echo $filtro == 'en_camino' ? 'active' : ''; ?>">
                En Camino <span class="badge"><?php echo isset($estadisticas['en_camino']) ? $estadisticas['en_camino'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=entregados" class="tab-item <?php echo $filtro == 'entregados' ? 'active' : ''; ?>">
                Entregados <span class="badge"><?php echo isset($estadisticas['entregados']) ? $estadisticas['entregados'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=cancelados" class="tab-item <?php echo $filtro == 'cancelados' ? 'active' : ''; ?>">
                Cancelados <span class="badge"><?php echo isset($estadisticas['cancelados']) ? $estadisticas['cancelados'] : 0; ?></span>
            </a>
            <a href="pedidos.php?filtro=hoy" class="tab-item <?php echo $filtro == 'hoy' ? 'active' : ''; ?>">
                Hoy <span class="badge"><?php echo isset($estadisticas['hoy']) ? $estadisticas['hoy'] : 0; ?></span>
            </a>
        </div>
        
        <!-- Orders List -->
        <?php if (empty($pedidos)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <h3 class="empty-title">No hay pedidos <?php echo $filtro != 'todos' ? 'en este estado' : ''; ?></h3>
            <p class="empty-description">
                <?php 
                if ($filtro == 'pendientes') {
                    echo "No hay pedidos pendientes de confirmación en este momento.";
                } elseif ($filtro == 'todos') {
                    echo "Aún no has recibido ningún pedido. Cuando recibas pedidos, aparecerán aquí.";
                } else {
                    echo "No hay pedidos con este estado. Intenta con otro filtro.";
                }
                ?>
            </p>
        </div>
        <?php else: ?>
        <div class="content-card">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Fecha y Hora</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $ped): ?>
                        <tr>
                            <td><strong>#<?php echo $ped['id_pedido']; ?></strong></td>
                            <td>
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?php echo substr(isset($ped['nombre_cliente']) ? $ped['nombre_cliente'] : 'C', 0, 1); ?>
                                    </div>
                                    <div>
                                        <p class="customer-name"><?php echo isset($ped['nombre_cliente']) ? $ped['nombre_cliente'] : 'Cliente'; ?></p>
                                        <p class="customer-phone"><?php echo isset($ped['telefono_cliente']) ? $ped['telefono_cliente'] : 'Sin teléfono'; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                try {
                                    $fecha = new DateTime($ped['fecha_creacion']);
                                    echo $fecha->format('d/m/Y H:i'); 
                                } catch (Exception $e) {
                                    echo 'Fecha no disponible';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $id_estado = isset($ped['id_estado']) ? (int)$ped['id_estado'] : 1;
                                $status_class = getStatusClass($id_estado);
                                $estado_texto = getStatusText($id_estado);
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $estado_texto; ?></span>
                            </td>
                            <td>
                                <strong>$<?php echo number_format(isset($ped['monto_total']) ? $ped['monto_total'] : 0, 2); ?></strong>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($id_estado == 1): // Pendiente ?>
                                        <!-- Usar formularios POST para acciones críticas -->
                                        <form method="POST" action="pedidos.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="id_pedido" value="<?php echo $ped['id_pedido']; ?>">
                                            <input type="hidden" name="action" value="aceptar">
                                            <button type="submit" class="btn-action btn-accept" onclick="return confirm('¿Confirmar este pedido?')">
                                                <i class="fas fa-check"></i> Aceptar
                                            </button>
                                        </form>
                                        
                                        <form method="POST" action="pedidos.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="id_pedido" value="<?php echo $ped['id_pedido']; ?>">
                                            <input type="hidden" name="action" value="rechazar">
                                            <button type="submit" class="btn-action btn-reject" onclick="return confirm('¿Rechazar este pedido?')">
                                                <i class="fas fa-times"></i> Rechazar
                                            </button>
                                        </form>
                                        
                                    <?php elseif ($id_estado == 2): // Confirmado ?>
                                        <form method="POST" action="pedidos.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="id_pedido" value="<?php echo $ped['id_pedido']; ?>">
                                            <input type="hidden" name="action" value="preparando">
                                            <button type="submit" class="btn-action btn-preparing">
                                                <i class="fas fa-fire"></i> Preparando
                                            </button>
                                        </form>
                                        
                                    <?php elseif ($id_estado == 3): // Preparando ?>
                                        <form method="POST" action="pedidos.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="id_pedido" value="<?php echo $ped['id_pedido']; ?>">
                                            <input type="hidden" name="action" value="listo">
                                            <button type="submit" class="btn-action btn-ready">
                                                <i class="fas fa-check-circle"></i> Listo
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn-action btn-view" onclick="verDetallesPedido(<?php echo $ped['id_pedido']; ?>)">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
  <div class="modal fade" id="modalDetallesPedido" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detallesPedidoContenido">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <div id="botonesAccion">
                        <!-- Botones cargados dinámicamente según el estado -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
   <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===========================
        // CONFIGURACIÓN GLOBAL AJAX
        // ===========================
        
        // Configuración global para AJAX
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                // Agregar headers específicos para Cloudflare
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Cache-Control', 'no-cache');
                xhr.setRequestHeader('Pragma', 'no-cache');
                
                // Token CSRF si está disponible
                const csrfToken = $('meta[name="csrf-token"]').attr('content') || '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
                if (csrfToken) {
                    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
            }
        });

        // ===========================
        // NOTIFICACIONES Y SONIDOS
        // ===========================
        
        // Notificación de audio para nuevos pedidos
        <?php if (!empty($pedidos_nuevos)): ?>
        try {
            const audio = new Audio('https://cdn.pixabay.com/audio/2024/10/25/audio_9b7a3774d3.mp3');
            audio.play().catch(e => console.warn('No se pudo reproducir el sonido:', e));
        } catch (error) {
            console.warn('Error al reproducir sonido de notificación:', error);
        }
        <?php endif; ?>
        
        // Solicitar permisos de notificación al cargar la página
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        // ===========================
        // FUNCIONES DE INTERFAZ
        // ===========================
        
        // Toggle sidebar en móvil
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleSidebar');
            const sidebar = document.getElementById('sidebar');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Cerrar sidebar al hacer clic fuera en dispositivos móviles
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggleBtn = toggleBtn.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggleBtn && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        });

        // ===========================
        // FUNCIÓN PRINCIPAL: VER DETALLES DEL PEDIDO
        // ===========================
        
        // Función mejorada para ver detalles del pedido
        async function verDetallesPedido(idPedido) {
            const modal = new bootstrap.Modal(document.getElementById('modalDetallesPedido'));
            
            // Mostrar modal con spinner
            document.getElementById('detallesPedidoContenido').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando detalles del pedido...</p>
                </div>
            `;
            
            document.getElementById('botonesAccion').innerHTML = '';
            modal.show();
            
            try {
                const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
                
                // Construir URL con parámetros
                const url = new URL(window.location.href);
                url.searchParams.set('ajax', 'get_detalles');
                url.searchParams.set('id_pedido', idPedido);
                url.searchParams.set('csrf_token', csrfToken);
                url.searchParams.set('_t', Date.now()); // Cache buster
                
                console.log('Requesting:', url.toString());
                
                // Realizar petición con fetch (más confiable que XMLHttpRequest)
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    },
                    credentials: 'same-origin',
                    cache: 'no-cache'
                });
                
                // Verificar si la respuesta es exitosa
                if (!response.ok) {
                    throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Verificar content-type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Respuesta no JSON:', text);
                    throw new Error('La respuesta del servidor no es JSON válido');
                }
                
                const data = await response.json();
                console.log('Datos recibidos:', data);
                
                if (!data.success) {
                    throw new Error(data.error || 'Error desconocido al obtener detalles');
                }
                
                // Renderizar los detalles del pedido
                renderizarDetallesPedido(data.data);
                
            } catch (error) {
                console.error('Error completo:', error);
                
                document.getElementById('detallesPedidoContenido').innerHTML = `
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle"></i> Error al cargar detalles</h5>
                        <p class="mb-0">${error.message}</p>
                        <hr>
                        <small class="text-muted">
                            Si el problema persiste, intenta recargar la página o contacta al soporte técnico.
                        </small>
                    </div>
                `;
                
                // Opcional: Cerrar modal después de un tiempo
                setTimeout(() => {
                    if (modal) {
                        modal.hide();
                    }
                }, 5000);
            }
        }

        // ===========================
        // FUNCIÓN PARA RENDERIZAR DETALLES
        // ===========================
        
        function renderizarDetallesPedido(data) {
            const { pedido, items, cliente, direccion, metodo_pago } = data;
            
            // Formatear fecha
            let fechaFormateada = 'Fecha no disponible';
            let tiempoTexto = '';
            
            try {
                const fecha = new Date(pedido.fecha_creacion);
                fechaFormateada = fecha.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const ahora = new Date();
                const tiempoTranscurrido = Math.floor((ahora - fecha) / 60000);
                
                if (tiempoTranscurrido < 60) {
                    tiempoTexto = `${tiempoTranscurrido} min`;
                } else {
                    const horas = Math.floor(tiempoTranscurrido / 60);
                    const minutos = tiempoTranscurrido % 60;
                    tiempoTexto = `${horas}h ${minutos}min`;
                }
            } catch (e) {
                console.error('Error formateando fecha:', e);
            }
            
            // Mapear estado a clase CSS
            const estadoClases = {
                'pendiente': 'status-pending',
                'confirmado': 'status-confirmed',
                'preparando': 'status-preparing',
                'listo_para_entrega': 'status-ready',
                'en_camino': 'status-delivering',
                'entregado': 'status-delivered',
                'cancelado': 'status-cancelled'
            };
            
            const estadoTextos = {
                'pendiente': 'Pendiente',
                'confirmado': 'Confirmado',
                'preparando': 'Preparando',
                'listo_para_entrega': 'Listo para entrega',
                'en_camino': 'En camino',
                'entregado': 'Entregado',
                'cancelado': 'Cancelado'
            };
            
            const estadoClase = estadoClases[pedido.estado] || 'status-pending';
            const estadoTexto = estadoTextos[pedido.estado] || 'Desconocido';
            
            // Generar HTML para items
            let itemsHtml = '';
            if (items && items.length > 0) {
                items.forEach(item => {
                    // Generar HTML de mensajes personalizados
                    let mensajesHtml = '';
                    if (item.personalizacion && item.personalizacion.length > 0) {
                        item.personalizacion.forEach(p => {
                            if (p.texto_producto) {
                                mensajesHtml += `
                                    <div class="mensaje-producto">
                                        <span class="mensaje-icon">✍️</span>
                                        <span class="mensaje-label">Escribir:</span>
                                        <span class="mensaje-texto">"${p.texto_producto}"</span>
                                    </div>`;
                            }
                            if (p.mensaje_tarjeta) {
                                mensajesHtml += `
                                    <div class="mensaje-tarjeta">
                                        <span class="mensaje-icon">💌</span>
                                        <span class="mensaje-label">Tarjeta:</span>
                                        <span class="mensaje-texto">"${p.mensaje_tarjeta}"</span>
                                    </div>`;
                            }
                        });
                    }

                    itemsHtml += `
                        <div class="order-item">
                            <div class="item-details">
                                <div class="item-name">${item.nombre}</div>
                                <div class="item-options">${item.descripcion || 'Sin descripción'}</div>
                                ${item.instrucciones_especiales ?
                                    `<div class="item-options text-info">
                                        <i class="fas fa-sticky-note"></i> ${item.instrucciones_especiales}
                                    </div>` : ''
                                }
                                ${mensajesHtml ? `<div class="mensajes-personalizados">${mensajesHtml}</div>` : ''}
                            </div>
                            <div class="item-quantity">x${item.cantidad}</div>
                            <div class="item-price">$${item.precio_total.toFixed(2)}</div>
                        </div>
                    `;
                });
            } else {
                itemsHtml = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay detalles de productos disponibles
                    </div>
                `;
            }
            
            // Construir dirección completa
            const direccionCompleta = [
                direccion.calle,
                direccion.numero,
                direccion.colonia,
                direccion.ciudad,
                direccion.estado,
                direccion.codigo_postal ? `CP ${direccion.codigo_postal}` : ''
            ].filter(Boolean).join(', ');
            
            // HTML principal
            const html = `
                <div class="order-detail-header">
                    <div>
                        <div class="order-id">Pedido #${pedido.id_pedido}</div>
                        <div class="order-date">${fechaFormateada} (hace ${tiempoTexto})</div>
                    </div>
                    <div>
                        <span class="status-badge ${estadoClase}">${estadoTexto}</span>
                    </div>
                </div>
                
                <div class="order-items">
                    <h6 class="mb-3"><i class="fas fa-utensils"></i> Productos:</h6>
                    ${itemsHtml}
                </div>
                
                <div class="order-summary">
                    <div class="summary-row">
                        <div class="summary-label">Subtotal:</div>
                        <div class="summary-value">$${pedido.subtotal.toFixed(2)}</div>
                    </div>
                    ${pedido.costo_envio > 0 ? `
                        <div class="summary-row">
                            <div class="summary-label">Costo de envío:</div>
                            <div class="summary-value">$${pedido.costo_envio.toFixed(2)}</div>
                        </div>
                    ` : ''}
                    ${pedido.cargo_servicio > 0 ? `
                        <div class="summary-row">
                            <div class="summary-label">Cargo por servicio:</div>
                            <div class="summary-value">$${pedido.cargo_servicio.toFixed(2)}</div>
                        </div>
                    ` : ''}
                    ${pedido.impuestos > 0 ? `
                        <div class="summary-row">
                            <div class="summary-label">Impuestos:</div>
                            <div class="summary-value">$${pedido.impuestos.toFixed(2)}</div>
                        </div>
                    ` : ''}
                    ${pedido.propina > 0 ? `
                        <div class="summary-row">
                            <div class="summary-label">Propina:</div>
                            <div class="summary-value">$${pedido.propina.toFixed(2)}</div>
                        </div>
                    ` : ''}
                    <div class="total-row">
                        <div class="total-label">Total:</div>
                        <div class="total-value">$${pedido.total.toFixed(2)}</div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="customer-details">
                            <h6 class="details-title"><i class="fas fa-user "></i>  Datos del Cliente</h6>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-user"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Nombre</div>
                                    <div class="detail-value">${cliente.nombre} ${cliente.apellido}</div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-phone"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Teléfono</div>
                                    <div class="detail-value">
                                        <a href="tel:${cliente.telefono}" class="text-decoration-none">${cliente.telefono}</a>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-envelope"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Email</div>
                                    <div class="detail-value">
                                        <a href="mailto:${cliente.email}" class="text-decoration-none">${cliente.email}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="delivery-details">
                            <h6 class="details-title"><i class="fas fa-map-marker-alt "></i>  Datos de Entrega</h6>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Dirección</div>
                                    <div class="detail-value">${direccionCompleta || 'Dirección no disponible'}</div>
                                    ${direccion.referencias ? 
                                        `<div class="detail-value text-muted">
                                            <i class="fas fa-info-circle"></i> ${direccion.referencias}
                                        </div>` : ''
                                    }
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="fas fa-credit-card"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Método de Pago</div>
                                    <div class="detail-value">${metodo_pago.tipo}</div>
                                    ${metodo_pago.numero_cuenta ? 
                                        `<div class="detail-value text-muted">${metodo_pago.numero_cuenta}</div>` : ''
                                    }
                                </div>
                            </div>
                            ${pedido.instrucciones_especiales ? `
                                <div class="detail-row">
                                    <div class="detail-icon"><i class="fas fa-sticky-note"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-label">Instrucciones Especiales</div>
                                        <div class="detail-value">${pedido.instrucciones_especiales}</div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('detallesPedidoContenido').innerHTML = html;
            
            // Generar botones de acción
            generarBotonesAccion(pedido);
        }

        // ===========================
        // FUNCIÓN PARA GENERAR BOTONES
        // ===========================
        
        function generarBotonesAccion(pedido) {
            const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            let botonesHtml = '';
            
            switch (pedido.estado) {
                case 'pendiente':
                    botonesHtml = `
                        <form method="POST" action="pedidos.php" style="display:inline;" class="me-2">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="id_pedido" value="${pedido.id_pedido}">
                            <input type="hidden" name="action" value="aceptar">
                            <button type="submit" class="btn btn-success" onclick="return confirm('¿Confirmar este pedido?')">
                                <i class="fas fa-check"></i> Aceptar Pedido
                            </button>
                        </form>
                        <form method="POST" action="pedidos.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="id_pedido" value="${pedido.id_pedido}">
                            <input type="hidden" name="action" value="rechazar">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Rechazar este pedido?')">
                                <i class="fas fa-times"></i> Rechazar
                            </button>
                        </form>
                    `;
                    break;
                    
                case 'confirmado':
                    botonesHtml = `
                        <form method="POST" action="pedidos.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="id_pedido" value="${pedido.id_pedido}">
                            <input type="hidden" name="action" value="preparando">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-fire"></i> Comenzar Preparación
                            </button>
                        </form>
                    `;
                    break;
                    
                case 'preparando':
                    botonesHtml = `
                        <form method="POST" action="pedidos.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="id_pedido" value="${pedido.id_pedido}">
                            <input type="hidden" name="action" value="listo">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-check-circle"></i> Marcar como Listo
                            </button>
                        </form>
                    `;
                    break;
                    
                default:
                    botonesHtml = `
                        <div class="text-muted">
                            <i class="fas fa-info-circle"></i> No hay acciones disponibles para este estado
                        </div>
                    `;
            }
            
            document.getElementById('botonesAccion').innerHTML = botonesHtml;
        }

        // ===========================
        // FUNCIONES AUXILIARES
        // ===========================
        
        // Función para manejar las acciones de pedidos
        function confirmarAccion(accion, idPedido) {
            let mensaje = '';
            switch(accion) {
                case 'aceptar':
                    mensaje = '¿Confirmar la aceptación de este pedido?';
                    break;
                case 'rechazar':
                    mensaje = '¿Está seguro de rechazar este pedido?';
                    break;
                case 'preparando':
                    mensaje = '¿Marcar este pedido como en preparación?';
                    break;
                case 'listo':
                    mensaje = '¿Marcar este pedido como listo para entrega?';
                    break;
                default:
                    return false;
            }
            
            return confirm(mensaje);
        }

        // ===========================
        // VERIFICACIÓN DE NUEVOS PEDIDOS
        // ===========================
        
        // Verificar nuevos pedidos cada 30 segundos
        setInterval(function() {
            // Solo verificar si estamos en la pestaña activa
            if (!document.hidden) {
                try {
                    const xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            try {
                                const data = JSON.parse(this.responseText);
                                if (data.nuevos_pedidos > 0) {
                                    // Reproducir sonido
                                    try {
                                        const audio = new Audio('https://cdn.pixabay.com/audio/2024/10/25/audio_9b7a3774d3.mp3');
                                        audio.play().catch(e => console.warn('No se pudo reproducir el sonido:', e));
                                    } catch (error) {
                                        console.warn('Error al reproducir sonido:', error);
                                    }
                                    
                                    // Mostrar notificación del navegador si está permitido
                                    if (Notification.permission === "granted") {
                                        new Notification('Nuevo pedido', {
                                            body: `Tienes ${data.nuevos_pedidos} nuevo(s) pedido(s)`,
                                            icon: '/favicon.ico'
                                        });
                                    }
                                    
                                    // Mostrar alerta
                                    if (confirm('¡Tienes ' + data.nuevos_pedidos + ' nuevo(s) pedido(s)! ¿Deseas refrescar la página para verlos?')) {
                                        window.location.reload();
                                    }
                                }
                            } catch (e) {
                                console.error('Error al procesar respuesta:', e);
                            }
                        }
                    };
                    xhttp.open("GET", "check_new_orders.php", true);
                    xhttp.send();
                } catch (e) {
                    console.error('Error en verificación automática:', e);
                }
            }
        }, 30000); // Verificar cada 30 segundos
    </script>
</body>
</html>