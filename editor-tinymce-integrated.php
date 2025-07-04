<?php
/**
 * Nuovo Editor TinyMCE Integrato con Template System
 * Editor completo con header/footer visibili durante l'editing
 */

session_start();
require_once __DIR__ . '/backend/config/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    // For development: create a test session
    if (!isset($_GET['dev']) || $_GET['dev'] !== 'test') {
        header('Location: login.php');
        exit;
    } else {
        // Simula sessione per testing
        $_SESSION['user_id'] = 1;
        $_SESSION['azienda_corrente'] = 1;
        $_SESSION['auth_token'] = 'dev_token_' . time();
        $_SESSION['login_time'] = time();
    }
}

$user_id = $_SESSION['user_id'];
$documento_id = $_GET['id'] ?? null;
$documento = null;

// Load existing document if ID provided
if ($documento_id) {
    try {
        // Database instance handled by functions
        $stmt = db_query("SELECT * FROM documenti WHERE id = ? AND utente_id = ?", [$documento_id, $user_id]);
        $documento = $stmt->fetch();
        
        if (!$documento) {
            die('Documento non trovato o non autorizzato');
        }
    } catch (Exception $e) {
        die('Errore caricamento documento: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $documento ? 'Modifica: ' . htmlspecialchars($documento['titolo']) : 'Nuovo Documento' ?> - Nexio Editor</title>
    
    <!-- TinyMCE CDN -->
    <script src="https://cdn.tiny.cloud/1/bhocezj3lxp7yvli73hunsrwjttflpmz8k3m4gbmxc8u937z/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            overflow-x: hidden;
        }

        /* Header della pagina */
        .page-header {
            background: white;
            border-bottom: 1px solid #ddd;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .page-header h1 {
            color: #0078d4;
            font-size: 24px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-primary { background: #0078d4; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline { background: white; color: #0078d4; border: 1px solid #0078d4; }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Container principale */
        .editor-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Barra degli strumenti personalizzata */
        .custom-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .toolbar-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .document-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .document-title {
            font-weight: 600;
            color: #333;
        }

        .document-meta {
            font-size: 12px;
            color: #666;
        }

        .template-info {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Area editor con template */
        .editor-wrapper {
            background: #f0f0f0;
            padding: 20px;
            min-height: 600px;
        }

        .document-page {
            background: white;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .page-template-header {
            background: #f8f9fa;
            border-bottom: 2px solid #0078d4;
            padding: 20px;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-template-footer {
            background: #f8f9fa;
            border-top: 1px solid #ccc;
            padding: 15px 20px;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: auto;
        }

        .page-content-area {
            flex: 1;
            padding: 30px;
        }

        /* TinyMCE customization */
        .tox-tinymce {
            border: none !important;
            border-radius: 0 !important;
        }

        .tox-editor-header {
            border: none !important;
            background: #f8f9fa !important;
        }

        /* Status bar */
        .status-bar {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #666;
        }

        .status-left, .status-right {
            display: flex;
            gap: 20px;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 8px 16px rgba(0,0,0,0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .custom-toolbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .document-page {
                margin: 0 10px 20px;
                max-width: calc(100% - 20px);
            }
        }

        /* Notification system */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 15px 20px;
            max-width: 350px;
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success { border-left: 4px solid #28a745; }
        .notification.error { border-left: 4px solid #dc3545; }
        .notification.warning { border-left: 4px solid #ffc107; }
        .notification.info { border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
            <p style="margin-top: 15px;">Caricamento template...</p>
        </div>
    </div>

    <!-- Header della pagina -->
    <header class="page-header">
        <h1>
            <i class="fas fa-file-alt"></i>
            <?= $documento ? htmlspecialchars($documento['titolo']) : 'Nuovo Documento' ?>
        </h1>
        
        <div class="header-actions">
            <button class="btn btn-outline" onclick="loadTemplate()">
                <i class="fas fa-sync-alt"></i> Ricarica Template
            </button>
            
            <button class="btn btn-secondary" onclick="saveDocument()">
                <i class="fas fa-save"></i> Salva
            </button>
            
            <button class="btn btn-primary" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> Esporta PDF
            </button>
            
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Torna alla Dashboard
            </a>
        </div>
    </header>

    <!-- Container principale -->
    <div class="editor-container">
        <!-- Barra degli strumenti personalizzata -->
        <div class="custom-toolbar">
            <div class="toolbar-section">
                <div class="document-info">
                    <div class="document-title" id="documentTitle">
                        <?= $documento ? htmlspecialchars($documento['titolo']) : 'Nuovo Documento' ?>
                    </div>
                    <div class="document-meta">
                        <span id="lastSaved">Mai salvato</span> • 
                        <span id="wordCount">0 parole</span> • 
                        <span id="charCount">0 caratteri</span>
                    </div>
                </div>
            </div>
            
            <div class="toolbar-section">
                <div class="template-info" id="templateInfo">
                    <i class="fas fa-file-contract"></i>
                    <span>Caricamento template...</span>
                </div>
            </div>
        </div>

        <!-- Area editor con template -->
        <div class="editor-wrapper">
            <div class="document-page" id="documentPage">
                <!-- Header del template -->
                <div class="page-template-header" id="templateHeader">
                    <div style="text-align: center; color: #666;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Caricamento header template...
                    </div>
                </div>

                <!-- Area contenuto principale -->
                <div class="page-content-area">
                    <textarea id="mainEditor"><?= $documento ? htmlspecialchars($documento['contenuto']) : '' ?></textarea>
                </div>

                <!-- Footer del template -->
                <div class="page-template-footer" id="templateFooter">
                    <div style="text-align: center; color: #666;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Caricamento footer template...
                    </div>
                </div>
            </div>
        </div>

        <!-- Status bar -->
        <div class="status-bar">
            <div class="status-left">
                <div class="status-item">
                    <i class="fas fa-user"></i>
                    <span>Utente: <?= $_SESSION['user_id'] ?? 'Guest' ?></span>
                </div>
                <div class="status-item">
                    <i class="fas fa-building"></i>
                    <span id="companyName">Caricamento...</span>
                </div>
            </div>
            
            <div class="status-right">
                <div class="status-item">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"></span>
                </div>
                <div class="status-item" id="connectionStatus">
                    <i class="fas fa-circle text-success"></i>
                    <span>Connesso</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configurazione globale
        const EDITOR_CONFIG = {
            documentId: <?= $documento_id ? json_encode($documento_id) : 'null' ?>,
            userId: <?= json_encode($user_id) ?>,
            autoSaveInterval: 30000, // 30 secondi
            currentTemplate: null,
            currentAzienda: null,
            isModified: false,
            lastSaveTime: null
        };

        // Inizializzazione TinyMCE
        tinymce.init({
            selector: '#mainEditor',
            height: 500,
            plugins: [
                // Core editing features
                'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount',
                // Premium features
                'checklist', 'mediaembed', 'casechange', 'formatpainter', 'pageembed', 'a11ychecker', 'tinymcespellchecker', 'permanentpen', 'powerpaste', 'advtable', 'advcode', 'editimage', 'advtemplate', 'mentions', 'tableofcontents', 'footnotes', 'mergetags', 'autocorrect', 'typography', 'inlinecss', 'markdown', 'importword', 'exportword', 'exportpdf'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table mergetags | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat | exportpdf',
            content_style: `
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    font-size: 14px;
                    line-height: 1.6;
                    color: #333;
                    max-width: none;
                    margin: 0;
                    padding: 20px;
                }
                p { margin-bottom: 1em; }
                h1, h2, h3, h4, h5, h6 { 
                    margin-top: 1.5em; 
                    margin-bottom: 0.5em; 
                    font-weight: 600;
                }
            `,
            menubar: false,
            statusbar: false,
            resize: false,
            branding: false,
            elementpath: false,
            
            // Merge tags per variabili template
            mergetags_list: [
                { value: 'nome_azienda', title: 'Nome Azienda' },
                { value: 'indirizzo_azienda', title: 'Indirizzo Azienda' },
                { value: 'telefono_azienda', title: 'Telefono Azienda' },
                { value: 'email_azienda', title: 'Email Azienda' },
                { value: 'data_corrente', title: 'Data Corrente' },
                { value: 'numero_pagina', title: 'Numero Pagina' },
            ],

            // Setup callback
            setup: function(editor) {
                editor.on('init', function() {
                    console.log('✅ TinyMCE inizializzato');
                    initializeEditor();
                });

                editor.on('input change', function() {
                    EDITOR_CONFIG.isModified = true;
                    updateWordCount();
                });

                // Auto-save
                editor.on('input', tinymce.util.Tools.debounce(function() {
                    if (EDITOR_CONFIG.isModified) {
                        autoSaveDocument();
                    }
                }, 5000));
            },

            // Configurazione export PDF
            exportpdf_converter_options: {
                'format': 'A4',
                'margin_top': '30mm',
                'margin_right': '20mm',
                'margin_bottom': '30mm',
                'margin_left': '20mm'
            },

            // Impostazioni avanzate
            paste_data_images: true,
            paste_as_text: false,
            smart_paste: true,
            
            // Configurazione immagini
            images_upload_url: 'backend/api/upload-image.php',
            images_upload_credentials: true,
            
            // Configurazione link
            link_default_target: '_blank',
            link_assume_external_targets: true
        });

        // Inizializzazione editor
        async function initializeEditor() {
            showLoading(true);
            
            try {
                // Carica template azienda
                await loadTemplate();
                
                // Avvia auto-save
                startAutoSave();
                
                // Aggiorna statistiche
                updateWordCount();
                updateCurrentTime();
                
                // Setup timer per aggiornamento ora
                setInterval(updateCurrentTime, 60000);
                
                console.log('✅ Editor completamente inizializzato');
                showNotification('🚀 Editor caricato con successo!', 'success');
                
            } catch (error) {
                console.error('❌ Errore inizializzazione editor:', error);
                showNotification('⚠️ Errore caricamento editor', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Caricamento template
        async function loadTemplate() {
            try {
                console.log('🔍 Caricamento template azienda...');
                const response = await fetch('backend/api/get-template-azienda.php');
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('📋 Risposta API template:', result);
                    
                    if (result.success) {
                        EDITOR_CONFIG.currentTemplate = result.template;
                        EDITOR_CONFIG.currentAzienda = result.azienda;
                        
                        // Applica template alla pagina
                        applyTemplateToPage();
                        
                        // Aggiorna info template
                        updateTemplateInfo();
                        
                        console.log('✅ Template applicato:', result.template.nome);
                        showNotification(`📄 Template "${result.template.nome}" caricato`, 'success');
                        
                    } else {
                        throw new Error(result.error || 'Errore caricamento template');
                    }
                } else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
            } catch (error) {
                console.error('❌ Errore caricamento template:', error);
                showNotification(`⚠️ ${error.message}`, 'error');
                
                // Applica template di fallback
                applyFallbackTemplate();
            }
        }

        // Applica template alla pagina
        function applyTemplateToPage() {
            const template = EDITOR_CONFIG.currentTemplate;
            const azienda = EDITOR_CONFIG.currentAzienda;
            
            if (!template) return;

            // Applica header
            if (template.header_html) {
                const headerElement = document.getElementById('templateHeader');
                headerElement.innerHTML = processTemplateVariables(template.header_html, {
                    nome_azienda: azienda?.nome || 'Azienda',
                    logo_azienda: azienda?.logo ? `uploads/loghi/${azienda.logo}` : '',
                    indirizzo_azienda: azienda?.indirizzo || '',
                    telefono_azienda: azienda?.telefono || '',
                    email_azienda: azienda?.email || '',
                    partita_iva: azienda?.partita_iva || '',
                    codice_fiscale: azienda?.codice_fiscale || '',
                    data_corrente: new Date().toLocaleDateString('it-IT'),
                    anno: new Date().getFullYear(),
                    numero_pagina: 1
                });
            }

            // Applica footer
            if (template.footer_html) {
                const footerElement = document.getElementById('templateFooter');
                footerElement.innerHTML = processTemplateVariables(template.footer_html, {
                    nome_azienda: azienda?.nome || 'Azienda',
                    logo_azienda: azienda?.logo ? `uploads/loghi/${azienda.logo}` : '',
                    indirizzo_azienda: azienda?.indirizzo || '',
                    telefono_azienda: azienda?.telefono || '',
                    email_azienda: azienda?.email || '',
                    partita_iva: azienda?.partita_iva || '',
                    codice_fiscale: azienda?.codice_fiscale || '',
                    data_corrente: new Date().toLocaleDateString('it-IT'),
                    anno: new Date().getFullYear(),
                    numero_pagina: 1
                });
            }
        }

        // Template di fallback
        function applyFallbackTemplate() {
            EDITOR_CONFIG.currentTemplate = {
                id: 0,
                nome: 'Template Base',
                header_html: `
                    <div style="text-align: center; border-bottom: 2px solid #0078d4; padding: 15px;">
                        <h2 style="color: #0078d4; margin: 0;">Nexio Platform</h2>
                        <p style="margin: 5px 0; font-size: 12px; color: #666;">{data_corrente}</p>
                    </div>`,
                footer_html: `
                    <div style="text-align: center; border-top: 1px solid #ccc; padding: 10px; font-size: 11px; color: #666;">
                        <p>© {anno} Nexio Platform | Pagina {numero_pagina}</p>
                    </div>`
            };
            EDITOR_CONFIG.currentAzienda = { nome: 'Azienda' };
            
            applyTemplateToPage();
            updateTemplateInfo();
            console.log('🔧 Template di fallback applicato');
        }

        // Elaborazione variabili template
        function processTemplateVariables(html, variables) {
            let processedHtml = html;
            Object.entries(variables).forEach(([key, value]) => {
                const regex1 = new RegExp(`\\{${key}\\}`, 'g');
                const regex2 = new RegExp(`\\[\\[${key.toUpperCase()}\\]\\]`, 'g');
                processedHtml = processedHtml.replace(regex1, value);
                processedHtml = processedHtml.replace(regex2, value);
            });
            return processedHtml;
        }

        // Aggiorna info template
        function updateTemplateInfo() {
            const template = EDITOR_CONFIG.currentTemplate;
            const azienda = EDITOR_CONFIG.currentAzienda;
            
            const templateInfo = document.getElementById('templateInfo');
            const companyName = document.getElementById('companyName');
            
            if (template && azienda) {
                templateInfo.innerHTML = `
                    <i class="fas fa-file-contract"></i>
                    <span>${template.nome} - ${azienda.nome}</span>
                `;
                companyName.textContent = azienda.nome;
            }
        }

        // Salvataggio documento
        async function saveDocument() {
            if (!EDITOR_CONFIG.isModified && EDITOR_CONFIG.documentId) {
                showNotification('📄 Nessuna modifica da salvare', 'info');
                return;
            }

            try {
                showLoading(true);
                
                const content = tinymce.get('mainEditor').getContent();
                const wordCount = tinymce.get('mainEditor').plugins.wordcount.getCount();
                
                const formData = new FormData();
                formData.append('contenuto', content);
                formData.append('word_count', wordCount);
                
                if (EDITOR_CONFIG.documentId) {
                    formData.append('documento_id', EDITOR_CONFIG.documentId);
                }

                const response = await fetch('backend/api/save-advanced-document.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        EDITOR_CONFIG.isModified = false;
                        EDITOR_CONFIG.lastSaveTime = new Date();
                        
                        if (!EDITOR_CONFIG.documentId && result.docId) {
                            EDITOR_CONFIG.documentId = result.docId;
                            // Aggiorna URL senza ricaricare la pagina
                            history.replaceState(null, null, `?id=${result.docId}&dev=test`);
                        }
                        
                        updateLastSaved();
                        showNotification('💾 Documento salvato con successo!', 'success');
                    } else {
                        throw new Error(result.error || 'Errore salvataggio');
                    }
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
                
            } catch (error) {
                console.error('❌ Errore salvataggio:', error);
                showNotification(`⚠️ Errore salvataggio: ${error.message}`, 'error');
            } finally {
                showLoading(false);
            }
        }

        // Auto-save
        function startAutoSave() {
            setInterval(async () => {
                if (EDITOR_CONFIG.isModified) {
                    await autoSaveDocument();
                }
            }, EDITOR_CONFIG.autoSaveInterval);
        }

        async function autoSaveDocument() {
            try {
                const content = tinymce.get('mainEditor').getContent();
                const formData = new FormData();
                formData.append('contenuto', content);
                formData.append('auto_save', '1');
                
                if (EDITOR_CONFIG.documentId) {
                    formData.append('documento_id', EDITOR_CONFIG.documentId);
                }

                const response = await fetch('backend/api/save-advanced-document.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        EDITOR_CONFIG.isModified = false;
                        EDITOR_CONFIG.lastSaveTime = new Date();
                        updateLastSaved();
                        
                        // Update connection status
                        updateConnectionStatus(true);
                    }
                }
            } catch (error) {
                console.log('Auto-save failed:', error);
                updateConnectionStatus(false);
            }
        }

        // Export PDF
        function exportPDF() {
            const editor = tinymce.get('mainEditor');
            if (editor && editor.plugins.exportpdf) {
                editor.plugins.exportpdf.exportPdf();
            } else {
                showNotification('⚠️ Funzione PDF non disponibile', 'warning');
            }
        }

        // Utility functions
        function updateWordCount() {
            const editor = tinymce.get('mainEditor');
            if (editor && editor.plugins.wordcount) {
                const wordCount = editor.plugins.wordcount.getCount();
                const charCount = editor.plugins.wordcount.getCharacterCount();
                
                document.getElementById('wordCount').textContent = `${wordCount} parole`;
                document.getElementById('charCount').textContent = `${charCount} caratteri`;
            }
        }

        function updateCurrentTime() {
            document.getElementById('currentTime').textContent = 
                new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        }

        function updateLastSaved() {
            const lastSaved = document.getElementById('lastSaved');
            if (EDITOR_CONFIG.lastSaveTime) {
                lastSaved.textContent = `Salvato: ${EDITOR_CONFIG.lastSaveTime.toLocaleTimeString('it-IT')}`;
            }
        }

        function updateConnectionStatus(connected) {
            const status = document.getElementById('connectionStatus');
            const icon = status.querySelector('i');
            const text = status.querySelector('span');
            
            if (connected) {
                icon.className = 'fas fa-circle';
                icon.style.color = '#28a745';
                text.textContent = 'Connesso';
            } else {
                icon.className = 'fas fa-circle';
                icon.style.color = '#dc3545';
                text.textContent = 'Disconnesso';
            }
        }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (show) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }

        function showNotification(message, type = 'info', duration = 5000) {
            // Rimuovi notifiche esistenti
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666;">×</button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Mostra notifica
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Rimuovi automaticamente
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM caricato, in attesa di TinyMCE...');
        });

        // Previeni uscita accidentale
        window.addEventListener('beforeunload', function(e) {
            if (EDITOR_CONFIG.isModified) {
                e.preventDefault();
                e.returnValue = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
                return e.returnValue;
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S per salvare
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveDocument();
            }
            
            // Ctrl+Shift+P per PDF
            if (e.ctrlKey && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                exportPDF();
            }
        });
    </script>
</body>
</html>