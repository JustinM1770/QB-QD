-- Tabla para almacenar logs de mensajes de WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message_type ENUM('template', 'text', 'interactive', 'image', 'document') DEFAULT 'text',
    status ENUM('sent', 'delivered', 'read', 'failed', 'pending') DEFAULT 'pending',
    whatsapp_message_id VARCHAR(100) NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_order_id (order_id),
    INDEX idx_message_id (whatsapp_message_id),
    INDEX idx_phone (phone_number),
    FOREIGN KEY (order_id) REFERENCES pedidos(id_pedido) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar campo de teléfono a la tabla de negocios si no existe
-- (para almacenar el número de WhatsApp del restaurante)
ALTER TABLE negocios 
ADD COLUMN IF NOT EXISTS whatsapp_phone VARCHAR(20) NULL AFTER telefono,
ADD INDEX idx_whatsapp_phone (whatsapp_phone);

-- Comentarios sobre el uso:
-- Esta tabla permite:
-- 1. Rastrear todos los mensajes enviados a restaurantes
-- 2. Relacionar mensajes de WhatsApp con pedidos específicos
-- 3. Monitorear el estado de entrega de cada mensaje
-- 4. Implementar reintento de mensajes fallidos
-- 5. Analítica sobre comunicación con restaurantes
