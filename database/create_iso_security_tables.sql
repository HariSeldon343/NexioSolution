-- 
-- Script per creazione tabelle sicurezza sistema ISO
-- Nexio Platform - Sistema documentale ISO completo
-- 

-- Tabella permessi ISO disponibili
CREATE TABLE IF NOT EXISTS iso_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_iso_permissions_code (code),
    INDEX idx_iso_permissions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella permessi utente ISO
CREATE TABLE IF NOT EXISTS iso_user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    
    UNIQUE KEY unique_user_permission (user_id, company_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES iso_permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    
    INDEX idx_user_permissions_user (user_id),
    INDEX idx_user_permissions_company (company_id),
    INDEX idx_user_permissions_active (active),
    INDEX idx_user_permissions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella permessi ruolo ISO
CREATE TABLE IF NOT EXISTS iso_role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name ENUM('super_admin', 'utente_speciale', 'admin', 'staff', 'cliente') NOT NULL,
    company_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    
    UNIQUE KEY unique_role_permission (role_name, company_id, permission_id),
    FOREIGN KEY (company_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES iso_permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
    
    INDEX idx_role_permissions_role (role_name),
    INDEX idx_role_permissions_company (company_id),
    INDEX idx_role_permissions_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella audit trail ISO
CREATE TABLE IF NOT EXISTS iso_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    company_id INT NOT NULL,
    operation VARCHAR(100) NOT NULL,
    result ENUM('authorized', 'denied', 'error', 'completed') NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    INDEX idx_audit_trail_user (user_id),
    INDEX idx_audit_trail_company (company_id),
    INDEX idx_audit_trail_operation (operation),
    INDEX idx_audit_trail_result (result),
    INDEX idx_audit_trail_created (created_at),
    INDEX idx_audit_trail_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella backup operazioni critiche
CREATE TABLE IF NOT EXISTS iso_operation_backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    operation VARCHAR(100) NOT NULL,
    backup_data LONGTEXT NOT NULL,
    context JSON,
    file_path VARCHAR(500),
    compressed BOOLEAN DEFAULT FALSE,
    retention_until DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    INDEX idx_operation_backups_company (company_id),
    INDEX idx_operation_backups_operation (operation),
    INDEX idx_operation_backups_created (created_at),
    INDEX idx_operation_backups_retention (retention_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella sessioni attive ISO
CREATE TABLE IF NOT EXISTS iso_active_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    permissions_snapshot JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    INDEX idx_active_sessions_user (user_id),
    INDEX idx_active_sessions_company (company_id),
    INDEX idx_active_sessions_token (session_token),
    INDEX idx_active_sessions_expires (expires_at),
    INDEX idx_active_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella rate limiting
CREATE TABLE IF NOT EXISTS iso_rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    operation VARCHAR(100) NOT NULL,
    attempts INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_rate_limit (identifier, operation),
    INDEX idx_rate_limits_identifier (identifier),
    INDEX idx_rate_limits_operation (operation),
    INDEX idx_rate_limits_window (window_start),
    INDEX idx_rate_limits_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella eventi sicurezza
CREATE TABLE IF NOT EXISTS iso_security_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(100) NOT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    user_id INT,
    company_id INT,
    ip_address VARCHAR(45),
    details JSON,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES utenti(id) ON DELETE SET NULL,
    
    INDEX idx_security_events_type (event_type),
    INDEX idx_security_events_severity (severity),
    INDEX idx_security_events_user (user_id),
    INDEX idx_security_events_company (company_id),
    INDEX idx_security_events_created (created_at),
    INDEX idx_security_events_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserimento permessi ISO di base
INSERT IGNORE INTO iso_permissions (code, name, description, category) VALUES
-- Gestione strutture
('iso_structure_create', 'Creare strutture documentali ISO', 'Permesso per creare nuove strutture documentali ISO per le aziende', 'structure'),
('iso_structure_modify', 'Modificare strutture documentali ISO', 'Permesso per modificare strutture documentali ISO esistenti', 'structure'),
('iso_structure_delete', 'Eliminare strutture documentali ISO', 'Permesso per eliminare strutture documentali ISO', 'structure'),
('iso_structure_export', 'Esportare strutture documentali ISO', 'Permesso per esportare strutture documentali ISO in vari formati', 'structure'),

-- Gestione cartelle
('iso_folder_create', 'Creare cartelle ISO', 'Permesso per creare nuove cartelle nella struttura ISO', 'folder'),
('iso_folder_modify', 'Modificare cartelle ISO', 'Permesso per modificare cartelle ISO esistenti', 'folder'),
('iso_folder_delete', 'Eliminare cartelle ISO', 'Permesso per eliminare cartelle ISO', 'folder'),
('iso_folder_view', 'Visualizzare cartelle ISO', 'Permesso per visualizzare cartelle ISO', 'folder'),

-- Gestione documenti
('iso_document_upload', 'Caricare documenti ISO', 'Permesso per caricare documenti nel sistema ISO', 'document'),
('iso_document_download', 'Scaricare documenti ISO', 'Permesso per scaricare documenti dal sistema ISO', 'document'),
('iso_document_modify', 'Modificare documenti ISO', 'Permesso per modificare documenti ISO esistenti', 'document'),
('iso_document_delete', 'Eliminare documenti ISO', 'Permesso per eliminare documenti ISO', 'document'),
('iso_document_approve', 'Approvare documenti ISO', 'Permesso per approvare documenti ISO', 'document'),
('iso_document_version', 'Gestire versioni documenti ISO', 'Permesso per gestire versioni dei documenti ISO', 'document'),

-- Compliance e audit
('iso_compliance_view', 'Visualizzare stato conformità ISO', 'Permesso per visualizzare lo stato di conformità ISO', 'compliance'),
('iso_compliance_audit', 'Eseguire audit conformità ISO', 'Permesso per eseguire audit di conformità ISO', 'compliance'),
('iso_audit_view', 'Visualizzare audit trail', 'Permesso per visualizzare l\'audit trail del sistema ISO', 'audit'),
('iso_audit_export', 'Esportare audit trail', 'Permesso per esportare l\'audit trail del sistema ISO', 'audit'),

-- Amministrazione
('iso_admin_config', 'Configurare sistema ISO', 'Permesso per configurare il sistema documentale ISO', 'admin'),
('iso_admin_users', 'Gestire utenti sistema ISO', 'Permesso per gestire utenti del sistema ISO', 'admin'),
('iso_admin_permissions', 'Gestire permessi ISO', 'Permesso per gestire i permessi del sistema ISO', 'admin'),
('iso_admin_backup', 'Gestire backup sistema ISO', 'Permesso per gestire i backup del sistema ISO', 'admin');

-- Assegnazione permessi di base per ruoli
-- Super admin ha tutti i permessi
INSERT IGNORE INTO iso_role_permissions (role_name, company_id, permission_id, granted_by, notes)
SELECT 'super_admin', a.id, p.id, 1, 'Permessi automatici per super admin'
FROM aziende a
CROSS JOIN iso_permissions p
WHERE a.id IN (SELECT DISTINCT azienda_id FROM utenti_aziende WHERE ruolo_azienda = 'admin' LIMIT 10);

-- Utente speciale ha permessi elevati
INSERT IGNORE INTO iso_role_permissions (role_name, company_id, permission_id, granted_by, notes)
SELECT 'utente_speciale', a.id, p.id, 1, 'Permessi automatici per utente speciale'
FROM aziende a
CROSS JOIN iso_permissions p
WHERE p.category IN ('structure', 'folder', 'document', 'compliance', 'audit')
AND a.id IN (SELECT DISTINCT azienda_id FROM utenti_aziende WHERE ruolo_azienda = 'admin' LIMIT 10);

-- Admin ha permessi gestione
INSERT IGNORE INTO iso_role_permissions (role_name, company_id, permission_id, granted_by, notes)
SELECT 'admin', a.id, p.id, 1, 'Permessi automatici per admin'
FROM aziende a
CROSS JOIN iso_permissions p
WHERE p.category IN ('folder', 'document', 'compliance')
AND a.id IN (SELECT DISTINCT azienda_id FROM utenti_aziende WHERE ruolo_azienda = 'admin' LIMIT 10);

-- Staff ha permessi base
INSERT IGNORE INTO iso_role_permissions (role_name, company_id, permission_id, granted_by, notes)
SELECT 'staff', a.id, p.id, 1, 'Permessi automatici per staff'
FROM aziende a
CROSS JOIN iso_permissions p
WHERE p.code IN ('iso_folder_view', 'iso_document_upload', 'iso_document_download', 'iso_compliance_view')
AND a.id IN (SELECT DISTINCT azienda_id FROM utenti_aziende WHERE ruolo_azienda = 'admin' LIMIT 10);

-- Trigger per cleanup automatico sessioni scadute
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS cleanup_expired_sessions
    BEFORE INSERT ON iso_active_sessions
    FOR EACH ROW
BEGIN
    DELETE FROM iso_active_sessions 
    WHERE expires_at < NOW() 
    OR last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END$$

-- Trigger per backup automatico prima di eliminazioni critiche
CREATE TRIGGER IF NOT EXISTS backup_before_folder_delete
    BEFORE DELETE ON cartelle
    FOR EACH ROW
BEGIN
    INSERT INTO iso_operation_backups (company_id, operation, backup_data, created_at)
    VALUES (
        OLD.azienda_id,
        'folder_delete_trigger',
        JSON_OBJECT(
            'folder_id', OLD.id,
            'folder_name', OLD.nome,
            'folder_path', OLD.percorso_completo,
            'parent_id', OLD.parent_id,
            'iso_standard', OLD.iso_standard_codice,
            'metadata', OLD.iso_metadata
        ),
        NOW()
    );
END$$

-- Trigger per backup automatico prima di eliminazioni documenti
CREATE TRIGGER IF NOT EXISTS backup_before_document_delete
    BEFORE DELETE ON documenti
    FOR EACH ROW
BEGIN
    INSERT INTO iso_operation_backups (company_id, operation, backup_data, created_at)
    VALUES (
        OLD.azienda_id,
        'document_delete_trigger',
        JSON_OBJECT(
            'document_id', OLD.id,
            'document_code', OLD.codice,
            'document_title', OLD.titolo,
            'file_path', OLD.file_path,
            'cartella_id', OLD.cartella_id,
            'versione', OLD.versione,
            'stato', OLD.stato
        ),
        NOW()
    );
END$$

DELIMITER ;

-- Vista per statistiche sicurezza
CREATE OR REPLACE VIEW iso_security_dashboard AS
SELECT 
    a.id as company_id,
    a.nome as company_name,
    COUNT(DISTINCT iat.id) as total_audit_events,
    COUNT(DISTINCT ise.id) as total_security_events,
    COUNT(DISTINCT CASE WHEN ise.severity = 'critical' THEN ise.id END) as critical_events,
    COUNT(DISTINCT CASE WHEN ise.severity = 'error' THEN ise.id END) as error_events,
    COUNT(DISTINCT iob.id) as total_backups,
    COUNT(DISTINCT ias.id) as active_sessions,
    MAX(iat.created_at) as last_audit_event,
    MAX(ise.created_at) as last_security_event
FROM aziende a
LEFT JOIN iso_audit_trail iat ON a.id = iat.company_id
LEFT JOIN iso_security_events ise ON a.id = ise.company_id
LEFT JOIN iso_operation_backups iob ON a.id = iob.company_id
LEFT JOIN iso_active_sessions ias ON a.id = ias.company_id AND ias.expires_at > NOW()
GROUP BY a.id, a.nome;

-- Indici per performance
CREATE INDEX IF NOT EXISTS idx_audit_trail_composite ON iso_audit_trail(company_id, operation, created_at);
CREATE INDEX IF NOT EXISTS idx_security_events_composite ON iso_security_events(company_id, event_type, created_at);
CREATE INDEX IF NOT EXISTS idx_backups_composite ON iso_operation_backups(company_id, operation, created_at);

-- Event per cleanup automatico dati vecchi (eseguito giornalmente)
-- Nota: richiede SUPER privileges
-- CREATE EVENT IF NOT EXISTS cleanup_old_audit_data
--     ON SCHEDULE EVERY 1 DAY
--     STARTS CURRENT_TIMESTAMP
--     DO
--     BEGIN
--         -- Mantieni audit trail per 2 anni
--         DELETE FROM iso_audit_trail WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
--         
--         -- Mantieni eventi sicurezza per 1 anno
--         DELETE FROM iso_security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
--         
--         -- Mantieni backup per 90 giorni (a meno che non sia specificato diversamente)
--         DELETE FROM iso_operation_backups 
--         WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
--         AND (retention_until IS NULL OR retention_until < CURDATE());
--         
--         -- Pulisci rate limits vecchi
--         DELETE FROM iso_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 7 DAY);
--     END;

-- Commenti per documentazione
ALTER TABLE iso_permissions COMMENT = 'Tabella permessi disponibili per sistema documentale ISO';
ALTER TABLE iso_user_permissions COMMENT = 'Permessi specifici assegnati agli utenti per sistema ISO';
ALTER TABLE iso_role_permissions COMMENT = 'Permessi assegnati ai ruoli per sistema ISO';
ALTER TABLE iso_audit_trail COMMENT = 'Audit trail completo delle operazioni sistema ISO';
ALTER TABLE iso_operation_backups COMMENT = 'Backup automatici per operazioni critiche sistema ISO';
ALTER TABLE iso_active_sessions COMMENT = 'Sessioni attive con cache permessi per performance';
ALTER TABLE iso_rate_limits COMMENT = 'Rate limiting per prevenzione abusi sistema ISO';
ALTER TABLE iso_security_events COMMENT = 'Log eventi di sicurezza sistema ISO';

-- Fine script creazione tabelle sicurezza ISO