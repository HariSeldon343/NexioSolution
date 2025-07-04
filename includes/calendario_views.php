<?php 
// Definisco variabili comuni per tutte le viste
$mesi = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
];

if ($view == 'cards'): ?>
    <!-- Vista Cards originale -->
    <?php 
    // Filtra solo eventi futuri per la vista cards
    $eventiFuturi = array_filter($eventi, function($e) {
        return strtotime($e['data_inizio']) >= time();
    });
    ?>
    <?php if (empty($eventiFuturi)): ?>
        <div class="empty-state">
            <i>📅</i>
            <h2>Nessun evento programmato</h2>
            <p>Non ci sono eventi futuri in calendario.</p>
            <?php if ($auth->isAdmin() || $auth->isStaff()): ?>
            <br>
            <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="btn btn-primary">
                <i>➕</i> Crea il primo evento
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($eventiFuturi as $evento): ?>
            <div class="event-card">
                <div class="event-header">
                    <h3 class="event-title"><?php echo htmlspecialchars($evento['titolo']); ?></h3>
                    <span class="status-badge status-<?php echo $evento['tipo']; ?>">
                        <?php echo ucfirst($evento['tipo']); ?>
                    </span>
                </div>
                
                <div class="event-info">
                    <div class="info-item">
                        <i>📅</i>
                        <span><?php echo format_datetime($evento['data_inizio']); ?></span>
                    </div>
                    <?php if ($evento['luogo']): ?>
                    <div class="info-item">
                        <i>📍</i>
                        <span><?php echo htmlspecialchars($evento['luogo']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <i>👤</i>
                        <span><?php echo htmlspecialchars($evento['nome_creatore'] . ' ' . $evento['cognome_creatore']); ?></span>
                    </div>
                </div>
                
                <?php if ($evento['descrizione']): ?>
                <p class="event-description"><?php echo htmlspecialchars(substr($evento['descrizione'], 0, 100)) . (strlen($evento['descrizione']) > 100 ? '...' : ''); ?></p>
                <?php endif; ?>
                
                <div class="event-actions">
                    <a href="<?php echo APP_PATH; ?>/eventi.php?action=view&id=<?php echo $evento['id']; ?>" class="btn btn-secondary btn-small">
                        <i>👁️</i> Dettagli
                    </a>
                    <?php if ($auth->isAdmin() || $auth->isStaff() || $evento['creato_da'] == $user['id']): ?>
                    <a href="<?php echo APP_PATH; ?>/eventi.php?action=edit&id=<?php echo $evento['id']; ?>" class="btn btn-secondary btn-small">
                        <i>✏️</i> Modifica
                    </a>
                    <?php endif; ?>
                    <?php
                    // Verifica se l'utente è già iscritto
                    $stmt_check = $db->getConnection()->prepare("SELECT * FROM partecipanti_eventi WHERE evento_id = ? AND utente_id = ?");
                    $stmt_check->execute([$evento['id'], $user['id']]);
                    $iscritto = $stmt_check->fetch();
                    ?>
                    <?php if (!$iscritto): ?>
                    <a href="<?php echo APP_PATH; ?>/eventi.php?action=partecipa&id=<?php echo $evento['id']; ?>" class="btn btn-primary btn-small">
                        <i>✓</i> Partecipa
                    </a>
                    <?php else: ?>
                    <span class="status-badge status-confermato">Iscritto</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
<?php elseif ($view == 'day'): ?>
    <!-- Vista Giornaliera -->
    <div class="calendar-navigation">
        <div class="nav-buttons">
            <a href="?view=day&date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>" class="btn btn-secondary btn-small">
                ← Precedente
            </a>
            <a href="?view=day&date=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary btn-small">
                Oggi
            </a>
            <a href="?view=day&date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>" class="btn btn-secondary btn-small">
                Successivo →
            </a>
        </div>
        <div class="nav-date">
            <?php echo formatDateItalian($date); ?>
        </div>
    </div>
    
    <div class="day-view">
        <?php 
        $eventiGiorno = getEventiPerData($eventi, $date);
        if (empty($eventiGiorno)): ?>
            <p style="text-align: center; color: #718096; padding: 40px;">Nessun evento programmato per questa data.</p>
        <?php else: ?>
            <?php foreach ($eventiGiorno as $evento): ?>
                <div class="hour-slot">
                    <div class="hour-label">
                        <?php echo date('H:i', strtotime($evento['data_inizio'])); ?>
                    </div>
                    <div class="hour-events">
                        <div class="event-detail-box">
                            <h4 style="margin: 0 0 10px 0; color: #2d3748;"><?php echo htmlspecialchars($evento['titolo']); ?></h4>
                            <?php if ($evento['luogo']): ?>
                                <p style="margin: 5px 0; color: #718096; font-size: 14px;">
                                    <i>📍</i> <?php echo htmlspecialchars($evento['luogo']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($evento['descrizione']): ?>
                                <p style="margin: 5px 0; color: #718096; font-size: 14px;">
                                    <?php echo htmlspecialchars($evento['descrizione']); ?>
                                </p>
                            <?php endif; ?>
                            <a href="<?php echo APP_PATH; ?>/eventi.php?action=view&id=<?php echo $evento['id']; ?>" style="color: #6b5cdf; font-size: 14px; text-decoration: none; font-weight: 500;">
                                Vedi dettagli →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
<?php elseif ($view == 'week'): ?>
    <!-- Vista Settimanale -->
    <?php
    $weekStart = new DateTime($date);
    $weekDay = $weekStart->format('N');
    $weekStart->modify('-' . ($weekDay - 1) . ' days');
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');
    ?>
    <div class="calendar-navigation">
        <div class="nav-buttons">
            <a href="?view=week&date=<?php echo date('Y-m-d', strtotime($date . ' -1 week')); ?>" class="btn btn-secondary btn-small">
                ← Settimana precedente
            </a>
            <a href="?view=week&date=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary btn-small">
                Settimana corrente
            </a>
            <a href="?view=week&date=<?php echo date('Y-m-d', strtotime($date . ' +1 week')); ?>" class="btn btn-secondary btn-small">
                Settimana successiva →
            </a>
        </div>
        <div class="nav-date">
            <?php 
            $meseInizio = (int)$weekStart->format('n');
            $meseFine = (int)$weekEnd->format('n');
            if ($meseInizio == $meseFine) {
                echo $weekStart->format('d') . ' - ' . $weekEnd->format('d') . ' ' . $mesi[$meseFine] . ' ' . $weekEnd->format('Y');
            } else {
                echo $weekStart->format('d') . ' ' . $mesi[$meseInizio] . ' - ' . $weekEnd->format('d') . ' ' . $mesi[$meseFine] . ' ' . $weekEnd->format('Y');
            }
            ?>
        </div>
    </div>
    
    <div class="week-view">
        <div class="week-grid">
            <?php 
            $giorni = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
            $currentDay = clone $weekStart;
            $today = date('Y-m-d');
            for ($i = 0; $i < 7; $i++): 
                $eventiGiorno = getEventiPerData($eventi, $currentDay->format('Y-m-d'));
                $isToday = $currentDay->format('Y-m-d') == $today;
            ?>
                <div class="week-day <?php echo $isToday ? 'today' : ''; ?>">
                    <div class="week-day-header">
                        <?php echo $giorni[$i]; ?><br>
                        <span style="font-weight: normal; font-size: 14px;">
                            <?php echo $currentDay->format('d/m'); ?>
                        </span>
                    </div>
                    <?php foreach ($eventiGiorno as $evento): ?>
                        <div class="mini-event" onclick="window.location.href='<?php echo APP_PATH; ?>/eventi.php?action=view&id=<?php echo $evento['id']; ?>'">
                            <?php echo date('H:i', strtotime($evento['data_inizio'])); ?> - <?php echo htmlspecialchars($evento['titolo']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php 
                $currentDay->modify('+1 day');
            endfor; 
            ?>
        </div>
    </div>
    
<?php elseif ($view == 'month'): ?>
    <!-- Vista Mensile -->
    <?php
    $monthStart = new DateTime($date);
    $monthStart->modify('first day of this month');
    $monthEnd = clone $monthStart;
    $monthEnd->modify('last day of this month');
    
    // Trova il primo lunedì da mostrare
    $calendarStart = clone $monthStart;
    if ($calendarStart->format('N') != 1) {
        $calendarStart->modify('last monday');
    }
    ?>
    <div class="calendar-navigation">
        <div class="nav-buttons">
            <a href="?view=month&date=<?php echo date('Y-m-d', strtotime($date . ' -1 month')); ?>" class="btn btn-secondary btn-small">
                ← Mese precedente
            </a>
            <a href="?view=month&date=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary btn-small">
                Mese corrente
            </a>
            <a href="?view=month&date=<?php echo date('Y-m-d', strtotime($date . ' +1 month')); ?>" class="btn btn-secondary btn-small">
                Mese successivo →
            </a>
        </div>
        <div class="nav-date">
            <?php echo $mesi[(int)$monthStart->format('n')] . ' ' . $monthStart->format('Y'); ?>
        </div>
    </div>
    
    <div class="month-view">
        <div class="month-grid">
            <!-- Header giorni -->
            <?php 
            $giorni = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
            foreach ($giorni as $giorno): ?>
                <div style="background: #f7fafc; padding: 10px; text-align: center; font-weight: 600; color: #4a5568;">
                    <?php echo $giorno; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Giorni del calendario -->
            <?php 
            $currentDay = clone $calendarStart;
            $today = date('Y-m-d');
            while ($currentDay <= $monthEnd || $currentDay->format('N') != 1):
                $isCurrentMonth = $currentDay->format('m') == $monthStart->format('m');
                $isToday = $currentDay->format('Y-m-d') == $today;
                $eventiGiorno = getEventiPerData($eventi, $currentDay->format('Y-m-d'));
            ?>
                <div class="month-day <?php echo !$isCurrentMonth ? 'other-month' : ''; ?> <?php echo $isToday ? 'today' : ''; ?>">
                    <div class="month-day-number"><?php echo $currentDay->format('j'); ?></div>
                    <?php foreach (array_slice($eventiGiorno, 0, 3) as $evento): ?>
                        <div class="mini-event" onclick="window.location.href='<?php echo APP_PATH; ?>/eventi.php?action=view&id=<?php echo $evento['id']; ?>'">
                            <?php echo htmlspecialchars($evento['titolo']); ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($eventiGiorno) > 3): ?>
                        <div style="font-size: 11px; color: #718096; text-align: center;">
                            +<?php echo count($eventiGiorno) - 3; ?> altri
                        </div>
                    <?php endif; ?>
                </div>
            <?php 
                $currentDay->modify('+1 day');
            endwhile; 
            ?>
        </div>
    </div>
<?php endif; ?> 