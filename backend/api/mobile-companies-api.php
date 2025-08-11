<?php
// API Mobile per Gestione Aziende  
require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica autenticazione tramite token
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token mancante']);
    exit();
}

$userId = intval(base64_decode($token));

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token non valido']);
    exit();
}

// Ottieni dati utente
$stmt = $pdo->prepare("SELECT * FROM utenti WHERE id = ? AND stato = 'attivo'");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Utente non trovato']);
    exit();
}

$isSuperAdmin = $user['role'] === 'super_admin';
$hasElevatedPrivileges = in_array($user['role'], ['super_admin', 'utente_speciale']);

// Solo utenti con privilegi elevati possono gestire aziende
if (!$hasElevatedPrivileges) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Lista aziende REALI dal database
            if ($isSuperAdmin) {
                // Super admin vede tutte le aziende
                $stmt = $pdo->query("
                    SELECT a.*, 
                           (SELECT COUNT(*) FROM utenti_aziende ua WHERE ua.azienda_id = a.id) as numero_utenti,
                           (SELECT COUNT(*) FROM documenti d WHERE d.azienda_id = a.id) as numero_documenti
                    FROM aziende a 
                    ORDER BY a.nome ASC
                ");
            } else {
                // Utente speciale vede solo le sue aziende
                $stmt = $pdo->prepare("
                    SELECT a.*,
                           (SELECT COUNT(*) FROM utenti_aziende ua2 WHERE ua2.azienda_id = a.id) as numero_utenti,
                           (SELECT COUNT(*) FROM documenti d WHERE d.azienda_id = a.id) as numero_documenti
                    FROM aziende a
                    JOIN utenti_aziende ua ON a.id = ua.azienda_id
                    WHERE ua.utente_id = ?
                    ORDER BY a.nome ASC
                ");
                $stmt->execute([$userId]);
            }
            
            $companies = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $companies[] = [
                    'id' => (int)$row['id'],
                    'nome' => $row['nome'],
                    'partita_iva' => $row['partita_iva'] ?? '',
                    'codice_fiscale' => $row['codice_fiscale'] ?? '',
                    'indirizzo' => $row['indirizzo'] ?? '',
                    'citta' => $row['citta'] ?? '',
                    'cap' => $row['cap'] ?? '',
                    'provincia' => $row['provincia'] ?? '',
                    'telefono' => $row['telefono'] ?? '',
                    'email' => $row['email'] ?? '',
                    'stato' => $row['stato'] ?? 'attiva',
                    'logo' => $row['logo'] ?? null,
                    'settore' => $row['settore'] ?? '',
                    'numero_dipendenti' => (int)($row['numero_dipendenti'] ?? 0),
                    'numero_utenti' => (int)($row['numero_utenti'] ?? 0),
                    'numero_documenti' => (int)($row['numero_documenti'] ?? 0),
                    'data_creazione' => $row['data_creazione'],
                    'data_modifica' => $row['data_modifica']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $companies
            ]);
            break;
            
        case 'detail':
            $companyId = $_GET['id'] ?? 0;
            
            // Verifica accesso all'azienda
            if ($isSuperAdmin) {
                $stmt = $pdo->prepare("SELECT * FROM aziende WHERE id = ?");
                $stmt->execute([$companyId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT a.* FROM aziende a
                    JOIN utenti_aziende ua ON a.id = ua.azienda_id
                    WHERE a.id = ? AND ua.utente_id = ?
                ");
                $stmt->execute([$companyId, $userId]);
            }
            
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Azienda non trovata']);
                exit();
            }
            
            // Ottieni utenti dell'azienda
            $stmt = $pdo->prepare("
                SELECT u.id, u.nome, u.cognome, u.email, u.role, ua.ruolo as ruolo_azienda
                FROM utenti u
                JOIN utenti_aziende ua ON u.id = ua.utente_id
                WHERE ua.azienda_id = ? AND u.stato = 'attivo'
                ORDER BY u.cognome, u.nome
            ");
            $stmt->execute([$companyId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Statistiche
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documenti WHERE azienda_id = ?");
            $stmt->execute([$companyId]);
            $documentsCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM eventi WHERE azienda_id = ?");
            $stmt->execute([$companyId]);
            $eventsCount = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'company' => $company,
                    'users' => $users,
                    'stats' => [
                        'documents' => (int)$documentsCount,
                        'events' => (int)$eventsCount,
                        'users' => count($users)
                    ]
                ]
            ]);
            break;
            
        case 'create':
            // Solo super admin può creare aziende
            if (!$isSuperAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo il super admin può creare aziende']);
                exit();
            }
            
            $nome = $_POST['nome'] ?? '';
            $partitaIva = $_POST['partita_iva'] ?? '';
            
            if (empty($nome)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nome azienda richiesto']);
                exit();
            }
            
            // Verifica duplicati
            $stmt = $pdo->prepare("SELECT id FROM aziende WHERE nome = ? OR (partita_iva = ? AND partita_iva != '')");
            $stmt->execute([$nome, $partitaIva]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Azienda già esistente']);
                exit();
            }
            
            // Inserisci azienda
            $stmt = $pdo->prepare("
                INSERT INTO aziende (nome, partita_iva, codice_fiscale, indirizzo, citta, cap, provincia, telefono, email, settore, numero_dipendenti, stato)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'attiva')
            ");
            
            $stmt->execute([
                $nome,
                $partitaIva,
                $_POST['codice_fiscale'] ?? '',
                $_POST['indirizzo'] ?? '',
                $_POST['citta'] ?? '',
                $_POST['cap'] ?? '',
                $_POST['provincia'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['email'] ?? '',
                $_POST['settore'] ?? '',
                $_POST['numero_dipendenti'] ?? 0
            ]);
            
            $companyId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'data' => ['id' => $companyId]
            ]);
            break;
            
        case 'update':
            $companyId = $_POST['id'] ?? 0;
            
            // Verifica accesso
            if (!$isSuperAdmin) {
                $stmt = $pdo->prepare("
                    SELECT 1 FROM utenti_aziende 
                    WHERE azienda_id = ? AND utente_id = ? AND ruolo = 'admin'
                ");
                $stmt->execute([$companyId, $userId]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
                    exit();
                }
            }
            
            // Aggiorna azienda
            $updates = [];
            $params = [];
            
            foreach (['nome', 'partita_iva', 'codice_fiscale', 'indirizzo', 'citta', 'cap', 'provincia', 'telefono', 'email', 'settore', 'numero_dipendenti'] as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $_POST[$field];
                }
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nessun campo da aggiornare']);
                exit();
            }
            
            $params[] = $companyId;
            $stmt = $pdo->prepare("UPDATE aziende SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'toggle-status':
            $companyId = $_POST['id'] ?? 0;
            
            // Solo super admin può cambiare stato
            if (!$isSuperAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo il super admin può cambiare lo stato']);
                exit();
            }
            
            // Ottieni stato attuale
            $stmt = $pdo->prepare("SELECT stato FROM aziende WHERE id = ?");
            $stmt->execute([$companyId]);
            $currentStatus = $stmt->fetchColumn();
            
            if (!$currentStatus) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Azienda non trovata']);
                exit();
            }
            
            // Cambia stato
            $newStatus = $currentStatus === 'attiva' ? 'sospesa' : 'attiva';
            $stmt = $pdo->prepare("UPDATE aziende SET stato = ? WHERE id = ?");
            $stmt->execute([$newStatus, $companyId]);
            
            echo json_encode([
                'success' => true,
                'data' => ['newStatus' => $newStatus]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }
} catch (Exception $e) {
    error_log("Errore mobile-companies-api: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore server']);
}