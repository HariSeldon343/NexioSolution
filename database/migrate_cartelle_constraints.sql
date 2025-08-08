-- Script di migrazione per sistemare i constraint della tabella cartelle
-- Esegui questo script se hai già la tabella cartelle con constraint problematici

-- Disabilita temporaneamente i foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Rimuovi i vecchi constraint se esistono
-- Ignora gli errori se i constraint non esistono
SET @sql = (SELECT CONCAT('ALTER TABLE cartelle DROP FOREIGN KEY ', CONSTRAINT_NAME)
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cartelle'
            AND COLUMN_NAME = 'creato_da'
            AND REFERENCED_TABLE_NAME = 'utenti'
            LIMIT 1);
            
PREPARE stmt FROM IFNULL(@sql, 'SELECT 1');
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT CONCAT('ALTER TABLE cartelle DROP FOREIGN KEY ', CONSTRAINT_NAME)
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'cartelle'
            AND COLUMN_NAME = 'aggiornato_da'
            AND REFERENCED_TABLE_NAME = 'utenti'
            LIMIT 1);
            
PREPARE stmt FROM IFNULL(@sql, 'SELECT 1');
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Modifica le colonne per permettere NULL
ALTER TABLE cartelle 
MODIFY COLUMN creato_da int(11) DEFAULT NULL,
MODIFY COLUMN aggiornato_da int(11) DEFAULT NULL;

-- 3. Crea un utente di sistema se non esiste nessun utente
SET @user_count = (SELECT COUNT(*) FROM utenti);
SET @system_user_id = NULL;

-- Se non ci sono utenti, crea un utente di sistema
IF @user_count = 0 THEN
    INSERT INTO utenti (username, password, email, nome, cognome, ruolo, attivo, data_registrazione)
    VALUES ('system', '$2y$10$DUMMY_HASH_DO_NOT_USE_FOR_LOGIN', 'system@nexiosolution.it', 
            'Sistema', 'Nexio', 'super_admin', 1, NOW());
    SET @system_user_id = LAST_INSERT_ID();
ELSE
    -- Altrimenti usa il primo admin/super_admin disponibile
    SET @system_user_id = (SELECT id FROM utenti 
                          WHERE ruolo IN ('super_admin', 'admin') 
                          AND attivo = 1 
                          ORDER BY id 
                          LIMIT 1);
    
    -- Se non ci sono admin, usa il primo utente
    IF @system_user_id IS NULL THEN
        SET @system_user_id = (SELECT id FROM utenti ORDER BY id LIMIT 1);
    END IF;
END IF;

-- 4. Aggiorna eventuali record con creato_da non valido
UPDATE cartelle 
SET creato_da = @system_user_id 
WHERE creato_da IS NOT NULL 
AND creato_da NOT IN (SELECT id FROM utenti);

UPDATE cartelle 
SET aggiornato_da = @system_user_id 
WHERE aggiornato_da IS NOT NULL 
AND aggiornato_da NOT IN (SELECT id FROM utenti);

-- 5. Ricrea i constraint con ON DELETE SET NULL
ALTER TABLE cartelle
ADD CONSTRAINT fk_cartelle_creato_da 
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_cartelle_aggiornato_da 
    FOREIGN KEY (aggiornato_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- Riabilita i foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verifica finale
SELECT 
    'Migration completed successfully!' as message,
    (SELECT COUNT(*) FROM cartelle) as total_folders,
    (SELECT COUNT(*) FROM cartelle WHERE creato_da IS NULL) as folders_without_creator,
    (SELECT COUNT(DISTINCT creato_da) FROM cartelle WHERE creato_da IS NOT NULL) as unique_creators;