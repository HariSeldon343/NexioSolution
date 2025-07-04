<?php
/**
 * Vista calendario mensile
 */

// Calcola date per il mese
$currentDate = strtotime($date);
$year = date('Y', $currentDate);
$month = date('m', $currentDate);
$firstDay = strtotime("$year-$month-01");
$lastDay = strtotime(date('Y-m-t', $firstDay));

// Trova il primo lunedì della griglia (potrebbe essere del mese precedente)
$startDay = $firstDay;
while (date('N', $startDay) != 1) {
    $startDay = strtotime('-1 day', $startDay);
}

// Trova l'ultima domenica della griglia (potrebbe essere del mese successivo)
$endDay = $lastDay;
while (date('N', $endDay) != 7) {
    $endDay = strtotime('+1 day', $endDay);
}

// Organizza eventi per data
$eventiPerData = [];
foreach ($eventi as $evento) {
    $dataEvento = date('Y-m-d', strtotime($evento['data_inizio']));
    if (!isset($eventiPerData[$dataEvento])) {
        $eventiPerData[$dataEvento] = [];
    }
    $eventiPerData[$dataEvento][] = $evento;
}

// Nomi giorni della settimana
$giorniSettimana = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
?>

<div class="calendar-month-view">
    <div class="calendar-header">
        <h2><?= date('F Y', $firstDay) ?></h2>
    </div>
    
    <div class="calendar-grid">
        <!-- Header giorni settimana -->
        <div class="calendar-weekdays">
            <?php foreach ($giorniSettimana as $giorno): ?>
            <div class="weekday-header"><?= $giorno ?></div>
            <?php endforeach; ?>
        </div>
        
        <!-- Griglia giorni -->
        <div class="calendar-days">
            <?php
            $current = $startDay;
            while ($current <= $endDay):
                $dayDate = date('Y-m-d', $current);
                $isCurrentMonth = date('m', $current) == $month;
                $isToday = $dayDate === date('Y-m-d');
                $isSelected = $dayDate === $date;
                $dayEvents = $eventiPerData[$dayDate] ?? [];
                
                $dayClasses = ['calendar-day'];
                if (!$isCurrentMonth) $dayClasses[] = 'other-month';
                if ($isToday) $dayClasses[] = 'today';
                if ($isSelected) $dayClasses[] = 'selected';
                if (!empty($dayEvents)) $dayClasses[] = 'has-events';
            ?>
            <div class="<?= implode(' ', $dayClasses) ?>" data-date="<?= $dayDate ?>">
                <div class="day-header">
                    <span class="day-number"><?= date('j', $current) ?></span>
                    <?php if (!empty($dayEvents)): ?>
                    <span class="events-count"><?= count($dayEvents) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="day-events">
                    <?php 
                    $maxVisibleEvents = 3;
                    $visibleEvents = array_slice($dayEvents, 0, $maxVisibleEvents);
                    $hiddenCount = count($dayEvents) - $maxVisibleEvents;
                    
                    foreach ($visibleEvents as $evento): 
                    ?>
                    <div class="day-event event-type-<?= $evento['tipo'] ?>" 
                         title="<?= htmlspecialchars($evento['titolo']) . ($evento['luogo'] ? ' - ' . htmlspecialchars($evento['luogo']) : '') ?>">
                        <span class="event-time"><?= date('H:i', strtotime($evento['data_inizio'])) ?></span>
                        <span class="event-title"><?= htmlspecialchars(substr($evento['titolo'], 0, 25)) ?><?= strlen($evento['titolo']) > 25 ? '...' : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($hiddenCount > 0): ?>
                    <div class="more-events">
                        +<?= $hiddenCount ?> altro<?= $hiddenCount > 1 ? 'i' : '' ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
                $current = strtotime('+1 day', $current);
            endwhile;
            ?>
        </div>
    </div>
</div>

<!-- Modal dettaglio giorno -->
<div id="dayModal" class="day-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="dayModalTitle"></h3>
            <button type="button" class="modal-close" onclick="closeDayModal()">×</button>
        </div>
        <div class="modal-body" id="dayModalBody">
            <!-- Eventi del giorno verranno caricati qui -->
        </div>
        <div class="modal-footer">
            <?php if ($auth->canManageEvents()): ?>
            <a href="#" id="newEventLink" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuovo Evento
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" onclick="closeDayModal()">Chiudi</button>
        </div>
    </div>
</div>

<style>
.calendar-month-view {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-header {
    padding: 20px;
    background: #4299e1;
    color: white;
    text-align: center;
}

.calendar-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    text-transform: capitalize;
}

.calendar-grid {
    border: 1px solid #e2e8f0;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f7fafc;
    border-bottom: 1px solid #e2e8f0;
}

.weekday-header {
    padding: 15px 10px;
    text-align: center;
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
    border-right: 1px solid #e2e8f0;
}

.weekday-header:last-child {
    border-right: none;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: minmax(120px, auto);
}

.calendar-day {
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px;
    cursor: pointer;
    transition: background-color 0.2s ease;
    position: relative;
    min-height: 120px;
    display: flex;
    flex-direction: column;
}

.calendar-day:nth-child(7n) {
    border-right: none;
}

.calendar-day:hover {
    background: #f7fafc;
}

.calendar-day.other-month {
    background: #f8f9fa;
    color: #a0aec0;
}

.calendar-day.today {
    background: #e6fffa;
    border: 2px solid #38b2ac;
}

.calendar-day.selected {
    background: #bee3f8;
    border: 2px solid #4299e1;
}

.calendar-day.has-events {
    background: #f0fff4;
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.day-number {
    font-weight: 600;
    font-size: 16px;
    color: #2d3748;
}

.other-month .day-number {
    color: #a0aec0;
}

.events-count {
    background: #4299e1;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
}

.day-events {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.day-event {
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 11px;
    line-height: 1.2;
    cursor: pointer;
    transition: opacity 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.day-event:hover {
    opacity: 0.8;
}

.event-time {
    font-weight: 600;
    opacity: 0.8;
}

.event-title {
    font-weight: 500;
}

/* Colori per tipi di evento */
.event-type-meeting { background: #bee3f8; color: #2b6cb0; }
.event-type-presentation { background: #fbb6ce; color: #b83280; }
.event-type-training { background: #c6f6d5; color: #22543d; }
.event-type-workshop { background: #fed7a8; color: #c05621; }
.event-type-conference { background: #e9d8fd; color: #553c9a; }
.event-type-social { background: #fef5e7; color: #975a16; }
.event-type-other { background: #e2e8f0; color: #4a5568; }

.more-events {
    padding: 2px 6px;
    background: #4a5568;
    color: white;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    margin-top: auto;
}

/* Modal */
.day-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.day-modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 20px;
    background: #f7fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
    font-size: 20px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-footer {
    padding: 20px;
    background: #f7fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.day-modal-event {
    padding: 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.2s ease;
}

.day-modal-event:hover {
    border-color: #4299e1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.day-modal-event:last-child {
    margin-bottom: 0;
}

.modal-event-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.modal-event-title {
    font-weight: 600;
    color: #2d3748;
    font-size: 16px;
    margin: 0;
}

.modal-event-time {
    color: #718096;
    font-size: 14px;
    font-weight: 500;
}

.modal-event-description {
    color: #4a5568;
    margin-bottom: 10px;
    line-height: 1.4;
}

.modal-event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 13px;
    color: #718096;
}

.modal-event-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
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

/* Responsive */
@media (max-width: 768px) {
    .calendar-days {
        grid-auto-rows: minmax(80px, auto);
    }
    
    .calendar-day {
        min-height: 80px;
        padding: 5px;
    }
    
    .day-number {
        font-size: 14px;
    }
    
    .day-event {
        font-size: 10px;
        padding: 2px 4px;
    }
    
    .event-title {
        display: none; /* Mostra solo orario su mobile */
    }
    
    .weekday-header {
        padding: 10px 5px;
        font-size: 12px;
    }
    
    .calendar-header h2 {
        font-size: 20px;
    }
    
    .modal-content {
        width: 95%;
        margin: 10px;
    }
}

@media (max-width: 480px) {
    .weekday-header {
        padding: 8px 2px;
        font-size: 11px;
    }
    
    .calendar-days {
        grid-auto-rows: minmax(60px, auto);
    }
    
    .calendar-day {
        min-height: 60px;
        padding: 3px;
    }
    
    .day-number {
        font-size: 12px;
    }
    
    .events-count {
        width: 16px;
        height: 16px;
        font-size: 10px;
    }
}
</style>

<script>
// Gestione click sui giorni
document.addEventListener('DOMContentLoaded', function() {
    const calendarDays = document.querySelectorAll('.calendar-day');
    const dayModal = document.getElementById('dayModal');
    const dayModalTitle = document.getElementById('dayModalTitle');
    const dayModalBody = document.getElementById('dayModalBody');
    const newEventLink = document.getElementById('newEventLink');
    
    // Eventi per data (passati dal PHP)
    const eventiPerData = <?= json_encode($eventiPerData) ?>;
    
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            const date = this.dataset.date;
            const dayEvents = eventiPerData[date] || [];
            
            // Aggiorna titolo modal
            const dayDate = new Date(date);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dayModalTitle.textContent = dayDate.toLocaleDateString('it-IT', options);
            
            // Aggiorna link nuovo evento
            if (newEventLink) {
                newEventLink.href = '?action=nuovo&date=' + date;
            }
            
            // Aggiorna contenuto modal
            if (dayEvents.length === 0) {
                dayModalBody.innerHTML = '<p style="text-align: center; color: #718096; padding: 20px;">Nessun evento in questa data</p>';
            } else {
                let html = '';
                dayEvents.forEach(evento => {
                    const startTime = new Date(evento.data_inizio).toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'});
                    const endTime = evento.data_fine ? new Date(evento.data_fine).toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'}) : '';
                    
                    html += `
                        <div class="day-modal-event event-type-${evento.tipo}">
                            <div class="modal-event-header">
                                <h4 class="modal-event-title">${escapeHtml(evento.titolo)}</h4>
                                <span class="modal-event-time">${startTime}${endTime ? ' - ' + endTime : ''}</span>
                            </div>
                            ${evento.descrizione ? `<p class="modal-event-description">${escapeHtml(evento.descrizione)}</p>` : ''}
                            <div class="modal-event-meta">
                                ${evento.luogo ? `<span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(evento.luogo)}</span>` : ''}
                                <span><i class="fas fa-user"></i> ${escapeHtml(evento.creatore_nome + ' ' + evento.creatore_cognome)}</span>
                                ${evento.num_partecipanti > 0 ? `<span><i class="fas fa-users"></i> ${evento.num_partecipanti} partecipanti</span>` : ''}
                            </div>
                        </div>
                    `;
                });
                dayModalBody.innerHTML = html;
            }
            
            // Mostra modal
            dayModal.classList.add('active');
        });
    });
    
    // Chiudi modal cliccando fuori
    dayModal.addEventListener('click', function(e) {
        if (e.target === dayModal) {
            closeDayModal();
        }
    });
});

function closeDayModal() {
    document.getElementById('dayModal').classList.remove('active');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>