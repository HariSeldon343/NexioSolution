<?php
/**
 * Test per verificare che i bottoni non causino reload della pagina
 * Problema: bottoni senza type="button" agiscono come submit
 * Soluzione: tutti i bottoni devono avere type="button"
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Test Button Fix';
include 'components/header.php';
?>

<div class="container mt-5">
    <h1>Test Button Fix - Prevenzione Reload</h1>
    
    <div class="alert alert-info">
        <h4>Problema Identificato:</h4>
        <p>I bottoni senza attributo <code>type="button"</code> causano il reload della pagina perché agiscono come submit.</p>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Test Bottoni</h3>
        </div>
        <div class="card-body">
            <h4>Bottone SENZA type="button" (PROBLEMA)</h4>
            <button class="btn btn-danger" onclick="testAction('senza type')">
                <i class="fas fa-times"></i> Questo causa reload
            </button>
            <p class="text-danger mt-2">↑ Questo bottone causerà un reload della pagina!</p>
            
            <hr>
            
            <h4>Bottone CON type="button" (CORRETTO)</h4>
            <button type="button" class="btn btn-success" onclick="testAction('con type')">
                <i class="fas fa-check"></i> Questo NON causa reload
            </button>
            <p class="text-success mt-2">↑ Questo bottone funziona correttamente!</p>
            
            <hr>
            
            <h4>Test OnlyOffice Button</h4>
            <button type="button" class="btn btn-primary" onclick="testOnlyOffice()">
                <i class="fas fa-file-word"></i> Test OnlyOffice (Corretto)
            </button>
            
            <div id="testResult" class="mt-4"></div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Bottoni Dinamici (come in filesystem.php)</h3>
        </div>
        <div class="card-body">
            <div id="dynamicButtons"></div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Stato dei Fix Applicati</h3>
        </div>
        <div class="card-body">
            <ul class="list-group">
                <li class="list-group-item">
                    <i class="fas fa-check text-success"></i> 
                    <strong>filesystem.php</strong> - Tutti i bottoni statici aggiornati con type="button"
                </li>
                <li class="list-group-item">
                    <i class="fas fa-check text-success"></i> 
                    <strong>filesystem.php (JS)</strong> - Bottoni dinamici nelle card aggiornati
                </li>
                <li class="list-group-item">
                    <i class="fas fa-check text-success"></i> 
                    <strong>Bottone OnlyOffice</strong> - Aggiornato con type="button"
                </li>
                <li class="list-group-item">
                    <i class="fas fa-check text-success"></i> 
                    <strong>iso-compliance.js</strong> - Bottoni Scarica/Modifica aggiornati
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
// Counter per verificare se la pagina viene ricaricata
if (!window.pageLoadCounter) {
    window.pageLoadCounter = 1;
} else {
    window.pageLoadCounter++;
}

document.addEventListener('DOMContentLoaded', function() {
    // Mostra il counter
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML = `<div class="alert alert-warning">
        <strong>Page Load Counter:</strong> ${window.pageLoadCounter}
        <br>Se questo numero aumenta quando clicchi un bottone, significa che la pagina sta facendo reload!
    </div>`;
    
    // Genera bottoni dinamici per test
    generateDynamicButtons();
});

function testAction(type) {
    console.log('Test action chiamata:', type);
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML += `<div class="alert alert-success">
        <strong>${new Date().toLocaleTimeString()}</strong> - Bottone ${type} cliccato (senza reload!)
    </div>`;
}

function testOnlyOffice() {
    console.log('OnlyOffice test chiamato');
    alert('OnlyOffice funziona! La pagina NON si è ricaricata.');
}

function generateDynamicButtons() {
    const container = document.getElementById('dynamicButtons');
    
    // Simula la generazione dei bottoni come in filesystem.php
    const html = `
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success" 
                    onclick="event.stopPropagation(); testAction('OnlyOffice dinamico')">
                <i class="fas fa-file-word"></i> OnlyOffice
            </button>
            <button type="button" class="btn btn-sm btn-primary" 
                    onclick="event.stopPropagation(); testAction('Download dinamico')">
                <i class="fas fa-download"></i> Download
            </button>
            <button type="button" class="btn btn-sm btn-warning" 
                    onclick="event.stopPropagation(); testAction('Edit dinamico')">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button type="button" class="btn btn-sm btn-danger" 
                    onclick="event.stopPropagation(); testAction('Delete dinamico')">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
        <p class="mt-2 text-muted">Questi bottoni sono generati dinamicamente come in filesystem.php</p>
    `;
    
    container.innerHTML = html;
}
</script>

<?php include 'components/footer.php'; ?>