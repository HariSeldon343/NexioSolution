# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nexio Platform - Multi-tenant collaborative document management system with companies, users, documents, calendars, and tickets. Built with PHP 8.0+ and MySQL, running on XAMPP (Windows WSL2).

## Environment & Database

- **Local Path**: `/mnt/c/xampp/htdocs/piattaforma-collaborativa/`
- **Database**: `nexiosol` (MySQL root/no password)
- **URL**: `http://localhost/piattaforma-collaborativa/`
- **PHP Version**: 8.0+ (via `/mnt/c/xampp/php/php.exe`)
- **User Roles**: `super_admin`, `utente_speciale`, `utente`

## Critical Commands

```bash
# MySQL Operations (Windows XAMPP)
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < database/[file].sql
/mnt/c/xampp/mysql/bin/mysql.exe -u root -e "SHOW TABLES;" nexiosol

# PHP Validation & Execution
/mnt/c/xampp/php/php.exe -l [file].php              # Check syntax
/mnt/c/xampp/php/php.exe [script].php               # Run script

# Setup & Maintenance Scripts
/mnt/c/xampp/php/php.exe scripts/setup-nexio-documentale.php    # Complete setup
/mnt/c/xampp/php/php.exe scripts/monitor-nexio-performance.php  # Monitor performance
/mnt/c/xampp/php/php.exe scripts/setup-company-folders.php      # Create company folders

# XAMPP Control
/mnt/c/xampp/xampp start
/mnt/c/xampp/xampp stop
/mnt/c/xampp/xampp status

# Composer (when available)
composer install --no-dev --optimize-autoloader
composer test                                       # Run PHPUnit tests
```

## Architecture

### Directory Structure
- **Frontend Pages**: Root directory PHP files (dashboard.php, aziende.php, filesystem.php, calendario.php, tickets.php)
- **Backend API**: `/backend/api/` - JSON REST endpoints (60+ API files)
- **Middleware**: `/backend/middleware/` - Auth.php (Singleton), PermissionMiddleware.php
- **Models**: `/backend/models/` - User, Template, DocumentVersion, AdvancedDocument
- **Utils**: `/backend/utils/` - ActivityLogger, CSRFTokenManager, Mailer, PermissionManager
- **Database**: `/database/` - 100+ SQL migration files
- **Assets**: `/assets/js/`, `/assets/css/` - Frontend resources with multiple UI fix layers
- **Components**: `/components/` - Reusable PHP components (header.php, sidebar.php, menu.php)

### Authentication & Session Management
```php
// Standard auth pattern - ALWAYS use this in pages
require_once 'backend/middleware/Auth.php';
$auth = Auth::getInstance();
$auth->requireAuth();  // Redirects to login if not authenticated

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();

// Permission checks
if ($auth->canAccess('module', 'action')) { /* ... */ }
if ($auth->canDeleteLogs()) { /* ... */ }
```

### Multi-Tenant Database Pattern
```php
// Normal users - filter by company
if ($aziendaId) {
    $query = "SELECT * FROM documenti WHERE azienda_id = ?";
    $params = [$aziendaId];
}

// Super admin - can see all or use NULL for global
if ($isSuperAdmin) {
    $query = "SELECT * FROM documenti WHERE azienda_id IS NULL OR azienda_id = ?";
    $params = [$aziendaId];  // Can be NULL for global items
}

// Global resources use azienda_id = NULL
$globalQuery = "SELECT * FROM cartelle WHERE azienda_id IS NULL";
```

### Database Connection Pattern
```php
// Use db_query() helper function (from config.php)
$stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$userId]);
$user = $stmt->fetch();

// Transaction example
db_connection()->beginTransaction();
try {
    db_query("INSERT INTO ...", $params);
    db_query("UPDATE ...", $params);
    db_connection()->commit();
} catch (Exception $e) {
    db_connection()->rollback();
    throw $e;
}
```

### API Development Pattern
All APIs should follow this structure:
```php
// 1. Auth check
require_once '../middleware/Auth.php';
$auth = Auth::getInstance();
$auth->requireAuth();

// 2. CSRF validation (for POST/PUT/DELETE)
require_once '../utils/CSRFTokenManager.php';
CSRFTokenManager::validateRequest();

// 3. JSON response headers
header('Content-Type: application/json');

// 4. Response format
echo json_encode(['success' => true, 'data' => $result]);
// OR
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Error message']);
```

### File System Management
- **Main Interface**: `filesystem.php`
- **Primary APIs**: `backend/api/folders-api.php`, `backend/api/filesystem-simple-api.php`
- **Upload**: `backend/api/simple-upload.php`, `backend/api/upload-multiple.php`
- **Storage Path**: `/uploads/documenti/[azienda_id]/` (NULL folder for global files)
- **Key Points**:
  - Use `cartella_id = NULL` for root folder (never 0)
  - Always include `azienda_id` in requests
  - Check `mime_type` column (not `file_type`)

### CSS Architecture (Multiple Fix Layers)
The project uses layered CSS fixes to address UI issues:
1. `style.css` - Base styles
2. `nexio-improvements.css` - Initial improvements
3. `nexio-color-fixes.css` - Color and contrast fixes
4. `nexio-ui-complete.css` - Comprehensive UI system
5. `nexio-urgent-fixes.css` - Critical visibility fixes
6. `nexio-button-white-text.css` - Forces white text on buttons
7. `nexio-table-simple.css` - Clean table styles
8. `log-attivita.css` - Dedicated log table styles
9. `log-details-fix.css` - Expandable panel fixes

### Common Issues & Solutions

**CSRF Token Errors**
- Token in meta tag: `<meta name="csrf-token" content="<?php echo $_SESSION["csrf_token"]; ?>">`
- JS reads: `document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')`
- Backend validates: `CSRFTokenManager::validateRequest()`

**Session Issues**
- Auth uses Singleton pattern - always use `Auth::getInstance()`
- Session starts automatically in Auth constructor
- Company switching: `backend/api/switch-azienda.php`

**Delete Operations Return HTML Instead of JSON**
- Add `header('Content-Type: application/json')` before any output
- Check for PHP errors/warnings that output before JSON
- Use `error_reporting(0)` in production

**Table Display Issues**
- Use dedicated CSS files (log-attivita.css, nexio-table-simple.css)
- Avoid inline styles - they interfere with responsive design
- Check for JavaScript that adds inline styles

### Key Database Tables
- `utenti` - Users with role field (super_admin, utente_speciale, utente)
- `aziende` - Companies (status: attiva/sospesa/cancellata)
- `utenti_aziende` - User-company associations
- `documenti` - Documents with file metadata
- `cartelle` - Folder hierarchy (parent_id for tree structure)
- `eventi` - Calendar events (tipo: evento/meeting/scadenza/compleanno)
- `tickets` - Support tickets (stato: aperto/in_lavorazione/chiuso)
- `referenti` - Company contacts
- `log_attivita` - Activity logs (non_eliminabile flag for protected logs)
- `tasks` - Task management integrated with calendar

### Testing & Development

**Create Test Pages**
When testing UI fixes, create standalone test pages:
```php
// test-[feature].php
define('APP_PATH', '/piattaforma-collaborativa');
$pageTitle = 'Test Feature';
require_once 'components/header.php';
// Test content here
require_once 'components/footer.php';
```

**Debug Database Issues**
```php
// Enable error logging
error_log("DEBUG: " . print_r($data, true));

// Check if table exists
$stmt = db_query("SHOW TABLES LIKE 'table_name'");
if ($stmt->rowCount() > 0) { /* exists */ }

// Verify column exists
$stmt = db_query("SHOW COLUMNS FROM table_name LIKE 'column_name'");
```

### Mobile App Integration
- API endpoints in `/backend/api/mobile-*.php`
- Authentication: `mobile-auth-api.php`
- Events: `mobile-events-api.php`
- Tasks: `mobile-tasks-api.php`
- Companies: `mobile-companies-api.php`