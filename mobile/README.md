# Nexio Calendar Mobile PWA

Progressive Web App per il sistema calendario di Nexio, ottimizzata per dispositivi mobile con funzionalità offline complete.

## 🚀 Caratteristiche Principali

### ✨ Progressive Web App
- **Installabile** su iOS e Android
- **Offline first** - funziona anche senza connessione
- **Performance native** con caching intelligente
- **Push notifications** per promemoria eventi
- **Responsive design** ottimizzato per mobile

### 📅 Funzionalità Calendario
- **4 viste calendario**: Mese, Settimana, Giorno, Lista
- **Gestione eventi completa**: Crea, modifica, elimina
- **Sincronizzazione real-time** con il calendario desktop
- **Navigazione touch** con gestures
- **Ricerca e filtri** eventi
- **Esportazione ICS** del calendario

### 🔒 Sicurezza e Multi-tenancy
- **Autenticazione SSO** con sistema Nexio
- **CSRF Protection** su tutte le operazioni
- **Isolamento multi-tenant** completo
- **Permessi granulari** per utenti e ruoli
- **Crittografia dati** sensibili

### 📱 Ottimizzazioni Mobile
- **Touch-friendly** UI/UX
- **Keyboard shortcuts** per utenti avanzati
- **Dark mode** supportato
- **Haptic feedback** simulation
- **Battery efficient** operations

## 🛠 Installazione

### Prerequisiti
- **Nexio Platform** già installato e configurato
- **PHP 8.0+** con estensioni necessarie
- **MySQL 5.7+** database
- **HTTPS** raccomandato (required per alcune funzioni PWA)

### Setup Veloce

1. **Verifica installazione base**:
   ```bash
   # I files PWA sono già nella directory /mobile/
   # Controlla che il sistema Nexio base sia funzionante
   cd /mnt/c/xampp/htdocs/piattaforma-collaborativa
   ```

2. **Configurazione server web**:
   ```apache
   # Il file .htaccess è già configurato
   # Assicurati che mod_rewrite sia attivo in Apache
   # Per HTTPS (raccomandato):
   # - Configura certificato SSL
   # - Decommentare righe HTTPS nel .htaccess
   ```

3. **Test installazione**:
   ```bash
   # Apri il browser e vai su:
   http://localhost/piattaforma-collaborativa/mobile/
   
   # Per HTTPS:
   https://your-domain.com/piattaforma-collaborativa/mobile/
   ```

### Database Updates

Il PWA utilizza le stesse tabelle del sistema principale, con queste aggiunte opzionali:

```sql
-- Tabella per push notifications (opzionale)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    subscription_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_subscription (utente_id)
);

-- Estensione tabella preferenze per mobile (opzionale)
ALTER TABLE user_preferences 
ADD COLUMN mobile_settings JSON AFTER valore;
```

## 📖 Utilizzo

### Accesso all'App

1. **Desktop/Tablet**: Vai su `http://your-server/piattaforma-collaborativa/mobile/`
2. **Mobile**: Apri il link nel browser, poi "Aggiungi a schermata home"
3. **Installazione PWA**: Clicca "Installa" quando appare il banner

### Navigazione Principale

#### 🎯 Header
- **Logo Nexio**: Torna alla vista corrente  
- **Sync**: Sincronizza dati manualmente
- **+ Evento**: Crea nuovo evento
- **Menu**: Apre il menu laterale

#### 🗓 Controlli Vista
- **Mese/Settimana/Giorno/Lista**: Cambia visualizzazione
- **← / →**: Naviga periodi precedenti/successivi  
- **Oggi**: Torna alla data odierna

#### 📱 Gesti Touch
- **Swipe sinistra/destra**: Naviga periodi
- **Tap evento**: Visualizza dettagli
- **Long press**: Menu contestuale (dove applicabile)

### Gestione Eventi

#### ✅ Creare Eventi
1. Tap **+ Evento** nella navbar
2. Compila **titolo** (obbligatorio) e **data/ora**
3. Aggiungi descrizione, luogo, tipo evento
4. Seleziona partecipanti (se hai permessi)
5. **Salva** per creare

#### ✏️ Modificare Eventi
1. Tap sull'evento desiderato
2. Modifica i campi necessari
3. **Salva** per confermare modifiche
4. Opzionale: Invia notifiche partecipanti

#### 🗑️ Eliminare Eventi
1. Apri evento da modificare
2. Tap **Elimina** (rosso)
3. Conferma eliminazione
4. Opzionale: Notifica partecipanti

### Funzioni Offline

#### 📦 Cache Automatica
- **Eventi recenti** (ultimo mese) salvati localmente
- **Preferenze utente** persistite 
- **Interfaccia** completamente utilizzabile offline
- **Auto-sync** al ritorno online

#### ✈️ Modalità Offline
- **Visualizza eventi** già scaricati
- **Crea nuovi eventi** (sincronizzati al ritorno online)
- **Naviga calendario** senza limitazioni
- **Modifica preferenze** localmente

### Menu Laterale

#### 📊 Sezione Calendario
- **Aggiorna**: Ricarica dati dal server
- **Esporta ICS**: Download file calendario

#### ⚙️ Impostazioni  
- **Notifiche**: Abilita/Disabilita notifiche push
- **Tema scuro**: Toggle dark/light mode
- **Preferenze**: Salvate automaticamente

#### 👤 Account
- **Info utente**: Nome, ruolo, azienda corrente
- **Esci**: Logout dal sistema

## 🔧 Configurazione Avanzata

### Service Worker

Il service worker gestisce:
- **Caching strategico** di assets e dati
- **Background sync** per operazioni offline
- **Push notifications** 
- **Auto-update** dell'app

Configurazione in `sw.js`:
```javascript
const CACHE_NAME = 'nexio-calendar-v1.0.0';
const RUNTIME_CACHE = 'nexio-calendar-runtime';
const DATA_CACHE = 'nexio-calendar-data';
```

### Push Notifications

Per abilitare le notifiche push:

1. **Genera VAPID keys**:
   ```bash
   # Usa un generatore online o libreria Node.js
   # Sostituisci 'your-vapid-public-key' in app.js
   ```

2. **Configura server push** (opzionale):
   ```php
   // In calendar-mobile-api.php
   // Implementa logica invio notifiche push
   ```

### API Endpoints

Il PWA utilizza questi endpoints principali:

- **GET** `/backend/api/calendar-mobile-api.php?action=auth_check`
- **GET** `/backend/api/calendar-mobile-api.php?action=events`
- **POST** `/backend/api/calendar-mobile-api.php?action=events` 
- **PUT** `/backend/api/calendar-mobile-api.php?action=events`
- **DELETE** `/backend/api/calendar-mobile-api.php?action=events`
- **GET** `/backend/api/calendar-mobile-api.php?action=sync`

### Personalizzazione UI

#### 🎨 Temi e Colori
Modifica variabili CSS in `styles.css`:
```css
:root {
    --primary-color: #2d5a9f;     /* Colore principale */
    --primary-light: #4299e1;     /* Variante chiara */
    --success-color: #28a745;     /* Successo */
    --warning-color: #ffc107;     /* Warning */
    --danger-color: #dc3545;      /* Errore/Elimina */
}
```

#### 📐 Layout Responsive
Breakpoints principali:
- **Mobile**: `< 576px` 
- **Tablet**: `576px - 768px`
- **Desktop**: `> 768px`

## 🐛 Troubleshooting

### Problemi Comuni

#### PWA non installabile
- ✅ Verifica **HTTPS** attivo (obbligatorio)
- ✅ Controlla che **manifest.json** sia accessibile
- ✅ Service worker registrato correttamente
- ✅ Browser supporta PWA (Chrome, Edge, Safari 11.3+)

#### Eventi non sincronizzano
- ✅ Verifica connessione internet
- ✅ Controlla log browser (F12 → Console)
- ✅ Testa endpoint API manualmente
- ✅ Verifica permessi utente nel sistema

#### Notifiche non funzionano  
- ✅ **HTTPS** obbligatorio per push notifications
- ✅ Permessi browser concessi
- ✅ VAPID keys configurate correttamente
- ✅ Service worker attivo

#### Layout rotto su mobile
- ✅ Viewport meta tag presente
- ✅ Bootstrap CSS caricato correttamente  
- ✅ CSS custom non in conflitto
- ✅ JavaScript errori nella console

### Log e Debug

#### Abilitare debug mode
```javascript
// In app.js, cambia:
const DEBUG = true; // Mostra log dettagliati console
```

#### Luoghi di log
- **Browser Console**: Errori JavaScript
- **Network tab**: Richieste API failed
- **Application tab**: Service Worker status
- **Server logs**: `/logs/error.log`

## 🔄 Updates e Manutenzione

### Aggiornamenti PWA

Per rilasciare una nuova versione:

1. **Aggiorna versione** in `manifest.json` e `sw.js`
2. **Modifica CACHE_NAME** nel service worker
3. **Testa funzionalità** in ambiente staging
4. **Deploy** su server di produzione
5. **Force refresh** su dispositivi (se necessario)

### Monitoraggio

Metriche da monitorare:
- **Install rate**: Quanti utenti installano la PWA
- **Engagement**: Tempo utilizzo vs web
- **Offline usage**: Utilizzo in modalità offline  
- **Error rate**: Errori JavaScript/API
- **Performance**: Tempi caricamento

### Backup

Include nelle procedure di backup:
- **Database** tabelle standard Nexio
- **Push subscriptions** (se attive)
- **User preferences** estese
- **Service worker cache** (rigenerato automaticamente)

## 📝 Supporto

### Compatibilità Browser

| Browser | Desktop | Mobile | PWA Install |
|---------|---------|--------|-------------|
| Chrome  | ✅      | ✅      | ✅          |
| Firefox | ✅      | ✅      | ❌          |
| Safari  | ✅      | ✅      | ⚠️ (limitato) |
| Edge    | ✅      | ✅      | ✅          |

### Dispositivi Testati

- **iOS**: iPhone 8+ (Safari 11.3+)
- **Android**: Chrome 67+, Samsung Internet
- **Desktop**: Chrome, Firefox, Edge, Safari

### Limitazioni Note

- **iOS Safari**: Installazione PWA limitata
- **Firefox**: Non supporta installazione PWA
- **Older browsers**: Fallback a versione web standard
- **HTTP**: Service worker limitato (solo localhost)

---

**Nexio Calendar PWA v1.0.0**  
© 2025 Nexio Solution - Sistema calendario mobile integrato