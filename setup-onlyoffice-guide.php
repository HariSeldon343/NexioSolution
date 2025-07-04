<?php
/**
 * Guida Setup OnlyOffice
 * Istruzioni complete per configurare OnlyOffice Document Server
 */

session_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup OnlyOffice - Nexio Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
            color: #333;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #0078d4;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 18px;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #0078d4;
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .section h3 {
            color: #495057;
            margin: 25px 0 15px 0;
            font-size: 18px;
        }
        
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
        }
        
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #17a2b8;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        
        .step {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #0078d4;
        }
        
        .step-number {
            background: #0078d4;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0078d4;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 10px 5px;
            transition: all 0.2s;
        }
        
        .btn:hover {
            background: #106ebe;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        ol, ul {
            margin: 15px 0;
            padding-left: 25px;
        }
        
        li {
            margin: 8px 0;
        }
        
        .tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        
        .tab.active {
            background: white;
            border-bottom-color: #0078d4;
            color: #0078d4;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
            padding: 25px;
            background: white;
            border-radius: 0 0 8px 8px;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-word"></i> Setup OnlyOffice Document Server</h1>
            <p>Guida completa per configurare OnlyOffice con il sistema template Nexio</p>
        </div>

        <!-- Panoramica -->
        <div class="section">
            <h2><i class="fas fa-info-circle"></i> Panoramica</h2>
            <p>OnlyOffice Document Server fornisce un editor di documenti completo simile a Microsoft Word, con supporto nativo per header, footer, tabelle, immagini e molto altro.</p>
            
            <div class="info">
                <strong><i class="fas fa-lightbulb"></i> Vantaggi OnlyOffice:</strong>
                <ul>
                    <li>✅ Editor simile a Microsoft Word</li>
                    <li>✅ Supporto nativo header/footer</li>
                    <li>✅ Collaborative editing in tempo reale</li>
                    <li>✅ Supporto completo formato DOCX</li>
                    <li>✅ API completa per integrazione</li>
                </ul>
            </div>
        </div>

        <!-- Opzioni di Setup -->
        <div class="section">
            <h2><i class="fas fa-cogs"></i> Opzioni di Setup</h2>
            
            <div class="tabs">
                <div class="tab active" onclick="showTab('cloud')">
                    <i class="fas fa-cloud"></i> OnlyOffice Cloud
                </div>
                <div class="tab" onclick="showTab('docker')">
                    <i class="fab fa-docker"></i> Docker (Locale)
                </div>
                <div class="tab" onclick="showTab('server')">
                    <i class="fas fa-server"></i> Server Dedicato
                </div>
            </div>

            <!-- Cloud Setup -->
            <div class="tab-content active" id="cloud">
                <h3>🌐 Setup OnlyOffice Cloud (Raccomandato per sviluppo)</h3>
                
                <div class="success">
                    <strong>✅ Più semplice e veloce per iniziare</strong><br>
                    Perfetto per sviluppo e test. Include già il Document Server configurato.
                </div>

                <div class="step">
                    <span class="step-number">1</span>
                    <strong>Registra account OnlyOffice Cloud</strong>
                    <ul>
                        <li>Vai su <a href="https://www.onlyoffice.com/cloud-office.aspx" target="_blank">OnlyOffice Cloud</a></li>
                        <li>Crea un account gratuito (30 giorni di prova)</li>
                        <li>Ottieni l'URL del tuo Document Server cloud</li>
                    </ul>
                </div>

                <div class="step">
                    <span class="step-number">2</span>
                    <strong>Configura l'editor</strong>
                    <p>Nel file <code>editor-onlyoffice-integrated.php</code>, sostituisci la CDN con il tuo server:</p>
                    <div class="code-block">
// Sostituisci questa riga:
&lt;script src="https://documentserver.onlyoffice.com/web-apps/apps/api/documents/api.js"&gt;&lt;/script&gt;

// Con:
&lt;script src="https://[TUO-DOMINIO].onlyoffice.com/web-apps/apps/api/documents/api.js"&gt;&lt;/script&gt;
                    </div>
                </div>

                <div class="step">
                    <span class="step-number">3</span>
                    <strong>Test immediato</strong>
                    <p>L'editor è pronto! Puoi testarlo subito:</p>
                    <a href="editor-onlyoffice-integrated.php?dev=test" class="btn btn-success" target="_blank">
                        <i class="fas fa-play"></i> Testa Editor OnlyOffice
                    </a>
                </div>
            </div>

            <!-- Docker Setup -->
            <div class="tab-content" id="docker">
                <h3>🐳 Setup Docker (Locale)</h3>
                
                <div class="info">
                    <strong>💡 Ideale per sviluppo locale</strong><br>
                    Esegue OnlyOffice sul tuo computer senza dipendenze esterne.
                </div>

                <div class="step">
                    <span class="step-number">1</span>
                    <strong>Installa Docker</strong>
                    <ul>
                        <li>Scarica <a href="https://www.docker.com/products/docker-desktop" target="_blank">Docker Desktop</a></li>
                        <li>Installa e avvia Docker</li>
                        <li>Verifica: <code>docker --version</code></li>
                    </ul>
                </div>

                <div class="step">
                    <span class="step-number">2</span>
                    <strong>Esegui OnlyOffice Document Server</strong>
                    <div class="code-block">
# Scarica e avvia OnlyOffice Document Server
docker run -i -t -d -p 8080:80 --restart=always \
    -v /app/onlyoffice/DocumentServer/logs:/var/log/onlyoffice \
    -v /app/onlyoffice/DocumentServer/data:/var/www/onlyoffice/Data \
    onlyoffice/documentserver
                    </div>
                </div>

                <div class="step">
                    <span class="step-number">3</span>
                    <strong>Configura l'editor</strong>
                    <p>Aggiorna l'URL del Document Server:</p>
                    <div class="code-block">
// Nel file editor-onlyoffice-integrated.php:
&lt;script src="http://localhost:8080/web-apps/apps/api/documents/api.js"&gt;&lt;/script&gt;
                    </div>
                </div>

                <div class="step">
                    <span class="step-number">4</span>
                    <strong>Verifica installazione</strong>
                    <ul>
                        <li>Visita: <a href="http://localhost:8080" target="_blank">http://localhost:8080</a></li>
                        <li>Dovresti vedere la pagina di benvenuto OnlyOffice</li>
                    </ul>
                </div>
            </div>

            <!-- Server Setup -->
            <div class="tab-content" id="server">
                <h3>🖥️ Setup Server Dedicato</h3>
                
                <div class="warning">
                    <strong>⚠️ Setup avanzato</strong><br>
                    Richiede conoscenze di amministrazione server Linux.
                </div>

                <div class="step">
                    <span class="step-number">1</span>
                    <strong>Requisiti server</strong>
                    <ul>
                        <li>Ubuntu 20.04+ o CentOS 8+</li>
                        <li>4GB RAM minimo (8GB raccomandato)</li>
                        <li>2 CPU cores</li>
                        <li>10GB spazio disco</li>
                    </ul>
                </div>

                <div class="step">
                    <span class="step-number">2</span>
                    <strong>Installa OnlyOffice Document Server</strong>
                    <div class="code-block">
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y wget

# Aggiungi repository OnlyOffice
wget -qO - https://download.onlyoffice.com/GPG-KEY-ONLYOFFICE | sudo apt-key add -
echo "deb https://download.onlyoffice.com/repo/debian squeeze main" | sudo tee /etc/apt/sources.list.d/onlyoffice.list

# Installa
sudo apt-get update
sudo apt-get install -y onlyoffice-documentserver
                    </div>
                </div>

                <div class="step">
                    <span class="step-number">3</span>
                    <strong>Configura SSL (raccomandato)</strong>
                    <div class="code-block">
# Installa certificato SSL
sudo apt-get install -y certbot
sudo certbot certonly --standalone -d tuodominio.com

# Configura OnlyOffice con SSL
sudo /usr/bin/documentserver-ssl-setup.sh tuodominio.com
                    </div>
                </div>
            </div>
        </div>

        <!-- Configurazione Template -->
        <div class="section">
            <h2><i class="fas fa-file-contract"></i> Integrazione Template System</h2>
            
            <div class="step">
                <span class="step-number">1</span>
                <strong>Test sistema template</strong>
                <p>Prima verifica che il sistema template funzioni:</p>
                <a href="setup-test-session.php" class="btn" target="_blank">
                    <i class="fas fa-cog"></i> Setup Test Session
                </a>
                <a href="test-template-api.php" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-flask"></i> Test Template API
                </a>
            </div>

            <div class="step">
                <span class="step-number">2</span>
                <strong>Testa editor integrato</strong>
                <p>Una volta configurato OnlyOffice, testa l'editor:</p>
                <a href="editor-onlyoffice-integrated.php?dev=test" class="btn btn-success" target="_blank">
                    <i class="fas fa-file-word"></i> Apri Editor OnlyOffice
                </a>
            </div>

            <div class="info">
                <strong><i class="fas fa-info-circle"></i> Funzionalità template:</strong>
                <ul>
                    <li>✅ Header/footer automatici dall'azienda</li>
                    <li>✅ Logo e informazioni aziendali</li>
                    <li>✅ Variabili dinamiche (date, pagine, etc.)</li>
                    <li>✅ Template personalizzabili per ogni azienda</li>
                    <li>✅ Salvataggio automatico in database</li>
                </ul>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="section">
            <h2><i class="fas fa-tools"></i> Risoluzione Problemi</h2>
            
            <h3>❌ "Document Server not available"</h3>
            <ul>
                <li>Verifica che il Document Server sia in esecuzione</li>
                <li>Controlla l'URL del server nell'editor</li>
                <li>Verifica le configurazioni CORS</li>
            </ul>

            <h3>❌ "Template not loading"</h3>
            <ul>
                <li>Esegui il setup della sessione test</li>
                <li>Verifica che esistano template nel database</li>
                <li>Controlla i log PHP per errori API</li>
            </ul>

            <h3>❌ "Save not working"</h3>
            <ul>
                <li>Verifica permessi cartella <code>documents/onlyoffice/</code></li>
                <li>Controlla callback URL nelle configurazioni</li>
                <li>Verifica connessione database</li>
            </ul>

            <div class="warning">
                <strong><i class="fas fa-bug"></i> Debug Mode:</strong><br>
                Aggiungi <code>?debug=1</code> all'URL dell'editor per abilitare logging dettagliato nella console browser.
            </div>
        </div>

        <!-- Link utili -->
        <div class="section">
            <h2><i class="fas fa-link"></i> Link Utili</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <h3>📚 Documentazione</h3>
                    <ul>
                        <li><a href="https://api.onlyoffice.com/" target="_blank">OnlyOffice API Docs</a></li>
                        <li><a href="https://github.com/ONLYOFFICE/DocumentServer" target="_blank">GitHub Repository</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3>🧪 Test e Debug</h3>
                    <ul>
                        <li><a href="setup-test-session.php">Setup Test Session</a></li>
                        <li><a href="test-template-api.php">Test Template API</a></li>
                        <li><a href="editor-onlyoffice-integrated.php?dev=test">Editor OnlyOffice</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3>⚙️ Configurazione</h3>
                    <ul>
                        <li><a href="gestione-moduli-template.php">Gestione Template</a></li>
                        <li><a href="backend/config/config.php">Config Sistema</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Nasconde tutti i tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Rimuove active da tutti i tab
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Mostra il tab selezionato
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>