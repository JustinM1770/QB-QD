<?php
/**
 * QuickBite - Servicio de Pagos SPEI (Transferencia Bancaria)
 * Integración con MercadoPago para pagos vía transferencia bancaria en México
 *
 * @version 1.0.0
 * @date 2026-01-24
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

class SPEIPaymentService {

    private $conn;
    private $accessToken;
    private $logger;
    private $paymentClient;

    /**
     * Constructor
     * @param mysqli $connection Conexión a base de datos
     * @param string $accessToken Token de acceso de MercadoPago (opcional, usa env si no se proporciona)
     */
    public function __construct($connection, $accessToken = null) {
        if (!$connection instanceof mysqli) {
            throw new InvalidArgumentException('Se requiere una conexión mysqli válida');
        }

        $this->conn = $connection;
        $this->accessToken = $accessToken ?? env('MP_ACCESS_TOKEN');

        if (empty($this->accessToken)) {
            throw new InvalidArgumentException('Se requiere un access token de MercadoPago');
        }

        // Configurar MercadoPago SDK
        MercadoPagoConfig::setAccessToken($this->accessToken);
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

        $this->paymentClient = new PaymentClient();
        $this->initLogger();
    }

    /**
     * Inicializa el logger
     */
    private function initLogger() {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = $logDir . '/spei_payments_' . date('Y-m-d') . '.log';
    }

    /**
     * Escribe en el log
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logger, $logMessage, FILE_APPEND);
    }

    /**
     * Crea un pago SPEI (transferencia bancaria) para un pedido
     *
     * @param array $params Parámetros del pago:
     *   - pedido_id: ID del pedido
     *   - amount: Monto a pagar
     *   - email: Email del pagador
     *   - description: Descripción del pago (opcional)
     *   - first_name: Nombre del pagador (opcional)
     *   - last_name: Apellido del pagador (opcional)
     * @return array Resultado con datos del pago SPEI
     */
    public function createSPEIPayment(array $params) {
        try {
            // Validar parámetros requeridos
            $required = ['pedido_id', 'amount', 'email'];
            foreach ($required as $field) {
                if (empty($params[$field])) {
                    throw new Exception("Campo requerido: {$field}");
                }
            }

            $pedidoId = (int)$params['pedido_id'];
            $amount = (float)$params['amount'];
            $email = filter_var($params['email'], FILTER_VALIDATE_EMAIL);

            if (!$email) {
                throw new Exception('Email inválido');
            }

            // Validar monto mínimo y máximo
            if ($amount < 10.00) {
                throw new Exception('El monto mínimo para transferencia SPEI es $10.00 MXN');
            }
            if ($amount > 500000.00) {
                throw new Exception('El monto máximo para transferencia SPEI es $500,000.00 MXN');
            }

            // Generar referencia externa única
            $externalReference = 'QB_SPEI_' . $pedidoId . '_' . time();

            // Descripción del pago
            $description = $params['description'] ?? "Pedido QuickBite #{$pedidoId}";

            // Crear el pago en MercadoPago con método bank_transfer (SPEI)
            $paymentData = [
                "transaction_amount" => $amount,
                "description" => $description,
                "payment_method_id" => "bank_transfer",
                "external_reference" => $externalReference,
                "notification_url" => $this->getWebhookURL(),
                "payer" => [
                    "email" => $email,
                    "first_name" => $params['first_name'] ?? "Cliente",
                    "last_name" => $params['last_name'] ?? "QuickBite",
                    "entity_type" => "individual",
                    "type" => "customer"
                ],
                "metadata" => [
                    "pedido_id" => $pedidoId,
                    "payment_type" => "spei",
                    "platform" => "quickbite"
                ],
                "additional_info" => [
                    "items" => [
                        [
                            "id" => "pedido_{$pedidoId}",
                            "title" => $description,
                            "quantity" => 1,
                            "unit_price" => $amount
                        ]
                    ]
                ]
            ];

            $this->log("Creando pago SPEI: " . json_encode([
                'pedido_id' => $pedidoId,
                'amount' => $amount,
                'email' => $email
            ]));

            // Crear pago en MercadoPago
            $payment = $this->paymentClient->create($paymentData);

            // Extraer datos del pago SPEI
            $mpPaymentId = $payment->id;
            $status = $payment->status;
            $statusDetail = $payment->status_detail ?? '';

            // Extraer información de la transferencia SPEI
            $transactionDetails = $payment->transaction_details ?? null;
            $pointOfInteraction = $payment->point_of_interaction ?? null;

            // CLABE y datos bancarios para la transferencia
            $bankInfo = null;
            $clabe = null;
            $ticketUrl = null;

            if ($pointOfInteraction && isset($pointOfInteraction->transaction_data)) {
                $txData = $pointOfInteraction->transaction_data;
                $clabe = $txData->bank_info->collector->account_id ?? null;
                $bankInfo = [
                    'clabe' => $clabe,
                    'bank_name' => $txData->bank_info->collector->bank_name ?? 'Banco',
                    'beneficiary' => $txData->bank_info->collector->account_holder_name ?? 'QuickBite',
                    'reference' => $txData->ticket_id ?? $externalReference
                ];
                $ticketUrl = $txData->ticket_url ?? null;
            }

            // También puede venir en transaction_details
            if (!$clabe && $transactionDetails) {
                $ticketUrl = $transactionDetails->external_resource_url ?? $ticketUrl;
            }

            // Fecha de expiración (por defecto 3 días)
            $expiresAt = $payment->date_of_expiration
                ? date('Y-m-d H:i:s', strtotime($payment->date_of_expiration))
                : date('Y-m-d H:i:s', strtotime('+3 days'));

            // Actualizar el pedido con la información del pago
            $this->updateOrderWithPayment($pedidoId, [
                'payment_id' => $mpPaymentId,
                'payment_method' => 'spei',
                'payment_status' => $status,
                'external_reference' => $externalReference,
                'clabe' => $clabe,
                'expires_at' => $expiresAt
            ]);

            // Guardar registro del pago SPEI pendiente
            $this->saveSPEIPaymentRecord([
                'pedido_id' => $pedidoId,
                'mercadopago_payment_id' => $mpPaymentId,
                'external_reference' => $externalReference,
                'amount' => $amount,
                'email' => $email,
                'clabe' => $clabe,
                'bank_info' => $bankInfo,
                'ticket_url' => $ticketUrl,
                'status' => $status,
                'expires_at' => $expiresAt
            ]);

            $this->log("Pago SPEI creado exitosamente: MP_ID={$mpPaymentId}, Pedido={$pedidoId}, CLABE={$clabe}");

            return [
                'success' => true,
                'payment_id' => $mpPaymentId,
                'pedido_id' => $pedidoId,
                'external_reference' => $externalReference,
                'amount' => number_format($amount, 2, '.', ''),
                'status' => $status,
                'status_detail' => $statusDetail,
                'clabe' => $clabe,
                'bank_info' => $bankInfo,
                'ticket_url' => $ticketUrl,
                'expires_at' => $expiresAt,
                'instructions' => [
                    '1. Ingresa a tu banca en línea o app bancaria',
                    '2. Selecciona la opción de transferencia SPEI',
                    '3. Ingresa la CLABE: ' . ($clabe ?? 'Ver en el ticket'),
                    '4. Ingresa el monto exacto: $' . number_format($amount, 2),
                    '5. En concepto/referencia escribe: ' . $externalReference,
                    '6. Confirma la transferencia',
                    '7. Tu pago se acreditará en minutos (máx. 24 horas)'
                ],
                'message' => 'Pago SPEI generado exitosamente. Realiza la transferencia para confirmar tu pedido.'
            ];

        } catch (MPApiException $e) {
            $this->log("Error MercadoPago API: " . $e->getMessage(), 'ERROR');
            $apiResponse = $e->getApiResponse();
            $errorContent = $apiResponse ? $apiResponse->getContent() : $e->getMessage();

            return [
                'success' => false,
                'error' => 'Error al crear pago SPEI',
                'message' => is_string($errorContent) ? $errorContent : json_encode($errorContent)
            ];
        } catch (Exception $e) {
            $this->log("Error al crear pago SPEI: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Consulta el estado de un pago SPEI
     *
     * @param int $pedidoId ID del pedido
     * @return array Estado del pago
     */
    public function getPaymentStatus($pedidoId) {
        try {
            // Buscar el pago en nuestra base de datos
            $stmt = $this->conn->prepare(
                "SELECT * FROM spei_payments WHERE pedido_id = ? ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->bind_param('i', $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'No se encontró pago SPEI para este pedido'
                ];
            }

            $speiPayment = $result->fetch_assoc();
            $stmt->close();

            // Consultar estado actual en MercadoPago
            $mpPaymentId = $speiPayment['mercadopago_payment_id'];
            $payment = $this->paymentClient->get($mpPaymentId);

            $currentStatus = $payment->status;
            $statusDetail = $payment->status_detail ?? '';

            // Actualizar estado si cambió
            if ($currentStatus !== $speiPayment['status']) {
                $this->updateSPEIPaymentStatus($speiPayment['id'], $currentStatus, $statusDetail);
                $this->log("Estado SPEI actualizado: {$mpPaymentId} -> {$currentStatus}");
            }

            return [
                'success' => true,
                'payment_id' => $mpPaymentId,
                'pedido_id' => $pedidoId,
                'amount' => $speiPayment['amount'],
                'status' => $currentStatus,
                'status_detail' => $statusDetail,
                'clabe' => $speiPayment['clabe'],
                'ticket_url' => $speiPayment['ticket_url'],
                'created_at' => $speiPayment['created_at'],
                'expires_at' => $speiPayment['expires_at'],
                'is_approved' => $currentStatus === 'approved',
                'is_pending' => in_array($currentStatus, ['pending', 'in_process']),
                'is_rejected' => in_array($currentStatus, ['rejected', 'cancelled', 'refunded'])
            ];

        } catch (Exception $e) {
            $this->log("Error al consultar estado SPEI: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Procesa webhook de MercadoPago para pagos SPEI
     *
     * @param int $paymentId ID del pago de MercadoPago
     * @return array Resultado del procesamiento
     */
    public function processWebhook($paymentId) {
        try {
            $this->log("Procesando webhook SPEI para payment_id: {$paymentId}");

            // Obtener información del pago de MercadoPago
            $payment = $this->paymentClient->get($paymentId);

            $status = $payment->status;
            $statusDetail = $payment->status_detail ?? '';
            $externalRef = $payment->external_reference ?? '';

            // Extraer pedido_id de la referencia externa
            $pedidoId = null;
            if (preg_match('/QB_SPEI_(\d+)_/', $externalRef, $matches)) {
                $pedidoId = (int)$matches[1];
            } elseif ($payment->metadata && isset($payment->metadata->pedido_id)) {
                $pedidoId = (int)$payment->metadata->pedido_id;
            }

            if (!$pedidoId) {
                $this->log("No se pudo extraer pedido_id del pago SPEI: {$paymentId}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'No se pudo identificar el pedido'
                ];
            }

            // Actualizar estado del pago SPEI
            $stmt = $this->conn->prepare(
                "UPDATE spei_payments SET status = ?, status_detail = ?, updated_at = NOW()
                 WHERE mercadopago_payment_id = ?"
            );
            $stmt->bind_param('sss', $status, $statusDetail, $paymentId);
            $stmt->execute();
            $stmt->close();

            // Determinar nuevo estado del pedido
            $nuevoEstadoPedido = null;
            switch ($status) {
                case 'approved':
                    $nuevoEstadoPedido = 2; // Confirmado/Pagado
                    break;
                case 'pending':
                case 'in_process':
                    $nuevoEstadoPedido = 1; // Pendiente de pago
                    break;
                case 'rejected':
                case 'cancelled':
                case 'refunded':
                    $nuevoEstadoPedido = 7; // Cancelado
                    break;
            }

            // Actualizar estado del pedido
            if ($nuevoEstadoPedido) {
                $stmtPedido = $this->conn->prepare(
                    "UPDATE pedidos SET
                        id_estado = ?,
                        payment_status = ?,
                        payment_status_detail = ?,
                        updated_at = NOW()
                     WHERE id_pedido = ?"
                );
                $stmtPedido->bind_param('issi', $nuevoEstadoPedido, $status, $statusDetail, $pedidoId);
                $stmtPedido->execute();
                $stmtPedido->close();
            }

            $this->log("Webhook SPEI procesado: Pedido={$pedidoId}, Status={$status}");

            return [
                'success' => true,
                'pedido_id' => $pedidoId,
                'payment_id' => $paymentId,
                'status' => $status,
                'nuevo_estado_pedido' => $nuevoEstadoPedido
            ];

        } catch (Exception $e) {
            $this->log("Error procesando webhook SPEI: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza el pedido con información del pago
     */
    private function updateOrderWithPayment($pedidoId, $paymentInfo) {
        $stmt = $this->conn->prepare(
            "UPDATE pedidos SET
                payment_id = ?,
                metodo_pago = ?,
                payment_status = ?,
                referencia_externa = ?,
                updated_at = NOW()
             WHERE id_pedido = ?"
        );

        $stmt->bind_param(
            'ssssi',
            $paymentInfo['payment_id'],
            $paymentInfo['payment_method'],
            $paymentInfo['payment_status'],
            $paymentInfo['external_reference'],
            $pedidoId
        );

        $stmt->execute();
        $stmt->close();
    }

    /**
     * Guarda el registro del pago SPEI
     */
    private function saveSPEIPaymentRecord($data) {
        // Verificar si la tabla existe, si no, crearla
        $this->ensureSPEITableExists();

        $bankInfoJson = $data['bank_info'] ? json_encode($data['bank_info']) : null;

        $stmt = $this->conn->prepare(
            "INSERT INTO spei_payments
            (pedido_id, mercadopago_payment_id, external_reference, amount, email, clabe, bank_info, ticket_url, status, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->bind_param(
            'issdssssss',
            $data['pedido_id'],
            $data['mercadopago_payment_id'],
            $data['external_reference'],
            $data['amount'],
            $data['email'],
            $data['clabe'],
            $bankInfoJson,
            $data['ticket_url'],
            $data['status'],
            $data['expires_at']
        );

        $stmt->execute();
        $stmt->close();
    }

    /**
     * Actualiza el estado del pago SPEI
     */
    private function updateSPEIPaymentStatus($id, $status, $statusDetail) {
        $stmt = $this->conn->prepare(
            "UPDATE spei_payments SET status = ?, status_detail = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('ssi', $status, $statusDetail, $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Asegura que la tabla spei_payments exista
     */
    private function ensureSPEITableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS spei_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            mercadopago_payment_id VARCHAR(50) NOT NULL,
            external_reference VARCHAR(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            email VARCHAR(255) NOT NULL,
            clabe VARCHAR(20) DEFAULT NULL,
            bank_info JSON DEFAULT NULL,
            ticket_url TEXT DEFAULT NULL,
            status VARCHAR(30) DEFAULT 'pending',
            status_detail VARCHAR(100) DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            INDEX idx_pedido (pedido_id),
            INDEX idx_mp_payment (mercadopago_payment_id),
            INDEX idx_external_ref (external_reference),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($sql);
    }

    /**
     * Obtiene la URL del webhook
     */
    private function getWebhookURL() {
        $appUrl = env('APP_URL', 'https://quickbite.com.mx');
        return rtrim($appUrl, '/') . '/webhook/mercadopago_webhook.php';
    }

    /**
     * Lista pagos SPEI pendientes (para panel admin)
     */
    public function listPendingPayments($limit = 50) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT sp.*, p.id_usuario, p.monto_total as pedido_total
                 FROM spei_payments sp
                 LEFT JOIN pedidos p ON sp.pedido_id = p.id_pedido
                 WHERE sp.status IN ('pending', 'in_process')
                 ORDER BY sp.created_at DESC
                 LIMIT ?"
            );
            $stmt->bind_param('i', $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            $stmt->close();

            return [
                'success' => true,
                'payments' => $payments,
                'count' => count($payments)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
