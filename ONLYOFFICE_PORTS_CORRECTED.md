# ✅ OnlyOffice - Correzione Porte Completata

## 🚨 CORREZIONI URGENTI APPLICATE

### ✅ Porte Corrette (CONFERMATE DALL'UTENTE)
- **OnlyOffice Document Server**: `https://localhost:8443` (HTTPS)
- **File Server Nginx**: `http://localhost:8083` (HTTP)

### 📝 File Corretti

#### 1. Configurazione Backend ✅
- `/backend/config/onlyoffice.config.php`
  - `DOCUMENT_SERVER_URL = 'https://localhost:8443'`
  - `FILE_SERVER_URL = 'http://localhost:8083'`

#### 2. Docker Compose ✅
- `/docker/docker-compose.yml`
  - OnlyOffice: Solo porta `8443:443` (HTTPS)
  - File Server: Porta `8083:80`

#### 3. File di Test Corretti ✅
- `test-onlyoffice-unified.php` - Aggiornato con porte corrette
- `test-onlyoffice-ports-fixed.php` - NUOVO file di test definitivo
- `test-onlyoffice-simple.php` - Corretto
- `test-onlyoffice-definitivo.php` - Corretto

### 🔧 Configurazione Corretta

```php
// backend/config/onlyoffice.config.php
const DOCUMENT_SERVER_URL = 'https://localhost:8443';  // HTTPS!
const FILE_SERVER_URL = 'http://localhost:8083';       // HTTP
```

```yaml
# docker/docker-compose.yml
services:
  onlyoffice:
    ports:
      - "8443:443"  # Solo HTTPS
  
  fileserver:
    ports:
      - "8083:80"   # File server
```

### 📌 Script Tag Corretto per OnlyOffice API

```html
<!-- SEMPRE usare HTTPS su porta 8443 -->
<script src="https://localhost:8443/web-apps/apps/api/documents/api.js"></script>
```

### 🧪 File di Test Principale

Usa `/test-onlyoffice-ports-fixed.php` per testare l'integrazione:
- URL: `http://localhost/piattaforma-collaborativa/test-onlyoffice-ports-fixed.php`

### ⚠️ Note Importanti

1. **HTTPS Obbligatorio**: OnlyOffice DEVE usare HTTPS (porta 8443)
2. **Certificati SSL**: Il browser potrebbe avvisare per certificati auto-firmati
3. **File Server**: Usa HTTP su porta 8083 per servire i documenti
4. **Callback**: Le callback da container a container usano `http://nexio-fileserver:80`

### 🚀 Comandi Docker

```bash
# Riavvia i container con le nuove porte
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa/docker
docker-compose down
docker-compose up -d

# Verifica le porte
docker ps | grep nexio

# Test connessione
curl -k https://localhost:8443/healthcheck
curl http://localhost:8083/
```

### ✅ Verifica Finale

1. Apri: `https://localhost:8443/` - Dovrebbe mostrare la pagina di OnlyOffice
2. Apri: `http://localhost:8083/` - Dovrebbe mostrare il file server Nginx
3. Test editor: `http://localhost/piattaforma-collaborativa/test-onlyoffice-ports-fixed.php`

---
**Data Correzione**: <?php echo date('Y-m-d H:i:s'); ?>
**Versione**: 1.0.0-FINAL