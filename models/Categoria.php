<?php
require_once dirname(__FILE__) . '/../config/database.php';

class Categoria {
    private $conn;
    private $table = 'categorias_negocio';
    
    // Propiedades de la categoría
    public $id_categoria;
    public $nombre;
    public $descripcion;
    public $icono;
    
    // Constructor con conexión a BD
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todas las categorías
     * @return array
     */
    public function obtenerTodas() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = [
                'id_categoria' => $row['id_categoria'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'icono' => $row['icono']
            ];
        }
        
        return $categorias;
    }
    
    /**
     * Obtener categorías populares
     * @param int $limite Número de categorías a obtener
     * @return array
     */
    public function obtenerPopulares($limite = 4) {
        $query = "SELECT c.*, COUNT(rnc.id_negocio) as total_negocios
                  FROM " . $this->table . " c
                  LEFT JOIN relacion_negocio_categoria rnc ON c.id_categoria = rnc.id_categoria
                  GROUP BY c.id_categoria
                  ORDER BY total_negocios DESC, c.nombre ASC
                  LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = [
                'id_categoria' => $row['id_categoria'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'icono' => $row['icono'],
                'total_negocios' => $row['total_negocios']
            ];
        }
        
        return $categorias;
    }
    
    /**
     * Obtener categoría por ID
     * @return boolean
     */
    public function obtenerPorId() {
        $query = "SELECT * FROM " . $this->table . " WHERE id_categoria = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_categoria);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id_categoria = $row['id_categoria'];
            $this->nombre = $row['nombre'];
            $this->descripcion = $row['descripcion'];
            $this->icono = $row['icono'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear una nueva categoría
     * @return boolean
     */
    public function crear() {
        $query = "INSERT INTO " . $this->table . " SET nombre = :nombre, descripcion = :descripcion, icono = :icono";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        
        // Vincular datos
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':descripcion', $this->descripcion);
        $stmt->bindParam(':icono', $this->icono);
        
        // Ejecutar
        if ($stmt->execute()) {
            $this->id_categoria = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar categoría existente
     * @return boolean
     */
    public function actualizar() {
        $query = "UPDATE " . $this->table . "
                  SET nombre = :nombre,
                      descripcion = :descripcion,
                      icono = :icono
                  WHERE id_categoria = :id_categoria";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $this->id_categoria = htmlspecialchars(strip_tags($this->id_categoria));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        
        // Vincular datos
        $stmt->bindParam(':id_categoria', $this->id_categoria);
        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':descripcion', $this->descripcion);
        $stmt->bindParam(':icono', $this->icono);
        
        // Ejecutar
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Eliminar categoría
     * @return boolean
     */
    public function eliminar() {
        $query = "DELETE FROM " . $this->table . " WHERE id_categoria = :id_categoria";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar
        $this->id_categoria = htmlspecialchars(strip_tags($this->id_categoria));
        
        // Vincular
        $stmt->bindParam(':id_categoria', $this->id_categoria);
        
        // Ejecutar
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}