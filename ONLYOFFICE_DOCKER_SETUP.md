# 🐳 OnlyOffice Docker Setup Guide

## ✅ Configurazione Completata

La piattaforma Nexio è stata configurata correttamente per OnlyOffice con i seguenti parametri:

| Parametro | Valore Configurato |
|-----------|-------------------|
| **Server URL** | `http://localhost:8082` |
| **JWT Enabled** | `true` ✅ |
| **JWT Secret** | `nexio-secret-key-2025-onlyoffice-jwt-secure-token` |
| **JWT Header** | `Authorization` |
| **Callback URL** | Auto-configurato |

## 🚀 Quick Start - Avvia OnlyOffice

### 1. Comando Docker Completo

```bash
docker run -d -p 8082:80 --name onlyoffice-ds \
  -e JWT_ENABLED=true \
  -e JWT_SECRET=nexio-secret-key-2025-onlyoffice-jwt-secure-token \
  -e JWT_HEADER=Authorization \
  onlyoffice/documentserver
```

**IMPORTANTE**: Il `JWT_SECRET` nel comando Docker DEVE corrispondere esattamente a quello configurato in `backend/config/onlyoffice.config.php`

### 2. Verifica che il Container sia Attivo

```bash
# Controlla lo stato
docker ps | grep onlyoffice-ds

# Visualizza i log
docker logs onlyoffice-ds

# Test di connessione
curl http://localhost:8082
```

### 3. Test della Configurazione

Apri nel browser:
- **Test Rapido**: http://localhost/piattaforma-collaborativa/test-onlyoffice-quick.php
- **Test Completo**: http://localhost/piattaforma-collaborativa/test-onlyoffice-jwt.php
- **Setup Helper**: http://localhost/piattaforma-collaborativa/setup-onlyoffice-docker.php

## 📝 File di Configurazione

### `backend/config/onlyoffice.config.php`

```php
// Configurazione attuale (già impostata)
$ONLYOFFICE_DS_PUBLIC_URL = 'http://localhost:8082';
$ONLYOFFICE_JWT_ENABLED = true;
$ONLYOFFICE_JWT_SECRET = 'nexio-secret-key-2025-onlyoffice-jwt-secure-token';
$ONLYOFFICE_JWT_HEADER = 'Authorization';
```

### `.env` (Opzionale)

Se preferisci usare variabili d'ambiente, crea un file `.env`:

```env
ONLYOFFICE_DS_PUBLIC_URL=http://localhost:8082
ONLYOFFICE_JWT_ENABLED=true
ONLYOFFICE_JWT_SECRET=nexio-secret-key-2025-onlyoffice-jwt-secure-token
ONLYOFFICE_JWT_HEADER=Authorization
```

## 🧪 Test dell'Editor

Una volta avviato il container Docker, puoi testare l'editor:

1. **Crea un nuovo documento**:
   ```
   http://localhost/piattaforma-collaborativa/onlyoffice-editor.php
   ```

2. **Modifica un documento esistente** (sostituisci ID):
   ```
   http://localhost/piattaforma-collaborativa/onlyoffice-editor.php?id=1
   ```

## 🔧 Comandi Docker Utili

```bash
# Ferma il container
docker stop onlyoffice-ds

# Avvia il container
docker start onlyoffice-ds

# Riavvia il container
docker restart onlyoffice-ds

# Rimuovi il container
docker rm -f onlyoffice-ds

# Visualizza i log in tempo reale
docker logs -f onlyoffice-ds

# Entra nel container
docker exec -it onlyoffice-ds bash
```

## ⚠️ Troubleshooting

### Problema: "OnlyOffice non raggiungibile"

**Soluzione**:
1. Verifica che Docker sia in esecuzione
2. Controlla che la porta 8082 sia libera: `netstat -an | grep 8082`
3. Avvia il container con il comando sopra

### Problema: "JWT Token Invalid"

**Soluzione**:
1. Assicurati che il `JWT_SECRET` sia identico in:
   - Comando Docker (`-e JWT_SECRET=...`)
   - File config (`$ONLYOFFICE_JWT_SECRET = ...`)
2. Riavvia il container dopo modifiche

### Problema: "Document won't load"

**Soluzione**:
1. Controlla i log: `docker logs onlyoffice-ds`
2. Verifica che il documento esista nel database
3. Controlla permessi utente

## 🔒 Sicurezza per la Produzione

Per l'ambiente di produzione:

1. **Genera un nuovo JWT Secret sicuro**:
   ```bash
   openssl rand -hex 32
   ```

2. **Usa HTTPS**:
   - Configura SSL/TLS sul server
   - Aggiorna tutti gli URL a `https://`

3. **Configura Firewall**:
   - Limita accesso alla porta 8082
   - Usa reverse proxy (nginx/Apache)

4. **Backup**:
   - Backup regolari del database
   - Backup dei documenti in `/documents/`

## ✅ Checklist Finale

- [x] OnlyOffice configurato con `http://localhost:8082`
- [x] JWT abilitato (`true`)
- [x] JWT Secret configurato (stesso valore in Docker e config)
- [x] JWT Header impostato su `Authorization`
- [x] File di test creati
- [x] Documentazione completa

## 📞 Supporto

Per problemi o domande:
1. Esegui: `php setup-onlyoffice-docker.php` per diagnostica
2. Controlla i log: `docker logs onlyoffice-ds`
3. Verifica la configurazione: `test-onlyoffice-jwt.php`

---

**Status**: ✅ PRONTO - Esegui il comando Docker per avviare OnlyOffice!