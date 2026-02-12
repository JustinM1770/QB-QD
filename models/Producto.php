<?php
/**
 * Clase Producto
 * 
 * Gestiona los productos de los negocios en la aplicación de delivery
 */
class Producto {
    // Conexión a la base de datos
    private $conn;
    private $table = 'productos';
    
    // Propiedades del objeto
    public $id_producto;
    public $id_negocio;
    public $id_categoria;
    public $nombre;
    public $descripcion;
    public $precio;
    public $imagen;
    public $disponible;
    public $destacado;
    public $tiene_elegibles;
    public $orden_visualizacion;
    public $calorias;
    
    /**
     * Constructor con la conexión a la base de datos
     *
     * @param object $db - Objeto de conexión a la base de datos
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los productos
     *
     * @return array - Arreglo de productos
     */
    public function obtenerTodos() {
        $query = "SELECT p.*, cp.nombre as nombre_categoria 
                  FROM " . $this->table . " p
                  LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                  ORDER BY p.orden_visualizacion, p.nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearDesdeBD($row);
        }
        
        return $productos;
    }
    
    /**
     * Obtener productos por negocio
     *
     * @param int $id_negocio - ID del negocio
     * @return array - Arreglo de productos del negocio
     */
    public function obtenerPorNegocio($id_negocio) {
        $query = "SELECT p.*, cp.nombre as nombre_categoria 
                  FROM " . $this->table . " p
                  LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                  WHERE p.id_negocio = :id_negocio
                  ORDER BY cp.orden_visualizacion, p.orden_visualizacion, p.nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->execute();
        
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearDesdeBD($row);
        }
        
        return $productos;
    }
    
    /**
     * Obtener productos por categoría
     *
     * @param int $id_categoria - ID de la categoría
     * @return array - Arreglo de productos de la categoría
     */
    public function obtenerPorCategoria($id_categoria) {
        $query = "SELECT p.*, cp.nombre as nombre_categoria 
                  FROM " . $this->table . " p
                  LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                  WHERE p.id_categoria = :id_categoria
                  ORDER BY p.orden_visualizacion, p.nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_categoria", $id_categoria);
        $stmt->execute();
        
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearDesdeBD($row);
        }
        
        return $productos;
    }
    
    /**
     * Obtener productos destacados por negocio
     *
     * @param int $id_negocio - ID del negocio
     * @param int $limite - Cantidad máxima de productos a devolver
     * @return array - Arreglo de productos destacados
     */
    public function obtenerDestacadosPorNegocio($id_negocio, $limite = 10) {
        $query = "SELECT p.*, cp.nombre as nombre_categoria 
                  FROM " . $this->table . " p
                  LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                  WHERE p.id_negocio = :id_negocio 
                  AND p.destacado = 1
                  AND p.disponible = 1
                  ORDER BY p.orden_visualizacion, p.nombre
                  LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearDesdeBD($row);
        }
        
        return $productos;
    }
    
    /**
     * Obtener un producto por ID junto con sus opciones
     *
     * @param int $id_producto - ID del producto
     * @return array|false - Información del producto o false si no se encuentra
     */
    public function obtenerPorId($id_producto) {
        try {
            // Validar conexión a BD
            if (!$this->conn || !($this->conn instanceof PDO)) {
                error_log('Error: Conexión a BD no válida en obtenerPorId()');
                return false;
            }

            // Validar ID de producto
            if (empty($id_producto) || !is_numeric($id_producto)) {
                error_log('Error: ID de producto inválido en obtenerPorId(): ' . $id_producto);
                return false;
            }

            $query = "SELECT p.*, cp.nombre as nombre_categoria 
                    FROM " . $this->table . " p
                    LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                    WHERE p.id_producto = :id_producto";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_producto", $id_producto);
            
            if (!$stmt->execute()) {
                error_log('Error al ejecutar consulta obtenerPorId(): ' . implode(", ", $stmt->errorInfo()));
                return false;
            }
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $producto = $this->mapearDesdeBD($row);
                $opciones = $this->obtenerOpcionesPorProducto($id_producto);
                
                if ($opciones === false) {
                    error_log('Error al obtener opciones para producto ID: ' . $id_producto);
                    return false;
                }
                
                $producto['elegibles'] = $opciones;
                return $producto;
            }
            
            error_log('Producto no encontrado ID: ' . $id_producto);
            return false;
        } catch (PDOException $e) {
            error_log('PDOException en obtenerPorId(): ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log('Exception en obtenerPorId(): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener las opciones (extras) de un producto
     *
     * @param int $id_producto - ID del producto
     * @return array|false - Arreglo de grupos de opciones o false si hay un error
     */
    public function obtenerOpcionesPorProducto($id_producto) {
        try {
            // Consulta para obtener los grupos de opciones
            $query = "SELECT go.id_grupo_opcion, go.nombre, go.obligatorio AS es_obligatorio,
                    go.min_selecciones, go.max_selecciones, go.orden_visualizacion
                    FROM grupos_opciones go
                    WHERE go.id_producto = :id_producto
                    ORDER BY go.orden_visualizacion";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_producto", $id_producto);
            
            if (!$stmt->execute()) {
                error_log('Error al obtener grupos de opciones: ' . implode(", ", $stmt->errorInfo()));
                return false;
            }
            
            $grupos = [];
            
            while ($grupo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Obtener opciones para cada grupo
                $queryOpciones = "SELECT o.id_opcion, o.nombre, o.precio_adicional, 
                                o.por_defecto, o.orden_visualizacion
                                FROM opciones o
                                WHERE o.id_grupo_opcion = :id_grupo_opcion
                                ORDER BY o.orden_visualizacion";
                
                $stmtOpciones = $this->conn->prepare($queryOpciones);
                $stmtOpciones->bindParam(":id_grupo_opcion", $grupo['id_grupo_opcion']);
                
                if (!$stmtOpciones->execute()) {
                    error_log('Error al obtener opciones para grupo ID: ' . $grupo['id_grupo_opcion']);
                    return false;
                }
                
                $opciones = [];
                while ($opcion = $stmtOpciones->fetch(PDO::FETCH_ASSOC)) {
                    $opciones[] = [
                        'id_opcion' => $opcion['id_opcion'],
                        'nombre' => $opcion['nombre'],
                        'precio_adicional' => $opcion['precio_adicional'],
                        'por_defecto' => $opcion['por_defecto'],
                        'orden_visualizacion' => $opcion['orden_visualizacion']
                    ];
                }
                
                $grupos[] = [
                    'id_grupo_opcion' => $grupo['id_grupo_opcion'],
                    'nombre' => $grupo['nombre'],
                    'es_obligatorio' => $grupo['es_obligatorio'],
                    'min_selecciones' => $grupo['min_selecciones'],
                    'max_selecciones' => $grupo['max_selecciones'],
                    'orden_visualizacion' => $grupo['orden_visualizacion'],
                    'opciones' => $opciones
                ];
            }
            
            return $grupos;
        } catch (PDOException $e) {
            error_log('PDOException en obtenerOpcionesPorProducto(): ' . $e->getMessage());
            return []; // Devolver array vacío en caso de error para evitar fallos
        } catch (Exception $e) {
            error_log('Exception en obtenerOpcionesPorProducto(): ' . $e->getMessage());
            return []; // Devolver array vacío en caso de error para evitar fallos
        }
    }
    
    /**
     * Obtener las categorías de productos
     *
     * @param int $id_negocio - ID del negocio
     * @return array - Arreglo de categorías de productos
     */
    public function obtenerCategoriasProductos($id_negocio) {
        $query = "SELECT * FROM categorias_producto 
                  WHERE id_negocio = :id_negocio
                  ORDER BY orden_visualizacion, nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->execute();
        
        $categorias = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = [
                'id_categoria' => $row['id_categoria'],
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'orden_visualizacion' => $row['orden_visualizacion']
            ];
        }
        
        return $categorias;
    }
    
    /**
     * Crear un nuevo producto
     *
     * @return bool - true si se creó correctamente, false en caso contrario
     */
    public function crear() {
        $query = "INSERT INTO " . $this->table . " 
                  (id_negocio, id_categoria, nombre, descripcion, precio, imagen, 
                   disponible, destacado, tiene_elegibles, orden_visualizacion, calorias, fecha_creacion, fecha_actualizacion) 
                  VALUES 
                  (:id_negocio, :id_categoria, :nombre, :descripcion, :precio, :imagen, 
                   :disponible, :destacado, :tiene_elegibles, :orden_visualizacion, :calorias, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->precio = floatval($this->precio);
        $this->imagen = isset($this->imagen) ? htmlspecialchars(strip_tags($this->imagen)) : null;
        $this->disponible = $this->disponible ? 1 : 0;
        $this->destacado = $this->destacado ? 1 : 0;
        $this->tiene_elegibles = isset($this->tiene_elegibles) ? ($this->tiene_elegibles ? 1 : 0) : 0;
        $this->orden_visualizacion = isset($this->orden_visualizacion) ? (int)$this->orden_visualizacion : 0;
        $this->calorias = isset($this->calorias) ? (int)$this->calorias : null;
        
        // Categoría puede ser null
        $categoria_id = $this->id_categoria > 0 ? $this->id_categoria : null;
        
        // Vincular parámetros
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        $stmt->bindParam(":id_categoria", $categoria_id);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":precio", $this->precio);
        $stmt->bindParam(":imagen", $this->imagen);
        $stmt->bindParam(":disponible", $this->disponible);
        $stmt->bindParam(":destacado", $this->destacado);
        $stmt->bindParam(":tiene_elegibles", $this->tiene_elegibles);
        $stmt->bindParam(":orden_visualizacion", $this->orden_visualizacion);
        $stmt->bindParam(":calorias", $this->calorias, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $this->id_producto = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar un producto existente
     *
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function actualizar() {
        $query = "UPDATE " . $this->table . " 
                  SET id_categoria = :id_categoria, 
                      nombre = :nombre, 
                      descripcion = :descripcion, 
                      precio = :precio, 
                      disponible = :disponible, 
                      destacado = :destacado, 
                      tiene_elegibles = :tiene_elegibles,
                      orden_visualizacion = :orden_visualizacion, 
                      calorias = :calorias,
                      fecha_actualizacion = NOW() ";
        
        // Añadir la imagen solo si se proporciona una nueva
        if (isset($this->imagen) && !empty($this->imagen)) {
            $query .= ", imagen = :imagen ";
        }
        
        $query .= "WHERE id_producto = :id_producto AND id_negocio = :id_negocio";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->precio = floatval($this->precio);
        $this->disponible = $this->disponible ? 1 : 0;
        $this->destacado = $this->destacado ? 1 : 0;
        $this->tiene_elegibles = isset($this->tiene_elegibles) ? ($this->tiene_elegibles ? 1 : 0) : 0;
        $this->orden_visualizacion = isset($this->orden_visualizacion) ? (int)$this->orden_visualizacion : 0;
        $this->calorias = isset($this->calorias) ? (int)$this->calorias : null;
        
        // Categoría puede ser null
        $categoria_id = $this->id_categoria > 0 ? $this->id_categoria : null;
        
        // Vincular parámetros
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        $stmt->bindParam(":id_categoria", $categoria_id);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":precio", $this->precio);
        $stmt->bindParam(":disponible", $this->disponible);
        $stmt->bindParam(":destacado", $this->destacado);
        $stmt->bindParam(":tiene_elegibles", $this->tiene_elegibles);
        $stmt->bindParam(":orden_visualizacion", $this->orden_visualizacion);
        $stmt->bindParam(":calorias", $this->calorias, PDO::PARAM_INT);
        
        // Vincular imagen solo si se proporciona una nueva
        if (isset($this->imagen) && !empty($this->imagen)) {
            $this->imagen = htmlspecialchars(strip_tags($this->imagen));
            $stmt->bindParam(":imagen", $this->imagen);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Eliminar un producto
     *
     * @return bool - true si se eliminó correctamente, false en caso contrario
     */
    public function eliminar() {
        // Primero eliminar elegibles asociados
        $query = "DELETE FROM elegibles_producto WHERE id_producto = :id_producto";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->execute();
        
        // Eliminar opciones asociadas
        $query = "DELETE FROM opciones WHERE id_grupo_opcion IN (
                    SELECT id_grupo_opcion FROM grupos_opciones WHERE id_producto = :id_producto
                  )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->execute();
        
        // Eliminar grupos de opciones
        $query = "DELETE FROM grupos_opciones WHERE id_producto = :id_producto";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->execute();
        
        // Finalmente eliminar el producto
        $query = "DELETE FROM " . $this->table . " WHERE id_producto = :id_producto AND id_negocio = :id_negocio";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_producto", $this->id_producto);
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        
        return $stmt->execute();
    }
    
    /**
     * Actualizar disponibilidad de un producto
     *
     * @param bool $disponible - true para disponible, false para no disponible
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function actualizarDisponibilidad($disponible) {
        $query = "UPDATE " . $this->table . " 
                  SET disponible = :disponible, 
                      fecha_actualizacion = NOW() 
                  WHERE id_producto = :id_producto";
        
        $stmt = $this->conn->prepare($query);
        
        $estado = $disponible ? 1 : 0;
        $stmt->bindParam(":disponible", $estado);
        $stmt->bindParam(":id_producto", $this->id_producto);
        
        if ($stmt->execute()) {
            $this->disponible = $estado;
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar si un producto es destacado
     *
     * @param bool $destacado - true para destacado, false para no destacado
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function actualizarDestacado($destacado) {
        $query = "UPDATE " . $this->table . " 
                  SET destacado = :destacado, 
                      fecha_actualizacion = NOW() 
                  WHERE id_producto = :id_producto";
        
        $stmt = $this->conn->prepare($query);
        
        $estado = $destacado ? 1 : 0;
        $stmt->bindParam(":destacado", $estado);
        $stmt->bindParam(":id_producto", $this->id_producto);
        
        if ($stmt->execute()) {
            $this->destacado = $estado;
            return true;
        }
        
        return false;
    }
    
    /**
     * Buscar productos por nombre o descripción
     *
     * @param int $id_negocio - ID del negocio
     * @param string $termino - Término de búsqueda
     * @return array - Arreglo de productos que coinciden con la búsqueda
     */
    public function buscar($id_negocio, $termino) {
        $query = "SELECT p.*, cp.nombre as nombre_categoria 
                  FROM " . $this->table . " p
                  LEFT JOIN categorias_producto cp ON p.id_categoria = cp.id_categoria
                  WHERE p.id_negocio = :id_negocio 
                  AND (p.nombre LIKE :termino OR p.descripcion LIKE :termino)
                  ORDER BY p.orden_visualizacion, p.nombre";
        
        $stmt = $this->conn->prepare($query);
        
        $termino_busqueda = "%{$termino}%";
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->bindParam(":termino", $termino_busqueda);
        $stmt->execute();
        
        $productos = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productos[] = $this->mapearDesdeBD($row);
        }
        
        return $productos;
    }
    
    /**
     * Mapear datos desde la base de datos a un array asociativo
     *
     * @param array $row - Fila de datos de la base de datos
     * @return array - Arreglo asociativo formateado
     */
    private function mapearDesdeBD($row) {
        return [
            'id_producto' => $row['id_producto'],
            'id_negocio' => $row['id_negocio'],
            'id_categoria' => $row['id_categoria'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'precio' => $row['precio'],
            'imagen' => $row['imagen'],
            'disponible' => $row['disponible'],
            'destacado' => $row['destacado'],
            'tiene_elegibles' => isset($row['tiene_elegibles']) ? $row['tiene_elegibles'] : 0,
            'orden_visualizacion' => $row['orden_visualizacion'],
            'calorias' => $row['calorias'],
            'fecha_creacion' => $row['fecha_creacion'],
            'fecha_actualizacion' => $row['fecha_actualizacion'],
            'nombre_categoria' => isset($row['nombre_categoria']) ? $row['nombre_categoria'] : 'Sin categoría',
            // Campos de personalización para regalos
            'permite_mensaje_tarjeta' => isset($row['permite_mensaje_tarjeta']) ? (bool)$row['permite_mensaje_tarjeta'] : false,
            'permite_texto_producto' => isset($row['permite_texto_producto']) ? (bool)$row['permite_texto_producto'] : false,
            'limite_texto_producto' => isset($row['limite_texto_producto']) ? (int)$row['limite_texto_producto'] : 50
        ];
    }
}
?>