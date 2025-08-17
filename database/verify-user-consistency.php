<?php
/**
 * Script di verifica consistenza database utenti
 * Esegue controlli approfonditi per verificare l'integrità dei dati
 * 
 * Uso: /mnt/c/xampp/php/php.exe database/verify-user-consistency.php
 */

require_once __DIR__ . '/../backend/config/config.php';

echo "\n" . str_repeat("=", 80) . "\n";
echo "VERIFICA CONSISTENZA DATABASE UTENTI\n";
echo str_repeat("=", 80) . "\n\n";

// 1. VERIFICA UTENTI
echo "1. UTENTI NEL SISTEMA:\n";
echo str_repeat("-", 40) . "\n";

$stmt = db_query("SELECT id, nome, cognome, email, ruolo, attivo FROM utenti ORDER BY ruolo, attivo DESC, id");
$utenti = $stmt->fetchAll();

$stats = [
    'totale' => 0,
    'attivi' => 0,
    'super_admin' => 0,
    'utente_speciale' => 0,
    'utente' => 0
];

foreach ($utenti as $u) {
    $stats['totale']++;
    if ($u['attivo']) $stats['attivi']++;
    $stats[$u['ruolo']]++;
    
    $status = $u['attivo'] ? '✓' : '✗';
    $ruolo_display = str_pad(ucfirst(str_replace('_', ' ', $u['ruolo'])), 15);
    
    printf("[%s] ID:%3d | %-30s | %-30s | %s\n", 
        $status, 
        $u['id'],
        substr($u['nome'] . ' ' . $u['cognome'], 0, 30),
        substr($u['email'], 0, 30),
        $ruolo_display
    );
}

echo "\nSTATISTICHE:\n";
echo "  Totale utenti: {$stats['totale']}\n";
echo "  Attivi: {$stats['attivi']}\n";
echo "  Super Admin: {$stats['super_admin']}\n";
echo "  Utenti Speciali: {$stats['utente_speciale']}\n";
echo "  Utenti Normali: {$stats['utente']}\n";

// 2. VERIFICA ASSOCIAZIONI UTENTI-AZIENDE
echo "\n2. ASSOCIAZIONI UTENTI-AZIENDE:\n";
echo str_repeat("-", 40) . "\n";

$stmt = db_query("
    SELECT u.email, a.nome as azienda, ua.ruolo, ua.ruolo_azienda, ua.attivo
    FROM utenti_aziende ua
    JOIN utenti u ON ua.utente_id = u.id
    JOIN aziende a ON ua.azienda_id = a.id
    ORDER BY a.nome, u.email
");
$associazioni = $stmt->fetchAll();

$current_azienda = '';
foreach ($associazioni as $a) {
    if ($current_azienda != $a['azienda']) {
        echo "\n  {$a['azienda']}:\n";
        $current_azienda = $a['azienda'];
    }
    $status = $a['attivo'] ? '✓' : '✗';
    printf("    [%s] %-30s | Ruolo: %-10s | %s\n",
        $status,
        substr($a['email'], 0, 30),
        $a['ruolo'],
        $a['ruolo_azienda'] ?: 'N/A'
    );
}

// 3. VERIFICA RIFERIMENTI ORFANI
echo "\n3. CONTROLLO RIFERIMENTI ORFANI:\n";
echo str_repeat("-", 40) . "\n";

// Controlla utenti_aziende
$stmt = db_query("
    SELECT COUNT(*) as cnt 
    FROM utenti_aziende 
    WHERE utente_id NOT IN (SELECT id FROM utenti)
");
$orfani = $stmt->fetch()['cnt'];
echo "  Associazioni con utenti inesistenti: $orfani\n";

$stmt = db_query("
    SELECT COUNT(*) as cnt 
    FROM utenti_aziende 
    WHERE azienda_id NOT IN (SELECT id FROM aziende)
");
$orfani = $stmt->fetch()['cnt'];
echo "  Associazioni con aziende inesistenti: $orfani\n";

// Controlla tickets
$stmt = db_query("
    SELECT COUNT(*) as cnt 
    FROM tickets 
    WHERE utente_id IS NOT NULL 
    AND utente_id NOT IN (SELECT id FROM utenti)
");
$orfani = $stmt->fetch()['cnt'];
echo "  Tickets con utenti inesistenti: $orfani\n";

// Controlla log_attivita
$stmt = db_query("
    SELECT COUNT(*) as cnt 
    FROM log_attivita 
    WHERE utente_id IS NOT NULL 
    AND utente_id NOT IN (SELECT id FROM utenti)
");
$orfani = $stmt->fetch()['cnt'];
echo "  Log attività con utenti inesistenti: $orfani\n";

// 4. VERIFICA UTENTI SENZA AZIENDA
echo "\n4. UTENTI SENZA ASSOCIAZIONI AZIENDA:\n";
echo str_repeat("-", 40) . "\n";

$stmt = db_query("
    SELECT u.id, u.nome, u.cognome, u.email, u.ruolo
    FROM utenti u
    LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id AND ua.attivo = 1
    WHERE u.attivo = 1 AND ua.id IS NULL
    ORDER BY u.ruolo, u.cognome
");
$senza_azienda = $stmt->fetchAll();

if (empty($senza_azienda)) {
    echo "  ✓ Tutti gli utenti attivi hanno almeno un'azienda associata\n";
} else {
    foreach ($senza_azienda as $u) {
        printf("  ⚠ %s %s (%s) - Ruolo: %s\n",
            $u['nome'],
            $u['cognome'],
            $u['email'],
            $u['ruolo']
        );
    }
}

// 5. VERIFICA DATI MANCANTI
echo "\n5. CONTROLLO COMPLETEZZA DATI:\n";
echo str_repeat("-", 40) . "\n";

$stmt = db_query("
    SELECT COUNT(*) as cnt 
    FROM utenti 
    WHERE attivo = 1 
    AND (nome IS NULL OR nome = '' OR cognome IS NULL OR cognome = '')
");
$incompleti = $stmt->fetch()['cnt'];
echo "  Utenti attivi con nome/cognome mancante: $incompleti\n";

$stmt = db_query("
    SELECT COUNT(*) as cnt 
    FROM utenti 
    WHERE attivo = 1 
    AND (email IS NULL OR email = '' OR email NOT LIKE '%@%')
");
$email_invalide = $stmt->fetch()['cnt'];
echo "  Utenti attivi con email invalida: $email_invalide\n";

// 6. RIEPILOGO FINALE
echo "\n" . str_repeat("=", 80) . "\n";
echo "RIEPILOGO FINALE\n";
echo str_repeat("=", 80) . "\n";

$all_ok = true;

if ($orfani > 0) {
    echo "⚠ ATTENZIONE: Trovati riferimenti orfani nel database\n";
    $all_ok = false;
}

if (!empty($senza_azienda) && !in_array('super_admin', array_column($senza_azienda, 'ruolo'))) {
    echo "⚠ ATTENZIONE: Alcuni utenti non-admin non hanno aziende associate\n";
    $all_ok = false;
}

if ($incompleti > 0 || $email_invalide > 0) {
    echo "⚠ ATTENZIONE: Alcuni utenti hanno dati incompleti\n";
    $all_ok = false;
}

if ($stats['super_admin'] == 0) {
    echo "⚠ CRITICO: Nessun super admin attivo nel sistema!\n";
    $all_ok = false;
}

if ($all_ok) {
    echo "✓ DATABASE UTENTI CONSISTENTE E COMPLETO\n";
} else {
    echo "\n⚠ Eseguire database/fix-user-database-consistency.sql per correggere i problemi\n";
}

echo "\n";