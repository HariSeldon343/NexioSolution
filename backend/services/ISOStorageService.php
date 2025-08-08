<?php
namespace Nexio\Services;

use Exception;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ISO Storage Service
 * Handles file storage, retrieval, and management for ISO compliance documents
 * 
 * @package Nexio\Services
 * @version 1.0.0
 */
class ISOStorageService {
    
    /** @var ISOStorageService Singleton instance */
    private static $instance = null;
    
    /** @var string Base storage path */
    private $basePath;
    
    /** @var array Storage statistics cache */
    private $statsCache = [];
    
    /** @var int Cache TTL in seconds */
    private const CACHE_TTL = 300;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->basePath = realpath(__DIR__ . '/../../uploads/iso_documents');
        $this->initializeStorage();
    }
    
    /**
     * Get singleton instance
     * 
     * @return ISOStorageService
     */
    public static function getInstance(): ISOStorageService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize storage structure
     * 
     * @return void
     * @throws Exception
     */
    private function initializeStorage(): void {
        if (!is_dir($this->basePath)) {
            if (!mkdir($this->basePath, 0755, true)) {
                throw new Exception('Impossibile creare directory di storage principale');
            }
        }
        
        // Create security files
        $this->createSecurityFiles($this->basePath);
        
        // Create temp directory
        $tempDir = $this->basePath . '/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
            $this->createSecurityFiles($tempDir);
        }
        
        // Create archive directory
        $archiveDir = $this->basePath . '/archive';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
            $this->createSecurityFiles($archiveDir);
        }
    }
    
    /**
     * Store file
     * 
     * @param string $sourcePath Temporary file path
     * @param int $companyId
     * @param string $folderPath
     * @param string $filename
     * @return array [relativePath, absolutePath, checksum]
     * @throws Exception
     */
    public function storeFile(string $sourcePath, int $companyId, string $folderPath, string $filename): array {
        if (!file_exists($sourcePath)) {
            throw new Exception('File sorgente non trovato');
        }
        
        // Generate secure paths
        $security = ISOSecurityService::getInstance();
        list($relativePath, $absolutePath) = $security->generateSecureFilePath($companyId, $folderPath, $filename);
        
        // Copy file to destination
        if (!copy($sourcePath, $absolutePath)) {
            throw new Exception('Impossibile copiare il file nella destinazione');
        }
        
        // Set proper permissions
        chmod($absolutePath, 0644);
        
        // Calculate checksum
        $checksum = hash_file('sha256', $absolutePath);
        
        // Verify integrity
        if (hash_file('sha256', $sourcePath) !== $checksum) {
            unlink($absolutePath);
            throw new Exception('Integrità del file compromessa durante la copia');
        }
        
        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'checksum' => $checksum
        ];
    }
    
    /**
     * Retrieve file
     * 
     * @param string $relativePath
     * @param bool $verifyChecksum
     * @param string|null $expectedChecksum
     * @return string Absolute path
     * @throws Exception
     */
    public function retrieveFile(string $relativePath, bool $verifyChecksum = true, ?string $expectedChecksum = null): string {
        $absolutePath = $this->basePath . $relativePath;
        
        if (!file_exists($absolutePath)) {
            throw new Exception('File non trovato');
        }
        
        if ($verifyChecksum && $expectedChecksum) {
            $actualChecksum = hash_file('sha256', $absolutePath);
            if ($actualChecksum !== $expectedChecksum) {
                throw new Exception('Checksum del file non valido. Possibile corruzione.');
            }
        }
        
        return $absolutePath;
    }
    
    /**
     * Move file
     * 
     * @param string $currentPath
     * @param string $newFolderPath
     * @param int $companyId
     * @return array New path information
     * @throws Exception
     */
    public function moveFile(string $currentPath, string $newFolderPath, int $companyId): array {
        $absolutePath = $this->basePath . $currentPath;
        
        if (!file_exists($absolutePath)) {
            throw new Exception('File da spostare non trovato');
        }
        
        $filename = basename($currentPath);
        $security = ISOSecurityService::getInstance();
        list($newRelativePath, $newAbsolutePath) = $security->generateSecureFilePath($companyId, $newFolderPath, $filename);
        
        if (!rename($absolutePath, $newAbsolutePath)) {
            throw new Exception('Impossibile spostare il file');
        }
        
        return [
            'relative_path' => $newRelativePath,
            'absolute_path' => $newAbsolutePath
        ];
    }
    
    /**
     * Delete file
     * 
     * @param string $relativePath
     * @return bool
     */
    public function deleteFile(string $relativePath): bool {
        $absolutePath = $this->basePath . $relativePath;
        
        if (file_exists($absolutePath)) {
            return unlink($absolutePath);
        }
        
        return true;
    }
    
    /**
     * Archive file
     * 
     * @param string $relativePath
     * @return string Archive path
     * @throws Exception
     */
    public function archiveFile(string $relativePath): string {
        $absolutePath = $this->basePath . $relativePath;
        
        if (!file_exists($absolutePath)) {
            throw new Exception('File da archiviare non trovato');
        }
        
        // Create archive path
        $archiveDir = $this->basePath . '/archive/' . date('Y/m');
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        
        $archivePath = $archiveDir . '/' . uniqid('archive_') . '_' . basename($relativePath);
        
        if (!rename($absolutePath, $archivePath)) {
            throw new Exception('Impossibile archiviare il file');
        }
        
        return str_replace($this->basePath, '', $archivePath);
    }
    
    /**
     * Create ZIP archive of multiple files
     * 
     * @param array $files Array of ['path' => relativePath, 'name' => displayName]
     * @param string $zipName
     * @return string ZIP file path
     * @throws Exception
     */
    public function createZipArchive(array $files, string $zipName): string {
        if (empty($files)) {
            throw new Exception('Nessun file da archiviare');
        }
        
        $tempDir = $this->basePath . '/temp';
        $zipPath = $tempDir . '/' . uniqid('download_') . '_' . $zipName . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception('Impossibile creare archivio ZIP');
        }
        
        foreach ($files as $file) {
            $absolutePath = $this->basePath . $file['path'];
            if (file_exists($absolutePath)) {
                $zip->addFile($absolutePath, $file['name']);
            }
        }
        
        $zip->close();
        
        return $zipPath;
    }
    
    /**
     * Extract content from file for indexing
     * 
     * @param string $filePath
     * @param string $mimeType
     * @return string Extracted text content
     */
    public function extractFileContent(string $filePath, string $mimeType): string {
        $content = '';
        
        try {
            switch ($mimeType) {
                case 'text/plain':
                case 'text/csv':
                    $content = file_get_contents($filePath);
                    break;
                    
                case 'application/pdf':
                    $content = $this->extractPdfContent($filePath);
                    break;
                    
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    $content = $this->extractDocxContent($filePath);
                    break;
                    
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    $content = $this->extractXlsxContent($filePath);
                    break;
                    
                default:
                    // For other file types, extract basic metadata
                    $content = $this->extractBasicMetadata($filePath);
            }
            
            // Clean and truncate content
            $content = $this->cleanTextContent($content);
            
            // Limit content size for indexing
            if (strlen($content) > 50000) {
                $content = substr($content, 0, 50000) . '...';
            }
            
        } catch (Exception $e) {
            // Log error but don't fail the upload
            error_log("Content extraction failed for {$filePath}: " . $e->getMessage());
        }
        
        return $content;
    }
    
    /**
     * Extract PDF content
     * 
     * @param string $filePath
     * @return string
     */
    private function extractPdfContent(string $filePath): string {
        // Use pdftotext if available
        if ($this->commandExists('pdftotext')) {
            $output = [];
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
            exec("pdftotext -layout '{$filePath}' '{$tempFile}' 2>&1", $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempFile)) {
                $content = file_get_contents($tempFile);
                unlink($tempFile);
                return $content;
            }
        }
        
        // Fallback: Extract basic info
        return $this->extractBasicMetadata($filePath);
    }
    
    /**
     * Extract DOCX content
     * 
     * @param string $filePath
     * @return string
     */
    private function extractDocxContent(string $filePath): string {
        $content = '';
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            // Read document.xml
            $xml = $zip->getFromName('word/document.xml');
            if ($xml !== false) {
                // Remove XML tags
                $content = strip_tags($xml);
                // Decode entities
                $content = html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $zip->close();
        }
        
        return $content;
    }
    
    /**
     * Extract XLSX content
     * 
     * @param string $filePath
     * @return string
     */
    private function extractXlsxContent(string $filePath): string {
        $content = '';
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            // Read shared strings
            $sharedStrings = [];
            $xml = $zip->getFromName('xl/sharedStrings.xml');
            if ($xml !== false) {
                $doc = new \DOMDocument();
                @$doc->loadXML($xml);
                $strings = $doc->getElementsByTagName('t');
                foreach ($strings as $string) {
                    $sharedStrings[] = $string->nodeValue;
                }
            }
            
            // Extract content from worksheets
            for ($i = 1; $i <= 10; $i++) {
                $xml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
                if ($xml === false) break;
                
                $doc = new \DOMDocument();
                @$doc->loadXML($xml);
                $cells = $doc->getElementsByTagName('v');
                
                foreach ($cells as $cell) {
                    $value = $cell->nodeValue;
                    // Check if it's a shared string reference
                    if (is_numeric($value) && isset($sharedStrings[$value])) {
                        $content .= $sharedStrings[$value] . ' ';
                    } else {
                        $content .= $value . ' ';
                    }
                }
            }
            
            $zip->close();
        }
        
        return $content;
    }
    
    /**
     * Extract basic metadata
     * 
     * @param string $filePath
     * @return string
     */
    private function extractBasicMetadata(string $filePath): string {
        $info = pathinfo($filePath);
        $stat = stat($filePath);
        
        return sprintf(
            "Filename: %s\nExtension: %s\nSize: %d bytes\nModified: %s",
            $info['basename'],
            $info['extension'] ?? 'unknown',
            $stat['size'] ?? 0,
            date('Y-m-d H:i:s', $stat['mtime'] ?? time())
        );
    }
    
    /**
     * Clean text content
     * 
     * @param string $content
     * @return string
     */
    private function cleanTextContent(string $content): string {
        // Remove multiple spaces
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove control characters
        $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
        
        // Trim
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Get storage statistics
     * 
     * @param int $companyId
     * @return array
     */
    public function getStorageStatistics(int $companyId): array {
        $cacheKey = "stats_{$companyId}";
        
        // Check cache
        if (isset($this->statsCache[$cacheKey])) {
            $cached = $this->statsCache[$cacheKey];
            if ($cached['timestamp'] > time() - self::CACHE_TTL) {
                return $cached['data'];
            }
        }
        
        $companyPath = $this->basePath . '/' . $companyId;
        
        $stats = [
            'total_size' => 0,
            'file_count' => 0,
            'largest_file' => 0,
            'by_extension' => [],
            'by_month' => []
        ];
        
        if (is_dir($companyPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($companyPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size = $file->getSize();
                    $ext = strtolower($file->getExtension());
                    
                    $stats['total_size'] += $size;
                    $stats['file_count']++;
                    $stats['largest_file'] = max($stats['largest_file'], $size);
                    
                    // By extension
                    if (!isset($stats['by_extension'][$ext])) {
                        $stats['by_extension'][$ext] = ['count' => 0, 'size' => 0];
                    }
                    $stats['by_extension'][$ext]['count']++;
                    $stats['by_extension'][$ext]['size'] += $size;
                    
                    // By month
                    $month = date('Y-m', $file->getMTime());
                    if (!isset($stats['by_month'][$month])) {
                        $stats['by_month'][$month] = ['count' => 0, 'size' => 0];
                    }
                    $stats['by_month'][$month]['count']++;
                    $stats['by_month'][$month]['size'] += $size;
                }
            }
        }
        
        // Cache results
        $this->statsCache[$cacheKey] = [
            'timestamp' => time(),
            'data' => $stats
        ];
        
        return $stats;
    }
    
    /**
     * Clean temporary files
     * 
     * @param int $olderThanHours
     * @return int Number of files deleted
     */
    public function cleanTemporaryFiles(int $olderThanHours = 24): int {
        $tempDir = $this->basePath . '/temp';
        $deleted = 0;
        
        if (is_dir($tempDir)) {
            $cutoffTime = time() - ($olderThanHours * 3600);
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                    if (unlink($file->getPathname())) {
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Create security files in directory
     * 
     * @param string $directory
     * @return void
     */
    private function createSecurityFiles(string $directory): void {
        // .htaccess
        $htaccess = "# Deny all access\nDeny from all\n\n# Disable PHP execution\n<FilesMatch \"\\.php$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>";
        file_put_contents($directory . '/.htaccess', $htaccess);
        
        // index.html
        $indexHtml = '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>';
        file_put_contents($directory . '/index.html', $indexHtml);
    }
    
    /**
     * Check if command exists
     * 
     * @param string $command
     * @return bool
     */
    private function commandExists(string $command): bool {
        $output = [];
        $returnCode = 0;
        exec("which {$command} 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }
}