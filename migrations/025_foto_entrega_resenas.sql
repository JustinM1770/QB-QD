-- =====================================================
-- MIGRACION 025: Sistema de Foto de Entrega y Resenas
-- QuickBite - Sistema completo de verificacion de entrega
-- =====================================================

-- Tabla para fotos de entrega
CREATE TABLE IF NOT EXISTS fotos_entrega (
    id_foto INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_repartidor INT NOT NULL,
    foto_url VARCHAR(500) NOT NULL COMMENT 'Ruta de la imagen',
    latitud DECIMAL(10,8) DEFAULT NULL COMMENT 'Ubicacion donde se tomo la foto',
    longitud DECIMAL(11,8) DEFAULT NULL COMMENT 'Ubicacion donde se tomo la foto',
    fecha_captura DATETIME DEFAULT CURRENT_TIMESTAMP,
    notas TEXT DEFAULT NULL COMMENT 'Notas del repartidor sobre la entrega',
    validada TINYINT(1) DEFAULT 0 COMMENT 'Si fue validada por el sistema/admin',
    fecha_validacion DATETIME DEFAULT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    INDEX idx_pedido (id_pedido),
    INDEX idx_repartidor (id_repartidor),
    INDEX idx_fecha (fecha_captura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar campo de foto de entrega a pedidos si no existe
ALTER TABLE pedidos
ADD COLUMN IF NOT EXISTS foto_entrega_url VARCHAR(500) DEFAULT NULL COMMENT 'Foto de entrega',
ADD COLUMN IF NOT EXISTS foto_entrega_fecha DATETIME DEFAULT NULL;

-- Expandir tabla de valoraciones si es necesario
ALTER TABLE valoraciones
ADD COLUMN IF NOT EXISTS calificacion_repartidor TINYINT DEFAULT NULL COMMENT 'Calificacion 1-5 al repartidor',
ADD COLUMN IF NOT EXISTS comentario_repartidor TEXT DEFAULT NULL COMMENT 'Comentario sobre el repartidor',
ADD COLUMN IF NOT EXISTS tiempo_entrega_percibido ENUM('muy_rapido', 'rapido', 'normal', 'lento', 'muy_lento') DEFAULT NULL,
ADD COLUMN IF NOT EXISTS estado_pedido ENUM('perfecto', 'bien', 'con_problemas', 'danado') DEFAULT 'perfecto',
ADD COLUMN IF NOT EXISTS foto_resena VARCHAR(500) DEFAULT NULL COMMENT 'Foto opcional del cliente sobre el pedido',
ADD COLUMN IF NOT EXISTS visible TINYINT(1) DEFAULT 1 COMMENT 'Si la resena es visible publicamente',
ADD COLUMN IF NOT EXISTS respuesta_negocio TEXT DEFAULT NULL COMMENT 'Respuesta del negocio a la resena',
ADD COLUMN IF NOT EXISTS fecha_respuesta DATETIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS util_count INT DEFAULT 0 COMMENT 'Cuantos usuarios marcaron como util';

-- Tabla para rastrear si el usuario ya dejo resena
CREATE TABLE IF NOT EXISTS resenas_pendientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_usuario INT NOT NULL,
    recordatorio_enviado TINYINT(1) DEFAULT 0,
    fecha_recordatorio DATETIME DEFAULT NULL,
    resena_completada TINYINT(1) DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_pedido_usuario (id_pedido, id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar campos de verificacion a negocios
ALTER TABLE negocios
ADD COLUMN IF NOT EXISTS verificado TINYINT(1) DEFAULT 0 COMMENT 'Negocio verificado por QuickBite',
ADD COLUMN IF NOT EXISTS fecha_verificacion DATE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS badge_premium TINYINT(1) DEFAULT 0 COMMENT 'Mostrar badge premium',
ADD COLUMN IF NOT EXISTS destacado TINYINT(1) DEFAULT 0 COMMENT 'Negocio destacado en homepage',
ADD COLUMN IF NOT EXISTS orden_destacado INT DEFAULT 0 COMMENT 'Orden de aparicion en destacados',
ADD COLUMN IF NOT EXISTS total_resenas INT DEFAULT 0 COMMENT 'Cache de total de resenas',
ADD COLUMN IF NOT EXISTS rating_promedio DECIMAL(2,1) DEFAULT 0.0 COMMENT 'Cache de rating promedio';

-- Crear indice para negocios destacados/premium
CREATE INDEX IF NOT EXISTS idx_negocio_destacado ON negocios(destacado, orden_destacado);
CREATE INDEX IF NOT EXISTS idx_negocio_verificado ON negocios(verificado);
CREATE INDEX IF NOT EXISTS idx_negocio_premium ON negocios(es_premium);

-- Agregar campos de estadisticas a repartidores
ALTER TABLE repartidores
ADD COLUMN IF NOT EXISTS total_resenas INT DEFAULT 0 COMMENT 'Total de resenas recibidas',
ADD COLUMN IF NOT EXISTS rating_promedio_resenas DECIMAL(2,1) DEFAULT 5.0 COMMENT 'Rating promedio de resenas';

-- Crear vista para negocios recomendados
CREATE OR REPLACE VIEW v_negocios_recomendados AS
SELECT
    n.*,
    COALESCE(AVG(v.calificacion_negocio), 0) as rating_calculado,
    COUNT(DISTINCT v.id_valoracion) as total_valoraciones,
    CASE
        WHEN n.es_premium = 1 AND n.verificado = 1 THEN 3
        WHEN n.es_premium = 1 THEN 2
        WHEN n.verificado = 1 THEN 1
        ELSE 0
    END as prioridad
FROM negocios n
LEFT JOIN valoraciones v ON n.id_negocio = v.id_negocio
WHERE n.activo = 1
  AND n.estado_operativo = 'activo'
GROUP BY n.id_negocio
ORDER BY prioridad DESC, rating_calculado DESC, total_valoraciones DESC;

-- Trigger para actualizar cache de resenas en negocio
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trg_actualizar_stats_negocio
AFTER INSERT ON valoraciones
FOR EACH ROW
BEGIN
    UPDATE negocios
    SET total_resenas = (SELECT COUNT(*) FROM valoraciones WHERE id_negocio = NEW.id_negocio AND visible = 1),
        rating_promedio = (SELECT COALESCE(AVG(calificacion_negocio), 0) FROM valoraciones WHERE id_negocio = NEW.id_negocio AND visible = 1)
    WHERE id_negocio = NEW.id_negocio;
END//

-- Trigger para actualizar stats del repartidor
CREATE TRIGGER IF NOT EXISTS trg_actualizar_stats_repartidor
AFTER INSERT ON valoraciones
FOR EACH ROW
BEGIN
    IF NEW.id_repartidor IS NOT NULL AND NEW.calificacion_repartidor IS NOT NULL THEN
        UPDATE repartidores
        SET total_resenas = (SELECT COUNT(*) FROM valoraciones WHERE id_repartidor = NEW.id_repartidor AND calificacion_repartidor IS NOT NULL),
            rating_promedio_resenas = (SELECT COALESCE(AVG(calificacion_repartidor), 5) FROM valoraciones WHERE id_repartidor = NEW.id_repartidor AND calificacion_repartidor IS NOT NULL),
            calificacion_promedio = (SELECT COALESCE(AVG(calificacion_repartidor), 5) FROM valoraciones WHERE id_repartidor = NEW.id_repartidor AND calificacion_repartidor IS NOT NULL)
        WHERE id_repartidor = NEW.id_repartidor;
    END IF;
END//
DELIMITER ;

-- Procedimiento para marcar resena pendiente al entregar pedido
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sp_crear_resena_pendiente(IN p_id_pedido INT)
BEGIN
    DECLARE v_id_usuario INT;

    SELECT id_usuario INTO v_id_usuario FROM pedidos WHERE id_pedido = p_id_pedido;

    IF v_id_usuario IS NOT NULL THEN
        INSERT IGNORE INTO resenas_pendientes (id_pedido, id_usuario)
        VALUES (p_id_pedido, v_id_usuario);
    END IF;
END//
DELIMITER ;
