<?php
/**
 * Nuovo Documento OnlyOffice - Accesso Rapido
 */

require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();

// Genera ID unico per nuovo documento
$nuovo_file_id = 'doc_' . $user['id'] . '_' . time();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Documento - Nexio OnlyOffice</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .logo {
            font-size: 48px;
            color: #0078d4;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0078d4, #005bb5);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,120,212,0.3);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e9ecef;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .feature-list {
            text-align: left;
            margin: 30px 0;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
            color: #666;
        }
        
        .feature-item i {
            color: #28a745;
            margin-right: 15px;
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-file-word"></i>
        </div>
        
        <h1>Nuovo Documento</h1>
        <p>Crea e modifica documenti professionali con OnlyOffice integrato nella piattaforma Nexio.</p>
        
        <div class="feature-list">
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Editor Word completo</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-cloud"></i>
                <span>Salvataggio automatico cloud</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-users"></i>
                <span>Collaborazione in tempo reale</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-download"></i>
                <span>Esportazione in tutti i formati</span>
            </div>
        </div>
        
        <a href="editor-onlyoffice-clean.php?id=<?php echo $nuovo_file_id; ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Crea Nuovo Documento
        </a>
        
        <br>
        
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna alla Dashboard
        </a>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px; color: #999;">
            Utente: <?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?> | 
            ID Documento: <code><?php echo $nuovo_file_id; ?></code>
        </div>
    </div>
</body>
</html> 