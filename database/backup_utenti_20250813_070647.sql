-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nexiosol
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `utenti`
--

DROP TABLE IF EXISTS `utenti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utenti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `cognome` varchar(100) DEFAULT NULL,
  `ruolo` varchar(50) DEFAULT 'utente',
  `azienda_id` int(11) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `primo_accesso` tinyint(1) DEFAULT 0,
  `prima_login` tinyint(1) DEFAULT 1,
  `password_scadenza` date DEFAULT NULL,
  `last_password_change` datetime DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_accesso` datetime DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `cellulare` varchar(50) DEFAULT NULL,
  `data_nascita` date DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL COMMENT 'Token per il reset della password',
  `password_reset_expires` datetime DEFAULT NULL COMMENT 'Scadenza del token di reset password',
  `data_registrazione` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ruolo` (`ruolo`),
  KEY `idx_attivo` (`attivo`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utenti`
--

LOCK TABLES `utenti` WRITE;
/*!40000 ALTER TABLE `utenti` DISABLE KEYS */;
INSERT INTO `utenti` VALUES (2,'asamodeo','$2y$10$q3ZPZsX8X.atocgbjqV2L.EPslOrNhe7rIaRW5ITOewPRer2vzpP6','asamodeo@fortibyte.it','Antonio Silverstro','Amodeo','super_admin',NULL,1,0,1,'2025-10-20','2025-07-22 16:07:28','2025-07-22 10:04:46',NULL,NULL,NULL,'1991-09-02',NULL,NULL,0,NULL,NULL,NULL,'2025-07-27 05:58:32'),(15,'francescobarreca','$2y$10$lbIkd64X1K1KjhJI8Rv25.8wMgt4341yAmwXTbx7zNJC0VccG/yZu','francescobarreca@scosolution.it','Francesco','Barreca','super_admin',9,1,0,1,'2025-10-21','2025-07-23 12:46:15','2025-07-23 09:28:00',NULL,NULL,NULL,'1966-09-26',NULL,NULL,0,NULL,NULL,NULL,'2025-07-27 05:58:32'),(17,'arearicerca','$2y$10$2AN3ZxEegQqZ96M4S7RqJ.5g79Jq/3iIK5oUaio66wkNzgmG/5R6q','qualita@romolohospital.com','Bumbaca','Pierluigi','utente',NULL,0,0,1,'2025-10-23','2025-07-25 15:01:09','2025-07-25 10:49:54',NULL,NULL,NULL,'1991-09-02',NULL,NULL,0,NULL,NULL,NULL,'2025-07-27 05:58:32'),(18,'admin','$2y$10$we8hAvZVOe89jMGvO5aoqeUAiyEf6g3BRfCtzKVOHxkQq0/zIlTeq','admin@example.com','Admin','Test','super_admin',NULL,0,0,1,NULL,NULL,'2025-08-08 14:22:54','2025-08-08 17:10:02',NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2025-08-08 16:22:54'),(23,'','$2y$10$1vJHU/1S8FqdKS6.kqZ5y.DDMs4unX3MNs6.kg7rAp6QO2vaHSa5u','a.oedoma@gmail.com','Test','Piattaforma','utente_speciale',NULL,1,0,1,'2025-11-07','2025-08-09 06:33:35','2025-08-09 05:29:31',NULL,NULL,NULL,'0000-00-00',NULL,NULL,0,NULL,NULL,NULL,'2025-08-09 07:29:31'),(24,'','$2y$10$AYazoo9grvsWbM9zwdo2/egNqnsL7B014Qz1h1HfTPiLrz1A0Vryy','amodeoantoniosilvestro@gmail.com','Utente','Prova','utente',NULL,1,0,1,'2025-11-07','2025-08-09 06:37:11','2025-08-09 05:35:20',NULL,NULL,NULL,'1991-09-02',NULL,NULL,0,NULL,NULL,NULL,'2025-08-09 07:35:20'),(25,'test_api_user','$2y$10$UZtYCrCd3kGCHHDBYxmmLuapFoP77HGj5Vi.aMiOf/tF1agagKAgi','test.api@nexio.com','Test API','User','utente',NULL,0,0,1,NULL,NULL,'2025-08-10 16:56:38',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2025-08-10 17:56:38');
/*!40000 ALTER TABLE `utenti` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utenti_aziende`
--

DROP TABLE IF EXISTS `utenti_aziende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utenti_aziende` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `ruolo` enum('admin','staff','viewer') DEFAULT 'staff',
  `ruolo_azienda` enum('responsabile_aziendale','referente','ospite') DEFAULT 'referente',
  `permessi` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permessi`)),
  `assegnato_da` int(11) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `data_associazione` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `idx_utenti_aziende_ruolo` (`azienda_id`,`ruolo_azienda`,`attivo`),
  KEY `idx_utente_azienda` (`utente_id`,`azienda_id`),
  KEY `idx_azienda_utente` (`azienda_id`,`utente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utenti_aziende`
--

LOCK TABLES `utenti_aziende` WRITE;
/*!40000 ALTER TABLE `utenti_aziende` DISABLE KEYS */;
INSERT INTO `utenti_aziende` VALUES (8,17,8,'staff','referente','[]',NULL,0,'2025-07-26 06:06:08'),(0,15,9,'admin','responsabile_aziendale',NULL,NULL,1,'2025-08-07 19:59:35'),(0,0,6,'admin','responsabile_aziendale',NULL,NULL,1,'2025-08-08 14:22:54'),(0,24,8,'staff','','[]',NULL,1,'2025-08-10 05:35:49');
/*!40000 ALTER TABLE `utenti_aziende` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-13  7:06:43
