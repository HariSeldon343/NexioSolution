/**
 * Script JavaScript per generare icone PWA
 * Usa Canvas per creare icone programmaticamente
 */

// Esegui quando il DOM è caricato
document.addEventListener('DOMContentLoaded', function() {
    createAllIcons();
});

function createAllIcons() {
    const sizes = [
        { size: 72, filename: 'icon-72x72.png' },
        { size: 96, filename: 'icon-96x96.png' }, 
        { size: 128, filename: 'icon-128x128.png' },
        { size: 144, filename: 'icon-144x144.png' },
        { size: 152, filename: 'icon-152x152.png' },
        { size: 192, filename: 'icon-192x192.png' },
        { size: 384, filename: 'icon-384x384.png' },
        { size: 512, filename: 'icon-512x512.png' },
        { size: 180, filename: 'apple-touch-icon.png' },
        { size: 32, filename: 'favicon.png' }
    ];

    console.log('Generazione icone iniziata...');

    sizes.forEach((iconSpec, index) => {
        setTimeout(() => {
            const canvas = createCalendarIcon(iconSpec.size);
            canvas.toBlob((blob) => {
                if (blob) {
                    downloadIcon(blob, iconSpec.filename);
                    console.log(`✓ Creata: ${iconSpec.filename}`);
                }
            }, 'image/png', 1.0);
        }, index * 100);
    });
}

function createCalendarIcon(size) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    canvas.width = size;
    canvas.height = size;

    // Background gradient (blu Nexio)
    const gradient = ctx.createLinearGradient(0, 0, 0, size);
    gradient.addColorStop(0, '#3b72c4');
    gradient.addColorStop(1, '#2d5a9f');
    
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, size, size);

    // Parametri calendario
    const padding = size * 0.15;
    const calendarWidth = size - (padding * 2);
    const calendarHeight = calendarWidth * 0.85;
    const calendarX = padding;
    const calendarY = size * 0.25;
    const cornerRadius = size * 0.08;

    // Sfondo bianco calendario
    ctx.fillStyle = '#ffffff';
    drawRoundedRect(ctx, calendarX, calendarY, calendarWidth, calendarHeight, cornerRadius);
    ctx.fill();

    // Header calendario (blu scuro)
    const headerHeight = calendarHeight * 0.25;
    ctx.fillStyle = '#1e3d6f';
    drawRoundedRect(ctx, calendarX, calendarY, calendarWidth, headerHeight, cornerRadius);
    ctx.fill();
    
    // Riempie angoli header
    ctx.fillRect(calendarX, calendarY + headerHeight - cornerRadius, calendarWidth, cornerRadius);

    // Anelli di rilegatura
    const ringWidth = size * 0.06;
    const ringHeight = size * 0.12;
    const ring1X = calendarX + calendarWidth * 0.2 - ringWidth / 2;
    const ring2X = calendarX + calendarWidth * 0.8 - ringWidth / 2;
    const ringY = calendarY - ringHeight * 0.5;
    
    ctx.strokeStyle = '#6b7280';
    ctx.lineWidth = Math.max(2, size * 0.012);
    
    // Ring 1
    drawRoundedRect(ctx, ring1X, ringY, ringWidth, ringHeight, ringWidth / 2);
    ctx.stroke();
    
    // Ring 2  
    drawRoundedRect(ctx, ring2X, ringY, ringWidth, ringHeight, ringWidth / 2);
    ctx.stroke();

    // Fori anelli
    const holeRadius = size * 0.015;
    ctx.fillStyle = '#6b7280';
    
    ctx.beginPath();
    ctx.arc(ring1X + ringWidth/2, ringY - holeRadius, holeRadius, 0, 2 * Math.PI);
    ctx.fill();
    
    ctx.beginPath();
    ctx.arc(ring2X + ringWidth/2, ringY - holeRadius, holeRadius, 0, 2 * Math.PI);
    ctx.fill();

    // Griglia calendario
    const gridStartY = calendarY + headerHeight + calendarHeight * 0.08;
    const cellSize = calendarWidth * 0.11;
    const spacing = calendarWidth * 0.02;
    const cols = 7;
    const rows = 3;

    // Celle calendario
    ctx.fillStyle = '#e5e7eb';
    
    for (let row = 0; row < rows; row++) {
        const colsInRow = row === 0 ? cols - 1 : cols; // Prima riga ha 6 celle
        const startX = row === 0 ? calendarX + cellSize + spacing : calendarX + spacing;
        
        for (let col = 0; col < Math.min(colsInRow, 6); col++) {
            const x = startX + col * (cellSize + spacing);
            const y = gridStartY + row * (cellSize + spacing);
            
            // Evidenzia giorno corrente (oggi = 15)
            if (row === 1 && col === 2) {
                ctx.fillStyle = '#10b981'; // Verde
                drawRoundedRect(ctx, x, y, cellSize, cellSize, cellSize * 0.15);
                ctx.fill();
                
                // Numero bianco
                if (size >= 48) {
                    ctx.fillStyle = '#ffffff';
                    ctx.font = `bold ${Math.max(8, size * 0.04)}px Arial, sans-serif`;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('15', x + cellSize/2, y + cellSize/2);
                }
                
                ctx.fillStyle = '#e5e7eb'; // Reset per altre celle
            } else {
                drawRoundedRect(ctx, x, y, cellSize, cellSize, cellSize * 0.15);
                ctx.fill();
            }
        }
    }

    return canvas;
}

function drawRoundedRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
}

function downloadIcon(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    // Cleanup
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// Export per uso esterno
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { createCalendarIcon, createAllIcons };
}