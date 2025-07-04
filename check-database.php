<?php
/**
 * Script per verificare la connessione al database
 */

// Configurazione database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'piattaforma_collaborativa';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verifica Database</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; }
        .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 5px; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h1>🔧 Diagnostica Database XAMPP</h1>";

// 1. Verifica se XAMPP è in esecuzione
echo "<h2>1. Verifica servizi XAMPP</h2>";

$xampp_running = false;
$mysql_port = 3306;

// Tenta di connettersi alla porta MySQL
$connection = @fsockopen($host, $mysql_port, $errno, $errstr, 5);
if ($connection) {
    echo "<div class='success'>✅ Servizio MySQL è in esecuzione sulla porta $mysql_port</div>";
    fclose($connection);
    $xampp_running = true;
} else {
    echo "<div class='error'>❌ Servizio MySQL NON è in esecuzione sulla porta $mysql_port</div>";
    echo "<div class='warning'>
        <strong>Azione richiesta:</strong>
        <ol>
            <li>Apri il Pannello di Controllo XAMPP</li>
            <li>Avvia il servizio <strong>MySQL</strong> (clicca su \"Start\")</li>
            <li>Ricarica questa pagina</li>
        </ol>
    </div>";
}

// 2. Verifica connessione PDO
echo "<h2>2. Test connessione PDO</h2>";

if ($xampp_running) {
    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "<div class='success'>✅ Connessione PDO riuscita</div>";
        
        // 3. Verifica database
        echo "<h2>3. Verifica database '$database'</h2>";
        
        $stmt = $pdo->query("SHOW DATABASES LIKE '$database'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✅ Database '$database' esiste</div>";
        } else {
            echo "<div class='warning'>⚠️ Database '$database' non esiste</div>";
            
            // Crea database
            echo "<h3>Creazione database...</h3>";
            try {
                $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "<div class='success'>✅ Database '$database' creato con successo</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ Errore creazione database: " . $e->getMessage() . "</div>";
            }
        }
        
        // 4. Verifica tabelle
        echo "<h2>4. Verifica tabelle</h2>";
        
        try {
            $pdo->exec("USE `$database`");
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                echo "<div class='warning'>⚠️ Nessuna tabella trovata nel database</div>";
                echo "<div class='info'>
                    <strong>Azione richiesta:</strong><br>
                    Esegui lo script di setup: <a href='setup-database.php'>setup-database.php</a>
                </div>";
            } else {
                echo "<div class='success'>✅ Trovate " . count($tables) . " tabelle:</div>";
                echo "<pre>" . implode(', ', $tables) . "</pre>";
                
                // Verifica tabella utenti
                if (in_array('utenti', $tables)) {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM utenti");
                    $count = $stmt->fetchColumn();
                    echo "<div class='info'>👥 Utenti nel sistema: $count</div>";
                }
                
                // Verifica tabella aziende
                if (in_array('aziende', $tables)) {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM aziende");
                    $count = $stmt->fetchColumn();
                    echo "<div class='info'>🏢 Aziende nel sistema: $count</div>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Errore accesso database: " . $e->getMessage() . "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Errore connessione PDO: " . $e->getMessage() . "</div>";
        
        if (strpos($e->getMessage(), '2002') !== false) {
            echo "<div class='warning'>
                <strong>Questo errore indica che MySQL non è in esecuzione.</strong><br>
                Controlla il Pannello di Controllo XAMPP e avvia MySQL.
            </div>";
        }
    }
}

// 5. Informazioni di sistema
echo "<h2>5. Informazioni di sistema</h2>";
echo "<div class='info'>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>PDO MySQL:</strong> " . (extension_loaded('pdo_mysql') ? '✅ Disponibile' : '❌ Non disponibile') . "<br>";
echo "<strong>Sistema:</strong> " . php_uname() . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "</div>";

// 6. Link utili
echo "<h2>6. Link utili</h2>";
echo "<div class='info'>";
echo "<a href='http://localhost/phpmyadmin' target='_blank'>📊 Apri phpMyAdmin</a><br>";
echo "<a href='setup-database.php'>🔧 Setup Database</a><br>";
echo "<a href='gestione-utenti.php'>👥 Torna a Gestione Utenti</a><br>";
echo "</div>";

echo "</body></html>";
?>