-- Database Schema per Sistema Documentale Avanzato
-- Supporta strutture ISO, ricerca full-text, backup e GDPR compliance

-- Tabella documenti avanzati (estensione della tabella documenti base)
CREATE TABLE IF NOT EXISTS documenti_avanzati (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(50) NOT NULL,
    titolo VARCHAR(200) NOT NULL,
    descrizione TEXT,
    tipo_documento VARCHAR(50),
    contenuto_html LONGTEXT,
    file_path VARCHAR(500),
    file_size BIGINT,
    mime_type VARCHAR(100),
    hash_file VARCHAR(64),
    cartella_id INT,
    template_id INT,
    classificazione_id INT,
    norma_iso VARCHAR(20),
    versione INT DEFAULT 1,
    stato ENUM('bozza', 'in_revisione', 'approvato', 'pubblicato', 'archiviato', 'obsoleto') DEFAULT 'bozza',
    tags JSON,
    metadati JSON,
    contiene_dati_personali BOOLEAN DEFAULT FALSE,
    tipo_dati_gdpr VARCHAR(100),
    periodo_conservazione INT, -- mesi
    azienda_id INT NOT NULL,
    creato_da INT,
    modificato_da INT,
    responsabile_id INT,
    approvato_da INT,
    approvato_il TIMESTAMP NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_pubblicazione TIMESTAMP NULL,
    data_scadenza TIMESTAMP NULL,
    
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES template_documenti(id) ON DELETE SET NULL,
    FOREIGN KEY (classificazione_id) REFERENCES classificazioni(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (modificato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (responsabile_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (approvato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_codice_azienda (codice, azienda_id),
    INDEX idx_cartella (cartella_id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_stato (stato),
    INDEX idx_norma_iso (norma_iso),
    INDEX idx_tipo_documento (tipo_documento),
    INDEX idx_data_creazione (data_creazione),
    INDEX idx_data_scadenza (data_scadenza),
    INDEX idx_contiene_dati_personali (contiene_dati_personali),
    FULLTEXT idx_ricerca_completa (titolo, descrizione, contenuto_html)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indice per ricerca full-text avanzata
CREATE TABLE IF NOT EXISTS search_index (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    testo_completo LONGTEXT,
    keywords JSON,
    semantic_data JSON,
    word_count INT,
    language VARCHAR(5) DEFAULT 'it',
    readability_score FLOAT,
    data_indicizzazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    hash_contenuto VARCHAR(32),
    
    FOREIGN KEY (documento_id) REFERENCES documenti_avanzati(id) ON DELETE CASCADE,
    UNIQUE KEY unique_documento (documento_id),
    INDEX idx_language (language),
    INDEX idx_word_count (word_count),
    INDEX idx_readability (readability_score),
    INDEX idx_hash (hash_contenuto),
    FULLTEXT idx_testo_completo (testo_completo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log delle ricerche per analytics
CREATE TABLE IF NOT EXISTS search_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_text VARCHAR(500),
    filters_applied JSON,
    result_count INT,
    search_time FLOAT,
    user_id INT,
    azienda_id INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_query_text (query_text),
    FULLTEXT idx_query_fulltext (query_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistiche di ricerca per azienda
CREATE TABLE IF NOT EXISTS search_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    stats_data JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY unique_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ricerche salvate dagli utenti
CREATE TABLE IF NOT EXISTS saved_searches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    azienda_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    criteria JSON NOT NULL,
    is_alert BOOLEAN DEFAULT FALSE,
    alert_frequency ENUM('daily', 'weekly', 'monthly') NULL,
    last_executed TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_alert (is_alert)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log dei backup
CREATE TABLE IF NOT EXISTS backups_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    backup_id VARCHAR(100) NOT NULL UNIQUE,
    azienda_id INT NOT NULL,
    type ENUM('full', 'incremental', 'selective') NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    file_hash VARCHAR(64),
    documents_count INT DEFAULT 0,
    options JSON,
    filters JSON,
    description TEXT,
    created_by INT,
    execution_time FLOAT,
    status ENUM('pending', 'in_progress', 'completed', 'failed', 'deleted') DEFAULT 'pending',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_backup_id (backup_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Data Tracking
CREATE TABLE IF NOT EXISTS gdpr_data_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    tipo_dati ENUM('basic', 'sensitive', 'judicial', 'biometric', 'genetic', 'health', 'other') NOT NULL,
    periodo_conservazione INT, -- mesi
    base_giuridica ENUM('consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests') NOT NULL,
    finalita_trattamento TEXT,
    categorie_interessati TEXT,
    data_raccolta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_scadenza TIMESTAMP NULL,
    trasferimenti_paesi_terzi BOOLEAN DEFAULT FALSE,
    paesi_destinazione JSON,
    misure_sicurezza JSON,
    azienda_id INT NOT NULL,
    
    FOREIGN KEY (documento_id) REFERENCES documenti_avanzati(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_documento (documento_id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_tipo_dati (tipo_dati),
    INDEX idx_data_scadenza (data_scadenza),
    INDEX idx_base_giuridica (base_giuridica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Rights Requests
CREATE TABLE IF NOT EXISTS gdpr_rights_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    type ENUM('access', 'rectification', 'erasure', 'portability', 'restriction', 'objection') NOT NULL,
    data_subject_name VARCHAR(200) NOT NULL,
    data_subject_email VARCHAR(200),
    data_subject_phone VARCHAR(50),
    description TEXT,
    legal_basis TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'rejected', 'expired') DEFAULT 'pending',
    verification_method VARCHAR(100),
    verification_status ENUM('pending', 'verified', 'failed') DEFAULT 'pending',
    verification_date TIMESTAMP NULL,
    received_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deadline_date TIMESTAMP NOT NULL,
    completed_date TIMESTAMP NULL,
    response_sent_date TIMESTAMP NULL,
    assigned_to INT,
    created_by INT,
    documents_involved JSON,
    response_data JSON,
    response_method ENUM('email', 'post', 'phone', 'in_person') DEFAULT 'email',
    notes TEXT,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_deadline (deadline_date),
    INDEX idx_data_subject_email (data_subject_email),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Data Deletions Log
CREATE TABLE IF NOT EXISTS gdpr_data_deletions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    data_subject_email VARCHAR(200),
    data_subject_name VARCHAR(200),
    documents_processed JSON,
    reason TEXT NOT NULL,
    verification_method VARCHAR(100),
    processed_by INT,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    backup_before_deletion BOOLEAN DEFAULT TRUE,
    backup_reference VARCHAR(100),
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_processed_at (processed_at),
    INDEX idx_data_subject_email (data_subject_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Consents Management
CREATE TABLE IF NOT EXISTS gdpr_consents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    data_subject_email VARCHAR(200) NOT NULL,
    data_subject_name VARCHAR(200),
    purpose VARCHAR(500) NOT NULL,
    consent_text TEXT NOT NULL,
    status ENUM('active', 'withdrawn', 'expired') DEFAULT 'active',
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    withdrawal_date TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    source VARCHAR(100), -- website, app, form, etc.
    ip_address VARCHAR(45),
    user_agent TEXT,
    opt_in_method ENUM('checkbox', 'button', 'signature', 'verbal', 'other') DEFAULT 'checkbox',
    consent_evidence JSON, -- storing proof of consent
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_azienda (azienda_id),
    INDEX idx_data_subject_email (data_subject_email),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_consent_date (consent_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Processing Registry (Registro delle Attività di Trattamento)
CREATE TABLE IF NOT EXISTS gdpr_processing_registry (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    processing_name VARCHAR(200) NOT NULL,
    purpose TEXT NOT NULL,
    legal_basis ENUM('consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests') NOT NULL,
    data_categories JSON NOT NULL, -- types of personal data
    data_subjects_categories JSON NOT NULL, -- categories of data subjects
    recipients JSON, -- who receives the data
    international_transfers BOOLEAN DEFAULT FALSE,
    transfer_countries JSON,
    retention_period VARCHAR(100),
    security_measures JSON,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    dpo_consulted BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_legal_basis (legal_basis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GDPR Data Protection Impact Assessment (DPIA)
CREATE TABLE IF NOT EXISTS gdpr_dpia (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    processing_registry_id INT,
    dpia_name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    necessity_assessment TEXT,
    proportionality_assessment TEXT,
    risks_identified JSON,
    risk_mitigation_measures JSON,
    overall_risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    residual_risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    consultation_required BOOLEAN DEFAULT FALSE,
    authority_consultation_date TIMESTAMP NULL,
    authority_opinion TEXT,
    status ENUM('draft', 'in_review', 'completed', 'requires_consultation') DEFAULT 'draft',
    conducted_by INT,
    reviewed_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    next_review_date TIMESTAMP NULL,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (processing_registry_id) REFERENCES gdpr_processing_registry(id) ON DELETE SET NULL,
    FOREIGN KEY (conducted_by) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_processing_registry (processing_registry_id),
    INDEX idx_risk_level (overall_risk_level),
    INDEX idx_status (status),
    INDEX idx_next_review (next_review_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiorna tabella cartelle per supporto ISO
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS tipo_speciale ENUM('iso_main', 'iso_folder', 'gdpr_folder', 'normal') DEFAULT 'normal',
ADD COLUMN IF NOT EXISTS norma_iso VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS configurazione_iso JSON NULL,
ADD INDEX idx_tipo_speciale (tipo_speciale),
ADD INDEX idx_norma_iso (norma_iso);

-- Aggiorna tabella classificazioni per supporto ISO
ALTER TABLE classificazioni 
ADD COLUMN IF NOT EXISTS norma_iso VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS tipo_classificazione ENUM('iso', 'gdpr', 'custom', 'standard') DEFAULT 'standard',
ADD COLUMN IF NOT EXISTS metadati_iso JSON NULL,
ADD INDEX idx_norma_iso (norma_iso),
ADD INDEX idx_tipo_classificazione (tipo_classificazione);

-- Trigger per prevenire eliminazione log GDPR
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS prevent_gdpr_log_deletion
BEFORE DELETE ON gdpr_rights_requests
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Deletion of GDPR rights requests is not permitted for compliance reasons';
END$$

CREATE TRIGGER IF NOT EXISTS prevent_gdpr_deletion_log_deletion
BEFORE DELETE ON gdpr_data_deletions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Deletion of GDPR data deletion logs is not permitted for compliance reasons';
END$$

CREATE TRIGGER IF NOT EXISTS prevent_consent_log_deletion
BEFORE DELETE ON gdpr_consents
FOR EACH ROW
BEGIN
    IF OLD.status = 'withdrawn' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Deletion of withdrawn consent records is not permitted for compliance reasons';
    END IF;
END$$
DELIMITER ;

-- Vista per dashboard GDPR
CREATE OR REPLACE VIEW vista_gdpr_dashboard AS
SELECT 
    a.id as azienda_id,
    a.nome as azienda_nome,
    COUNT(DISTINCT da.id) as documenti_con_dati_personali,
    COUNT(DISTINCT grr.id) as richieste_diritti_totali,
    COUNT(DISTINCT CASE WHEN grr.status = 'pending' THEN grr.id END) as richieste_diritti_pending,
    COUNT(DISTINCT gc.id) as consensi_attivi,
    COUNT(DISTINCT gpr.id) as trattamenti_registrati,
    COUNT(DISTINCT gdpia.id) as dpia_completate,
    AVG(CASE 
        WHEN gdpia.overall_risk_level = 'low' THEN 1
        WHEN gdpia.overall_risk_level = 'medium' THEN 2
        WHEN gdpia.overall_risk_level = 'high' THEN 3
    END) as rischio_medio
FROM aziende a
LEFT JOIN documenti_avanzati da ON a.id = da.azienda_id AND da.contiene_dati_personali = 1
LEFT JOIN gdpr_rights_requests grr ON a.id = grr.azienda_id
LEFT JOIN gdpr_consents gc ON a.id = gc.azienda_id AND gc.status = 'active'
LEFT JOIN gdpr_processing_registry gpr ON a.id = gpr.azienda_id
LEFT JOIN gdpr_dpia gdpia ON a.id = gdpia.azienda_id AND gdpia.status = 'completed'
GROUP BY a.id, a.nome;

-- Vista per monitoraggio scadenze
CREATE OR REPLACE VIEW vista_scadenze_gdpr AS
SELECT 
    'data_retention' as tipo_scadenza,
    gdt.azienda_id,
    da.id as documento_id,
    da.titolo,
    gdt.data_scadenza,
    DATEDIFF(gdt.data_scadenza, NOW()) as giorni_rimanenti,
    gdt.tipo_dati,
    'Scadenza periodo di conservazione' as descrizione
FROM gdpr_data_tracking gdt
JOIN documenti_avanzati da ON gdt.documento_id = da.id
WHERE gdt.data_scadenza IS NOT NULL 
  AND gdt.data_scadenza > NOW()
  AND DATEDIFF(gdt.data_scadenza, NOW()) <= 90

UNION ALL

SELECT 
    'rights_request' as tipo_scadenza,
    grr.azienda_id,
    NULL as documento_id,
    CONCAT('Richiesta diritti: ', grr.type) as titolo,
    grr.deadline_date as data_scadenza,
    DATEDIFF(grr.deadline_date, NOW()) as giorni_rimanenti,
    grr.type as tipo_dati,
    CONCAT('Risposta richiesta diritti - ', grr.data_subject_name) as descrizione
FROM gdpr_rights_requests grr
WHERE grr.status = 'pending' 
  AND grr.deadline_date > NOW()
  AND DATEDIFF(grr.deadline_date, NOW()) <= 30

ORDER BY giorni_rimanenti ASC;

-- Inserimento moduli di sistema per le nuove funzionalità
INSERT IGNORE INTO moduli_sistema (codice, nome, descrizione, icona, url_pagina, permessi_richiesti, ordine, attivo) VALUES
('advanced_search', 'Ricerca Avanzata', 'Motore di ricerca avanzato con filtri e indicizzazione full-text', 'fas fa-search-plus', 'ricerca-avanzata.php', '["view_documents"]', 20, 1),
('iso_management', 'Gestione ISO', 'Creazione e gestione strutture documentali ISO', 'fas fa-certificate', 'gestione-iso.php', '["manage_iso_structures"]', 25, 1),
('gdpr_compliance', 'Conformità GDPR', 'Gestione conformità GDPR e privacy', 'fas fa-shield-alt', 'conformita-gdpr.php', '["gdpr_management"]', 30, 1),
('backup_management', 'Gestione Backup', 'Creazione e gestione backup documentali', 'fas fa-database', 'gestione-backup.php', '["manage_backups"]', 35, 1),
('document_analytics', 'Analytics Documenti', 'Analisi e statistiche sui documenti', 'fas fa-chart-line', 'document-analytics.php', '["view_analytics"]', 40, 1);

-- Popolamento permessi di base
INSERT IGNORE INTO permessi_sistema (codice, nome, descrizione, categoria) VALUES
('view_documents', 'Visualizzare Documenti', 'Permesso di visualizzare i documenti', 'documenti'),
('manage_iso_structures', 'Gestire Strutture ISO', 'Permesso di creare e gestire strutture ISO', 'iso'),
('gdpr_management', 'Gestione GDPR', 'Permesso di gestire conformità GDPR', 'gdpr'),
('manage_backups', 'Gestire Backup', 'Permesso di creare e gestire backup', 'backup'),
('view_analytics', 'Visualizzare Analytics', 'Permesso di visualizzare analytics e statistiche', 'analytics'),
('advanced_search', 'Ricerca Avanzata', 'Permesso di utilizzare la ricerca avanzata', 'ricerca');

-- Configurazioni di base per il sistema
INSERT IGNORE INTO configurazioni (chiave, valore, tipo, descrizione) VALUES
('search_index_enabled', '1', 'boolean', 'Abilita indicizzazione automatica per ricerca full-text'),
('search_min_word_length', '3', 'integer', 'Lunghezza minima parole per indicizzazione'),
('backup_retention_days', '90', 'integer', 'Giorni di conservazione backup automatici'),
('gdpr_retention_default_months', '24', 'integer', 'Periodo di conservazione predefinito (mesi) per dati personali'),
('gdpr_notification_days', '30', 'integer', 'Giorni di preavviso per scadenze GDPR'),
('iso_template_auto_update', '1', 'boolean', 'Aggiornamento automatico template ISO');

COMMIT;