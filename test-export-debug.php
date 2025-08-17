<?php
/**
 * Debug script per testare l'export DOCX/PDF
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Export Debug</h1>";

// Test 1: Check vendor autoload
echo "<h2>1. Checking vendor/autoload.php</h2>";
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "✅ vendor/autoload.php found<br>";
    require_once $vendorPath;
} else {
    die("❌ vendor/autoload.php NOT found at: $vendorPath");
}

// Test 2: Check PHPWord
echo "<h2>2. Checking PHPWord</h2>";
if (class_exists('PhpOffice\PhpWord\PhpWord')) {
    echo "✅ PHPWord class available<br>";
    
    // Try to create instance
    try {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        echo "✅ PHPWord instance created successfully<br>";
        
        // Check Jc class
        if (class_exists('PhpOffice\PhpWord\SimpleType\Jc')) {
            echo "✅ Jc alignment class available<br>";
        } else {
            echo "❌ Jc alignment class NOT available<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error creating PHPWord: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ PHPWord class NOT available<br>";
}

// Test 3: Check DOMPDF
echo "<h2>3. Checking DOMPDF</h2>";
if (class_exists('Dompdf\Dompdf')) {
    echo "✅ DOMPDF class available<br>";
    
    try {
        $dompdf = new \Dompdf\Dompdf();
        echo "✅ DOMPDF instance created successfully<br>";
    } catch (Exception $e) {
        echo "❌ Error creating DOMPDF: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ DOMPDF class NOT available<br>";
}

// Test 4: Check database connection
echo "<h2>4. Checking Database</h2>";
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

try {
    $auth = Auth::getInstance();
    echo "✅ Auth instance created<br>";
    
    // Check if authenticated
    if (isset($_SESSION['user'])) {
        echo "✅ User authenticated: " . $_SESSION['user']['nome'] . "<br>";
    } else {
        echo "⚠️ Not authenticated - please login first<br>";
    }
    
    // Test database query
    $stmt = db_query("SELECT COUNT(*) as count FROM documenti");
    $result = $stmt->fetch();
    echo "✅ Database connected - Documents count: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 5: Simple DOCX generation
echo "<h2>5. Test Simple DOCX Generation</h2>";
try {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    
    // Add header
    $header = $section->addHeader();
    $header->addText('Test Header', ['size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    
    // Add content
    $section->addText('Test Document Content');
    
    // Add footer
    $footer = $section->addFooter();
    $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);
    
    echo "✅ DOCX structure created successfully<br>";
    
    // Try to save to temp
    $tempFile = sys_get_temp_dir() . '/test_' . time() . '.docx';
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($tempFile);
    
    if (file_exists($tempFile)) {
        echo "✅ DOCX file saved successfully to: $tempFile<br>";
        echo "File size: " . filesize($tempFile) . " bytes<br>";
        unlink($tempFile); // Clean up
    } else {
        echo "❌ Failed to save DOCX file<br>";
    }
    
} catch (Exception $e) {
    echo "❌ DOCX generation error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 6: Check metadata in a document
echo "<h2>6. Test Document Metadata</h2>";
if (isset($_SESSION['user'])) {
    try {
        $stmt = db_query("SELECT id, titolo, metadata FROM documenti WHERE id = 13");
        $doc = $stmt->fetch();
        
        if ($doc) {
            echo "Document ID 13 found: " . $doc['titolo'] . "<br>";
            
            if ($doc['metadata']) {
                $metadata = json_decode($doc['metadata'], true);
                echo "Metadata:<br>";
                echo "<pre>" . print_r($metadata, true) . "</pre>";
            } else {
                echo "No metadata stored<br>";
            }
        } else {
            echo "Document ID 13 not found<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error checking document: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<p><a href='document-editor.php?id=13'>Go to Document Editor</a></p>";
echo "<p><a href='backend/api/download-export.php?type=docx&doc_id=13'>Test DOCX Export</a></p>";
echo "<p><a href='backend/api/download-export.php?type=pdf&doc_id=13'>Test PDF Export</a></p>";
?>