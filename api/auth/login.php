<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();
require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y contraseña son requeridos']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, email, telefono, password, tipo_usuario, foto_perfil, email_verificado FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email o contraseña incorrectos']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $user['id'];
    $_SESSION['loggedin'] = true;
    $_SESSION['tipo_usuario'] = $user['tipo_usuario'];

    unset($user['password']);
    $user['email_verificado'] = (bool)$user['email_verificado'];

    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'usuario' => $user
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
