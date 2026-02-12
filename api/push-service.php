<?php
/**
 * Servicio para enviar notificaciones push
 * Usar con: php push-service.php
 */

require_once '../vendor/autoload.php';
require_once '../config/database.php';

class PushNotificationService {
    private $db;
    
    // Claves VAPID - IMPORTANTE: Cambiar por tus propias claves en producción
    private $publicKey = 'BEl62iUYgUivxIkv69yViEuiBIa6wvIKfFWqWHjWj9Dv0Q5yD5Z9v5EiXeDvnAO5D-WY5z5t5jNAm5sCfnvl2q4';
    private $privateKey = 'aXGQhLUGlKGFk4xc-POq9s8-lWyxHGnLCx6YhLXWNUY';
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Enviar notificación usando cURL (sin dependencias)
     */
    public function sendToUser($userId, $title, $body, $data = []) {
        try {
            $query = "SELECT * FROM push_subscriptions WHERE user_id = :user_id AND is_active = TRUE";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($subscriptions)) {
                return ['success' => false, 'message' => 'No hay suscripciones para este usuario'];
            }
            
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/icons/icon-192x192.png',
                'badge' => '/assets/icons/icon-72x72.png',
                'data' => array_merge($data, ['timestamp' => time()]),
                'tag' => 'quickbite-' . time()
            ]);
            
            $sent = 0;
            $failed = 0;
            
            foreach ($subscriptions as $sub) {
                $result = $this->sendPushNotification($sub, $payload);
                
                if ($result) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            
            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($subscriptions)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar notificación a todos los usuarios suscritos
     */
    public function sendToAll($title, $body, $data = []) {
        try {
            $query = "SELECT * FROM push_subscriptions WHERE is_active = TRUE";
            $stmt = $this->db->query($query);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($subscriptions)) {
                return ['success' => false, 'message' => 'No hay suscripciones activas'];
            }
            
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/assets/icons/icon-192x192.png',
                'badge' => '/assets/icons/icon-72x72.png',
                'data' => array_merge($data, ['timestamp' => time()]),
                'tag' => 'quickbite-' . time()
            ]);
            
            $sent = 0;
            $failed = 0;
            
            foreach ($subscriptions as $sub) {
                $result = $this->sendPushNotification($sub, $payload);
                
                if ($result) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            
            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($subscriptions)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar notificación usando cURL
     */
    private function sendPushNotification($subscription, $payload) {
        try {
            // Extraer dominio del endpoint
            $endpoint = $subscription['endpoint'];
            $urlParts = parse_url($endpoint);
            
            // Headers para la notificación
            $headers = [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ];
            
            // Configurar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Verificar si la notificación fue exitosa
            return ($httpCode >= 200 && $httpCode < 300);
            
        } catch (Exception $e) {
            error_log("Error enviando push notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación de pedido
     */
    public function sendOrderNotification($userId, $orderId, $status) {
        $statusMessages = [
            'confirmado' => [
                'title' => '✅ Pedido confirmado',
                'body' => 'Tu pedido #' . $orderId . ' ha sido confirmado y está siendo preparado.'
            ],
            'en_camino' => [
                'title' => '🚴 Pedido en camino',
                'body' => 'Tu pedido #' . $orderId . ' está en camino. ¡Llega pronto!'
            ],
            'entregado' => [
                'title' => '🎉 Pedido entregado',
                'body' => 'Tu pedido #' . $orderId . ' ha sido entregado. ¡Esperamos que lo disfrutes!'
            ],
            'cancelado' => [
                'title' => '❌ Pedido cancelado',
                'body' => 'Tu pedido #' . $orderId . ' ha sido cancelado. Te reembolsaremos pronto.'
            ]
        ];
        
        if (!isset($statusMessages[$status])) {
            return ['success' => false, 'error' => 'Estado de pedido no válido'];
        }
        
        $message = $statusMessages[$status];
        $data = [
            'url' => '/order-tracking.php?id=' . $orderId,
            'action' => 'view_order',
            'orderId' => $orderId
        ];
        
        return $this->sendToUser($userId, $message['title'], $message['body'], $data);
    }
    
    /**
     * Obtener estadísticas de suscripciones
     */
    public function getStats() {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN is_active = TRUE THEN 1 END) as active,
                    COUNT(CASE WHEN user_id IS NOT NULL THEN 1 END) as registered_users,
                    COUNT(CASE WHEN user_id IS NULL THEN 1 END) as anonymous_users
                FROM push_subscriptions
            ";
            
            $stmt = $this->db->query($query);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Si el script se ejecuta directamente, enviar notificación de prueba
if (php_sapi_name() === 'cli') {
    $pushService = new PushNotificationService();
    
    echo "=== QUICKBITE PUSH NOTIFICATION SERVICE ===\n\n";
    
    // Mostrar estadísticas
    $stats = $pushService->getStats();
    if (isset($stats['error'])) {
        echo "❌ Error obteniendo estadísticas: " . $stats['error'] . "\n\n";
    } else {
        echo "📊 Estadísticas:\n";
        echo "   Total suscripciones: " . ($stats['total'] ?? 0) . "\n";
        echo "   Suscripciones activas: " . ($stats['active'] ?? 0) . "\n";
        echo "   Usuarios registrados: " . ($stats['registered_users'] ?? 0) . "\n";
        echo "   Usuarios anónimos: " . ($stats['anonymous_users'] ?? 0) . "\n\n";
    }
    
    // Enviar notificación de prueba
    echo "🔔 Enviando notificación de prueba a todos los usuarios...\n";
    
    $result = $pushService->sendToAll(
        '🍕 ¡Prueba de QuickBite!',
        'Las notificaciones push están funcionando correctamente. ¡Tu comida favorita te está esperando!',
        ['url' => '/']
    );
    
    if ($result['success']) {
        echo "✅ Notificación enviada exitosamente:\n";
        echo "   Enviadas: " . $result['sent'] . "\n";
        echo "   Fallidas: " . $result['failed'] . "\n";
        echo "   Total: " . $result['total'] . "\n";
    } else {
        echo "❌ Error enviando notificación: " . ($result['message'] ?? $result['error']) . "\n";
    }
    
    echo "\n=== FIN ===\n";
}
?>