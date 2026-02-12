<?php
/**
 * QuickBite - Constantes de Estados de Pedido
 *
 * Este archivo centraliza los estados de pedido para evitar inconsistencias.
 * SIEMPRE usar estas constantes en lugar de valores hardcodeados.
 *
 * TABLA DE REFERENCIA:
 * +----+----------------+-------------+---------------------+
 * | ID | Nombre         | Código      | Descripción         |
 * +----+----------------+-------------+---------------------+
 * | 1  | Pendiente      | pending     | Esperando confirm.  |
 * | 2  | Confirmado     | confirmed   | Negocio aceptó      |
 * | 3  | En Preparación | preparing   | Cocinando           |
 * | 4  | Listo          | ready       | Esperando repartidor|
 * | 5  | En Camino      | on_the_way  | Repartidor en ruta  |
 * | 6  | Entregado      | delivered   | Completado          |
 * | 7  | Cancelado      | cancelled   | Cancelado           |
 * +----+----------------+-------------+---------------------+
 *
 * USO RECOMENDADO:
 *   if ($pedido->id_estado === EstadoPedido::ENTREGADO) { ... }
 *   $query = "WHERE id_estado = " . EstadoPedido::PENDIENTE;
 */

// ========================================
// CONSTANTES DE ESTADOS (usar estos IDs)
// ========================================

class EstadoPedido {
    // IDs numéricos (usar en queries de BD)
    public const PENDIENTE = 1;
    public const CONFIRMADO = 2;
    public const EN_PREPARACION = 3;
    public const LISTO = 4;
    public const EN_CAMINO = 5;
    public const ENTREGADO = 6;
    public const CANCELADO = 7;

    // Nombres legibles para mostrar al usuario
    public const NOMBRES = [
        self::PENDIENTE => 'Pendiente',
        self::CONFIRMADO => 'Confirmado',
        self::EN_PREPARACION => 'En Preparación',
        self::LISTO => 'Listo para Recoger',
        self::EN_CAMINO => 'En Camino',
        self::ENTREGADO => 'Entregado',
        self::CANCELADO => 'Cancelado'
    ];

    // Códigos internos (para APIs, logs)
    public const CODIGOS = [
        self::PENDIENTE => 'pending',
        self::CONFIRMADO => 'confirmed',
        self::EN_PREPARACION => 'preparing',
        self::LISTO => 'ready',
        self::EN_CAMINO => 'on_the_way',
        self::ENTREGADO => 'delivered',
        self::CANCELADO => 'cancelled'
    ];

    // Colores para UI (Bootstrap)
    public const COLORES = [
        self::PENDIENTE => 'warning',
        self::CONFIRMADO => 'info',
        self::EN_PREPARACION => 'primary',
        self::LISTO => 'success',
        self::EN_CAMINO => 'info',
        self::ENTREGADO => 'success',
        self::CANCELADO => 'danger'
    ];

    // Iconos Font Awesome
    public const ICONOS = [
        self::PENDIENTE => 'fa-clock',
        self::CONFIRMADO => 'fa-check-circle',
        self::EN_PREPARACION => 'fa-utensils',
        self::LISTO => 'fa-box',
        self::EN_CAMINO => 'fa-motorcycle',
        self::ENTREGADO => 'fa-check-double',
        self::CANCELADO => 'fa-times-circle'
    ];

    // Estados que indican pedido activo (no finalizado)
    public const ACTIVOS = [
        self::PENDIENTE,
        self::CONFIRMADO,
        self::EN_PREPARACION,
        self::LISTO,
        self::EN_CAMINO
    ];

    // Estados finales (pedido cerrado)
    public const FINALES = [
        self::ENTREGADO,
        self::CANCELADO
    ];

    // Estados que puede ver el negocio para aceptar
    public const ACEPTABLES_NEGOCIO = [
        self::PENDIENTE
    ];

    // Estados que puede ver el repartidor para tomar
    public const DISPONIBLES_REPARTIDOR = [
        self::LISTO
    ];

    /**
     * Obtener nombre legible del estado
     */
    public static function getNombre(int $estado): string {
        return self::NOMBRES[$estado] ?? 'Desconocido';
    }

    /**
     * Obtener código del estado
     */
    public static function getCodigo(int $estado): string {
        return self::CODIGOS[$estado] ?? 'unknown';
    }

    /**
     * Obtener color Bootstrap del estado
     */
    public static function getColor(int $estado): string {
        return self::COLORES[$estado] ?? 'secondary';
    }

    /**
     * Obtener icono Font Awesome del estado
     */
    public static function getIcono(int $estado): string {
        return self::ICONOS[$estado] ?? 'fa-question-circle';
    }

    /**
     * Verificar si el estado es activo (no finalizado)
     */
    public static function esActivo(int $estado): bool {
        return in_array($estado, self::ACTIVOS);
    }

    /**
     * Verificar si el estado es final (pedido cerrado)
     */
    public static function esFinal(int $estado): bool {
        return in_array($estado, self::FINALES);
    }

    /**
     * Verificar si es un ID de estado válido
     */
    public static function esValido(int $estado): bool {
        return $estado >= 1 && $estado <= 7;
    }

    /**
     * Obtener ID de estado desde código string
     * Útil para APIs que reciben strings
     */
    public static function desdeString(string $codigo): ?int {
        $codigo = strtolower(trim($codigo));

        // Mapeo de strings comunes a IDs
        $mapeo = [
            // Códigos oficiales
            'pending' => self::PENDIENTE,
            'confirmed' => self::CONFIRMADO,
            'preparing' => self::EN_PREPARACION,
            'ready' => self::LISTO,
            'on_the_way' => self::EN_CAMINO,
            'delivered' => self::ENTREGADO,
            'cancelled' => self::CANCELADO,

            // Variantes en español (legacy)
            'pendiente' => self::PENDIENTE,
            'confirmado' => self::CONFIRMADO,
            'en_preparacion' => self::EN_PREPARACION,
            'preparando' => self::EN_PREPARACION,
            'listo' => self::LISTO,
            'en_camino' => self::EN_CAMINO,
            'entregado' => self::ENTREGADO,
            'cancelado' => self::CANCELADO,

            // Otros comunes
            'accepted' => self::CONFIRMADO,
            'cooking' => self::EN_PREPARACION,
            'completed' => self::ENTREGADO,
            'canceled' => self::CANCELADO
        ];

        return $mapeo[$codigo] ?? null;
    }

    /**
     * Obtener siguiente estado válido en el flujo normal
     */
    public static function getSiguienteEstado(int $estadoActual): ?int {
        $flujo = [
            self::PENDIENTE => self::CONFIRMADO,
            self::CONFIRMADO => self::EN_PREPARACION,
            self::EN_PREPARACION => self::LISTO,
            self::LISTO => self::EN_CAMINO,
            self::EN_CAMINO => self::ENTREGADO
        ];

        return $flujo[$estadoActual] ?? null;
    }

    /**
     * Verificar si una transición de estado es válida
     */
    public static function transicionValida(int $estadoActual, int $nuevoEstado): bool {
        // Cancelar siempre es válido desde estados activos
        if ($nuevoEstado === self::CANCELADO && self::esActivo($estadoActual)) {
            return true;
        }

        // Verificar flujo normal
        $siguienteEsperado = self::getSiguienteEstado($estadoActual);
        return $siguienteEsperado === $nuevoEstado;
    }

    /**
     * Obtener todos los estados como array para selects/dropdowns
     */
    public static function getTodos(): array {
        $estados = [];
        foreach (self::NOMBRES as $id => $nombre) {
            $estados[] = [
                'id' => $id,
                'nombre' => $nombre,
                'codigo' => self::CODIGOS[$id],
                'color' => self::COLORES[$id],
                'icono' => self::ICONOS[$id]
            ];
        }
        return $estados;
    }
}

// ========================================
// FUNCIONES HELPER (compatibilidad legacy)
// ========================================

/**
 * Obtener nombre de estado (función legacy)
 * @deprecated Usar EstadoPedido::getNombre()
 */
function obtenerNombreEstado(int $idEstado): string {
    return EstadoPedido::getNombre($idEstado);
}

/**
 * Convertir string de estado a ID (función legacy)
 * @deprecated Usar EstadoPedido::desdeString()
 */
function estadoStringToId(string $estado): int {
    return EstadoPedido::desdeString($estado) ?? 1;
}
