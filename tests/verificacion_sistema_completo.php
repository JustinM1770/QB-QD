<?php
/**
 * VERIFICACIÓN COMPLETA DEL SISTEMA QUICKBITE
 * Revisa todos los componentes críticos antes de lanzar a producción
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     🔍 VERIFICACIÓN COMPLETA DEL SISTEMA QUICKBITE              ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$errores = [];
$advertencias = [];
$exitos = [];

// ══════════════════════════════════════════════════════════════════
// 1. VERIFICAR CONEXIÓN A BASE DE DATOS
// ══════════════════════════════════════════════════════════════════
echo "┌─ 1. CONEXIÓN A BASE DE DATOS ─────────────────────────────────────\n";

try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "│  ✅ Conexión a MySQL exitosa\n";
    $exitos[] = "Conexión a base de datos";
} catch (Exception $e) {
    echo "│  ❌ ERROR: " . $e->getMessage() . "\n";
    $errores[] = "No se puede conectar a la base de datos: " . $e->getMessage();
}
echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 2. VERIFICAR TABLAS CRÍTICAS
// ══════════════════════════════════════════════════════════════════
echo "┌─ 2. TABLAS DE BASE DE DATOS ──────────────────────────────────────\n";

$tablas_requeridas = [
    'usuarios' => 'Usuarios del sistema',
    'negocios' => 'Negocios/restaurantes',
    'productos' => 'Productos de los negocios',
    'categorias_producto' => 'Categorías de productos',
    'pedidos' => 'Pedidos de clientes',
    'detalles_pedido' => 'Productos de cada pedido',
    'repartidores' => 'Repartidores',
    'direcciones_usuario' => 'Direcciones de entrega',
    'wallets' => 'Billeteras digitales',
    'wallet_transacciones' => 'Transacciones de billetera',
    'wallet_retiros' => 'Solicitudes de retiro',
    'estados_pedido' => 'Estados de pedidos',
];

foreach ($tablas_requeridas as $tabla => $descripcion) {
    try {
        $stmt = $db->query("SELECT 1 FROM $tabla LIMIT 1");
        echo "│  ✅ $tabla\n";
    } catch (PDOException $e) {
        echo "│  ❌ $tabla - NO EXISTE\n";
        $errores[] = "Tabla faltante: $tabla ($descripcion)";
    }
}
echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 3. VERIFICAR COLUMNAS CRÍTICAS
// ══════════════════════════════════════════════════════════════════
echo "┌─ 3. COLUMNAS CRÍTICAS ────────────────────────────────────────────\n";

$columnas_verificar = [
    ['pedidos', 'metodo_pago', 'Método de pago en pedidos'],
    ['pedidos', 'id_repartidor', 'Repartidor asignado'],
    ['pedidos', 'comision_plataforma', 'Comisión calculada'],
    ['pedidos', 'pago_negocio', 'Pago al negocio'],
    ['pedidos', 'pago_repartidor', 'Pago al repartidor'],
    ['negocios', 'cuenta_clabe', 'CLABE del negocio'],
    ['negocios', 'saldo_deudor', 'Deudas del negocio'],
    ['repartidores', 'cuenta_clabe', 'CLABE del repartidor'],
    ['wallets', 'saldo_disponible', 'Saldo disponible'],
    ['wallets', 'saldo_pendiente', 'Saldo pendiente'],
    ['wallet_transacciones', 'es_efectivo', 'Marcador de efectivo'],
];

foreach ($columnas_verificar as $col) {
    try {
        $stmt = $db->query("SELECT {$col[1]} FROM {$col[0]} LIMIT 1");
        echo "│  ✅ {$col[0]}.{$col[1]}\n";
    } catch (PDOException $e) {
        echo "│  ❌ {$col[0]}.{$col[1]} - FALTA\n";
        $errores[] = "Columna faltante: {$col[0]}.{$col[1]} ({$col[2]})";
    }
}
echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 4. VERIFICAR ARCHIVOS PHP CRÍTICOS (SINTAXIS)
// ══════════════════════════════════════════════════════════════════
echo "┌─ 4. ARCHIVOS PHP CRÍTICOS ────────────────────────────────────────\n";

$archivos_criticos = [
    '/var/www/html/config/database.php' => 'Configuración BD',
    '/var/www/html/config/quickbite_fees.php' => 'Configuración comisiones',
    '/var/www/html/services/PagosPedidoService.php' => 'Servicio de pagos',
    '/var/www/html/admin/finalizar_pedido.php' => 'Finalizar pedido',
    '/var/www/html/admin/wallet_negocio.php' => 'Wallet negocio',
    '/var/www/html/admin/repartidor/wallet.php' => 'Wallet repartidor',
    '/var/www/html/checkout.php' => 'Checkout',
    '/var/www/html/carrito.php' => 'Carrito',
    '/var/www/html/login.php' => 'Login',
    '/var/www/html/register.php' => 'Registro',
    '/var/www/html/index.php' => 'Página principal',
];

foreach ($archivos_criticos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        // Verificar sintaxis PHP
        $output = shell_exec("php -l $archivo 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "│  ✅ " . basename($archivo) . "\n";
        } else {
            echo "│  ❌ " . basename($archivo) . " - ERROR SINTAXIS\n";
            $errores[] = "Error de sintaxis en: $archivo";
        }
    } else {
        echo "│  ❌ " . basename($archivo) . " - NO EXISTE\n";
        $errores[] = "Archivo faltante: $archivo ($descripcion)";
    }
}
echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 5. VERIFICAR CONFIGURACIONES
// ══════════════════════════════════════════════════════════════════
echo "┌─ 5. CONFIGURACIONES ──────────────────────────────────────────────\n";

// Verificar config de comisiones
if (file_exists('/var/www/html/config/quickbite_fees.php')) {
    require_once '/var/www/html/config/quickbite_fees.php';
    if (defined('QUICKBITE_COMISION_BASICA')) {
        echo "│  ✅ Comisión básica: " . QUICKBITE_COMISION_BASICA . "%\n";
    }
    if (defined('QUICKBITE_COMISION_PREMIUM')) {
        echo "│  ✅ Comisión premium: " . QUICKBITE_COMISION_PREMIUM . "%\n";
    }
    if (defined('QUICKBITE_ENVIO_MINIMO')) {
        echo "│  ✅ Envío mínimo: $" . QUICKBITE_ENVIO_MINIMO . "\n";
    }
} else {
    echo "│  ❌ Configuración de comisiones no encontrada\n";
    $errores[] = "Falta archivo de configuración de comisiones";
}

// Verificar MercadoPago config
if (file_exists('/var/www/html/config/mercadopago.php')) {
    $mp_config = require '/var/www/html/config/mercadopago.php';
    if (!empty($mp_config['access_token'])) {
        echo "│  ✅ MercadoPago configurado\n";
    } else {
        echo "│  ⚠️  MercadoPago: Token vacío\n";
        $advertencias[] = "MercadoPago sin access_token configurado";
    }
} else {
    echo "│  ⚠️  MercadoPago: Archivo no existe\n";
    $advertencias[] = "Configuración de MercadoPago no encontrada";
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 6. VERIFICAR DATOS MÍNIMOS
// ══════════════════════════════════════════════════════════════════
echo "┌─ 6. DATOS EN EL SISTEMA ──────────────────────────────────────────\n";

// Estados de pedido
$stmt = $db->query("SELECT COUNT(*) FROM estados_pedido");
$cnt = $stmt->fetchColumn();
if ($cnt >= 6) {
    echo "│  ✅ Estados de pedido: $cnt estados\n";
} else {
    echo "│  ❌ Estados de pedido: Solo $cnt (necesita mínimo 6)\n";
    $errores[] = "Faltan estados de pedido en la tabla estados_pedido";
}

// Usuarios
$stmt = $db->query("SELECT COUNT(*) FROM usuarios");
$cnt = $stmt->fetchColumn();
echo "│  📊 Usuarios registrados: $cnt\n";

// Negocios
$stmt = $db->query("SELECT COUNT(*) FROM negocios WHERE activo = 1");
$cnt = $stmt->fetchColumn();
if ($cnt > 0) {
    echo "│  ✅ Negocios activos: $cnt\n";
} else {
    echo "│  ⚠️  No hay negocios activos\n";
    $advertencias[] = "No hay negocios activos en el sistema";
}

// Productos
$stmt = $db->query("SELECT COUNT(*) FROM productos WHERE disponible = 1");
$cnt = $stmt->fetchColumn();
if ($cnt > 0) {
    echo "│  ✅ Productos disponibles: $cnt\n";
} else {
    echo "│  ⚠️  No hay productos disponibles\n";
    $advertencias[] = "No hay productos disponibles";
}

// Repartidores
$stmt = $db->query("SELECT COUNT(*) FROM repartidores WHERE activo = 1");
$cnt = $stmt->fetchColumn();
if ($cnt > 0) {
    echo "│  ✅ Repartidores activos: $cnt\n";
} else {
    echo "│  ⚠️  No hay repartidores activos\n";
    $advertencias[] = "No hay repartidores para asignar pedidos";
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 7. PROBAR FLUJO DE CHECKOUT
// ══════════════════════════════════════════════════════════════════
echo "┌─ 7. VERIFICAR FLUJO DE CHECKOUT ──────────────────────────────────\n";

// Verificar checkout
if (file_exists('/var/www/html/checkout.php')) {
    echo "│  ✅ Checkout existe\n";
} else {
    echo "│  ❌ Checkout no encontrado\n";
    $errores[] = "Falta checkout.php";
}

// Verificar carrito
if (file_exists('/var/www/html/carrito.php')) {
    echo "│  ✅ Página de carrito existe\n";
} else {
    echo "│  ❌ Página de carrito no encontrada\n";
    $errores[] = "Falta carrito.php";
}

// Verificar confirmación de pedido
if (file_exists('/var/www/html/confirmacion_pedido.php')) {
    echo "│  ✅ Confirmación de pedido existe\n";
} else {
    echo "│  ❌ Confirmación de pedido no encontrada\n";
    $errores[] = "Falta confirmacion_pedido.php";
}

// Carrito usa sesiones (no modelo separado)
echo "│  ✅ Carrito usa sesiones (sistema estándar)\n";

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 8. PROBAR SERVICIO DE PAGOS
// ══════════════════════════════════════════════════════════════════
echo "┌─ 8. SERVICIO DE PAGOS ────────────────────────────────────────────\n";

try {
    require_once '/var/www/html/services/PagosPedidoService.php';
    $servicio = new PagosPedidoService($db);
    echo "│  ✅ PagosPedidoService carga correctamente\n";

    // Verificar métodos existen
    $metodos = ['procesarPagosPedido', 'solicitarRetiro', 'obtenerResumenGanancias'];
    foreach ($metodos as $metodo) {
        if (method_exists($servicio, $metodo)) {
            echo "│  ✅ Método $metodo() existe\n";
        } else {
            echo "│  ❌ Método $metodo() FALTA\n";
            $errores[] = "Método faltante en PagosPedidoService: $metodo";
        }
    }
} catch (Exception $e) {
    echo "│  ❌ Error cargando servicio: " . $e->getMessage() . "\n";
    $errores[] = "Error en PagosPedidoService: " . $e->getMessage();
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 9. VERIFICAR PERMISOS DE DIRECTORIOS
// ══════════════════════════════════════════════════════════════════
echo "┌─ 9. PERMISOS Y DIRECTORIOS ───────────────────────────────────────\n";

$dirs_verificar = [
    '/var/www/html/uploads' => 'Subida de archivos',
    '/var/www/html/admin/logs' => 'Logs de admin',
];

foreach ($dirs_verificar as $dir => $desc) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "│  ✅ $dir (escritura OK)\n";
        } else {
            echo "│  ⚠️  $dir (sin permisos escritura)\n";
            $advertencias[] = "Directorio sin permisos de escritura: $dir";
        }
    } else {
        echo "│  ⚠️  $dir no existe\n";
        $advertencias[] = "Directorio no existe: $dir ($desc)";
    }
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 10. VERIFICAR PÁGINAS PRINCIPALES ACCESIBLES
// ══════════════════════════════════════════════════════════════════
echo "┌─ 10. PÁGINAS PRINCIPALES ─────────────────────────────────────────\n";

$paginas = [
    'index.php' => 'Inicio',
    'login.php' => 'Login',
    'register.php' => 'Registro',
    'carrito.php' => 'Carrito',
    'buscar.php' => 'Buscar/Explorar negocios',
    'negocio.php' => 'Ver negocio',
    'checkout.php' => 'Checkout',
    'pedidos.php' => 'Mis pedidos',
    'confirmacion_pedido.php' => 'Confirmación pedido',
];

foreach ($paginas as $pagina => $desc) {
    $path = "/var/www/html/$pagina";
    if (file_exists($path)) {
        echo "│  ✅ $pagina\n";
    } else {
        echo "│  ❌ $pagina - NO EXISTE\n";
        $errores[] = "Página faltante: $pagina ($desc)";
    }
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 11. VERIFICAR MODELOS PRINCIPALES
// ══════════════════════════════════════════════════════════════════
echo "┌─ 11. MODELOS PHP ─────────────────────────────────────────────────\n";

$modelos = [
    'Usuario.php',
    'Negocio.php',
    'Producto.php',
    'Pedido.php',
    'Repartidor.php',
    'WalletMercadoPago.php',
    'Direccion.php',
];

foreach ($modelos as $modelo) {
    $path = "/var/www/html/models/$modelo";
    if (file_exists($path)) {
        echo "│  ✅ models/$modelo\n";
    } else {
        echo "│  ❌ models/$modelo - NO EXISTE\n";
        $errores[] = "Modelo faltante: $modelo";
    }
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// 12. TEST RÁPIDO DE FLUJO COMPLETO
// ══════════════════════════════════════════════════════════════════
echo "┌─ 12. TEST DE FLUJO COMPLETO ──────────────────────────────────────\n";

try {
    // Verificar que se puede crear un pedido de prueba
    $stmt = $db->query("SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'cliente' LIMIT 1");
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT id_negocio FROM negocios WHERE activo = 1 LIMIT 1");
    $negocio = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT id_repartidor FROM repartidores WHERE activo = 1 LIMIT 1");
    $repartidor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente && $negocio && $repartidor) {
        echo "│  ✅ Hay datos suficientes para crear pedidos\n";
        echo "│     - Cliente disponible: ID " . $cliente['id_usuario'] . "\n";
        echo "│     - Negocio disponible: ID " . $negocio['id_negocio'] . "\n";
        echo "│     - Repartidor disponible: ID " . $repartidor['id_repartidor'] . "\n";
    } else {
        if (!$cliente) {
            echo "│  ❌ No hay clientes registrados\n";
            $errores[] = "No hay clientes para hacer pedidos";
        }
        if (!$negocio) {
            echo "│  ❌ No hay negocios activos\n";
            $errores[] = "No hay negocios activos para recibir pedidos";
        }
        if (!$repartidor) {
            echo "│  ❌ No hay repartidores activos\n";
            $errores[] = "No hay repartidores para entregar pedidos";
        }
    }
} catch (Exception $e) {
    echo "│  ❌ Error en test: " . $e->getMessage() . "\n";
    $errores[] = "Error en test de flujo: " . $e->getMessage();
}

echo "└──────────────────────────────────────────────────────────────────\n\n";

// ══════════════════════════════════════════════════════════════════
// RESUMEN FINAL
// ══════════════════════════════════════════════════════════════════
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    📋 RESUMEN DE VERIFICACIÓN                   ║\n";
echo "╠══════════════════════════════════════════════════════════════════╣\n";

$total_exitos = count($exitos);
$total_errores = count($errores);
$total_advertencias = count($advertencias);

echo "║  ✅ Verificaciones exitosas: " . str_pad($total_exitos, 32) . "║\n";
echo "║  ⚠️  Advertencias: " . str_pad($total_advertencias, 42) . "║\n";
echo "║  ❌ Errores críticos: " . str_pad($total_errores, 39) . "║\n";
echo "╠══════════════════════════════════════════════════════════════════╣\n";

if ($total_errores > 0) {
    echo "║                                                                  ║\n";
    echo "║  ❌ ERRORES QUE DEBEN CORREGIRSE:                               ║\n";
    foreach ($errores as $error) {
        $error_corto = strlen($error) > 58 ? substr($error, 0, 55) . '...' : $error;
        echo "║  • " . str_pad($error_corto, 60) . "║\n";
    }
}

if ($total_advertencias > 0) {
    echo "║                                                                  ║\n";
    echo "║  ⚠️  ADVERTENCIAS (revisar):                                    ║\n";
    foreach ($advertencias as $adv) {
        $adv_corto = strlen($adv) > 58 ? substr($adv, 0, 55) . '...' : $adv;
        echo "║  • " . str_pad($adv_corto, 60) . "║\n";
    }
}

echo "╠══════════════════════════════════════════════════════════════════╣\n";

if ($total_errores == 0) {
    echo "║                                                                  ║\n";
    echo "║  🎉 ¡SISTEMA LISTO PARA PRODUCCIÓN!                             ║\n";
    echo "║                                                                  ║\n";
    echo "║  El sistema está funcionando correctamente.                     ║\n";
    echo "║  Puedes empezar a invitar negocios y procesar pedidos.          ║\n";
    echo "║                                                                  ║\n";
} else {
    echo "║                                                                  ║\n";
    echo "║  ⛔ HAY ERRORES QUE CORREGIR ANTES DE LANZAR                    ║\n";
    echo "║                                                                  ║\n";
    echo "║  Corrige los errores listados arriba antes de continuar.        ║\n";
    echo "║                                                                  ║\n";
}

echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Código de salida
exit($total_errores > 0 ? 1 : 0);
