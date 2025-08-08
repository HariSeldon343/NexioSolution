-- VISTE PER REPORTING SISTEMA DOCUMENTALE MULTI-STANDARD
-- Viste ottimizzate per dashboard, report e analisi conformità
-- Versione: 3.0 - Gennaio 2025

USE NexioSol;

-- =======================================================================================
-- 1. VISTE PRINCIPALI PER DOCUMENTI E CARTELLE
-- =======================================================================================

-- Vista completa documenti con tutte le informazioni correlate
CREATE OR REPLACE VIEW v_documenti_dettaglio_completo AS
SELECT 
    -- Informazioni base documento
    d.id,
    d.codice,
    d.titolo,
    d.descrizione,
    d.file_path,
    d.file_size,
    d.mime_type,
    d.checksum_file,
    d.stato,
    d.workflow_stato,
    d.versione,
    d.numero_revisione,
    d.data_scadenza,
    d.data_creazione,
    d.ultima_modifica,
    
    -- Informazioni cartella
    c.id AS cartella_id,
    c.nome AS cartella_nome,
    c.percorso_completo AS cartella_percorso,
    
    -- Informazioni struttura ISO
    dsf.id AS structure_folder_id,
    dsf.standard_codice,
    dsf.percorso_iso,
    ds.nome AS standard_nome,
    ds.icona AS standard_icona,
    ds.colore AS standard_colore,
    dst.nome AS template_nome,
    dst.livello AS template_livello,
    dst.obbligatoria AS template_obbligatoria,
    
    -- Informazioni configurazione azienda
    cds.tipo_struttura,
    cds.nome_configurazione,
    
    -- Informazioni utenti
    u_creato.nome AS creato_da_nome,
    u_creato.cognome AS creato_da_cognome,
    u_creato.email AS creato_da_email,
    u_responsabile.nome AS responsabile_nome,
    u_responsabile.cognome AS responsabile_cognome,
    u_responsabile.email AS responsabile_email,
    u_modificato.nome AS modificato_da_nome,
    u_modificato.cognome AS modificato_da_cognome,
    
    -- Statistiche versioni
    COALESCE(stats_vers.numero_versioni, 1) AS numero_versioni,
    stats_vers.ultima_versione_data,
    
    -- Statistiche condivisioni
    COALESCE(stats_share.numero_condivisioni_attive, 0) AS numero_condivisioni_attive,
    COALESCE(stats_share.numero_accessi_totali, 0) AS numero_accessi_totali,
    
    -- Informazioni compliance
    comp.stato_conformita,
    comp.punteggio_conformita,
    comp.data_ultima_verifica,
    comp.data_prossima_verifica,
    
    -- Metadati e tag
    d.metadata_compliance,
    d.tags_documenti,
    
    -- Indicatori stato
    CASE 
        WHEN d.data_scadenza IS NOT NULL AND d.data_scadenza <= CURDATE() THEN TRUE
        ELSE FALSE
    END AS documento_scaduto,
    
    CASE 
        WHEN d.virus_scan_status = 'clean' THEN TRUE
        WHEN d.virus_scan_status = 'pending' THEN NULL
        ELSE FALSE
    END AS sicurezza_verificata,
    
    d.preview_available,
    d.thumbnail_path IS NOT NULL AS ha_thumbnail

FROM documenti d
LEFT JOIN cartelle c ON c.id = d.cartella_id
LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
LEFT JOIN document_structure_templates dst ON dst.id = dsf.template_id
LEFT JOIN document_standards ds ON ds.id = dst.standard_id
LEFT JOIN company_document_structures cds ON cds.id = dsf.company_structure_id
LEFT JOIN utenti u_creato ON u_creato.id = d.creato_da
LEFT JOIN utenti u_responsabile ON u_responsabile.id = d.responsabile_documento
LEFT JOIN utenti u_modificato ON u_modificato.id = d.modificato_da

-- Statistiche versioni
LEFT JOIN (
    SELECT 
        documento_id,
        COUNT(*) as numero_versioni,
        MAX(data_creazione) as ultima_versione_data
    FROM document_versions
    GROUP BY documento_id
) stats_vers ON stats_vers.documento_id = d.id

-- Statistiche condivisioni
LEFT JOIN (
    SELECT 
        documento_id,
        COUNT(*) as numero_condivisioni_attive,
        SUM(numero_accessi_effettuati) as numero_accessi_totali
    FROM document_sharing
    WHERE attiva = TRUE
    GROUP BY documento_id
) stats_share ON stats_share.documento_id = d.id

-- Informazioni compliance più recenti
LEFT JOIN (
    SELECT 
        dcc1.documento_id,
        dcc1.stato_conformita,
        dcc1.punteggio_conformita,
        dcc1.data_verifica as data_ultima_verifica,
        dcc1.data_prossima_verifica
    FROM document_compliance_checks dcc1
    WHERE dcc1.data_verifica = (
        SELECT MAX(dcc2.data_verifica)
        FROM document_compliance_checks dcc2
        WHERE dcc2.documento_id = dcc1.documento_id
    )
) comp ON comp.documento_id = d.id;

-- Vista cartelle con statistiche complete
CREATE OR REPLACE VIEW v_cartelle_statistiche_complete AS
SELECT 
    -- Informazioni base cartella
    c.id,
    c.nome,
    c.parent_id,
    c.azienda_id,
    c.percorso_completo,
    c.descrizione,
    c.is_iso_structure,
    c.iso_standard_codice,
    c.iso_compliance_level,
    c.stato,
    c.hidden,
    c.data_creazione,
    c.ultima_modifica,
    
    -- Informazioni template ISO (se applicabile)
    dst.nome AS iso_template_nome,
    dst.livello AS iso_livello,
    dst.ordine_visualizzazione,
    dst.obbligatoria AS iso_obbligatoria,
    ds.nome AS standard_nome,
    ds.icona AS standard_icona,
    ds.colore AS standard_colore,
    
    -- Informazioni parent
    cp.nome AS parent_nome,
    cp.percorso_completo AS parent_percorso,
    
    -- Informazioni utente
    u.nome AS creato_da_nome,
    u.cognome AS creato_da_cognome,
    
    -- Statistiche sottocartelle
    COALESCE(stats_folders.numero_sottocartelle, 0) AS numero_sottocartelle,
    COALESCE(stats_folders.sottocartelle_iso, 0) AS sottocartelle_iso,
    
    -- Statistiche documenti
    COALESCE(stats_docs.numero_documenti, 0) AS numero_documenti,
    COALESCE(stats_docs.documenti_pubblicati, 0) AS documenti_pubblicati,
    COALESCE(stats_docs.documenti_bozza, 0) AS documenti_bozza,
    COALESCE(stats_docs.documenti_scaduti, 0) AS documenti_scaduti,
    COALESCE(stats_docs.dimensione_totale, 0) AS dimensione_totale_bytes,
    ROUND(COALESCE(stats_docs.dimensione_totale, 0) / 1024 / 1024, 2) AS dimensione_totale_mb,
    
    -- Statistiche compliance
    COALESCE(stats_compliance.documenti_conformi, 0) AS documenti_conformi,
    COALESCE(stats_compliance.documenti_non_conformi, 0) AS documenti_non_conformi,
    COALESCE(stats_compliance.punteggio_medio_conformita, 0) AS punteggio_medio_conformita,
    
    -- Indicatori
    CASE 
        WHEN stats_docs.numero_documenti > 0 THEN 
            ROUND(stats_compliance.documenti_conformi * 100.0 / stats_docs.numero_documenti, 2)
        ELSE 0
    END AS percentuale_conformita,
    
    stats_docs.numero_documenti > 0 AS ha_documenti,
    stats_folders.numero_sottocartelle > 0 AS ha_sottocartelle

FROM cartelle c
LEFT JOIN document_structure_templates dst ON dst.id = c.iso_template_id
LEFT JOIN document_standards ds ON ds.codice = c.iso_standard_codice
LEFT JOIN cartelle cp ON cp.id = c.parent_id
LEFT JOIN utenti u ON u.id = c.creato_da

-- Statistiche sottocartelle
LEFT JOIN (
    SELECT 
        parent_id,
        COUNT(*) as numero_sottocartelle,
        COUNT(CASE WHEN is_iso_structure = TRUE THEN 1 END) as sottocartelle_iso
    FROM cartelle
    WHERE stato != 'cestino'
    GROUP BY parent_id
) stats_folders ON stats_folders.parent_id = c.id

-- Statistiche documenti
LEFT JOIN (
    SELECT 
        cartella_id,
        COUNT(*) as numero_documenti,
        COUNT(CASE WHEN workflow_stato = 'pubblicato' THEN 1 END) as documenti_pubblicati,
        COUNT(CASE WHEN workflow_stato = 'bozza' THEN 1 END) as documenti_bozza,
        COUNT(CASE WHEN data_scadenza <= CURDATE() THEN 1 END) as documenti_scaduti,
        SUM(COALESCE(file_size, 0)) as dimensione_totale
    FROM documenti
    WHERE stato != 'cestino'
    GROUP BY cartella_id
) stats_docs ON stats_docs.cartella_id = c.id

-- Statistiche compliance
LEFT JOIN (
    SELECT 
        d.cartella_id,
        COUNT(CASE WHEN comp.stato_conformita = 'conforme' THEN 1 END) as documenti_conformi,
        COUNT(CASE WHEN comp.stato_conformita = 'non_conforme' THEN 1 END) as documenti_non_conformi,
        AVG(comp.punteggio_conformita) as punteggio_medio_conformita
    FROM documenti d
    LEFT JOIN (
        SELECT 
            dcc1.documento_id,
            dcc1.stato_conformita,
            dcc1.punteggio_conformita
        FROM document_compliance_checks dcc1
        WHERE dcc1.data_verifica = (
            SELECT MAX(dcc2.data_verifica)
            FROM document_compliance_checks dcc2
            WHERE dcc2.documento_id = dcc1.documento_id
        )
    ) comp ON comp.documento_id = d.id
    GROUP BY d.cartella_id
) stats_compliance ON stats_compliance.cartella_id = c.id;

-- =======================================================================================
-- 2. VISTE PER DASHBOARD AZIENDALI
-- =======================================================================================

-- Vista dashboard compliance per azienda
CREATE OR REPLACE VIEW v_dashboard_compliance_azienda AS
SELECT 
    a.id AS azienda_id,
    a.nome AS azienda_nome,
    
    -- Configurazioni documentali
    COUNT(DISTINCT cds.id) AS numero_configurazioni,
    COUNT(DISTINCT CASE WHEN cds.stato = 'attiva' THEN cds.id END) AS configurazioni_attive,
    
    -- Standard attivi
    GROUP_CONCAT(DISTINCT 
        CASE WHEN cds.stato = 'attiva' THEN
            JSON_UNQUOTE(JSON_EXTRACT(cds.standards_attivi, CONCAT('$[', numbers.n, ']')))
        END
        SEPARATOR ', '
    ) AS standards_attivi,
    
    -- Statistiche cartelle
    COUNT(DISTINCT c.id) AS numero_cartelle_totali,
    COUNT(DISTINCT CASE WHEN c.is_iso_structure = TRUE THEN c.id END) AS numero_cartelle_iso,
    COUNT(DISTINCT CASE WHEN c.stato = 'attiva' THEN c.id END) AS cartelle_attive,
    
    -- Statistiche documenti
    COUNT(DISTINCT d.id) AS numero_documenti_totali,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'pubblicato' THEN d.id END) AS documenti_pubblicati,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'bozza' THEN d.id END) AS documenti_bozza,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'in_revisione' THEN d.id END) AS documenti_in_revisione,
    COUNT(DISTINCT CASE WHEN d.data_scadenza <= CURDATE() THEN d.id END) AS documenti_scaduti,
    
    -- Statistiche compliance
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'conforme' THEN d.id END) AS documenti_conformi,
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'non_conforme' THEN d.id END) AS documenti_non_conformi,
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'parzialmente_conforme' THEN d.id END) AS documenti_parzialmente_conformi,
    COUNT(DISTINCT CASE WHEN comp.stato_conformita IS NULL OR comp.stato_conformita = 'da_verificare' THEN d.id END) AS documenti_da_verificare,
    
    -- Punteggi medi
    ROUND(AVG(comp.punteggio_conformita), 2) AS punteggio_medio_conformita,
    
    -- Percentuali
    CASE 
        WHEN COUNT(DISTINCT d.id) > 0 THEN
            ROUND(COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'conforme' THEN d.id END) * 100.0 / COUNT(DISTINCT d.id), 2)
        ELSE 0
    END AS percentuale_conformita,
    
    CASE 
        WHEN COUNT(DISTINCT d.id) > 0 THEN
            ROUND(COUNT(DISTINCT CASE WHEN d.workflow_stato = 'pubblicato' THEN d.id END) * 100.0 / COUNT(DISTINCT d.id), 2)
        ELSE 0
    END AS percentuale_pubblicati,
    
    -- Dimensioni
    COALESCE(SUM(d.file_size), 0) AS dimensione_totale_bytes,
    ROUND(COALESCE(SUM(d.file_size), 0) / 1024 / 1024, 2) AS dimensione_totale_mb,
    
    -- Date importanti
    MAX(d.ultima_modifica) AS ultima_modifica_documento,
    MAX(comp.data_verifica) AS ultima_verifica_conformita,
    MIN(CASE WHEN d.data_scadenza > CURDATE() THEN d.data_scadenza END) AS prossima_scadenza_documento

FROM aziende a
LEFT JOIN company_document_structures cds ON cds.azienda_id = a.id
LEFT JOIN cartelle c ON c.azienda_id = a.id
LEFT JOIN documenti d ON d.azienda_id = a.id
LEFT JOIN (
    SELECT 
        dcc1.documento_id,
        dcc1.stato_conformita,
        dcc1.punteggio_conformita,
        dcc1.data_verifica
    FROM document_compliance_checks dcc1
    WHERE dcc1.data_verifica = (
        SELECT MAX(dcc2.data_verifica)
        FROM document_compliance_checks dcc2
        WHERE dcc2.documento_id = dcc1.documento_id
    )
) comp ON comp.documento_id = d.id
CROSS JOIN (
    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
) numbers
WHERE a.stato = 'attiva'
GROUP BY a.id, a.nome;

-- Vista dettaglio per standard specifico
CREATE OR REPLACE VIEW v_dashboard_standard_dettaglio AS
SELECT 
    a.id AS azienda_id,
    a.nome AS azienda_nome,
    dsf.standard_codice,
    ds.nome AS standard_nome,
    ds.icona AS standard_icona,
    ds.colore AS standard_colore,
    cds.tipo_struttura,
    
    -- Statistiche cartelle per standard
    COUNT(DISTINCT dsf.id) AS numero_cartelle_standard,
    COUNT(DISTINCT CASE WHEN c.stato = 'attiva' THEN dsf.id END) AS cartelle_attive,
    
    -- Statistiche documenti per standard
    COUNT(DISTINCT d.id) AS numero_documenti_standard,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'pubblicato' THEN d.id END) AS documenti_pubblicati,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'bozza' THEN d.id END) AS documenti_bozza,
    
    -- Compliance per standard
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'conforme' THEN d.id END) AS documenti_conformi,
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'non_conforme' THEN d.id END) AS documenti_non_conformi,
    ROUND(AVG(comp.punteggio_conformita), 2) AS punteggio_medio,
    
    -- Percentuale conformità
    CASE 
        WHEN COUNT(DISTINCT d.id) > 0 THEN
            ROUND(COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'conforme' THEN d.id END) * 100.0 / COUNT(DISTINCT d.id), 2)
        ELSE 0
    END AS percentuale_conformita_standard,
    
    -- Template più utilizzati
    GROUP_CONCAT(DISTINCT 
        CONCAT(dst.nome, ' (', COUNT(DISTINCT d.id), ' docs)')
        ORDER BY COUNT(DISTINCT d.id) DESC
        SEPARATOR '; '
    ) AS template_utilizzo,
    
    -- Date
    MAX(d.ultima_modifica) AS ultima_modifica,
    MAX(comp.data_verifica) AS ultima_verifica,
    
    -- Stato complessivo
    CASE 
        WHEN COUNT(DISTINCT d.id) = 0 THEN 'Nessun documento'
        WHEN AVG(comp.punteggio_conformita) >= 90 THEN 'Eccellente'
        WHEN AVG(comp.punteggio_conformita) >= 70 THEN 'Buono'
        WHEN AVG(comp.punteggio_conformita) >= 50 THEN 'Sufficiente'
        ELSE 'Necessita miglioramenti'
    END AS stato_complessivo

FROM aziende a
JOIN company_document_structures cds ON cds.azienda_id = a.id
JOIN document_structure_folders dsf ON dsf.company_structure_id = cds.id
JOIN document_structure_templates dst ON dst.id = dsf.template_id
JOIN document_standards ds ON ds.id = dst.standard_id
LEFT JOIN cartelle c ON c.id = dsf.cartella_id
LEFT JOIN documenti d ON d.document_structure_folder_id = dsf.id
LEFT JOIN (
    SELECT 
        dcc1.documento_id,
        dcc1.standard_codice,
        dcc1.stato_conformita,
        dcc1.punteggio_conformita,
        dcc1.data_verifica
    FROM document_compliance_checks dcc1
    WHERE dcc1.data_verifica = (
        SELECT MAX(dcc2.data_verifica)
        FROM document_compliance_checks dcc2
        WHERE dcc2.documento_id = dcc1.documento_id
        AND dcc2.standard_codice = dcc1.standard_codice
    )
) comp ON comp.documento_id = d.id AND comp.standard_codice = dsf.standard_codice

WHERE a.stato = 'attiva' AND cds.stato = 'attiva'
GROUP BY a.id, dsf.standard_codice, ds.nome, ds.icona, ds.colore, cds.tipo_struttura;

-- =======================================================================================
-- 3. VISTE PER AUDIT E SICUREZZA
-- =======================================================================================

-- Vista audit trail con dettagli utente
CREATE OR REPLACE VIEW v_audit_trail_dettagliato AS
SELECT 
    dat.id,
    dat.documento_id,
    d.titolo AS documento_titolo,
    d.codice AS documento_codice,
    c.nome AS cartella_nome,
    dsf.standard_codice,
    
    dat.utente_id,
    CONCAT(u.nome, ' ', u.cognome) AS utente_nome_completo,
    u.email AS utente_email,
    u.ruolo AS utente_ruolo,
    
    dat.azione,
    dat.dettagli_azione,
    dat.valori_precedenti,
    dat.valori_nuovi,
    dat.ip_address,
    dat.user_agent,
    dat.session_id,
    dat.api_endpoint,
    dat.tempo_esecuzione,
    dat.risultato,
    dat.messaggio_errore,
    dat.data_azione,
    
    -- Categorizzazione azioni
    CASE 
        WHEN dat.azione IN ('create', 'update', 'delete') THEN 'Modifica'
        WHEN dat.azione IN ('read', 'download') THEN 'Accesso'
        WHEN dat.azione IN ('share', 'approve', 'reject') THEN 'Workflow'
        WHEN dat.azione IN ('archive', 'restore') THEN 'Gestione'
        ELSE 'Altro'
    END AS categoria_azione,
    
    -- Livello di importanza
    CASE 
        WHEN dat.azione IN ('delete', 'approve', 'reject') THEN 'Alta'
        WHEN dat.azione IN ('create', 'update', 'share') THEN 'Media'
        ELSE 'Bassa'
    END AS importanza,
    
    -- Indicatori di sicurezza
    CASE 
        WHEN dat.risultato = 'fallito' THEN TRUE
        WHEN dat.ip_address NOT LIKE '192.168.%' AND dat.ip_address NOT LIKE '10.%' AND dat.ip_address != '127.0.0.1' THEN TRUE
        ELSE FALSE
    END AS potenziale_anomalia

FROM document_audit_trail dat
LEFT JOIN documenti d ON d.id = dat.documento_id
LEFT JOIN cartelle c ON c.id = d.cartella_id
LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
LEFT JOIN utenti u ON u.id = dat.utente_id;

-- Vista documenti a rischio sicurezza
CREATE OR REPLACE VIEW v_documenti_rischio_sicurezza AS
SELECT 
    d.id,
    d.codice,
    d.titolo,
    d.azienda_id,
    a.nome AS azienda_nome,
    c.nome AS cartella_nome,
    dsf.standard_codice,
    
    -- Indicatori di rischio
    CASE WHEN d.virus_scan_status = 'infected' THEN 'CRITICO' 
         WHEN d.virus_scan_status = 'error' THEN 'ALTO'
         WHEN d.virus_scan_status = 'pending' THEN 'MEDIO'
         ELSE 'BASSO' 
    END AS livello_rischio_virus,
    
    CASE WHEN d.data_scadenza <= CURDATE() THEN 'ALTO'
         WHEN d.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'MEDIO'
         ELSE 'BASSO'
    END AS livello_rischio_scadenza,
    
    CASE WHEN stats_access.accessi_sospetti > 5 THEN 'ALTO'
         WHEN stats_access.accessi_sospetti > 0 THEN 'MEDIO'
         ELSE 'BASSO'
    END AS livello_rischio_accessi,
    
    -- Dettagli rischi
    d.virus_scan_status,
    d.virus_scan_date,
    d.data_scadenza,
    DATEDIFF(d.data_scadenza, CURDATE()) AS giorni_a_scadenza,
    
    -- Statistiche accessi
    COALESCE(stats_access.accessi_totali, 0) AS accessi_totali,
    COALESCE(stats_access.accessi_sospetti, 0) AS accessi_sospetti,
    COALESCE(stats_access.ultimo_accesso, d.ultima_modifica) AS ultimo_accesso,
    
    -- Responsabili
    CONCAT(u_resp.nome, ' ', u_resp.cognome) AS responsabile_nome,
    u_resp.email AS responsabile_email,
    
    -- Azioni raccomandate
    CASE 
        WHEN d.virus_scan_status = 'infected' THEN 'Quarantena immediata'
        WHEN d.virus_scan_status = 'error' THEN 'Ripetere scansione'
        WHEN d.data_scadenza <= CURDATE() THEN 'Aggiornare o rimuovere'
        WHEN stats_access.accessi_sospetti > 5 THEN 'Verificare accessi'
        ELSE 'Monitoraggio'
    END AS azione_raccomandata

FROM documenti d
LEFT JOIN aziende a ON a.id = d.azienda_id
LEFT JOIN cartelle c ON c.id = d.cartella_id
LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
LEFT JOIN utenti u_resp ON u_resp.id = d.responsabile_documento
LEFT JOIN (
    SELECT 
        documento_id,
        COUNT(*) as accessi_totali,
        COUNT(CASE WHEN risultato = 'fallito' THEN 1 END) as accessi_sospetti,
        MAX(data_azione) as ultimo_accesso
    FROM document_audit_trail
    WHERE azione IN ('read', 'download')
    AND data_azione >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY documento_id
) stats_access ON stats_access.documento_id = d.id

WHERE (
    d.virus_scan_status IN ('infected', 'error', 'pending') OR
    d.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
    stats_access.accessi_sospetti > 0
)
AND d.stato != 'cestino';

-- =======================================================================================
-- 4. VISTE PER KPI E METRICHE
-- =======================================================================================

-- Vista KPI mensili per azienda
CREATE OR REPLACE VIEW v_kpi_mensili_azienda AS
SELECT 
    a.id AS azienda_id,
    a.nome AS azienda_nome,
    YEAR(d.data_creazione) AS anno,
    MONTH(d.data_creazione) AS mese,
    DATE_FORMAT(d.data_creazione, '%Y-%m') AS periodo,
    
    -- KPI creazione documenti
    COUNT(DISTINCT d.id) AS documenti_creati,
    COUNT(DISTINCT CASE WHEN d.workflow_stato = 'pubblicato' THEN d.id END) AS documenti_pubblicati,
    SUM(COALESCE(d.file_size, 0)) AS bytes_creati,
    
    -- KPI per standard
    COUNT(DISTINCT CASE WHEN dsf.standard_codice = 'ISO9001' THEN d.id END) AS docs_iso9001,
    COUNT(DISTINCT CASE WHEN dsf.standard_codice = 'ISO14001' THEN d.id END) AS docs_iso14001,
    COUNT(DISTINCT CASE WHEN dsf.standard_codice = 'ISO45001' THEN d.id END) AS docs_iso45001,
    COUNT(DISTINCT CASE WHEN dsf.standard_codice = 'GDPR' THEN d.id END) AS docs_gdpr,
    
    -- KPI compliance
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'conforme' THEN d.id END) AS docs_conformi,
    COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'non_conforme' THEN d.id END) AS docs_non_conformi,
    AVG(comp.punteggio_conformita) AS punteggio_medio_compliance,
    
    -- KPI attività utenti
    COUNT(DISTINCT d.creato_da) AS utenti_attivi_creazione,
    COUNT(DISTINCT audit.utente_id) AS utenti_attivi_accesso,
    
    -- Percentuali
    CASE 
        WHEN COUNT(DISTINCT d.id) > 0 THEN
            ROUND(COUNT(DISTINCT CASE WHEN d.workflow_stato = 'pubblicato' THEN d.id END) * 100.0 / COUNT(DISTINCT d.id), 2)
        ELSE 0
    END AS percentuale_pubblicazione,
    
    CASE 
        WHEN COUNT(DISTINCT d.id) > 0 THEN
            ROUND(COUNT(DISTINCT CASE WHEN comp.stato_conformita = 'conforme' THEN d.id END) * 100.0 / COUNT(DISTINCT d.id), 2)
        ELSE 0
    END AS percentuale_conformita

FROM aziende a
LEFT JOIN documenti d ON d.azienda_id = a.id
LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
LEFT JOIN (
    SELECT 
        dcc1.documento_id,
        dcc1.stato_conformita,
        dcc1.punteggio_conformita
    FROM document_compliance_checks dcc1
    WHERE dcc1.data_verifica = (
        SELECT MAX(dcc2.data_verifica)
        FROM document_compliance_checks dcc2
        WHERE dcc2.documento_id = dcc1.documento_id
    )
) comp ON comp.documento_id = d.id
LEFT JOIN (
    SELECT DISTINCT documento_id, utente_id, YEAR(data_azione) as anno, MONTH(data_azione) as mese
    FROM document_audit_trail
    WHERE azione IN ('read', 'download', 'update')
) audit ON audit.documento_id = d.id 
    AND audit.anno = YEAR(d.data_creazione) 
    AND audit.mese = MONTH(d.data_creazione)

WHERE a.stato = 'attiva'
AND d.data_creazione >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY a.id, YEAR(d.data_creazione), MONTH(d.data_creazione)
ORDER BY a.nome, anno DESC, mese DESC;

-- =======================================================================================
-- MESSAGGIO FINALE
-- =======================================================================================

SELECT CONCAT(
    'Viste per reporting create con successo!\n\n',
    'Viste principali:\n',
    '- v_documenti_dettaglio_completo: Vista completa documenti\n',
    '- v_cartelle_statistiche_complete: Cartelle con statistiche\n',
    '- v_dashboard_compliance_azienda: Dashboard compliance per azienda\n',
    '- v_dashboard_standard_dettaglio: Dettaglio per standard\n',
    '- v_audit_trail_dettagliato: Audit trail con dettagli utente\n',
    '- v_documenti_rischio_sicurezza: Documenti a rischio\n',
    '- v_kpi_mensili_azienda: KPI mensili per azienda\n\n',
    'Le viste sono ottimizzate per dashboard e report di conformità.'
) AS viste_reporting_info;