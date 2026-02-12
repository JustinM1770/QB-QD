-- Migración: Sistema de Cupones
-- Fecha: 2025-01-13
-- Descripción: Tablas para gestión de cupones y descuentos

SET @dbname = DATABASE();

-- =====================================================
-- 1. Tabla principal de cupones
-- =====================================================

CREATE TABLE IF NOT EXISTS cupones (
    id_cupon INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    tipo_descuento ENUM('porcentaje', 'monto_fijo') NOT NULL DEFAULT 'porcentaje',
    valor_descuento DECIMAL(10,2) NOT NULL,
    minimo_compra DECIMAL(10,2) DEFAULT 0,
    maximo_descuento DECIMAL(10,2) DEFAULT NULL COMMENT 'Límite máximo de descuento para porcentajes',
    usos_maximos INT DEFAULT NULL COMMENT 'NULL = ilimitado',
    usos_actuales INT DEFAULT 0,
    usos_por_usuario INT DEFAULT 1 COMMENT 'Veces que un usuario puede usar el cupón',
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME DEFAULT NULL,
    aplica_todos_negocios TINYINT(1) DEFAULT 1,
    solo_primera_compra TINYINT(1) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_activo (activo),
    INDEX idx_fechas (fecha_inicio, fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. Tabla de negocios específicos para cupones
-- =====================================================

CREATE TABLE IF NOT EXISTS cupones_negocios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cupon INT NOT NULL,
    id_negocio INT NOT NULL,
    UNIQUE KEY unique_cupon_negocio (id_cupon, id_negocio),
    FOREIGN KEY (id_cupon) REFERENCES cupones(id_cupon) ON DELETE CASCADE,
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. Tabla de uso de cupones por usuario
-- =====================================================

CREATE TABLE IF NOT EXISTS cupones_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cupon INT NOT NULL,
    id_usuario INT NOT NULL,
    id_pedido INT DEFAULT NULL,
    descuento_aplicado DECIMAL(10,2) NOT NULL,
    fecha_uso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cupon) REFERENCES cupones(id_cupon) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    INDEX idx_cupon_usuario (id_cupon, id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. Agregar campo de cupón a pedidos
-- =====================================================

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'id_cupon');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN id_cupon INT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'descuento_cupon');
SET @query = IF(@columnExists = 0, 'ALTER TABLE pedidos ADD COLUMN descuento_cupon DECIMAL(10,2) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 5. Cupones de ejemplo
-- =====================================================

INSERT IGNORE INTO cupones (codigo, descripcion, tipo_descuento, valor_descuento, minimo_compra, solo_primera_compra, activo) VALUES
('BIENVENIDO10', 'Descuento de bienvenida 10%', 'porcentaje', 10.00, 100.00, 1, 1),
('PRIMERACOMPRA', 'Primera compra $50 de descuento', 'monto_fijo', 50.00, 150.00, 1, 1);

SELECT 'Migración 028 completada: Sistema de cupones creado' AS resultado;
