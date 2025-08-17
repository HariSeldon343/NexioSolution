<?php
/**
 * Document Editor - Editor di documenti online con TinyMCE
 * Supporta conversione DOCX, collaborazione real-time e versionamento
 */

require_once 'backend/middleware/Auth.php';
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    header('Location: filesystem.php');
    exit;
}

$userId = $auth->getUser()['id'];
$userName = $auth->getUser()['nome'] . ' ' . $auth->getUser()['cognome'];
$aziendaId = $auth->getCurrentAzienda()['id'] ?? null;
$isSuperAdmin = $auth->isSuperAdmin();

// Verifica esistenza e permessi documento
$stmt = db_query(
    "SELECT d.*, 
            d.titolo as nome,
            d.file_path as percorso_file,
            d.dimensione_file as dimensione,
            (SELECT contenuto_html FROM document_versions 
             WHERE document_id = d.id 
             ORDER BY version_number DESC LIMIT 1) as ultimo_contenuto,
            (SELECT MAX(version_number) FROM document_versions 
             WHERE document_id = d.id) as ultima_versione
     FROM documenti d 
     WHERE d.id = ? AND (d.azienda_id = ? OR d.azienda_id IS NULL OR ?)",
    [$documentId, $aziendaId, $isSuperAdmin ? 1 : 0]
);

$document = $stmt->fetch();
if (!$document) {
    die("Documento non trovato o accesso negato");
}

// Verifica permessi di modifica
$canEdit = true; // Per ora tutti possono modificare, implementare logica permessi se necessario

// Se il documento è un DOCX, carica il contenuto
$documentContent = '';
$extractedHeader = '';
$extractedFooter = '';

if (!empty($document['ultimo_contenuto'])) {
    $documentContent = $document['ultimo_contenuto'];
} elseif (!empty($document['percorso_file']) && file_exists('uploads/documenti/' . $document['percorso_file'])) {
    // Tenta di convertire DOCX in HTML
    require_once 'vendor/autoload.php';
    try {
        $filePath = 'uploads/documenti/' . $document['percorso_file'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'docx') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            
            // Try to extract header and footer from first section
            $sections = $phpWord->getSections();
            if (!empty($sections)) {
                $firstSection = $sections[0];
                
                // Extract header text if exists
                $headers = $firstSection->getHeaders();
                if (!empty($headers)) {
                    foreach ($headers as $header) {
                        $elements = $header->getElements();
                        foreach ($elements as $element) {
                            if (method_exists($element, 'getText')) {
                                $extractedHeader .= $element->getText() . ' ';
                            }
                        }
                    }
                }
                
                // Extract footer text if exists
                $footers = $firstSection->getFooters();
                if (!empty($footers)) {
                    foreach ($footers as $footer) {
                        $elements = $footer->getElements();
                        foreach ($elements as $element) {
                            if (method_exists($element, 'getText')) {
                                $extractedFooter .= $element->getText() . ' ';
                            }
                        }
                    }
                }
            }
            
            // Convert to HTML
            $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
            
            ob_start();
            $htmlWriter->save('php://output');
            $html = ob_get_clean();
            
            // Estrai solo il contenuto del body
            if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $matches)) {
                $documentContent = $matches[1];
            } else {
                $documentContent = $html;
            }
        }
    } catch (Exception $e) {
        error_log("Errore conversione DOCX: " . $e->getMessage());
        $documentContent = '<p>Impossibile caricare il contenuto del documento</p>';
    }
}

$pageTitle = "Editor - " . htmlspecialchars($document['nome']);
if (!defined('APP_PATH')) {
    define('APP_PATH', '/piattaforma-collaborativa');
}

// Genera CSRF token se non esiste
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title><?php echo $pageTitle; ?> - Nexio Platform</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- TinyMCE Self-Hosted -->
    <script src="assets/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
    
    <!-- Custom styles -->
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .editor-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .editor-container {
            display: flex;
            height: calc(100vh - 70px);
            background: white;
        }
        
        .editor-main {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .editor-sidebar {
            width: 320px;
            background: #f8f9fa;
            border-left: 1px solid #dee2e6;
            overflow-y: auto;
        }
        
        .sidebar-section {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .sidebar-section h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .document-info {
            font-size: 0.875rem;
        }
        
        .document-info dt {
            color: #6c757d;
            font-weight: normal;
            margin-bottom: 4px;
        }
        
        .document-info dd {
            color: #212529;
            margin-bottom: 12px;
        }
        
        .version-item {
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .version-item:hover {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .version-item.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .version-number {
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .version-date {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .version-item.active .version-date {
            color: rgba(255,255,255,0.8);
        }
        
        .active-users {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .active-user {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            position: relative;
        }
        
        .active-user.online::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: #28a745;
            border: 2px solid white;
            border-radius: 50%;
        }
        
        .save-indicator {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 12px 24px;
            background: #28a745;
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .toolbar-section {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .toolbar-divider {
            width: 1px;
            height: 24px;
            background: #dee2e6;
            margin: 0 5px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-badge.editing {
            background: #d1f4d1;
            color: #0f5132;
        }
        
        .status-badge.readonly {
            background: #fff3cd;
            color: #664d03;
        }
        
        .tox-tinymce {
            border: 1px solid #dee2e6 !important;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Header con toolbar -->
    <div class="editor-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="toolbar-section">
                <button class="btn btn-light" onclick="window.location.href='filesystem.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                
                <div class="toolbar-divider"></div>
                
                <h5 class="mb-0"><?php echo htmlspecialchars($document['nome']); ?></h5>
            </div>
            
            <div class="toolbar-section">
                <?php if ($canEdit): ?>
                    <span class="status-badge editing">
                        <i class="fas fa-circle" style="font-size: 8px;"></i>
                        Modalità modifica
                    </span>
                <?php else: ?>
                    <span class="status-badge readonly">
                        <i class="fas fa-lock"></i>
                        Solo lettura
                    </span>
                <?php endif; ?>
                
                <div class="toolbar-divider"></div>
                
                <button class="btn btn-outline-secondary" onclick="insertTOC()">
                    <i class="fas fa-list-ol"></i> Inserisci TOC
                </button>
                
                <button class="btn btn-outline-secondary" onclick="printDocument()">
                    <i class="fas fa-print"></i> Stampa
                </button>
                
                <button class="btn btn-primary" onclick="saveDocument(true)" id="saveBtn">
                    <i class="fas fa-save"></i> Salva
                </button>
            </div>
        </div>
    </div>
    
    <!-- Container principale -->
    <div class="editor-container">
        <!-- Area editor -->
        <div class="editor-main">
            <textarea id="document-editor"><?php echo htmlspecialchars($documentContent); ?></textarea>
        </div>
        
        <!-- Sidebar -->
        <div class="editor-sidebar">
            <!-- Informazioni documento -->
            <div class="sidebar-section">
                <h6>Informazioni Documento</h6>
                <dl class="document-info">
                    <dt>Nome file:</dt>
                    <dd><?php echo htmlspecialchars($document['nome']); ?></dd>
                    
                    <dt>Ultima modifica:</dt>
                    <dd><?php echo date('d/m/Y H:i', strtotime($document['data_modifica'] ?? $document['data_creazione'])); ?></dd>
                    
                    <dt>Versione corrente:</dt>
                    <dd>v<?php echo $document['ultima_versione'] ?? 1; ?></dd>
                    
                    <dt>Dimensione:</dt>
                    <dd><?php echo number_format($document['dimensione'] / 1024, 2); ?> KB</dd>
                </dl>
            </div>
            
            <!-- Utenti attivi -->
            <div class="sidebar-section">
                <h6>Collaboratori Attivi</h6>
                <div class="active-users" id="activeUsers">
                    <div class="active-user online" title="<?php echo htmlspecialchars($userName); ?>">
                        <?php echo strtoupper(substr($auth->getUser()['nome'], 0, 1) . substr($auth->getUser()['cognome'], 0, 1)); ?>
                    </div>
                </div>
            </div>
            
            <!-- Page Layout Settings -->
            <div class="sidebar-section">
                <h6>
                    <button class="btn btn-sm btn-link text-decoration-none p-0 text-start w-100" type="button" data-bs-toggle="collapse" data-bs-target="#pageLayoutSettings">
                        <i class="fas fa-chevron-down me-1" id="pageLayoutIcon"></i> Impostazioni Layout Pagina
                    </button>
                </h6>
                <div class="collapse show" id="pageLayoutSettings">
                    <div class="mt-3">
                        <!-- Header Text -->
                        <div class="mb-3">
                            <label for="headerText" class="form-label small">Intestazione (Header)</label>
                            <input type="text" class="form-control form-control-sm" id="headerText" 
                                   placeholder="Testo intestazione..." maxlength="200">
                        </div>
                        
                        <!-- Footer Text -->
                        <div class="mb-3">
                            <label for="footerText" class="form-label small">Piè di pagina (Footer)</label>
                            <input type="text" class="form-control form-control-sm" id="footerText" 
                                   placeholder="Testo piè di pagina..." maxlength="200">
                        </div>
                        
                        <!-- Page Numbering -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enablePageNumbers">
                                <label class="form-check-label small" for="enablePageNumbers">
                                    Abilita numerazione pagine
                                </label>
                            </div>
                        </div>
                        
                        <!-- Page Number Format -->
                        <div class="mb-3" id="pageNumberFormatGroup" style="display: none;">
                            <label for="pageNumberFormat" class="form-label small">Formato numero pagina</label>
                            <select class="form-select form-select-sm" id="pageNumberFormat">
                                <option value="Page {PAGE}">Pagina X</option>
                                <option value="Page {PAGE} of {NUMPAGES}">Pagina X di Y</option>
                                <option value="{PAGE} / {NUMPAGES}">X / Y</option>
                                <option value="- {PAGE} -">- X -</option>
                            </select>
                        </div>
                        
                        <!-- Apply Settings Button -->
                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-primary" onclick="applyPageSettings()">
                                <i class="fas fa-check"></i> Applica Impostazioni
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportWithSettings('docx')">
                                <i class="fas fa-file-word"></i> Esporta DOCX
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportWithSettings('pdf')">
                                <i class="fas fa-file-pdf"></i> Esporta PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cronologia versioni -->
            <div class="sidebar-section">
                <h6>Cronologia Versioni</h6>
                <div id="versionHistory">
                    <div class="text-muted text-center py-3">
                        <i class="fas fa-spinner fa-spin"></i> Caricamento...
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Indicatore di salvataggio -->
    <div class="save-indicator" id="saveIndicator">
        <i class="fas fa-check-circle"></i>
        <span>Documento salvato</span>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Configurazione
    const DOCUMENT_ID = <?php echo $documentId; ?>;
    const USER_ID = <?php echo $userId; ?>;
    const USER_NAME = '<?php echo addslashes($userName); ?>';
    const CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
    const WS_URL = 'ws://localhost:8080'; // WebSocket server URL
    const WS_ENABLED = false; // Set to true when WebSocket server is running
    
    let editor;
    let wsConnection;
    let autoSaveTimer;
    let lastSavedContent = '';
    let activeUsers = new Map();
    let isTyping = false;
    let typingTimer;
    let wsReconnectAttempts = 0;
    const WS_MAX_RECONNECT_ATTEMPTS = 3;
    
    // Inizializzazione TinyMCE
    tinymce.init({
        selector: '#document-editor',
        license_key: '4jharm4wbljffqkf1cmbbehx5nzacqseuqlmsjoyre65ikvr',
        base_url: '/piattaforma-collaborativa/assets/vendor/tinymce/js/tinymce',
        suffix: '.min',
        height: '100%',
        readonly: !CAN_EDIT,
        
        // Plugin open-source only
        plugins: [
            'anchor', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime',
            'media', 'table', 'help', 'wordcount', 'pagebreak', 'autosave',
            'codesample', 'directionality', 'emoticons', 'importcss',
            'nonbreaking', 'quickbars', 'save', 'searchreplace', 'visualchars'
        ],
        
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | table | insertTOC | ' +
                'pagebreak | removeformat | code fullscreen | help',
                
        menubar: 'file edit view insert format table tools help',
        
        block_formats: 'Paragrafo=p; Titolo 1=h1; Titolo 2=h2; Titolo 3=h3; Titolo 4=h4; Titolo 5=h5; Titolo 6=h6',
        
        content_style: `
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                font-size: 14px; 
                line-height: 1.6;
                color: #333;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            h1, h2, h3, h4, h5, h6 { 
                color: #2c3e50; 
                margin-top: 1.5em;
                margin-bottom: 0.5em;
            }
            table { 
                border-collapse: collapse; 
                width: 100%;
                margin: 1em 0;
            }
            table td, table th { 
                border: 1px solid #ddd; 
                padding: 8px 12px;
            }
            table th {
                background: #f5f5f5;
                font-weight: 600;
            }
        `,
        
        // Configurazione tabelle
        table_default_attributes: {
            border: '1'
        },
        table_default_styles: {
            'border-collapse': 'collapse',
            'width': '100%'
        },
        table_class_list: [
            {title: 'Nessuna', value: ''},
            {title: 'Tabella striped', value: 'table-striped'},
            {title: 'Tabella bordata', value: 'table-bordered'}
        ],
        
        // Auto-save
        autosave_interval: '30s',
        autosave_retention: '30m',
        autosave_restore_when_empty: true,
        
        // Pagebreak plugin
        pagebreak_separator: '<div style="page-break-after: always;"></div>',
        
        // Image upload
        images_upload_url: 'backend/api/upload-image.php',
        automatic_uploads: true,
        images_reuse_filename: true,
        
        // Custom toolbar button per TOC
        toolbar_mode: 'sliding',
        
        setup: function(ed) {
            editor = ed;
            
            // Aggiungi comando custom per inserire TOC placeholder
            ed.ui.registry.addButton('insertTOC', {
                text: 'TOC',
                tooltip: 'Inserisci indice (Table of Contents)',
                onAction: function() {
                    ed.insertContent('<p>[[TOC]]</p>');
                    showNotification('TOC placeholder inserito. Verrà generato l\'indice nell\'export.', 'info');
                }
            });
            
            // Gestione eventi per collaborazione real-time
            if (CAN_EDIT) {
                let changeTimer;
                
                ed.on('input', function(e) {
                    clearTimeout(changeTimer);
                    changeTimer = setTimeout(() => {
                        broadcastChange();
                    }, 500); // Throttling a 500ms
                });
                
                ed.on('change', function() {
                    scheduleAutoSave();
                });
            }
            
            ed.on('init', function() {
                lastSavedContent = ed.getContent();
                initWebSocket();
                loadVersionHistory();
            });
        }
    });
    
    // Inizializzazione WebSocket
    function initWebSocket() {
        if (!WS_ENABLED) {
            console.log('WebSocket disabilitato, modalità offline');
            return;
        }
        
        if (wsReconnectAttempts >= WS_MAX_RECONNECT_ATTEMPTS) {
            console.log('Raggiunto limite tentativi WebSocket, modalità offline');
            return;
        }
        
        try {
            wsConnection = new WebSocket(WS_URL);
            
            wsConnection.onopen = function() {
                console.log('WebSocket connesso');
                wsReconnectAttempts = 0; // Reset counter on successful connection
                // Registra l'utente come editor attivo
                wsConnection.send(JSON.stringify({
                    type: 'register_editor',
                    document_id: DOCUMENT_ID,
                    user_id: USER_ID,
                    user_name: USER_NAME
                }));
            };
            
            wsConnection.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    handleWebSocketMessage(data);
                } catch (e) {
                    console.error('Errore parsing messaggio WebSocket:', e);
                }
            };
            
            wsConnection.onerror = function(error) {
                // Silently handle error if under max attempts
                if (wsReconnectAttempts < WS_MAX_RECONNECT_ATTEMPTS) {
                    wsReconnectAttempts++;
                }
            };
            
            wsConnection.onclose = function() {
                if (wsReconnectAttempts < WS_MAX_RECONNECT_ATTEMPTS) {
                    console.log(`WebSocket disconnesso, tentativo ${wsReconnectAttempts + 1}/${WS_MAX_RECONNECT_ATTEMPTS}...`);
                    wsReconnectAttempts++;
                    setTimeout(initWebSocket, 5000);
                }
            };
        } catch (e) {
            console.log('WebSocket non disponibile, modalità offline');
        }
    }
    
    // Gestione messaggi WebSocket
    function handleWebSocketMessage(data) {
        switch(data.type) {
            case 'user_joined':
                addActiveUser(data.user);
                break;
                
            case 'user_left':
                removeActiveUser(data.user_id);
                break;
                
            case 'document_change':
                if (data.user_id !== USER_ID && data.document_id === DOCUMENT_ID) {
                    applyRemoteChange(data.content);
                }
                break;
                
            case 'document_saved':
                if (data.document_id === DOCUMENT_ID) {
                    showNotification(data.user_name + ' ha salvato il documento');
                    loadVersionHistory();
                }
                break;
        }
    }
    
    // Broadcast modifiche locali
    function broadcastChange() {
        if (wsConnection && wsConnection.readyState === WebSocket.OPEN && CAN_EDIT) {
            wsConnection.send(JSON.stringify({
                type: 'document_change',
                document_id: DOCUMENT_ID,
                user_id: USER_ID,
                content: editor.getContent()
            }));
        }
    }
    
    // Applica modifiche remote
    function applyRemoteChange(content) {
        if (editor && !isTyping) {
            const bookmark = editor.selection.getBookmark();
            editor.setContent(content);
            editor.selection.moveToBookmark(bookmark);
        }
    }
    
    // Auto-save
    function scheduleAutoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            saveDocument(false);
        }, 5000); // Salva dopo 5 secondi di inattività
    }
    
    // Salvataggio documento
    async function saveDocument(isManual = false) {
        if (!CAN_EDIT) return;
        
        const content = editor.getContent();
        
        // Se non ci sono modifiche e non è un salvataggio manuale, esci
        if (content === lastSavedContent && !isManual) {
            return;
        }
        
        // Mostra indicatore di salvataggio
        const saveBtn = document.getElementById('saveBtn');
        const originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvataggio...';
        saveBtn.disabled = true;
        
        try {
            const response = await fetch('backend/api/save-advanced-document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    docId: DOCUMENT_ID,
                    content: content,
                    plainText: editor.getContent({format: 'text'}),
                    title: document.querySelector('h5.mb-0')?.textContent || 'Documento',
                    stats: {
                        wordCount: editor.plugins.wordcount.getCount(),
                        charCount: editor.getContent({format: 'text'}).length
                    },
                    settings: {
                        is_major_version: isManual
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                lastSavedContent = content;
                showSaveIndicator();
                
                // Broadcast evento di salvataggio
                if (wsConnection && wsConnection.readyState === WebSocket.OPEN) {
                    wsConnection.send(JSON.stringify({
                        type: 'document_saved',
                        document_id: DOCUMENT_ID,
                        user_name: USER_NAME
                    }));
                }
                
                // Ricarica cronologia versioni
                loadVersionHistory();
            } else {
                alert('Errore nel salvataggio: ' + (data.error || 'Errore sconosciuto'));
            }
        } catch (error) {
            console.error('Errore salvataggio:', error);
            alert('Errore nel salvataggio del documento');
        } finally {
            saveBtn.innerHTML = originalHtml;
            saveBtn.disabled = false;
        }
    }
    
    // TOC gestito automaticamente dal plugin tableofcontents
    // Il plugin aggiunge automaticamente il menu Insert > Table of contents
    
    // Stampa documento
    function printDocument() {
        const content = editor.getContent();
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title><?php echo addslashes($document['nome']); ?></title>
                <style>
                    body { 
                        font-family: 'Times New Roman', serif; 
                        margin: 2cm;
                        line-height: 1.6;
                    }
                    h1, h2, h3, h4, h5, h6 { 
                        page-break-after: avoid;
                        margin-top: 1em;
                        margin-bottom: 0.5em;
                    }
                    table { 
                        border-collapse: collapse; 
                        width: 100%;
                        page-break-inside: avoid;
                    }
                    td, th { 
                        border: 1px solid #000; 
                        padding: 8px;
                    }
                    @page {
                        margin: 2cm;
                    }
                    @media print {
                        .pagebreak { page-break-after: always; }
                    }
                </style>
            </head>
            <body>
                ${content}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
        
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
    
    // Carica cronologia versioni
    async function loadVersionHistory() {
        try {
            const response = await fetch(`backend/api/document-versions-api.php?action=list&document_id=${DOCUMENT_ID}`);
            const data = await response.json();
            
            if (data.success && data.versions) {
                displayVersionHistory(data.versions);
            }
        } catch (error) {
            console.error('Errore caricamento versioni:', error);
        }
    }
    
    // Mostra cronologia versioni
    function displayVersionHistory(versions) {
        const container = document.getElementById('versionHistory');
        container.innerHTML = '';
        
        if (!versions || versions.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">Nessuna versione disponibile</div>';
            return;
        }
        
        versions.forEach((version, index) => {
            const versionDiv = document.createElement('div');
            versionDiv.className = 'version-item' + (index === 0 ? ' active' : '');
            versionDiv.innerHTML = `
                <div class="version-number">Versione ${version.version_number || index + 1}</div>
                <div class="version-date">${formatDate(version.created_at)}</div>
                <div class="version-date">di ${version.created_by_name || 'Sistema'}</div>
            `;
            
            versionDiv.onclick = () => loadVersion(version.id);
            container.appendChild(versionDiv);
        });
    }
    
    // Carica una versione specifica
    async function loadVersion(versionId) {
        if (!confirm('Vuoi caricare questa versione? Le modifiche non salvate andranno perse.')) {
            return;
        }
        
        try {
            const response = await fetch(`backend/api/document-versions-api.php?action=get&version_id=${versionId}`);
            const data = await response.json();
            
            if (data.success && data.content) {
                editor.setContent(data.content);
                showNotification('Versione caricata con successo');
                
                // Aggiorna la cronologia
                loadVersionHistory();
            }
        } catch (error) {
            console.error('Errore caricamento versione:', error);
            alert('Errore nel caricamento della versione');
        }
    }
    
    // Aggiungi utente attivo
    function addActiveUser(user) {
        if (!activeUsers.has(user.id)) {
            activeUsers.set(user.id, user);
            updateActiveUsersDisplay();
        }
    }
    
    // Rimuovi utente attivo
    function removeActiveUser(userId) {
        if (activeUsers.delete(userId)) {
            updateActiveUsersDisplay();
        }
    }
    
    // Aggiorna visualizzazione utenti attivi
    function updateActiveUsersDisplay() {
        const container = document.getElementById('activeUsers');
        container.innerHTML = '';
        
        // Aggiungi sempre l'utente corrente
        const currentUser = document.createElement('div');
        currentUser.className = 'active-user online';
        currentUser.title = USER_NAME;
        currentUser.textContent = USER_NAME.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        container.appendChild(currentUser);
        
        // Aggiungi altri utenti attivi
        activeUsers.forEach(user => {
            const userDiv = document.createElement('div');
            userDiv.className = 'active-user online';
            userDiv.title = user.name;
            userDiv.textContent = user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            container.appendChild(userDiv);
        });
    }
    
    // Mostra indicatore di salvataggio
    function showSaveIndicator() {
        const indicator = document.getElementById('saveIndicator');
        indicator.style.display = 'flex';
        
        setTimeout(() => {
            indicator.style.display = 'none';
        }, 3000);
    }
    
    // Mostra notifica
    function showNotification(message) {
        // Potresti implementare un sistema di notifiche più sofisticato
        console.log('Notifica:', message);
    }
    
    // Formatta data
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) { // Meno di 1 minuto
            return 'Adesso';
        } else if (diff < 3600000) { // Meno di 1 ora
            const minutes = Math.floor(diff / 60000);
            return `${minutes} minut${minutes === 1 ? 'o' : 'i'} fa`;
        } else if (diff < 86400000) { // Meno di 1 giorno
            const hours = Math.floor(diff / 3600000);
            return `${hours} or${hours === 1 ? 'a' : 'e'} fa`;
        } else {
            return date.toLocaleDateString('it-IT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
    
    // Gestione uscita dalla pagina
    window.addEventListener('beforeunload', function(e) {
        if (editor && editor.isDirty() && CAN_EDIT) {
            e.preventDefault();
            e.returnValue = 'Ci sono modifiche non salvate. Vuoi davvero uscire?';
        }
        
        // Chiudi connessione WebSocket
        if (wsConnection) {
            wsConnection.close();
        }
    });
    
    // Traccia quando l'utente sta digitando
    document.addEventListener('keydown', () => {
        isTyping = true;
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            isTyping = false;
        }, 1000);
    });
    
    // ===== PAGE LAYOUT SETTINGS FUNCTIONS =====
    
    // Variables to store page settings
    let pageSettings = {
        headerText: '<?php echo addslashes(trim($extractedHeader)); ?>',
        footerText: '<?php echo addslashes(trim($extractedFooter)); ?>',
        pageNumbering: false,
        pageNumberFormat: 'Page {PAGE}'
    };
    
    // Load saved page settings from document metadata
    async function loadPageSettings() {
        try {
            const response = await fetch(`backend/api/get-document.php?id=${DOCUMENT_ID}`);
            const data = await response.json();
            
            if (data.success && data.documento && data.documento.metadata) {
                const metadata = JSON.parse(data.documento.metadata || '{}');
                if (metadata.header_text !== undefined) {
                    pageSettings.headerText = metadata.header_text;
                    document.getElementById('headerText').value = metadata.header_text;
                }
                if (metadata.footer_text !== undefined) {
                    pageSettings.footerText = metadata.footer_text;
                    document.getElementById('footerText').value = metadata.footer_text;
                }
                if (metadata.page_numbering !== undefined) {
                    pageSettings.pageNumbering = metadata.page_numbering;
                    document.getElementById('enablePageNumbers').checked = metadata.page_numbering;
                    togglePageNumberFormat();
                }
                if (metadata.page_number_format !== undefined) {
                    pageSettings.pageNumberFormat = metadata.page_number_format;
                    document.getElementById('pageNumberFormat').value = metadata.page_number_format;
                }
            }
        } catch (error) {
            console.error('Error loading page settings:', error);
        }
    }
    
    // Toggle page number format dropdown visibility
    function togglePageNumberFormat() {
        const formatGroup = document.getElementById('pageNumberFormatGroup');
        const checkbox = document.getElementById('enablePageNumbers');
        formatGroup.style.display = checkbox.checked ? 'block' : 'none';
    }
    
    // Apply page settings (save to document metadata)
    async function applyPageSettings() {
        if (!CAN_EDIT) {
            alert('Non hai i permessi per modificare questo documento');
            return;
        }
        
        // Get current values
        pageSettings.headerText = document.getElementById('headerText').value;
        pageSettings.footerText = document.getElementById('footerText').value;
        pageSettings.pageNumbering = document.getElementById('enablePageNumbers').checked;
        pageSettings.pageNumberFormat = document.getElementById('pageNumberFormat').value;
        
        // Save with document
        try {
            const response = await fetch('backend/api/save-advanced-document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    docId: DOCUMENT_ID,
                    content: editor.getContent(),
                    plainText: editor.getContent({format: 'text'}),
                    title: document.querySelector('h5.mb-0')?.textContent || 'Documento',
                    header_text: pageSettings.headerText,
                    footer_text: pageSettings.footerText,
                    page_numbering: pageSettings.pageNumbering,
                    page_number_format: pageSettings.pageNumberFormat,
                    stats: {
                        wordCount: editor.plugins.wordcount ? editor.plugins.wordcount.getCount() : 0,
                        charCount: editor.getContent({format: 'text'}).length
                    },
                    settings: {
                        is_major_version: false
                    }
                })
            });
            
            let data;
            // Better error handling for non-JSON responses
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                data = await response.json();
            } else {
                // Response is not JSON, probably PHP error
                const errorText = await response.text();
                console.error('Server returned non-JSON response:', errorText);
                alert('Errore del server. Controlla la console per dettagli.');
                return;
            }
            
            if (data && data.success) {
                showNotification('Impostazioni di layout salvate');
                showSaveIndicator();
            } else {
                alert('Errore nel salvataggio delle impostazioni: ' + (data?.error || 'Errore sconosciuto'));
            }
        } catch (error) {
            console.error('Error saving page settings:', error);
            alert('Errore nel salvataggio delle impostazioni');
        }
    }
    
    // Export document with header/footer settings
    async function exportWithSettings(format) {
        if (!['docx', 'pdf', 'html'].includes(format)) {
            alert('Formato non supportato');
            return;
        }
        
        try {
            // First save the current settings
            await applyPageSettings();
            
            // Get page settings
            const headerText = document.getElementById('header-text').value;
            const footerText = document.getElementById('footer-text').value;
            const pageNumbering = document.getElementById('page-numbering').checked;
            const pageNumberFormat = document.getElementById('page-number-format').value;
            
            // Prepare export request
            const formData = new FormData();
            formData.append('action', 'export');
            formData.append('documento_id', DOCUMENT_ID);
            formData.append('format', format);
            formData.append('header_text', headerText);
            formData.append('footer_text', footerText);
            formData.append('page_numbering', pageNumbering);
            formData.append('page_number_format', pageNumberFormat);
            
            // Call export API
            const response = await fetch('backend/api/document-editor.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.export && result.export.download_url) {
                // Open download URL
                window.open(result.export.download_url, '_blank');
            } else {
                alert(result.error || 'Errore durante l\'esportazione');
            }
            
        } catch (error) {
            console.error('Export error:', error);
            alert('Errore durante l\'esportazione: ' + error.message);
        }
    }
    
    // Function to insert TOC placeholder
    function insertTOC() {
        if (editor) {
            editor.insertContent('<p>[[TOC]]</p>');
            showNotification('TOC placeholder inserito. Verrà generato l\'indice nell\'export.', 'info');
        }
    }
    
    // Function to generate TOC from headings
    function generateTOCFromContent(content) {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = content;
        
        const headings = tempDiv.querySelectorAll('h1, h2, h3, h4, h5, h6');
        if (headings.length === 0) return '';
        
        let tocHTML = '<div class="toc-container" style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; background: #f9f9f9;">';
        tocHTML += '<h2 style="margin-top: 0;">Indice</h2>';
        tocHTML += '<ol style="margin: 0; padding-left: 20px;">';
        
        headings.forEach((heading, index) => {
            const level = parseInt(heading.tagName.substring(1));
            const text = heading.textContent;
            const indent = (level - 1) * 20;
            
            tocHTML += `<li style="margin-left: ${indent}px; list-style-type: ${level === 1 ? 'decimal' : level === 2 ? 'lower-alpha' : 'lower-roman'};">`;
            tocHTML += `<a href="#heading-${index}" style="text-decoration: none; color: #333;">${text}</a>`;
            tocHTML += '</li>';
            
            // Add ID to heading for linking
            heading.id = `heading-${index}`;
        });
        
        tocHTML += '</ol></div>';
        return tocHTML;
    }
    
    // Initialize page settings on load
    document.addEventListener('DOMContentLoaded', function() {
        // Load saved settings
        loadPageSettings();
        
        // Setup event listeners
        document.getElementById('enablePageNumbers').addEventListener('change', togglePageNumberFormat);
        
        // Toggle collapse icon
        document.getElementById('pageLayoutSettings').addEventListener('show.bs.collapse', function() {
            document.getElementById('pageLayoutIcon').className = 'fas fa-chevron-down me-1';
        });
        
        document.getElementById('pageLayoutSettings').addEventListener('hide.bs.collapse', function() {
            document.getElementById('pageLayoutIcon').className = 'fas fa-chevron-right me-1';
        });
    });
    </script>
</body>
</html>