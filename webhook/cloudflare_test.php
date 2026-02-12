<?php
// Test de webhook con logging detallado
header('Content-Type: application/json');

$log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

file_put_contents('../logs/webhook_test.log', json_encode($log, JSON_PRETTY_PRINT) . "\n", FILE_APPEND | LOCK_EX);

echo json_encode([
    'status' => 'success',
    'message' => 'Webhook accessible',
    'timestamp' => $log['timestamp']
]);
?>