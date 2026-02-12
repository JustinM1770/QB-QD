<?php
class Order {
    private $conn;
    private $table_name = "pedidos";

    public $id_pedido;
    public $id_usuario;
    public $fecha_pedido;
    public $total;
    public $estado;
    public $es_pedido_gratis;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Crear nuevo pedido
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (id_usuario, fecha_pedido, total, estado, es_pedido_gratis) VALUES (?, NOW(), ?, 'pendiente', ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("idi", $this->id_usuario, $this->total, $this->es_pedido_gratis);

        if ($stmt->execute()) {
            $this->id_pedido = $this->conn->insert_id;
            return true;
        }
        return false;
    }

    // Actualizar estado del pedido
    public function updateStatus($estado) {
        $query = "UPDATE " . $this->table_name . " SET estado = ? WHERE id_pedido = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $estado, $this->id_pedido);

        return $stmt->execute();
    }
}
?>
