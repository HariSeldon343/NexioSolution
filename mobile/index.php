<?php
// Mobile Version - Redirect to login if not authenticated
session_start();
require_once 'config.php';
require_once '../backend/config/config.php';
require_once '../backend/middleware/Auth.php';

$auth = Auth::getInstance();

// Se non autenticato, redirect al login
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit();
}

// Ottieni dati utente
$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="description" content="Nexio Mobile - Piattaforma collaborativa per la gestione documentale">
    <title>Nexio Mobile</title>
    
    <?php echo base_url_meta(); ?>
    <?php echo js_config(); ?>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.php">
    
    <!-- Primary Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="icons/icon-72x72.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icons/icon-512x512.png">
    
    <!-- Apple Touch Icons for iOS -->
    <link rel="apple-touch-icon" href="icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="72x72" href="icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="icons/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="icons/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="icons/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="icons/icon-512x512.png">
    
    <!-- iOS specific PWA tags -->
    <meta name="apple-mobile-web-app-title" content="Nexio">
    <link rel="apple-touch-startup-image" href="icons/icon-512x512.png">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileColor" content="#2563eb">
    <meta name="msapplication-TileImage" content="icons/icon-144x144.png">
    
    <!-- Styles -->
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
            --sidebar-bg: #162d4f;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--light);
            color: var(--dark);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Header */
        .mobile-header {
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
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 600;
            flex: 1;
            text-align: center;
        }
        
        .menu-btn, .user-btn {
            background: none;
            border: none;
            color: white;
            padding: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Main Content */
        .mobile-content {
            padding-top: 56px;
            padding-bottom: 60px;
            min-height: 100vh;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            margin: 0 auto 8px;
            background: var(--primary);
            opacity: 0.1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Section */
        .section {
            padding: 0 16px 16px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--dark);
        }
        
        /* List Items */
        .list-card {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .list-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .list-content {
            flex: 1;
            min-width: 0;
        }
        
        .list-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .list-subtitle {
            font-size: 12px;
            color: var(--secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .list-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* Bottom Navigation */
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
            transition: color 0.2s;
            cursor: pointer;
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
        
        /* Side Menu */
        .side-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: left 0.3s;
            z-index: 2000;
        }
        
        .side-menu.open {
            left: 0;
        }
        
        .menu-header {
            background: var(--primary);
            color: white;
            padding: 20px;
        }
        
        .menu-user {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-role {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .menu-items {
            padding: 16px 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .menu-item:active {
            background: var(--light);
        }
        
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
            z-index: 1999;
        }
        
        .menu-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        
        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideDown {
            from {
                transform: translate(-50%, -100%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }
        
        /* Responsive */
        @media (min-width: 768px) {
            .dashboard-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .mobile-content {
                max-width: 1200px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="mobile-header">
        <button class="menu-btn" onclick="toggleMenu()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 12h18M3 6h18M3 18h18"/>
            </svg>
        </button>
        <div class="header-title">Nexio</div>
        <button class="user-btn" onclick="showUserMenu()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4l2 2"/>
            </svg>
        </button>
    </header>
    
    <!-- Side Menu -->
    <div class="side-menu" id="sideMenu">
        <div class="menu-header">
            <div class="menu-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['nome'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['ruolo'] ?? 'utente'); ?></div>
                </div>
            </div>
            <?php if ($currentAzienda): ?>
            <div style="padding: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; font-size: 12px;">
                <?php echo htmlspecialchars($currentAzienda['nome']); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="menu-items">
            <a href="dashboard.php" class="menu-item">
                <span>📊</span>
                <span>Dashboard</span>
            </a>
            <a href="documenti.php" class="menu-item">
                <span>📁</span>
                <span>Documenti</span>
            </a>
            <a href="calendario.php" class="menu-item">
                <span>📅</span>
                <span>Calendario</span>
            </a>
            <a href="tasks.php" class="menu-item">
                <span>✅</span>
                <span>Attività</span>
            </a>
            <?php if ($isSuperAdmin): ?>
            <a href="aziende.php" class="menu-item">
                <span>🏢</span>
                <span>Aziende</span>
            </a>
            <a href="utenti.php" class="menu-item">
                <span>👥</span>
                <span>Utenti</span>
            </a>
            <?php endif; ?>
            <hr style="margin: 16px 20px; border: none; border-top: 1px solid #e5e7eb;">
            <a href="profilo.php" class="menu-item">
                <span>⚙️</span>
                <span>Profilo</span>
            </a>
            <a href="../logout.php" class="menu-item">
                <span>🚪</span>
                <span>Esci</span>
            </a>
        </div>
    </div>
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>
    
    <!-- Main Content -->
    <main class="mobile-content" id="mainContent">
        <!-- Dashboard sarà caricato qui -->
        <div class="loading">
            <div class="spinner"></div>
        </div>
    </main>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a class="nav-item active" onclick="loadPage('dashboard')">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Home</span>
        </a>
        <a class="nav-item" onclick="loadPage('documenti')">
            <span class="nav-icon">📁</span>
            <span class="nav-label">Documenti</span>
        </a>
        <a class="nav-item" onclick="loadPage('calendario')">
            <span class="nav-icon">📅</span>
            <span class="nav-label">Calendario</span>
        </a>
        <a class="nav-item" onclick="loadPage('tasks')">
            <span class="nav-icon">✅</span>
            <span class="nav-label">Tasks</span>
        </a>
        <a class="nav-item" onclick="loadPage('altro')">
            <span class="nav-icon">⋯</span>
            <span class="nav-label">Altro</span>
        </a>
    </nav>
    
    <script>
        // Toggle side menu
        function toggleMenu() {
            const menu = document.getElementById('sideMenu');
            const overlay = document.getElementById('menuOverlay');
            menu.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        // Show user menu
        function showUserMenu() {
            // TODO: Implementare menu utente
            toggleMenu();
        }
        
        // Load page content
        async function loadPage(page) {
            const content = document.getElementById('mainContent');
            content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            // Update nav active state
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.closest('.nav-item').classList.add('active');
            
            try {
                let response;
                switch(page) {
                    case 'dashboard':
                        response = await fetch(`${window.NexioConfig.BASE_URL}/mobile/api/dashboard-data.php`);
                        const data = await response.json();
                        content.innerHTML = renderDashboard(data);
                        break;
                    case 'documenti':
                        content.innerHTML = renderDocumenti();
                        break;
                    case 'calendario':
                        content.innerHTML = renderCalendario();
                        break;
                    case 'tasks':
                        content.innerHTML = renderTasks();
                        break;
                    case 'altro':
                        toggleMenu();
                        break;
                    default:
                        content.innerHTML = '<div style="padding: 20px; text-align: center;">Pagina non trovata</div>';
                }
            } catch (error) {
                content.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--danger);">Errore caricamento dati</div>';
            }
        }
        
        // Render dashboard
        function renderDashboard(data) {
            return `
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(37, 99, 235, 0.1);">
                            <span style="font-size: 20px;">📄</span>
                        </div>
                        <div class="stat-value">${data?.stats?.documenti || 0}</div>
                        <div class="stat-label">Documenti</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(16, 185, 129, 0.1);">
                            <span style="font-size: 20px;">📅</span>
                        </div>
                        <div class="stat-value">${data?.stats?.eventi || 0}</div>
                        <div class="stat-label">Eventi</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.1);">
                            <span style="font-size: 20px;">✅</span>
                        </div>
                        <div class="stat-value">${data?.stats?.tasks || 0}</div>
                        <div class="stat-label">Tasks</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: rgba(139, 92, 246, 0.1);">
                            <span style="font-size: 20px;">👥</span>
                        </div>
                        <div class="stat-value">${data?.stats?.utenti || 0}</div>
                        <div class="stat-label">Utenti</div>
                    </div>
                </div>
                
                <div class="section">
                    <h2 class="section-title">Attività Recenti</h2>
                    ${data?.activities?.map(activity => `
                        <div class="list-card">
                            <div class="list-icon" style="background: rgba(37, 99, 235, 0.1);">
                                <span>📄</span>
                            </div>
                            <div class="list-content">
                                <div class="list-title">${activity.descrizione}</div>
                                <div class="list-subtitle">${activity.data} • ${activity.utente}</div>
                            </div>
                        </div>
                    `).join('') || '<p style="text-align: center; color: var(--secondary);">Nessuna attività recente</p>'}
                </div>
                
                <div class="section">
                    <h2 class="section-title">Prossimi Eventi</h2>
                    ${data?.events?.map(event => `
                        <div class="list-card">
                            <div class="list-icon" style="background: rgba(16, 185, 129, 0.1);">
                                <span>📅</span>
                            </div>
                            <div class="list-content">
                                <div class="list-title">${event.titolo}</div>
                                <div class="list-subtitle">${event.data_inizio}</div>
                            </div>
                        </div>
                    `).join('') || '<p style="text-align: center; color: var(--secondary);">Nessun evento programmato</p>'}
                </div>
            `;
        }
        
        // Render documenti
        async function renderDocumenti() {
            try {
                const response = await fetch(`${window.NexioConfig.API_URL}/folders-api.php?action=get_contents`);
                const data = await response.json();
                
                if (!data.success) throw new Error(data.error);
                
                let html = '<div class="section">';
                html += '<h2 class="section-title">Documenti e Cartelle</h2>';
                
                // Cartelle
                if (data.folders && data.folders.length > 0) {
                    data.folders.forEach(folder => {
                        html += `
                            <div class="list-card" onclick="openFolder(${folder.id})">
                                <div class="list-icon" style="background: rgba(245, 158, 11, 0.1);">
                                    <span>📁</span>
                                </div>
                                <div class="list-content">
                                    <div class="list-title">${folder.nome}</div>
                                    <div class="list-subtitle">Cartella</div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                // Documenti
                if (data.files && data.files.length > 0) {
                    data.files.forEach(file => {
                        const icon = getFileIcon(file.mime_type);
                        html += `
                            <div class="list-card" onclick="viewDocument(${file.id})">
                                <div class="list-icon" style="background: ${icon.bg};">
                                    <span>${icon.emoji}</span>
                                </div>
                                <div class="list-content">
                                    <div class="list-title">${file.titolo || file.nome_file}</div>
                                    <div class="list-subtitle">${formatFileSize(file.file_size)} • ${formatDate(file.data_modifica)}</div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                if (data.folders?.length === 0 && data.files?.length === 0) {
                    html += '<p style="text-align: center; color: var(--secondary); padding: 20px;">Nessun documento disponibile</p>';
                }
                
                html += '</div>';
                return html;
            } catch (error) {
                return '<div style="padding: 20px; text-align: center; color: var(--danger);">Errore caricamento documenti</div>';
            }
        }
        
        function getFileIcon(mimeType) {
            if (!mimeType) return { emoji: '📄', bg: 'rgba(100, 116, 139, 0.1)' };
            if (mimeType.includes('pdf')) return { emoji: '📕', bg: 'rgba(239, 68, 68, 0.1)' };
            if (mimeType.includes('word')) return { emoji: '📘', bg: 'rgba(37, 99, 235, 0.1)' };
            if (mimeType.includes('excel') || mimeType.includes('sheet')) return { emoji: '📗', bg: 'rgba(16, 185, 129, 0.1)' };
            if (mimeType.includes('image')) return { emoji: '🖼️', bg: 'rgba(139, 92, 246, 0.1)' };
            return { emoji: '📄', bg: 'rgba(100, 116, 139, 0.1)' };
        }
        
        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 10) / 10 + ' ' + sizes[i];
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: '2-digit' });
        }
        
        // Render calendario
        function renderCalendario() {
            return `
                <div style="padding: 20px;">
                    <h2 style="margin-bottom: 16px;">Calendario</h2>
                    <p style="color: var(--secondary);">Sezione calendario in costruzione...</p>
                </div>
            `;
        }
        
        // Render tasks
        function renderTasks() {
            return `
                <div style="padding: 20px;">
                    <h2 style="margin-bottom: 16px;">Tasks</h2>
                    <p style="color: var(--secondary);">Sezione tasks in costruzione...</p>
                </div>
            `;
        }
        
        // Load dashboard on init
        document.addEventListener('DOMContentLoaded', () => {
            loadPage('dashboard');
        });
        
        // Register service worker for PWA with better error handling
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                // Registra il service worker con percorso relativo
                const swPath = `${window.NexioConfig.BASE_URL}/mobile/sw-dynamic.js`;
                const swScope = `${window.NexioConfig.BASE_URL}/mobile/`;
                
                navigator.serviceWorker.register(swPath, {
                    scope: swScope
                })
                    .then(registration => {
                        console.log('ServiceWorker registered:', registration);
                        
                        // Check for updates periodically
                        setInterval(() => {
                            registration.update();
                        }, 60000); // Check every minute
                        
                        // Handle updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New service worker available
                                    if (confirm('Nuova versione disponibile! Vuoi aggiornare?')) {
                                        newWorker.postMessage({ type: 'SKIP_WAITING' });
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    })
                    .catch(error => {
                        console.error('ServiceWorker registration failed:', error);
                    });
            });
            
            // Handle online/offline status
            window.addEventListener('online', () => {
                console.log('Back online');
                showNotification('Connessione ripristinata', 'success');
            });
            
            window.addEventListener('offline', () => {
                console.log('Gone offline');
                showNotification('Modalità offline', 'warning');
            });
        }
        
        // PWA Install prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button
            showInstallPrompt();
        });
        
        function showInstallPrompt() {
            // Create install banner if not exists
            if (!document.getElementById('installBanner')) {
                const banner = document.createElement('div');
                banner.id = 'installBanner';
                banner.innerHTML = `
                    <div style="
                        position: fixed;
                        bottom: 70px;
                        left: 10px;
                        right: 10px;
                        background: var(--primary);
                        color: white;
                        padding: 12px;
                        border-radius: 8px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        z-index: 999;
                        animation: slideUp 0.3s ease-out;
                    ">
                        <div>
                            <div style="font-weight: 600;">Installa Nexio Mobile</div>
                            <div style="font-size: 12px; opacity: 0.9;">Accedi rapidamente dalla home</div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="installPWA()" style="
                                background: white;
                                color: var(--primary);
                                border: none;
                                padding: 8px 16px;
                                border-radius: 4px;
                                font-weight: 600;
                                cursor: pointer;
                            ">Installa</button>
                            <button onclick="dismissInstall()" style="
                                background: transparent;
                                color: white;
                                border: 1px solid rgba(255,255,255,0.3);
                                padding: 8px 16px;
                                border-radius: 4px;
                                cursor: pointer;
                            ">Dopo</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(banner);
            }
        }
        
        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    }
                    deferredPrompt = null;
                    dismissInstall();
                });
            }
        }
        
        function dismissInstall() {
            const banner = document.getElementById('installBanner');
            if (banner) {
                banner.remove();
            }
        }
        
        // Show notification helper
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 70px;
                left: 50%;
                transform: translateX(-50%);
                background: ${type === 'success' ? '#10b981' : type === 'warning' ? '#f59e0b' : '#2563eb'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                animation: slideDown 0.3s ease-out;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>