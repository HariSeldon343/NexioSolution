-- =====================================================
-- FIX USER DATABASE CONSISTENCY
-- =====================================================
-- Questo script risolve le inconsistenze nel database utenti
-- Backup creato prima dell'esecuzione
-- Data: 2025-08-13
-- =====================================================

-- 0. SISTEMA I RIFERIMENTI ORFANI PRIMA DI ELIMINARLI
-- =====================================================
-- Aggiorna i riferimenti all'utente_id=1 che non esiste più
-- Assegnali al primo super_admin disponibile (id=2)
UPDATE tickets SET utente_id = 2 WHERE utente_id = 1;
UPDATE log_attivita SET utente_id = 2 WHERE utente_id = 1;
UPDATE documenti SET creato_da = 2 WHERE creato_da = 1;
UPDATE eventi SET creato_da = 2 WHERE creato_da = 1;

-- 1. RIMUOVI RIFERIMENTI ORFANI
-- =====================================================
DELETE FROM utenti_aziende WHERE utente_id = 0;
DELETE FROM utenti_aziende WHERE utente_id NOT IN (SELECT id FROM utenti);
DELETE FROM utenti_aziende WHERE azienda_id NOT IN (SELECT id FROM aziende);

-- 2. PULIZIA UTENTI DI TEST NON PIÙ NECESSARI
-- =====================================================
-- Disattiva utenti di test obsoleti ma mantieni per riferimenti storici
UPDATE utenti SET attivo = 0 WHERE email LIKE '%test%' OR email LIKE '%example.com%';

-- 3. SISTEMA I RUOLI DEGLI UTENTI
-- =====================================================
-- Assicurati che i ruoli siano validi (super_admin, utente_speciale, utente)
UPDATE utenti SET ruolo = 'utente' WHERE ruolo NOT IN ('super_admin', 'utente_speciale', 'utente');

-- 4. COMPLETA I DATI MANCANTI DEGLI UTENTI ATTIVI
-- =====================================================
-- Aggiungi cognome dove mancante
UPDATE utenti SET cognome = 'Da Completare' WHERE cognome IS NULL OR cognome = '' AND attivo = 1;
UPDATE utenti SET nome = 'Da Completare' WHERE nome IS NULL OR nome = '' AND attivo = 1;

-- 5. CORREGGI LE ASSOCIAZIONI UTENTI-AZIENDE
-- =====================================================
-- Assicurati che ogni utente attivo abbia almeno un'azienda (tranne super_admin)
INSERT IGNORE INTO utenti_aziende (utente_id, azienda_id, ruolo, ruolo_azienda, attivo, data_associazione)
SELECT u.id, 
       (SELECT id FROM aziende WHERE stato = 'attiva' ORDER BY id LIMIT 1),
       'staff',
       'utente',
       1,
       NOW()
FROM utenti u
WHERE u.attivo = 1 
  AND u.ruolo = 'utente'
  AND NOT EXISTS (
    SELECT 1 FROM utenti_aziende ua WHERE ua.utente_id = u.id AND ua.attivo = 1
  );

-- 6. SISTEMA I RUOLI NELLE ASSOCIAZIONI
-- =====================================================
-- Allinea i ruoli nelle associazioni con i ruoli utente
UPDATE utenti_aziende ua
JOIN utenti u ON ua.utente_id = u.id
SET ua.ruolo = CASE 
    WHEN u.ruolo = 'super_admin' THEN 'admin'
    WHEN u.ruolo = 'utente_speciale' THEN 'admin'
    ELSE 'staff'
END
WHERE ua.ruolo IS NULL OR ua.ruolo = '';

-- 7. CREA UTENTI STANDARD SE NON ESISTONO
-- =====================================================
-- Assicurati che ci sia almeno un super admin attivo
INSERT IGNORE INTO utenti (nome, cognome, email, password, ruolo, attivo, data_creazione)
SELECT 'System', 'Administrator', 'admin@nexio.local', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
       'super_admin', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM utenti WHERE ruolo = 'super_admin' AND attivo = 1
);

-- 8. REPORT FINALE
-- =====================================================
SELECT 'UTENTI ATTIVI:' as Report;
SELECT id, CONCAT(nome, ' ', cognome) as nome_completo, email, ruolo, 
       CASE WHEN attivo = 1 THEN 'ATTIVO' ELSE 'DISATTIVO' END as stato
FROM utenti 
ORDER BY ruolo, attivo DESC, cognome, nome;

SELECT '' as '';
SELECT 'ASSOCIAZIONI UTENTI-AZIENDE:' as Report;
SELECT u.email, a.nome as azienda, ua.ruolo as ruolo_azienda, 
       CASE WHEN ua.attivo = 1 THEN 'ATTIVA' ELSE 'DISATTIVA' END as stato
FROM utenti_aziende ua
JOIN utenti u ON ua.utente_id = u.id
JOIN aziende a ON ua.azienda_id = a.id
ORDER BY u.email, a.nome;

SELECT '' as '';
SELECT 'STATISTICHE:' as Report;
SELECT 
    (SELECT COUNT(*) FROM utenti WHERE attivo = 1) as utenti_attivi,
    (SELECT COUNT(*) FROM utenti WHERE ruolo = 'super_admin' AND attivo = 1) as super_admin,
    (SELECT COUNT(*) FROM utenti WHERE ruolo = 'utente_speciale' AND attivo = 1) as utenti_speciali,
    (SELECT COUNT(*) FROM utenti WHERE ruolo = 'utente' AND attivo = 1) as utenti_normali,
    (SELECT COUNT(*) FROM utenti_aziende WHERE attivo = 1) as associazioni_attive;