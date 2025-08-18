-- ====================================================
-- Migration: Create OnlyOffice tables (simplified)
-- Date: 2025-01-18 10:50
-- Description: Create missing OnlyOffice tables with simplified structure
-- ====================================================

START TRANSACTION;

USE nexiosol;

-- Drop test table
DROP TABLE IF EXISTS onlyoffice_callbacks;

-- ====================================================
-- 1. Extended version management table
-- ====================================================

CREATE TABLE IF NOT EXISTS documenti_versioni_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path TEXT,
    file_size BIGINT DEFAULT 0,
    content_html LONGTEXT,
    created_by_id INT,
    created_by_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_major BOOLEAN DEFAULT FALSE,
    is_current BOOLEAN DEFAULT FALSE,
    notes TEXT,
    changes_data TEXT,
    hash VARCHAR(64),
    metadata TEXT,
    
    INDEX idx_documento_version (documento_id, version_number),
    INDEX idx_current (documento_id, is_current),
    INDEX idx_created_at (created_at),
    UNIQUE KEY unique_doc_version (documento_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 2. Collaborative actions log
-- ====================================================

CREATE TABLE IF NOT EXISTS document_collaborative_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    user_id INT,
    action_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_document_type (document_id, action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    
    UNIQUE KEY unique_lock (document_id, lock_type),
    INDEX idx_expires (expires_at),
    INDEX idx_locked_by (locked_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 4. OnlyOffice callback history
-- ====================================================

CREATE TABLE IF NOT EXISTS onlyoffice_callbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT,
    callback_key VARCHAR(255),
    status INT,
    url TEXT,
    changes_url TEXT,
    force_save_type INT,
    users TEXT,
    actions TEXT,
    history TEXT,
    raw_data TEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_callback_key (callback_key),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 5. Document templates for OnlyOffice
-- ====================================================

CREATE TABLE IF NOT EXISTS onlyoffice_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    file_path TEXT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    category VARCHAR(100),
    azienda_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_category (category),
    INDEX idx_azienda (azienda_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================
-- 6. Insert default OnlyOffice templates
-- ====================================================

INSERT IGNORE INTO onlyoffice_templates (name, description, file_type, category, is_active, file_path) VALUES
('Documento Vuoto', 'Documento Word vuoto', 'docx', 'general', TRUE, 'templates/blank.docx'),
('Foglio di Calcolo Vuoto', 'Foglio Excel vuoto', 'xlsx', 'general', TRUE, 'templates/blank.xlsx'),
('Presentazione Vuota', 'Presentazione PowerPoint vuota', 'pptx', 'general', TRUE, 'templates/blank.pptx'),
('Lettera Commerciale', 'Template per lettera commerciale', 'docx', 'business', TRUE, 'templates/letter.docx'),
('Report Mensile', 'Template per report mensile', 'docx', 'reports', TRUE, 'templates/report.docx'),
('Budget Annuale', 'Template per budget annuale', 'xlsx', 'finance', TRUE, 'templates/budget.xlsx'),
('Presentazione Aziendale', 'Template presentazione aziendale', 'pptx', 'business', TRUE, 'templates/presentation.pptx');

COMMIT;

-- ====================================================
-- Verification
-- ====================================================

SELECT 
    COUNT(*) as TabelleMancanti
FROM (
    SELECT 'documenti_versioni_extended' as table_name 
    UNION SELECT 'document_collaborative_actions' 
    UNION SELECT 'document_locks' 
    UNION SELECT 'onlyoffice_callbacks' 
    UNION SELECT 'onlyoffice_templates'
) as needed 
WHERE table_name NOT IN (
    SELECT table_name 
    FROM information_schema.tables 
    WHERE table_schema = 'nexiosol'
);