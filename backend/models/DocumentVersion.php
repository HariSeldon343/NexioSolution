<?php
/**
 * Modello per gestione versioni documenti
 * Integrato con TinyMCE Editor e sistema di versionamento
 */

require_once __DIR__ . '/../config/config.php';

class DocumentVersion {
    private $db;
    
    public function __construct() {
        $this->db = db_connection();
    }
    
    /**
     * Crea una nuova versione del documento
     */
    public function addVersion($documentId, $contentHtml, $filePath = null, $userId = null, $userName = null, $isMajor = false, $notes = '') {
        try {
            // Ottieni il numero di versione successivo
            $stmt = db_query(
                "SELECT MAX(version_number) as max_version FROM document_versions WHERE document_id = ?",
                [$documentId]
            );
            $result = $stmt->fetch();
            $newVersionNumber = ($result['max_version'] ?? 0) + 1;
            
            // Disattiva versione corrente precedente
            db_query(
                "UPDATE document_versions SET is_current = 0 WHERE document_id = ? AND is_current = 1",
                [$documentId]
            );
            
            // Genera ID univoco per la versione
            $versionId = 'ver_' . uniqid() . '_' . time();
            
            // Calcola hash del contenuto per deduplicazione
            $hashFile = sha1($contentHtml);
            
            // Inserisci nuova versione
            $stmt = db_query(
                "INSERT INTO document_versions 
                 (id, document_id, version_number, contenuto_html, file_path, 
                  created_by, created_by_name, created_at, is_major, notes, 
                  is_current, hash_file, file_size) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 1, ?, ?)",
                [
                    $versionId,
                    $documentId,
                    $newVersionNumber,
                    $contentHtml,
                    $filePath,
                    $userId,
                    $userName,
                    $isMajor ? 1 : 0,
                    $notes,
                    $hashFile,
                    strlen($contentHtml)
                ]
            );
            
            // Aggiorna documento principale
            db_query(
                "UPDATE documenti 
                 SET contenuto_html = ?, 
                     data_modifica = NOW(), 
                     modificato_da = ?,
                     current_version_id = ?
                 WHERE id = ?",
                [$contentHtml, $userId, $versionId, $documentId]
            );
            
            return [
                'id' => $versionId,
                'version_number' => $newVersionNumber,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Errore creazione versione: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ottieni lista versioni di un documento
     */
    public function getVersions($documentId, $limit = 20) {
        $stmt = db_query(
            "SELECT v.*, u.nome as user_nome, u.cognome as user_cognome
             FROM document_versions v
             LEFT JOIN utenti u ON v.created_by = u.id
             WHERE v.document_id = ?
             ORDER BY v.version_number DESC
             LIMIT ?",
            [$documentId, $limit]
        );
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottieni una versione specifica
     */
    public function getVersion($versionId) {
        $stmt = db_query(
            "SELECT * FROM document_versions WHERE id = ?",
            [$versionId]
        );
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ripristina una versione precedente
     */
    public function restoreVersion($versionId, $userId, $userName) {
        $version = $this->getVersion($versionId);
        if (!$version) {
            throw new Exception('Versione non trovata');
        }
        
        // Crea nuova versione con contenuto ripristinato
        return $this->addVersion(
            $version['document_id'],
            $version['contenuto_html'],
            $version['file_path'],
            $userId,
            $userName,
            true,
            'Ripristinato dalla versione ' . $version['version_number']
        );
    }
    
    /**
     * Confronta due versioni
     */
    public function compareVersions($versionId1, $versionId2) {
        $v1 = $this->getVersion($versionId1);
        $v2 = $this->getVersion($versionId2);
        
        if (!$v1 || !$v2) {
            throw new Exception('Una o entrambe le versioni non trovate');
        }
        
        // Verifica se esiste già un confronto cached
        $stmt = db_query(
            "SELECT * FROM document_version_comparisons 
             WHERE (version1_id = ? AND version2_id = ?) 
                OR (version1_id = ? AND version2_id = ?)",
            [$versionId1, $versionId2, $versionId2, $versionId1]
        );
        
        $comparison = $stmt->fetch();
        
        if (!$comparison) {
            // Genera nuovo confronto
            $diff = $this->generateDiff($v1['contenuto_html'], $v2['contenuto_html']);
            
            // Salva in cache
            db_query(
                "INSERT INTO document_version_comparisons 
                 (id, version1_id, version2_id, diff_html, summary, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    uniqid('cmp_'),
                    $versionId1,
                    $versionId2,
                    $diff['html'],
                    $diff['summary']
                ]
            );
            
            return $diff;
        }
        
        return [
            'html' => $comparison['diff_html'],
            'summary' => $comparison['summary']
        ];
    }
    
    /**
     * Genera diff tra due contenuti HTML
     */
    private function generateDiff($html1, $html2) {
        // Implementazione semplice di diff
        // In produzione, usare una libreria come php-htmldiff
        
        $lines1 = explode("\n", strip_tags($html1));
        $lines2 = explode("\n", strip_tags($html2));
        
        $added = count($lines2) - count($lines1);
        $modified = 0;
        
        for ($i = 0; $i < min(count($lines1), count($lines2)); $i++) {
            if ($lines1[$i] !== $lines2[$i]) {
                $modified++;
            }
        }
        
        return [
            'html' => '<p>Confronto non disponibile in questa versione</p>',
            'summary' => sprintf('%d righe aggiunte, %d modificate', max(0, $added), $modified)
        ];
    }
    
    /**
     * Pulisci versioni vecchie (mantieni solo le ultime N)
     */
    public function cleanOldVersions($documentId, $keepLast = 50) {
        // Ottieni l'ID della versione da cui iniziare a eliminare
        $stmt = db_query(
            "SELECT id FROM document_versions 
             WHERE document_id = ? 
             ORDER BY version_number DESC 
             LIMIT ?, 1000",
            [$documentId, $keepLast]
        );
        
        $versionsToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($versionsToDelete)) {
            $placeholders = str_repeat('?,', count($versionsToDelete) - 1) . '?';
            
            // Elimina versioni vecchie (CASCADE eliminerà anche views e comparisons)
            db_query(
                "DELETE FROM document_versions WHERE id IN ($placeholders)",
                $versionsToDelete
            );
            
            return count($versionsToDelete);
        }
        
        return 0;
    }
    
    /**
     * Registra visualizzazione versione
     */
    public function logView($versionId, $userId, $ipAddress = null) {
        db_query(
            "INSERT INTO document_version_views (id, version_id, user_id, ip_address, viewed_at)
             VALUES (?, ?, ?, ?, NOW())",
            [uniqid('view_'), $versionId, $userId, $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }
}
?>