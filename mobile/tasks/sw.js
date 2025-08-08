/**
 * Nexio Tasks PWA - Service Worker
 * Enables offline functionality, caching, and push notifications
 */

const CACHE_NAME = 'nexio-tasks-v1.0.0';
const API_CACHE_NAME = 'nexio-tasks-api-v1.0.0';
const OFFLINE_URL = 'offline.html';

// Files to cache for offline functionality
const STATIC_CACHE_URLS = [
    '/piattaforma-collaborativa/mobile/tasks/',
    '/piattaforma-collaborativa/mobile/tasks/index.html',
    '/piattaforma-collaborativa/mobile/tasks/app.js',
    '/piattaforma-collaborativa/mobile/tasks/styles.css',
    '/piattaforma-collaborativa/mobile/tasks/manifest.json',
    '/piattaforma-collaborativa/assets/vendor/bootstrap/css/bootstrap.min.css',
    '/piattaforma-collaborativa/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
    '/piattaforma-collaborativa/assets/vendor/fontawesome/css/all.min.css',
    '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
    '/piattaforma-collaborativa/assets/images/nexio-logo.svg',
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js'
];

// API endpoints to cache
const API_CACHE_URLS = [
    '/piattaforma-collaborativa/backend/api/task-mobile-api.php'
];

// Install event - cache static resources
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    
    event.waitUntil(
        Promise.all([
            // Cache static resources
            caches.open(CACHE_NAME).then((cache) => {
                console.log('[SW] Caching static resources');
                return cache.addAll(STATIC_CACHE_URLS.map(url => {
                    return new Request(url, { cache: 'reload' });
                }));
            }),
            // Skip waiting to activate immediately
            self.skipWaiting()
        ])
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Take control of all pages immediately
            self.clients.claim()
        ])
    );
});

// Fetch event - handle network requests with caching strategy
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Handle different types of requests with appropriate caching strategies
    if (request.method === 'GET') {
        if (isStaticResource(request)) {
            // Static resources: Cache First strategy
            event.respondWith(cacheFirst(request));
        } else if (isApiRequest(request)) {
            // API requests: Network First with offline fallback
            event.respondWith(networkFirstWithOfflineSupport(request));
        } else if (request.mode === 'navigate') {
            // Navigation requests: Network First with offline page fallback
            event.respondWith(handleNavigation(request));
        }
    } else if (request.method === 'POST' || request.method === 'PUT' || request.method === 'DELETE') {
        // Mutation requests: Network Only with offline queue
        event.respondWith(handleMutation(request));
    }
});

// Check if request is for static resources
function isStaticResource(request) {
    const url = request.url;
    return STATIC_CACHE_URLS.some(staticUrl => url.includes(staticUrl)) ||
           url.includes('.css') ||
           url.includes('.js') ||
           url.includes('.png') ||
           url.includes('.jpg') ||
           url.includes('.svg') ||
           url.includes('.woff') ||
           url.includes('.woff2');
}

// Check if request is for API endpoints
function isApiRequest(request) {
    return request.url.includes('/backend/api/') || 
           request.url.includes('task-mobile-api.php');
}

// Cache First strategy for static resources
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('[SW] Cache first failed:', error);
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

// Network First strategy with offline support for API requests
async function networkFirstWithOfflineSupport(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful API responses
            const cache = await caches.open(API_CACHE_NAME);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        
        // If network fails, try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network first failed, trying cache:', error);
        
        // Network failed, try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline data for specific endpoints
        if (request.url.includes('task-mobile-api.php')) {
            return createOfflineApiResponse(request);
        }
        
        return new Response(
            JSON.stringify({ error: 'Offline', message: 'No cached data available' }),
            {
                status: 503,
                statusText: 'Service Unavailable',
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Handle navigation requests
async function handleNavigation(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        console.log('[SW] Navigation failed, showing offline page');
        const cache = await caches.open(CACHE_NAME);
        const cachedResponse = await cache.match('/piattaforma-collaborativa/mobile/tasks/index.html');
        return cachedResponse || new Response('App is offline', { 
            status: 200, 
            headers: { 'Content-Type': 'text/html' }
        });
    }
}

// Handle mutation requests (POST, PUT, DELETE)
async function handleMutation(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        console.log('[SW] Mutation failed, queuing for later sync:', error);
        
        // Queue the request for background sync
        await queueMutationRequest(request);
        
        return new Response(
            JSON.stringify({ 
                success: false, 
                error: 'Offline', 
                message: 'Request queued for sync when online',
                queued: true 
            }),
            {
                status: 202,
                statusText: 'Accepted',
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Create offline API response with cached data
function createOfflineApiResponse(request) {
    const url = new URL(request.url);
    const action = url.searchParams.get('action') || 'tasks';
    
    let offlineData = {};
    
    switch (action) {
        case 'tasks':
            offlineData = {
                success: true,
                tasks: [],
                offline: true,
                message: 'Offline mode - showing cached tasks'
            };
            break;
        case 'kanban_board':
            offlineData = {
                success: true,
                kanban: {
                    todo: [],
                    in_progress: [],
                    done: []
                },
                offline: true,
                message: 'Offline mode - showing cached kanban board'
            };
            break;
        case 'statistics':
            offlineData = {
                success: true,
                stats: {
                    total: 0,
                    todo: 0,
                    in_progress: 0,
                    completed: 0,
                    completion_rate: 0,
                    overdue: 0,
                    avg_completion_days: 0
                },
                offline: true,
                message: 'Offline mode - showing cached statistics'
            };
            break;
        default:
            offlineData = {
                success: false,
                error: 'Offline',
                message: 'Action not available offline'
            };
    }
    
    return new Response(JSON.stringify(offlineData), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
    });
}

// Queue mutation requests for background sync
async function queueMutationRequest(request) {
    try {
        const requestData = {
            url: request.url,
            method: request.method,
            headers: [...request.headers.entries()],
            body: request.method !== 'GET' ? await request.text() : null,
            timestamp: Date.now()
        };
        
        // Store in IndexedDB for persistence
        const db = await openDB();
        const tx = db.transaction(['sync_queue'], 'readwrite');
        const store = tx.objectStore('sync_queue');
        await store.add(requestData);
        
        console.log('[SW] Queued request for sync:', requestData);
    } catch (error) {
        console.error('[SW] Failed to queue request:', error);
    }
}

// Open IndexedDB for sync queue
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('nexio-tasks-sync', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('sync_queue')) {
                const store = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp');
            }
        };
    });
}

// Background Sync event
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync event:', event.tag);
    
    if (event.tag === 'nexio-tasks-sync') {
        event.waitUntil(syncQueuedRequests());
    }
});

// Sync queued requests when back online
async function syncQueuedRequests() {
    try {
        const db = await openDB();
        const tx = db.transaction(['sync_queue'], 'readonly');
        const store = tx.objectStore('sync_queue');
        const requests = await store.getAll();
        
        console.log(`[SW] Syncing ${requests.length} queued requests`);
        
        for (const requestData of requests) {
            try {
                const request = new Request(requestData.url, {
                    method: requestData.method,
                    headers: new Headers(requestData.headers),
                    body: requestData.body
                });
                
                const response = await fetch(request);
                
                if (response.ok) {
                    // Remove from queue on successful sync
                    const deleteTx = db.transaction(['sync_queue'], 'readwrite');
                    const deleteStore = deleteTx.objectStore('sync_queue');
                    await deleteStore.delete(requestData.id);
                    
                    console.log('[SW] Successfully synced request:', requestData.id);
                } else {
                    console.error('[SW] Failed to sync request:', requestData.id, response.status);
                }
            } catch (error) {
                console.error('[SW] Error syncing request:', requestData.id, error);
            }
        }
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

// Push event for notifications
self.addEventListener('push', (event) => {
    console.log('[SW] Push received:', event);
    
    let notificationData = {
        title: 'Nexio Tasks',
        body: 'Nuovo aggiornamento disponibile',
        icon: '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
        badge: '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
        tag: 'nexio-tasks-notification',
        requireInteraction: true,
        actions: [
            {
                action: 'open',
                title: 'Apri App',
                icon: '/piattaforma-collaborativa/assets/images/nexio-icon.svg'
            },
            {
                action: 'dismiss',
                title: 'Chiudi'
            }
        ]
    };
    
    if (event.data) {
        try {
            const pushData = event.data.json();
            notificationData = { ...notificationData, ...pushData };
        } catch (error) {
            console.error('[SW] Failed to parse push data:', error);
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
    );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event);
    
    event.notification.close();
    
    if (event.action === 'open' || !event.action) {
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((clientList) => {
                    // If app is already open, focus it
                    for (const client of clientList) {
                        if (client.url.includes('mobile/tasks') && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    
                    // Otherwise open new window
                    if (clients.openWindow) {
                        return clients.openWindow('/piattaforma-collaborativa/mobile/tasks/');
                    }
                })
        );
    }
});

// Message event for communication with app
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    } else if (event.data && event.data.type === 'FORCE_SYNC') {
        syncQueuedRequests();
    }
});

// Periodic Background Sync (if supported)
if ('periodicSync' in self.registration) {
    self.addEventListener('periodicsync', (event) => {
        console.log('[SW] Periodic sync event:', event.tag);
        
        if (event.tag === 'nexio-tasks-periodic-sync') {
            event.waitUntil(syncQueuedRequests());
        }
    });
}

// Error event handling
self.addEventListener('error', (event) => {
    console.error('[SW] Error:', event.error);
});

self.addEventListener('unhandledrejection', (event) => {
    console.error('[SW] Unhandled promise rejection:', event.reason);
    event.preventDefault();
});

console.log('[SW] Service worker loaded successfully');