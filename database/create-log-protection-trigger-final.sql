-- Creazione del trigger di protezione per log_attivita
-- Data: 2025-08-10

USE nexiosol;

DELIMITER $$

DROP TRIGGER IF EXISTS prevent_protected_log_delete$$

CREATE TRIGGER prevent_protected_log_delete
BEFORE DELETE ON log_attivita
FOR EACH ROW
BEGIN
    -- Blocca l'eliminazione dei log marcati come non eliminabili
    IF OLD.non_eliminabile = 1 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete protected log entries (audit compliance)';
    END IF;
    
    -- Blocca l'eliminazione dei log di tipo eliminazione_log
    IF OLD.azione = 'eliminazione_log' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete deletion log entries (audit trail)';
    END IF;
END$$

DELIMITER ;