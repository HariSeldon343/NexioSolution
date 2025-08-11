<?php
/**
 * Test Page for Frontend Fixes
 * This page tests the duration formatter, button styling, and mobile sidebar
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Test Frontend Fixes';
require_once 'components/header.php';
?>

<style>
.test-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.test-section {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.test-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.test-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.test-label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 5px;
    font-size: 12px;
    text-transform: uppercase;
}

.test-value {
    font-size: 16px;
    color: #2d3748;
}

.original {
    color: #e53e3e;
    text-decoration: line-through;
}

.formatted {
    color: #38a169;
    font-weight: 600;
}
</style>

<div class="test-container">
    <h1><i class="fas fa-vial"></i> Test Frontend Fixes</h1>

    <!-- Duration Formatter Test -->
    <div class="test-section">
        <h2><i class="fas fa-clock"></i> Test Duration Formatter</h2>
        <p>Verifica che le durate vengano mostrate in formato leggibile italiano</p>
        
        <div class="test-grid">
            <div class="test-item">
                <div class="test-label">30 minuti</div>
                <div class="original">30 min</div>
                <div class="formatted duration-minutes" data-duration-minutes="30">30 min</div>
            </div>
            
            <div class="test-item">
                <div class="test-label">1 ora e 30 minuti</div>
                <div class="original">90 min</div>
                <div class="formatted duration-minutes" data-duration-minutes="90">90 min</div>
            </div>
            
            <div class="test-item">
                <div class="test-label">2 ore</div>
                <div class="original">120 min</div>
                <div class="formatted duration-minutes" data-duration-minutes="120">120 min</div>
            </div>
            
            <div class="test-item">
                <div class="test-label">1 giorno</div>
                <div class="original">1440 min</div>
                <div class="formatted duration-minutes" data-duration-minutes="1440">1440 min</div>
            </div>
            
            <div class="test-item">
                <div class="test-label">4 giorni (esempio del problema)</div>
                <div class="original">5760 min</div>
                <div class="formatted duration-minutes" data-duration-minutes="5760">5760 min</div>
            </div>
            
            <div class="test-item">
                <div class="test-label">1 settimana</div>
                <div class="original">10080 min</div>
                <div class="formatted duration-minutes" data-duration-minutes="10080">10080 min</div>
            </div>
        </div>

        <h3 style="margin-top: 30px;">Test Task Duration (giorni lavorativi)</h3>
        <div class="test-grid">
            <div class="test-item">
                <div class="test-label">Task di 1 giorno</div>
                <span class="task-duration">1 gg</span>
            </div>
            
            <div class="test-item">
                <div class="test-label">Task di 5 giorni</div>
                <span class="task-duration">5 gg</span>
            </div>
            
            <div class="test-item">
                <div class="test-label">Task di 10 giorni</div>
                <span class="task-duration">10 gg</span>
            </div>
        </div>
    </div>

    <!-- Button Styling Test -->
    <div class="test-section">
        <h2><i class="fas fa-mouse-pointer"></i> Test Button Styling</h2>
        <p>Verifica che i bottoni abbiano dimensioni corrette e siano ben leggibili</p>
        
        <div class="empty-state" style="margin: 30px 0;">
            <i class="fas fa-calendar-alt"></i>
            <h2>Nessun evento programmato</h2>
            <p>Non ci sono eventi futuri in calendario.</p>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crea il primo evento
            </a>
            <a href="#" class="btn btn-success" style="background: #10b981;">
                <i class="fas fa-tasks"></i> Assegna Task
            </a>
        </div>

        <h3>Altri bottoni di test</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
            <button class="btn btn-primary">
                <i class="fas fa-save"></i> Salva
            </button>
            <button class="btn btn-secondary">
                <i class="fas fa-times"></i> Annulla
            </button>
            <button class="btn btn-success">
                <i class="fas fa-check"></i> Conferma
            </button>
            <button class="btn btn-danger">
                <i class="fas fa-trash"></i> Elimina
            </button>
        </div>
    </div>

    <!-- Mobile Sidebar Test -->
    <div class="test-section">
        <h2><i class="fas fa-mobile-alt"></i> Test Mobile Sidebar</h2>
        <p>Riduci la finestra del browser sotto i 768px per vedere il menu mobile</p>
        
        <div class="alert alert-info">
            <strong>Test checklist:</strong>
            <ul style="margin: 10px 0;">
                <li>✓ Il pulsante hamburger menu appare su mobile</li>
                <li>✓ La sidebar si apre/chiude correttamente</li>
                <li>✓ L'overlay oscura il contenuto quando la sidebar è aperta</li>
                <li>✓ Cliccando sull'overlay si chiude la sidebar</li>
                <li>✓ Il tasto ESC chiude la sidebar</li>
            </ul>
        </div>
    </div>

    <!-- JavaScript Test Output -->
    <div class="test-section">
        <h2><i class="fas fa-code"></i> JavaScript Console Test</h2>
        <p>Verifica che tutte le funzioni JavaScript siano caricate correttamente</p>
        
        <div id="js-test-output" style="font-family: monospace; background: #2d3748; color: #68d391; padding: 20px; border-radius: 6px; margin-top: 20px;">
            <div>Checking JavaScript functions...</div>
        </div>
    </div>
</div>

<script>
// Test JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    const output = document.getElementById('js-test-output');
    let testResults = [];
    
    // Test DurationFormatter
    if (typeof DurationFormatter !== 'undefined') {
        testResults.push('✓ DurationFormatter loaded');
        
        // Test some formatting
        const test1 = DurationFormatter.formatMinutes(5760);
        testResults.push(`  - formatMinutes(5760) = "${test1}"`);
        
        const test2 = DurationFormatter.formatMinutesShort(90);
        testResults.push(`  - formatMinutesShort(90) = "${test2}"`);
        
        const test3 = DurationFormatter.formatWorkDays(5);
        testResults.push(`  - formatWorkDays(5) = "${test3}"`);
    } else {
        testResults.push('✗ DurationFormatter NOT loaded');
    }
    
    // Test formatAllDurations function
    if (typeof formatAllDurations === 'function') {
        testResults.push('✓ formatAllDurations function available');
        // Call it to format the test durations
        formatAllDurations();
    } else {
        testResults.push('✗ formatAllDurations function NOT available');
    }
    
    // Test sidebar mobile
    if (document.querySelector('.mobile-menu-toggle')) {
        testResults.push('✓ Mobile menu toggle button created');
    } else {
        testResults.push('✗ Mobile menu toggle button NOT created');
    }
    
    if (document.querySelector('.sidebar-overlay')) {
        testResults.push('✓ Sidebar overlay created');
    } else {
        testResults.push('✗ Sidebar overlay NOT created');
    }
    
    // Update output
    output.innerHTML = testResults.map(r => `<div>${r}</div>`).join('');
});
</script>

<?php require_once 'components/footer.php'; ?>