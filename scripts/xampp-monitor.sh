#!/bin/bash

# Script di monitoraggio XAMPP
# Controlla ogni 10 minuti se XAMPP è attivo e lo riavvia se necessario

# Configurazioni
XAMPP_PATH="/opt/lampp"
LOG_FILE="/opt/lampp/htdocs/piattaforma-collaborativa/logs/xampp-monitor.log"
PID_FILE="/tmp/xampp-monitor.pid"
EMAIL_NOTIFY="asamodeo@fortibyte.it"

# Funzione per il logging
log_message() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] $message" >> "$LOG_FILE"
    echo "[$timestamp] $message"
}

# Funzione per inviare notifiche email
send_notification() {
    local subject="$1"
    local body="$2"
    
    if [ -f "/opt/lampp/htdocs/piattaforma-collaborativa/backend/utils/Mailer.php" ]; then
        php -r "
        require_once '/opt/lampp/htdocs/piattaforma-collaborativa/backend/config/config.php';
        require_once '/opt/lampp/htdocs/piattaforma-collaborativa/backend/utils/Mailer.php';
        try {
            \$mailer = Mailer::getInstance();
            \$mailer->send('$EMAIL_NOTIFY', '$subject', '$body');
        } catch (Exception \$e) {
            error_log('Errore invio notifica XAMPP: ' . \$e->getMessage());
        }
        " 2>/dev/null
    fi
}

# Funzione per controllare se un processo è attivo
check_process() {
    local process_name="$1"
    pgrep -f "$process_name" > /dev/null 2>&1
    return $?
}

# Funzione per controllare se una porta è in ascolto
check_port() {
    local port="$1"
    netstat -tuln 2>/dev/null | grep ":$port " > /dev/null 2>&1
    return $?
}

# Funzione per controllare Apache
check_apache() {
    if check_process "httpd" || check_process "apache2" || check_port "80"; then
        return 0
    else
        return 1
    fi
}

# Funzione per controllare MySQL
check_mysql() {
    if check_process "mysqld" || check_port "3306"; then
        return 0
    else
        return 1
    fi
}

# Funzione per avviare XAMPP
start_xampp() {
    log_message "Tentativo di avvio XAMPP..."
    
    if [ ! -f "$XAMPP_PATH/lampp" ]; then
        log_message "ERRORE: File $XAMPP_PATH/lampp non trovato!"
        return 1
    fi
    
    sudo "$XAMPP_PATH/lampp" start > /tmp/xampp_start.log 2>&1
    local exit_code=$?
    
    if [ $exit_code -eq 0 ]; then
        log_message "XAMPP avviato con successo"
        sleep 10
        
        if check_apache && check_mysql; then
            log_message "Tutti i servizi XAMPP sono ora attivi"
            return 0
        else
            log_message "ATTENZIONE: XAMPP avviato ma alcuni servizi potrebbero non essere attivi"
            return 1
        fi
    else
        log_message "ERRORE: Impossibile avviare XAMPP (exit code: $exit_code)"
        cat /tmp/xampp_start.log >> "$LOG_FILE"
        return 1
    fi
}

# Funzione per riavviare un servizio specifico
restart_service() {
    local service="$1"
    log_message "Riavvio servizio $service..."
    
    case "$service" in
        "apache")
            sudo "$XAMPP_PATH/lampp" restart apache > /tmp/xampp_restart_apache.log 2>&1
            ;;
        "mysql")
            sudo "$XAMPP_PATH/lampp" restart mysql > /tmp/xampp_restart_mysql.log 2>&1
            ;;
        *)
            log_message "Servizio sconosciuto: $service"
            return 1
            ;;
    esac
    
    local exit_code=$?
    if [ $exit_code -eq 0 ]; then
        log_message "Servizio $service riavviato con successo"
        return 0
    else
        log_message "ERRORE: Impossibile riavviare servizio $service (exit code: $exit_code)"
        return 1
    fi
}

# Funzione principale
monitor_xampp() {
    log_message "=== Inizio controllo XAMPP ==="
    
    local apache_status=0
    local mysql_status=0
    local actions_taken=0
    
    # Controlla Apache
    if check_apache; then
        log_message "✓ Apache è attivo"
    else
        log_message "✗ Apache non è attivo"
        apache_status=1
    fi
    
    # Controlla MySQL
    if check_mysql; then
        log_message "✓ MySQL è attivo"
    else
        log_message "✗ MySQL non è attivo"
        mysql_status=1
    fi
    
    # Se entrambi i servizi sono spenti, riavvia tutto XAMPP
    if [ $apache_status -eq 1 ] && [ $mysql_status -eq 1 ]; then
        log_message "Entrambi i servizi sono spenti. Riavvio completo di XAMPP..."
        if start_xampp; then
            actions_taken=1
            send_notification "XAMPP Riavviato" "XAMPP è stato riavviato automaticamente."
        else
            log_message "ERRORE CRITICO: Impossibile riavviare XAMPP!"
            send_notification "ERRORE CRITICO XAMPP" "Impossibile riavviare XAMPP. Intervento richiesto!"
        fi
    else
        # Riavvia solo i servizi specifici
        if [ $apache_status -eq 1 ]; then
            if restart_service "apache"; then
                actions_taken=1
            fi
        fi
        
        if [ $mysql_status -eq 1 ]; then
            if restart_service "mysql"; then
                actions_taken=1
            fi
        fi
    fi
    
    # Verifica finale se sono state prese azioni
    if [ $actions_taken -eq 1 ]; then
        sleep 5
        log_message "--- Verifica finale dopo riavvio ---"
        
        if check_apache; then
            log_message "✓ Apache ora è attivo"
        else
            log_message "✗ Apache ancora non attivo"
        fi
        
        if check_mysql; then
            log_message "✓ MySQL ora è attivo"
        else
            log_message "✗ MySQL ancora non attivo"
        fi
    fi
    
    log_message "=== Fine controllo XAMPP ==="
}

# Controlla se già in esecuzione
if [ -f "$PID_FILE" ]; then
    old_pid=$(cat "$PID_FILE")
    if ps -p $old_pid > /dev/null 2>&1; then
        log_message "Script già in esecuzione (PID: $old_pid). Uscita."
        exit 1
    else
        rm -f "$PID_FILE"
    fi
fi

# Scrivi PID
echo $$ > "$PID_FILE"

# Crea directory log
mkdir -p "$(dirname "$LOG_FILE")"

# Esegui monitoraggio
monitor_xampp

# Pulizia
rm -f "$PID_FILE"

exit 0
