# OnlyOffice Host.Docker.Internal Implementation - DEFINITIVA

## Implementazione Completata

Data: 2025-08-20
Status: ✅ COMPLETATO

## Problema Risolto

Il Document Server OnlyOffice eseguito in un container Docker non poteva raggiungere `localhost` perché all'interno del container, `localhost` punta al container stesso. La soluzione è usare `host.docker.internal` che Docker Desktop mappa automaticamente all'host.

## File Modificati

### 1. `/backend/config/onlyoffice.config.php` ✅
- **Modificato**: Classe `OnlyOfficeConfig` completamente riscritta
- **Funzionalità aggiunte**:
  - `FILESERVER_INTERNAL_BASE` sempre usa `host.docker.internal`
  - `getDocumentUrl()` genera URL con host.docker.internal per OnlyOffice
  - `getCallbackUrl()` genera callback con host.docker.internal
  - `getPublicDocumentUrl()` genera URL pubblici per il browser
  - `generateDocumentKey()` crea chiavi univoche per i documenti
  - `getEditorConfig()` genera configurazione completa con `chat` in `permissions`

### 2. `/onlyoffice-editor.php` ✅
- **Riscritto completamente** per usare la nuova configurazione
- **Caratteristiche**:
  - Usa sempre `host.docker.internal` per URL interni
  - Carica `api.js` con URL pubblico completo
  - Debug dettagliato in console e log
  - Gestione errori migliorata
  - UI di caricamento professionale

### 3. `/backend/api/onlyoffice-document-public.php` ✅
- **Nuovo file creato** per servire documenti pubblicamente
- **Funzionalità**:
  - Headers CORS per accesso dal container
  - Ricerca intelligente dei file in multiple directory
  - Creazione automatica di documenti di test DOCX validi
  - MIME types corretti per file Office
  - Logging dettagliato per debug

### 4. Script di Verifica ✅
- **`verify-host-docker-internal.bat`**: Script Windows per test completi
- **`verify-host-docker-internal.sh`**: Script Linux/WSL equivalente
- **Test eseguiti**:
  1. Risoluzione DNS di host.docker.internal
  2. Accesso HTTP all'applicazione
  3. Test endpoint documenti
  4. Test download completo
  5. Test callback API
  6. Informazioni di rete del container

### 5. `/test-onlyoffice-host-docker-internal.php` ✅
- **Nuovo file di test definitivo** con UI professionale
- **Caratteristiche**:
  - Dashboard di debug completa
  - Test di connettività integrati
  - Visualizzazione configurazione
  - Console di debug dettagliata
  - UI moderna e responsive

## Configurazione Chiave

### URL Schema

```
Browser → localhost:8082 → OnlyOffice Container
                              ↓
                    host.docker.internal
                              ↓
                    XAMPP (localhost:80)
```

### Configurazione Corretta

```php
// SEMPRE per URL interni (OnlyOffice → App)
const FILESERVER_INTERNAL_BASE = 'http://host.docker.internal/piattaforma-collaborativa/';

// Per il browser (dipende dall'ambiente)
public static function getFileServerPublicBase() {
    if (self::isLocal()) {
        return 'http://localhost/piattaforma-collaborativa/';
    } else {
        return 'https://app.nexiosolution.it/piattaforma-collaborativa/';
    }
}
```

## Punti Critici da Ricordare

1. **MAI usare localhost negli URL per OnlyOffice** - sempre `host.docker.internal`
2. **Parametro `chat` va in `permissions`, NON in `customization`** (deprecato)
3. **URL pubblici solo per il browser**, non per OnlyOffice
4. **CORS headers necessari** per permettere accesso dal container
5. **JWT disabilitato per testing**, da abilitare in produzione

## Test di Verifica

### Windows (PowerShell/CMD)
```batch
verify-host-docker-internal.bat
```

### Linux/WSL
```bash
chmod +x verify-host-docker-internal.sh
./verify-host-docker-internal.sh
```

### Browser Test
```
http://localhost/piattaforma-collaborativa/test-onlyoffice-host-docker-internal.php
```

## Troubleshooting

### Problema: "DocsAPI non disponibile"
**Soluzione**: Verifica che il container sia attivo:
```bash
docker ps | grep nexio-documentserver
```

### Problema: "Cannot resolve host.docker.internal"
**Soluzione**: Usa Docker Desktop (non Docker CE) che include il supporto per host.docker.internal

### Problema: "File not found"
**Soluzione**: Il sistema crea automaticamente file di test. Verifica i permessi della directory:
```bash
chmod 777 documents/onlyoffice/
```

### Problema: "Connection refused"
**Soluzione**: Verifica che XAMPP sia in esecuzione:
```bash
/mnt/c/xampp/xampp status
```

## Log Files

- **PHP Error Log**: `logs/error.log`
- **OnlyOffice Log**: `logs/onlyoffice.log`
- **Docker Logs**: `docker logs nexio-documentserver`

## Prossimi Passi

1. **Abilitare JWT in produzione** modificando `JWT_ENABLED = true` in `onlyoffice.config.php`
2. **Configurare HTTPS** per produzione con certificati validi
3. **Implementare persistenza** dei documenti editati
4. **Aggiungere gestione versioni** complete dei documenti
5. **Configurare backup automatici** dei documenti

## Conclusione

L'implementazione è completa e funzionante. OnlyOffice ora comunica correttamente con l'applicazione attraverso `host.docker.internal`, risolvendo definitivamente i problemi di connettività tra container Docker e host.

---

**Implementato da**: Claude Code  
**Data**: 2025-08-20  
**Status**: ✅ FUNZIONANTE