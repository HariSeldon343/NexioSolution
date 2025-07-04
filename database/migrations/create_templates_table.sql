-- Migrazione per la creazione della tabella templates
-- Gestione modelli di documento con intestazioni e piè di pagina personalizzabili

CREATE TABLE IF NOT EXISTS templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    azienda_id INT,
    
    -- Configurazioni JSON per intestazione e piè di pagina
    intestazione_config JSON,
    pie_pagina_config JSON,
    
    -- CSS personalizzato per il template
    stili_css LONGTEXT,
    
    -- Stato del template
    attivo BOOLEAN DEFAULT TRUE,
    
    -- Metadati
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indici
    INDEX idx_azienda (azienda_id),
    INDEX idx_attivo (attivo),
    INDEX idx_nome (nome),
    
    -- Chiavi esterne
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
);

-- Inserimento template di esempio
INSERT INTO templates (
    nome, 
    descrizione, 
    azienda_id, 
    intestazione_config, 
    pie_pagina_config, 
    stili_css,
    creato_da
) VALUES (
    'Template Standard Aziendale',
    'Template base con logo aziendale, titolo documento e informazioni standard',
    1,
    JSON_OBJECT(
        'columns', JSON_ARRAY(
            JSON_OBJECT(
                'rows', JSON_ARRAY(
                    JSON_OBJECT('type', 'logo', 'logo_url', '/assets/logo.png', 'max_height', '50px'),
                    JSON_OBJECT('type', 'testo_libero', 'content', 'Nome Azienda S.r.l.')
                )
            ),
            JSON_OBJECT(
                'rows', JSON_ARRAY(
                    JSON_OBJECT('type', 'titolo_documento'),
                    JSON_OBJECT('type', 'codice_documento')
                )
            ),
            JSON_OBJECT(
                'rows', JSON_ARRAY(
                    JSON_OBJECT('type', 'data_corrente'),
                    JSON_OBJECT('type', 'numero_versione')
                )
            )
        )
    ),
    JSON_OBJECT(
        'columns', JSON_ARRAY(
            JSON_OBJECT(
                'rows', JSON_ARRAY(
                    JSON_OBJECT('type', 'copyright', 'content', '© 2024 Nome Azienda S.r.l. Tutti i diritti riservati.')
                )
            ),
            JSON_OBJECT(
                'rows', JSON_ARRAY(
                    JSON_OBJECT('type', 'numero_pagine')
                )
            ),
            JSON_OBJECT(
                'rows', JSON_ARRAY(
                    JSON_OBJECT('type', 'data_revisione')
                )
            )
        )
    ),
    '.template-header { padding: 20px 0; }
     .template-footer { padding: 15px 0; font-size: 11px; }
     .template-table td { padding: 8px; }',
    1
);