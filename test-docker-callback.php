<?php
/**
 * Test Docker Callback URL Configuration
 * Verifica che il callback URL sia configurato correttamente per Docker
 */

require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Docker Callback Configuration</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            max-width: 900px; 
            margin: 40px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 10px;
        }
        .success { 
            color: #4CAF50; 
            font-weight: bold; 
        }
        .error { 
            color: #f44336; 
            font-weight: bold; 
        }
        .warning { 
            color: #FF9800; 
            font-weight: bold; 
        }
        .info { 
            color: #2196F3; 
        }
        .config-box {
            background: #f9f9f9;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .config-item {
            margin: 10px 0;
            display: flex;
            align-items: center;
        }
        .config-label {
            font-weight: 600;
            min-width: 200px;
            color: #555;
        }
        .config-value {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #e8f5e9;
            padding: 5px 10px;
            border-radius: 4px;
            word-break: break-all;
        }
        .docker-command {
            background: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: 'Consolas', 'Monaco', monospace;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .test-result {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .test-success {
            background: #e8f5e9;
            border: 1px solid #4CAF50;
        }
        .test-error {
            background: #ffebee;
            border: 1px solid #f44336;
        }
        .test-warning {
            background: #fff3e0;
            border: 1px solid #FF9800;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .correct {
            background: #e8f5e9;
        }
        .incorrect {
            background: #ffebee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐳 Docker Callback URL Test</h1>
        
        <div class="config-box">
            <h2>Configurazione Attuale</h2>
            
            <div class="config-item">
                <span class="config-label">OnlyOffice Server URL:</span>
                <span class="config-value"><?php echo htmlspecialchars(ONLYOFFICE_DS_PUBLIC_URL); ?></span>
            </div>
            
            <div class="config-item">
                <span class="config-label">Callback URL:</span>
                <span class="config-value"><?php echo htmlspecialchars(ONLYOFFICE_CALLBACK_URL); ?></span>
            </div>
            
            <?php
            // Analizza il callback URL
            $callbackParts = parse_url(ONLYOFFICE_CALLBACK_URL);
            $callbackHost = $callbackParts['host'] ?? '';
            
            $isCorrect = ($callbackHost === 'host.docker.internal');
            ?>
            
            <div class="config-item">
                <span class="config-label">Callback Host:</span>
                <span class="config-value <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                    <?php echo htmlspecialchars($callbackHost); ?>
                </span>
                <?php if ($isCorrect): ?>
                    <span class="success icon">✅</span>
                <?php else: ?>
                    <span class="error icon">❌</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($isCorrect): ?>
            <div class="test-result test-success">
                <h3><span class="icon">✅</span>Configurazione Corretta!</h3>
                <p>Il callback URL è configurato correttamente per Docker Desktop su Windows.</p>
                <p>OnlyOffice Document Server nel container potrà comunicare con la tua applicazione usando <strong>host.docker.internal</strong>.</p>
            </div>
        <?php else: ?>
            <div class="test-result test-error">
                <h3><span class="icon">❌</span>Configurazione Errata!</h3>
                <p>Il callback URL usa <strong><?php echo htmlspecialchars($callbackHost); ?></strong> invece di <strong>host.docker.internal</strong>.</p>
                <p>Da dentro il container Docker, <?php echo htmlspecialchars($callbackHost); ?> non sarà raggiungibile!</p>
                
                <h4>Soluzione:</h4>
                <p>Il callback URL dovrebbe essere:</p>
                <div class="config-value" style="margin: 10px 0;">
                    http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php
                </div>
            </div>
        <?php endif; ?>
        
        <div class="config-box">
            <h2>🐳 Comando Docker Corretto</h2>
            <p>Usa questo comando per avviare OnlyOffice con JWT:</p>
            
            <div class="docker-command">docker run -d -p 8082:80 --name onlyoffice-ds \
  -e JWT_ENABLED=true \
  -e JWT_SECRET=<?php echo htmlspecialchars(ONLYOFFICE_JWT_SECRET); ?> \
  -e JWT_HEADER=<?php echo htmlspecialchars(ONLYOFFICE_JWT_HEADER); ?> \
  onlyoffice/documentserver</div>
        </div>
        
        <div class="config-box">
            <h2>📋 Checklist di Verifica</h2>
            <table>
                <tr>
                    <th>Componente</th>
                    <th>Valore Atteso</th>
                    <th>Valore Attuale</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>JWT Enabled</td>
                    <td>true</td>
                    <td><?php echo ONLYOFFICE_JWT_ENABLED ? 'true' : 'false'; ?></td>
                    <td><?php echo ONLYOFFICE_JWT_ENABLED ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></td>
                </tr>
                <tr>
                    <td>Server URL</td>
                    <td>http://localhost:8082</td>
                    <td><?php echo htmlspecialchars(ONLYOFFICE_DS_PUBLIC_URL); ?></td>
                    <td><?php echo (ONLYOFFICE_DS_PUBLIC_URL === 'http://localhost:8082') ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>'; ?></td>
                </tr>
                <tr>
                    <td>Callback Host</td>
                    <td>host.docker.internal</td>
                    <td><?php echo htmlspecialchars($callbackHost); ?></td>
                    <td><?php echo $isCorrect ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></td>
                </tr>
                <tr>
                    <td>JWT Secret Length</td>
                    <td>&gt;= 32 chars</td>
                    <td><?php echo strlen(ONLYOFFICE_JWT_SECRET); ?> chars</td>
                    <td><?php echo (strlen(ONLYOFFICE_JWT_SECRET) >= 32) ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="config-box">
            <h2>🔍 Test di Connettività</h2>
            
            <?php
            // Test OnlyOffice server
            echo "<p>Testing OnlyOffice Server (" . ONLYOFFICE_DS_PUBLIC_URL . ")...</p>";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, ONLYOFFICE_DS_PUBLIC_URL . '/web-apps/apps/api/documents/api.js');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo '<div class="test-result test-success">';
                echo '<span class="icon">✅</span> OnlyOffice Document Server è raggiungibile!';
                echo '</div>';
            } else {
                echo '<div class="test-result test-warning">';
                echo '<span class="icon">⚠️</span> OnlyOffice Document Server non raggiungibile (HTTP ' . $httpCode . ')';
                echo '<p>Assicurati che il container Docker sia in esecuzione.</p>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="config-box">
            <h2>📝 Note Importanti</h2>
            <ul>
                <li><strong>host.docker.internal</strong> è un hostname speciale che Docker Desktop su Windows/Mac fornisce per permettere ai container di raggiungere l'host.</li>
                <li>Su Linux, potresti dover usare <code>--add-host=host.docker.internal:host-gateway</code> nel comando docker run.</li>
                <li>Il JWT secret nel comando Docker DEVE essere identico a quello configurato in PHP.</li>
                <li>La porta 8082 deve essere libera sul tuo sistema.</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 8px;">
            <h3>🚀 Prossimi Passi</h3>
            <ol>
                <li>Avvia il container Docker con il comando sopra</li>
                <li>Verifica che OnlyOffice sia raggiungibile su http://localhost:8082</li>
                <li>Apri un documento: <a href="onlyoffice-editor.php?id=1">onlyoffice-editor.php?id=1</a></li>
                <li>Monitora i log: <code>docker logs -f onlyoffice-ds</code></li>
            </ol>
        </div>
    </div>
</body>
</html>