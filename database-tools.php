<?php
/**
 * Strumenti per la gestione del database
 */

require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo super admin possono accedere
if (!$auth->isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Strumenti Database';
require_once 'components/header.php';
?>

<style>
    .tool-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
    }
    
    .tool-card h3 {
        color: #2d5a9f;
        margin-bottom: 10px;
    }
    
    .tool-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        display: inline-block;
        margin: 5px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: #2d5a9f;
        color: white;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    
    .btn-warning {
        background: #ffc107;
        color: #212529;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>

<div class="content-header">
    <h1><i class="fas fa-database"></i> Strumenti Database</h1>
</div>

<div class="tool-grid">
    <div class="tool-card">
        <h3><i class="fas fa-stethoscope"></i> Diagnostica</h3>
        <p>Verifica lo stato della connessione al database e dei servizi XAMPP.</p>
        <a href="check-database.php" class="btn btn-primary" target="_blank">
            <i class="fas fa-search"></i> Esegui Diagnostica
        </a>
    </div>

    <div class="tool-card">
        <h3><i class="fas fa-cogs"></i> Setup Database</h3>
        <p>Crea il database e le tabelle necessarie per il funzionamento della piattaforma.</p>
        <a href="setup-database.php" class="btn btn-success" target="_blank">
            <i class="fas fa-hammer"></i> Setup Database
        </a>
    </div>

    <div class="tool-card">
        <h3><i class="fas fa-table"></i> phpMyAdmin</h3>
        <p>Accedi all'interfaccia di gestione del database per operazioni avanzate.</p>
        <a href="http://localhost/phpmyadmin" class="btn btn-secondary" target="_blank">
            <i class="fas fa-external-link-alt"></i> Apri phpMyAdmin
        </a>
    </div>

    <div class="tool-card">
        <h3><i class="fas fa-user-cog"></i> Aggiungi Colonne</h3>
        <p>Esegui gli script per aggiungere nuove funzionalità al database.</p>
        <a href="add-responsabile-column.php" class="btn btn-warning" target="_blank">
            <i class="fas fa-plus"></i> Aggiungi Responsabile Azienda
        </a>
    </div>

    <div class="tool-card">
        <h3><i class="fas fa-sync"></i> Verifica Struttura</h3>
        <p>Controlla e corregge la struttura delle tabelle del database.</p>
        <a href="verify-database-structure.php" class="btn btn-primary" target="_blank">
            <i class="fas fa-check-double"></i> Verifica Struttura
        </a>
    </div>

    <div class="tool-card">
        <h3><i class="fas fa-arrow-left"></i> Torna alla Dashboard</h3>
        <p>Ritorna alla dashboard principale della piattaforma.</p>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</div>

<div class="tool-card" style="margin-top: 30px;">
    <h3><i class="fas fa-info-circle"></i> Informazioni di Sistema</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div>
            <strong>Versione PHP:</strong> <?php echo phpversion(); ?><br>
            <strong>PDO MySQL:</strong> <?php echo extension_loaded('pdo_mysql') ? '✅ Disponibile' : '❌ Non disponibile'; ?><br>
            <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?>
        </div>
        <div>
            <strong>Host Database:</strong> <?php echo DB_HOST; ?><br>
            <strong>Nome Database:</strong> <?php echo DB_NAME; ?><br>
            <strong>Utente Database:</strong> <?php echo DB_USER; ?>
        </div>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>