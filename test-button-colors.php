<?php
require_once 'backend/config/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Button Colors - Nexio</title>
    
    <!-- Include all standard headers -->
    <?php 
    $pageTitle = "Test Button Colors";
    include 'components/header.php'; 
    ?>
    
    <style>
        .test-container {
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .color-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="test-container">
            <h1>Button Color Test</h1>
            
            <div class="test-section">
                <h3>Primary & Secondary Buttons with Inline Styles (Should ALL have WHITE text)</h3>
                <div class="button-grid">
                    <!-- Exact copy of problematic button from platform -->
                    <div>
                        <button type="submit" class="btn btn-primary" style="height: 36px; padding: 0px 16px; background: white; border: 1px solid rgb(45, 90, 159); border-radius: 2px; color: rgb(45, 90, 159); font-weight: 500; cursor: pointer; transition: border-color 0.15s; text-transform: uppercase; letter-spacing: 0.05em; font-size: 12px; transform: translateY(0px);">
                            <i class="fas fa-search" style="font-size: 11px;"></i> Applica Filtro
                        </button>
                        <div class="color-info">Original problematic button</div>
                    </div>
                    
                    <!-- Button with white background inline -->
                    <div>
                        <button class="btn btn-primary" style="background: white; color: rgb(45, 90, 159);">
                            <i class="fas fa-plus"></i> Add New
                        </button>
                        <div class="color-info">White bg, blue text inline</div>
                    </div>
                    
                    <!-- Button with blue text inline -->
                    <div>
                        <button class="btn btn-primary" style="color: #2d5a9f;">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <div class="color-info">Blue text inline</div>
                    </div>
                    
                    <!-- Normal primary button -->
                    <div>
                        <button class="btn btn-primary">
                            <i class="fas fa-check"></i> Normal Primary
                        </button>
                        <div class="color-info">No inline styles</div>
                    </div>
                    
                    <!-- Submit input -->
                    <div>
                        <input type="submit" class="btn btn-primary" value="Submit Form" style="color: rgb(45, 90, 159);">
                        <div class="color-info">Submit input with blue text</div>
                    </div>
                    
                    <!-- Link button -->
                    <div>
                        <a href="#" class="btn btn-primary" style="background: white; color: #2d5a9f;">
                            <i class="fas fa-link"></i> Link Button
                        </a>
                        <div class="color-info">Link with inline styles</div>
                    </div>
                </div>
                
                <h4 class="mt-4">Secondary Buttons (Should also have WHITE text)</h4>
                <div class="button-grid">
                    <!-- Exact secondary button from platform -->
                    <div>
                        <button class="btn btn-secondary" onclick="showNewFolderModal()" style="text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; transform: translateY(0px);">
                            <i class="fas fa-folder-plus"></i> Nuova Cartella
                        </button>
                        <div class="color-info">Secondary from platform</div>
                    </div>
                    
                    <!-- Secondary with color inline -->
                    <div>
                        <button class="btn btn-secondary" style="color: #333;">
                            <i class="fas fa-cog"></i> Settings
                        </button>
                        <div class="color-info">Secondary with dark text</div>
                    </div>
                    
                    <!-- Normal secondary -->
                    <div>
                        <button class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <div class="color-info">Normal secondary</div>
                    </div>
                    
                    <!-- Secondary link -->
                    <div>
                        <a href="#" class="btn btn-secondary" style="color: rgb(100, 100, 100);">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <div class="color-info">Secondary link</div>
                    </div>
                </div>
            </div>
            
            <div class="test-section">
                <h3>Color Check Results</h3>
                <div id="test-results"></div>
            </div>
            
            <div class="test-section">
                <h3>Manual Fix Test</h3>
                <button class="btn btn-secondary" onclick="manualFix()">Force Fix All Buttons</button>
                <p class="mt-2">Click this button to manually trigger the color fix JavaScript</p>
            </div>
            
            <a href="dashboard.php" class="btn btn-success mt-3">Back to Dashboard</a>
        </div>
    </div>
    
    <script>
        function checkButtonColors() {
            const results = document.getElementById('test-results');
            let html = '<ul>';
            let passCount = 0;
            let totalCount = 0;
            
            // Check all primary and secondary buttons
            const buttons = document.querySelectorAll('.btn-primary, .btn-secondary');
            
            buttons.forEach((button, index) => {
                totalCount++;
                const computed = window.getComputedStyle(button);
                const color = computed.getPropertyValue('color');
                const rgb = color.match(/\d+/g);
                
                // Check if color is white (255, 255, 255)
                const isWhite = rgb && rgb[0] == 255 && rgb[1] == 255 && rgb[2] == 255;
                
                const buttonType = button.classList.contains('btn-primary') ? 'Primary' : 'Secondary';
                
                if (isWhite) {
                    passCount++;
                    html += `<li>${buttonType} Button ${index + 1}: <span style="color: green;">✓ WHITE (${color})</span></li>`;
                } else {
                    html += `<li>${buttonType} Button ${index + 1}: <span style="color: red;">✗ NOT WHITE (${color})</span></li>`;
                }
            });
            
            html += '</ul>';
            html += `<p><strong>Result: ${passCount}/${totalCount} buttons have white text</strong></p>`;
            
            if (passCount === totalCount) {
                html += '<div class="alert alert-success">✅ All buttons have white text!</div>';
            } else {
                html += '<div class="alert alert-danger">❌ Some buttons still have wrong text color</div>';
            }
            
            results.innerHTML = html;
        }
        
        function manualFix() {
            // Call the global fix function if available
            if (typeof fixPrimaryButtons === 'function') {
                fixPrimaryButtons();
                setTimeout(checkButtonColors, 100);
                alert('Fix applied! Check the results.');
            } else {
                alert('Fix function not loaded. Please refresh the page.');
            }
        }
        
        // Check colors after page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(checkButtonColors, 1500);
        });
    </script>
</body>
</html>