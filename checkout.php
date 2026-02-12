<?php
// Manejador de errores centralizado
require_once __DIR__ . '/config/error_handler.php';

session_start();

// Incluir sistema de protección CSRF
require_once __DIR__ . '/config/csrf.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Verificar si hay productos en el carrito
if (!isset($_SESSION['carrito']['items']) || empty($_SESSION['carrito']['items'])) {
    header("location: carrito.php");
    exit;
}

// Incluir configuración y modelos
require_once 'config/database.php';
require_once 'config/env.php';
require_once 'config/quickbite_fees.php'; // Sistema de tarifas y comisiones
require_once 'includes/business_helpers.php';
require_once 'api/WhatsAppLocalClient.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Producto.php';
require_once 'models/Direccion.php';
require_once 'models/MetodoPago.php';
require_once 'models/Pedido.php';

// Conectar a BD
$database = new Database();
$db = $database->getConnection();

// Obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION['id_usuario'];
$usuario->obtenerPorId();

// Verificar si el usuario tiene un pedido pendiente
$pedido = new Pedido($db);
$pedidoPendiente = null;
$pedidosPendientes = $pedido->obtenerPorUsuario($usuario->id_usuario);
foreach ($pedidosPendientes as $p) {
    if ((int)$p['id_estado'] === 1) { // Estado pendiente
        $pedidoPendiente = $p;
        break;
    }
}

if ($pedidoPendiente) {
    echo '<div class="alert alert-warning" role="alert" style="margin: 20px;">';
    echo 'Tienes un pedido en curso. Por favor, ';
    echo '<a href="confirmacion_pedido.php?id=' . htmlspecialchars($pedidoPendiente['id_pedido']) . '">haz clic aquí para ver el estado de tu pedido</a>.';
    echo '</div>';
    // No permitir continuar con nuevo pedido
    exit;
}

// Manejar solicitudes AJAX para direcciones
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_address') {
    header('Content-Type: application/json');

    // Validar CSRF token para AJAX
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    }

    try {
        $direccion = new Direccion($db);
        $direccion->id_usuario = $_SESSION['id_usuario'];
        $direccion->nombre_direccion = trim($_POST['nombre_direccion']);
        $direccion->calle = trim($_POST['calle']);
        $direccion->numero = trim($_POST['numero']);
        $direccion->colonia = trim($_POST['colonia']);
        $direccion->ciudad = trim($_POST['ciudad']);
        $direccion->codigo_postal = trim($_POST['codigo_postal']);
        $direccion->estado = trim($_POST['estado']);
        $direccion->es_predeterminada = isset($_POST['es_predeterminada']) ? 1 : 0;
        
        if ($direccion->crear()) {
            echo json_encode(['success' => true, 'message' => 'Dirección guardada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar la dirección']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ✅ SOLUCIÓN COMPLETA: Verificar y validar el negocio de forma robusta
$negocio = null;
$negocio_id = 0;

logError("🔍 Iniciando validación completa del negocio");

// Función para validar negocio existe y está activo
function validarNegocioExiste($db, $id_negocio) {
    try {
        $stmt = $db->prepare("SELECT id_negocio, nombre, activo, costo_envio, categoria_negocio, metodos_pago_aceptados FROM negocios WHERE id_negocio = ? AND activo = 1");
        $stmt->execute([(int)$id_negocio]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            logError("✅ Negocio validado", $result);
            return $result;
        }
        
        logError("❌ Negocio no encontrado o inactivo", ['id_negocio' => $id_negocio]);
        return false;
        
    } catch (Exception $e) {
        logError("❌ Error validando negocio", ['id_negocio' => $id_negocio, 'error' => $e->getMessage()]);
        return false;
    }
}

// Función para obtener negocio desde productos del carrito con validación estricta
function obtenerNegocioDesdeCarrito($db) {
    if (empty($_SESSION['carrito']['items'])) {
        logError("❌ Carrito vacío");
        return null;
    }
    
    logError("🔍 Buscando negocio desde productos del carrito", [
        'total_items' => count($_SESSION['carrito']['items'])
    ]);
    
    // Obtener todos los productos únicos del carrito de forma más robusta
    $productos_ids = [];
    foreach ($_SESSION['carrito']['items'] as $item) {
        if (isset($item['id_producto']) && $item['id_producto'] > 0) {
            $productos_ids[] = (int)$item['id_producto'];
        }
    }
    $productos_ids = array_unique($productos_ids);
    
    if (empty($productos_ids)) {
        logError("❌ No hay IDs de productos válidos en el carrito");
        return null;
    }

    // Crear placeholders para la consulta de forma segura
    $placeholders = implode(',', array_fill(0, count($productos_ids), '?'));
    
    try {
        // Consulta simplificada y compatible con sql_mode=only_full_group_by
        // Primero obtenemos el id_negocio del primer producto
        $stmt = $db->prepare("
            SELECT DISTINCT n.id_negocio, n.nombre as negocio_nombre, n.activo, n.costo_envio, n.metodos_pago_aceptados
            FROM productos p
            INNER JOIN negocios n ON p.id_negocio = n.id_negocio
            WHERE p.id_producto IN ($placeholders) AND n.activo = 1
            LIMIT 1
        ");
        $stmt->execute($productos_ids);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            logError("✅ Negocio encontrado desde carrito", $resultado);
            return $resultado;
        } else {
            logError("❌ No se encontró negocio activo para los productos del carrito", ['productos_ids' => $productos_ids]);
            return null;
        }
        
    } catch (Exception $e) {
        logError("❌ Error obteniendo negocio desde carrito", ['error' => $e->getMessage(), 'productos_ids' => $productos_ids]);
        return null;
    }
}

// PASO 1: Intentar usar el ID de negocio de la sesión
$negocio_id_session = isset($_SESSION['carrito']['negocio_id']) ? (int)$_SESSION['carrito']['negocio_id'] : 0;

if ($negocio_id_session > 0) {
    logError("🔍 Validando negocio de la sesión", ['negocio_id' => $negocio_id_session]);
    $negocio_data = validarNegocioExiste($db, $negocio_id_session);
    
    if ($negocio_data) {
        $negocio_id = (int)$negocio_data['id_negocio'];

        // Crear objeto negocio y asignar propiedades
        $negocio = new Negocio($db);
        $negocio->id_negocio = $negocio_id;
        $negocio->nombre = $negocio_data['nombre'];
        $negocio->activo = $negocio_data['activo'];
        $negocio->costo_envio = $negocio_data['costo_envio'] ?? 0;
        $negocio->metodos_pago_aceptados = $negocio_data['metodos_pago_aceptados'] ?? 'mp_card,efectivo,spei';

        logError("✅ Negocio de sesión validado exitosamente", ['negocio_id' => $negocio_id, 'nombre' => $negocio->nombre]);
    }
}

// PASO 2: Si no se pudo validar desde sesión, buscar desde productos
if (!$negocio || $negocio_id <= 0) {
    logError("🔍 Negocio de sesión no válido, buscando desde productos...");
    
    $negocio_data = obtenerNegocioDesdeCarrito($db);
    
    if ($negocio_data) {
        $negocio_id = (int)$negocio_data['id_negocio'];

        // Crear objeto negocio y asignar propiedades
        $negocio = new Negocio($db);
        $negocio->id_negocio = $negocio_id;
        $negocio->nombre = $negocio_data['negocio_nombre'];
        $negocio->activo = $negocio_data['activo'];
        $negocio->costo_envio = $negocio_data['costo_envio'] ?? 0;
        $negocio->metodos_pago_aceptados = $negocio_data['metodos_pago_aceptados'] ?? 'mp_card,efectivo,spei';

        // Actualizar sesión con el negocio correcto
        $_SESSION['carrito']['negocio_id'] = $negocio_id;
        $_SESSION['carrito']['negocio_nombre'] = $negocio->nombre;

        logError("✅ Negocio recuperado desde productos", ['negocio_id' => $negocio_id, 'nombre' => $negocio->nombre]);
    }
}

// PASO 3: VALIDACIÓN FINAL CRÍTICA
if (!$negocio || $negocio_id <= 0) {
    logError("❌ ERROR CRÍTICO: No se pudo obtener negocio válido");
    
    // Limpiar carrito corrupto
    $_SESSION['carrito'] = [
        'items' => [],
        'negocio_id' => 0,
        'negocio_nombre' => '',
        'subtotal' => 0,
        'total' => 0
    ];
    
    echo '<div class="alert alert-danger" role="alert" style="margin: 20px;">';
    echo 'Error: No se pudo validar el negocio para tu pedido. El carrito ha sido limpiado.';
    echo '<br><a href="index.php" class="btn btn-primary mt-2">Volver al inicio</a>';
    echo '</div>';
    exit;
}

// PASO 4: Doble verificación que el negocio existe en BD
$stmt_final_check = $db->prepare("SELECT COUNT(*) FROM negocios WHERE id_negocio = ? AND activo = 1");
$stmt_final_check->execute([$negocio_id]);

if ($stmt_final_check->fetchColumn() == 0) {
    logError("❌ ERROR FINAL: Negocio no existe en BD después de validaciones", ['negocio_id' => $negocio_id]);
    
    echo '<div class="alert alert-danger" role="alert" style="margin: 20px;">';
    echo 'Error: El negocio seleccionado ya no está disponible.';
    echo '<br><a href="index.php" class="btn btn-primary mt-2">Seleccionar otro negocio</a>';
    echo '</div>';
    exit;
}

logError("🎉 Negocio validado completamente", [
    'id_negocio' => $negocio_id,
    'nombre' => $negocio->nombre ?? 'Sin nombre',
    'costo_envio' => $negocio->costo_envio ?? 0
]);

// Valores seguros después de validación completa
$costo_envio = isset($negocio->costo_envio) ? (float)$negocio->costo_envio : 0;
$categoria_negocio = isset($negocio->categoria_negocio) ? $negocio->categoria_negocio : 'restaurante';
$direcciones = [];

// Métodos de pago aceptados por el negocio
$metodos_pago_str = isset($negocio->metodos_pago_aceptados) ? $negocio->metodos_pago_aceptados : 'mp_card,efectivo,spei';
if (strpos($metodos_pago_str, 'spei') === false) {
    $metodos_pago_str .= ',spei';
}
$metodos_pago_permitidos = array_map('trim', explode(',', $metodos_pago_str));
logError("💳 Métodos de pago permitidos", ['metodos' => $metodos_pago_permitidos]);

// ✅ OBTENER HORARIOS DEL NEGOCIO PARA VALIDACIÓN DE PICKUP
$horarios_negocio = [];
$negocio_abierto = false;
try {
    $stmt_horarios = $db->prepare("SELECT dia_semana, hora_apertura, hora_cierre, activo FROM negocio_horarios WHERE id_negocio = ? AND activo = 1 ORDER BY dia_semana");
    $stmt_horarios->execute([$negocio_id]);
    $horarios_raw = $stmt_horarios->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir a formato para JavaScript (0=Domingo, 1=Lunes, etc.)
    foreach ($horarios_raw as $h) {
        $horarios_negocio[$h['dia_semana']] = [
            'apertura' => $h['hora_apertura'],
            'cierre' => $h['hora_cierre'],
            'activo' => (bool)$h['activo']
        ];
    }
    
    // Verificar si está abierto ahora
    $dia_actual = (int)date('w'); // 0=Domingo, 1=Lunes, etc.
    $hora_actual = date('H:i:s');
    
    if (isset($horarios_negocio[$dia_actual]) && $horarios_negocio[$dia_actual]['activo']) {
        $apertura = $horarios_negocio[$dia_actual]['apertura'];
        $cierre = $horarios_negocio[$dia_actual]['cierre'];
        $negocio_abierto = ($hora_actual >= $apertura && $hora_actual <= $cierre);
    }
    
    logError("✅ Horarios del negocio obtenidos", ['horarios' => $horarios_negocio, 'abierto' => $negocio_abierto]);
} catch (Exception $e) {
    logError("⚠️ Error obteniendo horarios: " . $e->getMessage());
    // Si no hay horarios, asumimos que está abierto
    $negocio_abierto = true;
}

// Obtener direcciones del usuario
try {
    $direccion = new Direccion($db);
    $direccion->id_usuario = $_SESSION['id_usuario'];
    $direcciones = $direccion->obtenerPorUsuario();
} catch (Exception $e) {
    logError("Error al obtener direcciones: " . $e->getMessage());
}

// Calcular totales
$subtotal = 0;
$impuesto = 0; // Inicializar impuesto para evitar warnings
$cargo_servicio = 0; // SIN CARGO POR SERVICIO - Eliminado para todos
$propina = 15; // Valor por defecto

// ✅ Comisión de procesamiento ELIMINADA
$mp_fee_percentage = 0; // Sin comisión adicional
$mp_fee = 0; // Sin comisión

// Calcular subtotal - usar precio completo sin reducción
foreach ($_SESSION['carrito']['items'] as $item) {
    $precio = isset($item['precio']) ? (float)$item['precio'] : 0;
    $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;

    $subtotal += $precio * $cantidad;
}

// ═══════════════════════════════════════════════════════════════
// ✅ NUEVO SISTEMA DE ENVÍO Y DESCUENTOS POR MEMBRESÍA
// ═══════════════════════════════════════════════════════════════

// Verificar si el usuario es miembro del Club QuickBite
$es_miembro_club = false;
try {
    $stmt_membresia = $db->prepare("SELECT es_miembro, fecha_fin_membresia FROM usuarios WHERE id_usuario = ? AND es_miembro = 1 AND (fecha_fin_membresia IS NULL OR fecha_fin_membresia >= CURDATE())");
    $stmt_membresia->execute([$_SESSION['id_usuario']]);
    $membresia = $stmt_membresia->fetch(PDO::FETCH_ASSOC);
    $es_miembro_club = ($membresia && $membresia['es_miembro'] == 1);
} catch (Exception $e) {
    logError("Error verificando membresía: " . $e->getMessage());
}

// Calcular costo de envío base (del negocio o nuevo sistema)
$costo_envio_base = isset($negocio->costo_envio) ? (float)$negocio->costo_envio : QUICKBITE_ENVIO_BASE_CORTO;

// Si el negocio no tiene costo configurado, usar tarifa base corta
if ($costo_envio_base <= 0) {
    $costo_envio_base = QUICKBITE_ENVIO_BASE_CORTO; // $18
}

// Envío por zonas para Orez Floristería
require_once __DIR__ . '/includes/orez_floreria.php';
if ($negocio_id == OREZ_NEGOCIO_ID && !empty($direcciones)) {
    $dir_seleccionada = null;
    // Si ya se envió el form, usar la dirección seleccionada
    if (!empty($_POST['direccion_id'])) {
        foreach ($direcciones as $d) {
            if ($d['id_direccion'] == $_POST['direccion_id']) {
                $dir_seleccionada = $d;
                break;
            }
        }
    }
    // Fallback: usar dirección predeterminada o la primera
    if (!$dir_seleccionada) {
        foreach ($direcciones as $d) {
            if (!empty($d['es_predeterminada'])) {
                $dir_seleccionada = $d;
                break;
            }
        }
        if (!$dir_seleccionada) {
            $dir_seleccionada = $direcciones[0];
        }
    }
    if ($dir_seleccionada) {
        $costo_envio_base = calcularEnvioOrez(
            $dir_seleccionada['ciudad'] ?? '',
            $dir_seleccionada['colonia'] ?? ''
        );
        logError("🌹 Orez envío por zona", [
            'ciudad' => $dir_seleccionada['ciudad'] ?? '',
            'colonia' => $dir_seleccionada['colonia'] ?? '',
            'costo' => $costo_envio_base
        ]);
    }
}

// Aplicar descuentos de membresía según monto del pedido
$descuento_envio = 'ninguno';
$costo_envio_final = $costo_envio_base;

if ($es_miembro_club) {
    if ($subtotal >= QUICKBITE_ENVIO_GRATIS_MONTO) { // $250+
        $costo_envio_final = 0;
        $descuento_envio = 'gratis';
        logError("✅ Envío GRATIS aplicado (miembro con pedido ≥$" . QUICKBITE_ENVIO_GRATIS_MONTO . ")");
    } elseif ($subtotal >= QUICKBITE_ENVIO_MITAD_MONTO) { // $150+
        $costo_envio_final = round($costo_envio_base * 0.5, 2);
        $descuento_envio = '50%';
        logError("✅ Envío 50% descuento aplicado (miembro con pedido ≥$" . QUICKBITE_ENVIO_MITAD_MONTO . ")");
    }
}

// Usar el costo de envío calculado

// Si el tipo de pedido es pickup, el costo de envío debe ser 0
if ((isset($_POST['tipo_pedido']) && $_POST['tipo_pedido'] === 'pickup') || (isset($pedido) && isset($pedido->tipo_pedido) && $pedido->tipo_pedido === 'pickup')) {
    $costo_envio = 0;
} else {
    $costo_envio = $costo_envio_final;
}

logError("📦 Costo de envío calculado", [
    'base' => $costo_envio_base,
    'final' => $costo_envio_final,
    'es_miembro' => $es_miembro_club,
    'subtotal' => $subtotal,
    'descuento' => $descuento_envio
]);

// ═══════════════════════════════════════════════════════════════
// ✅ CALCULAR AHORRO POTENCIAL CON MEMBRESÍA (Para promoción)
// ═══════════════════════════════════════════════════════════════
$ahorro_potencial_envio = 0;
$mostrar_promo_membresia = false;

if (!$es_miembro_club && $subtotal >= QUICKBITE_ENVIO_MITAD_MONTO) {
    // Calcular cuánto ahorraría si fuera miembro
    if ($subtotal >= QUICKBITE_ENVIO_GRATIS_MONTO) {
        // Ahorraría todo el envío
        $ahorro_potencial_envio = $costo_envio_base;
    } else {
        // Ahorraría 50% del envío
        $ahorro_potencial_envio = round($costo_envio_base * 0.5, 2);
    }
    $mostrar_promo_membresia = ($ahorro_potencial_envio > 0);
}

// Precio de membresía para mostrar
$precio_membresia_club = QUICKBITE_CLUB_PRECIO;

// Variables para mensajes de error
$direccion_err = $metodo_pago_err = $propina_err = $general_err = "";

// Procesar pedido cuando se envíe el formulario (ACTUALIZADO para incluir comisión)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    logError("Formulario enviado. Procesando pedido...");
    
    // Validar token CSRF antes de procesar
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $general_err = "Error de seguridad: Token CSRF inválido. Por favor recarga la página.";
        logError("❌ Token CSRF inválido");
    } else {
    
    // ✅ RE-VALIDAR NEGOCIO ANTES DE PROCESAR (CRÍTICO)
    $negocio_revalidado = validarNegocioExiste($db, $negocio_id);
    if (!$negocio_revalidado) {
        $general_err = "El negocio seleccionado ya no está disponible. Por favor selecciona otro.";
        logError("❌ Negocio no válido al procesar pedido", ['negocio_id' => $negocio_id]);
    } else {
        // Actualizar datos del negocio
        $negocio->id_negocio = (int)$negocio_revalidado['id_negocio'];
        $negocio->nombre = $negocio_revalidado['nombre'];
        $negocio->costo_envio = $negocio_revalidado['costo_envio'] ?? 0;
        $costo_envio = (float)$negocio->costo_envio;

        // Re-aplicar envío por zonas para Orez
        if ($negocio_id == OREZ_NEGOCIO_ID && !empty($_POST['direccion_id']) && !empty($direcciones)) {
            foreach ($direcciones as $d) {
                if ($d['id_direccion'] == $_POST['direccion_id']) {
                    $costo_envio = calcularEnvioOrez(
                        $d['ciudad'] ?? '',
                        $d['colonia'] ?? ''
                    );
                    break;
                }
            }
        }

        logError("✅ Negocio re-validado para procesamiento", $negocio_revalidado);
    }
    
    // Validar dirección seleccionada
    if (empty($general_err) && (empty($_POST["direccion_id"]) || !isset($_POST["direccion_id"]))) {
        $direccion_err = "Por favor selecciona una dirección de entrega.";
    } else if (empty($general_err)) {
        $id_direccion = trim($_POST["direccion_id"]);
        
        // ✅ VALIDAR QUE LA DIRECCIÓN EXISTE Y PERTENECE AL USUARIO
        $stmt_dir = $db->prepare("SELECT COUNT(*) FROM direcciones_usuario WHERE id_direccion = ? AND id_usuario = ?");
        $stmt_dir->execute([(int)$id_direccion, (int)$_SESSION['id_usuario']]);
        
        if ($stmt_dir->fetchColumn() == 0) {
            $direccion_err = "Dirección no válida o no pertenece al usuario.";
            logError("❌ Dirección no válida", ['id_direccion' => $id_direccion, 'id_usuario' => $_SESSION['id_usuario']]);
        } else {
            logError("✅ Dirección validada", ['id_direccion' => $id_direccion]);
        }
    }
    
    // Validar método de pago seleccionado
    if (empty($general_err) && (empty($_POST["payment_method"]) || !isset($_POST["payment_method"]))) {
        $metodo_pago_err = "Por favor selecciona un método de pago.";
    } else if (empty($general_err)) {
        $metodo_pago_seleccionado = trim($_POST["payment_method"]);
        
        // ✅ NUEVO: Calcular comisión de MercadoPago si es pago con tarjeta
        if (in_array($metodo_pago_seleccionado, ['mp_card', 'google_pay', 'apple_pay'])) {
            $base_total = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto;
            $mp_fee = max(0, $base_total * $mp_fee_percentage); // Asegurar que no sea negativo
            logError("Comisión de MercadoPago calculada: $" . number_format($mp_fee, 2) . " para método: " . $metodo_pago_seleccionado);
        } else {
            $mp_fee = 0;
        }
    }
    
    // Validar propina
    if (empty($general_err) && isset($_POST["propina"])) {
        $propina = floatval($_POST["propina"]);
        if ($propina < 0) {
            $propina_err = "La propina no puede ser negativa.";
        }
        
        // ✅ RECALCULAR COMISIÓN SI CAMBIA LA PROPINA
        if (in_array($metodo_pago_seleccionado, ['mp_card', 'google_pay', 'apple_pay'])) {
            $base_total = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto;
            $mp_fee = max(0, $base_total * $mp_fee_percentage); // Asegurar que no sea negativo
        }
    }
    
    // Procesar pedido si no hay errores
    if (empty($direccion_err) && empty($metodo_pago_err) && empty($propina_err) && empty($general_err)) {
        // ✅ CALCULAR TOTAL FINAL INCLUYENDO COMISIÓN DE MERCADOPAGO
        $total = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto + $mp_fee;
        
        logError("Total calculado: Subtotal=$subtotal, Envío=$costo_envio, Propina=$propina, Servicio=$cargo_servicio, Impuesto=$impuesto, MercadoPago Fee=$mp_fee, Total=$total");
        
        $payment_processed = false;
        $payment_details = [];
        
        // Procesar según el método de pago seleccionado
        switch ($metodo_pago_seleccionado) {
            case 'mp_card':
                if (isset($_POST['mp_payment_id']) || isset($_POST['payment_intent_id'])) {
                    try {
                        $payment_id = $_POST['mp_payment_id'] ?? $_POST['payment_intent_id'];
                        $mp_status = $_POST['mp_status'] ?? 'approved';
                        
                        // Verificar si es un pago real de MercadoPago o simulado
                        if (strpos($payment_id, 'pi_') === 0 || strpos($payment_id, 'dev_') === 0) {
                            // PAGO SIMULADO - Aceptar como válido para desarrollo
                            logError("Pago simulado detectado: " . $payment_id);
                            $payment_processed = true;
                            $payment_details = [
                                'method' => 'mercadopago',
                                'payment_id' => $payment_id,
                                'amount' => $total,
                                'currency' => 'mxn',
                                'mp_fee' => $mp_fee,
                                'status' => $mp_status
                            ];
                        } else {
                            // PAGO REAL de MercadoPago
                            logError("Pago MercadoPago procesado: " . $payment_id);
                            $payment_processed = true;
                            $payment_details = [
                                'method' => 'mercadopago',
                                'payment_id' => $payment_id,
                                'amount' => $total,
                                'currency' => 'mxn',
                                'mp_fee' => $mp_fee,
                                'status' => $mp_status
                            ];
                        }
                    } catch (Exception $e) {
                        // Usar función helper para mensaje amigable
                        $error_message = $e->getMessage();
                        $error_code = '';
                        
                        // Extraer código de error si está presente en el mensaje
                        if (preg_match('/\b(diff_param_bins|cc_rejected_\w+|invalid_\w+)\b/i', $error_message, $matches)) {
                            $error_code = $matches[1];
                        }
                        
                        $general_err = formatPaymentError($error_code, $error_message);
                        logError("Error en MercadoPago: " . $e->getMessage());
                    }
                } else {
                    $general_err = "No se recibió la información del pago.";
                }
                break;
                
            case 'paypal':
                // Pago con PayPal (sin comisión adicional)
                if (isset($_POST['paypal_order_id'])) {
                    $payment_processed = true;
                    $payment_details = [
                        'method' => 'paypal',
                        'paypal_order_id' => $_POST['paypal_order_id'],
                        'mp_fee' => 0 // PayPal no tiene comisión adicional
                    ];
                    logError("Pago PayPal procesado. Order ID: " . $_POST['paypal_order_id']);
                } else {
                    $general_err = "No se recibió la confirmación de PayPal.";
                }
                break;
                
            case 'efectivo':
                // Pago en efectivo (sin comisión)
                $total_sin_comision = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto; // Sin comisión de MercadoPago
                // Modo temporal: el cliente pagará 50,000
                $monto_efectivo = 50000;
                
                if ($monto_efectivo < $total_sin_comision) {
                    $general_err = "El monto en efectivo debe ser igual o mayor al total del pedido.";
                } else {
                    $payment_processed = true;
                    $payment_details = [
                        'method' => 'efectivo',
                        'monto_efectivo' => $monto_efectivo,
                        'mp_fee' => 0 // Efectivo no tiene comisión
                    ];
                    $mp_fee = 0; // Asegurar que mp_fee sea 0 para efectivo
                    $total = $total_sin_comision; // Actualizar total sin comisión para efectivo
                    logError("Pago en efectivo procesado. Monto: $" . number_format($monto_efectivo, 2));
                }
                break;

            case 'spei':
                // Pago SPEI - Pedido queda en estado pendiente_pago hasta confirmar transferencia
                $total_sin_comision = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto;
                $payment_processed = true;
                $es_pago_spei = true; // Flag para estado pendiente_pago
                $payment_details = [
                    'method' => 'spei',
                    'mp_fee' => 0,
                    'status' => 'pending'
                ];
                $mp_fee = 0;
                $total = $total_sin_comision;
                logError("Pago SPEI seleccionado. Total: $" . number_format($total, 2));
                break;

            default:
                $general_err = "Método de pago no válido.";
                break;
        }
        
        // Variable para trackear si la transacción fue iniciada
        $transactionStarted = false;
        
        if ($payment_processed) {
            try {
                // Iniciar transacción si es posible
                if (method_exists($db, 'beginTransaction')) {
                    $db->beginTransaction();
                    $transactionStarted = true;
                }
                
                logError("Iniciando creación de pedido con pago procesado", [
                    'metodo_pago' => $metodo_pago_seleccionado,
                    'mp_fee' => $mp_fee,
                    'total_final' => $total,
                    'negocio_id' => $negocio_id
                ]);

                // Verificar si el usuario califica para pedido y envío gratis por programa de referidos
                try {
                    if (file_exists('api/Referral.php') && file_exists('models/Membership.php')) {
                        require_once 'api/Referral.php';
                        require_once 'models/Membership.php';
                        $referral = new Referral($db);
                        $userId = $_SESSION['id_usuario'];
                        $countQualifiedReferrals = $referral->countReferralsWithPurchasesOrMembership($userId);

                        if ($countQualifiedReferrals >= 2) {
                            $costo_envio = 0;
                            $cargo_servicio = 0;
                            // Recalcular total si se aplicaron descuentos
                            if (in_array($metodo_pago_seleccionado, ['mp_card', 'google_pay', 'apple_pay'])) {
                                $base_total = $subtotal + $impuesto + $propina;
                                $mp_fee = max(0, $base_total * $mp_fee_percentage); // Asegurar que no sea negativo
                                $total = $base_total + $mp_fee;
                            } else {
                                $total = $subtotal + $impuesto + $propina;
                            }
                            logError("Aplicando descuentos por referidos", [
                                'descuento_aplicado' => true,
                                'nuevo_total' => $total,
                                'mp_fee_ajustado' => $mp_fee
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    logError("Error aplicando descuentos por referidos (continuando sin descuento)", ['error' => $e->getMessage()]);
                }

                // ✅ CREAR EL PEDIDO CON VALIDACIONES FINALES CRÍTICAS
                
                // TRIPLE VALIDACIÓN ANTES DE CREAR EL PEDIDO
                logError("🔍 Triple validación antes de crear pedido", [
                    'usuario_id' => $_SESSION['id_usuario'],
                    'negocio_id' => $negocio_id,
                    'direccion_id' => $id_direccion
                ]);
                
                // Validar usuario existe
                $stmt_user = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_usuario = ?");
                $stmt_user->execute([(int)$_SESSION['id_usuario']]);
                if ($stmt_user->fetchColumn() == 0) {
                    throw new Exception("Usuario no válido");
                }
                
                // Validar negocio existe y está activo
                $stmt_neg = $db->prepare("SELECT COUNT(*) FROM negocios WHERE id_negocio = ? AND activo = 1");
                $stmt_neg->execute([(int)$negocio_id]);
                if ($stmt_neg->fetchColumn() == 0) {
                    throw new Exception("Negocio no válido o inactivo: ID $negocio_id");
                }
                
                // Validar dirección existe y pertenece al usuario
                $stmt_dir = $db->prepare("SELECT COUNT(*) FROM direcciones_usuario WHERE id_direccion = ? AND id_usuario = ?");
                $stmt_dir->execute([(int)$id_direccion, (int)$_SESSION['id_usuario']]);
                if ($stmt_dir->fetchColumn() == 0) {
                    throw new Exception("Dirección no válida");
                }
                
                // Validar estado pedido existe
                $stmt_est = $db->prepare("SELECT COUNT(*) FROM estados_pedido WHERE id_estado = 1");
                $stmt_est->execute();
                if ($stmt_est->fetchColumn() == 0) {
                    throw new Exception("Estado de pedido no válido");
                }
                
                logError("✅ Triple validación exitosa, procediendo a crear pedido");

                $pedido = new Pedido($db);

                // Asignar propiedades con validación estricta
                $pedido->id_usuario = (int)$_SESSION['id_usuario'];
                $pedido->id_negocio = (int)$negocio_id;
                // SPEI/Efectivo: estado 7 (pendiente_pago), otros: estado 1 (pendiente)
                $pedido->id_estado = in_array($metodo_pago_seleccionado, ['spei', 'efectivo']) ? 7 : 1;
                $pedido->id_direccion = (int)$id_direccion;
                $pedido->id_repartidor = null; // Inicialmente sin repartidor

                // Asignar id_metodo_pago correctamente
                if ($metodo_pago_seleccionado === 'efectivo') {
                    $pedido->id_metodo_pago = null;
                } elseif (in_array($metodo_pago_seleccionado, ['mp_card', 'google_pay', 'apple_pay'])) {
                    // Intentar obtener método de pago predeterminado del usuario
                    try {
                        require_once 'models/MetodoPago.php';
                        $metodoPagoModel = new MetodoPago($db);
                        $metodoPagoModel->id_usuario = (int)$_SESSION['id_usuario'];
                        if ($metodoPagoModel->obtenerPredeterminado()) {
                            $pedido->id_metodo_pago = (int)$metodoPagoModel->id_metodo_pago;
                        } else {
                            $pedido->id_metodo_pago = null;
                        }
                    } catch (Exception $e) {
                        logError("Error obteniendo método de pago predeterminado: " . $e->getMessage());
                        $pedido->id_metodo_pago = null;
                    }
                } else {
                    $pedido->id_metodo_pago = null;
                }

                $pedido->metodo_pago = $metodo_pago_seleccionado;
                $pedido->total_productos = (float)$subtotal;
                $pedido->impuestos = (float)$impuesto;
                $pedido->costo_envio = (float)$costo_envio;
                $pedido->cargo_servicio = (float)$cargo_servicio;
                $pedido->propina = (float)$propina;
                $pedido->monto_total = (float)$total;
                $pedido->tipo_pedido = isset($_POST["tipo_pedido"]) ? htmlspecialchars(trim($_POST["tipo_pedido"]), ENT_QUOTES, 'UTF-8') : 'delivery';
                $pedido->pickup_time = isset($_POST["pickup_time"]) && !empty($_POST["pickup_time"])
                    ? htmlspecialchars(trim($_POST["pickup_time"]), ENT_QUOTES, 'UTF-8')
                    : null;

                // ✅ PEDIDO PROGRAMADO
                $pedido->es_programado = isset($_POST["es_programado"]) && $_POST["es_programado"] === '1' ? 1 : 0;
                $pedido->fecha_programada = null;
                if ($pedido->es_programado && isset($_POST["fecha_hora_programada"]) && !empty($_POST["fecha_hora_programada"])) {
                    $pedido->fecha_programada = htmlspecialchars(trim($_POST["fecha_hora_programada"]), ENT_QUOTES, 'UTF-8');
                }

                // ✅ INSTRUCCIONES ESPECIALES MEJORADAS
                $instrucciones = [];
                if (isset($_POST["tipo_pedido"])) {
                    $tipo_texto = $_POST["tipo_pedido"] === "pickup" ? "PickUp (Recoger en tienda)" : "Delivery (Envío a domicilio)";
                    $instrucciones[] = "Tipo de pedido: " . $tipo_texto;
                }

                // Agregar info de pedido programado
                if ($pedido->es_programado && $pedido->fecha_programada) {
                    $fecha_formateada = date('d/m/Y H:i', strtotime($pedido->fecha_programada));
                    $instrucciones[] = "PEDIDO PROGRAMADO para: " . $fecha_formateada;
                }

                // ✅ OPCIONES DE REGALO
                $es_regalo = isset($_POST["es_regalo"]) && $_POST["es_regalo"] == '1';
                $modo_anonimo = isset($_POST["modo_anonimo"]) && $_POST["modo_anonimo"] == '1';
                $nombre_remitente = isset($_POST["nombre_remitente"]) ? trim($_POST["nombre_remitente"]) : '';
                $mensaje_regalo = isset($_POST["mensaje_regalo"]) ? trim($_POST["mensaje_regalo"]) : '';
                $tipo_envoltura = isset($_POST["tipo_envoltura"]) ? trim($_POST["tipo_envoltura"]) : 'normal';

                if ($es_regalo) {
                    $instrucciones[] = "🎁 ES UN REGALO";

                    if ($modo_anonimo) {
                        $instrucciones[] = "👤 ENVÍO ANÓNIMO (no revelar remitente)";
                    } elseif (!empty($nombre_remitente)) {
                        $instrucciones[] = "De parte de: " . htmlspecialchars($nombre_remitente, ENT_QUOTES, 'UTF-8');
                    }

                    if (!empty($mensaje_regalo)) {
                        $instrucciones[] = "💌 MENSAJE: " . htmlspecialchars($mensaje_regalo, ENT_QUOTES, 'UTF-8');
                    }

                    if ($tipo_envoltura !== 'normal') {
                        $tipo_texto_envoltura = $tipo_envoltura === 'regalo' ? 'Presentación para regalo' : 'Entrega sorpresa';
                        $instrucciones[] = "🎀 " . $tipo_texto_envoltura;
                    }
                }

                if (isset($_POST["instrucciones"]) && !empty(trim($_POST["instrucciones"]))) {
                    $instrucciones[] = "📝 " . trim($_POST["instrucciones"]);
                }

                // ✅ AGREGAR INFORMACIÓN DE COMISIÓN A LAS INSTRUCCIONES
                if ($mp_fee > 0) {
                    $instrucciones[] = "Comisión de procesamiento de tarjeta: $" . number_format($mp_fee, 2);
                }

                $pedido->instrucciones_especiales = implode(". ", $instrucciones);
                
                // ✅ PAYMENT DETAILS COMO JSON STRING CON MERCADOPAGO FEE
                $payment_details = $payment_details ?? [];
                $payment_details['mp_fee'] = $mp_fee;
                $pedido->payment_details = json_encode($payment_details);

                // Si es pago en efectivo, guardar el monto
                if ($metodo_pago_seleccionado === 'efectivo') {
                    $pedido->monto_efectivo = (float)($payment_details['monto_efectivo'] ?? $total);
                } else {
                    $pedido->monto_efectivo = 0.00;
                }

                // Campos adicionales requeridos por la estructura de la tabla
                $pedido->tiempo_entrega_estimado = date('Y-m-d H:i:s', strtotime('+45 minutes')); // Estimado 45 min
                $pedido->tiempo_entrega_real = '0000-00-00 00:00:00'; // Por defecto
                $pedido->fecha_creacion = date('Y-m-d H:i:s');
                $pedido->fecha_entrega = null;
                $pedido->tiempo_entrega = null;
                $pedido->ganancia = null; // Se calculará después
                $pedido->fecha_actualizacion = date('Y-m-d H:i:s');

                logError("Datos del pedido preparados", [
                    'id_usuario' => $pedido->id_usuario,
                    'id_negocio' => $pedido->id_negocio,
                    'id_direccion' => $pedido->id_direccion,
                    'metodo_pago' => $pedido->metodo_pago,
                    'monto_total' => $pedido->monto_total,
                    'mp_fee' => $mp_fee,
                    'id_metodo_pago' => $pedido->id_metodo_pago
                ]);

                // ✅ CREAR EL PEDIDO
                $resultado = $pedido->crear();
                
                if ($resultado === true) {
                    logError("Pedido creado exitosamente", [
                        'id_pedido' => $pedido->id_pedido,
                        'total_con_mp_fee' => $total
                    ]);
                    
                    // ✅ AGREGAR DETALLES DEL PEDIDO
                    $allDetailsAdded = true;
                    $detalles_agregados = 0;
                    
                    foreach ($_SESSION['carrito']['items'] as $item) {
                        // Validar item antes de agregar
                        if (!isset($item['id_producto']) || !isset($item['cantidad']) || !isset($item['precio'])) {
                            logError("Item del carrito incompleto", ['item' => $item]);
                            continue;
                        }
                        
                        // Verificar que el producto existe y pertenece al negocio correcto
                        $stmt_prod = $db->prepare("SELECT COUNT(*) FROM productos WHERE id_producto = ? AND id_negocio = ?");
                        $stmt_prod->execute([(int)$item['id_producto'], (int)$negocio_id]);
                        
                        if ($stmt_prod->fetchColumn() == 0) {
                            logError("Producto no pertenece al negocio", [
                                'id_producto' => $item['id_producto'],
                                'id_negocio' => $negocio_id
                            ]);
                            continue;
                        }
                        
                        $item_subtotal = (float)$item['cantidad'] * (float)$item['precio'];
                        
                        $added = $pedido->agregarDetalle(
                            $pedido->id_pedido,
                            (int)$item['id_producto'],
                            (int)$item['cantidad'],
                            (float)$item['precio'],
                            $item_subtotal
                        );
                        
                        if ($added) {
                            $detalles_agregados++;
                            logError("Detalle agregado", [
                                'producto' => $item['id_producto'],
                                'cantidad' => $item['cantidad'],
                                'precio' => $item['precio']
                            ]);
                        } else {
                            logError("Error agregando detalle", ['item' => $item]);
                            $allDetailsAdded = false;
                            break;
                        }
                    }

                    if ($allDetailsAdded && $detalles_agregados > 0) {
                        // ✅ CONFIRMAR TRANSACCIÓN
                        if ($transactionStarted && method_exists($db, 'commit')) {
                            $db->commit();
                            $transactionStarted = false;
                        }

                        logError("Pedido completado exitosamente", [
                            'id_pedido' => $pedido->id_pedido,
                            'detalles_agregados' => $detalles_agregados,
                            'mp_fee_aplicado' => $mp_fee,
                            'metodo_pago' => $metodo_pago_seleccionado
                        ]);

                        // Actualizar estado de referido si aplica
                        try {
                            if (isset($referral)) {
                                $referral->markOrderMade($userId);
                            }
                        } catch (Exception $e) {
                            logError("Error actualizando referido (no crítico)", ['error' => $e->getMessage()]);
                        }

                        // ✅ ENVIAR NOTIFICACIÓN POR WHATSAPP AL RESTAURANTE
                        try {
                            // Obtener teléfono del negocio desde la BD
                            $stmt_telefono = $db->prepare("SELECT telefono FROM negocios WHERE id_negocio = ?");
                            $stmt_telefono->execute([$negocio_id]);
                            $telefono_negocio = $stmt_telefono->fetchColumn() ?: '';
                            
                            logError("📞 Teléfono del negocio obtenido", ['telefono' => $telefono_negocio]);
                            if (!empty($telefono_negocio)) {
                                // Obtener información de la dirección
                                $direccion_info = "";
                                foreach ($direcciones as $dir) {
                                    if ($dir['id_direccion'] == $id_direccion) {
                                        $direccion_info = $dir['calle'] . " " . $dir['numero'] . ", " . $dir['colonia'] . ", " . $dir['ciudad'];
                                        break;
                                    }
                                }
                                
                                $mensaje = "🍕 *NUEVO PEDIDO RECIBIDO* 🍕\n\n";
                                $mensaje .= "📋 *Pedido #" . $pedido->id_pedido . "*\n";
                                $mensaje .= "👤 *Cliente:* " . $usuario->nombre . " " . ($usuario->apellido ?? '') . "\n";
                                $mensaje .= "💰 *Total:* $" . number_format($pedido->monto_total, 2) . "\n";

                                // ✅ INFORMACIÓN DE REGALO DESTACADA
                                if ($es_regalo) {
                                    $mensaje .= "\n🎁 *¡ES UN REGALO!*\n";
                                    if ($modo_anonimo) {
                                        $mensaje .= "👤 _Envío anónimo - No revelar remitente_\n";
                                    } elseif (!empty($nombre_remitente)) {
                                        $mensaje .= "💝 *De parte de:* " . htmlspecialchars($nombre_remitente) . "\n";
                                    }
                                    if (!empty($mensaje_regalo)) {
                                        $mensaje .= "💌 *Mensaje:* \"" . htmlspecialchars($mensaje_regalo) . "\"\n";
                                    }
                                    if ($tipo_envoltura !== 'normal') {
                                        $emoji_envoltura = $tipo_envoltura === 'sorpresa' ? '🎊' : '🎀';
                                        $texto_envoltura = $tipo_envoltura === 'regalo' ? 'Presentación para regalo' : 'Entrega sorpresa';
                                        $mensaje .= $emoji_envoltura . " *Presentación:* " . $texto_envoltura . "\n";
                                    }
                                    $mensaje .= "\n";
                                }

                                // ✅ AGREGAR INFORMACIÓN DE COMISIÓN SI APLICA
                                if ($mp_fee > 0) {
                                    $mensaje .= "💳 *Comisión procesamiento:* $" . number_format($mp_fee, 2) . "\n";
                                }

                                $mensaje .= "📦 *Tipo:* " . (isset($_POST["tipo_pedido"]) && $_POST["tipo_pedido"] === "pickup" ? "PickUp (Recoger en tienda)" : "Delivery (Envío a domicilio)") . "\n";

                                if (isset($_POST["pickup_time"]) && !empty($_POST["pickup_time"])) {
                                    $mensaje .= "⏰ *Hora para recoger:* " . htmlspecialchars(trim($_POST["pickup_time"])) . "\n";
                                }

                                if (!empty($direccion_info)) {
                                    $mensaje .= "📍 *Dirección:* " . $direccion_info . "\n";
                                }

                                // Solo mostrar instrucciones si hay algo aparte de la info del regalo
                                if (isset($_POST["instrucciones"]) && !empty(trim($_POST["instrucciones"]))) {
                                    $mensaje .= "📝 *Instrucciones:* " . trim($_POST["instrucciones"]) . "\n";
                                }
                                
                                $mensaje .= "🔗 *Ver pedido:* https://" . $_SERVER['HTTP_HOST'] . "/admin/pedidos.php\n\n";
                                $mensaje .= "✅ *Confirma recibido respondiendo este mensaje*";

                                // Enviar notificación por WhatsApp al restaurante
                                try {
                                    $whatsapp = new WhatsAppLocalClient();
                                    $telefono_formateado = preg_replace('/[^0-9]/', '', $telefono_negocio);

                                    if (in_array($metodo_pago_seleccionado, ['spei', 'efectivo'])) {
                                        // Transferencia SPEI (efectivo se usa como placeholder para SPEI)
                                        $tipo_pago_texto = 'TRANSFERENCIA SPEI';
                                        $es_pickup = isset($_POST["tipo_pedido"]) && $_POST["tipo_pedido"] === "pickup";

                                        $msg = "🏦 *NUEVO PEDIDO POR {$tipo_pago_texto}*\n\n";
                                        $msg .= "📋 *Pedido #" . $pedido->id_pedido . "*\n";
                                        $msg .= "👤 *Cliente:* " . ($usuario->nombre ?? 'Cliente') . " " . ($usuario->apellido ?? '') . "\n";
                                        $msg .= "💰 *Total:* $" . number_format($pedido->monto_total, 2) . "\n";
                                        $msg .= "💳 *Método:* " . $tipo_pago_texto . "\n";
                                        $msg .= "📦 *Tipo:* " . ($es_pickup ? "PickUp (Recoger en tienda)" : "Delivery (Envío a domicilio)") . "\n";

                                        // Pickup time
                                        if ($es_pickup && isset($_POST["pickup_time"]) && !empty($_POST["pickup_time"])) {
                                            $msg .= "⏰ *Hora para recoger:* " . htmlspecialchars(trim($_POST["pickup_time"])) . "\n";
                                        }

                                        // Pedido programado
                                        if ($pedido->es_programado && $pedido->fecha_programada) {
                                            $fecha_prog = date('d/m/Y H:i', strtotime($pedido->fecha_programada));
                                            $msg .= "📅 *PEDIDO PROGRAMADO para:* " . $fecha_prog . "\n";
                                        }

                                        // Dirección de entrega
                                        if (!$es_pickup && !empty($direccion_info)) {
                                            $msg .= "📍 *Dirección:* " . $direccion_info . "\n";
                                        }

                                        // Información de regalo
                                        if ($es_regalo) {
                                            $msg .= "\n🎁 *¡ES UN REGALO!*\n";
                                            if ($modo_anonimo) {
                                                $msg .= "👤 _Envío anónimo - No revelar remitente_\n";
                                            } elseif (!empty($nombre_remitente)) {
                                                $msg .= "💝 *De parte de:* " . htmlspecialchars($nombre_remitente) . "\n";
                                            }
                                            if (!empty($mensaje_regalo)) {
                                                $msg .= "💌 *Mensaje:* \"" . htmlspecialchars($mensaje_regalo) . "\"\n";
                                            }
                                            if ($tipo_envoltura !== 'normal') {
                                                $emoji_env = $tipo_envoltura === 'sorpresa' ? '🎊' : '🎀';
                                                $texto_env = $tipo_envoltura === 'regalo' ? 'Presentación para regalo' : 'Entrega sorpresa';
                                                $msg .= $emoji_env . " *Presentación:* " . $texto_env . "\n";
                                            }
                                        }

                                        // Productos del pedido
                                        if (!empty($_SESSION['carrito']['items'])) {
                                            $msg .= "\n🛒 *Productos:*\n";
                                            foreach ($_SESSION['carrito']['items'] as $item) {
                                                $nombre_prod = $item['nombre'] ?? 'Producto';
                                                $cant = $item['cantidad'] ?? 1;
                                                $precio = $item['precio'] ?? 0;
                                                $msg .= "  • {$cant}x {$nombre_prod} - $" . number_format($precio * $cant, 2) . "\n";
                                            }
                                        }

                                        // Instrucciones especiales
                                        if (isset($_POST["instrucciones"]) && !empty(trim($_POST["instrucciones"]))) {
                                            $msg .= "\n📝 *Instrucciones:* " . trim($_POST["instrucciones"]) . "\n";
                                        }

                                        $msg .= "\n━━━━━━━━━━━━━━━━━━━━━━━━\n";
                                        $msg .= "⏳ *El cliente realizará una transferencia SPEI.*\n";
                                        $msg .= "Revisa tu cuenta bancaria.\n\n";
                                        $msg .= "*Cuando verifiques que recibiste el pago, responde:*\n\n";
                                        $msg .= "✅ *recibido*\n";
                                        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━";

                                        $resultado = $whatsapp->sendMessage($telefono_formateado, $msg);
                                    } else {
                                        // Otros métodos: envío normal con botones
                                        $resultado = $whatsapp->sendOrderNotification(
                                            $telefono_formateado,
                                            $pedido->id_pedido,
                                            'pendiente',
                                            $pedido->monto_total,
                                            $usuario->nombre ?? 'Cliente',
                                            $negocio->nombre,
                                            1
                                        );
                                    }

                                    logError("WhatsApp enviado al restaurante", [
                                        'telefono' => $telefono_formateado,
                                        'pedido_id' => $pedido->id_pedido,
                                        'metodo_pago' => $metodo_pago_seleccionado,
                                        'success' => $resultado['success'] ?? false
                                    ]);
                                } catch (Exception $e) {
                                    logError("Error enviando WhatsApp al restaurante (no crítico)", [
                                        'error' => $e->getMessage(),
                                        'pedido_id' => $pedido->id_pedido
                                    ]);
                                }
                                
                                // Enviar notificación al cliente (solo si NO es SPEI/efectivo, ya que esos se notifican al confirmar pago)
                                if (!in_array($metodo_pago_seleccionado, ['spei', 'efectivo'])) {
                                    try {
                                        if (!empty($usuario->telefono)) {
                                            $telefono_cliente = preg_replace('/[^0-9]/', '', $usuario->telefono);

                                            $msg_cliente = "✅ *¡Pedido recibido!*\n\n"
                                                . "📋 *Pedido #" . $pedido->id_pedido . "*\n"
                                                . "🏪 *" . $negocio->nombre . "*\n"
                                                . "💰 *Total:* $" . number_format($pedido->monto_total, 2) . "\n\n"
                                                . "Tu pedido ha sido enviado al negocio. Te notificaremos cuando sea aceptado.";

                                            $resultado_cliente = $whatsapp->sendMessage($telefono_cliente, $msg_cliente);

                                            logError("WhatsApp enviado al cliente", [
                                                'telefono' => $telefono_cliente,
                                                'pedido_id' => $pedido->id_pedido,
                                                'success' => $resultado_cliente['success'] ?? false
                                            ]);
                                        }
                                    } catch (Exception $e) {
                                        logError("Error enviando WhatsApp al cliente (no crítico)", [
                                            'error' => $e->getMessage()
                                        ]);
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            logError("Error generando WhatsApp (no crítico)", ['error' => $e->getMessage()]);
                        }

                        // ✅ LIMPIAR EL CARRITO
                        $_SESSION['carrito'] = [
                            'items' => [],
                            'negocio_id' => 0,
                            'negocio_nombre' => '',
                            'subtotal' => 0,
                            'total' => 0
                        ];

                        // ✅ REDIRIGIR A CONFIRMACIÓN
                        $mensaje_exito = "¡Pedido creado exitosamente! ID: " . $pedido->id_pedido;

                        // Si es pago SPEI, crear el pago y mostrar instrucciones
                        if ($metodo_pago_seleccionado === 'spei') {
                            try {
                                require_once __DIR__ . '/models/SPEIPaymentService.php';

                                // Obtener conexión mysqli
                                $mysqli = new mysqli(
                                    env('DB_HOST', 'localhost'),
                                    env('DB_USER', 'root'),
                                    env('DB_PASS', ''),
                                    env('DB_NAME', 'quickbite'),
                                    env('DB_PORT', 3306)
                                );
                                $mysqli->set_charset('utf8mb4');

                                $speiService = new SPEIPaymentService($mysqli);
                                $speiResult = $speiService->createSPEIPayment([
                                    'pedido_id' => $pedido->id_pedido,
                                    'amount' => $total,
                                    'email' => $_SESSION['usuario']['email'] ?? 'cliente@quickbite.com.mx',
                                    'first_name' => $_SESSION['usuario']['nombre'] ?? 'Cliente',
                                    'last_name' => $_SESSION['usuario']['apellido'] ?? 'QuickBite'
                                ]);

                                $mysqli->close();

                                if ($speiResult['success']) {
                                    // Redirigir a confirmación con parámetro SPEI
                                    header("Location: confirmacion_pedido.php?id=" . $pedido->id_pedido . "&spei=1");
                                    exit;
                                } else {
                                    logError("Error creando pago SPEI", $speiResult);
                                    // Aún así redirigir a confirmación
                                    header("Location: confirmacion_pedido.php?id=" . $pedido->id_pedido . "&spei_error=1");
                                    exit;
                                }
                            } catch (Exception $speiEx) {
                                logError("Excepción en pago SPEI: " . $speiEx->getMessage());
                                header("Location: confirmacion_pedido.php?id=" . $pedido->id_pedido);
                                exit;
                            }
                        }

                        // Usar JavaScript para redirección después de mostrar mensaje
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'confirmacion_pedido.php?id=" . $pedido->id_pedido . "';
                            }, 2000);
                        </script>";

                        echo "<div class='alert alert-success text-center' style='margin: 20px; padding: 20px; font-size: 1.2rem;'>";
                        echo "<i class='fas fa-check-circle' style='font-size: 2rem; color: #28a745; margin-bottom: 10px;'></i><br>";
                        echo $mensaje_exito . "<br>";
                        if ($mp_fee > 0) {
                            echo "<small>Comisión de procesamiento incluida: $" . number_format($mp_fee, 2) . "</small><br>";
                        }
                        echo "<small>Redirigiendo a la confirmación...</small>";
                        echo "</div>";

                        // Detener ejecución para evitar mostrar el formulario
                        exit;
                        
                    } else {
                        // ✅ ERROR AGREGANDO DETALLES
                        if ($transactionStarted && method_exists($db, 'rollBack') && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                            $db->rollBack();
                            $transactionStarted = false;
                        }
                        $general_err = "Error al agregar los productos al pedido. Detalles agregados: $detalles_agregados";
                        logError("Error agregando detalles del pedido", [
                            'detalles_agregados' => $detalles_agregados,
                            'total_items' => count($_SESSION['carrito']['items'])
                        ]);
                    }
                } else {
                    // ✅ ERROR CREANDO PEDIDO
                    if ($transactionStarted && method_exists($db, 'rollBack') && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                        $db->rollBack();
                        $transactionStarted = false;
                    }
                    $general_err = "Error al crear el pedido: " . (is_string($resultado) ? $resultado : 'Error desconocido');
                    logError("Error creando pedido", ['error' => $resultado]);
                }
                
            } catch (Exception $e) {
                // ✅ MANEJO DE EXCEPCIONES
                if (method_exists($db, 'rollBack') && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                    $db->rollBack();
                }
                $general_err = "Error interno: " . $e->getMessage();
                logError("Excepción al procesar pedido", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            // ✅ PAGO NO PROCESADO
            if (empty($general_err)) {
                $general_err = "No se pudo procesar el pago. Por favor intenta de nuevo.";
            }
            logError("Pago no procesado", [
                'metodo_pago' => $metodo_pago_seleccionado ?? 'no_definido',
                'mp_fee_calculado' => $mp_fee
            ]);
        }
    }
    } // Cierre del else de validación CSRF
}

// ✅ CALCULAR TOTAL FINAL PARA MOSTRAR EN LA PÁGINA (DINÁMICO)
// Este total será actualizado por JavaScript según el método de pago seleccionado
$total_base = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto;
$total = $total_base; // Inicialmente sin comisión de MercadoPago
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    
    <!-- Global Theme CSS y JS (Modo Oscuro Persistente) -->
    <link rel="stylesheet" href="assets/css/global-theme.css?v=2.1">
    <link rel="stylesheet" href="assets/css/store-premium.css?v=1.0">
    <script src="assets/js/theme-handler.js?v=2.1"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- Scripts de pago -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script src="https://www.paypal.com/sdk/js?client-id=Abzmyjjr7wGmTcr7cGZy3dbJFTUAR-Sr6RkJMzTGndnsO8fQe00mCKkn7on30J7kO78Vp0A6RYP_Qlaf&currency=MXN&disable-funding=credit,card&intent=capture&vault=false&commit=true"></script>
    <script src="https://pay.google.com/gp/p/js/pay.js"></script>
        <style>
        :root {
            --primary: #0165FF;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --gradient: linear-gradient(135deg, #1E88E5 0%, #64B5F6 100%);
        } 

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Nunito', sans-serif;
            font-weight: 700;
        }

        .container {
            padding-bottom: 100px;
        }

        .page-header {
            display: flex;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        }

        .page-title {
            font-size: 1.5rem;
            margin: 0;
            flex: 1;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 1.25rem;
            }
        }

        .back-button {
            background-color: var(--secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            text-decoration: none;
            margin-right: 15px;
        }

        .checkout-section {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            .checkout-section {
                padding: 15px;
                border-radius: 12px;
            }
        }

        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .section-title {
                font-size: 1.1rem;
            }
        }

        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }

        .address-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .address-card.selected, .address-card:hover {
            border-color: var(--primary);
            background-color: rgba(1, 101, 255, 0.05);
        }

        .address-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .address-details {
            font-size: 0.9rem;
            color: #666;
        }

        .add-new {
            display: flex;
            align-items: center;
            color: var(--primary);
            font-weight: 500;
            margin-top: 10px;
            text-decoration: none;
            cursor: pointer;
        }

        .add-new i {
            margin-right: 8px;
        }

        /* ✅ MÉTODOS DE PAGO MINIMALISTAS ACTUALIZADOS */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (max-width: 576px) {
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
        }

        .payment-method {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
            position: relative;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        @media (max-width: 576px) {
            .payment-method {
                padding: 12px 8px;
                min-height: 80px;
            }
        }

        .payment-method:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(1, 101, 255, 0.1);
        }

        .payment-method.selected {
            border-color: var(--primary);
            background: rgba(1, 101, 255, 0.05);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .payment-method .payment-icon {
            font-size: 1.8rem;
            margin-bottom: 6px;
            color: #6b7280;
            transition: color 0.2s ease;
        }

        @media (max-width: 576px) {
            .payment-method .payment-icon {
                font-size: 1.5rem;
                margin-bottom: 4px;
            }
        }

        .payment-method.selected .payment-icon {
            color: var(--primary);
        }

        .payment-method .payment-name {
            font-weight: 500;
            font-size: 0.85rem;
            color: #374151;
            margin: 0;
            line-height: 1.2;
        }

        @media (max-width: 576px) {
            .payment-method .payment-name {
                font-size: 0.75rem;
            }
        }

        .payment-method .payment-desc {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 2px;
            line-height: 1.1;
        }

        @media (max-width: 576px) {
            .payment-method .payment-desc {
                font-size: 0.65rem;
            }
        }

        .payment-method.selected .payment-name {
            color: var(--primary);
        }

        /* Indicador de comisión eliminado */
        }

        /* ✅ ICONOS ESPECÍFICOS CON COLORES DE MARCA */
        .payment-method[data-payment="mp_card"] .payment-icon {
            color: #635bff;
        }

        .payment-method[data-payment="google_pay"] .payment-icon {
            color: #4285f4;
        }

        .payment-method[data-payment="apple_pay"] .payment-icon {
            color: #000000;
        }

        .payment-method[data-payment="paypal"] .payment-icon {
            color: #0070ba;
        }

        .payment-method[data-payment="efectivo"] .payment-icon {
            color: #059669;
        }

        .payment-method[data-payment="spei"] .payment-icon {
            color: #1565c0;
        }

        /* ✅ INFORMACIÓN DE COMISIÓN */
        .mp-fee-info {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 12px;
            margin: 12px 0;
            display: none;
        }

        .mp-fee-info.show {
            display: block;
        }

        .mp-fee-info .fa-info-circle {
            color: #d97706;
            margin-right: 8px;
        }

        .mp-fee-text {
            font-size: 0.85rem;
            color: #92400e;
            font-weight: 500;
        }

        /* Formularios de pago mejorados */
        .payment-form {
            display: none;
            margin-top: 16px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fafafa;
        }

        .payment-form.active {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(-10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        .payment-selector {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 16px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.payment-selector:hover {
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(1, 101, 255, 0.1);
}

.payment-selector-button {
    padding: 16px;
    width: 100%;
}

.payment-selector-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.payment-selector-icon {
    width: 48px;
    height: 48px;
    background: var(--secondary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.payment-selector-icon i {
    font-size: 1.5rem;
    color: var(--primary);
}

.payment-selector-text {
    flex: 1;
    text-align: left;
}

.payment-selector-title {
    font-weight: 600;
    font-size: 1rem;
    color: var(--dark);
    margin-bottom: 2px;
}

.payment-selector-subtitle {
    font-size: 0.85rem;
    color: #666;
}

.payment-selector-arrow {
    margin-left: 12px;
}

.payment-selector-arrow i {
    font-size: 1.2rem;
    color: var(--primary);
    transition: transform 0.2s ease;
}

/* Método seleccionado */
.selected-payment-method {
    border: 1px solid var(--primary);
    border-radius: 12px;
    margin-bottom: 16px;
    background: rgba(1, 101, 255, 0.05);
}

.selected-payment-content {
    display: flex;
    align-items: center;
    padding: 16px;
    justify-content: space-between;
}

.selected-payment-icon {
    width: 48px;
    height: 48px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.selected-payment-icon i {
    font-size: 1.5rem;
}

.selected-payment-info {
    flex: 1;
    text-align: left;
}

.selected-payment-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--dark);
    margin-bottom: 2px;
}

.selected-payment-desc {
    font-size: 0.85rem;
    color: #666;
}

.change-payment-btn {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.change-payment-btn:hover {
    background: #0052cc;
    transform: translateY(-1px);
}

/* Responsive para móviles */
@media (max-width: 576px) {
    .payment-selector-content {
        padding: 12px;
    }
    
    .payment-selector-icon {
        width: 40px;
        height: 40px;
        margin-right: 10px;
    }
    
    .payment-selector-icon i {
        font-size: 1.3rem;
    }
    
    .payment-selector-title {
        font-size: 0.9rem;
    }
    
    .payment-selector-subtitle {
        font-size: 0.8rem;
    }
    
    .selected-payment-content {
        padding: 12px;
    }
    
    .selected-payment-icon {
        width: 40px;
        height: 40px;
        margin-right: 10px;
    }
    
    .selected-payment-icon i {
        font-size: 1.3rem;
    }
    
    .selected-payment-name {
        font-size: 0.9rem;
    }
    
    .selected-payment-desc {
        font-size: 0.8rem;
    }
    
    .change-payment-btn {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}

        /* MercadoPago Elements mejorados */
        .MercadoPagoElement {
            box-sizing: border-box;
            height: 45px;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .MercadoPagoElement--focus {
            box-shadow: 0 2px 6px rgba(1, 101, 255, 0.15);
            border-color: var(--primary);
        }

        .MercadoPagoElement--invalid {
            border-color: #fa755a;
            box-shadow: 0 2px 6px rgba(250, 117, 90, 0.15);
        }

        /* Botones de wallets digitales */
        .digital-wallet-button {
            width: 100%;
            height: 48px;
            border-radius: 8px;
            margin: 10px 0;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .digital-wallet-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Google Pay */
        #google-pay-button {
            background: linear-gradient(135deg, #4285f4, #3367d6);
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            height: 48px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(66, 133, 244, 0.4);
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }
        #google-pay-button:hover {
            background: linear-gradient(135deg, #3367d6, #254a9e);
            box-shadow: 0 6px 12px rgba(51, 103, 214, 0.6);
        }
        #google-pay-button:active {
            background: linear-gradient(135deg, #254a9e, #1a3570);
            box-shadow: 0 2px 6px rgba(25, 54, 112, 0.8);
        }

        /* Apple Pay */
        #apple-pay-button {
            background: #000;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* PayPal */
        #paypal-button-container {
            margin-top: 15px;
            min-height: 48px;
        }

        /* Efectivo */
        .cash-info {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }

        .cash-info .fa-money-bill {
            color: #856404;
            margin-right: 10px;
            font-size: 1.2rem;
        }

        /* Propina */
        .tip-options {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            gap: 10px;
        }

        @media (max-width: 576px) {
            .tip-options {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }

        .tip-option {
            flex: 1;
            padding: 15px 10px;
            text-align: center;
            border: 2px solid #eee;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .tip-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(1, 101, 255, 0.1);
        }

        .tip-option.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.1) 0%, rgba(100, 181, 246, 0.1) 100%);
        }

        .tip-value {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .tip-percent {
            font-size: 0.85rem;
            color: #666;
        }

        .custom-tip {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 10px;
            background: #f8f9fa;
        }

        .custom-tip-label {
            font-weight: 500;
            margin-bottom: 10px;
        }

        .custom-tip-input .input-group-text {
            background-color: var(--primary);
            border: none;
            font-weight: 600;
            color: white;
        }

        .custom-tip-input .form-control {
            border: 2px solid #eee;
            border-radius: 0 8px 8px 0;
            padding: 10px 15px;
        }

        .custom-tip-input .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
        }

        /* ✅ RESUMEN DE PEDIDO CON MERCADOPAGO FEE */
        .order-summary {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            bottom: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .order-summary {
                padding: 15px;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                border-radius: 15px 15px 0 0;
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15);
                max-height: 50vh;
                overflow-y: auto;
            }
        }

        .summary-toggle {
            display: none;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .summary-toggle {
                display: flex;
            }
        }

        .summary-content {
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .summary-content.collapsed {
                display: none;
            }
        }

        .summary-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--dark);
        }

        @media (max-width: 768px) {
            .summary-title {
                font-size: 1.1rem;
                margin-bottom: 0;
            }
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 5px 0;
        }

        @media (max-width: 768px) {
            .summary-row {
                margin-bottom: 8px;
            }
        }

        .summary-label {
            color: #666;
            font-size: 0.9rem;
        }

        .summary-value {
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* ✅ FILA DE MERCADOPAGO FEE */
        .summary-row.mp-fee {
            color: #dc2626;
            font-size: 0.85rem;
            display: none;
        }

        .summary-row.mp-fee.show {
            display: flex;
        }

        /* ✅ BANNER PROMOCIÓN MEMBRESÍA */
        .membership-promo-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 12px 15px;
            margin: 15px 0;
            animation: pulse-glow 2s ease-in-out infinite;
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 5px rgba(245, 158, 11, 0.3); }
            50% { box-shadow: 0 0 15px rgba(245, 158, 11, 0.5); }
        }

        .membership-promo-banner .promo-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .membership-promo-banner .promo-content {
            flex: 1;
            min-width: 0;
        }

        .membership-promo-banner .promo-title {
            font-weight: 700;
            font-size: 14px;
            color: #92400e;
            margin-bottom: 2px;
        }

        .membership-promo-banner .promo-text {
            font-size: 12px;
            color: #a16207;
            line-height: 1.3;
        }

        .membership-promo-banner .promo-btn {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .membership-promo-banner .promo-btn:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: scale(1.05);
            color: white;
        }

        @media (max-width: 480px) {
            .membership-promo-banner {
                flex-wrap: wrap;
                padding: 10px;
            }
            .membership-promo-banner .promo-btn {
                width: 100%;
                justify-content: center;
                margin-top: 8px;
            }
        }

        /* Dark mode */
        [data-theme="dark"] .membership-promo-banner,
        html.dark-mode .membership-promo-banner {
            background: linear-gradient(135deg, #451a03 0%, #78350f 100%);
            border-color: #f59e0b;
        }

        [data-theme="dark"] .membership-promo-banner .promo-title,
        html.dark-mode .membership-promo-banner .promo-title {
            color: #fde68a;
        }

        [data-theme="dark"] .membership-promo-banner .promo-text,
        html.dark-mode .membership-promo-banner .promo-text {
            color: #fcd34d;
        }

        .summary-total {
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f1f1f1;
        }

        @media (max-width: 768px) {
            .summary-total {
                font-size: 1.1rem;
                margin-top: 10px;
                padding-top: 10px;
            }
        }

        .place-order-btn {
            background: var(--gradient);
            border: none;
            border-radius: 50px;
            padding: 15px 0;
            color: white;
            font-weight: 600;
            margin-top: 20px;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .place-order-btn {
                padding: 12px 0;
                font-size: 1rem;
                margin-top: 15px;
            }
        }

        .place-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(1, 101, 255, 0.3);
        }

        .place-order-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 6px;
        }

        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 249, 250, 0.95) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: all 0.5s;
            opacity: 0;
            visibility: hidden;
        }

        .preloader.active {
            opacity: 1;
            visibility: visible;
        }

        .preloader-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .preloader-text {
            margin-top: 20px;
            font-weight: 600;
            text-align: center;
            font-size: 1.1rem;
        }

        .preloader-message {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
            max-width: 80%;
            text-align: center;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Layout responsivo mejorado */
        @media (min-width: 992px) {
            .checkout-layout {
                display: grid;
                grid-template-columns: 1fr 350px;
                gap: 30px;
                align-items: start;
            }
            
            .order-summary {
                position: sticky;
                top: 20px;
                margin-top: 0;
            }
        }

        /* Productos del carrito compactos */
        .cart-items-compact {
            max-height: 200px;
            overflow-y: auto;
        }

        .cart-item-compact {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .cart-item-compact:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            margin-right: 10px;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 500;
            font-size: 0.85rem;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-quantity {
            font-size: 0.75rem;
            color: #666;
        }

        .cart-item-price {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--primary);
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .container {
                padding: 0 10px 100px 10px;
            }
            
            .checkout-section {
                margin-bottom: 15px;
            }
        }

/* =======================================================
   MODO OSCURO - CHECKOUT.PHP
   ======================================================= */
@media (prefers-color-scheme: dark) {
    :root {
        --body-bg: #000000;
        --white: #111111;
        --light: #000000;
        --gray-100: #1a1a1a;
        --gray-200: #333333;
        --gray-900: #ffffff;
        --gray-800: #e0e0e0;
        --gray-700: #cccccc;
        --gray-600: #aaaaaa;
    }

    body {
        background-color: #000000 !important;
        color: #e0e0e0;
    }

    .checkout-header {
        background: #000000 !important;
        border-bottom: 1px solid #333;
    }

    .checkout-section, .checkout-card {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .section-title, .checkout-title {
        color: #fff !important;
    }

    .back-button, .back-btn {
        color: #fff !important;
    }

    .form-label {
        color: #e0e0e0 !important;
    }

    .form-control, .form-select {
        background: #1a1a1a !important;
        border-color: #333 !important;
        color: #fff !important;
    }

    .form-control::placeholder {
        color: #666 !important;
    }

    .cart-item {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }

    .cart-item-name {
        color: #fff !important;
    }

    .cart-item-quantity {
        color: #888 !important;
    }

    .summary-row, .total-row {
        color: #e0e0e0 !important;
        border-color: #333 !important;
    }

    .summary-total {
        color: #fff !important;
    }

    .payment-option {
        background: #1a1a1a !important;
        border-color: #333 !important;
        color: #e0e0e0 !important;
    }

    .payment-option.selected {
        border-color: var(--primary) !important;
    }

    .address-item, .address-card {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }

    .address-item.selected, .address-card.selected {
        border-color: var(--primary) !important;
    }

    .address-name, .address-title {
        color: #fff !important;
    }

    .address-details {
        color: #aaa !important;
    }
}

/* Soporte para data-theme="dark" */
[data-theme="dark"] body,
html.dark-mode body {
    background-color: #000000 !important;
    color: #e0e0e0;
}

[data-theme="dark"] .checkout-header,
html.dark-mode .checkout-header {
    background: #000000 !important;
    border-bottom: 1px solid #333;
}

[data-theme="dark"] .checkout-section,
[data-theme="dark"] .checkout-card,
html.dark-mode .checkout-section,
html.dark-mode .checkout-card {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .section-title,
[data-theme="dark"] .checkout-title,
html.dark-mode .section-title,
html.dark-mode .checkout-title {
    color: #fff !important;
}

[data-theme="dark"] .back-button,
[data-theme="dark"] .back-btn,
html.dark-mode .back-button,
html.dark-mode .back-btn {
    color: #fff !important;
}

[data-theme="dark"] .form-label,
html.dark-mode .form-label {
    color: #e0e0e0 !important;
}

[data-theme="dark"] .form-control,
[data-theme="dark"] .form-select,
html.dark-mode .form-control,
html.dark-mode .form-select {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .cart-item,
html.dark-mode .cart-item {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .cart-item-name,
html.dark-mode .cart-item-name {
    color: #fff !important;
}

[data-theme="dark"] .summary-row,
[data-theme="dark"] .total-row,
html.dark-mode .summary-row,
html.dark-mode .total-row {
    color: #e0e0e0 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .payment-option,
html.dark-mode .payment-option {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #e0e0e0 !important;
}

[data-theme="dark"] .address-item,
[data-theme="dark"] .address-card,
html.dark-mode .address-item,
html.dark-mode .address-card {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .address-name,
[data-theme="dark"] .address-title,
html.dark-mode .address-name,
html.dark-mode .address-title {
    color: #fff !important;
}

[data-theme="dark"] .address-details,
html.dark-mode .address-details {
    color: #aaa !important;
}

/* ===== ESTILOS ADICIONALES MODO OSCURO CHECKOUT ===== */
[data-theme="dark"] .checkout-layout,
[data-theme="dark"] .checkout-forms,
html.dark-mode .checkout-layout,
html.dark-mode .checkout-forms {
    background: transparent !important;
}

[data-theme="dark"] .page-header,
html.dark-mode .page-header {
    background: #000000 !important;
    border-bottom: 1px solid #333;
}

[data-theme="dark"] .page-title,
html.dark-mode .page-title {
    color: #fff !important;
}

[data-theme="dark"] .back-button,
html.dark-mode .back-button {
    color: #fff !important;
}

[data-theme="dark"] .payment-selector,
[data-theme="dark"] .payment-selector-button,
html.dark-mode .payment-selector,
html.dark-mode .payment-selector-button {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .payment-selector-title,
html.dark-mode .payment-selector-title {
    color: #fff !important;
}

[data-theme="dark"] .payment-selector-subtitle,
html.dark-mode .payment-selector-subtitle {
    color: #888 !important;
}

[data-theme="dark"] .payment-selector-arrow,
[data-theme="dark"] .payment-selector-icon,
html.dark-mode .payment-selector-arrow,
html.dark-mode .payment-selector-icon {
    color: #888 !important;
}

[data-theme="dark"] .payment-methods,
html.dark-mode .payment-methods {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .payment-method,
html.dark-mode .payment-method {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .payment-method:hover,
html.dark-mode .payment-method:hover {
    border-color: var(--primary) !important;
    background: #222 !important;
}

[data-theme="dark"] .payment-method.selected,
html.dark-mode .payment-method.selected {
    border-color: var(--primary) !important;
    background: rgba(1, 101, 255, 0.1) !important;
}

[data-theme="dark"] .payment-name,
html.dark-mode .payment-name {
    color: #fff !important;
}

[data-theme="dark"] .payment-desc,
html.dark-mode .payment-desc {
    color: #888 !important;
}

[data-theme="dark"] .payment-icon,
html.dark-mode .payment-icon {
    color: #fff !important;
}

[data-theme="dark"] .selected-payment-method,
[data-theme="dark"] .selected-payment-content,
html.dark-mode .selected-payment-method,
html.dark-mode .selected-payment-content {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .selected-payment-name,
html.dark-mode .selected-payment-name {
    color: #fff !important;
}

[data-theme="dark"] .selected-payment-desc,
html.dark-mode .selected-payment-desc {
    color: #888 !important;
}

[data-theme="dark"] .summary-card,
[data-theme="dark"] .order-summary,
html.dark-mode .summary-card,
html.dark-mode .order-summary {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .summary-title,
html.dark-mode .summary-title {
    color: #fff !important;
}

[data-theme="dark"] .summary-label,
html.dark-mode .summary-label {
    color: #aaa !important;
}

[data-theme="dark"] .summary-value,
html.dark-mode .summary-value {
    color: #fff !important;
}

[data-theme="dark"] .summary-total,
[data-theme="dark"] .total-row,
html.dark-mode .summary-total,
html.dark-mode .total-row {
    color: #fff !important;
    border-color: #333 !important;
}

[data-theme="dark"] .propina-btn,
html.dark-mode .propina-btn {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .propina-btn.active,
[data-theme="dark"] .propina-btn:hover,
html.dark-mode .propina-btn.active,
html.dark-mode .propina-btn:hover {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
}

[data-theme="dark"] .cart-items-preview,
html.dark-mode .cart-items-preview {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .cart-item-preview,
html.dark-mode .cart-item-preview {
    border-color: #333 !important;
}

[data-theme="dark"] .preloader,
html.dark-mode .preloader {
    background: #000000 !important;
}

[data-theme="dark"] .preloader-text,
[data-theme="dark"] .preloader-message,
html.dark-mode .preloader-text,
html.dark-mode .preloader-message {
    color: #fff !important;
}

[data-theme="dark"] .fee-info,
[data-theme="dark"] .mp-fee-info,
html.dark-mode .fee-info,
html.dark-mode .mp-fee-info {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #aaa !important;
}

[data-theme="dark"] .modal-content,
html.dark-mode .modal-content {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .modal-header,
html.dark-mode .modal-header {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .modal-title,
html.dark-mode .modal-title {
    color: #fff !important;
}

[data-theme="dark"] .modal-body,
html.dark-mode .modal-body {
    background: #111111 !important;
}

[data-theme="dark"] .btn-close,
html.dark-mode .btn-close {
    filter: invert(1) !important;
}

/* Media query backup para checkout */
@media (prefers-color-scheme: dark) {
    .checkout-section, .checkout-card {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .payment-selector, .payment-selector-button {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .payment-selector-title {
        color: #fff !important;
    }
    .payment-method {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }
    .payment-name {
        color: #fff !important;
    }
    .payment-desc {
        color: #888 !important;
    }
    .summary-card, .order-summary {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .summary-label {
        color: #aaa !important;
    }
    .summary-value, .summary-total {
        color: #fff !important;
    }
    .propina-btn {
        background: #1a1a1a !important;
        border-color: #333 !important;
        color: #fff !important;
    }
    .modal-content {
        background: #111111 !important;
    }
    .modal-header {
        background: #1a1a1a !important;
    }
    .modal-title {
        color: #fff !important;
    }
    .page-header {
        background: #000000 !important;
        border-bottom: 1px solid #333;
    }
    .page-title {
        color: #fff !important;
    }
    .back-button {
        color: #fff !important;
    }
    /* Formulario de tarjeta modo oscuro */
    .payment-form {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .payment-form h5 {
        color: #fff !important;
    }
    .payment-form .form-label {
        color: #ccc !important;
    }
    .MercadoPagoElement, .StripeElement {
        background: #1a1a1a !important;
        border-color: #444 !important;
    }
    .cash-info {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }
    .cash-amount-input {
        background: #1a1a1a !important;
        border-color: #444 !important;
        color: #fff !important;
    }
    .digital-wallet-button {
        background: #1a1a1a !important;
        border-color: #333 !important;
        color: #fff !important;
    }
}

/* ===== ESTILOS MODO OSCURO - FORMULARIO DE PAGO ===== */
[data-theme="dark"] .payment-form,
html.dark-mode .payment-form {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .payment-form h5,
html.dark-mode .payment-form h5 {
    color: #fff !important;
}

[data-theme="dark"] .payment-form .form-label,
html.dark-mode .payment-form .form-label {
    color: #ccc !important;
}

[data-theme="dark"] .MercadoPagoElement,
[data-theme="dark"] .StripeElement,
[data-theme="dark"] #card-number-element,
[data-theme="dark"] #card-expiry-element,
[data-theme="dark"] #card-cvc-element,
html.dark-mode .MercadoPagoElement,
html.dark-mode .StripeElement,
html.dark-mode #card-number-element,
html.dark-mode #card-expiry-element,
html.dark-mode #card-cvc-element {
    background: #1a1a1a !important;
    border: 1px solid #444 !important;
    border-radius: 8px !important;
    padding: 12px !important;
    color: #fff !important;
}

[data-theme="dark"] .cash-info,
html.dark-mode .cash-info {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .cash-info i,
html.dark-mode .cash-info i {
    color: var(--success) !important;
}

[data-theme="dark"] .cash-amount-input,
html.dark-mode .cash-amount-input {
    background: #1a1a1a !important;
    border-color: #444 !important;
    color: #fff !important;
}

[data-theme="dark"] .digital-wallet-button,
html.dark-mode .digital-wallet-button {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .digital-wallet-button:hover,
html.dark-mode .digital-wallet-button:hover {
    background: #222 !important;
    border-color: var(--primary) !important;
}

[data-theme="dark"] .payment-form .text-muted,
html.dark-mode .payment-form .text-muted {
    color: #888 !important;
}

[data-theme="dark"] .address-option,
html.dark-mode .address-option {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .address-option:hover,
html.dark-mode .address-option:hover {
    border-color: var(--primary) !important;
}

[data-theme="dark"] .address-option.selected,
html.dark-mode .address-option.selected {
    border-color: var(--primary) !important;
    background: rgba(1, 101, 255, 0.1) !important;
}

[data-theme="dark"] .address-option label,
html.dark-mode .address-option label {
    color: #fff !important;
}

[data-theme="dark"] .address-street,
html.dark-mode .address-street {
    color: #aaa !important;
}

[data-theme="dark"] .add-new,
html.dark-mode .add-new {
    color: var(--primary) !important;
}

[data-theme="dark"] .propina-buttons,
html.dark-mode .propina-buttons {
    background: transparent !important;
}

[data-theme="dark"] .propina-option,
html.dark-mode .propina-option {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .propina-option:hover,
html.dark-mode .propina-option:hover {
    border-color: var(--primary) !important;
    background: #222 !important;
}

[data-theme="dark"] .propina-option.active,
[data-theme="dark"] .propina-option.selected,
html.dark-mode .propina-option.active,
html.dark-mode .propina-option.selected {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
    color: #fff !important;
}

[data-theme="dark"] .propina-option .propina-amount,
html.dark-mode .propina-option .propina-amount {
    color: #fff !important;
}

[data-theme="dark"] .propina-option .propina-label,
html.dark-mode .propina-option .propina-label {
    color: #aaa !important;
}

[data-theme="dark"] .propina-option.active .propina-label,
[data-theme="dark"] .propina-option.selected .propina-label,
html.dark-mode .propina-option.active .propina-label,
html.dark-mode .propina-option.selected .propina-label {
    color: rgba(255,255,255,0.8) !important;
}

[data-theme="dark"] .badge,
html.dark-mode .badge {
    background: var(--primary) !important;
}

[data-theme="dark"] .error-message,
html.dark-mode .error-message {
    color: #ef4444 !important;
}

[data-theme="dark"] .alert-info,
html.dark-mode .alert-info {
    background: rgba(1, 101, 255, 0.1) !important;
    border-color: #333 !important;
    color: #ccc !important;
}

[data-theme="dark"] .mp-fee-info,
html.dark-mode .mp-fee-info {
    background: rgba(251, 191, 36, 0.1) !important;
    border-color: #444 !important;
}

[data-theme="dark"] .mp-fee-text,
html.dark-mode .mp-fee-text {
    color: #fbbf24 !important;
}

/* ===== TIP OPTIONS - MODO OSCURO ===== */
[data-theme="dark"] .tip-options,
html.dark-mode .tip-options {
    background: transparent !important;
}

[data-theme="dark"] .tip-option,
html.dark-mode .tip-option {
    background: #1a1a1a !important;
    border-color: #333 !important;
    color: #fff !important;
}

[data-theme="dark"] .tip-option:hover,
html.dark-mode .tip-option:hover {
    border-color: var(--primary) !important;
    background: #222 !important;
}

[data-theme="dark"] .tip-option.selected,
html.dark-mode .tip-option.selected {
    border-color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(1, 101, 255, 0.2) 0%, rgba(100, 181, 246, 0.2) 100%) !important;
}

[data-theme="dark"] .tip-value,
html.dark-mode .tip-value {
    color: #fff !important;
}

[data-theme="dark"] .tip-percent,
html.dark-mode .tip-percent {
    color: #888 !important;
}

[data-theme="dark"] .tip-option.selected .tip-percent,
html.dark-mode .tip-option.selected .tip-percent {
    color: rgba(255, 255, 255, 0.7) !important;
}

[data-theme="dark"] .custom-tip,
html.dark-mode .custom-tip {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .custom-tip-label,
html.dark-mode .custom-tip-label {
    color: #fff !important;
}

[data-theme="dark"] .input-group-text,
html.dark-mode .input-group-text {
    background: #1a1a1a !important;
    border-color: #444 !important;
    color: #fff !important;
}

[data-theme="dark"] #custom_tip,
html.dark-mode #custom_tip {
    background: #1a1a1a !important;
    border-color: #444 !important;
    color: #fff !important;
}

/* ===== TIPO DE PEDIDO - MODO OSCURO ===== */
[data-theme="dark"] .form-check-label,
html.dark-mode .form-check-label {
    color: #e0e0e0 !important;
}

/* ===== SELECTED PAYMENT METHOD - MODO OSCURO ===== */
[data-theme="dark"] .selected-payment-method,
html.dark-mode .selected-payment-method {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .selected-payment-content,
html.dark-mode .selected-payment-content {
    background: #111111 !important;
}

[data-theme="dark"] .selected-payment-name,
html.dark-mode .selected-payment-name {
    color: #fff !important;
}

[data-theme="dark"] .selected-payment-desc,
html.dark-mode .selected-payment-desc {
    color: #888 !important;
}

[data-theme="dark"] .selected-payment-icon,
html.dark-mode .selected-payment-icon {
    background: #1a1a1a !important;
    color: var(--primary) !important;
}

[data-theme="dark"] .change-payment-btn,
html.dark-mode .change-payment-btn {
    background: var(--primary) !important;
    color: #fff !important;
}

/* ===== RESUMEN DEL PEDIDO - SIDEBAR ===== */
[data-theme="dark"] .order-summary,
[data-theme="dark"] .checkout-sidebar,
html.dark-mode .order-summary,
html.dark-mode .checkout-sidebar {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .order-summary h3,
[data-theme="dark"] .summary-title,
html.dark-mode .order-summary h3,
html.dark-mode .summary-title {
    color: #fff !important;
}

[data-theme="dark"] .order-item,
html.dark-mode .order-item {
    border-color: #333 !important;
}

[data-theme="dark"] .order-item-name,
html.dark-mode .order-item-name {
    color: #fff !important;
}

[data-theme="dark"] .order-item-price,
html.dark-mode .order-item-price {
    color: var(--primary) !important;
}

[data-theme="dark"] .order-item-quantity,
html.dark-mode .order-item-quantity {
    color: #888 !important;
}

/* ===== CART ITEMS COMPACT - MODO OSCURO ===== */
[data-theme="dark"] .cart-items-compact,
html.dark-mode .cart-items-compact {
    background: transparent !important;
}

[data-theme="dark"] .cart-item-compact,
html.dark-mode .cart-item-compact {
    border-color: #333 !important;
}

[data-theme="dark"] .cart-item-name,
html.dark-mode .cart-item-name {
    color: #fff !important;
}

[data-theme="dark"] .cart-item-quantity,
html.dark-mode .cart-item-quantity {
    color: #888 !important;
}

[data-theme="dark"] .cart-item-price,
html.dark-mode .cart-item-price {
    color: var(--primary) !important;
}

[data-theme="dark"] .cart-item-details,
html.dark-mode .cart-item-details {
    color: #e0e0e0 !important;
}

/* Media query backup para nuevos elementos */
@media (prefers-color-scheme: dark) {
    .tip-option {
        background: #1a1a1a !important;
        border-color: #333 !important;
        color: #fff !important;
    }
    .tip-option.selected {
        border-color: var(--primary) !important;
        background: linear-gradient(135deg, rgba(1, 101, 255, 0.2) 0%, rgba(100, 181, 246, 0.2) 100%) !important;
    }
    .tip-value {
        color: #fff !important;
    }
    .tip-percent {
        color: #888 !important;
    }
    .custom-tip {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .custom-tip-label {
        color: #fff !important;
    }
    .input-group-text {
        background: #1a1a1a !important;
        border-color: #444 !important;
        color: #fff !important;
    }
    #custom_tip {
        background: #1a1a1a !important;
        border-color: #444 !important;
        color: #fff !important;
    }
    .selected-payment-method {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .order-summary, .checkout-sidebar {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .order-item-name {
        color: #fff !important;
    }
}

/* ===============================================
   OPCIONES DE REGALO Y PERSONALIZACIÓN
   =============================================== */
.special-option-card {
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    border: 2px solid #ffe0e0;
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.special-option-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(231,76,60,0.05) 0%, transparent 70%);
    pointer-events: none;
}

.special-option-card:has(#es_regalo:checked) {
    border-color: #e74c3c;
    box-shadow: 0 4px 20px rgba(231, 76, 60, 0.2);
}

.special-option-toggle {
    padding: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.special-option-toggle .form-check-input {
    width: 54px;
    height: 28px;
    cursor: pointer;
    margin: 0;
    flex-shrink: 0;
}

.special-option-toggle .form-check-input:checked {
    background-color: #e74c3c;
    border-color: #e74c3c;
}

.special-option-toggle .form-check-label {
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.special-option-toggle .form-check-label i {
    font-size: 1.3rem;
}

.gift-options-container {
    padding-top: 20px;
    margin-top: 20px;
    border-top: 2px dashed #ffe0e0;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-15px);
        max-height: 0;
    }
    to {
        opacity: 1;
        transform: translateY(0);
        max-height: 1000px;
    }
}

/* Opción de anónimo */
.anonymous-option {
    background: linear-gradient(135deg, #e8f4fc 0%, #f0f8ff 100%);
    padding: 16px;
    border-radius: 14px;
    border: 2px solid #b3d9f2;
    transition: all 0.3s ease;
}

.anonymous-option:hover {
    border-color: #3498db;
    box-shadow: 0 2px 10px rgba(52, 152, 219, 0.15);
}

.anonymous-option .form-check-input {
    width: 22px;
    height: 22px;
    margin-top: 0;
    cursor: pointer;
}

.anonymous-option .form-check-input:checked {
    background-color: #3498db;
    border-color: #3498db;
}

.anonymous-option .form-check-label {
    cursor: pointer;
    padding-left: 8px;
}

.anonymous-option .form-check-label strong {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Campo de remitente */
#remitente-section {
    transition: all 0.3s ease;
}

#remitente-section .form-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
    color: #495057;
}

#nombre_remitente {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

#nombre_remitente:focus {
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

/* Mensaje de regalo */
#mensaje_regalo {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    resize: none;
    transition: all 0.3s ease;
    min-height: 100px;
}

#mensaje_regalo:focus {
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

#mensaje_regalo::placeholder {
    color: #adb5bd;
}

.mensaje-counter-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
}

#mensaje-contador {
    font-weight: 700;
    color: #6c757d;
    transition: color 0.3s ease;
}

.contador-warning {
    color: #e74c3c !important;
}

/* Opciones de envoltura */
.gift-wrap-section {
    margin-top: 5px;
}

.gift-wrap-section .form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    margin-bottom: 12px;
}

.gift-wrap-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.gift-wrap-option {
    position: relative;
}

.gift-wrap-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.gift-wrap-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 12px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    min-height: 90px;
}

.gift-wrap-option label:hover {
    border-color: #ffb3b3;
    background: #fff;
    transform: translateY(-2px);
}

.gift-wrap-option label:active {
    transform: translateY(0);
}

.gift-wrap-option label i {
    font-size: 1.8rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.gift-wrap-option label span {
    font-size: 0.85rem;
    font-weight: 500;
    color: #495057;
    white-space: nowrap;
}

.gift-wrap-option input[type="radio"]:checked + label {
    border-color: #e74c3c;
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.2);
}

.gift-wrap-option input[type="radio"]:checked + label i {
    color: #e74c3c;
    transform: scale(1.15);
}

.gift-wrap-option input[type="radio"]:checked + label span {
    color: #e74c3c;
    font-weight: 600;
}

/* Badge de selección */
.gift-wrap-option input[type="radio"]:checked + label::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: -8px;
    right: -8px;
    background: #e74c3c;
    color: white;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    box-shadow: 0 2px 6px rgba(231, 76, 60, 0.4);
}

/* Instrucciones para repartidor */
.delivery-instructions {
    margin-top: 5px;
}

.delivery-instructions .form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: #495057;
}

.delivery-instructions textarea {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 12px 15px;
    resize: none;
    transition: all 0.3s ease;
}

.delivery-instructions textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* ============================================
   TEMA OSCURO - OPCIONES DE REGALO
   ============================================ */
[data-theme="dark"] .special-option-card,
html.dark-mode .special-option-card {
    background: linear-gradient(135deg, #2d1a1a 0%, #1a1a1a 100%);
    border-color: #4d2a2a;
}

[data-theme="dark"] .special-option-card::before,
html.dark-mode .special-option-card::before {
    background: radial-gradient(circle, rgba(231,76,60,0.08) 0%, transparent 70%);
}

[data-theme="dark"] .gift-options-container,
html.dark-mode .gift-options-container {
    border-top-color: #4d2a2a;
}

[data-theme="dark"] .anonymous-option,
html.dark-mode .anonymous-option {
    background: linear-gradient(135deg, #1a2d3d 0%, #1a1a2d 100%);
    border-color: #2a4d5d;
}

[data-theme="dark"] .anonymous-option:hover,
html.dark-mode .anonymous-option:hover {
    border-color: #3498db;
}

[data-theme="dark"] #nombre_remitente,
[data-theme="dark"] #mensaje_regalo,
[data-theme="dark"] .delivery-instructions textarea,
html.dark-mode #nombre_remitente,
html.dark-mode #mensaje_regalo,
html.dark-mode .delivery-instructions textarea {
    background: #1a1a1a;
    border-color: #333;
    color: #fff;
}

[data-theme="dark"] #nombre_remitente:focus,
[data-theme="dark"] #mensaje_regalo:focus,
html.dark-mode #nombre_remitente:focus,
html.dark-mode #mensaje_regalo:focus {
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.15);
}

[data-theme="dark"] .gift-wrap-option label,
html.dark-mode .gift-wrap-option label {
    background: #2a2a2a;
    border-color: #444;
}

[data-theme="dark"] .gift-wrap-option label:hover,
html.dark-mode .gift-wrap-option label:hover {
    background: #333;
    border-color: #ff6b6b;
}

[data-theme="dark"] .gift-wrap-option label span,
html.dark-mode .gift-wrap-option label span {
    color: #ccc;
}

[data-theme="dark"] .gift-wrap-option input[type="radio"]:checked + label,
html.dark-mode .gift-wrap-option input[type="radio"]:checked + label {
    background: linear-gradient(135deg, #3d1a1a 0%, #2a1a1a 100%);
    border-color: #e74c3c;
}

[data-theme="dark"] .gift-wrap-option input[type="radio"]:checked + label span,
html.dark-mode .gift-wrap-option input[type="radio"]:checked + label span {
    color: #ff6b6b;
}

[data-theme="dark"] .form-label,
html.dark-mode .form-label {
    color: #ccc;
}

/* ============================================
   RESPONSIVE - TABLETS (768px - 991px)
   ============================================ */
@media (max-width: 991px) {
    .special-option-card {
        padding: 18px;
    }

    .gift-wrap-options {
        gap: 10px;
    }

    .gift-wrap-option label {
        padding: 15px 10px;
        min-height: 85px;
    }

    .gift-wrap-option label i {
        font-size: 1.6rem;
    }
}

/* ============================================
   RESPONSIVE - MÓVILES (576px - 767px)
   ============================================ */
@media (max-width: 767px) {
    .special-option-card {
        padding: 16px;
        border-radius: 14px;
    }

    .special-option-toggle .form-check-label {
        font-size: 1rem;
    }

    .special-option-toggle .form-check-input {
        width: 48px;
        height: 26px;
    }

    .gift-options-container {
        padding-top: 16px;
        margin-top: 16px;
    }

    .anonymous-option {
        padding: 14px;
    }

    .gift-wrap-options {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .gift-wrap-option label {
        padding: 12px 8px;
        min-height: 80px;
        border-radius: 12px;
    }

    .gift-wrap-option label i {
        font-size: 1.4rem;
    }

    .gift-wrap-option label span {
        font-size: 0.75rem;
    }

    .gift-wrap-option input[type="radio"]:checked + label::after {
        width: 18px;
        height: 18px;
        font-size: 0.6rem;
        top: -6px;
        right: -6px;
    }

    #mensaje_regalo {
        min-height: 80px;
        padding: 12px;
    }
}

/* ============================================
   RESPONSIVE - MÓVILES PEQUEÑOS (hasta 575px)
   ============================================ */
@media (max-width: 575px) {
    .special-option-card {
        padding: 14px;
        margin: 0 -5px;
        border-radius: 12px;
    }

    .special-option-toggle {
        flex-wrap: wrap;
    }

    .special-option-toggle .form-check-label {
        font-size: 0.95rem;
    }

    .special-option-toggle .form-check-label i {
        font-size: 1.1rem;
    }

    .gift-options-container {
        padding-top: 14px;
        margin-top: 14px;
    }

    .anonymous-option {
        padding: 12px;
        border-radius: 10px;
    }

    .anonymous-option .form-check-label small {
        font-size: 0.8rem;
    }

    /* Grid horizontal en móviles pequeños */
    .gift-wrap-options {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .gift-wrap-option label {
        flex-direction: row;
        justify-content: flex-start;
        gap: 12px;
        padding: 14px 16px;
        min-height: auto;
        text-align: left;
    }

    .gift-wrap-option label i {
        font-size: 1.5rem;
        width: 40px;
        text-align: center;
    }

    .gift-wrap-option label span {
        font-size: 0.9rem;
    }

    .gift-wrap-option input[type="radio"]:checked + label::after {
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
    }

    #nombre_remitente,
    #mensaje_regalo,
    .delivery-instructions textarea {
        font-size: 16px; /* Previene zoom en iOS */
        padding: 12px;
    }

    #mensaje_regalo {
        min-height: 70px;
    }

    .form-label {
        font-size: 0.9rem;
    }
}

/* ============================================
   RESPONSIVE - MÓVILES MUY PEQUEÑOS (hasta 375px)
   ============================================ */
@media (max-width: 375px) {
    .special-option-card {
        padding: 12px;
    }

    .special-option-toggle .form-check-input {
        width: 44px;
        height: 24px;
    }

    .special-option-toggle .form-check-label {
        font-size: 0.9rem;
    }

    .gift-wrap-option label {
        padding: 12px 14px;
    }

    .gift-wrap-option label i {
        font-size: 1.3rem;
        width: 35px;
    }

    .gift-wrap-option label span {
        font-size: 0.85rem;
    }
}
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
        <div class="preloader-text">Procesando tu pedido</div>
        <div class="preloader-message" id="preloader-message">Estamos validando la información...</div>
    </div>

    <div class="container">
        <?php include_once __DIR__ . '/includes/membership_banner.php'; ?>
        <div class="page-header">
            <a href="carrito.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Finalizar Pedido</h1>
        </div>
        
        <?php if (!empty($general_err)): ?>
            <div class="alert alert-danger"><?php echo $general_err; ?></div>
        <?php endif; ?>
        
        <div class="checkout-layout">
            <div class="checkout-forms">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="checkout-form">
                    <?php echo csrf_field(); ?>
                    <!-- Dirección de entrega -->
                    <div class="checkout-section">
                        <h2 class="section-title"><i class="fas fa-map-marker-alt"></i> Dirección de entrega</h2>
                        
                        <?php if (empty($direcciones)): ?>
                            <p>No tienes direcciones guardadas. Añade una dirección para continuar.</p>
                            <a class="add-new" data-bs-toggle="modal" data-bs-target="#direccionModal">
                                <i class="fas fa-plus"></i> Agregar nueva dirección
                            </a>
                        <?php else: ?>
                            <?php foreach ($direcciones as $dir): ?>
                                <div class="form-check address-card <?php echo $dir['es_predeterminada'] ? 'selected' : ''; ?>">
                                    <input class="form-check-input" type="radio" name="direccion_id" 
                                           id="direccion_<?php echo $dir['id_direccion']; ?>" 
                                           value="<?php echo $dir['id_direccion']; ?>" 
                                           <?php echo $dir['es_predeterminada'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="direccion_<?php echo $dir['id_direccion']; ?>">
                                        <div class="address-name">
                                            <?php echo $dir['nombre_direccion']; ?>
                                            <?php echo $dir['es_predeterminada'] ? '<span class="badge bg-primary">Predeterminada</span>' : ''; ?>
                                        </div>
                                        <div class="address-details">
                                            <?php echo $dir['calle'] . ' ' . $dir['numero'] . ', ' . $dir['colonia'] . ', ' . $dir['ciudad'] . ', ' . $dir['estado'] . ' ' . $dir['codigo_postal']; ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <a class="add-new" data-bs-toggle="modal" data-bs-target="#direccionModal">
                                <i class="fas fa-plus"></i> Agregar nueva dirección
                            </a>
                            
                            <?php if (!empty($direccion_err)): ?>
                                <div class="error-message"><?php echo $direccion_err; ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                   

                    <!-- ✅ MÉTODO DE PAGO CON SISTEMA EXPANDIBLE -->
                    <div class="checkout-section">
                        <h2 class="section-title"><i class="fas fa-credit-card"></i> Método de pago</h2>
                        
                        <!-- ✅ BOTÓN PARA MOSTRAR MÉTODOS DE PAGO -->
                        <div class="payment-selector" id="payment-selector">
                            <div class="payment-selector-button" onclick="togglePaymentMethods()">
                                <div class="payment-selector-content">
                                    <div class="payment-selector-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="payment-selector-text">
                                        <div class="payment-selector-title">Seleccionar método de pago</div>
                                        <div class="payment-selector-subtitle">Elige cómo deseas pagar</div>
                                    </div>
                                    <div class="payment-selector-arrow">
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ✅ MÉTODO SELECCIONADO (OCULTO INICIALMENTE) -->
                        <div class="selected-payment-method" id="selected-payment-method" style="display: none;">
                            <div class="selected-payment-content">
                                <div class="selected-payment-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="selected-payment-info">
                                    <div class="selected-payment-name">Método seleccionado</div>
                                    <div class="selected-payment-desc">Descripción del método</div>
                                </div>
                                <button type="button" class="change-payment-btn" onclick="togglePaymentMethods()">
                                    Cambiar
                                </button>
                            </div>
                        </div>
                        
                        <!-- ✅ GRID DE MÉTODOS DE PAGO -->
                        <div class="payment-methods" id="payment-methods">
                            <?php /*
                            if (in_array('mp_card', $metodos_pago_permitidos)): ?>
                            <!-- Tarjeta de crédito/débito con MercadoPago -->
                            <div class="payment-method" data-payment="mp_card">
                                <div class="payment-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="payment-name">Tarjeta</div>
                                <div class="payment-desc">Débito/Crédito</div>
                            </div>
                            <?php endif;

                            if (in_array('paypal', $metodos_pago_permitidos)): ?>
                            <!-- PayPal -->
                            <div class="payment-method" data-payment="paypal">
                                <div class="payment-icon">
                                    <i class="fab fa-paypal"></i>
                                </div>
                                <div class="payment-name">PayPal</div>
                                <div class="payment-desc">Cuenta PayPal</div>
                            </div>
                            <?php endif;

                            if (in_array('spei', $metodos_pago_permitidos)): ?>
                            <!-- SPEI -->
                            <div class="payment-method" data-payment="spei">
                                <div class="payment-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="payment-name">SPEI</div>
                                <div class="payment-desc">Transferencia</div>
                            </div>
                            <?php endif;
                            */ ?>
                            <?php if (in_array('efectivo', $metodos_pago_permitidos)): ?>
                            <!-- Efectivo -->
                            <div class="payment-method" data-payment="efectivo">
                                <div class="payment-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="payment-name">SPEI</div>
                                <div class="payment-desc">Transferencia</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ✅ FORMULARIOS DE PAGO ESPECÍFICOS -->
                        
                        <!-- Formulario MercadoPago Bricks (Tarjeta) -->
                        <div id="mp_card_form" class="payment-form">
                            <div id="cardPaymentBrick_container"></div>
                            <div id="card-errors" class="error-message" style="display: none;"></div>
                        </div>

                        <!-- Formulario PayPal -->
                        <div id="paypal_form" class="payment-form">
                            <div class="text-center">
                                <p class="mb-3">Inicia sesión en tu cuenta PayPal para completar el pago</p>
                                <div id="paypal-button-container"></div>
                            </div>
                        </div>

                        <!-- Formulario SPEI -->
                        <div id="spei_form" class="payment-form">
                            <div class="spei-info" style="background: linear-gradient(135deg, #e3f2fd 0%, #f5f5f5 100%); border-radius: 12px; padding: 20px; margin-bottom: 15px;">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-university text-primary me-3" style="font-size: 24px; margin-top: 3px;"></i>
                                    <div>
                                        <h6 class="mb-2" style="color: #1565c0;"><strong>Transferencia SPEI</strong></h6>
                                        <p class="mb-2 text-muted" style="font-size: 14px;">
                                            Al confirmar tu pedido, recibirás los datos bancarios para realizar la transferencia.
                                            El pago se verifica automáticamente en minutos.
                                        </p>
                                        <ul style="font-size: 13px; color: #666; margin-bottom: 0; padding-left: 20px;">
                                            <li>Disponible 24/7 desde cualquier banco</li>
                                            <li>Sin comisiones adicionales</li>
                                            <li>Confirmación automática</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info" style="font-size: 13px;">
                                <i class="fas fa-info-circle me-2"></i>
                                Tu pedido quedará en espera hasta confirmar el pago. Tienes hasta 24 horas para completar la transferencia.
                            </div>
                        </div>

                        <!-- Formulario Efectivo -->
                        <div id="efectivo_form" class="payment-form">
                            <div class="cash-info">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-university text-primary me-2" style="font-size: 22px;"></i>
                                    <div>
                                        <strong>Pago por SPEI / Transferencia:</strong> Realiza tu pago por transferencia bancaria.
                                    </div>
                                </div>
                                <div class="alert alert-info" style="font-size: 15px;">
                                    <ul style="margin-bottom: 0;">
                                        <li><strong>Banco:</strong> BanCoppel</li>
                                        <li><strong>CLABE:</strong> <span id="clabe-cuenta">4169160603555633</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btn-copiar-clabe" style="padding:2px 8px; font-size:13px;">Copiar</button>
                                        </li>
                                        <li><strong>Nombre del beneficiario:</strong> Barbara Julissa Rodriguez Ornelas</li>
                                        <li><strong>Monto:</strong> $<span id="spei-monto"><?php echo number_format($total, 2); ?></span></li>
                                    <script>
                                    // Sincronizar el monto SPEI con el total a pagar
                                    document.addEventListener('DOMContentLoaded', function() {
                                        function updateSpeiMonto() {
                                            var total = document.getElementById('total_display');
                                            var monto = document.getElementById('spei-monto');
                                            if (total && monto) {
                                                monto.textContent = total.textContent.replace('$','');
                                            }
                                        }
                                        // Actualizar al cargar
                                        updateSpeiMonto();
                                        // Actualizar cuando cambie el total
                                        var observer = new MutationObserver(updateSpeiMonto);
                                        var totalNode = document.getElementById('total_display');
                                        if (totalNode) {
                                            observer.observe(totalNode, { childList: true, characterData: true, subtree: true });
                                        }
                                        // También actualizar al cambiar propina personalizada
                                        var customTip = document.getElementById('custom_tip');
                                        if (customTip) {
                                            customTip.addEventListener('input', updateSpeiMonto);
                                        }
                                    });
                                    </script>
                                    </ul>
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var btnCopiar = document.getElementById('btn-copiar-clabe');
                                        if(btnCopiar) {
                                            btnCopiar.addEventListener('click', function() {
                                                var clabe = document.getElementById('clabe-cuenta').textContent.trim();
                                                if (navigator.clipboard) {
                                                    navigator.clipboard.writeText(clabe).then(function() {
                                                        btnCopiar.textContent = '¡Copiado!';
                                                        setTimeout(function(){ btnCopiar.textContent = 'Copiar'; }, 1500);
                                                    });
                                                } else {
                                                    // Fallback para navegadores antiguos
                                                    var tempInput = document.createElement('input');
                                                    tempInput.value = clabe;
                                                    document.body.appendChild(tempInput);
                                                    tempInput.select();
                                                    document.execCommand('copy');
                                                    document.body.removeChild(tempInput);
                                                    btnCopiar.textContent = '¡Copiado!';
                                                    setTimeout(function(){ btnCopiar.textContent = 'Copiar'; }, 1500);
                                                }
                                            });
                                        }
                                    });
                                    </script>
                                    <div class="mt-2">
                                        <strong>IMPORTANTE:</strong> El nombre del titular de la cuenta desde la que transfieres debe coincidir con el nombre registrado en tu pedido para validar la transferencia.
                                    </div>
                                    <div class="mt-2">
                                        Una vez realizada la transferencia, tu pedido será validado manualmente y recibirás confirmación por WhatsApp.
                                    </div>
                                </div>
                            </div>
                            <!--<div class="form-group mt-3">
                                <label class="form-label">Monto con el que pagarás:</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="monto_efectivo" id="monto_efectivo" 
                                           class="form-control" value="<?php echo $total; ?>" 
                                           min="<?php echo $total; ?>" step="0.01">
                                </div>
                                <small class="text-muted">
                                    El monto debe ser igual o mayor a $<?php echo number_format($total, 2); ?>
                                </small>
                            </div>-->
                        </div>
                        
                        <?php if (!empty($metodo_pago_err)): ?>
                            <div class="error-message"><?php echo $metodo_pago_err; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Propina -->
                    <div class="checkout-section">
                        <h2 class="section-title"><i class="fas fa-hand-holding-usd"></i> Propina para el repartidor</h2>
                        
                        <div class="tip-options">
                            <div class="tip-option" data-value="0">
                                <div class="tip-value">Sin propina</div>
                                <div class="tip-percent">$0</div>
                            </div>
                            <div class="tip-option" data-value="10">
                                <div class="tip-value">$10</div>
                                <div class="tip-percent">Básica</div>
                            </div>
                            <div class="tip-option selected" data-value="15">
                                <div class="tip-value">$15</div>
                                <div class="tip-percent">Recomendada</div>
                            </div>
                            <div class="tip-option" data-value="20">
                                <div class="tip-value">$20</div>
                                <div class="tip-percent">Generosa</div>
                            </div>
                        </div>
                        
                        <div class="custom-tip">
                            <div class="custom-tip-label">O ingresa un monto personalizado:</div>
                            <div class="custom-tip-input">
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="propina" id="custom_tip" class="form-control" 
                                           value="15" min="0" step="5">
                                </div>
                            </div>
                            
                            <?php if (!empty($propina_err)): ?>
                                <div class="error-message"><?php echo $propina_err; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tipo de pedido: Delivery o PickUp -->
                    <div class="checkout-section">
                        <h2 class="section-title"><i class="fas fa-truck"></i> Tipo de pedido</h2>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_pedido" id="tipo_delivery" value="delivery" checked>
                            <label class="form-check-label" for="tipo_delivery">
                                Delivery (Envío a domicilio)
                            </label>
                            <!-- Indicador de repartidores conectados -->
                            <div id="couriers-status" class="couriers-status mt-2" style="display: none;">
                                <span id="couriers-count-badge" class="badge bg-success">
                                    <i class="fas fa-motorcycle"></i> <span id="couriers-count">0</span> repartidores disponibles
                                </span>
                                <span id="couriers-warning" class="badge bg-warning text-dark" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> No hay repartidores disponibles en este momento
                                </span>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_pedido" id="tipo_pickup" value="pickup">
                            <label class="form-check-label" for="tipo_pickup">
                                PickUp (Recoger en tienda)
                            </label>
                        </div>
                        <!-- Horarios del negocio para pickup -->
                        <div id="pickup-time-section" class="mt-3" style="display: none;">
                            <label for="pickup_time" class="form-label">Hora para recoger el pedido</label>
                            <input type="time" id="pickup_time" name="pickup_time" class="form-control" />
                            <small id="pickup-hours-info" class="text-muted">
                                Horario del negocio: <span id="negocio-horario">Cargando...</span>
                            </small>
                            <div id="pickup-time-error" class="text-danger mt-1" style="display: none;"></div>
                        </div>
                    </div>

                    <!-- Programar entrega -->
                    <?php
                    // Verificar si el negocio acepta pedidos programados
                    $acepta_programados = true;
                    $tiempo_minimo_programacion = 60; // minutos por defecto
                    try {
                        $stmt_config = $db->prepare("SELECT acepta_programados, tiempo_minimo_programacion FROM negocios WHERE id_negocio = ?");
                        $stmt_config->execute([$negocio_id]);
                        $config_negocio = $stmt_config->fetch(PDO::FETCH_ASSOC);
                        if ($config_negocio) {
                            $acepta_programados = (bool)($config_negocio['acepta_programados'] ?? 1);
                            $tiempo_minimo_programacion = (int)($config_negocio['tiempo_minimo_programacion'] ?? 60);
                        }
                    } catch (Exception $e) {
                        // Si falla, usar valores por defecto
                    }
                    ?>
                    <?php
                    $requiere_programado = isset($_SESSION['carrito']['requiere_programado']) && $_SESSION['carrito']['requiere_programado'] === true;
                    ?>
                    <?php if ($acepta_programados): ?>
                    <div class="checkout-section">
                        <h2 class="section-title"><i class="fas fa-clock"></i> ¿Cuándo quieres tu pedido?</h2>
                        <?php if ($requiere_programado): ?>
                        <div class="alert alert-info mb-3" style="border-radius: 12px;">
                            <i class="fas fa-info-circle me-2"></i>
                            El negocio está cerrado actualmente. Tu pedido será programado para cuando abran.
                        </div>
                        <?php endif; ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipo_entrega_tiempo" id="entrega_ahora" value="ahora" <?php echo $requiere_programado ? 'disabled' : 'checked'; ?>>
                            <label class="form-check-label <?php echo $requiere_programado ? 'text-muted' : ''; ?>" for="entrega_ahora">
                                <i class="fas fa-bolt text-warning"></i> Entregar lo antes posible
                                <?php if ($requiere_programado): ?><small>(No disponible - Negocio cerrado)</small><?php endif; ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_entrega_tiempo" id="entrega_programada" value="programado" <?php echo $requiere_programado ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="entrega_programada">
                                <i class="fas fa-calendar-alt text-primary"></i> Programar entrega
                            </label>
                        </div>

                        <!-- Selector de fecha y hora para pedidos programados -->
                        <div id="programacion-section" class="mt-3" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_programada" class="form-label">Fecha de entrega</label>
                                    <input type="date" id="fecha_programada" name="fecha_programada" class="form-control"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           max="<?php echo date('Y-m-d', strtotime('+21 days')); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="hora_programada" class="form-label">Hora de entrega</label>
                                    <input type="time" id="hora_programada" name="hora_programada" class="form-control">
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Tiempo mínimo de anticipación: <?php echo $tiempo_minimo_programacion; ?> minutos.
                                Solo puedes programar dentro del horario del negocio.
                            </small>
                            <div id="programacion-error" class="text-danger mt-2" style="display: none;"></div>

                            <!-- Alerta WhatsApp para pedidos programados -->
                            <div id="programado-whatsapp-alert" class="mt-3" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border: 1px solid #86efac; border-radius: 12px; padding: 14px; display: none;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <div style="background: #22c55e; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fab fa-whatsapp" style="color: white; font-size: 18px;"></i>
                                    </div>
                                    <div>
                                        <strong style="color: #166534; font-size: 14px;">Recibirás una notificación por WhatsApp</strong>
                                        <p style="color: #15803d; font-size: 13px; margin: 4px 0 0;">
                                            Te enviaremos un recordatorio 1 hora antes de la entrega programada para que estés listo.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="es_programado" id="es_programado" value="0">
                        <input type="hidden" name="fecha_hora_programada" id="fecha_hora_programada" value="">
                    </div>
                    <?php endif; ?>

                    <!-- Opciones especiales del pedido (solo para florería y repostería) -->
                   
                    <div class="checkout-section">
                        <h2 class="section-title"><i class="fas fa-gift"></i> Personaliza tu pedido</h2>

                        <!-- Es un regalo -->
                        <div class="special-option-card" id="gift-options-card">
                            <div class="form-check form-switch special-option-toggle">
                                <input class="form-check-input" type="checkbox" role="switch" id="es_regalo" name="es_regalo" value="1" onchange="toggleGiftOptions()">
                                <label class="form-check-label" for="es_regalo">
                                    <i class="fas fa-gift text-danger"></i>
                                    <strong>Es un regalo</strong>
                                </label>
                            </div>

                            <!-- Opciones de regalo (ocultas por defecto) -->
                            <div id="gift-options" style="display: none;" class="gift-options-container">

                                <!-- Modo anónimo -->
                                <div class="form-check anonymous-option">
                                    <input class="form-check-input" type="checkbox" id="modo_anonimo" name="modo_anonimo" value="1">
                                    <label class="form-check-label" for="modo_anonimo">
                                        <strong>
                                            <i class="fas fa-user-secret text-primary"></i>
                                            Enviar de forma anónima
                                        </strong>
                                        <small class="d-block text-muted mt-1">Tu nombre no aparecerá en la entrega</small>
                                    </label>
                                </div>

                                <!-- Nombre del remitente (opcional si no es anónimo) -->
                                <div id="remitente-section" class="mb-4 mt-4">
                                    <label for="nombre_remitente" class="form-label">
                                        <i class="fas fa-user text-secondary"></i>
                                        De parte de:
                                    </label>
                                    <input type="text" class="form-control" id="nombre_remitente" name="nombre_remitente"
                                           placeholder="Tu nombre o como quieres firmar" maxlength="50"
                                           autocomplete="off">
                                </div>

                                <!-- Mensaje/Tarjeta personalizada -->
                                <div class="mb-4">
                                    <label for="mensaje_regalo" class="form-label">
                                        <i class="fas fa-envelope-open-text text-danger"></i>
                                        Mensaje para la tarjeta
                                    </label>
                                    <textarea class="form-control" id="mensaje_regalo" name="mensaje_regalo"
                                              placeholder="Escribe un mensaje especial que irá en la tarjeta de regalo..."
                                              maxlength="300"></textarea>
                                    <div class="mensaje-counter-wrapper">
                                        <small class="text-muted">Máx. 300 caracteres</small>
                                        <small><span id="mensaje-contador">0</span>/300</small>
                                    </div>
                                </div>

                                <!-- Tipo de envoltura -->
                                <div class="gift-wrap-section">
                                    <label class="form-label">
                                        <i class="fas fa-ribbon text-warning"></i>
                                        Tipo de presentación
                                    </label>
                                    <div class="gift-wrap-options">
                                        <div class="gift-wrap-option">
                                            <input type="radio" name="tipo_envoltura" id="wrap_normal" value="normal" checked>
                                            <label for="wrap_normal">
                                                <i class="fas fa-box"></i>
                                                <span>Normal</span>
                                            </label>
                                        </div>
                                        <div class="gift-wrap-option">
                                            <input type="radio" name="tipo_envoltura" id="wrap_regalo" value="regalo">
                                            <label for="wrap_regalo">
                                                <i class="fas fa-gift"></i>
                                                <span>Para regalo</span>
                                            </label>
                                        </div>
                                        <div class="gift-wrap-option">
                                            <input type="radio" name="tipo_envoltura" id="wrap_sorpresa" value="sorpresa">
                                            <label for="wrap_sorpresa">
                                                <i class="fas fa-star"></i>
                                                <span>Sorpresa</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instrucciones para el repartidor -->
                        <div class="delivery-instructions mt-4">
                            <label for="instrucciones" class="form-label">
                                <i class="fas fa-motorcycle text-primary"></i>
                                Instrucciones para el repartidor
                            </label>
                            <textarea name="instrucciones" id="instrucciones" rows="2" class="form-control"
                                      placeholder="Ej: Tocar el timbre, dejar en recepción, llamar al llegar..."></textarea>
                        </div>
                    </div>
                   

                    <!-- Campo oculto para el método de pago seleccionado -->
                    <input type="hidden" name="payment_method" id="payment_method" value="">
                    <input type="hidden" name="tiempo_minimo_programacion" id="tiempo_minimo_programacion" value="<?php echo $tiempo_minimo_programacion ?? 60; ?>">
                </form>
            </div>

            <!-- ✅ RESUMEN DEL PEDIDO ACTUALIZADO CON MERCADOPAGO FEE -->
            <div class="order-summary">
                <!-- Toggle para móviles -->
                <div class="summary-toggle" onclick="toggleSummary()">
                    <h2 class="summary-title">Resumen del pedido</h2>
                    <div>
                        <span id="total_display_mobile">$<?php echo number_format($total, 2); ?></span>
                        <i class="fas fa-chevron-up" id="summary-arrow"></i>
                    </div>
                </div>
                
                <div class="summary-content" id="summary-content">
                    <h2 class="summary-title d-none d-md-block">Resumen del pedido</h2>
                    
                    <!-- Productos del carrito compactos -->
                    <div class="cart-items-compact mb-3">
                        <?php if (!empty($_SESSION['carrito']['items'])): ?>
                            <?php foreach ($_SESSION['carrito']['items'] as $item): ?>
                                <div class="cart-item-compact">
                                    <?php if (!empty($item['imagen'])): ?>
                                        <img src="<?php echo $item['imagen']; ?>" alt="<?php echo $item['nombre']; ?>" class="cart-item-image">
                                    <?php endif; ?>
                                    <div class="cart-item-details">
                                        <div class="cart-item-name"><?php echo htmlspecialchars($item['nombre']); ?></div>
                                        <div class="cart-item-quantity"><?php echo $item['cantidad']; ?> x $<?php echo number_format($item['precio'], 2); ?></div>
                                    </div>
                                    <div class="cart-item-price">
                                        $<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Subtotal productos</span>
                        <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                  <!--  <div class="summary-row">
                        <span class="summary-label">IVA (16%)</span>
                        <span class="summary-value">$<?php echo number_format($impuesto, 2); ?></span>
                    </div>-->
                    
                    <div class="summary-row">
                        <span class="summary-label">Costo de envío</span>
                        <span class="summary-value" id="envio_display">$<?php echo number_format($costo_envio, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Cargo por servicio</span>
                        <span class="summary-value">$<?php echo number_format($cargo_servicio, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Propina</span>
                        <span class="summary-value" id="tip_display">$<?php echo number_format($propina, 2); ?></span>
                    </div>

                    <!-- CUPÓN DE DESCUENTO -->
                    <div class="coupon-section" style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                        <div class="coupon-input-group" style="display: flex; gap: 8px;">
                            <input type="text" id="codigo_cupon" placeholder="Código de cupón"
                                   style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <button type="button" id="btn_aplicar_cupon" onclick="aplicarCupon()"
                                    style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                Aplicar
                            </button>
                        </div>
                        <div id="cupon_mensaje" style="margin-top: 8px; font-size: 13px; display: none;"></div>
                    </div>

                    <!-- Fila de descuento (oculta por defecto) -->
                    <div class="summary-row" id="descuento_row" style="display: none; color: #28a745;">
                        <span class="summary-label">Descuento cupón</span>
                        <span class="summary-value" id="descuento_display">-$0.00</span>
                    </div>
                    <input type="hidden" name="id_cupon" id="id_cupon" value="">
                    <input type="hidden" name="descuento_cupon" id="descuento_cupon" value="0">

                   

                    <div class="summary-row summary-total">
                        <span>Total a pagar</span>
                        <span id="total_display">$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <button type="button" class="place-order-btn" id="submit-button">
                        <i class="fas fa-shopping-cart me-2"></i>Realizar pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar dirección -->
    <div class="modal fade" id="direccionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar nueva dirección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formDireccion">
                        <input type="hidden" name="action" value="add_address">
                        <?php echo csrf_field(); ?>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nombre para la dirección</label>
                                <input type="text" class="form-control" name="nombre_direccion" placeholder="Ej. Casa" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Calle</label>
                                <input type="text" class="form-control" name="calle" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Número</label>
                                <input type="text" class="form-control" name="numero" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Colonia</label>
                                <input type="text" class="form-control" name="colonia" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código Postal</label>
                                <input type="text" class="form-control" name="codigo_postal" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado</label>
                                <input type="text" class="form-control" name="estado" required>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="es_predeterminada" value="1">
                            <label class="form-check-label">Establecer como dirección predeterminada</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarDireccion">Guardar</button>
                </div>
            </div>
        </div>
    </div>

       <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <script>
        // ✅ FUNCIÓN PARA TOGGLE DE MÉTODOS DE PAGO
        function togglePaymentMethods() {
            const paymentMethods = document.getElementById('payment-methods');
            const paymentSelector = document.getElementById('payment-selector');
            const selectedMethod = document.getElementById('selected-payment-method');
            const arrow = paymentSelector.querySelector('.payment-selector-arrow i');
            
            if (paymentMethods.style.display === 'none' || paymentMethods.style.display === '') {
                // Mostrar métodos de pago
                paymentMethods.style.display = 'grid';
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
                paymentSelector.style.display = 'block';
                selectedMethod.style.display = 'none';
            } else {
                // Ocultar métodos de pago
                paymentMethods.style.display = 'none';
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
            }
        }

        // ✅ FUNCIÓN PARA SELECCIONAR MÉTODO DE PAGO
        function selectPaymentMethod(paymentType) {
            const paymentMethods = document.getElementById('payment-methods');
            const paymentSelector = document.getElementById('payment-selector');
            const selectedMethod = document.getElementById('selected-payment-method');
            
            // Datos de los métodos de pago
            const paymentData = {
                'mp_card': {
                    icon: 'fas fa-credit-card',
                    name: 'Tarjeta',
                    desc: 'Débito/Crédito',
                    color: '#635bff'
                },
                'google_pay': {
                    icon: 'fab fa-google-pay',
                    name: 'Google Pay',
                    desc: 'Pago rápido',
                    color: '#4285f4'
                },
                'apple_pay': {
                    icon: 'fab fa-apple-pay',
                    name: 'Apple Pay',
                    desc: 'Touch/Face ID',
                    color: '#000000'
                },
                'paypal': {
                    icon: 'fab fa-paypal',
                    name: 'PayPal',
                    desc: 'Cuenta PayPal',
                    color: '#0070ba'
                },
                'efectivo': {
                    icon: 'fas fa-money-bill-wave',
                    name: 'SPEI',
                    desc: 'Transferencia bancaria',
                    color: '#059669'
                },
                'spei': {
                    icon: 'fas fa-university',
                    name: 'SPEI',
                    desc: 'Transferencia',
                    color: '#1565c0'
                }
            };
            
            const selected = paymentData[paymentType];
            if (selected) {
                // Actualizar el método seleccionado
                const iconElement = selectedMethod.querySelector('.selected-payment-icon i');
                const nameElement = selectedMethod.querySelector('.selected-payment-name');
                const descElement = selectedMethod.querySelector('.selected-payment-desc');
                
                iconElement.className = selected.icon;
                iconElement.style.color = selected.color;
                nameElement.textContent = selected.name;
                descElement.textContent = selected.desc;
                
                // Mostrar método seleccionado y ocultar selector
                paymentMethods.style.display = 'none';
                paymentSelector.style.display = 'none';
                selectedMethod.style.display = 'block';
                
                // Actualizar campo oculto
                document.getElementById('payment_method').value = paymentType;
                
                // ✅ Disparar el evento jQuery para sincronizar con el sistema de checkout
                const paymentMethodElement = document.querySelector(`.payment-method[data-payment="${paymentType}"]`);
                // ✅ La inicialización de elementos se hace en el handler jQuery
            }
        }

        // ✅ FUNCIÓN GLOBAL PARA SER LLAMADA DESDE JQUERY
        window.updatePaymentMethodDisplay = function(paymentType) {
            const paymentMethods = document.getElementById('payment-methods');
            const paymentSelector = document.getElementById('payment-selector');
            const selectedMethod = document.getElementById('selected-payment-method');
            
            const paymentData = {
                'mp_card': { icon: 'fas fa-credit-card', name: 'Tarjeta', desc: 'Débito/Crédito', color: '#635bff' },
                'google_pay': { icon: 'fab fa-google-pay', name: 'Google Pay', desc: 'Pago rápido', color: '#4285f4' },
                'apple_pay': { icon: 'fab fa-apple-pay', name: 'Apple Pay', desc: 'Touch/Face ID', color: '#000000' },
                'paypal': { icon: 'fab fa-paypal', name: 'PayPal', desc: 'Cuenta PayPal', color: '#0070ba' },
                'efectivo': { icon: 'fas fa-money-bill-wave', name: 'SPEI', desc: 'Transferencia bancaria', color: '#059669' },
                'spei': { icon: 'fas fa-university', name: 'SPEI', desc: 'Transferencia', color: '#1565c0' }
            };
            
            const selected = paymentData[paymentType];
            if (selected && selectedMethod) {
                const iconElement = selectedMethod.querySelector('.selected-payment-icon i');
                const nameElement = selectedMethod.querySelector('.selected-payment-name');
                const descElement = selectedMethod.querySelector('.selected-payment-desc');
                
                if (iconElement) {
                    iconElement.className = selected.icon;
                    iconElement.style.color = selected.color;
                }
                if (nameElement) nameElement.textContent = selected.name;
                if (descElement) descElement.textContent = selected.desc;
                
                paymentMethods.style.display = 'none';
                paymentSelector.style.display = 'none';
                selectedMethod.style.display = 'block';
            }
        };

        // Nota: Los eventos de click se manejan en jQuery para evitar conflictos
    </script>
<script>
    $(document).ready(function() {
        // ✅ CONFIGURACIÓN DE MERCADOPAGO REAL - Key desde variable de entorno
        const mp = new MercadoPago('<?php echo env("MP_PUBLIC_KEY"); ?>', {
            locale: 'es-MX'
        });
        
        // Variables globales
        let selectedPaymentMethod = '';
        let cardFormMounted = false;
        let paypalReady = false;
        let cardForm = null;

        // ✅ Auto-seleccionar entrega programada si es requerido
        <?php if ($requiere_programado): ?>
        setTimeout(function() {
            const programacionSection = document.getElementById('programacion-section');
            const esProgramado = document.getElementById('es_programado');
            const whatsappAlert = document.getElementById('programado-whatsapp-alert');

            if (programacionSection) programacionSection.style.display = 'block';
            if (esProgramado) esProgramado.value = '1';
            if (whatsappAlert) whatsappAlert.style.display = 'block';

            // Establecer fecha mínima como mañana para negocios cerrados
            const fechaInput = document.getElementById('fecha_programada');
            if (fechaInput && !fechaInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                fechaInput.value = tomorrow.toISOString().split('T')[0];
            }
        }, 500);
        <?php endif; ?>

        // ✅ FUNCIÓN PARA DETECTAR MODO OSCURO
        function isDarkMode() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ||
                   document.documentElement.classList.contains('dark-mode') ||
                   localStorage.getItem('quickbite-theme') === 'dark' ||
                   (localStorage.getItem('quickbite-theme') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        }

        // Gestión del preloader
        function showPreloader(message) {
            if (message) {
                document.getElementById('preloader-message').textContent = message;
            }
            document.getElementById('preloader').classList.add('active');
        }

        function hidePreloader() {
            document.getElementById('preloader').classList.remove('active');
        }
        
        // Toggle del resumen en móviles
        window.toggleSummary = function() {
            const content = document.getElementById('summary-content');
            const arrow = document.getElementById('summary-arrow');
            
            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
            } else {
                content.classList.add('collapsed');
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
            }
        };
        
        // Inicializar resumen colapsado en móviles
        if (window.innerWidth <= 768) {
            const summaryContent = document.getElementById('summary-content');
            const summaryArrow = document.getElementById('summary-arrow');
            if (summaryContent) summaryContent.classList.add('collapsed');
            if (summaryArrow) {
                summaryArrow.classList.remove('fa-chevron-up');
                summaryArrow.classList.add('fa-chevron-down');
            }
        }
        
        // Variable global para descuento de cupón
        let descuentoCupon = 0;

        // ✅ FUNCIÓN UPDATERSUMMARY ACTUALIZADA CON COMISIÓN DE MERCADOPAGO Y CUPONES
        function updateSummary() {
            const propina = parseFloat($('#custom_tip').val()) || 0;
            const subtotal = <?php echo $subtotal; ?>;
            const impuesto = <?php echo $impuesto; ?>;
            const costoEnvioBase = <?php echo $costo_envio; ?>;
            const cargoServicio = <?php echo $cargo_servicio; ?>;

            // ✅ VERIFICAR TIPO DE PEDIDO: Si es pickup, envío = $0
            const tipoPedido = $('input[name="tipo_pedido"]:checked').val();
            const costoEnvio = (tipoPedido === 'pickup') ? 0 : costoEnvioBase;

            // ✅ CALCULAR COMISIÓN DE MERCADOPAGO SI ES MÉTODO CON TARJETA
            let mpFee = 0;
            const isMercadoPagoPayment = ['mp_card'].includes(selectedPaymentMethod);

            // Base sin descuento para comisión
            const baseParaComision = subtotal + impuesto + costoEnvio + cargoServicio + propina - descuentoCupon;

            if (isMercadoPagoPayment) {
                mpFee = 0; // Sin comisión adicional
            }

            const total = subtotal + impuesto + costoEnvio + cargoServicio + propina + mpFee - descuentoCupon;

            // ✅ ACTUALIZAR DISPLAYS
            $('#tip_display').text('$' + propina.toFixed(2));
            $('#envio_display').text('$' + costoEnvio.toFixed(2));
            $('#mp_fee_display').text('$' + mpFee.toFixed(2));
            $('#descuento_display').text('-$' + descuentoCupon.toFixed(2));
            $('#total_display').text('$' + total.toFixed(2));
            $('#total_display_mobile').text('$' + total.toFixed(2));

            // ✅ MOSTRAR/OCULTAR INFORMACIÓN DE COMISIÓN
            if (isMercadoPagoPayment) {
                $('#mp-fee-info').addClass('show');
                $('#mp-fee-row').addClass('show');
            } else {
                $('#mp-fee-info').removeClass('show');
                $('#mp-fee-row').removeClass('show');
            }

            // Actualizar mínimo para efectivo
            $('#monto_efectivo').attr('min', total).val(total.toFixed(2));
        }

        // ✅ FUNCIÓN PARA APLICAR CUPÓN
        async function aplicarCupon() {
            const codigo = $('#codigo_cupon').val().trim();
            const mensajeDiv = $('#cupon_mensaje');
            const btn = $('#btn_aplicar_cupon');

            if (!codigo) {
                mensajeDiv.html('<span style="color: #dc3545;">Ingresa un código de cupón</span>').show();
                return;
            }

            btn.prop('disabled', true).text('Validando...');

            try {
                const subtotal = <?php echo $subtotal; ?>;
                const negocioId = <?php echo $negocio_id; ?>;

                const response = await fetch(`/api/cupones.php?action=validar&codigo=${encodeURIComponent(codigo)}&subtotal=${subtotal}&negocio_id=${negocioId}`);
                const result = await response.json();

                if (result.success && result.valido) {
                    // Cupón válido
                    descuentoCupon = parseFloat(result.descuento);
                    $('#id_cupon').val(result.cupon.id);
                    $('#descuento_cupon').val(descuentoCupon);
                    $('#descuento_row').show();

                    mensajeDiv.html('<span style="color: #28a745;"><i class="fas fa-check-circle"></i> ' + result.mensaje + '</span>').show();

                    // Cambiar botón a "Quitar"
                    btn.text('Quitar').off('click').on('click', quitarCupon);
                    $('#codigo_cupon').prop('disabled', true);

                    updateSummary();
                    updateBrickAmount();
                } else {
                    mensajeDiv.html('<span style="color: #dc3545;"><i class="fas fa-times-circle"></i> ' + (result.error || 'Cupón no válido') + '</span>').show();
                }
            } catch (error) {
                mensajeDiv.html('<span style="color: #dc3545;">Error al validar cupón</span>').show();
            }

            btn.prop('disabled', false);
            if (btn.text() === 'Validando...') btn.text('Aplicar');
        }

        // ✅ FUNCIÓN PARA QUITAR CUPÓN
        function quitarCupon() {
            descuentoCupon = 0;
            $('#id_cupon').val('');
            $('#descuento_cupon').val('0');
            $('#descuento_row').hide();
            $('#codigo_cupon').val('').prop('disabled', false);
            $('#cupon_mensaje').hide();
            $('#btn_aplicar_cupon').text('Aplicar').off('click').on('click', aplicarCupon);
            updateSummary();
            updateBrickAmount();
        }
        
        // ✅ FUNCIÓN PARA CALCULAR EL TOTAL ACTUAL
        function getCurrentTotal() {
            const propina = parseFloat($('#custom_tip').val()) || 0;
            const subtotal = <?php echo $subtotal; ?>;
            const costoEnvioBase = <?php echo $costo_envio; ?>;
            const cargoServicio = <?php echo $cargo_servicio; ?>;
            const tipoPedido = $('input[name="tipo_pedido"]:checked').val();
            const costoEnvio = (tipoPedido === 'pickup') ? 0 : costoEnvioBase;
            return subtotal + costoEnvio + cargoServicio + propina - descuentoCupon;
        }

        // ✅ INICIALIZAR MERCADOPAGO BRICKS - CARD PAYMENT
        let cardPaymentBrickController = null;

        async function initCardElements() {
            // Si ya existe un Brick, destruirlo primero
            if (cardPaymentBrickController) {
                await cardPaymentBrickController.unmount();
                cardPaymentBrickController = null;
                cardFormMounted = false;
            }

            try {
                console.log('🔧 Inicializando MercadoPago Bricks...');

                const bricksBuilder = mp.bricks();
                const totalAmount = getCurrentTotal();

                console.log('💰 Monto para Brick:', totalAmount);

                cardPaymentBrickController = await bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', {
                    initialization: {
                        amount: totalAmount,
                        payer: {
                            email: '<?php echo htmlspecialchars($_SESSION['usuario']['email'] ?? ''); ?>'
                        }
                    },
                    customization: {
                        visual: {
                            style: {
                                theme: isDarkMode() ? 'dark' : 'default'
                            }
                        },
                        paymentMethods: {
                            maxInstallments: 1,
                            minInstallments: 1
                        }
                    },
                    callbacks: {
                        onReady: () => {
                            console.log('✅ Card Payment Brick listo');
                        },
                        onSubmit: async (cardFormData) => {
                            showPreloader('Procesando pago con MercadoPago...');

                            // Usar el total actual calculado, no el del Brick
                            const totalActual = getCurrentTotal();
                            console.log('💳 Procesando pago por $' + totalActual);

                            try {
                                const response = await fetch('/api/mercadopago/process_payment.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        token: cardFormData.token,
                                        transaction_amount: totalActual,
                                        installments: cardFormData.installments,
                                        cardholder_email: cardFormData.payer.email,
                                        description: 'Pedido QuickBite'
                                    })
                                });

                                const result = await response.json();

                                if (result.success) {
                                    console.log('✅ Pago exitoso:', result);
                                    $('<input>').attr({ type: 'hidden', name: 'mp_payment_id', value: result.payment_id }).appendTo('#checkout-form');
                                    $('<input>').attr({ type: 'hidden', name: 'mp_status', value: result.status }).appendTo('#checkout-form');
                                    document.getElementById('checkout-form').submit();
                                } else {
                                    throw new Error(result.error || 'Error procesando el pago');
                                }
                            } catch (error) {
                                hidePreloader();
                                console.error('❌ Error en pago:', error);
                                document.getElementById('card-errors').textContent = error.message;
                                document.getElementById('card-errors').style.display = 'block';
                            }
                        },
                        onError: (error) => {
                            console.error('❌ Error en Brick:', error);
                            document.getElementById('card-errors').textContent = 'Error al procesar el pago. Intenta de nuevo.';
                            document.getElementById('card-errors').style.display = 'block';
                        }
                    }
                });

                cardFormMounted = true;

            } catch (error) {
                console.error('❌ Error inicializando MercadoPago Bricks:', error);
                document.getElementById('card-errors').textContent = 'Error al cargar el formulario de pago.';
                document.getElementById('card-errors').style.display = 'block';
            }
        }

        // ✅ ACTUALIZAR EL BRICK CUANDO CAMBIE EL MONTO
        async function updateBrickAmount() {
            if (selectedPaymentMethod === 'mp_card' && cardFormMounted) {
                await initCardElements(); // Recrear el Brick con el nuevo monto
            }
        }

        // ✅ PROCESAR PAGO CON MERCADOPAGO BRICKS (llamado por el botón de checkout)
        async function processCardPayment() {
            if (cardPaymentBrickController) {
                const submitButton = document.querySelector('#cardPaymentBrick_container button[type="submit"]');
                if (submitButton) {
                    submitButton.click();
                }
            } else {
                alert('El formulario de pago no está listo. Por favor espera.');
            }
        }
        
        // ✅ INICIALIZAR PAYPAL
        function initPayPal() {
            if (paypalReady) return;
            
            if (window.paypal) {
                try {
                    paypal.Buttons({
                        createOrder: function(data, actions) {
                            const total = parseFloat($('#total_display').text().replace('$', '').replace(',', ''));
                            return actions.order.create({
                                purchase_units: [{
                                    amount: {
                                        value: total.toFixed(2),
                                        currency_code: 'MXN'
                                    },
                                    description: 'Pedido QuickBite - Comida a domicilio'
                                }]
                            });
                        },
                        onApprove: function(data, actions) {
                            showPreloader('Procesando pago con PayPal...');
                            return actions.order.capture().then(function(details) {
                                console.log('PayPal pago completado:', details);
                                processOrder('paypal', data.orderID);
                            }).catch(function(error) {
                                hidePreloader();
                                console.error('Error capturando PayPal:', error);
                                alert('Error procesando el pago de PayPal. Por favor intenta de nuevo.');
                            });
                        },
                        onError: function(err) {
                            hidePreloader();
                            console.error('Error PayPal:', err);
                            alert('Error con PayPal. Por favor intenta con otro método de pago.');
                        },
                        onCancel: function(data) {
                            hidePreloader();
                            console.log('PayPal cancelado:', data);
                        },
                        style: {
                            layout: 'vertical',
                            color: 'blue',
                            shape: 'rect',
                            label: 'paypal'
                        }
                    }).render('#paypal-button-container');
                    
                    paypalReady = true;
                } catch (error) {
                    console.error('Error inicializando PayPal:', error);
                    simulatePayPalButton();
                }
            } else {
                simulatePayPalButton();
            }
        }
        
        // Simular botón de PayPal
        function simulatePayPalButton() {
            document.getElementById('paypal-button-container').innerHTML = 
                '<button class="btn btn-primary w-100" onclick="simulatePayPal()" style="background-color: #0070ba; border: none; padding: 12px; border-radius: 8px; color: white; font-weight: 600;"><i class="fab fa-paypal me-2"></i>Pagar con PayPal</button>';
            
            window.simulatePayPal = async function() {
                try {
                    showPreloader('Procesando pago con PayPal...');
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    const orderId = 'PAYPAL_' + Math.random().toString(36).substr(2, 9);
                    processOrder('paypal', orderId);
                } catch (error) {
                    hidePreloader();
                    console.error('Error con PayPal simulado:', error);
                    alert('Error procesando PayPal: ' + error.message);
                }
            };
            
            paypalReady = true;
        }
        
        // Procesar pedido
        function processOrder(paymentMethod, paymentId) {
            console.log('🚀 Procesando pedido REAL:', {paymentMethod, paymentId});
            
            // VALIDACIONES ESTRICTAS
            if (!paymentMethod || !paymentId) {
                console.error('❌ Datos faltantes:', {paymentMethod, paymentId});
                hidePreloader();
                alert('Error en los datos de pago');
                return;
            }
            
            // CONFIGURAR FORMULARIO
            $('#payment_method').val(paymentMethod);
            
            // LIMPIAR CAMPOS ANTERIORES
            $('input[name="payment_intent_id"]').remove();
            $('input[name="paypal_order_id"]').remove();
            
            // AGREGAR CAMPO CORRECTO
            if (paymentMethod === 'mp_card' || paymentMethod === 'google_pay' || paymentMethod === 'apple_pay') {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'payment_intent_id',
                    value: paymentId
                }).appendTo('#checkout-form');
                
                console.log('✅ PaymentIntent agregado:', paymentId);
            }
            
            // ENVIAR FORMULARIO
            showPreloader('Confirmando pedido...');
            
            setTimeout(() => {
                try {
                    console.log('📤 Enviando formulario a checkout.php');
                    document.getElementById('checkout-form').submit();
                } catch (error) {
                    console.error('❌ Error enviando formulario:', error);
                    hidePreloader();
                    alert('Error al confirmar pedido');
                }
            }, 1000);
        }
        
        // Eventos
        
        // Selección de dirección
        $('.address-card').click(function() {
            $('.address-card').removeClass('selected');
            $(this).addClass('selected');
            $(this).find('input[type="radio"]').prop('checked', true);
        });
        
        // ✅ SELECCIÓN DE MÉTODO DE PAGO ACTUALIZADA CON COMISIÓN
        $('.payment-method').click(function() {
            $('.payment-method').removeClass('selected');
            $(this).addClass('selected');
            
            // Ocultar todos los formularios
            $('.payment-form').removeClass('active');
            
            // Mostrar formulario específico
            const paymentType = $(this).data('payment');
            selectedPaymentMethod = paymentType;
            $('#payment_method').val(paymentType);
            
            $(`#${paymentType}_form`).addClass('active');
            
            // ✅ Actualizar la UI del selector de método de pago
            if (typeof window.updatePaymentMethodDisplay === 'function') {
                window.updatePaymentMethodDisplay(paymentType);
            }
            
            // ✅ ACTUALIZAR RESUMEN CON/SIN COMISIÓN
            updateSummary();
            
            // Inicializar según el tipo
            switch (paymentType) {
                case 'mp_card':
                    initCardElements();
                    break;
                case 'google_pay':
                    initGooglePay();
                    break;
                case 'apple_pay':
                    initApplePay();
                    break;
                case 'paypal':
                    initPayPal();
                    break;
                case 'efectivo':
                    break;
                case 'spei':
                    // SPEI no requiere inicialización especial
                    break;
            }
        });
        
        // Selección de propina
        $('.tip-option').click(function() {
            $('.tip-option').removeClass('selected');
            $(this).addClass('selected');

            const tipValue = $(this).data('value');
            $('#custom_tip').val(tipValue);
            updateSummary();
            updateBrickAmount();
        });
        
        // Propina personalizada
        $('#custom_tip').on('input', function() {
            $('.tip-option').removeClass('selected');
            updateSummary();
            updateBrickAmount();
        });
        
        // Botón de realizar pedido
        $('#submit-button').click(async function() {
            // Validar formulario
            if (!validateForm()) {
                return;
            }
            
            // Log para debugging
            console.log('✅ Método de pago seleccionado:', selectedPaymentMethod);
            
            // Procesar según el método de pago seleccionado
            switch (selectedPaymentMethod) {
                case 'mp_card':
                    await processCardPayment();
                    break;
                case 'paypal':
                    showPreloader('Procesando pago con PayPal...');
                    $('#paypal-button-container button').trigger('click');
                    break;
                case 'efectivo':
                    console.log('💵 Procesando pago en efectivo');
                    // Asegurar que el campo hidden esté configurado
                    $('#payment_method').val('efectivo');
                    // Procesar como efectivo
                    showPreloader('Procesando tu pedido...');
                    document.getElementById('checkout-form').submit();
                    break;
                case 'spei':
                    console.log('🏦 Procesando pago SPEI');
                    // Asegurar que el campo hidden esté configurado
                    $('#payment_method').val('spei');
                    // Mostrar preloader mientras se crea el pedido
                    showPreloader('Generando datos de pago SPEI...');
                    document.getElementById('checkout-form').submit();
                    break;
                default:
                    // Si no hay método seleccionado pero el formulario pasó validación,
                    // verificar si hay un método en el campo hidden
                    const hiddenMethod = $('#payment_method').val();
                    if (hiddenMethod === 'efectivo') {
                        console.log('💵 Procesando pago en efectivo (desde hidden)');
                        showPreloader('Procesando tu pedido...');
                        document.getElementById('checkout-form').submit();
                    } else {
                        alert('Por favor selecciona un método de pago.');
                    }
                    break;
            }
        });
        
        // ✅ VALIDAR FORMULARIO - VERSIÓN MEJORADA
        function validateForm() {
            const errorMessages = [];
            
            console.log('🔍 Validando formulario...');
            console.log('📍 Dirección seleccionada:', $("input[name='direccion_id']:checked").length > 0);
            console.log('💳 Método de pago global:', selectedPaymentMethod);
            console.log('💳 Método de pago hidden:', $('#payment_method').val());
            
            // Verificar dirección seleccionada
            if (!$("input[name='direccion_id']:checked").length) {
                errorMessages.push("Por favor selecciona una dirección de entrega");
            }
            
            // Verificar método de pago - aceptar tanto de variable global como de campo hidden
            const metodoPago = selectedPaymentMethod || $('#payment_method').val();
            if (!metodoPago) {
                errorMessages.push("Por favor selecciona un método de pago");
            } else {
                // Si existe en campo hidden pero no en variable global, sincronizar
                if (!selectedPaymentMethod && $('#payment_method').val()) {
                    selectedPaymentMethod = $('#payment_method').val();
                    console.log('✅ Variable sincronizada desde hidden:', selectedPaymentMethod);
                }
            }
            
            // Validaciones específicas por método de pago
           
            
            if (errorMessages.length > 0) {
                alert(errorMessages.join("\n"));
                return false;
            }
            
            return true;
        }
        
        // Guardar dirección
        $('#guardarDireccion').click(function() {
            const form = $('#formDireccion');
            const formData = form.serialize();
            
            // Validar campos requeridos
            const requiredFields = ['nombre_direccion', 'calle', 'numero', 'colonia', 'ciudad', 'codigo_postal', 'estado'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!$(`input[name="${field}"]`).val().trim()) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                alert('Por favor completa todos los campos requeridos.');
                return;
            }
            
            $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');
            $(this).prop('disabled', true);
            
            showPreloader('Guardando dirección...');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (data && data.success) {
                            hidePreloader();
                            alert('Dirección guardada correctamente');
                            window.location.reload();
                        } else {
                            const errorMsg = data && data.message ? data.message : 'Error desconocido';
                            hidePreloader();
                            alert('Error: ' + errorMsg);
                            $('#guardarDireccion').html('Guardar').prop('disabled', false);
                        }
                    } catch (e) {
                        console.error('Error al procesar la respuesta:', e);
                        hidePreloader();
                        alert('Error al procesar la respuesta. Por favor intenta nuevamente.');
                        $('#guardarDireccion').html('Guardar').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', status, error);
                    hidePreloader();
                    alert('Error de conexión. Por favor intenta nuevamente.');
                    $('#guardarDireccion').html('Guardar').prop('disabled', false);
                }
            });
        });
        
        // Inicializar al cargar la página
        updateSummary();
        
        // ✅ SISTEMA DE REPARTIDORES Y HORARIOS
        const horariosNegocio = <?php echo json_encode($horarios_negocio); ?>;
        const negocioAbierto = <?php echo $negocio_abierto ? 'true' : 'false'; ?>;
        const WEBSOCKET_API_URL = 'https://quickbite.shop/verUT/api/couriers-online';
        
        // Función para cargar estado de repartidores
        async function loadCouriersStatus() {
            try {
                const response = await fetch(WEBSOCKET_API_URL);
                const data = await response.json();
                
                const couriersStatus = document.getElementById('couriers-status');
                const couriersCountBadge = document.getElementById('couriers-count-badge');
                const couriersWarning = document.getElementById('couriers-warning');
                const couriersCount = document.getElementById('couriers-count');
                
                if (couriersStatus) {
                    couriersStatus.style.display = 'block';
                    
                    if (data.success && data.couriers_online > 0) {
                        couriersCountBadge.style.display = 'inline-block';
                        couriersWarning.style.display = 'none';
                        couriersCount.textContent = data.couriers_online;
                    } else {
                        couriersCountBadge.style.display = 'none';
                        couriersWarning.style.display = 'inline-block';
                    }
                }
                
                console.log('✅ Estado de repartidores:', data);
            } catch (error) {
                console.error('Error cargando estado de repartidores:', error);
                // En caso de error, ocultar indicador
                const couriersStatus = document.getElementById('couriers-status');
                if (couriersStatus) couriersStatus.style.display = 'none';
            }
        }
        
        // Función para validar hora de pickup contra horarios del negocio
        function validatePickupTime(time) {
            if (!time) return { valid: true, message: '' }; // Opcional
            
            const now = new Date();
            const dayOfWeek = now.getDay(); // 0=Domingo, 1=Lunes, etc.
            
            const horarioDia = horariosNegocio[dayOfWeek];
            
            if (!horarioDia || !horarioDia.activo) {
                return { valid: false, message: 'El negocio está cerrado hoy. Selecciona otro día.' };
            }
            
            const apertura = horarioDia.apertura.substring(0, 5);
            const cierre = horarioDia.cierre.substring(0, 5);
            
            if (time < apertura || time > cierre) {
                return { valid: false, message: `Hora inválida. Horario: ${apertura} - ${cierre}` };
            }
            
            // Validar que no sea una hora pasada
            const horaActual = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            if (time < horaActual) {
                return { valid: false, message: 'No puedes seleccionar una hora pasada.' };
            }
            
            return { valid: true, message: '' };
        }
        
        // Mostrar horarios del día actual
        function showTodayHours() {
            const now = new Date();
            const dayOfWeek = now.getDay();
            const horarioDia = horariosNegocio[dayOfWeek];
            const horarioInfo = document.getElementById('negocio-horario');
            
            if (horarioInfo) {
                if (horarioDia && horarioDia.activo) {
                    const apertura = horarioDia.apertura.substring(0, 5);
                    const cierre = horarioDia.cierre.substring(0, 5);
                    horarioInfo.textContent = `${apertura} - ${cierre}`;
                } else {
                    horarioInfo.textContent = 'Cerrado hoy';
                }
            }
        }
        
        // Manejar cambio de tipo de pedido
        $('input[name="tipo_pedido"]').on('change', function() {
            const tipoPedido = $(this).val();
            const pickupSection = document.getElementById('pickup-time-section');
            const couriersStatus = document.getElementById('couriers-status');

            if (tipoPedido === 'delivery') {
                if (pickupSection) pickupSection.style.display = 'none';
                if (couriersStatus) couriersStatus.style.display = 'block';
                loadCouriersStatus();
            } else {
                if (pickupSection) pickupSection.style.display = 'block';
                if (couriersStatus) couriersStatus.style.display = 'none';
                showTodayHours();
            }

            // ✅ ACTUALIZAR RESUMEN AL CAMBIAR TIPO DE PEDIDO
            updateSummary();
            // ✅ ACTUALIZAR BRICK DE MERCADOPAGO CON NUEVO MONTO
            updateBrickAmount();
        });
        
        // Validar hora de pickup al cambiar
        $('#pickup_time').on('change', function() {
            const time = $(this).val();
            const result = validatePickupTime(time);
            const errorDiv = document.getElementById('pickup-time-error');
            
            if (errorDiv) {
                if (!result.valid) {
                    errorDiv.textContent = result.message;
                    errorDiv.style.display = 'block';
                    $(this).addClass('is-invalid');
                } else {
                    errorDiv.style.display = 'none';
                    $(this).removeClass('is-invalid');
                }
            }
        });
        
        // Cargar estado de repartidores al inicio (si es delivery por defecto)
        if ($('#tipo_delivery').is(':checked')) {
            loadCouriersStatus();
        }

        // =====================================================
        // PEDIDOS PROGRAMADOS - Lógica de validación
        // =====================================================

        // Manejar cambio entre entrega ahora o programada
        $('input[name="tipo_entrega_tiempo"]').on('change', function() {
            const tipoEntrega = $(this).val();
            const programacionSection = document.getElementById('programacion-section');
            const esProgramado = document.getElementById('es_programado');
            const whatsappAlert = document.getElementById('programado-whatsapp-alert');

            if (tipoEntrega === 'programado') {
                if (programacionSection) programacionSection.style.display = 'block';
                if (esProgramado) esProgramado.value = '1';
                if (whatsappAlert) whatsappAlert.style.display = 'block';
                // Establecer fecha mínima como hoy
                const fechaInput = document.getElementById('fecha_programada');
                if (fechaInput && !fechaInput.value) {
                    fechaInput.value = new Date().toISOString().split('T')[0];
                }
            } else {
                if (programacionSection) programacionSection.style.display = 'none';
                if (esProgramado) esProgramado.value = '0';
                if (whatsappAlert) whatsappAlert.style.display = 'none';
                // Limpiar campos
                $('#fecha_programada').val('');
                $('#hora_programada').val('');
                $('#fecha_hora_programada').val('');
            }
        });

        // Validar fecha y hora programada
        function validarProgramacion() {
            const esProgramado = document.getElementById('es_programado')?.value === '1';
            if (!esProgramado) return { valid: true, message: '' };

            const fecha = document.getElementById('fecha_programada')?.value;
            const hora = document.getElementById('hora_programada')?.value;
            const tiempoMinimo = parseInt(document.getElementById('tiempo_minimo_programacion')?.value || '60');
            const errorDiv = document.getElementById('programacion-error');

            if (!fecha || !hora) {
                return { valid: false, message: 'Por favor selecciona fecha y hora para la entrega programada.' };
            }

            // Crear objeto Date para la fecha/hora programada
            const fechaHoraProgramada = new Date(fecha + 'T' + hora);
            const ahora = new Date();

            // Calcular diferencia en minutos
            const diffMinutos = (fechaHoraProgramada - ahora) / (1000 * 60);

            if (diffMinutos < tiempoMinimo) {
                return {
                    valid: false,
                    message: `Debes programar con al menos ${tiempoMinimo} minutos de anticipación.`
                };
            }

            // Validar que esté dentro del horario del negocio
            const diaSemana = fechaHoraProgramada.getDay();
            const horarioDia = horariosNegocio[diaSemana];

            if (!horarioDia || !horarioDia.activo) {
                return {
                    valid: false,
                    message: 'El negocio está cerrado el día seleccionado.'
                };
            }

            const horaSeleccionada = hora;
            const apertura = horarioDia.apertura.substring(0, 5);
            const cierre = horarioDia.cierre.substring(0, 5);

            if (horaSeleccionada < apertura || horaSeleccionada > cierre) {
                return {
                    valid: false,
                    message: `La hora debe estar entre ${apertura} y ${cierre}.`
                };
            }

            // Si todo es válido, guardar el datetime completo
            const fechaHoraCompleta = fecha + ' ' + hora + ':00';
            document.getElementById('fecha_hora_programada').value = fechaHoraCompleta;

            return { valid: true, message: '' };
        }

        // Validar al cambiar fecha u hora
        $('#fecha_programada, #hora_programada').on('change', function() {
            const result = validarProgramacion();
            const errorDiv = document.getElementById('programacion-error');

            if (errorDiv) {
                if (!result.valid) {
                    errorDiv.textContent = result.message;
                    errorDiv.style.display = 'block';
                } else {
                    errorDiv.style.display = 'none';
                }
            }
        });
        
        // Actualizar cada 30 segundos
        setInterval(() => {
            if ($('#tipo_delivery').is(':checked')) {
                loadCouriersStatus();
            }
        }, 30000);
        
        // Manejar redimensionamiento de ventana
        $(window).resize(function() {
            if (window.innerWidth > 768) {
                // En desktop, siempre mostrar el resumen
                document.getElementById('summary-content').classList.remove('collapsed');
            } else {
                // En móvil, mantener el estado colapsado por defecto
                if (!document.getElementById('summary-content').classList.contains('collapsed')) {
                    document.getElementById('summary-content').classList.add('collapsed');
                    document.getElementById('summary-arrow').classList.remove('fa-chevron-up');
                    document.getElementById('summary-arrow').classList.add('fa-chevron-down');
                }
            }
        });
        
        console.log('✅ Checkout JavaScript inicializado correctamente con comisión de MercadoPago');
    });

    // =====================================================
    // SAN VALENTIN - Selector de Horarios
    // =====================================================

    // Seleccionar time slot
    function selectTimeSlot(element) {
        // Remover selección previa
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });

        // Seleccionar este
        element.classList.add('selected');

        // Actualizar valores ocultos
        const fecha = document.getElementById('fecha_programada').value;
        const hora = element.dataset.time;

        if (fecha && hora) {
            document.getElementById('es_programado').value = '1';
            document.getElementById('fecha_hora_programada').value = fecha + ' ' + hora + ':00';
        }
    }

    // Actualizar horarios disponibles según la fecha
    function actualizarHorarios() {
        const fechaInput = document.getElementById('fecha_programada');
        const fecha = fechaInput.value;

        if (!fecha) return;

        const fechaSeleccionada = new Date(fecha + 'T12:00:00');
        const hoy = new Date();
        const esHoy = fechaSeleccionada.toDateString() === hoy.toDateString();

        // Obtener hora actual
        const horaActual = hoy.getHours();

        // Actualizar disponibilidad de slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            const horaSlot = parseInt(slot.dataset.time.split(':')[0]);

            // Si es hoy, deshabilitar horas pasadas
            if (esHoy && horaSlot <= horaActual) {
                slot.classList.add('disabled');
                slot.onclick = null;
            } else {
                slot.classList.remove('disabled');
                slot.onclick = function() { selectTimeSlot(this); };
            }
        });

        // Limpiar selección previa si el slot seleccionado ya no es válido
        const selectedSlot = document.querySelector('.time-slot.selected');
        if (selectedSlot && selectedSlot.classList.contains('disabled')) {
            selectedSlot.classList.remove('selected');
            document.getElementById('fecha_hora_programada').value = '';
        }
    }

    // Inicializar fecha con hoy
    document.addEventListener('DOMContentLoaded', function() {
        const fechaInput = document.getElementById('fecha_programada');
        if (fechaInput && !fechaInput.value) {
            fechaInput.value = new Date().toISOString().split('T')[0];
            actualizarHorarios();
        }

        // Inicializar contador de caracteres del mensaje
        const mensajeRegalo = document.getElementById('mensaje_regalo');
        const mensajeContador = document.getElementById('mensaje-contador');
        if (mensajeRegalo && mensajeContador) {
            mensajeRegalo.addEventListener('input', function() {
                mensajeContador.textContent = this.value.length;
                if (this.value.length > 250) {
                    mensajeContador.style.color = '#e74c3c';
                } else {
                    mensajeContador.style.color = '#6c757d';
                }
            });
        }

        // Manejar checkbox de modo anónimo
        const modoAnonimo = document.getElementById('modo_anonimo');
        const remitenteSection = document.getElementById('remitente-section');
        if (modoAnonimo && remitenteSection) {
            modoAnonimo.addEventListener('change', function() {
                remitenteSection.style.display = this.checked ? 'none' : 'block';
                if (this.checked) {
                    document.getElementById('nombre_remitente').value = '';
                }
            });
        }

        // Inicializar opciones de envoltura
        document.querySelectorAll('.gift-wrap-option label').forEach(label => {
            label.addEventListener('click', function() {
                const input = this.previousElementSibling;
                if (input && input.type === 'radio') {
                    input.checked = true;
                }
            });
        });
    });

    // Toggle opciones de regalo
    function toggleGiftOptions() {
        const esRegalo = document.getElementById('es_regalo');
        const giftOptions = document.getElementById('gift-options');

        if (esRegalo && giftOptions) {
            if (esRegalo.checked) {
                giftOptions.style.display = 'grid';
                console.log("Opciones de regalo: MOSTRANDO");
            } else {
                giftOptions.style.display = 'none';
                console.log("Opciones de regalo: OCULTANDO");
                // Limpiar opciones al desactivar
                document.getElementById('modo_anonimo').checked = false;
                document.getElementById('nombre_remitente').value = '';
                document.getElementById('mensaje_regalo').value = '';
                document.getElementById('mensaje-contador').textContent = '0';
                document.getElementById('wrap_normal').checked = true;
                document.getElementById('remitente-section').style.display = 'block';
            }
        }
    }
    </script>
<?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>
