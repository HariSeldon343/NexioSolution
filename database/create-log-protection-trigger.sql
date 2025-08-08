-- Create trigger to protect log_attivita table
-- This trigger prevents any deletion from the log_attivita table for audit compliance

DELIMITER //

DROP TRIGGER IF EXISTS prevent_log_attivita_delete//

CREATE TRIGGER prevent_log_attivita_delete
BEFORE DELETE ON log_attivita
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Deletion from log_attivita is not allowed for audit compliance';
END//

DELIMITER ;

-- Test the trigger is working
-- This should fail with an error
-- DELETE FROM log_attivita WHERE id = -999999;