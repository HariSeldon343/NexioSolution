# 🚀 OnlyOffice - Setup SEMPLICE e DEFINITIVO

## ✅ Cosa abbiamo fatto

1. **Disabilitato JWT** temporaneamente per eliminare complessità (`backend/config/onlyoffice.config.php`)
2. **Creato file di test semplici**:
   - `test-onlyoffice-simple.html` - Pagina HTML pura per testare l'editor
   - `test-onlyoffice-status.php` - Verifica stato del server
   - `backend/api/onlyoffice-callback-simple.php` - Callback handler semplificato

3. **File Docker semplificati**:
   - `docker-compose-simple.yml` - Configurazione Docker minima
   - `start-onlyoffice-simple.sh` - Script per avviare OnlyOffice

## 📋 Come avviare OnlyOffice

### Opzione 1: Docker Compose (Consigliato)
```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa
docker-compose -f docker-compose-simple.yml up -d
```

### Opzione 2: Script Bash
```bash
cd /mnt/c/xampp/htdocs/piattaforma-collaborativa
./start-onlyoffice-simple.sh
```

### Opzione 3: Docker Run Diretto
```bash
docker run -d \
  --name onlyoffice-documentserver \
  -p 8082:80 \
  -e JWT_ENABLED=false \
  onlyoffice/documentserver:latest
```

## 🧪 Come testare

### 1. Verifica che il server sia attivo
```bash
# Controlla che il container sia in esecuzione
docker ps | grep onlyoffice

# Test healthcheck
curl http://localhost:8082/healthcheck
# Dovrebbe rispondere: true
```

### 2. Apri la pagina di status
Vai a: http://localhost/piattaforma-collaborativa/test-onlyoffice-status.php

Dovresti vedere:
- ✅ Server OnlyOffice: OK
- ✅ API JavaScript: OK  
- ✅ Healthcheck: Server attivo
- ✅ Docker Container: Container OnlyOffice attivo

### 3. Testa l'editor
1. Apri: http://localhost/piattaforma-collaborativa/test-onlyoffice-simple.html
2. Clicca su "🔍 Test Connessione Diretta" 
3. Se tutto è verde, clicca su "📝 Carica Editor"
4. L'editor dovrebbe caricarsi con un documento di test

## 🔧 Troubleshooting

### Problema: "Server non disponibile"
```bash
# Verifica i log del container
docker logs onlyoffice-documentserver

# Riavvia il container
docker restart onlyoffice-documentserver
```

### Problema: "DocsAPI non definito"
- Il server OnlyOffice non è raggiungibile
- Verifica che la porta 8082 sia libera: `netstat -an | grep 8082`
- Prova a ricaricare la pagina dopo 30 secondi

### Problema: "Editor carica all'infinito"
- Apri la console del browser (F12)
- Cerca errori JavaScript
- Verifica che il documento test esista: `/documents/test/sample.docx`

### Problema: "Callback non funziona"
- Controlla i log: `tail -f logs/onlyoffice-callback.log`
- Verifica che l'URL sia raggiungibile dal container Docker

## 📁 File Importanti

```
/piattaforma-collaborativa/
├── test-onlyoffice-simple.html          # Pagina test HTML
├── test-onlyoffice-status.php           # Verifica stato server
├── docker-compose-simple.yml            # Docker config semplificata
├── start-onlyoffice-simple.sh           # Script avvio rapido
├── backend/
│   ├── config/
│   │   └── onlyoffice.config.php       # Config (JWT disabilitato)
│   └── api/
│       └── onlyoffice-callback-simple.php # Callback handler
├── documents/
│   ├── test/
│   │   └── sample.docx                 # Documento di test
│   └── saved/                          # Documenti salvati
└── logs/
    └── onlyoffice-callback.log         # Log dei callback
```

## ✨ Prossimi Passi (dopo che funziona)

Una volta che l'editor base funziona:

1. **Riabilita JWT** per sicurezza:
   - Modifica `backend/config/onlyoffice.config.php`
   - Imposta `$ONLYOFFICE_JWT_ENABLED = true;`
   - Configura la stessa chiave nel Docker e nel PHP

2. **Integra con il database**:
   - Salva i documenti nel database
   - Gestisci versioni e permessi
   - Integra con il sistema utenti

3. **Migliora l'interfaccia**:
   - Usa il template esistente del sito
   - Aggiungi gestione errori
   - Implementa autosave

## 🎯 Obiettivo Raggiunto

Quando vedi l'editor OnlyOffice caricato e funzionante nella pagina test, hai raggiunto l'obiettivo! 

Da lì puoi gradualmente aggiungere complessità mantenendo sempre una versione funzionante.

---

**IMPORTANTE**: Questa è una configurazione di TEST. Non usare in produzione senza riabilitare JWT e implementare proper security!