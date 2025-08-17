<?php
/**
 * Crea icone PNG semplici per PWA usando dati binari
 * Non richiede estensioni grafiche
 */

// Crea directory se non esiste
$iconDir = __DIR__ . '/icons';
if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

// Funzione per creare PNG minimo valido
function createMinimalPng($width, $height, $outputPath) {
    // PNG header
    $png = "\x89PNG\r\n\x1a\n";
    
    // IHDR chunk
    $ihdr = pack('N', $width) . pack('N', $height);
    $ihdr .= "\x08\x02\x00\x00\x00"; // 8 bit depth, RGB, no compression
    $ihdr_crc = pack('N', crc32("IHDR" . $ihdr));
    $png .= pack('N', 13) . "IHDR" . $ihdr . $ihdr_crc;
    
    // Crea dati immagine (blu Nexio con N bianca)
    $imageData = '';
    $centerX = $width / 2;
    $centerY = $height / 2;
    $fontSize = $height * 0.4;
    
    for ($y = 0; $y < $height; $y++) {
        $imageData .= "\x00"; // Filter type: None
        for ($x = 0; $x < $width; $x++) {
            // Sfondo blu Nexio (#2563eb)
            $r = 37;
            $g = 99;
            $b = 235;
            
            // Crea una "N" bianca semplice al centro
            $inN = false;
            $relX = abs($x - $centerX);
            $relY = abs($y - $centerY);
            
            // Forma base della N (molto semplificata)
            if ($relY < $fontSize/2) {
                // Aste verticali della N
                if (($relX < 10 && $x < $centerX) || // Asta sinistra
                    ($relX < 10 && $x > $centerX) || // Asta destra  
                    // Diagonale
                    (abs($relX - $relY * 0.8) < 15)) {
                    $inN = true;
                }
            }
            
            if ($inN) {
                // Bianco per la lettera
                $r = $g = $b = 255;
            }
            
            $imageData .= chr($r) . chr($g) . chr($b);
        }
    }
    
    // Comprimi con zlib
    $compressedData = gzcompress($imageData, 9);
    $idat_crc = pack('N', crc32("IDAT" . $compressedData));
    $png .= pack('N', strlen($compressedData)) . "IDAT" . $compressedData . $idat_crc;
    
    // IEND chunk
    $png .= "\x00\x00\x00\x00IEND\xae\x42\x60\x82";
    
    file_put_contents($outputPath, $png);
    return file_exists($outputPath);
}

// Crea PNG base 512x512 da usare per tutte le dimensioni
$basePng = $iconDir . '/icon-base.png';

// Crea un PNG placeholder blu con testo
$pngData = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAgAAAAIACAYAAAD0eNT6AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAOxAAADsQBlSsOGwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAABTFSURBVHic7d1/zG1nQQfwb3t7aUspUKClQEuhUCgtUFoKBQoUKBQoUChQKFAoUChQKFAoUKBAgQKFAoUChQKFAoUChQKFAoUChQIFChQoUKBQoECBAgUKpbS3t7f+YXaS2WSy' .
    'Z3LPOed9z+fz+XwSQkJCznOe53m+7/ue95z3JgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA8P9kZ2fn0J2dnf8aO3fu/L9jV1dXz66urv7f2NnZ+e/hfQHgAOzs7Jy0s7PzqJ2dnRfu7Oy8bWdn56s7Ozs7' .
    'B2hnZ+frOzs7b93Z2XnBzs7OI3Z2dk7e7WsDAFO2s7NzxM7OzkN3dnZeubOz8+mdnZ1f73KAn6+fDX/vFcPfPWK3rxkATMHOzs5pOzs7z9zZ2fnIbgf2g7Gzs/ORnZ2dZ+3s7Jy229cSAPbLzs7O7+zs7Dx3Z2fnR7sdwPfTz4b38Mjdvr4AsFd2dnYuGILgbgfqKfXD4T1dsNvXGwB+EyGCAAGQceFuB+Rt9oPBezhnt98DANjI' .
    '2bsdgPfDD3Z2ds7a7fcCANZ25m4H3v32g+EaAMD03brrgXZW7t3t9wYAbtGZux1gZ+mH2g0AMNVQO0vnz9L5ux1gZ+mH2hEAMM1Qu0vnztK5ux1gZ+ncWTp3t98rANjIWbsdYGfp3Fk6d7ffKwDYyNm7HWBn6fxZOn+33ysA2MiFux1gZ+mCWbpgt98rANjIRbsdYGfpolm6aLffKwDYyCW7HWBn6ZJZumS33ysA' .
    '2Mill+52gJ2ly2bpst1+rwBgI1fsdoCdpStm6YrBfq8AYCNYyNX7HWBn6apZumq33ysA2MjVux1gZ+nqWbp6t98rANjINbsdYGfpmVm6ZrffKwDYyLW7HWBn6dpZuna33ysA2Mh1ux1gZ+m6Wbput98rANjItbsdYBEiCBEEiI1cv9sBdpaun6Xrd/u9AoCN3LDbAXaWbpilG3b7vQKAjdy42wF2lm6cpRt3+70C' .
    'gI3ctNsBdpZumqWbdvu9AoCN3LzbAXaWbp6lm3f7vQKAjdyy2wF2lm6ZpVt2+70CgI38+m4H2Fn69Sx9evffKwDYyG27HWBn6bZZum233ysA2Mjtux1gZ+n2Wbp9t98rANjIHbsdYGfpjlm6Y7ffKwDYyJ27HWBn6c5ZunO33ysA2Mhdux1gZ+muWbprt98rANjI3bsdYGfp7lm6e7ffKwDYyD27HWBn6Z5Zume3' .
    '3ysA2Mi9ux1gZ+neWbp3t98rANjIvbsdYGfpvlm6b7ffKwDYyH2dnZ37BzsQAMD+un+WjJilB3b7vQKAjTy428F1lh6cpQd3+70CgI08tNvBdZYemqWHdvu9AoCNPLzbwXWWHp6lh3f7vQKAjTyy28F1lh6ZpUd2+70CgI08utvBdZYenaVHd/u9AoCNPKbbwXWWHpOlx3T7vQKAjTyu28F1lh6Xpcd1+70CgI08vtsBVh5e' .
    'nqUnQPu9AoCNPLHbAVYeXp6lJ3T7vQKAjTyp2wFWHl6epSd1+70CgI08udvBVR5enqUnd/u9AoCNPKXbwVUeXp6lp3T7vQKAjTy128FVHl6epad2+70CgI08rdvBVR5enqWndfu9AoCNPL3bwVUeXp6lp3f7vQKAjTyj28FVHl6epWd0+70CgI08s9vBVR5enqVndvu9AoCNPKvbwVUeXp6lZ3X7vQKAjTy728FVHl6epWd3' .
    '+70CgI08p9vBVR5enqXndPu9AoCNPLfbwVUeXp6l53b7vQKAjTyv28FVHl6epd1+rwBgI8/vdnCVh5dn6fndfu8AoCNP6nZwlYeXZ+lJ3X7vAKAjT+52cJWHl2fpyd1+7wCgI0/pdnCVh5dn6SndBu8AoCNP7XZwlYeXZ+mp3X7vAKAjT+t2cJWHl2fpad1+7wCgI0/vdnCVh5dn6endfu8AoCPP6HZwlYeXZ+kZ3X7v' .
    'AKAjz+x2cJWHl2fpmd1+7wCgI8/qdnCVh5dn6Vndfu8AoCPP7nZwlYeXZ+nZ3X7vAKAjz+l2cJWHl2fpOd1+7wCgI8/tdnCVh5dn6bndfu8AoCPP63ZwlYeXZ+l53X7vAKAjz+92cJWHl2fp+d1+7wCgIy/odnCVh5dn6QXdfu8AoCMv7HZwlYeXZ+mF3X7vAKAjL+p2cJWHl2fpRd1+7wCgIy/udnCVh5dn6cXdfu8A' .
    'oCMv6XZwlYeXZ+kl3X7vAKAjL+12cJWHl2fpBd1+7wAAAAAAAAAAAAAAAAAAAAB7YGdn56idnZ0zdnZ2Lt7Z2Xnizs7Oa3d2dj68s7Pz452dnf+ysez8cmdn57s7Ozuf2tnZef/Ozs6rdnZ2HrOzs3PBzs7OaTs7O0ccbHBfg9OHz3zM8NmvGj77/cPP+O7wM/9rN68nwNzs7Oy8bmdn52e7HZRsLP9tZ2fnTzs7' .
    'Oz+dbH6r25j5dn4pnzVsHFl6+3jvOzs7R+32ewawdnd2dm6/28FoGh0/Hbq05yaTbewjO6ft9nsOdYwg/aM1ux2Mtt0pH9pJZzE5xoSxnZ2d++z2e86Ufn4vI8sAQp7b7WC07U79+PDfL7aD5Iyk6kJPP6fJxPDcnZ3zu/2eM6Vuz/ewtMjqR64trvU/O5vFjl1dpOqxwlJVux2Mtt2pLznXzqKvfE9IH5Md8Pfe7fecKfVHX3mLn' .
    'BM2+x9n7+x82T24TqF7xnycdxAAA'
);

file_put_contents($basePng, $pngData);

// Dimensioni necessarie per PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Per ogni dimensione, copia il file base (in produzione useresti un ridimensionatore)
foreach ($sizes as $size) {
    $outputPath = $iconDir . '/icon-' . $size . 'x' . $size . '.png';
    
    // Per ora copia semplicemente il file base
    // In produzione dovresti ridimensionare l'immagine
    if (copy($basePng, $outputPath)) {
        echo "✓ Creata icona: icon-{$size}x{$size}.png\n";
    } else {
        echo "✗ Errore creazione: icon-{$size}x{$size}.png\n";
    }
}

// Crea icona maskable
$maskablePath = $iconDir . '/icon-512x512-maskable.png';
if (copy($basePng, $maskablePath)) {
    echo "✓ Creata icona maskable: icon-512x512-maskable.png\n";
}

// Crea copie per compatibilità
copy($iconDir . '/icon-192x192.png', $iconDir . '/icon-192.png');
copy($iconDir . '/icon-512x512.png', $iconDir . '/icon-512.png');

echo "\n✅ Icone PNG placeholder create! In produzione, sostituiscile con icone reali.\n";