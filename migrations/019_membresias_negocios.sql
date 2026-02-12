-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN: Sistema de Membresías para Negocios
-- Descripción: Membresías Premium opcionales para negocios
-- Modelo: Comisión 10% básica → 8% con Premium ($199/mes)
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. Crear tabla de planes de membresía para negocios
CREATE TABLE IF NOT EXISTS planes_membresia_negocio (
    id_plan INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    precio_mensual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    comision_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    caracteristicas JSON,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar planes predeterminados
INSERT INTO planes_membresia_negocio (nombre, descripcion, precio_mensual, comision_porcentaje, caracteristicas, orden) VALUES
('Básico', 'Plan gratuito con todas las funciones esenciales', 0.00, 10.00,
    '{"aparecer_en_app": true, "recibir_pedidos": true, "panel_admin": true, "reportes_basicos": true, "whatsapp_bot": false, "ia_menu": false, "badge_premium": false, "prioridad_busqueda": false}',
    1),
('Premium', 'Comisión reducida + herramientas avanzadas', 199.00, 8.00,
    '{"aparecer_en_app": true, "recibir_pedidos": true, "panel_admin": true, "reportes_basicos": true, "reportes_avanzados": true, "whatsapp_bot": true, "ia_menu": true, "badge_premium": true, "prioridad_busqueda": true, "soporte_prioritario": true}',
    2);

-- 3. Crear tabla de membresías activas de negocios
CREATE TABLE IF NOT EXISTS membresias_negocios (
    id_membresia INT AUTO_INCREMENT PRIMARY KEY,
    id_negocio INT NOT NULL,
    id_plan INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('activa', 'cancelada', 'expirada', 'pendiente_pago') DEFAULT 'activa',
    metodo_pago VARCHAR(50),
    referencia_pago VARCHAR(100),
    auto_renovar TINYINT(1) DEFAULT 1,
    monto_pagado DECIMAL(10,2),
    notas TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE,
    FOREIGN KEY (id_plan) REFERENCES planes_membresia_negocio(id_plan),
    INDEX idx_negocio_estado (id_negocio, estado),
    INDEX idx_fecha_fin (fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Agregar columna de membresía a tabla negocios (si no existe)
ALTER TABLE negocios
    ADD COLUMN IF NOT EXISTS id_membresia_activa INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS es_premium TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS comision_actual DECIMAL(5,2) DEFAULT 10.00,
    ADD COLUMN IF NOT EXISTS fecha_inicio_premium DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS fecha_fin_premium DATE DEFAULT NULL;

-- 5. Crear índice para búsquedas de negocios premium
CREATE INDEX IF NOT EXISTS idx_negocios_premium ON negocios(es_premium, activo);

-- 6. Crear tabla de historial de pagos de membresías
CREATE TABLE IF NOT EXISTS pagos_membresias_negocios (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_membresia INT NOT NULL,
    id_negocio INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    referencia_externa VARCHAR(100),
    estado ENUM('pendiente', 'completado', 'fallido', 'reembolsado') DEFAULT 'pendiente',
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    detalles JSON,
    FOREIGN KEY (id_membresia) REFERENCES membresias_negocios(id_membresia),
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE,
    INDEX idx_negocio_fecha (id_negocio, fecha_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Crear vista para negocios con su membresía actual
CREATE OR REPLACE VIEW vista_negocios_membresia AS
SELECT
    n.id_negocio,
    n.nombre,
    n.es_premium,
    n.comision_actual,
    COALESCE(pm.nombre, 'Básico') as nombre_plan,
    COALESCE(pm.precio_mensual, 0) as precio_plan,
    mn.fecha_inicio,
    mn.fecha_fin,
    mn.estado as estado_membresia,
    CASE
        WHEN mn.estado = 'activa' AND mn.fecha_fin >= CURDATE() THEN 'vigente'
        WHEN mn.estado = 'activa' AND mn.fecha_fin < CURDATE() THEN 'por_renovar'
        ELSE 'sin_premium'
    END as estado_premium
FROM negocios n
LEFT JOIN membresias_negocios mn ON n.id_negocio = mn.id_negocio AND mn.estado = 'activa'
LEFT JOIN planes_membresia_negocio pm ON mn.id_plan = pm.id_plan;

-- 8. Trigger para actualizar estado premium del negocio
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_membresia_negocio_insert
AFTER INSERT ON membresias_negocios
FOR EACH ROW
BEGIN
    IF NEW.estado = 'activa' THEN
        UPDATE negocios
        SET es_premium = 1,
            comision_actual = (SELECT comision_porcentaje FROM planes_membresia_negocio WHERE id_plan = NEW.id_plan),
            id_membresia_activa = NEW.id_membresia,
            fecha_inicio_premium = NEW.fecha_inicio,
            fecha_fin_premium = NEW.fecha_fin
        WHERE id_negocio = NEW.id_negocio;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS after_membresia_negocio_update
AFTER UPDATE ON membresias_negocios
FOR EACH ROW
BEGIN
    IF NEW.estado != 'activa' OR NEW.fecha_fin < CURDATE() THEN
        UPDATE negocios
        SET es_premium = 0,
            comision_actual = 10.00,
            id_membresia_activa = NULL,
            fecha_inicio_premium = NULL,
            fecha_fin_premium = NULL
        WHERE id_negocio = NEW.id_negocio AND id_membresia_activa = NEW.id_membresia;
    ELSEIF NEW.estado = 'activa' THEN
        UPDATE negocios
        SET es_premium = 1,
            comision_actual = (SELECT comision_porcentaje FROM planes_membresia_negocio WHERE id_plan = NEW.id_plan),
            id_membresia_activa = NEW.id_membresia,
            fecha_inicio_premium = NEW.fecha_inicio,
            fecha_fin_premium = NEW.fecha_fin
        WHERE id_negocio = NEW.id_negocio;
    END IF;
END//
DELIMITER ;

-- 9. Procedimiento para verificar membresías expiradas (ejecutar diariamente con cron)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS verificar_membresias_expiradas()
BEGIN
    -- Marcar membresías expiradas
    UPDATE membresias_negocios
    SET estado = 'expirada'
    WHERE estado = 'activa' AND fecha_fin < CURDATE();

    -- Actualizar negocios con membresías expiradas
    UPDATE negocios n
    INNER JOIN membresias_negocios mn ON n.id_membresia_activa = mn.id_membresia
    SET n.es_premium = 0,
        n.comision_actual = 10.00,
        n.id_membresia_activa = NULL,
        n.fecha_inicio_premium = NULL,
        n.fecha_fin_premium = NULL
    WHERE mn.estado = 'expirada';
END//
DELIMITER ;

-- 10. Evento programado para verificar membresías expiradas diariamente
-- Nota: Asegúrate de que event_scheduler esté habilitado en MySQL
-- SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS evt_verificar_membresias_negocios
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 1 HOUR)
DO CALL verificar_membresias_expiradas();
