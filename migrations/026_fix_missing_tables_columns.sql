-- =====================================================
-- MIGRACION 026: Correccion de tablas y columnas faltantes
-- QuickBite - Solucion de errores criticos de funcionalidad
-- Fecha: 2026-01-10
-- =====================================================

-- =====================================================
-- 1. CREAR TABLA grupos_opciones (para extras de productos)
-- =====================================================
CREATE TABLE IF NOT EXISTS grupos_opciones (
    id_grupo_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    nombre VARCHAR(100) NOT NULL COMMENT 'Ej: Tamano, Extras, Bebida',
    es_obligatorio TINYINT(1) DEFAULT 0 COMMENT 'Si el cliente debe elegir al menos una opcion',
    min_selecciones INT DEFAULT 0 COMMENT 'Minimo de opciones a seleccionar',
    max_selecciones INT DEFAULT 1 COMMENT 'Maximo de opciones a seleccionar',
    orden_visualizacion INT DEFAULT 0 COMMENT 'Orden en que aparece el grupo',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    INDEX idx_producto (id_producto),
    INDEX idx_orden (orden_visualizacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. CREAR TABLA opciones (opciones dentro de cada grupo)
-- =====================================================
CREATE TABLE IF NOT EXISTS opciones (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_grupo_opcion INT NOT NULL,
    nombre VARCHAR(100) NOT NULL COMMENT 'Ej: Grande, Mediano, Extra queso',
    precio_adicional DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Costo adicional de esta opcion',
    por_defecto TINYINT(1) DEFAULT 0 COMMENT 'Si esta opcion viene seleccionada por defecto',
    orden_visualizacion INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_grupo_opcion) REFERENCES grupos_opciones(id_grupo_opcion) ON DELETE CASCADE,
    INDEX idx_grupo (id_grupo_opcion),
    INDEX idx_orden (orden_visualizacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. AGREGAR COLUMNAS FALTANTES EN negocios
-- =====================================================
-- Columna rating_promedio
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'negocios' AND COLUMN_NAME = 'rating_promedio');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE negocios ADD COLUMN rating_promedio DECIMAL(2,1) DEFAULT 0.0 COMMENT ''Cache de rating promedio''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna total_resenas
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'negocios' AND COLUMN_NAME = 'total_resenas');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE negocios ADD COLUMN total_resenas INT DEFAULT 0 COMMENT ''Cache de total de resenas''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna verificado
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'negocios' AND COLUMN_NAME = 'verificado');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE negocios ADD COLUMN verificado TINYINT(1) DEFAULT 0 COMMENT ''Negocio verificado por QuickBite''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna destacado
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'negocios' AND COLUMN_NAME = 'destacado');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE negocios ADD COLUMN destacado TINYINT(1) DEFAULT 0 COMMENT ''Negocio destacado en homepage''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. AGREGAR COLUMNAS FALTANTES EN valoraciones
-- =====================================================
-- Columna visible
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'valoraciones' AND COLUMN_NAME = 'visible');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE valoraciones ADD COLUMN visible TINYINT(1) DEFAULT 1 COMMENT ''Si la resena es visible publicamente''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna calificacion_repartidor
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'valoraciones' AND COLUMN_NAME = 'calificacion_repartidor');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE valoraciones ADD COLUMN calificacion_repartidor TINYINT DEFAULT NULL COMMENT ''Calificacion 1-5 al repartidor''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna respuesta_negocio
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'valoraciones' AND COLUMN_NAME = 'respuesta_negocio');
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE valoraciones ADD COLUMN respuesta_negocio TEXT DEFAULT NULL COMMENT ''Respuesta del negocio a la resena''',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 5. CREAR INDICES PARA OPTIMIZACION
-- =====================================================
-- Indice para negocios por rating
CREATE INDEX IF NOT EXISTS idx_negocio_rating ON negocios(rating_promedio DESC);
CREATE INDEX IF NOT EXISTS idx_negocio_destacado ON negocios(destacado, rating_promedio DESC);

-- =====================================================
-- 6. INICIALIZAR VALORES POR DEFECTO
-- =====================================================
-- Establecer rating_promedio = 0 donde sea NULL
UPDATE negocios SET rating_promedio = 0.0 WHERE rating_promedio IS NULL;
UPDATE negocios SET total_resenas = 0 WHERE total_resenas IS NULL;

-- Establecer visible = 1 para todas las valoraciones existentes
UPDATE valoraciones SET visible = 1 WHERE visible IS NULL;

-- =====================================================
-- FIN DE MIGRACION 026
-- =====================================================
