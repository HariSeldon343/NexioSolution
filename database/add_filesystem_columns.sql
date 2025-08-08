-- Aggiunge colonne necessarie per il sistema filesystem semplificato
USE NexioSol;

-- Aggiungi colonna formato alla tabella documenti se non esiste
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS formato VARCHAR(10) NULL AFTER file_path;

-- Aggiungi colonna dimensione_file alla tabella documenti se non esiste  
ALTER TABLE documenti
ADD COLUMN IF NOT EXISTS dimensione_file BIGINT DEFAULT 0 AFTER formato;

-- Crea directory per upload se non esistono
-- Nota: Le directory fisiche devono essere create manualmente o via PHP