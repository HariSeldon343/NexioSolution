<?php
// API Mobile per Dashboard
require_once '../config/config.php';
require_once '../middleware/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inizializza autenticazione
$auth = Auth::getInstance();

// Verifica autenticazione tramite sessione o token
$authenticated = false;
$user = null;
$currentAzienda = null;

// Prova prima con sessione
session_start();
if ($auth->isAuthenticated()) {
    $authenticated = true;
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
} 
// Altrimenti prova con token Bearer
else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    // Per ora accettiamo qualsiasi token non vuoto
    // In produzione dovremmo validare il token JWT
    if (!empty($token)) {
        // Decodifica token base64 per ottenere user_id
        $decoded = base64_decode($token);
        $userId = intval($decoded);
        
        if ($userId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM utenti WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $authenticated = true;
                
                // Ottieni azienda corrente
                $stmt = $pdo->prepare("
                    SELECT a.* FROM aziende a
                    JOIN utenti_aziende ua ON a.id = ua.azienda_id
                    WHERE ua.utente_id = ? AND a.stato = 'attiva'
                    ORDER BY ua.is_default DESC, a.id ASC
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $currentAzienda = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            // Statistiche dashboard
            $stats = [];
            $aziendaId = $currentAzienda['id'] ?? null;
            $isSuperAdmin = $user['role'] === 'super_admin';
            
            // Conta documenti
            $query = "SELECT COUNT(*) as total FROM documenti WHERE stato = 'attivo'";
            if (!$isSuperAdmin && $aziendaId) {
                $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$aziendaId]);
            } else {
                $stmt = $pdo->query($query);
            }
            $stats['documenti'] = $stmt->fetchColumn();
            
            // Conta eventi futuri
            $query = "SELECT COUNT(*) as total FROM eventi WHERE data_inizio >= NOW()";
            if (!$isSuperAdmin && $aziendaId) {
                $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$aziendaId]);
            } else {
                $stmt = $pdo->query($query);
            }
            $stats['eventi'] = $stmt->fetchColumn();
            
            // Conta task aperti
            $query = "SELECT COUNT(*) as total FROM tasks WHERE stato IN ('pending', 'in_progress')";
            if (!$isSuperAdmin) {
                $query .= " AND (assegnato_a = ? OR creato_da = ?)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user['id'], $user['id']]);
            } else {
                $stmt = $pdo->query($query);
            }
            $stats['tasks'] = $stmt->fetchColumn();
            
            // Conta utenti attivi (solo per admin)
            if ($isSuperAdmin) {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti WHERE stato = 'attivo'");
                $stats['utenti'] = $stmt->fetchColumn();
            }
            
            // Attività recenti
            $activities = [];
            $query = "SELECT 
                'documento' as tipo,
                CONCAT('Nuovo documento: ', nome) as descrizione,
                data_creazione as data,
                creato_da as utente_id
                FROM documenti 
                WHERE stato = 'attivo'";
            
            if (!$isSuperAdmin && $aziendaId) {
                $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
            }
            
            $query .= " ORDER BY data_creazione DESC LIMIT 10";
            
            if (!$isSuperAdmin && $aziendaId) {
                $stmt = $pdo->prepare($query);
                $stmt->execute([$aziendaId]);
            } else {
                $stmt = $pdo->query($query);
            }
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Ottieni nome utente
                $userStmt = $pdo->prepare("SELECT CONCAT(nome, ' ', cognome) as nome FROM utenti WHERE id = ?");
                $userStmt->execute([$row['utente_id']]);
                $userName = $userStmt->fetchColumn();
                
                $activities[] = [
                    'tipo' => $row['tipo'],
                    'descrizione' => $row['descrizione'],
                    'data' => date('d/m/Y H:i', strtotime($row['data'])),
                    'utente' => $userName ?: 'Sistema'
                ];
            }
            
            // Eventi prossimi
            $events = [];
            $query = "SELECT id, titolo, descrizione, data_inizio, data_fine 
                     FROM eventi 
                     WHERE data_inizio >= NOW()";
            
            if (!$isSuperAdmin && $aziendaId) {
                $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
            }
            
            $query .= " ORDER BY data_inizio ASC LIMIT 5";
            
            if (!$isSuperAdmin && $aziendaId) {
                $stmt = $pdo->prepare($query);
                $stmt->execute([$aziendaId]);
            } else {
                $stmt = $pdo->query($query);
            }
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $events[] = [
                    'id' => $row['id'],
                    'titolo' => $row['titolo'],
                    'descrizione' => $row['descrizione'],
                    'data_inizio' => date('d/m/Y H:i', strtotime($row['data_inizio'])),
                    'data_fine' => $row['data_fine'] ? date('d/m/Y H:i', strtotime($row['data_fine'])) : null
                ];
            }
            
            // Tasks
            $tasks = [];
            $query = "SELECT id, titolo, descrizione, priorita, stato, data_scadenza 
                     FROM tasks 
                     WHERE stato IN ('pending', 'in_progress')";
            
            if (!$isSuperAdmin) {
                $query .= " AND (assegnato_a = ? OR creato_da = ?)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user['id'], $user['id']]);
            } else {
                $stmt = $pdo->query($query);
            }
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tasks[] = [
                    'id' => $row['id'],
                    'titolo' => $row['titolo'],
                    'descrizione' => $row['descrizione'],
                    'priorita' => $row['priorita'],
                    'stato' => $row['stato'],
                    'data_scadenza' => $row['data_scadenza'] ? date('d/m/Y', strtotime($row['data_scadenza'])) : null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'activities' => $activities,
                    'events' => $events,
                    'tasks' => $tasks
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }
} catch (Exception $e) {
    error_log("Errore mobile-api: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore server']);
}