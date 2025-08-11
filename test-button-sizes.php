<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Button Sizes - Nexio</title>
    
    <!-- Include all standard headers -->
    <?php 
    $pageTitle = "Test Button Sizes";
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
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
            align-items: center;
        }
        .size-info {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        .measurement {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
        .ruler {
            background: linear-gradient(to right, #ddd 1px, transparent 1px);
            background-size: 10px 100%;
            height: 50px;
            border: 1px solid #999;
            position: relative;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="test-container">
            <h1>Button Size Test</h1>
            
            <div class="test-section">
                <h3>Problematic Ticket Button (Should be normalized)</h3>
                
                <!-- Exact copy of oversized button -->
                <div class="button-grid">
                    <a href="tickets.php?action=nuovo" class="btn btn-primary" style="opacity: 1; visibility: visible; background-color: rgb(59, 130, 246); color: rgb(255, 255, 255) !important; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 500; border-color: rgb(13, 110, 253) !important; transform: translateY(0px);" data-original-color="rgb(255, 255, 255)">
                        <i class="fas fa-plus" style="color: rgb(255, 255, 255) !important;"></i> Crea il primo ticket
                    </a>
                    <div class="size-info">Original oversized button</div>
                </div>
                <div class="measurement" id="ticket-btn-size"></div>
            </div>
            
            <div class="test-section">
                <h3>Standard Button Sizes (Reference)</h3>
                <div class="button-grid">
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i> Normal Primary
                    </button>
                    <button class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Normal Secondary
                    </button>
                    <button class="btn btn-success">
                        <i class="fas fa-check"></i> Normal Success
                    </button>
                    <button class="btn btn-sm btn-primary">
                        <i class="fas fa-star"></i> Small
                    </button>
                    <button class="btn btn-lg btn-primary">
                        <i class="fas fa-rocket"></i> Large
                    </button>
                </div>
                <div class="measurement" id="standard-sizes"></div>
            </div>
            
            <div class="test-section">
                <h3>Buttons with Excessive Inline Styles</h3>
                <div class="button-grid">
                    <button class="btn btn-primary" style="height: 60px; padding: 20px 40px; font-size: 24px;">
                        <i class="fas fa-home" style="font-size: 32px;"></i> Oversized
                    </button>
                    <button class="btn btn-secondary" style="height: 50px; padding: 15px 30px;">
                        <i class="fas fa-cog" style="font-size: 24px;"></i> Too Big
                    </button>
                    <a href="#" class="btn btn-success" style="height: 44px; font-size: 18px; padding: 10px 25px;">
                        <i class="fas fa-download" style="font-size: 20px;"></i> Download
                    </a>
                </div>
                <div class="measurement" id="oversized-sizes"></div>
            </div>
            
            <div class="test-section">
                <h3>Icon Size Test</h3>
                <div class="button-grid">
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i> Icon Test 1
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-folder" style="font-size: 24px;"></i> Icon Test 2
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-user" style="font-size: 32px;"></i> Icon Test 3
                    </button>
                </div>
                <div class="measurement" id="icon-sizes"></div>
            </div>
            
            <div class="test-section">
                <h3>Size Analysis</h3>
                <div id="analysis"></div>
                <button class="btn btn-warning mt-3" onclick="forceFixSizes()">Force Fix All Sizes</button>
            </div>
            
            <div class="mt-4">
                <a href="dashboard.php" class="btn btn-success">Back to Dashboard</a>
                <a href="tickets.php" class="btn btn-info">Go to Tickets</a>
            </div>
        </div>
    </div>
    
    <script>
        function measureButtons() {
            // Measure ticket button
            const ticketBtn = document.querySelector('a[href*="tickets.php?action=nuovo"]');
            if (ticketBtn) {
                const rect = ticketBtn.getBoundingClientRect();
                const computed = window.getComputedStyle(ticketBtn);
                const icon = ticketBtn.querySelector('i');
                const iconSize = icon ? window.getComputedStyle(icon).fontSize : 'N/A';
                
                document.getElementById('ticket-btn-size').innerHTML = 
                    `Ticket Button: Width=${rect.width.toFixed(1)}px, Height=${rect.height.toFixed(1)}px, ` +
                    `Font=${computed.fontSize}, Padding=${computed.padding}, Icon=${iconSize}`;
            }
            
            // Measure standard buttons
            let standardHtml = '';
            document.querySelectorAll('.test-section:nth-child(3) .btn').forEach((btn, i) => {
                const rect = btn.getBoundingClientRect();
                const computed = window.getComputedStyle(btn);
                standardHtml += `Button ${i+1}: ${rect.width.toFixed(1)}x${rect.height.toFixed(1)}px, Font=${computed.fontSize}<br>`;
            });
            document.getElementById('standard-sizes').innerHTML = standardHtml;
            
            // Measure oversized buttons
            let oversizedHtml = '';
            document.querySelectorAll('.test-section:nth-child(4) .btn').forEach((btn, i) => {
                const rect = btn.getBoundingClientRect();
                const computed = window.getComputedStyle(btn);
                oversizedHtml += `Button ${i+1}: ${rect.width.toFixed(1)}x${rect.height.toFixed(1)}px, Font=${computed.fontSize}<br>`;
            });
            document.getElementById('oversized-sizes').innerHTML = oversizedHtml;
            
            // Measure icon sizes
            let iconHtml = '';
            document.querySelectorAll('.test-section:nth-child(5) .btn i').forEach((icon, i) => {
                const computed = window.getComputedStyle(icon);
                iconHtml += `Icon ${i+1}: ${computed.fontSize}<br>`;
            });
            document.getElementById('icon-sizes').innerHTML = iconHtml;
            
            // Analysis
            analyzeButtons();
        }
        
        function analyzeButtons() {
            const buttons = document.querySelectorAll('.btn');
            let oversizedCount = 0;
            let correctCount = 0;
            
            buttons.forEach(btn => {
                const rect = btn.getBoundingClientRect();
                if (rect.height > 40) {
                    oversizedCount++;
                } else {
                    correctCount++;
                }
            });
            
            const icons = document.querySelectorAll('.btn i');
            let oversizedIcons = 0;
            
            icons.forEach(icon => {
                const fontSize = parseInt(window.getComputedStyle(icon).fontSize);
                if (fontSize > 16) {
                    oversizedIcons++;
                }
            });
            
            const analysisHtml = `
                <h4>Results:</h4>
                <ul>
                    <li>Total buttons: ${buttons.length}</li>
                    <li>Correctly sized buttons (≤40px height): ${correctCount}</li>
                    <li>Oversized buttons (>40px height): ${oversizedCount}</li>
                    <li>Total icons in buttons: ${icons.length}</li>
                    <li>Oversized icons (>16px): ${oversizedIcons}</li>
                </ul>
                <div class="alert ${oversizedCount === 0 ? 'alert-success' : 'alert-warning'}">
                    ${oversizedCount === 0 ? '✅ All buttons are properly sized!' : '⚠️ Some buttons need size normalization'}
                </div>
            `;
            
            document.getElementById('analysis').innerHTML = analysisHtml;
        }
        
        function forceFixSizes() {
            // Call the global fix functions if available
            if (typeof fixButtonSizes === 'function') {
                fixButtonSizes();
                setTimeout(measureButtons, 200);
                alert('Size fix applied! Check the measurements.');
            } else {
                alert('Size fix function not loaded. Please refresh the page.');
            }
        }
        
        // Measure on load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(measureButtons, 1000);
            
            // Auto-apply fix after 1.5 seconds
            if (typeof fixButtonSizes === 'function') {
                setTimeout(() => {
                    fixButtonSizes();
                    setTimeout(measureButtons, 200);
                }, 1500);
            }
        });
    </script>
</body>
</html>