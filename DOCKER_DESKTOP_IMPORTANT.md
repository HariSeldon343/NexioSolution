# DOCKER DESKTOP - CONFIGURAZIONE CRITICA

## ⚠️ PROBLEMA CRITICO RISOLTO

Su **Docker Desktop per Windows**, il container OnlyOffice **NON PUÒ** usare `localhost` o `127.0.0.1` per comunicare con l'host. Dal punto di vista del container, `localhost` punta al container stesso!

## ✅ SOLUZIONE OBBLIGATORIA

**SEMPRE** usare `host.docker.internal` per le comunicazioni container → host.

## 📋 Configurazione Corretta

### URLs dal Browser (utente):
```javascript
// Questi URL sono usati dal browser dell'utente
const documentServerUrl = "http://localhost:8082";  // OnlyOffice Document Server
const apiScriptUrl = "http://localhost:8082/web-apps/apps/api/documents/api.js";
```

### URLs per OnlyOffice Container:
```javascript
// CRITICO: Questi URL sono usati dal container OnlyOffice
const documentUrl = "http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/document.docx";
const callbackUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php";
```

## ❌ ERRORI COMUNI DA EVITARE

### ERRATO:
```javascript
// NON funzionerà dal container!
url: "http://localhost/piattaforma-collaborativa/documents/..."
url: "http://127.0.0.1/piattaforma-collaborativa/documents/..."
url: "http://172.23.161.116/piattaforma-collaborativa/documents/..." // IP locale WSL2
```

### CORRETTO:
```javascript
// SEMPRE così per Docker Desktop:
url: "http://host.docker.internal/piattaforma-collaborativa/documents/..."
```

## 🔍 Come Verificare

### 1. Test Manuale dal Container:
```bash
# Verifica che host.docker.internal sia risolvibile
docker exec nexio-onlyoffice ping host.docker.internal

# Test accesso documento
docker exec nexio-onlyoffice curl http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test.docx

# Test callback
docker exec nexio-onlyoffice curl http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php
```

### 2. Script Automatico:
```bash
# Windows
verify-docker-desktop.bat

# Linux/Mac con Docker Desktop
./verify-docker-desktop.sh
```

## 📂 File Aggiornati

Tutti i seguenti file sono stati aggiornati per usare `host.docker.internal`:

1. **test-onlyoffice-http-working.php**
   - Document URL: usa host.docker.internal
   - Callback URL: usa host.docker.internal

2. **test-onlyoffice-docker-desktop.php**
   - File specifico per test Docker Desktop
   - Mostra chiaramente la differenza browser vs container

3. **backend/config/onlyoffice.config.php**
   - Aggiunto `DOCUMENT_HOST_INTERNAL` constant
   - Metodo `isDockerDesktop()` per rilevamento automatico
   - Metodo `getDocumentUrlForContainer()` per URL corretti

## 🎯 Sintomi del Problema (Prima della Fix)

Se vedi questi errori, significa che non stai usando `host.docker.internal`:

- **Error -4**: "Cannot download document" 
  - OnlyOffice non può scaricare il documento dall'URL fornito
  
- **Connection refused**
  - Il container non può connettersi a localhost
  
- **Network unreachable**
  - Il container non può raggiungere l'IP locale

## 💡 Perché host.docker.internal?

`host.docker.internal` è un hostname speciale fornito da Docker Desktop che:

1. Risolve sempre all'IP dell'host dal punto di vista del container
2. Funziona su Windows, Mac e Linux con Docker Desktop
3. È la soluzione ufficiale di Docker per questo problema
4. Non richiede configurazioni aggiuntive

## 📚 Riferimenti

- [Docker Desktop Networking](https://docs.docker.com/desktop/networking/#i-want-to-connect-from-a-container-to-a-service-on-the-host)
- [OnlyOffice Docker Documentation](https://helpcenter.onlyoffice.com/installation/docs-developer-install-docker.aspx)

## ⚡ Quick Test

Apri nel browser:
```
http://localhost/piattaforma-collaborativa/test-onlyoffice-docker-desktop.php
```

Questo file di test:
1. Mostra chiaramente gli URL corretti
2. Testa la connettività
3. Carica l'editor con la configurazione corretta per Docker Desktop

---

**RICORDA**: Su Docker Desktop, `host.docker.internal` non è opzionale, è **OBBLIGATORIO**!