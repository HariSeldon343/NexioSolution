<?php
/**
 * Vista calendario settimanale
 */

// Include calendar helper
require_once 'backend/utils/CalendarHelper.php';

// Calcola date della settimana
$currentDate = strtotime($date);
$startWeek = strtotime('monday this week', $currentDate);
$endWeek = strtotime('sunday this week', $currentDate);

// Organizza eventi per data e ora
$eventiPerData = [];
foreach ($eventi as $evento) {
    $dataEvento = date('Y-m-d', strtotime($evento['data_inizio']));
    if (!isset($eventiPerData[$dataEvento])) {
        $eventiPerData[$dataEvento] = [];
    }
    $eventiPerData[$dataEvento][] = $evento;
}

// Organizza task per data (se l'utente può vederli)
$taskPerData = [];
if (isset($user_tasks) && !empty($user_tasks)) {
    foreach ($user_tasks as $task) {
        $dataInizio = strtotime($task['data_inizio']);
        $dataFine = strtotime($task['data_fine']);
        
        // Aggiungi il task a ogni giorno nel suo intervallo
        $currentData = $dataInizio;
        while ($currentData <= $dataFine) {
            $dataTask = date('Y-m-d', $currentData);
            // Solo includi se è nella settimana corrente
            if ($currentData >= $startWeek && $currentData <= $endWeek) {
                if (!isset($taskPerData[$dataTask])) {
                    $taskPerData[$dataTask] = [];
                }
                $taskPerData[$dataTask][] = $task;
            }
            $currentData = strtotime('+1 day', $currentData);
        }
    }
}

// Genera i giorni della settimana
$giorniSettimana = [];
for ($i = 0; $i < 7; $i++) {
    $giorno = strtotime("+$i days", $startWeek);
    $giorniSettimana[] = $giorno;
}

// Ore del giorno (8:00 - 20:00)
$ore = range(8, 20);
?>

<div class="calendar-week-view">
    <div class="week-header">
        <div class="week-dates">
            <?= date('d', $startWeek) ?> - <?= date('d F Y', $endWeek) ?>
        </div>
    </div>
    
    <div class="week-grid">
        <!-- Header giorni -->
        <div class="week-days-header">
            <div class="time-column-header">Ora</div>
            <?php foreach ($giorniSettimana as $giorno): 
                $isToday = date('Y-m-d', $giorno) === date('Y-m-d');
                $dayClasses = ['day-header'];
                if ($isToday) $dayClasses[] = 'today';
            ?>
            <div class="<?= implode(' ', $dayClasses) ?>" data-date="<?= date('Y-m-d', $giorno) ?>">
                <div class="day-name"><?= date('D', $giorno) ?></div>
                <div class="day-number"><?= date('j', $giorno) ?></div>
                <div class="events-count">
                    <?php 
                    $dayEvents = $eventiPerData[date('Y-m-d', $giorno)] ?? [];
                    if (count($dayEvents) > 0) {
                        echo count($dayEvents) . ' eventi';
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Griglia oraria -->
        <div class="week-time-grid">
            <?php foreach ($ore as $ora): ?>
            <div class="time-row">
                <div class="time-label"><?= sprintf('%02d:00', $ora) ?></div>
                
                <?php foreach ($giorniSettimana as $giorno): 
                    $dataGiorno = date('Y-m-d', $giorno);
                    $eventiOra = [];
                    
                    // Trova eventi per questa ora
                    if (isset($eventiPerData[$dataGiorno])) {
                        foreach ($eventiPerData[$dataGiorno] as $evento) {
                            $oraEvento = intval(date('H', strtotime($evento['data_inizio'])));
                            if ($oraEvento == $ora) {
                                $eventiOra[] = $evento;
                            }
                        }
                    }
                    
                    // Aggiungi task per questo giorno (mostrati alla prima ora disponibile)
                    $dayTasks = [];
                    if ($ora == 8 && isset($taskPerData[$dataGiorno])) {
                        $dayTasks = $taskPerData[$dataGiorno];
                    }
                ?>
                <div class="time-slot" data-date="<?= $dataGiorno ?>" data-hour="<?= $ora ?>">
                    <?php foreach ($eventiOra as $evento): 
                        $startTime = date('H:i', strtotime($evento['data_inizio']));
                        $endTime = $evento['data_fine'] ? date('H:i', strtotime($evento['data_fine'])) : '';
                        $duration = $evento['data_fine'] ? 
                            (strtotime($evento['data_fine']) - strtotime($evento['data_inizio'])) / 3600 : 1;
                        $height = max(30, $duration * 50); // Min 30px, 50px per ora
                    ?>
                    <div class="week-event event-type-<?= $evento['tipo'] ?>" 
                         style="height: <?= $height ?>px;"
                         title="<?= htmlspecialchars($evento['titolo']) ?>"
                         data-event-id="<?= $evento['id'] ?>">
                        <div class="event-time"><?= $startTime ?><?= $endTime ? '-' . $endTime : '' ?></div>
                        <div class="event-title"><?= htmlspecialchars($evento['titolo']) ?></div>
                        <?php if ($evento['luogo']): ?>
                        <div class="event-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($evento['luogo']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($auth->canManageEvents() && ($auth->canViewAllEvents() || $evento['creato_da'] == $user['id'])): ?>
                        <div class="event-actions">
                            <a href="?action=modifica&id=<?= $evento['id'] ?>" class="event-action-btn">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php 
                    // Mostra i task solo nella prima ora del giorno
                    foreach ($dayTasks as $task): 
                        $prodottoServizio = $task['prodotto_servizio'] ?? 'Non specificato';
                    ?>
                    <div class="week-event event-task" 
                         title="Task: <?= htmlspecialchars($task['attivita']) ?> - <?= htmlspecialchars($prodottoServizio) ?>"
                         data-task-id="<?= $task['id'] ?>">
                        <div class="event-type">TASK</div>
                        <div class="event-title"><?= htmlspecialchars($task['attivita']) ?></div>
                        <div class="event-details">
                            <?= htmlspecialchars($task['giornate_previste']) ?> gg - <?= htmlspecialchars($task['utente_nome']) ?>
                        </div>
                        
                        <?php if ($auth->isSuperAdmin()): ?>
                        <div class="event-actions">
                            <a href="?action=modifica_task&id=<?= $task['id'] ?>" class="event-action-btn">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?action=elimina_task&id=<?= $task['id'] ?>" class="event-action-btn delete-btn"
                               onclick="return confirm('Sei sicuro di voler eliminare questo task?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.calendar-week-view {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.week-header {
    padding: 20px;
    background: #4299e1;
    color: white;
    text-align: center;
}

.week-dates {
    font-size: 20px;
    font-weight: 600;
}

.week-grid {
    border: 1px solid #e2e8f0;
}

.week-days-header {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    background: #f7fafc;
    border-bottom: 2px solid #e2e8f0;
}

.time-column-header {
    padding: 15px 10px;
    text-align: center;
    font-weight: 600;
    color: #4a5568;
    border-right: 1px solid #e2e8f0;
    background: #edf2f7;
}

.day-header {
    padding: 15px 10px;
    text-align: center;
    border-right: 1px solid #e2e8f0;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.day-header:hover {
    background: #e2e8f0;
}

.day-header.today {
    background: #bee3f8;
    color: #2b6cb0;
    font-weight: 600;
}

.day-name {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: #718096;
    margin-bottom: 5px;
}

.day-number {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 5px;
}

.today .day-number {
    color: #2b6cb0;
}

.events-count {
    font-size: 11px;
    color: #718096;
}

.today .events-count {
    color: #2b6cb0;
}

.week-time-grid {
    max-height: 650px;
    overflow-y: auto;
}

.time-row {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    min-height: 60px;
    border-bottom: 1px solid #f1f5f9;
}

.time-label {
    padding: 10px;
    text-align: center;
    font-size: 12px;
    font-weight: 500;
    color: #718096;
    background: #f8f9fa;
    border-right: 1px solid #e2e8f0;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}

.time-slot {
    border-right: 1px solid #f1f5f9;
    padding: 2px;
    cursor: pointer;
    transition: background-color 0.2s ease;
    position: relative;
    min-height: 58px;
}

.time-slot:hover {
    background: #f7fafc;
}

.time-slot:last-child {
    border-right: none;
}

.week-event {
    background: #4299e1;
    color: white;
    border-radius: 6px;
    padding: 6px 8px;
    margin: 1px 0;
    font-size: 12px;
    line-height: 1.3;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.week-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.event-time {
    font-weight: 600;
    font-size: 11px;
    margin-bottom: 2px;
    opacity: 0.9;
}

.event-title {
    font-weight: 500;
    margin-bottom: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.event-location {
    font-size: 10px;
    opacity: 0.8;
    display: flex;
    align-items: center;
    gap: 3px;
}

.event-actions {
    position: absolute;
    top: 2px;
    right: 2px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.week-event:hover .event-actions {
    opacity: 1;
}

.event-action-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 3px;
    padding: 2px 4px;
    color: white;
    cursor: pointer;
    font-size: 10px;
    text-decoration: none;
    transition: background-color 0.2s ease;
}

.event-action-btn:hover {
    background: rgba(255,255,255,0.3);
}

/* Colori per tipi di evento */
.event-type-meeting { background: #4299e1; }
.event-type-presentation { background: #ed64a6; }
.event-type-training { background: #48bb78; }
.event-type-workshop { background: #ed8936; }
.event-type-conference { background: #9f7aea; }
.event-type-social { background: #ecc94b; color: #744210; }
.event-type-other { background: #a0aec0; }

/* Task styling */
.event-task {
    background: #48bb78 !important;
    color: white;
    border-left: 4px solid #22543d;
}

.event-task .event-type {
    background: rgba(0,0,0,0.2);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 700;
    display: inline-block;
    margin-bottom: 4px;
}

.event-task .event-details {
    font-size: 10px;
    opacity: 0.9;
    margin-top: 4px;
}

.event-task .delete-btn {
    background: rgba(229, 62, 62, 0.3);
}

.event-task .delete-btn:hover {
    background: rgba(229, 62, 62, 0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .week-days-header {
        grid-template-columns: 60px repeat(7, 1fr);
    }
    
    .time-row {
        grid-template-columns: 60px repeat(7, 1fr);
    }
    
    .time-column-header,
    .time-label {
        padding: 8px 5px;
        font-size: 11px;
    }
    
    .day-header {
        padding: 10px 5px;
    }
    
    .day-name {
        font-size: 10px;
    }
    
    .day-number {
        font-size: 14px;
    }
    
    .events-count {
        font-size: 9px;
    }
    
    .week-event {
        padding: 4px 6px;
        font-size: 10px;
    }
    
    .event-time {
        font-size: 9px;
    }
    
    .event-title {
        font-size: 10px;
    }
    
    .event-location {
        display: none; /* Nascondi location su mobile */
    }
}

@media (max-width: 480px) {
    .week-dates {
        font-size: 16px;
    }
    
    .week-days-header {
        grid-template-columns: 50px repeat(7, 1fr);
    }
    
    .time-row {
        grid-template-columns: 50px repeat(7, 1fr);
        min-height: 50px;
    }
    
    .time-slot {
        min-height: 48px;
    }
    
    .time-label {
        padding: 5px 2px;
        font-size: 10px;
    }
    
    .day-header {
        padding: 8px 3px;
    }
    
    .day-name {
        margin-bottom: 3px;
    }
    
    .day-number {
        font-size: 12px;
        margin-bottom: 3px;
    }
    
    .events-count {
        font-size: 8px;
    }
    
    .week-event {
        padding: 3px 4px;
        font-size: 9px;
    }
    
    .event-title {
        font-size: 9px;
    }
    
    .event-time {
        font-size: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Click sui time slot per aggiungere eventi
    const timeSlots = document.querySelectorAll('.time-slot');
    
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function(e) {
            // Evita il click se è stato fatto su un evento
            if (e.target.closest('.week-event')) {
                return;
            }
            
            <?php if ($auth->canManageEvents()): ?>
            const date = this.dataset.date;
            const hour = this.dataset.hour;
            
            // Reindirizza alla creazione evento con data e ora preimpostate
            const url = new URL('<?= APP_PATH ?>/calendario-eventi.php', window.location.origin);
            url.searchParams.set('action', 'nuovo');
            url.searchParams.set('date', date);
            url.searchParams.set('time', hour + ':00');
            
            window.location.href = url.toString();
            <?php endif; ?>
        });
    });
    
    // Click sui giorni header
    const dayHeaders = document.querySelectorAll('.day-header');
    
    dayHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const date = this.dataset.date;
            
            // Vai alla vista giornaliera
            const url = new URL(window.location.href);
            url.searchParams.set('view', 'day');
            url.searchParams.set('date', date);
            
            window.location.href = url.toString();
        });
    });
    
    // Click sugli eventi
    const weekEvents = document.querySelectorAll('.week-event');
    
    weekEvents.forEach(event => {
        event.addEventListener('click', function(e) {
            // Evita il click se è stato fatto su un'azione
            if (e.target.closest('.event-action-btn')) {
                return;
            }
            
            const eventId = this.dataset.eventId;
            
            <?php if ($auth->canManageEvents()): ?>
            // Vai alla modifica evento
            window.location.href = '?action=modifica&id=' + eventId;
            <?php endif; ?>
        });
    });
});
</script>