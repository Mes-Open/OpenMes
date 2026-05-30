// OpenMES no longer uses a service worker — the app is online-only (no offline
// mode) and live data comes from Electric SQL, so the old offline-precache SW
// was removed. This file is intentionally a self-destruct kill-switch: any
// browser still running the previous service worker will, on its next update
// check, install this version, delete all caches, unregister itself, and reload
// open tabs — clearing the stale cache that could otherwise intercept/hang
// requests. Safe to delete this file once no clients hold the old SW.

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const keys = await caches.keys();
                await Promise.all(keys.map((k) => caches.delete(k)));
                await self.registration.unregister();
                const clients = await self.clients.matchAll({ type: 'window' });
                clients.forEach((c) => c.navigate(c.url));
            } catch {
                /* best-effort cleanup */
            }
        })(),
    );
});

// Never intercept fetches — always go straight to the network.
