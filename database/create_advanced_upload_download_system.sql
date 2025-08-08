-- =====================================================
-- Advanced Upload/Download & Search System Tables
-- Sistema tabelle per gestione upload/download multipli e ricerca full-text
-- Nexio Platform - Sistema Documentale ISO
-- =====================================================

-- Tabella sessioni upload multipli
CREATE TABLE IF NOT EXISTS upload_sessions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    batch_id VARCHAR(100) NOT NULL UNIQUE,
    azienda_id INT(11) NOT NULL,
    created_by INT(11) NOT NULL,
    total_files INT(11) NOT NULL DEFAULT 0,
    files_processed INT(11) NOT NULL DEFAULT 0,
    files_success INT(11) NOT NULL DEFAULT 0,
    files_errors INT(11) NOT NULL DEFAULT 0,
    total_size BIGINT NOT NULL DEFAULT 0,
    final_size BIGINT DEFAULT NULL,
    progress DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    stato ENUM('pending', 'processing', 'completed', 'completed_with_errors', 'failed') NOT NULL DEFAULT 'pending',
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_batch_id (batch_id),
    INDEX idx_azienda_stato (azienda_id, stato),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella errori upload
CREATE TABLE IF NOT EXISTS upload_errors (
    id INT(11) NOT NULL AUTO_INCREMENT,
    batch_id VARCHAR(100) NOT NULL,
    session_id INT(11) DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_index INT(11) DEFAULT NULL,
    error_type VARCHAR(50) NOT NULL,
    error_message TEXT NOT NULL,
    error_details JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_batch_id (batch_id),
    INDEX idx_session_id (session_id),
    INDEX idx_error_type (error_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (session_id) REFERENCES upload_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella sessioni download multipli
CREATE TABLE IF NOT EXISTS download_sessions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    zip_id VARCHAR(100) NOT NULL UNIQUE,
    azienda_id INT(11) NOT NULL,
    created_by INT(11) NOT NULL,
    document_ids JSON NOT NULL,
    total_documents INT(11) NOT NULL DEFAULT 0,
    files_processed INT(11) NOT NULL DEFAULT 0,
    total_size BIGINT NOT NULL DEFAULT 0,
    final_size BIGINT DEFAULT NULL,
    compression_ratio DECIMAL(5,2) DEFAULT NULL,
    progress DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    stato ENUM('pending', 'processing', 'completed', 'failed', 'expired') NOT NULL DEFAULT 'pending',
    options JSON DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    zip_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_zip_id (zip_id),
    INDEX idx_azienda_stato (azienda_id, stato),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella token download
CREATE TABLE IF NOT EXISTS download_tokens (
    id INT(11) NOT NULL AUTO_INCREMENT,
    token VARCHAR(64) NOT NULL UNIQUE,
    zip_id VARCHAR(100) NOT NULL,
    azienda_id INT(11) NOT NULL,
    created_by INT(11) DEFAULT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    download_count INT(11) NOT NULL DEFAULT 0,
    max_downloads INT(11) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    downloaded_at TIMESTAMP NULL DEFAULT NULL,
    downloaded_by INT(11) DEFAULT NULL,
    last_access_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_token (token),
    INDEX idx_zip_id (zip_id),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (downloaded_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella indice di ricerca full-text
CREATE TABLE IF NOT EXISTS search_index (
    id INT(11) NOT NULL AUTO_INCREMENT,
    documento_id INT(11) NOT NULL,
    azienda_id INT(11) NOT NULL,
    testo_completo LONGTEXT NOT NULL,
    keywords JSON DEFAULT NULL,
    semantic_data JSON DEFAULT NULL,
    word_count INT(11) NOT NULL DEFAULT 0,
    language VARCHAR(5) DEFAULT 'it',
    readability_score DECIMAL(3,2) DEFAULT NULL,
    hash_contenuto VARCHAR(64) NOT NULL,
    data_indicizzazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_documento (documento_id),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_language (language),
    INDEX idx_word_count (word_count),
    INDEX idx_data_indicizzazione (data_indicizzazione),
    INDEX idx_hash_contenuto (hash_contenuto),
    FULLTEXT KEY ft_testo_completo (testo_completo),
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella log ricerche
CREATE TABLE IF NOT EXISTS search_log (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    azienda_id INT(11) NOT NULL,
    query_text TEXT NOT NULL,
    filters_applied JSON DEFAULT NULL,
    result_count INT(11) NOT NULL DEFAULT 0,
    search_time DECIMAL(8,3) NOT NULL DEFAULT 0.000,
    search_type ENUM('standard', 'semantic', 'advanced', 'suggestion') NOT NULL DEFAULT 'standard',
    user_agent TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_search_type (search_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_query_result (result_count),
    FULLTEXT KEY ft_query_text (query_text),
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella statistiche ricerca per azienda
CREATE TABLE IF NOT EXISTS search_statistics (
    id INT(11) NOT NULL AUTO_INCREMENT,
    azienda_id INT(11) NOT NULL,
    total_searches INT(11) NOT NULL DEFAULT 0,
    total_documents_indexed INT(11) NOT NULL DEFAULT 0,
    avg_search_time DECIMAL(8,3) NOT NULL DEFAULT 0.000,
    most_searched_terms JSON DEFAULT NULL,
    search_success_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    last_index_update TIMESTAMP NULL DEFAULT NULL,
    stats_data JSON DEFAULT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_azienda_period (azienda_id, period_start, period_end),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_period (period_start, period_end),
    INDEX idx_updated_at (updated_at),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella queue elaborazione asincrona
CREATE TABLE IF NOT EXISTS processing_queue (
    id INT(11) NOT NULL AUTO_INCREMENT,
    job_type ENUM('upload_processing', 'indexing', 'optimization', 'cleanup', 'notification') NOT NULL,
    job_data JSON NOT NULL,
    priorita ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    stato ENUM('pending', 'processing', 'completed', 'failed', 'retrying') NOT NULL DEFAULT 'pending',
    tentativi INT(11) NOT NULL DEFAULT 0,
    max_tentativi INT(11) NOT NULL DEFAULT 3,
    azienda_id INT(11) DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    processor_id VARCHAR(50) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    scheduled_for TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_job_type (job_type),
    INDEX idx_stato_priorita (stato, priorita),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_created_by (created_by),
    INDEX idx_tentativi (tentativi),
    INDEX idx_scheduled_for (scheduled_for),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella job cleanup automatico
CREATE TABLE IF NOT EXISTS cleanup_jobs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tipo ENUM('zip_file', 'temp_file', 'old_session', 'expired_token', 'log_cleanup') NOT NULL,
    target_path TEXT DEFAULT NULL,
    target_criteria JSON DEFAULT NULL,
    stato ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    retention_days INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_for TIMESTAMP NOT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_tipo (tipo),
    INDEX idx_stato (stato),
    INDEX idx_scheduled_for (scheduled_for),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella quarantena file sospetti
CREATE TABLE IF NOT EXISTS file_quarantine (
    id INT(11) NOT NULL AUTO_INCREMENT,
    original_filename VARCHAR(255) NOT NULL,
    quarantine_path VARCHAR(500) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    threat_type VARCHAR(100) DEFAULT NULL,
    threat_details JSON DEFAULT NULL,
    azienda_id INT(11) NOT NULL,
    uploaded_by INT(11) DEFAULT NULL,
    quarantined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT(11) DEFAULT NULL,
    status ENUM('quarantined', 'safe', 'threat', 'deleted') NOT NULL DEFAULT 'quarantined',
    notes TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_file_hash (file_hash),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_status (status),
    INDEX idx_quarantined_at (quarantined_at),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT(11) NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    azienda_id INT(11) DEFAULT NULL,
    user_id INT(11) DEFAULT NULL,
    requests_count INT(11) NOT NULL DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_end TIMESTAMP NOT NULL,
    blocked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_identifier_action (identifier, action),
    INDEX idx_azienda_id (azienda_id),
    INDEX idx_user_id (user_id),
    INDEX idx_window_end (window_end),
    INDEX idx_blocked_until (blocked_until),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiorna tabella documenti per supportare batch e ottimizzazione
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS batch_id VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ottimizzato TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS data_ottimizzazione TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS hash_file VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS indicizzato TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS data_indicizzazione TIMESTAMP NULL DEFAULT NULL;

-- Aggiorna indici tabella documenti
ALTER TABLE documenti 
    ADD INDEX IF NOT EXISTS idx_batch_id (batch_id),
    ADD INDEX IF NOT EXISTS idx_ottimizzato (ottimizzato),
    ADD INDEX IF NOT EXISTS idx_hash_file (hash_file),
    ADD INDEX IF NOT EXISTS idx_indicizzato (indicizzato);

-- Aggiorna tabella aziende per quote storage
ALTER TABLE aziende 
    ADD COLUMN IF NOT EXISTS storage_limit BIGINT DEFAULT NULL COMMENT 'Limite storage in bytes',
    ADD COLUMN IF NOT EXISTS storage_used BIGINT NOT NULL DEFAULT 0 COMMENT 'Storage utilizzato in bytes',
    ADD COLUMN IF NOT EXISTS max_upload_size BIGINT DEFAULT NULL COMMENT 'Dimensione massima upload in bytes',
    ADD COLUMN IF NOT EXISTS max_files_per_upload INT(11) DEFAULT 100 COMMENT 'Numero massimo file per upload multiplo';

-- Vista riassuntiva upload sessions
CREATE OR REPLACE VIEW v_upload_sessions_summary AS
SELECT 
    us.id,
    us.batch_id,
    us.azienda_id,
    a.nome as azienda_nome,
    us.created_by,
    CONCAT(u.nome, ' ', u.cognome) as created_by_name,
    us.total_files,
    us.files_success,
    us.files_errors,
    us.total_size,
    us.final_size,
    us.progress,
    us.stato,
    us.created_at,
    us.completed_at,
    TIMESTAMPDIFF(SECOND, us.created_at, COALESCE(us.completed_at, NOW())) as duration_seconds,
    (SELECT COUNT(*) FROM upload_errors ue WHERE ue.batch_id = us.batch_id) as error_count
FROM upload_sessions us
JOIN aziende a ON us.azienda_id = a.id
JOIN utenti u ON us.created_by = u.id;

-- Vista riassuntiva download sessions
CREATE OR REPLACE VIEW v_download_sessions_summary AS
SELECT 
    ds.id,
    ds.zip_id,
    ds.azienda_id,
    a.nome as azienda_nome,
    ds.created_by,
    CONCAT(u.nome, ' ', u.cognome) as created_by_name,
    ds.total_documents,
    ds.files_processed,
    ds.total_size,
    ds.final_size,
    ds.compression_ratio,
    ds.progress,
    ds.stato,
    ds.created_at,
    ds.completed_at,
    ds.expires_at,
    TIMESTAMPDIFF(SECOND, ds.created_at, COALESCE(ds.completed_at, NOW())) as duration_seconds,
    (ds.final_size IS NOT NULL AND ds.total_size > 0) as compression_calculated
FROM download_sessions ds
JOIN aziende a ON ds.azienda_id = a.id
JOIN utenti u ON ds.created_by = u.id;

-- Vista statistiche ricerca per azienda
CREATE OR REPLACE VIEW v_search_stats_current AS
SELECT 
    sl.azienda_id,
    a.nome as azienda_nome,
    COUNT(*) as total_searches,
    COUNT(DISTINCT sl.user_id) as active_users,
    AVG(sl.search_time) as avg_search_time,
    AVG(sl.result_count) as avg_results,
    SUM(CASE WHEN sl.result_count > 0 THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate,
    DATE(sl.timestamp) as search_date
FROM search_log sl
JOIN aziende a ON sl.azienda_id = a.id
WHERE sl.timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY sl.azienda_id, DATE(sl.timestamp);

-- Trigger per aggiornare storage utilizzato
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS tr_documenti_insert_storage 
AFTER INSERT ON documenti
FOR EACH ROW
BEGIN
    UPDATE aziende 
    SET storage_used = storage_used + COALESCE(NEW.dimensione_file, 0)
    WHERE id = NEW.azienda_id;
END$$

CREATE TRIGGER IF NOT EXISTS tr_documenti_delete_storage 
AFTER DELETE ON documenti
FOR EACH ROW
BEGIN
    UPDATE aziende 
    SET storage_used = storage_used - COALESCE(OLD.dimensione_file, 0)
    WHERE id = OLD.azienda_id AND storage_used >= COALESCE(OLD.dimensione_file, 0);
END$$

CREATE TRIGGER IF NOT EXISTS tr_documenti_update_storage 
AFTER UPDATE ON documenti
FOR EACH ROW
BEGIN
    IF OLD.dimensione_file != NEW.dimensione_file THEN
        UPDATE aziende 
        SET storage_used = storage_used - COALESCE(OLD.dimensione_file, 0) + COALESCE(NEW.dimensione_file, 0)
        WHERE id = NEW.azienda_id;
    END IF;
END$$

DELIMITER ;

-- Inserimento configurazioni default
INSERT IGNORE INTO moduli_sistema (codice, nome, descrizione, attivo) VALUES 
('upload_multiplo', 'Upload Multiplo', 'Sistema upload multiplo con validazione ISO', 1),
('download_multiplo', 'Download Multiplo', 'Sistema download multiplo con compressione ZIP', 1),
('ricerca_avanzata', 'Ricerca Avanzata', 'Motore di ricerca full-text con AI semantica', 1),
('file_optimization', 'Ottimizzazione File', 'Sistema ottimizzazione e compressione file', 1);

-- Commit delle modifiche
COMMIT;

-- Messaggio di completamento
SELECT 'Sistema avanzato upload/download e ricerca full-text creato con successo!' as status;