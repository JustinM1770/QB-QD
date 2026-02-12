<?php
/**
 * API para manejar suscripciones de notificaciones push
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

session_start();

// Incluir configuración de base de datos
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Registrar nueva suscripción
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Datos de suscripción inválidos');
        }
        
        $endpoint = $input['endpoint'] ?? '';
        $p256dh = $input['keys']['p256dh'] ?? '';
        $auth = $input['keys']['auth'] ?? '';
        $user_id = $_SESSION['id_usuario'] ?? null;
        
        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            throw new Exception('Datos de suscripción incompletos');
        }
        
        // Verificar si ya existe esta suscripción
        $checkQuery = "SELECT id FROM push_subscriptions WHERE endpoint = :endpoint";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':endpoint', $endpoint);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => true,
                'message' => 'Suscripción ya existe'
            ]);
            exit;
        }
        
        // Crear tabla si no existe
        $createTableQuery = "
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                endpoint TEXT NOT NULL,
                p256dh_key VARCHAR(255) NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $db->exec($createTableQuery);
        
        // Insertar nueva suscripción
        $insertQuery = "
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key) 
            VALUES (:user_id, :endpoint, :p256dh, :auth)
        ";
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':user_id', $user_id);
        $insertStmt->bindParam(':endpoint', $endpoint);
        $insertStmt->bindParam(':p256dh', $p256dh);
        $insertStmt->bindParam(':auth', $auth);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Suscripción registrada correctamente'
            ]);
        } else {
            throw new Exception('Error al registrar suscripción');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obtener suscripciones (solo para admin)
        if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
            throw new Exception('No autorizado');
        }
        
        $query = "
            SELECT ps.*, u.nombre_usuario 
            FROM push_subscriptions ps 
            LEFT JOIN usuarios u ON ps.user_id = u.id_usuario 
            WHERE ps.is_active = TRUE 
            ORDER BY ps.created_at DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'subscriptions' => $subscriptions,
            'total' => count($subscriptions)
        ]);
        
    } else {
        throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>