<?php
/**
 * Clase ReferralRepartidor
 *
 * Gestiona el sistema de referidos entre repartidores con bonificaciones
 * A diferencia de los clientes que reciben descuentos, los repartidores reciben bonificaciones en efectivo
 */
class ReferralRepartidor {
    private $conn;
    private $table_referidos = "referidos_repartidores";
    private $table_beneficios = "beneficios_repartidores";
    private $table_config = "config_bonificaciones_repartidor";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generar codigo de referido unico para un repartidor
     */
    public function generarCodigoReferido($id_repartidor) {
        // Generar codigo basado en id y timestamp
        $codigo = 'REP' . strtoupper(substr(md5($id_repartidor . time() . 'quickbite_rep'), 0, 8));

        // Verificar que no exista
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM repartidores WHERE codigo_referido = ?");
        $stmt->execute([$codigo]);

        if ($stmt->fetchColumn() > 0) {
            // Si existe, agregar caracteres aleatorios
            $codigo = 'REP' . strtoupper(substr(md5($id_repartidor . microtime(true)), 0, 8));
        }

        // Actualizar el repartidor con su codigo
        $stmt = $this->conn->prepare("UPDATE repartidores SET codigo_referido = ? WHERE id_repartidor = ?");
        $stmt->execute([$codigo, $id_repartidor]);

        return $codigo;
    }

    /**
     * Obtener codigo de referido de un repartidor (o generarlo si no existe)
     */
    public function obtenerCodigoReferido($id_repartidor) {
        $stmt = $this->conn->prepare("SELECT codigo_referido FROM repartidores WHERE id_repartidor = ?");
        $stmt->execute([$id_repartidor]);
        $codigo = $stmt->fetchColumn();

        if (empty($codigo)) {
            $codigo = $this->generarCodigoReferido($id_repartidor);
        }

        return $codigo;
    }

    /**
     * Verificar si un codigo de referido es valido y obtener el id del referente
     */
    public function verificarCodigoReferido($codigo) {
        $stmt = $this->conn->prepare("SELECT id_repartidor FROM repartidores WHERE codigo_referido = ? AND activo = 1");
        $stmt->execute([$codigo]);
        return $stmt->fetchColumn();
    }

    /**
     * Registrar un nuevo referido
     */
    public function registrarReferido($codigo_referido, $id_repartidor_referido) {
        $id_referente = $this->verificarCodigoReferido($codigo_referido);

        if (!$id_referente) {
            return ['success' => false, 'message' => 'Codigo de referido invalido'];
        }

        if ($id_referente == $id_repartidor_referido) {
            return ['success' => false, 'message' => 'No puedes referirte a ti mismo'];
        }

        // Verificar que no exista ya el referido
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table_referidos} WHERE id_repartidor_referido = ?");
        $stmt->execute([$id_repartidor_referido]);

        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Este repartidor ya fue referido por alguien mas'];
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table_referidos}
                (id_repartidor_referente, id_repartidor_referido, fecha_referido)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$id_referente, $id_repartidor_referido]);

            // Incrementar contador de referidos del referente
            $stmt = $this->conn->prepare("UPDATE repartidores SET total_referidos = total_referidos + 1 WHERE id_repartidor = ?");
            $stmt->execute([$id_referente]);

            return ['success' => true, 'message' => 'Referido registrado correctamente'];
        } catch (Exception $e) {
            error_log("Error al registrar referido: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar referido'];
        }
    }

    /**
     * Contar total de referidos de un repartidor
     */
    public function contarTotalReferidos($id_repartidor) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table_referidos} WHERE id_repartidor_referente = ?");
        $stmt->execute([$id_repartidor]);
        return $stmt->fetchColumn();
    }

    /**
     * Contar referidos activos (que han completado entregas)
     */
    public function contarReferidosActivos($id_repartidor, $minimo_entregas = 10) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM {$this->table_referidos} rr
            JOIN repartidores r ON rr.id_repartidor_referido = r.id_repartidor
            WHERE rr.id_repartidor_referente = ?
            AND r.total_entregas >= ?
            AND r.activo = 1
        ");
        $stmt->execute([$id_repartidor, $minimo_entregas]);
        return $stmt->fetchColumn();
    }

    /**
     * Obtener historial de referidos
     */
    public function obtenerHistorialReferidos($id_repartidor) {
        $stmt = $this->conn->prepare("
            SELECT
                rr.id_referido,
                rr.fecha_referido,
                rr.entregas_completadas,
                rr.bonificacion_otorgada,
                rr.fecha_bonificacion,
                u.nombre,
                u.apellido,
                r.total_entregas,
                r.calificacion_promedio,
                r.activo,
                n.nombre as nivel_nombre,
                n.emoji as nivel_emoji
            FROM {$this->table_referidos} rr
            JOIN repartidores r ON rr.id_repartidor_referido = r.id_repartidor
            JOIN usuarios u ON r.id_usuario = u.id_usuario
            LEFT JOIN niveles_repartidor n ON r.id_nivel = n.id_nivel
            WHERE rr.id_repartidor_referente = ?
            ORDER BY rr.fecha_referido DESC
        ");
        $stmt->execute([$id_repartidor]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar y otorgar bonificacion por referido
     */
    public function verificarBonificacionReferido($id_repartidor_referido) {
        // Obtener datos del referido
        $stmt = $this->conn->prepare("
            SELECT rr.*, r.total_entregas
            FROM {$this->table_referidos} rr
            JOIN repartidores r ON rr.id_repartidor_referido = r.id_repartidor
            WHERE rr.id_repartidor_referido = ?
        ");
        $stmt->execute([$id_repartidor_referido]);
        $referido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referido) {
            return; // No es un repartidor referido
        }

        // Obtener configuracion de bonificacion
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_config} WHERE tipo_bonificacion = 'referido_nuevo' AND activo = 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            return;
        }

        // Verificar si cumple requisito y no se ha otorgado
        if ($referido['total_entregas'] >= $config['requisito_minimo'] && !$referido['bonificacion_otorgada']) {
            $this->otorgarBonificacion(
                $referido['id_repartidor_referente'],
                'referido',
                $config['monto'],
                $config['descripcion'],
                $referido['id_referido']
            );

            // Marcar como otorgada
            $stmt = $this->conn->prepare("
                UPDATE {$this->table_referidos}
                SET bonificacion_otorgada = 1, fecha_bonificacion = NOW(), entregas_completadas = ?
                WHERE id_referido = ?
            ");
            $stmt->execute([$referido['total_entregas'], $referido['id_referido']]);
        }
    }

    /**
     * Otorgar una bonificacion a un repartidor
     */
    public function otorgarBonificacion($id_repartidor, $tipo, $monto, $descripcion, $id_referido = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table_beneficios}
                (id_repartidor, tipo_beneficio, monto_bonificacion, descripcion, estado, id_referido_relacionado, fecha_solicitud)
                VALUES (?, ?, ?, ?, 'aprobado', ?, NOW())
            ");
            $stmt->execute([$id_repartidor, $tipo, $monto, $descripcion, $id_referido]);

            // Acreditar directamente al wallet del repartidor
            $this->acreditarBonificacion($id_repartidor, $monto, $this->conn->lastInsertId());

            return true;
        } catch (Exception $e) {
            error_log("Error al otorgar bonificacion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Acreditar bonificacion al wallet del repartidor
     */
    private function acreditarBonificacion($id_repartidor, $monto, $id_beneficio) {
        try {
            // Actualizar saldo del repartidor
            $stmt = $this->conn->prepare("
                UPDATE repartidores
                SET saldo_wallet = saldo_wallet + ?,
                    total_bonificaciones = total_bonificaciones + ?
                WHERE id_repartidor = ?
            ");
            $stmt->execute([$monto, $monto, $id_repartidor]);

            // Registrar transaccion en wallet
            $stmt = $this->conn->prepare("
                INSERT INTO transacciones_wallet_repartidor
                (id_repartidor, tipo, monto, descripcion, fecha_transaccion)
                VALUES (?, 'bonificacion', ?, 'Bonificacion por programa de referidos', NOW())
            ");
            $stmt->execute([$id_repartidor, $monto]);

            // Marcar beneficio como acreditado
            $stmt = $this->conn->prepare("
                UPDATE {$this->table_beneficios}
                SET estado = 'acreditado', fecha_acreditacion = NOW()
                WHERE id_beneficio = ?
            ");
            $stmt->execute([$id_beneficio]);

            return true;
        } catch (Exception $e) {
            error_log("Error al acreditar bonificacion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener historial de bonificaciones de un repartidor
     */
    public function obtenerHistorialBonificaciones($id_repartidor) {
        $stmt = $this->conn->prepare("
            SELECT b.*,
                   CONCAT(u.nombre, ' ', u.apellido) as referido_nombre
            FROM {$this->table_beneficios} b
            LEFT JOIN {$this->table_referidos} rr ON b.id_referido_relacionado = rr.id_referido
            LEFT JOIN repartidores r ON rr.id_repartidor_referido = r.id_repartidor
            LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
            WHERE b.id_repartidor = ?
            ORDER BY b.fecha_solicitud DESC
        ");
        $stmt->execute([$id_repartidor]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener total de bonificaciones de un repartidor
     */
    public function obtenerTotalBonificaciones($id_repartidor) {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(monto_bonificacion), 0) as total
            FROM {$this->table_beneficios}
            WHERE id_repartidor = ? AND estado = 'acreditado'
        ");
        $stmt->execute([$id_repartidor]);
        return $stmt->fetchColumn();
    }

    /**
     * Obtener estadisticas de referidos
     */
    public function obtenerEstadisticasReferidos($id_repartidor) {
        return [
            'total_referidos' => $this->contarTotalReferidos($id_repartidor),
            'referidos_activos' => $this->contarReferidosActivos($id_repartidor),
            'total_bonificaciones' => $this->obtenerTotalBonificaciones($id_repartidor),
            'codigo_referido' => $this->obtenerCodigoReferido($id_repartidor)
        ];
    }

    /**
     * Obtener configuracion de bonificaciones activas
     */
    public function obtenerConfiguracionBonificaciones() {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_config} WHERE activo = 1 ORDER BY monto ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generar enlace de referido
     */
    public function generarEnlaceReferido($id_repartidor) {
        $codigo = $this->obtenerCodigoReferido($id_repartidor);
        return "https://quickbite.com.mx/registro-repartidor.php?ref=" . $codigo;
    }
}
?>
