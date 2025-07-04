<?php
/**
 * Vista lista eventi
 */
?>

<div class="events-list-view">
    <?php if (empty($eventi)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>Nessun evento trovato</h3>
            <p>Non ci sono eventi in questo periodo</p>
            <?php if ($auth->canManageEvents()): ?>
            <a href="?action=nuovo" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crea il primo evento
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php 
            $currentDate = null;
            foreach ($eventi as $evento): 
                $eventoDate = date('Y-m-d', strtotime($evento['data_inizio']));
                if ($currentDate !== $eventoDate): 
                    $currentDate = $eventoDate;
            ?>
                <div class="date-divider">
                    <h3><?= date('l, d F Y', strtotime($evento['data_inizio'])) ?></h3>
                </div>
            <?php endif; ?>
            
            <div class="event-card" data-event-id="<?= $evento['id'] ?>">
                <div class="event-time">
                    <span class="start-time"><?= date('H:i', strtotime($evento['data_inizio'])) ?></span>
                    <?php if ($evento['data_fine'] && $evento['data_fine'] !== $evento['data_inizio']): ?>
                    <span class="end-time">- <?= date('H:i', strtotime($evento['data_fine'])) ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="event-content">
                    <div class="event-header">
                        <h4 class="event-title"><?= htmlspecialchars($evento['titolo']) ?></h4>
                        <div class="event-type event-type-<?= $evento['tipo'] ?>">
                            <?= ucfirst($evento['tipo']) ?>
                        </div>
                    </div>
                    
                    <?php if ($evento['descrizione']): ?>
                    <p class="event-description"><?= htmlspecialchars($evento['descrizione']) ?></p>
                    <?php endif; ?>
                    
                    <div class="event-meta">
                        <?php if ($evento['luogo']): ?>
                        <span class="event-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($evento['luogo']) ?>
                        </span>
                        <?php endif; ?>
                        
                        <span class="event-creator">
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($evento['creatore_nome'] . ' ' . $evento['creatore_cognome']) ?>
                        </span>
                        
                        <?php if ($evento['num_partecipanti'] > 0): ?>
                        <span class="event-participants">
                            <i class="fas fa-users"></i>
                            <?= $evento['num_partecipanti'] ?> partecipant<?= $evento['num_partecipanti'] > 1 ? 'i' : 'e' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="event-actions">
                    <a href="esporta-calendario.php?tipo=evento&evento_id=<?= $evento['id'] ?>" 
                       class="btn btn-sm btn-secondary" title="Esporta evento in ICS">
                        <i class="fas fa-download"></i>
                    </a>
                    
                    <?php if ($auth->canManageEvents() && ($auth->canViewAllEvents() || $evento['creato_da'] == $user['id'])): ?>
                    <a href="?action=modifica&id=<?= $evento['id'] ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?action=elimina&id=<?= $evento['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Sei sicuro di voler eliminare questo evento?')">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.events-list-view {
    min-height: 400px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #718096;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #e2e8f0;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #2d3748;
    font-size: 24px;
}

.empty-state p {
    margin-bottom: 30px;
    font-size: 16px;
}

.events-grid {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.date-divider {
    margin: 20px 0 10px 0;
    padding: 10px 0;
    border-bottom: 2px solid #4299e1;
}

.date-divider h3 {
    color: #2d3748;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.event-card {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    padding: 20px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s ease;
    position: relative;
}

.event-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #4299e1;
    transform: translateY(-2px);
}

.event-time {
    min-width: 80px;
    text-align: center;
    padding: 10px;
    background: #f7fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.start-time {
    font-weight: 600;
    color: #2d3748;
    font-size: 16px;
}

.end-time {
    color: #718096;
    font-size: 14px;
}

.event-content {
    flex: 1;
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.event-title {
    color: #2d3748;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    line-height: 1.3;
}

.event-type {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
}

.event-type-meeting { background: #bee3f8; color: #2b6cb0; }
.event-type-presentation { background: #fbb6ce; color: #b83280; }
.event-type-training { background: #c6f6d5; color: #22543d; }
.event-type-workshop { background: #fed7a8; color: #c05621; }
.event-type-conference { background: #e9d8fd; color: #553c9a; }
.event-type-social { background: #fef5e7; color: #975a16; }
.event-type-other { background: #e2e8f0; color: #4a5568; }

.event-description {
    color: #718096;
    margin-bottom: 15px;
    line-height: 1.5;
    font-size: 14px;
}

.event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 13px;
    color: #718096;
}

.event-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.event-meta i {
    color: #a0aec0;
}

.event-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-left: auto;
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
    justify-content: center;
    gap: 6px;
    min-width: 44px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    min-width: 38px;
}

.btn-outline {
    background: white;
    color: #4299e1;
    border: 1px solid #4299e1;
}

.btn-outline:hover {
    background: #4299e1;
    color: white;
}

.btn-danger {
    background: #e53e3e;
    color: white;
}

.btn-danger:hover {
    background: #c53030;
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

/* Responsive design */
@media (max-width: 768px) {
    .event-card {
        flex-direction: column;
        gap: 15px;
    }
    
    .event-time {
        min-width: auto;
        align-self: flex-start;
    }
    
    .event-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .event-meta {
        flex-direction: column;
        gap: 8px;
    }
    
    .event-actions {
        flex-direction: row;
        align-self: flex-end;
        margin-left: 0;
        margin-top: 10px;
    }
}

@media (max-width: 480px) {
    .events-grid {
        gap: 10px;
    }
    
    .event-card {
        padding: 15px;
    }
    
    .date-divider {
        margin: 15px 0 5px 0;
    }
    
    .date-divider h3 {
        font-size: 16px;
    }
    
    .event-title {
        font-size: 16px;
    }
}
</style>