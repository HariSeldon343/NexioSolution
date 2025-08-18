-- Fix OnlyOffice Database Column Issues
-- Generated: 2025-08-18

-- 1. Add missing columns to documenti table
-- Add percorso_file as alias for file_path (for backward compatibility)
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS percorso_file VARCHAR(500) 
GENERATED ALWAYS AS (file_path) VIRTUAL;

-- Add data_caricamento as alias for data_creazione
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS data_caricamento TIMESTAMP 
GENERATED ALWAYS AS (data_creazione) VIRTUAL;

-- 2. Add role column to utenti table as alias for ruolo
ALTER TABLE utenti 
ADD COLUMN IF NOT EXISTS role VARCHAR(50) 
GENERATED ALWAYS AS (ruolo) VIRTUAL;

-- 3. Create views for backward compatibility (alternative approach)
-- This view provides the expected column names for OnlyOffice integration
CREATE OR REPLACE VIEW v_documenti_onlyoffice AS
SELECT 
    d.*,
    d.file_path AS percorso_file,
    d.data_creazione AS data_caricamento,
    a.nome AS nome_azienda,
    c.nome AS cartella_nome
FROM documenti d
LEFT JOIN aziende a ON d.azienda_id = a.id
LEFT JOIN cartelle c ON d.cartella_id = c.id;

-- View for users with role column
CREATE OR REPLACE VIEW v_utenti_onlyoffice AS
SELECT 
    u.*,
    u.ruolo AS role
FROM utenti u;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_documenti_file_path ON documenti(file_path);
CREATE INDEX IF NOT EXISTS idx_documenti_data_creazione ON documenti(data_creazione);
CREATE INDEX IF NOT EXISTS idx_utenti_ruolo ON utenti(ruolo);

-- Verify the changes
SELECT 'Columns added successfully' AS status;