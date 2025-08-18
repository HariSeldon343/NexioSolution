-- ====================================================
-- Migration: Create missing OnlyOffice tables
-- Date: 2025-01-18 10:45
-- Description: Create missing tables for OnlyOffice integration
-- ====================================================

START TRANSACTION;

USE nexiosol;

-- ====================================================
-- 1. Extended version management table
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
-- 2. Collaborative actions log
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
-- 3. Document locks for conflict prevention
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
-- 4. OnlyOffice callback history
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
-- 5. Document templates for OnlyOffice
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
-- 6. Insert default OnlyOffice templates
-- ====================================================

INSERT IGNORE INTO onlyoffice_templates (name, description, file_type, category, is_active) VALUES
('Documento Vuoto', 'Documento Word vuoto', 'docx', 'general', TRUE),
('Foglio di Calcolo Vuoto', 'Foglio Excel vuoto', 'xlsx', 'general', TRUE),
('Presentazione Vuota', 'Presentazione PowerPoint vuota', 'pptx', 'general', TRUE),
('Lettera Commerciale', 'Template per lettera commerciale', 'docx', 'business', TRUE),
('Report Mensile', 'Template per report mensile', 'docx', 'reports', TRUE),
('Budget Annuale', 'Template per budget annuale', 'xlsx', 'finance', TRUE),
('Presentazione Aziendale', 'Template presentazione aziendale', 'pptx', 'business', TRUE);

COMMIT;

-- ====================================================
-- Verification
-- ====================================================

SELECT 
    'Tabelle create con successo' as Status,
    COUNT(*) as Count
FROM information_schema.tables 
WHERE table_schema = 'nexiosol' 
AND table_name IN (
    'documenti_versioni_extended',
    'document_collaborative_actions',
    'document_locks',
    'onlyoffice_callbacks',
    'onlyoffice_templates'
);