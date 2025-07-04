<?php
/**
 * Nexio Advanced Document Editor - Integrated Version
 * Editor completo stile Office 365 con integrazione database
 */

session_start();
require_once __DIR__ . '/backend/config/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    // For development: create a test session
    if (!isset($_GET['dev']) || $_GET['dev'] !== 'test') {
        header('Location: login.php');
        exit;
    } else {
        // Simula sessione per testing
        $_SESSION['user_id'] = 1;
        $_SESSION['azienda_corrente'] = 1;
        $_SESSION['auth_token'] = 'dev_token_' . time();
        $_SESSION['login_time'] = time();
    }
}

$user_id = $_SESSION['user_id'];
$documento_id = $_GET['id'] ?? null;
$documento = null;

// Load existing document if ID provided
if ($documento_id) {
    try {
        // Database instance handled by functions
        $stmt = db_query("SELECT * FROM documenti WHERE id = ? AND user_id = ?", [$documento_id, $user_id]);
        $documento = $stmt->fetch();

        if (!$documento) {
            echo "Documento non trovato o accesso negato.";
            exit;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        echo "Errore di database: " . $e->getMessage();
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexio Advanced Document Editor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }

        /* Office 365 Style Toolbar */
        .office-toolbar {
            background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%);
            color: white;
            padding: 8px 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid #005a9e;
        }

        .toolbar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .document-title-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .document-title-input {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            min-width: 250px;
        }

        .document-title-input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .document-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .action-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }

        /* Office 365 Ribbon */
        .office-ribbon {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 4px 0;
            flex-wrap: wrap;
        }

        .ribbon-group {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 0 12px;
            border-right: 1px solid rgba(255,255,255,0.2);
            position: relative;
        }

        .ribbon-group:last-child {
            border-right: none;
        }

        .ribbon-group::after {
            content: attr(data-label);
            position: absolute;
            bottom: -16px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: rgba(255,255,255,0.8);
            white-space: nowrap;
        }

        .ribbon-btn {
            background: transparent;
            border: 1px solid transparent;
            color: white;
            padding: 6px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            min-width: 45px;
        }

        .ribbon-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.3);
        }

        .ribbon-btn.active {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.4);
        }

        .ribbon-btn i {
            font-size: 14px;
        }

        .ribbon-btn span {
            font-size: 9px;
            line-height: 1;
        }

        .ribbon-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            cursor: pointer;
        }

        .ribbon-select option {
            background: #0078d4;
            color: white;
        }

        /* Color Picker */
        .color-picker {
            position: relative;
            display: inline-block;
        }

        .color-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px;
            display: none;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .color-grid {
            display: grid;
            grid-template-columns: repeat(8, 20px);
            gap: 2px;
        }

        .color-item {
            width: 20px;
            height: 20px;
            border: 1px solid #ddd;
            cursor: pointer;
            border-radius: 2px;
        }

        .color-item:hover {
            transform: scale(1.1);
            border-color: #0078d4;
        }

        /* Main Layout */
        .main-layout {
            display: flex;
            margin-top: 100px;
            height: calc(100vh - 140px);
        }

        /* Enhanced Sidebar */
        .sidebar {
            width: 320px;
            background: white;
            border-right: 1px solid #e1e5e9;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
        }

        .sidebar-tabs {
            display: flex;
            border-bottom: 1px solid #e1e5e9;
        }

        .sidebar-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-tab.active {
            border-bottom-color: #0078d4;
            color: #0078d4;
            background: rgba(0,120,212,0.05);
        }

        .sidebar-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .sidebar-title {
            font-size: 14px;
            font-weight: 600;
            color: #323130;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e1e5e9;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0078d4;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: #605e5c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Navigation */
        .document-outline {
            max-height: 300px;
            overflow-y: auto;
        }

        .outline-item {
            padding: 8px 12px;
            margin: 2px 0;
            cursor: pointer;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.2s ease;
        }

        .outline-item:hover {
            background: rgba(0,120,212,0.05);
        }

        .outline-item.h1 {
            font-weight: bold;
            color: #323130;
        }

        .outline-item.h2 {
            padding-left: 24px;
            color: #605e5c;
        }

        .outline-item.h3 {
            padding-left: 36px;
            color: #8a8886;
        }

        /* Templates */
        .template-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .template-item {
            padding: 12px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            font-size: 11px;
            transition: all 0.2s ease;
        }

        .template-item:hover {
            border-color: #0078d4;
            background: rgba(0,120,212,0.05);
        }

        .template-item i {
            font-size: 20px;
            color: #0078d4;
            margin-bottom: 4px;
        }

        /* Document Area */
        .document-area {
            flex: 1;
            background: #f3f2f1;
            padding: 30px;
            overflow-y: auto;
            display: flex;
            justify-content: center;
        }

        .document-wrapper {
            width: 210mm;
            max-width: 100%;
            position: relative;
        }

        /* Advanced Page System */
        .document-page {
            background: white;
            min-height: 297mm;
            padding: 25mm;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            position: relative;
            outline: none;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #323130;
            page-break-after: always;
        }

        .document-page:last-child {
            margin-bottom: 0;
        }

        /* Enhanced Headers and Footers */
        .page-header, .page-footer {
            position: absolute;
            left: 25mm;
            right: 25mm;
            height: 15mm;
            display: flex;
            align-items: center;
            font-size: 10pt;
            color: #605e5c;
            border-color: #e1e5e9;
            font-family: 'Segoe UI', sans-serif;
        }

        .page-header {
            top: 10mm;
            border-bottom: 1px solid #e1e5e9;
            justify-content: space-between;
        }

        .page-footer {
            bottom: 10mm;
            border-top: 1px solid #e1e5e9;
            justify-content: space-between;
        }

        .header-content, .footer-content {
            display: flex;
            justify-content: space-between;
            width: 100%;
            align-items: center;
        }

        .header-logo, .footer-logo {
            height: 12mm;
            width: auto;
        }

        /* Enhanced Typography */
        .document-page h1 {
            font-size: 24pt;
            font-weight: bold;
            margin: 24pt 0 16pt 0;
            color: #0078d4;
            border-bottom: 2px solid #0078d4;
            padding-bottom: 8pt;
        }

        .document-page h2 {
            font-size: 18pt;
            font-weight: bold;
            margin: 20pt 0 12pt 0;
            color: #323130;
        }

        .document-page h3 {
            font-size: 14pt;
            font-weight: bold;
            margin: 16pt 0 10pt 0;
            color: #323130;
        }

        .document-page p {
            margin: 0 0 12pt 0;
            text-align: justify;
        }

        .document-page ul, .document-page ol {
            margin: 12pt 0 12pt 24pt;
        }

        .document-page li {
            margin: 6pt 0;
        }

        /* Page Numbers */
        .page-number {
            font-size: 10pt;
            color: #605e5c;
        }

        /* Status Bar */
        .status-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #323130;
            color: white;
            padding: 6px 16px;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 999;
            height: 32px;
        }

        .status-left, .status-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Notifications */
        .notification {
            position: fixed;
            top: 120px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            min-width: 250px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: linear-gradient(135deg, #107c10, #0e6b0e);
        }

        .notification.error {
            background: linear-gradient(135deg, #d13438, #b32d32);
        }

        .notification.info {
            background: linear-gradient(135deg, #0078d4, #106ebe);
        }

        /* Loading Indicator */
        .auto-save-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .save-spinner {
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Print Styles */
        @media print {
            .office-toolbar, .sidebar, .status-bar, .notification {
                display: none !important;
            }
            
            .main-layout {
                margin-top: 0 !important;
                height: auto !important;
            }
            
            .document-area {
                padding: 0 !important;
                background: white !important;
            }
            
            .document-page {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin-bottom: 0 !important;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 280px;
            }
            
            .ribbon-group::after {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .office-ribbon {
                justify-content: center;
                gap: 8px;
            }
            
            .ribbon-group {
                padding: 0 8px;
                gap: 2px;
            }
            
            .document-area {
                padding: 15px;
            }
            
            .document-wrapper {
                width: 100%;
            }
            
            .document-page {
                padding: 15mm;
            }
        }
    </style>
</head>
<body>
    <!-- Office 365 Style Toolbar -->
    <div class="office-toolbar">
        <div class="toolbar-header">
            <div class="document-title-section">
                <i class="fas fa-file-word" style="font-size: 20px;"></i>
                <input type="text" id="document-title" class="document-title-input" 
                       placeholder="Documento senza titolo" 
                       value="<?php echo htmlspecialchars($documento['titolo'] ?? 'Nuovo Documento'); ?>">
            </div>
            
            <div class="document-actions">
                <button class="action-btn" onclick="saveDocument()" title="Salva (Ctrl+S)">
                    <i class="fas fa-save"></i>
                    <span>Salva</span>
                </button>
                <button class="action-btn" onclick="exportToPDF()" title="Esporta PDF">
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF</span>
                </button>
                <button class="action-btn" onclick="printDocument()" title="Stampa (Ctrl+P)">
                    <i class="fas fa-print"></i>
                    <span>Stampa</span>
                </button>
                <button class="action-btn" onclick="goToDashboard()" title="Torna alla Dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </button>
            </div>
        </div>
        
        <!-- Office 365 Ribbon -->
        <div class="office-ribbon">
            <div class="ribbon-group" data-label="Appunti">
                <button class="ribbon-btn" onclick="executeCommand('undo')" title="Annulla (Ctrl+Z)">
                    <i class="fas fa-undo"></i>
                    <span>Annulla</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('redo')" title="Ripeti (Ctrl+Y)">
                    <i class="fas fa-redo"></i>
                    <span>Ripeti</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('cut')" title="Taglia (Ctrl+X)">
                    <i class="fas fa-cut"></i>
                    <span>Taglia</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('copy')" title="Copia (Ctrl+C)">
                    <i class="fas fa-copy"></i>
                    <span>Copia</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('paste')" title="Incolla (Ctrl+V)">
                    <i class="fas fa-paste"></i>
                    <span>Incolla</span>
                </button>
            </div>
            
            <div class="ribbon-group" data-label="Carattere">
                <select class="ribbon-select" onchange="executeCommand('fontName', this.value)">
                    <option value="Times New Roman" selected>Times New Roman</option>
                    <option value="Arial">Arial</option>
                    <option value="Calibri">Calibri</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Helvetica">Helvetica</option>
                    <option value="Verdana">Verdana</option>
                </select>
                
                <select class="ribbon-select" onchange="executeCommand('fontSize', this.value)">
                    <option value="1">8pt</option>
                    <option value="2">10pt</option>
                    <option value="3" selected>12pt</option>
                    <option value="4">14pt</option>
                    <option value="5">18pt</option>
                    <option value="6">24pt</option>
                    <option value="7">36pt</option>
                </select>
                
                <button class="ribbon-btn" onclick="executeCommand('bold')" title="Grassetto (Ctrl+B)" data-cmd="bold">
                    <i class="fas fa-bold"></i>
                    <span>G</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('italic')" title="Corsivo (Ctrl+I)" data-cmd="italic">
                    <i class="fas fa-italic"></i>
                    <span>C</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('underline')" title="Sottolineato (Ctrl+U)" data-cmd="underline">
                    <i class="fas fa-underline"></i>
                    <span>S</span>
                </button>
                
                <div class="color-picker">
                    <button class="ribbon-btn" onclick="toggleColorPicker('text-color')" title="Colore testo">
                        <i class="fas fa-font" style="color: #000;"></i>
                        <span>A</span>
                    </button>
                    <div class="color-dropdown" id="text-color-dropdown">
                        <div class="color-grid" id="text-color-grid"></div>
                    </div>
                </div>
                
                <div class="color-picker">
                    <button class="ribbon-btn" onclick="toggleColorPicker('highlight-color')" title="Evidenzia">
                        <i class="fas fa-highlighter"></i>
                        <span>Evid</span>
                    </button>
                    <div class="color-dropdown" id="highlight-color-dropdown">
                        <div class="color-grid" id="highlight-color-grid"></div>
                    </div>
                </div>
            </div>
            
            <div class="ribbon-group" data-label="Paragrafo">
                <button class="ribbon-btn" onclick="executeCommand('justifyLeft')" title="Allinea a sinistra">
                    <i class="fas fa-align-left"></i>
                    <span>Sinistra</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('justifyCenter')" title="Centra">
                    <i class="fas fa-align-center"></i>
                    <span>Centro</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('justifyRight')" title="Allinea a destra">
                    <i class="fas fa-align-right"></i>
                    <span>Destra</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('justifyFull')" title="Giustifica">
                    <i class="fas fa-align-justify"></i>
                    <span>Giustifica</span>
                </button>
                
                <button class="ribbon-btn" onclick="executeCommand('insertUnorderedList')" title="Elenco puntato">
                    <i class="fas fa-list-ul"></i>
                    <span>Elenco</span>
                </button>
                <button class="ribbon-btn" onclick="executeCommand('insertOrderedList')" title="Elenco numerato">
                    <i class="fas fa-list-ol"></i>
                    <span>Numeri</span>
                </button>
            </div>
            
            <div class="ribbon-group" data-label="Stili">
                <button class="ribbon-btn" onclick="insertHeading('h1')" title="Titolo 1">
                    <i class="fas fa-heading"></i>
                    <span>Titolo 1</span>
                </button>
                <button class="ribbon-btn" onclick="insertHeading('h2')" title="Titolo 2">
                    <i class="fas fa-heading"></i>
                    <span>Titolo 2</span>
                </button>
                <button class="ribbon-btn" onclick="insertHeading('h3')" title="Titolo 3">
                    <i class="fas fa-heading"></i>
                    <span>Titolo 3</span>
                </button>
            </div>
            
            <div class="ribbon-group" data-label="Inserisci">
                <button class="ribbon-btn" onclick="insertTable()" title="Inserisci tabella">
                    <i class="fas fa-table"></i>
                    <span>Tabella</span>
                </button>
                <button class="ribbon-btn" onclick="insertImage()" title="Inserisci immagine">
                    <i class="fas fa-image"></i>
                    <span>Immagine</span>
                </button>
                <button class="ribbon-btn" onclick="insertLink()" title="Inserisci collegamento">
                    <i class="fas fa-link"></i>
                    <span>Link</span>
                </button>
                <button class="ribbon-btn" onclick="insertPageBreak()" title="Interruzione pagina">
                    <i class="fas fa-file-alt"></i>
                    <span>Pagina</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Enhanced Sidebar -->
        <div class="sidebar">
            <div class="sidebar-tabs">
                <div class="sidebar-tab active" onclick="switchSidebarTab('stats')">Statistiche</div>
                <div class="sidebar-tab" onclick="switchSidebarTab('outline')">Struttura</div>
                <div class="sidebar-tab" onclick="switchSidebarTab('templates')">Template</div>
            </div>
            
            <div class="sidebar-content">
                <!-- Statistics Tab -->
                <div id="stats-tab" class="tab-content">
                    <div class="sidebar-section">
                        <div class="sidebar-title">
                            <i class="fas fa-chart-bar"></i>
                            Statistiche Documento
                        </div>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number" id="word-count">0</div>
                                <div class="stat-label">Parole</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="char-count">0</div>
                                <div class="stat-label">Caratteri</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="page-count">1</div>
                                <div class="stat-label">Pagine</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="paragraph-count">1</div>
                                <div class="stat-label">Paragrafi</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <div class="sidebar-title">
                            <i class="fas fa-clock"></i>
                            Tempo di Lettura
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="reading-time">0</div>
                            <div class="stat-label">Minuti stimati</div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Outline Tab -->
                <div id="outline-tab" class="tab-content" style="display: none;">
                    <div class="sidebar-section">
                        <div class="sidebar-title">
                            <i class="fas fa-list"></i>
                            Struttura Documento
                        </div>
                        <div class="document-outline" id="document-outline">
                            <div class="outline-item h1">Inizia a scrivere per vedere la struttura</div>
                        </div>
                    </div>
                </div>
                
                <!-- Templates Tab -->
                <div id="templates-tab" class="tab-content" style="display: none;">
                    <div class="sidebar-section">
                        <div class="sidebar-title">
                            <i class="fas fa-file-alt"></i>
                            Template Documento
                        </div>
                        <div class="template-grid">
                            <div class="template-item" onclick="applyTemplate('business-letter')">
                                <i class="fas fa-envelope"></i>
                                <div>Lettera Commerciale</div>
                            </div>
                            <div class="template-item" onclick="applyTemplate('report')">
                                <i class="fas fa-chart-line"></i>
                                <div>Report</div>
                            </div>
                            <div class="template-item" onclick="applyTemplate('memo')">
                                <i class="fas fa-sticky-note"></i>
                                <div>Promemoria</div>
                            </div>
                            <div class="template-item" onclick="applyTemplate('proposal')">
                                <i class="fas fa-handshake"></i>
                                <div>Proposta</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <div class="sidebar-title">
                            <i class="fas fa-cog"></i>
                            Impostazioni Template
                        </div>
                        <div style="font-size: 12px; color: #605e5c; line-height: 1.4;">
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="auto-header" checked> Header automatico
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="auto-footer" checked> Footer automatico
                            </label>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="page-numbers" checked> Numerazione pagine
                            </label>
                            <label style="display: block;">
                                <input type="checkbox" id="company-logo" checked> Logo aziendale
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Area -->
        <div class="document-area">
            <div class="document-wrapper">
                <!-- Document Pages -->
                <div class="document-page" id="page-1">
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="header-content">
                            <span id="header-left">Nexio Platform</span>
                            <span id="header-right" class="current-date"></span>
                        </div>
                    </div>
                    
                    <!-- Main Editor Content -->
                    <div class="page-content" contenteditable="true" id="main-editor">
                        <?php 
                        if ($documento && !empty($documento['contenuto_html'])) {
                            echo $documento['contenuto_html'];
                        } else {
                            echo '<p>Benvenuto nel nuovo Editor Avanzato di Nexio Platform. Inizia a scrivere il tuo documento professionale qui. Questo editor offre tutte le funzionalità di Microsoft Office 365 con salvataggio automatico ogni 30 secondi.</p>';
                        }
                        ?>
                    </div>
                    
                    <!-- Page Footer -->
                    <div class="page-footer">
                        <div class="footer-content">
                            <span id="footer-left">© 2024 Nexio Platform</span>
                            <span class="page-number">Pagina <span id="current-page-num">1</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="status-bar">
        <div class="status-left">
            <div class="status-item">
                <i class="fas fa-circle" style="color: #107c10; font-size: 8px;"></i>
                <span>Pronto</span>
            </div>
            <div class="status-item auto-save-indicator">
                <div class="save-spinner" id="save-spinner"></div>
                <span id="auto-save-status">Salvato automaticamente</span>
            </div>
        </div>
        <div class="status-right">
            <div class="status-item">
                <span>Pagina <span id="status-page">1</span> di <span id="status-total">1</span></span>
            </div>
            <div class="status-item">
                <span id="cursor-position">Riga 1, Colonna 1</span>
            </div>
            <div class="status-item">
                <span>Zoom: 100%</span>
            </div>
        </div>
    </div>

    <script>
        // Configuration
        const EDITOR_CONFIG = {
            documentId: <?php echo $documento_id ? $documento_id : 'null'; ?>,
            userId: <?php echo $user_id; ?>,
            autoSaveInterval: 30000, // 30 seconds
            apiBaseUrl: 'backend/api/',
            currentTemplate: null,
            currentAzienda: null
        };

        // Advanced Office 365 Style Editor
        class NexioAdvancedEditor {
            constructor() {
                this.editor = document.getElementById('main-editor');
                this.autoSaveInterval = null;
                this.lastSaveTime = Date.now();
                this.isModified = false;
                this.currentPage = 1;
                this.totalPages = 1;
                this.templates = this.initializeTemplates();
                
                this.init();
            }

            async init() {
                this.setupEventListeners();
                this.initializeColorPickers();
                this.updateCurrentDate();
                
                // Carica template azienda prima di tutto
                await this.loadAziendaTemplate();
                
                this.startAutoSave();
                this.updateAllStats();
                this.updateDocumentOutline();
                this.setupPagination();
                this.focusEditor();
                
                console.log('✅ Nexio Advanced Editor inizializzato!');
                this.showNotification('🚀 Editor avanzato caricato con successo!', 'success');
            }

            setupEventListeners() {
                // Content change events
                this.editor.addEventListener('input', () => {
                    this.isModified = true;
                    this.updateAllStats();
                    this.updateDocumentOutline();
                    this.updatePageCount();
                });

                this.editor.addEventListener('keydown', this.handleKeydown.bind(this));
                this.editor.addEventListener('paste', () => {
                    setTimeout(() => {
                        this.updateAllStats();
                        this.updateDocumentOutline();
                    }, 100);
                });

                // Selection change for toolbar updates
                document.addEventListener('selectionchange', this.updateToolbarState.bind(this));

                // Document title change
                document.getElementById('document-title').addEventListener('input', () => {
                    this.isModified = true;
                });

                // Global keyboard shortcuts
                document.addEventListener('keydown', this.handleGlobalShortcuts.bind(this));

                // Click outside to close color pickers
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.color-picker')) {
                        this.closeAllColorPickers();
                    }
                });
            }

            handleKeydown(e) {
                // Handle Enter key for proper paragraph creation
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.insertParagraph();
                }
                
                // Update stats after any key press
                setTimeout(() => {
                    this.updateAllStats();
                    this.updateDocumentOutline();
                }, 10);
            }

            handleGlobalShortcuts(e) {
                if (e.ctrlKey) {
                    switch(e.key.toLowerCase()) {
                        case 's':
                            e.preventDefault();
                            this.saveDocument();
                            break;
                        case 'p':
                            e.preventDefault();
                            this.printDocument();
                            break;
                        case 'b':
                            if (this.isEditorFocused()) {
                                e.preventDefault();
                                executeCommand('bold');
                            }
                            break;
                        case 'i':
                            if (this.isEditorFocused()) {
                                e.preventDefault();
                                executeCommand('italic');
                            }
                            break;
                        case 'u':
                            if (this.isEditorFocused()) {
                                e.preventDefault();
                                executeCommand('underline');
                            }
                            break;
                    }
                }
            }

            insertParagraph() {
                const selection = window.getSelection();
                if (selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    const p = document.createElement('p');
                    p.innerHTML = '<br>';
                    
                    range.deleteContents();
                    range.insertNode(p);
                    
                    // Position cursor inside new paragraph
                    range.setStart(p, 0);
                    range.setEnd(p, 0);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }

            isEditorFocused() {
                return document.activeElement === this.editor || this.editor.contains(document.activeElement);
            }

            updateCurrentDate() {
                const now = new Date();
                const dateStr = now.toLocaleDateString('it-IT', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                const elements = document.querySelectorAll('.current-date');
                elements.forEach(el => el.textContent = dateStr);
            }

            updateAllStats() {
                const text = this.getPlainText();
                const words = text.trim().split(/\s+/).filter(word => word.length > 0);
                const paragraphs = this.editor.querySelectorAll('p').length || 1;
                const readingTime = Math.ceil(words.length / 200); // 200 words per minute
                
                document.getElementById('word-count').textContent = words.length;
                document.getElementById('char-count').textContent = text.length;
                document.getElementById('paragraph-count').textContent = paragraphs;
                document.getElementById('reading-time').textContent = readingTime;
                
                this.updateCursorPosition();
            }

            updatePageCount() {
                // Simple page calculation based on content height
                const editorHeight = this.editor.scrollHeight;
                const pageHeight = 800; // Approximate page height in pixels
                const pages = Math.max(1, Math.ceil(editorHeight / pageHeight));
                
                this.totalPages = pages;
                document.getElementById('page-count').textContent = pages;
                document.getElementById('status-total').textContent = pages;
                document.getElementById('current-page-num').textContent = this.currentPage;
                document.getElementById('status-page').textContent = this.currentPage;
            }

            updateCursorPosition() {
                try {
                    const selection = window.getSelection();
                    if (selection.rangeCount > 0) {
                        const range = selection.getRangeAt(0);
                        const textBeforeCursor = this.getPlainText().substring(0, range.startOffset);
                        const lines = textBeforeCursor.split('\n');
                        const currentLine = lines.length;
                        const currentColumn = lines[lines.length - 1].length + 1;
                        
                        document.getElementById('cursor-position').textContent = 
                            `Riga ${currentLine}, Colonna ${currentColumn}`;
                    }
                } catch (e) {
                    document.getElementById('cursor-position').textContent = 'Posizione cursore';
                }
            }

            updateDocumentOutline() {
                const outline = document.getElementById('document-outline');
                const headings = this.editor.querySelectorAll('h1, h2, h3, h4, h5, h6');
                
                if (headings.length === 0) {
                    outline.innerHTML = '<div class="outline-item">Nessun titolo trovato</div>';
                    return;
                }

                const outlineHTML = Array.from(headings).map((heading, index) => {
                    const level = heading.tagName.toLowerCase();
                    const text = heading.textContent.trim();
                    return `<div class="outline-item ${level}" onclick="scrollToHeading(${index})">${text}</div>`;
                }).join('');

                outline.innerHTML = outlineHTML;
            }

            getPlainText() {
                return this.editor.textContent || '';
            }

            getHTMLContent() {
                return this.editor.innerHTML;
            }

            updateToolbarState() {
                const commands = ['bold', 'italic', 'underline'];
                commands.forEach(cmd => {
                    const button = document.querySelector(`button[data-cmd="${cmd}"]`);
                    if (button) {
                        try {
                            if (document.queryCommandState(cmd)) {
                                button.classList.add('active');
                            } else {
                                button.classList.remove('active');
                            }
                        } catch (e) {
                            // Ignore errors
                        }
                    }
                });
            }

            initializeColorPickers() {
                const colors = [
                    '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#efefef',
                    '#f3f3f3', '#ffffff', '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff',
                    '#4a86e8', '#0000ff', '#9900ff', '#ff00ff', '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc',
                    '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc'
                ];

                const textColorGrid = document.getElementById('text-color-grid');
                const highlightColorGrid = document.getElementById('highlight-color-grid');

                colors.forEach(color => {
                    // Text color
                    const textColorItem = document.createElement('div');
                    textColorItem.className = 'color-item';
                    textColorItem.style.backgroundColor = color;
                    textColorItem.onclick = () => {
                        executeCommand('foreColor', color);
                        this.closeAllColorPickers();
                    };
                    textColorGrid.appendChild(textColorItem);

                    // Highlight color
                    const highlightColorItem = document.createElement('div');
                    highlightColorItem.className = 'color-item';
                    highlightColorItem.style.backgroundColor = color;
                    highlightColorItem.onclick = () => {
                        executeCommand('hiliteColor', color);
                        this.closeAllColorPickers();
                    };
                    highlightColorGrid.appendChild(highlightColorItem);
                });
            }

            closeAllColorPickers() {
                document.querySelectorAll('.color-dropdown').forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }

            insertPageBreak() {
                // Create new page
                const newPageNum = this.totalPages + 1;
                const newPage = document.createElement('div');
                newPage.className = 'document-page';
                newPage.id = `page-${newPageNum}`;
                
                newPage.innerHTML = `
                    <div class="page-header">
                        <div class="header-content">
                            <span id="header-left-${newPageNum}">Nexio Platform</span>
                            <span id="header-right-${newPageNum}" class="current-date"></span>
                        </div>
                    </div>
                    
                    <div class="page-content" contenteditable="true">
                        <p><br></p>
                    </div>
                    
                    <div class="page-footer">
                        <div class="footer-content">
                            <span id="footer-left-${newPageNum}">© 2024 Nexio Platform</span>
                            <span class="page-number">Pagina <span>${newPageNum}</span></span>
                        </div>
                    </div>
                `;

                document.querySelector('.document-wrapper').appendChild(newPage);
                this.updateCurrentDate();
                this.updatePageCount();
                
                // Focus new page
                const newPageContent = newPage.querySelector('.page-content');
                newPageContent.focus();
            }

            async saveDocument() {
                // Show saving indicator
                const spinner = document.getElementById('save-spinner');
                const status = document.getElementById('auto-save-status');
                
                spinner.style.display = 'block';
                status.textContent = 'Salvataggio in corso...';

                const docData = {
                    docId: EDITOR_CONFIG.documentId,
                    title: document.getElementById('document-title').value,
                    content: this.getHTMLContent(),
                    plainText: this.getPlainText(),
                    stats: {
                        words: document.getElementById('word-count').textContent,
                        chars: document.getElementById('char-count').textContent,
                        pages: document.getElementById('page-count').textContent,
                        paragraphs: document.getElementById('paragraph-count').textContent,
                        readingTime: document.getElementById('reading-time').textContent
                    },
                    settings: {
                        autoHeader: document.getElementById('auto-header').checked,
                        autoFooter: document.getElementById('auto-footer').checked,
                        pageNumbers: document.getElementById('page-numbers').checked,
                        companyLogo: document.getElementById('company-logo').checked
                    }
                };

                try {
                    const response = await fetch('backend/api/save-advanced-document.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(docData)
                    });

                    const result = await response.json();

                    if (response.ok) {
                        // Update document ID if this was a new document
                        if (!EDITOR_CONFIG.documentId && result.docId) {
                            EDITOR_CONFIG.documentId = result.docId;
                            // Update URL to include document ID
                            const newUrl = new URL(window.location);
                            newUrl.searchParams.set('id', result.docId);
                            window.history.replaceState({}, '', newUrl);
                        }

                        this.isModified = false;
                        this.lastSaveTime = Date.now();
                        
                        spinner.style.display = 'none';
                        status.textContent = 'Salvato automaticamente';
                        
                        this.showNotification('📄 Documento salvato con successo!', 'success');
                        console.log('Documento salvato:', result);
                    } else {
                        throw new Error(result.error || 'Errore nel salvataggio');
                    }
                } catch (error) {
                    console.error('Errore durante il salvataggio:', error);
                    spinner.style.display = 'none';
                    status.textContent = 'Errore nel salvataggio';
                    this.showNotification('❌ Errore durante il salvataggio: ' + error.message, 'error');
                }
            }

            printDocument() {
                // Set document title for print
                const originalTitle = document.title;
                document.title = document.getElementById('document-title').value || 'Documento Nexio';
                
                window.print();
                
                // Restore original title
                document.title = originalTitle;
                
                this.showNotification('🖨️ Invio alla stampante...', 'info');
            }

            startAutoSave() {
                this.autoSaveInterval = setInterval(() => {
                    if (this.isModified && this.getPlainText().trim().length > 0) {
                        this.saveDocument();
                        console.log('Auto-save eseguito ogni 30 secondi');
                    }
                }, EDITOR_CONFIG.autoSaveInterval);
            }

            async loadAziendaTemplate() {
                try {
                    console.log('🔍 Caricamento template azienda...');
                    const response = await fetch('backend/api/get-template-azienda.php');
                    
                    if (response.ok) {
                        const result = await response.json();
                        console.log('📋 Risposta API template:', result);
                        
                        if (result.success) {
                            EDITOR_CONFIG.currentTemplate = result.template;
                            EDITOR_CONFIG.currentAzienda = result.azienda;
                            
                            console.log('✅ Template caricato:', {
                                nome: result.template.nome,
                                azienda: result.azienda.nome,
                                hasHeader: !!result.template.header_html,
                                hasFooter: !!result.template.footer_html,
                                type: result.template.tipo
                            });
                            
                            // Applica il template alle pagine
                            this.applyTemplateToPages();
                            
                            this.showNotification(`📄 Template "${result.template.nome}" caricato per ${result.azienda.nome}`, 'success');
                        } else {
                            console.error('❌ API error:', result.error);
                            this.showNotification(`⚠️ Errore template: ${result.error}`, 'error');
                        }
                    } else {
                        console.error('❌ HTTP error:', response.status, response.statusText);
                        const errorText = await response.text();
                        console.error('Error response:', errorText);
                        this.showNotification(`⚠️ Errore server: ${response.status}`, 'error');
                    }
                } catch (error) {
                    console.error('❌ Errore critico caricamento template:', error);
                    this.showNotification('⚠️ Template di default utilizzato', 'warning');
                    
                    // Applica template di fallback
                    this.applyFallbackTemplate();
                }
            }

            applyFallbackTemplate() {
                // Template di emergenza quando non è possibile caricare il template dall'API
                EDITOR_CONFIG.currentTemplate = {
                    id: 0,
                    nome: 'Template Base',
                    header_html: `
                        <div style="text-align: center; border-bottom: 2px solid #0078d4; padding: 15px;">
                            <h2 style="color: #0078d4; margin: 0;">Nexio Platform</h2>
                            <p style="margin: 5px 0; font-size: 12px; color: #666;">{data_corrente}</p>
                        </div>`,
                    footer_html: `
                        <div style="text-align: center; border-top: 1px solid #ccc; padding: 10px; font-size: 11px; color: #666;">
                            <p>© {anno} Nexio Platform | Pagina {numero_pagina}</p>
                        </div>`,
                    tipo: 'word'
                };
                EDITOR_CONFIG.currentAzienda = {
                    id: 1,
                    nome: 'Azienda'
                };
                
                this.applyTemplateToPages();
                console.log('🔧 Template di fallback applicato');
            }

            applyTemplateToPages() {
                const template = EDITOR_CONFIG.currentTemplate;
                const azienda = EDITOR_CONFIG.currentAzienda;
                
                if (!template) return;

                // Applica header e footer a tutte le pagine
                const pages = document.querySelectorAll('.document-page');
                pages.forEach((page, index) => {
                    this.updatePageHeaderFooter(page, index + 1, template, azienda);
                });
            }

            updatePageHeaderFooter(page, pageNumber, template, azienda) {
                const header = page.querySelector('.page-header .header-content');
                const footer = page.querySelector('.page-footer .footer-content');
                
                if (header && template.header_html) {
                    const headerHtml = this.processTemplateVariables(template.header_html, {
                        nome_azienda: azienda?.nome || 'Azienda',
                        logo_azienda: azienda?.logo ? `uploads/loghi/${azienda.logo}` : '',
                        indirizzo_azienda: azienda?.indirizzo || '',
                        telefono_azienda: azienda?.telefono || '',
                        email_azienda: azienda?.email || '',
                        partita_iva: azienda?.partita_iva || '',
                        codice_fiscale: azienda?.codice_fiscale || '',
                        data_corrente: new Date().toLocaleDateString('it-IT'),
                        anno: new Date().getFullYear(),
                        numero_pagina: pageNumber,
                        totale_pagine: document.querySelectorAll('.document-page').length
                    });
                    header.innerHTML = headerHtml;
                }
                
                if (footer && template.footer_html) {
                    const footerHtml = this.processTemplateVariables(template.footer_html, {
                        nome_azienda: azienda?.nome || 'Azienda',
                        logo_azienda: azienda?.logo ? `uploads/loghi/${azienda.logo}` : '',
                        indirizzo_azienda: azienda?.indirizzo || '',
                        telefono_azienda: azienda?.telefono || '',
                        email_azienda: azienda?.email || '',
                        partita_iva: azienda?.partita_iva || '',
                        codice_fiscale: azienda?.codice_fiscale || '',
                        data_corrente: new Date().toLocaleDateString('it-IT'),
                        anno: new Date().getFullYear(),
                        numero_pagina: pageNumber,
                        totale_pagine: document.querySelectorAll('.document-page').length
                    });
                    footer.innerHTML = footerHtml;
                }
                
                // Applica configurazioni CSS se presenti
                if (template.header_config) {
                    const headerEl = page.querySelector('.page-header');
                    if (headerEl && template.header_config.height) {
                        headerEl.style.height = template.header_config.height;
                    }
                }
                
                if (template.footer_config) {
                    const footerEl = page.querySelector('.page-footer');
                    if (footerEl && template.footer_config.height) {
                        footerEl.style.height = template.footer_config.height;
                    }
                }
            }

            processTemplateVariables(html, variables) {
                let processedHtml = html;
                Object.entries(variables).forEach(([key, value]) => {
                    // Supporta sia formato {variable} che [[VARIABLE]]
                    const regex1 = new RegExp(`\\{${key}\\}`, 'g');
                    const regex2 = new RegExp(`\\[\\[${key.toUpperCase()}\\]\\]`, 'g');
                    processedHtml = processedHtml.replace(regex1, value);
                    processedHtml = processedHtml.replace(regex2, value);
                });
                return processedHtml;
            }

            setupPagination() {
                // Observer per monitorare l'altezza del contenuto
                const resizeObserver = new ResizeObserver(() => {
                    this.checkPageOverflow();
                });
                
                resizeObserver.observe(this.editor);
                
                // Controllo iniziale
                this.checkPageOverflow();
            }

            checkPageOverflow() {
                const maxHeightPerPage = 800; // Altezza massima in pixel per pagina
                const currentHeight = this.editor.scrollHeight;
                const currentPages = document.querySelectorAll('.document-page').length;
                const neededPages = Math.ceil(currentHeight / maxHeightPerPage);
                
                if (neededPages > currentPages) {
                    // Aggiungi pagine
                    for (let i = currentPages; i < neededPages; i++) {
                        this.addNewPage(i + 1);
                    }
                } else if (neededPages < currentPages && currentPages > 1) {
                    // Rimuovi pagine vuote (mantieni almeno una pagina)
                    this.removeEmptyPages(neededPages);
                }
                
                this.updatePageCount();
            }

            addNewPage(pageNumber) {
                const template = EDITOR_CONFIG.currentTemplate;
                const azienda = EDITOR_CONFIG.currentAzienda;
                
                const newPage = document.createElement('div');
                newPage.className = 'document-page';
                newPage.id = `page-${pageNumber}`;
                
                newPage.innerHTML = `
                    <div class="page-header">
                        <div class="header-content">
                            <span>Header Pagina ${pageNumber}</span>
                            <span class="current-date"></span>
                        </div>
                    </div>
                    
                    <div class="page-content" contenteditable="false">
                        <!-- Contenuto distribuito automaticamente -->
                    </div>
                    
                    <div class="page-footer">
                        <div class="footer-content">
                            <span>Footer</span>
                            <span class="page-number">Pagina ${pageNumber}</span>
                        </div>
                    </div>
                `;

                document.querySelector('.document-wrapper').appendChild(newPage);
                
                // Applica template se disponibile
                if (template) {
                    this.updatePageHeaderFooter(newPage, pageNumber, template, azienda);
                }
                
                this.updateCurrentDate();
                console.log(`Pagina ${pageNumber} aggiunta`);
            }

            removeEmptyPages(keepPages) {
                const pages = document.querySelectorAll('.document-page');
                for (let i = pages.length - 1; i >= keepPages; i--) {
                    if (pages[i] && pages[i].id !== 'page-1') { // Non rimuovere mai la prima pagina
                        pages[i].remove();
                        console.log(`Pagina ${i + 1} rimossa`);
                    }
                }
            }

            focusEditor() {
                this.editor.focus();
                
                // Position cursor at end
                try {
                    const range = document.createRange();
                    const selection = window.getSelection();
                    range.selectNodeContents(this.editor);
                    range.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(range);
                } catch (e) {
                    console.log('Focus editor fallback');
                }
            }

            showNotification(message, type = 'info') {
                // Remove existing notifications
                const existing = document.querySelectorAll('.notification');
                existing.forEach(n => n.remove());
                
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                // Show notification
                setTimeout(() => notification.classList.add('show'), 100);
                
                // Hide notification
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, 4000);
            }

            initializeTemplates() {
                return {
                    'business-letter': {
                        name: 'Lettera Commerciale',
                        content: `
                            <div style="text-align: right; margin-bottom: 40px;">
                                <div>Nexio Platform S.r.l.</div>
                                <div>Via Roma, 123</div>
                                <div>20100 Milano (MI)</div>
                                <div>Tel: +39 02 123456</div>
                                <div>Email: info@nexio.it</div>
                            </div>
                            
                            <div style="margin-bottom: 30px;">
                                <div><strong>Spett.le</strong></div>
                                <div>[Nome Destinatario]</div>
                                <div>[Indirizzo]</div>
                                <div>[CAP Città]</div>
                            </div>
                            
                            <div style="text-align: right; margin-bottom: 30px;">
                                Milano, ${new Date().toLocaleDateString('it-IT')}
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <strong>Oggetto: [Oggetto della comunicazione]</strong>
                            </div>
                            
                            <p>Gentili Signori,</p>
                            
                            <p>[Inserire qui il contenuto della lettera...]</p>
                            
                            <p>In attesa di un Vostro cortese riscontro, porgiamo cordiali saluti.</p>
                            
                            <div style="margin-top: 40px;">
                                <div>Nexio Platform S.r.l.</div>
                                <div style="margin-top: 60px;">_________________________</div>
                                <div>[Nome e Cognome]</div>
                                <div>[Ruolo]</div>
                            </div>
                        `
                    },
                    'report': {
                        name: 'Report',
                        content: `
                            <h1>Report Mensile</h1>
                            <div style="border-bottom: 2px solid #0078d4; margin-bottom: 30px; padding-bottom: 10px;">
                                <strong>Periodo:</strong> ${new Date().toLocaleDateString('it-IT')}
                            </div>
                            
                            <h2>Sommario Esecutivo</h2>
                            <p>[Inserire qui un breve riassunto dei punti principali del report...]</p>
                            
                            <h2>Dati e Analisi</h2>
                            <p>[Inserire qui i dati e le analisi dettagliate...]</p>
                            
                            <h2>Conclusioni</h2>
                            <p>[Inserire qui le conclusioni e le raccomandazioni...]</p>
                            
                            <h2>Prossimi Passi</h2>
                            <ul>
                                <li>[Azione 1]</li>
                                <li>[Azione 2]</li>
                                <li>[Azione 3]</li>
                            </ul>
                        `
                    },
                    'memo': {
                        name: 'Promemoria',
                        content: `
                            <div style="text-align: center; margin-bottom: 30px;">
                                <h1>PROMEMORIA INTERNO</h1>
                            </div>
                            
                            <div style="border: 2px solid #0078d4; padding: 20px; margin-bottom: 30px;">
                                <div><strong>A:</strong> [Destinatario]</div>
                                <div><strong>Da:</strong> [Mittente]</div>
                                <div><strong>Data:</strong> ${new Date().toLocaleDateString('it-IT')}</div>
                                <div><strong>Oggetto:</strong> [Oggetto del promemoria]</div>
                            </div>
                            
                            <p>[Inserire qui il contenuto del promemoria...]</p>
                            
                            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc;">
                                <p><strong>Priorità:</strong> □ Alta □ Media □ Bassa</p>
                                <p><strong>Scadenza:</strong> [Data scadenza]</p>
                            </div>
                        `
                    },
                    'proposal': {
                        name: 'Proposta',
                        content: `
                            <h1>Proposta Commerciale</h1>
                            <div style="border-bottom: 2px solid #0078d4; margin-bottom: 30px; padding-bottom: 10px;">
                                <strong>Nexio Platform - Proposta N° [NUMERO]</strong>
                            </div>
                            
                            <h2>Introduzione</h2>
                            <p>[Breve introduzione alla proposta e al cliente...]</p>
                            
                            <h2>Obiettivi del Progetto</h2>
                            <ul>
                                <li>[Obiettivo 1]</li>
                                <li>[Obiettivo 2]</li>
                                <li>[Obiettivo 3]</li>
                            </ul>
                            
                            <h2>Soluzione Proposta</h2>
                            <p>[Descrizione dettagliata della soluzione...]</p>
                            
                            <h2>Timeline</h2>
                            <p>[Fasi del progetto e tempistiche...]</p>
                            
                            <h2>Investimento</h2>
                            <p>[Dettagli sui costi e condizioni...]</p>
                            
                            <h2>Prossimi Passi</h2>
                            <p>[Azioni successive e contatti...]</p>
                        `
                    }
                };
            }

            applyTemplate(templateId) {
                const template = this.templates[templateId];
                if (template) {
                    this.editor.innerHTML = template.content;
                    this.isModified = true;
                    this.updateAllStats();
                    this.updateDocumentOutline();
                    this.showNotification(`📋 Template "${template.name}" applicato!`, 'success');
                }
            }
        }

        // Global functions for toolbar
        function executeCommand(command, value = null) {
            try {
                document.execCommand(command, false, value);
                if (window.nexioAdvancedEditor) {
                    window.nexioAdvancedEditor.updateToolbarState();
                }
            } catch (e) {
                console.log('Command not supported:', command);
            }
        }

        function insertHeading(tag) {
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                const heading = document.createElement(tag);
                
                if (range.collapsed) {
                    heading.textContent = `Nuovo ${tag.toUpperCase()}`;
                } else {
                    heading.appendChild(range.extractContents());
                }
                
                range.insertNode(heading);
                
                // Position cursor after heading
                const newP = document.createElement('p');
                newP.innerHTML = '<br>';
                heading.parentNode.insertBefore(newP, heading.nextSibling);
                
                range.setStart(newP, 0);
                range.setEnd(newP, 0);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            
            setTimeout(() => {
                if (window.nexioAdvancedEditor) {
                    window.nexioAdvancedEditor.updateDocumentOutline();
                }
            }, 100);
        }

        function insertPageBreak() {
            if (window.nexioAdvancedEditor) {
                window.nexioAdvancedEditor.insertPageBreak();
            }
        }

        function insertTable() {
            const rows = prompt('Numero di righe:', '3');
            const cols = prompt('Numero di colonne:', '3');
            
            if (rows && cols) {
                let tableHTML = '<table border="1" style="border-collapse: collapse; width: 100%; margin: 10px 0;">';
                for (let i = 0; i < parseInt(rows); i++) {
                    tableHTML += '<tr>';
                    for (let j = 0; j < parseInt(cols); j++) {
                        tableHTML += '<td style="padding: 8px; border: 1px solid #ccc;">&nbsp;</td>';
                    }
                    tableHTML += '</tr>';
                }
                tableHTML += '</table><p><br></p>';
                
                executeCommand('insertHTML', tableHTML);
            }
        }

        function insertImage() {
            const url = prompt('URL dell\'immagine:');
            if (url) {
                const imgHTML = `<img src="${url}" style="max-width: 100%; height: auto; margin: 10px 0;" alt="Immagine">`;
                executeCommand('insertHTML', imgHTML);
            }
        }

        function insertLink() {
            const url = prompt('URL del link:');
            const text = prompt('Testo del link:');
            if (url && text) {
                const linkHTML = `<a href="${url}" target="_blank">${text}</a>`;
                executeCommand('insertHTML', linkHTML);
            }
        }

        function saveDocument() {
            if (window.nexioAdvancedEditor) {
                window.nexioAdvancedEditor.saveDocument();
            }
        }

        function printDocument() {
            if (window.nexioAdvancedEditor) {
                window.nexioAdvancedEditor.printDocument();
            }
        }

        function exportToPDF() {
            // Redirect to PDF export script
            const docId = EDITOR_CONFIG.documentId;
            if (docId) {
                window.open(`documento-pdf.php?id=${docId}`, '_blank');
            } else {
                if (window.nexioAdvancedEditor) {
                    window.nexioAdvancedEditor.showNotification('⚠️ Salva il documento prima di esportare in PDF', 'info');
                }
            }
        }

        function goToDashboard() {
            if (window.nexioAdvancedEditor && window.nexioAdvancedEditor.isModified) {
                if (confirm('Hai modifiche non salvate. Vuoi salvare prima di uscire?')) {
                    window.nexioAdvancedEditor.saveDocument();
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    window.location.href = 'dashboard.php';
                }
            } else {
                window.location.href = 'dashboard.php';
            }
        }

        function toggleColorPicker(type) {
            const dropdown = document.getElementById(`${type}-dropdown`);
            const isVisible = dropdown.style.display === 'block';
            
            // Close all color pickers first
            if (window.nexioAdvancedEditor) {
                window.nexioAdvancedEditor.closeAllColorPickers();
            }
            
            // Toggle the clicked one
            dropdown.style.display = isVisible ? 'none' : 'block';
        }

        function switchSidebarTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.sidebar-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            document.getElementById(`${tabName}-tab`).style.display = 'block';
        }

        function applyTemplate(templateId) {
            if (window.nexioAdvancedEditor) {
                window.nexioAdvancedEditor.applyTemplate(templateId);
            }
        }

        function scrollToHeading(index) {
            const headings = document.querySelectorAll('#main-editor h1, #main-editor h2, #main-editor h3, #main-editor h4, #main-editor h5, #main-editor h6');
            if (headings[index]) {
                headings[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight the heading briefly
                const originalStyle = headings[index].style.backgroundColor;
                headings[index].style.backgroundColor = 'rgba(0,120,212,0.2)';
                setTimeout(() => {
                    headings[index].style.backgroundColor = originalStyle;
                }, 2000);
            }
        }

        // Initialize editor when page loads
        document.addEventListener('DOMContentLoaded', () => {
            window.nexioAdvancedEditor = new NexioAdvancedEditor();
        });

        // Prevent accidental page leave
        window.addEventListener('beforeunload', (e) => {
            if (window.nexioAdvancedEditor && window.nexioAdvancedEditor.isModified) {
                e.preventDefault();
                e.returnValue = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>