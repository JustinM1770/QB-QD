<?php
class Ranking {
    private $conn;
    private $table_name = "ranking_mensual";

    public $id_ranking;
    public $id_usuario;
    public $mes;
    public $total_pedidos;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtener ranking del mes actual
    public function getCurrentMonthRanking() {
        $mes_actual = date('Y-m');

        $query = "SELECT u.id_usuario, u.nombre, r.total_pedidos
                  FROM usuarios u
                  LEFT JOIN " . $this->table_name . " r ON u.id_usuario = r.id_usuario AND r.mes = ?
                  ORDER BY r.total_pedidos DESC
                  LIMIT 10";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $mes_actual);
        $stmt->execute();

        $result = $stmt->get_result();

        $ranking = [];
        while ($row = $result->fetch_assoc()) {
            $ranking[] = $row;
        }

        return $ranking;
    }

    // Actualizar ranking mensual (puede ser llamada periódicamente)
    public function updateMonthlyRanking() {
        $mes_actual = date('Y-m');

        // Obtener conteo de pedidos por usuario en el mes actual
        $query = "SELECT id_usuario, COUNT(*) as total_pedidos
                  FROM pedidos
                  WHERE DATE_FORMAT(fecha_pedido, '%Y-%m') = ?
                  GROUP BY id_usuario";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $mes_actual);
        $stmt->execute();

        $result = $stmt->get_result();

        // Insertar o actualizar ranking
        while ($row = $result->fetch_assoc()) {
            $id_usuario = $row['id_usuario'];
            $total_pedidos = $row['total_pedidos'];

            // Verificar si ya existe registro
            $checkQuery = "SELECT id_ranking FROM " . $this->table_name . " WHERE id_usuario = ? AND mes = ?";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bind_param("is", $id_usuario, $mes_actual);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                // Actualizar
                $updateQuery = "UPDATE " . $this->table_name . " SET total_pedidos = ? WHERE id_usuario = ? AND mes = ?";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bind_param("iis", $total_pedidos, $id_usuario, $mes_actual);
                $updateStmt->execute();
            } else {
                // Insertar
                $insertQuery = "INSERT INTO " . $this->table_name . " (id_usuario, mes, total_pedidos) VALUES (?, ?, ?)";
                $insertStmt = $this->conn->prepare($insertQuery);
                $insertStmt->bind_param("isi", $id_usuario, $mes_actual, $total_pedidos);
                $insertStmt->execute();
            }
        }
    }
}
?>
