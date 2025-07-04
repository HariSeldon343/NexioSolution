-- Aggiunge colonne mancanti e corregge struttura database
USE piattaforma_collaborativa;

-- Aggiungi colonna data_iscrizione a partecipanti_eventi se non esiste
ALTER TABLE partecipanti_eventi 
ADD COLUMN IF NOT EXISTS data_iscrizione TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Aggiungi colonna versione a documenti se non esiste
ALTER TABLE documenti 
MODIFY COLUMN versione VARCHAR(20) DEFAULT '1.0';

-- Aggiungi colonna data_logout a sessioni
ALTER TABLE sessioni 
ADD COLUMN IF NOT EXISTS data_logout TIMESTAMP NULL;

-- Creare tabella configurazioni se non esiste
CREATE TABLE IF NOT EXISTS configurazioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chiave VARCHAR(100) UNIQUE NOT NULL,
    valore TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    descrizione TEXT,
    modificabile BOOLEAN DEFAULT TRUE,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB; 