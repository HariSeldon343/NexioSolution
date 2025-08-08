# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nexio Platform - Multi-tenant collaborative document management system with companies, users, documents, calendars, and tickets. Runs on XAMPP (Windows WSL2) with PHP 8.0+ and MySQL.

## Environment & Database

- **Local Path**: `/mnt/c/xampp/htdocs/piattaforma-collaborativa/`
- **Database**: `nexiosol` (MySQL root/no password)
- **URL**: `http://localhost/piattaforma-collaborativa/`
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

# Setup & Testing
php scripts/setup-nexio-documentale.php             # Complete setup
php scripts/test-nexio-documentale.php              # Run tests
php scripts/monitor-nexio-performance.php           # Monitor performance

# XAMPP Control
/mnt/c/xampp/xampp start
/mnt/c/xampp/xampp stop
```

## Architecture

### File Organization
- **Frontend Pages**: Root directory PHP files (dashboard.php, aziende.php, filesystem.php, etc.)
- **Backend API**: `/backend/api/` - JSON REST endpoints
- **Middleware**: `/backend/middleware/Auth.php` - Session-based authentication (Singleton)
- **Database**: `/database/` - 100+ SQL migration files
- **Assets**: `/assets/js/`, `/assets/css/` - Frontend resources

### Authentication Flow
```php
// Standard auth pattern used across all pages
require_once 'backend/middleware/Auth.php';
$auth = Auth::getInstance();
$auth->requireAuth();  // Redirects to login if not authenticated

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();
```

### Multi-Tenant Database Pattern
```php
// Normal users - filter by company
if ($companyId) {
    $query = "SELECT * FROM documenti WHERE azienda_id = ?";
}
// Super admin - can see all or use NULL for global
if ($isSuperAdmin) {
    $query = "SELECT * FROM documenti WHERE azienda_id IS NULL OR azienda_id = ?";
}
```

### File System Management
- **Main Interface**: `filesystem.php`
- **API**: `backend/api/files-api.php`, `backend/api/upload-multiple.php`
- **JavaScript**: `assets/js/file-explorer.js` (class: FileExplorerManager)
- **Storage**: `/uploads/documenti/[azienda_id]/` (NULL folder for global files)

### Common Issues & Solutions

**CSRF Token Errors**
- Token in meta tag: `<meta name="csrf-token" content="...">`
- JS reads: `document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')`
- Backend validates via CSRFTokenManager

**File Upload Issues**
- `cartella_id`: Use NULL for root folder, not 0
- Always send `azienda_id` parameter
- Check `mime_type` column (not `file_type`)

**Delete Operations Return HTML Instead of JSON**
- Ensure proper JSON headers: `header('Content-Type: application/json')`
- Check for PHP errors/warnings that output before JSON

**Duplicate Records in Lists**
- Add `DISTINCT` to SELECT queries
- Check for JOIN conditions causing duplicates
- Verify database integrity (no ID=0 records)

### Key Database Tables
- `utenti` - Users with role field
- `aziende` - Companies (status: attiva/sospesa/cancellata)
- `utenti_aziende` - User-company associations
- `documenti` - Documents with file metadata
- `cartelle` - Folder hierarchy (parent_id for tree structure)
- `eventi` - Calendar events
- `tickets` - Support tickets
- `referenti` - Company contacts

### API Response Pattern
```php
// Success
echo json_encode(['success' => true, 'data' => $result]);

// Error
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Error message']);
```