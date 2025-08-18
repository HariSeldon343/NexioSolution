<?php
/**
 * OnlyOffice Docker Setup Helper
 * Configura e verifica l'integrazione con OnlyOffice Document Server in Docker
 */

require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Colori per output CLI
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[1;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "{$blue}";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         OnlyOffice Docker Setup & Configuration Check         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "{$reset}\n";

// Configurazione attuale
echo "{$yellow}CONFIGURAZIONE ATTUALE:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";

$config = [
    'Server URL' => ONLYOFFICE_DS_PUBLIC_URL,
    'Internal URL' => ONLYOFFICE_DS_INTERNAL_URL,
    'JWT Enabled' => ONLYOFFICE_JWT_ENABLED ? 'YES' : 'NO',
    'JWT Secret' => substr(ONLYOFFICE_JWT_SECRET, 0, 20) . '...',
    'JWT Header' => ONLYOFFICE_JWT_HEADER,
    'Callback URL' => ONLYOFFICE_CALLBACK_URL,
];

foreach ($config as $key => $value) {
    $color = ($key === 'JWT Enabled' && $value === 'NO') ? $red : $green;
    echo sprintf("%-15s: {$color}%s{$reset}\n", $key, $value);
}

echo "\n{$yellow}DOCKER SETUP COMMANDS:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";

// Comando Docker consigliato
$dockerCommand = sprintf(
    "docker run -d -p 8082:80 --name onlyoffice-ds \\\n" .
    "  -e JWT_ENABLED=true \\\n" .
    "  -e JWT_SECRET=%s \\\n" .
    "  -e JWT_HEADER=%s \\\n" .
    "  onlyoffice/documentserver",
    ONLYOFFICE_JWT_SECRET,
    ONLYOFFICE_JWT_HEADER
);

echo "{$blue}1. Avvia OnlyOffice Document Server:{$reset}\n";
echo "{$green}" . $dockerCommand . "{$reset}\n\n";

echo "{$blue}2. Verifica che il container sia in esecuzione:{$reset}\n";
echo "{$green}docker ps | grep onlyoffice-ds{$reset}\n\n";

echo "{$blue}3. Controlla i log del container:{$reset}\n";
echo "{$green}docker logs onlyoffice-ds{$reset}\n\n";

// Test di connessione
echo "\n{$yellow}TEST DI CONNESSIONE:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";

// Test 1: Verifica se OnlyOffice è raggiungibile
echo "Testing " . ONLYOFFICE_DS_PUBLIC_URL . " ... ";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, ONLYOFFICE_DS_PUBLIC_URL . '/web-apps/apps/api/documents/api.js');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "{$green}✓ OnlyOffice è raggiungibile!{$reset}\n";
} else {
    echo "{$red}✗ OnlyOffice non raggiungibile (HTTP $httpCode){$reset}\n";
    echo "{$yellow}  Assicurati che Docker sia in esecuzione con il comando sopra{$reset}\n";
}

// Test 2: Verifica JWT
echo "\nTesting JWT generation ... ";
if (function_exists('generateJWT')) {
    try {
        $testPayload = [
            'document' => ['key' => 'test123'],
            'editorConfig' => ['user' => ['id' => '1', 'name' => 'Test']],
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $token = generateJWT($testPayload);
        if ($token) {
            echo "{$green}✓ JWT generation works!{$reset}\n";
            
            // Verifica il token
            $verified = verifyJWT($token);
            if ($verified) {
                echo "{$green}✓ JWT verification works!{$reset}\n";
            } else {
                echo "{$red}✗ JWT verification failed{$reset}\n";
            }
        }
    } catch (Exception $e) {
        echo "{$red}✗ Error: " . $e->getMessage() . "{$reset}\n";
    }
} else {
    echo "{$red}✗ JWT functions not found{$reset}\n";
}

// Test 3: Verifica tabelle database
echo "\n{$yellow}DATABASE CHECK:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";

$tables = [
    'documenti' => 'Documents table',
    'documenti_versioni_extended' => 'Version tracking',
    'onlyoffice_sessions' => 'Active sessions',
    'document_activity_log' => 'Activity logging'
];

foreach ($tables as $table => $description) {
    try {
        $stmt = db_connection()->query("SHOW TABLES LIKE '$table'");
        if ($stmt && $stmt->rowCount() > 0) {
            echo "{$green}✓{$reset} $table - $description\n";
        } else {
            echo "{$red}✗{$reset} $table - $description {$yellow}(run migrations){$reset}\n";
        }
    } catch (Exception $e) {
        echo "{$red}✗{$reset} $table - Error checking table\n";
    }
}

// File .env check
echo "\n{$yellow}ENVIRONMENT FILE:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";

if (file_exists('.env')) {
    echo "{$green}✓ .env file exists{$reset}\n";
    
    // Check if JWT secret is in .env
    $envContent = file_get_contents('.env');
    if (strpos($envContent, 'ONLYOFFICE_JWT_SECRET') !== false) {
        echo "{$green}✓ JWT secret configured in .env{$reset}\n";
    } else {
        echo "{$yellow}⚠ JWT secret not found in .env{$reset}\n";
    }
} else {
    echo "{$yellow}⚠ .env file not found{$reset}\n";
    echo "  Copy .env.example to .env and configure it:\n";
    echo "  {$green}cp .env.example .env{$reset}\n";
}

// Riepilogo e prossimi passi
echo "\n{$blue}";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      PROSSIMI PASSI                         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "{$reset}\n";

$steps = [];

if ($httpCode !== 200) {
    $steps[] = "1. Avvia OnlyOffice Docker container con il comando sopra";
}

if (!ONLYOFFICE_JWT_ENABLED) {
    $steps[] = "2. Abilita JWT in backend/config/onlyoffice.config.php";
}

if (strpos(ONLYOFFICE_JWT_SECRET, 'nexio-secret-key-2025') !== false) {
    $steps[] = "3. IMPORTANTE: Cambia il JWT secret con uno sicuro!";
    $steps[] = "   Genera con: openssl rand -hex 32";
}

if (!file_exists('.env')) {
    $steps[] = "4. Crea il file .env dalla copia di .env.example";
}

if (empty($steps)) {
    echo "{$green}✓ Tutto configurato correttamente!{$reset}\n\n";
    echo "Puoi ora:\n";
    echo "1. Aprire un documento: {$blue}http://localhost/piattaforma-collaborativa/onlyoffice-editor.php?id=1{$reset}\n";
    echo "2. Testare la configurazione: {$blue}http://localhost/piattaforma-collaborativa/test-onlyoffice-integration.php{$reset}\n";
} else {
    foreach ($steps as $step) {
        echo "{$yellow}$step{$reset}\n";
    }
}

echo "\n{$blue}COMANDI UTILI DOCKER:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";
echo "Stato:    {$green}docker ps | grep onlyoffice{$reset}\n";
echo "Logs:     {$green}docker logs onlyoffice-ds{$reset}\n";
echo "Restart:  {$green}docker restart onlyoffice-ds{$reset}\n";
echo "Stop:     {$green}docker stop onlyoffice-ds{$reset}\n";
echo "Remove:   {$green}docker rm onlyoffice-ds{$reset}\n";

echo "\n{$blue}TEST LINKS:{$reset}\n";
echo "─────────────────────────────────────────────────────────\n";
echo "Quick Test:       {$green}http://localhost/piattaforma-collaborativa/test-onlyoffice-quick.php{$reset}\n";
echo "Full Test:        {$green}http://localhost/piattaforma-collaborativa/test-onlyoffice-jwt.php{$reset}\n";
echo "Integration Test: {$green}http://localhost/piattaforma-collaborativa/test-onlyoffice-integration.php{$reset}\n";
echo "Open Editor:      {$green}http://localhost/piattaforma-collaborativa/onlyoffice-editor.php?id=1{$reset}\n";

echo "\n";
?>