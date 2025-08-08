<?php
/**
 * Pagina per aggiornamento progresso task
 * Solo super admin e utenti speciali possono accedere
 */
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';
require_once 'backend/config/database.php';
require_once 'backend/functions/aziende-functions.php';
require_once 'backend/utils/EmailTemplate.php';
require_once 'backend/utils/Mailer.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $user['ruolo'] === 'utente_speciale';

// Solo super admin e utenti speciali possono accedere
if (!$isSuperAdmin && !$isUtenteSpeciale) {
    $_SESSION['error'] = "Non hai i permessi per accedere a questa pagina";
    redirect(APP_PATH . '/dashboard.php');
}

// Gestione aggiornamento progresso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    $task_id = $_POST['task_id'] ?? null;
    $percentuale = $_POST['percentuale'] ?? null;
    $note = sanitize_input($_POST['note'] ?? '');
    
    if ($task_id && $percentuale !== null) {
        try {
            db_connection()->beginTransaction();
            
            // Verifica che l'utente sia assegnato al task
            $stmt = db_query("
                SELECT ta.*, t.* 
                FROM task_assegnazioni ta
                JOIN task_calendario t ON ta.task_id = t.id
                WHERE ta.task_id = ? AND ta.utente_id = ?
            ", [$task_id, $user['id']]);
            
            $assignment = $stmt->fetch();
            
            if (!$assignment) {
                throw new Exception("Non sei assegnato a questo task");
            }
            
            $percentuale_precedente = $assignment['percentuale_completamento'];
            
            // Aggiorna percentuale completamento
            db_query("
                UPDATE task_assegnazioni 
                SET percentuale_completamento = ?
                WHERE task_id = ? AND utente_id = ?
            ", [$percentuale, $task_id, $user['id']]);
            
            // Registra l'aggiornamento nello storico
            db_query("
                INSERT INTO task_progressi (task_id, utente_id, percentuale_precedente, percentuale_nuova, note)
                VALUES (?, ?, ?, ?, ?)
            ", [$task_id, $user['id'], $percentuale_precedente, $percentuale, $note]);
            
            // Calcola la percentuale totale del task (media delle percentuali di tutti gli assegnati)
            $stmt = db_query("
                SELECT AVG(percentuale_completamento) as percentuale_media
                FROM task_assegnazioni
                WHERE task_id = ?
            ", [$task_id]);
            $result = $stmt->fetch();
            $percentuale_totale = $result['percentuale_media'] ?? 0;
            
            // Ottieni lo stato precedente del task
            $stmt = db_query("SELECT stato FROM task_calendario WHERE id = ?", [$task_id]);
            $task_data = $stmt->fetch();
            $stato_precedente = $task_data['stato'] ?? 'assegnato';
            
            // Aggiorna la percentuale totale e lo stato del task
            $stato = 'assegnato';
            if ($percentuale_totale > 0 && $percentuale_totale < 100) {
                $stato = 'in_corso';
            } elseif ($percentuale_totale >= 100) {
                $stato = 'completato';
            }
            
            db_query("
                UPDATE task_calendario 
                SET percentuale_completamento_totale = ?, stato = ?
                WHERE id = ?
            ", [$percentuale_totale, $stato, $task_id]);
            
            // Se lo stato è cambiato, invia notifica usando NotificationManager
            if ($stato_precedente != $stato) {
                require_once 'backend/utils/NotificationManager.php';
                $notificationManager = NotificationManager::getInstance();
                
                // Ottieni info completa del task
                $stmt = db_query("
                    SELECT t.*, a.nome as azienda_nome 
                    FROM task_calendario t
                    JOIN aziende a ON t.azienda_id = a.id
                    WHERE t.id = ?
                ", [$task_id]);
                $task_completo = $stmt->fetch();
                
                // Ottieni info del creatore del task
                $creatore = db_query("
                    SELECT u.* 
                    FROM utenti u
                    JOIN task_calendario t ON t.assegnato_da = u.id
                    WHERE t.id = ?
                ", [$task_id])->fetch();
                
                if ($creatore && $creatore['id'] != $user['id']) {
                    $changed_by = [
                        'nome' => $user['nome'],
                        'cognome' => $user['cognome'],
                        'email' => $user['email']
                    ];
                    
                    $notificationManager->notificaTaskStatoCambiato($task_completo, $stato_precedente, $stato, $changed_by, $creatore);
                    error_log("Notifica cambio stato task inviata da task-progress.php - Task ID: $task_id - da $stato_precedente a $stato");
                }
            }
            
            // Invia sempre notifica di aggiornamento progresso (oltre a quella di cambio stato se applicabile)
            $creatore = db_query("
                SELECT u.* 
                FROM utenti u
                JOIN task_calendario t ON t.assegnato_da = u.id
                WHERE t.id = ?
            ", [$task_id])->fetch();
            
            if ($creatore && $creatore['id'] != $user['id']) {
                sendTaskProgressNotification($assignment, $user, $creatore, $percentuale_precedente, $percentuale, $note);
            }
            
            db_connection()->commit();
            
            $_SESSION['success'] = "Progresso aggiornato con successo";
            
        } catch (Exception $e) {
            if (db_connection()->inTransaction()) {
                db_connection()->rollBack();
            }
            $_SESSION['error'] = "Errore nell'aggiornamento del progresso: " . $e->getMessage();
        }
    }
}

// Carica i task assegnati all'utente
$sql = "
    SELECT 
        t.*,
        ta.percentuale_completamento,
        a.nome as azienda_nome,
        CASE 
            WHEN t.prodotto_servizio_tipo = 'personalizzato' 
            THEN t.prodotto_servizio_personalizzato
            ELSE t.prodotto_servizio_predefinito
        END as prodotto_servizio,
        GROUP_CONCAT(DISTINCT CONCAT(u.nome, ' ', u.cognome) SEPARATOR ', ') as altri_assegnati,
        COUNT(DISTINCT ta_all.utente_id) as totale_assegnati
    FROM task_assegnazioni ta
    JOIN task_calendario t ON ta.task_id = t.id
    JOIN aziende a ON t.azienda_id = a.id
    LEFT JOIN task_assegnazioni ta_all ON ta_all.task_id = t.id
    LEFT JOIN utenti u ON ta_all.utente_id = u.id AND u.id != ?
    WHERE ta.utente_id = ? 
    AND t.stato IN ('assegnato', 'in_corso')
    GROUP BY t.id, ta.percentuale_completamento
    ORDER BY t.data_inizio DESC
";

$stmt = db_query($sql, [$user['id'], $user['id']]);
$tasks = $stmt->fetchAll();

// Funzione per inviare notifica progresso
function sendTaskProgressNotification($task, $updater, $creator, $percentuale_precedente, $percentuale_nuova, $note) {
    $mailer = Mailer::getInstance();
    
    $subject = "Aggiornamento Progresso Task: {$task['attivita']} - {$task['citta']}";
    
    $dettagli = [
        'Attività' => $task['attivita'],
        'Città' => $task['citta'],
        'Aggiornato da' => $updater['nome'] . ' ' . $updater['cognome'],
        'Progresso precedente' => $percentuale_precedente . '%',
        'Nuovo progresso' => $percentuale_nuova . '%',
        'Variazione' => ($percentuale_nuova - $percentuale_precedente > 0 ? '+' : '') . ($percentuale_nuova - $percentuale_precedente) . '%'
    ];
    
    if (!empty($note)) {
        $dettagli['Note'] = $note;
    }
    
    $body = EmailTemplate::generate(
        'Aggiornamento Progresso Task',
        "Il progresso di un task che hai creato è stato aggiornato.",
        'Visualizza Dettagli',
        APP_URL . '/task-progress.php',
        $dettagli
    );
    
    return $mailer->send($creator['email'], $subject, $body);
}

include 'components/header.php';
?>

<style>

.action-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: center;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-secondary {
    background: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
}

.btn-secondary:hover {
    background: #e2e6ea;
    color: #495057;
}
</style>

<div class="content-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-tasks"></i> Gestione Task</h1>
        <div class="page-subtitle">Monitora l'avanzamento delle attività</div>
    </div>
    
    <div class="action-bar">
        <a href="calendario-eventi.php" class="btn btn-secondary">
            <i class="fas fa-calendar"></i> Torna al Calendario
        </a>
    </div>

<div class="container mt-4">

    <?php if (empty($tasks)): ?>
    <div class="empty-state">
        <i class="fas fa-clipboard-check"></i>
        <h3>Nessun task attivo</h3>
        <p>Non hai task assegnati in corso o da completare</p>
    </div>
    <?php else: ?>
    <div class="tasks-progress-list">
        <?php foreach ($tasks as $task): 
            $giorniSpecifici = [];
            if ($task['usa_giorni_specifici']) {
                $stmt = db_query("SELECT data_giorno FROM task_giorni WHERE task_id = ? ORDER BY data_giorno", [$task['id']]);
                $giorniSpecifici = array_column($stmt->fetchAll(), 'data_giorno');
            }
        ?>
        <div class="task-progress-card">
            <div class="task-header">
                <h3><?= htmlspecialchars($task['attivita']) ?> - <?= htmlspecialchars($task['prodotto_servizio']) ?></h3>
                <div class="task-meta">
                    <span><i class="fas fa-building"></i> <?= htmlspecialchars($task['azienda_nome']) ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($task['citta']) ?></span>
                    <span><i class="fas fa-calendar"></i> <?= $task['giornate_previste'] ?> giorni</span>
                </div>
            </div>
            
            <?php if ($task['descrizione']): ?>
            <div class="task-description">
                <?= nl2br(htmlspecialchars($task['descrizione'])) ?>
            </div>
            <?php endif; ?>
            
            <div class="task-period">
                <i class="fas fa-calendar-alt"></i>
                <strong>Periodo:</strong> 
                <?= date('d/m/Y', strtotime($task['data_inizio'])) ?> - 
                <?= date('d/m/Y', strtotime($task['data_fine'])) ?>
                
                <?php if (!empty($giorniSpecifici)): ?>
                <div class="specific-days">
                    <strong>Giorni specifici:</strong>
                    <?php foreach ($giorniSpecifici as $giorno): ?>
                    <span class="day-badge"><?= date('d/m', strtotime($giorno)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($task['totale_assegnati'] > 1): ?>
            <div class="other-assignees">
                <i class="fas fa-users"></i>
                <strong>Altri assegnati:</strong> <?= htmlspecialchars($task['altri_assegnati']) ?>
            </div>
            <?php endif; ?>
            
            <div class="progress-section">
                <h4>Il tuo progresso</h4>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $task['percentuale_completamento'] ?>%"></div>
                    </div>
                    <span class="progress-text"><?= $task['percentuale_completamento'] ?>%</span>
                </div>
                
                <form method="POST" action="" class="progress-form">
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    
                    <div class="progress-buttons">
                        <button type="submit" name="percentuale" value="0" class="btn btn-progress <?= $task['percentuale_completamento'] == 0 ? 'active' : '' ?>">
                            0%
                        </button>
                        <button type="submit" name="percentuale" value="25" class="btn btn-progress <?= $task['percentuale_completamento'] == 25 ? 'active' : '' ?>">
                            25%
                        </button>
                        <button type="submit" name="percentuale" value="50" class="btn btn-progress <?= $task['percentuale_completamento'] == 50 ? 'active' : '' ?>">
                            50%
                        </button>
                        <button type="submit" name="percentuale" value="75" class="btn btn-progress <?= $task['percentuale_completamento'] == 75 ? 'active' : '' ?>">
                            75%
                        </button>
                        <button type="submit" name="percentuale" value="100" class="btn btn-progress <?= $task['percentuale_completamento'] == 100 ? 'active' : '' ?>">
                            100%
                        </button>
                    </div>
                    
                    <div class="progress-note">
                        <label for="note_<?= $task['id'] ?>">Note aggiuntive (opzionale):</label>
                        <textarea name="note" id="note_<?= $task['id'] ?>" class="form-control" rows="2" 
                                  placeholder="Aggiungi eventuali note sull'avanzamento..."></textarea>
                    </div>
                </form>
                
                <!-- Storico aggiornamenti -->
                <?php
                $stmt = db_query("
                    SELECT tp.*, u.nome, u.cognome
                    FROM task_progressi tp
                    JOIN utenti u ON tp.utente_id = u.id
                    WHERE tp.task_id = ? AND tp.utente_id = ?
                    ORDER BY tp.creato_il DESC
                    LIMIT 5
                ", [$task['id'], $user['id']]);
                $progressi = $stmt->fetchAll();
                ?>
                
                <?php if (!empty($progressi)): ?>
                <div class="progress-history">
                    <h5>Storico aggiornamenti</h5>
                    <div class="history-list">
                        <?php foreach ($progressi as $progresso): ?>
                        <div class="history-item">
                            <div class="history-date">
                                <?= date('d/m/Y H:i', strtotime($progresso['creato_il'])) ?>
                            </div>
                            <div class="history-change">
                                <?= $progresso['percentuale_precedente'] ?>% → <?= $progresso['percentuale_nuova'] ?>%
                                <?php if ($progresso['note']): ?>
                                <div class="history-note"><?= htmlspecialchars($progresso['note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>

.header-actions {
    display: flex;
    gap: 10px;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #718096;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #e2e8f0;
}

.empty-state h3 {
    color: #2d3748;
    font-size: 24px;
    margin-bottom: 10px;
}

.tasks-progress-list {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.task-progress-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
}

.task-header h3 {
    color: #2d3748;
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 15px 0;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 14px;
    color: #718096;
}

.task-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.task-description {
    margin: 20px 0;
    padding: 15px;
    background: #f7fafc;
    border-radius: 8px;
    color: #4a5568;
    line-height: 1.6;
}

.task-period {
    margin: 20px 0;
    padding: 15px;
    background: #e6f7ff;
    border-radius: 8px;
    color: #2d3748;
}

.specific-days {
    margin-top: 10px;
}

.day-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #4299e1;
    color: white;
    border-radius: 12px;
    font-size: 12px;
    margin: 2px;
}

.other-assignees {
    margin: 15px 0;
    padding: 10px 15px;
    background: #fef5e7;
    border-radius: 8px;
    color: #975a16;
}

.progress-section {
    margin-top: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.progress-section h4 {
    color: #2d3748;
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 15px 0;
}

.progress-bar-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.progress-bar {
    flex: 1;
    height: 30px;
    background: #e2e8f0;
    border-radius: 15px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    min-width: 50px;
    text-align: right;
}

.progress-form {
    margin-top: 20px;
}

.progress-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.btn-progress {
    flex: 1;
    padding: 12px;
    border: 2px solid #e2e8f0;
    background: white;
    color: #4a5568;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-progress:hover {
    border-color: #4299e1;
    color: #2d3748;
    transform: translateY(-1px);
}

.btn-progress.active {
    background: #4299e1;
    color: white;
    border-color: #3182ce;
}

.progress-note {
    margin-top: 20px;
}

.progress-note label {
    display: block;
    margin-bottom: 5px;
    color: #4a5568;
    font-weight: 500;
}

.progress-note textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    resize: vertical;
}

.progress-history {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.progress-history h5 {
    color: #4a5568;
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 15px 0;
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.history-item {
    display: flex;
    gap: 15px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    font-size: 14px;
}

.history-date {
    color: #718096;
    min-width: 120px;
}

.history-change {
    font-weight: 500;
    color: #2d3748;
}

.history-note {
    margin-top: 5px;
    color: #718096;
    font-weight: normal;
    font-style: italic;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .task-meta {
        flex-direction: column;
        gap: 10px;
    }
    
    .progress-buttons {
        flex-wrap: wrap;
    }
    
    .btn-progress {
        min-width: 60px;
    }
}
</style>

<?php include 'components/footer.php'; ?>