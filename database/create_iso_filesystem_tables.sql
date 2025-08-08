-- ISO Filesystem Tables
-- Creates all necessary tables for filesystem.php functionality

-- Company Document Schemas Table (for schema selection)
CREATE TABLE IF NOT EXISTS company_document_schemas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    schema_type VARCHAR(50) NOT NULL,
    schema_config JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company_schema (azienda_id, schema_type),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id),
    INDEX idx_active (azienda_id, is_active),
    INDEX idx_schema_type (schema_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns to cartelle table if they don't exist
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS livello INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS colore VARCHAR(7) DEFAULT '#fbbf24',
ADD COLUMN IF NOT EXISTS iso_template_id INT,
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(20),
ADD COLUMN IF NOT EXISTS iso_compliance_level ENUM('obbligatoria', 'raccomandata', 'opzionale', 'personalizzata'),
ADD COLUMN IF NOT EXISTS iso_metadata JSON,
ADD COLUMN IF NOT EXISTS data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS creato_da INT,
ADD COLUMN IF NOT EXISTS aggiornato_da INT,
ADD INDEX IF NOT EXISTS idx_iso_template (iso_template_id),
ADD INDEX IF NOT EXISTS idx_iso_standard (iso_standard_codice),
ADD INDEX IF NOT EXISTS idx_livello (livello),
ADD INDEX IF NOT EXISTS idx_azienda_parent (azienda_id, parent_id);

-- Add missing columns to documenti table if they don't exist
ALTER TABLE documenti
ADD COLUMN IF NOT EXISTS formato VARCHAR(10),
ADD COLUMN IF NOT EXISTS dimensione_file BIGINT,
ADD COLUMN IF NOT EXISTS tipo_documento VARCHAR(50),
ADD COLUMN IF NOT EXISTS data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS creato_da INT,
ADD COLUMN IF NOT EXISTS aggiornato_da INT,
ADD INDEX IF NOT EXISTS idx_cartella (cartella_id),
ADD INDEX IF NOT EXISTS idx_stato (stato),
ADD INDEX IF NOT EXISTS idx_azienda_cartella (azienda_id, cartella_id);

-- Ensure iso_company_configurations exists (from create_iso_compliance_system.sql)
-- This table is already created in the ISO compliance system

-- Ensure iso_standards exists (from create_iso_system_fixed.sql)
-- This table is already created in the ISO system

-- Ensure iso_folder_templates exists (from create_iso_system_fixed.sql)
-- This table is already created in the ISO system

-- Create or update documenti directory upload paths
CREATE TABLE IF NOT EXISTS document_upload_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    upload_path VARCHAR(500) NOT NULL,
    max_file_size BIGINT DEFAULT 52428800, -- 50MB default
    allowed_extensions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_azienda (azienda_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default upload configurations
INSERT INTO document_upload_config (azienda_id, upload_path, allowed_extensions)
SELECT 
    a.id,
    CONCAT('uploads/documenti/azienda_', a.id, '/'),
    JSON_ARRAY('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png')
FROM aziende a
WHERE NOT EXISTS (
    SELECT 1 FROM document_upload_config 
    WHERE azienda_id = a.id
);

-- Create views for document management
CREATE OR REPLACE VIEW v_documenti_completi AS
SELECT 
    d.*,
    c.nome AS cartella_nome,
    c.percorso_completo AS cartella_percorso,
    c.iso_standard_codice,
    u1.nome AS creato_da_nome,
    u1.cognome AS creato_da_cognome,
    u2.nome AS aggiornato_da_nome,
    u2.cognome AS aggiornato_da_cognome,
    a.nome AS azienda_nome
FROM documenti d
LEFT JOIN cartelle c ON d.cartella_id = c.id
LEFT JOIN utenti u1 ON d.creato_da = u1.id
LEFT JOIN utenti u2 ON d.aggiornato_da = u2.id
LEFT JOIN aziende a ON d.azienda_id = a.id;

-- Create view for folder hierarchy
CREATE OR REPLACE VIEW v_cartelle_gerarchia AS
WITH RECURSIVE folder_hierarchy AS (
    -- Base case: root folders
    SELECT 
        id,
        nome,
        parent_id,
        percorso_completo,
        azienda_id,
        livello,
        iso_standard_codice,
        CAST(id AS CHAR(1000)) AS path_ids,
        nome AS full_path
    FROM cartelle
    WHERE parent_id IS NULL
    
    UNION ALL
    
    -- Recursive case
    SELECT 
        c.id,
        c.nome,
        c.parent_id,
        c.percorso_completo,
        c.azienda_id,
        c.livello,
        c.iso_standard_codice,
        CONCAT(fh.path_ids, '/', c.id) AS path_ids,
        CONCAT(fh.full_path, ' / ', c.nome) AS full_path
    FROM cartelle c
    INNER JOIN folder_hierarchy fh ON c.parent_id = fh.id
)
SELECT * FROM folder_hierarchy;

-- Add triggers for automatic timestamp updates
DELIMITER //

CREATE TRIGGER IF NOT EXISTS documenti_update_timestamp
BEFORE UPDATE ON documenti
FOR EACH ROW
BEGIN
    SET NEW.data_modifica = CURRENT_TIMESTAMP;
END//

CREATE TRIGGER IF NOT EXISTS cartelle_update_timestamp
BEFORE UPDATE ON cartelle
FOR EACH ROW
BEGIN
    SET NEW.data_aggiornamento = CURRENT_TIMESTAMP;
END//

-- Trigger to update folder path when parent changes
CREATE TRIGGER IF NOT EXISTS cartelle_update_path
AFTER UPDATE ON cartelle
FOR EACH ROW
BEGIN
    IF OLD.parent_id != NEW.parent_id OR OLD.nome != NEW.nome THEN
        -- Update will be handled by the PHP application
        -- This is just a placeholder for documentation
        SELECT 1;
    END IF;
END//

DELIMITER ;

-- Grant necessary permissions (adjust user as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON NexioSol.* TO 'nexio_user'@'localhost';

-- Final setup message
SELECT 'ISO Filesystem tables created/updated successfully' AS status;