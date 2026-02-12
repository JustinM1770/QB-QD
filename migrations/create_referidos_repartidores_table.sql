-- Script para crear las tablas de referidos y beneficios de repartidores
-- Sistema de gamificacion: Referidos dan bonificaciones en lugar de descuentos

-- Tabla de referidos entre repartidores
CREATE TABLE IF NOT EXISTS referidos_repartidores (
    id_referido INT AUTO_INCREMENT PRIMARY KEY,
    id_repartidor_referente INT NOT NULL COMMENT 'Repartidor que refiere',
    id_repartidor_referido INT NOT NULL COMMENT 'Repartidor que fue referido',
    fecha_referido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entregas_completadas INT DEFAULT 0 COMMENT 'Entregas completadas por el referido',
    activo TINYINT(1) DEFAULT 1 COMMENT 'Si el referido sigue activo como repartidor',
    bonificacion_otorgada TINYINT(1) DEFAULT 0 COMMENT 'Si ya se otorgo la bonificacion al referente',
    fecha_bonificacion DATETIME DEFAULT NULL COMMENT 'Fecha cuando se otorgo la bonificacion',
    FOREIGN KEY (id_repartidor_referente) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    FOREIGN KEY (id_repartidor_referido) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    UNIQUE KEY unique_referido_repartidor (id_repartidor_referente, id_repartidor_referido),
    INDEX idx_referente (id_repartidor_referente),
    INDEX idx_referido (id_repartidor_referido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de beneficios/bonificaciones de repartidores
CREATE TABLE IF NOT EXISTS beneficios_repartidores (
    id_beneficio INT AUTO_INCREMENT PRIMARY KEY,
    id_repartidor INT NOT NULL,
    tipo_beneficio ENUM('referido', 'nivel', 'meta_semanal', 'meta_mensual', 'bono_especial') NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    monto_bonificacion DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto en pesos de la bonificacion',
    estado ENUM('pendiente', 'aprobado', 'acreditado', 'rechazado', 'expirado') DEFAULT 'pendiente',
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME DEFAULT NULL,
    fecha_acreditacion DATETIME DEFAULT NULL,
    id_referido_relacionado INT DEFAULT NULL COMMENT 'Si es por referido, cual referido',
    notas TEXT DEFAULT NULL,
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    FOREIGN KEY (id_referido_relacionado) REFERENCES referidos_repartidores(id_referido) ON DELETE SET NULL,
    INDEX idx_repartidor (id_repartidor),
    INDEX idx_tipo (tipo_beneficio),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar columna de codigo de referido a la tabla repartidores si no existe
ALTER TABLE repartidores
ADD COLUMN IF NOT EXISTS codigo_referido VARCHAR(12) DEFAULT NULL COMMENT 'Codigo unico para referir nuevos repartidores',
ADD COLUMN IF NOT EXISTS total_referidos INT DEFAULT 0 COMMENT 'Total de repartidores referidos',
ADD COLUMN IF NOT EXISTS total_bonificaciones DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total acumulado de bonificaciones';

-- Crear indice para codigo de referido
CREATE INDEX IF NOT EXISTS idx_codigo_referido ON repartidores(codigo_referido);

-- Configuracion de bonificaciones (para que el CEO pueda ajustar valores)
CREATE TABLE IF NOT EXISTS config_bonificaciones_repartidor (
    id_config INT AUTO_INCREMENT PRIMARY KEY,
    tipo_bonificacion VARCHAR(50) NOT NULL UNIQUE,
    monto DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    requisito_minimo INT DEFAULT NULL COMMENT 'Ejemplo: minimo de entregas del referido',
    activo TINYINT(1) DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuracion inicial de bonificaciones
INSERT INTO config_bonificaciones_repartidor (tipo_bonificacion, monto, descripcion, requisito_minimo, activo) VALUES
('referido_nuevo', 50.00, 'Bonificacion por referir un nuevo repartidor que complete 10 entregas', 10, 1),
('referido_activo', 25.00, 'Bonificacion adicional cuando tu referido complete 50 entregas', 50, 1),
('referido_estrella', 100.00, 'Bonificacion especial cuando tu referido alcance nivel Oro', NULL, 1),
('meta_semanal_25', 30.00, 'Bonificacion por completar 25 entregas en una semana', 25, 1),
('meta_semanal_50', 75.00, 'Bonificacion por completar 50 entregas en una semana', 50, 1),
('meta_mensual_100', 150.00, 'Bonificacion por completar 100 entregas en un mes', 100, 1),
('bono_calificacion_5', 20.00, 'Bonificacion por mantener calificacion 5.0 en 20 entregas consecutivas', 20, 1)
ON DUPLICATE KEY UPDATE monto = VALUES(monto), descripcion = VALUES(descripcion);
