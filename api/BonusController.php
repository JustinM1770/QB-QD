<?php
require_once 'Bonus.php';
require_once 'User.php';

class BonusController {
    private $db;
    private $bonus;
    private $user;

    public function __construct($db) {
        $this->db = $db;
        $this->bonus = new Bonus($db);
        $this->user = new User($db);
    }

    public function getBonus() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $id_usuario = $_SESSION['id_usuario'];

        // Obtener pedidos realizados del usuario
        $query = "SELECT pedidos_realizados FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $pedidos_realizados = $row['pedidos_realizados'] ?? 0;

        $bonificacion = $this->bonus->getBonusByOrderCount($pedidos_realizados);

        http_response_code(200);
        echo json_encode(["bonificacion" => $bonificacion]);
    }
}
?>
