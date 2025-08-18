-- ================================================================
-- OnlyOffice Integration Tables for Nexio Platform
-- ================================================================
-- This script creates all necessary tables for OnlyOffice integration
-- including document versioning, collaborative editing tracking,
-- and security audit logs
-- ================================================================

-- Drop existing tables if needed (comment out in production)
-- DROP TABLE IF EXISTS document_collaborative_actions;
-- DROP TABLE IF EXISTS document_active_editors;
-- DROP TABLE IF EXISTS document_activity_log;
-- DROP TABLE IF EXISTS documenti_versioni_extended;
-- DROP TABLE IF EXISTS onlyoffice_sessions;
-- DROP TABLE IF EXISTS onlyoffice_rate_limits;
-- DROP TABLE IF EXISTS onlyoffice_security_log;

-- ================================================================
-- Extended Document Versions Table
-- ================================================================
CREATE TABLE IF NOT EXISTS documenti_versioni_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT DEFAULT 0,
    content_hash VARCHAR(64),
    created_by_id INT,
    created_by_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_major BOOLEAN DEFAULT FALSE,
    is_current BOOLEAN DEFAULT FALSE,
    notes TEXT,
    changes_data JSON,
    metadata JSON,
    
    -- Indexes
    INDEX idx_documento_version (documento_id, version_number),
    INDEX idx_current_version (documento_id, is_current),
    INDEX idx_created_at (created_at),
    
    -- Foreign key
    CONSTRAINT fk_version_documento 
        FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_version_user 
        FOREIGN KEY (created_by_id) REFERENCES utenti(id) ON DELETE SET NULL,
    
    -- Unique constraint
    UNIQUE KEY uk_documento_version (documento_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Active Document Editors Table
-- ================================================================
CREATE TABLE IF NOT EXISTS document_active_editors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT,
    user_name VARCHAR(255),
    session_id VARCHAR(255),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    connection_id VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Indexes
    INDEX idx_document_active (document_id, is_active),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_session (session_id),
    INDEX idx_last_activity (last_activity),
    
    -- Foreign keys
    CONSTRAINT fk_editor_document 
        FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_editor_user 
        FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    
    -- Unique constraint for active sessions
    UNIQUE KEY uk_active_session (document_id, user_id, session_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Collaborative Actions Log Table
-- ================================================================
CREATE TABLE IF NOT EXISTS document_collaborative_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    user_id INT,
    user_name VARCHAR(255),
    action_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_document_actions (document_id, created_at),
    INDEX idx_action_type (action_type),
    INDEX idx_user_actions (user_id, created_at),
    INDEX idx_created_at_desc (created_at DESC),
    
    -- Foreign keys
    CONSTRAINT fk_action_document 
        FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_action_user 
        FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Document Activity Log Table
-- ================================================================
CREATE TABLE IF NOT EXISTS document_activity_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details JSON,
    user_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_document_activity (document_id, created_at DESC),
    INDEX idx_action (action),
    INDEX idx_user_activity (user_id, created_at DESC),
    
    -- Foreign keys
    CONSTRAINT fk_activity_document 
        FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_user 
        FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- OnlyOffice Sessions Table
-- ================================================================
CREATE TABLE IF NOT EXISTS onlyoffice_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(255) UNIQUE NOT NULL,
    document_id INT NOT NULL,
    user_id INT,
    token VARCHAR(500),
    config JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    status ENUM('active', 'closed', 'expired') DEFAULT 'active',
    
    -- Indexes
    INDEX idx_session_key (session_key),
    INDEX idx_document_sessions (document_id, status),
    INDEX idx_user_sessions (user_id, status),
    INDEX idx_status (status, last_accessed),
    
    -- Foreign keys
    CONSTRAINT fk_session_document 
        FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_user 
        FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- OnlyOffice Rate Limiting Table
-- ================================================================
CREATE TABLE IF NOT EXISTS onlyoffice_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    requests_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_end TIMESTAMP NOT NULL,
    
    -- Indexes
    INDEX idx_identifier_endpoint (identifier, endpoint, window_end),
    INDEX idx_window_cleanup (window_end),
    
    -- Unique constraint
    UNIQUE KEY uk_rate_limit (identifier, endpoint, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- OnlyOffice Security Log Table
-- ================================================================
CREATE TABLE IF NOT EXISTS onlyoffice_security_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    message TEXT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    user_id INT,
    document_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_event_type (event_type, created_at DESC),
    INDEX idx_severity (severity, created_at DESC),
    INDEX idx_user_security (user_id, created_at DESC),
    INDEX idx_document_security (document_id, created_at DESC),
    INDEX idx_ip_address (ip_address, created_at DESC),
    
    -- Foreign keys (nullable for flexibility)
    CONSTRAINT fk_security_user 
        FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    CONSTRAINT fk_security_document 
        FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Update documenti table with OnlyOffice fields
-- ================================================================
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS is_editing BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS editing_users JSON,
    ADD COLUMN IF NOT EXISTS editing_started_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS current_version INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS total_versions INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS last_error VARCHAR(500),
    ADD COLUMN IF NOT EXISTS last_error_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS onlyoffice_key VARCHAR(255),
    ADD COLUMN IF NOT EXISTS onlyoffice_url VARCHAR(500),
    ADD INDEX IF NOT EXISTS idx_editing_status (is_editing, editing_started_at),
    ADD INDEX IF NOT EXISTS idx_onlyoffice_key (onlyoffice_key);

-- ================================================================
-- Create cleanup events for old data
-- ================================================================

-- Cleanup old rate limit entries (runs every hour)
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_onlyoffice_rate_limits
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM onlyoffice_rate_limits 
    WHERE window_end < DATE_SUB(NOW(), INTERVAL 1 HOUR);
END$$
DELIMITER ;

-- Cleanup old collaborative actions (keeps last 30 days)
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_collaborative_actions
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    DELETE FROM document_collaborative_actions 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$
DELIMITER ;

-- Cleanup expired sessions (runs every day)
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_onlyoffice_sessions
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    UPDATE onlyoffice_sessions 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND last_accessed < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    DELETE FROM onlyoffice_sessions 
    WHERE status IN ('closed', 'expired') 
    AND ended_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$
DELIMITER ;

-- Cleanup old security logs (keeps last 90 days)
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_security_logs
ON SCHEDULE EVERY 1 WEEK
DO
BEGIN
    DELETE FROM onlyoffice_security_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    AND severity NOT IN ('error', 'critical');
    
    -- Keep critical logs for 1 year
    DELETE FROM onlyoffice_security_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY);
END$$
DELIMITER ;

-- ================================================================
-- Create stored procedures for common operations
-- ================================================================

-- Procedure to create a new document version
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS sp_create_document_version(
    IN p_document_id INT,
    IN p_file_path VARCHAR(500),
    IN p_file_size BIGINT,
    IN p_user_id INT,
    IN p_user_name VARCHAR(255),
    IN p_is_major BOOLEAN,
    IN p_notes TEXT,
    IN p_changes_data JSON
)
BEGIN
    DECLARE v_version_number INT;
    
    -- Get next version number
    SELECT COALESCE(MAX(version_number), 0) + 1 INTO v_version_number
    FROM documenti_versioni_extended
    WHERE documento_id = p_document_id;
    
    -- Mark current version as not current
    UPDATE documenti_versioni_extended 
    SET is_current = FALSE 
    WHERE documento_id = p_document_id AND is_current = TRUE;
    
    -- Insert new version
    INSERT INTO documenti_versioni_extended (
        documento_id, version_number, file_path, file_size,
        created_by_id, created_by_name, is_major, is_current,
        notes, changes_data
    ) VALUES (
        p_document_id, v_version_number, p_file_path, p_file_size,
        p_user_id, p_user_name, p_is_major, TRUE,
        p_notes, p_changes_data
    );
    
    -- Update document record
    UPDATE documenti 
    SET current_version = v_version_number,
        total_versions = v_version_number,
        aggiornato_il = NOW(),
        file_path = p_file_path,
        dimensione_file = p_file_size
    WHERE id = p_document_id;
    
    SELECT v_version_number AS version_number;
END$$
DELIMITER ;

-- Procedure to get active editors for a document
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS sp_get_active_editors(
    IN p_document_id INT
)
BEGIN
    SELECT 
        dae.user_id,
        dae.user_name,
        dae.started_at,
        dae.last_activity,
        u.email,
        u.avatar
    FROM document_active_editors dae
    LEFT JOIN utenti u ON dae.user_id = u.id
    WHERE dae.document_id = p_document_id 
    AND dae.is_active = TRUE
    AND dae.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY dae.started_at ASC;
END$$
DELIMITER ;

-- ================================================================
-- Create views for reporting
-- ================================================================

-- View for document version history
CREATE OR REPLACE VIEW v_document_version_history AS
SELECT 
    dve.documento_id,
    d.nome_file,
    dve.version_number,
    dve.created_at,
    dve.created_by_name,
    dve.is_major,
    dve.is_current,
    dve.file_size,
    dve.notes,
    u.email as created_by_email
FROM documenti_versioni_extended dve
JOIN documenti d ON dve.documento_id = d.id
LEFT JOIN utenti u ON dve.created_by_id = u.id
ORDER BY dve.documento_id, dve.version_number DESC;

-- View for active editing sessions
CREATE OR REPLACE VIEW v_active_editing_sessions AS
SELECT 
    d.id as document_id,
    d.nome_file,
    d.tipo_documento,
    COUNT(DISTINCT dae.user_id) as active_editors_count,
    GROUP_CONCAT(DISTINCT dae.user_name SEPARATOR ', ') as active_editors,
    MIN(dae.started_at) as editing_started_at,
    MAX(dae.last_activity) as last_activity
FROM documenti d
JOIN document_active_editors dae ON d.id = dae.document_id
WHERE dae.is_active = TRUE
AND dae.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
GROUP BY d.id, d.nome_file, d.tipo_documento;

-- ================================================================
-- Insert initial data
-- ================================================================

-- Log the migration
INSERT INTO onlyoffice_security_log (event_type, severity, message, details)
VALUES ('migration', 'info', 'OnlyOffice tables created successfully', 
        JSON_OBJECT('version', '1.0.0', 'timestamp', NOW()));

-- ================================================================
-- Grant permissions (adjust as needed)
-- ================================================================
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.documenti_versioni_extended TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.document_active_editors TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.document_collaborative_actions TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.document_activity_log TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.onlyoffice_sessions TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.onlyoffice_rate_limits TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.onlyoffice_security_log TO 'nexio_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE nexiosol.sp_create_document_version TO 'nexio_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE nexiosol.sp_get_active_editors TO 'nexio_user'@'localhost';

-- ================================================================
-- Success message
-- ================================================================
SELECT 'OnlyOffice integration tables created successfully!' AS status;