<?php
/**
 * Test del Flujo Real de Pedido
 * Prueba el carrito -> checkout -> confirmación -> wallets
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/quickbite_fees.php';
require_once __DIR__ . '/../services/PagosPedidoService.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST DE FLUJO REAL DE PEDIDO - QUICKBITE                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$database = new Database();
$pdo = $database->getConnection();

$errores = [];
$exitos = [];

// =============================================
// 1. VERIFICAR DATOS DE PRUEBA
// =============================================
echo "═══ 1. VERIFICANDO DATOS DE PRUEBA ═══\n";

// Negocio
$stmt = $pdo->query("
    SELECT n.*, COUNT(p.id_producto) as productos
    FROM negocios n
    LEFT JOIN productos p ON n.id_negocio = p.id_negocio AND p.disponible = 1 AND p.precio > 0
    WHERE n.activo = 1
    GROUP BY n.id_negocio
    HAVING productos > 0
    LIMIT 1
");
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    die("ERROR: No hay negocios con productos activos\n");
}
echo "✅ Negocio: {$negocio['nombre']} (ID: {$negocio['id_negocio']}) - {$negocio['productos']} productos\n";

// Repartidor
$stmt = $pdo->query("
    SELECT r.*, u.nombre
    FROM repartidores r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.activo = 1
    LIMIT 1
");
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repartidor) {
    die("ERROR: No hay repartidores activos\n");
}
echo "✅ Repartidor: {$repartidor['nombre']} (ID: {$repartidor['id_repartidor']})\n";

// Cliente
$stmt = $pdo->query("SELECT * FROM usuarios WHERE activo = 1 LIMIT 1");
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
echo "✅ Cliente: {$cliente['nombre']} (ID: {$cliente['id_usuario']})\n";

// Dirección
$stmt = $pdo->prepare("SELECT * FROM direcciones_usuario WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$cliente['id_usuario']]);
$direccion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$direccion) {
    $stmt = $pdo->prepare("
        INSERT INTO direcciones_usuario (id_usuario, nombre_direccion, calle, numero, colonia, ciudad, estado, codigo_postal, latitud, longitud, es_predeterminada)
        VALUES (?, 'Casa Test', 'Av. Prueba', '100', 'Centro', 'Ciudad', 'Jalisco', '45000', 20.6597, -103.3496, 1)
    ");
    $stmt->execute([$cliente['id_usuario']]);
    $id_direccion = $pdo->lastInsertId();
    echo "✅ Dirección creada (ID: $id_direccion)\n";
} else {
    $id_direccion = $direccion['id_direccion'];
    echo "✅ Dirección existente (ID: $id_direccion)\n";
}

echo "\n";

// =============================================
// 2. VERIFICAR WALLETS
// =============================================
echo "═══ 2. VERIFICANDO WALLETS ═══\n";

// Wallet negocio
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
$stmt->execute([$negocio['id_negocio']]);
$wallet_negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet_negocio) {
    $cuenta = 'TEST_NEG_' . $negocio['id_negocio'] . '_' . time();
    $stmt = $pdo->prepare("
        INSERT INTO wallets (id_usuario, tipo_usuario, cuenta_externa_id, saldo_disponible, saldo_pendiente, estado, fecha_creacion)
        VALUES (?, 'business', ?, 0, 0, 'activo', NOW())
    ");
    $stmt->execute([$negocio['id_negocio'], $cuenta]);
    $wallet_negocio_id = $pdo->lastInsertId();
    $saldo_inicial_negocio = 0;
    echo "✅ Wallet negocio creada (ID: $wallet_negocio_id)\n";
} else {
    $wallet_negocio_id = $wallet_negocio['id_wallet'];
    $saldo_inicial_negocio = floatval($wallet_negocio['saldo_disponible']);
    echo "✅ Wallet negocio existente (ID: $wallet_negocio_id) - Saldo: \${$saldo_inicial_negocio}\n";
}

// Wallet repartidor
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
$stmt->execute([$repartidor['id_usuario']]);
$wallet_repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet_repartidor) {
    $cuenta = 'TEST_REP_' . $repartidor['id_usuario'] . '_' . time();
    $stmt = $pdo->prepare("
        INSERT INTO wallets (id_usuario, tipo_usuario, cuenta_externa_id, saldo_disponible, saldo_pendiente, estado, fecha_creacion)
        VALUES (?, 'courier', ?, 0, 0, 'activo', NOW())
    ");
    $stmt->execute([$repartidor['id_usuario'], $cuenta]);
    $wallet_repartidor_id = $pdo->lastInsertId();
    $saldo_inicial_repartidor = 0;
    echo "✅ Wallet repartidor creada (ID: $wallet_repartidor_id)\n";
} else {
    $wallet_repartidor_id = $wallet_repartidor['id_wallet'];
    $saldo_inicial_repartidor = floatval($wallet_repartidor['saldo_disponible']);
    echo "✅ Wallet repartidor existente (ID: $wallet_repartidor_id) - Saldo: \${$saldo_inicial_repartidor}\n";
}

echo "\n";

// =============================================
// 3. SIMULAR CARRITO Y CREAR PEDIDO
// =============================================
echo "═══ 3. SIMULANDO CARRITO Y CREANDO PEDIDO ═══\n";

// Obtener productos
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id_negocio = ? AND disponible = 1 AND precio > 0 LIMIT 2");
$stmt->execute([$negocio['id_negocio']]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = [];
$subtotal = 0;
foreach ($productos as $p) {
    $cantidad = rand(1, 2);
    $items[] = [
        'id_producto' => $p['id_producto'],
        'nombre' => $p['nombre'],
        'precio' => floatval($p['precio']),
        'cantidad' => $cantidad,
        'subtotal' => floatval($p['precio']) * $cantidad
    ];
    $subtotal += floatval($p['precio']) * $cantidad;
    echo "   + {$p['nombre']} x$cantidad = \$" . number_format(floatval($p['precio']) * $cantidad, 2) . "\n";
}

// Calcular distribución
$propina = round($subtotal * 0.10, 2);
$distribucion = calcularDistribucionPedido($subtotal, 3, $propina, false, (bool)$negocio['es_premium']);

echo "\n";
echo "┌────────────────────────────────────────────┐\n";
printf("│ Subtotal:          \$%20.2f │\n", $distribucion['cliente']['productos']);
printf("│ Envío (3km):       \$%20.2f │\n", $distribucion['cliente']['envio']);
printf("│ Cargo servicio:    \$%20.2f │\n", $distribucion['cliente']['cargo_servicio']);
printf("│ Propina:           \$%20.2f │\n", $distribucion['cliente']['propina']);
echo "├────────────────────────────────────────────┤\n";
printf("│ TOTAL:             \$%20.2f │\n", $distribucion['cliente']['total']);
echo "└────────────────────────────────────────────┘\n";
echo "\n";

// Crear pedido
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            id_usuario, id_negocio, id_repartidor, id_direccion, id_estado,
            total_productos, costo_envio, cargo_servicio, propina, monto_total,
            comision_plataforma, comision_porcentaje, pago_negocio, pago_repartidor,
            metodo_pago, payment_status, tipo_pedido, fecha_creacion
        ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'test', 'approved', 'delivery', NOW())
    ");
    $stmt->execute([
        $cliente['id_usuario'],
        $negocio['id_negocio'],
        $repartidor['id_repartidor'],
        $id_direccion,
        $distribucion['cliente']['productos'],
        $distribucion['cliente']['envio'],
        $distribucion['cliente']['cargo_servicio'],
        $distribucion['cliente']['propina'],
        $distribucion['cliente']['total'],
        $distribucion['negocio']['comision_monto'],
        $distribucion['negocio']['comision_porcentaje'],
        $distribucion['negocio']['recibe'],
        $distribucion['repartidor']['total']
    ]);

    $id_pedido = $pdo->lastInsertId();

    // Insertar detalles
    foreach ($items as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO detalles_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $item['precio'], $item['subtotal']]);
    }

    $pdo->commit();
    echo "✅ Pedido creado (ID: $id_pedido)\n";
    $exitos[] = "Pedido creado";

} catch (Exception $e) {
    $pdo->rollBack();
    $errores[] = "Error creando pedido: " . $e->getMessage();
    die("ERROR: " . $e->getMessage() . "\n");
}

echo "\n";

// =============================================
// 4. SIMULAR FLUJO DE ESTADOS
// =============================================
echo "═══ 4. SIMULANDO FLUJO DE ESTADOS ═══\n";

$estados = [
    2 => 'confirmado',
    3 => 'en_preparacion',
    4 => 'listo_para_recoger',
    5 => 'en_camino',
    6 => 'entregado'
];

foreach ($estados as $id_estado => $nombre) {
    $stmt = $pdo->prepare("UPDATE pedidos SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?");
    $stmt->execute([$id_estado, $id_pedido]);
    echo "   → Estado: $nombre (ID: $id_estado)\n";
}

// Actualizar fecha de entrega
$stmt = $pdo->prepare("UPDATE pedidos SET fecha_entrega = NOW() WHERE id_pedido = ?");
$stmt->execute([$id_pedido]);

echo "✅ Pedido marcado como entregado\n";
$exitos[] = "Flujo de estados completado";

echo "\n";

// =============================================
// 5. PROCESAR DISTRIBUCIÓN DE PAGOS
// =============================================
echo "═══ 5. PROCESANDO DISTRIBUCIÓN DE PAGOS ═══\n";

$servicio = new PagosPedidoService($pdo);
$resultado = $servicio->procesarPagosPedido($id_pedido);

if ($resultado['success']) {
    echo "✅ Pagos procesados correctamente\n";
    echo "   → Negocio recibe: \$" . number_format($resultado['distribucion']['pago_negocio'], 2) . "\n";
    echo "   → Repartidor recibe: \$" . number_format($resultado['distribucion']['pago_repartidor'], 2) . "\n";
    echo "   → QuickBite recibe: \$" . number_format($resultado['distribucion']['ganancia_quickbite'], 2) . "\n";
    $exitos[] = "Pagos procesados";
} else {
    echo "❌ Error procesando pagos: " . $resultado['error'] . "\n";
    $errores[] = "Error en pagos: " . $resultado['error'];
}

echo "\n";

// =============================================
// 6. VERIFICAR SALDOS ACTUALIZADOS
// =============================================
echo "═══ 6. VERIFICANDO SALDOS ACTUALIZADOS ═══\n";

// Wallet negocio
$stmt = $pdo->prepare("SELECT saldo_disponible, saldo_pendiente FROM wallets WHERE id_wallet = ?");
$stmt->execute([$wallet_negocio_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
$saldo_final_negocio = floatval($wallet['saldo_disponible']);
$incremento_negocio = $saldo_final_negocio - $saldo_inicial_negocio;

echo "🏪 NEGOCIO:\n";
echo "   Saldo inicial:  \$" . number_format($saldo_inicial_negocio, 2) . "\n";
echo "   Saldo final:    \$" . number_format($saldo_final_negocio, 2) . "\n";
echo "   Incremento:     \$" . number_format($incremento_negocio, 2) . "\n";

if (abs($incremento_negocio - $distribucion['negocio']['recibe']) < 0.01) {
    echo "   ✅ CORRECTO\n";
    $exitos[] = "Saldo negocio actualizado";
} else {
    echo "   ❌ DIFERENCIA DETECTADA\n";
    $errores[] = "Diferencia en saldo negocio";
}

// Wallet repartidor
$stmt->execute([$wallet_repartidor_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
$saldo_final_repartidor = floatval($wallet['saldo_disponible']);
$incremento_repartidor = $saldo_final_repartidor - $saldo_inicial_repartidor;

echo "\n🚴 REPARTIDOR:\n";
echo "   Saldo inicial:  \$" . number_format($saldo_inicial_repartidor, 2) . "\n";
echo "   Saldo final:    \$" . number_format($saldo_final_repartidor, 2) . "\n";
echo "   Incremento:     \$" . number_format($incremento_repartidor, 2) . "\n";

if (abs($incremento_repartidor - $distribucion['repartidor']['total']) < 0.01) {
    echo "   ✅ CORRECTO\n";
    $exitos[] = "Saldo repartidor actualizado";
} else {
    echo "   ❌ DIFERENCIA DETECTADA\n";
    $errores[] = "Diferencia en saldo repartidor";
}

echo "\n";

// =============================================
// 7. VERIFICAR TRANSACCIONES
// =============================================
echo "═══ 7. VERIFICANDO TRANSACCIONES ═══\n";

$stmt = $pdo->prepare("SELECT * FROM wallet_transacciones WHERE id_pedido = ? ORDER BY id_wallet");
$stmt->execute([$id_pedido]);
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Transacciones del pedido #$id_pedido:\n";
foreach ($transacciones as $t) {
    $tipo = $t['id_wallet'] == $wallet_negocio_id ? 'Negocio' : 'Repartidor';
    echo "   - $tipo: \$" . number_format($t['monto'], 2) . " ({$t['tipo']}) - {$t['descripcion']}\n";
}

if (count($transacciones) >= 2) {
    echo "✅ Transacciones registradas correctamente\n";
    $exitos[] = "Transacciones registradas";
} else {
    $errores[] = "Faltan transacciones";
}

echo "\n";

// =============================================
// RESUMEN
// =============================================
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                        RESUMEN DEL TEST                            ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";

foreach ($exitos as $exito) {
    echo "║  ✅ $exito\n";
}

if (!empty($errores)) {
    echo "║                                                                    ║\n";
    echo "║  ERRORES:                                                          ║\n";
    foreach ($errores as $error) {
        echo "║  ❌ $error\n";
    }
}

echo "╠════════════════════════════════════════════════════════════════════╣\n";

if (empty($errores)) {
    echo "║                                                                    ║\n";
    echo "║   ✅✅✅ TODOS LOS TESTS PASARON CORRECTAMENTE ✅✅✅            ║\n";
    echo "║                                                                    ║\n";
} else {
    echo "║                                                                    ║\n";
    echo "║   ⚠️  HAY " . count($errores) . " ERRORES QUE REVISAR                                   ║\n";
    echo "║                                                                    ║\n";
}

echo "╚════════════════════════════════════════════════════════════════════╝\n";

echo "\n";
echo "SALDOS FINALES EN WALLETS:\n";
echo "┌──────────────────┬──────────────────┬──────────────────┐\n";
echo "│ Wallet           │ Disponible       │ Pendiente        │\n";
echo "├──────────────────┼──────────────────┼──────────────────┤\n";
printf("│ Negocio          │ \$%14.2f │ \$%14.2f │\n", $saldo_final_negocio, floatval($wallet['saldo_pendiente'] ?? 0));

$stmt->execute([$wallet_repartidor_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
printf("│ Repartidor       │ \$%14.2f │ \$%14.2f │\n", floatval($wallet['saldo_disponible']), floatval($wallet['saldo_pendiente'] ?? 0));
echo "└──────────────────┴──────────────────┴──────────────────┘\n\n";
