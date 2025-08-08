<?php
/**
 * PWA Icons Generator for Nexio Calendar
 * Generates all required PWA icons from base64 encoded images
 */

// Define required icon sizes
$iconSizes = [
    72 => 'icon-72.png',
    96 => 'icon-96.png', 
    128 => 'icon-128.png',
    144 => 'icon-144.png',
    152 => 'icon-152.png',
    192 => 'icon-192.png',
    384 => 'icon-384.png',
    512 => 'icon-512.png',
    180 => 'apple-touch-icon.png'
];

// Special icons
$specialIcons = [
    16 => 'favicon-16.png',
    32 => 'favicon-32.png', 
    48 => 'favicon-48.png'
];

function createCalendarIcon($size) {
    // Create a new image
    $image = imagecreatetruecolor($size, $size);
    
    // Enable alpha blending
    imagealphablending($image, false);
    imagesavealpha($image, true);
    
    // Colors
    $bgStart = imagecolorallocate($image, 59, 114, 196);  // #3b72c4
    $bgEnd = imagecolorallocate($image, 45, 90, 159);     // #2d5a9f
    $white = imagecolorallocate($image, 255, 255, 255);
    $darkBlue = imagecolorallocate($image, 30, 61, 111);  // #1e3d6f
    $gray = imagecolorallocate($image, 107, 114, 128);    // #6b7280
    $lightGray = imagecolorallocate($image, 229, 231, 235); // #e5e7eb
    $green = imagecolorallocate($image, 16, 185, 129);    // #10b981
    
    // Create gradient background
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (1 - $ratio) * 59 + $ratio * 45;
        $g = (1 - $ratio) * 114 + $ratio * 90;
        $b = (1 - $ratio) * 196 + $ratio * 159;
        $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
        imageline($image, 0, $y, $size, $y, $color);
    }
    
    // Calendar dimensions
    $padding = (int)($size * 0.15);
    $calWidth = $size - ($padding * 2);
    $calHeight = (int)($calWidth * 0.85);
    $calX = $padding;
    $calY = $padding + (int)(($size - $calHeight - $padding * 2) / 2);
    
    // Calendar body (rounded rectangle approximation)
    $radius = (int)($size * 0.08);
    imagefilledrectangle($image, $calX + $radius, $calY, $calX + $calWidth - $radius, $calY + $calHeight, $white);
    imagefilledrectangle($image, $calX, $calY + $radius, $calX + $calWidth, $calY + $calHeight - $radius, $white);
    
    // Rounded corners (simplified)
    imagefilledellipse($image, $calX + $radius, $calY + $radius, $radius * 2, $radius * 2, $white);
    imagefilledellipse($image, $calX + $calWidth - $radius, $calY + $radius, $radius * 2, $radius * 2, $white);
    imagefilledellipse($image, $calX + $radius, $calY + $calHeight - $radius, $radius * 2, $radius * 2, $white);
    imagefilledellipse($image, $calX + $calWidth - $radius, $calY + $calHeight - $radius, $radius * 2, $radius * 2, $white);
    
    // Calendar header
    $headerHeight = (int)($calHeight * 0.25);
    imagefilledrectangle($image, $calX + $radius, $calY, $calX + $calWidth - $radius, $calY + $headerHeight, $darkBlue);
    imagefilledrectangle($image, $calX, $calY + $radius, $calX + $calWidth, $calY + $headerHeight, $darkBlue);
    imagefilledellipse($image, $calX + $radius, $calY + $radius, $radius * 2, $radius * 2, $darkBlue);
    imagefilledellipse($image, $calX + $calWidth - $radius, $calY + $radius, $radius * 2, $radius * 2, $darkBlue);
    
    // Binding rings (simplified as rectangles)
    $ringWidth = (int)($size * 0.06);
    $ringHeight = (int)($size * 0.12);
    $ringY = $calY - (int)($ringHeight * 0.4);
    
    // Left ring
    $leftRingX = $calX + (int)($calWidth * 0.25) - (int)($ringWidth / 2);
    imagerectangle($image, $leftRingX, $ringY, $leftRingX + $ringWidth, $ringY + $ringHeight, $gray);
    imagerectangle($image, $leftRingX + 1, $ringY + 1, $leftRingX + $ringWidth - 1, $ringY + $ringHeight - 1, $gray);
    
    // Right ring
    $rightRingX = $calX + (int)($calWidth * 0.75) - (int)($ringWidth / 2);
    imagerectangle($image, $rightRingX, $ringY, $rightRingX + $ringWidth, $ringY + $ringHeight, $gray);
    imagerectangle($image, $rightRingX + 1, $ringY + 1, $rightRingX + $ringWidth - 1, $ringY + $ringHeight - 1, $gray);
    
    // Ring holes
    $holeRadius = (int)($size * 0.015);
    imagefilledellipse($image, $calX + (int)($calWidth * 0.25), $calY - (int)($size * 0.02), $holeRadius * 2, $holeRadius * 2, $gray);
    imagefilledellipse($image, $calX + (int)($calWidth * 0.75), $calY - (int)($size * 0.02), $holeRadius * 2, $holeRadius * 2, $gray);
    
    // Calendar grid
    $gridStartY = $calY + $headerHeight + (int)($size * 0.02);
    $gridHeight = $calHeight - $headerHeight - (int)($size * 0.04);
    $cellWidth = (int)($calWidth / 7);
    $cellHeight = (int)($gridHeight / 5);
    
    // Draw calendar cells
    for ($row = 0; $row < 4; $row++) {
        for ($col = 0; $col < 7; $col++) {
            if ($row === 3 && $col > 2) continue; // Partial last row
            
            $cellX = $calX + $col * $cellWidth + (int)($size * 0.01);
            $cellY = $gridStartY + $row * $cellHeight + (int)($size * 0.01);
            $cellW = $cellWidth - (int)($size * 0.02);
            $cellH = $cellHeight - (int)($size * 0.02);
            
            imagefilledrectangle($image, $cellX, $cellY, $cellX + $cellW, $cellY + $cellH, $lightGray);
        }
    }
    
    // Today highlight
    $todayCol = 2;
    $todayRow = 1;
    $todayX = $calX + $todayCol * $cellWidth + (int)($size * 0.01);
    $todayY = $gridStartY + $todayRow * $cellHeight + (int)($size * 0.01);
    $todayW = $cellWidth - (int)($size * 0.02);
    $todayH = $cellHeight - (int)($size * 0.02);
    
    imagefilledrectangle($image, $todayX, $todayY, $todayX + $todayW, $todayY + $todayH, $green);
    
    // Today number
    if ($size >= 32) {
        $fontSize = max(1, (int)($size / 16));
        $fontFile = __DIR__ . '/../assets/fonts/arial.ttf'; // Fallback to imagestring if no font
        
        if (file_exists($fontFile)) {
            imagettftext($image, $fontSize, 0, $todayX + (int)($todayW / 2) - 5, $todayY + (int)($todayH / 2) + 3, $white, $fontFile, '15');
        } else {
            imagestring($image, 2, $todayX + (int)($todayW / 2) - 6, $todayY + (int)($todayH / 2) - 6, '15', $white);
        }
    }
    
    return $image;
}

// Generate and save icons
function generateIcons() {
    global $iconSizes, $specialIcons;
    
    $results = [];
    
    // Generate main PWA icons
    foreach ($iconSizes as $size => $filename) {
        try {
            $image = createCalendarIcon($size);
            
            if (imagepng($image, __DIR__ . '/' . $filename)) {
                $results[] = "✓ Generated $filename ($size x $size)";
            } else {
                $results[] = "✗ Failed to save $filename";
            }
            
            imagedestroy($image);
        } catch (Exception $e) {
            $results[] = "✗ Error generating $filename: " . $e->getMessage();
        }
    }
    
    // Generate favicon sizes
    foreach ($specialIcons as $size => $filename) {
        try {
            $image = createCalendarIcon($size);
            
            if (imagepng($image, __DIR__ . '/' . $filename)) {
                $results[] = "✓ Generated $filename ($size x $size)";
            } else {
                $results[] = "✗ Failed to save $filename";
            }
            
            imagedestroy($image);
        } catch (Exception $e) {
            $results[] = "✗ Error generating $filename: " . $e->getMessage();
        }
    }
    
    // Create favicon.ico (simplified - just copy 32px version)
    if (file_exists(__DIR__ . '/favicon-32.png')) {
        if (copy(__DIR__ . '/favicon-32.png', __DIR__ . '/favicon.ico')) {
            $results[] = "✓ Generated favicon.ico";
        } else {
            $results[] = "✗ Failed to create favicon.ico";
        }
    }
    
    return $results;
}

// Main execution
if (isset($_GET['generate']) || php_sapi_name() === 'cli') {
    $results = generateIcons();
    
    if (php_sapi_name() === 'cli') {
        // Command line output
        foreach ($results as $result) {
            echo $result . "\n";
        }
    } else {
        // Web output
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>PWA Icons Generator</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} ul{line-height:1.6;}</style>';
        echo '</head><body>';
        echo '<h1>Nexio Calendar PWA Icons Generator</h1>';
        echo '<ul>';
        foreach ($results as $result) {
            $class = strpos($result, '✓') === 0 ? 'success' : 'error';
            echo "<li class=\"$class\">$result</li>";
        }
        echo '</ul>';
        echo '<p><a href="index.html">← Back to PWA</a></p>';
        echo '</body></html>';
    }
} else {
    // Show generation form
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>PWA Icons Generator</title>';
    echo '<style>body{font-family:Arial,sans-serif;margin:40px;text-align:center;} .btn{background:#2d5a9f;color:white;padding:15px 30px;border:none;border-radius:8px;font-size:16px;cursor:pointer;text-decoration:none;display:inline-block;margin:20px;}</style>';
    echo '</head><body>';
    echo '<h1>Nexio Calendar PWA Icons</h1>';
    echo '<p>Click the button below to generate all required PWA icons.</p>';
    echo '<a href="?generate=1" class="btn">Generate Icons</a>';
    echo '<p><small>This will create icons in sizes: 72x72, 96x96, 128x128, 144x144, 152x152, 192x192, 384x384, 512x512, 180x180 (Apple), and favicon variants.</small></p>';
    echo '</body></html>';
}
?>