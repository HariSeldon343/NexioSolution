#!/bin/bash

# Script per configurare il cron job di controllo scadenza password

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
CRON_SCRIPT="$PROJECT_DIR/cron/check-password-expiry.php"
CRON_LOG="$PROJECT_DIR/logs/password-expiry-cron.log"

echo "=== Setup Cron Job Scadenza Password ==="
echo "Directory progetto: $PROJECT_DIR"

# Verifica che lo script PHP esista
if [ ! -f "$CRON_SCRIPT" ]; then
    echo "❌ Errore: Script PHP non trovato in $CRON_SCRIPT"
    exit 1
fi

# Crea directory logs se non esiste
mkdir -p "$PROJECT_DIR/logs"

# Definisci il comando cron
CRON_CMD="/opt/lampp/bin/php $CRON_SCRIPT >> $CRON_LOG 2>&1"

# Definisci la pianificazione (ogni giorno alle 8:00)
CRON_SCHEDULE="0 8 * * *"

# Crea entry cron
CRON_ENTRY="$CRON_SCHEDULE $CRON_CMD"

# Verifica se il cron job esiste già
if crontab -l 2>/dev/null | grep -q "$CRON_SCRIPT"; then
    echo "⚠️  Cron job già esistente. Rimuovo la vecchia versione..."
    # Rimuovi il vecchio cron job
    crontab -l 2>/dev/null | grep -v "$CRON_SCRIPT" | crontab -
fi

# Aggiungi il nuovo cron job
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

echo "✅ Cron job configurato con successo!"
echo ""
echo "📋 Dettagli:"
echo "   - Esecuzione: Ogni giorno alle 8:00"
echo "   - Script: $CRON_SCRIPT"
echo "   - Log: $CRON_LOG"
echo ""
echo "📌 Notifiche inviate:"
echo "   - 7 giorni prima della scadenza"
echo "   - 3 giorni prima della scadenza"
echo "   - 1 giorno prima della scadenza"
echo "   - Ogni lunedì per password già scadute"
echo ""
echo "Per verificare il cron job:"
echo "   crontab -l | grep password-expiry"
echo ""
echo "Per testare manualmente:"
echo "   /opt/lampp/bin/php $CRON_SCRIPT"