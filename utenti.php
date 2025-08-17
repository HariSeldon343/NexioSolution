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
require_once 'components/page-header.php';

renderPageHeader('Gestione Utenti', 'Amministra gli utenti del sistema', 'users');

// I pulsanti sono spostati nella action bar sotto l'header
?>

<div class="action-bar" style="margin-bottom: 2rem;">
    <?php if ($auth->canAccess('users', 'write')): ?>
    <a href="<?php echo APP_PATH; ?>/aziende.php" 
       
       onmouseover="this.style.background='#2d5a9f'; this.style.color='white';"
       onmouseout="this.style.background='white'; this.style.color='#2d5a9f';">
        <i class="fas fa-users-cog" style="font-size: 0.75rem;"></i> Gestisci da Aziende
    </a>
    <?php endif; ?>
</div>

<div class="users-table-container">
    <?php if (empty($utenti)): ?>
    <div class="empty-state">
        <i>👥</i>
        <h2>Nessun utente trovato</h2>
        <p>Non ci sono utenti <?php echo $currentAzienda ? 'in questa azienda' : 'nel sistema'; ?>.</p>
    </div>
    <?php else: ?>
    <div class="table-container" style="background: white; border-radius: 4px; padding: 0; border: 1px solid #e5e7eb; overflow: hidden;">
        <table style="width: 100%; border: none !important; border-collapse: collapse;">
            <thead style="background: white; border-bottom: 1px solid #e5e7eb;">
                <tr>
                    <th >Utente</th>
                    <th >Ruolo</th>
                    <th >Stato</th>
                    <th >Attività</th>
                    <th >Ultimo accesso</th>
                    <?php if (!$currentAzienda && $auth->isSuperAdmin()): ?>
                    <th>Aziende</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utenti as $utente): ?>
                <tr style="border-bottom: 1px solid #f9fafb;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 0.75rem !important; border: none !important;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div >
                                <?php 
                                $initials = strtoupper(substr($utente['nome'], 0, 1) . substr($utente['cognome'], 0, 1));
                                echo $initials;
                                ?>
                            </div>
                            <div>
                                <div >
                                    <?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?>
                                </div>
                                <div >
                                    <?php echo htmlspecialchars($utente['email']); ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 0.75rem !important; border: none !important;">
                        <span style="<?php
                            echo $utente['ruolo'] == 'super_admin' ? 'border-color: #dc2626; color: #dc2626;' : 
                                 ($utente['ruolo'] == 'admin' ? 'border-color: #2d5a9f; color: #2d5a9f;' : 
                                 ($utente['ruolo'] == 'staff' ? 'border-color: #d97706; color: #d97706;' :
                                  'border-color: #6b7280; color: #6b7280;'));
                        ?> padding: 2px 6px !important; border-radius: 2px !important; font-size: 0.625rem !important; font-weight: 400 !important; display: inline-flex; align-items: center; gap: 0.25rem; background: white !important; border: 1px solid; text-transform: uppercase !important; letter-spacing: 0.05em !important;">
                            <?php if($utente['ruolo'] == 'super_admin'): ?>
                                <i class="fas fa-shield-alt" style="font-size: 0.625rem;"></i>
                            <?php elseif($utente['ruolo'] == 'admin'): ?>
                                <i class="fas fa-user-cog" style="font-size: 0.625rem;"></i>
                            <?php elseif($utente['ruolo'] == 'staff'): ?>
                                <i class="fas fa-user-tie" style="font-size: 0.625rem;"></i>
                            <?php else: ?>
                                <i class="fas fa-user" style="font-size: 0.625rem;"></i>
                            <?php endif; ?>
                            <?php echo ucfirst(str_replace('_', ' ', $utente['ruolo'])); ?>
                        </span>
                    </td>
                    <td style="padding: 0.75rem !important; border: none !important;">
                        <span style="<?php echo $utente['attivo'] ? 'border-color: #10b981; color: #10b981;' : 'border-color: #dc2626; color: #dc2626;'; ?> padding: 2px 6px !important; border-radius: 2px !important; font-size: 0.625rem !important; font-weight: 400 !important; display: inline-flex; align-items: center; gap: 0.25rem; background: white !important; border: 1px solid; text-transform: uppercase !important; letter-spacing: 0.05em !important;">
                            <?php if($utente['attivo']): ?>
                                <span ></span>
                            <?php else: ?>
                                <span ></span>
                            <?php endif; ?>
                            <?php echo $utente['attivo'] ? 'Attivo' : 'Disattivo'; ?>
                        </span>
                    </td>
                    <td style="padding: 0.75rem !important; border: none !important;">
                        <div style="display: flex; gap: 0.5rem;">
                            <span >
                                <i class="fas fa-file-alt" ></i> <?php echo $utente['num_documenti']; ?> doc
                            </span>
                            <span >
                                <i class="fas fa-calendar" ></i> <?php echo $utente['num_eventi']; ?> eventi
                            </span>
                        </div>
                    </td>
                    <td >
                        <?php if($utente['ultimo_accesso']): ?>
                            <i class="fas fa-clock" ></i>
                        <?php endif; ?>
                        <?php echo $utente['ultimo_accesso'] ? format_datetime($utente['ultimo_accesso']) : '<span >Mai effettuato</span>'; ?>
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
                                <span class="status-badge" >
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