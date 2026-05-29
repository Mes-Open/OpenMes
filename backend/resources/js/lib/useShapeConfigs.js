import { useEffect, useState } from 'react';

/**
 * Fetch signed shape configs from the gatekeeper (GET /api/shapes/{name}).
 *
 * One quick authenticated request per shape — PHP authorizes and returns the
 * signed { url, params } the browser then streams from Electric (via Caddy).
 * PHP never holds the long-poll.
 *
 * Auth is the session cookie (Sanctum SPA stateful mode) — sent automatically
 * on same-origin requests. The streaming requests that follow carry the HMAC
 * signature instead.
 */
export function useShapeConfigs(names) {
    const [configs, setConfigs] = useState(null);
    const [error, setError] = useState(null);
    const key = names.join(',');

    useEffect(() => {
        let cancelled = false;

        Promise.all(
            names.map((name) =>
                fetch(`/api/shapes/${name}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then((r) => {
                        if (!r.ok) throw new Error(`shape ${name}: HTTP ${r.status}`);
                        return r.json();
                    })
                    // Electric's client wraps url in `new URL()`, which rejects
                    // relative paths — absolutize against the current origin.
                    .then((cfg) => [name, { ...cfg, url: window.location.origin + cfg.url }]),
            ),
        )
            .then((entries) => {
                if (!cancelled) setConfigs(Object.fromEntries(entries));
            })
            .catch((e) => {
                if (!cancelled) setError(e);
            });

        return () => {
            cancelled = true;
        };
    }, [key]);

    return { configs, error };
}
