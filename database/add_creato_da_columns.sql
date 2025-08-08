-- Add missing creato_da and aggiornato_da columns to NexioSol database
-- This script safely adds columns only if they don't already exist

-- Helper procedure to add column if missing
DELIMITER $$

DROP PROCEDURE IF EXISTS AddColumnIfNotExists$$
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(255),
    IN columnName VARCHAR(255),
    IN columnDefinition VARCHAR(1000)
)
BEGIN
    DECLARE columnExists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO columnExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND COLUMN_NAME = columnName;
    
    IF columnExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        SELECT CONCAT('Added column ', tableName, '.', columnName) AS result;
    ELSE
        SELECT CONCAT('Column ', tableName, '.', columnName, ' already exists') AS result;
    END IF;
END$$

DELIMITER ;

-- Add creato_da columns
CALL AddColumnIfNotExists('documenti', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('cartelle', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('aziende', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('classificazione', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('classificazioni', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('moduli_documento', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('moduli_template', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('referenti', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('temi_azienda', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('templates', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('permessi_cartelle', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('permessi_cartelle_ruoli', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('user_permissions', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('eventi', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('documenti_versioni', 'creato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('template_documenti', 'creato_da', 'INT NULL DEFAULT NULL');

-- Add aggiornato_da columns
CALL AddColumnIfNotExists('documenti', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('cartelle', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('aziende', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('classificazione', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('classificazioni', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('moduli_documento', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('moduli_template', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('referenti', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('temi_azienda', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('templates', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('user_permissions', 'aggiornato_da', 'INT NULL DEFAULT NULL');
CALL AddColumnIfNotExists('template_documenti', 'aggiornato_da', 'INT NULL DEFAULT NULL');

-- Add foreign key constraints
DELIMITER $$

DROP PROCEDURE IF EXISTS AddForeignKeyIfNotExists$$
CREATE PROCEDURE AddForeignKeyIfNotExists(
    IN tableName VARCHAR(255),
    IN columnName VARCHAR(255),
    IN constraintName VARCHAR(255)
)
BEGIN
    DECLARE constraintExists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO constraintExists
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND CONSTRAINT_NAME = constraintName;
    
    IF constraintExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD CONSTRAINT `', constraintName, 
                         '` FOREIGN KEY (`', columnName, '`) REFERENCES `utenti`(`id`) ON DELETE SET NULL');
        
        -- Try to add FK, but continue if it fails (due to orphaned records)
        BEGIN
            DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
            BEGIN
                SELECT CONCAT('Warning: Could not add FK for ', tableName, '.', columnName, ' - orphaned records may exist') AS result;
            END;
            
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            SELECT CONCAT('Added FK constraint for ', tableName, '.', columnName) AS result;
        END;
    ELSE
        SELECT CONCAT('FK constraint for ', tableName, '.', columnName, ' already exists') AS result;
    END IF;
END$$

DELIMITER ;

-- Add foreign keys for creato_da
CALL AddForeignKeyIfNotExists('documenti', 'creato_da', 'fk_documenti_creato_da');
CALL AddForeignKeyIfNotExists('cartelle', 'creato_da', 'fk_cartelle_creato_da');
CALL AddForeignKeyIfNotExists('aziende', 'creato_da', 'fk_aziende_creato_da');
CALL AddForeignKeyIfNotExists('classificazione', 'creato_da', 'fk_classificazione_creato_da');
CALL AddForeignKeyIfNotExists('classificazioni', 'creato_da', 'fk_classificazioni_creato_da');
CALL AddForeignKeyIfNotExists('moduli_documento', 'creato_da', 'fk_moduli_documento_creato_da');
CALL AddForeignKeyIfNotExists('moduli_template', 'creato_da', 'fk_moduli_template_creato_da');
CALL AddForeignKeyIfNotExists('referenti', 'creato_da', 'fk_referenti_creato_da');
CALL AddForeignKeyIfNotExists('temi_azienda', 'creato_da', 'fk_temi_azienda_creato_da');
CALL AddForeignKeyIfNotExists('templates', 'creato_da', 'fk_templates_creato_da');
CALL AddForeignKeyIfNotExists('permessi_cartelle', 'creato_da', 'fk_permessi_cartelle_creato_da');
CALL AddForeignKeyIfNotExists('permessi_cartelle_ruoli', 'creato_da', 'fk_permessi_cartelle_ruoli_creato_da');
CALL AddForeignKeyIfNotExists('user_permissions', 'creato_da', 'fk_user_permissions_creato_da');
CALL AddForeignKeyIfNotExists('eventi', 'creato_da', 'fk_eventi_creato_da');
CALL AddForeignKeyIfNotExists('documenti_versioni', 'creato_da', 'fk_documenti_versioni_creato_da');
CALL AddForeignKeyIfNotExists('template_documenti', 'creato_da', 'fk_template_documenti_creato_da');

-- Add foreign keys for aggiornato_da
CALL AddForeignKeyIfNotExists('documenti', 'aggiornato_da', 'fk_documenti_aggiornato_da');
CALL AddForeignKeyIfNotExists('cartelle', 'aggiornato_da', 'fk_cartelle_aggiornato_da');
CALL AddForeignKeyIfNotExists('aziende', 'aggiornato_da', 'fk_aziende_aggiornato_da');
CALL AddForeignKeyIfNotExists('classificazione', 'aggiornato_da', 'fk_classificazione_aggiornato_da');
CALL AddForeignKeyIfNotExists('classificazioni', 'aggiornato_da', 'fk_classificazioni_aggiornato_da');
CALL AddForeignKeyIfNotExists('moduli_documento', 'aggiornato_da', 'fk_moduli_documento_aggiornato_da');
CALL AddForeignKeyIfNotExists('moduli_template', 'aggiornato_da', 'fk_moduli_template_aggiornato_da');
CALL AddForeignKeyIfNotExists('referenti', 'aggiornato_da', 'fk_referenti_aggiornato_da');
CALL AddForeignKeyIfNotExists('temi_azienda', 'aggiornato_da', 'fk_temi_azienda_aggiornato_da');
CALL AddForeignKeyIfNotExists('templates', 'aggiornato_da', 'fk_templates_aggiornato_da');
CALL AddForeignKeyIfNotExists('user_permissions', 'aggiornato_da', 'fk_user_permissions_aggiornato_da');
CALL AddForeignKeyIfNotExists('template_documenti', 'aggiornato_da', 'fk_template_documenti_aggiornato_da');

-- Clean up procedures
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
DROP PROCEDURE IF EXISTS AddForeignKeyIfNotExists;

-- Summary
SELECT 'Database update completed! Run check-missing-columns.php to verify.' AS result;