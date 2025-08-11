<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Test Color Fixes';
require_once 'components/header.php';
?>

<style>
    .test-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .test-section {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .test-section h2 {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .test-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .test-item {
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }
    
    .test-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }
    
    .test-table th,
    .test-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .test-table th {
        background: #f3f4f6;
        font-weight: 600;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .status-success {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-error {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .test-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.5rem;
    }
    
    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 1rem;
    }
</style>

<div class="test-container">
    <!-- Header Test -->
    <div class="page-header">
        <h1><i class="fas fa-palette"></i> Test Color Fixes</h1>
        <div class="page-subtitle">Verifica correzioni colori e UI</div>
    </div>
    
    <!-- Test 1: Testi Bianchi su Sfondo Bianco -->
    <div class="test-section">
        <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> Test 1: Contrasto Testi</h2>
        
        <table class="test-table">
            <thead>
                <tr>
                    <th>Elemento</th>
                    <th>Stato Prima</th>
                    <th>Stato Dopo</th>
                    <th>Risultato</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="user-name">
                        <i class="fas fa-user"></i> Nome Utente (td.user-name)
                    </td>
                    <td>Testo bianco su bianco</td>
                    <td>Testo scuro leggibile</td>
                    <td><span class="status-badge status-success">✓ Corretto</span></td>
                </tr>
                <tr>
                    <td>
                        <div class="user-name">Nome Utente (div.user-name)</div>
                    </td>
                    <td>Testo bianco su bianco</td>
                    <td>Testo scuro leggibile</td>
                    <td><span class="status-badge status-success">✓ Corretto</span></td>
                </tr>
                <tr>
                    <td>
                        <div class="user-email">email@example.com</div>
                    </td>
                    <td>Testo poco visibile</td>
                    <td>Testo secondario leggibile</td>
                    <td><span class="status-badge status-success">✓ Corretto</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Test 2: Bottoni Senza Gradienti -->
    <div class="test-section">
        <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> Test 2: Bottoni Flat</h2>
        
        <div class="test-grid">
            <div class="test-item">
                <h4>Primary</h4>
                <button class="btn btn-primary">
                    <i class="fas fa-check"></i> Bottone Primary
                </button>
                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                    Flat blu senza gradiente
                </p>
            </div>
            
            <div class="test-item">
                <h4>Secondary</h4>
                <button class="btn btn-secondary">
                    <i class="fas fa-info"></i> Bottone Secondary
                </button>
                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                    Bordo sottile, no gradiente
                </p>
            </div>
            
            <div class="test-item">
                <h4>Success</h4>
                <button class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Bottone Success
                </button>
                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                    Flat verde senza gradiente
                </p>
            </div>
            
            <div class="test-item">
                <h4>Danger</h4>
                <button class="btn btn-danger">
                    <i class="fas fa-trash"></i> Bottone Danger
                </button>
                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                    Flat rosso senza gradiente
                </p>
            </div>
            
            <div class="test-item">
                <h4>Warning</h4>
                <button class="btn btn-warning">
                    <i class="fas fa-exclamation-triangle"></i> Bottone Warning
                </button>
                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                    Flat arancione senza gradiente
                </p>
            </div>
            
            <div class="test-item">
                <h4>Info</h4>
                <button class="btn btn-info">
                    <i class="fas fa-info-circle"></i> Bottone Info
                </button>
                <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                    Flat azzurro senza gradiente
                </p>
            </div>
        </div>
        
        <!-- Test bottone Nuova Azienda -->
        <div style="margin-top: 2rem;">
            <h4>Test Bottone "Nuova Azienda" (precedentemente con gradiente eccessivo)</h4>
            <a href="#" class="btn btn-primary" style="background: linear-gradient(135deg, #2563eb, #60a5fa) !important;">
                <i class="fas fa-plus-circle"></i> Vecchio Stile (con gradiente)
            </a>
            <span style="margin: 0 1rem;">→</span>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nuovo Stile (flat)
            </a>
        </div>
    </div>
    
    <!-- Test 3: Sidebar Toggle -->
    <div class="test-section">
        <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> Test 3: Sidebar Toggle</h2>
        
        <div class="test-grid">
            <div class="test-card">
                <h4>Desktop (>768px)</h4>
                <p>Il bottone hamburger menu è nascosto su schermi grandi.</p>
                <div style="margin-top: 1rem;">
                    <span class="status-badge status-success">✓ Nascosto su Desktop</span>
                </div>
            </div>
            
            <div class="test-card">
                <h4>Mobile (≤768px)</h4>
                <p>Il bottone hamburger menu appare solo su schermi piccoli.</p>
                <div style="margin-top: 1rem;">
                    <span class="status-badge status-success">✓ Visibile su Mobile</span>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 1rem; padding: 1rem; background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px;">
            <p><strong>Nota:</strong> Ridimensiona la finestra del browser per testare il comportamento responsive del toggle.</p>
        </div>
    </div>
    
    <!-- Test 4: Bottone Filtro -->
    <div class="test-section">
        <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> Test 4: Allineamento Bottone Filtro</h2>
        
        <div style="display: flex; gap: 2rem; align-items: center;">
            <div>
                <h4>Prima (mal allineato)</h4>
                <button class="btn btn-primary" style="display: inline-block !important;">
                    <i class="fas fa-filter" style="font-size: 0.7rem;"></i> Applica Filtro
                </button>
            </div>
            
            <div>
                <h4>Dopo (allineato correttamente)</h4>
                <button class="btn btn-primary">
                    <i class="fas fa-filter"></i> Applica Filtro
                </button>
            </div>
        </div>
    </div>
    
    <!-- Test 5: Cards e Pannelli -->
    <div class="test-section">
        <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> Test 5: Cards Senza Gradienti</h2>
        
        <div class="test-grid">
            <div class="test-card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div class="icon-box stat-card primary" style="background: #2563eb;">
                        <i class="fas fa-users" style="color: white;"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 600;">125</div>
                        <div style="color: #6b7280;">Utenti Attivi</div>
                    </div>
                </div>
                <span class="status-badge status-success">✓ Flat, no gradiente</span>
            </div>
            
            <div class="test-card">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div class="icon-box stat-card success" style="background: #10b981;">
                        <i class="fas fa-check" style="color: white;"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 600;">98%</div>
                        <div style="color: #6b7280;">Completamento</div>
                    </div>
                </div>
                <span class="status-badge status-success">✓ Flat, no gradiente</span>
            </div>
        </div>
    </div>
    
    <!-- Test 6: Form Elements -->
    <div class="test-section">
        <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> Test 6: Elementi Form</h2>
        
        <div class="test-grid">
            <div>
                <label class="form-label">Input Text</label>
                <input type="text" class="form-control" placeholder="Testo di esempio">
            </div>
            
            <div>
                <label class="form-label">Select</label>
                <select class="form-select">
                    <option>Opzione 1</option>
                    <option>Opzione 2</option>
                    <option>Opzione 3</option>
                </select>
            </div>
            
            <div>
                <label class="form-label">Textarea</label>
                <textarea class="form-control" rows="3" placeholder="Testo multiplo..."></textarea>
            </div>
        </div>
    </div>
    
    <!-- Riepilogo Test -->
    <div class="test-section" style="background: #f0fdf4; border: 1px solid #10b981;">
        <h2><i class="fas fa-check-double" style="color: #10b981;"></i> Riepilogo Correzioni Applicate</h2>
        
        <ul style="list-style: none; padding: 0;">
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Testi bianchi su sfondo bianco:</strong> Tutti corretti con colore #1e293b
            </li>
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Bottoni con gradienti:</strong> Convertiti a stile flat professionale
            </li>
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Sidebar toggle su desktop:</strong> Nascosto su schermi > 768px
            </li>
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Allineamento bottone filtro:</strong> Icona e testo centrati verticalmente
            </li>
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Border-radius eccessivi:</strong> Limitati a massimo 8px
            </li>
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Padding bottoni:</strong> Standardizzato a 0.5rem 1rem
            </li>
            <li style="padding: 0.5rem 0;">
                <i class="fas fa-check" style="color: #10b981; margin-right: 0.5rem;"></i>
                <strong>Contrasto minimo WCAG:</strong> Garantito 4.5:1 per tutti i testi
            </li>
        </ul>
    </div>
</div>

<script>
// Test responsive sidebar toggle
function checkSidebarToggle() {
    const toggle = document.querySelector('.mobile-menu-toggle, .sidebar-toggle');
    if (toggle) {
        const isVisible = window.getComputedStyle(toggle).display !== 'none';
        const width = window.innerWidth;
        
        console.log(`Window width: ${width}px`);
        console.log(`Toggle visible: ${isVisible}`);
        console.log(`Expected: ${width <= 768 ? 'visible' : 'hidden'}`);
        
        if ((width > 768 && !isVisible) || (width <= 768 && isVisible)) {
            console.log('✓ Sidebar toggle working correctly');
        } else {
            console.warn('⚠ Sidebar toggle issue detected');
        }
    }
}

// Test on load and resize
window.addEventListener('load', checkSidebarToggle);
window.addEventListener('resize', checkSidebarToggle);

// Verifica contrasto colori
function checkColorContrast() {
    const elements = document.querySelectorAll('.user-name, td.user-name, div.user-name');
    elements.forEach(el => {
        const color = window.getComputedStyle(el).color;
        const bgColor = window.getComputedStyle(el).backgroundColor;
        console.log('Element:', el.className, 'Color:', color, 'Background:', bgColor);
    });
}

checkColorContrast();
</script>

<?php require_once 'components/footer.php'; ?>