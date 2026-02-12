<?php
class ElegibleProducto {
    private $conn;
    private $table_name = "elegibles_producto";
    
    public $id_elegible;
    public $id_producto;
    public $nombre;
    public $precio_adicional;
    public $disponible;
    public $orden;
    public $fecha_creacion;
    public $fecha_actualizacion;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear nuevo elegible
    public function crear() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET id_producto=:id_producto, 
                     nombre=:nombre, 
                     precio_adicional=:precio_adicional, 
                     disponible=:disponible, 
                     orden=:orden";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id_producto = htmlspecialchars(strip_tags($this->id_producto));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->precio_adicional = htmlspecialchars(strip_tags($this->precio_adicional));
        $this->disponible = htmlspecialchars(strip_tags($this->disponible));
        $this->orden = htmlspecialchars(strip_tags($this->orden));
        
        // Vincular valores
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":precio_adicional", $this->precio_adicional);
        $stmt->bindParam(":disponible", $this->disponible);
        $stmt->bindParam(":orden", $this->orden);
        
        if($stmt->execute()) {
            $this->id_elegible = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Obtener elegibles por producto
    public function obtenerPorProducto($id_producto) {
        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE id_producto = ? 
                 ORDER BY orden ASC, nombre ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_producto);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener elegible por ID
    public function obtenerPorId($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_elegible = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->id_elegible = $row['id_elegible'];
            $this->id_producto = $row['id_producto'];
            $this->nombre = $row['nombre'];
            $this->precio_adicional = $row['precio_adicional'];
            $this->disponible = $row['disponible'];
            $this->orden = $row['orden'];
            $this->fecha_creacion = $row['fecha_creacion'];
            $this->fecha_actualizacion = $row['fecha_actualizacion'];
            
            return $row;
        }
        
        return false;
    }
    
    // Actualizar elegible
    public function actualizar() {
        $query = "UPDATE " . $this->table_name . " 
                 SET nombre=:nombre, 
                     precio_adicional=:precio_adicional, 
                     disponible=:disponible, 
                     orden=:orden 
                 WHERE id_elegible=:id_elegible AND id_producto=:id_producto";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id_elegible = htmlspecialchars(strip_tags($this->id_elegible));
        $this->id_producto = htmlspecialchars(strip_tags($this->id_producto));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->precio_adicional = htmlspecialchars(strip_tags($this->precio_adicional));
        $this->disponible = htmlspecialchars(strip_tags($this->disponible));
        $this->orden = htmlspecialchars(strip_tags($this->orden));
        
        // Vincular valores
        $stmt->bindParam(":id_elegible", $this->id_elegible);
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":precio_adicional", $this->precio_adicional);
        $stmt->bindParam(":disponible", $this->disponible);
        $stmt->bindParam(":orden", $this->orden);
        
        return $stmt->execute();
    }
    
    // Eliminar elegible
    public function eliminar() {
        $query = "DELETE FROM " . $this->table_name . " 
                 WHERE id_elegible = ? AND id_producto = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_elegible);
        $stmt->bindParam(2, $this->id_producto);
        
        return $stmt->execute();
    }
    
    // Eliminar todos los elegibles de un producto
    public function eliminarPorProducto($id_producto) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_producto = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_producto);
        
        return $stmt->execute();
    }
    
    // Contar elegibles por producto
    public function contarPorProducto($id_producto) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                 WHERE id_producto = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_producto);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    // Actualizar orden de elegibles
    public function actualizarOrden($elegibles_orden) {
        $this->conn->beginTransaction();
        
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET orden = ? 
                     WHERE id_elegible = ? AND id_producto = ?";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($elegibles_orden as $elegible) {
                $stmt->bindParam(1, $elegible['orden']);
                $stmt->bindParam(2, $elegible['id_elegible']);
                $stmt->bindParam(3, $this->id_producto);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
}
?>