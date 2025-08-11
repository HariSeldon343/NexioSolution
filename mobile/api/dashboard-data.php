<?php
session_start();
require_once '../../backend/config/config.php';
require_once '../../backend/middleware/Auth.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();

if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorizzato']);
    exit();
}

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();

try {
    $stats = [];
    $aziendaId = $currentAzienda['id'] ?? null;
    
    // Conta documenti (tutti gli stati tranne cestino)
    $query = "SELECT COUNT(*) as total FROM documenti WHERE stato != 'cestino'";
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
    } else {
        $stats['utenti'] = 0;
    }
    
    // Attività recenti
    $activities = [];
    $query = "SELECT 
        'documento' as tipo,
        CONCAT('Nuovo documento: ', titolo) as descrizione,
        data_creazione as data,
        creato_da as utente_id
        FROM documenti 
        WHERE stato != 'cestino'";
    
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
    }
    
    $query .= " ORDER BY data_creazione DESC LIMIT 5";
    
    if (!$isSuperAdmin && $aziendaId) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'activities' => $activities,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore server']);
}