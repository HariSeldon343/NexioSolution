# OnlyOffice HTTPS Configuration - Final Status

## ✅ Configurazione Completata

Data: 2025-08-19  
Versione: Docker Compose con HTTPS

## 🔒 Porte Confermate e Funzionanti

| Servizio | Porta | Protocollo | Stato | Uso |
|----------|-------|------------|-------|-----|
| OnlyOffice Document Server | 8080 | HTTP | ✅ Attivo | Development/Testing |
| OnlyOffice Document Server | 8443 | HTTPS | ✅ Attivo | Production-Ready |
| Nginx File Server | 8081 | HTTP | ✅ Attivo | Serving documenti |

## 📋 File di Configurazione Principali

### 1. Docker Compose
**File:** `/docker/docker-compose.yml`
- ✅ OnlyOffice su porte 8080:80 e 8443:443
- ✅ File server Nginx su porta 8081:80
- ✅ Volumi configurati correttamente
- ✅ Health check attivo

### 2. Configurazione PHP
**File:** `/backend/config/onlyoffice.config.php`
```php
const ONLYOFFICE_DS_PUBLIC_URL = 'https://localhost:8443/';  // HTTPS
const FILESERVER_PUBLIC_URL = 'http://localhost:8081/';      // HTTP
```

### 3. File di Test Aggiornati
Tutti i file di test sono stati aggiornati per usare le porte corrette:
- ✅ `test-onlyoffice-docker.php` - Porta 8081
- ✅ `test-onlyoffice-ports-fixed.php` - Porta 8081
- ✅ `test-onlyoffice-simple.php` - Porta 8081
- ✅ `test-onlyoffice-unified.php` - Porta 8081
- ✅ `test-onlyoffice-working.php` - Porta 8081
- ✅ `test-ports-verification.php` - Porta 8081

## 🔧 Comandi Docker

### Avvio Container
```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa/docker
docker-compose up -d
```

### Verifica Stato
```bash
docker ps --filter "name=nexio-"
docker logs nexio-onlyoffice
docker logs nexio-fileserver
```

### Riavvio Container
```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa/docker
docker-compose restart
```

### Stop Container
```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa/docker
docker-compose down
```

## 🌐 URL di Test

### OnlyOffice Document Server
- HTTP Health Check: http://localhost:8080/healthcheck ✅
- HTTPS Health Check: https://localhost:8443/healthcheck ✅
- HTTP Welcome Page: http://localhost:8080/welcome/
- HTTPS Welcome Page: https://localhost:8443/welcome/
- API JavaScript: https://localhost:8443/web-apps/apps/api/documents/api.js

### File Server
- Root: http://localhost:8081/ ✅
- Documents: http://localhost:8081/documents/
- Test Document: http://localhost:8081/documents/onlyoffice/45.docx

### Test Pages
- Final Check: http://localhost/piattaforma-collaborativa/test-onlyoffice-final-check.php
- HTTPS Test: http://localhost/piattaforma-collaborativa/test-onlyoffice-https.php

## 🚀 Checklist Deployment Cloudflare

### 1. Certificati SSL
- [ ] Rimuovere certificati self-signed
- [ ] Configurare Cloudflare Origin Certificate
- [ ] Abilitare Full (Strict) SSL mode in Cloudflare
- [ ] Configurare Cloudflare Tunnel per accesso sicuro

### 2. Sicurezza JWT
- [ ] Abilitare `JWT_ENABLED = true`
- [ ] Generare `JWT_SECRET` sicuro (32+ caratteri)
- [ ] Salvare secret in variabile d'ambiente
- [ ] Non committare secret nel repository

### 3. Configurazione URL
- [ ] Aggiornare `PRODUCTION_URL` in OnlyOfficeConfig
- [ ] Configurare `PRODUCTION_DS_URL` per dominio pubblico
- [ ] Verificare callback URL per produzione
- [ ] Testare con dominio reale

### 4. Docker Production
- [ ] Usare Docker Swarm o Kubernetes per alta disponibilità
- [ ] Configurare volumi persistenti su storage affidabile
- [ ] Implementare backup automatici
- [ ] Configurare monitoring e alerting

### 5. Network Security
- [ ] Configurare Cloudflare WAF
- [ ] Implementare rate limiting
- [ ] Bloccare accesso diretto alle porte (solo via Cloudflare)
- [ ] Configurare IP whitelist se necessario
- [ ] Abilitare DDoS protection

### 6. Performance
- [ ] Abilitare Cloudflare caching
- [ ] Configurare CDN per assets statici
- [ ] Ottimizzare immagini Docker
- [ ] Implementare auto-scaling

### 7. Monitoring
- [ ] Configurare health checks
- [ ] Implementare logging centralizzato
- [ ] Setup alerting per downtime
- [ ] Monitorare metriche performance

## 📊 Test di Verifica

### Test Connettività Base
```bash
# HTTP Health Check
curl -I http://localhost:8080/healthcheck

# HTTPS Health Check (ignora certificato self-signed)
curl -k -I https://localhost:8443/healthcheck

# File Server
curl -I http://localhost:8081/
```

### Test Documento
```bash
# Verifica accesso documento via file server
curl -I http://localhost:8081/documents/onlyoffice/45.docx
```

### Test API JavaScript
```javascript
// In browser console
fetch('https://localhost:8443/web-apps/apps/api/documents/api.js')
  .then(r => console.log('API Status:', r.status))
  .catch(e => console.error('API Error:', e));
```

## 🔍 Troubleshooting

### Container non si avvia
```bash
# Check logs
docker logs nexio-onlyoffice
docker logs nexio-fileserver

# Verifica risorse
docker system df
docker stats
```

### HTTPS non funziona
1. Verifica che la porta 8443 sia mappata correttamente
2. Controlla i log per errori SSL
3. Assicurati che il browser accetti certificati self-signed
4. Prova con `curl -k` per ignorare verifica certificato

### File non accessibili
1. Verifica permessi della cartella documents
2. Controlla mapping volumi in docker-compose.yml
3. Verifica che nginx.conf sia presente per fileserver

### DocsAPI non disponibile
1. Attendi che OnlyOffice completi l'avvio (può richiedere 1-2 minuti)
2. Verifica health check: `docker ps` dovrebbe mostrare "(healthy)"
3. Controlla console browser per errori CORS o SSL

## ✨ Note Finali

La configurazione HTTPS è ora completamente funzionante con:
- **HTTPS su porta 8443** per OnlyOffice Document Server (production-ready)
- **HTTP su porta 8080** per testing/development
- **HTTP su porta 8081** per file server Nginx

Il sistema è pronto per il deployment su Cloudflare seguendo la checklist sopra indicata. 
Tutti i test passano correttamente e l'integrazione con l'applicazione PHP è completa.

Per assistenza o problemi, consultare:
- Log Docker: `docker logs nexio-onlyoffice`
- Test page: `test-onlyoffice-final-check.php`
- Configurazione: `backend/config/onlyoffice.config.php`