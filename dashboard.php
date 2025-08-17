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

        // Documenti totali (tutti gli stati tranne cestino)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE stato != 'cestino'");
            $stats['documenti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['documenti'] = 0;
        }

        // Eventi futuri (prossimi 30 giorni per essere più rilevante)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM eventi WHERE data_inizio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)");
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
        
        // Spazio utilizzato (MB) - calcola dalla colonna dimensione_file o file_size
        try {
            // Prima prova con dimensione_file, poi con file_size
            $stmt = db_query("SELECT SUM(COALESCE(dimensione_file, file_size, 0)) as total_bytes FROM documenti WHERE stato != 'cestino'");
            $totalBytes = $stmt && $stmt->rowCount() > 0 ? ($stmt->fetch()['total_bytes'] ?? 0) : 0;
            
            // Se non ci sono dati nel DB, calcola lo spazio reale su disco
            if ($totalBytes == 0) {
                $uploadsDir = __DIR__ . '/uploads/';
                if (is_dir($uploadsDir)) {
                    $totalBytes = 0;
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $totalBytes += $file->getSize();
                        }
                    }
                }
            }
            
            $stats['spazio_filesystem'] = round($totalBytes / 1048576, 2); // Convert bytes to MB
        } catch (Exception $e) {
            $stats['spazio_filesystem'] = 0;
        }
    } else if ($aziendaId) {
        // Vista specifica azienda
        
        // Documenti dell'azienda (tutti tranne cestino)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE azienda_id = ? AND stato != 'cestino'", [$aziendaId]);
            $stats['documenti'] = $stmt && $stmt->rowCount() > 0 ? $stmt->fetch()['count'] : 0;
        } catch (Exception $e) {
            $stats['documenti'] = 0;
        }

        // Eventi futuri dell'azienda (prossimi 30 giorni)
        try {
            $stmt = db_query("SELECT COUNT(*) as count FROM eventi WHERE azienda_id = ? AND data_inizio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)", [$aziendaId]);
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
        
        // Spazio filesystem dell'azienda (MB) - solo file caricati tramite filesystem
        try {
            // Calcola spazio solo dai file del filesystem (hanno file_path popolato)
            // Questi sono i file caricati tramite filesystem.php, non template o altri documenti
            $stmt = db_query("
                SELECT SUM(COALESCE(dimensione_file, file_size, 0)) as total_bytes 
                FROM documenti 
                WHERE azienda_id = ? 
                    AND stato != 'cestino'
                    AND file_path IS NOT NULL 
                    AND file_path != ''
            ", [$aziendaId]);
            $totalBytes = $stmt && $stmt->rowCount() > 0 ? ($stmt->fetch()['total_bytes'] ?? 0) : 0;
            
            // Se non ci sono dati nel DB, prova a calcolare dalla directory dell'azienda
            if ($totalBytes == 0 && $aziendaId) {
                $aziendaUploadsDir = __DIR__ . '/uploads/documenti/' . $aziendaId . '/';
                if (is_dir($aziendaUploadsDir)) {
                    $totalBytes = 0;
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($aziendaUploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $totalBytes += $file->getSize();
                        }
                    }
                }
            }
            
            $stats['spazio_filesystem'] = round($totalBytes / 1048576, 2); // Convert bytes to MB
        } catch (Exception $e) {
            $stats['spazio_filesystem'] = 0;
        }
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
    
    // File caricati recentemente - Prendi gli ultimi file dalla tabella documenti
    if ($aziendaId) {
        $stmt = db_query("
            SELECT d.*, u.nome, u.cognome,
                   COALESCE(d.file_size, 0) as dimensione_file,
                   COALESCE(d.mime_type, d.file_type, 'application/octet-stream') as tipo_mime
            FROM documenti d
            LEFT JOIN utenti u ON d.creato_da = u.id
            WHERE d.azienda_id = ?
            AND d.file_path IS NOT NULL
            ORDER BY d.data_creazione DESC
            LIMIT 10
        ", [$aziendaId]);
    } else if ($hasElevatedPrivileges) {
        $stmt = db_query("
            SELECT d.*, u.nome, u.cognome, a.nome as azienda_nome,
                   COALESCE(d.file_size, 0) as dimensione_file,
                   COALESCE(d.mime_type, d.file_type, 'application/octet-stream') as tipo_mime
            FROM documenti d
            LEFT JOIN utenti u ON d.creato_da = u.id
            LEFT JOIN aziende a ON d.azienda_id = a.id
            WHERE d.file_path IS NOT NULL
            ORDER BY d.data_creazione DESC
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
    if (!isset($stats['spazio_filesystem'])) $stats['spazio_filesystem'] = 0;
    
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
/* Dashboard Styles - Minimal & Clean */

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card-modern {
    background: white;
    border-radius: 4px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    transition: transform 0.15s ease;
    position: relative;
}

.stat-card-modern:hover {
    transform: translateY(-1px);
}

.stat-card-modern .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 1rem;
    background: transparent;
    border: 1px solid #e5e7eb;
}

.stat-card-modern .stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-card-modern .stat-label {
    color: #6b7280;
    font-size: 13px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 2rem;
}

.dashboard-panel {
    background: white;
    border-radius: 4px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f3f4f6;
}

.panel-header h2 {
    font-size: 16px;
    font-weight: 500;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.panel-header h2 i {
    color: #6b7280;
    font-size: 14px;
}

.activity-item {
    display: flex;
    align-items: start;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f9fafb;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    font-size: 14px;
    border: 1px solid #e5e7eb;
    background: white;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 400;
    color: #111827;
    margin-bottom: 2px;
    font-size: 14px;
}

.activity-meta {
    font-size: 12px;
    color: #9ca3af;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}

.quick-action {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 1.25rem;
    text-align: center;
    text-decoration: none;
    color: #374151;
    transition: border-color 0.15s ease;
}

.quick-action:hover {
    border-color: #2d5a9f;
}

.quick-action i {
    font-size: 20px;
    color: #6b7280;
    margin-bottom: 0.75rem;
}

.quick-action span {
    display: block;
    font-weight: 400;
    color: #374151;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

/* Info Widget - Minimal */
.info-widget {
    background: white;
    border: 1px solid #e5e7eb;
    padding: 1rem 1.5rem;
    border-radius: 4px;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
}

.info-widget-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
}

.info-widget-item i {
    font-size: 14px;
    color: #9ca3af;
}

.info-widget-item span {
    font-size: 13px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

/* Workday Summary Table - Minimal Design */
.workday-summary {
    background: white;
    border-radius: 4px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
}

.workday-summary h3 {
    color: #111827;
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.workday-summary h3 i {
    color: #6b7280;
    font-size: 14px;
}

.workday-table-container {
    overflow-x: auto;
}

.workday-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.workday-table th {
    background: white;
    color: #6b7280;
    font-weight: 400;
    text-align: left;
    padding: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.workday-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #f9fafb;
    vertical-align: middle;
    color: #374151;
}

.workday-table tbody tr:hover {
    background: #fafafa;
}

.user-name {
    font-weight: 500;
    color: #111827;
}

.role-badge {
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 10px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid;
    background: transparent;
}

.role-super_admin {
    border-color: #2d5a9f;
    color: #2d5a9f;
}

.role-utente_speciale {
    border-color: #d97706;
    color: #d97706;
}

.role-admin {
    border-color: #7c3aed;
    color: #7c3aed;
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
    background: white;
    color: #6b7280;
    padding: 2px 6px;
    border: 1px solid #e5e7eb;
    border-radius: 2px;
    font-size: 10px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.summary-row {
    background: white;
    font-weight: 500;
}

.summary-row td {
    border-top: 1px solid #e5e7eb;
    border-bottom: none;
    padding-top: 1rem;
}

.btn-outline {
    background: white;
    color: #6b7280;
    border: 1px solid #e5e7eb;
    padding: 4px 8px;
    border-radius: 2px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 400;
    transition: border-color 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.btn-outline:hover {
    border-color: #2d5a9f;
    color: #2d5a9f;
}

/* Icon colors - Minimal */
.stat-icon-building { 
    color: #2d5a9f !important; 
}
.stat-icon-file { 
    color: #2d5a9f !important; 
}
.stat-icon-users { 
    color: #2d5a9f !important; 
}
.stat-icon-calendar { 
    color: #2d5a9f !important; 
}
.stat-icon-ticket { 
    color: #2d5a9f !important; 
}
.stat-icon-disk { 
    color: #2d5a9f !important; 
}

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
        <select name="azienda_filter" class="form-control" >
            <option value="">-- Vista Globale --</option>
            <?php foreach ($aziende_list as $az): ?>
                <option value="<?php echo $az['id']; ?>" <?php echo (isset($_GET['azienda_filter']) && $_GET['azienda_filter'] == $az['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($az['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" >
            <i class="fas fa-search" style="font-size: 11px;"></i> Applica Filtro
        </button>
        <?php if (isset($_GET['azienda_filter']) && $_GET['azienda_filter']): ?>
            <a href="?" class="btn btn-secondary" >
                <i class="fas fa-times" style="font-size: 11px;"></i> Rimuovi Filtro
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
        <h2><i class="fas fa-headset" ></i>Tickets Aperti</h2>
        <a href="<?php echo APP_PATH; ?>/tickets.php" >
            Tutti i tickets <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    
    <?php if (empty($tickets_recenti)): ?>
        <p >
            <i class="fas fa-check-circle" ></i>
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
                        <h4 >
                            <?php echo htmlspecialchars($ticket['titolo']); ?>
                        </h4>
                        <span style="background: <?php echo $priorityBg; ?>; color: <?php echo $priorityColor; ?>; 
                                     padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                            <?php echo ucfirst($ticket['priorita']); ?>
                        </span>
                    </div>
                    <div >
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($ticket['utente_nome'] . ' ' . $ticket['utente_cognome']); ?> •
                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y', strtotime($ticket['created_at'])); ?>
                        <?php if (!$aziendaId && isset($ticket['azienda_nome'])): ?>
                            • <i class="fas fa-building"></i> <?php echo htmlspecialchars($ticket['azienda_nome']); ?>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <span >
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
    <i class="fas fa-building" ></i>
    <h2 >Nessuna Azienda Associata</h2>
    <p >Il tuo account non è ancora associato a nessuna azienda. Per poter utilizzare la piattaforma, contatta l'amministratore del sistema.</p>
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
        <div class="stat-label">Eventi (30gg)</div>
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
        <div class="stat-value"><?php echo number_format($stats['spazio_filesystem'] ?? 0, 1, ',', '.'); ?> MB</div>
        <div class="stat-label">Spazio Filesystem</div>
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
    <!-- File Caricati Recentemente -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2><i class="fas fa-cloud-upload-alt"></i>File Caricati Recentemente</h2>
            <a href="<?php echo APP_PATH; ?>/filesystem.php" >
                File Manager <i class="fas fa-arrow-right" style="font-size: 10px;"></i>
            </a>
        </div>
        
        <?php if (empty($attivitaRecenti)): ?>
            <p >
                <i class="fas fa-folder-open" ></i>
                Nessun file caricato recentemente
            </p>
        <?php else: ?>
            <?php 
            // Funzione per formattare la data in formato relativo italiano
            function formatRelativeDate($dateString) {
                $date = new DateTime($dateString);
                $now = new DateTime();
                $diff = $now->diff($date);
                
                if ($diff->y > 0) {
                    return $diff->y == 1 ? '1 anno fa' : $diff->y . ' anni fa';
                } elseif ($diff->m > 0) {
                    return $diff->m == 1 ? '1 mese fa' : $diff->m . ' mesi fa';
                } elseif ($diff->d > 0) {
                    if ($diff->d == 1) return 'ieri';
                    if ($diff->d < 7) return $diff->d . ' giorni fa';
                    $weeks = floor($diff->d / 7);
                    return $weeks == 1 ? '1 settimana fa' : $weeks . ' settimane fa';
                } elseif ($diff->h > 0) {
                    return $diff->h == 1 ? '1 ora fa' : $diff->h . ' ore fa';
                } elseif ($diff->i > 0) {
                    return $diff->i == 1 ? '1 minuto fa' : $diff->i . ' minuti fa';
                } else {
                    return 'adesso';
                }
            }
            
            // Funzione per formattare la dimensione del file
            function formatFileSize($bytes) {
                if ($bytes == 0) return 'N/A';
                $units = ['B', 'KB', 'MB', 'GB'];
                $factor = floor((strlen($bytes) - 1) / 3);
                return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
            }
            
            // Funzione per ottenere l'icona in base al tipo MIME
            function getFileIcon($mimeType) {
                $mimeMap = [
                    'application/pdf' => ['fas fa-file-pdf', '#dc2626', '#fee2e2'],
                    'application/msword' => ['fas fa-file-word', '#2563eb', '#dbeafe'],
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['fas fa-file-word', '#2563eb', '#dbeafe'],
                    'application/vnd.ms-excel' => ['fas fa-file-excel', '#059669', '#d1fae5'],
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['fas fa-file-excel', '#059669', '#d1fae5'],
                    'application/vnd.ms-powerpoint' => ['fas fa-file-powerpoint', '#dc2626', '#fee2e2'],
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['fas fa-file-powerpoint', '#dc2626', '#fee2e2'],
                    'text/plain' => ['fas fa-file-alt', '#6b7280', '#f3f4f6'],
                    'text/html' => ['fas fa-file-code', '#8b5cf6', '#ede9fe'],
                    'text/css' => ['fas fa-file-code', '#3b82f6', '#dbeafe'],
                    'application/javascript' => ['fas fa-file-code', '#f59e0b', '#fef3c7'],
                    'application/json' => ['fas fa-file-code', '#10b981', '#d1fae5'],
                    'application/zip' => ['fas fa-file-archive', '#6b7280', '#f3f4f6'],
                    'application/x-rar-compressed' => ['fas fa-file-archive', '#6b7280', '#f3f4f6'],
                    'application/x-7z-compressed' => ['fas fa-file-archive', '#6b7280', '#f3f4f6'],
                ];
                
                // Controlla per tipi di immagine
                if (strpos($mimeType, 'image/') === 0) {
                    return ['fas fa-file-image', '#10b981', '#d1fae5'];
                }
                
                // Controlla per tipi video
                if (strpos($mimeType, 'video/') === 0) {
                    return ['fas fa-file-video', '#dc2626', '#fee2e2'];
                }
                
                // Controlla per tipi audio
                if (strpos($mimeType, 'audio/') === 0) {
                    return ['fas fa-file-audio', '#8b5cf6', '#ede9fe'];
                }
                
                // Default per tipo sconosciuto
                return isset($mimeMap[$mimeType]) ? $mimeMap[$mimeType] : ['fas fa-file', '#6b7280', '#f3f4f6'];
            }
            ?>
            
            <?php foreach (array_slice($attivitaRecenti, 0, 8) as $file): ?>
                <?php
                $fileIcon = getFileIcon($file['tipo_mime']);
                $fileName = basename($file['file_path'] ?? $file['titolo'] ?? 'File senza nome');
                $fileSize = formatFileSize($file['dimensione_file']);
                $relativeDate = formatRelativeDate($file['data_creazione']);
                ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background: <?php echo $fileIcon[2]; ?>;">
                        <i class="<?php echo $fileIcon[0]; ?>" style="color: <?php echo $fileIcon[1]; ?>;"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">
                            <?php echo htmlspecialchars($fileName); ?>
                            <?php if ($fileSize != 'N/A'): ?>
                                <span >
                                    (<?php echo $fileSize; ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="activity-meta">
                            <i class="fas fa-user" style="font-size: 11px;"></i>
                            <?php echo htmlspecialchars($file['nome'] . ' ' . $file['cognome']); ?> • 
                            <i class="fas fa-clock" style="font-size: 11px;"></i>
                            <?php echo $relativeDate; ?>
                            <?php if (!$aziendaId && isset($file['azienda_nome'])): ?>
                                • <i class="fas fa-building" style="font-size: 11px;"></i> 
                                <?php echo htmlspecialchars($file['azienda_nome']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($file['file_path'])): ?>
                    <a href="<?php echo APP_PATH; ?>/backend/api/download-file.php?id=<?php echo $file['id']; ?>" 
                        
                       title="Scarica file">
                        <i class="fas fa-download"></i>
                    </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Azioni Rapide -->
    <div class="dashboard-panel">
        <div class="panel-header">
            <h2><i class="fas fa-rocket"></i>Azioni Rapide</h2>
        </div>
        
        <div class="quick-actions-grid">
            <a href="<?php echo APP_PATH; ?>/filesystem.php" class="quick-action">
                <i class="fas fa-folder-open"></i>
                <span>File Manager</span>
            </a>
            
            <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="quick-action">
                <i class="fas fa-calendar-plus"></i>
                <span>Nuovo Evento</span>
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


<?php if (false): // Disabilitato - mostra i task sbagliati dalla tabella tasks invece di task_calendario ?>
<!-- Sezione Task Assegnati per Super User -->
<div class="dashboard-panel" style="margin-top: 25px;">
    <div class="panel-header">
        <h2><i class="fas fa-tasks" ></i>Task Assegnati</h2>
        <a href="<?php echo APP_PATH; ?>/tasks.php" >
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

<script>
// Dashboard interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Subtle fade-in for cards
    const statCards = document.querySelectorAll('.stat-card-modern');
    statCards.forEach((card) => {
        if (card) {
            card.style.opacity = '1';
            card.style.transform = 'none';
        }
    });
    
    // Panels - keep static
    const panels = document.querySelectorAll('.dashboard-panel, .workday-summary');
    panels.forEach((panel) => {
        if (panel) {
            panel.style.opacity = '1';
        }
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

<?php include dirname(__FILE__) . '/components/footer.php'; ?> 