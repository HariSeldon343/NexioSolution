# Nexio Sidebar Standardizzata

## Panoramica

La sidebar standardizzata di Nexio fornisce una navigazione consistente e responsiva per tutta la piattaforma. È stata progettata per essere modulare, accessibile e facile da mantenere.

## Struttura dei File

### File Principali
- `/components/sidebar.php` - Componente sidebar riutilizzabile
- `/backend/utils/MenuHelper.php` - Helper per la gestione del menu
- `/assets/css/sidebar-responsive.css` - Stili responsive per la sidebar
- `/assets/js/sidebar-mobile.js` - Funzionalità mobile e accessibilità

### Integrazione
Il componente viene incluso automaticamente in `/components/header.php` quando l'utente è autenticato.

## Caratteristiche

### 1. Menu Organizzato per Sezioni
- **Dashboard** - Sempre visibile
- **Area Operativa** - File Manager, Calendario, Task, Ticket, Conformità, AI
- **Gestione** - Aziende (solo privilegi elevati)
- **Amministrazione** - Utenti, Audit Log, Configurazioni (solo privilegi elevati)
- **Account** - Profilo, Logout

### 2. Controllo Permessi Intelligente
- Integrazione con `Auth::getInstance()`
- Controllo moduli tramite `ModulesHelper`
- Visibilità dinamica basata sui ruoli utente

### 3. Rilevamento Automatico Pagina Attiva
- Confronto automatico dell'URL corrente
- Supporto per alias di pagine correlate
- Classe `active` applicata automaticamente

### 4. Responsive Design
- **Desktop**: Sidebar fissa a sinistra (250px)
- **Tablet**: Sidebar ridotta (220px)
- **Mobile**: Sidebar collassabile con overlay
- **Large screens**: Sidebar estesa (280px)

### 5. Funzionalità Mobile
- Toggle button per apertura/chiusura
- Overlay semi-trasparente
- Navigazione da tastiera (Escape per chiudere, Ctrl+M per toggle)
- Focus trap per accessibilità
- Prevenzione scroll del body quando aperta

## Configurazione Menu

### Aggiungere Nuove Voci

Modifica `/backend/utils/MenuHelper.php` nel metodo `getMenuConfiguration()`:

```php
'nuova_voce' => [
    'icon' => 'fas fa-icon-name',
    'title' => 'Titolo Voce',
    'url' => 'pagina.php',
    'aliases' => ['pagina-correlata.php'], // Opzionale
    'visible' => $condition, // Condizione di visibilità
    'section' => 'operativa' // Sezione di appartenenza
]
```

### Sezioni Disponibili
- `main` - Voci principali (senza titolo sezione)
- `operativa` - Area Operativa
- `gestione` - Gestione
- `amministrazione` - Amministrazione  
- `account` - Account

### Permessi Supportati
```php
// Esempi di condizioni di visibilità
'visible' => true, // Sempre visibile
'visible' => $auth->isSuperAdmin(), // Solo super admin
'visible' => $auth->hasElevatedPrivileges(), // Privilegi elevati
'visible' => ModulesHelper::isModuleEnabled('nome_modulo'), // Modulo attivato
'visible' => $userRole === 'utente_speciale', // Ruolo specifico
```

## Utilizzo nelle Pagine

### Implementazione Standard
Ogni pagina deve solo includere l'header standard:

```php
<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Titolo Pagina';
include 'components/header.php';
?>

<!-- Contenuto della pagina -->
<div class="container-fluid">
    <!-- Il tuo contenuto qui -->
</div>

<?php include 'components/footer.php'; ?>
```

### Informazioni Utente
La sidebar mostra automaticamente:
- Nome completo utente
- Ruolo (con "Super Admin" per super admin)
- Nome azienda corrente (se presente)
- Link "Cambia Azienda" (se multiple aziende disponibili)

## Personalizzazione CSS

### Variabili Principali
```css
/* Colori */
--sidebar-bg: #2d3748;
--sidebar-text: #e2e8f0;
--sidebar-active: #4299e1;
--sidebar-hover: #4299e1;

/* Dimensioni */
--sidebar-width-desktop: 250px;
--sidebar-width-tablet: 220px;
--sidebar-width-large: 280px;
```

### Override Stili
Per personalizzare l'aspetto, aggiungi CSS dopo l'inclusione della sidebar:

```css
.sidebar {
    /* Personalizzazioni qui */
}

.sidebar-menu .menu-item a {
    /* Personalizzazioni link menu */
}
```

## Accessibilità

### Caratteristiche Implementate
- Navigazione da tastiera completa
- ARIA labels appropriati
- Focus trap su mobile
- Supporto screen reader
- Rispetto preferenze utente (reduced motion, high contrast)

### Scorciatoie da Tastiera
- `Escape` - Chiude sidebar su mobile
- `Ctrl + M` - Toggle sidebar su mobile
- `Tab` / `Shift + Tab` - Navigazione focus

## Best Practices

### 1. Aggiornamento Menu
- Non modificare direttamente `/components/sidebar.php`
- Usa sempre `MenuHelper` per modifiche al menu
- Testa le modifiche con diversi ruoli utente

### 2. Permessi
- Usa sempre controlli di permessi appropriati
- Non fare affidamento solo sulla visibilità del menu per la sicurezza
- Implementa controlli di accesso anche nelle pagine

### 3. Performance
- I file CSS/JS hanno cache busting automatico
- Le condizioni di visibilità sono valutate una sola volta per richiesta
- La sidebar è ottimizzata per il rendering veloce

### 4. Testing
Testa sempre:
- Desktop (varie risoluzioni)
- Tablet (768px - 992px)
- Mobile (< 768px)
- Diversi ruoli utente
- Navigazione da tastiera
- Screen reader (se possibile)

## Troubleshooting

### Sidebar Non Visibile
1. Verifica che l'utente sia autenticato
2. Controlla che `header.php` sia incluso correttamente
3. Verifica percorsi dei file CSS/JS

### Menu Item Non Attivo
1. Verifica URL nella configurazione `MenuHelper`
2. Aggiungi alias se necessario per pagine correlate
3. Controlla che la funzione `isActivePage()` funzioni correttamente

### Problemi Mobile
1. Verifica che `sidebar-mobile.js` sia caricato
2. Controlla console per errori JavaScript
3. Testa su dispositivi reali, non solo emulazione

## Manutenzione

### Aggiornamenti Futuri
- Modifica solo `MenuHelper.php` per aggiungere/rimuovere voci
- Usa `/assets/css/sidebar-responsive.css` per modifiche di stile
- Estendi `/assets/js/sidebar-mobile.js` per nuove funzionalità

### Backup
Mantieni sempre backup di:
- `/components/sidebar.php`
- `/backend/utils/MenuHelper.php`
- File CSS/JS personalizzati

## Supporto

Per domande o problemi con la sidebar, consulta:
1. Questa documentazione
2. Commenti nei file di codice
3. Log di sistema per errori PHP/JavaScript