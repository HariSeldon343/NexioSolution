<?php

require_once __DIR__ . '/../config/database.php';

/**
 * ISO Structure Validator
 * 
 * Enterprise-level validation for ISO document structures
 * Provides comprehensive validation, error handling, and compliance checking
 * 
 * @package Nexio
 * @version 1.0.0
 * @author Claude Code
 */
class ISOValidator
{
    private static ?ISOValidator $instance = null;
    
    /** @var array Validation rules cache */
    private array $validationRules = [];
    
    /** @var array Error messages */
    private array $errors = [];
    
    /** @var array Warning messages */
    private array $warnings = [];
    
    /**
     * Validation error types
     */
    public const ERROR_TYPES = [
        'REQUIRED_FIELD_MISSING' => 'Required field is missing',
        'INVALID_STRUCTURE_TYPE' => 'Invalid structure type',
        'UNSUPPORTED_STANDARD' => 'Unsupported ISO standard',
        'INSUFFICIENT_STANDARDS' => 'At least one standard must be selected',
        'COMPANY_NOT_FOUND' => 'Company not found',
        'STRUCTURE_ALREADY_EXISTS' => 'Structure already exists',
        'INVALID_CONFIGURATION' => 'Invalid configuration parameters',
        'DEPENDENCY_VIOLATION' => 'Dependency requirements not met',
        'PERMISSION_DENIED' => 'Insufficient permissions',
        'RESOURCE_CONFLICT' => 'Resource conflict detected'
    ];
    
    /**
     * Validation severity levels
     */
    public const SEVERITY_LEVELS = [
        'ERROR' => 'error',
        'WARNING' => 'warning',
        'INFO' => 'info'
    ];
    
    private function __construct()
    {
        $this->loadValidationRules();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): ISOValidator
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
     * Load validation rules
     */
    private function loadValidationRules(): void
    {
        $this->validationRules = [
            'structure_initialization' => [
                'required_fields' => ['company_id', 'structure_type', 'active_standards'],
                'optional_fields' => ['advanced_config', 'created_by'],
                'field_types' => [
                    'company_id' => 'integer',
                    'structure_type' => 'string',
                    'active_standards' => 'array',
                    'advanced_config' => 'array',
                    'created_by' => 'integer'
                ],
                'field_constraints' => [
                    'structure_type' => ['separata', 'integrata', 'personalizzata'],
                    'active_standards' => ['min_length' => 1, 'max_length' => 10],
                    'company_id' => ['min' => 1]
                ]
            ],
            'configuration_update' => [
                'required_fields' => ['company_id'],
                'optional_fields' => ['structure_type', 'active_standards', 'advanced_config'],
                'field_types' => [
                    'company_id' => 'integer',
                    'structure_type' => 'string',
                    'active_standards' => 'array',
                    'advanced_config' => 'array'
                ]
            ],
            'advanced_config' => [
                'personalizzata' => [
                    'optional_fields' => ['root_folder_name', 'folder_mappings', 'excluded_folders'],
                    'field_types' => [
                        'root_folder_name' => 'string',
                        'folder_mappings' => 'array',
                        'excluded_folders' => 'array'
                    ],
                    'field_constraints' => [
                        'root_folder_name' => ['min_length' => 3, 'max_length' => 100],
                        'excluded_folders' => ['max_length' => 20]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Validate structure initialization data
     */
    public function validateStructureInitialization(array $data): array
    {
        $this->clearErrors();
        
        $rules = $this->validationRules['structure_initialization'];
        
        // Validate required fields
        $this->validateRequiredFields($data, $rules['required_fields']);
        
        // Validate field types
        $this->validateFieldTypes($data, $rules['field_types']);
        
        // Validate field constraints
        $this->validateFieldConstraints($data, $rules['field_constraints']);
        
        // Validate business rules
        $this->validateBusinessRules($data);
        
        // Validate company access
        $this->validateCompanyAccess($data['company_id'] ?? null);
        
        // Validate standards compatibility
        if (isset($data['active_standards'])) {
            $this->validateStandardsCompatibility($data['active_standards']);
        }
        
        // Validate advanced configuration
        if (isset($data['advanced_config']) && isset($data['structure_type'])) {
            $this->validateAdvancedConfiguration($data['advanced_config'], $data['structure_type']);
        }
        
        return $this->getValidationResult();
    }
    
    /**
     * Validate configuration update data
     */
    public function validateConfigurationUpdate(array $data): array
    {
        $this->clearErrors();
        
        $rules = $this->validationRules['configuration_update'];
        
        // Validate required fields
        $this->validateRequiredFields($data, $rules['required_fields']);
        
        // Validate field types
        $this->validateFieldTypes($data, $rules['field_types']);
        
        // Validate company access
        $this->validateCompanyAccess($data['company_id'] ?? null);
        
        // Validate existing configuration
        $this->validateExistingConfiguration($data['company_id'] ?? null);
        
        // Validate update constraints
        $this->validateUpdateConstraints($data);
        
        return $this->getValidationResult();
    }
    
    /**
     * Validate company structure compliance
     */
    public function validateStructureCompliance(int $companyId): array
    {
        $this->clearErrors();
        
        try {
            // Get company configuration
            $config = db_query("
                SELECT * FROM aziende_iso_config 
                WHERE azienda_id = ? AND stato = 'attiva'
            ", [$companyId])->fetch();
            
            if (!$config) {
                $this->addError('STRUCTURE_NOT_CONFIGURED', 'No active ISO structure configuration found');
                return $this->getValidationResult();
            }
            
            $activeStandards = json_decode($config['standards_attivi'], true);
            $complianceResults = [];
            
            foreach ($activeStandards as $standardCode) {
                $complianceResults[$standardCode] = $this->validateStandardCompliance($companyId, $standardCode);
            }
            
            // Calculate overall compliance score
            $totalScore = 0;
            $standardCount = 0;
            
            foreach ($complianceResults as $result) {
                if (isset($result['compliance_score'])) {
                    $totalScore += $result['compliance_score'];
                    $standardCount++;
                }
            }
            
            $overallScore = $standardCount > 0 ? round($totalScore / $standardCount, 2) : 0;
            
            return [
                'valid' => count($this->errors) === 0,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'compliance_data' => [
                    'overall_score' => $overallScore,
                    'standards_compliance' => $complianceResults,
                    'recommendations' => $this->generateComplianceRecommendations($complianceResults)
                ]
            ];
            
        } catch (Exception $e) {
            $this->addError('VALIDATION_ERROR', 'Error validating compliance: ' . $e->getMessage());
            return $this->getValidationResult();
        }
    }
    
    /**
     * Validate standard compliance
     */
    private function validateStandardCompliance(int $companyId, string $standardCode): array
    {
        $result = [
            'standard' => $standardCode,
            'compliance_score' => 0,
            'mandatory_folders' => [],
            'missing_folders' => [],
            'empty_folders' => [],
            'recommendations' => []
        ];
        
        try {
            // Get mandatory folders for this standard
            $mandatoryTemplates = db_query("
                SELECT ft.* FROM iso_folder_templates ft
                JOIN iso_standards s ON ft.standard_id = s.id
                WHERE s.codice = ? AND ft.obbligatoria = 1
            ", [$standardCode])->fetchAll();
            
            $result['mandatory_folders'] = array_column($mandatoryTemplates, 'nome');
            
            // Check existing folders
            $existingFolders = db_query("
                SELECT c.nome, c.id,
                       (SELECT COUNT(*) FROM documenti WHERE cartella_id = c.id) as document_count
                FROM cartelle c
                WHERE c.azienda_id = ? AND c.iso_standard_codice = ?
            ", [$companyId, $standardCode])->fetchAll();
            
            $existingFolderNames = array_column($existingFolders, 'nome');
            
            // Find missing mandatory folders
            $result['missing_folders'] = array_diff($result['mandatory_folders'], $existingFolderNames);
            
            // Find empty folders
            foreach ($existingFolders as $folder) {
                if ($folder['document_count'] == 0) {
                    $result['empty_folders'][] = $folder['nome'];
                }
            }
            
            // Calculate compliance score
            $mandatoryCount = count($result['mandatory_folders']);
            $implementedCount = $mandatoryCount - count($result['missing_folders']);
            $result['compliance_score'] = $mandatoryCount > 0 ? round(($implementedCount / $mandatoryCount) * 100, 2) : 100;
            
            // Generate recommendations
            if (!empty($result['missing_folders'])) {
                $result['recommendations'][] = 'Creare le cartelle obbligatorie mancanti: ' . implode(', ', $result['missing_folders']);
            }
            
            if (!empty($result['empty_folders'])) {
                $result['recommendations'][] = 'Popolare le cartelle vuote con documenti appropriati: ' . implode(', ', array_slice($result['empty_folders'], 0, 3));
            }
            
            if ($result['compliance_score'] < 70) {
                $result['recommendations'][] = 'La conformità è sotto il livello raccomandato del 70%. Prioritizzare il completamento della struttura documentale.';
            }
            
        } catch (Exception $e) {
            $this->addWarning('COMPLIANCE_CHECK_ERROR', "Error checking compliance for {$standardCode}: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Validate required fields
     */
    private function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $this->addError('REQUIRED_FIELD_MISSING', "Required field '{$field}' is missing or empty");
            }
        }
    }
    
    /**
     * Validate field types
     */
    private function validateFieldTypes(array $data, array $fieldTypes): void
    {
        foreach ($fieldTypes as $field => $expectedType) {
            if (!isset($data[$field])) {
                continue;
            }
            
            $value = $data[$field];
            $isValid = match($expectedType) {
                'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'string' => is_string($value),
                'array' => is_array($value),
                'boolean' => is_bool($value),
                default => true
            };
            
            if (!$isValid) {
                $this->addError('INVALID_FIELD_TYPE', "Field '{$field}' must be of type {$expectedType}");
            }
        }
    }
    
    /**
     * Validate field constraints
     */
    private function validateFieldConstraints(array $data, array $constraints): void
    {
        foreach ($constraints as $field => $fieldConstraints) {
            if (!isset($data[$field])) {
                continue;
            }
            
            $value = $data[$field];
            
            // Handle different constraint types
            if (is_array($fieldConstraints) && isset($fieldConstraints[0])) {
                // Enum constraint
                if (!in_array($value, $fieldConstraints)) {
                    $this->addError('INVALID_FIELD_VALUE', "Field '{$field}' must be one of: " . implode(', ', $fieldConstraints));
                }
            } else {
                // Other constraints
                foreach ($fieldConstraints as $constraint => $constraintValue) {
                    switch ($constraint) {
                        case 'min':
                            if (is_numeric($value) && $value < $constraintValue) {
                                $this->addError('CONSTRAINT_VIOLATION', "Field '{$field}' must be at least {$constraintValue}");
                            }
                            break;
                            
                        case 'max':
                            if (is_numeric($value) && $value > $constraintValue) {
                                $this->addError('CONSTRAINT_VIOLATION', "Field '{$field}' must be at most {$constraintValue}");
                            }
                            break;
                            
                        case 'min_length':
                            if (is_array($value) && count($value) < $constraintValue) {
                                $this->addError('CONSTRAINT_VIOLATION', "Field '{$field}' must have at least {$constraintValue} items");
                            } elseif (is_string($value) && strlen($value) < $constraintValue) {
                                $this->addError('CONSTRAINT_VIOLATION', "Field '{$field}' must be at least {$constraintValue} characters long");
                            }
                            break;
                            
                        case 'max_length':
                            if (is_array($value) && count($value) > $constraintValue) {
                                $this->addError('CONSTRAINT_VIOLATION', "Field '{$field}' must have at most {$constraintValue} items");
                            } elseif (is_string($value) && strlen($value) > $constraintValue) {
                                $this->addError('CONSTRAINT_VIOLATION', "Field '{$field}' must be at most {$constraintValue} characters long");
                            }
                            break;
                    }
                }
            }
        }
    }
    
    /**
     * Validate business rules
     */
    private function validateBusinessRules(array $data): void
    {
        // Check for existing active structure
        if (isset($data['company_id'])) {
            $existingConfig = db_query("
                SELECT id, stato FROM aziende_iso_config 
                WHERE azienda_id = ? AND stato IN ('attiva', 'configurazione')
            ", [$data['company_id']])->fetch();
            
            if ($existingConfig) {
                if ($existingConfig['stato'] === 'attiva') {
                    $this->addError('STRUCTURE_ALREADY_EXISTS', 'An active ISO structure already exists for this company');
                } elseif ($existingConfig['stato'] === 'configurazione') {
                    $this->addWarning('CONFIGURATION_IN_PROGRESS', 'A structure configuration is already in progress');
                }
            }
        }
        
        // Validate standard combinations
        if (isset($data['active_standards'])) {
            $incompatibleCombinations = [
                ['ISO9001', 'ISO27001'] // Example of potentially conflicting standards
            ];
            
            foreach ($incompatibleCombinations as $combination) {
                if (count(array_intersect($data['active_standards'], $combination)) === count($combination)) {
                    $this->addWarning('STANDARD_COMPATIBILITY', 
                        'Standards ' . implode(' and ', $combination) . ' may have overlapping requirements');
                }
            }
        }
    }
    
    /**
     * Validate company access
     */
    private function validateCompanyAccess(?int $companyId): void
    {
        if (!$companyId) {
            return;
        }
        
        // Check if company exists
        $company = db_query("SELECT id, nome FROM aziende WHERE id = ?", [$companyId])->fetch();
        if (!$company) {
            $this->addError('COMPANY_NOT_FOUND', "Company with ID {$companyId} not found");
            return;
        }
        
        // Check user access to company
        $auth = \Auth::getInstance();
        if (!$auth->isSuperAdmin()) {
            $currentCompany = $auth->getCurrentAzienda();
            if (!$currentCompany || $currentCompany['azienda_id'] != $companyId) {
                $this->addError('PERMISSION_DENIED', 'Insufficient permissions to access this company');
            }
        }
    }
    
    /**
     * Validate standards compatibility
     */
    private function validateStandardsCompatibility(array $standards): void
    {
        $supportedStandards = ISOStructureManager::SUPPORTED_STANDARDS;
        
        foreach ($standards as $standard) {
            if (!in_array($standard, $supportedStandards)) {
                $this->addError('UNSUPPORTED_STANDARD', "Standard '{$standard}' is not supported");
            }
            
            // Check if standard exists in database
            $standardExists = db_query("
                SELECT id FROM iso_standards 
                WHERE codice = ? AND attivo = 1
            ", [$standard])->fetch();
            
            if (!$standardExists) {
                $this->addError('STANDARD_NOT_FOUND', "Standard '{$standard}' not found in database or inactive");
            }
        }
    }
    
    /**
     * Validate advanced configuration
     */
    private function validateAdvancedConfiguration(array $config, string $structureType): void
    {
        if (!isset($this->validationRules['advanced_config'][$structureType])) {
            return;
        }
        
        $rules = $this->validationRules['advanced_config'][$structureType];
        
        // Validate field types
        if (isset($rules['field_types'])) {
            $this->validateFieldTypes($config, $rules['field_types']);
        }
        
        // Validate field constraints
        if (isset($rules['field_constraints'])) {
            $this->validateFieldConstraints($config, $rules['field_constraints']);
        }
        
        // Structure-specific validation
        if ($structureType === 'personalizzata') {
            $this->validatePersonalizedConfiguration($config);
        }
    }
    
    /**
     * Validate personalized configuration
     */
    private function validatePersonalizedConfiguration(array $config): void
    {
        // Validate root folder name
        if (isset($config['root_folder_name'])) {
            $name = $config['root_folder_name'];
            if (preg_match('/[<>:"|?*\\\\\/]/', $name)) {
                $this->addError('INVALID_FOLDER_NAME', 'Root folder name contains invalid characters');
            }
        }
        
        // Validate excluded folders
        if (isset($config['excluded_folders'])) {
            if (count($config['excluded_folders']) > 15) {
                $this->addWarning('TOO_MANY_EXCLUSIONS', 'Excluding too many folders may result in incomplete compliance');
            }
        }
        
        // Validate folder mappings
        if (isset($config['folder_mappings'])) {
            foreach ($config['folder_mappings'] as $original => $custom) {
                if (empty($custom) || !is_string($custom)) {
                    $this->addError('INVALID_FOLDER_MAPPING', "Invalid mapping for folder '{$original}'");
                }
                
                if (preg_match('/[<>:"|?*\\\\\/]/', $custom)) {
                    $this->addError('INVALID_FOLDER_NAME', "Custom folder name '{$custom}' contains invalid characters");
                }
            }
        }
    }
    
    /**
     * Validate existing configuration
     */
    private function validateExistingConfiguration(?int $companyId): void
    {
        if (!$companyId) {
            return;
        }
        
        $config = db_query("
            SELECT * FROM aziende_iso_config WHERE azienda_id = ?
        ", [$companyId])->fetch();
        
        if (!$config) {
            $this->addError('CONFIGURATION_NOT_FOUND', 'No existing configuration found for this company');
            return;
        }
        
        if ($config['stato'] === 'configurazione') {
            $this->addWarning('CONFIGURATION_IN_PROGRESS', 'Configuration is currently in progress');
        }
    }
    
    /**
     * Validate update constraints
     */
    private function validateUpdateConstraints(array $data): void
    {
        // Check if structural changes are allowed
        if (isset($data['structure_type']) || isset($data['active_standards'])) {
            $companyId = $data['company_id'];
            
            // Check if there are existing documents that might be affected
            $documentCount = db_query("
                SELECT COUNT(*) FROM documenti 
                WHERE azienda_id = ? AND cartella_id IN (
                    SELECT id FROM cartelle WHERE iso_standard_codice IS NOT NULL
                )
            ", [$companyId])->fetchColumn();
            
            if ($documentCount > 0) {
                $this->addWarning('DOCUMENTS_EXIST', 
                    "Structural changes may affect {$documentCount} existing documents");
            }
        }
    }
    
    /**
     * Generate compliance recommendations
     */
    private function generateComplianceRecommendations(array $complianceResults): array
    {
        $recommendations = [];
        
        foreach ($complianceResults as $standardCode => $result) {
            if ($result['compliance_score'] < 50) {
                $recommendations[] = [
                    'priority' => 'high',
                    'standard' => $standardCode,
                    'message' => "Compliance for {$standardCode} is critically low ({$result['compliance_score']}%). Immediate action required."
                ];
            } elseif ($result['compliance_score'] < 80) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'standard' => $standardCode,
                    'message' => "Compliance for {$standardCode} needs improvement ({$result['compliance_score']}%)."
                ];
            }
            
            if (!empty($result['missing_folders'])) {
                $recommendations[] = [
                    'priority' => 'high',
                    'standard' => $standardCode,
                    'message' => "Missing mandatory folders for {$standardCode}: " . implode(', ', array_slice($result['missing_folders'], 0, 3))
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Add error
     */
    private function addError(string $code, string $message): void
    {
        $this->errors[] = [
            'code' => $code,
            'message' => $message,
            'severity' => 'error',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Add warning
     */
    private function addWarning(string $code, string $message): void
    {
        $this->warnings[] = [
            'code' => $code,
            'message' => $message,
            'severity' => 'warning',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Clear errors and warnings
     */
    private function clearErrors(): void
    {
        $this->errors = [];
        $this->warnings = [];
    }
    
    /**
     * Get validation result
     */
    private function getValidationResult(): array
    {
        return [
            'valid' => count($this->errors) === 0,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings)
        ];
    }
    
    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get all warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }
    
    /**
     * Get formatted validation summary
     */
    public function getValidationSummary(): string
    {
        $summary = [];
        
        if (count($this->errors) > 0) {
            $summary[] = count($this->errors) . ' error(s)';
        }
        
        if (count($this->warnings) > 0) {
            $summary[] = count($this->warnings) . ' warning(s)';
        }
        
        if (empty($summary)) {
            return 'Validation passed successfully';
        }
        
        return 'Validation completed with ' . implode(' and ', $summary);
    }
}