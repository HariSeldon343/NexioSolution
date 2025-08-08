-- NEXIO COMPLETE MULTI-STANDARD DOCUMENT SYSTEM
-- Schema database completo per gestione documentale multi-norma
-- Supporta: ISO 9001, 14001, 45001, GDPR, strutture personalizzate
-- Versione: 3.0 - Gennaio 2025
-- Compatibile con: MySQL 8.0+, Nexio Platform

USE NexioSol;

SET FOREIGN_KEY_CHECKS = 0;

-- =======================================================================================
-- 1. SISTEMA MULTI-TENANT E STANDARD DOCUMENTALI
-- =======================================================================================

-- Drop existing tables in correct order to avoid FK issues
DROP TABLE IF EXISTS document_audit_trail;
DROP TABLE IF EXISTS document_compliance_checks;
DROP TABLE IF EXISTS document_search_index;
DROP TABLE IF EXISTS document_permissions;
DROP TABLE IF EXISTS document_versions;
DROP TABLE IF EXISTS document_sharing;
DROP TABLE IF EXISTS folder_permissions;
DROP TABLE IF EXISTS company_document_structures;
DROP TABLE IF EXISTS document_structure_folders;
DROP TABLE IF EXISTS document_structure_templates;
DROP TABLE IF EXISTS document_standards;

-- Tabella standard documentali (norme supportate)
CREATE TABLE document_standards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(20) NOT NULL UNIQUE COMMENT 'Codice standard (ISO9001, ISO14001, ISO45001, GDPR)',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome descrittivo dello standard',
    descrizione TEXT COMMENT 'Descrizione dettagliata',
    versione VARCHAR(20) COMMENT 'Versione standard (es. 2015, 2018)',
    categoria ENUM('iso', 'privacy', 'sicurezza', 'qualita', 'ambiente', 'custom') DEFAULT 'iso',
    icona VARCHAR(50) DEFAULT 'fa-certificate' COMMENT 'Icona Font Awesome',
    colore VARCHAR(7) DEFAULT '#3b82f6' COMMENT 'Colore esadecimale',
    attivo BOOLEAN DEFAULT TRUE,
    compatibile_con JSON COMMENT 'Array di standard compatibili per struttura integrata',
    metadata_schema JSON COMMENT 'Schema metadati specifici per lo standard',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_standard_attivo (attivo),
    INDEX idx_standard_categoria (categoria),
    INDEX idx_standard_codice (codice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Template strutture documentali per ogni standard
CREATE TABLE document_structure_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    standard_id INT NOT NULL,
    parent_template_id INT NULL COMMENT 'Per struttura gerarchica',
    codice VARCHAR(50) NOT NULL COMMENT 'Codice identificativo cartella',
    nome VARCHAR(200) NOT NULL COMMENT 'Nome cartella visualizzato',
    descrizione TEXT,
    livello INT DEFAULT 1 COMMENT 'Livello gerarchico (1=root, 2=principale, 3=sotto)',
    ordine_visualizzazione INT DEFAULT 0,
    icona VARCHAR(50) DEFAULT 'fa-folder',
    colore VARCHAR(7) DEFAULT '#fbbf24',
    obbligatoria BOOLEAN DEFAULT FALSE COMMENT 'Se la cartella è obbligatoria',
    configurabile BOOLEAN DEFAULT TRUE COMMENT 'Se può essere personalizzata',
    metadata_template JSON COMMENT 'Template metadati per documenti nella cartella',
    regole_naming JSON COMMENT 'Regole per denominazione documenti',
    template_documenti JSON COMMENT 'Template documenti predefiniti per questa cartella',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (standard_id) REFERENCES document_standards(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_template_id) REFERENCES document_structure_templates(id) ON DELETE CASCADE,
    INDEX idx_template_standard (standard_id),
    INDEX idx_template_parent (parent_template_id),
    INDEX idx_template_livello (standard_id, livello),
    INDEX idx_template_ordine (standard_id, ordine_visualizzazione),
    UNIQUE KEY uk_standard_codice (standard_id, codice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurazione strutture documentali per azienda
CREATE TABLE company_document_structures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    nome_configurazione VARCHAR(200) NOT NULL COMMENT 'Nome della configurazione',
    tipo_struttura ENUM('separata', 'integrata', 'personalizzata') DEFAULT 'separata' COMMENT 'Tipo organizzazione',
    standards_attivi JSON NOT NULL COMMENT 'Array di codici standard attivi',
    configurazione_mapping JSON COMMENT 'Mapping cartelle condivise per struttura integrata',
    personalizzazioni JSON COMMENT 'Personalizzazioni specifiche azienda',
    stato ENUM('bozza', 'attiva', 'sospesa', 'archiviata') DEFAULT 'bozza',
    default_struttura BOOLEAN DEFAULT FALSE COMMENT 'Se è la struttura di default per azienda',
    permessi_default JSON COMMENT 'Permessi di default per nuove cartelle/documenti',
    workflow_approvazione JSON COMMENT 'Workflow di approvazione documenti',
    regole_retention JSON COMMENT 'Regole di conservazione documenti',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_attivazione TIMESTAMP NULL,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creato_da INT,
    modificato_da INT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (modificato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    UNIQUE KEY uk_azienda_default (azienda_id, default_struttura),
    INDEX idx_company_structure_azienda (azienda_id),
    INDEX idx_company_structure_stato (stato),
    INDEX idx_company_structure_tipo (tipo_struttura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cartelle documentali implementate per azienda (link tra template e cartelle reali)
CREATE TABLE document_structure_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_structure_id INT NOT NULL,
    template_id INT NOT NULL,
    cartella_id INT NOT NULL COMMENT 'Link alla tabella cartelle esistente',
    standard_codice VARCHAR(20) NOT NULL,
    percorso_iso VARCHAR(1000) COMMENT 'Percorso ISO completo',
    configurazione_personalizzata JSON COMMENT 'Configurazioni specifiche cartella',
    metadata_cartella JSON COMMENT 'Metadati aggiuntivi',
    regole_locali JSON COMMENT 'Regole specifiche per questa implementazione',
    stato ENUM('attiva', 'disattivata', 'eliminata') DEFAULT 'attiva',
    statistiche JSON COMMENT 'Cache statistiche (num documenti, dimensione, etc.)',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_sincronizzazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_structure_id) REFERENCES company_document_structures(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES document_structure_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    UNIQUE KEY uk_company_template (company_structure_id, template_id),
    UNIQUE KEY uk_company_cartella (company_structure_id, cartella_id),
    INDEX idx_dsf_company_structure (company_structure_id),
    INDEX idx_dsf_template (template_id),
    INDEX idx_dsf_cartella (cartella_id),
    INDEX idx_dsf_standard (standard_codice),
    INDEX idx_dsf_stato (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================================
-- 2. GESTIONE DOCUMENTI AVANZATA
-- =======================================================================================

-- Estensione tabella documenti esistente per supporto avanzato
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS document_structure_folder_id INT COMMENT 'Link alla struttura documentale',
ADD COLUMN IF NOT EXISTS metadata_compliance JSON COMMENT 'Metadati conformità normative',
ADD COLUMN IF NOT EXISTS tags_documenti JSON COMMENT 'Tag per categorizzazione',
ADD COLUMN IF NOT EXISTS checksum_file VARCHAR(64) COMMENT 'Hash SHA-256 del file',
ADD COLUMN IF NOT EXISTS virus_scan_status ENUM('pending', 'clean', 'infected', 'error', 'skipped') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS virus_scan_date TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS full_text_content LONGTEXT COMMENT 'Contenuto estratto per ricerca full-text',
ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(500) COMMENT 'Path thumbnail generata',
ADD COLUMN IF NOT EXISTS preview_available BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS workflow_stato ENUM('bozza', 'in_revisione', 'approvato', 'pubblicato', 'scaduto', 'ritirato') DEFAULT 'bozza',
ADD COLUMN IF NOT EXISTS data_scadenza DATE COMMENT 'Data scadenza documento (per review)',
ADD COLUMN IF NOT EXISTS responsabile_documento INT COMMENT 'Responsabile del documento',
ADD COLUMN IF NOT EXISTS numero_revisione INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS motivo_revisione TEXT,
ADD INDEX IF NOT EXISTS idx_doc_structure_folder (document_structure_folder_id),
ADD INDEX IF NOT EXISTS idx_doc_checksum (checksum_file),
ADD INDEX IF NOT EXISTS idx_doc_workflow (workflow_stato),
ADD INDEX IF NOT EXISTS idx_doc_scadenza (data_scadenza),
ADD INDEX IF NOT EXISTS idx_doc_responsabile (responsabile_documento),
ADD FULLTEXT INDEX IF NOT EXISTS idx_doc_fulltext (titolo, full_text_content);

-- Aggiungi foreign key per document_structure_folder_id
ALTER TABLE documenti 
ADD CONSTRAINT fk_doc_structure_folder 
FOREIGN KEY (document_structure_folder_id) REFERENCES document_structure_folders(id) ON DELETE SET NULL;

-- Versioni documenti con metadati completi
CREATE TABLE document_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    numero_versione INT NOT NULL,
    numero_revisione INT DEFAULT 1,
    file_path VARCHAR(500),
    file_size BIGINT DEFAULT 0,
    mime_type VARCHAR(100),
    checksum_file VARCHAR(64),
    contenuto_estratto LONGTEXT COMMENT 'Contenuto estratto per versione',
    metadata_versione JSON COMMENT 'Metadati specifici versione',
    note_versione TEXT,
    motivo_modifica TEXT,
    modifiche_principali JSON COMMENT 'Elenco modifiche principali',
    stato_approvazione ENUM('in_attesa', 'approvata', 'rifiutata') DEFAULT 'in_attesa',
    approvata_da INT,
    data_approvazione TIMESTAMP NULL,
    note_approvazione TEXT,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (approvata_da) REFERENCES utenti(id) ON DELETE SET NULL,
    UNIQUE KEY uk_doc_version_revision (documento_id, numero_versione, numero_revisione),
    INDEX idx_dv_documento (documento_id),
    INDEX idx_dv_checksum (checksum_file),
    INDEX idx_dv_stato_approvazione (stato_approvazione),
    INDEX idx_dv_data_creazione (data_creazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Condivisioni documenti con controlli avanzati
CREATE TABLE document_sharing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    condiviso_da INT NOT NULL,
    condiviso_con INT NULL COMMENT 'NULL per link pubblici',
    tipo_condivisione ENUM('utente_interno', 'utente_esterno', 'link_pubblico', 'link_tempo', 'gruppo_lavoro') DEFAULT 'utente_interno',
    permessi ENUM('lettura', 'download', 'commento', 'modifica') DEFAULT 'lettura',
    token_accesso VARCHAR(64) UNIQUE COMMENT 'Token per accesso tramite link',
    password_accesso VARCHAR(255) COMMENT 'Password opzionale per link',
    data_scadenza TIMESTAMP NULL,
    numero_accessi_max INT DEFAULT 0 COMMENT '0 = illimitati',
    numero_accessi_effettuati INT DEFAULT 0,
    restrizioni_ip JSON COMMENT 'Array di IP/range autorizzati',
    watermark_enabled BOOLEAN DEFAULT FALSE,
    download_enabled BOOLEAN DEFAULT TRUE,
    notifiche_accesso BOOLEAN DEFAULT FALSE,
    attiva BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso TIMESTAMP NULL,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (condiviso_da) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (condiviso_con) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_ds_documento (documento_id),
    INDEX idx_ds_token (token_accesso),
    INDEX idx_ds_condiviso_con (condiviso_con),
    INDEX idx_ds_scadenza (data_scadenza),
    INDEX idx_ds_attiva (attiva)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================================
-- 3. SISTEMA PERMESSI GRANULARI
-- =======================================================================================

-- Permessi per cartelle
CREATE TABLE folder_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cartella_id INT NOT NULL,
    utente_id INT NULL,
    ruolo VARCHAR(50) NULL COMMENT 'NULL per utente specifico, ruolo per permessi di ruolo',
    gruppo_lavoro VARCHAR(100) NULL COMMENT 'Gruppo di lavoro specifico',
    permessi JSON NOT NULL COMMENT 'Permessi dettagliati: {read, write, delete, share, admin}',
    eredita_da_parent BOOLEAN DEFAULT TRUE,
    applica_a_sottocartelle BOOLEAN DEFAULT TRUE,
    applica_a_documenti BOOLEAN DEFAULT TRUE,
    data_scadenza TIMESTAMP NULL,
    attivo BOOLEAN DEFAULT TRUE,
    assegnato_da INT,
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (assegnato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_fp_cartella (cartella_id),
    INDEX idx_fp_utente (utente_id),
    INDEX idx_fp_ruolo (ruolo),
    INDEX idx_fp_attivo (attivo),
    INDEX idx_fp_scadenza (data_scadenza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permessi per documenti
CREATE TABLE document_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    utente_id INT NULL,
    ruolo VARCHAR(50) NULL,
    gruppo_lavoro VARCHAR(100) NULL,
    permessi JSON NOT NULL COMMENT 'Permessi dettagliati per documento',
    eredita_da_cartella BOOLEAN DEFAULT TRUE,
    override_eredita JSON COMMENT 'Permessi che sovrascrivono eredità',
    data_scadenza TIMESTAMP NULL,
    attivo BOOLEAN DEFAULT TRUE,
    assegnato_da INT,
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (assegnato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_dp_documento (documento_id),
    INDEX idx_dp_utente (utente_id),
    INDEX idx_dp_ruolo (ruolo),
    INDEX idx_dp_attivo (attivo),
    INDEX idx_dp_scadenza (data_scadenza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================================
-- 4. RICERCA E INDICIZZAZIONE
-- =======================================================================================

-- Indice di ricerca per performance ottimizzate
CREATE TABLE document_search_index (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    titolo_tokens TEXT COMMENT 'Token estratti dal titolo',
    contenuto_tokens TEXT COMMENT 'Token estratti dal contenuto',
    tags_tokens TEXT COMMENT 'Token estratti dai tag',
    metadata_tokens TEXT COMMENT 'Token estratti dai metadati',
    full_tokens TEXT COMMENT 'Tutti i token combinati',
    lingua_documento VARCHAR(5) DEFAULT 'it' COMMENT 'Lingua per stemming',
    peso_rilevanza DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Peso per ranking risultati',
    data_indicizzazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    UNIQUE KEY uk_search_documento (documento_id),
    FULLTEXT INDEX idx_search_full (full_tokens),
    FULLTEXT INDEX idx_search_titolo (titolo_tokens),
    FULLTEXT INDEX idx_search_contenuto (contenuto_tokens),
    INDEX idx_search_lingua (lingua_documento),
    INDEX idx_search_peso (peso_rilevanza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================================
-- 5. COMPLIANCE E AUDIT TRAIL
-- =======================================================================================

-- Verifiche di conformità documenti
CREATE TABLE document_compliance_checks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    standard_codice VARCHAR(20) NOT NULL,
    tipo_verifica ENUM('automatica', 'manuale', 'scheduled', 'audit') DEFAULT 'automatica',
    stato_conformita ENUM('conforme', 'non_conforme', 'parzialmente_conforme', 'da_verificare', 'esentato') DEFAULT 'da_verificare',
    punteggio_conformita DECIMAL(5,2) COMMENT 'Da 0.00 a 100.00',
    requisiti_verificati JSON COMMENT 'Lista requisiti verificati con esito',
    non_conformita_trovate JSON COMMENT 'Lista non conformità rilevate',
    azioni_correttive JSON COMMENT 'Azioni correttive da intraprendere',
    scadenza_azioni DATE COMMENT 'Scadenza per azioni correttive',
    verificato_da INT,
    data_verifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_prossima_verifica DATE,
    note_verificatore TEXT,
    allegati_verifica JSON COMMENT 'Eventuali allegati alla verifica',
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (verificato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_dcc_documento (documento_id),
    INDEX idx_dcc_standard (standard_codice),
    INDEX idx_dcc_stato (stato_conformita),
    INDEX idx_dcc_data_verifica (data_verifica),
    INDEX idx_dcc_prossima_verifica (data_prossima_verifica),
    INDEX idx_dcc_scadenza_azioni (scadenza_azioni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail completo per documenti
CREATE TABLE document_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    utente_id INT,
    azione ENUM('create', 'read', 'update', 'delete', 'download', 'share', 'approve', 'reject', 'archive', 'restore') NOT NULL,
    dettagli_azione JSON COMMENT 'Dettagli specifici azione',
    valori_precedenti JSON COMMENT 'Valori prima della modifica',
    valori_nuovi JSON COMMENT 'Valori dopo la modifica',
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    api_endpoint VARCHAR(255) COMMENT 'Endpoint API utilizzato',
    tempo_esecuzione DECIMAL(8,3) COMMENT 'Tempo esecuzione in millisecondi',
    risultato ENUM('successo', 'fallito', 'parziale') DEFAULT 'successo',
    messaggio_errore TEXT,
    data_azione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_dat_documento (documento_id),
    INDEX idx_dat_utente (utente_id),
    INDEX idx_dat_azione (azione),
    INDEX idx_dat_data (data_azione),
    INDEX idx_dat_ip (ip_address),
    INDEX idx_dat_risultato (risultato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================================
-- 6. DATI PREDEFINITI STANDARD DOCUMENTALI
-- =======================================================================================

-- Inserimento standard documentali
INSERT INTO document_standards (codice, nome, descrizione, versione, categoria, icona, colore, compatibile_con, metadata_schema) VALUES
('ISO9001', 'ISO 9001:2015', 'Sistema di Gestione della Qualità', '2015', 'qualita', 'fa-award', '#3b82f6', 
 JSON_ARRAY('ISO14001', 'ISO45001'), 
 JSON_OBJECT('required_fields', JSON_ARRAY('responsabile', 'data_approvazione'), 'optional_fields', JSON_ARRAY('revisore', 'data_revisione'))),

('ISO14001', 'ISO 14001:2015', 'Sistema di Gestione Ambientale', '2015', 'ambiente', 'fa-leaf', '#10b981',
 JSON_ARRAY('ISO9001', 'ISO45001'),
 JSON_OBJECT('required_fields', JSON_ARRAY('responsabile_ambientale', 'aspetti_ambientali'), 'optional_fields', JSON_ARRAY('impatti_ambientali'))),

('ISO45001', 'ISO 45001:2018', 'Sistema di Gestione della Salute e Sicurezza sul Lavoro', '2018', 'sicurezza', 'fa-shield-alt', '#dc2626',
 JSON_ARRAY('ISO9001', 'ISO14001'),
 JSON_OBJECT('required_fields', JSON_ARRAY('responsabile_ssl', 'valutazione_rischi'), 'optional_fields', JSON_ARRAY('misure_prevenzione'))),

('GDPR', 'GDPR 2016/679', 'Regolamento Generale sulla Protezione dei Dati', '2016/679', 'privacy', 'fa-user-shield', '#6366f1',
 JSON_ARRAY(),
 JSON_OBJECT('required_fields', JSON_ARRAY('dpo', 'base_giuridica', 'categorie_dati'), 'optional_fields', JSON_ARRAY('durata_conservazione'))),

('ISO27001', 'ISO 27001:2013', 'Sistema di Gestione della Sicurezza delle Informazioni', '2013', 'sicurezza', 'fa-lock', '#8b5cf6',
 JSON_ARRAY('GDPR'),
 JSON_OBJECT('required_fields', JSON_ARRAY('classification', 'access_level'), 'optional_fields', JSON_ARRAY('encryption_required')));

-- Template strutture per ISO 9001
SET @iso9001_id = (SELECT id FROM document_standards WHERE codice = 'ISO9001');

INSERT INTO document_structure_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria, configurabile, metadata_template) VALUES
(@iso9001_id, NULL, 'ISO9001_ROOT', 'Sistema Qualità ISO 9001', 'Struttura principale per ISO 9001:2015', 1, 1, 'fa-award', '#3b82f6', TRUE, FALSE, 
 JSON_OBJECT('template_fields', JSON_ARRAY('responsabile_qualita', 'data_implementazione'))),

(@iso9001_id, LAST_INSERT_ID(), 'MANUALE_SISTEMA', 'Manuale del Sistema Qualità', 'Manuale del Sistema di Gestione della Qualità', 2, 1, 'fa-book', '#059669', TRUE, TRUE,
 JSON_OBJECT('templates', JSON_ARRAY('Manuale_Qualita_Template.docx'), 'naming_pattern', 'MQ_{version}_{date}')),

(@iso9001_id, @iso9001_id+1, 'POLITICHE_QUALITA', 'Politiche per la Qualità', 'Politiche aziendali relative alla qualità', 2, 2, 'fa-file-alt', '#7c3aed', TRUE, TRUE,
 JSON_OBJECT('templates', JSON_ARRAY('Politica_Qualita_Template.docx'), 'approval_required', TRUE)),

(@iso9001_id, @iso9001_id+1, 'PROCEDURE', 'Procedure Operative', 'Procedure operative standard', 2, 3, 'fa-tasks', '#dc2626', TRUE, TRUE,
 JSON_OBJECT('subfolders', JSON_ARRAY('Gestione_Documenti', 'Gestione_Risorse', 'Processi_Operativi'))),

(@iso9001_id, @iso9001_id+1, 'MODULI_REGISTRAZIONI', 'Moduli e Registrazioni', 'Moduli e registrazioni del sistema qualità', 2, 4, 'fa-clipboard-list', '#f59e0b', FALSE, TRUE,
 JSON_OBJECT('auto_numbering', TRUE, 'retention_period', 2555)),

(@iso9001_id, @iso9001_id+1, 'AUDIT_QUALITA', 'Audit Interni', 'Programma e report audit interni', 2, 5, 'fa-search', '#8b5cf6', TRUE, TRUE,
 JSON_OBJECT('annual_planning', TRUE, 'report_templates', JSON_ARRAY('Rapporto_Audit_Template.docx'))),

(@iso9001_id, @iso9001_id+1, 'NON_CONFORMITA', 'Non Conformità', 'Gestione delle non conformità e azioni correttive', 2, 6, 'fa-exclamation-triangle', '#ef4444', TRUE, TRUE,
 JSON_OBJECT('auto_numbering', TRUE, 'severity_levels', JSON_ARRAY('minore', 'maggiore', 'critica'))),

(@iso9001_id, @iso9001_id+1, 'AZIONI_MIGLIORAMENTO', 'Azioni di Miglioramento', 'Azioni di miglioramento continuo', 2, 7, 'fa-chart-line', '#10b981', FALSE, TRUE,
 JSON_OBJECT('tracking_required', TRUE, 'effectiveness_review', TRUE)),

(@iso9001_id, @iso9001_id+1, 'RIESAME_DIREZIONE', 'Riesame della Direzione', 'Documenti del riesame della direzione', 2, 8, 'fa-users-cog', '#06b6d4', TRUE, TRUE,
 JSON_OBJECT('frequency', 'annual', 'participants_required', JSON_ARRAY('top_management', 'quality_manager'))),

(@iso9001_id, @iso9001_id+1, 'FORMAZIONE_QUALITA', 'Formazione e Competenze', 'Programmi formativi e registrazione competenze', 2, 9, 'fa-graduation-cap', '#3b82f6', FALSE, TRUE,
 JSON_OBJECT('competence_matrix', TRUE, 'training_records', TRUE)),

(@iso9001_id, @iso9001_id+1, 'GESTIONE_FORNITORI', 'Gestione Fornitori', 'Qualificazione e monitoraggio fornitori', 2, 10, 'fa-handshake', '#f59e0b', FALSE, TRUE,
 JSON_OBJECT('vendor_qualification', TRUE, 'performance_monitoring', TRUE)),

(@iso9001_id, @iso9001_id+1, 'INDICATORI_KPI', 'Indicatori e KPI', 'Indicatori di performance e monitoraggio', 2, 11, 'fa-chart-bar', '#8b5cf6', FALSE, TRUE,
 JSON_OBJECT('dashboard_integration', TRUE, 'automated_reports', TRUE));

-- Template strutture per ISO 14001
SET @iso14001_id = (SELECT id FROM document_standards WHERE codice = 'ISO14001');

INSERT INTO document_structure_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria, configurabile, metadata_template) VALUES
(@iso14001_id, NULL, 'ISO14001_ROOT', 'Sistema Gestione Ambientale', 'Struttura principale per ISO 14001:2015', 1, 1, 'fa-leaf', '#10b981', TRUE, FALSE,
 JSON_OBJECT('environmental_aspects', TRUE, 'legal_compliance', TRUE)),

(@iso14001_id, LAST_INSERT_ID(), 'MANUALE_AMBIENTALE', 'Manuale Ambientale', 'Manuale del Sistema di Gestione Ambientale', 2, 1, 'fa-book', '#059669', TRUE, TRUE,
 JSON_OBJECT('policy_integration', TRUE, 'scope_definition', TRUE)),

(@iso14001_id, @iso14001_id+1, 'POLITICA_AMBIENTALE', 'Politica Ambientale', 'Politica ambientale dell\'organizzazione', 2, 2, 'fa-file-alt', '#7c3aed', TRUE, TRUE,
 JSON_OBJECT('top_management_commitment', TRUE, 'public_availability', TRUE)),

(@iso14001_id, @iso14001_id+1, 'ASPETTI_AMBIENTALI', 'Aspetti e Impatti Ambientali', 'Identificazione e valutazione aspetti ambientali', 2, 3, 'fa-leaf', '#10b981', TRUE, TRUE,
 JSON_OBJECT('significance_criteria', TRUE, 'impact_assessment', TRUE)),

(@iso14001_id, @iso14001_id+1, 'CONFORMITA_LEGALE', 'Conformità Legislativa', 'Conformità alle prescrizioni legali ambientali', 2, 4, 'fa-balance-scale', '#f59e0b', TRUE, TRUE,
 JSON_OBJECT('legal_register', TRUE, 'compliance_evaluation', TRUE)),

(@iso14001_id, @iso14001_id+1, 'OBIETTIVI_AMBIENTALI', 'Obiettivi e Programmi', 'Obiettivi ambientali e programmi di miglioramento', 2, 5, 'fa-target', '#3b82f6', TRUE, TRUE,
 JSON_OBJECT('smart_objectives', TRUE, 'progress_monitoring', TRUE)),

(@iso14001_id, @iso14001_id+1, 'EMERGENZE_AMBIENTALI', 'Emergenze Ambientali', 'Preparazione e risposta alle emergenze', 2, 6, 'fa-exclamation-circle', '#ef4444', TRUE, TRUE,
 JSON_OBJECT('emergency_procedures', TRUE, 'response_plan', TRUE)),

(@iso14001_id, @iso14001_id+1, 'MONITORAGGIO_AMBIENTALE', 'Monitoraggio e Misurazione', 'Monitoraggio delle prestazioni ambientali', 2, 7, 'fa-chart-line', '#8b5cf6', TRUE, TRUE,
 JSON_OBJECT('kpi_environmental', TRUE, 'measurement_methods', TRUE));

-- Template strutture per ISO 45001
SET @iso45001_id = (SELECT id FROM document_standards WHERE codice = 'ISO45001');

INSERT INTO document_structure_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria, configurabile, metadata_template) VALUES
(@iso45001_id, NULL, 'ISO45001_ROOT', 'Sistema SSL ISO 45001', 'Struttura principale per ISO 45001:2018', 1, 1, 'fa-shield-alt', '#dc2626', TRUE, FALSE,
 JSON_OBJECT('worker_participation', TRUE, 'risk_assessment', TRUE)),

(@iso45001_id, LAST_INSERT_ID(), 'MANUALE_SSL', 'Manuale SSL', 'Manuale del Sistema di Gestione SSL', 2, 1, 'fa-book', '#059669', TRUE, TRUE,
 JSON_OBJECT('context_organization', TRUE, 'worker_consultation', TRUE)),

(@iso45001_id, @iso45001_id+1, 'POLITICA_SSL', 'Politica SSL', 'Politica per la salute e sicurezza sul lavoro', 2, 2, 'fa-file-alt', '#7c3aed', TRUE, TRUE,
 JSON_OBJECT('management_commitment', TRUE, 'consultation_requirements', TRUE)),

(@iso45001_id, @iso45001_id+1, 'VALUTAZIONE_RISCHI', 'Valutazione dei Rischi', 'Identificazione pericoli e valutazione rischi', 2, 3, 'fa-exclamation-triangle', '#dc2626', TRUE, TRUE,
 JSON_OBJECT('hazard_identification', TRUE, 'risk_assessment_methodology', TRUE)),

(@iso45001_id, @iso45001_id+1, 'PROCEDURE_SSL', 'Procedure SSL', 'Procedure operative per la sicurezza', 2, 4, 'fa-tasks', '#f59e0b', TRUE, TRUE,
 JSON_OBJECT('work_instructions', TRUE, 'emergency_procedures', TRUE)),

(@iso45001_id, @iso45001_id+1, 'FORMAZIONE_SSL', 'Formazione e Competenze SSL', 'Formazione e sviluppo competenze SSL', 2, 5, 'fa-graduation-cap', '#3b82f6', TRUE, TRUE,
 JSON_OBJECT('competence_requirements', TRUE, 'training_records', TRUE)),

(@iso45001_id, @iso45001_id+1, 'SORVEGLIANZA_SANITARIA', 'Sorveglianza Sanitaria', 'Sorveglianza sanitaria dei lavoratori', 2, 6, 'fa-heartbeat', '#ec4899', TRUE, TRUE,
 JSON_OBJECT('health_surveillance', TRUE, 'medical_records', TRUE)),

(@iso45001_id, @iso45001_id+1, 'INCIDENTI_INFORTUNI', 'Incidenti e Infortuni', 'Gestione incidenti e investigazione', 2, 7, 'fa-ambulance', '#ef4444', TRUE, TRUE,
 JSON_OBJECT('incident_reporting', TRUE, 'investigation_process', TRUE)),

(@iso45001_id, @iso45001_id+1, 'DPI_ATTREZZATURE', 'DPI e Attrezzature', 'Gestione DPI e attrezzature di lavoro', 2, 8, 'fa-hard-hat', '#f59e0b', FALSE, TRUE,
 JSON_OBJECT('ppe_management', TRUE, 'equipment_maintenance', TRUE));

-- Template strutture per GDPR
SET @gdpr_id = (SELECT id FROM document_standards WHERE codice = 'GDPR');

INSERT INTO document_structure_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria, configurabile, metadata_template) VALUES
(@gdpr_id, NULL, 'GDPR_ROOT', 'Privacy e Protezione Dati', 'Struttura principale per conformità GDPR', 1, 1, 'fa-user-shield', '#6366f1', TRUE, FALSE,
 JSON_OBJECT('data_protection', TRUE, 'privacy_by_design', TRUE)),

(@gdpr_id, LAST_INSERT_ID(), 'POLITICHE_PRIVACY', 'Politiche Privacy', 'Politiche sulla privacy e protezione dati', 2, 1, 'fa-file-alt', '#7c3aed', TRUE, TRUE,
 JSON_OBJECT('privacy_policy', TRUE, 'cookie_policy', TRUE)),

(@gdpr_id, @gdpr_id+1, 'REGISTRI_TRATTAMENTO', 'Registri delle Attività', 'Registri delle attività di trattamento', 2, 2, 'fa-clipboard-list', '#059669', TRUE, TRUE,
 JSON_OBJECT('processing_records', TRUE, 'legal_basis', TRUE)),

(@gdpr_id, @gdpr_id+1, 'CONSENSI', 'Gestione Consensi', 'Gestione dei consensi al trattamento', 2, 3, 'fa-check-square', '#10b981', TRUE, TRUE,
 JSON_OBJECT('consent_management', TRUE, 'withdrawal_process', TRUE)),

(@gdpr_id, @gdpr_id+1, 'VALUTAZIONI_IMPATTO', 'DPIA', 'Data Protection Impact Assessment', 2, 4, 'fa-chart-line', '#f59e0b', TRUE, TRUE,
 JSON_OBJECT('dpia_methodology', TRUE, 'risk_assessment', TRUE)),

(@gdpr_id, @gdpr_id+1, 'VIOLAZIONI_DATI', 'Data Breach', 'Registro violazioni e notifiche', 2, 5, 'fa-exclamation-triangle', '#ef4444', TRUE, TRUE,
 JSON_OBJECT('breach_notification', TRUE, '72h_rule', TRUE)),

(@gdpr_id, @gdpr_id+1, 'DIRITTI_INTERESSATI', 'Diritti degli Interessati', 'Gestione richieste diritti GDPR', 2, 6, 'fa-users', '#3b82f6', TRUE, TRUE,
 JSON_OBJECT('rights_management', TRUE, 'response_timeframes', TRUE)),

(@gdpr_id, @gdpr_id+1, 'CONTRATTI_FORNITORI', 'Contratti e DPA', 'Data Processing Agreement con fornitori', 2, 7, 'fa-handshake', '#8b5cf6', FALSE, TRUE,
 JSON_OBJECT('dpa_templates', TRUE, 'vendor_assessment', TRUE)),

(@gdpr_id, @gdpr_id+1, 'FORMAZIONE_PRIVACY', 'Formazione Privacy', 'Formazione e sensibilizzazione privacy', 2, 8, 'fa-graduation-cap', '#3b82f6', FALSE, TRUE,
 JSON_OBJECT('awareness_training', TRUE, 'role_specific_training', TRUE));

-- =======================================================================================
-- 7. VISTE PER REPORTING E DASHBOARD
-- =======================================================================================

-- Vista documenti completi con informazioni struttura
CREATE OR REPLACE VIEW v_documenti_struttura_completa AS
SELECT 
    d.*,
    c.nome AS cartella_nome,
    c.percorso_completo,
    dsf.standard_codice,
    dsf.percorso_iso,
    ds.nome AS standard_nome,
    dst.nome AS template_nome,
    dst.icona AS template_icona,
    dst.colore AS template_colore,
    cds.tipo_struttura,
    cds.nome_configurazione,
    u_creato.nome AS creato_da_nome,
    u_creato.cognome AS creato_da_cognome,
    u_responsabile.nome AS responsabile_nome,
    u_responsabile.cognome AS responsabile_cognome,
    COALESCE(vers.numero_versioni, 1) AS numero_versioni,
    COALESCE(shares.numero_condivisioni, 0) AS numero_condivisioni,
    COALESCE(comp.ultimo_check_conformita, NULL) AS ultimo_check_conformita,
    COALESCE(comp.stato_conformita, 'da_verificare') AS stato_conformita
FROM documenti d
LEFT JOIN cartelle c ON c.id = d.cartella_id
LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
LEFT JOIN document_structure_templates dst ON dst.id = dsf.template_id
LEFT JOIN document_standards ds ON ds.id = dst.standard_id
LEFT JOIN company_document_structures cds ON cds.id = dsf.company_structure_id
LEFT JOIN utenti u_creato ON u_creato.id = d.creato_da
LEFT JOIN utenti u_responsabile ON u_responsabile.id = d.responsabile_documento
LEFT JOIN (
    SELECT documento_id, COUNT(*) as numero_versioni 
    FROM document_versions 
    GROUP BY documento_id
) vers ON vers.documento_id = d.id
LEFT JOIN (
    SELECT documento_id, COUNT(*) as numero_condivisioni 
    FROM document_sharing 
    WHERE attiva = TRUE 
    GROUP BY documento_id
) shares ON shares.documento_id = d.id
LEFT JOIN (
    SELECT 
        documento_id, 
        MAX(data_verifica) as ultimo_check_conformita,
        stato_conformita
    FROM document_compliance_checks 
    GROUP BY documento_id
) comp ON comp.documento_id = d.id;

-- Vista statistiche strutture documentali per azienda
CREATE OR REPLACE VIEW v_statistiche_strutture_azienda AS
SELECT 
    a.id AS azienda_id,
    a.nome AS azienda_nome,
    cds.id AS struttura_id,
    cds.nome_configurazione,
    cds.tipo_struttura,
    cds.stato AS stato_struttura,
    JSON_LENGTH(cds.standards_attivi) AS numero_standard_attivi,
    COUNT(DISTINCT dsf.id) AS numero_cartelle_struttura,
    COUNT(DISTINCT d.id) AS numero_documenti_totali,
    COALESCE(SUM(d.file_size), 0) AS dimensione_totale_bytes,
    COUNT(DISTINCT CASE WHEN d.stato = 'pubblicato' THEN d.id END) AS documenti_pubblicati,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'in_revisione' THEN d.id END) AS documenti_in_revisione,
    COUNT(DISTINCT CASE WHEN d.data_scadenza <= CURDATE() THEN d.id END) AS documenti_scaduti,
    COALESCE(AVG(comp_stats.punteggio_conformita), 0) AS punteggio_conformita_medio
FROM aziende a
LEFT JOIN company_document_structures cds ON cds.azienda_id = a.id
LEFT JOIN document_structure_folders dsf ON dsf.company_structure_id = cds.id
LEFT JOIN documenti d ON d.document_structure_folder_id = dsf.id
LEFT JOIN (
    SELECT 
        documento_id, 
        AVG(punteggio_conformita) as punteggio_conformita
    FROM document_compliance_checks 
    WHERE stato_conformita IN ('conforme', 'parzialmente_conforme')
    GROUP BY documento_id
) comp_stats ON comp_stats.documento_id = d.id
GROUP BY a.id, cds.id;

-- =======================================================================================
-- 8. INDICI PER PERFORMANCE OTTIMALI
-- =======================================================================================

-- Indici di performance per query frequenti
CREATE INDEX idx_documenti_azienda_stato ON documenti(azienda_id, stato);
CREATE INDEX idx_documenti_workflow_scadenza ON documenti(workflow_stato, data_scadenza);
CREATE INDEX idx_cartelle_azienda_iso ON cartelle(azienda_id, iso_standard_codice);
CREATE INDEX idx_doc_structure_folders_azienda ON document_structure_folders(company_structure_id, stato);
CREATE INDEX idx_audit_trail_date_action ON document_audit_trail(data_azione, azione);
CREATE INDEX idx_compliance_checks_standard_state ON document_compliance_checks(standard_codice, stato_conformita);

-- Indici compositi per ricerche complesse
CREATE INDEX idx_documenti_ricerca_completa ON documenti(azienda_id, stato, workflow_stato, data_scadenza);
CREATE INDEX idx_permissions_user_entity ON document_permissions(utente_id, documento_id, attivo);
CREATE INDEX idx_sharing_active_expiry ON document_sharing(attiva, data_scadenza);

SET FOREIGN_KEY_CHECKS = 1;

-- =======================================================================================
-- 9. STORED PROCEDURES PER OPERAZIONI COMPLESSE
-- =======================================================================================

-- Nota: Le stored procedures sono definite in file separati per evitare 
-- problemi di parsing con i DELIMITER

-- =======================================================================================
-- 10. CONFIGURAZIONI INIZIALI E DATI DI ESEMPIO
-- =======================================================================================

-- Inserimento configurazione di esempio per Azienda Demo
-- (Se esiste già azienda con id=1)
INSERT IGNORE INTO company_document_structures 
(azienda_id, nome_configurazione, tipo_struttura, standards_attivi, stato, default_struttura, creato_da)
SELECT 
    1,
    'Configurazione Standard Multi-Norma',
    'integrata',
    JSON_ARRAY('ISO9001', 'ISO14001', 'GDPR'),
    'bozza',
    TRUE,
    1
WHERE EXISTS (SELECT 1 FROM aziende WHERE id = 1);

-- =======================================================================================
-- MESSAGGIO FINALE
-- =======================================================================================

SELECT CONCAT(
    'Sistema documentale multi-standard inizializzato correttamente!\n',
    'Standard supportati: ISO 9001, ISO 14001, ISO 45001, GDPR, ISO 27001\n',
    'Tipi struttura: Separata, Integrata, Personalizzata\n',
    'Features: Ricerca full-text, Audit trail, Gestione permessi, Compliance tracking\n',
    'Versione schema: 3.0 - Gennaio 2025'
) AS messaggio_sistema;