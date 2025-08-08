<?php
/**
 * Database Error Handler for Nexio Platform
 * Handles database connection errors without breaking session management
 */

class DatabaseErrorHandler {
    private static $instance = null;
    private $error = null;
    private $errorCode = null;
    private $errorMessage = null;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set database connection error
     */
    public function setError($exception) {
        $this->error = $exception;
        $this->errorCode = $exception->getCode();
        $this->errorMessage = $exception->getMessage();
    }
    
    /**
     * Check if there's a database error
     */
    public function hasError() {
        return $this->error !== null;
    }
    
    /**
     * Get error code
     */
    public function getErrorCode() {
        return $this->errorCode;
    }
    
    /**
     * Get error message
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }
    
    /**
     * Check if error is MySQL not running
     */
    public function isMySQLNotRunning() {
        return $this->errorCode == 2002 || 
               (strpos($this->errorMessage, '2002') !== false) ||
               (strpos($this->errorMessage, 'Connection refused') !== false);
    }
    
    /**
     * Display error page (only call after headers are sent)
     */
    public function displayErrorPage() {
        $isLocalhost = defined('DB_HOST') && DB_HOST === 'localhost';
        $errorMessage = htmlspecialchars($this->errorMessage);
        
        if ($this->isMySQLNotRunning() && $isLocalhost) {
            $this->displayMySQLNotRunningError($errorMessage);
        } else {
            $this->displayGenericError($errorMessage, $isLocalhost);
        }
    }
    
    /**
     * Display MySQL not running error
     */
    private function displayMySQLNotRunningError($errorMessage) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Errore Database - <?php echo APP_NAME ?? 'Nexio'; ?></title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    max-width: 600px; 
                    margin: 50px auto; 
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .error-box { 
                    background: #fee2e2; 
                    border: 1px solid #fecaca; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin-bottom: 20px;
                }
                .solution-box { 
                    background: #dbeafe; 
                    border: 1px solid #bfdbfe; 
                    border-radius: 8px; 
                    padding: 20px; 
                }
                h2 { color: #dc2626; margin-top: 0; } 
                h3 { color: #1d4ed8; margin-top: 0; }
                ol { margin-left: 20px; } 
                li { margin-bottom: 8px; }
                a { color: #2563eb; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .icon { font-size: 1.2em; margin-right: 5px; }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h2>❌ Database non connesso</h2>
                <p><strong>Errore:</strong> <?php echo $errorMessage; ?></p>
                <p>Il server MySQL/MariaDB non è in esecuzione.</p>
            </div>
            
            <div class='solution-box'>
                <h3>🔧 Come risolvere:</h3>
                <ol>
                    <li>Apri il <strong>Pannello di Controllo XAMPP</strong></li>
                    <li>Clicca su <strong>"Start"</strong> accanto a <strong>MySQL</strong></li>
                    <li>Attendi che lo stato diventi verde</li>
                    <li>Ricarica questa pagina</li>
                </ol>
                
                <p><strong>Link utili:</strong></p>
                <ul>
                    <li><a href='http://localhost/phpmyadmin' target='_blank'>
                        <span class='icon'>📊</span>phpMyAdmin
                    </a></li>
                </ul>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Display generic database error
     */
    private function displayGenericError($errorMessage, $isLocalhost) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Errore Database - <?php echo APP_NAME ?? 'Nexio'; ?></title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    max-width: 600px; 
                    margin: 50px auto; 
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .error-box { 
                    background: #fee2e2; 
                    border: 1px solid #fecaca; 
                    border-radius: 8px; 
                    padding: 20px; 
                }
                h2 { color: #dc2626; margin-top: 0; }
                .error-details {
                    background: #fff;
                    border: 1px solid #e5e7eb;
                    border-radius: 4px;
                    padding: 10px;
                    margin-top: 10px;
                    font-family: monospace;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h2>❌ Errore di connessione al database</h2>
                <?php if ($isLocalhost): ?>
                    <div class='error-details'><?php echo $errorMessage; ?></div>
                <?php else: ?>
                    <p>Si è verificato un errore di connessione al database.</p>
                    <p>Contattare l'amministratore del sistema.</p>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get simple error message for logging
     */
    public function getLogMessage() {
        return "Database connection error: " . $this->errorMessage;
    }
}