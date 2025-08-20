# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nexio Platform - Enterprise multi-tenant collaborative document management system with ISO compliance automation. Features include hierarchical document management, calendar integration, ticket system, task management, OnlyOffice document editing integration, and mobile applications. Built with PHP 8.0+ and MySQL, currently running on XAMPP (Windows WSL2) with 100+ database tables, 60+ API endpoints, and comprehensive security features.

## Environment & Database

- **Local Path**: `/mnt/c/xampp/htdocs/piattaforma-collaborativa/`
- **Database**: `nexiosol` (MySQL root/no password)
- **URL**: `http://localhost/piattaforma-collaborativa/`
- **PHP Version**: 8.0+ (via `/mnt/c/xampp/php/php.exe`)
- **User Roles**: `super_admin`, `utente_speciale`, `utente`

### Environment Configuration
Copy `.env.example` to `.env` and configure:
- `ONLYOFFICE_DS_PUBLIC_URL`: Document server URL (default: `http://localhost:8082`)
- `ONLYOFFICE_JWT_SECRET`: JWT secret matching Docker container
- `ONLYOFFICE_JWT_ENABLED`: Always `true` for security
- `ONLYOFFICE_CALLBACK_HOST`: Use `host.docker.internal` for Docker
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`: Database credentials

## Critical Commands

```bash
# MySQL Operations (Windows XAMPP)
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < database/[file].sql
/mnt/c/xampp/mysql/bin/mysql.exe -u root -e "SHOW TABLES;" nexiosol
/mnt/c/xampp/mysql/bin/mysql.exe -u root -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='nexiosol';" # Count tables (100+)

# PHP Validation & Execution
/mnt/c/xampp/php/php.exe -l [file].php              # Check syntax
/mnt/c/xampp/php/php.exe [script].php               # Run script
/mnt/c/xampp/php/php.exe -r "phpinfo();"            # Check PHP config

# Setup & Maintenance Scripts
/mnt/c/xampp/php/php.exe scripts/setup-nexio-documentale.php    # Complete setup
/mnt/c/xampp/php/php.exe scripts/monitor-nexio-performance.php  # Monitor performance
/mnt/c/xampp/php/php.exe scripts/setup-company-folders.php      # Create company folders
/mnt/c/xampp/php/php.exe scripts/setup-onlyoffice-security.php  # Setup OnlyOffice JWT
/mnt/c/xampp/php/php.exe backend/websocket/server.php           # Start WebSocket server

# Cron Jobs
/mnt/c/xampp/php/php.exe cron/check-password-expiry.php        # Password expiry check
/mnt/c/xampp/php/php.exe cron/process-email-queue.php          # Process email queue

# Testing
/mnt/c/xampp/php/php.exe vendor/bin/phpunit tests/              # Run all tests
/mnt/c/xampp/php/php.exe vendor/bin/phpunit tests/OnlyOfficeIntegrationTest.php  # Run specific test
composer test                                       # Run PHPUnit tests via composer

# OnlyOffice Docker Management
docker ps | grep onlyoffice-ds                      # Check OnlyOffice status
docker logs onlyoffice-ds                           # View OnlyOffice logs
docker restart onlyoffice-ds                        # Restart OnlyOffice
./docker/setup-onlyoffice-https.sh                  # Setup OnlyOffice with HTTPS

# XAMPP Control
/mnt/c/xampp/xampp start
/mnt/c/xampp/xampp stop
/mnt/c/xampp/xampp status

# Composer (when available)
composer install --no-dev --optimize-autoloader
composer dump-autoload -o                          # Optimize autoloader
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

// Check database performance
$stmt = db_query("SHOW PROCESSLIST");
$stmt = db_query("SHOW STATUS LIKE 'Slow_queries'");
```

### Mobile App Integration
- API endpoints in `/backend/api/mobile-*.php`
- Authentication: `mobile-auth-api.php` (JWT tokens)
- Events: `mobile-events-api.php`
- Tasks: `mobile-tasks-api.php`
- Companies: `mobile-companies-api.php`
- Flutter app: `/flutter_nexio_app/`
- PWA: `/mobile/`

### OnlyOffice Document Editor Integration
OnlyOffice provides collaborative document editing capabilities via Docker container.

**Configuration Files**
- Main config: `backend/config/onlyoffice.config.php`
- Environment: `.env` (copy from `.env.example`)
- Docker setup: `docker/docker-compose.yml`

**Key URLs & Ports**
- Document Server: `https://localhost:8443` (local) / `https://app.nexiosolution.it/onlyoffice/` (production)
- Internal callback: `http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php`
- JWT enabled with HS256 algorithm

**API Endpoints**
- `backend/api/onlyoffice-document.php` - Main document endpoint
- `backend/api/onlyoffice-callback.php` - Save callback handler
- `backend/api/onlyoffice-auth.php` - JWT authentication
- `backend/api/onlyoffice-prepare.php` - Document preparation

**Document Flow**
1. User clicks edit → `onlyoffice-prepare.php` generates JWT token
2. Document served via `onlyoffice-document-serve.php`
3. OnlyOffice loads document with JWT auth
4. Auto-save triggers callback to `onlyoffice-callback.php`
5. Document saved to `documents/onlyoffice/` with versioning

### Known Issues & Solutions

**CSS Chaos (68 overlapping files)**
- Problem: Multiple fix layers causing specificity wars
- Solution: Consolidate into 3-5 organized files
- Files to merge: nexio-*.css files in /assets/css/

**Performance Bottlenecks**
- No caching layer (implement Redis)
- No asset bundling (add Webpack/Vite)
- 20+ CSS files loaded per page
- Solution: Implement build pipeline

**Security Concerns**
- MySQL root without password (CRITICAL)
- CORS allows all origins (*)
- Session security needs improvement
- Solution: Harden security configuration

**Technical Debt**
- Mixed jQuery and vanilla JavaScript
- No automated tests
- Hardcoded configuration values
- Solution: Gradual refactoring plan

## Specialized Agent Tools

The project includes specialized agents in `.claude/agents/` for specific tasks:
- **php-syntax-guardian**: Validates PHP 8.0+ syntax on all .php file changes
- **backend-api-guardian**: Reviews REST API endpoints for Auth/CSRF/JSON patterns
- **tenancy-sql-sheriff**: Ensures multi-tenant SQL queries filter by company
- **db-migration-operator**: Manages database migrations for `nexiosol` database
- **security-auditor**: OWASP-first security review for new features
- **test-runner-qa**: Runs PHPUnit tests and ensures quality gates
- **css-ui-fixer**: Manages the complex CSS layer system
- **perf-observer**: Analyzes performance bottlenecks with PHP/SQL profiling

## Database Migrations

**Migration Files Pattern**
- Location: `/database/`
- Naming: `YYYYMMDD_HHMM_description.sql` for ordered execution
- Rollback: `*_down.sql` files for reversing changes

**Running Migrations**
```bash
# Apply single migration
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < database/migration_file.sql

# Apply all pending migrations (ordered by filename)
for f in database/20*.sql; do
    echo "Applying $f..."
    /mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < "$f"
done

# Check migration status
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol -e "SELECT * FROM migrations ORDER BY id DESC LIMIT 10;"
```

## Important Reminders

### Development Best Practices
- **File Management**: Always prefer editing existing files over creating new ones
- **Documentation**: Only create docs when explicitly requested
- **Security First**: Never expose sensitive data or create security vulnerabilities
- **Multi-Tenant Aware**: Always consider company isolation in queries
- **Performance**: Use indexes and optimize queries for 100+ table database
- **Error Handling**: Always include proper error handling and logging

### Quick Reference

**Common Tasks**
1. **Add new API endpoint**: Create in `/backend/api/`, include Auth and CSRF
2. **Add database table**: Create SQL in `/database/`, run migration
3. **Fix UI issue**: Check existing CSS fixes first, avoid adding more layers
4. **Add new feature**: Update relevant model, API, and frontend
5. **Debug issue**: Check `/logs/`, use `error_log()`, verify database

**Troubleshooting**
```bash
# Check if XAMPP services are running
/mnt/c/xampp/xampp status

# Test database connection
/mnt/c/xampp/mysql/bin/mysql.exe -u root -e "SELECT 1;" nexiosol

# Verify PHP configuration
/mnt/c/xampp/php/php.exe -i | grep -E "mysqli|pdo"

# Check OnlyOffice container status
docker ps -a | grep onlyoffice

# View recent error logs
tail -f logs/error.log
tail -f logs/onlyoffice.log

# Clear session issues
rm -rf /tmp/sess_*
```

**Performance Tips**
- Use `db_query()` helper for prepared statements
- Implement pagination for large datasets
- Cache frequently accessed data
- Optimize images before upload
- Minimize JavaScript/CSS files

**Security Checklist**
- Validate all user input
- Use prepared statements for SQL
- Implement CSRF tokens
- Check user permissions
- Sanitize output (XSS prevention)
- Log security-relevant actions
- Use HTTPS in production