/**
 * Script per generare tutte le icone PWA di diverse dimensioni
 * Usa l'SVG esistente come base
 */

// Legge il contenuto SVG base
fetch('./icon.svg')
    .then(response => response.text())
    .then(svgContent => {
        const sizes = [72, 96, 128, 144, 152, 192, 384, 512];
        
        sizes.forEach(size => {
            generatePNGFromSVG(svgContent, size)
                .then(blob => {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = `icon-${size}x${size}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                    
                    console.log(`Generated icon-${size}x${size}.png`);
                })
                .catch(error => {
                    console.error(`Error generating ${size}x${size} icon:`, error);
                });
        });
    })
    .catch(error => {
        console.error('Error loading SVG:', error);
    });

/**
 * Converte SVG in PNG di una specifica dimensione
 */
function generatePNGFromSVG(svgContent, size) {
    return new Promise((resolve, reject) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        
        canvas.width = size;
        canvas.height = size;
        
        // Crea SVG data URL
        const svgBlob = new Blob([svgContent], { type: 'image/svg+xml' });
        const svgUrl = URL.createObjectURL(svgBlob);
        
        img.onload = () => {
            // Disegna l'immagine SVG sul canvas
            ctx.drawImage(img, 0, 0, size, size);
            
            // Converte il canvas in blob PNG
            canvas.toBlob((blob) => {
                URL.revokeObjectURL(svgUrl);
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('Failed to create blob'));
                }
            }, 'image/png');
        };
        
        img.onerror = () => {
            URL.revokeObjectURL(svgUrl);
            reject(new Error('Failed to load SVG image'));
        };
        
        img.src = svgUrl;
    });
}

// Aggiungi pulsante per generazione automatica
document.addEventListener('DOMContentLoaded', () => {
    const button = document.createElement('button');
    button.textContent = 'Genera Tutte le Icone';
    button.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 10px 20px;
        background: #3b72c4;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        z-index: 1000;
    `;
    
    button.onclick = () => {
        // Trigger icon generation
        const script = document.createElement('script');
        script.textContent = `
            fetch('./icon.svg')
                .then(response => response.text())
                .then(svgContent => {
                    const sizes = [72, 96, 128, 144, 152, 192, 384, 512];
                    
                    sizes.forEach((size, index) => {
                        setTimeout(() => {
                            generatePNGFromSVG(svgContent, size);
                        }, index * 500);
                    });
                });
        `;
        document.head.appendChild(script);
    };
    
    document.body.appendChild(button);
});