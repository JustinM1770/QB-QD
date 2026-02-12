<?php
// Errores desactivados en producción - usar logs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Función para manejo seguro de errores
function handleError($message, $redirectToLogin = false) {
    error_log("Error en perfil.php: " . $message);
    if ($redirectToLogin) {
        header('Location: login.php');
}
        exit;
    }
    die("Error: " . htmlspecialchars($message));
}
// 1. MANEJO ROBUSTO DE SESIONES
try {
    if (session_status() === PHP_SESSION_NONE) {
        if (!session_start()) {
            throw new Exception('No se pudo iniciar la sesión');
        }
    
    if (!isset($_SESSION['id_usuario']) || empty($_SESSION['id_usuario'])) {
        throw new Exception('Usuario no autenticado');
    error_log("Usuario autenticado: " . $_SESSION['id_usuario']);
} catch (Exception $e) {
    handleError("Error de sesión: " . $e->getMessage(), true);
// 2. VERIFICACIÓN Y CARGA DE ARCHIVOS REQUERIDOS
$required_files = [
    'config/database.php' => 'Configuración de base de datos',
    'models/Usuario.php' => 'Modelo Usuario',
    'models/DireccionUsuario.php' => 'Modelo DireccionUsuario',
    'models/MetodoPago.php' => 'Modelo MetodoPago',
    'models/Pedido.php' => 'Modelo Pedido',
    'models/Membership.php' => 'Modelo Membership'
];
// Verificar existencia de archivos
foreach ($required_files as $file => $description) {
    if (!file_exists($file)) {
        handleError("Archivo requerido no encontrado: {$file} ({$description})");
// Incluir archivos con manejo de errores
    try {
        require_once $file;
        error_log("Archivo incluido correctamente: " . $file);
    } catch (Exception $e) {
        handleError("Error al incluir {$file}: " . $e->getMessage());
    } catch (ParseError $e) {
        handleError("Error de sintaxis en {$file}: " . $e->getMessage());
// Verificar archivo opcional
if (file_exists('api/Referral.php')) {
        require_once 'api/Referral.php';
        error_log("Error al cargar Referral.php: " . $e->getMessage());
// 3. CONEXIÓN A BASE DE DATOS
    if (!class_exists('Database')) {
        throw new Exception('Clase Database no encontrada');
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    // Verificar conexión activa
        $stmt = $db->prepare("SELECT 1");
        if (!$stmt || !$stmt->execute()) {
}
            throw new Exception("Error en conexión: " . implode(" ", $db->errorInfo()));
    } catch (PDOException $e) {
        throw new Exception("Error de PDO: " . $e->getMessage());
    error_log("Conexión a BD verificada correctamente");
    handleError("Error crítico de base de datos: " . $e->getMessage());
// 4. VERIFICAR E INSTANCIAR MODELOS
$required_classes = [
    'Usuario' => 'models/Usuario.php',
    'DireccionUsuario' => 'models/DireccionUsuario.php',
    'MetodoPago' => 'models/MetodoPago.php',
    'Pedido' => 'models/Pedido.php',
    'Membership' => 'models/Membership.php'
foreach ($required_classes as $class => $file) {
    if (!class_exists($class)) {
        handleError("Clase {$class} no encontrada (requerida de {$file})");
// Instanciar objetos con manejo de errores
    $usuario = new Usuario($db);
    $direccion = new DireccionUsuario($db);
    $metodoPago = new MetodoPago($db);
    $pedido = new Pedido($db);
    $membership = new Membership($db);
    // Instanciar Referral solo si existe
    $referral = null;
    if (class_exists('Referral')) {
        $referral = new Referral($db);
    handleError("Error al instanciar modelos: " . $e->getMessage());
// 5. OBTENER DATOS DEL USUARIO
$usuario->id_usuario = $_SESSION['id_usuario'];
    if (!$usuario->obtenerPorId()) {
        error_log("Advertencia: No se pudieron obtener datos completos del usuario ID: " . $_SESSION['id_usuario']);
        // No es crítico, continuamos
    error_log("Error al obtener datos del usuario: " . $e->getMessage());
    // Inicializar valores por defecto
    $usuario->nombre = '';
    $usuario->apellido = '';
    $usuario->email = '';
    $usuario->telefono = '';
// 6. VERIFICAR MEMBRESÍA
$esMiembroActivo = false;
$diasRestantesMembresia = 0;
$infoMembresia = null;
    // Establecer el ID del usuario en el objeto membership
    $membership->id_usuario = $_SESSION['id_usuario'];
    // Verificar si tiene membresía activa
    $esMiembroActivo = $membership->isActive();
    if ($esMiembroActivo) {
        $infoMembresia = $membership->getActiveMembership();
        if ($infoMembresia && isset($infoMembresia['fecha_fin'])) {
            $fechaFin = new DateTime($infoMembresia['fecha_fin']);
            $fechaHoy = new DateTime();
            
            // Solo calcular días restantes si la fecha de fin es futura
            if ($fechaFin > $fechaHoy) {
                $interval = $fechaHoy->diff($fechaFin);
}
                $diasRestantesMembresia = $interval->days;
            } else {
                // La membresía expiró
                $esMiembroActivo = false;
                $diasRestantesMembresia = 0;
            }
    error_log("Estado membresía - Usuario ID: " . $_SESSION['id_usuario'] . ", Activo: " . ($esMiembroActivo ? 'Sí' : 'No') . ", Días restantes: " . $diasRestantesMembresia);
    error_log("Error al verificar membresía: " . $e->getMessage());
    // Valores por defecto en caso de error
    $esMiembroActivo = false;
    $diasRestantesMembresia = 0;
}   
$datosReferidos = [
    'total_referidos' => 0,
    'referidos_que_compraron' => 0, // 2 productos O membresía
    'compras_realizadas' => 0, // Compras del usuario actual
    'beneficio_referido_disponible' => false, // Pedido gratis por referir
    'beneficio_referido_usado' => false,
    'descuento_10_compras_disponible' => false, // 15% descuento por 10 compras
    'descuento_10_compras_usado' => false,
    'tiene_membresia' => false,
    'codigo_referido' => '',
    'enlace_referido' => ''
if ($referral) {
        $userId = $_SESSION['id_usuario'];
        
        // Generar código de referido seguro
        $datosReferidos['codigo_referido'] = substr(md5($userId . 'quickbite_salt'), 0, 8);
        $datosReferidos['enlace_referido'] = "https://quickbite.com.mx/register.php?ref=" . $datosReferidos['codigo_referido'];
        // Obtener estadísticas de referidos
        $datosReferidos['total_referidos'] = $referral->countTotalReferrals($userId);
        $datosReferidos['referidos_que_compraron'] = $referral->countReferralsWithPurchasesOrMembership($userId);
        // Obtener número de compras del usuario actual
        $pedido = new Pedido($db);
        $datosReferidos['compras_realizadas'] = $pedido->contarComprasCompletadas($userId);
        // Verificar si tiene membresía activa
        $datosReferidos['tiene_membresia'] = $esMiembroActivo;
        // NUEVA LÓGICA DE BENEFICIOS:
        // 1. Beneficio por referir: 2 usuarios que compren 2 productos O membresía = pedido gratis
        $datosReferidos['beneficio_referido_disponible'] = $datosReferidos['referidos_que_compraron'] >= 2;
        $datosReferidos['beneficio_referido_usado'] = $referral->haUsadoBeneficioReferido($userId);
        // 2. Descuento por fidelidad: 10 compras + membresía activa = 15% descuento próxima compra
        $datosReferidos['descuento_10_compras_disponible'] = ($datosReferidos['compras_realizadas'] >= 10 && $datosReferidos['tiene_membresia']);
        $datosReferidos['descuento_10_compras_usado'] = $referral->haUsadoDescuentoFidelidad($userId);
        error_log("Error al obtener datos de referidos: " . $e->getMessage());
// Verificar si puede usar el beneficio
$puedeUsarBeneficio = $datosReferidos['beneficio_referido_disponible'] && !$datosReferidos['beneficio_usado'];
// 8. PROCESAR FORMULARIO DE ACTUALIZACIÓN
$mensaje = '';
$tipo_mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
        // Validaciones básicas
        if (empty($_POST['nombre']) || empty($_POST['apellido']) || empty($_POST['email']) || empty($_POST['telefono'])) {
            throw new Exception('Todos los campos son obligatorios');
        // Validar formato de email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El formato del correo electrónico no es válido');
}
        // Validar formato de teléfono
        if (!preg_match('/^\+?[0-9]{7,15}$/', $_POST['telefono'])) {
            throw new Exception('El número de teléfono no tiene un formato válido');
        $emailAnterior = $usuario->email;
        $emailNuevo = $_POST['email'];
        // Asignar valores del formulario
        $usuario->nombre = trim($_POST['nombre']);
        $usuario->apellido = trim($_POST['apellido']);
        $usuario->email = trim($emailNuevo);
        $usuario->telefono = trim($_POST['telefono']);
        // Manejar cambio de contraseña
        if (!empty($_POST['nueva_password']) && !empty($_POST['confirmar_password'])) {
            if ($_POST['nueva_password'] !== $_POST['confirmar_password']) {
                throw new Exception('Las contraseñas nuevas no coinciden');
            if (empty($_POST['password_actual'])) {
                throw new Exception('Debes proporcionar la contraseña actual');
            if (empty($usuario->password)) {
                throw new Exception('No se pudo verificar la contraseña actual');
            if (!password_verify($_POST['password_actual'], $usuario->password)) {
                throw new Exception('La contraseña actual es incorrecta');
            $usuario->password = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
        // Actualizar perfil
        if ($emailNuevo !== $emailAnterior) {
            // Manejar cambio de email
            $codigoVerificacion = substr(str_shuffle('0123456789'), 0, 6);
            $usuario->verification_code = $codigoVerificacion;
            $usuario->is_verified = 0;
            $query = "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ?, verification_code = ?, is_verified = 0" . 
                     (!empty($usuario->password) ? ", password = ?" : "") . " WHERE id_usuario = ?";
            $stmt = $db->prepare($query);
            if (!empty($usuario->password)) {
                $stmt->bind_param("ssssssi", $usuario->nombre, $usuario->apellido, $usuario->email, 
                                 $usuario->telefono, $codigoVerificacion, $usuario->password, $usuario->id_usuario);
                $stmt->bind_param("sssssi", $usuario->nombre, $usuario->apellido, $usuario->email, 
                                 $usuario->telefono, $codigoVerificacion, $usuario->id_usuario);
            if (!$stmt->execute()) {
                throw new Exception('Error al actualizar el perfil: ' . $stmt->error);
            // Intentar enviar email de verificación
            if (file_exists('api/send_verification_email.php')) {
                try {
                    require_once 'api/send_verification_email.php';
                    $sendEmail = new SendVerificationEmail($db);
                    $sendEmail->send($usuario->id_usuario);
}
                    $mensaje = 'Perfil actualizado correctamente. Se ha enviado un código de verificación a tu nuevo correo.';
                } catch (Exception $e) {
                    error_log("Error al enviar email de verificación: " . $e->getMessage());
                    $mensaje = 'Perfil actualizado correctamente. No se pudo enviar el correo de verificación.';
                }
                $mensaje = 'Perfil actualizado correctamente.';
            $tipo_mensaje = 'success';
        } else {
            // Actualización normal sin cambio de email
            if ($usuario->actualizar()) {
                $mensaje = 'Perfil actualizado correctamente';
                $tipo_mensaje = 'success';
                throw new Exception('Error al actualizar el perfil');
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
        error_log("Error en actualización de perfil: " . $e->getMessage());
// 9. OBTENER DATOS ADICIONALES (con manejo de errores)
$direcciones = [];
$metodosPago = [];
$historialPedidos = [];
    $direcciones = $direccion->obtenerPorUsuarioId($usuario->id_usuario);
    error_log("Error al obtener direcciones: " . $e->getMessage());
    $metodosPago = $metodoPago->obtenerPorUsuario($usuario->id_usuario);
    error_log("Error al obtener métodos de pago: " . $e->getMessage());
    $historialPedidos = $pedido->obtenerHistorialUsuario($usuario->id_usuario, 5);
    error_log("Error al obtener historial: " . $e->getMessage());
// Título de la página
$titulo_pagina = "Mi Perfil";
// 10. INCLUIR ARCHIVO OPCIONAL
if (file_exists('perfil_puntos.php')) {
    // Solo mostrar mensaje si hay error
        // No incluir aquí, se incluirá en el HTML cuando sea necesario
        error_log("Advertencia: Error en perfil_puntos.php: " . $e->getMessage());
error_log("Perfil cargado exitosamente para usuario: " . $_SESSION['id_usuario']);
$puedeUsarBeneficioReferido = $datosReferidos['beneficio_referido_disponible'] && !$datosReferidos['beneficio_referido_usado'];
$puedeUsarDescuentoFidelidad = $datosReferidos['descuento_10_compras_disponible'] && !$datosReferidos['descuento_10_compras_usado'];
?>
<!-- Resto del HTML permanece igual -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina ?? "Mi Perfil") ?> - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #4285F4;
            --primary-dark: #0052CC;
            --secondary: #F8FAFC;
            --accent: #1E293B;
            --dark: #0F172A;
            --light: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --gradient: linear-gradient(135deg, #0165FF 0%, #4285F4 100%);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 16px;
            --border-radius-lg: 20px;
            --border-radius-xl: 24px;
            --border-radius-full: 50px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-900);
            line-height: 1.6;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-weight: 400;
            min-height: 100vh;
            padding-bottom: 100px;
        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 0.5rem;
            color: var(--dark);
        /* Header */
        .header {
            background: var(--light);
            border-bottom: 2px solid var(--gray-100);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-sm);
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            gap: 0.5rem;
        .header-title {
            font-size: 1.25rem;
        .back-btn {
            color: var(--gray-600);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        .back-btn:hover {
            background-color: var(--gray-100);
        .container {
            padding: 2rem 1.5rem;
        /* Content Container */
        .main-content {
            min-height: calc(100vh - 180px);
            margin-bottom: 2rem;
        /* Section */
        .section {
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        .section.active {
            display: block;
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        /* Cards */
        .card {
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            border: 2px solid var(--gray-100);
            overflow: hidden;
        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        .card-header {
            background: var(--gradient);
            color: var(--light);
            padding: 1.5rem;
            border-bottom: none;
        .card-header h5 {
        .card-body {
            padding: 2rem;
        /* Form Controls */
        .form-control, .form-select {
            padding: 1rem 1.25rem;
            border: 2px solid var(--gray-200);
            font-size: 1rem;
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
            outline: none;
        .form-label {
            font-weight: 600;
            color: var(--gray-700);
        /* Buttons */
        .btn {
            border-radius: var(--border-radius-full);
            padding: 0.875rem 1.5rem;
            border: 2px solid transparent;
            font-size: 0.95rem;
        .btn-primary {
            border: none;
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            filter: brightness(1.05);
        .btn-secondary {
            background: var(--gray-100);
            border-color: var(--gray-200);
        .btn-secondary:hover {
            background: var(--gray-200);
            color: var(--gray-800);
            transform: translateY(-1px);
        .btn-outline-primary {
            background: transparent;
        .btn-outline-primary:hover {
            background: var(--primary);
        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        .btn-outline-danger:hover {
            background: #dc3545;
        .btn-outline-success {
            color: #22c55e;
            border-color: #22c55e;
        .btn-outline-success:hover {
            background: #22c55e;
        .btn-sm {
            font-size: 0.875rem;
        .btn-light {
        .btn-light:hover {
        /* Ocultar referidos en pestañas específicas */
.section:not(#perfil) .card:has(.fa-users) {
    display: none !important;
/* Alternativa más específica */
#direcciones .card,
#pagos .card,
#historial .card,
#favoritos .card {
    /* Mantener las cards de cada sección */
/* Ocultar solo la card de referidos en pestañas no-perfil */
body:has(.nav-item:not([data-section="perfil"]).active) .card:has(.fa-users) {
/* Forzar ocultar referidos en pestañas específicas */
.nav-item[data-section="direcciones"].active ~ * #card-referidos,
.nav-item[data-section="pagos"].active ~ * #card-referidos,
.nav-item[data-section="historial"].active ~ * #card-referidos,
.nav-item[data-section="favoritos"].active ~ * #card-referidos {
        /* List Groups */
        .list-group-item {
            border-radius: var(--border-radius) !important;
            margin-bottom: 1rem;
        .list-group-item:hover {
        .list-group-item h6 {
        /* Badges */
        .badge {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        .badge.bg-success {
            background: #22c55e !important;
        .badge.bg-warning {
            background: #f59e0b !important;
        .badge.bg-info {
            background: var(--primary-light) !important;
        .badge.bg-primary {
            background: var(--primary) !important;
        .badge.bg-danger {
            background: #dc3545 !important;
        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border: 2px solid;
        .alert-success {
            background: #f0fdf4;
            color: #166534;
        .alert-danger {
            background: #fef2f2;
            border-color: #ef4444;
            color: #dc2626;
        .alert-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        .alert-info {
            background: #eff6ff;
            color: var(--primary-dark);
        /* Tables */
        .table {
        .table thead th {
            padding: 1rem;
        .table tbody td {
            border-top: 1px solid var(--gray-200);
            vertical-align: middle;
        .table-hover tbody tr:hover {
            background: var(--gray-50);
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            justify-content: space-around;
            padding: 0.75rem 0;
            box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.15);
            border-top: 2px solid var(--gray-100);
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
        .nav-item {
            flex-direction: column;
            color: var(--gray-500);
            padding: 0.5rem;
            font-size: 0.75rem;
            min-width: 60px;
            cursor: pointer;
            flex-shrink: 0;
            white-space: nowrap;
        .nav-item:hover {
            background-color: rgba(1, 101, 255, 0.1);
        .nav-item i {
            margin-bottom: 0.25rem;
        .nav-item.active {
        /* Modals */
        .modal-content {
            border-radius: var(--border-radius-xl);
        .modal-header {
            border-radius: var(--border-radius-xl) var(--border-radius-xl) 0 0;
        .modal-title {
        .btn-close {
            filter: invert(1);
        .modal-body {
        .modal-footer {
            padding: 1.5rem 2rem;
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
        .empty-state h3 {
        .empty-state p {
        /* Loading Spinner */
        .loading-container {
            justify-content: center;
            padding: 3rem;
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        /* Membership Status */
        .membership-status {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            position: relative;
        /* Membresía activa - Fondo dorado */
.membership-status.active {
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #FF8C00 100%);
    box-shadow: 0 4px 20px rgba(255, 215, 0, 0.3);
    animation: goldShimmer 3s infinite;
/* Animación de brillo dorado */
@keyframes goldShimmer {
    0%, 100% { 
        box-shadow: 0 4px 20px rgba(255, 215, 0, 0.3);
    50% { 
        box-shadow: 0 4px 30px rgba(255, 215, 0, 0.5);
/* Efecto de partículas doradas */
.membership-status.active::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
    animation: goldSweep 4s infinite;
    pointer-events: none;
@keyframes goldSweep {
    0% { transform: rotate(0deg) translate(-50%, -50%); }
    100% { transform: rotate(360deg) translate(-50%, -50%); }
.membership-status.inactive {
    background: linear-gradient(135deg, var(--gray-400) 0%, var(--gray-500) 100%);
.membership-status h4 {
    color: var(--light);
    margin-bottom: 0.5rem;
    font-weight: 800;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 1;
.membership-status.active h4 {
    color: #8B4513; /* Color marrón oscuro para contraste con dorado */
    text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.3);
.membership-status p {
    opacity: 0.9;
    margin-bottom: 1rem;
.membership-status.active p {
    color: #654321; /* Color marrón para mejor legibilidad */
    font-weight: 600;
/* Icono de corona dorada para miembros premium */
.membership-status.active .fa-crown {
    color: #FFD700;
    filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.3));
    animation: crownGlow 2s infinite alternate;
@keyframes crownGlow {
    0% { filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.3)); }
    100% { filter: drop-shadow(1px 1px 6px rgba(255, 215, 0, 0.8)); }
/* Días restantes con estilo especial para premium */
.membership-days-remaining {
    font-size: 1.1rem;
    font-weight: 700;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-full);
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: inline-block;
    margin-top: 0.5rem;
.membership-status.active .membership-days-remaining {
    background: rgba(139, 69, 19, 0.3);
    color: #2D1810;
    border: 2px solid rgba(255, 215, 0, 0.5);
        .membership-status.inactive {
            background: linear-gradient(135deg, var(--gray-400) 0%, var(--gray-500) 100%);
        .membership-status h4 {
        .membership-status p {
            opacity: 0.9;
        .membership-btn {
            padding: 0.75rem 1.5rem;
            display: inline-block;
            z-index: 1;
        .membership-btn:hover {
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            .header-content {
                padding: 0 1rem;
            .header-title {
                font-size: 1.1rem;
            .card-header {
                padding: 1.25rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            .card-body {
                padding: 1.5rem;
            .table-responsive {
                border-radius: var(--border-radius);
                overflow: hidden;
            .bottom-nav {
                padding: 0.6rem 0.25rem;
                gap: 0.25rem;
                display: flex;
                justify-content: space-evenly;
            .nav-item {
                font-size: 0.7rem;
                padding: 0.5rem 0.3rem;
                min-width: 58px;
                flex: 1 1 auto;
                max-width: 80px;
            .nav-item i {
                font-size: 1.15rem;
                margin-bottom: 0.3rem;
            .nav-item span {
                line-height: 1.2;
                display: block;
        @media (max-width: 480px) {
            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.875rem;
            .list-group-item {
                padding: 0.5rem 0.2rem;
                gap: 0.15rem;
                font-size: 0.65rem;
                padding: 0.4rem 0.2rem;
                min-width: 52px;
                max-width: 70px;
                font-size: 1.05rem;
                margin-bottom: 0.2rem;
        @media (max-width: 360px) {
                padding: 0.45rem 0.1rem;
                gap: 0.1rem;
                font-size: 0.6rem;
                padding: 0.35rem 0.15rem;
                min-width: 48px;
                max-width: 62px;
                font-size: 1rem;
                margin-bottom: 0.15rem;
                text-overflow: ellipsis;
                white-space: nowrap;
                max-width: 100%;
                font-weight: 600;
        /* Mejorar toque en dispositivos táctiles */
        @media (hover: none) and (pointer: coarse) {
                min-height: 50px;
                padding: 0.6rem 0.4rem;
            .nav-item:active {
                transform: scale(0.95);
                background-color: rgba(1, 101, 255, 0.15);
        /* Focus states for accessibility */
        button:focus,
        input:focus,
        select:focus,
        textarea:focus,
        a:focus {
            outline: 3px solid var(--primary);
            outline-offset: 2px;
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
            <div class="header-title">Mi Perfil</div>
            <a href="logout.php" class="btn btn-primary btn-sm">Cerrar sesión</a>
        </div>
    </header>
    <div class="container">
        <div class="main-content">
            <!-- Sección Perfil -->
            <div id="perfil" class="section active">
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <!-- Estado de Membresía -->
              
<!-- Estado de Membresía - CORREGIDO -->
<div class="membership-status <?php echo $esMiembroActivo ? 'active' : 'inactive'; ?>">
    <h4>
        <i class="fas fa-crown me-2"></i>
        <?php echo $esMiembroActivo ? 'QuickBite Premium' : 'Usuario Regular'; ?>
    </h4>
    <?php if ($esMiembroActivo): ?>
        <p style="font-weight: 600;">
            ¡Disfruta de todos los beneficios Premium!
        </p>
        <div class="membership-days-remaining">
            <i class="fas fa-calendar-alt me-1"></i>
            Días restantes: <strong><?php echo $diasRestantesMembresia; ?></strong>
        <?php if ($diasRestantesMembresia <= 7): ?>
            <div class="alert alert-warning mt-3 mb-0" style="background: rgba(255, 193, 7, 0.2); border-color: #ffc107; color: #856404;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Tu membresía expira pronto. <a href="membership_subscribe.php" class="alert-link">Renovar ahora</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p>Únete a QuickBite Premium y disfruta beneficios exclusivos</p>
        <a href="membership_subscribe.php" class="membership-btn">
            <i class="fas fa-star me-2"></i>Suscribirse ahora
        </a>
    <?php endif; ?>
</div>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i>Información Personal</h5>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario->nombre); ?>" required>
                                </div>
                                    <label for="apellido" class="form-label">Apellido</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($usuario->apellido); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario->email); ?>" required>
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario->telefono); ?>" required>
                            <hr class="my-4">
                            <h6>Cambiar contraseña</h6>
                                <label for="password_actual" class="form-label">Contraseña actual</label>
                                <input type="password" class="form-control" id="password_actual" name="password_actual">
                                    <label for="nueva_password" class="form-label">Nueva contraseña</label>
                                    <input type="password" class="form-control" id="nueva_password" name="nueva_password">
                                    <label for="confirmar_password" class="form-label">Confirmar nueva contraseña</label>
                                    <input type="password" class="form-control" id="confirmar_password" name="confirmar_password">
                            <button type="submit" name="actualizar_perfil" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar cambios
                            </button>
                        </form>
                </div>
                <!-- Programa de referidos -->
                <?php include 'perfil_puntos.php'; ?>
    <div class="card" id="card-referidos">
                        <h5><i class="fas fa-users me-2"></i>Programa de Referidos y Beneficios</h5>
                <!-- Estadísticas de referidos -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-primary mb-1"><?php echo $datosReferidos['total_referidos']; ?></h4>
                            <small class="text-muted">Total referidos</small>
                        </div>
                            <h4 class="text-success mb-1"><?php echo $datosReferidos['referidos_que_compraron']; ?>/2</h4>
                            <small class="text-muted">Referidos activos</small>
                            <h4 class="text-info mb-1"><?php echo $datosReferidos['compras_realizadas']; ?></h4>
                            <small class="text-muted">Tus compras</small>
                            <h4 class="<?php echo $datosReferidos['tiene_membresia'] ? 'text-warning' : 'text-muted'; ?> mb-1">
                                <i class="fas fa-crown"></i>
                            </h4>
                            <small class="text-muted">Membresía</small>
        <!-- BENEFICIO 1: Pedido gratis por referir -->
        <div class="benefit-section mb-4 p-3 border rounded">
            <h6 class="d-flex align-items-center mb-3">
                <i class="fas fa-gift text-success me-2"></i>
                Beneficio por Referir Amigos
            </h6>
            <?php if ($puedeUsarBeneficioReferido): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>¡Pedido gratis desbloqueado!</strong>
                    <br>
                    <small>Has referido <?php echo $datosReferidos['referidos_que_compraron']; ?> amigos que compraron 2 productos o membresía.</small>
                    <button class="btn btn-success btn-sm mt-2" onclick="usarBeneficioReferido()">
                        <i class="fas fa-gift me-1"></i>Activar pedido gratis
                    </button>
            <?php elseif ($datosReferidos['beneficio_referido_disponible']): ?>
                <div class="alert alert-info">
                    Ya utilizaste tu pedido gratis por referir amigos. ¡Gracias por compartir QuickBite!
            <?php else: ?>
                <div class="progress-option">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Refiere 2 amigos que compren 2 productos o membresía</span>
                <span class="badge bg-primary"><?php echo $datosReferidos['referidos_que_compraron']; ?>/2</span>
            <div class="progress">
                <div class="progress-bar bg-success" role="progressbar" 
                    style="width: <?php echo min(100, ($datosReferidos['referidos_que_compraron'] / 2) * 100); ?>%">
                    <small class="text-muted">Beneficio: Pedido completo + envío gratis (hasta $300 MXN)</small>
            <?php endif; ?>
        <!-- BENEFICIO 2: Descuento por fidelidad -->
                <i class="fas fa-star text-warning me-2"></i>
                Beneficio de Fidelidad
            <?php if ($puedeUsarDescuentoFidelidad): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-crown me-2"></i>
                    <strong>¡15% de descuento desbloqueado!</strong>
                    <small>Has realizado <?php echo $datosReferidos['compras_realizadas']; ?> compras y tienes membresía activa.</small>
                    <button class="btn btn-warning btn-sm mt-2" onclick="usarDescuentoFidelidad()">
                        <i class="fas fa-percentage me-1"></i>Activar 15% descuento
            <?php elseif ($datosReferidos['descuento_10_compras_usado']): ?>
                    Ya utilizaste tu descuento del 15%. ¡Sigue comprando para obtener más beneficios!
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="progress-option">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Realiza 10 compras</span>
                                <span class="badge <?php echo $datosReferidos['compras_realizadas'] >= 10 ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo min(10, $datosReferidos['compras_realizadas']); ?>/10
                                </span>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo min(100, ($datosReferidos['compras_realizadas'] / 10) * 100); ?>%">
                                <span>Tener membresía activa</span>
                                <span class="badge <?php echo $datosReferidos['tiene_membresia'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $datosReferidos['tiene_membresia'] ? 'Activa' : 'Inactiva'; ?>
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo $datosReferidos['tiene_membresia'] ? '100' : '0'; ?>%">
                <small class="text-muted">Beneficio: 15% de descuento en tu próxima compra (requiere ambas condiciones)</small>
                <?php if (!$datosReferidos['tiene_membresia']): ?>
                    <a href="membership_subscribe.php" class="btn btn-outline-warning btn-sm mt-2">
                        <i class="fas fa-crown me-1"></i>Obtener membresía
                    </a>
        <!-- Enlace de referido -->
        <div class="mt-4" >
            <label class="form-label"><strong>Tu enlace de referido:</strong></label>
            <div class="input-group">
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($datosReferidos['enlace_referido']); ?>" readonly id="enlaceReferido">
                <button class="btn btn-outline-primary" type="button" onclick="copiarEnlace()">
                    <i class="fas fa-copy me-2"></i>Copiar
                </button>
                <button class="btn btn-outline-secondary" type="button" onclick="compartirEnlace()">
                    <i class="fas fa-share-alt me-2"></i>Compartir
            <small class="text-muted">
                Código: <code><?php echo $datosReferidos['codigo_referido']; ?></code>
                <br>
                Comparte este enlace para que tus amigos se registren y obtengas beneficios cuando realicen compras.
            </small>
        <!-- Historial de referidos (solo si tiene referidos) -->
        <?php if ($datosReferidos['total_referidos'] > 0): ?>
            <div class="mt-4">
                <button class="btn btn-outline-info btn-sm" onclick="toggleHistorialReferidos()">
                    <i class="fas fa-history me-1"></i>Ver referidos registrados
                <div id="historialReferidos" style="display: none;" class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Referido</th>
                                    <th>Fecha registro</th>
                                    <th>Compras/Membresía</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tablaReferidos">
                                <tr><td colspan="4" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
    </div>
            <!-- FIN Sección Perfil -->
            <!-- Sección Direcciones -->
            <div id="direcciones" class="section">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>Mis Direcciones</h5>
                        <button class="btn btn-light btn-sm" onclick="abrirModalDireccion()">
                            <i class="fas fa-plus me-2"></i>Añadir
                        </button>
                        <div id="listaDirecciones">
                            <!-- Las direcciones se cargarán aquí dinámicamente -->
                            <div class="loading-container">
                                <div class="spinner"></div>
                                <p>Cargando direcciones...</p>
            <!-- Sección Métodos de Pago -->
            <div id="pagos" class="section">
                        <h5><i class="fas fa-credit-card me-2"></i>Métodos de Pago</h5>
                        <button class="btn btn-light btn-sm" onclick="abrirModalPago()">
                        <div id="listaMetodosPago">
                            <!-- Los métodos de pago se cargarán aquí dinámicamente -->
                                <p>Cargando métodos de pago...</p>
            <!-- Sección Historial -->
            <div id="historial" class="section">
                        <h5><i class="fas fa-history me-2"></i>Historial de Pedidos</h5>
                        <div id="listaHistorial">
                            <!-- El historial se cargará aquí dinámicamente -->
                                <p>Cargando historial...</p>
            <!-- Sección Favoritos -->
            <div id="favoritos" class="section">
                        <h5><i class="fas fa-heart me-2"></i>Mis Favoritos</h5>
                        <div id="listaFavoritos">
                            <!-- Los favoritos se cargarán aquí dinámicamente -->
                                <p>Cargando favoritos...</p>
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="nav-item active" data-section="perfil">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        <div class="nav-item" data-section="direcciones">
            <i class="fas fa-map-marker-alt"></i>
            <span>Direcciones</span>
        <div class="nav-item" data-section="pagos">
            <i class="fas fa-credit-card"></i>
            <span>Pagos</span>
        <div class="nav-item" data-section="historial">
            <i class="fas fa-history"></i>
            <span>Historial</span>
        <div class="nav-item" data-section="favoritos">
            <i class="fas fa-heart"></i>
            <span>Favoritos</span>
    </nav>
    <!-- Modal para Dirección -->
    <div class="modal fade" id="modalDireccion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDireccionTitulo">Nueva Dirección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                <div class="modal-body">
                    <form id="formDireccion">
                        <input type="hidden" id="direccionId" name="direccion_id">
                        <div class="mb-3">
                            <label class="form-label">Nombre de la dirección</label>
                            <input type="text" class="form-control" id="nombreDireccion" name="nombre_direccion" placeholder="Ej. Casa" required>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Calle</label>
                                <input type="text" class="form-control" id="calle" name="calle" required>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Número</label>
                                <input type="text" class="form-control" id="numero" name="numero" required>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Colonia</label>
                                <input type="text" class="form-control" id="colonia" name="colonia" required>
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" id="ciudad" name="ciudad" required>
                                <label class="form-label">Código Postal</label>
                                <input type="text" class="form-control" id="codigoPostal" name="codigo_postal" required>
                                <label class="form-label">Estado</label>
                                <input type="text" class="form-control" id="estado" name="estado" required>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="predeterminada" name="predeterminada">
                            <label class="form-check-label" for="predeterminada">Establecer como predeterminada</label>
                    </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarDireccion()">Guardar</button>
    <!-- Modal para Método de Pago -->
    <div class="modal fade" id="modalPago" tabindex="-1">
                    <h5 class="modal-title">Nuevo Método de Pago</h5>
                    <form id="formPago">
                            <label class="form-label">Tipo de pago</label>
                            <select class="form-select" id="tipoPago" name="tipo_pago" required onchange="mostrarCamposPago()">
                                <option value="">Seleccionar...</option>
                                <option value="tarjeta">Tarjeta de crédito/débito</option>
                                <option value="paypal">PayPal</option>
                                <option value="efectivo">Efectivo</option>
                            </select>
                        
                        <!-- Campos para tarjeta -->
                        <div id="camposTarjeta" style="display: none;">
                                <label class="form-label">Nombre en la tarjeta</label>
                                <input type="text" class="form-control" id="nombreTarjeta" name="nombre_tarjeta">
                                <label class="form-label">Número de tarjeta</label>
                                <input type="text" class="form-control" id="numeroTarjeta" name="numero_tarjeta" maxlength="16">
                        <!-- Campos para PayPal -->
                        <div id="camposPaypal" style="display: none;">
                                <label class="form-label">Correo electrónico de PayPal</label>
                                <input type="email" class="form-control" id="correoPaypal" name="correo_paypal">
                            <input class="form-check-input" type="checkbox" id="metodoPredeterminado" name="predeterminado">
                            <label class="form-check-label" for="metodoPredeterminado">Establecer como predeterminado</label>
                    <button type="button" class="btn btn-primary" onclick="guardarMetodoPago()">Guardar</button>
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
// JavaScript adicional para el programa de referidos
function usarBeneficio() {
    if (confirm('¿Estás seguro de que quieres usar un beneficio ahora? Se aplicará a tu próximo pedido.')) {
        fetch('api/use_referral_benefit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'use_benefit'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Beneficio activado. Se aplicará en tu próximo pedido.', 'success');
                location.reload(); // Recargar para actualizar el contador
                mostrarNotificacion('Error: ' + data.message, 'danger');
        .catch(error => {
            mostrarNotificacion('Error al activar beneficio', 'danger');
}
            console.error('Error:', error);
        });
function compartirEnlace() {
    if (navigator.share) {
        navigator.share({
            title: 'Únete a QuickBite',
            text: '¡Únete a QuickBite con mi enlace de referido y obtén beneficios!',
}
            url: document.getElementById('enlaceReferido').value
    } else {
        // Fallback para navegadores que no soportan Web Share API
        copiarEnlace();
        mostrarNotificacion('Enlace copiado. Compártelo manualmente.', 'info');
function toggleHistorialReferidos() {
    const historial = document.getElementById('historialReferidos');
    if (historial.style.display === 'none') {
        historial.style.display = 'block';
        cargarHistorialReferidos();
        historial.style.display = 'none';
function cargarHistorialReferidos() {
    fetch('api/get_referral_history.php')
            const tbody = document.getElementById('tablaReferidos');
            if (data.success && data.referidos.length > 0) {
                let html = '';
                data.referidos.forEach(referido => {
                    const estado = referido.activo ? 'Activo' : 'Pendiente';
}
                    const badgeClass = referido.activo ? 'bg-success' : 'bg-warning';
                    const progreso = `${referido.pedidos_completados}/2 pedidos`;
                    
                    html += `
                        <tr>
                            <td>${referido.nombre_usuario || 'Usuario #' + referido.id}</td>
                            <td>${referido.fecha_referido}</td>
                            <td><span class="badge ${badgeClass}">${estado}</span></td>
                            <td>${progreso}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay referidos aún</td></tr>';
            document.getElementById('tablaReferidos').innerHTML = 
                '<tr><td colspan="4" class="text-center text-danger">Error al cargar historial</td></tr>';
</script>
       <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        // Variables globales
        let modalDireccion, modalPago;
        let direcciones = [];
        let metodosPago = [];
        let historial = [];
        let favoritos = [];
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar modales
            modalDireccion = new bootstrap.Modal(document.getElementById('modalDireccion'));
            modalPago = new bootstrap.Modal(document.getElementById('modalPago'));
            // Manejar navegación
            setupNavigation();
            // Cargar datos iniciales solo para la sección de perfil
            // Las otras secciones se cargarán cuando se acceda a ellas
        // Configurar navegación
        function setupNavigation() {
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    const section = this.dataset.section;
                    cambiarSeccion(section);
            });
        // Cambiar sección
    // Cambiar sección
function cambiarSeccion(seccion) {
    // Ocultar todas las secciones
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    // Mostrar sección seleccionada
    document.getElementById(seccion).classList.add('active');
    // Actualizar navegación
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    document.querySelector(`[data-section="${seccion}"]`).classList.add('active');
    // OCULTAR REFERIDOS Y MEMBRESÍA EN OTRAS PESTAÑAS
    const cardReferidos = document.getElementById('card-referidos');
    const membershipStatus = document.querySelector('.membership-status');
    const perfilSection = document.getElementById('perfil');
    if (seccion === 'perfil') {
        // Mostrar todo en perfil
        if (cardReferidos) cardReferidos.style.display = 'block';
        if (membershipStatus) membershipStatus.style.display = 'block';
        // Restaurar todos los alerts
        if (perfilSection) {
            const alerts = perfilSection.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = '';
        // Ocultar card de referidos y membresía
        if (cardReferidos) cardReferidos.style.display = 'none';
        if (membershipStatus) membershipStatus.style.display = 'none';
        // Ocultar todos los alerts que están dentro de card-referidos o membership-status
            // Alerts dentro de card-referidos
            if (cardReferidos) {
                const alertsReferidos = cardReferidos.querySelectorAll('.alert');
                alertsReferidos.forEach(alert => {
                    alert.style.display = 'none';
            // Alerts dentro de membership-status
            if (membershipStatus) {
                const alertsMembresia = membershipStatus.querySelectorAll('.alert');
                alertsMembresia.forEach(alert => {
    // Cargar datos según la sección
    switch(seccion) {
        case 'direcciones':
            cargarDirecciones();
            break;
        case 'pagos':
            cargarMetodosPago();
        case 'historial':
            cargarHistorial();
        case 'favoritos':
            cargarFavoritos();
        // ===== FUNCIONES PARA DIRECCIONES =====
        function cargarDirecciones() {
            const container = document.getElementById('listaDirecciones');
            // Mostrar loading
            container.innerHTML = `
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>Cargando direcciones...</p>
            `;
            // Verificar si existe el endpoint
            fetch('api/get_addresses.php')
                .then(response => {
}
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        direcciones = data.direcciones || [];
}
                        mostrarDirecciones();
                    } else {
                        throw new Error(data.message || 'Error desconocido');
                .catch(error => {
                    console.log('API no disponible, mostrando estado vacío:', error);
                    direcciones = [];
                    mostrarDirecciones();
        function mostrarDirecciones() {
            if (!direcciones || direcciones.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>No tienes direcciones guardadas</h3>
                        <p>Añade una dirección para realizar pedidos más rápido</p>
                        <button class="btn btn-primary" onclick="abrirModalDireccion()">
                            <i class="fas fa-plus me-2"></i>Añadir dirección
                `;
                return;
            let html = '';
}
            direcciones.forEach(dir => {
                const direccionCompleta = `${dir.calle || ''} ${dir.numero || ''}, ${dir.colonia || ''}, ${dir.ciudad || ''}`.trim();
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    ${dir.nombre || 'Dirección sin nombre'}
                                    ${dir.predeterminada ? '<span class="badge bg-success ms-2">Predeterminada</span>' : ''}
                                </h6>
                                <p class="mb-1">${direccionCompleta || 'Dirección incompleta'}</p>
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="editarDireccion(${dir.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="eliminarDireccion(${dir.id})">
                                    <i class="fas fa-trash"></i>
            container.innerHTML = html;
        function abrirModalDireccion(id = null) {
            document.getElementById('modalDireccionTitulo').textContent = id ? 'Editar Dirección' : 'Nueva Dirección';
            document.getElementById('formDireccion').reset();
            if (id) {
                const direccion = direcciones.find(d => d.id == id);
                if (direccion) {
                    document.getElementById('direccionId').value = direccion.id;
                    document.getElementById('nombreDireccion').value = direccion.nombre || '';
                    document.getElementById('calle').value = direccion.calle || '';
                    document.getElementById('numero').value = direccion.numero || '';
                    document.getElementById('colonia').value = direccion.colonia || '';
                    document.getElementById('ciudad').value = direccion.ciudad || '';
                    document.getElementById('codigoPostal').value = direccion.codigo_postal || '';
                    document.getElementById('estado').value = direccion.estado || '';
                    document.getElementById('predeterminada').checked = direccion.predeterminada || false;
            modalDireccion.show();
        function guardarDireccion() {
            const formData = new FormData(document.getElementById('formDireccion'));
            const id = formData.get('direccion_id');
            // Simular guardado exitoso
            modalDireccion.hide();
            mostrarNotificacion('Dirección guardada correctamente', 'success');
            // Simular nueva dirección guardada
            const nuevaDireccion = {
                id: id || Date.now(),
                nombre: formData.get('nombre_direccion'),
                calle: formData.get('calle'),
                numero: formData.get('numero'),
                colonia: formData.get('colonia'),
                ciudad: formData.get('ciudad'),
                codigo_postal: formData.get('codigo_postal'),
                estado: formData.get('estado'),
}
                predeterminada: formData.get('predeterminada') === 'on'
            };
                // Actualizar existente
                const index = direcciones.findIndex(d => d.id == id);
                if (index !== -1) {
                    direcciones[index] = nuevaDireccion;
                // Agregar nueva
                direcciones.push(nuevaDireccion);
            mostrarDirecciones();
        function editarDireccion(id) {
            abrirModalDireccion(id);
        function eliminarDireccion(id) {
            if (confirm('¿Estás seguro de eliminar esta dirección?')) {
                direcciones = direcciones.filter(d => d.id != id);
                mostrarDirecciones();
                mostrarNotificacion('Dirección eliminada', 'success');
        // ===== FUNCIONES PARA MÉTODOS DE PAGO =====
        function cargarMetodosPago() {
            const container = document.getElementById('listaMetodosPago');
                    <p>Cargando métodos de pago...</p>
            fetch('api/get_payment_methods.php')
                        metodosPago = data.metodos_pago || [];
                        mostrarMetodosPago();
                    metodosPago = [];
                    mostrarMetodosPago();
        function mostrarMetodosPago() {
            if (!metodosPago || metodosPago.length === 0) {
                        <i class="fas fa-credit-card"></i>
                        <h3>No tienes métodos de pago</h3>
                        <p>Añade un método de pago para realizar pedidos</p>
                        <button class="btn btn-primary" onclick="abrirModalPago()">
                            <i class="fas fa-plus me-2"></i>Añadir método
            metodosPago.forEach(metodo => {
                const icono = getIconoMetodoPago(metodo.tipo);
                const nombreMetodo = getNombreMetodoPago(metodo);
}
                
                                    <i class="${icono} me-2"></i>${nombreMetodo}
                                    ${metodo.predeterminado ? '<span class="badge bg-success ms-2">Predeterminado</span>' : ''}
                                <button class="btn btn-sm btn-outline-primary me-2" onclick="editarMetodoPago(${metodo.id})">
                                <button class="btn btn-sm btn-outline-danger" onclick="eliminarMetodoPago(${metodo.id})">
        function getIconoMetodoPago(tipo) {
            switch(tipo) {
                case 'tarjeta_credito':
                case 'tarjeta_debito':
                case 'tarjeta':
                    return 'fas fa-credit-card';
                case 'paypal':
                    return 'fab fa-paypal';
                case 'efectivo':
                    return 'fas fa-money-bill';
                default:
        function getNombreMetodoPago(metodo) {
            if (metodo.tipo === 'tarjeta_credito' || metodo.tipo === 'tarjeta_debito' || metodo.tipo === 'tarjeta') {
                const proveedor = metodo.proveedor || 'Tarjeta';
}
                const ultimos4 = metodo.numero_cuenta ? metodo.numero_cuenta.slice(-4) : '****';
                return `${proveedor} **** ${ultimos4}`;
            } else if (metodo.tipo === 'paypal') {
                return metodo.correo_paypal || 'PayPal';
            } else if (metodo.tipo === 'efectivo') {
                return 'Efectivo';
                return 'Método de pago';
        function abrirModalPago() {
            document.getElementById('formPago').reset();
            modalPago.show();
        function mostrarCamposPago() {
            const tipo = document.getElementById('tipoPago').value;
            document.getElementById('camposTarjeta').style.display = tipo === 'tarjeta' ? 'block' : 'none';
            document.getElementById('camposPaypal').style.display = tipo === 'paypal' ? 'block' : 'none';
        function guardarMetodoPago() {
            modalPago.hide();
            mostrarNotificacion('Método de pago guardado correctamente', 'success');
            // Simular nuevo método de pago
            const formData = new FormData(document.getElementById('formPago'));
            const nuevoMetodo = {
                id: Date.now(),
                tipo: formData.get('tipo_pago'),
                predeterminado: formData.get('predeterminado') === 'on'
            if (nuevoMetodo.tipo === 'tarjeta') {
                nuevoMetodo.nombre_tarjeta = formData.get('nombre_tarjeta');
}
                nuevoMetodo.numero_cuenta = formData.get('numero_tarjeta');
            } else if (nuevoMetodo.tipo === 'paypal') {
                nuevoMetodo.correo_paypal = formData.get('correo_paypal');
            metodosPago.push(nuevoMetodo);
            mostrarMetodosPago();
        function editarMetodoPago(id) {
            mostrarNotificacion('Función de edición en desarrollo', 'info');
        function eliminarMetodoPago(id) {
            if (confirm('¿Estás seguro de eliminar este método de pago?')) {
                metodosPago = metodosPago.filter(m => m.id != id);
                mostrarMetodosPago();
                mostrarNotificacion('Método de pago eliminado', 'success');
        // ===== FUNCIONES PARA HISTORIAL =====
        function cargarHistorial() {
            const container = document.getElementById('listaHistorial');
                    <p>Cargando historial de pedidos...</p>
}
            fetch('api/get_order_history.php')
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        historial = data.historial || [];
                        console.log('Historial cargado:', historial.length, 'pedidos');
                        mostrarHistorial();
                        throw new Error(data.message || 'Error al obtener el historial');
                    console.error('Error al cargar historial:', error);
                    // Mostrar mensaje de error en lugar de datos simulados
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <h3>Error al cargar el historial</h3>
                            <p>No se pudo obtener tu historial de pedidos en este momento.</p>
                            <button class="btn btn-primary" onclick="cargarHistorial()">
                                <i class="fas fa-sync-alt me-2"></i>Reintentar
        // Función eliminada - ahora usamos datos reales de la API
        // function generarHistorialSimulado() { ... }
        function mostrarHistorial() {
            if (!historial || historial.length === 0) {
                        <i class="fas fa-history"></i>
                        <h3>No tienes pedidos aún</h3>
                        <p>Cuando realices tu primer pedido aparecerá aquí</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-utensils me-2"></i>Explorar restaurantes
                        </a>
            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Pedido</th><th>Fecha</th><th>Negocio</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
            historial.forEach(pedido => {
                const estadoBadge = getEstadoBadge(pedido.estado);
                const fechaFormateada = pedido.fecha_formateada || formatearFecha(pedido.fecha);
}
                const estadoTexto = pedido.estado_texto || traducirEstado(pedido.estado);
                const totalFormateado = pedido.total_formateado || `$${pedido.total.toFixed(2)}`;
                    <tr>
                        <td><strong>#${pedido.id}</strong></td>
                        <td><small class="text-muted">${fechaFormateada}</small></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>
                                    <div class="fw-semibold">${pedido.negocio || 'Negocio no disponible'}</div>
                        </td>
                        <td><strong>${totalFormateado}</strong></td>
                        <td><span class="badge ${estadoBadge}">${estadoTexto}</span></td>
                            <button class="btn btn-sm btn-outline-primary" onclick="verDetallePedido(${pedido.id})" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            ${pedido.estado === 'entregado' ? `
                                <button class="btn btn-sm btn-outline-success ms-1" onclick="volverPedir(${pedido.id})" title="Volver a pedir">
                                    <i class="fas fa-redo"></i>
                            ` : ''}
                    </tr>
            html += '</tbody></table></div>';
        function getEstadoBadge(estado) {
            switch(estado) {
                case 'entregado':
                    return 'bg-success';
                case 'en_camino':
                case 'listo':
                    return 'bg-info';
                case 'preparando':
                case 'confirmado':
                    return 'bg-warning';
                case 'cancelado':
                    return 'bg-danger';
                case 'pendiente':
                    return 'bg-secondary';
        function traducirEstado(estado) {
            const estados = {
                'pendiente': 'Pendiente',
                'confirmado': 'Confirmado',
                'preparando': 'Preparando',
                'listo': 'Listo',
                'en_camino': 'En camino',
                'entregado': 'Entregado',
                'cancelado': 'Cancelado'
            return estados[estado] || estado.charAt(0).toUpperCase() + estado.slice(1);
        function verDetallePedido(id) {
            // Redirigir a la página de detalle del pedido
            window.location.href = `order-tracking.php?pedido=${id}`;
        function volverPedir(id) {
            if (confirm('¿Quieres volver a pedir los mismos productos de este pedido?')) {
                // Enviar request para agregar los productos al carrito
                fetch('api/reorder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        pedido_id: id
                    })
                .then(response => response.json())
                        mostrarNotificacion('Productos agregados al carrito', 'success');
                        // Opcional: redirigir al carrito
                        setTimeout(() => {
                            window.location.href = 'carrito.php';
                        }, 1500);
                        mostrarNotificacion(data.message || 'Error al agregar productos al carrito', 'danger');
                    console.error('Error:', error);
                    mostrarNotificacion('Error al procesar la solicitud', 'danger');
        // ===== FUNCIONES PARA FAVORITOS =====
        function cargarFavoritos() {
            const container = document.getElementById('listaFavoritos');
                    <p>Cargando favoritos...</p>
            fetch('api/get_favorites.php')
                        favoritos = data.favoritos || [];
                        mostrarFavoritos();
                    console.log('API no disponible, mostrando datos simulados:', error);
                    favoritos = generarFavoritosSimulados();
                    mostrarFavoritos();
        function generarFavoritosSimulados() {
            return [
                {
                    id: 1,
                    id_favorito: 1,
                    nombre: 'Pizzería Don Luigi',
                    descripcion: 'Auténtica pizza italiana',
                    imagen: 'assets/img/pizzeria.jpg'
                },
                    id: 2,
                    id_favorito: 2,
                    nombre: 'Sushi Yamato',
                    descripcion: 'Sushi fresco y delicioso',
                    imagen: 'assets/img/sushi.jpg'
            ];
        function mostrarFavoritos() {
            if (!favoritos || favoritos.length === 0) {
                        <i class="fas fa-heart"></i>
                        <h3>No tienes favoritos</h3>
                        <p>Guarda tus restaurantes favoritos para encontrarlos fácilmente</p>
                            <i class="fas fa-search me-2"></i>Buscar restaurantes
            let html = '<div class="row">';
            favoritos.forEach(favorito => {
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
}
                                    <div class="flex-grow-1">
                                        <h6 class="card-title">${favorito.nombre || 'Restaurante sin nombre'}</h6>
                                        <p class="card-text text-muted">${favorito.descripcion || 'Sin descripción'}</p>
                                    </div>
                                    <div class="ms-2">
                                        <button class="btn btn-sm btn-outline-primary me-2" onclick="verNegocio(${favorito.id})" title="Ver restaurante">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarFavorito(${favorito.id_favorito || favorito.id})" title="Eliminar de favoritos">
                                            <i class="fas fa-heart-broken"></i>
            html += '</div>';
        function verNegocio(id) {
            mostrarNotificacion(`Viendo negocio #${id}`, 'info');
            // window.location.href = `negocio.php?id=${id}`;
        function eliminarFavorito(id) {
            if (confirm('¿Eliminar de favoritos?')) {
                favoritos = favoritos.filter(f => (f.id_favorito || f.id) != id);
                mostrarFavoritos();
                mostrarNotificacion('Eliminado de favoritos', 'success');
        // ===== FUNCIONES PARA REFERIDOS =====
        function usarBeneficioReferido() {
            if (confirm('¿Estás seguro de que quieres usar tu beneficio de referido ahora? Se aplicará a tu próximo pedido.')) {
                fetch('api/use_referral_benefit.php', {
                        action: 'use_referral_benefit'
                        mostrarNotificacion('Beneficio de referido activado. Se aplicará en tu próximo pedido.', 'success');
                        setTimeout(() => location.reload(), 2000);
                        mostrarNotificacion('Error: ' + (data.message || 'No se pudo activar el beneficio'), 'danger');
                    mostrarNotificacion('Error al activar beneficio de referido', 'danger');
        function usarDescuentoFidelidad() {
            if (confirm('¿Estás seguro de que quieres usar tu descuento de fidelidad ahora? Se aplicará a tu próximo pedido.')) {
                        action: 'use_fidelity_discount'
                        mostrarNotificacion('Descuento de fidelidad activado. Se aplicará en tu próximo pedido.', 'success');
                        mostrarNotificacion('Error: ' + (data.message || 'No se pudo activar el descuento'), 'danger');
                    mostrarNotificacion('Error al activar descuento de fidelidad', 'danger');
        function copiarEnlace() {
            const input = document.getElementById('enlaceReferido');
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    mostrarNotificacion('Enlace copiado al portapapeles', 'success');
                } else {
                    // Fallback para navegadores modernos
                    navigator.clipboard.writeText(input.value).then(() => {
                        mostrarNotificacion('Enlace copiado al portapapeles', 'success');
                    }).catch(() => {
                        mostrarNotificacion('No se pudo copiar el enlace', 'warning');
                    });
            } catch (err) {
                mostrarNotificacion('Error al copiar el enlace', 'danger');
                console.error('Error:', err);
        function compartirEnlace() {
            const enlace = document.getElementById('enlaceReferido')?.value;
            if (!enlace) return;
            if (navigator.share) {
                navigator.share({
                    title: 'Únete a QuickBite',
                    text: '¡Únete a QuickBite con mi enlace de referido y obtén beneficios!',
}
                    url: enlace
                }).catch(err => {
                    console.log('Error sharing:', err);
                    copiarEnlace();
                copiarEnlace();
                mostrarNotificacion('Enlace copiado. Compártelo manualmente.', 'info');
        function toggleHistorialReferidos() {
            const historial = document.getElementById('historialReferidos');
            if (!historial) return;
            if (historial.style.display === 'none') {
                historial.style.display = 'block';
                cargarHistorialReferidos();
                historial.style.display = 'none';
        function cargarHistorialReferidos() {
            if (!tbody) return;
            fetch('api/get_referral_history.php')
                    if (data.success && data.referidos && data.referidos.length > 0) {
                        let html = '';
                        data.referidos.forEach(referido => {
                            const estado = referido.activo ? 'Activo' : 'Pendiente';
                            const badgeClass = referido.activo ? 'bg-success' : 'bg-warning';
                            const compras = referido.compras_realizadas || 0;
                            const membresia = referido.tiene_membresia ? 'Sí' : 'No';
                            
}
                            html += `
                                    <td>${referido.nombre_usuario || 'Usuario #' + referido.id}</td>
                                    <td>${referido.fecha_referido || 'N/A'}</td>
                                    <td>${compras} compras / Membresía: ${membresia}</td>
                                    <td><span class="badge ${badgeClass}">${estado}</span></td>
                            `;
                        });
                        tbody.innerHTML = html;
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay referidos aún</td></tr>';
                    console.log('Error cargando historial de referidos:', error);
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay referidos registrados</td></tr>';
        // ===== FUNCIÓN PARA MOSTRAR NOTIFICACIONES =====
        function mostrarNotificacion(mensaje, tipo = 'info') {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            const tipoClass = tipo === 'error' ? 'danger' : tipo;
            notification.className = `alert alert-${tipoClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${getIconoNotificacion(tipo)} me-2"></i>
                    <span>${mensaje}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            document.body.appendChild(notification);
            // Auto-eliminar después de 4 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
            }, 4000);
        function getIconoNotificacion(tipo) {
                case 'success': return 'check-circle';
                case 'danger': 
                case 'error': return 'exclamation-circle';
                case 'warning': return 'exclamation-triangle';
                case 'info': return 'info-circle';
                default: return 'info-circle';
        // ===== FUNCIONES ADICIONALES PARA VALIDACIÓN =====
        function validarFormularioDireccion() {
            const nombre = document.getElementById('nombreDireccion').value.trim();
            const calle = document.getElementById('calle').value.trim();
            const numero = document.getElementById('numero').value.trim();
            const colonia = document.getElementById('colonia').value.trim();
            const ciudad = document.getElementById('ciudad').value.trim();
            const codigoPostal = document.getElementById('codigoPostal').value.trim();
            const estado = document.getElementById('estado').value.trim();
            if (!nombre || !calle || !numero || !colonia || !ciudad || !codigoPostal || !estado) {
                mostrarNotificacion('Por favor completa todos los campos obligatorios', 'warning');
}
                return false;
            if (!/^\d{5}$/.test(codigoPostal)) {
                mostrarNotificacion('El código postal debe tener 5 dígitos', 'warning');
            return true;
        function validarFormularioPago() {
            const tipoPago = document.getElementById('tipoPago').value;
            if (!tipoPago) {
                mostrarNotificacion('Por favor selecciona un tipo de pago', 'warning');
            if (tipoPago === 'tarjeta') {
                const nombreTarjeta = document.getElementById('nombreTarjeta').value.trim();
                const numeroTarjeta = document.getElementById('numeroTarjeta').value.trim();
                if (!nombreTarjeta || !numeroTarjeta) {
                    mostrarNotificacion('Por favor completa los datos de la tarjeta', 'warning');
}
                    return false;
                if (!/^\d{16}$/.test(numeroTarjeta)) {
                    mostrarNotificacion('El número de tarjeta debe tener 16 dígitos', 'warning');
            if (tipoPago === 'paypal') {
                const correoPaypal = document.getElementById('correoPaypal').value.trim();
                if (!correoPaypal) {
                    mostrarNotificacion('Por favor ingresa tu correo de PayPal', 'warning');
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correoPaypal)) {
                    mostrarNotificacion('Por favor ingresa un correo válido', 'warning');
        // Sobrescribir las funciones de guardado para incluir validación
        const guardarDireccionOriginal = guardarDireccion;
        guardarDireccion = function() {
            if (validarFormularioDireccion()) {
}
                guardarDireccionOriginal();
        };
        const guardarMetodoPagoOriginal = guardarMetodoPago;
        guardarMetodoPago = function() {
            if (validarFormularioPago()) {
                guardarMetodoPagoOriginal();
        // ===== FUNCIONES DE UTILIDAD =====
        function formatearFecha(fecha) {
            const date = new Date(fecha);
            return date.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
        function formatearMonto(monto) {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(monto);
        // ===== MANEJO DE ERRORES GLOBALES =====
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Error no manejado:', event.reason);
            mostrarNotificacion('Ocurrió un error inesperado', 'danger');
        window.addEventListener('error', function(event) {
            console.error('Error de JavaScript:', event.error);
            mostrarNotificacion('Error en la aplicación', 'danger');
        // ===== INICIALIZACIÓN ADICIONAL =====
            // Configurar tooltips si están disponibles
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
            // Configurar máscaras de entrada
            configurarMascaras();
        function configurarMascaras() {
            // Máscara para código postal (solo números, máximo 5)
            const codigoPostalInput = document.getElementById('codigoPostal');
            if (codigoPostalInput) {
                codigoPostalInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 5);
            // Máscara para número de tarjeta (solo números, máximo 16)
            const numeroTarjetaInput = document.getElementById('numeroTarjeta');
            if (numeroTarjetaInput) {
                numeroTarjetaInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 16);
    </script>
</body>
</html>
}
