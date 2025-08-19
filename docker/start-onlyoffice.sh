#!/bin/bash
#===============================================================================
# ONLYOFFICE QUICK START SCRIPT
# Avvia rapidamente OnlyOffice con un singolo comando
#===============================================================================

echo "======================================"
echo "OnlyOffice Quick Start"
echo "======================================"
echo ""

# Colori
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Percorsi
DOCKER_DIR="/mnt/c/xampp/htdocs/piattaforma-collaborativa/docker"

# Cambia alla directory Docker
cd "$DOCKER_DIR"

# Verifica se i container sono già in esecuzione
if docker ps | grep -q "nexio-onlyoffice"; then
    echo -e "${YELLOW}OnlyOffice è già in esecuzione${NC}"
    echo ""
    read -p "Vuoi riavviarlo? (yes/no): " restart
    if [ "$restart" == "yes" ]; then
        echo -e "${YELLOW}Riavvio OnlyOffice...${NC}"
        docker-compose restart
    else
        echo -e "${GREEN}OnlyOffice è già pronto all'uso${NC}"
    fi
else
    echo -e "${GREEN}Avvio OnlyOffice...${NC}"
    docker-compose up -d
    
    # Attendi che sia pronto
    echo -e "${YELLOW}Attendo che OnlyOffice sia pronto...${NC}"
    sleep 5
    
    MAX_ATTEMPTS=30
    ATTEMPT=0
    while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
        if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/healthcheck | grep -q "200"; then
            echo -e "${GREEN}✓ OnlyOffice è pronto!${NC}"
            break
        fi
        echo -n "."
        sleep 2
        ATTEMPT=$((ATTEMPT + 1))
    done
fi

echo ""
echo "======================================"
echo -e "${GREEN}OnlyOffice Status${NC}"
echo "======================================"
docker ps --filter "name=nexio-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo ""
echo "======================================"
echo -e "${GREEN}URL di Accesso${NC}"
echo "======================================"
echo "OnlyOffice: http://localhost:8080"
echo "File Server: http://localhost:8081"
echo ""
echo -e "${YELLOW}Usa 'docker logs -f nexio-onlyoffice' per vedere i logs${NC}"