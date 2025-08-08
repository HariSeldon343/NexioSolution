-- Enhanced Filesystem Database Schema for Nexio Platform
-- Supports advanced file operations, GDPR compliance, and performance optimization

-- Enhanced files table with metadata and GDPR support
CREATE TABLE IF NOT EXISTS files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    nome_originale VARCHAR(255) NOT NULL,
    percorso VARCHAR(1000) NOT NULL,
    dimensione BIGINT NOT NULL DEFAULT 0,
    tipo_mime VARCHAR(100) NOT NULL,
    estensione VARCHAR(10) NOT NULL,
    hash_file VARCHAR(64) NOT NULL, -- SHA-256 for deduplication
    cartella_id INT,
    azienda_id INT NOT NULL,
    creato_da INT NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    accesso_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    versione INT DEFAULT 1,
    stato ENUM('attivo', 'cancellato', 'archiviato') DEFAULT 'attivo',
    descrizione TEXT,
    tags JSON,
    metadati JSON, -- File metadata (EXIF, document properties, etc.)
    thumbnail_path VARCHAR(500),
    preview_path VARCHAR(500),
    contenuto_indicizzato LONGTEXT, -- Extracted text for search
    gdpr_consenso BOOLEAN DEFAULT FALSE,
    gdpr_data_scadenza DATE,
    gdpr_motivo_conservazione VARCHAR(255),
    INDEX idx_cartella_azienda (cartella_id, azienda_id),
    INDEX idx_hash (hash_file),
    INDEX idx_tipo_mime (tipo_mime),
    INDEX idx_creato_da (creato_da),
    INDEX idx_gdpr_scadenza (gdpr_data_scadenza),
    FULLTEXT idx_contenuto_search (nome, descrizione, contenuto_indicizzato),
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Enhanced cartelle table with additional metadata
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS descrizione TEXT,
ADD COLUMN IF NOT EXISTS icona VARCHAR(50) DEFAULT 'folder',
ADD COLUMN IF NOT EXISTS colore VARCHAR(7) DEFAULT '#6c757d',
ADD COLUMN IF NOT EXISTS creato_da INT,
ADD COLUMN IF NOT EXISTS creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS modificato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS dimensione_totale BIGINT DEFAULT 0,
ADD COLUMN IF NOT EXISTS conteggio_file INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS stato ENUM('attiva', 'nascosta', 'archiviata') DEFAULT 'attiva',
ADD COLUMN IF NOT EXISTS permessi JSON, -- Custom permissions
ADD INDEX IF NOT EXISTS idx_parent_azienda (parent_id, azienda_id),
ADD INDEX IF NOT EXISTS idx_percorso (percorso_completo(255)),
ADD FOREIGN KEY IF NOT EXISTS fk_cartelle_creato_da (creato_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- File versions for version control
CREATE TABLE IF NOT EXISTS file_versioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    versione INT NOT NULL,
    percorso VARCHAR(1000) NOT NULL,
    dimensione BIGINT NOT NULL,
    hash_file VARCHAR(64) NOT NULL,
    creato_da INT NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note_versione TEXT,
    INDEX idx_file_versione (file_id, versione),
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE RESTRICT,
    UNIQUE KEY uk_file_versione (file_id, versione)
) ENGINE=InnoDB;

-- File access logs for GDPR compliance
CREATE TABLE IF NOT EXISTS file_access_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    utente_id INT NOT NULL,
    azienda_id INT NOT NULL,
    azione ENUM('visualizzazione', 'download', 'modifica', 'eliminazione', 'condivisione') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_accesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dettagli JSON,
    INDEX idx_file_data (file_id, data_accesso),
    INDEX idx_utente_data (utente_id, data_accesso),
    INDEX idx_azienda_data (azienda_id, data_accesso),
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- File sharing and permissions
CREATE TABLE IF NOT EXISTS file_condivisioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    condiviso_da INT NOT NULL,
    condiviso_con INT,
    email_esterno VARCHAR(255),
    tipo_condivisione ENUM('lettura', 'scrittura', 'eliminazione', 'completo') DEFAULT 'lettura',
    scadenza DATETIME,
    token_accesso VARCHAR(64) UNIQUE,
    password_accesso VARCHAR(255),
    numero_accessi INT DEFAULT 0,
    max_accessi INT,
    attivo BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_condivisione (file_id),
    INDEX idx_token (token_accesso),
    INDEX idx_scadenza (scadenza),
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (condiviso_da) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (condiviso_con) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Search index optimization
CREATE TABLE IF NOT EXISTS file_search_index (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    termine VARCHAR(100) NOT NULL,
    frequenza INT DEFAULT 1,
    posizione JSON, -- Positions where term appears
    INDEX idx_termine (termine),
    INDEX idx_file_termine (file_id, termine),
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY uk_file_termine (file_id, termine)
) ENGINE=InnoDB;

-- File cache for performance
CREATE TABLE IF NOT EXISTS file_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    tipo_cache ENUM('thumbnail', 'preview', 'metadata', 'content') NOT NULL,
    percorso_cache VARCHAR(500) NOT NULL,
    dimensione BIGINT NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accesso_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scadenza DATETIME,
    INDEX idx_file_tipo (file_id, tipo_cache),
    INDEX idx_scadenza (scadenza),
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY uk_file_tipo_cache (file_id, tipo_cache)
) ENGINE=InnoDB;

-- GDPR data retention policies
CREATE TABLE IF NOT EXISTS gdpr_retention_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    tipo_documento VARCHAR(100) NOT NULL,
    periodo_conservazione INT NOT NULL, -- In months
    motivo_legale VARCHAR(255) NOT NULL,
    auto_eliminazione BOOLEAN DEFAULT FALSE,
    attivo BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY uk_azienda_tipo (azienda_id, tipo_documento)
) ENGINE=InnoDB;

-- GDPR consent tracking
CREATE TABLE IF NOT EXISTS gdpr_consensi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    utente_id INT NOT NULL,
    tipo_consenso ENUM('trattamento', 'conservazione', 'condivisione', 'profilazione') NOT NULL,
    consenso_dato BOOLEAN NOT NULL,
    data_consenso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scadenza_consenso DATETIME,
    revocato BOOLEAN DEFAULT FALSE,
    data_revoca TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_file_consenso (file_id, tipo_consenso),
    INDEX idx_scadenza (scadenza_consenso)
) ENGINE=InnoDB;

-- File quarantine for security
CREATE TABLE IF NOT EXISTS file_quarantine (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    motivo ENUM('virus', 'malware', 'contenuto_inappropriato', 'violazione_policy') NOT NULL,
    dettagli TEXT,
    quarantena_da INT NOT NULL,
    data_quarantena TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rilasciato BOOLEAN DEFAULT FALSE,
    data_rilascio TIMESTAMP NULL,
    rilasciato_da INT,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (quarantena_da) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (rilasciato_da) REFERENCES utenti(id) ON DELETE RESTRICT,
    INDEX idx_file_quarantena (file_id),
    INDEX idx_rilasciato (rilasciato)
) ENGINE=InnoDB;

-- Triggers for automatic folder size calculation
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_files_insert_size 
AFTER INSERT ON files
FOR EACH ROW
BEGIN
    IF NEW.cartella_id IS NOT NULL THEN
        UPDATE cartelle SET 
            dimensione_totale = dimensione_totale + NEW.dimensione,
            conteggio_file = conteggio_file + 1,
            modificato_il = CURRENT_TIMESTAMP
        WHERE id = NEW.cartella_id;
        
        -- Update parent folders recursively
        UPDATE cartelle c1 
        JOIN cartelle c2 ON FIND_IN_SET(c1.id, REPLACE(c2.percorso_completo, '/', ','))
        SET c1.dimensione_totale = c1.dimensione_totale + NEW.dimensione,
            c1.modificato_il = CURRENT_TIMESTAMP
        WHERE c2.id = NEW.cartella_id AND c1.id != NEW.cartella_id;
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS tr_files_delete_size 
AFTER DELETE ON files
FOR EACH ROW
BEGIN
    IF OLD.cartella_id IS NOT NULL THEN
        UPDATE cartelle SET 
            dimensione_totale = GREATEST(0, dimensione_totale - OLD.dimensione),
            conteggio_file = GREATEST(0, conteggio_file - 1),
            modificato_il = CURRENT_TIMESTAMP
        WHERE id = OLD.cartella_id;
        
        -- Update parent folders recursively
        UPDATE cartelle c1 
        JOIN cartelle c2 ON FIND_IN_SET(c1.id, REPLACE(c2.percorso_completo, '/', ','))
        SET c1.dimensione_totale = GREATEST(0, c1.dimensione_totale - OLD.dimensione),
            c1.modificato_il = CURRENT_TIMESTAMP
        WHERE c2.id = OLD.cartella_id AND c1.id != OLD.cartella_id;
    END IF;
END$$

DELIMITER ;

-- Insert default GDPR retention policies
INSERT IGNORE INTO gdpr_retention_policies (azienda_id, tipo_documento, periodo_conservazione, motivo_legale, auto_eliminazione) 
SELECT 
    id as azienda_id,
    'documenti_generali' as tipo_documento,
    60 as periodo_conservazione, -- 5 years
    'Conservazione per finalità amministrative e legali' as motivo_legale,
    FALSE as auto_eliminazione
FROM aziende;

-- Create indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_files_compound ON files (azienda_id, cartella_id, stato, creato_il);
CREATE INDEX IF NOT EXISTS idx_files_search ON files (azienda_id, tipo_mime, dimensione);
CREATE INDEX IF NOT EXISTS idx_cartelle_hierarchy ON cartelle (azienda_id, parent_id, nome);

-- Create view for file hierarchy with permissions
CREATE OR REPLACE VIEW v_file_hierarchy AS
SELECT 
    f.id,
    f.nome,
    f.percorso,
    f.dimensione,
    f.tipo_mime,
    f.creato_il,
    f.modificato_il,
    f.cartella_id,
    f.azienda_id,
    c.nome as cartella_nome,
    c.percorso_completo as cartella_percorso,
    u.nome as creato_da_nome,
    u.cognome as creato_da_cognome,
    CASE 
        WHEN f.gdpr_data_scadenza IS NOT NULL AND f.gdpr_data_scadenza < CURDATE() THEN TRUE
        ELSE FALSE
    END as gdpr_scaduto
FROM files f
LEFT JOIN cartelle c ON f.cartella_id = c.id
LEFT JOIN utenti u ON f.creato_da = u.id
WHERE f.stato = 'attivo';