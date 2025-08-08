/**
 * Service Worker per Nexio Calendario PWA
 * Gestisce caching, sincronizzazione offline e notifiche
 */

const CACHE_NAME = 'nexio-calendario-v1.2.0';
const DYNAMIC_CACHE = 'nexio-calendario-dynamic-v1.0.0';

// File da cachare immediatamente
const STATIC_ASSETS = [
    './',
    './index.html',
    './styles.css',
    './app.js',
    './manifest.json',
    './icon-192.png',
    './icon-512.png',
    './apple-touch-icon.png',
    './favicon.ico',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Pattern delle API da cacheare
const API_PATTERNS = [
    '/backend/api/calendar-events.php',
    '/backend/api/get-referenti.php'
];

// Durata cache per diversi tipi di contenuto (in millisecondi)
const CACHE_DURATIONS = {
    static: 24 * 60 * 60 * 1000,    // 24 ore
    api: 5 * 60 * 1000,             // 5 minuti
    images: 7 * 24 * 60 * 60 * 1000 // 7 giorni
};

/**
 * Installazione Service Worker
 */
self.addEventListener('install', event => {
    console.log('[SW] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Installation complete');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('[SW] Installation failed:', error);
            })
    );
});

/**
 * Attivazione Service Worker
 */
self.addEventListener('activate', event => {
    console.log('[SW] Activating...');
    
    event.waitUntil(
        Promise.all([
            // Rimuovi cache obsolete
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME && cacheName !== DYNAMIC_CACHE) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Prendi controllo di tutte le pagine
            self.clients.claim()
        ])
            .then(() => {
                console.log('[SW] Activation complete');
            })
            .catch(error => {
                console.error('[SW] Activation failed:', error);
            })
    );
});

/**
 * Intercettazione richieste fetch
 */
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Gestisci solo richieste GET
    if (request.method !== 'GET') {
        return;
    }
    
    // Strategia diversa in base al tipo di risorsa
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request));
    } else if (isApiRequest(url)) {
        event.respondWith(networkFirst(request));
    } else if (isImageRequest(url)) {
        event.respondWith(staleWhileRevalidate(request));
    } else {
        event.respondWith(networkFirst(request));
    }
});

/**
 * Cache First Strategy - per risorse statiche
 */
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            // Verifica se il cache è ancora valido
            const cacheTime = cachedResponse.headers.get('sw-cache-time');
            if (cacheTime) {
                const now = Date.now();
                const age = now - parseInt(cacheTime);
                if (age < CACHE_DURATIONS.static) {
                    return cachedResponse;
                }
            } else {
                return cachedResponse; // Fallback per cache senza timestamp
            }
        }
        
        // Se non in cache o scaduto, fetch dalla rete
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            const responseClone = networkResponse.clone();
            
            // Aggiungi timestamp al cache
            const enhancedResponse = new Response(responseClone.body, {
                status: responseClone.status,
                statusText: responseClone.statusText,
                headers: {
                    ...Object.fromEntries(responseClone.headers.entries()),
                    'sw-cache-time': Date.now().toString()
                }
            });
            
            cache.put(request, enhancedResponse);
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('[SW] Cache First failed:', error);
        
        // Fallback al cache se disponibile
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Ultimo fallback
        return new Response('Offline - Contenuto non disponibile', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

/**
 * Network First Strategy - per API e contenuti dinamici
 */
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache la risposta se è un'API
            if (isApiRequest(new URL(request.url))) {
                const cache = await caches.open(DYNAMIC_CACHE);
                const responseClone = networkResponse.clone();
                
                // Aggiungi timestamp
                const enhancedResponse = new Response(responseClone.body, {
                    status: responseClone.status,
                    statusText: responseClone.statusText,
                    headers: {
                        ...Object.fromEntries(responseClone.headers.entries()),
                        'sw-cache-time': Date.now().toString()
                    }
                });
                
                cache.put(request, enhancedResponse);
            }
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('[SW] Network failed, trying cache for:', request.url);
        
        // Fallback al cache
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            // Verifica freshness per API
            if (isApiRequest(new URL(request.url))) {
                const cacheTime = cachedResponse.headers.get('sw-cache-time');
                if (cacheTime) {
                    const now = Date.now();
                    const age = now - parseInt(cacheTime);
                    if (age > CACHE_DURATIONS.api) {
                        console.log('[SW] API cache expired, but using as fallback');
                        // Aggiungi header per indicare che è cache scaduto
                        const staleResponse = new Response(cachedResponse.body, {
                            status: cachedResponse.status,
                            statusText: cachedResponse.statusText,
                            headers: {
                                ...Object.fromEntries(cachedResponse.headers.entries()),
                                'sw-cache-stale': 'true'
                            }
                        });
                        return staleResponse;
                    }
                }
            }
            
            return cachedResponse;
        }
        
        // Nessun cache disponibile
        if (isApiRequest(new URL(request.url))) {
            return new Response(JSON.stringify({
                success: false,
                error: 'Connessione non disponibile',
                offline: true
            }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            });
        }
        
        return new Response('Offline - Contenuto non disponibile', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

/**
 * Stale While Revalidate - per immagini e risorse meno critiche
 */
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(() => null);
    
    return cachedResponse || await fetchPromise || new Response('Image not available', {
        status: 404,
        statusText: 'Not Found'
    });
}

/**
 * Background Sync per operazioni offline
 */
self.addEventListener('sync', event => {
    console.log('[SW] Background sync:', event.tag);
    
    if (event.tag === 'sync-events') {
        event.waitUntil(syncEvents());
    }
    
    if (event.tag === 'sync-new-event') {
        event.waitUntil(syncNewEvent());
    }
    
    if (event.tag === 'sync-updated-event') {
        event.waitUntil(syncUpdatedEvent());
    }
});

/**
 * Sincronizza eventi quando torna online
 */
async function syncEvents() {
    try {
        console.log('[SW] Syncing events...');
        
        // Notifica l'app che la sincronizzazione è iniziata
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'SYNC_STARTED',
                data: { action: 'events' }
            });
        });
        
        // La sincronizzazione vera sarà gestita dall'app
        // Qui triggeriamo solo l'evento
        
        clients.forEach(client => {
            client.postMessage({
                type: 'SYNC_COMPLETED',
                data: { action: 'events' }
            });
        });
        
    } catch (error) {
        console.error('[SW] Sync failed:', error);
        
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'SYNC_FAILED',
                data: { action: 'events', error: error.message }
            });
        });
    }
}

/**
 * Sincronizza nuovo evento creato offline
 */
async function syncNewEvent() {
    try {
        const pendingEvents = await getFromIndexedDB('pendingEvents');
        
        for (const eventData of pendingEvents) {
            const response = await fetch('../backend/api/calendar-events.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(eventData)
            });
            
            if (response.ok) {
                await removeFromIndexedDB('pendingEvents', eventData.id);
            }
        }
        
        // Notifica l'app
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'EVENTS_SYNCED',
                data: { action: 'create' }
            });
        });
        
    } catch (error) {
        console.error('[SW] New event sync failed:', error);
    }
}

/**
 * Sincronizza eventi modificati offline
 */
async function syncUpdatedEvent() {
    try {
        const pendingUpdates = await getFromIndexedDB('pendingUpdates');
        
        for (const updateData of pendingUpdates) {
            const response = await fetch('../backend/api/calendar-events.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(updateData)
            });
            
            if (response.ok) {
                await removeFromIndexedDB('pendingUpdates', updateData.id);
            }
        }
        
        // Notifica l'app
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'EVENTS_SYNCED',
                data: { action: 'update' }
            });
        });
        
    } catch (error) {
        console.error('[SW] Update event sync failed:', error);
    }
}

/**
 * Push notifications
 */
self.addEventListener('push', event => {
    console.log('[SW] Push received');
    
    const options = {
        body: 'Hai nuovi eventi nel calendario',
        icon: './icon-192.png',
        badge: './icon-72.png',
        tag: 'calendar-update',
        renotify: true,
        requireInteraction: true,
        actions: [
            {
                action: 'view',
                title: 'Visualizza',
                icon: './icon-72.png'
            },
            {
                action: 'dismiss',
                title: 'Ignora'
            }
        ]
    };
    
    if (event.data) {
        const data = event.data.json();
        options.body = data.message || options.body;
        options.data = data;
    }
    
    event.waitUntil(
        self.registration.showNotification('Nexio Calendario', options)
    );
});

/**
 * Notification click handler
 */
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            self.clients.openWindow('./')
        );
    }
});

/**
 * Message handler per comunicazioni con l'app
 */
self.addEventListener('message', event => {
    console.log('[SW] Message received:', event.data);
    
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'CLAIM_CLIENTS':
            self.clients.claim();
            break;
            
        case 'CACHE_BUST':
            // Forza aggiornamento cache
            caches.delete(CACHE_NAME);
            caches.delete(DYNAMIC_CACHE);
            break;
            
        case 'SYNC_REQUEST':
            // Registra background sync
            if (data.tag) {
                self.registration.sync.register(data.tag);
            }
            break;
    }
});

// Utility Functions

function isStaticAsset(url) {
    return STATIC_ASSETS.some(asset => 
        url.pathname.endsWith(asset.replace('./', ''))
    ) || url.hostname === 'cdnjs.cloudflare.com';
}

function isApiRequest(url) {
    return API_PATTERNS.some(pattern => 
        url.pathname.includes(pattern)
    );
}

function isImageRequest(url) {
    return /\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(url.pathname);
}

// IndexedDB helpers (simplified)
async function getFromIndexedDB(storeName) {
    // Implementazione semplificata - in un'app reale useresti una libreria come Dexie
    return [];
}

async function removeFromIndexedDB(storeName, id) {
    // Implementazione semplificata
    return true;
}

console.log('[SW] Service Worker loaded');