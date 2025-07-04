-- Script di ottimizzazione performance database Nexio
-- =============================================

-- 1. Ottimizzazione tabella utenti
-- ---------------------------------

-- Aggiungi indici per ricerche veloci
ALTER TABLE utenti 
ADD INDEX idx_email (email),
ADD INDEX idx_attivo (attivo),
ADD INDEX idx_ruolo (ruolo),
ADD INDEX idx_data_creazione (data_creazione DESC);

-- Indice composito per login
ALTER TABLE utenti 
ADD INDEX idx_login (email, attivo);

-- 2. Ottimizzazione tabella log_attivita
-- --------------------------------------

-- Indice per query recenti
ALTER TABLE log_attivita 
ADD INDEX idx_data_azione (data_azione DESC),
ADD INDEX idx_utente_id (utente_id),
ADD INDEX idx_tipo (tipo);

-- Indice composito per report
ALTER TABLE log_attivita 
ADD INDEX idx_report (utente_id, tipo, data_azione);

-- 3. Ottimizzazione tabella documenti
-- -----------------------------------

ALTER TABLE documenti 
ADD INDEX idx_stato (stato),
ADD INDEX idx_data_creazione (data_creazione DESC),
ADD INDEX idx_utente_creazione (utente_creazione);

-- 4. Ottimizzazione tabella eventi
-- --------------------------------

ALTER TABLE eventi 
ADD INDEX idx_data_inizio (data_inizio),
ADD INDEX idx_data_fine (data_fine),
ADD INDEX idx_stato (stato);

-- Indice per calendario
ALTER TABLE eventi 
ADD INDEX idx_calendario (data_inizio, data_fine, stato);

-- 5. Ottimizzazione tabella referenti
-- -----------------------------------

ALTER TABLE referenti 
ADD INDEX idx_stato (stato),
ADD INDEX idx_azienda (azienda),
ADD INDEX idx_email (email);

-- 6. Pulizia e ottimizzazione tabelle
-- -----------------------------------

-- Ottimizza tutte le tabelle
OPTIMIZE TABLE utenti;
OPTIMIZE TABLE log_attivita;
OPTIMIZE TABLE documenti;
OPTIMIZE TABLE eventi;
OPTIMIZE TABLE referenti;
OPTIMIZE TABLE aziende;

-- 7. Configurazioni MySQL per performance
-- ---------------------------------------

-- Aumenta buffer pool (esegui come root MySQL)
-- SET GLOBAL innodb_buffer_pool_size = 256M;

-- Abilita query cache
-- SET GLOBAL query_cache_type = ON;
-- SET GLOBAL query_cache_size = 64M;

-- 8. Creazione viste materializzate per report
-- --------------------------------------------

-- Vista per conteggio utenti attivi
CREATE OR REPLACE VIEW v_utenti_attivi AS
SELECT 
    COUNT(*) as totale,
    COUNT(CASE WHEN ruolo = 'super_admin' THEN 1 END) as super_admin,
    COUNT(CASE WHEN ruolo = 'utente' THEN 1 END) as utenti_normali
FROM utenti 
WHERE attivo = 1;

-- Vista per statistiche documenti
CREATE OR REPLACE VIEW v_documenti_stats AS
SELECT 
    COUNT(*) as totale,
    COUNT(CASE WHEN stato = 'pubblicato' THEN 1 END) as pubblicati,
    COUNT(CASE WHEN stato = 'bozza' THEN 1 END) as bozze,
    COUNT(CASE WHEN DATE(data_creazione) = CURDATE() THEN 1 END) as oggi
FROM documenti;

-- 9. Procedura stored per pulizia log vecchi
-- ------------------------------------------

DELIMITER $$

CREATE PROCEDURE sp_clean_old_logs(IN days_to_keep INT)
BEGIN
    -- Elimina log più vecchi di X giorni
    DELETE FROM log_attivita 
    WHERE data_azione < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Ottimizza tabella dopo eliminazione
    OPTIMIZE TABLE log_attivita;
END$$

DELIMITER ;

-- Esegui pulizia log più vecchi di 90 giorni
-- CALL sp_clean_old_logs(90);

-- 10. Trigger per cache invalidation
-- ----------------------------------

DELIMITER $$

CREATE TRIGGER tr_utenti_cache_invalidate
AFTER INSERT ON utenti
FOR EACH ROW
BEGIN
    -- Aggiorna timestamp cache
    UPDATE sistema_config 
    SET valore = UNIX_TIMESTAMP() 
    WHERE chiave = 'cache_utenti_timestamp';
END$$

CREATE TRIGGER tr_utenti_cache_invalidate_update
AFTER UPDATE ON utenti
FOR EACH ROW
BEGIN
    -- Aggiorna timestamp cache
    UPDATE sistema_config 
    SET valore = UNIX_TIMESTAMP() 
    WHERE chiave = 'cache_utenti_timestamp';
END$$

DELIMITER ;

-- 11. Tabella per configurazione sistema
-- --------------------------------------

CREATE TABLE IF NOT EXISTS sistema_config (
    chiave VARCHAR(100) PRIMARY KEY,
    valore TEXT,
    descrizione TEXT,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserisci configurazioni di cache
INSERT INTO sistema_config (chiave, valore, descrizione) VALUES
('cache_utenti_timestamp', UNIX_TIMESTAMP(), 'Timestamp ultima modifica utenti'),
('cache_documenti_timestamp', UNIX_TIMESTAMP(), 'Timestamp ultima modifica documenti')
ON DUPLICATE KEY UPDATE valore = VALUES(valore);

-- Fine script ottimizzazione 