@echo off
echo ======================================
echo Riavvio OnlyOffice in modalita HTTP
echo ======================================

cd /d C:\xampp\htdocs\piattaforma-collaborativa\docker

echo.
echo Fermando container esistenti...
docker-compose down

echo.
echo Avviando container...
docker-compose up -d

echo.
echo Attendere 30 secondi per l'avvio...
timeout /t 30

echo.
echo Test connessione OnlyOffice...
curl http://localhost:8082/healthcheck

echo.
echo Test file server...
curl http://localhost:8083/

echo.
echo ======================================
echo Container attivi:
docker ps --filter "name=nexio-"

echo.
echo ======================================
echo OnlyOffice disponibile su: http://localhost:8082
echo File Server disponibile su: http://localhost:8083
echo.
echo Apri nel browser: http://localhost/piattaforma-collaborativa/test-onlyoffice-http-working.php
echo ======================================
pause