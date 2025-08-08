<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$userAziende = $auth->getAccessibleCompanies();

// Se ha solo un'azienda, reindirizza automaticamente
if (count($userAziende) == 1) {
    $auth->switchCompany($userAziende[0]['id']);
    // Controlla se c'è un parametro redirect
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : APP_PATH . '/dashboard.php';
    redirect($redirect);
}

// Se non ha aziende, mostra errore
if (empty($userAziende)) {
    $error = "Non hai accesso a nessuna azienda. Contatta l'amministratore.";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleziona Azienda - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #dde1e7;
        }
        
        .selection-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            border: 1px solid #c7cad1;
        }
        
        .selection-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .selection-header h1 {
            color: #1a202c;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .selection-header p {
            color: #718096;
            font-size: 16px;
        }
        
        .aziende-grid {
            display: grid;
            gap: 15px;
            margin-top: 30px;
        }
        
        .azienda-card {
            background: #f7fafc;
            border: 2px solid #c1c7d0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .azienda-card:hover {
            background: #ffffff;
            border-color: #6366f1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        
        .azienda-info h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .azienda-role {
            font-size: 14px;
            color: #718096;
            background: #e2e8f0;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .arrow-icon {
            color: #9ca3af;
            font-size: 20px;
        }
        
        .azienda-card:hover .arrow-icon {
            color: #6366f1;
        }
    </style>
</head>
<body>
    <div class="selection-container">
        <div class="selection-header">
            <h1>Seleziona Azienda</h1>
            <p>Scegli l'azienda con cui vuoi lavorare</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="aziende-grid">
            <?php foreach ($userAziende as $ua): ?>
            <a href="<?php echo APP_PATH; ?>/cambia-azienda.php?azienda_id=<?php echo $ua['id']; ?>&redirect=<?php echo urlencode($_GET['redirect'] ?? APP_PATH . '/filesystem.php'); ?>" class="azienda-card">
                <div class="azienda-info">
                    <h3><?php echo htmlspecialchars($ua['nome']); ?></h3>
                    <?php if (isset($ua['ruolo_azienda'])): ?>
                    <span class="azienda-role">
                        <?php 
                        $ruoli = [
                            'proprietario' => 'Proprietario',
                            'admin' => 'Amministratore',
                            'manager' => 'Manager',
                            'user' => 'Utente',
                            'utente' => 'Utente',
                            'ospite' => 'Ospite'
                        ];
                        echo $ruoli[$ua['ruolo_azienda']] ?? ucfirst($ua['ruolo_azienda']);
                        ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="arrow-icon">→</div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo APP_PATH; ?>/logout.php" style="color: #718096; text-decoration: none; font-size: 14px;">
                ← Torna al login
            </a>
        </div>
    </div>
</body>
</html> 