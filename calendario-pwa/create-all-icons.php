<?php
/**
 * Script PHP per generare tutte le icone PWA necessarie
 */

// Definisci le dimensioni richieste
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Path SVG sorgente
$svgPath = __DIR__ . '/icon.svg';
$iconsDir = __DIR__ . '/icons/';

// Crea directory icons se non exists
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

echo "Generazione icone PWA...\n";

// Verifica se il file SVG esiste
if (!file_exists($svgPath)) {
    die("Errore: File icon.svg non trovato!\n");
}

// Genera icone per ogni dimensione
foreach ($sizes as $size) {
    $outputPath = $iconsDir . "icon-{$size}x{$size}.png";
    
    if (generateIconFromSVG($svgPath, $outputPath, $size)) {
        echo "✓ Generata: icon-{$size}x{$size}.png\n";
    } else {
        echo "✗ Errore generando: icon-{$size}x{$size}.png\n";
    }
}

// Genera anche apple-touch-icon.png (180x180)
$appleTouchPath = $iconsDir . 'apple-touch-icon.png';
if (generateIconFromSVG($svgPath, $appleTouchPath, 180)) {
    echo "✓ Generata: apple-touch-icon.png\n";
} else {
    echo "✗ Errore generando apple-touch-icon.png\n";
}

// Genera favicon.ico (32x32)
$faviconPath = $iconsDir . 'favicon.png';
if (generateIconFromSVG($svgPath, $faviconPath, 32)) {
    echo "✓ Generata: favicon.png\n";
} else {
    echo "✗ Errore generando favicon.png\n";
}

echo "\nGenerazione icone completata!\n";

/**
 * Genera icona PNG da SVG usando ImageMagick o GD
 */
function generateIconFromSVG($svgPath, $outputPath, $size) {
    // Prova prima con ImageMagick se disponibile
    if (class_exists('Imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($svgPath);
            $imagick->setImageFormat('png');
            $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            $imagick->setBackgroundColor(new ImagickPixel('transparent'));
            
            return $imagick->writeImage($outputPath);
        } catch (Exception $e) {
            // Fall back to alternative method
        }
    }
    
    // Fallback: crea PNG semplice con GD
    return createSimplePNG($outputPath, $size);
}

/**
 * Crea un'icona PNG semplice usando GD
 */
function createSimplePNG($outputPath, $size) {
    // Crea immagine
    $img = imagecreatetruecolor($size, $size);
    
    // Abilita trasparenza
    imagealphablending($img, false);
    imagesavealpha($img, true);
    
    // Colori
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    $blue = imagecolorallocate($img, 45, 90, 159); // #2d5a9f
    $lightBlue = imagecolorallocate($img, 59, 114, 196); // #3b72c4
    $white = imagecolorallocate($img, 255, 255, 255);
    $green = imagecolorallocate($img, 16, 185, 129); // #10b981
    
    // Background trasparente
    imagefill($img, 0, 0, $transparent);
    
    // Background gradient (semplificato)
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (int)(59 + (45 - 59) * $ratio);
        $g = (int)(114 + (90 - 114) * $ratio);
        $b = (int)(196 + (159 - 196) * $ratio);
        $color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $size - 1, $y, $color);
    }
    
    // Calendario base (rettangolo bianco)
    $calX = (int)($size * 0.15);
    $calY = (int)($size * 0.25);
    $calW = (int)($size * 0.7);
    $calH = (int)($size * 0.6);
    
    imagefilledrectangle($img, $calX, $calY, $calX + $calW, $calY + $calH, $white);
    
    // Header calendario (blu scuro)
    $headerH = (int)($calH * 0.25);
    imagefilledrectangle($img, $calX, $calY, $calX + $calW, $calY + $headerH, $blue);
    
    // Anelli di rilegatura (semplificati)
    $ringW = (int)($size * 0.06);
    $ringH = (int)($size * 0.15);
    $ring1X = $calX + (int)($calW * 0.2);
    $ring2X = $calX + (int)($calW * 0.8);
    $ringY = $calY - (int)($ringH * 0.3);
    
    // Disegna anelli (rettangoli grigi)
    $gray = imagecolorallocate($img, 107, 114, 128);
    imagefilledrectangle($img, $ring1X, $ringY, $ring1X + $ringW, $ringY + $ringH, $gray);
    imagefilledrectangle($img, $ring2X, $ringY, $ring2X + $ringW, $ringY + $ringH, $gray);
    
    // Griglia calendario semplificata
    $gridStartY = $calY + $headerH + (int)($calH * 0.1);
    $cellSize = (int)($calW * 0.12);
    $spacing = (int)($calW * 0.02);
    
    // Disegna alcune celle grigie
    $lightGray = imagecolorallocate($img, 229, 231, 235);
    for ($row = 0; $row < 3; $row++) {
        for ($col = 0; $col < 6; $col++) {
            $x = $calX + ($col * ($cellSize + $spacing)) + $spacing;
            $y = $gridStartY + ($row * ($cellSize + $spacing));
            
            if ($row == 1 && $col == 2) {
                // Evidenzia una cella (oggi)
                imagefilledrectangle($img, $x, $y, $x + $cellSize, $y + $cellSize, $green);
                // Numero bianco
                if ($size >= 64) {
                    $fontSize = max(1, (int)($size / 25));
                    imagestring($img, $fontSize, $x + (int)($cellSize/3), $y + (int)($cellSize/3), '15', $white);
                }
            } else {
                imagefilledrectangle($img, $x, $y, $x + $cellSize, $y + $cellSize, $lightGray);
            }
        }
    }
    
    // Salva immagine
    $result = imagepng($img, $outputPath);
    imagedestroy($img);
    
    return $result;
}

// Se chiamato da command line, esegui
if (php_sapi_name() === 'cli') {
    // Già eseguito sopra
} else {
    // Se chiamato da web, mostra risultato HTML
    echo "<html><body><h1>Generazione Icone PWA</h1><pre>";
    echo "Script completato! Controlla la directory /icons/";
    echo "</pre></body></html>";
}
?>