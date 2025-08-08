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
            
            $documentContent = $documento['contenuto'];
            $documentTitle = $documento['titolo'];
        }
        
        switch ($format) {
            case 'pdf':
                $result = exportToPDF($documentContent, $documentTitle);
                break;
                
            case 'docx':
                $result = exportToDocx($documentContent, $documentTitle);
                break;
                
            case 'html':
                $result = exportToHtml($documentContent, $documentTitle);
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

function exportToPDF($content, $title) {
    require_once '../utils/DompdfGenerator.php';
    
    try {
        $generator = new DompdfGenerator();
        
        // Pulisci il contenuto HTML
        $cleanContent = cleanHTMLForPDF($content);
        
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

function exportToDocx($content, $title) {
    try {
        // Per ora, esportiamo come HTML con mimetype Word
        $cleanContent = cleanHTMLForWord($content);
        
        $filename = sanitizeFilename($title) . '_' . date('Y-m-d_H-i-s') . '.doc';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        
        // Crea un file HTML che Word può aprire
        $wordHTML = generateWordHTML($cleanContent, $title);
        file_put_contents($tempPath, $wordHTML);
        
        return [
            'format' => 'docx',
            'filename' => $filename,
            'path' => $tempPath,
            'download_url' => 'backend/api/download-export.php?file=' . urlencode($filename),
            'size' => strlen($wordHTML)
        ];
        
    } catch (Exception $e) {
        throw new Exception('Errore nella generazione DOCX: ' . $e->getMessage());
    }
}

function exportToHtml($content, $title) {
    try {
        $cleanContent = $content;
        
        $filename = sanitizeFilename($title) . '_' . date('Y-m-d_H-i-s') . '.html';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        
        $htmlContent = generateFullHTML($cleanContent, $title);
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

function cleanHTMLForPDF($html) {
    // Rimuove elementi TinyMCE specifici e pulisce HTML
    $html = preg_replace('/<div[^>]*class="mce-pagebreak"[^>]*>.*?<\/div>/is', '<div style="page-break-before: always;"></div>', $html);
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><table><tr><td><th><div><span>');
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