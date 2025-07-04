<?php
/**
 * Configurazione Email - Esempio
 * 
 * 1. Copia questo file e rinominalo in email.config.php
 * 2. Modifica i valori con le tue credenziali SMTP
 * 3. Include questo file nel config.php principale
 */

// Gmail SMTP (con password app)
define('EMAIL_HOST', 'smtp.gmail.com');
define('EMAIL_PORT', 587);
define('EMAIL_USERNAME', 'tua-email@gmail.com');
define('EMAIL_PASSWORD', 'xxxx-xxxx-xxxx-xxxx'); // Password app Google
define('EMAIL_FROM', 'tua-email@gmail.com');
define('EMAIL_FROM_NAME', 'Nexio Support');

// Infomaniak SMTP
// define('EMAIL_HOST', 'mail.infomaniak.com');
// define('EMAIL_PORT', 587);
// define('EMAIL_USERNAME', 'email@tuodominio.com');
// define('EMAIL_PASSWORD', 'password');
// define('EMAIL_FROM', 'noreply@tuodominio.com');
// define('EMAIL_FROM_NAME', 'Nexio Support');

// Aruba SMTP
// define('EMAIL_HOST', 'smtps.aruba.it');
// define('EMAIL_PORT', 465);
// define('EMAIL_USERNAME', 'email@tuodominio.it');
// define('EMAIL_PASSWORD', 'password');
// define('EMAIL_FROM', 'noreply@tuodominio.it');
// define('EMAIL_FROM_NAME', 'Nexio Support');

/**
 * ISTRUZIONI PER GMAIL:
 * 1. Attiva l'autenticazione a 2 fattori
 * 2. Vai su https://myaccount.google.com/apppasswords
 * 3. Genera una password app per "Mail"
 * 4. Usa quella password qui sopra
 * 
 * ISTRUZIONI PER CRON JOB:
 * Aggiungi questa riga al crontab per eseguire ogni 5 minuti:
 * ogni-5-minuti * * * * /usr/bin/php /path/to/backend/cron/send_ticket_emails.php >> /path/to/logs/cron_email.log 2>&1
 * 
 * Per Windows Task Scheduler:
 * 1. Crea un nuovo task
 * 2. Trigger: ogni 5 minuti
 * 3. Action: "C:\xampp\php\php.exe" "C:\xampp\htdocs\piattaforma-collaborativa\backend\cron\send_ticket_emails.php"
 */ 