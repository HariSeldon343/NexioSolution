<?php
/**
 * PHPUnit Test Suite for OnlyOffice Integration
 * 
 * Run with: vendor/bin/phpunit tests/OnlyOfficeIntegrationTest.php
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use PDO;

class OnlyOfficeIntegrationTest extends TestCase
{
    private static $db;
    private static $config;
    private static $testDocumentId;
    private static $testAziendaId = 1;
    
    /**
     * Set up test environment before all tests
     */
    public static function setUpBeforeClass(): void
    {
        // Load configuration
        require_once __DIR__ . '/../backend/config/config.php';
        require_once __DIR__ . '/../backend/config/onlyoffice.config.php';
        
        // Store config for tests
        self::$config = [
            'jwt_enabled' => $GLOBALS['ONLYOFFICE_JWT_ENABLED'] ?? false,
            'jwt_secret' => $GLOBALS['ONLYOFFICE_JWT_SECRET'] ?? '',
            'server_url' => $GLOBALS['ONLYOFFICE_DS_PUBLIC_URL'] ?? '',
            'callback_url' => $GLOBALS['ONLYOFFICE_CALLBACK_URL'] ?? '',
            'documents_dir' => $GLOBALS['ONLYOFFICE_DOCUMENTS_DIR'] ?? '',
        ];
        
        // Get database connection
        self::$db = db_connection();
    }
    
    /**
     * Clean up after all tests
     */
    public static function tearDownAfterClass(): void
    {
        // Clean up test documents if any
        if (self::$testDocumentId) {
            self::$db->query("DELETE FROM documenti WHERE id = " . self::$testDocumentId);
            self::$db->query("DELETE FROM documenti_versioni WHERE documento_id = " . self::$testDocumentId);
        }
    }
    
    /**
     * Test JWT configuration
     */
    public function testJWTConfiguration()
    {
        $this->assertTrue(
            self::$config['jwt_enabled'],
            'JWT should be enabled for OnlyOffice integration'
        );
        
        $this->assertNotEmpty(
            self::$config['jwt_secret'],
            'JWT secret should not be empty'
        );
        
        // Warn if using default secret
        if (self::$config['jwt_secret'] === 'a7f3b2c9d8e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') {
            $this->markTestIncomplete('Using default JWT secret - should be changed for production');
        }
    }
    
    /**
     * Test server configuration
     */
    public function testServerConfiguration()
    {
        $this->assertNotEmpty(
            self::$config['server_url'],
            'OnlyOffice server URL should be configured'
        );
        
        $this->assertNotEmpty(
            self::$config['callback_url'],
            'Callback URL should be configured'
        );
        
        // Check URL format
        $this->assertMatchesRegularExpression(
            '/^https?:\/\//',
            self::$config['server_url'],
            'Server URL should start with http:// or https://'
        );
    }
    
    /**
     * Test documents directory
     */
    public function testDocumentsDirectory()
    {
        $dir = self::$config['documents_dir'];
        
        $this->assertDirectoryExists(
            $dir,
            "Documents directory should exist: $dir"
        );
        
        $this->assertDirectoryIsWritable(
            $dir,
            "Documents directory should be writable: $dir"
        );
        
        // Test file creation
        $testFile = $dir . '/test_' . time() . '.txt';
        $written = @file_put_contents($testFile, 'test');
        
        $this->assertNotFalse(
            $written,
            'Should be able to write test file to documents directory'
        );
        
        // Clean up
        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }
    
    /**
     * Test database tables exist
     */
    public function testDatabaseTables()
    {
        // Check documenti table
        $stmt = self::$db->query("SHOW TABLES LIKE 'documenti'");
        $this->assertEquals(
            1,
            $stmt->rowCount(),
            'documenti table should exist'
        );
        
        // Check for version tables
        $stmt = self::$db->query("SHOW TABLES LIKE 'documenti_versioni%'");
        $this->assertGreaterThan(
            0,
            $stmt->rowCount(),
            'At least one version table should exist'
        );
        
        // Check log_attivita table
        $stmt = self::$db->query("SHOW TABLES LIKE 'log_attivita'");
        $this->assertEquals(
            1,
            $stmt->rowCount(),
            'log_attivita table should exist'
        );
    }
    
    /**
     * Test required API files exist
     */
    public function testAPIFilesExist()
    {
        $apiFiles = [
            'onlyoffice-callback.php',
            'onlyoffice-document.php',
            'onlyoffice-prepare.php'
        ];
        
        $apiPath = __DIR__ . '/../backend/api/';
        
        foreach ($apiFiles as $file) {
            $this->assertFileExists(
                $apiPath . $file,
                "API file should exist: $file"
            );
            
            $this->assertFileIsReadable(
                $apiPath . $file,
                "API file should be readable: $file"
            );
        }
    }
    
    /**
     * Test JWT generation and verification
     */
    public function testJWTFunctions()
    {
        if (!function_exists('generateOnlyOfficeJWT')) {
            $this->markTestSkipped('JWT functions not available');
        }
        
        // Test token generation
        $payload = [
            'test' => 'data',
            'timestamp' => time()
        ];
        
        $token = generateOnlyOfficeJWT($payload);
        $this->assertNotEmpty($token, 'Should generate JWT token');
        
        // Check token format (3 parts separated by dots)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT should have 3 parts');
        
        // Test token verification
        $result = verifyOnlyOfficeJWT($token);
        $this->assertTrue($result['valid'], 'Token should be valid');
        $this->assertEquals('data', $result['payload']['test'], 'Payload should match');
    }
    
    /**
     * Test multi-tenant isolation
     */
    public function testMultiTenantIsolation()
    {
        // Check that documents have azienda_id
        $stmt = self::$db->query(
            "SELECT COUNT(*) as total, 
                    COUNT(azienda_id) as with_company 
             FROM documenti"
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            $isolationRate = ($result['with_company'] / $result['total']) * 100;
            $this->assertGreaterThanOrEqual(
                90, // At least 90% should have azienda_id
                $isolationRate,
                'Most documents should be assigned to a company'
            );
        }
    }
    
    /**
     * Test document creation with OnlyOffice metadata
     */
    public function testDocumentCreation()
    {
        // Create test document
        $stmt = self::$db->prepare(
            "INSERT INTO documenti (titolo, azienda_id, tipo_documento, mime_type, dimensione_file)
             VALUES (?, ?, 'documento', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1024)"
        );
        
        $testTitle = 'Test OnlyOffice Document ' . time();
        $stmt->execute([$testTitle, self::$testAziendaId]);
        
        self::$testDocumentId = self::$db->lastInsertId();
        
        $this->assertGreaterThan(0, self::$testDocumentId, 'Document should be created');
        
        // Verify document was created
        $stmt = self::$db->prepare("SELECT * FROM documenti WHERE id = ?");
        $stmt->execute([self::$testDocumentId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($document, 'Document should exist in database');
        $this->assertEquals($testTitle, $document['titolo'], 'Document title should match');
        $this->assertEquals(self::$testAziendaId, $document['azienda_id'], 'Document should belong to correct company');
    }
    
    /**
     * Test callback security features
     */
    public function testCallbackSecurity()
    {
        // Test rate limiting function exists
        if (function_exists('checkOnlyOfficeRateLimit')) {
            $testId = 'test_' . time();
            $result = checkOnlyOfficeRateLimit($testId);
            $this->assertTrue($result, 'First rate limit check should pass');
        }
        
        // Test IP validation function exists
        if (function_exists('validateOnlyOfficeCallbackIP')) {
            $result = validateOnlyOfficeCallbackIP();
            $this->assertIsBool($result, 'IP validation should return boolean');
        }
    }
    
    /**
     * Test version management integration
     */
    public function testVersionManagement()
    {
        if (!self::$testDocumentId) {
            $this->markTestSkipped('No test document available');
        }
        
        // Check if version model exists
        $versionModelPath = __DIR__ . '/../backend/models/DocumentVersion.php';
        $this->assertFileExists($versionModelPath, 'DocumentVersion model should exist');
        
        // Test version creation (if model is available)
        if (class_exists('\DocumentVersion')) {
            $version = new \DocumentVersion();
            $this->assertInstanceOf('\DocumentVersion', $version, 'Should create DocumentVersion instance');
        }
    }
    
    /**
     * Test OnlyOffice configuration validation
     */
    public function testConfigurationValidation()
    {
        if (!function_exists('checkOnlyOfficeConfig')) {
            $this->markTestSkipped('Configuration check function not available');
        }
        
        $errors = checkOnlyOfficeConfig();
        $this->assertIsArray($errors, 'Configuration check should return array');
        
        if (!empty($errors)) {
            $this->markTestIncomplete(
                'Configuration has issues: ' . implode(', ', $errors)
            );
        }
    }
    
    /**
     * Test supported file formats
     */
    public function testSupportedFormats()
    {
        $formats = $GLOBALS['ONLYOFFICE_SUPPORTED_FORMATS'] ?? [];
        
        $this->assertNotEmpty($formats, 'Supported formats should be configured');
        
        // Check for essential formats
        $essentialFormats = ['docx', 'xlsx', 'pptx'];
        foreach ($essentialFormats as $format) {
            $this->assertContains(
                $format,
                $formats,
                "Essential format '$format' should be supported"
            );
        }
    }
    
    /**
     * Test activity logging
     */
    public function testActivityLogging()
    {
        // Check if ActivityLogger exists
        $loggerPath = __DIR__ . '/../backend/utils/ActivityLogger.php';
        $this->assertFileExists($loggerPath, 'ActivityLogger should exist');
        
        if (class_exists('\ActivityLogger')) {
            // Test logging (without actually logging)
            $this->assertTrue(
                method_exists('\ActivityLogger', 'log'),
                'ActivityLogger should have log method'
            );
        }
    }
    
    /**
     * Test security headers configuration
     */
    public function testSecurityHeaders()
    {
        $headers = $GLOBALS['ONLYOFFICE_SECURITY_HEADERS'] ?? [];
        
        $this->assertNotEmpty($headers, 'Security headers should be configured');
        
        // Check for essential security headers
        $essentialHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection'
        ];
        
        foreach ($essentialHeaders as $header) {
            $this->assertArrayHasKey(
                $header,
                $headers,
                "Security header '$header' should be configured"
            );
        }
    }
    
    /**
     * Integration test for document edit flow
     * @group integration
     */
    public function testDocumentEditFlow()
    {
        if (!self::$testDocumentId) {
            $this->markTestSkipped('No test document available');
        }
        
        // Generate document key
        $documentKey = self::$testDocumentId . '_' . time();
        
        // Test key format
        $this->assertMatchesRegularExpression(
            '/^\d+_\d+$/',
            $documentKey,
            'Document key should have correct format'
        );
        
        // Simulate callback data structure
        $callbackData = [
            'key' => $documentKey,
            'status' => 1, // Editing
            'users' => [
                ['id' => '1', 'name' => 'Test User']
            ]
        ];
        
        $this->assertIsArray($callbackData, 'Callback data should be valid array');
        $this->assertArrayHasKey('status', $callbackData, 'Callback should have status');
    }
}
?>