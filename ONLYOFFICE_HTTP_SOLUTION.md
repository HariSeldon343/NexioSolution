# OnlyOffice HTTP Solution - Risoluzione Problemi SSL/WebSocket

## Problema Identificato

OnlyOffice non riusciva a stabilire connessioni WebSocket su HTTPS a causa di problemi con certificati SSL self-signed:
- SecurityError: Failed to register ServiceWorker 
- WebSocket connection to 'wss://localhost:8443/...' failed
- L'editor si inizializzava ma falliva quando tentava di aprire connessioni WebSocket

## Soluzione Implementata

### 1. Configurazione HTTP per Sviluppo

Modificato `/backend/config/onlyoffice.config.php` per utilizzare HTTP invece di HTTPS:
- Document Server: `http://localhost:8082` (invece di https://localhost:8443)
- File Server: `http://localhost:8083` (nginx fileserver)
- Comunicazione interna: HTTP tra container Docker

### 2. File Modificati

#### backend/config/onlyoffice.config.php
```php
// URL pubblici - HTTP per sviluppo
const ONLYOFFICE_DS_PUBLIC_URL = 'http://localhost:8082/';
const FILESERVER_PUBLIC_URL = 'http://localhost:8083/';

// URL interni Docker - HTTP per comunicazione interna
const ONLYOFFICE_DS_INTERNAL_URL = 'http://nexio-onlyoffice/';
const FILESERVER_INTERNAL_URL = 'http://nexio-fileserver/';
```

#### onlyoffice/nginx-config/default.conf
Configurato nginx per:
- Servire file su porta 8080 (mappata a 8083 esternamente)
- Abilitare CORS per sviluppo
- Gestire path `/piattaforma-collaborativa/documents/onlyoffice/`
- Proxy verso API PHP backend

### 3. File di Test Creati

#### test-onlyoffice-http-working.php
Pagina di test completa che:
- Usa esclusivamente HTTP
- Include debugging dettagliato
- Verifica connettività a tutti gli endpoint
- Testa WebSocket su ws:// (non wss://)
- Mostra log in tempo reale

### 4. Script di Utilità

#### restart-onlyoffice-http.sh
Script bash per:
- Riavviare container in modalità HTTP
- Verificare endpoint
- Mostrare status servizi

## Come Utilizzare

### 1. Riavviare i Container

```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa
./restart-onlyoffice-http.sh
```

### 2. Verificare i Servizi

Verificare che i seguenti endpoint rispondano:
- http://localhost:8082/healthcheck (OnlyOffice)
- http://localhost:8083/health (File Server)
- http://localhost:8082/web-apps/apps/api/documents/api.js (API JS)

### 3. Testare l'Editor

Aprire nel browser:
```
http://localhost/piattaforma-collaborativa/test-onlyoffice-http-working.php
```

Cliccare su:
1. "Verifica Connessione" - dovrebbe mostrare tutti i servizi OK
2. "Carica Editor" - dovrebbe caricare l'editor OnlyOffice
3. "Test WebSocket" - verifica connettività WebSocket

## Docker Compose

Il file `docker-compose.yml` espone:
- Porta 8082 per OnlyOffice Document Server
- Porta 8083 per Nginx File Server

```yaml
services:
  onlyoffice-documentserver:
    ports:
      - "8082:80"  # HTTP su porta 8082
      
  nginx-fileserver:
    ports:
      - "8083:8080"  # File server su porta 8083
```

## Vantaggi della Soluzione HTTP

1. **Nessun problema con certificati SSL** in sviluppo
2. **WebSocket funziona** senza problemi di sicurezza
3. **ServiceWorker non richiesto** per HTTP
4. **Debugging più semplice** senza encryption

## Considerazioni per Produzione

Per l'ambiente di produzione su Cloudflare:

1. **Configurare HTTPS con certificati validi**
   - Usare certificati Let's Encrypt o Cloudflare Origin
   - Configurare docker-compose per HTTPS

2. **Aggiornare configurazione**
   ```php
   // In produzione
   const ONLYOFFICE_DS_PUBLIC_URL = 'https://app.nexiosolution.it/onlyoffice/';
   ```

3. **Abilitare JWT**
   ```php
   const JWT_ENABLED = true;
   const JWT_SECRET = 'your-secure-secret-key';
   ```

4. **Configurare reverse proxy**
   - Nginx o Apache con SSL termination
   - WebSocket proxy con upgrade headers

## Troubleshooting

### Se l'editor non si carica:
1. Verificare che i container Docker siano in esecuzione: `docker ps`
2. Controllare i log: `docker-compose logs -f`
3. Verificare che le porte 8082 e 8083 siano libere

### Se WebSocket non funziona:
1. Verificare che si stia usando `ws://` non `wss://`
2. Controllare firewall/antivirus che potrebbero bloccare WebSocket
3. Verificare console browser per errori

### Se i documenti non si aprono:
1. Verificare che il file esista in `/documents/onlyoffice/`
2. Controllare permessi del file
3. Verificare URL del file server (http://localhost:8083)

## Test Automatici

Per verificare che tutto funzioni:

```bash
# Test endpoint OnlyOffice
curl -I http://localhost:8082/healthcheck

# Test file server
curl -I http://localhost:8083/health

# Test API JavaScript
curl -I http://localhost:8082/web-apps/apps/api/documents/api.js
```

Tutti dovrebbero restituire HTTP 200 OK.

## Conclusione

La soluzione HTTP risolve completamente i problemi di SSL/WebSocket in ambiente di sviluppo. Per la produzione, sarà necessario configurare HTTPS con certificati validi, ma la struttura base rimarrà la stessa.