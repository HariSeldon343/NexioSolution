# OnlyOffice Docker Setup - SOLUZIONE DEFINITIVA

## Panoramica
Configurazione Docker definitiva e funzionante per OnlyOffice Document Server integrato con Nexio Platform.

## Architettura
- **OnlyOffice Document Server**: Container principale per editing documenti (porta 8080)
- **Nginx File Server**: Server statico per servire i documenti (porta 8081)
- **Network Bridge**: Rete Docker dedicata per comunicazione tra container
- **Volumi Persistenti**: Storage per documenti, logs e configurazioni

## File di Configurazione

### 1. `docker-compose.yml`
Configurazione principale Docker con:
- Container OnlyOffice con JWT disabilitato (per testing)
- Container Nginx per servire file statici
- Network bridge dedicata
- Volumi per persistenza dati

### 2. `nginx.conf`
Configurazione Nginx per:
- Servire documenti dalla directory locale
- Headers CORS per accesso cross-origin
- Cache control ottimizzato
- MIME types corretti per documenti Office

### 3. `onlyoffice.config.php`
Configurazione PHP con:
- URL dei servizi Docker
- Gestione documenti e permessi
- Configurazione editor
- Helper functions per integrazione

## Scripts di Gestione

### `cleanup-onlyoffice.sh`
Rimuove completamente tutti i container, volumi e reti OnlyOffice.
```bash
bash docker/cleanup-onlyoffice.sh
```

### `setup-onlyoffice.sh`
Installa e configura OnlyOffice da zero.
```bash
bash docker/setup-onlyoffice.sh
```

### `test-onlyoffice.sh`
Esegue test completi di funzionamento.
```bash
bash docker/test-onlyoffice.sh
```

### `start-onlyoffice.sh`
Avvio rapido di OnlyOffice.
```bash
bash docker/start-onlyoffice.sh
```

## Installazione Completa

### 1. Pulizia (se necessario)
Se hai container OnlyOffice esistenti che causano problemi:
```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa
bash docker/cleanup-onlyoffice.sh
```

### 2. Setup Nuovo
Installa OnlyOffice pulito:
```bash
bash docker/setup-onlyoffice.sh
```

### 3. Verifica
Testa che tutto funzioni:
```bash
bash docker/test-onlyoffice.sh
```

## Uso Quotidiano

### Avvio
```bash
cd docker
docker-compose up -d
# oppure
bash docker/start-onlyoffice.sh
```

### Stop
```bash
cd docker
docker-compose down
```

### Restart
```bash
cd docker
docker-compose restart
```

### Visualizza Logs
```bash
docker logs -f nexio-onlyoffice
```

### Stato Container
```bash
docker ps --filter "name=nexio-"
```

## URL di Accesso

- **OnlyOffice Document Server**: http://localhost:8080
- **File Server (Nginx)**: http://localhost:8081
- **Health Check**: http://localhost:8080/healthcheck
- **Test Page**: http://localhost/piattaforma-collaborativa/test-onlyoffice-complete.php

## Integrazione con Nexio

### Creazione Nuovo Documento
```php
require_once 'backend/config/onlyoffice.config.php';

// Crea nuovo documento
$doc = OnlyOfficeConfig::createNewDocument('word', 'Mio Documento');

// Genera configurazione editor
$config = OnlyOfficeConfig::generateEditorConfig(
    ['id' => 1, 'filename' => $doc['filename']],
    $user,
    'edit'
);
```

### Test Connessione
```php
$test = OnlyOfficeConfig::testConnection();
if ($test['success']) {
    echo "OnlyOffice è raggiungibile!";
} else {
    echo "Errore: " . $test['error'];
}
```

## Troubleshooting

### Container non si avvia
1. Verifica che Docker Desktop sia in esecuzione
2. Controlla che le porte 8080 e 8081 siano libere
3. Esegui pulizia completa: `bash docker/cleanup-onlyoffice.sh`
4. Riprova setup: `bash docker/setup-onlyoffice.sh`

### Documenti non si aprono
1. Verifica permessi directory: `chmod 777 documents/onlyoffice`
2. Controlla logs: `docker logs nexio-onlyoffice`
3. Test connettività: `curl http://localhost:8080/healthcheck`

### Errori di rete
1. Verifica network Docker: `docker network ls`
2. Ispeziona network: `docker network inspect nexio-network`
3. Ricrea network se necessario nel docker-compose.yml

### Performance lenta
1. Aumenta risorse in docker-compose.yml (sezione deploy)
2. Verifica uso memoria: `docker stats nexio-onlyoffice`
3. Pulisci cache: `docker system prune -f`

## Sicurezza

### Sviluppo (Attuale)
- JWT disabilitato per semplificare testing
- Accesso aperto su localhost
- CORS permissivo

### Produzione (Da Implementare)
1. Abilita JWT in docker-compose.yml: `JWT_ENABLED=true`
2. Imposta JWT_SECRET sicuro
3. Configura HTTPS con certificati SSL
4. Limita CORS a domini specifici
5. Implementa autenticazione robusta

## Monitoraggio

### Health Check
```bash
curl http://localhost:8080/healthcheck
```

### Statistiche Container
```bash
docker stats nexio-onlyoffice
```

### Spazio Disco
```bash
docker system df
```

### Logs in Tempo Reale
```bash
docker logs -f --tail 100 nexio-onlyoffice
```

## Backup

### Backup Documenti
```bash
tar -czf backup_docs_$(date +%Y%m%d).tar.gz documents/onlyoffice/
```

### Backup Volumi Docker
```bash
docker run --rm -v onlyoffice_postgresql:/data -v $(pwd):/backup alpine tar czf /backup/postgres_backup.tar.gz /data
```

## Note Importanti

1. **JWT è disabilitato** - Abilita in produzione per sicurezza
2. **Porta 8080** - Assicurati che sia libera prima dell'installazione
3. **Permessi** - La directory documents/onlyoffice deve essere scrivibile
4. **Memoria** - OnlyOffice richiede almeno 2GB RAM
5. **Docker Desktop** - Deve essere in esecuzione su Windows

## Supporto

Per problemi o domande:
1. Controlla i logs: `docker logs nexio-onlyoffice`
2. Esegui test completo: `bash docker/test-onlyoffice.sh`
3. Verifica configurazione: `cat docker/docker-compose.yml`
4. Consulta documentazione OnlyOffice: https://api.onlyoffice.com/

## Versione
- OnlyOffice Document Server: Latest
- Nginx: Alpine
- Docker Compose: 3.8
- Data: Gennaio 2025