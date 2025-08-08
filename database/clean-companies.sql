-- Clean Companies SQL Script
-- Mantiene solo MedTec, Romolo Hospital e Sud Marmi
-- Elimina tutte le altre aziende e i dati correlati

SET FOREIGN_KEY_CHECKS = 0;

-- Backup delle aziende da mantenere (per sicurezza)
CREATE TEMPORARY TABLE aziende_da_mantenere AS
SELECT id FROM aziende 
WHERE nome IN ('MedTec', 'Romolo Hospital', 'Sud Marmi');

-- Elimina dati da tutte le tabelle correlate per aziende non desiderate
DELETE FROM documenti 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM cartelle 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM eventi 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM referenti 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM tickets 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM task_calendario 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM log_attivita 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere) 
AND azienda_id != 0;

DELETE FROM utenti_aziende 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere);

DELETE FROM moduli_azienda 
WHERE azienda_id NOT IN (SELECT id FROM aziende_da_mantenere);

-- Elimina le aziende non desiderate
DELETE FROM aziende 
WHERE id NOT IN (SELECT id FROM aziende_da_mantenere);

-- Riabilita i foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Mostra le aziende rimanenti
SELECT id, nome, codice, stato 
FROM aziende 
ORDER BY nome;

-- Mostra statistiche per verificare la pulizia
SELECT 
    'Aziende' as tabella, COUNT(*) as totale 
FROM aziende
UNION ALL
SELECT 
    'Cartelle', COUNT(*) 
FROM cartelle
UNION ALL
SELECT 
    'Documenti', COUNT(*) 
FROM documenti
UNION ALL
SELECT 
    'Eventi', COUNT(*) 
FROM eventi
UNION ALL
SELECT 
    'Referenti', COUNT(*) 
FROM referenti
UNION ALL
SELECT 
    'Tickets', COUNT(*) 
FROM tickets
UNION ALL
SELECT 
    'Tasks', COUNT(*) 
FROM task_calendario
UNION ALL
SELECT 
    'Log Attività', COUNT(*) 
FROM log_attivita;

-- Drop temporary table
DROP TEMPORARY TABLE IF EXISTS aziende_da_mantenere;