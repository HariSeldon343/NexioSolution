<?php
/**
 * Genera icone PNG dalle SVG per PWA
 * Richiede estensione Imagick o GD
 */

// Definisci le dimensioni necessarie per PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// SVG base content con il logo Nexio
$svgContent = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    <rect width="512" height="512" fill="#2563eb"/>
    <text x="256" y="280" font-family="Arial, sans-serif" font-size="200" font-weight="bold" fill="white" text-anchor="middle">N</text>
    <text x="256" y="380" font-family="Arial, sans-serif" font-size="60" fill="white" text-anchor="middle">NEXIO</text>
</svg>
SVG;

// SVG per icona maskable (con padding extra per safe area)
$svgMaskableContent = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    <rect width="512" height="512" fill="#2563eb"/>
    <text x="256" y="280" font-family="Arial, sans-serif" font-size="160" font-weight="bold" fill="white" text-anchor="middle">N</text>
    <text x="256" y="360" font-family="Arial, sans-serif" font-size="48" fill="white" text-anchor="middle">NEXIO</text>
</svg>
SVG;

// Crea directory se non esiste
$iconDir = __DIR__ . '/icons';
if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

// Funzione per creare PNG da SVG usando GD (fallback se Imagick non disponibile)
function createPngFromSvg($svgContent, $size, $outputPath) {
    // Salva temporaneamente l'SVG
    $tempSvg = sys_get_temp_dir() . '/temp_icon_' . $size . '.svg';
    file_put_contents($tempSvg, str_replace('512', $size, $svgContent));
    
    // Crea immagine PNG
    $im = imagecreatetruecolor($size, $size);
    
    // Colore di sfondo (blu Nexio)
    $blue = imagecolorallocate($im, 37, 99, 235);
    imagefill($im, 0, 0, $blue);
    
    // Colore testo (bianco)
    $white = imagecolorallocate($im, 255, 255, 255);
    
    // Aggiungi testo "N"
    $fontSize = $size * 0.35;
    $fontPath = 'C:/Windows/Fonts/arial.ttf'; // Path per Windows
    if (!file_exists($fontPath)) {
        $fontPath = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'; // Path per Linux
    }
    
    if (file_exists($fontPath)) {
        // Usa font TrueType se disponibile
        $textBox = imagettfbbox($fontSize, 0, $fontPath, 'N');
        $textWidth = abs($textBox[2] - $textBox[0]);
        $textHeight = abs($textBox[7] - $textBox[1]);
        $x = ($size - $textWidth) / 2;
        $y = ($size + $textHeight) / 2 - $size * 0.1;
        imagettftext($im, $fontSize, 0, $x, $y, $white, $fontPath, 'N');
        
        // Aggiungi "NEXIO" sotto
        $smallFontSize = $size * 0.08;
        $textBox = imagettfbbox($smallFontSize, 0, $fontPath, 'NEXIO');
        $textWidth = abs($textBox[2] - $textBox[0]);
        $x = ($size - $textWidth) / 2;
        $y = $y + $fontSize * 0.4;
        imagettftext($im, $smallFontSize, 0, $x, $y, $white, $fontPath, 'NEXIO');
    } else {
        // Fallback a font built-in
        $font = 5; // Font built-in più grande
        $text = 'N';
        $fontWidth = imagefontwidth($font) * strlen($text);
        $fontHeight = imagefontheight($font);
        $x = ($size - $fontWidth) / 2;
        $y = ($size - $fontHeight) / 2;
        imagestring($im, $font, $x, $y, $text, $white);
    }
    
    // Salva come PNG
    imagepng($im, $outputPath);
    imagedestroy($im);
    
    // Rimuovi file temporaneo
    if (file_exists($tempSvg)) {
        unlink($tempSvg);
    }
    
    return file_exists($outputPath);
}

// Genera icone PNG standard
foreach ($sizes as $size) {
    $outputPath = $iconDir . '/icon-' . $size . 'x' . $size . '.png';
    
    if (createPngFromSvg($svgContent, $size, $outputPath)) {
        echo "✓ Creata icona: icon-{$size}x{$size}.png\n";
    } else {
        echo "✗ Errore creazione: icon-{$size}x{$size}.png\n";
    }
}

// Genera icona maskable 512x512
$outputPath = $iconDir . '/icon-512x512-maskable.png';
if (createPngFromSvg($svgMaskableContent, 512, $outputPath)) {
    echo "✓ Creata icona maskable: icon-512x512-maskable.png\n";
} else {
    echo "✗ Errore creazione: icon-512x512-maskable.png\n";
}

// Crea anche copie per compatibilità
copy($iconDir . '/icon-192x192.png', $iconDir . '/icon-192.png');
copy($iconDir . '/icon-512x512.png', $iconDir . '/icon-512.png');

echo "\n✅ Generazione icone completata!\n";