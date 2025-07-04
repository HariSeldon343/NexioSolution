-- Aggiorna la struttura della tabella documenti per supportare i template

-- Aggiungi colonna modulo_id se non esiste
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS modulo_id INT AFTER azienda_id,
ADD CONSTRAINT fk_documenti_modulo FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id);

-- Aggiungi colonna template_data per salvare i dati del template in JSON
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS template_data JSON AFTER contenuto;

-- Rimuovi colonna categoria_id se esiste (non più necessaria)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'documenti' 
AND COLUMN_NAME = 'categoria_id';

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE documenti DROP COLUMN categoria_id', 
    'SELECT "Column categoria_id does not exist"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rimuovi colonna file_path se esiste (gestione file spostata altrove)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'documenti' 
AND COLUMN_NAME = 'file_path';

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE documenti DROP COLUMN file_path', 
    'SELECT "Column file_path does not exist"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiungi indice per modulo_id
ALTER TABLE documenti ADD INDEX idx_modulo_id (modulo_id);

-- Aggiungi indice composito per azienda_id e modulo_id
ALTER TABLE documenti ADD INDEX idx_azienda_modulo (azienda_id, modulo_id); 