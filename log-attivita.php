<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$logger = ActivityLogger::getInstance();
$currentAzienda = $auth->getCurrentAzienda();

// Solo admin possono accedere
if (!$auth->canAccess('settings', 'read')) {
    redirect(APP_PATH . '/dashboard.php');
}

// Gestione eliminazione log (solo super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->canDeleteLogs()) {
    if (isset($_POST['action']) && $_POST['action'] === 'delete_logs') {
        try {
            db_connection()->beginTransaction();
            
            // Determina quali log eliminare
            $deleteWhere = [];
            $deleteParams = [];
            
            // Escludiamo sempre i log protetti e quelli di eliminazione
            $deleteWhere[] = "(non_eliminabile = 0 OR non_eliminabile IS NULL)";
            $deleteWhere[] = "(azione != 'eliminazione_log' OR azione IS NULL)";
            
            if (isset($_POST['delete_all']) && $_POST['delete_all'] === '1') {
                // Elimina tutti i log non protetti
                $dettagli = "Eliminati TUTTI i log di sistema (esclusi log protetti e di eliminazione)";
            } else {
                // Elimina log filtrati
                if (!empty($_POST['entita_tipo'])) {
                    $deleteWhere[] = "entita_tipo = :entita_tipo";
                    $deleteParams['entita_tipo'] = $_POST['entita_tipo'];
                }
                
                if (!empty($_POST['delete_before'])) {
                    $deleteWhere[] = "DATE(data_azione) < :delete_before";
                    $deleteParams['delete_before'] = $_POST['delete_before'];
                }
                
                if (count($deleteWhere) <= 2) { // Solo i filtri di esclusione base
                    throw new Exception("Nessun criterio di eliminazione specificato");
                }
                
                $dettagli = "Eliminati log con criteri: " . json_encode($_POST);
            }
            
            // Conta quanti log verranno eliminati
            $countSql = "SELECT COUNT(*) as count FROM log_attivita WHERE " . implode(" AND ", $deleteWhere);
            $stmt = db_query($countSql, $deleteParams);
            $countDeleted = $stmt->fetch()['count'];
            
            // Conta i log protetti che rimarranno (non_eliminabile = 1 o azione = 'eliminazione_log')
            $stmt = db_query("SELECT COUNT(*) as count FROM log_attivita WHERE non_eliminabile = 1 OR azione = 'eliminazione_log'");
            $countNonEliminabili = $stmt->fetch()['count'];
            
            // Prima di eliminare, crea un log dell'eliminazione (non eliminabile)
            $logger->log('sistema', 'eliminazione_log', null, 
                "$dettagli. Totale record eliminati: $countDeleted");
            
            // Marca il log appena creato come non eliminabile
            $lastLogId = db_connection()->lastInsertId();
            if ($lastLogId) {
                db_query("UPDATE log_attivita SET non_eliminabile = 1 WHERE id = ?", [$lastLogId]);
            }
            
            // Esegui l'eliminazione
            $deleteSql = "DELETE FROM log_attivita WHERE " . implode(" AND ", $deleteWhere);
            db_query($deleteSql, $deleteParams);
            
            db_connection()->commit();
            
            if ($countNonEliminabili > 0) {
                $_SESSION['success'] = "Eliminati $countDeleted log di attività. Conservati $countNonEliminabili log protetti per audit e conformità.";
            } else {
                $_SESSION['success'] = "Eliminati $countDeleted log di attività";
            }
            redirect(APP_PATH . '/log-attivita.php');
            
        } catch (Exception $e) {
            db_connection()->rollback();
            
            $_SESSION['error'] = "Errore durante l'eliminazione: " . $e->getMessage();
        }
    }
}

// Parametri filtro
$entita_tipo = $_GET['tipo'] ?? '';
$azione = $_GET['azione'] ?? '';
$data_da = $_GET['data_da'] ?? date('Y-m-d', strtotime('-7 days'));
$data_a = $_GET['data_a'] ?? date('Y-m-d');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Costruisci query con filtri
$where = ["1=1"];
$params = [];

// Ottieni l'ID dell'azienda in modo sicuro
$aziendaId = null;
if ($currentAzienda) {
    $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                 (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
}

if ($aziendaId && !$auth->isSuperAdmin()) {
    $where[] = "azienda_id = :azienda_id";
    $params['azienda_id'] = $aziendaId;
} elseif ($aziendaId && $auth->isSuperAdmin()) {
    $where[] = "azienda_id = :azienda_id";
    $params['azienda_id'] = $aziendaId;
}

if ($entita_tipo) {
    $where[] = "entita_tipo = :entita_tipo";
    $params['entita_tipo'] = $entita_tipo;
}

if ($azione) {
    $where[] = "azione = :azione";
    $params['azione'] = $azione;
}

if ($data_da) {
    $where[] = "DATE(data_azione) >= :data_da";
    $params['data_da'] = $data_da;
}

if ($data_a) {
    $where[] = "DATE(data_azione) <= :data_a";
    $params['data_a'] = $data_a;
}

$where_clause = implode(" AND ", $where);

// Conta totale record
$count_sql = "SELECT COUNT(*) as total FROM log_attivita WHERE $where_clause";
$stmt = db_query($count_sql, $params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Debug: Aggiungi logging per capire cosa succede
error_log("DEBUG LOG-ATTIVITA: Where clause: $where_clause");
error_log("DEBUG LOG-ATTIVITA: Params: " . print_r($params, true));
error_log("DEBUG LOG-ATTIVITA: Total count: $total");

// Ottieni log - usa la tabella diretta invece della vista
$sql = "SELECT l.*, u.nome, u.cognome, u.email, a.nome as azienda_nome
        FROM log_attivita l
        LEFT JOIN utenti u ON l.utente_id = u.id
        LEFT JOIN aziende a ON l.azienda_id = a.id
        WHERE $where_clause 
        ORDER BY l.data_azione DESC 
        LIMIT $per_page OFFSET $offset";

error_log("DEBUG LOG-ATTIVITA: SQL finale: $sql");

$stmt = db_query($sql, $params);
$logs = $stmt->fetchAll();

error_log("DEBUG LOG-ATTIVITA: Logs trovati: " . count($logs));

// Fallback: Se non ci sono log con la query principale, prova query semplificata
if (empty($logs) && $total > 0) {
    error_log("DEBUG LOG-ATTIVITA: Tentativo query semplificata");
    $simple_sql = "SELECT * FROM log_attivita ORDER BY data_azione DESC LIMIT $per_page OFFSET $offset";
    $stmt = db_query($simple_sql);
    $logs = $stmt->fetchAll();
    error_log("DEBUG LOG-ATTIVITA: Query semplificata - Logs trovati: " . count($logs));
}

// Ottieni valori unici per filtri
$stmt = db_query("SELECT DISTINCT entita_tipo FROM log_attivita WHERE entita_tipo IS NOT NULL ORDER BY entita_tipo");
$tipi = $stmt->fetchAll();

$stmt = db_query("SELECT DISTINCT azione FROM log_attivita ORDER BY azione");
$azioni = $stmt->fetchAll();

$pageTitle = 'Log Attività';
$bodyClass = 'log-attivita-page';
require_once 'components/header.php';
?>

<!-- Load dedicated CSS for log attivita table -->
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/log-attivita.css">

<style>
    .filters-form {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid #c7cad1;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .filters-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    /* Additional custom styles - most styles moved to log-attivita.css */
    
    .action-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .action-badge.creazione {
        background: #d4edda;
        color: #155724;
    }
    
    .action-badge.modifica {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .action-badge.eliminazione {
        background: #f8d7da;
        color: #721c24;
    }
    
    .action-badge.download {
        background: #fff3cd;
        color: #856404;
    }
    
    .entity-type {
        display: inline-block;
        padding: 3px 8px;
        background: #e2e8f0;
        color: #4a5568;
        border-radius: 8px;
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 600;
    }
    
    .user-info-cell {
        font-size: 13px;
    }
    
    .user-name {
        font-weight: 500;
        color: #2d3748;
    }
    
    .user-email {
        color: #718096;
        font-size: 12px;
    }
    
    .details-cell {
        font-size: 13px;
        color: #4a5568;
        max-width: 250px !important;
        word-wrap: break-word !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        white-space: normal !important;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 30px;
    }
    
    .pagination a, .pagination span {
        padding: 8px 12px;
        border: 1px solid #c1c7d0;
        border-radius: 6px;
        text-decoration: none;
        color: #4a5568;
        background: white;
        transition: all 0.2s;
    }
    
    .pagination a:hover {
        background: #f7fafc;
        border-color: #a0aec0;
    }
    
    .pagination .current {
        background: #4f46e5;
        color: white;
        border-color: #4f46e5;
    }
    
    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #c7cad1;
        text-align: center;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #718096;
        font-size: 14px;
    }
    
    /* Button styling fix */
    .btn {
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        font-weight: 500 !important;
    }
    
    /* Column widths handled in log-attivita.css */
</style>

<div class="page-header">
    <h1><i class="fas fa-history"></i> Log Attività</h1>
    <div class="page-subtitle">Registro delle attività del sistema</div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Statistiche rapide -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-value"><?php echo number_format($total); ?></div>
        <div class="stat-label">Attività Totali</div>
    </div>
    
    <?php
    // Attività oggi - uso sintassi compatibile
    $oggi = date('Y-m-d');
    $sql = "SELECT COUNT(*) as count FROM log_attivita WHERE DATE(data_azione) = ?";
    $params = [$oggi];
    if ($aziendaId) {
        $sql .= " AND azienda_id = ?";
        $params[] = $aziendaId;
    }
    $stmt = db_query($sql, $params);
    $oggiCount = $stmt->fetch()['count'];
    ?>
    <div class="stat-box">
        <div class="stat-value"><?php echo number_format($oggiCount); ?></div>
        <div class="stat-label">Attività Oggi</div>
    </div>
    
    <?php
    // Documenti caricati questa settimana - include tutti i tipi di upload e creazione
    $settimanaFa = date('Y-m-d', strtotime('-7 days'));
    $sql = "SELECT COUNT(*) as count FROM log_attivita 
        WHERE (
            (entita_tipo = 'documento' AND azione IN ('creazione', 'upload', 'caricamento')) 
            OR (entita_tipo = 'file' AND azione IN ('upload', 'caricamento', 'creazione'))
            OR (azione = 'upload_file')
            OR (azione = 'upload_multiple')
        )
        AND DATE(data_azione) >= ?";
    $params = [$settimanaFa];
    if ($aziendaId) {
        $sql .= " AND azienda_id = ?";
        $params[] = $aziendaId;
    }
    $stmt = db_query($sql, $params);
    $doc_settimana = $stmt->fetch()['count'];
    ?>
    <div class="stat-box">
        <div class="stat-value"><?php echo number_format($doc_settimana); ?></div>
        <div class="stat-label">Documenti Caricati (7gg)</div>
    </div>
    
    <?php
    // Utenti attivi questa settimana - conta solo utenti con ID valido (esclude NULL)
    $sql = "SELECT COUNT(DISTINCT utente_id) as count 
        FROM log_attivita 
        WHERE utente_id IS NOT NULL 
        AND DATE(data_azione) >= ?";
    $params = [$settimanaFa];
    if ($aziendaId) {
        $sql .= " AND azienda_id = ?";
        $params[] = $aziendaId;
    }
    $stmt = db_query($sql, $params);
    $utenti_attivi = $stmt->fetch()['count'];
    ?>
    <div class="stat-box">
        <div class="stat-value"><?php echo number_format($utenti_attivi); ?></div>
        <div class="stat-label">Utenti Attivi (7gg)</div>
    </div>
</div>

<!-- Filtri -->
<div class="filters-form">
    <form method="get" action="">
        <div class="filters-row">
            <div class="form-group">
                <label for="tipo">Tipo Entità</label>
                <select id="tipo" name="tipo">
                    <option value="">Tutti</option>
                    <?php foreach ($tipi as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['entita_tipo']); ?>" 
                                <?php echo $entita_tipo === $t['entita_tipo'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($t['entita_tipo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="azione">Azione</label>
                <select id="azione" name="azione">
                    <option value="">Tutte</option>
                    <?php foreach ($azioni as $a): ?>
                        <option value="<?php echo htmlspecialchars($a['azione']); ?>" 
                                <?php echo $azione === $a['azione'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($a['azione']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="data_da">Data Da</label>
                <input type="date" id="data_da" name="data_da" value="<?php echo $data_da; ?>">
            </div>
            
            <div class="form-group">
                <label for="data_a">Data A</label>
                <input type="date" id="data_a" name="data_a" value="<?php echo $data_a; ?>">
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filtra
            </button>
            <a href="<?php echo APP_PATH; ?>/log-attivita.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
            <?php if ($auth->canDeleteLogs()): ?>
            <button type="button" class="btn btn-danger" onclick="showDeleteModal()">
                <i class="fas fa-trash"></i> Elimina Log
            </button>
            <small style="margin-left: 10px; color: #666; display: inline-block;">
                <i class="fas fa-info-circle"></i> I log di eliminazione non possono essere cancellati
            </small>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabella log -->
<?php if (empty($logs)): ?>
    <div class="empty-state">
        <i class="fas fa-clipboard-list"></i>
        <h2>Nessuna attività trovata</h2>
        <p>Non ci sono attività che corrispondono ai criteri di ricerca.</p>
    </div>
<?php else: ?>
    <div class="log-table-container">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Data/Ora</th>
                    <th>Utente</th>
                    <th>Tipo</th>
                    <th>Azione</th>
                    <th style="max-width: 300px;">Dettagli</th>
                    <?php if ($auth->isSuperAdmin() && !$currentAzienda): ?>
                        <th style="min-width: 120px;">Azienda</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr 
>
                        <td class="log-date-cell">
                            <div class="log-date"><?php echo date('d/m/Y', strtotime($log['data_azione'])); ?></div>
                            <div class="log-time">
                                <?php echo date('H:i:s', strtotime($log['data_azione'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="user-info-cell">
                                <div class="user-name"><?php echo htmlspecialchars(($log['nome'] ?? '') . ' ' . ($log['cognome'] ?? '') ?: 'Sistema'); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="entity-type <?php echo htmlspecialchars($log['entita_tipo']); ?>"><?php echo htmlspecialchars($log['entita_tipo']); ?></span>
                        </td>
                        <td>
                            <span class="action-badge <?php echo $log['azione']; ?>">
                                <?php echo ucfirst($log['azione']); ?>
                            </span>
                        </td>
                        <td class="details-cell">
                            <?php 
                            // Se i dettagli sono JSON, decodificali e mostrali meglio
                            $dettagli = $log['dettagli'] ?? '';
                            if (!empty($dettagli)) {
                                $dettagliDecoded = json_decode($dettagli, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($dettagliDecoded)) {
                                    // Mostra dettagli JSON in modo leggibile
                                    foreach ($dettagliDecoded as $key => $value) {
                                        if ($key === 'ip' || $key === 'user_agent') continue; // Salta questi
                                        $label = ucfirst(str_replace('_', ' ', $key));
                                        if (is_array($value)) {
                                            $value = implode(', ', $value);
                                        }
                                        echo '<div class="detail-row"><strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($value) . '</div>';
                                    }
                                } else {
                                    // Non è JSON, mostra come testo normale
                                    echo htmlspecialchars($dettagli);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                            <?php if ($log['ip_address']): ?>
                                <div class="ip-info">
                                    <i class="fas fa-globe"></i> IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php if ($auth->isSuperAdmin() && !$currentAzienda): ?>
                            <td class="company-cell">
                                <?php echo htmlspecialchars($log['azienda_nome'] ?? '-'); ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginazione -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                    &laquo; Prima
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    &lsaquo; Prec
                </a>
            <?php else: ?>
                <span class="disabled">&laquo; Prima</span>
                <span class="disabled">&lsaquo; Prec</span>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    Succ &rsaquo;
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                    Ultima &raquo;
                </a>
            <?php else: ?>
                <span class="disabled">Succ &rsaquo;</span>
                <span class="disabled">Ultima &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($auth->isSuperAdmin()): ?>
<!-- Modal Eliminazione Log -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i> Elimina Log Attività</h2>
            <button type="button" class="close-modal" onclick="closeDeleteModal()">×</button>
        </div>
        
        <form method="POST" action="" id="deleteLogsForm">
            <input type="hidden" name="action" value="delete_logs">
            
            <div class="modal-body">
                <div class="warning-box">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>ATTENZIONE:</strong> Questa operazione è irreversibile!
                </div>
                
                <div style="background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px; margin-top: 10px; color: #1565c0;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> I log protetti (marcati come non eliminabili) e i log delle eliminazioni precedenti vengono sempre conservati per motivi di sicurezza e audit.
                </div>
                
                <div class="delete-options">
                    <h3>Seleziona cosa eliminare:</h3>
                    
                    <label class="radio-option">
                        <input type="radio" name="delete_type" value="all" onchange="toggleDeleteOptions()">
                        <span class="option-text">
                            <strong>Elimina TUTTI i log</strong>
                            <small>Rimuove tutti i log di sistema, eccetto i log protetti e quelli delle eliminazioni precedenti</small>
                        </span>
                    </label>
                    
                    <label class="radio-option">
                        <input type="radio" name="delete_type" value="filtered" checked onchange="toggleDeleteOptions()">
                        <span class="option-text">
                            <strong>Elimina con criteri</strong>
                            <small>Rimuove solo i log che corrispondono ai criteri selezionati</small>
                        </span>
                    </label>
                    
                    <div id="filterOptions" class="filter-options">
                        <div class="form-group">
                            <label>Tipo Entità</label>
                            <select name="entita_tipo">
                                <option value="">Tutti i tipi</option>
                                <?php foreach ($tipi as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['entita_tipo']); ?>">
                                        <?php echo ucfirst($t['entita_tipo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Elimina log più vecchi di</label>
                            <input type="date" name="delete_before" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="log-count-info" id="logCountInfo" style="display:none;">
                    <i class="fas fa-info-circle"></i>
                    <span id="logCountText"></span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger" onclick="return confirmDeleteLogs()">
                    <i class="fas fa-trash"></i> Conferma Eliminazione
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                    Annulla
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Modal styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: none;
    overflow-y: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    margin-bottom: 5%;
    padding: 0;
    border: 1px solid #c1c7d0;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    position: relative;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header h2 {
    margin: 0;
    color: #2d3748;
}

.close-modal {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #718096;
}

.close-modal:hover {
    color: #2d3748;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
    max-height: calc(90vh - 180px); /* Altezza modal meno header e footer approssimativi */
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.warning-box {
    background: #fee;
    border: 1px solid #fcc;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    color: #c00;
}

.delete-options {
    margin: 20px 0;
}

.radio-option {
    display: block;
    padding: 15px;
    margin: 10px 0;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.radio-option:hover {
    border-color: #a0aec0;
    background: #f7fafc;
}

.radio-option input[type="radio"] {
    margin-right: 10px;
}

.option-text {
    display: inline-block;
    vertical-align: top;
}

.option-text strong {
    display: block;
    margin-bottom: 5px;
}

.option-text small {
    color: #718096;
}

.filter-options {
    margin: 20px 0 20px 30px;
    padding: 15px;
    background: #f7fafc;
    border-radius: 8px;
}

.log-count-info {
    background: #e6f7ff;
    border: 1px solid #91d5ff;
    border-radius: 8px;
    padding: 10px 15px;
    color: #0050b3;
    margin-top: 15px;
}
</style>

<script>
function showDeleteModal() {
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function toggleDeleteOptions() {
    const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
    const filterOptions = document.getElementById('filterOptions');
    
    if (deleteType === 'all') {
        filterOptions.style.display = 'none';
        document.getElementById('deleteLogsForm').elements.delete_all.value = '1';
    } else {
        filterOptions.style.display = 'block';
        if (document.getElementById('deleteLogsForm').elements.delete_all) {
            document.getElementById('deleteLogsForm').elements.delete_all.remove();
        }
    }
}

function confirmDeleteLogs() {
    const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
    
    if (deleteType === 'all') {
        return confirm('⚠️ ATTENZIONE ⚠️\n\nStai per eliminare TUTTI i log del sistema.\nQuesta operazione è IRREVERSIBILE!\n\nSei assolutamente sicuro di voler procedere?');
    } else {
        const tipo = document.querySelector('select[name="entita_tipo"]').value;
        const dataBefore = document.querySelector('input[name="delete_before"]').value;
        
        if (!tipo && !dataBefore) {
            alert('Seleziona almeno un criterio di eliminazione.');
            return false;
        }
        
        let message = 'Stai per eliminare i log con i seguenti criteri:\n';
        if (tipo) message += `\n- Tipo: ${tipo}`;
        if (dataBefore) message += `\n- Più vecchi di: ${dataBefore}`;
        message += '\n\nConfermi?';
        
        return confirm(message);
    }
}

// Aggiungi campo delete_all quando necessario
document.getElementById('deleteLogsForm').addEventListener('submit', function(e) {
    const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
    
    if (deleteType === 'all') {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_all';
        input.value = '1';
        this.appendChild(input);
    }
});

// Chiudi modal cliccando fuori
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeDeleteModal();
    }
}
</script>
<?php endif; ?>

<!-- Load dedicated JavaScript for log-attivita page -->
<script src="<?php echo APP_PATH; ?>/assets/js/log-attivita.js"></script>

<?php require_once 'components/footer.php'; ?> 