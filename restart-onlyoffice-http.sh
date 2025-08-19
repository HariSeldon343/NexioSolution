#!/bin/bash

# Script per riavviare OnlyOffice in modalità HTTP (sviluppo)
# Questo evita i problemi con certificati SSL self-signed

echo "================================================"
echo "OnlyOffice HTTP Mode Restart Script"
echo "================================================"
echo ""

# Vai alla directory onlyoffice
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa/onlyoffice

echo "1. Fermando i container esistenti..."
docker-compose down

echo ""
echo "2. Pulendo eventuali volumi e cache..."
docker system prune -f

echo ""
echo "3. Riavviando i container in modalità HTTP..."
docker-compose up -d

echo ""
echo "4. Attendendo che i servizi siano pronti..."
sleep 10

echo ""
echo "5. Verificando lo stato dei container..."
docker-compose ps

echo ""
echo "6. Verificando gli endpoint HTTP..."
echo ""

# Test health check OnlyOffice
echo -n "OnlyOffice Document Server (HTTP:8082): "
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8082/healthcheck | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# Test API JavaScript
echo -n "OnlyOffice API JS: "
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8082/web-apps/apps/api/documents/api.js | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

# Test file server
echo -n "Nginx File Server (HTTP:8083): "
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8083/health | grep -q "200"; then
    echo "✅ OK"
else
    echo "❌ FAILED"
fi

echo ""
echo "================================================"
echo "OnlyOffice è ora in esecuzione in modalità HTTP"
echo "================================================"
echo ""
echo "URLs disponibili:"
echo "- Document Server: http://localhost:8082"
echo "- File Server: http://localhost:8083"
echo "- Test Page: http://localhost/piattaforma-collaborativa/test-onlyoffice-http-working.php"
echo ""
echo "NOTA: Questa configurazione è solo per sviluppo."
echo "      Per produzione, configurare HTTPS con certificati validi."
echo ""
echo "Per vedere i log:"
echo "  docker-compose logs -f"
echo ""
echo "Per fermare i container:"
echo "  docker-compose down"
echo ""