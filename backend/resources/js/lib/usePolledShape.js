import { useEffect, useRef, useState } from 'react';
import { ShapeStream, isChangeMessage, isControlMessage } from '@electric-sql/client';
import { fetchShapeConfig } from './useShapeConfigs';

/**
 * One-shot, NON-LIVE read of a shape: fetch the current rows once and resolve.
 * `subscribe: false` tells Electric to "sync the current shape and stop", so the
 * connection closes immediately — NOT a held long-poll. The polling hook ticks
 * on this.
 */
export function fetchShapeSnapshot(config) {
    return new Promise((resolve, reject) => {
        const rows = new Map();
        const stream = new ShapeStream({ ...config, subscribe: false });
        const unsubscribe = stream.subscribe(
            (messages) => {
                for (const msg of messages) {
                    if (isChangeMessage(msg)) {
                        if (msg.headers.operation === 'delete') rows.delete(msg.key);
                        else rows.set(msg.key, msg.value);
                    } else if (isControlMessage(msg) && msg.headers.control === 'up-to-date') {
                        unsubscribe();
                        resolve([...rows.values()]);
                    }
                }
            },
            (err) => {
                unsubscribe();
                reject(err);
            },
        );
    });
}

/**
 * Poll a shape on an interval instead of holding a live connection.
 *
 * Each tick is a SHORT one-shot fetch that frees the connection the moment it
 * returns (~1% duty cycle at 5s), so many polled shapes run at once WITHOUT
 * tripping the browser's ~6-per-origin HTTP/1.1 limit — unlike live shapes,
 * which hold a connection continuously. Picks up writes on the next tick (works
 * for MUTABLE data); writes still go through Laravel.
 *
 * The gatekeeper signs configs with a 1h TTL. When a tick fails (likely an
 * expired signature → 403), we re-mint a fresh signed config by `name` and
 * retry — so a long-open tab keeps polling instead of silently going stale.
 *
 * Returns { data, isLoading }. Usually used via useSyncedShape, not directly.
 */
export function usePolledShape(name, config, intervalMs = 5000) {
    const [data, setData] = useState(null);
    const cfgRef = useRef(config);
    cfgRef.current = config; // always poll with the latest config the page passed

    useEffect(() => {
        if (!config) return undefined;
        let cancelled = false;
        let inFlight = false;

        const tick = async () => {
            if (inFlight) return; // never stack ticks if one runs long
            inFlight = true;
            try {
                const rows = await fetchShapeSnapshot(cfgRef.current);
                if (!cancelled) setData(rows);
            } catch {
                // Signature likely expired (1h TTL) → re-mint and retry next tick.
                if (name) {
                    try {
                        cfgRef.current = await fetchShapeConfig(name);
                    } catch {
                        /* gatekeeper down — retry next tick */
                    }
                }
            } finally {
                inFlight = false;
            }
        };

        tick(); // immediate first load, then on the interval
        const timer = setInterval(tick, intervalMs);
        return () => {
            cancelled = true;
            clearInterval(timer);
        };
        // config flows through cfgRef; restart only on a different shape/cadence.
    }, [name, intervalMs]);

    return { data: data ?? [], isLoading: data === null };
}
