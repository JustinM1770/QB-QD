<?php
// Habilitar error reporting para desarrollo
ini_set('display_errors', 0);
error_reporting(0);

// Configurar log de errores
ini_set('log_errors', 1);
ini_set('error_log', '../logs/stripe_webhook_errors.log');

require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Cargar variables de entorno
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../models/WalletStripe.php';

    // Configurar Stripe
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    // Obtener el payload raw y headers
    $payload = file_get_contents('php://input');
    $sig_header = null;

    // Log de todos los headers recibidos
    error_log("Headers completos recibidos: " . print_r(getallheaders(), true));
    
    // Intentar obtener la firma de diferentes formas
    foreach (getallheaders() as $name => $value) {
        error_log("Header: $name: $value");
        if (strtolower($name) === 'stripe-signature') {
            $sig_header = $value;
            error_log("🎯 Encontrada firma de Stripe: $value");
            break;
        }
    }
    
    // Intentar otros métodos si aún no se encuentra
    if (!$sig_header) {
        if (isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            error_log("Firma encontrada en HTTP_STRIPE_SIGNATURE");
        } elseif (isset($_SERVER['HTTP_X_STRIPE_SIGNATURE'])) {
            $sig_header = $_SERVER['HTTP_X_STRIPE_SIGNATURE'];
            error_log("Firma encontrada en HTTP_X_STRIPE_SIGNATURE");
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Stripe-Signature'])) {
                $sig_header = $headers['Stripe-Signature'];
                error_log("Firma encontrada en apache_request_headers");
            }
        }
    }

    // Log de debugging
    error_log("Payload recibido: " . substr($payload, 0, 100) . "...");
    error_log("Headers recibidos: " . print_r($_SERVER, true));
    
    if (empty($sig_header)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Firma no encontrada',
            'message' => 'No se encontró la firma de Stripe en los headers'
        ]);
        exit();
    }

    // Verificar firma del webhook
    $endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'];
    
    if (empty($endpoint_secret)) {
        error_log("Error: STRIPE_WEBHOOK_SECRET no está configurado");
        http_response_code(500);
        echo json_encode([
            'error' => 'Error de configuración',
            'message' => 'El secreto del webhook no está configurado'
        ]);
        exit();
    }

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            $endpoint_secret
        );
    } catch(\UnexpectedValueException $e) {
        http_response_code(400);
        error_log("Error de payload: " . $e->getMessage());
        echo json_encode(['error' => 'Payload inválido']);
        exit();
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        error_log("Error de firma: " . $e->getMessage());
        echo json_encode(['error' => 'Firma inválida']);
        exit();
    }

    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('No se pudo conectar a la base de datos');
    }

    // Inicializar wallet
    $wallet = new WalletStripe($db, $_ENV['STRIPE_SECRET_KEY']);

    // Registrar webhook en BD con manejo de errores
    $query = "INSERT INTO stripe_webhooks (stripe_event_id, event_type, payload, created_at)
              VALUES (:event_id, :event_type, :payload, NOW())
              ON DUPLICATE KEY UPDATE 
                  procesado = FALSE,
                  intentos = intentos + 1,
                  last_attempt = NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':event_id', $event->id);
    $stmt->bindParam(':event_type', $event->type);
    $stmt->bindParam(':payload', json_encode($event));
    
    if (!$stmt->execute()) {
        throw new Exception('Error al registrar el webhook en la base de datos');
    }
    
    // Procesar evento
    $wallet->procesarWebhook($event);
    
    // Marcar como procesado
    $query_update = "UPDATE stripe_webhooks 
                    SET procesado = TRUE,
                        processed_at = NOW()
                    WHERE stripe_event_id = :event_id";
    $stmt = $db->prepare($query_update);
    $stmt->bindParam(':event_id', $event->id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el estado del webhook');
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'event_id' => $event->id,
        'event_type' => $event->type
    ]);
    
} catch (Exception $e) {
    error_log("Error crítico procesando webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
    exit();
}