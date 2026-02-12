<?php
/**
 * Simulacion de Flujo de Pedido y Distribucion de Pagos
 * QuickBite - Test de integracion de pagos
 *
 * Ejecutar: php tests/payment_flow_simulation.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/env.php';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     SIMULACION DE FLUJO DE PEDIDO Y PAGOS - QUICKBITE       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Conexion a BD
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME') . ";charset=utf8mb4",
        env('DB_USER'),
        env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage() . "\n");
}

$simulation_results = [];
$errors = [];

// ============================================================
// CONFIGURACION DE COMISIONES (ajustar segun tu modelo de negocio)
// ============================================================
$CONFIG = [
    'comision_plataforma_porcentaje' => 15,    // 15% para QuickBite
    'comision_repartidor_base' => 25,          // $25 MXN base por entrega
    'comision_repartidor_por_km' => 5,         // $5 MXN por km adicional
    'iva_porcentaje' => 16,                    // IVA Mexico
    'propina_repartidor_porcentaje' => 100,    // 100% de propina va al repartidor
];

echo "═══ CONFIGURACION DE COMISIONES ═══\n";
echo "Comision plataforma: {$CONFIG['comision_plataforma_porcentaje']}%\n";
echo "Pago base repartidor: \${$CONFIG['comision_repartidor_base']} MXN\n";
echo "Pago por km adicional: \${$CONFIG['comision_repartidor_por_km']} MXN/km\n";
echo "IVA: {$CONFIG['iva_porcentaje']}%\n\n";

// ============================================================
// PASO 1: OBTENER DATOS PARA SIMULACION
// ============================================================
echo "═══ PASO 1: PREPARANDO DATOS DE SIMULACION ═══\n";

// Obtener un negocio activo CON productos
$stmt = $pdo->query("
    SELECT n.* FROM negocios n
    INNER JOIN productos p ON n.id_negocio = p.id_negocio
    WHERE n.activo = 1 AND p.disponible = 1
    GROUP BY n.id_negocio
    HAVING COUNT(p.id_producto) > 0
    LIMIT 1
");
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    die("ERROR: No hay negocios activos para simular\n");
}

echo "Negocio: {$negocio['nombre']} (ID: {$negocio['id_negocio']})\n";
echo "Costo envio del negocio: \${$negocio['costo_envio']}\n";

// Obtener un repartidor disponible (con nombre desde usuarios)
$stmt = $pdo->query("
    SELECT r.*, u.nombre as nombre_repartidor
    FROM repartidores r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE r.activo = 1 AND r.disponible = 1
    LIMIT 1
");
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repartidor) {
    echo "[WARN] No hay repartidores disponibles, simulando sin repartidor\n";
    $repartidor = ['id_repartidor' => null, 'nombre_repartidor' => 'Sin asignar'];
} else {
    echo "Repartidor: {$repartidor['nombre_repartidor']} (ID: {$repartidor['id_repartidor']})\n";
}

// Obtener un usuario
$stmt = $pdo->query("SELECT * FROM usuarios WHERE activo = 1 LIMIT 1");
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("ERROR: No hay usuarios activos para simular\n");
}
echo "Cliente: {$usuario['nombre']} (ID: {$usuario['id_usuario']})\n";

// Obtener productos del negocio
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id_negocio = ? AND disponible = 1 LIMIT 3");
$stmt->execute([$negocio['id_negocio']]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($productos)) {
    die("ERROR: No hay productos disponibles en el negocio\n");
}

echo "Productos disponibles: " . count($productos) . "\n\n";

// ============================================================
// PASO 2: CREAR PEDIDO DE SIMULACION
// ============================================================
echo "═══ PASO 2: SIMULANDO PEDIDO ═══\n";

// Calcular totales del pedido
$items_pedido = [];
$subtotal_productos = 0;

foreach ($productos as $producto) {
    $cantidad = rand(1, 2);
    $subtotal = $producto['precio'] * $cantidad;
    $items_pedido[] = [
        'producto' => $producto['nombre'],
        'precio_unitario' => $producto['precio'],
        'cantidad' => $cantidad,
        'subtotal' => $subtotal
    ];
    $subtotal_productos += $subtotal;
}

// Mostrar items
echo "\n┌─────────────────────────────────────────────────────┐\n";
echo "│                   DETALLE DEL PEDIDO                │\n";
echo "├─────────────────────────────────────────────────────┤\n";

foreach ($items_pedido as $item) {
    $nombre = str_pad(substr($item['producto'], 0, 25), 25);
    $precio = str_pad('$' . number_format($item['precio_unitario'], 2), 10, ' ', STR_PAD_LEFT);
    $cant = str_pad('x' . $item['cantidad'], 4);
    $sub = str_pad('$' . number_format($item['subtotal'], 2), 10, ' ', STR_PAD_LEFT);
    echo "│ $nombre $precio $cant $sub │\n";
}

echo "├─────────────────────────────────────────────────────┤\n";

// Calcular costos adicionales
$costo_envio = floatval($negocio['costo_envio'] ?? 25.00);
$propina = round($subtotal_productos * 0.10, 2); // 10% propina simulada
$cargo_servicio = round($subtotal_productos * ($CONFIG['comision_plataforma_porcentaje'] / 100), 2);

// Calcular total
$monto_total = $subtotal_productos + $costo_envio + $propina;

printf("│ %-35s %15s │\n", "Subtotal productos:", '$' . number_format($subtotal_productos, 2));
printf("│ %-35s %15s │\n", "Costo de envio:", '$' . number_format($costo_envio, 2));
printf("│ %-35s %15s │\n", "Propina:", '$' . number_format($propina, 2));
echo "├─────────────────────────────────────────────────────┤\n";
printf("│ %-35s %15s │\n", "TOTAL A PAGAR:", '$' . number_format($monto_total, 2));
echo "└─────────────────────────────────────────────────────┘\n\n";

// ============================================================
// PASO 3: SIMULACION DE DISTRIBUCION DE PAGOS
// ============================================================
echo "═══ PASO 3: DISTRIBUCION DEL PAGO ═══\n\n";

// Calcular distribucion
// MODELO DE NEGOCIO QUICKBITE (Municipios Jalisco):
// - Cliente paga: subtotal_productos + costo_envio + propina
// - Negocio recibe: subtotal_productos - comision_plataforma (sobre productos)
// - Repartidor recibe: MINIMO $25 MXN garantizado + propina
// - Si costo_envio < $25, la plataforma cubre la diferencia
// - Plataforma recibe: comision sobre productos - subsidio repartidor (si aplica)

$PAGO_MINIMO_REPARTIDOR = 25.00; // Minimo garantizado para municipios

$comision_plataforma_bruta = round($subtotal_productos * ($CONFIG['comision_plataforma_porcentaje'] / 100), 2);
$pago_negocio = $subtotal_productos - $comision_plataforma_bruta;

// Calcular pago del repartidor
$pago_repartidor_propina = $propina;

// Si el costo de envio es menor al minimo, la plataforma subsidia
if ($costo_envio < $PAGO_MINIMO_REPARTIDOR) {
    $pago_repartidor_envio = $PAGO_MINIMO_REPARTIDOR;
    $subsidio_plataforma = $PAGO_MINIMO_REPARTIDOR - $costo_envio;
} else {
    $pago_repartidor_envio = $costo_envio;
    $subsidio_plataforma = 0;
}

$pago_repartidor_total = $pago_repartidor_envio + $pago_repartidor_propina;

// La comision real de la plataforma es la bruta menos el subsidio
$comision_plataforma = $comision_plataforma_bruta - $subsidio_plataforma;

echo "┌─────────────────────────────────────────────────────┐\n";
echo "│              DISTRIBUCION DEL DINERO                │\n";
echo "├─────────────────────────────────────────────────────┤\n";

// Para el negocio
echo "│                                                     │\n";
echo "│  🏪 NEGOCIO: {$negocio['nombre']}\n";
echo "│  ├─ Venta de productos:        \$" . str_pad(number_format($subtotal_productos, 2), 10, ' ', STR_PAD_LEFT) . "\n";
echo "│  ├─ Comision plataforma (-{$CONFIG['comision_plataforma_porcentaje']}%): \$" . str_pad('-' . number_format($comision_plataforma, 2), 9, ' ', STR_PAD_LEFT) . "\n";
echo "│  └─ RECIBE:                    \$" . str_pad(number_format($pago_negocio, 2), 10, ' ', STR_PAD_LEFT) . "\n";

echo "│                                                     │\n";

// Para el repartidor
if ($repartidor['id_repartidor']) {
    echo "│  🚴 REPARTIDOR: {$repartidor['nombre_repartidor']}\n";
    echo "│  ├─ Pago minimo garantizado:   \$" . str_pad(number_format($PAGO_MINIMO_REPARTIDOR, 2), 10, ' ', STR_PAD_LEFT) . "\n";
    if ($subsidio_plataforma > 0) {
        echo "│  │  (Costo envio: \${$costo_envio} + Subsidio: \$$subsidio_plataforma)\n";
    }
    echo "│  ├─ Propina (100%):            \$" . str_pad(number_format($pago_repartidor_propina, 2), 10, ' ', STR_PAD_LEFT) . "\n";
    echo "│  └─ RECIBE:                    \$" . str_pad(number_format($pago_repartidor_total, 2), 10, ' ', STR_PAD_LEFT) . "\n";
} else {
    echo "│  🚴 REPARTIDOR: Sin asignar (pendiente)           │\n";
    $pago_repartidor_total = 0;
    $subsidio_plataforma = 0;
}

echo "│                                                     │\n";

// Para la plataforma
echo "│  💼 QUICKBITE (Plataforma)\n";
echo "│  ├─ Comision productos ({$CONFIG['comision_plataforma_porcentaje']}%):  \$" . str_pad(number_format($comision_plataforma_bruta, 2), 10, ' ', STR_PAD_LEFT) . "\n";
if ($subsidio_plataforma > 0) {
    echo "│  ├─ Subsidio repartidor:       \$" . str_pad('-' . number_format($subsidio_plataforma, 2), 10, ' ', STR_PAD_LEFT) . "\n";
}
echo "│  └─ RECIBE:                    \$" . str_pad(number_format($comision_plataforma, 2), 10, ' ', STR_PAD_LEFT) . "\n";

echo "│                                                     │\n";
echo "├─────────────────────────────────────────────────────┤\n";

// Verificacion
$total_distribuido = $pago_negocio + $pago_repartidor_total + $comision_plataforma;
$diferencia = abs($monto_total - $total_distribuido);

printf("│  Total cobrado al cliente:     \$%10s        │\n", number_format($monto_total, 2));
printf("│  Total distribuido:            \$%10s        │\n", number_format($total_distribuido, 2));

if ($diferencia < 0.01) {
    echo "│  ✅ BALANCE CORRECTO                               │\n";
    $simulation_results['balance'] = 'OK';
} else {
    echo "│  ❌ DIFERENCIA: \$" . number_format($diferencia, 2) . " (REVISAR)         │\n";
    $simulation_results['balance'] = 'ERROR';
    $errors[] = "Diferencia en balance: $diferencia";
}

echo "└─────────────────────────────────────────────────────┘\n\n";

// ============================================================
// PASO 4: VERIFICAR SISTEMA DE WALLETS
// ============================================================
echo "═══ PASO 4: VERIFICACION DE WALLETS ═══\n\n";

// Verificar wallet del negocio
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
$stmt->execute([$negocio['id_negocio']]);
$wallet_negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if ($wallet_negocio) {
    echo "✅ Wallet del negocio existe\n";
    echo "   Saldo disponible: \$" . number_format($wallet_negocio['saldo_disponible'], 2) . "\n";
    echo "   Saldo pendiente:  \$" . number_format($wallet_negocio['saldo_pendiente'], 2) . "\n";
    echo "   Estado: {$wallet_negocio['estado']}\n";
    $simulation_results['wallet_negocio'] = 'OK';
} else {
    echo "⚠️ El negocio NO tiene wallet configurado\n";
    echo "   Se crearia automaticamente al recibir primer pago\n";
    $simulation_results['wallet_negocio'] = 'PENDIENTE';
}

echo "\n";

// Verificar wallet del repartidor
if ($repartidor['id_repartidor']) {
    $stmt = $pdo->prepare("SELECT * FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
    $stmt->execute([$repartidor['id_repartidor']]);
    $wallet_repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($wallet_repartidor) {
        echo "✅ Wallet del repartidor existe\n";
        echo "   Saldo disponible: \$" . number_format($wallet_repartidor['saldo_disponible'], 2) . "\n";
        echo "   Saldo pendiente:  \$" . number_format($wallet_repartidor['saldo_pendiente'], 2) . "\n";
        echo "   Estado: {$wallet_repartidor['estado']}\n";
        $simulation_results['wallet_repartidor'] = 'OK';
    } else {
        echo "⚠️ El repartidor NO tiene wallet configurado\n";
        echo "   Se crearia automaticamente al completar primera entrega\n";
        $simulation_results['wallet_repartidor'] = 'PENDIENTE';
    }
}

echo "\n";

// ============================================================
// PASO 5: VERIFICAR HISTORIAL DE TRANSACCIONES
// ============================================================
echo "═══ PASO 5: HISTORIAL DE TRANSACCIONES ═══\n\n";

$stmt = $pdo->query("SELECT * FROM wallet_transacciones ORDER BY fecha DESC LIMIT 5");
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($transacciones) > 0) {
    echo "Ultimas " . count($transacciones) . " transacciones:\n";
    echo "┌────────┬─────────────────┬────────────┬─────────────────────┐\n";
    echo "│ ID     │ Tipo            │ Monto      │ Fecha               │\n";
    echo "├────────┼─────────────────┼────────────┼─────────────────────┤\n";

    foreach ($transacciones as $tx) {
        printf("│ %-6s │ %-15s │ \$%8s │ %s │\n",
            $tx['id_transaccion'],
            substr($tx['tipo'], 0, 15),
            number_format($tx['monto'], 2),
            substr($tx['fecha'], 0, 19)
        );
    }
    echo "└────────┴─────────────────┴────────────┴─────────────────────┘\n";
    $simulation_results['transacciones'] = 'OK';
} else {
    echo "⚠️ No hay transacciones registradas aun\n";
    $simulation_results['transacciones'] = 'VACIO';
}

echo "\n";

// ============================================================
// PASO 6: VERIFICAR ESTADOS DE PEDIDO
// ============================================================
echo "═══ PASO 6: FLUJO DE ESTADOS DE PEDIDO ═══\n\n";

$stmt = $pdo->query("SELECT * FROM estados_pedido ORDER BY id_estado");
$estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Flujo de estados configurado:\n";
$estado_icons = [
    'pendiente' => '🟡',
    'confirmado' => '🟢',
    'en_preparacion' => '👨‍🍳',
    'listo_para_recoger' => '📦',
    'en_camino' => '🚴',
    'entregado' => '✅',
    'cancelado' => '❌',
    'abandonado' => '⚠️',
    'reasignado' => '🔄',
    'sin_repartidor' => '❓'
];

foreach ($estados as $estado) {
    $icon = $estado_icons[strtolower($estado['nombre'])] ?? '⚪';
    echo "  $icon {$estado['id_estado']}. {$estado['nombre']}\n";
}

echo "\n";

// ============================================================
// PASO 7: SIMULACION DE PAGO CON STRIPE
// ============================================================
echo "═══ PASO 7: VERIFICACION INTEGRACION STRIPE ═══\n\n";

$stripe_key = env('STRIPE_SECRET_KEY');
if ($stripe_key && strpos($stripe_key, 'sk_live_') === 0) {
    echo "✅ Stripe configurado en modo PRODUCCION\n";
    echo "   Public Key: " . substr(env('STRIPE_PUBLIC_KEY'), 0, 20) . "...\n";
    echo "   Webhook configurado: " . (env('STRIPE_WEBHOOK_SECRET') ? 'Si' : 'No') . "\n";
    $simulation_results['stripe'] = 'PRODUCCION';
} elseif ($stripe_key && strpos($stripe_key, 'sk_test_') === 0) {
    echo "⚠️ Stripe configurado en modo TEST\n";
    $simulation_results['stripe'] = 'TEST';
} else {
    echo "❌ Stripe NO configurado\n";
    $simulation_results['stripe'] = 'NO_CONFIG';
    $errors[] = 'Stripe no configurado';
}

echo "\n";

// ============================================================
// PASO 8: VERIFICACION MERCADOPAGO
// ============================================================
echo "═══ PASO 8: VERIFICACION INTEGRACION MERCADOPAGO ═══\n\n";

$mp_token = env('MP_ACCESS_TOKEN');
if ($mp_token && strpos($mp_token, 'APP_USR') === 0) {
    echo "✅ MercadoPago configurado\n";
    echo "   App ID: " . env('MP_APP_ID') . "\n";
    echo "   Webhook configurado: " . (env('MP_WEBHOOK_SECRET') ? 'Si' : 'No') . "\n";
    $simulation_results['mercadopago'] = 'OK';
} else {
    echo "❌ MercadoPago NO configurado correctamente\n";
    $simulation_results['mercadopago'] = 'NO_CONFIG';
    $errors[] = 'MercadoPago no configurado';
}

echo "\n";

// ============================================================
// RESUMEN FINAL
// ============================================================
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMEN DE SIMULACION                     ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";

$all_ok = true;
foreach ($simulation_results as $key => $value) {
    $status_icon = ($value === 'OK' || $value === 'PRODUCCION') ? '✅' : (($value === 'PENDIENTE' || $value === 'TEST' || $value === 'VACIO') ? '⚠️' : '❌');
    if ($value === 'ERROR' || $value === 'NO_CONFIG') $all_ok = false;

    $key_formatted = str_pad(ucfirst(str_replace('_', ' ', $key)), 25);
    echo "║  $status_icon $key_formatted $value\n";
}

echo "╠══════════════════════════════════════════════════════════════╣\n";

if ($all_ok && empty($errors)) {
    echo "║  ✅ SIMULACION EXITOSA - Sistema de pagos funcional         ║\n";
} else {
    echo "║  ⚠️ HAY ASPECTOS QUE REVISAR                                ║\n";
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "║     - $error\n";
        }
    }
}

echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Tabla resumen de montos
echo "RESUMEN DE DISTRIBUCION SIMULADA:\n";
echo "┌───────────────────────┬────────────────┬────────────────┐\n";
echo "│ Concepto              │ Monto          │ Porcentaje     │\n";
echo "├───────────────────────┼────────────────┼────────────────┤\n";
printf("│ %-21s │ \$%12s │ %13s │\n", "Cliente paga", number_format($monto_total, 2), "100%");
printf("│ %-21s │ \$%12s │ %12s%% │\n", "Negocio recibe", number_format($pago_negocio, 2), number_format($pago_negocio/$monto_total*100, 1));
printf("│ %-21s │ \$%12s │ %12s%% │\n", "Repartidor recibe", number_format($pago_repartidor_total, 2), number_format($pago_repartidor_total/$monto_total*100, 1));
printf("│ %-21s │ \$%12s │ %12s%% │\n", "QuickBite recibe", number_format($comision_plataforma, 2), number_format($comision_plataforma/$monto_total*100, 1));
echo "└───────────────────────┴────────────────┴────────────────┘\n";
