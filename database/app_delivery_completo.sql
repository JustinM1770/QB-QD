-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: app_delivery
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.24.04.1

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

--
-- Table structure for table `ahorro_miembros`
--

DROP TABLE IF EXISTS `ahorro_miembros`;
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

--
-- Dumping data for table `ahorro_miembros`
--

LOCK TABLES `ahorro_miembros` WRITE;
/*!40000 ALTER TABLE `ahorro_miembros` DISABLE KEYS */;
/*!40000 ALTER TABLE `ahorro_miembros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banner_clicks`
--

DROP TABLE IF EXISTS `banner_clicks`;
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

--
-- Dumping data for table `banner_clicks`
--

LOCK TABLES `banner_clicks` WRITE;
/*!40000 ALTER TABLE `banner_clicks` DISABLE KEYS */;
/*!40000 ALTER TABLE `banner_clicks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banner_impressions`
--

DROP TABLE IF EXISTS `banner_impressions`;
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

--
-- Dumping data for table `banner_impressions`
--

LOCK TABLES `banner_impressions` WRITE;
/*!40000 ALTER TABLE `banner_impressions` DISABLE KEYS */;
/*!40000 ALTER TABLE `banner_impressions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beneficios_referidos`
--

DROP TABLE IF EXISTS `beneficios_referidos`;
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

--
-- Dumping data for table `beneficios_referidos`
--

LOCK TABLES `beneficios_referidos` WRITE;
/*!40000 ALTER TABLE `beneficios_referidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `beneficios_referidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beneficios_repartidores`
--

DROP TABLE IF EXISTS `beneficios_repartidores`;
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

--
-- Dumping data for table `beneficios_repartidores`
--

LOCK TABLES `beneficios_repartidores` WRITE;
/*!40000 ALTER TABLE `beneficios_repartidores` DISABLE KEYS */;
/*!40000 ALTER TABLE `beneficios_repartidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bonificaciones_repartidor`
--

DROP TABLE IF EXISTS `bonificaciones_repartidor`;
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

--
-- Dumping data for table `bonificaciones_repartidor`
--

LOCK TABLES `bonificaciones_repartidor` WRITE;
/*!40000 ALTER TABLE `bonificaciones_repartidor` DISABLE KEYS */;
/*!40000 ALTER TABLE `bonificaciones_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_aliados`
--

DROP TABLE IF EXISTS `categorias_aliados`;
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

--
-- Dumping data for table `categorias_aliados`
--

LOCK TABLES `categorias_aliados` WRITE;
/*!40000 ALTER TABLE `categorias_aliados` DISABLE KEYS */;
INSERT INTO `categorias_aliados` VALUES (1,'Gimnasio','dumbbell','Gimnasios y centros deportivos',1,1,'2026-01-06 21:30:28'),(2,'Salud','stethoscope','Doctores, dentistas, clínicas',2,1,'2026-01-06 21:30:28'),(3,'Belleza','spa','Estéticas, salones de belleza, spas',3,1,'2026-01-06 21:30:28'),(4,'Entretenimiento','film','Cines, eventos, diversión',4,1,'2026-01-06 21:30:28'),(5,'Farmacia','pills','Farmacias y productos de salud',5,1,'2026-01-06 21:30:28'),(6,'Educación','graduation-cap','Cursos, talleres, escuelas',6,1,'2026-01-06 21:30:28'),(7,'Servicios','tools','Servicios generales',7,1,'2026-01-06 21:30:28'),(8,'Otros','ellipsis-h','Otros negocios',99,1,'2026-01-06 21:30:28');
/*!40000 ALTER TABLE `categorias_aliados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_negocio`
--

DROP TABLE IF EXISTS `categorias_negocio`;
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

--
-- Dumping data for table `categorias_negocio`
--

LOCK TABLES `categorias_negocio` WRITE;
/*!40000 ALTER TABLE `categorias_negocio` DISABLE KEYS */;
INSERT INTO `categorias_negocio` VALUES (4,'Caféteria','Cafés especiales y postres','fa fa-coffee'),(5,'Restaurante','Comida formal e informal','fa fa-utensils'),(6,'Panadería','Pan dulce, bolillos y más','fa fa-bread-slice'),(7,'Heladería','Helados, nieves y paletas','fa fa-ice-cream'),(8,'Taquería','Tacos, salsas y más','fa fa-pepper-hot'),(9,'Frutería','Frutas frescas y jugos','fa fa-apple-alt'),(10,'Pizzería','Pizzas artesanales o clásicas','fa fa-pizza-slice'),(11,'Mariscos','Pescados, camarones, cocteles','fa fa-fish'),(12,'Bebidas','Jugos, refrescos, licuados','fa fa-glass-whiskey'),(13,'Snacks','Botanas, papas, dulces','fa fa-cookie-bite'),(14,'Comida Vegana','Comida sin productos animales','fa fa-seedling'),(15,'Hamburguesas','Hamburguesas y papas','fa fa-hamburger'),(16,'Tés','Tés especiales y postres','fas fa-mug-hot'),(17,'Sushi','Rollo japonés, sashimi y más','fa fa-fish'),(18,'Parrilla','Cortes de carne y asados','fa fa-drumstick-bite'),(19,'Comida China','Platillos orientales clásicos','fa fa-bowl-rice'),(20,'Florería','Arreglos florales y regalos','fa fa-spa'),(21,'Repostería','Pasteles, cupcakes y postres','fa fa-birthday-cake'),(22,'Comida China','Comida asiática tradicional','fa fa-fish');
/*!40000 ALTER TABLE `categorias_negocio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_producto`
--

DROP TABLE IF EXISTS `categorias_producto`;
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

--
-- Dumping data for table `categorias_producto`
--

LOCK TABLES `categorias_producto` WRITE;
/*!40000 ALTER TABLE `categorias_producto` DISABLE KEYS */;
INSERT INTO `categorias_producto` VALUES (5,8,'Platos Principales','Selección de platos italianos clásicos.',0),(6,8,'Antojitos','',0),(7,8,'Tacos','',0),(8,8,'Tamales','',0),(9,8,'Bebidas','',0),(10,9,'Ramo Principal San Valentín','Ramo especial edición San Valentín 2026',1),(11,9,'Ramos de Rosas','Hermosos ramos con rosas en diferentes cantidades y colores',2),(12,9,'Extras para Ramos','Complementos adicionales para personalizar tu ramo',3),(13,9,'Ramos con Dólar','Ramos elegantes con eucalipto dólar',4),(14,9,'Ramos con Gerberas','Ramos coloridos con gerberas frescas',5),(15,9,'Orquídeas','Elegantes orquídeas en diferentes presentaciones',6),(16,9,'Ramos Premium','Ramos exclusivos con flores premium como tulipanes, hortensias y rosa inglesa',7),(17,9,'Arreglos en Base','Arreglos florales en bases de cerámica, madera y cartón rígido',8),(18,9,'Otros Ramos','Ramos variados con diferentes combinaciones de flores',9);
/*!40000 ALTER TABLE `categorias_producto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `codigos_descuento_aliados`
--

DROP TABLE IF EXISTS `codigos_descuento_aliados`;
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

--
-- Dumping data for table `codigos_descuento_aliados`
--

LOCK TABLES `codigos_descuento_aliados` WRITE;
/*!40000 ALTER TABLE `codigos_descuento_aliados` DISABLE KEYS */;
/*!40000 ALTER TABLE `codigos_descuento_aliados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_bonificaciones_repartidor`
--

DROP TABLE IF EXISTS `config_bonificaciones_repartidor`;
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

--
-- Dumping data for table `config_bonificaciones_repartidor`
--

LOCK TABLES `config_bonificaciones_repartidor` WRITE;
/*!40000 ALTER TABLE `config_bonificaciones_repartidor` DISABLE KEYS */;
INSERT INTO `config_bonificaciones_repartidor` VALUES (1,'referido_nuevo',50.00,'Bonificacion por referir un nuevo repartidor que complete 10 entregas',10,1,'2026-01-22 03:29:19'),(2,'referido_activo',25.00,'Bonificacion adicional cuando tu referido complete 50 entregas',50,1,'2026-01-22 03:29:19'),(3,'referido_estrella',100.00,'Bonificacion especial cuando tu referido alcance nivel Oro',NULL,1,'2026-01-22 03:29:19');
/*!40000 ALTER TABLE `config_bonificaciones_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_mandados`
--

DROP TABLE IF EXISTS `configuracion_mandados`;
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

--
-- Dumping data for table `configuracion_mandados`
--

LOCK TABLES `configuracion_mandados` WRITE;
/*!40000 ALTER TABLE `configuracion_mandados` DISABLE KEYS */;
INSERT INTO `configuracion_mandados` VALUES (1,'radio_busqueda_km','5','Radio máximo de búsqueda en kilómetros','2025-07-01 05:16:04'),(2,'precio_membresia_mensual','299.00','Precio mensual de la membresía premium','2025-07-01 05:16:04'),(3,'precio_membresia_anual','2990.00','Precio anual de la membresía premium','2025-07-01 05:16:04'),(4,'comision_mandados','15','Porcentaje de comisión en mandados','2025-07-01 05:16:04'),(5,'tiempo_entrega_maximo','60','Tiempo máximo de entrega en minutos','2025-07-01 05:16:04');
/*!40000 ALTER TABLE `configuracion_mandados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_sistema`
--

DROP TABLE IF EXISTS `configuracion_sistema`;
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

--
-- Dumping data for table `configuracion_sistema`
--

LOCK TABLES `configuracion_sistema` WRITE;
/*!40000 ALTER TABLE `configuracion_sistema` DISABLE KEYS */;
/*!40000 ALTER TABLE `configuracion_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_timeout`
--

DROP TABLE IF EXISTS `configuracion_timeout`;
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

--
-- Dumping data for table `configuracion_timeout`
--

LOCK TABLES `configuracion_timeout` WRITE;
/*!40000 ALTER TABLE `configuracion_timeout` DISABLE KEYS */;
INSERT INTO `configuracion_timeout` VALUES (1,'global',NULL,10,20,3,5.00,2.00,5.00,1),(2,'global',NULL,10,20,3,5.00,2.00,5.00,1),(3,'global',NULL,10,20,3,5.00,2.00,5.00,1);
/*!40000 ALTER TABLE `configuracion_timeout` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cupones`
--

DROP TABLE IF EXISTS `cupones`;
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

--
-- Dumping data for table `cupones`
--

LOCK TABLES `cupones` WRITE;
/*!40000 ALTER TABLE `cupones` DISABLE KEYS */;
INSERT INTO `cupones` VALUES (1,'BIENVENIDO10','Descuento de bienvenida 10%','porcentaje',10.00,100.00,NULL,NULL,0,1,'2026-01-13 16:45:27',NULL,1,1,1,NULL,'2026-01-13 22:45:27','2026-01-13 22:45:27'),(2,'PRIMERACOMPRA','Primera compra $50 de descuento','monto_fijo',50.00,150.00,NULL,NULL,0,1,'2026-01-13 16:45:27',NULL,1,1,1,NULL,'2026-01-13 22:45:27','2026-01-13 22:45:27');
/*!40000 ALTER TABLE `cupones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cupones_negocios`
--

DROP TABLE IF EXISTS `cupones_negocios`;
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

--
-- Dumping data for table `cupones_negocios`
--

LOCK TABLES `cupones_negocios` WRITE;
/*!40000 ALTER TABLE `cupones_negocios` DISABLE KEYS */;
/*!40000 ALTER TABLE `cupones_negocios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cupones_usuarios`
--

DROP TABLE IF EXISTS `cupones_usuarios`;
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

--
-- Dumping data for table `cupones_usuarios`
--

LOCK TABLES `cupones_usuarios` WRITE;
/*!40000 ALTER TABLE `cupones_usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `cupones_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalles_pedido`
--

DROP TABLE IF EXISTS `detalles_pedido`;
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

--
-- Dumping data for table `detalles_pedido`
--

LOCK TABLES `detalles_pedido` WRITE;
/*!40000 ALTER TABLE `detalles_pedido` DISABLE KEYS */;
INSERT INTO `detalles_pedido` VALUES (35,60,7,1,90.00,0.00,NULL,90.00),(36,61,31,1,629.00,0.00,NULL,629.00),(37,62,26,1,10.00,0.00,NULL,10.00),(38,63,26,1,10.00,0.00,NULL,10.00),(39,64,26,1,10.00,0.00,NULL,10.00),(40,65,26,1,10.00,0.00,NULL,10.00),(41,66,26,1,10.00,0.00,NULL,10.00),(42,67,51,1,603.75,0.00,NULL,603.75),(43,68,50,1,471.45,0.00,NULL,471.45),(44,69,81,1,733.95,0.00,NULL,733.95);
/*!40000 ALTER TABLE `detalles_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deudas_comisiones`
--

DROP TABLE IF EXISTS `deudas_comisiones`;
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

--
-- Dumping data for table `deudas_comisiones`
--

LOCK TABLES `deudas_comisiones` WRITE;
/*!40000 ALTER TABLE `deudas_comisiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `deudas_comisiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deudas_comisiones_negocios`
--

DROP TABLE IF EXISTS `deudas_comisiones_negocios`;
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

--
-- Dumping data for table `deudas_comisiones_negocios`
--

LOCK TABLES `deudas_comisiones_negocios` WRITE;
/*!40000 ALTER TABLE `deudas_comisiones_negocios` DISABLE KEYS */;
INSERT INTO `deudas_comisiones_negocios` VALUES (1,7,55,35.00,'2026-01-07 14:13:08',NULL,'pendiente',NULL,NULL),(2,7,56,35.00,'2026-01-07 14:13:45',NULL,'pendiente',NULL,NULL),(3,7,57,45.00,'2026-01-07 14:16:19',NULL,'pendiente',NULL,NULL),(4,7,58,35.00,'2026-01-19 13:39:54',NULL,'pendiente',NULL,NULL);
/*!40000 ALTER TABLE `deudas_comisiones_negocios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `direcciones_usuario`
--

DROP TABLE IF EXISTS `direcciones_usuario`;
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

--
-- Dumping data for table `direcciones_usuario`
--

LOCK TABLES `direcciones_usuario` WRITE;
/*!40000 ALTER TABLE `direcciones_usuario` DISABLE KEYS */;
INSERT INTO `direcciones_usuario` VALUES (1,4,'Casa','Ignacio Vallarta','203','Jardines del sol','Aguascalientes','Aguascalientes','20266',21.86313800,-102.27581000,0,NULL,'2025-04-14 00:41:51','2025-08-20 02:03:51'),(2,4,'Casa','Guanajuato','56','Los Ángeles','Teocaltiche','Jalisco','47204',21.42802400,-102.57254200,1,NULL,'2025-08-19 19:06:47','2025-08-19 19:54:09'),(3,50,'Casa','Guanajuato','56','Los Angeles','TEOCALTICHE','JALISCO','47204',21.41670000,-102.56670000,1,NULL,'2025-08-22 00:49:25',NULL),(4,2,'Casa Test','Av. Prueba','100','Centro','Ciudad','Jalisco','45000',20.65970000,-103.34960000,1,NULL,'2026-01-07 19:57:28',NULL),(5,67,'Casa','Donato guerra','400','El nejayotee','Teocaltiche','Jalisco','47200',21.41670000,-102.56670000,1,NULL,'2026-02-01 14:53:43',NULL),(6,68,'Casa','Donato Guerra','400','El nejayote','Teocaltiche','Jalisco','47200',21.41670000,-102.56670000,0,NULL,'2026-02-02 15:57:14',NULL);
/*!40000 ALTER TABLE `direcciones_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `elegibles_producto`
--

DROP TABLE IF EXISTS `elegibles_producto`;
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

--
-- Dumping data for table `elegibles_producto`
--

LOCK TABLES `elegibles_producto` WRITE;
/*!40000 ALTER TABLE `elegibles_producto` DISABLE KEYS */;
INSERT INTO `elegibles_producto` VALUES (15,9,'Asada',0.00,1,1,'2025-11-30 01:46:30','2025-11-30 01:46:30'),(16,9,'Pollo',0.00,1,2,'2025-11-30 01:46:30','2025-11-30 01:46:30'),(17,19,'Cilantro',0.00,1,1,'2026-01-02 02:28:30','2026-01-02 02:28:30'),(18,19,'Salsa verde',0.00,1,2,'2026-01-02 02:28:30','2026-01-02 02:28:30'),(19,19,'Cebolla',0.00,1,3,'2026-01-02 02:28:30','2026-01-02 02:28:30'),(20,19,'Salsa roja',0.00,1,4,'2026-01-02 02:28:30','2026-01-02 02:28:30'),(21,29,'Pastor',0.00,1,1,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(22,29,'Asada',5.00,1,2,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(23,29,'Suadero',0.00,1,3,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(24,29,'Chorizo',0.00,1,4,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(25,29,'Campechano',5.00,1,5,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(26,29,'Cabeza',0.00,1,6,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(27,29,'Tripa',0.00,1,7,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(28,81,'Violetas',0.00,1,1,'2026-01-23 22:02:16','2026-01-23 22:02:16'),(29,81,'Blancas',0.00,1,2,'2026-01-23 22:02:16','2026-01-23 22:02:16');
/*!40000 ALTER TABLE `elegibles_producto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verifications`
--

DROP TABLE IF EXISTS `email_verifications`;
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

--
-- Dumping data for table `email_verifications`
--

LOCK TABLES `email_verifications` WRITE;
/*!40000 ALTER TABLE `email_verifications` DISABLE KEYS */;
INSERT INTO `email_verifications` VALUES (1,'xdycechola@gmail.com','853983','2025-08-06 22:18:27',0,'2025-08-06 19:00:19'),(11,'james.lafleur@chipimail.com','180866','2025-08-14 02:52:58',0,'2025-08-14 02:37:58'),(12,'rcedillo390171@gmail.com','305957','2025-08-15 21:59:52',0,'2025-08-15 21:44:53'),(13,'lapiazza@gmail.com','104851','2025-08-19 00:20:23',0,'2025-08-19 00:05:23'),(14,'ivanulises.1029@gmail.com','604170','2025-08-19 18:59:45',0,'2025-08-19 18:44:45'),(15,'adelaidamarquez@outlook.es','035037','2025-08-20 03:52:45',0,'2025-08-20 03:37:45'),(16,'marquezadelaida9@gmail.com','182864','2025-08-20 03:57:45',0,'2025-08-20 03:42:45'),(17,'pablomdelc209@gmail.com','167787','2025-08-20 04:23:54',0,'2025-08-20 04:08:54'),(18,'calibre50@gmail.com','119953','2025-08-20 21:25:16',0,'2025-08-20 21:06:20'),(20,'prueba123@gmail.com','115382','2025-08-21 21:52:19',0,'2025-08-21 21:37:19'),(21,'xdyceszn@gmail.com','915248','2025-08-21 23:05:17',0,'2025-08-21 22:46:45'),(23,'xdycestub@gmail.com','622273','2025-08-21 23:09:46',0,'2025-08-21 22:54:46'),(24,'jlf@chipimail.com','975370','2025-09-06 01:42:06',0,'2025-09-06 01:27:06'),(25,'diaz.ibarra.tonanzinmonserrat@cbtis247.edu.mx','751790','2025-09-29 23:55:14',0,'2025-09-29 23:40:14'),(26,'pepin@gmail.com','610582','2025-09-30 06:20:59',0,'2025-09-30 06:05:59'),(27,'23150364@aguascalientes.tecnm.mx','789912','2025-10-26 20:59:23',0,'2025-10-26 20:44:23'),(28,'jm7701@icloud.com','284247','2025-11-20 03:04:26',0,'2025-10-26 20:46:15');
/*!40000 ALTER TABLE `email_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verifications_repartidores`
--

DROP TABLE IF EXISTS `email_verifications_repartidores`;
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

--
-- Dumping data for table `email_verifications_repartidores`
--

LOCK TABLES `email_verifications_repartidores` WRITE;
/*!40000 ALTER TABLE `email_verifications_repartidores` DISABLE KEYS */;
INSERT INTO `email_verifications_repartidores` VALUES (1,'prueba123@gmail.com','549510','2025-08-21 21:40:43',0,'2025-08-21 21:25:43'),(2,'xdyceee@outlook.com','871823','2025-09-09 01:41:09',0,'2025-09-09 01:26:09'),(3,'chuyitacolmen@gmail.com','803436','2025-09-09 02:19:53',0,'2025-09-09 02:04:53'),(4,'gladysedy@gmail.com','267089','2025-09-09 02:49:36',0,'2025-09-09 02:34:36'),(5,'eli_avila9@hotmail.com','419891','2025-09-09 03:01:53',0,'2025-09-09 02:46:54');
/*!40000 ALTER TABLE `email_verifications_repartidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_pedidos`
--

DROP TABLE IF EXISTS `estado_pedidos`;
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

--
-- Dumping data for table `estado_pedidos`
--

LOCK TABLES `estado_pedidos` WRITE;
/*!40000 ALTER TABLE `estado_pedidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `estado_pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estados_pedido`
--

DROP TABLE IF EXISTS `estados_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estados_pedido` (
  `id_estado` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_estado`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estados_pedido`
--

LOCK TABLES `estados_pedido` WRITE;
/*!40000 ALTER TABLE `estados_pedido` DISABLE KEYS */;
INSERT INTO `estados_pedido` VALUES (1,'pendiente','Pedido recibido, esperando confirmación del negocio'),(2,'confirmado','Pedido confirmado por el negocio'),(3,'en_preparacion','El negocio está preparando el pedido'),(4,'listo_para_recoger','Pedido listo para ser recogido por el repartidor'),(5,'en_camino','Pedido en camino con el repartidor'),(6,'entregado','Pedido entregado correctamente'),(7,'cancelado','Pedido cancelado'),(8,'abandonado','Pedido abandonado por el repartidor, buscando nuevo repartidor'),(9,'reasignado','Pedido reasignado a un nuevo repartidor'),(10,'sin_repartidor','No hay repartidores disponibles, esperando');
/*!40000 ALTER TABLE `estados_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favoritos`
--

DROP TABLE IF EXISTS `favoritos`;
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

--
-- Dumping data for table `favoritos`
--

LOCK TABLES `favoritos` WRITE;
/*!40000 ALTER TABLE `favoritos` DISABLE KEYS */;
INSERT INTO `favoritos` VALUES (5,50,9,'2026-01-23 20:04:20');
/*!40000 ALTER TABLE `favoritos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favoritos_mandados`
--

DROP TABLE IF EXISTS `favoritos_mandados`;
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

--
-- Dumping data for table `favoritos_mandados`
--

LOCK TABLES `favoritos_mandados` WRITE;
/*!40000 ALTER TABLE `favoritos_mandados` DISABLE KEYS */;
/*!40000 ALTER TABLE `favoritos_mandados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favoritos_productos_api`
--

DROP TABLE IF EXISTS `favoritos_productos_api`;
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

--
-- Dumping data for table `favoritos_productos_api`
--

LOCK TABLES `favoritos_productos_api` WRITE;
/*!40000 ALTER TABLE `favoritos_productos_api` DISABLE KEYS */;
/*!40000 ALTER TABLE `favoritos_productos_api` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fotos_entrega`
--

DROP TABLE IF EXISTS `fotos_entrega`;
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

--
-- Dumping data for table `fotos_entrega`
--

LOCK TABLES `fotos_entrega` WRITE;
/*!40000 ALTER TABLE `fotos_entrega` DISABLE KEYS */;
/*!40000 ALTER TABLE `fotos_entrega` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ganancias_repartidor`
--

DROP TABLE IF EXISTS `ganancias_repartidor`;
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

--
-- Dumping data for table `ganancias_repartidor`
--

LOCK TABLES `ganancias_repartidor` WRITE;
/*!40000 ALTER TABLE `ganancias_repartidor` DISABLE KEYS */;
/*!40000 ALTER TABLE `ganancias_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grupos_opciones`
--

DROP TABLE IF EXISTS `grupos_opciones`;
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

--
-- Dumping data for table `grupos_opciones`
--

LOCK TABLES `grupos_opciones` WRITE;
/*!40000 ALTER TABLE `grupos_opciones` DISABLE KEYS */;
INSERT INTO `grupos_opciones` VALUES (1,29,'Modificadores','Personaliza tu taco',0,'multiple',0,5,0,1,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(2,39,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:40:29','2026-01-22 00:40:29'),(3,38,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(4,37,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(5,35,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(6,36,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(7,34,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(8,33,'Color de Rosas','Selecciona el color de tus rosas',1,'unica',1,1,1,1,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(9,55,'Color de Gerberas','Selecciona el color de tus gerberas',1,'unica',1,1,1,1,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(10,88,'Color de Tulipanes','Selecciona el color de tus tulipanes',1,'unica',1,1,1,1,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(11,81,'Color de Orquídea','Selecciona el color de tu orquídea',1,'unica',1,1,1,1,'2026-01-24 07:20:03','2026-01-24 07:20:03');
/*!40000 ALTER TABLE `grupos_opciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_busquedas`
--

DROP TABLE IF EXISTS `historial_busquedas`;
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

--
-- Dumping data for table `historial_busquedas`
--

LOCK TABLES `historial_busquedas` WRITE;
/*!40000 ALTER TABLE `historial_busquedas` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_busquedas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_estados`
--

DROP TABLE IF EXISTS `historial_estados`;
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

--
-- Dumping data for table `historial_estados`
--

LOCK TABLES `historial_estados` WRITE;
/*!40000 ALTER TABLE `historial_estados` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_estados_pedido`
--

DROP TABLE IF EXISTS `historial_estados_pedido`;
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

--
-- Dumping data for table `historial_estados_pedido`
--

LOCK TABLES `historial_estados_pedido` WRITE;
/*!40000 ALTER TABLE `historial_estados_pedido` DISABLE KEYS */;
INSERT INTO `historial_estados_pedido` VALUES (1,68,5,'Pedido aceptado por repartidor','2026-02-04 22:34:01'),(2,68,6,'Entrega exitosa','2026-02-04 22:34:11');
/*!40000 ALTER TABLE `historial_estados_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_niveles_repartidor`
--

DROP TABLE IF EXISTS `historial_niveles_repartidor`;
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

--
-- Dumping data for table `historial_niveles_repartidor`
--

LOCK TABLES `historial_niveles_repartidor` WRITE;
/*!40000 ALTER TABLE `historial_niveles_repartidor` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_niveles_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios_negocio`
--

DROP TABLE IF EXISTS `horarios_negocio`;
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

--
-- Dumping data for table `horarios_negocio`
--

LOCK TABLES `horarios_negocio` WRITE;
/*!40000 ALTER TABLE `horarios_negocio` DISABLE KEYS */;
INSERT INTO `horarios_negocio` VALUES (43,7,0,'09:00:00','16:00:00',0),(44,7,1,'09:00:00','22:00:00',0),(45,7,2,'09:00:00','22:00:00',0),(46,7,3,'09:00:00','22:00:00',0),(47,7,4,'09:00:00','22:00:00',0),(48,7,5,'09:00:00','22:00:00',0),(49,7,6,'09:00:00','22:00:00',0),(50,8,0,'10:00:00','21:00:00',1),(51,8,1,'10:00:00','21:00:00',0),(52,8,2,'10:00:00','21:00:00',0),(53,8,3,'10:00:00','21:00:00',0),(54,8,4,'10:00:00','21:00:00',0),(55,8,5,'10:00:00','21:00:00',0),(56,8,6,'10:00:00','21:00:00',0),(57,9,0,'09:00:00','23:00:00',0),(58,9,1,'09:00:00','23:00:00',0),(59,9,2,'09:00:00','23:00:00',0),(60,9,3,'09:00:00','23:00:00',0),(61,9,4,'09:00:00','23:00:00',0),(62,9,5,'09:00:00','23:00:00',0),(63,9,6,'09:00:00','23:00:00',0);
/*!40000 ALTER TABLE `horarios_negocio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_peticiones_api`
--

DROP TABLE IF EXISTS `log_peticiones_api`;
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

--
-- Dumping data for table `log_peticiones_api`
--

LOCK TABLES `log_peticiones_api` WRITE;
/*!40000 ALTER TABLE `log_peticiones_api` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_peticiones_api` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logros_repartidor`
--

DROP TABLE IF EXISTS `logros_repartidor`;
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

--
-- Dumping data for table `logros_repartidor`
--

LOCK TABLES `logros_repartidor` WRITE;
/*!40000 ALTER TABLE `logros_repartidor` DISABLE KEYS */;
INSERT INTO `logros_repartidor` VALUES (1,'Primer Pedido','Completaste tu primer pedido','🎉','pedidos_completados',1,10.00,1),(2,'Repartidor Novato','Completaste 10 pedidos','🌟','pedidos_completados',10,25.00,1),(3,'Repartidor Experto','Completaste 50 pedidos','⭐','pedidos_completados',50,50.00,1),(4,'Repartidor Élite','Completaste 100 pedidos','💫','pedidos_completados',100,100.00,1),(5,'Repartidor Legendario','Completaste 500 pedidos','👑','pedidos_completados',500,250.00,1),(6,'Maestro del Batch','Completaste 10 entregas múltiples','📦','pedidos_batch',10,50.00,1),(7,'Rey del Batch','Completaste 50 entregas múltiples','🚀','pedidos_batch',50,150.00,1),(8,'Héroe del Rescate','Rescataste 5 pedidos abandonados','🦸','rescates',5,75.00,1),(9,'Salvador de Pedidos','Rescataste 20 pedidos abandonados','🏅','rescates',20,200.00,1),(10,'5 Estrellas','Mantuviste calificación perfecta por 30 días','✨','calificacion',30,100.00,1),(11,'Racha de 7','Completaste 7 pedidos seguidos sin rechazar','🔥','racha',7,35.00,1),(12,'Racha de 15','Completaste 15 pedidos seguidos sin rechazar','💥','racha',15,75.00,1),(13,'Maratonista','Recorriste más de 100km en un día','🏃','distancia',100,50.00,1),(14,'Primer Pedido','Completaste tu primer pedido','🎉','pedidos_completados',1,10.00,1),(15,'Repartidor Novato','Completaste 10 pedidos','🌟','pedidos_completados',10,25.00,1),(16,'Repartidor Experto','Completaste 50 pedidos','⭐','pedidos_completados',50,50.00,1),(17,'Repartidor Élite','Completaste 100 pedidos','💫','pedidos_completados',100,100.00,1),(18,'Repartidor Legendario','Completaste 500 pedidos','👑','pedidos_completados',500,250.00,1),(19,'Maestro del Batch','Completaste 10 entregas múltiples','📦','pedidos_batch',10,50.00,1),(20,'Rey del Batch','Completaste 50 entregas múltiples','🚀','pedidos_batch',50,150.00,1),(21,'Héroe del Rescate','Rescataste 5 pedidos abandonados','🦸','rescates',5,75.00,1),(22,'Salvador de Pedidos','Rescataste 20 pedidos abandonados','🏅','rescates',20,200.00,1),(23,'5 Estrellas','Mantuviste calificación perfecta por 30 días','✨','calificacion',30,100.00,1),(24,'Racha de 7','Completaste 7 pedidos seguidos sin rechazar','🔥','racha',7,35.00,1),(25,'Racha de 15','Completaste 15 pedidos seguidos sin rechazar','💥','racha',15,75.00,1),(26,'Maratonista','Recorriste más de 100km en un día','🏃','distancia',100,50.00,1),(27,'Primer Pedido','Completaste tu primer pedido','🎉','pedidos_completados',1,10.00,1),(28,'Repartidor Novato','Completaste 10 pedidos','🌟','pedidos_completados',10,25.00,1),(29,'Repartidor Experto','Completaste 50 pedidos','⭐','pedidos_completados',50,50.00,1),(30,'Repartidor Élite','Completaste 100 pedidos','💫','pedidos_completados',100,100.00,1),(31,'Repartidor Legendario','Completaste 500 pedidos','👑','pedidos_completados',500,250.00,1),(32,'Maestro del Batch','Completaste 10 entregas múltiples','📦','pedidos_batch',10,50.00,1),(33,'Rey del Batch','Completaste 50 entregas múltiples','🚀','pedidos_batch',50,150.00,1),(34,'Héroe del Rescate','Rescataste 5 pedidos abandonados','🦸','rescates',5,75.00,1),(35,'Salvador de Pedidos','Rescataste 20 pedidos abandonados','🏅','rescates',20,200.00,1),(36,'5 Estrellas','Mantuviste calificación perfecta por 30 días','✨','calificacion',30,100.00,1),(37,'Racha de 7','Completaste 7 pedidos seguidos sin rechazar','🔥','racha',7,35.00,1),(38,'Racha de 15','Completaste 15 pedidos seguidos sin rechazar','💥','racha',15,75.00,1),(39,'Maratonista','Recorriste más de 100km en un día','🏃','distancia',100,50.00,1);
/*!40000 ALTER TABLE `logros_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membresias`
--

DROP TABLE IF EXISTS `membresias`;
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

--
-- Dumping data for table `membresias`
--

LOCK TABLES `membresias` WRITE;
/*!40000 ALTER TABLE `membresias` DISABLE KEYS */;
INSERT INTO `membresias` VALUES (2,4,'2025-07-22 00:00:00','2025-08-22 00:00:00','activo','monthly',NULL),(3,48,'2025-08-21 21:45:18','2025-09-21 21:45:18','activo','monthly',NULL),(4,50,'2025-08-22 01:01:50','2025-09-22 01:01:50','activo','monthly',NULL),(5,50,'2025-10-23 23:19:41','2035-10-23 23:19:41','activo','monthly',NULL);
/*!40000 ALTER TABLE `membresias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membresias_negocios`
--

DROP TABLE IF EXISTS `membresias_negocios`;
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

--
-- Dumping data for table `membresias_negocios`
--

LOCK TABLES `membresias_negocios` WRITE;
/*!40000 ALTER TABLE `membresias_negocios` DISABLE KEYS */;
/*!40000 ALTER TABLE `membresias_negocios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensajes_chat`
--

DROP TABLE IF EXISTS `mensajes_chat`;
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

--
-- Dumping data for table `mensajes_chat`
--

LOCK TABLES `mensajes_chat` WRITE;
/*!40000 ALTER TABLE `mensajes_chat` DISABLE KEYS */;
/*!40000 ALTER TABLE `mensajes_chat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metodos_pago`
--

DROP TABLE IF EXISTS `metodos_pago`;
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

--
-- Dumping data for table `metodos_pago`
--

LOCK TABLES `metodos_pago` WRITE;
/*!40000 ALTER TABLE `metodos_pago` DISABLE KEYS */;
INSERT INTO `metodos_pago` VALUES (1,4,'efectivo','','Pago en efectivo','',1);
/*!40000 ALTER TABLE `metodos_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metricas_repartidor`
--

DROP TABLE IF EXISTS `metricas_repartidor`;
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

--
-- Dumping data for table `metricas_repartidor`
--

LOCK TABLES `metricas_repartidor` WRITE;
/*!40000 ALTER TABLE `metricas_repartidor` DISABLE KEYS */;
INSERT INTO `metricas_repartidor` VALUES (1,10,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(2,11,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(3,12,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(4,13,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(5,1,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(6,2,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(7,3,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(8,4,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(9,5,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(10,6,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(11,7,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29'),(12,8,0,0,0,0.00,0.00,0.00,5.00,100.00,100,'2026-01-03 23:51:29');
/*!40000 ALTER TABLE `metricas_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metricas_rutas`
--

DROP TABLE IF EXISTS `metricas_rutas`;
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

--
-- Dumping data for table `metricas_rutas`
--

LOCK TABLES `metricas_rutas` WRITE;
/*!40000 ALTER TABLE `metricas_rutas` DISABLE KEYS */;
/*!40000 ALTER TABLE `metricas_rutas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `negocio_horarios`
--

DROP TABLE IF EXISTS `negocio_horarios`;
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

--
-- Dumping data for table `negocio_horarios`
--

LOCK TABLES `negocio_horarios` WRITE;
/*!40000 ALTER TABLE `negocio_horarios` DISABLE KEYS */;
INSERT INTO `negocio_horarios` VALUES (1,7,0,'09:00:00','16:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(2,7,1,'09:00:00','22:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(3,7,2,'09:00:00','22:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(4,7,3,'09:00:00','22:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(5,7,4,'09:00:00','22:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(6,7,5,'09:00:00','22:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(7,7,6,'09:00:00','22:00:00',1,'2026-01-03 21:46:14','2026-01-03 21:46:14'),(22,9,0,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-23 20:03:13'),(23,9,1,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-23 20:03:13'),(24,9,2,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-23 20:03:13'),(25,9,3,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-25 07:14:20'),(26,9,4,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-23 20:03:13'),(27,9,5,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-23 20:03:13'),(28,9,6,'08:00:00','19:00:00',1,'2026-01-23 20:03:13','2026-01-25 07:14:20'),(29,8,0,'10:00:00','22:00:00',1,'2026-01-25 17:21:31','2026-01-25 17:21:31'),(30,8,1,'10:00:00','22:00:00',1,'2026-01-25 17:21:31','2026-01-25 17:21:31'),(31,8,2,'10:00:00','22:00:00',1,'2026-01-25 17:21:31','2026-01-25 17:21:31'),(32,8,3,'00:00:00','00:00:00',0,'2026-01-25 17:21:31','2026-01-25 17:21:31'),(33,8,4,'10:00:00','22:00:00',1,'2026-01-25 17:21:31','2026-01-25 17:21:31'),(34,8,5,'10:00:00','22:00:00',1,'2026-01-25 17:21:31','2026-01-25 17:21:31'),(35,8,6,'00:00:00','00:00:00',0,'2026-01-25 17:21:31','2026-01-25 17:21:31');
/*!40000 ALTER TABLE `negocio_horarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `negocios`
--

DROP TABLE IF EXISTS `negocios`;
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

--
-- Dumping data for table `negocios`
--

LOCK TABLES `negocios` WRITE;
/*!40000 ALTER TABLE `negocios` DISABLE KEYS */;
INSERT INTO `negocios` VALUES (7,41,'Cremeria Wicho','assets/img/restaurants/default.jpg','assets/img/restaurants/default.jpg','Contamos con gran variedad de jamones, salchicha lácteos abarrote y de mas','3346150551','ivanulises.1029@gmail.com','Emiliano Zapata','3','Centro','Ojuelos','Jalisco','47540',21.86356720,-101.59344160,5,30,50.00,10.00,0,'activo','2025-08-19 18:53:38','2026-01-24 20:50:46',0,NULL,1,'restaurante',30,NULL,NULL,NULL,0,10.00,NULL,NULL,NULL,NULL,150.00,0,NULL,0,0,0,0,0.0,1,1,60,'mp_card,efectivo,paypal',0),(8,61,'Cafe','assets/img/restaurants/logo_691e8484dcbec_cafe.png','assets/img/restaurants/portada_691e8484dcc8c_pizzapiaza.jpg','Cafe rico','4492873740','xdyceszn@gmail.com','moctezuma','23','Los Angeles','TEOCALTICHE','Jalisco','47204',21.42824900,-102.57096800,5,30,0.00,25.00,0,'activo','2025-11-20 03:01:24','2026-01-25 20:17:12',0,NULL,1,'restaurante',30,NULL,NULL,NULL,1,10.00,NULL,NULL,NULL,NULL,0.00,1,'2026-01-09',1,1,1,0,0.0,1,1,60,'mp_card,efectivo,paypal',0),(9,23,'Orez Floristería','assets/img/restaurants/logo_69716b6edcf3b.png','assets/img/restaurants/portada_69716b6ef0f43.png','Floristería especializada en arreglos florales, ramos de rosas, tulipanes, gerberas y más. Edición especial San Valentín 2026.','+52 346 103 5947','contacto@orezfloristeria.com','11 de novimebre','7','Centro','Teocaltiche','Jalisco','47204',21.44020200,-102.57042000,15,45,199.00,70.00,1,'activo','2026-01-22 00:19:53','2026-02-01 17:02:30',1,NULL,1,'floreria',60,NULL,NULL,NULL,1,10.00,NULL,NULL,NULL,NULL,0.00,1,NULL,0,0,0,0,0.0,1,1,60,'mp_card,spei,efectivo',1);
/*!40000 ALTER TABLE `negocios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `negocios_aliados`
--

DROP TABLE IF EXISTS `negocios_aliados`;
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

--
-- Dumping data for table `negocios_aliados`
--

LOCK TABLES `negocios_aliados` WRITE;
/*!40000 ALTER TABLE `negocios_aliados` DISABLE KEYS */;
INSERT INTO `negocios_aliados` VALUES (1,'Gym Fitness Teocaltiche',1,'Gimnasio completo con aparatos y clases grupales',NULL,NULL,NULL,'Teocaltiche',NULL,NULL,NULL,NULL,NULL,NULL,20.00,'porcentaje',NULL,'20% en mensualidad','Válido para nuevos miembros. Mostrar membresía QuickBite Club activa.',NULL,0,1,NULL,'2026-01-06',NULL,'activo',NULL,NULL,NULL,0,'2026-01-06 21:32:03','2026-01-06 21:32:03'),(2,'Dr. García - Medicina General',2,'Consulta médica general y preventiva',NULL,NULL,NULL,'Teocaltiche',NULL,NULL,NULL,NULL,NULL,NULL,15.00,'porcentaje',NULL,'15% primera consulta','Solo primera consulta. Presentar membresía activa.',NULL,0,1,NULL,'2026-01-06',NULL,'activo',NULL,NULL,NULL,0,'2026-01-06 21:32:03','2026-01-06 21:32:03'),(3,'Estética Belleza Total',3,'Cortes, tintes, manicure, pedicure y más',NULL,NULL,NULL,'Teocaltiche',NULL,NULL,NULL,NULL,NULL,NULL,10.00,'porcentaje',NULL,'10% en todos los servicios','No acumulable con otras promociones.',NULL,0,1,NULL,'2026-01-06',NULL,'activo',NULL,NULL,NULL,0,'2026-01-06 21:32:03','2026-01-06 21:32:03'),(4,'Farmacia del Centro',5,'Medicamentos y productos de salud',NULL,NULL,NULL,'Teocaltiche',NULL,NULL,NULL,NULL,NULL,NULL,5.00,'porcentaje',NULL,'5% en compras','Excepto medicamentos controlados.',NULL,0,1,NULL,'2026-01-06',NULL,'activo',NULL,NULL,NULL,0,'2026-01-06 21:32:03','2026-01-06 21:32:03');
/*!40000 ALTER TABLE `negocios_aliados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `niveles_repartidor`
--

DROP TABLE IF EXISTS `niveles_repartidor`;
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

--
-- Dumping data for table `niveles_repartidor`
--

LOCK TABLES `niveles_repartidor` WRITE;
/*!40000 ALTER TABLE `niveles_repartidor` DISABLE KEYS */;
INSERT INTO `niveles_repartidor` VALUES (1,'Nuevo','🆕','#9CA3AF',0,NULL,'Bienvenida al equipo','Repartidor recién registrado',0,1,'2026-01-07 04:01:10'),(2,'Bronce','🥉','#CD7F32',10,NULL,'Chaleco Oficial QuickBite','Completa 10 entregas para ganar tu chaleco oficial',1,1,'2026-01-07 04:01:10'),(3,'Plata','🥈','#C0C0C0',50,4.00,'Mochila Térmica Premium','Completa 50 entregas con buena calificación para ganar tu mochila',2,1,'2026-01-07 04:01:10'),(4,'Oro','🥇','#FFD700',150,4.50,'Chip CFE TEIT Internet Ilimitado','Elite: 150 entregas + calificación 4.5+ = Internet gratis de por vida',3,1,'2026-01-07 04:01:10'),(5,'Diamante','💎','#00D4FF',500,4.80,'Bono mensual + Seguro de gastos médicos','Los mejores repartidores con beneficios exclusivos',4,1,'2026-01-07 04:01:10');
/*!40000 ALTER TABLE `niveles_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones_log`
--

DROP TABLE IF EXISTS `notificaciones_log`;
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

--
-- Dumping data for table `notificaciones_log`
--

LOCK TABLES `notificaciones_log` WRITE;
/*!40000 ALTER TABLE `notificaciones_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opciones`
--

DROP TABLE IF EXISTS `opciones`;
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

--
-- Dumping data for table `opciones`
--

LOCK TABLES `opciones` WRITE;
/*!40000 ALTER TABLE `opciones` DISABLE KEYS */;
INSERT INTO `opciones` VALUES (1,1,'Sin cebolla',NULL,0.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(2,1,'Sin cilantro',NULL,0.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(3,1,'Sin verdura',NULL,0.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(4,1,'Extra salsa verde',NULL,0.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(5,1,'Extra salsa roja',NULL,0.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(6,1,'Con limón',NULL,0.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(7,1,'Extra queso',NULL,8.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(8,1,'Doble carne',NULL,15.00,1,0,0,NULL,0,'2026-01-03 23:26:34','2026-01-03 23:26:34'),(9,2,'Rosas Rojas','Clásicas rosas rojas, símbolo del amor',0.00,1,1,1,'assets/img/products/orez/producto-002.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(10,2,'Rosas Blancas','Elegantes rosas blancas, pureza y paz',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(11,2,'Rosas Rosa','Delicadas rosas rosa, ternura y gratitud',0.00,1,0,3,'assets/img/products/orez/producto-009.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(12,2,'Rosas Mixtas','Combinación de colores vibrantes',25.00,1,0,4,'assets/img/products/orez/producto-010.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(13,3,'Rosas Rojas','Clásicas rosas rojas',0.00,1,1,1,'assets/img/products/orez/producto-004.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(14,3,'Rosas Blancas','Elegantes rosas blancas',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(15,3,'Rosas Rosa','Delicadas rosas rosa',0.00,1,0,3,'assets/img/products/orez/producto-006.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(16,3,'Rosas Mixtas','Combinación de colores',50.00,1,0,4,'assets/img/products/orez/producto-010.png',0,'2026-01-22 00:40:52','2026-01-22 00:40:52'),(17,4,'Rosas Rojas','Clásicas rosas rojas',0.00,1,1,1,'assets/img/products/orez/producto-003.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(18,4,'Rosas Blancas','Elegantes rosas blancas',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(19,4,'Rosas Rosa','Delicadas rosas rosa',0.00,1,0,3,'assets/img/products/orez/producto-025.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(20,4,'Rosas Mixtas Rojo/Blanco','Combinación rojo y blanco',75.00,1,0,4,'assets/img/products/orez/producto-007.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(21,4,'Rosas Mixtas Multicolor','Combinación de varios colores',100.00,1,0,5,'assets/img/products/orez/producto-026.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(22,5,'Rosas Rojas','Clásicas rosas rojas',0.00,1,1,1,'assets/img/products/orez/producto-004.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(23,5,'Rosas Blancas','Elegantes rosas blancas',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(24,5,'Rosas Rosa','Delicadas rosas rosa',0.00,1,0,3,'assets/img/products/orez/producto-025.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(25,5,'Rosas Mixtas Rojo/Rosa','Combinación rojo y rosa',150.00,1,0,4,'assets/img/products/orez/producto-005.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(26,5,'Rosas Mixtas Multicolor','Combinación de varios colores',200.00,1,0,5,'assets/img/products/orez/producto-026.png',0,'2026-01-22 00:42:27','2026-01-22 00:42:27'),(27,6,'Rosas Rojas','Clásicas rosas rojas',0.00,1,1,1,'assets/img/products/orez/producto-004.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(28,6,'Rosas Blancas','Elegantes rosas blancas',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(29,6,'Rosas Rosa','Delicadas rosas rosa',0.00,1,0,3,'assets/img/products/orez/producto-025.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(30,6,'Rosas Mixtas','Combinación de colores',100.00,1,0,4,'assets/img/products/orez/producto-010.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(31,7,'Rosas Rojas','Clásicas rosas rojas',0.00,1,1,1,'assets/img/products/orez/producto-003.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(32,7,'Rosas Blancas','Elegantes rosas blancas',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(33,7,'Rosas Rosa','Delicadas rosas rosa',0.00,1,0,3,'assets/img/products/orez/producto-025.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(34,7,'Rosas Mixtas Premium','Combinación exclusiva de colores',200.00,1,0,4,'assets/img/products/orez/producto-026.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(35,8,'Rosas Rojas','Clásicas rosas rojas',0.00,1,1,1,'assets/img/products/orez/producto-002.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(36,8,'Rosas Blancas','Elegantes rosas blancas',0.00,1,0,2,'assets/img/products/orez/producto-008.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(37,8,'Rosas Rosa','Delicadas rosas rosa',0.00,1,0,3,'assets/img/products/orez/producto-025.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(38,8,'Rosas Mixtas Premium','Combinación exclusiva de colores',300.00,1,0,4,'assets/img/products/orez/producto-026.png',0,'2026-01-22 00:44:45','2026-01-22 00:44:45'),(39,9,'Gerberas Pasteles','Colores suaves y delicados',0.00,1,1,1,'assets/img/products/orez/producto-047.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(40,9,'Gerberas Rojas','Vibrantes gerberas rojas',0.00,1,0,2,'assets/img/products/orez/producto-046.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(41,9,'Gerberas Naranjas','Alegres gerberas naranjas',0.00,1,0,3,'assets/img/products/orez/producto-055.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(42,9,'Gerberas Mixtas','Combinación de colores',50.00,1,0,4,'assets/img/products/orez/producto-048.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(43,10,'Tulipanes Rosados','Delicados tulipanes rosa',0.00,1,1,1,'assets/img/products/orez/producto-086.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(44,10,'Tulipanes Naranjas','Vibrantes tulipanes naranjas',0.00,1,0,2,'assets/img/products/orez/producto-093.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(45,10,'Tulipanes Mixtos','Combinación de colores',50.00,1,0,3,'assets/img/products/orez/producto-088.png',0,'2026-01-22 00:45:02','2026-01-22 00:45:02'),(46,11,'Blancas','Orquídeas de color blanco',0.00,1,1,1,NULL,0,'2026-01-24 07:20:03','2026-01-24 07:20:03'),(47,11,'Violetas','Orquídeas de color violeta',0.00,1,0,2,NULL,0,'2026-01-24 07:20:03','2026-01-24 07:20:03');
/*!40000 ALTER TABLE `opciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opciones_detalle_pedido`
--

DROP TABLE IF EXISTS `opciones_detalle_pedido`;
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

--
-- Dumping data for table `opciones_detalle_pedido`
--

LOCK TABLES `opciones_detalle_pedido` WRITE;
/*!40000 ALTER TABLE `opciones_detalle_pedido` DISABLE KEYS */;
/*!40000 ALTER TABLE `opciones_detalle_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opciones_unidad`
--

DROP TABLE IF EXISTS `opciones_unidad`;
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

--
-- Dumping data for table `opciones_unidad`
--

LOCK TABLES `opciones_unidad` WRITE;
/*!40000 ALTER TABLE `opciones_unidad` DISABLE KEYS */;
/*!40000 ALTER TABLE `opciones_unidad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
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

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,'xdyceszn@gmail.com','832886','2025-08-04 19:29:44',1,'2025-08-04 17:14:44'),(2,'xdyceszn@gmail.com','837850','2025-08-04 19:29:50',1,'2025-08-04 17:14:50'),(3,'xdyceszn@gmail.com','576048','2025-08-04 19:32:20',1,'2025-08-04 17:17:20'),(4,'xdyceszn@gmail.com','957662','2025-08-04 19:49:28',1,'2025-08-04 17:34:28'),(5,'xdyceszn@gmail.com','982807','2025-08-04 19:58:17',0,'2025-08-04 17:43:17'),(6,'xdyceszn@gmail.com','731182','2025-08-04 19:19:48',0,'2025-08-04 19:04:48'),(7,'xdyceszn@gmail.com','590941','2025-08-04 20:09:18',0,'2025-08-04 19:54:18'),(8,'xdyceszn@gmail.com','574497','2025-08-06 18:55:18',0,'2025-08-06 18:40:18'),(9,'orezfloral@gmail.com','716074','2026-02-02 10:06:02',0,'2026-02-02 15:51:02');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
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

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
INSERT INTO `pedidos` VALUES (53,4,7,1,6,1,NULL,'delivery',NULL,350.00,35.00,5.00,0.00,20.00,410.00,NULL,'2026-01-07 20:11:05',NULL,'2026-01-07 20:11:05',NULL,NULL,NULL,'2026-01-07 20:11:05','efectivo',0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(54,4,7,1,6,1,NULL,'delivery',NULL,350.00,35.00,5.00,0.00,20.00,410.00,NULL,'2026-01-07 20:11:26',NULL,'2026-01-07 20:11:26',NULL,NULL,NULL,'2026-01-07 20:11:26','efectivo',0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,35.00,10.00,315.00,55.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(55,4,7,1,6,1,NULL,'delivery',NULL,350.00,35.00,5.00,0.00,20.00,410.00,NULL,'2026-01-07 20:13:08',NULL,'2026-01-07 20:13:08',NULL,NULL,NULL,'2026-01-07 20:13:08','efectivo',0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,35.00,10.00,315.00,55.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(56,4,7,1,6,1,NULL,'delivery',NULL,350.00,35.00,5.00,0.00,20.00,410.00,NULL,'2026-01-07 20:13:45',NULL,'2026-01-07 20:13:45',NULL,NULL,NULL,'2026-01-07 20:13:45','efectivo',0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,35.00,10.00,315.00,55.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(57,4,7,1,6,1,NULL,'delivery',NULL,450.00,40.00,5.00,0.00,25.00,520.00,NULL,'2026-01-07 20:16:19',NULL,'2026-01-07 20:16:19',NULL,NULL,NULL,'2026-01-07 20:16:19','efectivo',0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,45.00,10.00,405.00,65.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(58,4,7,1,6,1,NULL,'delivery',NULL,350.00,35.00,5.00,0.00,20.00,410.00,NULL,'2026-01-19 19:39:54',NULL,'2026-01-19 19:39:54',NULL,NULL,NULL,'2026-01-19 19:39:54','efectivo',0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,35.00,10.00,315.00,55.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(60,50,8,NULL,4,3,NULL,'delivery',NULL,90.00,1.99,0.00,0.00,0.00,91.99,'Tipo de pedido: Delivery (Envío a domicilio)','2026-02-04 22:37:14',NULL,'2026-01-25 02:48:38',NULL,NULL,NULL,'2026-02-04 22:37:14',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,'Cancelado por el usuario desde tracking',NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(61,50,9,NULL,4,3,NULL,'pickup',NULL,629.00,25.00,0.00,0.00,0.00,654.00,'Tipo de pedido: Delivery (Envío a domicilio)','2026-02-04 22:37:26',NULL,'2026-01-25 06:49:30',NULL,NULL,NULL,'2026-02-04 22:37:26',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,'Cancelado por el usuario desde tracking',NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,1,NULL,0),(62,50,8,NULL,7,3,NULL,'pickup','16:30:00',10.00,25.00,0.00,0.00,0.00,35.00,'Tipo de pedido: PickUp (Recoger en tienda)','2026-01-25 18:41:46',NULL,'2026-01-25 18:24:56',NULL,NULL,NULL,'2026-01-25 18:41:46',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(63,50,8,NULL,7,3,NULL,'pickup','16:00:00',10.00,25.00,0.00,0.00,0.00,35.00,'Tipo de pedido: PickUp (Recoger en tienda)','2026-01-25 18:46:24',NULL,'2026-01-25 18:46:24',NULL,NULL,NULL,'2026-01-25 18:46:24',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(64,50,8,NULL,7,3,NULL,'pickup','16:00:00',10.00,25.00,0.00,0.00,0.00,35.00,'Tipo de pedido: PickUp (Recoger en tienda)','2026-01-25 19:09:51',NULL,'2026-01-25 19:09:51',NULL,NULL,NULL,'2026-01-25 19:09:51',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(65,50,8,NULL,7,3,NULL,'delivery',NULL,10.00,25.00,0.00,0.00,0.00,35.00,'Tipo de pedido: Delivery (Envío a domicilio)','2026-01-25 19:16:07',NULL,'2026-01-25 19:16:07',NULL,NULL,NULL,'2026-01-25 19:16:07',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(66,50,8,NULL,7,3,NULL,'pickup','16:00:00',10.00,25.00,0.00,0.00,0.00,35.00,'Tipo de pedido: PickUp (Recoger en tienda)','2026-01-25 19:57:25',NULL,'2026-01-25 19:45:30',NULL,NULL,NULL,'2026-01-25 19:57:25',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(67,50,9,NULL,7,3,NULL,'pickup','18:00:00',603.75,25.00,0.00,0.00,0.00,628.75,'Tipo de pedido: PickUp (Recoger en tienda)','2026-01-25 20:33:15',NULL,'2026-01-25 20:13:06',NULL,NULL,NULL,'2026-01-25 20:33:15',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(68,50,9,2,6,3,NULL,'delivery',NULL,471.45,25.00,0.00,0.00,0.00,496.45,'Tipo de pedido: Delivery (Envío a domicilio)','2026-02-04 22:34:11',NULL,'2026-02-01 14:32:56','2026-02-04 16:34:11',NULL,NULL,'2026-02-04 22:34:11','efectivo',0.00,NULL,'approved',NULL,NULL,0.00,NULL,0,'2026-02-04 16:34:01','2026-02-04 16:34:01',NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0),(69,68,9,NULL,7,6,NULL,'delivery',NULL,733.95,70.00,0.00,0.00,10.00,813.95,'Tipo de pedido: Delivery (Envío a domicilio). 🎁 ES UN REGALO. De parte de: Cesar. 💌 MENSAJE: Te quiero. 📝 Casa blanca de 2 pisos, si no está nadie llamar a 346 102 1896','2026-02-02 15:59:47',NULL,'2026-02-02 15:59:47',NULL,NULL,NULL,'2026-02-02 15:59:47',NULL,0.00,NULL,NULL,NULL,NULL,0.00,NULL,0,NULL,NULL,NULL,10,20,0,0,NULL,NULL,0.00,10.00,0.00,0.00,0.00,NULL,NULL,0,NULL,NULL,0.00,0,NULL,0);
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos_disponibles_batch`
--

DROP TABLE IF EXISTS `pedidos_disponibles_batch`;
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

--
-- Dumping data for table `pedidos_disponibles_batch`
--

LOCK TABLES `pedidos_disponibles_batch` WRITE;
/*!40000 ALTER TABLE `pedidos_disponibles_batch` DISABLE KEYS */;
/*!40000 ALTER TABLE `pedidos_disponibles_batch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos_ruta`
--

DROP TABLE IF EXISTS `pedidos_ruta`;
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

--
-- Dumping data for table `pedidos_ruta`
--

LOCK TABLES `pedidos_ruta` WRITE;
/*!40000 ALTER TABLE `pedidos_ruta` DISABLE KEYS */;
/*!40000 ALTER TABLE `pedidos_ruta` ENABLE KEYS */;
UNLOCK TABLES;
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

--
-- Table structure for table `personalizacion_unidad`
--

DROP TABLE IF EXISTS `personalizacion_unidad`;
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

--
-- Dumping data for table `personalizacion_unidad`
--

LOCK TABLES `personalizacion_unidad` WRITE;
/*!40000 ALTER TABLE `personalizacion_unidad` DISABLE KEYS */;
/*!40000 ALTER TABLE `personalizacion_unidad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planes_membresia_negocio`
--

DROP TABLE IF EXISTS `planes_membresia_negocio`;
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

--
-- Dumping data for table `planes_membresia_negocio`
--

LOCK TABLES `planes_membresia_negocio` WRITE;
/*!40000 ALTER TABLE `planes_membresia_negocio` DISABLE KEYS */;
INSERT INTO `planes_membresia_negocio` VALUES (1,'Básico','Plan gratuito con todas las funciones esenciales',0.00,10.00,'{\"ia_menu\": false, \"panel_admin\": true, \"whatsapp_bot\": false, \"badge_premium\": false, \"aparecer_en_app\": true, \"recibir_pedidos\": true, \"reportes_basicos\": true, \"prioridad_busqueda\": false}',1,1,'2026-01-06 21:32:54','2026-01-06 21:32:54'),(2,'Premium','Comisión reducida + herramientas avanzadas',199.00,8.00,'{\"ia_menu\": true, \"panel_admin\": true, \"whatsapp_bot\": true, \"badge_premium\": true, \"aparecer_en_app\": true, \"recibir_pedidos\": true, \"reportes_basicos\": true, \"prioridad_busqueda\": true, \"reportes_avanzados\": true, \"soporte_prioritario\": true}',1,2,'2026-01-06 21:32:54','2026-01-06 21:32:54');
/*!40000 ALTER TABLE `planes_membresia_negocio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
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

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (7,8,NULL,'Cafe Latte','cafe expresso',90.00,NULL,1,1,0,0,'2025-11-20 03:04:44','2025-11-21 05:02:20',NULL,0,0,0,0,50),(8,8,5,'Lasagna','Pasta, carne molida, salsa de tomate y queso mozzarella.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',700,0,0,0,0,50),(9,8,5,'Spaghetti Carbonara','Espaguetis, panceta, huevos, queso y pimienta negra.',100.00,NULL,1,0,1,0,'2025-11-26 00:59:46','2025-11-30 01:35:34',750,0,0,0,0,50),(10,8,5,'Risotto alla Milanese','Arroz, azafrán, caldo de carne, cebolla y queso parmesano.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',600,0,0,0,0,50),(11,8,5,'Fettuccine Alfredo','Fettuccine, mantequilla y queso parmesano.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',900,0,0,0,0,50),(12,8,5,'Ravioli','Pasta rellena y salsa de tomate con salvia.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',600,0,0,0,0,50),(13,8,5,'Minestrone','Sopa de verduras, frijoles y pasta pequeña.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',350,0,0,0,0,50),(14,8,5,'Pizza Margherita','Salsa de tomate, mozzarella, albahaca y aceite de oliva.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',800,0,0,0,0,50),(15,8,5,'Pesto alla Genovese','Albahaca, piñones, aceite de oliva y queso parmesano.',100.00,NULL,1,0,0,0,'2025-11-26 00:59:46','2025-11-26 00:59:46',800,0,0,0,0,50),(16,8,6,'Empanadas','3 deliciosa empandas rellenas de pollo, carne o cerdo.',85.00,'/public/images/platillos/empanadas_1764802797.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',250,0,0,0,0,50),(17,8,6,'Tacos Dorados','3 tacos dorados bañados en salsa roja o verde y rellenas de pollo.',47.00,'/public/images/platillos/tacos_dorados_1764802797.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',300,0,0,0,0,50),(18,8,6,'Tostadas','Crujientes tostadas de pierna de cerdo, acompañadas con la salsa especial de la casa.',89.00,'/public/images/platillos/tostadas_1764802798.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',400,0,0,0,0,50),(19,8,7,'Arrachera','Orden de 3 tacos acompañados de salsa rojo y verde.',87.00,'/public/images/platillos/arrachera_1764802798.jpg',1,0,1,0,'2025-12-03 23:01:07','2026-01-02 02:28:30',450,0,0,0,0,50),(20,8,7,'Sirloin','Orden de 3 tacos acompañados de salsas y cebolla asada',97.00,'/public/images/platillos/sirloin_1764802798.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',500,0,0,0,0,50),(21,8,7,'Al Pastor','Orden de 4 tacos acompañados de piña asada, cebolla y cilantro.',87.00,'/public/images/platillos/al_pastor_1764802798.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',400,0,0,0,0,50),(22,8,8,'Oaxaqueños','Delicioso tamal tradicional relleno de: Pollo o Cerdo.',75.00,'/public/images/platillos/oaxaque__os_1764802799.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',350,0,0,0,0,50),(23,8,8,'Elote Dulce','Tradicionales tamales de elote dulce.',50.00,'/public/images/platillos/elote_dulce_1764802799.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',200,0,0,0,0,50),(24,8,9,'Refresco en Lata','',25.00,'/public/images/platillos/refresco_en_lata_1764802799.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',150,0,0,0,0,50),(25,8,9,'Refresco en Botella','',25.00,'/public/images/platillos/refresco_en_botella_1764802799.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',150,0,0,0,0,50),(26,8,9,'Agua Fresca de Fruta','',10.00,'/public/images/platillos/agua_fresca_de_fruta_1764802800.jpg',1,1,0,0,'2025-12-03 23:01:07','2026-01-25 17:20:12',100,0,0,0,0,50),(27,8,9,'Café','',25.00,'/public/images/platillos/caf___1764802800.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',5,0,0,0,0,50),(28,8,9,'Cerveza','',45.00,'/public/images/platillos/cerveza_1764802800.jpg',1,0,0,0,'2025-12-03 23:01:07','2025-12-03 23:01:07',150,0,0,0,0,50),(29,8,NULL,'Orden de Tacos (3 pzas)','Deliciosos tacos con la carne de tu elección. Personaliza cada taco individualmente.',45.00,NULL,1,0,1,0,'2026-01-03 23:26:34','2026-01-03 23:26:34',NULL,1,1,0,0,50),(30,9,10,'Amar y Vivir - Ramo Principal','10 rosas, 12 claveles, 6 gerberas, 1 paq de margarita y follaje. Edición especial San Valentín.',1225.00,'assets/img/products/orez/producto-000.png',1,1,0,0,'2026-01-22 00:21:12','2026-01-25 03:20:09',NULL,0,0,1,0,50),(31,9,10,'Amar y Vivir - Mitad Ramo','5 rosas, 6 claveles, 3 gerberas, margaritas y follaje. Versión compacta del ramo principal.',629.00,'assets/img/products/orez/producto-000.png',1,0,0,0,'2026-01-22 00:21:12','2026-01-25 03:20:09',NULL,0,0,1,0,50),(32,9,10,'Amar y Vivir - Doble Ramo','20 rosas, 24 claveles, 12 gerberas, 2 paq de margarita y follaje. Versión XL del ramo principal.',2369.00,'assets/img/products/orez/producto-000.png',1,0,0,0,'2026-01-22 00:21:12','2026-01-25 03:20:09',NULL,0,0,1,0,50),(33,9,11,'Ramo de 200 Rosas','Impresionante ramo con 200 rosas frescas. Disponible en diferentes colores.',6298.95,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,1,'2026-01-22 00:21:38','2026-01-25 03:20:09',NULL,1,0,1,0,50),(34,9,11,'Ramo de 150 Rosas','Espectacular ramo con 150 rosas frescas. Disponible en diferentes colores.',4828.95,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,2,'2026-01-22 00:21:38','2026-01-25 03:20:09',NULL,1,0,1,0,50),(35,9,11,'Ramo de 100 Rosas','Hermoso ramo con 100 rosas frescas. Disponible en diferentes colores.',3358.95,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,3,'2026-01-22 00:21:38','2026-01-25 03:20:09',NULL,1,0,1,0,50),(36,9,11,'Ramo de 75 Rosas','Elegante ramo con 75 rosas frescas. Disponible en diferentes colores.',2518.95,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,4,'2026-01-22 00:21:38','2026-01-25 02:59:39',NULL,1,0,1,0,50),(37,9,11,'Ramo de 50 Rosas','Clásico ramo con 50 rosas frescas. Disponible en diferentes colores.',1804.95,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,5,'2026-01-22 00:21:38','2026-01-25 03:20:09',NULL,1,0,1,0,50),(38,9,11,'Ramo de 25 Rosas','Precioso ramo con 25 rosas frescas. Disponible en diferentes colores.',918.75,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,6,'2026-01-22 00:21:38','2026-01-25 02:59:39',NULL,1,0,1,0,50),(39,9,11,'Ramo de 12 Rosas','Tradicional docena de rosas frescas. Disponible en diferentes colores.',471.45,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,1,0,7,'2026-01-22 00:21:38','2026-01-25 03:20:09',NULL,1,0,1,0,50),(40,9,11,'Ramo de 50 Rosas con 5 Tulipanes','50 rosas combinadas con 5 tulipanes frescos. Combinación especial.',2205.00,'assets/img/productos/OREZ/ROSAS/rosas1.jpeg',1,0,0,8,'2026-01-22 00:21:38','2026-01-25 03:20:09',NULL,1,0,1,0,50),(41,9,12,'Listón con Frase Personalizada','Listón decorativo con mensaje personalizado para tu ramo.',51.45,'assets/img/products/orez/producto-034.png',1,0,0,1,'2026-01-22 00:22:08','2026-01-25 02:59:39',NULL,0,0,0,0,50),(42,9,12,'Orquídea Extra','Añade una hermosa orquídea a tu ramo. Precio por pieza.',89.25,'assets/img/products/orez/producto-035.png',1,0,0,2,'2026-01-22 00:22:08','2026-01-25 02:59:39',NULL,0,0,0,0,50),(43,9,12,'Pliego de Papel Extra','Papel de envoltura adicional para hacer tu ramo más vistoso.',10.50,'assets/img/products/orez/producto-036.png',1,0,0,3,'2026-01-22 00:22:08','2026-01-25 02:59:39',NULL,0,0,0,0,50),(44,9,12,'Follaje Adicional','Añade follaje extra (eucalipto, etc.) a tu arreglo.',78.75,'assets/img/products/orez/producto-037.png',1,0,0,4,'2026-01-22 00:22:08','2026-01-25 02:59:39',NULL,0,0,0,0,50),(45,9,13,'40 Rosas Rojas con Dólar','40 rosas rojas combinadas con eucalipto dólar. Elegante y sofisticado.',1573.95,'/uploads/productos/producto_6973e2062a2e1_40rosascondolar.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 03:20:09',NULL,1,0,1,0,50),(46,9,13,'20 Rosas Rojas con Dólar - Mitad','20 rosas rojas con eucalipto dólar. Versión compacta.',891.45,'/uploads/productos/producto_6973e24a0bfa1_40rosascondolar.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(47,9,13,'20 Rosas Melón con Dólar','20 rosas color melón con eucalipto dólar. Tonos cálidos y elegantes.',838.95,'/uploads/productos/producto_6973e261e308d_20rosasmeloncd.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(48,9,13,'10 Rosas Melón con Dólar - Mitad','10 rosas melón con eucalipto dólar. Versión compacta.',471.45,'/uploads/productos/producto_6973e2a730df5_20rosasmeloncd.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(49,9,13,'20 Rosas Rosita Pastel con Dólar','20 rosas rosa pastel con eucalipto dólar. Delicado y romántico.',838.95,'/uploads/productos/producto_6973e2bbabea4_20rosasrositapastelcdl.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(50,9,13,'10 Rosas Rosita Pastel con Dólar - Mitad','10 rosas rosa pastel con eucalipto dólar. Versión compacta.',471.45,'/uploads/productos/producto_6973e2d5c0f6c_20rosasrositapastelcdl.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(51,9,13,'14 Rosas Mixtas con Dólar','14 rosas en colores mixtos con eucalipto dólar. Colorido y especial.',603.75,'/uploads/productos/producto_6973e2e7350fc_14rosasmixtascondolar.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(52,9,13,'7 Rosas Mixtas con Dólar - Mitad','7 rosas mixtas con eucalipto dólar. Versión compacta.',345.45,'/uploads/productos/producto_6973e300af690_14rosasmixtascondolar.jpeg',1,0,0,0,'2026-01-22 00:22:27','2026-01-25 02:59:39',NULL,1,0,1,0,50),(53,9,14,'30 Rosas, 8 Gerberas y Follaje','Combinación de 30 rosas con 8 gerberas y follaje decorativo.',1647.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/30rosas8gerbyfollaje.jpeg',1,0,0,0,'2026-01-22 00:23:11','2026-01-25 03:20:09',NULL,0,0,1,0,50),(54,9,14,'15 Rosas, 4 Gerberas y Follaje - Mitad','15 rosas, 4 gerberas y follaje. Versión compacta.',870.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/30rosas8gerbyfollaje.jpeg',1,0,0,0,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(55,9,14,'15 Gerberas Pasteles','15 gerberas en tonos pastel. Colorido y alegre.',786.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/15GerbPast.jpeg',1,0,0,0,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(56,9,14,'8 Gerberas Pasteles - Mitad','8 gerberas en tonos pastel. Versión compacta.',471.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/15GerbPast.jpeg',1,0,0,4,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(57,9,14,'Gerbera, Claveles, Rosa, Astromelia y Follaje','Mix de gerberas, claveles, rosas, astromelia y follaje.',576.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbClavelRosaAstromyFllje.jpeg',1,0,0,5,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(58,9,14,'Gerbera, Claveles, Rosa, Astromelia - Doble','Versión doble del mix con más flores.',1048.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbClavelRosaAstromyFllje.jpeg',1,0,0,6,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(59,9,14,'7 Gerberas con Envoltura Moderna','7 gerberas con presentación moderna y elegante.',450.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/7gerbconenvoltmodern.jpeg',1,0,0,7,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(60,9,14,'14 Gerberas con Envoltura Moderna - Doble','14 gerberas con presentación moderna. Versión doble.',891.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/7gerbconenvoltmodern.jpeg',1,0,0,8,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(61,9,14,'6 Gerberas, Statice y Follaje','6 gerberas combinadas con statice y follaje.',435.75,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/6GerbstatceYFllje.jpeg',1,0,0,9,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(62,9,14,'12 Gerberas, Statice y Follaje - Doble','12 gerberas con statice y follaje. Versión doble.',786.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/6GerbstatceYFllje.jpeg',1,0,0,10,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(63,9,14,'Gerbera, Hortensia, Mini Rosa, Rosa y Follaje','Combinación especial de gerbera, hortensia, mini rosa y follaje.',1363.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbhortMiniRsRsFllje.jpeg',1,1,0,11,'2026-01-22 00:23:11','2026-01-25 03:20:09',NULL,0,0,1,0,50),(64,9,14,'Gerbera, Hortensia, Mini Rosa - Mitad','Versión compacta del ramo premium.',712.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbhortMiniRsRsFllje.jpeg',1,0,0,12,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(65,9,14,'Gerberas, Rosas y Follaje','Combinación clásica de gerberas con rosas y follaje.',288.75,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosFllje.jpeg',1,0,0,13,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(66,9,14,'Gerberas, Rosas y Follaje - Doble','Versión doble con más gerberas y rosas.',523.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosFllje.jpeg',1,0,0,14,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(67,9,14,'Gerberas, Rosa, Lisianthus, Astromelia y Follaje','Mix elegante con gerberas, rosa, lisianthus y astromelia.',838.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosLisiaAstroFllje.jpeg',1,0,0,15,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(68,9,14,'Gerberas, Rosa, Lisianthus, Astromelia - Doble','Versión doble del mix elegante.',1500.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosLisiaAstroFllje.jpeg',1,0,0,16,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(69,9,14,'3 Gerberas con Follaje','Pequeño ramo con 3 gerberas y follaje. Perfecto para detalles.',208.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/3GerbFllje.jpeg',1,0,0,17,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(70,9,14,'6 Gerberas con Follaje - Doble','6 gerberas con follaje. Versión doble.',397.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/3GerbFllje.jpeg',1,0,0,18,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(71,9,14,'Gerberas, Mathiola, Lisianthus y Rosa','Combinación aromática con mathiola, lisianthus y rosa.',576.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbMathLisianRos.jpeg',1,0,0,19,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(72,9,14,'Gerberas, Mathiola, Lisianthus y Rosa - Doble','Versión doble de la combinación aromática.',1048.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbMathLisianRos.jpeg',1,0,0,20,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(73,9,14,'Gerberas, Rosas, Claveles y Follaje','Mix de gerberas, rosas, claveles y follaje.',628.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosClavFllje.jpeg',1,0,0,21,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(74,9,14,'Gerberas, Rosas, Claveles - Mitad','Versión compacta del mix.',341.25,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosClavFllje.jpeg',1,0,0,22,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(75,9,14,'Gerberas, Claveles y Follaje','Combinación de gerberas con claveles y follaje.',450.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbClavFllje.jpeg',1,0,0,23,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(76,9,14,'Gerberas, Claveles y Follaje - Doble','Versión doble con más flores.',838.95,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbClavFllje.jpeg',1,0,0,24,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(77,9,14,'Gerberas, Rosas, Claveles y Margaritas','Mix variado con gerberas, rosas, claveles y margaritas.',750.75,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosClaveMarg.jpeg',1,0,0,25,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(78,9,14,'Gerberas, Rosas, Claveles y Margaritas - Mitad','Versión compacta del mix variado.',408.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbRosClaveMarg.jpeg',1,0,0,26,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(79,9,14,'Gerbera Pompón, Arcadia y Follaje','Gerberas pompón con arcadia y follaje. Textura única.',597.45,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbPompArcaFllje.jpeg',1,0,0,27,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(80,9,14,'Gerbera Pompón, Arcadia y Follaje - Mitad','Versión compacta del ramo con textura.',330.75,'assets/img/productos/OREZ/ROSAS/ramos con gerberas/GerbPompArcaFllje.jpeg',1,0,0,28,'2026-01-22 00:23:11','2026-01-25 02:59:39',NULL,0,0,1,0,50),(81,9,15,'Orquídea con Base de Cerámica','Elegante orquídea en maceta de cerámica. Dura mucho más que flores cortadas.',733.95,'assets/img/productos/OREZ/ROSAS/Orquideas/OrquBLyVIOL.jpeg',1,1,1,1,'2026-01-22 00:23:33','2026-01-25 03:20:09',NULL,0,0,1,0,50),(82,9,16,'5 Paq Mini Rosa, Nube y 15 Rosas Rojas','Espectacular combinación de mini rosas, nube y 15 rosas rojas.',2098.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/5paqMiniRsN15RR.jpeg',1,0,0,1,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,1,0,1,0,50),(83,9,16,'5 Paq Mini Rosa, Nube y 15 Rosas - Mitad','Versión compacta de la combinación premium.',1311.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/5paqMiniRsN15RR.jpeg',1,0,0,2,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(84,9,16,'1 Paq Rosa Inglesa, Rosas, Claveles y Follaje','Ramo con rosa inglesa, rosas, claveles y follaje.',1349.25,'assets/img/productos/OREZ/ROSAS/RamosPremium/1PRsINGLRsClaFllje.jpeg',1,0,0,3,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(85,9,16,'Tulipanes, Mini Rosa, Rosa, Claveles y Follaje','Combinación de tulipanes con mini rosas, rosas y claveles.',1048.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/TulMRRosClaFllje.jpeg',1,0,0,4,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(86,9,16,'1 Paq Lisianthus y 1 Paq Rosa Inglesa','Combinación elegante de lisianthus con rosa inglesa.',1521.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/1PLisia1PRINGL.jpeg',1,0,0,5,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(87,9,16,'3 Tulipanes con Follaje','3 tulipanes frescos con follaje decorativo.',418.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/TulpFllje.jpeg',1,0,0,6,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(88,9,16,'7 Tulipanes con Follaje','7 tulipanes frescos con follaje decorativo.',922.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/7TulFllje.jpeg',1,1,0,7,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(89,9,16,'20 Tulipanes, Mini Rosa y Follaje','20 tulipanes con mini rosas y follaje. Espectacular.',2413.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/20TulMRFllje.jpeg',1,0,0,8,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(90,9,16,'40 Tulipanes con Follaje','40 tulipanes frescos con follaje. Impresionante.',3988.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/40TulFllje.jpeg',1,0,0,9,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(91,9,16,'20 Tulipanes con Follaje - Mitad','20 tulipanes con follaje. Versión media.',2098.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/40TulFllje.jpeg',1,0,0,10,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(92,9,16,'10 Tulipanes y Follaje','10 tulipanes frescos con follaje decorativo.',1311.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/TulpFllje.jpeg',1,0,0,11,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(93,9,16,'2 Tulipanes con Follaje y Flor','2 tulipanes con follaje y flores complementarias.',330.75,'assets/img/productos/OREZ/ROSAS/RamosPremium/2TulFlljeFLOR.jpeg',1,0,0,12,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(94,9,16,'5 Tulipanes y 2 Gerberas','Combinación de 5 tulipanes con 2 gerberas.',719.25,'assets/img/productos/OREZ/ROSAS/RamosPremium/5Tul2Gerb.jpeg',1,0,0,13,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(95,9,16,'10 Tulipanes, 9 Rosas y Follaje','Combinación de tulipanes con rosas y follaje.',1521.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/10Tul9RsFllje.jpeg',1,0,0,14,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(96,9,16,'4 Tulipanes, Rosas, Mini Rosas y Varias Flores','Mix de tulipanes con rosas, mini rosas y flores variadas.',786.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/4TulRMRVF.jpeg',1,0,0,15,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(97,9,16,'5 Tulipanes, 1 Paq Lisianthus, 1 Paq Mini Rosa','Combinación premium de tulipanes, lisianthus y mini rosa.',1468.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/5Tul1PLisia1PMR.jpeg',1,0,0,16,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(98,9,16,'10 Tulipanes, 9 Rosas y Follaje (Premium)','Versión premium del ramo de tulipanes y rosas.',1384.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/10Tul9RsFllje.jpeg',1,0,0,17,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(99,9,16,'75 Rosas y 10 Tulipanes','Espectacular ramo con 75 rosas y 10 tulipanes.',3411.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/75R10Tul.jpeg',1,0,0,18,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(100,9,16,'75 Rosas y 10 Tulipanes - Mitad','37 rosas y 5 tulipanes. Versión media.',1783.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/75R10Tul.jpeg',1,0,0,19,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(101,9,16,'7 Tulipanes, 70 Rosas y 5 Orquídeas','Ramo de lujo con tulipanes, rosas y orquídeas.',3621.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/7Tul70Rs5Orq.jpeg',1,0,0,20,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(102,9,16,'4 Tulipanes, 35 Rosas y 2 Orquídeas - Mitad','Versión media del ramo de lujo.',1920.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/7Tul70Rs5Orq.jpeg',1,0,0,21,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(103,9,16,'35 Rosas, 7 Tulipanes, 1 Paq Mini Rosa y Follaje','Combinación de rosas, tulipanes y mini rosas.',2361.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/35R7Tul1PMRFllje.jpeg',1,0,0,22,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(104,9,16,'4 Hortensias y 4 Tulipanes con Follaje','Elegante combinación de hortensias y tulipanes.',1185.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/4Hort4TulFllje.jpeg',1,0,0,23,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(105,9,16,'Hortensia, ½ Paq Lisianthus, Rosas y Claveles','Mix de hortensia, lisianthus, rosas y claveles.',681.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/Hort12PLisiaRCL.jpeg',1,0,0,24,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(106,9,16,'5 Hortensias, 2 Paq Rosa Inglesa y Follaje','Ramo premium con hortensias y rosa inglesa.',2886.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/5Hort2PRINGLFllje.jpeg',1,0,0,25,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(107,9,16,'2 Hortensias, 1 Paq Rosa Inglesa y Follaje - Mitad','Versión compacta del ramo de hortensias.',1454.25,'assets/img/productos/OREZ/ROSAS/RamosPremium/5Hort2PRINGLFllje.jpeg',1,0,0,26,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(108,9,16,'100 Rosas, Astromelia, Mathiola, 6 Tulipanes y Lilis','Ramo de lujo con 100 rosas y flores premium.',5931.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/100R Astr Mth 6Tul Lils.jpeg',1,0,0,27,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(109,9,16,'100 Rosas, Astromelia, Mathiola - Mitad','Versión media del ramo de 100 rosas.',3148.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/100R Astr Mth 6Tul Lils.jpeg',1,0,0,28,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(110,9,16,'7 Paq Mini Rosa, Follaje y 75 Rosas','Impresionante ramo con mini rosas y 75 rosas.',4618.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/7paqMR Fllje 75Rs.jpeg',1,0,0,29,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(111,9,16,'7 Paq Mini Rosa, 75 Rosas - Mitad','Versión media del ramo de mini rosas.',2518.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/7paqMR Fllje 75Rs.jpeg',1,0,0,30,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(112,9,16,'2 Paq Mini Rosa, 8 Girasoles, 6 Hortensias y Follaje','Combinación alegre con girasoles, hortensias y mini rosas.',2361.45,'assets/img/productos/OREZ/ROSAS/RamosPremium/2P MiRos 8girs 6Hort Fllje.jpeg',1,0,0,31,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(113,9,16,'2 Paq Mini Rosa, 8 Girasoles - Mitad','Versión media del ramo de girasoles.',1258.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/2P MiRos 8girs 6Hort Fllje.jpeg',1,0,0,32,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(114,9,16,'70 Rosas, 10 Gerberas, 8 Perritos, 2 Paq Mini Rosa','Ramo variado con rosas, gerberas, perritos y mini rosas.',3673.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/70 rs 10gerb 8Perr 2 PaqMR Fllje.jpeg',1,0,0,33,'2026-01-22 00:24:04','2026-01-25 03:20:09',NULL,0,0,1,0,50),(115,9,16,'70 Rosas, 10 Gerberas, Perritos - Mitad','Versión media del ramo variado.',1993.95,'assets/img/productos/OREZ/ROSAS/RamosPremium/70 rs 10gerb 8Perr 2 PaqMR Fllje.jpeg',1,0,0,34,'2026-01-22 00:24:04','2026-01-25 02:59:39',NULL,0,0,1,0,50),(116,9,17,'160 Rosas y 11 Orquídeas en Base de Madera','Espectacular arreglo con 160 rosas y 11 orquídeas en base de madera. Pieza de colección.',7873.95,'assets/img/productos/OREZ/ROSAS/ArreglosBase/160R 11Orq BasMad.jpeg',1,0,0,1,'2026-01-22 00:24:14','2026-01-25 03:20:09',NULL,0,0,1,0,50),(117,9,17,'80 Rosas y 6 Orquídeas en Base - Mitad','Versión media del arreglo premium en base de madera.',4093.95,'assets/img/productos/OREZ/ROSAS/ArreglosBase/160R 11Orq BasMad.jpeg',1,0,0,2,'2026-01-22 00:24:14','2026-01-25 02:59:39',NULL,0,0,1,0,50),(118,9,17,'2 Paq Lisianthus y 9 Rosas en Base de Cerámica','Elegante arreglo de lisianthus y rosas en base de cerámica.',2042.25,'assets/img/productos/OREZ/ROSAS/ArreglosBase/2PLisia 9Rs bseCera.jpeg',1,0,0,3,'2026-01-22 00:24:14','2026-01-25 03:20:09',NULL,0,0,1,0,50),(119,9,17,'1 Paq Lisianthus y 5 Rosas en Base - Mitad','Versión compacta del arreglo de cerámica.',1102.50,'assets/img/productos/OREZ/ROSAS/ArreglosBase/2PLisia 9Rs bseCera.jpeg',1,0,0,4,'2026-01-22 00:24:14','2026-01-25 02:59:39',NULL,0,0,1,0,50),(120,9,18,'1 Paq Lisianthus, 2 Paq Astromelia, 16 Rosas, 6 Claveles y Follaje','Mix especial con lisianthus, astromelia, rosas y claveles.',1731.45,'assets/img/productos/OREZ/ROSAS/OtrosRamos/1PLisa 2PAstro 16Rs 6Cla Fllje.jpeg',1,0,0,1,'2026-01-22 00:25:00','2026-01-25 03:20:09',NULL,0,0,1,0,50),(121,9,18,'Lisianthus, Astromelia, 8 Rosas y 3 Claveles - Mitad','Versión compacta del mix especial.',1048.95,'assets/img/productos/OREZ/ROSAS/OtrosRamos/1PLisa 2PAstro 16Rs 6Cla Fllje.jpeg',1,0,0,2,'2026-01-22 00:25:00','2026-01-25 02:59:39',NULL,0,0,1,0,50),(122,9,18,'50 Claveles con Follaje','Hermoso ramo con 50 claveles y follaje decorativo.',996.45,'assets/img/productos/OREZ/ROSAS/OtrosRamos/50Cla Fllje.jpeg',1,0,0,3,'2026-01-22 00:25:00','2026-01-25 02:59:39',NULL,0,0,1,0,50),(123,9,18,'25 Claveles con Follaje - Mitad','Versión compacta con 25 claveles.',523.95,'assets/img/productos/OREZ/ROSAS/OtrosRamos/50Cla Fllje.jpeg',1,0,0,4,'2026-01-22 00:25:00','2026-01-25 02:59:39',NULL,0,0,1,0,50),(124,9,18,'17 Claveles con Follaje','Ramo de 17 claveles con follaje decorativo.',393.75,'assets/img/productos/OREZ/ROSAS/OtrosRamos/17CLa Fllje.jpeg',1,0,0,5,'2026-01-22 00:25:00','2026-01-25 02:59:39',NULL,0,0,1,0,50),(125,9,18,'34 Claveles con Follaje - Doble','Versión doble con 34 claveles.',719.25,'assets/img/productos/OREZ/ROSAS/OtrosRamos/17CLa Fllje.jpeg',1,0,0,6,'2026-01-22 00:25:00','2026-01-25 02:59:39',NULL,0,0,1,0,50),(126,9,18,'10 Mini Girasoles y 1 Hortensia Blanca','Alegre combinación de mini girasoles con hortensia blanca.',1102.50,'assets/img/productos/OREZ/ROSAS/OtrosRamos/10MiniGira 1 Horten.jpeg',1,0,0,7,'2026-01-22 00:25:00','2026-01-25 03:20:09',NULL,0,0,1,0,50);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos_populares`
--

DROP TABLE IF EXISTS `productos_populares`;
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

--
-- Dumping data for table `productos_populares`
--

LOCK TABLES `productos_populares` WRITE;
/*!40000 ALTER TABLE `productos_populares` DISABLE KEYS */;
/*!40000 ALTER TABLE `productos_populares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos_populares_api`
--

DROP TABLE IF EXISTS `productos_populares_api`;
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

--
-- Dumping data for table `productos_populares_api`
--

LOCK TABLES `productos_populares_api` WRITE;
/*!40000 ALTER TABLE `productos_populares_api` DISABLE KEYS */;
/*!40000 ALTER TABLE `productos_populares_api` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promociones`
--

DROP TABLE IF EXISTS `promociones`;
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

--
-- Dumping data for table `promociones`
--

LOCK TABLES `promociones` WRITE;
/*!40000 ALTER TABLE `promociones` DISABLE KEYS */;
/*!40000 ALTER TABLE `promociones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotional_banners`
--

DROP TABLE IF EXISTS `promotional_banners`;
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

--
-- Dumping data for table `promotional_banners`
--

LOCK TABLES `promotional_banners` WRITE;
/*!40000 ALTER TABLE `promotional_banners` DISABLE KEYS */;
INSERT INTO `promotional_banners` VALUES (1,'6 meses de ChatGPT Plus con QuickBite Pro Black','Disfruta de envíos ilimitados GRATIS y un mundo de beneficios exclusivos','assets/img/banners/banner_68ca149163be3.png','https://quickbite.com.mx/membership_subscribe','nuevo_negocio',15,'2025-09-16 19:53:00','2025-09-17 23:59:00',1,0,7,'2025-09-17 01:39:58','2025-09-17 01:53:21');
/*!40000 ALTER TABLE `promotional_banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `puntos_redenciones`
--

DROP TABLE IF EXISTS `puntos_redenciones`;
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

--
-- Dumping data for table `puntos_redenciones`
--

LOCK TABLES `puntos_redenciones` WRITE;
/*!40000 ALTER TABLE `puntos_redenciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `puntos_redenciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reasignaciones_pedido`
--

DROP TABLE IF EXISTS `reasignaciones_pedido`;
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

--
-- Dumping data for table `reasignaciones_pedido`
--

LOCK TABLES `reasignaciones_pedido` WRITE;
/*!40000 ALTER TABLE `reasignaciones_pedido` DISABLE KEYS */;
/*!40000 ALTER TABLE `reasignaciones_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recompensas_repartidor`
--

DROP TABLE IF EXISTS `recompensas_repartidor`;
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

--
-- Dumping data for table `recompensas_repartidor`
--

LOCK TABLES `recompensas_repartidor` WRITE;
/*!40000 ALTER TABLE `recompensas_repartidor` DISABLE KEYS */;
/*!40000 ALTER TABLE `recompensas_repartidor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referidos`
--

DROP TABLE IF EXISTS `referidos`;
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

--
-- Dumping data for table `referidos`
--

LOCK TABLES `referidos` WRITE;
/*!40000 ALTER TABLE `referidos` DISABLE KEYS */;
INSERT INTO `referidos` VALUES (1,4,48,'2025-08-21 21:37:19',0);
/*!40000 ALTER TABLE `referidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referidos_repartidores`
--

DROP TABLE IF EXISTS `referidos_repartidores`;
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

--
-- Dumping data for table `referidos_repartidores`
--

LOCK TABLES `referidos_repartidores` WRITE;
/*!40000 ALTER TABLE `referidos_repartidores` DISABLE KEYS */;
/*!40000 ALTER TABLE `referidos_repartidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `relacion_negocio_categoria`
--

DROP TABLE IF EXISTS `relacion_negocio_categoria`;
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

--
-- Dumping data for table `relacion_negocio_categoria`
--

LOCK TABLES `relacion_negocio_categoria` WRITE;
/*!40000 ALTER TABLE `relacion_negocio_categoria` DISABLE KEYS */;
INSERT INTO `relacion_negocio_categoria` VALUES (8,4),(8,5),(7,6),(8,8),(7,9),(8,10),(8,12),(8,15),(9,20);
/*!40000 ALTER TABLE `relacion_negocio_categoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repartidor_capacidad`
--

DROP TABLE IF EXISTS `repartidor_capacidad`;
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

--
-- Dumping data for table `repartidor_capacidad`
--

LOCK TABLES `repartidor_capacidad` WRITE;
/*!40000 ALTER TABLE `repartidor_capacidad` DISABLE KEYS */;
INSERT INTO `repartidor_capacidad` VALUES (1,10,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(2,11,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(3,12,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(4,13,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(5,1,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(6,2,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(7,3,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(8,4,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(9,5,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(10,6,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(11,7,5,10.00,90,5.00,1,'2025-11-21 00:37:30'),(12,8,5,10.00,90,5.00,1,'2025-11-21 00:37:30');
/*!40000 ALTER TABLE `repartidor_capacidad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repartidor_logros`
--

DROP TABLE IF EXISTS `repartidor_logros`;
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

--
-- Dumping data for table `repartidor_logros`
--

LOCK TABLES `repartidor_logros` WRITE;
/*!40000 ALTER TABLE `repartidor_logros` DISABLE KEYS */;
/*!40000 ALTER TABLE `repartidor_logros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repartidores`
--

DROP TABLE IF EXISTS `repartidores`;
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

--
-- Dumping data for table `repartidores`
--

LOCK TABLES `repartidores` WRITE;
/*!40000 ALTER TABLE `repartidores` DISABLE KEYS */;
INSERT INTO `repartidores` VALUES (1,2,'bicicleta',NULL,NULL,NULL,1,1,21.42480874,-102.56914793,'2026-02-01 17:21:04',4,323.00,0.00,NULL,0,'2025-08-06 02:35:35','2026-02-01 17:21:04',1,5.00,NULL,0,0.00,0,'058597000034495984','Otro','justin martinez',0,5.0,'REP5F21E1EC',0,0.00),(2,3,'motocicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2026-02-04 22:34:22',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2026-02-04 22:34:22',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(3,10,'motocicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2025-08-12 04:54:36',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2025-08-12 04:54:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(4,11,'motocicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2025-08-12 04:54:36',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2025-08-12 04:54:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(5,15,'bicicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2025-08-12 04:54:36',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2025-08-12 04:54:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(6,16,'bicicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2025-08-12 04:54:36',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2025-08-12 04:54:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(7,17,'bicicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2025-08-12 04:54:36',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2025-08-12 04:54:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(8,18,'bicicleta',NULL,NULL,NULL,1,1,NULL,NULL,'2025-08-12 04:54:36',0,0.00,0.00,NULL,0,'2025-08-06 02:35:35','2025-08-12 04:54:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(10,53,'motocicleta',NULL,'',NULL,1,0,NULL,NULL,'2025-09-09 01:26:43',0,0.00,0.00,NULL,1,'2025-09-09 01:26:09','2025-09-09 01:26:43',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(11,54,'motocicleta',NULL,'',NULL,1,0,NULL,NULL,'2025-09-09 02:06:04',0,0.00,0.00,NULL,1,'2025-09-09 02:04:53','2025-09-09 02:06:04',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(12,55,'motocicleta',NULL,'',NULL,1,0,NULL,NULL,'2025-09-09 02:34:36',0,0.00,0.00,'267089',0,'2025-09-09 02:34:36','2025-09-09 02:34:36',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00),(13,56,'coche',NULL,'09N5191845',NULL,1,0,NULL,NULL,'2025-09-09 02:46:54',0,0.00,0.00,'419891',0,'2025-09-09 02:46:54','2025-09-09 02:46:54',1,5.00,NULL,0,0.00,0,NULL,NULL,NULL,0,5.0,NULL,0,0.00);
/*!40000 ALTER TABLE `repartidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resenas_pendientes`
--

DROP TABLE IF EXISTS `resenas_pendientes`;
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

--
-- Dumping data for table `resenas_pendientes`
--

LOCK TABLES `resenas_pendientes` WRITE;
/*!40000 ALTER TABLE `resenas_pendientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `resenas_pendientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rutas_entrega`
--

DROP TABLE IF EXISTS `rutas_entrega`;
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

--
-- Dumping data for table `rutas_entrega`
--

LOCK TABLES `rutas_entrega` WRITE;
/*!40000 ALTER TABLE `rutas_entrega` DISABLE KEYS */;
/*!40000 ALTER TABLE `rutas_entrega` ENABLE KEYS */;
UNLOCK TABLES;
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

--
-- Table structure for table `spei_payments`
--

DROP TABLE IF EXISTS `spei_payments`;
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

--
-- Dumping data for table `spei_payments`
--

LOCK TABLES `spei_payments` WRITE;
/*!40000 ALTER TABLE `spei_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `spei_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stripe_webhooks`
--

DROP TABLE IF EXISTS `stripe_webhooks`;
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

--
-- Dumping data for table `stripe_webhooks`
--

LOCK TABLES `stripe_webhooks` WRITE;
/*!40000 ALTER TABLE `stripe_webhooks` DISABLE KEYS */;
/*!40000 ALTER TABLE `stripe_webhooks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sugerencias_batch`
--

DROP TABLE IF EXISTS `sugerencias_batch`;
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

--
-- Dumping data for table `sugerencias_batch`
--

LOCK TABLES `sugerencias_batch` WRITE;
/*!40000 ALTER TABLE `sugerencias_batch` DISABLE KEYS */;
/*!40000 ALTER TABLE `sugerencias_batch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
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

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ubicaciones_usuarios`
--

DROP TABLE IF EXISTS `ubicaciones_usuarios`;
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

--
-- Dumping data for table `ubicaciones_usuarios`
--

LOCK TABLES `ubicaciones_usuarios` WRITE;
/*!40000 ALTER TABLE `ubicaciones_usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `ubicaciones_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `uso_beneficios_aliados`
--

DROP TABLE IF EXISTS `uso_beneficios_aliados`;
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

--
-- Dumping data for table `uso_beneficios_aliados`
--

LOCK TABLES `uso_beneficios_aliados` WRITE;
/*!40000 ALTER TABLE `uso_beneficios_aliados` DISABLE KEYS */;
/*!40000 ALTER TABLE `uso_beneficios_aliados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
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

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (2,'prueba@gmail.com','$2y$10$HOgu8cndnwZsECJLtzVRZ.dVMVgSra7foeljklBHPUxdoH0NxOOSy','Repartidor','prueba','6361286343',NULL,'2025-04-09 14:54:37','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(3,'admin@gmail.com','$2y$10$bEj0b4mCvmmOg0qXYgUBa.yMjc2HbXpxSsVa6WijFBzfmFq8BvN5m','Repartidor','admin','2408109238',NULL,'2025-04-10 14:23:17','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(4,'xdyce@gmail.com','$2y$10$sHTcSY6QH5C/1SrMG27Gb.9bEqf1xX.MQvZe6gWhZECoH5CzTCXb2','Agustín','González Gutiérrez','4492873740',NULL,'2025-04-12 22:53:56','2025-08-06 03:43:19','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(6,'betitocer10@gmail.com','$2y$10$CJBFqWqF8vsXP8RCJk2E0eXWZcnlK.Nfnt9EaSz2vWdqXh3hMW/1.','Alberto','Cervantes Aguilera','3461016506',NULL,'2025-06-25 20:01:11','2025-08-06 03:43:19','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(9,'juan@gmail.com','$2y$10$u8KS9Y7bPMWwmg1EJqcK.uSoQhA9xSIjezresr.fpaNo8y1ZtLk8K','juan','','2344234234',NULL,'2025-06-30 05:02:13','2025-08-06 03:43:19','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(10,'pablo@gmail.com','$2y$10$MwmQGSBaLciofJujm/W1O.LaPMNzHCrx3fKrPDboC4TieZ1qB6uiy','Repartidor','pablo','3192504052',NULL,'2025-07-02 21:22:45','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(11,'hinojosa@gmail.com','$2y$10$1v7DigBgdwEp.fmJxmALmOXkyQtOG5HTS94caI5m58NK6BKU4hOuu','Repartidor','hinojosa','6361286343',NULL,'2025-07-03 03:37:38','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(13,'luan@gmail.com','$2y$10$cCWLl/XD07roId5a8xsqh.ndunb7b6joqRCIiP1hQu5fZyNxPptxC','Juan Perez','','3330488443',NULL,'2025-07-03 04:20:12','2025-08-06 03:43:19','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(14,'gaudi@gmail.com','$2y$10$aE0JWCNJBPPHRsJjN1vGJ.dFIlDUmvhmn7EOd5r6ub0Uzwvgx1oxW','Alfredo Vidaurri','','2408109238',NULL,'2025-07-03 05:28:55','2025-08-06 03:43:19','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(15,'alan@gmail.com','$2y$10$Y2yjYXglAQitUYgtF2yyX.8dMuaQByfwDD6mGWfiR1StRs5T3XBKi','Repartidor','alan','4492839523',NULL,'2025-07-03 06:01:43','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(16,'justin@gmail.com','$2y$10$sV.SbPQJ/Tiht4Ckbhemy.HNTS1OqcAVgxncH5/tdHpCPlWmCqCbO','Repartidor','justin','4492873740',NULL,'2025-07-03 06:05:24','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(17,'agustin@gmail.com','$2y$10$2fM.gTZR2rgmO6qUSOLs.eOxRjqMPq3nj3omTXyMebXP9UR3d4tui','Repartidor','agustin','4492873405',NULL,'2025-07-03 06:18:48','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(18,'nansi@gmail.com','$2y$10$Un1qOM3GHvL3fx2VsCKfaez6xQaEVGjwXOj/npNBFrgrsshg3JHoK','Repartidor','nansi','3467874480',NULL,'2025-07-07 05:27:24','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(19,'ITA@gmail.com','$2y$10$cyA51H0DIYHQrJvP04IR7Ok/9ndBAM.KMIoi7Bb2V1ec8iji68z8a','ITA','','1234567890',NULL,'2025-07-07 17:28:23','2025-08-06 03:43:19','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(20,'villasenordayana27@gmail.com','$2y$10$vT2aLCEyvkVwwJtx6vPUxenSArhOrKA7nyJSLs5Xpi4Ku0nq/keN.','Dayana Villaseñor','','4961498252',NULL,'2025-07-10 18:46:39','2025-08-06 03:43:19','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(21,'Angelesco2207@gmail.com','$2y$10$4xssrsXs5k0teK9EBuJZm.KiBu4R4lIgLv5GNl79G1bt2/J6d0eb6','Repartidor','Angelesco2207','3467009105',NULL,'2025-07-15 03:42:47','2025-08-06 03:43:19','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(22,'ceo@empresa.com','$2y$10$E4UJgxgVKvL4PuE.lxW8Ceo/YJGxlCJ5s8b3vN4QlG.sFtPcP6y4W','Justin','Martinez','+524492873740','','2025-07-19 01:28:41','2025-08-05 20:20:41','ceo',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(23,'orez@gmail.com','$2y$10$..GP8f5B0PK4pE9GJGkWXuJ5DIgDqaHudnBvMQJeOokMcjhZBpnRW','Juls Orez','','6361286343',NULL,'2025-07-22 18:30:40','2026-02-01 14:28:18','negocio','658439',1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(24,'admin@quickbite.com.mx','$2y$12$WSdpeMjf6AwhTOyRGd.XIupDgUqzbi1oiwMS.CTgbWMLaF.5T9wBC','Admin','QuickBite','1234567890',NULL,'2025-08-04 19:01:45','2025-08-06 03:43:19','admin',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(25,'gaelgagsgags@gmail.com','$2y$12$mWSrz/bF1ZcXMlgjr9ZRGOrw0HRWpqX0mfNJxqG6W3g2zve3SJXpG','Justin Gael','Díaz Jimenez','3461134170','https://lh3.googleusercontent.com/a/ACg8ocJYzl79fJiJBF6J_xaD2Qlszdnn5iNzNfNq5U7JVNp80ZPRXBOSbw=s96-c','2025-08-04 22:26:13','2026-01-23 03:56:57','cliente','250914',1,1,NULL,NULL,NULL,0.00,0,0,NULL,'100234069583371489957'),(26,'admin@quickbite.com','$2y$12$ZxOmcBIodQQ4L5gkB0HWBuyIxNJnH3/78GSkxGhSFJ6JsgwRo3wia','Administrador','Sistema','+524492873740',NULL,'2025-08-05 20:25:20','2025-08-05 20:28:30','ceo',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(37,'xdycechola@gmail.com','$2y$12$LgJgQTyhRCngdWihuMVWee6HXVKJw4jrraXK05mtPGEgOC.rmJJHO','justin','martinez','4492873740',NULL,'2025-08-06 22:03:27','2025-08-06 22:03:55','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(38,'james.lafleur@chipimail.com','$2y$12$BsMtUfoUpexiG1GTAeGEpO9E8NF9Z1pLb5dXOxgb/v94hnuehh0LC','James','LaFleur','5512382881',NULL,'2025-08-14 02:37:58','2025-08-14 02:38:41','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(39,'rcedillo390171@gmail.com','$2y$12$FdBJ.30EdbE1VI1dC8/N8eLfdQItulLt427sFd6GzSY.fmRt/ouwG','Roberto','Cedillo','3461080268',NULL,'2025-08-15 21:44:53','2025-08-15 21:45:14','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(40,'lapiazza@gmail.com','$2y$12$mFRMO7KFdNMjx.6.N7gote4ocK2acdz.Qr7Omwb9jPVPTti3iheii','Edgar Carrillo','','3461012268',NULL,'2025-08-19 00:05:23','2025-08-19 00:08:45','negocio','104851',1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(41,'ivanulises.1029@gmail.com','$2y$12$PiCkyUwVowkCFiZIoGYNGuHeagMDfdok3gSynQUDOGPbJ3bjrbw0W','Ivan Ulises Rodriguez Lara','','3346150551',NULL,'2025-08-19 18:44:45','2025-08-19 18:46:13','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(42,'adelaidamarquez@outlook.es','$2y$12$Adbp2CdgrXyVBGV81eBmduaUggK3/CbwetBCQtSTBrv/IBEFkfCb.','Nora Lizeth Márquez Gil','','4495574293',NULL,'2025-08-20 03:37:45','2025-08-20 03:37:45','negocio','035037',0,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(43,'marquezadelaida9@gmail.com','$2y$12$atFhclonRYAZFbMCURObTOLoWr0WvUxU7qr5SSzonMZRbT.2IjCye','Nora Lizeth Márquez Gil','','4495574293',NULL,'2025-08-20 03:42:45','2025-08-20 03:43:30','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(44,'pablomdelc209@gmail.com','$2y$12$HHwlKbA/rM4G6jbnBnbxTu2RL2IWMrTMb4RKdhewy.Y4F0lKxTQPy','Pablo','','4961017130',NULL,'2025-08-20 04:08:54','2025-08-20 04:09:50','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(46,'calibre50@gmail.com','$2y$12$bM5PajuT2aWmf0oKVVxlA.SCE7bwWEl0y9BCDUaKbVMKfg/c89ZOS','Luan Cafeteria','','4492873740',NULL,'2025-08-20 21:10:16','2025-08-20 21:13:45','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(48,'prueba123@gmail.com','$2y$12$l9R4FX7E9JswwYSaHBnmSurHb1WhDqGMkZ8fokWp5MgfcsQb0YX1m','Justin','Martinez','3535466723',NULL,'2025-08-21 21:37:19','2025-08-21 21:37:35','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(50,'xdyceszn@gmail.com','$2y$12$OyLi4ycGvEzDW261ge3mBeuIQ.527h7ZE0ulUPpzI7CDbYo6XSD0u','Justin','Martinez Gonzalez','4492873740',NULL,'2025-08-21 22:50:17','2025-08-21 22:50:32','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(51,'xdycestub@gmail.com','$2y$12$W8cz26I2rcsPlL7gWz7OtOA1nw0RyqFD8R9WSLoA8Z9vxQLdMjKdC','Justin','Martinez','3330488443',NULL,'2025-08-21 22:54:46','2025-08-21 22:55:10','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(52,'jlf@chipimail.com','$2y$12$OvcPil3ovAoUizvJVHH/teGUwzBpQaIJrQvHz5wyk74iGK59/mdvq','J','LF','5512382881',NULL,'2025-09-06 01:27:06','2025-09-06 01:27:27','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(53,'xdyceee@outlook.com','$2y$12$fiXl/h3MkXeJEDqYCtooOe51ht6wMFD3aOc9svabuOIdrj.076gx.','Justin','Martinez','4492873740',NULL,'2025-09-09 01:26:09','2025-09-09 01:26:43','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(54,'chuyitacolmen@gmail.com','$2y$12$dZK/aNe/cLnDOIUun5/HYeXOzqVDkfj3RmNLC3RCNntG/zkenU.PK','Mary','Amador','3461089652',NULL,'2025-09-09 02:04:53','2025-09-09 02:06:04','repartidor',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(55,'gladysedy@gmail.com','$2y$12$CuFt5MKjtVZEdgHMK8ZRau3guF3eTV5aW/71YmOEF6U6wVMKVA26i','Gladys Aide','Aguilera Aguilera','3461118790',NULL,'2025-09-09 02:34:36','2025-09-09 03:02:06','repartidor','267089',1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(56,'eli_avila9@hotmail.com','$2y$12$nvqxa4k2G8fXYMNmSZONneft8u4O4kgz3x/PQznLTrjCp5oMO5TJK','Elizabeth','Avila','3461134867',NULL,'2025-09-09 02:46:54','2025-09-09 03:13:08','repartidor','419891',1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(57,'diaz.ibarra.tonanzinmonserrat@cbtis247.edu.mx','$2y$12$u.98ZqIZrgCfQ4hqNbhuou8WUvVYvLqfUQqMSW3WcUYlCZIaC5Sf2','Monserrat','Diaz','3461030947',NULL,'2025-09-29 23:40:14','2025-09-29 23:40:50','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(58,'pepin@gmail.com','$2y$12$dD9AxYlwtY4irLKwdURr1OS032Se85BbH.szCEIb4PoH.48CZiaCu','Pepein','Popop','4449512232',NULL,'2025-09-30 06:05:59','2025-09-30 06:05:59','cliente','610582',0,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(59,'23150364@aguascalientes.tecnm.mx','$2y$12$dyS/sSdN7KljI57HPMhFoec4PkEYgUOKyFCqL7ZFwi2g0o4bwQgn.','QuickBite','','4492873748',NULL,'2025-10-26 20:44:23','2025-10-26 20:44:23','negocio','789912',0,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(61,'jm7701@icloud.com','$2y$10$jqH.f1ek3/q2tkCWnoN.uOIAmS5BMyZq5DMeMuyrRi1AE926qVMz2','Gaudi Café','','4492873740',NULL,'2025-11-20 02:49:26','2025-11-20 02:49:52','negocio',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(62,'test_1769074576@test.com','$2y$10$2xgzH87BgC3A.S5sIXeIPudlqr9nn2kIYLoJOIWsejIEaqTwAveoe','Test','Usuario','5551234567',NULL,'2026-01-22 09:36:16','2026-01-22 09:36:16','cliente',NULL,NULL,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(66,'julirodriguez944@gmail.com','$2y$10$GI2PdkzZqK.tIhvmoOypzOQ9xi8JI0.VUboA8opeFBGxC02rmR7um','Juli','Rodriguez','3461035947',NULL,'2026-01-31 15:01:31','2026-01-31 15:01:31','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(67,'orezfloral@gmail.com','$2y$10$f5U6RewfKLtUd7lwsqmEMOGMB.qK54pteeHFlf4TzQfFeJXk/d676','Orez','Floral','3461130233','https://lh3.googleusercontent.com/a/ACg8ocIBY9-D0z19Adjx9FA1ABRef3ZVn_D_FcLDBLfIhy1e0aAy5Q=s96-c','2026-01-31 15:08:04','2026-02-01 14:43:28','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,'103177267938022129611'),(68,'nenadeelias944@gmail.com','$2y$10$kiiAnMDuQ5PARxl9WyaPy.Ri/1a.cZm5eRK7dsuHayty1MGLcgjbG','Cesar','Rodriguez','3461130233',NULL,'2026-02-02 15:54:52','2026-02-02 15:55:14','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(69,'villalobosjose17@icloud.com','$2y$10$u4ODDq8bJYfc1ck0BFBtVOVDZqot1wuR/CGZLjbtfikAVBqr/pNgO','José Guadalupe','Villalobos','3461055688',NULL,'2026-02-03 16:06:04','2026-02-03 16:06:04','cliente','167100',0,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL),(70,'ashleyduran0624@gmail.com','$2y$10$EfTqO.z2SRV1st.5cp23KeQ7cFdTg9XuxX52aEvuMXuBWYNCfvTvO','Pao','Duran','3461137381',NULL,'2026-02-04 04:10:29','2026-02-04 04:11:35','cliente',NULL,1,1,NULL,NULL,NULL,0.00,0,0,NULL,NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_negocios_recomendados`
--

DROP TABLE IF EXISTS `v_negocios_recomendados`;
/*!50001 DROP VIEW IF EXISTS `v_negocios_recomendados`*/;
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

--
-- Temporary view structure for view `v_pedidos_timeout`
--

DROP TABLE IF EXISTS `v_pedidos_timeout`;
/*!50001 DROP VIEW IF EXISTS `v_pedidos_timeout`*/;
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

--
-- Temporary view structure for view `v_repartidores_disponibles`
--

DROP TABLE IF EXISTS `v_repartidores_disponibles`;
/*!50001 DROP VIEW IF EXISTS `v_repartidores_disponibles`*/;
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

--
-- Temporary view structure for view `v_sugerencias_batch_activas`
--

DROP TABLE IF EXISTS `v_sugerencias_batch_activas`;
/*!50001 DROP VIEW IF EXISTS `v_sugerencias_batch_activas`*/;
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

--
-- Table structure for table `valoraciones`
--

DROP TABLE IF EXISTS `valoraciones`;
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

--
-- Dumping data for table `valoraciones`
--

LOCK TABLES `valoraciones` WRITE;
/*!40000 ALTER TABLE `valoraciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `valoraciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `vista_comisiones_pedidos`
--

DROP TABLE IF EXISTS `vista_comisiones_pedidos`;
/*!50001 DROP VIEW IF EXISTS `vista_comisiones_pedidos`*/;
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

--
-- Temporary view structure for view `vista_pedidos_disponibles_batch`
--

DROP TABLE IF EXISTS `vista_pedidos_disponibles_batch`;
/*!50001 DROP VIEW IF EXISTS `vista_pedidos_disponibles_batch`*/;
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

--
-- Temporary view structure for view `vista_productos_opciones`
--

DROP TABLE IF EXISTS `vista_productos_opciones`;
/*!50001 DROP VIEW IF EXISTS `vista_productos_opciones`*/;
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

--
-- Temporary view structure for view `vista_resumen_comisiones_negocio`
--

DROP TABLE IF EXISTS `vista_resumen_comisiones_negocio`;
/*!50001 DROP VIEW IF EXISTS `vista_resumen_comisiones_negocio`*/;
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

--
-- Temporary view structure for view `vista_rutas_activas`
--

DROP TABLE IF EXISTS `vista_rutas_activas`;
/*!50001 DROP VIEW IF EXISTS `vista_rutas_activas`*/;
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

--
-- Table structure for table `wallet`
--

DROP TABLE IF EXISTS `wallet`;
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

--
-- Dumping data for table `wallet`
--

LOCK TABLES `wallet` WRITE;
/*!40000 ALTER TABLE `wallet` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_auditoria`
--

DROP TABLE IF EXISTS `wallet_auditoria`;
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

--
-- Dumping data for table `wallet_auditoria`
--

LOCK TABLES `wallet_auditoria` WRITE;
/*!40000 ALTER TABLE `wallet_auditoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_retiros`
--

DROP TABLE IF EXISTS `wallet_retiros`;
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

--
-- Dumping data for table `wallet_retiros`
--

LOCK TABLES `wallet_retiros` WRITE;
/*!40000 ALTER TABLE `wallet_retiros` DISABLE KEYS */;
INSERT INTO `wallet_retiros` VALUES (1,2,500.00,'058597000034495984','','procesando',NULL,NULL,NULL,'pending',NULL,'2025-11-20 00:42:53',NULL),(2,2,500.00,'058597000034495984','','procesando',NULL,NULL,NULL,'pending',NULL,'2025-11-20 00:46:41',NULL),(3,2,1000.00,'012180001234567899','','procesando',NULL,NULL,NULL,'pending',NULL,'2025-11-20 01:02:01',NULL),(4,2,1000.00,'012180001234567899','','procesando',NULL,NULL,NULL,'pending',NULL,'2025-11-20 01:02:05',NULL),(5,5,100.00,NULL,NULL,'procesando',NULL,NULL,NULL,NULL,NULL,'2026-01-07 19:46:23',NULL),(6,4,100.00,NULL,NULL,'procesando',NULL,NULL,NULL,NULL,NULL,'2026-01-07 19:46:23',NULL),(7,5,100.00,NULL,NULL,'procesando',NULL,NULL,NULL,NULL,NULL,'2026-01-22 09:37:57',NULL),(8,4,100.00,NULL,NULL,'procesando',NULL,NULL,NULL,NULL,NULL,'2026-01-22 09:37:57',NULL);
/*!40000 ALTER TABLE `wallet_retiros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_transacciones`
--

DROP TABLE IF EXISTS `wallet_transacciones`;
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

--
-- Dumping data for table `wallet_transacciones`
--

LOCK TABLES `wallet_transacciones` WRITE;
/*!40000 ALTER TABLE `wallet_transacciones` DISABLE KEYS */;
INSERT INTO `wallet_transacciones` VALUES (3,5,'ingreso',342.00,'Venta pedido #50','completado',50,0.00,NULL,'2026-01-07 19:46:12',0),(4,4,'ingreso',82.00,'Entrega pedido #50 (envio + propina)','completado',50,0.00,NULL,'2026-01-07 19:46:12',0),(5,5,'ingreso',351.00,'Venta pedido #51','completado',51,0.00,NULL,'2026-01-07 19:46:23',0),(6,4,'ingreso',83.50,'Entrega pedido #51 (envio + propina)','completado',51,0.00,NULL,'2026-01-07 19:46:23',0),(7,5,'retiro',-100.00,'Solicitud de retiro','pendiente',NULL,0.00,NULL,'2026-01-07 19:46:23',0),(8,4,'retiro',-100.00,'Solicitud de retiro','pendiente',NULL,0.00,NULL,'2026-01-07 19:46:23',0),(9,5,'ingreso',252.00,'Venta pedido #52 (comisión 28)','completado',52,28.00,NULL,'2026-01-07 19:57:29',0),(10,4,'ingreso',53.00,'Entrega pedido #52 + propina $28','completado',52,0.00,NULL,'2026-01-07 19:57:29',0),(11,2,'ganancia_efectivo',315.00,'Venta efectivo pedido #54 (comisión $35 adeudada)','completado',54,35.00,NULL,'2026-01-07 20:11:26',1),(12,2,'ganancia_efectivo',315.00,'Venta efectivo pedido #55 (comisión $35 adeudada)','completado',55,35.00,NULL,'2026-01-07 20:13:08',1),(13,6,'ganancia_efectivo',55.00,'Entrega efectivo pedido #55','completado',55,0.00,NULL,'2026-01-07 20:13:08',1),(14,2,'ganancia_efectivo',315.00,'Venta efectivo pedido #56 (comisión $35 adeudada)','completado',56,35.00,NULL,'2026-01-07 20:13:45',1),(15,6,'ganancia_efectivo',55.00,'Entrega efectivo pedido #56','completado',56,0.00,NULL,'2026-01-07 20:13:45',1),(16,2,'ganancia_efectivo',405.00,'Venta efectivo pedido #57 (comisión $45 adeudada)','completado',57,45.00,NULL,'2026-01-07 20:16:19',1),(17,6,'ganancia_efectivo',65.00,'Entrega efectivo pedido #57','completado',57,0.00,NULL,'2026-01-07 20:16:19',1),(18,2,'ganancia_efectivo',315.00,'Venta efectivo pedido #58 (comisión $35 adeudada)','completado',58,35.00,NULL,'2026-01-19 19:39:54',1),(19,6,'ganancia_efectivo',55.00,'Entrega efectivo pedido #58','completado',58,0.00,NULL,'2026-01-19 19:39:54',1),(20,5,'ingreso',441.60,'Venta pedido #59','completado',59,0.00,NULL,'2026-01-22 09:37:57',0),(21,4,'ingreso',104.50,'Entrega pedido #59 (envio + propina)','completado',59,0.00,NULL,'2026-01-22 09:37:57',0),(22,5,'retiro',-100.00,'Solicitud de retiro','pendiente',NULL,0.00,NULL,'2026-01-22 09:37:57',0),(23,4,'retiro',-100.00,'Solicitud de retiro','pendiente',NULL,0.00,NULL,'2026-01-22 09:37:57',0);
/*!40000 ALTER TABLE `wallet_transacciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
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

--
-- Dumping data for table `wallets`
--

LOCK TABLES `wallets` WRITE;
/*!40000 ALTER TABLE `wallets` DISABLE KEYS */;
INSERT INTO `wallets` VALUES (2,7,'business','acct_test_7',2000.00,0.00,2000.00,'activo',0,'2025-11-20 00:39:08','2025-11-20 01:02:05'),(4,2,'courier','MP_courier_2_1767320606',123.00,200.00,0.00,'activo',0,'2026-01-02 02:23:26','2026-01-22 09:37:57'),(5,8,'business','LOCAL_NEG_8_1767815136',1186.60,200.00,0.00,'activo',0,'2026-01-07 19:45:36','2026-01-22 09:37:57'),(6,1,'courier','LOCAL_COU_1_1767816788',0.00,0.00,0.00,'activo',0,'2026-01-07 20:13:08','2026-01-07 20:13:08'),(7,61,'business','MP_business_61_1767819144',0.00,0.00,0.00,'activo',0,'2026-01-07 20:52:24','2026-01-07 20:52:24'),(8,23,'business','MP_business_23_1769072359',0.00,0.00,0.00,'activo',0,'2026-01-22 08:59:19','2026-01-22 08:59:19');
/*!40000 ALTER TABLE `wallets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_messages`
--

DROP TABLE IF EXISTS `whatsapp_messages`;
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

--
-- Dumping data for table `whatsapp_messages`
--

LOCK TABLES `whatsapp_messages` WRITE;
/*!40000 ALTER TABLE `whatsapp_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zonas_envio`
--

DROP TABLE IF EXISTS `zonas_envio`;
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

--
-- Dumping data for table `zonas_envio`
--

LOCK TABLES `zonas_envio` WRITE;
/*!40000 ALTER TABLE `zonas_envio` DISABLE KEYS */;
INSERT INTO `zonas_envio` VALUES (1,9,'Villa Hidalgo Jalisco',350.00,1,1,'2026-01-25 05:26:33'),(2,9,'Mechoacanejo',180.00,1,2,'2026-01-25 05:26:33');
/*!40000 ALTER TABLE `zonas_envio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `v_negocios_recomendados`
--

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

--
-- Final view structure for view `v_pedidos_timeout`
--

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

--
-- Final view structure for view `v_repartidores_disponibles`
--

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

--
-- Final view structure for view `v_sugerencias_batch_activas`
--

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

--
-- Final view structure for view `vista_comisiones_pedidos`
--

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

--
-- Final view structure for view `vista_pedidos_disponibles_batch`
--

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

--
-- Final view structure for view `vista_productos_opciones`
--

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

--
-- Final view structure for view `vista_resumen_comisiones_negocio`
--

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

--
-- Final view structure for view `vista_rutas_activas`
--

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

-- Dump completed on 2026-02-05  8:16:30
