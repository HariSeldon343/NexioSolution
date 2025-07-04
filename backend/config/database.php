<?php
/**
 * Configurazione e connessione al database
 */

// Prevenzione accesso diretto - commentiamo per permettere l'inclusione
// if (!defined('ROOT_PATH')) {
//     exit('Accesso negato');
// }

try {
    // Creazione connessione PDO
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Imposta il timezone per MySQL
    $pdo->exec("SET time_zone = '+01:00'");
    
} catch (PDOException $e) {
    // Log dell'errore
    error_log("Errore connessione database: " . $e->getMessage());
    
    // In sviluppo mostra l'errore con istruzioni, in produzione messaggio generico
    if (defined('DB_HOST') && DB_HOST === 'localhost') {
        $error_code = $e->getCode();
        $error_message = $e->getMessage();
        
        // Controlla se è un errore di connessione (MySQL non in esecuzione)
        if ($error_code == 2002 || strpos($error_message, '2002') !== false) {
            die("
            <html>
            <head><title>Errore Database</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error-box { background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; }
                .solution-box { background: #dbeafe; border: 1px solid #bfdbfe; border-radius: 8px; padding: 20px; margin-top: 20px; }
                h2 { color: #dc2626; } h3 { color: #1d4ed8; }
                ol { margin-left: 20px; } li { margin-bottom: 8px; }
            </style>
            </head>
            <body>
                <div class='error-box'>
                    <h2>❌ Database non connesso</h2>
                    <p><strong>Errore:</strong> " . htmlspecialchars($error_message) . "</p>
                    <p>Il server MySQL/MariaDB non è in esecuzione.</p>
                </div>
                
                <div class='solution-box'>
                    <h3>🔧 Come risolvere:</h3>
                    <ol>
                        <li>Apri il <strong>Pannello di Controllo XAMPP</strong></li>
                        <li>Clicca su <strong>\"Start\"</strong> accanto a <strong>MySQL</strong></li>
                        <li>Attendi che lo stato diventi verde</li>
                        <li>Ricarica questa pagina</li>
                    </ol>
                    
                    <p><strong>Link utili:</strong></p>
                    <ul>
                        <li><a href='" . APP_PATH . "/check-database.php'>🔍 Diagnostica Database</a></li>
                        <li><a href='http://localhost/phpmyadmin' target='_blank'>📊 phpMyAdmin</a></li>
                    </ul>
                </div>
            </body>
            </html>
            ");
        } else {
            die("Errore di connessione al database: " . htmlspecialchars($error_message) . "<br><br>
                 <a href='" . APP_PATH . "/check-database.php'>🔍 Diagnostica Database</a>");
        }
    } else {
        die("Errore di connessione al database. Contattare l'amministratore.");
    }
}

// Funzioni di utilità per il database
function db_query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Errore query database: " . $e->getMessage() . " - SQL: " . $sql);
        throw $e;
    }
}

function db_fetch($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

function db_insert($table, $data) {
    global $pdo;
    $fields = array_keys($data);
    $placeholders = ':' . implode(', :', $fields);
    $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
    
    $stmt = $pdo->prepare($sql);
    foreach ($data as $field => $value) {
        $stmt->bindValue(":{$field}", $value);
    }
    $stmt->execute();
    return $pdo->lastInsertId();
}

function db_update($table, $data, $where, $whereParams = []) {
    global $pdo;
    $setParts = [];
    foreach (array_keys($data) as $field) {
        $setParts[] = "{$field} = :{$field}";
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
    
    $stmt = $pdo->prepare($sql);
    foreach ($data as $field => $value) {
        $stmt->bindValue(":{$field}", $value);
    }
    foreach ($whereParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    return $stmt->rowCount();
}

function db_delete($table, $where, $whereParams = []) {
    global $pdo;
    $sql = "DELETE FROM {$table} WHERE {$where}";
    
    $stmt = $pdo->prepare($sql);
    foreach ($whereParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    return $stmt->rowCount();
}

function db_exists($table, $where, $whereParams = []) {
    $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
    $stmt = db_query($sql, $whereParams);
    return $stmt->fetch() !== false;
}

function db_count($table, $where = '1=1', $whereParams = []) {
    $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
    $result = db_fetch($sql, $whereParams);
    return (int)$result['count'];
}

// Funzione per testare la connessione
function test_db_connection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
} 