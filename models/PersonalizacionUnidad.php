<?php
/**
 * Modelo para manejar la personalización por unidad de productos
 * 
 * Permite que cada unidad de un producto (ej: cada taco de una orden de 3)
 * tenga su propia configuración de elegible (tipo) y opciones (modificadores)
 * 
 * Ejemplo de uso:
 * - 3 Tacos: Taco 1 (Pastor, sin cebolla), Taco 2 (Asada, extra queso), Taco 3 (Suadero)
 */

class PersonalizacionUnidad {
    private $conn;
    private $table = 'personalizacion_unidad';
    private $table_opciones = 'opciones_unidad';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener elegibles disponibles para un producto
     */
    public function obtenerElegibles($id_producto) {
        $query = "SELECT id_elegible, nombre, precio_adicional, disponible, orden
                  FROM elegibles_producto 
                  WHERE id_producto = :id_producto AND disponible = 1
                  ORDER BY orden, nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener opciones/modificadores disponibles para un producto
     */
    public function obtenerOpciones($id_producto) {
        $query = "SELECT g.id_grupo_opcion, g.nombre as grupo_nombre, g.descripcion as grupo_descripcion,
                         g.obligatorio, g.tipo_seleccion, g.min_selecciones, g.max_selecciones,
                         o.id_opcion, o.nombre as opcion_nombre, o.precio_adicional, o.disponible
                  FROM grupos_opciones g
                  JOIN opciones o ON g.id_grupo_opcion = o.id_grupo_opcion
                  WHERE g.id_producto = :id_producto AND g.activo = 1 AND o.disponible = 1
                  ORDER BY g.orden_visualizacion, o.orden_visualizacion";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agrupar por grupo
        $grupos = [];
        foreach ($rows as $row) {
            $id_grupo = $row['id_grupo_opcion'];
            
            if (!isset($grupos[$id_grupo])) {
                $grupos[$id_grupo] = [
                    'id_grupo_opcion' => $id_grupo,
                    'nombre' => $row['grupo_nombre'],
                    'descripcion' => $row['grupo_descripcion'],
                    'obligatorio' => (bool)$row['obligatorio'],
                    'tipo_seleccion' => $row['tipo_seleccion'],
                    'min_selecciones' => (int)$row['min_selecciones'],
                    'max_selecciones' => (int)$row['max_selecciones'],
                    'opciones' => []
                ];
            }
            
            $grupos[$id_grupo]['opciones'][] = [
                'id_opcion' => (int)$row['id_opcion'],
                'nombre' => $row['opcion_nombre'],
                'precio_adicional' => (float)$row['precio_adicional']
            ];
        }
        
        return array_values($grupos);
    }
    
    /**
     * Verificar si un producto permite personalización por unidad
     */
    public function permitePersonalizacionUnidad($id_producto) {
        $query = "SELECT permite_personalizacion_unidad, tiene_elegibles, tiene_opciones_dinamicas
                  FROM productos 
                  WHERE id_producto = :id_producto";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            return false;
        }
        
        return [
            'permite_personalizacion_unidad' => (bool)$producto['permite_personalizacion_unidad'],
            'tiene_elegibles' => (bool)$producto['tiene_elegibles'],
            'tiene_opciones_dinamicas' => (bool)$producto['tiene_opciones_dinamicas']
        ];
    }
    
    /**
     * Guardar personalización de unidades para un detalle de pedido
     *
     * @param int $id_detalle_pedido
     * @param array $unidades Array de unidades con formato:
     *   [
     *     ['numero_unidad' => 1, 'id_elegible' => 21, 'opciones' => [1, 4], 'notas' => 'bien dorado',
     *      'mensaje_tarjeta' => 'Felicidades!', 'texto_producto' => 'Feliz Cumple'],
     *     ...
     *   ]
     */
    public function guardarPersonalizacion($id_detalle_pedido, $unidades) {
        try {
            $this->conn->beginTransaction();

            // Eliminar personalizaciones anteriores
            $this->eliminarPersonalizacion($id_detalle_pedido);

            $precio_adicional_total = 0;

            foreach ($unidades as $unidad) {
                $numero = (int)($unidad['numero_unidad'] ?? 1);
                $id_elegible = !empty($unidad['id_elegible']) ? (int)$unidad['id_elegible'] : null;
                $notas = trim($unidad['notas'] ?? '');
                $opciones = $unidad['opciones'] ?? [];
                $mensaje_tarjeta = trim($unidad['mensaje_tarjeta'] ?? '');
                $texto_producto = trim($unidad['texto_producto'] ?? '');

                // Insertar personalización de unidad
                $query = "INSERT INTO personalizacion_unidad
                          (id_detalle_pedido, numero_unidad, id_elegible, notas_unidad, mensaje_tarjeta, texto_producto)
                          VALUES (:id_detalle_pedido, :numero_unidad, :id_elegible, :notas_unidad, :mensaje_tarjeta, :texto_producto)";

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id_detalle_pedido', $id_detalle_pedido, PDO::PARAM_INT);
                $stmt->bindParam(':numero_unidad', $numero, PDO::PARAM_INT);
                $stmt->bindParam(':id_elegible', $id_elegible, PDO::PARAM_INT);
                $stmt->bindParam(':notas_unidad', $notas, PDO::PARAM_STR);
                $stmt->bindParam(':mensaje_tarjeta', $mensaje_tarjeta, PDO::PARAM_STR);
                $stmt->bindParam(':texto_producto', $texto_producto, PDO::PARAM_STR);
                $stmt->execute();
                
                $id_personalizacion = $this->conn->lastInsertId();
                
                // Obtener precio del elegible si aplica
                if ($id_elegible) {
                    $precio_elegible = $this->obtenerPrecioElegible($id_elegible);
                    $precio_adicional_total += $precio_elegible;
                }
                
                // Insertar opciones de la unidad
                if (!empty($opciones)) {
                    foreach ($opciones as $id_opcion) {
                        $precio_opcion = $this->obtenerPrecioOpcion($id_opcion);
                        $precio_adicional_total += $precio_opcion;
                        
                        $query_opcion = "INSERT INTO opciones_unidad 
                                        (id_personalizacion, id_opcion, precio_adicional)
                                        VALUES (:id_personalizacion, :id_opcion, :precio_adicional)";
                        
                        $stmt_opcion = $this->conn->prepare($query_opcion);
                        $stmt_opcion->bindParam(':id_personalizacion', $id_personalizacion, PDO::PARAM_INT);
                        $stmt_opcion->bindParam(':id_opcion', $id_opcion, PDO::PARAM_INT);
                        $stmt_opcion->bindParam(':precio_adicional', $precio_opcion);
                        $stmt_opcion->execute();
                    }
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'precio_adicional_total' => $precio_adicional_total
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error guardando personalización: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener personalización guardada de un detalle de pedido
     */
    public function obtenerPersonalizacion($id_detalle_pedido) {
        $query = "SELECT pu.id_personalizacion, pu.numero_unidad, pu.id_elegible, pu.notas_unidad,
                         pu.mensaje_tarjeta, pu.texto_producto,
                         e.nombre as elegible_nombre, e.precio_adicional as elegible_precio
                  FROM personalizacion_unidad pu
                  LEFT JOIN elegibles_producto e ON pu.id_elegible = e.id_elegible
                  WHERE pu.id_detalle_pedido = :id_detalle_pedido
                  ORDER BY pu.numero_unidad";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_detalle_pedido', $id_detalle_pedido, PDO::PARAM_INT);
        $stmt->execute();

        $unidades = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $unidad = [
                'id_personalizacion' => (int)$row['id_personalizacion'],
                'numero_unidad' => (int)$row['numero_unidad'],
                'elegible' => null,
                'notas' => $row['notas_unidad'],
                'mensaje_tarjeta' => $row['mensaje_tarjeta'] ?? '',
                'texto_producto' => $row['texto_producto'] ?? '',
                'opciones' => []
            ];

            if ($row['id_elegible']) {
                $unidad['elegible'] = [
                    'id' => (int)$row['id_elegible'],
                    'nombre' => $row['elegible_nombre'],
                    'precio_adicional' => (float)$row['elegible_precio']
                ];
            }

            // Obtener opciones de esta unidad
            $unidad['opciones'] = $this->obtenerOpcionesUnidad($row['id_personalizacion']);

            $unidades[] = $unidad;
        }

        return $unidades;
    }
    
    /**
     * Obtener opciones de una unidad específica
     */
    private function obtenerOpcionesUnidad($id_personalizacion) {
        $query = "SELECT ou.id_opcion, o.nombre, ou.precio_adicional
                  FROM opciones_unidad ou
                  JOIN opciones o ON ou.id_opcion = o.id_opcion
                  WHERE ou.id_personalizacion = :id_personalizacion";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_personalizacion', $id_personalizacion, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Eliminar personalización de un detalle de pedido
     */
    public function eliminarPersonalizacion($id_detalle_pedido) {
        // Las opciones_unidad se eliminan automáticamente por CASCADE
        $query = "DELETE FROM personalizacion_unidad WHERE id_detalle_pedido = :id_detalle_pedido";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_detalle_pedido', $id_detalle_pedido, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Obtener precio adicional de un elegible
     */
    private function obtenerPrecioElegible($id_elegible) {
        $query = "SELECT precio_adicional FROM elegibles_producto WHERE id_elegible = :id_elegible";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_elegible', $id_elegible, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['precio_adicional'] : 0;
    }
    
    /**
     * Obtener precio adicional de una opción
     */
    private function obtenerPrecioOpcion($id_opcion) {
        $query = "SELECT precio_adicional FROM opciones WHERE id_opcion = :id_opcion";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_opcion', $id_opcion, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['precio_adicional'] : 0;
    }
    
    /**
     * Calcular precio total con personalizaciones
     */
    public function calcularPrecioTotal($id_producto, $cantidad, $unidades) {
        // Obtener precio base del producto
        $query = "SELECT precio FROM productos WHERE id_producto = :id_producto";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            return 0;
        }
        
        $precio_base = (float)$producto['precio'];
        $precio_adicional = 0;
        
        // Calcular precio adicional de cada unidad
        foreach ($unidades as $unidad) {
            if (!empty($unidad['id_elegible'])) {
                $precio_adicional += $this->obtenerPrecioElegible($unidad['id_elegible']);
            }
            
            if (!empty($unidad['opciones'])) {
                foreach ($unidad['opciones'] as $id_opcion) {
                    $precio_adicional += $this->obtenerPrecioOpcion($id_opcion);
                }
            }
        }
        
        return $precio_base + $precio_adicional;
    }
    
    /**
     * Formatear personalización para mostrar en ticket/resumen
     */
    public function formatearParaTicket($id_detalle_pedido) {
        $personalizaciones = $this->obtenerPersonalizacion($id_detalle_pedido);

        if (empty($personalizaciones)) {
            return '';
        }

        $lineas = [];

        foreach ($personalizaciones as $unidad) {
            $descripcion = "  #{$unidad['numero_unidad']}: ";

            if ($unidad['elegible']) {
                $descripcion .= $unidad['elegible']['nombre'];
                if ($unidad['elegible']['precio_adicional'] > 0) {
                    $descripcion .= " (+$" . number_format($unidad['elegible']['precio_adicional'], 2) . ")";
                }
            }

            if (!empty($unidad['opciones'])) {
                $mods = [];
                foreach ($unidad['opciones'] as $opcion) {
                    $mod = $opcion['nombre'];
                    if ($opcion['precio_adicional'] > 0) {
                        $mod .= " (+$" . number_format($opcion['precio_adicional'], 2) . ")";
                    }
                    $mods[] = $mod;
                }
                if (!empty($mods)) {
                    $descripcion .= " - " . implode(', ', $mods);
                }
            }

            if (!empty($unidad['notas'])) {
                $descripcion .= " [" . $unidad['notas'] . "]";
            }

            $lineas[] = $descripcion;

            // Mensajes personalizados para regalo
            if (!empty($unidad['texto_producto'])) {
                $lineas[] = "     ✍️ Escribir: \"" . $unidad['texto_producto'] . "\"";
            }

            if (!empty($unidad['mensaje_tarjeta'])) {
                $lineas[] = "     💌 Tarjeta: \"" . $unidad['mensaje_tarjeta'] . "\"";
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * Obtener configuración de personalización de un producto
     */
    public function obtenerConfigProducto($id_producto) {
        $query = "SELECT permite_personalizacion_unidad, tiene_elegibles, tiene_opciones_dinamicas,
                         permite_mensaje_tarjeta, permite_texto_producto, limite_texto_producto
                  FROM productos
                  WHERE id_producto = :id_producto";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();

        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            return null;
        }

        return [
            'permite_personalizacion_unidad' => (bool)($producto['permite_personalizacion_unidad'] ?? false),
            'tiene_elegibles' => (bool)($producto['tiene_elegibles'] ?? false),
            'tiene_opciones_dinamicas' => (bool)($producto['tiene_opciones_dinamicas'] ?? false),
            'permite_mensaje_tarjeta' => (bool)($producto['permite_mensaje_tarjeta'] ?? false),
            'permite_texto_producto' => (bool)($producto['permite_texto_producto'] ?? false),
            'limite_texto_producto' => (int)($producto['limite_texto_producto'] ?? 50)
        ];
    }
}
