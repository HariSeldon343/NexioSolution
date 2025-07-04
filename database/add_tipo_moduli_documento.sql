-- Aggiungi colonna tipo alla tabella moduli_documento
ALTER TABLE moduli_documento 
ADD COLUMN IF NOT EXISTS tipo ENUM('word', 'excel', 'form') DEFAULT 'word' AFTER descrizione;

-- Aggiorna i moduli esistenti in base alla categoria (se presente)
UPDATE moduli_documento SET tipo = 'word' WHERE tipo IS NULL;

-- Rimuovi la colonna categoria se esiste (opzionale - da eseguire solo dopo verifica)
-- ALTER TABLE moduli_documento DROP COLUMN IF EXISTS categoria; 