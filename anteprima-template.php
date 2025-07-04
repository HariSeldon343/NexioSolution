<?php
/**
 * Anteprima Template - Mostra come apparirà il template applicato
 */

require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/models/Template.php';

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

$template_id = $_GET['id'] ?? null;
if (!$template_id) {
    die('ID template non specificato');
}

// Connessione database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Errore connessione database: ' . $e->getMessage());
}

$template = new Template($pdo);
$templateData = $template->getById($template_id);

if (!$templateData) {
    die('Template non trovato');
}

// Dati di esempio per l'anteprima
$documentData = [
    'titolo' => 'Documento di Esempio',
    'codice' => 'DOC_' . date('Ymd_His') . '_' . $user['id'],
    'versione' => '1.0',
    'data_revisione' => date('d/m/Y'),
    'logo_azienda' => '/assets/images/logo-example.png'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anteprima Template - <?= htmlspecialchars($templateData['nome']) ?></title>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .preview-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .preview-header {
            background: #0d7377;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .preview-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .template-info {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .document-preview {
            padding: 40px;
        }
        
        .document-header,
        .document-footer {
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
            background: #fafafa;
        }
        
        .document-content {
            padding: 40px 20px;
            text-align: center;
            border: 2px dashed #dee2e6;
            color: #6c757d;
            font-style: italic;
            margin: 20px 0;
        }
        
        /* Stili del template */
        <?= $templateData['stili_css'] ?>
        <?= $template->generateCSS($template_id) ?>
        
        .actions {
            padding: 20px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            background: #0d7377;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 5px;
            transition: background 0.2s ease;
        }
        
        .btn:hover {
            background: #0a5d61;
        }
        
        .btn.secondary {
            background: #6c757d;
        }
        
        .btn.secondary:hover {
            background: #545b62;
        }
        
        @media print {
            .preview-header,
            .template-info,
            .actions {
                display: none;
            }
            
            .preview-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .document-preview {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-header">
            <h1>Anteprima Template</h1>
            <p><?= htmlspecialchars($templateData['nome']) ?></p>
        </div>
        
        <div class="template-info">
            <div>
                <strong>Template:</strong> <?= htmlspecialchars($templateData['nome']) ?><br>
                <small><?= htmlspecialchars($templateData['descrizione'] ?? '') ?></small>
            </div>
            <div>
                <small>Creato: <?= date('d/m/Y', strtotime($templateData['data_creazione'])) ?></small>
            </div>
        </div>
        
        <div class="document-preview">
            <!-- Intestazione -->
            <div class="document-header">
                <?= $template->generateHeader($template_id, $documentData) ?>
            </div>
            
            <!-- Contenuto documento -->
            <div class="document-content">
                <h2>Contenuto del Documento</h2>
                <p>Qui verrà visualizzato il contenuto principale del documento.</p>
                <p>L'intestazione e il piè di pagina verranno applicati automaticamente.</p>
                <br>
                <p><strong>Lorem ipsum dolor sit amet</strong>, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                <br>
                <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
            </div>
            
            <!-- Piè di pagina -->
            <div class="document-footer">
                <?= $template->generateFooter($template_id, $documentData) ?>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn" onclick="window.print()">
                <i class="fas fa-print"></i>
                Stampa Anteprima
            </button>
            <a href="gestione-template.php?action=edit&id=<?= $template_id ?>" class="btn secondary">
                <i class="fas fa-edit"></i>
                Modifica Template
            </a>
            <button class="btn secondary" onclick="window.close()">
                <i class="fas fa-times"></i>
                Chiudi
            </button>
        </div>
    </div>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>