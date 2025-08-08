<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Ensure session has email
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    $_SESSION['user_email'] = $_SESSION['email'] ?? 'admin@nexio.it';
}

// Crea tabella se non esiste
db_query("
    CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        to_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        is_html TINYINT(1) DEFAULT 1,
        status ENUM('pending', 'viewed', 'sent') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        viewed_at TIMESTAMP NULL,
        INDEX idx_status (status),
        INDEX idx_to (to_email),
        INDEX idx_created (created_at)
    )
");

// Crea tabella notifiche in-app se non esiste
db_query("
    CREATE TABLE IF NOT EXISTS notifiche (
        id INT AUTO_INCREMENT PRIMARY KEY,
        utente_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        titolo VARCHAR(255) NOT NULL,
        messaggio TEXT,
        url VARCHAR(255),
        letta TINYINT(1) DEFAULT 0,
        creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        letta_il TIMESTAMP NULL,
        FOREIGN KEY (utente_id) REFERENCES utenti(id),
        INDEX idx_utente (utente_id),
        INDEX idx_letta (letta),
        INDEX idx_creata (creata_il)
    )
");

// Segna come letta se richiesto
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    db_query("
        UPDATE email_notifications 
        SET status = 'viewed', viewed_at = NOW() 
        WHERE id = ? AND to_email = ?
    ", [$id, $_SESSION['user_email']]);
    
    header('Location: notifiche-email.php');
    exit;
}

// Carica notifiche email per l'utente corrente
$notifications = [];
$stmt = db_query("
    SELECT * FROM email_notifications 
    WHERE to_email = ? 
    ORDER BY created_at DESC 
    LIMIT 50
", [$_SESSION['user_email']]);

while ($row = $stmt->fetch()) {
    $notifications[] = $row;
}

// Conta notifiche non lette
$unreadCount = db_query("
    SELECT COUNT(*) FROM email_notifications 
    WHERE to_email = ? AND status = 'pending'
", [$_SESSION['user_email']])->fetchColumn();

require_once 'components/header.php';
?>

<style>
.notification-card {
    transition: all 0.3s ease;
    cursor: pointer;
}
.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.notification-unread {
    background-color: #f0f8ff;
    border-left: 4px solid #0d6efd;
}
.notification-badge {
    position: absolute;
    top: 10px;
    right: 10px;
}
.email-preview {
    max-height: 100px;
    overflow: hidden;
    position: relative;
}
.email-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 30px;
    background: linear-gradient(to bottom, transparent, white);
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Notifiche Email</h1>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-primary"><?php echo $unreadCount; ?> non lette</span>
                    <?php endif; ?>
                </div>
                <div>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Aggiorna
                    </button>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Nessuna notifica email al momento.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="col-lg-6 mb-3">
                            <div class="card notification-card <?php echo $notification['status'] === 'pending' ? 'notification-unread' : ''; ?>" 
                                 onclick="showEmailDetail(<?php echo $notification['id']; ?>)">
                                <div class="card-body">
                                    <?php if ($notification['status'] === 'pending'): ?>
                                        <span class="badge bg-primary notification-badge">Nuova</span>
                                    <?php endif; ?>
                                    
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($notification['subject']); ?>
                                    </h5>
                                    
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                        <?php if ($notification['status'] === 'viewed'): ?>
                                            | <i class="bi bi-eye"></i> Letta il <?php echo date('d/m/Y H:i', strtotime($notification['viewed_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="email-preview">
                                        <?php 
                                        $preview = strip_tags($notification['body']);
                                        echo htmlspecialchars(substr($preview, 0, 200)) . '...';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal per visualizzare email -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailModalTitle">Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="emailModalBody">
                <!-- Contenuto email -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                <a href="#" id="markReadBtn" class="btn btn-primary">Segna come letta</a>
            </div>
        </div>
    </div>
</div>

<script>
// Ensure Bootstrap is loaded
if (typeof bootstrap === 'undefined') {
    console.error('Bootstrap not loaded!');
}

// Clean up any existing modals on page load
document.addEventListener('DOMContentLoaded', function() {
    // Remove any existing modal backdrops
    const existingBackdrops = document.querySelectorAll('.modal-backdrop');
    existingBackdrops.forEach(backdrop => backdrop.remove());
    
    // Remove show class from any open modals
    const openModals = document.querySelectorAll('.modal.show');
    openModals.forEach(modal => {
        modal.classList.remove('show');
        modal.style.display = 'none';
    });
    
    // Reset body classes
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
});

// Global modal instance
let emailModalInstance = null;

// Initialize modal on page load
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('emailModal');
    if (modalElement) {
        emailModalInstance = new bootstrap.Modal(modalElement, {
            backdrop: true,  // Allow clicking outside to close
            keyboard: true   // Allow ESC key to close
        });
        
        // Add event listener for close button
        modalElement.addEventListener('hidden.bs.modal', function () {
            document.getElementById('emailModalBody').innerHTML = '';
            document.getElementById('emailModalTitle').textContent = 'Email';
        });
    }
});

function showEmailDetail(id) {
    try {
        // Cerca la notifica nell'array
        const notifications = <?php echo json_encode($notifications); ?>;
        const notification = notifications.find(n => n.id == id);
        
        if (notification) {
            document.getElementById('emailModalTitle').textContent = notification.subject;
            
            // Se è HTML, mostra direttamente, altrimenti converti newline in <br>
            if (notification.is_html == 1) {
                document.getElementById('emailModalBody').innerHTML = notification.body;
            } else {
                document.getElementById('emailModalBody').innerHTML = notification.body.replace(/\n/g, '<br>');
            }
            
            // Imposta link per segnare come letta
            document.getElementById('markReadBtn').href = '?mark_read=1&id=' + id;
            if (notification.status !== 'pending') {
                document.getElementById('markReadBtn').style.display = 'none';
            } else {
                document.getElementById('markReadBtn').style.display = 'inline-block';
            }
            
            // Mostra modal usando l'istanza globale
            if (emailModalInstance) {
                emailModalInstance.show();
            } else {
                console.error('Modal instance not initialized');
            }
        }
    } catch (error) {
        console.error('Error showing email detail:', error);
        alert('Errore nel visualizzare l\'email. Ricarica la pagina.');
    }
}

// Function to close modal programmatically if needed
function closeEmailModal() {
    if (emailModalInstance) {
        emailModalInstance.hide();
    }
}

// Auto-refresh ogni 30 secondi
setInterval(function() {
    const badge = document.querySelector('.badge.bg-primary');
    if (badge) {
        fetch('backend/api/check-notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.unread > 0 && data.unread != <?php echo $unreadCount; ?>) {
                    location.reload();
                }
            });
    }
}, 30000);
</script>

<?php require_once 'components/footer.php'; ?>