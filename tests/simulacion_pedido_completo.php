<?php
/**
 * SIMULACION COMPLETA DE PEDIDO - QUICKBITE
 *
 * Este script simula el flujo completo de un pedido:
 * 1. Crear pedido
 * 2. Procesar pago
 * 3. Completar entrega
 * 4. Actualizar saldos de negocio y repartidor
 * 5. Verificar funcionalidad de retiro
 *
 * Ejecutar: php tests/simulacion_pedido_completo.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/quickbite_fees.php';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║        SIMULACION COMPLETA DE PEDIDO - QUICKBITE                     ║\n";
echo "║        Flujo: Pedido -> Pago -> Entrega -> Saldos -> Retiro          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Conexion a BD
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexion a base de datos exitosa\n\n";
} catch (PDOException $e) {
    die("❌ Error de conexion: " . $e->getMessage() . "\n");
}

$errores = [];
$resultados = [];

// ============================================================
// PASO 1: PREPARAR DATOS
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 1: PREPARANDO DATOS PARA LA SIMULACION\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// Obtener negocio con productos
$stmt = $pdo->query("
    SELECT n.* FROM negocios n
    INNER JOIN productos p ON n.id_negocio = p.id_negocio
    WHERE n.activo = 1 AND p.disponible = 1 AND p.precio > 0
    GROUP BY n.id_negocio
    HAVING COUNT(p.id_producto) > 0
    LIMIT 1
");
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    die("❌ ERROR: No hay negocios activos con productos disponibles\n");
}

echo "📍 Negocio seleccionado: {$negocio['nombre']} (ID: {$negocio['id_negocio']})\n";
echo "   Premium: " . ($negocio['es_premium'] ? 'SI' : 'NO') . "\n";
echo "   Costo envio base: $" . number_format(floatval($negocio['costo_envio'] ?? 25), 2) . "\n";

// Obtener repartidor activo
$stmt = $pdo->query("
    SELECT r.*, u.nombre as nombre_repartidor, u.email
    FROM repartidores r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.activo = 1
    LIMIT 1
");
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repartidor) {
    die("❌ ERROR: No hay repartidores activos\n");
}

echo "🚴 Repartidor seleccionado: {$repartidor['nombre_repartidor']} (ID: {$repartidor['id_repartidor']})\n";

// Obtener usuario cliente
$stmt = $pdo->query("
    SELECT * FROM usuarios
    WHERE activo = 1 AND tipo_usuario = 'cliente' OR tipo_usuario IS NULL
    LIMIT 1
");
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    // Si no hay cliente, usar cualquier usuario
    $stmt = $pdo->query("SELECT * FROM usuarios WHERE activo = 1 LIMIT 1");
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
}

echo "👤 Cliente: {$cliente['nombre']} (ID: {$cliente['id_usuario']})\n";

// Obtener o crear dirección del cliente
$stmt = $pdo->prepare("SELECT * FROM direcciones_usuario WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$cliente['id_usuario']]);
$direccion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$direccion) {
    echo "⚠️ Cliente sin dirección, creando dirección de prueba...\n";
    $stmt = $pdo->prepare("
        INSERT INTO direcciones_usuario (id_usuario, nombre_direccion, calle, numero, colonia, ciudad, codigo_postal, latitud, longitud, activa)
        VALUES (?, 'Casa', 'Calle Prueba', '123', 'Centro', 'Ciudad', '45000', 20.6597, -103.3496, 1)
    ");
    $stmt->execute([$cliente['id_usuario']]);
    $id_direccion = $pdo->lastInsertId();
    echo "✅ Dirección creada (ID: $id_direccion)\n";
} else {
    $id_direccion = $direccion['id_direccion'];
    echo "📍 Dirección: {$direccion['calle']} #{$direccion['numero']}, {$direccion['colonia']}\n";
}

// Obtener productos
$stmt = $pdo->prepare("
    SELECT * FROM productos
    WHERE id_negocio = ? AND disponible = 1 AND precio > 0
    LIMIT 3
");
$stmt->execute([$negocio['id_negocio']]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($productos)) {
    die("❌ ERROR: No hay productos disponibles en el negocio\n");
}

echo "📦 Productos disponibles: " . count($productos) . "\n\n";

// ============================================================
// PASO 2: VERIFICAR Y CREAR WALLETS
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 2: VERIFICANDO/CREANDO WALLETS\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// Verificar/crear wallet del negocio
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
$stmt->execute([$negocio['id_negocio']]);
$wallet_negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet_negocio) {
    echo "⚠️ Wallet del negocio no existe, creando...\n";
    $cuenta_externa = 'LOCAL_NEG_' . $negocio['id_negocio'] . '_' . time();
    $stmt = $pdo->prepare("
        INSERT INTO wallets (id_usuario, tipo_usuario, cuenta_externa_id, saldo_disponible, saldo_pendiente, estado, fecha_creacion)
        VALUES (?, 'business', ?, 0.00, 0.00, 'activo', NOW())
    ");
    $stmt->execute([$negocio['id_negocio'], $cuenta_externa]);
    $wallet_negocio_id = $pdo->lastInsertId();
    $wallet_negocio = ['id_wallet' => $wallet_negocio_id, 'saldo_disponible' => 0, 'saldo_pendiente' => 0];
    echo "✅ Wallet del negocio creado (ID: $wallet_negocio_id)\n";
} else {
    echo "✅ Wallet del negocio existe (ID: {$wallet_negocio['id_wallet']})\n";
    echo "   Saldo disponible: $" . number_format(floatval($wallet_negocio['saldo_disponible']), 2) . "\n";
}

$saldo_inicial_negocio = floatval($wallet_negocio['saldo_disponible']);

// Verificar/crear wallet del repartidor
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
$stmt->execute([$repartidor['id_usuario']]);
$wallet_repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wallet_repartidor) {
    echo "⚠️ Wallet del repartidor no existe, creando...\n";
    $cuenta_externa = 'LOCAL_REP_' . $repartidor['id_usuario'] . '_' . time();
    $stmt = $pdo->prepare("
        INSERT INTO wallets (id_usuario, tipo_usuario, cuenta_externa_id, saldo_disponible, saldo_pendiente, estado, fecha_creacion)
        VALUES (?, 'courier', ?, 0.00, 0.00, 'activo', NOW())
    ");
    $stmt->execute([$repartidor['id_usuario'], $cuenta_externa]);
    $wallet_repartidor_id = $pdo->lastInsertId();
    $wallet_repartidor = ['id_wallet' => $wallet_repartidor_id, 'saldo_disponible' => 0, 'saldo_pendiente' => 0];
    echo "✅ Wallet del repartidor creado (ID: $wallet_repartidor_id)\n";
} else {
    echo "✅ Wallet del repartidor existe (ID: {$wallet_repartidor['id_wallet']})\n";
    echo "   Saldo disponible: $" . number_format(floatval($wallet_repartidor['saldo_disponible']), 2) . "\n";
}

$saldo_inicial_repartidor = floatval($wallet_repartidor['saldo_disponible']);
echo "\n";

// ============================================================
// PASO 3: CALCULAR PEDIDO
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 3: CALCULANDO PEDIDO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$items_pedido = [];
$subtotal_productos = 0;

foreach ($productos as $producto) {
    $cantidad = rand(1, 2);
    $precio = floatval($producto['precio']);
    $subtotal = $precio * $cantidad;
    $items_pedido[] = [
        'id_producto' => $producto['id_producto'],
        'nombre' => $producto['nombre'],
        'precio_unitario' => $precio,
        'cantidad' => $cantidad,
        'subtotal' => $subtotal
    ];
    $subtotal_productos += $subtotal;
}

// Configuracion de comisiones
$distancia_km = 3; // Simulamos 3km
$propina = round($subtotal_productos * 0.15, 2); // 15% propina
$es_premium = (bool)($negocio['es_premium'] ?? false);
$es_miembro_club = false; // Cliente normal

// Calcular distribucion usando las funciones de quickbite_fees.php
$distribucion = calcularDistribucionPedido($subtotal_productos, $distancia_km, $propina, $es_miembro_club, $es_premium);

echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│                      DETALLE DEL PEDIDO                            │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";

foreach ($items_pedido as $item) {
    printf("│  %-30s  x%d  $%8.2f  = $%8.2f │\n",
        substr($item['nombre'], 0, 30),
        $item['cantidad'],
        $item['precio_unitario'],
        $item['subtotal']
    );
}

echo "├─────────────────────────────────────────────────────────────────────┤\n";
printf("│  Subtotal productos:                              $%12.2f │\n", $distribucion['cliente']['productos']);
printf("│  Costo de envio (%dkm):                            $%12.2f │\n", $distancia_km, $distribucion['cliente']['envio']);
printf("│  Cargo de servicio:                               $%12.2f │\n", $distribucion['cliente']['cargo_servicio']);
printf("│  Propina:                                         $%12.2f │\n", $distribucion['cliente']['propina']);
echo "├─────────────────────────────────────────────────────────────────────┤\n";
printf("│  TOTAL A PAGAR:                                   $%12.2f │\n", $distribucion['cliente']['total']);
echo "└─────────────────────────────────────────────────────────────────────┘\n\n";

// ============================================================
// PASO 4: CREAR PEDIDO EN BASE DE DATOS
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 4: CREANDO PEDIDO EN BASE DE DATOS\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();

    // Insertar pedido
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            id_usuario, id_negocio, id_repartidor, id_estado, id_direccion,
            total_productos, costo_envio, cargo_servicio, propina, monto_total,
            comision_plataforma, comision_porcentaje, pago_negocio, pago_repartidor,
            metodo_pago, payment_status, fecha_creacion, tipo_pedido
        ) VALUES (
            ?, ?, ?, 1, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            'simulacion', 'pending', NOW(), 'delivery'
        )
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

    // Insertar detalles del pedido
    foreach ($items_pedido as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO detalles_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_pedido,
            $item['id_producto'],
            $item['cantidad'],
            $item['precio_unitario'],
            $item['subtotal']
        ]);
    }

    $pdo->commit();

    echo "✅ Pedido creado exitosamente (ID: $id_pedido)\n";
    $resultados['pedido_creado'] = true;

} catch (Exception $e) {
    $pdo->rollBack();
    $errores[] = "Error creando pedido: " . $e->getMessage();
    echo "❌ Error creando pedido: " . $e->getMessage() . "\n";
    $resultados['pedido_creado'] = false;
}

echo "\n";

// ============================================================
// PASO 5: SIMULAR PAGO EXITOSO
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 5: SIMULANDO PAGO EXITOSO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    // Actualizar estado de pago
    $payment_id = 'SIM_' . time() . '_' . $id_pedido;

    $stmt = $pdo->prepare("
        UPDATE pedidos SET
            payment_status = 'approved',
            payment_id = ?,
            id_estado = 2,
            fecha_actualizacion = NOW()
        WHERE id_pedido = ?
    ");
    $stmt->execute([$payment_id, $id_pedido]);

    echo "✅ Pago procesado exitosamente\n";
    echo "   Payment ID: $payment_id\n";
    echo "   Estado: approved\n";
    $resultados['pago_procesado'] = true;

} catch (Exception $e) {
    $errores[] = "Error procesando pago: " . $e->getMessage();
    echo "❌ Error: " . $e->getMessage() . "\n";
    $resultados['pago_procesado'] = false;
}

echo "\n";

// ============================================================
// PASO 6: SIMULAR ENTREGA
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 6: SIMULANDO ENTREGA DEL PEDIDO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    // Simular flujo de estados
    $estados = [
        3 => 'en_preparacion',
        4 => 'listo_para_recoger',
        5 => 'en_camino',
        6 => 'entregado'
    ];

    foreach ($estados as $id_estado => $nombre) {
        $stmt = $pdo->prepare("UPDATE pedidos SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?");
        $stmt->execute([$id_estado, $id_pedido]);
        echo "   ➤ Estado actualizado: $nombre\n";
    }

    // Marcar fecha de entrega
    $stmt = $pdo->prepare("UPDATE pedidos SET fecha_entrega = NOW() WHERE id_pedido = ?");
    $stmt->execute([$id_pedido]);

    echo "✅ Pedido entregado exitosamente\n";
    $resultados['entrega_completada'] = true;

} catch (Exception $e) {
    $errores[] = "Error en entrega: " . $e->getMessage();
    echo "❌ Error: " . $e->getMessage() . "\n";
    $resultados['entrega_completada'] = false;
}

echo "\n";

// ============================================================
// PASO 7: ACTUALIZAR SALDOS DE NEGOCIO Y REPARTIDOR
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 7: ACTUALIZANDO SALDOS DE NEGOCIO Y REPARTIDOR\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

try {
    $pdo->beginTransaction();

    // Actualizar saldo del negocio
    $pago_negocio = $distribucion['negocio']['recibe'];

    $stmt = $pdo->prepare("
        UPDATE wallets
        SET saldo_disponible = saldo_disponible + ?,
            fecha_actualizacion = NOW()
        WHERE id_wallet = ?
    ");
    $stmt->execute([$pago_negocio, $wallet_negocio['id_wallet']]);

    // Registrar transaccion del negocio
    $stmt = $pdo->prepare("
        INSERT INTO wallet_transacciones (id_wallet, tipo, monto, descripcion, estado, id_pedido, fecha)
        VALUES (?, 'ingreso', ?, ?, 'completado', ?, NOW())
    ");
    $stmt->execute([
        $wallet_negocio['id_wallet'],
        $pago_negocio,
        "Venta pedido #$id_pedido",
        $id_pedido
    ]);

    echo "✅ Saldo del negocio actualizado: +$" . number_format($pago_negocio, 2) . "\n";

    // Actualizar saldo del repartidor
    $pago_repartidor = $distribucion['repartidor']['total'];

    $stmt = $pdo->prepare("
        UPDATE wallets
        SET saldo_disponible = saldo_disponible + ?,
            fecha_actualizacion = NOW()
        WHERE id_wallet = ?
    ");
    $stmt->execute([$pago_repartidor, $wallet_repartidor['id_wallet']]);

    // Registrar transaccion del repartidor
    $stmt = $pdo->prepare("
        INSERT INTO wallet_transacciones (id_wallet, tipo, monto, descripcion, estado, id_pedido, fecha)
        VALUES (?, 'ingreso', ?, ?, 'completado', ?, NOW())
    ");
    $stmt->execute([
        $wallet_repartidor['id_wallet'],
        $pago_repartidor,
        "Entrega pedido #$id_pedido (envio + propina)",
        $id_pedido
    ]);

    echo "✅ Saldo del repartidor actualizado: +$" . number_format($pago_repartidor, 2) . "\n";

    // Registrar en ganancias_repartidor
    $stmt = $pdo->prepare("
        INSERT INTO ganancias_repartidor (id_repartidor, id_pedido, ganancia, fecha_ganancia)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$repartidor['id_repartidor'], $id_pedido, $pago_repartidor]);

    // Actualizar total_ganancias del repartidor
    $stmt = $pdo->prepare("
        UPDATE repartidores SET total_ganancias = COALESCE(total_ganancias, 0) + ?, total_entregas = COALESCE(total_entregas, 0) + 1
        WHERE id_repartidor = ?
    ");
    $stmt->execute([$pago_repartidor, $repartidor['id_repartidor']]);

    $pdo->commit();

    $resultados['saldos_actualizados'] = true;

} catch (Exception $e) {
    $pdo->rollBack();
    $errores[] = "Error actualizando saldos: " . $e->getMessage();
    echo "❌ Error: " . $e->getMessage() . "\n";
    $resultados['saldos_actualizados'] = false;
}

echo "\n";

// ============================================================
// PASO 8: VERIFICAR SALDOS ACTUALIZADOS
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 8: VERIFICANDO SALDOS ACTUALIZADOS\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// Verificar saldo del negocio
$stmt = $pdo->prepare("SELECT saldo_disponible, saldo_pendiente FROM wallets WHERE id_wallet = ?");
$stmt->execute([$wallet_negocio['id_wallet']]);
$nuevo_saldo_negocio = $stmt->fetch(PDO::FETCH_ASSOC);

$saldo_actual_negocio = floatval($nuevo_saldo_negocio['saldo_disponible']);
$incremento_negocio = $saldo_actual_negocio - $saldo_inicial_negocio;

echo "🏪 NEGOCIO: {$negocio['nombre']}\n";
echo "   Saldo inicial:  $" . number_format($saldo_inicial_negocio, 2) . "\n";
echo "   Saldo actual:   $" . number_format($saldo_actual_negocio, 2) . "\n";
echo "   Incremento:     $" . number_format($incremento_negocio, 2) . "\n";

if (abs($incremento_negocio - $distribucion['negocio']['recibe']) < 0.01) {
    echo "   ✅ Correcto - coincide con el pago calculado\n";
} else {
    echo "   ❌ Error - diferencia detectada\n";
    $errores[] = "Diferencia en saldo del negocio";
}

// Verificar saldo del repartidor
$stmt = $pdo->prepare("SELECT saldo_disponible, saldo_pendiente FROM wallets WHERE id_wallet = ?");
$stmt->execute([$wallet_repartidor['id_wallet']]);
$nuevo_saldo_repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

$saldo_actual_repartidor = floatval($nuevo_saldo_repartidor['saldo_disponible']);
$incremento_repartidor = $saldo_actual_repartidor - $saldo_inicial_repartidor;

echo "\n🚴 REPARTIDOR: {$repartidor['nombre_repartidor']}\n";
echo "   Saldo inicial:  $" . number_format($saldo_inicial_repartidor, 2) . "\n";
echo "   Saldo actual:   $" . number_format($saldo_actual_repartidor, 2) . "\n";
echo "   Incremento:     $" . number_format($incremento_repartidor, 2) . "\n";

if (abs($incremento_repartidor - $distribucion['repartidor']['total']) < 0.01) {
    echo "   ✅ Correcto - coincide con el pago calculado\n";
} else {
    echo "   ❌ Error - diferencia detectada\n";
    $errores[] = "Diferencia en saldo del repartidor";
}

echo "\n";

// ============================================================
// PASO 9: SIMULAR SOLICITUD DE RETIRO
// ============================================================
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "  PASO 9: SIMULANDO SOLICITUD DE RETIRO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$monto_retiro_negocio = min(100, $saldo_actual_negocio); // Minimo $100 o saldo disponible
$monto_retiro_repartidor = min(100, $saldo_actual_repartidor);

try {
    // Solo simular retiro si hay saldo suficiente
    if ($saldo_actual_negocio >= 100) {
        echo "🏪 Simulando retiro del negocio: $" . number_format($monto_retiro_negocio, 2) . "\n";

        $pdo->beginTransaction();

        // Crear solicitud de retiro
        $stmt = $pdo->prepare("
            INSERT INTO wallet_retiros (id_wallet, monto, estado, fecha_solicitud)
            VALUES (?, ?, 'procesando', NOW())
        ");
        $stmt->execute([$wallet_negocio['id_wallet'], $monto_retiro_negocio]);

        // Mover de disponible a pendiente
        $stmt = $pdo->prepare("
            UPDATE wallets SET
                saldo_disponible = saldo_disponible - ?,
                saldo_pendiente = saldo_pendiente + ?
            WHERE id_wallet = ?
        ");
        $stmt->execute([$monto_retiro_negocio, $monto_retiro_negocio, $wallet_negocio['id_wallet']]);

        // Registrar transaccion
        $stmt = $pdo->prepare("
            INSERT INTO wallet_transacciones (id_wallet, tipo, monto, descripcion, estado, fecha)
            VALUES (?, 'retiro', ?, 'Solicitud de retiro', 'pendiente', NOW())
        ");
        $stmt->execute([$wallet_negocio['id_wallet'], -$monto_retiro_negocio]);

        $pdo->commit();

        echo "   ✅ Solicitud de retiro creada exitosamente\n";
        $resultados['retiro_negocio'] = true;
    } else {
        echo "⚠️ Negocio: Saldo insuficiente para retiro (minimo $100)\n";
        $resultados['retiro_negocio'] = 'SALDO_INSUFICIENTE';
    }

    if ($saldo_actual_repartidor >= 100) {
        echo "\n🚴 Simulando retiro del repartidor: $" . number_format($monto_retiro_repartidor, 2) . "\n";

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO wallet_retiros (id_wallet, monto, estado, fecha_solicitud)
            VALUES (?, ?, 'procesando', NOW())
        ");
        $stmt->execute([$wallet_repartidor['id_wallet'], $monto_retiro_repartidor]);

        $stmt = $pdo->prepare("
            UPDATE wallets SET
                saldo_disponible = saldo_disponible - ?,
                saldo_pendiente = saldo_pendiente + ?
            WHERE id_wallet = ?
        ");
        $stmt->execute([$monto_retiro_repartidor, $monto_retiro_repartidor, $wallet_repartidor['id_wallet']]);

        $stmt = $pdo->prepare("
            INSERT INTO wallet_transacciones (id_wallet, tipo, monto, descripcion, estado, fecha)
            VALUES (?, 'retiro', ?, 'Solicitud de retiro', 'pendiente', NOW())
        ");
        $stmt->execute([$wallet_repartidor['id_wallet'], -$monto_retiro_repartidor]);

        $pdo->commit();

        echo "   ✅ Solicitud de retiro creada exitosamente\n";
        $resultados['retiro_repartidor'] = true;
    } else {
        echo "\n⚠️ Repartidor: Saldo insuficiente para retiro (minimo $100)\n";
        $resultados['retiro_repartidor'] = 'SALDO_INSUFICIENTE';
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errores[] = "Error en retiro: " . $e->getMessage();
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================
// RESUMEN FINAL
// ============================================================
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                      RESUMEN DE SIMULACION                          ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";

echo "║                                                                      ║\n";
echo "║  📋 DISTRIBUCION DEL PEDIDO #$id_pedido                               \n";
echo "║  ├─ Cliente pago:        $" . str_pad(number_format($distribucion['cliente']['total'], 2), 12, ' ', STR_PAD_LEFT) . "                      ║\n";
echo "║  ├─ Negocio recibe:      $" . str_pad(number_format($distribucion['negocio']['recibe'], 2), 12, ' ', STR_PAD_LEFT) . " ({$distribucion['negocio']['comision_porcentaje']}% comision)       ║\n";
echo "║  ├─ Repartidor recibe:   $" . str_pad(number_format($distribucion['repartidor']['total'], 2), 12, ' ', STR_PAD_LEFT) . " (envio + propina)       ║\n";
echo "║  └─ QuickBite recibe:    $" . str_pad(number_format($distribucion['quickbite']['ganancia_neta'], 2), 12, ' ', STR_PAD_LEFT) . "                      ║\n";
echo "║                                                                      ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";
echo "║  RESULTADOS DE PRUEBAS:                                              ║\n";

$all_ok = true;
foreach ($resultados as $key => $value) {
    $status = ($value === true || $value === 'OK') ? '✅' : (($value === 'SALDO_INSUFICIENTE') ? '⚠️' : '❌');
    if ($value === false) $all_ok = false;

    $key_formatted = str_pad(ucfirst(str_replace('_', ' ', $key)), 25);
    $value_str = is_bool($value) ? ($value ? 'OK' : 'FALLO') : $value;
    echo "║    $status $key_formatted $value_str\n";
}

echo "╠══════════════════════════════════════════════════════════════════════╣\n";

if ($all_ok && empty($errores)) {
    echo "║                                                                      ║\n";
    echo "║   ✅✅✅ SIMULACION EXITOSA - TODOS LOS FLUJOS FUNCIONAN ✅✅✅     ║\n";
    echo "║                                                                      ║\n";
} else {
    echo "║                                                                      ║\n";
    echo "║   ⚠️ HAY ERRORES QUE REVISAR:                                        ║\n";
    foreach ($errores as $error) {
        echo "║     - $error\n";
    }
    echo "║                                                                      ║\n";
}

echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Mostrar verificacion final de wallets
echo "VERIFICACION FINAL DE SALDOS:\n";
echo "┌────────────────────┬──────────────────┬──────────────────┐\n";
echo "│ Wallet             │ Saldo Disponible │ Saldo Pendiente  │\n";
echo "├────────────────────┼──────────────────┼──────────────────┤\n";

$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_wallet = ?");
$stmt->execute([$wallet_negocio['id_wallet']]);
$w = $stmt->fetch(PDO::FETCH_ASSOC);
printf("│ %-18s │ $%15s │ $%15s │\n", "Negocio", number_format(floatval($w['saldo_disponible']), 2), number_format(floatval($w['saldo_pendiente']), 2));

$stmt->execute([$wallet_repartidor['id_wallet']]);
$w = $stmt->fetch(PDO::FETCH_ASSOC);
printf("│ %-18s │ $%15s │ $%15s │\n", "Repartidor", number_format(floatval($w['saldo_disponible']), 2), number_format(floatval($w['saldo_pendiente']), 2));

echo "└────────────────────┴──────────────────┴──────────────────┘\n\n";

echo "Simulacion completada: " . date('Y-m-d H:i:s') . "\n\n";
