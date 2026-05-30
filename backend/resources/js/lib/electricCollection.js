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
 * @param {string} id      stable collection id (the shape name)
 * @param {{url: string, params: object}} config  gatekeeper output (url absolutized)
 * @param {(row: any) => string|number} [getKey]  defaults to row.id
 */
export function electricCollection(id, config, getKey = (row) => row.id) {
    return createCollection(
        electricCollectionOptions({
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
    );
}
