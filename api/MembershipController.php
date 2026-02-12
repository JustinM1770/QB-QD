<?php
require_once 'Membership.php';

class MembershipController {
    private $db;
    private $membership;

    public function __construct($db) {
        $this->db = $db;
        $this->membership = new Membership($db);
    }

    public function subscribe() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $this->membership->id_usuario = $_SESSION['id_usuario'];

        if ($this->membership->subscribe()) {
            http_response_code(201);
            echo json_encode(["message" => "Membresía activada correctamente"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error al activar membresía"]);
        }
    }

    public function status() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $this->membership->id_usuario = $_SESSION['id_usuario'];

        $active = $this->membership->isActive();

        http_response_code(200);
        echo json_encode(["activo" => $active]);
    }
}
?>
