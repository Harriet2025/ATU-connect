// =========================================================
// ATU CONNECT — SERVICE WORKER
// Minimal app-shell cache so the PWA is installable and opens offline.
// =========================================================

// Bumped whenever app files change so the activate handler below purges any
// previously cached (now stale) copies on the user's device.
const CACHE_NAME = 'atu-connect-v4';
// Only truly static assets are precached. HTML pages and js/script.js are
// deliberately NOT precached — they change on every deploy, so we always
// fetch them fresh from the network (see the network-first handler below)
// and never risk serving a stale cached page/app to the user.
const APP_SHELL = [
  './manifest.json',
  './icons/icon.svg',
  './icons/icon-maskable.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))
    )
  );
  self.clients.claim();
});

// Network-first: this app changes frequently, so always prefer a fresh
// response when online (and refresh the cache with it) — only fall back to
// the cached copy when the network is unreachable (true offline support).
// A cache-first strategy would silently freeze the app on whatever was
// cached the very first time it was installed, ignoring all future updates.
//
// API calls (api/...) are never cached and never get the page-shell
// fallback: a failed API request must reject/fail normally so the
// frontend's own error handling runs. Serving cached HTML in place of a
// failed JSON response would make apiFetch() try to JSON.parse() a page
// of markup, fail, and show a false "could not load" error even though
// the failure was just a transient network blip, not a real API error.
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  if (url.pathname.includes('/api/')) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(res => {
        const resClone = res.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, resClone));
        return res;
      })
      .catch(() => caches.match(event.request).then(cached =>
        cached || (event.request.mode === 'navigate' ? caches.match('./dashboard.html') : undefined)
      ))
  );
});
