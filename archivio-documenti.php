<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Se non c'è un'azienda selezionata, reindirizza
if (!$currentAzienda && !$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/seleziona-azienda.php');
}

// Se super admin senza azienda selezionata, mostra messaggio
if (!$currentAzienda && $auth->isSuperAdmin()) {
    $pageTitle = 'Archivio Documenti';
    require_once 'components/header.php';
    ?>
    <div class="content-header">
        <h1><i class="fas fa-archive"></i> Archivio Documenti Azienda</h1>
    </div>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Seleziona un'azienda per visualizzare il suo archivio documenti.
        <a href="seleziona-azienda.php" class="btn btn-primary btn-sm ml-3">Seleziona Azienda</a>
    </div>
    <?php
    require_once 'components/footer.php';
    exit;
}

// Gestione ricerca e filtri
$search = $_GET['search'] ?? '';
$filter_stato = $_GET['stato'] ?? '';
$filter_modulo = $_GET['modulo'] ?? '';

// Carica tutti i documenti dell'azienda con gerarchia
$documentsQuery = "
    SELECT d.*, 
           md.nome as modulo_nome, md.codice as modulo_codice, md.tipo as modulo_tipo,
           md.icona as modulo_icona,
           u.nome as nome_autore, u.cognome as cognome_autore,
           (SELECT COUNT(*) FROM documenti sd WHERE sd.parent_id = d.id AND sd.azienda_id = :azienda_id2) as sotto_documenti_count
    FROM documenti d
    LEFT JOIN moduli_documento md ON d.modulo_id = md.id
    LEFT JOIN utenti u ON d.creato_da = u.id
    WHERE d.parent_id IS NULL
    AND d.azienda_id = :azienda_id";

$docParams = [
    'azienda_id' => $currentAzienda['azienda_id'],
    'azienda_id2' => $currentAzienda['azienda_id']
];

// Filtra documenti in bozza se l'utente non ha permessi
if (!$auth->isSuperAdmin()) {
    $permissions = $auth->getUserPermissions();
    $canSeeDrafts = $permissions && $permissions['puo_vedere_bozze'];
    
    if (!$canSeeDrafts) {
        // Mostra solo documenti pubblicati o bozze create dall'utente
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

// Filtro modulo
if (!empty($filter_modulo)) {
    $documentsQuery .= " AND d.modulo_id = :modulo_id";
    $docParams['modulo_id'] = $filter_modulo;
}

$documentsQuery .= " ORDER BY md.ordine, d.data_creazione DESC";

$documenti = db_query($documentsQuery, $docParams)->fetchAll();

// Carica i sotto-documenti per ogni documento principale
$documentiConSottoDocumenti = [];
foreach ($documenti as $doc) {
    $doc['sotto_documenti'] = [];
    
    if ($doc['sotto_documenti_count'] > 0) {
        // Query per i sotto-documenti
        $subDocsQuery = "
            SELECT d.*, 
                   md.nome as modulo_nome, md.codice as modulo_codice,
                   u.nome as nome_autore, u.cognome as cognome_autore
            FROM documenti d
            LEFT JOIN moduli_documento md ON d.modulo_id = md.id
            LEFT JOIN utenti u ON d.creato_da = u.id
            WHERE d.parent_id = :parent_id
            AND d.azienda_id = :azienda_id";
        
        $subParams = [
            'parent_id' => $doc['id'],
            'azienda_id' => $currentAzienda['azienda_id']
        ];
        
        // Applica gli stessi filtri di visibilità
        if (!$auth->isSuperAdmin() && !$canSeeDrafts) {
            $subDocsQuery .= " AND (d.stato != 'bozza' OR d.creato_da = :user_id)";
            $subParams['user_id'] = $user['id'];
        }
        
        $subDocsQuery .= " ORDER BY d.data_creazione DESC";
        
        $doc['sotto_documenti'] = db_query($subDocsQuery, $subParams)->fetchAll();
    }
    
    $documentiConSottoDocumenti[] = $doc;
}

// Carica i moduli disponibili per il filtro
$moduliQuery = "
    SELECT DISTINCT md.* 
    FROM moduli_documento md
    INNER JOIN azienda_moduli am ON md.id = am.modulo_id
    WHERE am.azienda_id = :azienda_id
    AND am.attivo = 1
    ORDER BY md.ordine, md.nome";

$moduli = db_query($moduliQuery, ['azienda_id' => $currentAzienda['azienda_id']])->fetchAll();

// Calcola statistiche
$totaleDocumenti = count($documenti);
$totaleSottoDocumenti = array_sum(array_column($documenti, 'sotto_documenti_count'));
$bozze = count(array_filter($documenti, function($d) { return $d['stato'] == 'bozza'; }));
$pubblicati = count(array_filter($documenti, function($d) { return $d['stato'] == 'pubblicato'; }));

$pageTitle = 'Archivio Documenti - ' . htmlspecialchars($currentAzienda['nome']);
require_once 'components/header.php';
?>

<style>
    .archive-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }
    
    .archive-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 1.75rem;
    }
    
    .archive-header .company-info {
        font-size: 0.875rem;
        opacity: 0.9;
    }
    
    .documents-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .search-filters {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        flex: 1;
        align-items: center;
    }
    
    .search-box {
        position: relative;
        flex: 1;
        max-width: 400px;
    }
    
    .search-box input {
        width: 100%;
        padding: 0.625rem 2.5rem 0.625rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.875rem;
    }
    
    .search-box button {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.375rem;
    }
    
    .filter-select {
        padding: 0.625rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.875rem;
        background: white;
    }
    
    /* Statistiche */
    .archive-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        text-align: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
    }
    
    .stat-card .stat-label {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    
    /* Vista gerarchica */
    .documents-tree {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.06);
    }
    
    .document-node {
        margin-bottom: 1.5rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        transition: box-shadow 0.2s;
    }
    
    .document-node:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .document-header {
        padding: 1rem 1.5rem;
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border-color);
    }
    
    .document-header-content {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .module-icon {
        font-size: 1.5rem;
        color: var(--primary-color);
        flex-shrink: 0;
    }
    
    .document-main-info {
        flex: 1;
    }
    
    .document-title-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
    }
    
    .document-title {
        font-weight: 600;
        color: var(--text-primary);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .document-title:hover {
        color: var(--primary-color);
    }
    
    .document-code {
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        color: var(--text-secondary);
        background: #f3f4f6;
        padding: 0.125rem 0.5rem;
        border-radius: 4px;
    }
    
    .document-meta {
        display: flex;
        gap: 1.5rem;
        font-size: 0.813rem;
        color: var(--text-secondary);
        flex-wrap: wrap;
    }
    
    .document-meta-item {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }
    
    .sub-documents-count {
        background: var(--primary-color);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.813rem;
        font-weight: 500;
    }
    
    .document-actions {
        display: flex;
        gap: 0.5rem;
        margin-left: auto;
    }
    
    .sub-documents {
        padding: 1rem 1.5rem;
        background: #fafafa;
    }
    
    .sub-document-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .sub-document-item:last-child {
        border-bottom: none;
    }
    
    .tree-indent {
        color: #9ca3af;
        margin-right: 0.75rem;
        font-family: monospace;
    }
    
    .sub-document-link {
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.875rem;
        transition: color 0.2s;
    }
    
    .sub-document-link:hover {
        color: var(--primary-color);
    }
    
    .sub-document-code {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-left: 0.5rem;
    }
    
    .show-all-link {
        display: block;
        text-align: center;
        padding: 0.75rem;
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        border-top: 1px solid #e5e7eb;
        transition: background 0.2s;
    }
    
    .show-all-link:hover {
        background: #f3f4f6;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-bozza {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-pubblicato {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-archiviato {
        background: #e5e7eb;
        color: #374151;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        display: block;
        opacity: 0.3;
    }
    
    .btn-small {
        font-size: 0.813rem;
        padding: 0.375rem 0.75rem;
    }
</style>

<div class="archive-header">
    <h1><i class="fas fa-archive"></i> Archivio Documenti</h1>
    <div class="company-info">
        <i class="fas fa-building"></i> <?php echo htmlspecialchars($currentAzienda['nome']); ?>
    </div>
</div>

<!-- Statistiche -->
<div class="archive-stats">
    <div class="stat-card">
        <div class="stat-value"><?php echo $totaleDocumenti; ?></div>
        <div class="stat-label">Documenti Principali</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $totaleSottoDocumenti; ?></div>
        <div class="stat-label">Sotto-documenti</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $pubblicati; ?></div>
        <div class="stat-label">Pubblicati</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $bozze; ?></div>
        <div class="stat-label">Bozze</div>
    </div>
</div>

<!-- Filtri e ricerca -->
<div class="documents-header">
    <div class="search-filters">
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Cerca documenti..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        
        <select name="modulo" class="filter-select" onchange="filterByModule(this.value)">
            <option value="">Tutti i moduli</option>
            <?php foreach ($moduli as $modulo): ?>
                <option value="<?php echo $modulo['id']; ?>" 
                        <?php echo $filter_modulo == $modulo['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($modulo['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="stato" class="filter-select" onchange="filterByStatus(this.value)">
            <option value="">Tutti gli stati</option>
            <option value="pubblicato" <?php echo $filter_stato == 'pubblicato' ? 'selected' : ''; ?>>Pubblicati</option>
            <option value="bozza" <?php echo $filter_stato == 'bozza' ? 'selected' : ''; ?>>Bozze</option>
            <option value="archiviato" <?php echo $filter_stato == 'archiviato' ? 'selected' : ''; ?>>Archiviati</option>
        </select>
    </div>
    
    <div class="header-actions">
        <?php if ($auth->canCreateDocuments()): ?>
                        <a href="editor-onlyoffice-integrated.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuovo Documento
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Vista gerarchica -->
<?php if (empty($documentiConSottoDocumenti)): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h2>Nessun documento trovato</h2>
        <p>Non ci sono documenti che corrispondono ai criteri di ricerca.</p>
    </div>
<?php else: ?>
    <div class="documents-tree">
        <?php foreach ($documentiConSottoDocumenti as $doc): ?>
            <div class="document-node">
                <div class="document-header">
                    <div class="document-header-content">
                        <?php
                        // Determina l'icona del modulo
                        $iconClass = 'fa-file-alt';
                        if ($doc['modulo_icona']) {
                            $iconClass = $doc['modulo_icona'];
                        } elseif ($doc['modulo_tipo'] == 'word') {
                            $iconClass = 'fa-file-word';
                        } elseif ($doc['modulo_tipo'] == 'excel') {
                            $iconClass = 'fa-file-excel';
                        }
                        ?>
                        <i class="module-icon fas <?php echo $iconClass; ?>"></i>
                        
                        <div class="document-main-info">
                            <div class="document-title-row">
                                <a href="documento.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="document-title">
                                    <?php echo htmlspecialchars($doc['titolo']); ?>
                                </a>
                                <span class="document-code"><?php echo htmlspecialchars($doc['codice']); ?></span>
                                <span class="status-badge status-<?php echo $doc['stato']; ?>">
                                    <?php echo ucfirst($doc['stato']); ?>
                                </span>
                                <?php if ($doc['sotto_documenti_count'] > 0): ?>
                                    <span class="sub-documents-count">
                                        <?php echo $doc['sotto_documenti_count']; ?> sotto-document<?php echo $doc['sotto_documenti_count'] > 1 ? 'i' : 'o'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="document-meta">
                                <span class="document-meta-item">
                                    <i class="fas fa-folder"></i>
                                    <?php echo htmlspecialchars($doc['modulo_nome'] ?? 'Modulo non trovato'); ?>
                                </span>
                                <span class="document-meta-item">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($doc['nome_autore'] . ' ' . $doc['cognome_autore']); ?>
                                </span>
                                <span class="document-meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo format_date($doc['data_creazione']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="document-actions">
                            <?php if ($auth->canEditDocuments()): ?>
                                                            <a href="editor-onlyoffice-integrated.php?id=<?php echo $doc['id']; ?>" 
                               class="btn btn-secondary btn-small">
                                <i class="fas fa-edit"></i> Modifica
                            </a>
                            <?php endif; ?>
                            <?php if ($auth->canDeleteDocuments()): ?>
                            <button onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['titolo']); ?>')" 
                                    class="btn btn-danger btn-small">
                                <i class="fas fa-trash"></i> Elimina
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($doc['sotto_documenti_count'] > 0): ?>
                <div class="sub-documents">
                    <?php 
                    $maxVisible = 3;
                    $visibleDocs = array_slice($doc['sotto_documenti'], 0, $maxVisible);
                    ?>
                    
                    <?php foreach ($visibleDocs as $subDoc): ?>
                    <div class="sub-document-item">
                        <span class="tree-indent">└─</span>
                        <a href="documento.php?action=view&id=<?php echo $subDoc['id']; ?>" 
                           class="sub-document-link">
                            <?php echo htmlspecialchars($subDoc['titolo']); ?>
                        </a>
                        <span class="sub-document-code"><?php echo htmlspecialchars($subDoc['codice']); ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($doc['sotto_documenti']) > $maxVisible): ?>
                    <a href="documenti.php?parent_id=<?php echo $doc['id']; ?>" class="show-all-link">
                        <i class="fas fa-plus-circle"></i> 
                        Mostra tutti i <?php echo count($doc['sotto_documenti']); ?> sotto-documenti
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function filterByModule(moduleId) {
    const url = new URL(window.location);
    if (moduleId) {
        url.searchParams.set('modulo', moduleId);
    } else {
        url.searchParams.delete('modulo');
    }
    window.location = url;
}

function filterByStatus(stato) {
    const url = new URL(window.location);
    if (stato) {
        url.searchParams.set('stato', stato);
    } else {
        url.searchParams.delete('stato');
    }
    window.location = url;
}

// Funzione per eliminare documento (solo super admin)
async function deleteDocument(documentId, documentTitle) {
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
        const response = await fetch('backend/api/delete-document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: documentId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mostra messaggio di successo
            showMessage(data.message, 'success');
            
            // Ricarica la pagina dopo 1 secondo
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage(data.message || 'Errore durante l\'eliminazione', 'error');
        }
    } catch (error) {
        console.error('Errore:', error);
        showMessage('Errore di connessione durante l\'eliminazione', 'error');
    } finally {
        // Rimuovi loader
        loader.remove();
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