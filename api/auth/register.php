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
$nombre = trim($input['nombre'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$telefono = trim($input['telefono'] ?? '');

if (empty($nombre) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nombre, email y contraseña son requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email no válido']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este email ya está registrado']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, telefono, tipo_usuario) VALUES (?, ?, ?, ?, 'cliente')");
    $stmt->execute([$nombre, $email, $hashedPassword, $telefono]);
    $userId = $pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $userId;
    $_SESSION['loggedin'] = true;
    $_SESSION['tipo_usuario'] = 'cliente';

    echo json_encode([
        'success' => true,
        'message' => 'Registro exitoso',
        'usuario' => [
            'id' => (int)$userId,
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'tipo_usuario' => 'cliente',
            'foto_perfil' => null,
            'email_verificado' => false
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
