<?php
/**
 * QuickBite - API de Referidos para Repartidores
 * Endpoints para gestionar el sistema de referidos de repartidores
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ReferralRepartidor.php';

$database = new Database();
$db = $database->getConnection();

$referral = new ReferralRepartidor($db);

// Obtener accion
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_stats':
            // Obtener estadisticas de referidos del repartidor actual
            if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'repartidor') {
                throw new Exception('No autorizado');
            }

            $id_repartidor = obtenerIdRepartidor($db, $_SESSION['id_usuario']);
            $stats = $referral->obtenerEstadisticasReferidos($id_repartidor);
            $stats['enlace_referido'] = $referral->generarEnlaceReferido($id_repartidor);

            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        case 'get_referidos':
            // Obtener lista de referidos
            if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'repartidor') {
                throw new Exception('No autorizado');
            }

            $id_repartidor = obtenerIdRepartidor($db, $_SESSION['id_usuario']);
            $referidos = $referral->obtenerHistorialReferidos($id_repartidor);

            echo json_encode(['success' => true, 'data' => $referidos]);
            break;

        case 'get_bonificaciones':
            // Obtener historial de bonificaciones
            if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'repartidor') {
                throw new Exception('No autorizado');
            }

            $id_repartidor = obtenerIdRepartidor($db, $_SESSION['id_usuario']);
            $bonificaciones = $referral->obtenerHistorialBonificaciones($id_repartidor);

            echo json_encode(['success' => true, 'data' => $bonificaciones]);
            break;

        case 'register_referido':
            // Registrar un nuevo referido (llamado durante el registro de repartidor)
            $codigo = $_POST['codigo_referido'] ?? '';
            $id_repartidor_nuevo = $_POST['id_repartidor'] ?? 0;

            if (empty($codigo) || empty($id_repartidor_nuevo)) {
                throw new Exception('Codigo de referido o ID de repartidor invalido');
            }

            $result = $referral->registrarReferido($codigo, $id_repartidor_nuevo);
            echo json_encode($result);
            break;

        case 'verify_codigo':
            // Verificar si un codigo de referido es valido
            $codigo = $_GET['codigo'] ?? $_POST['codigo'] ?? '';

            if (empty($codigo)) {
                throw new Exception('Codigo de referido requerido');
            }

            $id_referente = $referral->verificarCodigoReferido($codigo);

            if ($id_referente) {
                // Obtener nombre del referente
                $stmt = $db->prepare("
                    SELECT CONCAT(u.nombre, ' ', u.apellido) as nombre_referente
                    FROM repartidores r
                    JOIN usuarios u ON r.id_usuario = u.id_usuario
                    WHERE r.id_repartidor = ?
                ");
                $stmt->execute([$id_referente]);
                $referente = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'referente' => $referente['nombre_referente'] ?? 'Repartidor QuickBite'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'valid' => false,
                    'message' => 'Codigo de referido no valido'
                ]);
            }
            break;

        case 'get_config':
            // Obtener configuracion de bonificaciones
            $config = $referral->obtenerConfiguracionBonificaciones();
            echo json_encode(['success' => true, 'data' => $config]);
            break;

        case 'check_bonificacion':
            // Verificar y otorgar bonificacion por referido (llamado despues de completar entrega)
            $id_repartidor = $_POST['id_repartidor'] ?? 0;

            if (empty($id_repartidor)) {
                throw new Exception('ID de repartidor requerido');
            }

            $referral->verificarBonificacionReferido($id_repartidor);
            echo json_encode(['success' => true, 'message' => 'Verificacion completada']);
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

/**
 * Obtener ID de repartidor por ID de usuario
 */
function obtenerIdRepartidor($db, $id_usuario) {
    $stmt = $db->prepare("SELECT id_repartidor FROM repartidores WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $result = $stmt->fetchColumn();

    if (!$result) {
        throw new Exception('Repartidor no encontrado');
    }

    return $result;
}
?>
