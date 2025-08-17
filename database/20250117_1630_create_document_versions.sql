-- Migrazione: Creazione tabella document_versions per versionamento documenti con editor
-- Data: 2025-01-17 16:30
-- Descrizione: Sistema di versionamento per documenti con supporto HTML e DOCX

START TRANSACTION;

-- Creazione tabella document_versions
CREATE TABLE IF NOT EXISTS `document_versions` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY COMMENT 'ID univoco versione (UUID o timestamp-based)',
    `document_id` INT(11) NOT NULL COMMENT 'FK a documenti.id',
    `version_number` INT(11) NOT NULL DEFAULT 1 COMMENT 'Numero progressivo versione',
    `contenuto_html` LONGTEXT DEFAULT NULL COMMENT 'Contenuto HTML del documento dall editor',
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Percorso file DOCX generato',
    `file_size` BIGINT(20) DEFAULT 0 COMMENT 'Dimensione file in bytes',
    `mime_type` VARCHAR(100) DEFAULT NULL COMMENT 'MIME type del file',
    `hash_file` VARCHAR(64) DEFAULT NULL COMMENT 'Hash SHA256 del file per deduplicazione',
    `created_by` INT(11) DEFAULT NULL COMMENT 'ID utente che ha creato la versione',
    `created_by_name` VARCHAR(200) DEFAULT NULL COMMENT 'Nome utente al momento della creazione',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/ora creazione versione',
    `is_major` BOOLEAN DEFAULT FALSE COMMENT 'Flag per versione maggiore (1.0 vs 1.1)',
    `notes` TEXT DEFAULT NULL COMMENT 'Note sulla versione o changelog',
    `is_current` BOOLEAN DEFAULT FALSE COMMENT 'Flag per versione corrente attiva',
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'published' COMMENT 'Stato della versione',
    
    -- Indici per performance
    INDEX `idx_doc_versions` (`document_id`, `version_number` DESC),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_is_current` (`is_current`),
    INDEX `idx_hash` (`hash_file`),
    
    -- Foreign Key constraint
    CONSTRAINT `fk_document_versions_document` 
        FOREIGN KEY (`document_id`) 
        REFERENCES `documenti` (`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    CONSTRAINT `fk_document_versions_user` 
        FOREIGN KEY (`created_by`) 
        REFERENCES `utenti` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    
    -- Vincolo di unicità per document_id + version_number
    UNIQUE KEY `uk_document_version` (`document_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tabella per il versionamento dei documenti con supporto editor HTML';

-- Trigger per gestire automaticamente il flag is_current
DELIMITER $$

CREATE TRIGGER `before_insert_document_versions`
BEFORE INSERT ON `document_versions`
FOR EACH ROW
BEGIN
    -- Se è la prima versione o is_current è TRUE, aggiorna le altre versioni
    IF NEW.is_current = TRUE THEN
        UPDATE `document_versions` 
        SET `is_current` = FALSE 
        WHERE `document_id` = NEW.document_id;
    END IF;
    
    -- Auto-incrementa version_number se non specificato
    IF NEW.version_number IS NULL OR NEW.version_number = 0 THEN
        SET NEW.version_number = (
            SELECT COALESCE(MAX(version_number), 0) + 1 
            FROM `document_versions` 
            WHERE `document_id` = NEW.document_id
        );
    END IF;
    
    -- Genera ID se non specificato (formato: docv_timestamp_random)
    IF NEW.id IS NULL OR NEW.id = '' THEN
        SET NEW.id = CONCAT('docv_', UNIX_TIMESTAMP(), '_', SUBSTRING(MD5(RAND()), 1, 8));
    END IF;
END$$

CREATE TRIGGER `after_update_document_versions`
AFTER UPDATE ON `document_versions`
FOR EACH ROW
BEGIN
    -- Se una versione diventa current, le altre devono essere non-current
    IF NEW.is_current = TRUE AND OLD.is_current = FALSE THEN
        UPDATE `document_versions` 
        SET `is_current` = FALSE 
        WHERE `document_id` = NEW.document_id 
        AND `id` != NEW.id;
    END IF;
END$$

DELIMITER ;

-- Tabella di supporto per tracciare chi ha visto quale versione
CREATE TABLE IF NOT EXISTS `document_version_views` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `version_id` VARCHAR(50) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    
    INDEX `idx_version_user` (`version_id`, `user_id`),
    INDEX `idx_viewed_at` (`viewed_at`),
    
    CONSTRAINT `fk_version_views_version` 
        FOREIGN KEY (`version_id`) 
        REFERENCES `document_versions` (`id`) 
        ON DELETE CASCADE,
    
    CONSTRAINT `fk_version_views_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `utenti` (`id`) 
        ON DELETE CASCADE,
    
    UNIQUE KEY `uk_version_user_view` (`version_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tracciamento visualizzazioni versioni documenti';

-- Tabella per confronti tra versioni
CREATE TABLE IF NOT EXISTS `document_version_comparisons` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `version_from_id` VARCHAR(50) NOT NULL,
    `version_to_id` VARCHAR(50) NOT NULL,
    `diff_html` LONGTEXT DEFAULT NULL COMMENT 'HTML con differenze evidenziate',
    `changes_summary` TEXT DEFAULT NULL COMMENT 'Riassunto delle modifiche',
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_versions` (`version_from_id`, `version_to_id`),
    INDEX `idx_created_at` (`created_at`),
    
    CONSTRAINT `fk_comparison_from` 
        FOREIGN KEY (`version_from_id`) 
        REFERENCES `document_versions` (`id`) 
        ON DELETE CASCADE,
    
    CONSTRAINT `fk_comparison_to` 
        FOREIGN KEY (`version_to_id`) 
        REFERENCES `document_versions` (`id`) 
        ON DELETE CASCADE,
    
    CONSTRAINT `fk_comparison_user` 
        FOREIGN KEY (`created_by`) 
        REFERENCES `utenti` (`id`) 
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Cache dei confronti tra versioni documenti';

-- Aggiunta colonna alla tabella documenti per referenziare la versione corrente
ALTER TABLE `documenti` 
ADD COLUMN IF NOT EXISTS `current_version_id` VARCHAR(50) DEFAULT NULL 
COMMENT 'ID della versione corrente attiva' AFTER `versione`;

ALTER TABLE `documenti`
ADD COLUMN IF NOT EXISTS `contenuto_html` LONGTEXT DEFAULT NULL 
COMMENT 'Contenuto HTML corrente del documento' AFTER `contenuto`;

ALTER TABLE `documenti`
ADD COLUMN IF NOT EXISTS `enable_versioning` BOOLEAN DEFAULT TRUE 
COMMENT 'Flag per abilitare/disabilitare versionamento' AFTER `current_version_id`;

-- Aggiungi indice per performance
ALTER TABLE `documenti` 
ADD INDEX IF NOT EXISTS `idx_current_version` (`current_version_id`);

-- Foreign key dalla tabella documenti
ALTER TABLE `documenti`
ADD CONSTRAINT `fk_documenti_current_version` 
    FOREIGN KEY (`current_version_id`) 
    REFERENCES `document_versions` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

-- Vista per recuperare facilmente l'ultima versione di ogni documento
CREATE OR REPLACE VIEW `v_latest_document_versions` AS
SELECT 
    dv.*,
    d.titolo as document_title,
    d.codice as document_code,
    d.tipo_documento as document_type,
    d.azienda_id,
    u.nome as created_by_user_name,
    u.cognome as created_by_user_surname
FROM `document_versions` dv
INNER JOIN `documenti` d ON dv.document_id = d.id
LEFT JOIN `utenti` u ON dv.created_by = u.id
WHERE dv.is_current = TRUE
ORDER BY dv.document_id, dv.version_number DESC;

-- Stored procedure per creare una nuova versione
DELIMITER $$

CREATE PROCEDURE `create_document_version`(
    IN p_document_id INT,
    IN p_contenuto_html LONGTEXT,
    IN p_file_path VARCHAR(500),
    IN p_created_by INT,
    IN p_is_major BOOLEAN,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_version_number INT;
    DECLARE v_version_id VARCHAR(50);
    DECLARE v_user_name VARCHAR(200);
    
    -- Ottieni il nome utente
    SELECT CONCAT(nome, ' ', cognome) INTO v_user_name 
    FROM `utenti` 
    WHERE id = p_created_by;
    
    -- Calcola il prossimo numero di versione
    SELECT COALESCE(MAX(version_number), 0) + 1 INTO v_version_number
    FROM `document_versions`
    WHERE document_id = p_document_id;
    
    -- Genera ID versione
    SET v_version_id = CONCAT('docv_', UNIX_TIMESTAMP(), '_', SUBSTRING(MD5(RAND()), 1, 8));
    
    -- Inserisci la nuova versione
    INSERT INTO `document_versions` (
        id, document_id, version_number, contenuto_html, 
        file_path, created_by, created_by_name, 
        is_major, notes, is_current
    ) VALUES (
        v_version_id, p_document_id, v_version_number, p_contenuto_html,
        p_file_path, p_created_by, v_user_name,
        p_is_major, p_notes, TRUE
    );
    
    -- Aggiorna il documento principale
    UPDATE `documenti` 
    SET 
        current_version_id = v_version_id,
        contenuto_html = p_contenuto_html,
        versione = v_version_number
    WHERE id = p_document_id;
    
    SELECT v_version_id as version_id, v_version_number as version_number;
END$$

DELIMITER ;

-- Inserisci alcuni dati di esempio per test (opzionale, commentato)
-- INSERT INTO `document_versions` (document_id, version_number, contenuto_html, created_by, created_by_name, is_current, notes)
-- SELECT 
--     id, 
--     1, 
--     contenuto,
--     1,
--     'Sistema',
--     TRUE,
--     'Versione iniziale importata dal sistema'
-- FROM `documenti` 
-- WHERE contenuto IS NOT NULL 
-- LIMIT 5;

COMMIT;

-- Query di verifica post-migrazione
SELECT 'Migrazione completata con successo!' as risultato;
SELECT COUNT(*) as tabelle_create FROM information_schema.tables 
WHERE table_schema = 'nexiosol' 
AND table_name IN ('document_versions', 'document_version_views', 'document_version_comparisons');