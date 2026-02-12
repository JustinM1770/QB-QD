<?php
class Bonus {
    private $conn;
    private $table_name = "bonificaciones";

    public $id_bonificacion;
    public $descripcion;
    public $cantidad_pedidos_requeridos;
    public $porcentaje_descuento;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtener bonificación aplicable según cantidad de pedidos realizados
    public function getBonusByOrderCount($pedidos_realizados) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE cantidad_pedidos_requeridos <= ? ORDER BY cantidad_pedidos_requeridos DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $pedidos_realizados);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
}
?>
