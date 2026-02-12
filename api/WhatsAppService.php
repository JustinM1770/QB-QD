<?php
/**
 * WhatsApp Cloud API Service
 * Maneja el envío de mensajes y notificaciones via WhatsApp Business API
 */

class WhatsAppService {
    
    private $phoneNumberId;
    private $accessToken;
    private $apiUrl;
    private $apiVersion;
    private $verifyToken;
    private $appSecret;
    
    /**
     * Constructor - Carga configuración de WhatsApp
     */
    public function __construct() {
        $config = require __DIR__ . '/../config/whatsapp_config.php';
        
        $this->phoneNumberId = $config['phone_number_id'];
        $this->accessToken = $config['access_token'];
        $this->apiUrl = $config['api_url'];
        $this->apiVersion = $config['api_version'];
        $this->verifyToken = $config['verify_token'];
        $this->appSecret = $config['app_secret'];
        
        // Validar configuración
        if (empty($this->phoneNumberId) || $this->phoneNumberId === 'TU_PHONE_NUMBER_ID') {
            throw new Exception('WhatsApp no está configurado correctamente. Por favor configura config/whatsapp_config.php');
        }
    }
    
    /**
     * Verificar si el servicio está configurado
     */
    public function isConfigured() {
        return !empty($this->phoneNumberId) && 
               $this->phoneNumberId !== 'TU_PHONE_NUMBER_ID' &&
               !empty($this->accessToken) &&
               $this->accessToken !== 'TU_ACCESS_TOKEN';
    }
    
    /**
     * Enviar template de nuevo pedido al restaurante
     * 
     * @param string $phoneNumber Teléfono del restaurante (formato: 521XXXXXXXXXX)
     * @param int $orderId ID del pedido
     * @param string $orderDetails Detalles del pedido
     * @param float $total Total del pedido
     * @param string $customerName Nombre del cliente
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function sendOrderTemplate($phoneNumber, $orderId, $orderDetails, $total, $customerName) {
        try {
            // Validar que el servicio esté configurado
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'WhatsApp no está configurado',
                    'error_code' => 'NOT_CONFIGURED'
                ];
            }
            
            // Formatear número de teléfono (remover caracteres no numéricos)
            $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // Validar formato de teléfono mexicano
            if (!preg_match('/^521[0-9]{10}$/', $phone)) {
                // Intentar auto-corregir
                if (strlen($phone) === 10) {
                    $phone = '521' . $phone; // Agregar código de país
                } else {
                    return [
                        'success' => false,
                        'error' => 'Formato de teléfono inválido. Debe ser 521XXXXXXXXXX',
                        'error_code' => 'INVALID_PHONE'
                    ];
                }
            }
            
            // Construir mensaje
            $message = $this->buildOrderMessage($orderId, $orderDetails, $total, $customerName);
            
            // Enviar mensaje
            $result = $this->sendMessage($phone, $message);
            
            // Registrar en base de datos
            if ($result['success']) {
                $this->logMessage($phone, $orderId, $message, $result['message_id']);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError('sendOrderTemplate', $e->getMessage(), [
                'phone' => $phoneNumber,
                'order_id' => $orderId
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }
    
    /**
     * Construir mensaje de pedido
     */
    private function buildOrderMessage($orderId, $orderDetails, $total, $customerName) {
        $mensaje = "🔔 *NUEVO PEDIDO #{$orderId}*\n\n";
        $mensaje .= "👤 Cliente: {$customerName}\n\n";
        $mensaje .= "📋 Detalles:\n{$orderDetails}\n\n";
        $mensaje .= "💰 Total: $" . number_format($total, 2) . "\n\n";
        $mensaje .= "⏰ Por favor confirma este pedido lo antes posible.\n";
        $mensaje .= "Para gestionar este pedido, ingresa a tu panel de administración.";
        
        return $mensaje;
    }
    
    /**
     * Enviar mensaje de texto simple
     * 
     * @param string $to Número de teléfono destino
     * @param string $message Mensaje a enviar
     * @return array
     */
    public function sendMessage($to, $message) {
        try {
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
            
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message
                ]
            ];
            
            $headers = [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception("cURL error: {$curlError}");
            }
            
            $result = json_decode($response, true);
            
            if ($httpCode !== 200) {
                $errorMsg = isset($result['error']['message']) 
                    ? $result['error']['message'] 
                    : 'Error desconocido al enviar mensaje';
                    
                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'error_code' => $result['error']['code'] ?? 'UNKNOWN',
                    'http_code' => $httpCode
                ];
            }
            
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['id'] ?? null,
                'response' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }
    
    /**
     * Enviar mensaje con botones interactivos
     */
    public function sendInteractiveButtons($to, $bodyText, $buttons) {
        try {
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
            
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $bodyText
                    ],
                    'action' => [
                        'buttons' => $buttons
                    ]
                ]
            ];
            
            $headers = [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode !== 200) {
                throw new Exception($result['error']['message'] ?? 'Error enviando mensaje interactivo');
            }
            
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['id'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesar webhook de WhatsApp
     */
    public function handleWebhook($payload) {
        try {
            $data = is_string($payload) ? json_decode($payload, true) : $payload;
            
            if (!isset($data['entry'][0]['changes'][0]['value'])) {
                return ['success' => false, 'error' => 'Invalid webhook payload'];
            }
            
            $value = $data['entry'][0]['changes'][0]['value'];
            
            // Procesar mensajes entrantes
            if (isset($value['messages'])) {
                foreach ($value['messages'] as $message) {
                    $this->processIncomingMessage($message);
                }
            }
            
            // Procesar cambios de estado
            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $this->processMessageStatus($status);
                }
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->logError('handleWebhook', $e->getMessage(), ['payload' => $payload]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Procesar mensaje entrante (respuesta de restaurante)
     */
    private function processIncomingMessage($message) {
        // Aquí puedes implementar lógica para procesar respuestas
        // Por ejemplo, si el restaurante responde "aceptar" o "rechazar"
        
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];
        
        // Si es un mensaje de texto
        if ($message['type'] === 'text') {
            $text = strtolower(trim($message['text']['body']));
            
            // Lógica de respuesta automática
            if (strpos($text, 'aceptar') !== false || strpos($text, 'acepto') !== false) {
                // Actualizar estado del pedido a "aceptado"
                $this->updateOrderStatus($from, 'aceptado');
            } elseif (strpos($text, 'rechazar') !== false || strpos($text, 'rechazo') !== false) {
                // Actualizar estado del pedido a "rechazado"
                $this->updateOrderStatus($from, 'rechazado');
            }
        }
        
        // Si es respuesta de botón interactivo
        if ($message['type'] === 'interactive') {
            $buttonReply = $message['interactive']['button_reply'];
            $buttonId = $buttonReply['id'];
            
            // Procesar según el ID del botón
            if ($buttonId === 'accept_order') {
                $this->updateOrderStatus($from, 'aceptado');
            } elseif ($buttonId === 'reject_order') {
                $this->updateOrderStatus($from, 'rechazado');
            }
        }
    }
    
    /**
     * Procesar estado de mensaje (entregado, leído, etc)
     */
    private function processMessageStatus($status) {
        $messageId = $status['id'];
        $statusType = $status['status']; // sent, delivered, read, failed
        
        // Actualizar estado en BD
        $this->updateMessageStatus($messageId, $statusType);
    }
    
    /**
     * Actualizar estado de pedido según respuesta
     */
    private function updateOrderStatus($phone, $status) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            // Buscar el último pedido enviado a este número
            $query = "SELECT referencia_id FROM whatsapp_messages 
                     WHERE telefono_destino = ? AND tipo_mensaje = 'nuevo_pedido' 
                     ORDER BY fecha_envio DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$phone]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $orderId = $result['referencia_id'];
                
                // Actualizar estado del pedido
                $queryUpdate = "UPDATE pedidos SET estado = ? WHERE id_pedido = ?";
                $stmtUpdate = $db->prepare($queryUpdate);
                $stmtUpdate->execute([$status, $orderId]);
                
                $this->logInfo('Order status updated', [
                    'order_id' => $orderId,
                    'status' => $status,
                    'phone' => $phone
                ]);
            }
            
        } catch (Exception $e) {
            $this->logError('updateOrderStatus', $e->getMessage(), [
                'phone' => $phone,
                'status' => $status
            ]);
        }
    }
    
    /**
     * Actualizar estado de mensaje en BD
     */
    private function updateMessageStatus($messageId, $status) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "UPDATE whatsapp_messages 
                     SET estado = ?, fecha_actualizacion = NOW() 
                     WHERE message_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$status, $messageId]);
            
        } catch (Exception $e) {
            $this->logError('updateMessageStatus', $e->getMessage(), [
                'message_id' => $messageId,
                'status' => $status
            ]);
        }
    }
    
    /**
     * Registrar mensaje enviado en BD
     */
    private function logMessage($phone, $orderId, $message, $messageId) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "INSERT INTO whatsapp_messages 
                     (message_id, telefono_destino, mensaje, tipo_mensaje, referencia_id, estado, fecha_envio)
                     VALUES (?, ?, ?, 'nuevo_pedido', ?, 'sent', NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([$messageId, $phone, $message, $orderId]);
            
        } catch (Exception $e) {
            // No lanzar excepción, solo loguear
            error_log("Error logging WhatsApp message: " . $e->getMessage());
        }
    }
    
    /**
     * Logging de errores
     */
    private function logError($method, $message, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'error' => $message,
            'context' => $context
        ];
        
        if (function_exists('logWhatsApp')) {
            logWhatsApp("ERROR in {$method}", $logData);
        } else {
            error_log("WhatsApp Error [{$method}]: {$message}");
        }
    }
    
    /**
     * Logging de información
     */
    private function logInfo($message, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context
        ];
        
        if (function_exists('logWhatsApp')) {
            logWhatsApp($message, $logData);
        }
    }
    
    /**
     * Verificar webhook (para configuración inicial)
     */
    public function verifyWebhook($mode, $token, $challenge) {
        if ($mode === 'subscribe' && $token === $this->verifyToken) {
            return $challenge;
        }
        return false;
    }
}
