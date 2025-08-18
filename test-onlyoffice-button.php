<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Button</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 50px;
            background: #f5f5f5;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .file-card {
            border: 1px solid #ddd;
            padding: 20px;
            margin: 10px;
            border-radius: 8px;
            text-align: center;
            background: #fafafa;
        }
        .action-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #28a745;
            border-radius: 8px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            margin: 10px;
        }
        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.5);
        }
        .action-btn i {
            font-size: 18px;
            pointer-events: none;
        }
        .debug-output {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Test OnlyOffice Button</h1>
        
        <div class="alert alert-info">
            <strong>Test Scenarios:</strong>
            <ol>
                <li>Click diretto con onclick inline</li>
                <li>Click con event listener separato</li>
                <li>Click con event delegation</li>
            </ol>
        </div>

        <div class="file-card">
            <h4>Test 1: Inline onclick</h4>
            <button class="action-btn btn-onlyoffice" 
                    onclick="testEditDocument(event, 123)" 
                    title="Test inline onclick">
                <i class="fas fa-file-word"></i>
            </button>
        </div>

        <div class="file-card">
            <h4>Test 2: Event Listener</h4>
            <button id="test-btn-2" 
                    class="action-btn btn-onlyoffice" 
                    data-file-id="456"
                    title="Test event listener">
                <i class="fas fa-file-word"></i>
            </button>
        </div>

        <div class="file-card">
            <h4>Test 3: Dinamically Added</h4>
            <div id="dynamic-container"></div>
            <button class="btn btn-primary mt-2" onclick="addDynamicButton()">
                Add Dynamic Button
            </button>
        </div>

        <div class="debug-output" id="debug-output">
            Debug Output:
        </div>
    </div>

    <script>
        // Debug helper
        function log(message) {
            console.log(message);
            const output = document.getElementById('debug-output');
            output.textContent += '\n' + new Date().toISOString().substr(11, 8) + ' - ' + message;
        }

        // Test function similar to the real one
        function testEditDocument(event, fileId) {
            log('testEditDocument called with fileId: ' + fileId);
            
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            if (!fileId) {
                log('ERROR: fileId is missing');
                alert('Error: fileId missing');
                return;
            }
            
            const url = 'onlyoffice-editor.php?id=' + fileId;
            log('Attempting to open: ' + url);
            
            try {
                const newWindow = window.open(url, '_blank');
                if (!newWindow) {
                    log('WARNING: Popup might be blocked');
                    if (confirm('Popup blocked. Open in same window?')) {
                        window.location.href = url;
                    }
                } else {
                    log('SUCCESS: Window opened');
                }
            } catch (error) {
                log('ERROR: ' + error.message);
                alert('Error opening editor: ' + error.message);
            }
        }

        // Test 2: Event listener
        document.getElementById('test-btn-2').addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const fileId = this.getAttribute('data-file-id');
            log('Event listener test - fileId: ' + fileId);
            testEditDocument(e, fileId);
        });

        // Test 3: Dynamic button
        function addDynamicButton() {
            const container = document.getElementById('dynamic-container');
            const randomId = Math.floor(Math.random() * 1000);
            container.innerHTML = `
                <button class="action-btn btn-onlyoffice dynamic-btn" 
                        onclick="testEditDocument(event, ${randomId})"
                        data-file-id="${randomId}"
                        title="Dynamic button">
                    <i class="fas fa-file-word"></i>
                </button>
                <p>Dynamic button with ID: ${randomId}</p>
            `;
            log('Added dynamic button with ID: ' + randomId);
        }

        // Event delegation for dynamic buttons
        document.addEventListener('click', function(e) {
            const button = e.target.closest('.dynamic-btn');
            if (button) {
                log('Delegation caught click on dynamic button');
                // The onclick will handle it, but this shows delegation works
            }
        });

        // Initial log
        log('Test page loaded and ready');
        
        // Check if window.open is available
        if (typeof window.open === 'function') {
            log('window.open is available');
        } else {
            log('WARNING: window.open is NOT available!');
        }
    </script>
</body>
</html>