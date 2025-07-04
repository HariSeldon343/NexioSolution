#!/bin/bash

# Script per verificare lo stato del monitoraggio XAMPP

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/xampp-monitor.sh"
LOG_FILE="/opt/lampp/htdocs/piattaforma-collaborativa/logs/xampp-monitor.log"

echo "📊 Stato Sistema di Monitoraggio XAMPP"
echo "======================================"

# Verifica cron job
echo "🔍 Verifica Cron Job:"
if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
    echo "   ✅ Cron job configurato"
    cron_line=$(crontab -l 2>/dev/null | grep "$MONITOR_SCRIPT")
    echo "   📋 Comando: $cron_line"
else
    echo "   ❌ Cron job NON configurato"
fi

echo ""

# Verifica script
echo "📄 Verifica Script:"
if [ -f "$MONITOR_SCRIPT" ]; then
    echo "   ✅ Script presente: $MONITOR_SCRIPT"
    if [ -x "$MONITOR_SCRIPT" ]; then
        echo "   ✅ Script eseguibile"
    else
        echo "   ⚠️  Script NON eseguibile"
    fi
else
    echo "   ❌ Script NON presente"
fi

echo ""

# Verifica log
echo "📋 Stato Log:"
if [ -f "$LOG_FILE" ]; then
    echo "   ✅ File di log presente: $LOG_FILE"
    log_size=$(du -h "$LOG_FILE" | cut -f1)
    echo "   📊 Dimensione: $log_size"
    
    # Ultimo controllo
    if [ -s "$LOG_FILE" ]; then
        last_check=$(tail -1 "$LOG_FILE" 2>/dev/null | grep -o '\[.*\]' | tr -d '[]')
        if [ ! -z "$last_check" ]; then
            echo "   🕐 Ultimo controllo: $last_check"
        fi
    fi
else
    echo "   ❌ File di log NON presente"
fi

echo ""

# Stato attuale XAMPP
echo "🖥️  Stato Attuale XAMPP:"

# Controlla Apache
if pgrep -f "httpd\|apache2" > /dev/null || netstat -tuln 2>/dev/null | grep ":80 " > /dev/null; then
    echo "   ✅ Apache: ATTIVO"
else
    echo "   ❌ Apache: NON ATTIVO"
fi

# Controlla MySQL
if pgrep -f "mysqld" > /dev/null || netstat -tuln 2>/dev/null | grep ":3306 " > /dev/null; then
    echo "   ✅ MySQL: ATTIVO"
else
    echo "   ❌ MySQL: NON ATTIVO"
fi

echo ""

# Statistiche recenti (ultime 24 ore)
echo "📈 Statistiche Recenti (ultime 24 ore):"
if [ -f "$LOG_FILE" ] && [ -s "$LOG_FILE" ]; then
    yesterday=$(date -d "1 day ago" '+%Y-%m-%d')
    today=$(date '+%Y-%m-%d')
    
    total_checks=$(grep -E "\[$yesterday|\[$today" "$LOG_FILE" 2>/dev/null | grep "Inizio controllo" | wc -l)
    apache_restarts=$(grep -E "\[$yesterday|\[$today" "$LOG_FILE" 2>/dev/null | grep "Riavvio servizio apache" | wc -l)
    mysql_restarts=$(grep -E "\[$yesterday|\[$today" "$LOG_FILE" 2>/dev/null | grep "Riavvio servizio mysql" | wc -l)
    full_restarts=$(grep -E "\[$yesterday|\[$today" "$LOG_FILE" 2>/dev/null | grep "XAMPP avviato" | wc -l)
    
    echo "   🔄 Controlli totali: $total_checks"
    echo "   🔧 Riavvii Apache: $apache_restarts"
    echo "   🔧 Riavvii MySQL: $mysql_restarts"
    echo "   🔧 Riavvii completi XAMPP: $full_restarts"
else
    echo "   ℹ️  Nessun dato disponibile"
fi

echo ""

# Azioni disponibili
echo "🛠️  Azioni Disponibili:"
echo "   • Installare monitoraggio: ./scripts/install-xampp-monitor.sh"
echo "   • Disinstallare monitoraggio: ./scripts/uninstall-xampp-monitor.sh"
echo "   • Test manuale: ./scripts/xampp-monitor.sh"
echo "   • Visualizzare log: tail -f $LOG_FILE"

exit 0 