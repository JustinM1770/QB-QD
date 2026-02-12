-- Agregar campos de cuenta bancaria a la tabla negocios
ALTER TABLE negocios 
ADD COLUMN cuenta_clabe VARCHAR(18) DEFAULT NULL,
ADD COLUMN banco VARCHAR(100) DEFAULT NULL,
ADD COLUMN titular_cuenta VARCHAR(255) DEFAULT NULL,
ADD COLUMN rfc_banco VARCHAR(13) DEFAULT NULL,
ADD COLUMN fecha_verificacion_cuenta DATETIME DEFAULT NULL,
ADD COLUMN cuenta_verificada BOOLEAN DEFAULT FALSE,
ADD COLUMN tipo_cuenta ENUM('ahorro', 'cheques') DEFAULT 'cheques';

-- Índice para búsqueda rápida
CREATE INDEX idx_negocio_cuenta_clabe ON negocios(cuenta_clabe);

-- Tabla para historial de retiros de negocios
CREATE TABLE IF NOT EXISTS retiros_negocio (
    id_retiro INT AUTO_INCREMENT PRIMARY KEY,
    id_negocio INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_procesamiento DATETIME NULL,
    estado ENUM('pendiente', 'procesando', 'completado', 'rechazado') DEFAULT 'pendiente',
    cuenta_clabe VARCHAR(18) NOT NULL,
    banco VARCHAR(100) NOT NULL,
    referencia_pago VARCHAR(100) NULL,
    notas TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE,
    INDEX idx_negocio_retiro (id_negocio),
    INDEX idx_estado_retiro (estado)
);

-- Tabla para configuración de métodos de pago
CREATE TABLE IF NOT EXISTS metodos_pago_negocio (
    id_metodo INT AUTO_INCREMENT PRIMARY KEY,
    id_negocio INT NOT NULL,
    tipo_metodo ENUM('tarjeta', 'transferencia', 'efectivo') DEFAULT 'tarjeta',
    proveedor_pago VARCHAR(50) DEFAULT 'stripe',
    clave_publica VARCHAR(255) NULL,
    clave_secreta VARCHAR(255) NULL,
    webhook_url VARCHAR(255) NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_negocio) REFERENCES negocios(id_negocio) ON DELETE CASCADE,
    INDEX idx_negocio_metodo (id_negocio)
);
