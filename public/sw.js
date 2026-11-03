// SIDAK TEJO Enterprise - Transition Worker
// Safely unregisters legacy sw.js to prevent 'TypeError: Failed to convert value to Response'
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

self.addEventListener('fetch', () => {
  // Pass-through without trapping or rejecting
  return;
});
