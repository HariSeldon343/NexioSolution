-- Create Missing Tables for Nexio Platform

-- 1. Create template_documenti table if not exists
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

-- 2. Create classificazioni table if not exists
CREATE TABLE IF NOT EXISTS `classificazioni` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codice` VARCHAR(50) NOT NULL,
    `nome` VARCHAR(200) NOT NULL,
    `descrizione` TEXT,
    `parent_id` INT NULL,
    `livello` INT NOT NULL DEFAULT 1,
    `azienda_id` INT NOT NULL,
    `percorso_completo` VARCHAR(500),
    `ordine` INT DEFAULT 0,
    `attiva` BOOLEAN DEFAULT TRUE,
    `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `data_modifica` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_codice` (`codice`),
    INDEX `idx_parent_id` (`parent_id`),
    INDEX `idx_azienda_id` (`azienda_id`),
    UNIQUE KEY `uk_codice_azienda` (`codice`, `azienda_id`),
    CONSTRAINT `fk_classificazioni_parent` FOREIGN KEY (`parent_id`) REFERENCES `classificazioni`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_classificazioni_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create task_calendario table if not exists
CREATE TABLE IF NOT EXISTS `task_calendario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titolo` VARCHAR(255) NOT NULL,
    `descrizione` TEXT,
    `tipo_attivita` ENUM('Consulenza', 'Operation', 'Verifica', 'Office') NOT NULL,
    `giornate_uomo` DECIMAL(3,1) NOT NULL CHECK (`giornate_uomo` >= 0 AND `giornate_uomo` <= 15),
    `data_inizio` DATE NOT NULL,
    `data_fine` DATE NOT NULL,
    `assegnato_a` INT NOT NULL,
    `creato_da` INT NOT NULL,
    `azienda_id` INT NOT NULL,
    `stato` ENUM('da_fare', 'in_corso', 'completato', 'annullato') DEFAULT 'da_fare',
    `priorita` ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    `note` TEXT,
    `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `data_modifica` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_assegnato_a` (`assegnato_a`),
    INDEX `idx_azienda_id` (`azienda_id`),
    INDEX `idx_stato` (`stato`),
    INDEX `idx_data_inizio` (`data_inizio`),
    INDEX `idx_data_fine` (`data_fine`),
    CONSTRAINT `fk_task_assegnato` FOREIGN KEY (`assegnato_a`) REFERENCES `utenti`(`id`),
    CONSTRAINT `fk_task_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti`(`id`),
    CONSTRAINT `fk_task_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create evento_partecipanti table if not exists
CREATE TABLE IF NOT EXISTS `evento_partecipanti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `evento_id` INT NOT NULL,
    `utente_id` INT NOT NULL,
    `stato` ENUM('invitato', 'accettato', 'rifiutato', 'tentativo') DEFAULT 'invitato',
    `ruolo` ENUM('organizzatore', 'partecipante', 'opzionale') DEFAULT 'partecipante',
    `note` TEXT,
    `data_risposta` TIMESTAMP NULL,
    `data_invito` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_evento_utente` (`evento_id`, `utente_id`),
    INDEX `idx_utente_id` (`utente_id`),
    INDEX `idx_stato` (`stato`),
    CONSTRAINT `fk_partecipante_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventi`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_partecipante_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create referenti table if not exists
CREATE TABLE IF NOT EXISTS `referenti` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL,
    `cognome` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(50),
    `cellulare` VARCHAR(50),
    `ruolo` VARCHAR(100),
    `azienda_id` INT NOT NULL,
    `azienda_riferimento` VARCHAR(200),
    `note` TEXT,
    `attivo` BOOLEAN DEFAULT TRUE,
    `creato_da` INT,
    `data_creazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `data_modifica` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_azienda_id` (`azienda_id`),
    INDEX `idx_email` (`email`),
    CONSTRAINT `fk_referenti_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_referenti_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create tickets_destinatari table if not exists
CREATE TABLE IF NOT EXISTS `tickets_destinatari` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `utente_id` INT NOT NULL,
    `tipo` ENUM('assegnato', 'cc', 'observer') DEFAULT 'assegnato',
    `data_assegnazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_ticket_utente` (`ticket_id`, `utente_id`),
    INDEX `idx_utente_id` (`utente_id`),
    INDEX `idx_tipo` (`tipo`),
    CONSTRAINT `fk_ticket_dest_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_dest_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create documenti_destinatari table if not exists
CREATE TABLE IF NOT EXISTS `documenti_destinatari` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `documento_id` INT NOT NULL,
    `utente_id` INT NOT NULL,
    `tipo_accesso` ENUM('lettura', 'scrittura', 'completo') DEFAULT 'lettura',
    `data_assegnazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `assegnato_da` INT,
    UNIQUE KEY `uk_documento_utente` (`documento_id`, `utente_id`),
    INDEX `idx_utente_id` (`utente_id`),
    INDEX `idx_tipo_accesso` (`tipo_accesso`),
    CONSTRAINT `fk_doc_dest_documento` FOREIGN KEY (`documento_id`) REFERENCES `documenti`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_dest_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_dest_assegnato` FOREIGN KEY (`assegnato_da`) REFERENCES `utenti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create password_history table if not exists
CREATE TABLE IF NOT EXISTS `password_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utente_id` INT NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `data_cambio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_utente_id` (`utente_id`),
    INDEX `idx_data_cambio` (`data_cambio`),
    CONSTRAINT `fk_pwd_history_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Create user_permissions table if not exists
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `utente_id` INT NOT NULL,
    `permesso` VARCHAR(100) NOT NULL,
    `risorsa_tipo` VARCHAR(50),
    `risorsa_id` INT,
    `concesso_da` INT,
    `data_concessione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `data_scadenza` TIMESTAMP NULL,
    UNIQUE KEY `uk_utente_permesso_risorsa` (`utente_id`, `permesso`, `risorsa_tipo`, `risorsa_id`),
    INDEX `idx_permesso` (`permesso`),
    INDEX `idx_risorsa` (`risorsa_tipo`, `risorsa_id`),
    CONSTRAINT `fk_user_perm_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_perm_concesso` FOREIGN KEY (`concesso_da`) REFERENCES `utenti`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report completion
SELECT 'All missing tables have been created successfully' as Result;