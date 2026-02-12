-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN: Modelo de Comisiones QuickBite (Compatible con sistema existente)
--
-- RESUMEN DEL MODELO:
-- - Negocios: 10% comisión básica, 8% con Premium ($199/mes)
-- - Clientes: Cargo servicio $5, envío mínimo $25 (gratis para miembros)
-- - Repartidores: Mínimo $25 garantizado + 100% propinas
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. Agregar campos de membresía premium a negocios
-- Usamos procedimiento para verificar si columna existe

DELIMITER //

CREATE PROCEDURE add_column_if_not_exists()
BEGIN
    -- Agregar es_premium a negocios
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'negocios'
                   AND COLUMN_NAME = 'es_premium') THEN
        ALTER TABLE negocios ADD COLUMN es_premium TINYINT(1) DEFAULT 0 COMMENT 'Si tiene membresía premium activa';
    END IF;

    -- Agregar comision_porcentaje a negocios
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'negocios'
                   AND COLUMN_NAME = 'comision_porcentaje') THEN
        ALTER TABLE negocios ADD COLUMN comision_porcentaje DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Comisión actual: 10% básico, 8% premium';
    END IF;

    -- Agregar fecha_inicio_premium a negocios
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'negocios'
                   AND COLUMN_NAME = 'fecha_inicio_premium') THEN
        ALTER TABLE negocios ADD COLUMN fecha_inicio_premium DATE DEFAULT NULL;
    END IF;

    -- Agregar fecha_fin_premium a negocios
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'negocios'
                   AND COLUMN_NAME = 'fecha_fin_premium') THEN
        ALTER TABLE negocios ADD COLUMN fecha_fin_premium DATE DEFAULT NULL;
    END IF;

    -- Agregar comision_plataforma a pedidos
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedidos'
                   AND COLUMN_NAME = 'comision_plataforma') THEN
        ALTER TABLE pedidos ADD COLUMN comision_plataforma DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Comisión cobrada a negocio';
    END IF;

    -- Agregar comision_porcentaje a pedidos
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedidos'
                   AND COLUMN_NAME = 'comision_porcentaje') THEN
        ALTER TABLE pedidos ADD COLUMN comision_porcentaje DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Porcentaje aplicado';
    END IF;

    -- Agregar pago_negocio a pedidos
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedidos'
                   AND COLUMN_NAME = 'pago_negocio') THEN
        ALTER TABLE pedidos ADD COLUMN pago_negocio DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Monto que recibe el negocio';
    END IF;

    -- Agregar pago_repartidor a pedidos
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedidos'
                   AND COLUMN_NAME = 'pago_repartidor') THEN
        ALTER TABLE pedidos ADD COLUMN pago_repartidor DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Monto que recibe el repartidor';
    END IF;

    -- Agregar subsidio_envio a pedidos
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedidos'
                   AND COLUMN_NAME = 'subsidio_envio') THEN
        ALTER TABLE pedidos ADD COLUMN subsidio_envio DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Envío subsidiado para miembros';
    END IF;

    -- Agregar ahorro_total_membresia a usuarios
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'usuarios'
                   AND COLUMN_NAME = 'ahorro_total_membresia') THEN
        ALTER TABLE usuarios ADD COLUMN ahorro_total_membresia DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total ahorrado por ser miembro';
    END IF;
END//

DELIMITER ;

-- Ejecutar el procedimiento
CALL add_column_if_not_exists();

-- Eliminar el procedimiento temporal
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- 2. Crear tabla de membresías de negocios (planes premium)
CREATE TABLE IF NOT EXISTS membresias_negocios (
    id_membresia INT AUTO_INCREMENT PRIMARY KEY,
    id_negocio INT NOT NULL,
    plan ENUM('basico', 'premium') DEFAULT 'basico',
    precio_pagado DECIMAL(10,2) DEFAULT 0.00,
    comision_porcentaje DECIMAL(5,2) DEFAULT 10.00,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('activa', 'cancelada', 'expirada') DEFAULT 'activa',
    metodo_pago VARCHAR(50),
    referencia_pago VARCHAR(100),
    auto_renovar TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_negocio_estado (id_negocio, estado),
    INDEX idx_fecha_fin (fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crear tabla de ahorro para miembros
CREATE TABLE IF NOT EXISTS ahorro_miembros (
    id_ahorro INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_pedido INT,
    tipo_ahorro ENUM('envio', 'cargo_servicio', 'descuento') NOT NULL,
    monto_ahorrado DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255),
    fecha_ahorro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_fecha (id_usuario, fecha_ahorro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Vista para calcular comisiones de pedidos
DROP VIEW IF EXISTS vista_comisiones_pedidos;
CREATE VIEW vista_comisiones_pedidos AS
SELECT
    p.id_pedido,
    p.id_negocio,
    n.nombre as nombre_negocio,
    n.es_premium,
    p.total_productos,
    p.costo_envio,
    p.cargo_servicio,
    p.propina,
    p.monto_total,
    CASE WHEN n.es_premium = 1 THEN 8.00 ELSE 10.00 END as comision_porcentaje,
    p.total_productos * (CASE WHEN n.es_premium = 1 THEN 0.08 ELSE 0.10 END) as comision_calculada,
    p.total_productos - (p.total_productos * (CASE WHEN n.es_premium = 1 THEN 0.08 ELSE 0.10 END)) as pago_negocio_calculado,
    p.fecha_creacion
FROM pedidos p
JOIN negocios n ON p.id_negocio = n.id_negocio;

-- 5. Vista resumen de comisiones por negocio
DROP VIEW IF EXISTS vista_resumen_comisiones_negocio;
CREATE VIEW vista_resumen_comisiones_negocio AS
SELECT
    n.id_negocio,
    n.nombre,
    n.es_premium,
    CASE WHEN n.es_premium = 1 THEN 8.00 ELSE 10.00 END as comision_actual,
    COUNT(p.id_pedido) as total_pedidos,
    SUM(p.total_productos) as ventas_totales,
    SUM(p.total_productos * (CASE WHEN n.es_premium = 1 THEN 0.08 ELSE 0.10 END)) as comisiones_totales,
    SUM(p.total_productos - (p.total_productos * (CASE WHEN n.es_premium = 1 THEN 0.08 ELSE 0.10 END))) as ganancias_netas
FROM negocios n
LEFT JOIN pedidos p ON n.id_negocio = p.id_negocio AND p.id_estado = 6
GROUP BY n.id_negocio;

-- 6. Procedimiento para activar membresía premium de negocio
DROP PROCEDURE IF EXISTS activar_membresia_negocio;
DELIMITER //
CREATE PROCEDURE activar_membresia_negocio(
    IN p_id_negocio INT,
    IN p_metodo_pago VARCHAR(50),
    IN p_referencia_pago VARCHAR(100)
)
BEGIN
    DECLARE v_precio_premium DECIMAL(10,2) DEFAULT 199.00;
    DECLARE v_comision_premium DECIMAL(5,2) DEFAULT 8.00;

    -- Crear membresía
    INSERT INTO membresias_negocios (
        id_negocio, plan, precio_pagado, comision_porcentaje,
        fecha_inicio, fecha_fin, estado, metodo_pago, referencia_pago
    ) VALUES (
        p_id_negocio, 'premium', v_precio_premium, v_comision_premium,
        CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'activa',
        p_metodo_pago, p_referencia_pago
    );

    -- Actualizar negocio
    UPDATE negocios
    SET es_premium = 1,
        comision_porcentaje = v_comision_premium,
        fecha_inicio_premium = CURDATE(),
        fecha_fin_premium = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
    WHERE id_negocio = p_id_negocio;
END//
DELIMITER ;

-- 7. Procedimiento para verificar membresías expiradas
DROP PROCEDURE IF EXISTS verificar_membresias_expiradas_negocios;
DELIMITER //
CREATE PROCEDURE verificar_membresias_expiradas_negocios()
BEGIN
    -- Marcar membresías expiradas
    UPDATE membresias_negocios
    SET estado = 'expirada'
    WHERE estado = 'activa' AND fecha_fin < CURDATE();

    -- Resetear negocios con membresía expirada a básico
    UPDATE negocios n
    INNER JOIN membresias_negocios mn ON n.id_negocio = mn.id_negocio
    SET n.es_premium = 0,
        n.comision_porcentaje = 10.00,
        n.fecha_inicio_premium = NULL,
        n.fecha_fin_premium = NULL
    WHERE mn.estado = 'expirada' AND n.es_premium = 1;
END//
DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN COMPLETADA
-- ═══════════════════════════════════════════════════════════════════════════
SELECT 'Migración 022_modelo_comisiones_quickbite completada exitosamente' as resultado;
