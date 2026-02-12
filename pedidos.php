<?php
class Pedido {
    // Propiedades de base de datos
    private $conn;
    private $table = 'pedidos';
    
    // Propiedades del pedido
    public $id;
    public $usuario_id;
    public $negocio_id;
    public $repartidor_id;
    public $direccion_id;
    public $metodo_pago_id;
    public $fecha_pedido;
    public $subtotal;
    public $costo_envio;
    public $impuestos;
    public $descuento;
    public $propina;
    public $total;
    public $tiempo_estimado_entrega;
    public $notas;
    public $estado_actual;
    public $codigo_seguimiento;
    public $valorado;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Método para obtener todos los pedidos (para admin)
    public function obtenerTodos($limite = 20, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    p.id, 
                    p.usuario_id, 
                    p.negocio_id, 
                    p.repartidor_id, 
                    p.direccion_id, 
                    p.metodo_pago_id, 
                    p.fecha_pedido, 
                    p.subtotal, 
                    p.costo_envio, 
                    p.impuestos, 
                    p.descuento, 
                    p.propina, 
                    p.total, 
                    p.tiempo_estimado_entrega, 
                    p.notas, 
                    p.estado_actual, 
                    p.codigo_seguimiento,
                    p.valorado,
                    u.nombre as nombre_usuario,
                    u.apellido as apellido_usuario,
                    n.nombre as nombre_negocio,
                    CONCAT(r.nombre, " ", r.apellido) as nombre_repartidor
                FROM 
                    ' . $this->table . ' p
                LEFT JOIN
                    usuarios u ON p.usuario_id = u.id
                LEFT JOIN
                    negocios n ON p.negocio_id = n.id
                LEFT JOIN
                    repartidores r ON p.repartidor_id = r.id
                ORDER BY
                    p.fecha_pedido DESC
                LIMIT :limite OFFSET :offset';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener pedidos de un usuario
    public function obtenerHistorialUsuario($usuario_id, $limite = 10, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    p.id, 
                    p.usuario_id, 
                    p.negocio_id, 
                    p.fecha_pedido, 
                    p.total, 
                    p.estado_actual,
                    p.valorado,
                    n.nombre as nombre_negocio
                FROM 
                    ' . $this->table . ' p
                LEFT JOIN
                    negocios n ON p.negocio_id = n.id
                WHERE 
                    p.usuario_id = :usuario_id
                ORDER BY
                    p.fecha_pedido DESC
                LIMIT :limite OFFSET :offset';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener pedidos de un negocio
    public function obtenerPedidosNegocio($negocio_id, $limite = 20, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    p.id, 
                    p.usuario_id, 
                    p.negocio_id, 
                    p.repartidor_id, 
                    p.fecha_pedido, 
                    p.total, 
                    p.tiempo_estimado_entrega,
                    p.estado_actual,
                    u.nombre as nombre_usuario,
                    u.apellido as apellido_usuario,
                    u.telefono as telefono_usuario,
                    CONCAT(r.nombre, " ", r.apellido) as nombre_repartidor
                FROM 
                    ' . $this->table . ' p
                LEFT JOIN
                    usuarios u ON p.usuario_id = u.id
                LEFT JOIN
                    repartidores r ON p.repartidor_id = r.id
                WHERE 
                    p.negocio_id = :negocio_id
                ORDER BY
                    FIELD(p.estado_actual, "pendiente", "confirmado", "en_preparacion", "en_camino", "entregado", "cancelado"),
                    p.fecha_pedido DESC
                LIMIT :limite OFFSET :offset';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener pedidos asignados a un repartidor
    public function obtenerPedidosRepartidor($repartidor_id, $limite = 10, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    p.id, 
                    p.usuario_id, 
                    p.negocio_id, 
                    p.fecha_pedido, 
                    p.total, 
                    p.estado_actual,
                    p.tiempo_estimado_entrega,
                    n.nombre as nombre_negocio,
                    n.direccion as direccion_negocio,
                    n.telefono as telefono_negocio,
                    u.nombre as nombre_usuario,
                    u.apellido as apellido_usuario,
                    u.telefono as telefono_usuario,
                    d.calle, 
                    d.numero, 
                    d.interior, 
                    d.colonia, 
                    d.ciudad, 
                    d.estado, 
                    d.codigo_postal, 
                    d.referencias,
                    d.latitud,
                    d.longitud
                FROM 
                    ' . $this->table . ' p
                LEFT JOIN
                    negocios n ON p.negocio_id = n.id
                LEFT JOIN
                    usuarios u ON p.usuario_id = u.id
                LEFT JOIN
                    direcciones_usuario d ON p.direccion_id = d.id
                WHERE 
                    p.repartidor_id = :repartidor_id AND
                    p.estado_actual IN ("confirmado", "en_preparacion", "en_camino") AND
                    p.notas NOT LIKE "%PickUp%"
                ORDER BY
                    FIELD(p.estado_actual, "confirmado", "en_preparacion", "en_camino"),
                    p.fecha_pedido ASC
                LIMIT :limite OFFSET :offset';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':repartidor_id', $repartidor_id);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener un pedido por ID
    public function obtenerPorId() {
        $query = 'SELECT 
                    p.id, 
                    p.usuario_id, 
                    p.negocio_id, 
                    p.repartidor_id, 
                    p.direccion_id, 
                    p.metodo_pago_id, 
                    p.fecha_pedido, 
                    p.subtotal, 
                    p.costo_envio, 
                    p.impuestos, 
                    p.descuento, 
                    p.propina, 
                    p.total, 
                    p.tiempo_estimado_entrega, 
                    p.notas, 
                    p.estado_actual, 
                    p.codigo_seguimiento,
                    p.valorado,
                    u.nombre as nombre_usuario,
                    u.apellido as apellido_usuario,
                    u.email as email_usuario,
                    u.telefono as telefono_usuario,
                    n.nombre as nombre_negocio,
                    n.telefono as telefono_negocio,
                    n.direccion as direccion_negocio,
                    CONCAT(r.nombre, " ", r.apellido) as nombre_repartidor,
                    r.telefono as telefono_repartidor,
                    d.calle, 
                    d.numero, 
                    d.interior, 
                    d.colonia, 
                    d.ciudad, 
                    d.estado, 
                    d.codigo_postal, 
                    d.referencias
                FROM 
                    ' . $this->table . ' p
                LEFT JOIN
                    usuarios u ON p.usuario_id = u.id
                LEFT JOIN
                    negocios n ON p.negocio_id = n.id
                LEFT JOIN
                    repartidores r ON p.repartidor_id = r.id
                LEFT JOIN
                    direcciones_usuario d ON p.direccion_id = d.id
                WHERE 
                    p.id = :id
                LIMIT 0,1';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        
        // Ejecutar query
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Establecer propiedades
            $this->usuario_id = $row['usuario_id'];
            $this->negocio_id = $row['negocio_id'];
            $this->repartidor_id = $row['repartidor_id'];
            $this->direccion_id = $row['direccion_id'];
            $this->metodo_pago_id = $row['metodo_pago_id'];
            $this->fecha_pedido = $row['fecha_pedido'];
            $this->subtotal = $row['subtotal'];
            $this->costo_envio = $row['costo_envio'];
            $this->impuestos = $row['impuestos'];
            $this->descuento = $row['descuento'];
            $this->propina = $row['propina'];
            $this->total = $row['total'];
            $this->tiempo_estimado_entrega = $row['tiempo_estimado_entrega'];
            $this->notas = $row['notas'];
            $this->estado_actual = $row['estado_actual'];
            $this->codigo_seguimiento = $row['codigo_seguimiento'];
            $this->valorado = $row['valorado'];
            
            return $row; // Devolver todos los datos incluyendo la información relacional
        }
        
        return false;
    }
    
    // Método para crear un nuevo pedido
    public function crear() {
        // Crear código de seguimiento único
        $this->codigo_seguimiento = $this->generarCodigoSeguimiento();
        
        // Iniciar transacción
        $this->conn->beginTransaction();
        
        try {
            // Insertar el pedido
            $query = 'INSERT INTO ' . $this->table . '
                    SET
                        usuario_id = :usuario_id,
                        negocio_id = :negocio_id,
                        direccion_id = :direccion_id,
                        metodo_pago_id = :metodo_pago_id,
                        fecha_pedido = NOW(),
                        subtotal = :subtotal,
                        costo_envio = :costo_envio,
                        impuestos = :impuestos,
                        descuento = :descuento,
                        propina = :propina,
                        total = :total,
                        tiempo_estimado_entrega = :tiempo_estimado_entrega,
                        notas = :notas,
                        estado_actual = "pendiente",
                        codigo_seguimiento = :codigo_seguimiento,
                        valorado = 0';
            
            $stmt = $this->conn->prepare($query);
            
            // Limpiar datos
            $this->usuario_id = htmlspecialchars(strip_tags($this->usuario_id));
            $this->negocio_id = htmlspecialchars(strip_tags($this->negocio_id));
            $this->direccion_id = htmlspecialchars(strip_tags($this->direccion_id));
            $this->metodo_pago_id = htmlspecialchars(strip_tags($this->metodo_pago_id));
            $this->subtotal = htmlspecialchars(strip_tags($this->subtotal));
            $this->costo_envio = htmlspecialchars(strip_tags($this->costo_envio));
            $this->impuestos = htmlspecialchars(strip_tags($this->impuestos));
            $this->descuento = htmlspecialchars(strip_tags($this->descuento));
            $this->propina = htmlspecialchars(strip_tags($this->propina));
            $this->total = htmlspecialchars(strip_tags($this->total));
            $this->tiempo_estimado_entrega = htmlspecialchars(strip_tags($this->tiempo_estimado_entrega));
            $this->notas = htmlspecialchars(strip_tags($this->notas));
            
            // Vincular parámetros
            $stmt->bindParam(':usuario_id', $this->usuario_id);
            $stmt->bindParam(':negocio_id', $this->negocio_id);
            $stmt->bindParam(':direccion_id', $this->direccion_id);
            $stmt->bindParam(':metodo_pago_id', $this->metodo_pago_id);
            $stmt->bindParam(':subtotal', $this->subtotal);
            $stmt->bindParam(':costo_envio', $this->costo_envio);
            $stmt->bindParam(':impuestos', $this->impuestos);
            $stmt->bindParam(':descuento', $this->descuento);
            $stmt->bindParam(':propina', $this->propina);
            $stmt->bindParam(':total', $this->total);
            $stmt->bindParam(':tiempo_estimado_entrega', $this->tiempo_estimado_entrega);
            $stmt->bindParam(':notas', $this->notas);
            $stmt->bindParam(':codigo_seguimiento', $this->codigo_seguimiento);
            
            // Ejecutar query
            $stmt->execute();
            
            // Obtener ID del pedido insertado
            $this->id = $this->conn->lastInsertId();
            
            // Registrar el estado inicial del pedido
            $this->registrarCambioEstado('pendiente', 'Pedido recibido');
            
            // Confirmar transacción
            $this->conn->commit();
            
            return true;
            
        } catch(PDOException $e) {
            // Revertir transacción en caso de error
            $this->conn->rollBack();
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
    
    // Método para actualizar un pedido
    public function actualizar() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    repartidor_id = :repartidor_id,
                    subtotal = :subtotal,
                    costo_envio = :costo_envio,
                    impuestos = :impuestos,
                    descuento = :descuento,
                    propina = :propina,
                    total = :total,
                    tiempo_estimado_entrega = :tiempo_estimado_entrega,
                    notas = :notas
                WHERE
                    id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->repartidor_id = htmlspecialchars(strip_tags($this->repartidor_id));
        $this->subtotal = htmlspecialchars(strip_tags($this->subtotal));
        $this->costo_envio = htmlspecialchars(strip_tags($this->costo_envio));
        $this->impuestos = htmlspecialchars(strip_tags($this->impuestos));
        $this->descuento = htmlspecialchars(strip_tags($this->descuento));
        $this->propina = htmlspecialchars(strip_tags($this->propina));
        $this->total = htmlspecialchars(strip_tags($this->total));
        $this->tiempo_estimado_entrega = htmlspecialchars(strip_tags($this->tiempo_estimado_entrega));
        $this->notas = htmlspecialchars(strip_tags($this->notas));
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':repartidor_id', $this->repartidor_id);
        $stmt->bindParam(':subtotal', $this->subtotal);
        $stmt->bindParam(':costo_envio', $this->costo_envio);
        $stmt->bindParam(':impuestos', $this->impuestos);
        $stmt->bindParam(':descuento', $this->descuento);
        $stmt->bindParam(':propina', $this->propina);
        $stmt->bindParam(':total', $this->total);
        $stmt->bindParam(':tiempo_estimado_entrega', $this->tiempo_estimado_entrega);
        $stmt->bindParam(':notas', $this->notas);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para asignar un repartidor a un pedido
    public function asignarRepartidor($repartidor_id, $comentario = 'Repartidor asignado') {
        $query = 'UPDATE ' . $this->table . '
                SET
                    repartidor_id = :repartidor_id
                WHERE
                    id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':repartidor_id', $repartidor_id);
        
        // Ejecutar query
        if($stmt->execute()) {
            $this->repartidor_id = $repartidor_id;
            
            // Registrar la asignación en el historial
            if ($this->estado_actual == 'pendiente') {
                $this->cambiarEstado('confirmado', $comentario);
            } else {
                $this->registrarCambioEstado($this->estado_actual, $comentario);
            }
            
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para cambiar el estado de un pedido
    public function cambiarEstado($nuevo_estado, $comentario = '') {
        // Verificar transición válida de estados
        if (!$this->esTransicionEstadoValida($this->estado_actual, $nuevo_estado)) {
            return false;
        }
        
        $query = 'UPDATE ' . $this->table . '
                SET
                    estado_actual = :nuevo_estado
                WHERE
                    id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nuevo_estado', $nuevo_estado);
        
        // Ejecutar query
        if($stmt->execute()) {
            // Registrar cambio en el historial
            if ($this->registrarCambioEstado($nuevo_estado, $comentario)) {
                $this->estado_actual = $nuevo_estado;
                return true;
            }
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para cancelar un pedido
    public function cancelarPedido($motivo) {
        // Solo se pueden cancelar pedidos pendientes o confirmados
        if ($this->estado_actual != 'pendiente' && $this->estado_actual != 'confirmado') {
            return false;
        }
        
        return $this->cambiarEstado('cancelado', $motivo);
    }
    
    // Método para registrar un cambio de estado en el historial
    private function registrarCambioEstado($estado, $comentario = '') {
        $query = 'INSERT INTO historial_estados_pedido
                SET
                    pedido_id = :pedido_id,
                    estado = :estado,
                    comentario = :comentario,
                    fecha = NOW()';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':pedido_id', $this->id);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':comentario', $comentario);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para obtener el historial de estados de un pedido
    public function obtenerHistorialEstados() {
        $query = 'SELECT 
                    h.estado, 
                    h.comentario, 
                    h.fecha,
                    CONCAT(r.nombre, " ", r.apellido) as nombre_repartidor
                FROM 
                    historial_estados_pedido h
                LEFT JOIN
                    repartidores r ON r.id = (SELECT repartidor_id FROM pedidos WHERE id = h.pedido_id AND fecha_pedido <= h.fecha ORDER BY fecha_pedido DESC LIMIT 1)
                WHERE 
                    h.pedido_id = :pedido_id
                ORDER BY
                    h.fecha ASC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':pedido_id', $this->id);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para marcar un pedido como valorado
    public function marcarValorado() {
        $query = 'UPDATE ' . $this->table . '
                SET
                    valorado = 1
                WHERE
                    id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        
        // Ejecutar query
        if($stmt->execute()) {
            $this->valorado = 1;
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para obtener los detalles (productos) de un pedido
    public function obtenerDetallesPedido() {
        $query = 'SELECT 
                    d.id, 
                    d.pedido_id, 
                    d.producto_id, 
                    d.cantidad, 
                    d.precio_unitario, 
                    d.subtotal, 
                    d.notas,
                    p.nombre as nombre_producto,
                    p.descripcion as descripcion_producto,
                    p.imagen as imagen_producto
                FROM 
                    detalles_pedido d
                LEFT JOIN
                    productos p ON d.producto_id = p.id
                WHERE 
                    d.pedido_id = :pedido_id
                ORDER BY
                    d.id ASC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':pedido_id', $this->id);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para agregar detalles (productos) a un pedido
    public function agregarDetalle($producto_id, $cantidad, $precio_unitario, $notas = '') {
        $subtotal = $cantidad * $precio_unitario;
        
        $query = 'INSERT INTO detalles_pedido
                SET
                    pedido_id = :pedido_id,
                    producto_id = :producto_id,
                    cantidad = :cantidad,
                    precio_unitario = :precio_unitario,
                    subtotal = :subtotal,
                    notas = :notas';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':pedido_id', $this->id);
        $stmt->bindParam(':producto_id', $producto_id);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':precio_unitario', $precio_unitario);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':notas', $notas);
        
        // Ejecutar query
        if($stmt->execute()) {
            $detalle_id = $this->conn->lastInsertId();
            return $detalle_id;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para agregar opciones a un detalle de pedido
    public function agregarOpcionDetalle($detalle_id, $opcion_id, $cantidad = 1, $precio = 0) {
        $query = 'INSERT INTO opciones_detalle_pedido
                SET
                    detalle_pedido_id = :detalle_id,
                    opcion_id = :opcion_id,
                    cantidad = :cantidad,
                    precio = :precio';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':detalle_id', $detalle_id);
        $stmt->bindParam(':opcion_id', $opcion_id);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':precio', $precio);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para obtener las opciones de un detalle de pedido
    public function obtenerOpcionesDetalle($detalle_id) {
        $query = 'SELECT 
                    o.id, 
                    o.detalle_pedido_id, 
                    o.opcion_id, 
                    o.cantidad, 
                    o.precio,
                    op.nombre as nombre_opcion,
                    g.nombre as grupo_opcion
                FROM 
                    opciones_detalle_pedido o
                LEFT JOIN
                    opciones op ON o.opcion_id = op.id
                LEFT JOIN
                    grupos_opciones g ON op.grupo_opcion_id = g.id
                WHERE 
                    o.detalle_pedido_id = :detalle_id
                ORDER BY
                    g.nombre, op.nombre';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':detalle_id', $detalle_id);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para actualizar el total del pedido basado en los detalles
    public function actualizarTotal() {
        // Obtener subtotal de los detalles
        $query = 'SELECT SUM(subtotal) as total_detalles FROM detalles_pedido WHERE pedido_id = :pedido_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pedido_id', $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->subtotal = $row['total_detalles'] ?: 0;
        
        // Calcular total
        $this->total = $this->subtotal + $this->costo_envio + $this->impuestos - $this->descuento + $this->propina;
        
        // Actualizar en la base de datos
        $query = 'UPDATE ' . $this->table . '
                SET
                    subtotal = :subtotal,
                    total = :total
                WHERE
                    id = :id';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':subtotal', $this->subtotal);
        $stmt->bindParam(':total', $this->total);
        
        // Ejecutar query
        if($stmt->execute()) {
            return true;
        }
        
        printf("Error: %s.\n", $stmt->error);
        return false;
    }
    
    // Método para generar un código de seguimiento único
    private function generarCodigoSeguimiento() {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $codigo = '';
        
        // Generar un código aleatorio de 8 caracteres
        for ($i = 0; $i < 8; $i++) {
            $codigo .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        // Verificar que el código no exista ya
        $query = 'SELECT id FROM ' . $this->table . ' WHERE codigo_seguimiento = :codigo_seguimiento';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':codigo_seguimiento', $codigo);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Si el código ya existe, generar otro recursivamente
            return $this->generarCodigoSeguimiento();
        }
        
        return $codigo;
    }
    
    // Verificar si una transición de estado es válida
    private function esTransicionEstadoValida($estado_actual, $nuevo_estado) {
        // Definir transiciones válidas para cada estado
        $transiciones_validas = [
            'pendiente' => ['confirmado', 'cancelado'],
            'confirmado' => ['en_preparacion', 'cancelado'],
            'en_preparacion' => ['en_camino', 'cancelado'],
            'en_camino' => ['entregado', 'cancelado'],
            'entregado' => [], // No hay transición después de entregado
            'cancelado' => []  // No hay transición después de cancelado
        ];
        
        // Verificar si el nuevo estado está en las transiciones válidas para el estado actual
        if (isset($transiciones_validas[$estado_actual]) && in_array($nuevo_estado, $transiciones_validas[$estado_actual])) {
            return true;
        }
        
        return false;
    }
    
    // Método para búsqueda de pedidos
    public function buscar($termino, $limite = 20, $pagina = 1) {
        $offset = ($pagina - 1) * $limite;
        
        $query = 'SELECT 
                    p.id, 
                    p.usuario_id, 
                    p.negocio_id, 
                    p.fecha_pedido, 
                    p.total, 
                    p.estado_actual,
                    p.codigo_seguimiento,
                    CONCAT(u.nombre, " ", u.apellido) as nombre_completo_usuario,
                    u.email as email_usuario,
                    n.nombre as nombre_negocio
                FROM 
                    ' . $this->table . ' p
                LEFT JOIN
                    usuarios u ON p.usuario_id = u.id
                LEFT JOIN
                    negocios n ON p.negocio_id = n.id
                WHERE 
                    p.codigo_seguimiento LIKE :termino OR
                    CONCAT(u.nombre, " ", u.apellido) LIKE :termino OR
                    u.email LIKE :termino OR
                    n.nombre LIKE :termino
                ORDER BY
                    p.fecha_pedido DESC
                LIMIT :limite OFFSET :offset';
        
        $termino = '%' . $termino . '%';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':termino', $termino);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener estadísticas de pedidos para un negocio
    public function obtenerEstadisticasNegocio($negocio_id, $desde = null, $hasta = null) {
        // Configurar fechas si no se proporcionan
        if (!$desde) {
            $desde = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!$hasta) {
            $hasta = date('Y-m-d');
        }
        
        $query = 'SELECT 
                    COUNT(*) as total_pedidos,
                    SUM(total) as ingresos_totales,
                    AVG(total) as promedio_pedido,
                    COUNT(CASE WHEN estado_actual = "cancelado" THEN 1 ELSE NULL END) as pedidos_cancelados,
                    COUNT(CASE WHEN estado_actual = "entregado" THEN 1 ELSE NULL END) as pedidos_entregados,
                    (COUNT(CASE WHEN estado_actual = "entregado" THEN 1 ELSE NULL END) / COUNT(*)) * 100 as tasa_exito
                FROM 
                    ' . $this->table . '
                WHERE 
                    negocio_id = :negocio_id AND
                    DATE(fecha_pedido) BETWEEN :desde AND :hasta';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Método para obtener estadísticas diarias de pedidos para un negocio
    public function obtenerEstadisticasDiariasNegocio($negocio_id, $desde = null, $hasta = null) {
        // Configurar fechas si no se proporcionan
        if (!$desde) {
            $desde = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!$hasta) {
            $hasta = date('Y-m-d');
        }
        
        $query = 'SELECT 
                    DATE(fecha_pedido) as fecha,
                    COUNT(*) as total_pedidos,
                    SUM(total) as ingresos_totales
                FROM 
                    ' . $this->table . '
                WHERE 
                    negocio_id = :negocio_id AND
                    DATE(fecha_pedido) BETWEEN :desde AND :hasta
                GROUP BY
                    DATE(fecha_pedido)
                ORDER BY
                    DATE(fecha_pedido) ASC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener productos más vendidos de un negocio
    public function obtenerProductosMasVendidosNegocio($negocio_id, $limite = 10, $desde = null, $hasta = null) {
        // Configurar fechas si no se proporcionan
        if (!$desde) {
            $desde = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!$hasta) {
            $hasta = date('Y-m-d');
        }
        
        $query = 'SELECT 
                    p.id as producto_id,
                    p.nombre as nombre_producto,
                    SUM(d.cantidad) as cantidad_vendida,
                    SUM(d.subtotal) as ingresos_totales,
                    AVG(d.precio_unitario) as precio_promedio
                FROM 
                    detalles_pedido d
                JOIN
                    pedidos pe ON d.pedido_id = pe.id
                JOIN
                    productos p ON d.producto_id = p.id
                WHERE 
                    pe.negocio_id = :negocio_id AND
                    pe.estado_actual = "entregado" AND
                    DATE(pe.fecha_pedido) BETWEEN :desde AND :hasta
                GROUP BY
                    p.id
                ORDER BY
                    cantidad_vendida DESC
                LIMIT :limite';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener horarios pico de un negocio
    public function obtenerHorariosPicoNegocio($negocio_id, $desde = null, $hasta = null) {
        // Configurar fechas si no se proporcionan
        if (!$desde) {
            $desde = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!$hasta) {
            $hasta = date('Y-m-d');
        }
        
        $query = 'SELECT 
                    HOUR(fecha_pedido) as hora,
                    COUNT(*) as total_pedidos,
                    SUM(total) as ingresos_totales
                FROM 
                    ' . $this->table . '
                WHERE 
                    negocio_id = :negocio_id AND
                    DATE(fecha_pedido) BETWEEN :desde AND :hasta
                GROUP BY
                    HOUR(fecha_pedido)
                ORDER BY
                    total_pedidos DESC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener estadísticas de repartidores de un negocio
    public function obtenerEstadisticasRepartidores($negocio_id, $desde = null, $hasta = null) {
        // Configurar fechas si no se proporcionan
        if (!$desde) {
            $desde = date('Y-m-d', strtotime('-30 days'));
        }
        
        if (!$hasta) {
            $hasta = date('Y-m-d');
        }
        
        $query = 'SELECT 
                    r.id as repartidor_id,
                    CONCAT(r.nombre, " ", r.apellido) as nombre_repartidor,
                    COUNT(*) as total_pedidos,
                    AVG(TIMESTAMPDIFF(MINUTE, 
                        (SELECT MIN(h.fecha) FROM historial_estados_pedido h WHERE h.pedido_id = p.id AND h.estado = "en_camino"),
                        (SELECT MIN(h.fecha) FROM historial_estados_pedido h WHERE h.pedido_id = p.id AND h.estado = "entregado")
                    )) as tiempo_entrega_promedio,
                    COUNT(CASE WHEN p.estado_actual = "entregado" THEN 1 ELSE NULL END) as pedidos_entregados,
                    (COUNT(CASE WHEN p.estado_actual = "entregado" THEN 1 ELSE NULL END) / COUNT(*)) * 100 as tasa_exito
                FROM 
                    ' . $this->table . ' p
                JOIN
                    repartidores r ON p.repartidor_id = r.id
                WHERE 
                    p.negocio_id = :negocio_id AND
                    DATE(p.fecha_pedido) BETWEEN :desde AND :hasta
                GROUP BY
                    r.id
                ORDER BY
                    total_pedidos DESC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        $stmt->bindParam(':desde', $desde);
        $stmt->bindParam(':hasta', $hasta);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para obtener pedidos pendientes de asignación de repartidor
    public function obtenerPedidosPendientesAsignacion($negocio_id) {
        $query = 'SELECT 
                    p.id, 
                    p.fecha_pedido, 
                    p.total,
                    p.tiempo_estimado_entrega,
                    CONCAT(u.nombre, " ", u.apellido) as nombre_cliente,
                    u.telefono as telefono_cliente,
                    CONCAT(d.calle, " ", d.numero, ", ", d.colonia, ", ", d.ciudad) as direccion_entrega,
                    d.latitud,
                    d.longitud
                FROM 
                    ' . $this->table . ' p
                JOIN
                    usuarios u ON p.usuario_id = u.id
                JOIN
                    direcciones_usuario d ON p.direccion_id = d.id
                WHERE 
                    p.negocio_id = :negocio_id AND
                    p.estado_actual = "confirmado" AND
                    p.repartidor_id IS NULL
                ORDER BY
                    p.fecha_pedido ASC';
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(':negocio_id', $negocio_id);
        
        // Ejecutar query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Método para notificar a un usuario sobre su pedido (placeholder, se implementaría con sistema de notificaciones real)
    public function notificarUsuario($mensaje) {
        // Este método es un placeholder para un sistema de notificaciones real
        // Aquí se implementaría la lógica para enviar notificaciones al usuario
        // Ya sea por email, SMS, notificaciones push, etc.
        
        // Por ahora, simplemente registramos la intención de notificar
        error_log("Notificación para usuario {$this->usuario_id}, pedido {$this->id}: {$mensaje}");
        
        return true;
    }
    
    // Método para obtener sugerencias de repartidores cercanos para un pedido
    public function obtenerRepartidoresCercanos() {
        // Primero obtenemos las coordenadas de la dirección de entrega
        $query = 'SELECT latitud, longitud FROM direcciones_usuario WHERE id = :direccion_id';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':direccion_id', $this->direccion_id);
        $stmt->execute();
        $direccion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$direccion) {
            return false;
        }
        
        // Luego buscamos repartidores disponibles cercanos a esa ubicación
        $query = 'SELECT 
                    r.id,
                    r.nombre,
                    r.apellido,
                    r.telefono,
                    r.tipo_vehiculo,
                    r.calificacion_promedio,
                    (6371 * acos(cos(radians(:lat)) * cos(radians(r.ubicacion_actual_lat)) * 
                    cos(radians(r.ubicacion_actual_lng) - radians(:lng)) + 
                    sin(radians(:lat)) * sin(radians(r.ubicacion_actual_lat)))) AS distancia,
                    (SELECT COUNT(*) FROM pedidos WHERE repartidor_id = r.id AND estado_actual IN ("confirmado", "en_preparacion", "en_camino")) AS pedidos_activos
                FROM 
                    repartidores r
                WHERE 
                    r.disponible = 1 AND
                    r.estado = "activo"
                HAVING 
                    distancia < 10 AND
                    pedidos_activos < 3
                ORDER BY 
                    pedidos_activos ASC,
                    distancia ASC,
                    r.calificacion_promedio DESC
                LIMIT 5';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':lat', $direccion['latitud']);
        $stmt->bindParam(':lng', $direccion['longitud']);
        $stmt->execute();
        
        return $stmt;
    }
}
?>