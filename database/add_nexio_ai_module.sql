-- Aggiunge il modulo Nexio AI al sistema
USE NexioSol;

-- Inserisce il modulo Nexio AI nella tabella moduli_sistema
INSERT INTO moduli_sistema (codice, nome, descrizione, icona, url_pagina, ordine, attivo) 
VALUES (
    'nexio_ai', 
    'Nexio AI', 
    'Assistente AI integrato per analisi documenti e automazione processi', 
    'fas fa-robot', 
    'nexio-ai.php', 
    10,
    1
)
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    descrizione = VALUES(descrizione),
    icona = VALUES(icona),
    url_pagina = VALUES(url_pagina),
    ordine = VALUES(ordine),
    attivo = VALUES(attivo);

-- Messaggio di conferma
SELECT 'Modulo Nexio AI aggiunto con successo' as message;