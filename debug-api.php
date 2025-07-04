<?php
/**
 * Debug API Document Editor
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug API Document Editor</h2>";

try {
    require_once __DIR__ . '/backend/config/config.php';
    echo "✅ Config caricato<br>";
    
    // Test connessione database
    $db = db_connection();
    echo "✅ Database connesso<br>";
    
    // Test autenticazione
    $auth = Auth::getInstance();
    echo "✅ Auth instance creato<br>";
    
    if ($auth->isLoggedIn()) {
        $user = $auth->getUser();
        echo "✅ Utente autenticato: {$user['username']}<br>";
        
        // Test query documenti
        $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE creato_da = ?", [$user['id']]);
        $result = $stmt->fetch();
        echo "✅ Documenti utente: {$result['count']}<br>";
        
        // Test inserimento
        echo "<h3>Test inserimento documento:</h3>";
        $title = 'Test Debug ' . date('Y-m-d H:i:s');
        $content = '<p>Contenuto di test</p>';
        $codice = 'DEBUG_' . date('Ymd_His') . '_' . $user['id'];
        
        $stmt = db_query(
            "INSERT INTO documenti (titolo, codice, contenuto, creato_da, data_creazione, ultima_modifica) 
             VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$title, $codice, $content, $user['id']]
        );
        
        if ($stmt) {
            $new_id = db_connection()->lastInsertId();
            echo "✅ Documento inserito con ID: $new_id<br>";
        } else {
            echo "❌ Errore inserimento documento<br>";
        }
        
    } else {
        echo "❌ Utente non autenticato<br>";
        echo "<a href='login.php'>Vai al login</a><br>";
    }
    
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<br><a href='editor-ckeditor.php'>Torna all'editor</a>";
?>