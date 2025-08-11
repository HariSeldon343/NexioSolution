<?php
/**
 * Dashboard Content - Mobile Version
 * Replica esatta della dashboard desktop ottimizzata per mobile
 */

// Carica statistiche
$stats = [
    'documenti' => 0,
    'eventi' => 0, 
    'tasks' => 0,
    'tickets' => 0,
    'utenti' => 0,
    'aziende' => 0
];

$recentActivities = [];
$upcomingEvents = [];
$myTasks = [];

try {
    $aziendaId = $currentAzienda['id'] ?? null;
    
    // Conta documenti
    $query = "SELECT COUNT(*) as total FROM documenti WHERE stato != 'cestino'";
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['documenti'] = $stmt->fetchColumn();
    
    // Conta eventi futuri
    $query = "SELECT COUNT(*) as total FROM eventi WHERE data_inizio >= NOW()";
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['eventi'] = $stmt->fetchColumn();
    
    // Conta tasks attivi
    $query = "SELECT COUNT(*) as total FROM tasks WHERE stato IN ('pending', 'in_progress')";
    if (!$isSuperAdmin) {
        $query .= " AND (assegnato_a = ? OR creato_da = ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user['id'], $user['id']]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['tasks'] = $stmt->fetchColumn();
    
    // Conta tickets aperti
    $query = "SELECT COUNT(*) as total FROM tickets WHERE stato != 'chiuso'";
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND azienda_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['tickets'] = $stmt->fetchColumn();
    
    // Conta utenti (solo admin)
    if ($isSuperAdmin) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti WHERE attivo = 1");
        $stats['utenti'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM aziende WHERE status = 'attiva'");
        $stats['aziende'] = $stmt->fetchColumn();
    }
    
    // Attività recenti
    $query = "SELECT 
        'documento' as tipo,
        CONCAT('Nuovo documento: ', titolo) as descrizione,
        data_creazione as data,
        u.nome as utente_nome,
        u.cognome as utente_cognome
        FROM documenti d
        LEFT JOIN utenti u ON d.creato_da = u.id
        WHERE d.stato != 'cestino'";
    
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (d.azienda_id = ? OR d.azienda_id IS NULL)";
    }
    $query .= " ORDER BY d.data_creazione DESC LIMIT 5";
    
    if (!$isSuperAdmin && $aziendaId) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $recentActivities[] = [
            'tipo' => $row['tipo'],
            'descrizione' => $row['descrizione'],
            'data' => $row['data'],
            'utente' => $row['utente_nome'] . ' ' . $row['utente_cognome']
        ];
    }
    
    // Eventi prossimi
    $query = "SELECT 
        e.*,
        u.nome as creatore_nome,
        u.cognome as creatore_cognome
        FROM eventi e
        LEFT JOIN utenti u ON e.creato_da = u.id
        WHERE e.data_inizio >= NOW()";
    
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (e.azienda_id = ? OR e.azienda_id IS NULL)";
    }
    $query .= " ORDER BY e.data_inizio ASC LIMIT 5";
    
    if (!$isSuperAdmin && $aziendaId) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    $upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // I miei tasks
    $query = "SELECT 
        t.*,
        u.nome as assegnato_nome,
        u.cognome as assegnato_cognome
        FROM tasks t
        LEFT JOIN utenti u ON t.assegnato_a = u.id
        WHERE t.stato IN ('pending', 'in_progress')";
    
    if (!$isSuperAdmin) {
        $query .= " AND (t.assegnato_a = ? OR t.creato_da = ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user['id'], $user['id']]);
    } else {
        $query .= " ORDER BY t.data_scadenza ASC LIMIT 5";
        $stmt = $pdo->query($query);
    }
    
    $myTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Log error silently
}
?>

<!-- Dashboard Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(45, 90, 159, 0.1); color: var(--primary);">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['documenti']); ?></div>
        <div class="stat-label">Documenti</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="fas fa-calendar"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['eventi']); ?></div>
        <div class="stat-label">Eventi</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['tasks']); ?></div>
        <div class="stat-label">Tasks Attivi</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['tickets']); ?></div>
        <div class="stat-label">Tickets</div>
    </div>
    
    <?php if ($isSuperAdmin): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['utenti']); ?></div>
        <div class="stat-label">Utenti</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['aziende']); ?></div>
        <div class="stat-label">Aziende</div>
    </div>
    <?php endif; ?>
</div>

<!-- I Miei Tasks -->
<?php if (!empty($myTasks)): ?>
<div class="section">
    <div class="section-header">
        <h2 class="section-title">I Miei Tasks</h2>
        <a href="m.php?page=tasks" class="section-action">Vedi tutti</a>
    </div>
    
    <?php foreach ($myTasks as $task): ?>
    <div class="task-card">
        <div class="task-header">
            <div>
                <div class="task-title"><?php echo htmlspecialchars($task['titolo']); ?></div>
                <div class="task-meta">
                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($task['data_scadenza'])); ?></span>
                    <span><i class="fas fa-flag"></i> <?php echo ucfirst($task['priorita']); ?></span>
                </div>
            </div>
            <span class="task-status <?php echo $task['stato']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $task['stato'])); ?>
            </span>
        </div>
        
        <?php if ($task['progresso'] !== null): ?>
        <div class="task-progress">
            <div class="progress-label">
                <span>Progresso</span>
                <span><?php echo $task['progresso']; ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $task['progresso']; ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($task['assegnato_a']): ?>
        <div class="task-assignee">
            <div class="assignee-avatar">
                <?php echo strtoupper(substr($task['assegnato_nome'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="assignee-name">
                <?php echo htmlspecialchars($task['assegnato_nome'] . ' ' . $task['assegnato_cognome']); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Prossimi Eventi -->
<?php if (!empty($upcomingEvents)): ?>
<div class="section">
    <div class="section-header">
        <h2 class="section-title">Prossimi Eventi</h2>
        <a href="m.php?page=calendario" class="section-action">Vedi calendario</a>
    </div>
    
    <?php foreach ($upcomingEvents as $event): ?>
    <div class="list-card">
        <div class="list-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="list-content">
            <div class="list-title"><?php echo htmlspecialchars($event['titolo']); ?></div>
            <div class="list-subtitle">
                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($event['data_inizio'])); ?>
                <?php if ($event['luogo']): ?>
                    • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['luogo']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Attività Recenti -->
<?php if (!empty($recentActivities)): ?>
<div class="section">
    <div class="section-header">
        <h2 class="section-title">Attività Recenti</h2>
        <a href="m.php?page=attivita" class="section-action">Vedi tutte</a>
    </div>
    
    <?php foreach ($recentActivities as $activity): ?>
    <div class="list-card">
        <div class="list-icon" style="background: rgba(45, 90, 159, 0.1); color: var(--primary);">
            <?php
            switch($activity['tipo']) {
                case 'documento':
                    echo '<i class="fas fa-file-alt"></i>';
                    break;
                case 'evento':
                    echo '<i class="fas fa-calendar"></i>';
                    break;
                case 'task':
                    echo '<i class="fas fa-tasks"></i>';
                    break;
                default:
                    echo '<i class="fas fa-bell"></i>';
            }
            ?>
        </div>
        <div class="list-content">
            <div class="list-title"><?php echo htmlspecialchars($activity['descrizione']); ?></div>
            <div class="list-subtitle">
                <?php echo htmlspecialchars($activity['utente']); ?> • 
                <?php echo date('d/m H:i', strtotime($activity['data'])); ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Empty State se non ci sono dati -->
<?php if (empty($myTasks) && empty($upcomingEvents) && empty($recentActivities)): ?>
<div class="empty-state">
    <div class="empty-icon">
        <i class="fas fa-inbox"></i>
    </div>
    <div class="empty-title">Nessuna attività</div>
    <div class="empty-text">Non ci sono attività recenti da mostrare</div>
</div>
<?php endif; ?>