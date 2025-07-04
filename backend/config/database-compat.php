<?php
/**
 * Classe di compatibilità Database
 * Wrapper per mantenere compatibilità con il codice esistente
 */

class Database {
    private static $instance = null;
    private $pdo = null;
    
    private function __construct() {
        // Ottieni la connessione PDO usando db_connect()
        if (function_exists('db_connect')) {
            $this->pdo = db_connect();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getPdo() {
        if ($this->pdo === null && function_exists('db_connect')) {
            $this->pdo = db_connect();
        }
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        if (function_exists('db_query')) {
            return db_query($sql, $params);
        }
        
        // Fallback se db_query non è disponibile
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function prepare($sql) {
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        return $pdo->prepare($sql);
    }
    
    public function insert($table, $data) {
        if (function_exists('db_insert')) {
            return db_insert($table, $data);
        }
        
        // Fallback implementation
        $columns = array_keys($data);
        $values = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO $table (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
        
        $stmt = $this->query($sql, $data);
        return $this->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        if (function_exists('db_update')) {
            return db_update($table, $data, $where, $whereParams);
        }
        
        // Fallback implementation
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        $sql = "UPDATE $table SET " . implode(", ", $setParts) . " WHERE $where";
        $params = array_merge($params, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }
    
    public function beginTransaction() {
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        return $pdo->beginTransaction();
    }
    
    public function commit() {
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        return $pdo->commit();
    }
    
    public function rollback() {
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        return $pdo->rollback();
    }
    
    public function lastInsertId() {
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        return $pdo->lastInsertId();
    }
    
    public function tableExists($tableName) {
        try {
            $stmt = $this->query("SHOW TABLES LIKE ?", [$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function exec($sql) {
        $pdo = $this->getPdo();
        if (!$pdo) {
            throw new Exception("Database connection not available");
        }
        return $pdo->exec($sql);
    }
    
    public function getConnection() {
        return $this->getPdo();
    }
}
