-- Aggiungi colonne mancanti alla tabella documenti
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS codice VARCHAR(50),
ADD COLUMN IF NOT EXISTS file_path VARCHAR(500),
ADD COLUMN IF NOT EXISTS cartella_id INT,
ADD COLUMN IF NOT EXISTS dimensione_file BIGINT DEFAULT 0,
ADD INDEX IF NOT EXISTS idx_codice (codice),
ADD INDEX IF NOT EXISTS idx_cartella (cartella_id),
ADD CONSTRAINT fk_documenti_cartella FOREIGN KEY (cartella_id) REFERENCES cartelle(id) ON DELETE SET NULL;