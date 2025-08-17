<?php
/**
 * Legenda per il calendario
 * Mostra i colori delle aziende e indicatori speciali
 */

// Ottieni info utente e aziende se non già disponibili
if (!isset($isSuperAdmin)) {
    $isSuperAdmin = isset($auth) ? $auth->isSuperAdmin() : false;
}

?>

<!-- Legenda Calendario -->
<div class="calendar-legend mb-3">
    <div class="legend-header">
        <i class="fas fa-palette"></i> Legenda
    </div>
    <div class="legend-items">
        <?php if (isset($aziende_list) && !empty($aziende_list)): ?>
            <?php foreach ($aziende_list as $index => $azienda): ?>
            <div class="legend-item">
                <span class="legend-color event-company-<?= $azienda['id'] ?>"></span>
                <span class="legend-label"><?= htmlspecialchars($azienda['nome']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="legend-item">
            <span class="legend-color event-no-company"></span>
            <span class="legend-label">👤 Calendario Personale</span>
        </div>
        
        <?php if (isset($showIcsIndicator) && $showIcsIndicator): ?>
        <div class="legend-item">
            <span class="legend-color event-ics-import"></span>
            <span class="legend-label">📥 Importato da ICS</span>
        </div>
        <?php endif; ?>
        
        <div class="legend-item">
            <span class="legend-color task-indicator"></span>
            <span class="legend-label">T: Task Assegnato</span>
        </div>
    </div>
</div>

<style>
.calendar-legend {
    background: white;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.legend-header {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 12px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #4a5568;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.1);
    display: inline-block;
}

.task-indicator {
    background: #ecfdf5;
    border-left: 3px solid #10b981;
}

.legend-label {
    white-space: nowrap;
}

/* Responsive */
@media (max-width: 768px) {
    .legend-items {
        gap: 12px;
    }
    
    .legend-item {
        font-size: 12px;
    }
    
    .legend-color {
        width: 16px;
        height: 16px;
    }
}

@media (max-width: 480px) {
    .calendar-legend {
        padding: 10px;
    }
    
    .legend-items {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
}
</style>