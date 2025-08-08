-- Add missing columns for modern file manager

-- Add creato_da column to cartelle if not exists
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS creato_da INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add dimensione_file column to documenti if not exists
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS dimensione_file BIGINT DEFAULT 0;

-- Add foreign key for creato_da
ALTER TABLE cartelle
ADD CONSTRAINT fk_cartelle_creato_da 
FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_cartelle_azienda ON cartelle(azienda_id);
CREATE INDEX IF NOT EXISTS idx_cartelle_parent ON cartelle(parent_id);
CREATE INDEX IF NOT EXISTS idx_documenti_cartella ON documenti(cartella_id);
CREATE INDEX IF NOT EXISTS idx_documenti_azienda ON documenti(azienda_id);