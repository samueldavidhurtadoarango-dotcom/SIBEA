-- MySQL dump 10.13  Distrib 9.3.0, for Win64 (x86_64)
--
-- Host: localhost    Database: benefitrack
-- ------------------------------------------------------
-- Server version	11.8.2-MariaDB

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
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id_auditoria` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `accion` varchar(100) NOT NULL,
  `entidad` varchar(100) NOT NULL,
  `id_entidad` int(11) DEFAULT NULL,
  `detalle` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle`)),
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_auditoria`),
  KEY `fk_auditoria_usuario` (`id_usuario`),
  KEY `idx_auditoria_fecha` (`fecha`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beneficios`
--

DROP TABLE IF EXISTS `beneficios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `beneficios` (
  `id_beneficio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_beneficio`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beneficios`
--

LOCK TABLES `beneficios` WRITE;
/*!40000 ALTER TABLE `beneficios` DISABLE KEYS */;
/*!40000 ALTER TABLE `beneficios` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_beneficio_actualizado
BEFORE UPDATE ON beneficios
FOR EACH ROW
BEGIN

    SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `convocatorias`
--

DROP TABLE IF EXISTS `convocatorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `convocatorias` (
  `id_convocatoria` int(11) NOT NULL AUTO_INCREMENT,
  `id_beneficio` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_cierre` date NOT NULL,
  `cupos` int(11) NOT NULL,
  `requisitos` text NOT NULL,
  `estado` enum('Activa','Inactiva','Cerrada','Cierre anticipado') NOT NULL DEFAULT 'Inactiva',
  `motivo_cierre` text DEFAULT NULL,
  `fecha_cierre_real` datetime DEFAULT NULL,
  `usuario_cierre` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_convocatoria`),
  KEY `fk_convocatoria_beneficio` (`id_beneficio`),
  KEY `fk_convocatoria_usuario` (`usuario_cierre`),
  KEY `idx_convocatoria_estado` (`estado`),
  CONSTRAINT `fk_convocatoria_beneficio` FOREIGN KEY (`id_beneficio`) REFERENCES `beneficios` (`id_beneficio`),
  CONSTRAINT `fk_convocatoria_usuario` FOREIGN KEY (`usuario_cierre`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `chk_fechas_convocatoria` CHECK (`fecha_cierre` > `fecha_inicio`),
  CONSTRAINT `chk_cupos_convocatoria` CHECK (`cupos` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `convocatorias`
--

LOCK TABLES `convocatorias` WRITE;
/*!40000 ALTER TABLE `convocatorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `convocatorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_postulacion`
--

DROP TABLE IF EXISTS `documentos_postulacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_postulacion` (
  `id_documento` int(11) NOT NULL AUTO_INCREMENT,
  `id_postulacion` int(11) NOT NULL,
  `id_tipo_documento` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `tamano_mb` decimal(6,2) NOT NULL,
  `estado_validacion` enum('Pendiente','Validado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  `resultado_ocr` text DEFAULT NULL,
  `datos_extraidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_extraidos`)),
  `observaciones` text DEFAULT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_validacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_documento`),
  KEY `fk_documento_postulacion` (`id_postulacion`),
  KEY `fk_documento_tipo` (`id_tipo_documento`),
  CONSTRAINT `fk_documento_postulacion` FOREIGN KEY (`id_postulacion`) REFERENCES `postulaciones` (`id_postulacion`),
  CONSTRAINT `fk_documento_tipo` FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipos_documento` (`id_tipo_documento`),
  CONSTRAINT `chk_tamano_documento` CHECK (`tamano_mb` <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_postulacion`
--

LOCK TABLES `documentos_postulacion` WRITE;
/*!40000 ALTER TABLE `documentos_postulacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `documentos_postulacion` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_validacion_documento
BEFORE UPDATE ON documentos_postulacion
FOR EACH ROW
BEGIN

    IF OLD.estado_validacion <> NEW.estado_validacion THEN

        SET NEW.fecha_validacion = CURRENT_TIMESTAMP;

    END IF;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `entidades_bancarias`
--

DROP TABLE IF EXISTS `entidades_bancarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entidades_bancarias` (
  `id_banco` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_banco`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entidades_bancarias`
--

LOCK TABLES `entidades_bancarias` WRITE;
/*!40000 ALTER TABLE `entidades_bancarias` DISABLE KEYS */;
/*!40000 ALTER TABLE `entidades_bancarias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_estados`
--

DROP TABLE IF EXISTS `historial_estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_estados` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_postulacion` int(11) NOT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) NOT NULL,
  `justificacion` text DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `fk_historial_postulacion` (`id_postulacion`),
  KEY `fk_historial_usuario` (`id_usuario`),
  CONSTRAINT `fk_historial_postulacion` FOREIGN KEY (`id_postulacion`) REFERENCES `postulaciones` (`id_postulacion`),
  CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_estados`
--

LOCK TABLES `historial_estados` WRITE;
/*!40000 ALTER TABLE `historial_estados` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `informacion_bancaria`
--

DROP TABLE IF EXISTS `informacion_bancaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `informacion_bancaria` (
  `id_informacion_bancaria` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_banco` int(11) NOT NULL,
  `id_tipo_cuenta` int(11) NOT NULL,
  `numero_cuenta` varchar(100) NOT NULL,
  `titular` varchar(200) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_informacion_bancaria`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  KEY `fk_bancaria_banco` (`id_banco`),
  KEY `fk_bancaria_tipo_cuenta` (`id_tipo_cuenta`),
  CONSTRAINT `fk_bancaria_banco` FOREIGN KEY (`id_banco`) REFERENCES `entidades_bancarias` (`id_banco`),
  CONSTRAINT `fk_bancaria_tipo_cuenta` FOREIGN KEY (`id_tipo_cuenta`) REFERENCES `tipos_cuenta` (`id_tipo_cuenta`),
  CONSTRAINT `fk_bancaria_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `informacion_bancaria`
--

LOCK TABLES `informacion_bancaria` WRITE;
/*!40000 ALTER TABLE `informacion_bancaria` DISABLE KEYS */;
/*!40000 ALTER TABLE `informacion_bancaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id_notificacion` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `destinatario` varchar(150) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `cuerpo` text NOT NULL,
  `tipo` varchar(50) DEFAULT 'Correo',
  `estado` enum('Pendiente','Enviada','Error') NOT NULL DEFAULT 'Pendiente',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_envio` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `fk_notificacion_usuario` (`id_usuario`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `postulaciones`
--

DROP TABLE IF EXISTS `postulaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `postulaciones` (
  `id_postulacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_convocatoria` int(11) NOT NULL,
  `datos_formulario` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_formulario`)),
  `estado` enum('Borrador','Enviada','En revisión','Pendiente de documentación','Aprobada','Rechazada') NOT NULL DEFAULT 'Borrador',
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_postulacion`),
  UNIQUE KEY `uk_usuario_convocatoria` (`id_usuario`,`id_convocatoria`),
  KEY `fk_postulacion_convocatoria` (`id_convocatoria`),
  KEY `idx_postulacion_estado` (`estado`),
  KEY `idx_postulacion_fecha` (`fecha_creacion`),
  CONSTRAINT `fk_postulacion_convocatoria` FOREIGN KEY (`id_convocatoria`) REFERENCES `convocatorias` (`id_convocatoria`),
  CONSTRAINT `fk_postulacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `postulaciones`
--

LOCK TABLES `postulaciones` WRITE;
/*!40000 ALTER TABLE `postulaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `postulaciones` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_postulacion_estado
AFTER UPDATE ON postulaciones
FOR EACH ROW
BEGIN

    IF OLD.estado <> NEW.estado THEN

        INSERT INTO historial_estados (
            id_postulacion,
            estado_anterior,
            estado_nuevo,
            id_usuario,
            justificacion
        )
        VALUES (
            NEW.id_postulacion,
            OLD.estado,
            NEW.estado,
            1,
            'Cambio automático de estado'
        );

    END IF;

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
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_postulacion_aprobada
AFTER UPDATE ON postulaciones
FOR EACH ROW
BEGIN

    IF OLD.estado <> 'Aprobada'
       AND NEW.estado = 'Aprobada' THEN

        INSERT INTO notificaciones (
            id_usuario,
            destinatario,
            asunto,
            cuerpo,
            tipo
        )
        SELECT
            u.id_usuario,
            u.correo,
            'Postulación aprobada',
            'Su postulación ha sido aprobada.',
            'Correo'
        FROM usuarios u
        WHERE u.id_usuario = NEW.id_usuario;

    END IF;

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
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_postulacion_rechazada
AFTER UPDATE ON postulaciones
FOR EACH ROW
BEGIN

    IF OLD.estado <> 'Rechazada'
       AND NEW.estado = 'Rechazada' THEN

        INSERT INTO notificaciones (
            id_usuario,
            destinatario,
            asunto,
            cuerpo,
            tipo
        )
        SELECT
            u.id_usuario,
            u.correo,
            'Postulación rechazada',
            'Su postulación ha sido rechazada. Consulte las observaciones.',
            'Correo'
        FROM usuarios u
        WHERE u.id_usuario = NEW.id_usuario;

    END IF;

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
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER tr_auditoria_postulacion
AFTER UPDATE ON postulaciones
FOR EACH ROW
BEGIN

    INSERT INTO auditoria (
        id_usuario,
        accion,
        entidad,
        id_entidad,
        detalle
    )
    VALUES (
        NEW.id_usuario,
        'ACTUALIZAR',
        'postulaciones',
        NEW.id_postulacion,
        JSON_OBJECT(
            'estado_anterior', OLD.estado,
            'estado_nuevo', NEW.estado
        )
    );

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `programas`
--

DROP TABLE IF EXISTS `programas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programas` (
  `id_programa` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_programa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programas`
--

LOCK TABLES `programas` WRITE;
/*!40000 ALTER TABLE `programas` DISABLE KEYS */;
/*!40000 ALTER TABLE `programas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Administra usuarios, beneficios, convocatorias y postulaciones'),(2,'Estudiante','Consulta beneficios y realiza postulaciones');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones`
--

DROP TABLE IF EXISTS `sesiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesiones` (
  `id_sesion` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `token_sesion` varchar(255) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sesion`),
  UNIQUE KEY `token_sesion` (`token_sesion`),
  KEY `fk_sesion_usuario` (`id_usuario`),
  CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones`
--

LOCK TABLES `sesiones` WRITE;
/*!40000 ALTER TABLE `sesiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudes_documentacion`
--

DROP TABLE IF EXISTS `solicitudes_documentacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudes_documentacion` (
  `id_solicitud` int(11) NOT NULL AUTO_INCREMENT,
  `id_postulacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `observacion` text NOT NULL,
  `estado` enum('Pendiente','Atendida','Cerrada') NOT NULL DEFAULT 'Pendiente',
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_atencion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `fk_solicitud_postulacion` (`id_postulacion`),
  KEY `fk_solicitud_usuario` (`id_usuario`),
  CONSTRAINT `fk_solicitud_postulacion` FOREIGN KEY (`id_postulacion`) REFERENCES `postulaciones` (`id_postulacion`),
  CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes_documentacion`
--

LOCK TABLES `solicitudes_documentacion` WRITE;
/*!40000 ALTER TABLE `solicitudes_documentacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitudes_documentacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_cuenta`
--

DROP TABLE IF EXISTS `tipos_cuenta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_cuenta` (
  `id_tipo_cuenta` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id_tipo_cuenta`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_cuenta`
--

LOCK TABLES `tipos_cuenta` WRITE;
/*!40000 ALTER TABLE `tipos_cuenta` DISABLE KEYS */;
INSERT INTO `tipos_cuenta` VALUES (1,'Ahorros'),(2,'Corriente');
/*!40000 ALTER TABLE `tipos_cuenta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_documento`
--

DROP TABLE IF EXISTS `tipos_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_documento` (
  `id_tipo_documento` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_tipo_documento`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_documento`
--

LOCK TABLES `tipos_documento` WRITE;
/*!40000 ALTER TABLE `tipos_documento` DISABLE KEYS */;
INSERT INTO `tipos_documento` VALUES (1,'Documento de identidad','Documento de identificación del estudiante','Activo'),(2,'Certificado bancario','Certificación de cuenta bancaria','Activo'),(3,'Documento adicional','Documento solicitado adicionalmente','Activo');
/*!40000 ALTER TABLE `tipos_documento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `documento` varchar(30) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_programa` int(11) DEFAULT NULL,
  `ficha` varchar(30) DEFAULT NULL,
  `estado` enum('Activo','Inactivo','Bloqueado') NOT NULL DEFAULT 'Activo',
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `fecha_bloqueo` datetime DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `documento` (`documento`),
  UNIQUE KEY `correo` (`correo`),
  KEY `fk_usuario_rol` (`id_rol`),
  KEY `fk_usuario_programa` (`id_programa`),
  KEY `idx_usuario_documento` (`documento`),
  KEY `idx_usuario_nombre` (`nombre`,`apellido`),
  CONSTRAINT `fk_usuario_programa` FOREIGN KEY (`id_programa`) REFERENCES `programas` (`id_programa`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-27  7:50:23

/* ============================================================
   AJUSTES PARA ALINEAR LA BASE DE DATOS CON LA MATRIZ
   BENEFITRACK
   ============================================================ */

/* 1. EPS: la matriz solicita registrar la EPS del aprendiz. */
CREATE TABLE IF NOT EXISTS `eps` (
  `id_eps` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id_eps`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

/* Relacionar la EPS con el usuario/aprendiz. */
ALTER TABLE `usuarios`
  ADD COLUMN `id_eps` int(11) DEFAULT NULL AFTER `id_programa`,
  ADD KEY `fk_usuario_eps` (`id_eps`),
  ADD CONSTRAINT `fk_usuario_eps`
    FOREIGN KEY (`id_eps`) REFERENCES `eps` (`id_eps`);

/* 2. Convocatorias: cantidad de pagos esperados para poder cerrar
      automáticamente una postulación. */
ALTER TABLE `convocatorias`
  ADD COLUMN `numero_pagos_esperados` int(11) NOT NULL DEFAULT 1 AFTER `cupos`,
  ADD CONSTRAINT `chk_pagos_esperados` CHECK (`numero_pagos_esperados` > 0);

/* 3. Estados de postulación alineados con la matriz. */
ALTER TABLE `postulaciones`
  MODIFY COLUMN `estado` enum(
    'Borrador',
    'Enviada',
    'En revisión',
    'Pendiente de documentación',
    'Validada',
    'Aprobada',
    'Rechazada',
    'Completado'
  ) NOT NULL DEFAULT 'Borrador';

/* 4. Tabla de pagos. */
CREATE TABLE IF NOT EXISTS `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_postulacion` int(11) NOT NULL,
  `id_beneficio` int(11) NOT NULL,
  `numero_pago` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha_pago` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('Registrado','Anulado') NOT NULL DEFAULT 'Registrado',
  `observacion` text DEFAULT NULL,
  PRIMARY KEY (`id_pago`),
  UNIQUE KEY `uk_pago_numero` (`id_postulacion`,`numero_pago`),
  KEY `fk_pago_postulacion` (`id_postulacion`),
  KEY `fk_pago_beneficio` (`id_beneficio`),
  CONSTRAINT `fk_pago_postulacion`
    FOREIGN KEY (`id_postulacion`) REFERENCES `postulaciones` (`id_postulacion`),
  CONSTRAINT `fk_pago_beneficio`
    FOREIGN KEY (`id_beneficio`) REFERENCES `beneficios` (`id_beneficio`),
  CONSTRAINT `chk_pago_numero` CHECK (`numero_pago` > 0),
  CONSTRAINT `chk_pago_monto` CHECK (`monto` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

/* 5. Auditoría: registrar también la IP de la acción de postulación
      cuando esté disponible. */

/* 6. Al cambiar a Validada, generar la notificación de confirmación.
      Se conserva Aprobada para compatibilidad con el diseño anterior. */
DROP TRIGGER IF EXISTS `tr_postulacion_aprobada`;
DELIMITER ;;
CREATE TRIGGER `tr_postulacion_aprobada`
AFTER UPDATE ON `postulaciones`
FOR EACH ROW
BEGIN
    IF OLD.estado NOT IN ('Validada','Aprobada')
       AND NEW.estado IN ('Validada','Aprobada') THEN
        INSERT INTO `notificaciones`
        (`id_usuario`,`destinatario`,`asunto`,`cuerpo`,`tipo`)
        SELECT
            u.id_usuario,
            u.correo,
            'Postulación validada',
            'Su postulación ha sido validada.',
            'Correo'
        FROM `usuarios` u
        WHERE u.id_usuario = NEW.id_usuario;
    END IF;
END;;
DELIMITER ;

/* 7. Al cambiar a Rechazada, generar la notificación con observaciones. */
DROP TRIGGER IF EXISTS `tr_postulacion_rechazada`;
DELIMITER ;;
CREATE TRIGGER `tr_postulacion_rechazada`
AFTER UPDATE ON `postulaciones`
FOR EACH ROW
BEGIN
    IF OLD.estado <> 'Rechazada'
       AND NEW.estado = 'Rechazada' THEN
        INSERT INTO `notificaciones`
        (`id_usuario`,`destinatario`,`asunto`,`cuerpo`,`tipo`)
        SELECT
            u.id_usuario,
            u.correo,
            'Postulación rechazada',
            CONCAT(
                'Su postulación ha sido rechazada. ',
                COALESCE(NEW.observaciones, 'Consulte las observaciones.')
            ),
            'Correo'
        FROM `usuarios` u
        WHERE u.id_usuario = NEW.id_usuario;
    END IF;
END;;
DELIMITER ;

/* 8. Completar automáticamente una postulación cuando se hayan
      registrado todos los pagos esperados. */
DROP TRIGGER IF EXISTS `tr_pago_completar_postulacion`;
DELIMITER ;;
CREATE TRIGGER `tr_pago_completar_postulacion`
AFTER INSERT ON `pagos`
FOR EACH ROW
BEGIN
    DECLARE pagos_registrados INT DEFAULT 0;
    DECLARE pagos_esperados INT DEFAULT 0;

    SELECT COUNT(*)
      INTO pagos_registrados
      FROM `pagos`
     WHERE id_postulacion = NEW.id_postulacion
       AND estado = 'Registrado';

    SELECT c.numero_pagos_esperados
      INTO pagos_esperados
      FROM `postulaciones` p
      INNER JOIN `convocatorias` c
        ON c.id_convocatoria = p.id_convocatoria
     WHERE p.id_postulacion = NEW.id_postulacion;

    IF pagos_registrados >= pagos_esperados THEN
        UPDATE `postulaciones`
           SET estado = 'Completado',
               fecha_actualizacion = CURRENT_TIMESTAMP
         WHERE id_postulacion = NEW.id_postulacion;
    END IF;
END;;
DELIMITER ;

/* 9. Datos iniciales de ejemplo para las nuevas tablas. */
INSERT INTO `eps` (`nombre`) VALUES
('SURA'),
('Nueva EPS'),
('Sanitas')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

/* Fin de ajustes */
