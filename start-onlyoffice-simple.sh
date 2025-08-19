#!/bin/bash

echo "======================================"
echo "🚀 AVVIO ONLYOFFICE SEMPLIFICATO"
echo "======================================"
echo ""

# Ferma eventuali container esistenti
echo "🛑 Fermo eventuali container esistenti..."
docker stop onlyoffice-documentserver 2>/dev/null
docker rm onlyoffice-documentserver 2>/dev/null

echo ""
echo "📦 Avvio OnlyOffice Document Server..."
echo "Configurazione:"
echo "  - Porta: 8082"
echo "  - JWT: DISABILITATO (per test)"
echo "  - URL: http://localhost:8082"
echo ""

# Avvia OnlyOffice SENZA JWT per test
docker run -d \
  --name onlyoffice-documentserver \
  -p 8082:80 \
  -e JWT_ENABLED=false \
  -e JWT_SECRET="" \
  -e JWT_HEADER="" \
  -e JWT_IN_BODY=false \
  -e USE_UNAUTHORIZED_STORAGE=true \
  -e WOPI_ENABLED=false \
  onlyoffice/documentserver:latest

echo ""
echo "⏳ Attendo che il server sia pronto (30 secondi)..."
sleep 30

echo ""
echo "🔍 Verifico stato del server..."
echo ""

# Test del server
if curl -s http://localhost:8082/healthcheck | grep -q "true"; then
    echo "✅ SERVER ONLYOFFICE ATTIVO E FUNZIONANTE!"
    echo ""
    echo "📋 Prossimi passi:"
    echo "1. Apri: http://localhost/piattaforma-collaborativa/test-onlyoffice-status.php"
    echo "2. Verifica che tutti i test siano verdi"
    echo "3. Apri: http://localhost/piattaforma-collaborativa/test-onlyoffice-simple.html"
    echo "4. Clicca su 'Carica Editor'"
    echo ""
else
    echo "❌ Il server non risponde ancora. Controlla i log:"
    echo "   docker logs onlyoffice-documentserver"
    echo ""
fi

echo "======================================"
echo "📊 Stato Container:"
docker ps | grep onlyoffice
echo "======================================"