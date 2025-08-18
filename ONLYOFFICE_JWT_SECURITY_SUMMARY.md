# OnlyOffice JWT Security Configuration Summary

## 🔐 Overview
Complete JWT authentication and security hardening has been implemented for OnlyOffice Document Server integration in the Nexio platform.

## ✅ What Has Been Configured

### 1. **JWT Authentication System**
- ✅ Full JWT token generation and verification functions
- ✅ HS256/HS512 algorithm support
- ✅ Automatic token expiration handling
- ✅ Secure secret key management via environment variables

### 2. **Security Features Implemented**
- ✅ **Rate Limiting**: Configurable requests per minute to prevent abuse
- ✅ **IP Whitelisting**: Optional restriction of callback sources
- ✅ **CORS Configuration**: Proper origin restrictions
- ✅ **HTTPS Enforcement**: Automatic in production environment
- ✅ **Security Headers**: HSTS, XSS Protection, Frame Options
- ✅ **Multi-tenant Isolation**: Company-level document access control
- ✅ **Version Control**: Automatic document versioning on save
- ✅ **Audit Logging**: Comprehensive activity tracking

### 3. **Files Created/Modified**

#### Core Configuration
- `/backend/config/onlyoffice.config.php` - Main configuration with JWT functions
- `.env.onlyoffice.example` - Environment variables template
- `/database/create_onlyoffice_tables.sql` - Database schema for versioning

#### API Endpoints
- `/backend/api/onlyoffice-auth.php` - JWT token generation for editor
- `/backend/api/onlyoffice-callback.php` - Secure callback handler with JWT verification
- `/backend/api/onlyoffice-document.php` - Document management
- `/backend/api/onlyoffice-prepare.php` - Document preparation
- `/backend/api/onlyoffice-proxy.php` - CORS proxy for assets

#### Testing & Validation
- `/test-onlyoffice-jwt.php` - Configuration validation tool
- `/scripts/setup-onlyoffice-security.php` - Setup and security check script

## 🚨 CRITICAL CONFIGURATION REQUIRED

### For Production Deployment

1. **Generate Secure JWT Secret** (MANDATORY)
```bash
# Generate a secure 32-character secret
openssl rand -hex 32
```

2. **Update Configuration File**
```php
// backend/config/onlyoffice.config.php
define('ONLYOFFICE_JWT_ENABLED', true);  // NEVER set to false in production
define('ONLYOFFICE_JWT_SECRET', 'your-generated-secret-here');
```

3. **Configure Server URLs**
```php
// Production URLs (use HTTPS)
define('ONLYOFFICE_DS_PUBLIC_URL', 'https://office.yourdomain.com');
define('ONLYOFFICE_DS_INTERNAL_URL', 'http://onlyoffice-ds:80'); // Docker internal
define('ONLYOFFICE_CALLBACK_URL', 'https://app.nexiosolution.it/piattaforma-collaborativa/backend/api/onlyoffice-callback.php');
```

## 🔧 Environment Variables (.env file)

Create `.env` file in project root:

```env
# OnlyOffice JWT Security
ONLYOFFICE_JWT_ENABLED=true
ONLYOFFICE_JWT_SECRET=your-32-char-minimum-secret-key-here
ONLYOFFICE_JWT_ALGORITHM=HS256

# OnlyOffice Server URLs
ONLYOFFICE_DS_PUBLIC_URL=https://office.yourdomain.com
ONLYOFFICE_DS_INTERNAL_URL=http://onlyoffice-ds:80
ONLYOFFICE_CALLBACK_URL=https://yourdomain.com/backend/api/onlyoffice-callback.php

# Security Settings
ONLYOFFICE_DEBUG=false
ONLYOFFICE_FORCE_HTTPS=true
ONLYOFFICE_RATE_LIMIT=100
ONLYOFFICE_RATE_LIMIT_WINDOW=60

# CORS Configuration
ONLYOFFICE_CORS_ORIGINS=https://yourdomain.com,https://office.yourdomain.com

# Optional IP Whitelisting
ONLYOFFICE_ALLOWED_IPS=

# Session Configuration
ONLYOFFICE_SESSION_TIMEOUT=3600
ONLYOFFICE_MAX_FILE_SIZE=52428800
```

## 📊 Database Setup

Run the migration to create required tables:

```bash
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < database/create_onlyoffice_tables.sql
```

This creates:
- `documenti_versioni_extended` - Full document version history
- `onlyoffice_sessions` - Active editing sessions
- `onlyoffice_collaborative_actions` - Collaboration audit log
- `onlyoffice_security_log` - Security events tracking

## 🧪 Testing Your Configuration

1. **Run Configuration Test**
```bash
# Open in browser
http://localhost/piattaforma-collaborativa/test-onlyoffice-jwt.php
```

2. **Verify JWT is Working**
- All tests should pass (green checkmarks)
- No critical issues should be present
- JWT token generation and verification should work

3. **Test Document Editing**
- Create/edit a document
- Verify JWT token is included in OnlyOffice configuration
- Check callback endpoint receives and validates JWT

## 🛡️ Security Best Practices

### DO's ✅
- ✅ Always use HTTPS in production
- ✅ Generate a unique, strong JWT secret (minimum 32 characters)
- ✅ Keep JWT secret in environment variables, never in code
- ✅ Enable rate limiting to prevent abuse
- ✅ Configure proper CORS origins
- ✅ Monitor security logs regularly
- ✅ Keep OnlyOffice Document Server updated
- ✅ Use internal URLs for server-to-server communication
- ✅ Implement proper multi-tenant isolation

### DON'Ts ❌
- ❌ Never disable JWT in production (`ONLYOFFICE_JWT_ENABLED = false`)
- ❌ Never use default JWT secrets
- ❌ Never expose JWT secret in logs or error messages
- ❌ Never allow CORS from all origins (`*`) in production
- ❌ Never skip JWT verification in callbacks
- ❌ Never store JWT secrets in version control

## 🚀 Production Deployment Checklist

- [ ] JWT secret generated and configured (32+ chars)
- [ ] JWT authentication enabled (`ONLYOFFICE_JWT_ENABLED = true`)
- [ ] HTTPS configured for all URLs
- [ ] Environment variables properly set
- [ ] Database tables created
- [ ] CORS origins restricted to your domains
- [ ] Debug mode disabled
- [ ] Rate limiting configured
- [ ] Security headers enabled
- [ ] Monitoring and logging set up
- [ ] Backup strategy in place
- [ ] SSL certificates valid
- [ ] Firewall rules configured
- [ ] Regular security audits scheduled

## 📈 Monitoring

### What to Monitor
1. **Failed JWT validations** - Could indicate attack attempts
2. **Rate limit violations** - Possible DDoS or abuse
3. **Callback errors** - Integration issues
4. **Version creation failures** - Storage or permission issues
5. **Unusual access patterns** - Security breaches

### Log Locations
- Security events: `logs/onlyoffice-security.log`
- Callback logs: `logs/onlyoffice-callbacks.log`
- Error logs: `logs/error.log`

## 🔍 Troubleshooting

### JWT Token Invalid
1. Check JWT secret matches between config and OnlyOffice
2. Verify token hasn't expired
3. Ensure algorithm matches (HS256)
4. Check system time synchronization

### Callback Not Working
1. Verify callback URL is accessible from OnlyOffice server
2. Check JWT is enabled and configured
3. Review firewall rules
4. Check PHP error logs

### Document Won't Save
1. Verify file permissions
2. Check disk space
3. Review callback logs
4. Ensure version table exists

## 📞 Support

For issues or questions:
1. Check test page: `/test-onlyoffice-jwt.php`
2. Review logs in `/logs/` directory
3. Run setup script: `/scripts/setup-onlyoffice-security.php`
4. Check OnlyOffice server status

## 🎯 Summary

The OnlyOffice integration now has enterprise-grade JWT security with:
- **Authentication**: Strong JWT token validation
- **Authorization**: Multi-tenant document isolation
- **Audit**: Complete activity logging
- **Versioning**: Automatic document version control
- **Security**: Multiple layers of protection

**Status**: System is configured but requires production JWT secret before deployment.

**Next Step**: Generate and configure a production JWT secret, then test with `/test-onlyoffice-jwt.php`