<?php
namespace Nexio\Utils;

/**
 * Namespace wrapper per ActivityLogger
 * Redirige le chiamate alla classe globale ActivityLogger
 */
class ActivityLogger
{
    private static ?ActivityLogger $instance = null;
    private $globalLogger;
    
    private function __construct()
    {
        // Usa la classe globale ActivityLogger
        $this->globalLogger = \ActivityLogger::getInstance();
    }
    
    public static function getInstance(): ActivityLogger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log($azione, $entita_tipo, $entita_id, $dettagli = [])
    {
        // Adatta i parametri per la classe globale
        $dettagli_str = is_array($dettagli) ? json_encode($dettagli) : $dettagli;
        return $this->globalLogger->log($entita_tipo, $azione, $entita_id, $dettagli_str);
    }
    
    public function logError($message, $context = [])
    {
        $dettagli = array_merge(['error' => $message], $context);
        return $this->log('error', 'system', null, $dettagli);
    }
    
    public function logSecurity($event, $details = [])
    {
        $dettagli = array_merge(['security_event' => $event], $details);
        return $this->log('security', 'system', null, $dettagli);
    }
    
    // Metodi di compatibilità
    public function logDocumentAction($action, $documentId, $details = [])
    {
        return $this->log($action, 'documento', $documentId, $details);
    }
    
    public function logFolderAction($action, $folderId, $details = [])
    {
        return $this->log($action, 'cartella', $folderId, $details);
    }
    
    public function logISOAction($action, $details = [])
    {
        return $this->log($action, 'iso_system', null, $details);
    }
    
    // Previeni clonazione e deserializzazione
    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}