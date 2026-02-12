-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN: Sistema de Alianzas con Negocios Locales
-- Descripción: Negocios aliados que ofrecen descuentos a miembros QuickBite Club
-- Estrategia: Gym 20%, Doctor 15%, Estética 10%, etc.
-- Win-Win: Aliados ganan clientes, QuickBite gana valor para membresía
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. Crear tabla de categorías de aliados
CREATE TABLE IF NOT EXISTS categorias_aliados (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    icono VARCHAR(50),
    descripcion TEXT,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insertar categorías de aliados predeterminadas
INSERT INTO categorias_aliados (nombre, icono, descripcion, orden) VALUES
('Gimnasio', 'dumbbell', 'Gimnasios y centros deportivos', 1),
('Salud', 'stethoscope', 'Doctores, dentistas, clínicas', 2),
('Belleza', 'spa', 'Estéticas, salones de belleza, spas', 3),
('Entretenimiento', 'film', 'Cines, eventos, diversión', 4),
('Farmacia', 'pills', 'Farmacias y productos de salud', 5),
('Educación', 'graduation-cap', 'Cursos, talleres, escuelas', 6),
('Servicios', 'tools', 'Servicios generales', 7),
('Otros', 'ellipsis-h', 'Otros negocios', 99);

-- 3. Crear tabla de negocios aliados
CREATE TABLE IF NOT EXISTS negocios_aliados (
    id_aliado INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    id_categoria INT NOT NULL,
    descripcion TEXT,
    logo VARCHAR(255),
    imagen_portada VARCHAR(255),
    direccion VARCHAR(255),
    ciudad VARCHAR(100),
    telefono VARCHAR(20),
    email VARCHAR(100),
    sitio_web VARCHAR(255),
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    horario_atencion JSON,
    descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    tipo_descuento ENUM('porcentaje', 'monto_fijo', 'producto_gratis') DEFAULT 'porcentaje',
    monto_descuento DECIMAL(10,2) DEFAULT NULL,
    descripcion_descuento VARCHAR(255) NOT NULL,
    condiciones TEXT,
    limite_usos_mes INT DEFAULT NULL,
    solo_primera_vez TINYINT(1) DEFAULT 0,
    requiere_codigo TINYINT(1) DEFAULT 1,
    codigo_descuento VARCHAR(50),
    fecha_inicio_alianza DATE NOT NULL,
    fecha_fin_alianza DATE DEFAULT NULL,
    estado ENUM('activo', 'pausado', 'finalizado') DEFAULT 'activo',
    contacto_nombre VARCHAR(100),
    contacto_telefono VARCHAR(20),
    notas_internas TEXT,
    veces_usado INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES categorias_aliados(id_categoria),
    INDEX idx_categoria_estado (id_categoria, estado),
    INDEX idx_ciudad (ciudad),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Insertar ejemplos de aliados (para Teocaltiche - personalizar según negocios reales)
-- NOTA: Estos son ejemplos placeholder, se deben actualizar con negocios reales
INSERT INTO negocios_aliados (nombre, id_categoria, descripcion, descuento_porcentaje, descripcion_descuento, condiciones, fecha_inicio_alianza) VALUES
('Gym Ejemplo', 1, 'Gimnasio completo con aparatos y clases', 20.00, '20% en mensualidad', 'Válido para nuevos miembros. Mostrar membresía QuickBite Club activa.', CURDATE()),
('Dr. Ejemplo - Medicina General', 2, 'Consulta médica general', 15.00, '15% primera consulta', 'Solo primera consulta. Presentar membresía activa.', CURDATE()),
('Estética Ejemplo', 3, 'Cortes, tintes, manicure y más', 10.00, '10% en todos los servicios', 'No acumulable con otras promociones.', CURDATE());

-- 5. Crear tabla de uso de beneficios (tracking)
CREATE TABLE IF NOT EXISTS uso_beneficios_aliados (
    id_uso INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_aliado INT NOT NULL,
    codigo_usado VARCHAR(50),
    monto_original DECIMAL(10,2),
    descuento_aplicado DECIMAL(10,2),
    monto_final DECIMAL(10,2),
    estado ENUM('pendiente', 'verificado', 'rechazado') DEFAULT 'pendiente',
    notas TEXT,
    fecha_uso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_verificacion DATETIME DEFAULT NULL,
    verificado_por VARCHAR(100),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_aliado) REFERENCES negocios_aliados(id_aliado),
    INDEX idx_usuario (id_usuario),
    INDEX idx_aliado (id_aliado),
    INDEX idx_fecha (fecha_uso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Crear tabla de códigos de descuento generados
CREATE TABLE IF NOT EXISTS codigos_descuento_aliados (
    id_codigo INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_aliado INT NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATE NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    fecha_uso DATETIME DEFAULT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_aliado) REFERENCES negocios_aliados(id_aliado),
    INDEX idx_codigo (codigo),
    INDEX idx_usuario_aliado (id_usuario, id_aliado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Trigger para incrementar contador de usos del aliado
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_uso_beneficio_insert
AFTER INSERT ON uso_beneficios_aliados
FOR EACH ROW
BEGIN
    IF NEW.estado = 'verificado' THEN
        UPDATE negocios_aliados
        SET veces_usado = veces_usado + 1
        WHERE id_aliado = NEW.id_aliado;

        -- Registrar como ahorro del miembro
        INSERT INTO quickbite_club_ahorros (id_usuario, tipo_ahorro, monto_ahorrado, descripcion)
        VALUES (
            NEW.id_usuario,
            'descuento_aliado',
            NEW.descuento_aplicado,
            (SELECT CONCAT('Descuento en ', nombre) FROM negocios_aliados WHERE id_aliado = NEW.id_aliado)
        );
    END IF;
END//
DELIMITER ;

-- 8. Vista de aliados activos con estadísticas
CREATE OR REPLACE VIEW vista_aliados_activos AS
SELECT
    na.id_aliado,
    na.nombre,
    ca.nombre as categoria,
    ca.icono as categoria_icono,
    na.descripcion,
    na.logo,
    na.direccion,
    na.ciudad,
    na.descuento_porcentaje,
    na.descripcion_descuento,
    na.condiciones,
    na.veces_usado,
    na.estado,
    (SELECT COUNT(*) FROM uso_beneficios_aliados WHERE id_aliado = na.id_aliado AND MONTH(fecha_uso) = MONTH(CURDATE())) as usos_este_mes
FROM negocios_aliados na
JOIN categorias_aliados ca ON na.id_categoria = ca.id_categoria
WHERE na.estado = 'activo'
    AND (na.fecha_fin_alianza IS NULL OR na.fecha_fin_alianza >= CURDATE())
ORDER BY ca.orden, na.nombre;

-- 9. Procedimiento para generar código de descuento para usuario
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS generar_codigo_aliado(
    IN p_id_usuario INT,
    IN p_id_aliado INT,
    OUT p_codigo VARCHAR(20)
)
BEGIN
    DECLARE v_es_miembro TINYINT;
    DECLARE v_limite INT;
    DECLARE v_usos_mes INT;
    DECLARE v_solo_primera_vez TINYINT;
    DECLARE v_ya_uso TINYINT;

    -- Verificar si es miembro Club
    SELECT es_miembro_club INTO v_es_miembro FROM usuarios WHERE id_usuario = p_id_usuario;

    IF v_es_miembro != 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Usuario no es miembro QuickBite Club';
    END IF;

    -- Obtener restricciones del aliado
    SELECT limite_usos_mes, solo_primera_vez INTO v_limite, v_solo_primera_vez
    FROM negocios_aliados WHERE id_aliado = p_id_aliado;

    -- Verificar si solo primera vez
    IF v_solo_primera_vez = 1 THEN
        SELECT COUNT(*) INTO v_ya_uso
        FROM uso_beneficios_aliados
        WHERE id_usuario = p_id_usuario AND id_aliado = p_id_aliado AND estado = 'verificado';

        IF v_ya_uso > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este beneficio solo es válido la primera vez';
        END IF;
    END IF;

    -- Verificar límite mensual si existe
    IF v_limite IS NOT NULL THEN
        SELECT COUNT(*) INTO v_usos_mes
        FROM uso_beneficios_aliados
        WHERE id_usuario = p_id_usuario
            AND id_aliado = p_id_aliado
            AND MONTH(fecha_uso) = MONTH(CURDATE())
            AND YEAR(fecha_uso) = YEAR(CURDATE());

        IF v_usos_mes >= v_limite THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Has alcanzado el límite de usos este mes';
        END IF;
    END IF;

    -- Generar código único
    SET p_codigo = CONCAT('QB', UPPER(SUBSTRING(MD5(CONCAT(p_id_usuario, p_id_aliado, NOW(), RAND())), 1, 8)));

    -- Guardar código
    INSERT INTO codigos_descuento_aliados (id_usuario, id_aliado, codigo, fecha_expiracion)
    VALUES (p_id_usuario, p_id_aliado, p_codigo, DATE_ADD(CURDATE(), INTERVAL 7 DAY));

END//
DELIMITER ;

-- 10. Procedimiento para validar código usado
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS validar_codigo_aliado(
    IN p_codigo VARCHAR(20),
    IN p_monto_original DECIMAL(10,2),
    OUT p_valido TINYINT,
    OUT p_descuento DECIMAL(10,2),
    OUT p_mensaje VARCHAR(255)
)
BEGIN
    DECLARE v_id_codigo INT;
    DECLARE v_id_usuario INT;
    DECLARE v_id_aliado INT;
    DECLARE v_usado TINYINT;
    DECLARE v_expirado TINYINT;
    DECLARE v_descuento_pct DECIMAL(5,2);

    SET p_valido = 0;
    SET p_descuento = 0;

    -- Buscar código
    SELECT
        cd.id_codigo, cd.id_usuario, cd.id_aliado, cd.usado,
        (cd.fecha_expiracion < CURDATE()) as expirado,
        na.descuento_porcentaje
    INTO v_id_codigo, v_id_usuario, v_id_aliado, v_usado, v_expirado, v_descuento_pct
    FROM codigos_descuento_aliados cd
    JOIN negocios_aliados na ON cd.id_aliado = na.id_aliado
    WHERE cd.codigo = p_codigo;

    IF v_id_codigo IS NULL THEN
        SET p_mensaje = 'Código no encontrado';
    ELSEIF v_usado = 1 THEN
        SET p_mensaje = 'Código ya fue utilizado';
    ELSEIF v_expirado = 1 THEN
        SET p_mensaje = 'Código expirado';
    ELSE
        SET p_valido = 1;
        SET p_descuento = p_monto_original * (v_descuento_pct / 100);
        SET p_mensaje = 'Código válido';

        -- Marcar como usado
        UPDATE codigos_descuento_aliados SET usado = 1, fecha_uso = NOW() WHERE id_codigo = v_id_codigo;

        -- Registrar uso
        INSERT INTO uso_beneficios_aliados (id_usuario, id_aliado, codigo_usado, monto_original, descuento_aplicado, monto_final, estado)
        VALUES (v_id_usuario, v_id_aliado, p_codigo, p_monto_original, p_descuento, p_monto_original - p_descuento, 'verificado');
    END IF;
END//
DELIMITER ;
