@echo off
echo ===============================================
echo   Scoperta IP e Test Connettivita OnlyOffice
echo ===============================================
echo.

echo [1] IP della macchina Windows:
echo ----------------------------------------
ipconfig | findstr /C:"IPv4"
echo.

echo [2] IP WSL2 (se disponibile):
echo ----------------------------------------
wsl hostname -I 2>nul
if errorlevel 1 (
    echo WSL2 non disponibile o non in esecuzione
)
echo.

echo [3] Test da Docker a vari host:
echo ----------------------------------------
echo.

echo Test con localhost (da container):
docker exec nexio-onlyoffice curl -s -o /dev/null -w "  Risultato: HTTP %%{http_code}\n" http://localhost/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>nul
if errorlevel 1 echo   Fallito o container non in esecuzione

echo.
echo Test con host.docker.internal:
docker exec nexio-onlyoffice curl -s -o /dev/null -w "  Risultato: HTTP %%{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>nul
if errorlevel 1 echo   Fallito

echo.
echo Test con IP WSL2 (172.23.161.116):
docker exec nexio-onlyoffice curl -s -o /dev/null -w "  Risultato: HTTP %%{http_code}\n" http://172.23.161.116/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>nul
if errorlevel 1 echo   Fallito

echo.
echo [4] Test diretto al file (da Windows):
echo ----------------------------------------
curl -I http://localhost/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx 2>nul | findstr "HTTP"
if errorlevel 1 (
    echo File non accessibile da localhost
)

echo.
echo [5] Verifica servizi in esecuzione:
echo ----------------------------------------
echo.
echo Apache su porta 80:
netstat -an | findstr :80 | findstr LISTENING
echo.
echo OnlyOffice su porta 8082:
netstat -an | findstr :8082 | findstr LISTENING
echo.

echo ===============================================
echo   ISTRUZIONI PER RISOLVERE IL PROBLEMA
echo ===============================================
echo.
echo 1. Se vedi "HTTP 200" per uno degli IP sopra, usa quell'IP
echo    nel file test-onlyoffice-http-working.php
echo.
echo 2. Se host.docker.internal funziona (HTTP 200), modifica:
echo    url: "http://host.docker.internal/piattaforma-collaborativa/..."
echo.
echo 3. Se solo l'IP WSL2 funziona, modifica:
echo    url: "http://172.23.161.116/piattaforma-collaborativa/..."
echo.
echo 4. Se nessuno funziona:
echo    a) Verifica che XAMPP Apache sia in esecuzione
echo    b) Verifica Windows Firewall (permetti porta 80)
echo    c) Riavvia Docker Desktop
echo.
pause