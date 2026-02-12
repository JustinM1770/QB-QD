<?php
class Referral {
    private $conn;
    private $table_name = "referidos";

    public $id_referido;
    public $id_usuario_referente;
    public $id_usuario_referido;
    public $fecha_referido;
    public $pedido_realizado;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Registrar nuevo referido
    public function addReferral() {
        $query = "INSERT INTO " . $this->table_name . " (id_usuario_referente, id_usuario_referido, fecha_referido, pedido_realizado) VALUES (?, ?, NOW(), 0)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $this->id_usuario_referente, PDO::PARAM_INT);
        $stmt->bindValue(2, $this->id_usuario_referido, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Marcar referido como con pedido realizado
    public function markOrderMade($id_usuario_referido) {
        $query = "UPDATE " . $this->table_name . " SET pedido_realizado = 1 WHERE id_usuario_referido = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario_referido, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Contar referidos con pedido realizado para un usuario
    public function countSuccessfulReferrals($id_usuario_referente) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE id_usuario_referente = ? AND pedido_realizado = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario_referente, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?? 0;
    }

    // Contar referidos que cumplen condición de 2 compras o membresía activa
    public function countReferralsWithPurchasesOrMembership($id_usuario_referente) {
        $query = "
            SELECT COUNT(DISTINCT r.id_usuario_referido) as total
            FROM " . $this->table_name . " r
            LEFT JOIN pedidos p ON r.id_usuario_referido = p.id_usuario AND p.id_estado IN (4,6)
            LEFT JOIN membresias m ON r.id_usuario_referido = m.id_usuario AND m.estado = 'activo' AND m.fecha_fin >= NOW()
            WHERE r.id_usuario_referente = ?
            GROUP BY r.id_usuario_referente
            HAVING COUNT(DISTINCT p.id_pedido) >= 2 OR COUNT(DISTINCT m.id_membresia) >= 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario_referente, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?? 0;
    }

    // Contar total de referidos
    public function countTotalReferrals($id_usuario_referente) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE id_usuario_referente = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario_referente, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?? 0;
    }

    // Contar referidos que compraron membresía
    public function countReferralsWithMembership($id_usuario_referente) {
        $query = "
            SELECT COUNT(DISTINCT r.id_usuario_referido) as total
            FROM " . $this->table_name . " r
            INNER JOIN membresias m ON r.id_usuario_referido = m.id_usuario 
            WHERE r.id_usuario_referente = ? 
            AND m.estado = 'activo'
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario_referente, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?? 0;
    }

    // Verificar si el usuario ya usó su beneficio de referido específicamente
    public function haUsadoBeneficioReferido($id_usuario) {
        $query = "SELECT COUNT(*) as usado FROM beneficios_referidos WHERE id_usuario = ? AND tipo_beneficio = 'referido' AND usado = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row['usado'] ?? 0) > 0;
    }

    // Verificar si el usuario ya usó su descuento de fidelidad
    public function haUsadoDescuentoFidelidad($id_usuario) {
        $query = "SELECT COUNT(*) as usado FROM beneficios_referidos WHERE id_usuario = ? AND tipo_beneficio = 'fidelidad' AND usado = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row['usado'] ?? 0) > 0;
    }

    // Marcar beneficio de referido como usado
    public function marcarBeneficioReferidoUsado($id_usuario) {
        $query = "INSERT INTO beneficios_referidos (id_usuario, tipo_beneficio, usado, fecha_uso) VALUES (?, 'referido', 1, NOW()) 
                  ON DUPLICATE KEY UPDATE usado = 1, fecha_uso = NOW()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Marcar descuento de fidelidad como usado
    public function marcarDescuentoFidelidadUsado($id_usuario) {
        $query = "INSERT INTO beneficios_referidos (id_usuario, tipo_beneficio, usado, fecha_uso) VALUES (?, 'fidelidad', 1, NOW()) 
                  ON DUPLICATE KEY UPDATE usado = 1, fecha_uso = NOW()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Obtener historial de referidos para mostrar en interfaz
    public function obtenerHistorialReferidos($id_usuario_referente) {
        $query = "
            SELECT 
                r.id_usuario_referido,
                u.nombre,
                u.apellido,
                r.fecha_referido,
                CASE 
                    WHEN m.id_membresia IS NOT NULL THEN 1 
                    ELSE 0 
                END as tiene_membresia,
                COUNT(p.id_pedido) as pedidos_completados
            FROM " . $this->table_name . " r
            INNER JOIN usuarios u ON r.id_usuario_referido = u.id_usuario
            LEFT JOIN membresias m ON r.id_usuario_referido = m.id_usuario AND m.estado = 'activo'
            LEFT JOIN pedidos p ON r.id_usuario_referido = p.id_usuario AND p.id_estado IN (4,6)
            WHERE r.id_usuario_referente = ?
            GROUP BY r.id_usuario_referido, u.nombre, u.apellido, r.fecha_referido, m.id_membresia
            ORDER BY r.fecha_referido DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $id_usuario_referente, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Verificar si un usuario fue referido por un código específico
    public function verificarCodigoReferido($codigo_referido) {
        $query = "SELECT id_usuario FROM usuarios WHERE MD5(CONCAT(id_usuario, 'quickbite_salt')) LIKE CONCAT(?, '%')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $codigo_referido, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['id_usuario'] ?? null;
    }

    // Registrar referido usando código
    public function registrarReferidoPorCodigo($codigo_referido, $id_usuario_referido) {
        $id_usuario_referente = $this->verificarCodigoReferido($codigo_referido);
        
        if (!$id_usuario_referente) {
            return false;
        }

        if ($id_usuario_referente == $id_usuario_referido) {
            return false;
        }

        $query_check = "SELECT COUNT(*) as existe FROM " . $this->table_name . " WHERE id_usuario_referido = ?";
        $stmt_check = $this->conn->prepare($query_check);
        $stmt_check->bindValue(1, $id_usuario_referido, PDO::PARAM_INT);
        $stmt_check->execute();
        $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existe['existe'] > 0) {
            return false;
        }

        $this->id_usuario_referente = $id_usuario_referente;
        $this->id_usuario_referido = $id_usuario_referido;

        return $this->addReferral();
    }
}
?>