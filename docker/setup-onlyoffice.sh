#!/bin/bash
#===============================================================================
# ONLYOFFICE DOCKER SETUP SCRIPT
# Installa e configura OnlyOffice Document Server per Nexio
#===============================================================================

echo "======================================"
echo "OnlyOffice Docker Setup Script"
echo "======================================"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Percorsi
DOCKER_DIR="/mnt/c/xampp/htdocs/piattaforma-collaborativa/docker"
DOCS_DIR="/mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice"
PROJECT_DIR="/mnt/c/xampp/htdocs/piattaforma-collaborativa"

# Verifica prerequisiti
echo -e "${BLUE}Verificando prerequisiti...${NC}"
echo ""

# 1. Verifica Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}✗ Docker non è installato!${NC}"
    echo "Installa Docker Desktop per Windows e riprova."
    exit 1
fi
echo -e "${GREEN}✓ Docker trovato${NC}"

# 2. Verifica Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo -e "${YELLOW}⚠ docker-compose command non trovato, provando con 'docker compose'${NC}"
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi
echo -e "${GREEN}✓ Docker Compose disponibile${NC}"

# 3. Verifica se Docker è in esecuzione
if ! docker info &> /dev/null; then
    echo -e "${RED}✗ Docker non è in esecuzione!${NC}"
    echo "Avvia Docker Desktop e riprova."
    exit 1
fi
echo -e "${GREEN}✓ Docker è in esecuzione${NC}"

# 4. Verifica file di configurazione
echo ""
echo -e "${BLUE}Verificando file di configurazione...${NC}"

if [ ! -f "$DOCKER_DIR/docker-compose.yml" ]; then
    echo -e "${RED}✗ docker-compose.yml non trovato!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ docker-compose.yml trovato${NC}"

if [ ! -f "$DOCKER_DIR/nginx.conf" ]; then
    echo -e "${RED}✗ nginx.conf non trovato!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ nginx.conf trovato${NC}"

# 5. Verifica container esistenti
echo ""
echo -e "${BLUE}Verificando container esistenti...${NC}"
EXISTING_CONTAINERS=$(docker ps -a | grep -i onlyoffice | wc -l)
if [ $EXISTING_CONTAINERS -gt 0 ]; then
    echo -e "${YELLOW}⚠ Trovati $EXISTING_CONTAINERS container OnlyOffice esistenti${NC}"
    echo ""
    read -p "Vuoi rimuoverli prima di continuare? (yes/no): " remove_existing
    if [ "$remove_existing" == "yes" ]; then
        echo "Esecuzione script di pulizia..."
        bash "$DOCKER_DIR/cleanup-onlyoffice.sh"
        if [ $? -ne 0 ]; then
            echo -e "${RED}✗ Errore durante la pulizia${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠ Procedo con i container esistenti (potrebbero esserci conflitti)${NC}"
    fi
else
    echo -e "${GREEN}✓ Nessun container OnlyOffice esistente${NC}"
fi

# 6. Crea directory per documenti se non esiste
echo ""
echo -e "${BLUE}Preparando directory documenti...${NC}"
if [ ! -d "$DOCS_DIR" ]; then
    mkdir -p "$DOCS_DIR"
    echo -e "${GREEN}✓ Directory documenti creata: $DOCS_DIR${NC}"
else
    echo -e "${GREEN}✓ Directory documenti esistente: $DOCS_DIR${NC}"
fi

# Imposta permessi
chmod 777 "$DOCS_DIR"
echo -e "${GREEN}✓ Permessi impostati per directory documenti${NC}"

# 7. Avvia i container
echo ""
echo -e "${BLUE}Avviando container Docker...${NC}"
echo ""

cd "$DOCKER_DIR"

# Pull delle immagini
echo "Scaricamento immagini Docker..."
$DOCKER_COMPOSE pull
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Errore durante il download delle immagini${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Immagini scaricate${NC}"

# Avvia i container
echo ""
echo "Avvio container..."
$DOCKER_COMPOSE up -d
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Errore durante l'avvio dei container${NC}"
    exit 1
fi

# 8. Attendi che OnlyOffice sia pronto
echo ""
echo -e "${BLUE}Attendo che OnlyOffice sia pronto (può richiedere 1-2 minuti)...${NC}"

MAX_ATTEMPTS=60
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

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo ""
    echo -e "${YELLOW}⚠ OnlyOffice potrebbe non essere ancora pronto${NC}"
    echo "Verifica manualmente lo stato con: docker logs nexio-onlyoffice"
fi

# 9. Verifica stato container
echo ""
echo -e "${BLUE}Stato container:${NC}"
echo "----------------------------------------"
docker ps --filter "name=nexio-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# 10. Test connettività
echo ""
echo -e "${BLUE}Test connettività:${NC}"
echo "----------------------------------------"

# Test OnlyOffice
echo -n "OnlyOffice (http://localhost:8080): "
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|302"; then
    echo -e "${GREEN}✓ Raggiungibile${NC}"
else
    echo -e "${RED}✗ Non raggiungibile${NC}"
fi

# Test File Server
echo -n "File Server (http://localhost:8081): "
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/health | grep -q "200"; then
    echo -e "${GREEN}✓ Raggiungibile${NC}"
else
    echo -e "${YELLOW}⚠ Non raggiungibile (potrebbe essere normale)${NC}"
fi

# 11. Informazioni finali
echo ""
echo "======================================"
echo -e "${GREEN}Setup completato con successo!${NC}"
echo "======================================"
echo ""
echo -e "${BLUE}Informazioni di accesso:${NC}"
echo "----------------------------------------"
echo "OnlyOffice Document Server: http://localhost:8080"
echo "File Server (Nginx):        http://localhost:8081"
echo "Health Check:               http://localhost:8080/healthcheck"
echo ""
echo -e "${BLUE}Directory documenti:${NC}"
echo "Local:    $DOCS_DIR"
echo "Docker:   /var/www/onlyoffice/documentserver/App_Data/cache/files"
echo ""
echo -e "${BLUE}Comandi utili:${NC}"
echo "----------------------------------------"
echo "Visualizza logs:        docker logs -f nexio-onlyoffice"
echo "Stato container:        docker ps --filter 'name=nexio-'"
echo "Stop container:         cd $DOCKER_DIR && $DOCKER_COMPOSE down"
echo "Restart container:      cd $DOCKER_DIR && $DOCKER_COMPOSE restart"
echo "Pulizia completa:       bash $DOCKER_DIR/cleanup-onlyoffice.sh"
echo ""
echo -e "${YELLOW}Nota: JWT è disabilitato per semplificare il testing.${NC}"
echo -e "${YELLOW}      Abilita JWT_ENABLED=true in produzione!${NC}"
echo ""

# Crea file di stato
echo "Installation completed at: $(date)" > "$DOCKER_DIR/.setup-status"
echo "OnlyOffice URL: http://localhost:8080" >> "$DOCKER_DIR/.setup-status"

echo -e "${GREEN}Il sistema OnlyOffice è pronto per l'uso!${NC}"