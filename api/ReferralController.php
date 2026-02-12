<?php
require_once 'Referral.php';

class ReferralController {
    private $db;
    private $referral;

    public function __construct($db) {
        $this->db = $db;
        $this->referral = new Referral($db);
    }

    public function addReferral() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->id_usuario_referido)) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan datos requeridos"]);
            return;
        }

        $this->referral->id_usuario_referente = $_SESSION['id_usuario'];
        $this->referral->id_usuario_referido = $data->id_usuario_referido;

        if ($this->referral->addReferral()) {
            http_response_code(201);
            echo json_encode(["message" => "Referido registrado correctamente"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error al registrar referido"]);
        }
    }

    public function checkFreeOrderEligibility() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $id_usuario = $_SESSION['id_usuario'];
        $count = $this->referral->countSuccessfulReferrals($id_usuario);

        $eligible = $count >= 2;

        http_response_code(200);
        echo json_encode(["eligible" => $eligible, "successful_referrals" => $count]);
    }
}
?>
