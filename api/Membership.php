<?php
class Membership {
    private $conn;
    private $table_name = "membresias";

    public $id_membresia;
    public $id_usuario;
    public $fecha_inicio;
    public $fecha_fin;
    public $estado;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Crear nueva membresía (suscripción)
    public function subscribe() {
        $query = "INSERT INTO " . $this->table_name . " (id_usuario, fecha_inicio, fecha_fin, estado) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 'activo')";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id_usuario);

        if ($stmt->execute()) {
            // Actualizar campo es_miembro en usuarios
            $updateQuery = "UPDATE usuarios SET es_miembro = 1, fecha_inicio_membresia = NOW(), fecha_fin_membresia = DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id_usuario = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $this->id_usuario);
            $updateStmt->execute();

            return true;
        }
        return false;
    }

    // Verificar estado de membresía activa
    public function isActive() {
        $query = "SELECT estado FROM " . $this->table_name . " WHERE id_usuario = ? AND estado = 'activo' AND fecha_fin >= NOW() LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id_usuario);
        $stmt->execute();

        $result = $stmt->get_result();

        return $result->num_rows === 1;
    }
}
?>
