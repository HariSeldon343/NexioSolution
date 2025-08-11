<?php
require_once 'backend/config/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Icons - Nexio</title>
    
    <!-- FontAwesome 6 - Official CDN Only (NO KITS) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- FontAwesome 5 Fallback -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            padding: 20px;
            background: #f5f5f5;
        }
        .icon-test {
            display: inline-block;
            width: 150px;
            text-align: center;
            padding: 20px;
            margin: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .icon-test i {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
        .icon-test .name {
            font-size: 12px;
            color: #666;
        }
        .status {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>FontAwesome Icon Test</h1>
        
        <div id="status" class="status">
            Checking FontAwesome loading status...
        </div>
        
        <h2>Common Icons Test</h2>
        <div class="row">
            <div class="col-auto icon-test">
                <i class="fas fa-home"></i>
                <div class="name">fa-home</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-users"></i>
                <div class="name">fa-users</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-folder"></i>
                <div class="name">fa-folder</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-calendar"></i>
                <div class="name">fa-calendar</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-bell"></i>
                <div class="name">fa-bell</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-cog"></i>
                <div class="name">fa-cog</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-trash"></i>
                <div class="name">fa-trash</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-edit"></i>
                <div class="name">fa-edit</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-plus"></i>
                <div class="name">fa-plus</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-times"></i>
                <div class="name">fa-times</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-check"></i>
                <div class="name">fa-check</div>
            </div>
            <div class="col-auto icon-test">
                <i class="fas fa-download"></i>
                <div class="name">fa-download</div>
            </div>
        </div>
        
        <h2>Button Test</h2>
        <div class="mb-3">
            <button class="btn btn-primary"><i class="fas fa-plus"></i> Add New</button>
            <button class="btn btn-success"><i class="fas fa-check"></i> Save</button>
            <button class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
            <button class="btn btn-warning"><i class="fas fa-edit"></i> Edit</button>
            <button class="btn btn-info"><i class="fas fa-download"></i> Download</button>
            <button class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
        </div>
        
        <h2>Console Check</h2>
        <p>Open browser console (F12) to check for any CORS or loading errors.</p>
        
        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    </div>
    
    <script>
        // Check if FontAwesome loaded
        document.addEventListener('DOMContentLoaded', function() {
            const testIcon = document.createElement('i');
            testIcon.className = 'fas fa-check';
            testIcon.style.position = 'absolute';
            testIcon.style.visibility = 'hidden';
            document.body.appendChild(testIcon);
            
            const computed = window.getComputedStyle(testIcon, ':before');
            const content = computed.getPropertyValue('content');
            document.body.removeChild(testIcon);
            
            const statusDiv = document.getElementById('status');
            
            if (content && content !== 'none' && content !== '""') {
                statusDiv.className = 'status success';
                statusDiv.innerHTML = '✅ FontAwesome loaded successfully! Icons should be visible.';
            } else {
                statusDiv.className = 'status error';
                statusDiv.innerHTML = '❌ FontAwesome failed to load. Unicode fallbacks will be used.';
            }
            
            // Check for console errors
            console.log('FontAwesome test complete. Check for any CORS errors above.');
        });
    </script>
</body>
</html>