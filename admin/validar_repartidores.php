<?php
require_once '../config/database.php';
require_once '../models/Repartidor.php';

// Verificar si es administrador
session_start();
if (!isset($_SESSION['es_admin']) || !$_SESSION['es_admin']) {
    header("Location: ../login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$repartidor = new Repartidor($db);

// Procesar aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_repartidor'];
    $accion = $_POST['accion'];
    
    if ($accion === 'aprobar') {
        $repartidor->actualizarEstado($id, 1); // 1 = Aprobado
        $mensaje = "Repartidor aprobado correctamente";
    } elseif ($accion === 'rechazar') {
        $repartidor->actualizarEstado($id, 2); // 2 = Rechazado
        $mensaje = "Repartidor rechazado correctamente";
    }
}

// Obtener repartidores pendientes
$repartidores = $repartidor->obtenerPendientes();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Validar Repartidores</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        .documento { max-width: 200px; word-break: break-all; }
    </style>
</head>
<body>
    <h1>Repartidores Pendientes de Validación</h1>
    
    <?php if (isset($mensaje)): ?>
        <div class="mensaje"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Vehículo</th>
                <th>Licencia</th>
                <th>Documento</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $repartidores->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td><?php echo $row['id_repartidor']; ?></td>
                <td><?php echo $row['id_usuario']; ?></td>
                <td><?php echo ucfirst($row['tipo_vehiculo']); ?></td>
                <td><?php echo $row['numero_licencia']; ?></td>
                <td class="documento">
                    <a href="../uploads/<?php echo $row['documento_identidad']; ?>" target="_blank">
                        <?php echo $row['documento_identidad']; ?>
                    </a>
                </td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id_repartidor" value="<?php echo $row['id_repartidor']; ?>">
                        <input type="hidden" name="accion" value="aprobar">
                        <button type="submit">Aprobar</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id_repartidor" value="<?php echo $row['id_repartidor']; ?>">
                        <input type="hidden" name="accion" value="rechazar">
                        <button type="submit">Rechazar</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
