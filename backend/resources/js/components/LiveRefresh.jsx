import { useEffect, useRef } from 'react';
import ShapeChangeWatcher from './ShapeChangeWatcher';
import { detectMultiplexed } from '../lib/transport';

/**
 * Cross-window live refresh for SERVER-PROP pages that works on BOTH http and
 * https — the reusable form of the schedule planner's sync.
 *
 *  - Always SHORT-POLLS `pollUrl` (an endpoint returning `{ last_updated }`)
 *    every `intervalMs` and calls `onRefresh` when the value changes. A short
 *    fetch is NOT a held connection, so it never trips HTTP/1.1's ~6-connection
 *    limit — reliable on plain http://localhost.
 *  - On HTTP/2 it ALSO mounts an Electric `ShapeChangeWatcher` for INSTANT push
 *    (skipped on HTTP/1.1 so it doesn't hold a long-poll connection there).
 *
 * `onRefresh` should be idempotent and guarded by the caller (e.g. skip while
 * the user is mid-drag/save, then flush).
 *
 * Props:
 *   pollUrl   — JSON endpoint returning { last_updated } (required for the poll)
 *   shape     — Electric shape to watch on HTTP/2 (default work_orders_all)
 *   intervalMs— poll cadence (default 10000)
 *   enabled   — gate the whole thing
 *   onRefresh — called when a change is detected
 */
export default function LiveRefresh({
    pollUrl,
    shape = 'work_orders_all',
    intervalMs = 10000,
    enabled = true,
    onRefresh,
}) {
    const cbRef = useRef(onRefresh);
    cbRef.current = onRefresh;
    const lastUpdate = useRef(null);
    const h2 = detectMultiplexed();

    useEffect(() => {
        if (!enabled || !pollUrl) return undefined;
        const tick = async () => {
            try {
                const r = await fetch(pollUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) return;
                const d = await r.json();
                if (!d.last_updated) return;
                // First tick just seeds the baseline (don't refresh on mount).
                if (lastUpdate.current === null) { lastUpdate.current = d.last_updated; return; }
                if (d.last_updated !== lastUpdate.current) {
                    lastUpdate.current = d.last_updated;
                    cbRef.current?.();
                }
            } catch { /* silent — try again next tick */ }
        };
        const t = setInterval(tick, intervalMs);
        return () => clearInterval(t);
    }, [enabled, pollUrl, intervalMs]);

    // Instant push on HTTP/2 only (avoids a held long-poll on HTTP/1.1).
    if (!enabled || !h2) return null;
    return <ShapeChangeWatcher shape={shape} onChange={() => cbRef.current?.()} />;
}
