<?php
/**
 * Fallback ZIP implementation using PclZip when ZipArchive is not available
 * 
 * @package Nexio
 * @version 1.0.0
 */

class ZipArchiveFallback {
    private $zipFile;
    private $files = [];
    private $folders = [];
    
    const CREATE = 1;
    const OVERWRITE = 8;
    
    public function __construct() {
        // Check if we can use native ZipArchive
        if (class_exists('ZipArchive')) {
            throw new Exception('Use native ZipArchive instead');
        }
    }
    
    /**
     * Open/create a ZIP file
     */
    public function open($filename, $flags = 0) {
        $this->zipFile = $filename;
        $this->files = [];
        $this->folders = [];
        
        // If overwrite, delete existing file
        if (($flags & self::OVERWRITE) && file_exists($filename)) {
            unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Add a file from string
     */
    public function addFromString($localname, $contents) {
        $this->files[] = [
            'name' => $localname,
            'content' => $contents,
            'type' => 'string'
        ];
        return true;
    }
    
    /**
     * Add a file from filesystem
     */
    public function addFile($filename, $localname = null) {
        if (!file_exists($filename)) {
            return false;
        }
        
        if ($localname === null) {
            $localname = basename($filename);
        }
        
        $this->files[] = [
            'name' => $localname,
            'path' => $filename,
            'type' => 'file'
        ];
        return true;
    }
    
    /**
     * Add empty directory
     */
    public function addEmptyDir($dirname) {
        $this->folders[] = $dirname;
        return true;
    }
    
    /**
     * Close and create the ZIP file using shell command
     */
    public function close() {
        if (empty($this->files) && empty($this->folders)) {
            return false;
        }
        
        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/zip_' . uniqid();
        if (!mkdir($tempDir, 0777, true)) {
            return false;
        }
        
        try {
            // Create folder structure
            foreach ($this->folders as $folder) {
                $folderPath = $tempDir . '/' . $folder;
                if (!is_dir($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }
            }
            
            // Add files
            foreach ($this->files as $file) {
                $filePath = $tempDir . '/' . $file['name'];
                $fileDir = dirname($filePath);
                
                if (!is_dir($fileDir)) {
                    mkdir($fileDir, 0777, true);
                }
                
                if ($file['type'] === 'string') {
                    file_put_contents($filePath, $file['content']);
                } else {
                    copy($file['path'], $filePath);
                }
            }
            
            // Try to use Windows ZIP command (PowerShell)
            if (PHP_OS_FAMILY === 'Windows' || stripos(PHP_OS, 'WIN') === 0) {
                $this->createZipWindows($tempDir);
            } else {
                $this->createZipLinux($tempDir);
            }
            
            // Clean up temp directory
            $this->deleteDirectory($tempDir);
            
            return file_exists($this->zipFile);
            
        } catch (Exception $e) {
            // Clean up on error
            $this->deleteDirectory($tempDir);
            return false;
        }
    }
    
    /**
     * Create ZIP using Windows PowerShell
     */
    private function createZipWindows($sourceDir) {
        // Convert paths for Windows
        $winSource = str_replace('/', '\\', $sourceDir);
        $winDest = str_replace('/', '\\', realpath(dirname($this->zipFile))) . '\\' . basename($this->zipFile);
        
        // PowerShell command to create ZIP
        $psCommand = sprintf(
            'powershell -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path \'%s\\*\' -DestinationPath \'%s\' -Force"',
            $winSource,
            $winDest
        );
        
        exec($psCommand, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Fallback: Try using tar (available in Windows 10+)
            $tarCommand = sprintf(
                'tar -czf "%s" -C "%s" .',
                $winDest,
                $winSource
            );
            exec($tarCommand, $output, $returnCode);
        }
    }
    
    /**
     * Create ZIP using Linux commands
     */
    private function createZipLinux($sourceDir) {
        $zipCommand = sprintf(
            'cd "%s" && zip -r "%s" .',
            $sourceDir,
            $this->zipFile
        );
        
        exec($zipCommand, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Fallback: Try tar
            $tarCommand = sprintf(
                'tar -czf "%s" -C "%s" .',
                $this->zipFile,
                $sourceDir
            );
            exec($tarCommand);
        }
    }
    
    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        
        rmdir($dir);
    }
}

/**
 * Factory function to get ZIP implementation
 */
function getZipArchive() {
    if (class_exists('ZipArchive')) {
        return new ZipArchive();
    } else {
        return new ZipArchiveFallback();
    }
}