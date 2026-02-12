-- Script para crear la tabla referidos (referidos)
CREATE TABLE IF NOT EXISTS referidos (
    id_referido INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_referente INT NOT NULL,
    id_usuario_referido INT NOT NULL,
    fecha_referido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pedido_realizado TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_usuario_referente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_referido) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY unique_referido (id_usuario_referente, id_usuario_referido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
