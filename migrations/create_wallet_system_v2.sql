-- =====================================================
-- QuickBite - Sistema de Wallet Digital v2.0
-- Fecha: 2025-11-20
-- Descripción: Tablas para sistema de billetera digital
--              con prevención de race conditions
-- =====================================================

-- Tabla principal de wallets
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `type` ENUM('repartidor', 'usuario', 'negocio') NOT NULL,
    `status` ENUM('active', 'suspended', 'blocked') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_wallet` (`user_id`, `type`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones de wallet
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `wallet_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `type` ENUM('credit', 'debit', 'refund', 'commission', 'withdrawal', 'deposit', 'tip', 'bonus', 'penalty') NOT NULL,
    `reference_id` VARCHAR(100) NULL COMMENT 'ID del pedido, pago, etc.',
    `reference_type` VARCHAR(50) NULL COMMENT 'order, payment, withdrawal, etc.',
    `description` VARCHAR(255) NULL,
    `balance_before` DECIMAL(10,2) NOT NULL,
    `balance_after` DECIMAL(10,2) NOT NULL,
    `metadata` JSON NULL COMMENT 'Datos adicionales de la transacción',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE RESTRICT,
    INDEX `idx_wallet_id` (`wallet_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_reference` (`reference_id`, `reference_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de límites de crédito para repartidores
CREATE TABLE IF NOT EXISTS `wallet_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `wallet_id` INT UNSIGNED NOT NULL,
    `credit_limit` DECIMAL(10,2) NOT NULL DEFAULT -200.00 COMMENT 'Límite negativo permitido',
    `daily_withdrawal_limit` DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_wallet_limit` (`wallet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista para consultas rápidas de balance
CREATE OR REPLACE VIEW `wallet_balances_view` AS
SELECT 
    w.id AS wallet_id,
    w.user_id,
    w.type,
    w.balance,
    w.status,
    COALESCE(wl.credit_limit, -200.00) AS credit_limit,
    w.created_at,
    w.updated_at
FROM wallets w
LEFT JOIN wallet_limits wl ON w.id = wl.wallet_id;
