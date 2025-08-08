-- =====================================================
-- ANALISI E FIX CARTELLE - DATABASE NexioSol
-- =====================================================

-- 1. ANALISI STRUTTURA TABELLA CARTELLE
-- -----------------------------------------------------
DESCRIBE cartelle;

-- Mostra tutti gli indici
SHOW INDEX FROM cartelle;

-- Mostra le foreign key
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'NexioSol' 
    AND TABLE_NAME = 'cartelle'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- 2. VERIFICA CARTELLE DUPLICATE
-- -----------------------------------------------------
-- Trova cartelle con stesso nome, parent_id e azienda_id
SELECT 
    nome, 
    parent_id, 
    azienda_id,
    COUNT(*) as duplicati,
    GROUP_CONCAT(id) as ids
FROM cartelle
GROUP BY nome, parent_id, azienda_id
HAVING COUNT(*) > 1;

-- 3. VERIFICA DIPENDENZE
-- -----------------------------------------------------
-- Tabelle che referenziano cartelle
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'NexioSol' 
    AND REFERENCED_TABLE_NAME = 'cartelle'
ORDER BY TABLE_NAME;

-- 4. CONTA DOCUMENTI PER CARTELLA
-- -----------------------------------------------------
SELECT 
    c.id,
    c.nome,
    c.percorso_completo,
    COUNT(d.id) as num_documenti
FROM cartelle c
LEFT JOIN documenti d ON c.id = d.cartella_id
GROUP BY c.id
ORDER BY num_documenti DESC;

-- 5. VERIFICA DOCUMENTI ORFANI
-- -----------------------------------------------------
-- Documenti con cartella_id che non esiste
SELECT 
    d.id,
    d.titolo,
    d.cartella_id,
    d.azienda_id
FROM documenti d
LEFT JOIN cartelle c ON d.cartella_id = c.id
WHERE d.cartella_id IS NOT NULL 
    AND c.id IS NULL;

-- 6. VERIFICA RIFERIMENTI CIRCOLARI
-- -----------------------------------------------------
WITH RECURSIVE folder_hierarchy AS (
    -- Anchor: cartelle root
    SELECT 
        id, 
        nome, 
        parent_id, 
        1 as level,
        CAST(id AS CHAR(1000)) AS path
    FROM cartelle
    WHERE parent_id IS NULL
    
    UNION ALL
    
    -- Recursive: sottocartelle
    SELECT 
        c.id, 
        c.nome, 
        c.parent_id, 
        fh.level + 1,
        CONCAT(fh.path, '/', c.id) AS path
    FROM cartelle c
    INNER JOIN folder_hierarchy fh ON c.parent_id = fh.id
    WHERE fh.level < 50  -- Previene loop infiniti
)
SELECT * FROM folder_hierarchy
WHERE path LIKE CONCAT('%/', id, '/%', id, '%');

-- 7. ANALISI PERMESSI CARTELLE
-- -----------------------------------------------------
SELECT 
    c.id,
    c.nome,
    COUNT(DISTINCT fp.utente_id) as utenti_con_permessi,
    COUNT(DISTINCT pc.id) as permessi_cartella,
    COUNT(DISTINCT pcr.id) as permessi_ruolo
FROM cartelle c
LEFT JOIN folder_permissions fp ON c.id = fp.folder_id
LEFT JOIN permessi_cartelle pc ON c.id = pc.cartella_id
LEFT JOIN permessi_cartelle_ruoli pcr ON c.id = pcr.cartella_id
GROUP BY c.id
HAVING utenti_con_permessi > 0 OR permessi_cartella > 0 OR permessi_ruolo > 0;

-- 8. VERIFICA TRIGGER
-- -----------------------------------------------------
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    ACTION_STATEMENT
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'NexioSol' 
    AND EVENT_OBJECT_TABLE = 'cartelle';

-- 9. FIX: RIMUOVI CARTELLE DUPLICATE (MANTENENDO QUELLA CON PIÙ DOCUMENTI)
-- -----------------------------------------------------
-- Prima creiamo una tabella temporanea con i duplicati
CREATE TEMPORARY TABLE IF NOT EXISTS cartelle_duplicate AS
SELECT 
    nome, 
    parent_id, 
    azienda_id,
    COUNT(*) as num_duplicati
FROM cartelle
GROUP BY nome, parent_id, azienda_id
HAVING COUNT(*) > 1;

-- Per ogni gruppo di duplicati, mantieni quella con più documenti
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS rimuovi_cartelle_duplicate()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_nome VARCHAR(200);
    DECLARE v_parent_id INT;
    DECLARE v_azienda_id INT;
    DECLARE cur CURSOR FOR SELECT nome, parent_id, azienda_id FROM cartelle_duplicate;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_nome, v_parent_id, v_azienda_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Trova la cartella da mantenere (quella con più documenti)
        SELECT id INTO @keep_id
        FROM cartelle c
        WHERE c.nome = v_nome 
            AND (c.parent_id = v_parent_id OR (c.parent_id IS NULL AND v_parent_id IS NULL))
            AND c.azienda_id = v_azienda_id
        ORDER BY (SELECT COUNT(*) FROM documenti WHERE cartella_id = c.id) DESC
        LIMIT 1;
        
        -- Sposta i documenti dalle cartelle duplicate a quella da mantenere
        UPDATE documenti d
        INNER JOIN cartelle c ON d.cartella_id = c.id
        SET d.cartella_id = @keep_id
        WHERE c.nome = v_nome 
            AND (c.parent_id = v_parent_id OR (c.parent_id IS NULL AND v_parent_id IS NULL))
            AND c.azienda_id = v_azienda_id
            AND c.id != @keep_id;
        
        -- Aggiorna parent_id delle sottocartelle
        UPDATE cartelle
        SET parent_id = @keep_id
        WHERE parent_id IN (
            SELECT id FROM (
                SELECT id FROM cartelle c2
                WHERE c2.nome = v_nome 
                    AND (c2.parent_id = v_parent_id OR (c2.parent_id IS NULL AND v_parent_id IS NULL))
                    AND c2.azienda_id = v_azienda_id
                    AND c2.id != @keep_id
            ) AS temp
        );
        
        -- Elimina le cartelle duplicate
        DELETE FROM cartelle
        WHERE nome = v_nome 
            AND (parent_id = v_parent_id OR (parent_id IS NULL AND v_parent_id IS NULL))
            AND azienda_id = v_azienda_id
            AND id != @keep_id;
        
    END LOOP;
    
    CLOSE cur;
END$$
DELIMITER ;

-- 10. FIX: RICOSTRUISCI PERCORSI COMPLETI
-- -----------------------------------------------------
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS ricostruisci_percorsi()
BEGIN
    -- Prima aggiorna le cartelle root
    UPDATE cartelle 
    SET percorso_completo = nome 
    WHERE parent_id IS NULL;
    
    -- Poi aggiorna ricorsivamente le sottocartelle
    REPEAT
        UPDATE cartelle c1
        INNER JOIN cartelle c2 ON c1.parent_id = c2.id
        SET c1.percorso_completo = CONCAT(c2.percorso_completo, '/', c1.nome)
        WHERE c1.percorso_completo IS NULL OR c1.percorso_completo = '';
    UNTIL ROW_COUNT() = 0 END REPEAT;
END$$
DELIMITER ;

-- 11. FIX: CREA CONSTRAINT UNICO PER PREVENIRE DUPLICATI FUTURI
-- -----------------------------------------------------
-- Prima verifica se esiste già
SELECT COUNT(*) 
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'NexioSol' 
    AND TABLE_NAME = 'cartelle' 
    AND CONSTRAINT_NAME = 'unique_folder_per_company';

-- Se non esiste, crealo
ALTER TABLE cartelle 
ADD CONSTRAINT unique_folder_per_company 
UNIQUE KEY (nome, parent_id, azienda_id);

-- 12. REPORT FINALE
-- -----------------------------------------------------
SELECT 
    'Totale cartelle' as metrica,
    COUNT(*) as valore
FROM cartelle
UNION ALL
SELECT 
    'Cartelle root',
    COUNT(*)
FROM cartelle
WHERE parent_id IS NULL
UNION ALL
SELECT 
    'Cartelle con documenti',
    COUNT(DISTINCT cartella_id)
FROM documenti
WHERE cartella_id IS NOT NULL
UNION ALL
SELECT 
    'Documenti totali',
    COUNT(*)
FROM documenti
UNION ALL
SELECT 
    'Documenti orfani',
    COUNT(*)
FROM documenti d
LEFT JOIN cartelle c ON d.cartella_id = c.id
WHERE d.cartella_id IS NOT NULL AND c.id IS NULL;

-- 13. QUERY PER TEST ELIMINAZIONE
-- -----------------------------------------------------
-- Test se una cartella può essere eliminata
SET @test_folder_id = 1; -- Sostituisci con l'ID da testare

SELECT 
    'Documenti nella cartella' as controllo,
    COUNT(*) as numero
FROM documenti 
WHERE cartella_id = @test_folder_id
UNION ALL
SELECT 
    'Sottocartelle',
    COUNT(*)
FROM cartelle 
WHERE parent_id = @test_folder_id
UNION ALL
SELECT 
    'Permessi assegnati',
    COUNT(*)
FROM folder_permissions 
WHERE folder_id = @test_folder_id
UNION ALL
SELECT 
    'Cartelle favorite',
    COUNT(*)
FROM cartelle_favorite 
WHERE cartella_id = @test_folder_id;