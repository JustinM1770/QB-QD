<?php
/**
 * Modelo para gestión de cupones de descuento
 */

class Cupon {
    private $conn;
    private $table = 'cupones';

    public $id_cupon;
    public $codigo;
    public $descripcion;
    public $tipo_descuento;
    public $valor_descuento;
    public $minimo_compra;
    public $maximo_descuento;
    public $usos_maximos;
    public $usos_actuales;
    public $usos_por_usuario;
    public $fecha_inicio;
    public $fecha_expiracion;
    public $aplica_todos_negocios;
    public $solo_primera_compra;
    public $activo;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Validar un cupón para un usuario y negocio específico
     */
    public function validar($codigo, $id_usuario, $id_negocio, $subtotal) {
        // Buscar cupón por código
        $query = "SELECT * FROM {$this->table} WHERE codigo = :codigo AND activo = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();

        $cupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cupon) {
            return ['valido' => false, 'error' => 'Cupón no encontrado o inactivo'];
        }

        // Verificar fechas
        $ahora = date('Y-m-d H:i:s');
        if ($cupon['fecha_inicio'] > $ahora) {
            return ['valido' => false, 'error' => 'Este cupón aún no está activo'];
        }

        if ($cupon['fecha_expiracion'] && $cupon['fecha_expiracion'] < $ahora) {
            return ['valido' => false, 'error' => 'Este cupón ha expirado'];
        }

        // Verificar usos máximos globales
        if ($cupon['usos_maximos'] !== null && $cupon['usos_actuales'] >= $cupon['usos_maximos']) {
            return ['valido' => false, 'error' => 'Este cupón ha alcanzado su límite de usos'];
        }

        // Verificar usos por usuario
        $query_uso = "SELECT COUNT(*) as usos FROM cupones_usuarios
                      WHERE id_cupon = :id_cupon AND id_usuario = :id_usuario";
        $stmt_uso = $this->conn->prepare($query_uso);
        $stmt_uso->bindParam(':id_cupon', $cupon['id_cupon']);
        $stmt_uso->bindParam(':id_usuario', $id_usuario);
        $stmt_uso->execute();
        $uso = $stmt_uso->fetch(PDO::FETCH_ASSOC);

        if ($uso['usos'] >= $cupon['usos_por_usuario']) {
            return ['valido' => false, 'error' => 'Ya has usado este cupón el máximo permitido'];
        }

        // Verificar mínimo de compra
        if ($subtotal < $cupon['minimo_compra']) {
            return [
                'valido' => false,
                'error' => 'El mínimo de compra para este cupón es $' . number_format($cupon['minimo_compra'], 2)
            ];
        }

        // Verificar si es solo primera compra
        if ($cupon['solo_primera_compra']) {
            $query_pedidos = "SELECT COUNT(*) as total FROM pedidos
                             WHERE id_usuario = :id_usuario AND id_estado != 7";
            $stmt_pedidos = $this->conn->prepare($query_pedidos);
            $stmt_pedidos->bindParam(':id_usuario', $id_usuario);
            $stmt_pedidos->execute();
            $pedidos = $stmt_pedidos->fetch(PDO::FETCH_ASSOC);

            if ($pedidos['total'] > 0) {
                return ['valido' => false, 'error' => 'Este cupón es solo para tu primera compra'];
            }
        }

        // Verificar si aplica al negocio
        if (!$cupon['aplica_todos_negocios']) {
            $query_negocio = "SELECT COUNT(*) as existe FROM cupones_negocios
                             WHERE id_cupon = :id_cupon AND id_negocio = :id_negocio";
            $stmt_negocio = $this->conn->prepare($query_negocio);
            $stmt_negocio->bindParam(':id_cupon', $cupon['id_cupon']);
            $stmt_negocio->bindParam(':id_negocio', $id_negocio);
            $stmt_negocio->execute();
            $negocio = $stmt_negocio->fetch(PDO::FETCH_ASSOC);

            if ($negocio['existe'] == 0) {
                return ['valido' => false, 'error' => 'Este cupón no aplica para este negocio'];
            }
        }

        // Calcular descuento
        $descuento = $this->calcularDescuento($cupon, $subtotal);

        return [
            'valido' => true,
            'cupon' => $cupon,
            'descuento' => $descuento,
            'mensaje' => $this->getMensajeDescuento($cupon, $descuento)
        ];
    }

    /**
     * Calcular el descuento según el tipo de cupón
     */
    private function calcularDescuento($cupon, $subtotal) {
        if ($cupon['tipo_descuento'] === 'porcentaje') {
            $descuento = $subtotal * ($cupon['valor_descuento'] / 100);
            // Aplicar máximo si existe
            if ($cupon['maximo_descuento'] && $descuento > $cupon['maximo_descuento']) {
                $descuento = $cupon['maximo_descuento'];
            }
        } else {
            $descuento = $cupon['valor_descuento'];
        }

        // El descuento no puede ser mayor al subtotal
        return min($descuento, $subtotal);
    }

    /**
     * Generar mensaje de descuento
     */
    private function getMensajeDescuento($cupon, $descuento) {
        if ($cupon['tipo_descuento'] === 'porcentaje') {
            return "¡{$cupon['valor_descuento']}% de descuento aplicado! (-$" . number_format($descuento, 2) . ")";
        } else {
            return "¡Descuento de $" . number_format($descuento, 2) . " aplicado!";
        }
    }

    /**
     * Aplicar cupón a un pedido
     */
    public function aplicar($id_cupon, $id_usuario, $id_pedido, $descuento) {
        try {
            $this->conn->beginTransaction();

            // Registrar uso del cupón
            $query = "INSERT INTO cupones_usuarios (id_cupon, id_usuario, id_pedido, descuento_aplicado)
                      VALUES (:id_cupon, :id_usuario, :id_pedido, :descuento)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_cupon', $id_cupon);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':id_pedido', $id_pedido);
            $stmt->bindParam(':descuento', $descuento);
            $stmt->execute();

            // Incrementar contador de usos
            $query_update = "UPDATE {$this->table} SET usos_actuales = usos_actuales + 1
                            WHERE id_cupon = :id_cupon";
            $stmt_update = $this->conn->prepare($query_update);
            $stmt_update->bindParam(':id_cupon', $id_cupon);
            $stmt_update->execute();

            // Actualizar pedido con el cupón
            $query_pedido = "UPDATE pedidos SET id_cupon = :id_cupon, descuento_cupon = :descuento
                            WHERE id_pedido = :id_pedido";
            $stmt_pedido = $this->conn->prepare($query_pedido);
            $stmt_pedido->bindParam(':id_cupon', $id_cupon);
            $stmt_pedido->bindParam(':descuento', $descuento);
            $stmt_pedido->bindParam(':id_pedido', $id_pedido);
            $stmt_pedido->execute();

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crear nuevo cupón
     */
    public function crear($datos) {
        $query = "INSERT INTO {$this->table}
                  (codigo, descripcion, tipo_descuento, valor_descuento, minimo_compra,
                   maximo_descuento, usos_maximos, usos_por_usuario, fecha_inicio,
                   fecha_expiracion, aplica_todos_negocios, solo_primera_compra, activo, creado_por)
                  VALUES
                  (:codigo, :descripcion, :tipo_descuento, :valor_descuento, :minimo_compra,
                   :maximo_descuento, :usos_maximos, :usos_por_usuario, :fecha_inicio,
                   :fecha_expiracion, :aplica_todos_negocios, :solo_primera_compra, :activo, :creado_por)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':codigo', $datos['codigo']);
        $stmt->bindParam(':descripcion', $datos['descripcion']);
        $stmt->bindParam(':tipo_descuento', $datos['tipo_descuento']);
        $stmt->bindParam(':valor_descuento', $datos['valor_descuento']);
        $stmt->bindParam(':minimo_compra', $datos['minimo_compra']);
        $stmt->bindParam(':maximo_descuento', $datos['maximo_descuento']);
        $stmt->bindParam(':usos_maximos', $datos['usos_maximos']);
        $stmt->bindParam(':usos_por_usuario', $datos['usos_por_usuario']);
        $stmt->bindParam(':fecha_inicio', $datos['fecha_inicio']);
        $stmt->bindParam(':fecha_expiracion', $datos['fecha_expiracion']);
        $stmt->bindParam(':aplica_todos_negocios', $datos['aplica_todos_negocios']);
        $stmt->bindParam(':solo_primera_compra', $datos['solo_primera_compra']);
        $stmt->bindParam(':activo', $datos['activo']);
        $stmt->bindParam(':creado_por', $datos['creado_por']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Obtener todos los cupones
     */
    public function obtenerTodos($soloActivos = false) {
        $query = "SELECT c.*,
                         (SELECT COUNT(*) FROM cupones_usuarios WHERE id_cupon = c.id_cupon) as total_usos
                  FROM {$this->table} c";

        if ($soloActivos) {
            $query .= " WHERE c.activo = 1 AND (c.fecha_expiracion IS NULL OR c.fecha_expiracion > NOW())";
        }

        $query .= " ORDER BY c.fecha_creacion DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener cupón por ID
     */
    public function obtenerPorId($id) {
        $query = "SELECT * FROM {$this->table} WHERE id_cupon = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar cupón
     */
    public function actualizar($id, $datos) {
        $query = "UPDATE {$this->table} SET
                  codigo = :codigo,
                  descripcion = :descripcion,
                  tipo_descuento = :tipo_descuento,
                  valor_descuento = :valor_descuento,
                  minimo_compra = :minimo_compra,
                  maximo_descuento = :maximo_descuento,
                  usos_maximos = :usos_maximos,
                  usos_por_usuario = :usos_por_usuario,
                  fecha_inicio = :fecha_inicio,
                  fecha_expiracion = :fecha_expiracion,
                  aplica_todos_negocios = :aplica_todos_negocios,
                  solo_primera_compra = :solo_primera_compra,
                  activo = :activo
                  WHERE id_cupon = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':codigo', $datos['codigo']);
        $stmt->bindParam(':descripcion', $datos['descripcion']);
        $stmt->bindParam(':tipo_descuento', $datos['tipo_descuento']);
        $stmt->bindParam(':valor_descuento', $datos['valor_descuento']);
        $stmt->bindParam(':minimo_compra', $datos['minimo_compra']);
        $stmt->bindParam(':maximo_descuento', $datos['maximo_descuento']);
        $stmt->bindParam(':usos_maximos', $datos['usos_maximos']);
        $stmt->bindParam(':usos_por_usuario', $datos['usos_por_usuario']);
        $stmt->bindParam(':fecha_inicio', $datos['fecha_inicio']);
        $stmt->bindParam(':fecha_expiracion', $datos['fecha_expiracion']);
        $stmt->bindParam(':aplica_todos_negocios', $datos['aplica_todos_negocios']);
        $stmt->bindParam(':solo_primera_compra', $datos['solo_primera_compra']);
        $stmt->bindParam(':activo', $datos['activo']);

        return $stmt->execute();
    }

    /**
     * Eliminar cupón
     */
    public function eliminar($id) {
        $query = "DELETE FROM {$this->table} WHERE id_cupon = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    /**
     * Cambiar estado de cupón
     */
    public function cambiarEstado($id, $activo) {
        $query = "UPDATE {$this->table} SET activo = :activo WHERE id_cupon = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':activo', $activo);
        return $stmt->execute();
    }

    /**
     * Agregar negocios específicos a un cupón
     */
    public function agregarNegocios($id_cupon, $negocios) {
        // Eliminar relaciones existentes
        $query_delete = "DELETE FROM cupones_negocios WHERE id_cupon = :id_cupon";
        $stmt_delete = $this->conn->prepare($query_delete);
        $stmt_delete->bindParam(':id_cupon', $id_cupon);
        $stmt_delete->execute();

        // Agregar nuevas relaciones
        if (!empty($negocios)) {
            $query = "INSERT INTO cupones_negocios (id_cupon, id_negocio) VALUES (:id_cupon, :id_negocio)";
            $stmt = $this->conn->prepare($query);

            foreach ($negocios as $id_negocio) {
                $stmt->bindParam(':id_cupon', $id_cupon);
                $stmt->bindParam(':id_negocio', $id_negocio);
                $stmt->execute();
            }
        }

        return true;
    }

    /**
     * Obtener estadísticas de un cupón
     */
    public function obtenerEstadisticas($id_cupon) {
        $query = "SELECT
                    COUNT(*) as total_usos,
                    SUM(descuento_aplicado) as total_descuento,
                    AVG(descuento_aplicado) as promedio_descuento
                  FROM cupones_usuarios
                  WHERE id_cupon = :id_cupon";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_cupon', $id_cupon);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
