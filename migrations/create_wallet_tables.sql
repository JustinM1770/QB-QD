-- Migración: Tablas de Wallet para MercadoPago
-- Fecha: 2025-11-02

-- Tabla principal de wallets
CREATE TABLE IF NOT EXISTS `wallets` (
  `id_wallet` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` INT(11) NOT NULL,
  `tipo_usuario` ENUM('business', 'courier') NOT NULL,
  `cuenta_externa_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID de cuenta en MercadoPago o Stripe',
  `estado` ENUM('activo', 'bloqueado', 'suspendido') DEFAULT 'activo',
  `saldo_disponible` DECIMAL(10,2) DEFAULT 0.00,
  `saldo_pendiente` DECIMAL(10,2) DEFAULT 0.00,
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_wallet`),
  KEY `idx_usuario` (`id_usuario`, `tipo_usuario`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones de wallet
CREATE TABLE IF NOT EXISTS `wallet_transacciones` (
  `id_transaccion` INT(11) NOT NULL AUTO_INCREMENT,
  `id_wallet` INT(11) NOT NULL,
  `tipo` ENUM('ingreso', 'retiro', 'comision', 'ajuste') NOT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `comision` DECIMAL(10,2) DEFAULT 0.00,
  `monto_neto` DECIMAL(10,2) NOT NULL,
  `descripcion` TEXT,
  `referencia_id` INT(11) DEFAULT NULL COMMENT 'ID del pedido relacionado',
  `tipo_referencia` VARCHAR(50) DEFAULT NULL COMMENT 'pedido, retiro, etc',
  `referencia_externa` VARCHAR(255) DEFAULT NULL COMMENT 'ID de transacción en MercadoPago',
  `estado` ENUM('pendiente', 'completado', 'cancelado', 'fallido') DEFAULT 'pendiente',
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_procesado` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_transaccion`),
  KEY `idx_wallet` (`id_wallet`),
  KEY `idx_estado` (`estado`),
  KEY `idx_referencia` (`referencia_id`, `tipo_referencia`),
  KEY `idx_fecha` (`fecha_creacion`),
  CONSTRAINT `fk_wallet_transaccion` FOREIGN KEY (`id_wallet`) REFERENCES `wallets` (`id_wallet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices adicionales para mejorar rendimiento (se crean solo si no existen)
-- Nota: Si ya existen, estos comandos fallarán silenciosamente
-- CREATE INDEX `idx_wallet_tipo_estado` ON `wallet_transacciones` (`id_wallet`, `tipo`, `estado`);
-- CREATE INDEX `idx_wallet_fecha` ON `wallet_transacciones` (`id_wallet`, `fecha_creacion` DESC);
