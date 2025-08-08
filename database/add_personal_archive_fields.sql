-- Aggiunge supporto per archivi personali e aziendali
-- Eseguire questo script per aggiornare la struttura del database

-- 1. Aggiungi campi alla tabella cartelle
ALTER TABLE cartelle 
ADD COLUMN tipo_archivio ENUM('personal', 'company') NOT NULL DEFAULT 'company' AFTER azienda_id,
ADD COLUMN proprietario_id INT NULL AFTER tipo_archivio,
ADD INDEX idx_tipo_archivio (tipo_archivio),
ADD INDEX idx_proprietario (proprietario_id);

-- 2. Aggiungi constraint per il proprietario (può essere utente o azienda)
-- Non aggiungiamo foreign key perché proprietario_id può riferirsi a utenti o aziende

-- 3. Aggiungi campi anche alla tabella documenti per coerenza
ALTER TABLE documenti 
ADD COLUMN tipo_archivio ENUM('personal', 'company') NOT NULL DEFAULT 'company' AFTER azienda_id,
ADD COLUMN proprietario_id INT NULL AFTER tipo_archivio,
ADD INDEX idx_doc_tipo_archivio (tipo_archivio),
ADD INDEX idx_doc_proprietario (proprietario_id);

-- 4. Aggiorna i record esistenti per impostare correttamente il proprietario
-- Per le cartelle aziendali esistenti
UPDATE cartelle 
SET proprietario_id = azienda_id 
WHERE tipo_archivio = 'company' AND proprietario_id IS NULL;

-- Per i documenti aziendali esistenti  
UPDATE documenti 
SET proprietario_id = azienda_id 
WHERE tipo_archivio = 'company' AND proprietario_id IS NULL;

-- 5. Crea vista per facilitare le query sugli archivi personali
CREATE OR REPLACE VIEW vista_archivi_personali AS
SELECT 
    c.*,
    u.nome as proprietario_nome,
    u.cognome as proprietario_cognome,
    u.email as proprietario_email
FROM cartelle c
JOIN utenti u ON c.proprietario_id = u.id
WHERE c.tipo_archivio = 'personal';

-- 6. Crea vista per facilitare le query sugli archivi aziendali
CREATE OR REPLACE VIEW vista_archivi_aziendali AS
SELECT 
    c.*,
    a.nome as azienda_nome,
    a.codice as azienda_codice
FROM cartelle c
JOIN aziende a ON c.proprietario_id = a.id
WHERE c.tipo_archivio = 'company';

-- 7. Aggiungi trigger per validare i dati
DELIMITER $$

CREATE TRIGGER before_cartelle_insert
BEFORE INSERT ON cartelle
FOR EACH ROW
BEGIN
    -- Per archivi personali, azienda_id deve essere NULL
    IF NEW.tipo_archivio = 'personal' THEN
        SET NEW.azienda_id = NULL;
    END IF;
    
    -- Per archivi aziendali, proprietario_id deve corrispondere ad azienda_id
    IF NEW.tipo_archivio = 'company' AND NEW.azienda_id IS NOT NULL THEN
        SET NEW.proprietario_id = NEW.azienda_id;
    END IF;
END$$

CREATE TRIGGER before_cartelle_update
BEFORE UPDATE ON cartelle
FOR EACH ROW
BEGIN
    -- Per archivi personali, azienda_id deve essere NULL
    IF NEW.tipo_archivio = 'personal' THEN
        SET NEW.azienda_id = NULL;
    END IF;
    
    -- Per archivi aziendali, proprietario_id deve corrispondere ad azienda_id
    IF NEW.tipo_archivio = 'company' AND NEW.azienda_id IS NOT NULL THEN
        SET NEW.proprietario_id = NEW.azienda_id;
    END IF;
END$$

DELIMITER ;

-- 8. Crea indici per ottimizzare le query più comuni
CREATE INDEX idx_personal_archive ON cartelle(proprietario_id, tipo_archivio) WHERE tipo_archivio = 'personal';
CREATE INDEX idx_company_archive ON cartelle(azienda_id, tipo_archivio) WHERE tipo_archivio = 'company';