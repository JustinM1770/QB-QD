<?php
// DASHBOARD REPARTIDOR - VERSIÓN MINIMALISTA Y PROFESIONAL
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Constante de Mapbox Token (seguridad)
define('MAPBOX_TOKEN', getenv('MAPBOX_TOKEN') ?: '');

require_once '../config/database.php';
require_once '../models/Usuario.php'; 
require_once '../models/Negocio.php';   

// Verificar si el usuario está logueado y es un repartidor
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['tipo_usuario'] !== 'repartidor') {
    header("Location: login.php");
    exit;
}

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Validar y sanitizar id_repartidor
$id_repartidor = filter_var($_SESSION['id_usuario'] ?? 0, FILTER_VALIDATE_INT);
if ($id_repartidor === false || $id_repartidor <= 0) {
    error_log("ID de repartidor inválido en sesión: " . ($_SESSION['id_usuario'] ?? 'null'));
    header("Location: login.php");
    exit;
}
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Repartidor', ENT_QUOTES, 'UTF-8');

// ==============================================================================
// NOTA: El estado "En Línea" ahora se controla automáticamente por WebSocket
// El repartidor estará "En Línea" SOLO cuando esté conectado al WebSocket
// Si se desconecta del WebSocket, automáticamente aparecerá como "Desconectado"
// No hay switch manual, el estado es en tiempo real según la conexión
// ==============================================================================

// Obtener estadísticas básicas
$total_entregas = 0;
$ganancias_hoy = 0;

try {
    // Total de entregas
    $query = "SELECT COUNT(*) as total FROM pedidos WHERE id_repartidor = ? AND id_estado = 6";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_repartidor);
    $stmt->execute();
    $total_entregas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Ganancias de hoy
    $query = "SELECT COALESCE(SUM(ganancia), 0) as ganancias_hoy 
              FROM pedidos 
              WHERE id_repartidor = ? 
              AND DATE(fecha_creacion) = CURDATE() 
              AND id_estado = 6";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_repartidor);
    $stmt->execute();
    $ganancias_hoy = $stmt->fetch(PDO::FETCH_ASSOC)['ganancias_hoy'] ?? 0;

} catch (PDOException $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════
// ✅ SISTEMA DE BONIFICACIÓN Y GAMIFICACIÓN
// ═══════════════════════════════════════════════════════════════
$gamificacion = [
    'nivel_actual' => null,
    'proximo_nivel' => null,
    'progreso' => 0,
    'entregas_faltantes' => 0,
    'bono_disponible' => 0,
    'niveles' => []
];

try {
    // Obtener datos del repartidor con nivel
    $stmt_rep = $db->prepare("SELECT r.*, n.nombre as nivel_nombre, n.emoji, n.recompensa, n.entregas_requeridas
                              FROM repartidores r
                              LEFT JOIN niveles_repartidor n ON r.id_nivel = n.id_nivel
                              WHERE r.id_usuario = ?");
    $stmt_rep->execute([$id_repartidor]);
    $rep_data = $stmt_rep->fetch(PDO::FETCH_ASSOC);

    if ($rep_data) {
        $gamificacion['nivel_actual'] = [
            'nombre' => $rep_data['nivel_nombre'] ?? 'Novato',
            'emoji' => $rep_data['emoji'] ?? '🚴',
            'recompensa' => $rep_data['recompensa'] ?? 0,
            'entregas_requeridas' => $rep_data['entregas_requeridas'] ?? 0
        ];

        // Obtener todos los niveles para mostrar el camino
        $stmt_niveles = $db->query("SELECT * FROM niveles_repartidor WHERE activo = 1 ORDER BY orden ASC");
        $gamificacion['niveles'] = $stmt_niveles->fetchAll(PDO::FETCH_ASSOC);

        // Encontrar el próximo nivel
        $nivel_actual_encontrado = false;
        foreach ($gamificacion['niveles'] as $nivel) {
            if ($nivel_actual_encontrado) {
                $gamificacion['proximo_nivel'] = $nivel;
                break;
            }
            if ($nivel['id_nivel'] == ($rep_data['id_nivel'] ?? 1)) {
                $nivel_actual_encontrado = true;
            }
        }

        // Calcular progreso
        if ($gamificacion['proximo_nivel']) {
            $entregas_actuales = $rep_data['total_entregas'] ?? $total_entregas;
            $entregas_nivel_actual = $gamificacion['nivel_actual']['entregas_requeridas'];
            $entregas_proximo = $gamificacion['proximo_nivel']['entregas_requeridas'];

            $gamificacion['entregas_faltantes'] = max(0, $entregas_proximo - $entregas_actuales);
            $rango = $entregas_proximo - $entregas_nivel_actual;
            $avance = $entregas_actuales - $entregas_nivel_actual;
            $gamificacion['progreso'] = $rango > 0 ? min(100, max(0, ($avance / $rango) * 100)) : 0;
            $gamificacion['bono_disponible'] = $gamificacion['proximo_nivel']['recompensa'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error en gamificación: " . $e->getMessage());
}

// Obtener pedidos activos con información completa para navegación
$pedidos_activos = [];
try {
    $query = "SELECT p.id_pedido, 
                     p.monto_total,
                     COALESCE(n.nombre, 'Restaurante') as nombre_negocio,
                     CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) as nombre_cliente,
                     p.id_estado,
                     COALESCE(p.ganancia, 35.00) as comision_repartidor,
                     COALESCE(p.propina, 0.00) as propina_estimada,
                     p.fecha_creacion,
                     p.instrucciones_especiales,
                     
                     -- Datos del negocio
                     CONCAT(COALESCE(n.calle, ''), ' ', COALESCE(n.numero, ''), ', ', COALESCE(n.colonia, '')) as direccion_negocio,
                     n.telefono as telefono_negocio,
                     n.logo as imagen_negocio,
                     COALESCE(n.latitud, 19.4326) as lat_negocio,
                     COALESCE(n.longitud, -99.1332) as lng_negocio,
                     
                     -- Datos del cliente/dirección
                     CONCAT(COALESCE(d.calle, ''), ' ', COALESCE(d.numero, ''), ', ', COALESCE(d.colonia, ''), ', ', COALESCE(d.ciudad, '')) as direccion_cliente,
                     u.telefono as telefono_cliente,
                     COALESCE(d.latitud, 19.4226) as lat_cliente,
                     COALESCE(d.longitud, -99.1432) as lng_cliente
                     
              FROM pedidos p
              LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
              LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
              LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
              WHERE p.id_repartidor = ? 
              AND p.id_estado IN (1, 4, 5)
              ORDER BY p.fecha_creacion DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_repartidor);
    $stmt->execute();
    $pedidos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error al obtener pedidos activos: " . $e->getMessage());
    $mensaje_error = "Error al cargar pedidos activos: " . $e->getMessage();
}

// Obtener pedidos disponibles (siempre disponibles cuando el WebSocket esté conectado)
$pedidos_disponibles = [];
try {
    $query = "SELECT p.id_pedido, 
                     p.monto_total,
                     COALESCE(n.nombre, 'Restaurante') as nombre_negocio,
                     CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) as nombre_cliente,
                     CONCAT(COALESCE(d.calle, ''), ' ', COALESCE(d.numero, ''), ', ', COALESCE(d.colonia, ''), ', ', COALESCE(d.ciudad, '')) as direccion_entrega,
                     p.fecha_creacion,
                     1500 as distancia
              FROM pedidos p
              LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
              LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
              LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
              WHERE p.id_repartidor IS NULL 
              AND p.id_estado = 4
              AND p.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
              ORDER BY p.fecha_creacion ASC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pedidos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pedidos_raw as $pedido) {
        $comision_estimada = 35.00;
        
        $pedidos_disponibles[] = [
            'id_pedido' => $pedido['id_pedido'],
            'restaurante' => $pedido['nombre_negocio'],
            'cliente' => $pedido['nombre_cliente'],
            'direccion_entrega' => $pedido['direccion_entrega'],
            'monto_total' => $pedido['monto_total'],
            'distancia' => $pedido['distancia'],
            'comision_estimada' => $comision_estimada,
            'tiempo_pedido' => $pedido['fecha_creacion']
        ];
    }
    
} catch (Exception $e) {
    error_log("Error al obtener pedidos disponibles: " . $e->getMessage());
}

// Función para obtener estado del pedido en texto legible
function obtenerEstadoTexto($id_estado) {
    $estados = [
        1 => 'Asignado',
        2 => 'Confirmado',
        3 => 'Preparando',
        4 => 'Listo para entrega',
        5 => 'En camino',
        6 => 'Entregado'
    ];
    return $estados[$id_estado] ?? 'Estado desconocido';
}

// Función para obtener clase CSS del estado
function obtenerClaseEstado($id_estado) {
    $clases = [
        1 => 'estado-asignado',
        2 => 'estado-confirmado',
        3 => 'estado-preparando',
        4 => 'estado-listo',
        5 => 'estado-en_camino',
        6 => 'estado-entregado'
    ];
    return $clases[$id_estado] ?? 'estado-asignado';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickBite - Dashboard Repartidor</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />
    
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary: #000000;
        --primary-light: #fafafa;
        --accent: #0066ff;
        --success: #00c853;
        --warning: #ffa726;
        --danger: #ff5252;
        --gray-50: #fafafa;
        --gray-100: #f5f5f5;
        --gray-200: #eeeeee;
        --gray-300: #e0e0e0;
        --gray-400: #bdbdbd;
        --gray-500: #9e9e9e;
        --gray-600: #757575;
        --gray-700: #616161;
        --gray-800: #424242;
        --gray-900: #212121;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --radius: 12px;
        --radius-lg: 16px;
        --radius-xl: 24px;
        --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--gray-50);
        color: var(--gray-900);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Layout Principal */
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background: var(--gray-50);
    }

    /* Sidebar - Oculto en móviles */
    .sidebar {
        display: none;
    }

    /* Main Content - Mobile First */
    .main-content {
        flex: 1;
        width: 100%;
        padding: 1rem;
        padding-bottom: 5rem;
        min-height: 100vh;
    }

    /* Dashboard Header */
    .dashboard-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .dashboard-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        letter-spacing: -0.02em;
        margin-bottom: 0.25rem;
    }

    .dashboard-header p {
        font-size: 0.875rem;
        color: var(--gray-500);
        font-weight: 400;
    }

    /* Status Switch - Rediseñado Minimalista */
    .status-switch {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: var(--white);
        padding: 1rem 1.25rem;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        transition: var(--transition);
    }

    .status-switch.online {
        background: linear-gradient(135deg, rgba(0, 200, 83, 0.05) 0%, rgba(0, 200, 83, 0.02) 100%);
        border-color: rgba(0, 200, 83, 0.2);
    }

    .status-switch.offline {
        background: var(--gray-50);
        border-color: var(--gray-200);
    }

    .connection-status {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .status-indicator {
        position: relative;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--gray-300);
        transition: var(--transition);
    }

    .status-indicator.online {
        background: var(--success);
        box-shadow: 0 0 0 3px rgba(0, 200, 83, 0.2);
    }

    .status-indicator.online::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--success);
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
        50% {
            opacity: 0;
            transform: translate(-50%, -50%) scale(2);
        }
    }

    .status-text {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .status-text strong {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-900);
    }

    .status-text small {
        font-size: 0.75rem;
        color: var(--gray-500);
        font-weight: 400;
    }

    .status-text.online strong {
        color: var(--success);
    }

    .status-text.offline strong {
        color: var(--gray-600);
    }

    /* Alerts */
    .alert {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-radius: var(--radius);
        margin-bottom: 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .alert-success {
        background: rgba(0, 200, 83, 0.08);
        color: #1b5e20;
        border: 1px solid rgba(0, 200, 83, 0.2);
    }

    .alert-danger {
        background: rgba(255, 82, 82, 0.08);
        color: #c62828;
        border: 1px solid rgba(255, 82, 82, 0.2);
    }

    .alert i {
        font-size: 1.125rem;
        flex-shrink: 0;
    }

    /* Stats Grid - Mobile Optimizado */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.875rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 1rem;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-card:active {
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .stat-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        margin-bottom: 0.75rem;
    }

    .stat-icon.primary { background: var(--gray-100); color: var(--primary); }
    .stat-icon.success { background: var(--gray-100); color: var(--success); }
    .stat-icon.info { background: var(--gray-100); color: var(--primary); }
    .stat-icon.warning { background: var(--gray-100); color: var(--gray-700); }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1;
        margin-bottom: 0.25rem;
        letter-spacing: -0.02em;
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--gray-500);
        line-height: 1.2;
    }

    /* Row Layout */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -0.5rem;
    }

    .col-md-6 {
        width: 100%;
        padding: 0 0.5rem;
    }

    .mb-4 {
        margin-bottom: 1rem;
    }

    .h-100 {
        height: 100%;
    }

    /* Card Dashboard */
    .card-dashboard {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .card-dashboard > div:first-child {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--white);
    }

    .card-dashboard h5 {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
        letter-spacing: -0.01em;
        text-transform: uppercase;
        font-size: 0.75rem;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 100px;
        line-height: 1;
    }

    .bg-primary {
        background: var(--accent);
        color: var(--white);
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 8px;
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: var(--transition);
        line-height: 1;
        white-space: nowrap;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }

    .btn-outline-primary {
        background: var(--white);
        color: var(--primary);
        border: 1px solid var(--gray-300);
    }

    .btn-outline-primary:active {
        background: var(--gray-50);
        border-color: var(--primary);
        transform: scale(0.98);
    }

    .btn-success {
        background: var(--primary);
        color: var(--white);
    }

    .btn-success:active {
        background: var(--gray-900);
        transform: scale(0.98);
    }

    .btn-aceptar {
        flex-shrink: 0;
    }

    .ms-2 {
        margin-left: 0.5rem;
    }

    /* List Group */
    .list-group {
        display: flex;
        flex-direction: column;
    }

    .list-group-item {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid var(--gray-100);
        transition: var(--transition);
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .pedido-activo-item {
        cursor: pointer;
        position: relative;
    }

    .pedido-activo-item:active {
        background: var(--gray-50);
    }

    .pedido-activo-item::after {
        content: '›';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.5rem;
        color: var(--gray-300);
        transition: var(--transition);
    }

    .list-group-item h6 {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 0.625rem;
    }

    .list-group-item small {
        font-size: 0.8125rem;
        color: var(--gray-600);
        display: block;
        margin-bottom: 0.25rem;
        line-height: 1.5;
    }

    /* Utilities */
    .d-flex {
        display: flex;
    }

    .justify-content-between {
        justify-content: space-between;
    }

    .align-items-center {
        align-items: center;
    }

    .align-items-start {
        align-items: flex-start;
    }

    .flex-grow-1 {
        flex-grow: 1;
    }

    .d-block {
        display: block;
    }

    .mb-0 {
        margin-bottom: 0;
    }

    .mb-1 {
        margin-bottom: 0.25rem;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .mt-2 {
        margin-top: 0.5rem;
    }

    .mt-3 {
        margin-top: 0.75rem;
    }

    .me-1 {
        margin-right: 0.25rem;
    }

    .me-2 {
        margin-right: 0.5rem;
    }

    .me-3 {
        margin-right: 0.75rem;
    }

    .text-muted {
        color: var(--gray-600);
    }

    .text-success {
        color: var(--success);
    }

    .text-info {
        color: #00bcd4;
    }

    .text-white {
        color: var(--white);
    }

    .gap-2 {
        gap: 0.5rem;
    }

    .d-grid {
        display: grid;
    }

    /* Estado Pedido */
    .estado-pedido {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .estado-asignado {
        background: rgba(255, 167, 38, 0.1);
        color: var(--warning);
    }

    .estado-confirmado {
        background: rgba(0, 102, 255, 0.1);
        color: var(--accent);
    }

    .estado-preparando {
        background: rgba(156, 39, 176, 0.1);
        color: #9c27b0;
    }

    .estado-listo {
        background: rgba(0, 200, 83, 0.1);
        color: var(--success);
    }

    .estado-en_camino {
        background: rgba(3, 169, 244, 0.1);
        color: #03a9f4;
    }

    .estado-entregado {
        background: rgba(76, 175, 80, 0.1);
        color: #4caf50;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 2.5rem 1.5rem;
        color: var(--gray-500);
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        opacity: 0.4;
        color: var(--gray-400);
    }

    .empty-state h4 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--gray-700);
    }

    .empty-state p {
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--gray-500);
    }

    /* Navigation Modal - MINIMALISTA */
    .navigation-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        background: var(--white);
        z-index: 1000;
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
    }

    .navigation-modal.show {
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    @keyframes slideUp {
        from {
            transform: translateY(100%);
        }
        to {
            transform: translateY(0);
        }
    }

    /* Header minimalista */
    .nav-header-minimal {
        background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);
        color: var(--white);
        padding: 1rem 1.25rem;
        position: relative;
        z-index: 10;
    }

    .btn-close-minimal {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .btn-close-minimal:active {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(0.95);
    }

    /* Mapa full screen */
    .map-container-minimal {
        flex: 1;
        position: relative;
        background: var(--gray-100);
        min-height: 400px;
        height: 100%;
    }
    
    .map-container-minimal #navigationMap {
        width: 100%;
        height: 100%;
    }

    /* Info flotante sobre el mapa */
    .map-overlay-info {
        position: absolute;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 0.5rem;
        z-index: 5;
    }

    .distance-badge,
    .time-badge {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 0.5rem 0.875rem;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--gray-900);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .distance-badge i {
        color: var(--primary);
        font-size: 0.75rem;
    }

    .time-badge i {
        color: #0066ff;
        font-size: 0.75rem;
    }

    /* Tarjeta de destino minimalista */
    .destination-minimal {
        background: var(--white);
        padding: 1.5rem;
        border-top: 1px solid var(--gray-100);
        position: relative;
    }

    .destination-avatar {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        overflow: hidden;
        background: var(--gray-50);
        position: relative;
        flex-shrink: 0;
    }

    .destination-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }

    .destination-avatar img[src]:not([src=""]) {
        display: block;
    }

    .avatar-fallback {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: white;
        font-size: 1.25rem;
    }

    .destination-avatar img[src]:not([src=""]) ~ .avatar-fallback {
        display: none;
    }

    .btn-call-minimal {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--primary);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        transition: var(--transition);
        flex-shrink: 0;
    }

    .btn-call-minimal:active {
        transform: scale(0.95);
        background: var(--gray-900);
    }

    /* Botones de navegación externa */
    .btn-nav-external {
        flex: 1;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 0.875rem 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-700);
        transition: var(--transition);
    }

    .btn-nav-external:active {
        background: var(--gray-100);
        transform: scale(0.98);
    }

    .btn-nav-external i {
        font-size: 1rem;
    }

    /* Botón de acción principal */
    .btn-action-minimal {
        width: 100%;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 1rem;
        font-size: 0.9375rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }

    .btn-action-minimal:active:not(:disabled) {
        transform: scale(0.98);
        background: var(--gray-900);
    }

    .btn-action-minimal:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-lg {
        padding: 1rem 1.5rem;
        font-size: 1rem;
    }

    .btn-warning {
        background: var(--warning);
        color: var(--white);
    }

    .btn-warning:active {
        background: #f57c00;
        transform: scale(0.98);
    }

    .btn-secondary {
        background: var(--gray-300);
        color: var(--gray-700);
    }

    .btn-secondary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-outline-info {
        background: transparent;
        color: #00bcd4;
        border: 1px solid #00bcd4;
    }

    .btn-outline-info:active {
        background: #00bcd4;
        color: var(--white);
    }

    .flex-fill {
        flex: 1;
    }

    /* Bottom Navigation */
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--white);
        border-top: 1px solid var(--gray-100);
        display: flex;
        justify-content: space-around;
        padding: 0.5rem 0;
        z-index: 100;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }

    .bottom-nav .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        padding: 0.5rem 0.75rem;
        color: var(--gray-500);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
        transition: var(--transition);
        border-radius: var(--radius);
        min-width: 64px;
    }

    .bottom-nav .nav-item:active,
    .bottom-nav .nav-item.active {
        background: var(--primary-light);
        color: var(--primary);
        transform: scale(0.95);
    }

    .bottom-nav .nav-item i {
        font-size: 1.25rem;
    }

    .bottom-nav .nav-item span {
        font-size: 0.6875rem;
    }

    /* Spinner */
    .fa-spinner.fa-spin {
        animation: fa-spin 1s infinite linear;
    }

    @keyframes fa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Desktop Styles (solo para referencia) */
    @media (min-width: 768px) {
        .sidebar {
            display: block;
            width: 240px;
            background: var(--white);
            border-right: 1px solid var(--gray-100);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
            overflow-y: auto;
        }

        .main-content {
            margin-left: 240px;
            padding: 2rem;
        }

        .dashboard-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5rem;
        }

        .dashboard-header h1 {
            font-size: 2rem;
        }

        .stats-container {
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.75rem;
        }

        .stat-value {
            font-size: 2.25rem;
        }

        .col-md-6 {
            width: 50%;
        }

        .card-dashboard > div:first-child {
            padding: 1.5rem 1.75rem;
        }

        .list-group-item {
            padding: 1.5rem 1.75rem;
        }

        .bottom-nav {
            display: none;
        }

        .main-content {
            padding-bottom: 2rem;
        }
    }

    /* Large Desktop */
    @media (min-width: 1200px) {
        .main-content {
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 280px;
            padding-right: 2rem;
        }
    }

    /* Extra Small Devices */
    @media (max-width: 360px) {
        .dashboard-header h1 {
            font-size: 1.25rem;
        }

        .stats-container {
            gap: 0.75rem;
        }

        .stat-card {
            padding: 1rem;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .stat-label {
            font-size: 0.75rem;
        }

        .bottom-nav .nav-item {
            padding: 0.375rem 0.5rem;
            min-width: 56px;
        }

        .bottom-nav .nav-item i {
            font-size: 1.125rem;
        }
    }

    /* Landscape Mode */
    @media (max-height: 600px) and (orientation: landscape) {
        .map-container {
            height: 40vh;
        }

        .empty-state {
            padding: 1.5rem 1rem;
        }
    }


        

    /* Touch Improvements */
    @media (hover: none) {
        .btn:hover {
            transform: none;
        }

        .stat-card:hover {
            transform: none;
        }
    }

    /* Safe Areas para iPhone */
    @supports (padding-top: env(safe-area-inset-top)) {
        .nav-header {
            padding-top: calc(1rem + env(safe-area-inset-top));
        }

        .bottom-nav {
            padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
        }
    }

    /* Dashboard Sections */
    .dashboard-section {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dashboard-section.active {
        display: block;
    }

    /* =============================================
       Modal de Foto de Entrega
    ============================================= */
    .foto-entrega-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100vh;
        background: rgba(0, 0, 0, 0.95);
        z-index: 1100;
        overflow: hidden;
        animation: fadeInModal 0.3s ease-out;
    }

    .foto-entrega-modal.show {
        display: flex;
        flex-direction: column;
    }

    @keyframes fadeInModal {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .foto-modal-header {
        background: linear-gradient(135deg, #ff6b35 0%, #e55a2b 100%);
        color: white;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .foto-modal-header h5 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
    }

    .foto-modal-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        position: relative;
    }

    .camera-container {
        width: 100%;
        max-width: 500px;
        aspect-ratio: 4/3;
        background: #1a1a1a;
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        margin-bottom: 1rem;
    }

    .camera-container video,
    .camera-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .camera-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.5);
    }

    .camera-overlay i {
        font-size: 4rem;
        color: rgba(255, 255, 255, 0.3);
    }

    .foto-preview {
        display: none;
    }

    .foto-preview.show {
        display: block;
    }

    .camera-hint {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.875rem;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .camera-hint i {
        margin-right: 0.5rem;
        color: #ff6b35;
    }

    .foto-modal-actions {
        display: flex;
        gap: 1rem;
        width: 100%;
        max-width: 500px;
        padding: 0 1rem;
    }

    .btn-foto-action {
        flex: 1;
        padding: 1rem 1.5rem;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-capturar {
        background: #ff6b35;
        color: white;
    }

    .btn-capturar:active {
        transform: scale(0.95);
        background: #e55a2b;
    }

    .btn-galeria {
        background: rgba(255, 255, 255, 0.15);
        color: white;
    }

    .btn-galeria:active {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-reintentar {
        background: rgba(255, 255, 255, 0.15);
        color: white;
    }

    .btn-confirmar-foto {
        background: #28a745;
        color: white;
    }

    .btn-confirmar-foto:active {
        transform: scale(0.95);
        background: #1e7e34;
    }

    .btn-cancelar-foto {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 1.25rem;
        cursor: pointer;
    }

    .foto-upload-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
    }

    .foto-upload-progress .progress-bar {
        height: 100%;
        background: #ff6b35;
        width: 0%;
        transition: width 0.3s ease;
    }

    /* Input file oculto */
    #fotoInput {
        display: none;
    }

    /* Indicador de ubicación */
    .location-indicator {
        position: absolute;
        bottom: 1rem;
        left: 1rem;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .location-indicator.success i {
        color: #28a745;
    }

    .location-indicator.error i {
        color: #dc3545;
    }
</style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar d-none d-md-block">
            <div class="sidebar-menu" style="padding: 20px 0;">
                <a href="#" class="menu-item active" style="padding: 12px 25px; display: flex; align-items: center; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-tachometer-alt" style="margin-right: 15px;"></i>
                    Dashboard
                </a>
                <a href="#" class="menu-item" style="padding: 12px 25px; display: flex; align-items: center; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-motorcycle" style="margin-right: 15px;"></i>
                    Pedidos
                </a>
                <a href="#" class="menu-item" style="padding: 12px 25px; display: flex; align-items: center; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-history" style="margin-right: 15px;"></i>
                    Historial
                </a>
                <a href="../admin/repartidor/wallet.php" class="menu-item" style="padding: 12px 25px; display: flex; align-items: center; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-dollar-sign" style="margin-right: 15px;"></i>
                    Ganancias
                </a>
                <a href="../logout.php" class="menu-item" style="padding: 12px 25px; display: flex; align-items: center; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-sign-out-alt" style="margin-right: 15px;"></i>
                    Cerrar Sesión
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <h1>QuickBite</h1>
                    <p>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?></p>
                </div>
                
                <div class="status-switch offline" id="connectionStatus">
                    <div class="connection-status">
                        <div class="status-indicator" id="statusIndicator"></div>
                        <div class="status-text offline" id="statusText">
                            <strong>Desconectado</strong>
                            <small>Conectando al sistema...</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($mensaje_error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Botón de diagnóstico temporal -->
                
            
            <!-- Sección Inicio -->
            <div id="seccion-inicio" class="dashboard-section active">
            <!-- Estadísticas -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_entregas; ?></div>
                    <div class="stat-label">Entregas Totales</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($ganancias_hoy, 2); ?></div>
                    <div class="stat-label">Ganancias Hoy</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="stat-value">0</div>
                    <div class="stat-label">Tiempo Promedio</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value">0.0</div>
                    <div class="stat-label">Calificación</div>
                </div>
            </div>

            <!-- ✅ WIDGET DE BONIFICACIÓN / CAMINO AL ÉXITO -->
                        <?php if (!empty($gamificacion['niveles']) && !empty($gamificacion['nivel_actual'])): ?>
            <div class="bonus-journey-widget" id="bonus-widget">
                <div class="bonus-header" onclick="toggleBonusWidget()">
                    <div class="bonus-gift-icon <?php echo $gamificacion['progreso'] >= 100 ? 'pulse-animation' : ''; ?>">
                        <i class="fas fa-gift"></i>
                        <?php if ($gamificacion['bono_disponible'] > 0): ?>
                        <span class="bonus-badge">$<?php echo number_format((float)$gamificacion['bono_disponible'], 0); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bonus-summary">
                        <div class="current-level">
                            <span class="level-emoji"><?php echo $gamificacion['nivel_actual']['emoji'] ?? '🚴'; ?></span>
                            <span class="level-name"><?php echo htmlspecialchars($gamificacion['nivel_actual']['nombre'] ?? 'Novato'); ?></span>
                        </div>
                        <?php if ($gamificacion['proximo_nivel']): ?>
                        <div class="progress-text">
                            <i class="fas fa-route"></i>
                            <?php echo $gamificacion['entregas_faltantes']; ?> entregas para <?php echo $gamificacion['proximo_nivel']['emoji']; ?> <?php echo htmlspecialchars($gamificacion['proximo_nivel']['nombre']); ?>
                        </div>
                        <?php else: ?>
                        <div class="progress-text champion">
                            <i class="fas fa-crown"></i> ¡Eres un campeón!
                        </div>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>

                <div class="bonus-details" id="bonus-details">
                    <!-- Barra de progreso -->
                    <div class="progress-journey">
                        <div class="progress-bar-journey">
                            <div class="progress-fill" style="width: <?php echo $gamificacion['progreso']; ?>%"></div>
                            <div class="progress-runner" style="left: <?php echo max(5, min(95, $gamificacion['progreso'])); ?>%">
                                🏃
                            </div>
                        </div>
                        <div class="progress-percentage"><?php echo round($gamificacion['progreso']); ?>%</div>
                    </div>

                    <!-- Camino de niveles -->
                    <div class="levels-path">
                        <?php
                        $nivel_actual_id = $gamificacion['nivel_actual']['nombre'] ?? '';
                        foreach ($gamificacion['niveles'] as $i => $nivel):
                            $is_current = ($nivel['nombre'] == $nivel_actual_id);
                            $is_completed = ($nivel['entregas_requeridas'] ?? 0) <= $total_entregas;
                            $is_next = ($gamificacion['proximo_nivel'] && $nivel['id_nivel'] == $gamificacion['proximo_nivel']['id_nivel']);
                        ?>
                        <div class="level-node <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : ($is_next ? 'next' : '')); ?>">
                            <div class="node-icon">
                                <?php if ($is_completed && !$is_current): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <?php echo $nivel['emoji'] ?? '🎯'; ?>
                                <?php endif; ?>
                            </div>
                            <div class="node-info">
                                <span class="node-name"><?php echo htmlspecialchars($nivel['nombre']); ?></span>
                                <span class="node-req"><?php echo $nivel['entregas_requeridas']; ?> entregas</span>
                                <?php if ($nivel['recompensa'] > 0): ?>
                                <span class="node-reward">💰 $<?php echo number_format((float)$nivel['recompensa'], 0); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($i < count($gamificacion['niveles']) - 1): ?>
                            <div class="node-connector <?php echo $is_completed ? 'completed' : ''; ?>"></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <!-- Meta final -->
                        <div class="level-node final">
                            <div class="node-icon trophy">
                                🏆
                            </div>
                            <div class="node-info">
                                <span class="node-name">¡Campeón!</span>
                                <span class="node-req">Meta final</span>
                            </div>
                        </div>
                    </div>

                    <a href="repartidor/gamificacion.php" class="view-rewards-btn">
                        <i class="fas fa-trophy"></i> Ver mis recompensas
                    </a>
                </div>
            </div>

            <style>
                .bonus-journey-widget {
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    border-radius: 16px;
                    margin-bottom: 20px;
                    overflow: hidden;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                }

                .bonus-header {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    padding: 20px;
                    cursor: pointer;
                    transition: background 0.3s;
                }

                .bonus-header:hover {
                    background: rgba(255, 255, 255, 0.05);
                }

                .bonus-gift-icon {
                    position: relative;
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 28px;
                    color: white;
                    flex-shrink: 0;
                }

                .bonus-gift-icon.pulse-animation {
                    animation: pulse-gift 1.5s ease-in-out infinite;
                }

                @keyframes pulse-gift {
                    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
                    50% { transform: scale(1.05); box-shadow: 0 0 20px 10px rgba(245, 158, 11, 0.2); }
                }

                .bonus-badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #22c55e;
                    color: white;
                    font-size: 11px;
                    font-weight: 700;
                    padding: 3px 8px;
                    border-radius: 10px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                }

                .bonus-summary {
                    flex: 1;
                }

                .current-level {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 5px;
                }

                .level-emoji {
                    font-size: 24px;
                }

                .level-name {
                    color: white;
                    font-weight: 700;
                    font-size: 1.1rem;
                }

                .progress-text {
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 0.9rem;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .progress-text.champion {
                    color: #ffd700;
                }

                .toggle-icon {
                    color: rgba(255, 255, 255, 0.5);
                    transition: transform 0.3s;
                }

                .bonus-journey-widget.expanded .toggle-icon {
                    transform: rotate(180deg);
                }

                .bonus-details {
                    display: none;
                    padding: 0 20px 20px;
                }

                .bonus-journey-widget.expanded .bonus-details {
                    display: block;
                }

                .progress-journey {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 25px;
                }

                .progress-bar-journey {
                    flex: 1;
                    height: 12px;
                    background: rgba(255, 255, 255, 0.1);
                    border-radius: 6px;
                    position: relative;
                    overflow: visible;
                }

                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #22c55e, #4ade80);
                    border-radius: 6px;
                    transition: width 0.5s ease;
                }

                .progress-runner {
                    position: absolute;
                    top: -8px;
                    transform: translateX(-50%);
                    font-size: 20px;
                    transition: left 0.5s ease;
                }

                .progress-percentage {
                    color: #4ade80;
                    font-weight: 700;
                    font-size: 1rem;
                    min-width: 50px;
                    text-align: right;
                }

                .levels-path {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    justify-content: center;
                    margin-bottom: 20px;
                }

                .level-node {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    position: relative;
                    min-width: 80px;
                }

                .node-icon {
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.1);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    margin-bottom: 8px;
                    border: 2px solid rgba(255, 255, 255, 0.2);
                    transition: all 0.3s;
                }

                .level-node.completed .node-icon {
                    background: #22c55e;
                    border-color: #22c55e;
                    color: white;
                }

                .level-node.current .node-icon {
                    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
                    border-color: #3b82f6;
                    box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
                    animation: current-pulse 2s ease-in-out infinite;
                }

                @keyframes current-pulse {
                    0%, 100% { box-shadow: 0 0 10px rgba(59, 130, 246, 0.3); }
                    50% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.6); }
                }

                .level-node.next .node-icon {
                    border-color: #f59e0b;
                    border-style: dashed;
                }

                .level-node.final .node-icon.trophy {
                    background: linear-gradient(135deg, #ffd700, #ff8c00);
                    border-color: #ffd700;
                }

                .node-info {
                    text-align: center;
                }

                .node-name {
                    display: block;
                    color: white;
                    font-size: 0.75rem;
                    font-weight: 600;
                }

                .node-req {
                    display: block;
                    color: rgba(255, 255, 255, 0.5);
                    font-size: 0.65rem;
                }

                .node-reward {
                    display: block;
                    color: #4ade80;
                    font-size: 0.7rem;
                    font-weight: 600;
                }

                .view-rewards-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    width: 100%;
                    padding: 12px;
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    color: white;
                    border-radius: 10px;
                    text-decoration: none;
                    font-weight: 600;
                    transition: all 0.3s;
                }

                .view-rewards-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 20px rgba(245, 158, 11, 0.3);
                    color: white;
                }

                @media (max-width: 576px) {
                    .bonus-header {
                        padding: 15px;
                    }

                    .bonus-gift-icon {
                        width: 50px;
                        height: 50px;
                        font-size: 22px;
                    }

                    .level-name {
                        font-size: 1rem;
                    }

                    .levels-path {
                        gap: 5px;
                    }

                    .level-node {
                        min-width: 60px;
                    }

                    .node-icon {
                        width: 40px;
                        height: 40px;
                        font-size: 18px;
                    }
                }
            </style>

            <script>
                function toggleBonusWidget() {
                    const widget = document.getElementById('bonus-widget');
                    widget.classList.toggle('expanded');
                }

                // Auto-expandir si está cerca del siguiente nivel
                document.addEventListener('DOMContentLoaded', function() {
                    const progreso = <?php echo $gamificacion['progreso']; ?>;
                    if (progreso >= 80) {
                        document.getElementById('bonus-widget').classList.add('expanded');
                    }
                });
            </script>
            <?php endif; ?>

            <!-- Pedidos Activos y Disponibles -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card-dashboard h-100">
                        <div style="font-size: 1.2rem; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <h5>Pedidos Activos</h5>
                            <span class="badge bg-primary"><?php echo count($pedidos_activos); ?></span>
                        </div>
                        
                        <?php if (count($pedidos_activos) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($pedidos_activos as $pedido): 
                                    // Sanitizar JSON para evitar XSS
                                    $pedido_json = json_encode($pedido, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                    if ($pedido_json === false) {
                                        error_log("Error encoding pedido JSON: " . json_last_error_msg());
                                        $pedido_json = '{}';
                                    }
                                ?>
                                    <div class="list-group-item pedido-activo-item" 
                                         onclick="iniciarNavegacion(<?php echo $pedido_json; ?>)"
                                         data-pedido-id="<?php echo (int)$pedido['id_pedido']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">Pedido #<?php echo (int)$pedido['id_pedido']; ?></h6>
                                                    <span class="estado-pedido <?php echo htmlspecialchars(obtenerClaseEstado($pedido['id_estado']), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars(obtenerEstadoTexto($pedido['id_estado']), ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted d-block">Restaurante: <?php echo htmlspecialchars($pedido['nombre_negocio'] ?? 'Restaurante', ENT_QUOTES, 'UTF-8'); ?></small>
                                                <small class="text-muted d-block">Cliente: <?php echo htmlspecialchars($pedido['nombre_cliente'] ?? 'Cliente', ENT_QUOTES, 'UTF-8'); ?></small>
                                                <small class="text-muted d-block">Dirección: <?php echo htmlspecialchars($pedido['direccion_cliente'] ?: 'Dirección del cliente', ENT_QUOTES, 'UTF-8'); ?></small>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <small class="text-success">
                                                        <i class="fas fa-dollar-sign me-1"></i>
                                                        Ganancia: $<?php echo number_format($pedido['comision_repartidor'], 2); ?>
                                                        <?php if ($pedido['propina_estimada'] > 0): ?>
                                                            + $<?php echo number_format($pedido['propina_estimada'], 2); ?> propina
                                                        <?php endif; ?>
                                                    </small>
                                                    <small class="text-info">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php 
                                                        $tiempo_transcurrido = round((time() - strtotime($pedido['fecha_creacion'])) / 60);
                                                        echo $tiempo_transcurrido; ?> min
                                                    </small>
                                                </div>
                                            </div>
                                            <i class="fas fa-chevron-right text-muted ms-2"></i>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-motorcycle"></i>
                                <h4>No tienes pedidos activos</h4>
                                <p>Cuando aceptes un pedido, aparecerá aquí para que puedas gestionarlo.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card-dashboard h-100">
                        <div style="font-size: 1.2rem; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <h5>Pedidos Disponibles</h5>
                            <span class="badge bg-primary"><?php echo count($pedidos_disponibles); ?></span>
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="location.reload()">
                                <i class="fas fa-sync"></i>
                            </button>
                        </div>
                        
                        <?php if (count($pedidos_disponibles) > 0): ?>
                            <div class="list-group" id="pedidosDisponibles">
                                <?php foreach ($pedidos_disponibles as $pedido): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div class="flex-grow-1">
                                                    <h6>Pedido #<?php echo $pedido['id_pedido']; ?></h6>
                                                    <small class="text-muted d-block">Restaurante: <?php echo htmlspecialchars($pedido['restaurante']); ?></small>
                                                    <small class="text-muted d-block">Cliente: <?php echo htmlspecialchars($pedido['cliente']); ?></small>
                                                    <small class="text-muted d-block">Dirección: <?php echo htmlspecialchars($pedido['direccion_entrega']); ?></small>
                                                    <small class="text-muted d-block">Distancia: <?php echo round($pedido['distancia'] / 1000, 1); ?> km</small>
                                                    <small class="text-success">
                                                        <i class="fas fa-dollar-sign me-1"></i>
                                                        Ganancia estimada: $<?php echo number_format($pedido['comision_estimada'], 2); ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-success btn-aceptar align-self-start" 
                                                        onclick="aceptarPedido(<?php echo $pedido['id_pedido']; ?>)">
                                                    Aceptar
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h4>No hay pedidos disponibles</h4>
                                <p>En este momento no hay pedidos disponibles en tu zona. Mantente en línea para recibir notificaciones.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
            <!-- Fin Sección Inicio -->
            
            <!-- Sección Pedidos -->
            <div id="seccion-pedidos" class="dashboard-section" style="display: none;">
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--primary);">
                    <i class="fas fa-list me-2"></i>Mis Pedidos
                </h2>
                <div class="card-dashboard">
                    <div>
                        <h5>Historial de Entregas</h5>
                    </div>
                    <div class="list-group">
                        <?php if ($total_entregas > 0): ?>
                            <div class="list-group-item text-center">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem; margin: 2rem 0 1rem;"></i>
                                <h4>Has completado <?php echo $total_entregas; ?> entregas</h4>
                                <p class="text-muted">¡Excelente trabajo!</p>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h4>Sin entregas aún</h4>
                                <p>Aquí aparecerá tu historial de entregas completadas.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Fin Sección Pedidos -->
            
            <!-- Sección Estadísticas -->
            <div id="seccion-estadisticas" class="dashboard-section" style="display: none;">
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--primary);">
                    <i class="fas fa-chart-line me-2"></i>Estadísticas
                </h2>
                
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_entregas; ?></div>
                        <div class="stat-label">Entregas Totales</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-value">$<?php echo number_format($ganancias_hoy, 2); ?></div>
                        <div class="stat-label">Ganancias Hoy</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-value">0</div>
                        <div class="stat-label">Días Activos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-value">0.0</div>
                        <div class="stat-label">Calificación</div>
                    </div>
                </div>
                
                <div class="card-dashboard" style="margin-top: 1.5rem;">
                    <div>
                        <h5>Resumen de la Semana</h5>
                    </div>
                    <div style="padding: 2rem;">
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h4>Estadísticas en desarrollo</h4>
                            <p>Pronto podrás ver gráficos detallados de tu rendimiento.</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Fin Sección Estadísticas -->
            
            <!-- Sección Perfil -->
            <div id="seccion-perfil" class="dashboard-section" style="display: none;">
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--primary);">
                    <i class="fas fa-user me-2"></i>Mi Perfil
                </h2>
                
                <div class="card-dashboard">
                    <div>
                        <h5>Información Personal</h5>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div class="d-flex align-items-center mb-3" style="padding-bottom: 1rem; border-bottom: 1px solid var(--gray-100);">
                            <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--gray-200); display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                <i class="fas fa-user" style="font-size: 2rem; color: var(--gray-500);"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; font-size: 1.25rem;"><?php echo htmlspecialchars($nombre_usuario); ?></h4>
                                <p style="margin: 0; color: var(--gray-500);">Repartidor</p>
                            </div>
                        </div>
                        
                        <div style="display: grid; gap: 1rem;">
                            <div style="padding: 1rem; background: var(--gray-50); border-radius: var(--radius);">
                                <small style="color: var(--gray-600); display: block; margin-bottom: 0.25rem;">Entregas Totales</small>
                                <strong style="font-size: 1.25rem; color: var(--primary);"><?php echo $total_entregas; ?></strong>
                            </div>
                            
                            <div style="padding: 1rem; background: var(--gray-50); border-radius: var(--radius);">
                                <small style="color: var(--gray-600); display: block; margin-bottom: 0.25rem;">Ganancias del Día</small>
                                <strong style="font-size: 1.25rem; color: var(--success);">$<?php echo number_format($ganancias_hoy, 2); ?></strong>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--gray-100);">
                            <a href="../logout.php" class="btn btn-outline-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                <i class="fas fa-sign-out-alt"></i>
                                Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Fin Sección Perfil -->
        </div>
    </div>

    <!-- Modal de Navegación - MINIMALISTA -->
    <div class="navigation-modal" id="navigationModal">
        <!-- Header Minimalista -->
        <div class="nav-header-minimal">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div>
                    <h5 class="mb-0 fw-bold" id="navTitle" style="font-size: 1.125rem;">Ir al Restaurante</h5>
                    <small class="text-white-50" id="navSubtitle" style="font-size: 0.8125rem;">#0000</small>
                </div>
                <button class="btn-close-minimal" onclick="cerrarNavegacion()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Mapa Full -->
        <div class="map-container-minimal">
            <div id="navigationMap" style="width: 100%; height: 100%;"></div>
            
            <!-- Info flotante sobre el mapa -->
            <div class="map-overlay-info">
                <div class="distance-badge">
                    <i class="fas fa-route"></i>
                    <span id="distanceText">...</span>
                </div>
                <div class="time-badge">
                    <i class="fas fa-clock"></i>
                    <span id="timeText">...</span>
                </div>
            </div>
        </div>

        <!-- Tarjeta de destino minimalista -->
        <div class="destination-minimal">
            <div class="d-flex align-items-center mb-4" style="gap: 1rem;">
                <div id="destinationIcon" class="destination-avatar">
                    <img id="destinationImage" src="" alt="Destino">
                    <div id="destinationIconFallback" class="avatar-fallback">
                        <i class="fas fa-store"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-semibold" id="destinationName" style="font-size: 0.9375rem;">Cargando...</h6>
                    <p class="mb-0 text-muted" id="destinationAddress" style="font-size: 0.8125rem; line-height: 1.4;">Cargando...</p>
                </div>
                <button class="btn-call-minimal" id="callButton">
                    <i class="fas fa-phone"></i>
                </button>
            </div>

            <!-- Botones de navegación externa minimalistas -->
            <div class="d-flex gap-3 mb-4">
                <button class="btn-nav-external" onclick="abrirGoogleMaps()">
                    <i class="fab fa-google"></i>
                    <span>Maps</span>
                </button>
                <button class="btn-nav-external" onclick="abrirWaze()">
                    <i class="fas fa-location-arrow"></i>
                    <span>Waze</span>
                </button>
            </div>

            <!-- Botón de acción principal -->
            <div id="navigationActions">
                <button class="btn-action-minimal" disabled>
                    <i class="fas fa-spinner fa-spin me-2"></i>Cargando...
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Foto de Entrega -->
    <div class="foto-entrega-modal" id="fotoEntregaModal">
        <div class="foto-modal-header">
            <h5><i class="fas fa-camera me-2"></i>Foto de Entrega</h5>
            <button class="btn-close-minimal" onclick="cerrarModalFoto()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="foto-modal-body">
            <div class="camera-container" id="cameraContainer">
                <!-- Vista de cámara -->
                <video id="cameraVideo" autoplay playsinline></video>
                <!-- Vista de preview -->
                <img id="fotoPreview" class="foto-preview" alt="Preview de foto">
                <!-- Overlay cuando no hay cámara -->
                <div class="camera-overlay" id="cameraOverlay">
                    <i class="fas fa-camera"></i>
                </div>
                <!-- Indicador de ubicación -->
                <div class="location-indicator" id="locationIndicator">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Obteniendo ubicación...</span>
                </div>
            </div>

            <p class="camera-hint">
                <i class="fas fa-info-circle"></i>
                Toma una foto del pedido entregado como comprobante
            </p>

            <!-- Botones de captura (vista inicial) -->
            <div class="foto-modal-actions" id="captureActions">
                <button class="btn-foto-action btn-galeria" onclick="seleccionarDeGaleria()">
                    <i class="fas fa-images"></i>
                    Galería
                </button>
                <button class="btn-foto-action btn-capturar" onclick="capturarFoto()">
                    <i class="fas fa-camera"></i>
                    Capturar
                </button>
            </div>

            <!-- Botones de confirmación (después de capturar) -->
            <div class="foto-modal-actions" id="confirmActions" style="display: none;">
                <button class="btn-foto-action btn-reintentar" onclick="reintentarFoto()">
                    <i class="fas fa-redo"></i>
                    Repetir
                </button>
                <button class="btn-foto-action btn-confirmar-foto" onclick="confirmarFotoEntrega()">
                    <i class="fas fa-check"></i>
                    Confirmar
                </button>
            </div>

            <!-- Barra de progreso -->
            <div class="foto-upload-progress" id="uploadProgress" style="display: none;">
                <div class="progress-bar" id="progressBar"></div>
            </div>
        </div>

        <!-- Input oculto para galería -->
        <input type="file" id="fotoInput" accept="image/*" onchange="procesarFotoGaleria(event)">
    </div>

    <!-- Bottom Navigation para móviles -->
    <div class="bottom-nav d-md-none">
        <a href="#inicio" class="nav-item active">
            <i class="fas fa-home"></i>
            <span>Inicio</span>
        </a>
        <a href="#pedidos" class="nav-item">
            <i class="fas fa-list"></i>
            <span>Pedidos</span>
        </a>
        <a href="repartidor/wallet.php" class="nav-item wallet-nav">
            <i class="fas fa-wallet"></i>
            <span>Cartera</span>
        </a>
        <a href="#estadisticas" class="nav-item">
            <i class="fas fa-chart-line"></i>
            <span>Stats</span>
        </a>
        <a href="#perfil" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
    <script src="../js/courier-tracking.js"></script>
<script>
    // DASHBOARD REPARTIDOR - VERSIÓN LIMPIA
console.log('🚀 Iniciando dashboard de repartidor v2.0');

// Helper para sanitizar texto y prevenir XSS
function sanitizeHTML(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

// ===========================
// VARIABLES GLOBALES UNIFICADAS
// ===========================
window.dashboardState = {
    currentOrder: null,
    navigationMap: null,
    currentStep: 'negocio',
    repartidorPosition: null,
    watchId: null,
    trackingActive: false,
    locationWatchId: null,
    repartidorMarker: null,
    routeAnimation: null
};

// Token de Mapbox (desde constante PHP)
mapboxgl.accessToken = '<?php echo MAPBOX_TOKEN; ?>';

// ===========================
// FUNCIÓN DE DIAGNÓSTICO
// ===========================
function probarConexion() {
    console.log('🔧 Iniciando diagnóstico de conexión...');
    
    const baseUrl = window.location.protocol + '//' + window.location.host;
    const testUrl = baseUrl + '/admin/test_session.php';
    
    const formData = new FormData();
    formData.append('test', 'diagnostico');
    
    fetch(testUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('📊 Status diagnóstico:', response.status);
        console.log('📊 Headers:', [...response.headers.entries()]);
        return response.json();
    })
    .then(data => {
        console.log('✅ Diagnóstico exitoso:', data);
    })
    .catch(error => {
        console.error('❌ Error en diagnóstico:', error);
    });
}

// Hacer la función disponible globalmente para pruebas manuales
window.probarConexion = probarConexion;

// ===========================
// FUNCIÓN PRINCIPAL DE NAVEGACIÓN
// ===========================
function iniciarNavegacion(pedido) {
    console.log('🗺️ Iniciando navegación para pedido:', pedido);
    
    window.dashboardState.currentOrder = pedido;
    
    // Determinar paso actual
    if (pedido.id_estado == 4) {
        window.dashboardState.currentStep = 'negocio';
    } else if (pedido.id_estado == 5) {
        window.dashboardState.currentStep = 'cliente';
    } else {
        window.dashboardState.currentStep = 'negocio';
    }
    
    mostrarModalNavegacion();
    
    // El mapa se inicializa y la ubicación se obtiene en el evento 'load' del mapa
    inicializarMapa();
    
    // Iniciar tracking GPS
    if (!window.dashboardState.trackingActive) {
        iniciarTrackingGPS(pedido.id_pedido);
    }
}

function mostrarModalNavegacion() {
    console.log('📱 Mostrando modal de navegación');
    
    const modal = document.getElementById('navigationModal');
    const title = document.getElementById('navTitle');
    const subtitle = document.getElementById('navSubtitle');
    
    if (!modal || !title || !subtitle) {
        console.error('❌ Elementos del modal no encontrados');
        return;
    }
    
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    title.textContent = isNegocio ? 'Ir al Restaurante' : 'Ir al Cliente';
    subtitle.textContent = `Pedido #${window.dashboardState.currentOrder.id_pedido}`;
    
    actualizarInfoDestino();
    actualizarBotonesAccion();
    
    // Usar la clase .show para activar flexbox
    modal.classList.add('show');
}

function actualizarInfoDestino() {
    const order = window.dashboardState.currentOrder;
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    
    console.log('📝 Actualizando info de destino:', { isNegocio, order });
    
    const elements = {
        icon: document.getElementById('destinationIcon'),
        image: document.getElementById('destinationImage'),
        iconFallback: document.getElementById('destinationIconFallback'),
        name: document.getElementById('destinationName'),
        address: document.getElementById('destinationAddress'),
        callButton: document.getElementById('callButton')
    };
    
    // Verificar que todos los elementos existen
    if (!Object.values(elements).every(el => el)) {
        console.error('❌ Elementos de información no encontrados:', elements);
        return;
    }
    
    if (isNegocio) {
        // Configurar imagen del negocio
        elements.icon.style.border = '3px solid #ffc107';
        
        if (order.imagen_negocio) {
            elements.image.src = order.imagen_negocio;
            elements.image.style.display = 'block';
            elements.iconFallback.style.display = 'none';
            
            // Manejar error de carga de imagen
            elements.image.onerror = function() {
                this.style.display = 'none';
                elements.iconFallback.style.display = 'flex';
                elements.iconFallback.innerHTML = '<i class="fas fa-store text-white"></i>';
                elements.iconFallback.style.background = '#ffc107';
            };
        } else {
            // Mostrar ícono por defecto si no hay imagen
            elements.image.style.display = 'none';
            elements.iconFallback.style.display = 'flex';
            elements.iconFallback.innerHTML = '<i class="fas fa-store text-white"></i>';
            elements.iconFallback.style.background = '#ffc107';
        }
        
        elements.name.textContent = order.nombre_negocio || 'Restaurante';
        elements.address.textContent = order.direccion_negocio || 'Dirección del restaurante';
        elements.callButton.onclick = () => {
            if (order.telefono_negocio) {
                window.open(`tel:${order.telefono_negocio}`, '_blank');
            } else {
                console.log('⚠️ No hay teléfono del negocio disponible');
            }
        };
    } else {
        // Para el cliente, mantener ícono
        elements.icon.style.border = '3px solid #28a745';
        elements.image.style.display = 'none';
        elements.iconFallback.style.display = 'flex';
        elements.iconFallback.innerHTML = '<i class="fas fa-home text-white"></i>';
        elements.iconFallback.style.background = '#28a745';
        
        elements.name.textContent = order.nombre_cliente || 'Cliente';
        elements.address.textContent = order.direccion_cliente || 'Dirección del cliente';
        elements.callButton.onclick = () => {
            if (order.telefono_cliente) {
                window.open(`tel:${order.telefono_cliente}`, '_blank');
            } else {
                console.log('⚠️ No hay teléfono del cliente disponible');
            }
        };
    }
    
    console.log('✅ Información de destino actualizada');
}

function actualizarBotonesAccion() {
    const container = document.getElementById('navigationActions');
    if (!container) {
        console.error('❌ Container de botones no encontrado');
        return;
    }
    
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    
    if (isNegocio) {
        container.innerHTML = `
            <button class="btn-action-minimal" onclick="manejarRecogida()" style="background: #ffa726;">
                <i class="fas fa-shopping-bag me-2"></i>
                He recogido el pedido
            </button>
        `;
    } else {
        container.innerHTML = `
            <button class="btn-action-minimal" onclick="manejarEntrega()" style="background: #00c853;">
                <i class="fas fa-check me-2"></i>
                He entregado el pedido
            </button>
        `;
    }
    
    console.log('✅ Botones actualizados para paso:', window.dashboardState.currentStep);
}

// ===========================
// FUNCIONES DE CAMBIO DE ESTADO
// ===========================
function manejarRecogida() {
    console.log('📦 INICIANDO PROCESO DE RECOGIDA');
    
    const order = window.dashboardState.currentOrder;
    if (!order) {
        console.error('❌ No hay pedido activo');
        mostrarError('No hay pedido activo para marcar como recogido');
        return;
    }
    
    console.log('📦 Pedido encontrado:', order.id_pedido);
    
    // CERRAR MODAL TEMPORALMENTE
    const navModal = document.getElementById('navigationModal');
    navModal.classList.remove('show');
    
    Swal.fire({
        title: '¿Confirmar recogida?',
        text: '¿Confirmas que has recogido el pedido del restaurante?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, he recogido el pedido',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        // REABRIR MODAL
        navModal.classList.add('show');
        
        console.log('🔔 SweetAlert resultado:', result);
        
        if (result.isConfirmed === true) {
            console.log('✅ Usuario confirmó recogida');
            ejecutarCambioEstado('recogido');
        } else {
            console.log('❌ Usuario canceló recogida');
        }
    });
}

function manejarEntrega() {
    console.log('📋 INICIANDO PROCESO DE ENTREGA');

    const order = window.dashboardState.currentOrder;
    if (!order) {
        console.error('❌ No hay pedido activo');
        mostrarError('No hay pedido activo para marcar como entregado');
        return;
    }

    console.log('📋 Pedido encontrado:', order.id_pedido);

    // CERRAR MODAL DE NAVEGACION
    const navModal = document.getElementById('navigationModal');
    navModal.classList.remove('show');

    // Mostrar opción: foto requerida o saltar
    Swal.fire({
        title: 'Foto de Entrega',
        html: `
            <p style="margin-bottom: 1rem; color: #666;">
                Toma una foto como comprobante de la entrega realizada.
            </p>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button id="btnTomarFoto" class="swal2-confirm swal2-styled" style="background: #ff6b35; margin: 0;">
                    <i class="fas fa-camera" style="margin-right: 8px;"></i>
                    Tomar Foto
                </button>
                <button id="btnSaltarFoto" class="swal2-cancel swal2-styled" style="background: #6c757d; margin: 0;">
                    Continuar sin foto
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            document.getElementById('btnTomarFoto').addEventListener('click', () => {
                Swal.close();
                abrirModalFoto();
            });
            document.getElementById('btnSaltarFoto').addEventListener('click', () => {
                Swal.close();
                confirmarEntregaSinFoto();
            });
        }
    });
}

function confirmarEntregaSinFoto() {
    const navModal = document.getElementById('navigationModal');

    Swal.fire({
        title: '¿Confirmar entrega?',
        text: '¿Confirmas que has entregado el pedido al cliente?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, he entregado el pedido',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (!result.isConfirmed) {
            navModal.classList.add('show');
        }

        console.log('🔔 SweetAlert resultado:', result);

        if (result.isConfirmed === true) {
            console.log('✅ Usuario confirmó entrega (sin foto)');
            ejecutarCambioEstado('entregado');
        } else {
            console.log('❌ Usuario canceló entrega');
        }
    });
}

function ejecutarCambioEstado(nuevoEstado, intentos = 0) {
    const MAX_INTENTOS = 3;
    const order = window.dashboardState.currentOrder;
    console.log(`🔄 EJECUTANDO CAMBIO DE ESTADO: ${nuevoEstado} para pedido: ${order.id_pedido} (intento ${intentos + 1}/${MAX_INTENTOS})`);
    
    // Mostrar loading inmediatamente
    mostrarBotonLoading(nuevoEstado);
    
    // Intentar primero con AJAX mejorado
    intentarCambioEstadoAJAX(nuevoEstado)
        .catch(error => {
            console.warn(`❌ Intento ${intentos + 1}/${MAX_INTENTOS} falló:`, error);
            
            if (intentos < MAX_INTENTOS - 1) {
                // Reintentar después de un delay (exponential backoff)
                const delay = Math.pow(2, intentos) * 1000;
                console.log(`🔄 Reintentando en ${delay}ms...`);
                
                setTimeout(() => {
                    ejecutarCambioEstado(nuevoEstado, intentos + 1);
                }, delay);
            } else {
                // Todos los intentos fallaron
                console.error('❌ Todos los intentos fallaron');
                
                // Guardar en localStorage para sincronizar después
                const pendingUpdate = {
                    order_id: order.id_pedido,
                    nuevo_estado: nuevoEstado,
                    timestamp: Date.now()
                };
                
                const pending = JSON.parse(localStorage.getItem('pending_updates') || '[]');
                pending.push(pendingUpdate);
                localStorage.setItem('pending_updates', JSON.stringify(pending));
                
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin conexión',
                    text: 'El cambio se guardará cuando recuperes la conexión',
                    confirmButtonText: 'Entendido'
                });
                
                // Continuar en modo offline
                manejarCambioExitoso(nuevoEstado);
            }
        });
}

function intentarCambioEstadoAJAX(nuevoEstado) {
    const order = window.dashboardState.currentOrder;
    
    // Preparar datos para el formulario
    const formData = new FormData();
    formData.append('id_pedido', order.id_pedido);
    formData.append('estado', nuevoEstado);
    
    console.log('📤 Enviando petición HTTP a actualizar_estado_pedido.php...');
    console.log('📦 Datos:', {
        id_pedido: order.id_pedido,
        estado: nuevoEstado
    });
    
    // Construir URL absoluta para evitar problemas con HTTP/HTTPS
    const baseUrl = window.location.protocol + '//' + window.location.host;
    const updateUrl = baseUrl + '/admin/actualizar_estado_pedido.php';
    
    console.log('🌐 URL construida:', updateUrl);
    console.log('🔗 Protocolo actual:', window.location.protocol);
    
    return fetch(updateUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('📥 Respuesta HTTP recibida:', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok
        });
        
        if (!response.ok) {
            // Intentar leer el cuerpo para debug
            return response.text().then(text => {
                console.error('Response body:', text.substring(0, 200));
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            });
        }
        
        const contentType = response.headers.get('content-type');
        console.log('📋 Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('❌ Non-JSON response:', text.substring(0, 200));
                throw new Error('Respuesta no es JSON válido');
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('📊 Datos JSON recibidos:', data);
        
        // Validar estructura mínima
        if (typeof data !== 'object' || data === null) {
            throw new Error('Respuesta JSON inválida');
        }
        
        if (data.success) {
            console.log('✅ Estado actualizado exitosamente en el servidor');
            manejarCambioExitoso(nuevoEstado);
        } else {
            console.error('❌ Error reportado por el servidor:', data);
            mostrarError('Error del servidor: ' + (data.message || data.error || 'Error desconocido'));
            restaurarBotones();
        }
    })
    .catch(error => {
        console.error('❌ Error en la petición:', error);
        console.error('❌ Tipo de error:', error.name);
        console.error('❌ Mensaje completo:', error.message);
        console.error('❌ Stack trace:', error.stack);
        
        // Re-lanzar el error para que sea manejado por el catch principal
        throw error;
    });
}

function intentarCambioEstadoForm(nuevoEstado) {
    console.log('📋 Intentando cambio de estado con formulario HTML...');
    
    const order = window.dashboardState.currentOrder;
    
    return new Promise((resolve, reject) => {
        // Crear formulario oculto
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/actualizar_estado_pedido.php';
        form.style.display = 'none';
        
        // Agregar campos
        const idPedidoInput = document.createElement('input');
        idPedidoInput.type = 'hidden';
        idPedidoInput.name = 'id_pedido';
        idPedidoInput.value = order.id_pedido;
        
        const estadoInput = document.createElement('input');
        estadoInput.type = 'hidden';
        estadoInput.name = 'estado';
        estadoInput.value = nuevoEstado;
        
        const ajaxInput = document.createElement('input');
        ajaxInput.type = 'hidden';
        ajaxInput.name = 'ajax_fallback';
        ajaxInput.value = '1';
        
        form.appendChild(idPedidoInput);
        form.appendChild(estadoInput);
        form.appendChild(ajaxInput);
        
        document.body.appendChild(form);
        
        // Usar iframe oculto para capturar respuesta
        const iframe = document.createElement('iframe');
        iframe.name = 'estado_response';
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
        
        form.target = 'estado_response';
        
        // Manejar respuesta del iframe
        iframe.onload = function() {
            try {
                const response = iframe.contentDocument.body.textContent;
                const data = JSON.parse(response);
                
                if (data.success) {
                    console.log('✅ Estado actualizado con formulario');
                    manejarCambioExitoso(nuevoEstado);
                    resolve(data);
                } else {
                    console.error('❌ Error en formulario:', data);
                    mostrarError('Error: ' + (data.message || 'Error desconocido'));
                    restaurarBotones();
                    reject(new Error(data.message || 'Error desconocido'));
                }
            } catch (e) {
                console.error('❌ Error procesando respuesta del formulario:', e);
                mostrarError('Error procesando respuesta del servidor');
                restaurarBotones();
                reject(e);
            } finally {
                // Limpiar elementos
                document.body.removeChild(form);
                document.body.removeChild(iframe);
            }
        };
        
        // Enviar formulario
        form.submit();
    });
}

function manejarCambioExitoso(nuevoEstado) {
    console.log(`✅ MANEJANDO CAMBIO EXITOSO: ${nuevoEstado}`);
    
    if (nuevoEstado === 'recogido') {
        setTimeout(() => {
            console.log('🔄 Cambiando a navegación hacia cliente...');
            
            // Cambiar paso
            window.dashboardState.currentStep = 'cliente';
            
            // Actualizar interfaz
            const navTitle = document.getElementById('navTitle');
            if (navTitle) {
                navTitle.textContent = 'Ir al Cliente';
            }
            
            actualizarInfoDestino();
            actualizarBotonesAccion();
            
            // Reinicializar mapa
            inicializarMapa();
            obtenerUbicacionRepartidor();
            
            // Mostrar notificación
            Swal.fire({
                icon: 'success',
                title: 'Pedido recogido',
                text: '¡Ahora dirígete al cliente!',
                timer: 3000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
            
            console.log('✅ Transición a cliente completada');
        }, 1000);
        
    } else if (nuevoEstado === 'entregado') {
        console.log('🛑 Deteniendo tracking y finalizando...');
        detenerTrackingGPS();
        
        setTimeout(() => {
            Swal.fire({
                icon: 'success',
                title: '¡Pedido entregado!',
                text: 'Has completado la entrega exitosamente. ¡Excelente trabajo!',
                timer: 4000,
                showConfirmButton: false
            }).then(() => {
                cerrarNavegacion();
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            });
        }, 1000);
    }
}

function mostrarBotonLoading(estado) {
    const container = document.getElementById('navigationActions');
    if (!container) return;
    
    const textoEstado = estado === 'recogido' ? 'recogido' : 'entregado';
    container.innerHTML = `
        <button class="btn-action-minimal" disabled style="background: var(--gray-400);">
            <i class="fas fa-spinner fa-spin me-2"></i>
            Procesando...
        </button>
    `;
    
    console.log('⏳ Botón de loading mostrado para:', estado);
}

function restaurarBotones() {
    console.log('🔄 Restaurando botones originales');
    actualizarBotonesAccion();
}

function mostrarError(mensaje) {
    console.error('❌ Mostrando error al usuario:', mensaje);
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonText: 'Entendido'
    });
}

// ===========================
// FUNCIONES DE MAPA
// ===========================
function crearMarcadorRepartidor(lat, lng) {
    console.log('🚚 Creando marcador personalizado del repartidor...');
    
    // Crear elemento personalizado para el marcador - SOLO LA IMAGEN
    const el = document.createElement('div');
    el.className = 'custom-delivery-marker';
    el.style.width = '32px';
    el.style.height = '32px';
    el.style.backgroundImage = 'url(/assets/icons/delivery.png)';
    el.style.backgroundSize = '32px 32px';
    el.style.backgroundRepeat = 'no-repeat';
    el.style.backgroundPosition = 'center';
    el.style.cursor = 'pointer';
    el.style.transition = 'all 0.3s ease';
    el.style.filter = 'drop-shadow(0 2px 4px rgba(0,0,0,0.3))';
    
    // Agregar animación de rotación suave cuando se mueve
    el.style.transformOrigin = 'center';
    
    // Agregar animación CSS si no existe
    if (!document.getElementById('delivery-marker-styles')) {
        const style = document.createElement('style');
        style.id = 'delivery-marker-styles';
        style.textContent = `
            .custom-delivery-marker {
                z-index: 1000;
            }
            .custom-delivery-marker:hover {
                transform: scale(1.2);
                filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4));
            }
            .custom-delivery-marker.moving {
                animation: delivery-bounce 0.6s ease-out;
            }
            @keyframes delivery-bounce {
                0% { transform: scale(1) translateY(0px); }
                50% { transform: scale(1.1) translateY(-3px); }
                100% { transform: scale(1) translateY(0px); }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Crear el marcador con el elemento personalizado
    const marker = new mapboxgl.Marker(el)
        .setLngLat([lng, lat])
        .addTo(window.dashboardState.navigationMap);
    
    console.log('✅ Marcador personalizado del repartidor creado');
    return marker;
}

// Función para iniciar la animación de la ruta tipo barra de carga
function iniciarAnimacionRuta(route) {
    console.log('🎬 Iniciando animación de ruta tipo barra de carga...');
    
    // IMPORTANTE: Limpiar animación anterior SIEMPRE
    if (window.dashboardState.routeAnimation) {
        clearInterval(window.dashboardState.routeAnimation);
        window.dashboardState.routeAnimation = null;
    }
    
    const coordinates = route.geometry.coordinates;
    const totalDuration = 10000; // 10 segundos para completar la animación
    const fps = 60; // Frames por segundo
    const totalFrames = (totalDuration / 1000) * fps;
    let currentFrame = 0;
    
    // Función para calcular progreso de la ruta
    function calculateProgressCoordinates(progress) {
        const targetIndex = Math.floor((coordinates.length - 1) * progress);
        const remainder = ((coordinates.length - 1) * progress) - targetIndex;
        
        if (targetIndex >= coordinates.length - 1) {
            return coordinates.slice(0, coordinates.length);
        }
        
        const progressCoordinates = coordinates.slice(0, targetIndex + 1);
        
        // Interpolar la posición final
        if (remainder > 0 && targetIndex < coordinates.length - 1) {
            const currentPoint = coordinates[targetIndex];
            const nextPoint = coordinates[targetIndex + 1];
            
            const interpolatedPoint = [
                currentPoint[0] + (nextPoint[0] - currentPoint[0]) * remainder,
                currentPoint[1] + (nextPoint[1] - currentPoint[1]) * remainder
            ];
            
            progressCoordinates.push(interpolatedPoint);
        }
        
        return progressCoordinates;
    }
    
    // Animación principal - SOLO LA LÍNEA
    window.dashboardState.routeAnimation = setInterval(() => {
        currentFrame++;
        const progress = currentFrame / totalFrames;
        
        if (progress >= 1) {
            // Completar animación
            clearInterval(window.dashboardState.routeAnimation);
            
            // Mostrar ruta completa
            const progressCoordinates = coordinates;
            
            window.dashboardState.navigationMap.getSource('route-progress').setData({
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'LineString',
                    coordinates: progressCoordinates
                }
            });
            
            console.log('✅ Animación de ruta completada');
            return;
        }
        
        // Calcular coordenadas de progreso
        const progressCoordinates = calculateProgressCoordinates(progress);
        
        // Actualizar SOLO la línea de progreso
        window.dashboardState.navigationMap.getSource('route-progress').setData({
            type: 'Feature',
            properties: {},
            geometry: {
                type: 'LineString',
                coordinates: progressCoordinates
            }
        });
        
    }, 1000 / fps);
    
    console.log('🎬 Animación de línea de ruta iniciada');
}

function actualizarMarcadorRepartidor(lat, lng) {
    console.log('📍 Actualizando posición del marcador del repartidor:', { lat, lng });
    
    if (window.dashboardState.repartidorMarker) {
        // Agregar clase de movimiento para animación
        const markerElement = window.dashboardState.repartidorMarker.getElement();
        if (markerElement) {
            markerElement.classList.add('moving');
            setTimeout(() => {
                markerElement.classList.remove('moving');
            }, 600);
        }
        
        // Animar el movimiento del marcador
        window.dashboardState.repartidorMarker.setLngLat([lng, lat]);
        console.log('✅ Marcador del repartidor actualizado');
    } else {
        console.log('⚠️ Marcador del repartidor no existe, creando nuevo...');
        window.dashboardState.repartidorMarker = crearMarcadorRepartidor(lat, lng);
    }
}

function inicializarMapa() {
    console.log('🗺️ Inicializando mapa...');
    
    // Limpiar animaciones anteriores
    if (window.dashboardState.routeAnimation) {
        clearInterval(window.dashboardState.routeAnimation);
        window.dashboardState.routeAnimation = null;
    }
    
    // Limpiar marcador anterior si existe
    if (window.dashboardState.repartidorMarker) {
        window.dashboardState.repartidorMarker.remove();
        window.dashboardState.repartidorMarker = null;
    }
    
    if (window.dashboardState.navigationMap) {
        window.dashboardState.navigationMap.remove();
    }

    const order = window.dashboardState.currentOrder;
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    
    const destLat = isNegocio ? order.lat_negocio : order.lat_cliente;
    const destLng = isNegocio ? order.lng_negocio : order.lng_cliente;

    console.log(`📍 Destino (${window.dashboardState.currentStep}):`, destLat, destLng);

    window.dashboardState.navigationMap = new mapboxgl.Map({
        container: 'navigationMap',
        style: 'mapbox://styles/mapbox/light-v11',
        center: [destLng, destLat],
        zoom: 15
    });

    // Esperar a que el mapa cargue
    window.dashboardState.navigationMap.on('load', function() {
        console.log('🗺️ Mapa cargado completamente');
        
        const destColor = isNegocio ? '#ffc107' : '#28a745';
        new mapboxgl.Marker({ color: destColor })
            .setLngLat([destLng, destLat])
            .addTo(window.dashboardState.navigationMap);

        window.dashboardState.navigationMap.addControl(new mapboxgl.NavigationControl(), 'top-right');
        
        // Obtener ubicación del repartidor después de que el mapa cargue
        obtenerUbicacionRepartidor();
    });
    
    console.log('✅ Mapa inicializándose...');
}

function obtenerUbicacionRepartidor() {
    console.log('📍 Obteniendo ubicación del repartidor...');
    
    if (!navigator.geolocation) {
        console.warn('⚠️ Geolocalización no soportada');
        usarUbicacionFallback();
        return;
    }
    
    // Verificar permisos primero (si está disponible)
    if (navigator.permissions) {
        navigator.permissions.query({ name: 'geolocation' })
            .then(permissionStatus => {
                console.log('📍 Estado de permiso:', permissionStatus.state);
                
                if (permissionStatus.state === 'denied') {
                    mostrarMensajePermisoDenegado();
                    usarUbicacionFallback();
                    return;
                }
                
                solicitarUbicacion();
            })
            .catch(e => {
                console.log('⚠️ No se pudo verificar permisos:', e);
                solicitarUbicacion();
            });
    } else {
        solicitarUbicacion();
    }
}

function mostrarMensajePermisoDenegado() {
    Swal.fire({
        icon: 'warning',
        title: 'Permiso de ubicación requerido',
        html: `
            <p>Para usar la navegación, necesitas permitir el acceso a tu ubicación.</p>
            <p><strong>Cómo activarlo:</strong></p>
            <ol style="text-align: left; padding-left: 20px;">
                <li>Haz clic en el ícono de candado en la barra de direcciones</li>
                <li>Busca "Ubicación" o "Location"</li>
                <li>Selecciona "Permitir"</li>
                <li>Recarga la página</li>
            </ol>
        `,
        confirmButtonText: 'Entendido'
    });
}

function usarUbicacionFallback() {
    console.log('📍 Usando ubicación por defecto');
    window.dashboardState.repartidorPosition = { lat: 19.4326, lng: -99.1332 };
    window.dashboardState.repartidorMarker = crearMarcadorRepartidor(19.4326, -99.1332);
    calcularRuta();
}

function solicitarUbicacion(intentos = 0) {
    const MAX_INTENTOS = 3;
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            window.dashboardState.repartidorPosition = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            console.log('📍 Ubicación obtenida:', window.dashboardState.repartidorPosition);
            
            // Crear marcador personalizado del repartidor
            window.dashboardState.repartidorMarker = crearMarcadorRepartidor(
                window.dashboardState.repartidorPosition.lat,
                window.dashboardState.repartidorPosition.lng
            );
            
            calcularRuta();
            
            window.dashboardState.watchId = navigator.geolocation.watchPosition(
                actualizarUbicacionRepartidor,
                (error) => console.error('❌ Error tracking location:', error),
                { enableHighAccuracy: true, timeout: 30000, maximumAge: 10000 }
            );
        },
        (error) => {
            let mensaje = 'Error obteniendo ubicación: ';
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    mensaje += 'Debes permitir el acceso a tu ubicación';
                    mostrarMensajePermisoDenegado();
                    usarUbicacionFallback();
                    return;
                case error.POSITION_UNAVAILABLE:
                    mensaje += 'Tu ubicación no está disponible';
                    if (intentos < MAX_INTENTOS - 1) {
                        console.log(`🔄 Reintentando... (${intentos + 1}/${MAX_INTENTOS})`);
                        setTimeout(() => solicitarUbicacion(intentos + 1), 3000);
                        return;
                    }
                    break;
                case error.TIMEOUT:
                    mensaje += 'Tiempo de espera agotado';
                    if (intentos < MAX_INTENTOS - 1) {
                        console.log(`🔄 Reintentando... (${intentos + 1}/${MAX_INTENTOS})`);
                        setTimeout(() => solicitarUbicacion(intentos + 1), 1000);
                        return;
                    }
                    break;
                default:
                    mensaje += error.message || 'Error desconocido';
            }
            
            console.error('❌', mensaje, error);
            usarUbicacionFallback();
        },
        {
            enableHighAccuracy: true,
            timeout: 30000,  // 30 segundos - más realista
            maximumAge: 10000  // Usar ubicación reciente si está disponible
        }
    );
}

function actualizarUbicacionRepartidor(position) {
    const nuevaUbicacion = {
        lat: position.coords.latitude,
        lng: position.coords.longitude
    };
    
    console.log('📍 Nueva ubicación del repartidor:', nuevaUbicacion);
    
    // Actualizar posición global
    window.dashboardState.repartidorPosition = nuevaUbicacion;
    
    // Actualizar marcador personalizado en tiempo real
    actualizarMarcadorRepartidor(nuevaUbicacion.lat, nuevaUbicacion.lng);
    
    // Recalcular ruta si es necesario
    if (window.dashboardState.currentOrder) {
        calcularRuta();
    }
}

function calcularRuta() {
    const repartidorPos = window.dashboardState.repartidorPosition;
    if (!repartidorPos) return;

    const order = window.dashboardState.currentOrder;
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    
    const destLat = isNegocio ? order.lat_negocio : order.lat_cliente;
    const destLng = isNegocio ? order.lng_negocio : order.lng_cliente;

    const url = `https://api.mapbox.com/directions/v5/mapbox/driving/${repartidorPos.lng},${repartidorPos.lat};${destLng},${destLat}?geometries=geojson&access_token=${mapboxgl.accessToken}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.routes && data.routes.length > 0) {
                const route = data.routes[0];
                
                const distanceKm = (route.distance / 1000).toFixed(1);
                const timeMin = Math.round(route.duration / 60);
                
                const distanceText = document.getElementById('distanceText');
                const timeText = document.getElementById('timeText');
                
                if (distanceText) distanceText.textContent = `${distanceKm} km`;
                if (timeText) timeText.textContent = `${timeMin} min`;
                
                // Dibujar ruta en el mapa
                if (window.dashboardState.navigationMap.isStyleLoaded()) {
                    agregarRutaAlMapa(route);
                } else {
                    window.dashboardState.navigationMap.on('load', () => agregarRutaAlMapa(route));
                }
            }
        })
        .catch(error => {
            console.error('❌ Error calculating route:', error);
        });
}

function agregarRutaAlMapa(route) {
    const map = window.dashboardState.navigationMap;
    
    if (map.getSource('route')) {
        map.removeLayer('route');
        map.removeSource('route');
    }
    
    // Limpiar animación anterior si existe
    if (map.getSource('route-progress')) {
        map.removeLayer('route-progress');
        map.removeSource('route-progress');
    }
    
    // Agregar ruta completa en negro (línea base)
    map.addSource('route', {
        type: 'geojson',
        data: {
            type: 'Feature',
            properties: {},
            geometry: route.geometry
        }
    });
    
    map.addLayer({
        id: 'route',
        type: 'line',
        source: 'route',
        layout: {
            'line-join': 'round',
            'line-cap': 'round'
        },
        paint: {
            'line-color': '#000000',
            'line-width': 4,
            'line-opacity': 0.3
        }
    });
    
    // Crear línea de progreso (inicialmente vacía)
    map.addSource('route-progress', {
        type: 'geojson',
        data: {
            type: 'Feature',
            properties: {},
            geometry: {
                type: 'LineString',
                coordinates: []
            }
        }
    });
    
    map.addLayer({
        id: 'route-progress',
        type: 'line',
        source: 'route-progress',
        layout: {
            'line-join': 'round',
            'line-cap': 'round'
        },
        paint: {
            'line-color': '#000000',
            'line-width': 6,
            'line-opacity': 1
        }
    });
    
    // Iniciar animación SOLO de la línea
    iniciarAnimacionRuta(route);
    
    // Ajustar vista
    const repartidorPos = window.dashboardState.repartidorPosition;
    const order = window.dashboardState.currentOrder;
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    
    const destLat = isNegocio ? order.lat_negocio : order.lat_cliente;
    const destLng = isNegocio ? order.lng_negocio : order.lng_cliente;
    
    const bounds = new mapboxgl.LngLatBounds()
        .extend([repartidorPos.lng, repartidorPos.lat])
        .extend([destLng, destLat]);
    
    map.fitBounds(bounds, { padding: 50 });
}

// ===========================
// FUNCIONES DE TRACKING GPS
// ===========================
function iniciarTrackingGPS(pedidoId) {
    console.log('🛰️ Iniciando tracking GPS para pedido:', pedidoId);
    
    if (!navigator.geolocation) {
        console.error('❌ Geolocalización no soportada');
        return false;
    }

    window.dashboardState.trackingActive = true;
    
    const geoOptions = {
        enableHighAccuracy: true,
        timeout: 30000,  // 30 segundos - más realista para GPS
        maximumAge: 10000  // Usar ubicación reciente (10 seg) si está disponible
    };

    const enviarUbicacion = (position) => {
        if (!window.dashboardState.trackingActive) return;

        const ubicacion = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            speed: position.coords.speed || 0,
            heading: position.coords.heading || 0,
            timestamp: new Date().toISOString(),
            order_id: pedidoId,
            courier_id: <?php echo $_SESSION['id_usuario'] ?? 'null'; ?>
        };

        console.log('📍 Enviando ubicación GPS:', ubicacion);
        
        // Actualizar marcador en tiempo real si el mapa está activo
        if (window.dashboardState.navigationMap && window.dashboardState.currentOrder) {
            actualizarMarcadorRepartidor(ubicacion.latitude, ubicacion.longitude);
        }
        
        // Enviar al servidor
        enviarUbicacionHTTP(ubicacion);
    };

    navigator.geolocation.getCurrentPosition(enviarUbicacion, console.error, geoOptions);
    
    window.dashboardState.locationWatchId = navigator.geolocation.watchPosition(
        enviarUbicacion,
        console.error,
        geoOptions
    );

    console.log('✅ Tracking GPS iniciado correctamente');
    return true;
}

function detenerTrackingGPS() {
    console.log('🛑 Deteniendo tracking GPS');
    
    window.dashboardState.trackingActive = false;
    
    if (window.dashboardState.locationWatchId !== null) {
        navigator.geolocation.clearWatch(window.dashboardState.locationWatchId);
        window.dashboardState.locationWatchId = null;
    }
    
    console.log('✅ Tracking GPS detenido');
}

function enviarUbicacionHTTP(ubicacion) {
    const baseUrl = window.location.protocol + '//' + window.location.host;
    const updateUrl = baseUrl + '/admin/actualizar_ubicacion_repartidor.php';
    
    fetch(updateUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(ubicacion),
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('📡 Status ubicación:', response.status);
        
        if (!response.ok) {
            // Si es 403, continuar silenciosamente (modo offline)
            if (response.status === 403) {
                console.log('⚠️ Error 403 en ubicación - continuando en modo offline');
                return { success: true, offline: true };
            }
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.log('⚠️ Respuesta no JSON en ubicación:', text.substring(0, 100));
                return { success: true, offline: true };
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.offline) {
                console.log('📍 Ubicación procesada en modo offline');
            } else {
                console.log('📍 Ubicación enviada exitosamente');
            }
        } else {
            console.warn('⚠️ Problema con ubicación, continuando:', data);
        }
    })
    .catch(error => {
        // Solo log, no mostrar errores al usuario para ubicación
        console.log('📍 Error en ubicación (modo silencioso):', error.message);
    });
}

// ===========================
// FUNCIONES AUXILIARES
// ===========================
function cerrarNavegacion() {
    console.log('❌ Cerrando navegación');
    
    const modal = document.getElementById('navigationModal');
    if (modal) {
        modal.classList.remove('show');
    }
    
    // Limpiar animaciones
    if (window.dashboardState.routeAnimation) {
        clearInterval(window.dashboardState.routeAnimation);
        window.dashboardState.routeAnimation = null;
    }
    
    // Limpiar marcador personalizado
    if (window.dashboardState.repartidorMarker) {
        window.dashboardState.repartidorMarker.remove();
        window.dashboardState.repartidorMarker = null;
    }
    
    if (window.dashboardState.navigationMap) {
        window.dashboardState.navigationMap.remove();
        window.dashboardState.navigationMap = null;
    }
    
    if (window.dashboardState.watchId) {
        navigator.geolocation.clearWatch(window.dashboardState.watchId);
        window.dashboardState.watchId = null;
    }
    
    window.dashboardState.currentOrder = null;
}

function abrirGoogleMaps() {
    const order = window.dashboardState.currentOrder;
    const repartidorPos = window.dashboardState.repartidorPosition;
    
    if (!order || !repartidorPos) return;
    
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    const destLat = isNegocio ? order.lat_negocio : order.lat_cliente;
    const destLng = isNegocio ? order.lng_negocio : order.lng_cliente;
    
    const url = `https://www.google.com/maps/dir/${repartidorPos.lat},${repartidorPos.lng}/${destLat},${destLng}`;
    window.open(url, '_blank');
}

function abrirWaze() {
    const order = window.dashboardState.currentOrder;
    if (!order) return;
    
    const isNegocio = window.dashboardState.currentStep === 'negocio';
    const destLat = isNegocio ? order.lat_negocio : order.lat_cliente;
    const destLng = isNegocio ? order.lng_negocio : order.lng_cliente;
    
    const url = `https://waze.com/ul?ll=${destLat},${destLng}&navigate=yes`;
    window.open(url, '_blank');
}

// ===========================
// FUNCIÓN PARA ACEPTAR PEDIDOS
// ===========================
function aceptarPedido(orderId) {
    console.log('✅ Aceptando pedido:', orderId);
    
    const btnAceptar = document.querySelector(`.btn-aceptar[onclick*="${orderId}"]`);
    if (btnAceptar) {
        btnAceptar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        btnAceptar.disabled = true;
    }
    
    const baseUrl = window.location.protocol + '//' + window.location.host;
    const acceptUrl = baseUrl + '/admin/aceptar_pedido.php';
    
    fetch(acceptUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'id_pedido=' + orderId,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Pedido aceptado!',
                text: 'El pedido ha sido asignado correctamente',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            if (btnAceptar) {
                btnAceptar.innerHTML = 'Aceptar';
                btnAceptar.disabled = false;
            }
            alert('Error al aceptar el pedido: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (btnAceptar) {
            btnAceptar.innerHTML = 'Aceptar';
            btnAceptar.disabled = false;
        }
        alert('Error al procesar la solicitud');
    });
}

// ===========================
// GESTIÓN DE ESTADO DE CONEXIÓN
// ===========================
function actualizarEstadoConexion(conectado) {
    console.log('🔄 Actualizando estado de conexión:', conectado ? 'ONLINE' : 'OFFLINE');
    
    const statusSwitch = document.getElementById('connectionStatus');
    const statusIndicator = document.getElementById('statusIndicator');
    const statusText = document.getElementById('statusText');
    
    if (!statusSwitch || !statusIndicator || !statusText) {
        console.warn('⚠️ Elementos de estado no encontrados');
        return;
    }
    
    if (conectado) {
        // Estado ONLINE
        statusSwitch.classList.remove('offline');
        statusSwitch.classList.add('online');
        statusIndicator.classList.add('online');
        statusText.classList.remove('offline');
        statusText.classList.add('online');
        statusText.innerHTML = `
            <strong>En Línea</strong>
            <small>Conectado al sistema</small>
        `;
    } else {
        // Estado OFFLINE
        statusSwitch.classList.remove('online');
        statusSwitch.classList.add('offline');
        statusIndicator.classList.remove('online');
        statusText.classList.remove('online');
        statusText.classList.add('offline');
        statusText.innerHTML = `
            <strong>Desconectado</strong>
            <small>Reconectando...</small>
        `;
    }
}

// ===========================
// INTEGRACIÓN CON WEBSOCKET
// ===========================
function setupWebSocketIntegration() {
    console.log('🔌 Configurando integración WebSocket...');
    
    // Intentar conectar al WebSocket
    if (typeof CourierClient !== 'undefined') {
        console.log('📡 CourierClient disponible, estableciendo conexión...');
        
        try {
            // Inicializar cliente WebSocket si no existe
            if (!window.courierClient) {
                console.log('🆕 Creando nueva instancia de CourierClient...');
                window.courierClient = new CourierClient(<?php echo $id_repartidor; ?>);
                
                // IMPORTANTE: Iniciar la conexión
                console.log('🔌 Iniciando conexión WebSocket...');
                window.courierClient.connect();
            }
            
            // Manejar eventos de conexión
            if (window.courierClient.on) {
                window.courierClient.on('connected', () => {
                    console.log('✅ WebSocket conectado exitosamente');
                    actualizarEstadoConexion(true);
                });
                
                window.courierClient.on('disconnected', () => {
                    console.log('❌ WebSocket desconectado');
                    actualizarEstadoConexion(false);
                });
                
                window.courierClient.on('reconnecting', () => {
                    console.log('🔄 Intentando reconectar WebSocket...');
                    actualizarEstadoConexion(false);
                });
                
                // Manejar pedidos disponibles del WebSocket
                window.courierClient.on('onNewAvailableOrders', (data) => {
                    console.log('📦 Pedidos WebSocket recibidos:', data);
                    
                    const container = document.querySelector('.list-group');
                    if (container && container.closest('.card-dashboard')) {
                        const cardTitle = container.closest('.card-dashboard').querySelector('h5');
                        if (cardTitle && cardTitle.textContent.includes('Pedidos Disponibles')) {
                            updatePedidosDisponibles(data.orders || []);
                        }
                    }
                });
            }
            
            // Verificar estado actual
            setTimeout(() => {
                if (window.courierClient.isConnected) {
                    actualizarEstadoConexion(true);
                } else {
                    actualizarEstadoConexion(false);
                }
            }, 1000);
            
        } catch (error) {
            console.error('❌ Error al configurar WebSocket:', error);
            actualizarEstadoConexion(false);
        }
        
    } else {
        console.warn('📡 CourierClient no disponible, cargando script...');
        actualizarEstadoConexion(false);
        
        // Intentar cargar el script del WebSocket
        setTimeout(() => {
            if (typeof CourierClient !== 'undefined') {
                setupWebSocketIntegration();
            } else {
                console.error('❌ CourierClient sigue sin estar disponible');
                actualizarEstadoConexion(false);
            }
        }, 3000);
    }
}

function updatePedidosDisponibles(orders) {
    console.log('🔄 Actualizando pedidos disponibles:', orders);
    
    // Buscar el container correcto en el DOM
    const containers = document.querySelectorAll('.list-group');
    let targetContainer = null;
    
    for (let container of containers) {
        const card = container.closest('.card-dashboard');
        if (card) {
            const title = card.querySelector('h5');
            if (title && title.textContent.includes('Pedidos Disponibles')) {
                targetContainer = container;
                break;
            }
        }
    }
    
    if (!targetContainer) {
        console.warn('⚠️ Container de pedidos disponibles no encontrado');
        return;
    }
    
    if (!orders || orders.length === 0) {
        targetContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h4>No hay pedidos disponibles</h4>
                <p>En este momento no hay pedidos disponibles en tu zona.</p>
            </div>
        `;
        return;
    }
    
    targetContainer.innerHTML = orders.map(order => {
        const idPedido = parseInt(order.id_pedido, 10) || 0;
        const distancia = ((parseFloat(order.distancia) || 1500) / 1000).toFixed(1);
        return `
        <div class="list-group-item" data-order-id="${idPedido}">
            <div class="d-flex justify-content-between">
                <div class="flex-grow-1">
                    <h6>Pedido #${idPedido}</h6>
                    <small class="text-muted d-block">Restaurante: ${sanitizeHTML(order.restaurante || 'Restaurante')}</small>
                    <small class="text-muted d-block">Cliente: ${sanitizeHTML(order.cliente || 'Cliente')}</small>
                    <small class="text-muted d-block">Dirección: ${sanitizeHTML(order.direccion_entrega || 'Dirección')}</small>
                    <small class="text-muted d-block">Distancia: ${distancia} km</small>
                    <small class="text-success">
                        <i class="fas fa-dollar-sign me-1"></i>
                        Ganancia estimada: $35.00
                    </small>
                </div>
                <button class="btn btn-sm btn-success btn-aceptar align-self-start"
                        onclick="aceptarPedido(${idPedido})">
                    Aceptar
                </button>
            </div>
        </div>`;
    }).join('');
    
    console.log('✅ Lista de pedidos disponibles actualizada');
}

// ===========================
// NAVEGACIÓN ENTRE SECCIONES
// ===========================
function setupBottomNavigation() {
    console.log('⚙️ Configurando navegación inferior');
    
    const navItems = document.querySelectorAll('.bottom-nav .nav-item');
    
    navItems.forEach(item => {
        // Evitar agregar listener al botón de wallet (tiene href real)
        if (item.classList.contains('wallet-nav')) {
            return;
        }
        
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Obtener la sección desde el href
            const targetSection = this.getAttribute('href').substring(1); // Quitar el #
            
            console.log('📱 Navegando a sección:', targetSection);
            
            // Actualizar estado activo de la navegación
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            // Mostrar la sección correspondiente
            mostrarSeccion(targetSection);
        });
    });
}

function mostrarSeccion(seccionId) {
    console.log('🔄 Mostrando sección:', seccionId);
    
    // Ocultar todas las secciones
    const secciones = document.querySelectorAll('.dashboard-section');
    secciones.forEach(seccion => {
        seccion.style.display = 'none';
    });
    
    // Mostrar la sección seleccionada
    const seccionTarget = document.getElementById('seccion-' + seccionId);
    if (seccionTarget) {
        seccionTarget.style.display = 'block';
        
        // Scroll al inicio
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        console.log('✅ Sección mostrada:', seccionId);
    } else {
        console.warn('⚠️ Sección no encontrada:', seccionId);
    }
}

// ===========================
// INICIALIZACIÓN
// ===========================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dashboard cargado, inicializando...');
    
    // Ejecutar diagnóstico al cargar
    probarConexion();
    
    // Configurar WebSocket si está disponible
    setTimeout(() => {
        setupWebSocketIntegration();
    }, 2000);
    
    // Configurar navegación móvil
    setupBottomNavigation();
    
    // Verificar si hay pedidos activos para tracking
    <?php if (count($pedidos_activos) > 0): ?>
    <?php foreach ($pedidos_activos as $pedido): ?>
    <?php if ($pedido['id_estado'] == 1 || $pedido['id_estado'] == 4 || $pedido['id_estado'] == 5): ?>
    setTimeout(() => {
        console.log('🔄 Detectado pedido activo, preparando para tracking:', <?php echo $pedido['id_pedido']; ?>);
        if (!window.dashboardState.trackingActive) {
            iniciarTrackingGPS(<?php echo $pedido['id_pedido']; ?>);
        }
    }, 3000);
    <?php break; ?>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    
    // Mostrar notificación de pedidos activos
    <?php if (count($pedidos_activos) > 0): ?>
    setTimeout(() => {
        Swal.fire({
            icon: 'info',
            title: 'Tienes pedidos activos',
            text: 'Tienes <?php echo count($pedidos_activos); ?> pedido(s) esperando. Haz clic en cualquiera para navegar.',
            timer: 4000,
            showConfirmButton: false,
            position: 'top-end',
            toast: true
        });
    }, 1000);
    <?php endif; ?>
    
    console.log('✅ Dashboard inicializado correctamente');
});

// Manejar evento de desconexión
window.addEventListener('offline', function() {
    Swal.fire({
        icon: 'warning',
        title: 'Sin conexión',
        text: 'Verifica tu conexión a internet',
        timer: 3000,
        showConfirmButton: false,
        position: 'top-end',
        toast: true
    });
});

// Pausar/reanudar tracking cuando el tab cambia de visibilidad
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        console.log('📱 App oculta, reduciendo frecuencia de tracking...');
        // Opcionalmente pausar animaciones
        if (window.dashboardState.routeAnimation) {
            clearInterval(window.dashboardState.routeAnimation);
            window.dashboardState.routeAnimation = null;
        }
    } else {
        console.log('📱 App visible, reanudando...');
        // Reanudar si hay una ruta activa
        if (window.dashboardState.currentOrder && window.dashboardState.navigationMap) {
            calcularRuta();
        }
    }
});

// Limpiar al cerrar - limpieza completa de recursos
window.addEventListener('beforeunload', function() {
    console.log('🧹 Limpiando recursos antes de salir...');
    
    // Detener tracking GPS
    detenerTrackingGPS();
    
    // Limpiar intervalos de animación
    if (window.dashboardState.routeAnimation) {
        clearInterval(window.dashboardState.routeAnimation);
        window.dashboardState.routeAnimation = null;
    }
    
    // Limpiar watchPosition
    if (window.dashboardState.watchId) {
        navigator.geolocation.clearWatch(window.dashboardState.watchId);
        window.dashboardState.watchId = null;
    }
    
    if (window.dashboardState.locationWatchId) {
        navigator.geolocation.clearWatch(window.dashboardState.locationWatchId);
        window.dashboardState.locationWatchId = null;
    }
    
    // Limpiar mapa
    if (window.dashboardState.navigationMap) {
        window.dashboardState.navigationMap.remove();
        window.dashboardState.navigationMap = null;
    }
    
    // Limpiar WebSocket
    if (window.courierClient && typeof window.courierClient.disconnect === 'function') {
        window.courierClient.disconnect();
    }
    
    console.log('✅ Recursos limpiados');
});

// Sincronizar cambios pendientes cuando recupere conexión
window.addEventListener('online', function() {
    sincronizarCambiosPendientes();
    
    Swal.fire({
        icon: 'success',
        title: 'Conexión restaurada',
        text: 'Ya puedes continuar trabajando normalmente',
        timer: 3000,
        showConfirmButton: false,
        position: 'top-end',
        toast: true
    });
});

function sincronizarCambiosPendientes() {
    const pending = JSON.parse(localStorage.getItem('pending_updates') || '[]');
    
    if (pending.length === 0) return;
    
    console.log('🔄 Sincronizando cambios pendientes:', pending.length);
    
    const baseUrl = window.location.protocol + '//' + window.location.host;
    
    pending.forEach((update, index) => {
        // Intentar enviar cada cambio pendiente
        fetch(baseUrl + '/admin/actualizar_estado_pedido.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `id_pedido=${update.order_id}&estado=${update.nuevo_estado}`,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Cambio sincronizado:', update.order_id);
                // Remover del array
                const currentPending = JSON.parse(localStorage.getItem('pending_updates') || '[]');
                const filtered = currentPending.filter(p => p.order_id !== update.order_id || p.timestamp !== update.timestamp);
                localStorage.setItem('pending_updates', JSON.stringify(filtered));
            }
        })
        .catch(err => console.error('❌ Error sincronizando:', err));
    });
}

// =============================================
// Sistema de Foto de Entrega
// =============================================
const fotoEntregaState = {
    stream: null,
    fotoBlob: null,
    ubicacion: { latitud: null, longitud: null }
};

function abrirModalFoto() {
    console.log('📸 Abriendo modal de foto de entrega');

    const modal = document.getElementById('fotoEntregaModal');
    modal.classList.add('show');

    // Obtener ubicación
    obtenerUbicacionFoto();

    // Intentar iniciar cámara
    iniciarCamara();
}

function cerrarModalFoto() {
    console.log('📸 Cerrando modal de foto');

    const modal = document.getElementById('fotoEntregaModal');
    modal.classList.remove('show');

    // Detener cámara
    detenerCamara();

    // Limpiar estado
    fotoEntregaState.fotoBlob = null;

    // Resetear UI
    resetearUIFoto();

    // Reabrir modal de navegación
    const navModal = document.getElementById('navigationModal');
    if (navModal) {
        navModal.classList.add('show');
    }
}

function obtenerUbicacionFoto() {
    const indicator = document.getElementById('locationIndicator');

    if (!navigator.geolocation) {
        indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>GPS no disponible</span>';
        indicator.className = 'location-indicator error';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            fotoEntregaState.ubicacion.latitud = position.coords.latitude;
            fotoEntregaState.ubicacion.longitud = position.coords.longitude;
            indicator.innerHTML = '<i class="fas fa-map-marker-alt"></i><span>Ubicación capturada</span>';
            indicator.className = 'location-indicator success';
            console.log('📍 Ubicación obtenida:', fotoEntregaState.ubicacion);
        },
        (error) => {
            console.warn('⚠️ Error obteniendo ubicación:', error);
            indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Sin ubicación</span>';
            indicator.className = 'location-indicator error';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

async function iniciarCamara() {
    const video = document.getElementById('cameraVideo');
    const overlay = document.getElementById('cameraOverlay');

    try {
        // Preferir cámara trasera en móviles
        const constraints = {
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        fotoEntregaState.stream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = fotoEntregaState.stream;
        overlay.style.display = 'none';
        console.log('📹 Cámara iniciada');

    } catch (error) {
        console.warn('⚠️ No se pudo acceder a la cámara:', error);
        overlay.innerHTML = '<i class="fas fa-image"></i>';
        overlay.style.display = 'flex';
    }
}

function detenerCamara() {
    if (fotoEntregaState.stream) {
        fotoEntregaState.stream.getTracks().forEach(track => track.stop());
        fotoEntregaState.stream = null;
    }

    const video = document.getElementById('cameraVideo');
    if (video) video.srcObject = null;
}

function capturarFoto() {
    const video = document.getElementById('cameraVideo');
    const preview = document.getElementById('fotoPreview');

    if (!fotoEntregaState.stream) {
        // Si no hay cámara, abrir galería
        seleccionarDeGaleria();
        return;
    }

    // Crear canvas para capturar
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);

    // Convertir a blob
    canvas.toBlob((blob) => {
        fotoEntregaState.fotoBlob = blob;

        // Mostrar preview
        const url = URL.createObjectURL(blob);
        preview.src = url;
        preview.classList.add('show');
        video.style.display = 'none';

        // Cambiar botones
        mostrarBotonesConfirmacion();

        console.log('📸 Foto capturada, tamaño:', blob.size);
    }, 'image/jpeg', 0.85);
}

function seleccionarDeGaleria() {
    document.getElementById('fotoInput').click();
}

function procesarFotoGaleria(event) {
    const file = event.target.files[0];
    if (!file) return;

    const preview = document.getElementById('fotoPreview');
    const video = document.getElementById('cameraVideo');
    const overlay = document.getElementById('cameraOverlay');

    // Guardar blob
    fotoEntregaState.fotoBlob = file;

    // Mostrar preview
    const url = URL.createObjectURL(file);
    preview.src = url;
    preview.classList.add('show');
    video.style.display = 'none';
    overlay.style.display = 'none';

    // Cambiar botones
    mostrarBotonesConfirmacion();

    console.log('📸 Foto seleccionada de galería:', file.name);
}

function mostrarBotonesConfirmacion() {
    document.getElementById('captureActions').style.display = 'none';
    document.getElementById('confirmActions').style.display = 'flex';
}

function reintentarFoto() {
    const preview = document.getElementById('fotoPreview');
    const video = document.getElementById('cameraVideo');
    const overlay = document.getElementById('cameraOverlay');

    // Limpiar
    fotoEntregaState.fotoBlob = null;
    preview.classList.remove('show');
    preview.src = '';

    // Restaurar video
    if (fotoEntregaState.stream) {
        video.style.display = 'block';
    } else {
        overlay.style.display = 'flex';
    }

    // Cambiar botones
    document.getElementById('confirmActions').style.display = 'none';
    document.getElementById('captureActions').style.display = 'flex';

    // Limpiar input
    document.getElementById('fotoInput').value = '';
}

function resetearUIFoto() {
    const preview = document.getElementById('fotoPreview');
    const video = document.getElementById('cameraVideo');
    const overlay = document.getElementById('cameraOverlay');

    preview.classList.remove('show');
    preview.src = '';
    video.style.display = 'block';
    overlay.style.display = 'none';

    document.getElementById('confirmActions').style.display = 'none';
    document.getElementById('captureActions').style.display = 'flex';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('fotoInput').value = '';

    // Resetear indicador de ubicación
    const indicator = document.getElementById('locationIndicator');
    indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Obteniendo ubicación...</span>';
    indicator.className = 'location-indicator';
}

async function confirmarFotoEntrega() {
    if (!fotoEntregaState.fotoBlob) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin foto',
            text: 'Por favor toma o selecciona una foto primero',
            confirmButtonColor: '#ff6b35'
        });
        return;
    }

    const order = window.dashboardState.currentOrder;
    if (!order) {
        mostrarError('No hay pedido activo');
        return;
    }

    console.log('📤 Subiendo foto de entrega para pedido:', order.id_pedido);

    // Mostrar progreso
    document.getElementById('uploadProgress').style.display = 'block';
    const progressBar = document.getElementById('progressBar');

    // Crear FormData
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('id_pedido', order.id_pedido);
    formData.append('foto', fotoEntregaState.fotoBlob, 'entrega.jpg');

    if (fotoEntregaState.ubicacion.latitud) {
        formData.append('latitud', fotoEntregaState.ubicacion.latitud);
        formData.append('longitud', fotoEntregaState.ubicacion.longitud);
    }

    try {
        // Simular progreso
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            if (progress <= 90) {
                progressBar.style.width = progress + '%';
            }
        }, 100);

        const response = await fetch('/api/foto_entrega.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        clearInterval(progressInterval);
        progressBar.style.width = '100%';

        const data = await response.json();

        if (data.success) {
            console.log('✅ Foto subida correctamente:', data.foto_url);

            // Cerrar modal de foto
            detenerCamara();
            document.getElementById('fotoEntregaModal').classList.remove('show');
            resetearUIFoto();

            // Ahora proceder con la confirmación de entrega
            Swal.fire({
                icon: 'success',
                title: 'Foto guardada',
                text: 'Ahora confirma la entrega del pedido',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Confirmar entrega sin volver a pedir foto
                confirmarEntregaConFoto();
            });

        } else {
            throw new Error(data.message || 'Error al subir la foto');
        }

    } catch (error) {
        console.error('❌ Error subiendo foto:', error);
        document.getElementById('uploadProgress').style.display = 'none';
        progressBar.style.width = '0%';

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'No se pudo subir la foto. Intenta de nuevo.',
            confirmButtonColor: '#ff6b35'
        });
    }
}

function confirmarEntregaConFoto() {
    console.log('✅ Procediendo a marcar como entregado (foto ya subida)');
    ejecutarCambioEstado('entregado');
}
    </script>
    
    <!-- Sistema Multi-Pedido -->
    <script src="/js/multi-pedido.js"></script>
</body>
</html>