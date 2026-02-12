<?php
require_once __DIR__ . '/../config/database.php';

class Negocio {
    private $conn;
    private $table = 'negocios';
    
    // Propiedades del negocio
    public $id_negocio;
    public $id_propietario;
    public $nombre;
    public $logo;
    public $imagen_portada;
    public $descripcion;
    public $telefono;
    public $email;
    public $calle;
    public $numero;
    public $colonia;
    public $ciudad;
    public $estado;
    public $codigo_postal;
    public $latitud;
    public $longitud;
    public $radio_entrega;
    public $tiempo_preparacion_promedio;
    public $pedido_minimo;
    public $costo_envio;
    public $activo;
    public $fecha_creacion;
    public $fecha_actualizacion;

    public $horarios;  // Agregado para evitar creación dinámica de propiedad
    
    // Constructor con conexión a BD
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los negocios activos
     * @return array
     */
    public function obtenerTodos() {
        $query = "SELECT n.*, 
                        (SELECT AVG(v.calificacion_negocio) FROM valoraciones v WHERE v.id_negocio = n.id_negocio) as rating
                  FROM " . $this->table . " n
                  WHERE n.activo = 1
                  ORDER BY rating DESC, n.nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $negocios = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $negocio = [
                'id_negocio' => $row['id_negocio'],
                'nombre' => $row['nombre'],
                'logo' => $row['logo'],
                'imagen_portada' => $row['imagen_portada'],
                'descripcion' => $row['descripcion'],
                'tiempo_preparacion_promedio' => $row['tiempo_preparacion_promedio'],
                'costo_envio' => $row['costo_envio'],
                'pedido_minimo' => $row['pedido_minimo'],
                'rating' => $row['rating'] ? $row['rating'] : 0,
                'categorias' => $this->obtenerCategoriasPorNegocio($row['id_negocio'])
            ];
            
            $negocios[] = $negocio;
        }
        
        return $negocios;
    }
    
    /**
     * Obtener negocios destacados (con mejor rating)
     * @param int $limite Número de negocios a obtener
     * @return array
     */
    public function obtenerDestacados($limite = 6) {
        $query = "SELECT n.*, 
                        (SELECT AVG(v.calificacion_negocio) FROM valoraciones v WHERE v.id_negocio = n.id_negocio) as rating
                  FROM " . $this->table . " n
                  WHERE n.activo = 1
                  ORDER BY rating DESC, n.nombre ASC
                  LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $negocios = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $negocio = [
                'id_negocio' => $row['id_negocio'],
                'nombre' => $row['nombre'],
                'logo' => $row['logo'],
                'imagen_portada' => $row['imagen_portada'],
                'tiempo_preparacion_promedio' => $row['tiempo_preparacion_promedio'],
                'costo_envio' => $row['costo_envio'],
                'rating' => $row['rating'] ? $row['rating'] : 0,
                'ciudad' => isset($row['ciudad']) ? $row['ciudad'] : '',
                'municipio' => isset($row['municipio']) ? $row['municipio'] : (isset($row['ciudad']) ? $row['ciudad'] : ''),
                'categorias' => $this->obtenerCategoriasPorNegocio($row['id_negocio'])
            ];
            
            $negocios[] = $negocio;
        }
        
        return $negocios;
    }
    
    /**
     * Obtener negocios por categoría
     * @param int $id_categoria ID de la categoría
     * @return array
     */
    public function obtenerPorCategoria($id_categoria) {
        $query = "SELECT n.*, 
                        (SELECT AVG(v.calificacion_negocio) FROM valoraciones v WHERE v.id_negocio = n.id_negocio) as rating
                  FROM " . $this->table . " n
                  JOIN relacion_negocio_categoria rnc ON n.id_negocio = rnc.id_negocio
                  WHERE n.activo = 1 AND rnc.id_categoria = :id_categoria
                  ORDER BY rating DESC, n.nombre ASC";
        
        $stmt = $this->conn->prepare($query);
$stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->execute();
        
        $negocios = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $negocio = [
                'id_negocio' => $row['id_negocio'],
                'nombre' => $row['nombre'],
                'logo' => $row['logo'],
                'imagen_portada' => $row['imagen_portada'],
                'tiempo_preparacion_promedio' => $row['tiempo_preparacion_promedio'],
                'costo_envio' => $row['costo_envio'],
                'rating' => $row['rating'] ? $row['rating'] : 0,
                'categorias' => $this->obtenerCategoriasPorNegocio($row['id_negocio'])
            ];
            
            $negocios[] = $negocio;
        }
        
        return $negocios;
    }
    
    /**
     * Obtener negocio por ID
     * @return boolean
     */
    public function obtenerPorId() {
        $query = "SELECT n.*, 
                        (SELECT AVG(v.calificacion_negocio) FROM valoraciones v WHERE v.id_negocio = n.id_negocio) as rating
                  FROM " . $this->table . " n
                  WHERE n.id_negocio = :id_negocio
                  LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_negocio', $this->id_negocio);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Asignar valores a propiedades del objeto
            $this->id_negocio = $row['id_negocio'];
            $this->id_propietario = $row['id_propietario'];
            $this->nombre = $row['nombre'];
            $this->logo = $row['logo'];
            $this->imagen_portada = $row['imagen_portada'];
            $this->descripcion = $row['descripcion'];
            $this->telefono = $row['telefono'];
            $this->email = $row['email'];
            $this->calle = $row['calle'];
            $this->numero = $row['numero'];
            $this->colonia = $row['colonia'];
            $this->ciudad = $row['ciudad'];
            $this->estado = $row['estado_geografico'];
            $this->codigo_postal = $row['codigo_postal'];
            $this->latitud = $row['latitud'];
            $this->longitud = $row['longitud'];
            $this->radio_entrega = $row['radio_entrega'];
            $this->tiempo_preparacion_promedio = $row['tiempo_preparacion_promedio'];
            $this->pedido_minimo = $row['pedido_minimo'];
            $this->costo_envio = $row['costo_envio'];
            $this->activo = $row['activo'];
            $this->fecha_creacion = $row['fecha_creacion'];
            $this->fecha_actualizacion = $row['fecha_actualizacion'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener los nombres de las categorías a las que pertenece un negocio
     * @param int $id_negocio ID del negocio
     * @return array
     */
    public function obtenerCategoriasPorNegocio($id_negocio) {
        $query = "SELECT c.nombre
                  FROM categorias_negocio c
                  JOIN relacion_negocio_categoria rnc ON c.id_categoria = rnc.id_categoria
                  WHERE rnc.id_negocio = :id_negocio";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_negocio', $id_negocio);
        $stmt->execute();
        
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $row['nombre'];
        }
        
        return $categorias;
    }
    
    /**
     * Buscar negocios por término de búsqueda (nombre o categoría)
     * @param string $termino Término de búsqueda
     * @return array
     */
    public function buscar($termino) {
        $termino = "%{$termino}%";
        
        $query = "SELECT DISTINCT n.*, 
                       (SELECT AVG(v.calificacion_negocio) FROM valoraciones v WHERE v.id_negocio = n.id_negocio) as rating
                  FROM " . $this->table . " n
                  LEFT JOIN relacion_negocio_categoria rnc ON n.id_negocio = rnc.id_negocio
                  LEFT JOIN categorias_negocio c ON rnc.id_categoria = c.id_categoria
                  WHERE n.activo = 1 
                     AND (n.nombre LIKE :termino1 
                          OR n.descripcion LIKE :termino2 
                          OR c.nombre LIKE :termino3)
                  ORDER BY rating DESC, n.nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':termino1', $termino);
        $stmt->bindParam(':termino2', $termino);
        $stmt->bindParam(':termino3', $termino);
        $stmt->execute();
        
        $negocios = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $negocio = [
                'id_negocio' => $row['id_negocio'],
                'nombre' => $row['nombre'],
                'logo' => $row['logo'],
                'imagen_portada' => $row['imagen_portada'],
                'tiempo_preparacion_promedio' => $row['tiempo_preparacion_promedio'],
                'costo_envio' => $row['costo_envio'],
                'rating' => $row['rating'] ? $row['rating'] : 0,
                'ciudad' => isset($row['ciudad']) ? $row['ciudad'] : '',
                'municipio' => isset($row['municipio']) ? $row['municipio'] : (isset($row['ciudad']) ? $row['ciudad'] : ''),
                'categorias' => $this->obtenerCategoriasPorNegocio($row['id_negocio'])
            ];
            
            $negocios[] = $negocio;
        }
        
        return $negocios;
    }
    
    /**
     * Crear un nuevo negocio
     * @return boolean
     */
    public function crear() {
        $query = "INSERT INTO " . $this->table . "
                  SET id_propietario = :id_propietario,
                      nombre = :nombre,
                      logo = :logo,
                      imagen_portada = :imagen_portada,
                      descripcion = :descripcion,
                      telefono = :telefono,
                      email = :email,
                      calle = :calle,
                      numero = :numero,
                      colonia = :colonia,
                      ciudad = :ciudad,
                      estado = :estado,
                      codigo_postal = :codigo_postal,
                      latitud = :latitud,
                      longitud = :longitud,
                      radio_entrega = :radio_entrega,
                      tiempo_preparacion_promedio = :tiempo_preparacion_promedio,
                      pedido_minimo = :pedido_minimo,
                      costo_envio = :costo_envio,
                      activo = :activo";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->calle = htmlspecialchars(strip_tags($this->calle));
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->colonia = htmlspecialchars(strip_tags($this->colonia));
        $this->ciudad = htmlspecialchars(strip_tags($this->ciudad));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->codigo_postal = htmlspecialchars(strip_tags($this->codigo_postal));
        
        // Vincular parámetros
        $stmt->bindParam(':id_propietario', $this->id_propietario);
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':logo', $this->logo);
        $stmt->bindParam(':imagen_portada', $this->imagen_portada);
        $stmt->bindParam(':descripcion', $this->descripcion);
        $stmt->bindParam(':telefono', $this->telefono);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':calle', $this->calle);
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':colonia', $this->colonia);
        $stmt->bindParam(':ciudad', $this->ciudad);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':codigo_postal', $this->codigo_postal);
        $stmt->bindParam(':latitud', $this->latitud);
        $stmt->bindParam(':longitud', $this->longitud);
        $stmt->bindParam(':radio_entrega', $this->radio_entrega);
        $stmt->bindParam(':tiempo_preparacion_promedio', $this->tiempo_preparacion_promedio);
        $stmt->bindParam(':pedido_minimo', $this->pedido_minimo);
        $stmt->bindParam(':costo_envio', $this->costo_envio);
        $stmt->bindParam(':activo', $this->activo);
        
        // Ejecutar con manejo de errores
        try {
            if ($stmt->execute()) {
                $this->id_negocio = $this->conn->lastInsertId();
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al crear negocio: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Actualizar negocio existente
     * @return boolean
     */
    public function actualizar() {
        $query = "UPDATE " . $this->table . "
                  SET nombre = :nombre,
                      logo = :logo,
                      imagen_portada = :imagen_portada,
                      descripcion = :descripcion,
                      telefono = :telefono,
                      email = :email,
                      calle = :calle,
                      numero = :numero,
                      colonia = :colonia,
                      ciudad = :ciudad,
                      estado = :estado,
                      codigo_postal = :codigo_postal,
                      latitud = :latitud,
                      longitud = :longitud,
                      radio_entrega = :radio_entrega,
                      tiempo_preparacion_promedio = :tiempo_preparacion_promedio,
                      pedido_minimo = :pedido_minimo,
                      costo_envio = :costo_envio,
                      activo = :activo
                  WHERE id_negocio = :id_negocio";
        
        if($this->id_propietario) {
            $query .= " AND id_propietario = :id_propietario";
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->id_negocio = htmlspecialchars(strip_tags($this->id_negocio));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->calle = htmlspecialchars(strip_tags($this->calle));
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->colonia = htmlspecialchars(strip_tags($this->colonia));
        $this->ciudad = htmlspecialchars(strip_tags($this->ciudad));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->codigo_postal = htmlspecialchars(strip_tags($this->codigo_postal));
        
        // Vincular parámetros
        $stmt->bindParam(':id_negocio', $this->id_negocio);
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':logo', $this->logo);
        $stmt->bindParam(':imagen_portada', $this->imagen_portada);
        $stmt->bindParam(':descripcion', $this->descripcion);
        $stmt->bindParam(':telefono', $this->telefono);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':calle', $this->calle);
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':colonia', $this->colonia);
        $stmt->bindParam(':ciudad', $this->ciudad);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':codigo_postal', $this->codigo_postal);
        $stmt->bindParam(':latitud', $this->latitud);
        $stmt->bindParam(':longitud', $this->longitud);
        $stmt->bindParam(':radio_entrega', $this->radio_entrega);
        $stmt->bindParam(':tiempo_preparacion_promedio', $this->tiempo_preparacion_promedio);
        $stmt->bindParam(':pedido_minimo', $this->pedido_minimo);
        $stmt->bindParam(':costo_envio', $this->costo_envio);
        $stmt->bindParam(':activo', $this->activo);
        
        if($this->id_propietario) {
            $stmt->bindParam(':id_propietario', $this->id_propietario);
        }
        
        // Ejecutar
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Cambiar estado de activo/inactivo
     * @param boolean $estado Nuevo estado
     * @return boolean
     */
    public function cambiarEstado($estado) {
        $query = "UPDATE " . $this->table . "
                  SET activo = :activo
                  WHERE id_negocio = :id_negocio";
        
        if($this->id_propietario) {
            $query .= " AND id_propietario = :id_propietario";
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id_negocio', $this->id_negocio);
        $stmt->bindParam(':activo', $estado, PDO::PARAM_BOOL);
        
        if($this->id_propietario) {
            $stmt->bindParam(':id_propietario', $this->id_propietario);
        }
        
        // Ejecutar
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar categorías de un negocio
     * @param array $categorias IDs de las categorías
     * @return boolean
     */
    public function actualizarCategorias($categorias) {
        // Primero eliminar todas las relaciones actuales
        $query_delete = "DELETE FROM relacion_negocio_categoria WHERE id_negocio = :id_negocio";
        $stmt_delete = $this->conn->prepare($query_delete);
        $stmt_delete->bindParam(':id_negocio', $this->id_negocio);
        $stmt_delete->execute();
        
        // Luego insertar las nuevas relaciones
        $query_insert = "INSERT INTO relacion_negocio_categoria (id_negocio, id_categoria) VALUES (:id_negocio, :id_categoria)";
        $stmt_insert = $this->conn->prepare($query_insert);
        
        // Insertar cada categoría
        foreach ($categorias as $id_categoria) {
            $stmt_insert->bindParam(':id_negocio', $this->id_negocio);
            $stmt_insert->bindParam(':id_categoria', $id_categoria);
            $stmt_insert->execute();
        }
        
        return true;
    }
    
    /**
     * Verificar si un negocio está dentro del radio de entrega de una ubicación
     * @param float $lat Latitud del usuario
     * @param float $lng Longitud del usuario
     * @return boolean
     */
    public function dentroDeRadioEntrega($lat, $lng) {
        // Cálculo de distancia usando la fórmula de Haversine
        $earth_radius = 6371; // Radio de la Tierra en km
        
        $dLat = deg2rad($lat - $this->latitud);
        $dLng = deg2rad($lng - $this->longitud);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($this->latitud)) * cos(deg2rad($lat)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earth_radius * $c;
        
        // Verificar si la distancia es menor o igual al radio de entrega
        return $distance <= $this->radio_entrega;
    }
    
    /**
     * Obtener horarios de un negocio
     * @return array
     */
    public function obtenerHorarios() {
        $query = "SELECT * FROM negocio_horarios WHERE id_negocio = :id_negocio ORDER BY dia_semana";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_negocio', $this->id_negocio);
        $stmt->execute();
        
        $horarios = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $horarios[] = [
                'id_horario' => $row['id_horario'],
                'dia_semana' => $row['dia_semana'],
                'hora_apertura' => $row['hora_apertura'],
                'hora_cierre' => $row['hora_cierre'],
                'activo' => $row['activo']
            ];
        }
        
        return $horarios;
    }
    
    /**
     * Verificar si el negocio está abierto actualmente
     * @return boolean
     */
    public function estaAbierto() {
        // Obtener día de la semana actual (0=Domingo, 1=Lunes, ..., 6=Sábado)
        $dia_actual = date('w');
        
        // Obtener hora actual en formato 24h (H:i:s)
        $hora_actual = date('H:i:s');
        
        // Buscar horario para el día actual
        $query = "SELECT * FROM negocio_horarios 
                  WHERE id_negocio = :id_negocio 
                    AND dia_semana = :dia_semana 
                    AND activo = 1 
                    AND hora_apertura <= :hora_apertura_check 
                    AND hora_cierre >= :hora_cierre_check
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_negocio', $this->id_negocio);
        $stmt->bindParam(':dia_semana', $dia_actual);
        $stmt->bindParam(':hora_apertura_check', $hora_actual);
        $stmt->bindParam(':hora_cierre_check', $hora_actual);
        $stmt->execute();
        
        // Si hay resultados, está abierto
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Guardar o actualizar horarios del negocio
     * @param array $horarios Array de horarios por día
     * @return boolean
     */
    public function guardarHorarios($horarios) {
        // Primero eliminar horarios existentes
        $query_delete = "DELETE FROM negocio_horarios WHERE id_negocio = :id_negocio";
        $stmt_delete = $this->conn->prepare($query_delete);
        $stmt_delete->bindParam(':id_negocio', $this->id_negocio);
        $stmt_delete->execute();
        
        // Insertar nuevos horarios
        $query_insert = "INSERT INTO negocio_horarios 
                         (id_negocio, dia_semana, hora_apertura, hora_cierre, activo) 
                         VALUES (:id_negocio, :dia_semana, :hora_apertura, :hora_cierre, :activo)";
        
        $stmt_insert = $this->conn->prepare($query_insert);
        
        foreach ($horarios as $dia => $horario) {
            $stmt_insert->bindParam(':id_negocio', $this->id_negocio);
            $stmt_insert->bindParam(':dia_semana', $dia);
            $stmt_insert->bindParam(':hora_apertura', $horario['hora_apertura']);
            $stmt_insert->bindParam(':hora_cierre', $horario['hora_cierre']);
            $stmt_insert->bindParam(':activo', $horario['activo']);
            $stmt_insert->execute();
        }
        
        return true;
    }
    
    /**
     * Obtener horarios formateados para mostrar
     * @return array
     */
    public function obtenerHorariosFormateados() {
        $dias_semana = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];
        
        $horarios = $this->obtenerHorarios();
        $horarios_formateados = [];
        
        foreach ($dias_semana as $dia_num => $dia_nombre) {
            $horario_dia = null;
            foreach ($horarios as $horario) {
                if ($horario['dia_semana'] == $dia_num) {
                    $horario_dia = $horario;
                    break;
                }
            }
            
            if ($horario_dia && $horario_dia['activo']) {
                $horarios_formateados[] = [
                    'dia' => $dia_nombre,
                    'hora_apertura' => date('H:i', strtotime($horario_dia['hora_apertura'])),
                    'hora_cierre' => date('H:i', strtotime($horario_dia['hora_cierre'])),
                    'activo' => true
                ];
            } else {
                $horarios_formateados[] = [
                    'dia' => $dia_nombre,
                    'hora_apertura' => null,
                    'hora_cierre' => null,
                    'activo' => false
                ];
            }
        }
        
        return $horarios_formateados;
    }

    /**
     * Obtener los negocios más cercanos a una ubicación
     * @param float $lat Latitud del usuario
     * @param float $lng Longitud del usuario
     * @param int $limite Número de negocios a obtener
     * @return array
     */
    public function obtenerCercanos($lat, $lng, $limite = 10) {
        // Fórmula de Haversine para calcular distancia entre dos puntos geográficos
        // Usamos parámetros con nombres únicos para evitar "Invalid parameter number"
        $query = "SELECT n.*, 
                       (SELECT AVG(v.calificacion_negocio) FROM valoraciones v WHERE v.id_negocio = n.id_negocio) as rating,
                       (6371 * acos(cos(radians(:lat1)) * cos(radians(n.latitud)) * cos(radians(n.longitud) - radians(:lng1)) + sin(radians(:lat2)) * sin(radians(n.latitud)))) AS distancia
                  FROM " . $this->table . " n   
                  WHERE n.activo = 1
                  HAVING distancia <= n.radio_entrega
                  ORDER BY distancia
                  LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':lat1', $lat);
        $stmt->bindParam(':lat2', $lat);
        $stmt->bindParam(':lng1', $lng);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $negocios = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $negocio = [
                'id_negocio' => $row['id_negocio'],
                'nombre' => $row['nombre'],
                'logo' => $row['logo'],
                'imagen_portada' => $row['imagen_portada'],
                'tiempo_preparacion_promedio' => $row['tiempo_preparacion_promedio'],
                'costo_envio' => $row['costo_envio'],
                'rating' => $row['rating'] ? $row['rating'] : 0,
                'distancia' => round($row['distancia'], 2),
                'categorias' => $this->obtenerCategoriasPorNegocio($row['id_negocio'])
            ];
            
            $negocios[] = $negocio;
        }
        
        return $negocios;
    }

    /**
     * Obtener negocios por ID de propietario
     * @param int $id_propietario ID del usuario propietario
     * @return PDOStatement
     */
    public function obtenerPorIdPropietario($id_propietario) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id_propietario = :id_propietario
                  ORDER BY nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_propietario', $id_propietario);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>