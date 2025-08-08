-- Inserimento dati iniziali per il sistema ISO

-- Inserimento strutture ISO predefinite
INSERT INTO strutture_iso (codice, nome, descrizione, versione, struttura_json) VALUES
('ISO_9001', 'ISO 9001:2015', 'Sistema di Gestione Qualità', '2015', '{"capitoli": [{"numero": "4", "nome": "Contesto organizzazione"}, {"numero": "5", "nome": "Leadership"}, {"numero": "6", "nome": "Pianificazione"}, {"numero": "7", "nome": "Supporto"}, {"numero": "8", "nome": "Attività operative"}, {"numero": "9", "nome": "Valutazione prestazioni"}, {"numero": "10", "nome": "Miglioramento"}]}'),
('ISO_14001', 'ISO 14001:2015', 'Sistema di Gestione Ambientale', '2015', '{"capitoli": [{"numero": "4", "nome": "Contesto organizzazione"}, {"numero": "5", "nome": "Leadership"}, {"numero": "6", "nome": "Pianificazione"}, {"numero": "7", "nome": "Supporto"}, {"numero": "8", "nome": "Attività operative"}, {"numero": "9", "nome": "Valutazione prestazioni"}, {"numero": "10", "nome": "Miglioramento"}]}'),
('ISO_45001', 'ISO 45001:2018', 'Sistema di Gestione Salute e Sicurezza sul Lavoro', '2018', '{"capitoli": [{"numero": "4", "nome": "Contesto organizzazione"}, {"numero": "5", "nome": "Leadership e partecipazione"}, {"numero": "6", "nome": "Pianificazione"}, {"numero": "7", "nome": "Supporto"}, {"numero": "8", "nome": "Attività operative"}, {"numero": "9", "nome": "Valutazione prestazioni"}, {"numero": "10", "nome": "Miglioramento"}]}'),
('ISO_27001', 'ISO/IEC 27001:2022', 'Sistema di Gestione Sicurezza delle Informazioni', '2022', '{"capitoli": [{"numero": "4", "nome": "Contesto organizzazione"}, {"numero": "5", "nome": "Leadership"}, {"numero": "6", "nome": "Pianificazione"}, {"numero": "7", "nome": "Supporto"}, {"numero": "8", "nome": "Attività operative"}, {"numero": "9", "nome": "Valutazione prestazioni"}, {"numero": "10", "nome": "Miglioramento"}]}');

-- Inserimento classificazioni ISO di base
INSERT INTO classificazioni_iso (tipo_iso, codice, nome, descrizione, ordine) VALUES
-- ISO 9001
('ISO_9001', 'POL', 'Politiche', 'Documenti di politica aziendale', 1),
('ISO_9001', 'PRO', 'Procedure', 'Procedure operative standard', 2),
('ISO_9001', 'IST', 'Istruzioni', 'Istruzioni di lavoro', 3),
('ISO_9001', 'MOD', 'Moduli', 'Moduli e registrazioni', 4),
('ISO_9001', 'MAN', 'Manuali', 'Manuali di sistema', 5),

-- ISO 14001
('ISO_14001', 'AAI', 'Analisi Ambientale Iniziale', 'Documenti di analisi ambientale', 1),
('ISO_14001', 'ASP', 'Aspetti Ambientali', 'Registro aspetti ambientali', 2),
('ISO_14001', 'REQ', 'Requisiti Legali', 'Registro requisiti legali ambientali', 3),
('ISO_14001', 'OBA', 'Obiettivi Ambientali', 'Obiettivi e programmi ambientali', 4),
('ISO_14001', 'EME', 'Emergenze', 'Piani di emergenza ambientale', 5),

-- ISO 45001
('ISO_45001', 'DVR', 'Documento Valutazione Rischi', 'Documenti di valutazione dei rischi', 1),
('ISO_45001', 'POS', 'Procedure Operative Sicurezza', 'Procedure di sicurezza', 2),
('ISO_45001', 'DPI', 'Dispositivi Protezione Individuale', 'Gestione DPI', 3),
('ISO_45001', 'FOR', 'Formazione', 'Registri formazione sicurezza', 4),
('ISO_45001', 'INF', 'Infortuni', 'Registro infortuni e near miss', 5),

-- ISO 27001
('ISO_27001', 'PSI', 'Politica Sicurezza Informazioni', 'Politiche di sicurezza IT', 1),
('ISO_27001', 'RIS', 'Analisi Rischi', 'Valutazione rischi informatici', 2),
('ISO_27001', 'INC', 'Gestione Incidenti', 'Registro incidenti di sicurezza', 3),
('ISO_27001', 'ACC', 'Controllo Accessi', 'Procedure controllo accessi', 4),
('ISO_27001', 'BCM', 'Business Continuity', 'Piani di continuità operativa', 5);

-- Crea spazio documentale per super admin (se non esiste)
INSERT IGNORE INTO spazi_documentali (tipo, nome, descrizione) 
VALUES ('super_admin', 'Documenti Sistema', 'Spazio documentale riservato ai super amministratori');

-- Ottieni l'ID dello spazio super admin
SET @spazio_super_admin_id = (SELECT id FROM spazi_documentali WHERE tipo = 'super_admin' LIMIT 1);

-- Crea cartelle base per super admin (se lo spazio esiste)
INSERT INTO cartelle_iso (spazio_id, parent_id, nome, percorso_completo, livello, icona, colore, ordine, protetta)
SELECT 
    @spazio_super_admin_id,
    NULL,
    nome,
    nome,
    0,
    icona,
    colore,
    ordine,
    1
FROM (
    SELECT 'Template ISO' AS nome, 'fas fa-file-alt' AS icona, '#3b82f6' AS colore, 1 AS ordine
    UNION ALL
    SELECT 'Procedure Sistema', 'fas fa-cogs', '#8b5cf6', 2
    UNION ALL
    SELECT 'Documentazione Tecnica', 'fas fa-book', '#10b981', 3
    UNION ALL
    SELECT 'Archivio Audit', 'fas fa-archive', '#f59e0b', 4
) AS cartelle_base
WHERE @spazio_super_admin_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM cartelle_iso 
    WHERE spazio_id = @spazio_super_admin_id 
    AND nome = cartelle_base.nome
);