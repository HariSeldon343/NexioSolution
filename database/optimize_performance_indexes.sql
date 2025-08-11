-- ============================================================================
-- NEXIO DATABASE PERFORMANCE OPTIMIZATION
-- Generated: 2025-08-10
-- Purpose: Add missing indexes and optimize database performance
-- ============================================================================

-- Record initial database size
SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Initial_DB_Size_MB',
    COUNT(*) AS 'Total_Tables'
FROM information_schema.TABLES 
WHERE table_schema = 'nexiosol';

-- ============================================================================
-- 1. ADD MISSING INDEXES FOR PERFORMANCE
-- ============================================================================

-- log_attivita table indexes
ALTER TABLE log_attivita 
    ADD INDEX IF NOT EXISTS idx_utente_id (utente_id),
    ADD INDEX IF NOT EXISTS idx_data_ora (data_ora),
    ADD INDEX IF NOT EXISTS idx_utente_data (utente_id, data_ora);

-- documenti table indexes
ALTER TABLE documenti 
    ADD INDEX IF NOT EXISTS idx_azienda_id (azienda_id),
    ADD INDEX IF NOT EXISTS idx_cartella_id (cartella_id),
    ADD INDEX IF NOT EXISTS idx_creato_da (creato_da),
    ADD INDEX IF NOT EXISTS idx_azienda_cartella (azienda_id, cartella_id),
    ADD INDEX IF NOT EXISTS idx_data_creazione (data_creazione);

-- cartelle table indexes
ALTER TABLE cartelle 
    ADD INDEX IF NOT EXISTS idx_parent_id (parent_id),
    ADD INDEX IF NOT EXISTS idx_azienda_id (azienda_id),
    ADD INDEX IF NOT EXISTS idx_parent_azienda (parent_id, azienda_id),
    ADD INDEX IF NOT EXISTS idx_nome (nome);

-- eventi table indexes
ALTER TABLE eventi 
    ADD INDEX IF NOT EXISTS idx_data_inizio (data_inizio),
    ADD INDEX IF NOT EXISTS idx_data_fine (data_fine),
    ADD INDEX IF NOT EXISTS idx_utente_id (utente_id),
    ADD INDEX IF NOT EXISTS idx_date_range (data_inizio, data_fine),
    ADD INDEX IF NOT EXISTS idx_utente_date (utente_id, data_inizio);

-- tasks table indexes
ALTER TABLE tasks 
    ADD INDEX IF NOT EXISTS idx_utente_id (utente_id),
    ADD INDEX IF NOT EXISTS idx_stato (stato),
    ADD INDEX IF NOT EXISTS idx_data_scadenza (data_scadenza),
    ADD INDEX IF NOT EXISTS idx_stato_scadenza (stato, data_scadenza),
    ADD INDEX IF NOT EXISTS idx_utente_stato (utente_id, stato);

-- tickets table indexes
ALTER TABLE tickets 
    ADD INDEX IF NOT EXISTS idx_stato (stato),
    ADD INDEX IF NOT EXISTS idx_priorita (priorita),
    ADD INDEX IF NOT EXISTS idx_assegnato_a (assegnato_a),
    ADD INDEX IF NOT EXISTS idx_stato_priorita (stato, priorita),
    ADD INDEX IF NOT EXISTS idx_assegnato_stato (assegnato_a, stato);

-- referenti table indexes
ALTER TABLE referenti 
    ADD INDEX IF NOT EXISTS idx_azienda_id (azienda_id),
    ADD INDEX IF NOT EXISTS idx_email (email),
    ADD INDEX IF NOT EXISTS idx_tipo (tipo);

-- filesystem_logs table indexes
ALTER TABLE filesystem_logs 
    ADD INDEX IF NOT EXISTS idx_utente_id (utente_id),
    ADD INDEX IF NOT EXISTS idx_data_operazione (data_operazione),
    ADD INDEX IF NOT EXISTS idx_utente_data (utente_id, data_operazione),
    ADD INDEX IF NOT EXISTS idx_tipo_operazione (tipo_operazione);

-- activity_logs table indexes
ALTER TABLE activity_logs 
    ADD INDEX IF NOT EXISTS idx_user_id (user_id),
    ADD INDEX IF NOT EXISTS idx_created_at (created_at),
    ADD INDEX IF NOT EXISTS idx_user_created (user_id, created_at),
    ADD INDEX IF NOT EXISTS idx_action (action);

-- ============================================================================
-- 2. ADD INDEXES FOR COMMON JOIN OPERATIONS
-- ============================================================================

-- utenti_aziende table (frequently joined)
ALTER TABLE utenti_aziende 
    ADD INDEX IF NOT EXISTS idx_utente_azienda (utente_id, azienda_id),
    ADD INDEX IF NOT EXISTS idx_azienda_utente (azienda_id, utente_id);

-- aziende table (frequently filtered by status)
ALTER TABLE aziende 
    ADD INDEX IF NOT EXISTS idx_stato (stato),
    ADD INDEX IF NOT EXISTS idx_nome (nome);

-- utenti table (frequently filtered by role and email)
ALTER TABLE utenti 
    ADD INDEX IF NOT EXISTS idx_email (email),
    ADD INDEX IF NOT EXISTS idx_role (role),
    ADD INDEX IF NOT EXISTS idx_attivo (attivo);

-- ============================================================================
-- 3. CLEAN UP ORPHANED RECORDS
-- ============================================================================

-- Remove orphaned documents (no existing azienda)
DELETE d FROM documenti d 
LEFT JOIN aziende a ON d.azienda_id = a.id 
WHERE d.azienda_id IS NOT NULL AND a.id IS NULL;

-- Remove orphaned folders (no existing azienda)
DELETE c FROM cartelle c 
LEFT JOIN aziende a ON c.azienda_id = a.id 
WHERE c.azienda_id IS NOT NULL AND a.id IS NULL;

-- Remove orphaned eventi (no existing user)
DELETE e FROM eventi e 
LEFT JOIN utenti u ON e.utente_id = u.id 
WHERE e.utente_id IS NOT NULL AND u.id IS NULL;

-- Remove orphaned tasks (no existing user)
DELETE t FROM tasks t 
LEFT JOIN utenti u ON t.utente_id = u.id 
WHERE t.utente_id IS NOT NULL AND u.id IS NULL;

-- Remove orphaned tickets (no existing assignee)
DELETE t FROM tickets t 
LEFT JOIN utenti u ON t.assegnato_a = u.id 
WHERE t.assegnato_a IS NOT NULL AND u.id IS NULL;

-- Remove orphaned referenti (no existing azienda)
DELETE r FROM referenti r 
LEFT JOIN aziende a ON r.azienda_id = a.id 
WHERE r.azienda_id IS NOT NULL AND a.id IS NULL;

-- Remove orphaned log entries (no existing user)
DELETE l FROM log_attivita l 
LEFT JOIN utenti u ON l.utente_id = u.id 
WHERE l.utente_id IS NOT NULL AND u.id IS NULL;

-- Remove orphaned filesystem logs (no existing user)
DELETE f FROM filesystem_logs f 
LEFT JOIN utenti u ON f.utente_id = u.id 
WHERE f.utente_id IS NOT NULL AND u.id IS NULL;

-- Remove orphaned activity logs (no existing user)
DELETE a FROM activity_logs a 
LEFT JOIN utenti u ON a.user_id = u.id 
WHERE a.user_id IS NOT NULL AND u.id IS NULL;

-- ============================================================================
-- 4. UPDATE TABLE STATISTICS
-- ============================================================================

ANALYZE TABLE log_attivita;
ANALYZE TABLE documenti;
ANALYZE TABLE cartelle;
ANALYZE TABLE eventi;
ANALYZE TABLE tasks;
ANALYZE TABLE tickets;
ANALYZE TABLE referenti;
ANALYZE TABLE filesystem_logs;
ANALYZE TABLE activity_logs;
ANALYZE TABLE utenti;
ANALYZE TABLE aziende;
ANALYZE TABLE utenti_aziende;

-- ============================================================================
-- 5. OPTIMIZE TABLES (DEFRAGMENT AND RECLAIM SPACE)
-- ============================================================================

OPTIMIZE TABLE log_attivita;
OPTIMIZE TABLE documenti;
OPTIMIZE TABLE cartelle;
OPTIMIZE TABLE eventi;
OPTIMIZE TABLE tasks;
OPTIMIZE TABLE tickets;
OPTIMIZE TABLE referenti;
OPTIMIZE TABLE filesystem_logs;
OPTIMIZE TABLE activity_logs;
OPTIMIZE TABLE utenti;
OPTIMIZE TABLE aziende;
OPTIMIZE TABLE utenti_aziende;

-- ============================================================================
-- 6. CHECK AND FIX DATABASE INTEGRITY
-- ============================================================================

-- Check for invalid foreign key references
SELECT 'Checking foreign key integrity...' AS Status;

-- Check documenti table integrity
SELECT COUNT(*) AS invalid_documenti_refs FROM documenti d 
LEFT JOIN aziende a ON d.azienda_id = a.id 
WHERE d.azienda_id IS NOT NULL AND a.id IS NULL;

-- Check cartelle table integrity
SELECT COUNT(*) AS invalid_cartelle_refs FROM cartelle c 
LEFT JOIN aziende a ON c.azienda_id = a.id 
WHERE c.azienda_id IS NOT NULL AND a.id IS NULL;

-- Check for duplicate primary keys (should return 0)
SELECT 'Checking for duplicate primary keys...' AS Status;

SELECT table_name, COUNT(*) as duplicates
FROM (
    SELECT 'documenti' as table_name, id, COUNT(*) as cnt FROM documenti GROUP BY id HAVING cnt > 1
    UNION ALL
    SELECT 'cartelle' as table_name, id, COUNT(*) as cnt FROM cartelle GROUP BY id HAVING cnt > 1
    UNION ALL
    SELECT 'utenti' as table_name, id, COUNT(*) as cnt FROM utenti GROUP BY id HAVING cnt > 1
    UNION ALL
    SELECT 'aziende' as table_name, id, COUNT(*) as cnt FROM aziende GROUP BY id HAVING cnt > 1
) as dup_check
GROUP BY table_name;

-- ============================================================================
-- 7. CREATE PERFORMANCE MONITORING VIEWS
-- ============================================================================

-- Drop existing views if they exist
DROP VIEW IF EXISTS v_slow_queries;
DROP VIEW IF EXISTS v_table_statistics;

-- Create view for monitoring slow queries
CREATE VIEW v_slow_queries AS
SELECT 
    l.id,
    l.utente_id,
    u.nome AS utente_nome,
    l.azione,
    l.data_ora,
    l.dettagli
FROM log_attivita l
LEFT JOIN utenti u ON l.utente_id = u.id
WHERE l.data_ora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY l.data_ora DESC;

-- Create view for table statistics
CREATE VIEW v_table_statistics AS
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
    ROUND((data_length / 1024 / 1024), 2) AS data_size_mb,
    ROUND((index_length / 1024 / 1024), 2) AS index_size_mb,
    ROUND((index_length / data_length) * 100, 2) AS index_ratio
FROM information_schema.TABLES
WHERE table_schema = 'nexiosol'
ORDER BY (data_length + index_length) DESC;

-- ============================================================================
-- 8. FINAL REPORT
-- ============================================================================

-- Report on final database size
SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Final_DB_Size_MB',
    COUNT(*) AS 'Total_Tables'
FROM information_schema.TABLES 
WHERE table_schema = 'nexiosol';

-- Report on indexed columns
SELECT 
    table_name AS 'Table',
    COUNT(DISTINCT index_name) AS 'Index_Count',
    GROUP_CONCAT(DISTINCT index_name ORDER BY index_name SEPARATOR ', ') AS 'Indexes'
FROM information_schema.STATISTICS
WHERE table_schema = 'nexiosol'
    AND table_name IN ('log_attivita', 'documenti', 'cartelle', 'eventi', 'tasks', 'tickets', 'referenti', 'filesystem_logs', 'activity_logs')
GROUP BY table_name
ORDER BY table_name;

-- Report optimization complete
SELECT 'Database optimization completed successfully!' AS Status;