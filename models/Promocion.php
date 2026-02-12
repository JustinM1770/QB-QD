<?php
/**
 * Clase Promocion
 * 
 * Gestiona las promociones y descuentos de los negocios en la aplicación de delivery
 */
class Promocion {
    // Conexión a la base de datos
    private $conn;
    
    // Propiedades del objeto
    public $id_promocion;
    public $id_negocio;
    public $nombre;
    public $descripcion;
    public $tipo_descuento; // 'percentage' o 'fixed_amount'
    public $valor_descuento;
    public $codigo;
    public $monto_pedido_minimo;
    public $monto_descuento_maximo;
    public $fecha_inicio;
    public $fecha_fin;
    public $limite_uso;
    public $contador_uso;
    public $activa;
    public $fecha_creacion;
    
    /**
     * Constructor con la conexión a la base de datos
     *
     * @param object $db - Objeto de conexión a la base de datos
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todas las promociones
     *
     * @return array - Arreglo de promociones
     */
    public function obtenerTodas() {
        $query = "SELECT * FROM promociones ORDER BY fecha_inicio DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $promociones = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $promociones[] = $this->mapearDesdeBD($row);
        }
        
        return $promociones;
    }
    
    /**
     * Obtener promociones por negocio
     *
     * @param int $id_negocio - ID del negocio
     * @return array - Arreglo de promociones del negocio
     */
    public function obtenerPorNegocio($id_negocio) {
        $query = "SELECT * FROM promociones 
                  WHERE id_negocio = :id_negocio 
                  ORDER BY fecha_inicio DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->execute();
        
        $promociones = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $promociones[] = $this->mapearDesdeBD($row);
        }
        
        return $promociones;
    }
    
    /**
     * Obtener promociones activas por negocio
     *
     * @param int $id_negocio - ID del negocio
     * @return array - Arreglo de promociones activas del negocio
     */
    public function obtenerActivasPorNegocio($id_negocio) {
        $fecha_actual = date('Y-m-d');
        
        $query = "SELECT * FROM promociones 
                  WHERE id_negocio = :id_negocio 
                  AND activa = 1 
                  AND fecha_inicio <= :fecha_actual 
                  AND fecha_fin >= :fecha_actual 
                  ORDER BY valor_descuento DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_negocio", $id_negocio);
        $stmt->bindParam(":fecha_actual", $fecha_actual);
        $stmt->execute();
        
        $promociones = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $promociones[] = $this->mapearDesdeBD($row);
        }
        
        return $promociones;
    }
    
    /**
     * Obtener una promoción por ID
     *
     * @param int $id_promocion - ID de la promoción
     * @return bool - true si se encontró la promoción, false en caso contrario
     */
    public function obtenerPorId($id_promocion) {
        $query = "SELECT * FROM promociones WHERE id_promocion = :id_promocion";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_promocion", $id_promocion);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->mapearPropiedad($row);
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener una promoción por código
     *
     * @param string $codigo - Código de la promoción
     * @return bool - true si se encontró la promoción, false en caso contrario
     */
    public function obtenerPorCodigo($codigo) {
        $query = "SELECT * FROM promociones WHERE codigo = :codigo";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":codigo", $codigo);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->mapearPropiedad($row);
            return true;
        }
        
        return false;
    }
    
    /**
     * Validar si un código de promoción es válido y aplicable
     *
     * @param string $codigo - Código de la promoción
     * @param int $id_negocio - ID del negocio
     * @param float $monto_pedido - Monto del pedido
     * @return bool - true si la promoción es válida, false en caso contrario
     */
    public function validarCodigo($codigo, $id_negocio, $monto_pedido = 0) {
        if (!$this->obtenerPorCodigo($codigo)) {
            return false;
        }
        
        $fecha_actual = date('Y-m-d');
        
        // Verificar que la promoción pertenezca al negocio
        if ($this->id_negocio != $id_negocio) {
            return false;
        }
        
        // Verificar que esté activa
        if (!$this->activa) {
            return false;
        }
        
        // Verificar fecha de validez
        if ($fecha_actual < $this->fecha_inicio || $fecha_actual > $this->fecha_fin) {
            return false;
        }
        
        // Verificar límite de uso
        if ($this->limite_uso > 0 && $this->contador_uso >= $this->limite_uso) {
            return false;
        }
        
        // Verificar monto mínimo
        if ($this->monto_pedido_minimo > 0 && $monto_pedido < $this->monto_pedido_minimo) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Calcular el descuento de una promoción
     *
     * @param float $monto_pedido - Monto total del pedido
     * @return float - Monto del descuento
     */
    public function calcularDescuento($monto_pedido) {
        $descuento = 0;
        
        if ($this->tipo_descuento == 'porcentaje') {
            $descuento = $monto_pedido * ($this->valor_descuento / 100);
            
            // Aplicar límite máximo de descuento si está definido
            if ($this->monto_descuento_maximo > 0 && $descuento > $this->monto_descuento_maximo) {
                $descuento = $this->monto_descuento_maximo;
            }
        } else { // monto_fijo
            $descuento = $this->valor_descuento;
            
            // El descuento no puede ser mayor que el monto del pedido
            if ($descuento > $monto_pedido) {
                $descuento = $monto_pedido;
            }
        }
        
        return $descuento;
    }
    
    /**
     * Incrementar el contador de uso de la promoción
     *
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function incrementarUso() {
        $query = "UPDATE promociones 
                  SET contador_uso = contador_uso + 1 
                  WHERE id_promocion = :id_promocion";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_promocion", $this->id_promocion);
        
        if ($stmt->execute()) {
            $this->contador_uso++;
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear una nueva promoción
     *
     * @return bool - true si se creó correctamente, false en caso contrario
     */
    public function crear() {
        $query = "INSERT INTO promociones 
                  (id_negocio, nombre, descripcion, tipo_descuento, valor_descuento, 
                   codigo, monto_pedido_minimo, monto_descuento_maximo, 
                   fecha_inicio, fecha_fin, limite_uso, contador_uso, activa, fecha_creacion) 
                  VALUES 
                  (:id_negocio, :nombre, :descripcion, :tipo_descuento, :valor_descuento, 
                   :codigo, :monto_pedido_minimo, :monto_descuento_maximo, 
                   :fecha_inicio, :fecha_fin, :limite_uso, 0, :activa, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id_negocio = htmlspecialchars(strip_tags($this->id_negocio));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->tipo_descuento = htmlspecialchars(strip_tags($this->tipo_descuento));
        $this->valor_descuento = floatval($this->valor_descuento);
        $this->codigo = htmlspecialchars(strip_tags($this->codigo));
        $this->monto_pedido_minimo = floatval($this->monto_pedido_minimo);
        $this->monto_descuento_maximo = floatval($this->monto_descuento_maximo);
        $this->limite_uso = intval($this->limite_uso);
        $this->activa = $this->activa ? 1 : 0;
        
        // Vincular parámetros
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":tipo_descuento", $this->tipo_descuento);
        $stmt->bindParam(":valor_descuento", $this->valor_descuento);
        $stmt->bindParam(":codigo", $this->codigo);
        $stmt->bindParam(":monto_pedido_minimo", $this->monto_pedido_minimo);
        $stmt->bindParam(":monto_descuento_maximo", $this->monto_descuento_maximo);
        $stmt->bindParam(":fecha_inicio", $this->fecha_inicio);
        $stmt->bindParam(":fecha_fin", $this->fecha_fin);
        $stmt->bindParam(":limite_uso", $this->limite_uso);
        $stmt->bindParam(":activa", $this->activa);
        
        if ($stmt->execute()) {
            $this->id_promocion = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar una promoción existente
     *
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function actualizar() {
        $query = "UPDATE promociones 
                  SET id_negocio = :id_negocio, 
                      nombre = :nombre, 
                      descripcion = :descripcion, 
                      tipo_descuento = :tipo_descuento, 
                      valor_descuento = :valor_descuento, 
                      codigo = :codigo, 
                      monto_pedido_minimo = :monto_pedido_minimo, 
                      monto_descuento_maximo = :monto_descuento_maximo, 
                      fecha_inicio = :fecha_inicio, 
                      fecha_fin = :fecha_fin, 
                      limite_uso = :limite_uso, 
                      activa = :activa 
                  WHERE id_promocion = :id_promocion";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar datos
        $this->id_negocio = htmlspecialchars(strip_tags($this->id_negocio));
        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->descripcion = htmlspecialchars(strip_tags($this->descripcion));
        $this->tipo_descuento = htmlspecialchars(strip_tags($this->tipo_descuento));
        $this->valor_descuento = floatval($this->valor_descuento);
        $this->codigo = htmlspecialchars(strip_tags($this->codigo));
        $this->monto_pedido_minimo = floatval($this->monto_pedido_minimo);
        $this->monto_descuento_maximo = floatval($this->monto_descuento_maximo);
        $this->limite_uso = intval($this->limite_uso);
        $this->activa = $this->activa ? 1 : 0;
        
        // Vincular parámetros
        $stmt->bindParam(":id_promocion", $this->id_promocion);
        $stmt->bindParam(":id_negocio", $this->id_negocio);
        $stmt->bindParam(":nombre", $this->nombre);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":tipo_descuento", $this->tipo_descuento);
        $stmt->bindParam(":valor_descuento", $this->valor_descuento);
        $stmt->bindParam(":codigo", $this->codigo);
        $stmt->bindParam(":monto_pedido_minimo", $this->monto_pedido_minimo);
        $stmt->bindParam(":monto_descuento_maximo", $this->monto_descuento_maximo);
        $stmt->bindParam(":fecha_inicio", $this->fecha_inicio);
        $stmt->bindParam(":fecha_fin", $this->fecha_fin);
        $stmt->bindParam(":limite_uso", $this->limite_uso);
        $stmt->bindParam(":activa", $this->activa);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Eliminar una promoción
     *
     * @return bool - true si se eliminó correctamente, false en caso contrario
     */
    public function eliminar() {
        $query = "DELETE FROM promociones WHERE id_promocion = :id_promocion";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_promocion", $this->id_promocion);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Mapear datos desde la base de datos a un array asociativo
     *
     * @param array $row - Fila de datos de la base de datos
     * @return array - Arreglo asociativo formateado
     */
    private function mapearDesdeBD($row) {
        return [
            'id_promocion' => $row['id_promocion'],
            'id_negocio' => $row['id_negocio'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'tipo_descuento' => $row['tipo_descuento'],
            'valor_descuento' => $row['valor_descuento'],
            'codigo' => $row['codigo'],
            'monto_pedido_minimo' => $row['monto_pedido_minimo'],
            'monto_descuento_maximo' => $row['monto_descuento_maximo'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_fin' => $row['fecha_fin'],
            'limite_uso' => $row['limite_uso'],
            'contador_uso' => $row['contador_uso'],
            'activa' => $row['activa'],
            'fecha_creacion' => $row['fecha_creacion']
        ];
    }
    
    /**
     * Mapear datos desde la base de datos a las propiedades del objeto
     *
     * @param array $row - Fila de datos de la base de datos
     */
    private function mapearPropiedad($row) {
        $this->id_promocion = $row['id_promocion'];
        $this->id_negocio = $row['id_negocio'];
        $this->nombre = $row['nombre'];
        $this->descripcion = $row['descripcion'];
        $this->tipo_descuento = $row['tipo_descuento'];
        $this->valor_descuento = $row['valor_descuento'];
        $this->codigo = $row['codigo'];
        $this->monto_pedido_minimo = $row['monto_pedido_minimo'];
        $this->monto_descuento_maximo = $row['monto_descuento_maximo'];
        $this->fecha_inicio = $row['fecha_inicio'];
        $this->fecha_fin = $row['fecha_fin'];
        $this->limite_uso = $row['limite_uso'];
        $this->contador_uso = $row['contador_uso'];
        $this->activa = $row['activa'];
        $this->fecha_creacion = $row['fecha_creacion'];
    }
    
    /**
     * Comprobar disponibilidad de un código para una nueva promoción
     * 
     * @param string $codigo - Código de promoción propuesto
     * @return bool - true si el código está disponible, false si ya existe
     */
    public function codigoDisponible($codigo) {
        $query = "SELECT COUNT(*) as total FROM promociones WHERE codigo = :codigo";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":codigo", $codigo);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] == 0;
    }
    
    /**
     * Obtener estadísticas de uso de la promoción
     * 
     * @return array - Estadísticas de uso (total_usado, pedidos_aplicados, etc)
     */
    public function obtenerEstadisticasUso() {
        // Aquí puedes implementar la lógica para obtener estadísticas de cómo se ha usado la promoción
        // Por ejemplo, cuántas veces, qué monto total de descuento ha generado, etc.
        
        $estadisticas = [
            'total_usado' => $this->contador_uso,
            'porcentaje_uso' => $this->limite_uso > 0 ? ($this->contador_uso / $this->limite_uso) * 100 : 0,
            'disponibles' => $this->limite_uso > 0 ? $this->limite_uso - $this->contador_uso : 'Ilimitado'
        ];
        
        // Puedes agregar más estadísticas según sea necesario
        
        return $estadisticas;
    }
    
    /**
     * Generar código de promoción aleatorio único
     * 
     * @param int $longitud - Longitud del código (por defecto 8 caracteres)
     * @return string - Código único generado
     */
    public function generarCodigoUnico($longitud = 8) {
        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $codigo = '';
        
        do {
            $codigo = '';
            for ($i = 0; $i < $longitud; $i++) {
                $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
            }
        } while (!$this->codigoDisponible($codigo));
        
        return $codigo;
    }
    
    /**
     * Verificar si la promoción está activa actualmente
     * 
     * @return bool - true si está activa en la fecha actual, false en caso contrario
     */
    public function estaVigente() {
        $fecha_actual = date('Y-m-d');
        
        return (
            $this->activa == 1 &&
            $fecha_actual >= $this->fecha_inicio &&
            $fecha_actual <= $this->fecha_fin &&
            ($this->limite_uso == 0 || $this->contador_uso < $this->limite_uso)
        );
    }
    
    /**
     * Activar o desactivar la promoción
     * 
     * @param bool $activar - true para activar, false para desactivar
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function cambiarEstado($activar = true) {
        $query = "UPDATE promociones SET activa = :activa WHERE id_promocion = :id_promocion";
        
        $stmt = $this->conn->prepare($query);
        
        $estado = $activar ? 1 : 0;
        $stmt->bindParam(":activa", $estado);
        $stmt->bindParam(":id_promocion", $this->id_promocion);
        
        if ($stmt->execute()) {
            $this->activa = $estado;
            return true;
        }
        
        return false;
    }
    
    /**
     * Extender fecha de vencimiento de la promoción
     * 
     * @param string $nueva_fecha - Nueva fecha de fin en formato 'Y-m-d'
     * @return bool - true si se actualizó correctamente, false en caso contrario
     */
    public function extenderVigencia($nueva_fecha) {
        if (strtotime($nueva_fecha) <= strtotime($this->fecha_fin)) {
            return false; // La nueva fecha debe ser posterior a la fecha actual de fin
        }
        
        $query = "UPDATE promociones SET fecha_fin = :fecha_fin WHERE id_promocion = :id_promocion";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":fecha_fin", $nueva_fecha);
        $stmt->bindParam(":id_promocion", $this->id_promocion);
        
        if ($stmt->execute()) {
            $this->fecha_fin = $nueva_fecha;
            return true;
        }
        
        return false;
    }
}
?>