<?php
// Manejador de errores centralizado
require_once __DIR__ . '/config/error_handler.php';

// Iniciar y verificar sesión
session_start();
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [
        'items' => [],
        'negocio_id' => 0,
        'negocio_nombre' => '',
        'subtotal' => 0,
        'total' => 0
    ];
    session_write_close();
    session_start();
}

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Producto.php';
require_once 'models/Negocio.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Redirigir al login si no está logueado
if (!$usuario_logueado) {
    header("Location: login.php?redirect=carrito.php");
    exit;
}

// Si está logueado, obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

// Inicializar carrito si no existe o no tiene la estructura correcta
if (!isset($_SESSION['carrito']) || !isset($_SESSION['carrito']['items'])) {
    $_SESSION['carrito'] = [
        'items' => [],
        'negocio_id' => 0,
        'negocio_nombre' => '',
        'subtotal' => 0,
        'total' => 0
    ];
}

// Acciones del carrito
if (isset($_GET['action'])) {
    $accion = $_GET['action'];
    
    // Agregar producto al carrito
    if ($accion == 'agregar' && isset($_POST['id_producto']) && isset($_POST['cantidad'])) {
        $id_producto = (int)$_POST['id_producto'];
        $cantidad = (int)$_POST['cantidad'];
        
        $producto = new Producto($db);
        $producto->id_producto = $id_producto;
        $info_producto = $producto->obtenerPorId($id_producto);
        
        if ($info_producto && $cantidad > 0) {
            $id_negocio = $info_producto['id_negocio'];

            // Verificar si el usuario tiene un pedido pendiente de este negocio
            $stmt_pedido = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE id_usuario = ? AND id_negocio = ? AND id_estado = 1");
            $stmt_pedido->execute([$usuario->id_usuario, $id_negocio]);
            $tiene_pedido_pendiente = $stmt_pedido->fetchColumn() > 0;

            if ($tiene_pedido_pendiente) {
                // Guardar mensaje de error en sesión
                $_SESSION['cart_error'] = "Ya tienes un pedido pendiente de este restaurante. No puedes agregar productos hasta que se complete tu pedido actual.";
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'carrito.php';
                header("Location: $redirect");
                exit;
            }

            // Obtener información del negocio
            $negocio = new Negocio($db);
            $negocio->id_negocio = $id_negocio;
            $info_negocio = $negocio->obtenerPorId();
            $nombre_negocio = ($info_negocio && isset($info_negocio['nombre'])) ? $info_negocio['nombre'] : 'Restaurante';

            // ✅ VALIDAR HORARIOS DEL NEGOCIO - NO PERMITIR AGREGAR SI ESTÁ CERRADO
            $negocio_esta_abierto = $negocio->estaAbierto();
            if (!$negocio_esta_abierto) {
                // Obtener horarios del día actual para mostrar en el mensaje
                $dia_actual = (int)date('w');
                $stmt_horario = $db->prepare("SELECT hora_apertura, hora_cierre FROM negocio_horarios WHERE id_negocio = ? AND dia_semana = ? AND activo = 1");
                $stmt_horario->execute([$id_negocio, $dia_actual]);
                $horario_hoy = $stmt_horario->fetch(PDO::FETCH_ASSOC);
                
                $mensaje_horario = "";
                if ($horario_hoy) {
                    $apertura = substr($horario_hoy['hora_apertura'], 0, 5);
                    $cierre = substr($horario_hoy['hora_cierre'], 0, 5);
                    $mensaje_horario = " Horario de hoy: $apertura - $cierre";
                } else {
                    $mensaje_horario = " El negocio no abre hoy.";
                }
                
                $_SESSION['cart_error'] = "El negocio '$nombre_negocio' está cerrado en este momento.$mensaje_horario";
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'negocio.php?id=' . $id_negocio;
                header("Location: $redirect");
                exit;
            }

            // Verificar si ya hay productos de otro negocio
            $carrito_vacio = empty($_SESSION['carrito']['items']);
            $mismo_negocio = true;

            if (!$carrito_vacio && $_SESSION['carrito']['negocio_id'] != $id_negocio) {
                $mismo_negocio = false;
            }
            
            // Si el carrito tiene productos de otro negocio y el usuario confirma, limpiar carrito
            if (!$carrito_vacio && !$mismo_negocio && isset($_POST['confirmar_cambio']) && $_POST['confirmar_cambio'] == 1) {
                $_SESSION['carrito'] = [
                    'items' => [],
                    'negocio_id' => $id_negocio,
                    'negocio_nombre' => $nombre_negocio,
                    'subtotal' => 0,
                    'total' => 0
                ];
                $carrito_vacio = true;
                $mismo_negocio = true;
            }
            
            // Si el carrito está vacío o los productos son del mismo negocio
            if ($carrito_vacio || $mismo_negocio) {
                // Actualizar información del negocio
                $_SESSION['carrito']['negocio_id'] = $id_negocio;
                $_SESSION['carrito']['negocio_nombre'] = $nombre_negocio;
                
                $producto_existe = false;
                $key_existente = null;
                
                // Verificar si el producto ya está en el carrito
                foreach ($_SESSION['carrito']['items'] as $key => $item) {
                    if (isset($item['id_producto']) && $item['id_producto'] == $id_producto) {
                        $producto_existe = true;
                        $key_existente = $key;
                        break;
                    }
                }
                
                // Si el producto ya existe, solo actualizar la cantidad
                if ($producto_existe && $key_existente !== null) {
                    $_SESSION['carrito']['items'][$key_existente]['cantidad'] += $cantidad;
                } 
                // Si el producto no existe en el carrito, agregarlo con índice numérico
                else {
                    $_SESSION['carrito']['items'][] = [
                        'id_producto' => $id_producto,
                        'nombre' => $info_producto['nombre'],
                        'descripcion' => isset($info_producto['descripcion']) ? $info_producto['descripcion'] : '',
                        'precio' => $info_producto['precio'],
                        'cantidad' => $cantidad,
                        'imagen' => isset($info_producto['imagen']) ? $info_producto['imagen'] : ''
                    ];
                }
                
                // Calcular subtotal y total
                $_SESSION['carrito']['subtotal'] = 0;
                foreach ($_SESSION['carrito']['items'] as $item) {
                    $_SESSION['carrito']['subtotal'] += $item['precio'] * $item['cantidad'];
                }
                
                // Obtener costo de envío del negocio
                $costo_envio = ($info_negocio && isset($info_negocio['costo_envio'])) ? 
                               $info_negocio['costo_envio'] : 0;
                $_SESSION['carrito']['total'] = $_SESSION['carrito']['subtotal'] + $costo_envio;
                
                // Redirigir de vuelta al negocio o a donde estaba el usuario
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'carrito.php';
                header("Location: $redirect");
                exit;
            } else {
                // Mostrar confirmación para cambiar de restaurante
                $mensaje_confirmacion = "Ya tienes productos de otro restaurante en tu carrito. 
                ¿Quieres vaciar tu carrito actual y agregar productos de {$nombre_negocio}?";
                
                // Guardar información temporal para después de la confirmación
                $_SESSION['temp_product'] = [
                    'id_producto' => $id_producto,
                    'cantidad' => $cantidad,
                    'redirect' => isset($_POST['redirect']) ? $_POST['redirect'] : 'carrito.php'
                ];
            }
        }
    }
    
    // Actualizar cantidad
    elseif ($accion == 'actualizar' && isset($_POST['id_producto']) && isset($_POST['cantidad'])) {
        $id_producto = (int)$_POST['id_producto'];
        $cantidad = (int)$_POST['cantidad'];
        
        $item_encontrado = false;
        
        foreach ($_SESSION['carrito']['items'] as $key => $item) {
            if (isset($item['id_producto']) && $item['id_producto'] == $id_producto) {
                if ($cantidad > 0) {
                    $_SESSION['carrito']['items'][$key]['cantidad'] = $cantidad;
                } else {
                    unset($_SESSION['carrito']['items'][$key]);
                }
                $item_encontrado = true;
                break;
            }
        }
        
        // Reindexar el array para evitar índices vacíos
        $_SESSION['carrito']['items'] = array_values($_SESSION['carrito']['items']);
        
        // Recalcular totales
        $_SESSION['carrito']['subtotal'] = 0;
        foreach ($_SESSION['carrito']['items'] as $item) {
            $_SESSION['carrito']['subtotal'] += $item['precio'] * $item['cantidad'];
        }
        
        // Obtener costo de envío
        $costo_envio = 0;
        if (!empty($_SESSION['carrito']['items'])) {
            $negocio = new Negocio($db);
            $negocio->id_negocio = $_SESSION['carrito']['negocio_id'];
            $info_negocio = $negocio->obtenerPorId();
            
            if ($info_negocio && isset($info_negocio['costo_envio'])) {
                $costo_envio = $info_negocio['costo_envio'];
            }
        }
        
        $_SESSION['carrito']['total'] = $_SESSION['carrito']['subtotal'] + $costo_envio;
        
        // Si el carrito está vacío, resetear completamente
        if (empty($_SESSION['carrito']['items'])) {
            $_SESSION['carrito'] = [
                'items' => [],
                'negocio_id' => 0,
                'negocio_nombre' => '',
                'subtotal' => 0,
                'total' => 0
            ];
        }
        
        // Responder con JSON si es una solicitud AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            echo json_encode([
                'success' => $item_encontrado,
                'total_carrito' => number_format($_SESSION['carrito']['total'], 2),
                'cantidad_items' => count($_SESSION['carrito']['items']),
                'carrito_vacio' => empty($_SESSION['carrito']['items'])
            ]);
            exit;
        }
        
        header("Location: carrito.php");
        exit;
    }
    
    // Eliminar producto
    elseif ($accion == 'eliminar' && isset($_GET['id_producto'])) {
        $id_producto = (int)$_GET['id_producto'];
        
        foreach ($_SESSION['carrito']['items'] as $key => $item) {
            if (isset($item['id_producto']) && $item['id_producto'] == $id_producto) {
                unset($_SESSION['carrito']['items'][$key]);
                break;
            }
        }
        
        // Reindexar el array
        $_SESSION['carrito']['items'] = array_values($_SESSION['carrito']['items']);
        
        // Recalcular totales
        $_SESSION['carrito']['subtotal'] = 0;
        foreach ($_SESSION['carrito']['items'] as $item) {
            $_SESSION['carrito']['subtotal'] += $item['precio'] * $item['cantidad'];
        }
        
        // Obtener costo de envío
        $costo_envio = 0;
        if (!empty($_SESSION['carrito']['items'])) {
            $negocio = new Negocio($db);
            $negocio->id_negocio = $_SESSION['carrito']['negocio_id'];
            $info_negocio = $negocio->obtenerPorId();
            
            if ($info_negocio && isset($info_negocio['costo_envio'])) {
                $costo_envio = $info_negocio['costo_envio'];
            }
        }
        
        $_SESSION['carrito']['total'] = $_SESSION['carrito']['subtotal'] + $costo_envio;
        
        // Si el carrito está vacío, resetear completamente
        if (empty($_SESSION['carrito']['items'])) {
            $_SESSION['carrito'] = [
                'items' => [],
                'negocio_id' => 0,
                'negocio_nombre' => '',
                'subtotal' => 0,
                'total' => 0
            ];
        }
        
        header("Location: carrito.php");
        exit;
    }
    
    // Vaciar carrito
    elseif ($accion == 'vaciar') {
        $_SESSION['carrito'] = [
            'items' => [],
            'negocio_id' => 0,
            'negocio_nombre' => '',
            'subtotal' => 0,
            'total' => 0
        ];
        header("Location: carrito.php");
        exit;
    }
}

// Calcular totales
$subtotal = isset($_SESSION['carrito']['subtotal']) ? $_SESSION['carrito']['subtotal'] : 0;
$costo_envio = 0;
$total = 0;

// Verificar si el carrito existe y tiene la estructura correcta
if (!empty($_SESSION['carrito']['items'])) {
    // Obtener información del negocio para el costo de envío
    if (isset($_SESSION['carrito']['negocio_id']) && $_SESSION['carrito']['negocio_id'] > 0) {
        $negocio_id = $_SESSION['carrito']['negocio_id'];
        $negocio = new Negocio($db);
        $negocio->id_negocio = $negocio_id;
        $info_negocio = $negocio->obtenerPorId();
        
        if ($info_negocio && isset($info_negocio['costo_envio'])) {
            $costo_envio = $info_negocio['costo_envio'];
        }
    }
    
    $total = $subtotal + $costo_envio;
    
    // Actualizar el total en la sesión
    $_SESSION['carrito']['total'] = $total;
}

// Preparar datos para la vista
$carrito_items = $_SESSION['carrito']['items'];
$negocio_nombre = isset($_SESSION['carrito']['negocio_nombre']) ? $_SESSION['carrito']['negocio_nombre'] : '';
$negocio_id = isset($_SESSION['carrito']['negocio_id']) ? $_SESSION['carrito']['negocio_id'] : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu Carrito - QuickBite</title>
    
    <!-- Global Theme CSS y JS (Modo Oscuro Persistente) -->
    <link rel="stylesheet" href="assets/css/global-theme.css?v=2.1">
    <script src="assets/js/theme-handler.js?v=2.1"></script>
    
    <!-- Fonts: Inter and DM Sans -->
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/transitions.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@300&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;         /* Azul principal */
            --primary-light: #0165FF;   /* Azul claro */
            --primary-dark: #0165FF;    /* Azul oscuro */
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FFFFFF;
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
            padding-bottom: 80px;
        }

        .page-header {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }

        .back-button {
            background: var(--secondary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--dark);
            margin-right: 15px;
        }

        .page-title {
            font-size: 1.5rem;
            margin: 0;
        }

        .cart-empty {
            text-align: center;
            padding: 40px 0;
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .cart-empty i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }

        .cart-empty h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .cart-empty p {
            color: #666;
            max-width: 80%;
            margin: 0 auto 20px;
        }

        .btn-browse {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-browse:hover {
            background: var(--primary-dark);
            color: white;
        }

        .cart-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-top: 20px;
        }

        .restaurant-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .restaurant-icon {
            width: 50px;
            height: 50px;
            background-color: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }

        .restaurant-name {
            flex-grow: 1;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .restaurant-time {
            color: #666;
            font-size: 0.9rem;
        }

        .cart-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            background-size: cover;
            background-position: center;
            margin-right: 15px;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .item-price {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
        }

        .quantity-btn {
            background: var(--secondary);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            font-weight: 600;
            margin: 0 10px;
        }

        .item-total {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            min-width: 70px;
        }

        .total-price {
            font-weight: 600;
            color: var(--primary);
        }

        .remove-item {
            color: #999;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
        }

        .remove-item:hover {
            color: #ff6b6b;
        }

        .cart-summary {
            background: var(--secondary);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-label {
            color: #666;
        }

        .summary-value {
            font-weight: 600;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }

        .total-label {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .total-value {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .checkout-button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 0;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkout-button:hover {
            background: var(--primary-dark);
        }

        .cart-actions {
            display: flex;
            margin-top: 15px;
        }

        .continue-shopping, .empty-cart {
            flex: 1;
            text-align: center;
            padding: 10px;
            text-decoration: none;
            font-weight: 600;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .continue-shopping {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
            margin-right: 10px;
        }

        .continue-shopping:hover {
            background: var(--primary-light);
            color: white;
        }

        .empty-cart {
            background: #f8f8f8;
            color: #ff6b6b;
            border: 1px solid #ff6b6b;
        }

        .empty-cart:hover {
            background: #ffe0e0;
        }

        /* Confirmación modal */
        .modal-content {
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--primary);
            color: white;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-footer {
            border-top: none;
        }

        .btn-secondary {
            background: #f8f8f8;
            color: var(--dark);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 600;
        }

        /* Bottom Navigation */
        .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        /* Fondo semitransparente con efecto vidrio */
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        
        /* Sombra suave hacia arriba, sin borde duro */
        border-top: none;
        box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
        
        padding: 0.5rem 1.5rem;
        /* Importante para iPhones con FaceID (evita que tape la barra negra inferior) */
        padding-bottom: max(10px, env(safe-area-inset-bottom));
        
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        border-radius: 20px 20px 0 0; /* Opcional: puntas redondeadas arriba */
    }

    /* Items normales */
    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #0165f0; /* Gris suave inactivo */
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        flex: 1;
        gap: 4px;
        position: relative;
    }

.nav-item .nav-icon {
    width: 24px;
    height: 24px;
    object-fit: contain;
    transition: all 0.3s ease;
    
    /* ESTO ES LO QUE TE FALTA: */
    /* Convertimos a escala de grises y bajamos la opacidad al 40% */
    /* Esto hace que un icono negro se vea gris claro */
    filter: grayscale(100%) brightness(0.6) opacity(0.7);
}

    .nav-item span {
        font-size: 0.65rem;
        font-weight: 500;
        transition: var(--transition);
    }

    /* Estado Activo (La magia de la animación) */
    .nav-item.active {
        color: var(--primary);
    }

.nav-item.active .nav-icon {
    transform: translateY(-2px);
    
    /* El "Filtro Mágico" que convierte el icono a azul #0165f0 */
    filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%);
    
    /* Importante: Restauramos la opacidad al 100% para que se vea vivo */
    opacity: 1;
}
    .nav-item.active span {
        font-weight: 700;
        color: var(--primary);
    }

    /* Efecto de punto debajo del activo (Estilo minimalista) */
    .nav-item.active::after {
        content: '';
        position: absolute;
        bottom: -5px; /* Ajustar según padding */
        width: 4px;
        height: 4px;
        background: var(--primary);
        border-radius: 50%;
    }

    /* BOTÓN CENTRAL FLOTANTE (EL CARRITO) */
    .central-btn {
        position: relative;
        /* Esto hace que suba */
        transform: translateY(-25px); 
        
        background: var(--primary); /* O un gradiente: var(--gradient) */
        width: 64px; /* Más grande */
        height: 64px;
        border-radius: 50%;
        
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Efecto rebote */
        
        /* El truco del "Recorte": Un borde grueso del mismo color que el fondo de la página */
        border: 5px solid var(--body-bg, #ffffff); /* Asegúrate que coincida con tu fondo */
        
        /* Sombra resplandeciente (Glow) */
        box-shadow: 0 10px 25px rgba(1, 101, 255, 0.4);
        z-index: 10;
    }

    .central-btn:active {
        transform: translateY(-22px) scale(0.95); /* Efecto al presionar */
        box-shadow: 0 5px 15px rgba(1, 101, 255, 0.3);
    }

    .central-btn .nav-icon {
        width: 28px;
        height: 28px;
        filter: brightness(0) invert(1); /* Blanco puro */
    }

    /* Hover effects (Solo PC) */
    @media (min-width: 768px) {
        .nav-item:hover {
            color: var(--primary);
        }
        .nav-item:hover .nav-icon {
            filter: grayscale(0%) opacity(0.8);
        }
        .central-btn:hover {
            transform: translateY(-30px) scale(1.05);
        }
    }

/* =======================================================
   MODO OSCURO - CARRITO.PHP
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

    .cart-item {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .item-name {
        color: #fff !important;
    }

    .item-price, .item-details {
        color: #aaa !important;
    }

    .quantity-btn {
        background: #222 !important;
        color: #fff !important;
        border-color: #444 !important;
    }

    .quantity-btn:hover {
        background: var(--primary) !important;
    }

    .remove-btn {
        color: #888 !important;
    }

    .remove-btn:hover {
        color: var(--danger) !important;
    }

    .cart-summary, .summary-card {
        background: #111111 !important;
        border-color: #333 !important;
    }

    .summary-row {
        color: #e0e0e0 !important;
        border-color: #333 !important;
    }

    .summary-total {
        color: #fff !important;
    }

    .empty-cart {
        color: #888 !important;
    }

    .empty-cart h3 {
        color: #fff !important;
    }

    /* Bottom Nav */
    .bottom-nav {
        background: rgba(0, 0, 0, 0.95) !important;
        border-top: 1px solid #333;
    }

    /* Iconos del navbar - BLANCOS */
    .nav-icon {
        filter: invert(1) brightness(2) !important;
    }

    .nav-item.active .nav-icon {
        filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%) !important;
    }

    .central-btn {
        border-color: #000 !important;
    }

    .central-btn .nav-icon {
        filter: brightness(0) invert(1) !important;
    }

    .nav-item span {
        color: #fff !important;
    }

    .nav-item.active span {
        color: var(--primary) !important;
    }
}

/* Soporte para data-theme="dark" y clase .dark-mode */
[data-theme="dark"] body,
html.dark-mode body {
    background-color: #000000 !important;
    color: #e0e0e0;
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

[data-theme="dark"] .cart-item,
html.dark-mode .cart-item {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .item-name,
html.dark-mode .item-name {
    color: #fff !important;
}

[data-theme="dark"] .item-price,
[data-theme="dark"] .item-details,
html.dark-mode .item-price,
html.dark-mode .item-details {
    color: #aaa !important;
}

[data-theme="dark"] .quantity-btn,
html.dark-mode .quantity-btn {
    background: #222 !important;
    color: #fff !important;
    border-color: #444 !important;
}

[data-theme="dark"] .cart-summary,
[data-theme="dark"] .summary-card,
html.dark-mode .cart-summary,
html.dark-mode .summary-card {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .summary-row,
html.dark-mode .summary-row {
    color: #e0e0e0 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .summary-total,
html.dark-mode .summary-total {
    color: #fff !important;
}

[data-theme="dark"] .bottom-nav,
html.dark-mode .bottom-nav {
    background: rgba(0, 0, 0, 0.95) !important;
    border-top: 1px solid #333;
}

[data-theme="dark"] .nav-icon,
html.dark-mode .nav-icon {
    filter: invert(1) brightness(2) !important;
}

[data-theme="dark"] .nav-item.active .nav-icon,
html.dark-mode .nav-item.active .nav-icon {
    filter: brightness(0) saturate(100%) invert(32%) sepia(91%) saturate(5453%) hue-rotate(210deg) brightness(100%) contrast(92%) !important;
}

[data-theme="dark"] .central-btn,
html.dark-mode .central-btn {
    border-color: #000 !important;
}

[data-theme="dark"] .central-btn .nav-icon,
html.dark-mode .central-btn .nav-icon {
    filter: brightness(0) invert(1) !important;
}

[data-theme="dark"] .nav-item span,
html.dark-mode .nav-item span {
    color: #fff !important;
}

[data-theme="dark"] .nav-item.active span,
html.dark-mode .nav-item.active span {
    color: var(--primary) !important;
}

/* ===== ESTILOS ADICIONALES MODO OSCURO ===== */
[data-theme="dark"] .cart-container,
html.dark-mode .cart-container {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .restaurant-info,
html.dark-mode .restaurant-info {
    background: #1a1a1a !important;
    border-color: #333 !important;
}

[data-theme="dark"] .restaurant-name,
html.dark-mode .restaurant-name {
    color: #fff !important;
}

[data-theme="dark"] .restaurant-time,
html.dark-mode .restaurant-time {
    color: #888 !important;
}

[data-theme="dark"] .item-image,
html.dark-mode .item-image {
    background-color: #222 !important;
}

[data-theme="dark"] .item-total,
html.dark-mode .item-total {
    color: #fff !important;
}

[data-theme="dark"] .total-price,
html.dark-mode .total-price {
    color: #fff !important;
}

[data-theme="dark"] .remove-item,
html.dark-mode .remove-item {
    color: #888 !important;
}

[data-theme="dark"] .remove-item:hover,
html.dark-mode .remove-item:hover {
    color: var(--danger) !important;
}

[data-theme="dark"] .cart-empty,
html.dark-mode .cart-empty {
    color: #888 !important;
}

[data-theme="dark"] .cart-empty h3,
html.dark-mode .cart-empty h3 {
    color: #fff !important;
}

[data-theme="dark"] .cart-empty i,
html.dark-mode .cart-empty i {
    color: #444 !important;
}

[data-theme="dark"] .cart-actions,
html.dark-mode .cart-actions {
    background: #111111 !important;
    border-color: #333 !important;
}

[data-theme="dark"] .quantity-input,
html.dark-mode .quantity-input {
    background: #1a1a1a !important;
    color: #fff !important;
    border-color: #444 !important;
}

[data-theme="dark"] .summary-label,
html.dark-mode .summary-label {
    color: #aaa !important;
}

[data-theme="dark"] .summary-value,
html.dark-mode .summary-value {
    color: #fff !important;
}

[data-theme="dark"] .total-label,
html.dark-mode .total-label {
    color: #fff !important;
}

[data-theme="dark"] .total-value,
html.dark-mode .total-value {
    color: #fff !important;
}

/* Media query backup */
@media (prefers-color-scheme: dark) {
    .cart-container {
        background: #111111 !important;
        border-color: #333 !important;
    }
    .restaurant-info {
        background: #1a1a1a !important;
        border-color: #333 !important;
    }
    .restaurant-name {
        color: #fff !important;
    }
    .item-total, .total-price {
        color: #fff !important;
    }
    .cart-empty {
        color: #888 !important;
    }
    .cart-empty h3 {
        color: #fff !important;
    }
    .quantity-input {
        background: #1a1a1a !important;
        color: #fff !important;
        border-color: #444 !important;
    }
    .summary-label {
        color: #aaa !important;
    }
    .summary-value, .total-label, .total-value {
        color: #fff !important;
    }
    .cart-actions {
        background: #111111 !important;
    }
}
    </style>
</head>
<body>
<?php include_once 'includes/valentine.php'; ?>
    <div class="container">
        <!-- Encabezado de página -->
        <div class="page-header">
            <a href="<?php echo !empty($carrito_items) && $negocio_id > 0 ? 'negocio.php?id=' . $negocio_id : 'index.php'; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Tu Carrito</h1>
        </div>

        <!-- Mostrar mensaje de error si existe -->
        <?php if (isset($_SESSION['cart_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 20px 0;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['cart_error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['cart_error']); ?>
        <?php endif; ?>
        
        <?php if (empty($carrito_items)): ?>
        <!-- Carrito vacío -->
        <div class="cart-empty">
            <i class="fas fa-shopping-cart"></i>
            <h3>Tu carrito está vacío</h3>
            <p>Parece que aún no has agregado ningún producto a tu carrito. Explora nuestros restaurantes y encuentra algo delicioso.</p>
            <a href="index.php" class="btn-browse">Explorar restaurantes</a>
        </div>
        <?php else: ?>
        <!-- Carrito con productos -->
        <div class="cart-container">
            <div class="restaurant-info">
                <div class="restaurant-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="restaurant-name">
                    <?php echo $negocio_nombre; ?>
                </div>
                <div class="restaurant-time">
                    <i class="fas fa-clock me-1"></i> 30-45 min
                </div>
            </div>
            
            <!-- Lista de productos en el carrito -->
            <?php foreach ($carrito_items as $key => $item): ?>
            <div class="cart-item">
                <div class="item-image" style="background-image: url('<?php echo isset($item['imagen']) && $item['imagen'] ? $item['imagen'] : 'assets/img/products/default.jpg'; ?>');"></div>
                <div class="item-details">
                    <div class="item-name"><?php echo isset($item['nombre']) ? $item['nombre'] : 'Producto'; ?></div>
                    <div class="item-price">$<?php echo isset($item['precio']) ? number_format($item['precio'], 2) : '0.00'; ?> c/u</div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="actualizarCantidad(<?php echo isset($item['id_producto']) ? $item['id_producto'] : 0; ?>, <?php echo isset($item['cantidad']) ? ($item['cantidad'] - 1) : 0; ?>)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-input" value="<?php echo isset($item['cantidad']) ? $item['cantidad'] : 1; ?>" min="1" max="99" onchange="actualizarCantidad(<?php echo isset($item['id_producto']) ? $item['id_producto'] : 0; ?>, this.value)">
                        <button class="quantity-btn" onclick="actualizarCantidad(<?php echo isset($item['id_producto']) ? $item['id_producto'] : 0; ?>, <?php echo isset($item['cantidad']) ? ($item['cantidad'] + 1) : 2; ?>)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="item-total">
                    <div class="total-price">$<?php echo isset($item['precio']) && isset($item['cantidad']) ? number_format($item['precio'] * $item['cantidad'], 2) : '0.00'; ?></div>
                    <a href="carrito.php?action=eliminar&id_producto=<?php echo isset($item['id_producto']) ? $item['id_producto'] : 0; ?>" class="remove-item">Eliminar</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Resumen del carrito -->
        <div class="cart-summary">
            <div class="summary-row">
                <div class="summary-label">Subtotal</div>
                <div class="summary-value">$<?php echo number_format($subtotal, 2); ?></div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Costo de envío</div>
                <div class="summary-value">$<?php echo number_format($costo_envio, 2); ?></div>
            </div>
            <div class="summary-total">
                <div class="total-label">Total</div>
                <div class="total-value">$<?php echo number_format($total, 2); ?></div>
            </div>
            
            <button class="checkout-button" onclick="window.location.href='checkout.php'">
                Proceder al pago
            </button>
            
            <div class="cart-actions">
                <a href="<?php echo $negocio_id > 0 ? 'negocio.php?id=' . $negocio_id : 'index.php'; ?>" class="continue-shopping">
                    Seguir comprando
                </a>
                <a href="carrito.php?action=vaciar" class="empty-cart">
                    Vaciar carrito
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Navegación inferior -->
  <nav class="bottom-nav">
    <a href="index.php" class="nav-item  qb-transition">
        <img src="assets/icons/home.png" alt="Inicio" class="nav-icon">
        <span>Inicio</span>
    </a>
    <a href="buscar.php" class="nav-item qb-transition">
        <img src="assets/icons/search.png" alt="Buscar" class="nav-icon">
        <span>Buscar</span>
    </a>
    <a href="<?php echo $usuario_logueado ? 'carrito.php' : 'login.php'; ?>" class="central-btn active qb-transition">
        <img src="assets/icons/cart.png" alt="Carrito" class="nav-icon">
    </a>
    <a href="<?php echo $usuario_logueado ? 'favoritos.php' : 'login.php'; ?>" class="nav-item qb-transition">
        <img src="assets/icons/fav.png" alt="Favoritos" class="nav-icon">
        <span>Favoritos</span>
    </a>
    <a href="<?php echo $usuario_logueado ? 'perfil.php' : 'login.php'; ?>" class="nav-item qb-transition">
        <img src="assets/icons/user.png" alt="Perfil" class="nav-icon">
        <span>Perfil</span>
    </a>
</nav>
    
    <!-- Modal de confirmación para cambiar de restaurante -->
    <?php if (isset($mensaje_confirmacion)): ?>
    <div class="modal fade" id="cambiarRestauranteModal" tabindex="-1" aria-labelledby="cambiarRestauranteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cambiarRestauranteModalLabel">Cambiar de restaurante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo $mensaje_confirmacion; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form action="carrito.php?action=agregar" method="POST">
                        <input type="hidden" name="id_producto" value="<?php echo isset($_SESSION['temp_product']['id_producto']) ? $_SESSION['temp_product']['id_producto'] : 0; ?>">
                        <input type="hidden" name="cantidad" value="<?php echo isset($_SESSION['temp_product']['cantidad']) ? $_SESSION['temp_product']['cantidad'] : 1; ?>">
                        <input type="hidden" name="confirmar_cambio" value="1">
                        <input type="hidden" name="redirect" value="<?php echo isset($_SESSION['temp_product']['redirect']) ? $_SESSION['temp_product']['redirect'] : 'carrito.php'; ?>">
                        <button type="submit" class="btn btn-primary">Cambiar restaurante</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para actualizar la cantidad de un producto
        function actualizarCantidad(idProducto, cantidad) {
            // Asegurarse de que la cantidad sea al menos 0
            cantidad = Math.max(0, parseInt(cantidad));
            
            // Enviar solicitud AJAX para actualizar la cantidad
            $.ajax({
                url: 'carrito.php?action=actualizar',
                type: 'POST',
                data: {
                    id_producto: idProducto,
                    cantidad: cantidad
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Si la cantidad es 0, recargar la página para eliminar el elemento
                        if (cantidad === 0 || response.carrito_vacio) {
                            window.location.reload();
                        } else {
                            // Actualizar la UI con los nuevos valores
                            window.location.reload();
                        }
                    }
                },
                error: function() {
                    console.error('Error al actualizar el carrito');
                    window.location.reload(); // Recargar la página en caso de error
                }
            });
        }
        
        // Mostrar modal de confirmación si existe
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($mensaje_confirmacion)): ?>
            var myModal = new bootstrap.Modal(document.getElementById('cambiarRestauranteModal'));
            myModal.show();
            <?php endif; ?>
        });
    </script>
    <script src="assets/js/transitions.js"></script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>