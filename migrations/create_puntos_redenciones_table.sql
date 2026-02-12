-- Migration to create puntos_redenciones table for tracking points redemptions by users

CREATE TABLE IF NOT EXISTS puntos_redenciones (
    id_redencion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo_redencion ENUM('cupon', 'producto_gratis', 'sorteo') NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    puntos_usados INT NOT NULL,
    fecha_redencion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
