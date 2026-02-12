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
            $query = "INSERT INTO " . $this->table_name . " (id_usuario, fecha_inicio, fecha_fin, estado) VALUES (:id_usuario, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 'activo')";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_usuario', $this->id_usuario, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Actualizar campo es_miembro en usuarios
                $updateQuery = "UPDATE usuarios SET es_miembro = 1, fecha_inicio_membresia = NOW(), fecha_fin_membresia = DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id_usuario = :id_usuario";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':id_usuario', $this->id_usuario, PDO::PARAM_INT);
                $updateStmt->execute();

                return true;
            }
            return false;
        }

        // Verificar estado de membresía activa
        public function isActive() {
            $query = "SELECT estado FROM " . $this->table_name . " WHERE id_usuario = :id_usuario AND estado = 'activo' AND fecha_fin >= NOW() LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_usuario', $this->id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result !== false;
        }

        // En models/Membership.php
    public function renew($id_usuario, $plan) {
        try {
            $this->conn->beginTransaction();
            
            // Obtener membresía actual
            $query = "SELECT * FROM membresias 
                    WHERE id_usuario = ? AND estado = 'activo' 
                    ORDER BY fecha_fin DESC LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id_usuario, PDO::PARAM_INT);
            $stmt->execute();
            
            $membresiaActual = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($membresiaActual) {
                // Calcular nueva fecha de fin
                $fechaInicioRenovacion = max(
                    new DateTime(), 
                    new DateTime($membresiaActual['fecha_fin'])
                );
                
                $duracion = $plan === 'yearly' ? '+1 year' : '+1 month';
                $nuevaFechaFin = clone $fechaInicioRenovacion;
                $nuevaFechaFin->modify($duracion);
                
                // Actualizar membresía existente
                $updateQuery = "UPDATE membresias 
                            SET fecha_fin = ?, 
                                fecha_actualizacion = NOW(),
                                plan = ?
                            WHERE id_membresia = ?";
                
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(1, $nuevaFechaFin->format('Y-m-d H:i:s'));
                $updateStmt->bindParam(2, $plan);
                $updateStmt->bindParam(3, $membresiaActual['id_membresia'], PDO::PARAM_INT);
                
                $resultado = $updateStmt->execute();
            } else {
                // No hay membresía activa, crear nueva
                $resultado = $this->subscribe($id_usuario, $plan);
            }
            
            if ($resultado) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollback();
                return false;
            }
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error renovando membresía: " . $e->getMessage());
            return false;
        }
    }
        // Cancelar membresía (cambiar estado a inactivo)
        public function cancel() {
            $query = "UPDATE " . $this->table_name . " SET estado = 'inactivo' WHERE id_usuario = :id_usuario AND estado = 'activo'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_usuario', $this->id_usuario, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Actualizar campo es_miembro en usuarios
                $updateQuery = "UPDATE usuarios SET es_miembro = 0, fecha_fin_membresia = NOW() WHERE id_usuario = :id_usuario";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':id_usuario', $this->id_usuario, PDO::PARAM_INT);
                $updateStmt->execute();

                return true;
            }
            return false;
        }

        // Obtener información de membresía activa
        public function getActiveMembership() {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id_usuario = :id_usuario AND estado = 'activo' AND fecha_fin >= NOW() LIMIT 1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_usuario', $this->id_usuario, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result !== false) {
                return $result;
            }
            return null;
        }
    }
    ?>
