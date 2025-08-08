-- Migrazione per aggiungere colonne mancanti alla tabella cartelle
-- Data: 2025-08-06 11:15:24

-- Verifica e aggiunta colonne mancanti
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'creata_da';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cartelle ADD COLUMN creata_da INT NULL',
    'SELECT "Colonna creata_da già esistente"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'data_modifica';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cartelle ADD COLUMN data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT "Colonna data_modifica già esistente"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'descrizione';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cartelle ADD COLUMN descrizione TEXT NULL',
    'SELECT "Colonna descrizione già esistente"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'icona';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cartelle ADD COLUMN icona VARCHAR(50) NULL',
    'SELECT "Colonna icona già esistente"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'ordine_visualizzazione';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cartelle ADD COLUMN ordine_visualizzazione INT DEFAULT 0',
    'SELECT "Colonna ordine_visualizzazione già esistente"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'visibile';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cartelle ADD COLUMN visibile TINYINT(1) DEFAULT 1',
    'SELECT "Colonna visibile già esistente"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aggiunta indici per ottimizzazione
CREATE INDEX IF NOT EXISTS idx_cartelle_azienda_id ON cartelle(azienda_id);
CREATE INDEX IF NOT EXISTS idx_cartelle_parent_id ON cartelle(parent_id);
CREATE INDEX IF NOT EXISTS idx_cartelle_creata_da ON cartelle(creata_da);
CREATE INDEX IF NOT EXISTS idx_cartelle_data_modifica ON cartelle(data_modifica);

-- Aggiunta foreign keys (se le tabelle esistono)
SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND CONSTRAINT_NAME = 'fk_cartelle_azienda';

SET @sql = IF(@fk_exists = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aziende') > 0,
    'ALTER TABLE cartelle ADD CONSTRAINT fk_cartelle_azienda FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE',
    'SELECT "FK cartelle_azienda già esistente o tabella aziende non trovata"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND CONSTRAINT_NAME = 'fk_cartelle_utente';

SET @sql = IF(@fk_exists = 0 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utenti') > 0,
    'ALTER TABLE cartelle ADD CONSTRAINT fk_cartelle_utente FOREIGN KEY (creata_da) REFERENCES utenti(id) ON DELETE SET NULL',
    'SELECT "FK cartelle_utente già esistente o tabella utenti non trovata"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

