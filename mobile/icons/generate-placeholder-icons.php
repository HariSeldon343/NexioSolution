<?php
/**
 * Generatore di icone placeholder per PWA
 * Crea file PNG placeholder per tutte le dimensioni richieste
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// SVG template con placeholder per la dimensione
$svgTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="{SIZE}" height="{SIZE}" viewBox="0 0 {SIZE} {SIZE}" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#2563eb;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#1d4ed8;stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect x="0" y="0" width="{SIZE}" height="{SIZE}" fill="url(#grad)"/>
  <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="{FONTSIZE}" 
        font-weight="bold" text-anchor="middle" fill="white" dy=".3em">N</text>
</svg>';

echo "Creazione icone SVG placeholder per Nexio Mobile PWA...\n\n";

foreach ($sizes as $size) {
    $fontSize = $size * 0.5;
    $svg = str_replace(['{SIZE}', '{FONTSIZE}'], [$size, $fontSize], $svgTemplate);
    
    $filename = "icon-{$size}x{$size}.svg";
    $filepath = __DIR__ . '/' . $filename;
    
    if (file_put_contents($filepath, $svg)) {
        echo "✓ Creata: $filename\n";
    } else {
        echo "✗ Errore creando: $filename\n";
    }
}

// Crea anche versione maskable
$maskableSvg = str_replace(['{SIZE}', '{FONTSIZE}'], [512, 256], $svgTemplate);
$maskableFile = __DIR__ . '/icon-512x512-maskable.svg';
if (file_put_contents($maskableFile, $maskableSvg)) {
    echo "✓ Creata: icon-512x512-maskable.svg\n";
} else {
    echo "✗ Errore creando: icon-512x512-maskable.svg\n";
}

echo "\n✅ Completato! Icone SVG create nella directory: " . __DIR__ . "\n";
echo "\nNota: Queste sono icone SVG che funzionano come placeholder.\n";
echo "Per icone PNG reali, è necessario convertirle con un tool esterno o abilitare GD in PHP.\n";

// Crea anche un semplice script HTML per visualizzare le icone
$htmlPreview = '<!DOCTYPE html>
<html>
<head>
    <title>PWA Icons Preview</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; }
        .icon-item { background: white; padding: 10px; border-radius: 8px; text-align: center; }
        .icon-item img { max-width: 100%; height: auto; display: block; margin: 0 auto 10px; }
        .icon-item span { font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h1>Nexio PWA Icons Preview</h1>
    <div class="icon-grid">';

foreach ($sizes as $size) {
    $htmlPreview .= "
        <div class='icon-item'>
            <img src='icon-{$size}x{$size}.svg' alt='{$size}x{$size}'>
            <span>{$size}x{$size}</span>
        </div>";
}

$htmlPreview .= '
    </div>
</body>
</html>';

file_put_contents(__DIR__ . '/preview.html', $htmlPreview);
echo "\n📱 Creato anche preview.html per visualizzare le icone\n";
?>