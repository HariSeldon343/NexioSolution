-- Migrazione: Aggiungere colonne mancanti alla tabella documenti
-- Data: 2025-08-17 13:52
-- Descrizione: Aggiunge user_id, updated_at e altre colonne necessarie per l'editor avanzato

START TRANSACTION;

-- Aggiungi colonna user_id se non esiste
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS user_id INT(11) DEFAULT NULL AFTER id,
ADD INDEX IF NOT EXISTS idx_user_id (user_id),
ADD CONSTRAINT IF NOT EXISTS fk_documenti_user 
    FOREIGN KEY (user_id) REFERENCES utenti(id) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

-- Aggiungi colonne temporali se non esistono
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER data_creazione,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Aggiungi colonna stato se non esiste
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS stato VARCHAR(50) DEFAULT 'bozza' AFTER tipo;

-- Aggiungi colonna titolo se non esiste (alias per nome)
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS titolo VARCHAR(500) DEFAULT NULL AFTER nome;

-- Migra dati esistenti: copia creato_da in user_id dove mancante
UPDATE documenti 
SET user_id = creato_da 
WHERE user_id IS NULL AND creato_da IS NOT NULL;

-- Migra dati esistenti: copia nome in titolo dove mancante
UPDATE documenti 
SET titolo = nome 
WHERE titolo IS NULL AND nome IS NOT NULL;

-- Migra dati esistenti: imposta created_at basandosi su data_creazione
UPDATE documenti 
SET created_at = data_creazione 
WHERE created_at IS NULL AND data_creazione IS NOT NULL;

-- Migra dati esistenti: imposta updated_at basandosi su data_modifica
UPDATE documenti 
SET updated_at = data_modifica 
WHERE updated_at IS NULL AND data_modifica IS NOT NULL;

-- Aggiungi indici per migliorare le performance
ALTER TABLE documenti 
ADD INDEX IF NOT EXISTS idx_stato (stato),
ADD INDEX IF NOT EXISTS idx_created_at (created_at),
ADD INDEX IF NOT EXISTS idx_updated_at (updated_at);

COMMIT;

-- Verifica finale
SELECT 
    'Colonne aggiunte con successo' AS risultato,
    COUNT(*) AS documenti_totali,
    SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS documenti_con_user_id,
    SUM(CASE WHEN titolo IS NOT NULL THEN 1 ELSE 0 END) AS documenti_con_titolo,
    SUM(CASE WHEN stato IS NOT NULL THEN 1 ELSE 0 END) AS documenti_con_stato
FROM documenti;