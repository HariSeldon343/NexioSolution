-- Fix for empty ruolo_azienda issue
-- This migration ensures no empty roles can be inserted

-- First, fix any existing empty roles
UPDATE utenti_aziende 
SET ruolo_azienda = 'referente' 
WHERE ruolo_azienda = '' OR ruolo_azienda IS NULL;

-- Add a constraint to prevent empty roles in the future
-- Note: Since ruolo_azienda is already an ENUM, empty strings should not be allowed
-- But we add this check for extra safety
ALTER TABLE utenti_aziende 
ADD CONSTRAINT check_ruolo_not_empty 
CHECK (ruolo_azienda IS NOT NULL AND ruolo_azienda != '');

-- Add an index for better performance on role queries
CREATE INDEX IF NOT EXISTS idx_utenti_aziende_ruolo ON utenti_aziende (azienda_id, ruolo_azienda, attivo);