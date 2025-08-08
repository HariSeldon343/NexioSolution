-- Sistema Documentale ISO - Versione Semplificata
-- Solo tabelle base senza stored procedures o trigger complessi

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
CREATE INDEX IF NOT EXISTS idx_cartelle_percorso ON cartelle_iso(percorso_completo(255));
CREATE INDEX IF NOT EXISTS idx_documenti_ricerca ON documenti_iso(titolo, stato, tipo_iso);
CREATE INDEX IF NOT EXISTS idx_versioni_documento ON versioni_documenti_iso(documento_id, versione);