# OnlyOffice - Configurazione Porte Corrette

## ✅ Configurazione Allineata con Docker

### Container e Porte Attive (Verificate con docker ps)

1. **nexio-onlyoffice** (Document Server)
   - Porta HTTP: **8080**
   - Porta HTTPS: **8443**
   - URL Consigliato: `http://localhost:8080` (evita problemi SSL)
   - URL Alternativo: `https://localhost:8443` (richiede accettazione certificato)
   - Servizio: OnlyOffice Document Server
   - Status: ✅ ATTIVO (healthy)

2. **nexio-fileserver** (Nginx File Server)
   - Porta: **8081** (HTTP)
   - URL: `http://localhost:8081`
   - Servizio: Nginx per servire file statici
   - Status: ✅ ATTIVO

### File Aggiornati

#### 1. `/backend/config/onlyoffice.config.php`
```php
const DOCUMENT_SERVER_URL = 'http://localhost:8080';  // HTTP per evitare problemi SSL
const FILE_SERVER_URL = 'http://localhost:8081';      // File server Nginx
const INTERNAL_FILE_SERVER_URL = 'http://nexio-fileserver:80';  // Comunicazione interna
```
- Rimosso parametro deprecato `chat` da customization e permissions
- Aggiunto supporto SSL per connessioni HTTPS

#### 2. `/test-onlyoffice-unified.php`
- Nuovo file di test unificato con configurazione corretta
- Include debug panel con tutte le URL configurate
- Supporta creazione automatica documenti di test
- URL: `http://localhost/piattaforma-collaborativa/test-onlyoffice-unified.php`

#### 3. `/backend/api/onlyoffice-document-public.php`
- API per servire documenti pubblicamente
- Supporta CORS per OnlyOffice
- Gestisce download parziali (Range requests)
- Accessibile via: `http://localhost:8081/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=[DOC_ID]`

### URL di Test

#### Test Pagina Editor
```bash
http://localhost/piattaforma-collaborativa/test-onlyoffice-unified.php
```

#### Test Connessione Document Server (HTTP)
```bash
curl http://localhost:8080/healthcheck
```
Risposta attesa: `{"status":"ok"}`

#### Test Connessione Document Server (HTTPS - con certificato auto-firmato)
```bash
curl -k https://localhost:8443/healthcheck
```
Risposta attesa: `{"status":"ok"}`

#### Test File Server
```bash
curl http://localhost:8081/
```
Risposta attesa: HTML di Nginx o contenuto servito

#### Test Document API
```bash
curl "http://localhost:8081/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=test_document"
```
Risposta attesa: Contenuto del documento o errore 404 se non esiste

### Comandi Docker Utili

#### Verificare Container Attivi
```bash
docker ps | grep nexio
```

#### Verificare Logs OnlyOffice
```bash
docker logs nexio-onlyoffice --tail 50
```

#### Verificare Logs File Server
```bash
docker logs nexio-fileserver --tail 50
```

#### Restart Container
```bash
docker restart nexio-onlyoffice
docker restart nexio-fileserver
```

### Configurazione JavaScript per Editor

```javascript
// URL corretta per caricare API OnlyOffice (usa HTTP per evitare problemi SSL)
<script src="http://localhost:8080/web-apps/apps/api/documents/api.js"></script>

// Configurazione documento
const config = {
    document: {
        url: "http://localhost:8081/piattaforma-collaborativa/documents/onlyoffice/[filename]",
        // ...
    },
    editorConfig: {
        callbackUrl: "http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-callback.php",
        // ...
    }
};
```

### Troubleshooting

#### Errore: ERR_CONNECTION_REFUSED
- **Causa**: Utilizzo porte sbagliate o container non attivi
- **Soluzione**: Verificare con `docker ps` le porte corrette (8080/8081)

#### Errore: NET::ERR_CERT_AUTHORITY_INVALID
- **Causa**: Certificato SSL auto-firmato su porta 8443
- **Soluzione**: Accettare il certificato visitando https://localhost:8443 direttamente

#### Errore: Document not found
- **Causa**: File non esiste nel path specificato
- **Soluzione**: Verificare che il file esista in `/documents/onlyoffice/`

#### Errore: CORS blocked
- **Causa**: Mancanza headers CORS
- **Soluzione**: Verificare che le API includano headers CORS corretti

### Note Importanti

1. **Sviluppo vs Produzione**
   - La configurazione attuale ignora i certificati SSL per localhost
   - In produzione, usare certificati SSL validi
   - In produzione, configurare JWT per sicurezza

2. **Percorsi File**
   - I documenti sono salvati in: `/documents/onlyoffice/`
   - Accessibili via file server: `http://localhost:8081/piattaforma-collaborativa/documents/onlyoffice/`

3. **Callback URL**
   - OnlyOffice deve poter raggiungere il callback URL
   - Usare hostname del container per comunicazione interna: `http://nexio-fileserver/`

### Testing Checklist

- [ ] Container Docker attivi su porte corrette
- [ ] File di configurazione aggiornato
- [ ] Test page carica senza errori
- [ ] Editor OnlyOffice si inizializza
- [ ] Documenti si aprono correttamente
- [ ] Modifiche vengono salvate via callback
- [ ] Download diretto funziona
- [ ] CORS non blocca richieste

### Prossimi Passi

1. **Testare integrazione completa**
   - Aprire `test-onlyoffice-unified.php`
   - Verificare che l'editor si carichi
   - Testare salvataggio modifiche

2. **Aggiornare altri file di test** (se necessario)
   - Rimuovere o aggiornare vecchi file di test
   - Consolidare test in un unico file

3. **Implementare in produzione**
   - Aggiornare `onlyoffice-editor.php` principale
   - Testare con documenti reali
   - Abilitare JWT per sicurezza

---

**Data Aggiornamento**: 2025-08-19
**Versione**: 1.1.0
**Status**: ✅ CONFIGURAZIONE CORRETTA E ALLINEATA CON DOCKER