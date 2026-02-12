<?php
require_once 'config/database.php';

class Direccion {
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
    public $latitud;
    public $longitud;
    public $es_predeterminada;
    
    // Constructor con conexión a BD
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * ✅ NUEVA FUNCIÓN: Geocodificar dirección automáticamente
     */
    private function geocodificarDireccion() {
        // Solo geocodificar si no hay coordenadas o si están vacías
        if (!empty($this->latitud) && !empty($this->longitud)) {
            error_log("Coordenadas ya existentes: lat={$this->latitud}, lng={$this->longitud}");
            return true; // Ya tiene coordenadas
        }
        
        // Construir dirección completa
        $direccion_completa = trim(sprintf(
            "%s %s, %s, %s, %s, %s",
            $this->calle ?? '',
            $this->numero ?? '',
            $this->colonia ?? '',
            $this->ciudad ?? '',
            $this->estado ?? '',
            $this->codigo_postal ?? ''
        ));
        
        error_log("Geocodificando dirección: $direccion_completa");
        
        // Para ciudades específicas, usar coordenadas predefinidas
        $ciudad_lower = strtolower(trim($this->ciudad ?? ''));
        
        switch ($ciudad_lower) {
            case 'teocaltiche':
                $this->latitud = $this->obtenerCoordenadasPorZona('teocaltiche')['lat'];
                $this->longitud = $this->obtenerCoordenadasPorZona('teocaltiche')['lng'];
                error_log("Coordenadas Teocaltiche asignadas: {$this->latitud}, {$this->longitud}");
                return true;
                
            case 'ojuelos':
            case 'ojuelos de jalisco':
                $this->latitud = $this->obtenerCoordenadasPorZona('ojuelos')['lat'];
                $this->longitud = $this->obtenerCoordenadasPorZona('ojuelos')['lng'];
                error_log("Coordenadas Ojuelos asignadas: {$this->latitud}, {$this->longitud}");
                return true;
                
            default:
                // Para otras ciudades, intentar con Mapbox API
                $coordenadas = $this->geocodificarConMapbox($direccion_completa);
                if ($coordenadas) {
                    $this->latitud = $coordenadas['lat'];
                    $this->longitud = $coordenadas['lng'];
                    error_log("Coordenadas Mapbox asignadas: {$this->latitud}, {$this->longitud}");
                    return true;
                }
                
                // Fallback: coordenadas de Teocaltiche por defecto
                $this->latitud = 21.4167;
                $this->longitud = -102.5667;
                error_log("Coordenadas fallback asignadas: {$this->latitud}, {$this->longitud}");
                return true;
        }
    }
    
    /**
     * ✅ NUEVA FUNCIÓN: Obtener coordenadas por zona específica
     */
    private function obtenerCoordenadasPorZona($ciudad) {
        $coordenadas_ciudades = [
            'teocaltiche' => [
                // Coordenadas más precisas por colonia/zona
                'centro' => ['lat' => 21.4167, 'lng' => -102.5667],
                'norte' => ['lat' => 21.4200, 'lng' => -102.5650],
                'sur' => ['lat' => 21.4130, 'lng' => -102.5680],
                'este' => ['lat' => 21.4170, 'lng' => -102.5630],
                'oeste' => ['lat' => 21.4160, 'lng' => -102.5700],
                'default' => ['lat' => 21.4167, 'lng' => -102.5667]
            ],
            'ojuelos' => [
                'centro' => ['lat' => 21.8667, 'lng' => -101.6000],
                'norte' => ['lat' => 21.8700, 'lng' => -101.5980],
                'sur' => ['lat' => 21.8630, 'lng' => -101.6020],
                'este' => ['lat' => 21.8670, 'lng' => -101.5970],
                'oeste' => ['lat' => 21.8660, 'lng' => -101.6030],
                'default' => ['lat' => 21.8667, 'lng' => -101.6000]
            ]
        ];
        
        // Intentar detectar zona por colonia
        $colonia_lower = strtolower(trim($this->colonia ?? ''));
        
        if (isset($coordenadas_ciudades[$ciudad])) {
            $zonas = $coordenadas_ciudades[$ciudad];
            
            // Mapeo de colonias a zonas
            if (strpos($colonia_lower, 'centro') !== false) {
                return $zonas['centro'];
            } elseif (strpos($colonia_lower, 'norte') !== false) {
                return $zonas['norte'];
            } elseif (strpos($colonia_lower, 'sur') !== false) {
                return $zonas['sur'];
            } elseif (strpos($colonia_lower, 'este') !== false || strpos($colonia_lower, 'oriente') !== false) {
                return $zonas['este'];
            } elseif (strpos($colonia_lower, 'oeste') !== false || strpos($colonia_lower, 'poniente') !== false) {
                return $zonas['oeste'];
            } else {
                return $zonas['default'];
            }
        }
        
        // Fallback
        return ['lat' => 21.4167, 'lng' => -102.5667];
    }
    
    /**
     * ✅ NUEVA FUNCIÓN: Geocodificar con Mapbox API
     */
    private function geocodificarConMapbox($direccion_completa) {
        try {
            $mapbox_token = getenv('MAPBOX_TOKEN') ?: '';
            
            $url = sprintf(
                'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json?access_token=%s&country=MX&types=address&limit=1',
                urlencode($direccion_completa),
                $mapbox_token
            );
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5, // Timeout más corto
                    'user_agent' => 'QuickBite-Direccion/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("Error: No se pudo conectar a Mapbox API para: $direccion_completa");
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['features']) && !empty($data['features'])) {
                $feature = $data['features'][0];
                $coordinates = $feature['geometry']['coordinates'];
                
                // Mapbox devuelve [lng, lat], necesitamos [lat, lng]
                return [
                    'lat' => (float)$coordinates[1],
                    'lng' => (float)$coordinates[0]
                ];
            }
            
            error_log("Mapbox API: No se encontraron resultados para: $direccion_completa");
            return null;
            
        } catch (Exception $e) {
            error_log("Error geocodificación Mapbox en modelo: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener todas las direcciones de un usuario
     * @return array
     */
    public function obtenerPorUsuario() {
        $query = "SELECT * FROM " . $this->table . " WHERE id_usuario = ? ORDER BY es_predeterminada DESC, nombre_direccion ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_usuario);
        $stmt->execute();
        
        $direcciones = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $direcciones[] = [
                'id_direccion' => $row['id_direccion'],
                'nombre_direccion' => $row['nombre_direccion'],
                'calle' => $row['calle'],
                'numero' => $row['numero'],
                'colonia' => $row['colonia'],
                'ciudad' => $row['ciudad'],
                'estado' => $row['estado'],
                'codigo_postal' => $row['codigo_postal'],
                'latitud' => $row['latitud'],
                'longitud' => $row['longitud'],
                'es_predeterminada' => $row['es_predeterminada']
            ];
        }
        
        return $direcciones;
    }
    
    /**
     * Obtener dirección por ID
     * @return boolean
     */
    public function obtenerPorId() {
        $query = "SELECT * FROM " . $this->table . " WHERE id_direccion = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_direccion);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id_direccion = $row['id_direccion'];
            $this->id_usuario = $row['id_usuario'];
            $this->nombre_direccion = $row['nombre_direccion'];
            $this->calle = $row['calle'];
            $this->numero = $row['numero'];
            $this->colonia = $row['colonia'];
            $this->ciudad = $row['ciudad'];
            $this->estado = $row['estado'];
            $this->codigo_postal = $row['codigo_postal'];
            $this->latitud = $row['latitud'];
            $this->longitud = $row['longitud'];
            $this->es_predeterminada = $row['es_predeterminada'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * ✅ MODIFICADO: Crear una nueva dirección CON geocodificación automática
     * @return boolean
     */
    public function crear() {
        // ✅ GEOCODIFICAR AUTOMÁTICAMENTE ANTES DE GUARDAR
        $this->geocodificarDireccion();
        
        // Si esta dirección será predeterminada, quitar la predeterminada actual
        if ($this->es_predeterminada) {
            $this->quitarPredeterminada();
        }
        
        $query = "INSERT INTO " . $this->table . "
                  SET id_usuario = :id_usuario,
                      nombre_direccion = :nombre_direccion,
                      calle = :calle,
                      numero = :numero,
                      colonia = :colonia,
                      ciudad = :ciudad,
                      estado = :estado,
                      codigo_postal = :codigo_postal,
                      latitud = :latitud,
                      longitud = :longitud,
                      es_predeterminada = :es_predeterminada";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->nombre_direccion = htmlspecialchars(strip_tags($this->nombre_direccion));
        $this->calle = htmlspecialchars(strip_tags($this->calle));
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->colonia = htmlspecialchars(strip_tags($this->colonia));
        $this->ciudad = htmlspecialchars(strip_tags($this->ciudad));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->codigo_postal = htmlspecialchars(strip_tags($this->codigo_postal));
        
        // ✅ LOG PARA DEBUGGING
        error_log("Creando dirección con coordenadas: lat={$this->latitud}, lng={$this->longitud}");
        
        // Vincular parámetros
        $stmt->bindParam(':id_usuario', $this->id_usuario);
        $stmt->bindParam(':nombre_direccion', $this->nombre_direccion);
        $stmt->bindParam(':calle', $this->calle);
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':colonia', $this->colonia);
        $stmt->bindParam(':ciudad', $this->ciudad);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':codigo_postal', $this->codigo_postal);
        $stmt->bindParam(':latitud', $this->latitud);
        $stmt->bindParam(':longitud', $this->longitud);
        $stmt->bindParam(':es_predeterminada', $this->es_predeterminada);
        
        // Ejecutar
        if ($stmt->execute()) {
            $this->id_direccion = $this->conn->lastInsertId();
            error_log("✅ Dirección creada exitosamente con ID: {$this->id_direccion}");
            return true;
        } else {
            error_log("❌ Error creando dirección: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
    }
    
    /**
     * ✅ MODIFICADO: Actualizar dirección existente CON geocodificación automática
     * @return boolean
     */
    public function actualizar() {
        // ✅ GEOCODIFICAR AUTOMÁTICAMENTE ANTES DE ACTUALIZAR
        $this->geocodificarDireccion();
        
        // Si esta dirección será predeterminada, quitar la predeterminada actual
        if ($this->es_predeterminada) {
            $this->quitarPredeterminada();
        }
        
        $query = "UPDATE " . $this->table . "
                  SET nombre_direccion = :nombre_direccion,
                      calle = :calle,
                      numero = :numero,
                      colonia = :colonia,
                      ciudad = :ciudad,
                      estado = :estado,
                      codigo_postal = :codigo_postal,
                      latitud = :latitud,
                      longitud = :longitud,
                      es_predeterminada = :es_predeterminada
                  WHERE id_direccion = :id_direccion AND id_usuario = :id_usuario";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->id_direccion = htmlspecialchars(strip_tags($this->id_direccion));
        $this->nombre_direccion = htmlspecialchars(strip_tags($this->nombre_direccion));
        $this->calle = htmlspecialchars(strip_tags($this->calle));
        $this->numero = htmlspecialchars(strip_tags($this->numero));
        $this->colonia = htmlspecialchars(strip_tags($this->colonia));
        $this->ciudad = htmlspecialchars(strip_tags($this->ciudad));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->codigo_postal = htmlspecialchars(strip_tags($this->codigo_postal));
        
        // ✅ LOG PARA DEBUGGING
        error_log("Actualizando dirección {$this->id_direccion} con coordenadas: lat={$this->latitud}, lng={$this->longitud}");
        
        // Vincular parámetros
        $stmt->bindParam(':id_direccion', $this->id_direccion);
        $stmt->bindParam(':id_usuario', $this->id_usuario);
        $stmt->bindParam(':nombre_direccion', $this->nombre_direccion);
        $stmt->bindParam(':calle', $this->calle);
        $stmt->bindParam(':numero', $this->numero);
        $stmt->bindParam(':colonia', $this->colonia);
        $stmt->bindParam(':ciudad', $this->ciudad);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':codigo_postal', $this->codigo_postal);
        $stmt->bindParam(':latitud', $this->latitud);
        $stmt->bindParam(':longitud', $this->longitud);
        $stmt->bindParam(':es_predeterminada', $this->es_predeterminada);
        
        // Ejecutar
        if ($stmt->execute()) {
            error_log("✅ Dirección actualizada exitosamente");
            return true;
        } else {
            error_log("❌ Error actualizando dirección: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
    }
    
    /**
     * ✅ NUEVA FUNCIÓN: Actualizar coordenadas de direcciones existentes sin coordenadas
     */
    public function actualizarCoordenadasExistentes() {
        // Buscar direcciones sin coordenadas
        $query = "SELECT * FROM " . $this->table . " WHERE (latitud IS NULL OR longitud IS NULL OR latitud = 0 OR longitud = 0)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $direcciones_actualizadas = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Crear una instancia temporal para geocodificar
            $temp_direccion = new Direccion($this->conn);
            $temp_direccion->id_direccion = $row['id_direccion'];
            $temp_direccion->id_usuario = $row['id_usuario'];
            $temp_direccion->calle = $row['calle'];
            $temp_direccion->numero = $row['numero'];
            $temp_direccion->colonia = $row['colonia'];
            $temp_direccion->ciudad = $row['ciudad'];
            $temp_direccion->estado = $row['estado'];
            $temp_direccion->codigo_postal = $row['codigo_postal'];
            $temp_direccion->latitud = null; // Forzar regeocofificación
            $temp_direccion->longitud = null;
            
            // Geocodificar
            $temp_direccion->geocodificarDireccion();
            
            // Actualizar solo las coordenadas
            $update_query = "UPDATE " . $this->table . " SET latitud = ?, longitud = ? WHERE id_direccion = ?";
            $update_stmt = $this->conn->prepare($update_query);
            
            if ($update_stmt->execute([$temp_direccion->latitud, $temp_direccion->longitud, $row['id_direccion']])) {
                $direcciones_actualizadas++;
                error_log("Coordenadas actualizadas para dirección ID: {$row['id_direccion']}");
            }
        }
        
        return $direcciones_actualizadas;
    }
    
    // ... resto de métodos sin cambios ...
    
    /**
     * Eliminar dirección
     * @return boolean
     */
    public function eliminar() {
        // Verificar si es la dirección predeterminada
        $query_check = "SELECT es_predeterminada FROM " . $this->table . " WHERE id_direccion = ? AND id_usuario = ?";
        $stmt_check = $this->conn->prepare($query_check);
        $stmt_check->bindParam(1, $this->id_direccion);
        $stmt_check->bindParam(2, $this->id_usuario);
        $stmt_check->execute();
        $row = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        // Eliminar dirección
        $query = "DELETE FROM " . $this->table . " WHERE id_direccion = ? AND id_usuario = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_direccion);
        $stmt->bindParam(2, $this->id_usuario);
        
        if ($stmt->execute()) {
            // Si era la predeterminada, establecer otra como predeterminada
            if ($row && $row['es_predeterminada']) {
                $this->establecerNuevaPredeterminada();
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Quitar la marca de predeterminada a todas las direcciones del usuario
     */
    private function quitarPredeterminada() {
        $query = "UPDATE " . $this->table . " SET es_predeterminada = 0 WHERE id_usuario = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_usuario);
        $stmt->execute();
    }
    
    /**
     * Establecer una nueva dirección como predeterminada después de eliminar la actual
     */
    private function establecerNuevaPredeterminada() {
        $query = "UPDATE " . $this->table . " SET es_predeterminada = 1 WHERE id_usuario = ? ORDER BY id_direccion ASC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_usuario);
        $stmt->execute();
    }
    
    /**
     * Obtener direcciones en texto formateado
     * @return string
     */
    public function obtenerDireccionFormateada() {
        return $this->calle . ' ' . $this->numero . ', ' . $this->colonia . ', ' . $this->ciudad . ', ' . $this->estado . ' ' . $this->codigo_postal;
    }
}
?>