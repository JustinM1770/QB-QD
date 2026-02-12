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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Negocio.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Promocion.php';

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado y es un negocio
$usuario_logueado = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$es_negocio = isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "negocio";

if (!$usuario_logueado || !$es_negocio) {
    header("Location: ../login.php?redirect=admin/negocio_configuracion.php");
    exit;
}

// Si está logueado, obtener información del usuario y su negocio
$usuario = new Usuario($db);
$usuario->id_usuario = $_SESSION["id_usuario"];
$usuario->obtenerPorId();

$negocio = new Negocio($db);
$producto = new Producto($db);
$promocion = new Promocion($db);

// Obtener información del negocio
$negocios = $negocio->obtenerPorIdPropietario($usuario->id_usuario);
$negocio_info = [
    'id_negocio' => 0,
    'nombre' => '',
    'descripcion' => '',
    'calle' => '',
    'numero' => '',
    'colonia' => '',
    'ciudad' => '',
    'estado' => '',
    'codigo_postal' => '',
    'telefono' => '',
    'email' => '',
    'latitud' => 0,
    'longitud' => 0,
    'radio_entrega' => 5,
    'tiempo_preparacion_promedio' => 30,
    'pedido_minimo' => 0,
    'costo_envio' => 25.00,
    'horarios' => '{}',
    'categorias' => [],
    'logo' => '',
    'imagen_portada' => ''
];

// Variable para verificar si el negocio ya existe
$negocio_existe = false;

// Si hay negocios, tomar el primero (asumiendo 1 negocio por usuario)
if (!empty($negocios) && is_array($negocios)) {
    $negocio_existe = true;
    $negocio_info = array_merge($negocio_info, $negocios[0]);
    $negocio->id_negocio = $negocio_info['id_negocio'];
    $negocio_info['categorias'] = $negocio->obtenerCategoriasPorNegocio($negocio->id_negocio);
} else {
    // Si no existe negocio, asegurar valores predeterminados
    $negocio_info = [
        'id_negocio' => 0,
        'nombre' => '',
        'descripcion' => '',
        'calle' => '',
        'numero' => '',
        'colonia' => '',
        'ciudad' => '',
        'estado' => '',
        'codigo_postal' => '',
        'telefono' => '',
        'email' => '',
        'latitud' => 20.6736,  // Coordenadas predeterminadas para Jalisco
        'longitud' => -103.3448,
        'radio_entrega' => 5,
        'tiempo_preparacion_promedio' => 30,
        'pedido_minimo' => 0,
        'costo_envio' => 25.00,
        'horarios' => '{}',
        'categorias' => []
    ];
}

// Obtener horarios desde la tabla negocio_horarios
$horarios = [];
$horarios_db = $negocio->obtenerHorarios();

$dias_semana = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];

// Convertir horarios de la base de datos al formato esperado por el formulario
foreach ($dias_semana as $dia_num => $dia_nombre) {
    $horario_encontrado = null;
    foreach ($horarios_db as $horario) {
        if ($horario['dia_semana'] == $dia_num) {
            $horario_encontrado = $horario;
            break;
        }
    }

    if ($horario_encontrado && $horario_encontrado['activo']) {
        $horarios[$dia_nombre] = date('H:i', strtotime($horario_encontrado['hora_apertura'])) . ' - ' . date('H:i', strtotime($horario_encontrado['hora_cierre']));
    } else {
        $horarios[$dia_nombre] = 'Cerrado';
    }
}

// Obtener todas las categorías para el formulario
$categoria = new Categoria($db);
$todas_categorias = $categoria->obtenerTodas();

// Obtener productos del negocio si existe
$productos = [];
$promociones = [];
if ($negocio_existe) {
    $productos = $producto->obtenerPorNegocio($negocio_info['id_negocio']);
    $promociones = $promocion->obtenerPorNegocio($negocio_info['id_negocio']);
}

// Determinar qué pestaña mostrar
$active_tab = 'informacion';
if (isset($_GET['tab'])) {
    $active_tab = $_GET['tab'];
}

// ═══════════════════════════════════════════════════════════════
// ✅ VERIFICAR SI EL NEGOCIO TIENE MEMBRESÍA PREMIUM
// ═══════════════════════════════════════════════════════════════
$es_negocio_premium = false;
$fecha_fin_premium = null;
$dias_restantes_premium = 0;

if ($negocio_existe) {
    try {
        $stmt_premium = $db->prepare("SELECT es_premium, fecha_fin_premium FROM negocios WHERE id_negocio = ?");
        $stmt_premium->execute([$negocio_info['id_negocio']]);
        $premium_info = $stmt_premium->fetch(PDO::FETCH_ASSOC);

        if ($premium_info && $premium_info['es_premium'] == 1) {
            $fecha_fin = $premium_info['fecha_fin_premium'];
            if ($fecha_fin === null || strtotime($fecha_fin) >= strtotime('today')) {
                $es_negocio_premium = true;
                $fecha_fin_premium = $fecha_fin;
                if ($fecha_fin) {
                    $dias_restantes_premium = max(0, floor((strtotime($fecha_fin) - strtotime('today')) / 86400));
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error verificando membresía premium: " . $e->getMessage());
    }
}

// Obtener estadísticas del mes para calcular ahorro potencial
$ventas_mes = 0;
$comisiones_pagadas = 0;
$ahorro_potencial_premium = 0;

if ($negocio_existe && !$es_negocio_premium) {
    try {
        $stmt_stats = $db->prepare("
            SELECT COALESCE(SUM(total_productos), 0) as ventas_mes
            FROM pedidos
            WHERE id_negocio = ?
            AND id_estado = 6
            AND MONTH(fecha_creacion) = MONTH(CURDATE())
            AND YEAR(fecha_creacion) = YEAR(CURDATE())
        ");
        $stmt_stats->execute([$negocio_info['id_negocio']]);
        $stats_mes = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        $ventas_mes = $stats_mes['ventas_mes'] ?? 0;

        // Calcular ahorro potencial: 10% - 8% = 2% de ahorro en comisiones
        $ahorro_potencial_premium = round($ventas_mes * 0.02, 2);
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
    }
}

// Procesar el formulario al enviar
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Determinar qué formulario se está procesando
    if (isset($_POST['form_type'])) {
        
        // Formulario de información básica
        if ($_POST['form_type'] == 'informacion_basica') {
            // Datos básicos del negocio
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);
            $calle = trim($_POST['calle']);
            $numero = trim($_POST['numero']);
            $colonia = trim($_POST['colonia']);
            $ciudad = trim($_POST['ciudad']);
            $estado = trim($_POST['estado']);
            $codigo_postal = trim($_POST['codigo_postal']);
            $telefono = trim($_POST['telefono']);
            $email = trim($_POST['email']);
            $costo_envio = 25.00; // Costo de envío fijo - no editable por el negocio
            $tiempo_preparacion = (int)$_POST['tiempo_preparacion'];
            $latitud = (float)$_POST['latitud'];
            $longitud = (float)$_POST['longitud'];
            $radio_entrega = isset($_POST['radio_entrega']) ? (float)$_POST['radio_entrega'] : 5;
            $pedido_minimo = isset($_POST['pedido_minimo']) ? (float)$_POST['pedido_minimo'] : 0;

            // Horarios - Convertir al formato para guardarHorarios()
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

            foreach ($dias_mapeo as $dia_num => $dia_lower) {
                $abierto = isset($_POST['abierto_' . $dia_lower]) ? true : false;

                if ($abierto) {
                    $hora_apertura = $_POST['apertura_' . $dia_lower] . ':00'; // Agregar segundos
                    $hora_cierre = $_POST['cierre_' . $dia_lower] . ':00'; // Agregar segundos
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

            // Categorías seleccionadas
            $categorias_seleccionadas = isset($_POST['categorias']) ? $_POST['categorias'] : [];

            // Validar campos requeridos
            $errores = [];

            if (empty($nombre)) {
                $errores[] = "El nombre del negocio es obligatorio.";
            }

            if (empty($calle) || empty($numero) || empty($colonia) || empty($ciudad)) {
                $errores[] = "La dirección completa es obligatoria.";
            }

            if (empty($telefono)) {
                $errores[] = "El teléfono es obligatorio.";
            }

            if (empty($categorias_seleccionadas)) {
                $errores[] = "Debes seleccionar al menos una categoría.";
            }

            // Si no hay errores, proceder a guardar los cambios
            if (empty($errores)) {
                // Configurar datos del negocio
                $negocio->id_propietario = $usuario->id_usuario;
                $negocio->nombre = $nombre;
                $negocio->descripcion = $descripcion;
                $negocio->calle = $calle;
                $negocio->numero = $numero;
                $negocio->colonia = $colonia;
                $negocio->ciudad = $ciudad;
                $negocio->estado = $estado;
                $negocio->codigo_postal = $codigo_postal;
                $negocio->telefono = $telefono;
                $negocio->email = $email;
                $negocio->latitud = $latitud;
                $negocio->longitud = $longitud;
                $negocio->radio_entrega = $radio_entrega;
                $negocio->tiempo_preparacion_promedio = $tiempo_preparacion;
                $negocio->pedido_minimo = $pedido_minimo;
                $negocio->costo_envio = $costo_envio;
                $negocio->horarios = json_encode($nuevo_horarios);

                // Manejar la carga de imágenes
                $logo_actual = $negocio_info['logo'] ?? '';
                $imagen_portada_actual = $negocio_info['imagen_portada'] ?? '';

                // Procesar imagen del logo si se ha cargado una nueva
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $ruta_logo = procesarImagen($_FILES['logo'], 'logo');
                    if ($ruta_logo) {
                        $negocio->logo = $ruta_logo;
                    }
                } else {
                    $negocio->logo = $logo_actual;
                }

                // Procesar imagen de portada si se ha cargado una nueva
                if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
                    $ruta_portada = procesarImagen($_FILES['portada'], 'portada');
                    if ($ruta_portada) {
                        $negocio->imagen_portada = $ruta_portada;
                    }
                } else {
                    $negocio->imagen_portada = $imagen_portada_actual;
                }

                // Guardar o actualizar negocio
                if ($negocio_existe) {
                    $negocio->id_negocio = $negocio_info['id_negocio'];
                    $resultado = $negocio->actualizar();
                } else {
                    $resultado = $negocio->crear();
                }

                if ($resultado) {
                    // Si se ha creado un nuevo negocio, obtener su ID
                    if (!$negocio_existe) {
                        $negocio_existe = true;
                        $negocio_info['id_negocio'] = $negocio->id_negocio;
                    }

                    // Actualizar categorías del negocio
                    $negocio->id_negocio = $negocio_info['id_negocio'];
                    $negocio->actualizarCategorias($categorias_seleccionadas);

                    $mensaje_exito = "La información del negocio ha sido guardada correctamente.";

                    // Actualizar la información del negocio para mostrar los cambios
                    $negocios_actualizados = $negocio->obtenerPorIdPropietario($usuario->id_usuario);
                    if (!empty($negocios_actualizados) && is_array($negocios_actualizados)) {
                        $negocio_info = array_merge($negocio_info, $negocios_actualizados[0]);
                        $negocio->id_negocio = $negocio_info['id_negocio'];
                        $negocio_info['categorias'] = $negocio->obtenerCategoriasPorNegocio($negocio->id_negocio);
                        $horarios = json_decode($negocio_info['horarios'], true);
                    }
                } else {
                    $mensaje_error = "Ha ocurrido un error al guardar la información. Por favor, intenta de nuevo.";
                }
            } else {
                $mensaje_error = implode("<br>", $errores);
            }
        }
        
        // Formulario para añadir/editar productos
        else if ($_POST['form_type'] == 'producto') {
            $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
            $nombre_producto = trim($_POST['nombre_producto']);
            $descripcion_producto = trim($_POST['descripcion_producto']);
            $precio = (float)$_POST['precio'];
            $categoria_id = (int)$_POST['categoria_producto'];
            $disponible = isset($_POST['disponible']) ? 1 : 0;
            $destacado = isset($_POST['destacado']) ? 1 : 0;
            
            // Validar campos
            $errores = [];
            
            if (empty($nombre_producto)) {
                $errores[] = "El nombre del producto es obligatorio.";
            }
            
            if ($precio <= 0) {
                $errores[] = "El precio debe ser mayor que cero.";
            }
            
            if (empty($errores)) {
                $producto->id_producto = $producto_id;
                $producto->id_negocio = $negocio_info['id_negocio'];
                $producto->id_categoria = $categoria_id;
                $producto->nombre = $nombre_producto;
                $producto->descripcion = $descripcion_producto;
                $producto->precio = $precio;
                $producto->disponible = $disponible;
                $producto->destacado = $destacado;
                
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
                
                if ($resultado) {
                    // Recargar productos
                    $productos = $producto->obtenerPorNegocio($negocio_info['id_negocio']);
                    $active_tab = 'menu';
                }
            } else {
                $mensaje_error_producto = implode("<br>", $errores);
                $active_tab = 'menu';
            }
        }
        
        // Formulario para añadir/editar promociones
        else if ($_POST['form_type'] == 'promocion') {
            $promocion_id = isset($_POST['promocion_id']) ? (int)$_POST['promocion_id'] : 0;
            $nombre_promocion = trim($_POST['nombre_promocion']);
            $descripcion_promocion = trim($_POST['descripcion_promocion']);
            $tipo_descuento = $_POST['tipo_descuento'];
            $valor_descuento = (float)$_POST['valor_descuento'];
            $codigo = trim($_POST['codigo']);
            $fecha_inicio = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            $limite_uso = (int)$_POST['limite_uso'];
            $monto_minimo = (float)$_POST['monto_minimo'];
            $activa = isset($_POST['activa']) ? 1 : 0;
            
            // Validar campos
            $errores = [];
            
            if (empty($nombre_promocion)) {
                $errores[] = "El nombre de la promoción es obligatorio.";
            }
            
            if ($valor_descuento <= 0) {
                $errores[] = "El valor del descuento debe ser mayor que cero.";
            }
            
            if (empty($fecha_inicio) || empty($fecha_fin)) {
                $errores[] = "Las fechas de inicio y fin son obligatorias.";
            }
            
            if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
                $errores[] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
            }
            
            if (empty($errores)) {
                $promocion->id_promocion = $promocion_id;
                $promocion->id_negocio = $negocio_info['id_negocio'];
                $promocion->nombre = $nombre_promocion;
                $promocion->descripcion = $descripcion_promocion;
                // Normalizar tipo_descuento: HTML envía 'percentage'/'fixed_amount', BD espera 'porcentaje'/'monto_fijo'
                $tipo_descuento_map = [
                    'percentage' => 'porcentaje',
                    'fixed_amount' => 'monto_fijo'
                ];
                
                $tipo_descuento_db = $tipo_descuento_map[$tipo_descuento] ?? 'porcentaje';
                $promocion->tipo_descuento = $tipo_descuento_db;
                $promocion->valor_descuento = $valor_descuento;
                $promocion->codigo = $codigo;
                $promocion->fecha_inicio = $fecha_inicio;
                $promocion->fecha_fin = $fecha_fin;
                $promocion->limite_uso = $limite_uso;
                $promocion->monto_pedido_minimo = $monto_minimo;
                $promocion->activa = $activa;
                
                // Guardar o actualizar promoción
                if ($promocion_id > 0) {
                    $resultado = $promocion->actualizar();
                    $mensaje_promocion = $resultado ? "Promoción actualizada correctamente." : "Error al actualizar la promoción.";
                } else {
                    $resultado = $promocion->crear();
                    $mensaje_promocion = $resultado ? "Promoción añadida correctamente." : "Error al añadir la promoción.";
                }
                
                if ($resultado) {
                    // Recargar promociones
                    $promociones = $promocion->obtenerPorNegocio($negocio_info['id_negocio']);
                    $active_tab = 'promociones';
                }
            } else {
                $mensaje_error_promocion = implode("<br>", $errores);
                $active_tab = 'promociones';
            }
        }
    }
}

// Función para procesar imágenes cargadas
function procesarImagen($archivo, $tipo) {
    $directorio_destino = $_SERVER['DOCUMENT_ROOT'] . "/assets/img/";
    $dir_negocios = $directorio_destino . "restaurants/";
    
    // Crear directorio si no existe
    if (!file_exists($dir_negocios)) {
        mkdir($dir_negocios, 0777, true);
    }
    
    // Generar nombre único para la imagen
    $nombre_archivo = uniqid($tipo . '_') . '_' . basename($archivo["name"]);
    $ruta_completa = $dir_negocios . $nombre_archivo;
    
    // Verificar tipo de archivo (solo permitir imágenes)
    $tipo_archivo = strtolower(pathinfo($ruta_completa, PATHINFO_EXTENSION));
    if ($tipo_archivo != "jpg" && $tipo_archivo != "png" && $tipo_archivo != "jpeg") {
        return false;
    }
    
    // Intentar mover el archivo cargado al directorio destino
    if (move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
        return "/assets/img/restaurants/" . $nombre_archivo;
    } else {
        error_log("Error al mover archivo cargado a $ruta_completa");
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $negocio_existe ? 'Gestionar Negocio' : 'Registrar Negocio'; ?> - QuickBite</title>
    <!-- Fonts: Inter and DM Sans -->
   <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@552&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        :root {
            --primary: #0165FF;         /* Azul principal */
            --primary-light: #E3F2FD;   /* Azul claro */
            --primary-dark: #0153CC;    /* Azul oscuro */
            --secondary: #F8F8F8;
            --accent: #FF9500;          /* Naranja acento */
            --accent-light: #FFE1AE;    /* Naranja claro */
            --dark: #2F2F2F;
            --light: #FAFAFA;
            --medium-gray: #888;
            --light-gray: #E8E8E8;
            --danger: #FF4D4D;
            --success: #4CAF50;
            --warning: #FFC107;
            --sidebar-width: 280px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            min-height: 100vh;
            display: flex;
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        /* Sidebar */
        .sidebar {
            font-family: 'League Spartan',  sans-serif;
            width: var(--sidebar-width);
            background-color: white;
            height: 100vh;
            position: fixed;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            z-index: 1000;
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
        }

        .sidebar-brand i {
            margin-right: 15px;
            font-size: 1.8rem;
        }

        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
            overflow-y: auto;
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
            transition: all 0.3s ease;
            position: relative;
            margin: 2px 0;
        }

        .menu-item i {
            margin-right: 15px;
            font-size: 1.1rem;
            color: var(--medium-gray);
            transition: all 0.3s ease;
            width: 20px;
            text-align: center;
        }

        .menu-item:hover {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .menu-item:hover i {
            color: var(--primary);
        }

        .menu-item.active {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            position: relative;
            border-radius: 0 30px 30px 0;
            margin-left: 0;
        }

        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary);
        }

        .menu-item.active i {
            color: var(--primary);
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--light-gray);
            background-color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
            font-weight: 600;
            font-size: 1.2rem;
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
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px;
            position: relative;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .page-description {
            color: var(--medium-gray);
            font-size: 1rem;
            max-width: 600px;
        }

        /* Tabs */
        .custom-tabs {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 30px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-item {
            padding: 15px 25px;
            font-weight: 500;
            color: var(--medium-gray);
            text-decoration: none;
            position: relative;
            white-space: nowrap;
        }

        .tab-item:hover {
            color: var(--primary);
        }

        .tab-item.active {
            color: var(--primary);
            font-weight: 600;
        }

        .tab-item.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--primary);
            border-radius: 3px 3px 0 0;
        }

        /* Card styles */
        .content-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        .content-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-title {
            font-size: 1.3rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 15px;
            color: var(--primary);
        }

        /* Form styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        .form-text {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-top: 8px;
            display: block;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 0.2rem;
        }

        .form-check-label {
            margin-left: 8px;
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background-color: var(--secondary);
            border: 1px solid var(--light-gray);
            border-radius: 10px 0 0 10px;
            padding: 0 15px;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            margin-bottom: 15px;
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            background-color: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--medium-gray);
            overflow: hidden;
            position: relative;
            border: 1px dashed var(--light-gray);
        }

        .image-preview i {
            font-size: 2.5rem;
            opacity: 0.5;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview:hover .image-overlay {
            opacity: 1;
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .image-overlay-content {
            color: white;
            text-align: center;
        }

        /* Horarios */
        .horario-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background-color: var(--secondary);
        }

        .horario-row:hover {
            background-color: var(--primary-light);
        }

        .dia-label {
            width: 120px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .horario-inputs {
            display: flex;
            align-items: center;
            flex-grow: 1;
            flex-wrap: wrap;
        }

        .horario-separator {
            margin: 0 15px;
            color: var(--medium-gray);
        }

        .horario-checkbox {
            margin-right: 20px;
        }

        /* Categorías */
        .categoria-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .categoria-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
        }

        .categoria-item:hover {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .categoria-item input {
            margin-right: 15px;
            width: 18px;
            height: 18px;
        }

        .categoria-item label {
            margin-bottom: 0;
            cursor: pointer;
            font-weight: 500;
            flex-grow: 1;
        }

        /* Alert messages */
        .alert {
            padding: 18px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 15px;
            font-size: 1.2rem;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.15);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(255, 77, 77, 0.15);
            color: var(--danger);
        }

        .alert-info {
            background-color: rgba(1, 101, 255, 0.15);
            color: var(--primary);
        }

        .alert-warning {
            background-color: rgba(255, 193, 7, 0.15);
            color: var(--warning);
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
        }

        .btn i {
            margin-right: 10px;
        }

        .btn-lg {
            padding: 15px 30px;
            font-size: 1rem;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(1, 101, 255, 0.3);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: var(--light-gray);
        }

        .btn-accent {
            background-color: var(--accent);
            color: white;
        }

        .btn-accent:hover {
            background-color: #E58600;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 149, 0, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary);
            background-color: transparent;
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #E53935;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 77, 77, 0.3);
        }

        .btn-text {
            background: none;
            border: none;
            color: var(--primary);
            padding: 5px 0;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-text:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Mapa */
        #map {
            width: 100%;
            height: 350px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 30px;
        }

        .badge-primary {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .badge-success {
            background-color: rgba(76, 175, 80, 0.15);
            color: var(--success);
        }

        .badge-danger {
            background-color: rgba(255, 77, 77, 0.15);
            color: var(--danger);
        }

        .badge-warning {
            background-color: rgba(255, 193, 7, 0.15);
            color: var(--warning);
        }

        .badge-accent {
            background-color: var(--accent-light);
            color: var(--accent);
        }

        /* Productos */
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .producto-card {
            background-color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .producto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        .producto-imagen {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .producto-imagen-placeholder {
            background-color: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--medium-gray);
            height: 100%;
        }

        .producto-imagen-placeholder i {
            font-size: 3rem;
            opacity: 0.5;
        }

        .producto-status {
            position: absolute;
            top: 15px;
            right: 15px;
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
            color: var(--primary);
            margin-bottom: 15px;
        }

        .producto-descripcion {
            color: var(--medium-gray);
            margin-bottom: 20px;
            font-size: 0.9rem;
            max-height: 80px;
            overflow: hidden;
        }

        .producto-actions {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid var(--light-gray);
            padding-top: 15px;
        }

        /* Tabla de promociones */
        .table {
            width: 100%;
            margin-bottom: 20px;
            color: var(--dark);
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th,
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid var(--light-gray);
        }

        .table th {
            font-weight: 600;
            background-color: var(--secondary);
            border-bottom: 2px solid var(--light-gray);
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: var(--primary-light);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        /* Modal */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background-color: var(--primary);
            color: white;
            border-bottom: none;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            border-top: 1px solid var(--light-gray);
            padding: 20px 25px;
            border-radius: 0 0 15px 15px;
        }

        /* Dashboard Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.2;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium-gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .stat-change-positive {
            color: var(--success);
        }

        .stat-change-negative {
            color: var(--danger);
        }

        .stat-change i {
            margin-right: 5px;
        }

        /* Toggle Sidebar Button */
        .toggle-sidebar {
            display: none;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1050;
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 15px;
            }
            
            .toggle-sidebar {
                display: flex;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1040;
                background-color: var(--primary);
                color: white;
                border-radius: 12px;
                width: 45px;
                height: 45px;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(1, 101, 255, 0.3);
                cursor: pointer;
                font-size: 1.2rem;
                border: none;
                transition: all 0.3s ease;
            }
            
            .toggle-sidebar:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 16px rgba(1, 101, 255, 0.4);
            }
            
            .page-header {
                margin-top: 50px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .page-title {
                font-size: 1.6rem;
            }
            
            .custom-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            
            .custom-tabs::-webkit-scrollbar {
                display: none;
            }
            
            .tab-item {
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            
            .horario-row {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px;
            }
            
            .dia-label {
                margin-bottom: 10px;
                width: 100%;
                font-size: 0.9rem;
            }
            
            .horario-inputs {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }
            
            .horario-inputs input {
                width: 100% !important;
            }
            
            .horario-separator {
                display: none;
            }
            
            .categoria-list {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }
            
            .categoria-item {
                padding: 12px;
                font-size: 0.9rem;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .btn-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 20px 15px;
            }
            
            #map {
                height: 250px;
            }
            
            .image-preview {
                height: 150px;
            }
        }

        @media (max-width: 768px) {
            .productos-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .page-title {
                font-size: 1.4rem;
            }
            
            .page-description {
                font-size: 0.9rem;
            }
            
            .content-card {
                padding: 20px 15px;
                margin-bottom: 20px;
                border-radius: 12px;
            }
            
            .card-title {
                font-size: 1.1rem;
                margin-bottom: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .form-control {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            
            .form-label {
                font-size: 0.9rem;
            }
            
            .producto-card {
                border-radius: 12px;
            }
            
            .producto-imagen {
                height: 180px;
            }
            
            .producto-contenido {
                padding: 15px;
            }
            
            .producto-nombre {
                font-size: 1.1rem;
            }
            
            .producto-precio {
                font-size: 1.2rem;
            }
            
            .producto-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .producto-actions .btn {
                width: 100%;
            }
            
            .table-responsive {
                border-radius: 10px;
                font-size: 0.85rem;
            }
            
            .table th,
            .table td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }
            
            .badge {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
            
            .alert {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
            
            .stat-value {
                font-size: 1.6rem;
            }
            
            .stat-icon {
                font-size: 2rem;
                opacity: 0.15;
            }
            
            .sidebar-footer {
                padding: 15px;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .user-name {
                font-size: 0.9rem;
            }
            
            .user-role {
                font-size: 0.75rem;
            }
            
            .menu-item {
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            
            .menu-section {
                font-size: 0.75rem;
                padding: 0 15px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .page-header {
                margin-top: 45px;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .page-description {
                font-size: 0.85rem;
            }
            
            .content-card {
                padding: 15px 12px;
                border-radius: 10px;
            }
            
            .card-title {
                font-size: 1rem;
                margin-bottom: 15px;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 0.85rem;
            }
            
            .btn-lg {
                padding: 12px 25px;
                font-size: 0.9rem;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .custom-tabs {
                gap: 5px;
            }
            
            .tab-item {
                padding: 10px 15px;
                font-size: 0.85rem;
            }
            
            .horario-row {
                padding: 10px;
            }
            
            .categoria-list {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .categoria-item {
                padding: 10px;
            }
            
            #map {
                height: 200px;
                border-radius: 8px;
            }
            
            .image-preview {
                height: 120px;
                border-radius: 8px;
            }
            
            .producto-imagen {
                height: 150px;
            }
            
            .producto-contenido {
                padding: 12px;
            }
            
            .producto-nombre {
                font-size: 1rem;
            }
            
            .producto-precio {
                font-size: 1.1rem;
            }
            
            .producto-descripcion {
                font-size: 0.85rem;
                max-height: 60px;
            }
            
            .modal-header {
                padding: 15px;
            }
            
            .modal-body {
                padding: 15px 12px;
            }
            
            .modal-footer {
                padding: 15px;
            }
            
            .modal-title {
                font-size: 1rem;
            }
            
            .alert {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
            
            .alert i {
                font-size: 1rem;
            }
            
            .stat-card {
                padding: 15px 12px;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.4rem;
            }
            
            .stat-change {
                font-size: 0.8rem;
            }
            
            .toggle-sidebar {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
                top: 12px;
                left: 12px;
            }
        }
        
        /* Overlay para cerrar sidebar en móvil */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
        
        @media (min-width: 993px) {
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* Mejoras táctiles para móvil */
        @media (max-width: 768px) {
            /* Mejorar área táctil de botones */
            .btn, .menu-item, .tab-item, .categoria-item {
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* Mejorar área táctil de checkboxes */
            .form-check-input {
                width: 22px;
                height: 22px;
                cursor: pointer;
            }
            
            .form-check-label {
                cursor: pointer;
                padding: 5px;
            }
            
            /* Hacer inputs más grandes en móvil */
            input[type="text"],
            input[type="email"],
            input[type="tel"],
            input[type="number"],
            input[type="time"],
            select,
            textarea {
                font-size: 16px !important; /* Prevenir zoom en iOS */
                min-height: 44px;
            }
            
            /* Mejorar tabla en móvil */
            .table-responsive {
                -webkit-overflow-scrolling: touch;
            }
            
            /* Hacer cards más fáciles de tocar */
            .producto-card,
            .stat-card,
            .content-card {
                -webkit-tap-highlight-color: rgba(1, 101, 255, 0.1);
            }
            
            /* Mejorar experiencia de scroll */
            body {
                -webkit-overflow-scrolling: touch;
            }
            
            /* Prevenir selección accidental de texto */
            .btn, .menu-item, .tab-item, .badge {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }
            
            /* Mejorar inputs de archivo */
            input[type="file"] {
                padding: 10px;
                font-size: 14px;
            }
            
            /* Sticky header para tablas */
            .table thead th {
                position: sticky;
                top: 0;
                z-index: 10;
                background-color: var(--secondary);
            }
        }
        
        /* Animaciones suaves */
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        .btn:active,
        .menu-item:active,
        .tab-item:active,
        .categoria-item:active {
            transform: scale(0.98);
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
    <button class="toggle-sidebar" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay para cerrar sidebar en móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?php echo $negocio_existe ? 'Gestionar mi Negocio' : 'Registrar mi Negocio'; ?></h1>
                <p class="page-description">
                    <?php echo $negocio_existe 
                        ? 'Administra la información, menú y promociones de tu negocio.' 
                        : 'Completa la información de tu negocio para comenzar a recibir pedidos.'; ?>
                </p>
            </div>
            
            <?php if ($negocio_existe): ?>
            <div>
                <a href="../<?php echo $negocio_info['id_negocio']; ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="fas fa-eye"></i> Ver mi Tienda
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['mensaje_bienvenida']) && $_SESSION['mensaje_bienvenida']): ?>
        <?php unset($_SESSION['mensaje_bienvenida']); ?>
        <div class="welcome-banner" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; color: white;">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="font-size: 3rem;">
                    <i class="fas fa-party-horn"></i>
                </div>
                <div style="flex: 1; min-width: 250px;">
                    <h3 style="margin: 0 0 8px 0; font-size: 1.4rem;">¡Bienvenido a QuickBite!</h3>
                    <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">
                        Tu negocio ya está registrado. Ahora puedes agregar tu menú, subir fotos y configurar todo a tu ritmo.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="menu.php" style="background: white; color: #667eea; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                        <i class="fas fa-plus"></i> Agregar Menú
                    </a>
                    <a href="?tab=imagenes" style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 0.9rem;">
                        <i class="fas fa-camera"></i> Subir Fotos
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($negocio_existe && !$es_negocio_premium): ?>
        <!-- ✅ BANNER PROMOCIÓN MEMBRESÍA PREMIUM PARA NEGOCIOS -->
        <div class="premium-promo-banner" id="premium-banner">
            <button class="close-banner" onclick="closePremiumBanner()">
                <i class="fas fa-times"></i>
            </button>
            <div class="premium-banner-content">
                <div class="premium-icon-badge">
                    <i class="fas fa-crown"></i>
                    <span class="badge-text">PRO</span>
                </div>
                <div class="premium-info">
                    <h3 class="premium-title">
                        <i class="fas fa-rocket"></i> ¡Potencia tu negocio con QuickBite PRO!
                    </h3>
                    <div class="premium-benefits">
                        <div class="benefit-item">
                            <i class="fas fa-percentage"></i>
                            <span>Solo <strong>8% comisión</strong> (en lugar de 10%)</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fab fa-whatsapp"></i>
                            <span>Bot de WhatsApp para pedidos automáticos</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-robot"></i>
                            <span>Menú Mágico con IA (foto a menú)</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Reportes y analytics avanzados</span>
                        </div>
                    </div>
                    <?php if ($ventas_mes > 0): ?>
                    <div class="savings-highlight">
                        <i class="fas fa-piggy-bank"></i>
                        Con tus ventas de este mes ($<?php echo number_format($ventas_mes, 0); ?>),
                        <strong>ahorrarías $<?php echo number_format($ahorro_potencial_premium, 2); ?></strong> en comisiones
                    </div>
                    <?php endif; ?>
                </div>
                <div class="premium-cta">
                    <div class="premium-price">
                        <span class="price-amount">$499</span>
                        <span class="price-period">/mes</span>
                    </div>
                    <a href="../membership_negocios.php" class="btn-upgrade">
                        <i class="fas fa-star"></i> Activar PRO
                    </a>
                    <p class="guarantee-text">
                        <i class="fas fa-shield-alt"></i> Cancela cuando quieras
                    </p>
                </div>
            </div>
        </div>

        <style>
            .premium-promo-banner {
                position: relative;
                background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
                border-radius: 16px;
                padding: 25px;
                margin-bottom: 25px;
                overflow: hidden;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            }

            .premium-promo-banner::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 100%;
                height: 200%;
                background: linear-gradient(45deg, transparent 30%, rgba(255, 215, 0, 0.1) 50%, transparent 70%);
                animation: shine 3s ease-in-out infinite;
            }

            @keyframes shine {
                0%, 100% { transform: translateX(-100%) rotate(45deg); }
                50% { transform: translateX(100%) rotate(45deg); }
            }

            .premium-promo-banner .close-banner {
                position: absolute;
                top: 15px;
                right: 15px;
                background: rgba(255, 255, 255, 0.1);
                border: none;
                color: rgba(255, 255, 255, 0.6);
                width: 30px;
                height: 30px;
                border-radius: 50%;
                cursor: pointer;
                transition: all 0.3s;
                z-index: 10;
            }

            .premium-promo-banner .close-banner:hover {
                background: rgba(255, 255, 255, 0.2);
                color: white;
            }

            .premium-banner-content {
                display: flex;
                align-items: center;
                gap: 25px;
                position: relative;
                z-index: 5;
                flex-wrap: wrap;
            }

            .premium-icon-badge {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 5px;
            }

            .premium-icon-badge i {
                font-size: 3rem;
                color: #ffd700;
                filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));
            }

            .premium-icon-badge .badge-text {
                background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
                color: #1e3a5f;
                padding: 3px 12px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 1px;
            }

            .premium-info {
                flex: 1;
                min-width: 280px;
            }

            .premium-title {
                color: white;
                font-size: 1.3rem;
                font-weight: 700;
                margin: 0 0 15px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .premium-title i {
                color: #ffd700;
            }

            .premium-benefits {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .benefit-item {
                display: flex;
                align-items: center;
                gap: 10px;
                color: rgba(255, 255, 255, 0.9);
                font-size: 0.9rem;
            }

            .benefit-item i {
                color: #4ade80;
                width: 20px;
            }

            .savings-highlight {
                margin-top: 15px;
                background: rgba(74, 222, 128, 0.15);
                border: 1px solid rgba(74, 222, 128, 0.3);
                border-radius: 10px;
                padding: 12px 15px;
                color: #4ade80;
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .premium-cta {
                text-align: center;
                min-width: 160px;
            }

            .premium-price {
                margin-bottom: 10px;
            }

            .price-amount {
                font-size: 2.5rem;
                font-weight: 800;
                color: white;
            }

            .price-period {
                font-size: 1rem;
                color: rgba(255, 255, 255, 0.7);
            }

            .btn-upgrade {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
                color: #1e3a5f;
                padding: 12px 25px;
                border-radius: 30px;
                font-weight: 700;
                text-decoration: none;
                transition: all 0.3s;
                box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
            }

            .btn-upgrade:hover {
                transform: translateY(-2px) scale(1.05);
                box-shadow: 0 8px 30px rgba(255, 215, 0, 0.4);
                color: #1e3a5f;
            }

            .guarantee-text {
                margin: 10px 0 0;
                font-size: 0.75rem;
                color: rgba(255, 255, 255, 0.5);
            }

            @media (max-width: 768px) {
                .premium-banner-content {
                    flex-direction: column;
                    text-align: center;
                }

                .premium-benefits {
                    grid-template-columns: 1fr;
                }

                .benefit-item {
                    justify-content: center;
                }

                .savings-highlight {
                    justify-content: center;
                    text-align: center;
                }
            }
        </style>

        <script>
            function closePremiumBanner() {
                const banner = document.getElementById('premium-banner');
                banner.style.transition = 'all 0.3s ease';
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(-20px)';
                setTimeout(() => banner.style.display = 'none', 300);
                // Guardar preferencia en localStorage (mostrar de nuevo en 7 días)
                localStorage.setItem('premium_banner_closed', Date.now());
            }

            // Verificar si debe mostrarse el banner
            document.addEventListener('DOMContentLoaded', function() {
                const closedTime = localStorage.getItem('premium_banner_closed');
                if (closedTime) {
                    const daysSinceClosed = (Date.now() - parseInt(closedTime)) / (1000 * 60 * 60 * 24);
                    if (daysSinceClosed < 7) {
                        document.getElementById('premium-banner').style.display = 'none';
                    } else {
                        localStorage.removeItem('premium_banner_closed');
                    }
                }
            });
        </script>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="custom-tabs">
            <a href="?tab=informacion" class="tab-item <?php echo $active_tab == 'informacion' ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i> Información
            </a>

            <?php if ($negocio_existe): ?>
            <a href="?tab=menu" class="tab-item <?php echo $active_tab == 'menu' ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i> Menú
            </a>
            <a href="?tab=promociones" class="tab-item <?php echo $active_tab == 'promociones' ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i> Promociones
            </a>
            <a href="?tab=horarios" class="tab-item <?php echo $active_tab == 'horarios' ? 'active' : ''; ?>">
                <i class="far fa-clock"></i> Horarios
            </a>
            <a href="?tab=entregas" class="tab-item <?php echo $active_tab == 'entregas' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Entregas
            </a>
            <a href="?tab=pagos" class="tab-item <?php echo $active_tab == 'pagos' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Pagos y Retiros
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($mensaje_exito)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $mensaje_exito; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje_error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $mensaje_error; ?>
        </div>
        <?php endif; ?>
        
        <!-- Tab: Información Básica -->
        <?php if ($active_tab == 'informacion'): ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
            <input type="hidden" name="form_type" value="informacion_basica">
            
            <!-- Información Básica -->
            <div class="content-card">
                <h2 class="card-title"><i class="fas fa-store"></i> Información Básica</h2>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nombre" class="form-label">Nombre del Negocio *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($negocio_info['nombre']); ?>" required>
                            <small class="form-text">Este nombre será visible para los clientes.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefono" class="form-label">Teléfono de Contacto *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($negocio_info['telefono']); ?>" required>
                            </div>
                            <small class="form-text">Número al que los clientes y repartidores podrán contactarte.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="4" placeholder="Describe tu negocio, especialidad, historia, etc."><?php echo htmlspecialchars($negocio_info['descripcion']); ?></textarea>
                    <small class="form-text">Una buena descripción ayuda a atraer clientes. Incluye información sobre tu especialidad, tradición, ingredientes, etc.</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email" class="form-label">Email de Contacto</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($negocio_info['email']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tiempo_preparacion" class="form-label">Tiempo de Preparación Promedio (min)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="number" class="form-control" id="tiempo_preparacion" name="tiempo_preparacion" min="5" max="120" value="<?php echo (int)$negocio_info['tiempo_preparacion_promedio']; ?>">
                            </div>
                            <small class="form-text">Tiempo promedio que tardas en preparar un pedido, ayuda a calcular los tiempos de entrega.</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="costo_envio" class="form-label">Costo de Envío</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control bg-light" id="costo_envio" value="25.00" readonly disabled>
                            </div>
                            <small class="form-text text-muted"><i class="fas fa-info-circle"></i> El costo de envío es fijo ($25 MXN) y es administrado por QuickBite.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="pedido_minimo" class="form-label">Pedido Mínimo ($)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="pedido_minimo" name="pedido_minimo" min="0" step="0.01" value="<?php echo number_format((float)$negocio_info['pedido_minimo'], 2, '.', ''); ?>">
                            </div>
                            <small class="form-text">Monto mínimo de compra para aceptar un pedido (0 = sin mínimo).</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Imágenes -->
            <div class="content-card">
                <h2 class="card-title"><i class="fas fa-images"></i> Imágenes del Negocio</h2>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Logo del Negocio</label>
                            <div class="image-preview" id="logo-preview" style="<?php echo !empty($negocio_info['logo']) ? "background-image: url('../" . htmlspecialchars($negocio_info['logo']) . "');" : ""; ?>">
                                <?php if (empty($negocio_info['logo'])): ?>
                                <i class="fas fa-image"></i>
                                <?php else: ?>
                                <div class="image-overlay">
                                    <div class="image-overlay-content">
                                        <p>Cambiar imagen</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" onchange="previewImage('logo', 'logo-preview')">
                            <small class="form-text">Formato cuadrado recomendado (500x500px). Aparecerá en listados, búsquedas y tu perfil.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Imagen de Portada</label>
                            <div class="image-preview" id="portada-preview" style="<?php echo !empty($negocio_info['imagen_portada']) ? "background-image: url('../" . htmlspecialchars($negocio_info['imagen_portada']) . "');" : ""; ?>">
                                <?php if (empty($negocio_info['imagen_portada'])): ?>
                                <i class="fas fa-image"></i>
                                <?php else: ?>
                                <div class="image-overlay">
                                    <div class="image-overlay-content">
                                        <p>Cambiar imagen</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" id="portada" name="portada" accept="image/*" onchange="previewImage('portada', 'portada-preview')">
                            <small class="form-text">Formato panorámico recomendado (1200x400px). Aparecerá como cabecera en tu perfil.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ubicación -->
            <div class="content-card">
                <h2 class="card-title"><i class="fas fa-map-marker-alt"></i> Ubicación</h2>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="calle" class="form-label">Calle *</label>
                            <input type="text" class="form-control" id="calle" name="calle" value="<?php echo htmlspecialchars($negocio_info['calle']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="numero" class="form-label">Número *</label>
                                    <input type="text" class="form-control" id="numero" name="numero" value="<?php echo htmlspecialchars($negocio_info['numero']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="colonia" class="form-label">Colonia *</label>
                                    <input type="text" class="form-control" id="colonia" name="colonia" value="<?php echo htmlspecialchars($negocio_info['colonia']); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ciudad" class="form-label">Ciudad *</label>
                            <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?php echo htmlspecialchars($negocio_info['ciudad']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="estado" class="form-label">Estado *</label>
                            <input type="text" class="form-control" id="estado" name="estado" value="<?php echo htmlspecialchars($negocio_info['estado']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="codigo_postal" class="form-label">Código Postal</label>
                            <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" value="<?php echo htmlspecialchars($negocio_info['codigo_postal']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ubicación en el Mapa</label>
                    <p class="form-text mb-3">Arrastra el marcador para ajustar la ubicación exacta de tu negocio.</p>
                    <div id="map"></div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="latitud" class="form-label">Latitud</label>
                            <input type="text" class="form-control" id="latitud" name="latitud" value="<?php echo $negocio_info['latitud']; ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="longitud" class="form-label">Longitud</label>
                            <input type="text" class="form-control" id="longitud" name="longitud" value="<?php echo $negocio_info['longitud']; ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="radio_entrega" class="form-label">Radio de Entrega (km)</label>
                            <input type="number" class="form-control" id="radio_entrega" name="radio_entrega" min="1" max="20" step="0.5" value="<?php echo htmlspecialchars($negocio_info['radio_entrega']); ?>">
                            <small class="form-text">Distancia máxima a la que realizarás entregas.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Horarios -->
            <div class="content-card">
                <h2 class="card-title"><i class="far fa-clock"></i> Horarios de Atención</h2>
                
                <?php
                $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                foreach ($dias as $dia) {
                    $dia_lower = strtolower($dia);
                    $horario_dia = $horarios[$dia] ?? 'Cerrado';
                    $abierto = $horario_dia !== 'Cerrado';
                    
                    // Extraer horas de apertura y cierre si está abierto
                    $hora_apertura = '09:00';
                    $hora_cierre = '18:00';
                    
                    if ($abierto && strpos($horario_dia, ' - ') !== false) {
                        list($hora_apertura, $hora_cierre) = explode(' - ', $horario_dia);
                    }
                ?>
                <div class="horario-row">
                    <div class="dia-label"><?php echo $dia; ?></div>
                    <div class="horario-checkbox">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="abierto_<?php echo $dia_lower; ?>" name="abierto_<?php echo $dia_lower; ?>" <?php echo $abierto ? 'checked' : ''; ?> onchange="toggleHorario('<?php echo $dia_lower; ?>')">
                            <label class="form-check-label" for="abierto_<?php echo $dia_lower; ?>">Abierto</label>
                        </div>
                    </div>
                    <div class="horario-inputs" id="horarios_<?php echo $dia_lower; ?>" style="display: <?php echo $abierto ? 'flex' : 'none'; ?>">
                        <input type="time" class="form-control" id="apertura_<?php echo $dia_lower; ?>" name="apertura_<?php echo $dia_lower; ?>" value="<?php echo $hora_apertura; ?>">
                        <span class="horario-separator">hasta</span>
                        <input type="time" class="form-control" id="cierre_<?php echo $dia_lower; ?>" name="cierre_<?php echo $dia_lower; ?>" value="<?php echo $hora_cierre; ?>">
                    </div>
                </div>
                <?php } ?>
            </div>
            
            <!-- Categorías -->
            <div class="content-card">
                <h2 class="card-title"><i class="fas fa-tags"></i> Categorías</h2>
                <p>Selecciona las categorías que mejor describan tu negocio:</p>
                
                <div class="categoria-list">
                    <?php
                    if (!empty($todas_categorias)) {
                        foreach ($todas_categorias as $cat) {
                            $checked = in_array($cat['id_categoria'], array_column($negocio_info['categorias'], 'id_categoria')) ? 'checked' : '';
                            echo '
                            <div class="categoria-item">
                                <input class="form-check-input" type="checkbox" id="cat_' . $cat['id_categoria'] . '" name="categorias[]" value="' . $cat['id_categoria'] . '" ' . $checked . '>
                                <label for="cat_' . $cat['id_categoria'] . '">' . htmlspecialchars($cat['nombre']) . '</label>
                            </div>';
                        }
                    } else {
                        echo '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay categorías disponibles. Contacta al administrador.</div>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Botón de guardar -->
            <div class="d-flex justify-content-end mb-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
        
        <?php elseif ($active_tab == 'menu' && $negocio_existe): ?>
        <!-- Tab: Menú -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="card-title mb-0"><i class="fas fa-utensils"></i> Menú de Productos</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">
                    <i class="fas fa-plus"></i> Añadir Producto
                </button>
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
            
            <?php if (empty($productos)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aún no has añadido productos a tu menú. Haz clic en "Añadir Producto" para comenzar.
                </div>
            <?php else: ?>
                <div class="productos-grid">
                    <?php foreach ($productos as $prod): ?>
                    <div class="producto-card">
                        <div class="producto-imagen" style="<?php echo !empty($prod['imagen']) ? "background-image: url('../" . htmlspecialchars($prod['imagen']) . "');" : ""; ?>">
                            <?php if (empty($prod['imagen'])): ?>
                            <div class="producto-imagen-placeholder">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="producto-status">
                                <?php if ($prod['destacado']): ?>
                                <span class="badge badge-accent"><i class="fas fa-star"></i> Destacado</span>
                                <?php endif; ?>
                                
                                <?php if (!$prod['disponible']): ?>
                                <span class="badge badge-danger"><i class="fas fa-ban"></i> No disponible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="producto-contenido">
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></h3>
                            <div class="producto-precio">$<?php echo number_format($prod['precio'], 2); ?></div>
                            <div class="producto-descripcion"><?php echo htmlspecialchars($prod['descripcion']); ?></div>
                            <div class="producto-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="editarProducto(<?php echo htmlspecialchars(json_encode($prod)); ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="confirmarEliminarProducto(<?php echo $prod['id_producto']; ?>, '<?php echo htmlspecialchars($prod['nombre']); ?>')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($active_tab == 'promociones' && $negocio_existe): ?>
        <!-- Tab: Promociones -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="card-title mb-0"><i class="fas fa-gift"></i> Promociones y Descuentos</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPromocion">
                    <i class="fas fa-plus"></i> Crear Promoción
                </button>
            </div>
            
            <?php if (isset($mensaje_promocion)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $mensaje_promocion; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($mensaje_error_promocion)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensaje_error_promocion; ?>
            </div>
            <?php endif; ?>
            
            <?php if (empty($promociones)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No tienes promociones activas. Crea una promoción para atraer más clientes.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Código</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promociones as $promo): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($promo['nombre']); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars(substr($promo['descripcion'], 0, 50)) . (strlen($promo['descripcion']) > 50 ? '...' : ''); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars($promo['codigo']); ?></code></td>
                                <td>
                                    <?php if ($promo['tipo_descuento'] == 'porcentaje'): ?>
                                    <span class="badge badge-primary">Porcentaje</span>
                                    <?php else: ?>
                                    <span class="badge badge-accent">Monto Fijo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($promo['tipo_descuento'] == 'porcentaje'): ?>
                                    <strong><?php echo number_format($promo['valor_descuento'], 0); ?>%</strong>
                                    <?php else: ?>
                                    <strong>$<?php echo number_format($promo['valor_descuento'], 2); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $fecha_inicio = new DateTime($promo['fecha_inicio']);
                                    $fecha_fin = new DateTime($promo['fecha_fin']);
                                    $hoy = new DateTime();
                                    
                                    echo $fecha_inicio->format('d/m/Y') . ' - ' . $fecha_fin->format('d/m/Y');
                                    
                                    if ($fecha_fin < $hoy) {
                                        echo '<div class="small text-danger">Vencida</div>';
                                    } elseif ($fecha_inicio > $hoy) {
                                        echo '<div class="small text-warning">Futura</div>';
                                    } else {
                                        echo '<div class="small text-success">Vigente</div>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($promo['activa']): ?>
                                    <span class="badge badge-success">Activa</span>
                                    <?php else: ?>
                                    <span class="badge badge-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarPromocion(<?php echo htmlspecialchars(json_encode($promo)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="confirmarEliminarPromocion(<?php echo $promo['id_promocion']; ?>, '<?php echo htmlspecialchars($promo['nombre']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Consejos para promociones efectivas:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Ofrece descuentos atractivos pero sostenibles para tu negocio.</li>
                        <li>Establece un monto mínimo de compra para maximizar el valor del pedido.</li>
                        <li>Promociona tus descuentos en tus redes sociales.</li>
                        <li>Utiliza promociones por tiempo limitado para crear urgencia.</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php elseif ($active_tab == 'horarios' && $negocio_existe): ?>
        <!-- Tab: Horarios -->
        <div class="content-card">
            <h2 class="card-title"><i class="far fa-clock"></i> Gestión de Horarios</h2>
            
            <p class="mb-4">Configura tus horarios de atención de forma más detallada, incluyendo horarios especiales y días festivos.</p>
            
            <!-- Contenido de horarios avanzados aquí -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Esta sección está en desarrollo. Por ahora, puedes configurar tus horarios básicos en la pestaña de Información.
            </div>
        </div>
        
        <?php elseif ($active_tab == 'entregas' && $negocio_existe): ?>
        <!-- Tab: Entregas -->
        <div class="content-card">
            <h2 class="card-title"><i class="fas fa-truck"></i> Configuración de Entregas</h2>
            
            <p class="mb-4">Configura los parámetros de entrega, costos adicionales por distancia y otras opciones.</p>
            
            <!-- Contenido de configuración de entregas aquí -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Esta sección está en desarrollo. Por ahora, puedes configurar el costo base de envío y radio de entrega en la pestaña de Información.
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Modal para Añadir/Editar Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProductoLabel">Añadir Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?tab=menu'; ?>" enctype="multipart/form-data">
                    <input type="hidden" name="form_type" value="producto">
                    <input type="hidden" name="producto_id" id="producto_id" value="0">
                    
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
                                    <small class="form-text">Describe los ingredientes, preparación o características especiales.</small>
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
                                                <?php 
                                                $categorias_producto = $producto->obtenerCategoriasProductos($negocio_info['id_negocio']);
                                                foreach ($categorias_producto as $cat) {
                                                    echo '<option value="' . $cat['id_categoria'] . '">' . htmlspecialchars($cat['nombre']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mt-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="disponible" name="disponible" checked>
                                        <label class="form-check-label" for="disponible">Disponible para ordenar</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="destacado" name="destacado">
                                        <label class="form-check-label" for="destacado">Producto destacado</label>
                                    </div>
                                    <small class="form-text">Los productos destacados aparecen primero en tu menú.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Imagen del Producto</label>
                                    <div class="image-preview" id="imagen-producto-preview">
                                        <i class="fas fa-hamburger"></i>
                                    </div>
                                    <input type="file" class="form-control" id="imagen_producto" name="imagen_producto" accept="image/*" onchange="previewImage('imagen_producto', 'imagen-producto-preview')">
                                    <small class="form-text">Imagen atractiva del producto (opcional).</small>
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
    
    <!-- Modal para Añadir/Editar Promoción -->
    <div class="modal fade" id="modalPromocion" tabindex="-1" aria-labelledby="modalPromocionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPromocionLabel">Crear Nueva Promoción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?tab=promociones'; ?>">
                    <input type="hidden" name="form_type" value="promocion">
                    <input type="hidden" name="promocion_id" id="promocion_id" value="0">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nombre_promocion" class="form-label">Nombre de la Promoción *</label>
                                    <input type="text" class="form-control" id="nombre_promocion" name="nombre_promocion" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="codigo" class="form-label">Código de Promoción</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                        <input type="text" class="form-control" id="codigo" name="codigo" placeholder="Ej: VERANO2025">
                                    </div>
                                    <small class="form-text">Código que los clientes ingresarán al hacer su pedido.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion_promocion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion_promocion" name="descripcion_promocion" rows="2"></textarea>
                            <small class="form-text">Descripción breve que explique la promoción.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tipo_descuento" class="form-label">Tipo de Descuento *</label>
                                    <select class="form-control" id="tipo_descuento" name="tipo_descuento" required>
                                        <option value="percentage">Porcentaje (%)</option>
                                        <option value="fixed_amount">Monto Fijo ($)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="valor_descuento" class="form-label">Valor del Descuento *</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="simbolo_descuento">%</span>
                                        <input type="number" class="form-control" id="valor_descuento" name="valor_descuento" min="0.01" step="0.01" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio *</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fecha_fin" class="form-label">Fecha de Fin *</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="monto_minimo" class="form-label">Monto Mínimo de Compra ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="monto_minimo" name="monto_minimo" min="0" step="0.01" value="0">
                                    </div>
                                    <small class="form-text">Importe mínimo del pedido para aplicar la promoción (0 = sin mínimo).</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="limite_uso" class="form-label">Límite de Usos</label>
                                    <input type="number" class="form-control" id="limite_uso" name="limite_uso" min="0" value="0">
                                    <small class="form-text">Veces que se puede usar la promoción (0 = ilimitado).</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="activa" name="activa" checked>
                                <label class="form-check-label" for="activa">Promoción activa</label>
                            </div>
                            <small class="form-text">Desactiva temporalmente la promoción sin eliminarla.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Promoción</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmación para Eliminar -->
    <div class="modal fade" id="modalConfirmDelete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteTitle">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmDeleteMessage">
                    ¿Estás seguro de que deseas eliminar este elemento?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmDelete">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />
<script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
<script src="../ws/business-orders.js"></script>
<script>
// Token de Mapbox
mapboxgl.accessToken = '<?php echo getenv("MAPBOX_TOKEN") ?: ""; ?>';

// Variables globales para el mapa
let map, marker, deliveryCircle;
let mapInitialized = false;
let fallbackAttempted = false; // Previene loop infinito si ambos mapas fallan

// Función para verificar soporte WebGL
function checkWebGLSupport() {
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        return !!gl;
    } catch (e) {
        return false;
    }
}

// Función para crear un círculo GeoJSON
function createGeoJSONCircle(center, radiusInKm, points = 64) {
    const coords = {
        latitude: center.lat,
        longitude: center.lng
    };

    const km = radiusInKm;
    const ret = [];
    const distanceX = km / (111.320 * Math.cos(coords.latitude * Math.PI / 180));
    const distanceY = km / 110.574;

    let theta, x, y;
    for (let i = 0; i < points; i++) {
        theta = (i / points) * (2 * Math.PI);
        x = distanceX * Math.cos(theta);
        y = distanceY * Math.sin(theta);
        ret.push([coords.longitude + x, coords.latitude + y]);
    }
    ret.push(ret[0]);
    
    return {
        "type": "Feature",
        "geometry": {
            "type": "Polygon",
            "coordinates": [ret]
        }
    };
}

// Función de fallback usando Leaflet si WebGL no está disponible
function initializeLeafletFallback() {
    console.log('Inicializando mapa con Leaflet (fallback)...');
    
    // Cargar Leaflet dinámicamente
    const leafletCSS = document.createElement('link');
    leafletCSS.rel = 'stylesheet';
    leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(leafletCSS);
    
    const leafletJS = document.createElement('script');
    leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    leafletJS.onload = function() {
        initLeafletMap();
    };
    document.head.appendChild(leafletJS);
}

function initLeafletMap() {
    try {
        const latInput = document.getElementById('latitud');
        const lngInput = document.getElementById('longitud');
        const radioInput = document.getElementById('radio_entrega');
        
        const lat = parseFloat(latInput.value) || 20.6736;
        const lng = parseFloat(lngInput.value) || -103.3448;
        const radio = parseFloat(radioInput.value) || 5;
        
        // Crear mapa con Leaflet
        const leafletMap = L.map('map').setView([lat, lng], 14);
        
        // Agregar tiles de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(leafletMap);
        
        // Crear marcador draggable
        const leafletMarker = L.marker([lat, lng], { draggable: true })
            .addTo(leafletMap)
            .bindPopup('<strong>Ubicación de tu negocio</strong><br>Arrastra para ajustar');
        
        // Crear círculo de entrega
        const deliveryCircle = L.circle([lat, lng], {
            color: '#0165FF',
            fillColor: '#0165FF',
            fillOpacity: 0.1,
            radius: radio * 1000 // Leaflet usa metros
        }).addTo(leafletMap);
        
        // Event listeners
        leafletMarker.on('dragend', function(e) {
            const position = e.target.getLatLng();
            latInput.value = position.lat.toFixed(6);
            lngInput.value = position.lng.toFixed(6);
            deliveryCircle.setLatLng(position);
        });
        
        leafletMap.on('click', function(e) {
            leafletMarker.setLatLng(e.latlng);
            latInput.value = e.latlng.lat.toFixed(6);
            lngInput.value = e.latlng.lng.toFixed(6);
            deliveryCircle.setLatLng(e.latlng);
        });
        
        radioInput.addEventListener('change', function() {
            const newRadius = parseFloat(this.value) || 5;
            deliveryCircle.setRadius(newRadius * 1000);
        });
        
        // Configurar geocodificación
        setupGeocodingForLeaflet(leafletMap, leafletMarker, deliveryCircle);
        
        console.log('Mapa Leaflet inicializado correctamente');
        mapInitialized = true;
        
    } catch (error) {
        console.error('Error al inicializar Leaflet:', error);
        showMapError('No se pudo cargar el mapa. Por favor, recarga la página.');
    }
}

// Función para configurar geocodificación con Leaflet
function setupGeocodingForLeaflet(leafletMap, leafletMarker, deliveryCircle) {
    const inputs = {
        calle: document.getElementById('calle'),
        numero: document.getElementById('numero'),
        colonia: document.getElementById('colonia'),
        ciudad: document.getElementById('ciudad'),
        estado: document.getElementById('estado')
    };

    const inputsExist = Object.values(inputs).every(input => input !== null);
    if (!inputsExist) return;

    function performGeocoding() {
        const address = [
            inputs.calle.value,
            inputs.numero.value,
            inputs.colonia.value,
            inputs.ciudad.value,
            inputs.estado.value,
            'México'
        ].filter(part => part.trim()).join(', ');

        if (address.length < 10) return;

        // Usar Nominatim para geocodificación gratuita
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    
                    leafletMap.setView([lat, lng], 16);
                    leafletMarker.setLatLng([lat, lng]);
                    deliveryCircle.setLatLng([lat, lng]);
                    
                    document.getElementById('latitud').value = lat.toFixed(6);
                    document.getElementById('longitud').value = lng.toFixed(6);
                }
            })
            .catch(error => {
                console.error('Error en geocodificación:', error);
            });
    }

    let geocodingTimeout;
    Object.values(inputs).forEach(input => {
        input.addEventListener('blur', function() {
            clearTimeout(geocodingTimeout);
            geocodingTimeout = setTimeout(performGeocoding, 500);
        });
    });
}

// Función principal para inicializar el mapa
function initializeMap() {
    if (mapInitialized) return;
    
    try {
        // Verificar que el contenedor del mapa existe
        const mapContainer = document.getElementById('map');
        if (!mapContainer) {
            console.error('Contenedor del mapa no encontrado');
            return;
        }

        // Verificar soporte WebGL
        if (!checkWebGLSupport()) {
            console.warn('WebGL no está disponible, usando fallback de Leaflet');
            showMapMessage('Cargando mapa alternativo...');
            fallbackAttempted = true;
            initializeLeafletFallback();
            return;
        }

        // Verificar que Mapbox GL JS está cargado
        if (typeof mapboxgl === 'undefined') {
            console.error('Mapbox GL JS no está cargado');
            initializeLeafletFallback();
            return;
        }

        // Obtener coordenadas desde los inputs
        const latInput = document.getElementById('latitud');
        const lngInput = document.getElementById('longitud');
        const radioInput = document.getElementById('radio_entrega');
        
        if (!latInput || !lngInput || !radioInput) {
            console.error('Inputs de coordenadas no encontrados');
            return;
        }

        const lat = parseFloat(latInput.value) || 20.6736;
        const lng = parseFloat(lngInput.value) || -103.3448;
        const radio = parseFloat(radioInput.value) || 5;
        
        console.log('Inicializando mapa Mapbox en coordenadas:', { lat, lng, radio });

        // Crear el mapa con manejo de errores
        map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [lng, lat],
            zoom: 14,
            language: 'es',
            attributionControl: false
        });

        // Manejar errores del mapa
        map.on('error', function(e) {
            if (fallbackAttempted) {
                console.error('Fallback ya fue intentado, deteniendo...');
                showMapError('No se pudo cargar ningún mapa. Por favor, recarga la página.');
                return;
            }
            
            console.error('Error de Mapbox:', e);
            showMapMessage('Error al cargar Mapbox, cambiando a mapa alternativo...');
            fallbackAttempted = true;
            
            setTimeout(() => {
                initializeLeafletFallback();
            }, 1000);
        });

        // Agregar controles de navegación
        map.addControl(new mapboxgl.NavigationControl(), 'top-right');
        map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

        // Crear marcador draggable
        marker = new mapboxgl.Marker({
            draggable: true,
            color: '#0165FF'
        })
        .setLngLat([lng, lat])
        .addTo(map);

        // Agregar popup al marcador
        const popup = new mapboxgl.Popup({ offset: 25 })
            .setHTML('<div><strong>Ubicación de tu negocio</strong><br>Arrastra para ajustar</div>');
        marker.setPopup(popup);

        // Esperar a que el mapa se cargue completamente
        map.on('load', function() {
            console.log('Mapa Mapbox cargado correctamente');
            
            // Crear círculo de entrega
            const center = { lat, lng };
            const circleGeoJSON = createGeoJSONCircle(center, radio);

            // Agregar fuente de datos para el círculo
            map.addSource('deliveryCircle', {
                type: 'geojson',
                data: circleGeoJSON
            });

            // Agregar capas del círculo
            map.addLayer({
                id: 'deliveryCircleFill',
                type: 'fill',
                source: 'deliveryCircle',
                layout: {},
                paint: {
                    'fill-color': '#0165FF',
                    'fill-opacity': 0.1
                }
            });

            map.addLayer({
                id: 'deliveryCircleOutline',
                type: 'line',
                source: 'deliveryCircle',
                layout: {},
                paint: {
                    'line-color': '#0165FF',
                    'line-width': 2,
                    'line-opacity': 0.8
                }
            });
            
            mapInitialized = true;
        });

        // Función para actualizar el círculo de entrega
        function updateDeliveryCircle() {
            if (!map.getSource('deliveryCircle')) return;
            
            const currentCenter = marker.getLngLat();
            const currentRadius = parseFloat(radioInput.value) || 5;
            const newCircle = createGeoJSONCircle(
                { lat: currentCenter.lat, lng: currentCenter.lng }, 
                currentRadius
            );
            
            map.getSource('deliveryCircle').setData(newCircle);
        }

        // Event listeners
        marker.on('dragend', function() {
            const lngLat = marker.getLngLat();
            latInput.value = lngLat.lat.toFixed(6);
            lngInput.value = lngLat.lng.toFixed(6);
            updateDeliveryCircle();
        });

        map.on('click', function(e) {
            marker.setLngLat(e.lngLat);
            latInput.value = e.lngLat.lat.toFixed(6);
            lngInput.value = e.lngLat.lng.toFixed(6);
            updateDeliveryCircle();
        });

        radioInput.addEventListener('change', function() {
            updateDeliveryCircle();
        });

        // Configurar geocodificación automática
        setupMapboxGeocoding();

    } catch (error) {
        console.error('Error al inicializar el mapa Mapbox:', error);
        showMapMessage('Error con Mapbox, cargando mapa alternativo...');
        setTimeout(() => {
            initializeLeafletFallback();
        }, 1000);
    }
}

// Función para configurar la geocodificación de Mapbox
function setupMapboxGeocoding() {
    const inputs = {
        calle: document.getElementById('calle'),
        numero: document.getElementById('numero'),
        colonia: document.getElementById('colonia'),
        ciudad: document.getElementById('ciudad'),
        estado: document.getElementById('estado')
    };

    const inputsExist = Object.values(inputs).every(input => input !== null);
    if (!inputsExist) return;

    function performGeocoding() {
        const address = [
            inputs.calle.value,
            inputs.numero.value,
            inputs.colonia.value,
            inputs.ciudad.value,
            inputs.estado.value,
            'México'
        ].filter(part => part.trim()).join(', ');

        if (address.length < 10) return;

        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(address)}.json?access_token=${mapboxgl.accessToken}&country=mx&language=es`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.features && data.features.length > 0) {
                    const location = data.features[0].center;
                    const coordinates = [location[0], location[1]];
                    
                    map.setCenter(coordinates);
                    marker.setLngLat(coordinates);
                    
                    document.getElementById('latitud').value = location[1].toFixed(6);
                    document.getElementById('longitud').value = location[0].toFixed(6);
                    
                    if (map.getSource('deliveryCircle')) {
                        const currentRadius = parseFloat(document.getElementById('radio_entrega').value) || 5;
                        const newCircle = createGeoJSONCircle(
                            { lat: location[1], lng: location[0] }, 
                            currentRadius
                        );
                        map.getSource('deliveryCircle').setData(newCircle);
                    }
                }
            })
            .catch(error => {
                console.error('Error en geocodificación:', error);
            });
    }

    let geocodingTimeout;
    Object.values(inputs).forEach(input => {
        input.addEventListener('blur', function() {
            clearTimeout(geocodingTimeout);
            geocodingTimeout = setTimeout(performGeocoding, 500);
        });
    });
}

// Función para mostrar mensajes en el mapa
function showMapMessage(message) {
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background-color: #f8f9fa; color: #6c757d; text-align: center; padding: 20px;">
                <div>
                    <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                    <h5>${message}</h5>
                </div>
            </div>
        `;
    }
}

// Función para mostrar error en el mapa
function showMapError(message) {
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background-color: #f8f9fa; color: #6c757d; text-align: center; padding: 20px;">
                <div>
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                    <h5>Error al cargar el mapa</h5>
                    <p>${message}</p>
                    <button class="btn btn-primary btn-sm" onclick="location.reload()">
                        <i class="fas fa-refresh"></i> Recargar página
                    </button>
                </div>
            </div>
        `;
    }
}

// Función para mostrar/ocultar los horarios según el checkbox
function toggleHorario(dia) {
    const checkbox = document.getElementById(`abierto_${dia}`);
    const horariosDiv = document.getElementById(`horarios_${dia}`);
    
    if (checkbox && horariosDiv) {
        horariosDiv.style.display = checkbox.checked ? 'flex' : 'none';
    }
}

// Función para mostrar la vista previa de la imagen
function previewImage(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (input && preview && input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.style.backgroundImage = `url('${e.target.result}')`;
            preview.innerHTML = '';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Funciones para modales
function editarProducto(producto) {
    const modal = document.getElementById('modalProducto');
    if (!modal) return;
    
    document.getElementById('modalProductoLabel').textContent = 'Editar Producto';
    document.getElementById('producto_id').value = producto.id_producto;
    document.getElementById('nombre_producto').value = producto.nombre;
    document.getElementById('descripcion_producto').value = producto.descripcion || '';
    document.getElementById('precio').value = producto.precio;
    document.getElementById('categoria_producto').value = producto.id_categoria || 0;
    document.getElementById('disponible').checked = producto.disponible == 1;
    document.getElementById('destacado').checked = producto.destacado == 1;
    
    const preview = document.getElementById('imagen-producto-preview');
    if (preview) {
        if (producto.imagen) {
            preview.style.backgroundImage = `url('../${producto.imagen}')`;
            preview.innerHTML = '';
        } else {
            preview.style.backgroundImage = '';
            preview.innerHTML = '<i class="fas fa-hamburger"></i>';
        }
    }
    
    new bootstrap.Modal(modal).show();
}

function editarPromocion(promocion) {
    const modal = document.getElementById('modalPromocion');
    if (!modal) return;
    
    document.getElementById('modalPromocionLabel').textContent = 'Editar Promoción';
    document.getElementById('promocion_id').value = promocion.id_promocion;
    document.getElementById('nombre_promocion').value = promocion.nombre;
    document.getElementById('descripcion_promocion').value = promocion.descripcion || '';
    document.getElementById('codigo').value = promocion.codigo || '';
    
    // Mapear valores de BD a valores del formulario
    const tipoDescuentoMap = {
        'porcentaje': 'percentage',
        'monto_fijo': 'fixed_amount'
    };
    document.getElementById('tipo_descuento').value = tipoDescuentoMap[promocion.tipo_descuento] || 'percentage';
    
    document.getElementById('valor_descuento').value = promocion.valor_descuento;
    document.getElementById('monto_minimo').value = promocion.monto_minimo;
    document.getElementById('limite_uso').value = promocion.limite_uso;
    document.getElementById('fecha_inicio').value = promocion.fecha_inicio;
    document.getElementById('fecha_fin').value = promocion.fecha_fin;
    document.getElementById('activa').checked = promocion.activa == 1;
    
    // Actualizar símbolo basado en el valor mapeado
    const simbolo = document.getElementById('simbolo_descuento');
    if (simbolo) {
        const tipoMapeado = tipoDescuentoMap[promocion.tipo_descuento] || 'percentage';
        simbolo.textContent = tipoMapeado === 'percentage' ? '%' : '$';
    }
    
    new bootstrap.Modal(modal).show();
}

function confirmarEliminarProducto(id, nombre) {
    const modal = document.getElementById('modalConfirmDelete');
    if (!modal) return;
    
    document.getElementById('confirmDeleteTitle').textContent = 'Eliminar Producto';
    document.getElementById('confirmDeleteMessage').textContent = `¿Estás seguro de que deseas eliminar el producto "${nombre}"?`;
    
    document.getElementById('btnConfirmDelete').onclick = function() {
        window.location.href = `eliminar_producto.php?id=${id}&redirect=negocio_configuracion.php?tab=menu`;
    };
    
    new bootstrap.Modal(modal).show();
}

function confirmarEliminarPromocion(id, nombre) {
    const modal = document.getElementById('modalConfirmDelete');
    if (!modal) return;
    
    document.getElementById('confirmDeleteTitle').textContent = 'Eliminar Promoción';
    document.getElementById('confirmDeleteMessage').textContent = `¿Estás seguro de que deseas eliminar la promoción "${nombre}"?`;
    
    document.getElementById('btnConfirmDelete').onclick = function() {
        window.location.href = `eliminar_promocion.php?id=${id}&redirect=negocio_configuracion.php?tab=promociones`;
    };
    
    new bootstrap.Modal(modal).show();
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
    `;
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Inicialización principal - EVENTO DOMCONTENTLOADED UNIFICADO
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando página de configuración del negocio...');
    
    // Inicializar el mapa si estamos en la pestaña de información
    const activeTab = '<?php echo $active_tab; ?>';
    if (activeTab === 'informacion') {
        console.log('Inicializando mapa...');
        setTimeout(initializeMap, 100); // ✅ CORREGIDO: initializeMap (no initMap)
    }
    
    // Toggle sidebar en móvil
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (toggleBtn && sidebar && sidebarOverlay) {
        // Abrir/cerrar sidebar
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });
        
        // Cerrar sidebar al hacer clic en el overlay
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Cerrar sidebar al hacer clic en un enlace del menú (en móvil)
        const menuItems = sidebar.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Manejar cambios de tamaño de ventana
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
    
    // Cambiar símbolo según tipo de descuento
    const tipoDescuento = document.getElementById('tipo_descuento');
    const simboloDescuento = document.getElementById('simbolo_descuento');
    
    if (tipoDescuento && simboloDescuento) {
        tipoDescuento.addEventListener('change', function() {
            simboloDescuento.textContent = this.value === 'percentage' ? '%' : '$';
        });
    }
    
    // Inicializar cliente WebSocket si está disponible
    <?php if ($negocio_existe && !empty($negocio_info['id_negocio'])): ?>
    try {
        // Verificar si BusinessClient está disponible
        if (typeof BusinessClient !== 'undefined') {
            const businessId = <?php echo json_encode($negocio_info['id_negocio']); ?>;
            console.log('Inicializando BusinessClient para negocio:', businessId);
            
            const businessClient = new BusinessClient(businessId);

            businessClient
                .on('onConnect', () => {
                    console.log('WebSocket conectado correctamente');
                    showNotification('Conexión en tiempo real establecida', 'success');
                })
                .on('onDisconnect', () => {
                    console.log('WebSocket desconectado');
                })
                .on('onNewOrder', (order) => {
                    console.log('Nuevo pedido recibido:', order);
                    showNotification(`Nuevo pedido #${order.orderId}`, 'success');
                })
                .on('onOrderStatusUpdate', (update) => {
                    console.log('Actualización de estado de pedido:', update);
                })
                .on('onCourierAssigned', (assignment) => {
                    console.log('Repartidor asignado:', assignment);
                })
                .on('onError', (error) => {
                    console.error('Error WebSocket:', error);
                });

            businessClient.connect();
        } else {
            console.log('BusinessClient no está disponible - funcionalidad en tiempo real deshabilitada');
        }
    } catch (error) {
        console.error('Error al inicializar BusinessClient:', error);
    }
    <?php else: ?>
    console.log('Negocio no válido para WebSocket');
    <?php endif; ?>
    
    // Reinicializar mapa cuando se cambie de pestaña (movido dentro del DOMContentLoaded)
    document.addEventListener('click', function(e) {
        if (e.target.matches('a[href*="tab=informacion"]')) {
            setTimeout(() => {
                if (document.getElementById('map') && !mapInitialized) {
                    initializeMap();
                }
            }, 200);
        }
    });
});
</script>

<!-- Incluir el cliente WebSocket para negocios (opcional) -->

</body>


</html>