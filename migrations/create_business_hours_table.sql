-- Tabla para almacenar los horarios de negocios
CREATE TABLE IF NOT EXISTS negocio_horarios (
    id_horario INT AUTO_INCREMENT PRIMARY KEY,
    id_negocio INT NOT NULL,
    dia_semana TINYINT NOT NULL CHECK (dia_semana BETWEEN 0 AND 6),
    hora_apertura TIME NOT NULL,
    hora_cierre TIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE,
    UNIQUE KEY unique_negocio_dia (id_negocio, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para mejorar el rendimiento
CREATE INDEX idx_negocio_horarios ON negocio_horarios(id_negocio, dia_semana, activo);
