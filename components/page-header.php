<?php
/**
 * Component: Page Header Riutilizzabile
 * 
 * Utilizzo:
 * include 'components/page-header.php'; 
 * renderPageHeader($title, $subtitle, $icon, $actions);
 * 
 * @param string $title - Titolo principale della pagina
 * @param string $subtitle - Sottotitolo/descrizione (opzionale) 
 * @param string $icon - Classe FontAwesome per l'icona (opzionale)
 * @param array $actions - Array di azioni/bottoni (opzionale)
 */

function renderPageHeader($title, $subtitle = '', $icon = '', $actions = []) {
    $auth = Auth::getInstance();
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    
    // Determina il sottotitolo se non fornito
    if (empty($subtitle)) {
        if ($auth->isSuperAdmin()) {
            $subtitle = 'Benvenuto, ' . ($user['nome'] ?? 'Admin') . ' • Vista Globale';
        } elseif ($auth->hasRole('utente_speciale')) {
            $subtitle = 'Benvenuto, ' . ($user['nome'] ?? 'Utente') . ' • Utente Speciale';
        } else {
            $aziendaNome = $currentAzienda['nome'] ?? 'Azienda';
            $subtitle = 'Benvenuto, ' . ($user['nome'] ?? 'Utente') . ' • ' . $aziendaNome;
        }
    }
    
    echo '<div class="dashboard-header">';
    echo '<h1>';
    if (!empty($icon)) {
        echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
    }
    echo htmlspecialchars($title);
    echo '</h1>';
    echo '<div class="dashboard-subtitle">';
    echo htmlspecialchars($subtitle);
    echo '</div>';
    if (!empty($actions)) {
        echo '<div class="dashboard-actions">';
        foreach ($actions as $action) {
            if (isset($action['permission']) && !$auth->canAccess($action['permission'])) {
                continue; // Salta azione se non ha permessi
            }
            
            $href = $action['href'] ?? '#';
            $class = $action['class'] ?? 'btn btn-primary';
            $text = $action['text'] ?? 'Azione';
            $actionIcon = $action['icon'] ?? '';
            $onclick = isset($action['onclick']) ? ' onclick="' . htmlspecialchars($action['onclick']) . '"' : '';
            
            echo '<a href="' . htmlspecialchars($href) . '" class="' . htmlspecialchars($class) . '"' . $onclick . '>';
            if (!empty($actionIcon)) {
                echo '<i class="' . htmlspecialchars($actionIcon) . '"></i> ';
            }
            echo htmlspecialchars($text);
            echo '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin: -20px -20px 30px -20px;
    border-radius: 0 0 15px 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><polygon fill="rgba(255,255,255,0.1)" points="1000,0 1000,100 0,100"></polygon></svg>') no-repeat center bottom;
    background-size: 100% 100%;
    pointer-events: none;
}

.dashboard-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

.dashboard-header h1 i {
    margin-right: 15px;
    opacity: 0.9;
}

.dashboard-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
    font-weight: 400;
    position: relative;
    z-index: 1;
}

.dashboard-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.dashboard-actions .btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.dashboard-actions .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

.dashboard-actions .btn-primary {
    background: rgba(255,255,255,0.95);
    color: #667eea;
    border-color: transparent;
}

.dashboard-actions .btn-primary:hover {
    background: white;
    color: #5a67d8;
    transform: translateY(-2px);
}

.dashboard-actions .btn-success {
    background: rgba(72,187,120,0.9);
    border-color: rgba(72,187,120,0.5);
}

.dashboard-actions .btn-danger {
    background: rgba(245,101,101,0.9);
    border-color: rgba(245,101,101,0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-header {
        margin: -20px -10px 20px -10px;
        padding: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 2rem;
    }
    
    .dashboard-subtitle {
        font-size: 1rem;
    }
    
    .dashboard-actions {
        flex-direction: column;
        gap: 8px;
    }
    
    .dashboard-actions .btn {
        justify-content: center;
        width: 100%;
    }
}

@media (max-width: 480px) {
    .dashboard-header h1 {
        font-size: 1.75rem;
    }
    
    .dashboard-subtitle {
        font-size: 0.95rem;
    }
}
</style>