-- Aggiorna la struttura della tabella cartelle con le colonne mancanti
-- Eseguire questo script sul database NexioSol

-- Seleziona il database
USE NexioSol;

-- Aggiungi le colonne mancanti alla tabella cartelle
ALTER TABLE cartelle 
ADD COLUMN IF NOT EXISTS modificato_da INT AFTER data_creazione,
ADD COLUMN IF NOT EXISTS data_modifica TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER modificato_da;

-- Aggiungi le foreign key se non esistono
ALTER TABLE cartelle
ADD CONSTRAINT fk_cartelle_modificato_da 
FOREIGN KEY IF NOT EXISTS (modificato_da) 
REFERENCES utenti(id) ON DELETE SET NULL;

-- Verifica la struttura aggiornata
DESCRIBE cartelle;