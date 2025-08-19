# OnlyOffice su Windows - Guida Installazione e Uso

## Prerequisiti

1. **Docker Desktop per Windows** installato e in esecuzione
2. **XAMPP** installato in `C:\xampp`
3. **Piattaforma Nexio** in `C:\xampp\htdocs\piattaforma-collaborativa`

## Struttura Porte

- **OnlyOffice Document Server**: porta `8082` (HTTP)
- **File Server (Nginx)**: porta `8083` (HTTP)
- **XAMPP Apache**: porta `80` (standard)

## Installazione Rapida

### 1. Da Windows CMD (Prompt dei Comandi)

Aprire CMD come Amministratore e navigare alla directory del progetto:

```cmd
cd C:\xampp\htdocs\piattaforma-collaborativa
```

### 2. Avviare OnlyOffice

Eseguire lo script batch:

```cmd
restart-onlyoffice-http.bat
```

Questo script:
- Ferma eventuali container esistenti
- Avvia OnlyOffice sulla porta 8082
- Avvia il File Server sulla porta 8083
- Verifica che i servizi siano attivi

### 3. Verificare lo Stato

Per controllare lo stato dei container:

```cmd
check-onlyoffice-status.bat
```

## Utilizzo da PowerShell

Se preferisci PowerShell, apri PowerShell come Amministratore:

```powershell
# Naviga alla directory
cd C:\xampp\htdocs\piattaforma-collaborativa

# Avvia OnlyOffice
.\restart-onlyoffice-http.bat

# Controlla stato
.\check-onlyoffice-status.bat
```

## Test dell'Integrazione

1. Assicurati che XAMPP sia in esecuzione (Apache + MySQL)
2. Avvia OnlyOffice con `restart-onlyoffice-http.bat`
3. Apri nel browser: http://localhost/piattaforma-collaborativa/test-onlyoffice-http-working.php
4. Clicca su "Verifica Connessione" per testare tutti gli endpoint
5. Clicca su "Carica Editor" per aprire l'editor di documenti

## Comandi Docker Utili

### Da CMD di Windows:

```cmd
REM Visualizza container attivi
docker ps

REM Visualizza log OnlyOffice
docker logs nexio-onlyoffice

REM Ferma tutti i container Nexio
docker stop nexio-onlyoffice nexio-fileserver

REM Rimuovi container (per pulizia completa)
docker rm nexio-onlyoffice nexio-fileserver

REM Ricostruisci da zero
cd docker
docker-compose build --no-cache
docker-compose up -d
```

## Troubleshooting

### Problema: "docker non è riconosciuto come comando"

**Soluzione**: 
- Assicurati che Docker Desktop sia installato
- Verifica che Docker sia nel PATH di sistema
- Riavvia CMD/PowerShell dopo l'installazione di Docker

### Problema: "Porta già in uso"

**Soluzione**:
```cmd
REM Trova processo che usa la porta 8082
netstat -ano | findstr :8082

REM Termina il processo (sostituisci PID con il numero trovato)
taskkill /PID [numero_pid] /F
```

### Problema: "Container non si avvia"

**Soluzione**:
1. Verifica che Docker Desktop sia in esecuzione
2. Pulisci e riavvia:
```cmd
docker-compose down -v
docker-compose up -d
```

### Problema: "File non trovato nel File Server"

**Soluzione**:
- Verifica che il file esista in `C:\xampp\htdocs\piattaforma-collaborativa\documents\onlyoffice\`
- Controlla i permessi della cartella
- Verifica URL: deve essere `http://localhost:8083/documents/onlyoffice/[nomefile]`

### Problema: "Editor non si carica"

**Soluzione**:
1. Apri Console Browser (F12) e controlla errori
2. Verifica che la porta 8082 sia raggiungibile: http://localhost:8082/healthcheck
3. Controlla CORS nel browser
4. Prova con un browser diverso o in modalità incognito

## File di Configurazione

### docker-compose.yml
Posizione: `C:\xampp\htdocs\piattaforma-collaborativa\docker\docker-compose.yml`

Configurazione principale:
- OnlyOffice su porta 8082
- File Server su porta 8083
- Volumi con percorsi relativi per compatibilità Windows

### test-onlyoffice-http-working.php
File di test principale per verificare l'integrazione.
URL: http://localhost/piattaforma-collaborativa/test-onlyoffice-http-working.php

## Note di Sicurezza

⚠️ **IMPORTANTE**: Questa configurazione è solo per SVILUPPO LOCALE.

In produzione:
- Usare HTTPS con certificati validi
- Abilitare JWT authentication
- Configurare firewall appropriato
- Non esporre porte Docker direttamente

## Supporto

Per problemi specifici:
1. Controlla i log con `docker logs nexio-onlyoffice`
2. Verifica lo stato con `check-onlyoffice-status.bat`
3. Consulta la documentazione ufficiale OnlyOffice

## Script Batch Disponibili

- **restart-onlyoffice-http.bat**: Riavvia OnlyOffice in modalità HTTP
- **check-onlyoffice-status.bat**: Controlla stato dei container e servizi

## Percorsi Importanti

- Progetto: `C:\xampp\htdocs\piattaforma-collaborativa\`
- Docker Config: `C:\xampp\htdocs\piattaforma-collaborativa\docker\`
- Documenti: `C:\xampp\htdocs\piattaforma-collaborativa\documents\onlyoffice\`
- Log: `C:\xampp\htdocs\piattaforma-collaborativa\logs\`

## Test Rapido

1. Avvia XAMPP (Apache + MySQL)
2. Esegui `restart-onlyoffice-http.bat`
3. Attendi 30 secondi
4. Apri: http://localhost/piattaforma-collaborativa/test-onlyoffice-http-working.php
5. Clicca "Carica Editor"

Se tutto funziona, vedrai l'editor OnlyOffice caricarsi nella pagina!