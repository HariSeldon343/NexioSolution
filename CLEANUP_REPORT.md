# NEXIO - Report Pulizia File Superflui

## File Identificati per Rimozione

### 🔴 FILE DI DEBUG/TEST - RIMOZIONE IMMEDIATA
Questi file sono chiaramente di debug/test e possono essere rimossi senza impatto:

1. `check-cartelle-structure.php` - Script di controllo struttura
2. `clean-test-documents.php` - Script pulizia test
3. `cleanup-cartelle-scripts.php` - Script cleanup
4. `cleanup-final.php` - Script cleanup finale
5. `cleanup-test-files.php` - Pulizia file test
6. `create-test-log.php` - Creazione log test
7. `database-cleanup-standalone.php` - Pulizia database standalone
8. `debug-nexio.php` - File debug generale
9. `debug-session-api.php` - Debug sessioni API
10. `diagnosi-database-standalone.php` - Diagnosi database
11. `esegui-backup-solo.php` - Script backup standalone
12. `fix-cartelle-columns.php` - Fix colonne cartelle
13. `sync-cartelle-data.php` - Sincronizzazione cartelle
14. `test-api-cartelle.php` - Test API cartelle
15. `test-api-final.php` - Test API finale
16. `test-database-connection.php` - Test connessione database
17. `test-files-api.php` - Test API files
18. `test-filesystem.php` - Test filesystem
19. `test-fixes-finali.php` - Test fix finali
20. `test-log-display.php` - Test visualizzazione log
21. `test-rapido-nexio.php` - Test rapido
22. `test_critical_tables.php` - Test tabelle critiche
23. `database_verification_script.php` - Verifica database

### 🟡 FILE DUPLICATI/LEGACY - VERIFICA E RIMOZIONE
File che sembrano duplicati o legacy:

1. `email-status.php` - Potrebbe essere integrato in configurazione-email.php
2. `database-manager.php` - Potrebbe essere consolidato
3. `database-tools.php` - Strumenti database standalone
4. `create-tasks-table.php` - Script creazione tabella (da eseguire una volta)

### 🟠 FILE FUNZIONALI DA VALUTARE
File che hanno funzionalità ma potrebbero essere consolidati:

1. `eventi.php` - Potrebbe essere duplicato di calendario-eventi.php
2. `lista-eventi.php` - Vista lista già in calendario-eventi.php
3. `notifiche-email.php` - Gestione notifiche email
4. `modifica-utente.php` - Modifica singolo utente

### ✅ FILE DA MANTENERE
File che sono parte integrante del sistema:

- `cambia-azienda.php` - Cambio azienda attiva
- `cambio-password.php` - Cambio password utente
- `conformita-normativa.php` - Modulo conformità ISO
- `log-attivita.php` - Visualizzazione log sistema
- `nexio-ai.php` - Modulo AI
- `recupera-password.php` - Reset password
- `referenti.php` - Gestione referenti
- `seleziona-azienda.php` - Selezione azienda iniziale

## Comandi di Rimozione

### FASE 1 - Rimozione File Debug/Test (Sicuro)
```bash
# File di debug e test
rm check-cartelle-structure.php
rm clean-test-documents.php
rm cleanup-cartelle-scripts.php
rm cleanup-final.php
rm cleanup-test-files.php
rm create-test-log.php
rm database-cleanup-standalone.php
rm debug-nexio.php
rm debug-session-api.php
rm diagnosi-database-standalone.php
rm esegui-backup-solo.php
rm fix-cartelle-columns.php
rm sync-cartelle-data.php
rm test-api-cartelle.php
rm test-api-final.php
rm test-database-connection.php
rm test-files-api.php
rm test-filesystem.php
rm test-fixes-finali.php
rm test-log-display.php
rm test-rapido-nexio.php
rm test_critical_tables.php
rm database_verification_script.php
```

### FASE 2 - Rimozione File Legacy (Controllare prima)
```bash
# File legacy/duplicati - CONTROLLARE FUNZIONALITÀ PRIMA
# rm email-status.php
# rm database-manager.php
# rm database-tools.php
# rm create-tasks-table.php
# rm eventi.php
# rm lista-eventi.php
```

### FASE 3 - Rimozione File dalla Git History (Opzionale)
Se si vuole pulire anche la cronologia Git:
```bash
git filter-branch --force --index-filter \
'git rm --cached --ignore-unmatch check-cartelle-structure.php clean-test-documents.php cleanup-cartelle-scripts.php cleanup-final.php cleanup-test-files.php create-test-log.php database-cleanup-standalone.php debug-nexio.php debug-session-api.php diagnosi-database-standalone.php esegui-backup-solo.php fix-cartelle-columns.php sync-cartelle-data.php test-*.php' \
--prune-empty --tag-name-filter cat -- --all
```

## Statistiche

- **File Debug/Test da rimuovere**: 23 file
- **File Legacy da valutare**: 4 file
- **File funzionali da valutare**: 4 file
- **File da mantenere**: 8+ file

## Spazio Recuperato Stimato
Stimati circa 2-3 MB di spazio recuperato e migliore organizzazione del codice.

## Raccomandazioni

1. **Eseguire backup** prima di qualsiasi rimozione
2. **Testare il sistema** dopo la rimozione dei file debug/test
3. **Verificare le funzionalità** dei file legacy prima della rimozione
4. **Aggiornare la documentazione** dopo la pulizia
5. **Consolidare le funzionalità** dei file duplicati quando possibile

---
**Data Report**: $(date)
**Generato da**: Claude Code Assistant