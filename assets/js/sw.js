// Justbit WP Starter — Service Worker
//
// Strategia:
//   - Cache-first per assets (/wp-content/themes/*.css|js|woff2|png|svg, /wp-includes/*)
//   - Network-first per HTML (con fallback alla cache se offline, infine /offline)
//   - Stale-while-revalidate per API/REST (immediata risposta cache + refresh)
//
// Versione: bumpa CACHE_VERSION per invalidare tutte le caches al deploy
// successivo. Auto-cleanup nelle vecchie versioni a activate event.

const CACHE_VERSION = 'jbw-v1';
const ASSET_CACHE   = `${CACHE_VERSION}-assets`;
const PAGE_CACHE    = `${CACHE_VERSION}-pages`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

const OFFLINE_URL = '/offline';

// Pre-cache della shell minima: home + offline page
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(PAGE_CACHE).then((cache) =>
      cache.addAll(['/', OFFLINE_URL]).catch(() => {})
    ).then(() => self.skipWaiting())
  );
});

// Cleanup vecchie cache version
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names
          .filter((n) => !n.startsWith(CACHE_VERSION))
          .map((n) => caches.delete(n))
      )
    ).then(() => self.clients.claim())
  );
});

// Routing
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  if (!req.url.startsWith(self.location.origin)) return; // no cross-origin

  const url = new URL(req.url);

  // ── Skip endpoints "dinamici" (admin, REST, search, ecc.)
  if (url.pathname.startsWith('/wp-admin/')) return;
  if (url.pathname.startsWith('/wp-login.php')) return;
  if (url.pathname.startsWith('/wp-json/') && !url.pathname.includes('/og-image')) return;
  if (url.search.includes('preview=true')) return;

  // ── Assets statici → cache-first
  if (/\.(?:css|js|woff2?|png|jpg|jpeg|webp|avif|svg|ico|gif)$/i.test(url.pathname)) {
    event.respondWith(cacheFirst(req, ASSET_CACHE));
    return;
  }

  // ── HTML/navigation → network-first con fallback offline
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(networkFirstWithOfflineFallback(req));
    return;
  }

  // ── Default → stale-while-revalidate
  event.respondWith(staleWhileRevalidate(req, RUNTIME_CACHE));
});

async function cacheFirst(req, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(req);
  if (cached) return cached;
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(req, res.clone());
    return res;
  } catch (e) {
    return new Response('', { status: 504 });
  }
}

async function networkFirstWithOfflineFallback(req) {
  try {
    const res = await fetch(req);
    if (res.ok) {
      const cache = await caches.open(PAGE_CACHE);
      cache.put(req, res.clone());
    }
    return res;
  } catch (e) {
    const cached = await caches.match(req);
    if (cached) return cached;
    const offline = await caches.match(OFFLINE_URL);
    return offline || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
  }
}

async function staleWhileRevalidate(req, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(req);
  const network = fetch(req).then((res) => {
    if (res.ok) cache.put(req, res.clone());
    return res;
  }).catch(() => null);
  return cached || network || new Response('', { status: 504 });
}
