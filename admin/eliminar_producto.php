<?php
/**
 * QuickBite - Eliminar Producto
 * Elimina un producto del negocio actual
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION["loggedin"]) || $_SESSION["tipo_usuario"] !== "negocio") {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Producto.php';

// Verificar que se recibió el ID del producto
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: negocio_configuracion.php?tab=menu&error=ID de producto inválido");
    exit;
}

$id_producto = (int)$_GET['id'];
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'negocio_configuracion.php?tab=menu';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Obtener información del negocio del usuario
    $usuario = new Usuario($db);
    $usuario->id_usuario = $_SESSION["id_usuario"];
    $usuario->obtenerPorId();

    $negocio = new Negocio($db);
    $negocios = $negocio->obtenerPorIdPropietario($usuario->id_usuario);

    if (empty($negocios)) {
        header("Location: negocio_configuracion.php?mensaje=No tienes un negocio registrado&tipo=error");
        exit;
    }

    $negocio_info = $negocios[0];
    $id_negocio = $negocio_info['id_negocio'];

    // Crear instancia del producto
    $producto = new Producto($db);
    $producto->id_producto = $id_producto;
    $producto->id_negocio = $id_negocio;

    // Verificar que el producto pertenece al negocio
    $producto_info = $producto->obtenerPorId($id_producto);

    if (!$producto_info || $producto_info['id_negocio'] != $id_negocio) {
        header("Location: " . $redirect . "&error=No tienes permiso para eliminar este producto");
        exit;
    }

    // Eliminar el producto
    if ($producto->eliminar()) {
        // Eliminar imagen si existe
        if (!empty($producto_info['imagen'])) {
            $ruta_imagen = __DIR__ . '/../' . $producto_info['imagen'];
            if (file_exists($ruta_imagen)) {
                unlink($ruta_imagen);
            }
        }

        header("Location: " . $redirect . "&mensaje=Producto eliminado correctamente&tipo=success");
    } else {
        header("Location: " . $redirect . "&error=Error al eliminar el producto");
    }

} catch (Exception $e) {
    error_log("Error al eliminar producto: " . $e->getMessage());
    header("Location: " . $redirect . "&error=Error al procesar la solicitud");
}

exit;
?>
