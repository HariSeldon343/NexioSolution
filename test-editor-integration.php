<?php
/**
 * Test per verificare l'integrazione di TinyMCE self-hosted
 * e la funzionalità TOC
 */

require_once 'backend/middleware/Auth.php';
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = "Test Editor Integration";
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
    
    <!-- TinyMCE Self-Hosted -->
    <script src="assets/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
    
    <style>
        body {
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 4px;
        }
        .test-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .test-status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .feature-test {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .feature-test .badge {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Test Integrazione TinyMCE Self-Hosted</h1>
        
        <div class="test-status info">
            <h4>Verifica Componenti</h4>
            <div class="feature-test">
                TinyMCE Core: <span id="tinymce-status" class="badge bg-secondary">Verificando...</span>
            </div>
            <div class="feature-test">
                Plugin OSS: <span id="plugins-status" class="badge bg-secondary">Verificando...</span>
            </div>
            <div class="feature-test">
                TOC Feature: <span id="toc-status" class="badge bg-secondary">Verificando...</span>
            </div>
        </div>
        
        <h3 class="mt-4">Editor di Test</h3>
        <p>Usa questo editor per testare le funzionalità. Prova ad inserire il placeholder [[TOC]] e alcuni heading.</p>
        
        <textarea id="test-editor">
            <h1>Documento di Test</h1>
            <p>Inserisci [[TOC]] qui sotto per generare l'indice:</p>
            <p>[[TOC]]</p>
            
            <h2>Sezione 1: Introduzione</h2>
            <p>Questo è il contenuto della prima sezione.</p>
            
            <h3>Sottosezione 1.1</h3>
            <p>Dettagli della sottosezione.</p>
            
            <h2>Sezione 2: Contenuto Principale</h2>
            <p>Questo è il contenuto principale del documento.</p>
            
            <h3>Sottosezione 2.1</h3>
            <p>Altri dettagli.</p>
            
            <h3>Sottosezione 2.2</h3>
            <p>Ancora più dettagli.</p>
            
            <h2>Sezione 3: Conclusioni</h2>
            <p>Le conclusioni del documento.</p>
        </textarea>
        
        <div class="mt-4">
            <h4>Azioni Test</h4>
            <button class="btn btn-primary" onclick="insertTOC()">Inserisci TOC</button>
            <button class="btn btn-secondary" onclick="getContent()">Ottieni Contenuto</button>
            <button class="btn btn-success" onclick="testExport('docx')">Test Export DOCX</button>
            <button class="btn btn-info" onclick="testExport('pdf')">Test Export PDF</button>
        </div>
        
        <div class="mt-4">
            <h4>Output</h4>
            <pre id="output" style="background: #f8f9fa; padding: 15px; border-radius: 4px; min-height: 100px;">
Pronto per il test...
            </pre>
        </div>
    </div>
    
    <script>
    let editor;
    
    // Verifica disponibilità TinyMCE
    function checkTinyMCE() {
        const statusEl = document.getElementById('tinymce-status');
        if (typeof tinymce !== 'undefined') {
            statusEl.textContent = 'Caricato';
            statusEl.className = 'badge bg-success';
            return true;
        } else {
            statusEl.textContent = 'Non trovato';
            statusEl.className = 'badge bg-danger';
            return false;
        }
    }
    
    // Inizializza TinyMCE
    tinymce.init({
        selector: '#test-editor',
        license_key: '4jharm4wbljffqkf1cmbbehx5nzacqseuqlmsjoyre65ikvr',
        base_url: '/piattaforma-collaborativa/assets/vendor/tinymce/js/tinymce',
        suffix: '.min',
        height: 400,
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
        
        setup: function(ed) {
            editor = ed;
            
            // Aggiungi comando custom per TOC
            ed.ui.registry.addButton('insertTOC', {
                text: 'TOC',
                tooltip: 'Inserisci indice (Table of Contents)',
                onAction: function() {
                    ed.insertContent('<p>[[TOC]]</p>');
                    document.getElementById('output').textContent = 'TOC placeholder inserito!';
                }
            });
            
            ed.on('init', function() {
                document.getElementById('plugins-status').textContent = 'Caricati';
                document.getElementById('plugins-status').className = 'badge bg-success';
                
                // Verifica TOC button
                if (ed.ui.registry.getAll().buttons.insertTOC) {
                    document.getElementById('toc-status').textContent = 'Disponibile';
                    document.getElementById('toc-status').className = 'badge bg-success';
                } else {
                    document.getElementById('toc-status').textContent = 'Non disponibile';
                    document.getElementById('toc-status').className = 'badge bg-warning';
                }
            });
        }
    });
    
    // Funzione per inserire TOC
    function insertTOC() {
        if (editor) {
            editor.insertContent('<p>[[TOC]]</p>');
            document.getElementById('output').textContent = 'TOC placeholder inserito nel documento!';
        }
    }
    
    // Funzione per ottenere il contenuto
    function getContent() {
        if (editor) {
            const content = editor.getContent();
            document.getElementById('output').textContent = content;
        }
    }
    
    // Funzione per testare l'export
    async function testExport(format) {
        if (!editor) {
            alert('Editor non inizializzato');
            return;
        }
        
        const content = editor.getContent();
        const outputEl = document.getElementById('output');
        
        outputEl.textContent = `Testando export ${format.toUpperCase()}...\n`;
        
        try {
            const formData = new FormData();
            formData.append('action', 'export');
            formData.append('content', content);
            formData.append('format', format);
            formData.append('header_text', 'Test Header - Nexio Platform');
            formData.append('footer_text', 'Test Footer - Pagina');
            formData.append('page_numbering', 'true');
            formData.append('page_number_format', 'page_x_of_y');
            
            const response = await fetch('backend/api/document-editor.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                outputEl.textContent = `Export ${format.toUpperCase()} completato con successo!\n`;
                outputEl.textContent += `File: ${result.export.filename}\n`;
                outputEl.textContent += `Dimensione: ${result.export.size} bytes\n`;
                outputEl.textContent += `\nVerifica che:\n`;
                outputEl.textContent += `1. Il placeholder [[TOC]] sia stato sostituito con l'indice\n`;
                outputEl.textContent += `2. Header e footer siano presenti\n`;
                outputEl.textContent += `3. La numerazione pagine sia corretta\n`;
                
                if (result.export.download_url) {
                    outputEl.textContent += `\nDownload URL: ${result.export.download_url}`;
                    window.open(result.export.download_url, '_blank');
                }
            } else {
                outputEl.textContent = `Errore export: ${result.error}`;
            }
        } catch (error) {
            outputEl.textContent = `Errore: ${error.message}`;
        }
    }
    
    // Verifica iniziale
    window.addEventListener('DOMContentLoaded', function() {
        checkTinyMCE();
    });
    </script>
</body>
</html>