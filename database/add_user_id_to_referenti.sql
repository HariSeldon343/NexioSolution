-- Aggiunge colonna utente_id alla tabella referenti_aziende
ALTER TABLE referenti_aziende 
ADD COLUMN IF NOT EXISTS utente_id INT AFTER azienda_id,
ADD FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL;

-- Aggiunge colonna max_referenti alla tabella aziende
ALTER TABLE aziende 
ADD COLUMN IF NOT EXISTS max_referenti INT DEFAULT 5 AFTER numero_dipendenti;

-- Aggiunge colonna permessi alla tabella utenti_aziende
ALTER TABLE utenti_aziende 
ADD COLUMN IF NOT EXISTS permessi JSON DEFAULT '[]' AFTER ruolo_azienda;

-- Indice per migliorare le performance
CREATE INDEX IF NOT EXISTS idx_referenti_utente ON referenti_aziende(utente_id); 