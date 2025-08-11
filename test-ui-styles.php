<?php
/**
 * Test UI Styles Page
 * Verifies that all UI components are properly styled
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = "Test UI Styles";
$additionalCSS = [];
$additionalJS = [];

require_once 'components/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">UI Components Test Page</h1>
    
    <!-- Test Icons -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>FontAwesome Icons Test</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Sidebar Icons:</h5>
                    <p>
                        <i class="fas fa-home"></i> Home |
                        <i class="fas fa-folder-open"></i> Folder |
                        <i class="fas fa-calendar-alt"></i> Calendar |
                        <i class="fas fa-tasks"></i> Tasks |
                        <i class="fas fa-headset"></i> Headset
                    </p>
                    <p>
                        <i class="fas fa-clipboard-list"></i> Clipboard |
                        <i class="fas fa-building"></i> Building |
                        <i class="fas fa-users"></i> Users |
                        <i class="fas fa-user-tie"></i> User Tie |
                        <i class="fas fa-cog"></i> Settings
                    </p>
                </div>
                <div class="col-md-6">
                    <h5>Action Icons:</h5>
                    <p>
                        <i class="fas fa-plus"></i> Plus |
                        <i class="fas fa-edit"></i> Edit |
                        <i class="fas fa-trash"></i> Trash |
                        <i class="fas fa-download"></i> Download |
                        <i class="fas fa-upload"></i> Upload
                    </p>
                    <p>
                        <i class="fas fa-search"></i> Search |
                        <i class="fas fa-filter"></i> Filter |
                        <i class="fas fa-sort"></i> Sort |
                        <i class="fas fa-check"></i> Check |
                        <i class="fas fa-times"></i> Times
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Test Badges -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Badges Test</h3>
        </div>
        <div class="card-body">
            <p>
                <span class="badge badge-primary">Super Admin</span>
                <span class="badge badge-success">Admin</span>
                <span class="badge badge-warning">Utente Speciale</span>
                <span class="badge badge-secondary">Utente</span>
                <span class="badge badge-info">Info</span>
                <span class="badge badge-danger">Danger</span>
            </p>
            <p>
                <span class="badge bg-primary">BG Primary</span>
                <span class="badge bg-success">BG Success</span>
                <span class="badge bg-warning">BG Warning</span>
                <span class="badge bg-secondary">BG Secondary</span>
                <span class="badge bg-info">BG Info</span>
                <span class="badge bg-danger">BG Danger</span>
            </p>
        </div>
    </div>
    
    <!-- Test Buttons -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Buttons Test</h3>
        </div>
        <div class="card-body">
            <h5>Standard Buttons:</h5>
            <p>
                <button class="btn btn-primary">Primary</button>
                <button class="btn btn-success">Success</button>
                <button class="btn btn-danger">Danger</button>
                <button class="btn btn-warning">Warning</button>
                <button class="btn btn-info">Info</button>
                <button class="btn btn-secondary">Secondary</button>
            </p>
            
            <h5>Outline Buttons:</h5>
            <p>
                <button class="btn btn-outline-primary">Outline Primary</button>
                <button class="btn btn-outline-danger">Outline Danger</button>
                <button class="btn btn-outline-success">Outline Success</button>
                <button class="btn btn-outline-warning">Outline Warning</button>
            </p>
            
            <h5>Button Sizes:</h5>
            <p>
                <button class="btn btn-primary btn-sm">Small Button</button>
                <button class="btn btn-primary">Normal Button</button>
                <button class="btn btn-primary btn-lg">Large Button</button>
            </p>
        </div>
    </div>
    
    <!-- Test Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Table Test</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>John Doe</td>
                        <td><span class="badge badge-primary">Super Admin</span></td>
                        <td><span class="badge badge-success">Active</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                        </td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Jane Smith</td>
                        <td><span class="badge badge-warning">Utente Speciale</span></td>
                        <td><span class="badge badge-success">Active</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                        </td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Bob Johnson</td>
                        <td><span class="badge badge-secondary">Utente</span></td>
                        <td><span class="badge badge-danger">Inactive</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Test Forms -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Form Elements Test</h3>
        </div>
        <div class="card-body">
            <form>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Text Input</label>
                            <input type="text" class="form-control" placeholder="Enter text">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Input</label>
                            <input type="email" class="form-control" placeholder="Enter email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Input</label>
                            <input type="password" class="form-control" placeholder="Enter password">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Select</label>
                            <select class="form-select">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Textarea</label>
                            <textarea class="form-control" rows="3" placeholder="Enter text"></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Submit Form</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </form>
        </div>
    </div>
    
    <!-- Test Alerts -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Alerts Test</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> This is a success alert
            </div>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> This is a danger alert
            </div>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-circle"></i> This is a warning alert
            </div>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> This is an info alert
            </div>
        </div>
    </div>
    
    <!-- Typography Test -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Typography Test</h3>
        </div>
        <div class="card-body">
            <h1>Heading 1</h1>
            <h2>Heading 2</h2>
            <h3>Heading 3</h3>
            <h4>Heading 4</h4>
            <h5>Heading 5</h5>
            <h6>Heading 6</h6>
            <p>This is a paragraph with <strong>bold text</strong>, <em>italic text</em>, and <a href="#">a link</a>.</p>
            <p class="text-muted">This is muted text.</p>
            <p>
                <span class="text-primary">Primary</span> |
                <span class="text-success">Success</span> |
                <span class="text-danger">Danger</span> |
                <span class="text-warning">Warning</span> |
                <span class="text-info">Info</span> |
                <span class="text-secondary">Secondary</span>
            </p>
        </div>
    </div>
</div>

<style>
/* Additional inline styles for testing */
.card {
    margin-bottom: 1.5rem;
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
}

i[class*="fa-"] {
    margin-right: 0.5rem;
}
</style>

<script>
// Test if FontAwesome is loaded
document.addEventListener('DOMContentLoaded', function() {
    const testIcon = document.createElement('i');
    testIcon.className = 'fas fa-home';
    testIcon.style.position = 'absolute';
    testIcon.style.visibility = 'hidden';
    document.body.appendChild(testIcon);
    
    const computed = window.getComputedStyle(testIcon, ':before');
    const content = computed.getPropertyValue('content');
    
    document.body.removeChild(testIcon);
    
    if (!content || content === 'none' || content === '') {
        console.error('FontAwesome is NOT loaded properly!');
        alert('FontAwesome icons are not loading. Please check the console for errors.');
    } else {
        console.log('FontAwesome is loaded successfully!');
    }
});
</script>

<?php require_once 'components/footer.php'; ?>