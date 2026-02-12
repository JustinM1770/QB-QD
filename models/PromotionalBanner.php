<?php
// ===== PRIMERO: Crear tabla SQL =====
/*
CREATE TABLE promotional_banners (
    id_banner INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    imagen_url VARCHAR(255),
    enlace_destino VARCHAR(255),
    tipo_banner ENUM('descuento', 'promocion', 'nuevo_negocio', 'evento') DEFAULT 'promocion',
    descuento_porcentaje INT DEFAULT 0,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME,
    activo BOOLEAN DEFAULT 1,
    posicion INT DEFAULT 0,
    negocio_id INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (negocio_id) REFERENCES negocios(id_negocio)
);
*/

// ===== models/PromotionalBanner.php =====
class PromotionalBanner {
    private $conn;
    private $table_name = "promotional_banners";

    public $id_banner;
    public $titulo;
    public $descripcion;
    public $imagen_url;
    public $enlace_destino;
    public $tipo_banner;
    public $descuento_porcentaje;
    public $fecha_inicio;
    public $fecha_fin;
    public $activo;
    public $posicion;
    public $negocio_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Obtener banners activos para mostrar en frontend
    public function obtenerActivos() {
        $query = "SELECT b.*, n.nombre as negocio_nombre 
                  FROM " . $this->table_name . " b
                  LEFT JOIN negocios n ON b.negocio_id = n.id_negocio
                  WHERE b.activo = 1 
                  AND (b.fecha_fin IS NULL OR b.fecha_fin > NOW())
                  ORDER BY b.posicion ASC, b.creado_en DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener todos los banners (para admin)
    public function obtenerTodos() {
        $query = "SELECT b.*, n.nombre as negocio_nombre 
                  FROM " . $this->table_name . " b
                  LEFT JOIN negocios n ON b.negocio_id = n.id_negocio
                  ORDER BY b.posicion ASC, b.creado_en DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear nuevo banner
    public function crear() {
        $query = "INSERT INTO " . $this->table_name . "
                  (titulo, descripcion, imagen_url, enlace_destino, tipo_banner, 
                   descuento_porcentaje, fecha_inicio, fecha_fin, activo, posicion, negocio_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $this->titulo,
            $this->descripcion,
            $this->imagen_url,
            $this->enlace_destino,
            $this->tipo_banner,
            $this->descuento_porcentaje,
            $this->fecha_inicio,
            $this->fecha_fin,
            $this->activo,
            $this->posicion,
            $this->negocio_id
        ]);
    }

    // Actualizar banner
    public function actualizar() {
        $query = "UPDATE " . $this->table_name . " SET
                  titulo = ?, descripcion = ?, imagen_url = ?, enlace_destino = ?,
                  tipo_banner = ?, descuento_porcentaje = ?, fecha_inicio = ?,
                  fecha_fin = ?, activo = ?, posicion = ?, negocio_id = ?
                  WHERE id_banner = ?";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $this->titulo, $this->descripcion, $this->imagen_url, $this->enlace_destino,
            $this->tipo_banner, $this->descuento_porcentaje, $this->fecha_inicio,
            $this->fecha_fin, $this->activo, $this->posicion, $this->negocio_id,
            $this->id_banner
        ]);
    }

    // Eliminar banner
    public function eliminar() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_banner = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$this->id_banner]);
    }

    // Obtener banner por ID
    public function obtenerPorId() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_banner = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->id_banner]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->titulo = $row['titulo'];
            $this->descripcion = $row['descripcion'];
            $this->imagen_url = $row['imagen_url'];
            $this->enlace_destino = $row['enlace_destino'];
            $this->tipo_banner = $row['tipo_banner'];
            $this->descuento_porcentaje = $row['descuento_porcentaje'];
            $this->fecha_inicio = $row['fecha_inicio'];
            $this->fecha_fin = $row['fecha_fin'];
            $this->activo = $row['activo'];
            $this->posicion = $row['posicion'];
            $this->negocio_id = $row['negocio_id'];
            return true;
        }
        return false;
    }

    // Obtener estadísticas de rendimiento
    public function obtenerEstadisticas() {
        $query = "SELECT 
                    COUNT(*) as total_banners,
                    COUNT(CASE WHEN activo = 1 THEN 1 END) as banners_activos,
                    COUNT(CASE WHEN tipo_banner = 'descuento' THEN 1 END) as banners_descuento,
                    COUNT(CASE WHEN fecha_fin < NOW() THEN 1 END) as banners_expirados,
                    AVG(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as tasa_activacion
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>