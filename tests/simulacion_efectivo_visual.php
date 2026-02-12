<?php
/**
 * Simulación Visual: Pedido con Pago en Efectivo
 * Muestra paso a paso cómo funciona el flujo de pagos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PagosPedidoService.php';

$database = new Database();
$db = $database->getConnection();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     🍔 SIMULACIÓN: PEDIDO CON PAGO EN EFECTIVO 💵              ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Obtener datos reales
$stmt = $db->query("SELECT n.*, u.nombre as nombre_propietario, u.id_usuario as id_propietario
                    FROM negocios n
                    JOIN usuarios u ON n.id_propietario = u.id_usuario
                    LIMIT 1");
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT r.*, u.nombre as nombre_repartidor
                    FROM repartidores r
                    JOIN usuarios u ON r.id_usuario = u.id_usuario
                    LIMIT 1");
$repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM usuarios WHERE tipo_usuario = 'cliente' LIMIT 1");
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════════════════════════
// PASO 1: MOSTRAR PARTICIPANTES
// ══════════════════════════════════════════════════════════════════
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 👥 PARTICIPANTES DEL PEDIDO                                   │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│ 🏪 Negocio:    " . str_pad($negocio['nombre'], 47) . "│\n";
echo "│ 🛵 Repartidor: " . str_pad($repartidor['nombre_repartidor'], 47) . "│\n";
echo "│ 👤 Cliente:    " . str_pad($cliente['nombre'], 47) . "│\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// PASO 2: SALDOS ANTES
// ══════════════════════════════════════════════════════════════════
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 💰 SALDOS ANTES DEL PEDIDO                                    │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";

// Wallet del negocio
$stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
$stmt->execute([$negocio['id_propietario']]);
$wallet_negocio = $stmt->fetch(PDO::FETCH_ASSOC);
$saldo_negocio_antes = floatval($wallet_negocio['saldo_disponible'] ?? 0);

// Wallet del repartidor
$stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
$stmt->execute([$repartidor['id_usuario']]);
$wallet_repartidor = $stmt->fetch(PDO::FETCH_ASSOC);
$saldo_repartidor_antes = floatval($wallet_repartidor['saldo_disponible'] ?? 0);

// Deuda del negocio
$stmt = $db->prepare("SELECT COALESCE(saldo_deudor, 0) as deuda FROM negocios WHERE id_negocio = ?");
$stmt->execute([$negocio['id_negocio']]);
$deuda_antes = floatval($stmt->fetch(PDO::FETCH_ASSOC)['deuda']);

echo "│ 🏪 Negocio - Saldo retirable:    $" . str_pad(number_format($saldo_negocio_antes, 2), 24) . "│\n";
echo "│ 🏪 Negocio - Deuda comisiones:   $" . str_pad(number_format($deuda_antes, 2), 24) . "│\n";
echo "│ 🛵 Repartidor - Saldo retirable: $" . str_pad(number_format($saldo_repartidor_antes, 2), 24) . "│\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// PASO 3: CREAR EL PEDIDO
// ══════════════════════════════════════════════════════════════════
$total_productos = 450.00;
$costo_envio = 40.00;
$propina = 25.00;
$cargo_servicio = 5.00;
$total = $total_productos + $costo_envio + $propina + $cargo_servicio;

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 🛒 DETALLES DEL PEDIDO                                        │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│   Productos (2x Hamburguesa, 1x Papas, 2x Refresco)           │\n";
echo "│   ─────────────────────────────────────────────────────────   │\n";
echo "│   Subtotal productos:              $" . str_pad(number_format($total_productos, 2), 22) . "│\n";
echo "│   Costo de envío:                  $" . str_pad(number_format($costo_envio, 2), 22) . "│\n";
echo "│   Propina al repartidor:           $" . str_pad(number_format($propina, 2), 22) . "│\n";
echo "│   Cargo por servicio:              $" . str_pad(number_format($cargo_servicio, 2), 22) . "│\n";
echo "│   ─────────────────────────────────────────────────────────   │\n";
echo "│   💵 TOTAL A PAGAR EN EFECTIVO:    $" . str_pad(number_format($total, 2), 22) . "│\n";
echo "│                                                                │\n";
echo "│   📋 Método de pago: EFECTIVO                                 │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// Obtener dirección
$stmt = $db->prepare("SELECT id_direccion FROM direcciones_usuario WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$cliente['id_usuario']]);
$dir = $stmt->fetch(PDO::FETCH_ASSOC);
$id_direccion = $dir['id_direccion'] ?? 1;

// Insertar pedido
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

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ ✅ PEDIDO CREADO: #" . str_pad($id_pedido, 44) . "│\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// PASO 4: SIMULAR ENTREGA Y PROCESAR PAGOS
// ══════════════════════════════════════════════════════════════════
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 🚚 SIMULANDO FLUJO DE ENTREGA...                              │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│ → Pedido confirmado por el negocio                            │\n";
echo "│ → Repartidor asignado y recoge el pedido                      │\n";
echo "│ → Repartidor en camino                                        │\n";
echo "│ → 🏠 Repartidor llega al domicilio                            │\n";
echo "│ → 💵 Cliente paga $" . number_format($total, 2) . " EN EFECTIVO                        │\n";
echo "│ → ✅ Pedido marcado como ENTREGADO                            │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// Procesar pagos
$servicioPagos = new PagosPedidoService($db);
$resultado = $servicioPagos->procesarPagosPedido($id_pedido);

if (!$resultado['success']) {
    echo "❌ ERROR: " . $resultado['error'] . "\n";
    exit(1);
}

// ══════════════════════════════════════════════════════════════════
// PASO 5: DISTRIBUCIÓN DE PAGOS
// ══════════════════════════════════════════════════════════════════
$dist = $resultado['distribucion'];

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 📊 DISTRIBUCIÓN DEL PAGO EN EFECTIVO                          │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│   El cliente pagó $" . number_format($total, 2) . " en efectivo al repartidor         │\n";
echo "│                                                                │\n";
echo "│   ┌──────────────────────────────────────────────────────┐    │\n";
echo "│   │ 🏪 NEGOCIO recibe en mano:                           │    │\n";
echo "│   │    Productos: $" . str_pad(number_format($total_productos, 2), 33) . "│    │\n";
echo "│   │    (menos comisión 10%: -$" . str_pad(number_format($dist['comision_plataforma'], 2), 22) . "│    │\n";
echo "│   │    = Ganancia neta: $" . str_pad(number_format($dist['pago_negocio'], 2), 28) . "│    │\n";
echo "│   └──────────────────────────────────────────────────────┘    │\n";
echo "│                                                                │\n";
echo "│   ┌──────────────────────────────────────────────────────┐    │\n";
echo "│   │ 🛵 REPARTIDOR recibe en mano:                        │    │\n";
echo "│   │    Envío: $" . str_pad(number_format($costo_envio, 2), 38) . "│    │\n";
echo "│   │    Propina: $" . str_pad(number_format($propina, 2), 36) . "│    │\n";
echo "│   │    = Total: $" . str_pad(number_format($dist['pago_repartidor'], 2), 36) . "│    │\n";
echo "│   └──────────────────────────────────────────────────────┘    │\n";
echo "│                                                                │\n";
echo "│   ┌──────────────────────────────────────────────────────┐    │\n";
echo "│   │ 🏢 QUICKBITE debe recibir:                           │    │\n";
echo "│   │    Comisión (10%): $" . str_pad(number_format($dist['comision_plataforma'], 2), 29) . "│    │\n";
echo "│   │    Cargo servicio: $" . str_pad(number_format($cargo_servicio, 2), 28) . "│    │\n";
echo "│   │    = Total adeudado: $" . str_pad(number_format($dist['comision_plataforma'] + $cargo_servicio, 2), 26) . "│    │\n";
echo "│   └──────────────────────────────────────────────────────┘    │\n";
echo "│                                                                │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// PASO 6: SALDOS DESPUÉS
// ══════════════════════════════════════════════════════════════════

// Wallet del negocio después
$stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'business'");
$stmt->execute([$negocio['id_propietario']]);
$saldo_negocio_despues = floatval($stmt->fetch(PDO::FETCH_ASSOC)['saldo_disponible'] ?? 0);

// Wallet del repartidor después
$stmt = $db->prepare("SELECT saldo_disponible FROM wallets WHERE id_usuario = ? AND tipo_usuario = 'courier'");
$stmt->execute([$repartidor['id_usuario']]);
$saldo_repartidor_despues = floatval($stmt->fetch(PDO::FETCH_ASSOC)['saldo_disponible'] ?? 0);

// Deuda del negocio después
$stmt = $db->prepare("SELECT COALESCE(saldo_deudor, 0) as deuda FROM negocios WHERE id_negocio = ?");
$stmt->execute([$negocio['id_negocio']]);
$deuda_despues = floatval($stmt->fetch(PDO::FETCH_ASSOC)['deuda']);

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 💰 SALDOS DESPUÉS DEL PEDIDO                                  │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│ 🏪 NEGOCIO:                                                   │\n";
echo "│    Saldo retirable:     $" . str_pad(number_format($saldo_negocio_despues, 2), 14) . " (sin cambios)           │\n";
echo "│    Deuda a QuickBite:   $" . str_pad(number_format($deuda_despues, 2), 14) . " (+$" . number_format($dist['comision_plataforma'], 2) . ")            │\n";
echo "│                                                                │\n";
echo "│ 🛵 REPARTIDOR:                                                │\n";
echo "│    Saldo retirable:     $" . str_pad(number_format($saldo_repartidor_despues, 2), 14) . " (sin cambios)           │\n";
echo "│                                                                │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// PASO 7: EXPLICACIÓN VISUAL
// ══════════════════════════════════════════════════════════════════
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 📝 ¿POR QUÉ EL SALDO RETIRABLE NO CAMBIÓ?                     │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│  ✓ El pago fue en EFECTIVO                                    │\n";
echo "│  ✓ El repartidor ya cobró $" . str_pad(number_format($total, 2), 6) . " del cliente               │\n";
echo "│  ✓ El negocio recibe su parte del efectivo físicamente        │\n";
echo "│  ✓ NO hay dinero que \"retirar\" porque ya lo tienen en mano    │\n";
echo "│                                                                │\n";
echo "│  ⚠️  El negocio ahora DEBE $" . str_pad(number_format($dist['comision_plataforma'], 2), 6) . " de comisión a QuickBite   │\n";
echo "│     (Esta deuda se acumula y debe pagarse periódicamente)     │\n";
echo "│                                                                │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// PASO 8: VERIFICAR TRANSACCIONES
// ══════════════════════════════════════════════════════════════════
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 📜 TRANSACCIONES REGISTRADAS                                  │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";

$stmt = $db->prepare("
    SELECT wt.*, w.tipo_usuario
    FROM wallet_transacciones wt
    JOIN wallets w ON wt.id_wallet = w.id_wallet
    WHERE wt.id_pedido = ?
    ORDER BY wt.fecha DESC
");
$stmt->execute([$id_pedido]);
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($transacciones as $t) {
    $tipo_icon = $t['tipo_usuario'] === 'business' ? '🏪' : '🛵';
    $tipo_nombre = $t['tipo_usuario'] === 'business' ? 'Negocio' : 'Repartidor';
    echo "│ $tipo_icon $tipo_nombre: " . str_pad($t['tipo'], 20) . " $" . str_pad(number_format($t['monto'], 2), 8) . "│\n";
    echo "│    └─ " . str_pad($t['descripcion'], 55) . "│\n";
}

echo "└────────────────────────────────────────────────────────────────┘\n\n";

// ══════════════════════════════════════════════════════════════════
// COMPARACIÓN CON PAGO DIGITAL
// ══════════════════════════════════════════════════════════════════
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ ⚖️  COMPARACIÓN: EFECTIVO vs DIGITAL                          │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│                                                                │\n";
echo "│   PAGO EN EFECTIVO (este pedido):                             │\n";
echo "│   ├─ Negocio recibe dinero físico: ✅ YA LO TIENE             │\n";
echo "│   ├─ Saldo retirable aumenta: ❌ NO (ya cobró)                │\n";
echo "│   ├─ Genera deuda de comisión: ✅ SÍ (\$" . number_format($dist['comision_plataforma'], 2) . ")                │\n";
echo "│   └─ Repartidor: ✅ YA COBRÓ \$" . number_format($dist['pago_repartidor'], 2) . " del cliente            │\n";
echo "│                                                                │\n";
echo "│   PAGO DIGITAL (tarjeta/MercadoPago):                         │\n";
echo "│   ├─ QuickBite recibe el pago primero                         │\n";
echo "│   ├─ Saldo retirable aumenta: ✅ SÍ                           │\n";
echo "│   ├─ Genera deuda: ❌ NO (comisión ya descontada)             │\n";
echo "│   └─ Repartidor: Retira cuando quiera de su wallet            │\n";
echo "│                                                                │\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     ✅ SIMULACIÓN COMPLETADA EXITOSAMENTE                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
