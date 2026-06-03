import { createCollection } from '@tanstack/react-db';
import { electricCollectionOptions } from '@tanstack/electric-db-collection';
import { FetchError } from '@electric-sql/client';
import { fetchShapeConfig } from './useShapeConfigs';

/**
 * Build a read-only TanStack DB collection from a signed gatekeeper config.
 *
 * The gatekeeper (`GET /api/shapes/{name}`) returns `{ url, params }` where
 * params carries the HMAC-signed table/columns/where. That object IS Electric's
 * `shapeOptions`, so it drops straight in — the collection streams from Electric
 * through Caddy, never holding a PHP worker.
 *
 * Read-only by design: no onInsert/onUpdate/onDelete. Writes go through Laravel
 * (write-through, then the committed row syncs back here) — never optimistic,
 * because MES data must reflect only confirmed state.
 *
 * gcTime is short on purpose: a collection stops its Electric stream ~1s after
 * its last subscriber unmounts. TanStack DB's default is 5 MINUTES, which means
 * every admin page you navigate away from would keep holding its long-poll for
 * 5 min — click through a handful of pages and you blow past the browser's ~6
 * concurrent connections (HTTP/1.1), freezing the whole origin. Releasing on
 * unmount keeps the held-connection count bounded to what's actually on screen.
 * (gcTime <= 0 would mean "never GC", so we use a small positive value.)
 *
 * @param {string} id      stable collection id (the shape name)
 * @param {{url: string, params: object}} config  gatekeeper output (url absolutized)
 * @param {(row: any) => string|number} [getKey]  defaults to row.id
 */
export function electricCollection(id, config, getKey = (row) => row.id) {
    return createCollection({
        gcTime: 1000, // release the Electric stream ~1s after the last subscriber unmounts
        ...electricCollectionOptions({
            id,
            shapeOptions: {
                url: config.url,
                params: config.params,
                // The gatekeeper signs configs with a ~1h TTL. When the signature
                // expires the stream gets a 403; transparently re-mint a fresh
                // signed config (same-origin session auth) and resume — otherwise
                // the page would silently stop live-syncing after an hour.
                onError: async (err) => {
                    if (err instanceof FetchError && err.status === 403) {
                        try {
                            const fresh = await fetchShapeConfig(id);
                            return { params: fresh.params };
                        } catch {
                            return; // give up; surfaces as a stopped stream
                        }
                    }
                    return; // other errors: let Electric's default backoff handle it
                },
            },
            getKey,
        }),
    });
}
