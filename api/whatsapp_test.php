<?php
/**
 * Endpoint de prueba para WhatsApp Business API
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/WhatsAppLocalClient.php';
require_once __DIR__ . '/../config/database.php';

try {
    // GET - Check status
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'status') {
        $whatsapp = new WhatsAppLocalClient();
        $isReady = $whatsapp->isReady();
        
        echo json_encode([
            'success' => true,
            'configured' => $isReady,
            'phone_number_id' => $isReady ? 'Bot Local (whatsapp-web.js)' : 'No conectado',
            'access_token' => $isReady ? 'Sesión activa' : 'No disponible',
            'provider' => 'WhatsApp Web (Bot Local)',
            'status' => $isReady ? 'ready' : 'not_ready'
        ]);
        exit;
    }
    
    // POST - Send messages
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['action'])) {
            throw new Exception('Acción no especificada');
        }
        
        $whatsapp = new WhatsAppLocalClient();
        
        switch ($data['action']) {
            case 'send_simple':
                if (!isset($data['phone']) || !isset($data['message'])) {
                    throw new Exception('Faltan parámetros: phone, message');
                }
                
                $result = $whatsapp->sendMessage($data['phone'], $data['message']);
                
                echo json_encode($result);
                break;
                
            case 'send_order':
                if (!isset($data['phone']) || !isset($data['order_id'])) {
                    throw new Exception('Faltan parámetros: phone, order_id');
                }
                
                $result = $whatsapp->sendOrderNotification(
                    $data['phone'],
                    $data['order_id'],
                    $data['status'] ?? 'confirmado',
                    $data['total'] ?? 0,
                    $data['customer_name'] ?? 'Cliente'
                );
                
                echo json_encode($result);
                break;
                
            case 'send_interactive':
                if (!isset($data['phone']) || !isset($data['message']) || !isset($data['buttons'])) {
                    throw new Exception('Faltan parámetros: phone, message, buttons');
                }
                
                $result = $whatsapp->sendButtons(
                    $data['phone'],
                    $data['message'],
                    $data['buttons']
                );
                
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Acción no válida: ' . $data['action']);
        }
        
    } else {
        throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
