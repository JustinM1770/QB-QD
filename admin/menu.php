<?php
// Configurar reporte de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Usar rutas absolutas basadas en la raíz del documento para evitar problemas
$document_root = $_SERVER['DOCUMENT_ROOT'];

require_once $document_root . '/config/database.php';
require_once $document_root . '/models/Usuario.php';
require_once $document_root . '/models/Negocio.php';
require_once $document_root . '/models/Producto.php';
require_once $document_root . '/models/CategoriaProducto.php';
require_once $document_root . '/models/ElegibleProducto.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado y es un negocio
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/menu.php");
    exit;
}

// Si está logueado, obtener información del usuario y su negocio
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

$negocio = new Negocio($db);
$negocios = $negocio->obtenerPorIdPropietario($usuario->id_usuario);

// Verificar si el usuario tiene un negocio registrado
if (empty($negocios)) {
    header("Location: negocio_configuracion.php?mensaje=Debes registrar tu negocio primero");
    exit;
}

$negocio_info = $negocios[0];
$negocio->id_negocio = $negocio_info['id_negocio'];

// Inicializar modelos
$producto = new Producto($db);
$categoria = new CategoriaProducto($db);
$elegible = new ElegibleProducto($db);

// Obtener categorías de productos
$categorias_producto = $categoria->obtenerPorNegocio($negocio->id_negocio);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$categorias_alimentos = [
    'tés', 'taquería', 'sushi', 'snack', 'restauran', 'repostería', 
    'pizzería', 'parrilla', 'panad', 'mariscos', 'heladería', 
    'hamburgues', 'frutería', 'vegana', 'china', 'café', 'bebidas'
];

// Inicializar flag para mostrar calorías
$mostrar_calorias = false;

// Verificar si el negocio pertenece a categorías de alimentos
if (isset($negocio_info['categoria_negocio']) && !empty($negocio_info['categoria_negocio'])) {
    $categoria_negocio = strtolower(trim($negocio_info['categoria_negocio']));
    
    // Verificar si la categoría del negocio coincide con alguna categoría de alimentos
    foreach ($categorias_alimentos as $cat_alimento) {
        if (strpos($categoria_negocio, $cat_alimento) !== false) {
            $mostrar_calorias = true;
            break;
        }
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Error: Token CSRF inválido');
    }

    // Procesar formulario para añadir/editar productos
// Procesar formulario para añadir/editar productos
if (isset($_POST['action']) && $_POST['action'] == 'guardar_producto') {
    $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $nombre_producto = trim($_POST['nombre_producto']);
    $descripcion_producto = trim($_POST['descripcion_producto']);
    $precio = (float)$_POST['precio'];
    $categoria_id = (int)$_POST['categoria_producto'];
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    
    // Procesar calorías - validación mejorada
    $calorias = null;
    if (isset($_POST['calorias']) && $_POST['calorias'] !== '') {
        $calorias_temp = filter_var($_POST['calorias'], FILTER_VALIDATE_INT);
        if ($calorias_temp !== false && $calorias_temp >= 0) {
            $calorias = $calorias_temp;
        }
    }
    
    // Validar campos
    $errores = [];
    
    if (empty($nombre_producto)) {
        $errores[] = "El nombre del producto es obligatorio.";
    }
    
    if ($precio <= 0) {
        $errores[] = "El precio debe ser mayor que cero.";
    }
    
    // Validar calorías si se proporcionaron
    if ($calorias !== null && $calorias < 0) {
        $errores[] = "Las calorías no pueden ser negativas.";
    }
    
    if (empty($errores)) {
        $producto->id_producto = $producto_id;
        $producto->id_negocio = $negocio->id_negocio;
        $producto->id_categoria = $categoria_id;
        $producto->nombre = $nombre_producto;
        $producto->descripcion = $descripcion_producto;
        $producto->precio = $precio;
        $producto->calorias = $calorias; // ¡Importante! Asignar las calorías al objeto
        $producto->disponible = $disponible;
        $producto->destacado = $destacado;
        
        echo "<!-- Debug: Objeto producto antes de guardar - Calorías: " . ($producto->calorias ?? 'NULL') . " -->";
        
        // Procesar imagen del producto
        if (isset($_FILES['imagen_producto']) && $_FILES['imagen_producto']['error'] === UPLOAD_ERR_OK) {
            $ruta_imagen = procesarImagen($_FILES['imagen_producto'], 'producto');
            if ($ruta_imagen) {
                $producto->imagen = $ruta_imagen;
            }
        }
        
        // Guardar o actualizar producto
        if ($producto_id > 0) {
            $resultado = $producto->actualizar();
            $mensaje_producto = $resultado ? "Producto actualizado correctamente." : "Error al actualizar el producto.";
        } else {
            $resultado = $producto->crear();
            $mensaje_producto = $resultado ? "Producto añadido correctamente." : "Error al añadir el producto.";
        }
        
        if (!$resultado) {
            $mensaje_error_producto = "Ha ocurrido un error al procesar el producto. Por favor, intenta de nuevo.";
        }
    } else {
        $mensaje_error_producto = implode("<br>", $errores);
    }
}
    
    // Procesar formulario para eliminar producto
    if (isset($_POST['action']) && $_POST['action'] == 'eliminar_producto') {
        $producto_id = (int)$_POST['producto_id'];
        $producto->id_producto = $producto_id;
        $producto->id_negocio = $negocio->id_negocio; // Importante para seguridad
        
        // Verificar que el producto pertenezca al negocio actual
        $producto_info = $producto->obtenerPorId($producto_id);
        
        if ($producto_info && $producto_info['id_negocio'] == $negocio->id_negocio) {
            if ($producto->eliminar()) {
                $mensaje_producto = "Producto eliminado correctamente.";
            } else {
                $mensaje_error_producto = "Error al eliminar el producto.";
            }
        } else {
            $mensaje_error_producto = "No tienes permiso para eliminar este producto.";
        }
    }
    
    // Procesar formulario para añadir/editar categoría
    if (isset($_POST['action']) && $_POST['action'] == 'guardar_categoria') {
        $categoria_id = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
        $nombre_categoria = trim($_POST['nombre_categoria']);
        $descripcion_categoria = trim($_POST['descripcion_categoria']);
        
        // Validar campos
        $errores = [];
        
        if (empty($nombre_categoria)) {
            $errores[] = "El nombre de la categoría es obligatorio.";
        }
        
        if (empty($errores)) {
            $categoria->id_categoria = $categoria_id;
            $categoria->id_negocio = $negocio->id_negocio;
            $categoria->nombre = $nombre_categoria;
            $categoria->descripcion = $descripcion_categoria;
            
            // Guardar o actualizar categoría
            if ($categoria_id > 0) {
                $resultado = $categoria->actualizar();
                $mensaje_categoria = $resultado ? "Categoría actualizada correctamente." : "Error al actualizar la categoría.";
            } else {
                $resultado = $categoria->crear();
                $mensaje_categoria = $resultado ? "Categoría añadida correctamente." : "Error al añadir la categoría.";
            }
            
            if ($resultado) {
                // Recargar categorías
                $categorias_producto = $categoria->obtenerPorNegocio($negocio->id_negocio);
            } else {
                $mensaje_error_categoria = "Ha ocurrido un error al procesar la categoría. Por favor, intenta de nuevo.";
            }
        } else {
            $mensaje_error_categoria = implode("<br>", $errores);
        }
    }
    
    // Procesar formulario para eliminar categoría
    if (isset($_POST['action']) && $_POST['action'] == 'eliminar_categoria') {
        $categoria_id = (int)$_POST['categoria_id'];
        $categoria->id_categoria = $categoria_id;
        $categoria->id_negocio = $negocio->id_negocio; // Importante para seguridad
        
        // Verificar que la categoría pertenezca al negocio actual
        $categoria_info = $categoria->obtenerPorId($categoria_id);
        
        if ($categoria_info && $categoria_info['id_negocio'] == $negocio->id_negocio) {
            if ($categoria->eliminar()) {
                $mensaje_categoria = "Categoría eliminada correctamente.";
                // Recargar categorías
                $categorias_producto = $categoria->obtenerPorNegocio($negocio->id_negocio);
            } else {
                $mensaje_error_categoria = "Error al eliminar la categoría. Posiblemente tenga productos asociados.";
            }
        } else {
            $mensaje_error_categoria = "No tienes permiso para eliminar esta categoría.";
        }
    }
    if (isset($_POST['action']) && $_POST['action'] == 'gestionar_elegibles') {
        $producto_id = (int)$_POST['producto_id'];
        $tiene_elegibles = isset($_POST['tiene_elegibles']) ? 1 : 0;
        $permite_texto_producto = isset($_POST['permite_texto_producto']) ? 1 : 0;
        $permite_mensaje_tarjeta = isset($_POST['permite_mensaje_tarjeta']) ? 1 : 0;
        $limite_texto_producto = (int)($_POST['limite_texto_producto'] ?? 50);

        // Verificar que el producto pertenezca al negocio actual
        $producto_info = $producto->obtenerPorId($producto_id);

        if ($producto_info && $producto_info['id_negocio'] == $negocio->id_negocio) {
            // Actualizar campos de personalización
            $query_update = "UPDATE productos SET tiene_elegibles = ?, permite_texto_producto = ?, permite_mensaje_tarjeta = ?, limite_texto_producto = ? WHERE id_producto = ?";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(1, $tiene_elegibles);
            $stmt_update->bindParam(2, $permite_texto_producto);
            $stmt_update->bindParam(3, $permite_mensaje_tarjeta);
            $stmt_update->bindParam(4, $limite_texto_producto);
            $stmt_update->bindParam(5, $producto_id);
            
            if ($stmt_update->execute()) {
                if ($tiene_elegibles == 1) {
                    // Procesar elegibles enviados
                    $elegibles_nombres = $_POST['elegibles_nombres'] ?? [];
                    $elegibles_precios = $_POST['elegibles_precios'] ?? [];
                    $elegibles_disponibles = $_POST['elegibles_disponibles'] ?? [];
                    $elegibles_ids = $_POST['elegibles_ids'] ?? [];
                    
                    // Eliminar elegibles que ya no están en la lista
                    $elegibles_actuales = $elegible->obtenerPorProducto($producto_id);
                    foreach ($elegibles_actuales as $elegible_actual) {
                        if (!in_array($elegible_actual['id_elegible'], $elegibles_ids)) {
                            $elegible->id_elegible = $elegible_actual['id_elegible'];
                            $elegible->id_producto = $producto_id;
                            $elegible->eliminar();
                        }
                    }
                    
                    // Procesar elegibles nuevos y existentes
                    for ($i = 0; $i < count($elegibles_nombres); $i++) {
                        if (!empty(trim($elegibles_nombres[$i]))) {
                            $elegible->id_producto = $producto_id;
                            $elegible->nombre = trim($elegibles_nombres[$i]);
                            $elegible->precio_adicional = (float)($elegibles_precios[$i] ?? 0);
                            $elegible->disponible = isset($elegibles_disponibles[$i]) ? 1 : 0;
                            $elegible->orden = $i + 1;
                            
                            if (isset($elegibles_ids[$i]) && !empty($elegibles_ids[$i])) {
                                // Actualizar elegible existente
                                $elegible->id_elegible = (int)$elegibles_ids[$i];
                                $elegible->actualizar();
                            } else {
                                // Crear nuevo elegible
                                $elegible->crear();
                            }
                        }
                    }
                    
                    $mensaje_producto = "Elegibles actualizados correctamente.";
                } else {
                    // Si no tiene elegibles, eliminar todos los elegibles existentes
                    $elegible->eliminarPorProducto($producto_id);
                    $mensaje_producto = "Elegibles eliminados correctamente.";
                }
            } else {
                $mensaje_error_producto = "Error al actualizar el producto.";
            }
        } else {
            $mensaje_error_producto = "No tienes permiso para modificar este producto.";
        }
    }

}

// Obtener productos del negocio
$productos = $producto->obtenerPorNegocio($negocio->id_negocio);
foreach ($productos as &$prod) {
    if ($prod['tiene_elegibles']) {
        $prod['elegibles'] = $elegible->obtenerPorProducto($prod['id_producto']);
    }
}
unset($prod);

    // Función para procesar imágenes cargadas
    function procesarImagen($archivo, $tipo) {
        // Usar directorio dentro del proyecto
        $dir_uploads = $_SERVER['DOCUMENT_ROOT'] . '/uploads/productos/';
        
        // Verificar configuración de subida de PHP
        if (!ini_get('file_uploads')) {
            error_log('File uploads are disabled in PHP configuration');
            return false;
        }

        // Verificar errores de subida
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            error_log('Upload failed with error code: ' . $archivo['error']);
            return false;
        }

        // Crear directorio si no existe
        if (!file_exists($dir_uploads)) {
            if (!mkdir($dir_uploads, 0775, true)) {
                error_log('Failed to create product image directory');
                return false;
            }
            chown($dir_uploads, '_www');
            chgrp($dir_uploads, 'staff');
        }

        // Verificar permisos del directorio
        if (!is_writable($dir_uploads)) {
            error_log('Product image directory is not writable');
            return false;
        }

        // Generar nombre único para la imagen
        $nombre_archivo = uniqid($tipo . '_') . '_' . basename($archivo["name"]);
        $ruta_completa = $dir_uploads . $nombre_archivo;

        // Verificar tipo de archivo (solo permitir imágenes)
        $tipo_archivo = strtolower(pathinfo($ruta_completa, PATHINFO_EXTENSION));
        if (!in_array($tipo_archivo, ['jpg', 'jpeg', 'png'])) {
            error_log('Invalid file type: ' . $tipo_archivo);
            return false;
        }

        // Mover el archivo
        if (move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
            return '/uploads/productos/' . $nombre_archivo; // Ruta accesible via web
        } else {
            error_log('Failed to move uploaded file to: ' . $ruta_completa);
            return false;
        }
}

// Agrupar productos por categoría
$productos_por_categoria = [];

// Primero agregar "Sin categoría" si hay productos sin categoría
$productos_sin_categoria = [];
foreach ($productos as $prod) {
    if (empty($prod['id_categoria'])) {
        $productos_sin_categoria[] = $prod;
    }
}

if (!empty($productos_sin_categoria)) {
    $productos_por_categoria[0] = [
        'nombre' => 'Sin categoría',
        'productos' => $productos_sin_categoria
    ];
}

// Luego agregar los productos con categoría
foreach ($categorias_producto as $cat) {
    $productos_en_categoria = [];
    foreach ($productos as $prod) {
        if ($prod['id_categoria'] == $cat['id_categoria']) {
            $productos_en_categoria[] = $prod;
        }
    }
    
    if (!empty($productos_en_categoria)) {
        $productos_por_categoria[$cat['id_categoria']] = [
            'nombre' => $cat['nombre'],
            'productos' => $productos_en_categoria
        ];
    }
}

// Si no hay productos agrupados por categoría y hay productos, agregar todos a "Sin categoría"
if (empty($productos_por_categoria) && !empty($productos)) {
    $productos_por_categoria[0] = [
        'nombre' => 'Sin categoría',
        'productos' => $productos
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Gestión de Menú - QuickBite</title>
    <!-- Responsive Design Global -->
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <!-- Fonts: Inter and DM Sans -->
   <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;         
            --primary-light: #E3F2FD;   
            --primary-dark: #0153CC;    
            --secondary: #F8F8F8;
            --accent: #FF9500;          
            --accent-light: #FFE1AE;    
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --medium-gray: #888;
            --light-gray: #E8E8E8;
            --danger: #FF4D4D;
            --success: #4CAF50;
            --warning: #FFC107;
            --sidebar-width: 280px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #f0f4f8 100%);
            min-height: 100vh;
            display: flex;
            color: var(--dark);
            margin: 0;
            padding: 0;
            line-height: 1.6;
            overflow-x: hidden;
            width: 100%;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            line-height: 1.3;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
            height: 100vh;
            position: fixed;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(1, 101, 255, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
        }

        .sidebar-brand {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .sidebar-brand:hover {
            transform: scale(1.02);
        }

        .sidebar-brand i {
            margin-right: 15px;
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-light) transparent;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 2px;
        }

        .menu-section {
            padding: 0 20px;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--medium-gray);
        }

        .menu-item {
            padding: 14px 25px;
            display: flex;
            align-items: center;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            margin: 2px 10px;
            border-radius: 12px;
        }

        .menu-item i {
            margin-right: 15px;
            font-size: 1.1rem;
            color: var(--medium-gray);
            transition: var(--transition);
            width: 20px;
            text-align: center;
        }

        .menu-item:hover {
            background: linear-gradient(135deg, var(--primary-light), rgba(1, 101, 255, 0.1));
            color: var(--primary);
            transform: translateX(5px);
        }

        .menu-item:hover i {
            color: var(--primary);
            transform: scale(1.1);
        }

        .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 600;
            box-shadow: var(--shadow-md);
        }

        .menu-item.active i {
            color: white;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            box-shadow: var(--shadow-sm);
        }

        .user-details {
            flex-grow: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin: 0;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--medium-gray);
            margin: 0;
        }

        .logout-btn {
            color: var(--medium-gray);
            margin-left: 15px;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 8px;
            border-radius: 8px;
        }

        .logout-btn:hover {
            color: var(--danger);
            background: rgba(255, 77, 77, 0.1);
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px;
            position: relative;
            min-height: 100vh;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--dark);
            background: linear-gradient(135deg, var(--dark), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-description {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 600px;
        }

        /* Card styles */
        .content-card {
            background: linear-gradient(135deg, white 0%, #fafbff 100%);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 25px;
            margin-bottom: 30px;
            transition: var(--transition);
            border: 1px solid rgba(1, 101, 255, 0.1);
            position: relative;
            overflow: visible;
            width: 100%;
            max-width: 100%;
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .content-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .card-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title i {
            margin-right: 15px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Productos */
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            width: 100%;
            max-width: 100%;
        }
        
        /* Ajuste para pantallas pequeñas */
        @media (max-width: 768px) {
            .productos-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .productos-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (min-width: 1400px) {
            .productos-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }

        .producto-card {
            background: linear-gradient(135deg, white 0%, #fafbff 100%);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(1, 101, 255, 0.1);
            width: 100%;
            max-width: 100%;
        }

        .producto-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .producto-imagen {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .producto-imagen-placeholder {
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--medium-gray);
            height: 100%;
        }

        .producto-imagen-placeholder i {
            font-size: 3rem;
            opacity: 0.5;
            background: linear-gradient(135deg, var(--medium-gray), var(--light-gray));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .producto-status {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .producto-contenido {
            padding: 20px;
        }

        .producto-nombre {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .producto-precio {
            font-weight: 700;
            font-size: 1.3rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .producto-descripcion {
            color: var(--medium-gray);
            margin-bottom: 20px;
            font-size: 0.9rem;
            max-height: 80px;
            overflow: hidden;
            line-height: 1.6;
        }

        .producto-actions {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid var(--light-gray);
            padding-top: 15px;
            gap: 10px;
        }
        
        @media (max-width: 576px) {
            .producto-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .producto-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
            outline: none;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 0.2rem;
            accent-color: var(--primary);
        }

        .form-check-label {
            margin-left: 8px;
        }

        .form-text {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-top: 8px;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            margin-bottom: 15px;
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--medium-gray);
            overflow: hidden;
            border: 2px dashed var(--light-gray);
            transition: var(--transition);
        }

        .image-preview:hover {
            border-color: var(--primary);
        }

        .image-preview i {
            font-size: 3rem;
            opacity: 0.5;
            background: linear-gradient(135deg, var(--medium-gray), var(--light-gray));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary i {
            margin-right: 10px;
        }

        .btn-outline-primary {
            border: 1px solid var(--primary);
            color: var(--primary);
            background-color: transparent;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-outline-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(1, 101, 255, 0.1), transparent);
            transition: left 0.5s;
        }

        .btn-outline-primary:hover::before {
            left: 100%;
        }

        .btn-outline-primary:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-primary i {
            margin-right: 10px;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #d32f2f);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-danger::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-danger:hover::before {
            left: 100%;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-danger i {
            margin-right: 10px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--medium-gray), #666);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Badge */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
        }

        .badge-success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.2), rgba(76, 175, 80, 0.1));
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .badge-danger {
            background: linear-gradient(135deg, rgba(255, 77, 77, 0.2), rgba(255, 77, 77, 0.1));
            color: var(--danger);
            border: 1px solid rgba(255, 77, 77, 0.3);
        }

        .badge-warning {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.2), rgba(255, 193, 7, 0.1));
            color: var(--warning);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .badge-primary {
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.2), rgba(1, 101, 255, 0.1));
            color: var(--primary);
            border: 1px solid rgba(1, 101, 255, 0.3);
        }

        /* Alert messages */
        .alert {
            padding: 20px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow-sm);
            animation: slideIn 0.3s ease;
        }
        .producto-elegibles {
    background: linear-gradient(135deg, rgba(1, 101, 255, 0.1), rgba(1, 101, 255, 0.05));
    border-radius: 6px;
    padding: 8px 12px;
    border-left: 3px solid var(--primary);
}

.elegible-item {
    background: linear-gradient(135deg, white 0%, #fafbff 100%);
    border: 1px solid var(--light-gray);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    position: relative;
    transition: var(--transition);
}

.elegible-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.elegible-item .btn-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: 0.75rem;
}

.elegible-drag-handle {
    cursor: move;
    color: var(--medium-gray);
    margin-right: 10px;
    display: flex;
    align-items: center;
}

.elegible-drag-handle:hover {
    color: var(--primary);
}

@media (max-width: 768px) {
    .elegible-item .row > div {
        margin-bottom: 10px;
    }
    
    .elegible-item .btn-remove {
        position: static;
        margin-top: 10px;
        width: 100%;
        border-radius: 6px;
    }
}

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            margin-right: 15px;
            font-size: 1.3rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.15), rgba(76, 175, 80, 0.1));
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(255, 77, 77, 0.15), rgba(255, 77, 77, 0.1));
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Modal */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-bottom: none;
            padding: 25px 30px;
        }

        .modal-title {
            font-weight: 700;
        }

        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
            background: white;
        }


        .modal-footer {
            padding: 25px 30px;
            border-top: 1px solid var(--light-gray);
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, white 0%, #fafbff 100%);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(1, 101, 255, 0.1);
        }

        .empty-state i {
            font-size: 5rem;
            background: linear-gradient(135deg, var(--light-gray), #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }

        .empty-state h4 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .empty-state p {
            color: var(--medium-gray);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        /* Toggle Sidebar Button */
        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1050;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            width: 50px;
            height: 50px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(1, 101, 255, 0.3);
            cursor: pointer;
            font-size: 1.4rem;
            color: white;
            border: none;
            transition: var(--transition);
        }

        .toggle-sidebar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(1, 101, 255, 0.4);
        }
        
        .toggle-sidebar:active {
            transform: scale(0.95);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .productos-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 992px) {
            :root {
                --sidebar-width: 280px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                width: var(--sidebar-width);
                left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 20px 15px;
            }
            
            .toggle-sidebar {
                display: flex !important;
                position: fixed;
                z-index: 999;
            }
            
            .page-header {
                margin-top: 60px;
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .productos-grid {
                grid-template-columns: 1fr !important;
                gap: 20px;
            }
            
            .producto-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .producto-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            
            .modal-body {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .page-header {
                padding: 15px;
                margin-top: 70px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .page-description {
                font-size: 0.9rem;
            }
            
            .content-card {
                padding: 20px;
            }
            
            .card-title {
                font-size: 1.1rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .productos-grid {
                gap: 15px;
            }
            
            .producto-contenido {
                padding: 15px;
            }
            
            .empty-state {
                padding: 60px 15px;
            }
            
            .empty-state i {
                font-size: 4rem;
            }
            
            .empty-state h4 {
                font-size: 1.3rem;
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            .modal-footer {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-footer .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .toggle-sidebar {
                top: 15px;
                left: 15px;
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
            
            .main-content {
                padding: 10px 8px;
            }
            
            .page-header {
                padding: 12px;
                margin-top: 75px;
            }
            
            .content-card {
                padding: 15px;
            }
            
            .productos-grid {
                gap: 12px;
            }
            
            .producto-contenido {
                padding: 12px;
            }
            
            .producto-nombre {
                font-size: 1.1rem;
                margin-bottom: 8px;
            }
            
            .producto-precio {
                font-size: 1.2rem;
                margin-bottom: 12px;
            }
            
            .producto-descripcion {
                font-size: 0.85rem;
                margin-bottom: 15px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .btn-primary, .btn-outline-primary, .btn-danger, .btn-secondary {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .empty-state {
                padding: 40px 10px;
            }
            
            .empty-state i {
                font-size: 3rem;
                margin-bottom: 20px;
            }
            
            .empty-state h4 {
                font-size: 1.2rem;
                margin-bottom: 10px;
            }
            
            .empty-state p {
                font-size: 0.9rem;
            }
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        @media (max-width: 992px) {
            .sidebar-overlay {
                display: block;
            }
        }

        /* Enhanced animations and micro-interactions */
        .content-card:hover .card-title i {
            transform: scale(1.1);
        }

        .producto-card:hover .producto-precio {
            transform: scale(1.05);
        }

        .menu-item::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 15px;
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            transform: translateY(-50%) scale(0);
            transition: var(--transition);
        }

        .menu-item.active::after {
            transform: translateY(-50%) scale(1);
        }

        .btn-primary:active,
        .btn-outline-primary:active,
        .btn-danger:active,
        .btn-secondary:active {
            transform: translateY(-1px) scale(0.98);
        }

        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--light-gray);
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success and error states */
        .success-animation {
            animation: successPulse 0.6s ease;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); background-color: rgba(76, 175, 80, 0.1); }
            100% { transform: scale(1); }
        }

        .error-animation {
            animation: errorShake 0.6s ease;
        }

        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Improved focus states for accessibility */
        .btn-primary:focus,
        .btn-outline-primary:focus,
        .btn-danger:focus,
        .btn-secondary:focus,
        .menu-item:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Input group styles */
        .input-group {
            position: relative;
            display: flex;
            align-items: stretch;
        }

        .input-group-text {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--medium-gray);
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
            border: 1px solid var(--light-gray);
            border-right: none;
            border-radius: 8px 0 0 8px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }

        .input-group .form-control:focus {
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
        }

        /* Enhanced form elements */
        .form-check {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
        }

        .form-check-label {
            margin-left: 0;
            color: var(--dark);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .form-check-label:hover {
            color: var(--primary);
        }

        /* Enhanced select styling */
        select.form-control {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%23666' d='m2 0-2 2h4zm0 5 2-2h-4z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px 12px;
            appearance: none;
            padding-right: 40px;
        }

        /* Enhanced textarea */
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
            line-height: 1.6;
        }

        /* File input styling */
        input[type="file"].form-control {
            padding: 8px 12px;
            border: 2px dashed var(--light-gray);
            background: linear-gradient(135deg, var(--secondary), #f0f4f8);
            transition: var(--transition);
            cursor: pointer;
        }

        input[type="file"].form-control:hover {
            border-color: var(--primary);
            background: linear-gradient(135deg, var(--primary-light), rgba(1, 101, 255, 0.05));
        }

        input[type="file"].form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 101, 255, 0.1);
        }

        /* Print styles */
        @media print {
            .sidebar,
            .toggle-sidebar,
            .page-header div:last-child,
            .producto-actions,
            .modal {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }
            
            .page-header {
                margin-bottom: 20px !important;
                padding: 0 !important;
                background: none !important;
                box-shadow: none !important;
            }
            
            .content-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
                page-break-inside: avoid;
            }
            
            .productos-grid {
                display: block !important;
            }
            
            .producto-card {
                display: inline-block !important;
                width: 48% !important;
                margin: 1% !important;
                page-break-inside: avoid;
            }
        }

        /* Custom scrollbar for better UX */
        .modal-body::-webkit-scrollbar,
        .content-card::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track,
        .content-card::-webkit-scrollbar-track {
            background: var(--secondary);
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb,
        .content-card::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 3px;
            transition: var(--transition);
        }

        .modal-body::-webkit-scrollbar-thumb:hover,
        .content-card::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Improved visual hierarchy */
        .card-title .btn {
            font-size: 0.85rem;
            padding: 8px 16px;
        }

        .producto-actions .btn {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            flex: 1;
            text-align: center;
        }

        /* Enhanced gradient effects */
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--primary));
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        /* Better spacing for mobile */
        @media (max-width: 576px) {
            .page-header > div:first-child {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .page-header > div:last-child {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .page-header .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* ========================================
           ESTILOS OPCIONES DINÁMICAS
           ======================================== */
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        }
        
        .grupo-opciones-card {
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .grupo-opciones-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .grupo-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
        }
        
        .grupo-drag-handle {
            color: var(--medium-gray);
            font-size: 1.2rem;
            margin-right: 15px;
            cursor: move;
        }
        
        .grupo-drag-handle:hover {
            color: var(--primary);
        }
        
        .grupo-info {
            flex: 1;
        }
        
        .grupo-titulo {
            margin: 0;
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .grupo-actions {
            display: flex;
            gap: 8px;
        }
        
        .grupo-toggle-icon {
            transition: transform 0.3s ease;
        }
        
        .grupo-body {
            padding: 20px;
        }
        
        .grupo-form {
            background: linear-gradient(135deg, rgba(1, 101, 255, 0.05), rgba(1, 101, 255, 0.02));
            border-radius: 8px;
            padding: 15px;
            border: 1px solid rgba(1, 101, 255, 0.1);
        }
        
        .opciones-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed var(--light-gray);
        }
        
        .opciones-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .opcion-item {
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #ffffff, #fafbff);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .opcion-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(1, 101, 255, 0.1);
        }
        
        .opcion-drag-handle {
            color: var(--medium-gray);
            margin-right: 12px;
            cursor: move;
            font-size: 1rem;
        }
        
        .opcion-drag-handle:hover {
            color: var(--primary);
        }
        
        .opcion-content {
            flex: 1;
        }
        
        .modal-xl {
            max-width: 1200px;
        }
        
        @media (max-width: 768px) {
            .grupo-header {
                flex-wrap: wrap;
            }
            
            .grupo-info {
                flex-basis: 100%;
                margin-bottom: 10px;
            }
            
            .grupo-actions {
                flex-wrap: wrap;
                width: 100%;
            }
            
            .grupo-actions .btn {
                flex: 1;
            }
        }
    
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="sidebar-brand">
                <i class="fas fa-utensils"></i>
                QuickBite
            </a>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">PRINCIPAL</div>
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
            <a href="pedidos.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                Pedidos
            </a>

            <div class="menu-section">MENÚ Y OFERTAS</div>
            <a href="menu.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                Menú
            </a>
            <a href="categorias.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                Categorías
            </a>
            <a href="promociones.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'promociones.php' ? 'active' : ''; ?>">
                <i class="fas fa-percent"></i>
                Promociones
            </a>

            <div class="menu-section">NEGOCIO</div>
            <a href="negocio_configuracion.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'negocio_configuracion.php' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i>
                Mi Negocio
            </a>
            <a href="wallet_negocio.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'wallet_negocio.php' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                Monedero
            </a>
            <a href="reportes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Reportes
            </a>

            <div class="menu-section">CONFIGURACIÓN</div>
            <a href="configuracion.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                Configuración
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($usuario->nombre, 0, 1); ?>
                </div>
                <div class="user-details">
                    <p class="user-name"><?php echo $usuario->nombre . ' ' . $usuario->apellido; ?></p>
                    <p class="user-role">Propietario</p>
                </div>
                <a href="../logout.php" class="logout-btn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Toggle Sidebar Button (para móvil) -->
    <button class="toggle-sidebar d-lg-none" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestión de Menú</h1>
                <p class="page-description">Administra todos los productos de tu negocio</p>
            </div>
            
            <div class="d-flex gap-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                    <i class="fas fa-plus"></i> Añadir Producto
                </button>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                    <i class="fas fa-folder-plus"></i> Nueva Categoría
                </button>
            </div>
        </div>
        
        
        <!-- Alerta sobre IA para subir menú -->
        <div class="alert ai-menu-alert" style="background: linear-gradient(135deg, #f8f9ff 0%, #faf8ff 100%); border: 1px solid #e8e9f3; border-radius: 12px; margin-bottom: 24px; padding: 20px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.08);">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <img src="/assets/icons/robot.png" alt="AI" style="width: 24px; height: 24px;">
                    </div>
                    <div>
                        <strong style="color: #2d3748; font-size: 15px; font-family: 'DM Sans', 'Inter', sans-serif; font-weight: 600; letter-spacing: -0.01em;">Sube tu menú con IA</strong>
                        <p style="margin: 4px 0 0 0; color: #718096; font-size: 13px; font-family: 'Inter', sans-serif; font-weight: 400;">Toma una foto y nuestra IA lo analizará automáticamente</p>
                    </div>
                </div>
                <a href="chat_menu.html?negocio_id=<?php echo $negocio_info['id_negocio']; ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; white-space: nowrap; font-family: 'DM Sans', sans-serif; font-weight: 500; padding: 10px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25); transition: all 0.3s ease;">
                    <i class="fas fa-sparkles" style="margin-right: 6px;"></i> Probar IA
                </a>
            </div>
        </div>
        
        <?php if (isset($mensaje_producto)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje_producto; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error_producto)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $mensaje_error_producto; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_categoria)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje_categoria; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error_categoria)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $mensaje_error_categoria; ?>
        </div>
        <?php endif; ?>
        
        <!-- Categorías y Productos -->
        <?php if (empty($productos)): ?>
        <div class="content-card">
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h4>Aún no tienes productos</h4>
                <p>Comienza a añadir productos a tu menú para que los clientes puedan realizar pedidos.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                    <i class="fas fa-plus"></i> Añadir Mi Primer Producto
                </button>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($productos_por_categoria as $cat_id => $categoria): ?>
            <div class="content-card">
                <h2 class="card-title">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($categoria['nombre']); ?></span>
                    <div>
                        <?php if ($cat_id > 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarCategoria(<?php echo $cat_id; ?>, '<?php echo htmlspecialchars($categoria['nombre']); ?>', '')">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <?php endif; ?>
                    </div>
                </h2>
                
                <div class="productos-grid">
                    <?php foreach ($categoria['productos'] as $producto): ?>
                    <div class="producto-card">
                        <div class="producto-imagen" style="<?php echo !empty($producto['imagen']) ? "background-image: url('" . htmlspecialchars($producto['imagen']) . "');" : ""; ?>">
                            <?php if (empty($producto['imagen'])): ?>
                            <div class="producto-imagen-placeholder">
                                <i class="fas fa-hamburger"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="producto-status">
                                <?php if ($producto['destacado']): ?>
                                <span class="badge badge-primary"><i class="fas fa-star"></i> Destacado</span>
                                <?php endif; ?>
                                
                                <?php if (!$producto['disponible']): ?>
                                <span class="badge badge-danger"><i class="fas fa-ban"></i> No disponible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="producto-contenido">
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                            
                            <!-- Mostrar elegibles si existen -->
                            <?php if ($producto['tiene_elegibles'] && !empty($producto['elegibles'])): ?>
                            <div class="producto-elegibles mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-list-ul"></i> 
                                    <?php 
                                    $elegibles_nombres = array_map(function($e) { 
                                        return $e['nombre'] . ($e['precio_adicional'] > 0 ? ' (+$' . number_format($e['precio_adicional'], 2) . ')' : '');
                                    }, array_filter($producto['elegibles'], function($e) { return $e['disponible']; }));
                                    echo implode(', ', array_slice($elegibles_nombres, 0, 3));
                                    if (count($elegibles_nombres) > 3) echo '...';
                                    ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="producto-precio">$<?php echo number_format($producto['precio'], 2); ?></div>
                            <div class="producto-descripcion"><?php echo htmlspecialchars($producto['descripcion']); ?></div>
                        </div>
                        
                        <div class="producto-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="editarProducto(<?php echo htmlspecialchars(json_encode($producto), ENT_QUOTES, 'UTF-8'); ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            
                            <button class="btn btn-sm btn-outline-success" onclick="gestionarOpcionesDinamicas(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>')" title="Opciones dinámicas (Proteínas, Tamaños, etc.)">
                                <i class="fas fa-sliders-h"></i> Opciones
                            </button>
                            
                            <?php
                            $configMensaje = [
                                'permite_texto_producto' => (bool)($producto['permite_texto_producto'] ?? false),
                                'permite_mensaje_tarjeta' => (bool)($producto['permite_mensaje_tarjeta'] ?? false),
                                'limite_texto_producto' => (int)($producto['limite_texto_producto'] ?? 50)
                            ];
                            ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="gestionarElegibles(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>', <?php echo $producto['tiene_elegibles']; ?>, <?php echo htmlspecialchars(json_encode($producto['elegibles'] ?? []), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($configMensaje), ENT_QUOTES, 'UTF-8'); ?>)" title="Personalización del producto">
                                <i class="fas fa-gift"></i> Personalizar
                            </button>
                            
                            <button class="btn btn-sm btn-danger" onclick="confirmarEliminarProducto(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal Añadir/Editar Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProductoTitle">Añadir Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="guardar_producto">
                    <input type="hidden" name="producto_id" id="producto_id" value="0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="nombre_producto" class="form-label">Nombre del Producto *</label>
                                    <input type="text" class="form-control" id="nombre_producto" name="nombre_producto" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="descripcion_producto" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion_producto" name="descripcion_producto" rows="3"></textarea>
                                    <span class="form-text">Describe los ingredientes, preparación, o características especiales.</span>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="precio" class="form-label">Precio *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="precio" name="precio" min="0.01" step="0.01" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="categoria_producto" class="form-label">Categoría</label>
                                            <select class="form-control" id="categoria_producto" name="categoria_producto">
                                                <option value="0">Sin categoría</option>
                                                <?php foreach ($categorias_producto as $cat): ?>
                                                <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="disponible" name="disponible" checked>
                                        <label class="form-check-label" for="disponible">
                                            Disponible para ordenar
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="destacado" name="destacado">
                                        <label class="form-check-label" for="destacado">
                                            Producto destacado
                                        </label>
                                    </div>
                                    <span class="form-text">Los productos destacados aparecen primero y se resaltan en el menú.</span>
                                </div>
<?php if ($mostrar_calorias): ?>
                                <div class="form-group">
                                    <label for="calorias" class="form-label">Calorías (opcional)</label>
                                    <input type="number" class="form-control" id="calorias" name="calorias" min="0" step="1" placeholder="Ejemplo: 250">
                                    <small class="form-text">
                                        Si no conoces las calorías, puedes buscar aquí: 
                                        <a href="https://www.google.com/search?q=calorías+de+alimentos" target="_blank" rel="noopener noreferrer">Buscar calorías</a>
                                    </small>
                                </div>
<?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Imagen del Producto</label>
                                    <div class="image-preview" id="imagen-producto-preview">
                                        <i class="fas fa-hamburger"></i>
                                    </div>
                                    <input type="file" class="form-control" id="imagen_producto" name="imagen_producto" accept="image/*" onchange="previewImage('imagen_producto', 'imagen-producto-preview')">
                                    <span class="form-text">Imagen atractiva del producto (opcional).</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalElegibles" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalElegiblesTitle">Gestionar Elegibles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="gestionar_elegibles">
                <input type="hidden" name="producto_id" id="elegibles_producto_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="modal-body">
                    <div class="form-group mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tiene_elegibles" name="tiene_elegibles" onchange="toggleElegibles()">
                            <label class="form-check-label" for="tiene_elegibles">
                                <strong>Este producto tiene opciones elegibles</strong>
                            </label>
                        </div>
                        <span class="form-text">Por ejemplo: Tacos de pastor, asada, pollo, etc.</span>
                    </div>

                    <!-- Opciones de mensaje personalizado -->
                    <div class="card mb-4" style="border: 1px solid #e5e7eb;">
                        <div class="card-header py-2" style="background: #f9fafb;">
                            <strong style="font-size: 0.9rem;">Personalización para regalos</strong>
                        </div>
                        <div class="card-body py-3">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="permite_texto_producto" name="permite_texto_producto">
                                <label class="form-check-label" for="permite_texto_producto">
                                    <span>✍️ Permitir texto en el producto</span>
                                </label>
                                <div class="form-text">Ej: "Feliz Cumpleaños" escrito en un pastel</div>
                            </div>
                            <div id="limite-texto-container" class="ms-4 mb-3" style="display: none;">
                                <label class="form-label small">Límite de caracteres:</label>
                                <input type="number" class="form-control form-control-sm" id="limite_texto_producto" name="limite_texto_producto" value="50" min="10" max="200" style="width: 100px;">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="permite_mensaje_tarjeta" name="permite_mensaje_tarjeta">
                                <label class="form-check-label" for="permite_mensaje_tarjeta">
                                    <span>💌 Permitir mensaje de tarjeta</span>
                                </label>
                                <div class="form-text">Ej: Dedicatoria para acompañar flores o regalo</div>
                            </div>
                        </div>
                    </div>

                    <div id="elegibles-container" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Opciones Elegibles</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarElegible()">
                                <i class="fas fa-plus"></i> Agregar Opción
                            </button>
                        </div>
                        
                        <div id="elegibles-list">
                            <!-- Los elegibles se cargarán aquí dinámicamente -->
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i>
                            <strong>Tip:</strong> Puedes agregar un precio adicional para opciones premium. Por ejemplo, "Carne Premium +$5.00"
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Elegibles</button>
                </div>
            </form>
        </div>
    </div>
</div>
    
    <!-- Modal Añadir/Editar Categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCategoriaTitle">Añadir Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="guardar_categoria">
                    <input type="hidden" name="categoria_id" id="categoria_id" value="0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nombre_categoria" class="form-label">Nombre de la Categoría *</label>
                            <input type="text" class="form-control" id="nombre_categoria" name="nombre_categoria" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion_categoria" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion_categoria" name="descripcion_categoria" rows="3"></textarea>
                            <span class="form-text">Descripción breve de la categoría (opcional).</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Categoría</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmar Eliminación -->
    <div class="modal fade" id="modalConfirmarEliminar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmarEliminarTitle">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmarEliminarMensaje">¿Estás seguro de que deseas eliminar este elemento?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                   <form method="POST" action="" id="formEliminar">
    <input type="hidden" name="action" id="eliminar_action" value="">
    <input type="hidden" name="producto_id" id="eliminar_id" value="">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <button type="submit" class="btn btn-danger">Eliminar</button>
</form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Opciones Dinámicas -->
    <div class="modal fade" id="modalOpcionesDinamicas" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sliders-h"></i> Opciones Dinámicas: <span id="opciones_producto_nombre"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" id="opciones_producto_id" value="0">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>¿Qué son las opciones dinámicas?</strong><br>
                        Permiten que tus clientes personalicen sus pedidos. Ejemplos:
                        <ul class="mb-0 mt-2">
                            <li><strong>Tacos:</strong> "Elige tu proteína" → Pastor, Bistec, Cabeza</li>
                            <li><strong>Bebidas:</strong> "Tipo de leche" → Entera, Deslactosada, Almendra</li>
                            <li><strong>Pizzas:</strong> "Tamaño" → Chica, Mediana (+$50), Grande (+$100)</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="mb-0"><i class="fas fa-layer-group"></i> Grupos de Opciones</h6>
                        <button type="button" class="btn btn-primary" onclick="crearNuevoGrupo()">
                            <i class="fas fa-plus"></i> Nuevo Grupo
                        </button>
                    </div>
                    
                    <div id="grupos-opciones-list">
                        <!-- Los grupos se cargarán aquí dinámicamente -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/opciones_dinamicas.js"></script>
    <script>
        // Toggle sidebar en móvil
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleSidebar');
            const sidebar = document.getElementById('sidebar');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Cerrar sidebar al hacer clic fuera en dispositivos móviles
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggleBtn = toggleBtn.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggleBtn && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        });
        
        // Función para mostrar la vista previa de la imagen
        function previewImage(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.innerHTML = '';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Función para editar un producto
        function editarProducto(producto) {
            document.getElementById('modalProductoTitle').textContent = 'Editar Producto';
            document.getElementById('producto_id').value = escapeHtml(producto.id_producto);
            document.getElementById('nombre_producto').value = escapeHtml(producto.nombre);
            document.getElementById('descripcion_producto').value = escapeHtml(producto.descripcion);
            document.getElementById('precio').value = parseFloat(producto.precio) || 0;
            document.getElementById('categoria_producto').value = parseInt(producto.id_categoria) || 0;
            document.getElementById('disponible').checked = producto.disponible == 1;
            document.getElementById('destacado').checked = producto.destacado == 1;
            
            const caloriasInput = document.getElementById('calorias');
            if (caloriasInput) {
                caloriasInput.value = producto.calorias || '';
            }
            
            const preview = document.getElementById('imagen-producto-preview');
            if (producto.imagen) {
                preview.style.backgroundImage = `url('../${producto.imagen}')`;
                preview.innerHTML = '';
            } else {
                preview.style.backgroundImage = '';
                preview.innerHTML = '<i class="fas fa-hamburger"></i>';
            }
            
            // Mostrar el modal para editar producto
            const modalElement = document.getElementById('modalProducto');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (!modal) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            } else {
                modal.show();
            }
        }
        
        // Función auxiliar para escape HTML y prevenir XSS
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
        
        // Función para editar una categoría
        function editarCategoria(id, nombre, descripcion) {
            document.getElementById('modalCategoriaTitle').textContent = 'Editar Categoría';
            document.getElementById('categoria_id').value = parseInt(id) || 0;
            document.getElementById('nombre_categoria').value = escapeHtml(nombre);
            document.getElementById('descripcion_categoria').value = escapeHtml(descripcion);
            
            const modal = new bootstrap.Modal(document.getElementById('modalCategoria'));
            modal.show();
        }
        
        // Función para confirmar eliminación de producto
        function confirmarEliminarProducto(id, nombre) {
            document.getElementById('confirmarEliminarTitle').textContent = 'Eliminar Producto';
            const nombreSeguro = escapeHtml(nombre);
            document.getElementById('confirmarEliminarMensaje').textContent = `¿Estás seguro de que deseas eliminar el producto "${nombreSeguro}"? Esta acción no se puede deshacer.`;
            document.getElementById('eliminar_action').value = 'eliminar_producto';
            document.getElementById('eliminar_id').value = parseInt(id) || 0;
            
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
            modal.show();
        }
        
        // Función para confirmar eliminación de categoría
        function confirmarEliminarCategoria(id, nombre) {
            document.getElementById('confirmarEliminarTitle').textContent = 'Eliminar Categoría';
            const nombreSeguro = escapeHtml(nombre);
            document.getElementById('confirmarEliminarMensaje').textContent = `¿Estás seguro de que deseas eliminar la categoría "${nombreSeguro}"? Los productos asociados se moverán a "Sin categoría".`;
            document.getElementById('eliminar_action').value = 'eliminar_categoria';
            document.getElementById('eliminar_id').value = parseInt(id) || 0;
            
            const modal = new bootstrap.Modal(document.getElementById('modalConfirmarEliminar'));
            modal.show();
        }
        
        // Resetear formularios al abrir modales
        document.getElementById('modalProducto').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalProductoTitle').textContent = 'Añadir Nuevo Producto';
            document.getElementById('producto_id').value = '0';
            document.querySelector('#modalProducto form').reset();
            document.getElementById('imagen-producto-preview').style.backgroundImage = '';
            document.getElementById('imagen-producto-preview').innerHTML = '<i class="fas fa-hamburger"></i>';
            // Asegurar que el modal se cierre completamente para evitar problemas
            const modalElement = document.getElementById('modalProducto');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
                modalInstance.hide();
            }
            // Resetear el título del modal para nuevo producto
            document.getElementById('modalProductoTitle').textContent = 'Añadir Nuevo Producto';
        });
        
        document.getElementById('modalCategoria').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalCategoriaTitle').textContent = 'Añadir Nueva Categoría';
            document.getElementById('categoria_id').value = '0';
            document.querySelector('#modalCategoria form').reset();
        });

        let elegibleCounter = 0;

// Función para gestionar elegibles
function gestionarElegibles(productoId, nombreProducto, tieneElegibles, elegibles, configMensaje = {}) {
    document.getElementById('modalElegiblesTitle').textContent = `Personalización: ${nombreProducto}`;
    document.getElementById('elegibles_producto_id').value = productoId;
    document.getElementById('tiene_elegibles').checked = tieneElegibles == 1;

    // Cargar configuración de mensajes personalizados
    const permiteTexto = configMensaje.permite_texto_producto || false;
    const permiteTarjeta = configMensaje.permite_mensaje_tarjeta || false;
    const limiteTexto = configMensaje.limite_texto_producto || 50;

    document.getElementById('permite_texto_producto').checked = permiteTexto;
    document.getElementById('permite_mensaje_tarjeta').checked = permiteTarjeta;
    document.getElementById('limite_texto_producto').value = limiteTexto;

    // Mostrar/ocultar límite de texto
    document.getElementById('limite-texto-container').style.display = permiteTexto ? 'block' : 'none';

    // Reiniciar contador
    elegibleCounter = 0;

    // Limpiar lista de elegibles
    const elegiblesList = document.getElementById('elegibles-list');
    elegiblesList.innerHTML = '';

    // Cargar elegibles existentes
    if (tieneElegibles == 1 && elegibles && elegibles.length > 0) {
        elegibles.forEach(function(elegible) {
            agregarElegibleExistente(elegible);
        });
    }

    // Mostrar/ocultar container de elegibles
    toggleElegibles();

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('modalElegibles'));
    modal.show();
}

// Toggle para mostrar límite de texto
document.getElementById('permite_texto_producto').addEventListener('change', function() {
    document.getElementById('limite-texto-container').style.display = this.checked ? 'block' : 'none';
});

// Función para mostrar/ocultar elegibles
function toggleElegibles() {
    const container = document.getElementById('elegibles-container');
    const checkbox = document.getElementById('tiene_elegibles');
    
    if (checkbox.checked) {
        container.style.display = 'block';
        // Si no hay elegibles, agregar uno por defecto
        if (document.getElementById('elegibles-list').children.length === 0) {
            agregarElegible();
        }
    } else {
        container.style.display = 'none';
    }
}

// Función para agregar un nuevo elegible
function agregarElegible() {
    const elegiblesList = document.getElementById('elegibles-list');
    const elegibleId = elegibleCounter++;
    
    const elegibleHTML = `
        <div class="elegible-item" id="elegible-${elegibleId}">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove" onclick="eliminarElegible(${elegibleId})" title="Eliminar opción">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="row align-items-center">
                <div class="col-1 d-none d-md-block">
                    <div class="elegible-drag-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                </div>
                <div class="col-md-5 col-12">
                    <label class="form-label">Nombre de la opción *</label>
                    <input type="text" class="form-control" name="elegibles_nombres[]" placeholder="Ej: Pastor, Asada, Pollo" required>
                    <input type="hidden" name="elegibles_ids[]" value="">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">Precio adicional</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" name="elegibles_precios[]" min="0" step="0.01" value="0" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="elegibles_disponibles[${elegibleId}]" checked>
                        <label class="form-check-label">
                            Disponible
                        </label>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    elegiblesList.insertAdjacentHTML('beforeend', elegibleHTML);
}

// Función para agregar elegible existente
function agregarElegibleExistente(elegible) {
    const elegiblesList = document.getElementById('elegibles-list');
    const elegibleId = elegibleCounter++;
    
    const elegibleHTML = `
        <div class="elegible-item" id="elegible-${elegibleId}">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove" onclick="eliminarElegible(${elegibleId})" title="Eliminar opción">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="row align-items-center">
                <div class="col-1 d-none d-md-block">
                    <div class="elegible-drag-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                </div>
                <div class="col-md-5 col-12">
                    <label class="form-label">Nombre de la opción *</label>
                    <input type="text" class="form-control" name="elegibles_nombres[]" value="${elegible.nombre}" placeholder="Ej: Pastor, Asada, Pollo" required>
                    <input type="hidden" name="elegibles_ids[]" value="${elegible.id_elegible}">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label">Precio adicional</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" name="elegibles_precios[]" min="0" step="0.01" value="${elegible.precio_adicional}" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="elegibles_disponibles[${elegibleId}]" ${elegible.disponible == 1 ? 'checked' : ''}>
                        <label class="form-check-label">
                            Disponible
                        </label>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    elegiblesList.insertAdjacentHTML('beforeend', elegibleHTML);
}

// Función para eliminar elegible
function eliminarElegible(elegibleId) {
    const elegible = document.getElementById(`elegible-${elegibleId}`);
    if (elegible) {
        elegible.remove();
    }
    
    // Si no quedan elegibles, agregar uno nuevo
    const elegiblesList = document.getElementById('elegibles-list');
    if (elegiblesList.children.length === 0) {
        agregarElegible();
    }
}

// Resetear modal de elegibles al cerrar
document.getElementById('modalElegibles').addEventListener('hidden.bs.modal', function () {
    document.getElementById('elegibles_producto_id').value = '';
    document.getElementById('tiene_elegibles').checked = false;
    document.getElementById('elegibles-container').style.display = 'none';
    document.getElementById('elegibles-list').innerHTML = '';
    elegibleCounter = 0;
});

// Agregar elegible inicial cuando se marca la checkbox
document.getElementById('tiene_elegibles').addEventListener('change', function() {
    if (this.checked) {
        const elegiblesList = document.getElementById('elegibles-list');
        if (elegiblesList.children.length === 0) {
            agregarElegible();
        }
    }
});

// Validación del formulario de elegibles
document.querySelector('#modalElegibles form').addEventListener('submit', function(e) {
    const tieneElegibles = document.getElementById('tiene_elegibles').checked;
    
    if (tieneElegibles) {
        const nombres = document.querySelectorAll('input[name="elegibles_nombres[]"]');
        let hayNombreVacio = false;
        
        nombres.forEach(function(input) {
            if (input.value.trim() === '') {
                hayNombreVacio = true;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (hayNombreVacio) {
            e.preventDefault();
            alert('Por favor, completa todos los nombres de las opciones elegibles.');
            return false;
        }
        
        if (nombres.length === 0) {
            e.preventDefault();
            alert('Debes agregar al menos una opción elegible.');
            return false;
        }
    }
});
    </script>
</body>
</html>