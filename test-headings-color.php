<?php
require_once 'backend/config/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Headings Color - Nexio</title>
    
    <!-- Include all standard headers to get all CSS -->
    <?php 
    $pageTitle = "Test Headings";
    include 'components/header.php'; 
    ?>
    
    <style>
        .test-container {
            padding: 20px;
            background: #2c3e50; /* Dark background to see white text */
        }
        .test-section {
            background: #34495e;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .color-info {
            font-size: 12px;
            color: #bbb;
            margin-top: 5px;
        }
        .calendar-simulation {
            background: #4a5568;
            padding: 20px;
            border-radius: 8px;
        }
        .test-result {
            background: white;
            color: #333;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="test-container">
            <h1 style="color: white;">Heading Color Test</h1>
            
            <div class="test-section">
                <h3 style="color: white;">Calendar Month Headings (Should be WHITE)</h3>
                
                <!-- Simulate calendar structure -->
                <div class="calendar">
                    <div class="calendar-header">
                        <h2>August 2025</h2>
                        <div class="color-info">Calendar month/year heading</div>
                    </div>
                </div>
                
                <div class="calendario mt-3">
                    <h2>September 2025</h2>
                    <div class="color-info">Alternative calendar class</div>
                </div>
                
                <div id="calendar" class="mt-3">
                    <h2>October 2025</h2>
                    <div class="color-info">Calendar with ID</div>
                </div>
                
                <div class="calendar-simulation mt-3">
                    <h2>November 2025</h2>
                    <div class="color-info">Generic h2 with year</div>
                </div>
                
                <div class="mt-3">
                    <h2 class="month-title">December 2025</h2>
                    <div class="color-info">H2 with month-title class</div>
                </div>
                
                <div class="mt-3">
                    <h2 class="fc-toolbar-title">January 2026</h2>
                    <div class="color-info">FullCalendar toolbar title</div>
                </div>
            </div>
            
            <div class="test-section">
                <h3 style="color: white;">Different Month Formats</h3>
                <h2>Gennaio 2025</h2>
                <h2>February 2025</h2>
                <h2>März 2025</h2>
                <h2>April 2025</h2>
                <h2>May 2025</h2>
                <h2>Giugno 2025</h2>
                <h2>July 2025</h2>
                <h2>Agosto 2025</h2>
            </div>
            
            <div class="test-section">
                <h3 style="color: white;">Other Headings (for comparison)</h3>
                <h2>Regular H2 Title</h2>
                <h2 style="color: #333;">H2 with inline dark color</h2>
                <h2 class="text-primary">H2 with text-primary class</h2>
            </div>
            
            <div class="test-result">
                <h3>Color Check Results</h3>
                <div id="test-results"></div>
            </div>
            
            <div class="mt-4">
                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                <a href="calendario-eventi.php" class="btn btn-info">Go to Calendar</a>
                <button class="btn btn-warning" onclick="forceFixHeadings()">Force Fix Headings</button>
            </div>
        </div>
    </div>
    
    <script>
        function checkHeadingColors() {
            const results = document.getElementById('test-results');
            let html = '<ul>';
            let passCount = 0;
            let totalCount = 0;
            
            // Check all h2 elements
            const headings = document.querySelectorAll('h2');
            
            headings.forEach((heading, index) => {
                totalCount++;
                const computed = window.getComputedStyle(heading);
                const color = computed.getPropertyValue('color');
                const rgb = color.match(/\d+/g);
                
                // Check if color is white (255, 255, 255)
                const isWhite = rgb && rgb[0] == 255 && rgb[1] == 255 && rgb[2] == 255;
                
                const text = heading.textContent.substring(0, 30);
                
                if (isWhite) {
                    passCount++;
                    html += `<li>"${text}": <span style="color: green;">✓ WHITE (${color})</span></li>`;
                } else {
                    html += `<li>"${text}": <span style="color: red;">✗ NOT WHITE (${color})</span></li>`;
                }
            });
            
            html += '</ul>';
            html += `<p><strong>Result: ${passCount}/${totalCount} h2 headings have white text</strong></p>`;
            
            if (passCount === totalCount) {
                html += '<div class="alert alert-success">✅ All headings have white text!</div>';
            } else {
                html += '<div class="alert alert-danger">❌ Some headings still have wrong text color</div>';
            }
            
            results.innerHTML = html;
        }
        
        function forceFixHeadings() {
            // Call the global fix functions if available
            if (typeof fixHeadingColors === 'function') {
                fixHeadingColors();
            }
            if (typeof fixPrimaryButtons === 'function') {
                fixPrimaryButtons();
            }
            setTimeout(checkHeadingColors, 100);
            alert('Fix applied! Check the results.');
        }
        
        // Check colors after page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for all CSS and JS to apply
            setTimeout(checkHeadingColors, 1500);
            
            // Also trigger the fix automatically
            if (typeof fixHeadingColors === 'function') {
                setTimeout(fixHeadingColors, 1000);
                setTimeout(checkHeadingColors, 2000);
            }
        });
    </script>
</body>
</html>