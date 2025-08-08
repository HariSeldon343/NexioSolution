/**
 * Service Worker per Nexio Calendar PWA
 * Gestisce cache, sincronizzazione offline e notifiche push
 */

const CACHE_NAME = 'nexio-calendar-v1.0.0';
const RUNTIME_CACHE = 'nexio-calendar-runtime';
const DATA_CACHE = 'nexio-calendar-data';

// Files to cache for offline functionality
const STATIC_CACHE_URLS = [
    '/piattaforma-collaborativa/mobile/',
    '/piattaforma-collaborativa/mobile/index.html',
    '/piattaforma-collaborativa/mobile/app.js',
    '/piattaforma-collaborativa/mobile/styles.css',
    '/piattaforma-collaborativa/mobile/manifest.json',
    '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
    '/piattaforma-collaborativa/assets/images/nexio-logo.svg',
    '/piattaforma-collaborativa/assets/vendor/bootstrap/css/bootstrap.min.css',
    '/piattaforma-collaborativa/assets/vendor/fontawesome/css/all.min.css'
];

// API endpoints to cache
const API_CACHE_URLS = [
    '/piattaforma-collaborativa/backend/api/calendar-api.php',
    '/piattaforma-collaborativa/backend/api/calendar-events.php'
];

// Install event - cache static resources
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_CACHE_URLS);
            })
            .then(() => {
                // Force activation of new service worker
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Failed to cache static assets:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME && 
                            cacheName !== RUNTIME_CACHE && 
                            cacheName !== DATA_CACHE) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                // Take control of all pages
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Handle API requests
    if (isApiRequest(url)) {
        event.respondWith(handleApiRequest(request));
        return;
    }
    
    // Handle static assets
    if (isStaticAsset(url)) {
        event.respondWith(handleStaticAsset(request));
        return;
    }
    
    // Handle page requests
    event.respondWith(handlePageRequest(request));
});

// Background sync for offline data
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'calendar-sync') {
        event.waitUntil(syncCalendarData());
    } else if (event.tag === 'event-create') {
        event.waitUntil(syncPendingEvents());
    } else if (event.tag === 'event-update') {
        event.waitUntil(syncEventUpdates());
    }
});

// Push notification handler
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');
    
    const options = {
        body: 'Hai un nuovo evento nel calendario',
        icon: '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
        badge: '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
        vibrate: [200, 100, 200],
        data: {
            url: '/piattaforma-collaborativa/mobile/'
        },
        actions: [
            {
                action: 'view',
                title: 'Visualizza',
                icon: '/piattaforma-collaborativa/assets/images/nexio-icon.svg'
            },
            {
                action: 'dismiss',
                title: 'Ignora'
            }
        ]
    };
    
    if (event.data) {
        try {
            const data = event.data.json();
            options.title = data.title || 'Nexio Calendar';
            options.body = data.body || options.body;
            options.data = { ...options.data, ...data };
        } catch (e) {
            options.title = 'Nexio Calendar';
        }
    } else {
        options.title = 'Nexio Calendar';
    }
    
    event.waitUntil(
        self.registration.showNotification(options.title, options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    if (event.action === 'view' || !event.action) {
        const urlToOpen = event.notification.data?.url || '/piattaforma-collaborativa/mobile/';
        
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((clients) => {
                    // Check if app is already open
                    for (const client of clients) {
                        if (client.url.includes('mobile') && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    
                    // Open new window if not found
                    if (self.clients.openWindow) {
                        return self.clients.openWindow(urlToOpen);
                    }
                })
        );
    }
});

// Message handler for communication with main app
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data?.type === 'CACHE_CLEAR') {
        clearAllCaches();
    } else if (event.data?.type === 'SYNC_REQUEST') {
        triggerBackgroundSync();
    }
});

// Helper functions
function isApiRequest(url) {
    return url.pathname.includes('/api/') && 
           (url.pathname.includes('calendar') || url.pathname.includes('events'));
}

function isStaticAsset(url) {
    return url.pathname.includes('/assets/') || 
           url.pathname.includes('/mobile/') ||
           url.pathname.endsWith('.css') ||
           url.pathname.endsWith('.js') ||
           url.pathname.endsWith('.svg') ||
           url.pathname.endsWith('.png');
}

async function handleApiRequest(request) {
    const url = new URL(request.url);
    
    try {
        // Try network first for API requests
        const networkResponse = await fetch(request.clone());
        
        if (networkResponse.ok) {
            // Cache successful GET requests
            if (request.method === 'GET') {
                const cache = await caches.open(DATA_CACHE);
                cache.put(request.clone(), networkResponse.clone());
            }
            return networkResponse;
        }
    } catch (error) {
        console.log('[SW] Network failed for API request, trying cache');
    }
    
    // Fallback to cache for GET requests
    if (request.method === 'GET') {
        const cache = await caches.open(DATA_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
    }
    
    // Return offline response for failed requests
    return new Response(
        JSON.stringify({
            success: false,
            error: 'Offline - riprova quando torni online',
            offline: true
        }),
        {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'application/json' }
        }
    );
}

async function handleStaticAsset(request) {
    // Cache first, then network
    const cache = await caches.open(CACHE_NAME);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            cache.put(request.clone(), networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Failed to fetch static asset:', request.url);
        
        // Return offline page for HTML requests
        if (request.headers.get('accept').includes('text/html')) {
            return cache.match('/piattaforma-collaborativa/mobile/offline.html') ||
                   new Response('<h1>Offline</h1><p>Connessione non disponibile</p>', {
                       headers: { 'Content-Type': 'text/html' }
                   });
        }
        
        return new Response('Offline', { status: 503 });
    }
}

async function handlePageRequest(request) {
    try {
        // Try network first for page requests
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache runtime pages
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request.clone(), networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed for page request, trying cache');
        
        // Try runtime cache
        const cache = await caches.open(RUNTIME_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Try main cache
        const mainCache = await caches.open(CACHE_NAME);
        const mainCachedResponse = await mainCache.match('/piattaforma-collaborativa/mobile/');
        
        if (mainCachedResponse) {
            return mainCachedResponse;
        }
        
        // Return offline page
        return new Response(`
            <!DOCTYPE html>
            <html lang="it">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Nexio Calendar - Offline</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .offline-message { color: #666; margin: 20px 0; }
                    .retry-button { 
                        background: #2d5a9f; color: white; padding: 10px 20px; 
                        border: none; border-radius: 5px; cursor: pointer; 
                    }
                </style>
            </head>
            <body>
                <h1>Nexio Calendar</h1>
                <div class="offline-message">
                    <p>Sei offline. Connettiti a internet per utilizzare l'app.</p>
                </div>
                <button class="retry-button" onclick="window.location.reload()">
                    Riprova
                </button>
            </body>
            </html>
        `, {
            headers: { 'Content-Type': 'text/html' },
            status: 503
        });
    }
}

async function syncCalendarData() {
    console.log('[SW] Syncing calendar data...');
    
    try {
        // Get last sync timestamp from IndexedDB
        const lastSync = await getLastSyncTime();
        
        // Fetch updated events
        const response = await fetch(`/piattaforma-collaborativa/backend/api/calendar-api.php?action=sync&lastSync=${lastSync}`);
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.success) {
                // Update local cache with new data
                await updateLocalCalendarCache(data.sync.events);
                
                // Update last sync time
                await setLastSyncTime(data.sync.newSync);
                
                console.log('[SW] Calendar sync completed:', data.sync.count, 'events updated');
                
                // Notify all clients about sync completion
                const clients = await self.clients.matchAll();
                clients.forEach(client => {
                    client.postMessage({
                        type: 'SYNC_COMPLETE',
                        data: data.sync
                    });
                });
            }
        }
    } catch (error) {
        console.error('[SW] Calendar sync failed:', error);
    }
}

async function syncPendingEvents() {
    console.log('[SW] Syncing pending events...');
    
    try {
        const pendingEvents = await getPendingEvents();
        
        for (const event of pendingEvents) {
            try {
                const response = await fetch('/piattaforma-collaborativa/backend/api/calendar-events.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(event)
                });
                
                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        await removePendingEvent(event.id);
                        console.log('[SW] Pending event synced:', event.title);
                    }
                }
            } catch (error) {
                console.error('[SW] Failed to sync pending event:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Failed to sync pending events:', error);
    }
}

async function syncEventUpdates() {
    console.log('[SW] Syncing event updates...');
    
    // Similar implementation for event updates
    // This would handle pending edits/deletions
}

async function clearAllCaches() {
    const cacheNames = await caches.keys();
    return Promise.all(cacheNames.map(name => caches.delete(name)));
}

function triggerBackgroundSync() {
    // Request background sync
    return self.registration.sync.register('calendar-sync');
}

// IndexedDB helpers for offline data storage
async function getLastSyncTime() {
    // Implement IndexedDB access to get last sync time
    return localStorage.getItem('lastSync') || new Date(0).toISOString();
}

async function setLastSyncTime(timestamp) {
    // Implement IndexedDB access to set last sync time
    localStorage.setItem('lastSync', timestamp);
}

async function updateLocalCalendarCache(events) {
    // Update local IndexedDB with new events
    // Implementation would use IndexedDB for persistent storage
    const cache = await caches.open(DATA_CACHE);
    
    // Store events data
    const eventsResponse = new Response(JSON.stringify({ events }), {
        headers: { 'Content-Type': 'application/json' }
    });
    
    await cache.put('/api/events-cache', eventsResponse);
}

async function getPendingEvents() {
    // Get pending events from IndexedDB
    return JSON.parse(localStorage.getItem('pendingEvents') || '[]');
}

async function removePendingEvent(eventId) {
    // Remove synced event from pending list
    const pending = await getPendingEvents();
    const filtered = pending.filter(e => e.id !== eventId);
    localStorage.setItem('pendingEvents', JSON.stringify(filtered));
}

console.log('[SW] Service worker loaded successfully');