-- =============================================
-- FIX: Agregar campo estado_operativo a negocios
-- =============================================

-- Paso 1: Agregar nuevo campo 'estado_operativo' con ENUM
ALTER TABLE negocios 
ADD COLUMN estado_operativo ENUM('activo', 'inactivo', 'suspendido', 'pendiente_aprobacion') 
NOT NULL DEFAULT 'activo' 
AFTER activo;

-- Paso 2: Migrar datos del campo 'activo' al nuevo 'estado_operativo'
UPDATE negocios 
SET estado_operativo = CASE 
    WHEN activo = 1 THEN 'activo'
    WHEN activo = 0 THEN 'inactivo'
    ELSE 'activo'
END;

-- Paso 3: Agregar índice para optimizar búsquedas
CREATE INDEX idx_estado_operativo ON negocios(estado_operativo);

-- Paso 4: Verificar resultado
SELECT 
    id_negocio,
    nombre,
    estado_geografico,
    activo,
    estado_operativo,
    ciudad
FROM negocios
ORDER BY id_negocio;
