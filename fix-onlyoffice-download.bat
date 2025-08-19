@echo off
echo ======================================
echo FIX OnlyOffice Download Error -4
echo ======================================
echo.

echo Step 1: Check Docker network configuration
echo --------------------------------------
docker network inspect bridge | findstr "Gateway"
echo.

echo Step 2: Test connectivity from container
echo --------------------------------------
docker exec nexio-onlyoffice ping -c 2 host.docker.internal
if %ERRORLEVEL% NEQ 0 (
    echo host.docker.internal NOT WORKING - trying gateway IP
    docker exec nexio-onlyoffice ping -c 2 172.17.0.1
)
echo.

echo Step 3: Test document access via document-serve.php
echo --------------------------------------
echo Testing with host.docker.internal...
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/backend/api/document-serve.php
echo.

echo Testing with gateway IP 172.17.0.1...
docker exec nexio-onlyoffice curl -I http://172.17.0.1/piattaforma-collaborativa/backend/api/document-serve.php
echo.

echo Step 4: Update docker-compose with volume mount (if needed)
echo --------------------------------------
echo If above tests fail, add this to docker-compose.yml under onlyoffice service:
echo     volumes:
echo       - C:/xampp/htdocs/piattaforma-collaborativa/documents:/var/www/documents:ro
echo.

echo Step 5: Test the PHP diagnostic page
echo --------------------------------------
echo Please open in browser:
echo http://localhost/piattaforma-collaborativa/test-all-onlyoffice-methods.php
echo.
echo This will test ALL methods and show which one works!
echo.

pause