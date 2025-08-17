<?php
/**
 * Test Page for Header/Footer Functionality
 * This page tests the document editor header, footer, and page numbering features
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$testPassed = [];
$testFailed = [];

// Test 1: Check if metadata column exists in documenti table
try {
    $stmt = db_query("SHOW COLUMNS FROM documenti LIKE 'metadata'");
    if ($stmt->rowCount() > 0) {
        $testPassed[] = "✓ metadata column exists in documenti table";
    } else {
        $testFailed[] = "✗ metadata column missing in documenti table";
    }
} catch (Exception $e) {
    $testFailed[] = "✗ Error checking documenti table: " . $e->getMessage();
}

// Test 2: Check if metadata column exists in document_versions table
try {
    $stmt = db_query("SHOW COLUMNS FROM document_versions LIKE 'metadata'");
    if ($stmt->rowCount() > 0) {
        $testPassed[] = "✓ metadata column exists in document_versions table";
    } else {
        $testFailed[] = "✗ metadata column missing in document_versions table";
    }
} catch (Exception $e) {
    $testFailed[] = "✗ Error checking document_versions table: " . $e->getMessage();
}

// Test 3: Check if uploads/exports directory exists
$exportDir = __DIR__ . '/uploads/exports';
if (is_dir($exportDir)) {
    $testPassed[] = "✓ uploads/exports directory exists";
    if (is_writable($exportDir)) {
        $testPassed[] = "✓ uploads/exports directory is writable";
    } else {
        $testFailed[] = "✗ uploads/exports directory is not writable";
    }
} else {
    $testFailed[] = "✗ uploads/exports directory does not exist";
}

// Test 4: Check if PHPWord is installed
if (class_exists('\PhpOffice\PhpWord\PhpWord')) {
    $testPassed[] = "✓ PHPWord library is installed";
} else {
    $testFailed[] = "✗ PHPWord library is not installed";
}

// Test 5: Check if DOMPDF is installed
if (class_exists('\Dompdf\Dompdf')) {
    $testPassed[] = "✓ DOMPDF library is installed";
} else {
    $testFailed[] = "✗ DOMPDF library is not installed";
}

// Test 6: Test saving document with header/footer metadata
try {
    // Create a test document
    $testMetadata = json_encode([
        'header_text' => 'Test Header',
        'footer_text' => 'Test Footer',
        'page_numbering' => true,
        'page_number_format' => 'Page {PAGE} of {NUMPAGES}'
    ]);
    
    $stmt = db_query(
        "INSERT INTO documenti (titolo, contenuto, metadata, creato_da, azienda_id, data_creazione) 
         VALUES (?, ?, ?, ?, ?, NOW())",
        ['Test Document for Header/Footer', '<p>Test content</p>', $testMetadata, $auth->getUser()['id'], $auth->getCurrentAzienda()['id'] ?? null]
    );
    
    $testDocId = db_connection()->lastInsertId();
    $testPassed[] = "✓ Successfully created test document with header/footer metadata (ID: $testDocId)";
    
    // Clean up test document
    db_query("DELETE FROM documenti WHERE id = ?", [$testDocId]);
    
} catch (Exception $e) {
    $testFailed[] = "✗ Error creating test document: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Header/Footer Functionality</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-passed { color: #28a745; font-weight: 500; }
        .test-failed { color: #dc3545; font-weight: 500; }
        .feature-card { border-left: 4px solid #0d6efd; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Header/Footer Functionality Test Results</h1>
        
        <!-- Test Results -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">System Tests</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($testPassed)): ?>
                    <h5 class="text-success">Passed Tests (<?php echo count($testPassed); ?>)</h5>
                    <ul class="list-unstyled">
                        <?php foreach ($testPassed as $test): ?>
                            <li class="test-passed mb-2"><?php echo $test; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($testFailed)): ?>
                    <h5 class="text-danger mt-3">Failed Tests (<?php echo count($testFailed); ?>)</h5>
                    <ul class="list-unstyled">
                        <?php foreach ($testFailed as $test): ?>
                            <li class="test-failed mb-2"><?php echo $test; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (empty($testFailed)): ?>
                    <div class="alert alert-success">
                        <strong>✓ All tests passed!</strong> The header/footer functionality is properly configured.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Feature Description -->
        <div class="card feature-card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Implemented Features</h4>
            </div>
            <div class="card-body">
                <h5>1. UI Interface in Document Editor</h5>
                <ul>
                    <li>Collapsible "Page Layout Settings" panel in the sidebar</li>
                    <li>Input fields for header and footer text</li>
                    <li>Checkbox to enable/disable page numbering</li>
                    <li>Dropdown for page number format selection</li>
                    <li>Apply settings button to save configuration</li>
                    <li>Export buttons for DOCX and PDF with headers/footers</li>
                </ul>
                
                <h5 class="mt-3">2. Backend API Support</h5>
                <ul>
                    <li>Extended document-edit-save.php to handle header/footer parameters</li>
                    <li>PHPWord integration for DOCX generation with headers/footers</li>
                    <li>DOMPDF integration for PDF generation with headers/footers</li>
                    <li>Metadata storage in both documenti and document_versions tables</li>
                </ul>
                
                <h5 class="mt-3">3. DOCX Import/Export</h5>
                <ul>
                    <li>Extraction of header/footer content from imported DOCX files</li>
                    <li>Pre-population of UI fields with extracted values</li>
                    <li>Preservation of formatting during conversion</li>
                </ul>
                
                <h5 class="mt-3">4. Page Number Formats</h5>
                <ul>
                    <li>"Page X" - Simple page number</li>
                    <li>"Page X of Y" - Page number with total pages</li>
                    <li>"X / Y" - Compact format</li>
                    <li>"- X -" - Decorative format</li>
                </ul>
                
                <h5 class="mt-3">5. Database/Versioning</h5>
                <ul>
                    <li>Header/footer settings stored in metadata JSON field</li>
                    <li>Version history maintains settings for each version</li>
                    <li>Settings can be retrieved and restored from any version</li>
                </ul>
            </div>
        </div>
        
        <!-- How to Use -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">How to Use</h4>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Open a document</strong> in the editor by clicking on it in the filesystem</li>
                    <li><strong>Look for the "Page Layout Settings"</strong> panel in the right sidebar</li>
                    <li><strong>Enter your header text</strong> (e.g., "Company Name - Confidential")</li>
                    <li><strong>Enter your footer text</strong> (e.g., "© 2025 Your Company")</li>
                    <li><strong>Enable page numbering</strong> if desired and select a format</li>
                    <li><strong>Click "Apply Settings"</strong> to save your configuration</li>
                    <li><strong>Use Export buttons</strong> to download DOCX or PDF with headers/footers</li>
                </ol>
                
                <div class="alert alert-info mt-3">
                    <strong>Note:</strong> Headers and footers are visible only in exported DOCX and PDF files, not in the web editor view.
                </div>
            </div>
        </div>
        
        <!-- Test Actions -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Test Actions</h4>
            </div>
            <div class="card-body">
                <a href="filesystem.php" class="btn btn-primary">Go to Filesystem</a>
                <a href="document-editor.php?id=1" class="btn btn-secondary">Open Document Editor</a>
                <button onclick="location.reload()" class="btn btn-outline-primary">Re-run Tests</button>
            </div>
        </div>
    </div>
</body>
</html>