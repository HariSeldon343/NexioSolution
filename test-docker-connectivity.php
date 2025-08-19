<?php
/**
 * Test Docker Connectivity
 * Verifica che host.docker.internal sia raggiungibile dal container
 */

header('Content-Type: application/json');

$results = [];

// Test 1: Verifica se siamo in un container Docker
$inDocker = file_exists('/.dockerenv');
$results['in_docker'] = $inDocker;

// Test 2: Risoluzione DNS di host.docker.internal
$host = 'host.docker.internal';
$ip = gethostbyname($host);
$results['host_docker_internal'] = [
    'hostname' => $host,
    'resolved_ip' => $ip,
    'resolution_success' => ($ip !== $host)
];

// Test 3: Test connettività HTTP verso host
if ($ip !== $host) {
    $testUrl = "http://{$ip}/piattaforma-collaborativa/";
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $results['http_test'] = [
        'url' => $testUrl,
        'http_code' => $httpCode,
        'success' => ($httpCode > 0),
        'error' => $error
    ];
}

// Test 4: Info ambiente
$results['environment'] = [
    'php_os' => PHP_OS,
    'php_os_family' => PHP_OS_FAMILY,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'wsl_distro' => $_SERVER['WSL_DISTRO_NAME'] ?? null,
    'hostname' => gethostname()
];

// Test 5: Verifica percorso documenti
$docPath = '/mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx';
$results['document_test'] = [
    'path' => $docPath,
    'exists' => file_exists($docPath),
    'readable' => is_readable($docPath),
    'size' => file_exists($docPath) ? filesize($docPath) : 0
];

// Test 6: URL corretti per Docker Desktop
$results['correct_urls'] = [
    'document_url_for_container' => 'http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/[filename]',
    'callback_url_for_container' => 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php',
    'important_note' => 'Docker Desktop REQUIRES host.docker.internal, NOT localhost!'
];

// Risultato finale
$results['summary'] = [
    'docker_desktop_ready' => $results['host_docker_internal']['resolution_success'] ?? false,
    'recommendation' => $results['host_docker_internal']['resolution_success'] 
        ? 'Use host.docker.internal for all container-to-host communication'
        : 'Not running in Docker Desktop environment or host.docker.internal not available'
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>