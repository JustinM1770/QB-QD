<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Instalar web-push via Composer: composer require minishlink/web-push
require_once '../vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendOrderStatusNotification($user_id, $order_id, $new_status, $business_name = '') {
    
    // Obtener mensaje según el estado
    function getStatusMessage($status, $business_name, $order_id) {
        $order_number = str_pad($order_id, 6, '0', STR_PAD_LEFT);
        
        switch($status) {
            case 1: return "Tu pedido #{$order_number} ha sido recibido por {$business_name}";
            case 2: return "{$business_name} ha confirmado tu pedido #{$order_number} y está preparándolo";
            case 3: return "Tu pedido #{$order_number} se está cocinando. Ya casi está listo";
            case 4: return "Tu pedido #{$order_number} está listo y esperando al repartidor";
            case 5: return "Tu pedido #{$order_number} va en camino. El repartidor se dirige a tu domicilio";
            case 6: return "Tu pedido #{$order_number} ha sido entregado. Que lo disfrutes";
            default: return "Tu pedido #{$order_number} ha sido actualizado";
        }
    }

    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener suscripciones del usuario
    $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$subscriptions) {
        return ['success' => false, 'message' => 'Usuario no tiene suscripciones'];
    }
    
    $message = getStatusMessage($new_status, $business_name, $order_id);
    
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:contacto@quickbite.com',
            'publicKey' => 'BOeqLR7r_-fyCTb5S8G3I-AmcRz58mueyf9ncPQ2Pm12dO_7bu1-2YBnU3iLrRS7fhw1N1bin7lNAmQSxpDx6Iw',
            'privateKey' => 'UQfNfE3QmISy-gyPUrgYGZcpb3-iaqbBe2AShA01KeY'
        ]
    ];
    
    $webPush = new WebPush($auth);
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($subscriptions as $sub) {
        try {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth']
            ]);
            
            $payload = json_encode([
                'title' => 'QuickBite - Actualización de Pedido',
                'body' => $message,
                'icon' => '/assets/img/logo.png',
                'badge' => '/assets/img/badge.png',
                'data' => [
                    'url' => "/order-tracking.php?id={$order_id}",
                    'order_id' => $order_id,
                    'status' => $new_status
                ]
            ]);
            
            $result = $webPush->sendOneNotification($subscription, $payload);
            
            if ($result->isSuccess()) {
                $sent_count++;
            } else {
                $failed_count++;
                error_log("Error enviando notificación: " . $result->getReason());
                
                // Si la suscripción es inválida, eliminarla
                if (in_array($result->getStatusCode(), [410, 413, 414])) {
                    $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE id = ?");
                    $stmt->execute([$sub['id']]);
                }
            }
        } catch (Exception $e) {
            $failed_count++;
            error_log("Excepción enviando notificación: " . $e->getMessage());
        }
    }
    
    return [
        'success' => $sent_count > 0,
        'sent' => $sent_count,
        'failed' => $failed_count,
        'message' => $message
    ];
}

// Endpoint para enviar notificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $data['user_id'] ?? null;
    $order_id = $data['order_id'] ?? null;
    $new_status = $data['new_status'] ?? null;
    $business_name = $data['business_name'] ?? 'el restaurante';
    
    if (!$user_id || !$order_id || !$new_status) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos']);
        exit;
    }
    
    $result = sendOrderStatusNotification($user_id, $order_id, $new_status, $business_name);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?>