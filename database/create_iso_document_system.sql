-- Sistema Documentale ISO Completo
-- Creazione tabelle per il nuovo sistema documentale ISO

-- Tabella per gli spazi documentali isolati
CREATE TABLE IF NOT EXISTS spazi_documentali (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('super_admin', 'azienda') NOT NULL,
    azienda_id INT DEFAULT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_tipo (tipo),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per le cartelle ISO
CREATE TABLE IF NOT EXISTS cartelle_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    spazio_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    nome VARCHAR(255) NOT NULL,
    percorso_completo TEXT NOT NULL,
    livello INT NOT NULL DEFAULT 0,
    tipo_iso VARCHAR(50) DEFAULT NULL,
    icona VARCHAR(50) DEFAULT 'fas fa-folder',
    colore VARCHAR(7) DEFAULT '#fbbf24',
    descrizione TEXT,
    ordine INT DEFAULT 0,
    protetta BOOLEAN DEFAULT FALSE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spazio_id) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES cartelle_iso(id) ON DELETE CASCADE,
    INDEX idx_spazio (spazio_id),
    INDEX idx_parent (parent_id),
    INDEX idx_percorso (percorso_completo(255)),
    INDEX idx_tipo_iso (tipo_iso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per i documenti ISO
CREATE TABLE IF NOT EXISTS documenti_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    spazio_id INT NOT NULL,
    cartella_id INT DEFAULT NULL,
    titolo VARCHAR(255) NOT NULL,
    codice VARCHAR(100) UNIQUE NOT NULL,
    descrizione TEXT,
    file_path VARCHAR(500),
    tipo_file VARCHAR(50),
    dimensione_file BIGINT DEFAULT 0,
    versione INT DEFAULT 1,
    stato ENUM('bozza', 'pubblicato', 'archiviato') DEFAULT 'pubblicato',
    tipo_iso VARCHAR(50),
    classificazione VARCHAR(100),
    tags TEXT,
    metadata JSON,
    creato_da INT NOT NULL,
    modificato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (spazio_id) REFERENCES spazi_documentali(id) ON DELETE CASCADE,
    FOREIGN KEY (cartella_id) REFERENCES cartelle_iso(id) ON DELETE SET NULL,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    FOREIGN KEY (modificato_da) REFERENCES utenti(id),
    INDEX idx_spazio (spazio_id),
    INDEX idx_cartella (cartella_id),
    INDEX idx_codice (codice),
    INDEX idx_stato (stato),
    INDEX idx_tipo_iso (tipo_iso),
    FULLTEXT idx_ricerca (titolo, descrizione, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per le versioni dei documenti
CREATE TABLE IF NOT EXISTS versioni_documenti_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    versione INT NOT NULL,
    file_path VARCHAR(500),
    dimensione_file BIGINT DEFAULT 0,
    note_versione TEXT,
    creato_da INT NOT NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti_iso(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id),
    INDEX idx_documento (documento_id),
    INDEX idx_versione (documento_id, versione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per le strutture ISO predefinite
CREATE TABLE IF NOT EXISTS strutture_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    struttura_json JSON NOT NULL,
    versione VARCHAR(20),
    attiva BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codice (codice),
    INDEX idx_attiva (attiva)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per le classificazioni ISO
CREATE TABLE IF NOT EXISTS classificazioni_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_iso VARCHAR(50) NOT NULL,
    codice VARCHAR(50) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    parent_id INT DEFAULT NULL,
    ordine INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES classificazioni_iso(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tipo_codice (tipo_iso, codice),
    INDEX idx_tipo (tipo_iso),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per i permessi sui documenti
CREATE TABLE IF NOT EXISTS permessi_documenti_iso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    utente_id INT DEFAULT NULL,
    ruolo VARCHAR(50) DEFAULT NULL,
    azienda_id INT DEFAULT NULL,
    tipo_permesso ENUM('lettura', 'scrittura', 'eliminazione', 'condivisione') NOT NULL,
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assegnato_da INT NOT NULL,
    FOREIGN KEY (documento_id) REFERENCES documenti_iso(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (assegnato_da) REFERENCES utenti(id),
    INDEX idx_documento (documento_id),
    INDEX idx_utente (utente_id),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella per le impostazioni ISO per azienda
CREATE TABLE IF NOT EXISTS impostazioni_iso_azienda (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    modalita ENUM('separato', 'integrato') DEFAULT 'integrato',
    iso_9001_attivo BOOLEAN DEFAULT TRUE,
    iso_14001_attivo BOOLEAN DEFAULT FALSE,
    iso_45001_attivo BOOLEAN DEFAULT FALSE,
    iso_27001_attivo BOOLEAN DEFAULT FALSE,
    altre_norme JSON,
    impostazioni_custom JSON,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY uk_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserimento strutture ISO predefinite
INSERT INTO strutture_iso (codice, nome, descrizione, versione, struttura_json) VALUES
('ISO_9001', 'ISO 9001:2015', 'Sistema di Gestione Qualità', '2015', '{"capitoli": [
    {"numero": "4", "nome": "Contesto dell\'organizzazione", "sottocapitoli": [
        {"numero": "4.1", "nome": "Comprendere l\'organizzazione e il suo contesto"},
        {"numero": "4.2", "nome": "Comprendere le esigenze e le aspettative delle parti interessate"},
        {"numero": "4.3", "nome": "Determinare il campo di applicazione del SGQ"},
        {"numero": "4.4", "nome": "Sistema di gestione per la qualità e relativi processi"}
    ]},
    {"numero": "5", "nome": "Leadership", "sottocapitoli": [
        {"numero": "5.1", "nome": "Leadership e impegno"},
        {"numero": "5.2", "nome": "Politica"},
        {"numero": "5.3", "nome": "Ruoli, responsabilità e autorità nell\'organizzazione"}
    ]},
    {"numero": "6", "nome": "Pianificazione", "sottocapitoli": [
        {"numero": "6.1", "nome": "Azioni per affrontare rischi e opportunità"},
        {"numero": "6.2", "nome": "Obiettivi per la qualità e pianificazione per il loro raggiungimento"},
        {"numero": "6.3", "nome": "Pianificazione delle modifiche"}
    ]},
    {"numero": "7", "nome": "Supporto", "sottocapitoli": [
        {"numero": "7.1", "nome": "Risorse"},
        {"numero": "7.2", "nome": "Competenza"},
        {"numero": "7.3", "nome": "Consapevolezza"},
        {"numero": "7.4", "nome": "Comunicazione"},
        {"numero": "7.5", "nome": "Informazioni documentate"}
    ]},
    {"numero": "8", "nome": "Attività operative", "sottocapitoli": [
        {"numero": "8.1", "nome": "Pianificazione e controllo operativi"},
        {"numero": "8.2", "nome": "Requisiti per i prodotti e servizi"},
        {"numero": "8.3", "nome": "Progettazione e sviluppo di prodotti e servizi"},
        {"numero": "8.4", "nome": "Controllo dei processi, prodotti e servizi forniti dall\'esterno"},
        {"numero": "8.5", "nome": "Produzione ed erogazione dei servizi"},
        {"numero": "8.6", "nome": "Rilascio di prodotti e servizi"},
        {"numero": "8.7", "nome": "Controllo degli output non conformi"}
    ]},
    {"numero": "9", "nome": "Valutazione delle prestazioni", "sottocapitoli": [
        {"numero": "9.1", "nome": "Monitoraggio, misurazione, analisi e valutazione"},
        {"numero": "9.2", "nome": "Audit interno"},
        {"numero": "9.3", "nome": "Riesame di direzione"}
    ]},
    {"numero": "10", "nome": "Miglioramento", "sottocapitoli": [
        {"numero": "10.1", "nome": "Generalità"},
        {"numero": "10.2", "nome": "Non conformità e azioni correttive"},
        {"numero": "10.3", "nome": "Miglioramento continuo"}
    ]}
]}'),

('ISO_14001', 'ISO 14001:2015', 'Sistema di Gestione Ambientale', '2015', '{"capitoli": [
    {"numero": "4", "nome": "Contesto dell\'organizzazione", "sottocapitoli": [
        {"numero": "4.1", "nome": "Comprendere l\'organizzazione e il suo contesto"},
        {"numero": "4.2", "nome": "Comprendere le esigenze e le aspettative delle parti interessate"},
        {"numero": "4.3", "nome": "Determinare il campo di applicazione del SGA"},
        {"numero": "4.4", "nome": "Sistema di gestione ambientale"}
    ]},
    {"numero": "5", "nome": "Leadership", "sottocapitoli": [
        {"numero": "5.1", "nome": "Leadership e impegno"},
        {"numero": "5.2", "nome": "Politica ambientale"},
        {"numero": "5.3", "nome": "Ruoli, responsabilità e autorità nell\'organizzazione"}
    ]},
    {"numero": "6", "nome": "Pianificazione", "sottocapitoli": [
        {"numero": "6.1", "nome": "Azioni per affrontare rischi e opportunità"},
        {"numero": "6.2", "nome": "Obiettivi ambientali e pianificazione per il loro raggiungimento"}
    ]},
    {"numero": "7", "nome": "Supporto", "sottocapitoli": [
        {"numero": "7.1", "nome": "Risorse"},
        {"numero": "7.2", "nome": "Competenza"},
        {"numero": "7.3", "nome": "Consapevolezza"},
        {"numero": "7.4", "nome": "Comunicazione"},
        {"numero": "7.5", "nome": "Informazioni documentate"}
    ]},
    {"numero": "8", "nome": "Attività operative", "sottocapitoli": [
        {"numero": "8.1", "nome": "Pianificazione e controllo operativi"},
        {"numero": "8.2", "nome": "Preparazione e risposta alle emergenze"}
    ]},
    {"numero": "9", "nome": "Valutazione delle prestazioni", "sottocapitoli": [
        {"numero": "9.1", "nome": "Monitoraggio, misurazione, analisi e valutazione"},
        {"numero": "9.2", "nome": "Audit interno"},
        {"numero": "9.3", "nome": "Riesame di direzione"}
    ]},
    {"numero": "10", "nome": "Miglioramento", "sottocapitoli": [
        {"numero": "10.1", "nome": "Generalità"},
        {"numero": "10.2", "nome": "Non conformità e azioni correttive"},
        {"numero": "10.3", "nome": "Miglioramento continuo"}
    ]}
]}'),

('ISO_45001', 'ISO 45001:2018', 'Sistema di Gestione Salute e Sicurezza sul Lavoro', '2018', '{"capitoli": [
    {"numero": "4", "nome": "Contesto dell\'organizzazione", "sottocapitoli": [
        {"numero": "4.1", "nome": "Comprendere l\'organizzazione e il suo contesto"},
        {"numero": "4.2", "nome": "Comprendere le esigenze e le aspettative dei lavoratori e delle altre parti interessate"},
        {"numero": "4.3", "nome": "Determinare il campo di applicazione del sistema di gestione SSL"},
        {"numero": "4.4", "nome": "Sistema di gestione SSL"}
    ]},
    {"numero": "5", "nome": "Leadership e partecipazione dei lavoratori", "sottocapitoli": [
        {"numero": "5.1", "nome": "Leadership e impegno"},
        {"numero": "5.2", "nome": "Politica SSL"},
        {"numero": "5.3", "nome": "Ruoli, responsabilità e autorità nell\'organizzazione"},
        {"numero": "5.4", "nome": "Consultazione e partecipazione dei lavoratori"}
    ]},
    {"numero": "6", "nome": "Pianificazione", "sottocapitoli": [
        {"numero": "6.1", "nome": "Azioni per affrontare rischi e opportunità"},
        {"numero": "6.2", "nome": "Obiettivi SSL e pianificazione per il loro raggiungimento"}
    ]},
    {"numero": "7", "nome": "Supporto", "sottocapitoli": [
        {"numero": "7.1", "nome": "Risorse"},
        {"numero": "7.2", "nome": "Competenza"},
        {"numero": "7.3", "nome": "Consapevolezza"},
        {"numero": "7.4", "nome": "Comunicazione"},
        {"numero": "7.5", "nome": "Informazioni documentate"}
    ]},
    {"numero": "8", "nome": "Attività operative", "sottocapitoli": [
        {"numero": "8.1", "nome": "Pianificazione e controllo operativi"},
        {"numero": "8.2", "nome": "Preparazione e risposta alle emergenze"}
    ]},
    {"numero": "9", "nome": "Valutazione delle prestazioni", "sottocapitoli": [
        {"numero": "9.1", "nome": "Monitoraggio, misurazione, analisi e valutazione"},
        {"numero": "9.2", "nome": "Audit interno"},
        {"numero": "9.3", "nome": "Riesame di direzione"}
    ]},
    {"numero": "10", "nome": "Miglioramento", "sottocapitoli": [
        {"numero": "10.1", "nome": "Generalità"},
        {"numero": "10.2", "nome": "Incidenti, non conformità e azioni correttive"},
        {"numero": "10.3", "nome": "Miglioramento continuo"}
    ]}
]}'),

('ISO_27001', 'ISO/IEC 27001:2022', 'Sistema di Gestione Sicurezza delle Informazioni', '2022', '{"capitoli": [
    {"numero": "4", "nome": "Contesto dell\'organizzazione", "sottocapitoli": [
        {"numero": "4.1", "nome": "Comprendere l\'organizzazione e il suo contesto"},
        {"numero": "4.2", "nome": "Comprendere le esigenze e le aspettative delle parti interessate"},
        {"numero": "4.3", "nome": "Determinare il campo di applicazione del SGSI"},
        {"numero": "4.4", "nome": "Sistema di gestione per la sicurezza delle informazioni"}
    ]},
    {"numero": "5", "nome": "Leadership", "sottocapitoli": [
        {"numero": "5.1", "nome": "Leadership e impegno"},
        {"numero": "5.2", "nome": "Politica"},
        {"numero": "5.3", "nome": "Ruoli, responsabilità e autorità nell\'organizzazione"}
    ]},
    {"numero": "6", "nome": "Pianificazione", "sottocapitoli": [
        {"numero": "6.1", "nome": "Azioni per affrontare rischi e opportunità"},
        {"numero": "6.2", "nome": "Obiettivi per la sicurezza delle informazioni e pianificazione per il loro raggiungimento"},
        {"numero": "6.3", "nome": "Pianificazione delle modifiche"}
    ]},
    {"numero": "7", "nome": "Supporto", "sottocapitoli": [
        {"numero": "7.1", "nome": "Risorse"},
        {"numero": "7.2", "nome": "Competenza"},
        {"numero": "7.3", "nome": "Consapevolezza"},
        {"numero": "7.4", "nome": "Comunicazione"},
        {"numero": "7.5", "nome": "Informazioni documentate"}
    ]},
    {"numero": "8", "nome": "Attività operative", "sottocapitoli": [
        {"numero": "8.1", "nome": "Pianificazione e controllo operativi"},
        {"numero": "8.2", "nome": "Valutazione del rischio per la sicurezza delle informazioni"},
        {"numero": "8.3", "nome": "Trattamento del rischio per la sicurezza delle informazioni"}
    ]},
    {"numero": "9", "nome": "Valutazione delle prestazioni", "sottocapitoli": [
        {"numero": "9.1", "nome": "Monitoraggio, misurazione, analisi e valutazione"},
        {"numero": "9.2", "nome": "Audit interno"},
        {"numero": "9.3", "nome": "Riesame di direzione"}
    ]},
    {"numero": "10", "nome": "Miglioramento", "sottocapitoli": [
        {"numero": "10.1", "nome": "Miglioramento continuo"},
        {"numero": "10.2", "nome": "Non conformità e azioni correttive"}
    ]}
]}');

-- Inserimento classificazioni ISO di base
INSERT INTO classificazioni_iso (tipo_iso, codice, nome, descrizione, ordine) VALUES
-- ISO 9001
('ISO_9001', 'POL', 'Politiche', 'Documenti di politica aziendale', 1),
('ISO_9001', 'PRO', 'Procedure', 'Procedure operative standard', 2),
('ISO_9001', 'IST', 'Istruzioni', 'Istruzioni di lavoro', 3),
('ISO_9001', 'MOD', 'Moduli', 'Moduli e registrazioni', 4),
('ISO_9001', 'MAN', 'Manuali', 'Manuali di sistema', 5),

-- ISO 14001
('ISO_14001', 'AAI', 'Analisi Ambientale Iniziale', 'Documenti di analisi ambientale', 1),
('ISO_14001', 'ASP', 'Aspetti Ambientali', 'Registro aspetti ambientali', 2),
('ISO_14001', 'REQ', 'Requisiti Legali', 'Registro requisiti legali ambientali', 3),
('ISO_14001', 'OBA', 'Obiettivi Ambientali', 'Obiettivi e programmi ambientali', 4),
('ISO_14001', 'EME', 'Emergenze', 'Piani di emergenza ambientale', 5),

-- ISO 45001
('ISO_45001', 'DVR', 'Documento Valutazione Rischi', 'Documenti di valutazione dei rischi', 1),
('ISO_45001', 'POS', 'Procedure Operative Sicurezza', 'Procedure di sicurezza', 2),
('ISO_45001', 'DPI', 'Dispositivi Protezione Individuale', 'Gestione DPI', 3),
('ISO_45001', 'FOR', 'Formazione', 'Registri formazione sicurezza', 4),
('ISO_45001', 'INF', 'Infortuni', 'Registro infortuni e near miss', 5),

-- ISO 27001
('ISO_27001', 'PSI', 'Politica Sicurezza Informazioni', 'Politiche di sicurezza IT', 1),
('ISO_27001', 'RIS', 'Analisi Rischi', 'Valutazione rischi informatici', 2),
('ISO_27001', 'INC', 'Gestione Incidenti', 'Registro incidenti di sicurezza', 3),
('ISO_27001', 'ACC', 'Controllo Accessi', 'Procedure controllo accessi', 4),
('ISO_27001', 'BCM', 'Business Continuity', 'Piani di continuità operativa', 5);

-- Stored Procedure per creare spazio documentale
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS crea_spazio_documentale(
    IN p_tipo VARCHAR(20),
    IN p_azienda_id INT,
    IN p_nome VARCHAR(255)
)
BEGIN
    DECLARE v_spazio_id INT;
    
    -- Crea lo spazio documentale
    INSERT INTO spazi_documentali (tipo, azienda_id, nome)
    VALUES (p_tipo, p_azienda_id, p_nome);
    
    SET v_spazio_id = LAST_INSERT_ID();
    
    -- Restituisce l'ID dello spazio creato
    SELECT v_spazio_id AS spazio_id;
END//
DELIMITER ;

-- Stored Procedure per creare struttura ISO
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS crea_struttura_iso(
    IN p_spazio_id INT,
    IN p_tipo_iso VARCHAR(50),
    IN p_modalita VARCHAR(20)
)
BEGIN
    DECLARE v_struttura_json JSON;
    DECLARE v_parent_id INT DEFAULT NULL;
    DECLARE v_cartella_id INT;
    DECLARE v_nome_iso VARCHAR(255);
    
    -- Recupera la struttura JSON per il tipo ISO
    SELECT struttura_json, nome INTO v_struttura_json, v_nome_iso
    FROM strutture_iso 
    WHERE codice = p_tipo_iso AND attiva = TRUE;
    
    IF v_struttura_json IS NOT NULL THEN
        -- Se modalità separata, crea cartella root per la norma
        IF p_modalita = 'separato' THEN
            INSERT INTO cartelle_iso (spazio_id, parent_id, nome, percorso_completo, livello, tipo_iso, icona, colore, protetta)
            VALUES (p_spazio_id, NULL, v_nome_iso, v_nome_iso, 0, p_tipo_iso, 'fas fa-certificate', '#2563eb', TRUE);
            
            SET v_parent_id = LAST_INSERT_ID();
        END IF;
        
        -- Crea la struttura delle cartelle basata sul JSON
        -- (Qui andrebbe la logica per parsare il JSON e creare le cartelle)
        -- Per ora creiamo una struttura di esempio
        
        -- Crea cartelle principali
        INSERT INTO cartelle_iso (spazio_id, parent_id, nome, percorso_completo, livello, tipo_iso, ordine, protetta)
        VALUES 
        (p_spazio_id, v_parent_id, '4. Contesto organizzazione', CONCAT(IFNULL(v_nome_iso, ''), '/4. Contesto organizzazione'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 4, TRUE),
        (p_spazio_id, v_parent_id, '5. Leadership', CONCAT(IFNULL(v_nome_iso, ''), '/5. Leadership'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 5, TRUE),
        (p_spazio_id, v_parent_id, '6. Pianificazione', CONCAT(IFNULL(v_nome_iso, ''), '/6. Pianificazione'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 6, TRUE),
        (p_spazio_id, v_parent_id, '7. Supporto', CONCAT(IFNULL(v_nome_iso, ''), '/7. Supporto'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 7, TRUE),
        (p_spazio_id, v_parent_id, '8. Attività operative', CONCAT(IFNULL(v_nome_iso, ''), '/8. Attività operative'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 8, TRUE),
        (p_spazio_id, v_parent_id, '9. Valutazione prestazioni', CONCAT(IFNULL(v_nome_iso, ''), '/9. Valutazione prestazioni'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 9, TRUE),
        (p_spazio_id, v_parent_id, '10. Miglioramento', CONCAT(IFNULL(v_nome_iso, ''), '/10. Miglioramento'), IF(v_parent_id IS NULL, 0, 1), p_tipo_iso, 10, TRUE);
        
    END IF;
END//
DELIMITER ;

-- Trigger per aggiornare percorso_completo
DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_insert_cartella_iso
BEFORE INSERT ON cartelle_iso
FOR EACH ROW
BEGIN
    DECLARE v_parent_path TEXT;
    
    IF NEW.parent_id IS NOT NULL THEN
        SELECT percorso_completo INTO v_parent_path
        FROM cartelle_iso
        WHERE id = NEW.parent_id;
        
        SET NEW.percorso_completo = CONCAT(v_parent_path, '/', NEW.nome);
        SET NEW.livello = (SELECT livello + 1 FROM cartelle_iso WHERE id = NEW.parent_id);
    ELSE
        SET NEW.percorso_completo = NEW.nome;
        SET NEW.livello = 0;
    END IF;
END//
DELIMITER ;

-- Vista per documenti con informazioni complete
CREATE OR REPLACE VIEW vista_documenti_iso AS
SELECT 
    d.*,
    c.nome AS cartella_nome,
    c.percorso_completo AS cartella_percorso,
    s.nome AS spazio_nome,
    s.tipo AS spazio_tipo,
    s.azienda_id,
    a.nome AS azienda_nome,
    u1.nome AS creato_da_nome,
    u1.cognome AS creato_da_cognome,
    u2.nome AS modificato_da_nome,
    u2.cognome AS modificato_da_cognome,
    cl.nome AS classificazione_nome
FROM documenti_iso d
LEFT JOIN cartelle_iso c ON d.cartella_id = c.id
LEFT JOIN spazi_documentali s ON d.spazio_id = s.id
LEFT JOIN aziende a ON s.azienda_id = a.id
LEFT JOIN utenti u1 ON d.creato_da = u1.id
LEFT JOIN utenti u2 ON d.modificato_da = u2.id
LEFT JOIN classificazioni_iso cl ON d.classificazione = cl.codice AND d.tipo_iso = cl.tipo_iso;

-- Indici per performance
CREATE INDEX idx_cartelle_percorso ON cartelle_iso(percorso_completo(255));
CREATE INDEX idx_documenti_ricerca ON documenti_iso(titolo, stato, tipo_iso);
CREATE INDEX idx_versioni_documento ON versioni_documenti_iso(documento_id, versione);