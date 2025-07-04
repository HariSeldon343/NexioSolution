-- Aggiornamento tabella documenti_frontespizio

-- Rinomina colonna configurazione in contenuto_json se esiste
SET @dbname = DATABASE();
SET @tablename = 'documenti_frontespizio';
SET @columnname = 'configurazione';
SET @newcolumnname = 'contenuto_json';

SET @query = CONCAT('SELECT COUNT(*) INTO @colexists FROM information_schema.columns WHERE table_schema = \'', 
                    @dbname, '\' AND table_name = \'', @tablename, '\' AND column_name = \'', @columnname, '\'');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @query = IF(@colexists > 0, 
                CONCAT('ALTER TABLE ', @tablename, ' CHANGE ', @columnname, ' ', @newcolumnname, ' JSON NOT NULL'), 
                'SELECT \'Column configurazione does not exist\' AS message');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiungi colonna ha_frontespizio a documenti se non esiste
ALTER TABLE documenti
ADD COLUMN IF NOT EXISTS ha_frontespizio BOOLEAN DEFAULT FALSE;

-- Aggiorna flag ha_frontespizio per documenti esistenti
UPDATE documenti d
SET ha_frontespizio = 1
WHERE EXISTS (SELECT 1 FROM documenti_frontespizio df WHERE df.documento_id = d.id);

SELECT 'Tabella documenti_frontespizio aggiornata con successo!' AS messaggio; 