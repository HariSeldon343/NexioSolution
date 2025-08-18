-- ====================================================
-- Migration: Complete OnlyOffice tables structure
-- Date: 2025-01-18 10:55
-- Description: Add missing columns and indexes to OnlyOffice tables
-- ====================================================

START TRANSACTION;

USE nexiosol;

-- ====================================================
-- 1. Complete documenti_versioni_extended structure
-- ====================================================

ALTER TABLE documenti_versioni_extended 
ADD COLUMN IF NOT EXISTS content_html LONGTEXT,
ADD COLUMN IF NOT EXISTS created_by_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS is_major BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS is_current BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS notes TEXT,
ADD COLUMN IF NOT EXISTS changes_data TEXT,
ADD COLUMN IF NOT EXISTS hash VARCHAR(64),
ADD COLUMN IF NOT EXISTS metadata TEXT,
ADD INDEX IF NOT EXISTS idx_documento_version (documento_id, version_number),
ADD INDEX IF NOT EXISTS idx_current (documento_id, is_current),
ADD INDEX IF NOT EXISTS idx_created_at (created_at);

-- ====================================================
-- 2. Complete document_collaborative_actions structure
-- ====================================================

ALTER TABLE document_collaborative_actions
ADD INDEX IF NOT EXISTS idx_document_type (document_id, action_type),
ADD INDEX IF NOT EXISTS idx_created_at (created_at);

-- ====================================================
-- 3. Complete document_locks structure
-- ====================================================

ALTER TABLE document_locks
ADD UNIQUE KEY IF NOT EXISTS unique_lock (document_id, lock_type),
ADD INDEX IF NOT EXISTS idx_expires (expires_at),
ADD INDEX IF NOT EXISTS idx_locked_by (locked_by);

-- ====================================================
-- 4. Complete onlyoffice_callbacks structure
-- ====================================================

ALTER TABLE onlyoffice_callbacks
ADD COLUMN IF NOT EXISTS url TEXT,
ADD COLUMN IF NOT EXISTS changes_url TEXT,
ADD COLUMN IF NOT EXISTS force_save_type INT,
ADD COLUMN IF NOT EXISTS users TEXT,
ADD COLUMN IF NOT EXISTS actions TEXT,
ADD COLUMN IF NOT EXISTS history TEXT,
ADD COLUMN IF NOT EXISTS raw_data TEXT,
ADD COLUMN IF NOT EXISTS processed BOOLEAN DEFAULT FALSE,
ADD INDEX IF NOT EXISTS idx_callback_key (callback_key),
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_processed (processed);

-- ====================================================
-- 5. Complete onlyoffice_templates structure
-- ====================================================

ALTER TABLE onlyoffice_templates
ADD INDEX IF NOT EXISTS idx_category (category),
ADD INDEX IF NOT EXISTS idx_azienda (azienda_id),
ADD INDEX IF NOT EXISTS idx_active (is_active);

-- ====================================================
-- 6. Insert default templates if not exists
-- ====================================================

INSERT INTO onlyoffice_templates (name, description, file_type, category, is_active, file_path)
SELECT * FROM (
    SELECT 'Documento Vuoto' as name, 'Documento Word vuoto' as description, 'docx' as file_type, 'general' as category, TRUE as is_active, 'templates/blank.docx' as file_path
    UNION SELECT 'Foglio di Calcolo Vuoto', 'Foglio Excel vuoto', 'xlsx', 'general', TRUE, 'templates/blank.xlsx'
    UNION SELECT 'Presentazione Vuota', 'Presentazione PowerPoint vuota', 'pptx', 'general', TRUE, 'templates/blank.pptx'
    UNION SELECT 'Lettera Commerciale', 'Template per lettera commerciale', 'docx', 'business', TRUE, 'templates/letter.docx'
    UNION SELECT 'Report Mensile', 'Template per report mensile', 'docx', 'reports', TRUE, 'templates/report.docx'
    UNION SELECT 'Budget Annuale', 'Template per budget annuale', 'xlsx', 'finance', TRUE, 'templates/budget.xlsx'
    UNION SELECT 'Presentazione Aziendale', 'Template presentazione aziendale', 'pptx', 'business', TRUE, 'templates/presentation.pptx'
) AS temp
WHERE NOT EXISTS (
    SELECT 1 FROM onlyoffice_templates WHERE name = temp.name
);

-- ====================================================
-- 7. Create views for reporting
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

COMMIT;

-- ====================================================
-- Final verification
-- ====================================================

SELECT 
    'OnlyOffice Setup Complete' as Status,
    (SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = 'nexiosol' 
     AND table_name IN ('documenti_versioni_extended', 'document_collaborative_actions', 
                       'document_locks', 'onlyoffice_callbacks', 'onlyoffice_templates')) as TablesCreated,
    (SELECT COUNT(*) FROM onlyoffice_templates) as TemplatesCount;