# OnlyOffice con Docker Desktop su Windows - Soluzione Definitiva

## Il Problema

OnlyOffice in esecuzione in un container Docker su Windows con Docker Desktop non può utilizzare `localhost` per raggiungere il file server XAMPP perché `localhost` dal container punta al container stesso, non all'host.

### Errore Tipico
```
Error while downloading the document file to be converted (error code -4)
```

Questo errore indica che OnlyOffice non riesce a scaricare il documento dal file server.

## La Soluzione: host.docker.internal

Docker Desktop fornisce un hostname speciale `host.docker.internal` che permette ai container di raggiungere l'host.

### Architettura

```
Browser (Windows)          OnlyOffice Container          XAMPP (Windows Host)
     |                            |                              |
     |-- HTTPS:8443 -->          |                              |
     |                           |                              |
     |                           |-- http://host.docker.internal -->|
     |                           |     /piattaforma-collaborativa/  |
     |                           |       documents/onlyoffice/      |
     |                           |                              |
     |<-- Editor JS/HTML --      |                              |
     |                           |                              |
     |                           |-- Callback URL -->           |
     |                           | (host.docker.internal)       |
```

## Configurazione

### 1. OnlyOffice Docker Container

**docker-compose.yml:**
```yaml
version: '3.8'

services:
  nexio-onlyoffice:
    image: onlyoffice/documentserver:latest
    container_name: nexio-onlyoffice
    ports:
      - "8443:443"  # HTTPS
      - "8082:80"   # HTTP (opzionale)
    environment:
      - JWT_ENABLED=false  # Disabilita JWT per test
    volumes:
      - onlyoffice_data:/var/www/onlyoffice/Data
      - onlyoffice_logs:/var/log/onlyoffice
    restart: unless-stopped
    extra_hosts:
      - "host.docker.internal:host-gateway"  # Importante per Linux

volumes:
  onlyoffice_data:
  onlyoffice_logs:
```

### 2. Configurazione PHP

**backend/config/onlyoffice.config.php:**

La classe `OnlyOfficeConfig` gestisce tutti gli URL distinguendo tra:

- **URL pubblici**: Usati dal browser (localhost)
- **URL interni**: Usati da OnlyOffice container (host.docker.internal)

```php
// URL per OnlyOffice (usa host.docker.internal)
$documentUrl = OnlyOfficeConfig::getDocumentUrlForDS($filename);
// Output: http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/file.docx

// URL per browser (usa localhost)
$browserUrl = OnlyOfficeConfig::getDocumentUrlForBrowser($filename);
// Output: http://localhost/piattaforma-collaborativa/documents/onlyoffice/file.docx

// Callback URL (usa host.docker.internal)
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($docId);
// Output: http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=123
```

## Test e Verifica

### 1. Avvia OnlyOffice
```bash
cd onlyoffice
docker-compose up -d
```

### 2. Verifica che il container sia attivo
```bash
docker ps
# Dovrebbe mostrare nexio-onlyoffice su porta 8443
```

### 3. Test healthcheck
```bash
curl -k https://localhost:8443/healthcheck
# Output: true
```

### 4. Test da dentro il container
```bash
# Verifica che il container possa raggiungere XAMPP
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/

# Verifica accesso ai documenti
docker exec nexio-onlyoffice curl -I http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document.docx
```

### 5. Test completo nel browser
Apri: http://localhost/piattaforma-collaborativa/test-onlyoffice-final-solution.php

## Troubleshooting

### Errore: Connection refused
**Problema**: OnlyOffice non riesce a connettersi a host.docker.internal

**Soluzioni**:
1. Verifica che XAMPP Apache sia in esecuzione
2. Verifica firewall Windows (deve permettere connessioni da Docker)
3. Riavvia Docker Desktop

### Errore: SSL certificate problem
**Problema**: Certificato SSL auto-firmato di OnlyOffice

**Soluzione**: Nel codice PHP, disabilita la verifica SSL per sviluppo:
```php
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
```

### Errore: CORS blocked
**Problema**: Browser blocca richieste cross-origin

**Soluzione**: OnlyOffice API gestisce CORS automaticamente. Assicurati di caricare api.js con l'URL completo HTTPS.

### Errore -4: Document download failed
**Problema**: OnlyOffice non riesce a scaricare il documento

**Verifiche**:
1. URL documento usa `host.docker.internal`, NON `localhost`
2. File esiste nel percorso specificato
3. Permessi lettura sul file
4. XAMPP risponde su porta 80

## URL di Riferimento

| Componente | URL Sviluppo | Usato da |
|------------|--------------|----------|
| OnlyOffice API | https://localhost:8443/web-apps/apps/api/documents/api.js | Browser |
| Document URL | http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/[file] | OnlyOffice |
| Callback URL | http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php | OnlyOffice |
| Browser Test | http://localhost/piattaforma-collaborativa/test-onlyoffice-final-solution.php | Developer |

## Differenze Linux vs Windows

### Docker Desktop (Windows/Mac)
- **DEVE** usare `host.docker.internal`
- Automaticamente risolve all'IP dell'host
- Funziona out-of-the-box

### Docker su Linux nativo
- Opzioni:
  1. Usa `--network host` (sconsigliato)
  2. Usa IP dell'host (es: 172.17.0.1)
  3. Aggiungi `--add-host host.docker.internal:host-gateway`

## Best Practices

1. **Non mischiare URL**: Tieni sempre separati URL per browser e URL per container
2. **Usa la classe OnlyOfficeConfig**: Centralizza la logica degli URL
3. **Test regolari**: Usa `verify-final-solution.bat` per verificare la connettività
4. **Logging**: Abilita logging in OnlyOffice per debug
5. **Sicurezza**: In produzione, usa HTTPS con certificati validi e abilita JWT

## File di Test

- `test-onlyoffice-final-solution.php` - Test completo con debug panel
- `verify-final-solution.bat` - Script verifica connettività
- `backend/config/onlyoffice.config.php` - Configurazione centralizzata

## Comandi Utili

```bash
# Logs OnlyOffice
docker logs -f nexio-onlyoffice

# Shell nel container
docker exec -it nexio-onlyoffice bash

# Test interno
docker exec nexio-onlyoffice curl http://host.docker.internal/piattaforma-collaborativa/

# Riavvia container
docker restart nexio-onlyoffice

# Stop e remove
docker-compose down
docker-compose up -d
```

## Conclusione

La chiave per far funzionare OnlyOffice con Docker Desktop su Windows è:

1. **OnlyOffice su HTTPS** (porta 8443)
2. **Sempre usare host.docker.internal** per URL interni
3. **Mai usare localhost** negli URL per OnlyOffice
4. **Testare la connettività** dal container all'host

Con questa configurazione, OnlyOffice può:
- Scaricare documenti da XAMPP
- Ricevere callback per salvare modifiche
- Funzionare correttamente in ambiente di sviluppo Windows