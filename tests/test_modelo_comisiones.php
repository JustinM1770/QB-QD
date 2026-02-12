<?php
/**
 * QuickBite - Test del Modelo de Comisiones
 *
 * Ejecutar: php tests/test_modelo_comisiones.php
 */

require_once __DIR__ . '/../config/quickbite_fees.php';

echo "═══════════════════════════════════════════════════════════════\n";
echo "    QUICKBITE - TEST DEL MODELO DE COMISIONES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Test 1: Configuración de constantes
echo "1. VERIFICANDO CONFIGURACIÓN\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "   Comisión básica:     " . QUICKBITE_COMISION_BASICA . "%\n";
echo "   Comisión premium:    " . QUICKBITE_COMISION_PREMIUM . "%\n";
echo "   Cargo servicio:      $" . QUICKBITE_CARGO_SERVICIO . "\n";
echo "   Envío mínimo:        $" . QUICKBITE_ENVIO_MINIMO . "\n";
echo "   Envío por km:        $" . QUICKBITE_ENVIO_POR_KM . "/km\n";
echo "   Km incluidos:        " . QUICKBITE_ENVIO_KM_BASE . " km\n";
echo "   Membresía negocio:   $" . QUICKBITE_MEMBRESIA_NEGOCIO_PRECIO . "/mes\n";
echo "   Membresía Club:      $" . QUICKBITE_CLUB_PRECIO . "/mes\n";
echo "\n";

// Test 2: Cálculo de envío
echo "2. CÁLCULO DE ENVÍO\n";
echo "─────────────────────────────────────────────────────────────────\n";
$distancias = [1, 2, 3, 4, 5, 7, 10, 15, 20];
foreach ($distancias as $km) {
    $costo = calcularCostoEnvioQuickBite($km, false);
    $costoMiembro = calcularCostoEnvioQuickBite($km, true);
    if ($costo == -1) {
        echo "   {$km}km: FUERA DE RANGO\n";
    } else {
        echo "   {$km}km: $" . number_format($costo, 2) . " (Miembro: $" . number_format($costoMiembro, 2) . ")\n";
    }
}
echo "\n";

// Test 3: Cálculo de comisión negocio
echo "3. CÁLCULO DE COMISIÓN NEGOCIO\n";
echo "─────────────────────────────────────────────────────────────────\n";
$montos = [100, 200, 500, 1000, 5000];
foreach ($montos as $monto) {
    $basico = calcularComisionNegocio($monto, false);
    $premium = calcularComisionNegocio($monto, true);
    echo "   Venta \${$monto}:\n";
    echo "      Básico  ({$basico['porcentaje']}%): Comisión \${$basico['monto_comision']}, Recibe \${$basico['monto_negocio']}\n";
    echo "      Premium ({$premium['porcentaje']}%): Comisión \${$premium['monto_comision']}, Recibe \${$premium['monto_negocio']}\n";
    $ahorro = $basico['monto_comision'] - $premium['monto_comision'];
    echo "      Ahorro Premium: \${$ahorro}\n\n";
}

// Test 4: Distribución completa de pedido
echo "4. DISTRIBUCIÓN COMPLETA DE PEDIDO\n";
echo "─────────────────────────────────────────────────────────────────\n";

$escenarios = [
    ['subtotal' => 200, 'km' => 3, 'propina' => 0, 'miembro' => false, 'premium' => false, 'desc' => 'Pedido normal'],
    ['subtotal' => 200, 'km' => 3, 'propina' => 30, 'miembro' => false, 'premium' => false, 'desc' => 'Con propina $30'],
    ['subtotal' => 200, 'km' => 3, 'propina' => 0, 'miembro' => true, 'premium' => false, 'desc' => 'Cliente miembro'],
    ['subtotal' => 200, 'km' => 3, 'propina' => 0, 'miembro' => false, 'premium' => true, 'desc' => 'Negocio premium'],
    ['subtotal' => 200, 'km' => 3, 'propina' => 30, 'miembro' => true, 'premium' => true, 'desc' => 'Miembro + Premium + Propina'],
    ['subtotal' => 500, 'km' => 7, 'propina' => 50, 'miembro' => false, 'premium' => false, 'desc' => 'Pedido grande 7km'],
];

foreach ($escenarios as $e) {
    $dist = calcularDistribucionPedido($e['subtotal'], $e['km'], $e['propina'], $e['miembro'], $e['premium']);

    echo "\n   ESCENARIO: {$e['desc']}\n";
    echo "   Productos: \${$e['subtotal']}, Distancia: {$e['km']}km, Propina: \${$e['propina']}\n";
    echo "   ───────────────────────────────────────────\n";
    echo "   CLIENTE PAGA:\n";
    echo "      Productos:      \${$dist['cliente']['productos']}\n";
    echo "      Envío:          \${$dist['cliente']['envio']}" . ($e['miembro'] ? " (GRATIS)" : "") . "\n";
    echo "      Cargo servicio: \${$dist['cliente']['cargo_servicio']}" . ($e['miembro'] ? " (GRATIS)" : "") . "\n";
    echo "      Propina:        \${$dist['cliente']['propina']}\n";
    echo "      TOTAL:          \${$dist['cliente']['total']}\n";
    echo "   ───────────────────────────────────────────\n";
    echo "   DISTRIBUCIÓN:\n";
    echo "      Negocio:        \${$dist['negocio']['recibe']} ({$dist['negocio']['comision_porcentaje']}% comisión)" . ($e['premium'] ? " [PREMIUM]" : "") . "\n";
    echo "      Repartidor:     \${$dist['repartidor']['total']} (envío + propina)\n";
    echo "      QuickBite:      \${$dist['quickbite']['ganancia_neta']}\n";
    if ($dist['quickbite']['subsidio_envio'] > 0) {
        echo "         (Subsidia envío: \${$dist['quickbite']['subsidio_envio']})\n";
    }
}

// Test 5: Conveniencia de Premium para negocios
echo "\n\n5. ANÁLISIS CONVENIENCIA PREMIUM PARA NEGOCIOS\n";
echo "─────────────────────────────────────────────────────────────────\n";
$ventasMensuales = [5000, 8000, 10000, 15000, 20000, 50000];
foreach ($ventasMensuales as $ventas) {
    $analisis = verificarConvenienciaPremium($ventas);
    $estado = $analisis['conviene'] ? "SI CONVIENE" : "NO conviene";
    echo "   Ventas \$" . number_format($ventas) . "/mes: {$estado}\n";
    echo "      - Ahorro neto: \$" . number_format($analisis['ahorro_neto'], 2) . "/mes\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "    TEST COMPLETADO\n";
echo "═══════════════════════════════════════════════════════════════\n";
