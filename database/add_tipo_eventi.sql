-- Aggiunge la colonna tipo alla tabella eventi
USE piattaforma_collaborativa;

ALTER TABLE eventi 
ADD COLUMN tipo ENUM('riunione', 'formazione', 'evento', 'altro') NOT NULL DEFAULT 'riunione' 
AFTER luogo; 