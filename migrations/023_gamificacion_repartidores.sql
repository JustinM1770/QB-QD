-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN: Sistema de Gamificación para Repartidores
-- Descripción: Niveles Bronce, Plata, Oro con recompensas físicas
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. Crear tabla de niveles de repartidor
CREATE TABLE IF NOT EXISTS niveles_repartidor (
    id_nivel INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    color VARCHAR(20) NOT NULL,
    entregas_requeridas INT NOT NULL DEFAULT 0,
    calificacion_minima DECIMAL(3,2) DEFAULT NULL,
    recompensa VARCHAR(100) NOT NULL,
    descripcion TEXT,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar niveles predeterminados
INSERT INTO niveles_repartidor (nombre, emoji, color, entregas_requeridas, calificacion_minima, recompensa, descripcion, orden) VALUES
('Nuevo', '🆕', '#9CA3AF', 0, NULL, 'Bienvenida al equipo', 'Repartidor recién registrado', 0),
('Bronce', '🥉', '#CD7F32', 10, NULL, 'Chaleco Oficial QuickBite', 'Completa 10 entregas para ganar tu chaleco oficial', 1),
('Plata', '🥈', '#C0C0C0', 50, 4.0, 'Mochila Térmica Premium', 'Completa 50 entregas con buena calificación para ganar tu mochila', 2),
('Oro', '🥇', '#FFD700', 150, 4.5, 'Chip CFE TEIT Internet Ilimitado', 'Elite: 150 entregas + calificación 4.5+ = Internet gratis de por vida', 3),
('Diamante', '💎', '#00D4FF', 500, 4.8, 'Bono mensual + Seguro de gastos médicos', 'Los mejores repartidores con beneficios exclusivos', 4);

-- 3. Agregar columnas a tabla repartidores
ALTER TABLE repartidores
    ADD COLUMN IF NOT EXISTS id_nivel INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS total_entregas INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS calificacion_promedio DECIMAL(3,2) DEFAULT 5.00,
    ADD COLUMN IF NOT EXISTS fecha_nivel_actual DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS recompensa_reclamada TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS saldo_deuda DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS bloqueado_por_deuda TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS fecha_ultimo_nivel DATE DEFAULT NULL;

-- 4. Crear tabla de historial de niveles
CREATE TABLE IF NOT EXISTS historial_niveles_repartidor (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_repartidor INT NOT NULL,
    id_nivel_anterior INT,
    id_nivel_nuevo INT NOT NULL,
    entregas_al_subir INT NOT NULL,
    calificacion_al_subir DECIMAL(3,2),
    recompensa_otorgada VARCHAR(100),
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notas TEXT,
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    INDEX idx_repartidor_fecha (id_repartidor, fecha_cambio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Crear tabla de recompensas reclamadas
CREATE TABLE IF NOT EXISTS recompensas_repartidor (
    id_recompensa INT AUTO_INCREMENT PRIMARY KEY,
    id_repartidor INT NOT NULL,
    id_nivel INT NOT NULL,
    recompensa VARCHAR(100) NOT NULL,
    estado ENUM('pendiente', 'enviada', 'entregada', 'cancelada') DEFAULT 'pendiente',
    direccion_envio TEXT,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_envio DATE DEFAULT NULL,
    fecha_entrega DATE DEFAULT NULL,
    tracking_number VARCHAR(50) DEFAULT NULL,
    notas TEXT,
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    FOREIGN KEY (id_nivel) REFERENCES niveles_repartidor(id_nivel),
    INDEX idx_repartidor_estado (id_repartidor, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Crear tabla de deudas de comisiones (pagos en efectivo)
CREATE TABLE IF NOT EXISTS deudas_comisiones (
    id_deuda INT AUTO_INCREMENT PRIMARY KEY,
    id_repartidor INT NOT NULL,
    id_pedido INT NOT NULL,
    monto_comision DECIMAL(10,2) NOT NULL,
    monto_pagado DECIMAL(10,2) DEFAULT 0.00,
    estado ENUM('pendiente', 'parcial', 'pagada', 'condonada') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_pago DATE DEFAULT NULL,
    metodo_pago VARCHAR(50) DEFAULT NULL,
    notas TEXT,
    FOREIGN KEY (id_repartidor) REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    INDEX idx_repartidor_estado (id_repartidor, estado),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Crear vista para estadísticas de repartidores
CREATE OR REPLACE VIEW vista_repartidores_gamificacion AS
SELECT
    r.id_repartidor,
    r.id_usuario,
    u.nombre,
    u.apellido,
    u.email,
    u.telefono,
    r.total_entregas,
    r.calificacion_promedio,
    r.saldo_deuda,
    r.bloqueado_por_deuda,
    n.id_nivel,
    n.nombre as nivel_nombre,
    n.emoji as nivel_emoji,
    n.color as nivel_color,
    n.recompensa as nivel_recompensa,
    n.entregas_requeridas,
    n_siguiente.nombre as proximo_nivel,
    n_siguiente.entregas_requeridas as entregas_proximo_nivel,
    (n_siguiente.entregas_requeridas - r.total_entregas) as entregas_faltantes,
    CASE
        WHEN r.saldo_deuda <= -200 THEN 'bloqueado'
        WHEN r.saldo_deuda < 0 THEN 'deuda'
        ELSE 'activo'
    END as estado_cuenta
FROM repartidores r
JOIN usuarios u ON r.id_usuario = u.id_usuario
LEFT JOIN niveles_repartidor n ON r.id_nivel = n.id_nivel
LEFT JOIN niveles_repartidor n_siguiente ON n_siguiente.orden = (n.orden + 1)
WHERE r.activo = 1;

-- 8. Trigger para actualizar nivel automáticamente
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_pedido_entregado
AFTER UPDATE ON pedidos
FOR EACH ROW
BEGIN
    DECLARE v_id_repartidor INT;
    DECLARE v_total_entregas INT;
    DECLARE v_calificacion DECIMAL(3,2);
    DECLARE v_nivel_actual INT;
    DECLARE v_nuevo_nivel INT;

    -- Solo procesar si el pedido cambió a estado "entregado" (id_estado = 6)
    IF NEW.id_estado = 6 AND OLD.id_estado != 6 AND NEW.id_repartidor IS NOT NULL THEN
        SET v_id_repartidor = NEW.id_repartidor;

        -- Actualizar contador de entregas
        UPDATE repartidores
        SET total_entregas = total_entregas + 1
        WHERE id_repartidor = v_id_repartidor;

        -- Obtener datos actualizados
        SELECT total_entregas, calificacion_promedio, id_nivel
        INTO v_total_entregas, v_calificacion, v_nivel_actual
        FROM repartidores WHERE id_repartidor = v_id_repartidor;

        -- Buscar si califica para nuevo nivel
        SELECT id_nivel INTO v_nuevo_nivel
        FROM niveles_repartidor
        WHERE entregas_requeridas <= v_total_entregas
        AND (calificacion_minima IS NULL OR calificacion_minima <= v_calificacion)
        AND activo = 1
        ORDER BY entregas_requeridas DESC
        LIMIT 1;

        -- Si hay nuevo nivel y es diferente al actual
        IF v_nuevo_nivel IS NOT NULL AND v_nuevo_nivel != v_nivel_actual THEN
            -- Actualizar nivel del repartidor
            UPDATE repartidores
            SET id_nivel = v_nuevo_nivel,
                fecha_nivel_actual = CURDATE(),
                recompensa_reclamada = 0
            WHERE id_repartidor = v_id_repartidor;

            -- Registrar en historial
            INSERT INTO historial_niveles_repartidor
            (id_repartidor, id_nivel_anterior, id_nivel_nuevo, entregas_al_subir, calificacion_al_subir)
            VALUES (v_id_repartidor, v_nivel_actual, v_nuevo_nivel, v_total_entregas, v_calificacion);
        END IF;
    END IF;
END//
DELIMITER ;

-- 9. Trigger para bloquear repartidor por deuda
DELIMITER //
CREATE TRIGGER IF NOT EXISTS check_deuda_repartidor
BEFORE UPDATE ON repartidores
FOR EACH ROW
BEGIN
    -- Bloquear si la deuda llega a -$200 o más
    IF NEW.saldo_deuda <= -200 AND OLD.bloqueado_por_deuda = 0 THEN
        SET NEW.bloqueado_por_deuda = 1;
    END IF;

    -- Desbloquear si la deuda se paga
    IF NEW.saldo_deuda > -200 AND OLD.bloqueado_por_deuda = 1 THEN
        SET NEW.bloqueado_por_deuda = 0;
    END IF;
END//
DELIMITER ;

-- 10. Procedimiento para registrar deuda por pago en efectivo
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS registrar_deuda_efectivo(
    IN p_id_repartidor INT,
    IN p_id_pedido INT,
    IN p_monto_comision DECIMAL(10,2)
)
BEGIN
    -- Insertar deuda
    INSERT INTO deudas_comisiones (id_repartidor, id_pedido, monto_comision)
    VALUES (p_id_repartidor, p_id_pedido, p_monto_comision);

    -- Actualizar saldo de deuda del repartidor
    UPDATE repartidores
    SET saldo_deuda = saldo_deuda - p_monto_comision
    WHERE id_repartidor = p_id_repartidor;
END//
DELIMITER ;

-- 11. Procedimiento para pagar deuda
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS pagar_deuda_repartidor(
    IN p_id_repartidor INT,
    IN p_monto DECIMAL(10,2),
    IN p_metodo VARCHAR(50)
)
BEGIN
    DECLARE v_deuda_actual DECIMAL(10,2);

    -- Obtener deuda actual
    SELECT ABS(saldo_deuda) INTO v_deuda_actual
    FROM repartidores WHERE id_repartidor = p_id_repartidor;

    -- Si el monto es mayor a la deuda, ajustar
    IF p_monto > v_deuda_actual THEN
        SET p_monto = v_deuda_actual;
    END IF;

    -- Actualizar saldo (sumar porque es negativo)
    UPDATE repartidores
    SET saldo_deuda = saldo_deuda + p_monto
    WHERE id_repartidor = p_id_repartidor;

    -- Marcar deudas como pagadas (FIFO)
    UPDATE deudas_comisiones
    SET estado = 'pagada',
        fecha_pago = CURDATE(),
        metodo_pago = p_metodo
    WHERE id_repartidor = p_id_repartidor
    AND estado = 'pendiente'
    ORDER BY fecha_creacion ASC
    LIMIT 1;
END//
DELIMITER ;

-- 12. Índices adicionales para rendimiento
CREATE INDEX IF NOT EXISTS idx_repartidores_nivel ON repartidores(id_nivel);
CREATE INDEX IF NOT EXISTS idx_repartidores_deuda ON repartidores(saldo_deuda, bloqueado_por_deuda);
CREATE INDEX IF NOT EXISTS idx_pedidos_repartidor_estado ON pedidos(id_repartidor, id_estado);
