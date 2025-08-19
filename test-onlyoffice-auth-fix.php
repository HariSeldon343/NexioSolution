<?php
/**
 * Test script per verificare il fix di onlyoffice-auth.php
 */

// Inizializza sessione per autenticazione
session_start();
require_once 'backend/config/config.php';

// Simula autenticazione se necessario per test locale
if (empty($_SESSION['user_id'])) {
    // Trova un utente di test
    $stmt = db_query("SELECT id FROM utenti LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo "✓ Sessione simulata per user_id: " . $user['id'] . "\n\n";
    } else {
        die("❌ Nessun utente trovato nel database\n");
    }
}

// Test 1: Verifica che l'endpoint risponda con JSON valido
echo "=== Test 1: Chiamata API generate_token ===\n";

$url = 'http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-auth.php?action=generate_token&document_id=1';

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => [
            'Cookie: PHPSESSID=' . session_id(),
            'X-CSRF-Token: ' . $_SESSION['csrf_token']
        ]
    ]
];

$context = stream_context_create($opts);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ Errore nella chiamata API\n";
    $error = error_get_last();
    echo "Dettagli: " . print_r($error, true) . "\n";
} else {
    echo "✓ Risposta ricevuta\n";
    echo "Raw response: " . substr($response, 0, 200) . "...\n\n";
    
    // Verifica che sia JSON valido
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ JSON valido ricevuto\n";
        echo "Struttura risposta:\n";
        print_r($json);
    } else {
        echo "❌ JSON non valido: " . json_last_error_msg() . "\n";
        echo "Prima parte della risposta: " . substr($response, 0, 100) . "\n";
    }
}

// Test 2: Verifica server status
echo "\n=== Test 2: Chiamata API server_status ===\n";

$url = 'http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-auth.php?action=server_status';
$response = @file_get_contents($url, false, $context);

if ($response !== false) {
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ Server status JSON valido\n";
        echo "Server available: " . ($json['server_available'] ? 'Yes' : 'No') . "\n";
        echo "Server URL: " . ($json['server_url'] ?? 'N/A') . "\n";
    } else {
        echo "❌ JSON non valido per server_status\n";
    }
}

// Test 3: Verifica che non ci siano Fatal error
echo "\n=== Test 3: Verifica assenza Fatal error ===\n";

// Controlla i log di errore PHP
$errorLog = '/mnt/c/xampp/apache/logs/error.log';
if (file_exists($errorLog)) {
    $lastLines = shell_exec("tail -n 10 '$errorLog' | grep -i 'fatal\\|onlyoffice'");
    if (empty($lastLines)) {
        echo "✓ Nessun Fatal error recente nei log\n";
    } else {
        echo "⚠ Errori trovati nei log:\n$lastLines\n";
    }
}

echo "\n=== Test completato ===\n";
echo "Se tutti i test sono passati (✓), l'endpoint onlyoffice-auth.php è stato corretto.\n";
?>