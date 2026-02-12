mysqldump: [Warning] Using a password on the command line interface can be insecure.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ahorro_miembros` (
  `id_ahorro` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_pedido` int DEFAULT NULL,
  `tipo_ahorro` enum('envio','cargo_servicio','descuento') COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto_ahorrado` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_ahorro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ahorro`),
  KEY `idx_usuario_fecha` (`id_usuario`,`fecha_ahorro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banner_clicks` (
  `id_click` int NOT NULL AUTO_INCREMENT,
  `id_banner` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_click` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_click`),
  KEY `id_banner` (`id_banner`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `banner_clicks_ibfk_1` FOREIGN KEY (`id_banner`) REFERENCES `promotional_banners` (`id_banner`) ON DELETE CASCADE,
  CONSTRAINT `banner_clicks_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banner_impressions` (
  `id_impression` int NOT NULL AUTO_INCREMENT,
  `id_banner` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_impression` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_impression`),
  KEY `id_banner` (`id_banner`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `banner_impressions_ibfk_1` FOREIGN KEY (`id_banner`) REFERENCES `promotional_banners` (`id_banner`) ON DELETE CASCADE,
  CONSTRAINT `banner_impressions_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `beneficios_referidos` (
  `id_beneficio` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo_beneficio` enum('referido','fidelidad','general') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'general',
  `usado` tinyint(1) DEFAULT '0',
  `fecha_uso` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_beneficio`),
  UNIQUE KEY `unique_usuario_tipo` (`id_usuario`,`tipo_beneficio`),
  CONSTRAINT `beneficios_referidos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `beneficios_repartidores` (
  `id_beneficio` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `tipo_beneficio` enum('referido','nivel','meta_semanal','meta_mensual','bono_especial') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto_bonificacion` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monto en pesos de la bonificacion',
  `estado` enum('pendiente','aprobado','acreditado','rechazado','expirado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_solicitud` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `fecha_acreditacion` datetime DEFAULT NULL,
  `id_referido_relacionado` int DEFAULT NULL COMMENT 'Si es por referido, cual referido',
  `notas` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_beneficio`),
  KEY `id_referido_relacionado` (`id_referido_relacionado`),
  KEY `idx_repartidor` (`id_repartidor`),
  KEY `idx_tipo` (`tipo_beneficio`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `beneficios_repartidores_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE,
  CONSTRAINT `beneficios_repartidores_ibfk_2` FOREIGN KEY (`id_referido_relacionado`) REFERENCES `referidos_repartidores` (`id_referido`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonificaciones_repartidor` (
  `id_bonificacion` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_pedido` int DEFAULT NULL,
  `id_ruta` int DEFAULT NULL,
  `tipo` enum('batch_delivery','rescate_pedido','velocidad','calificacion_perfecta','racha_completados','hora_pico') COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','pagada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_pago` datetime DEFAULT NULL,
  PRIMARY KEY (`id_bonificacion`),
  KEY `idx_bonif_repartidor` (`id_repartidor`,`estado`),
  KEY `idx_bonif_fecha` (`fecha_creacion`),
  CONSTRAINT `bonificaciones_repartidor_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_aliados` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `orden` int DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_negocio` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `icono` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_producto` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `orden_visualizacion` int DEFAULT '0',
  PRIMARY KEY (`id_categoria`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `categorias_producto_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos_descuento_aliados` (
  `id_codigo` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_aliado` int NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_generacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` date NOT NULL,
  `usado` tinyint(1) DEFAULT '0',
  `fecha_uso` datetime DEFAULT NULL,
  PRIMARY KEY (`id_codigo`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_usuario_aliado` (`id_usuario`,`id_aliado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_bonificaciones_repartidor` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `tipo_bonificacion` varchar(50) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `requisito_minimo` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `tipo_bonificacion` (`tipo_bonificacion`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_mandados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_sistema` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `clave_config` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valor_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `clave_config` (`clave_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_timeout` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('global','zona','negocio') COLLATE utf8mb4_unicode_ci DEFAULT 'global',
  `id_referencia` int DEFAULT NULL COMMENT 'ID de zona o negocio si aplica',
  `timeout_aceptacion_minutos` int DEFAULT '10',
  `timeout_recogida_minutos` int DEFAULT '20',
  `max_intentos_asignacion` int DEFAULT '3',
  `radio_busqueda_km` decimal(5,2) DEFAULT '5.00' COMMENT 'Radio para buscar repartidores',
  `incremento_radio_km` decimal(5,2) DEFAULT '2.00' COMMENT 'Incremento por cada reintento',
  `bonificacion_reintento` decimal(10,2) DEFAULT '5.00' COMMENT 'Bonus extra por recoger pedido reasignado',
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_config`),
  KEY `idx_config_tipo` (`tipo`,`id_referencia`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cupones` (
  `id_cupon` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_descuento` enum('porcentaje','monto_fijo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'porcentaje',
  `valor_descuento` decimal(10,2) NOT NULL,
  `minimo_compra` decimal(10,2) DEFAULT '0.00',
  `maximo_descuento` decimal(10,2) DEFAULT NULL COMMENT 'Límite máximo de descuento para porcentajes',
  `usos_maximos` int DEFAULT NULL COMMENT 'NULL = ilimitado',
  `usos_actuales` int DEFAULT '0',
  `usos_por_usuario` int DEFAULT '1' COMMENT 'Veces que un usuario puede usar el cupón',
  `fecha_inicio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` datetime DEFAULT NULL,
  `aplica_todos_negocios` tinyint(1) DEFAULT '1',
  `solo_primera_compra` tinyint(1) DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cupon`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_activo` (`activo`),
  KEY `idx_fechas` (`fecha_inicio`,`fecha_expiracion`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cupones_negocios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cupon` int NOT NULL,
  `id_negocio` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cupon_negocio` (`id_cupon`,`id_negocio`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `cupones_negocios_ibfk_1` FOREIGN KEY (`id_cupon`) REFERENCES `cupones` (`id_cupon`) ON DELETE CASCADE,
  CONSTRAINT `cupones_negocios_ibfk_2` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cupones_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cupon` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_pedido` int DEFAULT NULL,
  `descuento_aplicado` decimal(10,2) NOT NULL,
  `fecha_uso` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `idx_cupon_usuario` (`id_cupon`,`id_usuario`),
  CONSTRAINT `cupones_usuarios_ibfk_1` FOREIGN KEY (`id_cupon`) REFERENCES `cupones` (`id_cupon`) ON DELETE CASCADE,
  CONSTRAINT `cupones_usuarios_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_pedido` (
  `id_detalle_pedido` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `instrucciones_especiales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `subtotal` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id_detalle_pedido`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalles_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `detalles_pedido_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deudas_comisiones` (
  `id_deuda` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_pedido` int NOT NULL,
  `monto_comision` decimal(10,2) NOT NULL,
  `monto_pagado` decimal(10,2) DEFAULT '0.00',
  `estado` enum('pendiente','parcial','pagada','condonada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_pago` date DEFAULT NULL,
  `metodo_pago` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_deuda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deudas_comisiones_negocios` (
  `id_deuda` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `id_pedido` int NOT NULL,
  `monto_comision` decimal(10,2) NOT NULL,
  `fecha_generacion` datetime NOT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `estado` enum('pendiente','pagada','cancelada') DEFAULT 'pendiente',
  `metodo_pago` varchar(50) DEFAULT NULL,
  `referencia_pago` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_deuda`),
  KEY `idx_negocio` (`id_negocio`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `direcciones_usuario` (
  `id_direccion` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `nombre_direccion` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `calle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `colonia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ciudad` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `codigo_postal` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `es_predeterminada` tinyint(1) DEFAULT '0',
  `referencias` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_geocodificacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_direccion`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `direcciones_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `elegibles_producto` (
  `id_elegible` int NOT NULL AUTO_INCREMENT,
  `id_producto` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `precio_adicional` decimal(10,2) DEFAULT '0.00',
  `disponible` tinyint(1) DEFAULT '1',
  `orden` int DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_elegible`),
  KEY `idx_producto_elegible` (`id_producto`),
  KEY `idx_disponible` (`disponible`),
  CONSTRAINT `elegibles_producto_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `codigo` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `codigo` (`codigo`),
  KEY `expira` (`expira`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications_repartidores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `codigo` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `codigo` (`codigo`),
  KEY `expira` (`expira`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estado_pedidos` (
  `id_estado_pedido` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `estado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_estado_pedido`),
  KEY `id_pedido` (`id_pedido`),
  CONSTRAINT `estado_pedidos_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estados_pedido` (
  `id_estado` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_estado`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favoritos` (
  `id_favorito` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_negocio` int NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_favorito`),
  UNIQUE KEY `favorito_unico` (`id_usuario`,`id_negocio`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favoritos_mandados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_producto` int NOT NULL,
  `fecha_agregado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_producto` (`id_usuario`,`id_producto`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `favoritos_mandados_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `favoritos_mandados_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favoritos_productos_api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_producto_api` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_producto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `precio` decimal(8,2) DEFAULT NULL,
  `negocio_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_agregado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_producto_api` (`id_usuario`,`id_producto_api`),
  CONSTRAINT `favoritos_productos_api_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fotos_entrega` (
  `id_foto` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_repartidor` int NOT NULL,
  `foto_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ruta de la imagen',
  `latitud` decimal(10,8) DEFAULT NULL COMMENT 'Ubicacion donde se tomo la foto',
  `longitud` decimal(11,8) DEFAULT NULL COMMENT 'Ubicacion donde se tomo la foto',
  `fecha_captura` datetime DEFAULT CURRENT_TIMESTAMP,
  `notas` text COLLATE utf8mb4_unicode_ci COMMENT 'Notas del repartidor sobre la entrega',
  `validada` tinyint(1) DEFAULT '0' COMMENT 'Si fue validada por el sistema/admin',
  `fecha_validacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_foto`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_repartidor` (`id_repartidor`),
  KEY `idx_fecha` (`fecha_captura`),
  CONSTRAINT `fotos_entrega_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `fotos_entrega_ibfk_2` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ganancias_repartidor` (
  `id_ganancia` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_pedido` int NOT NULL,
  `ganancia` decimal(10,2) NOT NULL,
  `fecha_ganancia` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tiempo_entrega` int DEFAULT NULL,
  `es_efectivo` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_ganancia`),
  KEY `id_repartidor` (`id_repartidor`),
  KEY `id_pedido` (`id_pedido`),
  CONSTRAINT `ganancias_repartidor_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`),
  CONSTRAINT `ganancias_repartidor_ibfk_2` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_opciones` (
  `id_grupo_opcion` int NOT NULL AUTO_INCREMENT,
  `id_producto` int NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `obligatorio` tinyint(1) DEFAULT '0',
  `tipo_seleccion` enum('unica','multiple') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'unica',
  `min_selecciones` int DEFAULT '0',
  `max_selecciones` int DEFAULT '1',
  `orden_visualizacion` int DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_grupo_opcion`),
  KEY `id_producto` (`id_producto`),
  KEY `idx_producto_activo` (`id_producto`,`activo`),
  CONSTRAINT `grupos_opciones_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_busquedas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `termino_busqueda` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `categoria` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `resultados_encontrados` int DEFAULT '0',
  `fecha_busqueda` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historial_usuario` (`id_usuario`),
  KEY `idx_historial_fecha` (`fecha_busqueda`),
  CONSTRAINT `historial_busquedas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_estados` (
  `id_historial` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_estado` int NOT NULL,
  `notas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_historial`),
  KEY `id_pedido` (`id_pedido`),
  CONSTRAINT `historial_estados_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_estados_pedido` (
  `id_historial` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_estado` int NOT NULL,
  `notas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_historial`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_estado` (`id_estado`),
  CONSTRAINT `historial_estados_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `historial_estados_pedido_ibfk_2` FOREIGN KEY (`id_estado`) REFERENCES `estados_pedido` (`id_estado`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_niveles_repartidor` (
  `id_historial` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_nivel_anterior` int DEFAULT NULL,
  `id_nivel_nuevo` int NOT NULL,
  `entregas_al_subir` int NOT NULL,
  `calificacion_al_subir` decimal(3,2) DEFAULT NULL,
  `recompensa_otorgada` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notas` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_historial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios_negocio` (
  `id_horario` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `dia_semana` tinyint NOT NULL,
  `hora_apertura` time NOT NULL,
  `hora_cierre` time NOT NULL,
  `cerrado` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_horario`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `horarios_negocio_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_peticiones_api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `tipo_peticion` enum('busqueda','categoria','detalle') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `parametros` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `respuesta_exitosa` tinyint(1) DEFAULT '1',
  `tiempo_respuesta_ms` int DEFAULT NULL,
  `codigo_error` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_peticion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `log_peticiones_api_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logros_repartidor` (
  `id_logro` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 0xF09F8F86,
  `requisito_tipo` enum('pedidos_completados','pedidos_batch','rescates','calificacion','distancia','racha') COLLATE utf8mb4_unicode_ci NOT NULL,
  `requisito_valor` int NOT NULL,
  `bonificacion` decimal(10,2) DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_logro`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membresias` (
  `id_membresia` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `estado` enum('activo','inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `plan` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'monthly' COMMENT 'Plan: monthly o yearly',
  `payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del pago de MercadoPago',
  PRIMARY KEY (`id_membresia`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `membresias_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membresias_negocios` (
  `id_membresia` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `plan` enum('basico','premium') COLLATE utf8mb4_unicode_ci DEFAULT 'basico',
  `precio_pagado` decimal(10,2) DEFAULT '0.00',
  `comision_porcentaje` decimal(5,2) DEFAULT '10.00',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('activa','cancelada','expirada') COLLATE utf8mb4_unicode_ci DEFAULT 'activa',
  `metodo_pago` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_pago` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_renovar` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_membresia`),
  KEY `idx_negocio_estado` (`id_negocio`,`estado`),
  KEY `idx_fecha_fin` (`fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mensajes_chat` (
  `id_mensaje` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `tipo_remitente` enum('usuario','negocio','repartidor','sistema') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_remitente` int NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `leido` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mensaje`),
  KEY `id_pedido` (`id_pedido`),
  CONSTRAINT `mensajes_chat_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metodos_pago` (
  `id_metodo_pago` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo_pago` enum('tarjeta_credito','tarjeta_debito','paypal','efectivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `proveedor` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_cuenta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_vencimiento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `es_predeterminado` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_metodo_pago`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `metodos_pago_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metricas_repartidor` (
  `id_metrica` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `total_pedidos_completados` int DEFAULT '0',
  `total_pedidos_abandonados` int DEFAULT '0',
  `total_pedidos_timeout` int DEFAULT '0',
  `promedio_tiempo_aceptacion` decimal(10,2) DEFAULT '0.00' COMMENT 'Minutos promedio en aceptar',
  `promedio_tiempo_recogida` decimal(10,2) DEFAULT '0.00' COMMENT 'Minutos promedio en recoger',
  `promedio_tiempo_entrega` decimal(10,2) DEFAULT '0.00' COMMENT 'Minutos promedio total',
  `calificacion_promedio` decimal(3,2) DEFAULT '5.00',
  `tasa_cumplimiento` decimal(5,2) DEFAULT '100.00' COMMENT 'Porcentaje de pedidos completados',
  `score_confiabilidad` int DEFAULT '100' COMMENT 'Score 0-100 para priorizar asignaciones',
  `ultima_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_metrica`),
  UNIQUE KEY `id_repartidor` (`id_repartidor`),
  KEY `idx_metricas_score` (`score_confiabilidad` DESC),
  CONSTRAINT `metricas_repartidor_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metricas_rutas` (
  `id_metrica` int NOT NULL AUTO_INCREMENT,
  `id_ruta` int NOT NULL,
  `id_repartidor` int NOT NULL,
  `fecha` date NOT NULL,
  `total_pedidos` int DEFAULT '0',
  `distancia_total` decimal(10,2) DEFAULT '0.00',
  `tiempo_total` int DEFAULT '0',
  `tiempo_promedio_por_pedido` int DEFAULT '0',
  `distancia_promedio_por_pedido` decimal(10,2) DEFAULT '0.00',
  `ganancia_total` decimal(10,2) DEFAULT '0.00',
  `ganancia_por_km` decimal(10,2) DEFAULT '0.00',
  `eficiencia_ruta` decimal(5,2) DEFAULT '0.00',
  PRIMARY KEY (`id_metrica`),
  KEY `idx_ruta` (`id_ruta`),
  KEY `idx_repartidor` (`id_repartidor`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `metricas_rutas_ibfk_1` FOREIGN KEY (`id_ruta`) REFERENCES `rutas_entrega` (`id_ruta`) ON DELETE CASCADE,
  CONSTRAINT `metricas_rutas_ibfk_2` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `negocio_horarios` (
  `id_horario` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `dia_semana` tinyint NOT NULL,
  `hora_apertura` time NOT NULL,
  `hora_cierre` time NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_horario`),
  UNIQUE KEY `unique_negocio_dia` (`id_negocio`,`dia_semana`),
  KEY `idx_negocio_horarios` (`id_negocio`,`dia_semana`,`activo`),
  CONSTRAINT `negocio_horarios_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE,
  CONSTRAINT `negocio_horarios_chk_1` CHECK ((`dia_semana` between 0 and 6))
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `negocios` (
  `id_negocio` int NOT NULL AUTO_INCREMENT,
  `id_propietario` int DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `imagen_portada` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `calle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `colonia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ciudad` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `estado_geografico` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Estado geográfico donde se encuentra el negocio',
  `codigo_postal` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `radio_entrega` int DEFAULT '5',
  `tiempo_preparacion_promedio` int DEFAULT NULL,
  `pedido_minimo` decimal(10,2) DEFAULT '0.00',
  `costo_envio` decimal(10,2) DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `estado_operativo` enum('activo','inactivo','suspendido','pendiente_aprobacion') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `membresia_premium` tinyint(1) DEFAULT '0',
  `fecha_expiracion_membresia` date DEFAULT NULL,
  `permite_mandados` tinyint(1) DEFAULT '1',
  `categoria_negocio` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'restaurante',
  `tiempo_entrega_estimado` int DEFAULT '30',
  `clabe` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'CLABE interbancaria (18 dígitos)',
  `banco` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nombre del banco',
  `titular_cuenta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Titular de la cuenta bancaria',
  `es_premium` tinyint(1) DEFAULT '0' COMMENT 'Si tiene membresía premium activa',
  `comision_porcentaje` decimal(5,2) DEFAULT '10.00' COMMENT 'Comisión actual: 10% básico, 8% premium',
  `fecha_inicio_premium` date DEFAULT NULL,
  `fecha_fin_premium` date DEFAULT NULL,
  `id_plan_membresia` int DEFAULT NULL,
  `cuenta_clabe` varchar(18) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `saldo_deudor` decimal(10,2) DEFAULT '0.00',
  `verificado` tinyint(1) DEFAULT '0',
  `fecha_verificacion` date DEFAULT NULL,
  `badge_premium` tinyint(1) DEFAULT '0',
  `destacado` tinyint(1) DEFAULT '0',
  `orden_destacado` int DEFAULT '0',
  `total_resenas` int DEFAULT '0',
  `rating_promedio` decimal(2,1) DEFAULT '0.0',
  `registro_completado` tinyint(1) DEFAULT '1',
  `acepta_programados` tinyint(1) DEFAULT '1' COMMENT 'Si acepta pedidos programados',
  `tiempo_minimo_programacion` int DEFAULT '60' COMMENT 'Minutos mínimos de anticipación',
  `metodos_pago_aceptados` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'mp_card,efectivo,paypal' COMMENT 'Métodos de pago aceptados separados por coma',
  `siempre_abierto_programados` tinyint(1) DEFAULT '0' COMMENT 'Si es 1, el negocio acepta pedidos programados aunque este cerrado',
  PRIMARY KEY (`id_negocio`),
  KEY `id_propietario` (`id_propietario`),
  KEY `idx_negocio_ubicacion` (`latitud`,`longitud`),
  KEY `idx_negocio_activo` (`activo`),
  KEY `idx_negocios_coordenadas` (`latitud`,`longitud`),
  KEY `idx_negocios_membresia` (`membresia_premium`),
  KEY `idx_negocios_mandados` (`permite_mandados`),
  KEY `idx_estado_operativo` (`estado_operativo`),
  KEY `idx_clabe_negocio` (`clabe`),
  KEY `idx_negocio_premium` (`es_premium`),
  KEY `idx_negocio_destacado` (`destacado`,`orden_destacado`),
  KEY `idx_negocio_verificado` (`verificado`),
  CONSTRAINT `negocios_ibfk_1` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `negocios_aliados` (
  `id_aliado` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_categoria` int NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_portada` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Teocaltiche',
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sitio_web` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `horario_atencion` json DEFAULT NULL,
  `descuento_porcentaje` decimal(5,2) NOT NULL DEFAULT '10.00',
  `tipo_descuento` enum('porcentaje','monto_fijo','producto_gratis') COLLATE utf8mb4_unicode_ci DEFAULT 'porcentaje',
  `monto_descuento` decimal(10,2) DEFAULT NULL,
  `descripcion_descuento` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `condiciones` text COLLATE utf8mb4_unicode_ci,
  `limite_usos_mes` int DEFAULT NULL,
  `solo_primera_vez` tinyint(1) DEFAULT '0',
  `requiere_codigo` tinyint(1) DEFAULT '1',
  `codigo_descuento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_inicio_alianza` date NOT NULL,
  `fecha_fin_alianza` date DEFAULT NULL,
  `estado` enum('activo','pausado','finalizado') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `contacto_nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas_internas` text COLLATE utf8mb4_unicode_ci,
  `veces_usado` int DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_aliado`),
  KEY `idx_categoria_estado` (`id_categoria`,`estado`),
  KEY `idx_ciudad` (`ciudad`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `niveles_repartidor` (
  `id_nivel` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emoji` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entregas_requeridas` int NOT NULL DEFAULT '0',
  `calificacion_minima` decimal(3,2) DEFAULT NULL,
  `recompensa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `orden` int DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_nivel`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `segmento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'todos',
  `enviados` int DEFAULT '0',
  `fallidos` int DEFAULT '0',
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opciones` (
  `id_opcion` int NOT NULL AUTO_INCREMENT,
  `id_grupo_opcion` int NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `precio_adicional` decimal(10,2) DEFAULT '0.00',
  `disponible` tinyint(1) DEFAULT '1',
  `por_defecto` tinyint(1) DEFAULT '0',
  `orden_visualizacion` int DEFAULT '0',
  `imagen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `calorias_adicionales` int DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_opcion`),
  KEY `id_grupo_opcion` (`id_grupo_opcion`),
  KEY `idx_grupo_disponible` (`id_grupo_opcion`,`disponible`),
  KEY `idx_orden` (`orden_visualizacion`),
  CONSTRAINT `opciones_ibfk_1` FOREIGN KEY (`id_grupo_opcion`) REFERENCES `grupos_opciones` (`id_grupo_opcion`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opciones_detalle_pedido` (
  `id_opcion_detalle` int NOT NULL AUTO_INCREMENT,
  `id_detalle_pedido` int NOT NULL,
  `id_opcion` int NOT NULL,
  `id_grupo_opcion` int NOT NULL,
  `cantidad` int DEFAULT '1',
  `precio_adicional` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_opcion_detalle`),
  KEY `id_detalle_pedido` (`id_detalle_pedido`),
  KEY `id_opcion` (`id_opcion`),
  KEY `id_grupo_opcion` (`id_grupo_opcion`),
  CONSTRAINT `opciones_detalle_pedido_ibfk_1` FOREIGN KEY (`id_detalle_pedido`) REFERENCES `detalles_pedido` (`id_detalle_pedido`) ON DELETE CASCADE,
  CONSTRAINT `opciones_detalle_pedido_ibfk_2` FOREIGN KEY (`id_opcion`) REFERENCES `opciones` (`id_opcion`) ON DELETE CASCADE,
  CONSTRAINT `opciones_detalle_pedido_ibfk_3` FOREIGN KEY (`id_grupo_opcion`) REFERENCES `grupos_opciones` (`id_grupo_opcion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opciones_unidad` (
  `id_opcion_unidad` int NOT NULL AUTO_INCREMENT,
  `id_personalizacion` int NOT NULL,
  `id_opcion` int NOT NULL,
  `precio_adicional` decimal(10,2) DEFAULT '0.00',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_opcion_unidad`),
  KEY `id_personalizacion` (`id_personalizacion`),
  KEY `id_opcion` (`id_opcion`),
  CONSTRAINT `opciones_unidad_ibfk_1` FOREIGN KEY (`id_personalizacion`) REFERENCES `personalizacion_unidad` (`id_personalizacion`) ON DELETE CASCADE,
  CONSTRAINT `opciones_unidad_ibfk_2` FOREIGN KEY (`id_opcion`) REFERENCES `opciones` (`id_opcion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `codigo` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id_pedido` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_negocio` int NOT NULL,
  `id_repartidor` int DEFAULT NULL,
  `id_estado` int NOT NULL,
  `id_direccion` int NOT NULL,
  `id_metodo_pago` int DEFAULT NULL,
  `tipo_pedido` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'delivery',
  `pickup_time` time DEFAULT NULL,
  `total_productos` decimal(10,2) NOT NULL,
  `costo_envio` decimal(10,2) NOT NULL,
  `cargo_servicio` decimal(10,2) DEFAULT '0.00',
  `impuestos` decimal(10,2) DEFAULT '0.00',
  `propina` decimal(10,2) DEFAULT '0.00',
  `monto_total` decimal(10,2) NOT NULL,
  `instrucciones_especiales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tiempo_entrega_estimado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tiempo_entrega_real` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega` datetime DEFAULT NULL,
  `tiempo_entrega` int DEFAULT NULL,
  `ganancia` decimal(10,2) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `metodo_pago` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `monto_efectivo` decimal(10,2) DEFAULT '0.00',
  `payment_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_status_detail` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `referencia_externa` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mp_fee` decimal(10,2) DEFAULT '0.00',
  `id_ruta` int DEFAULT NULL,
  `es_entrega_multiple` tinyint(1) DEFAULT '0',
  `fecha_asignacion_repartidor` datetime DEFAULT NULL COMMENT 'Cuando se asignó al repartidor',
  `fecha_aceptacion_repartidor` datetime DEFAULT NULL COMMENT 'Cuando el repartidor confirmó que va en camino',
  `fecha_recogida` datetime DEFAULT NULL COMMENT 'Cuando el repartidor recogió el pedido',
  `timeout_aceptacion_minutos` int DEFAULT '10' COMMENT 'Minutos límite para aceptar',
  `timeout_recogida_minutos` int DEFAULT '20' COMMENT 'Minutos límite para recoger',
  `intentos_asignacion` int DEFAULT '0' COMMENT 'Veces que se ha intentado asignar',
  `prioridad` int DEFAULT '0' COMMENT 'Prioridad de asignación (mayor = más urgente)',
  `motivo_cancelacion` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Razón de cancelación si aplica',
  `id_repartidor_anterior` int DEFAULT NULL COMMENT 'Repartidor anterior si fue reasignado',
  `comision_plataforma` decimal(10,2) DEFAULT '0.00' COMMENT 'Comisión cobrada a negocio',
  `comision_porcentaje` decimal(5,2) DEFAULT '10.00' COMMENT 'Porcentaje aplicado',
  `pago_negocio` decimal(10,2) DEFAULT '0.00' COMMENT 'Monto que recibe el negocio',
  `pago_repartidor` decimal(10,2) DEFAULT '0.00' COMMENT 'Monto que recibe el repartidor',
  `subsidio_envio` decimal(10,2) DEFAULT '0.00' COMMENT 'Envío subsidiado para miembros',
  `foto_entrega_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `foto_entrega_fecha` datetime DEFAULT NULL,
  `es_regalo` tinyint(1) DEFAULT '0',
  `nombre_destinatario` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_cupon` int DEFAULT NULL,
  `descuento_cupon` decimal(10,2) DEFAULT '0.00',
  `es_programado` tinyint(1) DEFAULT '0',
  `fecha_programada` datetime DEFAULT NULL COMMENT 'Fecha y hora deseada de entrega',
  `recordatorio_enviado` tinyint(1) DEFAULT '0' COMMENT 'Si se envió recordatorio al negocio',
  PRIMARY KEY (`id_pedido`),
  KEY `id_direccion` (`id_direccion`),
  KEY `id_metodo_pago` (`id_metodo_pago`),
  KEY `idx_pedidos_usuario` (`id_usuario`),
  KEY `idx_pedidos_negocio` (`id_negocio`),
  KEY `idx_pedidos_repartidor` (`id_repartidor`),
  KEY `idx_pedidos_estado` (`id_estado`),
  KEY `idx_pedidos_fecha` (`fecha_creacion`),
  KEY `idx_ruta` (`id_ruta`),
  KEY `idx_pedidos_timeout` (`fecha_asignacion_repartidor`,`id_estado`),
  KEY `idx_pedidos_prioridad` (`prioridad`,`fecha_creacion`),
  KEY `idx_pedidos_programados` (`es_programado`,`fecha_programada`,`id_estado`),
  KEY `idx_pedidos_ref_externa` (`referencia_externa`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE SET NULL,
  CONSTRAINT `pedidos_ibfk_4` FOREIGN KEY (`id_estado`) REFERENCES `estados_pedido` (`id_estado`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_ibfk_5` FOREIGN KEY (`id_direccion`) REFERENCES `direcciones_usuario` (`id_direccion`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_ibfk_6` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodos_pago` (`id_metodo_pago`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos_disponibles_batch` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_negocio` int NOT NULL,
  `latitud_negocio` decimal(10,8) NOT NULL,
  `longitud_negocio` decimal(10,8) NOT NULL,
  `latitud_entrega` decimal(10,8) NOT NULL,
  `longitud_entrega` decimal(10,8) NOT NULL,
  `distancia_negocio_entrega` decimal(10,2) NOT NULL COMMENT 'KM del negocio al cliente',
  `fecha_listo` datetime NOT NULL COMMENT 'Cuando estará listo para recoger',
  `prioridad` int DEFAULT '0',
  `es_express` tinyint(1) DEFAULT '0',
  `fecha_agregado` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_pedido` (`id_pedido`),
  KEY `idx_batch_negocio` (`id_negocio`),
  KEY `idx_batch_ubicacion` (`latitud_negocio`,`longitud_negocio`),
  KEY `idx_batch_fecha` (`fecha_listo`),
  CONSTRAINT `pedidos_disponibles_batch_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_disponibles_batch_ibfk_2` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos_ruta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ruta` int NOT NULL,
  `id_pedido` int NOT NULL,
  `orden_entrega` int NOT NULL,
  `estado_parada` enum('pendiente','en_camino','recogido','entregado','fallido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `tipo_parada` enum('recoleccion','entrega') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(10,8) NOT NULL,
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `distancia_desde_anterior` decimal(10,2) DEFAULT '0.00',
  `tiempo_desde_anterior` int DEFAULT '0',
  `hora_llegada_estimada` datetime DEFAULT NULL,
  `hora_llegada_real` datetime DEFAULT NULL,
  `hora_salida` datetime DEFAULT NULL,
  `notas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pedido_ruta` (`id_ruta`,`id_pedido`),
  KEY `idx_ruta` (`id_ruta`),
  KEY `idx_pedido` (`id_pedido`),
  KEY `idx_orden` (`orden_entrega`),
  KEY `idx_estado` (`estado_parada`),
  CONSTRAINT `pedidos_ruta_ibfk_1` FOREIGN KEY (`id_ruta`) REFERENCES `rutas_entrega` (`id_ruta`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_ruta_ibfk_2` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_pedido_ruta_insert` AFTER INSERT ON `pedidos_ruta` FOR EACH ROW BEGIN
    UPDATE rutas_entrega 
    SET total_pedidos = total_pedidos + 1
    WHERE id_ruta = NEW.id_ruta;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_pedido_ruta_update` AFTER UPDATE ON `pedidos_ruta` FOR EACH ROW BEGIN
    IF NEW.estado_parada = 'entregado' AND OLD.estado_parada != 'entregado' THEN
        UPDATE rutas_entrega 
        SET pedidos_completados = pedidos_completados + 1
        WHERE id_ruta = NEW.id_ruta;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personalizacion_unidad` (
  `id_personalizacion` int NOT NULL AUTO_INCREMENT,
  `id_detalle_pedido` int NOT NULL,
  `numero_unidad` int NOT NULL DEFAULT '1',
  `id_elegible` int DEFAULT NULL,
  `notas_unidad` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mensaje_tarjeta` text,
  `texto_producto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_personalizacion`),
  UNIQUE KEY `unique_unidad` (`id_detalle_pedido`,`numero_unidad`),
  KEY `id_elegible` (`id_elegible`),
  CONSTRAINT `personalizacion_unidad_ibfk_1` FOREIGN KEY (`id_detalle_pedido`) REFERENCES `detalles_pedido` (`id_detalle_pedido`) ON DELETE CASCADE,
  CONSTRAINT `personalizacion_unidad_ibfk_2` FOREIGN KEY (`id_elegible`) REFERENCES `elegibles_producto` (`id_elegible`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planes_membresia_negocio` (
  `id_plan` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `precio_mensual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `comision_porcentaje` decimal(5,2) NOT NULL DEFAULT '10.00',
  `caracteristicas` json DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_plan`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id_producto` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `id_categoria` int DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `precio` decimal(10,2) NOT NULL,
  `imagen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT '1',
  `destacado` tinyint(1) DEFAULT '0',
  `tiene_elegibles` tinyint(1) DEFAULT '0',
  `orden_visualizacion` int DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `calorias` int DEFAULT NULL COMMENT 'Cantidad de calorías del producto',
  `tiene_opciones_dinamicas` tinyint(1) DEFAULT '0',
  `permite_personalizacion_unidad` tinyint(1) DEFAULT '0',
  `permite_mensaje_tarjeta` tinyint(1) DEFAULT '0',
  `permite_texto_producto` tinyint(1) DEFAULT '0',
  `limite_texto_producto` int DEFAULT '50',
  PRIMARY KEY (`id_producto`),
  KEY `idx_producto_negocio` (`id_negocio`),
  KEY `idx_producto_categoria` (`id_categoria`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE,
  CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_producto` (`id_categoria`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos_populares` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_producto` int NOT NULL,
  `contador_busquedas` int DEFAULT '0',
  `contador_agregados_carrito` int DEFAULT '0',
  `contador_comprados` int DEFAULT '0',
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `productos_populares_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos_populares_api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_producto_api` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre_producto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `categoria` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contador_busquedas` int DEFAULT '0',
  `contador_agregados_carrito` int DEFAULT '0',
  `ultima_busqueda` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_producto_api` (`id_producto_api`),
  KEY `idx_productos_populares_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promociones` (
  `id_promocion` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int DEFAULT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tipo_descuento` enum('porcentaje','monto_fijo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valor_descuento` decimal(10,2) NOT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `monto_pedido_minimo` decimal(10,2) DEFAULT '0.00',
  `monto_descuento_maximo` decimal(10,2) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `limite_uso` int DEFAULT NULL,
  `contador_uso` int DEFAULT '0',
  `activa` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_promocion`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `promociones_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotional_banners` (
  `id_banner` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `imagen_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `enlace_destino` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo_banner` enum('descuento','promocion','nuevo_negocio','evento') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'promocion',
  `descuento_porcentaje` int DEFAULT '0',
  `fecha_inicio` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_fin` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `posicion` int DEFAULT '0',
  `negocio_id` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_banner`),
  KEY `negocio_id` (`negocio_id`),
  CONSTRAINT `promotional_banners_ibfk_1` FOREIGN KEY (`negocio_id`) REFERENCES `negocios` (`id_negocio`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `puntos_redenciones` (
  `id_redencion` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo_redencion` enum('cupon','producto_gratis','sorteo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `puntos_usados` int NOT NULL,
  `fecha_redencion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_redencion`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `puntos_redenciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reasignaciones_pedido` (
  `id_reasignacion` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_repartidor_anterior` int DEFAULT NULL,
  `id_repartidor_nuevo` int DEFAULT NULL,
  `motivo` enum('timeout_aceptacion','timeout_recogida','abandono_voluntario','problema_vehiculo','emergencia','reasignacion_admin','optimizacion_ruta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_reasignacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `iniciado_por` enum('sistema','repartidor','negocio','admin','cliente') COLLATE utf8mb4_unicode_ci DEFAULT 'sistema',
  `id_usuario_iniciador` int DEFAULT NULL COMMENT 'ID del usuario que inició la reasignación',
  PRIMARY KEY (`id_reasignacion`),
  KEY `idx_reasig_pedido` (`id_pedido`),
  KEY `idx_reasig_repartidor_ant` (`id_repartidor_anterior`),
  KEY `idx_reasig_fecha` (`fecha_reasignacion`),
  CONSTRAINT `reasignaciones_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recompensas_repartidor` (
  `id_recompensa` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_nivel` int NOT NULL,
  `recompensa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','enviada','entregada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `direccion_envio` text COLLATE utf8mb4_unicode_ci,
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_envio` date DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `tracking_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_recompensa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referidos` (
  `id_referido` int NOT NULL AUTO_INCREMENT,
  `id_usuario_referente` int NOT NULL,
  `id_usuario_referido` int NOT NULL,
  `fecha_referido` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pedido_realizado` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_referido`),
  UNIQUE KEY `unique_referido` (`id_usuario_referente`,`id_usuario_referido`),
  KEY `id_usuario_referido` (`id_usuario_referido`),
  CONSTRAINT `referidos_ibfk_1` FOREIGN KEY (`id_usuario_referente`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `referidos_ibfk_2` FOREIGN KEY (`id_usuario_referido`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referidos_repartidores` (
  `id_referido` int NOT NULL AUTO_INCREMENT,
  `id_repartidor_referente` int NOT NULL COMMENT 'Repartidor que refiere',
  `id_repartidor_referido` int NOT NULL COMMENT 'Repartidor que fue referido',
  `fecha_referido` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entregas_completadas` int DEFAULT '0' COMMENT 'Entregas completadas por el referido',
  `activo` tinyint(1) DEFAULT '1' COMMENT 'Si el referido sigue activo como repartidor',
  `bonificacion_otorgada` tinyint(1) DEFAULT '0' COMMENT 'Si ya se otorgo la bonificacion al referente',
  `fecha_bonificacion` datetime DEFAULT NULL COMMENT 'Fecha cuando se otorgo la bonificacion',
  PRIMARY KEY (`id_referido`),
  UNIQUE KEY `unique_referido_repartidor` (`id_repartidor_referente`,`id_repartidor_referido`),
  KEY `idx_referente` (`id_repartidor_referente`),
  KEY `idx_referido` (`id_repartidor_referido`),
  CONSTRAINT `referidos_repartidores_ibfk_1` FOREIGN KEY (`id_repartidor_referente`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE,
  CONSTRAINT `referidos_repartidores_ibfk_2` FOREIGN KEY (`id_repartidor_referido`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relacion_negocio_categoria` (
  `id_negocio` int NOT NULL,
  `id_categoria` int NOT NULL,
  PRIMARY KEY (`id_negocio`,`id_categoria`),
  KEY `id_categoria` (`id_categoria`),
  CONSTRAINT `relacion_negocio_categoria_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE,
  CONSTRAINT `relacion_negocio_categoria_ibfk_2` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_negocio` (`id_categoria`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repartidor_capacidad` (
  `id_capacidad` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `max_pedidos_simultaneos` int DEFAULT '5',
  `max_distancia_ruta` decimal(10,2) DEFAULT '10.00',
  `max_tiempo_ruta` int DEFAULT '90',
  `radio_busqueda` decimal(10,2) DEFAULT '5.00',
  `acepta_batch` tinyint(1) DEFAULT '1',
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_capacidad`),
  UNIQUE KEY `unique_repartidor` (`id_repartidor`),
  KEY `idx_repartidor` (`id_repartidor`),
  CONSTRAINT `repartidor_capacidad_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repartidor_logros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `id_logro` int NOT NULL,
  `fecha_desbloqueo` datetime DEFAULT CURRENT_TIMESTAMP,
  `bonificacion_otorgada` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_repartidor_logro` (`id_repartidor`,`id_logro`),
  KEY `id_logro` (`id_logro`),
  CONSTRAINT `repartidor_logros_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE,
  CONSTRAINT `repartidor_logros_ibfk_2` FOREIGN KEY (`id_logro`) REFERENCES `logros_repartidor` (`id_logro`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repartidores` (
  `id_repartidor` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo_vehiculo` enum('bicicleta','motocicleta','coche','camioneta') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `placa_vehiculo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_licencia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `documento_identidad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `disponible` tinyint(1) DEFAULT '0',
  `latitud_actual` decimal(10,8) DEFAULT NULL,
  `longitud_actual` decimal(11,8) DEFAULT NULL,
  `ultima_actualizacion_ubicacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_entregas` int DEFAULT '0',
  `total_ganancias` decimal(10,2) DEFAULT '0.00',
  `tiempo_promedio_entrega` decimal(5,2) DEFAULT '0.00',
  `verification_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_nivel` int DEFAULT '1',
  `calificacion_promedio` decimal(3,2) DEFAULT '5.00',
  `fecha_nivel_actual` date DEFAULT NULL,
  `recompensa_reclamada` tinyint(1) DEFAULT '0',
  `saldo_deuda` decimal(10,2) DEFAULT '0.00',
  `bloqueado_por_deuda` tinyint(1) DEFAULT '0',
  `cuenta_clabe` varchar(18) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `banco` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `titular_cuenta` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_resenas` int DEFAULT '0',
  `rating_promedio_resenas` decimal(2,1) DEFAULT '5.0',
  `codigo_referido` varchar(12) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_referidos` int DEFAULT '0',
  `total_bonificaciones` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id_repartidor`),
  KEY `id_usuario` (`id_usuario`),
  KEY `idx_repartidor_ubicacion` (`latitud_actual`,`longitud_actual`),
  KEY `idx_repartidor_disponible` (`disponible`),
  CONSTRAINT `repartidores_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resenas_pendientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_usuario` int NOT NULL,
  `recordatorio_enviado` tinyint(1) DEFAULT '0',
  `fecha_recordatorio` datetime DEFAULT NULL,
  `resena_completada` tinyint(1) DEFAULT '0',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pedido_usuario` (`id_pedido`,`id_usuario`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `resenas_pendientes_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `resenas_pendientes_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rutas_entrega` (
  `id_ruta` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `estado` enum('activa','en_progreso','completada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'activa',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_finalizacion` datetime DEFAULT NULL,
  `total_pedidos` int DEFAULT '0',
  `pedidos_completados` int DEFAULT '0',
  `distancia_total` decimal(10,2) DEFAULT '0.00',
  `tiempo_estimado` int DEFAULT '0',
  `ganancia_total` decimal(10,2) DEFAULT '0.00',
  `latitud_inicio` decimal(10,8) DEFAULT NULL,
  `longitud_inicio` decimal(10,8) DEFAULT NULL,
  `ruta_optimizada` json DEFAULT NULL,
  `notas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_pedidos` int DEFAULT '4' COMMENT 'Máximo de pedidos en esta ruta',
  `radio_agrupacion_km` decimal(5,2) DEFAULT '3.00' COMMENT 'Radio máximo para agrupar pedidos',
  `tipo_ruta` enum('single','batch','optimizada') COLLATE utf8mb4_unicode_ci DEFAULT 'single',
  `ahorro_distancia_km` decimal(10,2) DEFAULT '0.00' COMMENT 'KM ahorrados vs entregas individuales',
  `bonificacion_batch` decimal(10,2) DEFAULT '0.00' COMMENT 'Bonus por batch delivery',
  PRIMARY KEY (`id_ruta`),
  KEY `idx_repartidor` (`id_repartidor`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_creacion` (`fecha_creacion`),
  CONSTRAINT `rutas_entrega_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `check_ruta_completada` AFTER UPDATE ON `rutas_entrega` FOR EACH ROW BEGIN
    IF NEW.total_pedidos > 0 AND NEW.pedidos_completados >= NEW.total_pedidos AND NEW.estado != 'completada' THEN
        UPDATE rutas_entrega 
        SET estado = 'completada',
            fecha_finalizacion = NOW()
        WHERE id_ruta = NEW.id_ruta;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spei_payments` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del registro',
  `pedido_id` int NOT NULL COMMENT 'ID del pedido asociado',
  `mercadopago_payment_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del pago en MercadoPago',
  `external_reference` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Referencia externa única (QB_SPEI_xxx)',
  `amount` decimal(10,2) NOT NULL COMMENT 'Monto del pago',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email del pagador',
  `clabe` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CLABE interbancaria para la transferencia',
  `bank_info` json DEFAULT NULL COMMENT 'Información bancaria completa (JSON)',
  `ticket_url` text COLLATE utf8mb4_unicode_ci COMMENT 'URL del comprobante/ticket de pago',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Estado: pending, in_process, approved, rejected, cancelled, refunded',
  `status_detail` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Detalle del estado de MercadoPago',
  `expires_at` datetime DEFAULT NULL COMMENT 'Fecha y hora de expiración del pago',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_spei_pedido` (`pedido_id`),
  KEY `idx_spei_mp_payment` (`mercadopago_payment_id`),
  KEY `idx_spei_external_ref` (`external_reference`),
  KEY `idx_spei_status` (`status`),
  KEY `idx_spei_created` (`created_at`),
  KEY `idx_spei_expires` (`expires_at`),
  CONSTRAINT `fk_spei_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de pagos SPEI (transferencia bancaria) via MercadoPago';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_webhooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stripe_event_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `event_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `procesado` tinyint(1) DEFAULT '0',
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stripe_event_id` (`stripe_event_id`),
  KEY `idx_event_id` (`stripe_event_id`),
  KEY `idx_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sugerencias_batch` (
  `id_sugerencia` int NOT NULL AUTO_INCREMENT,
  `id_repartidor` int NOT NULL,
  `pedidos_sugeridos` json NOT NULL COMMENT 'Array de IDs de pedidos',
  `distancia_total_km` decimal(10,2) NOT NULL,
  `tiempo_estimado_min` int NOT NULL,
  `ganancia_estimada` decimal(10,2) NOT NULL,
  `ahorro_vs_individual` decimal(10,2) NOT NULL COMMENT 'Ahorro en KM',
  `score_eficiencia` int NOT NULL COMMENT '0-100, qué tan buena es la ruta',
  `estado` enum('pendiente','aceptada','rechazada','expirada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_expiracion` datetime NOT NULL,
  PRIMARY KEY (`id_sugerencia`),
  KEY `idx_sug_repartidor` (`id_repartidor`,`estado`),
  KEY `idx_sug_expiracion` (`fecha_expiracion`),
  CONSTRAINT `sugerencias_batch_ibfk_1` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `reference_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ubicaciones_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `nombre_ubicacion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Mi ubicación',
  `direccion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `es_principal` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ubicaciones_usuario` (`id_usuario`),
  KEY `idx_ubicaciones_principal` (`es_principal`),
  CONSTRAINT `ubicaciones_usuarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `uso_beneficios_aliados` (
  `id_uso` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_aliado` int NOT NULL,
  `codigo_usado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto_original` decimal(10,2) DEFAULT NULL,
  `descuento_aplicado` decimal(10,2) DEFAULT NULL,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `estado` enum('pendiente','verificado','rechazado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_uso` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_verificacion` datetime DEFAULT NULL,
  `verificado_por` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_uso`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_aliado` (`id_aliado`),
  KEY `idx_fecha` (`fecha_uso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `foto_perfil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'cliente',
  `verification_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `clabe` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'CLABE interbancaria (18 dígitos)',
  `banco` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nombre del banco',
  `titular_cuenta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Titular de la cuenta bancaria',
  `ahorro_total_membresia` decimal(10,2) DEFAULT '0.00' COMMENT 'Total ahorrado por ser miembro',
  `es_miembro` tinyint(1) DEFAULT '0' COMMENT 'Si es miembro QuickBite Club',
  `es_miembro_club` tinyint(1) DEFAULT '0' COMMENT 'Alias de es_miembro',
  `fecha_fin_membresia` date DEFAULT NULL COMMENT 'Fecha de expiración de membresía',
  `google_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_usuarios_email` (`email`),
  KEY `idx_clabe` (`clabe`),
  KEY `idx_google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_negocios_recomendados` AS SELECT 
 1 AS `id_negocio`,
 1 AS `id_propietario`,
 1 AS `nombre`,
 1 AS `logo`,
 1 AS `imagen_portada`,
 1 AS `descripcion`,
 1 AS `telefono`,
 1 AS `email`,
 1 AS `calle`,
 1 AS `numero`,
 1 AS `colonia`,
 1 AS `ciudad`,
 1 AS `estado_geografico`,
 1 AS `codigo_postal`,
 1 AS `latitud`,
 1 AS `longitud`,
 1 AS `radio_entrega`,
 1 AS `tiempo_preparacion_promedio`,
 1 AS `pedido_minimo`,
 1 AS `costo_envio`,
 1 AS `activo`,
 1 AS `estado_operativo`,
 1 AS `fecha_creacion`,
 1 AS `fecha_actualizacion`,
 1 AS `membresia_premium`,
 1 AS `fecha_expiracion_membresia`,
 1 AS `permite_mandados`,
 1 AS `categoria_negocio`,
 1 AS `tiempo_entrega_estimado`,
 1 AS `clabe`,
 1 AS `banco`,
 1 AS `titular_cuenta`,
 1 AS `es_premium`,
 1 AS `comision_porcentaje`,
 1 AS `fecha_inicio_premium`,
 1 AS `fecha_fin_premium`,
 1 AS `id_plan_membresia`,
 1 AS `cuenta_clabe`,
 1 AS `saldo_deudor`,
 1 AS `verificado`,
 1 AS `fecha_verificacion`,
 1 AS `badge_premium`,
 1 AS `destacado`,
 1 AS `orden_destacado`,
 1 AS `total_resenas`,
 1 AS `rating_promedio`,
 1 AS `rating_calculado`,
 1 AS `total_valoraciones`,
 1 AS `prioridad`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_pedidos_timeout` AS SELECT 
 1 AS `id_pedido`,
 1 AS `id_negocio`,
 1 AS `id_repartidor`,
 1 AS `id_estado`,
 1 AS `estado_nombre`,
 1 AS `fecha_asignacion_repartidor`,
 1 AS `fecha_aceptacion_repartidor`,
 1 AS `timeout_aceptacion_minutos`,
 1 AS `timeout_recogida_minutos`,
 1 AS `intentos_asignacion`,
 1 AS `prioridad`,
 1 AS `minutos_desde_asignacion`,
 1 AS `minutos_desde_aceptacion`,
 1 AS `estado_timeout`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_repartidores_disponibles` AS SELECT 
 1 AS `id_repartidor`,
 1 AS `id_usuario`,
 1 AS `nombre`,
 1 AS `telefono`,
 1 AS `latitud`,
 1 AS `longitud`,
 1 AS `disponible`,
 1 AS `vehiculo`,
 1 AS `score`,
 1 AS `tasa_cumplimiento`,
 1 AS `calificacion`,
 1 AS `pedidos_completados`,
 1 AS `pedidos_activos`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_sugerencias_batch_activas` AS SELECT 
 1 AS `id_sugerencia`,
 1 AS `id_repartidor`,
 1 AS `pedidos_sugeridos`,
 1 AS `distancia_total_km`,
 1 AS `tiempo_estimado_min`,
 1 AS `ganancia_estimada`,
 1 AS `ahorro_vs_individual`,
 1 AS `score_eficiencia`,
 1 AS `estado`,
 1 AS `fecha_creacion`,
 1 AS `fecha_expiracion`,
 1 AS `nombre_repartidor`,
 1 AS `num_pedidos`*/;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `valoraciones` (
  `id_valoracion` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_negocio` int NOT NULL,
  `id_repartidor` int DEFAULT NULL,
  `calificacion_negocio` tinyint NOT NULL,
  `calificacion_entrega` tinyint DEFAULT NULL,
  `comentario` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calificacion_repartidor` tinyint DEFAULT NULL,
  `comentario_repartidor` text COLLATE utf8mb4_general_ci,
  `tiempo_entrega_percibido` enum('muy_rapido','rapido','normal','lento','muy_lento') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado_pedido` enum('perfecto','bien','con_problemas','danado') COLLATE utf8mb4_general_ci DEFAULT 'perfecto',
  `foto_resena` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visible` tinyint(1) DEFAULT '1',
  `respuesta_negocio` text COLLATE utf8mb4_general_ci,
  `fecha_respuesta` datetime DEFAULT NULL,
  `util_count` int DEFAULT '0',
  PRIMARY KEY (`id_valoracion`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_negocio` (`id_negocio`),
  KEY `id_repartidor` (`id_repartidor`),
  CONSTRAINT `valoraciones_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `valoraciones_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `valoraciones_ibfk_3` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE,
  CONSTRAINT `valoraciones_ibfk_4` FOREIGN KEY (`id_repartidor`) REFERENCES `repartidores` (`id_repartidor`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_comisiones_pedidos` AS SELECT 
 1 AS `id_pedido`,
 1 AS `id_negocio`,
 1 AS `nombre_negocio`,
 1 AS `es_premium`,
 1 AS `total_productos`,
 1 AS `costo_envio`,
 1 AS `cargo_servicio`,
 1 AS `propina`,
 1 AS `monto_total`,
 1 AS `comision_porcentaje`,
 1 AS `comision_calculada`,
 1 AS `pago_negocio_calculado`,
 1 AS `fecha_creacion`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_pedidos_disponibles_batch` AS SELECT 
 1 AS `id_pedido`,
 1 AS `id_negocio`,
 1 AS `nombre_negocio`,
 1 AS `lat_negocio`,
 1 AS `lng_negocio`,
 1 AS `id_direccion`,
 1 AS `lat_cliente`,
 1 AS `lng_cliente`,
 1 AS `colonia_cliente`,
 1 AS `monto_total`,
 1 AS `fecha_creacion`,
 1 AS `minutos_esperando`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_productos_opciones` AS SELECT 
 1 AS `id_producto`,
 1 AS `producto_nombre`,
 1 AS `precio_base`,
 1 AS `tiene_opciones_dinamicas`,
 1 AS `id_grupo_opcion`,
 1 AS `grupo_nombre`,
 1 AS `grupo_descripcion`,
 1 AS `obligatorio`,
 1 AS `tipo_seleccion`,
 1 AS `min_selecciones`,
 1 AS `max_selecciones`,
 1 AS `grupo_orden`,
 1 AS `total_opciones`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_resumen_comisiones_negocio` AS SELECT 
 1 AS `id_negocio`,
 1 AS `nombre`,
 1 AS `es_premium`,
 1 AS `comision_actual`,
 1 AS `total_pedidos`,
 1 AS `ventas_totales`,
 1 AS `comisiones_totales`,
 1 AS `ganancias_netas`*/;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_rutas_activas` AS SELECT 
 1 AS `id_ruta`,
 1 AS `id_repartidor`,
 1 AS `nombre_repartidor`,
 1 AS `telefono_repartidor`,
 1 AS `estado`,
 1 AS `total_pedidos`,
 1 AS `pedidos_completados`,
 1 AS `distancia_total`,
 1 AS `tiempo_estimado`,
 1 AS `ganancia_total`,
 1 AS `fecha_creacion`,
 1 AS `fecha_inicio`,
 1 AS `minutos_transcurridos`,
 1 AS `paradas_totales`,
 1 AS `paradas_completadas`*/;
SET character_set_client = @saved_cs_client;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `balance` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_negocio` (`id_negocio`),
  CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_auditoria` (
  `id_audit` int NOT NULL AUTO_INCREMENT,
  `id_wallet` int NOT NULL,
  `accion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_audit`),
  KEY `idx_wallet` (`id_wallet`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `wallet_auditoria_ibfk_1` FOREIGN KEY (`id_wallet`) REFERENCES `wallets` (`id_wallet`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_retiros` (
  `id_retiro` int NOT NULL AUTO_INCREMENT,
  `id_wallet` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `clabe` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stripe_transfer_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` enum('procesando','completado','fallido','reversado') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'procesando',
  `proof_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `processed_by` int DEFAULT NULL,
  `status_new` enum('pending','completed','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `razon_fallo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_completacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_retiro`),
  KEY `idx_wallet` (`id_wallet`),
  KEY `idx_estado` (`estado`),
  KEY `idx_stripe_transfer` (`stripe_transfer_id`),
  KEY `idx_fecha` (`fecha_solicitud`),
  CONSTRAINT `wallet_retiros_ibfk_1` FOREIGN KEY (`id_wallet`) REFERENCES `wallets` (`id_wallet`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transacciones` (
  `id_transaccion` int NOT NULL AUTO_INCREMENT,
  `id_wallet` int NOT NULL,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `estado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'completada',
  `id_pedido` int DEFAULT NULL,
  `comision` decimal(10,2) DEFAULT '0.00',
  `referencia_stripe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `es_efectivo` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_transaccion`),
  KEY `idx_wallet` (`id_wallet`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_pedido` (`id_pedido`),
  CONSTRAINT `wallet_transacciones_ibfk_1` FOREIGN KEY (`id_wallet`) REFERENCES `wallets` (`id_wallet`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id_wallet` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `tipo_usuario` enum('business','courier') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cuenta_externa_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `saldo_disponible` decimal(10,2) DEFAULT '0.00',
  `saldo_pendiente` decimal(10,2) DEFAULT '0.00',
  `saldo_total` decimal(10,2) DEFAULT '0.00',
  `estado` enum('activo','bloqueado','suspendido') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'activo',
  `onboarding_completado` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_wallet`),
  UNIQUE KEY `stripe_account_id` (`cuenta_externa_id`),
  UNIQUE KEY `unique_usuario_tipo` (`id_usuario`,`tipo_usuario`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_tipo` (`tipo_usuario`),
  KEY `idx_estado` (`estado`),
  KEY `idx_stripe` (`cuenta_externa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_messages` (
  `id_mensaje` int NOT NULL AUTO_INCREMENT,
  `message_id` varchar(255) NOT NULL,
  `telefono_destino` varchar(20) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo_mensaje` varchar(50) DEFAULT 'nuevo_pedido',
  `referencia_id` int DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'sent',
  `fecha_envio` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mensaje`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_telefono` (`telefono_destino`),
  KEY `idx_referencia` (`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zonas_envio` (
  `id_zona` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `nombre_zona` varchar(100) NOT NULL,
  `costo_envio` decimal(10,2) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `orden` int DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_zona`),
  KEY `idx_negocio` (`id_negocio`),
  CONSTRAINT `zonas_envio_ibfk_1` FOREIGN KEY (`id_negocio`) REFERENCES `negocios` (`id_negocio`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `v_negocios_recomendados`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`quickbite`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_negocios_recomendados` AS select `n`.`id_negocio` AS `id_negocio`,`n`.`id_propietario` AS `id_propietario`,`n`.`nombre` AS `nombre`,`n`.`logo` AS `logo`,`n`.`imagen_portada` AS `imagen_portada`,`n`.`descripcion` AS `descripcion`,`n`.`telefono` AS `telefono`,`n`.`email` AS `email`,`n`.`calle` AS `calle`,`n`.`numero` AS `numero`,`n`.`colonia` AS `colonia`,`n`.`ciudad` AS `ciudad`,`n`.`estado_geografico` AS `estado_geografico`,`n`.`codigo_postal` AS `codigo_postal`,`n`.`latitud` AS `latitud`,`n`.`longitud` AS `longitud`,`n`.`radio_entrega` AS `radio_entrega`,`n`.`tiempo_preparacion_promedio` AS `tiempo_preparacion_promedio`,`n`.`pedido_minimo` AS `pedido_minimo`,`n`.`costo_envio` AS `costo_envio`,`n`.`activo` AS `activo`,`n`.`estado_operativo` AS `estado_operativo`,`n`.`fecha_creacion` AS `fecha_creacion`,`n`.`fecha_actualizacion` AS `fecha_actualizacion`,`n`.`membresia_premium` AS `membresia_premium`,`n`.`fecha_expiracion_membresia` AS `fecha_expiracion_membresia`,`n`.`permite_mandados` AS `permite_mandados`,`n`.`categoria_negocio` AS `categoria_negocio`,`n`.`tiempo_entrega_estimado` AS `tiempo_entrega_estimado`,`n`.`clabe` AS `clabe`,`n`.`banco` AS `banco`,`n`.`titular_cuenta` AS `titular_cuenta`,`n`.`es_premium` AS `es_premium`,`n`.`comision_porcentaje` AS `comision_porcentaje`,`n`.`fecha_inicio_premium` AS `fecha_inicio_premium`,`n`.`fecha_fin_premium` AS `fecha_fin_premium`,`n`.`id_plan_membresia` AS `id_plan_membresia`,`n`.`cuenta_clabe` AS `cuenta_clabe`,`n`.`saldo_deudor` AS `saldo_deudor`,`n`.`verificado` AS `verificado`,`n`.`fecha_verificacion` AS `fecha_verificacion`,`n`.`badge_premium` AS `badge_premium`,`n`.`destacado` AS `destacado`,`n`.`orden_destacado` AS `orden_destacado`,`n`.`total_resenas` AS `total_resenas`,`n`.`rating_promedio` AS `rating_promedio`,coalesce(avg(`v`.`calificacion_negocio`),0) AS `rating_calculado`,count(distinct `v`.`id_valoracion`) AS `total_valoraciones`,(case when ((`n`.`es_premium` = 1) and (`n`.`verificado` = 1)) then 3 when (`n`.`es_premium` = 1) then 2 when (`n`.`verificado` = 1) then 1 else 0 end) AS `prioridad` from (`negocios` `n` left join `valoraciones` `v` on((`n`.`id_negocio` = `v`.`id_negocio`))) where ((`n`.`activo` = 1) and (`n`.`estado_operativo` = 'activo')) group by `n`.`id_negocio` order by `prioridad` desc,`rating_calculado` desc,`total_valoraciones` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_pedidos_timeout`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`quickbite`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_pedidos_timeout` AS select `p`.`id_pedido` AS `id_pedido`,`p`.`id_negocio` AS `id_negocio`,`p`.`id_repartidor` AS `id_repartidor`,`p`.`id_estado` AS `id_estado`,`ep`.`nombre` AS `estado_nombre`,`p`.`fecha_asignacion_repartidor` AS `fecha_asignacion_repartidor`,`p`.`fecha_aceptacion_repartidor` AS `fecha_aceptacion_repartidor`,`p`.`timeout_aceptacion_minutos` AS `timeout_aceptacion_minutos`,`p`.`timeout_recogida_minutos` AS `timeout_recogida_minutos`,coalesce(`p`.`intentos_asignacion`,0) AS `intentos_asignacion`,coalesce(`p`.`prioridad`,0) AS `prioridad`,timestampdiff(MINUTE,`p`.`fecha_asignacion_repartidor`,now()) AS `minutos_desde_asignacion`,timestampdiff(MINUTE,`p`.`fecha_aceptacion_repartidor`,now()) AS `minutos_desde_aceptacion`,(case when ((`p`.`id_estado` = 4) and (`p`.`fecha_aceptacion_repartidor` is null) and (timestampdiff(MINUTE,`p`.`fecha_asignacion_repartidor`,now()) > coalesce(`p`.`timeout_aceptacion_minutos`,10))) then 'TIMEOUT_ACEPTACION' when ((`p`.`id_estado` = 5) and (`p`.`fecha_recogida` is null) and (timestampdiff(MINUTE,`p`.`fecha_aceptacion_repartidor`,now()) > coalesce(`p`.`timeout_recogida_minutos`,20))) then 'TIMEOUT_RECOGIDA' else 'OK' end) AS `estado_timeout` from (`pedidos` `p` join `estados_pedido` `ep` on((`p`.`id_estado` = `ep`.`id_estado`))) where ((`p`.`id_estado` in (4,5)) and (`p`.`id_repartidor` is not null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_repartidores_disponibles`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`quickbite`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_repartidores_disponibles` AS select `r`.`id_repartidor` AS `id_repartidor`,`r`.`id_usuario` AS `id_usuario`,`u`.`nombre` AS `nombre`,`u`.`telefono` AS `telefono`,`r`.`latitud_actual` AS `latitud`,`r`.`longitud_actual` AS `longitud`,`r`.`disponible` AS `disponible`,`r`.`tipo_vehiculo` AS `vehiculo`,coalesce(`m`.`score_confiabilidad`,100) AS `score`,coalesce(`m`.`tasa_cumplimiento`,100) AS `tasa_cumplimiento`,coalesce(`m`.`calificacion_promedio`,5.0) AS `calificacion`,coalesce(`m`.`total_pedidos_completados`,0) AS `pedidos_completados`,(select count(0) from `pedidos` where ((`pedidos`.`id_repartidor` = `r`.`id_repartidor`) and (`pedidos`.`id_estado` in (4,5)))) AS `pedidos_activos` from ((`repartidores` `r` join `usuarios` `u` on((`r`.`id_usuario` = `u`.`id_usuario`))) left join `metricas_repartidor` `m` on((`r`.`id_repartidor` = `m`.`id_repartidor`))) where ((`r`.`disponible` = 1) and (`r`.`activo` = 1)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_sugerencias_batch_activas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`quickbite`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sugerencias_batch_activas` AS select `sb`.`id_sugerencia` AS `id_sugerencia`,`sb`.`id_repartidor` AS `id_repartidor`,`sb`.`pedidos_sugeridos` AS `pedidos_sugeridos`,`sb`.`distancia_total_km` AS `distancia_total_km`,`sb`.`tiempo_estimado_min` AS `tiempo_estimado_min`,`sb`.`ganancia_estimada` AS `ganancia_estimada`,`sb`.`ahorro_vs_individual` AS `ahorro_vs_individual`,`sb`.`score_eficiencia` AS `score_eficiencia`,`sb`.`estado` AS `estado`,`sb`.`fecha_creacion` AS `fecha_creacion`,`sb`.`fecha_expiracion` AS `fecha_expiracion`,`u`.`nombre` AS `nombre_repartidor`,json_length(`sb`.`pedidos_sugeridos`) AS `num_pedidos` from ((`sugerencias_batch` `sb` join `repartidores` `r` on((`sb`.`id_repartidor` = `r`.`id_repartidor`))) join `usuarios` `u` on((`r`.`id_usuario` = `u`.`id_usuario`))) where ((`sb`.`estado` = 'pendiente') and (`sb`.`fecha_expiracion` > now())) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vista_comisiones_pedidos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`quickbite`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_comisiones_pedidos` AS select `p`.`id_pedido` AS `id_pedido`,`p`.`id_negocio` AS `id_negocio`,`n`.`nombre` AS `nombre_negocio`,`n`.`es_premium` AS `es_premium`,`p`.`total_productos` AS `total_productos`,`p`.`costo_envio` AS `costo_envio`,`p`.`cargo_servicio` AS `cargo_servicio`,`p`.`propina` AS `propina`,`p`.`monto_total` AS `monto_total`,(case when (`n`.`es_premium` = 1) then 8.00 else 10.00 end) AS `comision_porcentaje`,(`p`.`total_productos` * (case when (`n`.`es_premium` = 1) then 0.08 else 0.10 end)) AS `comision_calculada`,(`p`.`total_productos` - (`p`.`total_productos` * (case when (`n`.`es_premium` = 1) then 0.08 else 0.10 end))) AS `pago_negocio_calculado`,`p`.`fecha_creacion` AS `fecha_creacion` from (`pedidos` `p` join `negocios` `n` on((`p`.`id_negocio` = `n`.`id_negocio`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vista_pedidos_disponibles_batch`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_pedidos_disponibles_batch` AS select `p`.`id_pedido` AS `id_pedido`,`p`.`id_negocio` AS `id_negocio`,`n`.`nombre` AS `nombre_negocio`,`n`.`latitud` AS `lat_negocio`,`n`.`longitud` AS `lng_negocio`,`p`.`id_direccion` AS `id_direccion`,`d`.`latitud` AS `lat_cliente`,`d`.`longitud` AS `lng_cliente`,`d`.`colonia` AS `colonia_cliente`,`p`.`monto_total` AS `monto_total`,`p`.`fecha_creacion` AS `fecha_creacion`,timestampdiff(MINUTE,`p`.`fecha_creacion`,now()) AS `minutos_esperando` from ((`pedidos` `p` join `negocios` `n` on((`p`.`id_negocio` = `n`.`id_negocio`))) join `direcciones_usuario` `d` on((`p`.`id_direccion` = `d`.`id_direccion`))) where ((`p`.`id_repartidor` is null) and (`p`.`id_estado` = 4) and (`p`.`id_ruta` is null) and (`p`.`tipo_pedido` = 'delivery') and (`p`.`fecha_creacion` >= (now() - interval 2 hour))) order by `p`.`fecha_creacion` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vista_productos_opciones`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_productos_opciones` AS select `p`.`id_producto` AS `id_producto`,`p`.`nombre` AS `producto_nombre`,`p`.`precio` AS `precio_base`,`p`.`tiene_opciones_dinamicas` AS `tiene_opciones_dinamicas`,`g`.`id_grupo_opcion` AS `id_grupo_opcion`,`g`.`nombre` AS `grupo_nombre`,`g`.`descripcion` AS `grupo_descripcion`,`g`.`obligatorio` AS `obligatorio`,`g`.`tipo_seleccion` AS `tipo_seleccion`,`g`.`min_selecciones` AS `min_selecciones`,`g`.`max_selecciones` AS `max_selecciones`,`g`.`orden_visualizacion` AS `grupo_orden`,count(`o`.`id_opcion`) AS `total_opciones` from ((`productos` `p` left join `grupos_opciones` `g` on(((`p`.`id_producto` = `g`.`id_producto`) and (`g`.`activo` = 1)))) left join `opciones` `o` on(((`g`.`id_grupo_opcion` = `o`.`id_grupo_opcion`) and (`o`.`disponible` = 1)))) group by `p`.`id_producto`,`g`.`id_grupo_opcion` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vista_resumen_comisiones_negocio`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`quickbite`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_resumen_comisiones_negocio` AS select `n`.`id_negocio` AS `id_negocio`,`n`.`nombre` AS `nombre`,`n`.`es_premium` AS `es_premium`,(case when (`n`.`es_premium` = 1) then 8.00 else 10.00 end) AS `comision_actual`,count(`p`.`id_pedido`) AS `total_pedidos`,sum(`p`.`total_productos`) AS `ventas_totales`,sum((`p`.`total_productos` * (case when (`n`.`es_premium` = 1) then 0.08 else 0.10 end))) AS `comisiones_totales`,sum((`p`.`total_productos` - (`p`.`total_productos` * (case when (`n`.`es_premium` = 1) then 0.08 else 0.10 end)))) AS `ganancias_netas` from (`negocios` `n` left join `pedidos` `p` on(((`n`.`id_negocio` = `p`.`id_negocio`) and (`p`.`id_estado` = 6)))) group by `n`.`id_negocio` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vista_rutas_activas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_rutas_activas` AS select `r`.`id_ruta` AS `id_ruta`,`r`.`id_repartidor` AS `id_repartidor`,`u`.`nombre` AS `nombre_repartidor`,`u`.`telefono` AS `telefono_repartidor`,`r`.`estado` AS `estado`,`r`.`total_pedidos` AS `total_pedidos`,`r`.`pedidos_completados` AS `pedidos_completados`,`r`.`distancia_total` AS `distancia_total`,`r`.`tiempo_estimado` AS `tiempo_estimado`,`r`.`ganancia_total` AS `ganancia_total`,`r`.`fecha_creacion` AS `fecha_creacion`,`r`.`fecha_inicio` AS `fecha_inicio`,timestampdiff(MINUTE,`r`.`fecha_inicio`,now()) AS `minutos_transcurridos`,count(`pr`.`id`) AS `paradas_totales`,sum((case when (`pr`.`estado_parada` = 'entregado') then 1 else 0 end)) AS `paradas_completadas` from (((`rutas_entrega` `r` join `repartidores` `rep` on((`r`.`id_repartidor` = `rep`.`id_repartidor`))) join `usuarios` `u` on((`rep`.`id_usuario` = `u`.`id_usuario`))) left join `pedidos_ruta` `pr` on((`r`.`id_ruta` = `pr`.`id_ruta`))) where (`r`.`estado` in ('activa','en_progreso')) group by `r`.`id_ruta` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

