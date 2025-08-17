<?php
session_start();
require_once 'config.php';
require_once '../backend/config/config.php';
require_once '../backend/middleware/Auth.php';

$auth = Auth::getInstance();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Ottieni eventi del mese corrente
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

$eventi = [];
try {
    $query = "SELECT * FROM eventi 
              WHERE data_inizio BETWEEN ? AND ? 
              AND (azienda_id = ? OR azienda_id IS NULL)
              ORDER BY data_inizio ASC";
    
    $stmt = db_query($query, [$startDate, $endDate, $currentAzienda['id'] ?? null]);
    $eventi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Log error
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <title>Calendario - Nexio Mobile</title>
    
    <?php echo base_url_meta(); ?>
    <?php echo js_config(); ?>
    
    <link rel="manifest" href="manifest.php">
    <link rel="icon" type="image/png" href="icons/icon-192x192.png">
    <link rel="stylesheet" href="<?php echo asset_url('css/nexio-mobile.css'); ?>">
    
    <style>
        /* Calendar specific styles */
        .calendar-container {
            padding: 16px;
            background: white;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav button {
            background: var(--light);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        
        .calendar-day-header {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: var(--secondary);
            padding: 8px 0;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 4px;
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .calendar-day:active {
            background: var(--light);
        }
        
        .calendar-day-number {
            font-size: 14px;
            font-weight: 500;
        }
        
        .calendar-day.other-month {
            opacity: 0.3;
        }
        
        .calendar-day.today {
            background: rgba(37, 99, 235, 0.1);
            border-color: var(--primary);
        }
        
        .calendar-day.today .calendar-day-number {
            color: var(--primary);
            font-weight: 700;
        }
        
        .calendar-day-events {
            margin-top: 2px;
        }
        
        .calendar-event-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
            margin: 1px;
        }
        
        /* View toggle */
        .view-toggle {
            display: flex;
            background: var(--light);
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 16px;
        }
        
        .view-toggle button {
            flex: 1;
            padding: 8px;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--secondary);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .view-toggle button.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Event list */
        .event-list {
            padding: 16px;
        }
        
        .event-card {
            background: white;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 12px;
        }
        
        .event-date-badge {
            min-width: 50px;
            text-align: center;
            padding: 8px;
            background: var(--light);
            border-radius: 8px;
        }
        
        .event-date-day {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .event-date-month {
            font-size: 11px;
            color: var(--secondary);
            text-transform: uppercase;
        }
        
        .event-info {
            flex: 1;
        }
        
        .event-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .event-time {
            font-size: 13px;
            color: var(--secondary);
        }
        
        .event-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }
        
        .event-type.meeting {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }
        
        .event-type.task {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .event-type.deadline {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="mobile-header">
        <button class="mobile-header__btn" onclick="history.back()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </button>
        <h1 class="mobile-header__title">Calendario</h1>
        <button class="mobile-header__btn" onclick="showAddEvent()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
        </button>
    </header>
    
    <!-- Content -->
    <div style="padding-top: 56px; padding-bottom: 60px;">
        <!-- View Toggle -->
        <div style="padding: 16px 16px 0;">
            <div class="view-toggle">
                <button class="active" onclick="switchView('month')">Mese</button>
                <button onclick="switchView('week')">Settimana</button>
                <button onclick="switchView('list')">Lista</button>
            </div>
        </div>
        
        <!-- Calendar View -->
        <div id="monthView" class="calendar-container">
            <div class="calendar-header">
                <button class="calendar-nav" onclick="changeMonth(-1)">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div class="calendar-title">
                    <?php echo ucfirst(strftime('%B %Y', strtotime($startDate))); ?>
                </div>
                <button class="calendar-nav" onclick="changeMonth(1)">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </button>
            </div>
            
            <div class="calendar-grid">
                <!-- Days of week -->
                <div class="calendar-day-header">L</div>
                <div class="calendar-day-header">M</div>
                <div class="calendar-day-header">M</div>
                <div class="calendar-day-header">G</div>
                <div class="calendar-day-header">V</div>
                <div class="calendar-day-header">S</div>
                <div class="calendar-day-header">D</div>
                
                <!-- Calendar days -->
                <?php
                $firstDay = date('N', strtotime($startDate)) - 1;
                $daysInMonth = date('t', strtotime($startDate));
                $today = date('Y-m-d');
                
                // Previous month days
                for ($i = 0; $i < $firstDay; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }
                
                // Current month days
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($currentDate === $today) ? 'today' : '';
                    
                    // Count events for this day
                    $dayEvents = array_filter($eventi, function($e) use ($currentDate) {
                        return date('Y-m-d', strtotime($e['data_inizio'])) === $currentDate;
                    });
                    
                    echo '<div class="calendar-day ' . $isToday . '" onclick="showDayEvents(\'' . $currentDate . '\')">';
                    echo '<div class="calendar-day-number">' . $day . '</div>';
                    
                    if (count($dayEvents) > 0) {
                        echo '<div class="calendar-day-events">';
                        for ($i = 0; $i < min(3, count($dayEvents)); $i++) {
                            echo '<span class="calendar-event-dot"></span>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- List View (hidden by default) -->
        <div id="listView" class="event-list" style="display: none;">
            <?php if (empty($eventi)): ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--secondary);">
                <div style="font-size: 48px; margin-bottom: 16px;">📅</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Nessun evento</div>
                <div style="font-size: 14px;">Non ci sono eventi programmati per questo mese</div>
            </div>
            <?php else: ?>
                <?php foreach ($eventi as $evento): ?>
                <div class="event-card" onclick="viewEvent(<?php echo $evento['id']; ?>)">
                    <div class="event-date-badge">
                        <div class="event-date-day"><?php echo date('d', strtotime($evento['data_inizio'])); ?></div>
                        <div class="event-date-month"><?php echo substr(strftime('%B', strtotime($evento['data_inizio'])), 0, 3); ?></div>
                    </div>
                    <div class="event-info">
                        <div class="event-title"><?php echo htmlspecialchars($evento['titolo']); ?></div>
                        <div class="event-time">
                            <?php echo date('H:i', strtotime($evento['data_inizio'])); ?>
                            <?php if ($evento['data_fine']): ?>
                            - <?php echo date('H:i', strtotime($evento['data_fine'])); ?>
                            <?php endif; ?>
                        </div>
                        <span class="event-type <?php echo $evento['tipo'] ?? 'evento'; ?>">
                            <?php echo ucfirst($evento['tipo'] ?? 'evento'); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="bottom-nav__item">
            <span class="bottom-nav__icon">🏠</span>
            <span class="bottom-nav__label">Home</span>
        </a>
        <a href="documenti.php" class="bottom-nav__item">
            <span class="bottom-nav__icon">📁</span>
            <span class="bottom-nav__label">Documenti</span>
        </a>
        <a href="calendario.php" class="bottom-nav__item active">
            <span class="bottom-nav__icon">📅</span>
            <span class="bottom-nav__label">Calendario</span>
        </a>
        <a href="tasks.php" class="bottom-nav__item">
            <span class="bottom-nav__icon">✅</span>
            <span class="bottom-nav__label">Tasks</span>
        </a>
        <a href="index.php" class="bottom-nav__item" onclick="nexioMobile.toggleSideMenu(); return false;">
            <span class="bottom-nav__icon">⋯</span>
            <span class="bottom-nav__label">Altro</span>
        </a>
    </nav>
    
    <!-- FAB -->
    <button class="fab" onclick="showAddEvent()">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
    </button>
    
    <script src="../assets/js/nexio-mobile.js"></script>
    <script>
        let currentView = 'month';
        let currentMonth = <?php echo $month; ?>;
        let currentYear = <?php echo $year; ?>;
        
        function switchView(view) {
            // Update buttons
            document.querySelectorAll('.view-toggle button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide views
            document.getElementById('monthView').style.display = view === 'month' ? 'block' : 'none';
            document.getElementById('listView').style.display = view === 'list' ? 'block' : 'none';
            
            currentView = view;
        }
        
        function changeMonth(direction) {
            currentMonth += direction;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            } else if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            
            window.location.href = `calendario.php?month=${currentMonth}&year=${currentYear}`;
        }
        
        function showDayEvents(date) {
            nexioMobile.showToast(`Eventi del ${date}`, 'info');
            // TODO: Show day events modal
        }
        
        function viewEvent(id) {
            window.location.href = `evento.php?id=${id}`;
        }
        
        function showAddEvent() {
            window.location.href = 'evento-nuovo.php';
        }
    </script>
</body>
</html>