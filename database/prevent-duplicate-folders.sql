-- Previeni future cartelle duplicate
-- Aggiungi un indice unico per evitare cartelle duplicate nella stessa azienda

-- Prima verifica che non ci siano più duplicati
SELECT 'Verifica duplicati prima di applicare il constraint:' as Messaggio;
SELECT 
    nome, 
    azienda_id, 
    COUNT(*) as num_duplicati
FROM cartelle
WHERE parent_id IS NULL
GROUP BY nome, azienda_id
HAVING COUNT(*) > 1;

-- Se la query sopra non restituisce risultati, procedi con:

-- Aggiungi indice unico (decommenta la linea sotto dopo aver verificato)
-- ALTER TABLE cartelle ADD UNIQUE INDEX idx_unique_root_folder (nome, azienda_id, parent_id);

-- Per cartelle non-root, aggiungi constraint per evitare duplicati nello stesso parent
-- ALTER TABLE cartelle ADD UNIQUE INDEX idx_unique_subfolder (nome, parent_id, azienda_id);