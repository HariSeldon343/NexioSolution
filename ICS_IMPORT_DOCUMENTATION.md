# Documentazione Importazione ICS

## Panoramica
La funzionalità di importazione ICS permette di importare eventi da file calendario standard (ICS/iCal) nel sistema Nexio.

## Funzionalità Implementate

### 1. Parsing Avanzato ICS
- **Eventi singoli**: Supporto completo per eventi standard VEVENT
- **Eventi ricorrenti**: Gestione di RRULE per eventi che si ripetono (giornalieri, settimanali, mensili, annuali)
- **Eventi tutto il giorno**: Riconoscimento automatico di eventi con VALUE=DATE
- **Timezone**: Conversione automatica da timezone specifici a Europe/Rome
- **Partecipanti**: Importazione di ATTENDEE con matching automatico utenti nel sistema
- **Organizzatore**: Riconoscimento campo ORGANIZER

### 2. Campi Supportati
- **SUMMARY** → titolo
- **DESCRIPTION** → descrizione  
- **LOCATION** → luogo
- **DTSTART/DTEND** → data_inizio/data_fine con ora_inizio/ora_fine
- **DURATION** → calcolo automatico data_fine
- **UID** → uid_import (per evitare duplicati)
- **CATEGORIES** → tipo (meeting, conferenza, formazione, scadenza, compleanno)
- **STATUS** → stato (programmato, annullato)
- **PRIORITY** → priorita (alta, media, bassa)
- **ATTENDEE** → partecipanti (se utenti esistono nel sistema)
- **CLASS** → tags (privato per PRIVATE/CONFIDENTIAL)
- **RRULE** → generazione eventi ricorrenti

### 3. Gestione Duplicati
- Controllo basato su UID se presente
- Controllo fallback su titolo + data_inizio + azienda
- Eventi duplicati vengono saltati automaticamente

### 4. Gestione Ricorrenze
- **FREQ**: DAILY, WEEKLY, MONTHLY, YEARLY
- **COUNT**: Numero massimo di occorrenze (limitato a 52)
- **UNTIL**: Data fine ricorrenza
- **INTERVAL**: Intervallo tra occorrenze

### 5. Database Schema

#### Campi aggiunti alla tabella `eventi`:
```sql
- uid_import VARCHAR(255) -- UID dal file ICS
- tutto_il_giorno TINYINT(1) -- Flag evento tutto il giorno
- ora_inizio TIME -- Ora di inizio separata
- ora_fine TIME -- Ora di fine separata
```

#### Tabella `evento_partecipanti`:
```sql
- id INT PRIMARY KEY
- evento_id INT 
- utente_id INT
- stato ENUM('invitato', 'confermato', 'rifiutato', 'forse')
```

## Utilizzo

### Da interfaccia web (calendario-eventi.php):
1. Click su pulsante "Importa ICS" nella toolbar
2. Seleziona file ICS dal computer
3. (Super Admin) Seleziona azienda destinazione
4. Click su "Importa Eventi"
5. Visualizza risultato importazione

### Formato file supportati:
- `.ics` - Standard iCalendar
- `.ical` - Alternativa iCalendar
- `.ifb` - Free/Busy information
- `.icalendar` - Estensione alternativa

### Limiti:
- Dimensione massima file: 5MB
- Eventi ricorrenti: massimo 52 occorrenze
- Line continuations: supportate (RFC compliant)

## Test
Usa `test-ics-import.php` per testare l'importazione con:
- File ICS di esempio predefinito
- Upload di file ICS personalizzati
- Visualizzazione eventi importati

## API Endpoint
**URL**: `/backend/api/import-ics.php`
**Method**: POST
**Headers**: 
- X-CSRF-Token: required
**Body**: multipart/form-data
- ics_file: File ICS da importare
- azienda_id: (opzionale, solo super admin)

**Response**:
```json
{
  "success": true,
  "imported": 5,
  "skipped": 2,
  "total": 7,
  "warnings": ["Lista di eventuali avvisi"]
}
```

## Sicurezza
- Validazione CSRF token obbligatoria
- Controllo autenticazione utente
- Validazione estensione file
- Sanitizzazione dati importati
- Transazioni database per atomicità

## Note Tecniche
- Il parser gestisce line folding (RFC 5545 compliant)
- Conversione automatica timezone a Europe/Rome
- Decodifica escape sequences ICS (\n, \,, \;, \\)
- Gestione graceful di campi mancanti con valori default