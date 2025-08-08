<?php
/**
 * Form per creazione/modifica task
 * Solo Super Admin possono accedere a questo form
 */

if (!isset($utenti_assegnabili) || !isset($aziende_disponibili)) {
    echo '<div class="alert alert-danger">Errore: dati mancanti per il form</div>';
    return;
}

$isEdit = isset($task) && !empty($task);
$formTitle = $isEdit ? 'Modifica Task' : 'Assegna Nuovo Task';
$submitText = $isEdit ? 'Aggiorna Task' : 'Assegna Task';
?>

<div class="form-container">
    <h2><i class="fas fa-tasks"></i> <?= $formTitle ?></h2>
    
    <?php if (isset($error) && !empty($error)): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="taskForm">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="utenti_assegnati">Assegna a <span class="required">*</span></label>
                <div class="users-checkbox-list">
                    <?php 
                    // Se è in modifica, carica gli utenti già assegnati
                    $utenti_assegnati = [];
                    if ($isEdit) {
                        $stmt = db_query("SELECT utente_id FROM task_assegnazioni WHERE task_id = ?", [$task['id']]);
                        $utenti_assegnati = array_column($stmt->fetchAll(), 'utente_id');
                    }
                    ?>
                    <?php foreach ($utenti_assegnabili as $utente): ?>
                    <label class="user-checkbox">
                        <input type="checkbox" name="utenti_assegnati[]" value="<?= $utente['id'] ?>" 
                               <?= (in_array($utente['id'], $utenti_assegnati)) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></span>
                        <span class="role-tag"><?= htmlspecialchars($utente['ruolo']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <small class="form-text text-muted">Seleziona uno o più utenti</small>
            </div>
            
            <div class="form-group col-md-6">
                <label for="attivita">Tipo Attività <span class="required">*</span></label>
                <select class="form-control" id="attivita" name="attivita" required>
                    <option value="">Seleziona attività...</option>
                    <option value="Consulenza" <?= ($isEdit && $task['attivita'] == 'Consulenza') ? 'selected' : '' ?>>Consulenza</option>
                    <option value="Operation" <?= ($isEdit && $task['attivita'] == 'Operation') ? 'selected' : '' ?>>Operation</option>
                    <option value="Verifica" <?= ($isEdit && $task['attivita'] == 'Verifica') ? 'selected' : '' ?>>Verifica</option>
                    <option value="Office" <?= ($isEdit && $task['attivita'] == 'Office') ? 'selected' : '' ?>>Office</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="giornate_previste">Giornate Previste <span class="required">*</span></label>
                <input type="number" class="form-control" id="giornate_previste" name="giornate_previste" 
                       min="0" max="15" step="0.5" required placeholder="0.5 - 15"
                       value="<?= $isEdit ? htmlspecialchars($task['giornate_previste']) : '' ?>">
                <small class="form-text text-muted">Incrementi di 0.5 giorni</small>
                <label class="checkbox-label" style="margin-top: 10px;">
                    <input type="checkbox" id="usaGiorniSpecifici" name="usa_giorni_specifici" value="1"
                           <?= ($isEdit && isset($task['usa_giorni_specifici']) && $task['usa_giorni_specifici']) ? 'checked' : '' ?>>
                    <span>Seleziona giorni specifici</span>
                </label>
            </div>
            
            <div class="form-group col-md-3">
                <label for="costo_giornata">Costo Giornata (€) <span class="required">*</span></label>
                <input type="number" class="form-control" id="costo_giornata" name="costo_giornata" 
                       min="0" step="0.01" required placeholder="0.00"
                       value="<?= $isEdit ? htmlspecialchars($task['costo_giornata']) : '' ?>">
            </div>
            
            <div class="form-group col-md-3">
                <label for="data_inizio">Data Inizio <span class="required">*</span></label>
                <input type="date" class="form-control" id="data_inizio" name="data_inizio" required
                       value="<?= $isEdit ? htmlspecialchars($task['data_inizio']) : '' ?>">
            </div>
            
            <div class="form-group col-md-3">
                <label for="data_fine">Data Fine <span class="required">*</span></label>
                <input type="date" class="form-control" id="data_fine" name="data_fine" required
                       value="<?= $isEdit ? htmlspecialchars($task['data_fine']) : '' ?>">
            </div>
        </div>
        
        <!-- Selezione giorni specifici -->
        <div class="day-selection-section" id="daySelectionSection" style="display: none;">
            <h4><i class="fas fa-calendar-check"></i> Seleziona giorni specifici</h4>
            <p class="form-text">Seleziona i giorni in cui il task sarà attivo (puoi selezionare giorni non consecutivi)</p>
            
            <div class="calendar-header">
                <div>Lun</div>
                <div>Mar</div>
                <div>Mer</div>
                <div>Gio</div>
                <div>Ven</div>
                <div>Sab</div>
                <div>Dom</div>
            </div>
            
            <div class="days-calendar" id="daysCalendar">
                <!-- Generato dinamicamente via JavaScript -->
            </div>
            
            <div class="selected-days-summary" id="selectedDaysSummary">
                <strong>Giorni selezionati:</strong> <span id="selectedCount">0</span> giorni
            </div>
            
            <input type="hidden" name="giorni_specifici" id="giorniSpecifici" value="">
        </div>
        
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="azienda_id">Azienda <span class="required">*</span></label>
                <select class="form-control" id="azienda_id" name="azienda_id" required>
                    <option value="">Seleziona azienda...</option>
                    <?php foreach ($aziende_disponibili as $azienda): ?>
                    <option value="<?= $azienda['id'] ?>" <?= ($isEdit && $task['azienda_id'] == $azienda['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($azienda['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group col-md-6">
                <label for="citta">Città <span class="required">*</span></label>
                <input type="text" class="form-control" id="citta" name="citta" required 
                       placeholder="Es. Milano, Roma, etc."
                       value="<?= $isEdit ? htmlspecialchars($task['citta']) : '' ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="prodotto_servizio">Prodotto/Servizio <span class="required">*</span></label>
            <div class="input-group">
                <select class="form-control" id="prodotto_servizio_select" onchange="toggleProdottoServizio()">
                    <option value="">Seleziona...</option>
                    <option value="9001">ISO 9001</option>
                    <option value="14001">ISO 14001</option>
                    <option value="27001">ISO 27001</option>
                    <option value="45001">ISO 45001</option>
                    <option value="Autorizzazione">Autorizzazione</option>
                    <option value="Accreditamento">Accreditamento</option>
                    <option value="altro">Altro (specifica...)</option>
                </select>
                <input type="text" class="form-control" id="prodotto_servizio_custom" name="prodotto_servizio" 
                       style="display: none;" placeholder="Specifica prodotto/servizio...">
            </div>
        </div>
        
        <div class="form-group">
            <label for="descrizione">Descrizione</label>
            <textarea class="form-control" id="descrizione" name="descrizione" rows="3" 
                      placeholder="Descrizione dettagliata del task..."><?= $isEdit ? htmlspecialchars($task['descrizione']) : '' ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="note">Note</label>
            <textarea class="form-control" id="note" name="note" rows="2" 
                      placeholder="Note aggiuntive..."><?= $isEdit ? htmlspecialchars($task['note']) : '' ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> <?= $submitText ?>
            </button>
            <a href="calendario-eventi.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Annulla
            </a>
            
            <?php if ($isEdit): ?>
            <div class="form-group mt-3">
                <label class="checkbox-label">
                    <input type="checkbox" name="invia_notifiche" value="1" checked>
                    <span>Invia notifiche email all'utente assegnato</span>
                </label>
            </div>
            
            <a href="?action=elimina_task&id=<?= $task['id'] ?>" class="btn btn-danger float-right"
               onclick="return confirm('Sei sicuro di voler eliminare questo task?')">
                <i class="fas fa-trash"></i> Elimina Task
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-container h2 {
    margin-bottom: 30px;
    color: #2d3748;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
}

.form-group {
    margin-bottom: 20px;
    padding: 0 10px;
}

.form-group.col-md-3 {
    flex: 0 0 25%;
    max-width: 25%;
}

.form-group.col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #4a5568;
}

.required {
    color: #e53e3e;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #718096;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

.alert {
    padding: 12px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #fed7d7;
    color: #742a2a;
    border: 1px solid #fc8181;
}

.input-group {
    display: flex;
    gap: 10px;
}

/* Users checkbox list */
.users-checkbox-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px;
    background: #f8f9fa;
}

.user-checkbox {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    margin-bottom: 5px;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-checkbox:hover {
    background: #e6f7ff;
}

.user-checkbox input[type="checkbox"] {
    margin-right: 10px;
}

.user-checkbox span {
    flex: 1;
    color: #2d3748;
}

.role-tag {
    font-size: 11px;
    padding: 2px 8px;
    background: #e2e8f0;
    color: #4a5568;
    border-radius: 10px;
    margin-left: 10px;
}

/* Day selection calendar */
.day-selection-section {
    margin-top: 20px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.days-calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-top: 15px;
}

.day-cell {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    cursor: pointer;
    background: white;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 500;
}

.day-cell:hover:not(.disabled) {
    background: #e6f7ff;
    border-color: #4299e1;
}

.day-cell.selected {
    background: #4299e1;
    color: white;
    border-color: #3182ce;
}

.day-cell.disabled {
    background: #f1f5f9;
    color: #cbd5e0;
    cursor: not-allowed;
}

.day-cell.weekend {
    background: #fef5e7;
}

.day-cell.weekend.selected {
    background: #ed8936;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    color: #4a5568;
}

.selected-days-summary {
    margin-top: 15px;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    font-size: 14px;
    color: #2d3748;
}

@media (max-width: 768px) {
    .form-group.col-md-3,
    .form-group.col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
</style>

<script>
function toggleProdottoServizio() {
    const select = document.getElementById('prodotto_servizio_select');
    const customInput = document.getElementById('prodotto_servizio_custom');
    
    if (select.value === 'altro') {
        select.style.display = 'none';
        customInput.style.display = 'block';
        customInput.required = true;
        customInput.focus();
    } else if (select.value !== '') {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
        // Imposta il valore nel campo nascosto
        customInput.value = select.value;
    }
}

// Validazione form
// Gestione selezione giorni specifici
let selectedDays = new Set();

function toggleDaySelection() {
    const checkbox = document.getElementById('usaGiorniSpecifici');
    const daySelectionSection = document.getElementById('daySelectionSection');
    
    if (checkbox.checked) {
        daySelectionSection.style.display = 'block';
        generateCalendar();
    } else {
        daySelectionSection.style.display = 'none';
        selectedDays.clear();
        updateSelectedDaysInput();
    }
}

function generateCalendar() {
    const dataInizio = document.getElementById('data_inizio').value;
    const dataFine = document.getElementById('data_fine').value;
    
    if (!dataInizio || !dataFine) {
        alert('Seleziona prima le date di inizio e fine');
        document.getElementById('usaGiorniSpecifici').checked = false;
        document.getElementById('daySelectionSection').style.display = 'none';
        return;
    }
    
    const start = new Date(dataInizio);
    const end = new Date(dataFine);
    const calendar = document.getElementById('daysCalendar');
    calendar.innerHTML = '';
    
    // Carica giorni già selezionati se in modifica
    <?php if ($isEdit && isset($task['id'])): ?>
    fetch('<?= APP_PATH ?>/backend/api/get-task-days.php?task_id=<?= $task['id'] ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.days) {
                data.days.forEach(day => selectedDays.add(day));
                renderCalendarDays(start, end, calendar);
            }
        });
    <?php else: ?>
    renderCalendarDays(start, end, calendar);
    <?php endif; ?>
}

function renderCalendarDays(start, end, calendar) {
    // Trova il primo lunedì prima o uguale alla data di inizio
    const firstDay = new Date(start);
    const dayOfWeek = firstDay.getDay();
    const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
    firstDay.setDate(firstDay.getDate() - daysToMonday);
    
    // Genera le celle del calendario
    const current = new Date(firstDay);
    while (current <= end || current.getDay() !== 1) {
        const dayCell = document.createElement('div');
        dayCell.className = 'day-cell';
        dayCell.textContent = current.getDate();
        
        const dateStr = current.toISOString().split('T')[0];
        dayCell.dataset.date = dateStr;
        
        // Controlla se il giorno è nel range
        if (current < start || current > end) {
            dayCell.classList.add('disabled');
        } else {
            // Weekend
            if (current.getDay() === 0 || current.getDay() === 6) {
                dayCell.classList.add('weekend');
            }
            
            // Giorno già selezionato
            if (selectedDays.has(dateStr)) {
                dayCell.classList.add('selected');
            }
            
            dayCell.addEventListener('click', toggleDay);
        }
        
        calendar.appendChild(dayCell);
        current.setDate(current.getDate() + 1);
    }
    
    updateSelectedDaysCount();
}

function toggleDay(e) {
    const dayCell = e.target;
    const date = dayCell.dataset.date;
    
    if (dayCell.classList.contains('selected')) {
        dayCell.classList.remove('selected');
        selectedDays.delete(date);
    } else {
        dayCell.classList.add('selected');
        selectedDays.add(date);
    }
    
    updateSelectedDaysCount();
    updateSelectedDaysInput();
}

function updateSelectedDaysCount() {
    document.getElementById('selectedCount').textContent = selectedDays.size;
}

function updateSelectedDaysInput() {
    const input = document.getElementById('giorniSpecifici');
    input.value = Array.from(selectedDays).sort().join(',');
}

// Event listeners
document.getElementById('usaGiorniSpecifici').addEventListener('change', toggleDaySelection);
document.getElementById('data_inizio').addEventListener('change', function() {
    if (document.getElementById('usaGiorniSpecifici').checked) {
        generateCalendar();
    }
});
document.getElementById('data_fine').addEventListener('change', function() {
    if (document.getElementById('usaGiorniSpecifici').checked) {
        generateCalendar();
    }
});

// Validazione form
document.getElementById('taskForm').addEventListener('submit', function(e) {
    const dataInizio = document.getElementById('data_inizio').value;
    const dataFine = document.getElementById('data_fine').value;
    
    if (dataInizio && dataFine && dataInizio > dataFine) {
        e.preventDefault();
        alert('La data di fine deve essere successiva o uguale alla data di inizio');
        return false;
    }
    
    // Verifica almeno un utente selezionato
    const checkboxes = document.querySelectorAll('input[name="utenti_assegnati[]"]:checked');
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Seleziona almeno un utente a cui assegnare il task');
        return false;
    }
    
    // Verifica giorni specifici se abilitati
    if (document.getElementById('usaGiorniSpecifici').checked && selectedDays.size === 0) {
        e.preventDefault();
        alert('Seleziona almeno un giorno specifico per il task');
        return false;
    }
    
    // Gestione prodotto/servizio
    const select = document.getElementById('prodotto_servizio_select');
    const customInput = document.getElementById('prodotto_servizio_custom');
    
    if (select.value !== 'altro' && select.value !== '') {
        customInput.value = select.value;
    }
});

// Inizializza al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('usaGiorniSpecifici').checked) {
        toggleDaySelection();
    }
});
</script>