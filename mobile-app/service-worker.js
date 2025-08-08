const CACHE_NAME = 'nexio-pwa-v1.0.0';
const urlsToCache = [
  './',
  './index.html',
  './calendar.html',
  './tasks.html',
  './css/app.css',
  './js/app.js',
  './js/calendar.js',
  './js/tasks.js',
  './icons/icon-72.svg',
  './icons/icon-192.svg',
  './icons/icon-512.svg',
  './icons/apple-touch-icon.svg',
  './manifest.json',
  './offline.html'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(async (cache) => {
        console.log('Service Worker: Caching files');
        
        // Cache files individually to be more forgiving
        const cachePromises = urlsToCache.map(async (url) => {
          try {
            await cache.add(url);
            console.log('Service Worker: Cached', url);
          } catch (error) {
            console.warn('Service Worker: Failed to cache', url, error.message);
            // Continue caching other files even if one fails
          }
        });
        
        await Promise.allSettled(cachePromises);
        console.log('Service Worker: Finished caching process');
        return self.skipWaiting(); // Activate immediately
      })
      .catch((err) => {
        console.error('Service Worker: Cache process failed', err);
        // Still skip waiting to activate the service worker
        return self.skipWaiting();
      })
  );
});

// Activate event - cleanup old caches
self.addEventListener('activate', (event) => {
  console.log('Service Worker: Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Deleting old cache', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Activated');
      return self.clients.claim(); // Take control of all pages
    })
  );
});

// Fetch event - serve from cache with network fallback
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip requests to other origins
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Return cached version or fetch from network
        if (response) {
          console.log('Service Worker: Serving from cache', event.request.url);
          return response;
        }

        console.log('Service Worker: Fetching from network', event.request.url);
        return fetch(event.request).then((response) => {
          // Check if valid response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clone the response
          const responseToCache = response.clone();

          // Add to cache
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });

          return response;
        });
      })
      .catch((err) => {
        console.error('Service Worker: Fetch failed', err);
        
        // Return offline page for navigation requests
        if (event.request.mode === 'navigate') {
          return caches.match('./offline.html');
        }
      })
  );
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
  console.log('Service Worker: Background sync', event.tag);
  
  if (event.tag === 'calendar-sync') {
    event.waitUntil(syncCalendarData());
  }
  
  if (event.tag === 'tasks-sync') {
    event.waitUntil(syncTasksData());
  }
});

// Push notifications
self.addEventListener('push', (event) => {
  console.log('Service Worker: Push notification received');
  
  const options = {
    body: event.data ? event.data.text() : 'Nuova notifica da Nexio',
    icon: './icons/icon-192.png',
    badge: './icons/icon-72.png',
    vibrate: [200, 100, 200],
    tag: 'nexio-notification',
    actions: [
      {
        action: 'open',
        title: 'Apri',
        icon: './icons/icon-192.png'
      },
      {
        action: 'close',
        title: 'Chiudi'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('Nexio', options)
  );
});

// Notification click
self.addEventListener('notificationclick', (event) => {
  console.log('Service Worker: Notification clicked', event.action);
  
  event.notification.close();

  if (event.action === 'open') {
    event.waitUntil(
      clients.matchAll({ type: 'window' }).then((clientsList) => {
        // If app is already open, focus it
        for (const client of clientsList) {
          if (client.url.includes('mobile-app') && 'focus' in client) {
            return client.focus();
          }
        }
        // Otherwise open new window
        if (clients.openWindow) {
          return clients.openWindow('./index.html');
        }
      })
    );
  }
});

// Helper functions for background sync
async function syncCalendarData() {
  try {
    console.log('Service Worker: Syncing calendar data');
    // Implementation for calendar sync would go here
    return Promise.resolve();
  } catch (error) {
    console.error('Service Worker: Calendar sync failed', error);
    throw error;
  }
}

async function syncTasksData() {
  try {
    console.log('Service Worker: Syncing tasks data');
    // Implementation for tasks sync would go here
    return Promise.resolve();
  } catch (error) {
    console.error('Service Worker: Tasks sync failed', error);
    throw error;
  }
}