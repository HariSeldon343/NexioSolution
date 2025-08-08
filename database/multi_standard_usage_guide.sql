-- Guida all'uso del Sistema Documentale Multi-Norma
-- Query di esempio e best practices per MySQL 8+

USE NexioSol;

-- ==================================================
-- QUERY DI ESEMPIO PER OPERAZIONI COMUNI
-- ==================================================

-- 1. Configurare un'azienda per sistema separato ISO 9001 e ISO 14001
-- -----------------------------------------------------------------
-- Prima configurare il tipo
INSERT INTO configurazioni_normative_azienda (azienda_id, tipo_configurazione, configurato_da)
VALUES (1, 'separata', 1);

-- Poi attivare gli standard
INSERT INTO aziende_standard (azienda_id, standard_id, attivo, data_attivazione, responsabile_id)
SELECT 1, id, TRUE, CURDATE(), 1
FROM standard_normativi 
WHERE codice IN ('ISO_9001', 'ISO_14001');

-- Creare struttura cartelle per ogni standard
CALL sp_crea_struttura_standard(1, 1, 1); -- ISO 9001
CALL sp_crea_struttura_standard(1, 2, 1); -- ISO 14001

-- 2. Configurare sistema integrato ISO (9001 + 14001 + 45001)
-- -----------------------------------------------------------------
-- Configurazione integrata
INSERT INTO configurazioni_normative_azienda (azienda_id, tipo_configurazione, configurato_da)
VALUES (2, 'integrata', 1);

-- Creare cartella radice per sistema integrato
INSERT INTO cartelle (nome, percorso_completo, livello, azienda_id, tipo_cartella, creato_da)
VALUES ('Sistema Integrato ISO', '/Sistema_Integrato_ISO', 0, 2, 'mista', 1);

-- Creare sottocartelle unificate
INSERT INTO cartelle (nome, parent_id, percorso_completo, livello, azienda_id, tipo_cartella, creato_da)
SELECT 
    cs.nome,
    (SELECT id FROM cartelle WHERE nome = 'Sistema Integrato ISO' AND azienda_id = 2),
    CONCAT('/Sistema_Integrato_ISO/', REPLACE(cs.nome, ' ', '_')),
    1,
    2,
    'mista',
    1
FROM categorie_standard cs
WHERE cs.standard_id = 1 -- Usa categorie ISO 9001 come base
GROUP BY cs.nome;

-- 3. Upload multiplo di documenti
-- -----------------------------------------------------------------
-- Creare batch di upload
INSERT INTO upload_batch (codice_batch, utente_id, azienda_id, cartella_destinazione_id, numero_file_totali)
VALUES (CONCAT('BATCH_', UNIX_TIMESTAMP()), 1, 1, 5, 10);

-- Registrare file nel batch
INSERT INTO upload_batch_files (batch_id, nome_file, dimensione_bytes, mime_type)
VALUES 
    (LAST_INSERT_ID(), 'procedura_acquisti.pdf', 1048576, 'application/pdf'),
    (LAST_INSERT_ID(), 'modulo_ordine.docx', 524288, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    (LAST_INSERT_ID(), 'politica_qualita.pdf', 2097152, 'application/pdf');

-- 4. Ricerca full-text avanzata
-- -----------------------------------------------------------------
-- Ricerca semplice in tutti i documenti dell'azienda
CALL sp_ricerca_documenti('procedura acquisti', 1, NULL, 20, 0);

-- Ricerca solo in ISO 9001
CALL sp_ricerca_documenti('non conformità', 1, 1, 10, 0);

-- Ricerca con query complessa
SELECT 
    d.id,
    d.codice,
    d.titolo,
    d.stato,
    sn.nome as norma,
    cs.nome as categoria,
    c.percorso_completo as percorso,
    MATCH(d.titolo, d.contenuto) AGAINST('audit interno' IN NATURAL LANGUAGE MODE) as rilevanza
FROM documenti d
JOIN standard_normativi sn ON d.standard_id = sn.id
JOIN categorie_standard cs ON d.categoria_standard_id = cs.id
JOIN cartelle c ON d.cartella_id = c.id
WHERE d.azienda_id = 1
AND d.stato = 'pubblicato'
AND MATCH(d.titolo, d.contenuto) AGAINST('audit interno' IN NATURAL LANGUAGE MODE)
ORDER BY rilevanza DESC, d.data_modifica DESC
LIMIT 20;

-- 5. Preparare download multiplo
-- -----------------------------------------------------------------
-- Creare batch per download ZIP
INSERT INTO download_batch (
    codice_batch, 
    utente_id, 
    azienda_id, 
    tipo_export, 
    documenti_ids
)
VALUES (
    CONCAT('DL_', UNIX_TIMESTAMP()),
    1,
    1,
    'zip',
    JSON_ARRAY(10, 15, 23, 45, 67) -- IDs dei documenti da scaricare
);

-- 6. Report compliance per azienda
-- -----------------------------------------------------------------
SELECT 
    sn.nome as 'Standard',
    COUNT(DISTINCT d.id) as 'Documenti Totali',
    COUNT(DISTINCT CASE WHEN d.stato = 'pubblicato' THEN d.id END) as 'Pubblicati',
    COUNT(DISTINCT CASE WHEN d.stato = 'bozza' THEN d.id END) as 'In Bozza',
    COUNT(DISTINCT CASE WHEN d.scadenza < CURDATE() THEN d.id END) as 'Scaduti',
    COALESCE(ast.data_scadenza, 'N/A') as 'Scadenza Certificazione',
    COALESCE(ast.data_audit_prossimo, 'N/A') as 'Prossimo Audit'
FROM aziende_standard ast
JOIN standard_normativi sn ON ast.standard_id = sn.id
LEFT JOIN documenti d ON ast.azienda_id = d.azienda_id AND ast.standard_id = d.standard_id
WHERE ast.azienda_id = 1
AND ast.attivo = TRUE
GROUP BY sn.id, ast.data_scadenza, ast.data_audit_prossimo;

-- 7. Gestione permessi granulari
-- -----------------------------------------------------------------
-- Dare permessi di lettura a un utente su documenti di una categoria
INSERT INTO permessi_documenti_avanzati (
    documento_id, 
    soggetto_tipo, 
    soggetto_id, 
    permesso_lettura, 
    permesso_download,
    concesso_da
)
SELECT 
    d.id, 
    'utente', 
    5, -- ID utente
    TRUE, 
    TRUE,
    1 -- Admin che concede
FROM documenti d
WHERE d.azienda_id = 1
AND d.categoria_standard_id = 3 -- Procedure
AND d.stato = 'pubblicato';

-- 8. Audit trail - ultimi accessi ai documenti
-- -----------------------------------------------------------------
SELECT 
    d.codice,
    d.titolo,
    u.nome as utente_nome,
    u.cognome as utente_cognome,
    ad.azione,
    ad.timestamp_azione,
    ad.ip_address
FROM audit_documenti ad
JOIN documenti d ON ad.documento_id = d.id
JOIN utenti u ON ad.utente_id = u.id
WHERE ad.azienda_id = 1
AND ad.timestamp_azione >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY ad.timestamp_azione DESC
LIMIT 100;

-- ==================================================
-- QUERY DI MONITORAGGIO PERFORMANCE
-- ==================================================

-- 1. Documenti più pesanti per ottimizzazione storage
SELECT 
    d.id,
    d.codice,
    d.titolo,
    d.dimensione_file / 1048576 as dimensione_mb,
    sn.nome as standard,
    c.percorso_completo
FROM documenti d
LEFT JOIN standard_normativi sn ON d.standard_id = sn.id
LEFT JOIN cartelle c ON d.cartella_id = c.id
WHERE d.azienda_id = 1
ORDER BY d.dimensione_file DESC
LIMIT 20;

-- 2. Analisi utilizzo ricerca
SELECT 
    DATE(data_ricerca) as giorno,
    COUNT(*) as numero_ricerche,
    AVG(numero_risultati) as media_risultati,
    AVG(tempo_esecuzione_ms) as tempo_medio_ms
FROM log_ricerche
WHERE azienda_id = 1
AND data_ricerca >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(data_ricerca)
ORDER BY giorno DESC;

-- 3. Statistiche upload per monitoraggio
SELECT 
    DATE(data_inizio) as giorno,
    COUNT(DISTINCT id) as batch_totali,
    SUM(numero_file_totali) as file_totali,
    SUM(numero_file_completati) as file_completati,
    SUM(dimensione_totale_bytes) / 1073741824 as gb_totali,
    AVG(TIMESTAMPDIFF(SECOND, data_inizio, data_fine)) as tempo_medio_secondi
FROM upload_batch
WHERE azienda_id = 1
AND data_inizio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(data_inizio);

-- ==================================================
-- MANUTENZIONE E OTTIMIZZAZIONE
-- ==================================================

-- 1. Pulizia indici di ricerca vecchi
DELETE di FROM documenti_indice_ricerca di
LEFT JOIN documenti d ON di.documento_id = d.id
WHERE d.id IS NULL;

-- 2. Ottimizzazione tabelle dopo grosse operazioni
OPTIMIZE TABLE documenti;
OPTIMIZE TABLE documenti_indice_ricerca;
OPTIMIZE TABLE audit_documenti;

-- 3. Verifica integrità referenziale
SELECT 
    'Documenti orfani' as tipo,
    COUNT(*) as numero
FROM documenti d
LEFT JOIN cartelle c ON d.cartella_id = c.id
WHERE d.cartella_id IS NOT NULL AND c.id IS NULL

UNION ALL

SELECT 
    'Permessi orfani' as tipo,
    COUNT(*) as numero
FROM permessi_documenti_avanzati p
LEFT JOIN documenti d ON p.documento_id = d.id
WHERE d.id IS NULL;

-- 4. Statistiche utilizzo spazio
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
    table_rows as righe_stimate
FROM information_schema.tables
WHERE table_schema = 'NexioSol'
AND table_name IN (
    'documenti',
    'documenti_indice_ricerca',
    'audit_documenti',
    'cartelle',
    'upload_batch_files'
)
ORDER BY (data_length + index_length) DESC;

-- ==================================================
-- QUERY PER SUPER ADMIN
-- ==================================================

-- 1. Dashboard globale multi-azienda
SELECT 
    a.nome as azienda,
    cna.tipo_configurazione,
    COUNT(DISTINCT ast.standard_id) as standard_attivi,
    COUNT(DISTINCT d.id) as totale_documenti,
    COUNT(DISTINCT c.id) as totale_cartelle,
    COUNT(DISTINCT CASE WHEN d.data_creazione >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN d.id END) as nuovi_ultimi_30gg
FROM aziende a
LEFT JOIN configurazioni_normative_azienda cna ON a.id = cna.azienda_id
LEFT JOIN aziende_standard ast ON a.id = ast.azienda_id AND ast.attivo = TRUE
LEFT JOIN documenti d ON a.id = d.azienda_id
LEFT JOIN cartelle c ON a.id = c.azienda_id
WHERE a.stato = 'attiva'
GROUP BY a.id
ORDER BY totale_documenti DESC;

-- 2. Analisi cross-azienda per standard
SELECT 
    sn.nome as standard,
    COUNT(DISTINCT ast.azienda_id) as aziende_certificate,
    COUNT(DISTINCT d.id) as documenti_totali,
    AVG(DATEDIFF(ast.data_scadenza, CURDATE())) as media_giorni_scadenza
FROM standard_normativi sn
LEFT JOIN aziende_standard ast ON sn.id = ast.standard_id AND ast.attivo = TRUE
LEFT JOIN documenti d ON sn.id = d.standard_id
GROUP BY sn.id
ORDER BY aziende_certificate DESC;

-- ==================================================
-- ESEMPI DI UTILIZZO IN PHP
-- ==================================================

/*
// Esempio PHP per upload multiplo
$batch_id = db_insert('upload_batch', [
    'codice_batch' => 'BATCH_' . time(),
    'utente_id' => $_SESSION['user_id'],
    'azienda_id' => $_SESSION['azienda_id'],
    'cartella_destinazione_id' => $cartella_id,
    'numero_file_totali' => count($_FILES['documenti']['name'])
]);

foreach ($_FILES['documenti']['name'] as $key => $filename) {
    db_insert('upload_batch_files', [
        'batch_id' => $batch_id,
        'nome_file' => $filename,
        'dimensione_bytes' => $_FILES['documenti']['size'][$key],
        'mime_type' => $_FILES['documenti']['type'][$key]
    ]);
}

// Processare il batch
db_query("CALL sp_processa_upload_batch(?)", [$batch_id]);

// Esempio per ricerca
$risultati = db_query("CALL sp_ricerca_documenti(?, ?, ?, ?, ?)", [
    $query,
    $_SESSION['azienda_id'],
    $standard_id ?? null,
    20,
    $offset
])->fetchAll();

// Esempio per controllo permessi
function userCanViewDocument($documento_id, $user_id) {
    // Super admin vede tutto
    if ($_SESSION['role'] === 'super_admin') return true;
    
    // Verifica azienda
    $doc = db_query("SELECT azienda_id FROM documenti WHERE id = ?", [$documento_id])->fetch();
    if ($doc['azienda_id'] !== $_SESSION['azienda_id']) return false;
    
    // Verifica permessi specifici
    $perm = db_query("
        SELECT permesso_lettura 
        FROM permessi_documenti_avanzati 
        WHERE documento_id = ? 
        AND soggetto_tipo = 'utente' 
        AND soggetto_id = ?
        AND (data_fine IS NULL OR data_fine > NOW())
    ", [$documento_id, $user_id])->fetch();
    
    return $perm && $perm['permesso_lettura'];
}
*/

-- Fine guida utilizzo