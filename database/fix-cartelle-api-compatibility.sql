-- =====================================================
-- MIGRAZIONE: Fix compatibilità API per tabella cartelle
-- =====================================================
-- Data: 2025-08-06
-- Risolve: Column not found: 1054 Unknown column 'c1.data_modifica' in 'field list'
-- 
-- Questo script aggiunge le colonne mancanti richieste dall'API files-api.php
-- e garantisce la compatibilità con le query esistenti
-- =====================================================

-- Verifica che la tabella cartelle esista
SET @table_exists = 0;
SELECT COUNT(*) INTO @table_exists 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle';

-- Procedi solo se la tabella esiste
SELECT IF(@table_exists = 0, 'ERRORE: La tabella cartelle non esiste!', 'Tabella cartelle trovata, procedo con le modifiche...') as status;

-- =====================================================
-- 1. AGGIUNTA COLONNA data_modifica (RICHIESTA DALL'API)
-- =====================================================
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'data_modifica';

SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'ALTER TABLE cartelle ADD COLUMN data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT "Colonna data_modifica già esistente o tabella non trovata" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sincronizza con data_aggiornamento esistente
SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'UPDATE cartelle SET data_modifica = data_aggiornamento WHERE data_aggiornamento IS NOT NULL',
    'SELECT "Sincronizzazione data_modifica non necessaria" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. AGGIUNTA COLONNA creata_da (RICHIESTA DALL'API)
-- =====================================================
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'creata_da';

SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'ALTER TABLE cartelle ADD COLUMN creata_da INT NULL',
    'SELECT "Colonna creata_da già esistente o tabella non trovata" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sincronizza con creato_da esistente
SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'UPDATE cartelle SET creata_da = creato_da WHERE creato_da IS NOT NULL',
    'SELECT "Sincronizzazione creata_da non necessaria" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. AGGIUNTA COLONNE AGGIUNTIVE PER COMPLETEZZA API
-- =====================================================

-- Colonna descrizione
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'descrizione';

SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'ALTER TABLE cartelle ADD COLUMN descrizione TEXT NULL',
    'SELECT "Colonna descrizione già esistente" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Colonna icona
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'icona';

SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'ALTER TABLE cartelle ADD COLUMN icona VARCHAR(50) NULL',
    'SELECT "Colonna icona già esistente" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Colonna ordine_visualizzazione
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'ordine_visualizzazione';

SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'ALTER TABLE cartelle ADD COLUMN ordine_visualizzazione INT DEFAULT 0',
    'SELECT "Colonna ordine_visualizzazione già esistente" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Colonna visibile
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartelle' AND COLUMN_NAME = 'visibile';

SET @sql = IF(@col_exists = 0 AND @table_exists = 1,
    'ALTER TABLE cartelle ADD COLUMN visibile TINYINT(1) DEFAULT 1',
    'SELECT "Colonna visibile già esistente" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. OTTIMIZZAZIONE INDICI PER PERFORMANCE
-- =====================================================

-- Indice su data_modifica per ordinamenti e filtri temporali
CREATE INDEX IF NOT EXISTS idx_cartelle_data_modifica ON cartelle(data_modifica);

-- Indice su creata_da per JOIN con utenti
CREATE INDEX IF NOT EXISTS idx_cartelle_creata_da ON cartelle(creata_da);

-- Indice composto per query API comuni
CREATE INDEX IF NOT EXISTS idx_cartelle_azienda_parent ON cartelle(azienda_id, parent_id);

-- Indice per filtri visibilità e ordinamento
CREATE INDEX IF NOT EXISTS idx_cartelle_visibile_ordine ON cartelle(visibile, ordine_visualizzazione);

-- =====================================================
-- 5. TRIGGER PER SINCRONIZZAZIONE AUTOMATICA TIMESTAMP
-- =====================================================

-- Rimuovi trigger esistente se presente
DROP TRIGGER IF EXISTS sync_cartelle_timestamps;

-- Crea trigger per mantenere sincronizzate le colonne timestamp
DELIMITER $$
CREATE TRIGGER sync_cartelle_timestamps 
    BEFORE UPDATE ON cartelle 
    FOR EACH ROW 
BEGIN 
    SET NEW.data_modifica = CURRENT_TIMESTAMP;
    SET NEW.data_aggiornamento = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- =====================================================
-- 6. VERIFICA FINALE E STATISTICHE
-- =====================================================

-- Verifica che tutte le colonne critiche esistano
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cartelle' 
    AND COLUMN_NAME IN ('data_modifica', 'creata_da', 'descrizione', 'icona', 'ordine_visualizzazione', 'visibile')
ORDER BY COLUMN_NAME;

-- Statistiche sulla tabella
SELECT 
    'Totale record' as statistica,
    COUNT(*) as valore
FROM cartelle
WHERE @table_exists = 1

UNION ALL

SELECT 
    'Record con data_modifica valida' as statistica,
    COUNT(*) as valore
FROM cartelle
WHERE data_modifica IS NOT NULL AND @table_exists = 1

UNION ALL

SELECT 
    'Record visibili' as statistica,
    COUNT(*) as valore
FROM cartelle
WHERE visibile = 1 AND @table_exists = 1;

-- =====================================================
-- 7. MESSAGGIO DI COMPLETAMENTO
-- =====================================================
SELECT 'MIGRAZIONE COMPLETATA: La tabella cartelle è ora compatibile con le API. L\'errore "Column not found: data_modifica" è stato risolto.' as messaggio_finale;