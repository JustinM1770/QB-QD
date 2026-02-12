<?php
class User {
    private $conn;
    private $table_name = "usuarios";

    public $id_usuario;
    public $nombre;
    public $email;
    public $password;
    public $es_miembro;
    public $fecha_registro;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Registrar nuevo usuario
    public function register() {
        $query = "INSERT INTO " . $this->table_name . " (nombre, email, contraseña, es_miembro, fecha_registro) VALUES (?, ?, ?, 0, NOW())";

        $stmt = $this->conn->prepare($query);
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);

        $stmt->bind_param("sss", $this->nombre, $this->email, $hashed_password);

        if ($stmt->execute()) {
            $this->id_usuario = $this->conn->insert_id;
            return true;
        }
        return false;
    }

    // Verificar login
    public function login() {
        $query = "SELECT id_usuario, nombre, email, contraseña, es_miembro FROM " . $this->table_name . " WHERE email = ? LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $this->email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            if (password_verify($this->password, $row['contraseña'])) {
                $this->id_usuario = $row['id_usuario'];
                $this->nombre = $row['nombre'];
                $this->es_miembro = $row['es_miembro'];
                return true;
            }
        }
        return false;
    }
}
?>
