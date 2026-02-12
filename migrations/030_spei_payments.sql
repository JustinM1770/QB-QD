-- =====================================================
-- Migración: Sistema de Pagos SPEI (Transferencia Bancaria)
-- Fecha: 2026-01-24
-- Descripción: Crea la tabla para rastrear pagos SPEI de MercadoPago
-- =====================================================

-- Tabla para almacenar pagos SPEI pendientes y completados
CREATE TABLE IF NOT EXISTS spei_payments (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único del registro',
    pedido_id INT NOT NULL COMMENT 'ID del pedido asociado',
    mercadopago_payment_id VARCHAR(50) NOT NULL COMMENT 'ID del pago en MercadoPago',
    external_reference VARCHAR(100) NOT NULL COMMENT 'Referencia externa única (QB_SPEI_xxx)',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Monto del pago',
    email VARCHAR(255) NOT NULL COMMENT 'Email del pagador',
    clabe VARCHAR(20) DEFAULT NULL COMMENT 'CLABE interbancaria para la transferencia',
    bank_info JSON DEFAULT NULL COMMENT 'Información bancaria completa (JSON)',
    ticket_url TEXT DEFAULT NULL COMMENT 'URL del comprobante/ticket de pago',
    status VARCHAR(30) DEFAULT 'pending' COMMENT 'Estado: pending, in_process, approved, rejected, cancelled, refunded',
    status_detail VARCHAR(100) DEFAULT NULL COMMENT 'Detalle del estado de MercadoPago',
    expires_at DATETIME DEFAULT NULL COMMENT 'Fecha y hora de expiración del pago',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',

    -- Índices para optimización de consultas
    INDEX idx_spei_pedido (pedido_id),
    INDEX idx_spei_mp_payment (mercadopago_payment_id),
    INDEX idx_spei_external_ref (external_reference),
    INDEX idx_spei_status (status),
    INDEX idx_spei_created (created_at),
    INDEX idx_spei_expires (expires_at),

    -- Clave foránea al pedido
    CONSTRAINT fk_spei_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id_pedido)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de pagos SPEI (transferencia bancaria) via MercadoPago';

-- Agregar columnas necesarias a la tabla pedidos si no existen
-- Estas columnas permiten almacenar información del método de pago

-- Columna para referencia externa (si no existe)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'pedidos'
     AND COLUMN_NAME = 'referencia_externa') = 0,
    'ALTER TABLE pedidos ADD COLUMN referencia_externa VARCHAR(100) DEFAULT NULL COMMENT ''Referencia externa del pago (QB_xxx)'' AFTER payment_id',
    'SELECT ''Column referencia_externa already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Columna para estado de pago detallado (si no existe)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'pedidos'
     AND COLUMN_NAME = 'payment_status_detail') = 0,
    'ALTER TABLE pedidos ADD COLUMN payment_status_detail VARCHAR(100) DEFAULT NULL COMMENT ''Detalle del estado de pago'' AFTER payment_status',
    'SELECT ''Column payment_status_detail already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para referencia externa en pedidos
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'pedidos'
     AND INDEX_NAME = 'idx_pedidos_ref_externa') = 0,
    'ALTER TABLE pedidos ADD INDEX idx_pedidos_ref_externa (referencia_externa)',
    'SELECT ''Index idx_pedidos_ref_externa already exists'''
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Comentarios de uso:
-- =====================================================
-- El flujo de pago SPEI funciona así:
--
-- 1. Usuario selecciona "Transferencia SPEI" en checkout
-- 2. Sistema crea pago en MercadoPago con payment_method_id = "bank_transfer"
-- 3. MercadoPago devuelve CLABE y datos bancarios
-- 4. Usuario realiza transferencia desde su banco
-- 5. MercadoPago notifica via webhook cuando el pago se acredita
-- 6. Sistema actualiza estado del pedido a "pagado"
--
-- Estados posibles:
-- - pending: Esperando transferencia del usuario
-- - in_process: Transferencia recibida, en proceso de validación
-- - approved: Pago confirmado
-- - rejected: Transferencia rechazada
-- - cancelled: Pago cancelado por usuario o sistema
-- - refunded: Pago devuelto
-- =====================================================
