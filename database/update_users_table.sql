-- Aggiornamento tabella utenti per gestione password e primo accesso
-- Esegui questo script nel database piattaforma_collaborativa

-- Aggiungi campi mancanti alla tabella utenti
ALTER TABLE utenti 
ADD COLUMN IF NOT EXISTS cognome VARCHAR(100) AFTER nome,
ADD COLUMN IF NOT EXISTS data_nascita DATE AFTER cognome,
ADD COLUMN IF NOT EXISTS primo_accesso BOOLEAN DEFAULT TRUE AFTER password,
ADD COLUMN IF NOT EXISTS password_scadenza DATE AFTER primo_accesso,
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) AFTER password_scadenza,
ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME AFTER password_reset_token,
ADD COLUMN IF NOT EXISTS created_by INT AFTER stato,
ADD COLUMN IF NOT EXISTS last_password_change DATETIME DEFAULT CURRENT_TIMESTAMP AFTER created_by;

-- Aggiorna password_scadenza per utenti esistenti (90 giorni da oggi)
UPDATE utenti 
SET password_scadenza = DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY)
WHERE password_scadenza IS NULL;

-- Aggiungi indice per migliorare le performance
CREATE INDEX IF NOT EXISTS idx_password_scadenza ON utenti(password_scadenza);
CREATE INDEX IF NOT EXISTS idx_primo_accesso ON utenti(primo_accesso);

-- Aggiungi foreign key per created_by
ALTER TABLE utenti
ADD CONSTRAINT fk_created_by 
FOREIGN KEY (created_by) REFERENCES utenti(id) 
ON DELETE SET NULL; 