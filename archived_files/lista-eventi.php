<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Prepara query per eventi
$sql = "
    SELECT e.*, u.nome as nome_creatore, u.cognome as cognome_creatore, 
           a.nome as nome_azienda
    FROM eventi e
    LEFT JOIN utenti u ON e.creato_da = u.id
    LEFT JOIN aziende a ON e.azienda_id = a.id
    WHERE 1=1";

$params = [];

// Filtra per azienda se necessario
if ($currentAzienda && !$auth->isSuperAdmin()) {
    $sql .= " AND e.azienda_id = :azienda_id";
    $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                 (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
    $params['azienda_id'] = $aziendaId;
}
// Se è super admin senza azienda selezionata, mostra tutti gli eventi

$sql .= " ORDER BY e.data_inizio DESC";

$stmt = db_query($sql, $params);
$eventi = $stmt->fetchAll();

$pageTitle = 'Eventi';
require_once 'components/header.php';
?>

<div class="content-header">
    <h1>Eventi</h1>
    <div>
        <a href="<?php echo APP_PATH; ?>/calendario.php" class="btn btn-secondary">
            <i>🗓️</i> Vista Calendario
        </a>
        <?php if ($auth->canAccess('calendar', 'write')): ?>
        <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="btn btn-primary">
            <i>➕</i> Nuovo Evento
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($eventi)): ?>
    <div class="empty-state">
        <i>📅</i>
        <h2>Nessun evento presente</h2>
        <p>Non ci sono eventi da visualizzare.</p>
        <?php if ($auth->canAccess('calendar', 'write')): ?>
        <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="btn btn-primary" style="margin-top: 20px;">
            <i>➕</i> Crea il primo evento
        </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Titolo</th>
                    <th>Data/Ora</th>
                    <th>Tipo</th>
                    <th>Luogo</th>
                    <?php if (!$currentAzienda || $auth->isSuperAdmin()): ?>
                    <th>Azienda</th>
                    <?php endif; ?>
                    <th>Creato da</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventi as $evento): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($evento['titolo']); ?></strong>
                        <?php if ($evento['descrizione']): ?>
                        <br><small style="color: #718096;">
                            <?php echo htmlspecialchars(substr($evento['descrizione'], 0, 50)); ?>...
                        </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo format_datetime($evento['data_inizio']); ?>
                        <?php if ($evento['data_fine'] && $evento['data_fine'] != $evento['data_inizio']): ?>
                        <br><small>fino a: <?php echo format_datetime($evento['data_fine']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $evento['tipo']; ?>">
                            <?php echo ucfirst($evento['tipo']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($evento['luogo'] ?: '-'); ?></td>
                    <?php if (!$currentAzienda || $auth->isSuperAdmin()): ?>
                    <td>
                        <?php if ($evento['nome_azienda']): ?>
                            <span style="color: #6366f1; font-weight: 500;">
                                <?php echo htmlspecialchars($evento['nome_azienda']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #9ca3af;">Personale</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($evento['nome_creatore'] . ' ' . $evento['cognome_creatore']); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="<?php echo APP_PATH; ?>/eventi.php?action=view&id=<?php echo $evento['id']; ?>" 
                               class="btn btn-secondary btn-small">
                                <i>👁️</i> Dettagli
                            </a>
                            <?php if ($auth->canAccess('calendar', 'write') || $evento['creato_da'] == $user['id']): ?>
                            <a href="<?php echo APP_PATH; ?>/eventi.php?action=edit&id=<?php echo $evento['id']; ?>" 
                               class="btn btn-secondary btn-small">
                                <i>✏️</i> Modifica
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once 'components/footer.php'; ?> 