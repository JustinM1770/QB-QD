<?php
require_once 'User.php';

class UserController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
        $this->user = new User($db);
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->nombre) || empty($data->email) || empty($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan datos requeridos"]);
            return;
        }

        $this->user->nombre = $data->nombre;
        $this->user->email = $data->email;
        $this->user->password = $data->password;

        if ($this->user->register()) {
            http_response_code(201);
            echo json_encode(["message" => "Usuario registrado correctamente"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error al registrar usuario"]);
        }
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email) || empty($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan datos requeridos"]);
            return;
        }

        $this->user->email = $data->email;
        $this->user->password = $data->password;

        if ($this->user->login()) {
            session_start();
            $_SESSION['id_usuario'] = $this->user->id_usuario;
            $_SESSION['nombre'] = $this->user->nombre;
            $_SESSION['es_miembro'] = $this->user->es_miembro;
            $_SESSION['loggedin'] = true;

            http_response_code(200);
            echo json_encode(["message" => "Login exitoso"]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Credenciales inválidas"]);
        }
    }
}
?>
