@echo off
echo.
echo ================================================
echo   NEXIO PLATFORM - OnlyOffice Docker Setup
echo ================================================
echo.

echo 🚀 Avvio OnlyOffice Document Server con Docker...
echo.

REM Controlla se Docker è installato
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker non è installato o non è nel PATH
    echo.
    echo 💡 Per installare Docker:
    echo    1. Vai su: https://www.docker.com/products/docker-desktop
    echo    2. Scarica e installa Docker Desktop
    echo    3. Riavvia il computer
    echo    4. Riavvia questo script
    echo.
    pause
    exit /b 1
)

echo ✅ Docker trovato, controllo se è in esecuzione...

REM Controlla se Docker è in esecuzione
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker Desktop non è in esecuzione
    echo.
    echo 💡 Avvia Docker Desktop e riprova
    echo.
    pause
    exit /b 1
)

echo ✅ Docker Desktop è in esecuzione

REM Controlla se il container OnlyOffice esiste già
docker ps -a --filter name=nexio-onlyoffice --format "{{.Names}}" | findstr nexio-onlyoffice >nul
if %errorlevel% equ 0 (
    echo.
    echo 📋 Container OnlyOffice esistente trovato
    echo.
    echo Cosa vuoi fare?
    echo 1. Avvia il container esistente
    echo 2. Rimuovi e ricrea il container
    echo 3. Esci
    echo.
    set /p choice=Scegli un'opzione (1-3): 
    
    if "!choice!"=="1" goto start_existing
    if "!choice!"=="2" goto recreate
    if "!choice!"=="3" goto exit
    goto exit
)

:create_new
echo.
echo 🔧 Creazione nuovo container OnlyOffice...
echo.

REM Crea il container OnlyOffice
docker run -d ^
    --name nexio-onlyoffice ^
    --restart always ^
    -p 8080:80 ^
    -e JWT_ENABLED=false ^
    onlyoffice/documentserver

if %errorlevel% equ 0 (
    echo ✅ Container OnlyOffice creato con successo!
    goto success
) else (
    echo ❌ Errore nella creazione del container
    pause
    exit /b 1
)

:start_existing
echo.
echo 🔄 Avvio container esistente...
docker start nexio-onlyoffice
if %errorlevel% equ 0 (
    echo ✅ Container avviato con successo!
    goto success
) else (
    echo ❌ Errore nell'avvio del container
    pause
    exit /b 1
)

:recreate
echo.
echo 🗑️ Rimozione container esistente...
docker stop nexio-onlyoffice >nul 2>&1
docker rm nexio-onlyoffice >nul 2>&1
echo ✅ Container rimosso
goto create_new

:success
echo.
echo ================================================
echo          🎉 OnlyOffice PRONTO! 🎉
echo ================================================
echo.
echo ✅ OnlyOffice Document Server è ora in esecuzione
echo 🌐 Disponibile su: http://localhost:8080
echo 📋 Nome container: nexio-onlyoffice
echo.
echo ⏳ Attendi 30-60 secondi per l'inizializzazione completa
echo.
echo 💡 Per testare l'integrazione:
echo    1. Vai su: http://localhost/piattaforma-collaborativa/editor-onlyoffice.php
echo    2. Oppure usa la dashboard della piattaforma
echo.
echo 🔧 Comandi utili:
echo    - Fermare:    docker stop nexio-onlyoffice
echo    - Riavviare:  docker restart nexio-onlyoffice
echo    - Logs:       docker logs nexio-onlyoffice
echo    - Stato:      docker ps
echo.

:exit
echo Premi un tasto per uscire...
pause >nul
exit 