const CACHE_NAME = 'sidak-tejo-v13-enterprise';
const MAPS_CACHE_NAME = 'sidak-tejo-maps-v9';

// Assets to cache on install
const PRECACHE_ASSETS = [
    'plugins/leaflet.js',
    'plugins/leaflet.css',
    'plugins/bootstrap/css/bootstrap.min.css',
    'plugins/fontawesome-free/css/all.min.css',
    'assets/fonts/fonts.css',
    'assets/img/logo_sidak_optimized.webp',
    'assets/img/logo_sidak_optimized.jpg',
    'assets/img/logo_sidak.png',
    'assets/img/no-image.png'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch(err => {
                console.warn('Pre-cache asset warning:', err);
            });
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME && cacheName !== MAPS_CACHE_NAME) {
                        console.log('Purging old ServiceWorker cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Only intercept GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    const url = event.request.url;

    // Hard Rule: NEVER intercept HTML navigations, dynamic endpoints, APIs, or asset corpus
    if (
        event.request.mode === 'navigate' ||
        url.includes('/api/') ||
        url.includes('/asset-corpus/') ||
        url.includes('/ajax') ||
        url.includes('/temuan') ||
        url.includes('/dashboard') ||
        url.includes('/gis') ||
        url.includes('/master-') ||
        url.includes('/fault-locator') ||
        url.includes('/executive-dashboard') ||
        url.includes('/laporan') ||
        url.includes('/documents') ||
        url.includes('/login')
    ) {
        return;
    }

    // Only intercept map tiles, /plugins/, /assets/, /dist/
    const isMapTile = url.includes('basemaps.cartocdn.com') ||
                      url.includes('openstreetmap.org') ||
                      url.includes('raw.githubusercontent.com/pointhi/leaflet-color-markers') ||
                      url.includes('arcgisonline.com');
    const isStaticAsset = url.includes('/plugins/') || url.includes('/assets/') || url.includes('/dist/');

    if (!isMapTile && !isStaticAsset) {
        return;
    }

    // Cache strategy for Map Tiles
    if (isMapTile) {
        event.respondWith(
            caches.open(MAPS_CACHE_NAME).then((cache) => {
                return cache.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    
                    return fetch(event.request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            cache.put(event.request, networkResponse.clone());
                        }
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
                        if (networkResponse && networkResponse.status === 200) {
                            cache.put(event.request, networkResponse.clone());
                        }
                        return networkResponse;
                    });
                });
            }).catch(() => {
                return fetch(event.request).catch(() => new Response('', { status: 504, statusText: 'Gateway Timeout' }));
            })
        );
        return;
    }

    // Network-First for custom styles and scripts (/dist/) to prevent developer caching trap
    if (url.includes('/dist/')) {
        event.respondWith(
            fetch(event.request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(async () => {
                const cached = await caches.match(event.request);
                return cached || new Response('', { status: 503, statusText: 'Offline' });
            })
        );
        return;
    }
});
