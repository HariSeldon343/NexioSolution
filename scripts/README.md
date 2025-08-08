# Scripts per Sistema Documentale Nexio

Questa directory contiene script di utilità per setup, test e monitoraggio del sistema.

## Script Disponibili

### 1. Setup Completo
```bash
php setup-nexio-documentale.php
```
Esegue il setup completo del sistema:
- Verifica requisiti
- Esegue tutti gli script SQL
- Crea directory necessarie
- Configura permessi
- Inserisce dati iniziali
- Crea strutture di test

### 2. Quick Setup (Bash)
```bash
./quick-setup.sh
```
Setup rapido interattivo:
- Verifica ambiente
- Crea directory
- Opzionale: esegue setup database
- Opzionale: esegue test sistema

### 3. Test Sistema
```bash
php test-nexio-documentale.php
```
Esegue test completi:
- Struttura database
- Sistema permessi
- Filesystem
- API documenti
- Upload/download
- Ricerca
- Strutture ISO
- Performance

### 4. Monitor Performance
```bash
# Monitoraggio singolo
php monitor-nexio-performance.php

# Monitoraggio continuo
php monitor-nexio-performance.php --continuous

# Genera report HTML
php monitor-nexio-performance.php --html
```
Monitora:
- Stato sistema
- Performance database
- Metriche applicative
- Utilizzo risorse
- Query lente
- Dimensioni tabelle

## Utilizzo Tipico

### Prima Installazione
```bash
# 1. Setup rapido
./quick-setup.sh

# 2. Oppure setup completo diretto
php setup-nexio-documentale.php

# 3. Verifica con test
php test-nexio-documentale.php
```

### Monitoraggio Produzione
```bash
# Aggiungi a cron per report giornalieri
0 8 * * * php /path/to/monitor-nexio-performance.php --html

# Monitor real-time
screen -S nexio-monitor
php monitor-nexio-performance.php --continuous
```

### Troubleshooting
```bash
# Test rapido dopo modifiche
php test-nexio-documentale.php

# Verifica performance
php monitor-nexio-performance.php
```

## Output e Log

- **Setup**: Genera report in `logs/setup-report-*.json`
- **Test**: Genera report in `logs/test-report-*.json`
- **Monitor**: Genera report in `logs/performance-*.json` e `logs/performance-report-*.html`

## Requisiti

- PHP 7.4+
- MySQL 5.7+
- Permessi di scrittura su directory logs, uploads, temp, cache
- Esecuzione da linea di comando (CLI)

## Note Sicurezza

- NON eseguire come root
- Proteggi questa directory dall'accesso web
- Mantieni backup prima di eseguire setup
- Verifica sempre i log dopo l'esecuzione