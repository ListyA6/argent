/* ARGENT service worker — shell cache + push */

const CACHE = 'argent-v1';
const SHELL = [
  '/assets/app.css?v=1',
  '/assets/app.js?v=1',
  '/assets/sfx.js?v=1',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET') return;

  // never cache API or auth-dependent pages
  if (url.pathname.startsWith('/api') || url.pathname === '/' || url.pathname === '/export.csv'
      || url.pathname === '/setup' || url.pathname.startsWith('/cron')) {
    return; // network only
  }

  // cache-first for static shell + fonts
  e.respondWith(
    caches.match(e.request, { ignoreVary: true }).then((hit) => {
      if (hit) return hit;
      return fetch(e.request).then((res) => {
        if (res.ok && (url.origin === location.origin || url.host.includes('fonts.g'))) {
          const clone = res.clone();
          caches.open(CACHE).then((c) => c.put(e.request, clone));
        }
        return res;
      });
    })
  );
});

self.addEventListener('push', (e) => {
  let data = {};
  try { data = e.data.json(); } catch (err) {}
  e.waitUntil(
    self.registration.showNotification(data.title || 'Argent', {
      body: data.body || '',
      tag: data.tag || 'argent',
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: data.url || '/' },
      vibrate: [60, 30, 60],
    })
  );
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if ('focus' in c) return c.focus();
      }
      return clients.openWindow(e.notification.data?.url || '/');
    })
  );
});
