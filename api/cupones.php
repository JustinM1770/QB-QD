<?php
/**
 * API para gestión de cupones
 *
 * GET /api/cupones.php?action=validar&codigo=XXX&subtotal=100&negocio_id=1
 * POST /api/cupones.php (action: aplicar)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Cupon.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $cupon = new Cupon($db);

    $action = $_GET['action'] ?? $_POST['action'] ?? 'validar';

    switch ($action) {
        case 'validar':
            // Validar cupón
            $codigo = trim($_GET['codigo'] ?? '');
            $subtotal = (float)($_GET['subtotal'] ?? 0);
            $id_negocio = (int)($_GET['negocio_id'] ?? 0);
            $id_usuario = $_SESSION['id_usuario'] ?? 0;

            if (empty($codigo)) {
                throw new Exception('Ingresa un código de cupón');
            }

            if (!$id_usuario) {
                throw new Exception('Debes iniciar sesión para usar cupones');
            }

            $resultado = $cupon->validar($codigo, $id_usuario, $id_negocio, $subtotal);

            if ($resultado['valido']) {
                echo json_encode([
                    'success' => true,
                    'valido' => true,
                    'descuento' => $resultado['descuento'],
                    'mensaje' => $resultado['mensaje'],
                    'cupon' => [
                        'id' => $resultado['cupon']['id_cupon'],
                        'codigo' => $resultado['cupon']['codigo'],
                        'tipo' => $resultado['cupon']['tipo_descuento'],
                        'valor' => $resultado['cupon']['valor_descuento']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'valido' => false,
                    'error' => $resultado['error']
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'aplicar':
            // Aplicar cupón a pedido
            $input = json_decode(file_get_contents('php://input'), true);

            $id_cupon = (int)($input['id_cupon'] ?? 0);
            $id_pedido = (int)($input['id_pedido'] ?? 0);
            $descuento = (float)($input['descuento'] ?? 0);
            $id_usuario = $_SESSION['id_usuario'] ?? 0;

            if (!$id_cupon || !$id_pedido || !$id_usuario) {
                throw new Exception('Datos incompletos para aplicar cupón');
            }

            $resultado = $cupon->aplicar($id_cupon, $id_usuario, $id_pedido, $descuento);

            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'disponibles':
            // Obtener cupones disponibles para el usuario (primera compra, etc.)
            $id_usuario = $_SESSION['id_usuario'] ?? 0;

            // Verificar si es primera compra
            $query = "SELECT COUNT(*) as total FROM pedidos WHERE id_usuario = :id AND id_estado != 7";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_usuario);
            $stmt->execute();
            $pedidos = $stmt->fetch(PDO::FETCH_ASSOC);

            $es_primera_compra = $pedidos['total'] == 0;

            // Obtener cupones activos
            $cupones = $cupon->obtenerTodos(true);

            // Filtrar cupones aplicables
            $disponibles = [];
            foreach ($cupones as $c) {
                // Si es solo primera compra y el usuario ya tiene pedidos, saltar
                if ($c['solo_primera_compra'] && !$es_primera_compra) {
                    continue;
                }
                $disponibles[] = [
                    'codigo' => $c['codigo'],
                    'descripcion' => $c['descripcion'],
                    'tipo' => $c['tipo_descuento'],
                    'valor' => $c['valor_descuento'],
                    'minimo' => $c['minimo_compra']
                ];
            }

            echo json_encode([
                'success' => true,
                'cupones' => $disponibles,
                'es_primera_compra' => $es_primera_compra
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
