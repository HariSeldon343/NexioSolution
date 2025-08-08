-- Aggiungi la colonna responsabile_id alla tabella aziende se non esiste già

-- Verifica se la colonna esiste e la aggiunge solo se necessario
SET @dbname = DATABASE();
SET @tablename = 'aziende';
SET @columnname = 'responsabile_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column already exists.' AS msg;",
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT DEFAULT NULL, ADD FOREIGN KEY (', @columnname, ') REFERENCES utenti(id) ON DELETE SET NULL;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;