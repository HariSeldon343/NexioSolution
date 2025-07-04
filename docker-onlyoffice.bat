@echo off
echo.
echo ================================================
echo   NEXIO PLATFORM - OnlyOffice Docker Manager
echo ================================================
echo.

:menu
echo Scegli un'opzione:
echo.
echo 1. Avvia OnlyOffice Document Server
echo 2. Ferma OnlyOffice Document Server  
echo 3. Riavvia OnlyOffice Document Server
echo 4. Vedi stato containers
echo 5. Vedi logs OnlyOffice
echo 6. Apri OnlyOffice nel browser
echo 7. Test integrazione completa
echo 8. Rimuovi container OnlyOffice
echo 0. Esci
echo.
set /p choice=Inserisci la tua scelta (0-8): 

if "%choice%"=="1" goto start
if "%choice%"=="2" goto stop
if "%choice%"=="3" goto restart
if "%choice%"=="4" goto status
if "%choice%"=="5" goto logs
if "%choice%"=="6" goto open
if "%choice%"=="7" goto test
if "%choice%"=="8" goto remove
if "%choice%"=="0" goto exit
goto menu

:start
echo.
echo 🚀 Avvio OnlyOffice Document Server...
docker run -i -t -d -p 8080:80 --restart=always --name onlyoffice-documentserver onlyoffice/documentserver
if %errorlevel%==0 (
    echo ✅ OnlyOffice avviato con successo!
    echo 📡 Disponibile su: http://localhost:8080
    echo ⏳ Attendi 30-60 secondi per l'inizializzazione completa
) else (
    echo ❌ Errore nell'avvio di OnlyOffice
    echo 💡 Verifica che Docker sia in esecuzione
)
echo.
pause
goto menu

:stop
echo.
echo 🛑 Arresto OnlyOffice Document Server...
docker stop onlyoffice-documentserver
if %errorlevel%==0 (
    echo ✅ OnlyOffice fermato con successo!
) else (
    echo ❌ Errore nell'arresto o container non trovato
)
echo.
pause
goto menu

:restart
echo.
echo 🔄 Riavvio OnlyOffice Document Server...
docker restart onlyoffice-documentserver
if %errorlevel%==0 (
    echo ✅ OnlyOffice riavviato con successo!
    echo 📡 Disponibile su: http://localhost:8080
) else (
    echo ❌ Errore nel riavvio o container non trovato
)
echo.
pause
goto menu

:status
echo.
echo 📊 Stato containers Docker:
echo.
docker ps -a --filter name=onlyoffice
echo.
pause
goto menu

:logs
echo.
echo 📋 Logs OnlyOffice (ultimi 50 righe):
echo.
docker logs --tail 50 onlyoffice-documentserver
echo.
pause
goto menu

:open
echo.
echo 🌐 Apertura OnlyOffice nel browser...
start "" "http://localhost:8080"
echo.
echo 💡 OnlyOffice dovrebbe aprirsi nel tuo browser predefinito
echo.
pause
goto menu

:test
echo.
echo 🧪 Apertura test integrazione completa...
start "" "http://localhost/piattaforma-collaborativa/test-onlyoffice.php"
echo.
echo 💡 La pagina di test dovrebbe aprirsi nel tuo browser
echo.
pause
goto menu

:remove
echo.
echo ⚠️  ATTENZIONE: Stai per rimuovere completamente il container OnlyOffice
echo 📂 Tutti i dati nel container saranno eliminati
echo.
set /p confirm=Sei sicuro? (s/N): 
if /i "%confirm%"=="s" (
    echo.
    echo 🗑️  Rimozione container OnlyOffice...
    docker stop onlyoffice-documentserver
    docker rm onlyoffice-documentserver
    echo ✅ Container rimosso!
) else (
    echo 🚫 Operazione annullata
)
echo.
pause
goto menu

:exit
echo.
echo 👋 Arrivederci!
echo.
pause
exit

:error
echo ❌ Opzione non valida. Riprova.
pause
goto menu 