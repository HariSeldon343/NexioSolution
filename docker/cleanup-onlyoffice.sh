#!/bin/bash
#===============================================================================
# ONLYOFFICE DOCKER CLEANUP SCRIPT
# Rimuove completamente tutti i container, volumi e reti OnlyOffice
#===============================================================================

echo "======================================"
echo "OnlyOffice Docker Cleanup Script"
echo "======================================"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}ATTENZIONE: Questo script rimuoverà TUTTI i container, volumi e reti OnlyOffice${NC}"
echo -e "${YELLOW}Tutti i dati non salvati andranno persi!${NC}"
echo ""
read -p "Sei sicuro di voler continuare? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo -e "${RED}Operazione annullata${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}Avvio pulizia completa...${NC}"
echo ""

# 1. Stop tutti i container OnlyOffice
echo "1. Stopping OnlyOffice containers..."
docker ps -a | grep -i onlyoffice | awk '{print $1}' | xargs -r docker stop 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Container fermati${NC}"
else
    echo -e "${YELLOW}⚠ Nessun container da fermare o già fermati${NC}"
fi

# 2. Rimuovi tutti i container OnlyOffice
echo ""
echo "2. Removing OnlyOffice containers..."
docker ps -a | grep -i onlyoffice | awk '{print $1}' | xargs -r docker rm -f 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Container rimossi${NC}"
else
    echo -e "${YELLOW}⚠ Nessun container da rimuovere${NC}"
fi

# 3. Rimuovi container con nome specifico
echo ""
echo "3. Removing named containers..."
docker rm -f nexio-documentserver 2>/dev/null
docker rm -f onlyoffice-documentserver 2>/dev/null
docker rm -f documentserver 2>/dev/null
echo -e "${GREEN}✓ Container nominati rimossi${NC}"

# 4. Rimuovi volumi OnlyOffice
echo ""
echo "4. Removing OnlyOffice volumes..."
docker volume ls | grep -i onlyoffice | awk '{print $2}' | xargs -r docker volume rm -f 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Volumi rimossi${NC}"
else
    echo -e "${YELLOW}⚠ Nessun volume da rimuovere${NC}"
fi

# 5. Rimuovi reti OnlyOffice
echo ""
echo "5. Removing OnlyOffice networks..."
docker network ls | grep -i onlyoffice | awk '{print $1}' | xargs -r docker network rm 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Reti rimosse${NC}"
else
    echo -e "${YELLOW}⚠ Nessuna rete da rimuovere${NC}"
fi

# 6. Rimuovi immagini OnlyOffice (opzionale)
echo ""
read -p "Vuoi rimuovere anche le immagini Docker di OnlyOffice? (yes/no): " remove_images
if [ "$remove_images" == "yes" ]; then
    echo "Removing OnlyOffice images..."
    docker images | grep -i onlyoffice | awk '{print $3}' | xargs -r docker rmi -f 2>/dev/null
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Immagini rimosse${NC}"
    else
        echo -e "${YELLOW}⚠ Nessuna immagine da rimuovere${NC}"
    fi
fi

# 7. Pulizia file temporanei locali
echo ""
echo "6. Cleaning local temporary files..."
rm -rf /mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice/temp_* 2>/dev/null
rm -rf /mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice/cache_* 2>/dev/null
echo -e "${GREEN}✓ File temporanei rimossi${NC}"

# 8. Docker system prune (opzionale)
echo ""
read -p "Vuoi eseguire docker system prune per pulire risorse inutilizzate? (yes/no): " do_prune
if [ "$do_prune" == "yes" ]; then
    echo "Running docker system prune..."
    docker system prune -f
    echo -e "${GREEN}✓ Pulizia sistema completata${NC}"
fi

echo ""
echo "======================================"
echo -e "${GREEN}Pulizia completata con successo!${NC}"
echo "======================================"
echo ""
echo "Stato attuale Docker:"
echo "---------------------"
echo "Container OnlyOffice attivi:"
docker ps | grep -i onlyoffice || echo "Nessuno"
echo ""
echo "Container OnlyOffice totali:"
docker ps -a | grep -i onlyoffice || echo "Nessuno"
echo ""
echo "Reti OnlyOffice:"
docker network ls | grep -i onlyoffice || echo "Nessuna"
echo ""
echo "Volumi OnlyOffice:"
docker volume ls | grep -i onlyoffice || echo "Nessuno"
echo ""
echo -e "${GREEN}Il sistema è pronto per una nuova installazione pulita di OnlyOffice${NC}"