-- =====================================================================
-- QuickBite - Migración para nivel Rappi
-- Fecha: 2026-02-12
-- Descripción: Tablas nuevas, columnas faltantes e índices para
--              multi-ciudad, chat, disputas, notificaciones, surge,
--              tracking GPS, preferencias, auditoría y más.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. SISTEMA MULTI-CIUDAD / MULTI-ESTADO / MUNICIPIOS
-- =====================================================================

-- Tabla de países (para futura expansión)
CREATE TABLE IF NOT EXISTS `paises` (
  `id_pais` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo_iso` char(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2',
  `moneda` varchar(10) DEFAULT 'MXN',
  `prefijo_telefono` varchar(5) DEFAULT '+52',
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_pais`),
  UNIQUE KEY `uk_codigo_iso` (`codigo_iso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de estados/entidades federativas
CREATE TABLE IF NOT EXISTS `estados` (
  `id_estado_geo` int NOT NULL AUTO_INCREMENT,
  `id_pais` int NOT NULL DEFAULT 1,
  `nombre` varchar(100) NOT NULL,
  `abreviatura` varchar(10) DEFAULT NULL COMMENT 'Ej: AGS, GTO, JAL',
  `activo` tinyint(1) DEFAULT '0' COMMENT '1 = QuickBite opera aquí',
  `fecha_lanzamiento` date DEFAULT NULL COMMENT 'Fecha en que se lanzó en este estado',
  `orden` int DEFAULT '0',
  PRIMARY KEY (`id_estado_geo`),
  KEY `idx_pais` (`id_pais`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `fk_estados_pais` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id_pais`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de municipios/ciudades
CREATE TABLE IF NOT EXISTS `municipios` (
  `id_municipio` int NOT NULL AUTO_INCREMENT,
  `id_estado_geo` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(120) DEFAULT NULL COMMENT 'URL amigable: aguascalientes, leon, etc.',
  `latitud_centro` decimal(10,8) DEFAULT NULL,
  `longitud_centro` decimal(11,8) DEFAULT NULL,
  `radio_cobertura_km` int DEFAULT '15' COMMENT 'Radio de cobertura en km',
  `activo` tinyint(1) DEFAULT '0' COMMENT '1 = QuickBite opera aquí',
  `fecha_lanzamiento` date DEFAULT NULL,
  `poblacion` int DEFAULT NULL,
  `orden` int DEFAULT '0',
  `imagen_portada` varchar(255) DEFAULT NULL,
  `total_negocios` int DEFAULT '0',
  `total_repartidores` int DEFAULT '0',
  `configuracion_envio` json DEFAULT NULL COMMENT 'Tarifas de envío personalizadas por municipio',
  PRIMARY KEY (`id_municipio`),
  KEY `idx_estado` (`id_estado_geo`),
  KEY `idx_activo` (`activo`),
  KEY `idx_slug` (`slug`),
  CONSTRAINT `fk_municipios_estado` FOREIGN KEY (`id_estado_geo`) REFERENCES `estados` (`id_estado_geo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zonas de cobertura (polígonos geográficos)
CREATE TABLE IF NOT EXISTS `zonas_cobertura` (
  `id_zona` int NOT NULL AUTO_INCREMENT,
  `id_municipio` int NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Ej: Centro, Norte, Oriente',
  `tipo` enum('cobertura','exclusion','premium') DEFAULT 'cobertura',
  `poligono` json NOT NULL COMMENT 'Array de coordenadas [{lat, lng}, ...]',
  `tarifa_extra` decimal(10,2) DEFAULT '0.00' COMMENT 'Cargo adicional por zona',
  `activo` tinyint(1) DEFAULT '1',
  `color` varchar(7) DEFAULT '#3388ff' COMMENT 'Color para el mapa',
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_zona`),
  KEY `idx_municipio` (`id_municipio`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `fk_zonas_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id_municipio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. SISTEMA DE NOTIFICACIONES IN-APP
-- =====================================================================

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id_notificacion` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo` enum('pedido','promocion','sistema','chat','pago','repartidor','membresia','recompensa') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `icono` varchar(50) DEFAULT 'bell' COMMENT 'Nombre del icono FontAwesome',
  `url_accion` varchar(500) DEFAULT NULL COMMENT 'URL a abrir al hacer click',
  `datos_extra` json DEFAULT NULL COMMENT 'Metadata adicional',
  `leida` tinyint(1) DEFAULT '0',
  `fecha_lectura` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_usuario_leida` (`id_usuario`, `leida`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`fecha_creacion`),
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preferencias de notificación por usuario
CREATE TABLE IF NOT EXISTS `preferencias_notificacion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `canal` enum('push','email','whatsapp','sms','in_app') NOT NULL,
  `tipo_notificacion` enum('pedidos','promociones','sistema','chat') NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_canal_tipo` (`id_usuario`, `canal`, `tipo_notificacion`),
  CONSTRAINT `fk_prefnotif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campañas de notificación masiva (CEO panel)
CREATE TABLE IF NOT EXISTS `campanas_notificacion` (
  `id_campana` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('push','email','whatsapp','sms') NOT NULL,
  `segmento` enum('todos','clientes','negocios','repartidores','miembros','por_ciudad') DEFAULT 'todos',
  `id_municipio` int DEFAULT NULL COMMENT 'Si segmento = por_ciudad',
  `estado` enum('borrador','programada','enviando','completada','cancelada') DEFAULT 'borrador',
  `fecha_programada` datetime DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `total_destinatarios` int DEFAULT '0',
  `total_enviados` int DEFAULT '0',
  `total_abiertos` int DEFAULT '0',
  `total_clicks` int DEFAULT '0',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_campana`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_programada` (`fecha_programada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. SISTEMA DE DISPUTAS Y REEMBOLSOS COMPLETO
-- =====================================================================

CREATE TABLE IF NOT EXISTS `disputas` (
  `id_disputa` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_usuario` int NOT NULL COMMENT 'Quien abrió la disputa',
  `tipo` enum('producto_faltante','producto_danado','pedido_incorrecto','demora_excesiva','cobro_incorrecto','no_entregado','mala_calidad','otro') NOT NULL,
  `descripcion` text NOT NULL,
  `evidencia_fotos` json DEFAULT NULL COMMENT 'Array de URLs de fotos',
  `estado` enum('abierta','en_revision','resuelta_favor_cliente','resuelta_favor_negocio','cerrada','escalada') DEFAULT 'abierta',
  `prioridad` enum('baja','media','alta','urgente') DEFAULT 'media',
  `monto_reclamado` decimal(10,2) DEFAULT '0.00',
  `monto_reembolsado` decimal(10,2) DEFAULT '0.00',
  `tipo_reembolso` enum('completo','parcial','credito','cupon','ninguno') DEFAULT NULL,
  `resolucion` text DEFAULT NULL,
  `resuelta_por` int DEFAULT NULL COMMENT 'ID del admin que resolvió',
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_disputa`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_estado` (`estado`),
  KEY `idx_prioridad` (`prioridad`, `fecha_creacion`),
  CONSTRAINT `fk_disputas_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `fk_disputas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensajes dentro de una disputa
CREATE TABLE IF NOT EXISTS `disputa_mensajes` (
  `id_mensaje` int NOT NULL AUTO_INCREMENT,
  `id_disputa` int NOT NULL,
  `tipo_remitente` enum('cliente','negocio','admin','sistema') NOT NULL,
  `id_remitente` int DEFAULT NULL,
  `mensaje` text NOT NULL,
  `adjuntos` json DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mensaje`),
  KEY `idx_disputa` (`id_disputa`),
  CONSTRAINT `fk_dmsg_disputa` FOREIGN KEY (`id_disputa`) REFERENCES `disputas` (`id_disputa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. TRACKING GPS EN TIEMPO REAL DE REPARTIDORES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ubicaciones_repartidor` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_pedido` int DEFAULT NULL COMMENT 'NULL si solo está disponible, no en entrega',
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `velocidad` decimal(5,2) DEFAULT NULL COMMENT 'km/h',
  `precision_gps` decimal(6,2) DEFAULT NULL COMMENT 'metros',
  `heading` decimal(5,2) DEFAULT NULL COMMENT 'dirección en grados',
  `bateria` tinyint DEFAULT NULL COMMENT 'porcentaje de batería',
  `fecha_registro` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_repartidor_fecha` (`id_repartidor`, `fecha_registro`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_fecha` (`fecha_registro`),
  CONSTRAINT `fk_ubicrep_repartidor` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. TIMESTAMPS POR CAMBIO DE ESTADO DE PEDIDO
-- =====================================================================

CREATE TABLE IF NOT EXISTS `pedido_timestamps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `estado_anterior` int DEFAULT NULL,
  `estado_nuevo` int NOT NULL,
  `timestamp_cambio` timestamp DEFAULT CURRENT_TIMESTAMP,
  `duracion_estado_anterior_seg` int DEFAULT NULL COMMENT 'Segundos en el estado anterior',
  `cambiado_por` enum('sistema','cliente','negocio','repartidor','admin') DEFAULT 'sistema',
  `id_usuario_cambio` int DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_estado_nuevo` (`estado_nuevo`),
  KEY `idx_timestamp` (`timestamp_cambio`),
  CONSTRAINT `fk_pedts_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. SURGE PRICING / PRECIOS DINÁMICOS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `surge_pricing` (
  `id_surge` int NOT NULL AUTO_INCREMENT,
  `id_municipio` int DEFAULT NULL,
  `multiplicador` decimal(3,2) NOT NULL DEFAULT '1.00' COMMENT '1.5 = 50% más caro',
  `motivo` enum('alta_demanda','clima','evento_especial','hora_pico','falta_repartidores') DEFAULT 'alta_demanda',
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `automatico` tinyint(1) DEFAULT '0' COMMENT 'Si se activa automáticamente',
  `umbral_pedidos_pendientes` int DEFAULT '10' COMMENT 'Pedidos sin repartidor para activar',
  `umbral_repartidores_disponibles` int DEFAULT '3' COMMENT 'Mínimo de repartidores para desactivar',
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_surge`),
  KEY `idx_municipio` (`id_municipio`),
  KEY `idx_activo` (`activo`),
  KEY `idx_fechas` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. PREFERENCIAS DE USUARIO
-- =====================================================================

CREATE TABLE IF NOT EXISTS `preferencias_usuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `alergias` json DEFAULT NULL COMMENT '["gluten","lacteos","mariscos"]',
  `dieta` enum('ninguna','vegetariano','vegano','keto','sin_gluten') DEFAULT 'ninguna',
  `idioma` varchar(5) DEFAULT 'es-MX',
  `tema` enum('claro','oscuro','sistema') DEFAULT 'sistema',
  `radio_busqueda_km` int DEFAULT '5',
  `ordenar_por_defecto` enum('distancia','rating','tiempo_entrega','precio') DEFAULT 'distancia',
  `recibir_ofertas` tinyint(1) DEFAULT '1',
  `compartir_ubicacion` tinyint(1) DEFAULT '1',
  `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario` (`id_usuario`),
  CONSTRAINT `fk_prefusr_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. AUDITORÍA DE ACCIONES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id_log` bigint NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `tipo_usuario` enum('cliente','negocio','repartidor','admin','sistema') DEFAULT 'sistema',
  `accion` varchar(100) NOT NULL COMMENT 'Ej: login, crear_pedido, cancelar_pedido',
  `entidad` varchar(50) DEFAULT NULL COMMENT 'Tabla afectada: pedidos, negocios, etc.',
  `id_entidad` int DEFAULT NULL COMMENT 'ID del registro afectado',
  `datos_anteriores` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_accion` (`accion`),
  KEY `idx_entidad` (`entidad`, `id_entidad`),
  KEY `idx_fecha` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. TOKENS JWT / SESIONES PARA APP MÓVIL
-- =====================================================================

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id_token` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `token` varchar(500) NOT NULL,
  `refresh_token` varchar(500) DEFAULT NULL,
  `tipo` enum('access','refresh','api_key') DEFAULT 'access',
  `dispositivo` varchar(255) DEFAULT NULL COMMENT 'Nombre del dispositivo',
  `plataforma` enum('web','android','ios','pwa') DEFAULT 'web',
  `ip_address` varchar(45) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` timestamp NULL DEFAULT NULL,
  `ultimo_uso` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_token`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_token` (`token`(255)),
  KEY `idx_refresh` (`refresh_token`(255)),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `fk_authtk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. DOCUMENTOS DE REPARTIDORES (con expiración)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `documentos_repartidor` (
  `id_documento` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `tipo` enum('ine_frente','ine_reverso','licencia','tarjeta_circulacion','seguro','comprobante_domicilio','antecedentes','foto_vehiculo') NOT NULL,
  `url_archivo` varchar(500) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado','expirado') DEFAULT 'pendiente',
  `fecha_expiracion` date DEFAULT NULL,
  `motivo_rechazo` varchar(255) DEFAULT NULL,
  `verificado_por` int DEFAULT NULL,
  `fecha_verificacion` datetime DEFAULT NULL,
  `fecha_subida` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_documento`),
  KEY `idx_repartidor` (`id_repartidor`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_estado` (`estado`),
  KEY `idx_expiracion` (`fecha_expiracion`),
  CONSTRAINT `fk_docrep_repartidor` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. RETOS / CHALLENGES PARA REPARTIDORES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `retos_repartidor` (
  `id_reto` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text,
  `tipo` enum('diario','semanal','mensual','especial') DEFAULT 'diario',
  `meta_cantidad` int NOT NULL COMMENT 'Ej: 10 entregas',
  `meta_tipo` enum('entregas','distancia_km','calificacion','ingresos','referidos') DEFAULT 'entregas',
  `recompensa_monto` decimal(10,2) DEFAULT '0.00',
  `recompensa_tipo` enum('efectivo','puntos','badge','bono') DEFAULT 'efectivo',
  `id_municipio` int DEFAULT NULL COMMENT 'NULL = aplica en todos',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reto`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_activo_fechas` (`activo`, `fecha_inicio`, `fecha_fin`),
  KEY `idx_municipio` (`id_municipio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progreso de repartidores en retos
CREATE TABLE IF NOT EXISTS `retos_progreso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_reto` int NOT NULL,
  `id_repartidor` int NOT NULL,
  `progreso_actual` int DEFAULT '0',
  `completado` tinyint(1) DEFAULT '0',
  `recompensa_reclamada` tinyint(1) DEFAULT '0',
  `fecha_completado` datetime DEFAULT NULL,
  `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reto_repartidor` (`id_reto`, `id_repartidor`),
  KEY `idx_repartidor` (`id_repartidor`),
  CONSTRAINT `fk_retoprog_reto` FOREIGN KEY (`id_reto`) REFERENCES `retos_repartidor` (`id_reto`) ON DELETE CASCADE,
  CONSTRAINT `fk_retoprog_rep` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 12. MAPAS DE CALOR / DEMANDA
-- =====================================================================

CREATE TABLE IF NOT EXISTS `demanda_zona` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_municipio` int DEFAULT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `radio_km` decimal(3,1) DEFAULT '1.0',
  `pedidos_ultima_hora` int DEFAULT '0',
  `repartidores_disponibles` int DEFAULT '0',
  `nivel_demanda` enum('baja','normal','alta','muy_alta') DEFAULT 'normal',
  `hora` tinyint NOT NULL COMMENT '0-23',
  `dia_semana` tinyint NOT NULL COMMENT '0=Domingo, 6=Sábado',
  `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_municipio` (`id_municipio`),
  KEY `idx_ubicacion` (`latitud`, `longitud`),
  KEY `idx_hora_dia` (`hora`, `dia_semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 13. SISTEMA DE COMBOS / PAQUETES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `combos` (
  `id_combo` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `imagen` varchar(255) DEFAULT NULL,
  `precio_combo` decimal(10,2) NOT NULL COMMENT 'Precio con descuento',
  `precio_sin_descuento` decimal(10,2) NOT NULL COMMENT 'Suma de productos individual',
  `disponible` tinyint(1) DEFAULT '1',
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `max_usos_dia` int DEFAULT NULL COMMENT 'Límite de ventas por día',
  `orden` int DEFAULT '0',
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_combo`),
  KEY `idx_negocio` (`id_negocio`),
  KEY `idx_disponible` (`disponible`),
  KEY `idx_fechas` (`fecha_inicio`, `fecha_fin`),
  CONSTRAINT `fk_combos_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Productos que conforman un combo
CREATE TABLE IF NOT EXISTS `combo_productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_combo` int NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_combo` (`id_combo`),
  KEY `idx_producto` (`id_producto`),
  CONSTRAINT `fk_comboprod_combo` FOREIGN KEY (`id_combo`) REFERENCES `combos` (`id_combo`) ON DELETE CASCADE,
  CONSTRAINT `fk_comboprod_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 14. HAPPY HOUR / PROMOCIONES POR HORARIO
-- =====================================================================

CREATE TABLE IF NOT EXISTS `promociones_horario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Ej: Happy Hour, 2x1 Martes',
  `tipo_descuento` enum('porcentaje','monto_fijo','2x1','envio_gratis') NOT NULL,
  `valor_descuento` decimal(10,2) DEFAULT '0.00',
  `aplica_a` enum('todo','categoria','producto') DEFAULT 'todo',
  `id_categoria_aplica` int DEFAULT NULL,
  `id_producto_aplica` int DEFAULT NULL,
  `dias_semana` json DEFAULT NULL COMMENT '[1,2,3] = Lun,Mar,Mie',
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `pedido_minimo` decimal(10,2) DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_inicio` date DEFAULT NULL COMMENT 'NULL = siempre activa',
  `fecha_fin` date DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_negocio` (`id_negocio`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `fk_promhora_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 15. CONFIGURACIÓN DINÁMICA DESDE CEO PANEL
-- =====================================================================

CREATE TABLE IF NOT EXISTS `configuracion_plataforma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `tipo` enum('string','number','boolean','json') DEFAULT 'string',
  `grupo` varchar(50) DEFAULT 'general' COMMENT 'comisiones, envio, pagos, etc.',
  `descripcion` varchar(255) DEFAULT NULL,
  `id_municipio` int DEFAULT NULL COMMENT 'NULL = global, con valor = por ciudad',
  `modificado_por` int DEFAULT NULL,
  `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clave_municipio` (`clave`, `id_municipio`),
  KEY `idx_grupo` (`grupo`),
  KEY `idx_municipio` (`id_municipio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 16. SUSPENSIÓN / BAN DE USUARIOS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `suspensiones_usuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo` enum('advertencia','suspension_temporal','suspension_permanente','ban') NOT NULL,
  `motivo` text NOT NULL,
  `fecha_inicio` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_fin` datetime DEFAULT NULL COMMENT 'NULL = permanente',
  `activa` tinyint(1) DEFAULT '1',
  `creada_por` int DEFAULT NULL COMMENT 'Admin que la creó',
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_activa` (`activa`),
  CONSTRAINT `fk_susp_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 17. MÉTRICAS Y ANALYTICS AVANZADOS
-- =====================================================================

-- Retención de usuarios (cohortes)
CREATE TABLE IF NOT EXISTS `metricas_retencion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cohorte_mes` date NOT NULL COMMENT 'Mes de registro del usuario',
  `mes_actividad` date NOT NULL COMMENT 'Mes de la actividad',
  `usuarios_registrados` int DEFAULT '0',
  `usuarios_activos` int DEFAULT '0',
  `pedidos_realizados` int DEFAULT '0',
  `ingresos` decimal(12,2) DEFAULT '0.00',
  `tasa_retencion` decimal(5,2) DEFAULT '0.00',
  `fecha_calculo` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cohorte_mes` (`cohorte_mes`, `mes_actividad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Métricas diarias de plataforma
CREATE TABLE IF NOT EXISTS `metricas_diarias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `id_municipio` int DEFAULT NULL COMMENT 'NULL = global',
  `total_pedidos` int DEFAULT '0',
  `pedidos_entregados` int DEFAULT '0',
  `pedidos_cancelados` int DEFAULT '0',
  `ingresos_brutos` decimal(12,2) DEFAULT '0.00',
  `comisiones_cobradas` decimal(12,2) DEFAULT '0.00',
  `subsidios_envio` decimal(12,2) DEFAULT '0.00',
  `nuevos_usuarios` int DEFAULT '0',
  `nuevos_negocios` int DEFAULT '0',
  `nuevos_repartidores` int DEFAULT '0',
  `usuarios_activos` int DEFAULT '0',
  `tiempo_entrega_promedio` int DEFAULT NULL COMMENT 'minutos',
  `calificacion_promedio` decimal(3,2) DEFAULT NULL,
  `tasa_cancelacion` decimal(5,2) DEFAULT NULL,
  `ticket_promedio` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fecha_municipio` (`fecha`, `id_municipio`),
  KEY `idx_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 18. HISTORIAL DE PAYOUTS / PAGOS A NEGOCIOS Y REPARTIDORES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `payouts` (
  `id_payout` int NOT NULL AUTO_INCREMENT,
  `tipo_destinatario` enum('negocio','repartidor') NOT NULL,
  `id_destinatario` int NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `moneda` varchar(3) DEFAULT 'MXN',
  `metodo_pago` enum('spei','transferencia','efectivo','mercadopago') DEFAULT 'spei',
  `referencia_bancaria` varchar(100) DEFAULT NULL,
  `clabe_destino` varchar(18) DEFAULT NULL,
  `banco_destino` varchar(100) DEFAULT NULL,
  `titular_cuenta` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','procesando','completado','fallido','cancelado') DEFAULT 'pendiente',
  `periodo_inicio` date DEFAULT NULL COMMENT 'Inicio del periodo de pago',
  `periodo_fin` date DEFAULT NULL COMMENT 'Fin del periodo de pago',
  `total_pedidos` int DEFAULT '0',
  `total_comisiones` decimal(12,2) DEFAULT '0.00',
  `total_propinas` decimal(12,2) DEFAULT '0.00',
  `notas` text DEFAULT NULL,
  `procesado_por` int DEFAULT NULL,
  `fecha_procesamiento` datetime DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_payout`),
  KEY `idx_destinatario` (`tipo_destinatario`, `id_destinatario`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha_creacion`),
  KEY `idx_periodo` (`periodo_inicio`, `periodo_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de pedidos incluidos en un payout
CREATE TABLE IF NOT EXISTS `payout_detalles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_payout` int NOT NULL,
  `id_pedido` int NOT NULL,
  `monto_pedido` decimal(10,2) NOT NULL,
  `comision` decimal(10,2) DEFAULT '0.00',
  `propina` decimal(10,2) DEFAULT '0.00',
  `monto_neto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payout` (`id_payout`),
  KEY `idx_pedido` (`id_pedido`),
  CONSTRAINT `fk_paydet_payout` FOREIGN KEY (`id_payout`) REFERENCES `payouts` (`id_payout`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 19. DETECCIÓN DE FRAUDE
-- =====================================================================

CREATE TABLE IF NOT EXISTS `alertas_fraude` (
  `id_alerta` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('multiples_cuentas','chargebacks','uso_abusivo_cupones','pedidos_sospechosos','ubicacion_falsa','referidos_falsos') NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_pedido` int DEFAULT NULL,
  `descripcion` text NOT NULL,
  `severidad` enum('baja','media','alta','critica') DEFAULT 'media',
  `estado` enum('nueva','investigando','confirmada','descartada') DEFAULT 'nueva',
  `datos_evidencia` json DEFAULT NULL,
  `resuelta_por` int DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_alerta`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_severidad` (`severidad`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 20. FACTURACIÓN CFDI
-- =====================================================================

CREATE TABLE IF NOT EXISTS `facturas` (
  `id_factura` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_negocio` int DEFAULT NULL,
  `tipo` enum('ingreso','egreso','traslado') DEFAULT 'ingreso',
  `uuid_cfdi` varchar(36) DEFAULT NULL COMMENT 'UUID del CFDI timbrado',
  `rfc_emisor` varchar(13) DEFAULT NULL,
  `rfc_receptor` varchar(13) DEFAULT NULL,
  `razon_social` varchar(255) DEFAULT NULL,
  `regimen_fiscal` varchar(3) DEFAULT NULL,
  `uso_cfdi` varchar(5) DEFAULT 'G03' COMMENT 'Gastos en general',
  `subtotal` decimal(12,2) NOT NULL,
  `iva` decimal(12,2) DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL,
  `moneda` varchar(3) DEFAULT 'MXN',
  `forma_pago` varchar(2) DEFAULT '99' COMMENT '01=Efectivo, 04=Tarjeta, etc.',
  `metodo_pago` varchar(3) DEFAULT 'PUE' COMMENT 'PUE=Pago en una exhibición',
  `xml_url` varchar(500) DEFAULT NULL,
  `pdf_url` varchar(500) DEFAULT NULL,
  `estado` enum('pendiente','timbrada','cancelada','error') DEFAULT 'pendiente',
  `error_mensaje` text DEFAULT NULL,
  `fecha_timbrado` datetime DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_factura`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_negocio` (`id_negocio`),
  KEY `idx_uuid` (`uuid_cfdi`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos fiscales de usuarios/negocios
CREATE TABLE IF NOT EXISTS `datos_fiscales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `id_negocio` int DEFAULT NULL,
  `rfc` varchar(13) NOT NULL,
  `razon_social` varchar(255) NOT NULL,
  `regimen_fiscal` varchar(3) NOT NULL,
  `codigo_postal_fiscal` varchar(5) NOT NULL,
  `uso_cfdi` varchar(5) DEFAULT 'G03',
  `email_facturacion` varchar(100) DEFAULT NULL,
  `es_predeterminado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_negocio` (`id_negocio`),
  KEY `idx_rfc` (`rfc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 21. COLUMNAS FALTANTES EN TABLAS EXISTENTES
-- =====================================================================

-- negocios: agregar id_municipio para multi-ciudad
ALTER TABLE `negocios`
  ADD COLUMN IF NOT EXISTS `id_municipio` int DEFAULT NULL AFTER `estado_geografico`,
  ADD COLUMN IF NOT EXISTS `auto_aceptar_pedidos` tinyint(1) DEFAULT '0' COMMENT 'Aceptar pedidos automáticamente',
  ADD COLUMN IF NOT EXISTS `pausado` tinyint(1) DEFAULT '0' COMMENT 'Pausar recepción temporalmente',
  ADD COLUMN IF NOT EXISTS `fecha_pausa` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `notificacion_sonido` tinyint(1) DEFAULT '1' COMMENT 'Sonido al recibir pedido',
  ADD COLUMN IF NOT EXISTS `acepta_efectivo` tinyint(1) DEFAULT '1',
  ADD COLUMN IF NOT EXISTS `acepta_tarjeta` tinyint(1) DEFAULT '1',
  ADD COLUMN IF NOT EXISTS `notificaciones_email` tinyint(1) DEFAULT '1',
  ADD COLUMN IF NOT EXISTS `notificaciones_whatsapp` tinyint(1) DEFAULT '1';

-- pedidos: agregar id_municipio, instrucciones al repartidor
ALTER TABLE `pedidos`
  ADD COLUMN IF NOT EXISTS `id_municipio` int DEFAULT NULL AFTER `id_negocio`,
  ADD COLUMN IF NOT EXISTS `instrucciones_repartidor` varchar(500) DEFAULT NULL COMMENT 'Instrucciones para el repartidor',
  ADD COLUMN IF NOT EXISTS `surge_multiplicador` decimal(3,2) DEFAULT '1.00',
  ADD COLUMN IF NOT EXISTS `fecha_confirmado` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_preparando` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_listo` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_en_camino` datetime DEFAULT NULL;

-- usuarios: agregar id_municipio
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `id_municipio` int DEFAULT NULL AFTER `tipo_usuario`,
  ADD COLUMN IF NOT EXISTS `fecha_nacimiento` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `genero` enum('masculino','femenino','otro','no_especificado') DEFAULT 'no_especificado',
  ADD COLUMN IF NOT EXISTS `ultimo_login` timestamp NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `total_pedidos` int DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `gasto_total` decimal(12,2) DEFAULT '0.00',
  ADD COLUMN IF NOT EXISTS `suspendido` tinyint(1) DEFAULT '0';

-- repartidores: agregar id_municipio, multi-pedido
ALTER TABLE `repartidores`
  ADD COLUMN IF NOT EXISTS `id_municipio` int DEFAULT NULL AFTER `id_usuario`,
  ADD COLUMN IF NOT EXISTS `en_entrega` tinyint(1) DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `max_pedidos_simultaneos` int DEFAULT '1' COMMENT 'Multi-pedido',
  ADD COLUMN IF NOT EXISTS `pedidos_activos_count` int DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `foto_perfil` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fecha_nacimiento` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `nss` varchar(15) DEFAULT NULL COMMENT 'NSS para seguro',
  ADD COLUMN IF NOT EXISTS `contacto_emergencia_nombre` varchar(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contacto_emergencia_tel` varchar(20) DEFAULT NULL;

-- productos: agregar info nutricional y búsqueda
ALTER TABLE `productos`
  ADD COLUMN IF NOT EXISTS `alergenos` json DEFAULT NULL COMMENT '["gluten","lacteos","nueces"]',
  ADD COLUMN IF NOT EXISTS `etiquetas` json DEFAULT NULL COMMENT '["vegano","picante","popular"]',
  ADD COLUMN IF NOT EXISTS `tiempo_preparacion` int DEFAULT NULL COMMENT 'Minutos de este producto',
  ADD COLUMN IF NOT EXISTS `total_vendidos` int DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `rating_promedio` decimal(3,2) DEFAULT '0.00';

-- direcciones_usuario: agregar id_municipio
ALTER TABLE `direcciones_usuario`
  ADD COLUMN IF NOT EXISTS `id_municipio` int DEFAULT NULL AFTER `estado`,
  ADD COLUMN IF NOT EXISTS `tipo` enum('casa','trabajo','pareja','otro') DEFAULT 'casa',
  ADD COLUMN IF NOT EXISTS `numero_interior` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `telefono_contacto` varchar(20) DEFAULT NULL;

-- mensajes_chat: agregar tipo de mensaje
ALTER TABLE `mensajes_chat`
  ADD COLUMN IF NOT EXISTS `tipo_mensaje` enum('texto','imagen','ubicacion','sistema') DEFAULT 'texto' AFTER `mensaje`,
  ADD COLUMN IF NOT EXISTS `url_adjunto` varchar(500) DEFAULT NULL;

-- =====================================================================
-- 22. ÍNDICES FULLTEXT PARA BÚSQUEDA
-- =====================================================================

-- Búsqueda de productos por nombre y descripción
ALTER TABLE `productos` ADD FULLTEXT INDEX `ft_productos_busqueda` (`nombre`, `descripcion`);

-- Búsqueda de negocios por nombre y descripción
ALTER TABLE `negocios` ADD FULLTEXT INDEX `ft_negocios_busqueda` (`nombre`, `descripcion`);

-- =====================================================================
-- 23. ÍNDICES ADICIONALES DE RENDIMIENTO
-- =====================================================================

-- Índice para búsqueda por municipio en negocios
-- (se agrega después del ALTER TABLE para asegurar que la columna existe)
CREATE INDEX IF NOT EXISTS `idx_negocio_municipio` ON `negocios` (`id_municipio`);
CREATE INDEX IF NOT EXISTS `idx_pedido_municipio` ON `pedidos` (`id_municipio`);
CREATE INDEX IF NOT EXISTS `idx_usuario_municipio` ON `usuarios` (`id_municipio`);
CREATE INDEX IF NOT EXISTS `idx_repartidor_municipio` ON `repartidores` (`id_municipio`);
CREATE INDEX IF NOT EXISTS `idx_direccion_municipio` ON `direcciones_usuario` (`id_municipio`);

-- =====================================================================
-- 24. DATOS INICIALES - ESTADOS Y MUNICIPIOS DE MÉXICO
-- =====================================================================

-- País
INSERT INTO `paises` (`id_pais`, `nombre`, `codigo_iso`, `moneda`, `prefijo_telefono`)
VALUES (1, 'México', 'MX', 'MXN', '+52')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Estados donde QuickBite operará
INSERT INTO `estados` (`id_estado_geo`, `id_pais`, `nombre`, `abreviatura`, `activo`, `orden`) VALUES
(1, 1, 'Aguascalientes', 'AGS', 1, 1),
(2, 1, 'Guanajuato', 'GTO', 1, 2),
(3, 1, 'Jalisco', 'JAL', 0, 3),
(4, 1, 'San Luis Potosí', 'SLP', 0, 4),
(5, 1, 'Querétaro', 'QRO', 0, 5),
(6, 1, 'Zacatecas', 'ZAC', 0, 6),
(7, 1, 'Baja California', 'BC', 0, 7),
(8, 1, 'Chihuahua', 'CHIH', 0, 8),
(9, 1, 'Ciudad de México', 'CDMX', 0, 9),
(10, 1, 'Nuevo León', 'NL', 0, 10),
(11, 1, 'Puebla', 'PUE', 0, 11),
(12, 1, 'Michoacán', 'MICH', 0, 12),
(13, 1, 'Sonora', 'SON', 0, 13),
(14, 1, 'Coahuila', 'COAH', 0, 14),
(15, 1, 'Tamaulipas', 'TAMPS', 0, 15),
(16, 1, 'Veracruz', 'VER', 0, 16),
(17, 1, 'Durango', 'DGO', 0, 17),
(18, 1, 'Nayarit', 'NAY', 0, 18),
(19, 1, 'Colima', 'COL', 0, 19),
(20, 1, 'Sinaloa', 'SIN', 0, 20),
(21, 1, 'Estado de México', 'EDOMEX', 0, 21),
(22, 1, 'Hidalgo', 'HGO', 0, 22),
(23, 1, 'Tlaxcala', 'TLAX', 0, 23),
(24, 1, 'Morelos', 'MOR', 0, 24),
(25, 1, 'Guerrero', 'GRO', 0, 25),
(26, 1, 'Oaxaca', 'OAX', 0, 26),
(27, 1, 'Chiapas', 'CHIS', 0, 27),
(28, 1, 'Tabasco', 'TAB', 0, 28),
(29, 1, 'Campeche', 'CAMP', 0, 29),
(30, 1, 'Yucatán', 'YUC', 0, 30),
(31, 1, 'Quintana Roo', 'QROO', 0, 31)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Municipios activos iniciales (Aguascalientes y León)
INSERT INTO `municipios` (`id_municipio`, `id_estado_geo`, `nombre`, `slug`, `latitud_centro`, `longitud_centro`, `radio_cobertura_km`, `activo`, `poblacion`) VALUES
-- Aguascalientes
(1, 1, 'Aguascalientes', 'aguascalientes', 21.8818, -102.2916, 15, 1, 863893),
(2, 1, 'Jesús María', 'jesus-maria', 21.9614, -102.3439, 8, 0, 129211),
(3, 1, 'Calvillo', 'calvillo', 21.8456, -102.7189, 6, 0, 56048),
(4, 1, 'Rincón de Romos', 'rincon-de-romos', 22.2322, -102.3178, 5, 0, 53866),
(5, 1, 'Pabellón de Arteaga', 'pabellon-de-arteaga', 22.1500, -102.2728, 5, 0, 46473),
(6, 1, 'San Francisco de los Romo', 'san-francisco-de-los-romo', 22.0719, -102.2703, 5, 0, 51527),
(7, 1, 'Asientos', 'asientos', 22.2367, -102.0850, 5, 0, 50637),
(8, 1, 'Cosío', 'cosio', 22.3656, -102.2992, 4, 0, 16766),
(9, 1, 'El Llano', 'el-llano', 21.9186, -101.9644, 4, 0, 19905),
(10, 1, 'San José de Gracia', 'san-jose-de-gracia', 22.1506, -102.4217, 4, 0, 9017),
(11, 1, 'Tepezalá', 'tepezala', 22.2242, -102.1711, 4, 0, 21836),
-- Guanajuato
(12, 2, 'León', 'leon', 21.1250, -101.6860, 15, 1, 1721215),
(13, 2, 'Irapuato', 'irapuato', 20.6736, -101.3486, 10, 0, 529440),
(14, 2, 'Celaya', 'celaya', 20.5236, -100.8155, 10, 0, 494304),
(15, 2, 'Salamanca', 'salamanca', 20.5731, -101.1950, 8, 0, 273271),
(16, 2, 'Guanajuato', 'guanajuato-capital', 21.0190, -101.2574, 8, 0, 184239),
(17, 2, 'San Miguel de Allende', 'san-miguel-de-allende', 20.9144, -100.7453, 8, 0, 171857),
(18, 2, 'Silao', 'silao', 20.9475, -101.4286, 8, 0, 180506),
-- Jalisco
(19, 3, 'Guadalajara', 'guadalajara', 20.6597, -103.3496, 20, 0, 1385629),
(20, 3, 'Zapopan', 'zapopan', 20.7214, -103.3890, 15, 0, 1476491),
(21, 3, 'Tlaquepaque', 'tlaquepaque', 20.6411, -103.3133, 10, 0, 664193),
(22, 3, 'Tonalá', 'tonala', 20.6233, -103.2348, 10, 0, 536111),
(23, 3, 'Lagos de Moreno', 'lagos-de-moreno', 21.3547, -101.9306, 8, 0, 170361),
(24, 3, 'Tepatitlán', 'tepatitlan', 20.8167, -102.7325, 8, 0, 141322),
-- San Luis Potosí
(25, 4, 'San Luis Potosí', 'san-luis-potosi', 22.1565, -100.9855, 15, 0, 911908),
(26, 4, 'Soledad de Graciano Sánchez', 'soledad-gs', 22.1833, -100.9381, 10, 0, 310158),
-- Querétaro
(27, 5, 'Querétaro', 'queretaro', 20.5888, -100.3899, 15, 0, 1049777),
(28, 5, 'San Juan del Río', 'san-juan-del-rio', 20.3861, -99.9961, 8, 0, 286312)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Configuración inicial de plataforma
INSERT INTO `configuracion_plataforma` (`clave`, `valor`, `tipo`, `grupo`, `descripcion`) VALUES
('comision_basica', '10', 'number', 'comisiones', 'Comisión básica a negocios (%)'),
('comision_premium', '8', 'number', 'comisiones', 'Comisión premium a negocios (%)'),
('envio_base_corto', '18', 'number', 'envio', 'Tarifa base envío ≤1.5km'),
('envio_base_largo', '25', 'number', 'envio', 'Tarifa base envío >1.5km'),
('envio_por_km', '5', 'number', 'envio', 'Costo por km adicional'),
('envio_radio_corto', '1.5', 'number', 'envio', 'Radio corto en km'),
('distancia_maxima', '15', 'number', 'envio', 'Distancia máxima de entrega en km'),
('membresia_negocio_precio', '499', 'number', 'membresias', 'Precio membresía premium negocio/mes'),
('membresia_club_precio', '49', 'number', 'membresias', 'Precio QuickBite Club/mes'),
('envio_gratis_monto', '250', 'number', 'envio', 'Monto mínimo para envío gratis (miembros)'),
('envio_mitad_monto', '150', 'number', 'envio', 'Monto mínimo para 50% envío (miembros)'),
('surge_activo', 'false', 'boolean', 'surge', 'Surge pricing activado globalmente'),
('surge_umbral_pedidos', '10', 'number', 'surge', 'Pedidos pendientes para activar surge'),
('surge_multiplicador_default', '1.5', 'number', 'surge', 'Multiplicador default de surge')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- =====================================================================
-- 25. VISTAS ÚTILES
-- =====================================================================

-- Vista: Negocios con info de municipio
CREATE OR REPLACE VIEW `v_negocios_por_ciudad` AS
SELECT
  n.*,
  m.nombre AS nombre_municipio,
  m.slug AS slug_municipio,
  e.nombre AS nombre_estado,
  e.abreviatura AS abreviatura_estado
FROM negocios n
LEFT JOIN municipios m ON n.id_municipio = m.id_municipio
LEFT JOIN estados e ON m.id_estado_geo = e.id_estado_geo
WHERE n.activo = 1;

-- Vista: Resumen de disputas pendientes
CREATE OR REPLACE VIEW `v_disputas_pendientes` AS
SELECT
  d.*,
  p.monto_total AS monto_pedido,
  u.nombre AS nombre_cliente,
  u.email AS email_cliente,
  n.nombre AS nombre_negocio
FROM disputas d
JOIN pedidos p ON d.id_pedido = p.id_pedido
JOIN usuarios u ON d.id_usuario = u.id_usuario
JOIN negocios n ON p.id_negocio = n.id_negocio
WHERE d.estado IN ('abierta', 'en_revision')
ORDER BY
  FIELD(d.prioridad, 'urgente', 'alta', 'media', 'baja'),
  d.fecha_creacion ASC;

-- Vista: Métricas de repartidor con retos activos
CREATE OR REPLACE VIEW `v_repartidores_con_retos` AS
SELECT
  r.id_repartidor,
  u.nombre,
  r.calificacion_promedio,
  r.total_entregas,
  COUNT(DISTINCT rp.id_reto) AS retos_activos,
  SUM(CASE WHEN rp.completado = 1 THEN 1 ELSE 0 END) AS retos_completados
FROM repartidores r
JOIN usuarios u ON r.id_usuario = u.id_usuario
LEFT JOIN retos_progreso rp ON r.id_repartidor = rp.id_repartidor
WHERE r.activo = 1
GROUP BY r.id_repartidor;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- FIN DE MIGRACIÓN
-- Total: 20+ tablas nuevas, 30+ columnas agregadas, índices FULLTEXT,
--        datos iniciales de 31 estados + 28 municipios
-- =====================================================================
