-- Fix per le colonne mancanti nella tabella utenti per il recupero password
-- Eseguire questo script sul database NexioSol

-- Aggiungi colonna password_reset_token se non esiste
ALTER TABLE utenti 
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) DEFAULT NULL 
COMMENT 'Token per il reset della password';

-- Aggiungi colonna password_reset_expires se non esiste
ALTER TABLE utenti 
ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME DEFAULT NULL 
COMMENT 'Scadenza del token di reset password';

-- Aggiungi colonna last_password_change se non esiste
ALTER TABLE utenti 
ADD COLUMN IF NOT EXISTS last_password_change DATETIME DEFAULT NULL 
COMMENT 'Data ultimo cambio password';

-- Crea indice sul token per ricerche più veloci
CREATE INDEX IF NOT EXISTS idx_password_reset_token 
ON utenti (password_reset_token);

-- Verifica che le colonne siano state aggiunte
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'NexioSol' 
    AND TABLE_NAME = 'utenti'
    AND COLUMN_NAME IN ('password_reset_token', 'password_reset_expires', 'last_password_change')
ORDER BY ORDINAL_POSITION;