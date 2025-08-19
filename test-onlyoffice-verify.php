<?php
// Test di verifica rapida per OnlyOffice con Docker Desktop

require_once 'backend/config/onlyoffice.config.php';

header('Content-Type: application/json');

$tests = [];

// Test 1: Verifica configurazione URL
$tests['config'] = [
    'document_server' => OnlyOfficeConfig::getDocumentServerUrl(),
    'document_url_example' => OnlyOfficeConfig::getDocumentUrlForDS('test.docx'),
    'callback_url_example' => OnlyOfficeConfig::getCallbackUrl('test123'),
    'is_docker_desktop' => OnlyOfficeConfig::isDockerDesktop()
];

// Test 2: Verifica connessione a OnlyOffice
$healthUrl = OnlyOfficeConfig::getDocumentServerUrl() . 'healthcheck';
$ch = curl_init($healthUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$tests['healthcheck'] = [
    'url' => $healthUrl,
    'status' => $httpCode,
    'response' => $response,
    'error' => $error,
    'success' => $httpCode == 200 && trim($response) == 'true'
];

// Test 3: Verifica file di test
$testFile = OnlyOfficeConfig::DOCUMENTS_PATH . '/test_document.docx';
$tests['test_file'] = [
    'path' => $testFile,
    'exists' => file_exists($testFile),
    'size' => file_exists($testFile) ? filesize($testFile) : 0,
    'readable' => is_readable($testFile)
];

// Test 4: Verifica che usiamo host.docker.internal
$docUrl = OnlyOfficeConfig::getDocumentUrlForDS('test.docx');
$tests['host_verification'] = [
    'uses_host_docker_internal' => strpos($docUrl, 'host.docker.internal') !== false,
    'document_url' => $docUrl
];

// Risultato finale
$allPassed = 
    $tests['healthcheck']['success'] && 
    $tests['test_file']['exists'] && 
    $tests['host_verification']['uses_host_docker_internal'];

$result = [
    'all_tests_passed' => $allPassed,
    'tests' => $tests,
    'ready_to_use' => $allPassed,
    'test_url' => $allPassed ? 
        'http://localhost/piattaforma-collaborativa/test-onlyoffice-final-solution.php' : 
        null
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>