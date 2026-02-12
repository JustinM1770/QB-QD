<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Endpoint de diagnóstico funcionando',
    'session_active' => isset($_SESSION['loggedin']),
    'user_type' => $_SESSION['tipo_usuario'] ?? 'no_definido',
    'user_id' => $_SESSION['id_usuario'] ?? 'no_definido',
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>