<?php
/**
 * Calendario Content - Mobile Version
 * Calendario completo come versione desktop con vista mensile, settimanale e giornaliera
 */

// Parametri vista
$view = $_GET['view'] ?? 'month'; // month, week, day, list
$currentDate = isset($_GET['date']) ? strtotime($_GET['date']) : time();
$year = date('Y', $currentDate);
$month = date('m', $currentDate);
$day = date('d', $currentDate);

// Carica eventi del periodo corrente
$eventi = [];

try {
    $aziendaId = $currentAzienda['id'] ?? null;
    
    // Query base per eventi
    $query = "SELECT e.*, 
              u.nome as creatore_nome,
              u.cognome as creatore_cognome,
              a.nome as azienda_nome
              FROM eventi e
              LEFT JOIN utenti u ON e.creato_da = u.id
              LEFT JOIN aziende a ON e.azienda_id = a.id
              WHERE 1=1";
    
    // Filtra per periodo in base alla vista
    switch ($view) {
        case 'day':
            $startDate = date('Y-m-d 00:00:00', $currentDate);
            $endDate = date('Y-m-d 23:59:59', $currentDate);
            $query .= " AND ((e.data_inizio >= ? AND e.data_inizio <= ?) 
                        OR (e.data_fine >= ? AND e.data_fine <= ?)
                        OR (e.data_inizio <= ? AND e.data_fine >= ?))";
            break;
            
        case 'week':
            $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week', $currentDate));
            $weekEnd = date('Y-m-d 23:59:59', strtotime('sunday this week', $currentDate));
            $query .= " AND ((e.data_inizio >= ? AND e.data_inizio <= ?) 
                        OR (e.data_fine >= ? AND e.data_fine <= ?)
                        OR (e.data_inizio <= ? AND e.data_fine >= ?))";
            break;
            
        case 'month':
        default:
            $monthStart = date('Y-m-01 00:00:00', $currentDate);
            $monthEnd = date('Y-m-t 23:59:59', $currentDate);
            $query .= " AND ((e.data_inizio >= ? AND e.data_inizio <= ?) 
                        OR (e.data_fine >= ? AND e.data_fine <= ?)
                        OR (e.data_inizio <= ? AND e.data_fine >= ?))";
            break;
    }
    
    // Filtra per azienda se non super admin
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (e.azienda_id = ? OR e.azienda_id IS NULL)";
    }
    
    $query .= " ORDER BY e.data_inizio ASC";
    
    // Prepara ed esegui query
    $stmt = $pdo->prepare($query);
    
    switch ($view) {
        case 'day':
            if (!$isSuperAdmin && $aziendaId) {
                $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $aziendaId]);
            } else {
                $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
            }
            break;
            
        case 'week':
            if (!$isSuperAdmin && $aziendaId) {
                $stmt->execute([$weekStart, $weekEnd, $weekStart, $weekEnd, $weekStart, $weekEnd, $aziendaId]);
            } else {
                $stmt->execute([$weekStart, $weekEnd, $weekStart, $weekEnd, $weekStart, $weekEnd]);
            }
            break;
            
        case 'month':
        default:
            if (!$isSuperAdmin && $aziendaId) {
                $stmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd, $monthStart, $monthEnd, $aziendaId]);
            } else {
                $stmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd, $monthStart, $monthEnd]);
            }
            break;
    }
    
    $eventi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Log error
}

// Funzioni helper
function getMonthName($month) {
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
             'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    return $mesi[intval($month)];
}

function getDayName($day) {
    $giorni = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    return $giorni[$day];
}

function getEventColor($tipo) {
    switch ($tipo) {
        case 'riunione': return '#3b82f6';
        case 'scadenza': return '#ef4444';
        case 'promemoria': return '#f59e0b';
        case 'formazione': return '#8b5cf6';
        default: return '#10b981';
    }
}
?>

<!-- Calendar Header -->
<div class="calendar-container">
    <div class="calendar-header">
        <div class="calendar-month">
            <?php 
            if ($view == 'day') {
                echo date('d', $currentDate) . ' ' . getMonthName($month) . ' ' . $year;
            } elseif ($view == 'week') {
                $weekStart = strtotime('monday this week', $currentDate);
                $weekEnd = strtotime('sunday this week', $currentDate);
                echo date('d', $weekStart) . '-' . date('d', $weekEnd) . ' ' . getMonthName($month) . ' ' . $year;
            } else {
                echo getMonthName($month) . ' ' . $year;
            }
            ?>
        </div>
        <div class="calendar-nav">
            <?php
            $prevDate = '';
            $nextDate = '';
            
            switch ($view) {
                case 'day':
                    $prevDate = date('Y-m-d', strtotime('-1 day', $currentDate));
                    $nextDate = date('Y-m-d', strtotime('+1 day', $currentDate));
                    break;
                case 'week':
                    $prevDate = date('Y-m-d', strtotime('-1 week', $currentDate));
                    $nextDate = date('Y-m-d', strtotime('+1 week', $currentDate));
                    break;
                case 'month':
                default:
                    $prevDate = date('Y-m-d', strtotime('-1 month', $currentDate));
                    $nextDate = date('Y-m-d', strtotime('+1 month', $currentDate));
                    break;
            }
            ?>
            <a href="m.php?page=calendario&view=<?php echo $view; ?>&date=<?php echo $prevDate; ?>" class="calendar-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <button class="calendar-btn" onclick="goToToday()">Oggi</button>
            <a href="m.php?page=calendario&view=<?php echo $view; ?>&date=<?php echo $nextDate; ?>" class="calendar-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    
    <!-- View Switcher -->
    <div style="display: flex; gap: 8px; margin-bottom: 16px; overflow-x: auto; padding: 4px 0;">
        <a href="m.php?page=calendario&view=month&date=<?php echo date('Y-m-d', $currentDate); ?>" 
           class="btn btn-secondary <?php echo $view == 'month' ? 'active' : ''; ?>" 
           style="white-space: nowrap; <?php echo $view == 'month' ? 'background: var(--primary); color: white;' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Mese
        </a>
        <a href="m.php?page=calendario&view=week&date=<?php echo date('Y-m-d', $currentDate); ?>" 
           class="btn btn-secondary <?php echo $view == 'week' ? 'active' : ''; ?>"
           style="white-space: nowrap; <?php echo $view == 'week' ? 'background: var(--primary); color: white;' : ''; ?>">
            <i class="fas fa-calendar-week"></i> Settimana
        </a>
        <a href="m.php?page=calendario&view=day&date=<?php echo date('Y-m-d', $currentDate); ?>" 
           class="btn btn-secondary <?php echo $view == 'day' ? 'active' : ''; ?>"
           style="white-space: nowrap; <?php echo $view == 'day' ? 'background: var(--primary); color: white;' : ''; ?>">
            <i class="fas fa-calendar-day"></i> Giorno
        </a>
        <a href="m.php?page=calendario&view=list&date=<?php echo date('Y-m-d', $currentDate); ?>" 
           class="btn btn-secondary <?php echo $view == 'list' ? 'active' : ''; ?>"
           style="white-space: nowrap; <?php echo $view == 'list' ? 'background: var(--primary); color: white;' : ''; ?>">
            <i class="fas fa-list"></i> Lista
        </a>
    </div>
    
    <?php if ($view == 'month'): ?>
    <!-- Month View -->
    <div class="calendar-grid">
        <!-- Giorni della settimana -->
        <?php
        $giorni = ['LUN', 'MAR', 'MER', 'GIO', 'VEN', 'SAB', 'DOM'];
        foreach ($giorni as $giorno):
        ?>
        <div class="calendar-day-header"><?php echo $giorno; ?></div>
        <?php endforeach; ?>
        
        <!-- Giorni del mese -->
        <?php
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $dayOfWeek = date('N', $firstDay) - 1; // 0 = Monday
        $today = date('Y-m-d');
        
        // Giorni vuoti prima del primo giorno
        for ($i = 0; $i < $dayOfWeek; $i++) {
            echo '<div class="calendar-day" style="opacity: 0.3;"></div>';
        }
        
        // Giorni del mese
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDay = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isToday = ($currentDay == $today);
            
            // Trova eventi per questo giorno
            $dayEvents = array_filter($eventi, function($e) use ($currentDay) {
                $eventStart = date('Y-m-d', strtotime($e['data_inizio']));
                $eventEnd = $e['data_fine'] ? date('Y-m-d', strtotime($e['data_fine'])) : $eventStart;
                return $currentDay >= $eventStart && $currentDay <= $eventEnd;
            });
            ?>
            <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>" 
                 onclick="viewDay('<?php echo $currentDay; ?>')">
                <div class="calendar-day-number"><?php echo $day; ?></div>
                <?php foreach (array_slice($dayEvents, 0, 2) as $event): ?>
                <div class="calendar-event" style="background: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>">
                    <?php echo htmlspecialchars(substr($event['titolo'], 0, 20)); ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 2): ?>
                <div style="font-size: 10px; color: var(--secondary); margin-top: 2px;">
                    +<?php echo count($dayEvents) - 2; ?> altri
                </div>
                <?php endif; ?>
            </div>
            <?php
        }
        
        // Giorni vuoti dopo l'ultimo giorno
        $remainingDays = 7 - (($dayOfWeek + $daysInMonth) % 7);
        if ($remainingDays < 7) {
            for ($i = 0; $i < $remainingDays; $i++) {
                echo '<div class="calendar-day" style="opacity: 0.3;"></div>';
            }
        }
        ?>
    </div>
    
    <?php elseif ($view == 'week'): ?>
    <!-- Week View -->
    <div class="week-view">
        <?php
        $weekStart = strtotime('monday this week', $currentDate);
        for ($i = 0; $i < 7; $i++) {
            $dayDate = strtotime("+$i days", $weekStart);
            $dayString = date('Y-m-d', $dayDate);
            $isToday = ($dayString == date('Y-m-d'));
            
            // Eventi del giorno
            $dayEvents = array_filter($eventi, function($e) use ($dayString) {
                $eventStart = date('Y-m-d', strtotime($e['data_inizio']));
                $eventEnd = $e['data_fine'] ? date('Y-m-d', strtotime($e['data_fine'])) : $eventStart;
                return $dayString >= $eventStart && $dayString <= $eventEnd;
            });
            ?>
            <div class="day-column" style="margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: <?php echo $isToday ? 'var(--primary)' : 'var(--light)'; ?>; color: <?php echo $isToday ? 'white' : 'var(--dark)'; ?>; border-radius: 4px;">
                    <div style="font-weight: 600;"><?php echo getDayName(date('w', $dayDate)); ?></div>
                    <div style="font-size: 12px;"><?php echo date('d/m', $dayDate); ?></div>
                </div>
                
                <?php if (empty($dayEvents)): ?>
                <div style="padding: 12px; text-align: center; color: var(--secondary); font-size: 14px;">
                    Nessun evento
                </div>
                <?php else: ?>
                    <?php foreach ($dayEvents as $event): ?>
                    <div class="list-card" style="margin-top: 8px;" onclick="viewEvent(<?php echo $event['id']; ?>)">
                        <div class="list-icon" style="background: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>20; color: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="list-content">
                            <div class="list-title"><?php echo htmlspecialchars($event['titolo']); ?></div>
                            <div class="list-subtitle">
                                <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($event['data_inizio'])); ?>
                                <?php if ($event['luogo']): ?>
                                    • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['luogo']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>
    </div>
    
    <?php elseif ($view == 'day'): ?>
    <!-- Day View -->
    <div class="day-view">
        <div style="padding: 12px; background: var(--light); border-radius: 4px; margin-bottom: 16px;">
            <div style="font-size: 18px; font-weight: 600; color: var(--dark);">
                <?php echo getDayName(date('w', $currentDate)); ?>
            </div>
            <div style="font-size: 14px; color: var(--secondary);">
                <?php echo date('d/m/Y', $currentDate); ?>
            </div>
        </div>
        
        <?php
        // Eventi del giorno ordinati per ora
        $dayString = date('Y-m-d', $currentDate);
        $dayEvents = array_filter($eventi, function($e) use ($dayString) {
            $eventStart = date('Y-m-d', strtotime($e['data_inizio']));
            $eventEnd = $e['data_fine'] ? date('Y-m-d', strtotime($e['data_fine'])) : $eventStart;
            return $dayString >= $eventStart && $dayString <= $eventEnd;
        });
        
        if (empty($dayEvents)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="empty-title">Nessun evento</div>
            <div class="empty-text">Non ci sono eventi programmati per questo giorno</div>
        </div>
        <?php else: ?>
            <?php foreach ($dayEvents as $event): ?>
            <div class="task-card" onclick="viewEvent(<?php echo $event['id']; ?>)">
                <div class="task-header">
                    <div style="flex: 1;">
                        <div class="task-title"><?php echo htmlspecialchars($event['titolo']); ?></div>
                        <div class="task-meta">
                            <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($event['data_inizio'])); ?></span>
                            <?php if ($event['data_fine']): ?>
                            <span>- <?php echo date('H:i', strtotime($event['data_fine'])); ?></span>
                            <?php endif; ?>
                            <?php if ($event['luogo']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['luogo']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="task-status" style="background: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>20; color: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>">
                        <?php echo ucfirst($event['tipo'] ?? 'Evento'); ?>
                    </span>
                </div>
                
                <?php if ($event['descrizione']): ?>
                <div style="margin-top: 12px; font-size: 14px; color: var(--secondary);">
                    <?php echo nl2br(htmlspecialchars(substr($event['descrizione'], 0, 200))); ?>
                    <?php if (strlen($event['descrizione']) > 200): ?>...<?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($event['partecipanti']): ?>
                <div style="margin-top: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-users" style="color: var(--secondary);"></i>
                    <span style="font-size: 14px; color: var(--secondary);">
                        <?php echo htmlspecialchars($event['partecipanti']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- List View -->
    <div class="list-view">
        <?php if (empty($eventi)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="empty-title">Nessun evento</div>
            <div class="empty-text">Non ci sono eventi in questo periodo</div>
        </div>
        <?php else: ?>
            <?php 
            $lastDate = '';
            foreach ($eventi as $event): 
                $eventDate = date('Y-m-d', strtotime($event['data_inizio']));
                if ($eventDate != $lastDate):
                    $lastDate = $eventDate;
            ?>
            <div style="padding: 8px 0; margin-top: 16px; font-size: 14px; font-weight: 600; color: var(--secondary);">
                <?php echo getDayName(date('w', strtotime($eventDate))); ?>, 
                <?php echo date('d', strtotime($eventDate)); ?> 
                <?php echo getMonthName(date('m', strtotime($eventDate))); ?>
            </div>
            <?php endif; ?>
            
            <div class="list-card" onclick="viewEvent(<?php echo $event['id']; ?>)">
                <div class="list-icon" style="background: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>20; color: <?php echo getEventColor($event['tipo'] ?? 'altro'); ?>">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="list-content">
                    <div class="list-title"><?php echo htmlspecialchars($event['titolo']); ?></div>
                    <div class="list-subtitle">
                        <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($event['data_inizio'])); ?>
                        <?php if ($event['data_fine']): ?>
                        - <?php echo date('H:i', strtotime($event['data_fine'])); ?>
                        <?php endif; ?>
                        <?php if ($event['luogo']): ?>
                        • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['luogo']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="list-action">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Floating Action Button per nuovo evento -->
<button onclick="createEvent()" style="
    position: fixed;
    bottom: 80px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    cursor: pointer;
    z-index: 100;
">
    <i class="fas fa-plus"></i>
</button>

<script>
function goToToday() {
    window.location.href = 'm.php?page=calendario&view=<?php echo $view; ?>&date=<?php echo date('Y-m-d'); ?>';
}

function viewDay(date) {
    window.location.href = 'm.php?page=calendario&view=day&date=' + date;
}

function viewEvent(eventId) {
    // Implementare modal o redirect per visualizzare dettagli evento
    window.location.href = 'm.php?page=evento&id=' + eventId;
}

function createEvent() {
    // Implementare modal o redirect per creare nuovo evento
    window.location.href = 'm.php?page=nuovo-evento';
}
</script>