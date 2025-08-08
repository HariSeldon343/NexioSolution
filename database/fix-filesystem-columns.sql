-- Fix per le colonne mancanti del filesystem
-- Esegui questo script per risolvere gli errori "undefined"

-- 1. Fix tabella cartelle
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS iso_compliance_level VARCHAR(50) DEFAULT 'base',
ADD COLUMN IF NOT EXISTS data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Fix tabella documenti
ALTER TABLE documenti
ADD COLUMN IF NOT EXISTS dimensione_file BIGINT DEFAULT 0,
ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS tipo_documento VARCHAR(50) DEFAULT 'generico';

-- 3. Crea tabella iso_standards se non esiste
CREATE TABLE IF NOT EXISTS iso_standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codice VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(200) NOT NULL,
    descrizione TEXT,
    attivo BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Inserisci standard ISO di base
INSERT IGNORE INTO iso_standards (codice, nome, descrizione) VALUES
('ISO9001', 'ISO 9001:2015', 'Sistema di Gestione della Qualità'),
('ISO14001', 'ISO 14001:2015', 'Sistema di Gestione Ambientale'),
('ISO45001', 'ISO 45001:2018', 'Sistema di Gestione SSL'),
('ISO27001', 'ISO 27001:2022', 'Sistema di Gestione Sicurezza Informazioni'),
('GDPR', 'GDPR', 'Regolamento Generale sulla Protezione dei Dati'),
('SGI', 'SGI', 'Sistema di Gestione Integrato'),
('CUSTOM', 'Personalizzato', 'Struttura Personalizzata');

-- 5. Aggiungi indici per migliorare le performance
CREATE INDEX IF NOT EXISTS idx_cartelle_standard ON cartelle(iso_standard_codice);
CREATE INDEX IF NOT EXISTS idx_cartelle_azienda_parent ON cartelle(azienda_id, parent_id);
CREATE INDEX IF NOT EXISTS idx_documenti_cartella ON documenti(cartella_id);
CREATE INDEX IF NOT EXISTS idx_documenti_azienda ON documenti(azienda_id);

-- 6. Aggiorna percorsi completi per cartelle esistenti
UPDATE cartelle 
SET percorso_completo = nome 
WHERE parent_id IS NULL AND (percorso_completo IS NULL OR percorso_completo = '');

-- 7. Crea una cartella di default per super admin se non esiste
INSERT IGNORE INTO cartelle (nome, parent_id, percorso_completo, azienda_id, iso_standard_codice, creato_da)
SELECT 'Documenti Personali', NULL, 'Documenti Personali', 0, 'CUSTOM', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cartelle WHERE azienda_id = 0 LIMIT 1);