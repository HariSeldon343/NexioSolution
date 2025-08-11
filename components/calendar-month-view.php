<?php
/**
 * Vista calendario mensile SEMPLICE
 * Griglia calendario con eventi visualizzati come badge
 */

// Include calendar helper
require_once 'backend/utils/CalendarHelper.php';

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
                ?>
                <div class="event-badge event-<?= htmlspecialchars($evento['tipo']) ?>" 
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

<script>
// JavaScript semplice per il calendario
document.addEventListener('DOMContentLoaded', function() {
    const calendarDays = document.querySelectorAll('.calendar-day');
    
    // Eventi per data dal PHP
    const eventiPerData = <?= json_encode($eventiPerData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const taskPerData = <?= json_encode($taskPerData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    
    // Aggiungi click listener per ogni giorno
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            const date = this.dataset.date;
            
            // Se il giorno ha eventi o task, mostra un alert semplice (per ora)
            const dayEvents = eventiPerData[date] || [];
            const dayTasks = taskPerData[date] || [];
            const totalItems = dayEvents.length + dayTasks.length;
            
            if (totalItems > 0) {
                let message = `Giorno ${date}:\n\n`;
                
                if (dayEvents.length > 0) {
                    message += `EVENTI (${dayEvents.length}):\n`;
                    dayEvents.forEach((evento, index) => {
                        const time = new Date(evento.data_inizio).toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'});
                        message += `• ${time} - ${evento.titolo}\n`;
                    });
                    message += '\n';
                }
                
                if (dayTasks.length > 0) {
                    message += `TASK (${dayTasks.length}):\n`;
                    dayTasks.forEach((task, index) => {
                        message += `• ${task.attivita} (${task.giornate_previste}gg)\n`;
                    });
                }
                
                alert(message);
            } else {
                // Nessun evento, proponi di crearne uno se l'utente ha permessi
                <?php if ($auth->canManageEvents()): ?>
                if (confirm(`Nessun evento il ${date}. Vuoi creare un nuovo evento?`)) {
                    window.location.href = `?action=nuovo&date=${date}`;
                }
                <?php else: ?>
                alert(`Nessun evento il ${date}`);
                <?php endif; ?>
            }
        });
    });
    
    console.log('Calendario mensile semplice inizializzato');
});
</script>