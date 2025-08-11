<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Se non c'è un'azienda selezionata e non è super admin, reindirizza
if (!$currentAzienda && !$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/seleziona-azienda.php');
}

// Ottieni la vista corrente (default: cards)
$view = $_GET['view'] ?? 'cards';
$date = $_GET['date'] ?? date('Y-m-d');
$currentDate = new DateTime($date);

// Imposta locale italiano per le date
setlocale(LC_TIME, 'it_IT.UTF-8', 'Italian_Italy.1252', 'it_IT', 'italian');

// Prepara query per eventi
$sql = "
    SELECT e.*, u.nome as nome_creatore, u.cognome as cognome_creatore, 
           a.nome as nome_azienda
    FROM eventi e
    LEFT JOIN utenti u ON e.creato_da = u.id
    LEFT JOIN aziende a ON e.azienda_id = a.id
    WHERE 1=1";

$params = [];

// Filtra per azienda se necessario
if ($currentAzienda) {
    $sql .= " AND e.azienda_id = :azienda_id";
    $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                 (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
    $params['azienda_id'] = $aziendaId;
} elseif (!$auth->isSuperAdmin()) {
    // Se non è super admin e non ha azienda selezionata, mostra solo i suoi eventi
    $userAziende = $auth->getUserAziende();
    if (!empty($userAziende)) {
        $aziendaIds = array_column($userAziende, 'azienda_id');
        $placeholders = array_map(function($i) { return ":azienda_$i"; }, array_keys($aziendaIds));
        $sql .= " AND e.azienda_id IN (" . implode(',', $placeholders) . ")";
        foreach ($aziendaIds as $i => $id) {
            $params["azienda_$i"] = $id;
        }
    } else {
        $sql .= " AND 1=0"; // Non mostrare nulla
    }
}
// Se è super admin senza azienda selezionata, mostra tutti gli eventi

$sql .= " ORDER BY e.data_inizio ASC";

$stmt = db_query($sql, $params);
$eventi = $stmt->fetchAll();

// Funzione per ottenere eventi per una data specifica
function getEventiPerData($eventi, $data) {
    $result = [];
    foreach ($eventi as $evento) {
        $dataEvento = date('Y-m-d', strtotime($evento['data_inizio']));
        if ($dataEvento == $data) {
            $result[] = $evento;
        }
    }
    return $result;
}

// Funzione per formattare data in italiano
function formatDateItalian($date) {
    $mesi = [
        1 => 'gennaio', 2 => 'febbraio', 3 => 'marzo', 4 => 'aprile',
        5 => 'maggio', 6 => 'giugno', 7 => 'luglio', 8 => 'agosto',
        9 => 'settembre', 10 => 'ottobre', 11 => 'novembre', 12 => 'dicembre'
    ];
    
    $giorni = [
        0 => 'domenica', 1 => 'lunedì', 2 => 'martedì', 3 => 'mercoledì',
        4 => 'giovedì', 5 => 'venerdì', 6 => 'sabato'
    ];
    
    $timestamp = strtotime($date);
    $giorno = $giorni[date('w', $timestamp)];
    $numero = date('j', $timestamp);
    $mese = $mesi[date('n', $timestamp)];
    $anno = date('Y', $timestamp);
    
    return ucfirst($giorno) . ' ' . $numero . ' ' . $mese . ' ' . $anno;
}

$pageTitle = 'Calendario';
require_once 'components/header.php';
require_once 'components/page-header.php';

// Gestione messaggi di successo e errore
$successMessage = $_GET['msg'] ?? null;
$errorMessage = $_GET['error'] ?? null;
?>

<style>
    .view-selector {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        background: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .view-btn {
        padding: 8px 16px;
        border: 1px solid #e5e7eb;
        background: white;
        color: #4a5568;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .view-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    
    .view-btn.active {
        background: #6366f1;
        color: white;
        border-color: #6366f1;
    }
    
    .calendar-navigation {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        background: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .nav-date {
        font-size: 20px;
        font-weight: 600;
        color: #2d3748;
    }
    
    .nav-buttons {
        display: flex;
        gap: 10px;
    }
    
    /* Vista giornaliera - Contrasto migliorato */
    .day-view {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 1px solid #d1d5db;
    }
    
    .hour-slot {
        border-bottom: 1px solid #e5e7eb;
        padding: 15px;
        display: flex;
        align-items: start;
        gap: 20px;
    }
    
    .hour-slot:last-child {
        border-bottom: none;
    }
    
    .hour-label {
        width: 60px;
        color: #718096;
        font-size: 14px;
        font-weight: 500;
    }
    
    .hour-events {
        flex: 1;
    }
    
    /* Vista settimanale - Contrasto migliorato */
    .week-view {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        overflow-x: auto;
        border: 1px solid #d1d5db;
    }
    
    .week-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        background: #374151;
        border: 2px solid #374151;
        border-radius: 8px;
        min-width: 800px;
        overflow: hidden;
    }
    
    .week-day {
        background: white;
        min-height: 180px;
        padding: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .week-day-header {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
        font-size: 14px;
    }
    
    .week-day.today {
        background: #dbeafe;
        border-color: #3b82f6;
        box-shadow: inset 0 0 0 2px #3b82f6;
    }
    
    .week-day.today .week-day-header {
        color: #1e40af;
        border-bottom-color: #3b82f6;
    }
    
    /* Vista mensile - Contrasto migliorato */
    .month-view {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 1px solid #d1d5db;
    }
    
    .month-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        background: #374151;
        border: 2px solid #374151;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .month-day {
        background: #ffffff;
        min-height: 120px;
        padding: 12px 8px;
        position: relative;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .month-day:hover {
        background: #f8fafc;
        box-shadow: inset 0 0 0 2px #3b82f6;
    }
    
    .month-day-number {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 8px;
        font-size: 16px;
        display: inline-block;
        width: 24px;
        height: 24px;
        line-height: 24px;
        text-align: center;
        border-radius: 50%;
    }
    
    .month-day.other-month {
        background: #f9fafb;
        color: #9ca3af;
    }
    
    .month-day.other-month .month-day-number {
        color: #d1d5db;
        font-weight: 400;
    }
    
    .month-day.today {
        background: #dbeafe;
        border-color: #3b82f6;
        box-shadow: inset 0 0 0 2px #3b82f6;
    }
    
    .month-day.today .month-day-number {
        background: #3b82f6;
        color: white;
        font-weight: 700;
    }
    
    .mini-event {
        background: #3b82f6;
        color: white;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid #2563eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .mini-event:hover {
        background: #1d4ed8;
        border-color: #1e40af;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .event-detail-box {
        background: #f0ebff;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #6b5cdf;
    }
    
    /* Messaggi di notifica */
    .notification {
        margin: 20px 0;
        padding: 15px 20px;
        border-radius: 8px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease-out;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .notification.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        border-left: 4px solid #28a745;
    }
    
    .notification.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        border-left: 4px solid #dc3545;
    }
    
    .notification .icon {
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .notification .message {
        flex: 1;
    }
    
    .notification .close-btn {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .notification .close-btn:hover {
        opacity: 1;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Auto-hide dopo 5 secondi */
    .notification.auto-hide {
        animation: slideDown 0.3s ease-out, fadeOut 0.5s ease-out 4.5s forwards;
    }
    
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
</style>

<?php
// Definisci azioni per l'header
$headerActions = [];
if (method_exists($auth, 'canManageEvents') && $auth->canManageEvents()) {
    $headerActions[] = [
        'text' => 'Nuovo Evento',
        'href' => APP_PATH . '/calendario-eventi.php?action=nuovo',
        'icon' => 'fas fa-plus',
        'class' => 'btn btn-primary'
    ];
    
    // Aggiungi bottone importa ICS
    $headerActions[] = [
        'text' => 'Importa ICS',
        'href' => '#',
        'icon' => 'fas fa-file-import',
        'class' => 'btn btn-info',
        'onclick' => 'openImportModal(); return false;'
    ];
}

// Render header con component
renderPageHeader('Calendario Eventi', '', 'fas fa-calendar', $headerActions);
?>

<!-- Messaggi di notifica -->
<?php if ($successMessage): ?>
<div class="notification success auto-hide" id="successNotification">
    <div class="icon">✅</div>
    <div class="message"><?php echo htmlspecialchars($successMessage); ?></div>
    <button class="close-btn" onclick="closeNotification('successNotification')">&times;</button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="notification error auto-hide" id="errorNotification">
    <div class="icon">❌</div>
    <div class="message"><?php echo htmlspecialchars($errorMessage); ?></div>
    <button class="close-btn" onclick="closeNotification('errorNotification')">&times;</button>
</div>
<?php endif; ?>

<!-- Selettore vista -->
<div class="view-selector">
    <a href="?view=cards" class="view-btn <?php echo $view == 'cards' ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i> Cards
    </a>
    <a href="?view=day&date=<?php echo $date; ?>" class="view-btn <?php echo $view == 'day' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-day"></i> Giornaliera
    </a>
    <a href="?view=week&date=<?php echo $date; ?>" class="view-btn <?php echo $view == 'week' ? 'active' : ''; ?>">
        <i>📆</i> Settimanale
    </a>
    <a href="?view=month&date=<?php echo $date; ?>" class="view-btn <?php echo $view == 'month' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-alt"></i> Mensile
    </a>
</div>

<?php 
// Se non ci sono eventi
if (empty($eventi)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-alt"></i>
        <h2>Nessun evento programmato</h2>
        <p>Non ci sono eventi futuri in calendario.</p>
        <?php if ($auth->canManageEvents()): ?>
        <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="btn btn-primary">
            <i class="fas fa-plus"></i> Crea il primo evento
        </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php 
    // Includi la vista del calendario se il file esiste
    $viewFile = 'components/calendario_views.php';
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        // Fallback se il file non esiste
        ?>
        <div class="alert alert-info">
            Vista calendario non disponibile. Mostra lista eventi:
        </div>
        <div class="event-grid">
            <?php foreach ($eventi as $evento): ?>
            <div class="event-card">
                <h3><?php echo htmlspecialchars($evento['titolo']); ?></h3>
                <p><i class="fas fa-calendar"></i> <?php echo format_date($evento['data_inizio']); ?></p>
                <p><i class="fas fa-clock"></i> <?php echo format_time($evento['data_inizio']); ?></p>
                <?php if ($evento['luogo']): ?>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evento['luogo']); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    ?>
<?php endif; ?>

<script>
// Funzione per chiudere le notifiche
function closeNotification(notificationId) {
    const notification = document.getElementById(notificationId);
    if (notification) {
        notification.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
}

// Auto-hide delle notifiche dopo 5 secondi
document.addEventListener('DOMContentLoaded', function() {
    const notifications = document.querySelectorAll('.notification.auto-hide');
    notifications.forEach(notification => {
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'fadeOut 0.5s ease-out forwards';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }
        }, 5000);
    });
});
</script>

<!-- Modal Importazione ICS -->
<div id="importICSModal" class="modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-import"></i> Importa Eventi da File ICS</h5>
                <button type="button" class="btn-close" onclick="closeImportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="importICSForm" enctype="multipart/form-data">
                    <?php if ($auth->isSuperAdmin()): ?>
                    <div class="form-group mb-3">
                        <label for="import_azienda_id" class="form-label">Azienda di destinazione</label>
                        <select id="import_azienda_id" name="azienda_id" class="form-control">
                            <?php if ($currentAzienda): ?>
                                <option value="<?php echo $currentAzienda['id'] ?? $currentAzienda['azienda_id']; ?>" selected>
                                    <?php echo htmlspecialchars($currentAzienda['nome'] ?? 'Azienda corrente'); ?>
                                </option>
                            <?php endif; ?>
                            <option value="">-- Seleziona azienda --</option>
                            <?php
                            $stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
                            while ($azienda = $stmt->fetch()):
                            ?>
                                <option value="<?php echo $azienda['id']; ?>">
                                    <?php echo htmlspecialchars($azienda['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group mb-3">
                        <label for="ics_file" class="form-label">Seleziona file ICS</label>
                        <input type="file" id="ics_file" name="ics_file" class="form-control" accept=".ics" required>
                        <small class="form-text text-muted">
                            Il file deve essere in formato ICS/iCalendar. Massimo 5MB.
                        </small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Nota:</strong> Gli eventi già esistenti (stesso titolo e data) verranno saltati per evitare duplicati.
                    </div>
                    
                    <div id="importProgress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 100%">
                                Importazione in corso...
                            </div>
                        </div>
                    </div>
                    
                    <div id="importResult" style="display: none;" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="importICSFile()">
                    <i class="fas fa-upload"></i> Importa
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1050;
    width: 100%;
    height: 100%;
    overflow: hidden;
    outline: 0;
    background: rgba(0, 0, 0, 0.5);
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, .2);
    border-radius: .3rem;
    outline: 0;
    max-height: calc(100vh - 3.5rem);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
}

.modal-title {
    margin-bottom: 0;
    line-height: 1.5;
    font-size: 1.25rem;
    font-weight: 500;
}

.btn-close {
    padding: .25rem .25rem;
    margin: -.25rem -.25rem -.25rem auto;
    background: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #000;
    opacity: .5;
    cursor: pointer;
}

.btn-close:hover {
    opacity: .75;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
    overflow-y: auto;
    max-height: calc(100vh - 200px);
}

.modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    padding: .75rem;
    border-top: 1px solid #dee2e6;
    gap: .5rem;
}

.progress {
    display: flex;
    height: 1rem;
    overflow: hidden;
    font-size: .75rem;
    background-color: #e9ecef;
    border-radius: .25rem;
}

.progress-bar {
    display: flex;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    background-color: #0d6efd;
    transition: width .6s ease;
}

.progress-bar-striped {
    background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
}

.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

@keyframes progress-bar-stripes {
    0% {
        background-position-x: 1rem;
    }
}
</style>

<script>
// Funzioni per la modal di importazione ICS
function openImportModal() {
    document.getElementById('importICSModal').style.display = 'block';
    document.getElementById('importResult').style.display = 'none';
    document.getElementById('importProgress').style.display = 'none';
    document.getElementById('importICSForm').reset();
}

function closeImportModal() {
    document.getElementById('importICSModal').style.display = 'none';
}

function importICSFile() {
    const fileInput = document.getElementById('ics_file');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Seleziona un file ICS da importare');
        return;
    }
    
    if (!file.name.toLowerCase().endsWith('.ics')) {
        alert('Il file deve avere estensione .ics');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB
        alert('Il file è troppo grande. Massimo 5MB.');
        return;
    }
    
    // Mostra progress bar
    document.getElementById('importProgress').style.display = 'block';
    document.getElementById('importResult').style.display = 'none';
    
    // Prepara FormData
    const formData = new FormData();
    formData.append('ics_file', file);
    
    // Aggiungi azienda_id se super admin
    const aziendaSelect = document.getElementById('import_azienda_id');
    if (aziendaSelect) {
        formData.append('azienda_id', aziendaSelect.value);
    }
    
    // Ottieni CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Invia richiesta
    fetch('<?php echo APP_PATH; ?>/backend/api/import-ics.php', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('importProgress').style.display = 'none';
        const resultDiv = document.getElementById('importResult');
        
        if (data.success) {
            resultDiv.className = 'alert alert-success';
            resultDiv.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <strong>Importazione completata!</strong><br>
                ${data.message}<br>
                <small>Eventi importati: ${data.imported} | Saltati (già esistenti): ${data.skipped}</small>
            `;
            
            // Ricarica la pagina dopo 2 secondi
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            resultDiv.className = 'alert alert-danger';
            resultDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <strong>Errore durante l'importazione:</strong><br>
                ${data.error}
            `;
        }
        
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        document.getElementById('importProgress').style.display = 'none';
        const resultDiv = document.getElementById('importResult');
        resultDiv.className = 'alert alert-danger';
        resultDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <strong>Errore di rete:</strong><br>
            ${error.message}
        `;
        resultDiv.style.display = 'block';
    });
}

// Chiudi modal cliccando fuori
window.addEventListener('click', function(event) {
    const modal = document.getElementById('importICSModal');
    if (event.target === modal) {
        closeImportModal();
    }
});
</script>

<?php require_once 'components/footer.php'; ?> 