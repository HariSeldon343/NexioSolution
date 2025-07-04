-- Rimuovi classificazioni esistenti
DELETE FROM classificazione WHERE azienda_id = 1;

-- Inserisci le nuove classificazioni standard
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello) VALUES
(1, '01', 'Manuale o Documento di Sistema', NULL, 1),
(1, '02', 'Documento della Direzione', NULL, 1),
(1, '03', 'Procedura di Sistema', NULL, 1),
(1, '04', 'Informazione Documentata', NULL, 1),
(1, '05', 'Documento di Rilevazione', NULL, 1),
(1, '06', 'Documento Informativo', NULL, 1),
(1, '07', 'Documento Generico', NULL, 1);

-- Aggiungi classificazioni per tutte le aziende esistenti
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello)
SELECT 
    a.id,
    c.codice,
    c.descrizione,
    NULL,
    1
FROM aziende a
CROSS JOIN (
    SELECT '01' as codice, 'Manuale o Documento di Sistema' as descrizione
    UNION SELECT '02', 'Documento della Direzione'
    UNION SELECT '03', 'Procedura di Sistema'
    UNION SELECT '04', 'Informazione Documentata'
    UNION SELECT '05', 'Documento di Rilevazione'
    UNION SELECT '06', 'Documento Informativo'
    UNION SELECT '07', 'Documento Generico'
) c
WHERE a.id != 1 AND a.stato = 'attiva'; 