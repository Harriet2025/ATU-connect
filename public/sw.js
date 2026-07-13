// =========================================================
// ATU CONNECT — SERVICE WORKER
// Minimal app-shell cache so the PWA is installable and opens offline.
// =========================================================

// Bumped whenever app files change so the activate handler below purges any
// previously cached (now stale) copies on the user's device.
const CACHE_NAME = 'atu-connect-v2';
const APP_SHELL = [
  './index.html',
  './login.html',
  './dashboard.html',
  './announcements.html',
  './assignments.html',
  './courses.html',
  './messages.html',
  './calendar.html',
  './notice-board.html',
  './notifications.html',
  './profile.html',
  './student.html',
  './lecturer.html',
  './admin.html',
  './css/style.css',
  './js/script.js',
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
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    fetch(event.request)
      .then(res => {
        const resClone = res.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, resClone));
        return res;
      })
      .catch(() => caches.match(event.request).then(cached => cached || caches.match('./dashboard.html')))
  );
});
