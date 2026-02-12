<?php
/**
 * QuickBite - API de Negocios Aliados
 * Endpoints para gestionar códigos de descuento y uso de beneficios
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Verificar autenticación para acciones protegidas
function requireAuth() {
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    return $_SESSION['id_usuario'];
}

// Verificar membresía
function requireMembership($db, $userId) {
    $stmt = $db->prepare("SELECT es_miembro, es_miembro_club, fecha_fin_membresia FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $esMiembro = ($user['es_miembro'] == 1 || $user['es_miembro_club'] == 1) &&
                 ($user['fecha_fin_membresia'] === null || $user['fecha_fin_membresia'] >= date('Y-m-d'));

    if (!$esMiembro) {
        http_response_code(403);
        echo json_encode(['error' => 'Se requiere membresía QuickBite Club']);
        exit;
    }
    return true;
}

try {
    switch ($action) {
        // Listar categorías de aliados
        case 'categorias':
            $stmt = $db->query("SELECT * FROM categorias_aliados WHERE activo = 1 ORDER BY orden");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // Listar aliados (público)
        case 'listar':
            $categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;

            $sql = "
                SELECT na.*, ca.nombre as categoria_nombre, ca.icono as categoria_icono
                FROM negocios_aliados na
                JOIN categorias_aliados ca ON na.id_categoria = ca.id_categoria
                WHERE na.estado = 'activo'
                AND (na.fecha_fin_alianza IS NULL OR na.fecha_fin_alianza >= CURDATE())
            ";

            if ($categoria) {
                $sql .= " AND na.id_categoria = :categoria";
            }
            $sql .= " ORDER BY ca.orden, na.nombre";

            $stmt = $db->prepare($sql);
            if ($categoria) {
                $stmt->bindParam(':categoria', $categoria, PDO::PARAM_INT);
            }
            $stmt->execute();

            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // Obtener detalle de un aliado
        case 'detalle':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('ID de aliado requerido');
            }

            $stmt = $db->prepare("
                SELECT na.*, ca.nombre as categoria_nombre, ca.icono as categoria_icono
                FROM negocios_aliados na
                JOIN categorias_aliados ca ON na.id_categoria = ca.id_categoria
                WHERE na.id_aliado = ?
            ");
            $stmt->execute([$id]);
            $aliado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$aliado) {
                throw new Exception('Aliado no encontrado');
            }

            echo json_encode(['success' => true, 'data' => $aliado]);
            break;

        // Generar código de descuento (requiere membresía)
        case 'generar_codigo':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $userId = requireAuth();
            requireMembership($db, $userId);

            $data = json_decode(file_get_contents('php://input'), true);
            $idAliado = isset($data['id_aliado']) ? (int)$data['id_aliado'] : 0;

            if (!$idAliado) {
                throw new Exception('ID de aliado requerido');
            }

            // Verificar que el aliado existe y está activo
            $stmt = $db->prepare("SELECT * FROM negocios_aliados WHERE id_aliado = ? AND estado = 'activo'");
            $stmt->execute([$idAliado]);
            $aliado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$aliado) {
                throw new Exception('Aliado no disponible');
            }

            // Verificar si solo primera vez
            if ($aliado['solo_primera_vez']) {
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM uso_beneficios_aliados
                    WHERE id_usuario = ? AND id_aliado = ? AND estado = 'verificado'
                ");
                $stmt->execute([$userId, $idAliado]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Este beneficio solo es válido la primera vez');
                }
            }

            // Verificar límite mensual
            if ($aliado['limite_usos_mes']) {
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM uso_beneficios_aliados
                    WHERE id_usuario = ? AND id_aliado = ?
                    AND MONTH(fecha_uso) = MONTH(CURDATE()) AND YEAR(fecha_uso) = YEAR(CURDATE())
                ");
                $stmt->execute([$userId, $idAliado]);
                if ($stmt->fetchColumn() >= $aliado['limite_usos_mes']) {
                    throw new Exception('Has alcanzado el límite de usos este mes');
                }
            }

            // Verificar si ya tiene un código activo para este aliado
            $stmt = $db->prepare("
                SELECT codigo FROM codigos_descuento_aliados
                WHERE id_usuario = ? AND id_aliado = ? AND usado = 0 AND fecha_expiracion >= CURDATE()
            ");
            $stmt->execute([$userId, $idAliado]);
            $codigoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($codigoExistente) {
                echo json_encode([
                    'success' => true,
                    'codigo' => $codigoExistente['codigo'],
                    'mensaje' => 'Ya tienes un código activo para este aliado'
                ]);
                break;
            }

            // Generar código único
            $codigo = 'QB' . strtoupper(substr(md5($userId . $idAliado . time() . rand()), 0, 8));

            $stmt = $db->prepare("
                INSERT INTO codigos_descuento_aliados (id_usuario, id_aliado, codigo, fecha_expiracion)
                VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 7 DAY))
            ");
            $stmt->execute([$userId, $idAliado, $codigo]);

            echo json_encode([
                'success' => true,
                'codigo' => $codigo,
                'expira' => date('Y-m-d', strtotime('+7 days')),
                'descuento' => $aliado['descuento_porcentaje'] . '%',
                'aliado' => $aliado['nombre']
            ]);
            break;

        // Validar código (para negocios aliados)
        case 'validar_codigo':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $codigo = $data['codigo'] ?? '';
            $montoOriginal = isset($data['monto']) ? (float)$data['monto'] : 0;

            if (empty($codigo)) {
                throw new Exception('Código requerido');
            }

            $stmt = $db->prepare("
                SELECT cd.*, na.descuento_porcentaje, na.nombre as aliado_nombre
                FROM codigos_descuento_aliados cd
                JOIN negocios_aliados na ON cd.id_aliado = na.id_aliado
                WHERE cd.codigo = ?
            ");
            $stmt->execute([$codigo]);
            $codigoData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$codigoData) {
                echo json_encode(['success' => false, 'error' => 'Código no encontrado']);
                break;
            }

            if ($codigoData['usado']) {
                echo json_encode(['success' => false, 'error' => 'Código ya utilizado']);
                break;
            }

            if ($codigoData['fecha_expiracion'] < date('Y-m-d')) {
                echo json_encode(['success' => false, 'error' => 'Código expirado']);
                break;
            }

            $descuento = $montoOriginal * ($codigoData['descuento_porcentaje'] / 100);

            echo json_encode([
                'success' => true,
                'valido' => true,
                'aliado' => $codigoData['aliado_nombre'],
                'descuento_porcentaje' => $codigoData['descuento_porcentaje'],
                'descuento_monto' => round($descuento, 2),
                'monto_final' => round($montoOriginal - $descuento, 2)
            ]);
            break;

        // Marcar código como usado
        case 'usar_codigo':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $codigo = $data['codigo'] ?? '';
            $montoOriginal = isset($data['monto']) ? (float)$data['monto'] : 0;

            if (empty($codigo)) {
                throw new Exception('Código requerido');
            }

            // Buscar código
            $stmt = $db->prepare("
                SELECT cd.*, na.descuento_porcentaje
                FROM codigos_descuento_aliados cd
                JOIN negocios_aliados na ON cd.id_aliado = na.id_aliado
                WHERE cd.codigo = ? AND cd.usado = 0 AND cd.fecha_expiracion >= CURDATE()
            ");
            $stmt->execute([$codigo]);
            $codigoData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$codigoData) {
                throw new Exception('Código inválido o expirado');
            }

            $descuento = $montoOriginal * ($codigoData['descuento_porcentaje'] / 100);
            $montoFinal = $montoOriginal - $descuento;

            // Marcar como usado
            $stmt = $db->prepare("UPDATE codigos_descuento_aliados SET usado = 1, fecha_uso = NOW() WHERE id_codigo = ?");
            $stmt->execute([$codigoData['id_codigo']]);

            // Registrar uso
            $stmt = $db->prepare("
                INSERT INTO uso_beneficios_aliados
                (id_usuario, id_aliado, codigo_usado, monto_original, descuento_aplicado, monto_final, estado)
                VALUES (?, ?, ?, ?, ?, ?, 'verificado')
            ");
            $stmt->execute([
                $codigoData['id_usuario'],
                $codigoData['id_aliado'],
                $codigo,
                $montoOriginal,
                $descuento,
                $montoFinal
            ]);

            // Incrementar contador
            $stmt = $db->prepare("UPDATE negocios_aliados SET veces_usado = veces_usado + 1 WHERE id_aliado = ?");
            $stmt->execute([$codigoData['id_aliado']]);

            // Actualizar ahorro del usuario
            $stmt = $db->prepare("UPDATE usuarios SET ahorro_total_membresia = ahorro_total_membresia + ? WHERE id_usuario = ?");
            $stmt->execute([$descuento, $codigoData['id_usuario']]);

            echo json_encode([
                'success' => true,
                'mensaje' => 'Descuento aplicado correctamente',
                'descuento' => round($descuento, 2)
            ]);
            break;

        // Historial de códigos del usuario
        case 'mis_codigos':
            $userId = requireAuth();

            $stmt = $db->prepare("
                SELECT cd.*, na.nombre as aliado_nombre, na.descuento_porcentaje
                FROM codigos_descuento_aliados cd
                JOIN negocios_aliados na ON cd.id_aliado = na.id_aliado
                WHERE cd.id_usuario = ?
                ORDER BY cd.fecha_generacion DESC
                LIMIT 20
            ");
            $stmt->execute([$userId]);

            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // Historial de uso del usuario
        case 'mi_historial':
            $userId = requireAuth();

            $stmt = $db->prepare("
                SELECT ub.*, na.nombre as aliado_nombre
                FROM uso_beneficios_aliados ub
                JOIN negocios_aliados na ON ub.id_aliado = na.id_aliado
                WHERE ub.id_usuario = ?
                ORDER BY ub.fecha_uso DESC
                LIMIT 20
            ");
            $stmt->execute([$userId]);

            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
