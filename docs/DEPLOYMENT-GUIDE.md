# Guida al Deployment - Sistema Documentale Nexio

## Indice

1. [Requisiti di Sistema](#requisiti-di-sistema)
2. [Installazione](#installazione)
3. [Configurazione](#configurazione)
4. [Deployment in Produzione](#deployment-in-produzione)
5. [Manutenzione](#manutenzione)
6. [Troubleshooting](#troubleshooting)
7. [Sicurezza](#sicurezza)
8. [Performance Tuning](#performance-tuning)

## Requisiti di Sistema

### Requisiti Minimi

- **Sistema Operativo**: Linux (Ubuntu 20.04+, CentOS 7+), Windows Server 2019+
- **Web Server**: Apache 2.4+ o Nginx 1.18+
- **PHP**: 7.4+ (consigliato 8.0+)
- **MySQL**: 5.7+ (consigliato 8.0+)
- **RAM**: 4GB minimo (8GB consigliato)
- **Spazio Disco**: 20GB minimo (dipende dall'utilizzo)
- **CPU**: 2 core minimo (4 core consigliato)

### Estensioni PHP Richieste

```bash
# Verifica con: php -m
- PDO
- pdo_mysql
- json
- mbstring
- openssl
- gd
- zip
- curl
- xml
- dom
- fileinfo
```

### Software Aggiuntivo

- **Composer**: Per gestione dipendenze PHP
- **Git**: Per deployment e versionamento
- **Redis** (opzionale): Per caching avanzato
- **Elasticsearch** (opzionale): Per ricerca full-text avanzata

## Installazione

### 1. Preparazione Server

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2 php php-mysql php-gd php-zip php-curl php-mbstring php-xml mysql-server git composer

# CentOS/RHEL
sudo yum update
sudo yum install httpd php php-mysql php-gd php-zip php-curl php-mbstring php-xml mariadb-server git composer
```

### 2. Clonazione Repository

```bash
cd /var/www
sudo git clone https://github.com/nexiosolution/piattaforma-collaborativa.git nexio
cd nexio
sudo chown -R www-data:www-data .
```

### 3. Installazione Dipendenze

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Configurazione Database

```bash
# Accedi a MySQL
mysql -u root -p

# Crea database e utente
CREATE DATABASE NexioSol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nexio'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON NexioSol.* TO 'nexio'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Setup Iniziale

```bash
# Rendi eseguibile lo script di setup
chmod +x scripts/setup-nexio-documentale.php

# Esegui setup
php scripts/setup-nexio-documentale.php
```

## Configurazione

### 1. File di Configurazione Principale

Copia e modifica il file di configurazione:

```bash
cp backend/config/config.example.php backend/config/config.php
```

Modifica `backend/config/config.php`:

```php
<?php
// Ambiente
define('ENVIRONMENT', 'production'); // development, staging, production
define('DEBUG', false);

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'NexioSol');
define('DB_USER', 'nexio');
define('DB_PASS', 'your_strong_password');

// Percorsi
define('ROOT_PATH', '/var/www/nexio');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('TEMP_PATH', ROOT_PATH . '/temp');

// URL
define('BASE_URL', 'https://yourdomain.com');
define('ASSETS_URL', BASE_URL . '/assets');

// Sicurezza
define('SESSION_LIFETIME', 3600); // 1 ora
define('CSRF_TOKEN_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minuti

// Email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Nexio Platform');

// OnlyOffice (se utilizzato)
define('ONLYOFFICE_URL', 'https://documentserver.yourdomain.com');
define('ONLYOFFICE_SECRET', 'your-secret-key');
```

### 2. Configurazione Web Server

#### Apache

Crea `/etc/apache2/sites-available/nexio.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/nexio
    
    <Directory /var/www/nexio>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Sicurezza headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    
    # Log
    ErrorLog ${APACHE_LOG_DIR}/nexio-error.log
    CustomLog ${APACHE_LOG_DIR}/nexio-access.log combined
</VirtualHost>
```

Abilita il sito:

```bash
sudo a2ensite nexio
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

#### Nginx

Crea `/etc/nginx/sites-available/nexio`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/nexio;
    index index.php;
    
    # Sicurezza
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Upload limits
    client_max_body_size 100M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    location ~ /\. {
        deny all;
    }
    
    # Blocca accesso a file sensibili
    location ~* \.(env|log|sql|conf|config)$ {
        deny all;
    }
}
```

### 3. Configurazione SSL

#### Con Let's Encrypt

```bash
# Apache
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com

# Nginx
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

### 4. Permessi File

```bash
# Imposta proprietario
sudo chown -R www-data:www-data /var/www/nexio

# Permessi directory
find /var/www/nexio -type d -exec chmod 755 {} \;

# Permessi file
find /var/www/nexio -type f -exec chmod 644 {} \;

# Directory scrivibili
chmod -R 775 /var/www/nexio/uploads
chmod -R 775 /var/www/nexio/logs
chmod -R 775 /var/www/nexio/temp
chmod -R 775 /var/www/nexio/cache
```

### 5. Cron Jobs

Aggiungi al crontab:

```bash
sudo crontab -e -u www-data
```

```cron
# Invio email ogni 5 minuti
*/5 * * * * php /var/www/nexio/backend/cron/send_emails.php

# Backup giornaliero alle 2:00 AM
0 2 * * * /var/www/nexio/scripts/backup.sh

# Pulizia file temporanei ogni domenica
0 3 * * 0 php /var/www/nexio/scripts/cleanup-temp.php

# Monitoraggio performance ogni ora
0 * * * * php /var/www/nexio/scripts/monitor-nexio-performance.php --html
```

## Deployment in Produzione

### 1. Pre-deployment Checklist

- [ ] Backup completo del sistema esistente
- [ ] Test in ambiente staging
- [ ] Verifica requisiti di sistema
- [ ] Preparazione credenziali database
- [ ] Configurazione DNS
- [ ] Certificati SSL pronti
- [ ] Piano di rollback

### 2. Deployment Steps

```bash
# 1. Metti il sito in manutenzione
echo "Sistema in manutenzione" > /var/www/nexio/maintenance.html

# 2. Backup database esistente (se presente)
mysqldump -u root -p NexioSol > backup_$(date +%Y%m%d_%H%M%S).sql

# 3. Pull ultimi aggiornamenti
cd /var/www/nexio
git pull origin main

# 4. Installa/aggiorna dipendenze
composer install --no-dev --optimize-autoloader

# 5. Esegui migrazioni database
php scripts/migrate-database.php

# 6. Pulisci cache
rm -rf cache/*
rm -rf temp/*

# 7. Imposta permessi
chown -R www-data:www-data .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# 8. Rimuovi manutenzione
rm maintenance.html

# 9. Riavvia servizi
systemctl restart apache2
systemctl restart php7.4-fpm
```

### 3. Verifica Post-deployment

```bash
# Esegui test di sistema
php scripts/test-nexio-documentale.php

# Verifica logs
tail -f logs/error.log

# Monitora performance
php scripts/monitor-nexio-performance.php
```

## Manutenzione

### Backup Automatici

Crea `/var/www/nexio/scripts/backup.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/backup/nexio"
DATE=$(date +%Y%m%d_%H%M%S)

# Crea directory se non esiste
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u nexio -p'password' NexioSol | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C /var/www/nexio uploads documents

# Mantieni solo ultimi 30 giorni
find $BACKUP_DIR -type f -mtime +30 -delete
```

### Monitoraggio

1. **Monitoraggio Real-time**:
```bash
php scripts/monitor-nexio-performance.php --continuous
```

2. **Report Giornalieri**:
```bash
# Aggiungi a cron
0 8 * * * php /var/www/nexio/scripts/monitor-nexio-performance.php --html
```

### Aggiornamenti

```bash
# 1. Backup
./scripts/backup.sh

# 2. Manutenzione mode
touch maintenance.html

# 3. Aggiorna codice
git pull origin main
composer install --no-dev

# 4. Aggiorna database
php scripts/migrate-database.php

# 5. Clear cache
rm -rf cache/*

# 6. Test
php scripts/test-nexio-documentale.php

# 7. Rimuovi manutenzione
rm maintenance.html
```

## Troubleshooting

### Problemi Comuni

#### 1. Errore 500

```bash
# Verifica logs
tail -f /var/log/apache2/error.log
tail -f logs/error.log

# Verifica permessi
ls -la uploads/
ls -la logs/

# Verifica PHP errors
php -l index.php
```

#### 2. Database Connection Failed

```bash
# Test connessione
mysql -u nexio -p -h localhost NexioSol

# Verifica servizio
systemctl status mysql

# Verifica configurazione
grep DB_ backend/config/config.php
```

#### 3. Upload Non Funzionanti

```bash
# Verifica limiti PHP
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Modifica php.ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

#### 4. Sessioni che Scadono

```bash
# Verifica configurazione sessioni
php -i | grep session

# Verifica permessi directory sessioni
ls -la /var/lib/php/sessions/
```

### Log Analysis

```bash
# Analizza errori frequenti
grep -i error logs/error.log | sort | uniq -c | sort -nr

# Trova query lente
grep "slow query" logs/performance-*.json

# Monitora accessi
tail -f /var/log/apache2/access.log | grep -v "GET /assets"
```

## Sicurezza

### 1. Hardening PHP

Modifica `php.ini`:

```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
```

### 2. Hardening MySQL

```sql
-- Rimuovi utenti anonimi
DELETE FROM mysql.user WHERE User='';

-- Disabilita accesso remoto root
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Rimuovi database test
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

FLUSH PRIVILEGES;
```

### 3. Firewall

```bash
# UFW (Ubuntu)
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Fail2ban
sudo apt install fail2ban
```

### 4. File Permissions

```bash
# Script di verifica permessi
#!/bin/bash
echo "Verifica permessi critici..."

# File config non devono essere world-readable
find /var/www/nexio -name "*.config.php" -perm -o+r -ls

# Directory uploads deve essere scrivibile solo da www-data
find /var/www/nexio/uploads -type d ! -perm 775 -ls

# Logs non devono essere accessibili dal web
find /var/www/nexio/logs -type f -perm -o+r -ls
```

## Performance Tuning

### 1. PHP Optimization

```ini
; php.ini ottimizzazioni
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### 2. MySQL Optimization

```ini
# my.cnf ottimizzazioni
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

max_connections = 200
thread_cache_size = 8
table_open_cache = 4000
```

### 3. Apache Optimization

```apache
# Abilita moduli
a2enmod deflate
a2enmod expires
a2enmod headers

# .htaccess ottimizzazioni
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 4. Monitoring Tools

```bash
# Installa tools di monitoring
apt install htop iotop mytop

# MySQL tuning
wget https://raw.githubusercontent.com/major/MySQLTuner-perl/master/mysqltuner.pl
perl mysqltuner.pl

# Apache status
a2enmod status
# Accedi a: http://yourdomain.com/server-status
```

## Script Utili

### Health Check

Crea `/var/www/nexio/scripts/health-check.sh`:

```bash
#!/bin/bash
echo "=== Nexio Health Check ==="
echo "Date: $(date)"

# Check servizi
echo -n "Apache: "
systemctl is-active apache2

echo -n "MySQL: "
systemctl is-active mysql

echo -n "PHP-FPM: "
systemctl is-active php7.4-fpm

# Check disk space
echo -e "\nDisk Usage:"
df -h | grep -E "^/dev/"

# Check database
echo -e "\nDatabase Connection:"
mysql -u nexio -p'password' -e "SELECT COUNT(*) as users FROM NexioSol.utenti;" 2>/dev/null && echo "OK" || echo "FAILED"

# Check logs for errors
echo -e "\nRecent Errors:"
tail -5 /var/www/nexio/logs/error.log 2>/dev/null || echo "No recent errors"
```

### Cleanup Script

Crea `/var/www/nexio/scripts/cleanup-temp.php`:

```php
<?php
// Pulizia file temporanei
$tempDirs = [
    __DIR__ . '/../temp',
    __DIR__ . '/../cache',
    __DIR__ . '/../uploads/temp'
];

$deletedFiles = 0;
$freedSpace = 0;

foreach ($tempDirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        // Elimina file più vecchi di 7 giorni
        if ($file->isFile() && $file->getMTime() < time() - (7 * 24 * 60 * 60)) {
            $size = $file->getSize();
            if (unlink($file->getRealPath())) {
                $deletedFiles++;
                $freedSpace += $size;
            }
        }
    }
}

echo "Cleanup completato: $deletedFiles file eliminati, " . 
     number_format($freedSpace / 1024 / 1024, 2) . " MB liberati\n";
```

## Conclusione

Questa guida copre gli aspetti principali del deployment di Nexio. Per assistenza specifica:

- **Documentazione**: [docs.nexiosolution.it](https://docs.nexiosolution.it)
- **Support**: support@nexiosolution.it
- **Community**: [community.nexiosolution.it](https://community.nexiosolution.it)

Ricorda di:
- Mantenere backup regolari
- Monitorare le performance
- Applicare aggiornamenti di sicurezza
- Rivedere i log periodicamente
- Testare il disaster recovery plan