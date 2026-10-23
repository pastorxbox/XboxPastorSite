/* Pastor Xbox service worker — cache leve + Web Push */
const CACHE = 'px-shell-v1';
const SHELL = [
  '/',
  '/manifest.webmanifest',
  '/assets/img/pwa-icon-192.png',
  '/assets/img/pastorxbox-avatar.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  if (url.pathname.startsWith('/api/')) {
    return;
  }
  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok && (url.pathname === '/' || url.pathname.startsWith('/assets/'))) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(req, copy));
        }
        return res;
      })
      .catch(() => caches.match(req).then((cached) => cached || caches.match('/')))
  );
});

self.addEventListener('push', (event) => {
  let data = {
    title: 'Pastor Xbox',
    body: 'Nova notícia publicada',
    url: '/',
    icon: '/assets/img/pwa-icon-192.png',
  };
  try {
    if (event.data) {
      const parsed = event.data.json();
      data = Object.assign(data, parsed || {});
    }
  } catch (err) {
    try {
      const text = event.data && event.data.text();
      if (text) {
        data.body = text;
      }
    } catch (e2) {
      // keep defaults
    }
  }

  event.waitUntil(
    self.registration.showNotification(String(data.title || 'Pastor Xbox'), {
      body: String(data.body || ''),
      icon: String(data.icon || '/assets/img/pwa-icon-192.png'),
      badge: '/assets/img/pwa-icon-192.png',
      data: { url: String(data.url || '/') },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsArr) => {
      for (const client of clientsArr) {
        if ('focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(target);
      }
      return undefined;
    })
  );
});
