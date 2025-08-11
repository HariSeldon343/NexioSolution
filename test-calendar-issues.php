<?php
/**
 * Test script to diagnose calendar issues
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/CalendarHelper.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();

// Test duration formatting
echo "<h2>Test Duration Formatting</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Minutes</th><th>Formatted</th></tr>";

$testDurations = [30, 60, 90, 120, 180, 240, 360, 480, 1440, 2880, 5760, 10080];
foreach ($testDurations as $minutes) {
    echo "<tr>";
    echo "<td>$minutes min</td>";
    echo "<td>" . CalendarHelper::formatDuration($minutes) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test event fetching
echo "<h2>Test Event Fetching</h2>";

// Get current month events
$year = date('Y');
$month = date('m');
$firstDayOfMonth = "$year-$month-01";

echo "<h3>Query Parameters:</h3>";
echo "<p>First day of month: $firstDayOfMonth</p>";
echo "<p>Current user ID: {$user['id']}</p>";
if ($currentAzienda) {
    $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
    echo "<p>Current azienda ID: $aziendaId</p>";
}

// Build query similar to getEventsForView for list view
$whereClause = "WHERE DATE(e.data_inizio) >= ?";
$params = [$firstDayOfMonth];

if (!$isSuperAdmin && $currentAzienda) {
    $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
    if ($aziendaId) {
        $whereClause .= " AND (e.azienda_id = ? OR e.creata_da = ?)";
        $params[] = $aziendaId;
        $params[] = $user['id'];
    }
}

$sql = "SELECT e.*, 
               u.nome as creatore_nome, u.cognome as creatore_cognome,
               a.nome as nome_azienda,
               COUNT(ep.id) as num_partecipanti
        FROM eventi e 
        LEFT JOIN utenti u ON e.creata_da = u.id 
        LEFT JOIN aziende a ON e.azienda_id = a.id
        LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
        $whereClause
        GROUP BY e.id
        ORDER BY e.data_inizio ASC";

echo "<h3>SQL Query:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo "<p>Parameters: " . json_encode($params) . "</p>";

try {
    $stmt = db_query($sql, $params);
    $eventi = $stmt->fetchAll();
    
    echo "<h3>Events Found: " . count($eventi) . "</h3>";
    
    if (count($eventi) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Titolo</th><th>Data Inizio</th><th>Data Fine</th><th>Durata</th><th>Azienda ID</th><th>Creato da</th></tr>";
        
        foreach ($eventi as $evento) {
            $durationMinutes = CalendarHelper::calculateDurationMinutes($evento['data_inizio'], $evento['data_fine']);
            $durationFormatted = CalendarHelper::formatDuration($durationMinutes);
            
            echo "<tr>";
            echo "<td>{$evento['id']}</td>";
            echo "<td>" . htmlspecialchars($evento['titolo']) . "</td>";
            echo "<td>{$evento['data_inizio']}</td>";
            echo "<td>{$evento['data_fine']}</td>";
            echo "<td>$durationFormatted ($durationMinutes min)</td>";
            echo "<td>{$evento['azienda_id']}</td>";
            echo "<td>{$evento['creata_da']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No events found in the database for this query.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test task fetching if user has permissions
if ($isSuperAdmin || $auth->isUtenteSpeciale()) {
    echo "<h2>Test Task Fetching</h2>";
    
    $task_sql = "SELECT t.*, 
                 u.nome as utente_nome, u.cognome as utente_cognome,
                 a.nome as azienda_nome
                 FROM task_calendario t
                 JOIN utenti u ON t.utente_assegnato_id = u.id
                 JOIN aziende a ON t.azienda_id = a.id
                 WHERE t.stato != 'annullato'
                 AND t.data_fine >= ?";
    
    $task_params = [$firstDayOfMonth];
    
    if (!$isSuperAdmin) {
        $task_sql .= " AND t.utente_assegnato_id = ?";
        $task_params[] = $user['id'];
    }
    
    $task_sql .= " ORDER BY t.data_inizio ASC";
    
    echo "<h3>Task SQL Query:</h3>";
    echo "<pre>" . htmlspecialchars($task_sql) . "</pre>";
    echo "<p>Parameters: " . json_encode($task_params) . "</p>";
    
    try {
        $stmt = db_query($task_sql, $task_params);
        $tasks = $stmt->fetchAll();
        
        echo "<h3>Tasks Found: " . count($tasks) . "</h3>";
        
        if (count($tasks) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Attività</th><th>Giornate</th><th>Durata Formattata</th><th>Data Inizio</th><th>Data Fine</th><th>Utente</th></tr>";
            
            foreach ($tasks as $task) {
                $durationFormatted = CalendarHelper::formatTaskDuration($task['giornate_previste']);
                
                echo "<tr>";
                echo "<td>{$task['id']}</td>";
                echo "<td>" . htmlspecialchars($task['attivita']) . "</td>";
                echo "<td>{$task['giornate_previste']}</td>";
                echo "<td>$durationFormatted</td>";
                echo "<td>{$task['data_inizio']}</td>";
                echo "<td>{$task['data_fine']}</td>";
                echo "<td>{$task['utente_nome']} {$task['utente_cognome']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No tasks found in the database for this query.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// Link back to calendar
echo "<hr>";
echo "<p><a href='calendario-eventi.php'>Back to Calendar</a></p>";
?>