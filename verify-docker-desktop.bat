@echo off
echo ========================================
echo   Docker Desktop Connectivity Test
echo   Testing host.docker.internal
echo ========================================
echo.

echo [1] Testing host.docker.internal resolution from OnlyOffice container...
docker exec nexio-onlyoffice ping -c 1 host.docker.internal
if %errorlevel% equ 0 (
    echo [OK] host.docker.internal is reachable
) else (
    echo [ERROR] host.docker.internal NOT reachable - Check Docker Desktop settings
)
echo.

echo [2] Testing document access from OnlyOffice container...
docker exec nexio-onlyoffice curl -I -s -o /dev/null -w "HTTP Status: %%{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx
echo.

echo [3] Testing callback URL from OnlyOffice container...
docker exec nexio-onlyoffice curl -I -s -o /dev/null -w "HTTP Status: %%{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php
echo.

echo [4] Testing OnlyOffice health check...
curl -I -s -o nul -w "HTTP Status: %%{http_code}\n" http://localhost:8082/healthcheck
echo.

echo [5] Checking container status...
docker ps --filter "name=nexio-onlyoffice" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo.

echo ========================================
echo   IMPORTANT NOTES:
echo   - OnlyOffice MUST use host.docker.internal
echo   - NOT localhost or 127.0.0.1
echo   - This is REQUIRED for Docker Desktop
echo ========================================
pause