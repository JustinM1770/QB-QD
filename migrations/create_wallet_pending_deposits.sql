-- =====================================================
-- QuickBite - Tabla de Depósitos Pendientes
-- Para gestionar depósitos OXXO/efectivo a la wallet
-- Fecha: 2025-11-20
-- =====================================================

CREATE TABLE IF NOT EXISTS `wallet_pending_deposits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('oxxo', 'paypal', 'spei', 'credit_card', 'seven_eleven') NOT NULL DEFAULT 'oxxo',
    `external_reference` VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID único del depósito',
    `mercadopago_payment_id` VARCHAR(100) NULL COMMENT 'ID del pago en MercadoPago',
    `status` ENUM('pending', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    `email` VARCHAR(255) NOT NULL,
    `ticket_url` VARCHAR(500) NULL COMMENT 'URL del ticket OXXO',
    `barcode` VARCHAR(100) NULL COMMENT 'Código de barras OXXO',
    `wallet_transaction_id` INT UNSIGNED NULL COMMENT 'ID de la transacción en wallet_transactions',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL COMMENT 'Fecha de expiración del ticket',
    `completed_at` TIMESTAMP NULL COMMENT 'Fecha en que se completó el pago',
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_external_ref` (`external_reference`),
    INDEX `idx_mp_payment` (`mercadopago_payment_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
