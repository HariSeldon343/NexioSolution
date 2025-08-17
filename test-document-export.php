<?php
/**
 * Test page per verificare export DOCX/PDF con header, footer e numeri di pagina
 */

require_once 'backend/middleware/Auth.php';
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Genera CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Test Export Documenti con Header/Footer";
if (!defined('APP_PATH')) {
    define('APP_PATH', '/piattaforma-collaborativa');
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
    
    <style>
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .test-section h3 {
            color: #495057;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .output-area {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            min-height: 100px;
        }
        .success-msg {
            color: #28a745;
            padding: 10px;
            background: #d4edda;
            border-radius: 5px;
            margin-top: 10px;
        }
        .error-msg {
            color: #dc3545;
            padding: 10px;
            background: #f8d7da;
            border-radius: 5px;
            margin-top: 10px;
        }
        .preview-box {
            border: 2px dashed #dee2e6;
            padding: 20px;
            margin-top: 15px;
            min-height: 200px;
            background: white;
        }
        .feature-card {
            background: #e7f3ff;
            border: 1px solid #0d6efd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-file-export"></i> Test Export Documenti con Header/Footer</h1>
        
        <!-- Test 1: Creazione documento con header/footer -->
        <div class="test-section">
            <h3>📝 Test 1: Crea Documento con Header/Footer</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Titolo Documento:</label>
                        <input type="text" id="docTitle" class="form-control" value="Documento Test con Header">
                    </div>
                    
                    <div class="form-group">
                        <label>Contenuto HTML:</label>
                        <textarea id="docContent" class="form-control" rows="5">
<h1>Documento di Test</h1>
<p>Questo è un documento di test per verificare l'export con header, footer e numeri di pagina.</p>
<h2>Sezione 1</h2>
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
<h2>Sezione 2</h2>
<p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                        </textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Header (Intestazione):</label>
                        <input type="text" id="headerText" class="form-control" value="Nexio Platform - Documento Ufficiale">
                    </div>
                    
                    <div class="form-group">
                        <label>Footer (Piè di pagina):</label>
                        <input type="text" id="footerText" class="form-control" value="© 2025 Nexio - Confidenziale">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="enablePageNumbers" checked> 
                            Abilita Numeri di Pagina
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Formato Numeri Pagina:</label>
                        <select id="pageNumberFormat" class="form-control">
                            <option value="page_x">Pagina X</option>
                            <option value="page_x_of_y" selected>Pagina X di Y</option>
                            <option value="x_of_y">X / Y</option>
                            <option value="simple">X</option>
                        </select>
                    </div>
                    
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="testCreateDocument()">
                            <i class="fas fa-plus"></i> Crea Documento
                        </button>
                        <button class="btn btn-success" onclick="testExportDocx()">
                            <i class="fas fa-file-word"></i> Export DOCX
                        </button>
                        <button class="btn btn-danger" onclick="testExportPdf()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                    
                    <div id="test1Output" class="output-area"></div>
                </div>
            </div>
        </div>
        
        <!-- Test 2: Import DOCX con header/footer -->
        <div class="test-section">
            <h3>📥 Test 2: Import DOCX con Header/Footer</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Seleziona file DOCX:</label>
                        <input type="file" id="docxFile" class="form-control" accept=".docx">
                    </div>
                    
                    <button class="btn btn-primary" onclick="testImportDocx()">
                        <i class="fas fa-upload"></i> Import e Analizza
                    </button>
                </div>
                
                <div class="col-md-6">
                    <div id="test2Output" class="output-area">
                        <p class="text-muted">Carica un file DOCX per vedere header/footer estratti</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test 3: Versioning con metadata -->
        <div class="test-section">
            <h3>🔄 Test 3: Versioning con Metadata Header/Footer</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>ID Documento:</label>
                        <input type="number" id="versionDocId" class="form-control" value="13">
                    </div>
                    
                    <button class="btn btn-primary" onclick="testLoadVersions()">
                        <i class="fas fa-history"></i> Carica Versioni
                    </button>
                    
                    <button class="btn btn-success" onclick="testSaveWithMetadata()">
                        <i class="fas fa-save"></i> Salva con Metadata
                    </button>
                </div>
                
                <div class="col-md-6">
                    <div id="test3Output" class="output-area">
                        <p class="text-muted">Le versioni appariranno qui</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test 4: Preview generazione -->
        <div class="test-section">
            <h3>👁️ Test 4: Preview Generazione Header/Footer</h3>
            
            <div class="preview-box">
                <div style="border-bottom: 2px solid #dee2e6; padding: 10px; text-align: center; background: #f8f9fa;">
                    <strong>HEADER:</strong> <span id="previewHeader">Nexio Platform - Documento Ufficiale</span>
                </div>
                
                <div style="padding: 20px; min-height: 300px;">
                    <h2>Contenuto Documento</h2>
                    <p>Lorem ipsum dolor sit amet...</p>
                    <p>Consectetur adipiscing elit...</p>
                </div>
                
                <div style="border-top: 2px solid #dee2e6; padding: 10px; background: #f8f9fa;">
                    <div class="d-flex justify-content-between">
                        <span><strong>FOOTER:</strong> <span id="previewFooter">© 2025 Nexio - Confidenziale</span></span>
                        <span><strong>Pagina</strong> <span id="previewPageNum">1 di 3</span></span>
                    </div>
                </div>
            </div>
            
            <button class="btn btn-info mt-3" onclick="updatePreview()">
                <i class="fas fa-sync"></i> Aggiorna Preview
            </button>
        </div>
        
        <!-- Informazioni tecniche -->
        <div class="test-section">
            <h3>ℹ️ Informazioni Tecniche</h3>
            
            <div class="feature-card">
                <h5>Funzionalità Implementate:</h5>
                <ul>
                    <li>✅ Interfaccia UI per gestione header/footer in document-editor.php</li>
                    <li>✅ API backend per salvare header/footer in DOCX</li>
                    <li>✅ Supporto numeri di pagina con 4 formati diversi</li>
                    <li>✅ Import/Export DOCX con preservazione header/footer</li>
                    <li>✅ Export PDF con header/footer usando DOMPDF</li>
                    <li>✅ Salvataggio metadata in document_versions</li>
                    <li>✅ Compatibilità con Auth, CSRF e PermissionManager</li>
                </ul>
            </div>
            
            <div class="feature-card">
                <h5>API Endpoints:</h5>
                <div class="code-block">
POST /backend/api/save-advanced-document.php
{
    "docId": 123,
    "content": "HTML content",
    "header_text": "Header text",
    "footer_text": "Footer text", 
    "page_numbering": true,
    "page_number_format": "page_x_of_y"
}

GET /backend/api/download-export.php?type=docx&doc_id=123
GET /backend/api/download-export.php?type=pdf&doc_id=123
                </div>
            </div>
        </div>
    </div>
    
    <script>
    const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
    let currentDocId = null;
    
    // Test 1: Crea documento
    async function testCreateDocument() {
        const output = document.getElementById('test1Output');
        output.innerHTML = '<div class="spinner-border"></div> Creazione documento...';
        
        try {
            const response = await fetch('backend/api/save-advanced-document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    title: document.getElementById('docTitle').value,
                    content: document.getElementById('docContent').value,
                    plainText: document.getElementById('docContent').value.replace(/<[^>]*>/g, ''),
                    header_text: document.getElementById('headerText').value,
                    footer_text: document.getElementById('footerText').value,
                    page_numbering: document.getElementById('enablePageNumbers').checked,
                    page_number_format: document.getElementById('pageNumberFormat').value
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                currentDocId = data.docId;
                output.innerHTML = `
                    <div class="success-msg">
                        ✅ Documento creato con successo!<br>
                        ID: ${data.docId}<br>
                        Versione: ${data.version || 1}
                    </div>
                `;
            } else {
                output.innerHTML = `<div class="error-msg">❌ Errore: ${data.error}</div>`;
            }
        } catch (error) {
            output.innerHTML = `<div class="error-msg">❌ Errore: ${error.message}</div>`;
        }
    }
    
    // Test Export DOCX
    async function testExportDocx() {
        if (!currentDocId) {
            alert('Prima crea un documento!');
            return;
        }
        
        window.open(`backend/api/download-export.php?type=docx&doc_id=${currentDocId}`, '_blank');
    }
    
    // Test Export PDF
    async function testExportPdf() {
        if (!currentDocId) {
            alert('Prima crea un documento!');
            return;
        }
        
        window.open(`backend/api/download-export.php?type=pdf&doc_id=${currentDocId}`, '_blank');
    }
    
    // Test 2: Import DOCX
    async function testImportDocx() {
        const output = document.getElementById('test2Output');
        const fileInput = document.getElementById('docxFile');
        
        if (!fileInput.files[0]) {
            alert('Seleziona un file DOCX!');
            return;
        }
        
        output.innerHTML = '<div class="spinner-border"></div> Analisi file...';
        
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        
        try {
            const response = await fetch('backend/api/import-docx.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                output.innerHTML = `
                    <div class="success-msg">
                        ✅ File importato con successo!<br>
                        <strong>Header:</strong> ${data.header || 'Non presente'}<br>
                        <strong>Footer:</strong> ${data.footer || 'Non presente'}<br>
                        <strong>Pagine:</strong> ${data.pages || 'N/A'}
                    </div>
                `;
            } else {
                output.innerHTML = `<div class="error-msg">❌ Errore: ${data.error}</div>`;
            }
        } catch (error) {
            output.innerHTML = `<div class="error-msg">❌ Errore: ${error.message}</div>`;
        }
    }
    
    // Test 3: Carica versioni
    async function testLoadVersions() {
        const output = document.getElementById('test3Output');
        const docId = document.getElementById('versionDocId').value;
        
        output.innerHTML = '<div class="spinner-border"></div> Caricamento versioni...';
        
        try {
            const response = await fetch(`backend/api/document-versions-api.php?action=list&document_id=${docId}`);
            const data = await response.json();
            
            if (data.success && data.versions) {
                let html = '<h5>Versioni trovate:</h5><ul>';
                data.versions.forEach(v => {
                    const metadata = v.metadata ? JSON.parse(v.metadata) : {};
                    html += `
                        <li>
                            Versione ${v.version_number} - ${v.created_at}<br>
                            Header: ${metadata.header_text || 'N/A'}<br>
                            Footer: ${metadata.footer_text || 'N/A'}
                        </li>
                    `;
                });
                html += '</ul>';
                output.innerHTML = html;
            } else {
                output.innerHTML = `<div class="error-msg">Nessuna versione trovata</div>`;
            }
        } catch (error) {
            output.innerHTML = `<div class="error-msg">❌ Errore: ${error.message}</div>`;
        }
    }
    
    // Test salva con metadata
    async function testSaveWithMetadata() {
        const docId = document.getElementById('versionDocId').value;
        const output = document.getElementById('test3Output');
        
        output.innerHTML = '<div class="spinner-border"></div> Salvataggio...';
        
        try {
            const response = await fetch('backend/api/save-advanced-document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    docId: docId,
                    content: '<p>Contenuto aggiornato con metadata</p>',
                    header_text: 'Header v' + Date.now(),
                    footer_text: 'Footer v' + Date.now(),
                    page_numbering: true,
                    page_number_format: 'page_x_of_y',
                    settings: {
                        is_major_version: true
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                output.innerHTML = `
                    <div class="success-msg">
                        ✅ Metadata salvati con successo!<br>
                        Versione: ${data.version}
                    </div>
                `;
            } else {
                output.innerHTML = `<div class="error-msg">❌ Errore: ${data.error}</div>`;
            }
        } catch (error) {
            output.innerHTML = `<div class="error-msg">❌ Errore: ${error.message}</div>`;
        }
    }
    
    // Aggiorna preview
    function updatePreview() {
        document.getElementById('previewHeader').textContent = document.getElementById('headerText').value;
        document.getElementById('previewFooter').textContent = document.getElementById('footerText').value;
        
        const format = document.getElementById('pageNumberFormat').value;
        const enabled = document.getElementById('enablePageNumbers').checked;
        
        if (enabled) {
            switch(format) {
                case 'page_x':
                    document.getElementById('previewPageNum').textContent = 'Pagina 1';
                    break;
                case 'page_x_of_y':
                    document.getElementById('previewPageNum').textContent = 'Pagina 1 di 3';
                    break;
                case 'x_of_y':
                    document.getElementById('previewPageNum').textContent = '1 / 3';
                    break;
                case 'simple':
                    document.getElementById('previewPageNum').textContent = '1';
                    break;
            }
        } else {
            document.getElementById('previewPageNum').textContent = '';
        }
    }
    
    // Auto-update preview on input change
    document.getElementById('headerText').addEventListener('input', updatePreview);
    document.getElementById('footerText').addEventListener('input', updatePreview);
    document.getElementById('enablePageNumbers').addEventListener('change', updatePreview);
    document.getElementById('pageNumberFormat').addEventListener('change', updatePreview);
    </script>
</body>
</html>