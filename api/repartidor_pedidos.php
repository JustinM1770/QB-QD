<?php
/**
 * API: Gestión de Pedidos para Repartidores
 * Sistema Multi-Pedido y Reasignación
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/GestionPedidos.php';

// Verificar autenticación
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo_usuario'] !== 'repartidor') {
    // Permitir también autenticación por token para la app
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $_GET['token'] ?? null;
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }
    
    // Validar token (simplificado - en producción usar JWT)
    $database = new Database();
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare("
        SELECT r.id_repartidor, u.id_usuario 
        FROM repartidores r 
        JOIN usuarios u ON r.id_usuario = u.id_usuario 
        WHERE u.token_sesion = ?
    ");
    $stmt->execute([str_replace('Bearer ', '', $token)]);
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auth) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }
    
    $idRepartidor = $auth['id_repartidor'];
} else {
    // Obtener id_repartidor de la sesión
    $database = new Database();
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare("SELECT id_repartidor FROM repartidores WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $idRepartidor = $stmt->fetchColumn();
}

if (!$idRepartidor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No es repartidor']);
    exit;
}

$gestion = new GestionPedidos();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // =====================================
        // GESTIÓN DE PEDIDOS INDIVIDUALES
        // =====================================
        
        case 'aceptar':
            // Repartidor acepta ir por el pedido
            $idPedido = intval($_POST['id_pedido'] ?? 0);
            if (!$idPedido) {
                throw new Exception('ID de pedido requerido');
            }
            $resultado = $gestion->aceptarPedido($idPedido, $idRepartidor);
            echo json_encode($resultado);
            break;
            
        case 'confirmar_recogida':
            // Repartidor confirma que recogió el pedido
            $idPedido = intval($_POST['id_pedido'] ?? 0);
            if (!$idPedido) {
                throw new Exception('ID de pedido requerido');
            }
            $resultado = $gestion->confirmarRecogida($idPedido, $idRepartidor);
            echo json_encode($resultado);
            break;
            
        case 'confirmar_entrega':
            // Repartidor confirma entrega
            $idPedido = intval($_POST['id_pedido'] ?? 0);
            if (!$idPedido) {
                throw new Exception('ID de pedido requerido');
            }
            $resultado = $gestion->confirmarEntrega($idPedido, $idRepartidor);
            echo json_encode($resultado);
            break;
            
        case 'abandonar':
            // Repartidor abandona pedido (antes de recoger)
            $idPedido = intval($_POST['id_pedido'] ?? 0);
            $motivo = $_POST['motivo'] ?? 'abandono_voluntario';
            $notas = $_POST['notas'] ?? '';
            
            if (!$idPedido) {
                throw new Exception('ID de pedido requerido');
            }
            
            $resultado = $gestion->abandonarPedido($idPedido, $idRepartidor, $motivo, $notas);
            echo json_encode($resultado);
            break;
            
        // =====================================
        // MULTI-PEDIDO / BATCH
        // =====================================
        
        case 'pedidos_cercanos':
            // Obtener pedidos disponibles cerca del repartidor
            $lat = floatval($_GET['lat'] ?? 0);
            $lng = floatval($_GET['lng'] ?? 0);
            $radio = floatval($_GET['radio'] ?? 3);
            
            if (!$lat || !$lng) {
                // Usar ubicación guardada del repartidor
                $stmt = $pdo->prepare("SELECT latitud_actual, longitud_actual FROM repartidores WHERE id_repartidor = ?");
                $stmt->execute([$idRepartidor]);
                $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);
                $lat = $ubicacion['latitud_actual'];
                $lng = $ubicacion['longitud_actual'];
            }
            
            if (!$lat || !$lng) {
                throw new Exception('Ubicación requerida');
            }
            
            $pedidos = $gestion->obtenerPedidosCercanos($idRepartidor, $lat, $lng, $radio);
            echo json_encode(['success' => true, 'pedidos' => $pedidos]);
            break;
            
        case 'sugerencias_batch':
            // Obtener sugerencias de rutas batch
            $sugerencias = $gestion->generarSugerenciasBatch($idRepartidor);
            echo json_encode(['success' => true, 'sugerencias' => $sugerencias]);
            break;
            
        case 'crear_ruta_batch':
            // Crear ruta con múltiples pedidos
            $idsPedidos = $_POST['pedidos'] ?? [];
            if (is_string($idsPedidos)) {
                $idsPedidos = json_decode($idsPedidos, true);
            }
            
            if (empty($idsPedidos) || !is_array($idsPedidos)) {
                throw new Exception('Lista de pedidos requerida');
            }
            
            $resultado = $gestion->crearRutaBatch($idRepartidor, array_map('intval', $idsPedidos));
            echo json_encode($resultado);
            break;
            
        case 'agregar_a_ruta':
            // Agregar pedido a ruta activa
            $idPedido = intval($_POST['id_pedido'] ?? 0);
            if (!$idPedido) {
                throw new Exception('ID de pedido requerido');
            }
            
            $resultado = $gestion->agregarPedidoARuta($idRepartidor, $idPedido);
            echo json_encode($resultado);
            break;
            
        case 'ruta_activa':
            // Obtener ruta activa del repartidor
            $ruta = $gestion->obtenerRutaActiva($idRepartidor);
            echo json_encode(['success' => true, 'ruta' => $ruta]);
            break;
            
        // =====================================
        // ESTADÍSTICAS Y LOGROS
        // =====================================
        
        case 'estadisticas':
            $stats = $gestion->obtenerEstadisticasRepartidor($idRepartidor);
            echo json_encode(['success' => true, 'estadisticas' => $stats]);
            break;
            
        case 'logros':
            $logros = $gestion->obtenerLogrosRepartidor($idRepartidor);
            echo json_encode(['success' => true, 'logros' => $logros]);
            break;
            
        // =====================================
        // ACTUALIZAR UBICACIÓN
        // =====================================
        
        case 'actualizar_ubicacion':
            $lat = floatval($_POST['lat'] ?? 0);
            $lng = floatval($_POST['lng'] ?? 0);
            
            if (!$lat || !$lng) {
                throw new Exception('Ubicación requerida');
            }
            
            $stmt = $pdo->prepare("
                UPDATE repartidores SET 
                    latitud_actual = ?, 
                    longitud_actual = ?,
                    ultima_actualizacion_ubicacion = NOW()
                WHERE id_repartidor = ?
            ");
            $stmt->execute([$lat, $lng, $idRepartidor]);
            
            echo json_encode(['success' => true]);
            break;
            
        // =====================================
        // MIS PEDIDOS ACTIVOS
        // =====================================
        
        case 'mis_pedidos':
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    n.nombre as nombre_negocio,
                    n.direccion as direccion_negocio,
                    n.latitud as lat_negocio,
                    n.longitud as lng_negocio,
                    n.telefono as telefono_negocio,
                    ep.nombre as estado_nombre,
                    u.nombre as nombre_cliente,
                    u.telefono as telefono_cliente
                FROM pedidos p
                JOIN negocios n ON p.id_negocio = n.id_negocio
                JOIN estados_pedido ep ON p.id_estado = ep.id_estado
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                WHERE p.id_repartidor = ? AND p.id_estado IN (4, 5)
                ORDER BY p.prioridad DESC, p.fecha_creacion ASC
            ");
            $stmt->execute([$idRepartidor]);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'pedidos' => $pedidos]);
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
