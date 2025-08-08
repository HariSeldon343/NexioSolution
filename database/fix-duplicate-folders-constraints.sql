-- Fix per prevenire cartelle duplicate
-- Aggiunge constraint UNIQUE e ottimizza gli indici

-- 1. Prima rimuovi eventuali constraint esistenti che potrebbero causare conflitti
ALTER TABLE cartelle DROP INDEX IF EXISTS idx_nome_parent_azienda;
ALTER TABLE cartelle DROP INDEX IF EXISTS uk_cartelle_nome_parent_azienda;

-- 2. Aggiungi constraint UNIQUE per prevenire duplicati
-- Questo impedirà la creazione di cartelle con stesso nome nella stessa posizione per la stessa azienda
ALTER TABLE cartelle 
ADD UNIQUE KEY uk_cartelle_nome_parent_azienda (nome, parent_id, azienda_id);

-- 3. Aggiungi indici per migliorare le performance delle query
-- Indice per ricerche per azienda
ALTER TABLE cartelle ADD INDEX IF NOT EXISTS idx_azienda_id (azienda_id);

-- Indice per ricerche per parent_id
ALTER TABLE cartelle ADD INDEX IF NOT EXISTS idx_parent_id (parent_id);

-- Indice per ricerche per percorso completo
ALTER TABLE cartelle ADD INDEX IF NOT EXISTS idx_percorso_completo (percorso_completo);

-- Indice composito per query comuni
ALTER TABLE cartelle ADD INDEX IF NOT EXISTS idx_azienda_parent (azienda_id, parent_id);

-- 4. Aggiungi constraint per integrità referenziale (se non esistono)
-- Foreign key per parent_id
ALTER TABLE cartelle 
ADD CONSTRAINT IF NOT EXISTS fk_cartelle_parent 
FOREIGN KEY (parent_id) REFERENCES cartelle(id) 
ON DELETE CASCADE;

-- Foreign key per azienda_id
ALTER TABLE cartelle 
ADD CONSTRAINT IF NOT EXISTS fk_cartelle_azienda 
FOREIGN KEY (azienda_id) REFERENCES aziende(id) 
ON DELETE CASCADE;

-- Foreign key per creato_da
ALTER TABLE cartelle 
ADD CONSTRAINT IF NOT EXISTS fk_cartelle_utente 
FOREIGN KEY (creato_da) REFERENCES utenti(id) 
ON DELETE SET NULL;

-- 5. Ottimizza la tabella documenti per riferimenti a cartelle
-- Indice per cartella_id
ALTER TABLE documenti ADD INDEX IF NOT EXISTS idx_cartella_id (cartella_id);

-- Foreign key per cartella_id
ALTER TABLE documenti 
ADD CONSTRAINT IF NOT EXISTS fk_documenti_cartella 
FOREIGN KEY (cartella_id) REFERENCES cartelle(id) 
ON DELETE SET NULL;

-- 6. Crea trigger per mantenere percorso_completo aggiornato
DELIMITER $$

DROP TRIGGER IF EXISTS before_cartelle_insert$$
CREATE TRIGGER before_cartelle_insert 
BEFORE INSERT ON cartelle
FOR EACH ROW
BEGIN
    DECLARE parent_path VARCHAR(1000);
    
    IF NEW.parent_id IS NOT NULL THEN
        SELECT percorso_completo INTO parent_path 
        FROM cartelle 
        WHERE id = NEW.parent_id;
        
        SET NEW.percorso_completo = CONCAT(parent_path, '/', NEW.nome);
    ELSE
        SET NEW.percorso_completo = NEW.nome;
    END IF;
END$$

DROP TRIGGER IF EXISTS before_cartelle_update$$
CREATE TRIGGER before_cartelle_update
BEFORE UPDATE ON cartelle
FOR EACH ROW
BEGIN
    DECLARE parent_path VARCHAR(1000);
    
    -- Se cambia il nome o il parent_id, aggiorna il percorso
    IF NEW.nome != OLD.nome OR 
       IFNULL(NEW.parent_id, 0) != IFNULL(OLD.parent_id, 0) THEN
        
        IF NEW.parent_id IS NOT NULL THEN
            SELECT percorso_completo INTO parent_path 
            FROM cartelle 
            WHERE id = NEW.parent_id;
            
            SET NEW.percorso_completo = CONCAT(parent_path, '/', NEW.nome);
        ELSE
            SET NEW.percorso_completo = NEW.nome;
        END IF;
    END IF;
END$$

DELIMITER ;

-- 7. Crea stored procedure per verificare integrità cartelle
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_verifica_integrita_cartelle$$
CREATE PROCEDURE sp_verifica_integrita_cartelle()
BEGIN
    -- Verifica duplicati
    SELECT 'Duplicati trovati:' as Controllo,
           COUNT(*) as Risultato
    FROM (
        SELECT nome, parent_id, azienda_id, COUNT(*) as cnt
        FROM cartelle
        GROUP BY nome, IFNULL(parent_id, 0), azienda_id
        HAVING COUNT(*) > 1
    ) as duplicates;
    
    -- Verifica percorsi non validi
    SELECT 'Percorsi non validi:' as Controllo,
           COUNT(*) as Risultato
    FROM cartelle
    WHERE percorso_completo IS NULL OR percorso_completo = '';
    
    -- Verifica riferimenti parent non validi
    SELECT 'Parent non validi:' as Controllo,
           COUNT(*) as Risultato
    FROM cartelle c1
    LEFT JOIN cartelle c2 ON c1.parent_id = c2.id
    WHERE c1.parent_id IS NOT NULL AND c2.id IS NULL;
    
    -- Verifica documenti orfani
    SELECT 'Documenti orfani:' as Controllo,
           COUNT(*) as Risultato
    FROM documenti d
    LEFT JOIN cartelle c ON d.cartella_id = c.id
    WHERE d.cartella_id IS NOT NULL AND c.id IS NULL;
END$$

DELIMITER ;

-- 8. Crea funzione per ottenere il percorso completo di una cartella
DELIMITER $$

DROP FUNCTION IF EXISTS fn_get_folder_path$$
CREATE FUNCTION fn_get_folder_path(folder_id INT) 
RETURNS VARCHAR(1000)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE path VARCHAR(1000) DEFAULT '';
    DECLARE current_name VARCHAR(200);
    DECLARE current_parent INT;
    DECLARE current_id INT;
    
    SET current_id = folder_id;
    
    WHILE current_id IS NOT NULL DO
        SELECT nome, parent_id INTO current_name, current_parent
        FROM cartelle
        WHERE id = current_id;
        
        IF path = '' THEN
            SET path = current_name;
        ELSE
            SET path = CONCAT(current_name, '/', path);
        END IF;
        
        SET current_id = current_parent;
    END WHILE;
    
    RETURN path;
END$$

DELIMITER ;

-- 9. Crea vista per monitorare lo stato delle cartelle
CREATE OR REPLACE VIEW v_cartelle_status AS
SELECT 
    c.id,
    c.nome,
    c.parent_id,
    c.percorso_completo,
    c.azienda_id,
    a.nome as azienda_nome,
    c.creato_da,
    u.username as creato_da_username,
    c.data_creazione,
    (SELECT COUNT(*) FROM documenti WHERE cartella_id = c.id) as documenti_count,
    (SELECT COUNT(*) FROM cartelle WHERE parent_id = c.id) as sottocartelle_count
FROM cartelle c
LEFT JOIN aziende a ON c.azienda_id = a.id
LEFT JOIN utenti u ON c.creato_da = u.id;

-- 10. Messaggio finale
SELECT 'Constraint e ottimizzazioni applicate con successo!' as Messaggio;