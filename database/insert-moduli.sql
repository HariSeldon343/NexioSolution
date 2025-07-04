-- Inserimento moduli documento
INSERT INTO moduli_documento (codice, nome, descrizione, tipo, icona, ordine, attivo) VALUES
('PROCEDURE', 'Procedure', 'Procedure operative standard', 'word', 'fa-clipboard-list', 1, 1),
('ISTRUZIONI', 'Istruzioni Operative', 'Istruzioni operative dettagliate', 'word', 'fa-tasks', 2, 1),
('MODULI', 'Moduli e Form', 'Moduli compilabili e form', 'form', 'fa-file-alt', 3, 1),
('REGISTRI', 'Registri', 'Registri di controllo e monitoraggio', 'excel', 'fa-book', 4, 1),
('VERBALI', 'Verbali', 'Verbali di riunioni e incontri', 'word', 'fa-comments', 5, 1),
('REPORT', 'Report', 'Report e analisi', 'excel', 'fa-chart-bar', 6, 1),
('CONTRATTI', 'Contratti', 'Contratti e accordi', 'word', 'fa-file-contract', 7, 1),
('POLITICHE', 'Politiche', 'Politiche aziendali', 'word', 'fa-shield-alt', 8, 1),
('CHECKLIST', 'Checklist', 'Liste di controllo', 'form', 'fa-check-square', 9, 1),
('COMUNICAZIONI', 'Comunicazioni', 'Comunicazioni interne ed esterne', 'word', 'fa-envelope', 10, 1)
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    descrizione = VALUES(descrizione),
    tipo = VALUES(tipo),
    icona = VALUES(icona),
    ordine = VALUES(ordine),
    attivo = VALUES(attivo); 