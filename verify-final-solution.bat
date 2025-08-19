@echo off
echo ==============================================
echo Testing OnlyOffice Final Solution
echo ==============================================
echo.

echo Test 1: Document Server su HTTPS porta 8443
echo ----------------------------------------------
curl -k https://localhost:8443/healthcheck
echo.
echo.

echo Test 2: Accesso da container a host.docker.internal
echo ----------------------------------------------
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/
echo.
echo.

echo Test 3: Download documento test via host.docker.internal
echo ----------------------------------------------
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document.docx
echo.
echo.

echo Test 4: Verifica che il container OnlyOffice sia in esecuzione
echo ----------------------------------------------
docker ps --filter "name=nexio-onlyoffice" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo.
echo.

echo Test 5: Logs del container OnlyOffice (ultimi 10 righe)
echo ----------------------------------------------
docker logs --tail 10 nexio-onlyoffice
echo.
echo.

echo ==============================================
echo Se tutti i test sono OK, apri nel browser:
echo http://localhost/piattaforma-collaborativa/test-onlyoffice-final-solution.php
echo ==============================================
pause