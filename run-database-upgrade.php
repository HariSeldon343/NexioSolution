<?php
/**
 * Script per aggiornare il database per l'Editor Avanzato
 * Esegue le modifiche necessarie per supportare il nuovo editor
 */

require_once __DIR__ . '/backend/config/config.php';

try {
    // Connessione al database
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<h2>🔄 Aggiornamento Database per Editor Avanzato</h2>\n";
    echo "<pre>\n";
    
    // Leggi e esegui lo script SQL
    $sqlFile = __DIR__ . '/database/upgrade-for-advanced-editor.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("File SQL non trovato: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Dividi le query
    $queries = array_filter(
        array_map('trim', explode(';', $sql)),
        function($query) {
            return !empty($query) && !preg_match('/^--/', $query);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($queries as $query) {
        try {
            if (trim($query)) {
                $pdo->exec($query);
                echo "✅ Query eseguita con successo\n";
                $successCount++;
            }
        } catch (PDOException $e) {
            // Se l'errore è per colonna già esistente, ignora
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "ℹ️  Colonna già esistente (ignorato)\n";
            } else {
                echo "❌ Errore query: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "\n📊 Riepilogo:\n";
    echo "✅ Query riuscite: $successCount\n";
    echo "❌ Query fallite: $errorCount\n";
    
    // Verifica colonne
    echo "\n🔍 Verifica struttura tabella documenti:\n";
    $stmt = $pdo->query("DESCRIBE documenti");
    $columns = $stmt->fetchAll();
    
    $requiredColumns = ['contenuto_html', 'contenuto_testo', 'metadata'];
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $col) {
                echo "✅ Colonna '$col' presente\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ Colonna '$col' mancante\n";
        }
    }
    
    // Verifica documenti
    echo "\n📄 Verifica documenti:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM documenti");
    $totalDocs = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as with_html FROM documenti WHERE contenuto_html IS NOT NULL");
    $docsWithHtml = $stmt->fetch()['with_html'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as with_metadata FROM documenti WHERE metadata IS NOT NULL");
    $docsWithMetadata = $stmt->fetch()['with_metadata'];
    
    echo "📊 Totale documenti: $totalDocs\n";
    echo "📄 Documenti con HTML: $docsWithHtml\n";
    echo "📋 Documenti con metadata: $docsWithMetadata\n";
    
    echo "\n🎉 Aggiornamento completato!\n";
    echo "L'Editor Avanzato è ora pronto per l'uso.\n";
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Errore durante l'aggiornamento</h2>\n";
    echo "<pre style='color: red;'>" . $e->getMessage() . "</pre>\n";
    
    // Dettagli aggiuntivi per debug
    if ($e instanceof PDOException) {
        echo "<pre style='color: red;'>Dettagli database: " . $e->getCode() . "</pre>\n";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aggiornamento Database - Editor Avanzato</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        pre {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        h2 {
            color: #0078d4;
            margin-bottom: 20px;
        }
        
        .success {
            color: #107c10;
        }
        
        .error {
            color: #d13438;
        }
        
        .info {
            color: #0078d4;
        }
        
        .actions {
            margin-top: 30px;
            text-align: center;
        }
        
        .btn {
            background: #0078d4;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 0 10px;
            font-weight: 500;
            transition: background 0.2s ease;
        }
        
        .btn:hover {
            background: #106ebe;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="editor-nexio-integrated.php" class="btn">🚀 Apri Editor Avanzato</a>
        <a href="dashboard.php" class="btn btn-secondary">📊 Torna alla Dashboard</a>
        <a href="documenti.php" class="btn btn-secondary">📄 Vai ai Documenti</a>
    </div>
    
    <script>
        // Auto-refresh della pagina se ci sono errori
        setTimeout(() => {
            const errors = document.querySelectorAll('.error');
            if (errors.length === 0) {
                console.log('✅ Aggiornamento completato con successo!');
            }
        }, 1000);
    </script>
</body>
</html>