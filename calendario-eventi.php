<?php
/**
 * Calendario Eventi Unificato
 * Gestione completa eventi con viste calendario
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/EventInvite.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Se non c'è un'azienda selezionata e non è super admin, reindirizza
if (!$currentAzienda && !$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/seleziona-azienda.php');
}

$action = $_GET['action'] ?? 'calendar';
$id = intval($_GET['id'] ?? 0);
$view = $_GET['view'] ?? 'month'; // month, week, day, list
$date = $_GET['date'] ?? date('Y-m-d');

$message = '';
$error = '';

// Gestisci le diverse azioni
switch ($action) {
    case 'nuovo':
        if (!$auth->canManageEvents()) {
            $_SESSION['error'] = "Non hai i permessi per creare eventi";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $eventInvite = new EventInvite();
        
        // Ottieni l'ID dell'azienda corrente
        $aziendaId = null;
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        }
        
        // Carica lista utenti invitabili
        $utenti_disponibili = $eventInvite->getInvitableUsers($user['id'], $aziendaId);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                db_connection()->beginTransaction();
                
                // Valida i dati
                $titolo = sanitize_input($_POST['titolo'] ?? '');
                $descrizione = sanitize_input($_POST['descrizione'] ?? '');
                $data_inizio = $_POST['data_inizio'] ?? '';
                $ora_inizio = $_POST['ora_inizio'] ?? '';
                $data_fine = $_POST['data_fine'] ?? '';
                $ora_fine = $_POST['ora_fine'] ?? '';
                $luogo = sanitize_input($_POST['luogo'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? 'riunione');
                
                if (empty($titolo) || empty($data_inizio) || empty($ora_inizio)) {
                    throw new Exception("Titolo, data e ora di inizio sono obbligatori");
                }
                
                $datetime_inizio = $data_inizio . ' ' . $ora_inizio;
                $datetime_fine = empty($data_fine) || empty($ora_fine) ? 
                    $datetime_inizio : $data_fine . ' ' . $ora_fine;
                
                // Inserisci evento
                $stmt = db_query(
                    "INSERT INTO eventi (titolo, descrizione, data_inizio, data_fine, luogo, tipo, azienda_id, creato_da, creato_il) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$titolo, $descrizione, $datetime_inizio, $datetime_fine, $luogo, $tipo, $aziendaId, $user['id']]
                );
                
                $evento_id = db_connection()->lastInsertId();
                
                // Gestisci partecipanti invitati
                $partecipanti = $_POST['partecipanti'] ?? [];
                if (!empty($partecipanti)) {
                    foreach ($partecipanti as $utente_id) {
                        db_query(
                            "INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il) 
                             VALUES (?, ?, 'invitato', NOW())",
                            [$evento_id, $utente_id]
                        );
                    }
                    
                    // Invia notifiche email
                    if (!empty($_POST['invia_notifiche'])) {
                        $eventInvite->sendInvitations($evento_id, $partecipanti);
                    }
                }
                
                db_connection()->commit();
                $_SESSION['success'] = "Evento creato con successo";
                redirect(APP_PATH . '/calendario-eventi.php');
                
            } catch (Exception $e) {
                db_connection()->rollback();
                $error = "Errore durante la creazione dell'evento: " . $e->getMessage();
            }
        }
        break;
        
    case 'modifica':
        if (!$auth->canManageEvents()) {
            $_SESSION['error'] = "Non hai i permessi per modificare eventi";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Carica evento esistente
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$id]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            $_SESSION['error'] = "Evento non trovato";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Verifica permessi
        if (!$auth->canViewAllEvents() && $evento['creato_da'] != $user['id']) {
            $_SESSION['error'] = "Non hai i permessi per modificare questo evento";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $eventInvite = new EventInvite();
        $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        $utenti_disponibili = $eventInvite->getInvitableUsers($user['id'], $aziendaId);
        
        // Carica partecipanti attuali
        $stmt = db_query("SELECT utente_id FROM evento_partecipanti WHERE evento_id = ?", [$id]);
        $partecipanti_attuali = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                db_connection()->beginTransaction();
                
                $titolo = sanitize_input($_POST['titolo'] ?? '');
                $descrizione = sanitize_input($_POST['descrizione'] ?? '');
                $data_inizio = $_POST['data_inizio'] ?? '';
                $ora_inizio = $_POST['ora_inizio'] ?? '';
                $data_fine = $_POST['data_fine'] ?? '';
                $ora_fine = $_POST['ora_fine'] ?? '';
                $luogo = sanitize_input($_POST['luogo'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? 'riunione');
                
                if (empty($titolo) || empty($data_inizio) || empty($ora_inizio)) {
                    throw new Exception("Titolo, data e ora di inizio sono obbligatori");
                }
                
                $datetime_inizio = $data_inizio . ' ' . $ora_inizio;
                $datetime_fine = empty($data_fine) || empty($ora_fine) ? 
                    $datetime_inizio : $data_fine . ' ' . $ora_fine;
                
                // Aggiorna evento
                db_query(
                    "UPDATE eventi SET titolo = ?, descrizione = ?, data_inizio = ?, data_fine = ?, 
                     luogo = ?, tipo = ? WHERE id = ?",
                    [$titolo, $descrizione, $datetime_inizio, $datetime_fine, $luogo, $tipo, $id]
                );
                
                // Aggiorna partecipanti
                db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$id]);
                
                $partecipanti = $_POST['partecipanti'] ?? [];
                if (!empty($partecipanti)) {
                    foreach ($partecipanti as $utente_id) {
                        db_query(
                            "INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il) 
                             VALUES (?, ?, 'invitato', NOW())",
                            [$id, $utente_id]
                        );
                    }
                }
                
                db_connection()->commit();
                $_SESSION['success'] = "Evento aggiornato con successo";
                redirect(APP_PATH . '/calendario-eventi.php');
                
            } catch (Exception $e) {
                db_connection()->rollback();
                $error = "Errore durante l'aggiornamento dell'evento: " . $e->getMessage();
            }
        }
        break;
        
    case 'elimina':
        if (!$auth->canManageEvents()) {
            $_SESSION['error'] = "Non hai i permessi per eliminare eventi";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$id]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            $_SESSION['error'] = "Evento non trovato";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Verifica permessi
        if (!$auth->canViewAllEvents() && $evento['creato_da'] != $user['id']) {
            $_SESSION['error'] = "Non hai i permessi per eliminare questo evento";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        try {
            db_connection()->beginTransaction();
            
            // Elimina partecipanti
            db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$id]);
            
            // Elimina evento
            db_query("DELETE FROM eventi WHERE id = ?", [$id]);
            
            db_connection()->commit();
            $_SESSION['success'] = "Evento eliminato con successo";
            
        } catch (Exception $e) {
            db_connection()->rollback();
            $_SESSION['error'] = "Errore durante l'eliminazione dell'evento: " . $e->getMessage();
        }
        
        redirect(APP_PATH . '/calendario-eventi.php');
        break;
}

// Carica eventi per la vista calendario
function getEventsForView($view, $date, $user, $auth) {
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Filtro per azienda se non super admin
    if (!$auth->canViewAllEvents()) {
        $currentAzienda = $auth->getCurrentAzienda();
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (e.azienda_id = ? OR e.creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
    }
    
    // Filtro per data basato sulla vista
    switch ($view) {
        case 'day':
            $whereClause .= " AND DATE(e.data_inizio) = ?";
            $params[] = $date;
            break;
            
        case 'week':
            $startWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $endWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $whereClause .= " AND DATE(e.data_inizio) BETWEEN ? AND ?";
            $params[] = $startWeek;
            $params[] = $endWeek;
            break;
            
        case 'month':
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $whereClause .= " AND YEAR(e.data_inizio) = ? AND MONTH(e.data_inizio) = ?";
            $params[] = $year;
            $params[] = $month;
            break;
    }
    
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   COUNT(ep.id) as num_partecipanti
            FROM eventi e 
            LEFT JOIN utenti u ON e.creato_da = u.id 
            LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
            $whereClause
            GROUP BY e.id
            ORDER BY e.data_inizio ASC";
    
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

$eventi = getEventsForView($view, $date, $user, $auth);

$pageTitle = 'Calendario Eventi';
include dirname(__FILE__) . '/components/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-calendar-alt"></i> Calendario Eventi</h1>
    <div class="header-actions">
        <div class="export-dropdown">
            <button class="btn btn-secondary dropdown-toggle" onclick="toggleExportDropdown()">
                <i class="fas fa-download"></i> Esporta ICS
            </button>
            <div class="dropdown-menu" id="exportDropdown">
                <a href="esporta-calendario.php?tipo=calendario&periodo=mese" class="dropdown-item">
                    <i class="fas fa-calendar"></i> Questo mese
                </a>
                <a href="esporta-calendario.php?tipo=calendario&periodo=trimestre" class="dropdown-item">
                    <i class="fas fa-calendar-alt"></i> Questo trimestre
                </a>
                <a href="esporta-calendario.php?tipo=calendario&periodo=anno" class="dropdown-item">
                    <i class="fas fa-calendar-check"></i> Questo anno
                </a>
                <a href="esporta-calendario.php?tipo=calendario&periodo=tutto" class="dropdown-item">
                    <i class="fas fa-download"></i> Tutti gli eventi
                </a>
            </div>
        </div>
        <?php if ($auth->canManageEvents()): ?>
        <a href="?action=nuovo" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuovo Evento
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Controlli vista calendario -->
<div class="calendar-controls">
    <div class="view-controls">
        <a href="?view=day&date=<?= $date ?>" class="btn btn-sm <?= $view === 'day' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-calendar-day"></i> Giorno
        </a>
        <a href="?view=week&date=<?= $date ?>" class="btn btn-sm <?= $view === 'week' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-calendar-week"></i> Settimana
        </a>
        <a href="?view=month&date=<?= $date ?>" class="btn btn-sm <?= $view === 'month' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-calendar"></i> Mese
        </a>
        <a href="?view=list&date=<?= $date ?>" class="btn btn-sm <?= $view === 'list' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-list"></i> Lista
        </a>
    </div>
    
    <div class="date-navigation">
        <?php
        $prevDate = date('Y-m-d', strtotime($date . ' -1 ' . ($view === 'month' ? 'month' : ($view === 'week' ? 'week' : 'day'))));
        $nextDate = date('Y-m-d', strtotime($date . ' +1 ' . ($view === 'month' ? 'month' : ($view === 'week' ? 'week' : 'day'))));
        ?>
        <a href="?view=<?= $view ?>&date=<?= $prevDate ?>" class="btn btn-sm btn-outline">
            <i class="fas fa-chevron-left"></i>
        </a>
        <span class="current-date">
            <?php
            switch ($view) {
                case 'day':
                    echo date('d/m/Y', strtotime($date));
                    break;
                case 'week':
                    $startWeek = date('d/m', strtotime('monday this week', strtotime($date)));
                    $endWeek = date('d/m/Y', strtotime('sunday this week', strtotime($date)));
                    echo "$startWeek - $endWeek";
                    break;
                case 'month':
                    echo date('F Y', strtotime($date));
                    break;
                case 'list':
                    echo 'Tutti gli eventi';
                    break;
            }
            ?>
        </span>
        <a href="?view=<?= $view ?>&date=<?= $nextDate ?>" class="btn btn-sm btn-outline">
            <i class="fas fa-chevron-right"></i>
        </a>
        <a href="?view=<?= $view ?>&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-home"></i> Oggi
        </a>
    </div>
</div>

<!-- Visualizzazione eventi -->
<div class="calendar-container">
    <?php if ($action === 'nuovo' || $action === 'modifica'): ?>
        <!-- Form per nuovo/modifica evento -->
        <?php include 'components/evento-form.php'; ?>
    <?php else: ?>
        <!-- Vista calendario -->
        <?php
        switch ($view) {
            case 'day':
                include 'components/calendar-day-view.php';
                break;
            case 'week':
                include 'components/calendar-week-view.php';
                break;
            case 'month':
                include 'components/calendar-month-view.php';
                break;
            case 'list':
            default:
                include 'components/calendar-list-view.php';
                break;
        }
        ?>
    <?php endif; ?>
</div>

<style>
/* Stili specifici per calendario eventi */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.calendar-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.view-controls {
    display: flex;
    gap: 10px;
}

.date-navigation {
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-date {
    font-weight: 600;
    color: #2d3748;
    min-width: 150px;
    text-align: center;
}

.calendar-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: #4299e1;
    color: white;
}

.btn-primary:hover {
    background: #3182ce;
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

.btn-outline {
    background: white;
    color: #4299e1;
    border: 1px solid #4299e1;
}

.btn-outline:hover {
    background: #4299e1;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Dropdown per esportazione */
.export-dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle::after {
    content: '▼';
    margin-left: 6px;
    font-size: 10px;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    z-index: 1000;
    display: none;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    color: #2d3748;
    text-decoration: none;
    font-size: 14px;
    border-bottom: 1px solid #f7fafc;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f7fafc;
    color: #2d3748;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item i {
    margin-right: 8px;
    width: 16px;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

@media (max-width: 768px) {
    .calendar-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .content-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .header-actions {
        flex-wrap: wrap;
        width: 100%;
    }
    
    .view-controls {
        overflow-x: auto;
        width: 100%;
    }
    
    .date-navigation {
        justify-content: center;
    }
    
    .dropdown-menu {
        right: auto;
        left: 0;
    }
}
</style>

<script>
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.classList.toggle('show');
}

// Chiudi dropdown quando si clicca fuori
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('exportDropdown');
    const button = event.target.closest('.dropdown-toggle');
    
    if (!button && dropdown) {
        dropdown.classList.remove('show');
    }
});

// Chiudi dropdown quando si preme ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('exportDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }
});
</script>

<?php include dirname(__FILE__) . '/components/footer.php'; ?>