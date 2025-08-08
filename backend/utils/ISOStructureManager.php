<?php

require_once __DIR__ . '/../config/database.php';

/**
 * ISO Structure Manager - Singleton Service
 * 
 * Enterprise-level document structure management for ISO standards
 * Handles creation, configuration, and maintenance of ISO-compliant folder structures
 * 
 * @package Nexio
 * @version 1.0.0
 * @author Claude Code
 */
class ISOStructureManager
{
    private static ?ISOStructureManager $instance = null;
    
    /** @var array Cache for ISO standards */
    private array $standardsCache = [];
    
    /** @var array Cache for folder templates */
    private array $templatesCache = [];
    
    /** @var array Configuration cache */
    private array $configCache = [];
    
    /** @var float Performance timing for operations */
    private float $lastOperationTime = 0.0;
    
    /**
     * Supported structure types
     */
    public const STRUCTURE_TYPES = [
        'separata' => 'Separate structure for each standard',
        'integrata' => 'Integrated structure with shared folders',
        'personalizzata' => 'Custom structure with standard templates'
    ];
    
    /**
     * Standard ISO codes supported
     */
    public const SUPPORTED_STANDARDS = [
        'ISO9001', 'ISO14001', 'ISO45001', 'GDPR', 'ISO27001'
    ];
    
    /**
     * Folder compliance levels
     */
    public const COMPLIANCE_LEVELS = [
        'obbligatoria' => 'Mandatory folder',
        'raccomandata' => 'Recommended folder', 
        'opzionale' => 'Optional folder',
        'personalizzata' => 'Custom folder'
    ];
    
    private function __construct()
    {
        $this->loadInitialData();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): ISOStructureManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Load initial data into cache
     */
    private function loadInitialData(): void
    {
        try {
            // Load ISO standards
            $standards = db_query("SELECT * FROM iso_standards WHERE attivo = 1 ORDER BY nome")->fetchAll();
            foreach ($standards as $standard) {
                $this->standardsCache[$standard['codice']] = $standard;
            }
            
            // Load folder templates
            $templates = db_query("
                SELECT ft.*, s.codice as standard_codice, s.nome as standard_nome
                FROM iso_folder_templates ft
                JOIN iso_standards s ON ft.standard_id = s.id
                WHERE s.attivo = 1
                ORDER BY s.codice, ft.livello, ft.ordine_visualizzazione
            ")->fetchAll();
            
            foreach ($templates as $template) {
                $standardCode = $template['standard_codice'];
                if (!isset($this->templatesCache[$standardCode])) {
                    $this->templatesCache[$standardCode] = [];
                }
                $this->templatesCache[$standardCode][] = $template;
            }
            
        } catch (Exception $e) {
            error_log("ISOStructureManager: Error loading initial data - " . $e->getMessage());
            throw new Exception("Failed to initialize ISO Structure Manager");
        }
    }
    
    /**
     * Get available ISO standards
     */
    public function getAvailableStandards(): array
    {
        return $this->standardsCache;
    }
    
    /**
     * Get folder templates for a specific standard
     */
    public function getStandardTemplates(string $standardCode): array
    {
        if (!isset($this->templatesCache[$standardCode])) {
            return [];
        }
        
        return $this->buildTemplateHierarchy($this->templatesCache[$standardCode]);
    }
    
    /**
     * Get company ISO configuration
     */
    public function getCompanyConfiguration(?int $companyId): ?array
    {
        // Se non c'è company ID (super_admin senza azienda selezionata), ritorna null
        if ($companyId === null) {
            return null;
        }
        
        $cacheKey = "config_{$companyId}";
        
        if (isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }
        
        try {
            $config = db_query("
                SELECT aic.*, a.nome as azienda_nome
                FROM aziende_iso_config aic
                JOIN aziende a ON aic.azienda_id = a.id
                WHERE aic.azienda_id = ?
            ", [$companyId])->fetch();
            
            if ($config) {
                $config['standards_attivi'] = json_decode($config['standards_attivi'], true);
                $config['configurazione_avanzata'] = json_decode($config['configurazione_avanzata'], true);
                $this->configCache[$cacheKey] = $config;
                return $config;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("ISOStructureManager: Error getting company configuration - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Initialize ISO structure for a company
     */
    public function initializeCompanyStructure(
        int $companyId,
        string $structureType,
        array $activeStandards,
        array $advancedConfig = [],
        int $createdBy = null
    ): array {
        $startTime = microtime(true);
        
        try {
            // Validate inputs
            $this->validateStructureInputs($structureType, $activeStandards);
            
            db_begin_transaction();
            
            // Create or update configuration
            $configId = $this->createOrUpdateConfiguration(
                $companyId, 
                $structureType, 
                $activeStandards, 
                $advancedConfig, 
                $createdBy
            );
            
            // Create folder structure based on type
            $result = match($structureType) {
                'separata' => $this->createSeparateStructure($companyId, $activeStandards, $createdBy),
                'integrata' => $this->createIntegratedStructure($companyId, $activeStandards, $createdBy),
                'personalizzata' => $this->createCustomStructure($companyId, $activeStandards, $advancedConfig, $createdBy),
                default => throw new Exception("Invalid structure type: {$structureType}")
            };
            
            // Update configuration status
            db_update('aziende_iso_config', [
                'stato' => 'attiva',
                'data_attivazione' => date('Y-m-d H:i:s')
            ], 'id = ?', [$configId]);
            
            // Log successful deployment
            $this->logDeployment($companyId, 'creazione_iniziale', $activeStandards, [
                'structure_type' => $structureType,
                'folders_created' => $result['folders_created'],
                'advanced_config' => $advancedConfig
            ], 'successo', microtime(true) - $startTime, $createdBy);
            
            db_commit();
            
            // Clear cache
            unset($this->configCache["config_{$companyId}"]);
            
            $this->lastOperationTime = microtime(true) - $startTime;
            
            return [
                'success' => true,
                'config_id' => $configId,
                'folders_created' => $result['folders_created'],
                'structure_summary' => $result['structure_summary'],
                'execution_time' => $this->lastOperationTime
            ];
            
        } catch (Exception $e) {
            db_rollback();
            
            // Log failed deployment
            $this->logDeployment($companyId, 'creazione_iniziale', $activeStandards, [
                'error' => $e->getMessage(),
                'structure_type' => $structureType
            ], 'fallito', microtime(true) - $startTime, $createdBy);
            
            throw new Exception("Failed to initialize ISO structure: " . $e->getMessage());
        }
    }
    
    /**
     * Create separate structure (each standard in its own root folder)
     */
    private function createSeparateStructure(int $companyId, array $activeStandards, ?int $createdBy): array
    {
        $foldersCreated = 0;
        $structureSummary = [];
        
        foreach ($activeStandards as $standardCode) {
            if (!isset($this->templatesCache[$standardCode])) {
                continue;
            }
            
            $templates = $this->templatesCache[$standardCode];
            $rootTemplate = array_filter($templates, fn($t) => $t['livello'] == 1)[0] ?? null;
            
            if (!$rootTemplate) {
                continue;
            }
            
            // Create root folder for this standard
            $rootFolderId = $this->createFolder(
                $companyId,
                $rootTemplate['nome'],
                null,
                $rootTemplate,
                $standardCode,
                $createdBy
            );
            
            $foldersCreated++;
            
            // Create child folders
            $childFolders = $this->createChildFolders(
                $companyId,
                $rootFolderId,
                $templates,
                $rootTemplate['id'],
                $standardCode,
                $createdBy
            );
            
            $foldersCreated += $childFolders;
            
            $structureSummary[$standardCode] = [
                'root_folder_id' => $rootFolderId,
                'folders_count' => $childFolders + 1,
                'standard_name' => $this->standardsCache[$standardCode]['nome']
            ];
        }
        
        return [
            'folders_created' => $foldersCreated,
            'structure_summary' => $structureSummary
        ];
    }
    
    /**
     * Create integrated structure (shared folders for common elements)
     */
    private function createIntegratedStructure(int $companyId, array $activeStandards, ?int $createdBy): array
    {
        $foldersCreated = 0;
        $structureSummary = [];
        
        // Create main SGI (Integrated Management System) root
        $rootFolderId = $this->createFolder(
            $companyId,
            'Sistema di Gestione Integrato',
            null,
            null,
            'SGI',
            $createdBy
        );
        $foldersCreated++;
        
        // Common folders that can be shared
        $commonFolders = [
            'PROCEDURE' => 'Procedure',
            'MODULI_REGISTRAZIONI' => 'Moduli e Registrazioni',
            'AUDIT' => 'Audit',
            'NON_CONFORMITA' => 'Non Conformità',
            'FORMAZIONE' => 'Formazione',
            'RIESAME_DIREZIONE' => 'Riesame della Direzione'
        ];
        
        $sharedFolderIds = [];
        
        // Create shared folders
        foreach ($commonFolders as $code => $name) {
            $folderId = $this->createFolder(
                $companyId,
                $name,
                $rootFolderId,
                null,
                'SGI',
                $createdBy
            );
            $sharedFolderIds[$code] = $folderId;
            $foldersCreated++;
        }
        
        // Create standard-specific folders
        foreach ($activeStandards as $standardCode) {
            $standardData = $this->standardsCache[$standardCode];
            
            // Create standard-specific root folder under SGI
            $standardFolderId = $this->createFolder(
                $companyId,
                $standardData['nome'],
                $rootFolderId,
                null,
                $standardCode,
                $createdBy
            );
            $foldersCreated++;
            
            // Create standard-specific folders (non-shared ones)
            $specificFolders = $this->getStandardSpecificFolders($standardCode);
            $standardFoldersCount = 0;
            
            foreach ($specificFolders as $template) {
                $this->createFolder(
                    $companyId,
                    $template['nome'],
                    $standardFolderId,
                    $template,
                    $standardCode,
                    $createdBy
                );
                $foldersCreated++;
                $standardFoldersCount++;
            }
            
            $structureSummary[$standardCode] = [
                'standard_folder_id' => $standardFolderId,
                'folders_count' => $standardFoldersCount,
                'shared_folders' => array_keys($sharedFolderIds)
            ];
        }
        
        $structureSummary['SGI'] = [
            'root_folder_id' => $rootFolderId,
            'shared_folders_ids' => $sharedFolderIds,
            'total_standards' => count($activeStandards)
        ];
        
        return [
            'folders_created' => $foldersCreated,
            'structure_summary' => $structureSummary
        ];
    }
    
    /**
     * Create custom structure with user-defined configurations
     */
    private function createCustomStructure(int $companyId, array $activeStandards, array $advancedConfig, ?int $createdBy): array
    {
        $foldersCreated = 0;
        $structureSummary = [];
        
        // Use advanced config to determine structure
        $customRootName = $advancedConfig['root_folder_name'] ?? 'Sistema di Gestione Documentale';
        $folderMappings = $advancedConfig['folder_mappings'] ?? [];
        $excludedFolders = $advancedConfig['excluded_folders'] ?? [];
        
        // Create custom root folder
        $rootFolderId = $this->createFolder(
            $companyId,
            $customRootName,
            null,
            null,
            'CUSTOM',
            $createdBy
        );
        $foldersCreated++;
        
        foreach ($activeStandards as $standardCode) {
            $templates = $this->templatesCache[$standardCode] ?? [];
            $standardFoldersCount = 0;
            
            foreach ($templates as $template) {
                // Skip excluded folders
                if (in_array($template['codice'], $excludedFolders)) {
                    continue;
                }
                
                // Skip root level templates
                if ($template['livello'] == 1) {
                    continue;
                }
                
                // Use custom name if mapped
                $folderName = $folderMappings[$template['codice']] ?? $template['nome'];
                
                $folderId = $this->createFolder(
                    $companyId,
                    $folderName,
                    $rootFolderId,
                    $template,
                    $standardCode,
                    $createdBy
                );
                $foldersCreated++;
                $standardFoldersCount++;
            }
            
            $structureSummary[$standardCode] = [
                'folders_count' => $standardFoldersCount
            ];
        }
        
        $structureSummary['CUSTOM'] = [
            'root_folder_id' => $rootFolderId,
            'root_name' => $customRootName,
            'excluded_folders' => $excludedFolders,
            'custom_mappings' => $folderMappings
        ];
        
        return [
            'folders_created' => $foldersCreated,
            'structure_summary' => $structureSummary
        ];
    }
    
    /**
     * Create a single folder with ISO metadata
     */
    private function createFolder(
        int $companyId,
        string $name,
        ?int $parentId,
        ?array $template,
        string $standardCode,
        ?int $createdBy
    ): int {
        // Build full path
        $fullPath = $name;
        if ($parentId) {
            $parent = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$parentId])->fetch();
            if ($parent) {
                $fullPath = $parent['percorso_completo'] . '/' . $name;
            }
        }
        
        // Prepare ISO metadata
        $isoMetadata = [
            'template_id' => $template['id'] ?? null,
            'standard_code' => $standardCode,
            'template_code' => $template['codice'] ?? null,
            'compliance_level' => $template['obbligatoria'] ?? false ? 'obbligatoria' : 'personalizzata',
            'icon' => $template['icona'] ?? 'fa-folder',
            'color' => $template['colore'] ?? '#fbbf24',
            'description' => $template['descrizione'] ?? null,
            'created_by_iso_manager' => true,
            'creation_timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Create folder in cartelle table
        $folderId = db_insert('cartelle', [
            'nome' => $name,
            'parent_id' => $parentId,
            'percorso_completo' => $fullPath,
            'azienda_id' => $companyId,
            'iso_template_id' => $template['id'] ?? null,
            'iso_standard_codice' => $standardCode,
            'iso_compliance_level' => $isoMetadata['compliance_level'],
            'iso_metadata' => json_encode($isoMetadata),
            'creato_da' => $createdBy
        ]);
        
        // Create entry in aziende_iso_folders if template exists
        if ($template) {
            db_insert('aziende_iso_folders', [
                'azienda_id' => $companyId,
                'template_id' => $template['id'],
                'cartella_id' => $folderId,
                'standard_codice' => $standardCode,
                'percorso_iso' => $fullPath,
                'personalizzazioni' => null,
                'stato' => 'attiva'
            ]);
        }
        
        return $folderId;
    }
    
    /**
     * Create child folders recursively
     */
    private function createChildFolders(
        int $companyId,
        int $parentFolderId,
        array $templates,
        int $parentTemplateId,
        string $standardCode,
        ?int $createdBy
    ): int {
        $foldersCreated = 0;
        
        $childTemplates = array_filter($templates, fn($t) => $t['parent_template_id'] == $parentTemplateId);
        
        foreach ($childTemplates as $template) {
            $folderId = $this->createFolder(
                $companyId,
                $template['nome'],
                $parentFolderId,
                $template,
                $standardCode,
                $createdBy
            );
            $foldersCreated++;
            
            // Recursively create sub-folders
            $subFolders = $this->createChildFolders(
                $companyId,
                $folderId,
                $templates,
                $template['id'],
                $standardCode,
                $createdBy
            );
            $foldersCreated += $subFolders;
        }
        
        return $foldersCreated;
    }
    
    /**
     * Get standard-specific folders (non-shared in integrated mode)
     */
    private function getStandardSpecificFolders(string $standardCode): array
    {
        $allTemplates = $this->templatesCache[$standardCode] ?? [];
        $sharedCodes = ['PROCEDURE', 'MODULI_REGISTRAZIONI', 'AUDIT', 'NON_CONFORMITA', 'FORMAZIONE', 'RIESAME_DIREZIONE'];
        
        return array_filter($allTemplates, function($template) use ($sharedCodes) {
            return !in_array($template['codice'], $sharedCodes) && $template['livello'] > 1;
        });
    }
    
    /**
     * Validate structure initialization inputs
     */
    private function validateStructureInputs(string $structureType, array $activeStandards): void
    {
        if (!in_array($structureType, array_keys(self::STRUCTURE_TYPES))) {
            throw new Exception("Invalid structure type: {$structureType}");
        }
        
        if (empty($activeStandards)) {
            throw new Exception("At least one ISO standard must be selected");
        }
        
        foreach ($activeStandards as $standard) {
            if (!in_array($standard, self::SUPPORTED_STANDARDS)) {
                throw new Exception("Unsupported ISO standard: {$standard}");
            }
            
            if (!isset($this->standardsCache[$standard])) {
                throw new Exception("ISO standard not found in database: {$standard}");
            }
        }
    }
    
    /**
     * Create or update company ISO configuration
     */
    private function createOrUpdateConfiguration(
        int $companyId,
        string $structureType,
        array $activeStandards,
        array $advancedConfig,
        ?int $createdBy
    ): int {
        $existing = db_query("SELECT id FROM aziende_iso_config WHERE azienda_id = ?", [$companyId])->fetch();
        
        $data = [
            'azienda_id' => $companyId,
            'tipo_struttura' => $structureType,
            'standards_attivi' => json_encode($activeStandards),
            'configurazione_avanzata' => json_encode($advancedConfig),
            'stato' => 'configurazione',
            'creato_da' => $createdBy
        ];
        
        if ($existing) {
            db_update('aziende_iso_config', $data, 'id = ?', [$existing['id']]);
            return $existing['id'];
        } else {
            return db_insert('aziende_iso_config', $data);
        }
    }
    
    /**
     * Log deployment operation
     */
    private function logDeployment(
        int $companyId,
        string $operation,
        array $standardsInvolved,
        array $operationDetails,
        string $result,
        float $executionTime,
        ?int $executedBy
    ): void {
        try {
            db_insert('iso_deployment_log', [
                'azienda_id' => $companyId,
                'operazione' => $operation,
                'standard_coinvolti' => json_encode($standardsInvolved),
                'dettagli_operazione' => json_encode($operationDetails),
                'risultato' => $result,
                'tempo_esecuzione_secondi' => round($executionTime, 3),
                'eseguito_da' => $executedBy
            ]);
        } catch (Exception $e) {
            error_log("ISOStructureManager: Failed to log deployment - " . $e->getMessage());
        }
    }
    
    /**
     * Build hierarchical template structure
     */
    private function buildTemplateHierarchy(array $templates): array
    {
        $hierarchy = [];
        $indexed = [];
        
        // Index all templates
        foreach ($templates as $template) {
            $indexed[$template['id']] = $template;
            $indexed[$template['id']]['children'] = [];
        }
        
        // Build hierarchy
        foreach ($indexed as $template) {
            if ($template['parent_template_id'] === null) {
                $hierarchy[] = &$indexed[$template['id']];
            } else {
                $indexed[$template['parent_template_id']]['children'][] = &$indexed[$template['id']];
            }
        }
        
        return $hierarchy;
    }
    
    /**
     * Get company ISO structure status
     */
    public function getCompanyStructureStatus(?int $companyId): array
    {
        // Se non c'è company ID (super_admin senza azienda selezionata)
        if ($companyId === null) {
            return [
                'configured' => false,
                'status' => 'no_company_selected',
                'message' => 'No company selected. Please select a company to view ISO structure.'
            ];
        }
        
        try {
            $config = $this->getCompanyConfiguration($companyId);
            
            if (!$config) {
                return [
                    'configured' => false,
                    'status' => 'non_configurato',
                    'message' => 'Company ISO structure not configured'
                ];
            }
            
            // Count created folders
            $foldersCount = db_query("
                SELECT COUNT(*) FROM cartelle 
                WHERE azienda_id = ? AND iso_standard_codice IS NOT NULL
            ", [$companyId])->fetchColumn();
            
            // Check compliance
            $complianceChecks = db_query("
                SELECT standard_codice, stato_conformita, punteggio_conformita
                FROM iso_compliance_check
                WHERE azienda_id = ?
                ORDER BY data_verifica DESC
            ", [$companyId])->fetchAll();
            
            $lastDeployment = db_query("
                SELECT * FROM iso_deployment_log
                WHERE azienda_id = ?
                ORDER BY data_esecuzione DESC
                LIMIT 1
            ", [$companyId])->fetch();
            
            return [
                'configured' => true,
                'status' => $config['stato'],
                'structure_type' => $config['tipo_struttura'],
                'active_standards' => $config['standards_attivi'],
                'folders_count' => (int)$foldersCount,
                'compliance_checks' => $complianceChecks,
                'last_deployment' => $lastDeployment,
                'activation_date' => $config['data_attivazione']
            ];
            
        } catch (Exception $e) {
            error_log("ISOStructureManager: Error getting structure status - " . $e->getMessage());
            return [
                'configured' => false,
                'status' => 'errore',
                'message' => 'Error retrieving structure status'
            ];
        }
    }
    
    /**
     * Get structure performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'last_operation_time' => $this->lastOperationTime,
            'standards_cached' => count($this->standardsCache),
            'templates_cached' => array_sum(array_map('count', $this->templatesCache)),
            'config_cache_size' => count($this->configCache),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        $this->configCache = [];
        $this->loadInitialData(); // Reload standards and templates
    }
}