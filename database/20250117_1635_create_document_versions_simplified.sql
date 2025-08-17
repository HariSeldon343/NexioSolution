-- Migrazione: Creazione tabella document_versions per versionamento documenti
-- Data: 2025-01-17 16:35
-- Versione semplificata per esecuzione sicura

-- Drop tabella di test se esiste
DROP TABLE IF EXISTS `document_versions`;

-- Creazione tabella document_versions
CREATE TABLE `document_versions` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `document_id` INT(11) NOT NULL,
    `version_number` INT(11) NOT NULL DEFAULT 1,
    `contenuto_html` LONGTEXT DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT(20) DEFAULT 0,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `hash_file` VARCHAR(64) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_by_name` VARCHAR(200) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_major` BOOLEAN DEFAULT FALSE,
    `notes` TEXT DEFAULT NULL,
    `is_current` BOOLEAN DEFAULT FALSE,
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'published',
    
    INDEX `idx_doc_versions` (`document_id`, `version_number` DESC),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_is_current` (`is_current`),
    INDEX `idx_hash` (`hash_file`),
    
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
    
    UNIQUE KEY `uk_document_version` (`document_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per tracciare visualizzazioni
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per confronti tra versioni
CREATE TABLE IF NOT EXISTS `document_version_comparisons` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `version_from_id` VARCHAR(50) NOT NULL,
    `version_to_id` VARCHAR(50) NOT NULL,
    `diff_html` LONGTEXT DEFAULT NULL,
    `changes_summary` TEXT DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;