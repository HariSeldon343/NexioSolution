-- Elimina la vista se esiste
DROP VIEW IF EXISTS vista_log_attivita;

-- Crea la vista con i nomi completi delle tabelle
CREATE VIEW vista_log_attivita AS
SELECT 
    log_attivita.*,
    COALESCE(utenti.email, referenti_aziende.email) as email_utente,
    COALESCE(
        CONCAT(utenti.nome, ' ', utenti.cognome), 
        CONCAT(referenti_aziende.nome, ' ', referenti_aziende.cognome)
    ) as nome_completo,
    aziende.nome as nome_azienda,
    CASE 
        WHEN log_attivita.utente_id IS NOT NULL THEN 'utente_sistema'
        WHEN log_attivita.referente_id IS NOT NULL THEN 'referente_azienda'
        ELSE 'sconosciuto'
    END as tipo_utente
FROM log_attivita
LEFT JOIN utenti ON log_attivita.utente_id = utenti.id
LEFT JOIN referenti_aziende ON log_attivita.referente_id = referenti_aziende.id
LEFT JOIN aziende ON log_attivita.azienda_id = aziende.id
ORDER BY log_attivita.creato_il DESC; 