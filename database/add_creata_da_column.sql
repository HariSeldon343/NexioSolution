-- Script per aggiungere la colonna creata_da alla tabella aziende
-- Database: NexioSol

-- Aggiungi colonna creata_da se non esiste
ALTER TABLE aziende 
ADD COLUMN IF NOT EXISTS creata_da INT NULL AFTER note;

-- Aggiungi colonna data_creazione se non esiste
ALTER TABLE aziende 
ADD COLUMN IF NOT EXISTS data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Aggiungi colonna data_modifica se non esiste
ALTER TABLE aziende 
ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Aggiungi colonna modificata_da se non esiste
ALTER TABLE aziende 
ADD COLUMN IF NOT EXISTS modificata_da INT NULL;

-- Aggiungi foreign key per creata_da (opzionale)
-- ALTER TABLE aziende 
-- ADD CONSTRAINT fk_aziende_creata_da 
-- FOREIGN KEY (creata_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- Aggiungi foreign key per modificata_da (opzionale)
-- ALTER TABLE aziende 
-- ADD CONSTRAINT fk_aziende_modificata_da 
-- FOREIGN KEY (modificata_da) REFERENCES utenti(id) ON DELETE SET NULL;

-- Verifica la struttura finale
DESCRIBE aziende;