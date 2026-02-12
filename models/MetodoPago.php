<?php
class MetodoPago {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "metodos_pago";
    
    // Propiedades del objeto
    public $id_metodo_pago;
    public $id_usuario;
    public $tipo_pago; // 'tarjeta_credito', 'tarjeta_debito', 'paypal', 'efectivo', etc.
    public $proveedor; // Visa, Mastercard, etc.
    public $titular;
    public $numero_cuenta; // Últimos 4 dígitos para tarjetas
    public $fecha_expiracion;
    public $es_predeterminado;
    public $fecha_creacion;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Obtener todos los métodos de pago de un usuario
    public function obtenerPorUsuario($id_usuario = null) {
        // Si no se proporciona un ID, usar el de la instancia
        if ($id_usuario === null) {
            $id_usuario = $this->id_usuario;
        }
        
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id_usuario = ? 
                  ORDER BY es_predeterminado DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener un método de pago por ID
    public function obtenerPorId() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_metodo_pago = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_metodo_pago);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id_usuario = $row['id_usuario'];
            $this->tipo_pago = $row['tipo_pago'];
            $this->proveedor = $row['proveedor'];
            $this->titular = $row['titular'];
            $this->numero_cuenta = $row['numero_cuenta'];
            $this->fecha_expiracion = $row['fecha_expiracion'];
            $this->es_predeterminado = $row['es_predeterminado'];
            $this->fecha_creacion = $row['fecha_creacion'];
            
            return true;
        }
        
        return false;
    }
    
    // Crear un nuevo método de pago
    public function crear() {
        // Si es predeterminado, actualizar todos los demás a no predeterminados
        if ($this->es_predeterminado == 1) {
            $this->quitarPredeterminados();
        }
        
        $query = "INSERT INTO " . $this->table_name . "
                  (id_usuario, tipo_pago, proveedor, titular, numero_cuenta, fecha_expiracion, es_predeterminado)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y sanitizar datos
        $this->id_usuario = htmlspecialchars(strip_tags($this->id_usuario));
        $this->tipo_pago = htmlspecialchars(strip_tags($this->tipo_pago));
        $this->proveedor = htmlspecialchars(strip_tags($this->proveedor));
        $this->titular = htmlspecialchars(strip_tags($this->titular));
        $this->numero_cuenta = htmlspecialchars(strip_tags($this->numero_cuenta));
        $this->fecha_expiracion = htmlspecialchars(strip_tags($this->fecha_expiracion));
        $this->es_predeterminado = (int)$this->es_predeterminado;
        
        // Vincular parámetros
        $stmt->bindParam(1, $this->id_usuario);
        $stmt->bindParam(2, $this->tipo_pago);
        $stmt->bindParam(3, $this->proveedor);
        $stmt->bindParam(4, $this->titular);
        $stmt->bindParam(5, $this->numero_cuenta);
        $stmt->bindParam(6, $this->fecha_expiracion);
        $stmt->bindParam(7, $this->es_predeterminado);
        
        // Ejecutar consulta
        if ($stmt->execute()) {
            $this->id_metodo_pago = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Actualizar un método de pago existente
    public function actualizar() {
        // Si se establece como predeterminado, actualizar los otros métodos
        if ($this->es_predeterminado == 1) {
            $this->quitarPredeterminados();
        }
        
        $query = "UPDATE " . $this->table_name . "
                  SET tipo_pago = ?, proveedor = ?, titular = ?, numero_cuenta = ?, 
                      fecha_expiracion = ?, es_predeterminado = ?
                  WHERE id_metodo_pago = ? AND id_usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y sanitizar datos
        $this->tipo_pago = htmlspecialchars(strip_tags($this->tipo_pago));
        $this->proveedor = htmlspecialchars(strip_tags($this->proveedor));
        $this->titular = htmlspecialchars(strip_tags($this->titular));
        $this->numero_cuenta = htmlspecialchars(strip_tags($this->numero_cuenta));
        $this->fecha_expiracion = htmlspecialchars(strip_tags($this->fecha_expiracion));
        $this->es_predeterminado = (int)$this->es_predeterminado;
        
        // Vincular parámetros
        $stmt->bindParam(1, $this->tipo_pago);
        $stmt->bindParam(2, $this->proveedor);
        $stmt->bindParam(3, $this->titular);
        $stmt->bindParam(4, $this->numero_cuenta);
        $stmt->bindParam(5, $this->fecha_expiracion);
        $stmt->bindParam(6, $this->es_predeterminado);
        $stmt->bindParam(7, $this->id_metodo_pago);
        $stmt->bindParam(8, $this->id_usuario);
        
        // Ejecutar consulta
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Eliminar un método de pago
    public function eliminar() {
        // Verificar si es predeterminado para establecer otro como predeterminado
        $this->obtenerPorId();
        
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id_metodo_pago = ? AND id_usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(1, $this->id_metodo_pago);
        $stmt->bindParam(2, $this->id_usuario);
        
        // Ejecutar consulta
        if ($stmt->execute()) {
            // Si era predeterminado, establecer otro como predeterminado
            if ($this->es_predeterminado == 1) {
                $this->establecerNuevoPredeterminado();
            }
            return true;
        }
        
        return false;
    }
    
    // Obtener el método de pago predeterminado de un usuario
    public function obtenerPredeterminado($id_usuario = null) {
        // Si no se proporciona un ID, usar el de la instancia
        if ($id_usuario === null) {
            $id_usuario = $this->id_usuario;
        }
        
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id_usuario = ? AND es_predeterminado = 1 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id_metodo_pago = $row['id_metodo_pago'] ?? null;
            $this->id_usuario = $row['id_usuario'] ?? null;
            $this->tipo_pago = $row['tipo_pago'] ?? null;
            $this->proveedor = $row['proveedor'] ?? null;
            $this->titular = isset($row['titular']) ? $row['titular'] : '';
            $this->numero_cuenta = $row['numero_cuenta'] ?? null;
            $this->fecha_expiracion = isset($row['fecha_expiracion']) ? $row['fecha_expiracion'] : '';
            $this->es_predeterminado = $row['es_predeterminado'] ?? 0;
            $this->fecha_creacion = isset($row['fecha_creacion']) ? $row['fecha_creacion'] : '';
            return true;
        }
        return false;
    }
    
    // Establecer un método como predeterminado
    public function establecerPredeterminado() {
        // Primero quitar todos los predeterminados
        $this->quitarPredeterminados();
        
        // Luego establecer este como predeterminado
        $query = "UPDATE " . $this->table_name . "
                  SET es_predeterminado = 1
                  WHERE id_metodo_pago = ? AND id_usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(1, $this->id_metodo_pago);
        $stmt->bindParam(2, $this->id_usuario);
        
        // Ejecutar consulta
        return $stmt->execute();
    }
    
    // Método privado para quitar todos los predeterminados de un usuario
    private function quitarPredeterminados() {
        $query = "UPDATE " . $this->table_name . "
                  SET es_predeterminado = 0
                  WHERE id_usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_usuario);
        
        return $stmt->execute();
    }
    
    // Método privado para establecer un nuevo predeterminado después de eliminar uno
    private function establecerNuevoPredeterminado() {
        $query = "UPDATE " . $this->table_name . "
                  SET es_predeterminado = 1
                  WHERE id_usuario = ?
                  ORDER BY fecha_creacion DESC
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_usuario);
        
        return $stmt->execute();
    }
    
    // Verificar si un usuario tiene al menos un método de pago
    public function tieneMetodoPago($id_usuario = null) {
        // Si no se proporciona un ID, usar el de la instancia
        if ($id_usuario === null) {
            $id_usuario = $this->id_usuario;
        }
        
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                  WHERE id_usuario = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_usuario);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($row['total'] > 0);
    }
    
    // Procesar un pago 
    public function procesarPago($monto, $id_pedido, $tipo_pago = null) {
        // Si no se proporciona un tipo de pago, usar el de la instancia
        if ($tipo_pago === null) {
            $tipo_pago = $this->tipo_pago;
        }
        
        // Dependiendo del tipo de pago, procesar de forma diferente
        switch ($tipo_pago) {
            case 'tarjeta_credito':
            case 'tarjeta_debito':
                return $this->procesarPagoTarjeta($monto, $id_pedido);
                
            case 'paypal':
                return $this->procesarPagoPaypal($monto, $id_pedido);
                
            case 'efectivo':
                return $this->procesarPagoEfectivo($monto, $id_pedido);
                
            default:
                return false;
        }
    }
    
    // Procesar pago con tarjeta
    private function procesarPagoTarjeta($monto, $id_pedido) {
        // Aquí iría la integración con una pasarela de pago real
        // Por ahora, simulamos un pago exitoso
        
        // Calcular comisión (15%)
        $comision = $monto * 0.15;
        $monto_negocio = $monto - $comision;
        
        // Registrar el pago en la base de datos
        $this->registrarTransaccion($id_pedido, $monto, $comision, $monto_negocio, 'completado');
        
        return true;
    }
    
    // Procesar pago con PayPal
    private function procesarPagoPaypal($monto, $id_pedido) {
        // Aquí iría la integración con PayPal
        // Por ahora, simulamos un pago exitoso
        
        // Calcular comisión (15%)
        $comision = $monto * 0.15;
        $monto_negocio = $monto - $comision;
        
        // Registrar el pago en la base de datos
        $this->registrarTransaccion($id_pedido, $monto, $comision, $monto_negocio, 'completado');
        
        return true;
    }
    
    // Procesar pago en efectivo
    private function procesarPagoEfectivo($monto, $id_pedido) {
        // Para pagos en efectivo, simplemente registramos la transacción como pendiente
        // El repartidor cobrará al cliente y posteriormente se ajustará el estado
        
        // Calcular comisión (15%)
        $comision = $monto * 0.15;
        $monto_negocio = $monto - $comision;
        
        // Registrar el pago en la base de datos
        $this->registrarTransaccion($id_pedido, $monto, $comision, $monto_negocio, 'pendiente');
        
        return true;
    }
    
    // Registrar una transacción en la base de datos
    private function registrarTransaccion($id_pedido, $monto_total, $comision, $monto_negocio, $estado) {
        // Verificar si existe la tabla de transacciones
        $tabla_existe = false;
        try {
            $check_query = "SELECT 1 FROM transacciones_pago LIMIT 1";
            $stmt = $this->conn->prepare($check_query);
            $stmt->execute();
            $tabla_existe = true;
        } catch (PDOException $e) {
            // La tabla no existe, la crearemos
            $tabla_existe = false;
        }
        
        // Si la tabla no existe, crearla
        if (!$tabla_existe) {
            try {
                $create_table = "CREATE TABLE transacciones_pago (
                    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
                    id_pedido INT NOT NULL,
                    id_metodo_pago INT NOT NULL,
                    monto_total DECIMAL(10,2) NOT NULL,
                    comision DECIMAL(10,2) NOT NULL,
                    monto_negocio DECIMAL(10,2) NOT NULL,
                    estado VARCHAR(50) NOT NULL,
                    fecha_transaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
                    FOREIGN KEY (id_metodo_pago) REFERENCES metodos_pago(id_metodo_pago)
                )";
                $this->conn->exec($create_table);
            } catch (PDOException $e) {
                // Si hay error al crear la tabla, continuamos sin registrar
                return false;
            }
        }
        
        // Intentar registrar la transacción
        try {
            $query = "INSERT INTO transacciones_pago 
                      (id_pedido, id_metodo_pago, monto_total, comision, monto_negocio, estado) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $id_pedido);
            $stmt->bindParam(2, $this->id_metodo_pago);
            $stmt->bindParam(3, $monto_total);
            $stmt->bindParam(4, $comision);
            $stmt->bindParam(5, $monto_negocio);
            $stmt->bindParam(6, $estado);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Si hay error al registrar, retornamos false
            return false;
        }
    }
    
    // Confirmar pago en efectivo (llamado cuando el repartidor confirma recepción)
    public function confirmarPagoEfectivo($id_pedido) {
        $query = "UPDATE transacciones_pago 
                  SET estado = 'completado' 
                  WHERE id_pedido = ? AND estado = 'pendiente'";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_pedido);
        
        return $stmt->execute();
    }
    
    // Obtener transacciones para un negocio
    public function obtenerTransaccionesPorNegocio($id_negocio, $estado = null) {
        $query = "SELECT tp.*, p.id_negocio, n.nombre as nombre_negocio 
                 FROM transacciones_pago tp
                 JOIN pedidos p ON tp.id_pedido = p.id_pedido
                 JOIN negocios n ON p.id_negocio = n.id_negocio
                 WHERE p.id_negocio = ?";
                 
        if ($estado !== null) {
            $query .= " AND tp.estado = ?";
        }
        
        $query .= " ORDER BY tp.fecha_transaccion DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        
        if ($estado !== null) {
            $stmt->bindParam(2, $estado);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener el saldo a pagar a un negocio
    public function obtenerSaldoNegocio($id_negocio) {
        $query = "SELECT SUM(monto_negocio) as saldo_pendiente
                 FROM transacciones_pago tp
                 JOIN pedidos p ON tp.id_pedido = p.id_pedido
                 WHERE p.id_negocio = ? AND tp.estado = 'completado'";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['saldo_pendiente'] ?? 0;
    }
    
    // Registrar pago a un negocio
    public function registrarPagoNegocio($id_negocio, $monto, $metodo_pago, $referencia) {
        // Verificar si existe la tabla de pagos a negocios
        $tabla_existe = false;
        try {
            $check_query = "SELECT 1 FROM pagos_negocio LIMIT 1";
            $stmt = $this->conn->prepare($check_query);
            $stmt->execute();
            $tabla_existe = true;
        } catch (PDOException $e) {
            // La tabla no existe, la crearemos
            $tabla_existe = false;
        }
        
        // Si la tabla no existe, crearla
        if (!$tabla_existe) {
            try {
                $create_table = "CREATE TABLE pagos_negocio (
                    id_pago INT AUTO_INCREMENT PRIMARY KEY,
                    id_negocio INT NOT NULL,
                    monto DECIMAL(10,2) NOT NULL,
                    metodo_pago VARCHAR(50) NOT NULL,
                    referencia VARCHAR(100),
                    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio)
                )";
                $this->conn->exec($create_table);
            } catch (PDOException $e) {
                // Si hay error al crear la tabla, continuamos sin registrar
                return false;
            }
        }
        
        // Registrar el pago
        $query = "INSERT INTO pagos_negocio (id_negocio, monto, metodo_pago, referencia)
                 VALUES (?, ?, ?, ?)";
                 
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_negocio);
        $stmt->bindParam(2, $monto);
        $stmt->bindParam(3, $metodo_pago);
        $stmt->bindParam(4, $referencia);
        
        return $stmt->execute();
    }
}
?>