<?php
class Repartidor {
    private $conn;
    private $table = 'repartidores';
    
    // Propiedades del repartidor según la estructura de la base de datos
    public $id;
    public $id_usuario;
    public $tipo_vehiculo;
    public $foto;
    public $licencia;
    public $seguro;
    public $activo;
    public $en_entrega;
    public $placa;
    public $modelo_vehiculo;
    public $fecha_registro;
    public $calificacion;
    public $lat;
    public $lng;
    public $token_notificacion;
    public $disponible;
    public $created_at;
    public $updated_at;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }

    // Verificar si el repartidor está disponible
    public function estaDisponible() {
        if (!$this->id) {
            return false;
        }
        
        $query = 'SELECT activo, en_entrega, disponible FROM ' . $this->table . ' WHERE id = ? LIMIT 1';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // El repartidor está disponible si:
        // 1. Está activo en el sistema
        // 2. No está en una entrega
        // 3. Ha marcado su disponibilidad
        return $row && $row['activo'] == 1 && $row['en_entrega'] == 0 && $row['disponible'] == 1;
    }

    // Cambiar estado de disponibilidad
    public function cambiarDisponibilidad($disponible) {
        $query = 'UPDATE ' . $this->table . ' 
                 SET disponible = :disponible, 
                     updated_at = NOW() 
                 WHERE id = :id';
                    
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':disponible', $disponible, PDO::PARAM_INT);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    // Obtener todos los repartidores
    public function obtenerTodos() {
        $query = 'SELECT 
                    r.id, 
                    r.nombre, 
                    r.apellido, 
                    r.email, 
                    r.telefono, 
                    r.foto,
                    r.documento_identidad,
                    r.tipo_vehiculo, 
                    r.placa_vehiculo,
                    r.estado, 
                    r.calificacion_promedio,
                    r.fecha_registro,
                    r.ultima_conexion,
                    r.ubicacion_actual_lat,
                    r.ubicacion_actual_lng,
                    r.disponible
                FROM 
                    ' . $this->table . ' r
                ORDER BY
                    r.disponible DESC, r.calificacion_promedio DESC';
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener un repartidor por ID
    public function obtenerPorId() {
        $query = 'SELECT 
                    r.id,
                    r.id_usuario,
                    r.tipo_vehiculo,
                    r.foto,
                    r.licencia,
                    r.seguro,
                    r.activo,
                    r.en_entrega,
                    r.placa,
                    r.modelo_vehiculo,
                    r.fecha_registro,
                    r.calificacion,
                    r.lat,
                    r.lng,
                    r.token_notificacion,
                    r.disponible,
                    r.created_at,
                    r.updated_at
                FROM 
                    ' . $this->table . ' r
                WHERE 
                    r.id = ?
                LIMIT 0,1';
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Asignar valores
            $this->id_usuario = $row['id_usuario'];
            $this->tipo_vehiculo = $row['tipo_vehiculo'];
            $this->foto = $row['foto'];
            $this->licencia = $row['licencia'];
            $this->seguro = $row['seguro'];
            $this->activo = $row['activo'];
            $this->en_entrega = $row['en_entrega'];
            $this->placa = $row['placa'];
            $this->modelo_vehiculo = $row['modelo_vehiculo'];
            $this->fecha_registro = $row['fecha_registro'];
            $this->calificacion = $row['calificacion'];
            $this->lat = $row['lat'];
            $this->lng = $row['lng'];
            $this->token_notificacion = $row['token_notificacion'];
            $this->disponible = $row['disponible'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Crear un nuevo repartidor
    public function crear() {
        $query = 'INSERT INTO ' . $this->table . '
                SET
                    nombre = :nombre,
                    apellido = :apellido,
                    email = :email,
                    telefono = :telefono,
                    foto = :foto,
                    documento_identidad = :documento_identidad,
                    tipo_vehiculo = :tipo_vehiculo,
                    placa_vehiculo = :placa_vehiculo,
                    estado = :estado,
                    calificacion_promedio = :calificacion_promedio,
                    fecha_registro = NOW(),
                    ultima_conexion = NOW(),
                    ubicacion_actual_lat = :ubicacion_actual_lat,
                    ubicacion_actual_lng = :ubicacion_actual_lng,
                    disponible = :disponible';
                    
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->apellido = htmlspecialchars(strip_tags($this->apellido));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->foto = htmlspecialchars(strip_tags($this->foto));
        $this->documento_identidad = htmlspecialchars(strip_tags($this->documento_identidad));
        $this->tipo_vehiculo = htmlspecialchars(strip_tags($this->tipo_vehiculo));
        $this->placa_vehiculo = htmlspecialchars(strip_tags($this->placa_vehiculo));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        
        // Bind de parámetros
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':apellido', $this->apellido);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefono', $this->telefono);
        $stmt->bindParam(':foto', $this->foto);
        $stmt->bindParam(':documento_identidad', $this->documento_identidad);
        $stmt->bindParam(':tipo_vehiculo', $this->tipo_vehiculo);
        $stmt->bindParam(':placa_vehiculo', $this->placa_vehiculo);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':calificacion_promedio', $this->calificacion_promedio);
        $stmt->bindParam(':ubicacion_actual_lat', $this->ubicacion_actual_lat);
        $stmt->bindParam(':ubicacion_actual_lng', $this->ubicacion_actual_lng);
        $stmt->bindParam(':disponible', $this->disponible);
        
        // Ejecutar query
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Actualizar un repartidor
    public function actualizar() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    nombre = :nombre,
                    apellido = :apellido,
                    email = :email,
                    telefono = :telefono,
                    foto = :foto,
                    documento_identidad = :documento_identidad,
                    tipo_vehiculo = :tipo_vehiculo,
                    placa_vehiculo = :placa_vehiculo,
                    estado = :estado,
                    calificacion_promedio = :calificacion_promedio,
                    ultima_conexion = NOW(),
                    ubicacion_actual_lat = :ubicacion_actual_lat,
                    ubicacion_actual_lng = :ubicacion_actual_lng,
                    disponible = :disponible
                WHERE
                    id = :id';
                    
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->apellido = htmlspecialchars(strip_tags($this->apellido));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->foto = htmlspecialchars(strip_tags($this->foto));
        $this->documento_identidad = htmlspecialchars(strip_tags($this->documento_identidad));
        $this->tipo_vehiculo = htmlspecialchars(strip_tags($this->tipo_vehiculo));
        $this->placa_vehiculo = htmlspecialchars(strip_tags($this->placa_vehiculo));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        
        // Bind de parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':apellido', $this->apellido);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefono', $this->telefono);
        $stmt->bindParam(':foto', $this->foto);
        $stmt->bindParam(':documento_identidad', $this->documento_identidad);
        $stmt->bindParam(':tipo_vehiculo', $this->tipo_vehiculo);
        $stmt->bindParam(':placa_vehiculo', $this->placa_vehiculo);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':calificacion_promedio', $this->calificacion_promedio);
        $stmt->bindParam(':ubicacion_actual_lat', $this->ubicacion_actual_lat);
        $stmt->bindParam(':ubicacion_actual_lng', $this->ubicacion_actual_lng);
        $stmt->bindParam(':disponible', $this->disponible);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Actualizar ubicación y disponibilidad
    public function actualizarUbicacion() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    ubicacion_actual_lat = :ubicacion_actual_lat,
                    ubicacion_actual_lng = :ubicacion_actual_lng,
                    ultima_conexion = NOW(),
                    disponible = :disponible
                WHERE
                    id = :id';
                    
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind de parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':ubicacion_actual_lat', $this->ubicacion_actual_lat);
        $stmt->bindParam(':ubicacion_actual_lng', $this->ubicacion_actual_lng);
        $stmt->bindParam(':disponible', $this->disponible);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Cambiar estado
    public function cambiarEstado() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    estado = :estado,
                    ultima_conexion = NOW()
                WHERE
                    id = :id';
                    
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        
        // Bind de parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':estado', $this->estado);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Eliminar un repartidor
    public function eliminar() {
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind de parámetro
        $stmt->bindParam(':id', $this->id);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Buscar repartidores disponibles cerca de una ubicación
    public function buscarDisponiblesCerca($lat, $lng, $radio_km = 5) {
        // Cálculo de distancia usando la fórmula de Haversine
        $query = 'SELECT 
                    r.id, 
                    r.nombre, 
                    r.apellido,
                    r.telefono,
                    r.foto,
                    r.tipo_vehiculo,
                    r.estado,
                    r.calificacion_promedio,
                    r.ubicacion_actual_lat,
                    r.ubicacion_actual_lng,
                    r.disponible,
                    (6371 * acos(cos(radians(:lat)) * cos(radians(r.ubicacion_actual_lat)) * 
                    cos(radians(r.ubicacion_actual_lng) - radians(:lng)) + 
                    sin(radians(:lat)) * sin(radians(r.ubicacion_actual_lat)))) AS distancia
                FROM 
                    ' . $this->table . ' r
                WHERE 
                    r.disponible = 1 AND
                    r.estado = "activo"
                HAVING 
                    distancia < :radio
                ORDER BY 
                    distancia,
                    r.calificacion_promedio DESC';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':lat', $lat);
        $stmt->bindParam(':lng', $lng);
        $stmt->bindParam(':radio', $radio_km);
        
        $stmt->execute();
        
        return $stmt;
    }
    
    // Obtener historial de pedidos de un repartidor
    public function obtenerHistorialPedidos($limite = 10, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    p.id as pedido_id,
                    p.fecha_pedido,
                    p.estado_actual,
                    p.total,
                    p.direccion_entrega,
                    u.nombre as nombre_usuario,
                    u.apellido as apellido_usuario,
                    n.nombre as nombre_negocio
                FROM 
                    pedidos p
                JOIN
                    usuarios u ON p.usuario_id = u.id
                JOIN
                    negocios n ON p.negocio_id = n.id
                WHERE 
                    p.repartidor_id = :repartidor_id
                ORDER BY
                    p.fecha_pedido DESC
                LIMIT :limite OFFSET :offset';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':repartidor_id', $this->id);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt;
    }
    
    // Actualizar calificación promedio
    public function actualizarCalificacionPromedio() {
        $query = 'UPDATE ' . $this->table . ' r
                SET
                    r.calificacion_promedio = (
                        SELECT AVG(v.calificacion)
                        FROM valoraciones v
                        WHERE 
                            v.repartidor_id = :repartidor_id AND
                            v.tipo = "repartidor"
                    )
                WHERE
                    r.id = :repartidor_id';
                    
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':repartidor_id', $this->id);
        
        if($stmt->execute()) {
            // Actualizar la propiedad en el objeto
            $query = 'SELECT calificacion_promedio FROM ' . $this->table . ' WHERE id = :id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) {
                $this->calificacion_promedio = $row['calificacion_promedio'];
            }
            
            return true;
        }
        
        return false;
    }
    
    // Obtener valoraciones del repartidor
    public function obtenerValoraciones($limite = 10, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    v.id,
                    v.pedido_id,
                    v.calificacion,
                    v.comentario,
                    v.fecha,
                    u.nombre as nombre_usuario,
                    u.apellido as apellido_usuario
                FROM 
                    valoraciones v
                JOIN
                    usuarios u ON v.usuario_id = u.id
                WHERE 
                    v.repartidor_id = :repartidor_id AND
                    v.tipo = "repartidor"
                ORDER BY
                    v.fecha DESC
                LIMIT :limite OFFSET :offset';
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':repartidor_id', $this->id);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt;
    }
}
?>