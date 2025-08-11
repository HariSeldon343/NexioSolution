-- Fix per i record con id=0 nella tabella log_attivita
-- Assegna ID progressivi univoci
-- Data: 2025-08-10

-- Crea una tabella temporanea con i record da aggiornare
CREATE TEMPORARY TABLE temp_log_ids AS
SELECT 
    @row_num := @row_num + 1 AS new_id,
    utente_id,
    tipo,
    descrizione,
    ip_address,
    user_agent,
    data_azione,
    entita_tipo,
    entita_id,
    azione,
    dettagli,
    non_eliminabile,
    azienda_id
FROM log_attivita, (SELECT @row_num := 104) AS init
WHERE id = 0
ORDER BY data_azione;

-- Elimina i vecchi record con id=0
DELETE FROM log_attivita WHERE id = 0;

-- Inserisci i record con i nuovi ID
INSERT INTO log_attivita (id, utente_id, tipo, descrizione, ip_address, user_agent, data_azione, entita_tipo, entita_id, azione, dettagli, non_eliminabile, azienda_id)
SELECT new_id, utente_id, tipo, descrizione, ip_address, user_agent, data_azione, entita_tipo, entita_id, azione, dettagli, non_eliminabile, azienda_id
FROM temp_log_ids;

-- Pulisci
DROP TEMPORARY TABLE temp_log_ids;

-- Ora possiamo aggiungere la PRIMARY KEY
ALTER TABLE log_attivita ADD PRIMARY KEY (id);

-- E l'AUTO_INCREMENT
ALTER TABLE log_attivita MODIFY COLUMN id int(11) NOT NULL AUTO_INCREMENT;

-- Imposta AUTO_INCREMENT al valore corretto
SET @max_id = (SELECT MAX(id) FROM log_attivita);
SET @sql = CONCAT('ALTER TABLE log_attivita AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;