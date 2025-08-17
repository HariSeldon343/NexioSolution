<?php
/**
 * API per il salvataggio dei documenti dall'editor TinyMCE
 * Gestisce versionamento, salvataggio del contenuto HTML, header/footer e page numbering
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/CSRFTokenManager.php';

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
    
    // Validazione CSRF token
    CSRFTokenManager::validateRequest();
    
    // Leggi dati JSON dal body della richiesta
    $input = json_decode(file_get_contents('php://input'), true);
    
    $documentId = $input['documento_id'] ?? null;
    $contentHtml = $input['content_html'] ?? '';
    $isMajorVersion = $input['is_major_version'] ?? false;
    
    // Nuovi campi per header/footer e page numbering
    $headerText = $input['header_text'] ?? '';
    $footerText = $input['footer_text'] ?? '';
    $pageNumbering = $input['page_numbering'] ?? false;
    $pageNumberFormat = $input['page_number_format'] ?? 'Page {PAGE}';
    $exportFormat = $input['export_format'] ?? null;
    
    if (!$documentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID documento mancante'
        ]);
        exit;
    }
    
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $aziendaId = $currentAzienda['id'] ?? null;
    $isSuperAdmin = $auth->isSuperAdmin();
    
    // Verifica permessi sul documento
    $stmt = db_query(
        "SELECT * FROM documenti 
         WHERE id = ? AND (azienda_id = ? OR azienda_id IS NULL OR ?)",
        [$documentId, $aziendaId, $isSuperAdmin ? 1 : 0]
    );
    
    $document = $stmt->fetch();
    if (!$document) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Documento non trovato o accesso negato'
        ]);
        exit;
    }
    
    db_connection()->beginTransaction();
    
    try {
        // Controlla se esiste la tabella document_versions
        $stmt = db_query("SHOW TABLES LIKE 'document_versions'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Ottieni il numero di versione corrente
            $stmt = db_query(
                "SELECT MAX(version_number) as max_version FROM document_versions WHERE document_id = ?",
                [$documentId]
            );
            $result = $stmt->fetch();
            $currentVersion = $result['max_version'] ?? 0;
            $newVersion = $currentVersion + 1;
            
            // Prepara metadata per versione
            $versionMetadata = json_encode([
                'header_text' => $headerText,
                'footer_text' => $footerText,
                'page_numbering' => $pageNumbering,
                'page_number_format' => $pageNumberFormat
            ]);
            
            // Inserisci nuova versione con metadata
            db_query(
                "INSERT INTO document_versions (document_id, version_number, contenuto_html, created_by, created_at, is_major_version, metadata) 
                 VALUES (?, ?, ?, ?, NOW(), ?, ?)",
                [$documentId, $newVersion, $contentHtml, $user['id'], $isMajorVersion ? 1 : 0, $versionMetadata]
            );
        }
        
        // Aggiorna il documento principale
        $updateFields = [
            "data_modifica = NOW()"
        ];
        $updateParams = [];
        
        // Se la tabella documenti ha un campo contenuto_html, aggiornalo
        $stmt = db_query("SHOW COLUMNS FROM documenti LIKE 'contenuto_html'");
        if ($stmt->rowCount() > 0) {
            $updateFields[] = "contenuto_html = ?";
            $updateParams[] = $contentHtml;
        }
        
        // Aggiorna metadata con header/footer settings
        $stmt = db_query("SELECT metadata FROM documenti WHERE id = ?", [$documentId]);
        $existingDoc = $stmt->fetch();
        $existingMetadata = json_decode($existingDoc['metadata'] ?? '{}', true);
        
        $existingMetadata['header_text'] = $headerText;
        $existingMetadata['footer_text'] = $footerText;
        $existingMetadata['page_numbering'] = $pageNumbering;
        $existingMetadata['page_number_format'] = $pageNumberFormat;
        
        $updateFields[] = "metadata = ?";
        $updateParams[] = json_encode($existingMetadata);
        
        // Aggiungi ID documento alla fine dei parametri
        $updateParams[] = $documentId;
        
        if (!empty($updateFields)) {
            $updateQuery = "UPDATE documenti SET " . implode(', ', $updateFields) . " WHERE id = ?";
            db_query($updateQuery, $updateParams);
        }
        
        // Log attività
        if (function_exists('logActivity')) {
            logActivity(
                'documento_modificato',
                "Documento '{$document['titolo']}' modificato",
                $user['id'],
                $aziendaId,
                ['documento_id' => $documentId, 'versione' => $newVersion ?? 1]
            );
        }
        
        db_connection()->commit();
        
        // Se richiesto, genera esportazione con header/footer
        $exportUrl = null;
        if ($exportFormat === 'docx') {
            $exportUrl = generateDocxWithHeaderFooter($documentId, $contentHtml, $headerText, $footerText, $pageNumbering, $pageNumberFormat);
        } elseif ($exportFormat === 'pdf') {
            $exportUrl = generatePdfWithHeaderFooter($documentId, $contentHtml, $headerText, $footerText, $pageNumbering, $pageNumberFormat);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento salvato con successo',
            'version' => $newVersion ?? 1,
            'timestamp' => date('Y-m-d H:i:s'),
            'export_url' => $exportUrl
        ]);
        
    } catch (Exception $e) {
        db_connection()->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Document Save Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore nel salvataggio del documento: ' . $e->getMessage()
    ]);
}

/**
 * Genera documento DOCX con header e footer usando PHPWord
 */
function generateDocxWithHeaderFooter($documentId, $contentHtml, $headerText, $footerText, $pageNumbering, $pageNumberFormat) {
    require_once '../../vendor/autoload.php';
    
    try {
        // Crea nuovo documento PHPWord
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        
        // Impostazioni documento
        $phpWord->getDocInfo()->setCreator('Nexio Platform');
        $phpWord->getDocInfo()->setLastModifiedBy($_SESSION['user']['nome'] ?? 'Sistema');
        $phpWord->getDocInfo()->setTitle('Documento ' . $documentId);
        
        // Crea sezione
        $section = $phpWord->addSection([
            'marginTop' => 1440,    // 1 inch = 1440 twips
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
            'headerHeight' => 720,
            'footerHeight' => 720
        ]);
        
        // Aggiungi header se presente
        if (!empty($headerText)) {
            $header = $section->addHeader();
            $header->addText(
                $headerText,
                ['name' => 'Arial', 'size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );
        }
        
        // Aggiungi footer con page numbering se richiesto
        $footer = $section->addFooter();
        
        if (!empty($footerText)) {
            $footer->addText(
                $footerText,
                ['name' => 'Arial', 'size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]
            );
        }
        
        if ($pageNumbering) {
            // Formatta il numero di pagina secondo il formato scelto
            $pageNumberText = str_replace(
                ['{PAGE}', '{NUMPAGES}'],
                ['', ''],  // Placeholder per i valori reali
                $pageNumberFormat
            );
            
            // Aggiungi campo numero pagina
            $footer->addPreserveText(
                str_replace('{PAGE}', '{PAGE}', $pageNumberFormat),
                ['name' => 'Arial', 'size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );
        }
        
        // Converti HTML in elementi PHPWord
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $contentHtml, false, false);
        
        // Salva il documento
        $filename = 'document_' . $documentId . '_' . time() . '.docx';
        $filepath = '../../uploads/exports/' . $filename;
        
        // Crea directory se non esiste
        if (!is_dir('../../uploads/exports')) {
            mkdir('../../uploads/exports', 0777, true);
        }
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filepath);
        
        return 'backend/api/download-export.php?file=' . urlencode($filename) . '&type=docx';
        
    } catch (Exception $e) {
        error_log("DOCX Generation Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Genera PDF con header e footer usando DOMPDF
 */
function generatePdfWithHeaderFooter($documentId, $contentHtml, $headerText, $footerText, $pageNumbering, $pageNumberFormat) {
    require_once '../../vendor/autoload.php';
    
    try {
        // Prepara HTML completo con header e footer
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 100px 50px 80px 50px;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 50px;
            text-align: center;
            font-size: 10pt;
            color: #666;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 40px;
            font-size: 10pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .footer-text {
            float: left;
        }
        
        .page-number {
            text-align: center;
        }
        
        .page-number:after {
            content: "' . str_replace(['{PAGE}', '{NUMPAGES}'], ['" counter(page) "', '" counter(pages) "'], $pageNumberFormat) . '";
        }
        
        h1 { font-size: 24pt; margin: 20px 0; }
        h2 { font-size: 18pt; margin: 18px 0; }
        h3 { font-size: 14pt; margin: 16px 0; }
        p { margin: 12px 0; text-align: justify; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
    </style>
</head>
<body>';
        
        // Aggiungi header se presente
        if (!empty($headerText)) {
            $html .= '<div class="header">' . htmlspecialchars($headerText) . '</div>';
        }
        
        // Aggiungi footer
        $html .= '<div class="footer">';
        if (!empty($footerText)) {
            $html .= '<div class="footer-text">' . htmlspecialchars($footerText) . '</div>';
        }
        if ($pageNumbering) {
            $html .= '<div class="page-number"></div>';
        }
        $html .= '</div>';
        
        // Aggiungi contenuto principale
        $html .= '<div class="content">' . $contentHtml . '</div>';
        $html .= '</body></html>';
        
        // Genera PDF con DOMPDF
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Salva il file
        $filename = 'document_' . $documentId . '_' . time() . '.pdf';
        $filepath = '../../uploads/exports/' . $filename;
        
        // Crea directory se non esiste
        if (!is_dir('../../uploads/exports')) {
            mkdir('../../uploads/exports', 0777, true);
        }
        
        file_put_contents($filepath, $dompdf->output());
        
        return 'backend/api/download-export.php?file=' . urlencode($filename) . '&type=pdf';
        
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return null;
    }
}
?>
