// Service Worker per Nexio Mobile PWA - Dynamic Version
// Usa percorsi relativi per compatibilità tra localhost e produzione

const CACHE_VERSION = 'v3';
const CACHE_NAME = `nexio-mobile-${CACHE_VERSION}`;
const RUNTIME_CACHE = `nexio-runtime-${CACHE_VERSION}`;
const IMAGE_CACHE = `nexio-images-${CACHE_VERSION}`;
const DOCUMENT_CACHE = `nexio-documents-${CACHE_VERSION}`;

// Ottieni il base path dal service worker scope
const SW_SCOPE = self.registration.scope;
const BASE_PATH = new URL(SW_SCOPE).pathname.replace('/mobile/', '');

// Asset essenziali da cachare all'installazione (percorsi relativi)
const STATIC_ASSETS = [
  './',
  './index.php',
  './login.php',
  './documenti.php',
  './editor.php',
  './offline.html',
  './manifest.php',
  '../assets/images/nexio-icon.svg',
  '../assets/images/nexio-logo.svg'
];

// Cache limits
const CACHE_LIMITS = {
  images: 50,      // Max 50 images
  documents: 100,  // Max 100 documents
  runtime: 50      // Max 50 runtime responses
};

// Pattern URL per API e risorse dinamiche
const API_PATTERN = /\/api\//;
const IMAGE_PATTERN = /\.(jpg|jpeg|png|gif|svg|webp)$/i;
const STATIC_PATTERN = /\.(css|js)$/i;
const ONLYOFFICE_PATTERN = /\/onlyoffice\//;

// Installazione del Service Worker
self.addEventListener('install', event => {
  console.log('[SW] Installing Service Worker v3');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW] Caching static assets');
        // Prova a cachare gli asset, ma non fallire se alcuni non sono disponibili
        return Promise.allSettled(
          STATIC_ASSETS.map(url => 
            cache.add(url).catch(err => {
              console.warn(`[SW] Failed to cache ${url}:`, err);
            })
          )
        );
      })
      .then(() => self.skipWaiting()) // Attiva immediatamente il nuovo SW
  );
});

// Attivazione del Service Worker
self.addEventListener('activate', event => {
  console.log('[SW] Activating Service Worker v3');
  
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          // Elimina vecchie cache
          if (cacheName !== CACHE_NAME && 
              cacheName !== RUNTIME_CACHE && 
              cacheName !== IMAGE_CACHE &&
              cacheName !== DOCUMENT_CACHE) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim()) // Prendi controllo di tutte le pagine
  );
});

// Intercettazione delle richieste
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Ignora richieste non HTTP/HTTPS
  if (!url.protocol.startsWith('http')) return;
  
  // Ignora richieste esterne al nostro dominio
  if (url.origin !== location.origin) return;
  
  // Non cachare OnlyOffice assets
  if (ONLYOFFICE_PATTERN.test(url.pathname)) {
    event.respondWith(fetch(request));
    return;
  }
  
  // Strategia per API: Network First con fallback alla cache
  if (API_PATTERN.test(url.pathname)) {
    event.respondWith(networkFirst(request, RUNTIME_CACHE));
    return;
  }
  
  // Strategia per immagini: Cache First con network fallback
  if (IMAGE_PATTERN.test(url.pathname)) {
    event.respondWith(cacheFirst(request, IMAGE_CACHE));
    return;
  }
  
  // Strategia per asset statici (CSS/JS): Cache First
  if (STATIC_PATTERN.test(url.pathname)) {
    event.respondWith(cacheFirst(request, CACHE_NAME));
    return;
  }
  
  // Strategia per documenti HTML: Network First con offline fallback
  if (request.mode === 'navigate' || request.destination === 'document') {
    event.respondWith(
      networkFirst(request, CACHE_NAME)
        .catch(() => caches.match('./offline.html'))
    );
    return;
  }
  
  // Default: Network First
  event.respondWith(networkFirst(request, RUNTIME_CACHE));
});

// Strategia Cache First
async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  
  if (cached) {
    // Aggiorna la cache in background
    fetch(request)
      .then(response => {
        if (response && response.status === 200) {
          cache.put(request, response.clone());
        }
      })
      .catch(() => {}); // Ignora errori di rete per l'aggiornamento
    
    return cached;
  }
  
  // Se non in cache, prova la rete
  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
      // Limita la dimensione della cache
      await trimCache(cacheName, CACHE_LIMITS.runtime);
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.error('[SW] Network request failed:', error);
    throw error;
  }
}

// Strategia Network First
async function networkFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  
  try {
    const response = await fetch(request);
    
    // Cacha solo risposte valide
    if (response && response.status === 200) {
      // Limita la dimensione della cache
      await trimCache(cacheName, CACHE_LIMITS.runtime);
      cache.put(request, response.clone());
    }
    
    return response;
  } catch (error) {
    // Fallback alla cache se offline
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
    
    throw error;
  }
}

// Funzione per limitare la dimensione della cache
async function trimCache(cacheName, maxItems) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  
  if (keys.length > maxItems) {
    // Rimuovi gli elementi più vecchi
    const keysToDelete = keys.slice(0, keys.length - maxItems);
    await Promise.all(keysToDelete.map(key => cache.delete(key)));
  }
}

// Gestione messaggi dal client
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => caches.delete(cacheName))
        );
      }).then(() => {
        // Notifica il client che la cache è stata pulita
        if (event.ports && event.ports[0]) {
          event.ports[0].postMessage({ type: 'CACHE_CLEARED' });
        }
      })
    );
  }
});

// Background Sync per sincronizzare dati quando torna online
self.addEventListener('sync', event => {
  if (event.tag === 'sync-data') {
    event.waitUntil(syncData());
  }
});

async function syncData() {
  // Implementare la logica di sincronizzazione qui
  console.log('[SW] Syncing data with server...');
  
  try {
    // Usa percorso relativo per API
    const response = await fetch('../backend/api/sync-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ action: 'sync' })
    });
    
    if (response.ok) {
      console.log('[SW] Data synced successfully');
    }
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}

// Push Notifications
self.addEventListener('push', event => {
  const options = {
    body: event.data ? event.data.text() : 'Nuova notifica da Nexio',
    icon: './icons/icon-192x192.png',
    badge: './icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'Apri',
        icon: './icons/icon-72x72.png'
      },
      {
        action: 'close',
        title: 'Chiudi',
        icon: './icons/icon-72x72.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('Nexio Mobile', options)
  );
});

// Gestione click sulle notifiche
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'explore') {
    // Apri l'app o naviga alla pagina specifica
    event.waitUntil(
      clients.openWindow('./')
    );
  }
});

console.log('[SW] Service Worker v3 loaded with dynamic paths');