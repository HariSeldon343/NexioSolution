const CACHE_NAME = 'nexio-mobile-v1';
const urlsToCache = [
  '/piattaforma-collaborativa/mobile/',
  '/piattaforma-collaborativa/mobile/login.php',
  '/piattaforma-collaborativa/mobile/manifest.json',
  '/piattaforma-collaborativa/assets/images/nexio-icon.svg'
];

// Install event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Fetch event
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch new
        return response || fetch(event.request);
      })
      .catch(() => {
        // If offline and no cache, return offline page
        if (event.request.destination === 'document') {
          return caches.match('/piattaforma-collaborativa/mobile/offline.html');
        }
      })
  );
});

// Activate event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});