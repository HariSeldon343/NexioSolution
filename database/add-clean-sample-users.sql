-- =====================================================
-- AGGIUNGI UTENTI DI ESEMPIO PULITI
-- =====================================================
-- Questo script aggiunge utenti di esempio ben strutturati
-- per dimostrazioni e test del sistema
-- Data: 2025-08-13
-- =====================================================

-- 1. AGGIUNGI UTENTI DI ESEMPIO SOLO SE RICHIESTO
-- =====================================================
-- Password per tutti gli utenti di esempio: Admin123!
-- Hash: $2y$10$XgPMqVBLRO3lQ8QZmKrVVOQR5dKqJVXJ3UYfHMhV0T7BH3Q2mGHXa

-- Aggiungi CEO per MedTec
INSERT INTO utenti (nome, cognome, email, password, ruolo, attivo, data_creazione)
VALUES 
    ('Roberto', 'Rossi', 'r.rossi@medtec.it', 
     '$2y$10$XgPMqVBLRO3lQ8QZmKrVVOQR5dKqJVXJ3UYfHMhV0T7BH3Q2mGHXa', 
     'utente_speciale', 1, NOW()),
    ('Maria', 'Bianchi', 'm.bianchi@medtec.it', 
     '$2y$10$XgPMqVBLRO3lQ8QZmKrVVOQR5dKqJVXJ3UYfHMhV0T7BH3Q2mGHXa', 
     'utente', 1, NOW())
ON DUPLICATE KEY UPDATE email = email;

-- Associa utenti a MedTec (id=6)
INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo, ruolo_azienda, attivo, data_associazione)
SELECT u.id, 6, 'admin', 'CEO', 1, NOW()
FROM utenti u 
WHERE u.email = 'r.rossi@medtec.it'
AND NOT EXISTS (
    SELECT 1 FROM utenti_aziende ua 
    WHERE ua.utente_id = u.id AND ua.azienda_id = 6
);

INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo, ruolo_azienda, attivo, data_associazione)
SELECT u.id, 6, 'staff', 'Manager', 1, NOW()
FROM utenti u 
WHERE u.email = 'm.bianchi@medtec.it'
AND NOT EXISTS (
    SELECT 1 FROM utenti_aziende ua 
    WHERE ua.utente_id = u.id AND ua.azienda_id = 6
);

-- Aggiungi utenti per Sud Marmi (id=5)
INSERT INTO utenti (nome, cognome, email, password, ruolo, attivo, data_creazione)
VALUES 
    ('Luigi', 'Verdi', 'l.verdi@sudmarmi.it', 
     '$2y$10$XgPMqVBLRO3lQ8QZmKrVVOQR5dKqJVXJ3UYfHMhV0T7BH3Q2mGHXa', 
     'utente', 1, NOW())
ON DUPLICATE KEY UPDATE email = email;

INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo, ruolo_azienda, attivo, data_associazione)
SELECT u.id, 5, 'staff', 'Responsabile', 1, NOW()
FROM utenti u 
WHERE u.email = 'l.verdi@sudmarmi.it'
AND NOT EXISTS (
    SELECT 1 FROM utenti_aziende ua 
    WHERE ua.utente_id = u.id AND ua.azienda_id = 5
);

-- 2. ASSICURA CHE IL SUPER ADMIN SIA ASSOCIATO A TUTTE LE AZIENDE
-- =====================================================
INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo, ruolo_azienda, attivo, data_associazione)
SELECT 2, a.id, 'admin', 'super_admin', 1, NOW()
FROM aziende a
WHERE NOT EXISTS (
    SELECT 1 FROM utenti_aziende ua 
    WHERE ua.utente_id = 2 AND ua.azienda_id = a.id
);

-- 3. REPORT FINALE
-- =====================================================
SELECT 'UTENTI TOTALI:' as Report;
SELECT COUNT(*) as totale, 
       SUM(CASE WHEN attivo = 1 THEN 1 ELSE 0 END) as attivi,
       SUM(CASE WHEN ruolo = 'super_admin' THEN 1 ELSE 0 END) as super_admin,
       SUM(CASE WHEN ruolo = 'utente_speciale' THEN 1 ELSE 0 END) as utenti_speciali,
       SUM(CASE WHEN ruolo = 'utente' THEN 1 ELSE 0 END) as utenti_normali
FROM utenti;

SELECT '' as '';
SELECT 'UTENTI PER AZIENDA:' as Report;
SELECT a.nome as azienda, COUNT(ua.utente_id) as num_utenti
FROM aziende a
LEFT JOIN utenti_aziende ua ON a.id = ua.azienda_id AND ua.attivo = 1
GROUP BY a.id, a.nome
ORDER BY a.nome;