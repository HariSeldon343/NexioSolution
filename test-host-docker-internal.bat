@echo off
echo ======================================
echo Test host.docker.internal connectivity
echo ======================================
echo.

echo Test 1: DNS Resolution from container
echo --------------------------------------
docker exec nexio-onlyoffice nslookup host.docker.internal
echo.

echo Test 2: Ping host.docker.internal
echo --------------------------------------
docker exec nexio-onlyoffice ping -c 2 host.docker.internal
echo.

echo Test 3: HTTP Access to XAMPP root
echo --------------------------------------
docker exec nexio-onlyoffice curl -v http://host.docker.internal/
echo.

echo Test 4: Access to specific document
echo --------------------------------------
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx
echo.

echo Test 5: Full document download
echo --------------------------------------
docker exec nexio-onlyoffice curl -o /tmp/test.docx http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx
docker exec nexio-onlyoffice ls -la /tmp/test.docx
echo.

echo Test 6: Get Docker gateway IP
echo --------------------------------------
docker network inspect bridge | findstr "Gateway"
echo.

echo Test 7: Test with direct IP (if gateway is 172.17.0.1)
echo --------------------------------------
docker exec nexio-onlyoffice curl -I http://172.17.0.1/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx
echo.

pause