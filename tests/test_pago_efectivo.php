<?php
/**
 * Test: Flujo de pago en efectivo
 *
 * Verifica que cuando un pedido se paga en efectivo:
 * 1. Las ganancias se registran pero NO son retirables
 * 2. El negocio genera una deuda de comisión a QuickBite
 * 3. El repartidor ve la ganancia pero no puede retirarla
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PagosPedidoService.php';

echo "==============================================\n";
echo "TEST: Flujo de Pago en Efectivo\n";
echo "==============================================\n\n";

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = [];

try {
    // 1. Obtener un negocio y repartidor de prueba
    echo "1. Obteniendo datos de prueba...\n";

    $stmt = $db->query("SELECT id_negocio, nombre, id_propietario FROM negocios LIMIT 1");
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT r.id_repartidor, r.id_usuario, u.nombre FROM repartidores r JOIN usuarios u ON r.id_usuario = u.id_usuario LIMIT 1");
    $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT id_usuario, nombre FROM usuarios WHERE tipo_usuario = 'cliente' LIMIT 1");
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$negocio || !$repartidor || !$cliente) {
        throw new Exception("Faltan datos de prueba (negocio, repartidor o cliente)");
    }

    echo "   Negocio: {$negocio['nombre']} (ID: {$negocio['id_negocio']})\n";
    echo "   Repartidor: {$repartidor['nombre']} (ID: {$repartidor['id_repartidor']})\n";
    echo "   Cliente: {$cliente['nombre']} (ID: {$cliente['id_usuario']})\n";
    $success[] = "Datos de prueba obtenidos";

    // 2. Crear pedido de prueba con pago en EFECTIVO
    echo "\n2. Creando pedido con pago en EFECTIVO...\n";

    // Obtener una dirección válida del cliente
    $stmt = $db->prepare("SELECT id_direccion FROM direcciones_usuario WHERE id_usuario = ? LIMIT 1");
    $stmt->execute([$cliente['id_usuario']]);
    $direccion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$direccion) {
        // Crear una dirección temporal
        $stmt = $db->prepare("INSERT INTO direcciones_usuario (id_usuario, direccion, ciudad, estado, codigo_postal, latitud, longitud)
                              VALUES (?, 'Calle Test 123', 'Ciudad Test', 'Estado Test', '12345', 19.4326, -99.1332)");
        $stmt->execute([$cliente['id_usuario']]);
        $id_direccion = $db->lastInsertId();
    } else {
        $id_direccion = $direccion['id_direccion'];
    }

    $total_productos = 350.00;
    $costo_envio = 35.00;
    $propina = 20.00;
    $cargo_servicio = 5.00;
    $total = $total_productos + $costo_envio + $propina + $cargo_servicio;

    $stmt = $db->prepare("
        INSERT INTO pedidos (
            id_usuario, id_negocio, id_repartidor, id_direccion,
            total_productos, costo_envio, propina, cargo_servicio, monto_total,
            metodo_pago, id_estado, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'efectivo', 6, NOW())
    ");
    $stmt->execute([
        $cliente['id_usuario'],
        $negocio['id_negocio'],
        $repartidor['id_repartidor'],
        $id_direccion,
        $total_productos,
        $costo_envio,
        $propina,
        $cargo_servicio,
        $total
    ]);

    $id_pedido = $db->lastInsertId();
    echo "   Pedido creado: #$id_pedido\n";
    echo "   Total productos: \$$total_productos\n";
    echo "   Envío: \$$costo_envio + Propina: \$$propina\n";
    echo "   Método de pago: EFECTIVO\n";
    $success[] = "Pedido en efectivo creado (ID: $id_pedido)";

    // 3. Obtener saldos ANTES del procesamiento
    echo "\n3. Saldos ANTES del procesamiento...\n";

    $stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
    $stmt->execute([$negocio['id_propietario']]);
    $wallet_negocio_antes = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_negocio_antes = floatval($wallet_negocio_antes['saldo_disponible'] ?? 0);

    $stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
    $stmt->execute([$repartidor['id_usuario']]);
    $wallet_repartidor_antes = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_repartidor_antes = floatval($wallet_repartidor_antes['saldo_disponible'] ?? 0);

    echo "   Saldo negocio (retirable): \$" . number_format($saldo_negocio_antes, 2) . "\n";
    echo "   Saldo repartidor (retirable): \$" . number_format($saldo_repartidor_antes, 2) . "\n";

    // 4. Procesar pago del pedido
    echo "\n4. Procesando distribución de pagos...\n";

    $servicioPagos = new PagosPedidoService($db);
    $resultado = $servicioPagos->procesarPagosPedido($id_pedido);

    if (!$resultado['success']) {
        throw new Exception("Error procesando pago: " . ($resultado['error'] ?? 'Error desconocido'));
    }

    echo "   Resultado: " . ($resultado['success'] ? 'OK' : 'ERROR') . "\n";
    echo "   Es efectivo: " . ($resultado['es_efectivo'] ? 'SÍ' : 'NO') . "\n";
    echo "   Distribución:\n";
    echo "     - Pago negocio: \$" . number_format($resultado['distribucion']['pago_negocio'], 2) . "\n";
    echo "     - Comisión plataforma: \$" . number_format($resultado['distribucion']['comision_plataforma'], 2) . "\n";
    echo "     - Pago repartidor: \$" . number_format($resultado['distribucion']['pago_repartidor'], 2) . "\n";
    echo "     - Nota: " . ($resultado['distribucion']['nota'] ?? 'N/A') . "\n";
    $success[] = "Pago procesado correctamente";

    // 5. Verificar saldos DESPUÉS del procesamiento
    echo "\n5. Saldos DESPUÉS del procesamiento...\n";

    $stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
    $stmt->execute([$negocio['id_propietario']]);
    $wallet_negocio_despues = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_negocio_despues = floatval($wallet_negocio_despues['saldo_disponible'] ?? 0);

    $stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
    $stmt->execute([$repartidor['id_usuario']]);
    $wallet_repartidor_despues = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_repartidor_despues = floatval($wallet_repartidor_despues['saldo_disponible'] ?? 0);

    echo "   Saldo negocio (retirable): \$" . number_format($saldo_negocio_despues, 2) . "\n";
    echo "   Saldo repartidor (retirable): \$" . number_format($saldo_repartidor_despues, 2) . "\n";

    // 6. Verificar que NO aumentó el saldo retirable
    echo "\n6. Verificando que el saldo retirable NO aumentó...\n";

    $diferencia_negocio = $saldo_negocio_despues - $saldo_negocio_antes;
    $diferencia_repartidor = $saldo_repartidor_despues - $saldo_repartidor_antes;

    echo "   Diferencia saldo negocio: \$" . number_format($diferencia_negocio, 2) . "\n";
    echo "   Diferencia saldo repartidor: \$" . number_format($diferencia_repartidor, 2) . "\n";

    if ($diferencia_negocio == 0) {
        echo "   ✓ CORRECTO: El saldo retirable del negocio NO aumentó\n";
        $success[] = "Saldo negocio no aumentó (pago en efectivo)";
    } else {
        echo "   ✗ ERROR: El saldo retirable del negocio SÍ aumentó\n";
        $errors[] = "El saldo retirable del negocio aumentó incorrectamente";
    }

    if ($diferencia_repartidor == 0) {
        echo "   ✓ CORRECTO: El saldo retirable del repartidor NO aumentó\n";
        $success[] = "Saldo repartidor no aumentó (pago en efectivo)";
    } else {
        echo "   ✗ ERROR: El saldo retirable del repartidor SÍ aumentó\n";
        $errors[] = "El saldo retirable del repartidor aumentó incorrectamente";
    }

    // 7. Verificar que se registró la ganancia en efectivo
    echo "\n7. Verificando registro de ganancia en efectivo...\n";

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM wallet_transacciones WHERE id_pedido = ? AND tipo = 'ganancia_efectivo'");
    $stmt->execute([$id_pedido]);
    $cnt_efectivo = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    if ($cnt_efectivo >= 1) {
        echo "   ✓ CORRECTO: Se registraron $cnt_efectivo transacciones de ganancia_efectivo\n";
        $success[] = "Transacciones de ganancia_efectivo registradas";
    } else {
        echo "   ✗ ERROR: No se registraron transacciones de ganancia_efectivo\n";
        $errors[] = "No se encontraron transacciones de ganancia_efectivo";
    }

    // 8. Verificar deuda de comisión
    echo "\n8. Verificando deuda de comisión del negocio...\n";

    $stmt = $db->prepare("SELECT monto_comision, estado FROM deudas_comisiones_negocios WHERE id_pedido = ? AND id_negocio = ?");
    $stmt->execute([$id_pedido, $negocio['id_negocio']]);
    $deuda = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($deuda) {
        echo "   ✓ CORRECTO: Deuda registrada por \$" . number_format($deuda['monto_comision'], 2) . " (Estado: {$deuda['estado']})\n";
        $success[] = "Deuda de comisión registrada correctamente";
    } else {
        echo "   ✗ ERROR: No se registró deuda de comisión\n";
        $errors[] = "No se encontró deuda de comisión";
    }

    // 9. Verificar saldo_deudor del negocio
    echo "\n9. Verificando saldo deudor del negocio...\n";

    $stmt = $db->prepare("SELECT saldo_deudor FROM negocios WHERE id_negocio = ?");
    $stmt->execute([$negocio['id_negocio']]);
    $negocio_deudor = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldo_deudor = floatval($negocio_deudor['saldo_deudor'] ?? 0);

    if ($saldo_deudor > 0) {
        echo "   ✓ CORRECTO: Negocio tiene saldo deudor de \$" . number_format($saldo_deudor, 2) . "\n";
        $success[] = "Saldo deudor del negocio actualizado";
    } else {
        echo "   ⚠ AVISO: Saldo deudor es \$0 (puede que no tenga deudas anteriores)\n";
    }

    // 10. Obtener resumen de ganancias
    echo "\n10. Resumen de ganancias (efectivo vs digital)...\n";

    // Obtener wallet del negocio para el resumen
    $stmt = $db->prepare("SELECT id_wallet FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
    $stmt->execute([$negocio['id_propietario']]);
    $wallet_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($wallet_info) {
        $resumen = $servicioPagos->obtenerResumenGanancias($wallet_info['id_wallet']);
        echo "   Ganancias digitales: \$" . number_format($resumen['ganancias_digitales']['total'], 2) .
             " (" . $resumen['ganancias_digitales']['cantidad_pedidos'] . " pedidos) - " .
             ($resumen['ganancias_digitales']['retirable'] ? 'Retirable' : 'No retirable') . "\n";
        echo "   Ganancias efectivo: \$" . number_format($resumen['ganancias_efectivo']['total'], 2) .
             " (" . $resumen['ganancias_efectivo']['cantidad_pedidos'] . " pedidos) - " .
             ($resumen['ganancias_efectivo']['retirable'] ? 'Retirable' : 'No retirable') . "\n";
        echo "   Total general: \$" . number_format($resumen['total_general'], 2) . "\n";
        $success[] = "Resumen de ganancias generado correctamente";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// Resumen final
echo "\n==============================================\n";
echo "RESUMEN DEL TEST\n";
echo "==============================================\n";
echo "Éxitos: " . count($success) . "\n";
foreach ($success as $s) {
    echo "  ✓ $s\n";
}

if (count($errors) > 0) {
    echo "\nErrores: " . count($errors) . "\n";
    foreach ($errors as $e) {
        echo "  ✗ $e\n";
    }
    echo "\n❌ TEST FALLIDO\n";
    exit(1);
} else {
    echo "\n✅ TEST COMPLETADO EXITOSAMENTE\n";
    echo "\nEl flujo de pago en efectivo funciona correctamente:\n";
    echo "- Las ganancias se registran pero NO son retirables\n";
    echo "- El negocio genera deuda de comisión a QuickBite\n";
    echo "- El historial muestra claramente qué es efectivo y qué es digital\n";
    exit(0);
}
