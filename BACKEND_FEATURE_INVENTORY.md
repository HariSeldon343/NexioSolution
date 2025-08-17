# Nexio Platform - Backend Feature Inventory

## Overview
The Nexio Platform backend is a comprehensive multi-tenant document management system built with PHP 8.0+ and MySQL, featuring advanced ISO compliance, real-time synchronization, and mobile support.

## 1. API Endpoints (/backend/api/)

### Authentication & Session Management
- **login.php** - User authentication with multi-tenant support
- **logout.php** - Session termination
- **check-auth.php** - Authentication status verification
- **validate-session.php** - Session validation for API calls
- **get-csrf-token.php** - CSRF token generation
- **switch-azienda.php** - Company context switching

### Document Management
- **advanced-documents-api.php** - Advanced document operations with versioning
- **document-editor.php** - Document editing interface
- **document-spaces-api.php** - Document space management
- **document-template.php** - Document template handling
- **documento-autosave.php** - Auto-save functionality
- **save-document.php** - Document saving
- **save-advanced-document.php** - Advanced document save with metadata
- **get-document.php** - Document retrieval
- **delete-document.php** - Document deletion
- **save-frontespizio.php** - Cover page management
- **get-frontespizio.php** - Cover page retrieval

### File System Operations
- **filesystem-simple-api.php** - Basic file operations
- **folders-api.php** - Folder CRUD operations
- **simple-upload.php** - Single file upload
- **upload-image.php** - Image upload with processing
- **upload-progress.php** - Upload progress tracking
- **simple-download.php** - Single file download
- **download-file.php** - File download with permissions
- **download-folder-zip.php** - Folder download as ZIP
- **download-multiple.php** - Multiple file download
- **download-zip.php** - ZIP archive creation
- **download-progress.php** - Download progress tracking
- **download-export.php** - Export functionality

### Calendar & Events
- **calendar-api.php** - Calendar operations
- **calendar-events.php** - Event management
- **eventi-semplice.php** - Simplified event handling
- **task-calendario-api.php** - Task-calendar integration
- **get-task-days.php** - Task day retrieval
- **import-ics.php** - ICS file import
- **update-event-company.php** - Event company updates

### Ticket System
- **delete-ticket.php** - Ticket deletion
- **mobile-tickets-api.php** - Mobile ticket API

### ISO Compliance System
- **iso-compliance-api.php** - Main ISO compliance operations
- **iso-documents-api.php** - ISO document management
- **iso-folders-api.php** - ISO folder structure
- **iso-structure-api.php** - ISO structure configuration
- **iso-setup-api.php** - ISO system setup
- **iso-export.php** - ISO data export
- **check-iso-status.php** - ISO compliance status

### Template Management
- **template-api.php** - Template CRUD operations
- **template-dragdrop-api.php** - Drag-and-drop template builder
- **template-elements-api.php** - Template element management
- **get-template.php** - Single template retrieval
- **get-templates.php** - Template listing
- **get-template-azienda.php** - Company-specific templates

### Permission Management
- **permission-management-api.php** - Permission system management
- **check-document-permission.php** - Document permission verification
- **check-folder-permission.php** - Folder permission verification
- **get-user-permissions.php** - User permission retrieval
- **get-resource-permissions.php** - Resource permission listing

### Mobile APIs
- **mobile-api.php** - Main mobile API endpoint
- **mobile-auth-api.php** - Mobile authentication
- **mobile-companies-api.php** - Company data for mobile
- **mobile-events-api.php** - Events for mobile
- **mobile-tasks-api.php** - Tasks for mobile
- **mobile-tickets-api.php** - Tickets for mobile

### OnlyOffice Integration
- **onlyoffice-auth.php** - OnlyOffice authentication
- **onlyoffice-callback.php** - OnlyOffice save callbacks
- **onlyoffice-document.php** - Document operations
- **onlyoffice-prepare.php** - Document preparation
- **onlyoffice-proxy.php** - OnlyOffice proxy service

### Search & Data Retrieval
- **search-advanced.php** - Advanced search functionality
- **get-referenti.php** - Contact retrieval

### Notifications & Sync
- **push-notifications.php** - Push notification handling
- **sync-api.php** - Data synchronization

### Versioned APIs (/backend/api/v1/)
- **backup/create.php** - Backup creation
- **documents/search.php** - Document search
- **gdpr/compliance.php** - GDPR compliance
- **structures/create.php** - Structure creation
- **structures/templates.php** - Structure templates

### Utility APIs
- **check_column_exists.php** - Database column verification

## 2. Middleware (/backend/middleware/)

### Auth.php
- **Singleton Pattern Authentication**
  - Session management
  - User authentication state
  - Company context management
  - Permission integration
  - Password expiry checks
  - Role-based access control (super_admin, utente_speciale, utente)
  - Multi-tenant support

### JWTAuth.php
- **JWT Token Authentication**
  - Token generation and validation
  - Mobile app authentication
  - API authentication
  - Token refresh mechanism

### PermissionMiddleware.php
- **Granular Permission System**
  - Resource-based permissions
  - Role-permission mapping
  - Dynamic permission checking
  - Cache management for performance

## 3. Models (/backend/models/)

### User.php
- User CRUD operations
- Authentication verification
- Password management
- Profile updates
- Company associations
- Role management

### Template.php
- Template creation and management
- Template versioning
- Template sharing
- Company-specific templates

### DocumentVersion.php
- Version control for documents
- Version comparison
- Rollback functionality
- Version metadata

### AdvancedDocument.php
- Advanced document operations
- Metadata management
- Full-text search
- Document relationships

### AdvancedFolder.php
- Hierarchical folder structure
- Permission inheritance
- Folder templates
- Batch operations

### DocumentSpace.php
- Document space management
- Space quotas
- Access control
- Space templates

## 4. Services (/backend/services/)

### ISOComplianceService.php
- **Core ISO Compliance Logic**
  - Company configuration initialization
  - Folder structure creation from templates
  - Document CRUD with versioning
  - Bulk operations
  - Compliance validation
  - Performance metrics tracking

### ISOSecurityService.php
- **Security for ISO Documents**
  - Access control enforcement
  - Audit trail generation
  - Security scanning
  - Encryption management
  - Compliance monitoring

### ISOStorageService.php
- **Storage Management for ISO**
  - File storage operations
  - Storage optimization
  - Backup management
  - Archive operations
  - Storage quotas

### MultiStandardDocumentService.php
- **Multi-Standard Support**
  - ISO 9001, 14001, 45001, 27001
  - Standard-specific templates
  - Cross-standard compliance
  - Unified reporting

## 5. Utils (/backend/utils/)

### Activity & Logging
- **ActivityLogger.php** - Comprehensive activity logging
- **ActivityLoggerNamespace.php** - Namespaced logging
- **DatabaseErrorHandler.php** - Database error management

### Authentication & Security
- **CSRFTokenManager.php** - CSRF protection
- **TwoFactorAuth.php** - 2FA implementation (TOTP)
- **DataEncryption.php** - Data encryption utilities
- **SecurityScanner.php** - Security vulnerability scanning
- **RateLimiter.php** - API rate limiting
- **PermissionManager.php** - Permission system core

### Email System (Multiple Implementations)
- **UniversalMailer.php** - Universal email with fallbacks
- **Mailer.php** - Base mailer class
- **BrevoMailer.php** - Brevo API integration
- **BrevoAPI.php** - Brevo API client
- **BrevoSMTP.php** - Brevo SMTP
- **SimpleSMTP.php** - Basic SMTP
- **DirectSMTP.php** - Direct SMTP connection
- **SocketSMTP.php** - Socket-based SMTP
- **CurlSMTP.php** - CURL-based SMTP
- **CurlMailer.php** - CURL email sending
- **HttpMailer.php** - HTTP-based email
- **LocalhostMailer.php** - Local development mailer
- **EmailTemplate.php** - Email templating
- **EmailTemplateOutlook.php** - Outlook-specific templates

### Notification System
- **NotificationManager.php** - Notification management
- **NotificationCenter.php** - Centralized notifications
- **EventInvite.php** - Calendar event invitations

### File & Document Management
- **AdvancedFilesystemAdapter.php** - Advanced file operations
- **MultiFileManager.php** - Multiple file handling
- **ZipArchiveFallback.php** - ZIP creation fallback
- **DocumentSpaceManager.php** - Document space operations
- **TemplateProcessor.php** - Template processing
- **DompdfGenerator.php** - PDF generation

### Calendar & Events
- **CalendarHelper.php** - Calendar utilities
- **CalendarColorHelper.php** - Calendar color management
- **ICSGenerator.php** - ICS file generation

### ISO Management
- **ISOStructureManager.php** - ISO structure management
- **ISOSecurityManager.php** - ISO security operations
- **ISOValidator.php** - ISO compliance validation

### JWT & Session
- **JWTManager.php** - JWT token management
- **SimpleJWT.php** - Simplified JWT implementation

### Search & Indexing
- **AdvancedSearchEngine.php** - Full-text search engine

### Helper Utilities
- **MenuHelper.php** - Menu generation
- **ModulesHelper.php** - Module management
- **UserRoleHelper.php** - User role utilities

## 6. Configuration (/backend/config/)

### Core Configuration
- **config.php** - Main configuration
- **database.php** - Database connection
- **database-compat.php** - Database compatibility layer
- **pdo-constants.php** - PDO constants

### Security Configuration
- **csp-config.php** - Content Security Policy
- **csp-fix.php** - CSP fixes
- **jwt-config.php** - JWT configuration

### Feature Configuration
- **onlyoffice.config.php** - OnlyOffice settings
- **email.config.example.php** - Email configuration template
- **permission-helpers.php** - Permission helpers
- **compliance-folders.php** - Compliance folder structure
- **compliance-folders-extended.php** - Extended compliance folders

### Environment Configuration
- **production-config.php** - Production settings
- **config.production.example.php** - Production template

## 7. Cron Jobs (/backend/cron/)

### send_emails.php
- Email queue processing
- Retry logic for failed emails
- Batch processing
- Error logging

### send_ticket_emails.php
- Ticket notification emails
- SLA notifications
- Escalation emails
- Status updates

## 8. Functions (/backend/functions/)

### aziende-functions.php
- Company management functions
- Company switching logic
- Company-user associations
- Company status management

## 9. WebSocket Server (/backend/websocket/)

### server.php
- **Real-time Features:**
  - User authentication via WebSocket
  - Document real-time updates
  - Ticket status synchronization
  - Event updates broadcasting
  - Presence management
  - Notification push
  - Ping/pong for connection health

## 10. Key Features Summary

### Multi-Tenant Architecture
- Company-based data isolation
- Dynamic company switching
- Company-specific configurations
- Global resource management (azienda_id = NULL)

### Advanced Document Management
- Version control with rollback
- Document templates
- Auto-save functionality
- OnlyOffice integration for real-time editing
- Multiple file format support
- Bulk operations

### ISO Compliance System
- Multi-standard support (9001, 14001, 45001, 27001)
- Automated folder structure creation
- Compliance validation
- Audit trail generation
- Document lifecycle management

### Security Features
- Two-factor authentication (TOTP)
- CSRF protection on all endpoints
- JWT authentication for APIs
- Granular permission system
- Rate limiting
- Security scanning
- Data encryption

### Email System
- Multiple mailer implementations with automatic fallback
- Template-based emails
- Queue management
- Retry logic
- Multiple SMTP methods
- Brevo integration

### Real-time Capabilities
- WebSocket server for live updates
- Document collaboration
- Instant notifications
- Presence indicators
- Auto-sync

### Mobile Support
- Dedicated mobile APIs
- JWT authentication
- Optimized data formats
- Push notifications
- Offline capability preparation

### Notification System
- Email notifications
- In-app notifications
- Push notifications
- Event-based triggers
- Customizable templates

### Calendar & Task Management
- Event management
- Task integration
- ICS import/export
- Recurring events
- Reminders

### Search & Indexing
- Full-text search
- Advanced filters
- Cross-entity search
- Performance optimization

### Monitoring & Logging
- Comprehensive activity logging
- Error tracking
- Performance metrics
- Audit trails
- Database error handling

## Architecture Patterns

1. **Singleton Pattern** - Used for Auth, PermissionManager, Services
2. **Factory Pattern** - Document and Template creation
3. **Repository Pattern** - Data access layer
4. **Service Layer** - Business logic encapsulation
5. **Middleware Pattern** - Request processing pipeline
6. **Observer Pattern** - Event-driven notifications
7. **Strategy Pattern** - Multiple email sending strategies

## Database Tables (Key)
- `utenti` - Users
- `aziende` - Companies
- `documenti` - Documents
- `cartelle` - Folders
- `eventi` - Events
- `tickets` - Support tickets
- `tasks` - Tasks
- `referenti_aziende` - Company contacts
- `log_attivita` - Activity logs
- `notifiche_email` - Email queue
- `user_permissions` - Granular permissions
- `iso_*` - ISO compliance tables

## Performance Optimizations
- Query result caching
- Lazy loading
- Connection pooling
- Batch processing
- Async operations
- Rate limiting
- Index optimization

## Security Measures
- Password hashing (bcrypt/argon2)
- SQL injection prevention (prepared statements)
- XSS protection
- CSRF tokens
- Rate limiting
- Session security
- Input validation
- Output encoding

## Deployment Considerations
- XAMPP environment (Windows WSL2)
- PHP 8.0+ requirement
- MySQL database
- WebSocket server (optional)
- Cron job setup
- File permissions
- SSL/TLS configuration