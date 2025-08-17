<?php
session_start();
require_once 'config.php';
require_once '../backend/config/config.php';
require_once '../backend/middleware/Auth.php';

$auth = Auth::getInstance();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Ottieni documento se in modalità edit
$documento = null;
$docId = $_GET['id'] ?? null;
if ($docId) {
    try {
        $stmt = db_query("SELECT * FROM documenti WHERE id = ? AND (azienda_id = ? OR azienda_id IS NULL)", 
                        [$docId, $currentAzienda['id'] ?? null]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log error
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <title><?php echo $documento ? 'Modifica' : 'Nuovo'; ?> Documento - Nexio Mobile</title>
    
    <?php echo base_url_meta(); ?>
    <?php echo js_config(); ?>
    
    <link rel="manifest" href="manifest.php">
    <link rel="icon" type="image/png" href="icons/icon-192x192.png">
    
    <!-- OnlyOffice Integration for Mobile -->
    <script>
        const USE_ONLYOFFICE = true;
        const documentId = '<?php echo $docId ?? ''; ?>';
    </script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: var(--dark);
            overflow-x: hidden;
        }
        
        /* Header */
        .header {
            background: var(--primary);
            color: white;
            padding: 12px 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .back-btn, .save-btn {
            background: none;
            border: none;
            color: white;
            padding: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .save-btn {
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
        }
        
        .save-btn:active {
            background: rgba(255,255,255,0.3);
        }
        
        .header-title {
            flex: 1;
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Content */
        .content {
            padding-top: 56px;
            padding-bottom: 20px;
            min-height: 100vh;
        }
        
        .form-container {
            padding: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            cursor: pointer;
        }
        
        /* Editor Container */
        .editor-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        /* OnlyOffice Mobile Overrides */
        .onlyoffice-container {
            border: none !important;
            border-radius: 8px !important;
        }
        
        .tox .tox-toolbar__primary {
            background: #f8fafc !important;
            border-bottom: 1px solid #e5e7eb !important;
            padding: 8px !important;
        }
        
        .tox .tox-tbtn {
            border-radius: 6px !important;
            margin: 2px !important;
        }
        
        .tox .tox-edit-area {
            padding: 12px !important;
        }
        
        .tox .tox-statusbar {
            display: none !important;
        }
        
        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .toast.show {
            opacity: 1;
        }
        
        .toast.success {
            background: var(--success);
        }
        
        .toast.error {
            background: var(--danger);
        }
        
        /* Action Buttons */
        .action-buttons {
            padding: 16px;
            display: flex;
            gap: 12px;
        }
        
        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:active {
            transform: scale(0.98);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 640px) {
            .tox .tox-toolbar__primary {
                flex-wrap: wrap !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <button class="back-btn" onclick="goBack()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </button>
        <h1 class="header-title"><?php echo $documento ? 'Modifica' : 'Nuovo'; ?> Documento</h1>
        <button class="save-btn" onclick="saveDocument()">
            Salva
        </button>
    </header>
    
    <!-- Content -->
    <div class="content">
        <form id="documentForm" class="form-container">
            <!-- Titolo -->
            <div class="form-group">
                <label class="form-label" for="titolo">Titolo del documento</label>
                <input type="text" id="titolo" name="titolo" class="form-input" 
                       value="<?php echo htmlspecialchars($documento['titolo'] ?? ''); ?>" 
                       placeholder="Inserisci il titolo..." required>
            </div>
            
            <!-- Tipo Documento -->
            <div class="form-group">
                <label class="form-label" for="tipo">Tipo documento</label>
                <select id="tipo" name="tipo" class="form-select">
                    <option value="generale">Documento Generale</option>
                    <option value="procedura">Procedura</option>
                    <option value="modulo">Modulo</option>
                    <option value="report">Report</option>
                    <option value="manuale">Manuale</option>
                </select>
            </div>
            
            <!-- Editor -->
            <div class="form-group">
                <label class="form-label">Contenuto</label>
                <div class="editor-container">
                    <textarea id="editor" name="contenuto"><?php echo htmlspecialchars($documento['contenuto_html'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Hidden fields -->
            <input type="hidden" id="doc_id" value="<?php echo $docId ?? ''; ?>">
        </form>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="saveDraft()">Salva Bozza</button>
            <button class="btn btn-primary" onclick="saveAndClose()">Salva e Chiudi</button>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toast" class="toast"></div>
    
    <script>
        // Redirect to OnlyOffice Editor for mobile
        function initializeEditor() {
            // Show migration notice
            const editorDiv = document.getElementById('editor');
            if (editorDiv) {
                editorDiv.innerHTML = `
                    <div style="padding: 20px; background: #e3f2fd; border-radius: 8px; text-align: center;">
                        <h4 style="margin: 0 0 10px 0; color: #1976d2;">Editor Aggiornato</h4>
                        <p style="margin: 0 0 15px 0; color: #424242;">L'editor mobile ora utilizza OnlyOffice per una migliore esperienza collaborativa.</p>
                        <p style="margin: 0; color: #757575;">Reindirizzamento automatico...</p>
                    </div>
                `;
                
                // Auto-redirect
                setTimeout(() => {
                    window.location.href = `../onlyoffice-editor.php?id=${documentId}`;
                }, 2000);
            }
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', initializeEditor);
        
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Save document
        async function saveDocument(asDraft = false) {
            const titolo = document.getElementById('titolo').value;
            const tipo = document.getElementById('tipo').value;
            const contenuto = document.getElementById('editor').innerHTML; // Using OnlyOffice now
            const docId = document.getElementById('doc_id').value;
            
            if (!titolo) {
                showToast('Inserisci un titolo', 'error');
                return false;
            }
            
            const formData = {
                titolo: titolo,
                tipo: tipo,
                contenuto_html: contenuto,
                stato: asDraft ? 'bozza' : 'pubblicato',
                doc_id: docId
            };
            
            try {
                const response = await fetch('../backend/api/save-document.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (!docId && result.id) {
                        document.getElementById('doc_id').value = result.id;
                    }
                    return true;
                } else {
                    throw new Error(result.error || 'Errore nel salvataggio');
                }
            } catch (error) {
                showToast('Errore: ' + error.message, 'error');
                return false;
            }
        }
        
        // Save as draft
        async function saveDraft(silent = false) {
            const saved = await saveDocument(true);
            if (saved && !silent) {
                showToast('Bozza salvata', 'success');
            }
        }
        
        // Save and close
        async function saveAndClose() {
            const saved = await saveDocument(false);
            if (saved) {
                showToast('Documento salvato', 'success');
                setTimeout(() => {
                    window.location.href = 'documenti.php';
                }, 1500);
            }
        }
        
        // Go back
        function goBack() {
            if (confirm('Vuoi salvare le modifiche prima di uscire?')) {
                saveAndClose();
            } else {
                history.back();
            }
        }
        
        // Prevent accidental navigation - handled by OnlyOffice
        // OnlyOffice has its own unsaved changes detection
    </script>
</body>
</html>