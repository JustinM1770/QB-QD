<?php
/**
 * QuickBite - API de Resenas
 * Permite a los clientes dejar resenas de negocios y repartidores
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'crear':
            // Crear nueva resena (requiere autenticacion)
            if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'cliente') {
                throw new Exception('Debes iniciar sesion para dejar una resena');
            }

            $id_pedido = (int)($_POST['id_pedido'] ?? 0);
            $calificacion_negocio = (int)($_POST['calificacion_negocio'] ?? 0);
            $calificacion_repartidor = isset($_POST['calificacion_repartidor']) ? (int)$_POST['calificacion_repartidor'] : null;
            $comentario = trim($_POST['comentario'] ?? '');
            $comentario_repartidor = trim($_POST['comentario_repartidor'] ?? '');
            $tiempo_entrega = $_POST['tiempo_entrega'] ?? null;
            $estado_pedido = $_POST['estado_pedido'] ?? 'perfecto';

            if ($id_pedido <= 0) {
                throw new Exception('ID de pedido invalido');
            }

            if ($calificacion_negocio < 1 || $calificacion_negocio > 5) {
                throw new Exception('La calificacion debe ser entre 1 y 5');
            }

            // Verificar que el pedido pertenece al usuario y esta entregado
            $stmt = $db->prepare("
                SELECT p.*, n.id_negocio
                FROM pedidos p
                JOIN negocios n ON p.id_negocio = n.id_negocio
                WHERE p.id_pedido = ? AND p.id_usuario = ? AND p.id_estado = 6
            ");
            $stmt->execute([$id_pedido, $_SESSION['id_usuario']]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                throw new Exception('Pedido no encontrado o no elegible para resena');
            }

            // Verificar que no haya resena existente
            $stmt = $db->prepare("SELECT id_valoracion FROM valoraciones WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);
            if ($stmt->fetch()) {
                throw new Exception('Ya dejaste una resena para este pedido');
            }

            // Insertar resena
            $stmt = $db->prepare("
                INSERT INTO valoraciones (
                    id_pedido, id_usuario, id_negocio, id_repartidor,
                    calificacion_negocio, calificacion_repartidor,
                    comentario, comentario_repartidor,
                    tiempo_entrega_percibido, estado_pedido,
                    fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $id_pedido,
                $_SESSION['id_usuario'],
                $pedido['id_negocio'],
                $pedido['id_repartidor'],
                $calificacion_negocio,
                $calificacion_repartidor,
                $comentario,
                $comentario_repartidor,
                $tiempo_entrega,
                $estado_pedido
            ]);

            // Marcar resena pendiente como completada
            $stmt = $db->prepare("UPDATE resenas_pendientes SET resena_completada = 1 WHERE id_pedido = ?");
            $stmt->execute([$id_pedido]);

            echo json_encode([
                'success' => true,
                'message' => 'Gracias por tu resena!'
            ]);
            break;

        case 'get_negocio':
            // Obtener resenas de un negocio
            $id_negocio = (int)($_GET['id_negocio'] ?? 0);
            $limite = min(50, (int)($_GET['limite'] ?? 10));
            $offset = (int)($_GET['offset'] ?? 0);

            $stmt = $db->prepare("
                SELECT v.*, u.nombre as nombre_usuario,
                       DATE_FORMAT(v.fecha_creacion, '%d/%m/%Y') as fecha_formateada
                FROM valoraciones v
                JOIN usuarios u ON v.id_usuario = u.id_usuario
                WHERE v.id_negocio = ? AND v.visible = 1
                ORDER BY v.fecha_creacion DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$id_negocio, $limite, $offset]);
            $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener estadisticas
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total,
                    AVG(calificacion_negocio) as promedio,
                    SUM(CASE WHEN calificacion_negocio = 5 THEN 1 ELSE 0 END) as cinco_estrellas,
                    SUM(CASE WHEN calificacion_negocio = 4 THEN 1 ELSE 0 END) as cuatro_estrellas,
                    SUM(CASE WHEN calificacion_negocio = 3 THEN 1 ELSE 0 END) as tres_estrellas,
                    SUM(CASE WHEN calificacion_negocio = 2 THEN 1 ELSE 0 END) as dos_estrellas,
                    SUM(CASE WHEN calificacion_negocio = 1 THEN 1 ELSE 0 END) as una_estrella
                FROM valoraciones
                WHERE id_negocio = ? AND visible = 1
            ");
            $stmt->execute([$id_negocio]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'resenas' => $resenas,
                'estadisticas' => $stats
            ]);
            break;

        case 'get_repartidor':
            // Obtener resenas de un repartidor
            $id_repartidor = (int)($_GET['id_repartidor'] ?? 0);
            $limite = min(50, (int)($_GET['limite'] ?? 10));

            $stmt = $db->prepare("
                SELECT v.calificacion_repartidor, v.comentario_repartidor,
                       v.tiempo_entrega_percibido,
                       DATE_FORMAT(v.fecha_creacion, '%d/%m/%Y') as fecha_formateada,
                       u.nombre as nombre_usuario
                FROM valoraciones v
                JOIN usuarios u ON v.id_usuario = u.id_usuario
                WHERE v.id_repartidor = ? AND v.calificacion_repartidor IS NOT NULL AND v.visible = 1
                ORDER BY v.fecha_creacion DESC
                LIMIT ?
            ");
            $stmt->execute([$id_repartidor, $limite]);
            $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estadisticas del repartidor
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_resenas,
                    AVG(calificacion_repartidor) as rating_promedio,
                    r.total_entregas
                FROM valoraciones v
                JOIN repartidores r ON v.id_repartidor = r.id_repartidor
                WHERE v.id_repartidor = ? AND v.calificacion_repartidor IS NOT NULL
                GROUP BY r.id_repartidor
            ");
            $stmt->execute([$id_repartidor]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'resenas' => $resenas,
                'estadisticas' => $stats
            ]);
            break;

        case 'verificar_pendiente':
            // Verificar si el usuario tiene resenas pendientes
            if (!isset($_SESSION['loggedin'])) {
                echo json_encode(['success' => true, 'pendiente' => null]);
                exit;
            }

            $stmt = $db->prepare("
                SELECT rp.id_pedido, p.id_negocio, n.nombre as nombre_negocio,
                       p.id_repartidor, p.tipo_pedido,
                       CONCAT(u.nombre, ' ', u.apellido) as nombre_repartidor
                FROM resenas_pendientes rp
                JOIN pedidos p ON rp.id_pedido = p.id_pedido
                JOIN negocios n ON p.id_negocio = n.id_negocio
                LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
                LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
                WHERE rp.id_usuario = ? AND rp.resena_completada = 0
                ORDER BY rp.fecha_creacion DESC
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['id_usuario']]);
            $pendiente = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'pendiente' => $pendiente
            ]);
            break;

        case 'responder':
            // Respuesta del negocio a una resena
            if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'negocio') {
                throw new Exception('No autorizado');
            }

            $id_valoracion = (int)($_POST['id_valoracion'] ?? 0);
            $respuesta = trim($_POST['respuesta'] ?? '');

            if (empty($respuesta)) {
                throw new Exception('La respuesta no puede estar vacia');
            }

            // Verificar que la resena pertenece al negocio del usuario
            $stmt = $db->prepare("
                SELECT v.id_valoracion
                FROM valoraciones v
                JOIN negocios n ON v.id_negocio = n.id_negocio
                WHERE v.id_valoracion = ? AND n.id_propietario = ?
            ");
            $stmt->execute([$id_valoracion, $_SESSION['id_usuario']]);

            if (!$stmt->fetch()) {
                throw new Exception('Resena no encontrada');
            }

            // Actualizar respuesta
            $stmt = $db->prepare("
                UPDATE valoraciones
                SET respuesta_negocio = ?, fecha_respuesta = NOW()
                WHERE id_valoracion = ?
            ");
            $stmt->execute([$respuesta, $id_valoracion]);

            echo json_encode([
                'success' => true,
                'message' => 'Respuesta guardada'
            ]);
            break;

        default:
            throw new Exception('Accion no reconocida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
