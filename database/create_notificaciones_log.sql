-- Tabla para historial de notificaciones push enviadas
CREATE TABLE IF NOT EXISTS notificaciones_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    segmento VARCHAR(50) DEFAULT 'todos',
    enviados INT DEFAULT 0,
    fallidos INT DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentario para referencia
-- Esta tabla registra todas las notificaciones push masivas enviadas
-- segmento: 'todos', 'activos', 'premium'
