-- =============================================
-- Script per creare automaticamente cartelle root per le aziende
-- Compatibile con MySQL 5.7+
-- =============================================

USE NexioSol;

-- =============================================
-- 1. TRIGGER per creare automaticamente una cartella root quando viene inserita una nuova azienda
-- =============================================

DELIMITER $$

DROP TRIGGER IF EXISTS after_azienda_insert$$

CREATE TRIGGER after_azienda_insert
AFTER INSERT ON aziende
FOR EACH ROW
BEGIN
    DECLARE cartella_nome VARCHAR(255);
    DECLARE percorso VARCHAR(1000);
    
    -- Usa il nome dell'azienda per la cartella
    SET cartella_nome = NEW.nome;
    
    -- Il percorso per la cartella root è solo il nome
    SET percorso = CONCAT('/', cartella_nome);
    
    -- Inserisci la cartella root per l'azienda
    INSERT INTO cartelle (
        nome,
        parent_id,
        percorso_completo,
        livello,
        azienda_id,
        creato_da,
        data_creazione,
        aggiornato_da,
        colore
    ) VALUES (
        cartella_nome,
        NULL,                   -- parent_id NULL per cartella root
        percorso,               -- percorso completo
        0,                      -- livello 0 per root
        NEW.id,                 -- ID dell'azienda appena creata
        1,                      -- creato_da = 1 (admin di sistema)
        NOW(),                  -- data creazione
        1,                      -- aggiornato_da = 1 (admin di sistema)
        '#3b82f6'              -- colore blu per cartelle root
    );
    
    -- Log dell'attività (se la tabella log_attivita esiste)
    IF EXISTS (SELECT 1 FROM information_schema.tables 
               WHERE table_schema = DATABASE() 
               AND table_name = 'log_attivita') THEN
        INSERT INTO log_attivita (
            utente_id,
            azienda_id,
            azione,
            entita_tipo,
            entita_id,
            dettagli,
            data_azione
        ) VALUES (
            1,                                          -- utente_id = 1 (admin di sistema)
            NEW.id,                                     -- azienda_id
            'cartella_root_creata',                     -- azione
            'cartelle',                                 -- entita_tipo
            LAST_INSERT_ID(),                          -- ID della cartella appena creata
            JSON_OBJECT(
                'nome_cartella', cartella_nome,
                'trigger_automatico', TRUE,
                'azienda_nome', NEW.nome
            ),
            NOW()
        );
    END IF;
    
END$$

DELIMITER ;

-- =============================================
-- 2. SCRIPT per creare cartelle root per aziende esistenti che non ce l'hanno
-- =============================================

-- Procedura per creare cartelle mancanti
DELIMITER $$

DROP PROCEDURE IF EXISTS crea_cartelle_root_mancanti$$

CREATE PROCEDURE crea_cartelle_root_mancanti()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_azienda_id INT;
    DECLARE v_azienda_nome VARCHAR(255);
    DECLARE v_cartelle_create INT DEFAULT 0;
    
    -- Cursor per aziende senza cartella root
    DECLARE cur_aziende CURSOR FOR
        SELECT a.id, a.nome
        FROM aziende a
        WHERE a.stato = 'attiva'
        AND NOT EXISTS (
            SELECT 1 
            FROM cartelle c 
            WHERE c.azienda_id = a.id 
            AND c.parent_id IS NULL
        );
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Apri il cursor
    OPEN cur_aziende;
    
    read_loop: LOOP
        FETCH cur_aziende INTO v_azienda_id, v_azienda_nome;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Crea la cartella root per questa azienda
        INSERT INTO cartelle (
            nome,
            parent_id,
            percorso_completo,
            livello,
            azienda_id,
            creato_da,
            data_creazione,
            aggiornato_da,
            colore
        ) VALUES (
            v_azienda_nome,
            NULL,
            CONCAT('/', v_azienda_nome),
            0,
            v_azienda_id,
            1,                      -- admin di sistema
            NOW(),
            1,                      -- admin di sistema
            '#3b82f6'              -- colore blu per cartelle root
        );
        
        SET v_cartelle_create = v_cartelle_create + 1;
        
        -- Log dell'attività
        IF EXISTS (SELECT 1 FROM information_schema.tables 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'log_attivita') THEN
            INSERT INTO log_attivita (
                utente_id,
                azienda_id,
                azione,
                entita_tipo,
                entita_id,
                dettagli,
                data_azione
            ) VALUES (
                1,
                v_azienda_id,
                'cartella_root_creata_script',
                'cartelle',
                LAST_INSERT_ID(),
                JSON_OBJECT(
                    'nome_cartella', v_azienda_nome,
                    'script_batch', TRUE,
                    'azienda_nome', v_azienda_nome
                ),
                NOW()
            );
        END IF;
        
    END LOOP;
    
    -- Chiudi il cursor
    CLOSE cur_aziende;
    
    -- Mostra il risultato
    SELECT CONCAT('Cartelle root create: ', v_cartelle_create) AS risultato;
    
END$$

DELIMITER ;

-- =============================================
-- 3. ESEGUI la procedura per creare le cartelle mancanti
-- =============================================

CALL crea_cartelle_root_mancanti();

-- =============================================
-- 4. VERIFICA: Query per controllare lo stato delle cartelle root
-- =============================================

-- Mostra aziende CON cartella root
SELECT 
    a.id AS azienda_id,
    a.nome AS azienda_nome,
    c.id AS cartella_id,
    c.nome AS cartella_nome,
    c.percorso_completo,
    c.data_creazione
FROM aziende a
LEFT JOIN cartelle c ON a.id = c.azienda_id AND c.parent_id IS NULL
WHERE a.stato = 'attiva'
ORDER BY a.id;

-- Conta aziende senza cartella root (dovrebbe essere 0 dopo l'esecuzione)
SELECT COUNT(*) AS aziende_senza_cartella_root
FROM aziende a
WHERE a.stato = 'attiva'
AND NOT EXISTS (
    SELECT 1 
    FROM cartelle c 
    WHERE c.azienda_id = a.id 
    AND c.parent_id IS NULL
);

-- =============================================
-- 5. UTILITY: Procedura per ricreare la cartella root di un'azienda specifica
-- =============================================

DELIMITER $$

DROP PROCEDURE IF EXISTS crea_cartella_root_per_azienda$$

CREATE PROCEDURE crea_cartella_root_per_azienda(IN p_azienda_id INT)
BEGIN
    DECLARE v_azienda_nome VARCHAR(255);
    DECLARE v_esiste_cartella INT;
    
    -- Verifica se l'azienda esiste
    SELECT nome INTO v_azienda_nome
    FROM aziende 
    WHERE id = p_azienda_id AND stato = 'attiva'
    LIMIT 1;
    
    IF v_azienda_nome IS NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Azienda non trovata o non attiva';
    END IF;
    
    -- Verifica se esiste già una cartella root
    SELECT COUNT(*) INTO v_esiste_cartella
    FROM cartelle
    WHERE azienda_id = p_azienda_id AND parent_id IS NULL;
    
    IF v_esiste_cartella > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Esiste già una cartella root per questa azienda';
    END IF;
    
    -- Crea la cartella root
    INSERT INTO cartelle (
        nome,
        parent_id,
        percorso_completo,
        livello,
        azienda_id,
        creato_da,
        data_creazione,
        aggiornato_da,
        colore
    ) VALUES (
        v_azienda_nome,
        NULL,
        CONCAT('/', v_azienda_nome),
        0,
        p_azienda_id,
        1,
        NOW(),
        1,
        '#3b82f6'
    );
    
    SELECT 'Cartella root creata con successo' AS risultato;
    
END$$

DELIMITER ;

-- =============================================
-- ESEMPIO D'USO:
-- Per creare manualmente la cartella root per l'azienda con ID 5:
-- CALL crea_cartella_root_per_azienda(5);
-- =============================================