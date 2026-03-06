const CACHE = 'openmmes-v1';
const OFFLINE_URL = '/offline';

const PRECACHE = [
    '/',
    '/offline',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE).then(cache => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Skip non-same-origin and Vite HMR
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/@') || url.pathname.startsWith('/node_modules')) return;

    // Network-first for HTML (always fresh data)
    if (event.request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(event.request).catch(() =>
                caches.match(OFFLINE_URL) ?? caches.match('/')
            )
        );
        return;
    }

    // Cache-first for static assets (CSS, JS, images)
    if (url.pathname.startsWith('/build/') || url.pathname.match(/\.(png|ico|svg|woff2?)$/)) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
    }
});
