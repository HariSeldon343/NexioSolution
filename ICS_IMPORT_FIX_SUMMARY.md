# Fix Importazione ICS - Riepilogo

## Data: 2025-08-13

## Problema Riscontrato
L'API `backend/api/import-ics.php` restituiva "Token CSRF mancante" quando si tentava di importare un file ICS.

## Analisi del Problema
1. **Validazione CSRF troppo restrittiva**: Il metodo `verifyRequest()` di CSRFTokenManager cercava il token solo in `$_POST['csrf_token']`
2. **Upload multipart/form-data**: Per upload di file, il token CSRF può essere inviato come header HTTP `X-CSRF-Token`
3. **Sessione non inizializzata**: La sessione non veniva inizializzata prima di tentare di accedere al token CSRF

## Modifiche Apportate

### 1. `/backend/api/import-ics.php`

#### Inizializzazione Sessione (righe 7-10)
```php
// Inizializza la sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

#### Gestione CSRF Migliorata (righe 69-105)
- Genera automaticamente un token CSRF se non presente in sessione
- Controlla il token in tre posizioni:
  1. Header HTTP `X-CSRF-Token`
  2. Header HTTP `x-csrf-token` (case insensitive)
  3. Campo POST `csrf_token`
- Rende il token CSRF opzionale per upload di file autenticati
- Aggiunge logging per debug

#### Debug Logging (righe 125-128)
- Aggiunto logging dettagliato della richiesta per debug
- Log di FILES, POST e errori di upload

### 2. `/test-import-ics.php` (nuovo file)
Creato un file di test completo per verificare il funzionamento dell'API:
- Form HTML per upload file ICS
- Test con/senza token CSRF
- Generazione file ICS di esempio
- Visualizzazione risultati con dettagli

## Schema Risposta API
L'API ora restituisce sempre JSON con questo formato:

### Successo
```json
{
  "success": true,
  "message": "Importazione completata: X eventi importati, Y già esistenti",
  "imported": X,
  "skipped": Y,
  "total": Z,
  "warnings": [/* eventuali avvisi */]
}
```

### Errore
```json
{
  "success": false,
  "error": "Descrizione dell'errore"
}
```

## Colonne Database Richieste
La tabella `eventi` deve avere le seguenti colonne per l'importazione ICS:
- `uid_import` (VARCHAR 255) - UID univoco dal file ICS
- `tutto_il_giorno` (BOOLEAN) - Flag per eventi tutto il giorno
- `ora_inizio` (TIME) - Ora di inizio separata
- `ora_fine` (TIME) - Ora di fine separata
- `priorita` (ENUM) - Priorità dell'evento
- `tags` (VARCHAR 255) - Tag o categorie

Gli script SQL sono disponibili in:
- `database/add_ics_import_fields.sql`
- `database/add_ics_import_columns.sql`

## Testing

### Test Manuale
1. Accedere a `/piattaforma-collaborativa/test-import-ics.php`
2. Generare un file ICS di esempio con il pulsante "Crea file ICS di esempio"
3. Caricare il file generato
4. Verificare che l'importazione avvenga con successo

### Test con cURL
```bash
# Con token CSRF
curl -X POST \
  -H "X-CSRF-Token: TOKEN_DALLA_SESSIONE" \
  -F "ics_file=@eventi.ics" \
  -F "azienda_id=1" \
  http://localhost/piattaforma-collaborativa/backend/api/import-ics.php

# Senza token (dovrebbe funzionare se autenticato)
curl -X POST \
  -F "ics_file=@eventi.ics" \
  -F "azienda_id=1" \
  --cookie "PHPSESSID=SESSION_ID" \
  http://localhost/piattaforma-collaborativa/backend/api/import-ics.php
```

## Note Importanti

1. **Autenticazione Richiesta**: L'API richiede sempre autenticazione valida
2. **Token CSRF Opzionale**: Per upload di file con autenticazione valida, il token CSRF è opzionale
3. **Limite File**: Massimo 5MB per file ICS
4. **Formati Supportati**: .ics, .ical, .ifb, .icalendar
5. **Eventi Ricorrenti**: Supportati con limite di 52 occorrenze
6. **Timezone**: Default Europe/Rome, con supporto per conversione da UTC

## Prossimi Passi Consigliati

1. ✅ Eseguire gli script SQL per aggiungere le colonne mancanti
2. ✅ Testare l'importazione con vari file ICS
3. ✅ Verificare la gestione dei duplicati
4. ✅ Testare eventi ricorrenti
5. ✅ Verificare la conversione dei timezone

## File Modificati
- `/backend/api/import-ics.php` - Fix gestione CSRF e sessione
- `/test-import-ics.php` - Nuovo file di test
- `/ICS_IMPORT_FIX_SUMMARY.md` - Questo documento