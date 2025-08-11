-- Fix del trigger per permettere l'eliminazione selettiva dei log
-- Mantiene la protezione per i log marcati come non_eliminabile = 1
-- Data: 2025-08-10

DELIMITER //

-- Prima rimuoviamo il vecchio trigger che blocca tutto
DROP TRIGGER IF EXISTS prevent_log_delete//
DROP TRIGGER IF EXISTS prevent_log_attivita_delete//

-- Creiamo un nuovo trigger che permette l'eliminazione selettiva
CREATE TRIGGER prevent_protected_log_delete
BEFORE DELETE ON log_attivita
FOR EACH ROW
BEGIN
    -- Blocca solo l'eliminazione dei log protetti (non_eliminabile = 1)
    IF OLD.non_eliminabile = 1 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete protected log entries (audit compliance)';
    END IF;
    
    -- Blocca anche l'eliminazione dei log di tipo 'eliminazione_log' per tracciabilità
    IF OLD.azione = 'eliminazione_log' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete deletion log entries (audit trail)';
    END IF;
END//

DELIMITER ;

-- Assicuriamoci che tutti i log di eliminazione esistenti siano marcati come non eliminabili
UPDATE log_attivita 
SET non_eliminabile = 1 
WHERE azione = 'eliminazione_log' AND non_eliminabile != 1;

-- Aggiungiamo un indice per migliorare le performance delle query sui log protetti
CREATE INDEX IF NOT EXISTS idx_log_attivita_non_eliminabile ON log_attivita(non_eliminabile);
CREATE INDEX IF NOT EXISTS idx_log_attivita_azione ON log_attivita(azione);

-- Test del nuovo trigger
-- Questo dovrebbe funzionare (elimina un log normale non protetto)
-- DELETE FROM log_attivita WHERE id = -999999 AND non_eliminabile = 0;

-- Questo dovrebbe fallire (tenta di eliminare un log protetto)
-- DELETE FROM log_attivita WHERE non_eliminabile = 1 LIMIT 1;

DELIMITER ;