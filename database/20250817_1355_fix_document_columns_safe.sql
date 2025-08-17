-- Migrazione sicura: Aggiungi colonne mancanti alla tabella documenti
-- Data: 2025-08-17 13:55
-- Descrizione: Aggiunge solo le colonne che effettivamente mancano

START TRANSACTION;

-- Aggiungi user_id solo se non esiste
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'nexiosol' 
  AND table_name = 'documenti' 
  AND column_name = 'user_id';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE documenti ADD COLUMN user_id INT(11) DEFAULT NULL AFTER id',
    'SELECT "Column user_id already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiungi indice su user_id se la colonna è stata aggiunta
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE documenti ADD INDEX idx_user_id (user_id)',
    'SELECT "Index on user_id skipped" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiungi created_at solo se non esiste
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'nexiosol' 
  AND table_name = 'documenti' 
  AND column_name = 'created_at';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE documenti ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT "Column created_at already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiungi updated_at solo se non esiste
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'nexiosol' 
  AND table_name = 'documenti' 
  AND column_name = 'updated_at';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE documenti ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT "Column updated_at already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migra dati esistenti solo se necessario
UPDATE documenti 
SET user_id = creato_da 
WHERE user_id IS NULL AND creato_da IS NOT NULL;

-- Se created_at è stata aggiunta, popola con data_creazione
UPDATE documenti 
SET created_at = data_creazione 
WHERE created_at = '0000-00-00 00:00:00' AND data_creazione IS NOT NULL;

-- Se updated_at è stata aggiunta, popola con data_modifica
UPDATE documenti 
SET updated_at = data_modifica 
WHERE updated_at = '0000-00-00 00:00:00' AND data_modifica IS NOT NULL;

COMMIT;

-- Report finale
SELECT 
    'Migrazione completata' AS risultato,
    COUNT(*) AS documenti_totali,
    SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS con_user_id,
    SUM(CASE WHEN titolo IS NOT NULL THEN 1 ELSE 0 END) AS con_titolo
FROM documenti;