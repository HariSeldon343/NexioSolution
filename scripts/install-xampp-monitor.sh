#!/bin/bash

# Script di installazione per il monitoraggio XAMPP
# Configura il cron job per eseguire il controllo ogni 10 minuti

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/xampp-monitor.sh"
CRON_COMMAND="*/10 * * * * $MONITOR_SCRIPT >/dev/null 2>&1"

echo "🔧 Installazione Sistema di Monitoraggio XAMPP"
echo "================================================"

# Verifica che lo script di monitoraggio esista
if [ ! -f "$MONITOR_SCRIPT" ]; then
    echo "❌ Errore: Script di monitoraggio non trovato in $MONITOR_SCRIPT"
    exit 1
fi

# Verifica che lo script sia eseguibile
if [ ! -x "$MONITOR_SCRIPT" ]; then
    echo "🔧 Rendere eseguibile lo script di monitoraggio..."
    chmod +x "$MONITOR_SCRIPT"
fi

# Test dello script
echo "🧪 Test dello script di monitoraggio..."
if ! bash -n "$MONITOR_SCRIPT"; then
    echo "❌ Errore: Lo script contiene errori di sintassi"
    exit 1
fi

echo "✅ Script verificato con successo"

# Backup del crontab attuale
echo "💾 Backup del crontab attuale..."
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S) 2>/dev/null || echo "Nessun crontab esistente"

# Verifica se il job è già presente
if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
    echo "⚠️  Il monitoraggio XAMPP è già configurato nel crontab"
    echo "   Vuoi sostituirlo? (y/n)"
    read -r response
    if [ "$response" != "y" ] && [ "$response" != "Y" ]; then
        echo "❌ Installazione annullata"
        exit 0
    fi
    
    # Rimuovi la voce esistente
    crontab -l 2>/dev/null | grep -v "$MONITOR_SCRIPT" | crontab -
fi

# Aggiungi il nuovo job
echo "⚙️  Configurazione del cron job..."
(crontab -l 2>/dev/null; echo "$CRON_COMMAND") | crontab -

# Verifica che sia stato aggiunto correttamente
if crontab -l 2>/dev/null | grep -q "$MONITOR_SCRIPT"; then
    echo "✅ Cron job configurato con successo!"
    echo ""
    echo "📋 Configurazione attuale:"
    echo "   Comando: $CRON_COMMAND"
    echo "   Frequenza: Ogni 10 minuti, 24 ore al giorno"
    echo "   Log: /opt/lampp/htdocs/piattaforma-collaborativa/logs/xampp-monitor.log"
    echo ""
    echo "🔍 Per visualizzare i log in tempo reale:"
    echo "   tail -f /opt/lampp/htdocs/piattaforma-collaborativa/logs/xampp-monitor.log"
    echo ""
    echo "🗑️  Per rimuovere il monitoraggio:"
    echo "   $SCRIPT_DIR/uninstall-xampp-monitor.sh"
else
    echo "❌ Errore: Impossibile configurare il cron job"
    exit 1
fi

# Crea il file di log iniziale
LOG_DIR="/opt/lampp/htdocs/piattaforma-collaborativa/logs"
mkdir -p "$LOG_DIR"
touch "$LOG_DIR/xampp-monitor.log"

# Test manuale
echo ""
echo "🧪 Vuoi eseguire un test manuale dello script? (y/n)"
read -r test_response
if [ "$test_response" = "y" ] || [ "$test_response" = "Y" ]; then
    echo "🚀 Esecuzione test..."
    "$MONITOR_SCRIPT"
    echo ""
    echo "📊 Ultimi log:"
    tail -10 "$LOG_DIR/xampp-monitor.log" 2>/dev/null || echo "Nessun log disponibile"
fi

echo ""
echo "🎉 Installazione completata!"
echo "   Il sistema ora monitora XAMPP ogni 10 minuti automaticamente."

exit 0 