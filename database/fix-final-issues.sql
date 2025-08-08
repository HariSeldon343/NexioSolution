-- Fix Final Issues for Nexio Platform

-- 1. Create trigger to protect log_attivita from deletion
DELIMITER $$

DROP TRIGGER IF EXISTS prevent_log_attivita_delete$$
CREATE TRIGGER prevent_log_attivita_delete
BEFORE DELETE ON log_attivita
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Deletion from log_attivita is not allowed for audit compliance';
END$$

DELIMITER ;

-- 2. Insert core modules
INSERT INTO moduli_sistema (codice, nome, descrizione, attivo) VALUES
('document_management', 'Gestione Documenti', 'Sistema completo di gestione documentale con versioning e classificazione', 1),
('calendar', 'Calendario', 'Calendario eventi e appuntamenti con inviti e notifiche', 1),
('email_notifications', 'Notifiche Email', 'Sistema di notifiche email automatiche', 1),
('iso_compliance', 'Conformità ISO', 'Gestione conformità ISO 9001/14001/45001', 1),
('advanced_editor', 'Editor Avanzato', 'Editor documenti avanzato con template', 1),
('file_manager', 'File Manager', 'Gestione avanzata file e cartelle', 1),
('user_management', 'Gestione Utenti', 'Sistema di gestione utenti e permessi', 1),
('activity_log', 'Log Attività', 'Registro attività e audit trail', 1),
('backup_restore', 'Backup e Restore', 'Sistema di backup e ripristino', 1),
('nexio_ai', 'Nexio AI', 'Assistente AI integrato per supporto intelligente', 1)
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    descrizione = VALUES(descrizione),
    attivo = VALUES(attivo);

-- 3. Add index on eventi.data_inizio if missing
ALTER TABLE eventi 
ADD INDEX IF NOT EXISTS idx_data_inizio (data_inizio);

-- 4. Create ISO tables (basic structure)
CREATE TABLE IF NOT EXISTS `iso_standard` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codice` VARCHAR(50) NOT NULL UNIQUE,
    `nome` VARCHAR(200) NOT NULL,
    `versione` VARCHAR(20),
    `descrizione` TEXT,
    `attivo` BOOLEAN DEFAULT TRUE,
    `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iso_sezioni` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `standard_id` INT NOT NULL,
    `codice` VARCHAR(50) NOT NULL,
    `titolo` VARCHAR(200) NOT NULL,
    `descrizione` TEXT,
    `parent_id` INT NULL,
    `ordine` INT DEFAULT 0,
    INDEX `idx_standard_id` (`standard_id`),
    INDEX `idx_parent_id` (`parent_id`),
    CONSTRAINT `fk_sezioni_standard` FOREIGN KEY (`standard_id`) REFERENCES `iso_standard`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sezioni_parent` FOREIGN KEY (`parent_id`) REFERENCES `iso_sezioni`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iso_requisiti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sezione_id` INT NOT NULL,
    `codice` VARCHAR(50) NOT NULL,
    `descrizione` TEXT NOT NULL,
    `tipo` ENUM('obbligatorio', 'raccomandato', 'informativo') DEFAULT 'obbligatorio',
    `note` TEXT,
    INDEX `idx_sezione_id` (`sezione_id`),
    CONSTRAINT `fk_requisiti_sezione` FOREIGN KEY (`sezione_id`) REFERENCES `iso_sezioni`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iso_documenti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `requisito_id` INT NOT NULL,
    `documento_id` INT NOT NULL,
    `azienda_id` INT NOT NULL,
    `stato` ENUM('bozza', 'in_revisione', 'approvato', 'obsoleto') DEFAULT 'bozza',
    `data_associazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_requisito_id` (`requisito_id`),
    INDEX `idx_documento_id` (`documento_id`),
    INDEX `idx_azienda_id` (`azienda_id`),
    UNIQUE KEY `uk_requisito_documento_azienda` (`requisito_id`, `documento_id`, `azienda_id`),
    CONSTRAINT `fk_iso_doc_requisito` FOREIGN KEY (`requisito_id`) REFERENCES `iso_requisiti`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_iso_doc_documento` FOREIGN KEY (`documento_id`) REFERENCES `documenti`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_iso_doc_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iso_audit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `azienda_id` INT NOT NULL,
    `standard_id` INT NOT NULL,
    `data_audit` DATE NOT NULL,
    `tipo` ENUM('interno', 'esterno', 'certificazione') DEFAULT 'interno',
    `auditor` VARCHAR(200),
    `stato` ENUM('pianificato', 'in_corso', 'completato', 'annullato') DEFAULT 'pianificato',
    `risultato` ENUM('conforme', 'non_conforme', 'parzialmente_conforme') NULL,
    `note` TEXT,
    `creato_da` INT,
    `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_azienda_id` (`azienda_id`),
    INDEX `idx_standard_id` (`standard_id`),
    INDEX `idx_data_audit` (`data_audit`),
    CONSTRAINT `fk_audit_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_audit_standard` FOREIGN KEY (`standard_id`) REFERENCES `iso_standard`(`id`),
    CONSTRAINT `fk_audit_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iso_non_conformita` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `audit_id` INT NOT NULL,
    `requisito_id` INT NOT NULL,
    `descrizione` TEXT NOT NULL,
    `gravita` ENUM('minore', 'maggiore', 'critica') DEFAULT 'minore',
    `stato` ENUM('aperta', 'in_lavorazione', 'chiusa', 'verificata') DEFAULT 'aperta',
    `data_rilevazione` DATE NOT NULL,
    `data_chiusura` DATE NULL,
    INDEX `idx_audit_id` (`audit_id`),
    INDEX `idx_requisito_id` (`requisito_id`),
    INDEX `idx_stato` (`stato`),
    CONSTRAINT `fk_nc_audit` FOREIGN KEY (`audit_id`) REFERENCES `iso_audit`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_nc_requisito` FOREIGN KEY (`requisito_id`) REFERENCES `iso_requisiti`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iso_azioni_correttive` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `non_conformita_id` INT NOT NULL,
    `descrizione` TEXT NOT NULL,
    `responsabile_id` INT NOT NULL,
    `data_prevista` DATE NOT NULL,
    `data_completamento` DATE NULL,
    `stato` ENUM('pianificata', 'in_corso', 'completata', 'verificata') DEFAULT 'pianificata',
    `efficacia` ENUM('efficace', 'parzialmente_efficace', 'non_efficace') NULL,
    `note` TEXT,
    INDEX `idx_non_conformita_id` (`non_conformita_id`),
    INDEX `idx_responsabile_id` (`responsabile_id`),
    INDEX `idx_stato` (`stato`),
    CONSTRAINT `fk_ac_non_conformita` FOREIGN KEY (`non_conformita_id`) REFERENCES `iso_non_conformita`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ac_responsabile` FOREIGN KEY (`responsabile_id`) REFERENCES `utenti`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Insert basic ISO standards
INSERT INTO iso_standard (codice, nome, versione, descrizione) VALUES
('ISO_9001', 'ISO 9001 - Sistema di Gestione Qualità', '2015', 'Standard internazionale per i sistemi di gestione della qualità'),
('ISO_14001', 'ISO 14001 - Sistema di Gestione Ambientale', '2015', 'Standard internazionale per i sistemi di gestione ambientale'),
('ISO_45001', 'ISO 45001 - Sistema di Gestione Salute e Sicurezza', '2018', 'Standard internazionale per i sistemi di gestione della salute e sicurezza sul lavoro')
ON DUPLICATE KEY UPDATE versione = VALUES(versione);

-- 6. Enable core modules for all companies
INSERT INTO moduli_azienda (azienda_id, modulo_id, abilitato)
SELECT a.id, m.id, 1
FROM aziende a
CROSS JOIN moduli_sistema m
WHERE m.codice IN ('document_management', 'calendar', 'tickets', 'email_notifications', 'user_management', 'activity_log')
ON DUPLICATE KEY UPDATE abilitato = 1;

-- Report completion
SELECT 'All final issues have been fixed' as Result;