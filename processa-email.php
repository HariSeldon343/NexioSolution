<?php
/**
 * Processore Coda Email - Può essere eseguito manualmente o via cron
 * Esempio cron: * /5 * * * * php /path/to/processa-email.php --cron
 */

// Gestione esecuzione da CLI o web
$isCLI = php_sapi_name() === 'cli';
$isCron = $isCLI && in_array('--cron', $argv ?? []);

if (!$isCLI) {
    require_once 'backend/config/config.php';
    $auth = Auth::getInstance();
    $auth->requireAuth();
    if (!$auth->isSuperAdmin()) {
        die('Accesso negato');
    }
}

// Configurazione
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__FILE__));
    require_once APP_PATH . '/backend/config/config.php';
}

require_once APP_PATH . '/backend/utils/Mailer.php';

class EmailQueueProcessor {
    private $db;
    private $mailer;
    private $maxRetries = 3;
    private $batchSize = 10;
    private $processedCount = 0;
    private $failedCount = 0;
    private $output = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = Mailer::getInstance();
    }
    
    public function process() {
        $this->log("=== Inizio processamento coda email ===");
        $this->log("Data/ora: " . date('Y-m-d H:i:s'));
        
        try {
            // Crea tabella se non esiste
            $this->ensureTableExists();
            
            // Recupera email da processare
            $emails = $this->getEmailsToProcess();
            
            if (empty($emails)) {
                $this->log("Nessuna email da processare");
                return $this->getResults();
            }
            
            $this->log("Email da processare: " . count($emails));
            
            foreach ($emails as $email) {
                $this->processEmail($email);
            }
            
            // Pulizia vecchie email inviate (opzionale)
            $this->cleanOldEmails();
            
        } catch (Exception $e) {
            $this->log("ERRORE CRITICO: " . $e->getMessage());
        }
        
        $this->log("=== Fine processamento ===");
        $this->log("Email inviate: " . $this->processedCount);
        $this->log("Email fallite: " . $this->failedCount);
        
        return $this->getResults();
    }
    
    private function ensureTableExists() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS notifiche_email (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    destinatario_email VARCHAR(255) NOT NULL,
                    destinatario_nome VARCHAR(255),
                    oggetto VARCHAR(255) NOT NULL,
                    contenuto TEXT NOT NULL,
                    tipo_notifica VARCHAR(50),
                    azienda_id INT,
                    priorita INT DEFAULT 5,
                    stato ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                    tentativi INT DEFAULT 0,
                    ultimo_errore TEXT,
                    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    inviato_il TIMESTAMP NULL,
                    INDEX idx_stato (stato),
                    INDEX idx_priorita (priorita),
                    INDEX idx_creato (creato_il)
                )
            ");
        } catch (Exception $e) {
            // Tabella già esistente
        }
    }
    
    private function getEmailsToProcess() {
        $sql = "
            SELECT * FROM notifiche_email 
            WHERE stato = 'pending' 
            AND tentativi < :max_retries
            ORDER BY priorita ASC, creato_il ASC
            LIMIT :batch_size
        ";
        
        $stmt = $this->db->query($sql, [
            'max_retries' => $this->maxRetries,
            'batch_size' => $this->batchSize
        ]);
        
        return $stmt->fetchAll();
    }
    
    private function processEmail($email) {
        $this->log("\nProcessamento email ID: {$email['id']}");
        $this->log("Destinatario: {$email['destinatario_email']}");
        $this->log("Oggetto: {$email['oggetto']}");
        
        try {
            // Incrementa tentativi
            $this->db->query("
                UPDATE notifiche_email 
                SET tentativi = tentativi + 1 
                WHERE id = :id
            ", ['id' => $email['id']]);
            
            // Invia email
            $result = $this->mailer->send(
                $email['destinatario_email'],
                $email['oggetto'],
                $email['contenuto']
            );
            
            if ($result) {
                // Successo
                $this->db->query("
                    UPDATE notifiche_email 
                    SET stato = 'sent', inviato_il = NOW() 
                    WHERE id = :id
                ", ['id' => $email['id']]);
                
                $this->log("✓ Email inviata con successo");
                $this->processedCount++;
            } else {
                throw new Exception("Invio fallito");
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            $this->log("✗ Errore: " . $error);
            
            // Aggiorna stato
            $stato = $email['tentativi'] >= $this->maxRetries - 1 ? 'failed' : 'pending';
            
            $this->db->query("
                UPDATE notifiche_email 
                SET stato = :stato, ultimo_errore = :errore 
                WHERE id = :id
            ", [
                'stato' => $stato,
                'errore' => $error,
                'id' => $email['id']
            ]);
            
            if ($stato === 'failed') {
                $this->failedCount++;
            }
        }
    }
    
    private function cleanOldEmails() {
        // Elimina email inviate più vecchie di 30 giorni
        try {
            $stmt = $this->db->query("
                DELETE FROM notifiche_email 
                WHERE stato = 'sent' 
                AND inviato_il < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                $this->log("\nPulizia: eliminate $deleted email vecchie");
            }
        } catch (Exception $e) {
            $this->log("Errore pulizia: " . $e->getMessage());
        }
    }
    
    private function log($message) {
        $this->output[] = $message;
        
        // Se eseguito da CLI, stampa immediatamente
        global $isCLI;
        if ($isCLI) {
            echo $message . "\n";
        }
        
        // Log su file se in modalità cron
        global $isCron;
        if ($isCron) {
            error_log($message);
        }
    }
    
    private function getResults() {
        return [
            'processed' => $this->processedCount,
            'failed' => $this->failedCount,
            'output' => $this->output
        ];
    }
}

// Esecuzione
$processor = new EmailQueueProcessor();
$results = $processor->process();

// Se non è CLI, mostra interfaccia web
if (!$isCLI):
    $pageTitle = 'Processa Coda Email';
    require_once 'components/header.php';
?>

<style>
.email-processor {
    max-width: 900px;
    margin: 20px auto;
}

.processor-output {
    background: #1a1a1a;
    color: #0f0;
    padding: 20px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.5;
    max-height: 600px;
    overflow-y: auto;
}

.processor-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.stat-box .value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-box.success .value {
    color: #28a745;
}

.stat-box.error .value {
    color: #dc3545;
}

.stat-box .label {
    color: #6c757d;
}

.actions {
    margin-top: 30px;
    text-align: center;
}

.info-box {
    background: #e3f2fd;
    border: 1px solid #90caf9;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-box h3 {
    margin-top: 0;
    color: #1976d2;
}

.info-box code {
    background: #f5f5f5;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
}
</style>

<div class="content-header">
    <h1><i class="fas fa-sync"></i> Processa Coda Email</h1>
</div>

<div class="email-processor">
    <div class="processor-stats">
        <div class="stat-box success">
            <div class="value"><?php echo $results['processed']; ?></div>
            <div class="label">Email Inviate</div>
        </div>
        <div class="stat-box error">
            <div class="value"><?php echo $results['failed']; ?></div>
            <div class="label">Email Fallite</div>
        </div>
    </div>
    
    <div class="info-box">
        <h3><i class="fas fa-info-circle"></i> Automazione con Cron</h3>
        <p>Per processare automaticamente la coda email, aggiungi questa riga al crontab:</p>
        <code>*/5 * * * * php <?php echo realpath(__FILE__); ?> --cron</code>
        <p style="margin-top: 10px;">Questo eseguirà il processamento ogni 5 minuti.</p>
    </div>
    
    <h2>Output Processamento</h2>
    <div class="processor-output">
        <?php foreach ($results['output'] as $line): ?>
            <?php echo htmlspecialchars($line); ?><br>
        <?php endforeach; ?>
    </div>
    
    <div class="actions">
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-primary">
            <i class="fas fa-redo"></i> Esegui di Nuovo
        </a>
        <a href="<?php echo APP_PATH; ?>/configurazione-email.php" class="btn btn-secondary">
            <i class="fas fa-cog"></i> Configurazione Email
        </a>
        <a href="<?php echo APP_PATH; ?>/email-log.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> Log Email
        </a>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>
<?php endif; ?> 