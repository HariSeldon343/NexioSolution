<?php
/**
 * Adapter per integrare il filesystem avanzato con il database esistente
 * Mappa i campi esistenti con quelli richiesti dal nuovo sistema
 */

class AdvancedFilesystemAdapter 
{
    private static $instance = null;
    private $fieldMapping;
    
    private function __construct() {
        $this->fieldMapping = require_once BASE_PATH . '/backend/config/filesystem-field-mapping.php';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ottiene cartelle con campi mappati per compatibilità
     */
    public function getFolders($companyId, $parentId = null) {
        $whereClause = "azienda_id = ?";
        $params = [$companyId];
        
        if ($parentId === null) {
            $whereClause .= " AND parent_id IS NULL";
        } else {
            $whereClause .= " AND parent_id = ?";
            $params[] = $parentId;
        }
        
        $sql = $this->fieldMapping['queries']['folders_select'] . " WHERE $whereClause ORDER BY nome";
        
        return db_query($sql, $params)->fetchAll();
    }
    
    /**
     * Ottiene documenti con campi mappati per compatibilità
     */
    public function getDocuments($companyId, $folderId = null) {
        $whereClause = "azienda_id = ?";
        $params = [$companyId];
        
        if ($folderId !== null) {
            $whereClause .= " AND cartella_id = ?";
            $params[] = $folderId;
        }
        
        $sql = $this->fieldMapping['queries']['documents_select'] . " WHERE $whereClause ORDER BY titolo";
        
        return db_query($sql, $params)->fetchAll();
    }
    
    /**
     * Crea una cartella usando i campi corretti
     */
    public function createFolder($data, $companyId, $userId) {
        $mapping = $this->fieldMapping['insert_helpers']['folders'];
        $insertData = [];
        
        foreach ($data as $key => $value) {
            if (isset($mapping[$key])) {
                $insertData[$mapping[$key]] = $value;
            }
        }
        
        // Aggiungi campi obbligatori
        $insertData['azienda_id'] = $companyId;
        $insertData['creato_da'] = $userId;
        
        // Sincronizza anche i campi alternativi se esistono
        if (db_column_exists('cartelle', 'created_by_alt')) {
            $insertData['created_by_alt'] = $userId;
        }
        
        // Calcola percorso completo
        if (isset($insertData['parent_id']) && $insertData['parent_id']) {
            $parent = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$insertData['parent_id']])->fetch();
            if ($parent) {
                $insertData['percorso_completo'] = $parent['percorso_completo'] . '/' . $insertData['nome'];
            }
        } else {
            $insertData['percorso_completo'] = $insertData['nome'];
        }
        
        return db_insert('cartelle', $insertData);
    }
    
    /**
     * Crea un documento usando i campi corretti
     */
    public function createDocument($data, $companyId, $userId) {
        $mapping = $this->fieldMapping['insert_helpers']['documents'];
        $insertData = [];
        
        foreach ($data as $key => $value) {
            if (isset($mapping[$key])) {
                $insertData[$mapping[$key]] = $value;
            }
        }
        
        // Aggiungi campi obbligatori
        $insertData['azienda_id'] = $companyId;
        $insertData['creato_da'] = $userId;
        
        // Sincronizza anche i campi alternativi se esistono
        if (db_column_exists('documenti', 'created_by_alt')) {
            $insertData['created_by_alt'] = $userId;
        }
        
        // Gestisci file_size in modo compatibile
        if (isset($data['file_size'])) {
            if (db_column_exists('documenti', 'file_size_alt')) {
                $insertData['file_size_alt'] = $data['file_size'];
            }
            if (db_column_exists('documenti', 'dimensione_file')) {
                $insertData['dimensione_file'] = $data['file_size'];
            }
            if (db_column_exists('documenti', 'dimensione_bytes')) {
                $insertData['dimensione_bytes'] = $data['file_size'];
            }
        }
        
        return db_insert('documenti', $insertData);
    }
    
    /**
     * Aggiorna una cartella
     */
    public function updateFolder($id, $data, $companyId, $userId) {
        $mapping = $this->fieldMapping['insert_helpers']['folders'];
        $updateData = [];
        
        foreach ($data as $key => $value) {
            if (isset($mapping[$key])) {
                $updateData[$mapping[$key]] = $value;
            }
        }
        
        // Aggiungi campi di aggiornamento
        $updateData['aggiornato_da'] = $userId;
        
        // Sincronizza anche i campi alternativi se esistono
        if (db_column_exists('cartelle', 'last_modified_by')) {
            $updateData['last_modified_by'] = $userId;
        }
        
        return db_update('cartelle', $updateData, 'id = ? AND azienda_id = ?', [$id, $companyId]);
    }
    
    /**
     * Aggiorna un documento
     */
    public function updateDocument($id, $data, $companyId, $userId) {
        $mapping = $this->fieldMapping['insert_helpers']['documents'];
        $updateData = [];
        
        foreach ($data as $key => $value) {
            if (isset($mapping[$key])) {
                $updateData[$mapping[$key]] = $value;
            }
        }
        
        // Aggiungi campi di aggiornamento
        $updateData['aggiornato_da'] = $userId;
        
        // Sincronizza anche i campi alternativi se esistono
        if (db_column_exists('documenti', 'last_modified_by')) {
            $updateData['last_modified_by'] = $userId;
        }
        
        // Gestisci file_size in modo compatibile
        if (isset($data['file_size'])) {
            if (db_column_exists('documenti', 'file_size_alt')) {
                $updateData['file_size_alt'] = $data['file_size'];
            }
        }
        
        return db_update('documenti', $updateData, 'id = ? AND azienda_id = ?', [$id, $companyId]);
    }
    
    /**
     * Elimina una cartella (soft delete)
     */
    public function deleteFolder($id, $companyId, $userId) {
        return db_update('cartelle', [
            'stato' => 'cestino',
            'aggiornato_da' => $userId
        ], 'id = ? AND azienda_id = ?', [$id, $companyId]);
    }
    
    /**
     * Elimina un documento (soft delete)
     */
    public function deleteDocument($id, $companyId, $userId) {
        return db_update('documenti', [
            'stato' => 'archiviato',
            'aggiornato_da' => $userId
        ], 'id = ? AND azienda_id = ?', [$id, $companyId]);
    }
    
    /**
     * Ricerca documenti con filtri
     */
    public function searchDocuments($companyId, $query = '', $filters = []) {
        $sql = $this->fieldMapping['queries']['documents_select'];
        $whereConditions = ["azienda_id = ?"];
        $params = [$companyId];
        
        // Filtro per query di ricerca
        if (!empty($query)) {
            $whereConditions[] = "(titolo LIKE ? OR contenuto LIKE ? OR contenuto_html LIKE ?)";
            $searchParam = "%$query%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Filtri aggiuntivi
        if (!empty($filters['folder_id'])) {
            $whereConditions[] = "cartella_id = ?";
            $params[] = $filters['folder_id'];
        }
        
        if (!empty($filters['document_type'])) {
            $whereConditions[] = "tipo_documento = ?";
            $params[] = $filters['document_type'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "stato = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "data_creazione >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "data_creazione <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
        $sql .= " ORDER BY data_creazione DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        return db_query($sql, $params)->fetchAll();
    }
    
    /**
     * Incrementa il contatore di download
     */
    public function incrementDownloadCount($documentId, $companyId) {
        $updateData = ['aggiornato_da' => $_SESSION['user_id'] ?? null];
        
        if (db_column_exists('documenti', 'download_count')) {
            // Usa SQL per incrementare atomicamente
            db_query("UPDATE documenti SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ? AND azienda_id = ?", 
                    [$documentId, $companyId]);
        }
        
        if (db_column_exists('documenti', 'last_accessed')) {
            $updateData['last_accessed'] = date('Y-m-d H:i:s');
        }
        
        if (!empty($updateData)) {
            db_update('documenti', $updateData, 'id = ? AND azienda_id = ?', [$documentId, $companyId]);
        }
    }
    
    /**
     * Verifica se una tabella/colonna esiste per evitare errori
     */
    public function checkCompatibility() {
        $status = [
            'tables' => [],
            'columns' => [],
            'missing' => []
        ];
        
        // Verifica tabelle principali
        $requiredTables = ['cartelle', 'documenti', 'utenti', 'aziende'];
        foreach ($requiredTables as $table) {
            $status['tables'][$table] = db_table_exists($table);
            if (!$status['tables'][$table]) {
                $status['missing'][] = "Tabella mancante: $table";
            }
        }
        
        // Verifica colonne essenziali
        $requiredColumns = [
            'cartelle' => ['id', 'nome', 'azienda_id', 'creato_da'],
            'documenti' => ['id', 'titolo', 'azienda_id', 'creato_da']
        ];
        
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                $exists = db_column_exists($table, $column);
                $status['columns'][$table][$column] = $exists;
                if (!$exists) {
                    $status['missing'][] = "Colonna mancante: $table.$column";
                }
            }
        }
        
        return $status;
    }
}