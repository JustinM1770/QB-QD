<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'checkout_errors.log');

// Función para loguear errores para debugging
function logError($message, $data = null) {
    $log_message = "[CHECKOUT ERROR] " . $message;
    if ($data !== null) {
        $log_message .= " | Data: " . json_encode($data);
    }
    error_log($log_message);
}
// Verificar si el usuario está logueado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
// Verificar si hay productos en el carrito
if (!isset($_SESSION['carrito']['items']) || empty($_SESSION['carrito']['items'])) {
    header("location: carrito.php");
// Incluir configuración y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Producto.php';
require_once 'models/Direccion.php';
require_once 'models/MetodoPago.php';
require_once 'models/Pedido.php';
// Configuración de MercadoPago
require_once 'vendor/autoload.php';
$mp_config = require_once 'config/mercadopago.php';
// Configurar MercadoPago (usando la nueva versión del SDK)
try {
    MercadoPago\MercadoPagoConfig::setAccessToken($mp_config['access_token']);
    MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(MercadoPago\MercadoPagoConfig::LOCAL);
    logError("✅ MercadoPago SDK configurado correctamente", [
        'access_token_prefix' => substr($mp_config['access_token'], 0, 15),
        'public_key_prefix' => substr($mp_config['public_key'], 0, 15)
    ]);
} catch (Exception $e) {
    logError("❌ Error configurando MercadoPago SDK: " . $e->getMessage());
// Conectar a BD
$database = new Database();
$db = $database->getConnection();
// Obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION['id_usuario'];
$usuario->obtenerPorId();
// ✅ VALIDAR EMAIL DEL USUARIO PARA MERCADOPAGO
if (empty($usuario->email) || !filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) {
    // Asignar email por defecto si no tiene uno válido
    $usuario->email = 'usuario' . $usuario->id_usuario . '@quickbite.com.mx';
    logError("Email del usuario inválido o faltante, usando email por defecto", [
        'id_usuario' => $usuario->id_usuario,
        'email_original' => $usuario->email ?? 'null',
        'email_asignado' => $usuario->email
// ✅ VERIFICAR ESTADO DE MEMBRESÍA DEL USUARIO
require_once 'models/Membership.php';
$membership = new Membership($db);
$membership->id_usuario = $_SESSION['id_usuario'];
$esMiembroActivo = $membership->isActive();
logError("Estado de membresía del usuario", [
'id_usuario' => $_SESSION['id_usuario'],
'es_miembro_activo' => $esMiembroActivo ? 'Sí' : 'No'
]);
// Verificar si el usuario tiene un pedido pendiente
$pedido = new Pedido($db);
$pedidoActivo = null;
$pedidosPendientes = $pedido->obtenerPorUsuario($usuario->id_usuario);
foreach ($pedidosPendientes as $p) {
    $esPickup = ($p['tipo_pedido'] === 'pickup');
    $estadoMaximo = $esPickup ? 4 : 6; // Pickup máximo estado 4, delivery máximo estado 6
    
    if ($p['id_estado'] >= 1 && $p['id_estado'] < $estadoMaximo) {
        $pedidoActivo = $p;
        break;
$repartidores_disponibles = false;
$total_repartidores_activos = 0;
    // Consultar repartidores activos en el sistema
    $query_repartidores = "SELECT COUNT(*) as total_activos 
                          FROM repartidores r 
                          INNER JOIN usuarios u ON r.id_repartidor = u.id_usuario 
                          WHERE r.activo = 1 
                          AND u.activo = 1";
    $stmt_repartidores = $db->prepare($query_repartidores);
    $stmt_repartidores->execute();
    $resultado_repartidores = $stmt_repartidores->fetch(PDO::FETCH_ASSOC);
    $total_repartidores_activos = (int)($resultado_repartidores['total_activos'] ?? 0);
    $repartidores_disponibles = $total_repartidores_activos > 0;
    logError("Verificación de repartidores disponibles", [
        'repartidores_activos' => $total_repartidores_activos,
        'delivery_disponible' => $repartidores_disponibles ? 'Sí' : 'No'
    logError("Error verificando repartidores disponibles: " . $e->getMessage());
    // En caso de error, asumir que no hay repartidores disponibles por seguridad
    $repartidores_disponibles = false;
    $total_repartidores_activos = 0;
if ($pedidoActivo) {
    echo '<div class="alert alert-warning" role="alert" style="margin: 20px;">';
    echo 'Tienes un pedido en curso. Por favor, ';
    echo '<a href="confirmacion_pedido.php?id=' . htmlspecialchars($pedidoActivo['id_pedido']) . '">haz clic aquí para ver el estado de tu pedido</a>.';
    echo '</div>';
    // No permitir continuar con nuevo pedido
// Manejar solicitudes AJAX para direcciones
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_address') {
    header('Content-Type: application/json');
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
}
            echo json_encode(['success' => true, 'message' => 'Dirección guardada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar la dirección']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
// ✅ SOLUCIÓN COMPLETA: Verificar y validar el negocio de forma robusta
$negocio = null;
$negocio_id = 0;
logError("🔍 Iniciando validación completa del negocio");
// Función para validar negocio existe y está activo
function validarNegocioExiste($db, $id_negocio) {
        $stmt = $db->prepare("SELECT id_negocio, nombre, activo, costo_envio, telefono FROM negocios WHERE id_negocio = ? AND activo = 1");
        $stmt->execute([(int)$id_negocio]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            logError("✅ Negocio validado", $result);
            return $result;
        logError("❌ Negocio no encontrado o inactivo", ['id_negocio' => $id_negocio]);
        return false;
        logError("❌ Error validando negocio", ['id_negocio' => $id_negocio, 'error' => $e->getMessage()]);
// Función para obtener negocio desde productos del carrito con validación estricta
function obtenerNegocioDesdeCarrito($db) {
    if (empty($_SESSION['carrito']['items'])) {
        logError("❌ Carrito vacío");
        return null;
    logError("🔍 Buscando negocio desde productos del carrito", ['total_items' => count($_SESSION['carrito']['items'])]);
    // Obtener todos los productos únicos del carrito
    $productos_ids = array_unique(array_column($_SESSION['carrito']['items'], 'id_producto'));
    if (empty($productos_ids)) {
        logError("❌ No hay IDs de productos válidos en el carrito");
    // Crear placeholders para la consulta
    $placeholders = str_repeat('?,', count($productos_ids) - 1) . '?';
        // Consulta para obtener el negocio de todos los productos
        $stmt = $db->prepare("
            SELECT p.id_producto, p.nombre as producto_nombre, p.id_negocio, 
                   n.nombre as negocio_nombre, n.activo, n.costo_envio, n.telefono
            FROM productos p 
            INNER JOIN negocios n ON p.id_negocio = n.id_negocio 
            WHERE p.id_producto IN ($placeholders) AND n.activo = 1
            GROUP BY n.id_negocio
            ORDER BY COUNT(p.id_producto) DESC
            LIMIT 1
        ");
        $stmt->execute($productos_ids);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($resultado) {
            logError("✅ Negocio encontrado desde carrito", $resultado);
            return $resultado;
            logError("❌ No se encontró negocio activo para los productos del carrito", ['productos_ids' => $productos_ids]);
            return null;
        logError("❌ Error obteniendo negocio desde carrito", ['error' => $e->getMessage(), 'productos_ids' => $productos_ids]);
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
        $negocio->telefono = $negocio_data['telefono'] ?? '';
        logError("✅ Negocio de sesión validado exitosamente", ['negocio_id' => $negocio_id, 'nombre' => $negocio->nombre, 'telefono' => $negocio->telefono]);
// PASO 2: Si no se pudo validar desde sesión, buscar desde productos
if (!$negocio || $negocio_id <= 0) {
    logError("🔍 Negocio de sesión no válido, buscando desde productos...");
    $negocio_data = obtenerNegocioDesdeCarrito($db);
        $negocio->nombre = $negocio_data['negocio_nombre'];
        // Actualizar sesión con el negocio correcto
        $_SESSION['carrito']['negocio_id'] = $negocio_id;
        $_SESSION['carrito']['negocio_nombre'] = $negocio->nombre;
        logError("✅ Negocio recuperado desde productos", ['negocio_id' => $negocio_id, 'nombre' => $negocio->nombre, 'telefono' => $negocio->telefono]);
// PASO 3: VALIDACIÓN FINAL CRÍTICA
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
// PASO 4: Doble verificación que el negocio existe en BD
$stmt_final_check = $db->prepare("SELECT COUNT(*) FROM negocios WHERE id_negocio = ? AND activo = 1");
$stmt_final_check->execute([$negocio_id]);
if ($stmt_final_check->fetchColumn() == 0) {
    logError("❌ ERROR FINAL: Negocio no existe en BD después de validaciones", ['negocio_id' => $negocio_id]);
    echo 'Error: El negocio seleccionado ya no está disponible.';
    echo '<br><a href="index.php" class="btn btn-primary mt-2">Seleccionar otro negocio</a>';
logError("🎉 Negocio validado completamente", [
    'id_negocio' => $negocio_id,
    'nombre' => $negocio->nombre ?? 'Sin nombre',
    'costo_envio' => $negocio->costo_envio ?? 0
// Valores seguros después de validación completa
$costo_envio = isset($negocio->costo_envio) ? (float)$negocio->costo_envio : 0;
$direcciones = [];
// Obtener direcciones del usuario
    $direccion = new Direccion($db);
    $direccion->id_usuario = $_SESSION['id_usuario'];
    $direcciones = $direccion->obtenerPorUsuario();
    logError("Error al obtener direcciones: " . $e->getMessage());
// Calcular totales
$subtotal = 0;
$impuesto = 0; // Inicializar impuesto para evitar warnings
$cargo_servicio = 0; // Inicializar cargo por servicio para evitar warnings
$propina = 0; // Valor por defecto: sin propina
// ✅ Porcentaje de procesamiento para pagos con tarjeta (MercadoPago)
$mp_fee_percentage = 0.0336; // 3.36% para México (comisión correcta)
$mp_fixed_fee = 2.50; // Cuota fija de MercadoPago México: $2.50 MXN
// Calcular subtotal - usar precio completo sin reducción
foreach ($_SESSION['carrito']['items'] as $item) {
    $precio = isset($item['precio']) ? (float)$item['precio'] : 0;
    $cantidad = isset($item['cantidad']) ? (int)$item['cantidad'] : 0;
    $subtotal += $precio * $cantidad;
// ✅ APLICAR LÓGICA DE MEMBRESÍA Y CARGO POR SERVICIO
logError("Aplicando lógica de membresía y cargo por servicio", [
    'subtotal' => $subtotal,
    'costo_envio_base' => $costo_envio,
    'es_miembro_activo' => $esMiembroActivo
// ✅ DETERMINAR TIPO DE PEDIDO DESDE EL INICIO
$tipo_pedido_inicial = 'delivery'; // Por defecto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["tipo_pedido"])) {
    $tipo_pedido_inicial = $_POST["tipo_pedido"];
logError("Tipo de pedido detectado", [
    'tipo_pedido' => $tipo_pedido_inicial,
    'es_pickup' => $tipo_pedido_inicial === 'pickup'
// 1. Calcular costo de envío según tipo de pedido y membresía
if ($tipo_pedido_inicial === 'pickup') {
    // ✅ PICKUP: SIEMPRE envío gratis
    $costo_envio = 0;
}
    logError("Envío gratis aplicado por ser pedido PICKUP");
} else if ($esMiembroActivo && $subtotal >= 300) {
    // ✅ DELIVERY + MEMBRESÍA + PEDIDO >= $300: envío gratis
    logError("Envío gratis aplicado por membresía y pedido mayor a $300", [
        'subtotal' => $subtotal,
}
        'es_miembro' => 'Sí'
} else {
    // ✅ DELIVERY normal: paga envío completo
    $costo_envio = isset($negocio->costo_envio) ? (float)$negocio->costo_envio : 0;
    logError("Envío normal aplicado", [
        'tipo_pedido' => $tipo_pedido_inicial,
        'es_miembro' => $esMiembroActivo ? 'Sí' : 'No',
        'costo_envio' => $costo_envio
// 2. Cargo por servicio (15%) solo si NO tiene membresía
if (!$esMiembroActivo) {
    $cargo_servicio = $subtotal * 0.15;
    logError("Cargo por servicio del 15% aplicado por no tener membresía", [
        'cargo_servicio' => $cargo_servicio,
        'porcentaje' => '15%'
    $cargo_servicio = 0;
    logError("Sin cargo por servicio por tener membresía activa");
// ✅ 3. COMISIÓN DE MERCADOPAGO (3.36% + $2.50 MXN)
// Esta comisión se aplica SIEMPRE a pagos con tarjeta, independientemente de la membresía
// Se calculará dinámicamente en JavaScript según el método de pago seleccionado
logError("Costos finales calculados", [
    'costo_envio_final' => $costo_envio,
    'cargo_servicio_final' => $cargo_servicio,
    'es_miembro' => $esMiembroActivo ? 'Sí' : 'No'
// Variables para mensajes de error
$direccion_err = $metodo_pago_err = $propina_err = $general_err = "";
// Procesar pedido cuando se envíe el formulario (ACTUALIZADO para incluir comisión)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    logError("Formulario enviado. Procesando pedido...");
    // ✅ RE-VALIDAR NEGOCIO ANTES DE PROCESAR (CRÍTICO)
    $negocio_revalidado = validarNegocioExiste($db, $negocio_id);
    if (!$negocio_revalidado) {
        $general_err = "El negocio seleccionado ya no está disponible. Por favor selecciona otro.";
}
        logError("❌ Negocio no válido al procesar pedido", ['negocio_id' => $negocio_id]);
    } else {
        // Actualizar datos del negocio
        $negocio->id_negocio = (int)$negocio_revalidado['id_negocio'];
        $negocio->nombre = $negocio_revalidado['nombre'];
        $negocio->costo_envio = $negocio_revalidado['costo_envio'] ?? 0;
        $costo_envio = (float)$negocio->costo_envio;
        logError("✅ Negocio re-validado para procesamiento", $negocio_revalidado);
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
            logError("✅ Dirección validada", ['id_direccion' => $id_direccion]);
    // Validar método de pago seleccionado
    if (empty($general_err) && (empty($_POST["payment_method"]) || !isset($_POST["payment_method"]))) {
        $metodo_pago_err = "Por favor selecciona un método de pago.";
        $metodo_pago_seleccionado = trim($_POST["payment_method"]);
        logError("Método de pago seleccionado: " . $metodo_pago_seleccionado);
    // Validar propina
    if (empty($general_err) && isset($_POST["propina"])) {
        $propina = floatval($_POST["propina"]);
        if ($propina < 0) {
            $propina_err = "La propina no puede ser negativa.";
    // Procesar pedido si no hay errores
    if (empty($direccion_err) && empty($metodo_pago_err) && empty($propina_err) && empty($general_err)) {
        // ✅ CALCULAR TOTAL BASE (la comisión de MercadoPago se agregará si aplica)
        $total_base = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto;
        $total = $total_base; // Por defecto, sin comisiones adicionales
        logError("Total base calculado: Subtotal=$subtotal, Envío=$costo_envio, Propina=$propina, Servicio=$cargo_servicio, Impuesto=$impuesto, Total=$total");
        $payment_processed = false;
        $payment_details = [];
        // Procesar según el método de pago seleccionado
        switch ($metodo_pago_seleccionado) {
            case 'mercadopago':
                if (isset($_POST['payment_id'])) {
                    try {
                        // Usar la nueva versión del SDK
                        $paymentClient = new MercadoPago\Client\Payment\PaymentClient();
                        
                        $payment_id = $_POST['payment_id'];
                        // Verificar el estado del pago
                        $payment = $paymentClient->get($payment_id);
                        if ($payment->status == 'approved') {
                            // ✅ Calcular comisión MercadoPago sobre total base
                            $mp_fee = ($total_base * $mp_fee_percentage) + $mp_fixed_fee;
                            $total = $total_base + $mp_fee; // Total incluyendo comisión MP
                            
                            $payment_processed = true;
                            $payment_details = [
                                'method' => 'mercadopago',
                                'payment_id' => $payment_id,
                                'amount' => $payment->transaction_amount,
                                'currency' => 'MXN',
                                'mp_fee' => $mp_fee,
                                'status' => $payment->status,
                                'payment_method_id' => $payment->payment_method_id,
                                'installments' => $payment->installments
                            ];
                            logError("Pago MercadoPago procesado", [
                                'total_final' => $total
}
                            ]);
                        } else {
                            $general_err = "El pago no fue aprobado. Estado: " . $payment->status;
                            logError("Pago no aprobado", [
                                'payment_id' => $payment_id
                        }
                    } catch (Exception $e) {
                        $general_err = "Error procesando el pago: " . $e->getMessage();
                        logError("Error en MercadoPago: " . $e->getMessage());
                    }
                } else {
                    $general_err = "No se recibió el ID de pago de MercadoPago";
                }
                break;
                
            case 'paypal':
                // Pago con PayPal (sin comisión adicional)
                if (isset($_POST['paypal_order_id'])) {
                    $payment_processed = true;
                    $payment_details = [
                        'method' => 'paypal',
                        'paypal_order_id' => $_POST['paypal_order_id']
                    ];
                    // Total permanece como total_base (sin comisiones adicionales)
                    logError("Pago PayPal procesado. Order ID: " . $_POST['paypal_order_id']);
                    $general_err = "No se recibió la confirmación de PayPal.";
            case 'efectivo':
                // Pago en efectivo (sin comisión)
                $monto_efectivo = isset($_POST['monto_efectivo']) ? floatval($_POST['monto_efectivo']) : $total_base;
                if ($monto_efectivo < $total_base) {
                    $general_err = "El monto en efectivo debe ser igual o mayor al total del pedido.";
                        'method' => 'efectivo',
                        'monto_efectivo' => $monto_efectivo
                    logError("Pago en efectivo procesado. Monto: $" . number_format($monto_efectivo, 2));
            default:
                $general_err = "Método de pago no válido.";
        if ($payment_processed) {
            try {
                // Iniciar transacción si es posible
                if (method_exists($db, 'beginTransaction')) {
                    $db->beginTransaction();
                logError("Iniciando creación de pedido con pago procesado", [
                    'metodo_pago' => $metodo_pago_seleccionado,
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
                            $total_base = $subtotal + $impuesto + $propina;
                            if ($metodo_pago_seleccionado === 'mercadopago') {
                                $mp_fee = ($total_base * $mp_fee_percentage) + $mp_fixed_fee;
}
                                $total = $total_base + $mp_fee;
                            } else {
                                $total = $total_base;
                            }
                            logError("Aplicando descuentos por referidos", [
                                'descuento_aplicado' => true,
                                'nuevo_total' => $total,
                                'metodo_pago' => $metodo_pago_seleccionado
                } catch (Exception $e) {
                    logError("Error aplicando descuentos por referidos (continuando sin descuento)", ['error' => $e->getMessage()]);
                // ✅ CREAR EL PEDIDO CON VALIDACIONES FINALES CRÍTICAS
                // TRIPLE VALIDACIÓN ANTES DE CREAR EL PEDIDO
                logError("🔍 Triple validación antes de crear pedido", [
                    'usuario_id' => $_SESSION['id_usuario'],
                    'negocio_id' => $negocio_id,
                    'direccion_id' => $id_direccion
                // Validar usuario existe
                $stmt_user = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_usuario = ?");
                $stmt_user->execute([(int)$_SESSION['id_usuario']]);
                if ($stmt_user->fetchColumn() == 0) {
                    throw new Exception("Usuario no válido");
                // Validar negocio existe y está activo
                $stmt_neg = $db->prepare("SELECT COUNT(*) FROM negocios WHERE id_negocio = ? AND activo = 1");
                $stmt_neg->execute([(int)$negocio_id]);
                if ($stmt_neg->fetchColumn() == 0) {
                    throw new Exception("Negocio no válido o inactivo: ID $negocio_id");
                // Validar dirección existe y pertenece al usuario
                $stmt_dir = $db->prepare("SELECT COUNT(*) FROM direcciones_usuario WHERE id_direccion = ? AND id_usuario = ?");
                $stmt_dir->execute([(int)$id_direccion, (int)$_SESSION['id_usuario']]);
                if ($stmt_dir->fetchColumn() == 0) {
                    throw new Exception("Dirección no válida");
                // Validar estado pedido existe
                $stmt_est = $db->prepare("SELECT COUNT(*) FROM estados_pedido WHERE id_estado = 1");
                $stmt_est->execute();
                if ($stmt_est->fetchColumn() == 0) {
                    throw new Exception("Estado de pedido no válido");
                logError("✅ Triple validación exitosa, procediendo a crear pedido");
                $pedido = new Pedido($db);
                // Asignar propiedades con validación estricta
                $pedido->id_usuario = (int)$_SESSION['id_usuario'];
                $pedido->id_negocio = (int)$negocio_id;
                $pedido->id_estado = 1; // Estado "pendiente"
                $pedido->id_direccion = (int)$id_direccion;
                $pedido->id_repartidor = null; // Inicialmente sin repartidor
                // Asignar id_metodo_pago correctamente
                if ($metodo_pago_seleccionado === 'mercadopago') {
                    // Intentar obtener método de pago predeterminado del usuario para MercadoPago
                        require_once 'models/MetodoPago.php';
                        $metodoPagoModel = new MetodoPago($db);
                        $metodoPagoModel->id_usuario = (int)$_SESSION['id_usuario'];
                        if ($metodoPagoModel->obtenerPredeterminado()) {
                            $pedido->id_metodo_pago = (int)$metodoPagoModel->id_metodo_pago;
                            $pedido->id_metodo_pago = null;
                        logError("Error obteniendo método de pago predeterminado: " . $e->getMessage());
                        $pedido->id_metodo_pago = null;
                    // PayPal y Efectivo no necesitan id_metodo_pago
                    $pedido->id_metodo_pago = null;
                $pedido->metodo_pago = $metodo_pago_seleccionado;
                $pedido->total_productos = (float)$subtotal;
                $pedido->impuestos = (float)$impuesto;
                $pedido->costo_envio = (float)$costo_envio;
                $pedido->cargo_servicio = (float)$cargo_servicio;
                $pedido->propina = (float)$propina;
                $pedido->monto_total = (float)$total;
                $pedido->tipo_pedido = isset($_POST["tipo_pedido"]) ? $_POST["tipo_pedido"] : 'delivery';
                $pedido->pickup_time = isset($_POST["pickup_time"]) && !empty($_POST["pickup_time"]) ? $_POST["pickup_time"] : null;
                // ✅ INSTRUCCIONES ESPECIALES MEJORADAS
                $instrucciones = [];
                if (isset($_POST["tipo_pedido"])) {
                    $tipo_texto = $_POST["tipo_pedido"] === "pickup" ? "PickUp (Recoger en tienda)" : "Delivery (Envío a domicilio)";
                    $instrucciones[] = "Tipo de pedido: " . $tipo_texto;
                if (isset($_POST["instrucciones"]) && !empty(trim($_POST["instrucciones"]))) {
                    $instrucciones[] = trim($_POST["instrucciones"]);
                // ✅ AGREGAR INFORMACIÓN DE COMISIÓN A LAS INSTRUCCIONES (solo MercadoPago)
                if ($metodo_pago_seleccionado === 'mercadopago' && isset($payment_details['mp_fee']) && $payment_details['mp_fee'] > 0) {
                    $instrucciones[] = "Comisión MercadoPago: $" . number_format($payment_details['mp_fee'], 2);
                $pedido->instrucciones_especiales = implode(". ", $instrucciones);
                $pedido->payment_details = json_encode($payment_details);
                // Si es pago en efectivo, guardar el monto
                if ($metodo_pago_seleccionado === 'efectivo') {
                    $pedido->monto_efectivo = (float)($payment_details['monto_efectivo'] ?? $total);
                    $pedido->monto_efectivo = 0.00;
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
                    'id_metodo_pago' => $pedido->id_metodo_pago
                // ✅ CREAR EL PEDIDO
                $resultado = $pedido->crear();
                if ($resultado === true) {
                        logError("Pedido creado exitosamente", [
                            'id_pedido' => $pedido->id_pedido,
                            'total_final' => $total,
                            'metodo_pago' => $metodo_pago_seleccionado
                        ]);                    // ✅ AGREGAR DETALLES DEL PEDIDO
                    $allDetailsAdded = true;
                    $detalles_agregados = 0;
                    
                    foreach ($_SESSION['carrito']['items'] as $item) {
                        // Validar item antes de agregar
                        if (!isset($item['id_producto']) || !isset($item['cantidad']) || !isset($item['precio'])) {
                            logError("Item del carrito incompleto", ['item' => $item]);
                            continue;
                        // Verificar que el producto existe y pertenece al negocio correcto
                        $stmt_prod = $db->prepare("SELECT COUNT(*) FROM productos WHERE id_producto = ? AND id_negocio = ?");
                        $stmt_prod->execute([(int)$item['id_producto'], (int)$negocio_id]);
                        if ($stmt_prod->fetchColumn() == 0) {
                            logError("Producto no pertenece al negocio", [
                                'id_producto' => $item['id_producto'],
                                'id_negocio' => $negocio_id
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
                            logError("Error agregando detalle", ['item' => $item]);
                            $allDetailsAdded = false;
                            break;
                    if ($allDetailsAdded && $detalles_agregados > 0) {
                        // ✅ CONFIRMAR TRANSACCIÓN
                        if (method_exists($db, 'commit')) {
                            $db->commit();
                        logError("Pedido completado exitosamente", [
                            'detalles_agregados' => $detalles_agregados,
                            'metodo_pago' => $metodo_pago_seleccionado,
                            'total_final' => $total
                        ]);
                        // Actualizar estado de referido si aplica
                        try {
                            if (isset($referral)) {
}
                                $referral->markOrderMade($userId);
                        } catch (Exception $e) {
                            logError("Error actualizando referido (no crítico)", ['error' => $e->getMessage()]);
                        // ✅ ENVIAR NOTIFICACIÓN POR WHATSAPP AL RESTAURANTE
                            logError("🔍 Iniciando proceso de WhatsApp", [
                                'negocio_id' => $negocio->id_negocio ?? 'null',
                                'telefono_negocio' => $negocio->telefono ?? 'null'
                            $telefono_negocio = $negocio->telefono ?? '';
                            if (!empty($telefono_negocio)) {
                                logError("✅ Teléfono encontrado, preparando envío", [
                                    'telefono' => $telefono_negocio,
                                    'id_pedido' => $pedido->id_pedido
                                ]);
                                
                                // Obtener información de la dirección
                                $direccion_info = "";
                                foreach ($direcciones as $dir) {
                                    if ($dir['id_direccion'] == $id_direccion) {
                                        $direccion_info = $dir['calle'] . " " . $dir['numero'] . ", " . $dir['colonia'] . ", " . $dir['ciudad'];
}
                                        break;
                                    }
                                }
                                // 🔥 ENVIAR MENSAJE AUTOMÁTICO POR WHATSAPP
                                require_once __DIR__ . '/api/whatsapp/WhatsAppBot.php';
                                $whatsappBot = new WhatsAppBot();
                                logError("🤖 WhatsAppBot instanciado", [
                                    'bot_url' => 'http://localhost:3000'
                                // Preparar detalles de productos
                                $items_detalle = [];
                                foreach ($_SESSION['carrito']['items'] as $item) {
                                    $items_detalle[] = $item['cantidad'] . "x " . $item['nombre'] . " - $" . number_format($item['precio'], 2);
                                logError("📦 Items preparados para WhatsApp", [
                                    'cantidad_items' => count($items_detalle)
                                // Enviar notificación
                                $resultado_whatsapp = $whatsappBot->notificarRestaurante(
                                    $telefono_negocio,
                                    $pedido->id_pedido,
                                    $usuario->nombre . " " . ($usuario->apellido ?? ''),
                                    $items_detalle,
                                    $pedido->monto_total,
                                    [
                                        'tipo_pedido' => isset($_POST["tipo_pedido"]) && $_POST["tipo_pedido"] === "pickup" ? "PickUp (Recoger en tienda)" : "Delivery (Envío a domicilio)",
                                        'hora_pickup' => isset($_POST["pickup_time"]) && !empty($_POST["pickup_time"]) ? htmlspecialchars(trim($_POST["pickup_time"])) : null,
                                        'direccion' => $direccion_info,
                                        'instrucciones' => $pedido->instrucciones_especiales ?? '',
                                        'metodo_pago' => $metodo_pago_seleccionado,
                                        'comision_mp' => ($metodo_pago_seleccionado === 'mercadopago' && isset($payment_details['mp_fee'])) ? $payment_details['mp_fee'] : 0
                                    ]
                                );
                                logError("📨 Resultado de WhatsApp recibido", [
                                    'success' => $resultado_whatsapp['success'] ?? false,
                                    'resultado_completo' => $resultado_whatsapp
                                if ($resultado_whatsapp['success']) {
                                    logError("✅ Notificación WhatsApp enviada exitosamente", [
                                        'id_pedido' => $pedido->id_pedido,
                                        'telefono' => $telefono_negocio,
                                        'chat_id' => $resultado_whatsapp['chat_id'] ?? null
}
                                    ]);
                                } else {
                                    logError("⚠️ Error enviando WhatsApp (no crítico)", [
                                        'error' => $resultado_whatsapp['error'] ?? 'Error desconocido'
                                logError("⚠️ No se puede enviar WhatsApp: teléfono vacío", [
                                    'negocio_id' => $negocio->id_negocio ?? 'null',
                                    'telefono' => $telefono_negocio
                            logError("Error generando WhatsApp (no crítico)", ['error' => $e->getMessage()]);
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
                        // Usar JavaScript para redirección después de mostrar mensaje
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'confirmacion_pedido.php?id=" . $pedido->id_pedido . "';
                            }, 2000);
                        </script>";
                        echo "<div class='alert alert-success text-center' style='margin: 20px; padding: 20px; font-size: 1.2rem;'>";
                        echo "<i class='fas fa-check-circle' style='font-size: 2rem; color: #28a745; margin-bottom: 10px;'></i><br>";
                        echo $mensaje_exito . "<br>";
                        if ($metodo_pago_seleccionado === 'mercadopago' && isset($payment_details['mp_fee']) && $payment_details['mp_fee'] > 0) {
                            echo "<small>Comisión MercadoPago incluida: $" . number_format($payment_details['mp_fee'], 2) . "</small><br>";
                        echo "<small>Redirigiendo a la confirmación...</small>";
                        echo "</div>";
                        // Detener ejecución para evitar mostrar el formulario
                        exit;
                    } else {
                        // ✅ ERROR AGREGANDO DETALLES
                        if (method_exists($db, 'rollBack')) {
                            $db->rollBack();
                        $general_err = "Error al agregar los productos al pedido. Detalles agregados: $detalles_agregados";
                        logError("Error agregando detalles del pedido", [
                            'total_items' => count($_SESSION['carrito']['items'])
                    // ✅ ERROR CREANDO PEDIDO
                    if (method_exists($db, 'rollBack')) {
                        $db->rollBack();
                    $general_err = "Error al crear el pedido: " . (is_string($resultado) ? $resultado : 'Error desconocido');
                    logError("Error creando pedido", ['error' => $resultado]);
            } catch (Exception $e) {
                // ✅ MANEJO DE EXCEPCIONES
                if (method_exists($db, 'rollBack') && method_exists($db, 'inTransaction') && $db->inTransaction()) {
                    $db->rollBack();
                $general_err = "Error interno: " . $e->getMessage();
                logError("Excepción al procesar pedido", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
            }
            // ✅ PAGO NO PROCESADO
            if (empty($general_err)) {
                $general_err = "No se pudo procesar el pago. Por favor intenta de nuevo.";
            logError("Pago no procesado", [
                'metodo_pago' => $metodo_pago_seleccionado ?? 'no_definido'
            ]);
// ✅ CALCULAR TOTAL BASE PARA MOSTRAR EN LA PÁGINA
// La comisión de MercadoPago se calculará dinámicamente en JavaScript
$total_base = $subtotal + $costo_envio + $propina + $cargo_servicio + $impuesto;
$total = $total_base; // Total base sin comisiones adicionales
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/soft-ui.css">
    <link rel="stylesheet" href="assets/css/transitions.css?v=2.0">
    <title>Finalizar Pedido - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Scripts de pago al final del body -->
        <style>
        :root {
            --primary: #0165FF;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FFFFFF;
            --gradient: linear-gradient(135deg, #1E88E5 0%, #64B5F6 100%);
        } 
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--dark);
            margin: 0;
            padding: 0;
        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        .container {
            padding-bottom: 100px;
        .page-header {
            align-items: center;
            padding: 20px 0;
            margin-bottom: 10px;
        .page-title {
            font-size: 1.5rem;
            flex: 1;
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.25rem;
        .back-button {
            background-color: var(--secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            justify-content: center;
            text-decoration: none;
            margin-right: 15px;
        .checkout-section {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            .checkout-section {
                padding: 15px;
                border-radius: 12px;
        .section-title {
            margin-bottom: 15px;
            font-size: 1.2rem;
            .section-title {
                font-size: 1.1rem;
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        .address-card {
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 80px;
            -webkit-tap-highlight-color: transparent;
            position: relative;
            .address-card {
                padding: 20px;
                margin-bottom: 18px;
                min-height: 90px;
                border-radius: 16px;
        @media (max-width: 576px) {
                padding: 24px;
                margin-bottom: 20px;
                min-height: 100px;
                border-radius: 20px;
                border-width: 3px;
        .address-card.selected, .address-card:hover {
            border-color: var(--primary);
            background-color: rgba(1, 101, 255, 0.05);
            box-shadow: 0 4px 12px rgba(1, 101, 255, 0.15);
        .address-card input[type="radio"] {
            position: absolute;
            top: 15px;
            right: 15px;
            transform: scale(1.3);
            .address-card input[type="radio"] {
                transform: scale(1.6);
                top: 20px;
                right: 20px;
        .address-name {
            font-weight: 600;
            margin-bottom: 5px;
        .address-details {
            font-size: 0.9rem;
            color: #666;
        .add-new {
            font-weight: 500;
            margin-top: 10px;
        .add-new i {
            margin-right: 8px;
        /* ✅ MÉTODOS DE PAGO MINIMALISTAS ACTUALIZADOS */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
        .payment-method {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px 12px;
            text-align: center;
            background: #ffffff;
            min-height: 90px;
            .payment-method {
                padding: 12px 8px;
                min-height: 80px;
        .payment-method:hover {
            box-shadow: 0 2px 8px rgba(1, 101, 255, 0.1);
        .payment-method.selected {
            background: rgba(1, 101, 255, 0.05);
            box-shadow: 0 0 0 1px var(--primary);
        .payment-method .payment-icon {
            font-size: 1.8rem;
            margin-bottom: 6px;
            color: #6b7280;
            transition: color 0.2s ease;
            .payment-method .payment-icon {
                font-size: 1.5rem;
                margin-bottom: 4px;
        .payment-method.selected .payment-icon {
        .payment-method .payment-name {
            font-size: 0.85rem;
            color: #374151;
            line-height: 1.2;
            .payment-method .payment-name {
                font-size: 0.75rem;
        .payment-method .payment-desc {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 2px;
            line-height: 1.1;
            .payment-method .payment-desc {
                font-size: 0.65rem;
        .payment-method.selected .payment-name {
        /* ✅ ICONOS ESPECÍFICOS CON COLORES DE MARCA */
        .payment-method[data-payment="paypal"] .payment-icon {
            color: #0070ba;
        .payment-method[data-payment="efectivo"] .payment-icon {
            color: #059669;
        /* Formularios de pago mejorados */
        .payment-form {
            display: none;
            margin-top: 16px;
            background: #fafafa;
        .payment-form.active {
            display: block;
            animation: slideIn 0.3s ease-out;
        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(-10px); 
            to { 
                opacity: 1; 
                transform: translateY(0); 
        .payment-selector {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 16px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
.payment-selector:hover {
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(1, 101, 255, 0.1);
.payment-selector-button {
    padding: 16px;
    width: 100%;
    min-height: 80px;
    display: flex;
    align-items: center;
    border: 2px solid transparent;
    -webkit-tap-highlight-color: transparent;
.payment-selector-button:hover {
    box-shadow: 0 4px 12px rgba(1, 101, 255, 0.15);
.payment-selector-button.selected {
    background: rgba(1, 101, 255, 0.05);
    box-shadow: 0 0 0 2px rgba(1, 101, 255, 0.2);
@media (max-width: 768px) {
    .payment-selector-button {
        padding: 20px 16px;
        min-height: 90px;
        border-radius: 16px;
@media (max-width: 576px) {
        padding: 24px 20px;
        min-height: 100px;
        border-radius: 20px;
        border-width: 3px;
.payment-selector-content {
    justify-content: space-between;
.payment-selector-icon {
    width: 48px;
    height: 48px;
    background: var(--secondary);
    border-radius: 50%;
    justify-content: center;
    margin-right: 12px;
.payment-selector-icon i {
    font-size: 1.5rem;
    color: var(--primary);
.payment-selector-text {
    flex: 1;
    text-align: left;
.payment-selector-title {
    font-weight: 600;
    font-size: 1rem;
    color: var(--dark);
    margin-bottom: 2px;
.payment-selector-subtitle {
    font-size: 0.85rem;
    color: #666;
.payment-selector-arrow {
    margin-left: 12px;
.payment-selector-arrow i {
    font-size: 1.2rem;
    transition: transform 0.2s ease;
/* Método seleccionado */
.selected-payment-method {
    border: 1px solid var(--primary);
.selected-payment-content {
.selected-payment-icon {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
.selected-payment-icon i {
.selected-payment-info {
.selected-payment-name {
.selected-payment-desc {
.change-payment-btn {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 500;
.change-payment-btn:hover {
    background: #0052cc;
    transform: translateY(-1px);
/* Responsive para móviles */
    .payment-selector-content {
        padding: 12px;
    .payment-selector-icon {
        width: 40px;
        height: 40px;
        margin-right: 10px;
    .payment-selector-icon i {
        font-size: 1.3rem;
    .payment-selector-title {
        font-size: 0.9rem;
    .payment-selector-subtitle {
        font-size: 0.8rem;
    .selected-payment-content {
    .selected-payment-icon {
    .selected-payment-icon i {
    .selected-payment-name {
    .selected-payment-desc {
    .change-payment-btn {
        padding: 6px 12px;
#mercadopago-form {
    min-height: 400px;
    padding: 20px 0;
.payment-method[data-payment="mercadopago"]::after {
    content: "+3.99%";
    position: absolute;
    top: 4px;
    right: 6px;
    background: #fee2e2;
    color: #dc2626;
    font-size: 0.6rem;
    padding: 2px 4px;
    border-radius: 4px;
.payment-method[data-payment="mercadopago"] .payment-icon {
    color: #009ee3;
        /* Botones de wallets digitales */
        .digital-wallet-button {
            width: 100%;
            height: 48px;
            border-radius: 8px;
            margin: 10px 0;
            border: none;
            transition: all 0.3s ease;
        .digital-wallet-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        /* Google Pay */
        #google-pay-button {
            background: linear-gradient(135deg, #4285f4, #3367d6);
            color: white;
            box-shadow: 0 4px 8px rgba(66, 133, 244, 0.4);
            transition: background 0.3s ease, box-shadow 0.3s ease;
        #google-pay-button:hover {
            background: linear-gradient(135deg, #3367d6, #254a9e);
            box-shadow: 0 6px 12px rgba(51, 103, 214, 0.6);
        #google-pay-button:active {
            background: linear-gradient(135deg, #254a9e, #1a3570);
            box-shadow: 0 2px 6px rgba(25, 54, 112, 0.8);
        /* Apple Pay */
        #apple-pay-button {
            background: #000;
        /* PayPal */
        #paypal-button-container {
            margin-top: 15px;
            min-height: 48px;
        /* Efectivo */
        .cash-info {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        .cash-info .fa-money-bill {
            color: #856404;
        /* Propina */
        .tip-options {
            justify-content: space-between;
            gap: 10px;
            .tip-options {
                display: grid;
                gap: 12px;
        .tip-option {
            padding: 15px 10px;
            background: white;
            min-height: 70px;
            .tip-option {
                padding: 18px 12px;
                padding: 20px 15px;
        .tip-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(1, 101, 255, 0.1);
        .tip-option.selected {
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.1) 0%, rgba(100, 181, 246, 0.1) 100%);
        .tip-value {
            font-size: 1.1rem;
        .tip-percent {
        .custom-tip {
            border: 1px solid #eee;
            background: #f8f9fa;
        .custom-tip-label {
        .custom-tip-input .input-group-text {
            background-color: var(--primary);
        .custom-tip-input .form-control {
            border-radius: 0 8px 8px 0;
            padding: 10px 15px;
        .custom-tip-input .form-control:focus {
            box-shadow: none;
        /* ✅ MEJORAS GENERALES PARA MÓVILES */
            .form-control, .form-select {
                min-height: 50px;
                font-size: 16px; /* Evita zoom en iOS */
                padding: 12px 16px;
                border-width: 2px;
            
            .btn {
                padding: 12px 20px;
                font-size: 16px;
            .form-check-input {
                transform: scale(1.3);
                margin-top: 0.2em;
            .form-check-label {
                padding-left: 0.5rem;
                cursor: pointer;
                min-height: 44px;
                display: flex;
                align-items: center;
                min-height: 56px;
                padding: 16px 20px;
                padding: 16px 24px;
                font-size: 18px;
                font-weight: 600;
                transform: scale(1.5);
                margin-top: 0.3em;
                padding-left: 0.8rem;
                min-height: 48px;
            /* Mejorar espaciado general */
            .container, .container-fluid {
                padding-left: 20px;
                padding-right: 20px;
            .card {
            .modal-content {
                margin: 20px;
        /* ✅ RESUMEN DE PEDIDO CON STRIPE FEE */
        .order-summary {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            bottom: 20px;
            margin-top: 20px;
            .order-summary {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                border-radius: 15px 15px 0 0;
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15);
                max-height: 50vh;
                overflow-y: auto;
        .summary-toggle {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        .summary-toggle:hover {
            background: rgba(1, 101, 255, 0.1);
            .summary-toggle {
        .summary-toggle .summary-toggle-info {
        .summary-toggle .summary-toggle-info i {
        .summary-toggle-total {
            font-size: 1.3rem;
        .summary-toggle-arrow {
            transition: transform 0.3s ease;
        .summary-content {
            transition: all 0.3s ease-in-out;
            overflow: hidden;
            .summary-content.collapsed {
                display: none;
                max-height: 0;
                opacity: 0;
            .summary-content:not(.collapsed) {
                display: block;
                opacity: 1;
                animation: slideDown 0.3s ease-out;
        @keyframes slideDown {
            from {
            to {
                max-height: 500px;
        .summary-title {
            .summary-title {
                margin-bottom: 0;
        .summary-row {
            margin-bottom: 12px;
            padding: 5px 0;
            .summary-row {
                margin-bottom: 8px;
        .summary-label {
        .summary-value {
        .summary-total {
            padding-top: 15px;
            border-top: 2px solid #f1f1f1;
            .summary-total {
                margin-top: 10px;
                padding-top: 10px;
        .place-order-btn {
            background: var(--primary);
            border-radius: 50px;
            min-height: 50px;
            .place-order-btn {
                padding: 18px 0;
                margin-top: 20px;
                min-height: 54px;
                box-shadow: 0 4px 12px rgba(1, 101, 255, 0.3);
                padding: 20px 0;
                font-size: 1.2rem;
                margin-top: 25px;
                min-height: 60px;
                font-weight: 700;
        /* Estilos para summary-actions */
        .summary-actions {
            .summary-actions {
                padding: 15px 20px 20px 20px;
                border-top: 1px solid #eee;
                background: white;
            .order-summary .summary-actions {
                margin: 0 -20px -20px -20px;
                border-radius: 0 0 15px 15px;
        .place-order-btn:hover {
            box-shadow: 0 8px 25px rgba(1, 101, 255, 0.3);
        .place-order-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        .error-message {
            color: #e74c3c;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 6px;
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 249, 250, 0.95) 100%);
            z-index: 9999;
            transition: all 0.5s;
            opacity: 0;
            visibility: hidden;
        .preloader.active {
            opacity: 1;
            visibility: visible;
        .preloader-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid var(--primary);
            animation: spin 1s linear infinite;
        .preloader-text {
        .preloader-message {
            max-width: 80%;
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        /* Layout responsivo mejorado */
        @media (min-width: 992px) {
            .checkout-layout {
                grid-template-columns: 1fr 350px;
                gap: 30px;
                align-items: start;
                position: sticky;
                margin-top: 0;
        /* Productos del carrito compactos */
        .cart-items-compact {
            max-height: 200px;
            overflow-y: auto;
        .cart-item-compact {
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        .cart-item-compact:last-child {
            border-bottom: none;
        .cart-item-image {
            object-fit: cover;
        .cart-item-details {
            min-width: 0;
        .cart-item-name {
            margin-bottom: 2px;
            white-space: nowrap;
            text-overflow: ellipsis;
        .cart-item-quantity {
            font-size: 0.75rem;
        .cart-item-price {
        /* Responsive adjustments */
            .container {
                padding: 0 10px 100px 10px;
                margin-bottom: 15px;
    </style>
</head>
<body>
<div class="qb-page-content">
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
        <div class="preloader-text">Procesando tu pedido</div>
        <div class="preloader-message" id="preloader-message">Estamos validando la información...</div>
    </div>
    <div class="container">
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
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!empty($direccion_err)): ?>
                                <div class="error-message"><?php echo $direccion_err; ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <!-- ✅ MÉTODO DE PAGO CON MERCADOPAGO -->
                        <h2 class="section-title"><i class="fas fa-credit-card"></i> Método de pago</h2>
                            <!-- ✅ INFORMACIÓN SOBRE COMISIONES 
    <div class="alert alert-info" style="margin-bottom: 20px;">
        <i class="fas fa-info-circle"></i>
        <strong>Información importante:</strong>
        <ul style="margin-bottom: 0; margin-top: 10px;">
            <li><strong>Monto mínimo:</strong> Los pagos con tarjeta requieren un mínimo de $10.00 MXN</li>
            <li><strong>Comisión de procesamiento:</strong> Se aplicará una comisión del 3.99% + $4 MXN por pagos con tarjeta</li>
        </ul>
    </div>-->
    <!-- Grid de métodos de pago -->
    <div class="payment-methods" id="payment-methods">
        <!-- MercadoPago -->
        <div class="payment-method" data-payment="mercadopago">
            <div class="payment-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="payment-name">Tarjeta</div>
            <div class="payment-desc">Débito/Crédito</div>
        <!-- PayPal -->
        <div class="payment-method" data-payment="paypal">
                <i class="fab fa-paypal"></i>
            <div class="payment-name">PayPal</div>
            <div class="payment-desc">Cuenta PayPal</div>
        <!-- Efectivo -->
        <div class="payment-method" data-payment="efectivo">
                <i class="fas fa-money-bill-wave"></i>
            <div class="payment-name">Efectivo</div>
            <div class="payment-desc">Al recibir</div>
    <!-- ✅ FORMULARIOS DE PAGO ESPECÍFICOS -->
    <!-- Formulario MercadoPago -->
    <div id="mercadopago_form" class="payment-form">
        <h5 style="margin-bottom: 20px;">
            <i class="fas fa-lock"></i> Pago seguro con MercadoPago
        </h5>
        <div id="mercadopago-form"></div>
        <div class="mt-3">
            <small class="text-muted">
                <i class="fas fa-shield-alt text-success"></i>
                Tu información está protegida. Procesado de forma segura por MercadoPago
            </small>
    <!-- Formulario PayPal -->
    <div id="paypal_form" class="payment-form">
        <div class="text-center">
            <p class="mb-3">Inicia sesión en tu cuenta PayPal para completar el pago</p>
            <div id="paypal-button-container"></div>
    <!-- Formulario Efectivo -->
    <div id="efectivo_form" class="payment-form">
        <div class="cash-info">
            <div class="d-flex align-items-center">
                <i class="fas fa-money-bill"></i>
                <div>
                    <strong>Pago en efectivo:</strong> Paga al recibir tu pedido. 
                    Asegúrate de tener el monto exacto o cambio disponible.
                </div>
        <div class="form-group mt-3">
            <label class="form-label">Monto con el que pagarás:</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="monto_efectivo" id="monto_efectivo" 
                       class="form-control" value="<?php echo $total; ?>" 
                       min="<?php echo $total; ?>" step="0.01">
                El monto debe ser igual o mayor a $<?php echo number_format($total, 2); ?>
    <?php if (!empty($metodo_pago_err)): ?>
        <div class="error-message"><?php echo $metodo_pago_err; ?></div>
    <?php endif; ?>
                    <!-- Propina -->
                        <h2 class="section-title"><i class="fas fa-hand-holding-usd"></i> Propina para el repartidor</h2>
                        <div class="tip-options">
                            <div class="tip-option selected" data-value="0">
                                <div class="tip-value">Sin propina</div>
                                <div class="tip-percent">$0</div>
                            </div>
                            <div class="tip-option" data-value="10">
                                <div class="tip-value">$10</div>
                                <div class="tip-percent">Básica</div>
                            <div class="tip-option" data-value="15">
                                <div class="tip-value">$15</div>
                                <div class="tip-percent">Recomendada</div>
                            <div class="tip-option" data-value="20">
                                <div class="tip-value">$20</div>
                                <div class="tip-percent">Generosa</div>
                        </div>
                        <div class="custom-tip">
                            <div class="custom-tip-label">O ingresa un monto personalizado:</div>
                            <div class="custom-tip-input">
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="propina" id="custom_tip" class="form-control" 
                                           value="0" min="0" step="5">
                            <?php if (!empty($propina_err)): ?>
                                <div class="error-message"><?php echo $propina_err; ?></div>
                    <!-- Tipo de pedido: Delivery o PickUp -->
                        <h2 class="section-title"><i class="fas fa-truck"></i> Tipo de pedido</h2>
                        <?php if (!$repartidores_disponibles): ?>
                            <!-- Solo PickUp disponible -->
                            <div class="alert alert-warning" role="alert">
                                <i class="fas fa-info-circle"></i>
                                <strong>Solo disponible PickUp:</strong> No hay repartidores disponibles en este momento. 
                                Puedes recoger tu pedido directamente en el restaurante.
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_pedido" id="tipo_pickup" value="pickup" checked disabled>
                                <label class="form-check-label" for="tipo_pickup">
                                    <strong>PickUp (Recoger en tienda)</strong> - Única opción disponible
                                </label>
                            <input type="hidden" name="tipo_pedido" value="pickup">
                            <!-- Ambas opciones disponibles -->
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-motorcycle"></i>
                                <strong>Delivery disponible:</strong> Hay <?php echo $total_repartidores_activos; ?> repartidor(es) activo(s).
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="tipo_pedido" id="tipo_delivery" value="delivery" checked>
                                <label class="form-check-label" for="tipo_delivery">
                                    <strong>Delivery (Envío a domicilio)</strong>
                                    <br><small class="text-muted">Tu pedido será entregado en la dirección seleccionada</small>
                                <input class="form-check-input" type="radio" name="tipo_pedido" id="tipo_pickup" value="pickup">
                                    <strong>PickUp (Recoger en tienda)</strong>
                                    <br><small class="text-muted">Recoge tu pedido directamente en el restaurante</small>
                        <!-- Campo de hora para pickup -->
                        <div class="mt-3" id="pickup-time-container" style="display: none;">
                            <label for="pickup_time" class="form-label">
                                <i class="fas fa-clock"></i> Hora preferida para recoger (opcional)
                            </label>
                            <input type="time" id="pickup_time" name="pickup_time" class="form-control" 
                                   min="<?php echo date('H:i'); ?>" />
                            <small class="text-muted">
                                Si no seleccionas una hora, el pedido estará listo en aproximadamente 30-45 minutos.
                            </small>
                    <!-- Instrucciones especiales -->
                        <h2 class="section-title"><i class="fas fa-comment-alt"></i> Instrucciones especiales</h2>
                        <textarea name="instrucciones" rows="3" class="form-control" 
                                  placeholder="Instrucciones para el repartidor o el restaurante (opcional)"
                                  style="border: 2px solid #eee; border-radius: 8px; padding: 12px;"></textarea>
                    <!-- Campos ocultos -->
                    <input type="hidden" name="payment_method" id="payment_method" value="">
                    <input type="hidden" name="payment_id" id="payment_id" value="">
                </form>
            <!-- ✅ RESUMEN DEL PEDIDO -->
<div class="order-summary">
    <div class="summary-toggle" onclick="toggleSummary()">
        <div class="summary-toggle-info">
            <i class="fas fa-receipt"></i>
            <h2 class="summary-title">Resumen del pedido</h2>
        <div style="display: flex; align-items: center; gap: 15px;">
            <span class="summary-toggle-total" id="total_display_mobile">$<?php echo number_format($total, 2); ?></span>
            <i class="fas fa-chevron-down summary-toggle-arrow" id="summary-arrow"></i>
    <div class="summary-content" id="summary-content">
        <h2 class="summary-title d-none d-md-block">Resumen del pedido</h2>
        <!-- ✅ INDICADOR DE MEMBRESÍA -->
        <?php if ($esMiembroActivo): ?>
            <div class="alert alert-success" style="padding: 10px; margin-bottom: 15px; font-size: 0.85rem;">
                <i class="fas fa-crown"></i> <strong>Miembro Activo</strong>
                <br><small>Sin cargo por servicio. Envío gratis en pedidos +$300</small>
        <!-- Productos del carrito -->
        <div class="cart-items-compact mb-3">
            <?php if (!empty($_SESSION['carrito']['items'])): ?>
                <?php foreach ($_SESSION['carrito']['items'] as $item): ?>
                    <div class="cart-item-compact">
                        <?php if (!empty($item['imagen'])): ?>
                            <img src="<?php echo $item['imagen']; ?>" alt="<?php echo $item['nombre']; ?>" class="cart-item-image">
                        <div class="cart-item-details">
                            <div class="cart-item-name"><?php echo htmlspecialchars($item['nombre']); ?></div>
                            <div class="cart-item-quantity"><?php echo $item['cantidad']; ?> x $<?php echo number_format($item['precio'], 2); ?></div>
                        <div class="cart-item-price">
                            $<?php echo number_format($item['precio'] * $item['cantidad'], 2); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        <div class="summary-row">
            <span class="summary-label">Subtotal productos</span>
            <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
        <div class="summary-row" id="envio-row">
            <span class="summary-label">
                Costo de envío
                <?php if ($esMiembroActivo && $subtotal >= 300): ?>
                    <span class="badge bg-success" style="font-size: 0.7rem;">GRATIS</span>
                <?php endif; ?>
            </span>
            <span class="summary-value" id="costo_envio_display">$<?php echo number_format($costo_envio, 2); ?></span>
                Cargo por servicio
                <?php if ($esMiembroActivo): ?>
            <span class="summary-value">$<?php echo number_format($cargo_servicio, 2); ?></span>
            <span class="summary-label">Propina</span>
            <span class="summary-value" id="tip_display">$<?php echo number_format($propina, 2); ?></span>
        <!-- Comisión de MercadoPago (oculta por defecto) -->
        <div class="summary-row" id="mp-fee-row" style="display: none;">
            <span class="summary-label" style="color: #dc2626;">
                Comisión procesamiento
                <i class="fas fa-info-circle" title="3.99% + $4 MXN por pagos con tarjeta"></i>
            <span class="summary-value" id="mp_fee_display" style="color: #dc2626;">$0.00</span>
        <div class="summary-row summary-total">
            <span>Total a pagar</span>
            <span id="total_display">$<?php echo number_format($total, 2); ?></span>
        <!-- ✅ ADVERTENCIA DE MONTO MÍNIMO -->
        <div id="min-amount-warning" class="alert alert-warning" style="display: none; padding: 8px; margin-top: 10px; font-size: 0.8rem;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Monto mínimo:</strong> Los pagos con tarjeta requieren un mínimo de $10.00 MXN
    <!-- Botón de pagar siempre visible -->
    <div class="summary-actions" style="margin-top: 15px;">
        <button type="button" class="place-order-btn" id="submit-button">
            <i class="fas fa-shopping-cart me-2"></i>Realizar pedido
        </button>
</div>
    <!-- Modal para agregar dirección -->
    <div class="modal fade" id="direccionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar nueva dirección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body">
                    <form id="formDireccion">
                        <input type="hidden" name="action" value="add_address">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nombre para la dirección</label>
                                <input type="text" class="form-control" name="nombre_direccion" placeholder="Ej. Casa" required>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Calle</label>
                                <input type="text" class="form-control" name="calle" required>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Número</label>
                                <input type="text" class="form-control" name="numero" required>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Colonia</label>
                                <input type="text" class="form-control" name="colonia" required>
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" required>
                                <label class="form-label">Código Postal</label>
                                <input type="text" class="form-control" name="codigo_postal" required>
                                <label class="form-label">Estado</label>
                                <input type="text" class="form-control" name="estado" required>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="es_predeterminada" value="1">
                            <label class="form-check-label">Establecer como dirección predeterminada</label>
                    </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarDireccion">Guardar</button>
          
                    <h5 class="modal-title qb-transition">Agregar nueva dirección</h5>
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script src="https://www.paypal.com/sdk/js?client-id=Abzmyjjr7wGmTcr7cGZy3dbJFTUAR-Sr6RkJMzTGndnsO8fQe00mCKkn7on30J7kO78Vp0A6RYP_Qlaf&currency=MXN&disable-funding=credit,card&intent=capture&vault=false&commit=true"></script>
<!-- Verificar que MercadoPago se cargue correctamente -->
<script>
window.addEventListener('load', function() {
    setTimeout(function() {
        if (typeof window.MercadoPago === 'undefined') {
            console.error('❌ MercadoPago SDK no se cargó correctamente');
            alert('Error: No se pudo cargar MercadoPago. Verifica tu conexión a internet y recarga la página.');
}
            console.log('✅ MercadoPago SDK cargado correctamente');
    }, 2000);
});
</script>
$(document).ready(function() {
    console.log('🚀 Iniciando sistema de checkout');
    let selectedPaymentMethod = '';
    let paymentBrickController = null;
    let paypalReady = false;
    let mp = null;
    let brickInitializing = false;
    // ===== FUNCIÓN PARA ACTUALIZAR RESUMEN =====
    function updateSummary() {
        const subtotal = parseFloat('<?php echo $subtotal; ?>');
        const cargoServicio = parseFloat('<?php echo $cargo_servicio; ?>');
        // ✅ CORREGIR PROPINA: usar valor real del campo o 0 si no hay selección
        let propina = 0;
        const customTipValue = $('#custom_tip').val();
        const selectedTipOption = $('.tip-option.selected');
        if (customTipValue && customTipValue.trim() !== '') {
            // Si hay valor custom, usarlo
            propina = parseFloat(customTipValue) || 0;
        } else if (selectedTipOption.length > 0) {
            // Si hay opción seleccionada, usar su valor
            propina = parseFloat(selectedTipOption.data('value')) || 0;
            // Si no hay nada seleccionado, propina es 0
            propina = 0;
        const impuesto = parseFloat('<?php echo $impuesto; ?>');
        // ✅ CORREGIR DETECCIÓN DE PICKUP
        const tipoPedidoSeleccionado = $('input[name="tipo_pedido"]:checked').val();
        const isPickup = (tipoPedidoSeleccionado === 'pickup');
        let costoEnvio = isPickup ? 0 : parseFloat('<?php echo $costo_envio; ?>');
        console.log('� Calculando resumen:', {
            'subtotal': subtotal,
            'propina': propina,
            'tipo_pedido': tipoPedidoSeleccionado,
            'es_pickup': isPickup,
            'costo_envio': costoEnvio,
}
            'cargo_servicio': cargoServicio
        });
        $('#costo_envio_display').text('$' + costoEnvio.toFixed(2));
        $('#tip_display').text('$' + propina.toFixed(2));
        let comision = 0;
        if (selectedPaymentMethod === 'mercadopago') {
            const baseTotal = subtotal + costoEnvio + propina + cargoServicio + impuesto;
            // ✅ COMISIÓN CORRECTA MEXICO: 3.36% + $2.50 MXN sobre base total (SIN incluir la propia comisión)
            comision = (baseTotal * 0.0336) + 2.50;
            $('#mp-fee-row').show();
            $('#mp_fee_display').text('$' + comision.toFixed(2));
            console.log('💳 Comisión MercadoPago:', {
                'base_total': baseTotal,
}
                'comision_calculada': comision
            });
            $('#mp-fee-row').hide();
            console.log('💳 Sin comisión para método:', selectedPaymentMethod);
        const total = subtotal + costoEnvio + propina + cargoServicio + impuesto + comision;
        console.log('🧮 Total final:', {
            'total_calculado': total,
            'desglose': {
                'subtotal': subtotal,
                'envio': costoEnvio, 
                'propina': propina,
                'servicio': cargoServicio,
                'impuesto': impuesto,
                'comision': comision
        $('#total_display').text('$' + total.toFixed(2));
        $('#total_display_mobile').text('$' + total.toFixed(2));
        if (selectedPaymentMethod === 'mercadopago' && total < 10) {
            $('#min-amount-warning').show();
            $('#submit-button').prop('disabled', true);
            $('#min-amount-warning').hide();
            $('#submit-button').prop('disabled', false);
        return total;
    // ===== FUNCIÓN AUXILIAR PARA LIMPIAR CONTENEDOR =====
    function clearContainer(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            // Método seguro para limpiar el contenedor
            while (container.firstChild) {
                container.removeChild(container.firstChild);
        return container;
    // ===== MANEJAR SELECCIÓN DE MÉTODOS DE PAGO =====
    $('.payment-method').on('click', function() {
        if (brickInitializing) {
            console.log('⏳ Esperando inicialización anterior...');
            return;
        $('.payment-method').removeClass('selected');
        $('.payment-form').removeClass('active').hide();
        $(this).addClass('selected');
        selectedPaymentMethod = $(this).data('payment');
        console.log('💳 Método seleccionado:', selectedPaymentMethod);
        const formId = selectedPaymentMethod + '_form';
        $('#' + formId).addClass('active').show();
        $('#payment_method').val(selectedPaymentMethod);
}
            initMercadoPagoBrick();
        } else if (selectedPaymentMethod === 'paypal') {
            initPayPal();
}
        updateSummary();
    });
    // ===== INICIALIZAR MERCADOPAGO BRICK (VERSIÓN CORREGIDA) =====
    async function initMercadoPagoBrick() {
            console.log('⏳ Ya hay una inicialización en progreso');
        brickInitializing = true;
        console.log('🎯 Inicializando MercadoPago Brick...');
        try {
            // Verificar que MercadoPago esté disponible
            if (typeof window.MercadoPago === 'undefined') {
                throw new Error('MercadoPago SDK no está disponible. Verifica la conexión a internet.');
            // Destruir brick anterior si existe
            if (paymentBrickController) {
                    await paymentBrickController.unmount();
}
                    console.log('✅ Brick anterior destruido');
                } catch (unmountError) {
                    console.warn('⚠️ Error al desmontar (no crítico):', unmountError);
                paymentBrickController = null;
                await new Promise(resolve => setTimeout(resolve, 300));
            // Limpiar contenedor
            const container = document.getElementById('mercadopago-form');
            if (!container) {
                throw new Error('Contenedor mercadopago-form no encontrado');
           
            // ✅ FORZAR RECÁLCULO COMPLETO ANTES DE INICIALIZAR MERCADOPAGO
            // Esto asegura que el total sea el correcto según las preferencias del usuario
            console.log('🔄 Forzando recálculo de total antes de inicializar MercadoPago...');
            let total = updateSummary();
            console.log('💰 Total recalculado para MercadoPago:', total);
            // ✅ Verificar que el total sea el correcto comparando con el display
            const totalFromDisplay = parseFloat($('#total_display').text().replace('$', '').replace(',', ''));
            console.log('🔍 Verificación: Total del display: $' + totalFromDisplay.toFixed(2) + ' vs Total calculado: $' + total.toFixed(2));
            if (Math.abs(total - totalFromDisplay) > 0.01) {
                console.warn('⚠️ Discrepancia detectada entre total calculado y display. Usando el del display.');
                total = totalFromDisplay;
            console.log('💳 Total FINAL para MercadoPago: $' + total.toFixed(2));
            if (total < 10) {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Monto mínimo requerido</strong>
}
                        <p>El monto mínimo para pagos con tarjeta es de $10.00 MXN</p>
                        <p><strong>Total actual: $${total.toFixed(2)}</strong></p>
                `;
                brickInitializing = false;
                return;
            // Validar email del usuario
            const userEmail = '<?php echo addslashes($usuario->email); ?>';
            let validEmail = userEmail && userEmail.includes('@') ? userEmail : null;
            if (!validEmail) {
                validEmail = 'user_' + Math.floor(Math.random() * 100000) + '@quickbite.com.mx';
                console.log('⚠️ Email temporal generado:', validEmail);
            console.log('📧 Email para MercadoPago:', validEmail);
            // Configurar MercadoPago
            const publicKey = '<?php echo $mp_config["public_key"]; ?>';
            if (!publicKey || publicKey.length < 20) {
                throw new Error('Public Key inválida: ' + publicKey);
            console.log('🔑 Configurando con Public Key:', publicKey.substring(0, 15) + '...');
            // Inicializar MercadoPago
            if (!mp || !mp.bricks) {
                console.log('🔧 Inicializando MercadoPago SDK...');
                mp = new window.MercadoPago(publicKey, {
}
                    locale: 'es-MX'
                });
                // Esperar a que el SDK esté listo
                await new Promise(resolve => setTimeout(resolve, 1000));
                if (!mp.bricks || typeof mp.bricks !== 'function') {
                    throw new Error('SDK de MercadoPago no se inicializó correctamente');
                console.log('✅ SDK inicializado correctamente');
            // Esperar antes de crear el brick
            await new Promise(resolve => setTimeout(resolve, 500));
            // Configuración del brick
            const brickConfig = {
                initialization: {
                    amount: parseFloat(total.toFixed(2)),
                    payer: {
}
                        email: validEmail
                },
                customization: {
                    paymentMethods: {
                        creditCard: 'all',
                        debitCard: 'all',
                        maxInstallments: 12
                    },
                    visual: {
                        style: {
                            theme: 'default'
                callbacks: {
                    onReady: () => {
                        console.log('✅ Brick listo para usar');
                        brickInitializing = false;
                    onSubmit: async (formData) => {
                        console.log('📤 Datos del formulario recibidos:', formData);
                        console.log('🔍 Estructura completa del formData:', JSON.stringify(formData, null, 2));
                        // Validación estricta de datos - MEJORADA
                        if (!formData) {
                            console.error('❌ formData es null o undefined');
                            alert('Error: No se recibieron datos del formulario');
                            return Promise.reject(new Error('No form data'));
                        // CORRECCIÓN CRÍTICA: Los datos están anidados en formData.formData
                        let actualFormData = formData;
                        // Si hay estructura anidada, usar los datos internos
                        if (formData.formData && typeof formData.formData === 'object') {
                            actualFormData = formData.formData;
                            console.log('🔄 Usando datos anidados de formData.formData:', actualFormData);
                        // Buscar el token en diferentes ubicaciones posibles
                        let token = null;
                        if (actualFormData.token) {
}
                            token = actualFormData.token;
                        } else if (actualFormData.cardToken) {
                            token = actualFormData.cardToken;
                        } else if (actualFormData.card_token) {
                            token = actualFormData.card_token;
                        } else if (actualFormData.payment_token) {
                            token = actualFormData.payment_token;
                        console.log('🔍 Token encontrado:', token);
                        console.log('🔍 Datos reales a usar:', actualFormData);
                        if (!token) {
                            console.error('❌ Token no encontrado en actualFormData:', actualFormData);
                            console.error('❌ Propiedades disponibles:', Object.keys(actualFormData));
                            alert('Error: No se pudo generar el token de pago.\n\nPor favor verifica que todos los campos estén completos e intenta nuevamente.');
                            return Promise.reject(new Error('Token missing'));
                        if (!actualFormData.payment_method_id) {
                            console.error('❌ payment_method_id faltante:', actualFormData);
                            alert('Error: No se pudo identificar el método de pago.\n\nPor favor intenta nuevamente.');
                            return Promise.reject(new Error('Payment method missing'));
                        console.log('✅ Datos validados correctamente:', {
                            token_length: token.length,
                            payment_method: actualFormData.payment_method_id,
}
                            installments: actualFormData.installments
                        });
                        // Mostrar loader
                        $('#preloader').addClass('active');
                        $('#preloader-message').text('Procesando pago...');
                        // Preparar datos para enviar usando los datos corregidos
                        const paymentData = {
                            transaction_amount: parseFloat(total.toFixed(2)),
                            token: token,
                            description: 'Pedido QuickBite',
                            installments: actualFormData.installments || 1,
                            payment_method_id: actualFormData.payment_method_id,
                            issuer_id: actualFormData.issuer_id || null,
                            payer: {
                                email: validEmail,
                                identification: actualFormData.payer?.identification || null
                        };
                        console.log('📊 Enviando al servidor:', {
                            amount: paymentData.transaction_amount,
                            method: paymentData.payment_method_id,
                            installments: paymentData.installments,
                            has_token: !!paymentData.token
                            const response = await fetch('process_payment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(paymentData)
                            });
                            console.log('📡 Respuesta del servidor status:', response.status);
                            // ✅ DETECCIÓN DE ERROR 403 - USAR PROCESADOR DE EMERGENCIA
                            if (response.status === 403) {
                                console.warn('⚠️ Error 403 detectado, intentando procesador de emergencia...');
                                try {
                                    const emergencyResponse = await fetch('emergency_payment.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'Accept': 'application/json',
}
                                            'X-Emergency-Mode': 'true'
                                        },
                                        body: new URLSearchParams({
                                            'formData': JSON.stringify(paymentData)
                                        })
                                    });
                                    
                                    console.log('🚨 Respuesta procesador emergencia:', emergencyResponse.status);
                                    if (emergencyResponse.ok) {
                                        const emergencyResult = await emergencyResponse.json();
                                        console.log('🆘 Resultado emergencia:', emergencyResult);
                                        
                                        if (emergencyResult.success) {
                                            alert('✅ Pago procesado exitosamente en modo de emergencia.\n\nSe ha creado tu pedido correctamente.');
                                            window.location.href = emergencyResult.redirect_url || 'confirmacion_pedido.php';
}
                                            return Promise.resolve();
                                        } else {
                                            throw new Error(emergencyResult.message || 'Error en procesador de emergencia');
                                        }
                                    } else {
                                        throw new Error(`Error ${emergencyResponse.status} en procesador de emergencia`);
                                } catch (emergencyError) {
                                    console.error('❌ Error en procesador de emergencia:', emergencyError);
                                    throw new Error('Error del servidor. Por favor contacta soporte técnico.');
                            if (!response.ok) {
                                const errorText = await response.text();
}
                                console.error('❌ Error del servidor:', errorText);
                                throw new Error(`Error del servidor: ${response.status} - ${errorText}`);
                            const result = await response.json();
                            console.log('📥 Respuesta procesada:', result);
                            if (result.success) {
                                $('#payment_id').val(result.id);
                                $('#payment_method').val('mercadopago');
                                $('#preloader-message').text('¡Pago exitoso! Creando pedido...');
                                console.log('✅ Pago aprobado, enviando formulario con ID:', result.id);
                                setTimeout(() => {
}
                                    document.getElementById('checkout-form').submit();
                                }, 1500);
                                return Promise.resolve();
                                $('#preloader').removeClass('active');
                                const errorMsg = result.detail || result.message || 'Pago rechazado por la entidad bancaria';
                                console.error('❌ Pago rechazado:', errorMsg);
                                alert('❌ Pago rechazado: ' + errorMsg + '\n\nIntenta con otra tarjeta.');
                                return Promise.reject(new Error(errorMsg));
                        } catch (error) {
                            $('#preloader').removeClass('active');
                            console.error('❌ Error en comunicación:', error);
                            alert('❌ Error al procesar el pago:\n\n' + error.message + '\n\nPor favor intenta nuevamente.');
                            return Promise.reject(error);
                    onError: (error) => {
                        console.error('❌ Error en brick:', error);
                        const errorMsg = error.message || 'Error desconocido';
                        console.error('🔍 Detalles del error:', error);
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle"></i>
                                <strong>Error al cargar el formulario de pago</strong>
                                <p style="font-size:0.9rem;margin-top:10px;">${errorMsg}</p>
                                <div style="margin-top:15px;">
                                    <button class="btn btn-primary btn-sm" onclick="initMercadoPagoBrick()">
                                        <i class="fas fa-redo"></i> Reintentar
                                    </button>
                                    <button class="btn btn-secondary btn-sm ms-2" onclick="location.reload()">
                                        <i class="fas fa-refresh"></i> Recargar página
                                    <button class="btn btn-success btn-sm ms-2" onclick="$('.payment-method[data-payment=efectivo]').click()">
                                        <i class="fas fa-money-bill"></i> Pagar en efectivo
                                <div style="margin-top:10px; padding:10px; background:#f8f9fa; border-radius:4px;">
                                    <small><strong>💡 Sugerencia:</strong> Asegúrate de llenar todos los campos del formulario antes de hacer clic en "Pagar".</small>
                        `;
            };
            console.log('🏗️ Creando Payment Brick con configuración:', {
                amount: brickConfig.initialization.amount,
                email: brickConfig.initialization.payer.email
            // Crear el brick
            const bricksBuilder = mp.bricks();
            paymentBrickController = await bricksBuilder.create(
                'payment',
                'mercadopago-form',
                brickConfig
            );
            console.log('✅ Brick creado exitosamente');
        } catch (error) {
            console.error('❌ Error crítico en inicialización:', error);
            brickInitializing = false;
            if (container) {
                    <div class="alert alert-danger">
}
                        <strong>No se pudo cargar el formulario de pago</strong>
                        <p style="font-size:0.9rem;margin-top:10px;"><strong>Error:</strong> ${error.message}</p>
                        <div style="margin-top:15px;">
                            <button class="btn btn-primary btn-sm" onclick="initMercadoPagoBrick()">
                                <i class="fas fa-redo"></i> Reintentar
                            </button>
                            <button class="btn btn-secondary btn-sm ms-2" onclick="location.reload()">
                                <i class="fas fa-refresh"></i> Recargar página
                            <button class="btn btn-success btn-sm ms-2" onclick="$('.payment-method[data-payment=efectivo]').click()">
                                <i class="fas fa-money-bill"></i> Pagar en efectivo
    // ===== INICIALIZAR PAYPAL =====
    function initPayPal() {
        if (paypalReady || !window.paypal) return;
            paypal.Buttons({
                createOrder: function(data, actions) {
                    const total = parseFloat($('#total_display').text().replace('$', '').replace(',', ''));
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: total.toFixed(2),
                                currency_code: 'MXN'
                        }]
                    });
                onApprove: function(data, actions) {
                    $('#preloader').addClass('active');
                    return actions.order.capture().then(function() {
                        $('#payment_id').val(data.orderID);
                        $('#payment_method').val('paypal');
                        setTimeout(() => {
                            document.getElementById('checkout-form').submit();
                        }, 500);
                onError: function() {
                    alert('Error con PayPal');
            }).render('#paypal-button-container');
            paypalReady = true;
            console.error('Error PayPal:', error);
    // ===== EVENTOS =====
    $('.tip-option').click(function() {
        $('.tip-option').removeClass('selected');
        $('#custom_tip').val($(this).data('value'));
    $('#custom_tip').on('input', function() {
    $('input[name="tipo_pedido"]').change(function() {
        console.log('🔄 Cambio tipo pedido:', tipoPedidoSeleccionado, '| Es pickup:', isPickup);
        $('#pickup-time-container').toggle(isPickup);
        if (!isPickup) $('#pickup_time').val('');
        // ✅ IMPORTANTE: Recalcular resumen cuando cambia tipo de pedido
    $('#submit-button').click(function() {
        if (!$("input[name='direccion_id']:checked").length) {
            alert("Por favor selecciona una dirección");
        if (!selectedPaymentMethod) {
            alert("Por favor selecciona un método de pago");
        if (selectedPaymentMethod === 'efectivo') {
            $('#payment_id').val('cash_payment');
            $('#payment_method').val('efectivo');
            $('#preloader').addClass('active');
            $('#preloader-message').text('Creando pedido...');
            setTimeout(() => {
}
                document.getElementById('checkout-form').submit();
            }, 1000);
        } else if (selectedPaymentMethod === 'mercadopago') {
            alert('Por favor completa los datos de tu tarjeta y presiona "Pagar" en el formulario azul.\n\n💡 La comisión mostrada (3.36% + $2.50) se calcula correctamente sobre el subtotal del pedido.');
            alert('Por favor completa el pago con el botón de PayPal.');
    $('.address-card').click(function() {
        $('.address-card').removeClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    // ===== HACER FUNCIONES GLOBALES =====
    window.initMercadoPagoBrick = initMercadoPagoBrick;
    // ===== INICIALIZACIÓN =====
    // Primero actualizar resumen con valores por defecto correctos
    updateSummary();
    // ✅ NO AUTO-SELECCIONAR MercadoPago al inicio para evitar inicialización con valores incorrectos
    // El usuario debe seleccionar el método de pago después de configurar propina y tipo de pedido
    console.log('✅ Sistema de checkout iniciado - Listo para que el usuario seleccione método de pago');
// ===== FUNCIÓN TOGGLE PARA RESUMEN MÓVIL =====
function toggleSummary() {
    const summaryContent = document.getElementById('summary-content');
    const summaryArrow = document.getElementById('summary-arrow');
    if (summaryContent && summaryArrow) {
        const isCollapsed = summaryContent.classList.contains('collapsed');
        if (isCollapsed) {
            // Mostrar resumen
            summaryContent.classList.remove('collapsed');
            summaryArrow.className = 'fas fa-chevron-up summary-toggle-arrow';
            // Ocultar resumen
            summaryContent.classList.add('collapsed');
            summaryArrow.className = 'fas fa-chevron-down summary-toggle-arrow';
        console.log('📱 Toggle resumen:', isCollapsed ? 'Mostrado' : 'Ocultado');
// ===== INICIALIZAR ESTADO MÓVIL =====
    // En móviles, iniciar con resumen colapsado
    if (window.innerWidth <= 768) {
        const summaryContent = document.getElementById('summary-content');
        const summaryArrow = document.getElementById('summary-arrow');
        if (summaryContent && summaryArrow) {
</body>
</html>
}
