<?php
// Script per verificare se la colonna contenuto_html esiste nella tabella documenti
require_once __DIR__ . '/../config/config.php';

echo "<h1>Verifica esistenza colonna contenuto_html</h1>";

try {
    // Connessione al database
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p>Connessione al database stabilita.</p>";
    
    // Verifica se la colonna esiste già
    $stmt = $pdo->query("SHOW COLUMNS FROM documenti LIKE 'contenuto_html'");
    $columnExists = ($stmt->rowCount() > 0);
    
    if ($columnExists) {
        echo "<p style='color: green; font-weight: bold;'>✅ La colonna contenuto_html esiste già nella tabella documenti.</p>";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ La colonna contenuto_html non esiste ancora nella tabella documenti.</p>";
        
        // Aggiungi la colonna se non esiste
        echo "<p>Tentativo di aggiungere la colonna...</p>";
        
        try {
            $pdo->exec("ALTER TABLE documenti ADD COLUMN contenuto_html LONGTEXT DEFAULT NULL AFTER contenuto");
            echo "<p style='color: green; font-weight: bold;'>✅ Colonna contenuto_html aggiunta con successo alla tabella documenti!</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Errore durante l'aggiunta della colonna: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Errore di connessione al database: " . $e->getMessage() . "</p>";
}

echo "<p><a href='../editor/index.php' style='display: inline-block; padding: 10px 15px; background: #0078d4; color: white; text-decoration: none; border-radius: 4px;'>Vai all'Editor</a></p>";
?> 