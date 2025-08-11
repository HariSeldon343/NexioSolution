-- Fix per la tabella moduli_azienda
-- Problema: mancanza di PRIMARY KEY e AUTO_INCREMENT

-- 1. Backup dei dati esistenti
CREATE TABLE IF NOT EXISTS moduli_azienda_backup AS 
SELECT * FROM moduli_azienda;

-- 2. Rimuovi eventuali duplicati prima di aggiungere constraints
DELETE ma1 FROM moduli_azienda ma1
INNER JOIN moduli_azienda ma2 
WHERE ma1.azienda_id = ma2.azienda_id 
  AND ma1.modulo_id = ma2.modulo_id 
  AND ma1.id > ma2.id;

-- 3. Modifica la struttura della tabella
ALTER TABLE moduli_azienda 
  MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT,
  ADD PRIMARY KEY (id);

-- 4. Aggiungi indice univoco per evitare duplicati
ALTER TABLE moduli_azienda 
  ADD UNIQUE KEY unique_azienda_modulo (azienda_id, modulo_id);

-- 5. Aggiungi foreign keys per integrità referenziale
ALTER TABLE moduli_azienda 
  ADD CONSTRAINT fk_moduli_azienda_azienda 
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_moduli_azienda_modulo 
    FOREIGN KEY (modulo_id) REFERENCES moduli(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_moduli_azienda_abilitato_da 
    FOREIGN KEY (abilitato_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- 6. Aggiungi indici per le query
ALTER TABLE moduli_azienda 
  ADD INDEX idx_azienda_abilitato (azienda_id, abilitato),
  ADD INDEX idx_modulo_abilitato (modulo_id, abilitato);

-- 7. Verifica la struttura finale
DESCRIBE moduli_azienda;

-- 8. Mostra il conteggio dei record
SELECT COUNT(*) as total_records FROM moduli_azienda;