<?php
session_start();

// Incluir configuración de BD y modelos
require_once 'config/database.php';
require_once 'models/Usuario.php';
require_once 'models/Negocio.php';
require_once 'models/Categoria.php';

// Errores desactivados en producción - usar logs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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
            
            $_SESSION['mensaje_exito'] = "¡Tu negocio ha sido registrado correctamente! Ahora puedes administrarlo desde tu panel.";
            header("Location: admin/dashboard.php");
            exit;
        } else {
            $mensaje_error = "Ha ocurrido un error al registrar el negocio. Por favor, intenta de nuevo.";
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
    }
}

// Función para procesar imágenes cargadas
function procesarImagen($archivo, $tipo) {
    $directorio_destino = "assets/img/restaurants/";
    
    if (!file_exists($directorio_destino)) {
        mkdir($directorio_destino, 0777, true);
    }
    
    $nombre_archivo = uniqid($tipo . '_') . '_' . basename($archivo["name"]);
    $ruta_completa = $directorio_destino . $nombre_archivo;
    
    $tipo_archivo = strtolower(pathinfo($ruta_completa, PATHINFO_EXTENSION));
    if ($tipo_archivo != "jpg" && $tipo_archivo != "png" && $tipo_archivo != "jpeg") {
        return false;
    }
    
    if (move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
        return $ruta_completa;
    } else {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registra tu Negocio - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-light: #E3F2FD;
            --primary-dark: #0052cc;
            --secondary: #F8F8F8;
            --accent: #2C2C2C;
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
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
            padding: 15px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 25px;
            padding: 20px 0;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .page-subtitle {
            font-size: 1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

        .user-info {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .user-info h3 {
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 1.3rem;
        }

        .user-info p {
            margin: 0;
            color: #666;
        }

        /* Steps responsive */
        .steps-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
            overflow-x: auto;
            padding: 10px 0;
        }

        .steps-container::before {
            content: '';
            position: absolute;
            top: 34px;
            left: 25px;
            right: 25px;
            height: 2px;
            background-color: #ddd;
            z-index: 1;
        }

        .step {
            min-width: 60px;
            text-align: center;
            position: relative;
            z-index: 2;
            flex-shrink: 0;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background-color: white;
            border: 3px solid #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 700;
            color: #999;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .step.active .step-number {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .step.completed .step-number {
            background-color: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.75rem;
            color: #777;
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: var(--success);
            font-weight: 600;
        }

        .form-section {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.4rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--accent);
            font-size: 0.9rem;
        }

        .required-label::after {
            content: " *";
            color: var(--danger);
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
            transform: translateY(-1px);
        }

        .form-control.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-text {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }

        .image-preview {
            width: 100%;
            height: 160px;
            margin-bottom: 10px;
            border-radius: 10px;
            background-size: cover;
            background-position: center;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            border: 2px dashed #ddd;
            transition: all 0.3s ease;
        }

        .image-preview:hover {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .image-preview i {
            font-size: 2rem;
        }

        /* Horarios responsive */
        .horario-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .dia-label {
            min-width: 100px;
            font-weight: 600;
            color: var(--accent);
        }

        .horario-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .horario-inputs {
            display: flex;
            align-items: center;
            flex-grow: 1;
            gap: 10px;
            min-width: 200px;
        }

        .horario-inputs input[type="time"] {
            flex: 1;
            min-width: 80px;
        }

        .horario-separator {
            color: #666;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Categorías responsive */
        .categoria-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .categoria-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
        }

        .categoria-item:hover {
            border-color: var(--primary);
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .categoria-item input:checked + label {
            color: var(--primary);
            font-weight: 600;
        }

        .categoria-item input {
            margin-right: 10px;
            transform: scale(1.2);
        }

        /* Mapa responsive */
        #map {
            width: 100%;
            height: 250px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
        }

        /* Navegación responsive */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }

        .btn-back, .btn-next, .btn-submit {
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back {
            background-color: #f1f3f4;
            color: #666;
        }

        .btn-back:hover {
            background-color: #e1e3e4;
            transform: translateY(-1px);
        }

        .btn-next, .btn-submit {
            background-color: var(--primary);
            color: white;
        }

        .btn-next:hover, .btn-submit:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(1, 101, 255, 0.3);
        }

        /* Alerts */
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        /* Responsive breakpoints */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .page-title {
                font-size: 1.6rem;
            }

            .page-subtitle {
                font-size: 0.9rem;
            }

            .form-section {
                padding: 20px 15px;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .steps-container {
                padding: 5px;
            }

            .step {
                min-width: 50px;
            }

            .step-number {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
            }

            .step-label {
                font-size: 0.7rem;
            }

            .horario-row {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .dia-label {
                min-width: auto;
                text-align: center;
            }

            .horario-inputs {
                justify-content: center;
                min-width: auto;
            }

            .categoria-list {
                grid-template-columns: 1fr;
            }

            .form-navigation {
                flex-direction: column;
                gap: 10px;
            }

            .btn-back, .btn-next, .btn-submit {
                width: 100%;
                justify-content: center;
                padding: 15px;
            }

            #map {
                height: 200px;
            }

            .image-preview {
                height: 120px;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 15px 0;
                margin-bottom: 20px;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .user-info {
                padding: 15px;
            }

            .form-section {
                padding: 15px;
                margin-bottom: 15px;
            }

            .form-control {
                padding: 10px 12px;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .horario-inputs input[type="time"] {
                min-width: 70px;
            }

            .steps-container::before {
                display: none; /* Hide connecting line on very small screens */
            }
        }

        /* Smooth transitions */
        .form-section {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading state for form submission */
        .btn-submit:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .btn-submit:disabled:hover {
            transform: none;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Registra tu Negocio</h1>
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
                <div class="step-label">Básica</div>
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
            <!-- Paso 1: Información Básica -->
            <div class="form-section" id="seccion1">
                <h2 class="section-title"><i class="fas fa-info-circle me-2"></i>Información Básica</h2>
                
                <div class="form-group">
                    <label for="nombre" class="form-label required-label">Nombre del Negocio</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" placeholder="El nombre de tu restaurante" required>
                </div>
                
                <div class="form-group">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="4" placeholder="Describe tu negocio, especialidad, historia, etc."></textarea>
                    <small class="form-text">Ayuda a los clientes a conocer mejor tu negocio</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefono" class="form-label required-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="10 dígitos" maxlength="10" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="contacto@turestaurante.com">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tiempo_preparacion" class="form-label">Tiempo de Preparación (min)</label>
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
                <h2 class="section-title"><i class="fas fa-map-marker-alt me-2"></i>Ubicación del Negocio</h2>
                
                <div class="form-group">
                    <label for="calle" class="form-label required-label">Calle</label>
                    <input type="text" class="form-control" id="calle" name="calle" placeholder="Nombre de la calle" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="numero" class="form-label required-label">Número</label>
                            <input type="text" class="form-control" id="numero" name="numero" placeholder="123" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="colonia" class="form-label required-label">Colonia</label>
                            <input type="text" class="form-control" id="colonia" name="colonia" placeholder="Nombre de la colonia" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ciudad" class="form-label required-label">Ciudad</label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" placeholder="Ciudad" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="estado" class="form-label required-label">Estado</label>
                            <input type="text" class="form-control" id="estado" name="estado" placeholder="Estado" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="codigo_postal" class="form-label">Código Postal</label>
                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" placeholder="12345" maxlength="5">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ubicación en el Mapa</label>
                    <small class="form-text d-block mb-2">Arrastra el marcador para ajustar la ubicación exacta de tu negocio.</small>
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
                <h2 class="section-title"><i class="fas fa-clock me-2"></i>Horarios de Atención</h2>
                
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
                <h2 class="section-title"><i class="fas fa-tags me-2"></i>Categorías de tu Negocio</h2>
                
                <p class="mb-4">Selecciona las categorías que mejor describen tu negocio. Esto ayudará a los clientes a encontrarte más fácilmente.</p>
                
                <div class="categoria-list">
                    <?php
                    if (!empty($todas_categorias)) {
                        foreach ($todas_categorias as $cat) {
                            echo '
                            <div class="categoria-item">
                                <input type="checkbox" id="cat_' . $cat['id_categoria'] . '" name="categorias[]" value="' . $cat['id_categoria'] . '">
                                <label for="cat_' . $cat['id_categoria'] . '">' . htmlspecialchars($cat['nombre']) . '</label>
                            </div>';
                        }
                    } else {
                        echo '<p class="text-center">No hay categorías disponibles. Por favor, contacta al administrador.</p>';
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
                <h2 class="section-title"><i class="fas fa-images me-2"></i>Imágenes de tu Negocio</h2>
                
                <p class="mb-4">Sube imágenes de tu negocio para hacerlo más atractivo para los clientes.</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Logo del Negocio</label>
                            <div class="image-preview" id="logo-preview">
                                <i class="fas fa-camera"></i>
                            </div>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" onchange="previewImage('logo', 'logo-preview')">
                            <small class="form-text">Formato cuadrado recomendado (500x500px)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Imagen de Portada</label>
                            <div class="image-preview" id="portada-preview">
                                <i class="fas fa-camera"></i>
                            </div>
                            <input type="file" class="form-control" id="imagen_portada" name="imagen_portada" accept="image/*" onchange="previewImage('imagen_portada', 'portada-preview')">
                            <small class="form-text">Formato panorámico recomendado (1200x400px)</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="btn-back" onclick="prevStep(5)"><i class="fas fa-arrow-left"></i> Anterior</button>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-check me-2"></i>Registrar Negocio
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo getenv('GOOGLE_MAPS_API_KEY') ?: ''; ?>&callback=initMap" async defer></script>
    <script>
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
            
            // Scroll al inicio suave
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
            
            // Scroll al inicio suave
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Validación de campos por paso
        function validateStep(step) {
            let isValid = true;
            
            // Remover clases de error previas
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            if (step === 1) {
                // Validar campos básicos
                const nombre = document.getElementById('nombre');
                const telefono = document.getElementById('telefono');
                
                if (!nombre.value.trim()) {
                    nombre.classList.add('is-invalid');
                    isValid = false;
                }
                
                if (!telefono.value.trim()) {
                    telefono.classList.add('is-invalid');
                    isValid = false;
                } else if (!/^[0-9]{10}$/.test(telefono.value)) {
                    telefono.classList.add('is-invalid');
                    alert('El teléfono debe tener exactamente 10 dígitos');
                    isValid = false;
                }
                
                if (!isValid) {
                    alert('Por favor, completa todos los campos obligatorios correctamente.');
                }
            }
            else if (step === 2) {
                // Validar campos de ubicación
                const requiredFields = ['calle', 'numero', 'colonia', 'ciudad', 'estado'];
                
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    }
                });
                
                // Validar que se haya seleccionado una ubicación en el mapa
                if (!document.getElementById('latitud').value || !document.getElementById('longitud').value) {
                    alert('Por favor, selecciona la ubicación de tu negocio en el mapa.');
                    isValid = false;
                }
                
                if (!isValid) {
                    alert('Por favor, completa todos los campos de dirección y selecciona la ubicación en el mapa.');
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
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Inicializar el mapa
        let map, marker;
        
        function initMap() {
            const defaultLatLng = { lat: 19.4326, lng: -99.1332 }; // Ciudad de México por defecto
            
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 13,
                center: defaultLatLng,
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ]
            });
            
            marker = new google.maps.Marker({
                position: defaultLatLng,
                map,
                draggable: true,
                title: "Ubicación del negocio",
                animation: google.maps.Animation.DROP
            });
            
            // Actualizar coordenadas en los campos ocultos al arrastrar el marcador
            google.maps.event.addListener(marker, 'dragend', function() {
                const position = marker.getPosition();
                document.getElementById('latitud').value = position.lat().toFixed(6);
                document.getElementById('longitud').value = position.lng().toFixed(6);
            });
            
            // Establecer marcador al hacer clic en el mapa
            google.maps.event.addListener(map, 'click', function(event) {
                marker.setPosition(event.latLng);
                document.getElementById('latitud').value = event.latLng.lat().toFixed(6);
                document.getElementById('longitud').value = event.latLng.lng().toFixed(6);
            });
            
            // Establecer coordenadas iniciales
            document.getElementById('latitud').value = defaultLatLng.lat.toFixed(6);
            document.getElementById('longitud').value = defaultLatLng.lng.toFixed(6);
            
            // Geocodificar dirección cuando se complete
            document.getElementById('estado').addEventListener('blur', geocodeDireccion);
        }
        
        // Función para geocodificar la dirección ingresada
        function geocodeDireccion() {
            const calle = document.getElementById('calle').value;
            const numero = document.getElementById('numero').value;
            const colonia = document.getElementById('colonia').value;
            const ciudad = document.getElementById('ciudad').value;
            const estado = document.getElementById('estado').value;
            
            if (calle && numero && colonia && ciudad && estado) {
                const direccion = `${calle} ${numero}, ${colonia}, ${ciudad}, ${estado}`;
                
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ 'address': direccion }, function(results, status) {
                    if (status === 'OK' && results[0]) {
                        const location = results[0].geometry.location;
                        map.setCenter(location);
                        marker.setPosition(location);
                        marker.setAnimation(google.maps.Animation.BOUNCE);
                        setTimeout(() => marker.setAnimation(null), 2000);
                        
                        document.getElementById('latitud').value = location.lat().toFixed(6);
                        document.getElementById('longitud').value = location.lng().toFixed(6);
                    }
                });
            }
        }
        
        // Formatear teléfono
        document.getElementById('telefono').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // Formatear código postal
        document.getElementById('codigo_postal').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // Remover clase is-invalid cuando el usuario comienza a escribir
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            });
            
            // Prevenir envío múltiple del formulario
            document.getElementById('registro-form').addEventListener('submit', function() {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Registrando...';
            });
        });
        
        // Manejo de errores del mapa
        window.initMapError = function() {
            document.getElementById('map').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;"><i class="fas fa-exclamation-triangle me-2"></i>Error al cargar el mapa</div>';
        };
    </script>
</body>
</html>