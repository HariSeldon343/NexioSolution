-- Aggiunge colonne di permesso mancanti alla tabella referenti_aziende
ALTER TABLE referenti_aziende 
ADD COLUMN IF NOT EXISTS puo_vedere_referenti BOOLEAN DEFAULT FALSE AFTER puo_gestire_eventi,
ADD COLUMN IF NOT EXISTS puo_gestire_referenti BOOLEAN DEFAULT FALSE AFTER puo_vedere_referenti,
ADD COLUMN IF NOT EXISTS puo_vedere_log BOOLEAN DEFAULT FALSE AFTER puo_gestire_referenti;

-- Indici per migliorare le performance sulle query dei permessi
CREATE INDEX IF NOT EXISTS idx_referenti_permessi_documenti ON referenti_aziende(azienda_id, puo_vedere_documenti, puo_creare_documenti, puo_modificare_documenti);
CREATE INDEX IF NOT EXISTS idx_referenti_permessi_altri ON referenti_aziende(azienda_id, puo_aprire_ticket, puo_gestire_eventi, riceve_notifiche_email); 