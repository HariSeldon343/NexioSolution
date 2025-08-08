-- Sistema di gestione documentale con spazi isolati e ISO compliance
-- Data: 2025-01-27

-- 1. Tabella per gli spazi documentali (super admin e aziende)
CREATE TABLE IF NOT EXISTS spazi_documentali (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_spazio ENUM('super_admin', 'azienda') NOT NULL,
    id_azienda INT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    modalita_gestione ENUM('separata', 'integrata') DEFAULT 'separata',
    norme_iso JSON DEFAULT NULL COMMENT 'Array delle norme ISO attive es: ["9001", "14001"]',
    configurazione JSON DEFAULT NULL,
    creato_da INT NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_azienda) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    INDEX idx_tipo_spazio (tipo_spazio),
    INDEX idx_id_azienda (id_azienda),
    UNIQUE KEY unique_super_admin (tipo_spazio, id_azienda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Strutture ISO predefinite
CREATE TABLE IF NOT EXISTS strutture_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice_norma VARCHAR(20) NOT NULL COMMENT 'es: 9001, 14001, 45001, 27001',
    nome_norma VARCHAR(255) NOT NULL,
    descrizione TEXT,
    struttura_cartelle JSON NOT NULL COMMENT 'Struttura gerarchica delle cartelle',
    icona VARCHAR(50) DEFAULT 'fas fa-folder',
    ordine INT DEFAULT 0,
    attivo BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_codice (codice_norma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Cartelle documentali estese
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS id_spazio INT NULL AFTER id_azienda;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS tipo_cartella ENUM('normale', 'iso', 'sistema') DEFAULT 'normale' AFTER tipo;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS norma_iso VARCHAR(20) NULL AFTER tipo_cartella;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS metadati JSON NULL AFTER norma_iso;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS cestinata BOOLEAN DEFAULT FALSE AFTER eliminata;
ALTER TABLE cartelle ADD COLUMN IF NOT EXISTS data_cestino TIMESTAMP NULL AFTER cestinata;
ALTER TABLE cartelle ADD FOREIGN KEY (id_spazio) REFERENCES spazi_documentali(id) ON DELETE CASCADE;
ALTER TABLE cartelle ADD INDEX idx_spazio (id_spazio);
ALTER TABLE cartelle ADD INDEX idx_cestinata (cestinata);

-- 4. Versionamento documenti migliorato
CREATE TABLE IF NOT EXISTS documenti_versioni_extended (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_documento INT NOT NULL,
    numero_versione VARCHAR(20) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    dimensione_file BIGINT,
    hash_file VARCHAR(64) COMMENT 'SHA256 del file',
    responsabile_revisione INT NULL,
    data_revisione DATE NULL,
    prossima_revisione DATE NULL,
    stato_workflow ENUM('bozza', 'in_revisione', 'approvato', 'obsoleto') DEFAULT 'bozza',
    approvato_da INT NULL,
    data_approvazione TIMESTAMP NULL,
    note_versione TEXT,
    metadati JSON,
    caricato_da INT NOT NULL,
    caricato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_documento) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (responsabile_revisione) REFERENCES utenti(id),
    FOREIGN KEY (approvato_da) REFERENCES utenti(id),
    FOREIGN KEY (caricato_da) REFERENCES utenti(id),
    INDEX idx_documento (id_documento),
    INDEX idx_stato (stato_workflow),
    INDEX idx_revisione (prossima_revisione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Metadati documenti ISO
CREATE TABLE IF NOT EXISTS documenti_metadati_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_documento INT NOT NULL,
    codice_documento VARCHAR(100) UNIQUE,
    tipo_documento ENUM('manuale', 'politica', 'procedura', 'modulo', 'registrazione', 'altro') NOT NULL,
    livello_distribuzione ENUM('pubblico', 'interno', 'riservato', 'confidenziale') DEFAULT 'interno',
    responsabile_documento INT NULL,
    frequenza_revisione INT NULL COMMENT 'Giorni tra una revisione e l\'altra',
    ultima_revisione DATE NULL,
    prossima_revisione DATE NULL,
    riferimenti_normativi TEXT,
    parole_chiave TEXT,
    processo_correlato VARCHAR(255),
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_documento) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (responsabile_documento) REFERENCES utenti(id),
    INDEX idx_tipo (tipo_documento),
    INDEX idx_prossima_revisione (prossima_revisione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Cestino documenti
CREATE TABLE IF NOT EXISTS cestino_documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_oggetto ENUM('documento', 'cartella') NOT NULL,
    id_oggetto INT NOT NULL,
    id_spazio INT NOT NULL,
    dati_oggetto JSON NOT NULL COMMENT 'Snapshot dei dati prima della cancellazione',
    percorso_originale VARCHAR(1000),
    eliminato_da INT NOT NULL,
    eliminato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scadenza_cestino TIMESTAMP NULL COMMENT 'Data dopo la quale verrà eliminato definitivamente',
    FOREIGN KEY (id_spazio) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (eliminato_da) REFERENCES utenti(id),
    INDEX idx_spazio (id_spazio),
    INDEX idx_scadenza (scadenza_cestino),
    INDEX idx_tipo_oggetto (tipo_oggetto, id_oggetto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Log attività documentale
CREATE TABLE IF NOT EXISTS log_attivita_documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_spazio INT NOT NULL,
    tipo_oggetto ENUM('spazio', 'cartella', 'documento', 'versione') NOT NULL,
    id_oggetto INT NOT NULL,
    azione VARCHAR(50) NOT NULL,
    dettagli JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    eseguito_da INT NOT NULL,
    eseguito_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_spazio) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (eseguito_da) REFERENCES utenti(id),
    INDEX idx_spazio (id_spazio),
    INDEX idx_oggetto (tipo_oggetto, id_oggetto),
    INDEX idx_data (eseguito_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Permessi spazi documentali
CREATE TABLE IF NOT EXISTS permessi_spazi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_spazio INT NOT NULL,
    tipo_permesso ENUM('utente', 'ruolo') NOT NULL,
    id_utente INT NULL,
    ruolo VARCHAR(50) NULL,
    permessi JSON NOT NULL COMMENT 'Array di permessi: ["lettura", "scrittura", "eliminazione", "gestione"]',
    creato_da INT NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_spazio) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    INDEX idx_spazio (id_spazio),
    INDEX idx_utente (id_utente),
    UNIQUE KEY unique_permesso (id_spazio, tipo_permesso, id_utente, ruolo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Template cartelle ISO
CREATE TABLE IF NOT EXISTS template_cartelle_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_template VARCHAR(255) NOT NULL,
    descrizione TEXT,
    norma_iso VARCHAR(20) NOT NULL,
    struttura JSON NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_norma (norma_iso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Inserimento strutture ISO predefinite
INSERT INTO strutture_iso (codice_norma, nome_norma, descrizione, struttura_cartelle) VALUES
('9001', 'ISO 9001:2015 - Sistema di Gestione Qualità', 'Norma internazionale per i sistemi di gestione della qualità', 
JSON_ARRAY(
    JSON_OBJECT('nome', 'Manuale_Sistema', 'descrizione', 'Manuale del sistema di gestione qualità', 'icona', 'fas fa-book'),
    JSON_OBJECT('nome', 'Politiche', 'descrizione', 'Politiche aziendali per la qualità', 'icona', 'fas fa-file-alt'),
    JSON_OBJECT('nome', 'Procedure', 'descrizione', 'Procedure operative e gestionali', 'icona', 'fas fa-clipboard-list'),
    JSON_OBJECT('nome', 'Moduli_Registrazioni', 'descrizione', 'Moduli e registrazioni qualità', 'icona', 'fas fa-file-invoice'),
    JSON_OBJECT('nome', 'Audit', 'descrizione', 'Audit interni ed esterni', 'icona', 'fas fa-search'),
    JSON_OBJECT('nome', 'Non_Conformità', 'descrizione', 'Gestione non conformità', 'icona', 'fas fa-exclamation-triangle'),
    JSON_OBJECT('nome', 'Azioni_Miglioramento', 'descrizione', 'Azioni correttive e preventive', 'icona', 'fas fa-chart-line'),
    JSON_OBJECT('nome', 'Riesame_Direzione', 'descrizione', 'Riesame della direzione', 'icona', 'fas fa-users'),
    JSON_OBJECT('nome', 'Formazione', 'descrizione', 'Formazione e competenze', 'icona', 'fas fa-graduation-cap'),
    JSON_OBJECT('nome', 'Gestione_Fornitori', 'descrizione', 'Valutazione e gestione fornitori', 'icona', 'fas fa-truck'),
    JSON_OBJECT('nome', 'Indicatori_KPI', 'descrizione', 'Indicatori di prestazione', 'icona', 'fas fa-chart-bar')
)),

('14001', 'ISO 14001:2015 - Sistema di Gestione Ambientale', 'Norma internazionale per i sistemi di gestione ambientale',
JSON_ARRAY(
    JSON_OBJECT('nome', 'Manuale_Sistema', 'descrizione', 'Manuale del sistema di gestione ambientale', 'icona', 'fas fa-book'),
    JSON_OBJECT('nome', 'Politiche', 'descrizione', 'Politica ambientale', 'icona', 'fas fa-file-alt'),
    JSON_OBJECT('nome', 'Procedure', 'descrizione', 'Procedure ambientali', 'icona', 'fas fa-clipboard-list'),
    JSON_OBJECT('nome', 'Moduli_Registrazioni', 'descrizione', 'Registrazioni ambientali', 'icona', 'fas fa-file-invoice'),
    JSON_OBJECT('nome', 'Aspetti_Ambientali', 'descrizione', 'Analisi aspetti e impatti ambientali', 'icona', 'fas fa-leaf'),
    JSON_OBJECT('nome', 'Requisiti_Legali', 'descrizione', 'Requisiti legali e conformità', 'icona', 'fas fa-gavel'),
    JSON_OBJECT('nome', 'Emergenze_Ambientali', 'descrizione', 'Gestione emergenze ambientali', 'icona', 'fas fa-exclamation-circle'),
    JSON_OBJECT('nome', 'Monitoraggio_Misure', 'descrizione', 'Monitoraggio e misurazioni', 'icona', 'fas fa-tachometer-alt'),
    JSON_OBJECT('nome', 'Audit', 'descrizione', 'Audit ambientali', 'icona', 'fas fa-search'),
    JSON_OBJECT('nome', 'Non_Conformità', 'descrizione', 'Non conformità ambientali', 'icona', 'fas fa-exclamation-triangle'),
    JSON_OBJECT('nome', 'Indicatori_KPI', 'descrizione', 'Indicatori ambientali', 'icona', 'fas fa-chart-bar')
)),

('45001', 'ISO 45001:2018 - Sistema di Gestione Salute e Sicurezza', 'Norma internazionale per la salute e sicurezza sul lavoro',
JSON_ARRAY(
    JSON_OBJECT('nome', 'Manuale_Sistema', 'descrizione', 'Manuale sicurezza', 'icona', 'fas fa-book'),
    JSON_OBJECT('nome', 'Politiche', 'descrizione', 'Politica sicurezza', 'icona', 'fas fa-file-alt'),
    JSON_OBJECT('nome', 'Procedure', 'descrizione', 'Procedure sicurezza', 'icona', 'fas fa-clipboard-list'),
    JSON_OBJECT('nome', 'Valutazione_Rischi', 'descrizione', 'DVR e valutazioni rischi', 'icona', 'fas fa-shield-alt'),
    JSON_OBJECT('nome', 'DPI', 'descrizione', 'Dispositivi protezione individuale', 'icona', 'fas fa-hard-hat'),
    JSON_OBJECT('nome', 'Formazione', 'descrizione', 'Formazione sicurezza', 'icona', 'fas fa-graduation-cap'),
    JSON_OBJECT('nome', 'Sorveglianza_Sanitaria', 'descrizione', 'Sorveglianza sanitaria', 'icona', 'fas fa-user-md'),
    JSON_OBJECT('nome', 'Emergenze', 'descrizione', 'Gestione emergenze', 'icona', 'fas fa-fire-extinguisher'),
    JSON_OBJECT('nome', 'Infortuni_Incidenti', 'descrizione', 'Registro infortuni', 'icona', 'fas fa-ambulance'),
    JSON_OBJECT('nome', 'Audit', 'descrizione', 'Audit sicurezza', 'icona', 'fas fa-search'),
    JSON_OBJECT('nome', 'Indicatori_KPI', 'descrizione', 'Indicatori sicurezza', 'icona', 'fas fa-chart-bar')
)),

('27001', 'ISO 27001:2022 - Sistema di Gestione Sicurezza Informazioni', 'Norma internazionale per la sicurezza delle informazioni',
JSON_ARRAY(
    JSON_OBJECT('nome', 'Manuale_Sistema', 'descrizione', 'Manuale SGSI', 'icona', 'fas fa-book'),
    JSON_OBJECT('nome', 'Politiche', 'descrizione', 'Politiche sicurezza informazioni', 'icona', 'fas fa-file-alt'),
    JSON_OBJECT('nome', 'Procedure', 'descrizione', 'Procedure sicurezza IT', 'icona', 'fas fa-clipboard-list'),
    JSON_OBJECT('nome', 'Risk_Assessment', 'descrizione', 'Valutazione rischi IT', 'icona', 'fas fa-shield-alt'),
    JSON_OBJECT('nome', 'Asset_Inventory', 'descrizione', 'Inventario asset', 'icona', 'fas fa-server'),
    JSON_OBJECT('nome', 'Access_Control', 'descrizione', 'Controllo accessi', 'icona', 'fas fa-key'),
    JSON_OBJECT('nome', 'Incident_Response', 'descrizione', 'Gestione incidenti', 'icona', 'fas fa-exclamation-circle'),
    JSON_OBJECT('nome', 'Business_Continuity', 'descrizione', 'Continuità operativa', 'icona', 'fas fa-sync'),
    JSON_OBJECT('nome', 'Audit', 'descrizione', 'Audit sicurezza IT', 'icona', 'fas fa-search'),
    JSON_OBJECT('nome', 'Formazione', 'descrizione', 'Awareness sicurezza', 'icona', 'fas fa-graduation-cap'),
    JSON_OBJECT('nome', 'Indicatori_KPI', 'descrizione', 'Metriche sicurezza', 'icona', 'fas fa-chart-bar')
));

-- Trigger per gestione automatica cestino
DELIMITER //

CREATE TRIGGER before_delete_cartella
BEFORE DELETE ON cartelle
FOR EACH ROW
BEGIN
    IF OLD.cestinata = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Impossibile eliminare direttamente. Usare il cestino.';
    END IF;
END//

CREATE TRIGGER before_delete_documento
BEFORE DELETE ON documenti
FOR EACH ROW
BEGIN
    DECLARE is_in_cestino INT;
    SELECT COUNT(*) INTO is_in_cestino 
    FROM cestino_documenti 
    WHERE tipo_oggetto = 'documento' AND id_oggetto = OLD.id;
    
    IF is_in_cestino = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Impossibile eliminare direttamente. Usare il cestino.';
    END IF;
END//

DELIMITER ;

-- Vista per documenti con metadati completi
CREATE OR REPLACE VIEW vista_documenti_completi AS
SELECT 
    d.*,
    c.nome AS nome_cartella,
    c.percorso_completo,
    s.nome AS nome_spazio,
    s.tipo_spazio,
    s.id_azienda AS id_azienda_spazio,
    dmi.codice_documento,
    dmi.tipo_documento,
    dmi.livello_distribuzione,
    dmi.prossima_revisione,
    dmi.responsabile_documento,
    ur.nome AS nome_responsabile,
    dv.numero_versione AS ultima_versione,
    dv.stato_workflow,
    dv.data_approvazione
FROM documenti d
LEFT JOIN cartelle c ON d.id_cartella = c.id
LEFT JOIN spazi_documentali s ON c.id_spazio = s.id
LEFT JOIN documenti_metadati_iso dmi ON d.id = dmi.id_documento
LEFT JOIN utenti ur ON dmi.responsabile_documento = ur.id
LEFT JOIN (
    SELECT id_documento, numero_versione, stato_workflow, data_approvazione
    FROM documenti_versioni_extended
    WHERE id IN (
        SELECT MAX(id) FROM documenti_versioni_extended GROUP BY id_documento
    )
) dv ON d.id = dv.id_documento;

-- Vista per cartelle con conteggio documenti
CREATE OR REPLACE VIEW vista_cartelle_documenti AS
SELECT 
    c.*,
    s.nome AS nome_spazio,
    s.tipo_spazio,
    COUNT(DISTINCT d.id) AS numero_documenti,
    COUNT(DISTINCT cs.id) AS numero_sottocartelle,
    SUM(d.dimensione_file) AS dimensione_totale
FROM cartelle c
LEFT JOIN spazi_documentali s ON c.id_spazio = s.id
LEFT JOIN documenti d ON c.id = d.id_cartella AND d.eliminato = 0
LEFT JOIN cartelle cs ON c.id = cs.id_cartella_padre AND cs.eliminata = 0 AND cs.cestinata = 0
WHERE c.eliminata = 0 AND c.cestinata = 0
GROUP BY c.id;