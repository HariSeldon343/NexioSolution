@echo off
echo ======================================
echo Test Connettivita OnlyOffice
echo ======================================

echo.
echo [1] Container attivi:
docker ps --filter "name=nexio-"

echo.
echo [2] Test da container a host con host.docker.internal:
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>nul
if errorlevel 1 (
    echo ❌ Fallito con host.docker.internal
) else (
    echo ✅ Successo con host.docker.internal
)

echo.
echo [3] Trova IP della macchina host:
echo.
echo IPv4 Addresses trovati:
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /C:"IPv4"') do (
    for /f "tokens=1" %%b in ("%%a") do (
        echo   - %%b
        set LASTIP=%%b
    )
)

echo.
echo [4] Test con IP locale %LASTIP%:
docker exec nexio-onlyoffice curl -I http://%LASTIP%/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>nul
if errorlevel 1 (
    echo ❌ Fallito con IP %LASTIP%
) else (
    echo ✅ Successo con IP %LASTIP%
)

echo.
echo [5] Test ping da container a host:
docker exec nexio-onlyoffice ping -c 2 host.docker.internal 2>nul
if errorlevel 1 (
    echo    host.docker.internal non pingabile
)

echo.
echo [6] Mostra route nel container:
docker exec nexio-onlyoffice ip route 2>nul

echo.
echo [7] Test porta 80 su host.docker.internal:
docker exec nexio-onlyoffice nc -zv host.docker.internal 80 2>&1

echo.
echo ======================================
echo   SUGGERIMENTI
echo ======================================
echo.
echo Se nessun metodo funziona:
echo 1. Verifica che Windows Firewall permetta connessioni in entrata sulla porta 80
echo 2. Verifica che Apache sia in ascolto su tutte le interfacce (non solo 127.0.0.1)
echo 3. Prova a usare l'IP trovato sopra nel file test-onlyoffice-http-working.php
echo.
pause