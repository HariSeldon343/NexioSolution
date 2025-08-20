#!/bin/bash

echo "==============================================="
echo "   OnlyOffice Host.Docker.Internal Verifier"
echo "==============================================="
echo ""

echo "[1] Testing host.docker.internal resolution from OnlyOffice container..."
echo "-----------------------------------------------"
docker exec nexio-documentserver ping -c 1 host.docker.internal
if [ $? -ne 0 ]; then
    echo "ERROR: Cannot resolve host.docker.internal from container!"
    echo "Please ensure Docker Desktop is running with default settings."
    exit 1
fi
echo "SUCCESS: host.docker.internal is reachable"
echo ""

echo "[2] Testing HTTP access to application root..."
echo "-----------------------------------------------"
docker exec nexio-documentserver curl -I -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/
echo ""

echo "[3] Testing document API endpoint..."
echo "-----------------------------------------------"
docker exec nexio-documentserver curl -I -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=22
echo ""

echo "[4] Testing full document download..."
echo "-----------------------------------------------"
docker exec nexio-documentserver curl -s http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=22 -o /tmp/test.docx
docker exec nexio-documentserver ls -la /tmp/test.docx
echo ""

echo "[5] Testing callback API endpoint..."
echo "-----------------------------------------------"
docker exec nexio-documentserver curl -I -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=22
echo ""

echo "[6] Container network information..."
echo "-----------------------------------------------"
docker exec nexio-documentserver ip addr show | grep inet
echo ""

echo "[7] Testing from host system (should work)..."
echo "-----------------------------------------------"
curl -I -s -o /dev/null -w "HTTP Status: %{http_code}\n" http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=22
echo ""

echo "==============================================="
echo "   Verification Complete"
echo "==============================================="
echo ""
echo "IMPORTANT NOTES:"
echo "- OnlyOffice MUST use host.docker.internal (not localhost)"
echo "- Browser uses localhost (or domain in production)"
echo "- Check logs/onlyoffice.log for detailed debug info"
echo ""