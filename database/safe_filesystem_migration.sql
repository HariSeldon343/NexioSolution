-- Migrazione sicura per filesystem avanzato
-- Basata sulla struttura esistente di NexioSol

-- ===============================================
-- STEP 1: Aggiungi colonne mancanti a CARTELLE
-- ===============================================

-- Nota: cartelle ha già 'descrizione' ma il sistema richiede 'description'
-- Creiamo un alias/view o aggiungiamo colonne secondo necessità

-- Aggiungi colonne mancanti a cartelle
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE AFTER access_permissions,
ADD COLUMN IF NOT EXISTS created_at_alt TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER data_creazione,
ADD COLUMN IF NOT EXISTS updated_at_alt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER data_aggiornamento,
ADD COLUMN IF NOT EXISTS created_by_alt INT AFTER creato_da,
ADD COLUMN IF NOT EXISTS last_modified_by INT AFTER aggiornato_da;

-- Aggiungi indici per nuove colonne
ALTER TABLE cartelle 
ADD INDEX IF NOT EXISTS idx_is_public (is_public),
ADD INDEX IF NOT EXISTS idx_created_at_alt (created_at_alt),
ADD INDEX IF NOT EXISTS idx_created_by_alt (created_by_alt),
ADD INDEX IF NOT EXISTS idx_last_modified_by (last_modified_by);

-- ===============================================
-- STEP 2: Aggiungi colonne mancanti a DOCUMENTI  
-- ===============================================

-- Nota: documenti ha già dimensione_bytes, ma il sistema richiede file_size
-- Nota: documenti ha già mime_type

ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS file_size_alt BIGINT AFTER dimensione_file,
ADD COLUMN IF NOT EXISTS description TEXT AFTER contenuto_html,
ADD COLUMN IF NOT EXISTS keywords TEXT AFTER tags,
ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE AFTER access_permissions,
ADD COLUMN IF NOT EXISTS download_count INT DEFAULT 0 AFTER preview_available,
ADD COLUMN IF NOT EXISTS last_accessed TIMESTAMP NULL AFTER data_aggiornamento,
ADD COLUMN IF NOT EXISTS created_at_alt TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER data_creazione,
ADD COLUMN IF NOT EXISTS updated_at_alt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER data_aggiornamento,
ADD COLUMN IF NOT EXISTS created_by_alt INT AFTER creato_da,
ADD COLUMN IF NOT EXISTS last_modified_by INT AFTER aggiornato_da;

-- Aggiungi indici per nuove colonne
ALTER TABLE documenti 
ADD INDEX IF NOT EXISTS idx_file_size_alt (file_size_alt),
ADD INDEX IF NOT EXISTS idx_keywords (keywords(255)),
ADD INDEX IF NOT EXISTS idx_is_public (is_public),
ADD INDEX IF NOT EXISTS idx_download_count (download_count),
ADD INDEX IF NOT EXISTS idx_last_accessed (last_accessed),
ADD INDEX IF NOT EXISTS idx_created_at_alt (created_at_alt),
ADD INDEX IF NOT EXISTS idx_created_by_alt (created_by_alt),
ADD INDEX IF NOT EXISTS idx_last_modified_by (last_modified_by);

-- ===============================================
-- STEP 3: Crea tabelle di supporto mancanti
-- ===============================================

-- Tabella per permessi documenti
CREATE TABLE IF NOT EXISTS document_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NULL,
    role VARCHAR(50) NULL,
    permission_type ENUM('read', 'write', 'delete', 'share') NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    azienda_id INT NOT NULL,
    INDEX idx_document_id (document_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_permission_type (permission_type),
    INDEX idx_azienda_id (azienda_id),
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per permessi cartelle
CREATE TABLE IF NOT EXISTS folder_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT NOT NULL,
    user_id INT NULL,
    role VARCHAR(50) NULL,
    permission_type ENUM('read', 'write', 'delete', 'share') NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    azienda_id INT NOT NULL,
    INDEX idx_folder_id (folder_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_permission_type (permission_type),
    INDEX idx_azienda_id (azienda_id),
    FOREIGN KEY (folder_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per tag documenti (più strutturata rispetto al campo JSON)
CREATE TABLE IF NOT EXISTS document_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    tag_name VARCHAR(100) NOT NULL,
    tag_color VARCHAR(7) DEFAULT '#007bff',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    azienda_id INT NOT NULL,
    INDEX idx_document_id (document_id),
    INDEX idx_tag_name (tag_name),
    INDEX idx_azienda_id (azienda_id),
    UNIQUE KEY unique_doc_tag (document_id, tag_name, azienda_id),
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella versioni documenti (se non esiste già)
CREATE TABLE IF NOT EXISTS document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    mime_type VARCHAR(100),
    hash_file VARCHAR(64),
    contenuto_html LONGTEXT,
    change_description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    azienda_id INT NOT NULL,
    INDEX idx_document_id (document_id),
    INDEX idx_version_number (version_number),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    INDEX idx_azienda_id (azienda_id),
    UNIQUE KEY unique_doc_version (document_id, version_number),
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per upload di file temporanei
CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    temp_filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    hash_file VARCHAR(64),
    upload_session VARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    processed BOOLEAN DEFAULT FALSE,
    azienda_id INT NOT NULL,
    INDEX idx_temp_filename (temp_filename),
    INDEX idx_upload_session (upload_session),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_expires_at (expires_at),
    INDEX idx_processed (processed),
    INDEX idx_azienda_id (azienda_id),
    FOREIGN KEY (uploaded_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per log attività (se non esiste già log_attivita)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    azienda_id INT NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_azienda_id (azienda_id),
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===============================================
-- STEP 4: Crea viste per compatibilità
-- ===============================================

-- Vista per mapping colonne cartelle
CREATE OR REPLACE VIEW folders_view AS
SELECT 
    id,
    nome as name,
    COALESCE(descrizione, '') as description,
    parent_id,
    percorso_completo as full_path,
    livello as level,
    icona as icon,
    colore as color,
    azienda_id as company_id,
    creato_da as created_by,
    COALESCE(created_by_alt, creato_da) as created_by_alt,
    data_creazione as created_at,
    COALESCE(created_at_alt, data_creazione) as created_at_alt,
    aggiornato_da as updated_by,
    COALESCE(last_modified_by, aggiornato_da) as last_modified_by,
    data_aggiornamento as updated_at,
    COALESCE(updated_at_alt, data_aggiornamento) as updated_at_alt,
    COALESCE(is_public, FALSE) as is_public,
    tags,
    is_iso_structure,
    iso_standard,
    access_permissions,
    hidden,
    stato as status,
    ultima_modifica as last_modified
FROM cartelle;

-- Vista per mapping colonne documenti
CREATE OR REPLACE VIEW documents_view AS
SELECT 
    id,
    titolo as title,
    contenuto as content,
    contenuto_html as content_html,
    COALESCE(description, '') as description,
    tipo_documento as document_type,
    stato as status,
    azienda_id as company_id,
    creato_da as created_by,
    COALESCE(created_by_alt, creato_da) as created_by_alt,
    data_creazione as created_at,
    COALESCE(created_at_alt, data_creazione) as created_at_alt,
    aggiornato_da as updated_by,
    COALESCE(last_modified_by, aggiornato_da) as last_modified_by,
    data_aggiornamento as updated_at,
    COALESCE(updated_at_alt, data_aggiornamento) as updated_at_alt,
    codice as code,
    classificazione_id as classification_id,
    cartella_id as folder_id,
    nome_file as filename,
    estensione as extension,
    COALESCE(file_size_alt, dimensione_bytes, dimensione_file) as file_size,
    mime_type,
    hash_file as file_hash,
    virus_scan_status,
    virus_scan_date,
    thumbnail_path,
    preview_available,
    full_text_content,
    tags,
    COALESCE(keywords, '') as keywords,
    COALESCE(is_public, FALSE) as is_public,
    COALESCE(download_count, 0) as download_count,
    last_accessed,
    access_permissions
FROM documenti;

-- ===============================================
-- STEP 5: Popola dati di compatibilità
-- ===============================================

-- Sincronizza file_size_alt con dimensione esistente
UPDATE documenti 
SET file_size_alt = COALESCE(dimensione_file, dimensione_bytes)
WHERE file_size_alt IS NULL 
  AND (dimensione_file IS NOT NULL OR dimensione_bytes IS NOT NULL);

-- Sincronizza created_by_alt
UPDATE cartelle 
SET created_by_alt = creato_da 
WHERE created_by_alt IS NULL AND creato_da IS NOT NULL;

UPDATE documenti 
SET created_by_alt = creato_da 
WHERE created_by_alt IS NULL AND creato_da IS NOT NULL;

-- Sincronizza last_modified_by
UPDATE cartelle 
SET last_modified_by = aggiornato_da 
WHERE last_modified_by IS NULL AND aggiornato_da IS NOT NULL;

UPDATE documenti 
SET last_modified_by = aggiornato_da 
WHERE last_modified_by IS NULL AND aggiornato_da IS NOT NULL;

-- ===============================================
-- STEP 6: Crea stored procedures per gestione
-- ===============================================

DELIMITER $$

-- Procedura per incrementare download count
CREATE OR REPLACE PROCEDURE increment_download_count(IN doc_id INT)
BEGIN
    UPDATE documenti 
    SET download_count = COALESCE(download_count, 0) + 1,
        last_accessed = CURRENT_TIMESTAMP
    WHERE id = doc_id;
END$$

-- Procedura per cleanup file temporanei scaduti
CREATE OR REPLACE PROCEDURE cleanup_expired_uploads()
BEGIN
    DELETE FROM file_uploads 
    WHERE expires_at IS NOT NULL 
      AND expires_at < CURRENT_TIMESTAMP 
      AND processed = FALSE;
END$$

DELIMITER ;

-- ===============================================
-- STEP 7: Crea triggers per mantenimento dati
-- ===============================================

-- Trigger per aggiornare timestamp alternativi
DELIMITER $$

CREATE OR REPLACE TRIGGER tr_cartelle_update_timestamps
    BEFORE UPDATE ON cartelle
    FOR EACH ROW
BEGIN
    SET NEW.updated_at_alt = CURRENT_TIMESTAMP;
    IF NEW.aggiornato_da IS NOT NULL THEN
        SET NEW.last_modified_by = NEW.aggiornato_da;
    END IF;
END$$

CREATE OR REPLACE TRIGGER tr_documenti_update_timestamps
    BEFORE UPDATE ON documenti
    FOR EACH ROW
BEGIN
    SET NEW.updated_at_alt = CURRENT_TIMESTAMP;
    IF NEW.aggiornato_da IS NOT NULL THEN
        SET NEW.last_modified_by = NEW.aggiornato_da;
    END IF;
    IF NEW.dimensione_file IS NOT NULL OR NEW.dimensione_bytes IS NOT NULL THEN
        SET NEW.file_size_alt = COALESCE(NEW.dimensione_file, NEW.dimensione_bytes);
    END IF;
END$$

DELIMITER ;

-- Commit della transazione
COMMIT;