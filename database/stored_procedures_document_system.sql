-- STORED PROCEDURES PER SISTEMA DOCUMENTALE MULTI-STANDARD
-- Procedure avanzate per gestione documenti, conformità e performance
-- Versione: 3.0 - Gennaio 2025

USE NexioSol;

DELIMITER //

-- =======================================================================================
-- 1. PROCEDURE PER GESTIONE STRUTTURE DOCUMENTALI
-- =======================================================================================

-- Procedura per implementare struttura documentale ISO per un'azienda
CREATE PROCEDURE IF NOT EXISTS sp_deploy_iso_structure(
    IN p_azienda_id INT,
    IN p_standard_codice VARCHAR(20),
    IN p_tipo_struttura ENUM('separata', 'integrata', 'personalizzata'),
    IN p_utente_id INT,
    OUT p_result JSON
)
BEGIN
    DECLARE v_error_count INT DEFAULT 0;
    DECLARE v_config_id INT;
    DECLARE v_standard_id INT;
    DECLARE v_folders_created INT DEFAULT 0;
    DECLARE v_start_time TIMESTAMP DEFAULT NOW();
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @error_code = MYSQL_ERRNO,
            @error_message = MESSAGE_TEXT;
        SET p_result = JSON_OBJECT(
            'success', FALSE,
            'error_code', @error_code,
            'error_message', @error_message,
            'folders_created', v_folders_created
        );
    END;

    START TRANSACTION;
    
    -- Verifica che lo standard esista
    SELECT id INTO v_standard_id 
    FROM document_standards 
    WHERE codice = p_standard_codice AND attivo = TRUE;
    
    IF v_standard_id IS NULL THEN
        SET p_result = JSON_OBJECT('success', FALSE, 'error', 'Standard non trovato o non attivo');
        ROLLBACK;
        LEAVE sp_deploy_iso_structure;
    END IF;
    
    -- Crea o aggiorna configurazione azienda
    INSERT INTO company_document_structures (
        azienda_id, 
        nome_configurazione, 
        tipo_struttura, 
        standards_attivi, 
        stato,
        default_struttura,
        creato_da
    ) VALUES (
        p_azienda_id,
        CONCAT('Struttura ', p_standard_codice, ' - ', p_tipo_struttura),
        p_tipo_struttura,
        JSON_ARRAY(p_standard_codice),
        'attiva',
        NOT EXISTS(SELECT 1 FROM company_document_structures WHERE azienda_id = p_azienda_id AND default_struttura = TRUE),
        p_utente_id
    ) ON DUPLICATE KEY UPDATE
        standards_attivi = JSON_ARRAY_APPEND(standards_attivi, '$', p_standard_codice),
        ultima_modifica = NOW(),
        modificato_da = p_utente_id;
    
    SET v_config_id = LAST_INSERT_ID();
    
    -- Crea cartelle basate sui template
    INSERT INTO cartelle (nome, parent_id, azienda_id, percorso_completo, is_iso_structure, iso_standard_codice, iso_template_id, iso_compliance_level, creato_da)
    SELECT 
        dst.nome,
        CASE 
            WHEN dst.parent_template_id IS NULL THEN NULL
            ELSE (SELECT c.id FROM cartelle c JOIN document_structure_templates pdst ON c.iso_template_id = pdst.id WHERE pdst.id = dst.parent_template_id AND c.azienda_id = p_azienda_id LIMIT 1)
        END,
        p_azienda_id,
        CASE 
            WHEN dst.parent_template_id IS NULL THEN dst.nome
            ELSE CONCAT((SELECT c.percorso_completo FROM cartelle c JOIN document_structure_templates pdst ON c.iso_template_id = pdst.id WHERE pdst.id = dst.parent_template_id AND c.azienda_id = p_azienda_id LIMIT 1), '/', dst.nome)
        END,
        TRUE,
        p_standard_codice,
        dst.id,
        CASE WHEN dst.obbligatoria THEN 'obbligatoria' ELSE 'raccomandata' END,
        p_utente_id
    FROM document_structure_templates dst
    WHERE dst.standard_id = v_standard_id
    AND NOT EXISTS (
        SELECT 1 FROM cartelle c 
        WHERE c.azienda_id = p_azienda_id 
        AND c.iso_template_id = dst.id
    )
    ORDER BY dst.livello, dst.ordine_visualizzazione;
    
    SET v_folders_created = ROW_COUNT();
    
    -- Crea collegamenti nella tabella document_structure_folders
    INSERT INTO document_structure_folders (
        company_structure_id,
        template_id,
        cartella_id,
        standard_codice,
        percorso_iso,
        stato
    )
    SELECT 
        v_config_id,
        c.iso_template_id,
        c.id,
        p_standard_codice,
        c.percorso_completo,
        'attiva'
    FROM cartelle c
    WHERE c.azienda_id = p_azienda_id 
    AND c.iso_standard_codice = p_standard_codice
    AND c.iso_template_id IS NOT NULL;
    
    -- Log dell'operazione
    INSERT INTO iso_deployment_log (
        azienda_id,
        operazione,
        standard_coinvolti,
        dettagli_operazione,
        risultato,
        tempo_esecuzione_secondi,
        eseguito_da
    ) VALUES (
        p_azienda_id,
        'deploy_iso_structure',
        JSON_ARRAY(p_standard_codice),
        JSON_OBJECT(
            'tipo_struttura', p_tipo_struttura,
            'folders_created', v_folders_created,
            'config_id', v_config_id
        ),
        'successo',
        TIMESTAMPDIFF(MICROSECOND, v_start_time, NOW()) / 1000000,
        p_utente_id
    );
    
    COMMIT;
    
    SET p_result = JSON_OBJECT(
        'success', TRUE,
        'config_id', v_config_id,
        'folders_created', v_folders_created,
        'execution_time', TIMESTAMPDIFF(MICROSECOND, v_start_time, NOW()) / 1000000
    );
    
END //

-- =======================================================================================
-- 2. PROCEDURE PER GESTIONE DOCUMENTI
-- =======================================================================================

-- Procedura per creare nuova versione documento
CREATE PROCEDURE IF NOT EXISTS sp_create_document_version(
    IN p_documento_id INT,
    IN p_file_path VARCHAR(500),
    IN p_file_size BIGINT,
    IN p_checksum VARCHAR(64),
    IN p_note_versione TEXT,
    IN p_motivo_modifica TEXT,
    IN p_utente_id INT,
    OUT p_version_id INT,
    OUT p_result JSON
)
BEGIN
    DECLARE v_current_version INT DEFAULT 1;
    DECLARE v_current_revision INT DEFAULT 1;
    DECLARE v_documento_exists BOOLEAN DEFAULT FALSE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @error_code = MYSQL_ERRNO,
            @error_message = MESSAGE_TEXT;
        SET p_result = JSON_OBJECT(
            'success', FALSE,
            'error_code', @error_code,
            'error_message', @error_message
        );
    END;

    START TRANSACTION;
    
    -- Verifica esistenza documento
    SELECT COUNT(*) > 0 INTO v_documento_exists
    FROM documenti 
    WHERE id = p_documento_id;
    
    IF NOT v_documento_exists THEN
        SET p_result = JSON_OBJECT('success', FALSE, 'error', 'Documento non trovato');
        ROLLBACK;
        LEAVE sp_create_document_version;
    END IF;
    
    -- Ottieni versione e revisione correnti
    SELECT 
        COALESCE(MAX(numero_versione), 0) + 1,
        1
    INTO v_current_version, v_current_revision
    FROM document_versions 
    WHERE documento_id = p_documento_id;
    
    -- Crea nuova versione
    INSERT INTO document_versions (
        documento_id,
        numero_versione,
        numero_revisione,
        file_path,
        file_size,
        checksum_file,
        note_versione,
        motivo_modifica,
        stato_approvazione,
        creato_da
    ) VALUES (
        p_documento_id,
        v_current_version,
        v_current_revision,
        p_file_path,
        p_file_size,
        p_checksum,
        p_note_versione,
        p_motivo_modifica,
        'in_attesa',
        p_utente_id
    );
    
    SET p_version_id = LAST_INSERT_ID();
    
    -- Aggiorna documento principale
    UPDATE documenti 
    SET 
        file_path = p_file_path,
        file_size = p_file_size,
        checksum_file = p_checksum,
        versione = v_current_version,
        numero_revisione = v_current_revision,
        motivo_revisione = p_motivo_modifica,
        modificato_da = p_utente_id,
        ultima_modifica = NOW()
    WHERE id = p_documento_id;
    
    -- Log audit trail
    INSERT INTO document_audit_trail (
        documento_id,
        utente_id,
        azione,
        dettagli_azione,
        risultato
    ) VALUES (
        p_documento_id,
        p_utente_id,
        'update',
        JSON_OBJECT(
            'new_version', v_current_version,
            'new_revision', v_current_revision,
            'file_size', p_file_size,
            'checksum', p_checksum,
            'motivo', p_motivo_modifica
        ),
        'successo'
    );
    
    COMMIT;
    
    SET p_result = JSON_OBJECT(
        'success', TRUE,
        'version_id', p_version_id,
        'version_number', v_current_version,
        'revision_number', v_current_revision
    );
    
END //

-- =======================================================================================
-- 3. PROCEDURE PER COMPLIANCE E VERIFICHE
-- =======================================================================================

-- Procedura per verifica conformità documento
CREATE PROCEDURE IF NOT EXISTS sp_check_document_compliance(
    IN p_documento_id INT,
    IN p_standard_codice VARCHAR(20),
    IN p_verificato_da INT,
    OUT p_result JSON
)
BEGIN
    DECLARE v_punteggio DECIMAL(5,2) DEFAULT 0.00;
    DECLARE v_stato_conformita ENUM('conforme', 'non_conforme', 'parzialmente_conforme', 'da_verificare') DEFAULT 'da_verificare';
    DECLARE v_requisiti_verificati JSON DEFAULT JSON_ARRAY();
    DECLARE v_non_conformita JSON DEFAULT JSON_ARRAY();
    DECLARE v_documento_exists BOOLEAN DEFAULT FALSE;
    DECLARE v_has_metadata BOOLEAN DEFAULT FALSE;
    DECLARE v_has_responsabile BOOLEAN DEFAULT FALSE;
    DECLARE v_is_approved BOOLEAN DEFAULT FALSE;
    DECLARE v_file_exists BOOLEAN DEFAULT FALSE;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @error_code = MYSQL_ERRNO,
            @error_message = MESSAGE_TEXT;
        SET p_result = JSON_OBJECT(
            'success', FALSE,
            'error_code', @error_code,
            'error_message', @error_message
        );
    END;

    START TRANSACTION;
    
    -- Verifica esistenza documento
    SELECT 
        COUNT(*) > 0,
        metadata_compliance IS NOT NULL AND JSON_LENGTH(metadata_compliance) > 0,
        responsabile_documento IS NOT NULL,
        workflow_stato IN ('approvato', 'pubblicato'),
        file_path IS NOT NULL AND file_path != ''
    INTO v_documento_exists, v_has_metadata, v_has_responsabile, v_is_approved, v_file_exists
    FROM documenti 
    WHERE id = p_documento_id;
    
    IF NOT v_documento_exists THEN
        SET p_result = JSON_OBJECT('success', FALSE, 'error', 'Documento non trovato');
        ROLLBACK;
        LEAVE sp_check_document_compliance;
    END IF;
    
    -- Calcola punteggio conformità basato su criteri standard
    SET v_punteggio = 0;
    
    -- Criteri di base (20 punti ciascuno)
    IF v_has_metadata THEN
        SET v_punteggio = v_punteggio + 20;
        SET v_requisiti_verificati = JSON_ARRAY_APPEND(v_requisiti_verificati, '$', 'metadata_present');
    ELSE
        SET v_non_conformita = JSON_ARRAY_APPEND(v_non_conformita, '$', 'missing_metadata');
    END IF;
    
    IF v_has_responsabile THEN
        SET v_punteggio = v_punteggio + 20;
        SET v_requisiti_verificati = JSON_ARRAY_APPEND(v_requisiti_verificati, '$', 'responsabile_assigned');
    ELSE
        SET v_non_conformita = JSON_ARRAY_APPEND(v_non_conformita, '$', 'missing_responsabile');
    END IF;
    
    IF v_is_approved THEN
        SET v_punteggio = v_punteggio + 30;
        SET v_requisiti_verificati = JSON_ARRAY_APPEND(v_requisiti_verificati, '$', 'document_approved');
    ELSE
        SET v_non_conformita = JSON_ARRAY_APPEND(v_non_conformita, '$', 'not_approved');
    END IF;
    
    IF v_file_exists THEN
        SET v_punteggio = v_punteggio + 20;
        SET v_requisiti_verificati = JSON_ARRAY_APPEND(v_requisiti_verificati, '$', 'file_present');
    ELSE
        SET v_non_conformita = JSON_ARRAY_APPEND(v_non_conformita, '$', 'missing_file');
    END IF;
    
    -- Verifica versioning (10 punti)
    IF EXISTS(SELECT 1 FROM document_versions WHERE documento_id = p_documento_id) THEN
        SET v_punteggio = v_punteggio + 10;
        SET v_requisiti_verificati = JSON_ARRAY_APPEND(v_requisiti_verificati, '$', 'versioning_enabled');
    ELSE
        SET v_non_conformita = JSON_ARRAY_APPEND(v_non_conformita, '$', 'no_version_control');
    END IF;
    
    -- Determina stato conformità
    CASE 
        WHEN v_punteggio >= 90 THEN SET v_stato_conformita = 'conforme';
        WHEN v_punteggio >= 70 THEN SET v_stato_conformita = 'parzialmente_conforme';
        WHEN v_punteggio > 0 THEN SET v_stato_conformita = 'non_conforme';
        ELSE SET v_stato_conformita = 'da_verificare';
    END CASE;
    
    -- Inserisci risultato verifica
    INSERT INTO document_compliance_checks (
        documento_id,
        standard_codice,
        tipo_verifica,
        stato_conformita,
        punteggio_conformita,
        requisiti_verificati,
        non_conformita_trovate,
        verificato_da,
        data_prossima_verifica
    ) VALUES (
        p_documento_id,
        p_standard_codice,
        'automatica',
        v_stato_conformita,
        v_punteggio,
        v_requisiti_verificati,
        v_non_conformita,
        p_verificato_da,
        DATE_ADD(CURDATE(), INTERVAL 6 MONTH) -- Prossima verifica in 6 mesi
    );
    
    -- Log audit trail
    INSERT INTO document_audit_trail (
        documento_id,
        utente_id,
        azione,
        dettagli_azione,
        risultato
    ) VALUES (
        p_documento_id,
        p_verificato_da,
        'compliance_check',
        JSON_OBJECT(
            'standard', p_standard_codice,
            'punteggio', v_punteggio,
            'stato', v_stato_conformita,
            'requisiti_ok', JSON_LENGTH(v_requisiti_verificati),
            'non_conformita', JSON_LENGTH(v_non_conformita)
        ),
        'successo'
    );
    
    COMMIT;
    
    SET p_result = JSON_OBJECT(
        'success', TRUE,
        'punteggio_conformita', v_punteggio,
        'stato_conformita', v_stato_conformita,
        'requisiti_verificati', v_requisiti_verificati,
        'non_conformita', v_non_conformita
    );
    
END //

-- =======================================================================================
-- 4. PROCEDURE PER RICERCA E INDICIZZAZIONE
-- =======================================================================================

-- Procedura per ricerca full-text avanzata
CREATE PROCEDURE IF NOT EXISTS sp_advanced_document_search(
    IN p_query VARCHAR(500),
    IN p_azienda_id INT,
    IN p_standard_codice VARCHAR(20),
    IN p_limit INT,
    IN p_offset INT,
    OUT p_result JSON
)
BEGIN
    DECLARE v_total_results INT DEFAULT 0;
    DECLARE v_search_results JSON DEFAULT JSON_ARRAY();
    DECLARE v_search_time DECIMAL(10,6);
    DECLARE v_start_time TIMESTAMP(6) DEFAULT NOW(6);
    
    -- Conta risultati totali
    SELECT COUNT(DISTINCT d.id) INTO v_total_results
    FROM documenti d
    JOIN document_search_index dsi ON dsi.documento_id = d.id
    LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
    WHERE d.azienda_id = p_azienda_id
    AND (p_standard_codice IS NULL OR dsf.standard_codice = p_standard_codice)
    AND (
        MATCH(dsi.full_tokens) AGAINST(p_query IN NATURAL LANGUAGE MODE)
        OR d.titolo LIKE CONCAT('%', p_query, '%')
        OR JSON_SEARCH(d.tags_documenti, 'one', CONCAT('%', p_query, '%')) IS NOT NULL
    );
    
    -- Ottieni risultati con ranking
    SELECT JSON_ARRAYAGG(
        JSON_OBJECT(
            'id', search_results.id,
            'titolo', search_results.titolo,
            'cartella_nome', search_results.cartella_nome,
            'standard_codice', search_results.standard_codice,
            'relevance_score', search_results.relevance_score,
            'data_modifica', search_results.ultima_modifica,
            'responsabile', search_results.responsabile_nome,
            'workflow_stato', search_results.workflow_stato
        )
    ) INTO v_search_results
    FROM (
        SELECT 
            d.id,
            d.titolo,
            c.nome as cartella_nome,
            COALESCE(dsf.standard_codice, 'Nessuno') as standard_codice,
            (
                MATCH(dsi.full_tokens) AGAINST(p_query IN NATURAL LANGUAGE MODE) * dsi.peso_rilevanza +
                CASE WHEN d.titolo LIKE CONCAT('%', p_query, '%') THEN 0.5 ELSE 0 END +
                CASE WHEN JSON_SEARCH(d.tags_documenti, 'one', CONCAT('%', p_query, '%')) IS NOT NULL THEN 0.3 ELSE 0 END
            ) as relevance_score,
            d.ultima_modifica,
            CONCAT(COALESCE(u.nome, ''), ' ', COALESCE(u.cognome, '')) as responsabile_nome,
            d.workflow_stato
        FROM documenti d
        JOIN document_search_index dsi ON dsi.documento_id = d.id
        LEFT JOIN cartelle c ON c.id = d.cartella_id
        LEFT JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
        LEFT JOIN utenti u ON u.id = d.responsabile_documento
        WHERE d.azienda_id = p_azienda_id
        AND (p_standard_codice IS NULL OR dsf.standard_codice = p_standard_codice)
        AND (
            MATCH(dsi.full_tokens) AGAINST(p_query IN NATURAL LANGUAGE MODE)
            OR d.titolo LIKE CONCAT('%', p_query, '%')
            OR JSON_SEARCH(d.tags_documenti, 'one', CONCAT('%', p_query, '%')) IS NOT NULL
        )
        ORDER BY relevance_score DESC, d.ultima_modifica DESC
        LIMIT p_limit OFFSET p_offset
    ) as search_results;
    
    SET v_search_time = TIMESTAMPDIFF(MICROSECOND, v_start_time, NOW(6)) / 1000000;
    
    -- Log ricerca per analytics
    INSERT INTO search_history (
        utente_id,
        query_ricerca,
        filtri,
        num_risultati,
        tempo_esecuzione,
        azienda_id
    ) VALUES (
        NULL, -- Da implementare se necessario
        p_query,
        JSON_OBJECT('standard_codice', p_standard_codice, 'limit', p_limit),
        v_total_results,
        v_search_time,
        p_azienda_id
    );
    
    SET p_result = JSON_OBJECT(
        'success', TRUE,
        'total_results', v_total_results,
        'search_time', v_search_time,
        'results', COALESCE(v_search_results, JSON_ARRAY())
    );
    
END //

-- =======================================================================================
-- 5. PROCEDURE DI MANUTENZIONE
-- =======================================================================================

-- Procedura per cleanup automatico
CREATE PROCEDURE IF NOT EXISTS sp_cleanup_document_system(
    IN p_days_old INT,
    OUT p_result JSON
)
BEGIN
    DECLARE v_deleted_audit INT DEFAULT 0;
    DECLARE v_deleted_search INT DEFAULT 0;
    DECLARE v_optimized_tables INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @error_code = MYSQL_ERRNO,
            @error_message = MESSAGE_TEXT;
        SET p_result = JSON_OBJECT(
            'success', FALSE,
            'error_code', @error_code,
            'error_message', @error_message
        );
    END;

    START TRANSACTION;
    
    -- Pulisci audit trail vecchio (solo read/download)
    DELETE FROM document_audit_trail 
    WHERE data_azione < DATE_SUB(NOW(), INTERVAL p_days_old DAY)
    AND azione IN ('read', 'download');
    SET v_deleted_audit = ROW_COUNT();
    
    -- Pulisci search history vecchia
    DELETE FROM search_history 
    WHERE data_ricerca < DATE_SUB(NOW(), INTERVAL p_days_old DAY);
    SET v_deleted_search = ROW_COUNT();
    
    -- Ricostruisci indici di ricerca per documenti modificati
    INSERT INTO document_search_index (
        documento_id, titolo_tokens, contenuto_tokens, full_tokens, peso_rilevanza
    )
    SELECT 
        d.id,
        d.titolo,
        COALESCE(LEFT(d.full_text_content, 5000), ''),
        CONCAT(d.titolo, ' ', COALESCE(LEFT(d.full_text_content, 5000), '')),
        CASE WHEN d.workflow_stato = 'pubblicato' THEN 1.00 ELSE 0.70 END
    FROM documenti d
    WHERE d.ultima_modifica > (
        SELECT COALESCE(MAX(ultima_modifica), '1970-01-01') 
        FROM document_search_index dsi 
        WHERE dsi.documento_id = d.id
    )
    ON DUPLICATE KEY UPDATE
        titolo_tokens = VALUES(titolo_tokens),
        contenuto_tokens = VALUES(contenuto_tokens),
        full_tokens = VALUES(full_tokens),
        peso_rilevanza = VALUES(peso_rilevanza),
        ultima_modifica = NOW();
    
    -- Ottimizza tabelle principali
    SET @sql = 'OPTIMIZE TABLE documenti, cartelle, document_search_index, document_audit_trail';
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SET v_optimized_tables = 4;
    
    COMMIT;
    
    SET p_result = JSON_OBJECT(
        'success', TRUE,
        'deleted_audit_records', v_deleted_audit,
        'deleted_search_records', v_deleted_search,
        'optimized_tables', v_optimized_tables
    );
    
END //

-- =======================================================================================
-- 6. PROCEDURE PER STATISTICS E REPORTING
-- =======================================================================================

-- Procedura per statistiche compliance azienda
CREATE PROCEDURE IF NOT EXISTS sp_get_compliance_statistics(
    IN p_azienda_id INT,
    IN p_date_from DATE,
    IN p_date_to DATE,
    OUT p_result JSON
)
BEGIN
    DECLARE v_total_docs INT DEFAULT 0;
    DECLARE v_conformi INT DEFAULT 0;
    DECLARE v_non_conformi INT DEFAULT 0;
    DECLARE v_parziali INT DEFAULT 0;
    DECLARE v_da_verificare INT DEFAULT 0;
    DECLARE v_avg_score DECIMAL(5,2) DEFAULT 0;
    DECLARE v_standards_stats JSON DEFAULT JSON_OBJECT();
    
    -- Statistiche generali
    SELECT 
        COUNT(DISTINCT d.id),
        COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'conforme' THEN d.id END),
        COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'non_conforme' THEN d.id END),
        COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'parzialmente_conforme' THEN d.id END),
        COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'da_verificare' OR dcc.id IS NULL THEN d.id END),
        COALESCE(AVG(dcc.punteggio_conformita), 0)
    INTO v_total_docs, v_conformi, v_non_conformi, v_parziali, v_da_verificare, v_avg_score
    FROM documenti d
    LEFT JOIN document_compliance_checks dcc ON dcc.documento_id = d.id 
        AND dcc.data_verifica BETWEEN p_date_from AND p_date_to
    WHERE d.azienda_id = p_azienda_id;
    
    -- Statistiche per standard
    SELECT JSON_OBJECTAGG(
        standard_codice,
        JSON_OBJECT(
            'total_documents', total_docs,
            'conformi', conformi_count,
            'non_conformi', non_conformi_count,
            'parziali', parziali_count,
            'average_score', avg_score,
            'last_check', last_check_date
        )
    ) INTO v_standards_stats
    FROM (
        SELECT 
            dsf.standard_codice,
            COUNT(DISTINCT d.id) as total_docs,
            COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'conforme' THEN d.id END) as conformi_count,
            COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'non_conforme' THEN d.id END) as non_conformi_count,
            COUNT(DISTINCT CASE WHEN dcc.stato_conformita = 'parzialmente_conforme' THEN d.id END) as parziali_count,
            COALESCE(AVG(dcc.punteggio_conformita), 0) as avg_score,
            MAX(dcc.data_verifica) as last_check_date
        FROM documenti d
        JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
        LEFT JOIN document_compliance_checks dcc ON dcc.documento_id = d.id 
            AND dcc.data_verifica BETWEEN p_date_from AND p_date_to
        WHERE d.azienda_id = p_azienda_id
        GROUP BY dsf.standard_codice
    ) stats;
    
    SET p_result = JSON_OBJECT(
        'success', TRUE,
        'period', JSON_OBJECT('from', p_date_from, 'to', p_date_to),
        'summary', JSON_OBJECT(
            'total_documents', v_total_docs,
            'conformi', v_conformi,
            'non_conformi', v_non_conformi,
            'parzialmente_conformi', v_parziali,
            'da_verificare', v_da_verificare,
            'average_score', v_avg_score,
            'compliance_rate', CASE WHEN v_total_docs > 0 THEN (v_conformi + v_parziali) * 100.0 / v_total_docs ELSE 0 END
        ),
        'by_standard', COALESCE(v_standards_stats, JSON_OBJECT())
    );
    
END //

DELIMITER ;

-- =======================================================================================
-- 7. EVENTI AUTOMATICI PER MANUTENZIONE
-- =======================================================================================

-- Evento per cleanup automatico notturno (se supportato)
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS ev_nightly_cleanup
ON SCHEDULE EVERY 1 DAY STARTS '02:00:00'
DO
BEGIN
    DECLARE cleanup_result JSON;
    CALL sp_cleanup_document_system(90, cleanup_result);
    
    INSERT INTO migration_log (step_name, status, error_message) 
    VALUES ('Nightly cleanup', 'completed', JSON_EXTRACT(cleanup_result, '$.success'));
END;

-- Evento per aggiornamento statistiche settimanale
CREATE EVENT IF NOT EXISTS ev_weekly_stats_update  
ON SCHEDULE EVERY 1 WEEK STARTS '2025-01-01 01:00:00'
DO
BEGIN
    -- Aggiorna cache statistiche nelle configurazioni azienda
    UPDATE company_document_structures cds
    SET personalizzazioni = JSON_SET(
        COALESCE(personalizzazioni, JSON_OBJECT()),
        '$.cached_stats',
        JSON_OBJECT(
            'total_folders', (
                SELECT COUNT(*) FROM document_structure_folders dsf 
                WHERE dsf.company_structure_id = cds.id AND dsf.stato = 'attiva'
            ),
            'total_documents', (
                SELECT COUNT(*) FROM documenti d 
                JOIN document_structure_folders dsf ON dsf.id = d.document_structure_folder_id
                WHERE dsf.company_structure_id = cds.id
            ),
            'last_update', NOW()
        )
    )
    WHERE stato = 'attiva';
END;

-- =======================================================================================
-- MESSAGGIO FINALE
-- =======================================================================================

SELECT CONCAT(
    'Stored Procedures create con successo!\n',
    'Procedure disponibili:\n',
    '- sp_deploy_iso_structure: Implementa strutture ISO\n',
    '- sp_create_document_version: Gestione versioni documenti\n', 
    '- sp_check_document_compliance: Verifica conformità\n',
    '- sp_advanced_document_search: Ricerca avanzata\n',
    '- sp_cleanup_document_system: Pulizia automatica\n',
    '- sp_get_compliance_statistics: Statistiche conformità\n',
    'Eventi automatici configurati per manutenzione notturna e settimanale.'
) AS stored_procedures_info;