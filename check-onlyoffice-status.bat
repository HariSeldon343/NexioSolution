@echo off
echo ======================================
echo Controllo stato OnlyOffice
echo ======================================
echo.

echo Container Docker in esecuzione:
echo ---------------------------------------
docker ps --filter "name=nexio-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo.
echo ======================================
echo Test connettivita servizi:
echo ======================================
echo.

echo Test OnlyOffice (porta 8082):
curl -s -o nul -w "HTTP Status: %%{http_code}\n" http://localhost:8082/healthcheck

echo.
echo Test File Server (porta 8083):
curl -s -o nul -w "HTTP Status: %%{http_code}\n" http://localhost:8083/

echo.
echo ======================================
echo Log recenti OnlyOffice:
echo ======================================
docker logs --tail 10 nexio-onlyoffice 2>&1

echo.
echo ======================================
echo Utilizzo risorse:
echo ======================================
docker stats --no-stream --filter "name=nexio-"

echo.
pause