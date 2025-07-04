-- Aggiungi colonna richiede_conferma_azioni alla tabella aziende se non esiste
ALTER TABLE aziende 
ADD COLUMN richiede_conferma_azioni BOOLEAN DEFAULT TRUE; 