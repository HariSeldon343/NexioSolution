-- Aggiunge campi ora_inizio e ora_fine alla tabella eventi
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS ora_inizio TIME DEFAULT NULL COMMENT 'Ora di inizio evento',
ADD COLUMN IF NOT EXISTS ora_fine TIME DEFAULT NULL COMMENT 'Ora di fine evento';

-- Aggiorna gli eventi esistenti estraendo l'ora dalla data_inizio e data_fine
UPDATE eventi 
SET ora_inizio = TIME(data_inizio),
    ora_fine = TIME(data_fine)
WHERE ora_inizio IS NULL OR ora_fine IS NULL;