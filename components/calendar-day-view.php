<?php
/**
 * Vista calendario giornaliera
 */

// Gli eventi sono già filtrati da getEventsForView
$eventiGiorno = $eventi;

// Ordina eventi per ora
usort($eventiGiorno, function($a, $b) {
    return strtotime($a['data_inizio']) - strtotime($b['data_inizio']);
});

// Filtra i task per oggi
$taskGiorno = [];
if (isset($user_tasks) && !empty($user_tasks)) {
    foreach ($user_tasks as $task) {
        $dataInizio = strtotime($task['data_inizio']);
        $dataFine = strtotime($task['data_fine']);
        $oggi = strtotime($date);
        
        // Controlla se il task è attivo oggi
        if ($oggi >= $dataInizio && $oggi <= $dataFine) {
            $taskGiorno[] = $task;
        }
    }
}

// Ore del giorno (6:00 - 23:00)
$ore = range(6, 23);

// Organizza eventi per ora
$eventiPerOra = [];
foreach ($eventiGiorno as $evento) {
    $oraEvento = intval(date('H', strtotime($evento['data_inizio'])));
    if (!isset($eventiPerOra[$oraEvento])) {
        $eventiPerOra[$oraEvento] = [];
    }
    $eventiPerOra[$oraEvento][] = $evento;
}
?>

<div class="calendar-day-view">
    <div class="day-header">
        <div class="day-info">
            <h2><?= date('l, d F Y', strtotime($date)) ?></h2>
            <div class="day-stats">
                <?php 
                $totaleAttivita = count($eventiGiorno) + count($taskGiorno);
                if ($totaleAttivita > 0): 
                ?>
                    <?php if (count($eventiGiorno) > 0): ?>
                    <span class="events-count">
                        <i class="fas fa-calendar-check"></i>
                        <?= count($eventiGiorno) ?> evento<?= count($eventiGiorno) > 1 ? 'i' : '' ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (count($taskGiorno) > 0): ?>
                    <span class="tasks-count">
                        <i class="fas fa-tasks"></i>
                        <?= count($taskGiorno) ?> task
                    </span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="no-events">
                        <i class="fas fa-calendar"></i>
                        Nessun evento o task programmato
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($auth->canManageEvents()): ?>
        <div class="day-actions">
            <a href="?action=nuovo&date=<?= $date ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuovo Evento
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="day-content">
        <div class="timeline-container">
            <?php if (empty($eventiGiorno)): ?>
                <div class="empty-day">
                    <i class="fas fa-calendar-day"></i>
                    <h3>Giornata libera</h3>
                    <p>Non ci sono eventi programmati per oggi</p>
                    <?php if ($auth->canManageEvents()): ?>
                    <a href="?action=nuovo&date=<?= $date ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Aggiungi il primo evento
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Timeline oraria -->
                <div class="day-timeline">
                    <?php foreach ($ore as $ora): 
                        $eventiOra = $eventiPerOra[$ora] ?? [];
                        $hasEvents = !empty($eventiOra);
                        $isCurrentHour = (date('Y-m-d') === $date && intval(date('H')) === $ora);
                    ?>
                    <div class="timeline-hour <?= $hasEvents ? 'has-events' : '' ?> <?= $isCurrentHour ? 'current-hour' : '' ?>" 
                         data-hour="<?= $ora ?>">
                        <div class="hour-label">
                            <span class="hour-time"><?= sprintf('%02d:00', $ora) ?></span>
                            <?php if ($isCurrentHour): ?>
                            <span class="current-indicator">ORA</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hour-content">
                            <?php if (empty($eventiOra)): ?>
                                <div class="empty-hour" onclick="createEventAt('<?= $date ?>', <?= $ora ?>)">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Clicca per aggiungere evento</span>
                                </div>
                            <?php else: ?>
                                <div class="hour-events">
                                    <?php foreach ($eventiOra as $evento): 
                                        $startTime = date('H:i', strtotime($evento['data_inizio']));
                                        $endTime = $evento['data_fine'] ? date('H:i', strtotime($evento['data_fine'])) : '';
                                        $duration = $evento['data_fine'] ? 
                                            (strtotime($evento['data_fine']) - strtotime($evento['data_inizio'])) / 60 : 60;
                                        $canEdit = $auth->canManageEvents() && ($auth->canViewAllEvents() || $evento['creato_da'] == $user['id']);
                                    ?>
                                    <div class="day-event event-type-<?= $evento['tipo'] ?>" 
                                         data-event-id="<?= $evento['id'] ?>"
                                         data-duration="<?= $duration ?>">
                                        <div class="event-header">
                                            <div class="event-time-range">
                                                <span class="start-time"><?= $startTime ?></span>
                                                <?php if ($endTime && $endTime !== $startTime): ?>
                                                <span class="time-separator">-</span>
                                                <span class="end-time"><?= $endTime ?></span>
                                                <?php endif; ?>
                                                <span class="duration">(<?= $duration ?> min)</span>
                                            </div>
                                            
                                            <?php if ($canEdit): ?>
                                            <div class="event-actions">
                                                <a href="?action=modifica&id=<?= $evento['id'] ?>" class="action-btn edit-btn">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=elimina&id=<?= $evento['id'] ?>" class="action-btn delete-btn"
                                                   onclick="return confirm('Sei sicuro di voler eliminare questo evento?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="event-content">
                                            <h4 class="event-title"><?= htmlspecialchars($evento['titolo']) ?></h4>
                                            
                                            <?php if ($evento['descrizione']): ?>
                                            <p class="event-description"><?= htmlspecialchars($evento['descrizione']) ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="event-meta">
                                                <div class="event-type-badge event-type-<?= $evento['tipo'] ?>">
                                                    <?= ucfirst($evento['tipo']) ?>
                                                </div>
                                                
                                                <?php if ($evento['luogo']): ?>
                                                <div class="event-location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?= htmlspecialchars($evento['luogo']) ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="event-creator">
                                                    <i class="fas fa-user"></i>
                                                    <?= htmlspecialchars($evento['creatore_nome'] . ' ' . $evento['creatore_cognome']) ?>
                                                </div>
                                                
                                                <?php if ($evento['num_partecipanti'] > 0): ?>
                                                <div class="event-participants">
                                                    <i class="fas fa-users"></i>
                                                    <?= $evento['num_partecipanti'] ?> partecipant<?= $evento['num_partecipanti'] > 1 ? 'i' : 'e' ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Task del giorno -->
        <?php if (!empty($taskGiorno)): ?>
        <div class="day-tasks-section">
            <h3><i class="fas fa-tasks"></i> Task Attivi</h3>
            <div class="tasks-list">
                <?php foreach ($taskGiorno as $task): 
                    $prodottoServizio = $task['prodotto_servizio'] ?? 'Non specificato';
                ?>
                <div class="day-task-card">
                    <div class="task-header">
                        <h4 class="task-title">
                            <?= htmlspecialchars($task['attivita']) ?> - <?= htmlspecialchars($prodottoServizio) ?>
                        </h4>
                        <span class="task-duration"><?= $task['giornate_previste'] ?> gg</span>
                    </div>
                    
                    <?php if ($task['descrizione']): ?>
                    <p class="task-description"><?= htmlspecialchars($task['descrizione']) ?></p>
                    <?php endif; ?>
                    
                    <div class="task-meta">
                        <span><i class="fas fa-building"></i> <?= htmlspecialchars($task['azienda_nome']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($task['citta']) ?></span>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($task['utente_nome'] . ' ' . $task['utente_cognome']) ?></span>
                        <?php if ($task['costo_giornata']): ?>
                        <span><i class="fas fa-euro-sign"></i> €<?= number_format($task['costo_giornata'], 2, ',', '.') ?>/gg</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="task-period">
                        <i class="fas fa-calendar-alt"></i>
                        <?= date('d/m/Y', strtotime($task['data_inizio'])) ?> - <?= date('d/m/Y', strtotime($task['data_fine'])) ?>
                    </div>
                    
                    <?php if ($auth->isSuperAdmin()): ?>
                    <div class="task-actions">
                        <a href="?action=modifica_task&id=<?= $task['id'] ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-edit"></i> Modifica
                        </a>
                        <a href="?action=elimina_task&id=<?= $task['id'] ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('Sei sicuro di voler eliminare questo task?')">
                            <i class="fas fa-trash"></i> Elimina
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Sidebar riassunto giornata -->
        <div class="day-sidebar">
            <div class="day-summary">
                <h3><i class="fas fa-chart-line"></i> Riassunto Giornata</h3>
                
                <div class="summary-stats">
                    <div class="stat-item">
                        <span class="stat-label">Eventi totali</span>
                        <span class="stat-value"><?= count($eventiGiorno) ?></span>
                    </div>
                    
                    <?php 
                    $tipiEventi = array_count_values(array_column($eventiGiorno, 'tipo'));
                    $tempoTotale = 0;
                    foreach ($eventiGiorno as $evento) {
                        if ($evento['data_fine']) {
                            $tempoTotale += (strtotime($evento['data_fine']) - strtotime($evento['data_inizio'])) / 3600;
                        } else {
                            $tempoTotale += 1; // Default 1 ora
                        }
                    }
                    ?>
                    
                    <div class="stat-item">
                        <span class="stat-label">Tempo impegnato</span>
                        <span class="stat-value"><?= number_format($tempoTotale, 1) ?>h</span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Tempo libero</span>
                        <span class="stat-value"><?= number_format(24 - $tempoTotale, 1) ?>h</span>
                    </div>
                </div>
                
                <?php if (!empty($tipiEventi)): ?>
                <div class="event-types-breakdown">
                    <h4>Tipi di evento</h4>
                    <?php foreach ($tipiEventi as $tipo => $count): ?>
                    <div class="type-item">
                        <span class="type-badge event-type-<?= $tipo ?>"><?= ucfirst($tipo) ?></span>
                        <span class="type-count"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($date >= date('Y-m-d')): ?>
            <div class="quick-actions">
                <h3><i class="fas fa-lightning-bolt"></i> Azioni Rapide</h3>
                
                <?php if ($auth->canManageEvents()): ?>
                <a href="?action=nuovo&date=<?= $date ?>&time=09:00" class="quick-action-btn">
                    <i class="fas fa-coffee"></i>
                    Riunione mattutina (9:00)
                </a>
                
                <a href="?action=nuovo&date=<?= $date ?>&time=14:00" class="quick-action-btn">
                    <i class="fas fa-handshake"></i>
                    Meeting pomeridiano (14:00)
                </a>
                
                <a href="?action=nuovo&date=<?= $date ?>&time=17:00" class="quick-action-btn">
                    <i class="fas fa-chart-bar"></i>
                    Review giornaliero (17:00)
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.calendar-day-view {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    color: white;
}

.day-info h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 600;
    text-transform: capitalize;
}

.day-stats {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    opacity: 0.9;
}

.day-content {
    display: flex;
    min-height: 600px;
}

.timeline-container {
    flex: 1;
    padding: 20px;
}

.empty-day {
    text-align: center;
    padding: 80px 20px;
    color: #718096;
}

.empty-day i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #e2e8f0;
}

.empty-day h3 {
    margin-bottom: 10px;
    color: #2d3748;
    font-size: 24px;
}

.day-timeline {
    max-height: 700px;
    overflow-y: auto;
}

.timeline-hour {
    display: flex;
    border-bottom: 1px solid #f1f5f9;
    min-height: 80px;
    transition: background-color 0.2s ease;
}

.timeline-hour:hover {
    background: #f8f9fa;
}

.timeline-hour.current-hour {
    background: #e6fffa;
    border-left: 4px solid #38b2ac;
}

.hour-label {
    width: 80px;
    padding: 15px 10px;
    background: #f8f9fa;
    border-right: 1px solid #e2e8f0;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.hour-time {
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
}

.current-indicator {
    background: #38b2ac;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}

.hour-content {
    flex: 1;
    padding: 10px 15px;
}

.empty-hour {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    color: #a0aec0;
    transition: all 0.2s ease;
}

.empty-hour:hover {
    border-color: #4299e1;
    color: #4299e1;
    background: #f7fafc;
}

.hour-events {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.day-event {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.day-event::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #4299e1;
}

.day-event:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.event-time-range {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
    color: #2d3748;
}

.start-time, .end-time {
    font-size: 16px;
}

.time-separator {
    color: #718096;
}

.duration {
    font-size: 12px;
    color: #718096;
    font-weight: normal;
}

.event-actions {
    display: flex;
    gap: 5px;
}

.action-btn {
    padding: 6px 8px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.edit-btn {
    background: #48bb78;
    color: white;
}

.edit-btn:hover {
    background: #38a169;
}

.delete-btn {
    background: #e53e3e;
    color: white;
}

.delete-btn:hover {
    background: #c53030;
}

.event-title {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    line-height: 1.3;
}

.event-description {
    color: #4a5568;
    margin-bottom: 15px;
    line-height: 1.5;
}

.event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

.event-type-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
}

.event-meta > div:not(.event-type-badge) {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: #718096;
}

.event-meta i {
    color: #a0aec0;
}

/* Colori per tipi di evento */
.event-type-meeting::before { background: #4299e1; }
.event-type-presentation::before { background: #ed64a6; }
.event-type-training::before { background: #48bb78; }
.event-type-workshop::before { background: #ed8936; }
.event-type-conference::before { background: #9f7aea; }
.event-type-social::before { background: #ecc94b; }
.event-type-other::before { background: #a0aec0; }

.event-type-meeting { background: #bee3f8; color: #2b6cb0; }
.event-type-presentation { background: #fbb6ce; color: #b83280; }
.event-type-training { background: #c6f6d5; color: #22543d; }
.event-type-workshop { background: #fed7a8; color: #c05621; }
.event-type-conference { background: #e9d8fd; color: #553c9a; }
.event-type-social { background: #fef5e7; color: #975a16; }
.event-type-other { background: #e2e8f0; color: #4a5568; }

/* Sidebar */
.day-sidebar {
    width: 300px;
    background: #f8f9fa;
    border-left: 1px solid #e2e8f0;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.day-summary h3,
.quick-actions h3 {
    margin: 0 0 20px 0;
    color: #2d3748;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.summary-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 25px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.stat-label {
    color: #718096;
    font-size: 14px;
}

.stat-value {
    font-weight: 600;
    color: #2d3748;
    font-size: 16px;
}

.event-types-breakdown h4 {
    margin: 0 0 15px 0;
    color: #4a5568;
    font-size: 16px;
}

.type-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.type-badge {
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 500;
}

.type-count {
    background: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #2d3748;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-decoration: none;
    color: #4a5568;
    font-size: 14px;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    border-color: #4299e1;
    color: #2d3748;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn {
    padding: 12px 20px;
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

/* Task section */
.day-tasks-section {
    margin: 30px 20px;
    padding: 25px;
    background: #f0fff4;
    border-radius: 12px;
    border: 2px solid #48bb78;
}

.day-tasks-section h3 {
    color: #22543d;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.day-task-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #9ae6b4;
    transition: all 0.2s ease;
}

.day-task-card:hover {
    box-shadow: 0 4px 12px rgba(72, 187, 120, 0.15);
    transform: translateY(-2px);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.task-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #22543d;
    flex: 1;
}

.task-duration {
    background: #48bb78;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.task-description {
    color: #4a5568;
    margin-bottom: 15px;
    line-height: 1.5;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #718096;
}

.task-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.task-period {
    background: #f7fafc;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    color: #4a5568;
    margin-bottom: 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.task-actions {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.tasks-count {
    background: #48bb78;
    color: white;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Responsive */
@media (max-width: 968px) {
    .day-content {
        flex-direction: column;
    }
    
    .day-sidebar {
        width: 100%;
    }
    
    .day-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .day-header {
        padding: 20px;
    }
    
    .day-info h2 {
        font-size: 22px;
    }
    
    .timeline-container {
        padding: 15px;
    }
    
    .hour-label {
        width: 60px;
        padding: 10px 5px;
    }
    
    .hour-time {
        font-size: 12px;
    }
    
    .day-event {
        padding: 12px;
    }
    
    .event-title {
        font-size: 16px;
    }
    
    .event-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .day-sidebar {
        padding: 15px;
    }
}
</style>

<script>
function createEventAt(date, hour) {
    <?php if ($auth->canManageEvents()): ?>
    const url = new URL('<?= APP_PATH ?>/calendario-eventi.php', window.location.origin);
    url.searchParams.set('action', 'nuovo');
    url.searchParams.set('date', date);
    url.searchParams.set('time', hour.toString().padStart(2, '0') + ':00');
    
    window.location.href = url.toString();
    <?php endif; ?>
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll alla current hour se è oggi
    const isToday = '<?= $date ?>' === '<?= date('Y-m-d') ?>';
    
    if (isToday) {
        const currentHour = document.querySelector('.current-hour');
        if (currentHour) {
            setTimeout(() => {
                currentHour.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 500);
        }
    }
});
</script>