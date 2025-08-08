-- MIGRAZIONE AL SISTEMA DOCUMENTALE MULTI-STANDARD
-- Script di migrazione per adattare il database esistente al nuovo sistema
-- Mantiene compatibilità con dati esistenti e aggiunge funzionalità avanzate
-- Versione: 3.0 - Gennaio 2025

USE NexioSol;

-- =======================================================================================
-- 1. BACKUP E PREPARAZIONE
-- =======================================================================================

-- Crea tabelle di backup per sicurezza
CREATE TABLE IF NOT EXISTS _backup_documenti_pre_migration AS SELECT * FROM documenti LIMIT 0;
CREATE TABLE IF NOT EXISTS _backup_cartelle_pre_migration AS SELECT * FROM cartelle LIMIT 0;

-- Log della migrazione
CREATE TABLE IF NOT EXISTS migration_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    step_name VARCHAR(200) NOT NULL,
    status ENUM('started', 'completed', 'failed') NOT NULL,
    error_message TEXT NULL,
    execution_time DECIMAL(10,3),
    timestamp_execution TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO migration_log (step_name, status) VALUES ('Migration started', 'started');

-- =======================================================================================
-- 2. VERIFICA PREREQUISITI
-- =======================================================================================

-- Verifica che le tabelle base esistano
SELECT 
    CASE 
        WHEN COUNT(*) = 4 THEN 'Prerequisiti soddisfatti'
        ELSE CONCAT('ERRORE: Mancano tabelle base. Trovate: ', COUNT(*), ' su 4')
    END AS prerequisiti_check
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('aziende', 'utenti', 'cartelle', 'documenti');

-- =======================================================================================
-- 3. ESTENSIONE TABELLE ESISTENTI
-- =======================================================================================

-- Aggiorna tabella utenti per ruoli avanzati (se non già fatto)
ALTER TABLE utenti 
MODIFY COLUMN ruolo ENUM('super_admin', 'utente_speciale', 'admin', 'staff', 'cliente') NOT NULL DEFAULT 'cliente';

-- Estendi tabella aziende per gestione avanzata
ALTER TABLE aziende 
ADD COLUMN IF NOT EXISTS configurazione_documentale JSON COMMENT 'Configurazioni documentali specifiche',
ADD COLUMN IF NOT EXISTS impostazioni_conformita JSON COMMENT 'Impostazioni conformità normative',
ADD COLUMN IF NOT EXISTS ultima_verifica_conformita TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS responsabile_conformita INT,
ADD INDEX IF NOT EXISTS idx_aziende_responsabile_conformita (responsabile_conformita);

-- Estendi tabella cartelle con metadati ISO e permessi
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS descrizione TEXT COMMENT 'Descrizione cartella',
ADD COLUMN IF NOT EXISTS tags JSON COMMENT 'Tag per categorizzazione',
ADD COLUMN IF NOT EXISTS is_iso_structure BOOLEAN DEFAULT FALSE COMMENT 'Se fa parte di struttura ISO',
ADD COLUMN IF NOT EXISTS iso_standard_codice VARCHAR(20) COMMENT 'Codice standard ISO di riferimento',
ADD COLUMN IF NOT EXISTS iso_template_id INT COMMENT 'ID template ISO utilizzato',
ADD COLUMN IF NOT EXISTS iso_compliance_level ENUM('obbligatoria', 'raccomandata', 'opzionale', 'personalizzata') COMMENT 'Livello conformità',
ADD COLUMN IF NOT EXISTS iso_metadata JSON COMMENT 'Metadati specifici ISO',
ADD COLUMN IF NOT EXISTS access_permissions JSON COMMENT 'Permessi di accesso',
ADD COLUMN IF NOT EXISTS hidden BOOLEAN DEFAULT FALSE COMMENT 'Se cartella è nascosta',
ADD COLUMN IF NOT EXISTS stato ENUM('attiva', 'cestino', 'archiviata') DEFAULT 'attiva' COMMENT 'Stato cartella',
ADD COLUMN IF NOT EXISTS data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data creazione',
ADD COLUMN IF NOT EXISTS ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD INDEX IF NOT EXISTS idx_cartelle_iso_standard (iso_standard_codice),
ADD INDEX IF NOT EXISTS idx_cartelle_iso_template (iso_template_id),
ADD INDEX IF NOT EXISTS idx_cartelle_compliance_level (iso_compliance_level),
ADD INDEX IF NOT EXISTS idx_cartelle_is_iso (is_iso_structure),
ADD INDEX IF NOT EXISTS idx_cartelle_stato (stato),
ADD INDEX IF NOT EXISTS idx_cartelle_hidden (hidden);

-- Estendi tabella documenti con metadati avanzati
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS document_structure_folder_id INT COMMENT 'Link alla struttura documentale',
ADD COLUMN IF NOT EXISTS metadata_compliance JSON COMMENT 'Metadati conformità normative',
ADD COLUMN IF NOT EXISTS tags_documenti JSON COMMENT 'Tag per categorizzazione',
ADD COLUMN IF NOT EXISTS checksum_file VARCHAR(64) COMMENT 'Hash SHA-256 del file',
ADD COLUMN IF NOT EXISTS virus_scan_status ENUM('pending', 'clean', 'infected', 'error', 'skipped') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS virus_scan_date TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS full_text_content LONGTEXT COMMENT 'Contenuto estratto per ricerca full-text',
ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(500) COMMENT 'Path thumbnail generata',
ADD COLUMN IF NOT EXISTS preview_available BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS workflow_stato ENUM('bozza', 'in_revisione', 'approvato', 'pubblicato', 'scaduto', 'ritirato') DEFAULT 'bozza',
ADD COLUMN IF NOT EXISTS data_scadenza DATE COMMENT 'Data scadenza documento per review',
ADD COLUMN IF NOT EXISTS responsabile_documento INT COMMENT 'Responsabile del documento',
ADD COLUMN IF NOT EXISTS numero_revisione INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS motivo_revisione TEXT,
ADD COLUMN IF NOT EXISTS data_ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD INDEX IF NOT EXISTS idx_doc_structure_folder (document_structure_folder_id),
ADD INDEX IF NOT EXISTS idx_doc_checksum (checksum_file),
ADD INDEX IF NOT EXISTS idx_doc_workflow (workflow_stato),
ADD INDEX IF NOT EXISTS idx_doc_scadenza (data_scadenza),
ADD INDEX IF NOT EXISTS idx_doc_responsabile (responsabile_documento),
ADD INDEX IF NOT EXISTS idx_doc_virus_scan (virus_scan_status);

-- Aggiungi indice full-text per ricerca (se non esiste)
ALTER TABLE documenti 
ADD FULLTEXT INDEX IF NOT EXISTS idx_doc_fulltext_search (titolo, full_text_content);

INSERT INTO migration_log (step_name, status) VALUES ('Extended existing tables', 'completed');

-- =======================================================================================
-- 4. MIGRAZIONE DATI ESISTENTI
-- =======================================================================================

-- Backup dati prima della migrazione
INSERT INTO _backup_documenti_pre_migration SELECT * FROM documenti;
INSERT INTO _backup_cartelle_pre_migration SELECT * FROM cartelle;

-- Aggiorna documenti esistenti con valori di default appropriati
UPDATE documenti 
SET 
    workflow_stato = CASE 
        WHEN stato = 'pubblicato' THEN 'pubblicato'
        WHEN stato = 'bozza' THEN 'bozza'
        ELSE 'bozza'
    END,
    numero_revisione = COALESCE(versione, 1),
    virus_scan_status = 'skipped'  -- Per documenti esistenti
WHERE workflow_stato IS NULL;

-- Aggiorna cartelle esistenti con metadati di base
UPDATE cartelle 
SET 
    stato = 'attiva',
    data_creazione = COALESCE(data_creazione, NOW()),
    is_iso_structure = FALSE
WHERE stato IS NULL;

-- Aggiorna aziende con configurazioni di default
UPDATE aziende 
SET 
    configurazione_documentale = JSON_OBJECT(
        'auto_numbering', TRUE,
        'versioning_enabled', TRUE,
        'compliance_checks', FALSE,
        'audit_trail', TRUE
    ),
    impostazioni_conformita = JSON_OBJECT(
        'standards_enabled', JSON_ARRAY(),
        'automatic_checks', FALSE,
        'notification_enabled', TRUE
    )
WHERE configurazione_documentale IS NULL;

INSERT INTO migration_log (step_name, status) VALUES ('Migrated existing data', 'completed');

-- =======================================================================================
-- 5. CREAZIONE CONFIGURAZIONI DEFAULT PER AZIENDE ESISTENTI
-- =======================================================================================

-- Crea configurazione documentale di default per ogni azienda esistente
INSERT INTO company_document_structures (
    azienda_id, 
    nome_configurazione, 
    tipo_struttura, 
    standards_attivi, 
    stato, 
    default_struttura,
    permessi_default,
    workflow_approvazione,
    regole_retention,
    creato_da
)
SELECT 
    a.id,
    CONCAT('Configurazione Standard - ', a.nome),
    'personalizzata',
    JSON_ARRAY(), -- Inizialmente vuoto, da configurare
    'bozza',
    TRUE,
    JSON_OBJECT(
        'default_read', JSON_ARRAY('admin', 'staff'),
        'default_write', JSON_ARRAY('admin'),
        'default_delete', JSON_ARRAY('admin'),
        'default_share', JSON_ARRAY('admin', 'staff')
    ),
    JSON_OBJECT(
        'approval_required', FALSE,
        'automatic_approval', JSON_ARRAY(),
        'manual_approval', JSON_ARRAY('admin')
    ),
    JSON_OBJECT(
        'default_retention_days', 2555, -- 7 anni
        'auto_archive', FALSE,
        'auto_delete', FALSE
    ),
    1 -- Super admin
FROM aziende a
WHERE NOT EXISTS (
    SELECT 1 FROM company_document_structures cds 
    WHERE cds.azienda_id = a.id AND cds.default_struttura = TRUE
);

INSERT INTO migration_log (step_name, status) VALUES ('Created default configurations', 'completed');

-- =======================================================================================
-- 6. MIGRAZIONE CARTELLE ESISTENTI ALLA NUOVA STRUTTURA
-- =======================================================================================

-- Identifica cartelle che potrebbero essere strutture ISO esistenti
UPDATE cartelle c
JOIN (
    SELECT id FROM cartelle 
    WHERE nome REGEXP '(ISO|Qualit|Ambiente|Sicurezza|Privacy|GDPR|Audit|Procedure|Manuale)'
    AND parent_id IS NULL
) iso_candidates ON c.id = iso_candidates.id
SET 
    c.is_iso_structure = TRUE,
    c.iso_compliance_level = 'personalizzata',
    c.iso_metadata = JSON_OBJECT(
        'migrated_from_existing', TRUE,
        'migration_date', NOW(),
        'original_structure', 'legacy'
    );

-- Aggiorna sottocartelle delle cartelle ISO identificate
UPDATE cartelle c
SET 
    c.is_iso_structure = TRUE,
    c.iso_compliance_level = 'personalizzata'
WHERE c.parent_id IN (
    SELECT id FROM (
        SELECT id FROM cartelle WHERE is_iso_structure = TRUE
    ) AS iso_parents
);

INSERT INTO migration_log (step_name, status) VALUES ('Migrated existing folders to ISO structure', 'completed');

-- =======================================================================================
-- 7. CREAZIONE PERMESSI DI DEFAULT
-- =======================================================================================

-- Crea permessi di default per cartelle principali
INSERT INTO folder_permissions (
    cartella_id,
    ruolo,
    permessi,
    eredita_da_parent,
    applica_a_sottocartelle,
    applica_a_documenti,
    attivo,
    assegnato_da
)
SELECT 
    c.id,
    'admin',
    JSON_OBJECT(
        'read', TRUE,
        'write', TRUE,
        'delete', TRUE,
        'share', TRUE,
        'admin', TRUE
    ),
    FALSE,
    TRUE,
    TRUE,
    TRUE,
    1
FROM cartelle c
WHERE c.parent_id IS NULL  -- Solo cartelle root
AND NOT EXISTS (
    SELECT 1 FROM folder_permissions fp 
    WHERE fp.cartella_id = c.id AND fp.ruolo = 'admin'
);

-- Crea permessi di lettura per staff
INSERT INTO folder_permissions (
    cartella_id,
    ruolo,
    permessi,
    eredita_da_parent,
    applica_a_sottocartelle,
    applica_a_documenti,
    attivo,
    assegnato_da
)
SELECT 
    c.id,
    'staff',
    JSON_OBJECT(
        'read', TRUE,
        'write', FALSE,
        'delete', FALSE,
        'share', FALSE,
        'admin', FALSE
    ),
    FALSE,
    TRUE,
    TRUE,
    TRUE,
    1
FROM cartelle c
WHERE c.parent_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM folder_permissions fp 
    WHERE fp.cartella_id = c.id AND fp.ruolo = 'staff'
);

INSERT INTO migration_log (step_name, status) VALUES ('Created default permissions', 'completed');

-- =======================================================================================
-- 8. MIGRAZIONE VERSIONI DOCUMENTI ESISTENTI
-- =======================================================================================

-- Migra versioni da versioni_documenti a document_versions (se la tabella esiste)
INSERT INTO document_versions (
    documento_id,
    numero_versione,
    numero_revisione,
    contenuto_estratto,
    note_versione,
    creato_da,
    data_creazione
)
SELECT 
    vd.documento_id,
    vd.versione,
    1,
    LEFT(vd.contenuto, 10000), -- Primi 10k caratteri
    vd.note_modifica,
    vd.modificato_da,
    vd.data_modifica
FROM versioni_documenti vd
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'versioni_documenti')
AND NOT EXISTS (
    SELECT 1 FROM document_versions dv 
    WHERE dv.documento_id = vd.documento_id 
    AND dv.numero_versione = vd.versione
);

INSERT INTO migration_log (step_name, status) VALUES ('Migrated document versions', 'completed');

-- =======================================================================================
-- 9. POPOLAMENTO INDICE DI RICERCA
-- =======================================================================================

-- Popola l'indice di ricerca per documenti esistenti
INSERT INTO document_search_index (
    documento_id,
    titolo_tokens,
    contenuto_tokens,
    full_tokens,
    lingua_documento,
    peso_rilevanza
)
SELECT 
    d.id,
    d.titolo,
    COALESCE(LEFT(d.contenuto, 5000), ''),
    CONCAT(d.titolo, ' ', COALESCE(LEFT(d.contenuto, 5000), '')),
    'it',
    CASE 
        WHEN d.stato = 'pubblicato' THEN 1.00
        WHEN d.stato = 'bozza' THEN 0.70
        ELSE 0.50
    END
FROM documenti d
WHERE NOT EXISTS (
    SELECT 1 FROM document_search_index dsi 
    WHERE dsi.documento_id = d.id
);

INSERT INTO migration_log (step_name, status) VALUES ('Populated search index', 'completed');

-- =======================================================================================
-- 10. CREAZIONE AUDIT TRAIL PER DOCUMENTI ESISTENTI
-- =======================================================================================

-- Crea entry di audit trail per documenti esistenti (creazione)
INSERT INTO document_audit_trail (
    documento_id,
    utente_id,
    azione,
    dettagli_azione,
    ip_address,
    risultato,
    data_azione
)
SELECT 
    d.id,
    d.creato_da,
    'create',
    JSON_OBJECT(
        'titolo', d.titolo,
        'migrated_from_existing', TRUE,
        'original_stato', d.stato
    ),
    '127.0.0.1',
    'successo',
    d.data_creazione
FROM documenti d
WHERE NOT EXISTS (
    SELECT 1 FROM document_audit_trail dat 
    WHERE dat.documento_id = d.id AND dat.azione = 'create'
);

INSERT INTO migration_log (step_name, status) VALUES ('Created audit trail entries', 'completed');

-- =======================================================================================
-- 11. OTTIMIZZAZIONE E PULIZIA
-- =======================================================================================

-- Aggiorna statistiche delle tabelle
ANALYZE TABLE documenti, cartelle, document_structure_folders, document_versions;

-- Ottimizza tabelle modificate
OPTIMIZE TABLE documenti, cartelle;

-- Aggiorna cache statistiche per configurazioni azienda
UPDATE company_document_structures cds
SET personalizzazioni = JSON_SET(
    COALESCE(personalizzazioni, JSON_OBJECT()),
    '$.migration_stats',
    JSON_OBJECT(
        'total_folders', (
            SELECT COUNT(*) FROM cartelle c 
            WHERE c.azienda_id = cds.azienda_id
        ),
        'total_documents', (
            SELECT COUNT(*) FROM documenti d 
            WHERE d.azienda_id = cds.azienda_id
        ),
        'migration_completed', NOW()
    )
);

INSERT INTO migration_log (step_name, status) VALUES ('Optimization completed', 'completed');

-- =======================================================================================
-- 12. VERIFICA INTEGRITÀ POST-MIGRAZIONE
-- =======================================================================================

-- Verifica integrità referenziale
SELECT 
    'Verifica integrità' AS test_name,
    CASE 
        WHEN COUNT(*) = 0 THEN 'PASS - Nessun documento orfano'
        ELSE CONCAT('FAIL - ', COUNT(*), ' documenti senza cartella valida')
    END AS result
FROM documenti d
LEFT JOIN cartelle c ON c.id = d.cartella_id
WHERE d.cartella_id IS NOT NULL AND c.id IS NULL
UNION ALL
SELECT 
    'Verifica configurazioni azienda' AS test_name,
    CASE 
        WHEN COUNT(*) > 0 THEN CONCAT('PASS - ', COUNT(*), ' configurazioni create')
        ELSE 'FAIL - Nessuna configurazione trovata'
    END AS result
FROM company_document_structures
UNION ALL
SELECT 
    'Verifica indice ricerca' AS test_name,
    CASE 
        WHEN COUNT(*) > 0 THEN CONCAT('PASS - ', COUNT(*), ' documenti indicizzati')
        ELSE 'FAIL - Indice ricerca vuoto'
    END AS result
FROM document_search_index;

-- =======================================================================================
-- 13. CREAZIONE STORED PROCEDURES PER MANUTENZIONE
-- =======================================================================================

DELIMITER //

-- Procedura per aggiornamento indice ricerca
CREATE PROCEDURE IF NOT EXISTS sp_update_search_index(IN doc_id INT)
BEGIN
    DECLARE doc_title VARCHAR(200);
    DECLARE doc_content LONGTEXT;
    DECLARE doc_status VARCHAR(50);
    
    SELECT titolo, COALESCE(contenuto, ''), stato
    INTO doc_title, doc_content, doc_status
    FROM documenti 
    WHERE id = doc_id;
    
    INSERT INTO document_search_index (
        documento_id, titolo_tokens, contenuto_tokens, full_tokens, peso_rilevanza
    ) VALUES (
        doc_id,
        doc_title,
        LEFT(doc_content, 5000),
        CONCAT(doc_title, ' ', LEFT(doc_content, 5000)),
        CASE WHEN doc_status = 'pubblicato' THEN 1.00 ELSE 0.70 END
    ) ON DUPLICATE KEY UPDATE
        titolo_tokens = VALUES(titolo_tokens),
        contenuto_tokens = VALUES(contenuto_tokens),
        full_tokens = VALUES(full_tokens),
        peso_rilevanza = VALUES(peso_rilevanza),
        ultima_modifica = NOW();
END //

-- Procedura per pulizia audit trail vecchio
CREATE PROCEDURE IF NOT EXISTS sp_cleanup_old_audit_trail(IN days_old INT)
BEGIN
    DELETE FROM document_audit_trail 
    WHERE data_azione < DATE_SUB(NOW(), INTERVAL days_old DAY)
    AND azione IN ('read', 'download');
    
    SELECT ROW_COUNT() AS records_deleted;
END //

DELIMITER ;

INSERT INTO migration_log (step_name, status) VALUES ('Created maintenance procedures', 'completed');

-- =======================================================================================
-- 14. MESSAGGIO FINALE E STATISTICHE
-- =======================================================================================

INSERT INTO migration_log (step_name, status) VALUES ('Migration completed successfully', 'completed');

-- Report finale migrazione
SELECT 
    'MIGRAZIONE COMPLETATA CON SUCCESSO' AS status,
    '' AS separator,
    CONCAT('Aziende processate: ', COUNT(DISTINCT a.id)) AS aziende_migrate,
    CONCAT('Configurazioni create: ', COUNT(DISTINCT cds.id)) AS configurazioni_create,
    CONCAT('Cartelle migrate: ', COUNT(DISTINCT c.id)) AS cartelle_migrate,
    CONCAT('Documenti processati: ', COUNT(DISTINCT d.id)) AS documenti_processati,
    CONCAT('Indici ricerca creati: ', COUNT(DISTINCT dsi.id)) AS indici_ricerca,
    CONCAT('Audit trail entries: ', COUNT(DISTINCT dat.id)) AS audit_entries,
    NOW() AS completata_il
FROM aziende a
LEFT JOIN company_document_structures cds ON cds.azienda_id = a.id
LEFT JOIN cartelle c ON c.azienda_id = a.id
LEFT JOIN documenti d ON d.azienda_id = a.id
LEFT JOIN document_search_index dsi ON dsi.documento_id = d.id
LEFT JOIN document_audit_trail dat ON dat.documento_id = d.id;

-- Log delle operazioni completate
SELECT 
    step_name,
    status,
    timestamp_execution,
    CASE 
        WHEN error_message IS NOT NULL THEN error_message
        ELSE 'Completato con successo'
    END AS result
FROM migration_log 
ORDER BY id;

-- Raccomandazioni post-migrazione
SELECT 
    'RACCOMANDAZIONI POST-MIGRAZIONE' AS titolo,
    '' AS separator,
    '1. Configurare standard documentali per ogni azienda' AS step_1,
    '2. Assegnare responsabili documenti' AS step_2, 
    '3. Impostare workflow di approvazione' AS step_3,
    '4. Configurare verifiche conformità' AS step_4,
    '5. Testare funzionalità ricerca full-text' AS step_5,
    '6. Verificare permessi cartelle/documenti' AS step_6,
    '7. Pianificare backup regolari' AS step_7;

SELECT 'MIGRAZIONE SISTEMA DOCUMENTALE MULTI-STANDARD COMPLETATA!' AS messaggio_finale;