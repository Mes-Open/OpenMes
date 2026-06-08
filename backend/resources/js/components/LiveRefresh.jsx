import { useEffect, useRef } from 'react';
import ShapeChangeWatcher from './ShapeChangeWatcher';

/**
 * Cross-window live refresh for SERVER-PROP pages — the reusable form of the
 * schedule planner's sync.
 *
 *  - Mounts a `ShapeChangeWatcher` for INSTANT push over the shared Reverb
 *    WebSocket (no held-connection cost, so it's always on when enabled).
 *  - Also SHORT-POLLS `pollUrl` (an endpoint returning `{ last_updated }`) every
 *    `intervalMs` as a cross-window fallback and calls `onRefresh` on change.
 *
 * `onRefresh` should be idempotent and guarded by the caller (e.g. skip while
 * the user is mid-drag/save, then flush).
 *
 * Props:
 *   pollUrl   — JSON endpoint returning { last_updated } (fallback poll)
 *   shape     — collection to watch (default work_orders_all)
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

    // Instant push over the shared Reverb socket — always on when enabled.
    if (!enabled) return null;
    return <ShapeChangeWatcher shape={shape} onChange={() => cbRef.current?.()} />;
}
