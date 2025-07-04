<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid upload']);
    exit;
}

$filename = uniqid('img_') . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$targetDir = __DIR__ . '/../../uploads/editor/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$path = $targetDir . $filename;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot move image']);
    exit;
}

$url = '/uploads/editor/' . $filename;

echo json_encode(['url' => $url]); 