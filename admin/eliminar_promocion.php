<?php
/**
 * QuickBite - Eliminar Promoción
 * Elimina una promoción del negocio actual
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
require_once __DIR__ . '/../models/Promocion.php';

// Verificar que se recibió el ID de la promoción
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: negocio_configuracion.php?tab=promociones&error=ID de promoción inválido");
    exit;
}

$id_promocion = (int)$_GET['id'];
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'negocio_configuracion.php?tab=promociones';

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

    // Crear instancia de la promoción
    $promocion = new Promocion($db);

    // Verificar que la promoción existe y pertenece al negocio
    if (!$promocion->obtenerPorId($id_promocion)) {
        header("Location: " . $redirect . "&error=Promoción no encontrada");
        exit;
    }

    if ($promocion->id_negocio != $id_negocio) {
        header("Location: " . $redirect . "&error=No tienes permiso para eliminar esta promoción");
        exit;
    }

    // Eliminar la promoción
    if ($promocion->eliminar()) {
        header("Location: " . $redirect . "&mensaje=Promoción eliminada correctamente&tipo=success");
    } else {
        header("Location: " . $redirect . "&error=Error al eliminar la promoción");
    }

} catch (Exception $e) {
    error_log("Error al eliminar promoción: " . $e->getMessage());
    header("Location: " . $redirect . "&error=Error al procesar la solicitud");
}

exit;
?>
