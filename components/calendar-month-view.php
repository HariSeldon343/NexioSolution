<?php
/**
 * Vista calendario mensile SEMPLICE
 * Griglia calendario con eventi visualizzati come badge
 */

// Include calendar helper
require_once 'backend/utils/CalendarHelper.php';
require_once 'backend/utils/CalendarColorHelper.php';

// Calcola date per il mese
$currentDate = strtotime($date);
$year = date('Y', $currentDate);
$month = date('m', $currentDate);
$firstDay = strtotime("$year-$month-01");
$lastDay = strtotime(date('Y-m-t', $firstDay));

// Trova il primo giorno della griglia (lunedì della settimana che contiene il primo del mese)
$startDay = $firstDay;
while (date('N', $startDay) != 1) {
    $startDay = strtotime('-1 day', $startDay);
}

// Trova l'ultimo giorno della griglia (domenica della settimana che contiene l'ultimo del mese)
$endDay = $lastDay;
while (date('N', $endDay) != 7) {
    $endDay = strtotime('+1 day', $endDay);
}

// Initialize color mappings for consistent colors across the view
if (isset($eventi)) {
    // Se abbiamo la lista aziende (da calendario-eventi.php), usala
    if (isset($aziende_list)) {
        CalendarColorHelper::initializeColorMappings($eventi, $aziende_list);
    } else {
        CalendarColorHelper::initializeColorMappings($eventi);
    }
}

// Genera CSS dinamico per i colori
echo CalendarColorHelper::generateColorCSS();

// Organizza eventi per data
$eventiPerData = [];
if (isset($eventi)) {
    foreach ($eventi as $evento) {
        $dataEvento = date('Y-m-d', strtotime($evento['data_inizio']));
        if (!isset($eventiPerData[$dataEvento])) {
            $eventiPerData[$dataEvento] = [];
        }
        $eventiPerData[$dataEvento][] = $evento;
    }
}

// Organizza task per data (se disponibili)
$taskPerData = [];
if (isset($user_tasks) && !empty($user_tasks)) {
    foreach ($user_tasks as $task) {
        $dataInizio = strtotime($task['data_inizio']);
        $dataFine = strtotime($task['data_fine']);
        
        // Aggiungi il task a ogni giorno nel suo intervallo
        $currentData = $dataInizio;
        while ($currentData <= $dataFine) {
            $dataTask = date('Y-m-d', $currentData);
            if (!isset($taskPerData[$dataTask])) {
                $taskPerData[$dataTask] = [];
            }
            $taskPerData[$dataTask][] = $task;
            $currentData = strtotime('+1 day', $currentData);
        }
    }
}

// Giorni della settimana (abbreviati per semplicità)
$giorniSettimana = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
?>

<!-- Calendar styles are generated dynamically -->

<!-- Calendar Legend -->
<?php 
$showIcsIndicator = true; // Show ICS import indicator in legend
include 'components/calendar-legend.php'; 
?>

<!-- Calendario Mensile Semplice -->
<div class="simple-calendar">
    <!-- Intestazione con nome mese -->
    <div class="calendar-month-header">
        <h2><?= ucfirst(date('F Y', $firstDay)) ?></h2>
    </div>
    
    <!-- Griglia del calendario -->
    <div class="calendar-grid">
        <!-- Riga header giorni della settimana -->
        <?php foreach ($giorniSettimana as $giorno): ?>
        <div class="calendar-weekday"><?= $giorno ?></div>
        <?php endforeach; ?>
        
        <!-- Giorni del calendario -->
        <?php
        $current = $startDay;
        while ($current <= $endDay):
            $dayDate = date('Y-m-d', $current);
            $dayNumber = date('j', $current);
            $isCurrentMonth = date('m', $current) == $month;
            $isToday = $dayDate === date('Y-m-d');
            
            // Conta eventi e task per questo giorno
            $dayEvents = $eventiPerData[$dayDate] ?? [];
            $dayTasks = $taskPerData[$dayDate] ?? [];
            $totalItems = count($dayEvents) + count($dayTasks);
            
            // CSS classes per il giorno
            $dayClasses = ['calendar-day'];
            if (!$isCurrentMonth) $dayClasses[] = 'other-month';
            if ($isToday) $dayClasses[] = 'today';
            if ($totalItems > 0) $dayClasses[] = 'has-items';
            
            // Check if day has multiple calendars
            if (!empty($dayEvents)) {
                $multiCalClass = CalendarColorHelper::getMultiCalendarClass($dayEvents);
                if ($multiCalClass) {
                    $dayClasses[] = $multiCalClass;
                }
            }
        ?>
        <div class="<?= implode(' ', $dayClasses) ?>" data-date="<?= $dayDate ?>">
            <div class="day-number"><?= $dayNumber ?></div>
            
            <?php if ($totalItems > 0): ?>
            <div class="day-items">
                <!-- Mostra eventi come badge semplici -->
                <?php 
                $maxVisible = 2; // Massimo 2 elementi visibili per giorno
                $itemsShown = 0;
                
                // Prima mostra gli eventi
                foreach (array_slice($dayEvents, 0, $maxVisible - $itemsShown) as $evento): 
                    $itemsShown++;
                    $colorClass = CalendarColorHelper::getEventColorClass($evento);
                    $sourceClass = CalendarColorHelper::getSourceIndicatorClass($evento);
                ?>
                <div class="event-badge event-<?= htmlspecialchars($evento['tipo']) ?> <?= $colorClass ?> <?= $sourceClass ?>" 
                     data-event-id="<?= $evento['id'] ?>"
                     data-event-json='<?= htmlspecialchars(json_encode($evento), ENT_QUOTES, 'UTF-8') ?>'
                     title="<?= date('H:i', strtotime($evento['data_inizio'])) ?> - <?= htmlspecialchars($evento['titolo']) ?>">
                    <?= htmlspecialchars(substr($evento['titolo'], 0, 12)) ?><?= strlen($evento['titolo']) > 12 ? '...' : '' ?>
                </div>
                <?php endforeach; ?>
                
                <!-- Poi mostra i task se c'è spazio -->
                <?php foreach (array_slice($dayTasks, 0, $maxVisible - $itemsShown) as $task): 
                    $itemsShown++;
                ?>
                <div class="task-badge" 
                     title="Task: <?= htmlspecialchars($task['attivita']) ?> (<?= CalendarHelper::formatTaskDuration($task['giornate_previste']) ?>)">
                    T: <?= htmlspecialchars(substr($task['attivita'], 0, 8)) ?>
                </div>
                <?php endforeach; ?>
                
                <!-- Se ci sono più elementi, mostra il contatore -->
                <?php if ($totalItems > $maxVisible): ?>
                <div class="more-badge">+<?= $totalItems - $maxVisible ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
            $current = strtotime('+1 day', $current);
        endwhile;
        ?>
    </div>
</div>

<style>
/* CSS Calendario Semplice */
.simple-calendar {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.calendar-month-header {
    background: #667eea;
    color: white;
    text-align: center;
    padding: 20px;
}

.calendar-month-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border: 1px solid #e5e7eb;
}

.calendar-weekday {
    background: #f8f9fa;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
    border-bottom: 2px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
}

.calendar-weekday:last-child {
    border-right: none;
}

.calendar-day {
    min-height: 120px;
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
    cursor: pointer;
    transition: background-color 0.2s ease;
    position: relative;
    display: flex;
    flex-direction: column;
}

.calendar-day:nth-child(7n+7) {
    border-right: none;
}

.calendar-day:hover {
    background: #f1f5f9;
}

.calendar-day.other-month {
    background: #f9fafb;
    opacity: 0.6;
}

.calendar-day.today {
    background: #dbeafe;
    border: 2px solid #3b82f6;
}

.calendar-day.has-items {
    background: #f0fdf4;
}

.day-number {
    font-weight: 600;
    font-size: 16px;
    color: #1f2937;
    margin-bottom: 4px;
}

.other-month .day-number {
    color: #9ca3af;
}

.day-items {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.event-badge {
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 500;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
}

/* Colori per diversi tipi di eventi */
.event-riunione, .event-meeting {
    background: #dbeafe;
    color: #1d4ed8;
}

.event-altro, .event-other {
    background: #f3f4f6;
    color: #374151;
}

.event-formazione, .event-training {
    background: #dcfce7;
    color: #166534;
}

.event-conferenza, .event-conference {
    background: #fae8ff;
    color: #a21caf;
}

.task-badge {
    background: #ecfdf5;
    color: #065f46;
    border-left: 3px solid #10b981;
    padding: 2px 4px;
    font-size: 10px;
    font-weight: 600;
    border-radius: 3px;
    cursor: pointer;
}

.more-badge {
    background: #6b7280;
    color: white;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 10px;
    text-align: center;
    margin-top: auto;
}

/* Responsive design */
@media (max-width: 768px) {
    .calendar-day {
        min-height: 80px;
        padding: 4px;
    }
    
    .day-number {
        font-size: 14px;
    }
    
    .event-badge,
    .task-badge {
        font-size: 9px;
        padding: 1px 2px;
    }
    
    .calendar-weekday {
        padding: 8px 4px;
        font-size: 12px;
    }
    
    .calendar-month-header h2 {
        font-size: 20px;
    }
}

@media (max-width: 480px) {
    .calendar-day {
        min-height: 60px;
        padding: 2px;
    }
    
    .day-number {
        font-size: 12px;
        margin-bottom: 2px;
    }
    
    .calendar-weekday {
        padding: 6px 2px;
        font-size: 10px;
    }
    
    /* Su mobile mostra solo punti colorati invece del testo */
    .event-badge,
    .task-badge {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        padding: 0;
        font-size: 0;
        margin: 1px;
    }
    
    .more-badge {
        font-size: 8px;
        padding: 1px 2px;
    }
}
</style>

<!-- Modal Dettagli Evento -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="eventDetailsModalLabel">
                    <i class="fas fa-calendar-day"></i> Dettagli Evento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <div id="eventDetailsContent">
                    <!-- Contenuto dinamico verrà inserito qui -->
                </div>
                
                <?php if ($auth->canManageEvents()): ?>
                <hr class="my-4">
                
                <!-- Form per modificare l'azienda associata -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-building"></i> Modifica Associazione Azienda</h6>
                    </div>
                    <div class="card-body">
                        <form id="updateEventCompanyForm">
                            <input type="hidden" id="eventIdForUpdate" name="event_id">
                            <div class="mb-3">
                                <label for="updateEventCompany" class="form-label">Azienda associata</label>
                                <select class="form-select" id="updateEventCompany" name="azienda_id">
                                    <option value="">🗓️ Nessuna azienda / Calendario personale</option>
                                    <?php
                                    // Carica lista aziende per il select
                                    $aziende_for_modal = [];
                                    if ($isSuperAdmin) {
                                        $aziende_for_modal = $aziende_list;
                                    } else if ($currentAzienda) {
                                        // Per utenti normali, mostra solo l'azienda corrente
                                        $aziende_for_modal[] = $currentAzienda;
                                    }
                                    
                                    foreach ($aziende_for_modal as $azienda): 
                                    ?>
                                    <option value="<?= $azienda['id'] ?>">
                                        <?= htmlspecialchars($azienda['nome']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva Modifica
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetCompanyForm()">
                                    <i class="fas fa-undo"></i> Annulla
                                </button>
                            </div>
                        </form>
                        <div id="updateCompanyResult" class="mt-3" style="display: none;"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <?php if ($auth->canManageEvents()): ?>
                <button type="button" class="btn btn-warning" id="editEventBtn">
                    <i class="fas fa-edit"></i> Modifica Evento
                </button>
                <button type="button" class="btn btn-danger" id="deleteEventBtn">
                    <i class="fas fa-trash"></i> Elimina
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript per il calendario con modal dettagli evento
document.addEventListener('DOMContentLoaded', function() {
    // Assicuriamoci che il modal sia nascosto all'avvio
    const eventDetailsModal = document.getElementById('eventDetailsModal');
    if (eventDetailsModal) {
        // Force hide the modal at page load
        eventDetailsModal.style.display = 'none';
        eventDetailsModal.classList.remove('show');
        eventDetailsModal.setAttribute('aria-hidden', 'true');
        
        // Remove any leftover backdrop
        const existingBackdrop = document.querySelector('.modal-backdrop');
        if (existingBackdrop) {
            existingBackdrop.remove();
        }
        
        // Ensure body doesn't have modal-open class
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        // Setup event listeners for proper modal behavior
        eventDetailsModal.addEventListener('hidden.bs.modal', function () {
            eventDetailsModal.style.display = 'none';
            eventDetailsModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        });
        
        eventDetailsModal.addEventListener('show.bs.modal', function () {
            eventDetailsModal.style.display = 'block';
            eventDetailsModal.removeAttribute('aria-hidden');
        });
        
        eventDetailsModal.addEventListener('shown.bs.modal', function () {
            // Focus management - focus on close button to avoid focus trap
            const closeBtn = eventDetailsModal.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.focus();
            }
        });
    }
    
    // Dati dal PHP
    const eventiPerData = <?= json_encode($eventiPerData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const taskPerData = <?= json_encode($taskPerData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
    const canManageEvents = <?= $auth->canManageEvents() ? 'true' : 'false' ?>;
    const currentUserId = <?= $user['id'] ?>;
    
    // Gestione click sugli eventi
    document.addEventListener('click', function(e) {
        // Click su un event badge
        if (e.target.classList.contains('event-badge')) {
            e.stopPropagation();
            const eventData = e.target.dataset.eventJson;
            if (eventData) {
                try {
                    const evento = JSON.parse(eventData);
                    showEventDetailsModal(evento);
                } catch (error) {
                    console.error('Errore parsing evento:', error);
                }
            }
        }
        
        // Click su un giorno del calendario (se non è stato cliccato un evento)
        const dayElement = e.target.closest('.calendar-day');
        if (dayElement && !e.target.classList.contains('event-badge') && !e.target.classList.contains('task-badge')) {
            const date = dayElement.dataset.date;
            const dayEvents = eventiPerData[date] || [];
            const dayTasks = taskPerData[date] || [];
            
            if (dayEvents.length === 0 && dayTasks.length === 0) {
                // Nessun evento, proponi di crearne uno se l'utente ha permessi
                <?php if ($auth->canManageEvents()): ?>
                if (confirm(`Nessun evento il ${date}. Vuoi creare un nuovo evento?`)) {
                    window.location.href = `?action=nuovo&date=${date}`;
                }
                <?php endif; ?>
            }
        }
    });
    
    // Funzione per mostrare il modal con i dettagli dell'evento
    function showEventDetailsModal(evento) {
        const modal = document.getElementById('eventDetailsModal');
        const contentDiv = document.getElementById('eventDetailsContent');
        
        // Formatta le date
        const dataInizio = new Date(evento.data_inizio);
        const dataFine = evento.data_fine ? new Date(evento.data_fine) : dataInizio;
        
        const formatDate = (date) => {
            return date.toLocaleDateString('it-IT', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };
        
        const formatTime = (date) => {
            return date.toLocaleTimeString('it-IT', {
                hour: '2-digit',
                minute: '2-digit'
            });
        };
        
        // Determina il colore/tipo dell'evento
        let tipoEvento = evento.tipo || 'evento';
        let colorBadge = 'badge-secondary';
        switch(tipoEvento) {
            case 'meeting':
            case 'riunione':
                colorBadge = 'badge-primary';
                break;
            case 'formazione':
            case 'training':
                colorBadge = 'badge-success';
                break;
            case 'conferenza':
            case 'conference':
                colorBadge = 'badge-info';
                break;
            case 'scadenza':
            case 'deadline':
                colorBadge = 'badge-warning';
                break;
            case 'compleanno':
            case 'birthday':
                colorBadge = 'badge-danger';
                break;
        }
        
        // Costruisci il contenuto HTML
        let html = `
            <div class="event-details">
                <h4 class="mb-3">
                    ${evento.titolo || 'Evento senza titolo'}
                    <span class="badge ${colorBadge} ms-2">${tipoEvento}</span>
                </h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-item">
                            <i class="fas fa-calendar text-primary me-2"></i>
                            <strong>Data inizio:</strong><br>
                            <span class="ms-4">${formatDate(dataInizio)}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item">
                            <i class="fas fa-clock text-primary me-2"></i>
                            <strong>Ora:</strong><br>
                            <span class="ms-4">${formatTime(dataInizio)}</span>
                        </div>
                    </div>
                </div>
                
                ${evento.data_fine && evento.data_fine !== evento.data_inizio ? `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="detail-item">
                            <i class="fas fa-calendar-check text-success me-2"></i>
                            <strong>Data fine:</strong><br>
                            <span class="ms-4">${formatDate(dataFine)}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item">
                            <i class="fas fa-clock text-success me-2"></i>
                            <strong>Ora fine:</strong><br>
                            <span class="ms-4">${formatTime(dataFine)}</span>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                ${evento.luogo ? `
                <div class="detail-item mb-3">
                    <i class="fas fa-map-marker-alt text-danger me-2"></i>
                    <strong>Luogo:</strong> ${evento.luogo}
                </div>
                ` : ''}
                
                ${evento.nome_azienda ? `
                <div class="detail-item mb-3">
                    <i class="fas fa-building text-info me-2"></i>
                    <strong>Azienda:</strong> ${evento.nome_azienda}
                </div>
                ` : `
                <div class="detail-item mb-3">
                    <i class="fas fa-user text-secondary me-2"></i>
                    <strong>Calendario:</strong> Personale (senza azienda)
                </div>
                `}
                
                ${evento.descrizione ? `
                <div class="detail-item mb-3">
                    <i class="fas fa-align-left text-secondary me-2"></i>
                    <strong>Descrizione:</strong><br>
                    <div class="ms-4 mt-2">${evento.descrizione.replace(/\n/g, '<br>')}</div>
                </div>
                ` : ''}
                
                ${evento.num_partecipanti > 0 ? `
                <div class="detail-item mb-3">
                    <i class="fas fa-users text-warning me-2"></i>
                    <strong>Partecipanti:</strong> ${evento.num_partecipanti}
                </div>
                ` : ''}
                
                ${evento.creatore_nome ? `
                <div class="detail-item text-muted small">
                    <i class="fas fa-user-plus me-2"></i>
                    Creato da: ${evento.creatore_nome} ${evento.creatore_cognome || ''}
                </div>
                ` : ''}
            </div>
        `;
        
        contentDiv.innerHTML = html;
        
        // Imposta l'ID evento per il form di aggiornamento
        if (canManageEvents) {
            document.getElementById('eventIdForUpdate').value = evento.id;
            document.getElementById('updateEventCompany').value = evento.azienda_id || '';
            
            // Setup pulsanti modifica/elimina
            const editBtn = document.getElementById('editEventBtn');
            const deleteBtn = document.getElementById('deleteEventBtn');
            
            if (editBtn) {
                editBtn.onclick = function() {
                    window.location.href = `?action=modifica&id=${evento.id}`;
                };
            }
            
            if (deleteBtn) {
                deleteBtn.onclick = function() {
                    if (confirm('Sei sicuro di voler eliminare questo evento?')) {
                        // Ottieni il CSRF token
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        
                        // Crea un form per l'eliminazione con metodo POST
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `?action=elimina&id=${evento.id}`;
                        
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = csrfToken;
                        form.appendChild(csrfInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                };
            }
        }
        
        // Mostra il modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    // Gestione form aggiornamento azienda
    const updateForm = document.getElementById('updateEventCompanyForm');
    if (updateForm) {
        updateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            // Mostra loading
            const resultDiv = document.getElementById('updateCompanyResult');
            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Aggiornamento in corso...</div>';
            resultDiv.style.display = 'block';
            
            fetch('<?= APP_PATH ?>/backend/api/update-event-company.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Azienda aggiornata con successo!</div>';
                    // Ricarica la pagina dopo 1.5 secondi
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${data.error || 'Errore durante l\'aggiornamento'}</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Errore di rete</div>';
                console.error('Errore:', error);
            });
        });
    }
    
    console.log('Calendario mensile con modal dettagli inizializzato');
});

function resetCompanyForm() {
    const form = document.getElementById('updateEventCompanyForm');
    if (form) {
        form.reset();
        document.getElementById('updateCompanyResult').style.display = 'none';
    }
}
</script>