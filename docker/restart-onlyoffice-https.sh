#!/bin/bash
# Script per riavviare OnlyOffice con supporto HTTPS su porta 8443

echo "================================================"
echo "OnlyOffice HTTPS Configuration - Porta 8443"
echo "================================================"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Cambia alla directory docker
cd "$(dirname "$0")"

echo -e "${YELLOW}1. Fermando i container esistenti...${NC}"
docker-compose down

echo -e "${YELLOW}2. Rimuovendo vecchi container e volumi...${NC}"
docker rm -f nexio-onlyoffice nexio-fileserver 2>/dev/null
docker volume prune -f

echo -e "${YELLOW}3. Avviando i container con nuova configurazione...${NC}"
docker-compose up -d

echo -e "${YELLOW}4. Attendendo che OnlyOffice sia pronto...${NC}"
echo "   Questo può richiedere 30-60 secondi..."

# Attendi che OnlyOffice sia pronto
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if curl -k -s https://localhost:8443/healthcheck > /dev/null 2>&1; then
        echo -e "${GREEN}✅ OnlyOffice HTTPS è online!${NC}"
        break
    elif curl -s http://localhost:8080/healthcheck > /dev/null 2>&1; then
        echo -e "${GREEN}✅ OnlyOffice HTTP è online!${NC}"
        break
    fi
    echo -n "."
    sleep 2
    attempt=$((attempt + 1))
done

echo ""
echo -e "${YELLOW}5. Verificando lo stato dei servizi...${NC}"
docker-compose ps

echo ""
echo "================================================"
echo -e "${GREEN}Configurazione completata!${NC}"
echo "================================================"
echo ""
echo "URL disponibili:"
echo "  - OnlyOffice HTTPS: https://localhost:8443"
echo "  - OnlyOffice HTTP:  http://localhost:8080"
echo "  - File Server:      http://localhost:8081"
echo ""
echo -e "${YELLOW}⚠️  IMPORTANTE per HTTPS:${NC}"
echo "1. Apri https://localhost:8443/healthcheck nel browser"
echo "2. Accetta il certificato self-signed"
echo "3. Poi visita: http://localhost/piattaforma-collaborativa/test-onlyoffice-https.php"
echo ""
echo "Per vedere i log:"
echo "  docker logs -f nexio-onlyoffice"
echo ""
echo "Per fermare i servizi:"
echo "  docker-compose down"
echo ""