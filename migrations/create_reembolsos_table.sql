-- ============================================
-- Tabla de Reembolsos
-- ============================================
-- Almacena todos los reembolsos procesados automáticamente o manualmente

CREATE TABLE IF NOT EXISTS `reembolsos` (
  `id_reembolso` INT(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `motivo` TEXT NOT NULL,
  `estado` ENUM('pendiente', 'procesando', 'aprobado', 'rechazado', 'error') DEFAULT 'pendiente',
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_aprobacion` DATETIME NULL,
  `payment_id_original` VARCHAR(100) NULL COMMENT 'ID del pago original (MercadoPago, Stripe, etc)',
  `refund_id` VARCHAR(100) NULL COMMENT 'ID del reembolso procesado',
  `metodo_reembolso` VARCHAR(50) NULL COMMENT 'Método usado para reembolso',
  `notas_admin` TEXT NULL,
  `procesado_automaticamente` TINYINT(1) DEFAULT 0,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reembolso`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_solicitud` (`fecha_solicitud`),
  CONSTRAINT `fk_reembolso_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `fk_reembolso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Agregar campos a tabla repartidores
-- ============================================
-- Para tracking de pedidos abandonados

ALTER TABLE `repartidores` 
ADD COLUMN IF NOT EXISTS `pedidos_abandonados` INT(11) DEFAULT 0 COMMENT 'Contador de pedidos abandonados',
ADD COLUMN IF NOT EXISTS `tasa_abandono` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Porcentaje de abandono',
ADD KEY `idx_pedidos_abandonados` (`pedidos_abandonados`);

-- ============================================
-- Actualizar descripción del estado abandonado
-- ============================================
UPDATE `estados_pedido` 
SET `descripcion` = 'Pedido abandonado por el repartidor - reembolso automático procesado'
WHERE `nombre` = 'abandonado';

-- ============================================
-- Insertar configuración de timeouts
-- ============================================
CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(100) NOT NULL UNIQUE,
  `valor` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NULL,
  `tipo` ENUM('integer', 'string', 'boolean', 'decimal') DEFAULT 'string',
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion_sistema` (`clave`, `valor`, `descripcion`, `tipo`) VALUES
('timeout_entrega_minutos', '60', 'Minutos máximos para entregar después de recoger', 'integer'),
('timeout_recogida_minutos', '30', 'Minutos máximos para recoger un pedido listo', 'integer'),
('timeout_en_camino_minutos', '45', 'Minutos máximos en estado "en camino" sin entregar', 'integer'),
('reembolso_automatico_habilitado', 'true', 'Habilitar reembolsos automáticos en pedidos abandonados', 'boolean'),
('notificar_usuario_abandono', 'true', 'Enviar notificación al usuario cuando se abandone un pedido', 'boolean')
ON DUPLICATE KEY UPDATE valor=valor;

-- ============================================
-- Vista para reportes de abandono
-- ============================================
CREATE OR REPLACE VIEW `vista_pedidos_abandonados` AS
SELECT 
    p.id_pedido,
    p.id_usuario,
    p.id_negocio,
    p.id_repartidor_anterior,
    p.monto_total,
    p.motivo_cancelacion,
    p.fecha_creacion,
    p.fecha_actualizacion,
    u.nombre as usuario_nombre,
    u.email as usuario_email,
    n.nombre as negocio_nombre,
    r.nombre as repartidor_nombre,
    r.telefono as repartidor_telefono,
    re.id_reembolso,
    re.estado as estado_reembolso,
    re.fecha_aprobacion as fecha_reembolso,
    TIMESTAMPDIFF(MINUTE, p.fecha_creacion, p.fecha_actualizacion) as minutos_totales
FROM pedidos p
LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
LEFT JOIN negocios n ON p.id_negocio = n.id_negocio
LEFT JOIN repartidores r ON p.id_repartidor_anterior = r.id_repartidor
LEFT JOIN reembolsos re ON p.id_pedido = re.id_pedido
WHERE p.id_estado = 8; -- abandonado

COMMIT;
