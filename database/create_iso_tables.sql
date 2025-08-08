-- Creazione tabelle per il sistema di gestione documentale ISO

-- Tabella spazi documentali
CREATE TABLE IF NOT EXISTS spazi_documentali (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('super_admin', 'azienda') NOT NULL,
    azienda_id INT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_tipo (tipo),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella cartelle ISO
CREATE TABLE IF NOT EXISTS cartelle_iso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spazio_id INT NOT NULL,
    parent_id INT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    tipo_iso VARCHAR(50) NULL,
    icona VARCHAR(50) DEFAULT 'fas fa-folder',
    colore VARCHAR(7) DEFAULT '#fbbf24',
    ordine INT DEFAULT 0,
    protetta BOOLEAN DEFAULT FALSE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spazio_id) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES cartelle_iso(id) ON DELETE CASCADE,
    INDEX idx_spazio (spazio_id),
    INDEX idx_parent (parent_id),
    INDEX idx_ordine (ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella documenti ISO
CREATE TABLE IF NOT EXISTS documenti_iso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spazio_id INT NOT NULL,
    cartella_id INT NULL,
    titolo VARCHAR(255) NOT NULL,
    codice VARCHAR(100) UNIQUE NOT NULL,
    descrizione TEXT,
    file_path VARCHAR(500),
    tipo_file VARCHAR(50),
    dimensione_file BIGINT,
    tipo_iso VARCHAR(50),
    classificazione VARCHAR(50),
    tags TEXT,
    versione_corrente INT DEFAULT 1,
    stato ENUM('bozza', 'attivo', 'obsoleto', 'archiviato') DEFAULT 'attivo',
    creato_da INT NOT NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificato_da INT NULL,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spazio_id) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (cartella_id) REFERENCES cartelle_iso(id) ON DELETE SET NULL,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    FOREIGN KEY (modificato_da) REFERENCES utenti(id),
    INDEX idx_spazio (spazio_id),
    INDEX idx_cartella (cartella_id),
    INDEX idx_codice (codice),
    INDEX idx_tipo_iso (tipo_iso),
    INDEX idx_classificazione (classificazione),
    INDEX idx_stato (stato),
    FULLTEXT idx_ricerca (titolo, descrizione, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella versioni documenti ISO
CREATE TABLE IF NOT EXISTS versioni_documenti_iso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    versione INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    dimensione_file BIGINT,
    note_versione TEXT,
    creato_da INT NOT NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti_iso(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    UNIQUE KEY uk_documento_versione (documento_id, versione),
    INDEX idx_documento (documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella classificazioni ISO
CREATE TABLE IF NOT EXISTS classificazioni_iso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_iso VARCHAR(50) NOT NULL,
    codice VARCHAR(50) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    ordine INT DEFAULT 0,
    attivo BOOLEAN DEFAULT TRUE,
    UNIQUE KEY uk_tipo_codice (tipo_iso, codice),
    INDEX idx_tipo_iso (tipo_iso),
    INDEX idx_ordine (ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella impostazioni ISO per azienda
CREATE TABLE IF NOT EXISTS impostazioni_iso_azienda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL UNIQUE,
    modalita ENUM('integrato', 'separato') DEFAULT 'integrato',
    iso_9001_attivo BOOLEAN DEFAULT FALSE,
    iso_14001_attivo BOOLEAN DEFAULT FALSE,
    iso_45001_attivo BOOLEAN DEFAULT FALSE,
    iso_27001_attivo BOOLEAN DEFAULT FALSE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stored procedure per creare la struttura ISO
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS crea_struttura_iso(
    IN p_spazio_id INT,
    IN p_tipo_iso VARCHAR(50),
    IN p_modalita VARCHAR(50)
)
BEGIN
    DECLARE v_cartella_root_id INT;
    
    -- Crea cartella root per il tipo ISO
    INSERT INTO cartelle_iso (spazio_id, nome, descrizione, tipo_iso, icona, colore, ordine, protetta)
    VALUES (
        p_spazio_id,
        CASE p_tipo_iso
            WHEN 'ISO_9001' THEN 'ISO 9001:2015 - Sistema Gestione Qualità'
            WHEN 'ISO_14001' THEN 'ISO 14001:2015 - Sistema Gestione Ambientale'
            WHEN 'ISO_45001' THEN 'ISO 45001:2018 - Sistema Gestione Sicurezza'
            WHEN 'ISO_27001' THEN 'ISO 27001:2022 - Sistema Gestione Sicurezza Informazioni'
            ELSE 'Sistema di Gestione'
        END,
        CONCAT('Documentazione ', p_tipo_iso),
        p_tipo_iso,
        CASE p_tipo_iso
            WHEN 'ISO_9001' THEN 'fas fa-award'
            WHEN 'ISO_14001' THEN 'fas fa-leaf'
            WHEN 'ISO_45001' THEN 'fas fa-hard-hat'
            WHEN 'ISO_27001' THEN 'fas fa-shield-alt'
            ELSE 'fas fa-folder'
        END,
        CASE p_tipo_iso
            WHEN 'ISO_9001' THEN '#3b82f6'
            WHEN 'ISO_14001' THEN '#10b981'
            WHEN 'ISO_45001' THEN '#ef4444'
            WHEN 'ISO_27001' THEN '#8b5cf6'
            ELSE '#6b7280'
        END,
        CASE p_tipo_iso
            WHEN 'ISO_9001' THEN 1
            WHEN 'ISO_14001' THEN 2
            WHEN 'ISO_45001' THEN 3
            WHEN 'ISO_27001' THEN 4
            ELSE 5
        END,
        1
    );
    
    SET v_cartella_root_id = LAST_INSERT_ID();
    
    -- Crea sottocartelle standard basate sul tipo ISO
    IF p_tipo_iso = 'ISO_9001' THEN
        INSERT INTO cartelle_iso (spazio_id, parent_id, nome, tipo_iso, icona, colore, ordine) VALUES
        (p_spazio_id, v_cartella_root_id, '4. Contesto dell\'organizzazione', p_tipo_iso, 'fas fa-building', '#f59e0b', 1),
        (p_spazio_id, v_cartella_root_id, '5. Leadership', p_tipo_iso, 'fas fa-users-cog', '#ef4444', 2),
        (p_spazio_id, v_cartella_root_id, '6. Pianificazione', p_tipo_iso, 'fas fa-clipboard-list', '#8b5cf6', 3),
        (p_spazio_id, v_cartella_root_id, '7. Supporto', p_tipo_iso, 'fas fa-hands-helping', '#3b82f6', 4),
        (p_spazio_id, v_cartella_root_id, '8. Attività operative', p_tipo_iso, 'fas fa-cogs', '#10b981', 5),
        (p_spazio_id, v_cartella_root_id, '9. Valutazione prestazioni', p_tipo_iso, 'fas fa-chart-line', '#f59e0b', 6),
        (p_spazio_id, v_cartella_root_id, '10. Miglioramento', p_tipo_iso, 'fas fa-chart-area', '#ef4444', 7);
        
    ELSEIF p_tipo_iso = 'ISO_14001' THEN
        INSERT INTO cartelle_iso (spazio_id, parent_id, nome, tipo_iso, icona, colore, ordine) VALUES
        (p_spazio_id, v_cartella_root_id, '4. Contesto dell\'organizzazione', p_tipo_iso, 'fas fa-globe', '#10b981', 1),
        (p_spazio_id, v_cartella_root_id, '5. Leadership', p_tipo_iso, 'fas fa-user-tie', '#3b82f6', 2),
        (p_spazio_id, v_cartella_root_id, '6. Pianificazione', p_tipo_iso, 'fas fa-tasks', '#8b5cf6', 3),
        (p_spazio_id, v_cartella_root_id, '7. Supporto', p_tipo_iso, 'fas fa-life-ring', '#f59e0b', 4),
        (p_spazio_id, v_cartella_root_id, '8. Attività operative', p_tipo_iso, 'fas fa-industry', '#ef4444', 5),
        (p_spazio_id, v_cartella_root_id, '9. Valutazione prestazioni', p_tipo_iso, 'fas fa-tachometer-alt', '#10b981', 6),
        (p_spazio_id, v_cartella_root_id, '10. Miglioramento', p_tipo_iso, 'fas fa-seedling', '#3b82f6', 7);
        
    ELSEIF p_tipo_iso = 'ISO_45001' THEN
        INSERT INTO cartelle_iso (spazio_id, parent_id, nome, tipo_iso, icona, colore, ordine) VALUES
        (p_spazio_id, v_cartella_root_id, '4. Contesto dell\'organizzazione', p_tipo_iso, 'fas fa-hospital', '#ef4444', 1),
        (p_spazio_id, v_cartella_root_id, '5. Leadership e partecipazione', p_tipo_iso, 'fas fa-user-shield', '#f59e0b', 2),
        (p_spazio_id, v_cartella_root_id, '6. Pianificazione', p_tipo_iso, 'fas fa-clipboard-check', '#8b5cf6', 3),
        (p_spazio_id, v_cartella_root_id, '7. Supporto', p_tipo_iso, 'fas fa-first-aid', '#3b82f6', 4),
        (p_spazio_id, v_cartella_root_id, '8. Attività operative', p_tipo_iso, 'fas fa-hard-hat', '#10b981', 5),
        (p_spazio_id, v_cartella_root_id, '9. Valutazione prestazioni', p_tipo_iso, 'fas fa-clipboard-list', '#ef4444', 6),
        (p_spazio_id, v_cartella_root_id, '10. Miglioramento', p_tipo_iso, 'fas fa-shield-alt', '#f59e0b', 7);
        
    ELSEIF p_tipo_iso = 'ISO_27001' THEN
        INSERT INTO cartelle_iso (spazio_id, parent_id, nome, tipo_iso, icona, colore, ordine) VALUES
        (p_spazio_id, v_cartella_root_id, '4. Contesto dell\'organizzazione', p_tipo_iso, 'fas fa-network-wired', '#8b5cf6', 1),
        (p_spazio_id, v_cartella_root_id, '5. Leadership', p_tipo_iso, 'fas fa-user-lock', '#3b82f6', 2),
        (p_spazio_id, v_cartella_root_id, '6. Pianificazione', p_tipo_iso, 'fas fa-project-diagram', '#10b981', 3),
        (p_spazio_id, v_cartella_root_id, '7. Supporto', p_tipo_iso, 'fas fa-server', '#f59e0b', 4),
        (p_spazio_id, v_cartella_root_id, '8. Attività operative', p_tipo_iso, 'fas fa-lock', '#ef4444', 5),
        (p_spazio_id, v_cartella_root_id, '9. Valutazione prestazioni', p_tipo_iso, 'fas fa-chart-bar', '#8b5cf6', 6),
        (p_spazio_id, v_cartella_root_id, '10. Miglioramento', p_tipo_iso, 'fas fa-sync-alt', '#3b82f6', 7),
        (p_spazio_id, v_cartella_root_id, 'Annex A - Controlli', p_tipo_iso, 'fas fa-list-check', '#10b981', 8);
    END IF;
    
END$$

DELIMITER ;

-- Crea directory per upload se non esiste
-- NOTA: Questo deve essere eseguito manualmente o tramite PHP
-- mkdir -p uploads/iso_documents/super_admin
-- mkdir -p uploads/iso_documents/azienda_*