<?php
/**
 * QuickBite - Configuración de Comisiones y Tarifas
 *
 * ACTUALIZADO: Enero 2026
 *
 * MODELO DE NEGOCIO JUSTO (GANAR-GANAR):
 * - Cliente: SIN cargo por servicio, descuentos en envío con membresía
 * - Negocio: Comisión competitiva (8-10%), membresía premium $499
 * - Repartidor: $18 base (≤1.5km), $25 base (>1.5km), 100% propinas
 * - QuickBite: Volumen > margen
 */

// ═══════════════════════════════════════════════════════════════
// COMISIONES PARA NEGOCIOS
// ═══════════════════════════════════════════════════════════════

// Comisión básica (todos los negocios sin membresía premium)
define('QUICKBITE_COMISION_BASICA', 10); // 10%

// Comisión premium (negocios con membresía premium activa)
define('QUICKBITE_COMISION_PREMIUM', 8); // 8%

// ═══════════════════════════════════════════════════════════════
// COMISIONES DE PASARELAS DE PAGO
// ═══════════════════════════════════════════════════════════════

// MercadoPago - Comisión que nos cobra
// https://www.mercadopago.com.mx/costs-section
define('MERCADOPAGO_COMISION_PORCENTAJE', 3.49); // 3.49% por transacción
define('MERCADOPAGO_COMISION_FIJA', 4.00);       // $4 MXN fijo por transacción
define('MERCADOPAGO_IVA', 16);                    // 16% IVA sobre comisión

// Stripe - Comisión que nos cobra (si se usa)
define('STRIPE_COMISION_PORCENTAJE', 3.6);        // 3.6% por transacción
define('STRIPE_COMISION_FIJA', 3.00);             // $3 MXN fijo por transacción

// ═══════════════════════════════════════════════════════════════
// CARGO DE SERVICIO AL CLIENTE
// ═══════════════════════════════════════════════════════════════

// Cargo fijo de servicio - ELIMINADO (0 para todos)
define('QUICKBITE_CARGO_SERVICIO', 0.00); // $0 MXN - SIN CARGO POR SERVICIO

// Cargo de servicio para miembros QuickBite Club (0 = gratis)
define('QUICKBITE_CARGO_SERVICIO_MIEMBRO', 0.00);

// ═══════════════════════════════════════════════════════════════
// CÁLCULO DE ENVÍO (Para Repartidor) - NUEVO MODELO
// ═══════════════════════════════════════════════════════════════

// Radio corto (≤1.5km): Tarifa base menor, más justo para cliente
define('QUICKBITE_ENVIO_RADIO_CORTO', 1.5); // 1.5 km
define('QUICKBITE_ENVIO_BASE_CORTO', 18.00); // $18 MXN para ≤1.5km

// Radio largo (>1.5km): Tarifa base mayor, justo para repartidor
define('QUICKBITE_ENVIO_BASE_LARGO', 25.00); // $25 MXN para >1.5km

// Costo por kilómetro adicional después de 1.5km
define('QUICKBITE_ENVIO_POR_KM', 5.00); // $5 MXN por km adicional

// LEGACY: Mantener compatibilidad con código existente
define('QUICKBITE_ENVIO_MINIMO', 18.00); // Mínimo $18 (radio corto)
define('QUICKBITE_ENVIO_KM_BASE', 1.5); // 1.5 km incluidos

// Envío con membresía - NUEVO MODELO ESCALONADO
// - Pedido ≥$250: Envío GRATIS
// - Pedido $150-$249: Envío 50% descuento
// - Pedido <$150: Envío normal
define('QUICKBITE_ENVIO_GRATIS_MONTO', 250.00); // Pedido ≥$250 = envío gratis
define('QUICKBITE_ENVIO_MITAD_MONTO', 150.00);  // Pedido $150-$249 = envío 50%
define('QUICKBITE_ENVIO_GRATIS_MIEMBRO', false); // Ya no es gratis automático

// Distancia máxima de entrega (km)
define('QUICKBITE_DISTANCIA_MAXIMA', 15);

// ═══════════════════════════════════════════════════════════════
// PROPINAS
// ═══════════════════════════════════════════════════════════════

// Porcentaje de propina que va al repartidor (100% = toda)
define('QUICKBITE_PROPINA_REPARTIDOR', 100); // 100%

// Sugerencias de propina (porcentajes)
define('QUICKBITE_PROPINA_SUGERENCIAS', [10, 15, 20]); // 10%, 15%, 20%

// ═══════════════════════════════════════════════════════════════
// MEMBRESÍAS DE NEGOCIOS
// ═══════════════════════════════════════════════════════════════

// Membresía Premium para Negocios - ACTUALIZADO
define('QUICKBITE_MEMBRESIA_NEGOCIO_PRECIO', 499.00); // $499/mes
define('QUICKBITE_MEMBRESIA_NEGOCIO_BENEFICIOS', [
    'comision_reducida' => '8% en lugar de 10%',
    'whatsapp_bot' => 'Bot automatizado para pedidos',
    'ia_menu' => 'IA para crear/actualizar menú con fotos',
    'reportes_avanzados' => 'Estadísticas detalladas',
    'badge_premium' => 'Distintivo Premium en la app',
    'prioridad_busqueda' => 'Mayor visibilidad en búsquedas',
    'soporte_prioritario' => 'Soporte técnico prioritario',
    'promociones_destacadas' => 'Promociones en portada de la app'
]);

// Umbral de ventas donde conviene la membresía premium ($499 / 2% ahorro = $24,950)
define('QUICKBITE_MEMBRESIA_NEGOCIO_UMBRAL', 25000); // $25,000/mes

// ═══════════════════════════════════════════════════════════════
// MEMBRESÍAS DE CLIENTES (QuickBite Club)
// ═══════════════════════════════════════════════════════════════

// QuickBite Club - Membresía para clientes
define('QUICKBITE_CLUB_PRECIO', 49.00); // $49/mes
define('QUICKBITE_CLUB_BENEFICIOS', [
    'envio_gratis' => 'Envío GRATIS en todos los pedidos',
    'sin_cargo_servicio' => 'Sin cargo de servicio ($5)',
    'descuentos_aliados' => 'Descuentos en negocios aliados',
    'promociones_exclusivas' => 'Promociones solo para miembros',
    'puntos_dobles' => 'Puntos dobles por cada compra',
    'prioridad_pedidos' => 'Prioridad en horarios pico'
]);

// Descuentos en aliados para miembros Club
define('QUICKBITE_CLUB_DESCUENTOS_ALIADOS', [
    'gimnasio' => 20,      // 20% en gym
    'doctor' => 15,        // 15% primera consulta
    'estetica' => 10,      // 10% en servicios
    'cine' => 10,          // 10% en entradas
    'farmacia' => 5        // 5% en medicamentos
]);

// ═══════════════════════════════════════════════════════════════
// SISTEMA DE PUNTOS (Cashback)
// ═══════════════════════════════════════════════════════════════

// Puntos por peso gastado (usuarios normales)
define('QUICKBITE_PUNTOS_POR_PESO', 1); // 1 punto por $1

// Puntos por peso gastado (miembros Club)
define('QUICKBITE_PUNTOS_POR_PESO_CLUB', 2); // 2 puntos por $1

// Valor de cada punto en pesos
define('QUICKBITE_VALOR_PUNTO', 0.10); // $0.10 por punto

// Mínimo de puntos para canjear
define('QUICKBITE_PUNTOS_MINIMO_CANJE', 100); // 100 puntos = $10

// ═══════════════════════════════════════════════════════════════
// DISTRIBUCIÓN DE UN PEDIDO - NUEVO MODELO (Enero 2026)
// ═══════════════════════════════════════════════════════════════
/*
 * TARIFAS DE ENVÍO:
 * ├── ≤1.5km: $18 base
 * └── >1.5km: $25 base + $5/km adicional
 *
 * ═══════════════════════════════════════════════════════════════
 * EJEMPLO 1: Pedido $200, envío 1km (radio corto)
 * ═══════════════════════════════════════════════════════════════
 * Cliente paga:
 * ├── Productos:      $200.00
 * ├── Envío (1km):    $18.00  ← Radio corto
 * └── Cargo servicio: $0.00   ← ELIMINADO
 *     Total:          $218.00
 *
 * Distribución:
 * ├── Negocio:    $180.00 (productos - 10% comisión)
 * ├── Repartidor: $18.00  (envío radio corto)
 * └── QuickBite:  $20.00  (comisión)
 *
 * ═══════════════════════════════════════════════════════════════
 * EJEMPLO 2: Pedido $200, envío 3km (radio largo)
 * ═══════════════════════════════════════════════════════════════
 * Envío: $25 base + (1.5km extra × $5) = $32.50
 *
 * Cliente paga:
 * ├── Productos:      $200.00
 * ├── Envío (3km):    $32.50  ← Radio largo + km extra
 * └── Cargo servicio: $0.00
 *     Total:          $232.50
 *
 * ═══════════════════════════════════════════════════════════════
 * EJEMPLO 3: Miembro con pedido $250+ (envío GRATIS)
 * ═══════════════════════════════════════════════════════════════
 * Cliente paga:
 * ├── Productos:      $250.00
 * ├── Envío:          $0.00   ← GRATIS por pedido ≥$250
 * └── Cargo servicio: $0.00
 *     Total:          $250.00
 *
 * Distribución:
 * ├── Negocio:    $225.00 (productos - 10% comisión)
 * ├── Repartidor: $18.00  (QuickBite subsidia)
 * └── QuickBite:  $7.00   ($25 comisión - $18 subsidio)
 *
 * ═══════════════════════════════════════════════════════════════
 * EJEMPLO 4: Miembro con pedido $150-$249 (envío 50%)
 * ═══════════════════════════════════════════════════════════════
 * Envío normal: $18 → Con descuento: $9
 *
 * Cliente paga:
 * ├── Productos:      $180.00
 * ├── Envío:          $9.00   ← 50% descuento
 * └── Cargo servicio: $0.00
 *     Total:          $189.00
 *
 * Distribución:
 * ├── Negocio:    $162.00 (productos - 10% comisión)
 * ├── Repartidor: $18.00  (tarifa completa)
 * └── QuickBite:  $9.00   ($18 comisión - $9 subsidio)
 */

// ═══════════════════════════════════════════════════════════════
// FUNCIONES AUXILIARES
// ═══════════════════════════════════════════════════════════════

/**
 * Calcular costo de envío basado en distancia - NUEVO MODELO
 *
 * TARIFAS:
 * - ≤1.5km: $18 base
 * - >1.5km: $25 base + $5/km adicional
 *
 * CON MEMBRESÍA:
 * - Pedido ≥$250: Envío GRATIS
 * - Pedido $150-$249: Envío 50% descuento
 * - Pedido <$150: Envío normal
 *
 * @param float $distanciaKm Distancia en kilómetros
 * @param bool $esMiembroClub Si el cliente es miembro del Club
 * @param float $subtotalPedido Subtotal del pedido (para calcular descuentos de membresía)
 * @return float Costo de envío
 */
function calcularCostoEnvioQuickBite($distanciaKm, $esMiembroClub = false, $subtotalPedido = 0) {
    // Verificar distancia máxima
    if ($distanciaKm > QUICKBITE_DISTANCIA_MAXIMA) {
        return -1; // Fuera de rango
    }

    // NUEVO MODELO: Radio corto vs largo
    if ($distanciaKm <= QUICKBITE_ENVIO_RADIO_CORTO) {
        // Radio corto (≤1.5km): $18 base
        $costoEnvio = QUICKBITE_ENVIO_BASE_CORTO;
    } else {
        // Radio largo (>1.5km): $25 base + $5/km adicional
        $kmAdicionales = $distanciaKm - QUICKBITE_ENVIO_RADIO_CORTO;
        $costoAdicional = $kmAdicionales * QUICKBITE_ENVIO_POR_KM;
        $costoEnvio = QUICKBITE_ENVIO_BASE_LARGO + $costoAdicional;
    }

    // Aplicar descuentos para miembros según monto del pedido
    if ($esMiembroClub && $subtotalPedido > 0) {
        if ($subtotalPedido >= QUICKBITE_ENVIO_GRATIS_MONTO) {
            // Pedido ≥$250: Envío GRATIS
            return 0.00;
        } elseif ($subtotalPedido >= QUICKBITE_ENVIO_MITAD_MONTO) {
            // Pedido $150-$249: Envío 50% descuento
            return round($costoEnvio * 0.5, 2);
        }
        // Pedido <$150: Envío normal (sin descuento)
    }

    return round($costoEnvio, 2);
}

/**
 * Calcular pago al repartidor - NUEVO MODELO
 *
 * El repartidor recibe:
 * - ≤1.5km: $18 base
 * - >1.5km: $25 base + $5/km adicional
 * - 100% de la propina
 *
 * @param float $distanciaKm Distancia en kilómetros
 * @param float $propina Propina del cliente
 * @return float Pago total al repartidor
 */
function calcularPagoRepartidor($distanciaKm, $propina = 0) {
    // Calcular pago por envío según distancia (sin descuentos de membresía)
    if ($distanciaKm <= QUICKBITE_ENVIO_RADIO_CORTO) {
        // Radio corto (≤1.5km): $18 base
        $pagoEnvio = QUICKBITE_ENVIO_BASE_CORTO;
    } else {
        // Radio largo (>1.5km): $25 base + $5/km adicional
        $kmAdicionales = $distanciaKm - QUICKBITE_ENVIO_RADIO_CORTO;
        $costoAdicional = $kmAdicionales * QUICKBITE_ENVIO_POR_KM;
        $pagoEnvio = QUICKBITE_ENVIO_BASE_LARGO + $costoAdicional;
    }

    // Propina completa va al repartidor
    $pagoTotal = $pagoEnvio + $propina;

    return round($pagoTotal, 2);
}

/**
 * Calcular comisión del negocio
 * @param float $subtotalProductos Subtotal de productos
 * @param bool $esPremium Si el negocio tiene membresía premium
 * @return array [porcentaje, monto_comision, monto_negocio]
 */
function calcularComisionNegocio($subtotalProductos, $esPremium = false) {
    $porcentaje = $esPremium ? QUICKBITE_COMISION_PREMIUM : QUICKBITE_COMISION_BASICA;
    $montoComision = $subtotalProductos * ($porcentaje / 100);
    $montoNegocio = $subtotalProductos - $montoComision;

    return [
        'porcentaje' => $porcentaje,
        'monto_comision' => round($montoComision, 2),
        'monto_negocio' => round($montoNegocio, 2)
    ];
}

/**
 * Calcular cargo de servicio
 * @param bool $esMiembroClub Si el cliente es miembro del Club
 * @return float Cargo de servicio
 */
function calcularCargoServicio($esMiembroClub = false) {
    return $esMiembroClub ? QUICKBITE_CARGO_SERVICIO_MIEMBRO : QUICKBITE_CARGO_SERVICIO;
}

/**
 * Calcular distribución completa de un pedido - NUEVO MODELO
 *
 * @param float $subtotalProductos Subtotal de productos
 * @param float $distanciaKm Distancia en kilómetros
 * @param float $propina Propina del cliente
 * @param bool $clienteEsMiembro Si el cliente es miembro del Club
 * @param bool $negocioEsPremium Si el negocio tiene membresía premium
 * @return array Distribución completa del pedido
 */
function calcularDistribucionPedido($subtotalProductos, $distanciaKm, $propina = 0, $clienteEsMiembro = false, $negocioEsPremium = false) {
    // Calcular envío que paga el cliente (con descuentos si aplica)
    $costoEnvioCliente = calcularCostoEnvioQuickBite($distanciaKm, $clienteEsMiembro, $subtotalProductos);

    // Calcular envío real (lo que recibe el repartidor, sin descuentos)
    $envioRealRepartidor = calcularPagoRepartidor($distanciaKm, 0); // Solo envío, propina aparte

    // Cargo servicio (ahora es $0 para todos)
    $cargoServicio = calcularCargoServicio($clienteEsMiembro);

    // Comisión del negocio
    $comision = calcularComisionNegocio($subtotalProductos, $negocioEsPremium);

    // Pago total al repartidor (envío real + propina)
    $pagoRepartidor = calcularPagoRepartidor($distanciaKm, $propina);

    // Total que paga el cliente
    $totalCliente = $subtotalProductos + $costoEnvioCliente + $cargoServicio + $propina;

    // Calcular subsidio de envío (diferencia entre lo que paga cliente y lo que recibe repartidor)
    $subsidioEnvio = 0;
    if ($clienteEsMiembro && $costoEnvioCliente < $envioRealRepartidor) {
        $subsidioEnvio = $envioRealRepartidor - $costoEnvioCliente;
    }

    // Ganancia QuickBite (comisión - subsidio de envío)
    $gananciaQuickBite = $comision['monto_comision'] + $cargoServicio - $subsidioEnvio;

    // Determinar tipo de descuento aplicado
    $tipoDescuentoEnvio = 'ninguno';
    if ($clienteEsMiembro) {
        if ($subtotalProductos >= QUICKBITE_ENVIO_GRATIS_MONTO) {
            $tipoDescuentoEnvio = 'gratis';
        } elseif ($subtotalProductos >= QUICKBITE_ENVIO_MITAD_MONTO) {
            $tipoDescuentoEnvio = '50%';
        }
    }

    return [
        'cliente' => [
            'productos' => round($subtotalProductos, 2),
            'envio' => round($costoEnvioCliente, 2),
            'cargo_servicio' => round($cargoServicio, 2),
            'propina' => round($propina, 2),
            'total' => round($totalCliente, 2),
            'descuento_envio' => $tipoDescuentoEnvio
        ],
        'negocio' => [
            'venta_productos' => round($subtotalProductos, 2),
            'comision_porcentaje' => $comision['porcentaje'],
            'comision_monto' => $comision['monto_comision'],
            'recibe' => $comision['monto_negocio'],
            'es_premium' => $negocioEsPremium
        ],
        'repartidor' => [
            'envio' => round($envioRealRepartidor, 2),
            'propina' => round($propina, 2),
            'total' => round($pagoRepartidor, 2),
            'tarifa_base' => $distanciaKm <= QUICKBITE_ENVIO_RADIO_CORTO
                ? QUICKBITE_ENVIO_BASE_CORTO
                : QUICKBITE_ENVIO_BASE_LARGO
        ],
        'quickbite' => [
            'comision_negocio' => $comision['monto_comision'],
            'cargo_servicio' => round($cargoServicio, 2),
            'subsidio_envio' => round($subsidioEnvio, 2),
            'ganancia_neta' => round($gananciaQuickBite, 2)
        ],
        'distancia_km' => $distanciaKm,
        'cliente_es_miembro' => $clienteEsMiembro
    ];
}

/**
 * Verificar si conviene membresía premium para negocio
 * @param float $ventasMensuales Ventas mensuales del negocio
 * @return array [conviene, ahorro_mensual, mensaje]
 */
function verificarConvenienciaPremium($ventasMensuales) {
    $comisionBasica = $ventasMensuales * (QUICKBITE_COMISION_BASICA / 100);
    $comisionPremium = $ventasMensuales * (QUICKBITE_COMISION_PREMIUM / 100);
    $ahorroComision = $comisionBasica - $comisionPremium;
    $costoMembresia = QUICKBITE_MEMBRESIA_NEGOCIO_PRECIO;
    $ahorroNeto = $ahorroComision - $costoMembresia;

    $conviene = $ahorroNeto > 0;

    return [
        'conviene' => $conviene,
        'ventas_mensuales' => $ventasMensuales,
        'comision_sin_premium' => round($comisionBasica, 2),
        'comision_con_premium' => round($comisionPremium, 2),
        'ahorro_comision' => round($ahorroComision, 2),
        'costo_membresia' => $costoMembresia,
        'ahorro_neto' => round($ahorroNeto, 2),
        'mensaje' => $conviene
            ? "Con tus ventas de $" . number_format($ventasMensuales, 2) . "/mes, ahorrarías $" . number_format($ahorroNeto, 2) . " mensuales con Premium."
            : "Con ventas de $" . number_format($ventasMensuales, 2) . "/mes, Premium no te conviene aún. Necesitas vender más de $" . number_format(QUICKBITE_MEMBRESIA_NEGOCIO_UMBRAL, 2) . "/mes."
    ];
}

// ═══════════════════════════════════════════════════════════════
// CÁLCULOS DE COMISIONES DE PASARELAS DE PAGO
// ═══════════════════════════════════════════════════════════════

/**
 * Calcular comisión de MercadoPago sobre un monto
 * @param float $monto Monto total de la transacción
 * @return array [comision_porcentaje, comision_fija, iva, total_comision, monto_neto]
 */
function calcularComisionMercadoPago($monto) {
    $comisionPorcentaje = $monto * (MERCADOPAGO_COMISION_PORCENTAJE / 100);
    $comisionFija = MERCADOPAGO_COMISION_FIJA;
    $subtotalComision = $comisionPorcentaje + $comisionFija;
    $iva = $subtotalComision * (MERCADOPAGO_IVA / 100);
    $totalComision = $subtotalComision + $iva;
    $montoNeto = $monto - $totalComision;

    return [
        'monto_bruto' => round($monto, 2),
        'comision_porcentaje' => round($comisionPorcentaje, 2),
        'comision_fija' => MERCADOPAGO_COMISION_FIJA,
        'subtotal_comision' => round($subtotalComision, 2),
        'iva' => round($iva, 2),
        'total_comision' => round($totalComision, 2),
        'monto_neto' => round($montoNeto, 2)
    ];
}

/**
 * Calcular comisión de Stripe sobre un monto
 * @param float $monto Monto total de la transacción
 * @return array [comision, monto_neto]
 */
function calcularComisionStripe($monto) {
    $comisionPorcentaje = $monto * (STRIPE_COMISION_PORCENTAJE / 100);
    $comisionFija = STRIPE_COMISION_FIJA;
    $totalComision = $comisionPorcentaje + $comisionFija;
    $montoNeto = $monto - $totalComision;

    return [
        'monto_bruto' => round($monto, 2),
        'comision_porcentaje' => round($comisionPorcentaje, 2),
        'comision_fija' => STRIPE_COMISION_FIJA,
        'total_comision' => round($totalComision, 2),
        'monto_neto' => round($montoNeto, 2)
    ];
}

/**
 * Calcular ganancia neta real de QuickBite (después de comisión de pasarela)
 * @param float $subtotalProductos Subtotal de productos
 * @param float $distanciaKm Distancia en kilómetros
 * @param float $propina Propina
 * @param bool $clienteEsMiembro Si cliente es miembro
 * @param bool $negocioEsPremium Si negocio es premium
 * @param string $pasarela 'mercadopago' o 'stripe'
 * @return array Distribución completa con comisión de pasarela
 */
function calcularGananciaNetaReal($subtotalProductos, $distanciaKm, $propina = 0, $clienteEsMiembro = false, $negocioEsPremium = false, $pasarela = 'mercadopago') {
    // Obtener distribución base
    $dist = calcularDistribucionPedido($subtotalProductos, $distanciaKm, $propina, $clienteEsMiembro, $negocioEsPremium);

    // Calcular comisión de pasarela sobre el total cobrado
    $totalCobrado = $dist['cliente']['total'];

    if ($pasarela === 'mercadopago') {
        $comisionPasarela = calcularComisionMercadoPago($totalCobrado);
    } else {
        $comisionPasarela = calcularComisionStripe($totalCobrado);
    }

    // La comisión de pasarela la absorbe QuickBite
    $gananciaQuickBiteBruta = $dist['quickbite']['ganancia_neta'];
    $gananciaQuickBiteNeta = $gananciaQuickBiteBruta - $comisionPasarela['total_comision'];

    $dist['pasarela'] = [
        'nombre' => $pasarela,
        'comision' => $comisionPasarela['total_comision'],
        'detalle' => $comisionPasarela
    ];

    $dist['quickbite']['comision_pasarela'] = $comisionPasarela['total_comision'];
    $dist['quickbite']['ganancia_bruta'] = $gananciaQuickBiteBruta;
    $dist['quickbite']['ganancia_neta'] = round($gananciaQuickBiteNeta, 2);

    return $dist;
}
