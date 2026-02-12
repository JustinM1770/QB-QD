<?php
/**
 * Clase WalletMercadoPago - Integración con MercadoPago para pagos de wallet
 * Maneja wallets de negocios y repartidores
 */

require_once __DIR__ . '/../vendor/autoload.php';
// No necesitamos clases específicas de MercadoPago para el wallet
// Solo usamos nuestra BD para gestionar saldos
class WalletMercadoPago {
    private $conn;
    private $access_token;
    private $public_key;
    
    const TIPO_NEGOCIO = 'business';
    const TIPO_REPARTIDOR = 'courier';
    const ESTADO_ACTIVO = 'activo';
    const ESTADO_BLOQUEADO = 'bloqueado';
    const ESTADO_SUSPENDIDO = 'suspendido';
    const COMISION_PLATAFORMA = 0.10; // 10%
    const MONTO_MINIMO_RETIRO = 100;
    public function __construct($db, $access_token, $public_key = null) {
        $this->conn = $db;
        $this->access_token = $access_token;
        $this->public_key = $public_key;
        
        // MercadoPago SDK se inicializa solo cuando es necesario para pagos
        // Para wallet, solo usamos nuestra BD
    }
    /**
     * Crear wallet para negocio o repartidor
     */
    public function crearWallet($id_usuario, $tipo_usuario, $nombre, $email) {
        try {
            $this->conn->beginTransaction();
            
            // En MercadoPago no necesitamos crear cuenta externa
            // Solo creamos el registro en nuestra BD
            $codigo_cuenta = 'MP_' . $tipo_usuario . '_' . $id_usuario . '_' . time();
            $query = "INSERT INTO wallets (
                id_usuario, 
                tipo_usuario, 
                cuenta_externa_id,
                estado,
                saldo_disponible,
                saldo_pendiente,
                fecha_creacion
            ) VALUES (?, ?, ?, ?, 0.00, 0.00, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $id_usuario,
                $tipo_usuario,
                $codigo_cuenta,
                self::ESTADO_ACTIVO
            ]);
            $id_wallet = $this->conn->lastInsertId();
            $this->conn->commit();
            return [
                'exito' => true,
                'id_wallet' => $id_wallet,
                'cuenta_id' => $codigo_cuenta,
                'mensaje' => 'Wallet creada exitosamente'
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error creando wallet: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener información de wallet
     */
    public function obtenerWallet($id_usuario, $tipo_usuario) {
        try {
            $query = "SELECT * FROM wallets 
                     WHERE id_usuario = ? AND tipo_usuario = ? 
                     ORDER BY fecha_creacion DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id_usuario, $tipo_usuario]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo wallet: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener resumen de wallet
     */
    public function obtenerResumen($id_wallet) {
        try {
            $query = "SELECT 
                (SELECT COUNT(*) FROM wallet_transacciones 
                 WHERE id_wallet = ? AND estado = 'completado') as total_transacciones,
                (SELECT SUM(monto) FROM wallet_transacciones 
                 WHERE id_wallet = ? AND tipo = 'ingreso' AND estado = 'completado') as total_ingresos,
                (SELECT SUM(monto) FROM wallet_transacciones 
                 WHERE id_wallet = ? AND tipo = 'retiro' AND estado = 'completado') as total_retiros
            FROM wallets WHERE id_wallet = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id_wallet, $id_wallet, $id_wallet, $id_wallet]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo resumen: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener transacciones de wallet
     */
    public function obtenerTransacciones($id_wallet, $limite = 20) {
        try {
            $query = "SELECT * FROM wallet_transacciones
                     WHERE id_wallet = ?
                     ORDER BY fecha DESC
                     LIMIT ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id_wallet, PDO::PARAM_INT);
            $stmt->bindParam(2, $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo transacciones: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registrar ingreso por comisión de pedido
     */
    public function registrarComision($id_wallet, $id_pedido, $monto, $descripcion) {
        try {
            // Calcular comisión de plataforma
            $comision_plataforma = $monto * self::COMISION_PLATAFORMA;
            $monto_neto = $monto - $comision_plataforma;
            
            $this->conn->beginTransaction();
            
            // Registrar transacción
            $query = "INSERT INTO wallet_transacciones (
                id_wallet,
                tipo,
                monto,
                comision,
                descripcion,
                id_pedido,
                estado,
                fecha
            ) VALUES (?, 'ingreso', ?, ?, ?, ?, 'completado', NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $id_wallet,
                $monto_neto,
                $comision_plataforma,
                $descripcion,
                $id_pedido
            ]);
            
            // Actualizar saldo disponible
            $query = "UPDATE wallets 
                     SET saldo_disponible = saldo_disponible + ? 
                     WHERE id_wallet = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$monto_neto, $id_wallet]);
            
            $this->conn->commit();
            
            return [
                'exito' => true,
                'monto_neto' => $monto_neto,
                'comision' => $comision_plataforma
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            error_log("Error registrando comisión: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Solicitar retiro de fondos
     */
    public function solicitarRetiro($id_wallet, $monto) {
        try {
            $this->conn->beginTransaction();
            
            // Validar monto mínimo
            if ($monto < self::MONTO_MINIMO_RETIRO) {
                throw new Exception("El monto mínimo de retiro es $" . self::MONTO_MINIMO_RETIRO);
            }
            
            // Verificar saldo disponible
            $wallet = $this->obtenerWalletPorId($id_wallet);
            if ($wallet['saldo_disponible'] < $monto) {
                throw new Exception("Saldo insuficiente");
            }
            
            // Crear transacción de retiro
            $query = "INSERT INTO wallet_transacciones (
                id_wallet, tipo, monto, comision, descripcion, estado, fecha
            ) VALUES (?, 'retiro', ?, 0, 'Solicitud de retiro', 'pendiente', NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $id_wallet,
                -$monto
            ]);
            
            $id_transaccion = $this->conn->lastInsertId();
            
            // Actualizar saldos
            $query = "UPDATE wallets
                     SET saldo_disponible = saldo_disponible - ?,
                         saldo_pendiente = saldo_pendiente + ?
                     WHERE id_wallet = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$monto, $monto, $id_wallet]);
            
            $this->conn->commit();
            
            return [
                'exito' => true,
                'id_transaccion' => $id_transaccion,
                'monto' => $monto,
                'mensaje' => 'Solicitud de retiro registrada. Será procesada en 1-3 días hábiles.'
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            error_log("Error en retiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener wallet por ID
     */
    private function obtenerWalletPorId($id_wallet) {
        $query = "SELECT * FROM wallets WHERE id_wallet = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id_wallet]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Procesar retiro (función administrativa)
     */
    public function procesarRetiro($id_transaccion, $referencia_externa = null) {
        try {
            $this->conn->beginTransaction();
            
            // Obtener transacción
            $query = "SELECT * FROM wallet_transacciones WHERE id_transaccion = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id_transaccion]);
            $transaccion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaccion || $transaccion['estado'] !== 'pendiente') {
                throw new Exception("Transacción no válida");
            }
            
            // Actualizar transacción
            $query = "UPDATE wallet_transacciones
                     SET estado = 'completado',
                         referencia_stripe = ?
                     WHERE id_transaccion = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$referencia_externa, $id_transaccion]);
            
            // Actualizar saldo pendiente
            $query = "UPDATE wallets
                     SET saldo_pendiente = saldo_pendiente - ?
                     WHERE id_wallet = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                abs($transaccion['monto']),
                $transaccion['id_wallet']
            ]);
            
            $this->conn->commit();
            
            return [
                'exito' => true,
                'mensaje' => 'Retiro procesado exitosamente'
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            error_log("Error procesando retiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener estadísticas de wallet
     */
    public function obtenerEstadisticas($id_wallet) {
        try {
            $query = "SELECT
                DATE(fecha) as fecha_dia,
                SUM(CASE WHEN tipo IN ('ingreso', 'ingreso_pedido', 'ingreso_entrega') THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'retiro' THEN ABS(monto) ELSE 0 END) as retiros,
                COUNT(*) as transacciones
            FROM wallet_transacciones
            WHERE id_wallet = ? AND estado = 'completado'
            AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(fecha)
            ORDER BY fecha_dia DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id_wallet]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }
}
