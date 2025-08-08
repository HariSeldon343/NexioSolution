-- Trigger per creare automaticamente una cartella per ogni azienda creata
DELIMITER $$

CREATE TRIGGER create_company_folder_after_insert
AFTER INSERT ON aziende
FOR EACH ROW
BEGIN
    -- Crea la cartella root per l'azienda
    INSERT INTO cartelle (nome, parent_id, percorso_completo, azienda_id, creato_da, data_creazione)
    VALUES (NEW.nome, NULL, CONCAT('/', NEW.nome), NEW.id, 1, NOW());
END$$

DELIMITER ;

-- Crea cartelle per aziende esistenti che non hanno ancora una cartella
INSERT INTO cartelle (nome, parent_id, percorso_completo, azienda_id, creato_da, data_creazione)
SELECT a.nome, NULL, CONCAT('/', a.nome), a.id, 1, NOW()
FROM aziende a
LEFT JOIN cartelle c ON a.id = c.azienda_id AND c.parent_id IS NULL AND c.nome = a.nome
WHERE c.id IS NULL;