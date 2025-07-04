<?php
/**
 * Dashboard funzionante della piattaforma Nexio
 */

// Abilita errori per debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carica configurazione
require_once __DIR__ . '/backend/config/config.php';

// Verifica autenticazione
$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    redirect(APP_PATH . '/login.php');
}

// Ottieni dati utente
$user = $auth->getUser();
$aziendaId = 1; // ID azienda di default

// Calcola statistiche
$stats = [];

try {
    // Statistiche base
    $stmt = db_query("SELECT COUNT(*) as count FROM aziende WHERE stato = 'attiva'");
    $stats['aziende'] = $stmt ? $stmt->fetch()['count'] : 0;

    $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE attivo = 1");
    $stats['documenti'] = $stmt ? $stmt->fetch()['count'] : 0;

    $stmt = db_query("SELECT COUNT(*) as count FROM eventi WHERE data_inizio > NOW()");
    $stats['eventi'] = $stmt ? $stmt->fetch()['count'] : 0;

    $stmt = db_query("SELECT COUNT(*) as count FROM utenti WHERE attivo = 1");
    $stats['utenti'] = $stmt ? $stmt->fetch()['count'] : 0;

    $stmt = db_query("SELECT COUNT(*) as count FROM tickets WHERE stato IN ('aperto', 'in_lavorazione')");
    $stats['tickets_aperti'] = $stmt ? $stmt->fetch()['count'] : 0;

    // Documenti recenti
    $stmt = db_query("
        SELECT d.id, d.titolo, d.codice, d.created_at,
               m.nome as modulo_nome
        FROM documenti d
        LEFT JOIN moduli_documento m ON d.modulo_id = m.id
        WHERE d.azienda_id = :azienda_id 
        AND d.attivo = 1 
        AND d.stato = 'pubblicato'
        ORDER BY d.created_at DESC
        LIMIT 5
    ", ['azienda_id' => $aziendaId]);

    $documenti_recenti = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Eventi prossimi
    $stmt = db_query("
        SELECT titolo, data_inizio, descrizione
        FROM eventi 
        WHERE data_inizio > NOW() AND azienda_id = :azienda_id
        ORDER BY data_inizio ASC 
        LIMIT 5
    ", ['azienda_id' => $aziendaId]);

    $eventi_prossimi = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Tickets recenti
    $stmt = db_query("
        SELECT t.id, t.titolo, t.stato, t.priorita, t.created_at,
               u.nome as utente_nome, u.cognome as utente_cognome
        FROM tickets t
        LEFT JOIN utenti u ON t.utente_id = u.id
        WHERE t.stato IN ('aperto', 'in_lavorazione')
        ORDER BY t.created_at DESC
        LIMIT 5
    ");

    $tickets_recenti = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (Exception $e) {
    // In caso di errore, usa valori di default
    $stats = ['aziende' => 0, 'documenti' => 0, 'eventi' => 0, 'utenti' => 0, 'tickets_aperti' => 0];
    $documenti_recenti = [];
    $eventi_prossimi = [];
    $tickets_recenti = [];
}

$pageTitle = 'Dashboard';
include dirname(__FILE__) . '/components/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-home"></i> Dashboard</h1>
    <p>Benvenuto, <?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?>!</p>
</div>

<!-- Statistiche -->
<div class="stats-grid">
    <div class="stat-card">
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <div style="background: #4299e1; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">
                🏢
            </div>
            <div>
                <div style="font-size: 28px; font-weight: bold; color: #2d3748;">
                    <?php echo $stats['aziende']; ?>
                </div>
                <div style="color: #718096; font-size: 14px;">Aziende Attive</div>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <div style="background: #48bb78; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">
                📄
            </div>
            <div>
                <div style="font-size: 28px; font-weight: bold; color: #2d3748;">
                    <?php echo $stats['documenti']; ?>
                </div>
                <div style="color: #718096; font-size: 14px;">Documenti</div>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <div style="background: #ed8936; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">
                📅
            </div>
            <div>
                <div style="font-size: 28px; font-weight: bold; color: #2d3748;">
                    <?php echo $stats['eventi']; ?>
                </div>
                <div style="color: #718096; font-size: 14px;">Eventi Futuri</div>
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <div style="background: #9f7aea; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">
                👥
            </div>
            <div>
                <div style="font-size: 28px; font-weight: bold; color: #2d3748;">
                    <?php echo $stats['utenti']; ?>
                </div>
                <div style="color: #718096; font-size: 14px;">Utenti</div>
            </div>
        </div>
    </div>
</div>

<!-- Sezioni principali -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
    
    <!-- Documenti recenti -->
    <div class="recent-items">
        <h2>📄 Documenti Recenti</h2>
        
        <?php if (empty($documenti_recenti)): ?>
            <p style="color: #718096; text-align: center; padding: 20px;">
                Nessun documento trovato
            </p>
        <?php else: ?>
            <?php foreach ($documenti_recenti as $doc): ?>
                <div class="recent-item">
                    <div>
                        <div style="font-weight: 600; color: #2d3748; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($doc['titolo']); ?>
                        </div>
                        <div style="font-size: 13px; color: #718096;">
                            <?php echo htmlspecialchars($doc['codice'] ?? 'N/A'); ?> •
                            <?php echo htmlspecialchars($doc['modulo_nome'] ?? 'Generale'); ?> •
                            <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                        </div>
                    </div>
                    <a href="<?php echo APP_PATH; ?>/documento-view.php?id=<?php echo $doc['id']; ?>" class="btn btn-secondary btn-small">
                        Visualizza
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo APP_PATH; ?>/documenti.php" class="btn btn-primary">
                <i class="fas fa-folder-open"></i> Tutti i Documenti
            </a>
                                <a href="<?php echo APP_PATH; ?>/editor-nexio-integrated.php" class="btn btn-success">
                <i class="fas fa-file-word"></i> Nuovo con Editor Nexio
            </a>
            <a href="<?php echo APP_PATH; ?>/test-onlyoffice-docker.php" class="btn btn-outline" style="background: white; color: #0078d4; border: 1px solid #0078d4;">
                <i class="fas fa-vial"></i> <span class="hide-mobile">Test OnlyOffice</span>
            </a>
        </div>
    </div>
    
    <!-- Eventi prossimi -->
    <div class="recent-items">
        <h2>📅 Eventi Prossimi</h2>
        
        <?php if (empty($eventi_prossimi)): ?>
            <p style="color: #718096; text-align: center; padding: 20px;">
                Nessun evento programmato
            </p>
        <?php else: ?>
            <?php foreach ($eventi_prossimi as $evento): ?>
                <div class="recent-item">
                    <div>
                        <div style="font-weight: 600; color: #2d3748; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($evento['titolo']); ?>
                        </div>
                        <div style="font-size: 13px; color: #718096;">
                            <?php echo date('d/m/Y H:i', strtotime($evento['data_inizio'])); ?>
                        </div>
                        <?php if (!empty($evento['descrizione'])): ?>
                            <div style="font-size: 12px; color: #a0aec0; margin-top: 3px;">
                                <?php echo htmlspecialchars(substr($evento['descrizione'], 0, 60)); ?>...
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?php echo APP_PATH; ?>/calendario.php" class="btn btn-primary">
                <i class="fas fa-calendar"></i> Vai al Calendario
            </a>
        </div>
    </div>
</div>

<!-- Tickets e azioni rapide -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
    
    <!-- Tickets aperti -->
    <div class="recent-items">
        <h2>🎫 Tickets Aperti</h2>
        
        <?php if (empty($tickets_recenti)): ?>
            <p style="color: #718096; text-align: center; padding: 20px;">
                Nessun ticket aperto
            </p>
        <?php else: ?>
            <?php foreach ($tickets_recenti as $ticket): ?>
                <div class="recent-item">
                    <div>
                        <div style="font-weight: 600; color: #2d3748; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($ticket['titolo']); ?>
                        </div>
                        <div style="font-size: 13px; color: #718096;">
                            <?php echo htmlspecialchars($ticket['utente_nome'] . ' ' . $ticket['utente_cognome']); ?> •
                            <?php echo ucfirst($ticket['priorita']); ?> •
                            <?php echo date('d/m/Y', strtotime($ticket['created_at'])); ?>
                        </div>
                    </div>
                    <span class="btn btn-secondary btn-small">
                        <?php echo ucfirst($ticket['stato']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?php echo APP_PATH; ?>/tickets.php" class="btn btn-primary">
                <i class="fas fa-ticket-alt"></i> Gestisci Tickets
            </a>
        </div>
    </div>
    
    <!-- Azioni rapide -->
    <div class="recent-items">
        <h2>⚡ Azioni Rapide</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
            <a href="<?php echo APP_PATH; ?>/editor-template-styled.php" class="btn btn-primary" style="text-align: center; padding: 20px;">
                <i class="fas fa-plus" style="display: block; font-size: 24px; margin-bottom: 10px;"></i>
                Nuovo Documento con Template
            </a>
            
            <a href="<?php echo APP_PATH; ?>/calendario-eventi.php?action=nuovo" class="btn btn-secondary" style="text-align: center; padding: 20px;">
                <i class="fas fa-calendar-plus" style="display: block; font-size: 24px; margin-bottom: 10px;"></i>
                Nuovo Evento
            </a>
            
            <a href="<?php echo APP_PATH; ?>/tickets.php?action=nuovo" class="btn btn-secondary" style="text-align: center; padding: 20px;">
                <i class="fas fa-life-ring" style="display: block; font-size: 24px; margin-bottom: 10px;"></i>
                Apri Ticket
            </a>
            
            <a href="<?php echo APP_PATH; ?>/gestione-template-styled.php" class="btn btn-primary" style="text-align: center; padding: 20px;">
                <i class="fas fa-layer-group" style="display: block; font-size: 24px; margin-bottom: 10px;"></i>
                Gestione Template
            </a>
        </div>
    </div>
</div>

</main>
</div>

<script>
// Script per migliorare l'esperienza utente
document.addEventListener('DOMContentLoaded', function() {
    // Aggiorna orario ogni minuto
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('it-IT');
        const dateString = now.toLocaleDateString('it-IT');
        
        // Se esiste un elemento per mostrare l'orario
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            timeElement.textContent = timeString + ' - ' + dateString;
        }
    }
    
    updateTime();
    setInterval(updateTime, 60000);
    
    // Animazione delle stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

</body>
</html> 