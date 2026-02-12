<?php
class Pedido {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "pedidos";
    
    // Propiedades del objeto
    public $id_pedido;
    public $id_usuario;
    public $id_negocio;
    public $id_direccion;
    public $id_repartidor;
    public $id_metodo_pago;
    public $id_estado; // 1:pendiente, 2:en_preparacion, 3:en_camino, 4:entregado, 5:cancelado
    public $total_productos;
    public $costo_envio;
    public $cargo_servicio = 0.00;
    public $impuestos = 0.00;
    public $propina = 0.00;
    public $monto_total;
    public $monto_efectivo = 0.00;
    public $instrucciones_especiales;
    public $tipo_pedido = 'delivery'; // Nuevo campo para tipo de pedido
    public $pickup_time = null;       // Nuevo campo para hora de pickup
    public $es_programado = 0;        // Si es pedido programado
    public $fecha_programada = null;  // Fecha y hora deseada de entrega
    public $tiempo_entrega_estimado;
    public $tiempo_entrega_real;
    public $fecha_creacion;
    public $fecha_actualizacion;
    public $calificacion;
    public $comentario;
    // Added properties to avoid deprecated dynamic property creation
    public $metodo_pago;
    public $payment_details;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear un nuevo pedido
    public function crear() {
        $query = "INSERT INTO " . $this->table_name . "
                  (id_usuario, id_negocio, id_direccion, id_metodo_pago, id_estado,
                   total_productos, costo_envio, cargo_servicio, impuestos, propina, monto_total,
                   instrucciones_especiales, tipo_pedido, pickup_time, es_programado, fecha_programada, fecha_creacion)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);

        // Limpiar y sanitizar datos
        $this->id_usuario = htmlspecialchars(strip_tags($this->id_usuario ?? ''));
        $this->id_negocio = htmlspecialchars(strip_tags($this->id_negocio ?? ''));
        $this->id_direccion = htmlspecialchars(strip_tags($this->id_direccion ?? ''));
        $this->id_metodo_pago = is_null($this->id_metodo_pago) ? null : htmlspecialchars(strip_tags($this->id_metodo_pago));
        $this->id_estado = (int)htmlspecialchars(strip_tags($this->id_estado ?? ''));
        $this->total_productos = (float)$this->total_productos;
        $this->costo_envio = (float)$this->costo_envio;
        $this->cargo_servicio = (float)$this->cargo_servicio;
        $this->impuestos = (float)$this->impuestos;
        $this->propina = (float)$this->propina;
        $this->monto_total = (float)$this->monto_total;
        $this->instrucciones_especiales = htmlspecialchars(strip_tags($this->instrucciones_especiales ?? ''));
        $this->tipo_pedido = htmlspecialchars(strip_tags($this->tipo_pedido ?? 'delivery'));
        $this->pickup_time = $this->pickup_time ? htmlspecialchars(strip_tags($this->pickup_time)) : null;
        $this->es_programado = (int)$this->es_programado;
        $this->fecha_programada = $this->fecha_programada ? htmlspecialchars(strip_tags($this->fecha_programada)) : null;

        // Vincular parámetros
        $stmt->bindParam(1, $this->id_usuario);
        $stmt->bindParam(2, $this->id_negocio);
        $stmt->bindParam(3, $this->id_direccion);
        $stmt->bindParam(4, $this->id_metodo_pago);
        $stmt->bindParam(5, $this->id_estado);
        $stmt->bindParam(6, $this->total_productos);
        $stmt->bindParam(7, $this->costo_envio);
        $stmt->bindParam(8, $this->cargo_servicio);
        $stmt->bindParam(9, $this->impuestos);
        $stmt->bindParam(10, $this->propina);
        $stmt->bindParam(11, $this->monto_total);
        $stmt->bindParam(12, $this->instrucciones_especiales);
        $stmt->bindParam(13, $this->tipo_pedido);
        $stmt->bindParam(14, $this->pickup_time);
        $stmt->bindParam(15, $this->es_programado);
        $stmt->bindParam(16, $this->fecha_programada);
        
            // Ejecutar consulta
            try {
                if ($stmt->execute()) {
                    $this->id_pedido = $this->conn->lastInsertId();
                    if (empty($this->id_pedido)) {
                        return "Error: No se pudo obtener el ID del pedido creado";
                    }
                    return true;
                } else {
                    $errorInfo = $stmt->errorInfo();
                    return "Error al crear pedido: " . $errorInfo[2];
                }
            } catch (PDOException $e) {
                return "Error PDO: " . $e->getMessage();
            }
    }
    
    // Agregar detalles del pedido (productos)
    public function agregarDetalle($id_pedido, $id_producto, $cantidad, $precio_unitario, $subtotal) {
        $query = "INSERT INTO detalles_pedido
                  (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(1, $id_pedido);
        $stmt->bindParam(2, $id_producto);
        $stmt->bindParam(3, $cantidad);
        $stmt->bindParam(4, $precio_unitario);
        $stmt->bindParam(5, $subtotal);
        
        // Ejecutar consulta
        return $stmt->execute();

    }
public function obtenerPorNegocio($id_negocio) {
    try {
        $query = "SELECT p.*, 
                  u.nombre as nombre_cliente, u.apellido as apellido_cliente, 
                  u.telefono as telefono_cliente
                  FROM pedidos p
                  LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE p.id_negocio = :id_negocio
                  ORDER BY 
                  CASE 
                    WHEN p.id_estado = 1 THEN 1
                    WHEN p.id_estado = 2 THEN 2
                    WHEN p.id_estado = 3 THEN 3
                    WHEN p.id_estado = 4 THEN 4
                    WHEN p.id_estado = 5 THEN 5
                    WHEN p.id_estado = 6 THEN 6
                    WHEN p.id_estado = 7 THEN 7
                    ELSE 8
                  END,
                  p.fecha_creacion DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Error al obtener pedidos: " . $e->getMessage());
    }
}


public function obtenerPorNegocioYEstado($id_negocio, $estado) {
        $query = "SELECT p.*, u.nombre as nombre_cliente, u.telefono as telefono_cliente
                 FROM pedidos p
                 LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                 WHERE p.id_negocio = ? AND p.id_estado = ?
                 ORDER BY p.fecha_creacion DESC";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->bindParam(2, $estado);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener detalles del pedido por ID
     * @return array|boolean Datos del pedido o false si no existe
     */
    public function obtenerPorId() {
        $query = "SELECT * FROM pedidos WHERE id_pedido = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_pedido);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Convertir claves a minúsculas para evitar problemas de acceso
            $row = array_change_key_case($row, CASE_LOWER);
            return $row;
        }
        return false;
    }
    
    /**
     * Obtener los productos del pedido
     * @return array Lista de productos del pedido
     */
    public function obtenerItems() {
        $query = "SELECT dp.*, p.nombre, p.precio, p.imagen FROM detalles_pedido dp 
                  JOIN productos p ON dp.id_producto = p.id_producto 
                  WHERE dp.id_pedido = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_pedido);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Actualizar el estado de un pedido
     * @param int $nuevo_estado ID del nuevo estado
     * @return bool Éxito o fracaso
     */
    public function actualizarEstado($nuevo_estado) {
        $query = "UPDATE " . $this->table_name . "
                  SET id_estado = ?, fecha_actualizacion = NOW()
                  WHERE id_pedido = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y sanitizar datos
        $nuevo_estado = (int)htmlspecialchars(strip_tags($nuevo_estado));
        
        // Vincular parámetros
        $stmt->bindParam(1, $nuevo_estado);
        $stmt->bindParam(2, $this->id_pedido);
        
        // Ejecutar consulta
        if ($stmt->execute()) {
            // Registrar el cambio de estado en el historial
            $this->registrarHistorialEstado($nuevo_estado);
            return true;
        }
        
        return false;
    }
    
    /**
     * Registrar un cambio de estado en el historial
     * @param int $id_estado Nuevo estado del pedido
     * @return bool Éxito o fracaso
     */
    private function registrarHistorialEstado($id_estado) {
        // Verificar si existe la tabla historial_estados
        $tablaExiste = false;
        try {
            $checkQuery = "SELECT 1 FROM historial_estados LIMIT 1";
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->execute();
            $tablaExiste = true;
        } catch (PDOException $e) {
            // La tabla no existe, la crearemos
            $tablaExiste = false;
        }
        
        // Si la tabla no existe, crearla
        if (!$tablaExiste) {
            try {
                $createTable = "CREATE TABLE historial_estados ( 
                    id_historial INT AUTO_INCREMENT PRIMARY KEY, 
                    id_pedido INT NOT NULL, 
                    id_estado INT NOT NULL, 
                    notas TEXT, 
                    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
                    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE 
                )";
                $this->conn->exec($createTable);
            } catch (PDOException $e) {
                // Si hay error al crear la tabla, continuamos sin registrar
                return false;
            }
        }
        
        // Registrar el cambio de estado
        $query = "INSERT INTO historial_estados (id_pedido, id_estado, notas) VALUES (?, ?, ?)";
        $notas = "Estado actualizado a " . $this->obtenerNombreEstado($id_estado);
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_pedido);
        $stmt->bindParam(2, $id_estado);
        $stmt->bindParam(3, $notas);
        return $stmt->execute();
    }
    
    /**
     * Obtener el nombre de un estado por su ID
     * 
     * ESTADOS DEL SISTEMA:
     * 1 = Pendiente (nuevo pedido)
     * 2 = Confirmado (negocio aceptó)
     * 3 = En preparación
     * 4 = Listo para recoger
     * 5 = En camino (repartidor en ruta)
     * 6 = Entregado
     * 7 = Cancelado
     * 
     * @param int $id_estado ID del estado
     * @return string Nombre del estado
     */
    private function obtenerNombreEstado($id_estado) {
        $estados = [
            1 => 'Pendiente',
            2 => 'Confirmado',
            3 => 'En preparación',
            4 => 'Listo para recoger',
            5 => 'En camino',
            6 => 'Entregado',
            7 => 'Cancelado'
        ];
        
        return isset($estados[$id_estado]) ? $estados[$id_estado] : 'Desconocido';
    }
    
    /**
     * Obtener todos los estados disponibles
     * @return array Lista de estados
     */
    public static function getEstados() {
        return [
            1 => ['nombre' => 'Pendiente', 'color' => 'warning', 'icon' => 'clock'],
            2 => ['nombre' => 'Confirmado', 'color' => 'info', 'icon' => 'check'],
            3 => ['nombre' => 'En preparación', 'color' => 'primary', 'icon' => 'utensils'],
            4 => ['nombre' => 'Listo para recoger', 'color' => 'success', 'icon' => 'box'],
            5 => ['nombre' => 'En camino', 'color' => 'info', 'icon' => 'motorcycle'],
            6 => ['nombre' => 'Entregado', 'color' => 'success', 'icon' => 'check-circle'],
            7 => ['nombre' => 'Cancelado', 'color' => 'danger', 'icon' => 'times-circle']
        ];
    }
    
    // Obtener un pedido por ID
    public function obtenerPorIdCompleto() {
        $query = "SELECT p.*, 
                        n.nombre AS nombre_negocio, n.imagen_logo, 
                        d.direccion, d.ciudad, d.codigo_postal,
                        u.nombre AS nombre_usuario, u.telefono,
                        r.nombre AS nombre_repartidor, r.telefono AS telefono_repartidor,
                        mp.tipo AS tipo_pago, mp.numero_tarjeta
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
                  LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                  LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
                  LEFT JOIN metodos_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
                  WHERE p.id_pedido = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_pedido);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Asignar valores a propiedades del objeto
            $this->id_usuario = $row['id_usuario'];
            $this->id_negocio = $row['id_negocio'];
            $this->id_direccion = $row['id_direccion'];
            $this->id_repartidor = $row['id_repartidor'];
            $this->id_metodo_pago = $row['id_metodo_pago'];
            $this->id_estado = $row['id_estado'];
            //$this->subtotal = $row['subtotal']; // Column does not exist, removed to fix error
            $this->costo_envio = $row['costo_envio'];
            //$this->total = $row['total']; // Column does not exist, removed to fix error
            $this->fecha_pedido = $row['fecha_creacion'];
            $this->fecha_entrega = $row['fecha_entrega'];
            $this->notas = $row['notas'];
            $this->calificacion = $row['calificacion'];
            $this->comentario = $row['comentario'];
            
            // Agregar información adicional
            $row['detalles'] = $this->obtenerDetalles();
            
            return $row;
        }
        
        return false;
    }
    
    // Obtener detalles (productos) de un pedido
    public function obtenerDetalles() {
        $query = "SELECT dp.*, p.nombre, p.imagen
                  FROM detalles_pedido dp
                  LEFT JOIN productos p ON dp.id_producto = p.id_producto
                  WHERE dp.id_pedido = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_pedido);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener todos los pedidos de un usuario
    public function obtenerPorUsuario($id_usuario, $limit = 10) {
        $query = "SELECT p.*, n.nombre AS nombre_negocio
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  WHERE p.id_usuario = ?
                  ORDER BY p.fecha_creacion DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    // Obtener pedidos disponibles para repartidores
    public function obtenerDisponiblesParaRepartidor($limit = 20) {
        $query = "SELECT p.*, n.nombre AS nombre_negocio, n.direccion AS direccion_negocio,
                         n.latitud AS lat_negocio, n.longitud AS lng_negocio,
                         d.direccion, d.ciudad, d.latitud AS lat_destino, d.longitud AS lng_destino,
                         u.nombre AS nombre_usuario
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
                  LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE p.id_estado = 2 AND p.id_repartidor IS NULL
                  ORDER BY p.fecha_pedido ASC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener pedidos asignados a un repartidor
    public function obtenerPorRepartidor($id_repartidor, $limit = 10) {
        $query = "SELECT p.*, n.nombre AS nombre_negocio, n.direccion AS direccion_negocio,
                         n.latitud AS lat_negocio, n.longitud AS lng_negocio,
                         d.direccion, d.ciudad, d.latitud AS lat_destino, d.longitud AS lng_destino,
                         u.nombre AS nombre_usuario, u.telefono
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  LEFT JOIN direcciones_usuario d ON p.id_direccion = d.id_direccion
                  LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE p.id_repartidor = ? AND p.id_estado IN (2, 3)
                  ORDER BY p.fecha_pedido ASC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_repartidor);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Actualizar el estado de un pedido (original de la clase)
    public function actualizarEstadoOriginal($nuevo_estado) {
        $query = "UPDATE " . $this->table_name . "
                  SET id_estado = ?";
        
        // Si el estado es 4 (entregado), actualizar fecha_entrega
        if ($nuevo_estado == 4) {
            $query .= ", fecha_entrega = NOW()";
        }
        
        $query .= " WHERE id_pedido = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y sanitizar datos
        $nuevo_estado = htmlspecialchars(strip_tags($nuevo_estado));
        
        // Vincular parámetros
        $stmt->bindParam(1, $nuevo_estado);
        $stmt->bindParam(2, $this->id_pedido);
        
        // Ejecutar consulta
        return $stmt->execute();
    }
    
    // Asignar un repartidor a un pedido
    public function asignarRepartidor($id_repartidor) {
        // Validar que el repartidor existe y está disponible
        $queryValidar = "SELECT id, activo, en_entrega, disponible 
                         FROM repartidores 
                         WHERE id = ? LIMIT 1";
        $stmtValidar = $this->conn->prepare($queryValidar);
        $stmtValidar->bindParam(1, $id_repartidor, PDO::PARAM_INT);
        $stmtValidar->execute();
        $repartidor = $stmtValidar->fetch(PDO::FETCH_ASSOC);
        
        if (!$repartidor) {
            error_log("asignarRepartidor: Repartidor no encontrado - ID: $id_repartidor");
            return false;
        }
        
        if ($repartidor['activo'] != 1 || $repartidor['disponible'] != 1) {
            error_log("asignarRepartidor: Repartidor no disponible - ID: $id_repartidor");
            return false;
        }
        
        // Asignar el repartidor al pedido
        $query = "UPDATE " . $this->table_name . "
                  SET id_repartidor = ?, fecha_actualizacion = NOW()
                  WHERE id_pedido = ? AND id_repartidor IS NULL";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitizar datos
        $id_repartidor = (int) $id_repartidor;
        
        // Vincular parámetros
        $stmt->bindParam(1, $id_repartidor, PDO::PARAM_INT);
        $stmt->bindParam(2, $this->id_pedido, PDO::PARAM_INT);
        
        // Ejecutar consulta
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    public function asignarRepartidorYActualizarEstado($id_repartidor, $id_estado) {
        try {
            $this->conn->beginTransaction();

            // Asignar repartidor
            $queryAsignar = "UPDATE " . $this->table_name . " SET id_repartidor = ? WHERE id_pedido = ? AND id_repartidor IS NULL";
            $stmtAsignar = $this->conn->prepare($queryAsignar);
            $stmtAsignar->execute([$id_repartidor, $this->id_pedido]);

            if ($stmtAsignar->rowCount() === 0) {
                $this->conn->rollBack();
                return false; // Ya asignado a otro repartidor
            }

            // Actualizar estado
            $queryEstado = "UPDATE " . $this->table_name . " SET id_estado = ?, fecha_actualizacion = NOW() WHERE id_pedido = ?";
            $stmtEstado = $this->conn->prepare($queryEstado);
            $stmtEstado->execute([$id_estado, $this->id_pedido]);

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error en asignarRepartidorYActualizarEstado: " . $e->getMessage());
            return false;
        }
    }
    
    // Calificar un pedido entregado
    public function calificar($calificacion, $comentario = '') {
        $query = "UPDATE " . $this->table_name . "
                  SET calificacion = ?, comentario = ?
                  WHERE id_pedido = ? AND estado = 'entregado' AND (calificacion IS NULL OR calificacion = 0)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y sanitizar datos
        $calificacion = (int)$calificacion;
        $comentario = htmlspecialchars(strip_tags($comentario));
        
        // Vincular parámetros
        $stmt->bindParam(1, $calificacion);
        $stmt->bindParam(2, $comentario);
        $stmt->bindParam(3, $this->id_pedido);
        
        // Ejecutar consulta
        if ($stmt->execute()) {
            // Actualizar rating del negocio si la calificación fue exitosa
            if ($stmt->rowCount() > 0) {
                $this->actualizarRatingNegocio();
            }
            return true;
        }
        
        return false;
    }

    // En models/Pedido.php
public function contarComprasCompletadas($id_usuario) {
    $query = "SELECT COUNT(*) as total FROM pedidos WHERE id_usuario = ? AND id_estado IN (4,6)";
    
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(1, $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ?? 0;
}
    
    // Método privado para actualizar el rating de un negocio
    private function actualizarRatingNegocio() {
        // Primero obtenemos el id_negocio de este pedido
        $query = "SELECT id_negocio FROM " . $this->table_name . " WHERE id_pedido = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_pedido);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $id_negocio = $row['id_negocio'];
            
            // Calcular el nuevo rating promedio
            $query = "UPDATE negocios n
                      SET rating = (
                          SELECT AVG(calificacion)
                          FROM " . $this->table_name . " p
                          WHERE p.id_negocio = n.id_negocio
                          AND p.calificacion IS NOT NULL
                          AND p.calificacion > 0
                      )
                      WHERE n.id_negocio = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id_negocio);
            
            return $stmt->execute();
        }
        
        return false;
    }
    
    // Cancelar un pedido
    public function cancelar($motivo = '') {
        $query = "UPDATE " . $this->table_name . "
                  SET id_estado = 5, comentario = ?
                  WHERE id_pedido = ? AND id_estado IN (1, 2)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y sanitizar datos
        $motivo = htmlspecialchars(strip_tags($motivo));
        
        // Vincular parámetros
        $stmt->bindParam(1, $motivo);
        $stmt->bindParam(2, $this->id_pedido);
        
        // Ejecutar consulta
        return $stmt->execute();
    }
    
    // Obtener estadísticas para un usuario
    public function obtenerEstadisticasUsuario($id_usuario) {
        $query = "SELECT
                    COUNT(*) as total_pedidos,
                    COUNT(CASE WHEN estado = 'entregado' THEN 1 END) as pedidos_entregados,
                    COUNT(CASE WHEN estado = 'cancelado' THEN 1 END) as pedidos_cancelados,
                    AVG(CASE WHEN estado = 'entregado' THEN total ELSE NULL END) as gasto_promedio,
                    SUM(CASE WHEN estado = 'entregado' THEN total ELSE 0 END) as gasto_total,
                    COUNT(DISTINCT id_negocio) as negocios_diferentes
                  FROM " . $this->table_name . "
                  WHERE id_usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener estadísticas para un negocio
    public function obtenerEstadisticasNegocio($id_negocio) {
        $query = "SELECT
                    COUNT(*) as total_pedidos,
                    COUNT(CASE WHEN id_estado = 6 THEN 1 END) as pedidos_entregados,
                    COUNT(CASE WHEN id_estado = 7 THEN 1 END) as pedidos_cancelados,
                    AVG(CASE WHEN id_estado = 6 THEN monto_total ELSE NULL END) as valor_promedio,
                    SUM(CASE WHEN id_estado = 6 THEN monto_total ELSE 0 END) as ingreso_total,
                    COUNT(CASE WHEN id_estado = 1 THEN 1 END) as pendientes,
                    COUNT(CASE WHEN id_estado = 3 THEN 1 END) as en_preparacion,
                    COUNT(CASE WHEN id_estado = 4 THEN 1 END) as listos,
                    COUNT(CASE WHEN id_estado = 5 THEN 1 END) as en_camino,
                    COUNT(CASE WHEN id_estado = 6 AND DATE(fecha_creacion) = CURDATE() THEN 1 END) as entregados_hoy,
                    SUM(CASE WHEN id_estado = 6 AND DATE(fecha_creacion) = CURDATE() THEN monto_total ELSE 0 END) as ingresos_hoy,
                    COUNT(CASE WHEN DATE(fecha_creacion) = CURDATE() THEN 1 END) as hoy,
                    COUNT(CASE WHEN id_estado IN (2, 3, 4) THEN 1 END) as en_proceso
                  FROM " . $this->table_name . "
                  WHERE id_negocio = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->execute();
        
        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular valores adicionales
        $estadisticas['en_proceso'] = $estadisticas['en_preparacion'] + $estadisticas['en_camino'];
        $estadisticas['porcentaje_completados'] = $estadisticas['total_pedidos'] > 0 ? 
            round(($estadisticas['pedidos_entregados'] / $estadisticas['total_pedidos']) * 100, 2) : 0;
        $estadisticas['ticket_promedio'] = $estadisticas['valor_promedio'];
        
        return $estadisticas;
    }
    
    // Obtener pedidos nuevos desde la última verificación
    public function obtenerPedidosNuevos($id_negocio, $fecha_ultimo_check) {
        $query = "SELECT p.id_pedido, p.fecha_creacion, p.id_estado, 
                         u.nombre AS nombre_cliente, u.telefono AS telefono_cliente
                  FROM " . $this->table_name . " p
                  JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE p.id_negocio = ? 
                  AND p.fecha_creacion > ?
                  AND p.id_estado IN (1, 2)  /* 1:pendiente, 2:en_preparacion */
                  ORDER BY p.fecha_creacion DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->bindParam(2, $fecha_ultimo_check);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener estadísticas para un repartidor
    public function obtenerEstadisticasRepartidor($id_repartidor) {
        $query = "SELECT
                    COUNT(*) as total_pedidos,
                    COUNT(CASE WHEN estado = 'entregado' THEN 1 END) as pedidos_entregados,
                    COUNT(CASE WHEN estado = 'cancelado' THEN 1 END) as pedidos_cancelados,
                    AVG(TIMESTAMPDIFF(MINUTE, fecha_pedido, fecha_entrega)) as tiempo_entrega_promedio,
                    SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) / COUNT(*) * 100 as tasa_completitud
                  FROM " . $this->table_name . "
                  WHERE id_repartidor = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_repartidor);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener el historial de pedidos de un usuario con paginación
    public function obtenerHistorialUsuario($id_usuario, $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT p.*, n.nombre AS nombre_negocio
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  WHERE p.id_usuario = ?
                  ORDER BY p.fecha_creacion DESC
                  LIMIT ? OFFSET ?";
                  $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->bindParam(2, $per_page, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener el conteo total para la paginación
        $query_count = "SELECT COUNT(*) as total
                         FROM " . $this->table_name . "
                         WHERE id_usuario = ?";
        
        $stmt_count = $this->conn->prepare($query_count);
        $stmt_count->bindParam(1, $id_usuario);
        $stmt_count->execute();
        
        $row_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $total_pages = ceil($row_count['total'] / $per_page);
        
        return [
            'pedidos' => $pedidos,
            'pagination' => [
                'total' => $row_count['total'],
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages
            ]
        ];
    }
    
    // Obtener los pedidos en curso (activos) de un usuario
    public function obtenerPedidosActivos($id_usuario) {
        $query = "SELECT p.*, n.nombre AS nombre_negocio, n.imagen_logo,
                         r.nombre AS nombre_repartidor, r.telefono AS telefono_repartidor
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  LEFT JOIN repartidores r ON p.id_repartidor = r.id_repartidor
                  WHERE p.id_usuario = ? AND p.estado IN ('pendiente', 'en_preparacion', 'en_camino')
                  ORDER BY p.fecha_pedido DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener los productos más vendidos de un negocio
    public function obtenerProductosMasVendidos($id_negocio, $limit = 5) {
        $query = "SELECT p.id_producto, p.nombre, p.precio, p.imagen,
                         SUM(dp.cantidad) as total_vendido
                  FROM detalles_pedido dp
                  JOIN productos p ON dp.id_producto = p.id_producto
                  JOIN " . $this->table_name . " ped ON dp.id_pedido = ped.id_pedido
                  WHERE ped.id_negocio = ? AND ped.estado = 'entregado'
                  GROUP BY p.id_producto
                  ORDER BY total_vendido DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener los ingresos por día de un negocio
    public function obtenerIngresosPorDia($id_negocio, $dias = 7) {
        $query = "SELECT 
                    DATE(fecha_creacion) as fecha,
                    SUM(total) as ingreso_total,
                    COUNT(*) as num_pedidos
                  FROM " . $this->table_name . "
                  WHERE id_negocio = ? 
                    AND estado = 'entregado'
                    AND fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  GROUP BY DATE(fecha_pedido)
                  ORDER BY fecha ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->bindParam(2, $dias, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Buscar pedidos por número o estado
    public function buscar($busqueda, $id_usuario = null, $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $busqueda = "%{$busqueda}%";
        
        $query = "SELECT p.*, n.nombre AS nombre_negocio, n.imagen_logo
                  FROM " . $this->table_name . " p
                  LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
                  WHERE (p.id_pedido LIKE ? OR p.estado LIKE ?)";
        
        // Si se proporciona id_usuario, filtrar por ese usuario
        if ($id_usuario) {
            $query .= " AND p.id_usuario = ?";
        }
        
        $query .= " ORDER BY p.fecha_pedido DESC
                    LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($query);
        
        if ($id_usuario) {
            $stmt->bindParam(1, $busqueda);
            $stmt->bindParam(2, $busqueda);
            $stmt->bindParam(3, $id_usuario);
            $stmt->bindParam(4, $per_page, PDO::PARAM_INT);
            $stmt->bindParam(5, $offset, PDO::PARAM_INT);
        } else {
            $stmt->bindParam(1, $busqueda);
            $stmt->bindParam(2, $busqueda);
            $stmt->bindParam(3, $per_page, PDO::PARAM_INT);
            $stmt->bindParam(4, $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener el conteo total para la paginación
        $query_count = "SELECT COUNT(*) as total
                         FROM " . $this->table_name . " p
                         WHERE (p.id_pedido LIKE ? OR p.estado LIKE ?)";
        
        if ($id_usuario) {
            $query_count .= " AND p.id_usuario = ?";
        }
        
        $stmt_count = $this->conn->prepare($query_count);
        
        if ($id_usuario) {
            $stmt_count->bindParam(1, $busqueda);
            $stmt_count->bindParam(2, $busqueda);
            $stmt_count->bindParam(3, $id_usuario);
        } else {
            $stmt_count->bindParam(1, $busqueda);
            $stmt_count->bindParam(2, $busqueda);
        }
        
        $stmt_count->execute();
        
        $row_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $total_pages = ceil($row_count['total'] / $per_page);
        
        return [
            'pedidos' => $pedidos,
            'pagination' => [
                'total' => $row_count['total'],
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => $total_pages
            ]
        ];
    }
    
public function cambiarEstado($id_pedido, $nuevo_estado) {
    try {
        error_log("=== MÉTODO cambiarEstado INICIADO ===");
        error_log("ID Pedido: $id_pedido");
        error_log("Nuevo estado recibido: $nuevo_estado (tipo: " . gettype($nuevo_estado) . ")");
        
        // Asegurar que tenemos la conexión
        if (!$this->conn) {
            error_log("ERROR: No hay conexión a la base de datos");
            return false;
        }
        
        // Si el nuevo_estado es un string, convertirlo a ID
        if (is_string($nuevo_estado)) {
            switch ($nuevo_estado) {
                case 'pendiente':
                    $id_estado = 1;
                    break;
                case 'confirmado':
                    $id_estado = 2;
                    break;
                case 'preparando':
                    $id_estado = 3;
                    break;
                case 'listo_para_entrega':
                case 'listo':
                    $id_estado = 4;
                    break;
                case 'en_camino':
                    $id_estado = 5;
                    break;
                case 'entregado':
                    $id_estado = 6;
                    break;
                case 'cancelado':
                    $id_estado = 7;
                    break;
                default:
                    error_log("ERROR: Estado de pedido no válido: $nuevo_estado");
                    return false;
            }
        } else {
            // Si ya es un número, usarlo directamente
            $id_estado = (int)$nuevo_estado;
        }

        error_log("ID Estado final: $id_estado");

        // Validar que el estado sea válido
        $estados_validos = [1, 2, 3, 4, 5, 6, 7];
        if (!in_array($id_estado, $estados_validos)) {
            error_log("ERROR: ID de estado no válido: $id_estado");
            return false;
        }

        // Verificar que el pedido existe
        $query_check = "SELECT id_pedido, id_estado, id_negocio FROM pedidos WHERE id_pedido = ?";
        $stmt_check = $this->conn->prepare($query_check);
        $stmt_check->bindParam(1, $id_pedido, PDO::PARAM_INT);
        $stmt_check->execute();
        
        $pedido_actual = $stmt_check->fetch(PDO::FETCH_ASSOC);
        error_log("Pedido actual encontrado: " . print_r($pedido_actual, true));
        
        if (!$pedido_actual) {
            error_log("ERROR: Pedido no encontrado: $id_pedido");
            return false;
        }

        // Actualizar el estado usando prepared statement con ? placeholders
        $query = "UPDATE pedidos 
                  SET id_estado = ?, 
                      fecha_actualizacion = NOW() 
                  WHERE id_pedido = ?";
        
        error_log("Query a ejecutar: $query");
        error_log("Parámetros: id_estado=$id_estado, id_pedido=$id_pedido");
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_estado, PDO::PARAM_INT);
        $stmt->bindParam(2, $id_pedido, PDO::PARAM_INT);
        
        $resultado = $stmt->execute();
        $filas_afectadas = $stmt->rowCount();
        
        error_log("Resultado execute(): " . ($resultado ? 'true' : 'false'));
        error_log("Filas afectadas: $filas_afectadas");
        
        if ($resultado && $filas_afectadas > 0) {
            // Actualizar propiedades del objeto
            $this->id_pedido = $id_pedido;
            $this->id_estado = $id_estado;
            
            // Verificar que el cambio se hizo
            $query_verify = "SELECT id_estado FROM pedidos WHERE id_pedido = ?";
            $stmt_verify = $this->conn->prepare($query_verify);
            $stmt_verify->bindParam(1, $id_pedido, PDO::PARAM_INT);
            $stmt_verify->execute();
            $estado_verificado = $stmt_verify->fetch(PDO::FETCH_ASSOC);
            
            error_log("Estado verificado después del update: " . print_r($estado_verificado, true));
            
            // Enviar notificación WhatsApp cuando el negocio acepta (estado 2) o cambia a preparando (estado 3)
            if ($id_estado == 2 || $id_estado == 3) {
                try {
                    require_once __DIR__ . '/../api/WhatsAppLocalClient.php';
                    
                    // Obtener teléfono del negocio
                    $query_negocio = "SELECT n.telefono as telefono_negocio, n.nombre as nombre_negocio,
                                             p.monto_total, u.nombre as nombre_cliente, u.telefono as telefono_cliente
                                      FROM pedidos p
                                      JOIN negocios n ON p.id_negocio = n.id_negocio
                                      JOIN usuarios u ON p.id_usuario = u.id_usuario
                                      WHERE p.id_pedido = ?";
                    $stmt_negocio = $this->conn->prepare($query_negocio);
                    $stmt_negocio->bindParam(1, $id_pedido, PDO::PARAM_INT);
                    $stmt_negocio->execute();
                    $data = $stmt_negocio->fetch(PDO::FETCH_ASSOC);
                    
                    if ($data && !empty($data['telefono_negocio'])) {
                        $whatsapp = new WhatsAppLocalClient();
                        
                        // Si es estado 2 (confirmado), enviar mensaje al negocio
                        if ($id_estado == 2 && !empty($data['telefono_negocio'])) {
                            $whatsapp->sendOrderNotification(
                                $data['telefono_negocio'],
                                $id_pedido,
                                'confirmado',
                                $data['monto_total'],
                                $data['nombre_cliente'],
                                $data['nombre_negocio'],
                                2
                            );
                            error_log("WhatsApp enviado al negocio: Pedido #$id_pedido confirmado");
                        }
                        
                        // Enviar notificación al cliente
                        if (!empty($data['telefono_cliente'])) {
                            $whatsapp->sendOrderNotification(
                                $data['telefono_cliente'],
                                $id_pedido,
                                $id_estado == 2 ? 'confirmado' : 'en_preparacion',
                                $data['monto_total'],
                                $data['nombre_cliente'],
                                $data['nombre_negocio'],
                                $id_estado
                            );
                            error_log("WhatsApp enviado al cliente: Pedido #$id_pedido estado $id_estado");
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error enviando WhatsApp en cambiarEstado: " . $e->getMessage());
                }
            }
            
            error_log("ÉXITO: Estado del pedido $id_pedido actualizado correctamente a: $id_estado");
            error_log("=== MÉTODO cambiarEstado TERMINADO ===");
            
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("ERROR: No se pudo actualizar. ErrorInfo: " . print_r($errorInfo, true));
            error_log("=== MÉTODO cambiarEstado TERMINADO CON ERROR ===");
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("ERROR PDO en cambiarEstado: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        return false;
    } catch (Exception $e) {
        error_log("ERROR general en cambiarEstado: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        return false;
    }
}
    
    // Método para obtener pedidos de hoy
    public function obtenerPedidosHoy($id_negocio) {
        $fecha_hoy = date('Y-m-d');
        $query = "SELECT p.*, u.nombre as nombre_cliente, u.telefono as telefono_cliente
                 FROM pedidos p
                 LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                 WHERE p.id_negocio = ? AND DATE(p.fecha_creacion) = ?
                 ORDER BY p.fecha_creacion DESC";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->bindParam(2, $fecha_hoy);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>