<?php
class DireccionUsuario {
    // Propiedades de base de datos
    private $conn;
    private $table = 'direcciones_usuario';
    
    // Propiedades de la dirección
    public $id_direccion;
    public $id_usuario;
    public $nombre_direccion;
    public $calle;
    public $numero;
    public $colonia;
    public $ciudad;
    public $estado;
    public $codigo_postal;
    public $referencias;
    public $latitud;
    public $longitud;
    public $es_predeterminada;
    public $fecha_registro;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para obtener las direcciones de un usuario
    public function obtenerPorUsuarioId($usuario_id) {
        $query = 'SELECT 
                    d.id_direccion, 
                    d.id_usuario, 
                    d.nombre_direccion, 
                    d.calle, 
                    d.numero, 
                    d.colonia, 
                    d.ciudad, 
                    d.estado, 
                    d.codigo_postal, 
                    d.referencias, 
                    d.latitud, 
                    d.longitud, 
                    d.es_predeterminada, 
                    d.fecha_registro
                FROM 
                    ' . $this->table . ' d
                WHERE 
                    d.id_usuario = :usuario_id
                ORDER BY
                    d.es_predeterminada DESC, 
                    d.fecha_registro DESC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':usuario_id', $usuario_id);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener una dirección por ID
    public function obtenerPorId() {
        $query = 'SELECT 
                    d.id_direccion, 
                    d.id_usuario, 
                    d.nombre_direccion, 
                    d.calle, 
                    d.numero, 
                    d.colonia, 
                    d.ciudad, 
                    d.estado, 
                    d.codigo_postal, 
                    d.referencias, 
                    d.latitud, 
                    d.longitud, 
                    d.es_predeterminada, 
                    d.fecha_registro
                FROM 
                    ' . $this->table . ' d
                WHERE 
                    d.id_direccion = :id
                LIMIT 0,1';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        
        // Ejecutar query
        $stmt->execute();
        
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Establecer propiedades
            $this->id_direccion = $row['id_direccion'];
            $this->id_usuario = $row['id_usuario'];
            $this->nombre_direccion = $row['nombre_direccion'];
            $this->calle = $row['calle'];
            $this->numero = $row['numero'];
            $this->colonia = $row['colonia'];
            $this->ciudad = $row['ciudad'];
            $this->estado = $row['estado'];
            $this->codigo_postal = $row['codigo_postal'];
            $this->referencias = $row['referencias'];
            $this->latitud = $row['latitud'];
            $this->longitud = $row['longitud'];
            $this->es_predeterminada = $row['es_predeterminada'];
            $this->fecha_registro = $row['fecha_registro'];
            
            return true;
        }
        
        return false;
    }
    
    // Método para crear una nueva dirección
    public function crear() {
        // Si la dirección es predeterminada, quitar el estado predeterminado de otras direcciones
        if ($this->predeterminada) {
            $this->quitarPredeterminado();
        }
        
        $query = 'INSERT INTO ' . $this->table . '
                SET
                    id_usuario = :id_usuario,
                    nombre_direccion = :nombre_direccion,
                    calle = :calle,
                    numero = :numero,
                    colonia = :colonia,
                    ciudad = :ciudad,
                    estado = :estado,
                    codigo_postal = :codigo_postal,
                    referencias = :referencias,
                    latitud = :latitud,
                    longitud = :longitud,
                    predeterminada = :predeterminada,
                    fecha_registro = NOW()';
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id_usuario = htmlspecialchars(strip_tags($this->id_usuario));
        $this->nombre_direccion = htmlspecialchars(strip_tags($this->nombre_direccion));
        $this->calle = htmlspecialchars(strip_tags($this->calle));
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->colonia = htmlspecialchars(strip_tags($this->colonia));
        $this->ciudad = htmlspecialchars(strip_tags($this->ciudad));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->codigo_postal = htmlspecialchars(strip_tags($this->codigo_postal));
        $this->referencias = htmlspecialchars(strip_tags($this->referencias));
        
        // Si no hay latitud y longitud, intentar geocodificar
        if (empty($this->latitud) || empty($this->longitud)) {
            $this->geocodificarDireccion();
        }
        
        // Vincular parámetros
        $stmt->bindParam(':id_usuario', $this->id_usuario);
        $stmt->bindParam(':nombre_direccion', $this->nombre_direccion);
        $stmt->bindParam(':calle', $this->calle);
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':interior', $this->interior);
        $stmt->bindParam(':colonia', $this->colonia);
        $stmt->bindParam(':ciudad', $this->ciudad);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':codigo_postal', $this->codigo_postal);
        $stmt->bindParam(':referencias', $this->referencias);
        $stmt->bindParam(':latitud', $this->latitud);
        $stmt->bindParam(':longitud', $this->longitud);
        $stmt->bindParam(':predeterminada', $this->predeterminada);
        
        // Ejecutar query
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para actualizar una dirección existente
    public function actualizar() {
        // Si la dirección es predeterminada, quitar el estado predeterminado de otras direcciones
        if ($this->predeterminada) {
            $this->quitarPredeterminado();
        }
        
        $query = 'UPDATE ' . $this->table . '
                SET
                    nombre_direccion = :nombre_direccion,
                    calle = :calle,
                    numero = :numero,
                    interior = :interior,
                    colonia = :colonia,
                    ciudad = :ciudad,
                    estado = :estado,
                    codigo_postal = :codigo_postal,
                    referencias = :referencias,
                    latitud = :latitud,
                    longitud = :longitud,
                    predeterminada = :predeterminada
                WHERE
                    id = :id AND usuario_id = :usuario_id';
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
        $this->nombre_direccion = htmlspecialchars(strip_tags($this->nombre_direccion));
        $this->calle = htmlspecialchars(strip_tags($this->calle));
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->interior = htmlspecialchars(strip_tags($this->interior));
        $this->colonia = htmlspecialchars(strip_tags($this->colonia));
        $this->ciudad = htmlspecialchars(strip_tags($this->ciudad));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->codigo_postal = htmlspecialchars(strip_tags($this->codigo_postal));
        $this->referencias = htmlspecialchars(strip_tags($this->referencias));
        
        // Si se modificó la dirección, actualizar coordenadas
        if (empty($this->latitud) || empty($this->longitud)) {
            $this->geocodificarDireccion();
        }
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        $stmt->bindParam(':nombre_direccion', $this->nombre_direccion);
        $stmt->bindParam(':calle', $this->calle);
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':interior', $this->interior);
        $stmt->bindParam(':colonia', $this->colonia);
        $stmt->bindParam(':ciudad', $this->ciudad);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':codigo_postal', $this->codigo_postal);
        $stmt->bindParam(':referencias', $this->referencias);
        $stmt->bindParam(':latitud', $this->latitud);
        $stmt->bindParam(':longitud', $this->longitud);
        $stmt->bindParam(':predeterminada', $this->predeterminada);
        
        // Ejecutar query
        if ($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para eliminar una dirección
    public function eliminar() {
        // Verificar si es la última dirección del usuario
        $query = 'SELECT COUNT(*) as total FROM ' . $this->table . ' WHERE usuario_id = :usuario_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['total'] <= 1) {
            // No permitir eliminar la última dirección
            return false;
        }
        
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = :id AND usuario_id = :usuario_id';
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        
        // Ejecutar query
        if ($stmt->execute()) {
            // Verificar si la dirección eliminada era predeterminada
            $wasDefault = $this->predeterminada;
            
            // Si era predeterminada, establecer otra dirección como predeterminada
            if ($wasDefault) {
                $this->establecerOtraPredeterminada();
            }
            
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para establecer una dirección como predeterminada
    public function establecerPredeterminada() {
        // Primero quitar el estado predeterminado de todas las direcciones del usuario
        $this->quitarPredeterminado();
        
        // Luego establecer esta dirección como predeterminada
        $query = 'UPDATE ' . $this->table . '
                SET
                    predeterminada = 1
                WHERE
                    id = :id AND usuario_id = :usuario_id';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        
        // Ejecutar query
        if ($stmt->execute()) {
            $this->predeterminada = 1;
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para obtener la dirección predeterminada de un usuario
    public function obtenerPredeterminada($usuario_id) {
        $query = 'SELECT 
                    d.id, 
                    d.usuario_id, 
                    d.nombre_direccion, 
                    d.calle, 
                    d.numero, 
                    d.interior, 
                    d.colonia, 
                    d.ciudad, 
                    d.estado, 
                    d.codigo_postal, 
                    d.referencias, 
                    d.latitud, 
                    d.longitud, 
                    d.predeterminada, 
                    d.fecha_registro
                FROM 
                    ' . $this->table . ' d
                WHERE 
                    d.usuario_id = :usuario_id AND
                    d.predeterminada = 1
                LIMIT 0,1';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':usuario_id', $usuario_id);
        
        // Ejecutar query
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Establecer propiedades
            $this->id = $row['id'];
            $this->usuario_id = $row['usuario_id'];
            $this->nombre_direccion = $row['nombre_direccion'];
            $this->calle = $row['calle'];
            $this->numero = $row['numero'];
            $this->interior = $row['interior'];
            $this->colonia = $row['colonia'];
            $this->ciudad = $row['ciudad'];
            $this->estado = $row['estado'];
            $this->codigo_postal = $row['codigo_postal'];
            $this->referencias = $row['referencias'];
            $this->latitud = $row['latitud'];
            $this->longitud = $row['longitud'];
            $this->predeterminada = $row['predeterminada'];
            $this->fecha_registro = $row['fecha_registro'];
            
            return true;
        }
        
        // Si no hay dirección predeterminada, obtener la más reciente
        return $this->obtenerPrimeraDireccion($usuario_id);
    }
    
    // Método para obtener la primera dirección de un usuario (si no hay predeterminada)
    private function obtenerPrimeraDireccion($usuario_id) {
        $query = 'SELECT 
                    d.id, 
                    d.usuario_id, 
                    d.nombre_direccion, 
                    d.calle, 
                    d.numero, 
                    d.interior, 
                    d.colonia, 
                    d.ciudad, 
                    d.estado, 
                    d.codigo_postal, 
                    d.referencias, 
                    d.latitud, 
                    d.longitud, 
                    d.predeterminada, 
                    d.fecha_registro
                FROM 
                    ' . $this->table . ' d
                WHERE 
                    d.usuario_id = :usuario_id
                ORDER BY
                    d.fecha_registro DESC
                LIMIT 0,1';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':usuario_id', $usuario_id);
        
        // Ejecutar query
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Establecer propiedades
            $this->id = $row['id'];
            $this->usuario_id = $row['usuario_id'];
            $this->nombre_direccion = $row['nombre_direccion'];
            $this->calle = $row['calle'];
            $this->numero = $row['numero'];
            $this->interior = $row['interior'];
            $this->colonia = $row['colonia'];
            $this->ciudad = $row['ciudad'];
            $this->estado = $row['estado'];
            $this->codigo_postal = $row['codigo_postal'];
            $this->referencias = $row['referencias'];
            $this->latitud = $row['latitud'];
            $this->longitud = $row['longitud'];
            $this->predeterminada = $row['predeterminada'];
            $this->fecha_registro = $row['fecha_registro'];
            
            // Establecer como predeterminada
            $this->establecerPredeterminada();
            
            return true;
        }
        
        return false;
    }
    
    // Método para quitar el estado predeterminado de todas las direcciones del usuario
    private function quitarPredeterminado() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    predeterminada = 0
                WHERE
                    usuario_id = :usuario_id';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        
        // Ejecutar query
        $stmt->execute();
        
        return true;
    }
    
    // Método para establecer otra dirección como predeterminada después de eliminar la predeterminada
    private function establecerOtraPredeterminada() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    predeterminada = 1
                WHERE
                    usuario_id = :usuario_id
                ORDER BY
                    fecha_registro DESC
                LIMIT 1';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':usuario_id', $this->usuario_id);
        
        // Ejecutar query
        $stmt->execute();
        
        return true;
    }
    
    // Método para geocodificar la dirección y obtener latitud y longitud
    private function geocodificarDireccion() {
        // Construir la dirección completa para la geocodificación
        $direccion = $this->calle . ' ' . $this->numero . ', ' . 
                    $this->colonia . ', ' . $this->ciudad . ', ' . 
                    $this->estado . ', ' . $this->codigo_postal;
        
        // Esta función es un placeholder. En una implementación real, 
        // aquí se haría una llamada a una API de geocodificación como Google Maps, Mapbox, etc.
        // Por simplicidad, asignaremos valores predeterminados o aleatorios.
        
        // En producción, esto debería ser reemplazado por una llamada real a la API
        $this->latitud = 19.4326; // Ejemplo: Ciudad de México
        $this->longitud = -99.1332;
        
        // Aplicar variación pequeña para simular ubicaciones distintas
        $this->latitud += (mt_rand(-1000, 1000) / 100000);
        $this->longitud += (mt_rand(-1000, 1000) / 100000);
        
        return true;
    }
    
    // Obtener dirección formateada para mostrar
    public function obtenerDireccionFormateada() {
        $direccion = $this->calle . ' ' . $this->numero;
        
        if (!empty($this->interior)) {
            $direccion .= ', Int. ' . $this->interior;
        }
        
        $direccion .= ', ' . $this->colonia . ', ' . $this->ciudad . ', ' . $this->estado . ', CP ' . $this->codigo_postal;
        
        return $direccion;
    }
}
?>