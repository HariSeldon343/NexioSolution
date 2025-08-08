-- Enhanced Filesystem Database Schema
-- This file contains all database tables and modifications needed for the enhanced filesystem with Windows Explorer interface

-- 1. Enhanced cartelle table with ISO support and metadata
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS tags JSON;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS is_iso_structure BOOLEAN DEFAULT FALSE;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS iso_standard VARCHAR(50);
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS access_permissions JSON;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS hidden BOOLEAN DEFAULT FALSE;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS stato ENUM('attiva', 'cestino', 'archiviata') DEFAULT 'attiva';
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS creato_da INT;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add foreign key for creator
ALTER TABLE cartelle ADD CONSTRAINT fk_cartelle_creato_da FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- 2. Enhanced documenti table with metadata and search features
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS file_metadata JSON;
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS hash_file VARCHAR(64);
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS virus_scan_status ENUM('pending', 'clean', 'infected', 'error') DEFAULT 'pending';
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS virus_scan_date TIMESTAMP NULL;
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(500);
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS preview_available BOOLEAN DEFAULT FALSE;
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS full_text_content LONGTEXT;
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS tags JSON;
ALTER TABLE documenti ADD COLUMN IF NOT EXISTS access_permissions JSON;

-- Add full-text search index
ALTER TABLE documenti ADD FULLTEXT INDEX idx_fulltext_search (titolo, descrizione, full_text_content);

-- 3. Document versions table for version control
CREATE TABLE IF NOT EXISTS documenti_versioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    versione INT NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    hash_file VARCHAR(64),
    mime_type VARCHAR(100),
    note_versione TEXT,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    UNIQUE KEY uk_doc_version (documento_id, versione),
    INDEX idx_documento_versioni (documento_id),
    INDEX idx_hash_versioni (hash_file)
) ENGINE=InnoDB;

-- 4. Document sharing table
CREATE TABLE IF NOT EXISTS documenti_condivisioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    condiviso_da INT NOT NULL,
    condiviso_con INT NULL, -- NULL for public links
    tipo_condivisione ENUM('utente', 'link_pubblico', 'link_scadenza') DEFAULT 'utente',
    permessi ENUM('lettura', 'scrittura', 'download') DEFAULT 'lettura',
    token_condivisione VARCHAR(64) UNIQUE,
    data_scadenza TIMESTAMP NULL,
    attiva BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (condiviso_da) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (condiviso_con) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_documento_condivisioni (documento_id),
    INDEX idx_token_condivisioni (token_condivisione),
    INDEX idx_condiviso_con (condiviso_con)
) ENGINE=InnoDB;

-- 5. Favorite files and folders
CREATE TABLE IF NOT EXISTS documenti_favorite (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    utente_id INT NOT NULL,
    data_aggiunta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY uk_doc_favorite (documento_id, utente_id),
    INDEX idx_utente_favorite_docs (utente_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cartelle_favorite (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cartella_id INT NOT NULL,
    utente_id INT NOT NULL,
    data_aggiunta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY uk_folder_favorite (cartella_id, utente_id),
    INDEX idx_utente_favorite_folders (utente_id)
) ENGINE=InnoDB;

-- 6. File access logs for GDPR compliance
CREATE TABLE IF NOT EXISTS file_access_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    utente_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    azione ENUM('view', 'download', 'share', 'delete', 'modify') NOT NULL,
    dettagli JSON,
    data_accesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_documento_access (documento_id),
    INDEX idx_utente_access (utente_id),
    INDEX idx_data_access (data_accesso),
    INDEX idx_azione_access (azione)
) ENGINE=InnoDB;

-- 7. Folder templates for ISO structures
CREATE TABLE IF NOT EXISTS folder_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    descrizione TEXT,
    struttura JSON NOT NULL, -- Hierarchical folder structure
    iso_standard VARCHAR(50),
    categoria ENUM('iso', 'custom', 'system') DEFAULT 'custom',
    attivo BOOLEAN DEFAULT TRUE,
    azienda_id INT NULL, -- NULL for system templates
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_template_azienda (azienda_id),
    INDEX idx_template_iso (iso_standard)
) ENGINE=InnoDB;

-- 8. Data retention policies for GDPR
CREATE TABLE IF NOT EXISTS data_retention_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    tipo_documento VARCHAR(100), -- File type or category
    periodo_conservazione INT NOT NULL, -- Days
    azione_scadenza ENUM('delete', 'archive', 'notify') DEFAULT 'notify',
    attiva BOOLEAN DEFAULT TRUE,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_retention_azienda (azienda_id),
    INDEX idx_retention_tipo (tipo_documento)
) ENGINE=InnoDB;

-- 9. GDPR consent tracking
CREATE TABLE IF NOT EXISTS gdpr_consent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    tipo_consenso VARCHAR(100) NOT NULL,
    consenso_dato BOOLEAN NOT NULL,
    data_consenso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    dettagli JSON,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_consent_utente (utente_id),
    INDEX idx_consent_tipo (tipo_consenso),
    INDEX idx_consent_data (data_consenso)
) ENGINE=InnoDB;

-- 10. Backup logs
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_backup ENUM('full', 'incremental', 'differential') NOT NULL,
    stato ENUM('in_corso', 'completato', 'fallito') NOT NULL,
    file_backup VARCHAR(500),
    dimensione_backup BIGINT,
    num_file_backup INT,
    data_inizio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_fine TIMESTAMP NULL,
    errori TEXT,
    azienda_id INT,
    avviato_da INT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (avviato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_backup_azienda (azienda_id),
    INDEX idx_backup_stato (stato),
    INDEX idx_backup_data (data_inizio)
) ENGINE=InnoDB;

-- 11. Search history for analytics
CREATE TABLE IF NOT EXISTS search_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT,
    query_ricerca VARCHAR(500) NOT NULL,
    filtri JSON,
    num_risultati INT,
    tempo_esecuzione DECIMAL(10,6),
    data_ricerca TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    azienda_id INT,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_search_utente (utente_id),
    INDEX idx_search_azienda (azienda_id),
    INDEX idx_search_data (data_ricerca)
) ENGINE=InnoDB;

-- 12. File thumbnails cache
CREATE TABLE IF NOT EXISTS file_thumbnails (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    thumbnail_path VARCHAR(500) NOT NULL,
    thumbnail_size VARCHAR(20), -- e.g., '200x200', '64x64'
    data_generazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    UNIQUE KEY uk_doc_thumb_size (documento_id, thumbnail_size),
    INDEX idx_thumbnail_documento (documento_id)
) ENGINE=InnoDB;

-- 13. System notifications for filesystem events
CREATE TABLE IF NOT EXISTS filesystem_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    tipo_notifica ENUM('quota_warning', 'virus_detected', 'backup_complete', 'share_received', 'file_expired') NOT NULL,
    messaggio TEXT NOT NULL,
    entita_tipo ENUM('documento', 'cartella', 'sistema'),
    entita_id INT,
    letta BOOLEAN DEFAULT FALSE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_scadenza TIMESTAMP NULL,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_notif_utente (utente_id),
    INDEX idx_notif_tipo (tipo_notifica),
    INDEX idx_notif_letta (letta),
    INDEX idx_notif_data (data_creazione)
) ENGINE=InnoDB;

-- 14. Enhanced indexes for performance
ALTER TABLE documenti ADD INDEX idx_hash_file (hash_file);
ALTER TABLE documenti ADD INDEX idx_virus_scan (virus_scan_status);
ALTER TABLE documenti ADD INDEX idx_file_size (file_size);
ALTER TABLE documenti ADD INDEX idx_mime_type (mime_type);
ALTER TABLE documenti ADD INDEX idx_tags (tags(100));

ALTER TABLE cartelle ADD INDEX idx_iso_structure (is_iso_structure);
ALTER TABLE cartelle ADD INDEX idx_iso_standard (iso_standard);
ALTER TABLE cartelle ADD INDEX idx_hidden (hidden);
ALTER TABLE cartelle ADD INDEX idx_stato (stato);

-- 15. Insert default folder templates
INSERT IGNORE INTO folder_templates (nome, descrizione, struttura, iso_standard, categoria, azienda_id) VALUES
('ISO 9001 Standard', 'Struttura cartelle standard per ISO 9001', 
 JSON_ARRAY(
   'Manuale_Sistema',
   'Politiche', 
   'Procedure',
   'Moduli_Registrazioni',
   'Audit',
   'Non_Conformità',
   'Azioni_Miglioramento',
   'Riesame_Direzione',
   'Formazione',
   'Gestione_Fornitori',
   'Indicatori_KPI'
 ), 'ISO9001', 'iso', NULL),

('ISO 14001 Standard', 'Struttura cartelle standard per ISO 14001',
 JSON_ARRAY(
   'Manuale_Ambientale',
   'Politica_Ambientale',
   'Procedure_Ambientali',
   'Registri_Ambientali',
   'Audit_Ambientali',
   'Non_Conformità_Ambientali',
   'Obiettivi_Ambientali',
   'Riesame_Ambientale',
   'Formazione_Ambientale'
 ), 'ISO14001', 'iso', NULL),

('ISO 45001 Standard', 'Struttura cartelle standard per ISO 45001',
 JSON_ARRAY(
   'Manuale_Sicurezza',
   'Politica_Sicurezza',
   'Procedure_Sicurezza',
   'Registri_Sicurezza',
   'Audit_Sicurezza',
   'Incidenti_Infortuni',
   'Valutazione_Rischi',
   'Formazione_Sicurezza',
   'DPI_Attrezzature'
 ), 'ISO45001', 'iso', NULL),

('GDPR Compliance', 'Struttura cartelle standard per conformità GDPR',
 JSON_ARRAY(
   'Registro_Trattamenti',
   'Privacy_Policy',
   'Informative_Privacy',
   'Consensi',
   'Data_Breach',
   'Valutazioni_Impatto',
   'Contratti_Fornitori',
   'Formazione_Privacy',
   'Audit_Privacy'
 ), 'GDPR', 'iso', NULL);

-- 16. Insert default data retention policies
INSERT IGNORE INTO data_retention_policies (azienda_id, tipo_documento, periodo_conservazione, azione_scadenza) VALUES
(1, 'temporary_files', 30, 'delete'),
(1, 'log_files', 365, 'archive'),
(1, 'backup_files', 2555, 'delete'), -- 7 years
(1, 'gdpr_data', 1095, 'notify'); -- 3 years

-- 17. Create triggers for automatic logging (GDPR compliance)
-- Note: Triggers will be created by separate script if needed

-- 18. Create views for common queries
CREATE OR REPLACE VIEW v_documenti_completi AS
SELECT 
    d.*,
    c.nome as cartella_nome,
    c.percorso_completo,
    u.nome as creato_da_nome,
    u.cognome as creato_da_cognome,
    COALESCE(fav.id, 0) as is_favorite,
    COALESCE(v.num_versions, 1) as num_versions,
    COALESCE(s.num_shares, 0) as num_shares
FROM documenti d
LEFT JOIN cartelle c ON c.id = d.cartella_id
LEFT JOIN utenti u ON u.id = d.creato_da
LEFT JOIN documenti_favorite fav ON fav.documento_id = d.id
LEFT JOIN (SELECT documento_id, COUNT(*) as num_versions FROM documenti_versioni GROUP BY documento_id) v ON v.documento_id = d.id
LEFT JOIN (SELECT documento_id, COUNT(*) as num_shares FROM documenti_condivisioni WHERE attiva = 1 GROUP BY documento_id) s ON s.documento_id = d.id;

CREATE OR REPLACE VIEW v_cartelle_complete AS
SELECT 
    c.*,
    COALESCE(sub.num_subfolders, 0) as num_subfolders,
    COALESCE(files.num_files, 0) as num_files,
    COALESCE(files.total_size, 0) as total_size,
    COALESCE(fav.id, 0) as is_favorite,
    u.nome as creato_da_nome,
    u.cognome as creato_da_cognome
FROM cartelle c
LEFT JOIN utenti u ON u.id = c.creato_da
LEFT JOIN cartelle_favorite fav ON fav.cartella_id = c.id
LEFT JOIN (SELECT parent_id, COUNT(*) as num_subfolders FROM cartelle WHERE stato != 'cestino' GROUP BY parent_id) sub ON sub.parent_id = c.id
LEFT JOIN (SELECT cartella_id, COUNT(*) as num_files, SUM(file_size) as total_size FROM documenti WHERE stato != 'cestino' GROUP BY cartella_id) files ON files.cartella_id = c.id;

-- 19. Performance optimization procedures
-- Note: Stored procedures will be created by separate script if needed

-- 20. Create events for automatic maintenance
-- Note: Events will be configured separately if needed

-- 21. Grant necessary permissions
-- Note: In production, create specific users with limited permissions

-- Final comment
-- Schema version: 2.0
-- Compatible with: Nexio Platform v1.0+
-- Features: ISO structures, GDPR compliance, Windows Explorer interface, full-text search
-- Last updated: January 2025