#!/bin/bash

# Setup completo per il sistema di monitoraggio XAMPP
# Questo script configura tutto ciò che è necessario

echo "🚀 Setup Completo Sistema di Monitoraggio XAMPP"
echo "================================================"

CURRENT_USER=$(whoami)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "👤 Utente corrente: $CURRENT_USER"
echo "📁 Directory script: $SCRIPT_DIR"
echo ""

# Passo 1: Verifica XAMPP
echo "🔍 Passo 1: Verifica installazione XAMPP"
if [ -f "/opt/lampp/lampp" ]; then
    echo "   ✅ XAMPP trovato in /opt/lampp"
else
    echo "   ❌ XAMPP non trovato in /opt/lampp"
    echo "   Installa XAMPP prima di continuare!"
    exit 1
fi

# Passo 2: Configura permessi sudo
echo ""
echo "🔐 Passo 2: Configurazione permessi sudo"
echo "Per permettere al sistema di riavviare XAMPP automaticamente,"
echo "è necessario configurare i permessi sudo."
echo ""
echo "Esegui questi comandi come amministratore:"
echo ""
echo "sudo tee /etc/sudoers.d/xampp-monitor << 'EOF'"
echo "$CURRENT_USER ALL=(ALL) NOPASSWD: /opt/lampp/lampp"
echo "EOF"
echo ""
echo "sudo chmod 440 /etc/sudoers.d/xampp-monitor"
echo ""

read -p "🤔 Hai già configurato i permessi sudo? (y/n): " sudo_configured

if [ "$sudo_configured" != "y" ] && [ "$sudo_configured" != "Y" ]; then
    echo ""
    echo "⚠️  Per favore configura i permessi sudo prima di continuare."
    echo "   Copia e incolla i comandi mostrati sopra in un terminale con sudo."
    echo ""
    echo "   Poi riesegui questo script."
    exit 1
fi

# Passo 3: Test permessi
echo ""
echo "🧪 Passo 3: Test permessi sudo"
if sudo -n /opt/lampp/lampp status >/dev/null 2>&1; then
    echo "   ✅ Permessi sudo configurati correttamente"
else
    echo "   ❌ Permessi sudo non funzionanti"
    echo "   Verifica la configurazione e riprova."
    exit 1
fi

# Passo 4: Test script di monitoraggio
echo ""
echo "🧪 Passo 4: Test script di monitoraggio"
if "$SCRIPT_DIR/xampp-monitor.sh" >/dev/null 2>&1; then
    echo "   ✅ Script di monitoraggio funzionante"
else
    echo "   ❌ Errore nello script di monitoraggio"
    echo "   Controlla i log per dettagli."
    exit 1
fi

# Passo 5: Installazione cron job
echo ""
echo "⚙️  Passo 5: Installazione monitoraggio automatico"
read -p "🤔 Vuoi installare il monitoraggio ogni 10 minuti? (y/n): " install_cron

if [ "$install_cron" = "y" ] || [ "$install_cron" = "Y" ]; then
    echo ""
    echo "🔧 Installazione in corso..."
    if "$SCRIPT_DIR/install-xampp-monitor.sh"; then
        echo "   ✅ Monitoraggio installato con successo!"
    else
        echo "   ❌ Errore durante l'installazione"
        exit 1
    fi
else
    echo "   ⏭️  Installazione automatica saltata"
fi

# Passo 6: Verifica finale
echo ""
echo "✅ Passo 6: Verifica finale"
"$SCRIPT_DIR/status-xampp-monitor.sh"

echo ""
echo "🎉 Setup completato!"
echo ""
echo "📋 Cosa succede ora:"
echo "   • Il sistema controlla XAMPP ogni 10 minuti"
echo "   • I log sono salvati in: logs/xampp-monitor.log"
echo "   • Le notifiche email vengono inviate in caso di problemi"
echo ""
echo "🛠️  Comandi utili:"
echo "   • Stato: ./scripts/status-xampp-monitor.sh"
echo "   • Log live: tail -f logs/xampp-monitor.log"
echo "   • Test manuale: ./scripts/xampp-monitor.sh"
echo "   • Disinstalla: ./scripts/uninstall-xampp-monitor.sh"
echo ""

exit 0 