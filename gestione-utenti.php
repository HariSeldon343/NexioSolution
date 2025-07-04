<?php
require_once 'backend/config/config.php';

// Verifica connessione database prima di procedere
try {
    $test_connection = db_connection();
    $test_connection->query("SELECT 1");
} catch (Exception $e) {
    // Se siamo qui, significa che il database non è disponibile
    header('Location: ' . APP_PATH . '/check-database.php');
    exit;
}

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo i super admin possono accedere
if (!$auth->isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// Database instance handled by functions
$user = $auth->getUser();

// Gestione azioni AJAX per migliori performance
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $response = ['success' => false, 'message' => ''];
        
        switch ($_POST['action']) {
            case 'create_user':
                $response = createUser($_POST);
                break;
            case 'reset_password':
                $response = resetPassword($_POST['user_id']);
                break;
            case 'toggle_status':
                $response = toggleUserStatus($_POST['user_id'], $_POST['new_status']);
                break;
            case 'check_email':
                $response = checkEmailExists($_POST['email']);
                break;
            case 'delete_user':
                $response = deleteUser($_POST['user_id']);
                break;
            case 'check_azienda_limits':
                $response = checkAziendaLimits($_POST['azienda_id']);
                break;
        }
        
        echo json_encode($response);
        exit;
    }
}

// Funzioni ottimizzate
function createUser($data) {
    // Validazioni input
    if (!isset($data['nome']) || !isset($data['cognome']) || !isset($data['email']) || !isset($data['ruolo'])) {
        return ['success' => false, 'message' => 'Dati mancanti: nome, cognome, email e ruolo sono obbligatori'];
    }
    
    $nome = sanitize_input($data['nome']);
    $cognome = sanitize_input($data['cognome']);
    $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
    $data_nascita = isset($data['data_nascita']) ? $data['data_nascita'] : null;
    $ruolo = sanitize_input($data['ruolo']);
    
    // Validazioni
    if (empty($nome) || empty($cognome)) {
        return ['success' => false, 'message' => 'Nome e cognome non possono essere vuoti'];
    }
    
    if (!$email) {
        return ['success' => false, 'message' => 'Email non valida'];
    }
    
    if (!in_array($ruolo, ['admin', 'utente', 'super_admin'])) {
        return ['success' => false, 'message' => 'Ruolo non valido'];
    }
    
    // Nuovi campi per associazione aziendale
    $azienda_id = !empty($data['azienda_id']) ? intval($data['azienda_id']) : null;
    $ruolo_azienda = !empty($data['ruolo_azienda']) ? $data['ruolo_azienda'] : null;
    
    // Genera o valida password
    if (isset($data['password_type']) && $data['password_type'] === 'manual' && !empty($data['password'])) {
        $password = $data['password'];
        
        // Validazione password manuale (8 caratteri, 1 maiuscola, 1 carattere speciale)
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'La password deve essere di almeno 8 caratteri'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['success' => false, 'message' => 'La password deve contenere almeno una lettera maiuscola'];
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return ['success' => false, 'message' => 'La password deve contenere almeno un carattere speciale'];
        }
    } else {
        // Genera password automatica complessa di 8 caratteri
        $password = generateRandomPassword(8);
    }
    
    // Inizia transazione per performance
    db_connection()->beginTransaction();
    
    try {
        // Verifica email con query preparata cached
        $stmt = db_query("SELECT id FROM utenti WHERE email = ? LIMIT 1", [$email]);
        
        if ($stmt->fetch()) {
            db_connection()->rollback();
            return ['success' => false, 'message' => 'Email già esistente nel sistema!'];
        }
        
        // Validazioni per ruoli aziendali
        if ($azienda_id && $ruolo_azienda) {
            // Carica informazioni azienda per verificare limiti
            $stmt = db_query("SELECT max_referenti FROM aziende WHERE id = ?", [$azienda_id]);
            $azienda_info = $stmt->fetch();
            $max_referenti = $azienda_info['max_referenti'] ?? 5;
            
            if ($ruolo_azienda === 'responsabile_aziendale') {
                // Verifica che non ci sia già un responsabile aziendale
                $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'responsabile_aziendale' AND attivo = 1", 
                               [$azienda_id]);
                $existing_responsabile = $stmt->fetch()['count'];
                
                if ($existing_responsabile > 0) {
                    db_connection()->rollback();
                    return ['success' => false, 'message' => 'Esiste già un Responsabile Aziendale per questa azienda.'];
                }
            } elseif ($ruolo_azienda === 'referente') {
                // Verifica limite massimo referenti
                $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'referente' AND attivo = 1", 
                               [$azienda_id]);
                $existing_referenti = $stmt->fetch()['count'];
                
                if ($existing_referenti >= $max_referenti) {
                    db_connection()->rollback();
                    return ['success' => false, 'message' => "Limite massimo di $max_referenti referenti raggiunto per questa azienda."];
                }
            }
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $passwordScadenza = date('Y-m-d', strtotime('+60 days')); // Cambiato da 90 a 60 giorni
        
        // Genera username dall'email (parte prima di @)
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        
        // Verifica unicità username e aggiungi numero se necessario
        $counter = 1;
        while (true) {
            $stmt = db_query("SELECT id FROM utenti WHERE username = ? LIMIT 1", [$username]);
            if (!$stmt->fetch()) {
                break; // Username disponibile
            }
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        // Inserisci utente
        db_query("
            INSERT INTO utenti (username, nome, cognome, email, password, data_nascita, ruolo, 
                               primo_accesso, password_scadenza, attivo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?, 1)
        ", [$username, $nome, $cognome, $email, $passwordHash, $data_nascita, $ruolo, $passwordScadenza]);
        $userId = db_connection()->lastInsertId();
        
        // Associa utente all'azienda se specificato
        if ($azienda_id && $ruolo_azienda) {
            $auth = Auth::getInstance();
            $current_user = $auth->getUser();
            
            db_insert('utenti_aziende', [
                'utente_id' => $userId,
                'azienda_id' => $azienda_id,
                'ruolo_azienda' => $ruolo_azienda,
                'assegnato_da' => $current_user['id'],
                'attivo' => 1
            ]);
        }
        
        // Log attività (asincrono)
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('utente', 'creazione', $userId, "Nuovo utente: $email");
        }
        
        db_connection()->commit();
        
        // Invia email di benvenuto con password
        try {
            // Recupera dati completi dell'utente
            $stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$userId]);
            $utente = $stmt->fetch();
            
            // Invia email con template unificato
            require_once 'backend/utils/NotificationCenter.php';
            $notificationCenter = NotificationCenter::getInstance();
            $notificationCenter->notifyWelcomeUser($utente, $password);
            
        } catch (Exception $e) {
            // Log errore ma non bloccare il processo
            error_log('Errore invio email benvenuto: ' . $e->getMessage());
        }
        
        // Notifica super admin della creazione utente
        try {
            $auth = Auth::getInstance();
            $current_user = $auth->getUser();
            $creator_name = $current_user ? "{$current_user['nome']} {$current_user['cognome']}" : "Sistema";
            
            $notificationManager = NotificationManager::getInstance();
            $notificationManager->notificaSuperAdmin(
                'utente_creato',
                "Nuovo utente creato: {$data['nome']} {$data['cognome']}",
                "
                <h3>Nuovo utente creato</h3>
                <p><strong>Nome:</strong> {$data['nome']} {$data['cognome']}</p>
                <p><strong>Email:</strong> {$data['email']}</p>
                <p><strong>Ruolo:</strong> {$data['ruolo']}</p>
                <p><strong>Creato da:</strong> $creator_name</p>
                "
            );
        } catch (Exception $e) {
            error_log('Errore notifica super admin: ' . $e->getMessage());
        }
        
        $message = "Utente creato con successo!";
        if ($data['password_type'] === 'generate') {
            $message .= " Password: <strong>$password</strong>";
        }
        if ($azienda_id && $ruolo_azienda) {
            $ruoli_nomi = [
                'responsabile_aziendale' => 'Responsabile Aziendale',
                'referente' => 'Referente',
                'ospite' => 'Ospite'
            ];
            $nome_ruolo = $ruoli_nomi[$ruolo_azienda] ?? $ruolo_azienda;
            $message .= " Assegnato come $nome_ruolo all'azienda.";
        }
        
        return ['success' => true, 'message' => $message, 'userId' => $userId];
        
    } catch (Exception $e) {
        db_connection()->rollback();
        error_log('Errore creazione utente: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Errore nella creazione dell\'utente: ' . $e->getMessage()];
    }
}

function resetPassword($userId) {
    $newPassword = generateRandomPassword();
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $passwordScadenza = date('Y-m-d', strtotime('+60 days')); // Cambiato da 90 a 60 giorni
    
    $stmt = db_query("
        UPDATE utenti 
        SET password = ?, primo_accesso = TRUE, password_scadenza = ?,
            last_password_change = NOW()
        WHERE id = ?
    ", [$passwordHash, $passwordScadenza, $userId]);
    
    if ($stmt && $stmt->rowCount() > 0) {
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('utente', 'reset_password', $userId, "Password resettata da super admin");
        }
        
        // Recupera dati utente per invio email
        $stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$userId]);
        $utente = $stmt->fetch();
        
        if ($utente) {
            // Invia email con nuova password
            try {
                require_once 'backend/utils/Mailer.php';
                $mailer = Mailer::getInstance();
                
                if ($mailer->isNotificationEnabled('password_reset')) {
                    $mailer->sendPasswordResetNotification($utente, $newPassword);
                    return ['success' => true, 'message' => "Password resettata e inviata via email a: " . $utente['email']];
                } else {
                    return ['success' => true, 'message' => "Password resettata: <strong>$newPassword</strong><br><small>Nota: L'invio email è disabilitato</small>"];
                }
            } catch (Exception $e) {
                error_log('Errore invio email reset password: ' . $e->getMessage());
                return ['success' => true, 'message' => "Password resettata: <strong>$newPassword</strong><br><small>Errore nell'invio email: " . $e->getMessage() . "</small>"];
            }
        }
        
        return ['success' => true, 'message' => "Password resettata: <strong>$newPassword</strong>"];
    }
    
    return ['success' => false, 'message' => 'Errore durante il reset della password'];
}

function toggleUserStatus($userId, $newStatus) {
    $stmt = db_query("UPDATE utenti SET attivo = ? WHERE id = ?", [$newStatus, $userId]);
    if ($stmt && $stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Stato utente aggiornato!'];
    }
    
    return ['success' => false, 'message' => 'Errore nell\'aggiornamento dello stato'];
}

function checkEmailExists($email) {
    $stmt = db_query("SELECT 1 FROM utenti WHERE email = ? LIMIT 1", [trim($email)]);
    
    return ['exists' => (bool)$stmt->fetch()];
}

function deleteUser($userId) {
    // Get current user info
    $auth = Auth::getInstance();
    $currentUser = $auth->getUser();
    
    // Non permettere l'eliminazione del proprio account
    if ($userId == $currentUser['id']) {
        return ['success' => false, 'message' => 'Non puoi eliminare il tuo stesso account!'];
    }
    
    // Verifica che l'utente da eliminare esista
    $stmt = db_query("SELECT nome, cognome, email, ruolo FROM utenti WHERE id = ?", [$userId]);
    $userToDelete = $stmt->fetch();
    
    if (!$userToDelete) {
        return ['success' => false, 'message' => 'Utente non trovato!'];
    }
    
    // Non permettere MAI l'eliminazione di asamodeo@fortibyte.it
    if ($userToDelete['email'] === 'asamodeo@fortibyte.it') {
        return ['success' => false, 'message' => 'Questo utente è protetto e non può essere eliminato!'];
    }
    
    // Solo asamodeo@fortibyte.it può eliminare altri super admin
    if ($userToDelete['ruolo'] === 'super_admin' && $currentUser['email'] !== 'asamodeo@fortibyte.it') {
        return ['success' => false, 'message' => 'Non hai i permessi per eliminare altri Super Admin!'];
    }
    
    db_connection()->beginTransaction();
    
    try {
        // Prima elimina le associazioni con le aziende
        $stmt = db_query("DELETE FROM utenti_aziende WHERE utente_id = ?", [$userId]);
        
        // Poi elimina eventuali documenti assegnati all'utente
        $stmt = db_query("UPDATE documenti SET assegnato_a = NULL WHERE assegnato_a = ?", [$userId]);
        
        // Infine elimina l'utente
        $stmt = db_query("DELETE FROM utenti WHERE id = ?", [$userId]);
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('utente', 'eliminazione', $userId, 
                "Eliminato utente: {$userToDelete['nome']} {$userToDelete['cognome']} ({$userToDelete['email']})");
        }
        
        db_connection()->commit();
        return ['success' => true, 'message' => 'Utente eliminato con successo!'];
        
    } catch (Exception $e) {
        db_connection()->rollback();
        error_log('Errore eliminazione utente: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'utente: ' . $e->getMessage()];
    }
}

// Funzione generateRandomPassword ora definita in config.php

// Funzione per verificare i limiti aziendali
function checkAziendaLimits($aziendaId) {
    if (!$aziendaId) {
        return ['success' => false, 'message' => 'ID azienda non fornito'];
    }
    
    try {
        // Carica informazioni azienda
        $stmt = db_query("SELECT max_referenti FROM aziende WHERE id = ?", [$aziendaId]);
        $azienda = $stmt->fetch();
        
        if (!$azienda) {
            return ['success' => false, 'message' => 'Azienda non trovata'];
        }
        
        $max_referenti = $azienda['max_referenti'] ?? 5;
        
        // Verifica responsabile aziendale esistente
        $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'responsabile_aziendale' AND attivo = 1", 
                       [$aziendaId]);
        $responsabile_exists = $stmt->fetch()['count'] > 0;
        
        // Verifica numero referenti attuali
        $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'referente' AND attivo = 1", 
                       [$aziendaId]);
        $referenti_count = $stmt->fetch()['count'];
        $referenti_full = $referenti_count >= $max_referenti;
        
        return [
            'success' => true,
            'responsabile_exists' => $responsabile_exists,
            'referenti_full' => $referenti_full,
            'referenti_count' => $referenti_count,
            'max_referenti' => $max_referenti
        ];
        
    } catch (Exception $e) {
        error_log('Errore verifica limiti azienda: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Errore durante la verifica'];
    }
}

// Query ottimizzata con indici e limit
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;
$azienda_filter = $_GET['azienda'] ?? '';

// Recupera lista aziende per filtro
$aziendeStmt = db_query("SELECT id, nome FROM aziende ORDER BY nome");
$aziende = $aziendeStmt->fetchAll();

// Verifica quali colonne esistono nella tabella utenti
$available_columns = [];
try {
    $check_columns = db_query("DESCRIBE utenti");
    while ($column = $check_columns->fetch()) {
        $available_columns[] = $column['Field'];
    }
} catch (Exception $e) {
    // Usa colonne base se la verifica fallisce
    $available_columns = ['id', 'nome', 'cognome', 'email', 'ruolo', 'attivo'];
}

// Costruisci la lista delle colonne disponibili
$user_columns = ['u.id', 'u.nome', 'u.cognome', 'u.email', 'u.ruolo', 'u.attivo'];
$group_columns = ['u.id', 'u.nome', 'u.cognome', 'u.email', 'u.ruolo', 'u.attivo'];

// Aggiungi colonne opzionali se esistono
if (in_array('data_nascita', $available_columns)) {
    $user_columns[] = 'u.data_nascita';
    $group_columns[] = 'u.data_nascita';
}

if (in_array('primo_accesso', $available_columns)) {
    $user_columns[] = 'u.primo_accesso';
    $group_columns[] = 'u.primo_accesso';
}

if (in_array('password_scadenza', $available_columns)) {
    $user_columns[] = 'u.password_scadenza';
    $user_columns[] = 'DATEDIFF(u.password_scadenza, CURDATE()) as giorni_scadenza';
    $group_columns[] = 'u.password_scadenza';
}

// Costruisci query con filtro azienda
$baseQuery = "SELECT COUNT(DISTINCT u.id) as total";
$selectQuery = "SELECT DISTINCT " . implode(', ', $user_columns) . ",
           GROUP_CONCAT(DISTINCT a.nome ORDER BY a.nome SEPARATOR ', ') as aziende";

$fromQuery = " FROM utenti u
               LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id AND ua.attivo = 1
               LEFT JOIN aziende a ON ua.azienda_id = a.id";

$whereQuery = " WHERE 1=1";
$params = [];

if ($azienda_filter) {
    $whereQuery .= " AND ua.azienda_id = ?";
    $params[] = $azienda_filter;
}

$groupQuery = " GROUP BY " . implode(', ', $group_columns);
$orderQuery = " ORDER BY u.id DESC";

// Conta totale utenti con filtro
try {
    $totalStmt = db_query($baseQuery . $fromQuery . $whereQuery, $params);
    $totalUsers = $totalStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
} catch (Exception $e) {
    error_log("Errore nel conteggio utenti: " . $e->getMessage());
    $totalUsers = 0;
    $totalPages = 1;
}

// Recupera utenti con paginazione e filtro - usa LIMIT con parametri posizionali
try {
    $finalParams = $params;
    $finalParams[] = $perPage;
    $finalParams[] = $offset;

    $finalQuery = $selectQuery . $fromQuery . $whereQuery . $groupQuery . $orderQuery . " LIMIT ? OFFSET ?";
    $stmt = db_query($finalQuery, $finalParams);
    $utenti = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Errore nel recupero utenti: " . $e->getMessage());
    // Fallback: query semplificata senza GROUP_CONCAT
    try {
        $simpleQuery = "SELECT u.id, u.nome, u.cognome, u.email, u.ruolo, u.attivo 
                       FROM utenti u 
                       ORDER BY u.id DESC 
                       LIMIT ? OFFSET ?";
        $stmt = db_query($simpleQuery, [$perPage, $offset]);
        $utenti = $stmt->fetchAll();
        
        // Aggiungi aziende vuote per compatibilità
        foreach ($utenti as &$utente) {
            $utente['aziende'] = '';
            $utente['primo_accesso'] = false;
            $utente['giorni_scadenza'] = null;
        }
    } catch (Exception $e2) {
        error_log("Errore anche con query semplificata: " . $e2->getMessage());
        $utenti = [];
    }
}

$pageTitle = 'Gestione Utenti';
include 'components/header.php';
?>

<style>
    /* Variabili CSS Nexio */
    :root {
        --primary-color: #2d5a9f;
        --primary-dark: #0f2847;
        --primary-light: #2a5a9f;
        --border-color: #e8e8e8;
        --text-primary: #2c2c2c;
        --text-secondary: #6b6b6b;
        --bg-primary: #faf8f5;
        --bg-secondary: #ffffff;
        --success-color: #059669;
        --danger-color: #dc2626;
        --warning-color: #d97706;
        --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    body {
        font-family: var(--font-sans);
        color: var(--text-primary);
        background: var(--bg-primary);
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: var(--bg-secondary);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    
    .content-header h1 {
        margin: 0;
        color: var(--text-primary);
        font-size: 1.875rem;
        font-weight: 700;
    }
    
    .content-header h1 small {
        font-size: 1rem;
        color: var(--text-secondary);
        font-weight: 400;
    }
    
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .filter-container {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .quick-search {
        position: relative;
        width: 300px;
    }
    
    .quick-search input {
        padding-right: 2.5rem;
    }
    
    .quick-search .search-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(43, 87, 154, 0.3);
    }
    
    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    
    .btn-secondary:hover {
        background: var(--border-color);
    }
    
    .btn-success {
        background: var(--success-color);
        color: white;
    }
    
    .btn-success:hover {
        background: #047857;
    }
    
    .btn-danger {
        background: var(--danger-color);
        color: white;
    }
    
    .btn-danger:hover {
        background: #b91c1c;
    }
    
    .btn-small {
        padding: 0.5rem 1rem;
        font-size: 14px;
    }
    
    .btn-group {
        display: flex;
        gap: 0.5rem;
    }
    
    /* Table Layout */
    .users-table-container {
        background: var(--bg-secondary);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        overflow: hidden;
        margin-bottom: 30px;
        position: relative;
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    .users-table th {
        background: #2d5a9f;
        color: white;
        padding: 16px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        border-bottom: 2px solid #2d5a9f;
    }
    
    .users-table td {
        padding: 16px 12px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        line-height: 1.4;
    }
    
    .users-table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .users-table tbody tr:hover {
        background: #f8fafc;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .users-table tbody tr:nth-child(even) {
        background: rgba(248, 250, 252, 0.3);
    }
    
    .users-table tbody tr:nth-child(even):hover {
        background: #f8fafc;
    }
    
    .user-info-cell {
        min-width: 200px;
    }
    
    .user-name {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
        line-height: 1.3;
    }
    
    .user-email {
        font-size: 13px;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .user-actions {
        display: flex;
        gap: 6px;
        justify-content: center;
        min-width: 160px;
    }
    
    .user-actions .btn {
        min-width: 32px;
        height: 32px;
        padding: 6px;
        justify-content: center;
        align-items: center;
        font-size: 12px;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border: 1px solid transparent;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .status-attivo {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .status-archiviato {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .status-confermato {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .status-pubblicato {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .status-invitato {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .status-rifiutato {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 1;
    }
    
    .modal-content {
        background: var(--bg-secondary);
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }
    
    .modal.active .modal-content {
        transform: scale(1);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    /* Password requirements styles */
    .password-requirements {
        margin-top: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 4px solid #e2e8f0;
    }
    
    .requirements-list {
        margin: 5px 0 0 0;
        padding-left: 20px;
        list-style: none;
    }
    
    .requirement {
        font-size: 12px;
        margin: 3px 0;
        transition: color 0.3s ease;
    }
    
    .requirement.valid {
        color: #22c55e;
    }
    
    .requirement.invalid {
        color: #ef4444;
    }
    
    .password-options {
        margin-bottom: 15px;
    }
    
    .password-options label {
        display: block;
        margin: 8px 0;
        font-weight: normal;
        cursor: pointer;
    }
    
    .password-options input[type="radio"] {
        margin-right: 8px;
    }
    
    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0.5rem;
        border-radius: 6px;
        transition: background 0.2s ease;
    }
    
    .modal-close:hover {
        background: var(--bg-primary);
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        font-weight: 600;
        font-size: 14px;
    }
    
    .form-control,
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s ease;
        background: var(--bg-secondary);
        font-family: var(--font-sans);
    }
    
    .form-control:focus,
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(43, 87, 154, 0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
    }
    
    .password-options {
        display: flex;
        gap: 2rem;
        margin: 1rem 0;
    }
    
    .password-options label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: normal;
        cursor: pointer;
    }
    
    .required {
        color: var(--danger-color);
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    /* Alert Styles */
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease;
    }
    
    .fade-out {
        animation: fadeOut 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-10px); }
    }
    
    /* Loading Spinner */
    .loader {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
    }
    
    .loader.active {
        display: block;
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid var(--border-color);
        border-top: 4px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }
    
    .pagination a,
    .pagination span {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        text-decoration: none;
        color: var(--text-primary);
        transition: all 0.2s ease;
    }
    
    .pagination a:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .pagination .active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .empty-state h2 {
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .content-header {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        
        .filter-container {
            flex-direction: column;
            width: 100%;
        }
        
        .quick-search {
            width: 100%;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .header-actions {
            flex-direction: column;
            width: 100%;
        }
        
        .user-actions {
            flex-wrap: wrap;
        }
        
        /* Table responsive */
        .users-table-container {
            overflow-x: auto;
        }
        
        .users-table {
            min-width: 800px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px 8px;
            font-size: 12px;
        }
        
        .user-name {
            font-size: 14px;
        }
        
        .user-email {
            font-size: 11px;
        }
        
        .status-badge {
            font-size: 10px;
            padding: 4px 8px;
        }
        
        .user-actions .btn {
            min-width: 28px;
            height: 28px;
            padding: 4px;
            font-size: 11px;
        }
    }
    
    /* Nascondere elementi su mobile */
    @media (max-width: 768px) {
        .hide-mobile {
            display: none;
        }
    }
    
    /* Badge utenti protetti */
    .status-badge[title*="protetto"] {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        color: white;
    }
    
    .status-badge[title*="protetto"] i {
        margin-right: 3px;
    }
    
    /* Overflow control per tabella */
    .users-table-container {
        overflow: hidden;
        position: relative;
    }
</style>

<!-- Loader globale -->
<div class="loader" id="globalLoader">
    <div class="spinner"></div>
</div>

<!-- Contenuto principale -->
<div class="content-header">
    <h1><i class="fas fa-users"></i> Gestione Utenti <small>(<?php echo $totalUsers; ?> totali)</small></h1>
    <div class="header-actions">
        <div class="filter-container">
            <select id="filterAzienda" class="form-control" onchange="filterByAzienda(this.value)">
                <option value="">Tutte le aziende</option>
                <?php foreach ($aziende as $azienda): ?>
                    <option value="<?php echo $azienda['id']; ?>" <?php echo $azienda_filter == $azienda['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($azienda['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div class="quick-search">
                <input type="text" id="searchUsers" class="form-control" placeholder="Cerca utente...">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        <button class="btn btn-primary" onclick="openCreateUserModal()">
            <i class="fas fa-user-plus"></i>
            <span class="hide-mobile">Nuovo Utente</span>
        </button>
    </div>
</div>

<div id="messageContainer"></div>

<?php if (empty($utenti)): ?>
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <h2>Nessun utente trovato</h2>
        <p>Non ci sono utenti che corrispondono ai criteri di ricerca.</p>
        <button class="btn btn-primary" onclick="openCreateUserModal()">
            <i class="fas fa-user-plus"></i> Crea il primo utente
        </button>
    </div>
<?php else: ?>
    <div class="users-table-container" id="usersTableContainer">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Ruolo Sistema</th>
                    <th>Stato</th>
                    <th>Data Nascita</th>
                    <th>Aziende</th>
                    <th>Password</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php foreach ($utenti as $utente): ?>
                    <tr data-user-id="<?php echo $utente['id']; ?>">
                        <td class="user-info-cell">
                            <div class="user-name"><?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($utente['email']); ?></div>
                            <?php if ($utente['email'] === 'asamodeo@fortibyte.it'): ?>
                                <span class="status-badge status-confermato" title="Utente protetto - non eliminabile" style="margin-top: 4px;">
                                    <i class="fas fa-shield-alt"></i> Protetto
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $utente['ruolo'] === 'super_admin' ? 'confermato' : 'pubblicato'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $utente['ruolo'])); ?>
                            </span>
                            <?php if ($utente['email'] === 'asamodeo@fortibyte.it'): ?>
                                <span class="status-badge" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); color: white; margin-left: 5px;" title="Super Amministratore principale">
                                    <i class="fas fa-crown"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $utente['attivo'] ? 'attivo' : 'archiviato'; ?>">
                                <?php echo $utente['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($utente['data_nascita']): ?>
                                <?php echo date('d/m/Y', strtotime($utente['data_nascita'])); ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($utente['aziende'])): ?>
                                <span title="<?php echo htmlspecialchars($utente['aziende']); ?>">
                                    <?php 
                                    $aziende_text = htmlspecialchars($utente['aziende']);
                                    echo strlen($aziende_text) > 30 ? substr($aziende_text, 0, 30) . '...' : $aziende_text; 
                                    ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $primo_accesso = isset($utente['primo_accesso']) ? $utente['primo_accesso'] : false;
                            $giorni_scadenza = isset($utente['giorni_scadenza']) ? $utente['giorni_scadenza'] : null;
                            ?>
                            <?php if ($primo_accesso): ?>
                                <span class="status-badge status-invitato">Primo accesso</span>
                            <?php elseif ($giorni_scadenza !== null && $giorni_scadenza < 0): ?>
                                <span class="status-badge status-rifiutato">Scaduta</span>
                            <?php elseif ($giorni_scadenza !== null && $giorni_scadenza <= 7): ?>
                                <span class="status-badge status-invitato">Scade tra <?php echo $giorni_scadenza; ?>g</span>
                            <?php elseif ($giorni_scadenza !== null): ?>
                                <span style="color: var(--success-color); font-size: 12px;">Valida (<?php echo $giorni_scadenza; ?>g)</span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="user-actions">
                                <button class="btn btn-small btn-secondary" onclick="resetPassword(<?php echo $utente['id']; ?>)" 
                                        title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                
                                <button class="btn btn-small btn-<?php echo $utente['attivo'] ? 'danger' : 'success'; ?>" 
                                        onclick="toggleUserStatus(<?php echo $utente['id']; ?>, <?php echo $utente['attivo'] ? '0' : '1'; ?>)"
                                        title="<?php echo $utente['attivo'] ? 'Disattiva' : 'Attiva'; ?>">
                                    <i class="fas fa-<?php echo $utente['attivo'] ? 'ban' : 'check'; ?>"></i>
                                </button>
                                
                                <a href="modifica-utente.php?id=<?php echo $utente['id']; ?>" class="btn btn-small btn-primary" title="Modifica">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php 
                                // Mostra il pulsante elimina solo se:
                                // 1. Non è il proprio account
                                // 2. Non è asamodeo@fortibyte.it (protetto)
                                // 3. Se è un super admin, solo asamodeo@fortibyte.it può eliminarlo
                                $canDelete = ($utente['id'] != $user['id']) && 
                                            ($utente['email'] !== 'asamodeo@fortibyte.it') &&
                                            ($utente['ruolo'] !== 'super_admin' || $user['email'] === 'asamodeo@fortibyte.it');
                                
                                if ($canDelete): ?>
                                <button class="btn btn-small btn-danger" onclick="deleteUser(<?php echo $utente['id']; ?>, '<?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?>')" 
                                        title="Elimina Utente">
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

<!-- Paginazione -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=1">«</a>
        <a href="?page=<?php echo $page - 1; ?>">‹</a>
    <?php endif; ?>
    
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    
    for ($i = $start; $i <= $end; $i++):
    ?>
        <?php if ($i == $page): ?>
            <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>">›</a>
        <a href="?page=<?php echo $totalPages; ?>">»</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal creazione utente ottimizzato -->
<div class="modal" id="create-user-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Nuovo Utente</h2>
            <button class="modal-close" onclick="closeModal('create-user-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="create-user-form" onsubmit="return createUserAjax(event)">
                <input type="hidden" name="action" value="create_user">
                
                <h3 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.125rem;">Informazioni Personali</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome <span class="required">*</span></label>
                        <input type="text" name="nome" id="nome" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cognome">Cognome <span class="required">*</span></label>
                        <input type="text" name="cognome" id="cognome" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span> <span id="emailCheck" style="font-size: 0.875rem;"></span></label>
                        <input type="email" name="email" id="email" class="form-control" required onblur="checkEmail(this.value)">
                    </div>
                    
                    <div class="form-group">
                        <label for="data_nascita">Data di Nascita <span class="required">*</span></label>
                        <input type="date" name="data_nascita" id="data_nascita" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ruolo">Ruolo Sistema <span class="required">*</span></label>
                    <select name="ruolo" id="ruolo" class="form-control" required>
                        <option value="">-- Seleziona ruolo --</option>
                        <option value="utente">Utente</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                    <small class="form-text">Il ruolo sistema determina i permessi di base dell'utente</small>
                </div>
                
                <h3 style="margin: 2rem 0 1rem 0; color: var(--text-primary); font-size: 1.125rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">Associazione Aziendale</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="azienda_id">Azienda (opzionale)</label>
                        <select name="azienda_id" id="azienda_id" class="form-control" onchange="updateRuoliAziendali()">
                            <option value="">-- Nessuna associazione --</option>
                            <?php foreach ($aziende as $azienda): ?>
                                <option value="<?php echo $azienda['id']; ?>">
                                    <?php echo htmlspecialchars($azienda['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Seleziona un'azienda per associare l'utente direttamente durante la creazione</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="ruolo_azienda">Ruolo Aziendale</label>
                        <select name="ruolo_azienda" id="ruolo_azienda" class="form-control" disabled>
                            <option value="">-- Prima seleziona un'azienda --</option>
                            <option value="responsabile_aziendale">Responsabile Aziendale (max 1 per azienda)</option>
                            <option value="referente">Referente (limitato)</option>
                            <option value="ospite">Ospite (solo visualizzazione)</option>
                        </select>
                        <small class="form-text">
                            <strong>Responsabile:</strong> Accesso completo all'azienda<br>
                            <strong>Referente:</strong> Gestione documenti e operazioni<br>
                            <strong>Ospite:</strong> Solo visualizzazione documenti
                        </small>
                    </div>
                </div>
                
                <div id="ruolo-warning" style="display: none; background: #fef3cd; border: 1px solid #fde68a; border-radius: 8px; padding: 10px; margin: 10px 0; color: #92400e;">
                    <i class="fas fa-exclamation-triangle"></i> <span id="warning-text"></span>
                </div>
                
                <h3 style="margin: 2rem 0 1rem 0; color: var(--text-primary); font-size: 1.125rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">Credenziali di Accesso</h3>
                
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-options">
                        <label>
                            <input type="radio" name="password_type" value="generate" checked onchange="togglePasswordInput()">
                            Genera automaticamente (consigliato)
                        </label>
                        <label>
                            <input type="radio" name="password_type" value="manual" onchange="togglePasswordInput()">
                            Inserisci manualmente
                        </label>
                    </div>
                    <input type="password" name="password" id="manual-password" class="form-control" 
                           style="display: none;" minlength="8" placeholder="Minimo 8 caratteri" 
                           oninput="validatePassword()">
                    <div id="password-requirements" style="display: none;" class="password-requirements">
                        <small class="form-text">Requisiti password:</small>
                        <ul class="requirements-list">
                            <li id="req-length" class="requirement">✗ Almeno 8 caratteri</li>
                            <li id="req-uppercase" class="requirement">✗ Almeno 1 lettera maiuscola</li>
                            <li id="req-special" class="requirement">✗ Almeno 1 carattere speciale (!@#$%^&*(),.?":{}|<>)</li>
                        </ul>
                    </div>
                    <small class="form-text">La password generata automaticamente è complessa e di 8 caratteri</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-user-modal')">Annulla</button>
                    <button type="submit" class="btn btn-primary" id="createUserBtn">
                        <span>Crea Utente</span>
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script ottimizzato con AJAX -->
<script>
// Fix per conflitti con app.js - previeni errori di elementi non esistenti
window.addEventListener('load', function() {
    // Override delle funzioni problematiche di app.js
    if (typeof initMobileMenu === 'function') {
        const originalInitMobileMenu = initMobileMenu;
        window.initMobileMenu = function() {
            const menuBtn = document.querySelector('.mobile-menu-btn');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (!menuBtn || !sidebar || !overlay) {
                return; // Esci se gli elementi non esistono
            }
            
            originalInitMobileMenu();
        };
    }
});

// Aggiungi elementi dummy per evitare errori se mancano
document.addEventListener('DOMContentLoaded', function() {
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.display = 'none';
        document.body.appendChild(overlay);
    }
    
    if (!document.querySelector('.mobile-menu-btn')) {
        const btn = document.createElement('div');
        btn.className = 'mobile-menu-btn';
        btn.style.display = 'none';
        document.body.appendChild(btn);
    }
});

// Gestione errori JavaScript globale per debug
window.addEventListener('error', function(e) {
    console.error('Errore JavaScript:', e.error);
    // Non bloccare l'esecuzione per errori di app.js
    return false;
});
</script>
<script>
// Cache DOM elements
const messageContainer = document.getElementById('messageContainer');
const globalLoader = document.getElementById('globalLoader');
const usersTableContainer = document.getElementById('usersTableContainer');
const usersTableBody = document.getElementById('usersTableBody');
const searchInput = document.getElementById('searchUsers');

// Debounce function per search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Funzione per mostrare messaggi
function showMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fade-in`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    messageContainer.innerHTML = '';
    messageContainer.appendChild(alertDiv);
    
    // Auto-hide dopo 5 secondi
    setTimeout(() => {
        alertDiv.classList.add('fade-out');
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

// Apertura modal ottimizzata (sovrascrivi quella di app.js se necessario)
if (typeof openModal !== 'undefined') {
    // Salva la funzione originale per altri usi
    window.originalOpenModal = openModal;
}

function openCreateUserModal() {
    const modal = document.getElementById('create-user-modal');
    modal.classList.add('active');
    document.getElementById('create-user-form').reset();
    document.getElementById('emailCheck').innerHTML = '';
    
    // Reset ruolo aziendale
    const ruoloAziendaSelect = document.getElementById('ruolo_azienda');
    if (ruoloAziendaSelect) {
        ruoloAziendaSelect.disabled = true;
        ruoloAziendaSelect.selectedIndex = 0;
    }
    
    // Nascondi warning
    const warning = document.getElementById('ruolo-warning');
    if (warning) {
        warning.style.display = 'none';
    }
}

// Chiusura modal (sovrascrivi quella di app.js se necessario)
if (typeof closeModal !== 'undefined') {
    // Salva la funzione originale per altri usi
    window.originalCloseModal = closeModal;
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Toggle password input
function togglePasswordInput() {
    const passwordInput = document.getElementById('manual-password');
    const passwordRequirements = document.getElementById('password-requirements');
    const passwordType = document.querySelector('input[name="password_type"]:checked').value;
    
    if (passwordType === 'manual') {
        passwordInput.style.display = 'block';
        passwordRequirements.style.display = 'block';
        passwordInput.required = true;
        validatePassword(); // Valida subito se c'è già testo
    } else {
        passwordInput.style.display = 'none';
        passwordRequirements.style.display = 'none';
        passwordInput.required = false;
        passwordInput.value = '';
    }
}

// Validate password requirements in real-time
function validatePassword() {
    const passwordInput = document.getElementById('manual-password');
    const password = passwordInput.value;
    
    // Get requirement elements
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqSpecial = document.getElementById('req-special');
    
    // Check length (8+ characters)
    if (password.length >= 8) {
        reqLength.textContent = '✓ Almeno 8 caratteri';
        reqLength.className = 'requirement valid';
    } else {
        reqLength.textContent = '✗ Almeno 8 caratteri';
        reqLength.className = 'requirement invalid';
    }
    
    // Check uppercase letter
    if (/[A-Z]/.test(password)) {
        reqUppercase.textContent = '✓ Almeno 1 lettera maiuscola';
        reqUppercase.className = 'requirement valid';
    } else {
        reqUppercase.textContent = '✗ Almeno 1 lettera maiuscola';
        reqUppercase.className = 'requirement invalid';
    }
    
    // Check special character
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        reqSpecial.textContent = '✓ Almeno 1 carattere speciale';
        reqSpecial.className = 'requirement valid';
    } else {
        reqSpecial.textContent = '✗ Almeno 1 carattere speciale (!@#$%^&*(),.?":{}|<>)';
        reqSpecial.className = 'requirement invalid';
    }
    
    // Update border color based on validation
    const allValid = password.length >= 8 && /[A-Z]/.test(password) && /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    if (password.length > 0) {
        passwordInput.style.borderColor = allValid ? '#22c55e' : '#ef4444';
    } else {
        passwordInput.style.borderColor = '';
    }
}

// Check email esistente (con debounce)
const checkEmail = debounce(async (email) => {
    if (!email) return;
    
    const emailCheck = document.getElementById('emailCheck');
    emailCheck.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const response = await fetch('gestione-utenti.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=check_email&email=${encodeURIComponent(email)}`
        });
        
        const data = await response.json();
        
        if (data.exists) {
            emailCheck.innerHTML = '<span style="color: var(--danger-color);">Email già esistente!</span>';
        } else {
            emailCheck.innerHTML = '<span style="color: var(--success-color);">Email disponibile</span>';
        }
    } catch (error) {
        emailCheck.innerHTML = '';
    }
}, 500);

// Creazione utente AJAX
async function createUserAjax(e) {
    e.preventDefault();
    
    const form = e.target;
    const btn = document.getElementById('createUserBtn');
    const spinner = btn.querySelector('.fa-spinner');
    const btnText = btn.querySelector('span');
    
    // Validazione password prima dell'invio
    const passwordType = form.querySelector('input[name="password_type"]:checked').value;
    if (passwordType === 'manual') {
        const password = form.querySelector('input[name="password"]').value;
        if (!password) {
            showMessage('Inserisci una password', 'error');
            return;
        }
        if (password.length < 8) {
            showMessage('La password deve essere di almeno 8 caratteri', 'error');
            return;
        }
        if (!/[A-Z]/.test(password)) {
            showMessage('La password deve contenere almeno una lettera maiuscola', 'error');
            return;
        }
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            showMessage('La password deve contenere almeno un carattere speciale', 'error');
            return;
        }
    }
    
    // Disabilita form
    btn.disabled = true;
    spinner.style.display = 'inline-block';
    btnText.textContent = 'Creazione in corso...';
    
    try {
        const formData = new FormData(form);
        const response = await fetch('gestione-utenti.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            closeModal('create-user-modal');
            
            // Ricarica tabella dopo 1 secondo
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Errore durante la creazione dell\'utente', 'error');
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
        btnText.textContent = 'Crea Utente';
    }
}

// Reset password AJAX
async function resetPassword(userId) {
    if (!confirm('Resettare la password per questo utente?')) return;
    
    globalLoader.classList.add('active');
    
    try {
        const response = await fetch('gestione-utenti.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=reset_password&user_id=${userId}`
        });
        
        const data = await response.json();
        showMessage(data.message, data.success ? 'success' : 'error');
    } catch (error) {
        showMessage('Errore durante il reset della password', 'error');
    } finally {
        globalLoader.classList.remove('active');
    }
}

// Toggle stato utente AJAX
async function toggleUserStatus(userId, newStatus) {
    globalLoader.classList.add('active');
    
    try {
        const response = await fetch('gestione-utenti.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=toggle_status&user_id=${userId}&new_status=${newStatus}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            // Aggiorna solo la riga interessata
            updateUserRow(userId);
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Errore durante l\'aggiornamento dello stato', 'error');
    } finally {
        globalLoader.classList.remove('active');
    }
}

// Elimina utente AJAX
async function deleteUser(userId, userName) {
    if (!confirm(`Sei sicuro di voler eliminare l'utente "${userName}"?\n\nQuesta azione è irreversibile!`)) {
        return;
    }
    
    globalLoader.classList.add('active');
    
    try {
        const response = await fetch('gestione-utenti.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=delete_user&user_id=${userId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            // Rimuovi la riga dalla tabella con animazione
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
            if (row) {
                // Aggiungi classe per animazione
                row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    row.remove();
                    // Controlla se ci sono ancora righe nella tabella
                    const tbody = document.querySelector('.users-table tbody');
                    if (tbody && tbody.children.length === 0) {
                        // Ricarica la pagina dopo un breve delay
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    }
                }, 300);
            } else {
                // Se la riga non esiste, ricarica comunque dopo un delay
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage('Errore durante l\'eliminazione dell\'utente', 'error');
    } finally {
        globalLoader.classList.remove('active');
    }
}

// Aggiorna singola riga utente
async function updateUserRow(userId) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (row) {
        // Qui potresti fare una chiamata AJAX per ottenere i dati aggiornati
        // Per ora facciamo un reload della pagina dopo 1 secondo
        setTimeout(() => location.reload(), 1000);
    }
}

// Ricerca live utenti nella tabella
if (searchInput) {
    searchInput.addEventListener('input', debounce(function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        if (usersTableBody) {
            const rows = usersTableBody.querySelectorAll('tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }
    }, 300));
}

// Gestione ESC per chiudere modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            activeModal.classList.remove('active');
        }
    }
});

// Preload per migliorare performance
document.addEventListener('DOMContentLoaded', () => {
    // Preconnect to external resources
    const preconnect = document.createElement('link');
    preconnect.rel = 'preconnect';
    preconnect.href = 'https://cdnjs.cloudflare.com';
    document.head.appendChild(preconnect);
});

// Funzione per filtrare per azienda
function filterByAzienda(aziendaId) {
    const currentUrl = new URL(window.location.href);
    if (aziendaId) {
        currentUrl.searchParams.set('azienda', aziendaId);
    } else {
        currentUrl.searchParams.delete('azienda');
    }
    currentUrl.searchParams.set('page', '1'); // Reset alla prima pagina
    window.location.href = currentUrl.toString();
}

// Funzione per aggiornare i ruoli aziendali basati sull'azienda selezionata
async function updateRuoliAziendali() {
    const aziendaSelect = document.getElementById('azienda_id');
    const ruoloSelect = document.getElementById('ruolo_azienda');
    const warning = document.getElementById('ruolo-warning');
    const warningText = document.getElementById('warning-text');
    
    if (!aziendaSelect || !ruoloSelect) return;
    
    const aziendaId = aziendaSelect.value;
    
    if (!aziendaId) {
        ruoloSelect.disabled = true;
        ruoloSelect.selectedIndex = 0;
        warning.style.display = 'none';
        return;
    }
    
    ruoloSelect.disabled = false;
    warning.style.display = 'none';
    
    // Reset opzioni
    const options = ruoloSelect.options;
    for (let i = 1; i < options.length; i++) {
        options[i].disabled = false;
        options[i].style.color = '';
        options[i].text = options[i].text.replace(' (Non disponibile)', '').replace(' (Limite raggiunto)', '');
    }
    
    try {
        // Verifica limiti aziendali via AJAX
        const response = await fetch('gestione-utenti.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=check_azienda_limits&azienda_id=${aziendaId}`
        });
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.responsabile_exists) {
                const responsabileOption = ruoloSelect.querySelector('option[value="responsabile_aziendale"]');
                if (responsabileOption) {
                    responsabileOption.disabled = true;
                    responsabileOption.style.color = '#9ca3af';
                    responsabileOption.text += ' (Non disponibile)';
                }
            }
            
            if (data.referenti_full) {
                const referenteOption = ruoloSelect.querySelector('option[value="referente"]');
                if (referenteOption) {
                    referenteOption.disabled = true;
                    referenteOption.style.color = '#9ca3af';
                    referenteOption.text += ' (Limite raggiunto)';
                }
            }
        }
    } catch (error) {
        console.error('Errore verifica limiti azienda:', error);
    }
}

// Mostra avviso quando selezionato un ruolo
document.addEventListener('DOMContentLoaded', function() {
    const ruoloSelect = document.getElementById('ruolo_azienda');
    if (ruoloSelect) {
        ruoloSelect.addEventListener('change', function() {
            const warning = document.getElementById('ruolo-warning');
            const warningText = document.getElementById('warning-text');
            
            if (!warning || !warningText) return;
            
            const selectedValue = this.value;
            
            if (selectedValue === 'responsabile_aziendale') {
                warningText.textContent = 'Il Responsabile Aziendale avrà accesso completo all\'azienda e potrà gestire tutti i documenti e referenti.';
                warning.style.display = 'block';
            } else if (selectedValue === 'referente') {
                warningText.textContent = 'Il Referente potrà gestire documenti e operazioni aziendali ma non altri utenti.';
                warning.style.display = 'block';
            } else if (selectedValue === 'ospite') {
                warningText.textContent = 'L\'Ospite avrà accesso solo in visualizzazione ai documenti dell\'azienda.';
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        });
    }
});
</script>

<style>
/* Stili aggiuntivi per performance */
.user-info-cell {
    line-height: 1.4;
}

.text-muted {
    color: var(--text-secondary);
}

.d-block {
    display: block;
}

.password-options {
    display: flex;
    gap: 2rem;
    margin: 1rem 0;
}

.password-options label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: normal;
    cursor: pointer;
}

.btn-group {
    display: flex;
    gap: 0.5rem;
}

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

.fade-out {
    animation: fadeOut 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeOut {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(-10px); }
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border-color);
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

@media (max-width: 768px) {
    .password-options {
        flex-direction: column;
        gap: 1rem;
    }
    
    .header-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .quick-search {
        width: 100%;
    }
}
</style>

<?php include 'components/footer.php'; ?> 