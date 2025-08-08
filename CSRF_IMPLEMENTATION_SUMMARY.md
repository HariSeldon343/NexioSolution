# Risoluzione Problema CSRF Token - Sistema Upload File

## Problema Identificato
Il sistema di upload file restituiva errore "Token CSRF non valido" perché:
1. Il frontend JavaScript generava token casuali se non trovava il meta tag
2. Il backend validava contro token di sessione mai generati
3. Mancava coordinamento tra frontend e backend per la gestione CSRF

## Soluzioni Implementate

### 1. Nuovo CSRFTokenManager (backend/utils/CSRFTokenManager.php)
- Classe singleton per gestione centralizzata dei token CSRF
- Metodi per generazione, validazione e rinnovo token
- Utilizzo di `hash_equals()` per confronti sicuri

### 2. Aggiornamento API Backend
**File modificati:**
- `backend/api/upload-multiple.php`: Implementata validazione CSRF obbligatoria
- `backend/api/upload-file.php`: Implementata validazione CSRF obbligatoria
- `backend/api/get-csrf-token.php`: Nuovo endpoint per recupero token

**Caratteristiche:**
- Validazione CSRF obbligatoria per tutti gli upload
- Utilizzo del CSRFTokenManager centralizzato
- Messaggi di errore chiari e specifici

### 3. Aggiornamento Frontend JavaScript (assets/js/file-explorer.js)
**Modifiche implementate:**
- `getCSRFToken()`: Verifica meta tag, non genera più token casuali
- `refreshCSRFToken()`: Nuovo metodo per recuperare token dal server
- `uploadSingleFile()`: Gestione intelligente degli errori CSRF con retry automatico
- Controllo presenza token prima dell'upload
- Recupero automatico nuovo token in caso di errore CSRF

### 4. Aggiornamento Pagina Principal (filesystem.php)
- Include CSRFTokenManager
- Genera token CSRF in sessione
- Aggiunge meta tag `csrf-token` con token validato
- Escaping sicuro del token nell'HTML

### 5. Script di Test (test-csrf-integration.php)
- Test completo del sistema CSRF
- Verifica generazione, validazione e rinnovo token
- Test casi di errore (token mancante/non valido)

## Flusso di Funzionamento Aggiornato

### Caricamento Pagina
1. `filesystem.php` genera token CSRF via CSRFTokenManager
2. Token inserito nel meta tag `csrf-token`
3. JavaScript legge token dal meta tag

### Upload File
1. JavaScript verifica presenza token nel meta tag
2. Se token presente, procede con upload
3. Backend valida token obbligatoriamente
4. Se errore CSRF, JavaScript tenta recupero nuovo token
5. Retry upload con nuovo token (massimo 1 tentativo)

### Gestione Errori
- Token mancante nel frontend: Errore immediato con suggerimento ricarica pagina
- Token non valido: Tentativo automatico di recupero nuovo token
- Token non recuperabile: Errore finale con messaggio specifico

## File Creati
- `backend/utils/CSRFTokenManager.php`: Manager centralizzato CSRF
- `backend/api/get-csrf-token.php`: Endpoint per recupero token
- `test-csrf-integration.php`: Script di test del sistema

## File Modificati
- `filesystem.php`: Integrazione CSRFTokenManager e meta tag
- `backend/api/upload-multiple.php`: Validazione CSRF obbligatoria
- `backend/api/upload-file.php`: Validazione CSRF obbligatoria
- `assets/js/file-explorer.js`: Gestione intelligente token CSRF

## Sicurezza Implementata
- ✅ Token CSRF generati con `random_bytes(32)`
- ✅ Validazione tramite `hash_equals()` (timing-safe)
- ✅ Token obbligatorio per tutti gli upload
- ✅ Escaping HTML sicuro del token
- ✅ Gestione errori senza esporre informazioni sensibili
- ✅ Retry mechanism per migliorare UX mantenendo sicurezza

## Test Raccomandati
1. Eseguire `test-csrf-integration.php` per verificare il CSRFTokenManager
2. Testare upload file normale (dovrebbe funzionare)
3. Testare upload con token manipolato (dovrebbe fallire)
4. Testare upload con sessione scaduta (dovrebbe recuperare token)
5. Testare upload con JavaScript disabilitato (dovrebbe fallire con messaggio chiaro)

## Note di Manutenzione
- I token CSRF sono legati alla sessione PHP
- Il CSRFTokenManager è thread-safe
- I token possono essere rinnovati dopo operazioni sensibili
- Il sistema è backward compatible con eventuali form HTML tradizionali