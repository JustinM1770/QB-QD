-- Migración: Campos para mensajes personalizados en pedidos tipo regalo
-- Fecha: 2025-01-13
-- Descripción: Agrega soporte para mensajes de tarjeta, texto en producto y opciones de regalo

-- =====================================================
-- 1. Campos en personalizacion_unidad
-- =====================================================

-- Verificar y agregar campos (MySQL 5.7+ compatible)
SET @dbname = DATABASE();

-- mensaje_tarjeta en personalizacion_unidad
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'personalizacion_unidad' AND COLUMN_NAME = 'mensaje_tarjeta');
SET @query = IF(@columnExists = 0, 'ALTER TABLE personalizacion_unidad ADD COLUMN mensaje_tarjeta TEXT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- texto_producto en personalizacion_unidad
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'personalizacion_unidad' AND COLUMN_NAME = 'texto_producto');
SET @query = IF(@columnExists = 0, 'ALTER TABLE personalizacion_unidad ADD COLUMN texto_producto VARCHAR(255) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. Campos en pedidos
-- =====================================================

-- es_regalo en pedidos
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'es_regalo');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN es_regalo TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- nombre_destinatario en pedidos
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'nombre_destinatario');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN nombre_destinatario VARCHAR(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. Campos en productos para habilitar personalización
-- =====================================================

-- permite_mensaje_tarjeta en productos
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'permite_mensaje_tarjeta');
SET @query = IF(@columnExists = 0, 'ALTER TABLE productos ADD COLUMN permite_mensaje_tarjeta TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- permite_texto_producto en productos
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'permite_texto_producto');
SET @query = IF(@columnExists = 0, 'ALTER TABLE productos ADD COLUMN permite_texto_producto TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- limite_texto_producto en productos
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'limite_texto_producto');
SET @query = IF(@columnExists = 0, 'ALTER TABLE productos ADD COLUMN limite_texto_producto INT DEFAULT 50', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Verificación
-- =====================================================
SELECT 'Migración 027 completada: Campos de mensaje personalizado agregados' AS resultado;
