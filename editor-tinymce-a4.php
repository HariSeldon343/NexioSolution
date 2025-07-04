<?php
/**
 * Editor TinyMCE 7 - Formato A4 Professionale
 */

require_once __DIR__ . '/backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

$documento_id = $_GET['id'] ?? null;
$documento = null;

if ($documento_id) {
    try {
        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documento_id]);
        if ($stmt) {
            $documento = $stmt->fetch();
        }
    } catch (Exception $e) {
        die('Errore caricamento documento: ' . $e->getMessage());
    }
}

$titolo = $documento ? $documento['titolo'] : 'Nuovo Documento';
$contenuto = $documento ? $documento['contenuto'] : '<p>Inizia a scrivere il tuo documento in formato A4...</p>';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titolo) ?> - Editor A4</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- TinyMCE 7 -->
    <script src="https://cdn.tiny.cloud/1/bhocezj3lxp7yvli73hunsrwjttflpmz8k3m4gbmxc8u937z/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: #0d7377;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header h1 { 
            font-size: 20px; 
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            background: white;
            color: #0d7377;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn.primary {
            background: #28a745;
            color: white;
        }
        
        .btn.primary:hover {
            background: #218838;
        }
        
        .btn.secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn.secondary:hover {
            background: #545b62;
        }
        
        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
            display: flex;
            justify-content: center;
            min-height: calc(100vh - 140px);
        }
        
        /* Document Container */
        .document-wrapper {
            position: relative;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .document-info {
            background: #f8f9fa;
            padding: 12px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #666;
        }
        
        .page-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* TinyMCE A4 Styling */
        .tox-tinymce {
            border: none !important;
            border-radius: 0 !important;
        }
        
        .tox-edit-area {
            border: none !important;
        }
        
        .tox-edit-area__iframe {
            background: white !important;
        }
        
        /* A4 Page Simulation */
        .a4-container {
            width: 794px; /* 21cm at 96 DPI */
            margin: 0 auto;
            background: white;
            position: relative;
        }
        
        .a4-page {
            width: 794px;
            min-height: 1123px; /* 29.7cm at 96 DPI */
            padding: 96px 72px; /* 2.5cm top/bottom, 1.9cm left/right */
            background: white;
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .a4-page::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: -5px;
            bottom: -5px;
            background: #f0f0f0;
            z-index: -1;
            border-radius: 2px;
        }
        
        /* Page Number */
        .page-number {
            position: absolute;
            bottom: 40px;
            right: 72px;
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            color: #666;
        }
        
        /* Zoom Controls */
        .zoom-controls {
            position: fixed;
            bottom: 80px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            z-index: 1001;
            border: 1px solid #e0e0e0;
        }
        
        .zoom-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: background 0.2s;
            color: #0d7377;
        }
        
        .zoom-btn:hover {
            background: #f8f9fa;
        }
        
        .zoom-btn:first-child {
            border-radius: 8px 0 0 8px;
            border-right: 1px solid #e0e0e0;
        }
        
        .zoom-btn:last-child {
            border-radius: 0 8px 8px 0;
            border-left: 1px solid #e0e0e0;
        }
        
        .zoom-level {
            padding: 0 16px;
            font-size: 13px;
            font-weight: 600;
            background: #0d7377;
            color: white;
            height: 36px;
            display: flex;
            align-items: center;
            min-width: 60px;
            justify-content: center;
        }
        
        /* Status Bar */
        .status-bar {
            background: white;
            border-top: 1px solid #dee2e6;
            padding: 10px 20px;
            font-size: 13px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        }
        
        .status-left,
        .status-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-success {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: 500;
        }
        
        .status-warning {
            color: #ffc107;
            font-weight: 500;
        }
        
        /* Loading Spinner */
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 860px) {
            .a4-container,
            .a4-page {
                width: 100% !important;
                max-width: calc(100vw - 40px) !important;
                margin: 0 auto !important;
            }
            
            .main-content {
                padding: 20px 10px;
            }
            
            .header h1 {
                font-size: 16px;
            }
            
            .header-buttons {
                gap: 5px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
        
        /* TinyMCE Custom Styles */
        .tox .tox-menubar {
            background: #f8f9fa !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .tox .tox-toolbar {
            background: #ffffff !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .tox .tox-statusbar {
            background: #f8f9fa !important;
            border-top: 1px solid #dee2e6 !important;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>
            <i class="fas fa-file-alt"></i>
            <?= htmlspecialchars($titolo) ?>
        </h1>
        <div class="header-buttons">
            <button class="btn primary" onclick="saveDocument()" id="saveBtn">
                <i class="fas fa-save"></i> Salva
            </button>
            <button class="btn secondary" onclick="insertPageBreak()">
                <i class="fas fa-file-plus"></i> Nuova Pagina
            </button>
            <button class="btn" onclick="printDocument()">
                <i class="fas fa-print"></i> Stampa
            </button>
            <button class="btn" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Esporta PDF
            </button>
            <a href="dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </header>
    
    <main class="main-content">
        <div class="document-wrapper">
            <div class="document-info">
                <div class="page-info">
                    <span style="background: #0d7377; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                        <i class="fas fa-file-alt"></i> <span id="pageInfo">Pagina 1 di 1</span>
                    </span>
                    <span><i class="fas fa-ruler"></i> A4 (210×297mm)</span>
                    <span><i class="fas fa-calculator"></i> <span id="wordCount">0 parole</span></span>
                </div>
                <div class="status-info">
                    <span id="documentStatus">Pronto</span>
                </div>
            </div>
            
            <div class="a4-container">
                <textarea id="tinymce-editor"><?= htmlspecialchars($contenuto) ?></textarea>
            </div>
        </div>
    </main>
    
    <!-- Zoom Controls -->
    <div class="zoom-controls">
        <div class="zoom-btn" onclick="zoomOut()" title="Riduci zoom">−</div>
        <div class="zoom-level" id="zoomLevel">100%</div>
        <div class="zoom-btn" onclick="zoomIn()" title="Aumenta zoom">+</div>
    </div>
    
    <!-- Status Bar -->
    <div class="status-bar">
        <div class="status-left">
            <div class="status-item">
                <i class="fas fa-user"></i>
                <span><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></span>
            </div>
            <div class="status-item">
                <i class="fas fa-clock"></i>
                <span id="lastSaved">Non salvato</span>
            </div>
        </div>
        <div class="status-right">
            <div class="status-item">
                <span id="connectionStatus">Online</span>
            </div>
            <div class="status-item">
                <span id="saveStatus">Pronto</span>
            </div>
        </div>
    </div>

    <script>
        let documentId = <?= $documento_id ? json_encode($documento_id) : 'null' ?>;
        let currentZoom = 1;
        let autoSaveInterval;
        
        // Initialize TinyMCE
        tinymce.init({
            selector: '#tinymce-editor',
            height: 800,
            width: 794, // A4 width
            plugins: [
                'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount',
                'pagebreak', 'nonbreaking', 'quickbars', 'autoresize'
            ],
            toolbar: 'undo redo | formatselect fontselect fontsizeselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | link image media table | pagebreak | removeformat | wordcount',
            
            // A4 Document Settings with page guides
            content_style: `
                body { 
                    font-family: 'Times New Roman', Times, serif; 
                    font-size: 12pt; 
                    line-height: 1.6; 
                    margin: 0;
                    padding: 96px 72px;
                    background: white;
                    max-width: 650px;
                    min-height: 1123px;
                    position: relative;
                    
                    /* Guide pagina visive - ogni 1123px (altezza A4) */
                    background-image: 
                        /* Linea superiore prima pagina */
                        linear-gradient(to right, rgba(13, 115, 119, 0.3) 0%, rgba(13, 115, 119, 0.3) 100%),
                        /* Pattern ripetuto per pagine multiple */
                        repeating-linear-gradient(
                            to bottom,
                            transparent 0px,
                            transparent 1121px,
                            rgba(13, 115, 119, 0.4) 1121px,
                            rgba(13, 115, 119, 0.4) 1123px,
                            transparent 1123px,
                            transparent 1143px
                        );
                    background-size: 
                        100% 2px,
                        100% 1143px;
                    background-position: 
                        0 0,
                        0 0;
                    background-repeat: 
                        no-repeat,
                        repeat-y;
                }
                
                /* Indicatori numerici di pagina */
                body::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 100%;
                    background-image: 
                        repeating-linear-gradient(
                            to bottom,
                            transparent 0px,
                            transparent 1100px,
                            rgba(13, 115, 119, 0.6) 1100px,
                            rgba(13, 115, 119, 0.6) 1102px,
                            transparent 1102px,
                            transparent 1123px
                        );
                    pointer-events: none;
                    z-index: -1;
                }
                
                @page { 
                    size: A4; 
                    margin: 2.5cm 1.9cm; 
                }
                h1 { font-size: 18pt; margin: 24pt 0 12pt 0; page-break-after: avoid; }
                h2 { font-size: 16pt; margin: 18pt 0 6pt 0; page-break-after: avoid; }
                h3 { font-size: 14pt; margin: 12pt 0 6pt 0; page-break-after: avoid; }
                p { margin: 0 0 12pt 0; text-align: justify; }
                ul, ol { margin: 12pt 0 12pt 24pt; }
                li { margin: 3pt 0; }
                table { margin: 12pt 0; border-collapse: collapse; width: 100%; }
                table td, table th { border: 1pt solid #000; padding: 6pt; }
                
                /* Stile per le interruzioni di pagina */
                .page-break {
                    page-break-before: always;
                    border-top: 2px dashed #0d7377 !important;
                    margin: 20px 0 !important;
                    padding: 10px 0 !important;
                    text-align: center !important;
                    color: #0d7377 !important;
                    font-size: 12px !important;
                    position: relative !important;
                }
                
                .page-break::before {
                    content: "--- Nuova Pagina ---" !important;
                    background: white !important;
                    padding: 0 10px !important;
                    position: relative !important;
                    z-index: 1 !important;
                }
            `,
            
            // Configuration
            menubar: 'file edit view insert format tools table help',
            statusbar: true,
            resize: false,
            branding: false,
            promotion: false,
            
            // Language
            language: 'it',
            
            // Font options
            font_formats: 'Times New Roman=times new roman,times,serif; Arial=arial,helvetica,sans-serif; Courier New=courier new,courier,monospace; Georgia=georgia,serif; Verdana=verdana,sans-serif',
            fontsize_formats: '8pt 9pt 10pt 11pt 12pt 14pt 18pt 24pt 30pt 36pt 48pt 60pt 72pt 96pt',
            
            // Page break
            pagebreak_separator: '<div class="page-break" style="page-break-before: always;"></div>',
            
            // Auto-resize
            autoresize_bottom_margin: 50,
            max_height: 2000,
            
            // Events
            setup: function(editor) {
                editor.on('init', function() {
                    console.log('✅ TinyMCE inizializzato correttamente');
                    updateWordCount();
                    updatePageIndicator();
                    startAutoSave();
                });
                
                editor.on('input change', function() {
                    updateDocumentStatus('Modificato - Non salvato');
                    updateWordCount();
                    updatePageIndicator();
                });
                
                editor.on('selectionchange', function() {
                    updatePageIndicator();
                });
                
                editor.on('keyup click', function() {
                    setTimeout(updatePageIndicator, 100);
                });
                
                editor.on('blur', function() {
                    // Auto-save on blur
                    if (editor.isDirty()) {
                        saveDocument(true);
                    }
                });
            },
            
            // File management
            file_picker_callback: function(cb, value, meta) {
                if (meta.filetype === 'image') {
                    // Handle image upload
                    const input = document.createElement('input');
                    input.setAttribute('type', 'file');
                    input.setAttribute('accept', 'image/*');
                    input.onchange = function() {
                        const file = this.files[0];
                        const reader = new FileReader();
                        reader.onload = function() {
                            cb(reader.result, { alt: file.name });
                        };
                        reader.readAsDataURL(file);
                    };
                    input.click();
                }
            }
        });
        
        // Document management functions
        function updateWordCount() {
            if (!tinymce.activeEditor) return;
            
            const content = tinymce.activeEditor.getContent({ format: 'text' });
            const words = content.trim().split(/\s+/).filter(word => word.length > 0).length;
            const chars = content.length;
            
            document.getElementById('wordCount').textContent = `${words} parole, ${chars} caratteri`;
        }
        
        function updatePageIndicator() {
            if (!tinymce.activeEditor) return;
            
            const editor = tinymce.activeEditor;
            const content = editor.getContent();
            const body = editor.getBody();
            
            // Calcola le pagine in base all'altezza del contenuto
            const contentHeight = body.scrollHeight;
            const pageHeight = 1123; // Altezza A4 in pixel
            const totalPages = Math.ceil(contentHeight / pageHeight);
            
            // Calcola la pagina corrente in base alla posizione del cursore
            let currentPage = 1;
            try {
                const selection = editor.selection;
                const range = selection.getRng();
                if (range.startContainer) {
                    const rect = range.getBoundingClientRect();
                    const editorRect = body.getBoundingClientRect();
                    const relativeTop = rect.top - editorRect.top + body.scrollTop;
                    currentPage = Math.max(1, Math.ceil(relativeTop / pageHeight));
                }
            } catch (e) {
                // Fallback se non riusciamo a calcolare la posizione
                currentPage = 1;
            }
            
            // Aggiorna l'indicatore
            const pageInfo = document.getElementById('pageInfo');
            if (totalPages > 1) {
                pageInfo.textContent = `Pagina ${currentPage} di ${totalPages}`;
            } else {
                pageInfo.textContent = 'Pagina 1 di 1';
            }
            
            // Aggiorna anche il titolo della finestra
            document.title = `Pagina ${currentPage}/${totalPages} - <?= htmlspecialchars($titolo) ?>`;
        }
        
        function updateDocumentStatus(message, type = 'normal') {
            const statusEl = document.getElementById('documentStatus');
            const saveStatusEl = document.getElementById('saveStatus');
            
            statusEl.textContent = message;
            saveStatusEl.textContent = message;
            
            // Remove existing classes
            statusEl.className = '';
            saveStatusEl.className = '';
            
            // Add type class
            if (type === 'success') {
                statusEl.className = 'status-success';
                saveStatusEl.className = 'status-success';
            } else if (type === 'error') {
                statusEl.className = 'status-error';
                saveStatusEl.className = 'status-error';
            } else if (type === 'warning') {
                statusEl.className = 'status-warning';
                saveStatusEl.className = 'status-warning';
            }
        }
        
        // Document actions
        function insertPageBreak() {
            if (!tinymce.activeEditor) return;
            
            tinymce.activeEditor.execCommand('mcePageBreak');
            updatePageIndicator();
        }
        
        async function saveDocument(auto = false) {
            if (!tinymce.activeEditor) {
                if (!auto) alert('❌ Editor non disponibile');
                return;
            }
            
            const saveBtn = document.getElementById('saveBtn');
            const originalText = saveBtn.innerHTML;
            
            if (!auto) {
                saveBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Salvando...';
                saveBtn.disabled = true;
            }
            
            updateDocumentStatus('💾 Salvando...', 'warning');
            
            try {
                const content = tinymce.activeEditor.getContent();
                const title = '<?= addslashes($titolo) ?>';
                
                if (!content || content.trim() === '' || content === '<p></p>') {
                    throw new Error('Il documento è vuoto');
                }
                
                const formData = new FormData();
                formData.append('action', 'save');
                formData.append('title', title);
                formData.append('content', content);
                
                if (documentId) {
                    formData.append('documento_id', documentId);
                }
                
                const response = await fetch('backend/api/document-editor.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    updateDocumentStatus('✅ Salvato con successo', 'success');
                    const now = new Date().toLocaleTimeString('it-IT');
                    document.getElementById('lastSaved').textContent = `Salvato: ${now}`;
                    
                    if (!documentId && result.documento_id) {
                        documentId = result.documento_id;
                        if (window.history && window.history.pushState) {
                            const newUrl = window.location.pathname + '?id=' + documentId;
                            window.history.pushState({}, '', newUrl);
                        }
                    }
                    
                    // Mark as clean
                    tinymce.activeEditor.setDirty(false);
                    
                    if (!auto) {
                        setTimeout(() => updateDocumentStatus('Pronto'), 3000);
                    }
                } else {
                    throw new Error(result.error || 'Errore sconosciuto');
                }
                
            } catch (error) {
                console.error('Errore salvataggio:', error);
                updateDocumentStatus('❌ Errore salvataggio', 'error');
                if (!auto) {
                    alert('❌ Errore salvataggio: ' + error.message);
                }
            } finally {
                if (!auto) {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }
        }
        
        function exportToPDF() {
            if (!tinymce.activeEditor) return;
            
            const content = tinymce.activeEditor.getContent();
            const title = '<?= addslashes($titolo) ?>';
            
            // Use TinyMCE's built-in PDF export if available
            if (tinymce.activeEditor.plugins.exportpdf) {
                tinymce.activeEditor.execCommand('mceExportToPDF');
            } else {
                // Fallback to print
                printDocument();
            }
        }
        
        function printDocument() {
            if (!tinymce.activeEditor) return;
            
            const content = tinymce.activeEditor.getContent();
            const title = '<?= addslashes($titolo) ?>';
            
            const printWindow = window.open('', '_blank');
            if (!printWindow) {
                alert('Impossibile aprire finestra di stampa');
                return;
            }
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${title}</title>
                    <style>
                        @page { size: A4; margin: 2.5cm 1.9cm; }
                        body { 
                            font-family: 'Times New Roman', serif; 
                            font-size: 12pt; 
                            line-height: 1.6; 
                            margin: 0; 
                            color: #333;
                        }
                        h1 { font-size: 18pt; margin: 24pt 0 12pt 0; page-break-after: avoid; }
                        h2 { font-size: 16pt; margin: 18pt 0 6pt 0; page-break-after: avoid; }
                        h3 { font-size: 14pt; margin: 12pt 0 6pt 0; page-break-after: avoid; }
                        p { margin: 0 0 12pt 0; text-align: justify; }
                        ul, ol { margin: 12pt 0 12pt 24pt; }
                        li { margin: 3pt 0; }
                        table { margin: 12pt 0; border-collapse: collapse; width: 100%; }
                        table td, table th { border: 1pt solid #000; padding: 6pt; }
                        .page-break { page-break-before: always; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <h1 style="text-align: center; margin-bottom: 30px;">${title}</h1>
                    ${content}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
        
        // Zoom functions
        function zoomIn() {
            currentZoom = Math.min(currentZoom + 0.1, 2);
            applyZoom();
        }
        
        function zoomOut() {
            currentZoom = Math.max(currentZoom - 0.1, 0.5);
            applyZoom();
        }
        
        function applyZoom() {
            const wrapper = document.querySelector('.document-wrapper');
            wrapper.style.transform = `scale(${currentZoom})`;
            wrapper.style.transformOrigin = 'top center';
            document.getElementById('zoomLevel').textContent = Math.round(currentZoom * 100) + '%';
        }
        
        // Auto-save functionality
        function startAutoSave() {
            // Auto-save every 2 minutes
            autoSaveInterval = setInterval(() => {
                if (tinymce.activeEditor && tinymce.activeEditor.isDirty()) {
                    saveDocument(true);
                }
            }, 120000);
        }
        
        function stopAutoSave() {
            if (autoSaveInterval) {
                clearInterval(autoSaveInterval);
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveDocument();
            }
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                insertPageBreak();
            }
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printDocument();
            }
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function(e) {
            if (tinymce.activeEditor && tinymce.activeEditor.isDirty()) {
                const message = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
                e.returnValue = message;
                return message;
            }
            stopAutoSave();
        });
        
        // Connection status
        function updateConnectionStatus() {
            const statusEl = document.getElementById('connectionStatus');
            if (navigator.onLine) {
                statusEl.textContent = 'Online';
                statusEl.className = 'status-success';
            } else {
                statusEl.textContent = 'Offline';
                statusEl.className = 'status-error';
            }
        }
        
        // Monitor connection
        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        updateConnectionStatus();
    </script>
</body>
</html>