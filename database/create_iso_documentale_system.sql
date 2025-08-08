-- ===================================================
-- Sistema Documentale ISO Multi-Norma per Nexio
-- ===================================================
-- Supporta ISO 9001, 14001, 45001, GDPR
-- Multi-tenant con configurazione per azienda
-- Versione 1.0.0
-- ===================================================

-- Tabella ISO Standards
CREATE TABLE IF NOT EXISTS iso_standards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(200) NOT NULL,
    versione VARCHAR(20),
    descrizione TEXT,
    requisiti JSON,
    attivo BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codice (codice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserimento Standards predefiniti
INSERT INTO iso_standards (codice, nome, versione, descrizione) VALUES
('ISO9001', 'Sistema di Gestione della Qualità', '2015', 'Standard internazionale per la gestione della qualità'),
('ISO14001', 'Sistema di Gestione Ambientale', '2015', 'Standard per la gestione ambientale'),
('ISO45001', 'Sistema di Gestione Salute e Sicurezza sul Lavoro', '2018', 'Standard per la sicurezza sul lavoro'),
('ISO27001', 'Sistema di Gestione della Sicurezza delle Informazioni', '2022', 'Standard per la sicurezza informatica'),
('GDPR', 'Regolamento Generale sulla Protezione dei Dati', '2016/679', 'Regolamento UE per la protezione dei dati personali'),
('SGI', 'Sistema di Gestione Integrato', 'Multi', 'Sistema che integra più standard ISO'),
('CUSTOM', 'Standard Personalizzato', 'N/A', 'Standard personalizzato aziendale')
ON DUPLICATE KEY UPDATE attivo = TRUE;

-- Configurazione ISO per Azienda
CREATE TABLE IF NOT EXISTS iso_configurazione_azienda (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    tipo_struttura ENUM('separata', 'integrata', 'personalizzata') DEFAULT 'separata',
    standards_attivi JSON,
    configurazione_avanzata JSON,
    stato ENUM('attiva', 'inattiva', 'configurazione') DEFAULT 'configurazione',
    data_configurazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    configurato_da INT,
    ultima_modifica TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_azienda (azienda_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (configurato_da) REFERENCES utenti(id),
    INDEX idx_azienda_stato (azienda_id, stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estensione tabella cartelle per supporto ISO
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(20),
ADD COLUMN IF NOT EXISTS iso_compliance_level ENUM('base', 'standard', 'avanzata', 'personalizzata') DEFAULT 'standard',
ADD COLUMN IF NOT EXISTS metadata_iso JSON,
ADD COLUMN IF NOT EXISTS requisiti_conformita JSON,
ADD COLUMN IF NOT EXISTS data_ultimo_audit DATETIME,
ADD CONSTRAINT fk_cartelle_iso_standard FOREIGN KEY (iso_standard_codice) REFERENCES iso_standards(codice),
ADD INDEX idx_iso_standard (iso_standard_codice);

-- Template struttura cartelle ISO
CREATE TABLE IF NOT EXISTS iso_template_cartelle (
    id INT PRIMARY KEY AUTO_INCREMENT,
    iso_standard_codice VARCHAR(20) NOT NULL,
    nome_cartella VARCHAR(200) NOT NULL,
    percorso_template VARCHAR(500),
    parent_path VARCHAR(500),
    ordine INT DEFAULT 0,
    descrizione TEXT,
    obbligatoria BOOLEAN DEFAULT TRUE,
    metadata JSON,
    FOREIGN KEY (iso_standard_codice) REFERENCES iso_standards(codice),
    INDEX idx_standard_ordine (iso_standard_codice, ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserimento template cartelle standard
INSERT INTO iso_template_cartelle (iso_standard_codice, nome_cartella, percorso_template, parent_path, ordine, obbligatoria) VALUES
-- ISO 9001
('ISO9001', '01_Manuale_Sistema', '01_Manuale_Sistema', NULL, 1, TRUE),
('ISO9001', '02_Politiche', '02_Politiche', NULL, 2, TRUE),
('ISO9001', '03_Procedure', '03_Procedure', NULL, 3, TRUE),
('ISO9001', '04_Moduli_Registrazioni', '04_Moduli_Registrazioni', NULL, 4, TRUE),
('ISO9001', '05_Audit', '05_Audit', NULL, 5, TRUE),
('ISO9001', '06_Non_Conformità', '06_Non_Conformità', NULL, 6, TRUE),
('ISO9001', '07_Azioni_Miglioramento', '07_Azioni_Miglioramento', NULL, 7, TRUE),
('ISO9001', '08_Riesame_Direzione', '08_Riesame_Direzione', NULL, 8, TRUE),
('ISO9001', '09_Formazione', '09_Formazione', NULL, 9, TRUE),
('ISO9001', '10_Gestione_Fornitori', '10_Gestione_Fornitori', NULL, 10, TRUE),
('ISO9001', '11_Indicatori_KPI', '11_Indicatori_KPI', NULL, 11, TRUE),
-- ISO 14001
('ISO14001', '01_Manuale_Ambientale', '01_Manuale_Ambientale', NULL, 1, TRUE),
('ISO14001', '02_Politica_Ambientale', '02_Politica_Ambientale', NULL, 2, TRUE),
('ISO14001', '03_Aspetti_Ambientali', '03_Aspetti_Ambientali', NULL, 3, TRUE),
('ISO14001', '04_Requisiti_Legali', '04_Requisiti_Legali', NULL, 4, TRUE),
('ISO14001', '05_Obiettivi_Traguardi', '05_Obiettivi_Traguardi', NULL, 5, TRUE),
('ISO14001', '06_Controllo_Operativo', '06_Controllo_Operativo', NULL, 6, TRUE),
('ISO14001', '07_Emergenze_Ambientali', '07_Emergenze_Ambientali', NULL, 7, TRUE),
('ISO14001', '08_Monitoraggio_Misurazioni', '08_Monitoraggio_Misurazioni', NULL, 8, TRUE),
('ISO14001', '09_Audit_Ambientali', '09_Audit_Ambientali', NULL, 9, TRUE),
('ISO14001', '10_Riesame_Ambientale', '10_Riesame_Ambientale', NULL, 10, TRUE),
-- ISO 45001
('ISO45001', '01_Manuale_SSL', '01_Manuale_SSL', NULL, 1, TRUE),
('ISO45001', '02_Politica_SSL', '02_Politica_SSL', NULL, 2, TRUE),
('ISO45001', '03_Valutazione_Rischi', '03_Valutazione_Rischi', NULL, 3, TRUE),
('ISO45001', '04_Procedure_Sicurezza', '04_Procedure_Sicurezza', NULL, 4, TRUE),
('ISO45001', '05_DPI_Attrezzature', '05_DPI_Attrezzature', NULL, 5, TRUE),
('ISO45001', '06_Formazione_Sicurezza', '06_Formazione_Sicurezza', NULL, 6, TRUE),
('ISO45001', '07_Sorveglianza_Sanitaria', '07_Sorveglianza_Sanitaria', NULL, 7, TRUE),
('ISO45001', '08_Gestione_Emergenze', '08_Gestione_Emergenze', NULL, 8, TRUE),
('ISO45001', '09_Infortuni_Incidenti', '09_Infortuni_Incidenti', NULL, 9, TRUE),
('ISO45001', '10_Audit_Sicurezza', '10_Audit_Sicurezza', NULL, 10, TRUE),
-- GDPR
('GDPR', '01_Registro_Trattamenti', '01_Registro_Trattamenti', NULL, 1, TRUE),
('GDPR', '02_Informative_Privacy', '02_Informative_Privacy', NULL, 2, TRUE),
('GDPR', '03_Consensi', '03_Consensi', NULL, 3, TRUE),
('GDPR', '04_Nomine_Autorizzazioni', '04_Nomine_Autorizzazioni', NULL, 4, TRUE),
('GDPR', '05_DPIA', '05_DPIA', NULL, 5, TRUE),
('GDPR', '06_Data_Breach', '06_Data_Breach', NULL, 6, TRUE),
('GDPR', '07_Diritti_Interessati', '07_Diritti_Interessati', NULL, 7, TRUE),
('GDPR', '08_Misure_Sicurezza', '08_Misure_Sicurezza', NULL, 8, TRUE),
('GDPR', '09_Audit_Privacy', '09_Audit_Privacy', NULL, 9, TRUE),
('GDPR', '10_Formazione_Privacy', '10_Formazione_Privacy', NULL, 10, TRUE)
ON DUPLICATE KEY UPDATE ordine = VALUES(ordine);

-- Estensione tabella documenti per supporto ISO
ALTER TABLE documenti
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(20),
ADD COLUMN IF NOT EXISTS iso_requisito VARCHAR(50),
ADD COLUMN IF NOT EXISTS classificazione_iso ENUM('pubblico', 'interno', 'riservato', 'confidenziale') DEFAULT 'interno',
ADD COLUMN IF NOT EXISTS approvato_da INT,
ADD COLUMN IF NOT EXISTS data_approvazione DATETIME,
ADD COLUMN IF NOT EXISTS prossima_revisione DATE,
ADD COLUMN IF NOT EXISTS tags JSON,
ADD CONSTRAINT fk_documenti_iso_standard FOREIGN KEY (iso_standard_codice) REFERENCES iso_standards(codice),
ADD CONSTRAINT fk_documenti_approvato FOREIGN KEY (approvato_da) REFERENCES utenti(id),
ADD INDEX idx_iso_requisito (iso_standard_codice, iso_requisito),
ADD INDEX idx_prossima_revisione (prossima_revisione);

-- Tabella per audit trail ISO
CREATE TABLE IF NOT EXISTS iso_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    iso_standard_codice VARCHAR(20),
    tipo_audit ENUM('interno', 'esterno', 'certificazione') NOT NULL,
    data_audit DATE NOT NULL,
    auditor VARCHAR(200),
    esito ENUM('conforme', 'non_conforme', 'osservazione') NOT NULL,
    documento_id INT,
    cartella_id INT,
    descrizione TEXT,
    azioni_correttive TEXT,
    data_chiusura DATE,
    creato_da INT NOT NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id),
    FOREIGN KEY (iso_standard_codice) REFERENCES iso_standards(codice),
    FOREIGN KEY (documento_id) REFERENCES documenti(id),
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id),
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    INDEX idx_azienda_standard (azienda_id, iso_standard_codice),
    INDEX idx_data_audit (data_audit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per conformità ISO
CREATE TABLE IF NOT EXISTS iso_conformita (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    iso_standard_codice VARCHAR(20) NOT NULL,
    requisito VARCHAR(50) NOT NULL,
    stato_conformita ENUM('conforme', 'parzialmente_conforme', 'non_conforme', 'non_applicabile') NOT NULL,
    documento_evidenza_id INT,
    note TEXT,
    data_verifica DATE,
    verificato_da INT,
    data_prossima_verifica DATE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id),
    FOREIGN KEY (iso_standard_codice) REFERENCES iso_standards(codice),
    FOREIGN KEY (documento_evidenza_id) REFERENCES documenti(id),
    FOREIGN KEY (verificato_da) REFERENCES utenti(id),
    UNIQUE KEY uk_conformita (azienda_id, iso_standard_codice, requisito),
    INDEX idx_stato_conformita (stato_conformita),
    INDEX idx_prossima_verifica (data_prossima_verifica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per gestione file multipli
CREATE TABLE IF NOT EXISTS upload_batch (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id VARCHAR(36) NOT NULL UNIQUE,
    azienda_id INT NOT NULL,
    cartella_id INT,
    utente_id INT NOT NULL,
    stato ENUM('in_corso', 'completato', 'errore', 'annullato') DEFAULT 'in_corso',
    totale_file INT DEFAULT 0,
    file_caricati INT DEFAULT 0,
    file_errori INT DEFAULT 0,
    dimensione_totale BIGINT DEFAULT 0,
    data_inizio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_fine TIMESTAMP NULL,
    metadata JSON,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id),
    FOREIGN KEY (cartella_id) REFERENCES cartelle(id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id),
    INDEX idx_batch_stato (batch_id, stato),
    INDEX idx_utente_data (utente_id, data_inizio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per download multipli
CREATE TABLE IF NOT EXISTS download_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    queue_id VARCHAR(36) NOT NULL UNIQUE,
    azienda_id INT NOT NULL,
    utente_id INT NOT NULL,
    tipo ENUM('documenti', 'cartella', 'ricerca') NOT NULL,
    elementi JSON NOT NULL,
    stato ENUM('in_coda', 'processando', 'completato', 'errore') DEFAULT 'in_coda',
    file_zip VARCHAR(255),
    dimensione_zip BIGINT,
    data_richiesta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_completamento TIMESTAMP NULL,
    data_scadenza TIMESTAMP NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id),
    INDEX idx_queue_stato (queue_id, stato),
    INDEX idx_utente_richiesta (utente_id, data_richiesta),
    INDEX idx_scadenza (data_scadenza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vista per dashboard ISO
CREATE OR REPLACE VIEW v_iso_dashboard AS
SELECT 
    a.id as azienda_id,
    a.nome as azienda_nome,
    iso.codice as standard_codice,
    iso.nome as standard_nome,
    COUNT(DISTINCT c.id) as totale_cartelle,
    COUNT(DISTINCT d.id) as totale_documenti,
    COUNT(DISTINCT CASE WHEN ic.stato_conformita = 'conforme' THEN ic.requisito END) as requisiti_conformi,
    COUNT(DISTINCT ic.requisito) as totale_requisiti,
    MAX(iat.data_audit) as ultimo_audit,
    MIN(CASE WHEN d.prossima_revisione >= CURDATE() THEN d.prossima_revisione END) as prossima_revisione
FROM aziende a
INNER JOIN iso_configurazione_azienda ica ON a.id = ica.azienda_id
CROSS JOIN iso_standards iso
LEFT JOIN cartelle c ON a.id = c.azienda_id AND c.iso_standard_codice = iso.codice
LEFT JOIN documenti d ON c.id = d.cartella_id AND d.iso_standard_codice = iso.codice
LEFT JOIN iso_conformita ic ON a.id = ic.azienda_id AND iso.codice = ic.iso_standard_codice
LEFT JOIN iso_audit_trail iat ON a.id = iat.azienda_id AND iso.codice = iat.iso_standard_codice
WHERE ica.stato = 'attiva'
  AND JSON_CONTAINS(ica.standards_attivi, JSON_QUOTE(iso.codice))
GROUP BY a.id, iso.codice;

-- Trigger per creazione automatica struttura cartelle
DELIMITER $$

CREATE TRIGGER after_iso_config_activate
AFTER UPDATE ON iso_configurazione_azienda
FOR EACH ROW
BEGIN
    -- Solo se lo stato cambia a 'attiva'
    IF NEW.stato = 'attiva' AND OLD.stato != 'attiva' THEN
        -- Log dell'attivazione
        INSERT INTO log_attivita (utente_id, azienda_id, azione, entita_tipo, entita_id, dettagli)
        VALUES (NEW.configurato_da, NEW.azienda_id, 'iso_config_activated', 'iso_configurazione', NEW.id,
                JSON_OBJECT('standards', NEW.standards_attivi, 'structure_type', NEW.tipo_struttura));
    END IF;
END$$

-- Trigger per auto-incremento versione documenti
CREATE TRIGGER before_document_version_insert
BEFORE INSERT ON documenti_versioni
FOR EACH ROW
BEGIN
    DECLARE max_version INT;
    
    -- Trova la versione massima esistente
    SELECT COALESCE(MAX(versione), 0) INTO max_version
    FROM documenti_versioni
    WHERE documento_id = NEW.documento_id;
    
    -- Imposta la nuova versione
    IF NEW.versione IS NULL OR NEW.versione <= max_version THEN
        SET NEW.versione = max_version + 1;
    END IF;
END$$

DELIMITER ;

-- Indici per performance
CREATE INDEX idx_cartelle_percorso ON cartelle(percorso_completo);
CREATE INDEX idx_documenti_ricerca ON documenti(titolo, codice, tipo_documento);
CREATE INDEX idx_audit_ricerca ON iso_audit_trail(azienda_id, data_audit, esito);

-- Grant permissions (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexiosol.* TO 'nexio_user'@'localhost';