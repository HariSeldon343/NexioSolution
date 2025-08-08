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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `azienda_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `allegati`
--

DROP TABLE IF EXISTS `allegati`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `allegati` (
  `id` int(11) NOT NULL,
  `nome_file` varchar(255) NOT NULL,
  `nome_originale` varchar(255) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `dimensione` int(11) DEFAULT NULL,
  `percorso` varchar(500) DEFAULT NULL,
  `entita_tipo` varchar(50) DEFAULT NULL,
  `entita_id` int(11) DEFAULT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `data_caricamento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `allegati`
--

LOCK TABLES `allegati` WRITE;
/*!40000 ALTER TABLE `allegati` DISABLE KEYS */;
/*!40000 ALTER TABLE `allegati` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `autorizzazioni_sanitarie`
--

DROP TABLE IF EXISTS `autorizzazioni_sanitarie`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `autorizzazioni_sanitarie` (
  `id` int(11) NOT NULL,
  `regione` enum('calabria','sicilia') NOT NULL,
  `tipo_struttura` varchar(100) NOT NULL,
  `codice` varchar(50) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `normativa_riferimento` text DEFAULT NULL,
  `icona` varchar(50) DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1,
  `creata_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornata_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `autorizzazioni_sanitarie`
--

LOCK TABLES `autorizzazioni_sanitarie` WRITE;
/*!40000 ALTER TABLE `autorizzazioni_sanitarie` DISABLE KEYS */;
/*!40000 ALTER TABLE `autorizzazioni_sanitarie` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aziende`
--

DROP TABLE IF EXISTS `aziende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aziende` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `ragione_sociale` varchar(255) DEFAULT NULL,
  `codice` varchar(50) DEFAULT NULL,
  `partita_iva` varchar(20) DEFAULT NULL,
  `codice_fiscale` varchar(20) DEFAULT NULL,
  `indirizzo` text DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `cap` varchar(10) DEFAULT NULL,
  `citta` varchar(100) DEFAULT NULL,
  `provincia` varchar(2) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `pec` varchar(255) DEFAULT NULL,
  `sito_web` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `settore` varchar(100) DEFAULT NULL,
  `numero_dipendenti` int(11) DEFAULT NULL,
  `fatturato_annuo` decimal(15,2) DEFAULT NULL,
  `data_fondazione` date DEFAULT NULL,
  `descrizione` text DEFAULT NULL,
  `stato` enum('attiva','sospesa','cancellata') DEFAULT 'attiva',
  `max_referenti` int(11) DEFAULT 5,
  `piano` enum('base','professional','enterprise') DEFAULT 'base',
  `scadenza_piano` date DEFAULT NULL,
  `limite_utenti` int(11) DEFAULT 5,
  `limite_spazio_mb` int(11) DEFAULT 1024,
  `note` text DEFAULT NULL,
  `creata_da` int(11) DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `aggiornato_da` int(11) DEFAULT NULL,
  `responsabile_id` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modificata_da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aziende`
--

LOCK TABLES `aziende` WRITE;
/*!40000 ALTER TABLE `aziende` DISABLE KEYS */;
INSERT INTO `aziende` VALUES (4,'Romolo Hospital','Romolo Hospital srl',NULL,'02056980796','','Via Sandro Pertini snc','','88821','Rocca di Neto','KR',NULL,'',NULL,NULL,'/uploads/loghi/logo_new_1753440087.png','',62,NULL,NULL,NULL,'attiva',2,'base',NULL,5,1024,'',2,NULL,NULL,17,'2025-07-25 10:41:27','2025-07-26 06:01:14',NULL),(5,'Sud Marmi','Sud Marmi srl',NULL,'00594340812','','C/da Piano Alastri, 46','','91015','Custonaci','TP',NULL,'',NULL,NULL,NULL,'Lavorazione Marmi',NULL,NULL,NULL,NULL,'attiva',3,'base',NULL,5,1024,'',2,NULL,NULL,NULL,'2025-07-27 06:46:09','2025-07-27 06:46:09',NULL),(6,'MedTec','Società a Responsabilità Limitata',NULL,'04914270873','','Via Della Costituzione 92-94','','95039','Trecastagni','CT',NULL,'',NULL,NULL,NULL,'Commercio all\'ingrosso di articoli medicali ed ortopedici',NULL,NULL,NULL,NULL,'attiva',2,'base',NULL,5,1024,'',2,NULL,NULL,NULL,'2025-07-28 04:23:19','2025-07-28 04:23:19',NULL);
/*!40000 ALTER TABLE `aziende` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_azienda_insert` AFTER INSERT ON `aziende` FOR EACH ROW BEGIN
        DECLARE cartella_nome VARCHAR(255);
        DECLARE percorso VARCHAR(1000);
        
        SET cartella_nome = NEW.nome;
        SET percorso = CONCAT('/', cartella_nome);
        
        INSERT INTO cartelle (
            nome,
            parent_id,
            percorso_completo,
            azienda_id,
            creato_da,
            data_creazione
        ) VALUES (
            cartella_nome,
            NULL,
            percorso,
            NEW.id,
            1,
            NOW()
        );
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `aziende_iso_config`
--

DROP TABLE IF EXISTS `aziende_iso_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aziende_iso_config` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `tipo_struttura` enum('separata','integrata','personalizzata') DEFAULT 'separata',
  `standards_attivi` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`standards_attivi`)),
  `configurazione_avanzata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configurazione_avanzata`)),
  `stato` enum('configurazione','attiva','sospesa') DEFAULT 'configurazione',
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_attivazione` timestamp NULL DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aziende_iso_config`
--

LOCK TABLES `aziende_iso_config` WRITE;
/*!40000 ALTER TABLE `aziende_iso_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `aziende_iso_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aziende_iso_folders`
--

DROP TABLE IF EXISTS `aziende_iso_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aziende_iso_folders` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `cartella_id` int(11) NOT NULL,
  `standard_codice` varchar(50) NOT NULL,
  `percorso_iso` varchar(1000) NOT NULL,
  `personalizzazioni` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`personalizzazioni`)),
  `stato` enum('attiva','disabilitata') DEFAULT 'attiva',
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aziende_iso_folders`
--

LOCK TABLES `aziende_iso_folders` WRITE;
/*!40000 ALTER TABLE `aziende_iso_folders` DISABLE KEYS */;
/*!40000 ALTER TABLE `aziende_iso_folders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aziende_moduli`
--

DROP TABLE IF EXISTS `aziende_moduli`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aziende_moduli` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `modulo_id` int(11) NOT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `data_attivazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_disattivazione` timestamp NULL DEFAULT NULL,
  `data_scadenza` date DEFAULT NULL,
  `configurazione_custom` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configurazione_custom`)),
  `note` text DEFAULT NULL,
  `attivato_da` int(11) DEFAULT NULL,
  `disattivato_da` int(11) DEFAULT NULL,
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aziende_moduli`
--

LOCK TABLES `aziende_moduli` WRITE;
/*!40000 ALTER TABLE `aziende_moduli` DISABLE KEYS */;
INSERT INTO `aziende_moduli` VALUES (32,5,9,1,'2025-07-27 06:46:14',NULL,NULL,NULL,NULL,2,NULL,'2025-07-27 06:46:14','2025-07-27 06:46:14');
/*!40000 ALTER TABLE `aziende_moduli` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cartelle`
--

DROP TABLE IF EXISTS `cartelle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cartelle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `percorso_completo` text NOT NULL,
  `livello` int(11) NOT NULL DEFAULT 0,
  `azienda_id` int(11) NOT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `colore` varchar(7) DEFAULT '#fbbf24',
  `stato` enum('attiva','cestino','archiviata') DEFAULT 'attiva',
  `description` text DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `is_iso_structure` tinyint(1) DEFAULT 0,
  `iso_standard` varchar(50) DEFAULT NULL,
  `access_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`access_permissions`)),
  `hidden` tinyint(1) DEFAULT 0,
  `data_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creata_da` int(11) DEFAULT NULL,
  `descrizione` text DEFAULT NULL,
  `icona` varchar(50) DEFAULT NULL,
  `ordine_visualizzazione` int(11) DEFAULT 0,
  `visibile` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_azienda` (`azienda_id`),
  KEY `idx_creato_da` (`creato_da`),
  KEY `idx_percorso` (`percorso_completo`(255)),
  KEY `idx_cartelle_gerarchia` (`azienda_id`,`parent_id`,`nome`),
  KEY `idx_iso_structure` (`is_iso_structure`),
  KEY `idx_iso_standard` (`iso_standard`),
  KEY `idx_hidden` (`hidden`),
  KEY `idx_stato` (`stato`),
  KEY `idx_cartelle_data_modifica` (`data_modifica`),
  KEY `idx_cartelle_creata_da` (`creata_da`),
  KEY `idx_cartelle_azienda_id` (`azienda_id`),
  KEY `idx_cartelle_parent_id` (`parent_id`),
  CONSTRAINT `fk_cartelle_parent` FOREIGN KEY (`parent_id`) REFERENCES `cartelle` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cartelle`
--

LOCK TABLES `cartelle` WRITE;
/*!40000 ALTER TABLE `cartelle` DISABLE KEYS */;
INSERT INTO `cartelle` VALUES (1,'Documenti',NULL,'/Documenti',0,1,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(2,'Documenti',NULL,'/Documenti',0,2,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(3,'Documenti',NULL,'/Documenti',0,3,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(4,'Amministrazione',1,'/Documenti/Amministrazione',1,1,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(5,'Clinica',1,'/Documenti/Clinica',1,1,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(6,'Risorse Umane',1,'/Documenti/Risorse Umane',1,1,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(7,'QualitÓ ISO',1,'/Documenti/QualitÓ ISO',1,1,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(8,'Progetti',2,'/Documenti/Progetti',1,2,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(9,'Fornitori',2,'/Documenti/Fornitori',1,2,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(10,'Contratti',2,'/Documenti/Contratti',1,2,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(11,'Ricerca',3,'/Documenti/Ricerca',1,3,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(12,'Brevetti',3,'/Documenti/Brevetti',1,3,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1),(13,'Documentazione Tecnica',3,'/Documenti/Documentazione Tecnica',1,3,2,'2025-08-06 09:20:59',NULL,'2025-08-06 09:20:59','#fbbf24','attiva',NULL,NULL,0,NULL,NULL,0,'2025-08-06 09:20:59',NULL,NULL,NULL,0,1);
/*!40000 ALTER TABLE `cartelle` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER sync_cartelle_timestamps 
        BEFORE UPDATE ON cartelle 
        FOR EACH ROW 
        BEGIN 
            SET NEW.data_modifica = CURRENT_TIMESTAMP;
            SET NEW.data_aggiornamento = CURRENT_TIMESTAMP;
        END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `certificazioni_iso`
--

DROP TABLE IF EXISTS `certificazioni_iso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificazioni_iso` (
  `id` int(11) NOT NULL,
  `codice` varchar(20) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `versione` varchar(20) DEFAULT NULL,
  `icona` varchar(50) DEFAULT NULL,
  `colore` varchar(7) DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1,
  `creata_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornata_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificazioni_iso`
--

LOCK TABLES `certificazioni_iso` WRITE;
/*!40000 ALTER TABLE `certificazioni_iso` DISABLE KEYS */;
INSERT INTO `certificazioni_iso` VALUES (1,'ISO9001','ISO 9001','Sistema di Gestione della Qualità','2015','fas fa-medal','#3b82f6',1,'2025-07-22 13:38:46','2025-07-22 13:38:46'),(2,'ISO14001','ISO 14001','Sistema di Gestione Ambientale','2015','fas fa-leaf','#10b981',1,'2025-07-22 13:38:46','2025-07-22 13:38:46'),(3,'ISO45001','ISO 45001','Sistema di Gestione della Salute e Sicurezza sul Lavoro','2018','fas fa-hard-hat','#f59e0b',1,'2025-07-22 13:38:46','2025-07-22 13:38:46');
/*!40000 ALTER TABLE `certificazioni_iso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `checklist_conformita`
--

DROP TABLE IF EXISTS `checklist_conformita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checklist_conformita` (
  `id` int(11) NOT NULL,
  `conformita_id` int(11) NOT NULL,
  `requisito_id` int(11) NOT NULL,
  `tipo_requisito` enum('certificazione','autorizzazione') NOT NULL,
  `stato` enum('non_iniziato','in_corso','completato','non_applicabile') DEFAULT 'non_iniziato',
  `percentuale_completamento` decimal(5,2) DEFAULT 0.00,
  `data_verifica` date DEFAULT NULL,
  `verificato_da` int(11) DEFAULT NULL,
  `evidenze` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `documento_riferimento_id` int(11) DEFAULT NULL,
  `creata_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornata_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checklist_conformita`
--

LOCK TABLES `checklist_conformita` WRITE;
/*!40000 ALTER TABLE `checklist_conformita` DISABLE KEYS */;
/*!40000 ALTER TABLE `checklist_conformita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificazione`
--

DROP TABLE IF EXISTS `classificazione`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classificazione` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `codice` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `livello` int(11) DEFAULT 1,
  `ordinamento` int(11) DEFAULT 0,
  `attiva` tinyint(1) DEFAULT 1,
  `azienda_id` int(11) DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificazione`
--

LOCK TABLES `classificazione` WRITE;
/*!40000 ALTER TABLE `classificazione` DISABLE KEYS */;
INSERT INTO `classificazione` VALUES (1,'Amministrazione','Documenti amministrativi','AMM',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(2,'Contabilità','Documenti contabili e fiscali','CONT',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(3,'Risorse Umane','Documenti HR e personale','HR',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(4,'Legale','Contratti e documenti legali','LEG',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(5,'Tecnico','Documentazione tecnica','TEC',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(6,'Marketing','Materiale marketing e comunicazione','MKT',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(7,'Qualità','Documenti sistema qualità','QUA',NULL,1,0,1,NULL,NULL,'2025-07-22 07:06:58',NULL,'2025-07-22 07:06:58'),(8,'Fatture','Fatture attive e passive','AMM-FAT',1,2,0,1,NULL,NULL,'2025-07-22 07:07:09',NULL,'2025-07-22 07:07:09'),(9,'Ordini','Ordini di acquisto','AMM-ORD',1,2,0,1,NULL,NULL,'2025-07-22 07:07:09',NULL,'2025-07-22 07:07:09'),(10,'Contratti Fornitori','Contratti con fornitori','AMM-FOR',1,2,0,1,NULL,NULL,'2025-07-22 07:07:09',NULL,'2025-07-22 07:07:09'),(11,'Bilanci','Bilanci annuali','CONT-BIL',2,2,0,1,NULL,NULL,'2025-07-22 07:07:09',NULL,'2025-07-22 07:07:09'),(12,'Dichiarazioni Fiscali','Dichiarazioni IVA, IRES, etc.','CONT-DIC',2,2,0,1,NULL,NULL,'2025-07-22 07:07:09',NULL,'2025-07-22 07:07:09');
/*!40000 ALTER TABLE `classificazione` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificazioni`
--

DROP TABLE IF EXISTS `classificazioni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classificazioni` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `codice` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `livello` int(11) DEFAULT 1,
  `ordinamento` int(11) DEFAULT 0,
  `attiva` tinyint(1) DEFAULT 1,
  `azienda_id` int(11) DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificazioni`
--

LOCK TABLES `classificazioni` WRITE;
/*!40000 ALTER TABLE `classificazioni` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificazioni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classificazioni_iso`
--

DROP TABLE IF EXISTS `classificazioni_iso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classificazioni_iso` (
  `id` int(11) NOT NULL,
  `tipo_iso` varchar(50) NOT NULL,
  `codice` varchar(50) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `ordine` int(11) DEFAULT 0,
  `attivo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classificazioni_iso`
--

LOCK TABLES `classificazioni_iso` WRITE;
/*!40000 ALTER TABLE `classificazioni_iso` DISABLE KEYS */;
/*!40000 ALTER TABLE `classificazioni_iso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_document_schemas`
--

DROP TABLE IF EXISTS `company_document_schemas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_document_schemas` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `schema_type` varchar(50) NOT NULL,
  `schema_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema_config`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_document_schemas`
--

LOCK TABLES `company_document_schemas` WRITE;
/*!40000 ALTER TABLE `company_document_schemas` DISABLE KEYS */;
INSERT INTO `company_document_schemas` VALUES (2,4,'iso9001','{\"name\": \"ISO 9001:2015\", \"description\": \"Sistema di Gestione della QualitÓ\", \"folders\": []}',1,'2025-07-29 16:13:39',2,'2025-07-29 16:13:39'),(3,5,'iso9001','{\"name\": \"ISO 9001:2015\", \"description\": \"Sistema di Gestione della QualitÓ\", \"folders\": []}',1,'2025-07-29 16:13:39',2,'2025-07-29 16:13:39'),(4,6,'iso9001','{\"name\": \"ISO 9001:2015\", \"description\": \"Sistema di Gestione della QualitÓ\", \"folders\": []}',1,'2025-07-29 16:13:39',2,'2025-07-29 16:13:39');
/*!40000 ALTER TABLE `company_document_schemas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `condivisioni_cartelle`
--

DROP TABLE IF EXISTS `condivisioni_cartelle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `condivisioni_cartelle` (
  `id` int(11) NOT NULL,
  `cartella_id` int(11) NOT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `tipo_permesso` varchar(50) DEFAULT 'lettura',
  `token` varchar(64) DEFAULT NULL,
  `scadenza` datetime DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `condivisioni_cartelle`
--

LOCK TABLES `condivisioni_cartelle` WRITE;
/*!40000 ALTER TABLE `condivisioni_cartelle` DISABLE KEYS */;
/*!40000 ALTER TABLE `condivisioni_cartelle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configurazioni`
--

DROP TABLE IF EXISTS `configurazioni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configurazioni` (
  `id` int(11) NOT NULL,
  `chiave` varchar(100) NOT NULL,
  `valore` text DEFAULT NULL,
  `descrizione` text DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configurazioni`
--

LOCK TABLES `configurazioni` WRITE;
/*!40000 ALTER TABLE `configurazioni` DISABLE KEYS */;
INSERT INTO `configurazioni` VALUES (1,'smtp_enabled','1','SMTP disabilitato','2025-07-22 05:01:46','2025-07-22 16:55:45'),(2,'smtp_host','mail.nexiosolution.it','Server SMTP','2025-07-22 05:01:46','2025-07-22 16:55:45'),(3,'smtp_port','465','Porta SMTP','2025-07-22 05:01:46','2025-07-22 16:55:45'),(4,'smtp_from_email','info@nexiosolution.it','Email mittente','2025-07-22 05:01:46','2025-07-22 16:55:45'),(5,'smtp_from_name','Nexio Solution','Nome mittente','2025-07-22 05:01:46','2025-07-22 16:55:45'),(6,'sistema_versione','1.0.0','Versione del sistema','2025-07-22 05:18:25','2025-07-22 05:18:25'),(7,'limite_upload_mb','50','Limite upload file in MB','2025-07-22 05:18:25','2025-07-22 05:18:25'),(8,'giorni_scadenza_password','60','Giorni di validità password','2025-07-22 05:18:25','2025-07-22 05:18:25'),(9,'tentativi_login_max','5','Tentativi massimi di login','2025-07-22 05:18:25','2025-07-22 05:18:25'),(10,'timeout_sessione','3600','Timeout sessione in secondi','2025-07-22 05:18:25','2025-07-22 05:18:25'),(11,'smtp_username','info@nexiosolution.it','Username SMTP','2025-07-22 10:14:20','2025-07-22 16:55:45'),(12,'smtp_password','Ricorda1991','Password SMTP','2025-07-22 10:14:20','2025-07-22 16:55:45'),(13,'smtp_encryption','ssl','Tipo di crittografia SMTP','2025-07-22 10:14:20','2025-07-22 16:55:45'),(21,'email_fallback_enabled','1',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(22,'email_fallback_method','mail',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(23,'email_queue_enabled','1',NULL,'2025-07-22 15:08:45','2025-07-22 15:08:45'),(24,'notify_file_uploaded','1',NULL,'2025-07-22 15:08:45','2025-07-22 15:08:45'),(25,'notify_file_replaced','1',NULL,'2025-07-22 15:08:45','2025-07-22 15:08:45'),(26,'notify_file_deleted','1',NULL,'2025-07-22 15:08:45','2025-07-22 15:08:45'),(27,'notify_folder_created','1',NULL,'2025-07-22 15:08:45','2025-07-22 15:08:45'),(28,'notify_ticket_created','1',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(29,'notify_ticket_status_changed','1',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(30,'notify_event_created','1',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(31,'notify_event_modified','1',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(32,'notify_user_created','1',NULL,'2025-07-22 15:08:45','2025-07-22 16:55:45'),(53,'notify_document_created','1',NULL,'2025-07-22 16:55:45','2025-07-22 16:55:45'),(54,'notify_document_modified','1',NULL,'2025-07-22 16:55:45','2025-07-22 16:55:45'),(55,'notify_document_shared','1',NULL,'2025-07-22 16:55:45','2025-07-22 16:55:45'),(56,'notify_password_reset','1',NULL,'2025-07-22 16:55:45','2025-07-22 16:55:45');
/*!40000 ALTER TABLE `configurazioni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conformita_azienda`
--

DROP TABLE IF EXISTS `conformita_azienda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conformita_azienda` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `tipo` enum('certificazione','autorizzazione') NOT NULL,
  `riferimento_id` int(11) NOT NULL,
  `stato` enum('in_preparazione','in_corso','completata','scaduta','sospesa') DEFAULT 'in_preparazione',
  `percentuale_completamento` decimal(5,2) DEFAULT 0.00,
  `data_inizio` date DEFAULT NULL,
  `data_target` date DEFAULT NULL,
  `data_completamento` date DEFAULT NULL,
  `data_scadenza` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `responsabile_id` int(11) DEFAULT NULL,
  `creata_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornata_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conformita_azienda`
--

LOCK TABLES `conformita_azienda` WRITE;
/*!40000 ALTER TABLE `conformita_azienda` DISABLE KEYS */;
/*!40000 ALTER TABLE `conformita_azienda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_retention_policies`
--

DROP TABLE IF EXISTS `data_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_retention_policies` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `tipo_documento` varchar(100) DEFAULT NULL,
  `periodo_conservazione` int(11) NOT NULL,
  `azione_scadenza` enum('delete','archive','notify') DEFAULT 'notify',
  `attiva` tinyint(1) DEFAULT 1,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_retention_policies`
--

LOCK TABLES `data_retention_policies` WRITE;
/*!40000 ALTER TABLE `data_retention_policies` DISABLE KEYS */;
INSERT INTO `data_retention_policies` VALUES (0,1,'temporary_files',30,'delete',1,NULL,'2025-08-06 09:10:20'),(0,1,'log_files',365,'archive',1,NULL,'2025-08-06 09:10:20'),(0,1,'backup_files',2555,'delete',1,NULL,'2025-08-06 09:10:20'),(0,1,'gdpr_data',1095,'notify',1,NULL,'2025-08-06 09:10:20');
/*!40000 ALTER TABLE `data_retention_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documenti`
--

DROP TABLE IF EXISTS `documenti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documenti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codice` varchar(50) NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `contenuto` longtext DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `tipo_documento` varchar(50) DEFAULT NULL,
  `formato` varchar(10) DEFAULT NULL,
  `dimensione_file` bigint(20) DEFAULT 0,
  `file_size` bigint(20) DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `hash_file` varchar(64) DEFAULT NULL,
  `virus_scan_status` enum('pending','clean','infected','error') DEFAULT 'pending',
  `virus_scan_date` timestamp NULL DEFAULT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `preview_available` tinyint(1) DEFAULT 0,
  `full_text_content` longtext DEFAULT NULL,
  `stato` enum('bozza','pubblicato','archiviato','cestino') DEFAULT 'bozza',
  `versione` int(11) DEFAULT 1,
  `azienda_id` int(11) NOT NULL,
  `cartella_id` int(11) DEFAULT NULL,
  `creato_da` int(11) NOT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificato_da` int(11) DEFAULT NULL,
  `data_modifica` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `classificazione_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `scadenza` date DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `file_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`file_metadata`)),
  `access_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`access_permissions`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codice_azienda` (`codice`,`azienda_id`),
  KEY `idx_azienda` (`azienda_id`),
  KEY `idx_cartella` (`cartella_id`),
  KEY `idx_creato_da` (`creato_da`),
  KEY `idx_stato` (`stato`),
  KEY `idx_tipo` (`tipo_documento`),
  KEY `idx_data_creazione` (`data_creazione`),
  KEY `idx_hash_file` (`hash_file`),
  KEY `idx_virus_scan` (`virus_scan_status`),
  KEY `idx_file_size` (`file_size`),
  KEY `idx_mime_type` (`mime_type`),
  FULLTEXT KEY `idx_fulltext_search` (`titolo`,`descrizione`,`full_text_content`),
  CONSTRAINT `fk_documenti_cartella` FOREIGN KEY (`cartella_id`) REFERENCES `cartelle` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documenti`
--

LOCK TABLES `documenti` WRITE;
/*!40000 ALTER TABLE `documenti` DISABLE KEYS */;
INSERT INTO `documenti` VALUES (1,'DOC-001','Manuale QualitÓ ISO 9001','Manuale del sistema di gestione qualitÓ',NULL,NULL,'manuale','pdf',0,2048000,'application/pdf',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,1,7,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'DOC-002','Procedura Accettazione Pazienti','Procedura operativa standard',NULL,NULL,'procedura','docx',0,156000,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,1,5,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,'DOC-003','Organigramma Aziendale','Struttura organizzativa ospedale',NULL,NULL,'documento','pdf',0,512000,'application/pdf',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,1,4,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(4,'PRJ-001','Progetto Villa Rossi','Documentazione progetto residenziale',NULL,NULL,'progetto','pdf',0,5120000,'application/pdf',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,2,8,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(5,'CNT-001','Contratto Fornitore Marmo','Contratto fornitura marmo Carrara',NULL,NULL,'contratto','pdf',0,1024000,'application/pdf',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,2,10,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(6,'BRV-001','Brevetto Dispositivo Medico','Documentazione brevetto internazionale',NULL,NULL,'brevetto','pdf',0,3072000,'application/pdf',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,3,12,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(7,'TEC-001','Manuale Tecnico Prodotto X','Documentazione tecnica completa',NULL,NULL,'manuale','pdf',0,4096000,'application/pdf',NULL,'pending',NULL,NULL,0,NULL,'pubblicato',1,3,13,2,'2025-08-06 09:21:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `documenti` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documenti_condivisioni`
--

DROP TABLE IF EXISTS `documenti_condivisioni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documenti_condivisioni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `condiviso_da` int(11) NOT NULL,
  `condiviso_con` int(11) DEFAULT NULL,
  `tipo_condivisione` enum('utente','link_pubblico','link_scadenza') DEFAULT 'utente',
  `permessi` enum('lettura','scrittura','download') DEFAULT 'lettura',
  `token_condivisione` varchar(64) DEFAULT NULL,
  `data_scadenza` timestamp NULL DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_condivisioni` (`token_condivisione`),
  KEY `idx_documento_condivisioni` (`documento_id`),
  KEY `idx_condiviso_con` (`condiviso_con`),
  CONSTRAINT `fk_condivisioni_documento` FOREIGN KEY (`documento_id`) REFERENCES `documenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documenti_condivisioni`
--

LOCK TABLES `documenti_condivisioni` WRITE;
/*!40000 ALTER TABLE `documenti_condivisioni` DISABLE KEYS */;
/*!40000 ALTER TABLE `documenti_condivisioni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documenti_versioni`
--

DROP TABLE IF EXISTS `documenti_versioni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documenti_versioni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` int(11) NOT NULL,
  `versione` int(11) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `hash_file` varchar(64) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `note_versione` text DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc_version` (`documento_id`,`versione`),
  KEY `idx_documento_versioni` (`documento_id`),
  KEY `idx_hash_versioni` (`hash_file`),
  CONSTRAINT `fk_versioni_documento` FOREIGN KEY (`documento_id`) REFERENCES `documenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documenti_versioni`
--

LOCK TABLES `documenti_versioni` WRITE;
/*!40000 ALTER TABLE `documenti_versioni` DISABLE KEYS */;
/*!40000 ALTER TABLE `documenti_versioni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_notifications`
--

DROP TABLE IF EXISTS `email_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_notifications` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_html` tinyint(1) DEFAULT 1,
  `status` enum('pending','viewed','sent','failed') DEFAULT 'pending',
  `message_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `viewed_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_notifications`
--

LOCK TABLES `email_notifications` WRITE;
/*!40000 ALTER TABLE `email_notifications` DISABLE KEYS */;
INSERT INTO `email_notifications` VALUES (1,'a.oedoma@gmail.com','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'pending',NULL,NULL,'2025-07-22 17:43:42',NULL,NULL),(2,'admin@nexio.it','Email di Test','<h3>Benvenuto\\!</h3><p>Questa è una email di test per verificare il sistema di notifiche.</p><p>Il sistema funziona correttamente\\!</p>',1,'pending',NULL,NULL,'2025-07-22 17:53:09',NULL,NULL),(3,'admin@nexio.it','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'pending',NULL,NULL,'2025-07-22 17:54:00',NULL,NULL),(4,'admin@nexio.it','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:18:50',NULL,NULL),(5,'admin@nexio.it','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'pending',NULL,NULL,'2025-07-23 07:18:54',NULL,NULL),(6,'admin@nexio.it','Test Email da Brevo - 2025-07-23 09:30:18','<h2>Test Email</h2>\r\n    <p>Questa è una email di test inviata tramite <strong>Brevo API</strong>.</p>\r\n    <p>Data invio: 23/07/2025 09:30:18</p>\r\n    <p>Sistema: Nexio Platform</p>\r\n    <hr>\r\n    <p style=\"color: #666; font-size: 12px;\">Se ricevi questa email, il sistema funziona correttamente!</p>',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:30:18',NULL,NULL),(7,'admin@nexio.it','Benvenuto su Nexio Platform!','\r\n        <!DOCTYPE html>\r\n        <html>\r\n        <head>\r\n            <style>\r\n                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n                .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n                .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }\r\n                .content { padding: 20px; background-color: #f4f4f4; }\r\n                .button { display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; }\r\n                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n            </style>\r\n        </head>\r\n        <body>\r\n            <div class=\'container\'>\r\n                <div class=\'header\'>\r\n                    <h1>Benvenuto su Nexio Platform!</h1>\r\n                </div>\r\n                <div class=\'content\'>\r\n                    <h2>Ciao Admin User,</h2>\r\n                    <p>Il tuo account è stato creato con successo. Ecco i tuoi dati di accesso:</p>\r\n                    <p><strong>Email:</strong> admin@nexio.it<br>\r\n                    <strong>Password temporanea:</strong> Password123!</p>\r\n                    <p>Per motivi di sicurezza, ti chiederemo di cambiare la password al primo accesso.</p>\r\n                    <p style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'http://localhost/piattaforma-collaborativa/login.php\' class=\'button\'>Accedi alla Piattaforma</a>\r\n                    </p>\r\n                    <p>Se hai domande o necessiti di assistenza, non esitare a contattarci.</p>\r\n                    <p>Cordiali saluti,<br>Il Team di Nexio</p>\r\n                </div>\r\n                <div class=\'footer\'>\r\n                    <p>&copy; 2025 Nexio Platform. Tutti i diritti riservati.</p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>\r\n        ',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:30:18',NULL,NULL),(8,'admin@nexio.it','Invito all\'evento: Test Meeting - Brevo Integration','\r\n        <!DOCTYPE html>\r\n        <html>\r\n        <head>\r\n            <style>\r\n                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n                .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n                .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; }\r\n                .content { padding: 20px; background-color: #f4f4f4; }\r\n                .event-details { background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; }\r\n                .button { display: inline-block; padding: 10px 20px; background-color: #27ae60; color: white; text-decoration: none; border-radius: 5px; }\r\n                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n            </style>\r\n        </head>\r\n        <body>\r\n            <div class=\'container\'>\r\n                <div class=\'header\'>\r\n                    <h1>Invito all\'Evento</h1>\r\n                </div>\r\n                <div class=\'content\'>\r\n                    <h2>Ciao Admin User,</h2>\r\n                    <p>Sei stato invitato a partecipare al seguente evento:</p>\r\n                    <div class=\'event-details\'>\r\n                        <h3>Test Meeting - Brevo Integration</h3>\r\n                        <p><strong>Data:</strong> 25/07/2025<br>\r\n                        <strong>Ora:</strong> 09:30<br>\r\n                        <strong>Luogo:</strong> Online - Zoom Meeting<br>\r\n                        <strong>Descrizione:</strong> Test dell\'integrazione del sistema email con Brevo</p>\r\n                    </div>\r\n                    <p style=\'text-align: center;\'>\r\n                        <a href=\'http://localhost/piattaforma-collaborativa/calendario-eventi.php\' class=\'button\'>Visualizza nel Calendario</a>\r\n                    </p>\r\n                    <p>Ti aspettiamo!</p>\r\n                    <p>Cordiali saluti,<br>Il Team di Nexio</p>\r\n                </div>\r\n                <div class=\'footer\'>\r\n                    <p>&copy; 2025 Nexio Platform. Tutti i diritti riservati.</p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>\r\n        ',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:30:19',NULL,NULL),(9,'admin@nexio.it','Reset Password - Nexio Platform','\r\n        <!DOCTYPE html>\r\n        <html>\r\n        <head>\r\n            <style>\r\n                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n                .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n                .header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; }\r\n                .content { padding: 20px; background-color: #f4f4f4; }\r\n                .button { display: inline-block; padding: 10px 20px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px; }\r\n                .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 20px 0; }\r\n                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }\r\n            </style>\r\n        </head>\r\n        <body>\r\n            <div class=\'container\'>\r\n                <div class=\'header\'>\r\n                    <h1>Reset Password</h1>\r\n                </div>\r\n                <div class=\'content\'>\r\n                    <h2>Ciao Admin User,</h2>\r\n                    <p>Abbiamo ricevuto una richiesta di reset password per il tuo account.</p>\r\n                    <p>Se hai effettuato tu questa richiesta, clicca sul pulsante sottostante per reimpostare la tua password:</p>\r\n                    <p style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'http://localhost/piattaforma-collaborativa/reset-password.php?token=test_token_68808f8b4340c\' class=\'button\'>Reset Password</a>\r\n                    </p>\r\n                    <div class=\'warning\'>\r\n                        <p><strong>Attenzione:</strong> Questo link è valido per 1 ora. Se non hai richiesto il reset della password, ignora questa email.</p>\r\n                    </div>\r\n                    <p>Per motivi di sicurezza, se non riesci a cliccare il pulsante, copia e incolla questo link nel tuo browser:</p>\r\n                    <p style=\'word-break: break-all;\'>http://localhost/piattaforma-collaborativa/reset-password.php?token=test_token_68808f8b4340c</p>\r\n                    <p>Cordiali saluti,<br>Il Team di Nexio</p>\r\n                </div>\r\n                <div class=\'footer\'>\r\n                    <p>&copy; 2025 Nexio Platform. Tutti i diritti riservati.</p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>\r\n        ',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:30:19',NULL,NULL),(10,'admin@nexio.it','Test wrapper mail() - 2025-07-23 09:30:19','<h3>Test Wrapper</h3><p>Questo test usa la funzione wrapper <code>brevo_mail()</code> che sostituisce <code>mail()</code></p>',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:30:19',NULL,NULL),(11,'a.oedoma@gmaill.com','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'failed',NULL,'Errore Brevo (401): Key not found','2025-07-23 07:31:04',NULL,NULL),(12,'a.oedoma@gmaill.com','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'pending',NULL,NULL,'2025-07-23 07:31:06',NULL,NULL),(13,'admin@nexio.it','Test Brevo SMTP - 2025-07-23 09:37:24','\r\n    <h2>Test Email via Brevo SMTP</h2>\r\n    <p>Questa email è stata inviata utilizzando le credenziali SMTP di Brevo.</p>\r\n    <p><strong>Data/Ora:</strong> 23/07/2025 09:37:24</p>\r\n    <p><strong>Server:</strong> smtp-relay.brevo.com:587</p>\r\n    <hr>\r\n    <p>Se ricevi questa email, la configurazione SMTP di Brevo funziona correttamente!</p>\r\n    ',1,'sent',NULL,NULL,'2025-07-23 07:37:25',NULL,'2025-07-23 08:37:25'),(14,'a.oedoma@gmail.com','Test Email - Piattaforma Collaborativa','\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\r\n                <h2>Test Email</h2>\r\n                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>\r\n                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>\r\n                <hr>\r\n                <p style=\"font-size: 12px; color: #666;\">\r\n                    Configurazione utilizzata:<br>\r\n                    Server: mail.nexiosolution.it<br>\r\n                    Porta: 465<br>\r\n                    Crittografia: ssl\r\n                </p>\r\n            </body>\r\n            </html>\r\n        ',1,'sent',NULL,NULL,'2025-07-23 07:39:33',NULL,'2025-07-23 08:39:33'),(15,'admin@nexio.it','Benvenuto in Nexio Solution','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Benvenuto in Nexio Solution</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio migliorato -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio con design moderno -->\r\n                    <div style=\'display: inline-block; margin-bottom: 20px;\'>\r\n                        <div style=\'display: inline-block; width: 80px; height: 80px; background: white; border-radius: 20px; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,0.2);\'>\r\n                            <div style=\'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; font-weight: bold; color: #2d5a9f; font-family: Arial, sans-serif;\'>N</div>\r\n                            <div style=\'position: absolute; bottom: 8px; right: 8px; width: 20px; height: 20px; background: #4299e1; border-radius: 50%;\'></div>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: white; font-size: 22px; font-weight: 600; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2);\'>Nexio Platform</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 28px; font-weight: 300; opacity: 0.95;\'>Benvenuto in Nexio Solution</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Nome:</span>\r\n                            <span style=\'color: #4a5568;\'>Test User</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Email:</span>\r\n                            <span style=\'color: #4a5568;\'>admin@nexio.it</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Ruolo:</span>\r\n                            <span style=\'color: #4a5568;\'>Dipendente</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>🕐</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Password temporanea:</span>\r\n                            <span style=\'color: #4a5568;\'>TestPassword123\\!</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi alla Piattaforma</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 07:50:04',NULL,'2025-07-23 08:50:04'),(16,'admin@nexio.it','Password Modificata','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Password Modificata</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio migliorato -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio con design moderno -->\r\n                    <div style=\'display: inline-block; margin-bottom: 20px;\'>\r\n                        <div style=\'display: inline-block; width: 80px; height: 80px; background: white; border-radius: 20px; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,0.2);\'>\r\n                            <div style=\'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; font-weight: bold; color: #2d5a9f; font-family: Arial, sans-serif;\'>N</div>\r\n                            <div style=\'position: absolute; bottom: 8px; right: 8px; width: 20px; height: 20px; background: #4299e1; border-radius: 50%;\'></div>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: white; font-size: 22px; font-weight: 600; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2);\'>Nexio Platform</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 28px; font-weight: 300; opacity: 0.95;\'>Password Modificata</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>La tua password è stata modificata con successo. Se non hai effettuato tu questa operazione, contatta immediatamente l\'amministratore.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>📅</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Data cambio:</span>\r\n                            <span style=\'color: #4a5568;\'>23/07/2025 09:50</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Prossima scadenza:</span>\r\n                            <span style=\'color: #4a5568;\'>21/09/2025</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi al tuo Account</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 07:50:04',NULL,'2025-07-23 08:50:04'),(17,'admin@nexio.it','Benvenuto in Nexio Solution','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Benvenuto in Nexio Solution</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio migliorato -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio con design moderno -->\r\n                    <div style=\'display: inline-block; margin-bottom: 20px;\'>\r\n                        <div style=\'display: inline-block; width: 80px; height: 80px; background: white; border-radius: 20px; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,0.2);\'>\r\n                            <div style=\'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; font-weight: bold; color: #2d5a9f; font-family: Arial, sans-serif;\'>N</div>\r\n                            <div style=\'position: absolute; bottom: 8px; right: 8px; width: 20px; height: 20px; background: #4299e1; border-radius: 50%;\'></div>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: white; font-size: 22px; font-weight: 600; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2);\'>Nexio Platform</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 28px; font-weight: 300; opacity: 0.95;\'>Benvenuto in Nexio Solution</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Nome:</span>\r\n                            <span style=\'color: #4a5568;\'>Mario Rossi</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Email:</span>\r\n                            <span style=\'color: #4a5568;\'>admin@nexio.it</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Ruolo:</span>\r\n                            <span style=\'color: #4a5568;\'>Dipendente</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>🕐</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Password temporanea:</span>\r\n                            <span style=\'color: #4a5568;\'>Password123\\!</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi alla Piattaforma</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 07:51:25',NULL,'2025-07-23 08:51:25'),(18,'admin@nexio.it','Password Modificata','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Password Modificata</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio migliorato -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio con design moderno -->\r\n                    <div style=\'display: inline-block; margin-bottom: 20px;\'>\r\n                        <div style=\'display: inline-block; width: 80px; height: 80px; background: white; border-radius: 20px; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,0.2);\'>\r\n                            <div style=\'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; font-weight: bold; color: #2d5a9f; font-family: Arial, sans-serif;\'>N</div>\r\n                            <div style=\'position: absolute; bottom: 8px; right: 8px; width: 20px; height: 20px; background: #4299e1; border-radius: 50%;\'></div>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: white; font-size: 22px; font-weight: 600; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2);\'>Nexio Platform</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 28px; font-weight: 300; opacity: 0.95;\'>Password Modificata</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>La tua password è stata modificata con successo. Se non hai effettuato tu questa operazione, contatta immediatamente l\'amministratore.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>📅</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Data cambio:</span>\r\n                            <span style=\'color: #4a5568;\'>23/07/2025 09:51</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Prossima scadenza:</span>\r\n                            <span style=\'color: #4a5568;\'>21/09/2025</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi al tuo Account</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 07:51:26',NULL,'2025-07-23 08:51:26'),(19,'a.oedoma@gmail.com','Benvenuto in Nexio Solution','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Benvenuto in Nexio Solution</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio migliorato -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio con design moderno -->\r\n                    <div style=\'display: inline-block; margin-bottom: 20px;\'>\r\n                        <div style=\'display: inline-block; width: 80px; height: 80px; background: white; border-radius: 20px; position: relative; box-shadow: 0 8px 24px rgba(0,0,0,0.2);\'>\r\n                            <div style=\'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 44px; font-weight: bold; color: #2d5a9f; font-family: Arial, sans-serif;\'>N</div>\r\n                            <div style=\'position: absolute; bottom: 8px; right: 8px; width: 20px; height: 20px; background: #4299e1; border-radius: 50%;\'></div>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: white; font-size: 22px; font-weight: 600; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2);\'>Nexio Platform</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 28px; font-weight: 300; opacity: 0.95;\'>Benvenuto in Nexio Solution</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Nome:</span>\r\n                            <span style=\'color: #4a5568;\'>Antonio Silverstro Amodeo</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Email:</span>\r\n                            <span style=\'color: #4a5568;\'>a.oedoma@gmail.com</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Ruolo:</span>\r\n                            <span style=\'color: #4a5568;\'>Super_admin</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>🕐</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Password temporanea:</span>\r\n                            <span style=\'color: #4a5568;\'>8Rvqk,)6</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi alla Piattaforma</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 08:32:45',NULL,'2025-07-23 09:32:45'),(20,'a.oedoma@gmail.com','Benvenuto in Nexio Solution','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Benvenuto in Nexio Solution</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio dal file SVG -->\r\n                    <div style=\'margin-bottom: 20px;\'>\r\n                        <!-- Stella e testo Nexio in HTML per compatibilità email -->\r\n                        <div style=\'display: inline-block;\'>\r\n                            <span style=\'font-size: 36px; color: #2d5a9f; vertical-align: middle; font-family: Arial, sans-serif;\'>✦</span>\r\n                            <span style=\'font-size: 32px; font-weight: 600; color: white; margin-left: 15px; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;\'>Nexio</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: rgba(255, 255, 255, 0.9); font-size: 14px; margin-bottom: 15px;\'>Semplifica, Connetti, Cresci Insieme</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 26px; font-weight: 400;\'>Benvenuto in Nexio Solution</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Nome:</span>\r\n                            <span style=\'color: #4a5568;\'>Antonio Silverstro Amodeo</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Email:</span>\r\n                            <span style=\'color: #4a5568;\'>a.oedoma@gmail.com</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Ruolo:</span>\r\n                            <span style=\'color: #4a5568;\'>Super_admin</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>🕐</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Password temporanea:</span>\r\n                            <span style=\'color: #4a5568;\'>sl2CR:s?</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi alla Piattaforma</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 09:05:07',NULL,'2025-07-23 10:05:07'),(21,'a.oedoma@gmail.com','Password Modificata','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Password Modificata</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio dal file SVG -->\r\n                    <div style=\'margin-bottom: 20px;\'>\r\n                        <!-- Stella e testo Nexio in HTML per compatibilità email -->\r\n                        <div style=\'display: inline-block;\'>\r\n                            <span style=\'font-size: 36px; color: #2d5a9f; vertical-align: middle; font-family: Arial, sans-serif;\'>✦</span>\r\n                            <span style=\'font-size: 32px; font-weight: 600; color: white; margin-left: 15px; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;\'>Nexio</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: rgba(255, 255, 255, 0.9); font-size: 14px; margin-bottom: 15px;\'>Semplifica, Connetti, Cresci Insieme</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 26px; font-weight: 400;\'>Password Modificata</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>La tua password è stata modificata con successo. Se non hai effettuato tu questa operazione, contatta immediatamente l\'amministratore.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>📅</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Data cambio:</span>\r\n                            <span style=\'color: #4a5568;\'>23/07/2025 11:06</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Prossima scadenza:</span>\r\n                            <span style=\'color: #4a5568;\'>21/09/2025</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi al tuo Account</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 09:06:24',NULL,'2025-07-23 10:06:24'),(22,'francescobarreca@scosolution.it','Benvenuto in Nexio Solution','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Benvenuto in Nexio Solution</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio dal file SVG -->\r\n                    <div style=\'margin-bottom: 20px;\'>\r\n                        <!-- Stella e testo Nexio in HTML per compatibilità email -->\r\n                        <div style=\'display: inline-block;\'>\r\n                            <span style=\'font-size: 36px; color: #2d5a9f; vertical-align: middle; font-family: Arial, sans-serif;\'>✦</span>\r\n                            <span style=\'font-size: 32px; font-weight: 600; color: white; margin-left: 15px; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;\'>Nexio</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: rgba(255, 255, 255, 0.9); font-size: 14px; margin-bottom: 15px;\'>Semplifica, Connetti, Cresci Insieme</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 26px; font-weight: 400;\'>Benvenuto in Nexio Solution</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Nome:</span>\r\n                            <span style=\'color: #4a5568;\'>Francesco Barreca</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Email:</span>\r\n                            <span style=\'color: #4a5568;\'>francescobarreca@scosolution.it</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Ruolo:</span>\r\n                            <span style=\'color: #4a5568;\'>Super_admin</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>🕐</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Password temporanea:</span>\r\n                            <span style=\'color: #4a5568;\'>2l?O{\"TK</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi alla Piattaforma</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 09:28:01',NULL,'2025-07-23 10:28:01'),(23,'francescobarreca@scosolution.it','Password Reimpostata - Nexio','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Password Reimpostata - Nexio</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio dal file SVG -->\r\n                    <div style=\'margin-bottom: 20px;\'>\r\n                        <!-- Stella e testo Nexio in HTML per compatibilità email -->\r\n                        <div style=\'display: inline-block;\'>\r\n                            <span style=\'font-size: 36px; color: #2d5a9f; vertical-align: middle; font-family: Arial, sans-serif;\'>✦</span>\r\n                            <span style=\'font-size: 32px; font-weight: 600; color: white; margin-left: 15px; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;\'>Nexio</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: rgba(255, 255, 255, 0.9); font-size: 14px; margin-bottom: 15px;\'>Semplifica, Connetti, Cresci Insieme</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 26px; font-weight: 400;\'>Password Reimpostata - Nexio</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>\r\n            <html>\r\n            <body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;\">\r\n                <div style=\"background-color: #f8f9fa; padding: 20px; border-radius: 5px;\">\r\n                    <h2 style=\"color: #2d3748; margin-bottom: 20px;\">Password Reimpostata</h2>\r\n                    \r\n                    <div style=\"background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;\">\r\n                        <p>Ciao <strong>Francesco</strong>,</p>\r\n                        <p>La tua password è stata reimpostata da un amministratore. Di seguito trovi i nuovi dettagli per accedere:</p>\r\n                        \r\n                        <div style=\"background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;\">\r\n                            <p><strong>Username:</strong> francescobarreca</p>\r\n                            <p><strong>Email:</strong> francescobarreca@scosolution.it</p><p><strong>Nuova password:</strong> sHb#0&gt;9(</p>\r\n                            <p style=\"color: #dc3545; font-size: 14px; font-weight: bold;\">🔐 IMPORTANTE: Dovrai cambiare questa password al primo accesso per motivi di sicurezza</p>\r\n                        </div>\r\n                        \r\n                        <p>Per accedere alla piattaforma, clicca sul pulsante qui sotto:</p>\r\n                    </div>\r\n                    \r\n                    <a href=\"http://localhost/login.php\" \r\n                       style=\"display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px;\">\r\n                        Accedi alla Piattaforma\r\n                    </a>\r\n                    \r\n                    <p style=\"margin-top: 20px; font-size: 14px; color: #718096;\">\r\n                        Se non hai richiesto questo reset, contatta immediatamente l\'amministratore del sistema.\r\n                    </p>\r\n                </div>\r\n            </body>\r\n            </html>\r\n            </p>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 11:44:33',NULL,'2025-07-23 12:44:33'),(24,'francescobarreca@scosolution.it','Password Modificata','\r\n        <!DOCTYPE html>\r\n        <html lang=\'it\'>\r\n        <head>\r\n            <meta charset=\'UTF-8\'>\r\n            <meta name=\'viewport\' content=\'width=device-width, initial-scale=1.0\'>\r\n            <title>Password Modificata</title>\r\n        </head>\r\n        <body style=\'margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\'>\r\n            <div style=\'max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\'>\r\n                <!-- Header con logo Nexio -->\r\n                <div style=\'background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 40px 30px; text-align: center;\'>\r\n                    <!-- Logo Nexio dal file SVG -->\r\n                    <div style=\'margin-bottom: 20px;\'>\r\n                        <!-- Stella e testo Nexio in HTML per compatibilità email -->\r\n                        <div style=\'display: inline-block;\'>\r\n                            <span style=\'font-size: 36px; color: #2d5a9f; vertical-align: middle; font-family: Arial, sans-serif;\'>✦</span>\r\n                            <span style=\'font-size: 32px; font-weight: 600; color: white; margin-left: 15px; vertical-align: middle; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;\'>Nexio</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'color: rgba(255, 255, 255, 0.9); font-size: 14px; margin-bottom: 15px;\'>Semplifica, Connetti, Cresci Insieme</div>\r\n                    <h1 style=\'color: white; margin: 0; font-size: 26px; font-weight: 400;\'>Password Modificata</h1>\r\n                </div>\r\n                \r\n                <!-- Contenuto principale -->\r\n                <div style=\'padding: 30px;\'>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;\'>La tua password è stata modificata con successo. Se non hai effettuato tu questa operazione, contatta immediatamente l\'amministratore.</p>\r\n                    <div style=\'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;\'>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>📅</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Data cambio:</span>\r\n                            <span style=\'color: #4a5568;\'>23/07/2025 13:46</span>\r\n                        </div>\r\n                        <div style=\'display: flex; align-items: center; margin-bottom: 12px;\'>\r\n                            <span style=\'display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;\'>•</span>\r\n                            <span style=\'color: #2d3748; font-weight: 600; margin-right: 8px;\'>Prossima scadenza:</span>\r\n                            <span style=\'color: #4a5568;\'>21/09/2025</span>\r\n                        </div>\r\n                    </div>\r\n                    <div style=\'text-align: center; margin: 30px 0;\'>\r\n                        <a href=\'https://app.nexiosolution.it/piattaforma-collaborativa/login.php\' style=\'display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;\'>Accedi al tuo Account</a>\r\n                    </div>\r\n                    <p style=\'color: #4a5568; line-height: 1.6; margin-bottom: 0;\'>Cordiali saluti,<br><strong>Il team di Nexio</strong></p>\r\n                </div>\r\n                \r\n                <!-- Footer -->\r\n                <div style=\'background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;\'>\r\n                    <p style=\'color: #718096; font-size: 12px; line-height: 1.5; margin: 0;\'>\r\n                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>\r\n                    </p>\r\n                </div>\r\n            </div>\r\n        </body>\r\n        </html>',1,'sent',NULL,NULL,'2025-07-23 11:46:15',NULL,'2025-07-23 12:46:15'),(25,'asamodeo@fortibyte.it','Task Cancellato: Office - Remoto','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Task Cancellato: Office - Remoto</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Task Cancellato: Office - Remoto\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Task Cancellato&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Task Cancellato<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Il task che ti era stato assegnato è stato cancellato.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Attività:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Office<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Periodo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 - 24/07/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Città:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Remoto<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Giornate previste:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                1.0<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Cancellato da:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Antonio Silverstro Amodeo<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-23 13:46:01',NULL,'2025-07-23 14:46:01'),(26,'asamodeo@fortibyte.it','Invito: prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Invito: prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Invito: prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Invito all&amp;#039;evento&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Invito all&amp;#039;evento<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Gentile Antonio Silverstro Amodeo, sei stato invitato a partecipare al seguente evento.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Evento:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                prova<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data inizio:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data fine:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 10:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Visualizza nel Calendario<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Visualizza nel Calendario<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-23 14:10:37',NULL,'2025-07-23 15:10:37'),(27,'a.oedoma@gmail.com','Benvenuto in Nexio Solution','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Benvenuto in Nexio Solution</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Benvenuto in Nexio Solution\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Benvenuto in Nexio Solution&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Benvenuto in Nexio Solution<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Nome:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Pippo Baudo<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Email:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                a.oedoma@gmail.com<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Ruolo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Utente_speciale<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Password temporanea:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                U#1*srb(<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/login.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Accedi alla Piattaforma<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/login.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Accedi alla Piattaforma<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-23 14:12:55',NULL,'2025-07-23 15:12:55'),(28,'a.oedoma@gmail.com','Invito: test 2','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Invito: test 2</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Invito: test 2\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Invito all&amp;#039;evento&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Invito all&amp;#039;evento<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Gentile Pippo Baudo, sei stato invitato a partecipare al seguente evento.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Evento:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                test 2<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data inizio:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data fine:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 10:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Visualizza nel Calendario<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Visualizza nel Calendario<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-23 14:14:44',NULL,'2025-07-23 15:14:44'),(29,'a.oedoma@gmail.com','Invito: Prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Invito: Prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Invito: Prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Invito all&amp;#039;evento&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Invito all&amp;#039;evento<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Gentile Pippo Baudo, sei stato invitato a partecipare al seguente evento.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Evento:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Prova<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data inizio:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data fine:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 10:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Visualizza nel Calendario<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Visualizza nel Calendario<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-23 14:15:42',NULL,'2025-07-23 15:15:42'),(30,'a.oedoma@gmail.com','Invito: Prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Invito: Prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Invito: Prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Invito all&amp;#039;evento&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Invito all&amp;#039;evento<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Gentile Pippo Baudo, sei stato invitato a partecipare al seguente evento.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Evento:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Prova<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data inizio:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data fine:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 10:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Visualizza nel Calendario<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/calendario-eventi.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Visualizza nel Calendario<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-23 14:28:03',NULL,'2025-07-23 15:28:03'),(31,'asamodeo@fortibyte.it','Nuovo Task Assegnato: Consulenza - prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Nuovo Task Assegnato: Consulenza - prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Nuovo Task Assegnato: Consulenza - prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Nuovo Task Assegnato&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Nuovo Task Assegnato<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Ti è stato assegnato un nuovo task.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Attività:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Consulenza<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Giornate previste:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                0.5<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Periodo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025 - 24/07/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Città:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                prova<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Prodotto/Servizio:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                9001<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Assegnato da:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Antonio Silverstro Amodeo<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;http://localhost/piattaforma-collaborativa/calendario-eventi.php?view=month&amp;amp;date=2025-07-24&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Visualizza Calendario<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;http://localhost/piattaforma-collaborativa/calendario-eventi.php?view=month&amp;amp;date=2025-07-24&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Visualizza Calendario<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-24 11:12:44',NULL,'2025-07-24 12:12:44'),(32,'asamodeo@fortibyte.it','Evento Cancellato: prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Evento Cancellato: prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Evento Cancellato: prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Evento Cancellato&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Evento Cancellato<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        L&amp;#039;evento &amp;#039;prova&amp;#039; è stato cancellato.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data originale:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Ora:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Luogo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                <br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Motivo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                L&amp;#039;evento è stato cancellato dall&amp;#039;organizzatore<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-24 11:13:02',NULL,'2025-07-24 12:13:02'),(33,'a.oedoma@gmail.com','Evento Cancellato: test 2','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Evento Cancellato: test 2</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Evento Cancellato: test 2\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Evento Cancellato&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Evento Cancellato<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        L&amp;#039;evento &amp;#039;test 2&amp;#039; è stato cancellato.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data originale:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Ora:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Luogo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                <br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Motivo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                L&amp;#039;evento è stato cancellato dall&amp;#039;organizzatore<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-24 11:13:10',NULL,'2025-07-24 12:13:10'),(34,'a.oedoma@gmail.com','Evento Cancellato: Prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Evento Cancellato: Prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Evento Cancellato: Prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Evento Cancellato&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Evento Cancellato<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        L&amp;#039;evento &amp;#039;Prova&amp;#039; è stato cancellato.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data originale:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Ora:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Luogo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                <br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Motivo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                L&amp;#039;evento è stato cancellato dall&amp;#039;organizzatore<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-24 11:13:16',NULL,'2025-07-24 12:13:16'),(35,'a.oedoma@gmail.com','Evento Cancellato: Prova','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Evento Cancellato: Prova</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Evento Cancellato: Prova\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Evento Cancellato&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Evento Cancellato<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        L&amp;#039;evento &amp;#039;Prova&amp;#039; è stato cancellato.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data originale:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                24/07/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Ora:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                09:00<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Luogo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                <br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Motivo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                L&amp;#039;evento è stato cancellato dall&amp;#039;organizzatore<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-24 11:13:23',NULL,'2025-07-24 12:13:23'),(36,'arearicerca@romolohospital.com','Benvenuto in Nexio Solution','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Benvenuto in Nexio Solution</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Benvenuto in Nexio Solution\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Benvenuto in Nexio Solution&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Benvenuto in Nexio Solution<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Nome:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Utente Prova<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Email:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                arearicerca@romolohospital.com<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Ruolo:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                <br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Password temporanea:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                Q:K0xkp6<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/login.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Accedi alla Piattaforma<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/login.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Accedi alla Piattaforma<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-25 10:49:55',NULL,'2025-07-25 11:49:55'),(37,'arearicerca@romolohospital.com','Password Reimpostata - Nexio','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Password Reimpostata - Nexio</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Password Reimpostata - Nexio\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        <br />\r\n            &lt;html&gt;<br />\r\n            &lt;body style=&quot;font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;&quot;&gt;<br />\r\n                &lt;div style=&quot;background-color: #f8f9fa; padding: 20px; border-radius: 5px;&quot;&gt;<br />\r\n                    &lt;h2 style=&quot;color: #2d3748; margin-bottom: 20px;&quot;&gt;Password Reimpostata&lt;/h2&gt;<br />\r\n                    <br />\r\n                    &lt;div style=&quot;background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;&quot;&gt;<br />\r\n                        &lt;p&gt;Ciao &lt;strong&gt;Utente&lt;/strong&gt;,&lt;/p&gt;<br />\r\n                        &lt;p&gt;La tua password è stata reimpostata da un amministratore. Di seguito trovi i nuovi dettagli per accedere:&lt;/p&gt;<br />\r\n                        <br />\r\n                        &lt;div style=&quot;background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;&quot;&gt;<br />\r\n                            &lt;p&gt;&lt;strong&gt;Username:&lt;/strong&gt; arearicerca&lt;/p&gt;<br />\r\n                            &lt;p&gt;&lt;strong&gt;Email:&lt;/strong&gt; arearicerca@romolohospital.com&lt;/p&gt;&lt;p&gt;&lt;strong&gt;Nuova password:&lt;/strong&gt; 9i${.99N&lt;/p&gt;<br />\r\n                            &lt;p style=&quot;color: #dc3545; font-size: 14px; font-weight: bold;&quot;&gt;🔐 IMPORTANTE: Dovrai cambiare questa password al primo accesso per motivi di sicurezza&lt;/p&gt;<br />\r\n                        &lt;/div&gt;<br />\r\n                        <br />\r\n                        &lt;p&gt;Per accedere alla piattaforma, clicca sul pulsante qui sotto:&lt;/p&gt;<br />\r\n                    &lt;/div&gt;<br />\r\n                    <br />\r\n                    &lt;a href=&quot;http://localhost/login.php&quot; <br />\r\n                       style=&quot;display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px;&quot;&gt;<br />\r\n                        Accedi alla Piattaforma<br />\r\n                    &lt;/a&gt;<br />\r\n                    <br />\r\n                    &lt;p style=&quot;margin-top: 20px; font-size: 14px; color: #718096;&quot;&gt;<br />\r\n                        Se non hai richiesto questo reset, contatta immediatamente l&#039;amministratore del sistema.<br />\r\n                    &lt;/p&gt;<br />\r\n                &lt;/div&gt;<br />\r\n            &lt;/body&gt;<br />\r\n            &lt;/html&gt;<br />\r\n            \r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-25 10:52:39',NULL,'2025-07-25 11:52:39'),(38,'arearicerca@romolohospital.com','Password Modificata','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Password Modificata</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Password Modificata\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Transitional//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd&quot;&gt;<br />\r\n&lt;html xmlns=&quot;http://www.w3.org/1999/xhtml&quot;&gt;<br />\r\n&lt;head&gt;<br />\r\n    &lt;meta http-equiv=&quot;Content-Type&quot; content=&quot;text/html; charset=UTF-8&quot; /&gt;<br />\r\n    &lt;meta name=&quot;viewport&quot; content=&quot;width=device-width, initial-scale=1.0&quot;/&gt;<br />\r\n    &lt;title&gt;Password Modificata&lt;/title&gt;<br />\r\n    &lt;!--[if mso]&gt;<br />\r\n    &lt;noscript&gt;<br />\r\n        &lt;xml&gt;<br />\r\n            &lt;o:OfficeDocumentSettings&gt;<br />\r\n                &lt;o:AllowPNG/&gt;<br />\r\n                &lt;o:PixelsPerInch&gt;96&lt;/o:PixelsPerInch&gt;<br />\r\n            &lt;/o:OfficeDocumentSettings&gt;<br />\r\n        &lt;/xml&gt;<br />\r\n    &lt;/noscript&gt;<br />\r\n    &lt;![endif]--&gt;<br />\r\n&lt;/head&gt;<br />\r\n&lt;body style=&quot;margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;&quot;&gt;<br />\r\n    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f4f4f4;&quot;&gt;<br />\r\n        &lt;tr&gt;<br />\r\n            &lt;td align=&quot;center&quot; style=&quot;padding: 40px 0;&quot;&gt;<br />\r\n                &lt;!-- Container principale --&gt;<br />\r\n                &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;600&quot; style=&quot;background-color: #ffffff; border: 1px solid #dddddd;&quot;&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Header --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#2d5a9f&quot; style=&quot;padding: 40px 20px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding-bottom: 20px;&quot;&gt;<br />\r\n                                        &lt;!-- Logo Nexio in formato testo --&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;&quot;&gt;<br />\r\n                                                    NEXIO<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 14px; padding-bottom: 10px;&quot;&gt;<br />\r\n                                        Semplifica, Connetti, Cresci Insieme<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;&quot;&gt;<br />\r\n                                        Password Modificata<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Contenuto principale --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td style=&quot;padding: 40px 30px;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;&quot;&gt;<br />\r\n                                        La tua password è stata modificata con successo. Se non hai effettuato tu questa operazione, contatta immediatamente l&amp;#039;amministratore.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;padding: 20px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;background-color: #f8f8f8; border: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td style=&quot;padding: 20px;&quot;&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Data cambio:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                25/07/2025 12:53<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                    &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot; width=&quot;100%&quot; style=&quot;margin-bottom: 10px;&quot;&gt;<br />\r\n                                                        &lt;tr&gt;<br />\r\n                                                            &lt;td width=&quot;30%&quot; style=&quot;color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;&quot;&gt;<br />\r\n                                                                Prossima scadenza:<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                            &lt;td width=&quot;70%&quot; style=&quot;color: #333333; font-size: 14px;&quot;&gt;<br />\r\n                                                                23/09/2025<br />\r\n                                                            &lt;/td&gt;<br />\r\n                                                        &lt;/tr&gt;<br />\r\n                                                    &lt;/table&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;padding: 30px 0;&quot;&gt;<br />\r\n                                        &lt;table border=&quot;0&quot; cellspacing=&quot;0&quot; cellpadding=&quot;0&quot;&gt;<br />\r\n                                            &lt;tr&gt;<br />\r\n                                                &lt;td align=&quot;center&quot; style=&quot;border-radius: 4px;&quot; bgcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                                    &lt;a href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/login.php&quot; target=&quot;_blank&quot; style=&quot;font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;&quot;&gt;<br />\r\n                                                        Accedi al tuo Account<br />\r\n                                                    &lt;/a&gt;<br />\r\n                                                &lt;/td&gt;<br />\r\n                                            &lt;/tr&gt;<br />\r\n                                        &lt;/table&gt;<br />\r\n                                        &lt;!-- Fallback per Outlook --&gt;<br />\r\n                                        &lt;!--[if mso]&gt;<br />\r\n                                        &lt;v:roundrect xmlns:v=&quot;urn:schemas-microsoft-com:vml&quot; xmlns:w=&quot;urn:schemas-microsoft-com:office:word&quot; href=&quot;https://app.nexiosolution.it/piattaforma-collaborativa/login.php&quot; style=&quot;height:40px;v-text-anchor:middle;width:200px;&quot; arcsize=&quot;10%&quot; stroke=&quot;f&quot; fillcolor=&quot;#4299e1&quot;&gt;<br />\r\n                                            &lt;w:anchorlock/&gt;<br />\r\n                                            &lt;center style=&quot;color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;&quot;&gt;<br />\r\n                                                Accedi al tuo Account<br />\r\n                                            &lt;/center&gt;<br />\r\n                                        &lt;/v:roundrect&gt;<br />\r\n                                        &lt;![endif]--&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td style=&quot;color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;&quot;&gt;<br />\r\n                                        Cordiali saluti,&lt;br&gt;<br />\r\n                                        &lt;strong&gt;Il team di Nexio&lt;/strong&gt;<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                    <br />\r\n                    &lt;!-- Footer --&gt;<br />\r\n                    &lt;tr&gt;<br />\r\n                        &lt;td align=&quot;center&quot; bgcolor=&quot;#f8f8f8&quot; style=&quot;padding: 30px 20px; border-top: 1px solid #e0e0e0;&quot;&gt;<br />\r\n                            &lt;table border=&quot;0&quot; cellpadding=&quot;0&quot; cellspacing=&quot;0&quot;&gt;<br />\r\n                                &lt;tr&gt;<br />\r\n                                    &lt;td align=&quot;center&quot; style=&quot;color: #666666; font-size: 12px; line-height: 18px;&quot;&gt;<br />\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma &lt;strong&gt;Nexio&lt;/strong&gt;.&lt;br&gt;<br />\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.<br />\r\n                                    &lt;/td&gt;<br />\r\n                                &lt;/tr&gt;<br />\r\n                            &lt;/table&gt;<br />\r\n                        &lt;/td&gt;<br />\r\n                    &lt;/tr&gt;<br />\r\n                &lt;/table&gt;<br />\r\n            &lt;/td&gt;<br />\r\n        &lt;/tr&gt;<br />\r\n    &lt;/table&gt;<br />\r\n&lt;/body&gt;<br />\r\n&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-25 10:53:39',NULL,'2025-07-25 11:53:39'),(39,'test@example.com','Test BrevoSMTP - 2025-07-25 13:50:43','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Test BrevoSMTP - 2025-07-25 13:50:43</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Test BrevoSMTP - 2025-07-25 13:50:43\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;html&gt;&lt;body&gt;&lt;h2&gt;Test Email BrevoSMTP&lt;/h2&gt;&lt;p&gt;Questa email è stata inviata tramite BrevoSMTP.&lt;/p&gt;&lt;p&gt;Data: 25/07/2025 13:50:43&lt;/p&gt;&lt;/body&gt;&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-25 11:50:44',NULL,'2025-07-25 12:50:44'),(40,'a.oedoma@gmail.com','Test BrevoSMTP - 2025-07-25 13:50:51','<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\r\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\r\n<head>\r\n    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\r\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>\r\n    <title>Test BrevoSMTP - 2025-07-25 13:50:51</title>\r\n    <!--[if mso]>\r\n    <noscript>\r\n        <xml>\r\n            <o:OfficeDocumentSettings>\r\n                <o:AllowPNG/>\r\n                <o:PixelsPerInch>96</o:PixelsPerInch>\r\n            </o:OfficeDocumentSettings>\r\n        </xml>\r\n    </noscript>\r\n    <![endif]-->\r\n</head>\r\n<body style=\"margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;\">\r\n    <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">\r\n        <tr>\r\n            <td align=\"center\" style=\"padding: 40px 0;\">\r\n                <!-- Container principale -->\r\n                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"background-color: #ffffff; border: 1px solid #dddddd;\">\r\n                    \r\n                    <!-- Header -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#2d5a9f\" style=\"padding: 40px 20px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"padding-bottom: 20px;\">\r\n                                        <!-- Logo Nexio in formato testo -->\r\n                                        <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                            <tr>\r\n                                                <td style=\"font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;\">\r\n                                                    NEXIO\r\n                                                </td>\r\n                                            </tr>\r\n                                        </table>\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 14px; padding-bottom: 10px;\">\r\n                                        Semplifica, Connetti, Cresci Insieme\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;\">\r\n                                        Test BrevoSMTP - 2025-07-25 13:50:51\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Contenuto principale -->\r\n                    <tr>\r\n                        <td style=\"padding: 40px 30px;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;\">\r\n                                        &lt;html&gt;&lt;body&gt;&lt;h2&gt;Test Email BrevoSMTP&lt;/h2&gt;&lt;p&gt;Questa email è stata inviata tramite BrevoSMTP.&lt;/p&gt;&lt;p&gt;Data: 25/07/2025 13:50:51&lt;/p&gt;&lt;/body&gt;&lt;/html&gt;\r\n                                    </td>\r\n                                </tr>\r\n                                <tr>\r\n                                    <td style=\"color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;\">\r\n                                        Cordiali saluti,<br>\r\n                                        <strong>Il team di Nexio</strong>\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                    \r\n                    <!-- Footer -->\r\n                    <tr>\r\n                        <td align=\"center\" bgcolor=\"#f8f8f8\" style=\"padding: 30px 20px; border-top: 1px solid #e0e0e0;\">\r\n                            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\r\n                                <tr>\r\n                                    <td align=\"center\" style=\"color: #666666; font-size: 12px; line-height: 18px;\">\r\n                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>Nexio</strong>.<br>\r\n                                        Per modificare le preferenze di notifica, accedi al tuo profilo.\r\n                                    </td>\r\n                                </tr>\r\n                            </table>\r\n                        </td>\r\n                    </tr>\r\n                </table>\r\n            </td>\r\n        </tr>\r\n    </table>\r\n</body>\r\n</html>',1,'sent',NULL,NULL,'2025-07-25 11:50:52',NULL,'2025-07-25 12:50:52');
/*!40000 ALTER TABLE `email_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventi`
--

DROP TABLE IF EXISTS `eventi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventi` (
  `id` int(11) NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime DEFAULT NULL,
  `luogo` varchar(255) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT 'riunione',
  `stato` enum('programmato','in_corso','completato','annullato') DEFAULT 'programmato',
  `azienda_id` int(11) DEFAULT NULL,
  `creata_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificata_da` int(11) DEFAULT NULL,
  `data_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creato_da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventi`
--

LOCK TABLES `eventi` WRITE;
/*!40000 ALTER TABLE `eventi` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evento_partecipanti`
--

DROP TABLE IF EXISTS `evento_partecipanti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evento_partecipanti` (
  `id` int(11) NOT NULL,
  `evento_id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `stato` enum('invitato','confermato','rifiutato','forse') DEFAULT 'invitato',
  `notifica_inviata` tinyint(1) DEFAULT 0,
  `data_invito` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evento_partecipanti`
--

LOCK TABLES `evento_partecipanti` WRITE;
/*!40000 ALTER TABLE `evento_partecipanti` DISABLE KEYS */;
/*!40000 ALTER TABLE `evento_partecipanti` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_access_logs`
--

DROP TABLE IF EXISTS `file_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_access_logs` (
  `id` int(11) NOT NULL,
  `documento_id` int(11) NOT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `azione` enum('view','download','share','delete','modify') NOT NULL,
  `dettagli` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli`)),
  `data_accesso` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_access_logs`
--

LOCK TABLES `file_access_logs` WRITE;
/*!40000 ALTER TABLE `file_access_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_uploads`
--

DROP TABLE IF EXISTS `file_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_uploads` (
  `id` int(11) NOT NULL,
  `temp_filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `hash_file` varchar(64) DEFAULT NULL,
  `upload_session` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `azienda_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_uploads`
--

LOCK TABLES `file_uploads` WRITE;
/*!40000 ALTER TABLE `file_uploads` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_uploads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `filesystem_logs`
--

DROP TABLE IF EXISTS `filesystem_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `filesystem_logs` (
  `id` int(11) NOT NULL,
  `azione` varchar(50) NOT NULL,
  `tipo_elemento` varchar(50) NOT NULL,
  `elemento_id` int(11) NOT NULL,
  `dettagli` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli`)),
  `utente_id` int(11) DEFAULT NULL,
  `azienda_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `data_azione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `filesystem_logs`
--

LOCK TABLES `filesystem_logs` WRITE;
/*!40000 ALTER TABLE `filesystem_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `filesystem_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `folder_permissions`
--

DROP TABLE IF EXISTS `folder_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folder_permissions` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `permission_type` enum('read','write','delete','share') NOT NULL,
  `granted_by` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `azienda_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `folder_permissions`
--

LOCK TABLES `folder_permissions` WRITE;
/*!40000 ALTER TABLE `folder_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `folder_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `folder_templates`
--

DROP TABLE IF EXISTS `folder_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folder_templates` (
  `id` int(11) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `struttura` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`struttura`)),
  `iso_standard` varchar(50) DEFAULT NULL,
  `categoria` enum('iso','custom','system') DEFAULT 'custom',
  `attivo` tinyint(1) DEFAULT 1,
  `azienda_id` int(11) DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `folder_templates`
--

LOCK TABLES `folder_templates` WRITE;
/*!40000 ALTER TABLE `folder_templates` DISABLE KEYS */;
INSERT INTO `folder_templates` VALUES (1,'ISO 9001:2015','Sistema di Gestione Qualità','[\"4_Contesto_Organizzazione\",\"5_Leadership\",\"6_Pianificazione\",\"7_Supporto\",\"8_Attivit\\u00e0_Operative\",\"9_Valutazione_Prestazioni\",\"10_Miglioramento\"]','ISO9001','iso',1,NULL,NULL,'2025-07-27 16:28:56'),(2,'ISO 14001:2015','Sistema di Gestione Ambientale','[\"Politica_Ambientale\",\"Aspetti_Ambientali\",\"Obblighi_Conformit\\u00e0\",\"Obiettivi_Ambientali\",\"Controllo_Operativo\",\"Emergenze_Ambientali\",\"Monitoraggio_Misurazione\"]','ISO14001','iso',1,NULL,NULL,'2025-07-27 16:28:56'),(3,'GDPR','Conformità GDPR','[\"Registro_Trattamenti\",\"Privacy_Policy\",\"Informative\",\"Consensi\",\"Data_Breach\",\"DPIA\",\"Formazione\"]','GDPR','iso',1,NULL,NULL,'2025-07-27 16:28:56'),(0,'ISO 9001 Standard','Struttura cartelle standard per ISO 9001','[\"Manuale_Sistema\", \"Politiche\", \"Procedure\", \"Moduli_Registrazioni\", \"Audit\", \"Non_Conformità\", \"Azioni_Miglioramento\", \"Riesame_Direzione\", \"Formazione\", \"Gestione_Fornitori\", \"Indicatori_KPI\"]','ISO9001','iso',1,NULL,NULL,'2025-08-06 09:10:20'),(0,'ISO 14001 Standard','Struttura cartelle standard per ISO 14001','[\"Manuale_Ambientale\", \"Politica_Ambientale\", \"Procedure_Ambientali\", \"Registri_Ambientali\", \"Audit_Ambientali\", \"Non_Conformità_Ambientali\", \"Obiettivi_Ambientali\", \"Riesame_Ambientale\", \"Formazione_Ambientale\"]','ISO14001','iso',1,NULL,NULL,'2025-08-06 09:10:20'),(0,'ISO 45001 Standard','Struttura cartelle standard per ISO 45001','[\"Manuale_Sicurezza\", \"Politica_Sicurezza\", \"Procedure_Sicurezza\", \"Registri_Sicurezza\", \"Audit_Sicurezza\", \"Incidenti_Infortuni\", \"Valutazione_Rischi\", \"Formazione_Sicurezza\", \"DPI_Attrezzature\"]','ISO45001','iso',1,NULL,NULL,'2025-08-06 09:10:20'),(0,'GDPR Compliance','Struttura cartelle standard per conformità GDPR','[\"Registro_Trattamenti\", \"Privacy_Policy\", \"Informative_Privacy\", \"Consensi\", \"Data_Breach\", \"Valutazioni_Impatto\", \"Contratti_Fornitori\", \"Formazione_Privacy\", \"Audit_Privacy\"]','GDPR','iso',1,NULL,NULL,'2025-08-06 09:10:20');
/*!40000 ALTER TABLE `folder_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gdpr_consent`
--

DROP TABLE IF EXISTS `gdpr_consent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gdpr_consent` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `tipo_consenso` varchar(100) NOT NULL,
  `consenso_dato` tinyint(1) NOT NULL,
  `data_consenso` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `dettagli` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gdpr_consent`
--

LOCK TABLES `gdpr_consent` WRITE;
/*!40000 ALTER TABLE `gdpr_consent` DISABLE KEYS */;
/*!40000 ALTER TABLE `gdpr_consent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `impostazioni_iso_azienda`
--

DROP TABLE IF EXISTS `impostazioni_iso_azienda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `impostazioni_iso_azienda` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `modalita` enum('integrato','separato') DEFAULT 'integrato',
  `iso_9001_attivo` tinyint(1) DEFAULT 0,
  `iso_14001_attivo` tinyint(1) DEFAULT 0,
  `iso_45001_attivo` tinyint(1) DEFAULT 0,
  `iso_27001_attivo` tinyint(1) DEFAULT 0,
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `modificato_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `impostazioni_iso_azienda`
--

LOCK TABLES `impostazioni_iso_azienda` WRITE;
/*!40000 ALTER TABLE `impostazioni_iso_azienda` DISABLE KEYS */;
INSERT INTO `impostazioni_iso_azienda` VALUES (1,4,'integrato',0,0,0,1,'2025-07-27 12:00:31','2025-07-27 12:01:09');
/*!40000 ALTER TABLE `impostazioni_iso_azienda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_blacklist`
--

DROP TABLE IF EXISTS `ip_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_blacklist` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` text DEFAULT NULL,
  `blocked_until` timestamp NULL DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_blacklist`
--

LOCK TABLES `ip_blacklist` WRITE;
/*!40000 ALTER TABLE `ip_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_whitelist`
--

DROP TABLE IF EXISTS `ip_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_whitelist` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_whitelist`
--

LOCK TABLES `ip_whitelist` WRITE;
/*!40000 ALTER TABLE `ip_whitelist` DISABLE KEYS */;
/*!40000 ALTER TABLE `ip_whitelist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_company_configurations`
--

DROP TABLE IF EXISTS `iso_company_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iso_company_configurations` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `standard_type` varchar(50) NOT NULL,
  `nome_standard` varchar(100) NOT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `configurazione` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configurazione`)),
  `data_attivazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultima_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modificato_da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_company_configurations`
--

LOCK TABLES `iso_company_configurations` WRITE;
/*!40000 ALTER TABLE `iso_company_configurations` DISABLE KEYS */;
/*!40000 ALTER TABLE `iso_company_configurations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_compliance_check`
--

DROP TABLE IF EXISTS `iso_compliance_check`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iso_compliance_check` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `standard_codice` varchar(50) NOT NULL,
  `tipo_verifica` enum('automatica','manuale','programmata') DEFAULT 'automatica',
  `stato_conformita` enum('conforme','non_conforme','parziale','da_verificare') DEFAULT 'da_verificare',
  `punteggio_conformita` decimal(5,2) DEFAULT 0.00,
  `dettagli_verifica` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli_verifica`)),
  `raccomandazioni` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raccomandazioni`)),
  `data_verifica` timestamp NOT NULL DEFAULT current_timestamp(),
  `verificato_da` int(11) DEFAULT NULL,
  `prossima_verifica` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_compliance_check`
--

LOCK TABLES `iso_compliance_check` WRITE;
/*!40000 ALTER TABLE `iso_compliance_check` DISABLE KEYS */;
/*!40000 ALTER TABLE `iso_compliance_check` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_deployment_log`
--

DROP TABLE IF EXISTS `iso_deployment_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iso_deployment_log` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `operazione` varchar(100) NOT NULL,
  `standard_coinvolti` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`standard_coinvolti`)),
  `dettagli_operazione` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli_operazione`)),
  `risultato` enum('successo','fallito','parziale') NOT NULL,
  `tempo_esecuzione_secondi` decimal(8,3) DEFAULT NULL,
  `data_esecuzione` timestamp NOT NULL DEFAULT current_timestamp(),
  `eseguito_da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_deployment_log`
--

LOCK TABLES `iso_deployment_log` WRITE;
/*!40000 ALTER TABLE `iso_deployment_log` DISABLE KEYS */;
INSERT INTO `iso_deployment_log` VALUES (1,4,'creazione_iniziale','[\"ISO9001\"]','{\"error\":\"SQLSTATE[HY093]: Invalid parameter number: mixed named and positional parameters\",\"structure_type\":\"separata\"}','fallito',0.005,'2025-07-27 18:29:26',2),(2,4,'creazione_iniziale','[\"ISO9001\"]','{\"error\":\"SQLSTATE[HY093]: Invalid parameter number: mixed named and positional parameters\",\"structure_type\":\"separata\"}','fallito',0.002,'2025-07-27 18:29:34',2);
/*!40000 ALTER TABLE `iso_deployment_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_documents`
--

DROP TABLE IF EXISTS `iso_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iso_documents` (
  `id` int(11) NOT NULL,
  `documento_id` int(11) NOT NULL,
  `iso_standard_codice` varchar(20) DEFAULT NULL,
  `tipo_documento_iso` varchar(50) DEFAULT NULL,
  `numero_revisione` varchar(20) DEFAULT NULL,
  `data_approvazione` date DEFAULT NULL,
  `approvato_da` int(11) DEFAULT NULL,
  `data_prossima_revisione` date DEFAULT NULL,
  `stato_revisione` enum('in_redazione','in_revisione','approvato','obsoleto') DEFAULT 'in_redazione',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_documents`
--

LOCK TABLES `iso_documents` WRITE;
/*!40000 ALTER TABLE `iso_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `iso_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_folder_templates`
--

DROP TABLE IF EXISTS `iso_folder_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iso_folder_templates` (
  `id` int(11) NOT NULL,
  `standard_id` int(11) NOT NULL,
  `parent_template_id` int(11) DEFAULT NULL,
  `codice` varchar(50) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `livello` int(11) DEFAULT 1,
  `ordine_visualizzazione` int(11) DEFAULT 0,
  `icona` varchar(50) DEFAULT 'fa-folder',
  `colore` varchar(7) DEFAULT '#fbbf24',
  `obbligatoria` tinyint(1) DEFAULT 0,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_folder_templates`
--

LOCK TABLES `iso_folder_templates` WRITE;
/*!40000 ALTER TABLE `iso_folder_templates` DISABLE KEYS */;
INSERT INTO `iso_folder_templates` VALUES (1,1,NULL,'MANUALE_SISTEMA','Manuale del Sistema','Manuale del Sistema di Gestione Qualità',1,1,'fa-book','#3b82f6',1,'2025-07-27 18:18:32'),(2,1,NULL,'POLITICHE','Politiche','Politiche aziendali per la qualità',1,2,'fa-file-alt','#10b981',1,'2025-07-27 18:18:32'),(3,1,NULL,'PROCEDURE','Procedure','Procedure operative standard',1,3,'fa-tasks','#f59e0b',1,'2025-07-27 18:18:32'),(4,1,NULL,'MODULI_REGISTRAZIONI','Moduli e Registrazioni','Moduli e registrazioni del sistema',1,4,'fa-clipboard','#8b5cf6',1,'2025-07-27 18:18:32'),(5,1,NULL,'AUDIT','Audit','Audit interni e verifiche',1,5,'fa-search','#ef4444',1,'2025-07-27 18:18:32'),(6,1,NULL,'NON_CONFORMITA','Non Conformità','Gestione delle non conformità',1,6,'fa-exclamation-triangle','#f97316',1,'2025-07-27 18:18:32'),(7,1,NULL,'AZIONI_MIGLIORAMENTO','Azioni di Miglioramento','Azioni correttive e preventive',1,7,'fa-chart-line','#06b6d4',1,'2025-07-27 18:18:32'),(8,1,NULL,'RIESAME_DIREZIONE','Riesame della Direzione','Riesame del sistema da parte della direzione',1,8,'fa-users-cog','#84cc16',1,'2025-07-27 18:18:32'),(9,1,NULL,'FORMAZIONE','Formazione','Formazione e competenze del personale',1,9,'fa-graduation-cap','#ec4899',1,'2025-07-27 18:18:32'),(10,1,NULL,'GESTIONE_FORNITORI','Gestione Fornitori','Gestione e valutazione dei fornitori',1,10,'fa-truck','#6366f1',1,'2025-07-27 18:18:32'),(11,1,NULL,'INDICATORI_KPI','Indicatori KPI','Indicatori di prestazione e monitoraggio',1,11,'fa-chart-bar','#14b8a6',1,'2025-07-27 18:18:32');
/*!40000 ALTER TABLE `iso_folder_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_standards`
--

DROP TABLE IF EXISTS `iso_standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iso_standards` (
  `id` int(11) NOT NULL,
  `codice` varchar(50) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `versione` varchar(20) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_standards`
--

LOCK TABLES `iso_standards` WRITE;
/*!40000 ALTER TABLE `iso_standards` DISABLE KEYS */;
INSERT INTO `iso_standards` VALUES (1,'ISO9001','ISO 9001:2015','Sistema di Gestione della Qualità','2015',1,'2025-07-27 18:18:32'),(2,'ISO14001','ISO 14001:2015','Sistema di Gestione Ambientale','2015',1,'2025-07-27 18:18:32'),(3,'ISO45001','ISO 45001:2018','Sistema di Gestione della Salute e Sicurezza sul Lavoro','2018',1,'2025-07-27 18:18:32'),(4,'GDPR','GDPR 2016/679','Regolamento Generale sulla Protezione dei Dati','2016/679',1,'2025-07-27 18:18:32'),(8,'ISO27001','ISO 27001:2013','Sistema di Gestione Sicurezza delle Informazioni','2013',1,'2025-07-28 05:00:10'),(10,'SGI','SGI','Sistema di Gestione Integrato',NULL,1,'2025-07-28 05:00:10'),(11,'CUSTOM','Personalizzato','Standard personalizzato',NULL,1,'2025-07-28 05:00:10');
/*!40000 ALTER TABLE `iso_standards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_attivita`
--

DROP TABLE IF EXISTS `log_attivita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_attivita` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT 'general',
  `descrizione` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `data_azione` timestamp NOT NULL DEFAULT current_timestamp(),
  `entita_tipo` varchar(50) DEFAULT NULL,
  `entita_id` int(11) DEFAULT NULL,
  `azione` varchar(50) DEFAULT NULL,
  `dettagli` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli`)),
  `non_eliminabile` tinyint(1) DEFAULT 0,
  `azienda_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_attivita`
--

LOCK TABLES `log_attivita` WRITE;
/*!40000 ALTER TABLE `log_attivita` DISABLE KEYS */;
INSERT INTO `log_attivita` VALUES (39,NULL,'eliminazione_log','Eliminazione_log sistema','127.0.0.1',NULL,'2025-07-23 08:33:33','sistema',NULL,'eliminazione_log','{\"messaggio\":\"Eliminati TUTTI i log di sistema (esclusi log di eliminazione). Totale record eliminati: 35\"}',1,NULL),(40,NULL,'eliminazione','Eliminazione utente','127.0.0.1',NULL,'2025-07-23 08:45:56','utente',6,'eliminazione','{\"messaggio\":\"Eliminato utente: Antonio Silverstro Amodeo (a.oedoma@gmail.com)\"}',0,NULL),(41,NULL,'creazione','Creazione utente','127.0.0.1',NULL,'2025-07-23 08:48:27','utente',7,'creazione','{\"messaggio\":\"Nuovo utente: a.oedoma@gmail.com\"}',0,NULL),(42,NULL,'eliminazione','Eliminazione utente','127.0.0.1',NULL,'2025-07-23 08:49:05','utente',7,'eliminazione','{\"messaggio\":\"Eliminato utente: Antonio Silverstro Amodeo (a.oedoma@gmail.com)\"}',0,NULL),(43,NULL,'creazione','Creazione utente','127.0.0.1',NULL,'2025-07-23 09:05:06','utente',14,'creazione','{\"messaggio\":\"Nuovo utente: a.oedoma@gmail.com\"}',0,NULL),(44,NULL,'general',NULL,NULL,NULL,'2025-07-23 09:05:07','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Benvenuto in Nexio Solution\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(45,NULL,'general',NULL,NULL,NULL,'2025-07-23 09:06:24','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Password Modificata\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(46,NULL,'creazione','Creazione utente','127.0.0.1',NULL,'2025-07-23 09:28:00','utente',15,'creazione','{\"messaggio\":\"Nuovo utente: francescobarreca@scosolution.it\"}',0,NULL),(47,NULL,'general',NULL,NULL,NULL,'2025-07-23 09:28:01','email',NULL,'email_sent','{\"to\":\"francescobarreca@scosolution.it\",\"subject\":\"Benvenuto in Nexio Solution\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(48,NULL,'reset_password','Reset_password utente','127.0.0.1',NULL,'2025-07-23 11:44:32','utente',15,'reset_password','{\"messaggio\":\"Password resettata da super admin\"}',0,NULL),(49,NULL,'general',NULL,NULL,NULL,'2025-07-23 11:44:33','email',NULL,'email_sent','{\"to\":\"francescobarreca@scosolution.it\",\"subject\":\"Password Reimpostata - Nexio\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(50,NULL,'general',NULL,NULL,NULL,'2025-07-23 11:46:15','email',NULL,'email_sent','{\"to\":\"francescobarreca@scosolution.it\",\"subject\":\"Password Modificata\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(51,2,'eliminazione','Eliminazione utente','127.0.0.1',NULL,'2025-07-23 13:26:51','utente',14,'eliminazione','{\"messaggio\":\"Eliminato utente: Antonio Silverstro Amodeo (a.oedoma@gmail.com)\"}',0,NULL),(52,NULL,'general',NULL,NULL,NULL,'2025-07-23 13:46:01','email',NULL,'email_sent','{\"to\":\"asamodeo@fortibyte.it\",\"subject\":\"Task Cancellato: Office - Remoto\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(53,2,'eliminazione','Eliminazione utente','127.0.0.1',NULL,'2025-07-23 13:47:10','utente',1,'eliminazione','{\"messaggio\":\"Eliminato utente: Admin Sistema (admin@nexiosolution.it)\"}',0,NULL),(54,NULL,'general',NULL,NULL,NULL,'2025-07-23 14:10:37','email',NULL,'email_sent','{\"to\":\"asamodeo@fortibyte.it\",\"subject\":\"Invito: prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(55,2,'creazione','Creazione utente','127.0.0.1',NULL,'2025-07-23 14:12:54','utente',16,'creazione','{\"messaggio\":\"Nuovo utente: a.oedoma@gmail.com\"}',0,NULL),(56,NULL,'general',NULL,NULL,NULL,'2025-07-23 14:12:55','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Benvenuto in Nexio Solution\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(57,NULL,'general',NULL,NULL,NULL,'2025-07-23 14:14:44','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Invito: test 2\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(58,NULL,'general',NULL,NULL,NULL,'2025-07-23 14:15:42','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Invito: Prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(59,NULL,'general',NULL,NULL,NULL,'2025-07-23 14:28:03','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Invito: Prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(60,2,'eliminazione','Eliminazione cartella','127.0.0.1',NULL,'2025-07-24 07:19:24','cartella',71,'eliminazione','{\"nome\":\"Documenti_Obsoleti\"}',0,NULL),(61,2,'eliminazione_schema','Eliminazione_schema sistema','127.0.0.1',NULL,'2025-07-24 07:22:52','sistema',9,'eliminazione_schema','{\"nome\":\"SISTEMA_GESTIONE_CONFORMITA\",\"azienda_id\":1}',0,NULL),(62,2,'eliminazione_root','Eliminazione_root cartella','127.0.0.1',NULL,'2025-07-24 07:26:26','cartella',1,'eliminazione_root','{\"nome\":\"Documenti\",\"azienda_id\":1}',0,NULL),(63,2,'eliminazione_root','Eliminazione_root cartella','127.0.0.1',NULL,'2025-07-24 07:27:14','cartella',74,'eliminazione_root','{\"nome\":\"SISTEMA_GESTIONE_CONFORMITA\",\"azienda_id\":2}',0,NULL),(64,NULL,'general',NULL,NULL,NULL,'2025-07-24 11:12:44','email',NULL,'email_sent','{\"to\":\"asamodeo@fortibyte.it\",\"subject\":\"Nuovo Task Assegnato: Consulenza - prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(65,NULL,'general',NULL,NULL,NULL,'2025-07-24 11:13:02','email',NULL,'email_sent','{\"to\":\"asamodeo@fortibyte.it\",\"subject\":\"Evento Cancellato: prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(66,NULL,'general',NULL,NULL,NULL,'2025-07-24 11:13:10','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Evento Cancellato: test 2\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(67,NULL,'general',NULL,NULL,NULL,'2025-07-24 11:13:16','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Evento Cancellato: Prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(68,NULL,'general',NULL,NULL,NULL,'2025-07-24 11:13:23','email',NULL,'email_sent','{\"to\":\"a.oedoma@gmail.com\",\"subject\":\"Evento Cancellato: Prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(69,2,'creazione','Creazione utente','::1',NULL,'2025-07-25 10:49:54','utente',17,'creazione','{\"messaggio\":\"Nuovo utente: arearicerca@romolohospital.com\"}',0,NULL),(70,NULL,'general',NULL,NULL,NULL,'2025-07-25 10:49:55','email',NULL,'email_sent','{\"to\":\"arearicerca@romolohospital.com\",\"subject\":\"Benvenuto in Nexio Solution\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(71,2,'reset_password','Reset_password utente','::1',NULL,'2025-07-25 10:52:38','utente',17,'reset_password','{\"messaggio\":\"Password resettata da super admin\"}',0,NULL),(72,NULL,'general',NULL,NULL,NULL,'2025-07-25 10:52:39','email',NULL,'email_sent','{\"to\":\"arearicerca@romolohospital.com\",\"subject\":\"Password Reimpostata - Nexio\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(73,17,'Password cambiata dall\'utente','Password cambiata dall\'utente password_cambiata','127.0.0.1',NULL,'2025-07-25 10:53:38','password_cambiata',0,'Password cambiata dall\'utente','{\"messaggio\":\"\"}',0,NULL),(74,NULL,'general',NULL,NULL,NULL,'2025-07-25 10:53:39','email',NULL,'email_sent','{\"to\":\"arearicerca@romolohospital.com\",\"subject\":\"Password Modificata\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(75,17,'Password temporanea generata per recupero','Password temporanea generata per recupero password_reset','127.0.0.1',NULL,'2025-07-25 12:35:57','password_reset',NULL,'Password temporanea generata per recupero','{\"user_id\":17,\"email\":\"arearicerca@romolohospital.com\"}',0,4),(76,NULL,'general',NULL,NULL,NULL,'2025-07-25 12:35:58','email',NULL,'email_sent','{\"to\":\"arearicerca@romolohospital.com\",\"subject\":\"Nexio - Password Temporanea\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(77,NULL,'Password temporanea generata per recupero','Password temporanea generata per recupero password_reset','127.0.0.1',NULL,'2025-07-25 13:01:09','password_reset',NULL,'Password temporanea generata per recupero','{\"user_id\":17,\"email\":\"arearicerca@romolohospital.com\"}',0,NULL),(78,NULL,'general',NULL,NULL,NULL,'2025-07-25 13:01:10','email',NULL,'email_sent','{\"to\":\"arearicerca@romolohospital.com\",\"subject\":\"Nexio - Password Temporanea\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(79,2,'modifica','Modifica utente','::1',NULL,'2025-07-26 04:20:58','utente',17,'modifica','{\"messaggio\":\"Utente modificato: qualita@romolohospital.com\"}',0,NULL),(80,2,'modifica','Modifica utente','::1',NULL,'2025-07-26 04:21:14','utente',17,'modifica','{\"messaggio\":\"Utente modificato: arearicerca@romolohospital.com\"}',0,NULL),(81,2,'modifica','Modifica utente','::1',NULL,'2025-07-26 04:33:02','utente',17,'modifica','{\"messaggio\":\"Utente modificato: qualita@romolohospital.com\"}',0,NULL),(82,2,'modifica','Modifica utente','::1',NULL,'2025-07-26 04:35:29','utente',17,'modifica','{\"messaggio\":\"Utente modificato: arearicerca@romolohospital.com\"}',0,NULL),(83,2,'eliminazione','Eliminazione utente','::1',NULL,'2025-07-26 05:54:08','utente',16,'eliminazione','{\"messaggio\":\"Eliminato utente: Pippo Baudo (a.oedoma@gmail.com)\"}',0,NULL),(84,2,'modifica','Modifica utente','::1',NULL,'2025-07-26 06:06:08','utente',17,'modifica','{\"messaggio\":\"Utente modificato: qualita@romolohospital.com\"}',0,NULL),(85,NULL,'general',NULL,NULL,NULL,'2025-07-26 06:38:33','email',NULL,'email_sent','{\"to\":\"asamodeo@fortibyte.it\",\"subject\":\"Task Cancellato: Consulenza - prova\",\"status\":\"success\",\"error\":\"BrevoSMTP\"}',0,NULL),(86,2,'aggiornamento_moduli','Aggiornamento_moduli azienda','::1',NULL,'2025-07-27 06:46:14','azienda',5,'aggiornamento_moduli','{\"aggiunti\":1,\"rimossi\":0}',0,NULL),(87,NULL,'sistema','Sistema filesystem_fix_eseguito','127.0.0.1',NULL,'2025-07-27 16:28:56','filesystem_fix_eseguito',NULL,'sistema','{\"operazioni\":38,\"successi\":26,\"skip\":12,\"percentuale\":68}',0,NULL),(88,2,'sistema','Sistema installazione_sistema_iso','::1',NULL,'2025-07-28 05:00:10','installazione_sistema_iso',0,'sistema','{\"script\":\"INSTALL-FILESYSTEM-DEFINITIVO.php\",\"timestamp\":\"2025-07-28 07:00:10\",\"standards_installed\":7,\"templates_installed\":11}',0,NULL),(89,2,'sistema','Sistema sistema_fix_definitivo','::1',NULL,'2025-07-28 05:00:16','sistema_fix_definitivo',0,'sistema','{\"script\":\"fix-filesystem-definitivo.php\",\"timestamp\":\"2025-07-28 07:00:16\"}',0,NULL),(90,2,'sistema','Sistema sistema_fix_definitivo','::1',NULL,'2025-07-28 05:00:30','sistema_fix_definitivo',0,'sistema','{\"script\":\"fix-filesystem-definitivo.php\",\"timestamp\":\"2025-07-28 07:00:30\"}',0,NULL),(91,NULL,'test_entity','Test_entity test_action','127.0.0.1',NULL,'2025-07-28 15:12:02','test_action',1,'test_entity','{\"test\":true}',0,NULL),(92,NULL,'test_entity','Test_entity test_action','127.0.0.1',NULL,'2025-07-28 15:12:26','test_action',1,'test_entity','{\"test\":true}',0,NULL),(93,2,'system','System database_fix','::1',NULL,'2025-07-29 16:49:02','database_fix',NULL,'system','{\"action\":\"create_table\",\"table\":\"iso_company_configurations\",\"script\":\"run-iso-fix.php\"}',0,NULL),(95,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:00','cartella_eliminata',164,'cartelle','{\"nome\":\"Documenti Personali\",\"percorso\":\"Documenti Personali\"}',0,NULL),(96,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:03','cartella_eliminata',163,'cartelle','{\"nome\":\"Test API 16:52:03\",\"percorso\":\"Test API 16:52:03\"}',0,NULL),(97,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:04','cartella_eliminata',161,'cartelle','{\"nome\":\"Test Finale 2025-07-28 16:52:03\",\"percorso\":\"Test Finale 2025-07-28 16:52:03\"}',0,NULL),(98,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:06','cartella_eliminata',160,'cartelle','{\"nome\":\"Test Gestionale 16:48:05\",\"percorso\":\"Test Gestionale 16:48:05\"}',0,NULL),(99,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:08','cartella_eliminata',170,'cartelle','{\"nome\":\"Test Personal Folder 1753715342\",\"percorso\":\"Test Personal Folder 1753715342\"}',0,NULL),(100,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:14','cartella_eliminata',172,'cartelle','{\"nome\":\"Test Personal Folder 1753715388\",\"percorso\":\"Test Personal Folder 1753715388\"}',0,NULL),(101,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:17','cartella_eliminata',169,'cartelle','{\"nome\":\"TEST_FOLDER_1753715342\",\"percorso\":\"TEST_FOLDER_1753715342\"}',0,NULL),(102,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:19','cartella_eliminata',171,'cartelle','{\"nome\":\"TEST_FOLDER_1753715388\",\"percorso\":\"TEST_FOLDER_1753715388\"}',0,NULL),(103,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:20','cartella_eliminata',166,'cartelle','{\"nome\":\"TEST_TEMP_1753715084\",\"percorso\":\"TEST_TEMP_1753715084\"}',0,NULL),(104,2,'cartelle','Cartelle cartella_eliminata','::1',NULL,'2025-07-29 21:07:22','cartella_eliminata',167,'cartelle','{\"nome\":\"TEST_UPDATED\",\"percorso\":\"TEST_UPDATED\"}',0,NULL);
/*!40000 ALTER TABLE `log_attivita` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `prevent_log_delete` BEFORE DELETE ON `log_attivita` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deletion from log_attivita is not allowed' */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `moduli_azienda`
--

DROP TABLE IF EXISTS `moduli_azienda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `moduli_azienda` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `modulo_id` int(11) NOT NULL,
  `abilitato` tinyint(1) DEFAULT 1,
  `data_abilitazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `abilitato_da` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moduli_azienda`
--

LOCK TABLES `moduli_azienda` WRITE;
/*!40000 ALTER TABLE `moduli_azienda` DISABLE KEYS */;
INSERT INTO `moduli_azienda` VALUES (7,4,16,1,'2025-07-27 06:30:41',2,NULL),(8,4,3,1,'2025-07-27 06:30:41',2,NULL),(9,4,9,1,'2025-07-27 06:30:41',2,NULL),(10,5,9,1,'2025-07-27 06:46:09',2,NULL),(11,6,3,1,'2025-07-28 04:23:19',2,NULL),(12,6,9,1,'2025-07-28 04:23:19',2,NULL);
/*!40000 ALTER TABLE `moduli_azienda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moduli_documento`
--

DROP TABLE IF EXISTS `moduli_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `moduli_documento` (
  `id` int(11) NOT NULL,
  `documento_id` int(11) NOT NULL,
  `modulo_template_id` int(11) NOT NULL,
  `contenuto_compilato` text DEFAULT NULL,
  `dati_compilati` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dati_compilati`)),
  `ordinamento` int(11) DEFAULT 0,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moduli_documento`
--

LOCK TABLES `moduli_documento` WRITE;
/*!40000 ALTER TABLE `moduli_documento` DISABLE KEYS */;
/*!40000 ALTER TABLE `moduli_documento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moduli_sistema`
--

DROP TABLE IF EXISTS `moduli_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `moduli_sistema` (
  `id` int(11) NOT NULL,
  `codice` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `icona` varchar(50) DEFAULT NULL,
  `url_pagina` varchar(255) DEFAULT NULL,
  `colore` varchar(7) DEFAULT NULL,
  `url_base` varchar(255) DEFAULT NULL,
  `ordine` int(11) DEFAULT 0,
  `attivo` tinyint(1) DEFAULT 1,
  `richiede_licenza` tinyint(1) DEFAULT 1,
  `configurazione` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configurazione`)),
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moduli_sistema`
--

LOCK TABLES `moduli_sistema` WRITE;
/*!40000 ALTER TABLE `moduli_sistema` DISABLE KEYS */;
INSERT INTO `moduli_sistema` VALUES (1,'EVENTI','Gestione Eventi','Calendario eventi e gestione partecipanti','fas fa-calendar-alt',NULL,'#0066cc','/calendario-eventi.php',1,1,1,NULL,'2025-07-22 13:36:56','2025-07-22 13:36:56'),(2,'FILESYSTEM','File Manager','Gestione documenti e file aziendali','fas fa-folder-open',NULL,'#f59e0b','/filesystem.php',2,1,1,NULL,'2025-07-22 13:36:56','2025-07-22 13:36:56'),(3,'TICKETS','Sistema Ticket','Gestione ticket e supporto','fas fa-ticket-alt',NULL,'#10b981','/tickets.php',3,1,1,NULL,'2025-07-22 13:36:56','2025-07-22 13:36:56'),(4,'CONFORMITA','Conformità Normativa','Gestione certificazioni ISO e autorizzazioni','fas fa-certificate',NULL,'#8b5cf6','/conformita.php',4,1,1,NULL,'2025-07-22 13:36:56','2025-07-22 13:36:56'),(5,'DASHBOARD','Dashboard','Pannello di controllo principale con statistiche e quick actions','fas fa-tachometer-alt',NULL,'#3b82f6','dashboard.php',1,1,0,NULL,'2025-07-22 14:58:08','2025-07-22 14:58:08'),(7,'TEMPLATE','Template Documenti','Editor template drag-and-drop per creazione documenti dinamici','fas fa-file-code',NULL,'#8b5cf6','template.php',3,1,1,NULL,'2025-07-22 14:58:08','2025-07-22 14:58:08'),(8,'ARCHIVIO','Archivio Documenti','Archivio documenti con sistema di classificazione avanzato','fas fa-archive',NULL,'#f59e0b','archivio-documenti.php',4,1,1,NULL,'2025-07-22 14:58:08','2025-07-22 14:58:08'),(9,'CALENDARIO','Calendario Eventi','Sistema calendario con inviti e notifiche email integrate','fas fa-calendar-alt',NULL,'#ef4444','calendario-eventi.php',5,1,1,NULL,'2025-07-22 14:58:08','2025-07-22 14:58:08'),(10,'UTENTI','Gestione Utenti','Amministrazione utenti, ruoli e permessi multi-tenant','fas fa-users',NULL,'#6366f1','gestione-utenti.php',7,1,0,NULL,'2025-07-22 14:58:18','2025-07-22 14:58:18'),(11,'AZIENDE','Gestione Aziende','Amministrazione aziende e configurazione moduli (solo super admin)','fas fa-building',NULL,'#84cc16','aziende.php',8,1,0,NULL,'2025-07-22 14:58:18','2025-07-22 14:58:18'),(12,'REFERENTI','Gestione Referenti','Database contatti e referenti aziendali','fas fa-address-book',NULL,'#f97316','referenti.php',9,1,1,NULL,'2025-07-22 14:58:18','2025-07-22 14:58:18'),(13,'NEWSLETTER','Newsletter','Sistema invio newsletter e gestione campagne email','fas fa-envelope',NULL,'#ec4899','newsletter.php',10,1,1,NULL,'2025-07-22 14:58:18','2025-07-22 14:58:18'),(14,'CONFIGURAZIONI','Configurazioni','Impostazioni sistema e configurazioni avanzate','fas fa-cog',NULL,'#71717a','configurazioni.php',12,1,0,NULL,'2025-07-22 14:58:18','2025-07-22 14:58:18'),(15,'ONLYOFFICE','OnlyOffice Editor','Editor documenti collaborativo in tempo reale','fas fa-edit',NULL,'#059669','nuovo-documento-onlyoffice.php',13,1,1,NULL,'2025-07-22 14:58:18','2025-07-22 14:58:18'),(16,'conformita_normativa','Conformità Normativa','Sistema per la gestione della conformità normativa e requisiti legali','fas fa-clipboard-list',NULL,NULL,NULL,3,1,1,NULL,'2025-07-25 09:32:54','2025-07-25 09:32:54'),(17,'nexio_ai','Nexio AI','Assistente AI integrato per analisi documenti e automazione processi','fas fa-robot','nexio-ai.php',NULL,NULL,10,1,1,NULL,'2025-07-26 04:37:06','2025-07-26 04:37:06'),(18,'filesystem_advanced','Filesystem Avanzato','Funzionalità avanzate filesystem',NULL,'filesystem.php?advanced=1',NULL,NULL,0,1,1,NULL,'2025-07-27 16:28:56','2025-07-27 16:28:56'),(19,'iso_structures','Strutture ISO','Gestione strutture documentali ISO',NULL,'gestione-struttura-iso.php',NULL,NULL,0,1,1,NULL,'2025-07-27 16:28:56','2025-07-27 16:28:56'),(20,'gdpr_compliance','Conformità GDPR','Gestione conformità GDPR',NULL,'gdpr-compliance.php',NULL,NULL,0,1,1,NULL,'2025-07-27 16:28:56','2025-07-27 16:28:56'),(21,'document_management','Gestione Documenti','Sistema di gestione documentale',NULL,NULL,NULL,NULL,0,1,1,NULL,'2025-07-28 05:41:48','2025-07-28 05:41:48'),(22,'calendar','Calendario','Calendario eventi e appuntamenti',NULL,NULL,NULL,NULL,0,1,1,NULL,'2025-07-28 05:41:48','2025-07-28 05:41:48'),(23,'email_notifications','Notifiche Email','Sistema di notifiche via email',NULL,NULL,NULL,NULL,0,1,1,NULL,'2025-07-28 05:41:48','2025-07-28 05:41:48'),(24,'iso_compliance','Conformità ISO','Gestione conformità ISO 9001/14001/45001',NULL,NULL,NULL,NULL,0,1,1,NULL,'2025-07-28 05:41:48','2025-07-28 05:41:48'),(25,'advanced_editor','Editor Avanzato','Editor documenti avanzato',NULL,NULL,NULL,NULL,0,1,1,NULL,'2025-07-28 05:41:48','2025-07-28 05:41:48');
/*!40000 ALTER TABLE `moduli_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moduli_template`
--

DROP TABLE IF EXISTS `moduli_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `moduli_template` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `contenuto_html` text DEFAULT NULL,
  `configurazione` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configurazione`)),
  `categoria` varchar(50) DEFAULT NULL,
  `icona` varchar(100) DEFAULT NULL,
  `ordinamento` int(11) DEFAULT 0,
  `attivo` tinyint(1) DEFAULT 1,
  `azienda_id` int(11) DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `codice` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moduli_template`
--

LOCK TABLES `moduli_template` WRITE;
/*!40000 ALTER TABLE `moduli_template` DISABLE KEYS */;
INSERT INTO `moduli_template` VALUES (1,'Intestazione','header','<div class=\"header\"><h1>{titolo}</h1></div>',NULL,'layout',NULL,0,1,NULL,NULL,'2025-07-22 05:18:25',NULL,'2025-07-22 07:17:48','MOD-001'),(2,'Paragrafo','paragraph','<p>{contenuto}</p>',NULL,'content',NULL,0,1,NULL,NULL,'2025-07-22 05:18:25',NULL,'2025-07-22 07:17:48','MOD-002'),(3,'Tabella','table','<table class=\"table\">{righe}</table>',NULL,'content',NULL,0,1,NULL,NULL,'2025-07-22 05:18:25',NULL,'2025-07-22 07:17:48','MOD-003'),(4,'Testo Semplice','text','<div class=\"text-module\">{content}</div>',NULL,'content',NULL,1,1,NULL,NULL,'2025-07-22 07:17:48',NULL,'2025-07-22 07:17:48','MOD-TEXT'),(5,'Immagine','image','<div class=\"image-module\"><img src=\"{src}\" alt=\"{alt}\"></div>',NULL,'media',NULL,2,1,NULL,NULL,'2025-07-22 07:17:48',NULL,'2025-07-22 07:17:48','MOD-IMG'),(6,'Tabella Dati','table','<div class=\"table-module\">{table_content}</div>',NULL,'data',NULL,3,1,NULL,NULL,'2025-07-22 07:17:48',NULL,'2025-07-22 07:17:48','MOD-TABLE'),(7,'Firma Digitale','signature','<div class=\"signature-module\">{signature_field}</div>',NULL,'interactive',NULL,4,1,NULL,NULL,'2025-07-22 07:17:48',NULL,'2025-07-22 07:17:48','MOD-SIGN'),(8,'Lista Puntata','list','<div class=\"list-module\"><ul>{list_items}</ul></div>',NULL,'content',NULL,5,1,NULL,NULL,'2025-07-22 07:17:48',NULL,'2025-07-22 07:17:48','MOD-LIST');
/*!40000 ALTER TABLE `moduli_template` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter`
--

DROP TABLE IF EXISTS `newsletter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `cognome` varchar(100) DEFAULT NULL,
  `azienda` varchar(255) DEFAULT NULL,
  `consenso` tinyint(1) DEFAULT 1,
  `data_iscrizione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_cancellazione` datetime DEFAULT NULL,
  `token_conferma` varchar(64) DEFAULT NULL,
  `confermato` tinyint(1) DEFAULT 0,
  `data_conferma` datetime DEFAULT NULL,
  `ip_iscrizione` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter`
--

LOCK TABLES `newsletter` WRITE;
/*!40000 ALTER TABLE `newsletter` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifiche`
--

DROP TABLE IF EXISTS `notifiche`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifiche` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `messaggio` text DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `letta` tinyint(1) DEFAULT 0,
  `creata_il` timestamp NOT NULL DEFAULT current_timestamp(),
  `letta_il` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifiche`
--

LOCK TABLES `notifiche` WRITE;
/*!40000 ALTER TABLE `notifiche` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifiche` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifiche_email`
--

DROP TABLE IF EXISTS `notifiche_email`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifiche_email` (
  `id` int(11) NOT NULL,
  `destinatario` varchar(255) NOT NULL,
  `oggetto` varchar(255) NOT NULL,
  `contenuto` text DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `stato` enum('in_coda','inviata','fallita') DEFAULT 'in_coda',
  `tentativi` int(11) DEFAULT 0,
  `errore` text DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_invio` datetime DEFAULT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `azienda_id` int(11) DEFAULT NULL,
  `riferimento_tipo` varchar(50) DEFAULT NULL,
  `riferimento_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifiche_email`
--

LOCK TABLES `notifiche_email` WRITE;
/*!40000 ALTER TABLE `notifiche_email` DISABLE KEYS */;
INSERT INTO `notifiche_email` VALUES (1,'asamodeo@fortibyte.it','Nuovo utente creato: Utente Prova','\n                <h3>Nuovo utente creato</h3>\n                <p><strong>Nome:</strong> Utente Prova</p>\n                <p><strong>Email:</strong> arearicerca@romolohospital.com</p>\n                <p><strong>Ruolo:</strong> utente</p>\n                <p><strong>Creato da:</strong> Antonio Silverstro Amodeo</p>\n                ','utente_creato','in_coda',0,NULL,'2025-07-25 10:49:55',NULL,NULL,NULL,NULL,NULL),(2,'francescobarreca@scosolution.it','Nuovo utente creato: Utente Prova','\n                <h3>Nuovo utente creato</h3>\n                <p><strong>Nome:</strong> Utente Prova</p>\n                <p><strong>Email:</strong> arearicerca@romolohospital.com</p>\n                <p><strong>Ruolo:</strong> utente</p>\n                <p><strong>Creato da:</strong> Antonio Silverstro Amodeo</p>\n                ','utente_creato','in_coda',0,NULL,'2025-07-25 10:49:55',NULL,NULL,NULL,NULL,NULL),(3,'asamodeo@fortibyte.it','Utente eliminato: Pippo Baudo','<h3>Utente eliminato dal sistema</h3>\n                <p><strong>Nome:</strong> Pippo Baudo</p>\n                <p><strong>Email:</strong> a.oedoma@gmail.com</p>\n                <p><strong>Ruolo:</strong> utente_speciale</p>\n                <p><strong>Eliminato da:</strong> Antonio Silverstro Amodeo</p>\n                <p><strong>Data:</strong> 26/07/2025 07:54</p>','utente_eliminato','in_coda',0,NULL,'2025-07-26 05:54:08',NULL,NULL,NULL,NULL,NULL),(4,'francescobarreca@scosolution.it','Utente eliminato: Pippo Baudo','<h3>Utente eliminato dal sistema</h3>\n                <p><strong>Nome:</strong> Pippo Baudo</p>\n                <p><strong>Email:</strong> a.oedoma@gmail.com</p>\n                <p><strong>Ruolo:</strong> utente_speciale</p>\n                <p><strong>Eliminato da:</strong> Antonio Silverstro Amodeo</p>\n                <p><strong>Data:</strong> 26/07/2025 07:54</p>','utente_eliminato','in_coda',0,NULL,'2025-07-26 05:54:08',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `notifiche_email` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_history`
--

DROP TABLE IF EXISTS `password_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_history` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `data_cambio` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `motivo` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_history`
--

LOCK TABLES `password_history` WRITE;
/*!40000 ALTER TABLE `password_history` DISABLE KEYS */;
INSERT INTO `password_history` VALUES (1,2,'$2y$10$q3ZPZsX8X.atocgbjqV2L.EPslOrNhe7rIaRW5ITOewPRer2vzpP6','2025-07-22 15:07:28',NULL,NULL,NULL),(3,15,'$2y$10$lbIkd64X1K1KjhJI8Rv25.8wMgt4341yAmwXTbx7zNJC0VccG/yZu','2025-07-23 11:46:15',NULL,NULL,NULL),(4,17,'$2y$10$xSkbYzpFfJbb4N7/9.wa5.gvfFE.1K5..uM6ZOu2G4jS/4PXKWyeC','2025-07-25 10:53:38',NULL,NULL,NULL);
/*!40000 ALTER TABLE `password_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit_attempts`
--

DROP TABLE IF EXISTS `rate_limit_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit_attempts` (
  `id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit_attempts`
--

LOCK TABLES `rate_limit_attempts` WRITE;
/*!40000 ALTER TABLE `rate_limit_attempts` DISABLE KEYS */;
INSERT INTO `rate_limit_attempts` VALUES (1,'login','127.0.0.1','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0',1,'2025-07-22 06:04:33'),(2,'login','127.0.0.1','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0',1,'2025-07-22 06:04:33'),(3,'login','151.44.203.71','151.44.203.71','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-22 11:05:23'),(4,'login','151.44.203.71','151.44.203.71','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-22 11:05:24'),(5,'login','151.44.220.20','151.44.220.20','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-22 16:05:27'),(6,'login','151.44.220.20','151.44.220.20','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-22 16:05:27'),(7,'login','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-23 04:51:27'),(8,'login','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-23 04:51:44'),(9,'login','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-23 04:52:01'),(10,'login','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','2a02:b125:f07:f210:9c9c:ef14:ec48:6e7c','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-23 04:52:01'),(11,'login','151.46.162.172','151.46.162.172','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 09:35:21'),(12,'login','151.46.162.172','151.46.162.172','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 09:35:21'),(13,'login','151.46.162.172','151.46.162.172','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 10:06:02'),(14,'login','151.46.162.172','151.46.162.172','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 10:06:02'),(15,'login','62.18.180.45','62.18.180.45','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',0,'2025-07-23 12:37:21'),(16,'login','62.18.180.45','62.18.180.45','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',0,'2025-07-23 12:39:08'),(17,'login','62.18.32.9','62.18.32.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-23 12:43:04'),(18,'login','62.18.32.9','62.18.32.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-23 12:44:06'),(19,'login','62.18.32.9','62.18.32.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-23 12:45:32'),(20,'login','62.18.32.9','62.18.32.9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-23 12:45:32'),(21,'login','127.0.0.1','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0',1,'2025-07-23 14:18:02'),(22,'login','127.0.0.1','127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0',1,'2025-07-23 14:18:02'),(23,'login','151.19.220.131','151.19.220.131','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 14:31:12'),(24,'login','151.19.220.131','151.19.220.131','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 14:31:18'),(25,'login','151.19.220.131','151.19.220.131','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 14:31:25'),(26,'login','151.19.220.131','151.19.220.131','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-23 14:31:25'),(27,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 06:18:06'),(28,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 06:18:12'),(29,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 06:18:12'),(30,'login','79.3.82.197','79.3.82.197','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-25 09:14:47'),(31,'login','79.3.82.197','79.3.82.197','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-25 09:14:47'),(32,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 11:05:23'),(33,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 11:05:23'),(34,'login','79.3.82.197','79.3.82.197','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 11:53:19'),(35,'login','79.3.82.197','79.3.82.197','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-25 11:53:19'),(36,'login','79.3.82.197','79.3.82.197','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-25 11:58:42'),(37,'login','79.3.82.197','79.3.82.197','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-25 11:58:42'),(38,'login','151.84.209.115','151.84.209.115','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-25 14:02:12'),(39,'login','151.84.209.115','151.84.209.115','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-25 14:02:12'),(40,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-26 05:17:36'),(41,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-26 05:17:36'),(42,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-26 07:41:26'),(43,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-26 07:41:26'),(44,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-26 15:56:26'),(45,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-26 15:56:38'),(46,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-26 15:56:38'),(47,'login','194.53.178.89','194.53.178.89','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-26 17:06:43'),(48,'login','194.53.178.89','194.53.178.89','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-26 17:06:44'),(49,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-27 05:30:29'),(50,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-27 05:30:30'),(51,'login','151.46.131.169','151.46.131.169','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-28 09:58:25'),(52,'login','151.46.131.169','151.46.131.169','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',1,'2025-07-28 09:58:25'),(53,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-28 16:39:12'),(54,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-28 16:39:20'),(55,'login','93.40.192.192','93.40.192.192','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-28 16:39:20'),(56,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 05:32:34'),(57,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 05:32:34'),(58,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 17:06:36'),(59,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 17:06:36'),(60,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 18:44:17'),(61,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 18:44:18'),(62,'login','93.40.195.88','93.40.195.88','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 18:47:05'),(63,'login','93.40.195.88','93.40.195.88','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 18:47:06'),(0,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 20:09:48'),(0,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-07-30 20:09:48'),(0,'login','2a02:b123:8f07:ba63:c1c9:63cd:912a:a0fc','2a02:b123:8f07:ba63:c1c9:63cd:912a:a0fc','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-30 20:32:31'),(0,'login','2a02:b123:8f07:ba63:c1c9:63cd:912a:a0fc','2a02:b123:8f07:ba63:c1c9:63cd:912a:a0fc','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-30 20:32:47'),(0,'login','2a02:b123:8f07:ba63:c1c9:63cd:912a:a0fc','2a02:b123:8f07:ba63:c1c9:63cd:912a:a0fc','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36',1,'2025-07-30 20:32:47'),(0,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-08-06 06:13:51'),(0,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-08-06 06:13:51'),(0,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-08-06 09:40:43'),(0,'login','::1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-08-06 09:40:43'),(0,'login','93.40.195.88','93.40.195.88','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-08-06 11:18:32'),(0,'login','93.40.195.88','93.40.195.88','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',1,'2025-08-06 11:18:32');
/*!40000 ALTER TABLE `rate_limit_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit_blacklist`
--

DROP TABLE IF EXISTS `rate_limit_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit_blacklist` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `permanent` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit_blacklist`
--

LOCK TABLES `rate_limit_blacklist` WRITE;
/*!40000 ALTER TABLE `rate_limit_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limit_blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limit_whitelist`
--

DROP TABLE IF EXISTS `rate_limit_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit_whitelist` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limit_whitelist`
--

LOCK TABLES `rate_limit_whitelist` WRITE;
/*!40000 ALTER TABLE `rate_limit_whitelist` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limit_whitelist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referenti`
--

DROP TABLE IF EXISTS `referenti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referenti` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `cellulare` varchar(50) DEFAULT NULL,
  `ruolo` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `azienda_id` int(11) DEFAULT NULL,
  `azienda_riferimento_id` int(11) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referenti`
--

LOCK TABLES `referenti` WRITE;
/*!40000 ALTER TABLE `referenti` DISABLE KEYS */;
/*!40000 ALTER TABLE `referenti` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referenti_aziende`
--

DROP TABLE IF EXISTS `referenti_aziende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referenti_aziende` (
  `id` int(11) NOT NULL,
  `referente_id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `ruolo` varchar(100) DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `data_assegnazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referenti_aziende`
--

LOCK TABLES `referenti_aziende` WRITE;
/*!40000 ALTER TABLE `referenti_aziende` DISABLE KEYS */;
/*!40000 ALTER TABLE `referenti_aziende` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessioni_utente`
--

DROP TABLE IF EXISTS `sessioni_utente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessioni_utente` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `data_login` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultimo_accesso` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_scadenza` datetime DEFAULT NULL,
  `attiva` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessioni_utente`
--

LOCK TABLES `sessioni_utente` WRITE;
/*!40000 ALTER TABLE `sessioni_utente` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessioni_utente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_assegnazioni`
--

DROP TABLE IF EXISTS `task_assegnazioni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assegnazioni` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `percentuale_completamento` decimal(5,2) DEFAULT 0.00,
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_assegnazioni`
--

LOCK TABLES `task_assegnazioni` WRITE;
/*!40000 ALTER TABLE `task_assegnazioni` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_assegnazioni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_calendario`
--

DROP TABLE IF EXISTS `task_calendario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_calendario` (
  `id` int(11) NOT NULL,
  `utente_assegnato_id` int(11) NOT NULL,
  `attivita` enum('Consulenza','Operation','Verifica','Office') NOT NULL,
  `giornate_previste` decimal(3,1) NOT NULL,
  `costo_giornata` decimal(10,2) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `citta` varchar(255) NOT NULL,
  `prodotto_servizio_tipo` enum('predefinito','personalizzato') DEFAULT 'predefinito',
  `prodotto_servizio_predefinito` enum('9001','14001','27001','45001','Autorizzazione','Accreditamento') DEFAULT NULL,
  `prodotto_servizio_personalizzato` varchar(255) DEFAULT NULL,
  `data_inizio` date NOT NULL,
  `data_fine` date NOT NULL,
  `usa_giorni_specifici` tinyint(1) DEFAULT 0,
  `descrizione` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `stato` enum('assegnato','in_corso','completato','annullato') DEFAULT 'assegnato',
  `percentuale_completamento_totale` decimal(5,2) DEFAULT 0.00,
  `evento_id` int(11) DEFAULT NULL,
  `assegnato_da` int(11) DEFAULT NULL,
  `data_assegnazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completato_il` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_calendario`
--

LOCK TABLES `task_calendario` WRITE;
/*!40000 ALTER TABLE `task_calendario` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_calendario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_giorni`
--

DROP TABLE IF EXISTS `task_giorni`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_giorni` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `data_giorno` date NOT NULL,
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_giorni`
--

LOCK TABLES `task_giorni` WRITE;
/*!40000 ALTER TABLE `task_giorni` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_giorni` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_progressi`
--

DROP TABLE IF EXISTS `task_progressi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_progressi` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `percentuale_precedente` decimal(5,2) DEFAULT NULL,
  `percentuale_nuova` decimal(5,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_progressi`
--

LOCK TABLES `task_progressi` WRITE;
/*!40000 ALTER TABLE `task_progressi` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_progressi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `azienda_id` int(11) DEFAULT NULL,
  `assegnato_a` int(11) DEFAULT NULL,
  `creato_da` int(11) NOT NULL,
  `priorita` enum('bassa','media','alta') DEFAULT 'media',
  `stato` enum('nuovo','in_corso','in_attesa','completato','annullato') DEFAULT 'nuovo',
  `data_scadenza` date DEFAULT NULL,
  `data_completamento` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES (1,'Task di esempio 1','Descrizione del primo task',NULL,NULL,2,'alta','nuovo','2025-08-01',NULL,NULL,'2025-07-25 07:53:41','2025-07-25 07:53:41'),(2,'Task di esempio 2','Descrizione del secondo task',NULL,NULL,2,'media','in_corso','2025-08-08',NULL,NULL,'2025-07-25 07:53:41','2025-07-25 07:53:41'),(3,'Task di esempio 3','Descrizione del terzo task',NULL,NULL,2,'bassa','nuovo','2025-08-24',NULL,NULL,'2025-07-25 07:53:41','2025-07-25 07:53:41');
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `temi_azienda`
--

DROP TABLE IF EXISTS `temi_azienda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temi_azienda` (
  `id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `nome_tema` varchar(100) NOT NULL,
  `colore_primario` varchar(7) DEFAULT '#2d5a9f',
  `colore_secondario` varchar(7) DEFAULT '#f8f9fa',
  `colore_testo` varchar(7) DEFAULT '#333333',
  `colore_sfondo` varchar(7) DEFAULT '#ffffff',
  `font_principale` varchar(100) DEFAULT 'Arial, sans-serif',
  `logo_personalizzato` varchar(255) DEFAULT NULL,
  `css_personalizzato` text DEFAULT NULL,
  `attivo` tinyint(1) DEFAULT 1,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_global` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `temi_azienda`
--

LOCK TABLES `temi_azienda` WRITE;
/*!40000 ALTER TABLE `temi_azienda` DISABLE KEYS */;
/*!40000 ALTER TABLE `temi_azienda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_destinatari`
--

DROP TABLE IF EXISTS `ticket_destinatari`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_destinatari` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `tipo_destinatario` enum('assegnato','cc','osservatore') DEFAULT 'cc',
  `notifica_inviata` tinyint(1) DEFAULT 0,
  `data_assegnazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `letto` tinyint(1) DEFAULT 0,
  `data_lettura` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_destinatari`
--

LOCK TABLES `ticket_destinatari` WRITE;
/*!40000 ALTER TABLE `ticket_destinatari` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_destinatari` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_risposte`
--

DROP TABLE IF EXISTS `ticket_risposte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_risposte` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `messaggio` text NOT NULL,
  `privata` tinyint(1) DEFAULT 0,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_risposte`
--

LOCK TABLES `ticket_risposte` WRITE;
/*!40000 ALTER TABLE `ticket_risposte` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_risposte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `codice` varchar(20) NOT NULL,
  `oggetto` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `priorita` enum('bassa','media','alta','urgente') DEFAULT 'media',
  `stato` enum('aperto','in_lavorazione','risolto','chiuso') DEFAULT 'aperto',
  `categoria` varchar(50) DEFAULT NULL,
  `azienda_id` int(11) DEFAULT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `assegnato_a` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_chiusura` datetime DEFAULT NULL,
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_value` tinyint(1) DEFAULT 1,
  `azienda_id` int(11) DEFAULT NULL,
  `creato_da` int(11) DEFAULT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utenti`
--

DROP TABLE IF EXISTS `utenti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utenti` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `cognome` varchar(100) DEFAULT NULL,
  `ruolo` enum('super_admin','utente_speciale','admin','staff','cliente') NOT NULL DEFAULT 'cliente',
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
  `data_registrazione` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utenti`
--

LOCK TABLES `utenti` WRITE;
/*!40000 ALTER TABLE `utenti` DISABLE KEYS */;
INSERT INTO `utenti` VALUES (2,'asamodeo','$2y$10$q3ZPZsX8X.atocgbjqV2L.EPslOrNhe7rIaRW5ITOewPRer2vzpP6','asamodeo@fortibyte.it','Antonio Silverstro','Amodeo','super_admin',NULL,1,0,1,'2025-10-20','2025-07-22 16:07:28','2025-07-22 10:04:46',NULL,NULL,NULL,'1991-09-02',NULL,NULL,0,NULL,NULL,NULL,'2025-07-27 05:58:32'),(15,'francescobarreca','$2y$10$lbIkd64X1K1KjhJI8Rv25.8wMgt4341yAmwXTbx7zNJC0VccG/yZu','francescobarreca@scosolution.it','Francesco','Barreca','super_admin',NULL,1,0,1,'2025-10-21','2025-07-23 12:46:15','2025-07-23 09:28:00',NULL,NULL,NULL,'1966-09-26',NULL,NULL,0,NULL,NULL,NULL,'2025-07-27 05:58:32'),(17,'arearicerca','$2y$10$2AN3ZxEegQqZ96M4S7RqJ.5g79Jq/3iIK5oUaio66wkNzgmG/5R6q','qualita@romolohospital.com','Bumbaca','Pierluigi','',NULL,1,0,1,'2025-10-23','2025-07-25 15:01:09','2025-07-25 10:49:54',NULL,NULL,NULL,'1991-09-02',NULL,NULL,0,NULL,NULL,NULL,'2025-07-27 05:58:32');
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
  `data_associazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utenti_aziende`
--

LOCK TABLES `utenti_aziende` WRITE;
/*!40000 ALTER TABLE `utenti_aziende` DISABLE KEYS */;
INSERT INTO `utenti_aziende` VALUES (8,17,4,'staff','','[]',NULL,1,'2025-07-26 06:06:08');
/*!40000 ALTER TABLE `utenti_aziende` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utenti_permessi`
--

DROP TABLE IF EXISTS `utenti_permessi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utenti_permessi` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `azienda_id` int(11) NOT NULL,
  `puo_vedere_documenti` tinyint(1) DEFAULT 1,
  `puo_creare_documenti` tinyint(1) DEFAULT 0,
  `puo_modificare_documenti` tinyint(1) DEFAULT 0,
  `puo_eliminare_documenti` tinyint(1) DEFAULT 0,
  `puo_scaricare_documenti` tinyint(1) DEFAULT 1,
  `puo_vedere_bozze` tinyint(1) DEFAULT 0,
  `puo_compilare_moduli` tinyint(1) DEFAULT 0,
  `puo_aprire_ticket` tinyint(1) DEFAULT 1,
  `puo_gestire_eventi` tinyint(1) DEFAULT 0,
  `puo_vedere_referenti` tinyint(1) DEFAULT 1,
  `puo_gestire_referenti` tinyint(1) DEFAULT 0,
  `puo_vedere_log_attivita` tinyint(1) DEFAULT 0,
  `riceve_notifiche_email` tinyint(1) DEFAULT 1,
  `puo_creare_eventi` tinyint(1) DEFAULT 0,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_modifica` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utenti_permessi`
--

LOCK TABLES `utenti_permessi` WRITE;
/*!40000 ALTER TABLE `utenti_permessi` DISABLE KEYS */;
INSERT INTO `utenti_permessi` VALUES (5,17,4,0,0,0,0,0,0,0,0,0,0,0,0,0,0,'2025-07-26 06:06:08','2025-07-26 06:06:08');
/*!40000 ALTER TABLE `utenti_permessi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `versioni_documenti_iso`
--

DROP TABLE IF EXISTS `versioni_documenti_iso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `versioni_documenti_iso` (
  `id` int(11) NOT NULL,
  `documento_id` int(11) NOT NULL,
  `versione` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `dimensione_file` bigint(20) DEFAULT NULL,
  `note_versione` text DEFAULT NULL,
  `creato_da` int(11) NOT NULL,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `versioni_documenti_iso`
--

LOCK TABLES `versioni_documenti_iso` WRITE;
/*!40000 ALTER TABLE `versioni_documenti_iso` DISABLE KEYS */;
/*!40000 ALTER TABLE `versioni_documenti_iso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `vista_conteggio_giornate_task`
--

DROP TABLE IF EXISTS `vista_conteggio_giornate_task`;
/*!50001 DROP VIEW IF EXISTS `vista_conteggio_giornate_task`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vista_conteggio_giornate_task` AS SELECT
 1 AS `utente_assegnato_id`,
  1 AS `attivita`,
  1 AS `totale_giornate`,
  1 AS `giornate_completate`,
  1 AS `giornate_pianificate` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `vista_log_attivita`
--

DROP TABLE IF EXISTS `vista_log_attivita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vista_log_attivita` (
  `id` int(11) DEFAULT NULL,
  `utente_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `descrizione` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `data_azione` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `entita_tipo` varchar(50) DEFAULT NULL,
  `entita_id` int(11) DEFAULT NULL,
  `azione` varchar(50) DEFAULT NULL,
  `dettagli` longtext DEFAULT NULL,
  `azienda_id` int(11) DEFAULT NULL,
  `nome_utente` varchar(100) DEFAULT NULL,
  `cognome_utente` varchar(100) DEFAULT NULL,
  `nome_completo` varchar(201) DEFAULT NULL,
  `email_utente` varchar(255) DEFAULT NULL,
  `tipo_utente` enum('super_admin','utente_speciale','admin','staff','cliente') DEFAULT NULL,
  `nome_azienda` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vista_log_attivita`
--

LOCK TABLES `vista_log_attivita` WRITE;
/*!40000 ALTER TABLE `vista_log_attivita` DISABLE KEYS */;
/*!40000 ALTER TABLE `vista_log_attivita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vista_statistiche_aziende`
--

DROP TABLE IF EXISTS `vista_statistiche_aziende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vista_statistiche_aziende` (
  `id` int(11) DEFAULT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `numero_utenti` bigint(21) DEFAULT NULL,
  `numero_documenti` bigint(21) DEFAULT NULL,
  `numero_eventi` bigint(21) DEFAULT NULL,
  `tickets_aperti` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vista_statistiche_aziende`
--

LOCK TABLES `vista_statistiche_aziende` WRITE;
/*!40000 ALTER TABLE `vista_statistiche_aziende` DISABLE KEYS */;
/*!40000 ALTER TABLE `vista_statistiche_aziende` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'nexiosol'
--

--
-- Final view structure for view `vista_conteggio_giornate_task`
--

/*!50001 DROP VIEW IF EXISTS `vista_conteggio_giornate_task`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_conteggio_giornate_task` AS select `tc`.`utente_assegnato_id` AS `utente_assegnato_id`,`tc`.`attivita` AS `attivita`,sum(`tc`.`giornate_previste`) AS `totale_giornate`,sum(case when `tc`.`stato` = 'completato' then `tc`.`giornate_previste` else 0 end) AS `giornate_completate`,sum(case when `tc`.`stato` in ('assegnato','in_corso') then `tc`.`giornate_previste` else 0 end) AS `giornate_pianificate` from `task_calendario` `tc` where `tc`.`stato` <> 'annullato' group by `tc`.`utente_assegnato_id`,`tc`.`attivita` */;
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

-- Dump completed on 2025-08-06 12:50:20
