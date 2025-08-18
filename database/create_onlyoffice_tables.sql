-- ====================================================
-- OnlyOffice Integration Tables
-- Complete database schema for OnlyOffice integration
-- ====================================================

USE nexiosol;

-- ====================================================
-- 1. Update documenti table with OnlyOffice columns
-- ====================================================

-- Add missing columns to documenti table if they don't exist
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS nome_file VARCHAR(255) DEFAULT NULL COMMENT 'Nome del file',
ADD COLUMN IF NOT EXISTS creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data creazione',
ADD COLUMN IF NOT EXISTS aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data ultimo aggiornamento',
ADD COLUMN IF NOT EXISTS ultimo_accesso TIMESTAMP NULL DEFAULT NULL COMMENT 'Data ultimo accesso',
ADD COLUMN IF NOT EXISTS is_editing BOOLEAN DEFAULT FALSE COMMENT 'Flag editing in corso',
ADD COLUMN IF NOT EXISTS editing_users JSON DEFAULT NULL COMMENT 'Utenti in editing',
ADD COLUMN IF NOT EXISTS editing_started_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Inizio editing',
ADD COLUMN IF NOT EXISTS current_version INT DEFAULT 1 COMMENT 'Versione corrente',
ADD COLUMN IF NOT EXISTS total_versions INT DEFAULT 1 COMMENT 'Totale versioni',
ADD COLUMN IF NOT EXISTS last_error TEXT DEFAULT NULL COMMENT 'Ultimo errore',
ADD COLUMN IF NOT EXISTS last_error_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp ultimo errore',
ADD COLUMN IF NOT EXISTS onlyoffice_key VARCHAR(255) DEFAULT NULL COMMENT 'Chiave documento OnlyOffice',
ADD COLUMN IF NOT EXISTS onlyoffice_url TEXT DEFAULT NULL COMMENT 'URL OnlyOffice',
ADD INDEX idx_onlyoffice_key (onlyoffice_key),
ADD INDEX idx_is_editing (is_editing),
ADD INDEX idx_ultimo_accesso (ultimo_accesso);

-- ====================================================
-- 2. Extended version management table
-- ====================================================

CREATE TABLE IF NOT EXISTS documenti_versioni_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path TEXT,
    file_size BIGINT DEFAULT 0,
    content_html LONGTEXT COMMENT 'HTML content for text documents',
    created_by_id INT,
    created_by_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_major BOOLEAN DEFAULT FALSE COMMENT 'Major version flag',
    is_current BOOLEAN DEFAULT FALSE COMMENT 'Current version flag',
    notes TEXT COMMENT 'Version notes',
    changes_data JSON COMMENT 'Changes history from OnlyOffice',
    hash VARCHAR(64) COMMENT 'File hash for integrity',
    metadata JSON COMMENT 'Additional metadata',
    
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_id) REFERENCES utenti(id) ON DELETE SET NULL,
    
    INDEX idx_documento_version (documento_id, version_number),
    INDEX idx_current (documento_id, is_current),
    INDEX idx_created_at (created_at),
    UNIQUE KEY unique_doc_version (documento_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Extended version management for OnlyOffice documents';

-- ====================================================
-- 3. Document activity log
-- ====================================================

CREATE TABLE IF NOT EXISTS document_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'Action type',
    details JSON COMMENT 'Action details',
    user_id INT,
    user_name VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    
    INDEX idx_document_action (document_id, action),
    INDEX idx_created_at (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detailed activity log for documents';

-- ====================================================
-- 4. Active editors tracking
-- ====================================================

CREATE TABLE IF NOT EXISTS document_active_editors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255),
    session_id VARCHAR(255),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    connection_id VARCHAR(255) COMMENT 'OnlyOffice connection ID',
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_editor (document_id, user_id),
    INDEX idx_document (document_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track active document editors';

-- ====================================================
-- 5. Collaborative actions log
-- ====================================================

CREATE TABLE IF NOT EXISTS document_collaborative_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    user_id INT,
    action_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    
    INDEX idx_document_type (document_id, action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log collaborative editing actions';

-- ====================================================
-- 6. OnlyOffice sessions
-- ====================================================

CREATE TABLE IF NOT EXISTS onlyoffice_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    session_key VARCHAR(255) NOT NULL COMMENT 'Unique session key',
    user_id INT NOT NULL,
    azienda_id INT NOT NULL,
    permissions JSON COMMENT 'User permissions for this session',
    jwt_token TEXT COMMENT 'JWT token for this session',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_session (session_key),
    INDEX idx_document_user (document_id, user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='OnlyOffice editing sessions';

-- ====================================================
-- 7. Document locks for conflict prevention
-- ====================================================

CREATE TABLE IF NOT EXISTS document_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    locked_by INT NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lock_type ENUM('read', 'write', 'exclusive') DEFAULT 'write',
    lock_reason VARCHAR(255),
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (locked_by) REFERENCES utenti(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_lock (document_id, lock_type),
    INDEX idx_expires (expires_at),
    INDEX idx_locked_by (locked_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Document locking mechanism';

-- ====================================================
-- 8. OnlyOffice callback history
-- ====================================================

CREATE TABLE IF NOT EXISTS onlyoffice_callbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT,
    callback_key VARCHAR(255),
    status INT COMMENT 'OnlyOffice status code',
    url TEXT COMMENT 'Document URL from callback',
    changes_url TEXT COMMENT 'Changes URL from callback',
    force_save_type INT COMMENT 'Force save type',
    users JSON COMMENT 'Users data from callback',
    actions JSON COMMENT 'Actions data from callback',
    history JSON COMMENT 'History data from callback',
    raw_data JSON COMMENT 'Complete raw callback data',
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE SET NULL,
    
    INDEX idx_callback_key (callback_key),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='OnlyOffice callback history for debugging';

-- ====================================================
-- 9. Document templates for OnlyOffice
-- ====================================================

CREATE TABLE IF NOT EXISTS onlyoffice_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    file_path TEXT NOT NULL,
    file_type VARCHAR(50) NOT NULL COMMENT 'docx, xlsx, pptx',
    category VARCHAR(100),
    azienda_id INT COMMENT 'NULL for global templates',
    is_active BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL,
    
    INDEX idx_category (category),
    INDEX idx_azienda (azienda_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Document templates for OnlyOffice';

-- ====================================================
-- 10. Create views for reporting
-- ====================================================

-- View for active editing sessions
CREATE OR REPLACE VIEW v_active_editing_sessions AS
SELECT 
    d.id as document_id,
    d.titolo as document_title,
    d.azienda_id,
    a.nome as azienda_nome,
    de.user_id,
    u.nome as user_name,
    de.started_at,
    de.last_activity,
    TIMESTAMPDIFF(MINUTE, de.started_at, NOW()) as editing_minutes
FROM document_active_editors de
JOIN documenti d ON de.document_id = d.id
LEFT JOIN aziende a ON d.azienda_id = a.id
LEFT JOIN utenti u ON de.user_id = u.id
WHERE de.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE);

-- View for document version history
CREATE OR REPLACE VIEW v_document_version_history AS
SELECT 
    dv.id as version_id,
    dv.documento_id,
    d.titolo as document_title,
    dv.version_number,
    dv.created_by_name,
    dv.created_at,
    dv.is_major,
    dv.is_current,
    dv.file_size,
    dv.notes,
    d.azienda_id
FROM documenti_versioni_extended dv
JOIN documenti d ON dv.documento_id = d.id
ORDER BY dv.documento_id, dv.version_number DESC;

-- View for document activity summary
CREATE OR REPLACE VIEW v_document_activity_summary AS
SELECT 
    document_id,
    COUNT(*) as total_actions,
    COUNT(DISTINCT user_id) as unique_users,
    MIN(created_at) as first_activity,
    MAX(created_at) as last_activity,
    COUNT(CASE WHEN action = 'document_saved' THEN 1 END) as save_count,
    COUNT(CASE WHEN action = 'document_opened' THEN 1 END) as open_count,
    COUNT(CASE WHEN action = 'save_error' THEN 1 END) as error_count
FROM document_activity_log
GROUP BY document_id;

-- ====================================================
-- 11. Insert default OnlyOffice templates
-- ====================================================

INSERT IGNORE INTO onlyoffice_templates (name, description, file_type, category, is_active) VALUES
('Documento Vuoto', 'Documento Word vuoto', 'docx', 'general', TRUE),
('Foglio di Calcolo Vuoto', 'Foglio Excel vuoto', 'xlsx', 'general', TRUE),
('Presentazione Vuota', 'Presentazione PowerPoint vuota', 'pptx', 'general', TRUE),
('Lettera Commerciale', 'Template per lettera commerciale', 'docx', 'business', TRUE),
('Report Mensile', 'Template per report mensile', 'docx', 'reports', TRUE),
('Budget Annuale', 'Template per budget annuale', 'xlsx', 'finance', TRUE),
('Presentazione Aziendale', 'Template presentazione aziendale', 'pptx', 'business', TRUE);

-- ====================================================
-- 12. Grant permissions
-- ====================================================

-- Ensure proper permissions for application user
-- GRANT ALL PRIVILEGES ON nexiosol.* TO 'nexio_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ====================================================
-- 13. Create triggers for data integrity
-- ====================================================

DELIMITER $$

-- Trigger to clean up active editors on document deletion
CREATE TRIGGER IF NOT EXISTS before_document_delete
BEFORE DELETE ON documenti
FOR EACH ROW
BEGIN
    DELETE FROM document_active_editors WHERE document_id = OLD.id;
    DELETE FROM document_locks WHERE document_id = OLD.id;
    DELETE FROM onlyoffice_sessions WHERE document_id = OLD.id;
END$$

-- Trigger to update document stats after version creation
CREATE TRIGGER IF NOT EXISTS after_version_insert
AFTER INSERT ON documenti_versioni_extended
FOR EACH ROW
BEGIN
    UPDATE documenti 
    SET total_versions = (
        SELECT COUNT(*) FROM documenti_versioni_extended 
        WHERE documento_id = NEW.documento_id
    ),
    current_version = NEW.version_number,
    aggiornato_il = NOW()
    WHERE id = NEW.documento_id;
END$$

-- Trigger to clean up expired sessions
CREATE EVENT IF NOT EXISTS cleanup_expired_sessions
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM onlyoffice_sessions 
    WHERE expires_at < NOW() OR (is_active = FALSE AND last_accessed < DATE_SUB(NOW(), INTERVAL 24 HOUR));
    
    DELETE FROM document_locks 
    WHERE expires_at < NOW();
    
    DELETE FROM document_active_editors 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 HOUR);
END$$

DELIMITER ;

-- ====================================================
-- 14. Add indexes for performance
-- ====================================================

-- Performance indexes for frequent queries
ALTER TABLE document_activity_log ADD INDEX idx_document_created (document_id, created_at);
ALTER TABLE documenti_versioni_extended ADD INDEX idx_doc_current (documento_id, is_current);
ALTER TABLE onlyoffice_callbacks ADD INDEX idx_doc_status (document_id, status);

-- ====================================================
-- Verification queries
-- ====================================================

-- Check if all tables were created
SELECT 
    'Tables Created' as Status,
    COUNT(*) as Count
FROM information_schema.tables 
WHERE table_schema = 'nexiosol' 
AND table_name IN (
    'documenti_versioni_extended',
    'document_activity_log',
    'document_active_editors',
    'document_collaborative_actions',
    'onlyoffice_sessions',
    'document_locks',
    'onlyoffice_callbacks',
    'onlyoffice_templates'
);

-- Check documenti table columns
SELECT 
    'Documenti Columns' as Status,
    COUNT(*) as Count
FROM information_schema.columns
WHERE table_schema = 'nexiosol'
AND table_name = 'documenti'
AND column_name IN (
    'nome_file', 'creato_il', 'aggiornato_il', 'ultimo_accesso',
    'is_editing', 'editing_users', 'current_version', 'total_versions'
);

-- Show summary
SELECT 'OnlyOffice tables setup complete!' as Message;