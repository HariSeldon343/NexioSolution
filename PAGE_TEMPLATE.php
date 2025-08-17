<?php
/**
 * STANDARD PAGE TEMPLATE FOR NEXIO PLATFORM
 * 
 * Copy this template when creating new pages to ensure consistency
 * Replace placeholders with actual content
 */

// ============================================
// SECTION 1: CONFIGURATION & AUTHENTICATION
// ============================================
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

// Initialize authentication
$auth = Auth::getInstance();
$auth->requireAuth();

// Get user and company data
$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? null;
$isSuperAdmin = $auth->isSuperAdmin();

// ============================================
// SECTION 2: AUTHORIZATION CHECK
// ============================================

// Example: Check specific permissions
// if (!$auth->canAccess('module_name', 'action')) {
//     $_SESSION['error'] = "Accesso negato";
//     redirect(APP_PATH . '/dashboard.php');
// }

// Example: Super admin only
// if (!$isSuperAdmin) {
//     $_SESSION['error'] = "Solo super admin possono accedere";
//     redirect(APP_PATH . '/dashboard.php');
// }

// ============================================
// SECTION 3: PAGE LOGIC & DATA PROCESSING
// ============================================

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submissions
    // Validate CSRF token
    // require_once 'backend/utils/CSRFTokenManager.php';
    // CSRFTokenManager::validateRequest();
    
    // Your POST handling logic here
}

// Load page data
$pageData = [];
try {
    // Your database queries and data loading here
    // Example:
    // $stmt = db_query("SELECT * FROM table WHERE azienda_id = ?", [$aziendaId]);
    // $pageData = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Page error: " . $e->getMessage());
    $_SESSION['error'] = "Si è verificato un errore nel caricamento dei dati";
}

// ============================================
// SECTION 4: PAGE RENDERING
// ============================================

// Set page title and include header
$pageTitle = 'Page Title Here';
include 'components/header.php';

// Optional: Include page header component
// require_once 'components/page-header.php';
// renderPageHeader('Page Title', 'Page description', 'icon-class');
?>

<!-- ============================================
     SECTION 5: PAGE CONTENT
     ============================================ -->

<main class="main-content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-file"></i> Page Title
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo APP_PATH; ?>/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Current Page</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-auto">
                    <!-- Page actions buttons -->
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Action
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="row">
            <div class="col-12">
                
                <!-- Content Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Section Title</h5>
                    </div>
                    <div class="card-body">
                        <!-- Your page content here -->
                        <p>Page content goes here...</p>
                        
                        <!-- Example table structure -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Column 1</th>
                                        <th>Column 2</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pageData as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['field1'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['field2'] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
</main>

<!-- ============================================
     SECTION 6: PAGE-SPECIFIC JAVASCRIPT
     ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Page-specific JavaScript code here
    
    // Example: Initialize tooltips
    // var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    // var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    //     return new bootstrap.Tooltip(tooltipTriggerEl)
    // });
});
</script>

<!-- ============================================
     SECTION 7: FOOTER INCLUSION
     ============================================ -->
<?php include 'components/footer.php'; ?>

<!-- 
IMPORTANT NOTES FOR DEVELOPERS:

1. ALWAYS include both header.php and footer.php
2. ALWAYS wrap content in <main class="main-content">
3. Use Bootstrap 5 classes consistently
4. Avoid inline styles - use CSS classes
5. Handle errors gracefully with try-catch blocks
6. Validate and sanitize all user input
7. Check permissions before showing sensitive data
8. Use htmlspecialchars() for output to prevent XSS
9. Include CSRF token validation for POST requests
10. Log errors for debugging but show user-friendly messages

BOOTSTRAP UTILITIES TO USE:
- Spacing: mb-4, mt-3, p-3, etc.
- Display: d-flex, d-none, d-block
- Text: text-center, text-muted, text-danger
- Background: bg-light, bg-white
- Borders: border, rounded, border-0

AVOID:
- Inline styles (style="...")
- Mixed Bootstrap versions
- Direct database access without error handling
- Outputting data without htmlspecialchars()
- Hardcoded paths (use APP_PATH constant)
-->