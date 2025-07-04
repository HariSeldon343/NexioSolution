-- Inserimento classificazioni di esempio per Azienda Demo (assumendo id = 1)
-- Modifica l'azienda_id secondo necessità

-- Classificazioni di primo livello
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '01', 'Manuale o Documento di Sistema', NULL, 1, 1),
(1, '02', 'Documento della Direzione', NULL, 1, 1),
(1, '03', 'Procedura di Sistema', NULL, 1, 1),
(1, '04', 'Informazione Documentata', NULL, 1, 1),
(1, '05', 'Documento di Rilevazione', NULL, 1, 1),
(1, '06', 'Documento Informativo', NULL, 1, 1),
(1, '07', 'Documento Generico', NULL, 1, 1);

-- Alcune sottoclassificazioni di esempio
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '01.01', 'Manuale Qualità', 1, 2, 1),
(1, '01.02', 'Manuale Procedure', 1, 2, 1),
(1, '02.01', 'Politica Aziendale', 2, 2, 1),
(1, '02.02', 'Obiettivi e Traguardi', 2, 2, 1),
(1, '03.01', 'Procedura Gestione Documenti', 3, 2, 1),
(1, '03.02', 'Procedura Audit Interni', 3, 2, 1),
(1, '04.01', 'Moduli e Registrazioni', 4, 2, 1),
(1, '04.02', 'Specifiche Tecniche', 4, 2, 1);

-- Per super admin: inserisci classificazioni anche per altre aziende se necessario
-- INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) 
-- SELECT id, '01', 'Manuale o Documento di Sistema', NULL, 1, 1 FROM aziende WHERE id != 1; 