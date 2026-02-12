<?php
/**
 * QuickBite - Servicio de Depósitos OXXO a Wallet
 * Integración con MercadoPago para pagos en efectivo (OXXO, 7Eleven, etc.)
 * * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/WalletService.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

class WalletDepositService {
    
    private $walletService;
    private $conn;
    private $accessToken;
    private $logger;

    public function __construct($connection, $accessToken) {
        if (!$connection instanceof mysqli) {
            throw new InvalidArgumentException('Se requiere una conexión mysqli válida');
        }
        
        $this->conn = $connection;
        $this->walletService = new WalletService($connection);
        $this->accessToken = $accessToken;
        
        // Configurar MercadoPago
        MercadoPagoConfig::setAccessToken($this->accessToken);
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        
        $this->initLogger();
    }

    private function initLogger() {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = $logDir . '/wallet_deposits_' . date('Y-m-d') . '.log';
    }

    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logger, $logMessage, FILE_APPEND);
    }

    /**
     * Crea un pago OXXO para depositar a la wallet
     * * @param int $userId ID del usuario
     * @param float $amount Monto a depositar
     * @param string $email Email del usuario
     * @param string $name Nombre del usuario (opcional)
     * @return array Resultado con URL del ticket OXXO
     */
    public function createOXXODeposit($userId, $amount, $email, $name = null) {
        try {
            // Validar monto mínimo
            if ($amount < 10.00) {
                throw new Exception('El monto mínimo de depósito es $10.00 MXN');
            }
            if ($amount > 10000.00) {
                throw new Exception('El monto máximo de depósito es $10,000.00 MXN');
            }

            // Validar email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }

            // Verificar que el usuario tenga wallet
            $walletInfo = $this->walletService->getBalance($userId);
            if (!$walletInfo['success']) {
                // Crear wallet si no existe
                $createResult = $this->walletService->createWallet($userId, 'usuario');
                if (!$createResult['success']) {
                    throw new Exception('No se pudo crear la wallet del usuario');
                }
            }

            // Generar ID único para el depósito
            $depositId = 'WALLET_OXXO_' . $userId . '_' . time();

            // Crear registro en base de datos
            $stmt = $this->conn->prepare(
                "INSERT INTO wallet_pending_deposits 
                (user_id, amount, payment_method, external_reference, status, email, created_at) 
                VALUES (?, ?, 'oxxo', ?, 'pending', ?, NOW())"
            );
            $stmt->bind_param('idss', $userId, $amount, $depositId, $email);
            $stmt->execute();
            $pendingDepositId = $this->conn->insert_id;
            $stmt->close();

            // Crear pago en MercadoPago
            $client = new PaymentClient();
            
            $paymentData = [
                "transaction_amount" => (float)$amount,
                "description" => "Depósito a Wallet QuickBite - $" . number_format($amount, 2),
                "payment_method_id" => "oxxo",
                "external_reference" => $depositId,
                "notification_url" => $this->getWebhookURL(),
                "payer" => [
                    "email" => $email,
                    "first_name" => $name ?? "Usuario",
                    "last_name" => "QuickBite"
                ],
                "metadata" => [
                    "user_id" => $userId,
                    "deposit_type" => "wallet_oxxo",
                    "pending_deposit_id" => $pendingDepositId
                ]
            ];

            $payment = $client->create($paymentData);

            // Actualizar con el ID de MercadoPago
            $updateStmt = $this->conn->prepare(
                "UPDATE wallet_pending_deposits 
                SET mercadopago_payment_id = ?, 
                    ticket_url = ?,
                    barcode = ?,
                    expires_at = ?
                WHERE id = ?"
            );

            $mpPaymentId = $payment->id;
            $ticketUrl = $payment->transaction_details->external_resource_url ?? null;
            $barcode = $payment->barcode->content ?? null;
            $expiresAt = isset($payment->date_of_expiration) 
                ? date('Y-m-d H:i:s', strtotime($payment->date_of_expiration))
                : date('Y-m-d H:i:s', strtotime('+3 days'));

            $updateStmt->bind_param('ssssi', $mpPaymentId, $ticketUrl, $barcode, $expiresAt, $pendingDepositId);
            $updateStmt->execute();
            $updateStmt->close();

            $this->log(
                "Depósito OXXO creado: User={$userId}, Amount={$amount}, " .
                "DepositID={$depositId}, MPPaymentID={$mpPaymentId}"
            );

            return [
                'success' => true,
                'deposit_id' => $depositId,
                'mercadopago_payment_id' => $mpPaymentId,
                'amount' => number_format($amount, 2, '.', ''),
                'ticket_url' => $ticketUrl,
                'barcode' => $barcode,
                'expires_at' => $expiresAt,
                'status' => 'pending',
                'instructions' => [
                    '1. Descarga o imprime tu ticket de pago',
                    '2. Acude a cualquier OXXO',
                    '3. Entrega el código de barras al cajero',
                    '4. Realiza el pago en efectivo',
                    '5. Tu saldo se acreditará automáticamente en minutos'
                ],
                'message' => 'Ticket OXXO generado exitosamente'
            ];

        } catch (MPApiException $e) {
            $this->log("Error MercadoPago: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Error al generar ticket OXXO: ' . $e->getApiResponse()->getContent()
            ];
        } catch (Exception $e) {
            $this->log("Error al crear depósito OXXO: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Procesa el webhook de MercadoPago cuando se confirma el pago
     * @param int $paymentId ID del pago de MercadoPago
     * @return array
     */
    public function processWebhookPayment($paymentId) {
        try {
            $this->log("Procesando webhook para payment ID: {$paymentId}");
            
            // Instanciar cliente (faltaba en tu código original)
            $client = new PaymentClient();

            // Obtener información del pago de MercadoPago
            $payment = $client->get($paymentId);

            if ($payment->status !== 'approved') {
                $this->log("Pago no aprobado. Estado: {$payment->status}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Pago no aprobado',
                    'status' => $payment->status
                ];
            }

            $externalRef = $payment->external_reference;
            $amount = $payment->transaction_amount;
            // MercadoPago devuelve metadata como objeto
            $userId = $payment->metadata->user_id ?? null;

            if (!$userId) {
                throw new Exception('No se encontró user_id en metadata del pago');
            }

            // Verificar si ya fue procesado (prevenir duplicados)
            $checkStmt = $this->conn->prepare(
                "SELECT id, status FROM wallet_pending_deposits 
                 WHERE mercadopago_payment_id = ? AND status = 'pending' LIMIT 1"
            );
            $checkStmt->bind_param('s', $paymentId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result->num_rows === 0) {
                $checkStmt->close();
                $this->log("Pago ya procesado o no encontrado: {$paymentId}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Pago ya procesado o no encontrado'
                ];
            }

            $pendingDeposit = $result->fetch_assoc();
            $checkStmt->close();

            // INICIAR TRANSACCIÓN DB
            $this->conn->begin_transaction();
            
            try {
                // 1. Acreditar a la wallet del usuario
                $walletResult = $this->walletService->addTransaction(
                    $userId,                // userId
                    $amount,                // amount
                    'deposit',              // type
                    $externalRef,           // refId
                    'oxxo_payment',         // refType
                    "Depósito OXXO - Pago #{$paymentId}", // description
                    [                       // metadata
                        'payment_method' => 'oxxo',
                        'mercadopago_id' => $paymentId,
                        'external_reference' => $externalRef
                    ]
                );

                if (!$walletResult['success']) {
                    throw new Exception('Error al acreditar a wallet: ' . $walletResult['message']);
                }

                // 2. Actualizar estado del depósito pendiente
                $updateStmt = $this->conn->prepare(
                    "UPDATE wallet_pending_deposits 
                     SET status = 'completed', 
                         wallet_transaction_id = ?,
                         completed_at = NOW()
                     WHERE id = ?"
                );

                $transactionId = $walletResult['transaction_id'];
                $updateStmt->bind_param('ii', $transactionId, $pendingDeposit['id']);
                $updateStmt->execute();
                $updateStmt->close();

                $this->conn->commit();
                
                $this->log(
                    "Depósito OXXO completado: User={$userId}, Amount={$amount}, " .
                    "MPPaymentID={$paymentId}, WalletTxID={$transactionId}"
                );

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'amount' => number_format($amount, 2, '.', ''),
                    'new_balance' => $walletResult['balance_after'],
                    'transaction_id' => $transactionId,
                    'message' => 'Depósito acreditado exitosamente'
                ];

            } catch (Exception $e) {
                $this->conn->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->log("Error al procesar webhook: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el estado de un depósito pendiente
     * @param string $depositId ID del depósito
     * @param int $userId ID del usuario (para seguridad)
     */
    public function getDepositStatus($depositId, $userId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT 
                    id,
                    amount,
                    payment_method,
                    mercadopago_payment_id,
                    status,
                    ticket_url,
                    barcode,
                    expires_at,
                    created_at,
                    completed_at
                FROM wallet_pending_deposits
                WHERE external_reference = ? AND user_id = ?"
            );
            $stmt->bind_param('si', $depositId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Depósito no encontrado'
                ];
            }

            $deposit = $result->fetch_assoc();
            $stmt->close();

            return [
                'success' => true,
                'deposit' => [
                    'id' => $depositId,
                    'amount' => number_format($deposit['amount'], 2, '.', ''),
                    'status' => $deposit['status'],
                    'payment_method' => $deposit['payment_method'],
                    'ticket_url' => $deposit['ticket_url'],
                    'barcode' => $deposit['barcode'],
                    'expires_at' => $deposit['expires_at'],
                    'created_at' => $deposit['created_at'],
                    'completed_at' => $deposit['completed_at']
                ]
            ];

        } catch (Exception $e) {
            $this->log("Error al obtener estado del depósito: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene historial de depósitos del usuario
     * @param int $userId
     * @param int $limit
     */
    public function getDepositHistory($userId, $limit = 20) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT 
                    external_reference,
                    amount,
                    payment_method,
                    status,
                    created_at,
                    completed_at
                FROM wallet_pending_deposits
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?"
            );
            $stmt->bind_param('ii', $userId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $deposits = [];
            while ($row = $result->fetch_assoc()) {
                $deposits[] = [
                    'id' => $row['external_reference'],
                    'amount' => number_format($row['amount'], 2, '.', ''),
                    'method' => $row['payment_method'],
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'completed_at' => $row['completed_at']
                ];
            }
            $stmt->close();

            return [
                'success' => true,
                'deposits' => $deposits,
                'count' => count($deposits)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'deposits' => []
            ];
        }
    }

    /**
     * Cancela un depósito pendiente (si aún no se pagó)
     * @param string $depositId
     */
    public function cancelDeposit($depositId, $userId) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE wallet_pending_deposits 
                 SET status = 'cancelled'
                 WHERE external_reference = ? 
                 AND user_id = ? 
                 AND status = 'pending'"
            );
            $stmt->bind_param('si', $depositId, $userId);
            $stmt->execute();
            
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                $this->log("Depósito cancelado: {$depositId}");
                return [
                    'success' => true,
                    'message' => 'Depósito cancelado'
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo cancelar el depósito o ya no está pendiente'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function getWebhookURL() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return "{$protocol}://{$host}/webhooks/wallet_oxxo_webhook.php";
    }
}
?>