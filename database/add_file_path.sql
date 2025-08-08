-- Aggiunge la colonna file_path alla tabella documenti
USE NexioSol;

ALTER TABLE documenti 
ADD COLUMN file_path VARCHAR(255) NULL AFTER contenuto; 