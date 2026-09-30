// Minimal app-shell service worker for the Jewelry Invoice & CRM PWA.
// Strategy:
//  - Navigations (Inertia page loads): network-first, falling back to the
//    last cached copy of that page, then to /offline.html.
//  - Static assets (built JS/CSS/fonts/images/icons): stale-while-revalidate.
//  - Anything else (POST/PUT/DELETE, external origins): pass straight through.

const CACHE_VERSION = 'jewel-crm-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll([OFFLINE_URL])),
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_VERSION)
            .map((key) => caches.delete(key)),
        ),
      ),
  );
  self.clients.claim();
});

function isStaticAsset(request) {
  return ['style', 'script', 'font', 'image'].includes(request.destination);
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(
          () =>
            caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)),
        ),
    );
    return;
  }

  if (isStaticAsset(request)) {
    event.respondWith(
      caches.open(CACHE_VERSION).then((cache) =>
        cache.match(request).then((cached) => {
          const networkFetch = fetch(request)
            .then((response) => {
              cache.put(request, response.clone());
              return response;
            })
            .catch(() => cached);

          return cached || networkFetch;
        }),
      ),
    );
  }
});
