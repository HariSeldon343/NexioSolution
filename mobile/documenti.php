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
$isSuperAdmin = $auth->isSuperAdmin();

// Ottieni documenti
$documenti = [];
try {
    $query = "SELECT d.*, c.nome as cartella_nome 
              FROM documenti d 
              LEFT JOIN cartelle c ON d.cartella_id = c.id 
              WHERE d.stato != 'cestino'";
    
    if (!$isSuperAdmin && $currentAzienda) {
        $query .= " AND (d.azienda_id = ? OR d.azienda_id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$currentAzienda['id']]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    $documenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Documenti - Nexio Mobile</title>
    
    <?php echo base_url_meta(); ?>
    <?php echo js_config(); ?>
    
    <link rel="manifest" href="manifest.php">
    <link rel="icon" type="image/png" href="icons/icon-192x192.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: var(--dark);
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 12px 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .back-btn {
            background: none;
            border: none;
            color: white;
            padding: 8px;
            cursor: pointer;
        }
        
        .header-title {
            flex: 1;
            font-size: 18px;
            font-weight: 600;
        }
        
        .search-btn {
            background: none;
            border: none;
            color: white;
            padding: 8px;
            cursor: pointer;
        }
        
        .content {
            padding-top: 56px;
            padding-bottom: 60px;
        }
        
        .search-bar {
            padding: 12px 16px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: none;
        }
        
        .search-bar.active {
            display: block;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .folder-nav {
            padding: 12px 16px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        .breadcrumb {
            color: var(--secondary);
            font-size: 14px;
        }
        
        .breadcrumb-separator {
            color: var(--secondary);
        }
        
        .breadcrumb-current {
            color: var(--primary);
            font-weight: 600;
        }
        
        .document-list {
            padding: 16px;
        }
        
        .document-item {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .document-item:active {
            transform: scale(0.98);
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .document-icon.pdf {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .document-icon.doc {
            background: rgba(37, 99, 235, 0.1);
        }
        
        .document-icon.xls {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .document-icon.folder {
            background: rgba(245, 158, 11, 0.1);
        }
        
        .document-info {
            flex: 1;
            min-width: 0;
        }
        
        .document-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        
        .document-meta {
            font-size: 12px;
            color: var(--secondary);
            display: flex;
            gap: 8px;
        }
        
        .document-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            background: none;
            border: none;
            padding: 8px;
            color: var(--secondary);
            cursor: pointer;
        }
        
        .fab {
            position: fixed;
            bottom: 70px;
            right: 16px;
            width: 56px;
            height: 56px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 100;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        
        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 8px;
        }
        
        .empty-text {
            font-size: 14px;
            color: var(--secondary);
        }
        
        /* Bottom navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            z-index: 1000;
        }
        
        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px;
            color: var(--secondary);
            text-decoration: none;
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-icon {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        .nav-label {
            font-size: 10px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <button class="back-btn" onclick="history.back()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </button>
        <h1 class="header-title">Documenti</h1>
        <button class="search-btn" onclick="toggleSearch()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.35-4.35"/>
            </svg>
        </button>
    </header>
    
    <!-- Content -->
    <div class="content">
        <!-- Search Bar -->
        <div class="search-bar" id="searchBar">
            <input type="text" class="search-input" placeholder="Cerca documenti..." id="searchInput">
        </div>
        
        <!-- Folder Navigation -->
        <div class="folder-nav">
            <span class="breadcrumb">Home</span>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-current">Tutti i documenti</span>
        </div>
        
        <!-- Document List -->
        <div class="document-list">
            <?php if (empty($documenti)): ?>
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <div class="empty-title">Nessun documento</div>
                <div class="empty-text">Non ci sono documenti da visualizzare</div>
            </div>
            <?php else: ?>
                <?php foreach ($documenti as $doc): ?>
                <div class="document-item" onclick="viewDocument(<?php echo $doc['id']; ?>)">
                    <div class="document-icon <?php 
                        if (strpos($doc['mime_type'] ?? '', 'pdf') !== false) echo 'pdf';
                        elseif (strpos($doc['mime_type'] ?? '', 'word') !== false) echo 'doc';
                        elseif (strpos($doc['mime_type'] ?? '', 'sheet') !== false) echo 'xls';
                        else echo 'doc';
                    ?>">
                        <?php 
                        if (strpos($doc['mime_type'] ?? '', 'pdf') !== false) echo '📕';
                        elseif (strpos($doc['mime_type'] ?? '', 'word') !== false) echo '📘';
                        elseif (strpos($doc['mime_type'] ?? '', 'sheet') !== false) echo '📗';
                        else echo '📄';
                        ?>
                    </div>
                    <div class="document-info">
                        <div class="document-name"><?php echo htmlspecialchars($doc['titolo'] ?? 'Senza titolo'); ?></div>
                        <div class="document-meta">
                            <span><?php echo $doc['cartella_nome'] ?? 'Root'; ?></span>
                            <span>•</span>
                            <span><?php 
                                $size = $doc['file_size'] ?? 0;
                                if ($size < 1024) echo $size . ' B';
                                elseif ($size < 1024*1024) echo round($size/1024, 1) . ' KB';
                                else echo round($size/(1024*1024), 1) . ' MB';
                            ?></span>
                            <span>•</span>
                            <span><?php echo date('d/m/y', strtotime($doc['data_modifica'] ?? $doc['data_creazione'])); ?></span>
                        </div>
                    </div>
                    <div class="document-actions">
                        <button class="action-btn" onclick="event.stopPropagation(); downloadDocument(<?php echo $doc['id']; ?>)">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- FAB -->
    <button class="fab" onclick="uploadDocument()">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
    </button>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Home</span>
        </a>
        <a href="documenti.php" class="nav-item active">
            <span class="nav-icon">📁</span>
            <span class="nav-label">Documenti</span>
        </a>
        <a href="calendario.php" class="nav-item">
            <span class="nav-icon">📅</span>
            <span class="nav-label">Calendario</span>
        </a>
        <a href="tasks.php" class="nav-item">
            <span class="nav-icon">✅</span>
            <span class="nav-label">Tasks</span>
        </a>
        <a href="altro.php" class="nav-item">
            <span class="nav-icon">⋯</span>
            <span class="nav-label">Altro</span>
        </a>
    </nav>
    
    <script>
        function toggleSearch() {
            const searchBar = document.getElementById('searchBar');
            const searchInput = document.getElementById('searchInput');
            searchBar.classList.toggle('active');
            if (searchBar.classList.contains('active')) {
                searchInput.focus();
            }
        }
        
        function viewDocument(id) {
            // TODO: Implementare visualizzazione documento
            console.log('View document:', id);
        }
        
        function downloadDocument(id) {
            window.location.href = '../backend/api/download-file.php?id=' + id;
        }
        
        function uploadDocument() {
            // TODO: Implementare upload documento
            console.log('Upload document');
        }
        
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.document-item');
            
            items.forEach(item => {
                const name = item.querySelector('.document-name').textContent.toLowerCase();
                item.style.display = name.includes(searchTerm) ? 'flex' : 'none';
            });
        });
    </script>
</body>
</html>