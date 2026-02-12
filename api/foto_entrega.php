<?php
/**
 * QuickBite - API de Foto de Entrega
 * Permite a los repartidores subir foto como prueba de entrega
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// Verificar autenticacion
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'repartidor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload':
            // Subir foto de entrega
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibio ninguna foto');
            }

            $id_pedido = (int)($_POST['id_pedido'] ?? 0);
            $latitud = $_POST['latitud'] ?? null;
            $longitud = $_POST['longitud'] ?? null;
            $notas = trim($_POST['notas'] ?? '');

            if ($id_pedido <= 0) {
                throw new Exception('ID de pedido invalido');
            }

            // Obtener ID del repartidor
            $stmt = $db->prepare("SELECT id_repartidor FROM repartidores WHERE id_usuario = ?");
            $stmt->execute([$_SESSION['id_usuario']]);
            $id_repartidor = $stmt->fetchColumn();

            if (!$id_repartidor) {
                throw new Exception('Repartidor no encontrado');
            }

            // Verificar que el pedido pertenece al repartidor
            $stmt = $db->prepare("SELECT id_pedido, id_estado FROM pedidos WHERE id_pedido = ? AND id_repartidor = ?");
            $stmt->execute([$id_pedido, $id_repartidor]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                throw new Exception('Pedido no encontrado o no asignado a este repartidor');
            }

            // Validar archivo
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $file_type = mime_content_type($_FILES['foto']['tmp_name']);

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG o WebP');
            }

            $max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['foto']['size'] > $max_size) {
                throw new Exception('La foto es muy grande. Maximo 5MB');
            }

            // Crear directorio si no existe
            $upload_dir = __DIR__ . '/../uploads/entregas/' . date('Y/m');
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generar nombre unico
            $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $filename = 'entrega_' . $id_pedido . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $filepath = $upload_dir . '/' . $filename;
            $url_path = 'uploads/entregas/' . date('Y/m') . '/' . $filename;

            // Mover archivo
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $filepath)) {
                throw new Exception('Error al guardar la foto');
            }

            // Comprimir imagen si es muy grande
            comprimirImagen($filepath, $filepath, 80);

            // Guardar en base de datos
            $stmt = $db->prepare("
                INSERT INTO fotos_entrega (id_pedido, id_repartidor, foto_url, latitud, longitud, notas, fecha_captura)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$id_pedido, $id_repartidor, $url_path, $latitud, $longitud, $notas]);

            // Actualizar pedido con la foto
            $stmt = $db->prepare("
                UPDATE pedidos
                SET foto_entrega_url = ?, foto_entrega_fecha = NOW()
                WHERE id_pedido = ?
            ");
            $stmt->execute([$url_path, $id_pedido]);

            echo json_encode([
                'success' => true,
                'message' => 'Foto de entrega guardada correctamente',
                'foto_url' => $url_path
            ]);
            break;

        case 'get':
            // Obtener foto de entrega de un pedido
            $id_pedido = (int)($_GET['id_pedido'] ?? 0);

            $stmt = $db->prepare("
                SELECT fe.*, p.id_estado
                FROM fotos_entrega fe
                JOIN pedidos p ON fe.id_pedido = p.id_pedido
                WHERE fe.id_pedido = ?
                ORDER BY fe.fecha_captura DESC
                LIMIT 1
            ");
            $stmt->execute([$id_pedido]);
            $foto = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $foto
            ]);
            break;

        default:
            throw new Exception('Accion no reconocida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Comprime una imagen manteniendo calidad aceptable
 */
function comprimirImagen($source, $destination, $quality = 80) {
    $info = getimagesize($source);

    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, round(9 * (100 - $quality) / 100));
    } elseif ($info['mime'] == 'image/webp') {
        $image = imagecreatefromwebp($source);
        imagewebp($image, $destination, $quality);
    }

    if (isset($image)) {
        imagedestroy($image);
    }
}
?>
