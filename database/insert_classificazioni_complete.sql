-- Struttura gerarchica completa delle classificazioni
-- Assumendo azienda_id = 1 (modificare secondo necessità)

-- Pulizia classificazioni esistenti per l'azienda
DELETE FROM classificazione WHERE azienda_id = 1;

-- PRIMO LIVELLO - Categorie principali
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '1', 'Normative e Standard', NULL, 1, 1),
(1, '2', 'Documentazione del Sistema di Gestione', NULL, 1, 1),
(1, '3', 'Documenti di Origine Esterna', NULL, 1, 1);

-- SECONDO LIVELLO - Sottocategorie per "Normative e Standard"
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '1.1', 'ISO 9001 - Sistema di Gestione Qualità', 1, 2, 1),
(1, '1.2', 'ISO 14001 - Sistema di Gestione Ambientale', 1, 2, 1),
(1, '1.3', 'ISO 45001 - Sistema di Gestione Sicurezza', 1, 2, 1),
(1, '1.4', 'ISO 27001 - Sistema di Gestione Sicurezza Informazioni', 1, 2, 1),
(1, '1.5', 'Normative Nazionali/Europee', 1, 2, 1),
(1, '1.6', 'Regolamenti Settoriali', 1, 2, 1),
(1, '1.7', 'Aggiornamenti Normativi', 1, 2, 1);

-- SECONDO LIVELLO - Sottocategorie per "Documentazione del Sistema di Gestione"
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '2.1', 'Manuale della Qualità/Ambiente/Sicurezza', 2, 2, 1),
(1, '2.2', 'Politiche Aziendali', 2, 2, 1),
(1, '2.3', 'Procedure Generali', 2, 2, 1),
(1, '2.4', 'Procedure Operative', 2, 2, 1),
(1, '2.5', 'Istruzioni di Lavoro', 2, 2, 1),
(1, '2.6', 'Moduli e Registrazioni', 2, 2, 1);

-- SECONDO LIVELLO - Sottocategorie per "Documenti di Origine Esterna"
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '3.1', 'Contratti con Fornitori', 3, 2, 1),
(1, '3.2', 'Certificazioni', 3, 2, 1),
(1, '3.3', 'Autorizzazioni', 3, 2, 1),
(1, '3.4', 'Comunicazioni Enti', 3, 2, 1);

-- TERZO LIVELLO - Aree funzionali per ogni sottocategoria principale
-- Per brevità, aggiungo le aree funzionali solo per alcune sottocategorie chiave

-- Aree funzionali per "Procedure Generali" (2.3)
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '2.3.1', 'Direzione e Strategia', 13, 3, 1),
(1, '2.3.2', 'Gestione Risorse Umane', 13, 3, 1),
(1, '2.3.3', 'Produzione/Erogazione Servizi', 13, 3, 1),
(1, '2.3.4', 'Qualità e Controllo', 13, 3, 1),
(1, '2.3.5', 'Ambiente e Sicurezza', 13, 3, 1),
(1, '2.3.6', 'Amministrazione e Finanza', 13, 3, 1),
(1, '2.3.7', 'Commerciale e Marketing', 13, 3, 1);

-- Aree funzionali per "Procedure Operative" (2.4)
INSERT INTO classificazione (azienda_id, codice, descrizione, parent_id, livello, attivo) VALUES
(1, '2.4.1', 'Direzione e Strategia', 14, 3, 1),
(1, '2.4.2', 'Gestione Risorse Umane', 14, 3, 1),
(1, '2.4.3', 'Produzione/Erogazione Servizi', 14, 3, 1),
(1, '2.4.4', 'Qualità e Controllo', 14, 3, 1),
(1, '2.4.5', 'Ambiente e Sicurezza', 14, 3, 1),
(1, '2.4.6', 'Amministrazione e Finanza', 14, 3, 1),
(1, '2.4.7', 'Commerciale e Marketing', 14, 3, 1);

-- Per replicare la struttura per altre aziende:
-- UPDATE classificazione SET azienda_id = 2 WHERE azienda_id = 1;
-- (eseguire dopo aver inserito per azienda 1) 