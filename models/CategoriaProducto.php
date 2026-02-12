<?php
/**
 * Clase CategoriaProducto
 * 
 * Gestiona las categorías de productos en la aplicación de delivery
 */
class CategoriaProducto {
    // Conexión a la base de datos
    private $conn;
    private $table = 'categorias_producto';
    
    // Propiedades del objeto
    public $id_categoria;
    public $id_negocio;
    public $nombre;
    public $descripcion;
    public $orden_visualizacion;
    
    /**
     * Constructor con la conexión a la base de datos
     *
     * @param object $db - Objeto de conexión a la base de datos
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todas las categorías de productos
     *
     * @return array - Arreglo de categorías
     */
    public function obtenerTodas() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $this->mapearDesdeBD($row);
        }
        
        return $categorias;
    }
    
    /**
     * Obtener categorías por negocio
     *
     * @param int $id_negocio - ID del negocio
     * @return array - Arreglo de categorías del negocio
     */
    public function obtenerPorNegocio($id_negocio) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id_negocio = :id_negocio 
                  ORDER BY orden_visualizacion, nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->execute();
        
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $this->mapearDesdeBD($row);
        }
        
        return $categorias;
    }
    
    /**
     * Obtener una categoría por ID
     *
     * @param int $id_categoria - ID de la categoría
     * @return array|false - Información de la categoría o false si no se encuentra
     */
    public function obtenerPorId($id_categoria) {
        $query = "SELECT * FROM " . $this->table . " WHERE id_categoria = :id_categoria";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_categoria", $id_categoria);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return $this->mapearDesdeBD($row);
        }
        
        return false;
    }
    
    /**
     * Crear una nueva categoría
     *
     * @return bool - true si se creó correctamente, false en caso contrario
     */
    public function crear() {
        $query = "INSERT INTO " . $this->table . " 
                  (id_negocio, nombre, descripcion, orden_visualizacion) 
                  VALUES 
                  (:id_negocio, :nombre, :descripcion, :orden_visualizacion)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->orden_visualizacion = isset($this->orden_visualizacion) ? (int)$this->orden_visualizacion : 0;
        
        // Vincular parámetros
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":orden_visualizacion", $this->orden_visualizacion);
        
        if ($stmt->execute()) {
            $this->id_categoria = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar una categoría existente
     *
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function actualizar() {
        $query = "UPDATE " . $this->table . " 
                  SET nombre = :nombre, 
                      descripcion = :descripcion, 
                      orden_visualizacion = :orden_visualizacion 
                  WHERE id_categoria = :id_categoria AND id_negocio = :id_negocio";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->orden_visualizacion = isset($this->orden_visualizacion) ? (int)$this->orden_visualizacion : 0;
        
        // Vincular parámetros
        $stmt->bindParam(":id_categoria", $this->id_categoria);
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":orden_visualizacion", $this->orden_visualizacion);
        
        return $stmt->execute();
    }
    
    /**
     * Eliminar una categoría
     *
     * @return bool - true si se eliminó correctamente, false en caso contrario
     */
    public function eliminar() {
        // Primero actualizar productos que pertenecen a esta categoría para quitarla
        $query = "UPDATE productos 
                  SET id_categoria = NULL 
                  WHERE id_categoria = :id_categoria";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_categoria", $this->id_categoria);
        $stmt->execute();
        
        // Luego eliminar la categoría
        $query = "DELETE FROM " . $this->table . " 
                  WHERE id_categoria = :id_categoria AND id_negocio = :id_negocio";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_categoria", $this->id_categoria);
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        
        return $stmt->execute();
    }
    
    /**
     * Mapear datos desde la base de datos a un array asociativo
     *
     * @param array $row - Fila de datos de la base de datos
     * @return array - Arreglo asociativo formateado
     */
    private function mapearDesdeBD($row) {
        return [
            'id_categoria' => $row['id_categoria'],
            'id_negocio' => $row['id_negocio'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'orden_visualizacion' => $row['orden_visualizacion']
        ];
    }
}
?>