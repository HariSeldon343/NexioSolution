-- Tabella per il Piano di Classificazione (Titolario)
CREATE TABLE IF NOT EXISTS classificazione (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    codice VARCHAR(20) NOT NULL,
    descrizione VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    livello INT NOT NULL DEFAULT 1,
    attivo BOOLEAN DEFAULT TRUE,
    note TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES classificazione(id) ON DELETE CASCADE,
    UNIQUE KEY unique_codice_azienda (azienda_id, codice),
    INDEX idx_parent (parent_id),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per i Fascicoli
CREATE TABLE IF NOT EXISTS fascicoli (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    classificazione_id INT NOT NULL,
    codice VARCHAR(50) NOT NULL,
    oggetto VARCHAR(500) NOT NULL,
    anno INT NOT NULL,
    stato ENUM('aperto', 'chiuso', 'versato') DEFAULT 'aperto',
    data_apertura DATE NOT NULL,
    data_chiusura DATE,
    responsabile_id INT,
    note TEXT,
    metadata JSON,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (classificazione_id) REFERENCES classificazione(id),
    FOREIGN KEY (responsabile_id) REFERENCES utenti(id),
    UNIQUE KEY unique_fascicolo (azienda_id, codice, anno),
    INDEX idx_classificazione (classificazione_id),
    INDEX idx_anno (anno),
    INDEX idx_stato (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per collegare documenti ai fascicoli
CREATE TABLE IF NOT EXISTS documenti_fascicoli (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    fascicolo_id INT NOT NULL,
    ordine INT DEFAULT 0,
    principale BOOLEAN DEFAULT FALSE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (fascicolo_id) REFERENCES fascicoli(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doc_fascicolo (documento_id, fascicolo_id),
    INDEX idx_fascicolo (fascicolo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiunta colonne alla tabella documenti per supportare la classificazione
ALTER TABLE documenti 
ADD COLUMN classificazione_id INT AFTER azienda_id,
ADD COLUMN numero_protocollo VARCHAR(50),
ADD COLUMN data_protocollo DATE,
ADD COLUMN versioning_abilitato BOOLEAN DEFAULT FALSE,
ADD COLUMN versione_corrente INT DEFAULT 1,
ADD FOREIGN KEY (classificazione_id) REFERENCES classificazione(id);

-- Tabella per le versioni dei documenti
CREATE TABLE IF NOT EXISTS documenti_versioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    versione INT NOT NULL,
    contenuto LONGTEXT,
    autore_id INT NOT NULL,
    note_versione TEXT,
    hash_contenuto VARCHAR(64),
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (autore_id) REFERENCES utenti(id),
    UNIQUE KEY unique_doc_version (documento_id, versione),
    INDEX idx_documento (documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per i destinatari dei documenti
CREATE TABLE IF NOT EXISTS documenti_destinatari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    referente_id INT NOT NULL,
    tipo_destinatario ENUM('principale', 'conoscenza', 'nascosto') DEFAULT 'principale',
    letto BOOLEAN DEFAULT FALSE,
    data_lettura TIMESTAMP NULL,
    notificato BOOLEAN DEFAULT FALSE,
    data_notifica TIMESTAMP NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (referente_id) REFERENCES referenti_aziende(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doc_dest (documento_id, referente_id),
    INDEX idx_referente (referente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserimento classificazione di esempio
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello) VALUES
(1, '01', 'AMMINISTRAZIONE', NULL, 1),
(1, '01.01', 'Organi di governo', 1, 2),
(1, '01.02', 'Organizzazione', 1, 2),
(1, '02', 'RISORSE UMANE', NULL, 1),
(1, '02.01', 'Gestione del personale', 4, 2),
(1, '02.02', 'Formazione', 4, 2),
(1, '03', 'RISORSE FINANZIARIE', NULL, 1),
(1, '03.01', 'Bilancio', 7, 2),
(1, '03.02', 'Contabilità', 7, 2),
(1, '04', 'ATTIVITÀ ISTITUZIONALI', NULL, 1),
(1, '04.01', 'Progetti', 10, 2),
(1, '04.02', 'Servizi', 10, 2); 