<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$db = Database::getInstance();
$currentAzienda = $auth->getCurrentAzienda();

// Imposta il titolo della pagina
$pageTitle = 'Dashboard';

// Includi header
include 'components/header.php';
?>

<!-- Contenuto Dashboard -->
<div class="dashboard-container">
    <!-- Header Pagina -->
    <div class="content-header">
        <h1>Dashboard</h1>
        <div class="header-actions">
            <?php if ($auth->isSuperAdmin()): ?>
                <button class="btn btn-secondary" data-modal="switch-company-modal">
                    <i class="fas fa-building"></i>
                    <span class="hide-mobile">Cambia Azienda</span>
                </button>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="window.location.href='<?php echo APP_PATH; ?>/documento.php?action=nuovo'">
                <i class="fas fa-plus"></i>
                <span class="hide-mobile">Nuovo Documento</span>
            </button>
        </div>
    </div>
    
    <!-- Widget Statistiche -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon documents">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="documenti">0</div>
                <div class="stat-label">Documenti</div>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i> 12%
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon events">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="eventi">0</div>
                <div class="stat-label">Eventi</div>
            </div>
            <div class="stat-trend">
                <i class="fas fa-minus"></i> 0%
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon users">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="utenti">0</div>
                <div class="stat-label">Utenti</div>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i> 5%
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon tickets">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="tickets">0</div>
                <div class="stat-label">Tickets Aperti</div>
            </div>
            <div class="stat-trend down">
                <i class="fas fa-arrow-down"></i> 8%
            </div>
        </div>
    </div>
    
    <!-- Contenuto principale -->
    <div class="dashboard-grid">
        <!-- Documenti Recenti -->
        <div class="dashboard-widget">
            <div class="widget-header">
                <h2>Documenti Recenti</h2>
                <a href="<?php echo APP_PATH; ?>/documenti.php" class="widget-action">
                    <i class="fas fa-folder-open"></i>
                    <span>Tutti i Documenti</span>
                </a>
            </div>
            <div class="widget-content">
                <div class="document-list">
                    <div class="document-item">
                        <div class="document-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="document-info">
                            <h4>Manuale Qualità ISO 9001</h4>
                            <p>Aggiornato 2 ore fa</p>
                        </div>
                        <div class="document-actions">
                            <button class="btn-icon" data-tooltip="Visualizza">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon" data-tooltip="Download">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="document-item">
                        <div class="document-icon">
                            <i class="fas fa-file-word"></i>
                        </div>
                        <div class="document-info">
                            <h4>Procedura Gestione Non Conformità</h4>
                            <p>Aggiornato ieri</p>
                        </div>
                        <div class="document-actions">
                            <button class="btn-icon" data-tooltip="Visualizza">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon" data-tooltip="Download">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Prossimi Eventi -->
        <div class="dashboard-widget">
            <div class="widget-header">
                <h2>Prossimi Eventi</h2>
                <a href="<?php echo APP_PATH; ?>/calendario.php" class="widget-action">
                    Vedi calendario <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="widget-content">
                <div class="event-list">
                    <div class="event-item">
                        <div class="event-date">
                            <div class="date-day">15</div>
                            <div class="date-month">Gen</div>
                        </div>
                        <div class="event-info">
                            <h4>Audit Interno ISO 9001</h4>
                            <p><i class="fas fa-clock"></i> 09:00 - 12:00</p>
                            <p><i class="fas fa-map-marker-alt"></i> Sala Riunioni</p>
                        </div>
                    </div>
                    
                    <div class="event-item">
                        <div class="event-date">
                            <div class="date-day">22</div>
                            <div class="date-month">Gen</div>
                        </div>
                        <div class="event-info">
                            <h4>Formazione Sicurezza</h4>
                            <p><i class="fas fa-clock"></i> 14:30 - 17:30</p>
                            <p><i class="fas fa-map-marker-alt"></i> Aula Formazione</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Attività Recenti -->
        <div class="dashboard-widget full-width">
            <div class="widget-header">
                <h2>Attività Recenti</h2>
                <a href="<?php echo APP_PATH; ?>/log-attivita.php" class="widget-action">
                    Vedi tutte <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="widget-content">
                <div class="activity-timeline">
                    <div class="activity-item">
                        <div class="activity-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>Mario Rossi</strong> ha approvato il documento "Procedura Acquisti"</p>
                            <span class="activity-time">10 minuti fa</span>
                        </div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-icon info">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>Laura Bianchi</strong> ha creato l'evento "Riunione Qualità"</p>
                            <span class="activity-time">1 ora fa</span>
                        </div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-icon warning">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="activity-content">
                            <p><strong>Giuseppe Verdi</strong> ha modificato il documento "Manuale Operativo"</p>
                            <span class="activity-time">2 ore fa</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cambio Azienda -->
<?php if ($auth->isSuperAdmin()): ?>
<div id="switch-company-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Seleziona Azienda</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo APP_PATH; ?>/cambia-azienda.php">
                <div class="form-group">
                    <label for="azienda_id">Azienda</label>
                    <select name="azienda_id" id="azienda_id" class="form-control" required>
                        <option value="">-- Vista Globale --</option>
                        <?php
                        $stmt = $db->query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
                        while ($azienda = $stmt->fetch()):
                        ?>
                            <option value="<?php echo $azienda['id']; ?>">
                                <?php echo htmlspecialchars($azienda['nome']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Cambia</button>
                    <button type="button" class="btn btn-secondary modal-close">Annulla</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Script specifici per questa pagina
$inlineScript = '
// Animazione contatori
document.addEventListener("DOMContentLoaded", function() {
    // Simula caricamento dati
    setTimeout(() => {
        animateCounter("documenti", 247);
        animateCounter("eventi", 12);
        animateCounter("utenti", 34);
        animateCounter("tickets", 5);
    }, 500);
});

function animateCounter(id, target) {
    const element = document.querySelector(`[data-counter="${id}"]`);
    if (!element) return;
    
    let current = 0;
    const increment = Math.ceil(target / 50);
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = current;
    }, 20);
}
';

// Includi footer
include 'components/footer.php';
?> 