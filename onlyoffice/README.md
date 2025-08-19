# OnlyOffice Document Server - Setup & Management

## Overview
OnlyOffice Document Server integration for Nexio Platform, running in Docker container on port 8082.

## Quick Status Check
```bash
# Check if OnlyOffice is working
curl -I http://localhost:8082/web-apps/apps/api/documents/api.js

# Check container status
./docker-onlyoffice-manager.sh status
```

## Management Script
The main management tool is `docker-onlyoffice-manager.sh`:

```bash
# Start OnlyOffice
./docker-onlyoffice-manager.sh start

# Stop OnlyOffice
./docker-onlyoffice-manager.sh stop

# Restart OnlyOffice
./docker-onlyoffice-manager.sh restart

# Check status and health
./docker-onlyoffice-manager.sh status

# View logs
./docker-onlyoffice-manager.sh logs

# Auto-fix issues
./docker-onlyoffice-manager.sh auto-fix

# Complete removal
./docker-onlyoffice-manager.sh remove
```

## Container Configuration
- **Container Name**: nexio-documentserver
- **Port**: 8082 (mapped to container port 80)
- **Network**: nexio-network
- **JWT Secret**: mySecureJwtSecret123
- **Data Volume**: /mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice

## Access URLs
- **Document Server**: http://localhost:8082
- **API Endpoint**: http://localhost:8082/web-apps/apps/api/documents/api.js
- **Welcome Page**: http://localhost:8082/welcome/
- **Connection Test**: http://localhost/piattaforma-collaborativa/test-onlyoffice-connection.php

## Automatic Monitoring
The system includes automatic health monitoring:

1. **Setup Monitoring** (run once):
```bash
./setup-cron.sh
```

2. **Manual Health Check**:
```bash
./monitor-onlyoffice.sh
```

3. **View Monitor Logs**:
```bash
tail -f monitor.log
```

## Troubleshooting

### Container Not Starting
```bash
# Remove and recreate
./docker-onlyoffice-manager.sh remove
./docker-onlyoffice-manager.sh start
```

### Connection Refused Error
```bash
# Auto-fix attempt
./docker-onlyoffice-manager.sh auto-fix

# Manual fix
docker stop nexio-documentserver
docker rm nexio-documentserver
./docker-onlyoffice-manager.sh start
```

### Check Docker Logs
```bash
docker logs nexio-documentserver --tail 100
```

### Port Already in Use
```bash
# Find process using port 8082
lsof -i :8082
# or
netstat -tulpn | grep 8082

# Kill the process or change port in docker-onlyoffice-manager.sh
```

## Integration with Nexio Platform

### PHP Configuration
The platform is configured to use OnlyOffice at:
- **Backend Config**: `/backend/config/onlyoffice.config.php`
- **JWT Config**: `/backend/config/jwt-config.php`

### API Endpoints
- **Document Preparation**: `/backend/api/onlyoffice-prepare.php`
- **Callback Handler**: `/backend/api/onlyoffice-callback.php`
- **Document Retrieval**: `/backend/api/onlyoffice-document.php`

### Testing Integration
1. **Connection Test**: Visit http://localhost/piattaforma-collaborativa/test-onlyoffice-connection.php
2. **Editor Test**: Visit http://localhost/piattaforma-collaborativa/test-onlyoffice-complete.php
3. **Full Editor**: Visit http://localhost/piattaforma-collaborativa/onlyoffice-editor.php

## Security Notes
- JWT is enabled for secure communication
- Documents are stored in isolated volumes
- Network isolation via Docker network
- Regular security updates via Docker image updates

## Maintenance

### Update OnlyOffice
```bash
# Pull latest image
docker pull onlyoffice/documentserver:latest

# Restart with new image
./docker-onlyoffice-manager.sh remove
./docker-onlyoffice-manager.sh start
```

### Backup Documents
```bash
# Backup document data
tar -czf onlyoffice-backup-$(date +%Y%m%d).tar.gz /mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice/
```

### Monitor Resources
```bash
# Check container resource usage
docker stats nexio-documentserver

# Check disk usage
du -sh /mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice/
```

## Support
For issues, check:
1. Monitor logs: `tail -f monitor.log`
2. Docker logs: `docker logs nexio-documentserver`
3. Connection test: http://localhost/piattaforma-collaborativa/test-onlyoffice-connection.php
4. OnlyOffice logs: `/var/log/onlyoffice/` inside container