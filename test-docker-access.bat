@echo off
echo ========================================
echo   OnlyOffice Docker Access Test
echo ========================================
echo.

echo [1] Verifico se Docker è in esecuzione...
docker ps >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker non è in esecuzione o non è accessibile
    echo    Avvia Docker Desktop e riprova
    pause
    exit /b 1
)
echo ✅ Docker è in esecuzione

echo.
echo [2] Verifico container OnlyOffice...
docker ps | findstr nexio-onlyoffice
if errorlevel 1 (
    echo ❌ Container OnlyOffice non trovato
    echo    Esegui: docker-compose up -d
) else (
    echo ✅ Container OnlyOffice trovato
)

echo.
echo [3] Test accesso documento da container OnlyOffice...
echo.

REM Test con host.docker.internal (raccomandato per Windows)
echo Testing: host.docker.internal
docker exec nexio-onlyoffice curl -s -o /dev/null -w "HTTP Status: %%{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx
if errorlevel 0 (
    echo ✅ host.docker.internal funziona
) else (
    echo ❌ host.docker.internal non raggiungibile
)

echo.
echo [4] Verifica DNS nel container...
docker exec nexio-onlyoffice nslookup host.docker.internal

echo.
echo [5] Test download completo del documento...
docker exec nexio-onlyoffice wget -O /tmp/test.docx http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>&1 | findstr /C:"saved" /C:"ERROR"

echo.
echo [6] Verifica dimensione file scaricato...
docker exec nexio-onlyoffice ls -la /tmp/test.docx 2>nul

echo.
echo ========================================
echo   Raccomandazioni
echo ========================================
echo.
echo Se host.docker.internal non funziona:
echo.
echo 1. Trova il tuo IP locale:
echo    ipconfig | findstr IPv4
echo.
echo 2. Usa l'IP nel test-onlyoffice-http-working.php:
echo    url: "http://[TUO-IP]/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx"
echo.
echo 3. Assicurati che Windows Firewall permetta connessioni sulla porta 80
echo.
echo 4. Verifica che XAMPP Apache sia in ascolto su tutte le interfacce:
echo    - Apri C:\xampp\apache\conf\httpd.conf
echo    - Cerca "Listen" e assicurati sia "Listen 80" (non "Listen 127.0.0.1:80")
echo.
pause