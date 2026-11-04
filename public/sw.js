// SIDAK TEJO Enterprise - Transition Worker
// Decommissions and immediately unregisters legacy sw.js
// NO fetch event listener to ensure zero request interference or Response conversion errors
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    self.registration.unregister().then(() => {
      return self.clients.claim();
    })
  );
});
