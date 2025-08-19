<?php
/**
 * Script di diagnostica per trovare la porta corretta di OnlyOffice
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>OnlyOffice Port Scanner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .port-test {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #ddd;
        }
        .port-test.success {
            border-left-color: #4CAF50;
            background: #f1f8f4;
        }
        .port-test.fail {
            border-left-color: #f44336;
            background: #fef5f5;
        }
        .status {
            font-weight: bold;
            margin-left: 10px;
        }
        .success .status { color: #4CAF50; }
        .fail .status { color: #f44336; }
        .details {
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .recommendation {
            background: #e3f2fd;
            border: 1px solid #2196F3;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .recommendation h3 {
            margin-top: 0;
            color: #1976D2;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 OnlyOffice Port Scanner</h1>
    
    <?php
    // Configurazioni da testare
    $ports = [
        ['url' => 'http://localhost:8080', 'desc' => 'HTTP porta 8080 (default alternativo)'],
        ['url' => 'http://localhost:8082', 'desc' => 'HTTP porta 8082 (configurata in docker-compose)'],
        ['url' => 'https://localhost:8443', 'desc' => 'HTTPS porta 8443 (SSL)'],
        ['url' => 'http://localhost:80', 'desc' => 'HTTP porta 80 (standard)'],
        ['url' => 'https://localhost:443', 'desc' => 'HTTPS porta 443 (SSL standard)'],
    ];
    
    $workingPorts = [];
    
    echo "<h2>Test delle porte OnlyOffice:</h2>";
    
    foreach ($ports as $port) {
        $url = $port['url'];
        $desc = $port['desc'];
        $healthcheckUrl = $url . '/healthcheck';
        $apiUrl = $url . '/web-apps/apps/api/documents/api.js';
        
        echo "<div class='port-test' id='port-" . parse_url($url, PHP_URL_PORT) . "'>";
        echo "<strong>Testing: $url</strong> - $desc";
        
        // Test healthcheck
        $ch = curl_init($healthcheckUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode == 200 && $response == 'true') {
            echo "<span class='status'>✅ FUNZIONA!</span>";
            $workingPorts[] = $url;
            
            // Test API availability
            $ch2 = curl_init($apiUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch2, CURLOPT_NOBODY, true);
            
            curl_exec($ch2);
            $apiHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            echo "<div class='details'>";
            echo "Healthcheck: OK (response: $response)<br>";
            echo "API JavaScript: " . ($apiHttpCode == 200 ? "✅ Disponibile" : "❌ Non disponibile (HTTP $apiHttpCode)") . "<br>";
            echo "URL API: <code>$apiUrl</code>";
            echo "</div>";
            
            echo "<script>document.getElementById('port-" . parse_url($url, PHP_URL_PORT) . "').className = 'port-test success';</script>";
        } else {
            echo "<span class='status'>❌ Non risponde</span>";
            echo "<div class='details'>";
            if ($error) {
                echo "Errore: $error<br>";
            }
            echo "HTTP Code: $httpCode<br>";
            echo "Response: " . ($response ? substr($response, 0, 100) : 'nessuna risposta');
            echo "</div>";
            
            echo "<script>document.getElementById('port-" . parse_url($url, PHP_URL_PORT) . "').className = 'port-test fail';</script>";
        }
        
        echo "</div>";
    }
    
    // Raccomandazioni
    if (!empty($workingPorts)) {
        ?>
        <div class='recommendation'>
            <h3>✅ OnlyOffice trovato!</h3>
            <p><strong>Porta funzionante:</strong> <?php echo $workingPorts[0]; ?></p>
            
            <h4>Aggiorna la configurazione:</h4>
            
            <p><strong>1. In backend/config/onlyoffice.config.php:</strong></p>
            <pre><?php
            $parsedUrl = parse_url($workingPorts[0]);
            $protocol = $parsedUrl['scheme'];
            $port = $parsedUrl['port'] ?? ($protocol == 'https' ? 443 : 80);
            
            echo "class OnlyOfficeConfig {
    const DOCUMENT_SERVER_URL = '$workingPorts[0]/';
    const DOCUMENT_SERVER_PORT = '$port';
    const USE_HTTPS = " . ($protocol == 'https' ? 'true' : 'false') . ";
    
    public static function getDocumentServerUrl() {
        return self::DOCUMENT_SERVER_URL;
    }
}";
            ?></pre>
            
            <p><strong>2. Nei file PHP che usano OnlyOffice:</strong></p>
            <pre>$onlyofficeUrl = '<?php echo $workingPorts[0]; ?>/';</pre>
        </div>
        <?php
    } else {
        ?>
        <div class='recommendation' style='background: #ffebee; border-color: #f44336;'>
            <h3>❌ OnlyOffice non trovato!</h3>
            <p>Nessuna porta risponde. Verificare che:</p>
            <ol>
                <li>Docker Desktop sia in esecuzione</li>
                <li>Il container OnlyOffice sia avviato: <code>docker ps</code></li>
                <li>Avviare OnlyOffice: <code>cd onlyoffice && docker-compose up -d</code></li>
                <li>Controllare i log: <code>docker logs nexio-onlyoffice</code></li>
            </ol>
        </div>
        <?php
    }
    ?>
    
    <h2>Informazioni Docker:</h2>
    <pre><?php
    echo "Containers attivi:\n";
    $output = shell_exec('docker ps --filter "name=nexio-" 2>&1');
    echo htmlspecialchars($output ?: 'Docker non disponibile o nessun container trovato');
    ?></pre>
</body>
</html>