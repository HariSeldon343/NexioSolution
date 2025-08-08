<?php
require_once 'backend/config/config.php';
require_once 'backend/config/database.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$pageTitle = 'Nexio AI';

// Include standard header
require_once 'components/header.php';
require_once 'components/page-header.php';
?>

<style>
    .ai-container {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .ai-placeholder {
        text-align: center;
        padding: 40px;
        max-width: 600px;
    }
    
    .ai-placeholder i {
        font-size: 5rem;
        margin-bottom: 2rem;
        color: #007bff;
        opacity: 0.8;
    }
    
    .ai-placeholder h3 {
        font-size: 2rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 1rem;
    }
    
    .ai-placeholder p {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 2rem;
    }
    
    .ai-features {
        text-align: left;
        background: white;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-top: 30px;
    }
    
    .ai-features h5 {
        color: #333;
        margin-bottom: 20px;
    }
    
    .ai-features ul {
        list-style: none;
        padding: 0;
    }
    
    .ai-features li {
        padding: 10px 0;
        padding-left: 30px;
        position: relative;
        color: #666;
    }
    
    .ai-features li:before {
        content: '\f00c';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        left: 0;
        color: #28a745;
    }
    
    .coming-soon-badge {
        display: inline-block;
        background: #ffc107;
        color: #333;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
        margin-top: 20px;
    }
</style>

<?php renderPageHeader('Nexio AI Assistant', 'Assistente intelligente per documenti', 'robot'); ?>

<div class="ai-container">
    <div class="ai-placeholder">
        <i class="fas fa-brain"></i>
        <h4>Nexio AI Assistant</h4>
        <p>Il tuo assistente intelligente per ottimizzare la gestione aziendale</p>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Questa funzionalità è attualmente in fase di sviluppo
        </div>
        
        <div class="ai-features">
            <h5><i class="fas fa-magic me-2"></i>Funzionalità in arrivo:</h5>
            <ul>
                <li>Analisi predittiva dei dati aziendali</li>
                <li>Generazione automatica di report e documenti</li>
                <li>Suggerimenti intelligenti per l'ottimizzazione dei processi</li>
                <li>Chatbot per assistenza immediata</li>
                <li>Automazione task ripetitivi</li>
                <li>Insights basati su machine learning</li>
                <li>Integrazione con tutti i moduli Nexio</li>
            </ul>
        </div>
        
        <span class="coming-soon-badge">
            <i class="fas fa-rocket me-2"></i>Coming Soon
        </span>
    </div>
</div>

<?php
// Include standard footer
require_once 'components/footer.php';
?>