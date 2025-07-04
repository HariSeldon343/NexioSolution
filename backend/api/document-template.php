<?php
/**
 * API per gestione documenti con template
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Template.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

// Connessione database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore database: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            $titolo = trim($_POST['title'] ?? '');
            $contenuto = trim($_POST['content'] ?? '');
            $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
            $documento_id = !empty($_POST['documento_id']) ? (int)$_POST['documento_id'] : null;
            
            if (empty($titolo)) {
                throw new Exception('Titolo documento obbligatorio');
            }
            
            if (empty($contenuto) || $contenuto === '<p></p>') {
                throw new Exception('Contenuto documento obbligatorio');
            }
            
            if ($documento_id) {
                // Aggiorna documento esistente
                $sql = "UPDATE documenti SET 
                       titolo = ?, 
                       contenuto = ?, 
                       template_id = ?,
                       ultima_modifica = NOW() 
                       WHERE id = ? AND creato_da = ?";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $titolo,
                    $contenuto,
                    $template_id,
                    $documento_id,
                    $user['id']
                ]);
                
                if (!$result) {
                    throw new Exception('Errore durante l\'aggiornamento del documento');
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Documento aggiornato con successo',
                    'documento_id' => $documento_id
                ]);
                
            } else {
                // Crea nuovo documento
                $codice = 'DOC_' . date('Ymd_His') . '_' . $user['id'];
                
                $sql = "INSERT INTO documenti (
                    titolo, 
                    contenuto, 
                    codice, 
                    template_id,
                    creato_da, 
                    data_creazione
                ) VALUES (?, ?, ?, ?, ?, NOW())";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $titolo,
                    $contenuto,
                    $codice,
                    $template_id,
                    $user['id']
                ]);
                
                if (!$result) {
                    throw new Exception('Errore durante la creazione del documento');
                }
                
                $nuovo_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Documento creato con successo',
                    'documento_id' => $nuovo_id,
                    'codice' => $codice
                ]);
            }
            break;
            
        case 'load':
            $documento_id = (int)($_GET['id'] ?? 0);
            
            if ($documento_id <= 0) {
                throw new Exception('ID documento non valido');
            }
            
            $sql = "SELECT d.*, t.nome as template_nome 
                    FROM documenti d 
                    LEFT JOIN templates t ON d.template_id = t.id 
                    WHERE d.id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$documento_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento non trovato');
            }
            
            // Carica anche i dati del template se presente
            $templateData = null;
            if ($documento['template_id']) {
                $template = new Template($pdo);
                $templateData = $template->getById($documento['template_id']);
            }
            
            echo json_encode([
                'success' => true,
                'documento' => $documento,
                'template' => $templateData
            ]);
            break;
            
        case 'export_pdf':
            $documento_id = (int)($_POST['documento_id'] ?? 0);
            
            if ($documento_id <= 0) {
                throw new Exception('ID documento non valido');
            }
            
            $sql = "SELECT * FROM documenti WHERE id = ? AND creato_da = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$documento_id, $user['id']]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento non trovato');
            }
            
            // Genera PDF con template se presente
            $template = new Template($pdo);
            $templateData = null;
            if ($documento['template_id']) {
                $templateData = $template->getById($documento['template_id']);
            }
            
            // Qui potresti integrare una libreria PDF come TCPDF o DomPDF
            // Per ora restituiamo l'HTML formattato
            $html = generateDocumentHTML($documento, $templateData, $template, $user);
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'message' => 'PDF generato con successo'
            ]);
            break;
            
        case 'list':
            $filtri = [];
            $params = [];
            
            if (!empty($_GET['template_id'])) {
                $filtri[] = "d.template_id = ?";
                $params[] = (int)$_GET['template_id'];
            }
            
            if (!empty($_GET['search'])) {
                $filtri[] = "(d.titolo LIKE ? OR d.contenuto LIKE ?)";
                $search = '%' . $_GET['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }
            
            $where = !empty($filtri) ? 'AND ' . implode(' AND ', $filtri) : '';
            
            $sql = "SELECT d.*, t.nome as template_nome,
                           u.nome as autore_nome, u.cognome as autore_cognome
                    FROM documenti d 
                    LEFT JOIN templates t ON d.template_id = t.id
                    LEFT JOIN utenti u ON d.creato_da = u.id
                    WHERE d.creato_da = ? {$where}
                    ORDER BY d.ultima_modifica DESC";
            
            array_unshift($params, $user['id']);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $documenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'documenti' => $documenti
            ]);
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno del server']);
}

/**
 * Genera HTML completo del documento con template
 */
function generateDocumentHTML($documento, $templateData, $template, $user) {
    $documentData = [
        'titolo' => $documento['titolo'],
        'codice' => $documento['codice'],
        'versione' => $documento['versione'] ?? '1.0',
        'data_revisione' => date('d/m/Y', strtotime($documento['data_creazione'])),
        'logo_azienda' => '/assets/images/logo.png'
    ];
    
    $header = '';
    $footer = '';
    $css = '';
    
    if ($templateData) {
        $header = $template->generateHeader($templateData['id'], $documentData);
        $footer = $template->generateFooter($templateData['id'], $documentData);
        $css = $template->generateCSS($templateData['id']);
    }
    
    return "
    <!DOCTYPE html>
    <html lang='it'>
    <head>
        <meta charset='UTF-8'>
        <title>" . htmlspecialchars($documento['titolo']) . "</title>
        <style>
            @page { size: A4; margin: 2.5cm 1.9cm; }
            body { 
                font-family: 'Times New Roman', serif; 
                font-size: 12pt; 
                line-height: 1.6; 
                margin: 0; 
                color: #333;
            }
            h1 { font-size: 18pt; margin: 24pt 0 12pt 0; }
            h2 { font-size: 16pt; margin: 18pt 0 6pt 0; }
            h3 { font-size: 14pt; margin: 12pt 0 6pt 0; }
            p { margin: 0 0 12pt 0; text-align: justify; }
            ul, ol { margin: 12pt 0 12pt 24pt; }
            li { margin: 3pt 0; }
            table { margin: 12pt 0; border-collapse: collapse; width: 100%; }
            table td, table th { border: 1pt solid #000; padding: 6pt; }
            img { max-width: 100%; height: auto; }
            {$css}
        </style>
    </head>
    <body>
        {$header}
        <div class='document-content'>
            {$documento['contenuto']}
        </div>
        {$footer}
    </body>
    </html>";
}
?>