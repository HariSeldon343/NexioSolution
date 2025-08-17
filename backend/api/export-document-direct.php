<?php
/**
 * API per scaricare documenti in formato DOCX o PDF con header/footer
 */

// Error handling - disable display errors and enable logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../middleware/Auth.php';
    
    // Check if vendor autoload exists
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        throw new Exception('Vendor autoload not found. Please run: composer install');
    }
    require_once $vendorAutoload;

    // Check if PHPWord is available
    if (!class_exists('PhpOffice\PhpWord\PhpWord')) {
        throw new Exception('PHPWord library not found. Please run: composer require phpoffice/phpword');
    }

    use PhpOffice\PhpWord\PhpWord;
    use PhpOffice\PhpWord\IOFactory;
    use PhpOffice\PhpWord\Shared\Html;
    use PhpOffice\PhpWord\SimpleType\Jc;

    $auth = Auth::getInstance();
    $auth->requireAuth();

    $type = $_GET['type'] ?? 'docx';
    $docId = $_GET['doc_id'] ?? null;

    if (!$docId) {
        throw new Exception('ID documento mancante');
    }

    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $aziendaId = $currentAzienda ? $currentAzienda['azienda_id'] : null;

    // Get document from database
    $stmt = db_query(
        "SELECT * FROM documenti 
         WHERE id = ? 
         AND (azienda_id = ? OR azienda_id IS NULL OR ?)",
        [$docId, $aziendaId, $auth->isSuperAdmin() ? 1 : 0]
    );

    $document = $stmt->fetch();
    if (!$document) {
        throw new Exception('Documento non trovato o accesso negato');
    }

    // Parse metadata for header/footer settings
    $metadata = json_decode($document['metadata'] ?? '{}', true);
    $headerText = $metadata['header_text'] ?? '';
    $footerText = $metadata['footer_text'] ?? '';
    $pageNumbering = $metadata['page_numbering'] ?? false;
    $pageNumberFormat = $metadata['page_number_format'] ?? 'page_x_of_y';

    // Generate filename
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $document['titolo']) . '_' . date('Y-m-d');

    if ($type === 'docx') {
    // Generate DOCX with PHPWord
    $phpWord = new PhpWord();
    
    // Document properties
    $phpWord->getDocInfo()->setCreator($user['nome'] . ' ' . $user['cognome']);
    $phpWord->getDocInfo()->setCompany('Nexio Platform');
    $phpWord->getDocInfo()->setTitle($document['titolo']);
    
    // Add section
    $section = $phpWord->addSection([
        'marginLeft' => 1440,
        'marginRight' => 1440,
        'marginTop' => 1440,
        'marginBottom' => 1440,
    ]);
    
    // Add header if specified
    if (!empty($headerText)) {
        $header = $section->addHeader();
        $header->addText(
            $headerText, 
            ['size' => 10], 
            ['alignment' => Jc::CENTER]
        );
    }
    
    // Add footer with page numbers if specified
    if (!empty($footerText) || $pageNumbering) {
        $footer = $section->addFooter();
        
        if ($pageNumbering) {
            $footerTable = $footer->addTable();
            $footerTable->addRow();
            
            // Footer text on the left
            $cell1 = $footerTable->addCell(4500);
            if (!empty($footerText)) {
                $cell1->addText($footerText, ['size' => 10]);
            }
            
            // Page numbers on the right
            $cell2 = $footerTable->addCell(4500);
            $pageNumberText = '';
            switch ($pageNumberFormat) {
                case 'page_x':
                    $pageNumberText = 'Pagina {PAGE}';
                    break;
                case 'page_x_of_y':
                    $pageNumberText = 'Pagina {PAGE} di {NUMPAGES}';
                    break;
                case 'x_of_y':
                    $pageNumberText = '{PAGE} / {NUMPAGES}';
                    break;
                case 'simple':
                    $pageNumberText = '{PAGE}';
                    break;
            }
            $cell2->addPreserveText(
                $pageNumberText, 
                ['size' => 10], 
                ['alignment' => Jc::RIGHT]
            );
        } else if (!empty($footerText)) {
            $footer->addText($footerText, ['size' => 10]);
        }
    }
    
    // Add document content
    $content = $document['contenuto_html'] ?? '<p>Documento vuoto</p>';
    Html::addHtml($section, $content, false, false);
    
    // Output DOCX
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '.docx"');
    header('Cache-Control: max-age=0');
    
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    
} elseif ($type === 'pdf') {
    // Generate PDF with DOMPDF
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $dompdf = new \Dompdf\Dompdf();
    
    // Prepare HTML with header/footer CSS
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                size: A4;
                margin: 2.5cm 2cm;';
    
    if (!empty($headerText)) {
        $html .= '
                @top-center {
                    content: "' . htmlspecialchars($headerText) . '";
                    font-size: 10pt;
                }';
    }
    
    if (!empty($footerText) || $pageNumbering) {
        $html .= '
                @bottom-left {
                    content: "' . htmlspecialchars($footerText) . '";
                    font-size: 10pt;
                }';
        
        if ($pageNumbering) {
            $html .= '
                @bottom-right {
                    content: ';
            switch ($pageNumberFormat) {
                case 'page_x':
                    $html .= '"Pagina " counter(page)';
                    break;
                case 'page_x_of_y':
                    $html .= '"Pagina " counter(page) " di " counter(pages)';
                    break;
                case 'x_of_y':
                    $html .= 'counter(page) " / " counter(pages)';
                    break;
                case 'simple':
                    $html .= 'counter(page)';
                    break;
            }
            $html .= ';
                    font-size: 10pt;
                }';
        }
    }
    
    $html .= '
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                color: #333;
            }
            h1, h2, h3, h4, h5, h6 {
                color: #2c3e50;
                margin-top: 1em;
                margin-bottom: 0.5em;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin: 1em 0;
            }
            table td, table th {
                border: 1px solid #ddd;
                padding: 8px;
            }
            table th {
                background: #f5f5f5;
                font-weight: bold;
            }
        </style>
    </head>
    <body>';
    
    $html .= $document['contenuto_html'] ?? '<p>Documento vuoto</p>';
    $html .= '</body></html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Cache-Control: max-age=0');
    
    echo $dompdf->output();
    
    } else {
        throw new Exception('Formato non supportato: ' . $type);
    }

} catch (Exception $e) {
    // Log the error
    error_log('Error in download-export.php: ' . $e->getMessage());
    
    // Return a proper error response
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'Errore durante l\'esportazione: ' . $e->getMessage();
    echo "\n\nSe il problema persiste, contattare l'amministratore.";
    exit;
}
?>