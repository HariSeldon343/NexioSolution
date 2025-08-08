-- Database schema for ISO Document Structure Management System
-- Nexio Platform - Enterprise Document Management
-- Created: 2025-01-27

-- ==================================================
-- ISO STANDARDS CONFIGURATION TABLES
-- ==================================================

-- Table for available ISO standards
CREATE TABLE IF NOT EXISTS iso_standards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(20) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT,
    versione VARCHAR(20) DEFAULT '2015',
    attivo BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_codice (codice),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company ISO structure configuration
CREATE TABLE IF NOT EXISTS aziende_iso_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    tipo_struttura ENUM('separata', 'integrata', 'personalizzata') NOT NULL DEFAULT 'separata',
    standards_attivi JSON NOT NULL COMMENT 'Array of active ISO standard codes',
    configurazione_avanzata JSON NULL COMMENT 'Advanced configuration options',
    stato ENUM('configurazione', 'attiva', 'sospesa') DEFAULT 'configurazione',
    data_attivazione DATETIME NULL,
    creato_da INT NOT NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    UNIQUE KEY uk_azienda_config (azienda_id),
    INDEX idx_tipo_struttura (tipo_struttura),
    INDEX idx_stato (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard folder templates for each ISO standard
CREATE TABLE IF NOT EXISTS iso_folder_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    standard_id INT NOT NULL,
    codice VARCHAR(50) NOT NULL,
    nome VARCHAR(200) NOT NULL,
    nome_inglese VARCHAR(200) NULL,
    descrizione TEXT,
    parent_template_id INT NULL,
    livello INT NOT NULL DEFAULT 1,
    ordine_visualizzazione INT NOT NULL DEFAULT 0,
    icona VARCHAR(50) DEFAULT 'fa-folder',
    colore VARCHAR(7) DEFAULT '#fbbf24',
    obbligatoria BOOLEAN DEFAULT TRUE,
    configurabile BOOLEAN DEFAULT FALSE,
    metadati_aggiuntivi JSON NULL,
    
    FOREIGN KEY (standard_id) REFERENCES iso_standards(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_template_id) REFERENCES iso_folder_templates(id) ON DELETE CASCADE,
    UNIQUE KEY uk_standard_codice (standard_id, codice),
    INDEX idx_parent (parent_template_id),
    INDEX idx_livello (livello),
    INDEX idx_ordine (ordine_visualizzazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company-specific folder instances
CREATE TABLE IF NOT EXISTS aziende_iso_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    template_id INT NOT NULL,
    cartella_id INT NOT NULL,
    standard_codice VARCHAR(20) NOT NULL,
    percorso_iso VARCHAR(500) NOT NULL COMMENT 'ISO-compliant path structure',
    personalizzazioni JSON NULL COMMENT 'Company-specific customizations',
    stato ENUM('attiva', 'nascosta', 'archiviata') DEFAULT 'attiva',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES iso_folder_templates(id),
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    UNIQUE KEY uk_azienda_template (azienda_id, template_id),
    UNIQUE KEY uk_azienda_cartella (azienda_id, cartella_id),
    INDEX idx_standard (standard_codice),
    INDEX idx_stato (stato),
    INDEX idx_percorso (percorso_iso(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document classification based on ISO standards
CREATE TABLE IF NOT EXISTS iso_document_classifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    standard_id INT NOT NULL,
    codice VARCHAR(50) NOT NULL,
    nome VARCHAR(200) NOT NULL,
    descrizione TEXT,
    template_folder_id INT NOT NULL,
    tipo_documento ENUM('manuale', 'procedura', 'modulo', 'registro', 'politica', 'istruzione', 'altro') NOT NULL,
    prefisso_codice VARCHAR(10) NULL,
    numerazione_automatica BOOLEAN DEFAULT TRUE,
    obbligatorio BOOLEAN DEFAULT FALSE,
    template_contenuto LONGTEXT NULL,
    metadati_richiesti JSON NULL,
    
    FOREIGN KEY (standard_id) REFERENCES iso_standards(id) ON DELETE CASCADE,
    FOREIGN KEY (template_folder_id) REFERENCES iso_folder_templates(id),
    UNIQUE KEY uk_standard_codice_class (standard_id, codice),
    INDEX idx_tipo_documento (tipo_documento),
    INDEX idx_obbligatorio (obbligatorio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- AUDIT AND COMPLIANCE TRACKING
-- ==================================================

-- Structure deployment history
CREATE TABLE IF NOT EXISTS iso_deployment_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    operazione ENUM('creazione_iniziale', 'aggiornamento_struttura', 'aggiunta_standard', 'rimozione_standard', 'personalizzazione') NOT NULL,
    standard_coinvolti JSON NOT NULL,
    dettagli_operazione JSON NOT NULL,
    risultato ENUM('successo', 'parziale', 'fallito') NOT NULL,
    errori_riscontrati JSON NULL,
    tempo_esecuzione_secondi DECIMAL(8,3) NULL,
    eseguito_da INT NOT NULL,
    data_esecuzione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (eseguito_da) REFERENCES utenti(id),
    INDEX idx_azienda_data (azienda_id, data_esecuzione),
    INDEX idx_operazione (operazione),
    INDEX idx_risultato (risultato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure compliance monitoring
CREATE TABLE IF NOT EXISTS iso_compliance_check (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    standard_codice VARCHAR(20) NOT NULL,
    verifica_tipo ENUM('struttura_completa', 'cartelle_obbligatorie', 'documenti_minimi', 'nomenclatura') NOT NULL,
    stato_conformita ENUM('conforme', 'non_conforme', 'parzialmente_conforme', 'da_verificare') NOT NULL,
    elementi_mancanti JSON NULL,
    raccomandazioni JSON NULL,
    punteggio_conformita DECIMAL(5,2) NULL COMMENT 'Percentage score 0-100',
    data_verifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verificato_da INT NULL,
    note TEXT NULL,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (verificato_da) REFERENCES utenti(id),
    INDEX idx_azienda_standard (azienda_id, standard_codice),
    INDEX idx_stato_conformita (stato_conformita),
    INDEX idx_data_verifica (data_verifica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- ENHANCEMENT TO EXISTING CARTELLE TABLE
-- ==================================================

-- Add ISO-specific columns to existing cartelle table
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS iso_template_id INT NULL COMMENT 'Reference to iso_folder_templates',
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(20) NULL COMMENT 'ISO standard code for this folder',
ADD COLUMN IF NOT EXISTS iso_compliance_level ENUM('obbligatoria', 'raccomandata', 'opzionale', 'personalizzata') DEFAULT 'personalizzata',
ADD COLUMN IF NOT EXISTS iso_metadata JSON NULL COMMENT 'ISO-specific metadata and properties',
ADD COLUMN IF NOT EXISTS creato_da INT NULL COMMENT 'User who created this folder',
ADD COLUMN IF NOT EXISTS ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

ADD INDEX IF NOT EXISTS idx_iso_template (iso_template_id),
ADD INDEX IF NOT EXISTS idx_iso_standard (iso_standard_codice),
ADD INDEX IF NOT EXISTS idx_iso_compliance (iso_compliance_level),
ADD INDEX IF NOT EXISTS idx_creato_da (creato_da);

-- Add foreign keys if they don't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'cartelle' 
     AND CONSTRAINT_NAME = 'fk_cartelle_iso_template') = 0,
    'ALTER TABLE cartelle ADD CONSTRAINT fk_cartelle_iso_template FOREIGN KEY (iso_template_id) REFERENCES iso_folder_templates(id) ON DELETE SET NULL',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'cartelle' 
     AND CONSTRAINT_NAME = 'fk_cartelle_creato_da') = 0,
    'ALTER TABLE cartelle ADD CONSTRAINT fk_cartelle_creato_da FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==================================================
-- INITIAL DATA POPULATION
-- ==================================================

-- Insert standard ISO standards
INSERT IGNORE INTO iso_standards (codice, nome, descrizione, versione, attivo) VALUES
('ISO9001', 'ISO 9001 - Sistema di Gestione per la Qualità', 'Standard internazionale per i sistemi di gestione della qualità', '2015', TRUE),
('ISO14001', 'ISO 14001 - Sistema di Gestione Ambientale', 'Standard per i sistemi di gestione ambientale', '2015', TRUE),
('ISO45001', 'ISO 45001 - Sistema di Gestione Salute e Sicurezza', 'Standard per i sistemi di gestione della salute e sicurezza sul lavoro', '2018', TRUE),
('GDPR', 'GDPR - Regolamento Generale sulla Protezione dei Dati', 'Regolamento europeo per la protezione dei dati personali', '2018', TRUE),
('ISO27001', 'ISO 27001 - Sistema di Gestione Sicurezza Informazioni', 'Standard per i sistemi di gestione della sicurezza delle informazioni', '2022', TRUE);

-- Create folder templates for ISO 9001
INSERT IGNORE INTO iso_folder_templates (standard_id, codice, nome, nome_inglese, descrizione, parent_template_id, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'QMS_ROOT', 'Sistema di Gestione Qualità', 'Quality Management System', 'Cartella principale per ISO 9001', NULL, 1, 1, 'fa-award', '#3b82f6', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'MANUAL_SISTEMA', 'Manuale Sistema', 'System Manual', 'Manuale del Sistema di Gestione per la Qualità', 1, 2, 1, 'fa-book', '#10b981', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'POLITICHE', 'Politiche', 'Policies', 'Politiche aziendali per la qualità', 1, 2, 2, 'fa-gavel', '#f59e0b', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'PROCEDURE', 'Procedure', 'Procedures', 'Procedure operative del sistema qualità', 1, 2, 3, 'fa-list-ol', '#8b5cf6', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'MODULI_REGISTRAZIONI', 'Moduli e Registrazioni', 'Forms and Records', 'Moduli e registrazioni del sistema qualità', 1, 2, 4, 'fa-file-alt', '#ef4444', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'AUDIT', 'Audit', 'Audits', 'Documentazione audit interni ed esterni', 1, 2, 5, 'fa-search', '#06b6d4', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'NON_CONFORMITA', 'Non Conformità', 'Non Conformities', 'Gestione non conformità e azioni correttive', 1, 2, 6, 'fa-exclamation-triangle', '#dc2626', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'AZIONI_MIGLIORAMENTO', 'Azioni di Miglioramento', 'Improvement Actions', 'Azioni di miglioramento continuo', 1, 2, 7, 'fa-arrow-up', '#059669', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'RIESAME_DIREZIONE', 'Riesame della Direzione', 'Management Review', 'Documentazione riesami della direzione', 1, 2, 8, 'fa-users-cog', '#7c3aed', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'FORMAZIONE', 'Formazione', 'Training', 'Documentazione formazione e competenze', 1, 2, 9, 'fa-graduation-cap', '#0891b2', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'GESTIONE_FORNITORI', 'Gestione Fornitori', 'Supplier Management', 'Valutazione e gestione fornitori', 1, 2, 10, 'fa-truck', '#ea580c', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO9001'), 'INDICATORI_KPI', 'Indicatori e KPI', 'Indicators and KPIs', 'Indicatori di prestazione e monitoraggio', 1, 2, 11, 'fa-chart-line', '#15803d', TRUE);

-- Create folder templates for ISO 14001
INSERT IGNORE INTO iso_folder_templates (standard_id, codice, nome, nome_inglese, descrizione, parent_template_id, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'EMS_ROOT', 'Sistema di Gestione Ambientale', 'Environmental Management System', 'Cartella principale per ISO 14001', NULL, 1, 1, 'fa-leaf', '#16a34a', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'MANUAL_AMBIENTALE', 'Manuale Ambientale', 'Environmental Manual', 'Manuale del Sistema di Gestione Ambientale', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 1, 'fa-book', '#10b981', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'POLITICA_AMBIENTALE', 'Politica Ambientale', 'Environmental Policy', 'Politica ambientale aziendale', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 2, 'fa-gavel', '#059669', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'PROCEDURE_AMBIENTALI', 'Procedure Ambientali', 'Environmental Procedures', 'Procedure operative ambientali', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 3, 'fa-list-ol', '#16a34a', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'ASPETTI_IMPATTI', 'Aspetti e Impatti Ambientali', 'Environmental Aspects and Impacts', 'Identificazione e valutazione aspetti ambientali', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 4, 'fa-globe', '#22c55e', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'OBIETTIVI_AMBIENTALI', 'Obiettivi Ambientali', 'Environmental Objectives', 'Obiettivi e traguardi ambientali', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 5, 'fa-bullseye', '#15803d', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'CONFORMITA_LEGALE', 'Conformità Legale', 'Legal Compliance', 'Adempimenti legislativi ambientali', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 6, 'fa-balance-scale', '#0f766e', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO14001'), 'EMERGENZE_AMBIENTALI', 'Emergenze Ambientali', 'Environmental Emergencies', 'Procedure per emergenze ambientali', (SELECT id FROM iso_folder_templates WHERE codice = 'EMS_ROOT'), 2, 7, 'fa-exclamation-circle', '#dc2626', TRUE);

-- Create folder templates for ISO 45001
INSERT IGNORE INTO iso_folder_templates (standard_id, codice, nome, nome_inglese, descrizione, parent_template_id, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'OHSMS_ROOT', 'Sistema Gestione Salute e Sicurezza', 'OH&S Management System', 'Cartella principale per ISO 45001', NULL, 1, 1, 'fa-hard-hat', '#dc2626', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'MANUAL_SICUREZZA', 'Manuale Sicurezza', 'Safety Manual', 'Manuale del Sistema di Gestione SSL', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 1, 'fa-book', '#dc2626', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'POLITICA_SICUREZZA', 'Politica di Sicurezza', 'Safety Policy', 'Politica per la salute e sicurezza', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 2, 'fa-shield-alt', '#b91c1c', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'VALUTAZIONE_RISCHI', 'Valutazione dei Rischi', 'Risk Assessment', 'Valutazione rischi e opportunità SSL', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 3, 'fa-exclamation-triangle', '#ef4444', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'DPI_ATTREZZATURE', 'DPI e Attrezzature', 'PPE and Equipment', 'Dispositivi protezione individuale e attrezzature', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 4, 'fa-tools', '#f97316', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'FORMAZIONE_SICUREZZA', 'Formazione Sicurezza', 'Safety Training', 'Formazione e addestramento sicurezza', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 5, 'fa-user-graduate', '#0891b2', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'INCIDENTI_INFORTUNI', 'Incidenti e Infortuni', 'Incidents and Injuries', 'Gestione incidenti e infortuni', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 6, 'fa-ambulance', '#be185d', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'ISO45001'), 'SORVEGLIANZA_SALUTE', 'Sorveglianza Sanitaria', 'Health Surveillance', 'Sorveglianza sanitaria lavoratori', (SELECT id FROM iso_folder_templates WHERE codice = 'OHSMS_ROOT'), 2, 7, 'fa-user-md', '#16a34a', TRUE);

-- Create folder templates for GDPR
INSERT IGNORE INTO iso_folder_templates (standard_id, codice, nome, nome_inglese, descrizione, parent_template_id, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'GDPR_ROOT', 'Protezione Dati Personali', 'Personal Data Protection', 'Cartella principale per GDPR', NULL, 1, 1, 'fa-user-shield', '#6366f1', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'POLITICHE_PRIVACY', 'Politiche Privacy', 'Privacy Policies', 'Politiche per la protezione dati personali', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 1, 'fa-user-secret', '#4f46e5', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'REGISTRI_TRATTAMENTI', 'Registri dei Trattamenti', 'Processing Records', 'Registri delle attività di trattamento', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 2, 'fa-list', '#5b21b6', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'INFORMATIVE_CONSENSI', 'Informative e Consensi', 'Information and Consents', 'Informative privacy e consensi', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 3, 'fa-file-contract', '#7c3aed', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'DPIA_VALUTAZIONI', 'DPIA e Valutazioni', 'DPIA and Assessments', 'Data Protection Impact Assessment', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 4, 'fa-clipboard-check', '#8b5cf6', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'DATA_BREACH', 'Data Breach', 'Data Breaches', 'Gestione violazioni dati personali', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 5, 'fa-shield-virus', '#dc2626', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'DIRITTI_INTERESSATI', 'Diritti degli Interessati', 'Data Subject Rights', 'Gestione diritti degli interessati', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 6, 'fa-hand-paper', '#059669', TRUE),
((SELECT id FROM iso_standards WHERE codice = 'GDPR'), 'FORNITORI_DPO', 'Fornitori e DPO', 'Suppliers and DPO', 'Documentazione fornitori e DPO', (SELECT id FROM iso_folder_templates WHERE codice = 'GDPR_ROOT'), 2, 7, 'fa-handshake', '#0891b2', TRUE);

-- ==================================================
-- SYSTEM CONFIGURATION AND PERFORMANCE
-- ==================================================

-- Create indexes for optimal performance
CREATE INDEX IF NOT EXISTS idx_cartelle_compound ON cartelle(azienda_id, parent_id, iso_standard_codice);
CREATE INDEX IF NOT EXISTS idx_cartelle_path ON cartelle(percorso_completo(255));
CREATE INDEX IF NOT EXISTS idx_iso_folders_compound ON aziende_iso_folders(azienda_id, standard_codice, stato);

-- Create views for easier data access
CREATE OR REPLACE VIEW v_iso_structure_overview AS
SELECT 
    a.id as azienda_id,
    a.nome as azienda_nome,
    aic.tipo_struttura,
    aic.standards_attivi,
    aic.stato as config_stato,
    COUNT(aif.id) as cartelle_iso_create,
    GROUP_CONCAT(DISTINCT aif.standard_codice) as standards_implementati
FROM aziende a
LEFT JOIN aziende_iso_config aic ON a.id = aic.azienda_id
LEFT JOIN aziende_iso_folders aif ON a.id = aif.azienda_id AND aif.stato = 'attiva'
GROUP BY a.id, a.nome, aic.tipo_struttura, aic.standards_attivi, aic.stato;

-- ==================================================
-- DATA INTEGRITY AND SECURITY
-- ==================================================

-- Triggers for audit trail
DELIMITER //

CREATE TRIGGER IF NOT EXISTS tr_iso_config_audit 
AFTER INSERT ON aziende_iso_config
FOR EACH ROW
BEGIN
    INSERT INTO iso_deployment_log (
        azienda_id, 
        operazione, 
        standard_coinvolti, 
        dettagli_operazione, 
        risultato, 
        eseguito_da
    ) VALUES (
        NEW.azienda_id, 
        'creazione_iniziale',
        NEW.standards_attivi,
        JSON_OBJECT('tipo_struttura', NEW.tipo_struttura, 'configurazione_avanzata', NEW.configurazione_avanzata),
        'successo',
        NEW.creato_da
    );
END//

CREATE TRIGGER IF NOT EXISTS tr_iso_config_update_audit 
AFTER UPDATE ON aziende_iso_config
FOR EACH ROW
BEGIN
    INSERT INTO iso_deployment_log (
        azienda_id, 
        operazione, 
        standard_coinvolti, 
        dettagli_operazione, 
        risultato, 
        eseguito_da
    ) VALUES (
        NEW.azienda_id, 
        'aggiornamento_struttura',
        NEW.standards_attivi,
        JSON_OBJECT(
            'vecchio_tipo', OLD.tipo_struttura, 
            'nuovo_tipo', NEW.tipo_struttura,
            'vecchi_standards', OLD.standards_attivi,
            'nuovi_standards', NEW.standards_attivi
        ),
        'successo',
        NEW.creato_da
    );
END//

DELIMITER ;

-- ==================================================
-- COMPLETION CONFIRMATION
-- ==================================================

-- Insert a configuration validation record
INSERT INTO iso_deployment_log (
    azienda_id,
    operazione,
    standard_coinvolti,
    dettagli_operazione,
    risultato,
    eseguito_da,
    data_esecuzione
) VALUES (
    1, -- System installation
    'creazione_iniziale',
    '["ISO9001", "ISO14001", "ISO45001", "GDPR"]',
    JSON_OBJECT(
        'action', 'schema_installation',
        'version', '1.0.0',
        'tables_created', JSON_ARRAY(
            'iso_standards',
            'aziende_iso_config', 
            'iso_folder_templates',
            'aziende_iso_folders',
            'iso_document_classifications',
            'iso_deployment_log',
            'iso_compliance_check'
        ),
        'templates_created', 36,
        'standards_configured', 4
    ),
    'successo',
    1
);

-- Final success message
SELECT 'ISO Document Structure System - Database schema created successfully!' as status,
       COUNT(*) as total_templates_created
FROM iso_folder_templates;