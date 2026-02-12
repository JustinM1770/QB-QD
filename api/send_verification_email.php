<?php
session_start();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../config/database.php';
require_once '../config/env.php';
require_once '../models/Usuario.php';

// Load PHPMailer classes
require '../vendor/autoload.php';

// SMTP configuration - Cargada desde variables de entorno (SEGURO)
$smtpHost = env('SMTP_HOST', 'smtp.hostinger.com');
$smtpUsername = env('SMTP_USER', 'contacto@quickbite.com.mx');
$smtpPassword = env('SMTP_PASS', '');  // ✅ SEGURO: Cargado desde .env
$smtpPort = (int) env('SMTP_PORT', 587);
$smtpSecure = 'tls';

if (!isset($_POST['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
    exit;
}

$id_usuario = (int)$_POST['id_usuario'];

$database = new Database();
$db = $database->getConnection();

$usuario = new Usuario($db);
$usuario->id_usuario = $id_usuario;

if (!$usuario->obtenerPorId()) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

if (empty($usuario->verification_code)) {
    echo json_encode(['success' => false, 'message' => 'Código de verificación no disponible']);
    exit;
}

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port       = $smtpPort;

    //Recipients
    $mail->setFrom($smtpUsername, 'QuickBite');
    $mail->addAddress($usuario->email, $usuario->nombre);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Código de verificación de correo electrónico';
    $mail->Body    = "<p>Hola " . htmlspecialchars($usuario->nombre) . ",</p>
                      <p>Tu código de verificación es: <strong>" . $usuario->verification_code . "</strong></p>
                      <p>Por favor ingresa este código para verificar tu correo electrónico.</p>
                      <p>Gracias,<br>QuickBite</p>";
    $mail->AltBody = "Hola " . $usuario->nombre . ",\n\n" .
                     "Tu código de verificación es: " . $usuario->verification_code . "\n\n" .
                     "Por favor ingresa este código para verificar tu correo electrónico.\n\n" .
                     "Gracias,\nQuickBite";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Correo de verificación enviado']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Error al enviar el correo: {$mail->ErrorInfo}"]);
}
?>
