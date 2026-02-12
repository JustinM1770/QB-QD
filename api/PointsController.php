<?php
require_once 'User.php';

class PointsController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
    }

    public function getPointsBalance() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $id_usuario = $_SESSION['id_usuario'];

        $query = "SELECT puntos_acumulados FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            http_response_code(200);
            echo json_encode(["puntos_acumulados" => (int)$row['puntos_acumulados']]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Usuario no encontrado"]);
        }
    }

    public function getPointsHistory() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $id_usuario = $_SESSION['id_usuario'];

        $query = "SELECT id_pedido, cantidad, fecha_acreditacion FROM puntos WHERE id_usuario = ? ORDER BY fecha_acreditacion DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                "id_pedido" => $row['id_pedido'],
                "cantidad" => (int)$row['cantidad'],
                "fecha_acreditacion" => $row['fecha_acreditacion']
            ];
        }

        http_response_code(200);
        echo json_encode(["history" => $history]);
    }

    public function redeemPoints() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->tipo_redencion) || empty($data->descripcion) || empty($data->puntos_usados)) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan datos requeridos"]);
            return;
        }

        $id_usuario = $_SESSION['id_usuario'];
        $tipo_redencion = $data->tipo_redencion;
        $descripcion = $data->descripcion;
        $puntos_usados = (int)$data->puntos_usados;

        // Verificar puntos suficientes
        $query = "SELECT puntos_acumulados FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['puntos_acumulados'] < $puntos_usados) {
                http_response_code(400);
                echo json_encode(["message" => "Puntos insuficientes"]);
                return;
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Usuario no encontrado"]);
            return;
        }

        // Insertar redención
        $queryInsert = "INSERT INTO puntos_redenciones (id_usuario, tipo_redencion, descripcion, puntos_usados, fecha_redencion) VALUES (?, ?, ?, ?, NOW())";
        $stmtInsert = $this->db->prepare($queryInsert);
        $stmtInsert->bind_param("isss", $id_usuario, $tipo_redencion, $descripcion, $puntos_usados);
        if (!$stmtInsert->execute()) {
            http_response_code(500);
            echo json_encode(["message" => "Error al registrar la redención"]);
            return;
        }

        // Actualizar puntos acumulados
        $queryUpdate = "UPDATE usuarios SET puntos_acumulados = puntos_acumulados - ? WHERE id_usuario = ?";
        $stmtUpdate = $this->db->prepare($queryUpdate);
        $stmtUpdate->bind_param("ii", $puntos_usados, $id_usuario);
        if (!$stmtUpdate->execute()) {
            http_response_code(500);
            echo json_encode(["message" => "Error al actualizar puntos"]);
            return;
        }

        http_response_code(200);
        echo json_encode(["message" => "Redención exitosa"]);
    }
}

$database = new Database();
$db = $database->getConnection();

$controller = new PointsController($db);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getPointsBalance':
        $controller->getPointsBalance();
        break;
    case 'getPointsHistory':
        $controller->getPointsHistory();
        break;
    case 'redeemPoints':
        $controller->redeemPoints();
        break;
    default:
        http_response_code(400);
        echo json_encode(["message" => "Acción no válida"]);
        break;
}
?>
