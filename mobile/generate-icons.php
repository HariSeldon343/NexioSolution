<?php
/**
 * Script per generare le icone PWA in tutte le dimensioni richieste
 * Crea icone PNG a partire da un template base
 */

// Crea la directory per le icone se non esiste
$iconsDir = __DIR__ . '/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

// Dimensioni richieste per le icone PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Colori del tema Nexio
$primaryColor = '#2563eb';
$backgroundColor = '#ffffff';

/**
 * Genera un'icona PNG con il logo Nexio
 */
function generateIcon($size, $outputPath, $isMaskable = false) {
    // Crea immagine
    $image = imagecreatetruecolor($size, $size);
    
    // Abilita trasparenza
    imagesavealpha($image, true);
    
    // Colori
    $white = imagecolorallocate($image, 255, 255, 255);
    $blue = imagecolorallocate($image, 37, 99, 235);
    $darkBlue = imagecolorallocate($image, 29, 78, 216);
    
    // Sfondo
    if ($isMaskable) {
        // Per icone maskable, usa sfondo pieno con padding
        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $white);
        $padding = $size * 0.2; // 20% padding per maskable
    } else {
        // Per icone normali, sfondo trasparente
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        $padding = $size * 0.1; // 10% padding per normali
    }
    
    // Disegna quadrato arrotondato per il logo
    $rectSize = $size - ($padding * 2);
    $rectX = $padding;
    $rectY = $padding;
    $cornerRadius = $rectSize * 0.15;
    
    // Riempi il rettangolo arrotondato
    imagefilledroundedrect($image, $rectX, $rectY, 
                          $rectX + $rectSize, $rectY + $rectSize, 
                          $cornerRadius, $cornerRadius, $blue);
    
    // Aggiungi gradiente (simulato con overlay)
    for ($i = 0; $i < $rectSize / 2; $i++) {
        $alpha = 30 - ($i * 0.5);
        if ($alpha < 0) $alpha = 0;
        $overlayColor = imagecolorallocatealpha($image, 29, 78, 216, $alpha);
        imageline($image, $rectX + $i, $rectY, 
                 $rectX + $i, $rectY + $rectSize, $overlayColor);
    }
    
    // Aggiungi il simbolo della stella (✦) al centro
    $fontSize = $rectSize * 0.4;
    $text = '✦';
    
    // Usa font built-in (può essere sostituito con un font TTF se disponibile)
    $font = 5; // Font built-in più grande
    
    // Calcola posizione del testo
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    $textX = ($size - $textWidth) / 2;
    $textY = ($size - $textHeight) / 2;
    
    // Disegna il simbolo
    imagestring($image, $font, $textX, $textY, $text, $white);
    
    // Alternativa: disegna una stella con linee
    if ($size >= 128) {
        $centerX = $size / 2;
        $centerY = $size / 2;
        $starSize = $rectSize * 0.3;
        
        // Disegna stella a 4 punte
        $points = [];
        for ($i = 0; $i < 8; $i++) {
            $angle = ($i * 45) * M_PI / 180;
            $radius = ($i % 2 == 0) ? $starSize : $starSize * 0.5;
            $points[] = $centerX + cos($angle) * $radius;
            $points[] = $centerY + sin($angle) * $radius;
        }
        
        imagefilledpolygon($image, $points, 8, $white);
    }
    
    // Salva l'immagine
    imagepng($image, $outputPath, 9);
    imagedestroy($image);
    
    return true;
}

/**
 * Funzione helper per disegnare rettangoli arrotondati
 */
function imagefilledroundedrect($image, $x1, $y1, $x2, $y2, $rx, $ry, $color) {
    // Riempi il corpo principale
    imagefilledrectangle($image, $x1 + $rx, $y1, $x2 - $rx, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $ry, $x2, $y2 - $ry, $color);
    
    // Disegna gli angoli arrotondati
    imagefilledellipse($image, $x1 + $rx, $y1 + $ry, $rx * 2, $ry * 2, $color);
    imagefilledellipse($image, $x2 - $rx, $y1 + $ry, $rx * 2, $ry * 2, $color);
    imagefilledellipse($image, $x1 + $rx, $y2 - $ry, $rx * 2, $ry * 2, $color);
    imagefilledellipse($image, $x2 - $rx, $y2 - $ry, $rx * 2, $ry * 2, $color);
}

// Genera icone standard
echo "Generazione icone PWA per Nexio Mobile...\n";

foreach ($sizes as $size) {
    $filename = "icon-{$size}x{$size}.png";
    $filepath = $iconsDir . '/' . $filename;
    
    if (generateIcon($size, $filepath)) {
        echo "✓ Generata: $filename\n";
    } else {
        echo "✗ Errore generando: $filename\n";
    }
}

// Genera icona maskable speciale (512x512)
$maskableFile = $iconsDir . '/icon-512x512-maskable.png';
if (generateIcon(512, $maskableFile, true)) {
    echo "✓ Generata: icon-512x512-maskable.png\n";
} else {
    echo "✗ Errore generando: icon-512x512-maskable.png\n";
}

// Genera anche icone Apple Touch
$appleSizes = [120, 180];
foreach ($appleSizes as $size) {
    $filename = "apple-touch-icon-{$size}x{$size}.png";
    $filepath = $iconsDir . '/' . $filename;
    
    if (generateIcon($size, $filepath)) {
        echo "✓ Generata: $filename\n";
    } else {
        echo "✗ Errore generando: $filename\n";
    }
}

echo "\nGenerazione completata! Le icone sono state salvate in: $iconsDir\n";
echo "\nPer utilizzare le icone:\n";
echo "1. Le icone sono già riferite nel manifest.json\n";
echo "2. Aggiungi questo tag nell'HTML per Apple devices:\n";
echo '   <link rel="apple-touch-icon" href="icons/apple-touch-icon-180x180.png">' . "\n";
echo "3. Testa l'installabilità su Chrome DevTools > Application > Manifest\n";
?>