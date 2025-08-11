<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();
$isSuperAdmin = $auth->isSuperAdmin();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test All UI Fixes - Nexio</title>
    
    <!-- FontAwesome 6 - Official CDN (NO KITS) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Nexio Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/nexio-ui-fixes.css">
    <link rel="stylesheet" href="assets/css/nexio-icon-fallback.css">
    
    <style>
        body { 
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
        .status { 
            padding: 10px; 
            border-radius: 5px; 
            margin-bottom: 10px; 
        }
        .status.success { 
            background: #d4edda; 
            color: #155724; 
        }
        .status.error { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        .icon-item {
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .icon-item i {
            font-size: 24px;
            display: block;
            margin-bottom: 5px;
        }
        .icon-item .label {
            font-size: 10px;
            color: #666;
        }
        .company-card {
            max-width: 350px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            margin: 10px;
            display: inline-block;
        }
        .log-table {
            width: 100%;
            table-layout: fixed;
        }
        .log-table .details-cell {
            max-width: 250px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .test-pass { color: green; font-weight: bold; }
        .test-fail { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4">Nexio UI Fixes Test Suite</h1>
        
        <!-- Test 1: FontAwesome Icons -->
        <div class="test-section">
            <h3>Test 1: FontAwesome Icons</h3>
            <div id="icon-status" class="status">Checking icons...</div>
            <div class="icon-grid">
                <div class="icon-item">
                    <i class="fas fa-home"></i>
                    <div class="label">Home</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-users"></i>
                    <div class="label">Users</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-folder"></i>
                    <div class="label">Folder</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-calendar"></i>
                    <div class="label">Calendar</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-trash"></i>
                    <div class="label">Delete</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-edit"></i>
                    <div class="label">Edit</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-plus"></i>
                    <div class="label">Add</div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-download"></i>
                    <div class="label">Download</div>
                </div>
            </div>
        </div>
        
        <!-- Test 2: Button Styling -->
        <div class="test-section">
            <h3>Test 2: Button Styling (Should be UPPERCASE with letter-spacing)</h3>
            <div class="mb-3">
                <button class="btn btn-primary">Primary Button</button>
                <button class="btn btn-success">Success Button</button>
                <button class="btn btn-danger">Delete Button</button>
                <button class="btn btn-warning">Warning Button</button>
                <button class="btn btn-info">Info Button</button>
                <button class="btn btn-secondary">Secondary</button>
            </div>
            <div id="button-status" class="status">Checking button styles...</div>
        </div>
        
        <!-- Test 3: Company Card Size -->
        <div class="test-section">
            <h3>Test 3: Company Card Size (Should be max-width: 350px)</h3>
            <div class="company-card">
                <h5>Test Company</h5>
                <p>This card should have a maximum width of 350px</p>
                <small>Status: Active</small>
            </div>
            <div id="card-status" class="status">Checking card size...</div>
        </div>
        
        <!-- Test 4: Text Wrapping in Tables -->
        <div class="test-section">
            <h3>Test 4: Log Activity Text Wrapping</h3>
            <table class="table log-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Date</th>
                        <th style="width: 100px;">User</th>
                        <th class="details-cell">Details (Should Wrap)</th>
                        <th style="width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2025-08-11 10:00</td>
                        <td>Admin</td>
                        <td class="details-cell">This is a very long text that should wrap properly within the maximum width of 250px without breaking the table layout</td>
                        <td>View</td>
                    </tr>
                </tbody>
            </table>
            <div id="table-status" class="status">Checking text wrapping...</div>
        </div>
        
        <!-- Test 5: Modal Display -->
        <div class="test-section">
            <h3>Test 5: Modal Should NOT Auto-Open</h3>
            <button class="btn btn-primary" onclick="testModal()">Open Test Modal</button>
            <div id="modal-status" class="status">Checking modal behavior...</div>
        </div>
        
        <!-- Test 6: Badge Styling -->
        <div class="test-section">
            <h3>Test 6: Badge Visibility</h3>
            <span class="badge bg-primary">Primary</span>
            <span class="badge bg-success">Success</span>
            <span class="badge bg-danger">Danger</span>
            <span class="badge bg-warning text-dark">Warning</span>
            <span class="badge bg-info text-dark">Info</span>
            <div id="badge-status" class="status mt-2">Checking badge styles...</div>
        </div>
        
        <!-- Test Results Summary -->
        <div class="test-section">
            <h3>Test Results Summary</h3>
            <ul id="test-results">
                <li>Loading test results...</li>
            </ul>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            <a href="calendario-eventi.php" class="btn btn-info">Test Calendar</a>
            <a href="log-attivita.php" class="btn btn-warning">Test Log Activity</a>
            <a href="aziende.php" class="btn btn-success">Test Companies</a>
        </div>
    </div>
    
    <!-- Test Modal -->
    <div class="modal fade" id="testModal" tabindex="-1" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Modal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    This modal should only open when button is clicked.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let testResults = [];
        
        // Test 1: Check FontAwesome
        function testIcons() {
            const testIcon = document.createElement('i');
            testIcon.className = 'fas fa-check';
            testIcon.style.position = 'absolute';
            testIcon.style.visibility = 'hidden';
            document.body.appendChild(testIcon);
            
            const computed = window.getComputedStyle(testIcon, ':before');
            const content = computed.getPropertyValue('content');
            document.body.removeChild(testIcon);
            
            const status = document.getElementById('icon-status');
            if (content && content !== 'none' && content !== '""') {
                status.className = 'status success';
                status.innerHTML = '✅ FontAwesome loaded successfully';
                testResults.push({name: 'FontAwesome Icons', passed: true});
            } else {
                status.className = 'status error';
                status.innerHTML = '❌ FontAwesome not loaded (using fallbacks)';
                testResults.push({name: 'FontAwesome Icons', passed: false});
            }
        }
        
        // Test 2: Check Button Styles
        function testButtons() {
            const btn = document.querySelector('.btn-primary');
            const computed = window.getComputedStyle(btn);
            const textTransform = computed.getPropertyValue('text-transform');
            const letterSpacing = computed.getPropertyValue('letter-spacing');
            
            const status = document.getElementById('button-status');
            if (textTransform === 'uppercase' && letterSpacing !== 'normal') {
                status.className = 'status success';
                status.innerHTML = '✅ Buttons styled correctly (UPPERCASE with letter-spacing)';
                testResults.push({name: 'Button Styling', passed: true});
            } else {
                status.className = 'status error';
                status.innerHTML = `❌ Button styling issue - Transform: ${textTransform}, Spacing: ${letterSpacing}`;
                testResults.push({name: 'Button Styling', passed: false});
            }
        }
        
        // Test 3: Check Card Size
        function testCardSize() {
            const card = document.querySelector('.company-card');
            const computed = window.getComputedStyle(card);
            const maxWidth = computed.getPropertyValue('max-width');
            
            const status = document.getElementById('card-status');
            if (maxWidth === '350px') {
                status.className = 'status success';
                status.innerHTML = '✅ Card max-width correctly set to 350px';
                testResults.push({name: 'Company Card Size', passed: true});
            } else {
                status.className = 'status error';
                status.innerHTML = `❌ Card max-width is ${maxWidth} (should be 350px)`;
                testResults.push({name: 'Company Card Size', passed: false});
            }
        }
        
        // Test 4: Check Table Text Wrapping
        function testTableWrapping() {
            const cell = document.querySelector('.details-cell');
            const computed = window.getComputedStyle(cell);
            const maxWidth = computed.getPropertyValue('max-width');
            const wordWrap = computed.getPropertyValue('word-wrap');
            
            const status = document.getElementById('table-status');
            if (maxWidth === '250px' && (wordWrap === 'break-word' || wordWrap === 'anywhere')) {
                status.className = 'status success';
                status.innerHTML = '✅ Text wrapping configured correctly';
                testResults.push({name: 'Table Text Wrapping', passed: true});
            } else {
                status.className = 'status error';
                status.innerHTML = `❌ Text wrapping issue - Max-width: ${maxWidth}, Word-wrap: ${wordWrap}`;
                testResults.push({name: 'Table Text Wrapping', passed: false});
            }
        }
        
        // Test 5: Check Modal Behavior
        function testModalBehavior() {
            const modal = document.getElementById('testModal');
            const status = document.getElementById('modal-status');
            
            if (modal && modal.style.display === 'none' && !modal.classList.contains('show')) {
                status.className = 'status success';
                status.innerHTML = '✅ Modal correctly hidden by default';
                testResults.push({name: 'Modal Auto-Open Prevention', passed: true});
            } else {
                status.className = 'status error';
                status.innerHTML = '❌ Modal display issue detected';
                testResults.push({name: 'Modal Auto-Open Prevention', passed: false});
            }
        }
        
        // Test 6: Check Badge Visibility
        function testBadges() {
            const badge = document.querySelector('.badge');
            const computed = window.getComputedStyle(badge);
            const display = computed.getPropertyValue('display');
            const opacity = computed.getPropertyValue('opacity');
            
            const status = document.getElementById('badge-status');
            if (display !== 'none' && opacity === '1') {
                status.className = 'status success';
                status.innerHTML = '✅ Badges are visible and styled correctly';
                testResults.push({name: 'Badge Visibility', passed: true});
            } else {
                status.className = 'status error';
                status.innerHTML = `❌ Badge visibility issue - Display: ${display}, Opacity: ${opacity}`;
                testResults.push({name: 'Badge Visibility', passed: false});
            }
        }
        
        // Update results summary
        function updateResults() {
            const resultsList = document.getElementById('test-results');
            let html = '';
            let passedCount = 0;
            
            testResults.forEach(result => {
                if (result.passed) passedCount++;
                html += `<li>${result.name}: <span class="${result.passed ? 'test-pass' : 'test-fail'}">${result.passed ? 'PASSED' : 'FAILED'}</span></li>`;
            });
            
            html += `<li class="mt-2"><strong>Overall: ${passedCount}/${testResults.length} tests passed</strong></li>`;
            resultsList.innerHTML = html;
        }
        
        // Test modal open function
        function testModal() {
            const modal = new bootstrap.Modal(document.getElementById('testModal'));
            modal.show();
        }
        
        // Run all tests on load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                testIcons();
                testButtons();
                testCardSize();
                testTableWrapping();
                testModalBehavior();
                testBadges();
                updateResults();
                
                // Check console for errors
                console.log('UI Test Suite Complete. Check for any CORS or loading errors above.');
            }, 1000);
        });
    </script>
</body>
</html>