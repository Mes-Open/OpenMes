import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Keeps the dashboard's `overview` prop fresh: a partial reload every
 * `intervalMs`, only while the browser tab is visible, plus one straight away
 * when the tab comes back after being hidden for longer than that.
 *
 * Returns `liveOk` — false after a refresh failed (server error, expired
 * session, network down), true again after the next one succeeds. Failures are
 * handled quietly (no Inertia error modal); the header greys its "Live" dot.
 */
export default function useOverviewRefresh(intervalMs = 60_000) {
    const [liveOk, setLiveOk] = useState(true);

    useEffect(() => {
        let visiting = false; // a user navigation / write is in flight
        let inFlight = false; // our own reload is in flight
        let lastAttempt = Date.now();

        const fail = () => {
            setLiveOk(false);
            return false; // suppress Inertia's error modal for a background poll
        };

        const refresh = () => {
            if (document.hidden || visiting || inFlight) return;
            inFlight = true;
            lastAttempt = Date.now();
            router.reload({
                only: ['overview'],
                onSuccess: () => setLiveOk(true),
                onHttpException: fail,
                onNetworkError: fail,
                onFinish: () => { inFlight = false; },
            });
        };

        // A background refresh must never cancel the user's own visit.
        const offStart = router.on('start', () => { if (!inFlight) visiting = true; });
        const offFinish = router.on('finish', () => { visiting = false; });

        const timer = setInterval(refresh, intervalMs);
        const onVisible = () => {
            if (!document.hidden && Date.now() - lastAttempt >= intervalMs) refresh();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            clearInterval(timer);
            offStart();
            offFinish();
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [intervalMs]);

    return liveOk;
}
