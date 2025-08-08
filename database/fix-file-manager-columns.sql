-- Fix per problemi del file manager
-- Assicura che le tabelle cartelle e documenti abbiano le colonne corrette e i vincoli giusti

-- 1. Assicurarsi che la colonna azienda_id nella tabella cartelle possa essere NULL per i super_admin
ALTER TABLE cartelle MODIFY COLUMN azienda_id INT NULL;

-- 2. Assicurarsi che la tabella documenti abbia tutte le colonne necessarie per i file
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) AFTER contenuto_html,
    ADD COLUMN IF NOT EXISTS dimensione_file BIGINT AFTER file_path,
    ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) AFTER dimensione_file,
    ADD COLUMN IF NOT EXISTS cartella_id INT AFTER azienda_id;

-- 3. Aggiungere indici per migliorare le performance
CREATE INDEX IF NOT EXISTS idx_cartelle_azienda_parent ON cartelle(azienda_id, parent_id);
CREATE INDEX IF NOT EXISTS idx_documenti_cartella ON documenti(cartella_id);
CREATE INDEX IF NOT EXISTS idx_documenti_azienda_cartella ON documenti(azienda_id, cartella_id);

-- 4. Aggiungere vincoli di foreign key se non esistono già
-- Per cartelle
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE cartelle 
    DROP FOREIGN KEY IF EXISTS fk_cartelle_azienda,
    DROP FOREIGN KEY IF EXISTS fk_cartelle_parent;
    
ALTER TABLE cartelle 
    ADD CONSTRAINT fk_cartelle_azienda 
        FOREIGN KEY (azienda_id) REFERENCES aziende(id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fk_cartelle_parent 
        FOREIGN KEY (parent_id) REFERENCES cartelle(id) 
        ON DELETE CASCADE ON UPDATE CASCADE;

-- Per documenti 
ALTER TABLE documenti
    DROP FOREIGN KEY IF EXISTS fk_documenti_cartella;
        
ALTER TABLE documenti
    ADD CONSTRAINT fk_documenti_cartella 
        FOREIGN KEY (cartella_id) REFERENCES cartelle(id) 
        ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- 5. Assicurarsi che data_modifica sia presente
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER data_aggiornamento;

-- Update existing records to set data_modifica = data_aggiornamento where data_modifica is NULL
UPDATE documenti SET data_modifica = data_aggiornamento WHERE data_modifica IS NULL AND data_aggiornamento IS NOT NULL;
UPDATE documenti SET data_modifica = data_creazione WHERE data_modifica IS NULL AND data_creazione IS NOT NULL;

-- 6. Correggere eventuali problemi di compatibilità con le colonne file
-- Se esistono sia file_size che dimensione_file, unificare i dati
UPDATE documenti SET dimensione_file = file_size WHERE dimensione_file IS NULL AND file_size IS NOT NULL;
UPDATE documenti SET mime_type = file_type WHERE mime_type IS NULL AND file_type IS NOT NULL;