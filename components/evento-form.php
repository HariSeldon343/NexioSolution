<?php
/**
 * Form per creazione/modifica eventi
 */

// Predefinisci valori per modifica
$titolo = isset($evento) ? ($evento['titolo'] ?? '') : '';
$descrizione = isset($evento) ? ($evento['descrizione'] ?? '') : '';
$data_inizio = isset($evento) && $evento ? date('Y-m-d', strtotime($evento['data_inizio'])) : '';
$ora_inizio = isset($evento) && $evento ? date('H:i', strtotime($evento['data_inizio'])) : '';
$data_fine = isset($evento) && $evento ? date('Y-m-d', strtotime($evento['data_fine'])) : '';
$ora_fine = isset($evento) && $evento ? date('H:i', strtotime($evento['data_fine'])) : '';
$luogo = isset($evento) ? ($evento['luogo'] ?? '') : '';
$tipo = isset($evento) ? ($evento['tipo'] ?? 'riunione') : 'riunione';

$isEdit = ($action === 'modifica');
$formTitle = $isEdit ? 'Modifica Evento' : 'Nuovo Evento';
$submitText = $isEdit ? 'Aggiorna Evento' : 'Crea Evento';
?>

<div class="evento-form-container">
    <div class="form-header">
        <h2><i class="fas fa-<?= $isEdit ? 'edit' : 'plus' ?>"></i> <?= $formTitle ?></h2>
        <a href="<?= APP_PATH ?>/calendario-eventi.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna al Calendario
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="evento-form">
        <div class="form-grid">
            <!-- Colonna sinistra: Informazioni base -->
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Informazioni Evento</h3>
                
                <div class="form-group">
                    <label for="titolo">Titolo *</label>
                    <input type="text" id="titolo" name="titolo" class="form-control" 
                           value="<?= htmlspecialchars($titolo) ?>" required 
                           placeholder="es. Riunione team, Presentazione progetto">
                </div>
                
                <div class="form-group">
                    <label for="descrizione">Descrizione</label>
                    <textarea id="descrizione" name="descrizione" class="form-control" rows="4"
                              placeholder="Descrivi l'evento, l'agenda, gli obiettivi..."><?= htmlspecialchars($descrizione) ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo">Tipo Evento</label>
                        <select id="tipo" name="tipo" class="form-control">
                            <option value="riunione" <?= $tipo === 'riunione' ? 'selected' : '' ?>>Riunione</option>
                            <option value="formazione" <?= $tipo === 'formazione' ? 'selected' : '' ?>>Formazione</option>
                            <option value="evento" <?= $tipo === 'evento' ? 'selected' : '' ?>>Evento</option>
                            <option value="altro" <?= $tipo === 'altro' ? 'selected' : '' ?>>Altro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="luogo">Luogo</label>
                        <input type="text" id="luogo" name="luogo" class="form-control" 
                               value="<?= htmlspecialchars($luogo) ?>" 
                               placeholder="es. Sala riunioni A, Via Roma 123, Online">
                    </div>
                </div>
            </div>
            
            <!-- Colonna destra: Data/ora e partecipanti -->
            <div class="form-section">
                <h3><i class="fas fa-clock"></i> Data e Ora</h3>
                
                <div class="datetime-group">
                    <div class="datetime-section">
                        <h4><i class="fas fa-play-circle"></i> Inizio *</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_inizio">Data</label>
                                <input type="date" id="data_inizio" name="data_inizio" class="form-control" 
                                       value="<?= $data_inizio ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="ora_inizio">Ora</label>
                                <div class="custom-time-picker">
                                    <input type="hidden" id="ora_inizio" name="ora_inizio" value="<?= $ora_inizio ?>" required>
                                    <div class="time-display" id="time-display-inizio">
                                        <i class="fas fa-clock"></i>
                                        <span class="time-value"><?= $ora_inizio ?: '09:00' ?></span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="time-selector" id="time-selector-inizio" style="display: none;">
                                        <div class="time-tabs">
                                            <div class="time-tab active" data-tab="hours">Ore</div>
                                            <div class="time-tab" data-tab="minutes">Minuti</div>
                                        </div>
                                        <div class="time-values">
                                            <div class="hours-grid active" data-type="hours">
                                                <?php for($h = 0; $h < 24; $h++): ?>
                                                    <div class="time-option" data-value="<?= sprintf('%02d', $h) ?>"><?= sprintf('%02d', $h) ?></div>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="minutes-grid" data-type="minutes" style="display: none;">
                                                <?php for($m = 0; $m < 60; $m += 5): ?>
                                                    <div class="time-option" data-value="<?= sprintf('%02d', $m) ?>"><?= sprintf('%02d', $m) ?></div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="time-quick-select">
                                            <span class="quick-time" data-time="08:00">8:00</span>
                                            <span class="quick-time" data-time="09:00">9:00</span>
                                            <span class="quick-time" data-time="10:00">10:00</span>
                                            <span class="quick-time" data-time="14:00">14:00</span>
                                            <span class="quick-time" data-time="15:00">15:00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="datetime-section">
                        <h4><i class="fas fa-stop-circle"></i> Fine</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="data_fine">Data</label>
                                <input type="date" id="data_fine" name="data_fine" class="form-control" 
                                       value="<?= $data_fine ?>">
                                <small class="help-text">Se vuoto, sarà uguale alla data di inizio</small>
                            </div>
                            <div class="form-group">
                                <label for="ora_fine">Ora</label>
                                <div class="custom-time-picker">
                                    <input type="hidden" id="ora_fine" name="ora_fine" value="<?= $ora_fine ?>">
                                    <div class="time-display" id="time-display-fine">
                                        <i class="fas fa-clock"></i>
                                        <span class="time-value"><?= $ora_fine ?: '10:00' ?></span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="time-selector" id="time-selector-fine" style="display: none;">
                                        <div class="time-tabs">
                                            <div class="time-tab active" data-tab="hours">Ore</div>
                                            <div class="time-tab" data-tab="minutes">Minuti</div>
                                        </div>
                                        <div class="time-values">
                                            <div class="hours-grid active" data-type="hours">
                                                <?php for($h = 0; $h < 24; $h++): ?>
                                                    <div class="time-option" data-value="<?= sprintf('%02d', $h) ?>"><?= sprintf('%02d', $h) ?></div>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="minutes-grid" data-type="minutes" style="display: none;">
                                                <?php for($m = 0; $m < 60; $m += 5): ?>
                                                    <div class="time-option" data-value="<?= sprintf('%02d', $m) ?>"><?= sprintf('%02d', $m) ?></div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="duration-quick">
                                            <span class="duration-btn" data-duration="30">+30min</span>
                                            <span class="duration-btn" data-duration="60">+1h</span>
                                            <span class="duration-btn" data-duration="120">+2h</span>
                                        </div>
                                    </div>
                                </div>
                                <small class="help-text">Se vuoto, durata predefinita: 1 ora</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Durata calcolata -->
                    <div class="duration-display" id="durationDisplay" style="display: none;">
                        <i class="fas fa-clock"></i>
                        <span>Durata: <strong id="durationText">1 ora</strong></span>
                    </div>
                </div>
                
                <?php if ($auth->canInviteUsers() && !empty($utenti_disponibili)): ?>
                <div class="partecipanti-section">
                    <h3><i class="fas fa-users"></i> Partecipanti</h3>
                    
                    <div class="form-group">
                        <label>Invita Utenti</label>
                        <div class="users-simple-list">
                            <?php foreach ($utenti_disponibili as $utente): ?>
                            <label class="user-row">
                                <input type="checkbox" name="partecipanti[]" value="<?= $utente['id'] ?>"
                                       <?= in_array($utente['id'], $partecipanti_attuali ?? []) ? 'checked' : '' ?>>
                                <span class="user-name"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></span>
                                <span class="user-role"><?php
                                    echo match($utente['ruolo']) {
                                        'super_admin' => 'Super Admin',
                                        'admin' => 'Admin',
                                        'manager' => 'Manager',
                                        'user' => 'Utente',
                                        default => ucfirst($utente['ruolo'])
                                    };
                                ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="invia_notifiche" value="1" checked>
                            <span>Invia notifiche email ai partecipanti</span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $submitText ?>
            </button>
            <a href="<?= APP_PATH ?>/calendario-eventi.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Annulla
            </a>
            
            <?php if ($isEdit && isset($evento)): ?>
            <a href="?action=elimina&id=<?= $evento['id'] ?>" class="btn btn-danger btn-delete"
               onclick="return confirm('Sei sicuro di voler eliminare questo evento?')">
                <i class="fas fa-trash"></i> Elimina Evento
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.evento-form-container {
    max-width: 1200px;
    margin: 0 auto;
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.form-header h2 {
    color: #2d3748;
    font-size: 24px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: #fed7d7;
    color: #742a2a;
    border: 1px solid #feb2b2;
}

.evento-form {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 30px;
}

.form-section {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.form-section h3 {
    color: #2d3748;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #4a5568;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.datetime-group {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.datetime-section {
    margin-bottom: 20px;
}

.datetime-section:last-child {
    margin-bottom: 0;
}

.datetime-section h4 {
    color: #2d3748;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.partecipanti-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.users-simple-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    padding: 0;
}

.user-row {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.2s ease;
}

.user-row:last-child {
    border-bottom: none;
}

.user-row:hover {
    background: #f8f9fa;
}

.user-row input[type="checkbox"] {
    margin-right: 12px;
}

.user-name {
    flex: 1;
    font-weight: 500;
    color: #2d3748;
    font-size: 14px;
}

.user-role {
    font-size: 12px;
    color: #718096;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: normal !important;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 8px;
    transform: scale(1.1);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-start;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #4299e1;
    color: white;
}

.btn-primary:hover {
    background: #3182ce;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

.btn-danger {
    background: #e53e3e;
    color: white;
    margin-left: auto;
}

.btn-danger:hover {
    background: #c53030;
}

/* Custom Time Picker Styles */
.custom-time-picker {
    position: relative;
    width: 100%;
}

.time-display {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.time-display:hover {
    border-color: #cbd5e0;
}

.time-display.active {
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.time-display i:first-child {
    color: #4299e1;
}

.time-display i:last-child {
    margin-left: auto;
    transition: transform 0.3s ease;
}

.time-display.active i:last-child {
    transform: rotate(180deg);
}

.time-value {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
}

.time-selector {
    position: absolute;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    z-index: 100;
    max-height: 400px;
    overflow: hidden;
}

.time-tabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
}

.time-tab {
    flex: 1;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    font-weight: 500;
    color: #718096;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.time-tab.active {
    background: white;
    color: #4299e1;
    border-bottom: 2px solid #4299e1;
}

.time-values {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.hours-grid, .minutes-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 5px;
    padding: 10px;
    height: 200px;
    overflow-y: auto;
}

.time-option {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    color: #2d3748;
    transition: all 0.2s ease;
}

.time-option:hover {
    background: #e2e8f0;
    transform: scale(1.05);
}

.time-option.selected {
    background: #4299e1;
    color: white;
}

.time-quick-select, .duration-quick {
    display: flex;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid #e2e8f0;
    background: #f8f9fa;
}

.quick-time, .duration-btn {
    padding: 6px 12px;
    background: white;
    border: 1px solid #cbd5e0;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-time:hover, .duration-btn:hover {
    background: #4299e1;
    color: white;
    border-color: #4299e1;
}

/* Scrollbar personalizzata */
.hours-grid::-webkit-scrollbar,
.minutes-grid::-webkit-scrollbar {
    width: 6px;
}

.hours-grid::-webkit-scrollbar-track,
.minutes-grid::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.hours-grid::-webkit-scrollbar-thumb,
.minutes-grid::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.hours-grid::-webkit-scrollbar-thumb:hover,
.minutes-grid::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

.help-text {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #718096;
    font-style: italic;
}

.duration-display {
    background: #f0fff4;
    border: 1px solid #9ae6b4;
    color: #276749;
    padding: 10px 15px;
    border-radius: 6px;
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.duration-display i {
    color: #38a169;
}

/* Responsive design */
@media (max-width: 968px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .form-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .form-actions {
        flex-wrap: wrap;
    }
    
    .btn-danger {
        margin-left: 0;
        order: -1;
    }
}

@media (max-width: 768px) {
    .evento-form {
        padding: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .datetime-group {
        padding: 15px;
    }
    
    .users-simple-list {
        max-height: 250px;
    }
}
</style>

<script>
// Auto-populate end date/time when start date/time changes
document.addEventListener('DOMContentLoaded', function() {
    const dataInizio = document.getElementById('data_inizio');
    const oraInizio = document.getElementById('ora_inizio');
    const dataFine = document.getElementById('data_fine');
    const oraFine = document.getElementById('ora_fine');
    const durationDisplay = document.getElementById('durationDisplay');
    const durationText = document.getElementById('durationText');
    
    function updateEndDateTime() {
        if (dataInizio.value && !dataFine.value) {
            dataFine.value = dataInizio.value;
        }
        
        if (oraInizio.value && !oraFine.value) {
            // Add 1 hour to start time
            const [hours, minutes] = oraInizio.value.split(':');
            const endTime = new Date();
            endTime.setHours(parseInt(hours) + 1, parseInt(minutes));
            
            const endHours = String(endTime.getHours()).padStart(2, '0');
            const endMinutes = String(endTime.getMinutes()).padStart(2, '0');
            oraFine.value = `${endHours}:${endMinutes}`;
        }
        
        updateDurationDisplay();
    }
    
    // Calcola e mostra la durata dell'evento
    function updateDurationDisplay() {
        if (dataInizio.value && oraInizio.value && dataFine.value && oraFine.value) {
            const startDateTime = new Date(dataInizio.value + 'T' + oraInizio.value);
            const endDateTime = new Date(dataFine.value + 'T' + oraFine.value);
            
            if (endDateTime > startDateTime) {
                const diffMs = endDateTime - startDateTime;
                const diffMinutes = Math.floor(diffMs / (1000 * 60));
                const hours = Math.floor(diffMinutes / 60);
                const minutes = diffMinutes % 60;
                
                let durationStr = '';
                if (hours > 0) {
                    durationStr += hours + (hours === 1 ? ' ora' : ' ore');
                }
                if (minutes > 0) {
                    if (durationStr) durationStr += ' e ';
                    durationStr += minutes + (minutes === 1 ? ' minuto' : ' minuti');
                }
                
                durationText.textContent = durationStr || '0 minuti';
                durationDisplay.style.display = 'flex';
            } else {
                durationDisplay.style.display = 'none';
            }
        } else {
            durationDisplay.style.display = 'none';
        }
    }
    
    // Event listeners per aggiornamento automatico date/ora
    dataInizio.addEventListener('change', updateEndDateTime);
    oraInizio.addEventListener('change', updateEndDateTime);
    dataFine.addEventListener('change', updateDurationDisplay);
    oraFine.addEventListener('change', updateDurationDisplay);
    
    // Custom Time Picker functionality
    function initializeTimePicker(pickerId, inputId) {
        const display = document.getElementById(`time-display-${pickerId}`);
        const selector = document.getElementById(`time-selector-${pickerId}`);
        const input = document.getElementById(inputId);
        const valueSpan = display.querySelector('.time-value');
        
        let selectedHour = '09';
        let selectedMinute = '00';
        
        // Parse initial value
        if (input.value) {
            [selectedHour, selectedMinute] = input.value.split(':');
        }
        
        // Toggle selector
        display.addEventListener('click', function() {
            const isOpen = selector.style.display !== 'none';
            
            // Close all other selectors
            document.querySelectorAll('.time-selector').forEach(s => {
                s.style.display = 'none';
                s.previousElementSibling.classList.remove('active');
            });
            
            if (!isOpen) {
                selector.style.display = 'block';
                display.classList.add('active');
                // Highlight current values
                updateSelectedOptions();
            } else {
                selector.style.display = 'none';
                display.classList.remove('active');
            }
        });
        
        // Tab switching
        selector.querySelectorAll('.time-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabType = this.getAttribute('data-tab');
                
                // Update active tab
                selector.querySelectorAll('.time-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding grid
                selector.querySelectorAll('.hours-grid, .minutes-grid').forEach(grid => {
                    grid.style.display = 'none';
                    grid.classList.remove('active');
                });
                
                const targetGrid = selector.querySelector(`.${tabType}-grid`);
                targetGrid.style.display = 'grid';
                targetGrid.classList.add('active');
            });
        });
        
        // Hour selection
        selector.querySelectorAll('.hours-grid .time-option').forEach(option => {
            option.addEventListener('click', function() {
                selectedHour = this.getAttribute('data-value');
                updateTime();
                // Switch to minutes tab
                selector.querySelector('[data-tab="minutes"]').click();
            });
        });
        
        // Minute selection
        selector.querySelectorAll('.minutes-grid .time-option').forEach(option => {
            option.addEventListener('click', function() {
                selectedMinute = this.getAttribute('data-value');
                updateTime();
                // Close selector
                selector.style.display = 'none';
                display.classList.remove('active');
            });
        });
        
        // Quick time selection
        selector.querySelectorAll('.quick-time').forEach(btn => {
            btn.addEventListener('click', function() {
                const time = this.getAttribute('data-time');
                [selectedHour, selectedMinute] = time.split(':');
                updateTime();
                selector.style.display = 'none';
                display.classList.remove('active');
            });
        });
        
        // Duration buttons (only for end time)
        if (pickerId === 'fine') {
            selector.querySelectorAll('.duration-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!oraInizio.value) {
                        alert('Seleziona prima l\'ora di inizio');
                        return;
                    }
                    
                    const duration = parseInt(this.getAttribute('data-duration'));
                    const [startHour, startMinute] = oraInizio.value.split(':');
                    
                    const startTime = new Date();
                    startTime.setHours(parseInt(startHour), parseInt(startMinute));
                    
                    const endTime = new Date(startTime.getTime() + (duration * 60 * 1000));
                    
                    selectedHour = String(endTime.getHours()).padStart(2, '0');
                    selectedMinute = String(endTime.getMinutes()).padStart(2, '0');
                    
                    updateTime();
                    selector.style.display = 'none';
                    display.classList.remove('active');
                });
            });
        }
        
        function updateTime() {
            const timeStr = `${selectedHour}:${selectedMinute}`;
            input.value = timeStr;
            valueSpan.textContent = timeStr;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
            
            if (inputId === 'ora_inizio') {
                updateEndDateTime();
            } else {
                updateDurationDisplay();
            }
        }
        
        function updateSelectedOptions() {
            // Clear all selected
            selector.querySelectorAll('.time-option').forEach(opt => opt.classList.remove('selected'));
            
            // Highlight selected hour
            selector.querySelectorAll('.hours-grid .time-option').forEach(opt => {
                if (opt.getAttribute('data-value') === selectedHour) {
                    opt.classList.add('selected');
                    // Scroll into view
                    opt.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            });
            
            // Highlight selected minute
            selector.querySelectorAll('.minutes-grid .time-option').forEach(opt => {
                if (opt.getAttribute('data-value') === selectedMinute) {
                    opt.classList.add('selected');
                }
            });
        }
    }
    
    // Initialize both time pickers
    initializeTimePicker('inizio', 'ora_inizio');
    initializeTimePicker('fine', 'ora_fine');
    
    // Close selectors when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-time-picker')) {
            document.querySelectorAll('.time-selector').forEach(s => {
                s.style.display = 'none';
                s.previousElementSibling.classList.remove('active');
            });
        }
    });
    
    // Aggiorna display durata all'avvio se già ci sono valori
    updateDurationDisplay();
    
    // Imposta data minima per gli input date (oggi)
    const today = new Date().toISOString().split('T')[0];
    dataInizio.setAttribute('min', today);
    dataFine.setAttribute('min', today);
    
    // Form validation
    const form = document.querySelector('.evento-form');
    form.addEventListener('submit', function(e) {
        const titolo = document.getElementById('titolo').value.trim();
        const dataInizioVal = dataInizio.value;
        const oraInizioVal = oraInizio.value;
        
        if (!titolo || !dataInizioVal || !oraInizioVal) {
            e.preventDefault();
            alert('Compila tutti i campi obbligatori (Titolo, Data e Ora di inizio)');
            return false;
        }
        
        // Verifica che l'evento non sia nel passato
        const now = new Date();
        const startDateTime = new Date(dataInizioVal + 'T' + oraInizioVal);
        
        // Aggiungi un margine di 5 minuti per evitare problemi di timing
        const nowPlusFiveMinutes = new Date(now.getTime() + (5 * 60 * 1000));
        
        if (startDateTime < nowPlusFiveMinutes) {
            e.preventDefault();
            alert('Non puoi creare eventi nel passato. L\'evento deve iniziare almeno tra 5 minuti.');
            return false;
        }
        
        // Check if end time is after start time
        if (dataFine.value && oraFine.value) {
            const endDateTime = new Date(dataFine.value + 'T' + oraFine.value);
            
            if (endDateTime <= startDateTime) {
                e.preventDefault();
                alert('La data/ora di fine deve essere successiva a quella di inizio');
                return false;
            }
        }
    });
});
</script>