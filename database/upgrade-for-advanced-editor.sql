-- Upgrade del database per l'Editor Avanzato Nexio
-- Aggiunge colonne necessarie per il funzionamento del nuovo editor

-- Verifica e aggiunta colonne alla tabella documenti
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS contenuto_html LONGTEXT DEFAULT NULL AFTER contenuto,
ADD COLUMN IF NOT EXISTS contenuto_testo LONGTEXT DEFAULT NULL AFTER contenuto_html,
ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL AFTER contenuto_testo;

-- Crea indici per migliorare le performance
CREATE INDEX IF NOT EXISTS idx_documenti_updated_at ON documenti(updated_at);
CREATE INDEX IF NOT EXISTS idx_documenti_user_updated ON documenti(user_id, updated_at);

-- Aggiorna documenti esistenti che non hanno contenuto_html
UPDATE documenti 
SET contenuto_html = contenuto, 
    contenuto_testo = REGEXP_REPLACE(REGEXP_REPLACE(contenuto, '<[^>]+>', ''), '&[^;]+;', '')
WHERE contenuto_html IS NULL AND contenuto IS NOT NULL;

-- Aggiungi metadati di base ai documenti esistenti
UPDATE documenti 
SET metadata = JSON_OBJECT(
    'editor_version', 'legacy',
    'migrated_to_advanced', CURRENT_TIMESTAMP,
    'stats', JSON_OBJECT(
        'words', 0,
        'chars', CHAR_LENGTH(COALESCE(contenuto_testo, '')),
        'pages', 1,
        'paragraphs', 1
    ),
    'settings', JSON_OBJECT(
        'autoHeader', true,
        'autoFooter', true,
        'pageNumbers', true,
        'companyLogo', true
    )
)
WHERE metadata IS NULL;

-- Crea tabella per le impostazioni dell'editor (opzionale)
CREATE TABLE IF NOT EXISTS editor_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_name VARCHAR(100) NOT NULL,
    setting_value JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (user_id, setting_name)
);

-- Inserisci impostazioni predefinite per l'editor
INSERT IGNORE INTO editor_settings (user_id, setting_name, setting_value)
SELECT DISTINCT id, 'default_template', JSON_OBJECT(
    'autoSave', true,
    'autoSaveInterval', 30000,
    'defaultFont', 'Times New Roman',
    'defaultFontSize', 12,
    'theme', 'office365'
)
FROM users WHERE id IN (SELECT DISTINCT user_id FROM documenti);

-- Log della migrazione
INSERT INTO log_attivita (user_id, azione, descrizione, created_at)
SELECT 1, 'sistema_aggiornato', 'Database aggiornato per Editor Avanzato Nexio', NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM log_attivita 
    WHERE azione = 'sistema_aggiornato' 
    AND descrizione LIKE '%Editor Avanzato Nexio%'
);