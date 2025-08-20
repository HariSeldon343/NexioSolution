@echo off
echo ======================================
echo Test Connettivita Docker per OnlyOffice
echo ======================================

echo Test 1: Container in esecuzione?
echo --------------------------------------
docker ps --format "table {{.Names}}\t{{.Status}}" | findstr -i "onlyoffice documentserver nexio"
if errorlevel 1 (
    echo ERRORE: Nessun container OnlyOffice trovato!
    echo Esegui prima: docker-compose up -d
)

echo.
echo Test 2: Test host.docker.internal
echo --------------------------------------
echo Tentativo ping host.docker.internal...

REM Prima trova il nome esatto del container
for /f "tokens=1" %%i in ('docker ps --format "{{.Names}}" ^| findstr -i "onlyoffice documentserver nexio"') do (
    set CONTAINER_NAME=%%i
    goto :found
)
echo Container non trovato!
goto :end

:found
echo Container trovato: %CONTAINER_NAME%

echo.
echo Test ping host.docker.internal:
docker exec %CONTAINER_NAME% sh -c "ping -c 1 host.docker.internal 2>&1 || echo 'Ping fallito'"

echo.
echo Test 3: Curl a XAMPP root
echo --------------------------------------
docker exec %CONTAINER_NAME% sh -c "curl -I -s -o /dev/null -w '%%{http_code}' http://host.docker.internal/ 2>&1 || echo 'Connessione fallita'"

echo.
echo Test 4: Test documento specifico
echo --------------------------------------
docker exec %CONTAINER_NAME% sh -c "curl -s http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document.php?doc=22 2>&1 | head -20"

echo.
echo Test 5: Trovare IP alternativo
echo --------------------------------------
echo IP Gateway Docker:
docker network inspect bridge --format "{{range .IPAM.Config}}{{.Gateway}}{{end}}"

echo.
echo IP del PC (usa quello della rete locale):
ipconfig | findstr /C:"IPv4" | findstr /V "127.0.0.1"

:end
echo.
pause