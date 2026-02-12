-- Migración: Pedidos Programados
-- Fecha: 2025-01-13
-- Descripción: Campos para programar pedidos para fecha/hora específica

SET @dbname = DATABASE();

-- =====================================================
-- 1. Agregar campos de programación a pedidos
-- =====================================================

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'es_programado');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN es_programado TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'fecha_programada');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN fecha_programada DATETIME DEFAULT NULL COMMENT "Fecha y hora deseada de entrega"', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'recordatorio_enviado');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN recordatorio_enviado TINYINT(1) DEFAULT 0 COMMENT "Si se envió recordatorio al negocio"', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. Agregar configuración de horarios a negocios
-- =====================================================

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'negocios' AND COLUMN_NAME = 'acepta_programados');
SET @query = IF(@columnExists = 0, 'ALTER TABLE negocios ADD COLUMN acepta_programados TINYINT(1) DEFAULT 1 COMMENT "Si acepta pedidos programados"', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'negocios' AND COLUMN_NAME = 'tiempo_minimo_programacion');
SET @query = IF(@columnExists = 0, 'ALTER TABLE negocios ADD COLUMN tiempo_minimo_programacion INT DEFAULT 60 COMMENT "Minutos mínimos de anticipación"', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. Índice para consultar pedidos programados pendientes
-- =====================================================

-- Verificar si el índice ya existe antes de crearlo
SET @indexExists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = 'pedidos'
    AND INDEX_NAME = 'idx_pedidos_programados'
);
SET @query = IF(@indexExists = 0,
    'CREATE INDEX idx_pedidos_programados ON pedidos(es_programado, fecha_programada, id_estado)',
    'SELECT 1'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migración 029 completada: Pedidos programados' AS resultado;
