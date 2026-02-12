<?php
/**
 * QuickBite - Wallet Service
 * Sistema de Billetera Digital con transacciones seguras
 * * @version 1.0.0
 * @date 2025-11-20
 */

class WalletService {
    private $conn;
    private $logger;

    /**
     * Constructor
     * @param mysqli $connection Conexión a la base de datos
     */
    public function __construct($connection) {
        if (!$connection instanceof mysqli) {
            throw new InvalidArgumentException('Se requiere una conexión mysqli válida');
        }
        $this->conn = $connection;
        $this->initLogger();
    }

    private function initLogger() {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = $logDir . '/wallet_' . date('Y-m-d') . '.log';
    }

    /**
     * Registra eventos en el log
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logger, $logMessage, FILE_APPEND);
    }

    /**
     * Crea una wallet para un usuario
     * * @param int $userId ID del usuario
     * @param string $type Tipo de wallet (repartidor, usuario, negocio)
     * @return array Resultado de la operación
     */
    public function createWallet($userId, $type) {
        try {
            // Validar tipo de wallet
            $validTypes = ['repartidor', 'usuario', 'negocio'];
            if (!in_array($type, $validTypes)) {
                throw new InvalidArgumentException('Tipo de wallet inválido');
            }

            // Validar que el usuario exista
            if (!$this->userExists($userId)) {
                throw new Exception('El usuario no existe');
            }

            // Verificar si ya existe una wallet para este usuario
            $checkStmt = $this->conn->prepare(
                "SELECT id FROM wallets WHERE user_id = ? AND type = ?"
            );
            $checkStmt->bind_param('is', $userId, $type);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $wallet = $result->fetch_assoc();
                $checkStmt->close();
                return [
                    'success' => true,
                    'wallet_id' => $wallet['id'],
                    'message' => 'Wallet ya existe'
                ];
            }
            $checkStmt->close();

            // Crear nueva wallet
            $this->conn->begin_transaction();
            
            try {
                $stmt = $this->conn->prepare(
                    "INSERT INTO wallets (user_id, balance, type, status) 
                     VALUES (?, 0.00, ?, 'active')"
                );
                $stmt->bind_param('is', $userId, $type);
                $stmt->execute();
                $walletId = $this->conn->insert_id;
                $stmt->close();

                // Si es repartidor, crear límite de crédito
                if ($type === 'repartidor') {
                    $limitStmt = $this->conn->prepare(
                        "INSERT INTO wallet_limits (wallet_id, credit_limit, daily_withdrawal_limit) 
                         VALUES (?, -200.00, 5000.00)"
                    );
                    $limitStmt->bind_param('i', $walletId);
                    $limitStmt->execute();
                    $limitStmt->close();
                }

                $this->conn->commit();
                $this->log("Wallet creada: ID={$walletId}, User={$userId}, Type={$type}");
                
                return [
                    'success' => true,
                    'wallet_id' => $walletId,
                    'message' => 'Wallet creada exitosamente'
                ];

            } catch (Exception $e) {
                $this->conn->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->log("Error al crear wallet: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Error al crear wallet: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Agrega una transacción a la wallet
     * Usa transacciones DB y bloqueo FOR UPDATE para evitar race conditions
     * * @param float $amount Monto (positivo para crédito, negativo para débito)
     * @param string $type Tipo de transacción
     * @param string|null $refId ID de referencia (pedido, pago, etc.)
     * @param string|null $refType Tipo de referencia
     * @param string|null $description Descripción
     * @param array|null $metadata Metadatos adicionales
     */
    public function addTransaction($userId, $amount, $type, $refId = null, $refType = null, $description = null, $metadata = null) {
        try {
            // Validar parámetros
            if (!is_numeric($amount) || $amount == 0) {
                throw new InvalidArgumentException('El monto debe ser un número diferente de cero');
            }

            $validTypes = ['credit', 'debit', 'refund', 'commission', 'withdrawal', 'deposit', 'tip', 'bonus', 'penalty'];
            if (!in_array($type, $validTypes)) {
                throw new InvalidArgumentException('Tipo de transacción inválido');
            }

            // Convertir a decimal con 2 decimales
            $amount = round((float)$amount, 2);

            // INICIAR TRANSACCIÓN DE BASE DE DATOS
            $this->conn->begin_transaction();
            
            try {
                // Obtener wallet con bloqueo FOR UPDATE para evitar race conditions
                $walletStmt = $this->conn->prepare(
                    "SELECT id, balance, type, status 
                     FROM wallets 
                     WHERE user_id = ? 
                     FOR UPDATE"
                );
                $walletStmt->bind_param('i', $userId);
                $walletStmt->execute();
                $walletResult = $walletStmt->get_result();

                if ($walletResult->num_rows === 0) {
                    $walletStmt->close();
                    throw new Exception('Wallet no encontrada para este usuario');
                }

                $wallet = $walletResult->fetch_assoc();
                $walletStmt->close();

                // Verificar que la wallet esté activa
                if ($wallet['status'] !== 'active') {
                    throw new Exception('La wallet está suspendida o bloqueada');
                }

                $walletId = $wallet['id'];
                $balanceBefore = $wallet['balance'];
                $balanceAfter = $balanceBefore + $amount;

                // Validar límites para repartidores
                if ($wallet['type'] === 'repartidor' && $balanceAfter < 0) {
                    $limitStmt = $this->conn->prepare(
                        "SELECT credit_limit FROM wallet_limits WHERE wallet_id = ?"
                    );
                    $limitStmt->bind_param('i', $walletId);
                    $limitStmt->execute();
                    $limitResult = $limitStmt->get_result();
                    
                    if ($limitResult->num_rows > 0) {
                        $limit = $limitResult->fetch_assoc()['credit_limit'];
                        if ($balanceAfter < $limit) {
                            $limitStmt->close();
                            throw new Exception('Operación rechazada: excede el límite de crédito permitido');
                        }
                    }
                    $limitStmt->close();
                }

                // Actualizar balance de la wallet
                $updateStmt = $this->conn->prepare(
                    "UPDATE wallets SET balance = ? WHERE id = ?"
                );
                $updateStmt->bind_param('di', $balanceAfter, $walletId);
                $updateStmt->execute();
                $updateStmt->close();

                // Preparar metadata como JSON
                $metadataJson = $metadata ? json_encode($metadata) : null;

                // Insertar registro de transacción
                $transStmt = $this->conn->prepare(
                    "INSERT INTO wallet_transactions 
                     (wallet_id, amount, type, reference_id, reference_type, description, balance_before, balance_after, metadata) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $transStmt->bind_param(
                    'idssssdds',
                    $walletId,
                    $amount,
                    $type,
                    $refId,
                    $refType,
                    $description,
                    $balanceBefore,
                    $balanceAfter,
                    $metadataJson
                );
                $transStmt->execute();
                $transactionId = $this->conn->insert_id;
                $transStmt->close();

                // CONFIRMAR TRANSACCIÓN
                $this->conn->commit();
                
                $this->log(
                    "Transacción exitosa: ID={$transactionId}, Wallet={$walletId}, " .
                    "Amount={$amount}, Type={$type}, BalanceBefore={$balanceBefore}, BalanceAfter={$balanceAfter}"
                );

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'balance_before' => number_format($balanceBefore, 2, '.', ''),
                    'balance_after' => number_format($balanceAfter, 2, '.', ''),
                    'amount' => number_format($amount, 2, '.', ''),
                    'message' => 'Transacción procesada exitosamente'
                ];

            } catch (Exception $e) {
                // REVERTIR TRANSACCIÓN EN CASO DE ERROR
                $this->conn->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->log("Error en transacción: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el balance actual de un usuario
     * @return array Resultado con el balance
     */
    public function getBalance($userId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, balance, type, status FROM wallets WHERE user_id = ?"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                return [
                    'success' => false,
                    'message' => 'Wallet no encontrada'
                ];
            }

            $wallet = $result->fetch_assoc();
            $stmt->close();

            return [
                'success' => true,
                'wallet_id' => $wallet['id'],
                'balance' => number_format($wallet['balance'], 2, '.', ''),
                'type' => $wallet['type'],
                'status' => $wallet['status']
            ];

        } catch (Exception $e) {
            $this->log("Error al obtener balance: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica si un repartidor puede trabajar según su balance
     * @param int $driverId ID del repartidor
     * @param float $limit Límite negativo permitido (por defecto -200)
     * @return bool True si puede trabajar, False si debe bloquearse
     */
    public function canDriverWork($driverId, $limit = -200.00) {
        try {
            $limit = round((float)$limit, 2);
            
            // Obtener balance del repartidor
            $stmt = $this->conn->prepare(
                "SELECT w.balance, COALESCE(wl.credit_limit, -200.00) AS credit_limit
                 FROM wallets w
                 LEFT JOIN wallet_limits wl ON w.id = wl.wallet_id
                 WHERE w.user_id = ? AND w.type = 'repartidor' AND w.status = 'active'"
            );
            $stmt->bind_param('i', $driverId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $this->log("Repartidor {$driverId} sin wallet o inactivo", 'WARNING');
                return false;
            }

            $data = $result->fetch_assoc();
            $balance = (float)$data['balance'];
            $creditLimit = (float)$data['credit_limit'];

            // Usar el límite más restrictivo
            $effectiveLimit = max($limit, $creditLimit);
            $canWork = $balance >= $effectiveLimit;

            $this->log(
                "Verificación repartidor {$driverId}: Balance={$balance}, " .
                "Límite={$effectiveLimit}, Puede trabajar=" . ($canWork ? 'SI' : 'NO')
            );
            return $canWork;

        } catch (Exception $e) {
            $this->log("Error al verificar repartidor: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Verifica si un usuario existe
     * @return bool
     */
    private function userExists($userId) {
        $stmt = $this->conn->prepare(
            "SELECT id FROM usuarios WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Obtiene el historial de transacciones
     * @param int $limit Límite de registros
     * @param int $offset Desplazamiento
     * @return array
     */
    public function getTransactionHistory($userId, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT 
                    wt.id,
                    wt.amount,
                    wt.type,
                    wt.reference_id,
                    wt.reference_type,
                    wt.description,
                    wt.balance_before,
                    wt.balance_after,
                    wt.metadata,
                    wt.created_at
                 FROM wallet_transactions wt
                 INNER JOIN wallets w ON wt.wallet_id = w.id
                 WHERE w.user_id = ?
                 ORDER BY wt.created_at DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->bind_param('iii', $userId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $transactions = [];
            while ($row = $result->fetch_assoc()) {
                $row['metadata'] = $row['metadata'] ? json_decode($row['metadata'], true) : null;
                $transactions[] = $row;
            }

            return [
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ];

        } catch (Exception $e) {
            $this->log("Error al obtener historial: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'transactions' => []
            ];
        }
    }

    /**
     * Suspende o activa una wallet
     * @param string $status Estado (active, suspended, blocked)
     */
    public function updateWalletStatus($userId, $status) {
        try {
            $validStatuses = ['active', 'suspended', 'blocked'];
            if (!in_array($status, $validStatuses)) {
                throw new InvalidArgumentException('Estado inválido');
            }

            $stmt = $this->conn->prepare(
                "UPDATE wallets SET status = ? WHERE user_id = ?"
            );
            $stmt->bind_param('si', $status, $userId);
            $stmt->execute();
            
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                $this->log("Wallet de usuario {$userId} actualizada a estado: {$status}");
                return [
                    'success' => true,
                    'message' => 'Estado actualizado'
                ];
            }

            return [
                'success' => false,
                'message' => 'No se encontró la wallet'
            ];

        } catch (Exception $e) {
            $this->log("Error al actualizar estado: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>