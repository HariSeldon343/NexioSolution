// Simple icon generator for Nexio Calendar PWA
// This creates base64 encoded images for the manifest

const iconSizes = [72, 96, 128, 144, 152, 192, 384, 512, 180]; // 180 for apple-touch-icon

function createCalendarIcon(size) {
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');
    
    // Background gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, size);
    gradient.addColorStop(0, '#3b72c4');
    gradient.addColorStop(1, '#2d5a9f');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, size, size);
    
    // Calendar base
    const padding = size * 0.15;
    const calWidth = size - (padding * 2);
    const calHeight = calWidth * 0.85;
    const calX = padding;
    const calY = padding + (size - calHeight - padding * 2) / 2;
    
    // Calendar body
    ctx.fillStyle = '#ffffff';
    ctx.roundRect(calX, calY, calWidth, calHeight, size * 0.08);
    ctx.fill();
    
    // Calendar header
    const headerHeight = calHeight * 0.25;
    ctx.fillStyle = '#1e3d6f';
    ctx.roundRect(calX, calY, calWidth, headerHeight, size * 0.08);
    ctx.fill();
    ctx.fillRect(calX, calY + headerHeight * 0.5, calWidth, headerHeight * 0.5);
    
    // Binding rings
    const ringWidth = size * 0.06;
    const ringHeight = size * 0.12;
    const ringY = calY - ringHeight * 0.4;
    
    ctx.strokeStyle = '#6b7280';
    ctx.lineWidth = size * 0.02;
    ctx.fillStyle = 'transparent';
    
    // Left ring
    ctx.beginPath();
    ctx.roundRect(calX + calWidth * 0.25 - ringWidth/2, ringY, ringWidth, ringHeight, ringWidth/2);
    ctx.stroke();
    
    // Right ring  
    ctx.beginPath();
    ctx.roundRect(calX + calWidth * 0.75 - ringWidth/2, ringY, ringWidth, ringHeight, ringWidth/2);
    ctx.stroke();
    
    // Ring holes
    ctx.fillStyle = '#6b7280';
    ctx.beginPath();
    ctx.arc(calX + calWidth * 0.25, calY - size * 0.02, size * 0.015, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.beginPath();
    ctx.arc(calX + calWidth * 0.75, calY - size * 0.02, size * 0.015, 0, Math.PI * 2);
    ctx.fill();
    
    // Calendar grid
    const gridStartY = calY + headerHeight + size * 0.02;
    const gridHeight = calHeight - headerHeight - size * 0.04;
    const cellWidth = calWidth / 7;
    const cellHeight = gridHeight / 5;
    
    ctx.fillStyle = '#e5e7eb';
    for (let row = 0; row < 4; row++) {
        for (let col = 0; col < 7; col++) {
            if (row === 3 && col > 2) continue; // Partial last row
            
            const cellX = calX + col * cellWidth + size * 0.01;
            const cellY = gridStartY + row * cellHeight + size * 0.01;
            const cellW = cellWidth - size * 0.02;
            const cellH = cellHeight - size * 0.02;
            
            ctx.fillRect(cellX, cellY, cellW, cellH);
        }
    }
    
    // Today highlight
    const todayCol = 2;
    const todayRow = 1;
    const todayX = calX + todayCol * cellWidth + size * 0.01;
    const todayY = gridStartY + todayRow * cellHeight + size * 0.01;
    const todayW = cellWidth - size * 0.02;
    const todayH = cellHeight - size * 0.02;
    
    ctx.fillStyle = '#10b981';
    ctx.fillRect(todayX, todayY, todayW, todayH);
    
    // Today number
    ctx.fillStyle = '#ffffff';
    ctx.font = `bold ${size * 0.08}px Arial`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('15', todayX + todayW/2, todayY + todayH/2);
    
    return canvas.toDataURL('image/png');
}

// Helper function for rounded rectangles (for older browsers)
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, width, height, radius) {
        this.beginPath();
        this.moveTo(x + radius, y);
        this.lineTo(x + width - radius, y);
        this.quadraticCurveTo(x + width, y, x + width, y + radius);
        this.lineTo(x + width, y + height - radius);
        this.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        this.lineTo(x + radius, y + height);
        this.quadraticCurveTo(x, y + height, x, y + height - radius);
        this.lineTo(x, y + radius);
        this.quadraticCurveTo(x, y, x + radius, y);
        this.closePath();
    };
}

// Generate all icons
function generateAllIcons() {
    const icons = {};
    
    iconSizes.forEach(size => {
        try {
            const dataUrl = createCalendarIcon(size);
            icons[`icon-${size}`] = dataUrl;
            console.log(`Generated ${size}x${size} icon`);
        } catch (error) {
            console.error(`Failed to generate ${size}x${size} icon:`, error);
        }
    });
    
    // Create favicon (simplified)
    try {
        const faviconDataUrl = createCalendarIcon(32);
        icons['favicon'] = faviconDataUrl;
        console.log('Generated favicon');
    } catch (error) {
        console.error('Failed to generate favicon:', error);
    }
    
    return icons;
}

// Export for use in PWA
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { createCalendarIcon, generateAllIcons };
}

// For browser usage
if (typeof window !== 'undefined') {
    window.NexioIconGenerator = { createCalendarIcon, generateAllIcons };
}