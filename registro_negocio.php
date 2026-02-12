<?php
session_start();

// Protección CSRF
require_once __DIR__ . '/config/csrf.php';

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Categoria.php';

// Configuración de errores para producción
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(0);

// Verificar si el usuario está logueado y es tipo negocio
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["tipo_usuario"] !== "negocio") {
    // Si no está logueado o no es tipo negocio, redirigir al registro de usuario negocio
    header("Location: registro_usuario_negocio.php");
    exit;
}

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Obtener información del usuario
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

// Crear instancia del modelo Negocio
$negocio = new Negocio($db);

// Verificar si el usuario ya tiene un negocio registrado
$query = "SELECT * FROM negocios WHERE id_propietario = ?";
$stmt = $db->prepare($query);
$stmt->bindParam(1, $usuario->id_usuario);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    // Si ya tiene un negocio, redirigir a la página de configuración
    header("Location: admin/negocio_configuracion.php");
    exit;
}

// Obtener todas las categorías para el formulario
$categoria = new Categoria($db);
$todas_categorias = $categoria->obtenerTodas();

// Procesar el formulario de registro del negocio
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mensaje_error = "Error de seguridad. Por favor, recarga la página e intenta de nuevo.";
    } else {

    // Recoger los datos del formulario
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $calle = trim($_POST['calle']);
    $numero = trim($_POST['numero']);
    $colonia = trim($_POST['colonia']);
    $ciudad = trim($_POST['ciudad']);
    $estado = trim($_POST['estado']);
    $codigo_postal = trim($_POST['codigo_postal']);
    $latitud = floatval($_POST['latitud']);
    $longitud = floatval($_POST['longitud']);
    $radio_entrega = intval($_POST['radio_entrega']);
    $tiempo_preparacion = intval($_POST['tiempo_preparacion']);
    $pedido_minimo = floatval($_POST['pedido_minimo']);
    $costo_envio = floatval($_POST['costo_envio']);
    
    // Categorías seleccionadas
    $categorias_seleccionadas = isset($_POST['categorias']) ? $_POST['categorias'] : [];
    
    // Validación de datos
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre del negocio es obligatorio.";
    }
    
    if (empty($telefono)) {
        $errores[] = "El teléfono es obligatorio.";
    }
    
    if (empty($calle) || empty($numero) || empty($colonia) || empty($ciudad) || empty($estado)) {
        $errores[] = "La dirección completa es obligatoria.";
    }
    
    if (empty($categorias_seleccionadas)) {
        $errores[] = "Debes seleccionar al menos una categoría.";
    }
    
    // Si no hay errores, proceder a crear el negocio
    if (empty($errores)) {
        // Configurar datos del negocio
        $negocio->id_propietario = $usuario->id_usuario;
        $negocio->nombre = $nombre;
        $negocio->descripcion = $descripcion;
        $negocio->telefono = $telefono;
        $negocio->email = $email;
        $negocio->calle = $calle;
        $negocio->numero = $numero;
        $negocio->colonia = $colonia;
        $negocio->ciudad = $ciudad;
        $negocio->estado = $estado;
        $negocio->codigo_postal = $codigo_postal;
        $negocio->latitud = $latitud;
        $negocio->longitud = $longitud;
        $negocio->radio_entrega = $radio_entrega;
        $negocio->tiempo_preparacion_promedio = $tiempo_preparacion;
        $negocio->pedido_minimo = $pedido_minimo;
        $negocio->costo_envio = $costo_envio;
        $negocio->activo = 1;
        
        // Procesar imágenes si se han cargado
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_path = procesarImagen($_FILES['logo'], 'logo');
            if ($logo_path) {
                $negocio->logo = $logo_path;
            }
        }
        
        if (isset($_FILES['imagen_portada']) && $_FILES['imagen_portada']['error'] === UPLOAD_ERR_OK) {
            $portada_path = procesarImagen($_FILES['imagen_portada'], 'portada');
            if ($portada_path) {
                $negocio->imagen_portada = $portada_path;
            }
        }
        
        // Crear el negocio
        if ($negocio->crear()) {
            // Actualizar las categorías del negocio
            $negocio->actualizarCategorias($categorias_seleccionadas);
            
            // Actualizar los horarios usando guardarHorarios()
            $nuevo_horarios = [];
            $dias_mapeo = [
                0 => 'domingo',
                1 => 'lunes',
                2 => 'martes',
                3 => 'miercoles',
                4 => 'jueves',
                5 => 'viernes',
                6 => 'sabado'
            ];
            
            foreach ($dias_mapeo as $dia_num => $dia_nombre) {
                $abierto = isset($_POST['abierto_' . $dia_num]) ? true : false;
                if ($abierto) {
                    $hora_apertura = $_POST['apertura_' . $dia_num] . ':00';
                    $hora_cierre = $_POST['cierre_' . $dia_num] . ':00';
                    $nuevo_horarios[$dia_num] = [
                        'hora_apertura' => $hora_apertura,
                        'hora_cierre' => $hora_cierre,
                        'activo' => 1
                    ];
                } else {
                    $nuevo_horarios[$dia_num] = [
                        'hora_apertura' => '00:00:00',
                        'hora_cierre' => '00:00:00',
                        'activo' => 0
                    ];
                }
            }
            
            $negocio->guardarHorarios($nuevo_horarios);

            // Procesar menú con IA si se subió una imagen
            if (isset($_FILES['menu_imagen']) && $_FILES['menu_imagen']['error'] === UPLOAD_ERR_OK) {
                try {
                    require_once __DIR__ . '/admin/gemini_menu_parser.php';

                    // Guardar temporalmente la imagen
                    $temp_path = sys_get_temp_dir() . '/' . uniqid('menu_') . '_' . basename($_FILES['menu_imagen']['name']);
                    if (move_uploaded_file($_FILES['menu_imagen']['tmp_name'], $temp_path)) {

                        // Inicializar el parser de Gemini
                        $parser = new GeminiMenuParser();

                        // Procesar el menú
                        $menu_data = $parser->parseMenuFromImage($temp_path);

                        // Guardar productos y categorías en la base de datos
                        if (!empty($menu_data['productos'])) {
                            // Primero crear/obtener categorías
                            $categorias_map = [];
                            if (!empty($menu_data['categorias'])) {
                                foreach ($menu_data['categorias'] as $cat) {
                                    $stmt = $db->prepare("INSERT INTO categorias_productos (nombre, descripcion) VALUES (?, ?) ON DUPLICATE KEY UPDATE id_categoria=LAST_INSERT_ID(id_categoria)");
                                    $stmt->execute([$cat['nombre'], $cat['descripcion'] ?? '']);
                                    $categorias_map[$cat['nombre']] = $db->lastInsertId();
                                }
                            }

                            // Insertar productos
                            $productos_insertados = 0;
                            foreach ($menu_data['productos'] as $producto) {
                                $id_categoria = isset($categorias_map[$producto['categoria']]) ? $categorias_map[$producto['categoria']] : null;

                                $stmt = $db->prepare("
                                    INSERT INTO productos
                                    (id_negocio, id_categoria, nombre, descripcion, precio, disponible, imagen, calorias)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");

                                $disponible = isset($producto['disponible']) ? ($producto['disponible'] ? 1 : 0) : 1;
                                $imagen = $producto['imagen'] ?? null;
                                $calorias = $producto['calorias'] ?? null;

                                $stmt->execute([
                                    $negocio->id_negocio,
                                    $id_categoria,
                                    $producto['nombre'],
                                    $producto['descripcion'] ?? '',
                                    $producto['precio'],
                                    $disponible,
                                    $imagen,
                                    $calorias
                                ]);

                                $productos_insertados++;
                            }

                            $_SESSION['mensaje_exito'] = "¡Tu negocio ha sido registrado correctamente! La IA procesó tu menú y agregó " . $productos_insertados . " productos automáticamente.";
                        }

                        // Limpiar archivo temporal
                        @unlink($temp_path);
                    }
                } catch (Exception $e) {
                    // Si falla la IA, continuar sin error (el negocio ya fue creado)
                    error_log("Error procesando menú con IA: " . $e->getMessage());
                    $_SESSION['mensaje_exito'] = "¡Tu negocio ha sido registrado correctamente! Puedes agregar tu menú manualmente desde el panel.";
                }
            } else {
                $_SESSION['mensaje_exito'] = "¡Tu negocio ha sido registrado correctamente! Ahora puedes agregar tu menú desde el panel.";
            }

            // Actualizar tipo de usuario a "negocio" (en caso de que no esté actualizado)
            if (method_exists($usuario, 'actualizarTipo')) {
                $usuario->actualizarTipo("negocio");
            } else {
                $query_update_user = "UPDATE usuarios SET tipo_usuario = 'negocio' WHERE id_usuario = ?";
                $stmt_update_user = $db->prepare($query_update_user);
                $stmt_update_user->bindParam(1, $usuario->id_usuario);
                $stmt_update_user->execute();
            }

            $_SESSION["tipo_usuario"] = "negocio";

            header("Location: admin/negocio_configuracion.php");
            exit;
        } else {
            $mensaje_error = "Ha ocurrido un error al registrar el negocio. Por favor, intenta de nuevo.";
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
    }
    } // Cierre del else CSRF
}

// Función mejorada para procesar y redimensionar imágenes automáticamente
function procesarImagen($archivo, $tipo) {
    $directorio_destino = "assets/img/restaurants/";

    if (!file_exists($directorio_destino)) {
        mkdir($directorio_destino, 0755, true);
    }

    $tipo_archivo = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));
    if ($tipo_archivo != "jpg" && $tipo_archivo != "png" && $tipo_archivo != "jpeg" && $tipo_archivo != "webp") {
        return false;
    }

    // Leer la imagen original
    $imagen_original = null;
    switch ($tipo_archivo) {
        case 'jpg':
        case 'jpeg':
            $imagen_original = @imagecreatefromjpeg($archivo["tmp_name"]);
            break;
        case 'png':
            $imagen_original = @imagecreatefrompng($archivo["tmp_name"]);
            break;
        case 'webp':
            $imagen_original = @imagecreatefromwebp($archivo["tmp_name"]);
            break;
    }

    if (!$imagen_original) {
        // Si falla la carga, intentar mover el archivo sin procesar
        $nombre_archivo = uniqid($tipo . '_') . '.' . $tipo_archivo;
        $ruta_completa = $directorio_destino . $nombre_archivo;
        if (move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
            return $ruta_completa;
        }
        return false;
    }

    // Obtener dimensiones originales
    $ancho_original = imagesx($imagen_original);
    $alto_original = imagesy($imagen_original);

    // Definir dimensiones objetivo según el tipo
    if ($tipo === 'logo') {
        // Logo: cuadrado 500x500px
        $ancho_destino = 500;
        $alto_destino = 500;
    } else if ($tipo === 'portada') {
        // Portada: panorámico 1200x400px
        $ancho_destino = 1200;
        $alto_destino = 400;
    } else {
        // Por defecto: mantener aspecto, máximo 800px de ancho
        $ratio = $ancho_original / $alto_original;
        $ancho_destino = 800;
        $alto_destino = intval($ancho_destino / $ratio);
    }

    // Crear imagen redimensionada
    $imagen_redimensionada = imagecreatetruecolor($ancho_destino, $alto_destino);

    // Preservar transparencia para PNG
    if ($tipo_archivo === 'png') {
        imagealphablending($imagen_redimensionada, false);
        imagesavealpha($imagen_redimensionada, true);
        $transparente = imagecolorallocatealpha($imagen_redimensionada, 0, 0, 0, 127);
        imagefill($imagen_redimensionada, 0, 0, $transparente);
    }

    // Redimensionar con alta calidad
    imagecopyresampled(
        $imagen_redimensionada,
        $imagen_original,
        0, 0, 0, 0,
        $ancho_destino,
        $alto_destino,
        $ancho_original,
        $alto_original
    );

    // Guardar imagen optimizada (siempre como JPG para mejor compresión, excepto si era PNG)
    $nombre_archivo = uniqid($tipo . '_') . '.jpg';
    $ruta_completa = $directorio_destino . $nombre_archivo;

    $guardado_exitoso = false;
    if ($tipo_archivo === 'png') {
        $nombre_archivo = uniqid($tipo . '_') . '.png';
        $ruta_completa = $directorio_destino . $nombre_archivo;
        $guardado_exitoso = imagepng($imagen_redimensionada, $ruta_completa, 8); // Compresión 8/9
    } else {
        $guardado_exitoso = imagejpeg($imagen_redimensionada, $ruta_completa, 85); // Calidad 85%
    }

    // Liberar memoria
    imagedestroy($imagen_original);
    imagedestroy($imagen_redimensionada);

    if ($guardado_exitoso) {
        return $ruta_completa;
    }

    return false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registra tu Negocio - QuickBite</title>
    <!-- Fonts: Inter and DM Sans -->
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <meta name="theme-color" content="#FFFFFF">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0165FF;         /* Azul principal */
            --primary-light: #E3F2FD;   /* Azul claro */
            --primary-dark: #0165FF;    /* Azul oscuro */
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FFFFFF;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            min-height: 100vh;
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        .container {
            max-width: 900px;
            padding: 20px 15px 80px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 20px;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .user-info {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .user-info h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }

        .steps-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .steps-container::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #ddd;
            z-index: 1;
        }

        .step {
            width: 50px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background-color: white;
            border: 2px solid #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-weight: 600;
            color: #999;
            margin: 0 auto 10px;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .step.completed .step-number {
            background-color: #4CAF50;
            border-color: #4CAF50;
            color: white;
        }

        .step-label {
            font-size: 0.8rem;
            color: #777;
        }

        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: #4CAF50;
            font-weight: 600;
        }

        .form-section {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        .form-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        .required-label::after {
            content: " *";
            color: #dc3545;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-size: cover;
            background-position: center;
            background-color: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }

        .image-preview i {
            font-size: 2rem;
        }

        /* Horarios */
        .horario-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .dia-label {
            width: 120px;
            font-weight: 500;
        }

        .horario-inputs {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }

        .horario-separator {
            margin: 0 10px;
            color: #666;
        }

        .horario-checkbox {
            margin-right: 15px;
        }

        /* Categorías */
        .categoria-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .categoria-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .categoria-item:hover {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .categoria-item input {
            margin-right: 10px;
        }

        /* Mapa */
        #map {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        /* Botones de navegación */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn-back {
            background-color: #f1f1f1;
            color: #666;
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: #e1e1e1;
        }

        .btn-next, .btn-submit {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-next:hover, .btn-submit:hover {
            background-color: var(--primary-dark);
        }

        /* Alert messages */
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .categoria-list {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Registra tu Negocio en QuickBite</h1>
            <p class="page-subtitle">Completa la información de tu restaurante para empezar a recibir pedidos a través de nuestra plataforma.</p>
        </div>
        
        <!-- Información del usuario logueado -->
        <div class="user-info">
            <h3>¡Hola, <?php echo htmlspecialchars($usuario->nombre); ?>!</h3>
            <p>Ahora vamos a registrar la información de tu negocio.</p>
        </div>
        
        <!-- Mostrar mensaje de éxito si existe -->
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['mensaje_exito']; unset($_SESSION['mensaje_exito']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Pasos del proceso de registro -->
        <div class="steps-container">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
                <div class="step-label">Información Básica</div>
            </div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
                <div class="step-label">Ubicación</div>
            </div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
                <div class="step-label">Horarios</div>
            </div>
            <div class="step" id="step4">
                <div class="step-number">4</div>
                <div class="step-label">Categorías</div>
            </div>
            <div class="step" id="step5">
                <div class="step-number">5</div>
                <div class="step-label">Imágenes</div>
            </div>
        </div>
        
        <?php if (isset($mensaje_error)): ?>
        <div class="alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $mensaje_error; ?>
        </div>
        <?php endif; ?>
        
        <!-- Formulario de registro -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" id="registro-form">
            <?php echo csrf_field(); ?>
            <!-- Paso 1: Información Básica -->
            <div class="form-section" id="seccion1">
                <h2 class="section-title">Información Básica</h2>
                
                <div class="form-group">
                    <label for="nombre" class="form-label required-label">Nombre del Negocio</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="4"></textarea>
                    <small class="form-text">Describe tu negocio, especialidad, historia, etc.</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefono" class="form-label required-label">Teléfono</label>
                            <input type="number" class="form-control" id="telefono" name="telefono" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tiempo_preparacion" class="form-label">Tiempo de Preparación Promedio (min)</label>
                            <input type="number" class="form-control" id="tiempo_preparacion" name="tiempo_preparacion" min="1" max="120" value="30">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="pedido_minimo" class="form-label">Pedido Mínimo ($)</label>
                            <input type="number" class="form-control" id="pedido_minimo" name="pedido_minimo" min="0" step="0.01" value="0">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="costo_envio" class="form-label">Costo de Envío ($)</label>
                            <input type="number" class="form-control" id="costo_envio" name="costo_envio" min="0" step="0.01" value="1.99">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="radio_entrega" class="form-label">Radio de Entrega (km)</label>
                            <input type="number" class="form-control" id="radio_entrega" name="radio_entrega" min="1" max="50" value="5">
                        </div>
                    </div>
                </div>
                
                <div class="form-navigation">
                    <div></div> <!-- Espacio vacío para alinear -->
                    <button type="button" class="btn-next" onclick="nextStep(1)">Siguiente <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- Paso 2: Ubicación -->
            <div class="form-section" id="seccion2" style="display: none;">
                <h2 class="section-title">Ubicación del Negocio</h2>
                
                <div class="form-group">
                    <label for="calle" class="form-label required-label">Calle</label>
                    <input type="text" class="form-control" id="calle" name="calle" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="numero" class="form-label required-label">Número</label>
                            <input type="text" class="form-control" id="numero" name="numero" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="colonia" class="form-label required-label">Colonia</label>
                            <input type="text" class="form-control" id="colonia" name="colonia" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ciudad" class="form-label required-label">Ciudad</label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="estado" class="form-label required-label">Estado</label>
                            <input type="text" class="form-control" id="estado" name="estado" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="codigo_postal" class="form-label">Código Postal</label>
                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ubicación en el Mapa</label>
                    <small class="form-text d-block mb-2">La ubicación se actualizará automáticamente al ingresar la dirección. También puedes hacer clic en el mapa para ajustar la posición exacta.</small>
                    <div id="map"></div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="latitud" class="form-label">Latitud</label>
                                <input type="text" class="form-control" id="latitud" name="latitud" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="longitud" class="form-label">Longitud</label>
                                <input type="text" class="form-control" id="longitud" name="longitud" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="btn-back" onclick="prevStep(2)"><i class="fas fa-arrow-left"></i> Anterior</button>
                    <button type="button" class="btn-next" onclick="nextStep(2)">Siguiente <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- Paso 3: Horarios -->
            <div class="form-section" id="seccion3" style="display: none;">
                <h2 class="section-title">Horarios de Atención</h2>
                
                <p class="mb-4">Define los días y horarios en que tu negocio estará disponible para recibir pedidos.</p>
                
                <?php
                $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                foreach ($dias as $index => $dia) {
                ?>
                <div class="horario-row">
                    <div class="dia-label"><?php echo $dia; ?></div>
                    <div class="horario-checkbox">
                        <input type="checkbox" id="abierto_<?php echo $index; ?>" name="abierto_<?php echo $index; ?>" checked onchange="toggleHorario(<?php echo $index; ?>)">
                        <label for="abierto_<?php echo $index; ?>">Abierto</label>
                    </div>
                    <div class="horario-inputs" id="horarios_<?php echo $index; ?>">
                        <input type="time" class="form-control" id="apertura_<?php echo $index; ?>" name="apertura_<?php echo $index; ?>" value="09:00">
                        <span class="horario-separator">hasta</span>
                        <input type="time" class="form-control" id="cierre_<?php echo $index; ?>" name="cierre_<?php echo $index; ?>" value="21:00">
                    </div>
                </div>
                <?php } ?>
                
                <div class="form-navigation">
                    <button type="button" class="btn-back" onclick="prevStep(3)"><i class="fas fa-arrow-left"></i> Anterior</button>
                    <button type="button" class="btn-next" onclick="nextStep(3)">Siguiente <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- Paso 4: Categorías -->
            <div class="form-section" id="seccion4" style="display: none;">
                <h2 class="section-title">Categorías de tu Negocio</h2>
                
                <p class="mb-4">Selecciona las categorías que mejor describen tu negocio. Esto ayudará a los clientes a encontrarte más fácilmente.</p>
                
                <div class="categoria-list">
                    <?php
                    if (!empty($todas_categorias)) {
                        foreach ($todas_categorias as $cat) {
                            echo '
                            <div class="categoria-item">
                                <input type="checkbox" id="cat_' . $cat['id_categoria'] . '" name="categorias[]" value="' . $cat['id_categoria'] . '">
                                <label for="cat_' . $cat['id_categoria'] . '">' . $cat['nombre'] . '</label>
                            </div>';
                        }
                    } else {
                        echo '<p>No hay categorías disponibles. Por favor, contacta al administrador.</p>';
                    }
                    ?>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="btn-back" onclick="prevStep(4)"><i class="fas fa-arrow-left"></i> Anterior</button>
                    <button type="button" class="btn-next" onclick="nextStep(4)">Siguiente <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
            
            <!-- Paso 5: Imágenes -->
            <div class="form-section" id="seccion5" style="display: none;">
                <h2 class="section-title">Imágenes de tu Negocio</h2>

                <p class="mb-4">Sube imágenes de tu negocio para hacerlo más atractivo para los clientes.</p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Logo del Negocio</label>
                            <div class="image-preview" id="logo-preview">
                                <i class="fas fa-image"></i>
                            </div>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" onchange="previewImage('logo', 'logo-preview')">
                            <small class="form-text">Se redimensionará automáticamente a 500x500px</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Imagen de Portada</label>
                            <div class="image-preview" id="portada-preview">
                                <i class="fas fa-image"></i>
                            </div>
                            <input type="file" class="form-control" id="imagen_portada" name="imagen_portada" accept="image/*" onchange="previewImage('imagen_portada', 'portada-preview')">
                            <small class="form-text">Se redimensionará automáticamente a 1200x400px</small>
                        </div>
                    </div>
                </div>

                <!-- IA: Menú Automático -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="ai-menu-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 15px; color: white;">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-robot" style="font-size: 2rem; margin-right: 15px;"></i>
                                <div>
                                    <h3 class="mb-1" style="font-size: 1.3rem;">🤖 Menú Inteligente con IA</h3>
                                    <p class="mb-0" style="font-size: 0.9rem; opacity: 0.9;">Sube una foto de tu menú y nuestra IA lo digitalizará automáticamente</p>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label class="form-label" style="color: white; font-weight: 600;">📷 Foto de tu Menú (Opcional)</label>
                                <div class="image-preview" id="menu-preview" style="border: 2px dashed rgba(255,255,255,0.5); background: rgba(255,255,255,0.1);">
                                    <i class="fas fa-file-image"></i>
                                    <p style="margin-top: 10px; font-size: 0.9rem;">Sube una imagen clara de tu menú</p>
                                </div>
                                <input type="file" class="form-control" id="menu_imagen" name="menu_imagen" accept="image/*" onchange="previewImage('menu_imagen', 'menu-preview')" style="margin-top: 10px;">
                                <small class="form-text" style="color: rgba(255,255,255,0.8);">
                                    ✨ La IA extraerá: nombres de platillos, descripciones, precios y categorías automáticamente<br>
                                    💡 Formatos aceptados: JPG, PNG, PDF. Asegúrate de que el texto sea legible.
                                </small>
                                <div id="ai-processing-status" style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 8px; display: none;">
                                    <i class="fas fa-spinner fa-spin"></i> <span id="ai-status-text">Procesando menú con IA...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-navigation mt-4">
                    <button type="button" class="btn-back" onclick="prevStep(5)"><i class="fas fa-arrow-left"></i> Anterior</button>
                    <button type="submit" class="btn-submit" id="btn-registrar">Registrar Negocio <i class="fas fa-check"></i></button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js"></script>
    <script>
        // MAPBOX ACCESS TOKEN - REEMPLAZA CON TU TOKEN
        mapboxgl.accessToken = '<?php echo getenv("MAPBOX_TOKEN") ?: ""; ?>';
        
        let map, marker;
        
        // Control de pasos del formulario
        function nextStep(currentStep) {
            // Validar campos del paso actual
            if (!validateStep(currentStep)) {
                return false;
            }
            
            // Ocultar paso actual
            document.getElementById('seccion' + currentStep).style.display = 'none';
            document.getElementById('step' + currentStep).classList.remove('active');
            document.getElementById('step' + currentStep).classList.add('completed');
            
            // Mostrar siguiente paso
            const nextStepNum = currentStep + 1;
            document.getElementById('seccion' + nextStepNum).style.display = 'block';
            document.getElementById('step' + nextStepNum).classList.add('active');
            
            // Inicializar el mapa si es el paso 2
            if (nextStepNum === 2 && !map) {
                initMap();
            }
            
            // Scroll al inicio
            window.scrollTo(0, 0);
        }
        
        function prevStep(currentStep) {
            // Ocultar paso actual
            document.getElementById('seccion' + currentStep).style.display = 'none';
            document.getElementById('step' + currentStep).classList.remove('active');
            
            // Mostrar paso anterior
            const prevStepNum = currentStep - 1;
            document.getElementById('seccion' + prevStepNum).style.display = 'block';
            document.getElementById('step' + prevStepNum).classList.add('active');
            document.getElementById('step' + prevStepNum).classList.remove('completed');
            
            // Scroll al inicio
            window.scrollTo(0, 0);
        }
        
        // Validación de campos por paso
        function validateStep(step) {
            let isValid = true;
            
            if (step === 1) {
                // Validar campos básicos
                if (!document.getElementById('nombre').value.trim()) {
                    document.getElementById('nombre').classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!document.getElementById('telefono').value.trim()) {
                    document.getElementById('telefono').classList.add('is-invalid');
                    isValid = false;
                }
            }
            else if (step === 2) {
                // Validar campos de ubicación
                if (!document.getElementById('calle').value.trim()) {
                    document.getElementById('calle').classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!document.getElementById('numero').value.trim()) {
                    document.getElementById('numero').classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!document.getElementById('colonia').value.trim()) {
                    document.getElementById('colonia').classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!document.getElementById('ciudad').value.trim()) {
                    document.getElementById('ciudad').classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!document.getElementById('estado').value.trim()) {
                    document.getElementById('estado').classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validar que se haya seleccionado una ubicación en el mapa
                if (!document.getElementById('latitud').value || !document.getElementById('longitud').value) {
                    alert('Por favor, selecciona la ubicación de tu negocio en el mapa.');
                    isValid = false;
                }
            }
            else if (step === 4) {
                // Validar que se haya seleccionado al menos una categoría
                const checkboxes = document.querySelectorAll('input[name="categorias[]"]:checked');
                if (checkboxes.length === 0) {
                    alert('Por favor, selecciona al menos una categoría para tu negocio.');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        // Mostrar/ocultar campos de horario
        function toggleHorario(dia) {
            const checkbox = document.getElementById(`abierto_${dia}`);
            const horariosDiv = document.getElementById(`horarios_${dia}`);
            
            if (checkbox.checked) {
                horariosDiv.style.display = 'flex';
            } else {
                horariosDiv.style.display = 'none';
            }
        }
        
        // Función para mostrar la vista previa de la imagen
        function previewImage(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.innerHTML = '';

                    // Si es la imagen del menú, mostrar mensaje de IA
                    if (inputId === 'menu_imagen') {
                        const statusDiv = document.getElementById('ai-processing-status');
                        const statusText = document.getElementById('ai-status-text');
                        const btnRegistrar = document.getElementById('btn-registrar');

                        statusDiv.style.display = 'block';
                        statusText.textContent = '✅ Imagen del menú cargada. La IA la procesará al registrar tu negocio.';
                        statusDiv.style.background = 'rgba(76, 175, 80, 0.3)';

                        // Cambiar texto del botón
                        btnRegistrar.innerHTML = '<i class="fas fa-robot"></i> Registrar y Procesar Menú con IA';
                    }
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        // Agregar indicador de procesamiento al enviar el formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registro-form');
            const btnRegistrar = document.getElementById('btn-registrar');
            const menuInput = document.getElementById('menu_imagen');

            if (form && btnRegistrar && menuInput) {
                form.addEventListener('submit', function(e) {
                    // Si hay imagen de menú, mostrar indicador de procesamiento
                    if (menuInput.files && menuInput.files[0]) {
                        btnRegistrar.disabled = true;
                        btnRegistrar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando con IA... Esto puede tardar 10-30 segundos';

                        const statusDiv = document.getElementById('ai-processing-status');
                        const statusText = document.getElementById('ai-status-text');
                        if (statusDiv && statusText) {
                            statusDiv.style.display = 'block';
                            statusDiv.style.background = 'rgba(33, 150, 243, 0.3)';
                            statusText.innerHTML = '<i class="fas fa-robot"></i> La IA está analizando tu menú... Por favor espera';
                        }
                    }
                });
            }
        });
        
        // Inicializar el mapa con Mapbox
        function initMap() {
            // Ubicación por defecto: Guadalajara, Jalisco, México
            const defaultLocation = [-103.3496, 20.6597];
            
            map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/streets-v12',
                center: defaultLocation,
                zoom: 13
            });
            
            // Crear marcador
            marker = new mapboxgl.Marker({
                draggable: true
            })
            .setLngLat(defaultLocation)
            .addTo(map);
            
            // Evento al arrastrar el marcador
            marker.on('dragend', function() {
                const lngLat = marker.getLngLat();
                document.getElementById('latitud').value = lngLat.lat.toFixed(6);
                document.getElementById('longitud').value = lngLat.lng.toFixed(6);
            });
            
            // Evento de clic en el mapa
            map.on('click', function(e) {
                const lngLat = e.lngLat;
                marker.setLngLat([lngLat.lng, lngLat.lat]);
                document.getElementById('latitud').value = lngLat.lat.toFixed(6);
                document.getElementById('longitud').value = lngLat.lng.toFixed(6);
            });
            
            // Establecer coordenadas iniciales
            document.getElementById('latitud').value = defaultLocation[1];
            document.getElementById('longitud').value = defaultLocation[0];
        }
        
        // Función para geocodificar usando Mapbox
        async function geocodeDireccion() {
            const calle = document.getElementById('calle').value.trim();
            const numero = document.getElementById('numero').value.trim();
            const colonia = document.getElementById('colonia').value.trim();
            const ciudad = document.getElementById('ciudad').value.trim();
            const estado = document.getElementById('estado').value.trim();
            
            // Solo geocodificar si tenemos información básica
            if (!calle || !ciudad || !estado) {
                return;
            }
            
            // Construir la dirección
            let direccion = '';
            if (calle && numero) {
                direccion += `${calle} ${numero}, `;
            } else if (calle) {
                direccion += `${calle}, `;
            }
            
            if (colonia) {
                direccion += `${colonia}, `;
            }
            
            direccion += `${ciudad}, ${estado}, México`;
            
            try {
                // Hacer petición a la API de Geocoding de Mapbox
                const response = await fetch(
                    `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(direccion)}.json?access_token=${mapboxgl.accessToken}&country=mx&limit=1`
                );
                
                if (!response.ok) {
                    throw new Error('Error en la geocodificación');
                }
                
                const data = await response.json();
                
                if (data.features && data.features.length > 0) {
                    const [lng, lat] = data.features[0].center;
                    
                    // Actualizar el mapa y marcador
                    if (map && marker) {
                        map.setCenter([lng, lat]);
                        marker.setLngLat([lng, lat]);
                        
                        // Actualizar los campos de coordenadas
                        document.getElementById('latitud').value = lat.toFixed(6);
                        document.getElementById('longitud').value = lng.toFixed(6);
                    }
                }
            } catch (error) {
                console.error('Error al geocodificar:', error);
            }
        }
        
        // Agregar event listeners para geocodificación automática
        document.addEventListener('DOMContentLoaded', function() {
            // Remover clase is-invalid cuando el usuario comienza a escribir
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            });
            
            // Geocodificar cuando se complete la dirección
            const camposDireccion = ['calle', 'numero', 'colonia', 'ciudad', 'estado'];
            camposDireccion.forEach(campo => {
                const elemento = document.getElementById(campo);
                if (elemento) {
                    // Usar un debounce para evitar demasiadas peticiones
                    let timeout;
                    elemento.addEventListener('input', function() {
                        clearTimeout(timeout);
                        timeout = setTimeout(geocodeDireccion, 1000); // Esperar 1 segundo después de que el usuario deje de escribir
                    });
                }
            });
        });
    </script>
     <?php include_once __DIR__ . '/includes/whatsapp_button.php'; ?>
</body>
</html>