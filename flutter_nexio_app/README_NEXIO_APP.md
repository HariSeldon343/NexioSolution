# Nexio Platform - App Mobile Flutter

## 📱 Descrizione
App mobile completa per la piattaforma Nexio, sviluppata in Flutter con tutte le funzionalità principali implementate e completamente in italiano.

## ✨ Funzionalità Implementate

### 🔐 Autenticazione
- Login con username/email e password
- Gestione sessione utente
- Logout sicuro
- Ricorda utente

### 📊 Dashboard
- Statistiche in tempo reale
- Grafici attività settimanale
- Attività recenti
- Widget statistiche interattivi

### 📁 Gestione Documenti
- Esplorazione cartelle
- Upload multiplo file
- Download documenti
- Ricerca documenti
- Anteprima file
- Gestione permessi

### 📅 Calendario Eventi
- Vista calendario mensile
- Creazione eventi
- Gestione appuntamenti
- Notifiche eventi
- Sincronizzazione real-time

### 🏢 Gestione Aziende (Solo Admin)
- Lista aziende
- Dettagli azienda
- Cambio azienda corrente
- Stato aziende

### 👥 Gestione Utenti (Solo Admin)
- Lista utenti
- Profili utente
- Gestione ruoli
- Attivazione/disattivazione

### 👤 Profilo Utente
- Modifica profilo
- Cambio password
- Impostazioni notifiche
- Preferenze app

## 🎨 Design e UI
- **Colori**: Basati su schema colori piattaforma web
  - Primary: #2563EB
  - Sidebar: #162D4F
  - Background: #F8FAFC
- **Font**: Inter (Google Fonts)
- **Icone**: Material Design Icons
- **Tema**: Material Design 3
- **Lingua**: Completamente in italiano

## 📲 Installazione APK

### Prerequisiti
- Android 5.0 (API 21) o superiore
- Almeno 100MB di spazio libero
- Connessione internet per sincronizzazione dati

### Passaggi Installazione
1. Trasferisci il file APK sul tuo dispositivo Android
2. Abilita "Origini sconosciute" nelle impostazioni di sicurezza
3. Apri il file APK e segui le istruzioni
4. Concedi i permessi richiesti:
   - Internet
   - Lettura/scrittura storage
   - Notifiche (opzionale)

## 🔧 Configurazione

### URL Server
L'app è configurata per connettersi a:
```
http://localhost/piattaforma-collaborativa
```

Per modificare l'URL del server, modifica il file:
```dart
lib/config/constants.dart
```

### Credenziali Test
Utilizza le stesse credenziali della piattaforma web per accedere.

## 🛠️ Build da Sorgente

### Requisiti
- Flutter SDK 3.32.8+
- Android Studio / VS Code
- JDK 11+

### Comandi Build
```bash
# Debug APK
flutter build apk --debug

# Release APK
flutter build apk --release

# App Bundle (per Play Store)
flutter build appbundle --release
```

## 📁 Struttura Progetto
```
lib/
├── config/          # Configurazioni app
├── models/          # Modelli dati
├── providers/       # State management
├── screens/         # Schermate app
├── services/        # Servizi API
└── widgets/         # Widget riutilizzabili
```

## 🚀 Funzionalità Future
- [ ] Notifiche push
- [ ] Modalità offline
- [ ] Sincronizzazione automatica
- [ ] Biometria per login
- [ ] Dark mode
- [ ] Multi-lingua
- [ ] Chat integrata
- [ ] Scanner documenti

## ⚠️ Note Importanti
1. L'app richiede connessione internet attiva
2. I dati sono sincronizzati in tempo reale con il server
3. Le modifiche fatte nell'app si riflettono immediatamente sul web
4. L'APK di debug ha prestazioni inferiori rispetto alla versione release

## 📝 Troubleshooting

### L'app non si connette al server
- Verifica che il server sia raggiungibile
- Controlla le impostazioni di rete
- Verifica l'URL del server in constants.dart

### Login non funziona
- Verifica credenziali
- Controlla connessione internet
- Verifica che l'utente sia attivo nel sistema

### Upload file non funziona
- Verifica permessi storage
- Controlla dimensione file (max 10MB)
- Verifica spazio disponibile

## 📞 Supporto
Per assistenza, contatta l'amministratore del sistema o apri un ticket dalla piattaforma web.

## 📄 Licenza
© 2025 Nexio Platform. Tutti i diritti riservati.