<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Solo admin possono accedere
if (!$auth->canAccess('users', 'read')) {
    redirect(APP_PATH . '/dashboard.php');
}

// Se è super admin senza azienda selezionata, mostra tutti gli utenti
// Altrimenti mostra solo gli utenti dell'azienda corrente
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM documenti WHERE creato_da = u.id) as num_documenti,
           (SELECT COUNT(*) FROM eventi WHERE creato_da = u.id) as num_eventi
    FROM utenti u
";

if ($currentAzienda && !$auth->isSuperAdmin()) {
    // Mostra solo utenti dell'azienda corrente
    $sql .= " JOIN utenti_aziende ua ON u.id = ua.utente_id 
              WHERE ua.azienda_id = :azienda_id AND ua.attivo = 1";
    $params = ['azienda_id' => $currentAzienda['azienda_id']];
} else if ($currentAzienda && $auth->isSuperAdmin()) {
    // Super admin con azienda selezionata
    $sql .= " JOIN utenti_aziende ua ON u.id = ua.utente_id 
              WHERE ua.azienda_id = :azienda_id";
    $params = ['azienda_id' => $currentAzienda['azienda_id']];
} else {
    // Super admin vista globale
    $params = [];
}

$sql .= " ORDER BY u.cognome, u.nome";

$stmt = db_query($sql, $params ?? []);
$utenti = $stmt->fetchAll();

$pageTitle = 'Gestione Utenti';
require_once 'components/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-users"></i> Gestione Utenti</h1>
    <div class="header-actions">
        <?php if ($auth->canAccess('users', 'write')): ?>
        <a href="<?php echo APP_PATH; ?>/aziende.php" class="btn btn-primary">
            <i class="fas fa-users-cog"></i> Gestisci da Aziende
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="users-table-container">
    <?php if (empty($utenti)): ?>
    <div class="empty-state">
        <i>👥</i>
        <h2>Nessun utente trovato</h2>
        <p>Non ci sono utenti <?php echo $currentAzienda ? 'in questa azienda' : 'nel sistema'; ?>.</p>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Ruolo</th>
                    <th>Stato</th>
                    <th>Attività</th>
                    <th>Ultimo accesso</th>
                    <?php if (!$currentAzienda && $auth->isSuperAdmin()): ?>
                    <th>Aziende</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utenti as $utente): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: #2d3748;">
                            <?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?>
                        </div>
                        <div style="color: #718096; font-size: 13px;">
                            <?php echo htmlspecialchars($utente['email']); ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge" style="<?php
                            echo $utente['ruolo'] == 'super_admin' ? 'background: #fee; color: #c33;' : 
                                 ($utente['ruolo'] == 'admin' ? 'background: #e6f3ff; color: #3182ce;' : 
                                 ($utente['ruolo'] == 'staff' ? 'background: #fff4e6; color: #d97706;' :
                                  'background: #f0fff4; color: #38a169;'));
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $utente['ruolo'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $utente['attivo'] ? 'attivo' : 'inattivo'; ?>">
                            <?php echo $utente['attivo'] ? 'Attivo' : 'Disattivo'; ?>
                        </span>
                    </td>
                    <td>
                        <div style="color: #718096; font-size: 13px;">
                            <span style="margin-right: 10px;">
                                <i>📄</i> <?php echo $utente['num_documenti']; ?> doc
                            </span>
                            <span>
                                <i>📅</i> <?php echo $utente['num_eventi']; ?> eventi
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php echo $utente['ultimo_accesso'] ? format_datetime($utente['ultimo_accesso']) : 'Mai'; ?>
                    </td>
                    <?php if (!$currentAzienda && $auth->isSuperAdmin()): ?>
                    <td>
                        <?php
                        $stmt_az = db_query("
                            SELECT a.nome 
                            FROM utenti_aziende ua
                            JOIN aziende a ON ua.azienda_id = a.id
                            WHERE ua.utente_id = :user_id",
                            ['user_id' => $utente['id']]
                        );
                        $aziende_utente = $stmt_az->fetchAll();
                        ?>
                        <div style="font-size: 13px;">
                            <?php foreach ($aziende_utente as $az): ?>
                                <span class="status-badge" style="background: #e6f3ff; color: #3182ce; margin: 2px;">
                                    <?php echo htmlspecialchars($az['nome']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'components/footer.php'; ?> 