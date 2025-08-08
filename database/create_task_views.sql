-- Creazione vista per il conteggio delle giornate task
USE nexiosol;

-- Drop della vista se esiste
DROP VIEW IF EXISTS vista_conteggio_giornate_task;

-- Creazione della vista per contare le giornate task per utente
CREATE VIEW vista_conteggio_giornate_task AS
SELECT 
    u.id AS utente_assegnato_id,
    u.nome AS utente_nome,
    u.cognome AS utente_cognome,
    COUNT(DISTINCT DATE(t.data_scadenza)) AS totale_giornate_task,
    COUNT(DISTINCT CASE WHEN t.stato = 'completato' THEN DATE(t.data_scadenza) END) AS giornate_completate,
    COUNT(DISTINCT CASE WHEN t.stato != 'completato' AND t.data_scadenza < CURDATE() THEN DATE(t.data_scadenza) END) AS giornate_scadute,
    COUNT(DISTINCT CASE WHEN t.stato != 'completato' AND t.data_scadenza >= CURDATE() THEN DATE(t.data_scadenza) END) AS giornate_future
FROM 
    utenti u
LEFT JOIN 
    tasks t ON u.id = t.assegnato_a
WHERE 
    u.attivo = 1
GROUP BY 
    u.id, u.nome, u.cognome;