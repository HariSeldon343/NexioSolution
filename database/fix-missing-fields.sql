-- Fix Missing Fields in Nexio Database
-- This script adds all missing fields identified by the integrity check

-- 1. Fix utenti table
ALTER TABLE `utenti` 
ADD COLUMN IF NOT EXISTS `password_expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_password_change`;

-- Add missing index on email
ALTER TABLE `utenti` 
ADD INDEX IF NOT EXISTS `idx_email` (`email`);

-- 2. Fix documenti table
ALTER TABLE `documenti` 
ADD COLUMN IF NOT EXISTS `template_id` INT NULL DEFAULT NULL AFTER `cartella_id`,
ADD COLUMN IF NOT EXISTS `versione` INT NOT NULL DEFAULT 1 AFTER `file_path`;

-- Add foreign key for template_id
ALTER TABLE `documenti`
ADD CONSTRAINT `fk_documenti_template` 
FOREIGN KEY IF NOT EXISTS (`template_id`) 
REFERENCES `template_documenti`(`id`) 
ON DELETE SET NULL;

-- 3. Fix eventi table - add missing index
ALTER TABLE `eventi` 
ADD INDEX IF NOT EXISTS `idx_data_inizio` (`data_inizio`);

-- 4. Fix notifiche_email table
ALTER TABLE `notifiche_email`
ADD COLUMN IF NOT EXISTS `corpo_html` LONGTEXT NULL AFTER `oggetto`;

-- Migrate existing corpo content to corpo_html if exists
UPDATE `notifiche_email` 
SET `corpo_html` = `corpo` 
WHERE `corpo_html` IS NULL 
AND `corpo` IS NOT NULL;

-- 5. Fix moduli_azienda table
ALTER TABLE `moduli_azienda`
ADD COLUMN IF NOT EXISTS `attivo` BOOLEAN DEFAULT TRUE AFTER `modulo_id`;

-- 6. Ensure template_documenti table exists
CREATE TABLE IF NOT EXISTS `template_documenti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(200) NOT NULL,
    `descrizione` TEXT,
    `contenuto_html` LONGTEXT,
    `struttura` JSON,
    `tipo` VARCHAR(50) DEFAULT 'documento',
    `azienda_id` INT NOT NULL,
    `creato_da` INT,
    `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `data_modifica` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_azienda_id` (`azienda_id`),
    INDEX `idx_tipo` (`tipo`),
    CONSTRAINT `fk_template_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_template_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Add any other missing columns that might be needed
ALTER TABLE `documenti`
ADD COLUMN IF NOT EXISTS `classificazione_id` INT NULL DEFAULT NULL AFTER `template_id`,
ADD COLUMN IF NOT EXISTS `creato_da` INT NULL DEFAULT NULL AFTER `azienda_id`,
ADD COLUMN IF NOT EXISTS `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `creato_da`,
ADD COLUMN IF NOT EXISTS `modificato_da` INT NULL DEFAULT NULL AFTER `data_creazione`,
ADD COLUMN IF NOT EXISTS `data_modifica` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `modificato_da`;

-- 8. Ensure classificazioni table exists for the classificazione_id reference
CREATE TABLE IF NOT EXISTS `classificazioni` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codice` VARCHAR(50) NOT NULL,
    `nome` VARCHAR(200) NOT NULL,
    `descrizione` TEXT,
    `parent_id` INT NULL,
    `livello` INT NOT NULL DEFAULT 1,
    `azienda_id` INT NOT NULL,
    INDEX `idx_codice` (`codice`),
    INDEX `idx_parent_id` (`parent_id`),
    INDEX `idx_azienda_id` (`azienda_id`),
    UNIQUE KEY `uk_codice_azienda` (`codice`, `azienda_id`),
    CONSTRAINT `fk_classificazioni_parent` FOREIGN KEY (`parent_id`) REFERENCES `classificazioni`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_classificazioni_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for classificazione_id
ALTER TABLE `documenti`
ADD CONSTRAINT `fk_documenti_classificazione` 
FOREIGN KEY IF NOT EXISTS (`classificazione_id`) 
REFERENCES `classificazioni`(`id`) 
ON DELETE SET NULL;

-- 9. Update existing records with default values where needed
UPDATE `utenti` 
SET `password_expires_at` = DATE_ADD(COALESCE(`last_password_change`, NOW()), INTERVAL 60 DAY)
WHERE `password_expires_at` IS NULL;

UPDATE `documenti` 
SET `versione` = 1 
WHERE `versione` IS NULL OR `versione` = 0;

-- 10. Add missing indexes for better performance
ALTER TABLE `documenti`
ADD INDEX IF NOT EXISTS `idx_stato` (`stato`),
ADD INDEX IF NOT EXISTS `idx_data_creazione` (`data_creazione`);

ALTER TABLE `notifiche_email`
ADD INDEX IF NOT EXISTS `idx_data_creazione` (`data_creazione`);

ALTER TABLE `tickets`
ADD INDEX IF NOT EXISTS `idx_codice` (`codice`);

-- Report completion
SELECT 'All missing fields have been added successfully' as Result;