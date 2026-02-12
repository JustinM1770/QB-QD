<?php
/**
 * Cliente para usar el bot local de WhatsApp Web
 */

class WhatsAppLocalClient {
    
    private $botUrl = 'http://localhost:3031';
    
    /**
     * Verificar si el bot está conectado
     */
    public function isReady() {
        try {
            $ch = curl_init($this->botUrl . '/status');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            return $data['ready'] ?? false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Enviar mensaje simple
     */
    public function sendMessage($phone, $message) {
        return $this->request('/send', [
            'phone' => $this->formatPhone($phone),
            'message' => $message
        ]);
    }
    
    /**
     * Enviar notificación de pedido
     */
    public function sendOrderNotification($phone, $orderId, $status, $total, $customerName = 'Cliente', $negocioNombre = 'QuickBite', $idEstado = null) {
        return $this->request('/send-order', [
            'phone' => $this->formatPhone($phone),
            'order_id' => $orderId,
            'status' => $status,
            'total' => $total,
            'customer_name' => $customerName,
            'negocio_nombre' => $negocioNombre,
            'id_estado' => $idEstado
        ]);
    }
    
    /**
     * Enviar mensaje con botones
     */
    public function sendButtons($phone, $message, $buttons) {
        return $this->request('/send-buttons', [
            'phone' => $this->formatPhone($phone),
            'message' => $message,
            'buttons' => $buttons
        ]);
    }
    
    /**
     * Formatear número de teléfono
     */
    private function formatPhone($phone) {
        // Remover caracteres no numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Si ya empieza con 521, dejarlo como está
        if (substr($phone, 0, 3) === '521') {
            return $phone;
        }

        // Si empieza con 52 pero sin el 1, agregar el 1
        if (substr($phone, 0, 2) === '52' && strlen($phone) === 12) {
            return '521' . substr($phone, 2);
        }

        // Si tiene 10 dígitos, agregar código de país con 1
        if (strlen($phone) === 10) {
            return '521' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Hacer request al bot
     */
    private function request($endpoint, $data) {
        try {
            $ch = curl_init($this->botUrl . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("Error de conexión: {$error}");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP {$httpCode}: {$response}");
            }
            
            $result = json_decode($response, true);
            
            if (!$result || !isset($result['success'])) {
                throw new Exception("Respuesta inválida del bot");
            }
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
