<?php
require_once 'backend/config/config.php';

// Se è una richiesta AJAX, gestisci gli errori in modo diverso
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';

if ($isAjax) {
    // Error handler per richieste AJAX
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore del server',
            'error' => $errstr
        ]);
        exit;
    });
    
    // Exception handler per richieste AJAX
    set_exception_handler(function($exception) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Errore del server',
            'error' => $exception->getMessage()
        ]);
        exit;
    });
}

// Verifica connessione database prima di procedere
try {
    $test_connection = db_connection();
    $test_connection->query("SELECT 1");
} catch (Exception $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database non disponibile']);
        exit;
    }
    // Se siamo qui, significa che il database non è disponibile
    header('Location: ' . APP_PATH . '/check-database.php');
    exit;
}

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo i super admin possono accedere
if (!$auth->isSuperAdmin()) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato']);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

// Database instance handled by functions
$user = $auth->getUser();

/**
 * Auto-associa un nuovo utente ad un'azienda basandosi sul dominio email
 */
function autoAssociateNewUser($userId, $email, $ruolo) {
    try {
        // Non applicare per super admin o utenti speciali
        if (in_array($ruolo, ['super_admin', 'utente_speciale'])) {
            return;
        }
        
        // Estrai il dominio dall'email
        if (!$email || strpos($email, '@') === false) {
            return;
        }
        
        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        
        // Mappatura domini -> aziende
        $domainMapping = [
            'romolohospital.com' => 'Romolo Hospital',
            // Aggiungi altre mappature qui se necessario
        ];
        
        if (!isset($domainMapping[$domain])) {
            return;
        }
        
        $aziendaNome = $domainMapping[$domain];
        
        // Trova l'azienda
        $stmt = db_query("SELECT id FROM aziende WHERE nome = ? AND stato = 'attiva'", [$aziendaNome]);
        $azienda = $stmt->fetch();
        
        if (!$azienda) {
            return;
        }
        
        // Crea l'associazione
        $ruolo_azienda = 'referente'; // Ruolo default
        if (strpos($email, 'admin') !== false) {
            $ruolo_azienda = 'responsabile_aziendale';
        }
        
        db_insert('utenti_aziende', [
            'utente_id' => $userId,
            'azienda_id' => $azienda['id'],
            'ruolo_azienda' => $ruolo_azienda,
            'assegnato_da' => 1, // Sistema
            'attivo' => 1
        ]);
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('sistema', 'auto_associazione', $userId, 
                "Auto-associato nuovo utente {$email} a {$aziendaNome}");
        }
        
    } catch (Exception $e) {
        // Log errore ma non bloccare la creazione utente
        error_log("Errore auto-associazione nuovo utente: " . $e->getMessage());
    }
}

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
    
    if (!in_array($ruolo, ['utente', 'super_admin', 'utente_speciale'])) {
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
        // Verifica email - permetti riutilizzo se utente non attivo
        $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND attivo = 1 LIMIT 1", [$email]);
        
        if ($stmt->fetch()) {
            db_connection()->rollback();
            return ['success' => false, 'message' => 'Email già utilizzata da un utente attivo!'];
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
                // Verifica numero massimo di referenti
                $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'referente' AND attivo = 1", 
                               [$azienda_id]);
                $existing_referenti = $stmt->fetch()['count'];
                
                if ($existing_referenti >= $max_referenti) {
                    db_connection()->rollback();
                    return ['success' => false, 'message' => "Numero massimo di referenti raggiunto per questa azienda ({$max_referenti})."];
                }
            }
        }
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Controlla se esiste un utente inattivo con la stessa email
        $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND attivo = 0", [$email]);
        $inactiveUser = $stmt->fetch();
        
        if ($inactiveUser) {
            // Riattiva l'utente esistente
            db_update('utenti', [
                'nome' => $nome,
                'cognome' => $cognome,
                'data_nascita' => $data_nascita,
                'password' => $passwordHash,
                'ruolo' => $ruolo,
                'attivo' => 1,
                'last_password_change' => date('Y-m-d H:i:s'),
                'primo_accesso' => 1
            ], 'id = ?', [$inactiveUser['id']]);
            
            $userId = $inactiveUser['id'];
            
            // Pulisci vecchie associazioni aziendali
            db_query("DELETE FROM utenti_aziende WHERE utente_id = ?", [$userId]);
        } else {
            // Crea nuovo utente
            $insertId = db_insert('utenti', [
                'nome' => $nome,
                'cognome' => $cognome,
                'email' => $email,
                'data_nascita' => $data_nascita,
                'password' => $passwordHash,
                'ruolo' => $ruolo,
                'attivo' => 1,
                'data_registrazione' => date('Y-m-d H:i:s'),
                'last_password_change' => date('Y-m-d H:i:s'),
                'primo_accesso' => 1
            ]);
            
            if (!$insertId) {
                db_connection()->rollback();
                return ['success' => false, 'message' => 'Errore durante la creazione dell\'utente'];
            }
            
            $userId = $insertId;
        }
        
        // Crea associazione aziendale se specificata
        if ($azienda_id && $ruolo_azienda) {
            db_insert('utenti_aziende', [
                'utente_id' => $userId,
                'azienda_id' => $azienda_id,
                'ruolo_azienda' => $ruolo_azienda,
                'assegnato_da' => $user['id'] ?? 1,
                'attivo' => 1
            ]);
        } else {
            // Auto-associazione basata su dominio email
            autoAssociateNewUser($userId, $email, $ruolo);
        }
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('utente', 'create', $userId, 
                "Creato nuovo utente: {$nome} {$cognome} ({$email}) con ruolo {$ruolo}");
        }
        
        db_connection()->commit();
        
        // Invia email se richiesto
        if (isset($data['send_email']) && $data['send_email'] === 'on') {
            $emailSent = sendWelcomeEmail($email, $nome, $password);
            $emailMessage = $emailSent ? ' Email di benvenuto inviata.' : ' (Email non inviata)';
        } else {
            $emailMessage = '';
        }
        
        return [
            'success' => true, 
            'message' => "Utente creato con successo!{$emailMessage}",
            'password' => $password,
            'userId' => $userId
        ];
        
    } catch (Exception $e) {
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        error_log("Errore creazione utente: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore durante la creazione dell\'utente: ' . $e->getMessage()];
    }
}

function sendWelcomeEmail($email, $nome, $password) {
    // Usa la configurazione corretta per l'URL base
    $baseUrl = 'http://localhost/piattaforma-collaborativa';
    
    try {
        $mailer = Mailer::getInstance();
        
        $subject = "Benvenuto su Nexio - Le tue credenziali di accesso";
        
        // Costruisci il corpo dell'email con link diretto (senza tracking)
        // Importante: non usare tag <a> per evitare il tracking di Brevo
        $body = "
        <div >
            <h2 >Benvenuto su Nexio, {$nome}!</h2>
            
            <p>Il tuo account è stato creato con successo. Di seguito trovi le tue credenziali di accesso:</p>
            
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Password temporanea:</strong> {$password}</p>
            </div>
            
            <p ><strong>Importante:</strong> Al primo accesso ti verrà richiesto di cambiare la password.</p>
            
            <p>Per accedere alla piattaforma, copia e incolla questo link nel tuo browser:</p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; word-break: break-all;'>
                <code >{$baseUrl}/login.php</code>
            </div>
            
            <p style='margin-top: 20px;'>Oppure clicca sul pulsante sottostante:</p>
            <p><a href='{$baseUrl}/login.php' style='background: #2d5a9f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Accedi a Nexio</a></p>
            
            <p >
                Questo messaggio è stato inviato automaticamente. Per assistenza, contatta l'amministratore del sistema.
            </p>
        </div>
        ";
        
        return $mailer->send($email, $subject, $body);
        
    } catch (Exception $e) {
        error_log("Errore invio email benvenuto: " . $e->getMessage());
        return false;
    }
}

function resetPassword($userId) {
    global $user;
    
    try {
        // Verifica che l'utente esista
        $stmt = db_query("SELECT email, nome FROM utenti WHERE id = ?", [$userId]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            return ['success' => false, 'message' => 'Utente non trovato'];
        }
        
        // Genera nuova password
        $newPassword = generateRandomPassword(12);
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Aggiorna password
        db_update('utenti', [
            'password' => $passwordHash,
            'last_password_change' => date('Y-m-d H:i:s'),
            'primo_accesso' => 1
        ], 'id = ?', [$userId]);
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('utente', 'reset_password', $userId, 
                "Reset password per utente ID: {$userId}");
        }
        
        // Invia email con nuova password
        $emailSent = false;
        try {
            $mailer = Mailer::getInstance();
            $subject = "Reset Password - Nexio";
            $body = "
            <div style='font-family: Arial, sans-serif;'>
                <h2>Reset Password</h2>
                <p>Ciao {$targetUser['nome']},</p>
                <p>La tua password è stata reimpostata. La nuova password temporanea è:</p>
                <p style='background: #f5f5f5; padding: 10px; font-family: monospace;'><strong>{$newPassword}</strong></p>
                <p>Ti verrà richiesto di cambiarla al prossimo accesso.</p>
            </div>
            ";
            
            $emailSent = $mailer->send($targetUser['email'], $subject, $body);
        } catch (Exception $e) {
            error_log("Errore invio email reset password: " . $e->getMessage());
        }
        
        return [
            'success' => true, 
            'message' => 'Password reimpostata con successo. ' . ($emailSent ? 'Email inviata all\'utente.' : 'Errore invio email.'),
            'password' => $newPassword
        ];
        
    } catch (Exception $e) {
        error_log("Errore reset password: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore durante il reset della password'];
    }
}

function toggleUserStatus($userId, $newStatus) {
    global $user;
    
    try {
        // Protezione utente principale
        $stmt = db_query("SELECT email FROM utenti WHERE id = ?", [$userId]);
        $targetUser = $stmt->fetch();
        
        if ($targetUser && $targetUser['email'] === 'asamodeo@fortibyte.it') {
            return ['success' => false, 'message' => 'Non è possibile modificare lo stato di questo utente'];
        }
        
        $newStatus = intval($newStatus);
        
        // Aggiorna stato
        db_update('utenti', ['attivo' => $newStatus], 'id = ?', [$userId]);
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            $action = $newStatus ? 'activate' : 'deactivate';
            ActivityLogger::getInstance()->log('utente', $action, $userId, 
                "Cambiato stato utente ID: {$userId} a " . ($newStatus ? 'attivo' : 'inattivo'));
        }
        
        return [
            'success' => true, 
            'message' => 'Stato utente aggiornato con successo'
        ];
        
    } catch (Exception $e) {
        error_log("Errore toggle status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore durante l\'aggiornamento dello stato'];
    }
}

function checkEmailExists($email) {
    try {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['exists' => false];
        }
        
        $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND attivo = 1", [$email]);
        
        return ['exists' => $stmt->fetch() ? true : false];
        
    } catch (Exception $e) {
        return ['exists' => false];
    }
}

function deleteUser($userId) {
    try {
        // Inizia transazione per garantire atomicità
        db_connection()->beginTransaction();
        
        // Protezione utente principale
        $stmt = db_query("SELECT email FROM utenti WHERE id = ?", [$userId]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            db_connection()->rollback();
            return ['success' => false, 'message' => 'Utente non trovato'];
        }
        
        if ($targetUser['email'] === 'asamodeo@fortibyte.it') {
            db_connection()->rollback();
            return ['success' => false, 'message' => 'Non è possibile eliminare questo utente'];
        }
        
        // Non eliminiamo fisicamente, ma disattiviamo (soft delete)
        $updateResult = db_update('utenti', ['attivo' => 0], 'id = ?', [$userId]);
        
        if ($updateResult === false) {
            db_connection()->rollback();
            return ['success' => false, 'message' => 'Errore durante la disattivazione dell\'utente'];
        }
        
        // Disattiva anche le associazioni aziendali
        db_query("UPDATE utenti_aziende SET attivo = 0 WHERE utente_id = ?", [$userId]);
        
        // Commit della transazione
        db_connection()->commit();
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            try {
                $auth = Auth::getInstance();
                $currentUser = $auth->getUser();
                ActivityLogger::getInstance()->log('utente', 'delete', $userId, 
                    "Eliminato (disattivato) utente ID: {$userId} da utente ID: " . ($currentUser['id'] ?? 'sistema'));
            } catch (Exception $logEx) {
                // Non bloccare l'operazione se il log fallisce
                error_log("Errore logging eliminazione utente: " . $logEx->getMessage());
            }
        }
        
        return [
            'success' => true, 
            'message' => 'Utente eliminato con successo'
        ];
        
    } catch (Exception $e) {
        // Rollback in caso di errore
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        error_log("Errore eliminazione utente: " . $e->getMessage());
        return ['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'utente: ' . $e->getMessage()];
    }
}

function checkAziendaLimits($aziendaId) {
    try {
        if (!$aziendaId) {
            return ['success' => true, 'hasResponsabile' => false, 'referentiCount' => 0, 'maxReferenti' => 5];
        }
        
        // Verifica responsabile aziendale
        $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'responsabile_aziendale' AND attivo = 1", 
                       [$aziendaId]);
        $hasResponsabile = $stmt->fetch()['count'] > 0;
        
        // Conta referenti
        $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = ? AND ruolo_azienda = 'referente' AND attivo = 1", 
                       [$aziendaId]);
        $referentiCount = $stmt->fetch()['count'];
        
        // Ottieni limite massimo
        $stmt = db_query("SELECT max_referenti FROM aziende WHERE id = ?", [$aziendaId]);
        $azienda = $stmt->fetch();
        $maxReferenti = $azienda['max_referenti'] ?? 5;
        
        return [
            'success' => true,
            'hasResponsabile' => $hasResponsabile,
            'referentiCount' => $referentiCount,
            'maxReferenti' => $maxReferenti
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Errore verifica limiti azienda'];
    }
}

// Recupera dati per la pagina
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; // Ridotto per performance
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$azienda_filter = isset($_GET['azienda']) && $_GET['azienda'] !== '' ? intval($_GET['azienda']) : null;

// Inizializza variabili per evitare undefined
$utenti = [];
$totalUsers = 0;
$aziende = [];

// Query ottimizzata con indici
try {
    // Count query semplificata
    $countQuery = "SELECT COUNT(DISTINCT u.id) as total FROM utenti u";
    $whereConditions = ["u.attivo = 1"]; // Filtra solo utenti attivi
    $params = [];
    
    if ($search) {
        $searchPattern = "%{$search}%";
        $whereConditions[] = "(u.nome LIKE ? OR u.cognome LIKE ? OR u.email LIKE ?)";
        $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern]);
    }
    
    if ($azienda_filter !== null) {
        $countQuery .= " JOIN utenti_aziende ua ON u.id = ua.utente_id";
        $whereConditions[] = "ua.azienda_id = ? AND ua.attivo = 1";
        $params[] = $azienda_filter;
    }
    
    if (!empty($whereConditions)) {
        $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $stmt = db_query($countQuery, $params);
    $totalUsers = $stmt->fetch()['total'];
    
    // Main query con JOIN ottimizzati
    $mainQuery = "
        SELECT DISTINCT u.*, 
               GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' (', ua.ruolo_azienda, ')') SEPARATOR ', ') as aziende,
               CASE 
                   WHEN u.primo_accesso = 1 THEN 1
                   ELSE 0
               END as primo_accesso,
               DATEDIFF(DATE_ADD(COALESCE(u.last_password_change, u.data_registrazione), INTERVAL 60 DAY), CURDATE()) as giorni_scadenza
        FROM utenti u
        LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id AND ua.attivo = 1
        LEFT JOIN aziende a ON ua.azienda_id = a.id
    ";
    
    $whereConditions = ["u.attivo = 1"]; // Filtra solo utenti attivi
    $params = [];
    
    if ($search) {
        $searchPattern = "%{$search}%";
        $whereConditions[] = "(u.nome LIKE ? OR u.cognome LIKE ? OR u.email LIKE ?)";
        $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern]);
    }
    
    if ($azienda_filter !== null) {
        $whereConditions[] = "ua.azienda_id = ?";
        $params[] = $azienda_filter;
    }
    
    if (!empty($whereConditions)) {
        $mainQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $mainQuery .= " GROUP BY u.id ORDER BY u.data_registrazione DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = db_query($mainQuery, $params);
    $utenti = $stmt->fetchAll();
    
    // Carica lista aziende per filtro
    $stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
    $aziende = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Errore caricamento utenti: " . $e->getMessage());
    
    // Fallback con query più semplice
    try {
        $stmt = db_query("SELECT * FROM utenti WHERE attivo = 1 ORDER BY data_registrazione DESC LIMIT ? OFFSET ?", [$limit, $offset]);
        $utenti = $stmt->fetchAll();
        $totalUsers = db_query("SELECT COUNT(*) as total FROM utenti WHERE attivo = 1")->fetch()['total'];
        $aziende = [];
    } catch (Exception $e2) {
        error_log("Errore anche con query semplificata: " . $e2->getMessage());
        $utenti = [];
        $totalUsers = 0;
        $aziende = [];
    }
}

$pageTitle = 'Gestione Utenti';
$bodyClass = 'gestione-utenti user-management';
include 'components/header.php';
require_once 'components/page-header.php';
?>

<!-- Clean Dashboard Styles -->
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/dashboard-clean.css">

<style>
    /* Modal positioning fix */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        /* Flexbox per centrare il contenuto */
        align-items: center;
        justify-content: center;
    }
    
    /* Quando il modal è visibile */
    .modal[style*="display: block"] {
        display: flex !important;
    }
    
    .modal-content {
        background-color: #fff;
        margin: auto;
        padding: 0;
        border: none;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    /* Pulsante chiudi */
    .close {
        color: #6b7280;
        font-size: 28px;
        font-weight: normal;
        line-height: 1;
        cursor: pointer;
        transition: color 0.2s;
        background: none;
        border: none;
        padding: 0;
        margin: 0;
    }
    
    .close:hover,
    .close:focus {
        color: #111827;
        text-decoration: none;
    }
    
    /* Additional styles specific to user management */
    
    /* Keep only custom styles not in dashboard-clean.css */
    .btn-small {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    /* User info cell styles */
    .user-info-cell {
        min-width: 250px;
    }
    
    .user-name {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
        margin-bottom: 2px;
    }
    
    .user-email {
        color: #6b7280;
        font-size: 12px;
    }
    
    /* User actions */
    .user-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    /* Status badges - clean design without gradients */
    .status-attivo {
        background: #d1fae5;
        color: #047857;
    }
    
    .status-archiviato {
        background: #fee2e2;
        color: #b91c1c;
    }
    
    .status-confermato {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-pubblicato {
        background: #e9d5ff;
        color: #6b21a8;
    }
    
    .status-invitato {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-rifiutato {
        background: #fee2e2;
        color: #b91c1c;
    }
    
    .status-info {
        background: #dbeafe;
        color: #1e40af;
    }
    
    /* Badge utenti protetti */
    .status-badge[title*="protetto"] {
        background: #f3f4f6;
        color: #4b5563;
        border: 1px solid #e5e7eb;
    }
    
    .status-badge[title*="protetto"] i {
        margin-right: 3px;
    }
    
    /* Form styles for modals */
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .required {
        color: #ef4444;
    }
    
    /* Modal overrides for clean style */
    .modal-content {
        border: none;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .modal-header {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        border-radius: 8px 8px 0 0;
        padding: 1.25rem 1.5rem;
    }
    
    .modal-header h3 {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        border-radius: 0 0 8px 8px;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    /* Loader styles */
    .loader {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    
    .loader.active {
        display: flex;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 3px solid #f3f4f6;
        border-top: 3px solid #2d5a9f;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Password requirements */
    .password-requirements {
        margin-top: 10px;
        background: #f9fafb;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }
    
    .requirements-list {
        margin: 5px 0 0 0;
        padding-left: 20px;
        font-size: 13px;
    }
    
    .requirement {
        color: #6b7280;
        margin-bottom: 3px;
    }
    
    .requirement.valid {
        color: #10b981;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .hide-mobile {
            display: none;
        }
        
        .action-bar {
            flex-direction: column;
            gap: 10px;
        }
        
        .search-box {
            width: 100%;
        }
        
        .user-actions {
            flex-wrap: wrap;
        }
        
        .table-clean {
            font-size: 12px;
        }
        
        .table-clean th,
        .table-clean td {
            padding: 8px;
        }
        
        /* Modal responsive */
        .modal-content {
            margin: 10px;
            width: calc(100% - 20px);
            max-width: calc(100% - 20px);
            max-height: calc(100vh - 20px);
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Loader globale -->
<div class="loader" id="globalLoader">
    <div class="spinner"></div>
</div>

<!-- Contenuto principale -->
<div class="page-header">
    <h1><i class="fas fa-users"></i> Gestione Utenti</h1>
    <div class="page-subtitle">Gestisci gli utenti del sistema • <?php echo $totalUsers; ?> utenti totali</div>
</div>

<div class="content-card">
    <div class="panel-header">
        <h2><i class="fas fa-filter"></i> Filtri e Azioni</h2>
    </div>
    <div class="action-bar">
        <select id="filterAzienda" class="form-control" onchange="filterByAzienda(this.value)" >
            <option value="">Tutte le aziende</option>
            <?php foreach ($aziende as $azienda): ?>
                <option value="<?php echo $azienda['id']; ?>" <?php echo $azienda_filter !== null && $azienda_filter == $azienda['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($azienda['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div class="search-box">
            <input type="text" id="searchUsers" class="form-control" placeholder="Cerca utente..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-secondary" type="button" onclick="searchUsers()">
                <i class="fas fa-search"></i>
            </button>
        </div>
        
        <button class="btn btn-primary" onclick="openCreateUserModal()">
            <i class="fas fa-user-plus"></i>
            <span class="hide-mobile">Nuovo Utente</span>
        </button>
    </div>
</div>

<div id="messageContainer"></div>

<?php if (empty($utenti)): ?>
    <div class="content-card">
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>Nessun utente trovato</h3>
            <p>Non ci sono utenti che corrispondono ai criteri di ricerca.</p>
            <button class="btn btn-primary" onclick="openCreateUserModal()">
                <i class="fas fa-user-plus"></i> Crea il primo utente
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="content-card" style="padding: 0;">
        <div class="users-table-container" id="usersTableContainer">
            <table class="table-clean">
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
                                    <span class="badge badge-secondary" title="Utente protetto - non eliminabile" style="margin-top: 4px;">
                                        <i class="fas fa-shield-alt"></i> Protetto
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge status-<?php 
                                    if ($utente['ruolo'] === 'super_admin') echo 'confermato';
                                    elseif ($utente['ruolo'] === 'utente_speciale') echo 'info';
                                    else echo 'pubblicato';
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $utente['ruolo'])); ?>
                                </span>
                                <?php if ($utente['email'] === 'asamodeo@fortibyte.it'): ?>
                                    <span class="badge badge-danger" style="margin-left: 5px;" title="Super Amministratore principale">
                                        <i class="fas fa-crown"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge status-<?php echo $utente['attivo'] ? 'attivo' : 'archiviato'; ?>">
                                    <?php echo $utente['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($utente['data_nascita']): ?>
                                    <?php echo date('d/m/Y', strtotime($utente['data_nascita'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
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
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $primo_accesso = isset($utente['primo_accesso']) ? $utente['primo_accesso'] : false;
                                $giorni_scadenza = isset($utente['giorni_scadenza']) ? $utente['giorni_scadenza'] : null;
                                ?>
                                <?php if ($primo_accesso): ?>
                                    <span class="badge status-invitato">Primo accesso</span>
                                <?php elseif ($giorni_scadenza !== null && $giorni_scadenza < 0): ?>
                                    <span class="badge status-rifiutato">Scaduta</span>
                                <?php elseif ($giorni_scadenza !== null && $giorni_scadenza <= 7): ?>
                                    <span class="badge status-invitato">Scade tra <?php echo $giorni_scadenza; ?>g</span>
                                <?php elseif ($giorni_scadenza !== null): ?>
                                    <span class="text-success text-small">Valida (<?php echo $giorni_scadenza; ?>g)</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="user-actions">
                                    <button class="btn btn-sm btn-secondary" onclick="resetPassword(<?php echo $utente['id']; ?>)" 
                                            title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-<?php echo $utente['attivo'] ? 'danger' : 'success'; ?>" 
                                            onclick="toggleUserStatus(<?php echo $utente['id']; ?>, <?php echo $utente['attivo'] ? '0' : '1'; ?>)"
                                            title="<?php echo $utente['attivo'] ? 'Disattiva' : 'Attiva'; ?>">
                                        <i class="fas fa-<?php echo $utente['attivo'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                    
                                    <a href="modifica-utente.php?id=<?php echo $utente['id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Modifica">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($utente['email'] !== 'asamodeo@fortibyte.it'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $utente['id']; ?>)" 
                                                title="Elimina">
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
    </div>
    
    <!-- Paginazione -->
    <?php 
    $totalPages = ceil($totalUsers / $limit); 
    if ($totalPages > 1): 
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?><?php echo $azienda_filter !== null ? '&azienda=' . $azienda_filter : ''; ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-chevron-left"></i> Precedente
            </a>
        <?php endif; ?>
        
        <span style="margin: 0 15px;">
            Pagina <?php echo $page; ?> di <?php echo $totalPages; ?>
        </span>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?><?php echo $azienda_filter !== null ? '&azienda=' . $azienda_filter : ''; ?>" class="btn btn-sm btn-secondary">
                Successiva <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal Crea Utente -->
<div id="create-user-modal" class="modal">
    <div class="modal-content" >
        <div class="modal-header">
            <h3>Crea Nuovo Utente</h3>
            <button type="button" class="close" onclick="closeModal('create-user-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="create-user-form" onsubmit="return createUserAjax(event)">
                <input type="hidden" name="action" value="create_user">
                
                <h3 >Informazioni Personali</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="nome">Nome <span class="required">*</span></label>
                        <input type="text" name="nome" id="nome" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="cognome">Cognome <span class="required">*</span></label>
                        <input type="text" name="cognome" id="cognome" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="email">Email <span class="required">*</span> <span id="emailCheck" style="font-size: 0.875rem;"></span></label>
                        <input type="email" name="email" id="email" class="form-control" required onblur="checkEmail(this.value)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="data_nascita">Data di Nascita</label>
                        <input type="date" name="data_nascita" id="data_nascita" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="ruolo">Ruolo Sistema <span class="required">*</span></label>
                    <select name="ruolo" id="ruolo" class="form-control" required onchange="checkRolePermissions(this.value)">
                        <option value="">Seleziona un ruolo</option>
                        <option value="utente">Utente</option>
                        <option value="utente_speciale">Utente Speciale</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Super Admin e Utenti Speciali possono vedere tutte le aziende
                    </small>
                </div>
                
                <h3 >Associazione Aziendale</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="azienda_id">Azienda</label>
                        <select name="azienda_id" id="azienda_id" class="form-control" onchange="checkAziendaLimits(this.value)">
                            <option value="">Nessuna azienda</option>
                            <?php foreach ($aziende as $azienda): ?>
                                <option value="<?php echo $azienda['id']; ?>">
                                    <?php echo htmlspecialchars($azienda['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Lascia vuoto per auto-associazione basata sul dominio email
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="ruolo_azienda">Ruolo in Azienda</label>
                        <select name="ruolo_azienda" id="ruolo_azienda" class="form-control">
                            <option value="">Seleziona un ruolo</option>
                            <option value="responsabile_aziendale">Responsabile Aziendale</option>
                            <option value="referente">Referente</option>
                            <option value="utente">Utente</option>
                        </select>
                    </div>
                </div>
                
                <div id="ruolo-warning" class="alert alert-warning" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> <span id="warning-text"></span>
                </div>
                
                <h3 >Credenziali di Accesso</h3>
                
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: center; font-weight: normal;">
                            <input type="radio" name="password_type" value="auto" checked onchange="togglePasswordInput()">
                            <span style="margin-left: 8px;">Genera password automatica (consigliato)</span>
                        </label>
                        <label style="display: flex; align-items: center; font-weight: normal;">
                            <input type="radio" name="password_type" value="manual" onchange="togglePasswordInput()">
                            <span style="margin-left: 8px;">Inserisci password manualmente</span>
                        </label>
                    </div>
                    <input type="password" name="password" id="manual-password" class="form-control" 
                           style="display: none; margin-top: 10px;" minlength="8" placeholder="Minimo 8 caratteri" 
                           oninput="validatePassword()">
                    <div id="password-requirements" style="display: none;" class="password-requirements">
                        <small class="text-muted">Requisiti password:</small>
                        <ul class="requirements-list">
                            <li id="req-length" class="requirement">✗ Almeno 8 caratteri</li>
                            <li id="req-uppercase" class="requirement">✗ Almeno una lettera maiuscola</li>
                            <li id="req-special" class="requirement">✗ Almeno un carattere speciale (!@#$%^&*)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; font-weight: normal;">
                        <input type="checkbox" name="send_email" id="send_email" checked>
                        <span style="margin-left: 8px;">Invia email con credenziali all'utente</span>
                    </label>
                </div>
                
                <div class="modal-footer">
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

<script>
// Cache DOM elements
const messageContainer = document.getElementById('messageContainer');
const globalLoader = document.getElementById('globalLoader');
const usersTableContainer = document.getElementById('usersTableContainer');
const searchInput = document.getElementById('searchUsers');

// Utility functions
function showLoader() {
    globalLoader.classList.add('active');
}

function hideLoader() {
    globalLoader.classList.remove('active');
}

function showMessage(message, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                type === 'error' ? 'fa-exclamation-circle' : 
                type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass}`;
    alertDiv.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
    `;
    
    messageContainer.innerHTML = '';
    messageContainer.appendChild(alertDiv);
    
    // Auto-hide dopo 5 secondi
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

// User management functions
function openCreateUserModal() {
    document.getElementById('create-user-modal').style.display = 'block';
    document.getElementById('create-user-form').reset();
    document.getElementById('emailCheck').innerHTML = '';
    document.getElementById('ruolo-warning').style.display = 'none';
    togglePasswordInput();
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

async function createUserAjax(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = document.getElementById('createUserBtn');
    const btnText = submitBtn.querySelector('span') || submitBtn;
    const btnSpinner = submitBtn.querySelector('i');
    
    // Validazione password manuale
    if (form.password_type.value === 'manual') {
        const password = form.password.value;
        if (!password || password.length < 8) {
            showMessage('La password deve essere di almeno 8 caratteri', 'error');
            return false;
        }
        if (!/[A-Z]/.test(password)) {
            showMessage('La password deve contenere almeno una lettera maiuscola', 'error');
            return false;
        }
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            showMessage('La password deve contenere almeno un carattere speciale', 'error');
            return false;
        }
    }
    
    // Disabilita pulsante e mostra spinner
    submitBtn.disabled = true;
    if (btnText.textContent !== undefined) {
        btnText.textContent = 'Creazione in corso...';
    } else {
        submitBtn.textContent = 'Creazione in corso...';
    }
    if (btnSpinner) {
        btnSpinner.style.display = 'inline-block';
    }
    
    try {
        const formData = new FormData(form);
        
        // Ottieni il token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const headers = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        
        // Aggiungi il token CSRF se disponibile
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            
            // Mostra password generata se disponibile
            if (data.password) {
                const passwordAlert = document.createElement('div');
                passwordAlert.className = 'alert alert-info';
                passwordAlert.style.marginTop = '10px';
                passwordAlert.innerHTML = `
                    <i class="fas fa-key"></i>
                    <strong>Password generata:</strong> <code>${data.password}</code>
                    <button onclick="copyToClipboard('${data.password}')" class="btn btn-sm btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-copy"></i> Copia
                    </button>
                `;
                messageContainer.appendChild(passwordAlert);
            }
            
            closeModal('create-user-modal');
            
            // Ricarica la pagina dopo 2 secondi
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showMessage(data.message || 'Errore durante la creazione dell\'utente', 'error');
        }
    } catch (error) {
        showMessage('Errore di connessione: ' + error.message, 'error');
    } finally {
        // Ripristina pulsante
        submitBtn.disabled = false;
        if (btnText.textContent !== undefined) {
            btnText.textContent = 'Crea Utente';
        } else {
            submitBtn.textContent = 'Crea Utente';
        }
        if (btnSpinner) {
            btnSpinner.style.display = 'none';
        }
    }
    
    return false;
}

async function resetPassword(userId) {
    if (!confirm('Sei sicuro di voler reimpostare la password per questo utente?')) {
        return;
    }
    
    showLoader();
    
    try {
        const formData = new FormData();
        formData.append('action', 'reset_password');
        formData.append('user_id', userId);
        
        // Ottieni il token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            
            // Mostra nuova password
            if (data.password) {
                const passwordAlert = document.createElement('div');
                passwordAlert.className = 'alert alert-info';
                passwordAlert.style.marginTop = '10px';
                passwordAlert.innerHTML = `
                    <i class="fas fa-key"></i>
                    <strong>Nuova password:</strong> <code>${data.password}</code>
                    <button onclick="copyToClipboard('${data.password}')" class="btn btn-sm btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-copy"></i> Copia
                    </button>
                `;
                messageContainer.appendChild(passwordAlert);
            }
        } else {
            showMessage(data.message || 'Errore durante il reset della password', 'error');
        }
    } catch (error) {
        showMessage('Errore di connessione: ' + error.message, 'error');
    } finally {
        hideLoader();
    }
}

async function toggleUserStatus(userId, newStatus) {
    const action = newStatus === 0 ? 'disattivare' : 'attivare';
    
    if (!confirm(`Sei sicuro di voler ${action} questo utente?`)) {
        return;
    }
    
    showLoader();
    
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('user_id', userId);
        formData.append('new_status', newStatus);
        
        // Ottieni il token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(data.message || 'Errore durante l\'aggiornamento dello stato', 'error');
        }
    } catch (error) {
        showMessage('Errore di connessione: ' + error.message, 'error');
    } finally {
        hideLoader();
    }
}

async function deleteUser(userId) {
    if (!confirm('Sei sicuro di voler eliminare questo utente? L\'azione è irreversibile.')) {
        return;
    }
    
    showLoader();
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
        
        // Ottieni il token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            
            // Rimuovi riga dalla tabella con animazione
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    
                    // Se non ci sono più righe, ricarica la pagina per mostrare l'empty state
                    if (document.querySelectorAll('#usersTableBody tr').length === 0) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                }, 300);
            } else {
                // Se la riga non esiste, ricarica la pagina per aggiornare la lista
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } else {
            showMessage(data.message || 'Errore durante l\'eliminazione dell\'utente', 'error');
        }
    } catch (error) {
        showMessage('Errore di connessione: ' + error.message, 'error');
    } finally {
        hideLoader();
    }
}

async function checkEmail(email) {
    const emailCheck = document.getElementById('emailCheck');
    
    if (!email) {
        emailCheck.innerHTML = '';
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_email');
        formData.append('email', email);
        
        // Ottieni il token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.exists) {
            emailCheck.innerHTML = '<span >Email già esistente!</span>';
        } else {
            emailCheck.innerHTML = '<span >Email disponibile</span>';
        }
    } catch (error) {
        emailCheck.innerHTML = '';
    }
}

async function checkAziendaLimits(aziendaId) {
    const warningDiv = document.getElementById('ruolo-warning');
    const warningText = document.getElementById('warning-text');
    const ruoloAziendaSelect = document.getElementById('ruolo_azienda');
    
    if (!aziendaId) {
        warningDiv.style.display = 'none';
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_azienda_limits');
        formData.append('azienda_id', aziendaId);
        
        // Ottieni il token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.hasResponsabile && ruoloAziendaSelect.value === 'responsabile_aziendale') {
                warningText.textContent = 'Questa azienda ha già un Responsabile Aziendale. Solo un responsabile è consentito per azienda.';
                warningDiv.style.display = 'block';
            } else if (data.referentiCount >= data.maxReferenti && ruoloAziendaSelect.value === 'referente') {
                warningText.textContent = `Questa azienda ha raggiunto il limite massimo di ${data.maxReferenti} referenti.`;
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Errore verifica limiti azienda:', error);
    }
}

function checkRolePermissions(role) {
    const aziendaSelect = document.getElementById('azienda_id');
    const ruoloAziendaSelect = document.getElementById('ruolo_azienda');
    
    if (role === 'super_admin' || role === 'utente_speciale') {
        // Disabilita selezione azienda per ruoli globali
        aziendaSelect.value = '';
        aziendaSelect.disabled = true;
        ruoloAziendaSelect.value = '';
        ruoloAziendaSelect.disabled = true;
    } else {
        aziendaSelect.disabled = false;
        ruoloAziendaSelect.disabled = false;
    }
}

function togglePasswordInput() {
    const passwordType = document.querySelector('input[name="password_type"]:checked').value;
    const manualPasswordInput = document.getElementById('manual-password');
    const passwordRequirements = document.getElementById('password-requirements');
    
    if (passwordType === 'manual') {
        manualPasswordInput.style.display = 'block';
        passwordRequirements.style.display = 'block';
        manualPasswordInput.required = true;
    } else {
        manualPasswordInput.style.display = 'none';
        passwordRequirements.style.display = 'none';
        manualPasswordInput.required = false;
        manualPasswordInput.value = '';
    }
}

function validatePassword() {
    const password = document.getElementById('manual-password').value;
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqSpecial = document.getElementById('req-special');
    
    // Check length
    if (password.length >= 8) {
        reqLength.classList.add('valid');
        reqLength.innerHTML = '✓ Almeno 8 caratteri';
    } else {
        reqLength.classList.remove('valid');
        reqLength.innerHTML = '✗ Almeno 8 caratteri';
    }
    
    // Check uppercase
    if (/[A-Z]/.test(password)) {
        reqUppercase.classList.add('valid');
        reqUppercase.innerHTML = '✓ Almeno una lettera maiuscola';
    } else {
        reqUppercase.classList.remove('valid');
        reqUppercase.innerHTML = '✗ Almeno una lettera maiuscola';
    }
    
    // Check special characters
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        reqSpecial.classList.add('valid');
        reqSpecial.innerHTML = '✓ Almeno un carattere speciale (!@#$%^&*)';
    } else {
        reqSpecial.classList.remove('valid');
        reqSpecial.innerHTML = '✗ Almeno un carattere speciale (!@#$%^&*)';
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showMessage('Password copiata negli appunti', 'success');
    }).catch(err => {
        console.error('Errore copia:', err);
    });
}

function filterByAzienda(aziendaId) {
    const url = new URL(window.location.href);
    if (aziendaId === '' || aziendaId === null) {
        url.searchParams.delete('azienda');
    } else {
        url.searchParams.set('azienda', aziendaId);
    }
    window.location.href = url.toString();
}

function searchUsers() {
    const searchValue = searchInput.value.trim();
    const url = new URL(window.location.href);
    
    if (searchValue) {
        url.searchParams.set('search', searchValue);
    } else {
        url.searchParams.delete('search');
    }
    
    url.searchParams.delete('page'); // Reset to page 1
    window.location.href = url.toString();
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Assicurati che il modal sia nascosto all'avvio
    document.getElementById('create-user-modal').style.display = 'none';
    
    // Search on Enter key
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchUsers();
        }
    });
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };
});
</script>

<?php include 'components/footer.php'; ?>