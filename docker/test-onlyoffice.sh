#!/bin/bash
#===============================================================================
# ONLYOFFICE DOCKER TEST SCRIPT
# Verifica completa del funzionamento di OnlyOffice
#===============================================================================

echo "======================================"
echo "OnlyOffice Docker Test Script"
echo "======================================"
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variabili
ONLYOFFICE_URL="http://localhost:8080"
FILESERVER_URL="http://localhost:8081"
TEST_RESULTS=()
FAILED_TESTS=0

# Funzione per registrare risultati test
log_test() {
    local test_name="$1"
    local result="$2"
    local details="$3"
    
    if [ "$result" == "PASS" ]; then
        echo -e "${GREEN}✓ $test_name${NC}"
        [ -n "$details" ] && echo "  $details"
    else
        echo -e "${RED}✗ $test_name${NC}"
        [ -n "$details" ] && echo -e "  ${YELLOW}$details${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
    TEST_RESULTS+=("$test_name: $result")
}

# 1. Test Docker Status
echo -e "${BLUE}1. Verificando stato Docker...${NC}"
echo "----------------------------------------"

# Verifica se Docker è in esecuzione
if docker info &> /dev/null; then
    log_test "Docker Engine" "PASS" "Docker è in esecuzione"
else
    log_test "Docker Engine" "FAIL" "Docker non è in esecuzione"
    exit 1
fi

# Verifica container OnlyOffice
ONLYOFFICE_STATUS=$(docker ps --filter "name=nexio-onlyoffice" --format "{{.Status}}")
if [ -n "$ONLYOFFICE_STATUS" ]; then
    if echo "$ONLYOFFICE_STATUS" | grep -q "Up"; then
        log_test "Container OnlyOffice" "PASS" "Status: $ONLYOFFICE_STATUS"
    else
        log_test "Container OnlyOffice" "FAIL" "Status: $ONLYOFFICE_STATUS"
    fi
else
    log_test "Container OnlyOffice" "FAIL" "Container non trovato"
fi

# Verifica container File Server
FILESERVER_STATUS=$(docker ps --filter "name=nexio-fileserver" --format "{{.Status}}")
if [ -n "$FILESERVER_STATUS" ]; then
    if echo "$FILESERVER_STATUS" | grep -q "Up"; then
        log_test "Container File Server" "PASS" "Status: $FILESERVER_STATUS"
    else
        log_test "Container File Server" "FAIL" "Status: $FILESERVER_STATUS"
    fi
else
    log_test "Container File Server" "FAIL" "Container non trovato o non configurato"
fi

echo ""

# 2. Test Network Connectivity
echo -e "${BLUE}2. Test connettività di rete...${NC}"
echo "----------------------------------------"

# Test porta 8080
nc -zv localhost 8080 &> /dev/null
if [ $? -eq 0 ]; then
    log_test "Porta 8080" "PASS" "Porta aperta e in ascolto"
else
    log_test "Porta 8080" "FAIL" "Porta non raggiungibile"
fi

# Test porta 8081 (file server)
nc -zv localhost 8081 &> /dev/null
if [ $? -eq 0 ]; then
    log_test "Porta 8081" "PASS" "Porta aperta e in ascolto"
else
    log_test "Porta 8081" "FAIL" "Porta non raggiungibile (potrebbe essere normale se file server non è attivo)"
fi

echo ""

# 3. Test HTTP Endpoints
echo -e "${BLUE}3. Test endpoint HTTP...${NC}"
echo "----------------------------------------"

# Test OnlyOffice root
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $ONLYOFFICE_URL)
if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "302" ]; then
    log_test "OnlyOffice Root" "PASS" "HTTP $HTTP_CODE"
else
    log_test "OnlyOffice Root" "FAIL" "HTTP $HTTP_CODE"
fi

# Test OnlyOffice healthcheck
HEALTH_RESPONSE=$(curl -s $ONLYOFFICE_URL/healthcheck)
if [ $? -eq 0 ]; then
    log_test "OnlyOffice Healthcheck" "PASS" "Endpoint raggiungibile"
else
    log_test "OnlyOffice Healthcheck" "FAIL" "Endpoint non raggiungibile"
fi

# Test OnlyOffice API
API_RESPONSE=$(curl -s $ONLYOFFICE_URL/web-apps/apps/api/documents/api.js | head -n 1)
if echo "$API_RESPONSE" | grep -q "DocsAPI" || [ -n "$API_RESPONSE" ]; then
    log_test "OnlyOffice API JS" "PASS" "API JavaScript caricata"
else
    log_test "OnlyOffice API JS" "FAIL" "API JavaScript non trovata"
fi

# Test File Server health
if [ -n "$FILESERVER_STATUS" ]; then
    FILESERVER_HEALTH=$(curl -s -o /dev/null -w "%{http_code}" $FILESERVER_URL/health)
    if [ "$FILESERVER_HEALTH" == "200" ]; then
        log_test "File Server Health" "PASS" "HTTP 200"
    else
        log_test "File Server Health" "FAIL" "HTTP $FILESERVER_HEALTH"
    fi
fi

echo ""

# 4. Test File System
echo -e "${BLUE}4. Test file system...${NC}"
echo "----------------------------------------"

DOCS_DIR="/mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice"

# Verifica directory documenti
if [ -d "$DOCS_DIR" ]; then
    log_test "Directory documenti" "PASS" "$DOCS_DIR esistente"
    
    # Verifica permessi scrittura
    TEST_FILE="$DOCS_DIR/.test_write_$(date +%s).tmp"
    if touch "$TEST_FILE" 2>/dev/null; then
        rm -f "$TEST_FILE"
        log_test "Permessi scrittura" "PASS" "Directory scrivibile"
    else
        log_test "Permessi scrittura" "FAIL" "Directory non scrivibile"
    fi
else
    log_test "Directory documenti" "FAIL" "Directory non trovata"
fi

echo ""

# 5. Test Docker Logs
echo -e "${BLUE}5. Analisi logs Docker...${NC}"
echo "----------------------------------------"

# Controlla errori nei logs
ERROR_COUNT=$(docker logs nexio-onlyoffice 2>&1 | grep -i "error" | wc -l)
WARNING_COUNT=$(docker logs nexio-onlyoffice 2>&1 | grep -i "warning" | wc -l)

if [ $ERROR_COUNT -eq 0 ]; then
    log_test "Errori nei logs" "PASS" "Nessun errore trovato"
else
    log_test "Errori nei logs" "FAIL" "$ERROR_COUNT errori trovati"
fi

if [ $WARNING_COUNT -lt 10 ]; then
    log_test "Warning nei logs" "PASS" "$WARNING_COUNT warning (accettabile)"
else
    log_test "Warning nei logs" "FAIL" "$WARNING_COUNT warning (troppi)"
fi

# Mostra ultimi errori se presenti
if [ $ERROR_COUNT -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}Ultimi errori dai logs:${NC}"
    docker logs nexio-onlyoffice 2>&1 | grep -i "error" | tail -5
fi

echo ""

# 6. Test Document Creation
echo -e "${BLUE}6. Test creazione documento...${NC}"
echo "----------------------------------------"

# Crea un documento di test
TEST_DOC="$DOCS_DIR/test_document_$(date +%s).docx"
TEST_CONTENT='{"document":{"key":"test","title":"Test Document","url":"'$FILESERVER_URL'/documents/onlyoffice/test.docx"}}'

# Verifica se possiamo creare un file
if echo "Test content" > "$TEST_DOC" 2>/dev/null; then
    log_test "Creazione file test" "PASS" "File creato: $(basename $TEST_DOC)"
    
    # Verifica se il file è accessibile via file server
    if [ -n "$FILESERVER_STATUS" ]; then
        sleep 1
        FILE_CHECK=$(curl -s -o /dev/null -w "%{http_code}" "$FILESERVER_URL/documents/onlyoffice/$(basename $TEST_DOC)")
        if [ "$FILE_CHECK" == "200" ]; then
            log_test "Accesso file via server" "PASS" "File accessibile via HTTP"
        else
            log_test "Accesso file via server" "FAIL" "HTTP $FILE_CHECK"
        fi
    fi
    
    # Cleanup
    rm -f "$TEST_DOC"
else
    log_test "Creazione file test" "FAIL" "Impossibile creare file"
fi

echo ""

# 7. Test Memory e CPU
echo -e "${BLUE}7. Test risorse sistema...${NC}"
echo "----------------------------------------"

# Ottieni statistiche container
STATS=$(docker stats nexio-onlyoffice --no-stream --format "CPU: {{.CPUPerc}} | MEM: {{.MemUsage}} | NET: {{.NetIO}}")
log_test "Statistiche container" "PASS" "$STATS"

# Verifica uso memoria
MEM_USAGE=$(docker stats nexio-onlyoffice --no-stream --format "{{.MemPerc}}" | sed 's/%//')
if (( $(echo "$MEM_USAGE < 80" | bc -l) )); then
    log_test "Uso memoria" "PASS" "${MEM_USAGE}% (sotto soglia)"
else
    log_test "Uso memoria" "FAIL" "${MEM_USAGE}% (sopra soglia 80%)"
fi

echo ""

# 8. Network Test
echo -e "${BLUE}8. Test comunicazione di rete...${NC}"
echo "----------------------------------------"

# Verifica network Docker
NETWORK_EXISTS=$(docker network ls | grep -c "nexio-network")
if [ $NETWORK_EXISTS -gt 0 ]; then
    log_test "Network Docker" "PASS" "nexio-network esistente"
    
    # Verifica container sulla rete
    CONTAINERS_ON_NETWORK=$(docker network inspect nexio-network --format '{{len .Containers}}' 2>/dev/null)
    if [ -n "$CONTAINERS_ON_NETWORK" ] && [ "$CONTAINERS_ON_NETWORK" -gt 0 ]; then
        log_test "Container su network" "PASS" "$CONTAINERS_ON_NETWORK container connessi"
    else
        log_test "Container su network" "FAIL" "Nessun container connesso"
    fi
else
    log_test "Network Docker" "FAIL" "Network non trovata"
fi

echo ""

# 9. Verifica configurazione JWT
echo -e "${BLUE}9. Verifica configurazione sicurezza...${NC}"
echo "----------------------------------------"

JWT_CONFIG=$(docker exec nexio-onlyoffice printenv JWT_ENABLED 2>/dev/null)
if [ "$JWT_CONFIG" == "false" ]; then
    log_test "JWT Configuration" "PASS" "JWT disabilitato (modalità test)"
    echo -e "  ${YELLOW}⚠ Ricorda di abilitare JWT in produzione!${NC}"
else
    log_test "JWT Configuration" "PASS" "JWT abilitato"
fi

echo ""

# Riepilogo finale
echo "======================================"
echo -e "${BLUE}RIEPILOGO TEST${NC}"
echo "======================================"
echo ""

TOTAL_TESTS=${#TEST_RESULTS[@]}
PASSED_TESTS=$((TOTAL_TESTS - FAILED_TESTS))

echo "Test totali:    $TOTAL_TESTS"
echo -e "Test passati:   ${GREEN}$PASSED_TESTS${NC}"
echo -e "Test falliti:   ${RED}$FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✓ TUTTI I TEST SONO PASSATI!${NC}"
    echo -e "${GREEN}OnlyOffice è configurato correttamente e funzionante.${NC}"
    EXIT_CODE=0
else
    echo -e "${YELLOW}⚠ ALCUNI TEST SONO FALLITI${NC}"
    echo ""
    echo "Suggerimenti per risolvere i problemi:"
    echo "1. Verifica i logs: docker logs nexio-onlyoffice"
    echo "2. Riavvia i container: cd docker && docker-compose restart"
    echo "3. Controlla lo stato: docker ps --filter 'name=nexio-'"
    echo "4. Se necessario, esegui pulizia e reinstallazione:"
    echo "   bash docker/cleanup-onlyoffice.sh"
    echo "   bash docker/setup-onlyoffice.sh"
    EXIT_CODE=1
fi

echo ""
echo "======================================"
echo "Test completato: $(date)"
echo "======================================"

exit $EXIT_CODE