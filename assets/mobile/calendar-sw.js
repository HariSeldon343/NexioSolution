/**
 * Service Worker per Nexio Calendar PWA
 * Gestisce cache offline e sincronizzazione
 */

const CACHE_NAME = 'nexio-calendar-v1.0.0';
const STATIC_CACHE = 'nexio-static-v1.0.0';
const DYNAMIC_CACHE = 'nexio-dynamic-v1.0.0';

// File statici da cachare
const STATIC_FILES = [
    '/piattaforma-collaborativa/calendario-eventi-mobile.php',
    '/piattaforma-collaborativa/assets/mobile/calendar-mobile.css',
    '/piattaforma-collaborativa/assets/mobile/calendar-mobile.js',
    '/piattaforma-collaborativa/manifest.json',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
];

// Pattern URL da cachare dinamicamente
const DYNAMIC_PATTERNS = [
    /\/piattaforma-collaborativa\/backend\/api\//,
    /\/piattaforma-collaborativa\/calendario-eventi\.php/,
    /\.css$/,
    /\.js$/,
    /\.woff2$/,
    /\.png$/,
    /\.jpg$/,
    /\.jpeg$/,
    /\.svg$/
];

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('[SW] Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static files');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                console.log('[SW] Static files cached');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('[SW] Error caching static files:', error);
            })
    );
});

// Activate event - cleanup old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && 
                            cacheName !== DYNAMIC_CACHE && 
                            cacheName !== CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Cache cleanup completed');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external requests (except CDN resources)
    if (!url.origin.includes('localhost') && 
        !url.origin.includes('nexiosolution.it') &&
        !url.origin.includes('cdnjs.cloudflare.com') &&
        !url.origin.includes('fonts.googleapis.com')) {
        return;
    }
    
    // Handle calendar API requests
    if (url.pathname.includes('/backend/api/calendar-events.php')) {
        event.respondWith(handleCalendarAPI(request));
        return;
    }
    
    // Handle static files
    if (isStaticFile(url.pathname)) {
        event.respondWith(handleStaticFile(request));
        return;
    }
    
    // Handle dynamic content
    if (isDynamicContent(url.pathname)) {
        event.respondWith(handleDynamicContent(request));
        return;
    }
    
    // Default: network first
    event.respondWith(
        fetch(request)
            .catch(() => {
                // If network fails, try cache
                return caches.match(request);
            })
    );
});

// Handle calendar API requests with offline support
async function handleCalendarAPI(request) {
    const url = new URL(request.url);
    
    try {
        // Try network first
        const response = await fetch(request);
        
        if (response.ok) {
            // Cache successful GET responses
            if (request.method === 'GET') {
                const cache = await caches.open(DYNAMIC_CACHE);
                cache.put(request, response.clone());
            }
            return response;
        }
        
        // If network response is not ok, try cache
        return await getCachedResponse(request);
        
    } catch (error) {
        console.log('[SW] Network failed for API request, trying cache');
        
        // Network failed, try cache
        const cachedResponse = await getCachedResponse(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response for calendar events
        if (url.pathname.includes('calendar-events.php')) {
            return new Response(JSON.stringify({
                success: true,
                events: [],
                offline: true,
                message: 'Dati offline non disponibili'
            }), {
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
        }
        
        throw error;
    }
}

// Handle static files with cache first strategy
async function handleStaticFile(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // If not in cache, fetch from network
        const response = await fetch(request);
        
        if (response.ok) {
            // Cache the response
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        
        return response;
        
    } catch (error) {
        console.error('[SW] Failed to fetch static file:', request.url);
        throw error;
    }
}

// Handle dynamic content with network first strategy
async function handleDynamicContent(request) {
    try {
        // Try network first
        const response = await fetch(request);
        
        if (response.ok) {
            // Cache the response
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        
        return response;
        
    } catch (error) {
        // Network failed, try cache
        const cachedResponse = await getCachedResponse(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        throw error;
    }
}

// Helper function to get cached response
async function getCachedResponse(request) {
    const staticCache = await caches.open(STATIC_CACHE);
    const dynamicCache = await caches.open(DYNAMIC_CACHE);
    
    return await staticCache.match(request) || await dynamicCache.match(request);
}

// Check if URL is a static file
function isStaticFile(pathname) {
    return pathname.includes('.css') ||
           pathname.includes('.js') ||
           pathname.includes('.png') ||
           pathname.includes('.jpg') ||
           pathname.includes('.jpeg') ||
           pathname.includes('.svg') ||
           pathname.includes('.woff') ||
           pathname.includes('.woff2') ||
           pathname.includes('manifest.json');
}

// Check if URL is dynamic content
function isDynamicContent(pathname) {
    return DYNAMIC_PATTERNS.some(pattern => pattern.test(pathname));
}

// Handle background sync for offline actions
self.addEventListener('sync', event => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'calendar-sync') {
        event.waitUntil(syncCalendarData());
    }
});

// Sync calendar data when online
async function syncCalendarData() {
    try {
        console.log('[SW] Syncing calendar data...');
        
        // This would typically sync with IndexedDB and send pending changes
        // Implementation depends on the specific offline data structure
        
        // For now, just clear old cache entries
        const cache = await caches.open(DYNAMIC_CACHE);
        const keys = await cache.keys();
        
        // Remove old API responses to force fresh data
        const apiRequests = keys.filter(request => 
            request.url.includes('/backend/api/calendar-events.php')
        );
        
        await Promise.all(apiRequests.map(request => cache.delete(request)));
        
        console.log('[SW] Calendar sync completed');
        
    } catch (error) {
        console.error('[SW] Calendar sync failed:', error);
    }
}

// Handle push notifications (future feature)
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body || 'Nuovo evento nel calendario',
        icon: '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
        badge: '/piattaforma-collaborativa/assets/images/nexio-icon.svg',
        vibrate: [200, 100, 200],
        data: {
            url: data.url || '/piattaforma-collaborativa/calendario-eventi-mobile.php'
        },
        actions: [
            {
                action: 'view',
                title: 'Visualizza',
                icon: '/piattaforma-collaborativa/assets/images/nexio-icon.svg'
            },
            {
                action: 'close',
                title: 'Chiudi'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'Nexio Calendar', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'view' || !event.action) {
        const url = event.notification.data?.url || '/piattaforma-collaborativa/calendario-eventi-mobile.php';
        
        event.waitUntil(
            clients.openWindow(url)
        );
    }
});

// Handle messages from the main thread
self.addEventListener('message', event => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'CACHE_CALENDAR_DATA':
            event.waitUntil(cacheCalendarData(data));
            break;
            
        case 'CLEAR_CACHE':
            event.waitUntil(clearAllCaches());
            break;
            
        default:
            console.log('[SW] Unknown message type:', type);
    }
});

// Cache calendar data from main thread
async function cacheCalendarData(data) {
    try {
        const cache = await caches.open(DYNAMIC_CACHE);
        const response = new Response(JSON.stringify(data), {
            headers: { 'Content-Type': 'application/json' }
        });
        
        await cache.put('/calendar-data', response);
        console.log('[SW] Calendar data cached');
        
    } catch (error) {
        console.error('[SW] Failed to cache calendar data:', error);
    }
}

// Clear all caches
async function clearAllCaches() {
    try {
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames.map(name => caches.delete(name)));
        console.log('[SW] All caches cleared');
        
    } catch (error) {
        console.error('[SW] Failed to clear caches:', error);
    }
}