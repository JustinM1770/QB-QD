<?php
// Iniciar sesión
session_start();

// Headers mejorados para compatibilidad con Cloudflare WAF
header('X-Powered-By: QuickBite/1.0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Accept-Ranges: bytes');

// Configurar headers para JSON
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
if ($isAjax || isset($_POST['ajax_fallback'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Suprimir errores en producción para evitar problemas con Cloudflare
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Incluir configuración de BD
require_once '../config/database.php';
require_once '../api/WhatsAppLocalClient.php';

// Log para debugging
error_log("=== ACTUALIZAR ESTADO PEDIDO ===");
error_log("POST datos: " . print_r($_POST, true));
error_log("SESSION: " . print_r($_SESSION, true));

// Verificar si el usuario está logueado y es un repartidor
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    error_log("Error: Usuario no autorizado");
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Error: Método no permitido");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar parámetros requeridos
if (!isset($_POST['id_pedido']) || !isset($_POST['estado'])) {
    error_log("Error: Parámetros faltantes");
    echo json_encode(['success' => false, 'message' => 'Parámetros requeridos faltantes']);
    exit;
}

$id_pedido = intval($_POST['id_pedido']);
$nuevo_estado = $_POST['estado'];
$id_repartidor = $_SESSION['id_usuario'];

error_log("Procesando: pedido=$id_pedido, estado=$nuevo_estado, repartidor=$id_repartidor");

// Mapear estados de texto a IDs - CORREGIDO
function obtenerIdEstado($estado_texto) {
    switch ($estado_texto) {
        case 'recogido': return 5;      // en_camino (cuando recoge del restaurante)
        case 'en_camino': return 5;     // en_camino 
        case 'entregado': return 6;     // entregado
        default: 
            error_log("Estado no válido: $estado_texto");
            return null;
    }
}

$id_estado = obtenerIdEstado($nuevo_estado);
if ($id_estado === null) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido: ' . $nuevo_estado]);
    exit;
}

error_log("Estado mapeado: $nuevo_estado -> $id_estado");

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar que el pedido pertenece al repartidor
    $query_verificar = "SELECT id_estado FROM pedidos WHERE id_pedido = ? AND id_repartidor = ?";
    $stmt_verificar = $db->prepare($query_verificar);
    $stmt_verificar->bindParam(1, $id_pedido);
    $stmt_verificar->bindParam(2, $id_repartidor);
    $stmt_verificar->execute();
    
    if ($stmt_verificar->rowCount() === 0) {
        error_log("Error: Pedido no encontrado o no autorizado");
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no autorizado']);
        exit;
    }
    
    $pedido_actual = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    $estado_actual = $pedido_actual['id_estado'];
    
    error_log("Estado actual del pedido: $estado_actual");
    
    // Validar transición de estado - CORREGIDO
    $transiciones_validas = [
        1 => [4, 5], // asignado -> listo, en_camino
        3 => [4, 5], // preparando -> listo, en_camino
        4 => [5],    // listo -> en_camino
        5 => [6]     // en_camino -> entregado
    ];
    
    if (!isset($transiciones_validas[$estado_actual]) || 
        !in_array($id_estado, $transiciones_validas[$estado_actual])) {
        error_log("Error: Transición no válida de estado $estado_actual a $id_estado");
        echo json_encode([
            'success' => false, 
            'message' => "Transición de estado no válida desde $estado_actual a $id_estado"
        ]);
        exit;
    }
    
    error_log("Transición válida: $estado_actual -> $id_estado");
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Actualizar el estado del pedido
        $query_update = "UPDATE pedidos SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?";
        $stmt_update = $db->prepare($query_update);
        $stmt_update->bindParam(1, $id_estado);
        $stmt_update->bindParam(2, $id_pedido);
        
        if (!$stmt_update->execute()) {
            throw new Exception('Error al ejecutar UPDATE de pedido');
        }
        
        if ($stmt_update->rowCount() === 0) {
            throw new Exception('No se actualizó ninguna fila');
        }
        
        error_log("Estado actualizado exitosamente en BD");
        
        // Registrar en el historial de estados (si la tabla existe)
        try {
            $observaciones = '';
            switch ($nuevo_estado) {
                case 'recogido':
                    $observaciones = 'Pedido recogido del restaurante';
                    break;
                case 'en_camino':
                    $observaciones = 'Repartidor en camino hacia el cliente';
                    break;
                case 'entregado':
                    $observaciones = 'Pedido entregado al cliente';
                    // También actualizar el tiempo de entrega real
                    try {
                        $query_entrega = "UPDATE pedidos SET tiempo_entrega_real = NOW() WHERE id_pedido = ?";
                        $stmt_entrega = $db->prepare($query_entrega);
                        $stmt_entrega->bindParam(1, $id_pedido);
                        $stmt_entrega->execute();
                        error_log("Tiempo de entrega actualizado");
                    } catch (PDOException $e) {
                        error_log("No se pudo actualizar tiempo_entrega_real: " . $e->getMessage());
                    }
                    break;
            }
            
            $query_historial = "INSERT INTO estado_pedidos (id_pedido, estado, fecha_cambio, observaciones) 
                               VALUES (?, ?, NOW(), ?)";
            $stmt_historial = $db->prepare($query_historial);
            $stmt_historial->bindParam(1, $id_pedido);
            $stmt_historial->bindParam(2, $nuevo_estado);
            $stmt_historial->bindParam(3, $observaciones);
            $stmt_historial->execute();
            error_log("Historial registrado");
        } catch (PDOException $e) {
            // Si la tabla no existe, continuar sin error
            error_log("Tabla estado_pedidos no existe o error en historial: " . $e->getMessage());
        }
        
        // Confirmar transacción
        $db->commit();
        
        error_log("Transacción completada exitosamente");
        
        // Enviar notificación por WhatsApp
        $whatsappSent = false;
        try {
            // Obtener datos del pedido para WhatsApp
            $query_pedido = "SELECT p.id_pedido, p.total, u.telefono, u.nombre, n.nombre as negocio_nombre
                            FROM pedidos p 
                            JOIN usuarios u ON p.id_usuario = u.id_usuario 
                            LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                            WHERE p.id_pedido = ?";
            $stmt_pedido = $db->prepare($query_pedido);
            $stmt_pedido->bindParam(1, $id_pedido);
            $stmt_pedido->execute();
            $pedido_data = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
            
            if ($pedido_data && !empty($pedido_data['telefono'])) {
                $whatsapp = new WhatsAppLocalClient();
                $result = $whatsapp->sendOrderNotification(
                    $pedido_data['telefono'],
                    $id_pedido,
                    $nuevo_estado,
                    $pedido_data['total'],
                    $pedido_data['nombre'],
                    $pedido_data['negocio_nombre'] ?? 'QuickBite',
                    $id_estado  // Agregar el id_estado
                );
                
                $whatsappSent = $result['success'] ?? false;
                error_log("WhatsApp enviado (Estado $id_estado - $nuevo_estado): " . ($whatsappSent ? 'SI' : 'NO'));
            }
        } catch (Exception $e) {
            error_log("Error al enviar WhatsApp: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Estado actualizado correctamente',
            'whatsapp_sent' => $whatsappSent,
            'data' => [
                'id_pedido' => $id_pedido,
                'estado_anterior' => $estado_actual,
                'estado_nuevo' => $id_estado,
                'estado_texto' => $nuevo_estado,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error en transacción: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} catch (PDOException $e) {
    error_log("Error de BD: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

error_log("=== FIN ACTUALIZAR ESTADO ===");
?>