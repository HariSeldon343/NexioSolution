#!/bin/bash

# Script di disinstallazione per il monitoraggio XAMPP
# Rimuove il cron job di monitoraggio

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/xampp-monitor.sh"

echo "🗑️  Disinstallazione Sistema di Monitoraggio XAMPP"
echo "================================================="

# Verifica se il job è presente
if ! crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
    echo "ℹ️  Il monitoraggio XAMPP non è configurato nel crontab"
    exit 0
fi

# Backup del crontab attuale
echo "💾 Backup del crontab attuale..."
crontab -l > /tmp/crontab_backup_uninstall_$(date +%Y%m%d_%H%M%S) 2>/dev/null

# Conferma disinstallazione
echo "⚠️  Sei sicuro di voler rimuovere il monitoraggio XAMPP? (y/n)"
read -r response
if [ "$response" != "y" ] && [ "$response" != "Y" ]; then
    echo "❌ Disinstallazione annullata"
    exit 0
fi

# Rimuovi il job dal crontab
echo "🔧 Rimozione del cron job..."
crontab -l 2>/dev/null | grep -v "$MONITOR_SCRIPT" | crontab -

# Verifica rimozione
if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
    echo "❌ Errore: Impossibile rimuovere il cron job"
    exit 1
else
    echo "✅ Cron job rimosso con successo!"
fi

# Chiedi se rimuovere anche i log
echo ""
echo "🗂️  Vuoi rimuovere anche i file di log? (y/n)"
read -r log_response
if [ "$log_response" = "y" ] || [ "$log_response" = "Y" ]; then
    LOG_FILE="/opt/lampp/htdocs/piattaforma-collaborativa/logs/xampp-monitor.log"
    if [ -f "$LOG_FILE" ]; then
        rm -f "$LOG_FILE"
        echo "✅ File di log rimosso"
    else
        echo "ℹ️  Nessun file di log da rimuovere"
    fi
fi

echo ""
echo "🎉 Disinstallazione completata!"
echo "   Il monitoraggio XAMPP è stato rimosso dal sistema."

exit 0 