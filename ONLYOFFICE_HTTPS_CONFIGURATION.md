# OnlyOffice HTTPS Configuration - Porta 8443

## 🔧 Modifiche Implementate

### 1. Configurazione OnlyOffice (`/backend/config/onlyoffice.config.php`)

#### Nuove Costanti URL
```php
// URL pubblici (accessibili dal browser)
const ONLYOFFICE_DS_PUBLIC_URL = 'https://localhost:8443/';  // HTTPS su porta 8443
const FILESERVER_PUBLIC_URL = 'http://localhost:8081/';      // HTTP su porta 8081

// URL interni Docker (comunicazione container-to-container)
const ONLYOFFICE_DS_INTERNAL_URL = 'https://nexio-onlyoffice/';
const FILESERVER_INTERNAL_URL = 'http://nexio-fileserver/';

// Produzione con Cloudflare
const PRODUCTION_URL = 'https://app.nexiosolution.it/piattaforma-collaborativa';
const PRODUCTION_DS_URL = 'https://app.nexiosolution.it/onlyoffice/';
```

#### Nuovi Metodi Helper
- `isProduction()` - Detecta ambiente di produzione
- `getDocumentServerInternalUrl()` - URL interno per container
- `getDocumentUrlForDS($filename)` - URL documento per OnlyOffice (usa hostname interno)
- `getDocumentUrlForBrowser($filename)` - URL documento per browser (debug)
- `getCallbackUrl($documentId)` - URL callback con supporto Docker

### 2. Docker Compose (`/docker/docker-compose.yml`)

#### Modifiche Porte
- **OnlyOffice**: Aggiunta porta `8443:443` per HTTPS
- **File Server**: Cambiata porta da `8083` a `8081`

```yaml
onlyoffice:
  ports:
    - "8080:80"   # HTTP
    - "8443:443"  # HTTPS (nuovo)

fileserver:
  ports:
    - "8081:80"   # Cambiato da 8083
```

### 3. Nginx Configuration (`/docker/nginx.conf`)

#### Proxy per API PHP
Aggiunto supporto per proxy delle richieste PHP attraverso il fileserver:
```nginx
location /piattaforma-collaborativa/ {
    proxy_pass http://host.docker.internal/piattaforma-collaborativa/;
    # Headers CORS completi
}
```

### 4. CORS Headers (`/backend/api/onlyoffice-document-public.php`)

Headers CORS aggiornati per supporto completo:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS, POST');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Authorization, Range');
header('Access-Control-Expose-Headers: Content-Length, Content-Range');
header('Access-Control-Max-Age: 3600');
```

### 5. File di Test (`/test-onlyoffice-https.php`)

Nuovo file di test specifico per HTTPS che:
- Mostra lo stato della connessione HTTPS
- Verifica i certificati self-signed
- Testa configurazione CORS
- Usa i nuovi metodi helper per URL corretti
- Include istruzioni per accettare certificati

### 6. Configurazione Produzione (`/.env.production`)

File di configurazione per deployment su Cloudflare con:
- URL di produzione
- Configurazione JWT abilitata
- Settings di sicurezza (HTTPS cookies, CSRF)
- Configurazione Cloudflare tunnel

### 7. Script di Deployment (`/docker/restart-onlyoffice-https.sh`)

Script bash per:
- Riavviare container con nuova configurazione
- Verificare connessione HTTPS
- Mostrare istruzioni per certificati self-signed

## 📋 Problemi Risolti

1. ✅ **Porta 8083 → 8081**: File server ora usa la porta corretta
2. ✅ **API con path relativo**: Ora usa URL assoluto con HTTPS
3. ✅ **CORS errors**: Headers CORS completi aggiunti
4. ✅ **URL interni vs pubblici**: Metodi helper distinguono tra accessi
5. ✅ **Preparazione Cloudflare**: Configurazione pronta per produzione

## 🚀 Come Usare

### Sviluppo Locale

1. **Riavvia i container Docker**:
```bash
cd docker
./restart-onlyoffice-https.sh
```

2. **Accetta il certificato self-signed**:
   - Apri: https://localhost:8443/healthcheck
   - Accetta il certificato nel browser

3. **Testa la configurazione**:
   - Visita: http://localhost/piattaforma-collaborativa/test-onlyoffice-https.php
   - Verifica che tutti gli indicatori siano verdi

### Produzione con Cloudflare

1. **Configura Cloudflare Tunnel**:
```bash
cloudflared tunnel create nexio-app
cloudflared tunnel route dns nexio-app app.nexiosolution.it
```

2. **Configura il tunnel per le porte**:
```yaml
# config.yml per Cloudflare
tunnel: nexio-app
credentials-file: /path/to/credentials.json

ingress:
  - hostname: app.nexiosolution.it
    path: /onlyoffice
    service: https://localhost:8443
  - hostname: app.nexiosolution.it
    service: http://localhost:80
```

3. **Usa file .env.production**:
```bash
cp .env.production .env
# Modifica le credenziali di produzione
```

## 🔍 Debug e Troubleshooting

### Verifica Connessione HTTPS
```bash
# Test diretto
curl -k https://localhost:8443/healthcheck

# Verifica certificato
openssl s_client -connect localhost:8443 -servername localhost
```

### Verifica CORS
```bash
# Test preflight OPTIONS
curl -X OPTIONS http://localhost:8081/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php \
  -H "Origin: https://localhost:8443" \
  -H "Access-Control-Request-Method: GET" \
  -v
```

### Logs Docker
```bash
# OnlyOffice logs
docker logs -f nexio-onlyoffice

# File server logs
docker logs -f nexio-fileserver
```

## ⚠️ Note Importanti

1. **Certificati Self-Signed**: In sviluppo, sempre accettare i certificati nel browser prima di testare
2. **Hostname Docker**: I container usano `host.docker.internal` su Windows/Mac per raggiungere l'host
3. **Network Docker**: Tutti i container devono essere sulla stessa rete (`nexio-network`)
4. **Firewall**: Assicurarsi che le porte 8080, 8081, 8443 siano aperte

## 📊 Architettura

```
Browser → https://localhost:8443 → OnlyOffice Container
                    ↓
        Richiesta documento tramite URL interno
                    ↓
    http://nexio-fileserver/piattaforma-collaborativa/api/...
                    ↓
        Proxy verso host.docker.internal
                    ↓
            PHP/XAMPP su localhost
```

## 🔐 Sicurezza

- **Sviluppo**: JWT disabilitato per testing
- **Produzione**: JWT abilitato con secret sicuro
- **CORS**: Configurato per permettere accesso da OnlyOffice
- **HTTPS**: Obbligatorio in produzione con certificati validi
- **CSP**: Headers di sicurezza configurati in nginx

## 📝 Checklist Pre-Produzione

- [ ] Cambiare JWT_SECRET in produzione
- [ ] Configurare certificati SSL validi
- [ ] Limitare CORS origins a domini specifici
- [ ] Configurare backup automatici
- [ ] Impostare monitoring e alerts
- [ ] Testare performance con load testing
- [ ] Verificare limiti di upload/download
- [ ] Configurare rate limiting

## 🆘 Supporto

Per problemi o domande:
1. Controlla i logs Docker
2. Verifica la configurazione in `onlyoffice.config.php`
3. Testa con `test-onlyoffice-https.php`
4. Verifica connettività tra container