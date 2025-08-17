-- Add columns for ICS import functionality
-- These columns are needed to properly store imported ICS events

-- Add uid_import column to track imported events and prevent duplicates
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS uid_import VARCHAR(255) DEFAULT NULL 
COMMENT 'UID from imported ICS file to prevent duplicates'
AFTER tags;

-- Add index for faster lookups
ALTER TABLE eventi 
ADD INDEX IF NOT EXISTS idx_uid_import (uid_import);

-- Add tutto_il_giorno flag for all-day events
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS tutto_il_giorno TINYINT(1) DEFAULT 0 
COMMENT 'Flag for all-day events'
AFTER uid_import;

-- Add separate time fields for better time handling
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS ora_inizio TIME DEFAULT NULL 
COMMENT 'Start time separate from date'
AFTER tutto_il_giorno;

ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS ora_fine TIME DEFAULT NULL 
COMMENT 'End time separate from date'
AFTER ora_inizio;

-- Add note field for additional ICS data that doesn't map to existing fields
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL 
COMMENT 'Additional notes from ICS import'
AFTER tags;

-- Update existing events to populate time fields from datetime fields
UPDATE eventi 
SET ora_inizio = TIME(data_inizio),
    ora_fine = TIME(data_fine)
WHERE ora_inizio IS NULL AND ora_fine IS NULL 
  AND data_inizio IS NOT NULL;

-- Add composite index for duplicate checking
ALTER TABLE eventi 
ADD INDEX IF NOT EXISTS idx_duplicate_check (titolo, data_inizio, azienda_id);

-- Log the update
INSERT INTO log_attivita (
    utente_id, 
    azione, 
    descrizione, 
    tabella_interessata,
    record_id,
    data_azione
) VALUES (
    1,
    'database_update',
    'Added ICS import columns to eventi table',
    'eventi',
    0,
    NOW()
);