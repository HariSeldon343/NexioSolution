<?php
// Versione MINIMA e FUNZIONANTE di filesystem.php
// Solo le funzionalità essenziali per testare OnlyOffice

require_once 'backend/middleware/Auth.php';
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$currentUser = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Query semplice per ottenere i documenti
$query = "
    SELECT 
        d.id,
        d.titolo,
        d.file_path,
        d.percorso_file,
        d.mime_type,
        d.dimensione_file,
        d.azienda_id
    FROM documenti d
    WHERE 1=1
";

$params = [];
if ($currentAzienda && !$auth->isSuperAdmin()) {
    $query .= " AND (d.azienda_id = ? OR d.azienda_id IS NULL)";
    $params[] = $currentAzienda;
}

$query .= " ORDER BY d.id DESC LIMIT 20";

$stmt = db_query($query, $params);
$documenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Funzione semplice per verificare se un documento è editabile
function isEditableDocument($mimeType) {
    $editableTypes = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint'
    ];
    return in_array($mimeType, $editableTypes);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filesystem Minimal - Test OnlyOffice</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
        }
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .document-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        .document-card:hover {
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .document-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            word-break: break-word;
        }
        .document-meta {
            font-size: 12px;
            color: #666;
            margin: 5px 0;
        }
        .document-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .btn-onlyoffice {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .btn-onlyoffice:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        .btn-download {
            background: #3498db;
            color: white;
        }
        .btn-download:hover {
            background: #2980b9;
        }
        .btn-disabled {
            background: #95a5a6;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .no-documents {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }
        .debug-info {
            background: #2c3e50;
            color: #4ec9b0;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📁 Filesystem Minimal - Test OnlyOffice</h1>
        
        <div class="info-box">
            <strong>ℹ️ Versione Minimal per Test</strong><br>
            Utente: <?php echo htmlspecialchars($currentUser['nome'] ?? 'N/A'); ?><br>
            Azienda: <?php echo htmlspecialchars($currentAzienda ?? 'Nessuna'); ?><br>
            Super Admin: <?php echo $auth->isSuperAdmin() ? 'Sì' : 'No'; ?><br>
            Documenti trovati: <?php echo count($documenti); ?>
        </div>

        <?php if (empty($documenti)): ?>
            <div class="no-documents">
                <h2>📭 Nessun documento trovato</h2>
                <p>Non ci sono documenti nel database.</p>
            </div>
        <?php else: ?>
            <div class="documents-grid">
                <?php foreach ($documenti as $doc): ?>
                    <?php 
                    $isEditable = isEditableDocument($doc['mime_type']);
                    $filePath = $doc['file_path'] ?: $doc['percorso_file'];
                    ?>
                    <div class="document-card">
                        <div class="document-title">
                            📄 <?php echo htmlspecialchars($doc['titolo']); ?>
                        </div>
                        <div class="document-meta">
                            ID: <?php echo $doc['id']; ?><br>
                            MIME: <?php echo htmlspecialchars($doc['mime_type'] ?? 'N/A'); ?><br>
                            Path: <?php echo htmlspecialchars($filePath ?? 'N/A'); ?><br>
                            Dimensione: <?php echo number_format($doc['dimensione_file'] / 1024, 2); ?> KB
                        </div>
                        
                        <div class="document-actions">
                            <?php if ($isEditable): ?>
                                <!-- LINK DIRETTO SEMPLICE -->
                                <a href="/piattaforma-collaborativa/onlyoffice-editor.php?id=<?php echo $doc['id']; ?>" 
                                   target="_blank"
                                   class="btn btn-onlyoffice">
                                    ✏️ OnlyOffice
                                </a>
                            <?php else: ?>
                                <span class="btn btn-disabled">
                                    ❌ Non editabile
                                </span>
                            <?php endif; ?>
                            
                            <a href="/piattaforma-collaborativa/backend/api/simple-download.php?id=<?php echo $doc['id']; ?>"
                               class="btn btn-download">
                                ⬇️ Download
                            </a>
                        </div>
                        
                        <div class="debug-info">
                            Editable: <?php echo $isEditable ? 'YES' : 'NO'; ?><br>
                            OnlyOffice URL: /piattaforma-collaborativa/onlyoffice-editor.php?id=<?php echo $doc['id']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 40px; padding: 20px; background: #ecf0f1; border-radius: 10px;">
            <h3>🧪 Test Links Diretti</h3>
            <p>Prova questi link per testare direttamente l'editor OnlyOffice:</p>
            <ul>
                <li><a href="/piattaforma-collaborativa/test-onlyoffice-direct.php?id=25" target="_blank">Test OnlyOffice Direct (ID 25)</a></li>
                <li><a href="/piattaforma-collaborativa/onlyoffice-editor.php?id=22" target="_blank">OnlyOffice Editor (ID 22)</a></li>
                <li><a href="/piattaforma-collaborativa/onlyoffice-editor.php" target="_blank">OnlyOffice Editor (senza ID)</a></li>
            </ul>
        </div>
    </div>

    <script>
        // Nessun JavaScript complesso - solo logging per debug
        console.log('Filesystem Minimal loaded');
        console.log('Documents found:', <?php echo count($documenti); ?>);
        
        // Log quando si clicca su un link OnlyOffice
        document.querySelectorAll('.btn-onlyoffice').forEach(btn => {
            btn.addEventListener('click', function(e) {
                console.log('OnlyOffice button clicked:', this.href);
                // NON preveniamo il default - lasciamo che il link funzioni normalmente
            });
        });
    </script>
</body>
</html>