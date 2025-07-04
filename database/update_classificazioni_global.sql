-- Rende le classificazioni globali (valide per tutte le aziende)

-- 1. Backup delle classificazioni esistenti
CREATE TABLE IF NOT EXISTS classificazione_backup AS SELECT * FROM classificazione;

-- 2. Rimuovi la foreign key e l'indice
ALTER TABLE classificazione DROP FOREIGN KEY classificazione_ibfk_1;
ALTER TABLE classificazione DROP INDEX unique_codice_azienda;
ALTER TABLE classificazione DROP INDEX idx_azienda;

-- 3. Rimuovi la colonna azienda_id
ALTER TABLE classificazione DROP COLUMN azienda_id;

-- 4. Aggiungi un nuovo indice univoco solo sul codice
ALTER TABLE classificazione ADD UNIQUE KEY unique_codice (codice);

-- 5. Elimina duplicati mantenendo solo una versione per codice
DELETE c1 FROM classificazione c1
INNER JOIN classificazione c2 
WHERE c1.codice = c2.codice 
AND c1.id > c2.id;

-- 6. Inserisci classificazioni standard se non esistono
INSERT IGNORE INTO classificazione (codice, descrizione, parent_id, livello, attivo) VALUES
-- Livello 1
('1', 'Normative e Standard', NULL, 1, 1),
('2', 'Documentazione del Sistema di Gestione', NULL, 1, 1),
('3', 'Documenti di Origine Esterna', NULL, 1, 1);

-- Recupera gli ID del livello 1
SET @norm_id = (SELECT id FROM classificazione WHERE codice = '1');
SET @doc_id = (SELECT id FROM classificazione WHERE codice = '2');
SET @ext_id = (SELECT id FROM classificazione WHERE codice = '3');

-- Livello 2 - Normative e Standard
INSERT IGNORE INTO classificazione (codice, descrizione, parent_id, livello, attivo) VALUES
('1.1', 'ISO 9001 - Sistema di Gestione Qualità', @norm_id, 2, 1),
('1.2', 'ISO 14001 - Sistema di Gestione Ambientale', @norm_id, 2, 1),
('1.3', 'ISO 45001 - Sistema di Gestione Sicurezza', @norm_id, 2, 1),
('1.4', 'ISO 27001 - Sistema di Gestione Sicurezza Informazioni', @norm_id, 2, 1),
('1.5', 'Normative Nazionali/Europee', @norm_id, 2, 1),
('1.6', 'Regolamenti Settoriali', @norm_id, 2, 1),
('1.7', 'Aggiornamenti Normativi', @norm_id, 2, 1);

-- Livello 2 - Documentazione Sistema
INSERT IGNORE INTO classificazione (codice, descrizione, parent_id, livello, attivo) VALUES
('2.1', 'Manuale della Qualità/Ambiente/Sicurezza', @doc_id, 2, 1),
('2.2', 'Politiche Aziendali', @doc_id, 2, 1),
('2.3', 'Procedure Generali', @doc_id, 2, 1),
('2.4', 'Procedure Operative', @doc_id, 2, 1),
('2.5', 'Istruzioni di Lavoro', @doc_id, 2, 1),
('2.6', 'Moduli e Registrazioni', @doc_id, 2, 1);

-- Livello 2 - Documenti Origine Esterna
INSERT IGNORE INTO classificazione (codice, descrizione, parent_id, livello, attivo) VALUES
('3.1', 'Contratti con Fornitori', @ext_id, 2, 1),
('3.2', 'Certificazioni', @ext_id, 2, 1),
('3.3', 'Autorizzazioni', @ext_id, 2, 1),
('3.4', 'Comunicazioni Enti', @ext_id, 2, 1); 