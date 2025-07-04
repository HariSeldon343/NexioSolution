<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

if (!$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/dashboard.php');
}

// Database instance handled by functions

// Paginazione
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filtri
$filter_stato = $_GET['stato'] ?? '';
$filter_tipo = $_GET['tipo'] ?? '';
$filter_search = $_GET['search'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Costruisci query
$where = [];
$params = [];

if ($filter_stato) {
    $where[] = "stato = :stato";
    $params['stato'] = $filter_stato;
}

if ($filter_tipo) {
    $where[] = "tipo_notifica = :tipo";
    $params['tipo'] = $filter_tipo;
}

if ($filter_search) {
    $where[] = "(destinatario_email LIKE :search OR oggetto LIKE :search2)";
    $params['search'] = "%$filter_search%";
    $params['search2'] = "%$filter_search%";
}

if ($filter_date_from) {
    $where[] = "creato_il >= :date_from";
    $params['date_from'] = $filter_date_from . ' 00:00:00';
}

if ($filter_date_to) {
    $where[] = "creato_il <= :date_to";
    $params['date_to'] = $filter_date_to . ' 23:59:59';
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Conta totale record
try {
    $stmt = db_query("SELECT COUNT(*) as total FROM notifiche_email $whereClause", $params);
    $totalRecords = $stmt->fetch()['total'];
} catch (Exception $e) {
    $totalRecords = 0;
}

$totalPages = ceil($totalRecords / $perPage);

// Recupera email
try {
    $sql = "
        SELECT * FROM notifiche_email 
        $whereClause
        ORDER BY creato_il DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $perPage;
    $params['offset'] = $offset;
    
    $stmt = db_query($sql, $params);
    $emails = $stmt->fetchAll();
} catch (Exception $e) {
    $emails = [];
    $error = "Tabella notifiche_email non trovata. Esegui il processore email per crearla.";
}

// Statistiche
try {
    $stats = [];
    
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE stato = 'pending'");
    $stats['pending'] = $stmt->fetch()['count'];
    
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE stato = 'sent'");
    $stats['sent'] = $stmt->fetch()['count'];
    
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE stato = 'failed'");
    $stats['failed'] = $stmt->fetch()['count'];
    
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE DATE(creato_il) = CURDATE()");
    $stats['today'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    $stats = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'today' => 0];
}

// Azione: Reinvia email fallita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_email'])) {
    $emailId = intval($_POST['retry_email']);
    
    try {
        db_query("
            UPDATE notifiche_email 
            SET stato = 'pending', tentativi = 0, ultimo_errore = NULL 
            WHERE id = :id AND stato = 'failed'
        ", ['id' => $emailId]);
        
        $message = "Email rimessa in coda per il reinvio";
    } catch (Exception $e) {
        $error = "Errore nel reinvio: " . $e->getMessage();
    }
}

// Azione: Elimina email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email'])) {
    $emailId = intval($_POST['delete_email']);
    
    try {
        db_query("DELETE FROM notifiche_email WHERE id = :id", ['id' => $emailId]);
        $message = "Email eliminata";
    } catch (Exception $e) {
        $error = "Errore nell'eliminazione: " . $e->getMessage();
    }
}

$pageTitle = 'Log Email';
require_once 'components/header.php';
?>

<style>
.email-log-container {
    max-width: 1400px;
    margin: 0 auto;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e8e8e8;
}

.stat-card .value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-card.pending .value { color: #f39c12; }
.stat-card.sent .value { color: #27ae60; }
.stat-card.failed .value { color: #e74c3c; }
.stat-card.today .value { color: #3498db; }

.filter-section {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.email-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.email-table table {
    width: 100%;
    border-collapse: collapse;
}

.email-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 1px solid #dee2e6;
}

.email-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f0;
}

.email-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.sent {
    background: #d4edda;
    color: #155724;
}

.status-badge.failed {
    background: #f8d7da;
    color: #721c24;
}

.email-subject {
    font-weight: 500;
    margin-bottom: 3px;
}

.email-to {
    color: #6c757d;
    font-size: 0.9rem;
}

.email-error {
    color: #dc3545;
    font-size: 0.85rem;
    margin-top: 5px;
}

.email-type {
    display: inline-block;
    padding: 3px 8px;
    background: #e9ecef;
    border-radius: 4px;
    font-size: 0.8rem;
    color: #495057;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-icon {
    padding: 5px 10px;
    font-size: 0.85rem;
    border: none;
    background: none;
    cursor: pointer;
    color: #6c757d;
    transition: all 0.2s;
}

.btn-icon:hover {
    color: #495057;
}

.btn-icon.retry {
    color: #28a745;
}

.btn-icon.retry:hover {
    color: #218838;
}

.btn-icon.delete {
    color: #dc3545;
}

.btn-icon.delete:hover {
    color: #c82333;
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
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: #495057;
    transition: all 0.2s;
}

.pagination a:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.pagination .active {
    background: #2d5a9f;
    color: white;
    border-color: #2d5a9f;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.3;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
}

.modal-close:hover {
    color: #495057;
}

.email-content {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}
</style>

<div class="content-header">
    <h1><i class="fas fa-list"></i> Log Email</h1>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="email-log-container">
    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="value"><?php echo $stats['pending']; ?></div>
            <div class="label">In Coda</div>
        </div>
        <div class="stat-card sent">
            <div class="value"><?php echo $stats['sent']; ?></div>
            <div class="label">Inviate</div>
        </div>
        <div class="stat-card failed">
            <div class="value"><?php echo $stats['failed']; ?></div>
            <div class="label">Fallite</div>
        </div>
        <div class="stat-card today">
            <div class="value"><?php echo $stats['today']; ?></div>
            <div class="label">Oggi</div>
        </div>
    </div>
    
    <!-- Filtri -->
    <div class="filter-section">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Stato</label>
                    <select name="stato" class="form-control">
                        <option value="">Tutti</option>
                        <option value="pending" <?php echo $filter_stato === 'pending' ? 'selected' : ''; ?>>In Coda</option>
                        <option value="sent" <?php echo $filter_stato === 'sent' ? 'selected' : ''; ?>>Inviate</option>
                        <option value="failed" <?php echo $filter_stato === 'failed' ? 'selected' : ''; ?>>Fallite</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo" class="form-control">
                        <option value="">Tutti</option>
                        <option value="documento_creato" <?php echo $filter_tipo === 'documento_creato' ? 'selected' : ''; ?>>Documento Creato</option>
                        <option value="documento_modificato" <?php echo $filter_tipo === 'documento_modificato' ? 'selected' : ''; ?>>Documento Modificato</option>
                        <option value="ticket_creato" <?php echo $filter_tipo === 'ticket_creato' ? 'selected' : ''; ?>>Ticket Creato</option>
                        <option value="evento_creato" <?php echo $filter_tipo === 'evento_creato' ? 'selected' : ''; ?>>Evento Creato</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Cerca</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Email o oggetto..." 
                           value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
                
                <div class="form-group">
                    <label>Dal</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo $filter_date_from; ?>">
                </div>
                
                <div class="form-group">
                    <label>Al</label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?php echo $filter_date_to; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtra
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tabella Email -->
    <?php if (!empty($emails)): ?>
        <div class="email-table">
            <table>
                <thead>
                    <tr>
                        <th>Data/Ora</th>
                        <th>Destinatario</th>
                        <th>Oggetto</th>
                        <th>Tipo</th>
                        <th>Stato</th>
                        <th>Tentativi</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $email): ?>
                        <tr>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($email['creato_il'])); ?>
                                <?php if ($email['stato'] === 'sent' && $email['inviato_il']): ?>
                                    <br><small class="text-muted">
                                        Inviata: <?php echo date('H:i', strtotime($email['inviato_il'])); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="email-to"><?php echo htmlspecialchars($email['destinatario_email']); ?></div>
                                <?php if ($email['destinatario_nome']): ?>
                                    <small><?php echo htmlspecialchars($email['destinatario_nome']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="email-subject"><?php echo htmlspecialchars($email['oggetto']); ?></div>
                                <?php if ($email['stato'] === 'failed' && $email['ultimo_errore']): ?>
                                    <div class="email-error">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($email['ultimo_errore']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($email['tipo_notifica']): ?>
                                    <span class="email-type"><?php echo htmlspecialchars($email['tipo_notifica']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $email['stato']; ?>">
                                    <?php 
                                    switch($email['stato']) {
                                        case 'pending': echo 'In Coda'; break;
                                        case 'sent': echo 'Inviata'; break;
                                        case 'failed': echo 'Fallita'; break;
                                        default: echo ucfirst($email['stato']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $email['tentativi']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon view" onclick="viewEmail(<?php echo $email['id']; ?>)" title="Visualizza">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($email['stato'] === 'failed'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="retry_email" value="<?php echo $email['id']; ?>">
                                            <button type="submit" class="btn-icon retry" title="Reinvia">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Eliminare questa email?');">
                                        <input type="hidden" name="delete_email" value="<?php echo $email['id']; ?>">
                                        <button type="submit" class="btn-icon delete" title="Elimina">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginazione -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>
                
                <span class="active"><?php echo $page; ?> di <?php echo $totalPages; ?></span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $totalPages; ?><?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Nessuna email trovata</h3>
            <p>Non ci sono email che corrispondono ai filtri selezionati.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal visualizzazione email -->
<div id="emailModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2>Dettagli Email</h2>
        <div id="emailDetails"></div>
    </div>
</div>

<script>
// Carica i dettagli email per la visualizzazione
const emailData = <?php echo json_encode($emails ?? []); ?>;

function viewEmail(id) {
    const email = emailData.find(e => e.id == id);
    if (!email) return;
    
    const detailsHtml = `
        <div class="email-details">
            <p><strong>Destinatario:</strong> ${email.destinatario_email}</p>
            <p><strong>Nome:</strong> ${email.destinatario_nome || 'N/D'}</p>
            <p><strong>Oggetto:</strong> ${email.oggetto}</p>
            <p><strong>Tipo:</strong> ${email.tipo_notifica || 'N/D'}</p>
            <p><strong>Stato:</strong> <span class="status-badge ${email.stato}">${
                email.stato === 'pending' ? 'In Coda' :
                email.stato === 'sent' ? 'Inviata' : 'Fallita'
            }</span></p>
            <p><strong>Creata:</strong> ${new Date(email.creato_il).toLocaleString('it-IT')}</p>
            ${email.inviato_il ? `<p><strong>Inviata:</strong> ${new Date(email.inviato_il).toLocaleString('it-IT')}</p>` : ''}
            <p><strong>Tentativi:</strong> ${email.tentativi}</p>
            ${email.ultimo_errore ? `<p><strong>Ultimo errore:</strong> <span style="color: #dc3545;">${email.ultimo_errore}</span></p>` : ''}
            
            <h3>Contenuto Email</h3>
            <div class="email-content">
                ${email.contenuto}
            </div>
        </div>
    `;
    
    document.getElementById('emailDetails').innerHTML = detailsHtml;
    document.getElementById('emailModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('emailModal').style.display = 'none';
}

// Chiudi modal cliccando fuori
window.onclick = function(event) {
    const modal = document.getElementById('emailModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php require_once 'components/footer.php'; ?>
 