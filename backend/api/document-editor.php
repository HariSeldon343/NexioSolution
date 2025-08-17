<?php
/**
 * API per Editor Documenti
 * Gestisce salvataggio e caricamento documenti dall'editor React
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';

try {
    $auth = Auth::getInstance();
    
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non autenticato'
        ]);
        exit;
    }
    
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $action = $_POST['action'] ?? $_GET['action'] ?? 'load';
    
    switch ($action) {
        case 'save':
            handleSaveDocument($user, $currentAzienda);
            break;
            
        case 'load':
            handleLoadDocument($user, $currentAzienda);
            break;
            
        case 'export':
            handleExportDocument($user, $currentAzienda);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Azione non supportata'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Document Editor API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
}

function handleSaveDocument($user, $currentAzienda) {
    $content = $_POST['content'] ?? '';
    $title = $_POST['title'] ?? 'Documento Senza Titolo';
    $documentId = $_POST['documento_id'] ?? null;
    
    if (empty($content)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Contenuto del documento obbligatorio'
        ]);
        return;
    }
    
    try {
        db_connection()->beginTransaction();
        
        $aziendaId = null;
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        }
        
        if ($documentId) {
            // Aggiorna documento esistente
            $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId]);
            $documento = $stmt->fetch();
            
            if (!$documento) {
                throw new Exception('Documento non trovato');
            }
            
            // Verifica permessi
            if ($documento['creato_da'] != $user['id'] && !in_array($user['ruolo'], ['super_admin', 'admin'])) {
                throw new Exception('Non hai i permessi per modificare questo documento');
            }
            
            // Salva versione precedente
            db_query(
                "INSERT INTO documenti_versioni (documento_id, contenuto, versione, creato_da, creato_il) 
                 VALUES (?, ?, ?, ?, NOW())",
                [$documentId, $documento['contenuto'], $documento['versione'], $user['id']]
            );
            
            // Aggiorna documento
            $newVersion = intval($documento['versione']) + 1;
            db_query(
                "UPDATE documenti SET 
                 titolo = ?, contenuto = ?, versione = ?, ultimo_aggiornamento = NOW() 
                 WHERE id = ?",
                [$title, $content, $newVersion, $documentId]
            );
            
            $resultDocumentId = $documentId;
            
        } else {
            // Crea nuovo documento
            $stmt = db_query(
                "INSERT INTO documenti (titolo, contenuto, tipo, stato, creato_da, azienda_id, creato_il, ultimo_aggiornamento) 
                 VALUES (?, ?, 'documento', 'bozza', ?, ?, NOW(), NOW())",
                [$title, $content, $user['id'], $aziendaId]
            );
            
            $resultDocumentId = db_connection()->lastInsertId();
        }
        
        db_connection()->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento salvato con successo',
            'documento_id' => $resultDocumentId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        db_connection()->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleLoadDocument($user, $currentAzienda) {
    $documentId = $_GET['id'] ?? null;
    
    if (!$documentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID documento mancante'
        ]);
        return;
    }
    
    try {
        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId]);
        $documento = $stmt->fetch();
        
        if (!$documento) {
            throw new Exception('Documento non trovato');
        }
        
        // Verifica permessi di lettura
        $canView = false;
        
        if ($documento['creato_da'] == $user['id']) {
            $canView = true;
        } elseif (in_array($user['ruolo'], ['super_admin', 'admin'])) {
            $canView = true;
        } elseif ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($documento['azienda_id'] == $aziendaId) {
                $canView = true;
            }
        }
        
        if (!$canView) {
            throw new Exception('Non hai i permessi per visualizzare questo documento');
        }
        
        echo json_encode([
            'success' => true,
            'documento' => [
                'id' => $documento['id'],
                'titolo' => $documento['titolo'],
                'contenuto' => $documento['contenuto'],
                'tipo' => $documento['tipo'],
                'stato' => $documento['stato'],
                'versione' => $documento['versione'],
                'creato_il' => $documento['creato_il'],
                'ultimo_aggiornamento' => $documento['ultimo_aggiornamento']
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleExportDocument($user, $currentAzienda) {
    $documentId = $_POST['documento_id'] ?? $_GET['id'] ?? null;
    $format = $_POST['format'] ?? $_GET['format'] ?? 'pdf';
    $content = $_POST['content'] ?? null;
    
    // Header/Footer parameters
    $headerText = $_POST['header_text'] ?? '';
    $footerText = $_POST['footer_text'] ?? '';
    $pageNumbering = filter_var($_POST['page_numbering'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $pageNumberFormat = $_POST['page_number_format'] ?? 'page_x_of_y';
    
    if (!$documentId && !$content) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID documento o contenuto obbligatorio'
        ]);
        return;
    }
    
    try {
        $documentContent = $content;
        $documentTitle = 'Documento';
        $metadata = [];
        
        if ($documentId) {
            $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId]);
            $documento = $stmt->fetch();
            
            if (!$documento) {
                throw new Exception('Documento non trovato');
            }
            
            // Verifica permessi
            $canView = false;
            if ($documento['creato_da'] == $user['id']) {
                $canView = true;
            } elseif (in_array($user['ruolo'], ['super_admin', 'admin'])) {
                $canView = true;
            } elseif ($currentAzienda) {
                $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
                if ($documento['azienda_id'] == $aziendaId) {
                    $canView = true;
                }
            }
            
            if (!$canView) {
                throw new Exception('Non hai i permessi per esportare questo documento');
            }
            
            $documentContent = $documento['contenuto_html'] ?? $documento['contenuto'];
            $documentTitle = $documento['titolo'];
            
            // Get metadata if exists
            if (!empty($documento['metadata'])) {
                $metadata = json_decode($documento['metadata'], true);
                // Use metadata values if not provided in POST
                if (empty($headerText)) $headerText = $metadata['header_text'] ?? '';
                if (empty($footerText)) $footerText = $metadata['footer_text'] ?? '';
                if (!isset($_POST['page_numbering'])) $pageNumbering = $metadata['page_numbering'] ?? false;
                if (!isset($_POST['page_number_format'])) $pageNumberFormat = $metadata['page_number_format'] ?? 'page_x_of_y';
            }
        }
        
        // Process [[TOC]] placeholder if present
        $documentContent = processTOCPlaceholder($documentContent);
        
        // Prepare export options
        $exportOptions = [
            'header_text' => $headerText,
            'footer_text' => $footerText,
            'page_numbering' => $pageNumbering,
            'page_number_format' => $pageNumberFormat
        ];
        
        switch ($format) {
            case 'pdf':
                $result = exportToPDF($documentContent, $documentTitle, $exportOptions);
                break;
                
            case 'docx':
                $result = exportToDocx($documentContent, $documentTitle, $exportOptions);
                break;
                
            case 'html':
                $result = exportToHtml($documentContent, $documentTitle, $exportOptions);
                break;
                
            default:
                throw new Exception('Formato di esportazione non supportato');
        }
        
        echo json_encode([
            'success' => true,
            'export' => $result
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function exportToPDF($content, $title, $options = []) {
    require_once '../utils/DompdfGenerator.php';
    
    try {
        $generator = new DompdfGenerator();
        
        // Pulisci il contenuto HTML
        $cleanContent = cleanHTMLForPDF($content);
        
        // Aggiungi header/footer se specificati
        if (!empty($options['header_text']) || !empty($options['footer_text']) || $options['page_numbering']) {
            $cleanContent = addHeaderFooterToPDF($cleanContent, $title, $options);
        }
        
        // Genera PDF
        $pdfContent = $generator->generateFromHTML($cleanContent, $title);
        
        // Salva temporaneamente
        $filename = sanitizeFilename($title) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $pdfContent);
        
        return [
            'format' => 'pdf',
            'filename' => $filename,
            'path' => $tempPath,
            'download_url' => 'backend/api/download-export.php?file=' . urlencode($filename),
            'size' => strlen($pdfContent)
        ];
        
    } catch (Exception $e) {
        throw new Exception('Errore nella generazione PDF: ' . $e->getMessage());
    }
}

function exportToDocx($content, $title, $options = []) {
    try {
        // Check if PHPWord is available
        if (!class_exists('PhpOffice\PhpWord\PhpWord')) {
            // Fallback to HTML export
            return exportToHtmlAsWord($content, $title, $options);
        }
        
        // Create PHPWord document
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Add section
        $section = $phpWord->addSection([
            'marginLeft' => 1440,
            'marginRight' => 1440,
            'marginTop' => 1440,
            'marginBottom' => 1440,
        ]);
        
        // Add header if specified
        if (!empty($options['header_text'])) {
            $header = $section->addHeader();
            $header->addText(
                $options['header_text'], 
                ['size' => 10], 
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );
        }
        
        // Add footer with page numbers if specified
        if (!empty($options['footer_text']) || $options['page_numbering']) {
            $footer = $section->addFooter();
            
            if ($options['page_numbering']) {
                $footerTable = $footer->addTable();
                $footerTable->addRow();
                
                // Footer text on the left
                $cell1 = $footerTable->addCell(4500);
                if (!empty($options['footer_text'])) {
                    $cell1->addText($options['footer_text'], ['size' => 10]);
                }
                
                // Page numbers on the right
                $cell2 = $footerTable->addCell(4500);
                $pageNumberText = '';
                switch ($options['page_number_format'] ?? 'page_x_of_y') {
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
                    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]
                );
            } else if (!empty($options['footer_text'])) {
                $footer->addText($options['footer_text'], ['size' => 10]);
            }
        }
        
        // Check if content has a TOC (already processed)
        if (strpos($content, 'class="toc-container"') !== false) {
            // Extract TOC and main content separately
            $tocPattern = '/<div class="toc-container"[^>]*>.*?<\/div>/is';
            preg_match($tocPattern, $content, $tocMatches);
            $tocContent = $tocMatches[0] ?? '';
            $mainContent = preg_replace($tocPattern, '', $content);
            
            // Add TOC as a separate section if exists
            if ($tocContent) {
                // Add TOC title
                $section->addText('Indice', ['bold' => true, 'size' => 16]);
                $section->addTextBreak();
                
                // Parse TOC entries from HTML
                if (preg_match_all('/<a[^>]*href="#([^"]*)"[^>]*>(.*?)<\/a>/i', $tocContent, $matches)) {
                    foreach ($matches[2] as $index => $heading) {
                        $cleanHeading = strip_tags($heading);
                        // Determine level based on list item indentation or default to 1
                        $section->addText($cleanHeading, ['size' => 11]);
                    }
                }
                
                // Add page break after TOC
                $section->addPageBreak();
            }
            
            // Add main content
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $mainContent, false, false);
        } else {
            // Add document content normally
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $content, false, false);
        }
        
        // Save to temp file
        $filename = sanitizeFilename($title) . '_' . date('Y-m-d_H-i-s') . '.docx';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return [
            'format' => 'docx',
            'filename' => $filename,
            'path' => $tempPath,
            'download_url' => 'backend/api/download-export.php?file=' . urlencode($filename),
            'size' => filesize($tempPath)
        ];
        
    } catch (Exception $e) {
        throw new Exception('Errore nella generazione DOCX: ' . $e->getMessage());
    }
}

function exportToHtmlAsWord($content, $title, $options = []) {
    // Fallback method when PHPWord is not available
    $cleanContent = cleanHTMLForWord($content);
    
    $filename = sanitizeFilename($title) . '_' . date('Y-m-d_H-i-s') . '.doc';
    $tempPath = sys_get_temp_dir() . '/' . $filename;
    
    // Create HTML that Word can open with header/footer support
    $wordHTML = generateWordHTMLWithHeaderFooter($cleanContent, $title, $options);
    file_put_contents($tempPath, $wordHTML);
    
    return [
        'format' => 'docx',
        'filename' => $filename,
        'path' => $tempPath,
        'download_url' => 'backend/api/download-export.php?file=' . urlencode($filename),
        'size' => strlen($wordHTML)
    ];
}

function exportToHtml($content, $title, $options = []) {
    try {
        $cleanContent = $content;
        
        $filename = sanitizeFilename($title) . '_' . date('Y-m-d_H-i-s') . '.html';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        
        // If header/footer options are provided, generate HTML with those
        if (!empty($options['header_text']) || !empty($options['footer_text']) || $options['page_numbering']) {
            $htmlContent = addHeaderFooterToPDF($cleanContent, $title, $options);
        } else {
            $htmlContent = generateFullHTML($cleanContent, $title);
        }
        
        file_put_contents($tempPath, $htmlContent);
        
        return [
            'format' => 'html',
            'filename' => $filename,
            'path' => $tempPath,
            'download_url' => 'backend/api/download-export.php?file=' . urlencode($filename),
            'size' => strlen($htmlContent)
        ];
        
    } catch (Exception $e) {
        throw new Exception('Errore nella generazione HTML: ' . $e->getMessage());
    }
}

function processTOCPlaceholder($content) {
    // Check if [[TOC]] placeholder exists
    if (strpos($content, '[[TOC]]') === false) {
        return $content;
    }
    
    // Generate TOC from content
    $toc = generateTableOfContents($content);
    
    // Replace placeholder with generated TOC
    $content = str_replace('[[TOC]]', $toc, $content);
    
    return $content;
}

function generateTableOfContents($content) {
    // Create a DOM document to parse the HTML
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    // Find all headings
    $xpath = new DOMXPath($dom);
    $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
    
    if ($headings->length === 0) {
        return '';
    }
    
    $tocHTML = '<div class="toc-container" style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; background: #f9f9f9; page-break-after: always;">';
    $tocHTML .= '<h2 style="margin-top: 0; color: #333;">Indice</h2>';
    $tocHTML .= '<ol style="margin: 0; padding-left: 20px;">';
    
    $headingIndex = 0;
    foreach ($headings as $heading) {
        $level = intval(substr($heading->nodeName, 1));
        $text = $heading->textContent;
        
        // Add ID to heading for internal linking
        $headingId = 'heading-' . $headingIndex;
        $heading->setAttribute('id', $headingId);
        
        // Add appropriate indentation based on heading level
        $indent = ($level - 1) * 20;
        $listStyle = $level === 1 ? 'decimal' : ($level === 2 ? 'lower-alpha' : 'lower-roman');
        
        $tocHTML .= '<li style="margin-left: ' . $indent . 'px; list-style-type: ' . $listStyle . ';">';
        $tocHTML .= '<a href="#' . $headingId . '" style="text-decoration: none; color: #333;">' . htmlspecialchars($text) . '</a>';
        $tocHTML .= '</li>';
        
        $headingIndex++;
    }
    
    $tocHTML .= '</ol>';
    $tocHTML .= '</div>';
    
    // Save the modified DOM back to HTML string
    $modifiedContent = $dom->saveHTML();
    
    // Insert TOC at the beginning or where placeholder was
    return $tocHTML;
}

function cleanHTMLForPDF($html) {
    // Rimuove elementi TinyMCE specifici e pulisce HTML
    $html = preg_replace('/<div[^>]*class="mce-pagebreak"[^>]*>.*?<\/div>/is', '<div style="page-break-before: always;"></div>', $html);
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><table><tr><td><th><div><span><a>');
    return $html;
}

function addHeaderFooterToPDF($content, $title, $options) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @page {
            size: A4;
            margin: 2.5cm 2cm;';
    
    if (!empty($options['header_text'])) {
        $html .= '
            @top-center {
                content: "' . htmlspecialchars($options['header_text']) . '";
                font-size: 10pt;
            }';
    }
    
    if (!empty($options['footer_text']) || $options['page_numbering']) {
        $html .= '
            @bottom-left {
                content: "' . htmlspecialchars($options['footer_text']) . '";
                font-size: 10pt;
            }';
        
        if ($options['page_numbering']) {
            $html .= '
            @bottom-right {
                content: ';
            switch ($options['page_number_format'] ?? 'page_x_of_y') {
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
        h1 { font-size: 24pt; }
        h2 { font-size: 18pt; }
        h3 { font-size: 14pt; }
        h4 { font-size: 12pt; }
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
<body>
    <h1>' . htmlspecialchars($title) . '</h1>
    ' . $content . '
</body>
</html>';
    
    return $html;
}

function cleanHTMLForWord($html) {
    // Mantiene più formattazione per Word
    return $html;
}

function generateWordHTML($content, $title) {
    return "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='ProgId' content='Word.Document'>
    <meta name='Generator' content='Microsoft Word'>
    <title>" . htmlspecialchars($title) . "</title>
    <style>
        @page { size: A4; margin: 2.5cm 1.9cm; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.6; }
        h1 { font-size: 18pt; margin: 24pt 0 12pt 0; }
        h2 { font-size: 16pt; margin: 18pt 0 6pt 0; }
        h3 { font-size: 14pt; margin: 12pt 0 6pt 0; }
        p { margin: 0 0 12pt 0; text-align: justify; }
    </style>
</head>
<body>
    <h1>" . htmlspecialchars($title) . "</h1>
    " . $content . "
</body>
</html>";
}

function generateWordHTMLWithHeaderFooter($content, $title, $options) {
    $headerSection = '';
    $footerSection = '';
    
    // Add header if specified
    if (!empty($options['header_text'])) {
        $headerSection = "
    <div style='mso-element:header' id='h1'>
        <p style='text-align:center; font-size:10pt;'>" . htmlspecialchars($options['header_text']) . "</p>
    </div>";
    }
    
    // Add footer if specified
    if (!empty($options['footer_text']) || $options['page_numbering']) {
        $footerContent = '';
        
        if (!empty($options['footer_text'])) {
            $footerContent .= htmlspecialchars($options['footer_text']);
        }
        
        if ($options['page_numbering']) {
            $pageNum = '';
            switch ($options['page_number_format'] ?? 'page_x_of_y') {
                case 'page_x':
                    $pageNum = "<span style='mso-field-code:\" PAGE \"'></span>";
                    break;
                case 'page_x_of_y':
                    $pageNum = "Pagina <span style='mso-field-code:\" PAGE \"'></span> di <span style='mso-field-code:\" NUMPAGES \"'></span>";
                    break;
                case 'x_of_y':
                    $pageNum = "<span style='mso-field-code:\" PAGE \"'></span> / <span style='mso-field-code:\" NUMPAGES \"'></span>";
                    break;
                case 'simple':
                    $pageNum = "<span style='mso-field-code:\" PAGE \"'></span>";
                    break;
            }
            
            if ($footerContent && $pageNum) {
                $footerSection = "
    <div style='mso-element:footer' id='f1'>
        <table width='100%' border='0' cellpadding='0' cellspacing='0'>
            <tr>
                <td style='text-align:left; font-size:10pt;'>$footerContent</td>
                <td style='text-align:right; font-size:10pt;'>$pageNum</td>
            </tr>
        </table>
    </div>";
            } else if ($pageNum) {
                $footerSection = "
    <div style='mso-element:footer' id='f1'>
        <p style='text-align:right; font-size:10pt;'>$pageNum</p>
    </div>";
            } else {
                $footerSection = "
    <div style='mso-element:footer' id='f1'>
        <p style='text-align:left; font-size:10pt;'>$footerContent</p>
    </div>";
            }
        }
    }
    
    return "<!DOCTYPE html>
<html xmlns:o='urn:schemas-microsoft-com:office:office' 
      xmlns:w='urn:schemas-microsoft-com:office:word' 
      xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset='UTF-8'>
    <meta name='ProgId' content='Word.Document'>
    <meta name='Generator' content='Microsoft Word'>
    <title>" . htmlspecialchars($title) . "</title>
    <style>
        @page Section1 { 
            size: A4; 
            margin: 2.5cm 1.9cm;
            mso-header: h1;
            mso-footer: f1;
        }
        div.Section1 { page: Section1; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.6; }
        h1 { font-size: 18pt; margin: 24pt 0 12pt 0; }
        h2 { font-size: 16pt; margin: 18pt 0 6pt 0; }
        h3 { font-size: 14pt; margin: 12pt 0 6pt 0; }
        p { margin: 0 0 12pt 0; text-align: justify; }
    </style>
</head>
<body>
    <div class='Section1'>
        $headerSection
        <h1>" . htmlspecialchars($title) . "</h1>
        " . $content . "
        $footerSection
    </div>
</body>
</html>";
}

function generateFullHTML($content, $title) {
    return "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($title) . "</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        @media print { body { margin: 0; padding: 2.5cm 1.9cm; } @page { size: A4; } }
    </style>
</head>
<body>
    " . $content . "
</body>
</html>";
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $filename);
    $filename = preg_replace('/\s+/', '_', $filename);
    return substr($filename, 0, 50);
}
?>