-- =====================================================
-- MIGRACIÓN: Sistema de Reasignación y Multi-Pedido
-- QuickBite - Sistema Ganar-Ganar
-- Fecha: 2026-01-03
-- =====================================================

-- =====================================================
-- PARTE 1: SISTEMA DE TIMEOUT Y REASIGNACIÓN
-- =====================================================

-- Nuevos estados para el flujo de reasignación
INSERT INTO estados_pedido (id_estado, nombre, descripcion) VALUES
(8, 'abandonado', 'Pedido abandonado por el repartidor, buscando nuevo repartidor'),
(9, 'reasignado', 'Pedido reasignado a un nuevo repartidor'),
(10, 'sin_repartidor', 'No hay repartidores disponibles, esperando')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion);

-- Campos adicionales en pedidos para control de tiempos
-- Usamos procedimiento para evitar errores si columnas ya existen
DROP PROCEDURE IF EXISTS add_pedidos_columns;
DELIMITER //
CREATE PROCEDURE add_pedidos_columns()
BEGIN
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'fecha_asignacion_repartidor') THEN
        ALTER TABLE pedidos ADD COLUMN fecha_asignacion_repartidor DATETIME NULL COMMENT 'Cuando se asignó al repartidor';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'fecha_aceptacion_repartidor') THEN
        ALTER TABLE pedidos ADD COLUMN fecha_aceptacion_repartidor DATETIME NULL COMMENT 'Cuando el repartidor confirmó que va en camino';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'fecha_recogida') THEN
        ALTER TABLE pedidos ADD COLUMN fecha_recogida DATETIME NULL COMMENT 'Cuando el repartidor recogió el pedido';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'timeout_aceptacion_minutos') THEN
        ALTER TABLE pedidos ADD COLUMN timeout_aceptacion_minutos INT DEFAULT 10 COMMENT 'Minutos límite para aceptar';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'timeout_recogida_minutos') THEN
        ALTER TABLE pedidos ADD COLUMN timeout_recogida_minutos INT DEFAULT 20 COMMENT 'Minutos límite para recoger';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'intentos_asignacion') THEN
        ALTER TABLE pedidos ADD COLUMN intentos_asignacion INT DEFAULT 0 COMMENT 'Veces que se ha intentado asignar';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'prioridad') THEN
        ALTER TABLE pedidos ADD COLUMN prioridad INT DEFAULT 0 COMMENT 'Prioridad de asignación (mayor = más urgente)';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'motivo_cancelacion') THEN
        ALTER TABLE pedidos ADD COLUMN motivo_cancelacion VARCHAR(255) NULL COMMENT 'Razón de cancelación si aplica';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'id_repartidor_anterior') THEN
        ALTER TABLE pedidos ADD COLUMN id_repartidor_anterior INT NULL COMMENT 'Repartidor anterior si fue reasignado';
    END IF;
END//
DELIMITER ;
CALL add_pedidos_columns();
DROP PROCEDURE IF EXISTS add_pedidos_columns;

-- Índices para búsquedas rápidas (ignorar error si ya existen)
-- Se crean con procedimiento
DROP PROCEDURE IF EXISTS create_pedidos_indexes;
DELIMITER //
CREATE PROCEDURE create_pedidos_indexes()
BEGIN
    DECLARE CONTINUE HANDLER FOR 1061 BEGIN END; -- Ignorar error "Duplicate key name"
    CREATE INDEX idx_pedidos_timeout ON pedidos(fecha_asignacion_repartidor, id_estado);
    CREATE INDEX idx_pedidos_prioridad ON pedidos(prioridad, fecha_creacion);
END//
DELIMITER ;
CALL create_pedidos_indexes();
DROP PROCEDURE IF EXISTS create_pedidos_indexes;

-- Tabla de historial de reasignaciones
CREATE TABLE IF NOT EXISTS reasignaciones_pedido (
    id_reasignacion INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_repartidor_anterior INT NULL,
    id_repartidor_nuevo INT NULL,
    motivo ENUM('timeout_aceptacion', 'timeout_recogida', 'abandono_voluntario', 'problema_vehiculo', 'emergencia', 'reasignacion_admin', 'optimizacion_ruta') NOT NULL,
    notas TEXT NULL,
    fecha_reasignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    iniciado_por ENUM('sistema', 'repartidor', 'negocio', 'admin', 'cliente') DEFAULT 'sistema',
    id_usuario_iniciador INT NULL COMMENT 'ID del usuario que inició la reasignación',
    
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    INDEX idx_reasig_pedido (id_pedido),
    INDEX idx_reasig_repartidor_ant (id_repartidor_anterior),
    INDEX idx_reasig_fecha (fecha_reasignacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de métricas de repartidores (para scoring de asignación)
CREATE TABLE IF NOT EXISTS metricas_repartidor (
    id_metrica INT PRIMARY KEY AUTO_INCREMENT,
    id_repartidor INT NOT NULL UNIQUE,
    total_pedidos_completados INT DEFAULT 0,
    total_pedidos_abandonados INT DEFAULT 0,
    total_pedidos_timeout INT DEFAULT 0,
    promedio_tiempo_aceptacion DECIMAL(10,2) DEFAULT 0 COMMENT 'Minutos promedio en aceptar',
    promedio_tiempo_recogida DECIMAL(10,2) DEFAULT 0 COMMENT 'Minutos promedio en recoger',
    promedio_tiempo_entrega DECIMAL(10,2) DEFAULT 0 COMMENT 'Minutos promedio total',
    calificacion_promedio DECIMAL(3,2) DEFAULT 5.00,
    tasa_cumplimiento DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Porcentaje de pedidos completados',
    score_confiabilidad INT DEFAULT 100 COMMENT 'Score 0-100 para priorizar asignaciones',
    ultima_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    INDEX idx_metricas_score (score_confiabilidad DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de configuración de timeouts por zona/negocio
CREATE TABLE IF NOT EXISTS configuracion_timeout (
    id_config INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('global', 'zona', 'negocio') DEFAULT 'global',
    id_referencia INT NULL COMMENT 'ID de zona o negocio si aplica',
    timeout_aceptacion_minutos INT DEFAULT 10,
    timeout_recogida_minutos INT DEFAULT 20,
    max_intentos_asignacion INT DEFAULT 3,
    radio_busqueda_km DECIMAL(5,2) DEFAULT 5.00 COMMENT 'Radio para buscar repartidores',
    incremento_radio_km DECIMAL(5,2) DEFAULT 2.00 COMMENT 'Incremento por cada reintento',
    bonificacion_reintento DECIMAL(10,2) DEFAULT 5.00 COMMENT 'Bonus extra por recoger pedido reasignado',
    activo TINYINT(1) DEFAULT 1,
    
    INDEX idx_config_tipo (tipo, id_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración global por defecto
INSERT INTO configuracion_timeout (tipo, timeout_aceptacion_minutos, timeout_recogida_minutos, max_intentos_asignacion) 
VALUES ('global', 10, 20, 3)
ON DUPLICATE KEY UPDATE tipo = tipo;

-- =====================================================
-- PARTE 2: SISTEMA MULTI-PEDIDO OPTIMIZADO
-- =====================================================

-- Mejorar tabla de rutas con más campos útiles
DROP PROCEDURE IF EXISTS add_rutas_columns;
DELIMITER //
CREATE PROCEDURE add_rutas_columns()
BEGIN
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'rutas_entrega' AND COLUMN_NAME = 'max_pedidos') THEN
        ALTER TABLE rutas_entrega ADD COLUMN max_pedidos INT DEFAULT 4 COMMENT 'Máximo de pedidos en esta ruta';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'rutas_entrega' AND COLUMN_NAME = 'radio_agrupacion_km') THEN
        ALTER TABLE rutas_entrega ADD COLUMN radio_agrupacion_km DECIMAL(5,2) DEFAULT 3.00 COMMENT 'Radio máximo para agrupar pedidos';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'rutas_entrega' AND COLUMN_NAME = 'tipo_ruta') THEN
        ALTER TABLE rutas_entrega ADD COLUMN tipo_ruta ENUM('single', 'batch', 'optimizada') DEFAULT 'single';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'rutas_entrega' AND COLUMN_NAME = 'ahorro_distancia_km') THEN
        ALTER TABLE rutas_entrega ADD COLUMN ahorro_distancia_km DECIMAL(10,2) DEFAULT 0 COMMENT 'KM ahorrados vs entregas individuales';
    END IF;
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'app_delivery' AND TABLE_NAME = 'rutas_entrega' AND COLUMN_NAME = 'bonificacion_batch') THEN
        ALTER TABLE rutas_entrega ADD COLUMN bonificacion_batch DECIMAL(10,2) DEFAULT 0 COMMENT 'Bonus por batch delivery';
    END IF;
END//
DELIMITER ;
CALL add_rutas_columns();
DROP PROCEDURE IF EXISTS add_rutas_columns;

-- Tabla de pedidos disponibles para batch (cache para performance)
CREATE TABLE IF NOT EXISTS pedidos_disponibles_batch (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL UNIQUE,
    id_negocio INT NOT NULL,
    latitud_negocio DECIMAL(10,8) NOT NULL,
    longitud_negocio DECIMAL(10,8) NOT NULL,
    latitud_entrega DECIMAL(10,8) NOT NULL,
    longitud_entrega DECIMAL(10,8) NOT NULL,
    distancia_negocio_entrega DECIMAL(10,2) NOT NULL COMMENT 'KM del negocio al cliente',
    fecha_listo DATETIME NOT NULL COMMENT 'Cuando estará listo para recoger',
    prioridad INT DEFAULT 0,
    es_express TINYINT(1) DEFAULT 0,
    fecha_agregado DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE,
    INDEX idx_batch_negocio (id_negocio),
    INDEX idx_batch_ubicacion (latitud_negocio, longitud_negocio),
    INDEX idx_batch_fecha (fecha_listo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de sugerencias de batch para repartidores
CREATE TABLE IF NOT EXISTS sugerencias_batch (
    id_sugerencia INT PRIMARY KEY AUTO_INCREMENT,
    id_repartidor INT NOT NULL,
    pedidos_sugeridos JSON NOT NULL COMMENT 'Array de IDs de pedidos',
    distancia_total_km DECIMAL(10,2) NOT NULL,
    tiempo_estimado_min INT NOT NULL,
    ganancia_estimada DECIMAL(10,2) NOT NULL,
    ahorro_vs_individual DECIMAL(10,2) NOT NULL COMMENT 'Ahorro en KM',
    score_eficiencia INT NOT NULL COMMENT '0-100, qué tan buena es la ruta',
    estado ENUM('pendiente', 'aceptada', 'rechazada', 'expirada') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    INDEX idx_sug_repartidor (id_repartidor, estado),
    INDEX idx_sug_expiracion (fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PARTE 3: INCENTIVOS Y GAMIFICACIÓN
-- =====================================================

-- Tabla de bonificaciones por eficiencia
CREATE TABLE IF NOT EXISTS bonificaciones_repartidor (
    id_bonificacion INT PRIMARY KEY AUTO_INCREMENT,
    id_repartidor INT NOT NULL,
    id_pedido INT NULL,
    id_ruta INT NULL,
    tipo ENUM('batch_delivery', 'rescate_pedido', 'velocidad', 'calificacion_perfecta', 'racha_completados', 'hora_pico') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    estado ENUM('pendiente', 'pagada', 'cancelada') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_pago DATETIME NULL,
    
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    INDEX idx_bonif_repartidor (id_repartidor, estado),
    INDEX idx_bonif_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logros/achievements
CREATE TABLE IF NOT EXISTS logros_repartidor (
    id_logro INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    icono VARCHAR(50) DEFAULT '🏆',
    requisito_tipo ENUM('pedidos_completados', 'pedidos_batch', 'rescates', 'calificacion', 'distancia', 'racha') NOT NULL,
    requisito_valor INT NOT NULL,
    bonificacion DECIMAL(10,2) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logros desbloqueados por repartidor
CREATE TABLE IF NOT EXISTS repartidor_logros (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_repartidor INT NOT NULL,
    id_logro INT NOT NULL,
    fecha_desbloqueo DATETIME DEFAULT CURRENT_TIMESTAMP,
    bonificacion_otorgada DECIMAL(10,2) DEFAULT 0,
    
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    FOREIGN KEY (id_logro) REFERENCES logros_repartidor(id_logro) ON DELETE CASCADE,
    UNIQUE KEY unique_repartidor_logro (id_repartidor, id_logro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar logros iniciales
INSERT INTO logros_repartidor (nombre, descripcion, icono, requisito_tipo, requisito_valor, bonificacion) VALUES
('Primer Pedido', 'Completaste tu primer pedido', '🎉', 'pedidos_completados', 1, 10.00),
('Repartidor Novato', 'Completaste 10 pedidos', '🌟', 'pedidos_completados', 10, 25.00),
('Repartidor Experto', 'Completaste 50 pedidos', '⭐', 'pedidos_completados', 50, 50.00),
('Repartidor Élite', 'Completaste 100 pedidos', '💫', 'pedidos_completados', 100, 100.00),
('Repartidor Legendario', 'Completaste 500 pedidos', '👑', 'pedidos_completados', 500, 250.00),
('Maestro del Batch', 'Completaste 10 entregas múltiples', '📦', 'pedidos_batch', 10, 50.00),
('Rey del Batch', 'Completaste 50 entregas múltiples', '🚀', 'pedidos_batch', 50, 150.00),
('Héroe del Rescate', 'Rescataste 5 pedidos abandonados', '🦸', 'rescates', 5, 75.00),
('Salvador de Pedidos', 'Rescataste 20 pedidos abandonados', '🏅', 'rescates', 20, 200.00),
('5 Estrellas', 'Mantuviste calificación perfecta por 30 días', '✨', 'calificacion', 30, 100.00),
('Racha de 7', 'Completaste 7 pedidos seguidos sin rechazar', '🔥', 'racha', 7, 35.00),
('Racha de 15', 'Completaste 15 pedidos seguidos sin rechazar', '💥', 'racha', 15, 75.00),
('Maratonista', 'Recorriste más de 100km en un día', '🏃', 'distancia', 100, 50.00)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- =====================================================
-- PARTE 4: VISTAS ÚTILES
-- =====================================================

-- Vista de pedidos que necesitan reasignación urgente
CREATE OR REPLACE VIEW v_pedidos_timeout AS
SELECT 
    p.id_pedido,
    p.id_negocio,
    p.id_repartidor,
    p.id_estado,
    ep.nombre as estado_nombre,
    p.fecha_asignacion_repartidor,
    p.fecha_aceptacion_repartidor,
    p.timeout_aceptacion_minutos,
    p.timeout_recogida_minutos,
    p.intentos_asignacion,
    p.prioridad,
    TIMESTAMPDIFF(MINUTE, p.fecha_asignacion_repartidor, NOW()) as minutos_desde_asignacion,
    TIMESTAMPDIFF(MINUTE, p.fecha_aceptacion_repartidor, NOW()) as minutos_desde_aceptacion,
    CASE 
        WHEN p.id_estado = 4 AND p.fecha_aceptacion_repartidor IS NULL 
             AND TIMESTAMPDIFF(MINUTE, p.fecha_asignacion_repartidor, NOW()) > p.timeout_aceptacion_minutos 
        THEN 'TIMEOUT_ACEPTACION'
        WHEN p.id_estado = 5 AND p.fecha_recogida IS NULL 
             AND TIMESTAMPDIFF(MINUTE, p.fecha_aceptacion_repartidor, NOW()) > p.timeout_recogida_minutos 
        THEN 'TIMEOUT_RECOGIDA'
        ELSE 'OK'
    END as estado_timeout
FROM pedidos p
JOIN estados_pedido ep ON p.id_estado = ep.id_estado
WHERE p.id_estado IN (4, 5) -- listo_para_recoger, en_camino
  AND p.id_repartidor IS NOT NULL;

-- Vista de repartidores disponibles con métricas
CREATE OR REPLACE VIEW v_repartidores_disponibles AS
SELECT 
    r.id_repartidor,
    r.id_usuario,
    u.nombre,
    u.telefono,
    r.latitud_actual as latitud,
    r.longitud_actual as longitud,
    r.disponible,
    r.tipo_vehiculo as vehiculo,
    COALESCE(m.score_confiabilidad, 100) as score,
    COALESCE(m.tasa_cumplimiento, 100) as tasa_cumplimiento,
    COALESCE(m.calificacion_promedio, 5.0) as calificacion,
    COALESCE(m.total_pedidos_completados, 0) as pedidos_completados,
    (SELECT COUNT(*) FROM pedidos WHERE id_repartidor = r.id_repartidor AND id_estado IN (4,5)) as pedidos_activos
FROM repartidores r
JOIN usuarios u ON r.id_usuario = u.id_usuario
LEFT JOIN metricas_repartidor m ON r.id_repartidor = m.id_repartidor
WHERE r.disponible = 1
  AND r.activo = 1;

-- Vista de sugerencias de batch activas
CREATE OR REPLACE VIEW v_sugerencias_batch_activas AS
SELECT 
    sb.*,
    u.nombre as nombre_repartidor,
    JSON_LENGTH(sb.pedidos_sugeridos) as num_pedidos
FROM sugerencias_batch sb
JOIN repartidores r ON sb.id_repartidor = r.id_repartidor
JOIN usuarios u ON r.id_usuario = u.id_usuario
WHERE sb.estado = 'pendiente'
  AND sb.fecha_expiracion > NOW();

-- =====================================================
-- PARTE 5: TRIGGERS AUTOMÁTICOS
-- =====================================================

-- =====================================================
-- PARTE 5: TRIGGERS (COMENTADOS - REQUIEREN SUPER PRIVILEGE)
-- La lógica se implementa en PHP en su lugar
-- =====================================================

-- Los triggers requieren privilegio SUPER en MySQL con binary logging
-- En su lugar, la lógica está en: models/GestionPedidos.php

/*
-- Trigger para crear métricas al registrar repartidor
DROP TRIGGER IF EXISTS after_repartidor_insert;
CREATE TRIGGER after_repartidor_insert
AFTER INSERT ON repartidores
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO metricas_repartidor (id_repartidor) VALUES (NEW.id_repartidor);
END;

-- Trigger para actualizar métricas al completar pedido  
DROP TRIGGER IF EXISTS after_pedido_completado;
CREATE TRIGGER after_pedido_completado
AFTER UPDATE ON pedidos
FOR EACH ROW
BEGIN
    IF NEW.id_estado = 6 AND OLD.id_estado != 6 AND NEW.id_repartidor IS NOT NULL THEN
        UPDATE metricas_repartidor SET total_pedidos_completados = total_pedidos_completados + 1 WHERE id_repartidor = NEW.id_repartidor;
    END IF;
    IF NEW.id_estado = 8 AND OLD.id_estado != 8 AND OLD.id_repartidor IS NOT NULL THEN
        UPDATE metricas_repartidor SET total_pedidos_abandonados = total_pedidos_abandonados + 1, score_confiabilidad = GREATEST(0, score_confiabilidad - 5) WHERE id_repartidor = OLD.id_repartidor;
    END IF;
END;
*/

-- =====================================================
-- DATOS INICIALES PARA TESTING
-- =====================================================

-- Crear métricas para repartidores existentes
INSERT IGNORE INTO metricas_repartidor (id_repartidor)
SELECT id_repartidor FROM repartidores;

SELECT '✅ Migración completada: Sistema de Reasignación y Multi-Pedido' as resultado;
