<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Gestione ricerca e filtri
$search = $_GET['search'] ?? '';
$filter_stato = $_GET['stato'] ?? '';
$filter_azienda = $_GET['azienda'] ?? '';

// Carica tutti i documenti (vista generale per admin)
$documentsQuery = "
    SELECT d.*, 
           md.nome as modulo_nome, md.codice as modulo_codice, md.tipo as modulo_tipo,
           u.nome as nome_autore, u.cognome as cognome_autore,
           a.nome as nome_azienda
    FROM documenti d
    LEFT JOIN moduli_documento md ON d.modulo_id = md.id
    LEFT JOIN utenti u ON d.creato_da = u.id
    LEFT JOIN aziende a ON d.azienda_id = a.id
    WHERE 1=1";

$docParams = [];

// Se non è super admin, mostra solo i documenti della sua azienda
if (!$auth->isSuperAdmin() && $currentAzienda) {
    $documentsQuery .= " AND d.azienda_id = :azienda_id";
    $docParams['azienda_id'] = $currentAzienda['azienda_id'];
} elseif ($auth->isSuperAdmin() && !empty($filter_azienda)) {
    // Super admin può filtrare per azienda
    $documentsQuery .= " AND d.azienda_id = :azienda_id";
    $docParams['azienda_id'] = $filter_azienda;
}

// Filtra documenti in bozza se l'utente non ha permessi
if (!$auth->isSuperAdmin()) {
    $canSeeDrafts = false;
    if ($currentAzienda) {
        $permissions = $auth->getUserPermissions();
        $canSeeDrafts = $permissions && $permissions['puo_vedere_bozze'];
    }
    
    if (!$canSeeDrafts) {
        $documentsQuery .= " AND (d.stato != 'bozza' OR d.creato_da = :user_id)";
        $docParams['user_id'] = $user['id'];
    }
}

// Filtro ricerca
if (!empty($search)) {
    $documentsQuery .= " AND (d.titolo LIKE :search OR d.codice LIKE :search2 OR d.contenuto LIKE :search3)";
    $docParams['search'] = "%$search%";
    $docParams['search2'] = "%$search%";
    $docParams['search3'] = "%$search%";
}

// Filtro stato
if (!empty($filter_stato)) {
    $documentsQuery .= " AND d.stato = :stato";
    $docParams['stato'] = $filter_stato;
}

$documentsQuery .= " ORDER BY d.data_creazione DESC";

$documenti = db_query($documentsQuery, $docParams)->fetchAll();

// Carica lista aziende per filtro (solo per super admin)
$aziende = [];
if ($auth->isSuperAdmin()) {
    $aziende = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome")->fetchAll();
}

// Calcola statistiche REALI (non filtrate da ricerca/stato)
// Query separata per le statistiche - mostra totali reali
$statsQuery = "SELECT stato, COUNT(*) as count FROM documenti WHERE 1=1";
$statsParams = [];

// Se non è super admin o ha selezionato un'azienda, filtra per azienda
if (!$auth->isSuperAdmin() && $currentAzienda) {
    $statsQuery .= " AND azienda_id = :azienda_id";
    $statsParams['azienda_id'] = $currentAzienda['azienda_id'];
} elseif ($auth->isSuperAdmin() && !empty($filter_azienda)) {
    $statsQuery .= " AND azienda_id = :azienda_id";
    $statsParams['azienda_id'] = $filter_azienda;
}

// Applica filtri di visibilità per le bozze (ma NON i filtri di ricerca/stato)
if (!$auth->isSuperAdmin()) {
    $canSeeDrafts = false;
    if ($currentAzienda) {
        $permissions = $auth->getUserPermissions();
        $canSeeDrafts = $permissions && $permissions['puo_vedere_bozze'];
    }
    
    if (!$canSeeDrafts) {
        $statsQuery .= " AND (stato != 'bozza' OR creato_da = :user_id)";
        $statsParams['user_id'] = $user['id'];
    }
}

// NON applicare filtri di ricerca o stato alle statistiche
// Le statistiche devono mostrare i totali reali

$statsQuery .= " GROUP BY stato";

$statsResult = db_query($statsQuery, $statsParams)->fetchAll();

// Inizializza contatori
$totaleDocumenti = 0;
$bozze = 0;
$pubblicati = 0;

// Conta per stato
foreach ($statsResult as $stat) {
    $totaleDocumenti += $stat['count'];
    if ($stat['stato'] == 'bozza') {
        $bozze = $stat['count'];
    } elseif ($stat['stato'] == 'pubblicato') {
        $pubblicati = $stat['count'];
    }
}

$pageTitle = 'Documenti';
require_once 'components/header.php';
?>

<style>
    /* Layout principale */
    .main-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .header-left h1 {
        color: #2d3748;
        font-size: 28px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    
    .page-description {
        color: #718096;
        font-size: 16px;
        margin: 0;
    }
    
    .header-actions {
        display: flex;
        gap: 12px;
    }
    
    /* Buttons */
    .btn {
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .btn-primary {
        background: #4299e1;
        color: white;
    }
    
    .btn-primary:hover {
        background: #3182ce;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background: #e2e8f0;
        color: #2d3748;
    }
    
    .btn-secondary:hover {
        background: #cbd5e0;
    }
    
    /* Statistiche */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s ease;
    }
    
    .stat-card:hover {
        border-color: #4299e1;
        box-shadow: 0 4px 16px rgba(66, 153, 225, 0.1);
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #f7fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4299e1;
        font-size: 20px;
    }
    
    .stat-icon.published {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .stat-icon.draft {
        background: #fef3c7;
        color: #92400e;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 4px;
    }
    
    .stat-label {
        color: #718096;
        font-size: 14px;
    }
    
    /* Filters */
    .content-filters {
        margin-bottom: 24px;
    }
    
    .filters-row {
        display: flex;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-container {
        flex: 1;
        min-width: 280px;
    }
    
    .search-input-wrapper {
        position: relative;
    }
    
    .search-input {
        width: 100%;
        padding: 12px 16px 12px 45px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }
    
    .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #718096;
    }
    
    .filter-controls {
        display: flex;
        gap: 12px;
    }
    
    .filter-select {
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        cursor: pointer;
        transition: border-color 0.3s ease;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #4299e1;
    }
    
    /* Content Body */
    .content-body {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    /* Table */
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f8f9fa;
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: #2d3748;
        border-bottom: 2px solid #e2e8f0;
        font-size: 14px;
    }
    
    .data-table td {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .data-table tr:hover {
        background: #f7fafc;
    }
    
    .item-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .item-title {
        font-weight: 500;
        color: #2d3748;
        text-decoration: none;
        font-size: 14px;
    }
    
    .item-title:hover {
        color: #4299e1;
    }
    
    .item-subtitle {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        color: #718096;
    }
    
    /* Badges */
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-secondary {
        background: #e2e8f0;
        color: #4a5568;
    }
    
    .badge-bozza {
        background: #fef3c7;
        color: #92400e;
    }
    
    .badge-pubblicato {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .badge-archiviato {
        background: #e5e7eb;
        color: #6b7280;
    }
    
    .text-secondary {
        color: #718096;
        font-size: 14px;
    }
    
    /* Action buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f7fafc;
        color: #4a5568;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }
    
    .action-btn:hover {
        background: #e2e8f0;
        color: #2d3748;
    }
    
    .delete-btn:hover {
        background: #fed7d7;
        color: #c53030;
        border-color: #feb2b2;
    }
    
    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
    }
    
    .empty-icon {
        margin-bottom: 24px;
    }
    
    .empty-icon i {
        font-size: 64px;
        color: #cbd5e0;
    }
    
    .empty-state h3 {
        font-size: 24px;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 12px;
    }
    
    .empty-state p {
        font-size: 16px;
        color: #718096;
        margin-bottom: 32px;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Responsive */
    @media (max-width: 968px) {
        .content-header {
            flex-direction: column;
            gap: 20px;
            align-items: flex-start;
        }
        
        .filters-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .search-container {
            min-width: auto;
        }
        
        .filter-controls {
            justify-content: stretch;
        }
        
        .filter-select {
            flex: 1;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .main-content {
            padding: 16px;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 16px;
        }
    }
</style>

<div class="main-content">
    <div class="content-header">
        <div class="header-left">
            <h1><i class="fas fa-file-alt"></i> Gestione Documenti</h1>
            <p class="page-description">Gestisci documenti aziendali, moduli e template del sistema</p>
        </div>
        <div class="header-actions">
            <?php if ($currentAzienda): ?>
            <a href="archivio-documenti.php" class="btn btn-secondary">
                <i class="fas fa-archive"></i> Archivio
            </a>
            <?php endif; ?>
            <?php if ($auth->canCreateDocuments()): ?>
            <a href="nuovo-documento.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuovo Documento
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $totaleDocumenti; ?></div>
                <div class="stat-label">Totale Documenti</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon published">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $pubblicati; ?></div>
                <div class="stat-label">Pubblicati</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon draft">
                <i class="fas fa-edit"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $bozze; ?></div>
                <div class="stat-label">Bozze</div>
            </div>
        </div>
    </div>

    <!-- Filtri e ricerca -->
    <div class="content-filters">
        <div class="filters-row">
            <div class="search-container">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form method="GET" style="display: flex; width: 100%;">
                        <input type="text" name="search" placeholder="Cerca documenti..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        <button type="submit" style="display: none;"></button>
                    </form>
                </div>
            </div>
            
            <div class="filter-controls">
                <?php if ($auth->isSuperAdmin()): ?>
                <select name="azienda" class="filter-select" onchange="filterByCompany(this.value)">
                    <option value="">Tutte le aziende</option>
                    <?php foreach ($aziende as $azienda): ?>
                        <option value="<?php echo $azienda['id']; ?>" 
                                <?php echo $filter_azienda == $azienda['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($azienda['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <select name="stato" class="filter-select" onchange="filterDocuments(this.value)">
                    <option value="">Tutti gli stati</option>
                    <option value="pubblicato" <?php echo $filter_stato == 'pubblicato' ? 'selected' : ''; ?>>Pubblicati</option>
                    <option value="bozza" <?php echo $filter_stato == 'bozza' ? 'selected' : ''; ?>>Bozze</option>
                    <option value="archiviato" <?php echo $filter_stato == 'archiviato' ? 'selected' : ''; ?>>Archiviati</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabella documenti -->
    <div class="content-body">
        <?php if (empty($documenti)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3>Nessun documento trovato</h3>
                <p>Non ci sono documenti che corrispondono ai criteri di ricerca selezionati</p>
                <?php if ($auth->canCreateDocuments()): ?>
                <a href="nuovo-documento.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crea il primo documento
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Modulo</th>
                            <th>Stato</th>
                            <?php if ($auth->isSuperAdmin()): ?>
                            <th>Azienda</th>
                            <?php endif; ?>
                            <th>Autore</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documenti as $doc): ?>
                        <tr>
                            <td>
                                <div class="item-info">
                                    <a href="documento.php?action=view&id=<?php echo $doc['id']; ?>" 
                                       class="item-title">
                                        <?php echo htmlspecialchars($doc['titolo']); ?>
                                    </a>
                                    <div class="item-subtitle"><?php echo htmlspecialchars($doc['codice']); ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($doc['modulo_nome'] ?? 'Modulo non trovato'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $doc['stato']; ?>">
                                    <?php echo ucfirst($doc['stato']); ?>
                                </span>
                            </td>
                            <?php if ($auth->isSuperAdmin()): ?>
                            <td>
                                <span class="text-secondary">
                                    <?php echo htmlspecialchars($doc['nome_azienda'] ?? 'N/D'); ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td>
                                <span class="text-secondary">
                                    <?php echo htmlspecialchars($doc['nome_autore'] . ' ' . $doc['cognome_autore']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-secondary">
                                    <?php echo format_date($doc['data_creazione']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="documento.php?action=view&id=<?php echo $doc['id']; ?>" 
                                       class="action-btn" title="Visualizza">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($auth->canEditDocuments()): ?>
                                    <a href="editor-nexio-integrated.php?id=<?php echo $doc['id']; ?>" 
                                       class="action-btn" title="Modifica con Editor Nexio">
                                        <i class="fas fa-file-word"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($auth->canDeleteDocuments()): ?>
                                    <button onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['titolo']); ?>')" 
                                            class="action-btn delete-btn" title="Elimina">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterDocuments(stato) {
    const url = new URL(window.location);
    if (stato) {
        url.searchParams.set('stato', stato);
    } else {
        url.searchParams.delete('stato');
    }
    window.location = url;
}

function filterByCompany(aziendaId) {
    const url = new URL(window.location);
    if (aziendaId) {
        url.searchParams.set('azienda', aziendaId);
    } else {
        url.searchParams.delete('azienda');
    }
    window.location = url;
}

// Funzione per eliminare documento (solo super admin)
async function deleteDocument(documentId, documentTitle) {
    console.log('Tentativo di eliminazione documento:', documentId, documentTitle);
    
    // Prima conferma
    if (!confirm(`Sei sicuro di voler eliminare il documento "${documentTitle}"?`)) {
        return;
    }
    
    // Seconda conferma con avviso
    if (!confirm('ATTENZIONE: Questa azione è irreversibile!\n\nIl documento e tutte le sue versioni verranno eliminati definitivamente.\n\nConfermi di voler procedere?')) {
        return;
    }
    
    // Mostra loader
    const loader = document.createElement('div');
    loader.className = 'loader-overlay';
    loader.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loader);
    
    try {
        console.log('Invio richiesta di eliminazione...');
        
        const response = await fetch('backend/api/delete-document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: documentId })
        });
        
        console.log('Risposta ricevuta:', response.status, response.statusText);
        
        // Prima leggiamo la risposta come testo per vedere cosa c'è
        const responseText = await response.text();
        console.log('Risposta come testo:', responseText);
        
        // Prova a parsare come JSON
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Dati risposta JSON:', data);
        } catch (jsonError) {
            console.error('Errore parsing JSON:', jsonError);
            console.error('Risposta raw:', responseText);
            showMessage('Errore del server: ' + responseText.substring(0, 200), 'error');
            return;
        }
        
        if (data.success) {
            // Mostra messaggio di successo
            showMessage(data.message, 'success');
            
            // Ricarica la pagina dopo 1 secondo con timestamp per evitare cache
            setTimeout(() => {
                window.location.href = window.location.pathname + '?refresh=' + Date.now();
            }, 1000);
        } else {
            console.error('Errore API:', data.message);
            showMessage(data.message || 'Errore durante l\'eliminazione', 'error');
        }
    } catch (error) {
        console.error('Errore JavaScript:', error);
        showMessage('Errore di connessione durante l\'eliminazione', 'error');
    } finally {
        // Rimuovi loader
        if (loader.parentNode) {
            loader.remove();
        }
    }
}

// Funzione per mostrare messaggi
function showMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fade-in`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-hide dopo 5 secondi
    setTimeout(() => {
        alertDiv.classList.add('fade-out');
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}
</script>

<style>
/* Loader overlay */
.loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fade-out {
    animation: fadeOut 0.3s ease-out forwards;
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}
</style>

<?php require_once 'components/footer.php'; ?> 