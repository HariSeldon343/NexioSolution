-- Script per correggere tutti i riferimenti al ruolo "admin" che non dovrebbe esistere
-- I ruoli validi sono: super_admin, utente_speciale, utente

-- 1. Aggiorna eventuali utenti con ruolo "admin" a "utente_speciale"
UPDATE utenti 
SET ruolo = 'utente_speciale' 
WHERE ruolo = 'admin';

-- 2. Aggiorna eventuali riferimenti nelle tabelle di permessi
UPDATE user_permissions 
SET role = 'utente_speciale' 
WHERE role = 'admin';

-- 3. Aggiorna eventuali riferimenti nei ruoli azienda
UPDATE utenti_aziende 
SET ruolo_azienda = 'responsabile_aziendale' 
WHERE ruolo_azienda = 'admin';

-- 4. Mostra i ruoli attuali per verifica
SELECT DISTINCT ruolo, COUNT(*) as count 
FROM utenti 
GROUP BY ruolo;

-- 5. Mostra i ruoli azienda attuali per verifica
SELECT DISTINCT ruolo_azienda, COUNT(*) as count 
FROM utenti_aziende 
WHERE ruolo_azienda IS NOT NULL
GROUP BY ruolo_azienda;