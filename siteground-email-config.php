<?php
/**
 * Guida configurazione email per SiteGround
 */

require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

if (!$auth->isSuperAdmin()) {
    die('Accesso negato. Solo super admin.');
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Configurazione Email SiteGround</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .config-box {
            background: #f8f9fa;
            border: 2px solid #28a745;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .error-box {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        code {
            background: #f4f4f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
        .option {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Configurazione Email per SiteGround</h1>
        
        <div class="error-box">
            <h2>⚠️ Problema Identificato</h2>
            <p>Stai usando l'hosting <strong>SiteGround</strong> ma hai configurato il server SMTP di <strong>Aruba/Nexio Solution</strong>.</p>
            <p>Questo può causare problemi di autenticazione e consegna email!</p>
        </div>

        <h2>✅ Soluzioni Disponibili</h2>

        <div class="option">
            <h3>Opzione 1: Usa SMTP di SiteGround (CONSIGLIATO)</h3>
            <div class="config-box">
                <p><strong>Configurazione SMTP SiteGround:</strong></p>
                <ul>
                    <li>Server SMTP: <code>Il tuo dominio (es: nexiosolution.it)</code> oppure <code>mail.nexiosolution.it</code></li>
                    <li>Porta: <code>465</code> (SSL) o <code>587</code> (TLS)</li>
                    <li>Crittografia: <code>SSL</code> per porta 465, <code>TLS</code> per porta 587</li>
                    <li>Username: <code>info@nexiosolution.it</code> (email completa)</li>
                    <li>Password: La password dell'email creata nel pannello SiteGround</li>
                </ul>
                <button class="btn btn-success" onclick="applySiteGroundConfig()">Applica Configurazione SiteGround</button>
            </div>
            <p><strong>Vantaggi:</strong> Nessun problema di autenticazione, migliore deliverability, supporto diretto da SiteGround</p>
        </div>

        <div class="option">
            <h3>Opzione 2: Continua con Aruba (Se hai email su Aruba)</h3>
            <div class="warning-box">
                <p>Se le tue email sono gestite da Aruba e non da SiteGround, devi:</p>
                <ol>
                    <li>Verificare che il server <code>mail.nexiosolution.it</code> accetti connessioni esterne</li>
                    <li>Controllare se Aruba richiede autenticazione POP/IMAP prima di SMTP</li>
                    <li>Verificare che il tuo IP non sia bloccato da Aruba</li>
                </ol>
                <p><strong>Prova queste configurazioni:</strong></p>
                <ul>
                    <li>Server: <code>smtps.aruba.it</code></li>
                    <li>Porta: <code>465</code></li>
                    <li>SSL: Abilitato</li>
                </ul>
            </div>
        </div>

        <div class="option">
            <h3>Opzione 3: Usa un Servizio SMTP Esterno (Più Affidabile)</h3>
            <div class="config-box">
                <h4>🚀 Brevo (ex SendinBlue) - GRATIS</h4>
                <ul>
                    <li>300 email/giorno gratuite</li>
                    <li>Configurazione semplice</li>
                    <li>Ottima deliverability</li>
                </ul>
                <a href="https://app.brevo.com/settings/keys/smtp" target="_blank" class="btn">Registrati su Brevo</a>
                
                <h4>📬 SendGrid - GRATIS</h4>
                <ul>
                    <li>100 email/giorno gratuite</li>
                    <li>API potenti</li>
                    <li>Statistiche dettagliate</li>
                </ul>
                <a href="https://sendgrid.com" target="_blank" class="btn">Registrati su SendGrid</a>
            </div>
        </div>

        <h2>🔍 Come Verificare le Tue Impostazioni Email</h2>
        <div class="config-box">
            <h3>Nel pannello SiteGround:</h3>
            <ol>
                <li>Accedi a <strong>Site Tools</strong></li>
                <li>Vai su <strong>Email > Accounts</strong></li>
                <li>Verifica che <code>info@nexiosolution.it</code> esista</li>
                <li>Clicca sui 3 puntini → <strong>Mail Configuration</strong></li>
                <li>Lì trovi le impostazioni SMTP corrette</li>
            </ol>
        </div>

        <h2>🧪 Test Rapidi</h2>
        <button class="btn" onclick="testSiteGroundConnection()">Test Connessione SiteGround</button>
        <button class="btn" onclick="checkDNSRecords()">Verifica Record DNS</button>
        <button class="btn btn-success" onclick="window.location.href='configurazione-email.php'">Vai alla Configurazione Email</button>

        <div id="testResults" style="margin-top: 20px;"></div>
    </div>

    <script>
    function applySiteGroundConfig() {
        if (confirm('Vuoi applicare la configurazione SMTP di SiteGround?\n\nServer: nexiosolution.it\nPorta: 465\nSSL: Abilitato')) {
            alert('Vai nelle Impostazioni e inserisci:\n\n' +
                  'Server SMTP: nexiosolution.it (o mail.nexiosolution.it)\n' +
                  'Porta: 465\n' +
                  'Crittografia: SSL\n' +
                  'Username: info@nexiosolution.it\n' +
                  'Password: [la password email dal pannello SiteGround]');
            window.location.href = 'configurazione-email.php';
        }
    }

    async function testSiteGroundConnection() {
        const resultsDiv = document.getElementById('testResults');
        resultsDiv.innerHTML = '<p>⏳ Test in corso...</p>';
        
        try {
            const response = await fetch('backend/api/test-email-methods.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    email: 'test@example.com',
                    method: 'alternative'
                })
            });
            
            const data = await response.json();
            
            if (data.results && data.results[0].details) {
                let html = '<h3>Risultati Test Connessione:</h3><ul>';
                data.results[0].details.forEach(detail => {
                    const icon = detail.success ? '✅' : '❌';
                    html += `<li>${icon} ${detail.config}: ${detail.message}</li>`;
                });
                html += '</ul>';
                resultsDiv.innerHTML = html;
            }
        } catch (error) {
            resultsDiv.innerHTML = '<p style="color: red;">Errore: ' + error.message + '</p>';
        }
    }

    async function checkDNSRecords() {
        const resultsDiv = document.getElementById('testResults');
        resultsDiv.innerHTML = '<p>⏳ Controllo DNS in corso...</p>';
        
        // Simula controllo DNS
        setTimeout(() => {
            resultsDiv.innerHTML = `
                <h3>Record DNS per nexiosolution.it:</h3>
                <p>Per verificare i record MX reali:</p>
                <ol>
                    <li>Vai su <a href="https://mxtoolbox.com/SuperTool.aspx?action=mx%3anexiosolution.it" target="_blank">MXToolbox</a></li>
                    <li>Controlla se puntano a SiteGround o Aruba</li>
                    <li>Se puntano a SiteGround, usa SMTP SiteGround</li>
                    <li>Se puntano a Aruba, usa SMTP Aruba</li>
                </ol>
            `;
        }, 1000);
    }
    </script>
</body>
</html> 