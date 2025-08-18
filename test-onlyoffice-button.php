<?php
/**
 * Test OnlyOffice Button - Verifica del funzionamento del pulsante
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Test OnlyOffice Button';
include 'components/header.php';
?>

<style>
.test-container {
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.test-section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.test-section h2 {
    color: #333;
    margin-bottom: 15px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.test-button {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    margin: 10px;
    display: inline-block;
}

.test-button:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.log-output {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
    font-family: monospace;
    min-height: 100px;
    max-height: 300px;
    overflow-y: auto;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 10px;
}

.status-ok { background: #28a745; color: white; }
.status-error { background: #dc3545; color: white; }
.status-warning { background: #ffc107; color: black; }
</style>

<div class="page-header">
    <h1><i class="fas fa-vial"></i> Test OnlyOffice Button</h1>
    <div class="page-subtitle">Verifica del funzionamento del pulsante OnlyOffice</div>
</div>

<div class="test-container">
    <!-- Test 1: Pulsante semplice -->
    <div class="test-section">
        <h2>Test 1: Pulsante Semplice (Corretto)</h2>
        <p>Questo pulsante ha event.stopPropagation() e preventDefault()</p>
        <button class="test-button" onclick="testEditDocument1(event, 123)">
            <i class="fas fa-file-word"></i> Apri con OnlyOffice (Corretto)
        </button>
        <div id="log1" class="log-output"></div>
    </div>

    <!-- Test 2: Pulsante senza stopPropagation (Problema originale) -->
    <div class="test-section">
        <h2>Test 2: Pulsante Senza stopPropagation (Problema)</h2>
        <p>Questo simula il problema originale - senza stopPropagation</p>
        <div onclick="parentClickHandler()" style="display: inline-block; border: 2px dashed #dc3545; padding: 10px;">
            <button class="test-button" onclick="testEditDocument2(event, 124)">
                <i class="fas fa-file-word"></i> Apri OnlyOffice (Problema)
            </button>
        </div>
        <div id="log2" class="log-output"></div>
    </div>

    <!-- Test 3: Pulsante con la correzione applicata -->
    <div class="test-section">
        <h2>Test 3: Pulsante con Correzione Completa</h2>
        <p>Questo usa la funzione corretta con tutti i controlli</p>
        <div onclick="parentClickHandler()" style="display: inline-block; border: 2px dashed #28a745; padding: 10px;">
            <button class="test-button" onclick="testEditDocumentFixed(event, 125)">
                <i class="fas fa-file-word"></i> Apri OnlyOffice (Corretto)
            </button>
        </div>
        <div id="log3" class="log-output"></div>
    </div>

    <!-- Test 4: Link che simula il comportamento -->
    <div class="test-section">
        <h2>Test 4: Link invece di Button</h2>
        <p>Test con un link invece di button per vedere se c'è differenza</p>
        <a href="#" class="test-button" onclick="testEditDocumentFixed(event, 126); return false;">
            <i class="fas fa-file-word"></i> Apri OnlyOffice (Link)
        </a>
        <div id="log4" class="log-output"></div>
    </div>
</div>

<script>
// Funzione di logging
function addLog(logId, message, type = 'info') {
    const logDiv = document.getElementById(logId);
    const timestamp = new Date().toLocaleTimeString();
    const color = type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue';
    logDiv.innerHTML += `<div style="color: ${color}">[${timestamp}] ${message}</div>`;
    logDiv.scrollTop = logDiv.scrollHeight;
}

// Test 1: Funzione corretta con stopPropagation
function testEditDocument1(event, fileId) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    addLog('log1', 'Funzione chiamata con ID: ' + fileId, 'info');
    addLog('log1', 'stopPropagation e preventDefault applicati', 'success');
    addLog('log1', 'Tentativo apertura OnlyOffice...', 'info');
    
    // Simuliamo l'apertura
    try {
        const url = 'onlyoffice-editor.php?id=' + fileId;
        addLog('log1', 'URL generato: ' + url, 'info');
        window.open(url, '_blank');
        addLog('log1', 'Finestra aperta con successo!', 'success');
    } catch (e) {
        addLog('log1', 'Errore: ' + e.message, 'error');
    }
}

// Test 2: Funzione senza stopPropagation (problema)
function testEditDocument2(event, fileId) {
    // NO stopPropagation - questo causa il problema
    
    addLog('log2', 'Funzione chiamata con ID: ' + fileId, 'info');
    addLog('log2', 'ATTENZIONE: stopPropagation NON applicato!', 'error');
    addLog('log2', 'L\'evento si propagherà al parent...', 'error');
    
    try {
        const url = 'onlyoffice-editor.php?id=' + fileId;
        addLog('log2', 'URL generato: ' + url, 'info');
        window.open(url, '_blank');
        addLog('log2', 'Finestra aperta ma evento propagato!', 'error');
    } catch (e) {
        addLog('log2', 'Errore: ' + e.message, 'error');
    }
}

// Test 3: Funzione corretta completa
function testEditDocumentFixed(event, fileId) {
    // Previeni la propagazione dell'evento per evitare refresh
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    addLog('log3', 'Funzione FIXED chiamata con ID: ' + fileId, 'info');
    addLog('log3', 'Event handling corretto applicato', 'success');
    addLog('log3', 'Nessuna propagazione al parent', 'success');
    
    // Apri l'editor OnlyOffice in una nuova scheda
    console.log('Opening OnlyOffice editor for document ID:', fileId);
    
    try {
        const url = 'onlyoffice-editor.php?id=' + fileId;
        addLog('log3', 'Apertura: ' + url, 'info');
        window.open(url, '_blank');
        addLog('log3', '✓ OnlyOffice aperto correttamente!', 'success');
    } catch (e) {
        addLog('log3', 'Errore apertura: ' + e.message, 'error');
    }
}

// Handler del parent per dimostrare la propagazione
function parentClickHandler() {
    alert('ATTENZIONE: Click propagato al parent! Questo causerebbe un refresh della pagina.');
    // In un caso reale questo potrebbe causare navigazione o refresh
}

// Test iniziale al caricamento
document.addEventListener('DOMContentLoaded', function() {
    addLog('log1', 'Test 1 pronto - Con stopPropagation', 'info');
    addLog('log2', 'Test 2 pronto - Senza stopPropagation (problema)', 'info');
    addLog('log3', 'Test 3 pronto - Correzione completa', 'info');
    addLog('log4', 'Test 4 pronto - Link con return false', 'info');
});
</script>

<?php include 'components/footer.php'; ?>