@echo off
echo ==========================================
echo    OnlyOffice Status Check
echo ==========================================
echo.

echo [1] Verifica Container Docker:
echo --------------------------------
docker ps --filter "name=nexio-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo.

echo [2] Test Healthcheck HTTP (porta 8082):
echo ----------------------------------------
curl -s http://localhost:8082/healthcheck
echo.
echo.

echo [3] Test API JavaScript:
echo ------------------------
curl -s -o nul -w "HTTP Status Code: %%{http_code}\n" http://localhost:8082/web-apps/apps/api/documents/api.js
echo.

echo [4] Container Logs (ultime 10 righe):
echo -------------------------------------
docker logs --tail 10 nexio-documentserver 2>&1
echo.

echo [5] Test file di prova:
echo -----------------------
if exist "documents\onlyoffice\test_document_*.docx" (
    echo File di test trovati in documents\onlyoffice\
    dir /b documents\onlyoffice\test_document_*.docx
) else (
    echo Nessun file di test trovato
)
echo.

echo ==========================================
echo Per testare l'editor, apri nel browser:
echo http://localhost/piattaforma-collaborativa/test-onlyoffice-fixed.php
echo ==========================================
echo.
pause