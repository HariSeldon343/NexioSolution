<?php
/**
 * Configurazione e connessione al database
 */

// Prevenzione accesso diretto - commentiamo per permettere l'inclusione
// if (!defined('ROOT_PATH')) {
//     exit('Accesso negato');
// }

// Se le costanti non sono definite, definiscile direttamente
// Questo evita dipendenze circolari con config.php
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'nexiosol');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

try {
    // Verifica che le costanti siano definite
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
        throw new Exception('Database configuration constants not defined');
    }
    
    // Creazione connessione PDO
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    // Aggiungi MYSQL_ATTR_INIT_COMMAND
    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
    } else {
        $options[1002] = "SET NAMES utf8mb4"; // Valore numerico della costante
    }
    
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
if (!function_exists('db_query')) {
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
}

if (!function_exists('db_fetch')) {
    function db_fetch($sql, $params = []) {
        $stmt = db_query($sql, $params);
        return $stmt->fetch();
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all($sql, $params = []) {
        $stmt = db_query($sql, $params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('db_insert')) {
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
}

if (!function_exists('db_update')) {
    function db_update($table, $data, $where, $whereParams = []) {
    global $pdo;
    $setParts = [];
    $allParams = [];
    $paramCounter = 0;
    
    // Use numbered placeholders for SET clause
    foreach ($data as $field => $value) {
        $setParts[] = "{$field} = :set_{$paramCounter}";
        $allParams[":set_{$paramCounter}"] = $value;
        $paramCounter++;
    }
    
    // Replace ? with numbered placeholders in WHERE clause
    $whereProcessed = $where;
    if (!empty($whereParams)) {
        $whereCounter = 0;
        $whereProcessed = preg_replace_callback('/\?/', function($match) use (&$whereCounter, &$allParams, $whereParams) {
            $placeholder = ":where_{$whereCounter}";
            $allParams[$placeholder] = $whereParams[$whereCounter];
            $whereCounter++;
            return $placeholder;
        }, $where);
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$whereProcessed}";
    
    $stmt = $pdo->prepare($sql);
    foreach ($allParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    $stmt->execute();
    return $stmt->rowCount();
    }
}

if (!function_exists('db_delete')) {
    function db_delete($table, $where, $whereParams = []) {
    global $pdo;
    $sql = "DELETE FROM {$table} WHERE {$where}";
    
    $stmt = $pdo->prepare($sql);
    
    // Handle both numeric and associative arrays
    if (!empty($whereParams)) {
        if (array_keys($whereParams) === range(0, count($whereParams) - 1)) {
            // Numeric array - bind by position
            foreach ($whereParams as $index => $value) {
                $stmt->bindValue($index + 1, $value);
            }
        } else {
            // Associative array - bind by name
            foreach ($whereParams as $param => $value) {
                $stmt->bindValue($param, $value);
            }
        }
    }
    
    $stmt->execute();
    return $stmt->rowCount();
    }
}

if (!function_exists('db_exists')) {
    function db_exists($table, $where, $whereParams = []) {
    $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
    $stmt = db_query($sql, $whereParams);
    return $stmt->fetch() !== false;
    }
}

if (!function_exists('db_count')) {
    function db_count($table, $where = '1=1', $whereParams = []) {
    $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
    $result = db_fetch($sql, $whereParams);
    return (int)$result['count'];
    }
}

// Funzioni per la gestione delle transazioni
if (!function_exists('db_begin_transaction')) {
    function db_begin_transaction() {
    global $pdo;
    try {
        return $pdo->beginTransaction();
    } catch (PDOException $e) {
        error_log("Errore avvio transazione: " . $e->getMessage());
        throw $e;
    }
    }
}

if (!function_exists('db_commit')) {
    function db_commit() {
    global $pdo;
    try {
        return $pdo->commit();
    } catch (PDOException $e) {
        error_log("Errore commit transazione: " . $e->getMessage());
        throw $e;
    }
    }
}

if (!function_exists('db_rollback')) {
    function db_rollback() {
    global $pdo;
    try {
        return $pdo->rollBack();
    } catch (PDOException $e) {
        error_log("Errore rollback transazione: " . $e->getMessage());
        throw $e;
    }
    }
}

if (!function_exists('db_in_transaction')) {
    function db_in_transaction() {
    global $pdo;
    return $pdo->inTransaction();
    }
}

// Funzione per testare la connessione
if (!function_exists('test_db_connection')) {
    function test_db_connection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
    }
}

/**
 * Verifica se una tabella esiste nel database corrente
 * Utilizza INFORMATION_SCHEMA per compatibilità con prepared statements
 * 
 * @param string $tableName Nome della tabella da verificare
 * @return bool True se la tabella esiste, false altrimenti
 */
if (!function_exists('db_table_exists')) {
    function db_table_exists($tableName) {
    try {
        $result = db_query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1", 
            [$tableName]
        );
        return $result->fetch() !== false;
    } catch (Exception $e) {
        error_log("Errore verifica esistenza tabella '$tableName': " . $e->getMessage());
        return false;
    }
    }
}

/**
 * Verifica se una colonna esiste in una tabella
 * 
 * @param string $tableName Nome della tabella
 * @param string $columnName Nome della colonna
 * @return bool True se la colonna esiste, false altrimenti
 */
if (!function_exists('db_column_exists')) {
    function db_column_exists($tableName, $columnName) {
    try {
        $result = db_query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1", 
            [$tableName, $columnName]
        );
        return $result->fetch() !== false;
    } catch (Exception $e) {
        error_log("Errore verifica esistenza colonna '$columnName' in tabella '$tableName': " . $e->getMessage());
        return false;
    }
    }
}

/**
 * Ottiene la lista di tutte le tabelle nel database corrente
 * 
 * @return array Array di nomi delle tabelle
 */
if (!function_exists('db_get_tables')) {
    function db_get_tables() {
    try {
        $result = db_query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             ORDER BY TABLE_NAME"
        );
        return $result->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Errore recupero lista tabelle: " . $e->getMessage());
        return [];
    }
    }
} 