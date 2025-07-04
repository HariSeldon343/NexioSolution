<?php
/**
 * Editor TinyMCE - Alternativa stabile a OnlyOffice
 * Editor HTML avanzato per documenti collaborativi
 */

require_once __DIR__ . '/backend/config/config.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

$documento_id = $_GET['id'] ?? null;
$documento = null;

// Carica documento esistente se specificato
if ($documento_id) {
    try {
        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documento_id]);
        if ($stmt) {
            $documento = $stmt->fetch();
            if (!$documento) {
                die('Documento non trovato');
            }
        }
    } catch (Exception $e) {
        die('Errore caricamento documento: ' . $e->getMessage());
    }
}

$titolo = $documento ? $documento['titolo'] : 'Nuovo Documento';
$contenuto = $documento ? $documento['contenuto'] : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titolo) ?> - Editor Collaborativo</title>
    
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .header {
            background: #2b579a; color: white; padding: 15px 20px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 { font-size: 20px; font-weight: 600; }
        
        .header-actions {
            display: flex; gap: 10px; align-items: center;
        }
        
        .btn {
            background: white; color: #2b579a; border: none;
            padding: 8px 16px; border-radius: 4px; cursor: pointer;
            font-weight: 500; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s;
        }
        
        .btn:hover { background: #f0f0f0; transform: translateY(-1px); }
        .btn.success { background: #28a745; color: white; }
        .btn.success:hover { background: #218838; }
        
        .editor-container {
            height: calc(100vh - 70px);
            padding: 20px;
            background: white;
        }
        
        .status-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #f8f9fa; border-top: 1px solid #dee2e6;
            padding: 8px 20px; display: flex;
            justify-content: space-between; align-items: center;
            font-size: 12px; color: #666; height: 40px;
        }
        
        .status-item {
            display: flex; align-items: center; gap: 5px;
        }
        
        .notification {
            position: fixed; top: 20px; right: 20px; z-index: 1000;
            padding: 15px 20px; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(400px); transition: transform 0.3s;
        }
        
        .notification.show { transform: translateX(0); }
        .notification.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .notification.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-file-alt"></i>
            <?= htmlspecialchars($titolo) ?>
        </h1>
        
        <div class="header-actions">
            <button class="btn success" onclick="saveDocument()" id="saveBtn">
                <i class="fas fa-save"></i>
                Salva
            </button>
            
            <button class="btn" onclick="exportDocument()">
                <i class="fas fa-download"></i>
                Esporta
            </button>
            
            <a href="dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i>
                Dashboard
            </a>
        </div>
    </div>
    
    <!-- Editor -->
    <div class="editor-container">
        <textarea id="tinymce-editor"><?= htmlspecialchars($contenuto) ?></textarea>
    </div>
    
    <!-- Status bar -->
    <div class="status-bar">
        <div style="display: flex; gap: 20px;">
            <div class="status-item">
                <i class="fas fa-user"></i>
                <span><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome']) ?></span>
            </div>
            <div class="status-item">
                <i class="fas fa-clock"></i>
                <span id="currentTime"></span>
            </div>
        </div>
        
        <div style="display: flex; gap: 20px;">
            <div class="status-item" id="saveStatus">
                <i class="fas fa-save"></i>
                <span>Non salvato</span>
            </div>
            <div class="status-item" id="wordCount">
                <i class="fas fa-font"></i>
                <span>0 parole</span>
            </div>
        </div>
    </div>

    <script>
        let documentId = <?= $documento_id ? json_encode($documento_id) : 'null' ?>;
        let isModified = false;
        let lastSaveTime = null;
        let editor = null;
        
        // Inizializza TinyMCE
        tinymce.init({
            selector: '#tinymce-editor',
            height: 'calc(100vh - 160px)',
            menubar: true,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'autosave',
                'save', 'textpattern', 'emoticons', 'template', 'codesample'
            ],
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | ' +
                    'alignleft aligncenter alignright alignjustify | ' +
                    'bullist numlist outdent indent | removeformat | ' +
                    'table link image media | code preview fullscreen | help',
            content_style: `
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    font-size: 14px; 
                    line-height: 1.6; 
                    max-width: 21cm; 
                    margin: 0 auto; 
                    padding: 2cm; 
                    background: white;
                }
            `,
            autosave_interval: '30s',
            autosave_prefix: 'tinymce-autosave-{path}{query}',
            autosave_restore_when_empty: true,
            contextmenu: 'link image table',
            language: 'it',
            branding: false,
            promotion: false,
            setup: function (ed) {
                editor = ed;
                ed.on('change keyup', function () {
                    isModified = true;
                    updateWordCount();
                    updateSaveStatus();
                });
                
                ed.on('init', function() {
                    console.log('✅ TinyMCE Editor inizializzato');
                    showNotification('Editor caricato con successo!', 'success');
                    updateWordCount();
                    startTimers();
                });
            }
        });
        
        // Funzioni di salvataggio
        async function saveDocument() {
            if (!editor) {
                showNotification('Editor non ancora pronto', 'error');
                return;
            }
            
            const saveBtn = document.getElementById('saveBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            saveBtn.disabled = true;
            
            try {
                const content = editor.getContent();
                const title = document.querySelector('.header h1').textContent.trim().replace('📄 ', '');
                
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
                
                const result = await response.json();
                
                if (result.success) {
                    isModified = false;
                    lastSaveTime = new Date();
                    
                    if (!documentId && result.documento_id) {
                        documentId = result.documento_id;
                        // Aggiorna URL senza ricaricare
                        history.replaceState(null, '', `?id=${documentId}`);
                    }
                    
                    updateSaveStatus();
                    showNotification('💾 Documento salvato con successo!', 'success');
                } else {
                    throw new Error(result.error || 'Errore salvataggio');
                }
                
            } catch (error) {
                console.error('Errore salvataggio:', error);
                showNotification('❌ Errore nel salvataggio: ' + error.message, 'error');
            } finally {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        }
        
        function exportDocument() {
            if (!editor) return;
            
            const content = editor.getContent();
            const title = document.querySelector('.header h1').textContent.trim().replace('📄 ', '');
            
            // Crea un blob con il contenuto HTML
            const htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>${title}</title>
                    <style>
                        body { font-family: Arial, sans-serif; max-width: 21cm; margin: 0 auto; padding: 2cm; }
                    </style>
                </head>
                <body>
                    <h1>${title}</h1>
                    ${content}
                </body>
                </html>
            `;
            
            const blob = new Blob([htmlContent], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = title + '.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showNotification('📥 Documento esportato!', 'success');
        }
        
        // Utility functions
        function updateWordCount() {
            if (!editor) return;
            
            const content = editor.getContent({ format: 'text' });
            const words = content.trim() ? content.trim().split(/\s+/).length : 0;
            document.getElementById('wordCount').innerHTML = 
                `<i class="fas fa-font"></i><span>${words} parole</span>`;
        }
        
        function updateSaveStatus() {
            const saveStatus = document.getElementById('saveStatus');
            
            if (lastSaveTime) {
                const timeStr = lastSaveTime.toLocaleTimeString('it-IT', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                saveStatus.innerHTML = 
                    `<i class="fas fa-save" style="color: #28a745;"></i><span>Salvato: ${timeStr}</span>`;
            } else if (isModified) {
                saveStatus.innerHTML = 
                    `<i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i><span>Modifiche non salvate</span>`;
            } else {
                saveStatus.innerHTML = 
                    `<i class="fas fa-save"></i><span>Non salvato</span>`;
            }
        }
        
        function updateCurrentTime() {
            document.getElementById('currentTime').textContent = 
                new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        }
        
        function startTimers() {
            updateCurrentTime();
            setInterval(updateCurrentTime, 60000);
            
            // Auto-save ogni 2 minuti se modificato
            setInterval(() => {
                if (isModified && editor) {
                    console.log('Auto-save...');
                    saveDocument();
                }
            }, 120000);
        }
        
        function showNotification(message, type = 'info') {
            // Rimuovi notifiche esistenti
            document.querySelectorAll('.notification').forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px;">×</button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Event listeners
        window.addEventListener('beforeunload', function(e) {
            if (isModified) {
                e.preventDefault();
                e.returnValue = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
                return e.returnValue;
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveDocument();
            }
        });
    </script>
</body>
</html>