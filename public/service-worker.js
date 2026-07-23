const CACHE_NAME = 'sidak-tejo-v3';
const MAPS_CACHE_NAME = 'sidak-tejo-maps-v2';

// Assets to cache on install
const PRECACHE_ASSETS = [
    '/plugins/leaflet.js',
    '/plugins/leaflet.css',
    '/assets/img/logo_sidak.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch(err => {
                console.warn('Pre-cache asset warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME && cacheName !== MAPS_CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = event.request.url;

    // Cache strategy for Map Tiles
    if (url.includes('basemaps.cartocdn.com') || url.includes('openstreetmap.org') || url.includes('raw.githubusercontent.com/pointhi/leaflet-color-markers')) {
        event.respondWith(
            caches.open(MAPS_CACHE_NAME).then((cache) => {
                return cache.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    
                    return fetch(event.request).then((networkResponse) => {
                        cache.put(event.request, networkResponse.clone());
                        return networkResponse;
                    }).catch(() => {
                        return new Response('', { status: 408, statusText: 'Offline Map Tile' });
                    });
                });
            })
        );
        return;
    }

    // Cache-First for static third-party libraries (plugins) and standard images (assets)
    if (url.includes('/plugins/') || url.includes('/assets/')) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }

                return caches.open(CACHE_NAME).then((cache) => {
                    return fetch(event.request).then((networkResponse) => {
                        if (networkResponse.status === 200) {
                            cache.put(event.request, networkResponse.clone());
                        }
                        return networkResponse;
                    });
                });
            }).catch(() => {
                return fetch(event.request);
            })
        );
        return;
    }

    // Network-First for custom styles and scripts (/dist/) to prevent developer caching trap
    if (url.includes('/dist/')) {
        event.respondWith(
            fetch(event.request).then((networkResponse) => {
                if (networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(() => {
                return caches.match(event.request);
            })
        );
        return;
    }

    // Default network-first falling back to cache for other GET requests
    if (event.request.method === 'GET') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(event.request);
            })
        );
    }
});
