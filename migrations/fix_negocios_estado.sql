-- =============================================
-- FIX: Corregir campo estado en tabla negocios
-- =============================================

-- Paso 1: Renombrar campo 'estado' a 'estado_geografico'
ALTER TABLE negocios 
CHANGE COLUMN estado estado_geografico VARCHAR(50) NOT NULL;

-- Paso 2: Agregar nuevo campo 'estado_operativo' con ENUM
ALTER TABLE negocios 
ADD COLUMN estado_operativo ENUM('activo', 'inactivo', 'suspendido', 'pendiente_aprobacion') 
NOT NULL DEFAULT 'activo' 
AFTER activo;

-- Paso 3: Migrar datos del campo 'activo' al nuevo 'estado_operativo'
-- Si activo = 1, entonces estado_operativo = 'activo'
-- Si activo = 0, entonces estado_operativo = 'inactivo'
UPDATE negocios 
SET estado_operativo = CASE 
    WHEN activo = 1 THEN 'activo'
    WHEN activo = 0 THEN 'inactivo'
    ELSE 'activo'
END;

-- Paso 4: Agregar índice para optimizar búsquedas por estado operativo
CREATE INDEX idx_estado_operativo ON negocios(estado_operativo);

-- Paso 5: Verificar cambios
SELECT 
    id_negocio,
    nombre,
    estado_geografico,
    activo,
    estado_operativo,
    ciudad
FROM negocios
ORDER BY id_negocio;

-- =============================================
-- NOTA: El campo 'activo' se mantiene por compatibilidad
-- pero ahora 'estado_operativo' es el campo oficial
-- =============================================
