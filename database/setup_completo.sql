-- SETUP COMPLETO DATABASE PIATTAFORMA COLLABORATIVA
-- Eseguire questo file per configurare completamente il database

-- 1. TABELLA CLASSIFICAZIONE (se non esiste)
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

-- 2. TABELLA DOCUMENTI_DESTINATARI
CREATE TABLE IF NOT EXISTS documenti_destinatari (
    id int(11) NOT NULL AUTO_INCREMENT,
    documento_id int(11) NOT NULL,
    referente_id int(11) NOT NULL,
    tipo_destinatario enum('principale','conoscenza') DEFAULT 'principale',
    data_invio datetime DEFAULT NULL,
    data_lettura datetime DEFAULT NULL,
    creato_il timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documento (documento_id),
    KEY idx_referente (referente_id),
    CONSTRAINT fk_documenti_destinatari_documento FOREIGN KEY (documento_id) REFERENCES documenti (id) ON DELETE CASCADE,
    CONSTRAINT fk_documenti_destinatari_referente FOREIGN KEY (referente_id) REFERENCES referenti_aziende (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABELLA DOCUMENTI_VERSIONI
CREATE TABLE IF NOT EXISTS documenti_versioni (
    id int(11) NOT NULL AUTO_INCREMENT,
    documento_id int(11) NOT NULL,
    versione int(11) NOT NULL,
    titolo varchar(255) NOT NULL,
    contenuto longtext,
    stato enum('bozza','pubblicato','archiviato') DEFAULT 'bozza',
    creato_da int(11) NOT NULL,
    creato_il datetime NOT NULL,
    note_versione text,
    PRIMARY KEY (id),
    UNIQUE KEY uk_documento_versione (documento_id, versione),
    KEY idx_documento (documento_id),
    KEY idx_creato_da (creato_da),
    CONSTRAINT fk_documenti_versioni_documento FOREIGN KEY (documento_id) REFERENCES documenti (id) ON DELETE CASCADE,
    CONSTRAINT fk_documenti_versioni_utente FOREIGN KEY (creato_da) REFERENCES utenti (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TABELLA REFERENTI_AZIENDE (se non esiste)
CREATE TABLE IF NOT EXISTS referenti_aziende (
    id int(11) NOT NULL AUTO_INCREMENT,
    azienda_id int(11) NOT NULL,
    nome varchar(100) NOT NULL,
    cognome varchar(100) NOT NULL,
    email varchar(255) NOT NULL,
    telefono varchar(50) DEFAULT NULL,
    ruolo_aziendale varchar(100) DEFAULT NULL,
    riceve_notifiche tinyint(1) DEFAULT 1,
    attivo tinyint(1) DEFAULT 1,
    creato_il timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_azienda (azienda_id),
    CONSTRAINT fk_referenti_azienda FOREIGN KEY (azienda_id) REFERENCES aziende (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. AGGIORNA TABELLA DOCUMENTI
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS classificazione_id INT(11) AFTER azienda_id,
    ADD COLUMN IF NOT EXISTS versioning_abilitato TINYINT(1) DEFAULT 0 AFTER stato,
    ADD COLUMN IF NOT EXISTS versione_corrente INT(11) DEFAULT 1 AFTER versioning_abilitato,
    ADD COLUMN IF NOT EXISTS numero_protocollo VARCHAR(50) AFTER versione_corrente,
    ADD COLUMN IF NOT EXISTS data_protocollo DATE AFTER numero_protocollo,
    ADD COLUMN IF NOT EXISTS creato_da INT(11) AFTER data_protocollo,
    ADD COLUMN IF NOT EXISTS aggiornato_da INT(11) AFTER creato_da,
    ADD COLUMN IF NOT EXISTS aggiornato_il DATETIME AFTER aggiornato_da;

-- Aggiungi foreign keys se non esistono
ALTER TABLE documenti
    ADD CONSTRAINT fk_documenti_classificazione_check 
    FOREIGN KEY IF NOT EXISTS (classificazione_id) REFERENCES classificazione(id);

-- 6. AGGIORNA TABELLA MODULI_TEMPLATE
ALTER TABLE moduli_template
    ADD COLUMN IF NOT EXISTS contenuto LONGTEXT AFTER footer_content;

-- 7. INSERISCI DATI DI ESEMPIO

-- Referenti di esempio
INSERT INTO referenti_aziende (azienda_id, nome, cognome, email, ruolo_aziendale) VALUES
(1, 'Mario', 'Rossi', 'mario.rossi@example.com', 'Responsabile Qualità'),
(1, 'Laura', 'Bianchi', 'laura.bianchi@example.com', 'Responsabile Sicurezza'),
(1, 'Giuseppe', 'Verdi', 'giuseppe.verdi@example.com', 'Direttore Tecnico')
ON DUPLICATE KEY UPDATE nome=nome;

-- Template di esempio
INSERT INTO moduli_documento (azienda_id, nome, descrizione, tipo, attivo) VALUES
(1, 'Procedura Standard', 'Template per procedure aziendali', 'procedura', 1),
(1, 'Istruzione Operativa', 'Template per istruzioni operative', 'istruzione', 1),
(1, 'Modulo Registrazione', 'Template per moduli di registrazione', 'modulo', 1)
ON DUPLICATE KEY UPDATE nome=nome;

-- Template content
INSERT INTO moduli_template (modulo_id, nome, header_content, footer_content, contenuto) 
SELECT 
    id,
    CONCAT('Template ', nome),
    '<div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px;"><h1>[NOME_AZIENDA]</h1><p>[TITOLO_DOCUMENTO]</p></div>',
    '<div style="text-align: center; border-top: 1px solid #ccc; padding-top: 10px; margin-top: 20px;"><p>Pagina [PAGINA] di [TOTALE_PAGINE] - Rev. [VERSIONE] del [DATA]</p></div>',
    '<h2>1. SCOPO</h2><p>Descrivere lo scopo del documento...</p><h2>2. CAMPO DI APPLICAZIONE</h2><p>Definire il campo di applicazione...</p><h2>3. RESPONSABILITÀ</h2><p>Elencare le responsabilità...</p><h2>4. DESCRIZIONE DELLE ATTIVITÀ</h2><p>Descrivere le attività...</p>'
FROM moduli_documento 
WHERE azienda_id = 1
ON DUPLICATE KEY UPDATE nome=nome;

-- 8. INSERISCI CLASSIFICAZIONI COMPLETE

-- Pulizia classificazioni esistenti per l'azienda
DELETE FROM classificazione WHERE azienda_id = 1;

-- PRIMO LIVELLO - Categorie principali
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '1', 'Normative e Standard', NULL, 1, 1),
(1, '2', 'Documentazione del Sistema di Gestione', NULL, 1, 1),
(1, '3', 'Documenti di Origine Esterna', NULL, 1, 1);

-- SECONDO LIVELLO - Sottocategorie per "Normative e Standard"
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '1.1', 'ISO 9001 - Sistema di Gestione Qualità', 1, 2, 1),
(1, '1.2', 'ISO 14001 - Sistema di Gestione Ambientale', 1, 2, 1),
(1, '1.3', 'ISO 45001 - Sistema di Gestione Sicurezza', 1, 2, 1),
(1, '1.4', 'ISO 27001 - Sistema di Gestione Sicurezza Informazioni', 1, 2, 1),
(1, '1.5', 'Normative Nazionali/Europee', 1, 2, 1),
(1, '1.6', 'Regolamenti Settoriali', 1, 2, 1),
(1, '1.7', 'Aggiornamenti Normativi', 1, 2, 1);

-- SECONDO LIVELLO - Sottocategorie per "Documentazione del Sistema di Gestione"
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '2.1', 'Manuale della Qualità/Ambiente/Sicurezza', 2, 2, 1),
(1, '2.2', 'Politiche Aziendali', 2, 2, 1),
(1, '2.3', 'Procedure Generali', 2, 2, 1),
(1, '2.4', 'Procedure Operative', 2, 2, 1),
(1, '2.5', 'Istruzioni di Lavoro', 2, 2, 1),
(1, '2.6', 'Moduli e Registrazioni', 2, 2, 1);

-- SECONDO LIVELLO - Sottocategorie per "Documenti di Origine Esterna"
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '3.1', 'Contratti con Fornitori', 3, 2, 1),
(1, '3.2', 'Certificazioni', 3, 2, 1),
(1, '3.3', 'Autorizzazioni', 3, 2, 1),
(1, '3.4', 'Comunicazioni Enti', 3, 2, 1);

-- TERZO LIVELLO - Aree funzionali per "Procedure Generali" (2.3)
-- Nota: l'ID 13 potrebbe variare, quindi uso una subquery
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.1', 'Direzione e Strategia', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.2', 'Gestione Risorse Umane', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.3', 'Produzione/Erogazione Servizi', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.4', 'Qualità e Controllo', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.5', 'Ambiente e Sicurezza', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.6', 'Amministrazione e Finanza', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.3.7', 'Commerciale e Marketing', id, 3, 1 FROM classificazione WHERE codice = '2.3' AND azienda_id = 1;

-- TERZO LIVELLO - Aree funzionali per "Procedure Operative" (2.4)
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.1', 'Direzione e Strategia', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.2', 'Gestione Risorse Umane', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.3', 'Produzione/Erogazione Servizi', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.4', 'Qualità e Controllo', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.5', 'Ambiente e Sicurezza', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.6', 'Amministrazione e Finanza', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
SELECT 1, '2.4.7', 'Commerciale e Marketing', id, 3, 1 FROM classificazione WHERE codice = '2.4' AND azienda_id = 1;

-- 9. CREA INDICI PER PERFORMANCE
CREATE INDEX IF NOT EXISTS idx_documenti_classificazione ON documenti(classificazione_id);
CREATE INDEX IF NOT EXISTS idx_documenti_azienda ON documenti(azienda_id);
CREATE INDEX IF NOT EXISTS idx_documenti_stato ON documenti(stato);
CREATE INDEX IF NOT EXISTS idx_classificazione_azienda ON classificazione(azienda_id);
CREATE INDEX IF NOT EXISTS idx_classificazione_parent ON classificazione(parent_id);

-- FINE SETUP 