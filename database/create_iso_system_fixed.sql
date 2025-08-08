-- ISO System Fixed - Tabelle allineate con ISOStructureManager.php
-- Risolve le discrepanze tra nomi tabelle e colonne

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing ISO tables
DROP TABLE IF EXISTS iso_deployment_log;
DROP TABLE IF EXISTS iso_compliance_check;
DROP TABLE IF EXISTS aziende_iso_folders;
DROP TABLE IF EXISTS aziende_iso_config;
DROP TABLE IF EXISTS iso_audit_logs;
DROP TABLE IF EXISTS iso_search_index;
DROP TABLE IF EXISTS iso_permissions;
DROP TABLE IF EXISTS iso_document_versions;
DROP TABLE IF EXISTS iso_documents;
DROP TABLE IF EXISTS iso_folders;
DROP TABLE IF EXISTS iso_company_configurations;
DROP TABLE IF EXISTS iso_folder_templates;
DROP TABLE IF EXISTS iso_standards;

-- 1. ISO Standards (con nomi colonne corretti per ISOStructureManager)
CREATE TABLE iso_standards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(20) NOT NULL UNIQUE,    -- ISOStructureManager cerca 'codice'
    nome VARCHAR(100) NOT NULL,            -- ISOStructureManager cerca 'nome'
    descrizione TEXT,
    versione VARCHAR(20),
    attivo BOOLEAN DEFAULT TRUE,           -- ISOStructureManager cerca 'attivo'
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attivo (attivo),
    INDEX idx_codice (codice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Folder Templates (con nomi colonne corretti)
CREATE TABLE iso_folder_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    standard_id INT NOT NULL,
    parent_template_id INT,                -- ISOStructureManager cerca 'parent_template_id'
    codice VARCHAR(50),                    -- ISOStructureManager cerca 'codice'
    nome VARCHAR(200) NOT NULL,            -- ISOStructureManager cerca 'nome'
    descrizione TEXT,
    livello INT DEFAULT 1,                 -- ISOStructureManager usa 'livello'
    ordine_visualizzazione INT DEFAULT 0,  -- ISOStructureManager cerca questo campo
    icona VARCHAR(50),                     -- ISOStructureManager cerca 'icona'
    colore VARCHAR(7) DEFAULT '#fbbf24',   -- ISOStructureManager cerca 'colore'
    obbligatoria BOOLEAN DEFAULT FALSE,    -- ISOStructureManager cerca 'obbligatoria'
    metadati JSON,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (standard_id) REFERENCES iso_standards(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_template_id) REFERENCES iso_folder_templates(id) ON DELETE CASCADE,
    INDEX idx_standard_parent (standard_id, parent_template_id),
    INDEX idx_livello (standard_id, livello),
    INDEX idx_ordine (standard_id, ordine_visualizzazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Company ISO Configuration (nome tabella che ISOStructureManager cerca)
CREATE TABLE aziende_iso_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    tipo_struttura ENUM('separata', 'integrata', 'personalizzata') DEFAULT 'separata',
    standards_attivi JSON,                 -- Array di codici standard attivi
    configurazione_avanzata JSON,          -- Configurazioni personalizzate
    stato ENUM('configurazione', 'attiva', 'sospesa', 'disattivata') DEFAULT 'configurazione',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_attivazione TIMESTAMP NULL,
    creato_da INT,
    UNIQUE KEY uk_azienda (azienda_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    INDEX idx_stato (stato),
    INDEX idx_tipo_struttura (tipo_struttura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Company ISO Folders (tabella che ISOStructureManager cerca)  
CREATE TABLE aziende_iso_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    template_id INT NOT NULL,
    cartella_id INT NOT NULL,              -- Link alla tabella cartelle esistente
    standard_codice VARCHAR(20) NOT NULL,
    percorso_iso VARCHAR(1000),            -- Percorso ISO completo
    personalizzazioni JSON,                -- Personalizzazioni specifiche dell'azienda
    stato ENUM('attiva', 'disattivata') DEFAULT 'attiva',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES iso_folder_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    UNIQUE KEY uk_azienda_template (azienda_id, template_id),
    INDEX idx_azienda_standard (azienda_id, standard_codice),
    INDEX idx_cartella (cartella_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ISO Deployment Log (per tracking operazioni)
CREATE TABLE iso_deployment_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    operazione VARCHAR(100) NOT NULL,
    standard_coinvolti JSON,               -- Array di standard coinvolti
    dettagli_operazione JSON,              -- Dettagli dell'operazione
    risultato ENUM('successo', 'fallito', 'parziale') NOT NULL,
    tempo_esecuzione_secondi DECIMAL(10,3),
    eseguito_da INT,
    data_esecuzione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (eseguito_da) REFERENCES utenti(id),
    INDEX idx_azienda_data (azienda_id, data_esecuzione),
    INDEX idx_operazione (operazione),
    INDEX idx_risultato (risultato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ISO Compliance Check (per verifiche conformità)
CREATE TABLE iso_compliance_check (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    standard_codice VARCHAR(20) NOT NULL,
    tipo_verifica ENUM('automatica', 'manuale', 'audit') NOT NULL,
    stato_conformita ENUM('conforme', 'non_conforme', 'parzialmente_conforme', 'da_verificare') NOT NULL,
    punteggio_conformita DECIMAL(5,2),     -- Da 0.00 a 100.00
    requisiti_verificati JSON,             -- Lista requisiti verificati
    non_conformita JSON,                   -- Lista non conformità trovate
    raccomandazioni JSON,                  -- Raccomandazioni per miglioramenti
    verificato_da INT,
    data_verifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_scadenza DATE,                    -- Scadenza verifica
    note TEXT,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (verificato_da) REFERENCES utenti(id),
    INDEX idx_azienda_standard (azienda_id, standard_codice),
    INDEX idx_stato (stato_conformita),
    INDEX idx_scadenza (data_scadenza),
    INDEX idx_data_verifica (data_verifica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiorna tabella cartelle per supportare metadati ISO
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS iso_template_id INT,
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(20),
ADD COLUMN IF NOT EXISTS iso_compliance_level ENUM('obbligatoria', 'raccomandata', 'opzionale', 'personalizzata'),
ADD COLUMN IF NOT EXISTS iso_metadata JSON,
ADD INDEX IF NOT EXISTS idx_iso_template (iso_template_id),
ADD INDEX IF NOT EXISTS idx_iso_standard (iso_standard_codice);

SET FOREIGN_KEY_CHECKS = 1;

-- Insert ISO standards con nomi colonne corretti
INSERT INTO iso_standards (codice, nome, descrizione, versione, attivo) VALUES
('ISO9001', 'ISO 9001:2015', 'Sistema di Gestione della Qualità', '2015', TRUE),
('ISO14001', 'ISO 14001:2015', 'Sistema di Gestione Ambientale', '2015', TRUE),
('ISO45001', 'ISO 45001:2018', 'Sistema di Gestione della Salute e Sicurezza sul Lavoro', '2018', TRUE),
('GDPR', 'GDPR 2016/679', 'Regolamento Generale sulla Protezione dei Dati', '2016/679', TRUE),
('ISO27001', 'ISO 27001:2013', 'Sistema di Gestione della Sicurezza delle Informazioni', '2013', TRUE);

-- Insert folder templates per ISO 9001
SET @iso9001_id = (SELECT id FROM iso_standards WHERE codice = 'ISO9001');

-- Root folder per ISO 9001
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) 
VALUES (@iso9001_id, NULL, 'ISO9001_ROOT', 'ISO 9001 - Sistema Qualità', 'Sistema di Gestione della Qualità ISO 9001:2015', 1, 1, 'fa-award', '#3b82f6', TRUE);

SET @iso9001_root_id = LAST_INSERT_ID();

-- Cartelle principali ISO 9001
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
(@iso9001_id, @iso9001_root_id, 'MANUALE_SISTEMA', 'Manuale del Sistema', 'Manuale del Sistema di Gestione Qualità', 2, 1, 'fa-book', '#059669', TRUE),
(@iso9001_id, @iso9001_root_id, 'POLITICHE', 'Politiche', 'Politiche aziendali per la qualità', 2, 2, 'fa-file-alt', '#7c3aed', TRUE),
(@iso9001_id, @iso9001_root_id, 'PROCEDURE', 'Procedure', 'Procedure operative standard', 2, 3, 'fa-tasks', '#dc2626', TRUE),
(@iso9001_id, @iso9001_root_id, 'MODULI_REGISTRAZIONI', 'Moduli e Registrazioni', 'Moduli e registrazioni del sistema', 2, 4, 'fa-clipboard', '#f59e0b', FALSE),
(@iso9001_id, @iso9001_root_id, 'AUDIT', 'Audit', 'Audit interni e verifiche', 2, 5, 'fa-search', '#8b5cf6', TRUE),
(@iso9001_id, @iso9001_root_id, 'NON_CONFORMITA', 'Non Conformità', 'Gestione delle non conformità', 2, 6, 'fa-exclamation-triangle', '#ef4444', TRUE),
(@iso9001_id, @iso9001_root_id, 'RIESAME_DIREZIONE', 'Riesame della Direzione', 'Documenti del riesame della direzione', 2, 7, 'fa-users-cog', '#06b6d4', TRUE);

-- Sotto-cartelle per Procedure
SET @proc_id = (SELECT id FROM iso_folder_templates WHERE standard_id = @iso9001_id AND codice = 'PROCEDURE');
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, obbligatoria) VALUES
(@iso9001_id, @proc_id, 'GESTIONE_DOCUMENTI', 'Gestione Documenti', 'Controllo dei documenti e registrazioni', 3, 1, 'fa-folder-open', TRUE),
(@iso9001_id, @proc_id, 'GESTIONE_RISORSE', 'Gestione Risorse', 'Gestione delle risorse umane e infrastrutture', 3, 2, 'fa-users', TRUE),
(@iso9001_id, @proc_id, 'PROCESSI_OPERATIVI', 'Processi Operativi', 'Processi operativi principali', 3, 3, 'fa-cogs', TRUE);

-- Insert folder templates per ISO 14001  
SET @iso14001_id = (SELECT id FROM iso_standards WHERE codice = 'ISO14001');

-- Root folder per ISO 14001
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) 
VALUES (@iso14001_id, NULL, 'ISO14001_ROOT', 'ISO 14001 - Sistema Ambientale', 'Sistema di Gestione Ambientale ISO 14001:2015', 1, 1, 'fa-leaf', '#10b981', TRUE);

SET @iso14001_root_id = LAST_INSERT_ID();

-- Cartelle principali ISO 14001
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
(@iso14001_id, @iso14001_root_id, 'MANUALE_AMBIENTALE', 'Manuale Ambientale', 'Manuale del Sistema di Gestione Ambientale', 2, 1, 'fa-book', '#059669', TRUE),
(@iso14001_id, @iso14001_root_id, 'POLITICA_AMBIENTALE', 'Politica Ambientale', 'Politica ambientale aziendale', 2, 2, 'fa-file-alt', '#7c3aed', TRUE),
(@iso14001_id, @iso14001_root_id, 'ASPETTI_AMBIENTALI', 'Aspetti Ambientali', 'Identificazione e valutazione aspetti ambientali', 2, 3, 'fa-leaf', '#10b981', TRUE),
(@iso14001_id, @iso14001_root_id, 'CONFORMITA_LEGALE', 'Conformità Legale', 'Conformità legislativa ambientale', 2, 4, 'fa-balance-scale', '#f59e0b', TRUE),
(@iso14001_id, @iso14001_root_id, 'EMERGENZE', 'Emergenze Ambientali', 'Gestione delle emergenze ambientali', 2, 5, 'fa-exclamation-circle', '#ef4444', TRUE);

-- Insert folder templates per ISO 45001
SET @iso45001_id = (SELECT id FROM iso_standards WHERE codice = 'ISO45001');

-- Root folder per ISO 45001
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) 
VALUES (@iso45001_id, NULL, 'ISO45001_ROOT', 'ISO 45001 - Sistema SSL', 'Sistema di Gestione della Salute e Sicurezza sul Lavoro', 1, 1, 'fa-shield-alt', '#dc2626', TRUE);

SET @iso45001_root_id = LAST_INSERT_ID();

-- Cartelle principali ISO 45001
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
(@iso45001_id, @iso45001_root_id, 'MANUALE_SSL', 'Manuale SSL', 'Manuale del Sistema di Gestione SSL', 2, 1, 'fa-book', '#059669', TRUE),
(@iso45001_id, @iso45001_root_id, 'VALUTAZIONE_RISCHI', 'Valutazione dei Rischi', 'Documenti di valutazione dei rischi', 2, 2, 'fa-shield-alt', '#dc2626', TRUE),
(@iso45001_id, @iso45001_root_id, 'PROCEDURE_SSL', 'Procedure SSL', 'Procedure di sicurezza', 2, 3, 'fa-tasks', '#f59e0b', TRUE),
(@iso45001_id, @iso45001_root_id, 'FORMAZIONE_SSL', 'Formazione SSL', 'Formazione e competenze SSL', 2, 4, 'fa-graduation-cap', '#3b82f6', TRUE),
(@iso45001_id, @iso45001_root_id, 'SORVEGLIANZA_SANITARIA', 'Sorveglianza Sanitaria', 'Sorveglianza sanitaria dei lavoratori', 2, 5, 'fa-heartbeat', '#ec4899', TRUE);

-- Insert folder templates per GDPR
SET @gdpr_id = (SELECT id FROM iso_standards WHERE codice = 'GDPR');

-- Root folder per GDPR
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) 
VALUES (@gdpr_id, NULL, 'GDPR_ROOT', 'GDPR - Privacy e Protezione Dati', 'Regolamento Generale sulla Protezione dei Dati', 1, 1, 'fa-shield-alt', '#6366f1', TRUE);

SET @gdpr_root_id = LAST_INSERT_ID();

-- Cartelle principali GDPR
INSERT INTO iso_folder_templates (standard_id, parent_template_id, codice, nome, descrizione, livello, ordine_visualizzazione, icona, colore, obbligatoria) VALUES
(@gdpr_id, @gdpr_root_id, 'POLITICHE_PRIVACY', 'Politiche Privacy', 'Politiche sulla privacy e protezione dati', 2, 1, 'fa-file-alt', '#7c3aed', TRUE),
(@gdpr_id, @gdpr_root_id, 'REGISTRI_TRATTAMENTO', 'Registri Trattamento', 'Registri delle attività di trattamento', 2, 2, 'fa-clipboard-list', '#059669', TRUE),
(@gdpr_id, @gdpr_root_id, 'CONSENSI', 'Consensi', 'Gestione dei consensi', 2, 3, 'fa-check-square', '#10b981', TRUE),
(@gdpr_id, @gdpr_root_id, 'VALUTAZIONI_IMPATTO', 'DPIA', 'Valutazioni d\'impatto sulla protezione dei dati', 2, 4, 'fa-chart-line', '#f59e0b', TRUE),
(@gdpr_id, @gdpr_root_id, 'VIOLAZIONI_DATI', 'Data Breach', 'Registro violazioni e data breach', 2, 5, 'fa-exclamation-triangle', '#ef4444', TRUE),
(@gdpr_id, @gdpr_root_id, 'DIRITTI_INTERESSATI', 'Diritti Interessati', 'Gestione richieste degli interessati', 2, 6, 'fa-users', '#3b82f6', TRUE);

-- Stored procedure semplificata per inizializzazione (senza DELIMITER issues)
-- Nota: Le procedure complesse sono definite nell'API PHP per evitare problemi di parsing

-- Crea indici per performance ottimale
CREATE INDEX idx_cartelle_iso_lookup ON cartelle(azienda_id, iso_standard_codice);
CREATE INDEX idx_iso_config_lookup ON aziende_iso_config(azienda_id, stato);
CREATE INDEX idx_iso_templates_hierarchy ON iso_folder_templates(standard_id, parent_template_id, livello);

-- Commenti finali
SELECT 'Sistema ISO inizializzato correttamente. Tabelle create e allineate con ISOStructureManager.php' AS messaggio;