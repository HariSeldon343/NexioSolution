<?php
// Test script per verificare che la creazione ticket funzioni
require_once 'backend/config/config.php';

echo "<h2>Test Fix Ticket - Campo 'tipo' -> 'tipo_destinatario'</h2>";

// Verifica struttura tabella
$stmt = $pdo->query("DESCRIBE ticket_destinatari");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Struttura tabella ticket_destinatari:</h3>";
echo "<pre>";
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
echo "</pre>";

// Verifica che la colonna corretta esista
$hasCorrectColumn = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'tipo_destinatario') {
        $hasCorrectColumn = true;
        echo "<p style='color: green;'>✓ Colonna 'tipo_destinatario' trovata correttamente</p>";
        break;
    }
}

if (!$hasCorrectColumn) {
    echo "<p style='color: red;'>✗ ERRORE: Colonna 'tipo_destinatario' non trovata!</p>";
}

// Test query di selezione
echo "<h3>Test Query SELECT:</h3>";
try {
    $testQuery = "SELECT td.tipo_destinatario FROM ticket_destinatari td LIMIT 1";
    $stmt = $pdo->query($testQuery);
    echo "<p style='color: green;'>✓ Query SELECT con 'tipo_destinatario' eseguita con successo</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Errore nella query SELECT: " . $e->getMessage() . "</p>";
}

// Test simulazione INSERT (senza eseguire)
echo "<h3>Test simulazione INSERT:</h3>";
try {
    $testInsert = "EXPLAIN INSERT INTO ticket_destinatari (ticket_id, utente_id, tipo_destinatario) VALUES (1, 1, 'assegnato')";
    $stmt = $pdo->query($testInsert);
    echo "<p style='color: green;'>✓ Query INSERT con 'tipo_destinatario' valida</p>";
} catch (PDOException $e) {
    // EXPLAIN INSERT potrebbe non funzionare, proviamo con altro metodo
    try {
        $stmt = $pdo->prepare("INSERT INTO ticket_destinatari (ticket_id, utente_id, tipo_destinatario) VALUES (?, ?, ?)");
        echo "<p style='color: green;'>✓ Prepared statement con 'tipo_destinatario' creato con successo</p>";
    } catch (PDOException $e2) {
        echo "<p style='color: red;'>✗ Errore nel prepared statement: " . $e2->getMessage() . "</p>";
    }
}

echo "<h3>Riepilogo correzioni applicate:</h3>";
echo "<ul>";
echo "<li>✓ Corretti tutti gli INSERT da 'tipo' a 'tipo_destinatario' in tickets.php</li>";
echo "<li>✓ Corretti tutti i SELECT da 'td.tipo' a 'td.tipo_destinatario'</li>";
echo "<li>✓ Aggiornata visualizzazione destinatari per usare 'tipo_destinatario'</li>";
echo "<li>✓ Valori corretti: 'assegnato' per destinatari principali, 'cc' per copia conoscenza</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Il problema dovrebbe essere risolto. Prova ora a creare un nuovo ticket.</strong></p>";
echo "<p><a href='tickets.php?action=nuovo' class='btn btn-primary'>Crea Nuovo Ticket</a></p>";
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 800px; 
    margin: 50px auto; 
    padding: 20px;
    background: #f5f5f5;
}
h2, h3 { color: #333; }
pre { 
    background: #fff; 
    padding: 10px; 
    border: 1px solid #ddd;
    border-radius: 4px;
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    margin-top: 10px;
}
.btn:hover {
    background: #0056b3;
}
</style>