<?php
namespace Nexio\Utils;

use Exception;
use PDO;

/**
 * MultiFileManager - Gestione upload multipli di file
 */
class MultiFileManager {
    private static $instance = null;
    private $uploadDir;
    private $maxFileSize = 104857600; // 100MB
    private $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'
    ];
    
    private function __construct() {
        $this->uploadDir = __DIR__ . '/../../uploads/documenti/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Gestisce upload multiplo di file
     */
    public function handleMultipleUpload($files, $metadata, $aziendaId) {
        $results = [
            'batch_id' => uniqid('batch_'),
            'summary' => [
                'session_id' => uniqid('upload_'),
                'total_files' => count($files),
                'successful' => 0,
                'errors' => 0,
                'total_size' => 0
            ],
            'files' => []
        ];
        
        foreach ($files as $index => $file) {
            try {
                // Validazione file
                $this->validateFile($file);
                
                // Genera nome unico
                $fileName = $this->generateUniqueFileName($file['name']);
                $targetPath = $this->uploadDir . $fileName;
                
                // Sposta il file
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Salva nel database
                    $docId = $this->saveToDatabase($fileName, $file, $metadata['files'][$index] ?? [], $aziendaId);
                    
                    $results['files'][] = [
                        'success' => true,
                        'original_name' => $file['name'],
                        'saved_name' => $fileName,
                        'size' => $file['size'],
                        'document_id' => $docId
                    ];
                    
                    $results['summary']['successful']++;
                    $results['summary']['total_size'] += $file['size'];
                } else {
                    throw new Exception("Impossibile salvare il file");
                }
                
            } catch (Exception $e) {
                $results['files'][] = [
                    'success' => false,
                    'original_name' => $file['name'],
                    'error' => $e->getMessage()
                ];
                $results['summary']['errors']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Valida un file
     */
    private function validateFile($file) {
        // Controlla errori upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Errore upload: " . $this->getUploadErrorMessage($file['error']));
        }
        
        // Controlla dimensione
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception("File troppo grande (max " . ($this->maxFileSize / 1048576) . "MB)");
        }
        
        // Controlla estensione
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions)) {
            throw new Exception("Tipo file non permesso");
        }
        
        return true;
    }
    
    /**
     * Genera nome file unico
     */
    private function generateUniqueFileName($originalName) {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        
        return $safeName . '_' . time() . '_' . uniqid() . '.' . $ext;
    }
    
    /**
     * Salva documento nel database
     */
    private function saveToDatabase($fileName, $fileInfo, $metadata, $aziendaId) {
        // Auth non è in namespace, quindi non serve il backslash
        $auth = Auth::getInstance();
        $user = $auth->getUser();
        
        $sql = "INSERT INTO documenti (
                    azienda_id, titolo, file_path, file_size, file_type,
                    cartella_id, tipo_documento, tags, creato_da, data_creazione
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $aziendaId,
            $metadata['titolo'] ?? $fileInfo['name'],
            'uploads/documenti/' . $fileName,
            $fileInfo['size'],
            $fileInfo['type'],
            $metadata['cartella_id'] ?? null,
            $metadata['tipo_documento'] ?? 'documento_generico',
            isset($metadata['tags']) ? json_encode($metadata['tags']) : null,
            $user['id']
        ];
        
        try {
            $stmt = db_query($sql, $params);
            return db_connection()->lastInsertId();
        } catch (Exception $e) {
            error_log("MultiFileManager saveToDatabase error: " . $e->getMessage());
            throw new Exception("Errore salvataggio database: " . $e->getMessage());
        }
    }
    
    /**
     * Ottiene messaggio errore upload
     */
    private function getUploadErrorMessage($code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File troppo grande per il server',
            UPLOAD_ERR_FORM_SIZE => 'File troppo grande per il form',
            UPLOAD_ERR_PARTIAL => 'Upload parziale',
            UPLOAD_ERR_NO_FILE => 'Nessun file',
            UPLOAD_ERR_NO_TMP_DIR => 'Directory temp mancante',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere',
            UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione'
        ];
        
        return $errors[$code] ?? 'Errore sconosciuto';
    }
}
?>