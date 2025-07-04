-- Verifica e aggiornamento struttura database

-- 1. Verifica/aggiorna tabella documenti
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS classificazione_id INT(11) AFTER azienda_id,
    ADD COLUMN IF NOT EXISTS versioning_abilitato TINYINT(1) DEFAULT 0 AFTER stato,
    ADD COLUMN IF NOT EXISTS versione_corrente INT(11) DEFAULT 1 AFTER versioning_abilitato,
    ADD COLUMN IF NOT EXISTS numero_protocollo VARCHAR(50) AFTER versione_corrente,
    ADD COLUMN IF NOT EXISTS data_protocollo DATE AFTER numero_protocollo,
    ADD COLUMN IF NOT EXISTS creato_da INT(11) AFTER data_protocollo,
    ADD COLUMN IF NOT EXISTS aggiornato_da INT(11) AFTER creato_da,
    ADD COLUMN IF NOT EXISTS aggiornato_il DATETIME AFTER aggiornato_da,
    ADD CONSTRAINT IF NOT EXISTS fk_documenti_classificazione 
        FOREIGN KEY (classificazione_id) REFERENCES classificazione(id),
    ADD CONSTRAINT IF NOT EXISTS fk_documenti_creato_da 
        FOREIGN KEY (creato_da) REFERENCES utenti(id),
    ADD CONSTRAINT IF NOT EXISTS fk_documenti_aggiornato_da 
        FOREIGN KEY (aggiornato_da) REFERENCES utenti(id);

-- 2. Verifica/crea tabella referenti_aziende se non esiste
CREATE TABLE IF NOT EXISTS `referenti_aziende` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `azienda_id` int(11) NOT NULL,
    `nome` varchar(100) NOT NULL,
    `cognome` varchar(100) NOT NULL,
    `email` varchar(255) NOT NULL,
    `telefono` varchar(50) DEFAULT NULL,
    `ruolo_aziendale` varchar(100) DEFAULT NULL,
    `riceve_notifiche` tinyint(1) DEFAULT 1,
    `attivo` tinyint(1) DEFAULT 1,
    `creato_il` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_azienda` (`azienda_id`),
    CONSTRAINT `fk_referenti_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Inserisci alcuni referenti di esempio per test
INSERT INTO referenti_aziende (azienda_id, nome, cognome, email, ruolo_aziendale) VALUES
(1, 'Mario', 'Rossi', 'mario.rossi@example.com', 'Responsabile Qualità'),
(1, 'Laura', 'Bianchi', 'laura.bianchi@example.com', 'Responsabile Sicurezza'),
(1, 'Giuseppe', 'Verdi', 'giuseppe.verdi@example.com', 'Direttore Tecnico')
ON DUPLICATE KEY UPDATE nome=nome;

-- 4. Verifica/crea indici per performance
CREATE INDEX IF NOT EXISTS idx_documenti_classificazione ON documenti(classificazione_id);
CREATE INDEX IF NOT EXISTS idx_documenti_azienda ON documenti(azienda_id);
CREATE INDEX IF NOT EXISTS idx_documenti_stato ON documenti(stato);
CREATE INDEX IF NOT EXISTS idx_classificazione_azienda ON classificazione(azienda_id);
CREATE INDEX IF NOT EXISTS idx_classificazione_parent ON classificazione(parent_id);

-- 5. Verifica/aggiorna tabella moduli_template
ALTER TABLE moduli_template
    ADD COLUMN IF NOT EXISTS contenuto LONGTEXT AFTER footer_content;

-- 6. Inserisci alcuni template di esempio se non esistono
INSERT INTO moduli_documento (azienda_id, nome, descrizione, tipo, attivo) VALUES
(1, 'Procedura Standard', 'Template per procedure aziendali', 'procedura', 1),
(1, 'Istruzione Operativa', 'Template per istruzioni operative', 'istruzione', 1),
(1, 'Modulo Registrazione', 'Template per moduli di registrazione', 'modulo', 1)
ON DUPLICATE KEY UPDATE nome=nome;

-- Inserisci template associati
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