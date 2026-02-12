<?php
require_once 'Order.php';
require_once 'Membership.php';
require_once 'User.php';

class OrderController {
    private $db;
    private $order;
    private $membership;
    private $user;

    public function __construct($db) {
        $this->db = $db;
        $this->order = new Order($db);
        $this->membership = new Membership($db);
        $this->user = new User($db);
    }

    /**
     * Verifica si un usuario tiene permiso para modificar un pedido
     * El usuario debe ser el propietario del negocio asociado al pedido
     */
    public function userCanModifyOrder($userId, $orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id_pedido
                FROM pedidos p
                INNER JOIN negocios n ON p.id_negocio = n.id_negocio
                WHERE p.id_pedido = :orderId
                AND n.id_propietario = :userId
            ");
            $stmt->bindParam(':orderId', $orderId, PDO::PARAM_INT);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error verificando permisos de pedido: " . $e->getMessage());
            return false;
        }
    }

    public function create() {
        session_start();
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->total)) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan datos requeridos"]);
            return;
        }

        $this->order->id_usuario = $_SESSION['id_usuario'];
        $this->order->total = $data->total;
        $this->order->es_pedido_gratis = isset($data->es_pedido_gratis) ? (int)$data->es_pedido_gratis : 0;

        // Agregar tipo de pedido y hora de recogida
        $this->order->tipo_pedido = isset($data->tipo_pedido) ? $data->tipo_pedido : 'delivery';
        $this->order->hora_recogida = isset($data->hora_recogida) ? $data->hora_recogida : null;

        if ($this->order->create()) {
            // Asignar puntos
            $this->assignPoints($this->order->id_usuario, $this->order->id_pedido, $this->order->total);

            // Actualizar contador de pedidos en usuarios
            $this->updateOrderCount($this->order->id_usuario);

            http_response_code(201);
            echo json_encode(["message" => "Pedido creado correctamente", "id_pedido" => $this->order->id_pedido]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error al crear pedido"]);
        }
    }

    // Nuevo método para que el negocio confirme o rechace un pedido
    public function updateOrderStatusByBusiness($orderId, $newStatus) {
        $this->order->id = $orderId;
        $validStatuses = ['confirmado', 'rechazado'];
        if (!in_array($newStatus, $validStatuses)) {
            http_response_code(400);
            echo json_encode(["message" => "Estado inválido"]);
            return;
        }
        $success = $this->order->cambiarEstado($newStatus, "Estado actualizado por negocio");
        if ($success) {
            http_response_code(200);
            echo json_encode(["message" => "Estado actualizado correctamente"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Error al actualizar estado"]);
        }
    }

    private function assignPoints($id_usuario, $id_pedido, $total) {
        // Verificar si es miembro para puntos x2
        $this->membership->id_usuario = $id_usuario;
        $multiplicador = $this->membership->isActive() ? 2 : 1;

        $puntos = floor($total / 10) * $multiplicador; // 1 punto por cada $10 gastados

        if ($puntos > 0) {
            $query = "INSERT INTO puntos (id_usuario, id_pedido, cantidad, fecha_acreditacion) VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iii", $id_usuario, $id_pedido, $puntos);
            $stmt->execute();

            // Actualizar puntos acumulados en usuarios
            $updateQuery = "UPDATE usuarios SET puntos_acumulados = puntos_acumulados + ? WHERE id_usuario = ?";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bind_param("ii", $puntos, $id_usuario);
            $updateStmt->execute();
        }

        // Verificar y asignar bono por referido
        $this->assignReferralBonus($id_usuario);

        // Verificar y asignar bono por 15 pedidos en el mes
        $this->assignMonthlyOrderBonus($id_usuario);
    }

    private function assignReferralBonus($id_usuario) {
        // Verificar si el usuario fue referido y si el bono ya fue asignado
        require_once 'Referral.php';
        $referral = new Referral($this->db);

        // Verificar si el referido ya tiene pedido_realizado marcado
        $query = "SELECT pedido_realizado, id_usuario_referente FROM referidos WHERE id_usuario_referido = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['pedido_realizado'] == 0) {
                // Marcar pedido_realizado = 1
                $referral->markOrderMade($id_usuario);

                // Asignar 50 puntos al referidor
                $id_referente = $row['id_usuario_referente'];
                $puntos_bono = 50;

                $queryInsert = "INSERT INTO puntos (id_usuario, id_pedido, cantidad, fecha_acreditacion) VALUES (?, NULL, ?, NOW())";
                $stmtInsert = $this->db->prepare($queryInsert);
                $stmtInsert->bind_param("ii", $id_referente, $puntos_bono);
                $stmtInsert->execute();

                // Actualizar puntos acumulados en usuarios
                $updateQuery = "UPDATE usuarios SET puntos_acumulados = puntos_acumulados + ? WHERE id_usuario = ?";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bind_param("ii", $puntos_bono, $id_referente);
                $updateStmt->execute();
            }
        }
    }

    private function assignMonthlyOrderBonus($id_usuario) {
        // Contar pedidos realizados en el mes actual
        $query = "SELECT COUNT(*) as total_pedidos FROM pedidos WHERE id_usuario = ? AND MONTH(fecha_creacion) = MONTH(CURRENT_DATE()) AND YEAR(fecha_creacion) = YEAR(CURRENT_DATE())";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row && $row['total_pedidos'] == 15) {
            // Verificar si ya se asignó el bono este mes
            $queryCheck = "SELECT COUNT(*) as total_bonos FROM puntos WHERE id_usuario = ? AND cantidad = 100 AND fecha_acreditacion >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')";
            $stmtCheck = $this->db->prepare($queryCheck);
            $stmtCheck->bind_param("i", $id_usuario);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            $rowCheck = $resultCheck->fetch_assoc();

            if ($rowCheck && $rowCheck['total_bonos'] == 0) {
                // Asignar bono de 100 puntos
                $puntos_bono = 100;
                $queryInsert = "INSERT INTO puntos (id_usuario, id_pedido, cantidad, fecha_acreditacion) VALUES (?, NULL, ?, NOW())";
                $stmtInsert = $this->db->prepare($queryInsert);
                $stmtInsert->bind_param("ii", $id_usuario, $puntos_bono);
                $stmtInsert->execute();

                // Actualizar puntos acumulados en usuarios
                $updateQuery = "UPDATE usuarios SET puntos_acumulados = puntos_acumulados + ? WHERE id_usuario = ?";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bind_param("ii", $puntos_bono, $id_usuario);
                $updateStmt->execute();
            }
        }
    }

    private function updateOrderCount($id_usuario) {
        $query = "UPDATE usuarios SET pedidos_realizados = pedidos_realizados + 1 WHERE id_usuario = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
    }
}
?>
