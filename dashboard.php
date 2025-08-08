<?php
/**
 * Dashboard funzionante della piattaforma Nexio
 */

// Carica configurazione
require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/middleware/Auth.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

// Ottieni dati utente
$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? null;
$isSuperAdmin = $auth->isSuperAdmin();
$hasElevatedPrivileges = $auth->hasElevatedPrivileges();

// Se non ha privilegi elevati e non ha azienda, prova a recuperarla
if (!$hasElevatedPrivileges && !$aziendaId) {
    $stmt = db_query("
        SELECT a.* 
        FROM aziende a
        JOIN utenti_aziende ua ON a.id = ua.azienda_id
        WHERE ua.utente_id = ? AND a.stato = 'attiva'
        LIMIT 1
    ", [$user['id']]);
    
    if ($stmt) {
        $azienda = $stmt->fetch();
        if ($azienda) {
            $aziendaId = $azienda['id'];
            $currentAzienda = $azienda;
            // Imposta in sessione
            $_SESSION['azienda_id'] = $aziendaId;
        }
    }
}

// Gestione filtro azienda per super user
if ($hasElevatedPrivileges && isset($_GET['azienda_filter']) && $_GET['azienda_filter']) {
    $filteredAziendaId = intval($_GET['azienda_filter']);
    // Verifica che l'azienda esista
    $stmt = db_query("SELECT * FROM aziende WHERE id = ? AND stato = 'attiva'", [$filteredAziendaId]);
    if ($stmt && $stmt->rowCount() > 0) {
        $aziendaId = $filteredAziendaId;
        $currentAzienda = $stmt->fetch();
    }
}

// Calcola statistiche e dati dashboard
$stats = [];
$attivitaRecenti = [];
$scadenze = [];
$graficiData = [];
$tasks = [];

try {
    // Recupera i task assegnati
    if ($hasElevatedPrivileges) {
        $taskQuery = "
            SELECT t.*, u.nome as assegnato_nome, u.cognome as assegnato_cognome, 
                   a.nome as azienda_nome, ut.nome as creatore_nome, ut.cognome as creatore_cognome
            FROM tasks t
            LEFT JOIN utenti u ON t.assegnato_a = u.id
            LEFT JOIN utenti ut ON t.creato_da = ut.id
            LEFT JOIN aziende a ON t.azienda_id = a.id
            WHERE t.stato != 'completato'
        ";
        
        if ($aziendaId) {
            $taskQuery .= " AND t.azienda_id = ?";
            $stmt = db_query($taskQuery . " ORDER BY t.priorita DESC, t.data_scadenza ASC", [$aziendaId]);
        } else {
            $stmt = db_query($taskQuery . " ORDER BY t.priorita DESC, t.data_scadenza ASC");
        }
        
        $tasks = $stmt ? $stmt->fetchAll() : [];
    }
    
    // Statistiche base
    if ($hasElevatedPrivileges && !$aziendaId) {
        // Vista globale per utenti con privilegi elevati
        
        // Aziende attive (exclude cancelled ones to match the list view)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM aziende WHERE stato != 'cancellata'");
            $stats['aziende'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['aziende'] = 0;
        }

        // Documenti pubblicati
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE stato = 'pubblicato'");
            $stats['documenti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['documenti'] = 0;
        }

        // Eventi futuri
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM eventi WHERE data_inizio > NOW()");
            $stats['eventi'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['eventi'] = 0;
        }

        // Utenti attivi
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM utenti WHERE attivo = 1");
            $stats['utenti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['utenti'] = 0;
        }

        // Tickets aperti
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM tickets WHERE stato IN ('aperto', 'in_lavorazione')");
            $stats['tickets_aperti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['tickets_aperti'] = 0;
        }
        
        // File caricati - conta tutti i documenti (sostituito query file_path che non esiste)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM documenti");
            $stats['files'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['files'] = 0;
        }
        
        // Attività ultime 24 ore
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM log_attivita WHERE data_azione > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['attivita_24h'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['attivita_24h'] = 0;
        }
        
        // Spazio utilizzato (MB) - non disponibile senza colonna dimensione_file
        $stats['spazio_utilizzato'] = 0; // Colonna dimensione_file non esiste
    } else if ($aziendaId) {
        // Vista specifica azienda
        
        // Documenti pubblicati
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE azienda_id = ? AND stato = 'pubblicato'", [$aziendaId]);
            $stats['documenti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['documenti'] = 0;
        }

        // Eventi futuri
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM eventi WHERE data_inizio > NOW() AND azienda_id = ?", [$aziendaId]);
            $stats['eventi'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['eventi'] = 0;
        }

        // Conta utenti della propria azienda
        try {
            $stmt = db_query("
                SELECT COUNT(DISTINCT u.id) as count 
                FROM utenti u
                JOIN utenti_aziende ua ON u.id = ua.utente_id
                WHERE ua.azienda_id = ? AND u.attivo = 1
            ", [$aziendaId]);
            $stats['utenti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['utenti'] = 0;
        }

        // Tickets aperti
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM tickets WHERE stato IN ('aperto', 'in_lavorazione') AND azienda_id = ?", [$aziendaId]);
            $stats['tickets_aperti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['tickets_aperti'] = 0;
        }
        
        // File caricati dell'azienda - conta tutti i documenti (sostituito query file_path che non esiste)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE azienda_id = ?", [$aziendaId]);
            $stats['files'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['files'] = 0;
        }
        
        // Cartelle create
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM cartelle WHERE azienda_id = ?", [$aziendaId]);
            $stats['cartelle'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['cartelle'] = 0;
        }
        
        // Eventi questa settimana
        try {
            $stmt = db_query("
                SELECT COUNT(*) as count FROM eventi 
                WHERE azienda_id = ? 
                AND data_inizio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ", [$aziendaId]);
            $stats['eventi_settimana'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['eventi_settimana'] = 0;
        }
        
        // Tickets risolti questo mese
        try {
            $stmt = db_query("
                SELECT COUNT(*) as count FROM tickets 
                WHERE azienda_id = ? 
                AND stato = 'chiuso' 
                AND updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$aziendaId]);
            $stats['tickets_risolti_mese'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['tickets_risolti_mese'] = 0;
        }
        
        // Attività ultime 24 ore per l'azienda
        try {
            $stmt = db_query("
                SELECT COUNT(*) as count FROM log_attivita 
                WHERE azienda_id = ? 
                AND data_azione > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ", [$aziendaId]);
            $stats['attivita_24h'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['attivita_24h'] = 0;
        }
        
        // Spazio utilizzato dall'azienda (MB) - non disponibile senza colonna dimensione_file
        $stats['spazio_utilizzato'] = 0; // Colonna dimensione_file non esiste
    }

    // Documenti recenti
    if ($aziendaId) {
        $stmt = db_query("
            SELECT d.id, d.titolo, d.codice, d.data_creazione
            FROM documenti d
            WHERE d.azienda_id = :azienda_id 
            AND d.stato = 'pubblicato'
            ORDER BY d.data_creazione DESC
            LIMIT 5
        ", ['azienda_id' => $aziendaId]);
    } else {
        // Super admin senza azienda selezionata vede gli ultimi documenti di tutte le aziende
        $stmt = db_query("
            SELECT d.id, d.titolo, d.codice, d.data_creazione, a.nome as azienda_nome
            FROM documenti d
            JOIN aziende a ON d.azienda_id = a.id
            WHERE d.stato = 'pubblicato'
            ORDER BY d.data_creazione DESC
            LIMIT 5
        ");
    }

    $documenti_recenti = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Attività recenti - SOLO FILESYSTEM
    if ($aziendaId) {
        $stmt = db_query("
            SELECT l.*, u.nome, u.cognome 
            FROM log_attivita l
            LEFT JOIN utenti u ON l.utente_id = u.id
            WHERE l.azienda_id = ?
            AND l.entita_tipo = 'filesystem'
            ORDER BY l.data_azione DESC
            LIMIT 10
        ", [$aziendaId]);
    } else if ($hasElevatedPrivileges) {
        $stmt = db_query("
            SELECT l.*, u.nome, u.cognome, a.nome as azienda_nome
            FROM log_attivita l
            LEFT JOIN utenti u ON l.utente_id = u.id
            LEFT JOIN aziende a ON l.azienda_id = a.id
            WHERE l.entita_tipo = 'filesystem'
            ORDER BY l.data_azione DESC
            LIMIT 10
        ");
    }
    $attivitaRecenti = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Eventi prossimi
    if ($aziendaId) {
        $stmt = db_query("
            SELECT titolo, data_inizio, descrizione
            FROM eventi 
            WHERE data_inizio > NOW() AND azienda_id = :azienda_id
            ORDER BY data_inizio ASC 
            LIMIT 5
        ", ['azienda_id' => $aziendaId]);
    } else {
        // Super admin senza azienda selezionata vede i prossimi eventi di tutte le aziende
        $stmt = db_query("
            SELECT e.titolo, e.data_inizio, e.descrizione, a.nome as azienda_nome
            FROM eventi e
            JOIN aziende a ON e.azienda_id = a.id
            WHERE e.data_inizio > NOW()
            ORDER BY e.data_inizio ASC 
            LIMIT 5
        ");
    }

    $eventi_prossimi = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Tickets recenti - Gestione dinamica dei nomi dei campi
    $tickets_recenti = [];
    try {
        // Prima verifichiamo i nomi dei campi nella tabella tickets
        $checkStmt = db_query("SHOW COLUMNS FROM tickets");
        $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determina i campi corretti
        $titleField = in_array('titolo', $columns) ? 't.titolo' : 
                     (in_array('oggetto', $columns) ? 't.oggetto' : "'N/A'");
        
        $dateField = in_array('created_at', $columns) ? 't.created_at' : 
                    (in_array('data_creazione', $columns) ? 't.data_creazione' : 
                    (in_array('creato_il', $columns) ? 't.creato_il' : 'NOW()'));
        
        if ($aziendaId) {
            $stmt = db_query("
                SELECT t.id, $titleField as titolo, t.stato, t.priorita, $dateField as created_at,
                       u.nome as utente_nome, u.cognome as utente_cognome
                FROM tickets t
                LEFT JOIN utenti u ON t.utente_id = u.id
                WHERE t.stato IN ('aperto', 'in_lavorazione') AND t.azienda_id = ?
                ORDER BY $dateField DESC
                LIMIT 5
            ", [$aziendaId]);
        } else {
            // Super admin senza azienda selezionata vede i tickets di tutte le aziende
            $stmt = db_query("
                SELECT t.id, $titleField as titolo, t.stato, t.priorita, $dateField as created_at,
                       u.nome as utente_nome, u.cognome as utente_cognome,
                       a.nome as azienda_nome
                FROM tickets t
                LEFT JOIN utenti u ON t.utente_id = u.id
                LEFT JOIN aziende a ON t.azienda_id = a.id
                WHERE t.stato IN ('aperto', 'in_lavorazione')
                ORDER BY $dateField DESC
                LIMIT 5
            ");
        }

        $tickets_recenti = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        // Log errore specifico per i tickets
        error_log("Dashboard tickets error: " . $e->getMessage());
        $tickets_recenti = [];
    }

} catch (Exception $e) {
    // In caso di errore, usa valori di default
    error_log("Dashboard error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    error_log("User ID: " . ($user['id'] ?? 'null') . ", Azienda ID: " . ($aziendaId ?? 'null') . ", Is Super Admin: " . ($isSuperAdmin ? 'yes' : 'no'));
    
    // Inizializza valori default solo se non già impostati
    if (!isset($stats['aziende'])) $stats['aziende'] = 0;
    if (!isset($stats['documenti'])) $stats['documenti'] = 0;
    if (!isset($stats['eventi'])) $stats['eventi'] = 0;
    if (!isset($stats['utenti'])) $stats['utenti'] = 0;
    if (!isset($stats['tickets_aperti'])) $stats['tickets_aperti'] = 0;
    if (!isset($stats['files'])) $stats['files'] = 0;
    if (!isset($stats['cartelle'])) $stats['cartelle'] = 0;
    if (!isset($stats['eventi_settimana'])) $stats['eventi_settimana'] = 0;
    if (!isset($stats['tickets_risolti_mese'])) $stats['tickets_risolti_mese'] = 0;
    if (!isset($stats['attivita_24h'])) $stats['attivita_24h'] = 0;
    if (!isset($stats['spazio_utilizzato'])) $stats['spazio_utilizzato'] = 0;
    
    if (!isset($documenti_recenti)) $documenti_recenti = [];
    if (!isset($eventi_prossimi)) $eventi_prossimi = [];
    if (!isset($tickets_recenti)) $tickets_recenti = [];
}

// Carica lista aziende per super admin
$aziende_list = [];
if ($hasElevatedPrivileges) {
    $stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
    $aziende_list = $stmt ? $stmt->fetchAll() : [];
}

$pageTitle = 'Dashboard';
include dirname(__FILE__) . '/components/header.php';
require_once 'components/page-header.php';
?>

<!-- Chart.js per grafici -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/dashboard-clean.css">

<style>
/* Dashboard Styles - Clean & Modern */

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card-modern {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
    position: relative;
}

.stat-card-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-card-modern .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
    background: #f3f4f6;
}

.stat-card-modern .stat-value {
    font-size: 32px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1;
}

.stat-card-modern .stat-label {
    color: #6b7280;
    font-size: 14px;
    font-weight: 500;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.dashboard-panel {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
}

.panel-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
}

.panel-header h2 i {
    color: #2d5a9f;
}

.activity-item {
    display: flex;
    align-items: start;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    font-size: 16px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 500;
    color: #111827;
    margin-bottom: 2px;
    font-size: 14px;
}

.activity-meta {
    font-size: 13px;
    color: #6b7280;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.quick-action {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.25rem;
    text-align: center;
    text-decoration: none;
    color: #374151;
    transition: all 0.2s ease;
}

.quick-action:hover {
    background: white;
    border-color: #2d5a9f;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(45, 90, 159, 0.1);
}

.quick-action i {
    font-size: 28px;
    color: #2d5a9f;
    margin-bottom: 8px;
}

.quick-action span {
    display: block;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
}

/* Info Widget - Simplified */
.info-widget {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    padding: 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.info-widget-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #374151;
}

.info-widget-item i {
    font-size: 18px;
    color: #6b7280;
}

.info-widget-item span {
    font-size: 14px;
    font-weight: 500;
}

/* Workday Summary Table - Clean Design */
.workday-summary {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
}

.workday-summary h3 {
    color: #111827;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.workday-summary h3 i {
    color: #2d5a9f;
}

.workday-table-container {
    overflow-x: auto;
}

.workday-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.workday-table th {
    background: #f9fafb;
    color: #4b5563;
    font-weight: 600;
    text-align: left;
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.workday-table td {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.workday-table tbody tr:hover {
    background: #f9fafb;
}

.user-name {
    font-weight: 500;
    color: #111827;
}

.role-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.role-super_admin {
    background: #dbeafe;
    color: #1e40af;
}

.role-utente_speciale {
    background: #fef3c7;
    color: #92400e;
}

.role-admin {
    background: #e9d5ff;
    color: #6b21a8;
}

.text-center {
    text-align: center;
}

.text-success {
    color: #059669;
}

.text-warning {
    color: #d97706;
}

.text-muted {
    color: #9ca3af;
}

.activity-types {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.activity-badge {
    background: #f3f4f6;
    color: #4b5563;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}

.summary-row {
    background: #f9fafb;
    font-weight: 600;
}

.summary-row td {
    border-top: 2px solid #e5e7eb;
    border-bottom: none;
}

.btn-outline {
    background: white;
    color: #2d5a9f;
    border: 1px solid #2d5a9f;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-outline:hover {
    background: #2d5a9f;
    color: white;
}

/* Icon colors - More subtle */
.stat-icon-building { background: #ede9fe; color: #6b21a8; }
.stat-icon-file { background: #dbeafe; color: #1e40af; }
.stat-icon-users { background: #d1fae5; color: #047857; }
.stat-icon-calendar { background: #fef3c7; color: #92400e; }
.stat-icon-ticket { background: #fee2e2; color: #b91c1c; }
.stat-icon-disk { background: #f3f4f6; color: #4b5563; }

@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-overview {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .stats-overview {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .info-widget {
        flex-direction: column;
        align-items: start;
    }
    
    .workday-table {
        font-size: 12px;
    }
    
    .workday-table th,
    .workday-table td {
        padding: 8px;
    }
}
</style>

<?php
// Render header standardizzato
renderPageHeader('Dashboard', 'Panoramica generale del sistema', 'tachometer-alt');
?>

<!-- Info Widget -->
<div class="info-widget">
    <div class="info-widget-item">
        <i class="fas fa-clock"></i>
        <span id="current-time"><?php echo date('H:i - d/m/Y'); ?></span>
    </div>
    
    <?php if ($currentAzienda): ?>
    <div class="info-widget-item">
        <i class="fas fa-building"></i>
        <span><?php echo htmlspecialchars($currentAzienda['nome']); ?></span>
    </div>
    <?php endif; ?>
    
    <div class="info-widget-item">
        <i class="fas fa-user-shield"></i>
        <span>
            <?php 
            switch($user['ruolo']) {
                case 'super_admin': echo 'Super Admin'; break;
                case 'utente_speciale': echo 'Utente Speciale'; break;
                case 'admin': echo 'Amministratore'; break;
                case 'staff': echo 'Staff'; break;
                default: echo 'Utente'; break;
            }
            ?>
        </span>
    </div>
    
    <?php if ($hasElevatedPrivileges && isset($stats['attivita_24h'])): ?>
    <div class="info-widget-item">
        <i class="fas fa-chart-line"></i>
        <span><?php echo $stats['attivita_24h']; ?> attività oggi</span>
    </div>
    <?php endif; ?>
</div>

<?php if ($hasElevatedPrivileges): ?>
<!-- Filtro Aziende per Super User -->
<div class="dashboard-panel" style="margin-bottom: 1.5rem;">
    <div class="panel-header">
        <h2><i class="fas fa-filter"></i>Filtra per Azienda</h2>
    </div>
    <form method="GET" action="" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <select name="azienda_filter" class="form-control" style="max-width: 300px; height: 38px; border: 1px solid #e5e7eb; border-radius: 6px;">
            <option value="">-- Vista Globale --</option>
            <?php foreach ($aziende_list as $az): ?>
                <option value="<?php echo $az['id']; ?>" <?php echo (isset($_GET['azienda_filter']) && $_GET['azienda_filter'] == $az['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($az['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 20px; background: #2d5a9f; border: none; border-radius: 6px; color: white; font-weight: 500; cursor: pointer; transition: all 0.2s;">
            <i class="fas fa-search"></i> Applica Filtro
        </button>
        <?php if (isset($_GET['azienda_filter']) && $_GET['azienda_filter']): ?>
            <a href="?" class="btn btn-secondary" style="height: 38px; padding: 0 20px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                <i class="fas fa-times"></i> Rimuovi Filtro
            </a>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<?php 
// Riepilogo giornate task per super admin - MOVED UP TO BE SECOND ELEMENT
if ($isSuperAdmin) {
    try {
        // Carica riepilogo giornate per tutti gli utenti
        $workday_sql = "SELECT 
                        u.id, u.nome, u.cognome, u.ruolo,
                        COUNT(DISTINCT t.id) as num_task,
                        SUM(t.giornate_previste) as totale_giornate,
                        SUM(CASE WHEN t.stato = 'completato' THEN t.giornate_previste ELSE 0 END) as giornate_completate,
                        SUM(CASE WHEN t.stato IN ('assegnato', 'in_corso') THEN t.giornate_previste ELSE 0 END) as giornate_in_corso,
                        GROUP_CONCAT(DISTINCT t.attivita ORDER BY t.attivita) as attivita_types
                    FROM utenti u
                    LEFT JOIN task_calendario t ON u.id = t.utente_assegnato_id AND t.stato != 'annullato'
                    WHERE u.attivo = 1 AND u.ruolo IN ('super_admin', 'utente_speciale', 'admin')";
        
        if ($aziendaId) {
            $workday_sql .= " AND (t.azienda_id = ? OR t.azienda_id IS NULL)";
        }
        
        $workday_sql .= " GROUP BY u.id
                          HAVING num_task > 0 OR u.ruolo = 'super_admin'
                          ORDER BY totale_giornate DESC, u.nome, u.cognome";
        
        $stmt = $aziendaId ? db_query($workday_sql, [$aziendaId]) : db_query($workday_sql);
        $user_workdays = $stmt ? $stmt->fetchAll() : [];
        
        if (!empty($user_workdays)):
?>
<!-- Riepilogo giornate per tutti gli utenti (solo Super Admin) -->
<div class="workday-summary">
    <h3><i class="fas fa-users"></i> Riepilogo Giornate Assegnate per Utente</h3>
    
    <div class="workday-table-container">
        <table class="workday-table">
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Ruolo</th>
                    <th>Task Assegnati</th>
                    <th>Totale Giornate</th>
                    <th>Completate</th>
                    <th>In Corso</th>
                    <th>Attività</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totale_task_generale = 0;
                $totale_gg_generale = 0;
                $totale_gg_completate = 0;
                $totale_gg_in_corso = 0;
                
                foreach ($user_workdays as $user_data): 
                    $totale_task_generale += $user_data['num_task'];
                    $totale_gg_generale += $user_data['totale_giornate'] ?? 0;
                    $totale_gg_completate += $user_data['giornate_completate'] ?? 0;
                    $totale_gg_in_corso += $user_data['giornate_in_corso'] ?? 0;
                ?>
                <tr>
                    <td class="user-name">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($user_data['nome'] . ' ' . $user_data['cognome']) ?>
                    </td>
                    <td>
                        <span class="role-badge role-<?= $user_data['ruolo'] ?>">
                            <?= match($user_data['ruolo']) {
                                'super_admin' => 'Super Admin',
                                'utente_speciale' => 'Utente Speciale',
                                'admin' => 'Admin',
                                default => ucfirst($user_data['ruolo'])
                            } ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $user_data['num_task'] ?></td>
                    <td class="text-center">
                        <strong><?= number_format($user_data['totale_giornate'] ?? 0, 1, ',', '.') ?></strong> gg
                    </td>
                    <td class="text-center text-success">
                        <?= number_format($user_data['giornate_completate'] ?? 0, 1, ',', '.') ?> gg
                    </td>
                    <td class="text-center text-warning">
                        <?= number_format($user_data['giornate_in_corso'] ?? 0, 1, ',', '.') ?> gg
                    </td>
                    <td class="activity-types">
                        <?php if ($user_data['attivita_types']): ?>
                            <?php foreach (explode(',', $user_data['attivita_types']) as $attivita): ?>
                                <span class="activity-badge"><?= htmlspecialchars($attivita) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Nessuna</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user_data['num_task'] > 0): ?>
                        <a href="<?= APP_PATH ?>/calendario-eventi.php?view=list&filter_user=<?= $user_data['id'] ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-eye"></i> Visualizza Task
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="summary-row">
                    <td colspan="2"><strong>TOTALE</strong></td>
                    <td class="text-center"><strong><?= $totale_task_generale ?></strong></td>
                    <td class="text-center"><strong><?= number_format($totale_gg_generale, 1, ',', '.') ?></strong> gg</td>
                    <td class="text-center text-success"><strong><?= number_format($totale_gg_completate, 1, ',', '.') ?></strong> gg</td>
                    <td class="text-center text-warning"><strong><?= number_format($totale_gg_in_corso, 1, ',', '.') ?></strong> gg</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php 
        endif;
    } catch (Exception $e) {
        // Log error but don't show to user
        error_log("Dashboard task summary error: " . $e->getMessage());
    }
}
?>

<!-- Tickets Aperti - Moved here to be 3rd element -->
<div class="dashboard-panel" style="margin-bottom: 25px;">
    <div class="panel-header">
        <h2><i class="fas fa-headset" style="margin-right: 10px; color: #667eea;"></i>Tickets Aperti</h2>
        <a href="<?php echo APP_PATH; ?>/tickets.php" style="color: #667eea; font-size: 14px; text-decoration: none;">
            Tutti i tickets <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    
    <?php if (empty($tickets_recenti)): ?>
        <p style="color: #718096; text-align: center; padding: 40px 20px;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 10px; display: block;"></i>
            Nessun ticket aperto - Ottimo lavoro!
        </p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
            <?php foreach ($tickets_recenti as $ticket): ?>
                <?php
                $priorityColor = '#718096';
                $priorityBg = '#f3f4f6';
                switch($ticket['priorita']) {
                    case 'alta':
                        $priorityColor = '#dc2626';
                        $priorityBg = '#fee2e2';
                        break;
                    case 'media':
                        $priorityColor = '#f59e0b';
                        $priorityBg = '#fef3c7';
                        break;
                    case 'bassa':
                        $priorityColor = '#10b981';
                        $priorityBg = '#d1fae5';
                        break;
                }
                ?>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <h4 style="font-weight: 500; color: #111827; margin: 0; flex: 1; font-size: 15px;">
                            <?php echo htmlspecialchars($ticket['titolo']); ?>
                        </h4>
                        <span style="background: <?php echo $priorityBg; ?>; color: <?php echo $priorityColor; ?>; 
                                     padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                            <?php echo ucfirst($ticket['priorita']); ?>
                        </span>
                    </div>
                    <div style="font-size: 13px; color: #6b7280;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($ticket['utente_nome'] . ' ' . $ticket['utente_cognome']); ?> •
                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y', strtotime($ticket['created_at'])); ?>
                        <?php if (!$aziendaId && isset($ticket['azienda_nome'])): ?>
                            • <i class="fas fa-building"></i> <?php echo htmlspecialchars($ticket['azienda_nome']); ?>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <span style="background: #f3f4f6; color: #374151; padding: 3px 8px; 
                                     border-radius: 4px; font-size: 12px;">
                            <?php echo ucfirst($ticket['stato']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$aziendaId && !$hasElevatedPrivileges): ?>
<div class="dashboard-panel" style="text-align: center; padding: 60px 20px;">
    <i class="fas fa-building" style="font-size: 64px; color: #e53e3e; margin-bottom: 20px;"></i>
    <h2 style="color: #1a202c; margin-bottom: 15px;">Nessuna Azienda Associata</h2>
    <p style="color: #718096; max-width: 500px; margin: 0 auto;">Il tuo account non è ancora associato a nessuna azienda. Per poter utilizzare la piattaforma, contatta l'amministratore del sistema.</p>
</div>
<?php else: ?>

<!-- Statistiche Overview -->
<div class="stats-overview">
    <?php if ($hasElevatedPrivileges && !$aziendaId): ?>
    <!-- Vista globale con statistiche aggregate -->
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-building">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-value"><?php echo $stats['aziende'] ?? 0; ?></div>
        <div class="stat-label">Aziende Attive</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-file">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-value"><?php echo $stats['documenti'] ?? 0; ?></div>
        <div class="stat-label">Documenti Totali</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-users">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo $stats['utenti'] ?? 0; ?></div>
        <div class="stat-label">Utenti Totali</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-calendar">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-value"><?php echo $stats['eventi'] ?? 0; ?></div>
        <div class="stat-label">Eventi Futuri</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-ticket">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-value"><?php echo $stats['tickets_aperti'] ?? 0; ?></div>
        <div class="stat-label">Tickets Aperti</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-disk">
            <i class="fas fa-hdd"></i>
        </div>
        <div class="stat-value"><?php echo $stats['spazio_utilizzato'] ?? 0; ?> MB</div>
        <div class="stat-label">Spazio Utilizzato</div>
    </div>
    
    <?php else: ?>
    <!-- Vista azienda specifica -->
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-file">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-value"><?php echo $stats['documenti'] ?? 0; ?></div>
        <div class="stat-label">Documenti</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-building">
            <i class="fas fa-folder"></i>
        </div>
        <div class="stat-value"><?php echo $stats['files'] ?? 0; ?></div>
        <div class="stat-label">Files Caricati</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-calendar">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-value"><?php echo $stats['eventi_settimana'] ?? 0; ?></div>
        <div class="stat-label">Eventi questa Settimana</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-users">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo $stats['utenti'] ?? 0; ?></div>
        <div class="stat-label">Utenti Azienda</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-ticket">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-value"><?php echo $stats['tickets_aperti'] ?? 0; ?></div>
        <div class="stat-label">Tickets Aperti</div>
    </div>
    
    <div class="stat-card-modern">
        <div class="stat-icon stat-icon-users">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?php echo $stats['tickets_risolti_mese'] ?? 0; ?></div>
        <div class="stat-label">Tickets Risolti (30gg)</div>
    </div>
    <?php endif; ?>
</div>


<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Attività Recenti -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2><i class="fas fa-folder" style="margin-right: 10px; color: #667eea;"></i>Attività File System</h2>
            <a href="<?php echo APP_PATH; ?>/log-attivita.php" style="color: #667eea; font-size: 14px; text-decoration: none;">
                Vedi tutto <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php if (empty($attivitaRecenti)): ?>
            <p style="color: #718096; text-align: center; padding: 40px 20px;">
                <i class="fas fa-clock" style="font-size: 48px; color: #e2e8f0; margin-bottom: 10px; display: block;"></i>
                Nessuna attività recente
            </p>
        <?php else: ?>
            <?php foreach (array_slice($attivitaRecenti, 0, 5) as $attivita): ?>
                <div class="activity-item">
                    <?php
                    $iconClass = 'fas fa-info-circle';
                    $iconBg = '#e2e8f0';
                    $iconColor = '#718096';
                    
                    switch($attivita['entita_tipo']) {
                        case 'filesystem':
                            // Icone diverse per diverse azioni
                            if (strpos($attivita['azione'], 'folder') !== false) {
                                $iconClass = 'fas fa-folder';
                                $iconBg = '#fef3c7';
                                $iconColor = '#f59e0b';
                            } else {
                                $iconClass = 'fas fa-file';
                                $iconBg = '#dbeafe';
                                $iconColor = '#3b82f6';
                            }
                            break;
                        case 'documento':
                            $iconClass = 'fas fa-file-alt';
                            $iconBg = '#dbeafe';
                            $iconColor = '#3b82f6';
                            break;
                        case 'evento':
                            $iconClass = 'fas fa-calendar';
                            $iconBg = '#fef3c7';
                            $iconColor = '#f59e0b';
                            break;
                        case 'utente':
                            $iconClass = 'fas fa-user';
                            $iconBg = '#ede9fe';
                            $iconColor = '#8b5cf6';
                            break;
                        case 'ticket':
                            $iconClass = 'fas fa-ticket-alt';
                            $iconBg = '#fee2e2';
                            $iconColor = '#ef4444';
                            break;
                    }
                    ?>
                    <div class="activity-icon" style="background: <?php echo $iconBg; ?>;">
                        <i class="<?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>;"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">
                            <?php 
                            // Traduci azioni filesystem in italiano
                            $azioniTradotte = [
                                'create_folder' => 'Creata cartella',
                                'upload_file' => 'Caricato file',
                                'delete_file' => 'Eliminato file',
                                'delete_folder' => 'Eliminata cartella',
                                'move_file' => 'Spostato file',
                                'rename_file' => 'Rinominato file',
                                'rename_folder' => 'Rinominata cartella',
                                'download_file' => 'Scaricato file',
                                'view_file' => 'Visualizzato file'
                            ];
                            
                            $azioneTradotta = $azioniTradotte[$attivita['azione']] ?? $attivita['azione'];
                            
                            // Estrai nome file/cartella dai dettagli JSON
                            $dettagli = !empty($attivita['dettagli']) ? json_decode($attivita['dettagli'], true) : [];
                            $nomeElemento = $dettagli['nome'] ?? $dettagli['file_name'] ?? $dettagli['folder_name'] ?? '';
                            
                            echo htmlspecialchars($azioneTradotta);
                            if ($nomeElemento) {
                                echo ': <strong>' . htmlspecialchars($nomeElemento) . '</strong>';
                            }
                            ?>
                        </div>
                        <div class="activity-meta">
                            <?php echo htmlspecialchars($attivita['nome'] . ' ' . $attivita['cognome']); ?> • 
                            <?php echo date('d/m H:i', strtotime($attivita['data_azione'])); ?>
                            <?php if (!$aziendaId && isset($attivita['azienda_nome'])): ?>
                                • <?php echo htmlspecialchars($attivita['azienda_nome']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Azioni Rapide -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2><i class="fas fa-rocket" style="margin-right: 10px; color: #667eea;"></i>Azioni Rapide</h2>
        </div>
        
        <div class="quick-actions-grid">
            <a href="<?php echo APP_PATH; ?>/filesystem.php" class="quick-action">
                <i class="fas fa-folder-open"></i>
                <span>File Manager</span>
            </a>
            
            <a href="<?php echo APP_PATH; ?>/editor-tinymce-a4.php" class="quick-action">
                <i class="fas fa-file-plus"></i>
                <span>Nuovo Documento</span>
            </a>
            
            <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="quick-action">
                <i class="fas fa-calendar-plus"></i>
                <span>Nuovo Evento</span>
            </a>
            
            <a href="<?php echo APP_PATH; ?>/tickets.php?action=nuovo" class="quick-action">
                <i class="fas fa-headset"></i>
                <span>Apri Ticket</span>
            </a>
            
            <?php if ($hasElevatedPrivileges): ?>
            <a href="<?php echo APP_PATH; ?>/gestione-utenti.php" class="quick-action">
                <i class="fas fa-user-plus"></i>
                <span>Gestione Utenti</span>
            </a>
            
            <a href="<?php echo APP_PATH; ?>/aziende.php" class="quick-action">
                <i class="fas fa-building"></i>
                <span>Gestione Aziende</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Seconda riga: Documenti e Eventi -->
<div class="dashboard-grid" style="margin-top: 25px;">
    <!-- Documenti Recenti -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2><i class="fas fa-file-alt" style="margin-right: 10px; color: #667eea;"></i>Documenti Recenti</h2>
            <a href="<?php echo APP_PATH; ?>/filesystem.php" style="color: #667eea; font-size: 14px; text-decoration: none;">
                Vedi tutti <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php if (empty($documenti_recenti)): ?>
            <p style="color: #718096; text-align: center; padding: 40px 20px;">
                <i class="fas fa-file" style="font-size: 48px; color: #e2e8f0; margin-bottom: 10px; display: block;"></i>
                Nessun documento recente
            </p>
        <?php else: ?>
            <?php foreach ($documenti_recenti as $doc): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #dbeafe;">
                        <i class="fas fa-file-alt" style="color: #3b82f6;"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">
                            <?php echo htmlspecialchars($doc['titolo']); ?>
                        </div>
                        <div class="activity-meta">
                            <?php echo htmlspecialchars($doc['codice'] ?? 'N/A'); ?> •
                            <?php echo date('d/m/Y', strtotime($doc['data_creazione'])); ?>
                            <?php if (!$aziendaId && isset($doc['azienda_nome'])): ?>
                                • <?php echo htmlspecialchars($doc['azienda_nome']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?php echo APP_PATH; ?>/documento-view.php?id=<?php echo $doc['id']; ?>" 
                       style="color: #667eea; text-decoration: none; font-size: 14px;">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Eventi Prossimi -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2><i class="fas fa-calendar" style="margin-right: 10px; color: #667eea;"></i>Prossimi Eventi</h2>
            <a href="<?php echo APP_PATH; ?>/calendario-eventi.php" style="color: #667eea; font-size: 14px; text-decoration: none;">
                Calendario <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php if (empty($eventi_prossimi)): ?>
            <p style="color: #718096; text-align: center; padding: 40px 20px;">
                <i class="fas fa-calendar-times" style="font-size: 48px; color: #e2e8f0; margin-bottom: 10px; display: block;"></i>
                Nessun evento programmato
            </p>
        <?php else: ?>
            <?php foreach ($eventi_prossimi as $evento): ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #fef3c7;">
                        <i class="fas fa-calendar-check" style="color: #f59e0b;"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">
                            <?php echo htmlspecialchars($evento['titolo']); ?>
                        </div>
                        <div class="activity-meta">
                            <?php echo date('d/m/Y H:i', strtotime($evento['data_inizio'])); ?>
                            <?php if (!$aziendaId && isset($evento['azienda_nome'])): ?>
                                • <?php echo htmlspecialchars($evento['azienda_nome']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($evento['descrizione'])): ?>
                            <div style="font-size: 12px; color: #a0aec0; margin-top: 3px;">
                                <?php echo htmlspecialchars(substr($evento['descrizione'], 0, 60)); ?>...
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (false): // Disabilitato - mostra i task sbagliati dalla tabella tasks invece di task_calendario ?>
<!-- Sezione Task Assegnati per Super User -->
<div class="dashboard-panel" style="margin-top: 25px;">
    <div class="panel-header">
        <h2><i class="fas fa-tasks" style="margin-right: 10px; color: #667eea;"></i>Task Assegnati</h2>
        <a href="<?php echo APP_PATH; ?>/tasks.php" style="color: #667eea; font-size: 14px; text-decoration: none;">
            Gestione Task <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="table table-hover" style="width: 100%;">
            <thead>
                <tr>
                    <th>Titolo</th>
                    <th>Assegnato a</th>
                    <th>Azienda</th>
                    <th>Priorità</th>
                    <th>Scadenza</th>
                    <th>Stato</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($tasks, 0, 10) as $task): ?>
                    <?php
                    $priorityClass = '';
                    $priorityIcon = '';
                    switch($task['priorita']) {
                        case 'alta':
                            $priorityClass = 'text-danger';
                            $priorityIcon = 'fa-exclamation-circle';
                            break;
                        case 'media':
                            $priorityClass = 'text-warning';
                            $priorityIcon = 'fa-exclamation-triangle';
                            break;
                        case 'bassa':
                            $priorityClass = 'text-success';
                            $priorityIcon = 'fa-info-circle';
                            break;
                    }
                    
                    $statusClass = '';
                    switch($task['stato']) {
                        case 'nuovo':
                            $statusClass = 'badge-primary';
                            break;
                        case 'in_corso':
                            $statusClass = 'badge-warning';
                            break;
                        case 'in_attesa':
                            $statusClass = 'badge-secondary';
                            break;
                        case 'completato':
                            $statusClass = 'badge-success';
                            break;
                    }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($task['titolo']); ?></strong>
                            <?php if ($task['descrizione']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($task['descrizione'], 0, 50)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($task['assegnato_a']): ?>
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($task['assegnato_nome'] . ' ' . $task['assegnato_cognome']); ?>
                            <?php else: ?>
                                <span class="text-muted">Non assegnato</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($task['azienda_nome']): ?>
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($task['azienda_nome']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="<?php echo $priorityClass; ?>">
                            <i class="fas <?php echo $priorityIcon; ?>"></i> <?php echo ucfirst($task['priorita']); ?>
                        </td>
                        <td>
                            <?php if ($task['data_scadenza']): ?>
                                <?php
                                $scadenza = new DateTime($task['data_scadenza']);
                                $oggi = new DateTime();
                                $diff = $oggi->diff($scadenza);
                                $isScaduto = $scadenza < $oggi;
                                ?>
                                <span class="<?php echo $isScaduto ? 'text-danger' : ''; ?>">
                                    <i class="fas fa-calendar"></i> <?php echo $scadenza->format('d/m/Y'); ?>
                                    <?php if ($isScaduto): ?>
                                        <br><small class="text-danger">Scaduto da <?php echo $diff->days; ?> giorni</small>
                                    <?php elseif ($diff->days <= 3): ?>
                                        <br><small class="text-warning">Scade tra <?php echo $diff->days; ?> giorni</small>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $task['stato'])); ?></span>
                        </td>
                        <td>
                            <a href="<?php echo APP_PATH; ?>/task-dettaglio.php?id=<?php echo $task['id']; ?>" 
                               class="btn btn-sm btn-primary" title="Visualizza">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (count($tasks) > 10): ?>
        <div style="text-align: center; margin-top: 15px;">
            <a href="<?php echo APP_PATH; ?>/tasks.php" class="btn btn-primary">
                Visualizza tutti i <?php echo count($tasks); ?> task
            </a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>


<?php endif; // Fine del blocco if (!$aziendaId && !$isSuperAdmin) ?>

</main>
</div>

<script>
// Dashboard interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Subtle fade-in for cards
    const statCards = document.querySelectorAll('.stat-card-modern');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.3s ease-out';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 50);
    });
    
    // Fade-in for panels
    const panels = document.querySelectorAll('.dashboard-panel, .workday-summary');
    panels.forEach((panel, index) => {
        panel.style.opacity = '0';
        
        setTimeout(() => {
            panel.style.transition = 'opacity 0.4s ease-out';
            panel.style.opacity = '1';
        }, 300 + (index * 50));
    });
    
    // Auto refresh every 5 minutes
    setTimeout(() => {
        location.reload();
    }, 300000);
    
    // Update clock
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        const dateString = now.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            timeElement.textContent = timeString + ' - ' + dateString;
        }
    }
    
    setInterval(updateClock, 1000);
    updateClock();
});
</script>

</body>
</html> 