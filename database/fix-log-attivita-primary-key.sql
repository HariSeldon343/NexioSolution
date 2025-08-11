-- Fix della struttura della tabella log_attivita
-- Aggiunge PRIMARY KEY e AUTO_INCREMENT alla colonna id
-- Data: 2025-08-10

-- Prima rimuoviamo eventuali record con id=0 o NULL
DELETE FROM log_attivita WHERE id = 0 OR id IS NULL;

-- Aggiungiamo la PRIMARY KEY e AUTO_INCREMENT
ALTER TABLE log_attivita 
MODIFY COLUMN id int(11) NOT NULL AUTO_INCREMENT,
ADD PRIMARY KEY (id);

-- Se la modifica sopra fallisce, proviamo in due passaggi
-- ALTER TABLE log_attivita ADD PRIMARY KEY (id);
-- ALTER TABLE log_attivita MODIFY COLUMN id int(11) NOT NULL AUTO_INCREMENT;

-- Verifica che l'AUTO_INCREMENT sia impostato correttamente
-- Trova il massimo ID esistente e imposta AUTO_INCREMENT al valore successivo
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM log_attivita);
SET @sql = CONCAT('ALTER TABLE log_attivita AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiungiamo anche gli indici mancanti se non esistono
CREATE INDEX IF NOT EXISTS idx_log_attivita_non_eliminabile ON log_attivita(non_eliminabile);
CREATE INDEX IF NOT EXISTS idx_log_attivita_azione ON log_attivita(azione);
CREATE INDEX IF NOT EXISTS idx_log_attivita_azienda ON log_attivita(azienda_id);